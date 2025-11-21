<?php
/**
 * API: Get Recipient Line Item Files
 * Fetches files from: tbl_payment_entry_line_items_detail
 * Returns: Files attached to a specific line item/recipient
 */

session_start();

// Database connection
require_once 'config/db_connect.php';

try {
    // Get parameters
    $payment_entry_id = $_GET['payment_entry_id'] ?? null;
    $recipient_id = $_GET['recipient_id'] ?? null;  // This is the actual recipient ID
    $recipient_name = $_GET['recipient_name'] ?? null;

    if (!$payment_entry_id || !$recipient_id) {
        throw new Exception('Missing required parameters: payment_entry_id and recipient_id');
    }

    // Query line item files from tbl_payment_entry_line_items_detail
    // Group by recipient_id_reference to get all files for this recipient
    $stmt = $pdo->prepare("
        SELECT 
            line_item_entry_id,
            line_item_media_upload_path,
            line_item_media_original_filename,
            line_item_media_filesize_bytes,
            line_item_media_mime_type,
            recipient_name_display,
            recipient_type_category,
            created_at_timestamp
        FROM tbl_payment_entry_line_items_detail
        WHERE payment_entry_master_id_fk = :payment_entry_id
        AND recipient_id_reference = :recipient_id
        AND line_item_media_upload_path IS NOT NULL
        AND line_item_media_upload_path != ''
        ORDER BY created_at_timestamp DESC
    ");

    $stmt->execute([
        ':payment_entry_id' => (int)$payment_entry_id,
        ':recipient_id' => (int)$recipient_id
    ]);

    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return response
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'line_item_files' => $files ?: [],
        'count' => count($files)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => 'Failed to fetch line item files'
    ]);
}
?>
