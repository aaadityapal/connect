<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $yearMonth = $data['yearMonth'];
    
    // Debug logs
    error_log("Received yearMonth: " . $yearMonth);
    
    // Validate yearMonth format
    if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        throw new Exception('Invalid date format');
    }
    
    // Prepare and execute query
    $taskQuery = "
        SELECT 
            t.id AS task_id,
            t.description AS task_description,
            t.created_by,
            ts.id AS stage_id,
            ts.stage_number,
            ts.due_date AS stage_deadline,
            ts.assigned_to AS stage_assigned_to,
            tsub.id AS substage_id,
            tsub.description AS substage_description,
            tsub.end_date AS substage_deadline,
            u1.username AS created_by_name,
            u2.username AS assigned_to_name,
            ts.status AS stage_status
        FROM tasks t
        LEFT JOIN task_stages ts ON t.id = ts.task_id
        LEFT JOIN task_substages tsub ON ts.id = tsub.stage_id
        LEFT JOIN users u1 ON t.created_by = u1.id
        LEFT JOIN users u2 ON ts.assigned_to = u2.id
        WHERE DATE_FORMAT(ts.due_date, '%Y-%m') = :yearMonth
        ORDER BY t.id, ts.stage_number, tsub.id
    ";
    
    $stmt = $pdo->prepare($taskQuery);
    $stmt->execute(['yearMonth' => $yearMonth]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($results) . " results");
    error_log("Results: " . json_encode($results));
    
    echo json_encode($results);
    
} catch (Exception $e) {
    error_log("Error in get_tasks_by_month.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 