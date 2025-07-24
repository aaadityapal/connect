<?php
// Test script for attendance notifications
session_start();
require_once 'config/db_connect.php';
require_once 'includes/attendance_notification.php';

// Set error reporting for easier debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Attendance Notification System Test</h2>";

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

// Function to display a table of data
function displayTable($data, $title = "Data") {
    if (empty($data)) {
        echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
        echo "<strong>{$title}:</strong> No data found";
        echo "</div>";
        return;
    }
    
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>{$title}:</strong>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    
    // Headers
    echo "<tr style='background-color: #f0f0f0;'>";
    foreach (array_keys($data[0]) as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr>";
    
    // Rows
    foreach ($data as $row) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
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
    
    if (!$table_exists) {
        // Try to create the table
        require_once 'includes/ensure_notifications_table.php';
        $table_created = ensure_notifications_table($conn);
        displayResult("Notifications Table", $table_created, $table_created ? "Table created successfully" : "Failed to create table");
    } else {
        displayResult("Notifications Table", true, "Table already exists");
    }
    
    // Test 3: Find a manager to test with
    $manager_query = "SELECT id, username, role, designation FROM users WHERE role LIKE '%Manager%' LIMIT 1";
    $manager_result = $conn->query($manager_query);
    $manager_exists = ($manager_result && $manager_result->num_rows > 0);
    $manager_data = $manager_exists ? $manager_result->fetch_assoc() : null;
    $manager_id = $manager_data ? $manager_data['id'] : 0;
    $manager_info = $manager_exists ? $manager_data['username'] . (isset($manager_data['designation']) ? " (" . $manager_data['designation'] . ")" : "") : "None";
    displayResult("Find Manager", $manager_exists, $manager_exists ? "Found manager: " . $manager_info . " (ID: $manager_id, Role: " . $manager_data['role'] . ")" : "No manager found - please add a manager user");
    
    // Test 4: Find an employee to test with
    $employee_query = "SELECT id, username, role, designation FROM users WHERE role NOT LIKE '%Manager%' LIMIT 1";
    $employee_result = $conn->query($employee_query);
    $employee_exists = ($employee_result && $employee_result->num_rows > 0);
    $employee_data = $employee_exists ? $employee_result->fetch_assoc() : null;
    $employee_id = $employee_data ? $employee_data['id'] : 0;
    $employee_info = $employee_exists ? $employee_data['username'] . (isset($employee_data['designation']) ? " (" . $employee_data['designation'] . ")" : "") : "None";
    displayResult("Find Employee", $employee_exists, $employee_exists ? "Found employee: " . $employee_info . " (ID: $employee_id, Role: " . $employee_data['role'] . ")" : "No employee found - please add a regular user");
    
    // Test 5: Check for existing attendance records
    $attendance_query = "SELECT id, user_id, date, punch_in, punch_out, approval_status FROM attendance ORDER BY id DESC LIMIT 5";
    $attendance_result = $conn->query($attendance_query);
    $attendance_exists = ($attendance_result && $attendance_result->num_rows > 0);
    
    if ($attendance_exists) {
        $attendance_records = [];
        while ($row = $attendance_result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
        displayTable($attendance_records, "Recent Attendance Records");
        
        // Use the most recent attendance record for testing
        $test_attendance_id = $attendance_records[0]['id'];
        displayResult("Find Attendance", true, "Using attendance ID: $test_attendance_id for testing");
    } else {
        displayResult("Find Attendance", false, "No attendance records found");
    }
    
    // Test 6: Check existing notifications
    $notifications_query = "SELECT id, user_id, title, content, type, is_read, created_at FROM notifications ORDER BY id DESC LIMIT 5";
    $notifications_result = $conn->query($notifications_query);
    
    if ($notifications_result && $notifications_result->num_rows > 0) {
        $notification_records = [];
        while ($row = $notifications_result->fetch_assoc()) {
            $notification_records[] = $row;
        }
        displayTable($notification_records, "Recent Notifications");
    } else {
        echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
        echo "<strong>Recent Notifications:</strong> No notifications found";
        echo "</div>";
    }
    
    // Test 7: Test notification function
    if ($manager_exists && $employee_exists && isset($test_attendance_id)) {
        echo "<h3>Testing Notification Creation</h3>";
        echo "<form method='post'>";
        echo "<input type='hidden' name='manager_id' value='$manager_id'>";
        echo "<input type='hidden' name='employee_id' value='$employee_id'>";
        echo "<input type='hidden' name='attendance_id' value='$test_attendance_id'>";
        echo "<select name='notification_type'>";
        echo "<option value='punch_in'>Punch In</option>";
        echo "<option value='punch_out'>Punch Out</option>";
        echo "</select>";
        echo "<button type='submit' name='create_notification'>Create Test Notification</button>";
        echo "</form>";
        
        // Process notification creation
        if (isset($_POST['create_notification'])) {
            $test_manager_id = $_POST['manager_id'];
            $test_employee_id = $_POST['employee_id'];
            $test_attendance_id = $_POST['attendance_id'];
            $test_type = $_POST['notification_type'];
            
            $notification_result = notify_manager($test_manager_id, $test_employee_id, $test_attendance_id, $test_type);
            displayResult("Send Notification", $notification_result, $notification_result ? "Notification sent successfully" : "Failed to send notification - check error logs");
            
            // If successful, show the latest notification
            if ($notification_result) {
                $latest_query = "SELECT id, user_id, title, content, type, is_read, created_at FROM notifications ORDER BY id DESC LIMIT 1";
                $latest_result = $conn->query($latest_query);
                
                if ($latest_result && $latest_result->num_rows > 0) {
                    $latest_records = [];
                    while ($row = $latest_result->fetch_assoc()) {
                        $latest_records[] = $row;
                    }
                    displayTable($latest_records, "Newly Created Notification");
                }
            }
        }
    }
    
    // Test 8: Verify attendance table has required approval columns
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
echo "<li>Run <code>php db/apply_notification_table.php</code> if the notifications table is missing</li>";
echo "<li>Test the attendance submission with out-of-geofence location</li>";
echo "<li>Check the attendance_approval.php page as a manager</li>";
echo "</ol>";

$conn->close();
?> 