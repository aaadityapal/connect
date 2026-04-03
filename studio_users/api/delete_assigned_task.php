<?php
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/activity_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['task_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing task ID']);
    exit();
}

$taskId = $data['task_id'];

try {
    // Load task and enforce creator-only delete permission
    $check = $pdo->prepare("SELECT id, created_by, status, completed_by, completion_history,
                                  project_id, project_name, stage_id, stage_number,
                                  task_description, priority, assigned_to, assigned_names,
                                  due_date, due_time, created_at
                           FROM studio_assigned_tasks
                           WHERE id = ? AND deleted_at IS NULL
                           LIMIT 1");
    $check->execute([$taskId]);
    $task = $check->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'Task not found or already deleted']);
        exit();
    }

    if ((int)$task['created_by'] !== (int)$user_id) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Only the task assigner can delete this task']);
        exit();
    }

    $assignedIds = array_values(array_filter(
        array_map('intval', array_map('trim', explode(',', (string)($task['assigned_to'] ?? '')))),
        function ($v) { return $v > 0; }
    ));

    // Block delete if any assignee has completed/marked completion
    $completedByRaw = trim((string)($task['completed_by'] ?? ''));
    $completedByIds = array_values(array_filter(array_map('trim', explode(',', $completedByRaw)), function ($v) {
        return $v !== '';
    }));

    $completionHistoryRaw = $task['completion_history'] ?? null;
    $completionHistory = [];
    if (!empty($completionHistoryRaw)) {
        $decoded = json_decode((string)$completionHistoryRaw, true);
        if (is_array($decoded)) {
            $completionHistory = $decoded;
        }
    }

    $hasAnyCompletion = !empty($completedByIds) || !empty($completionHistory) || (($task['status'] ?? '') === 'Completed');
    if ($hasAnyCompletion) {
        $isGroupTask = count($assignedIds) > 1;
        $errorMessage = $isGroupTask
            ? 'Task cannot be deleted because at least one assignee has already marked it as completed'
            : 'Task cannot be deleted because this user has already done the task';

        echo json_encode(['success' => false, 'error' => $errorMessage]);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE studio_assigned_tasks SET deleted_at = NOW() WHERE id = ? AND created_by = ? AND deleted_at IS NULL");
    $stmt->execute([$taskId, $user_id]);

    if ($stmt->rowCount() > 0) {
        // Log activity for creator + all assignees so it appears in notifications
        $title = trim((string)($task['project_name'] ?? ''));
        if (!empty($task['stage_number'])) {
            $title .= ($title !== '' ? ' — ' : '') . 'Stage ' . $task['stage_number'];
        }
        if ($title === '') {
            $title = trim((string)($task['task_description'] ?? ''));
        }
        if ($title === '') {
            $title = 'Task #' . $taskId;
        }

        $recipientIds = array_values(array_unique(array_merge([(int)$user_id], $assignedIds)));

        $metadata = [
            'task_id' => (int)$taskId,
            'project_id' => !empty($task['project_id']) ? (int)$task['project_id'] : null,
            'project_name' => $task['project_name'] ?? null,
            'stage_id' => !empty($task['stage_id']) ? (int)$task['stage_id'] : null,
            'stage_number' => $task['stage_number'] ?? null,
            'title' => $title,
            'task_description' => $task['task_description'] ?? null,
            'priority' => $task['priority'] ?? null,
            'assigned_to' => $assignedIds,
            'assigned_names' => $task['assigned_names'] ?? null,
            'due_date' => $task['due_date'] ?? null,
            'due_time' => $task['due_time'] ?? null,
            'status_before_delete' => $task['status'] ?? null,
            'created_by' => (int)($task['created_by'] ?? 0),
            'created_at' => $task['created_at'] ?? null,
            'deleted_by' => (int)$user_id,
            'deleted_at' => date('Y-m-d H:i:s')
        ];

        foreach ($recipientIds as $recipientId) {
            if ($recipientId <= 0) continue;

            $desc = ($recipientId === (int)$user_id)
                ? 'You deleted task: "' . $title . '"'
                : 'Task deleted by assigner: "' . $title . '"';

            logUserActivity(
                $pdo,
                $recipientId,
                'task_deleted',
                'task',
                $desc,
                (int)$taskId,
                $metadata
            );
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Task not found or already deleted']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error', 'details' => $e->getMessage()]);
}
?>
