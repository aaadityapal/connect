<?php
/**
 * Update Payment Entry Handler
 * Updates payment entry details and related records
 * Called from payment_entry_edit_modal_comprehensive_v2.php
 * 
 * Handles updating:
 * - tbl_payment_entry_master_records (main entry)
 * - tbl_payment_acceptance_methods_primary (acceptance methods)
 * - tbl_payment_entry_line_items_detail (line items)
 * - tbl_payment_entry_audit_activity_log (audit logging)
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// ===================================================================
// Helper Function: Fetch Recipient Name by Type and ID
// ===================================================================
function fetchRecipientName($recipientType, $recipientId, $pdo) {
    if (!$recipientType || !$recipientId) {
        return '';
    }

    try {
        // Check if recipient type is labour-related
        if (stripos($recipientType, 'labour') !== false || 
            stripos($recipientType, 'permanent') !== false || 
            stripos($recipientType, 'temporary') !== false) {
            
            // Fetch from labour table
            $stmt = $pdo->prepare("
                SELECT COALESCE(full_name, '') as name
                FROM tbl_labour_management_master
                WHERE labour_id = :id
                LIMIT 1
            ");
            $stmt->bindValue(':id', $recipientId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['name'])) {
                return $result['name'];
            }
        } else {
            // Try vendor table
            $stmt = $pdo->prepare("
                SELECT COALESCE(vendor_full_name, '') as name
                FROM pm_vendor_registry_master
                WHERE vendor_id = :id
                LIMIT 1
            ");
            $stmt->bindValue(':id', $recipientId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['name'])) {
                return $result['name'];
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching recipient name: ' . $e->getMessage());
    }

    return '';
}

try {
    // Get POST data (form sends camelCase, so we need to handle both formats)
    $payment_entry_id = intval($_POST['payment_entry_id'] ?? 0);
    $payment_date = $_POST['paymentDate'] ?? $_POST['payment_date'] ?? '';
    $payment_amount = floatval($_POST['paymentAmount'] ?? $_POST['payment_amount'] ?? 0);
    $authorized_user_id = intval($_POST['authorizedUserId'] ?? $_POST['authorized_user_id'] ?? 0);
    $payment_mode = $_POST['paymentMode'] ?? $_POST['payment_mode'] ?? '';
    $admin_notes = $_POST['adminNotes'] ?? $_POST['admin_notes'] ?? '';
    $acceptance_methods = !empty($_POST['acceptanceMethods']) ? json_decode($_POST['acceptanceMethods'], true) : 
                          (!empty($_POST['acceptance_methods']) ? json_decode($_POST['acceptance_methods'], true) : []);
    $line_items = !empty($_POST['lineItems']) ? json_decode($_POST['lineItems'], true) : 
                  (!empty($_POST['line_items']) ? json_decode($_POST['line_items'], true) : []);

    // Validate required fields
    if (!$payment_entry_id || !$payment_date || $payment_amount <= 0) {
        throw new Exception('Missing required fields: payment_entry_id, payment_date, payment_amount. Received: payment_entry_id=' . $payment_entry_id . ', payment_date=' . $payment_date . ', payment_amount=' . $payment_amount);
    }

    // Start transaction
    $pdo->beginTransaction();

    // ===================================================================
    // 1. Update Master Record
    // ===================================================================
    $updateMasterQuery = "
        UPDATE tbl_payment_entry_master_records
        SET 
            payment_date_logged = :payment_date,
            payment_amount_base = :payment_amount,
            authorized_user_id_fk = :authorized_user_id,
            payment_mode_selected = :payment_mode,
            notes_admin_internal = :admin_notes,
            updated_by_user_id = :updated_by_user_id,
            updated_timestamp_utc = NOW()
        WHERE payment_entry_id = :payment_entry_id
    ";

    $masterStmt = $pdo->prepare($updateMasterQuery);
    $masterStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $masterStmt->bindValue(':payment_date', $payment_date, PDO::PARAM_STR);
    $masterStmt->bindValue(':payment_amount', $payment_amount, PDO::PARAM_STR);
    $masterStmt->bindValue(':authorized_user_id', $authorized_user_id, PDO::PARAM_INT);
    $masterStmt->bindValue(':payment_mode', $payment_mode, PDO::PARAM_STR);
    $masterStmt->bindValue(':admin_notes', $admin_notes, PDO::PARAM_STR);
    $masterStmt->bindValue(':updated_by_user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    
    if (!$masterStmt->execute()) {
        throw new Exception('Failed to update payment entry master record: ' . implode(', ', $masterStmt->errorInfo()));
    }

    // ===================================================================
    // 2. Update Acceptance Methods (if payment_mode is multiple_acceptance)
    // ===================================================================
    // 2. Update Acceptance Methods (if payment_mode is multiple_acceptance)
    // ===================================================================
    if ($payment_mode === 'multiple_acceptance' && !empty($acceptance_methods)) {
        // Delete existing acceptance methods for this payment entry
        $deleteAcceptanceQuery = "
            DELETE FROM tbl_payment_acceptance_methods_primary
            WHERE payment_entry_id_fk = :payment_entry_id
        ";
        $deleteStmt = $pdo->prepare($deleteAcceptanceQuery);
        $deleteStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
        $deleteStmt->execute();

        // Insert new acceptance methods
        $insertAcceptanceQuery = "
            INSERT INTO tbl_payment_acceptance_methods_primary (
                payment_entry_id_fk,
                payment_method_type,
                amount_received_value,
                reference_number_cheque,
                method_sequence_order,
                recorded_timestamp
            ) VALUES (
                :payment_entry_id,
                :payment_method_type,
                :amount_received_value,
                :reference_number_cheque,
                :method_sequence_order,
                NOW()
            )
        ";

        $insertAcceptanceStmt = $pdo->prepare($insertAcceptanceQuery);

        foreach ($acceptance_methods as $index => $method) {
            $insertAcceptanceStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
            $insertAcceptanceStmt->bindValue(':payment_method_type', $method['payment_method_type'] ?? '', PDO::PARAM_STR);
            $insertAcceptanceStmt->bindValue(':amount_received_value', $method['amount_received_value'] ?? 0, PDO::PARAM_STR);
            $insertAcceptanceStmt->bindValue(':reference_number_cheque', $method['reference_number_cheque'] ?? null, PDO::PARAM_STR);
            $insertAcceptanceStmt->bindValue(':method_sequence_order', $index, PDO::PARAM_INT);

            if (!$insertAcceptanceStmt->execute()) {
                throw new Exception('Failed to insert acceptance method: ' . implode(', ', $insertAcceptanceStmt->errorInfo()));
            }
        }
    }

    // ===================================================================
    // 3. Update Line Items
    // ===================================================================
    if (!empty($line_items)) {
        // Delete existing line items for this payment entry
        $deleteLineItemsQuery = "
            DELETE FROM tbl_payment_entry_line_items_detail
            WHERE payment_entry_master_id_fk = :payment_entry_id
        ";
        $deleteLineStmt = $pdo->prepare($deleteLineItemsQuery);
        $deleteLineStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
        $deleteLineStmt->execute();

        // Insert new line items
        $insertLineItemQuery = "
            INSERT INTO tbl_payment_entry_line_items_detail (
                payment_entry_master_id_fk,
                recipient_type_category,
                recipient_id_reference,
                recipient_name_display,
                payment_description_notes,
                line_item_amount,
                line_item_payment_mode,
                line_item_paid_via_user_id,
                line_item_sequence_number,
                line_item_status,
                created_at_timestamp,
                modified_at_timestamp
            ) VALUES (
                :payment_entry_id,
                :recipient_type_category,
                :recipient_id_reference,
                :recipient_name_display,
                :payment_description_notes,
                :line_item_amount,
                :line_item_payment_mode,
                :line_item_paid_via_user_id,
                :line_item_sequence_number,
                :line_item_status,
                NOW(),
                NOW()
            )
        ";

        $insertLineStmt = $pdo->prepare($insertLineItemQuery);

        foreach ($line_items as $index => $item) {
            $recipientTypeCategory = $item['recipient_type_category'] ?? '';
            $recipientIdReference = $item['recipient_id_reference'] ?? null;
            $recipientNameDisplay = $item['recipient_name_display'] ?? '';
            
            // If recipient_id_reference is provided, fetch the correct name from database
            if ($recipientIdReference && !$recipientNameDisplay) {
                $recipientNameDisplay = fetchRecipientName($recipientTypeCategory, $recipientIdReference, $pdo);
            }
            
            $insertLineStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
            $insertLineStmt->bindValue(':recipient_type_category', $recipientTypeCategory, PDO::PARAM_STR);
            $insertLineStmt->bindValue(':recipient_id_reference', $recipientIdReference, PDO::PARAM_INT);
            $insertLineStmt->bindValue(':recipient_name_display', $recipientNameDisplay, PDO::PARAM_STR);
            $insertLineStmt->bindValue(':payment_description_notes', $item['payment_description_notes'] ?? '', PDO::PARAM_STR);
            $insertLineStmt->bindValue(':line_item_amount', $item['line_item_amount'] ?? 0, PDO::PARAM_STR);
            $insertLineStmt->bindValue(':line_item_payment_mode', $item['line_item_payment_mode'] ?? '', PDO::PARAM_STR);
            $insertLineStmt->bindValue(':line_item_paid_via_user_id', $item['line_item_paid_via_user_id'] ?? null, PDO::PARAM_INT);
            $insertLineStmt->bindValue(':line_item_sequence_number', $index, PDO::PARAM_INT);
            $insertLineStmt->bindValue(':line_item_status', $item['line_item_status'] ?? 'pending', PDO::PARAM_STR);

            if (!$insertLineStmt->execute()) {
                throw new Exception('Failed to insert line item: ' . implode(', ', $insertLineStmt->errorInfo()));
            }
        }
    }

    // ===================================================================
    // 4. Log Audit Activity
    // ===================================================================
    $auditQuery = "
        INSERT INTO tbl_payment_entry_audit_activity_log (
            payment_entry_id_fk,
            audit_action_type,
            audit_change_description,
            audit_performed_by_user_id,
            audit_action_timestamp_utc,
            audit_ip_address_captured,
            audit_user_agent_info
        ) VALUES (
            :payment_entry_id,
            :action_type,
            :change_description,
            :user_id,
            NOW(),
            :ip_address,
            :user_agent
        )
    ";

    $auditStmt = $pdo->prepare($auditQuery);
    $auditStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $auditStmt->bindValue(':action_type', 'updated', PDO::PARAM_STR);
    $auditStmt->bindValue(':change_description', 'Payment entry edited via comprehensive edit modal. Amount: ' . $payment_amount . ', Mode: ' . $payment_mode, PDO::PARAM_STR);
    $auditStmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $auditStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? 'Unknown', PDO::PARAM_STR);
    $auditStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', PDO::PARAM_STR);

    if (!$auditStmt->execute()) {
        throw new Exception('Failed to log audit activity: ' . implode(', ', $auditStmt->errorInfo()));
    }

    // ===================================================================
    // 5. Recalculate Summary Totals and Update Grand Total in Master Record
    // ===================================================================
    
    // First, calculate the sums - calculate acceptance methods total
    $calculateAcceptanceQuery = "
        SELECT COALESCE(SUM(amount_received_value), 0) as total_acceptance
        FROM tbl_payment_acceptance_methods_primary
        WHERE payment_entry_id_fk = :payment_entry_id
    ";
    
    $calcAcceptanceStmt = $pdo->prepare($calculateAcceptanceQuery);
    $calcAcceptanceStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $calcAcceptanceStmt->execute();
    $acceptanceData = $calcAcceptanceStmt->fetch(PDO::FETCH_ASSOC);
    $totalAcceptanceMethods = floatval($acceptanceData['total_acceptance'] ?? 0);
    
    // Calculate line items total
    $calculateLineItemsQuery = "
        SELECT COALESCE(SUM(line_item_amount), 0) as total_line_items
        FROM tbl_payment_entry_line_items_detail
        WHERE payment_entry_master_id_fk = :payment_entry_id
    ";
    
    $calcLineItemsStmt = $pdo->prepare($calculateLineItemsQuery);
    $calcLineItemsStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $calcLineItemsStmt->execute();
    $lineItemsData = $calcLineItemsStmt->fetch(PDO::FETCH_ASSOC);
    $totalLineItems = floatval($lineItemsData['total_line_items'] ?? 0);
    
    // Get acceptance methods count
    $getAcceptanceCountQuery = "
        SELECT COUNT(*) as acceptance_count
        FROM tbl_payment_acceptance_methods_primary
        WHERE payment_entry_id_fk = :payment_entry_id
    ";
    
    $getAcceptanceCountStmt = $pdo->prepare($getAcceptanceCountQuery);
    $getAcceptanceCountStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $getAcceptanceCountStmt->execute();
    $acceptanceCountData = $getAcceptanceCountStmt->fetch(PDO::FETCH_ASSOC);
    $acceptanceCount = intval($acceptanceCountData['acceptance_count'] ?? 0);
    
    // Get line items count
    $getLineItemsCountQuery = "
        SELECT COUNT(*) as line_items_count
        FROM tbl_payment_entry_line_items_detail
        WHERE payment_entry_master_id_fk = :payment_entry_id
    ";
    
    $getLineItemsCountStmt = $pdo->prepare($getLineItemsCountQuery);
    $getLineItemsCountStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $getLineItemsCountStmt->execute();
    $lineItemsCountData = $getLineItemsCountStmt->fetch(PDO::FETCH_ASSOC);
    $lineItemsCount = intval($lineItemsCountData['line_items_count'] ?? 0);
    
    // Calculate grand total: ONLY sum of line items + acceptance methods
    // Note: Main payment amount field is now just for display/reference
    // The actual grand total should be calculated from line items and acceptance methods
    $grandTotal = $totalLineItems + $totalAcceptanceMethods;
    
    // Update master record with new grand total
    $updateGrandTotalQuery = "
        UPDATE tbl_payment_entry_master_records
        SET payment_amount_base = :grand_total
        WHERE payment_entry_id = :payment_entry_id
    ";
    
    $updateGrandStmt = $pdo->prepare($updateGrandTotalQuery);
    $updateGrandStmt->bindValue(':grand_total', $grandTotal, PDO::PARAM_STR);
    $updateGrandStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    
    if (!$updateGrandStmt->execute()) {
        throw new Exception('Failed to update grand total: ' . implode(', ', $updateGrandStmt->errorInfo()));
    }
    
    // Now update or insert the summary table with pre-calculated values
    // Using INSERT ... ON DUPLICATE KEY UPDATE to handle both cases
    $recalculateTotalsQuery = "
        INSERT INTO tbl_payment_entry_summary_totals (
            payment_entry_master_id_fk,
            total_amount_main_payment,
            total_amount_acceptance_methods,
            total_amount_line_items,
            total_amount_grand_aggregate,
            acceptance_methods_count,
            line_items_count,
            summary_calculated_timestamp
        ) VALUES (
            :payment_entry_id,
            :grand_total,
            :total_acceptance,
            :total_line_items,
            :grand_total_agg,
            :acceptance_count,
            :line_items_count,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            total_amount_main_payment = VALUES(total_amount_main_payment),
            total_amount_acceptance_methods = VALUES(total_amount_acceptance_methods),
            total_amount_line_items = VALUES(total_amount_line_items),
            total_amount_grand_aggregate = VALUES(total_amount_grand_aggregate),
            acceptance_methods_count = VALUES(acceptance_methods_count),
            line_items_count = VALUES(line_items_count),
            summary_calculated_timestamp = NOW()
    ";

    $recalcStmt = $pdo->prepare($recalculateTotalsQuery);
    $recalcStmt->bindValue(':payment_entry_id', $payment_entry_id, PDO::PARAM_INT);
    $recalcStmt->bindValue(':grand_total', $grandTotal, PDO::PARAM_STR);
    $recalcStmt->bindValue(':total_acceptance', $totalAcceptanceMethods, PDO::PARAM_STR);
    $recalcStmt->bindValue(':total_line_items', $totalLineItems, PDO::PARAM_STR);
    $recalcStmt->bindValue(':grand_total_agg', $grandTotal, PDO::PARAM_STR);
    $recalcStmt->bindValue(':acceptance_count', $acceptanceCount, PDO::PARAM_INT);
    $recalcStmt->bindValue(':line_items_count', $lineItemsCount, PDO::PARAM_INT);
    
    if (!$recalcStmt->execute()) {
        throw new Exception('Failed to recalculate summary totals: ' . implode(', ', $recalcStmt->errorInfo()));
    }

    // Commit transaction
    $pdo->commit();

    // ===================================================================
    // Success Response
    // ===================================================================
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment entry updated successfully',
        'payment_entry_id' => $payment_entry_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Database Error in update_payment_entry_handler: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error in update_payment_entry_handler: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
