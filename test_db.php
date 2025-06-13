<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

try {
    // Database connection parameters
    $host = 'localhost';
    $dbname = 'crm';
    $username = 'root';
    $password = '';

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
    
    echo "<p style='color:green'>Database connection successful!</p>";
    
    // Check if project_payouts table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'project_payouts'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>project_payouts table exists!</p>";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE project_payouts");
        echo "<h2>Table Structure:</h2>";
        echo "<pre>";
        while ($row = $stmt->fetch()) {
            print_r($row);
        }
        echo "</pre>";
        
        // Try to insert a test record
        $stmt = $pdo->prepare("
            INSERT INTO project_payouts (
                project_name, 
                project_type, 
                client_name, 
                project_date, 
                amount, 
                payment_mode, 
                project_stage
            ) VALUES (
                'Test Project',
                'Architecture',
                'Test Client',
                '2023-06-12',
                1000.00,
                'Cash',
                'Stage 1'
            )
        ");
        
        if ($stmt->execute()) {
            $id = $pdo->lastInsertId();
            echo "<p style='color:green'>Test record inserted successfully with ID: $id</p>";
            
            // Retrieve the record
            $stmt = $pdo->prepare("SELECT * FROM project_payouts WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            
            echo "<h2>Retrieved Record:</h2>";
            echo "<pre>";
            print_r($record);
            echo "</pre>";
            
            // Clean up - delete the test record
            $stmt = $pdo->prepare("DELETE FROM project_payouts WHERE id = ?");
            $stmt->execute([$id]);
            echo "<p style='color:green'>Test record deleted successfully</p>";
        } else {
            echo "<p style='color:red'>Failed to insert test record</p>";
        }
    } else {
        echo "<p style='color:red'>project_payouts table does not exist!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Database error: " . $e->getMessage() . "</p>";
}
?> 