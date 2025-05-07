<?php
// Set proper content type header for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Increase upload limits (these settings only work if PHP is not in safe mode)
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_input_time', 600);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M');

try {
    // Include necessary files
    require_once '../config/db_connect.php';
    require_once 'work_progress_media_handler.php';
    
    // Debug logging
    error_log("Work Media Upload - Request started");
    error_log("POST data: " . json_encode($_POST));
    error_log("FILES data: " . json_encode($_FILES));
    
    // Ensure it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['work_media_file'])) {
        throw new Exception('No files were submitted');
    }
    
    // Get work progress ID and description
    $workProgressId = isset($_POST['work_progress_id']) ? intval($_POST['work_progress_id']) : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    if ($workProgressId <= 0) {
        throw new Exception('Invalid work progress ID: ' . $workProgressId);
    }
    
    // Check for upload directory
    $uploadDir = dirname(dirname(__FILE__)) . '/uploads/work_progress/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception("Failed to create upload directory: $uploadDir");
        }
        error_log("Created upload directory: $uploadDir");
    } else {
        // Check permissions
        if (!is_writable($uploadDir)) {
            throw new Exception("Upload directory is not writable: $uploadDir");
        }
    }
    
    // Initialize database connection
    if (!isset($pdo)) {
        throw new Exception('Database connection failed');
    }
    
    // Create media handler
    $mediaHandler = new WorkProgressMediaHandler($pdo);
    
    // Process each uploaded file
    $results = [];
    $successCount = 0;
    
    // Normalize the files array for both single and multiple file uploads
    $files = [];
    
    // Check if it's a single file or multiple files
    if (is_array($_FILES['work_media_file']['name'])) {
        // Multiple files
        error_log("Processing multiple files array");
        $fileCount = count($_FILES['work_media_file']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if (!empty($_FILES['work_media_file']['name'][$i])) {
                $files[] = [
                    'name' => $_FILES['work_media_file']['name'][$i],
                    'type' => $_FILES['work_media_file']['type'][$i],
                    'tmp_name' => $_FILES['work_media_file']['tmp_name'][$i],
                    'error' => $_FILES['work_media_file']['error'][$i],
                    'size' => $_FILES['work_media_file']['size'][$i]
                ];
            }
        }
    } else {
        // Single file
        error_log("Processing single file");
        if (!empty($_FILES['work_media_file']['name'])) {
            $files[] = [
                'name' => $_FILES['work_media_file']['name'],
                'type' => $_FILES['work_media_file']['type'],
                'tmp_name' => $_FILES['work_media_file']['tmp_name'],
                'error' => $_FILES['work_media_file']['error'],
                'size' => $_FILES['work_media_file']['size']
            ];
        }
    }
    
    // If no valid files found
    if (empty($files)) {
        throw new Exception('No valid files were submitted for upload');
    }
    
    // Process each file
    foreach ($files as $index => $file) {
        error_log("Processing file {$index}: " . $file['name']);
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = getUploadErrorMessage($file['error']);
            error_log("Upload error for file {$file['name']}: $errorMessage");
            $results[] = [
                'success' => false,
                'error' => $errorMessage,
                'file_name' => $file['name']
            ];
            continue;
        }
        
        // Check if the temp file exists
        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            error_log("Temp file missing for {$file['name']}");
            $results[] = [
                'success' => false,
                'error' => 'Temporary file missing, upload may be too large for server',
                'file_name' => $file['name']
            ];
            continue;
        }
        
        // Save the file
        try {
            $result = $mediaHandler->saveMedia($workProgressId, $file, $description);
            error_log("Save result for file {$file['name']}: " . json_encode($result));
            
            $results[] = $result;
            if ($result['success']) {
                $successCount++;
            }
        } catch (Exception $e) {
            error_log("Error saving file {$file['name']}: " . $e->getMessage());
            $results[] = [
                'success' => false,
                'error' => $e->getMessage(),
                'file_name' => $file['name']
            ];
        }
    }
    
    // Check if any uploads were successful
    if ($successCount > 0) {
        error_log("Upload process completed successfully with $successCount files");
        echo json_encode([
            'success' => true,
            'message' => "$successCount file(s) uploaded successfully",
            'results' => $results
        ]);
    } else {
        error_log("No files were uploaded successfully. Results: " . json_encode($results));
        throw new Exception('No files were uploaded successfully');
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Work Media Upload Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return JSON error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_execution_time' => ini_get('max_execution_time')
        ]
    ]);
}

// Helper function to translate PHP upload error codes to readable messages
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}

exit; 