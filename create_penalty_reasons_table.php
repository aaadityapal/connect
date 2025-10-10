<?php
// Include database connection
require_once 'config/db_connect.php';

try {
    // Create the penalty_reasons table
    $sql = "CREATE TABLE IF NOT EXISTS penalty_reasons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        penalty_date DATE NOT NULL,
        penalty_amount DECIMAL(5,1) NOT NULL,
        reason TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, penalty_date)
    )";
    
    $pdo->exec($sql);
    echo "Table 'penalty_reasons' created successfully or already exists.";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>