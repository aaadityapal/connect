<?php
/**
 * approve_task_completion.php
 * Marks a completed task as approved by its creator.
 * This removes it from the "Approvals" list in the upcoming deadline modal.
 */
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/activity_helper.php';
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

    if (($row['status'] ?? '') !== 'Completed') {
        echo json_encode(['success' => false, 'error' => 'Task is not completed yet']);
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

    $assignedToRaw = (string)($row['assigned_to'] ?? '');
    $assigneeIds = array_values(array_filter(
        array_map('intval', array_map('trim', explode(',', $assignedToRaw))),
        fn($v) => $v > 0
    ));

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
        'approved_at' => date('Y-m-d H:i:s')
    ];

    logUserActivity(
        $pdo,
        $userId,
        'task_completion_approved',
        'task',
        'Approved completion: ' . '"' . $title . '"',
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
            'Completion approved by creator: ' . '"' . $title . '"',
            $taskId,
            $metadata
        );
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
