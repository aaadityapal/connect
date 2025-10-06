<?php
/**
 * Add missing punch columns to attendance table
 */
require_once 'config/db_connect.php';

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if columns already exist
    $columns_to_add = [
        'missing_punch_in_id' => "ALTER TABLE attendance ADD COLUMN missing_punch_in_id int(11) DEFAULT NULL",
        'missing_punch_out_id' => "ALTER TABLE attendance ADD COLUMN missing_punch_out_id int(11) DEFAULT NULL",
        'missing_punch_reason' => "ALTER TABLE attendance ADD COLUMN missing_punch_reason text DEFAULT NULL",
        'missing_punch_out_reason' => "ALTER TABLE attendance ADD COLUMN missing_punch_out_reason text DEFAULT NULL",
        'missing_punch_approval_status' => "ALTER TABLE attendance ADD COLUMN missing_punch_approval_status enum('pending','approved','rejected') DEFAULT 'pending'"
    ];
    
    $indexes_to_add = [
        'idx_missing_punch_in_id' => "ALTER TABLE attendance ADD KEY idx_missing_punch_in_id (missing_punch_in_id)",
        'idx_missing_punch_out_id' => "ALTER TABLE attendance ADD KEY idx_missing_punch_out_id (missing_punch_out_id)",
        'idx_missing_punch_approval_status' => "ALTER TABLE attendance ADD KEY idx_missing_punch_approval_status (missing_punch_approval_status)"
    ];
    
    echo "<h2>Adding Missing Punch Columns to Attendance Table</h2>";
    
    // Add columns
    foreach ($columns_to_add as $column_name => $sql) {
        // Check if column already exists
        $check_query = "SHOW COLUMNS FROM attendance LIKE '$column_name'";
        $result = $conn->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            echo "<p>Column '$column_name' already exists. Skipping.</p>";
        } else {
            echo "<p>Adding column '$column_name'...</p>";
            if ($conn->query($sql) === TRUE) {
                echo "<p style='color: green;'>Successfully added column '$column_name'</p>";
            } else {
                echo "<p style='color: red;'>Error adding column '$column_name': " . $conn->error . "</p>";
                throw new Exception("Failed to add column: " . $conn->error);
            }
        }
    }
    
    // Add indexes
    echo "<h3>Adding Indexes</h3>";
    foreach ($indexes_to_add as $index_name => $sql) {
        // Check if index already exists
        $check_query = "SHOW INDEX FROM attendance WHERE Key_name = '$index_name'";
        $result = $conn->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            echo "<p>Index '$index_name' already exists. Skipping.</p>";
        } else {
            echo "<p>Adding index '$index_name'...</p>";
            if ($conn->query($sql) === TRUE) {
                echo "<p style='color: green;'>Successfully added index '$index_name'</p>";
            } else {
                echo "<p style='color: red;'>Error adding index '$index_name': " . $conn->error . "</p>";
                throw new Exception("Failed to add index: " . $conn->error);
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    echo "<h3 style='color: green;'>All columns and indexes added successfully!</h3>";
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color: red;'>Transaction rolled back.</p>";
}
?>