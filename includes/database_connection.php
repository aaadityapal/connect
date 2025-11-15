<?php
/**
 * Database Configuration and Connection
 * Centralized database connection constants
 */

// Database Configuration Constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'crm');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// Create connection using mysqli
$connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($connection->connect_error) {
    error_log("Database Connection failed: " . $connection->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Set charset to utf8mb4
$connection->set_charset("utf8mb4");

?>
