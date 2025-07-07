<?php
/**
 * Create Overtime Payments Table
 * 
 * This script creates the overtime_payments table in the database.
 * It should be run once to set up the table structure.
 */

// Include database connection
require_once '../config/db_connect.php';

// Read the SQL file
$sql_file = file_get_contents('overtime_payments.sql');

// Execute the SQL
$result = mysqli_multi_query($conn, $sql_file);

if ($result) {
    echo "Overtime payments table created successfully.\n";
    
    // Process all result sets
    do {
        // Check if there are more result sets
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    
} else {
    echo "Error creating overtime payments table: " . mysqli_error($conn) . "\n";
}

// Close the database connection
mysqli_close($conn); 