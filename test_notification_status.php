<?php
// Simple test file to verify the notification status functionality
require_once 'config/db_connect.php';

// Test data
$user_id = 1; // Test user ID
$test_dates = ['2025-10-10', '2025-10-09', '2025-10-08'];

echo "Testing notification status functionality...\n";

// Check read status
try {
    $placeholders = str_repeat('?,', count($test_dates) - 1) . '?';
    $read_query = "
        SELECT attendance_date 
        FROM attendance_notification_read 
        WHERE user_id = ? 
        AND attendance_date IN ($placeholders)
    ";
    
    $params = array_merge([$user_id], $test_dates);
    $stmt = $pdo->prepare($read_query);
    $stmt->execute($params);
    $read_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Read dates: " . implode(', ', $read_dates) . "\n";
} catch (Exception $e) {
    echo "Error checking read status: " . $e->getMessage() . "\n";
}

// Check submitted status for missing punch in
try {
    $placeholders = str_repeat('?,', count($test_dates) - 1) . '?';
    $submitted_query = "
        SELECT date, 'in' as type FROM missing_punch_in 
        WHERE user_id = ? AND date IN ($placeholders) AND status IS NOT NULL
        UNION
        SELECT date, 'out' as type FROM missing_punch_out 
        WHERE user_id = ? AND date IN ($placeholders) AND status IS NOT NULL
    ";
    
    $submitted_params = array_merge([$user_id], $test_dates, [$user_id], $test_dates);
    $submitted_stmt = $pdo->prepare($submitted_query);
    $submitted_stmt->execute($submitted_params);
    $submitted_records = $submitted_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Submitted records: " . count($submitted_records) . "\n";
    foreach ($submitted_records as $record) {
        echo "  Date: " . $record['date'] . ", Type: " . $record['type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error checking submitted status: " . $e->getMessage() . "\n";
}

echo "Test completed.\n";
?>