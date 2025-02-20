<?php
require_once 'config.php';

try {
    $sql = "ALTER TABLE users
            ADD COLUMN IF NOT EXISTS base_salary DECIMAL(10,2) DEFAULT 0.00,
            ADD COLUMN IF NOT EXISTS allowances JSON DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS deductions JSON DEFAULT NULL";
    
    $pdo->exec($sql);
    echo "Salary columns added successfully!";
} catch (PDOException $e) {
    echo "Error adding salary columns: " . $e->getMessage();
}
?> 