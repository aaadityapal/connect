<?php
require_once 'config.php';
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Task Data Debug</h2>";

try {
    $user_id = $_SESSION['user_id'] ?? 'not set';
    echo "<h3>Current User ID: $user_id</h3>";

    // Step 1: Test database connection
    echo "<h3>Database Connection Test:</h3>";
    if ($pdo) {
        echo "Database connection successful<br><br>";
    } else {
        throw new Exception("Database connection failed");
    }

    // Step 2: Simple tasks count
    echo "<h3>Tasks Table Check:</h3>";
    $simple_query = "SELECT COUNT(*) as count FROM tasks";
    echo "Executing query: " . $simple_query . "<br>";
    $result = $pdo->query($simple_query);
    if ($result) {
        $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Total tasks in database: " . $count . "<br><br>";
    } else {
        echo "Failed to get task count<br><br>";
    }

    // Step 3: Check if user has any assigned tasks
    echo "<h3>User's Task Check:</h3>";
    $user_tasks_query = "SELECT COUNT(*) as count FROM task_stages WHERE assigned_to = ?";
    echo "Executing query: " . $user_tasks_query . "<br>";
    $stmt = $pdo->prepare($user_tasks_query);
    $stmt->execute([$user_id]);
    $user_task_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Tasks assigned to user: " . $user_task_count . "<br><br>";

    // Step 4: Show table structures
    echo "<h3>Table Structure:</h3>";
    try {
        $tables = ['tasks', 'task_stages', 'task_priorities', 'task_status'];
        foreach ($tables as $table) {
            echo "<strong>Table: {$table}</strong><br>";
            $structure_query = "DESCRIBE {$table}";
            $structure_result = $pdo->query($structure_query);
            if ($structure_result) {
                echo "<pre>";
                print_r($structure_result->fetchAll(PDO::FETCH_ASSOC));
                echo "</pre>";
            } else {
                echo "Could not get structure for table: {$table}<br>";
            }
        }
    } catch (Exception $e) {
        echo "Error checking table structure: " . $e->getMessage() . "<br>";
    }

} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "Code: " . $e->getCode() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "Trace:<br><pre>";
    print_r($e->getTraceAsString());
    echo "</pre>";
}

echo "<h3>Debug Complete</h3>";
?> 