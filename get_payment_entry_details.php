<?php
/**
 * Get Payment Entry Details API
 * Fetches complete payment entry information from all related tables
 * Used by payment_entry_details_modal.php
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $paymentEntryId = intval($_GET['payment_entry_id'] ?? 0);

    if ($paymentEntryId <= 0) {
        throw new Exception('Invalid payment entry ID');
    }

    // ===================================================================
    // 1. Fetch Master Record with Project Title
    // ===================================================================
    $masterQuery = "
        SELECT 
            m.*,
            u.username as created_by_username,
            p.title as project_title
        FROM tbl_payment_entry_master_records m
        LEFT JOIN users u ON m.created_by_user_id = u.id
        LEFT JOIN projects p ON (
            CASE 
                WHEN m.project_id_fk > 0 THEN m.project_id_fk = p.id
                ELSE CAST(m.project_name_reference AS UNSIGNED) = p.id
            END
        )
        WHERE m.payment_entry_id = :entry_id
    ";
    
    $masterStmt = $pdo->prepare($masterQuery);
    $masterStmt->bindParam(':entry_id', $paymentEntryId);
    $masterStmt->execute();
    $masterRecord = $masterStmt->fetch(PDO::FETCH_ASSOC);

    if (!$masterRecord) {
        throw new Exception('Payment entry not found');
    }

    // ===================================================================
    // 2. Fetch Summary Totals
    // ===================================================================
    $summaryQuery = "
        SELECT * FROM tbl_payment_entry_summary_totals
        WHERE payment_entry_master_id_fk = :entry_id
    ";
    
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->bindParam(':entry_id', $paymentEntryId);
    $summaryStmt->execute();
    $summaryTotals = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    // ===================================================================
    // 3. Fetch Acceptance Methods (Main Payment)
    // ===================================================================
    $acceptanceQuery = "
        SELECT * FROM tbl_payment_acceptance_methods_primary
        WHERE payment_entry_id_fk = :entry_id
        ORDER BY method_sequence_order ASC
    ";
    
    $acceptanceStmt = $pdo->prepare($acceptanceQuery);
    $acceptanceStmt->bindParam(':entry_id', $paymentEntryId);
    $acceptanceStmt->execute();
    $acceptanceMethods = $acceptanceStmt->fetchAll(PDO::FETCH_ASSOC);

    // ===================================================================
    // 4. Fetch Line Items
    // ===================================================================
    $lineItemsQuery = "
        SELECT * FROM tbl_payment_entry_line_items_detail
        WHERE payment_entry_master_id_fk = :entry_id
        ORDER BY line_item_sequence_number ASC
    ";
    
    $lineItemsStmt = $pdo->prepare($lineItemsQuery);
    $lineItemsStmt->bindParam(':entry_id', $paymentEntryId);
    $lineItemsStmt->execute();
    $lineItems = $lineItemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // ===================================================================
    // 5. Fetch File Attachments
    // ===================================================================
    $filesQuery = "
        SELECT * FROM tbl_payment_entry_file_attachments_registry
        WHERE payment_entry_master_id_fk = :entry_id
        ORDER BY attachment_upload_timestamp DESC
    ";
    
    $filesStmt = $pdo->prepare($filesQuery);
    $filesStmt->bindParam(':entry_id', $paymentEntryId);
    $filesStmt->execute();
    $fileAttachments = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

    // ===================================================================
    // 6. Fetch Audit Log
    // ===================================================================
    $auditQuery = "
        SELECT 
            a.*,
            u.username as performed_by_username
        FROM tbl_payment_entry_audit_activity_log a
        LEFT JOIN users u ON a.audit_performed_by_user_id = u.id
        WHERE a.payment_entry_id_fk = :entry_id
        ORDER BY a.audit_action_timestamp_utc DESC
    ";
    
    $auditStmt = $pdo->prepare($auditQuery);
    $auditStmt->bindParam(':entry_id', $paymentEntryId);
    $auditStmt->execute();
    $auditLog = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

    // ===================================================================
    // Success Response
    // ===================================================================
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment entry details fetched successfully',
        'data' => [
            'master_record' => $masterRecord,
            'summary_totals' => $summaryTotals,
            'acceptance_methods' => $acceptanceMethods,
            'line_items' => $lineItems,
            'file_attachments' => $fileAttachments,
            'audit_log' => $auditLog
        ]
    ]);

} catch (Exception $e) {
    error_log('Get Payment Entry Details Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
