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

        // Prepare the update query
        // For status updates, also update the status_changed_date
        if ($fieldName === 'status' && isset($_POST['updateStatusDate'])) {
            // Check if custom date is provided
            if (isset($_POST['customStatusDate']) && !empty($_POST['customStatusDate'])) {
                $customDate = filter_input(INPUT_POST, 'customStatusDate', FILTER_SANITIZE_STRING);
                
                // Validate date format
                $dateObj = DateTime::createFromFormat('Y-m-d', $customDate);
                if (!$dateObj || $dateObj->format('Y-m-d') !== $customDate) {
                    throw new Exception('Invalid date format');
                }
                
                // Make sure date is not in the future
                $today = new DateTime();
                if ($dateObj > $today) {
                    throw new Exception('Status change date cannot be in the future');
                }
                
                $query = "UPDATE users SET $fieldName = :value, status_changed_date = :custom_date, updated_at = NOW() WHERE id = :id";
                $params = [
                    ':value' => $value,
                    ':custom_date' => $customDate,
                    ':id' => $employeeId
                ];
            } else {
                // Use current datetime if no custom date is provided
                $query = "UPDATE users SET $fieldName = :value, status_changed_date = NOW(), updated_at = NOW() WHERE id = :id";
                $params = [
                    ':value' => $value,
                    ':id' => $employeeId
                ];
            }
        } else {
            $query = "UPDATE users SET $fieldName = :value, updated_at = NOW() WHERE id = :id";
            $params = [
                ':value' => $value,
                ':id' => $employeeId
            ];
        }
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute($params);

        if ($result) {
            // For status updates, include additional response data
            $response = [
                'success' => true,
                'message' => 'Update successful',
                'newValue' => $value
            ];
            
            if ($fieldName === 'status') {
                $response['status'] = $value;
                
                // If we updated the status_changed_date, include it in the response
                if (isset($_POST['updateStatusDate'])) {
                    if (isset($_POST['customStatusDate']) && !empty($_POST['customStatusDate'])) {
                        // Format the custom date for display
                        $dateObj = new DateTime($_POST['customStatusDate']);
                        $response['statusChangedDate'] = $dateObj->format('d M Y');
                    } else {
                        $response['statusChangedDate'] = date('d M Y');
                    }
                }
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