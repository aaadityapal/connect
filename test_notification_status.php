<?php
/**
 * Test script to verify notification status functionality
 */

// Include database connection
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

try {
    global $conn;
    
    // Test user ID (you may need to change this)
    $user_id = 1;
    
    // Test dates
    $test_dates = ['2025-10-01', '2025-10-02', '2025-10-03'];
    
    // Insert test data into missing_punch_in table with pending status
    foreach ($test_dates as $date) {
        $query = "INSERT IGNORE INTO missing_punch_in (user_id, date, punch_in_time, reason, confirmed, status) VALUES (?, ?, '09:00:00', 'Test reason', 1, 'pending')";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $date]);
    }
    
    // Test the check_notification_read_status.php functionality
    // Simulate POST request
    $_POST['dates'] = $test_dates;
    $_SESSION['user_id'] = $user_id;
    
    // Include the check script
    ob_start();
    include 'ajax_handlers/check_notification_read_status.php';
    $output = ob_get_clean();
    
    echo "Test Results:\n";
    echo "Inserted test data for dates: " . implode(', ', $test_dates) . "\n";
    echo "Response from check_notification_read_status.php:\n";
    echo $output;
    
    // Clean up test data
    foreach ($test_dates as $date) {
        $query = "DELETE FROM missing_punch_in WHERE user_id = ? AND date = ? AND reason = 'Test reason'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $date]);
    }
    
    echo "\nTest data cleaned up.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>