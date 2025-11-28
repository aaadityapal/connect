<?php
/**
 * Fetch Payment Mode Attachments
 * Returns all file attachments related to payment modes for a payment entry
 * Gets data from both acceptance methods and file attachments registry
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    // Include database connection
    require_once __DIR__ . '/config/db_connect.php';
    
    // Get payment_entry_id from GET parameter
    $payment_entry_id = intval($_GET['payment_entry_id'] ?? 0);
    
    if (!$payment_entry_id) {
        throw new Exception('Payment entry ID is required');
    }
    
    // First, try to fetch acceptance methods (for main payment with multiple_acceptance mode)
    $acceptanceQuery = "
        SELECT 
            acceptance_method_id,
            payment_entry_id_fk,
            payment_method_type,
            amount_received_value,
            reference_number_cheque,
            method_sequence_order,
            supporting_document_path,
            supporting_document_original_name,
            supporting_document_filesize,
            supporting_document_mime_type,
            recorded_timestamp,
            'acceptance_method' as source_type
        FROM tbl_payment_acceptance_methods_primary
        WHERE payment_entry_id_fk = :payment_entry_id
        ORDER BY method_sequence_order ASC, recorded_timestamp DESC
    ";
    
    $acceptanceStmt = $pdo->prepare($acceptanceQuery);
    $acceptanceStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $acceptanceStmt->execute();
    $acceptanceMethods = $acceptanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fix paths for acceptance methods
    foreach ($acceptanceMethods as &$method) {
        if ($method['supporting_document_path'] && strpos($method['supporting_document_path'], '/uploads/') === 0) {
            $method['supporting_document_path'] = '/connect' . $method['supporting_document_path'];
        }
    }
    
    // Second, fetch file attachments from the registry (for both main payment proof and method supporting documents)
    $fileQuery = "
        SELECT 
            attachment_id,
            payment_entry_master_id_fk,
            attachment_type_category,
            attachment_reference_id,
            attachment_file_original_name,
            attachment_file_stored_path,
            attachment_file_size_bytes,
            attachment_file_mime_type,
            attachment_file_extension,
            attachment_upload_timestamp,
            'file_registry' as source_type
        FROM tbl_payment_entry_file_attachments_registry
        WHERE payment_entry_master_id_fk = :payment_entry_id
        AND (
            attachment_type_category = 'acceptance_method_media'
            OR attachment_type_category = 'proof_image'
        )
        ORDER BY attachment_upload_timestamp DESC
    ";
    
    $fileStmt = $pdo->prepare($fileQuery);
    $fileStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $fileStmt->execute();
    $fileAttachments = $fileStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine both results and fix paths
    // Convert relative paths to proper URLs by prefixing with /connect if needed
    foreach ($fileAttachments as &$file) {
        if (strpos($file['attachment_file_stored_path'], '/uploads/') === 0) {
            $file['attachment_file_stored_path'] = '/connect' . $file['attachment_file_stored_path'];
        }
    }
    
    $allAttachments = array_merge($acceptanceMethods, $fileAttachments);
    
    // If no acceptance methods but there are file attachments, format the response to show files
    if (empty($acceptanceMethods) && !empty($fileAttachments)) {
        $formattedAttachments = [];
        foreach ($fileAttachments as $file) {
            // Fix paths here too
            $filePath = $file['attachment_file_stored_path'];
            if (strpos($filePath, '/uploads/') === 0) {
                $filePath = '/connect' . $filePath;
            }
            
            if ($file['attachment_type_category'] === 'acceptance_method_media') {
                $formattedAttachments[] = [
                    'payment_method_type' => 'Document',
                    'amount_received_value' => '0.00',
                    'reference_number_cheque' => $file['attachment_file_original_name'],
                    'supporting_document_path' => $filePath,
                    'supporting_document_original_name' => $file['attachment_file_original_name'],
                    'source_type' => 'file_registry'
                ];
            } else if ($file['attachment_type_category'] === 'proof_image') {
                $formattedAttachments[] = [
                    'payment_method_type' => 'Payment Proof',
                    'amount_received_value' => '0.00',
                    'reference_number_cheque' => $file['attachment_file_original_name'],
                    'supporting_document_path' => $filePath,
                    'supporting_document_original_name' => $file['attachment_file_original_name'],
                    'source_type' => 'file_registry'
                ];
            }
        }
        $allAttachments = $formattedAttachments;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'acceptance_methods' => $acceptanceMethods,
        'file_attachments' => $fileAttachments,
        'all_attachments' => $allAttachments,
        'count_acceptance' => count($acceptanceMethods),
        'count_files' => count($fileAttachments)
    ]);
    
} catch (Exception $e) {
    error_log('Error in fetch_payment_acceptance_methods.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>

