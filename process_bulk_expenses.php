<?php
// Start session for authentication
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Check if user has the appropriate role
$allowed_roles = ['Senior Manager (Site)', 'Purchase Manager', 'HR', 'Senior Manager (Studio)'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include database connection
include_once('includes/db_connect.php');

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if we have valid data
if (!isset($data['expense_ids']) || !isset($data['action']) || 
    !is_array($data['expense_ids']) || empty($data['expense_ids']) ||
    ($data['action'] !== 'approve' && $data['action'] !== 'reject')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Get expense IDs and action
$expense_ids = $data['expense_ids'];
$action = $data['action'];

// Map action to status
$status = ($action === 'approve') ? 'Approved' : 'Rejected';

// Prepare placeholders for SQL query
$placeholders = implode(',', array_fill(0, count($expense_ids), '?'));

// Get user role for specific status update
$user_role = $_SESSION['role'];
$status_field = '';

// Determine which status field to update based on user role
if (strpos($user_role, 'Manager') !== false || $user_role === 'Senior Manager (Studio)') {
    $status_field = 'manager_status';
} elseif ($user_role === 'HR') {
    $status_field = 'hr_status';
} else {
    $status_field = 'accountant_status';
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update specific role status
    $role_status_query = "UPDATE travel_expenses SET $status_field = ? WHERE id IN ($placeholders) AND status = 'Pending'";
    $stmt = $conn->prepare($role_status_query);
    
    // Create parameter array with status as first parameter followed by expense IDs
    $params = array_merge([$status], $expense_ids);
    
    // Bind parameters dynamically
    $types = 's' . str_repeat('i', count($expense_ids));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    // Check if all role statuses are aligned to update the main status
    if ($status === 'Approved') {
        // For approval, check if all required roles have approved
        $update_main_status_query = "UPDATE travel_expenses 
                                    SET status = 'Approved' 
                                    WHERE id IN ($placeholders) 
                                    AND manager_status = 'Approved' 
                                    AND accountant_status = 'Approved' 
                                    AND hr_status = 'Approved'";
    } else {
        // For rejection, if any role rejects, the expense is rejected
        $update_main_status_query = "UPDATE travel_expenses 
                                    SET status = 'Rejected' 
                                    WHERE id IN ($placeholders) 
                                    AND (manager_status = 'Rejected' 
                                         OR accountant_status = 'Rejected' 
                                         OR hr_status = 'Rejected')";
    }
    
    $stmt_main = $conn->prepare($update_main_status_query);
    $stmt_main->bind_param(str_repeat('i', count($expense_ids)), ...$expense_ids);
    $stmt_main->execute();
    
    // Update the updated_by field and reason field based on role
    $user_id = $_SESSION['user_id'];
    $reason_field = '';
    
    // Determine which reason field to update
    if (strpos($user_role, 'Manager') !== false || $user_role === 'Senior Manager (Studio)') {
        $reason_field = 'manager_reason';
    } elseif ($user_role === 'HR') {
        $reason_field = 'hr_reason';
    } else {
        $reason_field = 'accountant_reason';
    }
    
    // Update the updated_by and reason fields
    $update_reason_query = "UPDATE travel_expenses SET updated_by = ?, $reason_field = ? WHERE id IN ($placeholders)";
    $reason_params = array_merge(
        [$user_id, $action === 'approve' ? 'Bulk approved' : 'Bulk rejected'],
        $expense_ids
    );
    
    $reason_types = 'is' . str_repeat('i', count($expense_ids));
    $reason_stmt = $conn->prepare($update_reason_query);
    $reason_stmt->bind_param($reason_types, ...$reason_params);
    $reason_stmt->execute();
    
    // Note: If you have an activity_log table, you can uncomment and modify this code
    /*
    $log_query = "INSERT INTO activity_log (user_id, activity_type, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)";
    
    foreach ($expense_ids as $expense_id) {
        $log_stmt = $conn->prepare($log_query);
        $details = json_encode([
            'action' => $action,
            'status' => $status,
            'role' => $user_role,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $log_stmt->bind_param('issss', $user_id, $action, 'travel_expense', $expense_id, $details);
        $log_stmt->execute();
    }
    */
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => count($expense_ids) . ' expense(s) ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully',
        'count' => count($expense_ids)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => 'Error processing expenses: ' . $e->getMessage()
    ]);
} 