<?php
// Include database connection
include 'config/db_connect.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers for plain text output
header('Content-Type: text/plain');

try {
    // First check if the table exists
    $tableExistsQuery = "SHOW TABLES LIKE 'manager_payments'";
    $tableExistsResult = $conn->query($tableExistsQuery);
    
    if ($tableExistsResult && $tableExistsResult->num_rows > 0) {
        echo "Table 'manager_payments' exists.\n\n";
        
        // Show current table structure
        $structureQuery = "SHOW CREATE TABLE manager_payments";
        $structureResult = $conn->query($structureQuery);
        if ($structureResult && $row = $structureResult->fetch_assoc()) {
            echo "Current table structure:\n";
            echo $row['Create Table'] . "\n\n";
        }
        
        // Check if we need to recreate the table
        $dropTable = "DROP TABLE IF EXISTS manager_payments";
        if ($conn->query($dropTable)) {
            echo "Dropped existing manager_payments table.\n";
        } else {
            echo "Error dropping table: " . $conn->error . "\n";
            exit;
        }
    } else {
        echo "Table 'manager_payments' does not exist. Creating new table.\n";
    }
    
    // Create the table with the correct column name
    $createTable = "CREATE TABLE manager_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        manager_id INT NOT NULL,
        project_id INT NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_mode VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($createTable)) {
        echo "Successfully created manager_payments table with correct column names.";
    } else {
        echo "Error creating table: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    echo "\nTrace: " . $e->getTraceAsString();
}
?> 