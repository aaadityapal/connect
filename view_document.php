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

    // Check if user has permission to view this document
    if ($_SESSION['role'] !== 'HR' && $document['status'] !== 'published') {
        die('Access denied');
    }

    // Log the view action with current timestamp
    $logStmt = $pdo->prepare("INSERT INTO hr_documents_log (document_id, action, action_by, action_date, document_type) 
        VALUES (?, 'view', ?, NOW(), ?)");
    $logStmt->execute([$document_id, $_SESSION['user_id'], $document['type']]);

    // Set the full path to the document
    $file_path = 'uploads/hr_documents/' . $document['filename'];

    if (!file_exists($file_path)) {
        die('File not found');
    }

    // Get file extension
    $file_extension = strtolower(pathinfo($document['filename'], PATHINFO_EXTENSION));

    // Set appropriate content type
    switch ($file_extension) {
        case 'pdf':
            header('Content-Type: application/pdf');
            break;
        case 'doc':
        case 'docx':
            header('Content-Type: application/msword');
            break;
        case 'xls':
        case 'xlsx':
            header('Content-Type: application/vnd.ms-excel');
            break;
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        default:
            header('Content-Type: application/octet-stream');
    }

    // Output the file
    readfile($file_path);

} catch (Exception $e) {
    error_log("Error in view_document.php: " . $e->getMessage());
    die('Error accessing document');
} 