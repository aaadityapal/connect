<?php
// Test file to check database connection

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Testing Database Connection</h1>";

try {
    // Include the database connection file
    require_once 'includes/config/db_connect.php';
    
    // Test connection with PDO
    echo "<h2>PDO Connection</h2>";
    if (isset($pdo)) {
        echo "<p style='color:green'>✅ PDO connection successful!</p>";
        
        // List tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Found " . count($tables) . " tables in database</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>❌ PDO connection variable not set</p>";
    }
    
    // Test connection with mysqli
    echo "<h2>MySQLi Connection</h2>";
    if (isset($conn) && $conn instanceof mysqli) {
        echo "<p style='color:green'>✅ MySQLi connection successful!</p>";
        
        // List tables
        $result = $conn->query("SHOW TABLES");
        $tableCount = $result->num_rows;
        echo "<p>Found $tableCount tables in database</p>";
        
        echo "<ul>";
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            echo "<li>{$row[0]}</li>";
        }
        echo "</ul>";
        
        // Check if required tables exist
        $requiredTables = [
            'hr_supervisor_calendar_site_events',
            'hr_supervisor_vendor_registry',
            'hr_supervisor_laborer_registry',
            'hr_supervisor_activity_log'
        ];
        
        echo "<h3>Required Tables Check</h3>";
        echo "<ul>";
        foreach ($requiredTables as $table) {
            $tableExists = $conn->query("SHOW TABLES LIKE '$table'")->num_rows > 0;
            if ($tableExists) {
                echo "<li style='color:green'>✅ $table - exists</li>";
            } else {
                echo "<li style='color:red'>❌ $table - missing</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>❌ MySQLi connection variable not set or invalid</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database connection error: " . $e->getMessage() . "</p>";
}
?> 