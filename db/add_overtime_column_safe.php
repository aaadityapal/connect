<?php
// Database connection
require_once '../config/db_connect.php';

// Check if column exists
$check_query = "SELECT COUNT(*) as column_exists 
               FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'attendance' 
               AND COLUMN_NAME = 'overtime_actioned_at'";

$result = $conn->query($check_query);
$row = $result->fetch_assoc();

if ($row['column_exists'] == 0) {
    // Column doesn't exist, add it
    $alter_query = "ALTER TABLE `attendance` 
                   ADD COLUMN `overtime_actioned_at` datetime DEFAULT NULL 
                   COMMENT 'When overtime was approved/rejected'";
    
    if ($conn->query($alter_query) === TRUE) {
        echo "Column 'overtime_actioned_at' added successfully.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column 'overtime_actioned_at' already exists.";
}

$conn->close();
?> 