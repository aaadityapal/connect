<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$document_id = $_GET['id'] ?? null;
$document_type = $_GET['type'] ?? null;

if (!$document_id || !$document_type) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
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
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document type']);
            exit();
    }

    $stmt->execute([$document_id]);
    $document = $stmt->fetch();

    if (!$document) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit();
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
    
    if (file_exists($file_path)) {
        // For downloads, we need to force the file to be downloaded not displayed in browser
        
        // Get file size
        $fileSize = filesize($file_path);
        
        // Set headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $fileSize);
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Output file
        readfile($file_path);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File not found on server']);
    }

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>