<?php
/**
 * Script to create the overtime_notifications table
 */

// Include database connection
require_once '../config/db_connect.php';

// SQL to create the overtime_notifications table
$sql = "CREATE TABLE IF NOT EXISTS `overtime_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `overtime_id` int(11) DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','read','actioned') NOT NULL DEFAULT 'pending',
  `manager_response` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `actioned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Execute the SQL
if ($conn->query($sql) === TRUE) {
    echo "Table overtime_notifications created successfully or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Check if the attendance table has the overtime columns
$check_columns_sql = "SHOW COLUMNS FROM `attendance` LIKE 'overtime_status'";
$result = $conn->query($check_columns_sql);

if ($result->num_rows == 0) {
    // Add overtime columns to attendance table
    $alter_sql = "ALTER TABLE `attendance` 
                  ADD COLUMN `overtime_status` enum('pending','submitted','approved','rejected') DEFAULT 'pending',
                  ADD COLUMN `overtime_approved_by` int(11) DEFAULT NULL,
                  ADD COLUMN `overtime_actioned_at` datetime DEFAULT NULL";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "Overtime columns added to attendance table successfully.<br>";
    } else {
        echo "Error adding overtime columns: " . $conn->error . "<br>";
    }
} else {
    echo "Overtime columns already exist in attendance table.<br>";
}

echo "Setup complete!";
?> 