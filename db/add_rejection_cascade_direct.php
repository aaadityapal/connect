<?php
// Simple script to add rejection_cascade column to travel_expenses table directly

// Include database connection
require_once '../config/db_connect.php';

try {
    // Check if column exists
    $checkQuery = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'travel_expenses' 
                  AND COLUMN_NAME = 'rejection_cascade'";
    $columnExists = $pdo->query($checkQuery)->fetchColumn() > 0;
    
    if (!$columnExists) {
        // Add the column
        $addColumnQuery = "ALTER TABLE travel_expenses 
                          ADD COLUMN rejection_cascade VARCHAR(50) NULL 
                          COMMENT 'Tracks which role initiated rejection cascade (e.g., HR_REJECTED, ACCOUNTANT_REJECTED)' 
                          AFTER hr_reason";
        $pdo->exec($addColumnQuery);
        echo "Column 'rejection_cascade' added successfully!\n";
        
        // Add index
        $addIndexQuery = "CREATE INDEX idx_rejection_cascade ON travel_expenses (rejection_cascade)";
        $pdo->exec($addIndexQuery);
        echo "Index added successfully!\n";
    } else {
        echo "Column 'rejection_cascade' already exists.\n";
    }
    
    // Log success
    $logFile = '../logs/database_updates.log';
    $message = date('Y-m-d H:i:s') . " - Successfully added or verified rejection_cascade column using direct method\n";
    file_put_contents($logFile, $message, FILE_APPEND);
    
} catch (PDOException $e) {
    // Log detailed error information
    $errorMessage = "Database error: " . $e->getMessage();
    $errorCode = $e->getCode();
    $errorTrace = $e->getTraceAsString();
    
    // Create detailed error log
    $detailedError = date('Y-m-d H:i:s') . " - DATABASE COLUMN ERROR (DIRECT METHOD):\n" .
                    "Message: " . $errorMessage . "\n" .
                    "Code: " . $errorCode . "\n" .
                    "Trace: " . $errorTrace . "\n\n";
    
    // Log to custom file with more details
    $logFile = '../logs/database_errors.log';
    file_put_contents($logFile, $detailedError, FILE_APPEND);
    
    echo "Error adding rejection cascade column: " . $e->getMessage() . "\n";
    echo "Check ../logs/database_errors.log for more details.\n";
}
?>