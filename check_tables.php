<?php
// Check if the required tables exist in the database
require_once('includes/db_connect.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Table Check</h1>";

// Check tables using mysqli
$tables_to_check = [
    'sv_inventory_items',
    'sv_calendar_events'
];

echo "<h2>Using mysqli:</h2>";
echo "<ul>";
foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<li style='color: green;'>Table '$table' exists</li>";
        
        // Show column structure
        echo "<ul>";
        $columns = $conn->query("DESCRIBE $table");
        while ($column = $columns->fetch_assoc()) {
            echo "<li>{$column['Field']} ({$column['Type']})</li>";
        }
        echo "</ul>";
    } else {
        echo "<li style='color: red;'>Table '$table' does not exist</li>";
    }
}
echo "</ul>";

// Check related/similar tables
echo "<h2>Similar tables:</h2>";
echo "<ul>";
$result = $conn->query("SHOW TABLES LIKE '%inventory%'");
while ($row = $result->fetch_array()) {
    echo "<li>{$row[0]}</li>";
}
$result = $conn->query("SHOW TABLES LIKE '%calendar%'");
while ($row = $result->fetch_array()) {
    echo "<li>{$row[0]}</li>";
}
echo "</ul>";
?> 