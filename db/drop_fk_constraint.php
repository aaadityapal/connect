<?php
/**
 * Drop Foreign Key Constraint
 * 
 * This script drops the foreign key constraint on the overtime_notifications table
 * that references the attendance table, which is causing issues with the payment processing.
 */

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Drop Foreign Key Constraint</h1>";

// Check if the overtime_notifications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_notifications'");
if (mysqli_num_rows($table_check) == 0) {
    die("<p style='color:red'>Error: The overtime_notifications table does not exist!</p>");
}

// Get the constraint name
$constraint_query = "
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'overtime_notifications' 
    AND COLUMN_NAME = 'overtime_id'
    AND REFERENCED_TABLE_NAME = 'attendance'
    LIMIT 1
";

$constraint_result = mysqli_query($conn, $constraint_query);

if (!$constraint_result) {
    echo "<p style='color:red'>Error querying constraints: " . mysqli_error($conn) . "</p>";
    exit;
}

if (mysqli_num_rows($constraint_result) == 0) {
    echo "<p style='color:orange'>No foreign key constraint found linking overtime_notifications.overtime_id to attendance.id</p>";
    exit;
}

$row = mysqli_fetch_assoc($constraint_result);
$constraint_name = $row['CONSTRAINT_NAME'];

echo "<p>Found constraint: " . htmlspecialchars($constraint_name) . "</p>";

// Drop the constraint
$drop_query = "ALTER TABLE overtime_notifications DROP FOREIGN KEY " . $constraint_name;

if (mysqli_query($conn, $drop_query)) {
    echo "<p style='color:green'>Successfully dropped foreign key constraint!</p>";
    echo "<p>You should now be able to process payments without the foreign key constraint error.</p>";
} else {
    echo "<p style='color:red'>Error dropping constraint: " . mysqli_error($conn) . "</p>";
}

// Close connection
mysqli_close($conn);
?> 