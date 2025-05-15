<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get query parameters
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$work_id = isset($_GET['work_id']) ? intval($_GET['work_id']) : 0;
$file_name = isset($_GET['file']) ? $_GET['file'] : '';

// Function to safely output HTML
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Function to check if file exists
function check_file($path) {
    $result = [
        'path' => $path,
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'filesize' => file_exists($path) ? filesize($path) : 0,
        'filetype' => file_exists($path) ? mime_content_type($path) : 'unknown'
    ];
    
    if (file_exists($path)) {
        $imageInfo = getimagesize($path);
        $result['dimensions'] = $imageInfo ? $imageInfo[0] . 'x' . $imageInfo[1] : 'Not an image';
    }
    
    return $result;
}

// Function to check all possible paths for a file
function check_all_paths($filename) {
    $paths = [
        'uploads/work_progress/' . $filename,
        'uploads/work_images/' . $filename,
        'uploads/inventory_images/' . $filename,
        'uploads/inventory_bills/' . $filename,
        'uploads/inventory/' . $filename,
        'uploads/material_images/' . $filename,
        'uploads/bill_images/' . $filename,
    ];
    
    $results = [];
    foreach ($paths as $path) {
        $results[$path] = check_file($path);
    }
    
    return $results;
}

// Function to get work progress media for a specific work
function get_work_progress_media($conn, $work_id) {
    $media = [];
    
    $query = "SELECT * FROM sv_work_progress_media WHERE work_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return ['error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("i", $work_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($file = $result->fetch_assoc()) {
        // Check file paths
        if (!empty($file['file_name'])) {
            $file['possible_paths'] = check_all_paths($file['file_name']);
            
            // If file_path is set, check that specific path
            if (!empty($file['file_path'])) {
                $file['file_path_check'] = check_file($file['file_path']);
            }
        }
        
        $media[] = $file;
    }
    
    $stmt->close();
    return $media;
}

// Function to get all work progress items for an event
function get_work_progress($conn, $event_id) {
    $works = [];
    
    $query = "SELECT * FROM sv_work_progress WHERE event_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return ['error' => "Prepare failed: " . $conn->error];
    }
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($work = $result->fetch_assoc()) {
        $works[] = $work;
    }
    
    $stmt->close();
    return $works;
}

// Check for a specific file
if (!empty($file_name)) {
    $file_checks = check_all_paths($file_name);
}

// Get work progress media if work_id is provided
if ($work_id > 0) {
    $work_media = get_work_progress_media($conn, $work_id);
}

// Get all works for an event if event_id is provided
if ($event_id > 0) {
    $works = get_work_progress($conn, $event_id);
}

// List upload directories
$directories = [
    'uploads/work_progress/',
    'uploads/work_images/',
    'uploads/inventory_images/',
    'uploads/inventory_bills/',
    'uploads/inventory_videos/',
    'uploads/inventory/',
    'uploads/material_images/',
    'uploads/bill_images/',
];

$dir_info = [];
foreach ($directories as $dir) {
    $exists = is_dir($dir);
    $dir_info[$dir] = [
        'exists' => $exists,
        'readable' => $exists ? is_readable($dir) : false,
        'writable' => $exists ? is_writable($dir) : false,
    ];
    
    if ($exists) {
        $files = scandir($dir);
        // Remove . and ..
        $files = array_diff($files, ['.', '..']);
        // Get only first 10 files
        $sample_files = array_slice($files, 0, 10);
        $dir_info[$dir]['file_count'] = count($files);
        $dir_info[$dir]['sample_files'] = $sample_files;
    }
}

// HTML Output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Path Tester</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .file-exists { color: green; }
        .file-missing { color: red; }
        .directory-exists { color: green; }
        .directory-missing { color: red; }
        .image-preview {
            max-width: 300px;
            max-height: 300px;
            margin: 10px 0;
            border: 1px solid #ddd;
        }
        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            overflow-x: auto;
        }
        .directory-listing {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <h1>Image Path Tester</h1>
        
        <!-- Forms for testing -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Check Specific File</div>
                    <div class="card-body">
                        <form action="" method="get">
                            <div class="form-group">
                                <label for="file">Filename:</label>
                                <input type="text" class="form-control" id="file" name="file" value="<?= e($file_name) ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Check File</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Check Work Progress Media</div>
                    <div class="card-body">
                        <form action="" method="get">
                            <div class="form-group">
                                <label for="work_id">Work ID:</label>
                                <input type="number" class="form-control" id="work_id" name="work_id" value="<?= $work_id ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Check Media</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">List Works for Event</div>
                    <div class="card-body">
                        <form action="" method="get">
                            <div class="form-group">
                                <label for="event_id">Event ID:</label>
                                <input type="number" class="form-control" id="event_id" name="event_id" value="<?= $event_id ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">List Works</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Results sections -->
        <?php if (!empty($file_name) && isset($file_checks)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>File Check Results for: <?= e($file_name) ?></h3>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Path</th>
                                <th>Exists</th>
                                <th>Readable</th>
                                <th>Size</th>
                                <th>Type</th>
                                <th>Dimensions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($file_checks as $path => $check): ?>
                                <tr>
                                    <td><?= e($path) ?></td>
                                    <td>
                                        <span class="<?= $check['exists'] ? 'file-exists' : 'file-missing' ?>">
                                            <?= $check['exists'] ? 'Yes' : 'No' ?>
                                        </span>
                                    </td>
                                    <td><?= $check['readable'] ? 'Yes' : 'No' ?></td>
                                    <td><?= $check['exists'] ? number_format($check['filesize']) . ' bytes' : 'N/A' ?></td>
                                    <td><?= $check['exists'] ? $check['filetype'] : 'N/A' ?></td>
                                    <td><?= $check['exists'] ? ($check['dimensions'] ?? 'N/A') : 'N/A' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php
                    // Find first existing file to display
                    $previewFile = null;
                    foreach ($file_checks as $path => $check) {
                        if ($check['exists'] && strpos($check['filetype'], 'image/') === 0) {
                            $previewFile = $path;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($previewFile): ?>
                        <div class="mt-4">
                            <h4>Image Preview:</h4>
                            <img src="<?= e($previewFile) ?>" alt="<?= e($file_name) ?>" class="image-preview">
                        </div>
                        
                        <div class="mt-4">
                            <h4>HTML Usage Example:</h4>
                            <div class="code-block">
                                &lt;img src="<?= e($previewFile) ?>" alt="Image"&gt;
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h4>CSS Background Example:</h4>
                            <div class="code-block">
                                background-image: url('<?= e($previewFile) ?>');
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h4>JavaScript Image Viewer Example:</h4>
                            <div class="code-block">
                                openImageViewer('<?= e($previewFile) ?>');
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($work_media) && $work_id > 0): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Work Progress Media for Work ID: <?= e($work_id) ?></h3>
                </div>
                <div class="card-body">
                    <?php if (isset($work_media['error'])): ?>
                        <div class="alert alert-danger"><?= e($work_media['error']) ?></div>
                    <?php elseif (empty($work_media)): ?>
                        <div class="alert alert-info">No media found for this work ID.</div>
                    <?php else: ?>
                        <?php foreach ($work_media as $index => $media): ?>
                            <div class="card mb-3">
                                <div class="card-header">Media Item #<?= $index + 1 ?></div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>Media Details:</h5>
                                            <ul class="list-group mb-3">
                                                <li class="list-group-item"><strong>ID:</strong> <?= e($media['media_id'] ?? 'N/A') ?></li>
                                                <li class="list-group-item"><strong>Work ID:</strong> <?= e($media['work_id'] ?? 'N/A') ?></li>
                                                <li class="list-group-item"><strong>File Name:</strong> <?= e($media['file_name'] ?? 'N/A') ?></li>
                                                <li class="list-group-item"><strong>Media Type:</strong> <?= e($media['media_type'] ?? 'N/A') ?></li>
                                                <li class="list-group-item"><strong>File Path:</strong> <?= e($media['file_path'] ?? 'Not set') ?></li>
                                                <li class="list-group-item"><strong>Sequence Number:</strong> <?= e($media['sequence_number'] ?? 'N/A') ?></li>
                                            </ul>
                                            
                                            <?php if (!empty($media['file_path']) && isset($media['file_path_check'])): ?>
                                                <h5>File Path Check:</h5>
                                                <ul class="list-group mb-3">
                                                    <li class="list-group-item">
                                                        <strong>Exists:</strong> 
                                                        <span class="<?= $media['file_path_check']['exists'] ? 'file-exists' : 'file-missing' ?>">
                                                            <?= $media['file_path_check']['exists'] ? 'Yes' : 'No' ?>
                                                        </span>
                                                    </li>
                                                    <li class="list-group-item"><strong>Size:</strong> <?= $media['file_path_check']['exists'] ? number_format($media['file_path_check']['filesize']) . ' bytes' : 'N/A' ?></li>
                                                    <li class="list-group-item"><strong>Type:</strong> <?= $media['file_path_check']['exists'] ? $media['file_path_check']['filetype'] : 'N/A' ?></li>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h5>Possible Locations:</h5>
                                            <?php if (isset($media['possible_paths'])): ?>
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Path</th>
                                                            <th>Exists</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($media['possible_paths'] as $path => $check): ?>
                                                            <tr>
                                                                <td><?= e($path) ?></td>
                                                                <td>
                                                                    <span class="<?= $check['exists'] ? 'file-exists' : 'file-missing' ?>">
                                                                        <?= $check['exists'] ? 'Yes' : 'No' ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                
                                                <?php
                                                // Find working path for preview
                                                $workingPath = null;
                                                foreach ($media['possible_paths'] as $path => $check) {
                                                    if ($check['exists'] && strpos($check['filetype'], 'image/') === 0) {
                                                        $workingPath = $path;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                
                                                <?php if ($workingPath): ?>
                                                    <div class="mt-3">
                                                        <h5>Preview:</h5>
                                                        <img src="<?= e($workingPath) ?>" alt="<?= e($media['file_name'] ?? 'Media') ?>" class="image-preview">
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="alert alert-warning">No file name to check possible paths.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($works) && $event_id > 0): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Work Progress Items for Event ID: <?= e($event_id) ?></h3>
                </div>
                <div class="card-body">
                    <?php if (isset($works['error'])): ?>
                        <div class="alert alert-danger"><?= e($works['error']) ?></div>
                    <?php elseif (empty($works)): ?>
                        <div class="alert alert-info">No work progress items found for this event.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Work ID</th>
                                        <th>Work Category</th>
                                        <th>Work Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($works as $work): ?>
                                        <tr>
                                            <td><?= e($work['work_id']) ?></td>
                                            <td><?= e($work['work_category']) ?></td>
                                            <td><?= e($work['work_type']) ?></td>
                                            <td>
                                                <?php if ($work['work_done'] === 'yes'): ?>
                                                    <span class="badge badge-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Not Completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?work_id=<?= $work['work_id'] ?>" class="btn btn-sm btn-info">
                                                    View Media
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h3>Directory Information</h3>
            </div>
            <div class="card-body">
                <?php foreach ($dir_info as $dir => $info): ?>
                    <div class="mb-3">
                        <h5>
                            <span class="<?= $info['exists'] ? 'directory-exists' : 'directory-missing' ?>">
                                <?= $info['exists'] ? '<i class="fas fa-folder-open"></i>' : '<i class="fas fa-folder-minus"></i>' ?>
                            </span>
                            <?= e($dir) ?>
                        </h5>
                        
                        <?php if ($info['exists']): ?>
                            <ul class="list-group mb-2">
                                <li class="list-group-item">
                                    <strong>Readable:</strong> <?= $info['readable'] ? 'Yes' : 'No' ?>
                                </li>
                                <li class="list-group-item">
                                    <strong>Writable:</strong> <?= $info['writable'] ? 'Yes' : 'No' ?>
                                </li>
                                <li class="list-group-item">
                                    <strong>File Count:</strong> <?= $info['file_count'] ?>
                                </li>
                            </ul>
                            
                            <?php if (!empty($info['sample_files'])): ?>
                                <div class="directory-listing">
                                    <strong>Sample Files:</strong><br>
                                    <?php foreach ($info['sample_files'] as $file): ?>
                                        <a href="?file=<?= urlencode($file) ?>"><?= e($file) ?></a><br>
                                    <?php endforeach; ?>
                                    <?php if ($info['file_count'] > count($info['sample_files'])): ?>
                                        <em>... and <?= $info['file_count'] - count($info['sample_files']) ?> more</em>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">Directory does not exist</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 