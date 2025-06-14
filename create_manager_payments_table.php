<?php
// Include database connection
include 'config/db_connect.php';

// Set headers for plain text output
header('Content-Type: text/plain');

try {
    // SQL to create table
    $sql = "CREATE TABLE IF NOT EXISTS manager_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        manager_id INT NOT NULL,
        project_payout_id INT NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_mode VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (manager_id) REFERENCES users(id),
        FOREIGN KEY (project_payout_id) REFERENCES project_payouts(id)
    )";

    // Execute the query
    $pdo->exec($sql);
    
    echo "Table 'manager_payments' created successfully!";
    
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}

// Close connection
$pdo = null;
?> 