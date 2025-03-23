<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$document_id = $_GET['id'] ?? null;
$document_type = $_GET['type'] ?? null;

if (!$document_id || !$document_type) {
    die('Invalid request');
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
    
    if (file_exists($file_path)) {
        // Set proper headers for file viewing
        header('Content-Type: ' . $document['file_type']);
        header('Content-Disposition: inline; filename="' . $document['original_filename'] . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        readfile($file_path);
    } else {
        die('File not found on server');
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?> 