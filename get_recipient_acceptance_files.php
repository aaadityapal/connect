<?php
/**
 * API: Get Recipient Acceptance Method Files
 * Fetches files from: tbl_payment_acceptance_methods_line_items
 * Returns: Acceptance method files for a specific recipient
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

    // Query acceptance method files from tbl_payment_acceptance_methods_line_items
    // First find the line_item_entry_id associated with this recipient
    $line_stmt = $pdo->prepare("
        SELECT DISTINCT line_item_entry_id
        FROM tbl_payment_entry_line_items_detail
        WHERE payment_entry_master_id_fk = :payment_entry_id
        AND recipient_id_reference = :recipient_id
        LIMIT 1
    ");
    
    $line_stmt->execute([
        ':payment_entry_id' => (int)$payment_entry_id,
        ':recipient_id' => (int)$recipient_id
    ]);
    
    $line_item = $line_stmt->fetch(PDO::FETCH_ASSOC);
    $line_item_id = $line_item['line_item_entry_id'] ?? null;
    
    if (!$line_item_id) {
        throw new Exception('Could not find line item for this recipient');
    }

    // Now fetch acceptance method files for this line item
    $stmt = $pdo->prepare("
        SELECT 
            line_item_acceptance_method_id,
            line_item_entry_id_fk,
            method_type_category,
            method_supporting_media_path,
            method_supporting_media_filename,
            method_supporting_media_size,
            method_supporting_media_type,
            method_recorded_at
        FROM tbl_payment_acceptance_methods_line_items
        WHERE payment_entry_master_id_fk = :payment_entry_id
        AND line_item_entry_id_fk = :line_item_id
        AND method_supporting_media_path IS NOT NULL
        AND method_supporting_media_path != ''
        ORDER BY method_recorded_at DESC
    ");

    $stmt->execute([
        ':payment_entry_id' => (int)$payment_entry_id,
        ':line_item_id' => (int)$line_item_id
    ]);

    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return response
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'acceptance_files' => $files ?: [],
        'count' => count($files)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => 'Failed to fetch acceptance method files'
    ]);
}
?>
