<?php
/**
 * api/extend_recurrence.php
 * Extends a recurring task by one full cycle (adds recurrence_extra + 1).
 * Each extension adds another full batch of instances equal to the base limit.
 *
 * POST  { task_id: int }
 */
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input  = json_decode(file_get_contents('php://input'), true);
$taskId = intval($input['task_id'] ?? 0);

if ($taskId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid task ID']);
    exit();
}

try {
    // Only the creator OR an assignee of the task can extend it
    $userId = intval($_SESSION['user_id']);

    $check = $pdo->prepare("SELECT id, recurrence_freq, recurrence_extra FROM studio_assigned_tasks
                             WHERE id = ? AND deleted_at IS NULL AND is_recurring = 1");
    $check->execute([$taskId]);
    $task = $check->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'Recurring task not found']);
        exit();
    }

    // Increment recurrence_extra by 1 (adds another full cycle)
    $stmt = $pdo->prepare("UPDATE studio_assigned_tasks SET recurrence_extra = recurrence_extra + 1 WHERE id = ?");
    $stmt->execute([$taskId]);

    $newExtra = intval($task['recurrence_extra']) + 1;

    // Log the extension to global_activity_logs
    try {
        $logStmt = $pdo->prepare(
            "INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
             VALUES
                (:uid, 'recurrence_extended', 'task', :eid, :desc, :meta, NOW(), 0, 0)"
        );
        $logStmt->execute([
            'uid'  => $userId,
            'eid'  => $taskId,
            'desc' => "Recurring task recurrence extended (cycle #{$newExtra}): freq = {$task['recurrence_freq']}",
            'meta' => json_encode(['task_id' => $taskId, 'freq' => $task['recurrence_freq'], 'extension_cycle' => $newExtra]),
        ]);
    } catch (Exception $le) {
        // Non-fatal
    }

    echo json_encode([
        'success'          => true,
        'message'          => 'Recurrence extended successfully',
        'task_id'          => $taskId,
        'recurrence_extra' => $newExtra,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error', 'details' => $e->getMessage()]);
}
?>
