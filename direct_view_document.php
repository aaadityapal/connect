<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

$document_id = $_GET['id'] ?? null;
$document_type = $_GET['type'] ?? null;

if (!$document_id || !$document_type) {
    die('Invalid request parameters');
}

try {
    // Different queries based on document type
    switch ($document_type) {
        case 'policy':
            $stmt = $pdo->prepare("
                SELECT stored_filename, file_type, original_filename
                FROM policy_documents 
                WHERE id = ?
            ");
            break;
        case 'official':
            $stmt = $pdo->prepare("
                SELECT stored_filename, file_type, original_filename
                FROM official_documents 
                WHERE id = ?
            ");
            break;
        case 'personal':
            $stmt = $pdo->prepare("
                SELECT stored_filename, file_type, original_filename
                FROM personal_documents 
                WHERE id = ?
            ");
            break;
        default:
            die('Invalid document type');
    }

    $stmt->execute([$document_id]);
    $document = $stmt->fetch();

    if (!$document) {
        die('Document not found');
    }

    // Determine the file path based on document type
    $base_path = 'uploads/documents/';
    switch ($document_type) {
        case 'policy':
            $file_path = $base_path . 'policy/' . $document['stored_filename'];
            break;
        case 'official':
            $file_path = $base_path . 'official/' . $document['stored_filename'];
            break;
        case 'personal':
            $file_path = $base_path . 'personal/' . $document['stored_filename'];
            break;
    }
    
    if (!file_exists($file_path)) {
        die('File not found on server');
    }

    // Get file information
    $fileInfo = pathinfo($file_path);
    $extension = strtolower($fileInfo['extension'] ?? '');

    // Set appropriate content type based on file extension
    switch ($extension) {
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
            $content_type = 'application/msword';
            break;
        case 'docx':
            $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            break;
        case 'xls':
            $content_type = 'application/vnd.ms-excel';
            break;
        case 'xlsx':
            $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            break;
        case 'txt':
            $content_type = 'text/plain';
            break;
        default:
            $content_type = 'application/octet-stream';
    }

    // Set headers for file display
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $document['original_filename'] . '"');
    header('Cache-Control: public, max-age=0');
    
    // Output the file content
    readfile($file_path);
    exit;

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>