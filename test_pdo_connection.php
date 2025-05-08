<?php
// Simple database connection test using PDO
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PDO Database Connection Test</h1>";

// Database connection parameters
$host = 'localhost';
$dbname = 'crm';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    echo "<p style='color:green'>Connected to database successfully!</p>";
    
    // List some tables
    $tables_query = "SHOW TABLES";
    $stmt = $pdo->query($tables_query);
    
    if ($stmt) {
        echo "<h2>Tables in Database:</h2>";
        echo "<ul>";
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            echo "<li>{$row[0]}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Error listing tables.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
} 