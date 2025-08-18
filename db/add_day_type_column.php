<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/add_day_type_column.sql');
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "Successfully added day_type column to leave_request table.\n";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
    // Log the error
    error_log("Error adding day_type column: " . $e->getMessage());
}
?>
