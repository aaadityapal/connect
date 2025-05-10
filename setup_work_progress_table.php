<?php
// Setup Work Progress Tables for Calendar Events
// Run this script to ensure the work progress tables exist

// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';

echo "<h1>Work Progress Tables Setup</h1>";

try {
    // Create work progress table
    $sql = "CREATE TABLE IF NOT EXISTS `sv_work_progress` (
        `work_id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_id` INT NOT NULL,
        `work_category` VARCHAR(100) NOT NULL,
        `work_type` VARCHAR(100) NOT NULL,
        `work_done` ENUM('yes', 'no') DEFAULT 'yes',
        `remarks` TEXT,
        `sequence_number` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✓ Work progress table created or already exists!</p>";
    
    // Create work media table
    $sql = "CREATE TABLE IF NOT EXISTS `sv_work_progress_media` (
        `media_id` INT AUTO_INCREMENT PRIMARY KEY,
        `work_id` INT NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `media_type` ENUM('image', 'video') DEFAULT 'image',
        `file_size` INT,
        `sequence_number` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`work_id`) REFERENCES `sv_work_progress`(`work_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✓ Work progress media table created or already exists!</p>";
    
    // Check table structures
    echo "<h2>Work Progress Table Structure:</h2>";
    $result = $pdo->query("DESCRIBE sv_work_progress");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column}</li>";
    }
    echo "</ul>";
    
    echo "<h2>Work Progress Media Table Structure:</h2>";
    $result = $pdo->query("DESCRIBE sv_work_progress_media");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column}</li>";
    }
    echo "</ul>";
    
    // Create upload directory for media files
    $upload_dir = 'uploads/calendar_events/work_progress_media/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        echo "<p style='color:green'>✓ Created upload directory for work progress media files</p>";
    } else {
        echo "<p style='color:green'>✓ Upload directory for work progress media already exists</p>";
    }
    
    // Insert a test work progress entry if requested
    if (isset($_GET['test']) && $_GET['test'] == 1) {
        // First check if there's at least one event to link to
        $eventCheck = $pdo->query("SELECT event_id FROM sv_calendar_events LIMIT 1");
        $event = $eventCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($event) {
            $eventId = $event['event_id'];
            
            // Insert a test work progress entry
            $stmt = $pdo->prepare("INSERT INTO sv_work_progress 
                (event_id, work_category, work_type, work_done, remarks, sequence_number) 
                VALUES (:event_id, :category, :type, :done, :remarks, :seq)");
                
            $stmt->execute([
                ':event_id' => $eventId,
                ':category' => 'Test Category',
                ':type' => 'Test Work Type',
                ':done' => 'yes',
                ':remarks' => 'This is a test work progress entry for development and testing.',
                ':seq' => 1
            ]);
            
            $workId = $pdo->lastInsertId();
            echo "<p style='color:green'>✓ Test work progress entry inserted successfully!</p>";
            
            // Show the inserted data
            $testData = $pdo->query("SELECT * FROM sv_work_progress WHERE work_id = {$workId}")->fetch(PDO::FETCH_ASSOC);
            
            echo "<h2>Test Work Progress Data:</h2>";
            echo "<pre>";
            print_r($testData);
            echo "</pre>";
            
            // Create a test media entry
            $mediaPath = $upload_dir . 'test_image.png';
            $fileName = 'test_image.png';
            
            $stmt = $pdo->prepare("INSERT INTO sv_work_progress_media 
                (work_id, file_name, file_path, media_type, file_size, sequence_number) 
                VALUES (:work_id, :file_name, :file_path, :media_type, :file_size, :seq)");
                
            $stmt->execute([
                ':work_id' => $workId,
                ':file_name' => $fileName,
                ':file_path' => $mediaPath,
                ':media_type' => 'image',
                ':file_size' => 1024,
                ':seq' => 1
            ]);
            
            echo "<p style='color:green'>✓ Test work progress media entry inserted successfully!</p>";
            
            // Show the inserted data
            $mediaId = $pdo->lastInsertId();
            $testMedia = $pdo->query("SELECT * FROM sv_work_progress_media WHERE media_id = {$mediaId}")->fetch(PDO::FETCH_ASSOC);
            
            echo "<h2>Test Work Progress Media Data:</h2>";
            echo "<pre>";
            print_r($testMedia);
            echo "</pre>";
        } else {
            echo "<p style='color:orange'>⚠️ No events found to link test work progress to. Please create an event first.</p>";
        }
    }
    
    echo "<p><a href='?test=1' style='display:inline-block; background:#3498db; color:white; padding:10px 15px; text-decoration:none; border-radius:4px;'>Insert Test Work Progress</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 