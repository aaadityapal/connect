<?php
/**
 * Fetch Complete Payment Entry Data - Comprehensive API
 * 
 * Retrieves complete payment entry information including:
 * - Master payment record
 * - Multiple acceptance methods (if applicable)
 * - All line items with their sub-details
 * - File attachments registry
 * - Payment entry status and audit information
 * - Summary totals and calculations
 * 
 * Used by: payment_entry_edit_modal_comprehensive_v2.php
 * API Endpoint: fetch_complete_payment_entry_data_comprehensive.php?payment_entry_id=123
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get payment entry ID from query parameter
$payment_entry_id = intval($_GET['payment_entry_id'] ?? 0);

if (!$payment_entry_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment entry ID is required']);
    exit;
}

try {
    // ===================================================================
    // 1. Fetch Master Payment Record with Project and User Details
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            p.id as project_id,
            p.title as project_title,
            p.project_type as project_type_name,
            p.description as project_description,
            u.username as created_by_username,
            u_auth.username as authorized_user_username,
            u_edited.username as edited_by_username
        FROM tbl_payment_entry_master_records m
        LEFT JOIN projects p ON m.project_id_fk = p.id
        LEFT JOIN users u ON m.created_by_user_id = u.id
        LEFT JOIN users u_auth ON m.authorized_user_id_fk = u_auth.id
        LEFT JOIN users u_edited ON m.edited_by = u_edited.id
        WHERE m.payment_entry_id = :id
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $masterRecord = $stmt->fetch();

    if (!$masterRecord) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment entry not found']);
        exit;
    }

    // ===================================================================
    // 2. Fetch Acceptance Methods (Primary)
    // ===================================================================
    $stmt = $pdo->prepare("
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
            recorded_timestamp
        FROM tbl_payment_acceptance_methods_primary
        WHERE payment_entry_id_fk = :id
        ORDER BY method_sequence_order ASC
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $acceptanceMethods = $stmt->fetchAll();

    // ===================================================================
    // 3. Fetch Line Items with Sub-Details
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT 
            l.line_item_entry_id,
            l.payment_entry_master_id_fk,
            l.recipient_type_category,
            l.recipient_id_reference,
            l.recipient_name_display,
            l.payment_description_notes,
            l.line_item_amount,
            l.line_item_payment_mode,
            l.line_item_paid_via_user_id,
            l.line_item_sequence_number,
            l.line_item_media_upload_path,
            l.line_item_media_original_filename,
            l.line_item_media_filesize_bytes,
            l.line_item_media_mime_type,
            l.line_item_status,
            l.created_at_timestamp,
            l.modified_at_timestamp,
            l.approved_by,
            l.approved_at,
            l.rejected_by,
            l.rejected_at,
            l.rejection_reason,
            l.edited_by,
            l.edited_at,
            l.edit_count,
            u.username as paid_by_username,
            u_approved.username as approved_by_username,
            u_rejected.username as rejected_by_username,
            u_edited.username as edited_by_username
        FROM tbl_payment_entry_line_items_detail l
        LEFT JOIN users u ON l.line_item_paid_via_user_id = u.id
        LEFT JOIN users u_approved ON l.approved_by = u_approved.id
        LEFT JOIN users u_rejected ON l.rejected_by = u_rejected.id
        LEFT JOIN users u_edited ON l.edited_by = u_edited.id
        WHERE l.payment_entry_master_id_fk = :id
        ORDER BY l.line_item_sequence_number ASC
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $lineItems = $stmt->fetchAll();

    // Fetch acceptance methods for each line item
    foreach ($lineItems as &$lineItem) {
        $stmt = $pdo->prepare("
            SELECT 
                line_item_acceptance_method_id,
                line_item_entry_id_fk,
                payment_entry_master_id_fk,
                method_type_category,
                method_amount_received,
                method_reference_identifier,
                method_display_sequence,
                method_supporting_media_path,
                method_supporting_media_filename,
                method_supporting_media_size,
                method_supporting_media_type,
                method_recorded_at
            FROM tbl_payment_acceptance_methods_line_items
            WHERE line_item_entry_id_fk = :line_item_id
            ORDER BY method_display_sequence ASC
        ");
        $stmt->execute([':line_item_id' => $lineItem['line_item_entry_id']]);
        $lineItem['acceptance_methods'] = $stmt->fetchAll();
    }

    // ===================================================================
    // 4. Fetch File Attachments Registry
    // ===================================================================
    $stmt = $pdo->prepare("
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
            attachment_verification_status,
            attachment_integrity_hash,
            uploaded_by_user_id
        FROM tbl_payment_entry_file_attachments_registry
        WHERE payment_entry_master_id_fk = :id
        ORDER BY attachment_upload_timestamp DESC
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $fileAttachments = $stmt->fetchAll();

    // ===================================================================
    // 5. Fetch Summary Totals
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT 
            summary_id,
            payment_entry_master_id_fk,
            total_amount_main_payment,
            total_amount_acceptance_methods,
            total_amount_line_items,
            total_amount_grand_aggregate,
            acceptance_methods_count,
            line_items_count,
            total_files_attached,
            summary_calculated_timestamp
        FROM tbl_payment_entry_summary_totals
        WHERE payment_entry_master_id_fk = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $summaryTotals = $stmt->fetch();

    // ===================================================================
    // 6. Fetch Audit Activity Log
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT 
            audit_log_id,
            payment_entry_id_fk,
            audit_action_type,
            audit_change_description,
            audit_performed_by_user_id,
            audit_action_timestamp_utc,
            audit_ip_address_captured,
            audit_user_agent_info
        FROM tbl_payment_entry_audit_activity_log
        WHERE payment_entry_id_fk = :id
        ORDER BY audit_action_timestamp_utc DESC
        LIMIT 50
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $auditLog = $stmt->fetchAll();

    // ===================================================================
    // 7. Fetch Status Transition History
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT 
            status_history_id,
            payment_entry_master_id_fk,
            status_from_previous,
            status_to_current,
            status_changed_by_user_id,
            status_change_reason_notes,
            status_change_timestamp_utc
        FROM tbl_payment_entry_status_transition_history
        WHERE payment_entry_master_id_fk = :id
        ORDER BY status_change_timestamp_utc DESC
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $statusHistory = $stmt->fetchAll();

    // ===================================================================
    // 8. Fetch Rejection Reasons (if any)
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT 
            rejection_id,
            payment_entry_master_id_fk,
            rejection_reason_code,
            rejection_reason_description,
            rejected_by_user_id,
            rejection_timestamp_utc,
            rejection_attachments_notes,
            resubmission_requested
        FROM tbl_payment_entry_rejection_reasons_detail
        WHERE payment_entry_master_id_fk = :id
        ORDER BY rejection_timestamp_utc DESC
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $rejectionReasons = $stmt->fetchAll();

    // ===================================================================
    // 9. Fetch Approval Records
    // ===================================================================
    $stmt = $pdo->prepare("
        SELECT 
            approval_id,
            payment_entry_master_id_fk,
            approved_by_user_id,
            approval_timestamp_utc,
            approval_notes_comments,
            approval_authorized_amount,
            approval_clearance_code,
            approval_reference_document_number
        FROM tbl_payment_entry_approval_records_final
        WHERE payment_entry_master_id_fk = :id
        ORDER BY approval_timestamp_utc DESC
    ");
    $stmt->execute([':id' => $payment_entry_id]);
    $approvalRecords = $stmt->fetchAll();

    // ===================================================================
    // Build Success Response
    // ===================================================================
    $responseData = [
        'success' => true,
        'entry' => array_merge(
            (array)$masterRecord,
            [
                'acceptance_methods' => $acceptanceMethods,
                'line_items' => $lineItems,
                'file_attachments' => $fileAttachments,
                'summary_totals' => $summaryTotals,
                'audit_log' => $auditLog,
                'status_history' => $statusHistory,
                'rejection_reasons' => $rejectionReasons,
                'approval_records' => $approvalRecords
            ]
        )
    ];

    http_response_code(200);
    echo json_encode($responseData);

} catch (PDOException $e) {
    error_log('Database Error in fetch_complete_payment_entry_data_comprehensive: ' . $e->getMessage());
    error_log('SQL Error Code: ' . $e->getCode());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    error_log('Error in fetch_complete_payment_entry_data_comprehensive: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
