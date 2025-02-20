<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

try {
    // Display current PHP version
    echo "<h3>PHP Version:</h3>";
    echo PHP_VERSION . "<br><br>";

    // Load config file
    echo "<h3>Loading Config:</h3>";
    if (file_exists('config.php')) {
        echo "Config file found<br>";
        require_once 'config.php';
        echo "Config file loaded<br><br>";
    } else {
        throw new Exception("Config file not found");
    }

    // Display database settings (hide password)
    echo "<h3>Database Settings:</h3>";
    echo "Host: " . (defined('DB_HOST') ? DB_HOST : 'Not defined') . "<br>";
    echo "Database: " . (defined('DB_NAME') ? DB_NAME : 'Not defined') . "<br>";
    echo "Username: " . (defined('DB_USER') ? DB_USER : 'Not defined') . "<br>";
    echo "Port: " . (defined('DB_PORT') ? DB_PORT : 'Not defined (using default)') . "<br><br>";

    // Test database connection
    echo "<h3>Connection Test:</h3>";
    if (!isset($pdo)) {
        // Try creating a new connection if $pdo is not set
        echo "Creating new PDO connection...<br>";
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
        echo "New connection created successfully<br>";
    } else {
        echo "Using existing PDO connection<br>";
    }

    // Test a simple query
    $stmt = $pdo->query("SELECT 1");
    echo "Test query executed successfully<br>";

    // Get MySQL version
    $version = $pdo->query("SELECT VERSION() as version")->fetch();
    echo "MySQL Version: " . $version['version'] . "<br>";

} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "Code: " . $e->getCode() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?> 