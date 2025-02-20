<?php
session_start();
require_once 'config.php';

// Check if user is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and validate input
        $employeeId = filter_input(INPUT_POST, 'employeeId', FILTER_SANITIZE_NUMBER_INT);
        $fieldName = filter_input(INPUT_POST, 'fieldName', FILTER_SANITIZE_STRING);
        $value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (!$employeeId || !$fieldName || !$value) {
            throw new Exception('Invalid input parameters');
        }

        // List of allowed fields to update
        $allowedFields = [
            'position', 'email', 'phone', 'dob', 'gender', 'designation',
            'address', 'city', 'state', 'country', 'postal_code',
            'emergency_contact_name', 'emergency_contact_phone',
            'reporting_manager', 'joining_date', 'status'
        ];

        if (!in_array($fieldName, $allowedFields)) {
            throw new Exception('Invalid field name');
        }

        // Add validation for status field
        if ($fieldName === 'status') {
            if (!in_array($value, ['active', 'inactive'])) {
                throw new Exception('Invalid status value');
            }
        }
        // Existing manager validation
        else if ($fieldName === 'reporting_manager') {
            $managerQuery = "SELECT COUNT(*) FROM users 
                             WHERE reporting_manager = :manager_name 
                             AND deleted_at IS NULL";
            $managerStmt = $pdo->prepare($managerQuery);
            $managerStmt->execute([':manager_name' => $value]);
            
            if ($managerStmt->fetchColumn() == 0) {
                throw new Exception('Invalid manager selected');
            }
        }

        // Prepare and execute the update query
        $query = "UPDATE users SET $fieldName = :value, updated_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($query);
        
        $result = $stmt->execute([
            ':value' => $value,
            ':id' => $employeeId
        ]);

        if ($result) {
            // For status updates, include additional response data
            $response = [
                'success' => true,
                'message' => 'Update successful',
                'newValue' => $value
            ];
            
            if ($fieldName === 'status') {
                $response['status'] = $value;
            }
            
            echo json_encode($response);
        } else {
            throw new Exception('Database update failed');
        }

    } catch (Exception $e) {
        error_log('Update Error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error updating information: ' . $e->getMessage()
        ]);
    }
    exit;
} 