<?php
header('Content-Type: application/json');
session_start();

require_once '../config/db_connect.php';

try {
    $query = "SELECT 
        id,
        username,
        designation,
        profile_picture as avatar
    FROM users 
    WHERE status = 'active' 
    AND deleted_at IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'users' => $users
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 