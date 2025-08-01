<?php
// Simple script to execute SQL from a file
require_once 'config/db_connect.php';

$sqlFile = isset($argv[1]) ? $argv[1] : null;

if (!$sqlFile || !file_exists($sqlFile)) {
    echo "Error: SQL file not found or not specified.\n";
    echo "Usage: php execute_sql.php path/to/sql_file.sql\n";
    exit(1);
}

try {
    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    echo "SQL executed successfully from file: $sqlFile\n";
} catch (PDOException $e) {
    echo "Error executing SQL: " . $e->getMessage() . "\n";
    exit(1);
}