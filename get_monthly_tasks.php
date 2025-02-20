<?php
require_once 'config.php';

// Get year and month from query parameters
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Calculate the first and last day of the month
$firstDay = date('Y-m-01', strtotime("$year-$month-01"));
$lastDay = date('Y-m-t', strtotime("$year-$month-01"));

try {
    // Modify your existing query to include date filtering
    $query = "
        SELECT 
            t.id AS task_id,
            t.description AS task_description,
            t.created_by,
            ts.id AS stage_id,
            ts.stage_number,
            ts.due_date AS stage_deadline,
            ts.assigned_to AS stage_assigned_to,
            ts.status AS stage_status,
            tsub.id AS substage_id,
            tsub.description AS substage_description,
            tsub.end_date AS substage_deadline,
            u1.username AS created_by_name,
            u2.username AS assigned_to_name
        FROM tasks t
        LEFT JOIN task_stages ts ON t.id = ts.task_id
        LEFT JOIN task_substages tsub ON ts.id = tsub.stage_id
        LEFT JOIN users u1 ON t.created_by = u1.id
        LEFT JOIN users u2 ON ts.assigned_to = u2.id
        WHERE (ts.due_date BETWEEN :first_day AND :last_day)
           OR (tsub.end_date BETWEEN :first_day AND :last_day)
        ORDER BY t.id, ts.stage_number, tsub.id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':first_day' => $firstDay,
        ':last_day' => $lastDay
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($results);

} catch (PDOException $e) {
    // Handle errors
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 