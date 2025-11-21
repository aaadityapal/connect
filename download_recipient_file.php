<?php
/**
 * Download Handler for Recipient Files
 * Serves files from:
 * - tbl_payment_entry_line_items_detail (line item media)
 * - tbl_payment_acceptance_methods_line_items (acceptance method media)
 */

session_start();

// Database connection
require_once 'config/db_connect.php';

try {
    // Get parameters
    $file_id = $_GET['file_id'] ?? null;
    $file_type = $_GET['file_type'] ?? null;  // 'line_item' or 'acceptance'

    if (!$file_id || !$file_type) {
        throw new Exception('Missing required parameters');
    }

    $file_path = null;
    $file_name = null;

    if ($file_type === 'line_item') {
        // Get file from line item table
        $stmt = $pdo->prepare("
            SELECT 
                line_item_media_upload_path as file_path,
                line_item_media_original_filename as file_name,
                line_item_media_mime_type as mime_type
            FROM tbl_payment_entry_line_items_detail
            WHERE line_item_entry_id = :file_id
            AND line_item_media_upload_path IS NOT NULL
            LIMIT 1
        ");

        $stmt->execute([':file_id' => (int)$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            throw new Exception('Line item file not found');
        }

        $file_path = $file['file_path'];
        $file_name = $file['file_name'];
        $mime_type = $file['mime_type'] ?? 'application/octet-stream';

    } elseif ($file_type === 'acceptance') {
        // Get file from acceptance methods table
        $stmt = $pdo->prepare("
            SELECT 
                method_supporting_media_path as file_path,
                method_supporting_media_filename as file_name,
                method_supporting_media_type as mime_type
            FROM tbl_payment_acceptance_methods_line_items
            WHERE line_item_acceptance_method_id = :file_id
            AND method_supporting_media_path IS NOT NULL
            LIMIT 1
        ");

        $stmt->execute([':file_id' => (int)$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            throw new Exception('Acceptance method file not found');
        }

        $file_path = $file['file_path'];
        $file_name = $file['file_name'];
        $mime_type = $file['mime_type'] ?? 'application/octet-stream';

    } else {
        throw new Exception('Invalid file type');
    }

    // Normalize file path
    if (empty($file_path)) {
        throw new Exception('File path is empty');
    }

    // Remove leading slashes and ensure proper path
    $file_path = trim($file_path, '/');

    // Construct full file system path
    $base_path = __DIR__;
    $full_path = $base_path . '/' . $file_path;

    // Security: Ensure the file is within the expected directory
    $real_path = realpath($full_path);
    $uploads_dir = realpath($base_path . '/uploads');

    if (!$real_path || strpos($real_path, $uploads_dir) !== 0) {
        throw new Exception('Invalid file path - security check failed');
    }

    // Check if file exists
    if (!file_exists($real_path)) {
        throw new Exception('File not found: ' . basename($real_path));
    }

    // Get file size
    $file_size = filesize($real_path);

    // Determine content type
    if (empty($mime_type)) {
        $mime_type = mime_content_type($real_path) ?: 'application/octet-stream';
    }

    // Clean output buffer
    ob_clean();

    // Set headers for download
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . $file_size);
    header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Read and output file
    readfile($real_path);
    exit;

} catch (Exception $e) {
    // Log error
    error_log('Download recipient file error: ' . $e->getMessage());

    // Return error response
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
