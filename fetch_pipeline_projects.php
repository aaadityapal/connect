<?php
require_once 'config/db_connect.php';

try {
    $sql = "SELECT 
                project_name,
                project_type,
                status,
                start_date
            FROM projects 
            WHERE status IN ('In Progress', 'Pending')
            ORDER BY start_date DESC
            LIMIT 10";
            
    $result = $conn->query($sql);
    $projects = [];
    
    while($row = $result->fetch_assoc()) {
        $projects[] = [
            'project_name' => $row['project_name'],
            'project_type' => $row['project_type'],
            'status' => $row['status'],
            'start_date' => date('d M Y', strtotime($row['start_date']))
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($projects);

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?> 