// includes/process_inventory_media.php
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
    require_once 'inventory_media_handler.php';
    
    // Ensure it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['inventory_media_file'])) {
        throw new Exception('No files were submitted');
    }
    
    // More detailed error checking for empty uploads
    if (empty($_FILES['inventory_media_file']['name']) || 
        (is_array($_FILES['inventory_media_file']['name']) && empty($_FILES['inventory_media_file']['name'][0]))) {
        throw new Exception('No file selected for upload');
    }
    
    // Get inventory ID and description
    $inventoryId = isset($_POST['inventory_id']) ? intval($_POST['inventory_id']) : 0;
    $descriptions = isset($_POST['inventory_media_caption']) ? $_POST['inventory_media_caption'] : [];
    
    if ($inventoryId <= 0) {
        throw new Exception('Invalid inventory ID');
    }
    
    // Initialize database connection and handler
    $pdo = isset($pdo) ? $pdo : $GLOBALS['pdo'];
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $mediaHandler = new InventoryMediaHandler($pdo);
    
    // Process each uploaded file
    $results = [];
    $files = $_FILES['inventory_media_file'];
    
    // Ensure we have a valid file structure
    if (!is_array($files['name'])) {
        // Convert to array format for consistency
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']]
        ];
    }
    
    // Handle multiple file uploads
    for ($i = 0; $i < count($files['name']); $i++) {
        // Skip if no file or error
        if (empty($files['name'][$i]) || !file_exists($files['tmp_name'][$i])) {
            continue;
        }
        
        $file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];
        
        // Skip files with errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = getUploadErrorMessage($file['error']);
            $results[] = [
                'success' => false,
                'error' => $errorMessage
            ];
            continue;
        }
        
        // Check file size (limit to 1GB)
        if ($file['size'] > 1024 * 1024 * 1024) {
            $results[] = [
                'success' => false,
                'error' => 'File size exceeds the 1GB limit'
            ];
            continue;
        }
        
        // Get the description for this file if available
        $description = isset($descriptions[$i]) ? $descriptions[$i] : '';
        
        // Save the file
        $result = $mediaHandler->saveMedia($inventoryId, $file, $description);
        $results[] = $result;
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
    error_log("Inventory Media Upload Error: " . $e->getMessage());
    
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