<?php
/**
 * Get Payment Entry Files API
 * Fetches all files/attachments associated with a specific payment entry
 * File Type: API Endpoint
 * Unique ID: get_payment_entry_files
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Set response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Get payment entry ID and recipient filter
    $payment_entry_id = $_GET['payment_entry_id'] ?? null;
    $recipient_filter = $_GET['recipient_filter'] ?? null;
    $recipient_type = $_GET['recipient_type'] ?? null;

    if (empty($payment_entry_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment entry ID is required']);
        exit;
    }

    // Validate payment entry ID
    $payment_entry_id = intval($payment_entry_id);

    // Query to fetch files for this payment entry with optional recipient filter
    $query = "
        SELECT 
            f.attachment_id,
            f.payment_entry_master_id_fk,
            f.attachment_type_category,
            f.attachment_reference_id,
            f.attachment_file_original_name,
            f.attachment_file_stored_path,
            f.attachment_file_size_bytes,
            f.attachment_file_mime_type,
            f.attachment_file_extension,
            f.attachment_upload_timestamp,
            f.attachment_verification_status,
            f.attachment_integrity_hash,
            f.uploaded_by_user_id,
            u.username as uploaded_by_username,
            l.recipient_name_display,
            l.recipient_type_category,
            l.recipient_id_reference
        FROM tbl_payment_entry_file_attachments_registry f
        LEFT JOIN users u ON f.uploaded_by_user_id = u.id
        LEFT JOIN tbl_payment_entry_line_items_detail l ON f.payment_entry_master_id_fk = l.payment_entry_master_id_fk
        WHERE f.payment_entry_master_id_fk = ?
    ";

    $params = [$payment_entry_id];

    // Apply recipient filter if provided
    if (!empty($recipient_filter)) {
        $query .= " AND (l.recipient_name_display LIKE ? OR CAST(l.recipient_id_reference AS CHAR) = ?)";
        $params[] = '%' . $recipient_filter . '%';
        $params[] = $recipient_filter;
    }

    $query .= " ORDER BY f.attachment_upload_timestamp DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($files)) {
        $files = [];
    }

    // Format response
    $formatted_files = [];

    foreach ($files as $file) {
        $formatted_files[] = [
            'attachment_id' => intval($file['attachment_id']),
            'payment_entry_id' => intval($file['payment_entry_master_id_fk']),
            'attachment_type_category' => $file['attachment_type_category'],
            'attachment_reference_id' => $file['attachment_reference_id'],
            'attachment_file_original_name' => $file['attachment_file_original_name'],
            'attachment_file_stored_path' => $file['attachment_file_stored_path'],
            'attachment_file_size_bytes' => intval($file['attachment_file_size_bytes']),
            'attachment_file_mime_type' => $file['attachment_file_mime_type'],
            'attachment_file_extension' => strtolower($file['attachment_file_extension']),
            'attachment_upload_timestamp' => $file['attachment_upload_timestamp'],
            'attachment_verification_status' => $file['attachment_verification_status'],
            'attachment_integrity_hash' => $file['attachment_integrity_hash'],
            'uploaded_by_username' => $file['uploaded_by_username'] ?? 'Unknown'
        ];
    }

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment entry files fetched successfully',
        'data' => $formatted_files,
        'count' => count($formatted_files)
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log error
    error_log('Get Payment Entry Files Error: ' . $e->getMessage());
    error_log('Stack Trace: ' . $e->getTraceAsString());
    error_log('Payment Entry ID: ' . ($_GET['payment_entry_id'] ?? 'N/A'));

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching payment entry files: ' . $e->getMessage(),
        'debug' => [
            'payment_entry_id' => $_GET['payment_entry_id'] ?? 'N/A',
            'error' => $e->getMessage()
        ]
    ]);
    exit;
}
?>
