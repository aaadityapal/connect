<?php
require_once 'config/db_connect.php';

echo "<pre>";
echo "Current PHP timezone: " . date_default_timezone_get() . "\n";
echo "Current PHP time: " . date('Y-m-d H:i:s') . "\n\n";

$queries = [
    "SELECT @@global.time_zone as global_tz",
    "SELECT @@session.time_zone as session_tz",
    "SELECT NOW() as mysql_now",
    "SELECT CURRENT_TIMESTAMP as current_ts",
    "SELECT UNIX_TIMESTAMP() as unix_ts",
    "SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP) as time_diff"
];

foreach ($queries as $query) {
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    echo $query . ":\n";
    print_r($row);
    echo "\n";
}

// Check if timezone tables are loaded
$timezone_tables = $conn->query("SELECT COUNT(*) as count FROM mysql.time_zone_name");
if ($timezone_tables) {
    $row = $timezone_tables->fetch_assoc();
    echo "\nTimezone tables loaded: " . ($row['count'] > 0 ? 'Yes' : 'No') . "\n";
} else {
    echo "\nCould not check timezone tables. Error: " . $conn->error . "\n";
}

echo "</pre>";
?> 