<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Include database connection
require_once 'config/db_connect.php';

echo "<h1>Database Connection Test</h1>";

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "<p>Database connection successful!</p>";
}

// Test database query
$query = "SHOW TABLES";
$result = $conn->query($query);
if ($result) {
    echo "<p>Query executed successfully</p>";
    
    echo "<h2>Tables in Database:</h2>";
    echo "<ul>";
    while ($row = $result->fetch_row()) {
        echo "<li>{$row[0]}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Query failed: " . $conn->error . "</p>";
}

// Check attendance table structure
echo "<h2>Attendance Table Structure:</h2>";
$tableQuery = "DESCRIBE attendance";
$tableResult = $conn->query($tableQuery);
if ($tableResult) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $tableResult->fetch_assoc()) {
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
} else {
    echo "<p>Failed to get table structure: " . $conn->error . "</p>";
}

// Test timestamp formats
echo "<h2>Timestamp Format Tests:</h2>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Current Unix timestamp: " . time() . "</p>";

$testTime = time();
$timeQuery = "SELECT FROM_UNIXTIME($testTime) as formatted_time, UNIX_TIMESTAMP(FROM_UNIXTIME($testTime)) as back_to_unix";
$timeResult = $conn->query($timeQuery);
if ($timeResult) {
    $row = $timeResult->fetch_assoc();
    echo "<p>Test timestamp: $testTime</p>";
    echo "<p>Formatted via MySQL: {$row['formatted_time']}</p>";
    echo "<p>Back to Unix timestamp: {$row['back_to_unix']}</p>";
    
    if ($testTime != $row['back_to_unix']) {
        echo "<p style='color:red'>Warning: Timestamp conversion is not consistent! Difference: " . 
             ($row['back_to_unix'] - $testTime) . " seconds</p>";
    } else {
        echo "<p style='color:green'>Timestamp conversion is consistent</p>";
    }
} else {
    echo "<p>Timestamp test failed: " . $conn->error . "</p>";
}

// Set user for testing
$_SESSION['user_id'] = 1; // Set to any valid user ID

// Display test form
echo "<h2>Test Punch Form</h2>";
echo "<form method='POST' action='api/test_punch.php'>";
echo "Punch Type: <select name='punch_type'><option value='in'>In</option><option value='out'>Out</option></select><br>";
echo "Latitude: <input type='text' name='latitude' value='12.9716'><br>";
echo "Longitude: <input type='text' name='longitude' value='77.5946'><br>";
echo "Accuracy: <input type='text' name='accuracy' value='10'><br>";
echo "Address: <input type='text' name='address' value='Test Address'><br>";
echo "Work Report: <textarea name='work_report'>Test work report</textarea><br>";
echo "<input type='submit' value='Test Punch'>";
echo "</form>";

echo "<h2>Existing Records</h2>";
$recordsQuery = "SELECT * FROM attendance ORDER BY id DESC LIMIT 5";
$recordsResult = $conn->query($recordsQuery);
if ($recordsResult) {
    if ($recordsResult->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>User</th><th>Date</th><th>Punch In</th><th>Punch Out</th><th>Working Hours</th><th>Overtime Hours</th></tr>";
        while ($row = $recordsResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['date']}</td>";
            echo "<td>{$row['punch_in']}</td>";
            echo "<td>{$row['punch_out']}</td>";
            echo "<td>{$row['working_hours']}</td>";
            echo "<td>{$row['overtime_hours']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No attendance records found</p>";
    }
} else {
    echo "<p>Failed to get attendance records: " . $conn->error . "</p>";
}
?> 