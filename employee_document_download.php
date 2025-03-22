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
        $query = "SELECT stored_filename, file_type, document_name, original_filename 
                 FROM official_documents 
                 WHERE id = ? AND assigned_user_id = ? AND is_deleted = 0";
    } else if ($document_type === 'personal') {
        $query = "SELECT stored_filename, file_type, document_name, original_filename 
                 FROM personal_documents 
                 WHERE id = ? AND assigned_user_id = ? AND is_deleted = 0";
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

    // Check if file exists
    if (!file_exists($file_path)) {
        throw new Exception('File not found on server');
    }

    // Get file mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);

    // Set headers for download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($file_path);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 