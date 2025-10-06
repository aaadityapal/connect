<?php
/**
 * Create missing punch tables if they don't exist
 */
require_once 'config/db_connect.php';

try {
    echo "<h2>Creating Missing Punch Tables</h2>";
    
    // Create missing_punch_in table
    $create_in_table = "
        CREATE TABLE IF NOT EXISTS `missing_punch_in` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `date` date NOT NULL,
          `punch_in_time` time NOT NULL,
          `reason` text NOT NULL,
          `confirmed` tinyint(1) DEFAULT 0,
          `status` enum('pending','approved','rejected') DEFAULT 'pending',
          `admin_notes` text DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `date` (`date`),
          KEY `status` (`status`),
          CONSTRAINT `fk_missing_punch_in_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    if ($conn->query($create_in_table) === TRUE) {
        echo "<p style='color: green;'>Successfully created/verified missing_punch_in table</p>";
    } else {
        echo "<p style='color: red;'>Error creating missing_punch_in table: " . $conn->error . "</p>";
        throw new Exception("Failed to create missing_punch_in table");
    }
    
    // Create missing_punch_out table
    $create_out_table = "
        CREATE TABLE IF NOT EXISTS `missing_punch_out` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `date` date NOT NULL,
          `punch_out_time` time NOT NULL,
          `reason` text NOT NULL,
          `work_report` text NOT NULL,
          `confirmed` tinyint(1) DEFAULT 0,
          `status` enum('pending','approved','rejected') DEFAULT 'pending',
          `admin_notes` text DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `date` (`date`),
          KEY `status` (`status`),
          CONSTRAINT `fk_missing_punch_out_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    if ($conn->query($create_out_table) === TRUE) {
        echo "<p style='color: green;'>Successfully created/verified missing_punch_out table</p>";
    } else {
        echo "<p style='color: red;'>Error creating missing_punch_out table: " . $conn->error . "</p>";
        throw new Exception("Failed to create missing_punch_out table");
    }
    
    echo "<h3 style='color: green;'>All missing punch tables created successfully!</h3>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>