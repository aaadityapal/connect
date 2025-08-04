<?php
// Script to add rejection_cascade column to travel_expenses table

// Include database connection
require_once '../config/db_connect.php';

try {
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/add_rejection_cascade_column.sql');
    
    // Execute SQL statements one by one
    $statements = explode(';', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    // Verify the column was added
    $checkQuery = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'travel_expenses' 
                  AND COLUMN_NAME = 'rejection_cascade'";
    $count = $pdo->query($checkQuery)->fetchColumn();
    
    if ($count > 0) {
        echo "Rejection cascade column added or already exists!\n";
        
        // Log success
        $logFile = '../logs/database_updates.log';
        $message = date('Y-m-d H:i:s') . " - Successfully added or verified rejection_cascade column\n";
        file_put_contents($logFile, $message, FILE_APPEND);
    } else {
        echo "Failed to add rejection_cascade column. Please check the database manually.\n";
    }
    
} catch (PDOException $e) {
    // Log detailed error information
    $errorMessage = "Database error: " . $e->getMessage();
    $errorCode = $e->getCode();
    $errorTrace = $e->getTraceAsString();
    
    // Create detailed error log
    $detailedError = date('Y-m-d H:i:s') . " - DATABASE COLUMN ERROR:\n" .
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