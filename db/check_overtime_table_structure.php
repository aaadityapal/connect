<?php
/**
 * Check Overtime Notifications Table Structure
 * 
 * This script examines the structure of the overtime_notifications table
 * to help debug issues with column names.
 */

// Include database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Overtime Notifications Table Structure</h1>";

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

// Check for specific columns
$has_user_id = in_array('user_id', $fields);
$has_employee_id = in_array('employee_id', $fields);

echo "<h2>Column Check</h2>";
echo "<p>user_id column exists: <strong>" . ($has_user_id ? "Yes" : "No") . "</strong></p>";
echo "<p>employee_id column exists: <strong>" . ($has_employee_id ? "Yes" : "No") . "</strong></p>";

// Get sample data
$sample_query = "SELECT * FROM overtime_notifications LIMIT 5";
$sample_result = mysqli_query($conn, $sample_query);

if ($sample_result && mysqli_num_rows($sample_result) > 0) {
    echo "<h2>Sample Data</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    
    // Table header
    echo "<tr>";
    foreach ($fields as $field) {
        echo "<th>$field</th>";
    }
    echo "</tr>";
    
    // Table data
    while ($row = mysqli_fetch_assoc($sample_result)) {
        echo "<tr>";
        foreach ($fields as $field) {
            echo "<td>" . (isset($row[$field]) ? htmlspecialchars($row[$field]) : 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No sample data available.</p>";
}

// Suggest a fix
echo "<h2>Suggested Fix</h2>";

if ($has_user_id) {
    echo "<p>Use <code>user_id</code> in your queries.</p>";
} else if ($has_employee_id) {
    echo "<p>Use <code>employee_id</code> in your queries.</p>";
} else {
    echo "<p style='color:red'>Neither <code>user_id</code> nor <code>employee_id</code> column found. Check the table structure carefully.</p>";
    
    // Suggest column to use for user/employee ID
    $potential_id_columns = array_filter($fields, function($field) {
        return strpos($field, 'id') !== false || strpos($field, 'user') !== false || strpos($field, 'employee') !== false;
    });
    
    if (!empty($potential_id_columns)) {
        echo "<p>Potential columns to use for user/employee ID: " . implode(", ", $potential_id_columns) . "</p>";
    }
}

// Close the database connection
mysqli_close($conn);

echo "<p>Script completed.</p>";
?> 