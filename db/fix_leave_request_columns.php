<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/fix_leave_request_columns.sql');
    $pdo->exec($sql);
    echo "Successfully updated leave_request table columns.\n";
} catch (PDOException $e) {
    echo "Error updating columns: " . $e->getMessage() . "\n";
    error_log("Error updating leave_request columns: " . $e->getMessage());
}
?>
