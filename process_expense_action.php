<?php
// Start session for authentication
session_start();

// Debug log
$log_file = 'expense_action_log.txt';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - SESSION data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Senior Manager (Site)', 'Purchase Manager', 'Accountant', 'HR Manager', 'HR'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if required parameters are provided
// Accept either 'action' or 'action_type' for backward compatibility
if (!isset($_POST['expense_id']) || (!isset($_POST['action']) && !isset($_POST['action_type']))) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

$expense_id = intval($_POST['expense_id']);
// Use action_type if available, otherwise fall back to action
$action = isset($_POST['action_type']) ? $_POST['action_type'] : $_POST['action']; // 'approve' or 'reject'
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Validate action
if ($action !== 'approve' && $action !== 'reject') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Convert action to status value
$status_value = ($action === 'approve') ? 'approved' : 'rejected';

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Special case for Purchase Manager who also acts as Accountant
    if ($role === 'Purchase Manager') {
        // Update both manager and accountant status fields
        $stmt = $conn->prepare("
            UPDATE travel_expenses
            SET accountant_status = ?,
                accountant_reason = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssis", $status_value, $notes, $user_id, $expense_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Expense not found or no changes made');
        }
    } else {
        // Determine which status field to update based on the user's role
        $status_field = '';
        $reason_field = '';
        
        if ($role === 'Senior Manager (Site)') {
            $status_field = 'manager_status';
            $reason_field = 'manager_reason';
        } elseif ($role === 'Accountant') {
            $status_field = 'accountant_status';
            $reason_field = 'accountant_reason';
        } elseif ($role === 'HR Manager' || $role === 'HR') {
            $status_field = 'hr_status';
            $reason_field = 'hr_reason';
        }
        
        if (empty($status_field)) {
            throw new Exception('Role not configured for expense approval');
        }
        
        // Update the specific status field and reason
        $stmt = $conn->prepare("
            UPDATE travel_expenses
            SET $status_field = ?,
                $reason_field = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssis", $status_value, $notes, $user_id, $expense_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Expense not found or no changes made');
        }
    }
    
    // Log the approval/rejection
    $log_stmt = $conn->prepare("
        INSERT INTO expense_action_logs 
        (expense_id, user_id, action_type, notes, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    if ($log_stmt) {
        $log_stmt->bind_param("isss", $expense_id, $user_id, $action, $notes);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    // The main status field will be updated automatically by the database trigger
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "Expense {$action}d successfully",
        'status' => $status_value,
        'expense_id' => $expense_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 