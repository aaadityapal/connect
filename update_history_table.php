<?php
/**
 * This script updates the project_status_history table to add 'not_started' to the ENUM fields
 */

// Display errors for debugging purposes
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Include database connection
    require_once 'config/db_connect.php';
    
    echo "<h2>Updating project_status_history table</h2>";
    
    // Check if PDO connection is available
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection failed or not available");
    }
    
    // First, check the current structure
    $query1 = "SHOW CREATE TABLE project_status_history";
    $stmt1 = $pdo->query($query1);
    $currentStructure = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>Current table structure:\n";
    print_r($currentStructure);
    echo "</pre>";
    
    // Modify the old_status column to include 'not_started'
    $query2 = "ALTER TABLE project_status_history 
               MODIFY COLUMN old_status ENUM('pending','in_progress','completed','on_hold','cancelled','not_started')";
    
    $stmt2 = $pdo->prepare($query2);
    $result2 = $stmt2->execute();
    
    if ($result2) {
        echo "<p>Updated old_status column successfully</p>";
    } else {
        echo "<p>Failed to update old_status column</p>";
        print_r($stmt2->errorInfo());
    }
    
    // Modify the new_status column to include 'not_started'
    $query3 = "ALTER TABLE project_status_history 
               MODIFY COLUMN new_status ENUM('pending','in_progress','completed','on_hold','cancelled','not_started')";
    
    $stmt3 = $pdo->prepare($query3);
    $result3 = $stmt3->execute();
    
    if ($result3) {
        echo "<p>Updated new_status column successfully</p>";
    } else {
        echo "<p>Failed to update new_status column</p>";
        print_r($stmt3->errorInfo());
    }
    
    // Verify the changes
    $query4 = "SHOW CREATE TABLE project_status_history";
    $stmt4 = $pdo->query($query4);
    $newStructure = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>Updated table structure:\n";
    print_r($newStructure);
    echo "</pre>";
    
    echo "<p>Table update process completed.</p>";
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    
    if (isset($e->errorInfo)) {
        echo "<pre>";
        print_r($e->errorInfo);
        echo "</pre>";
    }
}
?> 