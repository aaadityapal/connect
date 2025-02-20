<?php
require_once 'config/db_connect.php';

header('Content-Type: application/json');

try {
    $today = date('Y-m-d');
    
    $query = "SELECT a.*, u.name as user_name 
              FROM attendance a 
              JOIN users u ON a.user_id = u.id 
              WHERE DATE(a.date) = ? 
              AND a.punch_in IS NOT NULL 
              AND a.status = 'Present'
              ORDER BY a.punch_in DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $attendance_data = [];
    
    while ($row = $result->fetch_assoc()) {
        $attendance_data[] = [
            'user_name' => $row['user_name'],
            'punch_in' => $row['punch_in'],
            'punch_out' => $row['punch_out'],
            'working_hours' => $row['working_hours'],
            'overtime_hours' => $row['overtime_hours'],
            'status' => $row['status'],
            'location' => $row['location'],
            'shift_time' => $row['shift_time']
        ];
    }
    
    echo json_encode($attendance_data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch attendance data']);
}
?> 