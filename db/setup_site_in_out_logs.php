<?php
require_once '../config/db_connect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Create geofence_locations table if it doesn't exist
    $sql_geofence = "CREATE TABLE IF NOT EXISTS geofence_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        location_name VARCHAR(255) NOT NULL,
        latitude DECIMAL(10, 7) NOT NULL,
        longitude DECIMAL(10, 7) NOT NULL,
        radius DECIMAL(8, 2) NOT NULL COMMENT 'Radius in meters',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($sql_geofence)) {
        throw new Exception("Error creating geofence_locations table: " . $conn->error);
    }
    
    // Create site_in_out_logs table if it doesn't exist
    $sql_logs = "CREATE TABLE IF NOT EXISTS site_in_out_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action ENUM('site_in', 'site_out') NOT NULL,
        latitude DECIMAL(10, 7) NOT NULL,
        longitude DECIMAL(10, 7) NOT NULL,
        address VARCHAR(255),
        geofence_location_id INT,
        distance_from_geofence DECIMAL(8,3),
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        device_info VARCHAR(255),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (geofence_location_id) REFERENCES geofence_locations(id)
    )";
    
    if (!$conn->query($sql_logs)) {
        throw new Exception("Error creating site_in_out_logs table: " . $conn->error);
    }
    
    echo "Database tables created successfully!";
    
} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}
?> 