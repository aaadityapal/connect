<?php
/**
 * Create Test Overtime Record
 * 
 * This script creates a test overtime record in the overtime_notifications table
 * with ID 633 to help resolve foreign key constraint issues.
 */

// Include database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Create Test Overtime Record</h1>";

// Check if the overtime_notifications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_notifications'");
if (mysqli_num_rows($table_check) == 0) {
    die("<p style='color:red'>Error: The overtime_notifications table does not exist!</p>");
}

// Check if record with ID 633 already exists
$check_query = "SELECT id FROM overtime_notifications WHERE id = 633";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) > 0) {
    echo "<p style='color:orange'>A record with ID 633 already exists in the overtime_notifications table.</p>";
} else {
    // Create a test record with ID 633
    $insert_query = "INSERT INTO overtime_notifications (id, user_id, date, shift_id, hours, status, created_at) 
                     VALUES (633, 1, CURDATE(), 1, 2.5, 'approved', NOW())";
    
    if (mysqli_query($conn, $insert_query)) {
        echo "<p style='color:green'>Successfully created test record with ID 633 in the overtime_notifications table.</p>";
    } else {
        echo "<p style='color:red'>Error creating test record: " . mysqli_error($conn) . "</p>";
        
        // Try to determine the structure of the overtime_notifications table
        echo "<h2>Overtime Notifications Table Structure</h2>";
        $structure_query = "DESCRIBE overtime_notifications";
        $structure_result = mysqli_query($conn, $structure_query);
        
        if ($structure_result) {
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
            
            // Try to create a more flexible insert query based on the table structure
            echo "<h2>Attempting Flexible Insert</h2>";
            
            // Reset the result pointer
            mysqli_data_seek($structure_result, 0);
            
            $required_fields = [];
            $optional_fields = [];
            
            while ($row = mysqli_fetch_assoc($structure_result)) {
                $field = $row['Field'];
                $null = $row['Null'];
                $default = $row['Default'];
                $extra = $row['Extra'];
                
                // Skip auto_increment fields
                if (strpos($extra, 'auto_increment') !== false) {
                    continue;
                }
                
                // Fields that must be provided
                if ($null === 'NO' && $default === NULL) {
                    $required_fields[] = $field;
                } else {
                    $optional_fields[] = $field;
                }
            }
            
            echo "<p>Required fields: " . implode(", ", $required_fields) . "</p>";
            echo "<p>Optional fields: " . implode(", ", $optional_fields) . "</p>";
            
            // Create a minimal insert with only required fields
            $fields = [];
            $values = [];
            
            foreach ($required_fields as $field) {
                $fields[] = $field;
                
                if ($field === 'id') {
                    $values[] = '633';
                } else if ($field === 'user_id') {
                    $values[] = '1';
                } else if ($field === 'date') {
                    $values[] = 'CURDATE()';
                } else if ($field === 'created_at' || $field === 'updated_at') {
                    $values[] = 'NOW()';
                } else if ($field === 'status') {
                    $values[] = "'approved'";
                } else if ($field === 'hours' || $field === 'overtime_hours') {
                    $values[] = '2.5';
                } else if ($field === 'shift_id') {
                    $values[] = '1';
                } else {
                    $values[] = "''"; // Empty string as default
                }
            }
            
            $flexible_query = "INSERT INTO overtime_notifications (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
            echo "<p>Flexible query: $flexible_query</p>";
            
            if (mysqli_query($conn, $flexible_query)) {
                echo "<p style='color:green'>Successfully created test record with ID 633 using flexible query!</p>";
            } else {
                echo "<p style='color:red'>Error with flexible query: " . mysqli_error($conn) . "</p>";
            }
        } else {
            echo "<p style='color:red'>Error getting table structure: " . mysqli_error($conn) . "</p>";
        }
    }
}

// Close the database connection
mysqli_close($conn);
?> 