<?php
// Check the exact schema of specific tables
require_once('includes/db_connect.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Detailed Table Structure</h1>";

// Tables to check
$tables = [
    'sv_inventory_items',
    'sv_calendar_events'
];

foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";
    
    // Check if table exists
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows == 0) {
        echo "<p style='color: red;'>Table does not exist!</p>";
        continue;
    }
    
    // Show columns
    echo "<h3>Columns:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $columns = $conn->query("SHOW COLUMNS FROM $table");
    while ($column = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Sample data
    echo "<h3>Sample Data (First 5 rows):</h3>";
    $data = $conn->query("SELECT * FROM $table LIMIT 5");
    
    if ($data->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        
        // Headers
        echo "<tr>";
        $fields = $data->fetch_fields();
        foreach ($fields as $field) {
            echo "<th>{$field->name}</th>";
        }
        echo "</tr>";
        
        // Reset data pointer
        $data->data_seek(0);
        
        // Rows
        while ($row = $data->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                // Truncate long values
                if (strlen($value) > 100) {
                    $value = substr($value, 0, 100) . '...';
                }
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No data in table.</p>";
    }
}

// Show all tables in database that might be related
echo "<h2>All tables containing 'calendar', 'event', or 'inventory':</h2>";
echo "<ul>";
$pattern = "SHOW TABLES WHERE Tables_in_" . $dbname . " LIKE '%calendar%' OR Tables_in_" . $dbname . " LIKE '%event%' OR Tables_in_" . $dbname . " LIKE '%inventory%'";
$relatedTables = $conn->query($pattern);

if ($relatedTables) {
    while ($row = $relatedTables->fetch_array()) {
        echo "<li>{$row[0]}</li>";
    }
} else {
    echo "<li>Error querying related tables: " . $conn->error . "</li>";
}
echo "</ul>";
?> 