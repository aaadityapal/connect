<?php
// Include database connection
include 'config/db_connect.php';

// Set headers for plain text output
header('Content-Type: text/plain');

try {
    // Add manager_id column to project_payouts table if it doesn't exist
    $query = "ALTER TABLE project_payouts ADD COLUMN manager_id INT NULL";
    
    if ($conn->query($query)) {
        echo "Successfully added manager_id column to project_payouts table.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 