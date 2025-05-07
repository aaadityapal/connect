<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>MySQLi Database Test</h1>";

try {
    // Include the database connection
    require_once 'config/db_connect.php';
    
    echo "<div style='color:green'>✓ Connected to database successfully using mysqli</div>";
    
    // Check if the table exists
    $tableQuery = $conn->query("SHOW TABLES LIKE 'work_progress_media'");
    $tableExists = $tableQuery->num_rows > 0;
    
    if (!$tableExists) {
        echo "<div style='color:blue'>Creating work_progress_media table...</div>";
        
        $createTableSql = "CREATE TABLE work_progress_media (
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
        
        if ($conn->query($createTableSql)) {
            echo "<div style='color:green'>✓ Table created successfully</div>";
            $tableExists = true;
        } else {
            echo "<div style='color:red'>✗ Failed to create table: " . $conn->error . "</div>";
        }
    } else {
        echo "<div style='color:green'>✓ work_progress_media table exists</div>";
    }
    
    if ($tableExists) {
        // Show table structure
        $describeQuery = $conn->query("DESCRIBE work_progress_media");
        echo "<h2>Table Structure:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($column = $describeQuery->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check record count
        $countQuery = $conn->query("SELECT COUNT(*) as count FROM work_progress_media");
        $countRow = $countQuery->fetch_assoc();
        echo "<div>Current record count: {$countRow['count']}</div>";
    }
    
    // Add a form to test inserts
    ?>
    <h2>Test Insert Record</h2>
    <form method="post" action="">
        <div style="margin-bottom: 10px;">
            <label>Work Progress ID: </label>
            <input type="number" name="work_progress_id" value="1" required>
        </div>
        <div style="margin-bottom: 10px;">
            <label>Media Type: </label>
            <select name="media_type" required>
                <option value="image">Image</option>
                <option value="video">Video</option>
            </select>
        </div>
        <div style="margin-bottom: 10px;">
            <label>File Path: </label>
            <input type="text" name="file_path" value="uploads/work_progress/test_image.jpg" required style="width: 300px;">
        </div>
        <div style="margin-bottom: 10px;">
            <label>Description: </label>
            <input type="text" name="description" value="Test media file">
        </div>
        <button type="submit" name="insert_test">Insert Test Record</button>
    </form>
    <?php
    
    // Process form submission
    if (isset($_POST['insert_test'])) {
        $workProgressId = intval($_POST['work_progress_id']);
        $mediaType = $conn->real_escape_string($_POST['media_type']);
        $filePath = $conn->real_escape_string($_POST['file_path']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $insertSql = "INSERT INTO work_progress_media 
                     (work_progress_id, media_type, file_path, description, created_at) 
                     VALUES 
                     ($workProgressId, '$mediaType', '$filePath', '$description', NOW())";
        
        if ($conn->query($insertSql)) {
            $newId = $conn->insert_id;
            echo "<div style='color:green; margin-top: 20px;'>✓ Record inserted successfully with ID: $newId</div>";
            
            // Verify record exists
            $verifyQuery = $conn->query("SELECT * FROM work_progress_media WHERE id = $newId");
            if ($verifyQuery->num_rows > 0) {
                $record = $verifyQuery->fetch_assoc();
                echo "<h3>Inserted Record:</h3>";
                echo "<table border='1' cellpadding='5'>";
                echo "<tr>";
                foreach (array_keys($record) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr><tr>";
                foreach ($record as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr></table>";
            } else {
                echo "<div style='color:red'>✗ Unable to verify record existence after insert.</div>";
            }
        } else {
            echo "<div style='color:red; margin-top: 20px;'>✗ Error inserting record: " . $conn->error . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color:red'>Error: " . $e->getMessage() . "</div>";
}
?> 