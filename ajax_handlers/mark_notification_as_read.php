<?php
/**
 * Mark attendance notification as read
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
    
    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    // Insert or update the read status
    $query = "
        INSERT INTO attendance_notification_read (user_id, attendance_date) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $date]);
    
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error marking notification as read: ' . $e->getMessage()]);
}
?>