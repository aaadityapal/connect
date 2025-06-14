<?php
// Include database connection
include 'config/db_connect.php';

// Set headers for plain text output
header('Content-Type: text/plain');

try {
    // Check if project_payout_id column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM manager_payments LIKE 'project_payout_id'");
    $projectPayoutIdExists = $checkColumn->num_rows > 0;
    
    // Check if project_id column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM manager_payments LIKE 'project_id'");
    $projectIdExists = $checkColumn->num_rows > 0;
    
    echo "Current table status:\n";
    echo "project_payout_id exists: " . ($projectPayoutIdExists ? "Yes" : "No") . "\n";
    echo "project_id exists: " . ($projectIdExists ? "Yes" : "No") . "\n\n";
    
    // Fix the column name if there's a mismatch
    if ($projectPayoutIdExists && !$projectIdExists) {
        // Rename project_payout_id to project_id
        $conn->query("ALTER TABLE manager_payments CHANGE project_payout_id project_id INT NOT NULL");
        echo "Renamed 'project_payout_id' to 'project_id'\n";
    } else if (!$projectPayoutIdExists && !$projectIdExists) {
        // Neither column exists, add project_id
        $conn->query("ALTER TABLE manager_payments ADD project_id INT NOT NULL AFTER manager_id");
        echo "Added 'project_id' column\n";
    } else if ($projectPayoutIdExists && $projectIdExists) {
        // Both columns exist, need to merge data
        echo "Both columns exist. This requires manual intervention.\n";
    } else {
        echo "No changes needed. 'project_id' column already exists.\n";
    }
    
    // Update the foreign key if needed
    $checkForeignKey = $conn->query("SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                                    WHERE TABLE_NAME = 'manager_payments' 
                                    AND COLUMN_NAME = 'project_id' 
                                    AND REFERENCED_TABLE_NAME = 'project_payouts'");
    
    if ($checkForeignKey->num_rows == 0) {
        // Try to drop any existing foreign key on project_id first
        $conn->query("ALTER TABLE manager_payments DROP FOREIGN KEY IF EXISTS manager_payments_ibfk_2");
        
        // Add the foreign key
        $conn->query("ALTER TABLE manager_payments ADD CONSTRAINT FK_manager_payments_project 
                     FOREIGN KEY (project_id) REFERENCES project_payouts(id)");
        echo "Added foreign key on 'project_id' referencing 'project_payouts'\n";
    } else {
        echo "Foreign key on 'project_id' already exists\n";
    }
    
    echo "\nTable structure updated successfully!";
    
} catch(Exception $e) {
    echo "Error updating table: " . $e->getMessage();
}
?> 