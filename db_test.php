<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

try {
    echo "<div>PHP Version: " . phpversion() . "</div>";
    
    // Check if PDO is available
    echo "<div>PDO Available: " . (class_exists('PDO') ? 'Yes' : 'No') . "</div>";
    
    // Check which PDO drivers are available
    echo "<div>PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "</div>";
    
    // Try to include the database connection file and see if it works
    echo "<div>Attempting to connect using config/db_connect.php...</div>";
    
    // Include the DB connection file
    require_once 'config/db_connect.php';
    
    echo "<div style='color:green'>✓ Database connected successfully!</div>";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT VERSION() as version");
    $dbVersion = $stmt->fetchColumn();
    echo "<div>Database version: " . htmlspecialchars($dbVersion) . "</div>";
    
    // Try a query on the work_progress_media table
    $tableQuery = $pdo->query("SHOW TABLES LIKE 'work_progress_media'");
    $tableExists = $tableQuery->rowCount() > 0;
    echo "<div>work_progress_media table exists: " . ($tableExists ? 'Yes' : 'No') . "</div>";
    
    if ($tableExists) {
        $countQuery = $pdo->query("SELECT COUNT(*) FROM work_progress_media");
        $count = $countQuery->fetchColumn();
        echo "<div>Number of records in work_progress_media: " . $count . "</div>";
        
        // Show the table structure
        $stmt = $pdo->query("DESCRIBE work_progress_media");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Table Structure:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            foreach ($column as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='color:red'>Error: " . $e->getMessage() . "</div>";
}
?> 