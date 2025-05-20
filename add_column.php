<?php
// Include database connection
include_once('includes/db_connect.php');

// Add bill_file_path column to travel_expenses table if it doesn't exist
try {
    // Check if column exists
    $result = $conn->query("SHOW COLUMNS FROM travel_expenses LIKE 'bill_file_path'");
    $exists = ($result->num_rows > 0);

    if (!$exists) {
        // Add the column
        $query = "ALTER TABLE travel_expenses ADD COLUMN bill_file_path VARCHAR(255) DEFAULT NULL";
        if ($conn->query($query)) {
            echo "Column 'bill_file_path' added successfully to travel_expenses table.";
        } else {
            echo "Error adding column: " . $conn->error;
        }
    } else {
        echo "Column 'bill_file_path' already exists in travel_expenses table.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 