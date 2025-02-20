<?php
require_once 'config.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');

try {
    $query = "
        SELECT 
            d.department_name as department,
            COUNT(DISTINCT e.id) as total,
            COUNT(DISTINCT CASE WHEN a.status = 'Present' THEN e.id END) as present,
            COUNT(DISTINCT CASE WHEN a.status = 'Absent' THEN e.id END) as absent,
            COUNT(DISTINCT CASE WHEN l.status = 'Approved' AND l.from_date <= ? AND l.to_date >= ? THEN e.id END) as on_leave
        FROM departments d
        LEFT JOIN employees e ON d.id = e.department_id
        LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
        LEFT JOIN leaves l ON e.id = l.employee_id
        GROUP BY d.department_name
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('sss', $date, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    
    echo json_encode($departments);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load department overview']);
}
?>