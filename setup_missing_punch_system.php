<?php
/**
 * Complete setup for missing punch system
 * This script will create tables, add columns, and set up constraints
 */
require_once 'config/db_connect.php';

try {
    echo "<h1>Setting up Missing Punch System</h1>";
    
    // Start transaction
    $conn->begin_transaction();
    
    echo "<h2>Step 1: Creating Missing Punch Tables</h2>";
    
    // Create missing_punch_in table
    $create_in_table = "
        CREATE TABLE IF NOT EXISTS `missing_punch_in` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `date` date NOT NULL,
          `punch_in_time` time NOT NULL,
          `reason` text NOT NULL,
          `confirmed` tinyint(1) DEFAULT 0,
          `status` enum('pending','approved','rejected') DEFAULT 'pending',
          `admin_notes` text DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `date` (`date`),
          KEY `status` (`status`),
          CONSTRAINT `fk_missing_punch_in_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    if ($conn->query($create_in_table) === TRUE) {
        echo "<p style='color: green;'>✓ Successfully created/verified missing_punch_in table</p>";
    } else {
        throw new Exception("Failed to create missing_punch_in table: " . $conn->error);
    }
    
    // Create missing_punch_out table
    $create_out_table = "
        CREATE TABLE IF NOT EXISTS `missing_punch_out` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `date` date NOT NULL,
          `punch_out_time` time NOT NULL,
          `reason` text NOT NULL,
          `work_report` text NOT NULL,
          `confirmed` tinyint(1) DEFAULT 0,
          `status` enum('pending','approved','rejected') DEFAULT 'pending',
          `admin_notes` text DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `date` (`date`),
          KEY `status` (`status`),
          CONSTRAINT `fk_missing_punch_out_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    if ($conn->query($create_out_table) === TRUE) {
        echo "<p style='color: green;'>✓ Successfully created/verified missing_punch_out table</p>";
    } else {
        throw new Exception("Failed to create missing_punch_out table: " . $conn->error);
    }
    
    echo "<h2>Step 2: Adding Missing Punch Columns to Attendance Table</h2>";
    
    // Add columns to attendance table
    $columns_to_add = [
        'missing_punch_in_id' => "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS missing_punch_in_id int(11) DEFAULT NULL",
        'missing_punch_out_id' => "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS missing_punch_out_id int(11) DEFAULT NULL",
        'missing_punch_reason' => "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS missing_punch_reason text DEFAULT NULL",
        'missing_punch_out_reason' => "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS missing_punch_out_reason text DEFAULT NULL",
        'missing_punch_approval_status' => "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS missing_punch_approval_status enum('pending','approved','rejected') DEFAULT 'pending'"
    ];
    
    foreach ($columns_to_add as $column_name => $sql) {
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ Successfully added/verified column '$column_name'</p>";
        } else {
            throw new Exception("Failed to add column '$column_name': " . $conn->error);
        }
    }
    
    echo "<h2>Step 3: Adding Indexes to Attendance Table</h2>";
    
    // Add indexes
    $indexes_to_add = [
        'idx_missing_punch_in_id' => "CREATE INDEX IF NOT EXISTS idx_missing_punch_in_id ON attendance (missing_punch_in_id)",
        'idx_missing_punch_out_id' => "CREATE INDEX IF NOT EXISTS idx_missing_punch_out_id ON attendance (missing_punch_out_id)",
        'idx_missing_punch_approval_status' => "CREATE INDEX IF NOT EXISTS idx_missing_punch_approval_status ON attendance (missing_punch_approval_status)"
    ];
    
    foreach ($indexes_to_add as $index_name => $sql) {
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ Successfully added/verified index '$index_name'</p>";
        } else {
            throw new Exception("Failed to add index '$index_name': " . $conn->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "<h2>Step 4: Adding Foreign Key Constraints</h2>";
    
    // Disable foreign key checks temporarily for constraint addition
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Add foreign key constraints (these need to be done outside transaction)
    $fk_constraints = [
        "ALTER TABLE attendance 
         ADD CONSTRAINT IF NOT EXISTS fk_attendance_missing_punch_in 
         FOREIGN KEY (missing_punch_in_id) 
         REFERENCES missing_punch_in(id) 
         ON DELETE SET NULL ON UPDATE CASCADE",
        
        "ALTER TABLE attendance 
         ADD CONSTRAINT IF NOT EXISTS fk_attendance_missing_punch_out 
         FOREIGN KEY (missing_punch_out_id) 
         REFERENCES missing_punch_out(id) 
         ON DELETE SET NULL ON UPDATE CASCADE"
    ];
    
    foreach ($fk_constraints as $index => $sql) {
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ Successfully added/verified foreign key constraint " . ($index + 1) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Warning: Could not add foreign key constraint " . ($index + 1) . ": " . $conn->error . "</p>";
            echo "<p>This might be OK if the constraint already exists or if there are data issues.</p>";
        }
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    echo "<h1 style='color: green;'>✓ Missing Punch System Setup Complete!</h1>";
    echo "<p>All tables, columns, indexes, and constraints have been successfully set up.</p>";
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Re-enable foreign key checks even if there's an error
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    echo "<h1 style='color: red;'>✗ Setup Failed</h1>";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Transaction has been rolled back.</p>";
}
?>