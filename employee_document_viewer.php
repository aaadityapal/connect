<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if document ID and type are provided
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing document ID or type']);
    exit;
}

$document_id = $_GET['id'];
$document_type = $_GET['type'];
$user_id = $_SESSION['user_id'];

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Prepare query based on document type
    if ($document_type === 'official') {
        $query = "SELECT od.*, u.username as uploader_name 
                 FROM official_documents od
                 LEFT JOIN users u ON od.uploaded_by = u.id
                 WHERE od.id = ? AND od.assigned_user_id = ? AND od.is_deleted = 0";
    } else if ($document_type === 'personal') {
        $query = "SELECT pd.*, u.username as uploader_name 
                 FROM personal_documents pd
                 LEFT JOIN users u ON pd.uploaded_by = u.id
                 WHERE pd.id = ? AND pd.assigned_user_id = ? AND pd.is_deleted = 0";
    } else {
        throw new Exception('Invalid document type');
    }

    $stmt = $db->prepare($query);
    $stmt->bind_param('ii', $document_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Document not found or access denied');
    }

    $document = $result->fetch_assoc();
    
    // Update the upload directory paths to match your structure
    $upload_dir = $document_type === 'official' 
        ? 'uploads/documents/official/' 
        : 'uploads/documents/personal/';
    
    $file_path = $upload_dir . $document['stored_filename'];
    
    // Log the file path and check directory existence
    error_log("Attempting to access file: " . $file_path);
    error_log("Upload directory exists: " . (is_dir($upload_dir) ? 'Yes' : 'No'));
    error_log("File exists: " . (file_exists($file_path) ? 'Yes' : 'No'));
    error_log("File permissions: " . decoct(fileperms($file_path)));
    
    // Check if file exists with absolute path
    $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $file_path;
    error_log("Absolute path: " . $absolute_path);
    error_log("File exists (absolute): " . (file_exists($absolute_path) ? 'Yes' : 'No'));

    // Check if file exists
    if (!file_exists($file_path)) {
        throw new Exception('File not found on server');
    }

    // Set appropriate content type
    $content_type = '';
    switch(strtolower(pathinfo($document['stored_filename'], PATHINFO_EXTENSION))) {
        case 'pdf':
            $content_type = 'application/pdf';
            break;
        case 'jpg':
        case 'jpeg':
            $content_type = 'image/jpeg';
            break;
        case 'png':
            $content_type = 'image/png';
            break;
        case 'doc':
        case 'docx':
            $content_type = 'application/msword';
            break;
        default:
            $content_type = 'application/octet-stream';
    }

    // Output the file
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $document['document_name'] . '"');
    header('Cache-Control: public, max-age=0');
    
    if (!readfile($file_path)) {
        throw new Exception('Failed to read file');
    }
    exit;

} catch (Exception $e) {
    error_log("Document viewer error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'document_id' => $document_id,
            'document_type' => $document_type,
            'user_id' => $user_id
        ]
    ]);
} 