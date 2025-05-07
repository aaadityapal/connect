<?php
$host = 'localhost';
$dbname = 'crm';  // Your database name
$username = 'root'; // Your database username
$password = '';     // Your database password

// Create connection using mysqli
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// For backward compatibility
$pdo = null;
try {
    $dsn = "mysql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("PDO Connection failed: " . $e->getMessage());
    // We already have mysqli, so we don't need to die here
}
?> 