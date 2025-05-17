<?php
// Test database connectivity
require_once 'config/db_connect.php';

// Check if connection is successful
echo "Database connection: " . ($conn ? "Success" : "Failed") . "<br>";

// Check attendance table structure
$result = $conn->query('SHOW COLUMNS FROM attendance');
if ($result) {
    echo "<h3>Attendance Table Structure:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "Error checking table structure: " . $conn->error;
}

// Check recent attendance records
$records_result = $conn->query('SELECT * FROM attendance ORDER BY id DESC LIMIT 5');
if ($records_result) {
    echo "<h3>Recent Attendance Records:</h3>";
    echo "<table border='1'>";
    
    // Table headers
    echo "<tr>";
    $field_info = $records_result->fetch_fields();
    foreach ($field_info as $field) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    // Table data
    while($record = $records_result->fetch_assoc()) {
        echo "<tr>";
        foreach ($record as $key => $value) {
            if ($key == 'punch_in_photo' || $key == 'punch_out_photo') {
                echo "<td>" . (empty($value) ? "NULL" : "[PHOTO: $value]") . "</td>";
            } else {
                echo "<td>" . $value . "</td>";
            }
        }
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "Error checking records: " . $conn->error;
}
?> 