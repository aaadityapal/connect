<?php
require_once 'config.php';
session_start();

// Ensure no whitespace or HTML before this point
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

try {
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

    $query = "
        SELECT 
            t.id,
            t.title,
            t.description,
            ts.due_date,
            ts.status,
            ts.priority,
            s.name as stage_name,
            s.color as stage_color,
            ss.name as substage_name,
            ss.color as substage_color
        FROM tasks t
        JOIN task_stages ts ON t.id = ts.task_id
        LEFT JOIN stages s ON ts.stage_id = s.id
        LEFT JOIN substages ss ON ts.substage_id = ss.id
        WHERE YEAR(ts.due_date) = :year 
        AND MONTH(ts.due_date) = :month
        ORDER BY ts.due_date ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':year' => $year,
        ':month' => $month
    ]);

    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group tasks by date
    $tasksByDate = [];
    foreach ($tasks as $task) {
        $date = date('Y-m-d', strtotime($task['due_date']));
        if (!isset($tasksByDate[$date])) {
            $tasksByDate[$date] = [];
        }
        $tasksByDate[$date][] = [
            'id' => $task['id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'due_date' => $task['due_date'],
            'status' => $task['status'],
            'priority' => $task['priority'],
            'stage_name' => $task['stage_name'],
            'stage_color' => $task['stage_color'],
            'substage_name' => $task['substage_name'],
            'substage_color' => $task['substage_color']
        ];
    }

    // Set proper JSON header
    header('Content-Type: application/json');
    // Prevent any HTML or whitespace from being output
    echo json_encode($tasksByDate);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 