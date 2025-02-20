<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Authentication required');
}

// Check if document ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid document ID');
}

$document_id = (int)$_GET['id'];

try {
    // Fetch document details
    $stmt = $pdo->prepare("SELECT * FROM hr_documents WHERE id = ?");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        die('Document not found');
    }

    // Check if user has permission to download this document
    if ($_SESSION['role'] !== 'HR' && $document['status'] !== 'published') {
        die('Access denied');
    }

    // Log the download action with current timestamp
    $logStmt = $pdo->prepare("INSERT INTO hr_documents_log (document_id, action, action_by, action_date, document_type) 
        VALUES (?, 'download', ?, NOW(), ?)");
    $logStmt->execute([$document_id, $_SESSION['user_id'], $document['type']]);

    // Set the full path to the document
    $file_path = 'uploads/hr_documents/' . $document['filename'];

    if (!file_exists($file_path)) {
        die('File not found');
    }

    // Get file mime type
    $mime_type = mime_content_type($file_path);

    // Set headers for download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output the file
    readfile($file_path);
    exit();

} catch (Exception $e) {
    error_log("Error in download_document.php: " . $e->getMessage());
    die('Error downloading document');
} 