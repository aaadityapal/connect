<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Database configuration
define('DB_HOST', 'localhost');     // Usually 'localhost' or '127.0.0.1'
define('DB_NAME', 'crm'); // Your database name
define('DB_USER', 'root');         // Your database username
define('DB_PASS', '');             // Your database password
define('DB_PORT', '3306');         // Usually 3306 for MySQL

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    throw new Exception("Database connection failed");
}

// Set timezone
date_default_timezone_set('Asia/Kolkata'); // Adjust to your timezone

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to get database connection
function getDBConnection() {
    global $pdo;
    return $pdo;
}

// Error handler
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    return true;
}

// Set error handler
set_error_handler("handleError"); 