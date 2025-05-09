<?php
/**
 * Material and Bill Photo Upload Test Form
 * This page provides a form to test uploading material and bill photos and verifies they are saved correctly
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set a fake user ID for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Test User';
}

// Include necessary files
require_once 'includes/calendar_data_handler.php';

// Process form submission
$uploadResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create test vendor and material if not provided
    $vendor_id = $_POST['vendor_id'] ?? null;
    $material_id = $_POST['material_id'] ?? null;
    
    if (empty($vendor_id)) {
        // Create a test vendor
        $vendor_query = "
            INSERT INTO hr_supervisor_vendor_registry 
            (event_id, vendor_name, vendor_type, vendor_contact, vendor_position) 
            VALUES (1, 'Test Vendor for File Upload', 'material', '1234567890', 1)
        ";
        
        $conn->query($vendor_query);
        $vendor_id = $conn->insert_id;
    }
    
    if (empty($material_id)) {
        // Create a test material record
        $material_query = "
            INSERT INTO hr_supervisor_material_transaction_records 
            (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) 
            VALUES ($vendor_id, 'Test Material for File Upload', 1000.00, 1, 1)
        ";
        
        $conn->query($material_query);
        $material_id = $conn->insert_id;
    }
    
    // Handle material photo upload
    $materialPhotos = [];
    if (!empty($_FILES['material_photo']['name'])) {
        $materialPhotos[] = $_FILES['material_photo'];
        $materialSuccess = processMaterialPhotos($material_id, $materialPhotos, 'material');
    }
    
    // Handle bill photo upload
    $billPhotos = [];
    if (!empty($_FILES['bill_photo']['name'])) {
        $billPhotos[] = $_FILES['bill_photo'];
        $billSuccess = processMaterialPhotos($material_id, $billPhotos, 'bill');
    }
    
    // Prepare result
    $uploadResult = [
        'vendor_id' => $vendor_id,
        'material_id' => $material_id,
        'material_upload' => !empty($materialPhotos) ? ($materialSuccess ? 'success' : 'failed') : 'not attempted',
        'bill_upload' => !empty($billPhotos) ? ($billSuccess ? 'success' : 'failed') : 'not attempted'
    ];
    
    // Check database for uploaded files
    $uploadedFiles = [];
    $fileQuery = "SELECT photo_id, photo_type, photo_filename, photo_path FROM hr_supervisor_material_photo_records WHERE material_id = $material_id";
    $fileResult = $conn->query($fileQuery);
    
    while ($file = $fileResult->fetch_assoc()) {
        $uploadedFiles[] = $file;
    }
    
    $uploadResult['uploaded_files'] = $uploadedFiles;
}

// Log errors to browser
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Material and Bill Photo Upload Test Form</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .success { color: green; background-color: #e0f0e0; padding: 8px; border-radius: 4px; }
        .error { color: red; background-color: #f0e0e0; padding: 8px; border-radius: 4px; }
        .info { color: blue; background-color: #e0e0f0; padding: 8px; border-radius: 4px; }
        .form-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="file"] { padding: 5px; border: 1px solid #ddd; width: 100%; }
        button { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        .file-check { margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Material and Bill Photo Upload Test Form</h1>
        
        <?php if ($uploadResult): ?>
            <div class="form-section">
                <h2>Upload Results</h2>
                
                <div class="info">
                    <p><strong>Vendor ID:</strong> <?php echo $uploadResult['vendor_id']; ?></p>
                    <p><strong>Material ID:</strong> <?php echo $uploadResult['material_id']; ?></p>
                </div>
                
                <div class="<?php echo $uploadResult['material_upload'] === 'success' ? 'success' : 'error'; ?>">
                    <p><strong>Material Photo Upload:</strong> <?php echo $uploadResult['material_upload']; ?></p>
                </div>
                
                <div class="<?php echo $uploadResult['bill_upload'] === 'success' ? 'success' : 'error'; ?>">
                    <p><strong>Bill Photo Upload:</strong> <?php echo $uploadResult['bill_upload']; ?></p>
                </div>
                
                <h3>Uploaded Files in Database</h3>
                <?php if (!empty($uploadResult['uploaded_files'])): ?>
                    <table>
                        <tr>
                            <th>Photo ID</th>
                            <th>Type</th>
                            <th>Filename</th>
                            <th>Path</th>
                            <th>File Exists</th>
                        </tr>
                        <?php foreach ($uploadResult['uploaded_files'] as $file): ?>
                            <tr>
                                <td><?php echo $file['photo_id']; ?></td>
                                <td><?php echo $file['photo_type']; ?></td>
                                <td><?php echo $file['photo_filename']; ?></td>
                                <td><?php echo $file['photo_path']; ?></td>
                                <td><?php 
                                    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $file['photo_path'];
                                    $exists = file_exists($filePath);
                                    if (!$exists) {
                                        // Try to extract the path from the database path
                                        $pathParts = explode('/', $file['photo_path']);
                                        $filename = end($pathParts);
                                        // Check in the actual directory shown in screenshot (2025/08)
                                        $alternativePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/2025/08/' . $filename;
                                        $exists = file_exists($alternativePath);
                                        if ($exists) {
                                            $filePath = $alternativePath;
                                        }
                                    }
                                    
                                    echo $exists ? 
                                        '<span class="success">Yes - Found at: ' . $filePath . '</span>' : 
                                        '<span class="error">No</span>';
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <div class="file-check">
                        <h3>File System Check</h3>
                        <?php
                        // Check all possible upload directories
                        $uploadDirs = [
                            $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m/d/'),
                            $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/2025/08/',
                            $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m') . '/'
                        ];
                        
                        $foundFiles = false;
                        
                        foreach ($uploadDirs as $uploadsDir):
                            if (file_exists($uploadsDir)):
                                $files = glob($uploadsDir . '*');
                                if (count($files) > 0):
                                    $foundFiles = true;
                        ?>
                            <div class="success">
                                <p>Directory exists with files: <?php echo $uploadsDir; ?></p>
                                <p>Files in directory:</p>
                                <ul>
                                    <?php foreach ($files as $file): ?>
                                        <li><?php echo basename($file); ?> (<?php echo filesize($file); ?> bytes)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php 
                                endif;
                            endif;
                        endforeach;
                        
                        if (!$foundFiles):
                        ?>
                            <div class="info">
                                <p>Checking all directories in /uploads/materials/:</p>
                                <ul>
                                <?php
                                // List all year directories
                                $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/';
                                if (file_exists($baseDir)) {
                                    $years = glob($baseDir . '*', GLOB_ONLYDIR);
                                    foreach ($years as $year) {
                                        echo '<li>Found year directory: ' . basename($year) . '</li>';
                                        
                                        // List all month directories in this year
                                        $months = glob($year . '/*', GLOB_ONLYDIR);
                                        foreach ($months as $month) {
                                            echo '<li style="margin-left: 20px;">Found month directory: ' . basename($year) . '/' . basename($month) . '</li>';
                                            
                                            // List files in this month directory
                                            $monthFiles = glob($month . '/*');
                                            if (count($monthFiles) > 0) {
                                                echo '<li style="margin-left: 40px;">Files found:</li>';
                                                echo '<ul style="margin-left: 60px;">';
                                                foreach ($monthFiles as $file) {
                                                    if (is_file($file)) {
                                                        echo '<li>' . basename($file) . ' (' . filesize($file) . ' bytes)</li>';
                                                    } else if (is_dir($file)) {
                                                        echo '<li>Directory: ' . basename($file) . '</li>';
                                                        // Check for files in day directories
                                                        $dayFiles = glob($file . '/*');
                                                        if (count($dayFiles) > 0) {
                                                            echo '<ul style="margin-left: 20px;">';
                                                            foreach ($dayFiles as $dayFile) {
                                                                echo '<li>' . basename($dayFile) . ' (' . filesize($dayFile) . ' bytes)</li>';
                                                            }
                                                            echo '</ul>';
                                                        }
                                                    }
                                                }
                                                echo '</ul>';
                                            }
                                        }
                                    }
                                } else {
                                    echo '<li>Base uploads/materials directory not found</li>';
                                }
                                ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($uploadResult['material_id'])): ?>
                        <p><a href="view_photos.php?material_id=<?php echo $uploadResult['material_id']; ?>" target="_blank">View in Photo Viewer</a></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="error">No files were uploaded to the database.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <h2>Upload Test Form</h2>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="material_photo">Material Photo:</label>
                    <input type="file" name="material_photo" id="material_photo" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="bill_photo">Bill Photo:</label>
                    <input type="file" name="bill_photo" id="bill_photo" accept="image/*">
                </div>
                
                <?php if (!empty($uploadResult)): ?>
                    <input type="hidden" name="vendor_id" value="<?php echo $uploadResult['vendor_id']; ?>">
                    <input type="hidden" name="material_id" value="<?php echo $uploadResult['material_id']; ?>">
                <?php endif; ?>
                
                <button type="submit">Upload Files</button>
            </form>
        </div>
        
        <div class="form-section">
            <h2>Technical Information</h2>
            <div class="info">
                <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
                <p><strong>Expected Upload Path (today):</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/uploads/materials/<?php echo date('Y/m/d/'); ?></p>
                <p><strong>Actual Upload Path (seen in screenshot):</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/uploads/materials/2025/08/</p>
                <p><strong>PHP Upload Max Filesize:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                <p><strong>PHP Post Max Size:</strong> <?php echo ini_get('post_max_size'); ?></p>
                
                <h3>Database Records</h3>
                <?php
                // Show recent uploads from the database
                $recentQuery = "SELECT photo_id, photo_type, photo_filename, photo_path, created_at 
                                FROM hr_supervisor_material_photo_records 
                                ORDER BY photo_id DESC LIMIT 10";
                $recentResult = $conn->query($recentQuery);
                
                if ($recentResult && $recentResult->num_rows > 0):
                ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Filename</th>
                        <th>Database Path</th>
                        <th>Created</th>
                    </tr>
                    <?php while ($row = $recentResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['photo_id']; ?></td>
                        <td><?php echo $row['photo_type']; ?></td>
                        <td><?php echo $row['photo_filename']; ?></td>
                        <td><?php echo $row['photo_path']; ?></td>
                        <td><?php echo $row['created_at'] ?? 'N/A'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
                <?php else: ?>
                <p>No recent upload records found in database.</p>
                <?php endif; ?>
                
                <h3>File Upload Directory Status</h3>
                <?php
                $baseDirs = [
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads',
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials',
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y'),
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m'),
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m/d'),
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/2025',
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/2025/08'
                ];
                
                foreach ($baseDirs as $dir):
                    $exists = file_exists($dir);
                    $writable = is_writable($dir);
                ?>
                    <div class="<?php echo $exists ? ($writable ? 'success' : 'error') : 'error'; ?>">
                        <p>
                            <strong><?php echo $dir; ?></strong>: 
                            <?php 
                                echo $exists ? 'Exists' : 'Does not exist';
                                echo $exists ? ($writable ? ' (Writable)' : ' (Not writable)') : '';
                                
                                if (!$exists) {
                                    echo ' - ';
                                    if (mkdir($dir, 0755, true)) {
                                        echo 'Created successfully';
                                    } else {
                                        echo 'Failed to create: ' . error_get_last()['message'];
                                    }
                                }
                            ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html> 