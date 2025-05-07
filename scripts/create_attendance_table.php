<?php
// Script to create the attendance table in the database

// Include database connection
require_once(__DIR__ . '/../includes/db_connect.php');

// SQL to create the attendance table
$sql = "
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `punch_in` time DEFAULT NULL,
  `punch_out` time DEFAULT NULL,
  `working_hours` time DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'present',
  `work_report` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `accuracy` decimal(10,2) DEFAULT NULL,
  `punch_in_photo` varchar(255) DEFAULT NULL,
  `punch_out_photo` varchar(255) DEFAULT NULL,
  `address` text,
  `punch_out_address` text,
  `shift_id` int(11) DEFAULT NULL,
  `shift_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  KEY `shift_id` (`shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

try {
    // Execute the query using mysqli
    if ($conn->multi_query($sql)) {
        echo "Attendance table created successfully or already exists.";
    } else {
        echo "Error creating table: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 