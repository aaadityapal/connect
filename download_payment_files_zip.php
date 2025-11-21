<?php
/**
 * Download Payment Entry Files as ZIP
 * Allows user to download all attachments from a payment entry as a single ZIP file
 * File Type: ZIP Download Handler
 * Unique ID: download_payment_files_zip
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('User not authenticated');
}

try {
    // Get payment entry ID
    $payment_entry_id = $_GET['payment_entry_id'] ?? null;

    if (empty($payment_entry_id)) {
        http_response_code(400);
        die('Payment entry ID is required');
    }

    // Validate payment entry ID
    $payment_entry_id = intval($payment_entry_id);

    // Query to fetch all files for this payment entry
    $query = "
        SELECT 
            attachment_id,
            attachment_file_original_name,
            attachment_file_stored_path,
            attachment_file_size_bytes
        FROM tbl_payment_entry_file_attachments_registry
        WHERE payment_entry_master_id_fk = ?
        ORDER BY attachment_upload_timestamp DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$payment_entry_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$files || count($files) === 0) {
        http_response_code(404);
        die('No files found for this payment entry');
    }

    // Check if ZipArchive is available
    if (!extension_loaded('zip')) {
        http_response_code(500);
        error_log('ZIP extension not available on this server');
        die('ZIP compression not available');
    }

    // Create temporary ZIP file
    $temp_dir = sys_get_temp_dir();
    $zip_filename = 'payment_entry_' . $payment_entry_id . '_' . time() . '.zip';
    $zip_path = $temp_dir . DIRECTORY_SEPARATOR . $zip_filename;

    $zip = new ZipArchive();
    
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        http_response_code(500);
        die('Cannot create ZIP file');
    }

    // Add files to ZIP
    $file_count = 0;
    $base_path = realpath(__DIR__);

    foreach ($files as $file) {
        $file_path = $file['attachment_file_stored_path'];

        // Security check: verify file is not outside webroot
        if (!file_exists($file_path) || !is_readable($file_path)) {
            error_log('File not accessible: ' . $file_path);
            continue;
        }

        $real_path = realpath($file_path);
        if (strpos($real_path, $base_path) !== 0) {
            error_log('Attempted path traversal in ZIP: ' . $file_path);
            continue;
        }

        // Add file to ZIP with original name
        $archive_name = basename($file['attachment_file_original_name']);
        
        // Handle duplicate filenames
        $counter = 1;
        $original_name = $archive_name;
        $name_parts = pathinfo($original_name);
        
        while ($zip->locateName($archive_name) !== false) {
            $archive_name = $name_parts['filename'] . '_' . $counter . '.' . $name_parts['extension'];
            $counter++;
        }

        if ($zip->addFile($file_path, $archive_name)) {
            $file_count++;
        } else {
            error_log('Failed to add file to ZIP: ' . $file_path);
        }
    }

    $zip->close();

    // Check if any files were added
    if ($file_count === 0) {
        @unlink($zip_path);
        http_response_code(500);
        die('Failed to create ZIP file with attachments');
    }

    // Get ZIP file size
    $zip_size = filesize($zip_path);

    // Log download
    error_log("ZIP download: payment_entry_id={$payment_entry_id}, user_id={$_SESSION['user_id']}, files={$file_count}, size={$zip_size}");

    // Send ZIP file
    header('Content-Type: application/zip');
    header('Content-Length: ' . $zip_size);
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    // Read and output ZIP file
    readfile($zip_path);

    // Delete temporary ZIP file
    @unlink($zip_path);
    exit;

} catch (Exception $e) {
    error_log('Download Payment Files ZIP Error: ' . $e->getMessage());
    http_response_code(500);
    die('Error creating ZIP file: ' . $e->getMessage());
}
?>
