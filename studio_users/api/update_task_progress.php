<?php
// api/update_task_progress.php
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/activity_helper.php';

date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$rawTaskId = $input['task_id'] ?? 0;
$progress = isset($input['progress_percent']) ? (int)$input['progress_percent'] : -1;

if ($progress < 0 || $progress > 100) {
    echo json_encode(['success' => false, 'error' => 'Progress must be between 0 and 100']);
    exit();
}

// Enforce 5% steps
$progress = (int)round($progress / 5) * 5;
$progress = max(0, min(100, $progress));

function ensureProgressColumn(PDO $pdo): void {
    $q = $pdo->query("SHOW COLUMNS FROM studio_assigned_tasks LIKE 'progress_percent'");
    $exists = $q && $q->fetch(PDO::FETCH_ASSOC);
    if (!$exists) {
        $pdo->exec("ALTER TABLE studio_assigned_tasks ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0");
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
    ensureProgressColumn($pdo);
    ensureTaskUserProgressTable($pdo);

    // Detect virtual recurring id format: "ID_YYYYMMDD" or "ID_YYYYMMDDHHii"
    $isVirtual = false;
    $virtualDate = null;
    $virtualTime = '00:00:00';
    $virtualHasExplicitTime = false;

    if (is_string($rawTaskId) && strpos($rawTaskId, '_') !== false) {
        [$taskIdPart, $dateTimeStr] = explode('_', $rawTaskId, 2);
        $taskId = (int)$taskIdPart;
        $isVirtual = true;

        $dt = DateTime::createFromFormat('YmdHi', $dateTimeStr);
        if ($dt) {
            $virtualHasExplicitTime = true;
        } else {
            $dt = DateTime::createFromFormat('Ymd', $dateTimeStr);
        }

        if (!$dt) {
            echo json_encode(['success' => false, 'error' => 'Invalid virtual task date']);
            exit();
        }

        $virtualDate = $dt->format('Y-m-d');
        $virtualTime = $dt->format('H:i:s');
    } else {
        $taskId = (int)$rawTaskId;
    }

    if ($taskId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid task ID']);
        exit();
    }

    if ($isVirtual) {
        // Reuse materialized instance if available
        if ($virtualHasExplicitTime) {
            $checkExisting = $pdo->prepare("\n                SELECT id FROM studio_assigned_tasks\n                WHERE recurrence_parent_id = ?\n                  AND due_date = ?\n                  AND (due_time = ? OR (due_time IS NULL AND ? = '00:00:00'))\n                  AND deleted_at IS NULL\n                LIMIT 1\n            ");
            $checkExisting->execute([$taskId, $virtualDate, $virtualTime, $virtualTime]);
        } else {
            $checkExisting = $pdo->prepare("\n                SELECT id FROM studio_assigned_tasks\n                WHERE recurrence_parent_id = ?\n                  AND due_date = ?\n                  AND deleted_at IS NULL\n                LIMIT 1\n            ");
            $checkExisting->execute([$taskId, $virtualDate]);
        }

        $existingId = $checkExisting->fetchColumn();

        if ($existingId) {
            $taskId = (int)$existingId;
        } else {
            // Materialize from parent so progress is per-instance
            $getOrig = $pdo->prepare("SELECT * FROM studio_assigned_tasks WHERE id = ? LIMIT 1");
            $getOrig->execute([$taskId]);
            $orig = $getOrig->fetch(PDO::FETCH_ASSOC);

            if (!$orig) {
                echo json_encode(['success' => false, 'error' => 'Original recurring task not found']);
                exit();
            }

            $parentId = $taskId;
            unset($orig['id']);
            $orig['due_date'] = $virtualDate;
            $orig['due_time'] = $virtualHasExplicitTime ? $virtualTime : ($orig['due_time'] ?? null);
            $orig['is_recurring'] = 0;
            $orig['recurrence_parent_id'] = $parentId;
            $orig['created_at'] = date('Y-m-d H:i:s');
            $orig['status'] = 'Pending';
            $orig['completed_by'] = null;
            $orig['completed_at'] = null;
            $orig['completion_history'] = null;
            $orig['progress_percent'] = 0;

            $cols = implode(',', array_keys($orig));
            $vals = ':' . implode(',:', array_keys($orig));
            $ins = $pdo->prepare("INSERT INTO studio_assigned_tasks ($cols) VALUES ($vals)");
            $ins->execute($orig);
            $taskId = (int)$pdo->lastInsertId();
        }
    }

    // Security + details for logging
        $check = $pdo->prepare("\n        SELECT id, project_name, stage_number, task_description, created_by, progress_percent, assigned_to
                FROM studio_assigned_tasks
                WHERE id = :id
                    AND deleted_at IS NULL
                    AND FIND_IN_SET(:uid, REPLACE(assigned_to, ', ', ','))
                LIMIT 1
        ");
    $check->execute([':id' => $taskId, ':uid' => $userId]);
    $taskRow = $check->fetch(PDO::FETCH_ASSOC);

    if (!$taskRow) {
        echo json_encode(['success' => false, 'error' => 'Task not found or access denied']);
        exit();
    }

    $assignedIds = array_values(array_filter(array_map('trim', explode(',', (string)($taskRow['assigned_to'] ?? ''))), function($v) {
        return $v !== '';
    }));

    $oldProgressStmt = $pdo->prepare("SELECT progress_percent FROM studio_task_user_progress WHERE task_id = ? AND user_id = ? LIMIT 1");
    $oldProgressStmt->execute([$taskId, $userId]);
    $oldProgressVal = $oldProgressStmt->fetchColumn();
    // Per-user model: if no personal row exists yet, old progress is 0 for this user.
    $oldProgress = $oldProgressVal === false ? 0 : (int)$oldProgressVal;

    if ($oldProgress === $progress) {
        echo json_encode([
            'success' => true,
            'task_id' => $taskId,
            'progress_percent' => $progress,
            'unchanged' => true,
        ]);
        exit();
    }

    $upsert = $pdo->prepare("\n        INSERT INTO studio_task_user_progress (task_id, user_id, progress_percent)\n        VALUES (:task_id, :user_id, :progress_percent)\n        ON DUPLICATE KEY UPDATE\n            progress_percent = VALUES(progress_percent),\n            updated_at = CURRENT_TIMESTAMP\n    ");
    $upsert->execute([
        ':task_id' => $taskId,
        ':user_id' => $userId,
        ':progress_percent' => $progress,
    ]);

    // Keep legacy aggregate column in sync (average across assignees)
    $aggregateProgress = 0;
    if (count($assignedIds) > 0) {
        $sum = 0;
        foreach ($assignedIds as $aid) {
            $pStmt = $pdo->prepare("SELECT progress_percent FROM studio_task_user_progress WHERE task_id = ? AND user_id = ? LIMIT 1");
            $pStmt->execute([$taskId, (int)$aid]);
            $pVal = $pStmt->fetchColumn();
            $sum += ($pVal === false) ? 0 : (int)$pVal;
        }
        $aggregateProgress = (int)round($sum / count($assignedIds));
    }

    $stmt = $pdo->prepare("\n        UPDATE studio_assigned_tasks\n        SET progress_percent = :p,\n            updated_at = NOW(),\n            updated_by = :uid\n        WHERE id = :id\n    ");
    $stmt->execute([':p' => $aggregateProgress, ':uid' => $userId, ':id' => $taskId]);

    // Activity logs / notifications (actor + creator)
    $actorName = 'User ' . $userId;
    try {
        $uStmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($uRow['username'])) {
            $actorName = trim((string)$uRow['username']);
        }
    } catch (Exception $e) {}

    $title = trim(($taskRow['project_name'] ?: 'General Task') . ($taskRow['stage_number'] ? ' — Stage ' . $taskRow['stage_number'] : ''));
    if ($title === '') $title = 'General Task';

    $delta = $progress - $oldProgress;
    $deltaStr = ($delta > 0 ? '+' : '') . $delta;

    $meta = [
        'task_id' => (int)$taskId,
        'title' => $title,
        'task_description' => $taskRow['task_description'] ?? null,
        'old_progress' => $oldProgress,
        'new_progress' => $progress,
        'delta' => $delta,
        'updated_by' => $userId,
        'updated_by_name' => $actorName,
    ];

    logUserActivity(
        $pdo,
        $userId,
        'task_progress_updated',
        'task',
        "You updated task progress: \"{$title}\" {$oldProgress}% → {$progress}% ({$deltaStr}%)",
        (int)$taskId,
        array_merge($meta, ['audience' => 'actor'])
    );

    $creatorId = isset($taskRow['created_by']) ? (int)$taskRow['created_by'] : 0;
    if ($creatorId > 0 && $creatorId !== $userId) {
        logUserActivity(
            $pdo,
            $creatorId,
            'task_progress_updated',
            'task',
            "{$actorName} updated task progress: \"{$title}\" {$oldProgress}% → {$progress}% ({$deltaStr}%)",
            (int)$taskId,
            array_merge($meta, ['audience' => 'creator'])
        );
    }

    echo json_encode([
        'success' => true,
        'task_id' => $taskId,
        'progress_percent' => $progress,
        'aggregate_progress_percent' => $aggregateProgress,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
