<?php
// =====================================================
// api/update_task_status.php
// Updates the status of a task (Pending / In Progress /
// Completed / Cancelled) for the logged-in user
// =====================================================
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/activity_helper.php';

// ── Force IST timezone so all timestamps are consistent ──
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = intval($_SESSION['user_id']);

// Accept JSON body
$input  = json_decode(file_get_contents('php://input'), true);
$rawTaskId = isset($input['task_id']) ? $input['task_id'] : 0;
$status    = isset($input['status'])  ? trim($input['status'])    : '';

// Detect virtual ID (Recurring expansion) — Format: "ID_YYYYMMDD"
$allowed   = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
$isVirtual = false;
$virtualDate = null;
$virtualHasExplicitTime = false;
if (is_string($rawTaskId) && strpos($rawTaskId, '_') !== false) {
    list($taskId, $dateTimeStr) = explode('_', $rawTaskId);
    $taskId = intval($taskId);
    $isVirtual = true;
    
    // Attempt YmdHi first (Hourly), fallback to Ymd (Daily+)
    $dt = DateTime::createFromFormat('YmdHi', $dateTimeStr);
    if ($dt) {
        $virtualHasExplicitTime = true;
    } else {
        $dt = DateTime::createFromFormat('Ymd', $dateTimeStr);
    }
    
    if ($dt) {
        $virtualDate = $dt->format('Y-m-d');
        $virtualTime = $dt->format('H:i:s');
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid virtual date format']);
        exit();
    }
} else {
    $taskId = intval($rawTaskId);
}

if (!$taskId || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

function ensureRejectCountColumn(PDO $pdo): void {
    $q = $pdo->query("SHOW COLUMNS FROM studio_assigned_tasks LIKE 'completion_reject_count'");
    $exists = $q && $q->fetch(PDO::FETCH_ASSOC);
    if (!$exists) {
        $pdo->exec("ALTER TABLE studio_assigned_tasks ADD COLUMN completion_reject_count INT NOT NULL DEFAULT 0");
    }
}

function ensureTaskUserProgressTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS studio_task_user_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_task_user (task_id, user_id),
        INDEX idx_task (task_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

try {
    ensureRejectCountColumn($pdo);
    ensureTaskUserProgressTable($pdo);

    // ── Handle Virtual Task Materialisation ──────────────────────────
    if ($isVirtual) {
        // First, check if a REAL task for this date already exists to avoid duplicates
        // (This happens if someone else already completed this recurring instance)
            if ($virtualHasExplicitTime) {
                $checkExisting = $pdo->prepare("
                    SELECT id FROM studio_assigned_tasks 
                    WHERE recurrence_parent_id = ? 
                      AND due_date = ? 
                      AND (due_time = ? OR (due_time IS NULL AND ? = '00:00:00'))
                      AND deleted_at IS NULL
                ");
                $checkExisting->execute([$taskId, $virtualDate, $virtualTime, $virtualTime]);
            } else {
                $checkExisting = $pdo->prepare("
                    SELECT id FROM studio_assigned_tasks 
                    WHERE recurrence_parent_id = ? 
                      AND due_date = ? 
                      AND deleted_at IS NULL
                ");
                $checkExisting->execute([$taskId, $virtualDate]);
            }
        $existingId = $checkExisting->fetchColumn();

        if ($existingId) {
            $taskId = $existingId; // Switch to the real task ID
        } else {
            // Materialize: Copy the original task into a new real entry for this date
            $getOrig = $pdo->prepare("SELECT * FROM studio_assigned_tasks WHERE id = ?");
            $getOrig->execute([$taskId]);
            $orig = $getOrig->fetch(PDO::FETCH_ASSOC);

            if ($orig) {
                unset($orig['id']);
                $orig['due_date']             = $virtualDate;
                $orig['due_time']             = $virtualHasExplicitTime ? $virtualTime : ($orig['due_time'] ?? null);
                $orig['is_recurring']         = 0; // The instance itself isn't a master template
                $orig['recurrence_parent_id'] = $taskId; // Link to master
                $orig['created_at']           = date('Y-m-d H:i:s');
                $orig['status']               = 'Pending'; // Start as pending
                $orig['completed_by']         = null;
                $orig['completed_at']         = null;
                $orig['completion_history']   = null;

                $cols = implode(',', array_keys($orig));
                $vals = ':' . implode(',:', array_keys($orig));
                $ins = $pdo->prepare("INSERT INTO studio_assigned_tasks ($cols) VALUES ($vals)");
                $ins->execute($orig);
                $taskId = $pdo->lastInsertId();
            } else {
                echo json_encode(['success' => false, 'error' => 'Original recurring task not found']);
                exit();
            }
        }
    }
    // ─────────────────────────────────────────────────────────────────
    // Make sure the task is actually assigned to this user before updating
    $check = $pdo->prepare("
                SELECT id,
                             project_name,
                             stage_number,
                             task_description,
                             created_by,
                             assigned_to,
                             assigned_names,
                             completed_by,
                             completed_at,
                             completion_history,
                                                         completion_reject_count,
                             status
        FROM studio_assigned_tasks
        WHERE id = :id
          AND deleted_at IS NULL
          AND FIND_IN_SET(:userId, REPLACE(assigned_to, ', ', ','))
    ");
    $check->execute([':id' => $taskId, ':userId' => $userId]);
    $taskRow = $check->fetch();

    if (!$taskRow) {
        echo json_encode(['success' => false, 'error' => 'Task not found or access denied']);
        exit();
    }

    $previousGlobalStatus = $taskRow['status'] ?? null;

    $assignedToArr = array_filter(array_map('trim', explode(',', $taskRow['assigned_to'])));
    $completedByArr = array_filter(array_map('trim', explode(',', $taskRow['completed_by'] ?? '')));
    $history = json_decode($taskRow['completion_history'] ?? '{}', true);
    $rejectCount = (int)($taskRow['completion_reject_count'] ?? 0);

    // Policy: after 2 creator rejections, assignee must extend deadline before marking done again.
    if ($status === 'Completed' && $rejectCount >= 2) {
        echo json_encode([
            'success' => false,
            'error' => 'Completion blocked: Please extend the task deadline before marking this task as done again.'
        ]);
        exit();
    }

    // If 'Completed' was requested by frontend, mark it done for THIS user
    if ($status === 'Completed') {
        if (!in_array($userId, $completedByArr)) {
            $completedByArr[] = $userId;
            // Store IST timestamp so undo timer is correct for Indian users
            $history[$userId] = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        }
    } else {
        // Otherwise, they want to undo/Pending
        $completedByArr = array_diff($completedByArr, [$userId]);
        unset($history[$userId]); // Clear individual time
    }
    $historyJson = json_encode($history);

    // Check if every assignee has now completed the task
    $allCompleted = count($assignedToArr) > 0;
    foreach ($assignedToArr as $uid) {
        if (!in_array($uid, $completedByArr)) {
            $allCompleted = false;
            break;
        }
    }

    // Compute the global state
    $newGlobalStatus = $allCompleted ? 'Completed' : (count($completedByArr) > 0 ? 'In Progress' : 'Pending');
    $completedStr = implode(',', $completedByArr);
    // Use IST-aware timestamp for completed_at
    $nowIst = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    $completedAtUpdate = $allCompleted ? "completed_at = '{$nowIst}'," : "completed_at = NULL,";

    $stmt = $pdo->prepare("
        UPDATE studio_assigned_tasks
        SET status             = :newStatus,
            completed_by       = :completedBy,
            completion_history = :history,
            $completedAtUpdate
            updated_at = NOW(),
            updated_by = :userId
        WHERE id = :id
    ");
    $stmt->execute([
        ':newStatus'   => $newGlobalStatus, 
        ':completedBy' => $completedStr, 
        ':history'     => $historyJson,
        ':userId'      => $userId, 
        ':id'          => $taskId
    ]);

    // Auto-progress rule: when a user marks task as completed,
    // set that specific user's progress to 100%.
    // IMPORTANT: reverse is intentionally not applied (100% progress should not auto-complete,
    // and undoing completion does not force progress back).
    if ($status === 'Completed') {
        $oldProgressStmt = $pdo->prepare("SELECT progress_percent FROM studio_task_user_progress WHERE task_id = ? AND user_id = ? LIMIT 1");
        $oldProgressStmt->execute([(int)$taskId, (int)$userId]);
        $oldProgressRaw = $oldProgressStmt->fetchColumn();
        $oldProgress = ($oldProgressRaw === false) ? 0 : (int)$oldProgressRaw;

        $upsertProgress = $pdo->prepare("\n            INSERT INTO studio_task_user_progress (task_id, user_id, progress_percent)\n            VALUES (:task_id, :user_id, 100)\n            ON DUPLICATE KEY UPDATE\n                progress_percent = 100,\n                updated_at = CURRENT_TIMESTAMP\n        ");
        $upsertProgress->execute([
            ':task_id' => (int)$taskId,
            ':user_id' => (int)$userId,
        ]);

        // Log progress transition caused by mark-done flow (from old -> 100)
        if ($oldProgress !== 100) {
            $actorName = 'User ' . $userId;
            try {
                $uStmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
                $uStmt->execute([$userId]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($uRow['username'])) {
                    $actorName = trim((string)$uRow['username']);
                }
            } catch (Exception $e) {}

            $progressTitle = trim(($taskRow['project_name'] ?: 'General Task') . ($taskRow['stage_number'] ? ' — Stage ' . $taskRow['stage_number'] : ''));
            if ($progressTitle === '') $progressTitle = 'General Task';
            $delta = 100 - $oldProgress;
            $deltaStr = ($delta > 0 ? '+' : '') . $delta;

            $progressMeta = [
                'task_id' => (int)$taskId,
                'title' => $progressTitle,
                'task_description' => $taskRow['task_description'] ?? null,
                'old_progress' => $oldProgress,
                'new_progress' => 100,
                'delta' => $delta,
                'updated_by' => $userId,
                'updated_by_name' => $actorName,
                'source' => 'mark_done_auto',
            ];

            try {
                logUserActivity(
                    $pdo,
                    $userId,
                    'task_progress_updated',
                    'task',
                    "You updated task progress: \"{$progressTitle}\" {$oldProgress}% → 100% ({$deltaStr}%) [auto via Mark Done]",
                    (int)$taskId,
                    array_merge($progressMeta, ['audience' => 'actor'])
                );
            } catch (Exception $e) {}

            $creatorIdForProgress = isset($taskRow['created_by']) ? (int)$taskRow['created_by'] : 0;
            if ($creatorIdForProgress > 0 && $creatorIdForProgress !== $userId) {
                try {
                    logUserActivity(
                        $pdo,
                        $creatorIdForProgress,
                        'task_progress_updated',
                        'task',
                        "{$actorName} updated task progress: \"{$progressTitle}\" {$oldProgress}% → 100% ({$deltaStr}%) [auto via Mark Done]",
                        (int)$taskId,
                        array_merge($progressMeta, ['audience' => 'creator'])
                    );
                } catch (Exception $e) {}
            }
        }

        // Keep legacy aggregate in sync for consumers that still fallback to task-level progress.
        $aggregateProgress = 0;
        if (!empty($assignedToArr)) {
            $sumProgress = 0;
            foreach ($assignedToArr as $aid) {
                $pStmt = $pdo->prepare("SELECT progress_percent FROM studio_task_user_progress WHERE task_id = ? AND user_id = ? LIMIT 1");
                $pStmt->execute([(int)$taskId, (int)$aid]);
                $pVal = $pStmt->fetchColumn();
                $sumProgress += ($pVal === false) ? 0 : (int)$pVal;
            }
            $aggregateProgress = (int)round($sumProgress / count($assignedToArr));
        }

        $aggStmt = $pdo->prepare("UPDATE studio_assigned_tasks SET progress_percent = :p WHERE id = :id");
        $aggStmt->execute([
            ':p' => $aggregateProgress,
            ':id' => (int)$taskId,
        ]);
    }

    // ── Log Activity ────────────────────────────────────────────────────────
    $title = trim(($taskRow['project_name'] ?: 'General Task') . ($taskRow['stage_number'] ? ' — Stage ' . $taskRow['stage_number'] : ''));
    $actionType = ($status === 'Completed') ? 'task_completed' : 'task_status_changed';
    $descPrefix = ($status === 'Completed') ? 'Task completed: ' : "Task marked as {$status}: ";

    try {
        logUserActivity($pdo, $userId, $actionType, 'task', $descPrefix . '"' . $title . '"', $taskId, [
            'task_id' => $taskId,
            'title' => $title,
            'requested_status' => $status,
            'previous_global_status' => $previousGlobalStatus,
            'new_global_status' => $newGlobalStatus,
            'all_assignees_completed' => $allCompleted,
            'created_by' => isset($taskRow['created_by']) ? (int)$taskRow['created_by'] : null,
            'assigned_to' => array_values(array_filter(array_map('intval', $assignedToArr), fn($v) => $v > 0)),
            'assigned_names' => $taskRow['assigned_names'] ?? null,
            'completed_by' => $completedStr,
            'completed_at' => $allCompleted ? $nowIst : null,
            'completion_history' => $history,
        ]);
    } catch (Exception $e) {} // non-fatal

    // If an assignee marked this task completed, notify creator and teammates.
    // - Partial completion: creator gets partial update; pending assignees get "still pending" update.
    // - Every completion (partial or full): creator also gets approval-needed update.
    $creatorId = isset($taskRow['created_by']) ? (int)$taskRow['created_by'] : 0;
    if ($creatorId > 0 && $creatorId !== $userId && $status === 'Completed') {
        $assignedIdsInt = array_values(array_filter(array_map('intval', $assignedToArr), fn($v) => $v > 0));
        $completedIdsInt = array_values(array_filter(array_map('intval', $completedByArr), fn($v) => $v > 0));
        $pendingIdsInt = array_values(array_filter($assignedIdsInt, fn($v) => !in_array($v, $completedIdsInt, true)));

        $assignedNamesArr = array_values(array_filter(array_map('trim', explode(',', (string)($taskRow['assigned_names'] ?? '')))));
        $idToName = [];
        for ($i = 0; $i < count($assignedIdsInt); $i++) {
            $idToName[(string)$assignedIdsInt[$i]] = $assignedNamesArr[$i] ?? ('User ' . $assignedIdsInt[$i]);
        }

        $actorName = $idToName[(string)$userId] ?? ('User ' . $userId);
        $pendingNames = array_map(function ($pid) use ($idToName) {
            return $idToName[(string)$pid] ?? ('User ' . $pid);
        }, $pendingIdsInt);
        $pendingNamesText = !empty($pendingNames) ? implode(', ', $pendingNames) : 'None';

        if (count($pendingIdsInt) > 0) {
            // Creator notification: partial completion update
            try {
                logUserActivity($pdo, $creatorId, 'task_partially_completed', 'task', 'Task partially done: ' . '"' . $title . '" — ' . $actorName . ' completed; pending: ' . $pendingNamesText . '.', $taskId, [
                    'task_id' => $taskId,
                    'title' => $title,
                    'event' => 'partial_completion',
                    'completed_by_user_id' => $userId,
                    'completed_by_user_name' => $actorName,
                    'previous_global_status' => $previousGlobalStatus,
                    'new_global_status' => $newGlobalStatus,
                    'created_by' => $creatorId,
                    'assigned_to' => $assignedIdsInt,
                    'assigned_names' => $taskRow['assigned_names'] ?? null,
                    'completed_by' => $completedStr,
                    'completed_at' => $nowIst,
                    'pending_user_ids' => $pendingIdsInt,
                    'pending_user_names' => $pendingNames,
                    'completion_history' => $history,
                ]);
            } catch (Exception $e) {} // non-fatal

            // Pending teammates notification: your part is still pending
            foreach ($pendingIdsInt as $pendingUserId) {
                if ($pendingUserId === $userId) continue;
                try {
                    logUserActivity($pdo, $pendingUserId, 'task_still_pending', 'task', 'Team update: ' . '"' . $title . '" partially completed — ' . $actorName . ' completed. Your part is still pending.', $taskId, [
                        'task_id' => $taskId,
                        'title' => $title,
                        'event' => 'pending_after_teammate_completion',
                        'completed_by_user_id' => $userId,
                        'completed_by_user_name' => $actorName,
                        'pending_user_id' => $pendingUserId,
                        'assigned_to' => $assignedIdsInt,
                        'assigned_names' => $taskRow['assigned_names'] ?? null,
                        'completed_by' => $completedStr,
                        'completion_history' => $history,
                    ]);
                } catch (Exception $e) {} // non-fatal
            }
        }

        // Creator approval-needed event for each assignee completion
        $approvalDesc = (count($pendingIdsInt) > 0)
            ? ('Task partially completed and awaiting your approval: ' . '"' . $title . '"')
            : ('Task completed and awaiting your approval: ' . '"' . $title . '"');
        try {
            logUserActivity($pdo, $creatorId, 'task_completed_for_approval', 'task', $approvalDesc, $taskId, [
                'task_id' => $taskId,
                'title' => $title,
                'event' => 'needs_approval',
                'completed_by_user_id' => $userId,
                'completed_by_user_name' => $actorName,
                'previous_global_status' => $previousGlobalStatus,
                'new_global_status' => $newGlobalStatus,
                'created_by' => $creatorId,
                'assigned_to' => $assignedIdsInt,
                'assigned_names' => $taskRow['assigned_names'] ?? null,
                'completed_by' => $completedStr,
                'completed_at' => $nowIst,
                'pending_user_ids' => $pendingIdsInt,
                'pending_user_names' => $pendingNames,
                'completion_history' => $history,
            ]);
        } catch (Exception $e) {} // non-fatal
    }
    // ────────────────────────────────────────────────────────────────────────

    echo json_encode([
        'success' => true,
        'task_id' => $taskId,
        'status'  => $status
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
