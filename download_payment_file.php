<?php
/**
 * Download Payment Entry File
 * Allows user to download a specific attachment from a payment entry
 * File Type: Download Handler
 * Unique ID: download_payment_file
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
            attachment_file_size_bytes,
            attachment_integrity_hash,
            attachment_verification_status
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

    // Log download
    error_log("File download: attachment_id={$attachment_id}, user_id={$_SESSION['user_id']}, file={$file['attachment_file_original_name']}");

    // Set headers for download
    header('Content-Type: ' . $file['attachment_file_mime_type']);
    header('Content-Length: ' . $file['attachment_file_size_bytes']);
    header('Content-Disposition: attachment; filename="' . basename($file['attachment_file_original_name']) . '"');
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    // Read and output file
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log('Download Payment File Error: ' . $e->getMessage());
    http_response_code(500);
    die('Error downloading file: ' . $e->getMessage());
}
?>
