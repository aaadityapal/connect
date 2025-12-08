<?php
/**
 * Add Edit Tracking Columns to Line Items Table
 * Adds edited_by, edited_at, and edit_count to tbl_payment_entry_line_items_detail
 */

require_once 'config/db_connect.php';

try {
    echo "Starting: Adding edit tracking columns to line items table...\n";

    // Check if columns already exist
    $checkQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_NAME = 'tbl_payment_entry_line_items_detail' 
                   AND TABLE_SCHEMA = DATABASE() 
                   AND COLUMN_NAME IN ('edited_by', 'edited_at', 'edit_count')";
    
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute();
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $columnsToAdd = [];
    if (!in_array('edited_by', $existingColumns)) {
        $columnsToAdd[] = "ADD COLUMN edited_by INT(11) NULL AFTER rejected_at";
    }
    if (!in_array('edited_at', $existingColumns)) {
        $columnsToAdd[] = "ADD COLUMN edited_at TIMESTAMP NULL AFTER edited_by";
    }
    if (!in_array('edit_count', $existingColumns)) {
        $columnsToAdd[] = "ADD COLUMN edit_count INT(11) DEFAULT 0 AFTER edited_at";
    }

    if (empty($columnsToAdd)) {
        echo "✓ All edit tracking columns already exist in tbl_payment_entry_line_items_detail\n";
    } else {
        $alterQuery = "ALTER TABLE tbl_payment_entry_line_items_detail " . implode(", ", $columnsToAdd);
        echo "Executing: $alterQuery\n";
        
        $pdo->exec($alterQuery);
        echo "✓ Successfully added edit tracking columns to line items table\n";
    }

    // Verify columns
    $verifyQuery = "SHOW COLUMNS FROM tbl_payment_entry_line_items_detail LIKE 'edited%'";
    $stmt = $pdo->prepare($verifyQuery);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($columns)) {
        echo "\n✓ Verification successful! Line item edit tracking columns:\n";
        foreach ($columns as $col) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    }

    echo "\n✓ Done! Line items now have independent edit tracking.\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    die(1);
}
?>
