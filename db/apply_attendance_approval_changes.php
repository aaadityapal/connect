<?php
// Include database connection
require_once '../config/db_connect.php';

echo "Starting database modifications for attendance approval system...\n";

// SQL statements
$sql = file_get_contents('add_attendance_approval_columns.sql');

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
    echo "Database modifications completed successfully.\n";
} else {
    echo "Database modifications completed with errors.\n";
}

$conn->close();
?> 