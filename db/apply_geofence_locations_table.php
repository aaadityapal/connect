<?php
/**
 * Script to create geofence locations tables
 */

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Read SQL file
$sql_file = file_get_contents(__DIR__ . '/create_geofence_locations_table.sql');

// Split SQL statements
$statements = array_filter(array_map('trim', explode(';', $sql_file)));

// Execute each statement
$success = true;
foreach ($statements as $statement) {
    if (!empty($statement)) {
        $result = $conn->query($statement);
        if (!$result) {
            echo "Error executing SQL: " . $conn->error . "\n";
            echo "Statement: " . $statement . "\n\n";
            $success = false;
        }
    }
}

if ($success) {
    echo "Geofence locations tables created successfully!\n";
} else {
    echo "There were errors creating the geofence locations tables.\n";
} 