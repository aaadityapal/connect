<?php
// Include database configuration
require_once 'config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting database check...\n";

try {
    // Method 1: Using INFORMATION_SCHEMA
    echo "Method 1: Using INFORMATION_SCHEMA\n";
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sv_calendar_events'
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) > 0) {
        echo "Columns in sv_calendar_events table:\n";
        foreach ($columns as $column) {
            echo "- " . $column['COLUMN_NAME'] . " (" . $column['DATA_TYPE'] . 
                 (isset($column['CHARACTER_MAXIMUM_LENGTH']) && !is_null($column['CHARACTER_MAXIMUM_LENGTH']) ? 
                 "(" . $column['CHARACTER_MAXIMUM_LENGTH'] . ")" : "") . 
                 ", " . ($column['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL') . ")\n";
        }
    } else {
        echo "No columns found using Method 1.\n";
    }
    
    // Method 2: Using SHOW COLUMNS
    echo "\nMethod 2: Using SHOW COLUMNS\n";
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sv_calendar_events");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) > 0) {
        echo "Columns in sv_calendar_events table:\n";
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ", " . 
                 ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . ")\n";
        }
    } else {
        echo "No columns found using Method 2.\n";
    }
    
    // Method 3: Using a SELECT query to get column names
    echo "\nMethod 3: Using a SELECT query\n";
    $stmt = $pdo->prepare("SELECT * FROM sv_calendar_events LIMIT 0");
    $stmt->execute();
    $columnCount = $stmt->columnCount();
    
    if ($columnCount > 0) {
        echo "Found $columnCount columns:\n";
        for ($i = 0; $i < $columnCount; $i++) {
            $meta = $stmt->getColumnMeta($i);
            echo "- " . $meta['name'] . "\n";
        }
    } else {
        echo "No columns found using Method 3.\n";
    }
    
    // Try to get a sample row
    echo "\nGetting a sample row from sv_calendar_events...\n";
    $stmt = $pdo->prepare("SELECT * FROM sv_calendar_events LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo "Sample row data:\n";
        foreach ($row as $key => $value) {
            echo "- $key: " . (is_null($value) ? "NULL" : $value) . "\n";
        }
    } else {
        echo "No rows found in the table.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDatabase check completed.\n";
?> 