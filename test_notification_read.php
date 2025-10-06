<?php
// Test script for notification read functionality
require_once 'config/db_connect.php';

// Test data
$user_id = 1; // Test user ID
$test_date = date('Y-m-d');

try {
    // Test inserting a read notification
    $query = "
        INSERT INTO attendance_notification_read (user_id, attendance_date, read_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE read_at = NOW()
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $test_date]);
    
    echo "Successfully marked notification as read for user $user_id on date $test_date\n";
    
    // Test checking read status
    $check_query = "
        SELECT attendance_date 
        FROM attendance_notification_read 
        WHERE user_id = ? 
        AND attendance_date = ?
    ";
    
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$user_id, $test_date]);
    $result = $check_stmt->fetch();
    
    if ($result) {
        echo "Confirmed: Notification for $test_date is marked as read\n";
    } else {
        echo "Error: Notification not found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>