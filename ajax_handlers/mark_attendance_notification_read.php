<?php
/**
 * Mark an attendance notification as read based on date
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php'; // Adjust path as needed

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = $pdo; // Use PDO connection from db_connect.php

try {
    // Get the date from the request
    $date = $_POST['date'] ?? '';
    
    if (empty($date)) {
        echo json_encode(['success' => false, 'message' => 'No date provided']);
        exit;
    }
    
    // Check if record already exists
    $check_query = "
        SELECT id 
        FROM attendance_notification_read 
        WHERE user_id = ? 
        AND attendance_date = ?
    ";
    
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute([$user_id, $date]);
    $existing_record = $check_stmt->fetch();
    
    if ($existing_record) {
        // Record already exists, return success
        echo json_encode(['success' => true, 'message' => 'Already marked as read']);
    } else {
        // Insert new record
        $insert_query = "
            INSERT INTO attendance_notification_read (user_id, attendance_date, read_at)
            VALUES (?, ?, NOW())
        ";
        
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->execute([$user_id, $date]);
        
        echo json_encode(['success' => true, 'message' => 'Marked as read']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error marking as read: ' . $e->getMessage()]);
}
?>