<?php
/**
 * Preview Payment Entry File
 * Displays a preview of supported file types (images, PDF)
 * File Type: Preview Handler
 * Unique ID: preview_payment_file
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('User not authenticated');
}

try {
    // Get attachment ID
    $attachment_id = $_GET['attachment_id'] ?? null;

    if (empty($attachment_id)) {
        http_response_code(400);
        die('Attachment ID is required');
    }

    // Validate attachment ID
    $attachment_id = intval($attachment_id);

    // Query to fetch file details
    $query = "
        SELECT 
            attachment_id,
            attachment_file_original_name,
            attachment_file_stored_path,
            attachment_file_mime_type,
            attachment_file_extension,
            attachment_file_size_bytes
        FROM tbl_payment_entry_file_attachments_registry
        WHERE attachment_id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$attachment_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        die('File not found');
    }

    // Check if file exists on disk
    $file_path = $file['attachment_file_stored_path'];
    
    if (!file_exists($file_path) || !is_readable($file_path)) {
        http_response_code(404);
        error_log('File not found on disk: ' . $file_path);
        die('File not accessible on server');
    }

    // Security check: verify file is not outside webroot
    $real_path = realpath($file_path);
    $base_path = realpath(__DIR__);
    
    if (strpos($real_path, $base_path) !== 0) {
        http_response_code(403);
        error_log('Attempted path traversal: ' . $file_path);
        die('Access denied');
    }

    // Check if file type is previewable
    $previewable_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    
    if (!in_array($file['attachment_file_mime_type'], $previewable_types)) {
        http_response_code(400);
        die('File type not previewable');
    }

    // Log preview
    error_log("File preview: attachment_id={$attachment_id}, user_id={$_SESSION['user_id']}, file={$file['attachment_file_original_name']}");

    // Set headers for preview
    header('Content-Type: ' . $file['attachment_file_mime_type']);
    header('Content-Length: ' . $file['attachment_file_size_bytes']);
    header('Content-Disposition: inline; filename="' . basename($file['attachment_file_original_name']) . '"');

    // Read and output file
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log('Preview Payment File Error: ' . $e->getMessage());
    http_response_code(500);
    die('Error previewing file: ' . $e->getMessage());
}
?>
