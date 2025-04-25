<?php
// Include database configuration
require_once 'config.php';

try {
    // SQL to add the status_changed_date column
    $sql = "ALTER TABLE users 
            ADD COLUMN status_changed_date DATETIME DEFAULT NULL 
            COMMENT 'Records when user status was last changed'";
    
    // Execute the query
    $pdo->exec($sql);
    
    echo "Column 'status_changed_date' added successfully to the users table.<br>";
    
    // Optional: Update existing records with current date/time
    $updateSql = "UPDATE users 
                 SET status_changed_date = NOW() 
                 WHERE status_changed_date IS NULL";
    
    $affected = $pdo->exec($updateSql);
    
    echo "Updated $affected existing records with the current timestamp.";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 