<?php
// Database functions for manager payments

/**
 * Add or update manager payment status
 * 
 * @param PDO $pdo Database connection
 * @param int $projectId Project ID
 * @param int $managerId Manager ID
 * @param float $amount Payment amount
 * @param float $commissionRate Commission rate percentage
 * @param string $status Payment status (pending or approved)
 * @return bool True on success, false on failure
 */
function saveManagerPayment($pdo, $projectId, $managerId, $amount, $commissionRate, $status = 'pending') {
    try {
        // Check if record exists
        $stmt = $pdo->prepare("
            SELECT id FROM manager_payments 
            WHERE project_id = :project_id AND manager_id = :manager_id
        ");
        
        $stmt->execute([
            ':project_id' => $projectId,
            ':manager_id' => $managerId
        ]);
        
        $existingRecord = $stmt->fetch();
        
        if ($existingRecord) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE manager_payments SET
                    amount = :amount,
                    commission_rate = :commission_rate,
                    payment_status = :payment_status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE project_id = :project_id AND manager_id = :manager_id
            ");
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO manager_payments (
                    project_id,
                    manager_id,
                    amount,
                    commission_rate,
                    payment_status
                ) VALUES (
                    :project_id,
                    :manager_id,
                    :amount,
                    :commission_rate,
                    :payment_status
                )
            ");
        }
        
        return $stmt->execute([
            ':project_id' => $projectId,
            ':manager_id' => $managerId,
            ':amount' => $amount,
            ':commission_rate' => $commissionRate,
            ':payment_status' => $status
        ]);
    } catch (PDOException $e) {
        error_log("Error saving manager payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Update manager payment status
 * 
 * @param PDO $pdo Database connection
 * @param int $projectId Project ID
 * @param int $managerId Manager ID
 * @param string $status Payment status (pending or approved)
 * @return bool True on success, false on failure
 */
function updatePaymentStatus($pdo, $projectId, $managerId, $status = 'approved') {
    try {
        $stmt = $pdo->prepare("
            UPDATE manager_payments SET
                payment_status = :payment_status,
                updated_at = CURRENT_TIMESTAMP
            WHERE project_id = :project_id AND manager_id = :manager_id
        ");
        
        return $stmt->execute([
            ':project_id' => $projectId,
            ':manager_id' => $managerId,
            ':payment_status' => $status
        ]);
    } catch (PDOException $e) {
        error_log("Error updating payment status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get payment status for a manager and project
 * 
 * @param PDO $pdo Database connection
 * @param int $projectId Project ID
 * @param int $managerId Manager ID
 * @return string|bool Payment status or false if not found
 */
function getPaymentStatus($pdo, $projectId, $managerId) {
    try {
        $stmt = $pdo->prepare("
            SELECT payment_status FROM manager_payments 
            WHERE project_id = :project_id AND manager_id = :manager_id
        ");
        
        $stmt->execute([
            ':project_id' => $projectId,
            ':manager_id' => $managerId
        ]);
        
        $result = $stmt->fetch();
        return $result ? $result['payment_status'] : 'pending';
    } catch (PDOException $e) {
        error_log("Error getting payment status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all payments for a project
 * 
 * @param PDO $pdo Database connection
 * @param int $projectId Project ID
 * @return array Array of payment records
 */
function getProjectPayments($pdo, $projectId) {
    try {
        $stmt = $pdo->prepare("
            SELECT mp.*, u.username, u.designation, u.role 
            FROM manager_payments mp
            JOIN users u ON mp.manager_id = u.id
            WHERE mp.project_id = :project_id
        ");
        
        $stmt->execute([':project_id' => $projectId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching project payments: " . $e->getMessage());
        return [];
    }
}
?> 