<?php
/**
 * Verify that the missing punch system is set up correctly
 */
require_once 'config/db_connect.php';

echo "<h1>Verifying Missing Punch System Setup</h1>";

try {
    // Check if missing_punch_in table exists
    echo "<h2>1. Checking missing_punch_in table</h2>";
    $result = $conn->query("SHOW TABLES LIKE 'missing_punch_in'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ missing_punch_in table exists</p>";
        
        // Check required columns
        $required_columns = ['id', 'user_id', 'date', 'punch_in_time', 'reason', 'status'];
        $columns_result = $conn->query("DESCRIBE missing_punch_in");
        $existing_columns = [];
        while ($row = $columns_result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
        
        foreach ($required_columns as $column) {
            if (in_array($column, $existing_columns)) {
                echo "<p style='color: green;'>✓ Column '$column' exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Column '$column' is missing</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>✗ missing_punch_in table does not exist</p>";
    }
    
    // Check if missing_punch_out table exists
    echo "<h2>2. Checking missing_punch_out table</h2>";
    $result = $conn->query("SHOW TABLES LIKE 'missing_punch_out'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ missing_punch_out table exists</p>";
        
        // Check required columns
        $required_columns = ['id', 'user_id', 'date', 'punch_out_time', 'reason', 'work_report', 'status'];
        $columns_result = $conn->query("DESCRIBE missing_punch_out");
        $existing_columns = [];
        while ($row = $columns_result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
        
        foreach ($required_columns as $column) {
            if (in_array($column, $existing_columns)) {
                echo "<p style='color: green;'>✓ Column '$column' exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Column '$column' is missing</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>✗ missing_punch_out table does not exist</p>";
    }
    
    // Check attendance table columns
    echo "<h2>3. Checking attendance table columns</h2>";
    $required_columns = [
        'missing_punch_in_id',
        'missing_punch_out_id', 
        'missing_punch_reason',
        'missing_punch_out_reason',
        'missing_punch_approval_status'
    ];
    
    $columns_result = $conn->query("DESCRIBE attendance");
    $existing_columns = [];
    while ($row = $columns_result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    foreach ($required_columns as $column) {
        if (in_array($column, $existing_columns)) {
            echo "<p style='color: green;'>✓ Column '$column' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Column '$column' is missing</p>";
        }
    }
    
    // Check indexes
    echo "<h2>4. Checking indexes</h2>";
    $required_indexes = [
        'idx_missing_punch_in_id',
        'idx_missing_punch_out_id',
        'idx_missing_punch_approval_status'
    ];
    
    foreach ($required_indexes as $index) {
        $index_result = $conn->query("SHOW INDEX FROM attendance WHERE Key_name = '$index'");
        if ($index_result && $index_result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Index '$index' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Index '$index' is missing</p>";
        }
    }
    
    // Check foreign key constraints
    echo "<h2>5. Checking foreign key constraints</h2>";
    $fk_constraints = [
        'fk_attendance_missing_punch_in' => "missing_punch_in",
        'fk_attendance_missing_punch_out' => "missing_punch_out"
    ];
    
    foreach ($fk_constraints as $constraint => $referenced_table) {
        $fk_result = $conn->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = 'crm' 
            AND TABLE_NAME = 'attendance' 
            AND CONSTRAINT_NAME = '$constraint'
        ");
        
        if ($fk_result && $fk_result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Foreign key constraint '$constraint' exists</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Foreign key constraint '$constraint' is missing (this might be OK)</p>";
        }
    }
    
    echo "<h1>Verification Complete</h1>";
    echo "<p>If all checks are green, the missing punch system is properly set up.</p>";
    echo "<p>If there are red X marks, run the setup_missing_punch_system.php script to fix the issues.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error during verification: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>