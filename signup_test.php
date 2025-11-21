<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...<br>";

try {
    // Test the require
    require_once __DIR__ . '/config/db_connect.php';
    echo "✅ Database connection required successfully<br>";
    
    // Test PDO exists
    if (isset($pdo)) {
        echo "✅ PDO object exists<br>";
        
        // Test a simple query
        $result = $pdo->query("SELECT 1");
        echo "✅ Simple query works<br>";
        
        // Test users table exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $row = $stmt->fetch();
        echo "✅ Users table has " . $row['count'] . " records<br>";
    } else {
        echo "❌ PDO object not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>
