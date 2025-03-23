<?php
session_start();
require_once 'config.php';

// Simulate user session if testing without login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Set a test user ID
}

// Configuration check
echo "<h2>Configuration Check</h2>";

// 1. Check Upload Directory
$uploadDir = 'uploads/hr_documents/'; // Updated path to match get_hr_documents.php
echo "<h3>1. Upload Directory Check:</h3>";

// Create directory structure if it doesn't exist
$directories = ['uploads', 'uploads/hr_documents'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        echo "❌ Directory does not exist at: " . realpath($dir) . "<br>";
        echo "Creating directory: $dir...<br>";
        if (mkdir($dir, 0755, true)) {
            echo "✅ Directory '$dir' created successfully<br>";
        } else {
            echo "❌ Failed to create directory '$dir'<br>";
        }
    } else {
        echo "✅ Directory '$dir' exists<br>";
        echo "Path: " . realpath($dir) . "<br>";
        echo "Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "<br>";
    }
}

// 2. Database Connection Check
echo "<h3>2. Database Connection Check:</h3>";
try {
    $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    echo "✅ Database connection successful<br>";
    
    // Test database query
    $sql = "SHOW TABLES LIKE 'hr_documents'";
    $result = $pdo->query($sql);
    
    if ($result->rowCount() > 0) {
        echo "✅ hr_documents table exists<br>";
        
        // Check table structure
        $sql = "DESCRIBE hr_documents";
        $columns = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        echo "Table columns: " . implode(", ", $columns) . "<br>";
    } else {
        echo "❌ hr_documents table not found<br>";
        
        // Create table if not exists
        echo "Creating hr_documents table...<br>";
        $createTable = "CREATE TABLE IF NOT EXISTS hr_documents (
            id INT PRIMARY KEY AUTO_INCREMENT,
            type VARCHAR(100) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            last_modified DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'published',
            uploaded_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )";
        
        if ($pdo->exec($createTable)) {
            echo "✅ Table created successfully<br>";
        } else {
            echo "❌ Failed to create table<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// 3. Document Records Check
echo "<h3>3. Document Records Check:</h3>";
try {
    $sql = "SELECT COUNT(*) FROM hr_documents WHERE status = 'published'";
    $count = $pdo->query($sql)->fetchColumn();
    echo "Total published documents: $count<br>";
    
    if ($count == 0) {
        echo "⚠️ No documents found in database<br>";
        
        // Insert test document if none exist
        echo "Inserting test document...<br>";
        $testFile = "test_document.pdf";
        $testFilePath = $uploadDir . $testFile;
        
        // Create sample PDF
        if (!file_exists($testFilePath)) {
            $pdf = "Test PDF content";
            file_put_contents($testFilePath, $pdf);
        }
        
        $sql = "INSERT INTO hr_documents (type, filename, original_name, file_size, file_type, uploaded_by) 
                VALUES ('Hr Policy', ?, ?, ?, 'application/pdf', ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$testFile, "Test Document.pdf", filesize($testFilePath), $_SESSION['user_id']])) {
            echo "✅ Test document inserted successfully<br>";
        } else {
            echo "❌ Failed to insert test document<br>";
        }
    }
    
    // Display existing documents
    echo "<h4>Existing Documents:</h4>";
    $sql = "SELECT * FROM hr_documents WHERE status = 'published'";
    $documents = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Filename</th><th>Type</th><th>Status</th><th>File Exists</th><th>File Path</th></tr>";
    foreach ($documents as $doc) {
        $filePath = $uploadDir . $doc['filename'];
        $fileExists = file_exists($filePath) ? "✅" : "❌";
        echo "<tr>";
        echo "<td>{$doc['id']}</td>";
        echo "<td>{$doc['filename']}</td>";
        echo "<td>{$doc['type']}</td>";
        echo "<td>{$doc['status']}</td>";
        echo "<td>$fileExists</td>";
        echo "<td>" . realpath($filePath) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "❌ Error checking documents: " . $e->getMessage() . "<br>";
}

// 4. File System Check
echo "<h3>4. File System Check:</h3>";
$files = scandir($uploadDir);
echo "Files in upload directory ($uploadDir):<br>";
echo "<ul>";
foreach ($files as $file) {
    if ($file != "." && $file != "..") {
        $filePath = $uploadDir . $file;
        echo "<li>$file";
        echo " (Size: " . filesize($filePath) . " bytes)";
        echo " (Permissions: " . substr(sprintf('%o', fileperms($filePath)), -4) . ")";
        echo " (Full path: " . realpath($filePath) . ")";
        echo "</li>";
    }
}
echo "</ul>";

// 5. Test Document Access
echo "<h3>5. Test Document Access:</h3>";
if (!empty($documents)) {
    $testDoc = $documents[0];
    $testPath = $uploadDir . $testDoc['filename'];
    echo "Testing access to: " . $testDoc['filename'] . "<br>";
    echo "Full path: " . realpath($testPath) . "<br>";
    
    if (file_exists($testPath)) {
        echo "✅ File exists<br>";
        if (is_readable($testPath)) {
            echo "✅ File is readable<br>";
            $contents = file_get_contents($testPath);
            if ($contents !== false) {
                echo "✅ File contents can be read<br>";
                echo "File size: " . strlen($contents) . " bytes<br>";
            } else {
                echo "❌ Cannot read file contents<br>";
                echo "Error: " . error_get_last()['message'] . "<br>";
            }
        } else {
            echo "❌ File is not readable<br>";
            echo "Current PHP user: " . get_current_user() . "<br>";
            echo "File owner: " . fileowner($testPath) . "<br>";
        }
    } else {
        echo "❌ File does not exist at path: $testPath<br>";
    }
}

// 6. Directory Permissions Check
echo "<h3>6. Directory Permissions Check:</h3>";
echo "PHP process user: " . get_current_user() . "<br>";
echo "Upload directory owner: " . fileowner($uploadDir) . "<br>";
echo "Upload directory group: " . filegroup($uploadDir) . "<br>";
echo "Upload directory permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br>";

// 7. PHP Configuration
echo "<h3>7. PHP Configuration:</h3>";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post max size: " . ini_get('post_max_size') . "<br>";
echo "Memory limit: " . ini_get('memory_limit') . "<br>";
echo "Open basedir: " . ini_get('open_basedir') . "<br>";
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2, h3, h4 { color: #333; }
    table { border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; }
    .success { color: green; }
    .error { color: red; }
</style> 