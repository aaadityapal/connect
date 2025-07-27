<?php
require_once '../config/db_connect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // First, set the MySQL timezone to IST
    $conn->query("SET time_zone = '+05:30'");
    
    // Modify the site_in_out_logs table to use TIMESTAMP instead of DATETIME
    $sql = "ALTER TABLE site_in_out_logs 
            MODIFY COLUMN timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    
    if (!$conn->query($sql)) {
        throw new Exception("Error modifying site_in_out_logs table: " . $conn->error);
    }
    
    // Update existing records to correct timezone if needed
    $sql = "UPDATE site_in_out_logs 
            SET timestamp = CONVERT_TZ(timestamp, @@session.time_zone, '+05:30')
            WHERE timestamp IS NOT NULL";
    
    if (!$conn->query($sql)) {
        throw new Exception("Error updating existing records: " . $conn->error);
    }
    
    echo "Timezone fix applied successfully!";
    
} catch (Exception $e) {
    die("Fix failed: " . $e->getMessage());
}
?> 