<?php
/**
 * Add foreign key constraints for missing punch columns
 */
require_once 'config/db_connect.php';

try {
    echo "<h2>Adding Foreign Key Constraints for Missing Punch Columns</h2>";
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Check if foreign key constraint for missing_punch_in_id already exists
    $check_fk_query = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                       WHERE TABLE_SCHEMA = 'crm' 
                       AND TABLE_NAME = 'attendance' 
                       AND COLUMN_NAME = 'missing_punch_in_id' 
                       AND REFERENCED_TABLE_NAME = 'missing_punch_in'";
    
    $result = $conn->query($check_fk_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<p>Foreign key constraint for missing_punch_in_id already exists. Skipping.</p>";
    } else {
        echo "<p>Adding foreign key constraint for missing_punch_in_id...</p>";
        $sql1 = "ALTER TABLE attendance 
                 ADD CONSTRAINT fk_attendance_missing_punch_in 
                 FOREIGN KEY (missing_punch_in_id) 
                 REFERENCES missing_punch_in(id) 
                 ON DELETE SET NULL ON UPDATE CASCADE";
        
        if ($conn->query($sql1) === TRUE) {
            echo "<p style='color: green;'>Successfully added foreign key constraint for missing_punch_in_id</p>";
        } else {
            echo "<p style='color: red;'>Error adding foreign key constraint for missing_punch_in_id: " . $conn->error . "</p>";
        }
    }
    
    // Check if foreign key constraint for missing_punch_out_id already exists
    $check_fk_query2 = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = 'crm' 
                        AND TABLE_NAME = 'attendance' 
                        AND COLUMN_NAME = 'missing_punch_out_id' 
                        AND REFERENCED_TABLE_NAME = 'missing_punch_out'";
    
    $result2 = $conn->query($check_fk_query2);
    
    if ($result2 && $result2->num_rows > 0) {
        echo "<p>Foreign key constraint for missing_punch_out_id already exists. Skipping.</p>";
    } else {
        echo "<p>Adding foreign key constraint for missing_punch_out_id...</p>";
        $sql2 = "ALTER TABLE attendance 
                 ADD CONSTRAINT fk_attendance_missing_punch_out 
                 FOREIGN KEY (missing_punch_out_id) 
                 REFERENCES missing_punch_out(id) 
                 ON DELETE SET NULL ON UPDATE CASCADE";
        
        if ($conn->query($sql2) === TRUE) {
            echo "<p style='color: green;'>Successfully added foreign key constraint for missing_punch_out_id</p>";
        } else {
            echo "<p style='color: red;'>Error adding foreign key constraint for missing_punch_out_id: " . $conn->error . "</p>";
        }
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    echo "<h3 style='color: green;'>Foreign key constraint process completed!</h3>";
    
} catch (Exception $e) {
    // Re-enable foreign key checks even if there's an error
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>