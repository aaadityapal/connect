<?php
// Test script for attendance notifications
session_start();
require_once 'config/db_connect.php';
require_once 'includes/attendance_notification.php';

// Set error reporting for easier debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Testing Attendance Notification System</h2>";

// Function to display test results
function displayResult($test, $result, $message = "") {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid " . ($result ? "green" : "red") . ";'>";
    echo "<strong>Test:</strong> " . htmlspecialchars($test) . " - ";
    echo "<span style='color: " . ($result ? "green" : "red") . ";'>" . ($result ? "PASSED" : "FAILED") . "</span>";
    if (!empty($message)) {
        echo "<br><span>" . htmlspecialchars($message) . "</span>";
    }
    echo "</div>";
}

try {
    // Test 1: Database connection
    $db_test = ($conn && !$conn->connect_error);
    displayResult("Database Connection", $db_test, $db_test ? "Connected successfully" : "Connection failed: " . $conn->connect_error);
    
    // Test 2: Check if notifications table exists
    $table_query = "SHOW TABLES LIKE 'notifications'";
    $table_result = $conn->query($table_query);
    $table_exists = ($table_result && $table_result->num_rows > 0);
    displayResult("Notifications Table", $table_exists, $table_exists ? "Table exists" : "Table doesn't exist - notifications will not be stored");
    
    // Test 3: Find a manager to test with
    $manager_query = "SELECT id, username FROM users WHERE role LIKE '%Manager%' LIMIT 1";
    $manager_result = $conn->query($manager_query);
    $manager_exists = ($manager_result && $manager_result->num_rows > 0);
    $manager_data = $manager_exists ? $manager_result->fetch_assoc() : null;
    $manager_id = $manager_data ? $manager_data['id'] : 0;
    displayResult("Find Manager", $manager_exists, $manager_exists ? "Found manager: " . $manager_data['username'] . " (ID: $manager_id)" : "No manager found - please add a manager user");
    
    // Test 4: Find an employee to test with
    $employee_query = "SELECT id, username FROM users WHERE role NOT LIKE '%Manager%' LIMIT 1";
    $employee_result = $conn->query($employee_query);
    $employee_exists = ($employee_result && $employee_result->num_rows > 0);
    $employee_data = $employee_exists ? $employee_result->fetch_assoc() : null;
    $employee_id = $employee_data ? $employee_data['id'] : 0;
    displayResult("Find Employee", $employee_exists, $employee_exists ? "Found employee: " . $employee_data['username'] . " (ID: $employee_id)" : "No employee found - please add a regular user");
    
    // Test 5: Test notification function
    if ($manager_exists && $employee_exists) {
        // Use a dummy attendance ID or create a real one
        $attendance_id = 9999;  // Just for testing
        $notification_result = notify_manager($manager_id, $employee_id, $attendance_id, 'punch_in');
        displayResult("Send Notification", $notification_result, $notification_result ? "Notification sent successfully" : "Failed to send notification - check error logs");
    } else {
        displayResult("Send Notification", false, "Skipped test due to missing manager or employee");
    }
    
    // Test 6: Verify attendance table has required approval columns
    $columns_query = "SHOW COLUMNS FROM attendance WHERE Field IN ('approval_status', 'manager_id', 'approval_timestamp', 'manager_comments')";
    $columns_result = $conn->query($columns_query);
    $columns_count = ($columns_result) ? $columns_result->num_rows : 0;
    displayResult("Approval Columns", $columns_count == 4, "Found $columns_count of 4 required columns");
    
    // List the actual columns if there are issues
    if ($columns_count < 4) {
        echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid orange;'>";
        echo "<strong>Missing columns:</strong><br>";
        $found_columns = [];
        while ($columns_result && $column = $columns_result->fetch_assoc()) {
            $found_columns[] = $column['Field'];
        }
        $expected_columns = ['approval_status', 'manager_id', 'approval_timestamp', 'manager_comments'];
        foreach ($expected_columns as $column) {
            if (!in_array($column, $found_columns)) {
                echo "- $column<br>";
            }
        }
        echo "<br><strong>Run the following script to add missing columns:</strong><br>";
        echo "<code>php db/apply_attendance_approval_changes.php</code>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    displayResult("Overall Test", false, "Error: " . $e->getMessage());
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Ensure all tests above pass</li>";
echo "<li>Run <code>php db/apply_attendance_approval_changes.php</code> if approval columns are missing</li>";
echo "<li>Test the attendance submission with out-of-geofence location</li>";
echo "<li>Check the attendance_approval.php page as a manager</li>";
echo "</ol>";

$conn->close();
?> 