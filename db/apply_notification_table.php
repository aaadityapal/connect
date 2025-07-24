<?php
// Script to create the notifications table
require_once '../config/db_connect.php';

echo "Starting to create notifications table...\n";

// SQL statements
$sql = file_get_contents('create_notifications_table.sql');

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)), 'strlen');

// Execute each statement
$success = true;
foreach ($statements as $statement) {
    if (!empty($statement)) {
        if ($conn->query($statement)) {
            echo "Success: " . substr($statement, 0, 50) . "...\n";
        } else {
            echo "Error: " . $conn->error . "\n";
            $success = false;
        }
    }
}

if ($success) {
    echo "Notifications table created successfully.\n";
} else {
    echo "There were errors creating the notifications table.\n";
}

$conn->close();
?> 