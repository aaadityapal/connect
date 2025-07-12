<?php
// Include database connection
require_once '../config/db_connect.php';

// SQL to add the new column
$sql = "ALTER TABLE attendance 
        ADD COLUMN outside_location_reason VARCHAR(255) NULL 
        COMMENT 'Reason provided when punching in/out from outside geofence'";

// Execute the query
if ($conn->query($sql) === TRUE) {
    echo "Column 'outside_location_reason' added successfully to attendance table.";
} else {
    echo "Error adding column: " . $conn->error;
}

// Close connection
$conn->close();
?> 