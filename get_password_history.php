<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && $_SESSION['role'] !== 'Senior Manager (Studio)')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if user_id is provided
if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit();
}

$user_id = intval($_GET['user_id']);

try {
    // Get password reset history for the user
    $query = "SELECT description, generated_password, timestamp 
              FROM activity_log 
              WHERE user_id = ? 
              AND activity_type = 'password_reset' 
              AND generated_password IS NOT NULL 
              ORDER BY timestamp DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'description' => $row['description'],
            'generated_password' => $row['generated_password'],
            'timestamp' => date('M d, Y h:i A', strtotime($row['timestamp']))
        ];
    }
    
    echo json_encode($history);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch password history']);
}
?> 