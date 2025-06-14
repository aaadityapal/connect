<?php
// Include database connection
include 'config/db_connect.php';

// Check table structure
echo "<h2>Manager Payments Table Structure</h2>";
$result = $conn->query("DESCRIBE manager_payments");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
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
    echo "Error getting table structure: " . $conn->error;
}

// Check table data
echo "<h2>Manager Payments Data</h2>";
$result = $conn->query("SELECT * FROM manager_payments");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'><tr>";
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            echo "<th>" . $field->name . "</th>";
        }
        echo "</tr>";
        
        // Reset result pointer
        $result->data_seek(0);
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . $value . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No data found in the table.";
    }
} else {
    echo "Error getting table data: " . $conn->error;
}
?> 