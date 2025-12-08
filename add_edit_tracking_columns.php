<?php
/**
 * Add Edit Tracking Columns to Payment Entry Master Records
 * 
 * Adds:
 * - edited_by (INT) - User ID of who last edited the payment entry
 * - edited_at (TIMESTAMP) - When the payment entry was last edited
 * 
 * This ensures edit metadata is tracked alongside creation and update timestamps
 */

// Note: Can be run from CLI or web for database schema migration

header('Content-Type: application/json');

require_once __DIR__ . '/config/db_connect.php';

try {
    // Start transaction
    $pdo->beginTransaction();

    // ===================================================================
    // 1. Add edited_by column if it doesn't exist
    // ===================================================================
    $checkEditedByQuery = "
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'tbl_payment_entry_master_records' 
        AND COLUMN_NAME = 'edited_by'
    ";
    $checkStmt = $pdo->prepare($checkEditedByQuery);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        $addEditedByQuery = "
            ALTER TABLE tbl_payment_entry_master_records
            ADD COLUMN edited_by INT NULL
            AFTER updated_by_user_id
        ";
        $pdo->exec($addEditedByQuery);
        echo "✓ Added 'edited_by' column\n";
    } else {
        echo "✓ Column 'edited_by' already exists\n";
    }

    // ===================================================================
    // 2. Add edited_at column if it doesn't exist
    // ===================================================================
    $checkEditedAtQuery = "
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'tbl_payment_entry_master_records' 
        AND COLUMN_NAME = 'edited_at'
    ";
    $checkStmt = $pdo->prepare($checkEditedAtQuery);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        $addEditedAtQuery = "
            ALTER TABLE tbl_payment_entry_master_records
            ADD COLUMN edited_at TIMESTAMP NULL
            AFTER edited_by
        ";
        $pdo->exec($addEditedAtQuery);
        echo "✓ Added 'edited_at' column\n";
    } else {
        echo "✓ Column 'edited_at' already exists\n";
    }

    // ===================================================================
    // 3. Add edit_count column if it doesn't exist (optional, for audit)
    // ===================================================================
    $checkEditCountQuery = "
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'tbl_payment_entry_master_records' 
        AND COLUMN_NAME = 'edit_count'
    ";
    $checkStmt = $pdo->prepare($checkEditCountQuery);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        $addEditCountQuery = "
            ALTER TABLE tbl_payment_entry_master_records
            ADD COLUMN edit_count INT DEFAULT 0
            AFTER edited_at
        ";
        $pdo->exec($addEditCountQuery);
        echo "✓ Added 'edit_count' column for audit purposes\n";
    } else {
        echo "✓ Column 'edit_count' already exists\n";
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Edit tracking columns added successfully',
        'columns_added' => [
            'edited_by' => 'INT - User ID of who last edited',
            'edited_at' => 'TIMESTAMP - When the entry was last edited',
            'edit_count' => 'INT - Total number of edits (audit purposes)'
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error adding columns: ' . $e->getMessage()
    ]);
}
?>
