<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/add_stage_payment_details.sql');
    
    // Execute the SQL commands
    $pdo->exec($sql);
    
    echo "Database updated successfully! Created tables 'hrm_project_stage_payment_transactions' and 'hrm_project_payment_entries'.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?> 