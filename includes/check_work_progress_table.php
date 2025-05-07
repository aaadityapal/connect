<?php
// Check and create work_progress_media table if needed

// Include database connection
require_once '../config/db_connect.php';

try {
    echo "Checking work_progress_media table...<br>";
    
    // Check if table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'work_progress_media'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Table doesn't exist, create it
        echo "Table does not exist. Creating work_progress_media table...<br>";
        
        $sql = "CREATE TABLE work_progress_media (
            id INT(11) NOT NULL AUTO_INCREMENT,
            work_progress_id INT(11) NOT NULL,
            media_type VARCHAR(50) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            description TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_work_progress_id (work_progress_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($sql);
        echo "Successfully created work_progress_media table!<br>";
    } else {
        echo "Table work_progress_media already exists.<br>";
    }
    
    // Check for uploads directory
    $uploadDir = dirname(dirname(__FILE__)) . '/uploads/work_progress/';
    if (!file_exists($uploadDir)) {
        echo "Creating upload directory: $uploadDir<br>";
        if (mkdir($uploadDir, 0777, true)) {
            chmod($uploadDir, 0777);
            echo "Upload directory created successfully.<br>";
        } else {
            echo "Failed to create upload directory. Please check permissions.<br>";
        }
    } else {
        echo "Upload directory exists.<br>";
        
        if (is_writable($uploadDir)) {
            echo "Upload directory is writable.<br>";
        } else {
            echo "Warning: Upload directory is not writable. Attempting to set permissions...<br>";
            if (chmod($uploadDir, 0777)) {
                echo "Permissions updated successfully.<br>";
            } else {
                echo "Failed to update directory permissions. Please manually set writable permissions.<br>";
            }
        }
    }
    
    // Print PHP configuration related to file uploads
    echo "<h3>PHP Configuration:</h3>";
    echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
    echo "post_max_size: " . ini_get('post_max_size') . "<br>";
    echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
    echo "memory_limit: " . ini_get('memory_limit') . "<br>";
    
    echo "<h3>Environment:</h3>";
    echo "PHP Version: " . phpversion() . "<br>";
    echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 