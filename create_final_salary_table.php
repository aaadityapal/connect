<?php
// Include database connection
require_once 'config/db_connect.php';

try {
    // Create the final_salary table
    $create_table_query = "
        CREATE TABLE IF NOT EXISTS final_salary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            base_salary DECIMAL(10, 2) NOT NULL,
            increment_percentage DECIMAL(5, 2) DEFAULT 0.00,
            effective_from DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($create_table_query);
    
    echo "Table 'final_salary' created successfully or already exists.";
    
} catch (PDOException $e) {
    error_log("Error creating final_salary table: " . $e->getMessage());
    echo "Error creating table: " . $e->getMessage();
}
?>