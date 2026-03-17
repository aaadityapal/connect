<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Default to today if no date supplied
    $date = (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']))
            ? $_GET['date']
            : date('Y-m-d');

    $query = "SELECT 
                sat.id,
                sat.project_id,
                sat.stage_id,
                sat.project_name,
                sat.stage_number,
                sat.task_description,
                sat.priority,
                sat.assigned_to,
                sat.assigned_names,
                sat.due_date,
                sat.due_time,
                sat.completion_history,
                sat.status,
                sat.completed_by,
                sat.is_recurring,
                sat.recurrence_freq,
                sat.created_at,
                u.username as created_by_name
              FROM studio_assigned_tasks sat
              LEFT JOIN users u ON sat.created_by = u.id
              WHERE sat.deleted_at IS NULL
                AND DATE(sat.created_at) = :date
              ORDER BY sat.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':date', $date);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also return the queried date so JS can confirm what was fetched
    $queriedDate = $date;

    // Format dates and times for display
    foreach ($tasks as &$task) {
        if ($task['due_date']) {
            $task['due_date_formatted'] = date('M j, Y', strtotime($task['due_date']));
        } else {
            $task['due_date_formatted'] = null;
        }
        if ($task['due_time']) {
            $task['due_time_formatted'] = date('h:i A', strtotime($task['due_time']));
        } else {
            $task['due_time_formatted'] = null;
        }
        $task['created_at_formatted'] = date('M j, Y g:i A', strtotime($task['created_at']));
    }

    echo json_encode([
        'success'      => true,
        'tasks'        => $tasks,
        'count'        => count($tasks),
        'queried_date' => $queriedDate
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
