<?php
// Script to create the user_update_preferences table

require_once '../config/db_connect.php';

// Read the SQL file
$sql = file_get_contents(__DIR__ . '/create_update_preferences_table.sql');

// Execute the SQL
if ($conn->multi_query($sql)) {
    echo "User update preferences table created successfully.\n";
    
    // Process all result sets
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close(); 