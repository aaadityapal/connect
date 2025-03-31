<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=crm",
        "root",
        "",
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get a valid substage ID
function getValidSubstageId() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id FROM project_substages WHERE deleted_at IS NULL LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['id'];
        } else {
            throw new Exception("No valid substage found in the database");
        }
    } catch(PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
}

// Display database tables structure
function displayTableStructure() {
    global $pdo;
    
    echo "<h3>Database Structure</h3>";
    
    try {
        // Display substage_files structure
        $stmt = $pdo->query("DESCRIBE substage_files");
        echo "<h4>substage_files table structure:</h4>";
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
        
        // Display project_substages structure
        $stmt = $pdo->query("DESCRIBE project_substages");
        echo "<h4>project_substages table structure:</h4>";
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
        
        // Display sample substages
        $stmt = $pdo->query("SELECT id, title FROM project_substages WHERE deleted_at IS NULL LIMIT 5");
        echo "<h4>Available Substages:</h4>";
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
        
    } catch(PDOException $e) {
        echo "❌ Error fetching table structure: " . $e->getMessage() . "<br>";
    }
}

// Test functions
function testFileUpload() {
    global $pdo;
    
    echo "<h3>Testing File Upload...</h3>";
    
    try {
        // Get a valid substage ID first
        $substageId = getValidSubstageId();
        echo "✅ Found valid substage ID: " . $substageId . "<br>";
        
        // Create test file
        $testContent = "This is a test file content";
        $testFilePath = "test_uploads/test_file.txt";
        
        // Ensure directory exists
        if (!file_exists("test_uploads")) {
            mkdir("test_uploads", 0777, true);
        }
        
        // Create test file
        file_put_contents($testFilePath, $testContent);
        
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO substage_files (
                substage_id, 
                file_name, 
                file_path, 
                type, 
                status, 
                uploaded_by, 
                uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $substageId, // Using valid substage_id
            'test_file.txt',
            $testFilePath,
            'text/plain',
            'pending',
            1 // test user_id
        ]);
        
        $fileId = $pdo->lastInsertId();
        echo "✅ File uploaded successfully. ID: " . $fileId . "<br>";
        return $fileId;
        
    } catch(Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
        return false;
    }
}

function testFileRetrieval($fileId) {
    global $pdo;
    
    echo "<h3>Testing File Retrieval...</h3>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM substage_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            echo "✅ File found in database:<br>";
            echo "<pre>";
            print_r($file);
            echo "</pre>";
            
            // Test file existence
            if (file_exists($file['file_path'])) {
                echo "✅ File exists on disk<br>";
            } else {
                echo "❌ File not found on disk<br>";
            }
            
        } else {
            echo "❌ File not found in database<br>";
        }
        
    } catch(PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
}

function testFileViewing($fileId) {
    global $pdo;
    
    echo "<h3>Testing File Viewing...</h3>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM substage_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file && file_exists($file['file_path'])) {
            echo "View file link: <a href='file_handler.php?action=view&file_id=" . $fileId . "' target='_blank'>View File</a><br>";
            
            // Test mime type
            $mimeType = mime_content_type($file['file_path']);
            echo "File mime type: " . $mimeType . "<br>";
            
        } else {
            echo "❌ File or path not found<br>";
        }
        
    } catch(PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
}

function testFileDownload($fileId) {
    global $pdo;
    
    echo "<h3>Testing File Download...</h3>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM substage_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file && file_exists($file['file_path'])) {
            echo "Download file link: <a href='file_handler.php?action=download&file_id=" . $fileId . "'>Download File</a><br>";
            
            // Test file size
            $fileSize = filesize($file['file_path']);
            echo "File size: " . $fileSize . " bytes<br>";
            
        } else {
            echo "❌ File or path not found<br>";
        }
        
    } catch(PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
}

function testFilePermissions($fileId) {
    global $pdo;
    
    echo "<h3>Testing File Permissions...</h3>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM substage_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file && file_exists($file['file_path'])) {
            // Check file permissions
            $perms = fileperms($file['file_path']);
            $permsOctal = substr(sprintf('%o', $perms), -4);
            echo "File permissions: " . $permsOctal . "<br>";
            
            // Check if readable
            if (is_readable($file['file_path'])) {
                echo "✅ File is readable<br>";
            } else {
                echo "❌ File is not readable<br>";
            }
            
            // Check directory permissions
            $dirPerms = fileperms(dirname($file['file_path']));
            $dirPermsOctal = substr(sprintf('%o', $dirPerms), -4);
            echo "Directory permissions: " . $dirPermsOctal . "<br>";
            
        } else {
            echo "❌ File or path not found<br>";
        }
        
    } catch(PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
}

// Display database structure first
displayTableStructure();

// Run tests
echo "<h2>File Handling Test Results</h2>";

// Test 1: Upload
$fileId = testFileUpload();

if ($fileId) {
    // Test 2: Retrieval
    testFileRetrieval($fileId);
    
    // Test 3: Viewing
    testFileViewing($fileId);
    
    // Test 4: Download
    testFileDownload($fileId);
    
    // Test 5: Permissions
    testFilePermissions($fileId);
}

// Add JavaScript for testing client-side functions
?>

<script>
// Test client-side file viewing
function testClientFileViewing(fileId) {
    console.log('Testing view file:', fileId);
    viewFile(fileId);
}

// Test client-side file downloading
function testClientFileDownload(fileId) {
    console.log('Testing download file:', fileId);
    downloadFile(fileId);
}

// Add test buttons
document.addEventListener('DOMContentLoaded', function() {
    if (<?php echo $fileId ? 'true' : 'false'; ?>) {
        const testButtons = `
            <h3>Client-side Tests</h3>
            <button onclick="testClientFileViewing('<?php echo $fileId; ?>')">
                Test View Function
            </button>
            <button onclick="testClientFileDownload('<?php echo $fileId; ?>')">
                Test Download Function
            </button>
        `;
        document.body.insertAdjacentHTML('beforeend', testButtons);
    }
});
</script>

<style>
/* Basic styling for test output */
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    line-height: 1.6;
}

h2 {
    color: #333;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}

h3 {
    color: #666;
    margin-top: 20px;
}

pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
}

button {
    padding: 8px 16px;
    margin: 5px;
    cursor: pointer;
    background: #4a6cf7;
    color: white;
    border: none;
    border-radius: 4px;
}

button:hover {
    background: #3a5cdc;
}

a {
    color: #4a6cf7;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.success {
    color: #22c55e;
}

.error {
    color: #ef4444;
}
</style>

<?php
// Clean up test file
function cleanup($fileId) {
    global $pdo;
    
    echo "<h3>Cleaning Up...</h3>";
    
    try {
        // Get file path
        $stmt = $pdo->prepare("SELECT file_path FROM substage_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file && file_exists($file['file_path'])) {
            // Delete physical file
            unlink($file['file_path']);
            echo "✅ Test file deleted<br>";
        }
        
        // Delete database record
        $stmt = $pdo->prepare("DELETE FROM substage_files WHERE id = ?");
        $stmt->execute([$fileId]);
        echo "✅ Database record deleted<br>";
        
    } catch(PDOException $e) {
        echo "❌ Cleanup error: " . $e->getMessage() . "<br>";
    }
}

// Uncomment the following line to clean up after testing
// cleanup($fileId);
?> 