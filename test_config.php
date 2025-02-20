<?php
// Database credentials
$host = 'localhost';
$username = 'root';     // Your MySQL username
$password = '';         // Your MySQL password
$database = 'crm';      // Changed from 'hr' to 'crm'

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Test connection
if (!$conn->ping()) {
    die("Database connection lost");
}

error_log("Database connection established successfully");
?>