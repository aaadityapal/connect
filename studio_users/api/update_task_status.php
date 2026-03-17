<?php
// =====================================================
// api/update_task_status.php
// Updates the status of a task (Pending / In Progress /
// Completed / Cancelled) for the logged-in user
// =====================================================
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = intval($_SESSION['user_id']);

// Accept JSON body
$input  = json_decode(file_get_contents('php://input'), true);
$taskId = isset($input['task_id']) ? intval($input['task_id']) : 0;
$status = isset($input['status'])  ? trim($input['status'])    : '';

// Validate
$allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
if (!$taskId || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

try {
    // Make sure the task is actually assigned to this user before updating
    $check = $pdo->prepare("
        SELECT id, project_name, stage_number, assigned_to, completed_by, completion_history, status 
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

    $assignedToArr = array_filter(array_map('trim', explode(',', $taskRow['assigned_to'])));
    $completedByArr = array_filter(array_map('trim', explode(',', $taskRow['completed_by'] ?? '')));
    $history = json_decode($taskRow['completion_history'] ?? '{}', true);

    // If 'Completed' was requested by frontend, mark it done for THIS user
    if ($status === 'Completed') {
        if (!in_array($userId, $completedByArr)) {
            $completedByArr[] = $userId;
            $history[$userId] = date('Y-m-d H:i:s'); // Track individual time
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
    $completedAtUpdate = $allCompleted ? "completed_at = NOW()," : "completed_at = NULL,";

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

    // ── Log Activity ────────────────────────────────────────────────────────
    $title = trim(($taskRow['project_name'] ?: 'General Task') . ($taskRow['stage_number'] ? ' — Stage ' . $taskRow['stage_number'] : ''));
    $actionType = ($status === 'Completed') ? 'task_completed' : 'task_status_changed';
    $descPrefix = ($status === 'Completed') ? 'Task completed: ' : "Task marked as {$status}: ";

    try {
        $logStmt = $pdo->prepare("
            INSERT INTO global_activity_logs
            (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
            VALUES
            (:user_id, :action_type, 'task', :entity_id, :description, :metadata, NOW(), 0)
        ");
        $logStmt->execute([
            ':user_id'     => $userId,
            ':action_type' => $actionType,
            ':entity_id'   => $taskId,
            ':description' => $descPrefix . '"' . $title . '"',
            ':metadata'    => json_encode(['status' => $status, 'title' => $title])
        ]);
    } catch (Exception $e) {} // non-fatal
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
