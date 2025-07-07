<?php
/**
 * Alter Overtime Payments Table
 * 
 * This script modifies the overtime_payments table to remove the foreign key constraint
 * that is causing issues with the payment process.
 */

// Include database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Alter Overtime Payments Table</h1>";

// Check if the overtime_payments table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_payments'");
if (mysqli_num_rows($table_check) == 0) {
    die("<p style='color:red'>Error: The overtime_payments table does not exist!</p>");
}

// Get the current table structure
echo "<h2>Current Table Structure</h2>";
$structure_query = "DESCRIBE overtime_payments";
$structure_result = mysqli_query($conn, $structure_query);

if (!$structure_result) {
    die("<p style='color:red'>Error getting table structure: " . mysqli_error($conn) . "</p>");
}

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = mysqli_fetch_assoc($structure_result)) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}

echo "</table>";

// Get the current foreign keys
echo "<h2>Current Foreign Keys</h2>";
$fk_query = "SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'overtime_payments' 
             AND REFERENCED_TABLE_NAME IS NOT NULL";
$fk_result = mysqli_query($conn, $fk_query);

if (!$fk_result) {
    echo "<p style='color:red'>Error getting foreign keys: " . mysqli_error($conn) . "</p>";
} else {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Constraint Name</th><th>Column Name</th><th>Referenced Table</th><th>Referenced Column</th></tr>";
    
    $constraints = [];
    while ($row = mysqli_fetch_assoc($fk_result)) {
        echo "<tr>";
        echo "<td>{$row['CONSTRAINT_NAME']}</td>";
        echo "<td>{$row['COLUMN_NAME']}</td>";
        echo "<td>{$row['REFERENCED_TABLE_NAME']}</td>";
        echo "<td>{$row['REFERENCED_COLUMN_NAME']}</td>";
        echo "</tr>";
        
        $constraints[] = $row['CONSTRAINT_NAME'];
    }
    
    echo "</table>";
    
    // Drop the foreign key constraints
    echo "<h2>Dropping Foreign Key Constraints</h2>";
    
    foreach ($constraints as $constraint) {
        $drop_query = "ALTER TABLE overtime_payments DROP FOREIGN KEY `$constraint`";
        
        if (mysqli_query($conn, $drop_query)) {
            echo "<p style='color:green'>Successfully dropped foreign key constraint: $constraint</p>";
        } else {
            echo "<p style='color:red'>Error dropping foreign key constraint $constraint: " . mysqli_error($conn) . "</p>";
        }
    }
    
    // Add back the employee and processor foreign keys but not the overtime one
    echo "<h2>Adding Back Safe Foreign Keys</h2>";
    
    // Add foreign key for employee_id
    $add_employee_fk = "ALTER TABLE overtime_payments 
                        ADD CONSTRAINT fk_overtime_payment_employee 
                        FOREIGN KEY (employee_id) REFERENCES users (id) 
                        ON DELETE CASCADE ON UPDATE CASCADE";
    
    if (mysqli_query($conn, $add_employee_fk)) {
        echo "<p style='color:green'>Successfully added employee foreign key constraint</p>";
    } else {
        echo "<p style='color:red'>Error adding employee foreign key constraint: " . mysqli_error($conn) . "</p>";
    }
    
    // Add foreign key for processed_by
    $add_processor_fk = "ALTER TABLE overtime_payments 
                         ADD CONSTRAINT fk_overtime_payment_processor 
                         FOREIGN KEY (processed_by) REFERENCES users (id) 
                         ON DELETE CASCADE ON UPDATE CASCADE";
    
    if (mysqli_query($conn, $add_processor_fk)) {
        echo "<p style='color:green'>Successfully added processor foreign key constraint</p>";
    } else {
        echo "<p style='color:red'>Error adding processor foreign key constraint: " . mysqli_error($conn) . "</p>";
    }
}

echo "<p>Table modification complete. The foreign key constraint on overtime_id has been removed.</p>";

// Close the database connection
mysqli_close($conn);
?> 