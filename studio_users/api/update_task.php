<?php
// api/update_task.php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['task_id'])) {
    echo json_encode(['success' => false, 'error' => 'No data or task id received']);
    exit();
}

$task_id = intval($input['task_id']);

try {
    $project_id         = !empty($input['project_id']) ? intval($input['project_id']) : null;
    $project_name       = !empty($input['project_name']) ? trim($input['project_name']) : null;
    $stage_id           = !empty($input['stage_id']) ? intval($input['stage_id']) : null;
    $stage_number       = !empty($input['stage_number']) ? trim($input['stage_number']) : null;
    $task_description   = !empty($input['task_description']) ? trim($input['task_description']) : '';
    $priority           = in_array($input['priority'] ?? '', ['Low', 'Medium', 'High']) ? $input['priority'] : 'Low';
    $assigned_to        = !empty($input['assigned_to']) ? trim($input['assigned_to']) : null;
    $assigned_names     = !empty($input['assigned_names']) ? trim($input['assigned_names']) : null;
    $due_date           = !empty($input['due_date']) ? $input['due_date'] : null;
    $due_time           = !empty($input['due_time']) ? $input['due_time'] : null;

    $query = "UPDATE studio_assigned_tasks SET
        project_id = :project_id,
        project_name = :project_name,
        stage_id = :stage_id,
        stage_number = :stage_number,
        task_description = :task_description,
        priority = :priority,
        assigned_to = :assigned_to,
        assigned_names = :assigned_names,
        due_date = :due_date,
        due_time = :due_time
        WHERE id = :task_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'project_id'        => $project_id,
        'project_name'      => $project_name,
        'stage_id'          => $stage_id,
        'stage_number'      => $stage_number,
        'task_description'  => $task_description,
        'priority'          => $priority,
        'assigned_to'       => $assigned_to,
        'assigned_names'    => $assigned_names,
        'due_date'          => $due_date,
        'due_time'          => $due_time,
        'task_id'           => $task_id
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error', 'details' => $e->getMessage()]);
}
?>
