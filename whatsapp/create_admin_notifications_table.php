<?php
/**
 * Create admin_notifications table
 * Run this script once to create the table
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();

    // Create admin_notifications table
    $sql = "CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);

    echo "✅ Table 'admin_notifications' created successfully!\n";
    echo "\nNow you can add admin phone numbers using the management page.\n";

} catch (PDOException $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}
