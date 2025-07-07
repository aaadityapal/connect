<?php
/**
 * Check Overtime IDs
 * 
 * This script checks if the overtime IDs being used in the payment process
 * actually exist in the overtime_notifications table.
 */

// Include database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Overtime ID Verification</h1>";

// Check if the overtime_notifications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_notifications'");
if (mysqli_num_rows($table_check) == 0) {
    die("<p style='color:red'>Error: The overtime_notifications table does not exist!</p>");
}

// Get the overtime IDs being used in the UI
echo "<h2>Checking Overtime IDs in the UI</h2>";
$query = "SELECT id, user_id, date, status FROM overtime_notifications ORDER BY id DESC LIMIT 20";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("<p style='color:red'>Error querying overtime_notifications: " . mysqli_error($conn) . "</p>");
}

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Overtime ID</th><th>User ID</th><th>Date</th><th>Status</th><th>Exists?</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['user_id']}</td>";
    echo "<td>{$row['date']}</td>";
    echo "<td>{$row['status']}</td>";
    echo "<td style='color:green'>Yes</td>";
    echo "</tr>";
}

echo "</table>";

// Check if there are any overtime_payments records
echo "<h2>Checking Existing Overtime Payments</h2>";

$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_payments'");
if (mysqli_num_rows($table_check) == 0) {
    echo "<p>The overtime_payments table does not exist yet.</p>";
} else {
    $query = "SELECT op.id, op.overtime_id, op.employee_id, op.status, 
              CASE WHEN on2.id IS NOT NULL THEN 'Yes' ELSE 'No' END as exists_in_notifications
              FROM overtime_payments op
              LEFT JOIN overtime_notifications on2 ON op.overtime_id = on2.id
              ORDER BY op.id DESC LIMIT 20";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "<p style='color:red'>Error querying overtime_payments: " . mysqli_error($conn) . "</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Payment ID</th><th>Overtime ID</th><th>Employee ID</th><th>Status</th><th>Exists in Notifications?</th></tr>";
        
        $found_issue = false;
        while ($row = mysqli_fetch_assoc($result)) {
            $style = $row['exists_in_notifications'] == 'No' ? "style='color:red;font-weight:bold'" : "";
            echo "<tr $style>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['overtime_id']}</td>";
            echo "<td>{$row['employee_id']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "<td>{$row['exists_in_notifications']}</td>";
            echo "</tr>";
            
            if ($row['exists_in_notifications'] == 'No') {
                $found_issue = true;
            }
        }
        
        echo "</table>";
        
        if ($found_issue) {
            echo "<p style='color:red'><strong>Warning:</strong> Some overtime payments reference overtime IDs that don't exist in the overtime_notifications table!</p>";
        } else {
            echo "<p style='color:green'>All overtime payments reference valid overtime IDs.</p>";
        }
    }
}

// Create a test record in overtime_notifications if needed
echo "<h2>Create Test Overtime Record</h2>";
echo "<p>If you're having issues with the foreign key constraint, you can create a test record in the overtime_notifications table.</p>";
echo "<p>Click the button below to create a test record with ID 633:</p>";
echo "<form method='post'>";
echo "<input type='submit' name='create_test_record' value='Create Test Record'>";
echo "</form>";

if (isset($_POST['create_test_record'])) {
    // Check if record with ID 633 already exists
    $check_query = "SELECT id FROM overtime_notifications WHERE id = 633";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo "<p style='color:orange'>A record with ID 633 already exists in the overtime_notifications table.</p>";
    } else {
        // Create a test record with ID 633
        $insert_query = "INSERT INTO overtime_notifications (id, user_id, date, shift_id, hours, status, created_at) 
                         VALUES (633, 1, CURDATE(), 1, 2.5, 'approved', NOW())";
        
        if (mysqli_query($conn, $insert_query)) {
            echo "<p style='color:green'>Successfully created test record with ID 633 in the overtime_notifications table.</p>";
        } else {
            echo "<p style='color:red'>Error creating test record: " . mysqli_error($conn) . "</p>";
        }
    }
}

// Close the database connection
mysqli_close($conn);
?> 