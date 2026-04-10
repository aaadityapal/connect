<?php
/**
 * approve_task_completion.php
 * Marks a completed task as approved by its creator.
 * This removes it from the "Approvals" list in the upcoming deadline modal.
 */
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/activity_helper.php';
require_once '../../includes/profile_completion_helper.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$taskIdRaw = $input['task_id'] ?? null;
$taskId = is_numeric($taskIdRaw) ? (int)$taskIdRaw : 0;

if (!$taskId) {
    echo json_encode(['success' => false, 'error' => 'Invalid task_id']);
    exit();
}

function ensureApprovalTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS studio_task_completion_approvals (
            id INT(11) NOT NULL AUTO_INCREMENT,
            task_id INT(11) NOT NULL,
            approved_by INT(11) NOT NULL,
            approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_task_id (task_id),
            KEY idx_approved_by (approved_by),
            KEY idx_approved_at (approved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

try {
    ensureApprovalTable($pdo);

    // Ensure the task exists and the current user is the creator
    $check = $pdo->prepare("
        SELECT id,
               created_by,
               status,
               assigned_to,
               assigned_names,
               completed_by,
               completed_at,
               completion_history,
               project_name,
               stage_number,
               task_description
        FROM studio_assigned_tasks
        WHERE id = ? AND deleted_at IS NULL
        LIMIT 1
    ");
    $check->execute([$taskId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit();
    }

    if ((int)$row['created_by'] !== $userId) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit();
    }

    $completedByRaw = trim((string)($row['completed_by'] ?? ''));
    if ($completedByRaw === '') {
        echo json_encode(['success' => false, 'error' => 'No assignee completion to approve yet']);
        exit();
    }

    // Approve (idempotent)
    $stmt = $pdo->prepare("
        INSERT INTO studio_task_completion_approvals (task_id, approved_by, approved_at)
        VALUES (:task_id, :approved_by, NOW())
        ON DUPLICATE KEY UPDATE approved_by = VALUES(approved_by), approved_at = VALUES(approved_at)
    ");
    $stmt->execute([
        ':task_id' => $taskId,
        ':approved_by' => $userId,
    ]);

    // Log activity (creator + assignees)
    $title = trim(((string)($row['project_name'] ?? '')));
    if (!empty($row['stage_number'])) {
        $title .= ($title !== '' ? ' — ' : '') . 'Stage ' . $row['stage_number'];
    }
    if ($title === '') {
        $title = trim((string)($row['task_description'] ?? ''));
    }
    if ($title === '') {
        $title = 'Task #' . $taskId;
    }

    // Resolve approver (creator) username for richer notification text
    $approverName = 'User ' . $userId;
    try {
        $uStmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $uName = $uStmt->fetchColumn();
        if (!empty($uName)) $approverName = (string)$uName;
    } catch (Exception $e) {
        // non-fatal fallback to User <id>
    }

    $assignedToRaw = (string)($row['assigned_to'] ?? '');
    $assigneeIds = array_values(array_filter(
        array_map('intval', array_map('trim', explode(',', $assignedToRaw))),
        fn($v) => $v > 0
    ));

    // Build a reliable id -> username map from users table (fallback to snapshot/fallback label)
    $assigneeNameById = [];
    if (!empty($assigneeIds)) {
        try {
            $ph = implode(',', array_fill(0, count($assigneeIds), '?'));
            $nStmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN ($ph)");
            $nStmt->execute($assigneeIds);
            foreach ($nStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $assigneeNameById[(int)$u['id']] = (string)$u['username'];
            }
        } catch (Exception $e) {
            // non-fatal
        }
    }

    // Snapshot fallback for any unresolved names
    $assignedNamesArr = array_values(array_filter(array_map('trim', explode(',', (string)($row['assigned_names'] ?? '')))));
    for ($i = 0; $i < count($assigneeIds); $i++) {
        $id = $assigneeIds[$i];
        if (!isset($assigneeNameById[$id])) {
            $assigneeNameById[$id] = $assignedNamesArr[$i] ?? ('User ' . $id);
        }
    }

    // Resolve who completed (prefer latest completion from history)
    $completedIds = array_values(array_filter(
        array_map('intval', array_map('trim', explode(',', (string)($row['completed_by'] ?? '')))),
        fn($v) => $v > 0
    ));
    $history = $row['completion_history'] ? json_decode($row['completion_history'], true) : [];
    if (!is_array($history)) $history = [];

    $latestCompleterId = null;
    $latestTs = 0;
    foreach ($completedIds as $cid) {
        $rawTs = $history[(string)$cid] ?? ($history[$cid] ?? null);
        $ts = $rawTs ? strtotime($rawTs) : 0;
        if ($ts > $latestTs) {
            $latestTs = $ts;
            $latestCompleterId = $cid;
        }
    }
    if ($latestCompleterId === null && !empty($completedIds)) {
        $latestCompleterId = end($completedIds);
    }

    $latestCompleterName = $latestCompleterId ? ($assigneeNameById[$latestCompleterId] ?? ('User ' . $latestCompleterId)) : 'Assignee';

    $metadata = [
        'task_id' => $taskId,
        'status' => $row['status'] ?? null,
        'title' => $title,
        'created_by' => (int)($row['created_by'] ?? 0),
        'assigned_to' => $assigneeIds,
        'assigned_names' => $row['assigned_names'] ?? null,
        'completed_by' => $row['completed_by'] ?? null,
        'completed_at' => $row['completed_at'] ?? null,
        'completion_history' => $row['completion_history'] ? json_decode($row['completion_history'], true) : null,
        'decision' => 'approved',
        'approved_by' => $userId,
        'approved_by_name' => $approverName,
        'approved_at' => date('Y-m-d H:i:s'),
        'latest_completed_by_user_id' => $latestCompleterId,
        'latest_completed_by_user_name' => $latestCompleterName
    ];

    logUserActivity(
        $pdo,
        $userId,
        'task_completion_approved',
        'task',
        'Approved completion: ' . '"' . $title . '" (completed by ' . $latestCompleterName . ') by ' . $approverName . '.',
        $taskId,
        $metadata
    );

    foreach ($assigneeIds as $assigneeId) {
        if ($assigneeId === $userId) continue;
        logUserActivity(
            $pdo,
            $assigneeId,
            'task_completion_approved',
            'task',
            'Completion approved by ' . $approverName . ': ' . '"' . $title . '" (completed by ' . $latestCompleterName . ').',
            $taskId,
            $metadata
        );
    }

    // ── Smart Automation: Chain profile reminders until 90% completion ─────────
    $isProfileReminderTask = (
        strtolower(trim((string)($row['project_name'] ?? ''))) === strtolower('ArchitectsHive Systems')
        && strtolower(trim((string)($row['task_description'] ?? ''))) === strtolower('Complete your profile as soon as possible')
    );

    if ($isProfileReminderTask) {
        // Target assignee: this reminder task is expected to be single-user; fallback to first assignee.
        $targetUserId = 0;
        if (!empty($assigneeIds)) {
            $targetUserId = (int)$assigneeIds[0];
        }

        if ($targetUserId > 0) {
            $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$targetUserId]);
            $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);

            if ($targetUser) {
                $computedPct = compute_profile_completion_percent($targetUser);
                $storedPct = isset($targetUser['profile_completion_percent']) ? (int)$targetUser['profile_completion_percent'] : -1;
                if ($storedPct !== $computedPct) {
                    $syncStmt = $pdo->prepare("UPDATE users SET profile_completion_percent = ? WHERE id = ?");
                    $syncStmt->execute([$computedPct, $targetUserId]);
                }

                // If below 90, schedule next-day reminder due by 06:00 PM.
                if ($computedPct < 90) {
                    $nextDueDate = date('Y-m-d', strtotime('+1 day'));
                    $dueTime = '18:00:00';
                    $projectName = 'ArchitectsHive Systems';
                    $taskDescription = 'Complete your profile as soon as possible';
                    $assignedToStr = (string)$targetUserId;
                    $assignedName = trim((string)($targetUser['username'] ?? ('User ' . $targetUserId)));

                    // Smart guard 1: if any active/open reminder already exists (including extended), do not create another.
                    $openCheck = $pdo->prepare("SELECT id FROM studio_assigned_tasks
                        WHERE deleted_at IS NULL
                          AND project_name = :project_name
                          AND task_description = :task_description
                          AND (
                            assigned_to = :assigned_to_exact
                            OR FIND_IN_SET(:assigned_to_csv, REPLACE(IFNULL(assigned_to, ''), ' ', '')) > 0
                          )
                          AND status IN ('Pending','In Progress')
                        ORDER BY id DESC
                        LIMIT 1");
                    $openCheck->execute([
                        ':project_name' => $projectName,
                        ':task_description' => $taskDescription,
                        ':assigned_to_exact' => $assignedToStr,
                        ':assigned_to_csv' => $assignedToStr,
                    ]);
                    $openExisting = $openCheck->fetch(PDO::FETCH_ASSOC);

                    if (!$openExisting) {
                        // Smart guard 2: avoid duplicate insertion for the same next-day due slot.
                        $dupCheck = $pdo->prepare("SELECT id FROM studio_assigned_tasks
                            WHERE deleted_at IS NULL
                              AND project_name = :project_name
                              AND task_description = :task_description
                              AND due_date = :due_date
                              AND due_time = :due_time
                              AND (
                                assigned_to = :assigned_to_exact
                                OR FIND_IN_SET(:assigned_to_csv, REPLACE(IFNULL(assigned_to, ''), ' ', '')) > 0
                              )
                            ORDER BY id DESC
                            LIMIT 1");
                        $dupCheck->execute([
                            ':project_name' => $projectName,
                            ':task_description' => $taskDescription,
                            ':due_date' => $nextDueDate,
                            ':due_time' => $dueTime,
                            ':assigned_to_exact' => $assignedToStr,
                            ':assigned_to_csv' => $assignedToStr,
                        ]);
                        $duplicate = $dupCheck->fetch(PDO::FETCH_ASSOC);

                        if (!$duplicate) {
                            $ins = $pdo->prepare("INSERT INTO studio_assigned_tasks
                                (project_id, project_name, stage_id, stage_number, task_description, priority, assigned_to, assigned_names, due_date, due_time, is_recurring, status, created_by, created_at)
                                VALUES
                                (NULL, :project_name, NULL, NULL, :task_description, 'Medium', :assigned_to, :assigned_names, :due_date, :due_time, 0, 'Pending', :created_by, NOW())");

                            $ins->execute([
                                ':project_name' => $projectName,
                                ':task_description' => $taskDescription,
                                ':assigned_to' => $assignedToStr,
                                ':assigned_names' => $assignedName,
                                ':due_date' => $nextDueDate,
                                ':due_time' => $dueTime,
                                ':created_by' => $userId,
                            ]);

                            $newTaskId = (int)$pdo->lastInsertId();
                            $autoMeta = [
                                'automation' => 'profile_reminder_chain',
                                'trigger' => 'task_completion_approved',
                                'trigger_task_id' => $taskId,
                                'new_task_id' => $newTaskId,
                                'employee_id' => $targetUserId,
                                'employee_name' => $assignedName,
                                'profile_completion_percent' => $computedPct,
                                'required_percent' => 90,
                                'due_date' => $nextDueDate,
                                'due_time' => $dueTime,
                            ];

                            logUserActivity(
                                $pdo,
                                $userId,
                                'profile_reminder_auto_created',
                                'task',
                                "Auto-created next profile reminder for {$assignedName} (completion: {$computedPct}%).",
                                $newTaskId,
                                $autoMeta
                            );

                            if ($targetUserId !== $userId) {
                                logUserActivity(
                                    $pdo,
                                    $targetUserId,
                                    'profile_reminder_received',
                                    'task',
                                    'A new profile completion reminder has been assigned automatically.',
                                    $newTaskId,
                                    $autoMeta
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
