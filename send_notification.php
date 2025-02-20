<?php
session_start();
require_once 'config/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $query = "INSERT INTO notifications (employee_id, task_id, message, created_at) 
              VALUES (?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", 
        $data['employee_id'],
        $data['task_id'],
        $data['message']
    );
    
    $success = $stmt->execute();
    
    echo json_encode(['success' => $success]);
}
?>
