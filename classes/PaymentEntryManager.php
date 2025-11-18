<?php
/**
 * Payment Entry Manager Class
 * Provides utility methods for retrieving, updating, and managing payment entries
 */

class PaymentEntryManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get payment entry by ID with all related data
     */
    public function getPaymentEntryById($payment_entry_id) {
        try {
            // Get main payment entry
            $stmt = $this->pdo->prepare("
                SELECT * FROM tbl_payment_entry_master_records
                WHERE payment_entry_id = :id
            ");
            $stmt->execute([':id' => $payment_entry_id]);
            $payment = $stmt->fetch();

            if (!$payment) {
                return null;
            }

            // Get acceptance methods
            $stmt = $this->pdo->prepare("
                SELECT * FROM tbl_payment_acceptance_methods_primary
                WHERE payment_entry_id_fk = :id
                ORDER BY method_sequence_order
            ");
            $stmt->execute([':id' => $payment_entry_id]);
            $payment['acceptance_methods'] = $stmt->fetchAll();

            // Get line items
            $stmt = $this->pdo->prepare("
                SELECT * FROM tbl_payment_entry_line_items_detail
                WHERE payment_entry_master_id_fk = :id
                ORDER BY line_item_sequence_number
            ");
            $stmt->execute([':id' => $payment_entry_id]);
            $line_items = $stmt->fetchAll();

            // Get acceptance methods for each line item
            foreach ($line_items as &$item) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM tbl_payment_acceptance_methods_line_items
                    WHERE line_item_entry_id_fk = :line_item_id
                    ORDER BY method_display_sequence
                ");
                $stmt->execute([':line_item_id' => $item['line_item_entry_id']]);
                $item['acceptance_methods'] = $stmt->fetchAll();
            }

            $payment['line_items'] = $line_items;

            // Get file attachments
            $stmt = $this->pdo->prepare("
                SELECT * FROM tbl_payment_entry_file_attachments_registry
                WHERE payment_entry_master_id_fk = :id
                ORDER BY attachment_upload_timestamp DESC
            ");
            $stmt->execute([':id' => $payment_entry_id]);
            $payment['attachments'] = $stmt->fetchAll();

            // Get summary totals
            $stmt = $this->pdo->prepare("
                SELECT * FROM tbl_payment_entry_summary_totals
                WHERE payment_entry_master_id_fk = :id
            ");
            $stmt->execute([':id' => $payment_entry_id]);
            $payment['summary'] = $stmt->fetch();

            // Get audit log
            $stmt = $this->pdo->prepare("
                SELECT * FROM tbl_payment_entry_audit_activity_log
                WHERE payment_entry_id_fk = :id
                ORDER BY audit_action_timestamp_utc DESC
            ");
            $stmt->execute([':id' => $payment_entry_id]);
            $payment['audit_log'] = $stmt->fetchAll();

            return $payment;
        } catch (Exception $e) {
            error_log('Error fetching payment entry: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all payment entries with pagination
     */
    public function getAllPaymentEntries($page = 1, $per_page = 20, $filters = []) {
        try {
            $offset = ($page - 1) * $per_page;
            $query = "SELECT * FROM vw_payment_entry_complete_details WHERE 1=1";
            $params = [];

            // Apply filters
            if (!empty($filters['status'])) {
                $query .= " AND entry_status_current = :status";
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['project_type'])) {
                $query .= " AND project_type_category = :project_type";
                $params[':project_type'] = $filters['project_type'];
            }

            if (!empty($filters['date_from'])) {
                $query .= " AND payment_date_logged >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $query .= " AND payment_date_logged <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $query .= " AND (project_name_reference LIKE :search OR payment_entry_id LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            // Get total count
            $count_stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM (" . $query . ") as cnt");
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];

            // Get paginated results
            $query .= " ORDER BY created_timestamp_utc DESC LIMIT :offset, :per_page";
            $stmt = $this->pdo->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':offset', intval($offset), PDO::PARAM_INT);
            $stmt->bindValue(':per_page', intval($per_page), PDO::PARAM_INT);
            $stmt->execute();

            return [
                'data' => $stmt->fetchAll(),
                'total' => intval($total),
                'page' => intval($page),
                'per_page' => intval($per_page),
                'total_pages' => ceil($total / $per_page)
            ];
        } catch (Exception $e) {
            error_log('Error fetching payment entries: ' . $e->getMessage());
            return ['data' => [], 'total' => 0];
        }
    }

    /**
     * Update payment entry status
     */
    public function updatePaymentStatus($payment_entry_id, $new_status, $reason = null, $user_id) {
        try {
            $this->pdo->beginTransaction();

            // Get current status
            $stmt = $this->pdo->prepare("
                SELECT entry_status_current FROM tbl_payment_entry_master_records
                WHERE payment_entry_id = :id
            ");
            $stmt->execute([':id' => $payment_entry_id]);
            $current = $stmt->fetch();

            if (!$current) {
                throw new Exception('Payment entry not found');
            }

            $old_status = $current['entry_status_current'];

            // Update main table
            $stmt = $this->pdo->prepare("
                UPDATE tbl_payment_entry_master_records
                SET entry_status_current = :status, updated_by_user_id = :user_id
                WHERE payment_entry_id = :id
            ");
            $stmt->execute([
                ':status' => $new_status,
                ':user_id' => $user_id,
                ':id' => $payment_entry_id
            ]);

            // Insert status history
            $stmt = $this->pdo->prepare("
                INSERT INTO tbl_payment_entry_status_transition_history
                (payment_entry_master_id_fk, status_from_previous, status_to_current, status_changed_by_user_id, status_change_reason_notes)
                VALUES (:payment_id, :from_status, :to_status, :user_id, :reason)
            ");
            $stmt->execute([
                ':payment_id' => $payment_entry_id,
                ':from_status' => $old_status,
                ':to_status' => $new_status,
                ':user_id' => $user_id,
                ':reason' => $reason
            ]);

            // Insert audit log
            $stmt = $this->pdo->prepare("
                INSERT INTO tbl_payment_entry_audit_activity_log
                (payment_entry_id_fk, audit_action_type, audit_change_description, audit_performed_by_user_id, audit_ip_address_captured, audit_user_agent_info)
                VALUES (:payment_id, 'status_changed', :description, :user_id, :ip, :agent)
            ");
            $stmt->execute([
                ':payment_id' => $payment_entry_id,
                ':description' => "Status changed from $old_status to $new_status. Reason: $reason",
                ':user_id' => $user_id,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('Error updating payment status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Approve payment entry
     */
    public function approvePayment($payment_entry_id, $authorized_amount = null, $reference_document = null, $user_id) {
        try {
            $this->pdo->beginTransaction();

            // Update status
            $this->updatePaymentStatus($payment_entry_id, 'approved', 'Payment approved', $user_id);

            // Insert approval record
            $stmt = $this->pdo->prepare("
                INSERT INTO tbl_payment_entry_approval_records_final
                (payment_entry_master_id_fk, approved_by_user_id, approval_authorized_amount, approval_reference_document_number, approval_clearance_code)
                VALUES (:payment_id, :user_id, :authorized_amount, :reference_doc, :clearance_code)
            ");
            
            $clearance_code = 'CLR-' . time() . '-' . bin2hex(random_bytes(3));
            
            $stmt->execute([
                ':payment_id' => $payment_entry_id,
                ':user_id' => $user_id,
                ':authorized_amount' => $authorized_amount,
                ':reference_doc' => $reference_document,
                ':clearance_code' => $clearance_code
            ]);

            $this->pdo->commit();
            return ['success' => true, 'clearance_code' => $clearance_code];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('Error approving payment: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reject payment entry
     */
    public function rejectPayment($payment_entry_id, $reason_code, $reason_description, $resubmit_requested = false, $user_id) {
        try {
            $this->pdo->beginTransaction();

            // Update status
            $this->updatePaymentStatus($payment_entry_id, 'rejected', $reason_description, $user_id);

            // Insert rejection record
            $stmt = $this->pdo->prepare("
                INSERT INTO tbl_payment_entry_rejection_reasons_detail
                (payment_entry_master_id_fk, rejection_reason_code, rejection_reason_description, rejected_by_user_id, resubmission_requested)
                VALUES (:payment_id, :reason_code, :reason_description, :user_id, :resubmit)
            ");
            $stmt->execute([
                ':payment_id' => $payment_entry_id,
                ':reason_code' => $reason_code,
                ':reason_description' => $reason_description,
                ':user_id' => $user_id,
                ':resubmit' => $resubmit_requested ? 1 : 0
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('Error rejecting payment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get payment summary statistics
     */
    public function getSummaryStats($date_from = null, $date_to = null) {
        try {
            $query = "SELECT 
                COUNT(*) as total_entries,
                COUNT(DISTINCT created_by_user_id) as total_users,
                SUM(total_amount_grand_aggregate) as total_amount,
                AVG(total_amount_grand_aggregate) as avg_amount,
                SUM(line_items_count) as total_line_items,
                SUM(acceptance_methods_count) as total_methods,
                SUM(total_files_attached) as total_files
            FROM tbl_payment_entry_summary_totals s
            JOIN tbl_payment_entry_master_records m ON s.payment_entry_master_id_fk = m.payment_entry_id
            WHERE 1=1";
            
            $params = [];

            if ($date_from) {
                $query .= " AND m.payment_date_logged >= :date_from";
                $params[':date_from'] = $date_from;
            }

            if ($date_to) {
                $query .= " AND m.payment_date_logged <= :date_to";
                $params[':date_to'] = $date_to;
            }

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log('Error getting summary stats: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get status breakdown
     */
    public function getStatusBreakdown() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    entry_status_current,
                    COUNT(*) as count,
                    SUM(payment_amount_base) as total_amount
                FROM tbl_payment_entry_master_records
                GROUP BY entry_status_current
            ");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error getting status breakdown: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment mode breakdown
     */
    public function getPaymentModeBreakdown() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    payment_mode_selected,
                    COUNT(*) as count,
                    SUM(payment_amount_base) as total_amount
                FROM tbl_payment_entry_master_records
                GROUP BY payment_mode_selected
            ");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error getting payment mode breakdown: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete payment entry (soft delete via status)
     */
    public function archivePayment($payment_entry_id, $reason = null, $user_id) {
        return $this->updatePaymentStatus($payment_entry_id, 'archived', $reason, $user_id);
    }

    /**
     * Get audit trail for payment
     */
    public function getAuditTrail($payment_entry_id, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM tbl_payment_entry_audit_activity_log
                WHERE payment_entry_id_fk = :id
                ORDER BY audit_action_timestamp_utc DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':id', $payment_entry_id);
            $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error getting audit trail: ' . $e->getMessage());
            return [];
        }
    }
}

// Usage Example:
/*
require_once __DIR__ . '/../config/db_connect.php';

$manager = new PaymentEntryManager($pdo);

// Get payment entry
$payment = $manager->getPaymentEntryById(1);

// Get all entries with filter
$entries = $manager->getAllPaymentEntries(1, 20, [
    'status' => 'submitted',
    'date_from' => '2024-01-01'
]);

// Approve payment
$manager->approvePayment(1, 50000, 'VOUCHER-001', $user_id);

// Reject payment
$manager->rejectPayment(1, 'INVALID_PROOF', 'Proof image is unclear', true, $user_id);

// Get statistics
$stats = $manager->getSummaryStats('2024-01-01', '2024-12-31');
*/
?>
