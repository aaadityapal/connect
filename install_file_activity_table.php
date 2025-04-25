<?php
// Script to create the file_activity_logs table

// Include database connection
require_once 'config/db_connect.php';

try {
    // SQL for creating the table
    $sql = "
    CREATE TABLE IF NOT EXISTS `file_activity_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `file_id` int(11) NOT NULL,
      `user_id` int(11) NOT NULL,
      `action_type` varchar(50) NOT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` text DEFAULT NULL,
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`id`),
      KEY `file_id` (`file_id`),
      KEY `user_id` (`user_id`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Execute the SQL
    $pdo->exec($sql);
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background-color: #f8f9fa; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>";
    echo "<h2 style='color: #10b981;'>Success!</h2>";
    echo "<p>The <strong>file_activity_logs</strong> table has been created successfully.</p>";
    echo "<p>This table will be used to track file downloads and other file-related activities in the system.</p>";
    
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;'>";
    echo "<h3 style='margin-top: 0; color: #3b82f6;'>Table Structure:</h3>";
    echo "<ul style='padding-left: 20px;'>";
    echo "<li><strong>id</strong>: Auto-increment primary key</li>";
    echo "<li><strong>file_id</strong>: ID of the file being accessed</li>";
    echo "<li><strong>user_id</strong>: ID of the user performing the action</li>";
    echo "<li><strong>action_type</strong>: Type of action (e.g., 'fingerprint_download')</li>";
    echo "<li><strong>ip_address</strong>: User's IP address</li>";
    echo "<li><strong>user_agent</strong>: User's browser information</li>";
    echo "<li><strong>created_at</strong>: Timestamp when the action occurred</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p style='margin-top: 20px;'>You can now safely delete this installation script or keep it for reference.</p>";
    echo "<a href='index.php' style='display: inline-block; margin-top: 15px; padding: 8px 16px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 4px;'>Return to Home</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background-color: #fef2f2; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>";
    echo "<h2 style='color: #ef4444;'>Error!</h2>";
    echo "<p>Could not create the file_activity_logs table:</p>";
    echo "<div style='padding: 10px; background-color: #fff1f2; border-left: 4px solid #ef4444; border-radius: 4px; overflow-x: auto;'>";
    echo "<code>" . htmlspecialchars($e->getMessage()) . "</code>";
    echo "</div>";
    echo "<p style='margin-top: 20px;'>Please check your database connection and try again.</p>";
    echo "</div>";
}
?> 