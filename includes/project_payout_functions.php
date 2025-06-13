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
        // Debug log the data received
        error_log("addProjectPayout - Data received: " . json_encode($data));
        error_log("addProjectPayout - Remaining amount: " . (isset($data['remaining_amount']) ? $data['remaining_amount'] : 'not set'));
        
        $stmt = $pdo->prepare("
            INSERT INTO project_payouts (
                project_name, 
                project_type, 
                client_name, 
                project_date, 
                amount, 
                payment_mode, 
                project_stage,
                remaining_amount
            ) VALUES (
                :project_name,
                :project_type,
                :client_name,
                :project_date,
                :amount,
                :payment_mode,
                :project_stage,
                :remaining_amount
            )
        ");
        
        // Set remaining amount if provided, otherwise default to 0
        $remainingAmount = isset($data['remaining_amount']) ? $data['remaining_amount'] : 0;
        
        $stmt->execute([
            ':project_name' => $data['project_name'],
            ':project_type' => $data['project_type'],
            ':client_name' => $data['client_name'],
            ':project_date' => $data['project_date'],
            ':amount' => $data['amount'],
            ':payment_mode' => $data['payment_mode'],
            ':project_stage' => $data['project_stage'],
            ':remaining_amount' => $remainingAmount
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
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
        $stmt = $pdo->query("
            SELECT * FROM project_payouts 
            ORDER BY project_date DESC
        ");
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching project payouts: " . $e->getMessage());
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
        $stmt = $pdo->prepare("
            SELECT * FROM project_payouts 
            WHERE id = :id
        ");
        
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching project payout: " . $e->getMessage());
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
        // Log the update operation for debugging
        error_log("Updating project ID: $id with data: " . json_encode($data));
        error_log("updateProjectPayout - Remaining amount: " . (isset($data['remaining_amount']) ? $data['remaining_amount'] : 'not set'));
        
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
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        // Set remaining amount if provided, otherwise keep existing value
        $remainingAmount = isset($data['remaining_amount']) ? $data['remaining_amount'] : 0;
        
        $params = [
            ':id' => $id,
            ':project_name' => $data['project_name'],
            ':project_type' => $data['project_type'],
            ':client_name' => $data['client_name'],
            ':project_date' => $data['project_date'],
            ':amount' => $data['amount'],
            ':payment_mode' => $data['payment_mode'],
            ':project_stage' => $data['project_stage'],
            ':remaining_amount' => $remainingAmount
        ];
        
        $result = $stmt->execute($params);
        
        // Log the result
        error_log("Update result: " . ($result ? "Success" : "Failed") . ", Rows affected: " . $stmt->rowCount());
        
        return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
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
        $stmt = $pdo->prepare("
            DELETE FROM project_payouts 
            WHERE id = :id
        ");
        
        return $stmt->execute([':id' => $id]);
    } catch (PDOException $e) {
        error_log("Error deleting project payout: " . $e->getMessage());
        return false;
    }
}
?> 