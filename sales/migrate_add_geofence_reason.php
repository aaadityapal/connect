<?php
/**
 * Migration: Add geofence_outside_reason column to attendance table
 * Run this script once to add the column if it doesn't exist
 */

require_once __DIR__ . '/../config/db_connect.php';

try {
    // Check if column already exists
    $checkQuery = "
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'attendance' AND COLUMN_NAME = 'geofence_outside_reason' 
        AND TABLE_SCHEMA = DATABASE()
    ";
    
    $checkStmt = $pdo->query($checkQuery);
    $exists = $checkStmt->rowCount() > 0;
    
    if ($exists) {
        echo "Column 'geofence_outside_reason' already exists in attendance table.\n";
        exit(0);
    }
    
    // Add the column
    $alterQuery = "
        ALTER TABLE attendance ADD COLUMN geofence_outside_reason TEXT NULL AFTER work_report
    ";
    
    $pdo->exec($alterQuery);
    
    echo "Successfully added 'geofence_outside_reason' column to attendance table.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
