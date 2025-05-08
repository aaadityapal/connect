<?php
// Test file to check MySQLi database connection

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Testing MySQLi Database Connection</h1>";

// Database connection parameters
$host = 'localhost';
$dbname = 'crm';
$username = 'root';
$password = '';

try {
    // Create MySQLi connection
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset and timezone
    $conn->set_charset("utf8mb4");
    $conn->query("SET time_zone = '+05:30'"); // IST
    
    echo "<p style='color:green'>✅ MySQLi connection successful!</p>";
    
    // Make the connection available globally
    $GLOBALS['conn'] = $conn;
    
    // Test query to list tables
    $tablesResult = $conn->query("SHOW TABLES");
    
    if ($tablesResult) {
        $tableCount = $tablesResult->num_rows;
        echo "<p>Found $tableCount tables in database</p>";
        
        echo "<ul>";
        while ($row = $tablesResult->fetch_array(MYSQLI_NUM)) {
            echo "<li>{$row[0]}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>❌ Error listing tables: " . $conn->error . "</p>";
    }
    
    // Check for specific HR tables
    $hrTables = [
        'hr_supervisor_calendar_site_events',
        'hr_supervisor_vendor_registry',
        'hr_supervisor_laborer_registry',
        'hr_supervisor_activity_log'
    ];
    
    echo "<h2>HR Tables Check</h2>";
    echo "<ul>";
    
    foreach ($hrTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        
        if ($result && $result->num_rows > 0) {
            echo "<li style='color:green'>✅ $table exists</li>";
            
            // If it's the events table, check for records
            if ($table === 'hr_supervisor_calendar_site_events') {
                $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
                $countRow = $countResult->fetch_assoc();
                echo "<ul><li>Records: " . $countRow['count'] . "</li></ul>";
            }
        } else {
            echo "<li style='color:red'>❌ $table does not exist</li>";
        }
    }
    
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 