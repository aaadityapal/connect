<?php
// Database connection
require_once '../config/db_connect.php';

// Read and execute the SQL file
$sql_file = file_get_contents('create_overtime_notifications_table.sql');

// Split SQL commands by semicolon
$commands = explode(';', $sql_file);

// Execute each command
$error = false;
$errorMessages = [];

foreach($commands as $command) {
    $command = trim($command);
    if (!empty($command)) {
        if (!$conn->query($command)) {
            $error = true;
            $errorMessages[] = "Error executing: " . htmlspecialchars($command) . " - Error: " . $conn->error;
        }
    }
}

// Output the result
echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Overtime Tables</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }
        .success {
            color: green;
            border: 1px solid green;
            padding: 10px;
            background-color: #e8f5e9;
        }
        .error {
            color: red;
            border: 1px solid red;
            padding: 10px;
            background-color: #ffebee;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            overflow: auto;
        }
        .back {
            margin-top: 20px;
            display: inline-block;
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>";

if ($error) {
    echo "<div class='error'>";
    echo "<h2>Setup Failed</h2>";
    echo "<p>There were errors during the setup process:</p>";
    echo "<ul>";
    foreach($errorMessages as $message) {
        echo "<li>" . $message . "</li>";
    }
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<h2>Setup Completed Successfully</h2>";
    echo "<p>The overtime_notifications table has been set up successfully. The following operations were performed:</p>";
    echo "<ul>";
    echo "<li>Created overtime_notifications table (if it didn't exist)</li>";
    echo "<li>Added overtime_status column to attendance table (if it didn't exist)</li>";
    echo "<li>Added overtime_approved_by column to attendance table (if it didn't exist)</li>";
    echo "<li>Added overtime_actioned_at column to attendance table (if it didn't exist)</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<a href='../employee_overtime.php' class='back'>Back to Overtime Dashboard</a>";
echo "</body></html>";
?> 