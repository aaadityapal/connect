<?php
// Notification System Debugging Tool
session_start();
require_once 'config/db_connect.php';
require_once 'includes/ensure_notifications_table.php';

// Set error reporting for easier debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set up basic styling
echo "<!DOCTYPE html>
<html>
<head>
    <title>Notification System Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .debug-title { font-weight: bold; margin-bottom: 10px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-btn { padding: 5px 10px; margin-right: 5px; cursor: pointer; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Notification System Debug</h1>";

// Function to display query results as a table
function displayQueryResults($result, $title) {
    echo "<div class='debug-section'>";
    echo "<div class='debug-title'>$title</div>";
    
    if (!$result) {
        echo "<p class='error'>Query failed</p>";
        return;
    }
    
    if ($result->num_rows == 0) {
        echo "<p class='warning'>No records found</p>";
        return;
    }
    
    echo "<table>";
    
    // Headers
    $first_row = $result->fetch_assoc();
    $result->data_seek(0);
    
    echo "<tr>";
    foreach (array_keys($first_row) as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr>";
    
    // Data rows
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
}

// Check if notifications table exists
echo "<div class='debug-section'>";
echo "<div class='debug-title'>Notifications Table Status</div>";

$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check->num_rows > 0) {
    echo "<p class='success'>Notifications table exists</p>";
    
    // Check table structure
    $columns = $conn->query("SHOW COLUMNS FROM notifications");
    echo "<p>Table structure:</p>";
    displayQueryResults($columns, "Columns");
    
} else {
    echo "<p class='error'>Notifications table does not exist</p>";
    
    // Create table button
    echo "<form method='post'>";
    echo "<input type='submit' name='create_table' value='Create Notifications Table' class='action-btn'>";
    echo "</form>";
    
    if (isset($_POST['create_table'])) {
        if (ensure_notifications_table($conn)) {
            echo "<p class='success'>Table created successfully!</p>";
            echo "<script>window.location.reload();</script>";
        } else {
            echo "<p class='error'>Failed to create table. Check server logs.</p>";
        }
    }
}
echo "</div>";

// Check recent notifications
echo "<div class='debug-section'>";
echo "<div class='debug-title'>Recent Notifications</div>";

$notifications = $conn->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 10");
if ($conn->error) {
    echo "<p class='error'>Error querying notifications: " . $conn->error . "</p>";
} else {
    displayQueryResults($notifications, "Last 10 Notifications");
}

// Create test notification form
echo "<h3>Create Test Notification</h3>";
echo "<form method='post'>";
echo "<div style='margin-bottom: 10px;'>";
echo "<label>Manager ID: </label>";
echo "<input type='number' name='manager_id' required>";
echo "</div>";

echo "<div style='margin-bottom: 10px;'>";
echo "<label>Title: </label>";
echo "<input type='text' name='title' value='Test Notification' required>";
echo "</div>";

echo "<div style='margin-bottom: 10px;'>";
echo "<label>Content: </label>";
echo "<textarea name='content' rows='3' style='width: 300px;'>This is a test notification</textarea>";
echo "</div>";

echo "<div style='margin-bottom: 10px;'>";
echo "<label>Link: </label>";
echo "<input type='text' name='link' value='attendance_approval.php'>";
echo "</div>";

echo "<input type='submit' name='create_notification' value='Create Notification' class='action-btn'>";
echo "</form>";

// Process notification creation
if (isset($_POST['create_notification'])) {
    $manager_id = $_POST['manager_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $link = $_POST['link'];
    
    $insert_query = "INSERT INTO notifications (user_id, title, content, link, type, is_read, created_at) 
                    VALUES (?, ?, ?, ?, 'test', 0, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    if (!$stmt) {
        echo "<p class='error'>Error preparing statement: " . $conn->error . "</p>";
    } else {
        $stmt->bind_param('isss', $manager_id, $title, $content, $link);
        if ($stmt->execute()) {
            echo "<p class='success'>Test notification created successfully!</p>";
            echo "<script>window.location.reload();</script>";
        } else {
            echo "<p class='error'>Error creating notification: " . $stmt->error . "</p>";
        }
    }
}
echo "</div>";

// Check database connection info
echo "<div class='debug-section'>";
echo "<div class='debug-title'>Database Connection Info</div>";

echo "<p>Server Info: " . $conn->server_info . "</p>";
echo "<p>Character Set: " . $conn->character_set_name() . "</p>";

// Check if we can run queries
$test_query = $conn->query("SELECT 1 AS test");
if ($test_query) {
    echo "<p class='success'>Database queries are working</p>";
} else {
    echo "<p class='error'>Database query failed: " . $conn->error . "</p>";
}
echo "</div>";

// Check users table structure
echo "<div class='debug-section'>";
echo "<div class='debug-title'>Users Table Structure</div>";

$users_columns = $conn->query("SHOW COLUMNS FROM users");
if ($conn->error) {
    echo "<p class='error'>Error querying users table: " . $conn->error . "</p>";
} else {
    displayQueryResults($users_columns, "Users Table Columns");
}
echo "</div>";

// Check attendance table structure
echo "<div class='debug-section'>";
echo "<div class='debug-title'>Attendance Table Structure</div>";

$attendance_columns = $conn->query("SHOW COLUMNS FROM attendance");
if ($conn->error) {
    echo "<p class='error'>Error querying attendance table: " . $conn->error . "</p>";
} else {
    displayQueryResults($attendance_columns, "Attendance Table Columns");
}
echo "</div>";

// Check for managers in the system
echo "<div class='debug-section'>";
echo "<div class='debug-title'>Available Managers</div>";

$managers = $conn->query("SELECT id, username, role, designation FROM users WHERE role LIKE '%Manager%' LIMIT 10");
if ($conn->error) {
    echo "<p class='error'>Error querying managers: " . $conn->error . "</p>";
} else {
    displayQueryResults($managers, "Managers");
}
echo "</div>";

// Display PHP info
echo "<div class='debug-section'>";
echo "<div class='debug-title'>PHP Environment</div>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Extensions: " . implode(', ', get_loaded_extensions()) . "</p>";
echo "</div>";

// Close the connection
$conn->close();

echo "</body></html>";
?> 