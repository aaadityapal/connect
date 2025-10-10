<?php
/**
 * Test script for the notification system
 */
session_start();

// For testing purposes, set a user ID
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Test user ID
}

// Include the necessary files
require_once 'config/db_connect.php';

// Test the get_missing_punches functionality
echo "<h2>Testing Missing Punches Retrieval</h2>\n";

// Simulate the get_missing_punches.php functionality
try {
    $user_id = $_SESSION['user_id'];
    
    // Calculate date 15 days ago (including today)
    $date_15_days_ago = date('Y-m-d', strtotime('-15 days'));
    $today = date('Y-m-d');
    
    // Fetch all attendance records for the last 15 days
    $query = "
        SELECT 
            id,
            user_id,
            date,
            punch_in,
            punch_out,
            approval_status,
            created_at
        FROM attendance 
        WHERE user_id = ? 
        AND date >= ?
        ORDER BY date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $date_15_days_ago]);
    $attendance_records = $stmt->fetchAll();
    
    echo "<p>Found " . count($attendance_records) . " attendance records</p>\n";
    
    // Display the records
    foreach ($attendance_records as $record) {
        echo "<p>Date: " . $record['date'] . " - Punch In: " . ($record['punch_in'] ?? 'MISSING') . " - Punch Out: " . ($record['punch_out'] ?? 'MISSING') . "</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}

// Test the check_notification_read_status functionality
echo "<h2>Testing Notification Read Status</h2>\n";

try {
    // Get some test dates
    $test_dates = [date('Y-m-d'), date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-2 days'))];
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($test_dates) - 1) . '?';
    
    // Query to check which dates have been read
    $read_query = "
        SELECT attendance_date 
        FROM attendance_notification_read 
        WHERE user_id = ? 
        AND attendance_date IN ($placeholders)
    ";
    
    $params = array_merge([$_SESSION['user_id']], $test_dates);
    $stmt = $pdo->prepare($read_query);
    $stmt->execute($params);
    $read_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Read dates: " . implode(', ', $read_dates) . "</p>\n";
    
    // Query to check which dates have submitted missing punch requests (any status except null)
    $submitted_query = "
        SELECT date, 'in' as type FROM missing_punch_in 
        WHERE user_id = ? AND date IN ($placeholders) AND status IS NOT NULL
        UNION
        SELECT date, 'out' as type FROM missing_punch_out 
        WHERE user_id = ? AND date IN ($placeholders) AND status IS NOT NULL
    ";
    
    $submitted_params = array_merge([$_SESSION['user_id']], $test_dates, [$_SESSION['user_id']], $test_dates);
    $submitted_stmt = $pdo->prepare($submitted_query);
    $submitted_stmt->execute($submitted_params);
    $submitted_records = $submitted_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Submitted records: " . count($submitted_records) . "</p>\n";
    foreach ($submitted_records as $record) {
        echo "<p>Date: " . $record['date'] . " - Type: " . $record['type'] . "</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}

echo "<h2>Test Complete</h2>\n";
?>