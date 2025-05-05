<?php
// Set proper content type header for JSON response
header('Content-Type: application/json');

// Increase upload limits
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_input_time', 600);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M');

try {
    require_once '../config/db_connect.php';
    require_once 'work_progress_media_handler.php';
    
    // Ensure it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['work_media_file'])) {
        throw new Exception('No files were submitted');
    }
    
    // More detailed error checking for empty uploads
    if (empty($_FILES['work_media_file']['name']) || 
        (is_array($_FILES['work_media_file']['name']) && empty($_FILES['work_media_file']['name'][0]))) {
        throw new Exception('No file selected for upload');
    }
    
    // Get work progress ID and description
    $workProgressId = isset($_POST['work_progress_id']) ? intval($_POST['work_progress_id']) : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    if ($workProgressId <= 0) {
        throw new Exception('Invalid work progress ID');
    }
    
    // Initialize database connection and handler
    $pdo = isset($pdo) ? $pdo : $GLOBALS['pdo'];
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $mediaHandler = new WorkProgressMediaHandler($pdo);
    
    // Process each uploaded file
    $results = [];
    
    // Check if multiple files were uploaded
    if (is_array($_FILES['work_media_file']['tmp_name'])) {
        for ($i = 0; $i < count($_FILES['work_media_file']['tmp_name']); $i++) {
            // Skip if no file or error
            if (empty($_FILES['work_media_file']['name'][$i]) || !file_exists($_FILES['work_media_file']['tmp_name'][$i])) {
                continue;
            }
            
            if ($_FILES['work_media_file']['error'][$i] === UPLOAD_ERR_OK) {
                $fileData = [
                    'name' => $_FILES['work_media_file']['name'][$i],
                    'type' => $_FILES['work_media_file']['type'][$i],
                    'tmp_name' => $_FILES['work_media_file']['tmp_name'][$i],
                    'error' => $_FILES['work_media_file']['error'][$i],
                    'size' => $_FILES['work_media_file']['size'][$i]
                ];
                
                // Check file size (limit to 1GB)
                if ($fileData['size'] > 1024 * 1024 * 1024) {
                    $results[] = [
                        'success' => false,
                        'error' => 'File size exceeds the 1GB limit'
                    ];
                    continue;
                }
                
                $result = $mediaHandler->saveMedia($workProgressId, $fileData, $description);
                $results[] = $result;
            } else {
                $errorMessage = getUploadErrorMessage($_FILES['work_media_file']['error'][$i]);
                $results[] = [
                    'success' => false,
                    'error' => $errorMessage
                ];
            }
        }
    } else {
        // Single file upload
        if ($_FILES['work_media_file']['error'] === UPLOAD_ERR_OK) {
            $fileData = [
                'name' => $_FILES['work_media_file']['name'],
                'type' => $_FILES['work_media_file']['type'],
                'tmp_name' => $_FILES['work_media_file']['tmp_name'],
                'error' => $_FILES['work_media_file']['error'],
                'size' => $_FILES['work_media_file']['size']
            ];
            
            // Check file size (limit to 1GB)
            if ($fileData['size'] > 1024 * 1024 * 1024) {
                $results[] = [
                    'success' => false,
                    'error' => 'File size exceeds the 1GB limit'
                ];
            } else {
                $result = $mediaHandler->saveMedia($workProgressId, $fileData, $description);
                $results[] = $result;
            }
        } else {
            $errorMessage = getUploadErrorMessage($_FILES['work_media_file']['error']);
            $results[] = [
                'success' => false,
                'error' => $errorMessage
            ];
        }
    }
    
    // Check if any uploads were successful
    $successCount = 0;
    foreach ($results as $result) {
        if (isset($result['success']) && $result['success']) $successCount++;
    }
    
    if ($successCount > 0) {
        echo json_encode([
            'success' => true,
            'message' => "$successCount file(s) uploaded successfully",
            'results' => $results
        ]);
    } else {
        throw new Exception('No files were uploaded successfully');
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Work Media Upload Error: " . $e->getMessage());
    
    // Return JSON error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
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