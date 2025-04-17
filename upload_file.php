<?php
/**
 * API endpoint to upload a file to a stage or substage
 */

session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Check if this is a POST request with a file
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or invalid request method']);
    exit();
}

// Get stage or substage ID
$stageId = isset($_POST['stage_id']) ? intval($_POST['stage_id']) : 0;
$substageId = isset($_POST['substage_id']) ? intval($_POST['substage_id']) : 0;

// Validate at least one ID is provided
if (!$stageId && !$substageId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing stage_id or substage_id parameter']);
    exit();
}

try {
    // Create uploads directory if it doesn't exist
    $uploadsDir = 'uploads';
    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    // File upload details
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    
    // Check for upload errors
    if ($fileError !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $errorMessage = isset($errorMessages[$fileError]) ? $errorMessages[$fileError] : 'Unknown upload error';
        throw new Exception($errorMessage);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $uploadFilePath = $uploadsDir . '/' . $uniqueFileName;
    
    // Move the uploaded file
    if (!move_uploaded_file($fileTmpName, $uploadFilePath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Insert file record in database
    $insertFileQuery = "INSERT INTO files (stage_id, substage_id, filename, filepath, size, uploaded_by, uploaded_at) 
                       VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $insertFileStmt = $conn->prepare($insertFileQuery);
    $stageIdParam = $stageId ?: null;
    $substageIdParam = $substageId ?: null;
    $insertFileStmt->bind_param("iissii", $stageIdParam, $substageIdParam, $fileName, $uniqueFileName, $fileSize, $userId);
    $insertFileStmt->execute();
    
    if ($insertFileStmt->affected_rows === 0) {
        // Delete file if database insert failed
        @unlink($uploadFilePath);
        throw new Exception('Failed to save file record');
    }
    
    $fileId = $insertFileStmt->insert_id;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'file_id' => $fileId,
        'file' => [
            'id' => $fileId,
            'filename' => $fileName,
            'size' => $fileSize,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?> 