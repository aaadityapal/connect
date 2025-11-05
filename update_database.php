<?php
require_once 'config/db_connect.php';

try {
    // Update the overtime_requests table to include 'submitted' as a valid status
    $query = "ALTER TABLE `overtime_requests` MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'submitted') DEFAULT 'pending'";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute();
    
    if ($result) {
        echo "Database schema updated successfully!\n";
    } else {
        echo "Failed to update database schema.\n";
        print_r($stmt->errorInfo());
    }
} catch (Exception $e) {
    echo "Error updating database schema: " . $e->getMessage() . "\n";
}
?>