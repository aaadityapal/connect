<?php
// Include database connection
require_once '../config/db_connect.php';

// SQL to drop the existing column
$sql1 = "ALTER TABLE attendance 
         DROP COLUMN IF EXISTS outside_location_reason";

// SQL to add the new columns
$sql2 = "ALTER TABLE attendance 
         ADD COLUMN punch_in_outside_reason VARCHAR(255) NULL 
         COMMENT 'Reason provided when punching in from outside geofence'";

$sql3 = "ALTER TABLE attendance 
         ADD COLUMN punch_out_outside_reason VARCHAR(255) NULL 
         COMMENT 'Reason provided when punching out from outside geofence'";

// Execute the queries
if ($conn->query($sql1) === TRUE) {
    echo "Column 'outside_location_reason' dropped successfully.<br>";
} else {
    echo "Error dropping column: " . $conn->error . "<br>";
}

if ($conn->query($sql2) === TRUE) {
    echo "Column 'punch_in_outside_reason' added successfully.<br>";
} else {
    echo "Error adding column: " . $conn->error . "<br>";
}

if ($conn->query($sql3) === TRUE) {
    echo "Column 'punch_out_outside_reason' added successfully.<br>";
} else {
    echo "Error adding column: " . $conn->error . "<br>";
}

// Close connection
$conn->close();
?> 