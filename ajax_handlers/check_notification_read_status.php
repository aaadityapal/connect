<?php
/**
 * Check if attendance notifications have been read or submitted
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
    // Get the dates from the request
    $dates = $_POST['dates'] ?? [];
    
    if (empty($dates)) {
        echo json_encode(['success' => false, 'message' => 'No dates provided']);
        exit;
    }
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($dates) - 1) . '?';
    
    // Query to check which dates have been read
    $read_query = "
        SELECT attendance_date 
        FROM attendance_notification_read 
        WHERE user_id = ? 
        AND attendance_date IN ($placeholders)
    ";
    
    $params = array_merge([$user_id], $dates);
    $stmt = $conn->prepare($read_query);
    $stmt->execute($params);
    $read_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Query to check which dates have submitted missing punch requests (any status except null)
    $submitted_query = "
        SELECT date, 'in' as type FROM missing_punch_in 
        WHERE user_id = ? AND date IN ($placeholders) AND status IS NOT NULL
        UNION
        SELECT date, 'out' as type FROM missing_punch_out 
        WHERE user_id = ? AND date IN ($placeholders) AND status IS NOT NULL
    ";
    
    $submitted_params = array_merge([$user_id], $dates, [$user_id], $dates);
    $submitted_stmt = $conn->prepare($submitted_query);
    $submitted_stmt->execute($submitted_params);
    $submitted_records = $submitted_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create separate arrays for punch in and punch out submitted dates
    $submitted_punch_in_dates = [];
    $submitted_punch_out_dates = [];
    
    foreach ($submitted_records as $record) {
        if ($record['type'] === 'in') {
            $submitted_punch_in_dates[] = $record['date'];
        } else if ($record['type'] === 'out') {
            $submitted_punch_out_dates[] = $record['date'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'read_dates' => $read_dates,
        'submitted_punch_in_dates' => $submitted_punch_in_dates,
        'submitted_punch_out_dates' => $submitted_punch_out_dates
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking status: ' . $e->getMessage()]);
}
?>