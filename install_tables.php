<?php
/**
 * Install Site Supervision Tables
 * This script runs the site_supervision_tables.sql file to create necessary tables
 */

// Include database connection
require_once 'config/db_connect.php';

// Set content type to plain text
header('Content-Type: text/plain');

try {
    // Read the SQL file
    $sql = file_get_contents('site_supervision_tables.sql');
    
    // Split SQL by semicolon to get individual queries
    $queries = explode(';', $sql);
    
    // Execute each query
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        echo "Executing query:\n";
        echo substr($query, 0, 100) . "...\n"; // Show only start of query to avoid huge output
        
        $success = $pdo->exec($query);
        
        if ($success !== false) {
            echo "SUCCESS\n\n";
        } else {
            echo "ERROR: " . print_r($pdo->errorInfo(), true) . "\n\n";
        }
    }
    
    echo "Installation completed successfully!\n";
    echo "You can now go back to debug_form.php to test your form.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Try running the SQL file directly in phpMyAdmin or your MySQL client.";
} 