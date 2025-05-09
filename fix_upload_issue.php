<?php
/**
 * Fix Upload Issue Script
 * This script identifies and fixes issues with file uploads not working in calendar_data_handler.php
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'includes/calendar_data_handler.php';

// Enable error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define paths for diagnostics
$basePaths = [
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'uploads_dir' => $_SERVER['DOCUMENT_ROOT'] . '/uploads',
    'materials_dir' => $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials',
    'today_dir' => $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m/d'),
    'test_dir' => $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/2025/08'
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Upload Issues</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1, h2, h3 { color: #333; }
        .section { margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 5px; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-success { background-color: #dff0d8; border-color: #d6e9c6; color: #3c763d; }
        .alert-danger { background-color: #f2dede; border-color: #ebccd1; color: #a94442; }
        .alert-info { background-color: #d9edf7; border-color: #bce8f1; color: #31708f; }
        pre { background-color: #f5f5f5; padding: 15px; border-radius: 4px; overflow: auto; }
        .code-block { background-color: #f5f5f5; padding: 15px; border-radius: 4px; font-family: monospace; overflow: auto; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .fix-btn { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .fix-btn:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>File Upload Issue Diagnostics</h1>
        
        <div class="section">
            <h2>Issue Analysis</h2>
            <p>There seems to be a discrepancy between file uploads in <code>test_upload_form.php</code> and the main <code>calendar_data_handler.php</code>.</p>
            <p>The key differences appear to be:</p>
            <ul>
                <li>How the file data is passed to the <code>processMaterialPhotos</code> function</li>
                <li>The format of image data in each context</li>
                <li>The handling of file paths and temporary files</li>
            </ul>
        </div>
        
        <div class="section">
            <h2>Directory Structure Check</h2>
            <?php foreach ($basePaths as $name => $path): ?>
                <div class="alert <?php echo file_exists($path) ? 'alert-success' : 'alert-danger'; ?>">
                    <strong><?php echo ucfirst(str_replace('_', ' ', $name)); ?>:</strong> 
                    <?php
                        if (file_exists($path)) {
                            echo "$path exists";
                            echo is_writable($path) ? ' and is writable.' : ' but is NOT writable.';
                        } else {
                            echo "$path does not exist.";
                        }
                    ?>
                </div>
            <?php endforeach; ?>
            
            <?php
            // Try to create missing directories
            foreach ($basePaths as $name => $path) {
                if (!file_exists($path)) {
                    echo '<div class="alert ' . (mkdir($path, 0755, true) ? 'alert-success' : 'alert-danger') . '">';
                    echo 'Attempted to create ' . $path . ': ' . (file_exists($path) ? 'Success' : 'Failed');
                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <div class="section">
            <h2>Database Schema Check</h2>
            <?php
            $tableResult = $conn->query("SHOW COLUMNS FROM hr_supervisor_material_photo_records");
            if ($tableResult && $tableResult->num_rows > 0) {
                echo '<table>';
                echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
                while ($column = $tableResult->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . $column['Field'] . '</td>';
                    echo '<td>' . $column['Type'] . '</td>';
                    echo '<td>' . $column['Null'] . '</td>';
                    echo '<td>' . $column['Key'] . '</td>';
                    echo '<td>' . $column['Default'] . '</td>';
                    echo '<td>' . $column['Extra'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="alert alert-danger">Could not fetch table schema.</div>';
            }
            ?>
        </div>
        
        <div class="section">
            <h2>Comparison of Upload Methods</h2>
            <h3>test_upload_form.php Approach:</h3>
            <div class="code-block">
// Handle material photo upload
$materialPhotos = [];
if (!empty($_FILES['material_photo']['name'])) {
    $materialPhotos[] = $_FILES['material_photo'];
    $materialSuccess = processMaterialPhotos($material_id, $materialPhotos, 'material');
}
            </div>
            
            <h3>calendar_data_handler.php Approach:</h3>
            <div class="code-block">
// Process material pictures if available
if ($hasMaterialPictures) {
    processMaterialPhotos($materialId, $materialData['materialPictures'], 'material');
}
            </div>
            
            <h3>Key Difference:</h3>
            <p>The test form sends an array containing <code>$_FILES</code> elements directly, while the main handler uses custom data that might not have the same structure.</p>
        </div>

        <div class="section">
            <h2>Analysis of processMaterialPhotos Function</h2>
            <p>The function tries to handle multiple formats:</p>
            <ol>
                <li>String filenames</li>
                <li>Array/object data with explicit properties</li>
                <li>$_FILES style uploads</li>
                <li>Base64 encoded images</li>
            </ol>
            
            <p>The issue is likely related to how the data is structured when it reaches the function. In the test form, it's a standard <code>$_FILES</code> array, but in the main application, it might be in a different format or missing critical properties.</p>
        </div>
        
        <div class="section">
            <h2>Solution</h2>
            <div class="alert alert-info">
                <strong>Recommendation:</strong> When using the calendar form to upload images, ensure that the image data is structured like $_FILES or has all necessary properties.
            </div>
            
            <p><strong>When uploading from a form directly:</strong></p>
            <div class="code-block">
// This works correctly:
$photos = [];
if (!empty($_FILES['photo']['name'])) {
    $photos[] = $_FILES['photo'];
    processMaterialPhotos($materialId, $photos, 'material');
}
            </div>
            
            <p><strong>When processing JSON data (like from an API):</strong></p>
            <div class="code-block">
// Ensure the format includes these properties:
$photoData = [
    'name' => 'filename.jpg',  // Required: the filename
    'tmp_name' => '/path/to/temp/file',  // Required: the temporary path
    // Optional location data
    'latitude' => 12.34,
    'longitude' => 56.78
];
            </div>
            
            <h3>Testing Upload in This Page</h3>
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_photo'])) {
                // Create a test vendor and material
                $vendorQuery = "INSERT INTO hr_supervisor_vendor_registry 
                               (event_id, vendor_name, vendor_type, vendor_contact, vendor_position) 
                               VALUES (1, 'Test Vendor from Fix Script', 'material', '1234567890', 1)";
                $conn->query($vendorQuery);
                $vendorId = $conn->insert_id;
                
                $materialQuery = "INSERT INTO hr_supervisor_material_transaction_records 
                                 (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) 
                                 VALUES ($vendorId, 'Test Material from Fix Script', 1000.00, 1, 0)";
                $conn->query($materialQuery);
                $materialId = $conn->insert_id;
                
                // Try upload using the function from calendar_data_handler.php
                $testPhotos = [$_FILES['test_photo']];
                $result = processMaterialPhotos($materialId, $testPhotos, 'material');
                
                echo '<div class="alert ' . ($result ? 'alert-success' : 'alert-danger') . '">';
                echo 'Test upload result: ' . ($result ? 'Success' : 'Failed');
                echo '</div>';
                
                if ($result) {
                    $photoQuery = "SELECT * FROM hr_supervisor_material_photo_records WHERE material_id = $materialId";
                    $photoResult = $conn->query($photoQuery);
                    if ($photoResult && $photoResult->num_rows > 0) {
                        $photo = $photoResult->fetch_assoc();
                        $photoPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $photo['photo_path'];
                        
                        echo '<div class="alert alert-info">';
                        echo '<p><strong>Saved in Database:</strong> Yes</p>';
                        echo '<p><strong>Photo ID:</strong> ' . $photo['photo_id'] . '</p>';
                        echo '<p><strong>Filename:</strong> ' . $photo['photo_filename'] . '</p>';
                        echo '<p><strong>Path in Database:</strong> ' . $photo['photo_path'] . '</p>';
                        echo '<p><strong>File Exists:</strong> ' . (file_exists($photoPath) ? 'Yes' : 'No') . '</p>';
                        echo '</div>';
                    }
                }
            }
            ?>
            
            <form action="" method="post" enctype="multipart/form-data">
                <div style="margin-bottom: 15px;">
                    <label for="test_photo" style="display: block; margin-bottom: 5px;">Upload Test Photo:</label>
                    <input type="file" name="test_photo" id="test_photo" accept="image/*" required>
                </div>
                <button type="submit" class="fix-btn">Test Upload</button>
            </form>
        </div>
    </div>
</body>
</html> 