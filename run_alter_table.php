<?php
// Database connection
require_once 'config/db_connect.php';

// SQL to add vendor_category column
$sql = "ALTER TABLE hr_vendors ADD COLUMN vendor_category VARCHAR(50) AFTER vendor_type";

if (mysqli_query($conn, $sql)) {
    echo "Column vendor_category added successfully to hr_vendors table.";
} else {
    echo "Error adding column: " . mysqli_error($conn);
}

// Close connection
mysqli_close($conn);
?>