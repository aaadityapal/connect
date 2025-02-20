<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

try {
    $query = "
        SELECT 
            p.id,
            p.title,
            p.description,
            p.start_date,
            p.end_date,
            p.status,
            p.project_type,
            u.username as assignee_name,
            ps.stage_number,
            pss.substage_number
        FROM projects p
        LEFT JOIN users u ON p.assigned_to = u.id
        LEFT JOIN project_stages ps ON p.id = ps.project_id
        LEFT JOIN project_substages pss ON ps.id = pss.stage_id
        WHERE YEAR(p.start_date) = ? 
        AND MONTH(p.start_date) = ?
        AND p.deleted_at IS NULL
        ORDER BY p.start_date ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $row['status'],
            'assignee_name' => $row['assignee_name'],
            'stage_number' => $row['stage_number'],
            'substage_number' => $row['substage_number']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'tasks' => $tasks
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch tasks: ' . $e->getMessage()
    ]);
}

$conn->close(); 