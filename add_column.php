<?php
// Include database connection
require_once 'config/db_connect.php'; // try the correct path for your db connection

try {
    // Add the leave_penalty_days column
    $alterQuery = "ALTER TABLE salary_penalties ADD COLUMN leave_penalty_days float DEFAULT 0 AFTER penalty_days";
    
    if ($conn->query($alterQuery)) {
        echo "Success: The leave_penalty_days column has been added to the salary_penalties table.";
    } else {
        echo "Error: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?> 