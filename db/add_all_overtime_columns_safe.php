<?php
// Database connection
require_once '../config/db_connect.php';

// Array of columns to check and add
$columns = [
    'overtime_status' => "ADD COLUMN `overtime_status` enum('pending','submitted','approved','rejected') DEFAULT 'pending' COMMENT 'Status of overtime request'",
    'overtime_approved_by' => "ADD COLUMN `overtime_approved_by` int(11) DEFAULT NULL COMMENT 'User ID of manager who approved/rejected overtime'",
    'overtime_actioned_at' => "ADD COLUMN `overtime_actioned_at` datetime DEFAULT NULL COMMENT 'When overtime was approved/rejected'"
];

echo "<h2>Overtime Columns Setup</h2>";

// Check and add each column
foreach ($columns as $column_name => $alter_statement) {
    // Check if column exists
    $check_query = "SELECT COUNT(*) as column_exists 
                   FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'attendance' 
                   AND COLUMN_NAME = '$column_name'";

    $result = $conn->query($check_query);
    $row = $result->fetch_assoc();

    if ($row['column_exists'] == 0) {
        // Column doesn't exist, add it
        $alter_query = "ALTER TABLE `attendance` $alter_statement";
        
        if ($conn->query($alter_query) === TRUE) {
            echo "<p style='color: green;'>✓ Column '$column_name' added successfully.</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding column '$column_name': " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Column '$column_name' already exists.</p>";
    }
}

$conn->close();
echo "<p><a href='../employee_overtime.php'>Return to Overtime Dashboard</a></p>";
?> 