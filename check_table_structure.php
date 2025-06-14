<?php
// Include database connection
include 'config/db_connect.php';

// Set headers for plain text output
header('Content-Type: text/plain');

// First check if the table exists
$tableExistsQuery = "SHOW TABLES LIKE 'project_payouts'";
$tableExistsResult = $conn->query($tableExistsQuery);

if ($tableExistsResult && $tableExistsResult->num_rows > 0) {
    echo "Table 'project_payouts' exists.\n\n";
    
    // Query to get table structure
    $query = "SHOW CREATE TABLE project_payouts";
    $result = $conn->query($query);
    
    if ($result && $row = $result->fetch_assoc()) {
        echo "Table structure:\n\n";
        echo $row['Create Table'] . "\n\n";
    } else {
        echo "Error getting table structure: " . $conn->error . "\n";
    }
    
    // Get all columns
    $columnsQuery = "SELECT * FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'project_payouts'";
    $columnsResult = $conn->query($columnsQuery);
    
    if ($columnsResult) {
        echo "Columns in project_payouts table:\n\n";
        while ($column = $columnsResult->fetch_assoc()) {
            echo "Column: {$column['COLUMN_NAME']}\n";
            echo "Type: {$column['COLUMN_TYPE']}\n";
            echo "Nullable: {$column['IS_NULLABLE']}\n";
            echo "Default: " . ($column['COLUMN_DEFAULT'] === NULL ? 'NULL' : $column['COLUMN_DEFAULT']) . "\n";
            echo "----------------------------\n";
        }
    } else {
        echo "Error getting columns: " . $conn->error . "\n";
    }
} else {
    echo "Table 'project_payouts' does not exist.\n";
}

// Check if manager_id column exists
$query = "SELECT COLUMN_NAME 
          FROM INFORMATION_SCHEMA.COLUMNS 
          WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'project_payouts' 
          AND COLUMN_NAME = 'manager_id'";
$result = $conn->query($query);

echo "\n\nChecking for manager_id column:\n";
if ($result && $result->num_rows > 0) {
    echo "manager_id column exists in project_payouts table.";
} else {
    echo "manager_id column does NOT exist in project_payouts table.";
}
?> 