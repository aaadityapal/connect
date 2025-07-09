<?php
// Database functions for project payouts
// The database connection is already included in the main file

/**
 * Add a new project payout
 * 
 * @param PDO $pdo Database connection
 * @param array $data Project data
 * @return int|bool The ID of the inserted record or false on failure
 */
function addProjectPayout($pdo, $data) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if we have multiple payments
        $hasMultiplePayments = isset($data['payment_details']) && count($data['payment_details']) > 0;
        
        // Debug logging
        error_log("Adding project payout with multiple payments: " . ($hasMultiplePayments ? 'Yes' : 'No'));
        if ($hasMultiplePayments) {
            error_log("Payment details: " . print_r($data['payment_details'], true));
        }
        
        // Insert into project_payouts table
        $stmt = $pdo->prepare("
            INSERT INTO project_payouts (
                project_name, project_type, client_name, project_date, 
                amount, payment_mode, project_stage, remaining_amount, has_multiple_payments
            ) VALUES (
                :project_name, :project_type, :client_name, :project_date, 
                :amount, :payment_mode, :project_stage, :remaining_amount, :has_multiple_payments
            )
        ");
        
        $stmt->bindParam(':project_name', $data['project_name'], PDO::PARAM_STR);
        $stmt->bindParam(':project_type', $data['project_type'], PDO::PARAM_STR);
        $stmt->bindParam(':client_name', $data['client_name'], PDO::PARAM_STR);
        $stmt->bindParam(':project_date', $data['project_date'], PDO::PARAM_STR);
        $stmt->bindParam(':amount', $data['amount'], PDO::PARAM_STR);
        $stmt->bindParam(':payment_mode', $data['payment_mode'], PDO::PARAM_STR);
        $stmt->bindParam(':project_stage', $data['project_stage'], PDO::PARAM_STR);
        $stmt->bindParam(':remaining_amount', $data['remaining_amount'], PDO::PARAM_STR);
        $stmt->bindParam(':has_multiple_payments', $hasMultiplePayments, PDO::PARAM_BOOL);
        
        $stmt->execute();
        $projectId = $pdo->lastInsertId();
        
        // If we have multiple payment details, insert them
        if ($hasMultiplePayments) {
            foreach ($data['payment_details'] as $payment) {
                $stmtDetail = $pdo->prepare("
                    INSERT INTO stage_payment_details (
                        project_id, stage, payment_date, payment_amount, payment_mode
                    ) VALUES (
                        :project_id, :stage, :payment_date, :payment_amount, :payment_mode
                    )
                ");
                
                $stmtDetail->bindParam(':project_id', $projectId, PDO::PARAM_INT);
                $stmtDetail->bindParam(':stage', $data['project_stage'], PDO::PARAM_STR);
                $stmtDetail->bindParam(':payment_date', $payment['date'], PDO::PARAM_STR);
                $stmtDetail->bindParam(':payment_amount', $payment['amount'], PDO::PARAM_STR);
                $stmtDetail->bindParam(':payment_mode', $payment['mode'], PDO::PARAM_STR);
                
                // Debug logging for each payment detail
                error_log("Inserting payment detail - Date: {$payment['date']}, Amount: {$payment['amount']}, Mode: {$payment['mode']}");
                
                $stmtDetail->execute();
                
                // Debug logging for insert result
                error_log("Payment detail inserted with ID: " . $pdo->lastInsertId());
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        return $projectId;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error adding project payout: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all project payouts
 * 
 * @param PDO $pdo Database connection
 * @return array Array of project payouts
 */
function getAllProjectPayouts($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM project_payouts ORDER BY created_at DESC");
        $stmt->execute();
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment details for projects with multiple payments
        foreach ($projects as &$project) {
            if ($project['has_multiple_payments']) {
                $stmtDetails = $pdo->prepare("SELECT * FROM stage_payment_details WHERE project_id = :project_id ORDER BY payment_date");
                $stmtDetails->bindParam(':project_id', $project['id'], PDO::PARAM_INT);
                $stmtDetails->execute();
                $project['payment_details'] = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        return $projects;
    } catch (PDOException $e) {
        error_log("Error getting all project payouts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a specific project payout by ID
 * 
 * @param PDO $pdo Database connection
 * @param int $id Project payout ID
 * @return array|bool Project payout data or false if not found
 */
function getProjectPayoutById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM project_payouts WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If project has multiple payments, get the payment details
        if ($project && $project['has_multiple_payments']) {
            $stmtDetails = $pdo->prepare("SELECT * FROM stage_payment_details WHERE project_id = :project_id ORDER BY payment_date");
            $stmtDetails->bindParam(':project_id', $id, PDO::PARAM_INT);
            $stmtDetails->execute();
            $project['payment_details'] = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $project;
    } catch (PDOException $e) {
        error_log("Error getting project payout: " . $e->getMessage());
        return false;
    }
}

/**
 * Update a project payout
 * 
 * @param PDO $pdo Database connection
 * @param int $id Project payout ID
 * @param array $data Project data
 * @return bool True on success, false on failure
 */
function updateProjectPayout($pdo, $id, $data) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if we have multiple payments
        $hasMultiplePayments = isset($data['payment_details']) && count($data['payment_details']) > 0;
        
        // Debug logging
        error_log("Updating project payout ID: $id with multiple payments: " . ($hasMultiplePayments ? 'Yes' : 'No'));
        if ($hasMultiplePayments) {
            error_log("Payment details: " . print_r($data['payment_details'], true));
        }
        
        // Update project_payouts table
        $stmt = $pdo->prepare("
            UPDATE project_payouts SET
                project_name = :project_name,
                project_type = :project_type,
                client_name = :client_name,
                project_date = :project_date,
                amount = :amount,
                payment_mode = :payment_mode,
                project_stage = :project_stage,
                remaining_amount = :remaining_amount,
                has_multiple_payments = :has_multiple_payments,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->bindParam(':project_name', $data['project_name'], PDO::PARAM_STR);
        $stmt->bindParam(':project_type', $data['project_type'], PDO::PARAM_STR);
        $stmt->bindParam(':client_name', $data['client_name'], PDO::PARAM_STR);
        $stmt->bindParam(':project_date', $data['project_date'], PDO::PARAM_STR);
        $stmt->bindParam(':amount', $data['amount'], PDO::PARAM_STR);
        $stmt->bindParam(':payment_mode', $data['payment_mode'], PDO::PARAM_STR);
        $stmt->bindParam(':project_stage', $data['project_stage'], PDO::PARAM_STR);
        $stmt->bindParam(':remaining_amount', $data['remaining_amount'], PDO::PARAM_STR);
        $stmt->bindParam(':has_multiple_payments', $hasMultiplePayments, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // If we have multiple payment details, update them
        if ($hasMultiplePayments) {
            // Delete old payment details
            $stmtDelete = $pdo->prepare("DELETE FROM stage_payment_details WHERE project_id = :project_id");
            $stmtDelete->bindParam(':project_id', $id, PDO::PARAM_INT);
            $stmtDelete->execute();
            error_log("Deleted existing payment details for project ID: $id");
            
            // Insert new payment details
            foreach ($data['payment_details'] as $payment) {
                $stmtDetail = $pdo->prepare("
                    INSERT INTO stage_payment_details (
                        project_id, stage, payment_date, payment_amount, payment_mode
                    ) VALUES (
                        :project_id, :stage, :payment_date, :payment_amount, :payment_mode
                    )
                ");
                
                $stmtDetail->bindParam(':project_id', $id, PDO::PARAM_INT);
                $stmtDetail->bindParam(':stage', $data['project_stage'], PDO::PARAM_STR);
                $stmtDetail->bindParam(':payment_date', $payment['date'], PDO::PARAM_STR);
                $stmtDetail->bindParam(':payment_amount', $payment['amount'], PDO::PARAM_STR);
                $stmtDetail->bindParam(':payment_mode', $payment['mode'], PDO::PARAM_STR);
                
                // Debug logging for each payment detail
                error_log("Inserting payment detail - Date: {$payment['date']}, Amount: {$payment['amount']}, Mode: {$payment['mode']}");
                
                $stmtDetail->execute();
                
                // Debug logging for insert result
                error_log("Payment detail inserted with ID: " . $pdo->lastInsertId());
            }
        } else {
            // If no multiple payments, delete any existing payment details
            $stmtDelete = $pdo->prepare("DELETE FROM stage_payment_details WHERE project_id = :project_id");
            $stmtDelete->bindParam(':project_id', $id, PDO::PARAM_INT);
            $stmtDelete->execute();
            error_log("Deleted existing payment details for project ID: $id (no multiple payments)");
        }
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error updating project payout: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a project payout
 * 
 * @param PDO $pdo Database connection
 * @param int $id Project payout ID
 * @return bool True on success, false on failure
 */
function deleteProjectPayout($pdo, $id) {
    try {
        // Note: Due to foreign key constraint with ON DELETE CASCADE,
        // deleting from project_payouts will automatically delete related stage_payment_details
        $stmt = $pdo->prepare("DELETE FROM project_payouts WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error deleting project payout: " . $e->getMessage());
        return false;
    }
}
?> 