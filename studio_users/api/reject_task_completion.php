<?php
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/activity_helper.php';

header('Content-Type: application/json');

// Keep timestamps consistent across the app
date_default_timezone_set('Asia/Kolkata');

function ensureRejectCountColumn(PDO $pdo): void {
    $q = $pdo->query("SHOW COLUMNS FROM studio_assigned_tasks LIKE 'completion_reject_count'");
    $exists = $q && $q->fetch(PDO::FETCH_ASSOC);
    if (!$exists) {
        $pdo->exec("ALTER TABLE studio_assigned_tasks ADD COLUMN completion_reject_count INT NOT NULL DEFAULT 0");
    }
}

try {
    ensureRejectCountColumn($pdo);

    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;

    if ($taskId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid task_id']);
        exit;
    }

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    $currentUserId = (int)$_SESSION['user_id'];

    // Ensure approvals table exists (same shape as approve endpoint)
    $pdo->exec("CREATE TABLE IF NOT EXISTS studio_task_completion_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        approved_by INT NOT NULL,
        approved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_task (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Load current task
    $stmt = $pdo->prepare("SELECT id,
                                  project_name,
                                  stage_number,
                                  task_description,
                                  status,
                                  assigned_to,
                                  assigned_names,
                                  created_by,
                                  completed_by,
                                  completion_history,
                                      completion_reject_count,
                                  completed_at
                           FROM studio_assigned_tasks
                           WHERE id = ? AND deleted_at IS NULL
                           LIMIT 1");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    if ((int)$task['created_by'] !== $currentUserId) {
        echo json_encode(['success' => false, 'error' => 'Only the task creator can reject completion']);
        exit;
    }

    $completedByRaw = trim((string)($task['completed_by'] ?? ''));
    if ($completedByRaw === '') {
        echo json_encode(['success' => false, 'error' => 'No assignee completion to reject']);
        exit;
    }

    $pdo->beginTransaction();

    // Revert to Pending and clear completion fields; increment rejection counter
    $upd = $pdo->prepare("UPDATE studio_assigned_tasks
                          SET status = 'Pending',
                              completed_by = NULL,
                              completed_at = NULL,
                              completion_history = NULL,
                              completion_reject_count = COALESCE(completion_reject_count, 0) + 1,
                              updated_at = NOW(),
                              updated_by = :user_id
                          WHERE id = :id");
    $upd->execute([':user_id' => $currentUserId, ':id' => $taskId]);

    // Remove any prior approval record if present
    $del = $pdo->prepare("DELETE FROM studio_task_completion_approvals WHERE task_id = ?");
    $del->execute([$taskId]);

    // Activity logs (creator + assignees)
    $title = trim(((string)($task['project_name'] ?? '')));
    if (!empty($task['stage_number'])) {
        $title .= ($title !== '' ? ' — ' : '') . 'Stage ' . $task['stage_number'];
    }
    if ($title === '') {
        $title = trim((string)($task['task_description'] ?? ''));
    }
    if ($title === '') {
        $title = 'Task #' . $taskId;
    }

    $assignedToRaw = (string)($task['assigned_to'] ?? '');
    $assigneeIds = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $assignedToRaw))), fn($v) => $v > 0));

    $newRejectCount = ((int)($task['completion_reject_count'] ?? 0)) + 1;

    $metadata = [
        'task_id' => $taskId,
        'task_description' => $task['task_description'] ?? null,
        'from_status' => 'Completed',
        'to_status' => 'Pending',
        'created_by' => (int)$task['created_by'],
        'assigned_to' => $assigneeIds,
        'assigned_names' => $task['assigned_names'] ?? null,
        'completed_by' => $task['completed_by'] ?? null,
        'completed_at' => $task['completed_at'] ?? null,
        'completion_history' => $task['completion_history'] ? json_decode($task['completion_history'], true) : null,
        'decision' => 'rejected',
        'completion_reject_count' => $newRejectCount
    ];

    logUserActivity(
        $pdo,
        $currentUserId,
        'task_completion_rejected',
        'task',
        'Rejected completion: ' . $title,
        $taskId,
        $metadata
    );

    foreach ($assigneeIds as $assigneeId) {
        if ($assigneeId === $currentUserId) continue;
        logUserActivity(
            $pdo,
            $assigneeId,
            'task_completion_rejected',
            'task',
            'Completion rejected by creator: ' . $title,
            $taskId,
            $metadata
        );
    }

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
