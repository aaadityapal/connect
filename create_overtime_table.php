<?php
require_once 'config/db_connect.php';

// Read the SQL file
$sql = file_get_contents('overtime_requests_table.sql');

try {
    // Execute the SQL
    $pdo->exec($sql);
    echo "Table 'overtime_requests' created successfully";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>