<?php
/**
 * Media Upload Test Script
 * 
 * Use this script to test if media uploads are working correctly
 */

// Include necessary files
require_once 'config/db_connect.php';
require_once 'includes/media_upload_handler.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$errors = [];
$messages = [];

// Process work progress media upload test
if (isset($_POST['upload_work_media'])) {
    // Create a test work progress entry
    $sql = "INSERT INTO event_work_progress (
                event_id, work_category, work_type, description, status, completion_percentage
            ) VALUES (?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['event_id'],
            'Test Category',
            'Test Type',
            'Test Description',
            'In Progress',
            50
        ]);
        
        $workId = $pdo->lastInsertId();
        $messages[] = "Created test work progress entry with ID: $workId";
        
        // Process the media upload directly with the file upload function
        if (isset($_FILES['work_media']) && !empty($_FILES['work_media']['name'])) {
            $file = $_FILES['work_media'];
            
            // Determine media type
            $fileType = $file['type'];
            $mediaType = (strpos($fileType, 'video/') === 0) ? 'video' : 'image';
            
            // Upload the file directly
            $destination = ($mediaType === 'video') ? 'work_videos' : 'work_images';
            $uploadFunction = ($mediaType === 'video') ? 'uploadVideo' : 'uploadImage';
            
            $uploadResult = $uploadFunction($file, $destination);
            
            if (isset($uploadResult['error'])) {
                $errors[] = "Failed to upload file: " . $uploadResult['error'];
            } else {
                // Insert into database
                try {
                    $sql = "INSERT INTO work_progress_media (
                                work_progress_id, media_type, file_path, description, created_at
                            ) VALUES (?, ?, ?, ?, NOW())";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $workId,
                        $mediaType,
                        $uploadResult['path'],
                        'Work progress media'
                    ]);
                    
                    $messages[] = "Media file {$file['name']} uploaded successfully";
                } catch (Exception $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                    // Clean up the uploaded file if database insert fails
                    deleteUploadedFile($uploadResult['path']);
                }
            }
        } else {
            $errors[] = "No work media file selected";
        }
    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
    }
}

// Process inventory media upload test
if (isset($_POST['upload_inventory_media'])) {
    // Create a test inventory entry
    $sql = "INSERT INTO event_inventory_items (
                event_id, inventory_type, material, quantity, units, unit_price, total_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['event_id'],
            'Test Type',
            'Test Material',
            10,
            'pcs',
            100,
            1000
        ]);
        
        $inventoryId = $pdo->lastInsertId();
        $messages[] = "Created test inventory entry with ID: $inventoryId";
        
        // Process the media upload directly with the file upload function
        if (isset($_FILES['inventory_media']) && !empty($_FILES['inventory_media']['name'])) {
            $file = $_FILES['inventory_media'];
            
            // Determine media type
            $fileType = $file['type'];
            $mediaType = (strpos($fileType, 'video/') === 0) ? 'video' : 'image';
            
            // Upload the file directly
            $destination = ($mediaType === 'video') ? 'inventory_videos' : 'inventory_images';
            $uploadFunction = ($mediaType === 'video') ? 'uploadVideo' : 'uploadImage';
            
            $uploadResult = $uploadFunction($file, $destination);
            
            if (isset($uploadResult['error'])) {
                $errors[] = "Failed to upload file: " . $uploadResult['error'];
            } else {
                // Insert into database
                try {
                    $sql = "INSERT INTO inventory_media (
                                inventory_id, media_type, file_path, description, created_at
                            ) VALUES (?, ?, ?, ?, NOW())";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $inventoryId,
                        $mediaType,
                        $uploadResult['path'],
                        'Inventory item media'
                    ]);
                    
                    $messages[] = "Media file {$file['name']} uploaded successfully";
                } catch (Exception $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                    // Clean up the uploaded file if database insert fails
                    deleteUploadedFile($uploadResult['path']);
                }
            }
        } else {
            $errors[] = "No inventory media file selected";
        }
    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
    }
}

// Get available event IDs for the dropdown
$eventQuery = "SELECT id, site_name, event_date FROM site_events ORDER BY event_date DESC LIMIT 10";
$eventStmt = $pdo->query($eventQuery);
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

// Debug file permissions
function debug_file_info() {
    $uploadBaseDir = dirname($_SERVER['DOCUMENT_ROOT']) . '/uploads/';
    $webRootUploads = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    
    $debug = [];
    $debug[] = "Current script user: " . get_current_user();
    $debug[] = "Parent directory (" . dirname($_SERVER['DOCUMENT_ROOT']) . ") writable: " . 
              (is_writable(dirname($_SERVER['DOCUMENT_ROOT'])) ? 'Yes' : 'No');
    $debug[] = "Web root (" . $_SERVER['DOCUMENT_ROOT'] . ") writable: " . 
              (is_writable($_SERVER['DOCUMENT_ROOT']) ? 'Yes' : 'No');
    
    // Check parent uploads directory
    if (file_exists($uploadBaseDir)) {
        $debug[] = "Parent uploads directory exists: Yes";
        $debug[] = "Parent uploads directory writable: " . 
                  (is_writable($uploadBaseDir) ? 'Yes' : 'No');
    } else {
        $debug[] = "Parent uploads directory exists: No";
    }
    
    // Check web root uploads directory
    if (file_exists($webRootUploads)) {
        $debug[] = "Web root uploads directory exists: Yes";
        $debug[] = "Web root uploads directory writable: " . 
                  (is_writable($webRootUploads) ? 'Yes' : 'No');
    } else {
        $debug[] = "Web root uploads directory exists: No";
    }
    
    return $debug;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Upload Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Media Upload Test</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($messages)): ?>
            <div class="alert alert-success">
                <ul class="mb-0">
                    <?php foreach ($messages as $message): ?>
                        <li><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php 
        // Display debug info
        $debugInfo = debug_file_info();
        if (!empty($debugInfo)): 
        ?>
        <div class="alert alert-info mt-3">
            <h5>Debug Information</h5>
            <ul class="mb-0">
                <?php foreach ($debugInfo as $info): ?>
                    <li><?= htmlspecialchars($info) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Test Work Progress Media Upload</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="event_id_work" class="form-label">Select Event</label>
                                <select class="form-select" id="event_id_work" name="event_id" required>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['site_name']) ?> (<?= $event['event_date'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="work_media" class="form-label">Select Work Media (Image/Video)</label>
                                <input type="file" class="form-control" id="work_media" name="work_media" required>
                            </div>
                            
                            <button type="submit" name="upload_work_media" class="btn btn-primary">Test Work Media Upload</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Test Inventory Media Upload</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="event_id_inventory" class="form-label">Select Event</label>
                                <select class="form-select" id="event_id_inventory" name="event_id" required>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['site_name']) ?> (<?= $event['event_date'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="inventory_media" class="form-label">Select Inventory Media (Image/Video)</label>
                                <input type="file" class="form-control" id="inventory_media" name="inventory_media" required>
                            </div>
                            
                            <button type="submit" name="upload_inventory_media" class="btn btn-primary">Test Inventory Media Upload</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Recent Work Progress Media</h3>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Work Progress ID</th>
                            <th>Media Type</th>
                            <th>File Path</th>
                            <th>Description</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $mediaQuery = "SELECT * FROM work_progress_media ORDER BY created_at DESC LIMIT 10";
                        $mediaStmt = $pdo->query($mediaQuery);
                        $mediaItems = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($mediaItems as $item):
                        ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td><?= $item['work_progress_id'] ?></td>
                                <td><?= $item['media_type'] ?></td>
                                <td><?= $item['file_path'] ?></td>
                                <td><?= $item['description'] ?></td>
                                <td><?= $item['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($mediaItems)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No work progress media found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Recent Inventory Media</h3>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Inventory ID</th>
                            <th>Media Type</th>
                            <th>File Path</th>
                            <th>Description</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $mediaQuery = "SELECT * FROM inventory_media ORDER BY created_at DESC LIMIT 10";
                        $mediaStmt = $pdo->query($mediaQuery);
                        $mediaItems = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($mediaItems as $item):
                        ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td><?= $item['inventory_id'] ?></td>
                                <td><?= $item['media_type'] ?></td>
                                <td><?= $item['file_path'] ?></td>
                                <td><?= $item['description'] ?></td>
                                <td><?= $item['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($mediaItems)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No inventory media found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4 mb-5">
            <a href="site_supervision.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 