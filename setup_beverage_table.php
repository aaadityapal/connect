<?php
// Setup Beverage Table for Calendar Events
// Run this script to ensure the beverages table exists

// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';

echo "<h1>Beverages Table Setup</h1>";

try {
    // Create the sv_event_beverages table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `sv_event_beverages` (
        `beverage_id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_id` INT NOT NULL,
        `beverage_type` VARCHAR(100),
        `beverage_name` VARCHAR(100),
        `amount` DECIMAL(10,2),
        `sequence_number` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✓ Beverages table created or already exists!</p>";
    
    // Check if the table was created successfully by querying its structure
    $result = $pdo->query("DESCRIBE sv_event_beverages");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Table Structure:</h2>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column}</li>";
    }
    echo "</ul>";
    
    // Insert a test beverage if requested
    if (isset($_GET['test']) && $_GET['test'] == 1) {
        // First check if there's at least one event to link to
        $eventCheck = $pdo->query("SELECT event_id FROM sv_calendar_events LIMIT 1");
        $event = $eventCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($event) {
            $eventId = $event['event_id'];
            
            // Insert a test beverage
            $stmt = $pdo->prepare("INSERT INTO sv_event_beverages 
                (event_id, beverage_type, beverage_name, amount, sequence_number) 
                VALUES (:event_id, :type, :name, :amount, :seq)");
                
            $stmt->execute([
                ':event_id' => $eventId,
                ':type' => 'Test Beverage Type',
                ':name' => 'Test Beverage Name',
                ':amount' => 150.50,
                ':seq' => 1
            ]);
            
            echo "<p style='color:green'>✓ Test beverage inserted successfully!</p>";
            
            // Show the inserted data
            $lastId = $pdo->lastInsertId();
            $testData = $pdo->query("SELECT * FROM sv_event_beverages WHERE beverage_id = {$lastId}")->fetch(PDO::FETCH_ASSOC);
            
            echo "<h2>Test Beverage Data:</h2>";
            echo "<pre>";
            print_r($testData);
            echo "</pre>";
        } else {
            echo "<p style='color:orange'>⚠️ No events found to link test beverage to. Please create an event first.</p>";
        }
    }
    
    echo "<p><a href='?test=1' style='display:inline-block; background:#4CAF50; color:white; padding:10px 15px; text-decoration:none; border-radius:4px;'>Insert Test Beverage</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 