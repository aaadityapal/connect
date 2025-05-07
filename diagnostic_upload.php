<?php
// Set content type to text/html for easier debugging
header('Content-Type: text/html');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Upload Diagnostics</h1>";

try {
    // Connect to database
    require_once 'config/db_connect.php';
    echo "<div style='color:green'>✓ Database connection successful</div>";
    
    // Check if the work_progress_media table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'work_progress_media'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<div style='color:green'>✓ work_progress_media table exists</div>";
        
        // Check table structure
        $stmt = $pdo->prepare("DESCRIBE work_progress_media");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Table Structure:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $hasRequiredColumns = true;
        $requiredColumns = [
            'id' => false,
            'work_progress_id' => false,
            'media_type' => false,
            'file_path' => false,
            'description' => false,
            'created_at' => false
        ];
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
            
            if (isset($requiredColumns[$column['Field']])) {
                $requiredColumns[$column['Field']] = true;
            }
            
            // Check for specific issues with column types
            if ($column['Field'] == 'media_type' && $column['Type'] == "enum('image','video')") {
                echo "<div style='color:blue'>ℹ️ media_type is an enum limited to 'image' and 'video'</div>";
            }
        }
        
        echo "</table>";
        
        // Check for missing columns
        foreach ($requiredColumns as $column => $exists) {
            if (!$exists) {
                echo "<div style='color:red'>✗ Required column '$column' is missing!</div>";
                $hasRequiredColumns = false;
            }
        }
        
        if ($hasRequiredColumns) {
            echo "<div style='color:green'>✓ All required columns exist</div>";
        }
        
        // Check existing data
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM work_progress_media");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<div>Total records in work_progress_media: {$result['count']}</div>";
        
        if ($result['count'] > 0) {
            // Display some recent records
            $stmt = $pdo->prepare("SELECT * FROM work_progress_media ORDER BY created_at DESC LIMIT 5");
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h2>Recent Records:</h2>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr>";
            foreach (array_keys($records[0]) as $key) {
                echo "<th>$key</th>";
            }
            echo "</tr>";
            
            foreach ($records as $record) {
                echo "<tr>";
                foreach ($record as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<div style='color:red'>✗ work_progress_media table does not exist!</div>";
        
        // Create the table
        echo "<h3>Creating table...</h3>";
        $sql = "CREATE TABLE work_progress_media (
            id INT(11) NOT NULL AUTO_INCREMENT,
            work_progress_id INT(11) NOT NULL,
            media_type ENUM('image', 'video') NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            description TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_work_progress_id (work_progress_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($sql);
        echo "<div style='color:green'>✓ work_progress_media table created successfully</div>";
    }
    
    // Check uploads directory
    $uploadDir = __DIR__ . '/uploads/work_progress/';
    if (!file_exists($uploadDir)) {
        echo "<div style='color:orange'>⚠️ Upload directory does not exist, creating it...</div>";
        if (mkdir($uploadDir, 0777, true)) {
            echo "<div style='color:green'>✓ Created upload directory: $uploadDir</div>";
        } else {
            echo "<div style='color:red'>✗ Failed to create upload directory!</div>";
        }
    } else {
        echo "<div style='color:green'>✓ Upload directory exists: $uploadDir</div>";
        
        if (!is_writable($uploadDir)) {
            echo "<div style='color:red'>✗ Upload directory is not writable!</div>";
            echo "<div>Attempting to set permissions...</div>";
            chmod($uploadDir, 0777);
            if (is_writable($uploadDir)) {
                echo "<div style='color:green'>✓ Successfully set directory permissions</div>";
            } else {
                echo "<div style='color:red'>✗ Failed to set directory permissions!</div>";
            }
        } else {
            echo "<div style='color:green'>✓ Upload directory is writable</div>";
        }
        
        // List files in upload directory
        $files = scandir($uploadDir);
        $fileCount = count($files) - 2; // Subtract . and ..
        echo "<div>Files in upload directory: $fileCount</div>";
        
        if ($fileCount > 0) {
            echo "<h3>Recent Uploaded Files:</h3>";
            echo "<ul>";
            $i = 0;
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    echo "<li>" . htmlspecialchars($file) . " - " . date("Y-m-d H:i:s", filemtime($uploadDir . $file)) . "</li>";
                    $i++;
                    if ($i >= 5) break; // Show at most 5 files
                }
            }
            echo "</ul>";
        }
    }
    
    // Check for work_progress records
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'work_progress'");
    $stmt->execute();
    $workProgressTableExists = $stmt->rowCount() > 0;
    
    if ($workProgressTableExists) {
        echo "<div style='color:green'>✓ work_progress table exists</div>";
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM work_progress");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<div>Total work_progress records: {$result['count']}</div>";
        
        if ($result['count'] > 0) {
            $stmt = $pdo->prepare("SELECT id FROM work_progress ORDER BY id DESC LIMIT 5");
            $stmt->execute();
            $workIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<h3>Sample Work Progress IDs for Testing:</h3>";
            echo "<ul>";
            foreach ($workIds as $id) {
                echo "<li>$id</li>";
            }
            echo "</ul>";
        } else {
            echo "<div style='color:orange'>⚠️ No work_progress records found.</div>";
        }
    } else {
        echo "<div style='color:orange'>⚠️ work_progress table does not exist!</div>";
    }
    
    // Test direct database insert
    echo "<h2>Test Direct Database Insert</h2>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='action' value='test_insert'>";
    echo "<div><label>Work Progress ID: <input type='number' name='work_progress_id' value='1' required></label></div>";
    echo "<div><label>Media Type: <select name='media_type' required><option value='image'>Image</option><option value='video'>Video</option></select></label></div>";
    echo "<div><label>File Path: <input type='text' name='file_path' value='uploads/work_progress/test_file.jpg' required></label></div>";
    echo "<div><label>Description: <input type='text' name='description' value='Test file'></label></div>";
    echo "<div><button type='submit'>Test Insert</button></div>";
    echo "</form>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_insert') {
        $workProgressId = intval($_POST['work_progress_id']);
        $mediaType = $_POST['media_type'];
        $filePath = $_POST['file_path'];
        $description = $_POST['description'];
        
        $stmt = $pdo->prepare(
            "INSERT INTO work_progress_media 
            (work_progress_id, media_type, file_path, description, created_at) 
            VALUES (?, ?, ?, ?, current_timestamp())"
        );
        
        $result = $stmt->execute([
            $workProgressId,
            $mediaType,
            $filePath,
            $description
        ]);
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            echo "<div style='color:green'>✓ Test insert successful! New ID: $newId</div>";
        } else {
            echo "<div style='color:red'>✗ Test insert failed!</div>";
            echo "<div>Error: " . print_r($stmt->errorInfo(), true) . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color:red'>Error: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 