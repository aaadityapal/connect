<?php
// Start session for authentication
session_start();

// Debug log
$log_file = 'expense_action_log.txt';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Request received in process_expense_action.php\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - SESSION data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Senior Manager (Site)', 'Purchase Manager', 'Accountant', 'HR Manager', 'HR', 'Senior Manager (Studio)'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

// Include database connection
require_once 'includes/db_connect.php';

// Check if required parameters are provided
// Accept either 'action' or 'action_type' for backward compatibility
if (!isset($_POST['expense_id'])) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Error: Missing expense_id parameter\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing expense_id parameter']);
    exit();
}

if (!isset($_POST['action_type']) && !isset($_POST['action'])) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Error: Missing action_type parameter\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing action_type parameter']);
    exit();
}

$expense_id = intval($_POST['expense_id']);
// Use action_type if available, otherwise fall back to action
$action = isset($_POST['action_type']) ? $_POST['action_type'] : $_POST['action']; // 'approve' or 'reject'
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get all expense IDs for batch processing
$all_expense_ids = [];
if (isset($_POST['all_expense_ids']) && !empty($_POST['all_expense_ids'])) {
    try {
        $all_expense_ids = json_decode($_POST['all_expense_ids'], true);
        if (!is_array($all_expense_ids)) {
            $all_expense_ids = [$expense_id]; // Fallback to single ID
        }
    } catch (Exception $e) {
        $all_expense_ids = [$expense_id]; // Fallback to single ID
    }
} else {
    $all_expense_ids = [$expense_id]; // Default to single ID
}

// Log the expense IDs being processed
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Processing expense IDs: " . json_encode($all_expense_ids) . "\n", FILE_APPEND);

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
    
    // Determine which status field to update based on the user's role
    $status_field = '';
    $reason_field = '';
    
    if ($role === 'Senior Manager (Site)' || $role === 'Senior Manager (Studio)') {
        $status_field = 'manager_status';
        $reason_field = 'manager_reason';
    } elseif ($role === 'Purchase Manager' || $role === 'Accountant') {
        $status_field = 'accountant_status';
        $reason_field = 'accountant_reason';
    } elseif ($role === 'HR Manager' || $role === 'HR') {
        $status_field = 'hr_status';
        $reason_field = 'hr_reason';
    }
    
    if (empty($status_field)) {
        throw new Exception('Role not configured for expense approval');
    }
    
    // Log the status field being updated
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Updating status field: $status_field to value: $status_value\n", FILE_APPEND);
    
    // Count successful updates
    $success_count = 0;
    $total_count = count($all_expense_ids);
    
    // Process each expense ID
    foreach ($all_expense_ids as $id) {
        $id = intval($id);
        
        // Update the specific status field and reason
        $sql = "UPDATE travel_expenses 
                SET $status_field = ?, 
                    $reason_field = ?, 
                    updated_by = ?, 
                    updated_at = NOW() 
                WHERE id = ?";
                
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error . " for SQL: $sql");
        }
        
        $stmt->bind_param("ssis", $status_value, $notes, $user_id, $id);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        if ($stmt->affected_rows > 0) {
            $success_count++;
            
            // Log the approval/rejection
            $log_sql = "INSERT INTO expense_action_logs 
                        (expense_id, user_id, action_type, notes, created_at)
                        VALUES (?, ?, ?, ?, NOW())";
                        
            $log_stmt = $conn->prepare($log_sql);
            
            if ($log_stmt) {
                $log_stmt->bind_param("isss", $id, $user_id, $action, $notes);
                $log_stmt->execute();
                $log_stmt->close();
            }
        }
        
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log the success
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Successfully processed $success_count out of $total_count expenses\n", FILE_APPEND);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "$success_count out of $total_count expenses {$action}d successfully",
        'status' => $status_value,
        'processed_count' => $success_count,
        'total_count' => $total_count
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