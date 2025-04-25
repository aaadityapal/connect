<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not authenticated']));
}

// Get the request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// Validate required fields
if (!isset($data['file_id']) || !isset($data['action'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

try {
    // Insert log record
    $stmt = $pdo->prepare("
        INSERT INTO file_activity_logs 
        (file_id, user_id, action_type, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $data['file_id'],
        $_SESSION['user_id'],
        $data['action'],
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);

    echo json_encode(['success' => true, 'message' => 'Activity logged successfully']);
} catch (Exception $e) {
    // Silently fail for logging - don't disrupt user experience
    error_log('Error logging file activity: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to log activity']);
}
?> 