<?php
/**
 * Insert Test Overtime Record
 * 
 * This script inserts a test record into the overtime_notifications table
 * with a specific ID to help resolve foreign key constraint issues.
 */

// Include database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Insert Test Overtime Record</h1>";

// Check if the overtime_notifications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_notifications'");
if (mysqli_num_rows($table_check) == 0) {
    die("<p style='color:red'>Error: The overtime_notifications table does not exist!</p>");
}

// Get the structure of the overtime_notifications table
$structure_query = "DESCRIBE overtime_notifications";
$structure_result = mysqli_query($conn, $structure_query);

if (!$structure_result) {
    die("<p style='color:red'>Error getting table structure: " . mysqli_error($conn) . "</p>");
}

echo "<h2>Table Structure</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

$fields = [];
while ($row = mysqli_fetch_assoc($structure_result)) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
    
    $fields[] = $row['Field'];
}

echo "</table>";

// Check if a record with ID 633 already exists
$check_query = "SELECT id FROM overtime_notifications WHERE id = 633";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) > 0) {
    echo "<p style='color:orange'>A record with ID 633 already exists in the overtime_notifications table.</p>";
} else {
    // Try different approaches to insert the record
    echo "<h2>Attempting to Insert Record</h2>";
    
    // Approach 1: Basic insert with minimal fields
    $insert_query1 = "INSERT INTO overtime_notifications (id, user_id, date, hours, status, created_at) 
                     VALUES (633, 1, CURDATE(), 2.5, 'approved', NOW())";
    
    echo "<p>Trying query 1: $insert_query1</p>";
    
    if (mysqli_query($conn, $insert_query1)) {
        echo "<p style='color:green'>Successfully inserted test record with ID 633 using query 1!</p>";
    } else {
        $error1 = mysqli_error($conn);
        echo "<p style='color:red'>Error with query 1: $error1</p>";
        
        // Approach 2: Try with more fields
        $insert_query2 = "INSERT INTO overtime_notifications (id, user_id, date, shift_id, hours, status, created_at) 
                         VALUES (633, 1, CURDATE(), 1, 2.5, 'approved', NOW())";
        
        echo "<p>Trying query 2: $insert_query2</p>";
        
        if (mysqli_query($conn, $insert_query2)) {
            echo "<p style='color:green'>Successfully inserted test record with ID 633 using query 2!</p>";
        } else {
            $error2 = mysqli_error($conn);
            echo "<p style='color:red'>Error with query 2: $error2</p>";
            
            // Approach 3: Get all current records to understand the structure
            echo "<h2>Examining Existing Records</h2>";
            
            $sample_query = "SELECT * FROM overtime_notifications LIMIT 1";
            $sample_result = mysqli_query($conn, $sample_query);
            
            if ($sample_result && mysqli_num_rows($sample_result) > 0) {
                $sample = mysqli_fetch_assoc($sample_result);
                
                echo "<table border='1' cellpadding='5' cellspacing='0'>";
                echo "<tr>";
                foreach ($fields as $field) {
                    echo "<th>$field</th>";
                }
                echo "</tr>";
                
                echo "<tr>";
                foreach ($fields as $field) {
                    echo "<td>" . (isset($sample[$field]) ? $sample[$field] : 'NULL') . "</td>";
                }
                echo "</tr>";
                echo "</table>";
                
                // Approach 4: Clone an existing record but with ID 633
                $clone_query = "INSERT INTO overtime_notifications 
                               SELECT 633, " . implode(", ", array_filter($fields, function($field) { return $field != 'id'; })) . " 
                               FROM overtime_notifications LIMIT 1";
                
                echo "<p>Trying query 4 (clone): $clone_query</p>";
                
                if (mysqli_query($conn, $clone_query)) {
                    echo "<p style='color:green'>Successfully inserted test record with ID 633 by cloning!</p>";
                } else {
                    echo "<p style='color:red'>Error with clone query: " . mysqli_error($conn) . "</p>";
                }
            } else {
                echo "<p style='color:red'>No existing records found to examine.</p>";
            }
        }
    }
}

// Close the database connection
mysqli_close($conn);

echo "<p>Script completed.</p>";
?> 