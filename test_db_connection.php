<?php
// Simple database connection test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

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
    
    echo "<p style='color:green'>Connected to database successfully!</p>";
    
    // List some tables
    $tables_query = "SHOW TABLES";
    $result = $conn->query($tables_query);
    
    if ($result) {
        echo "<h2>Tables in Database:</h2>";
        echo "<ul>";
        while ($row = $result->fetch_row()) {
            echo "<li>{$row[0]}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Error listing tables: " . $conn->error . "</p>";
    }
    
    // Close connection
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 20px auto;
        padding: 0 20px;
    }
    h2 {
        color: #333;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }
    h3 {
        color: #444;
        margin-top: 20px;
    }
    pre {
        background-color: #f5f5f5;
        padding: 10px;
        border-radius: 3px;
        overflow: auto;
    }
    .success {
        color: green;
        font-weight: bold;
    }
    .error {
        color: red;
        font-weight: bold;
    }
    ul {
        background-color: #f8f8f8;
        padding: 10px 10px 10px 30px;
        border-radius: 3px;
    }
</style> 