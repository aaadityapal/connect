<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Senior Manager (Site)', 'Admin', 'HR Manager', 'Purchase Manager', 'HR'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'You do not have permission to perform this action']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get POST data
$expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
$field = isset($_POST['field']) ? $_POST['field'] : '';
$value = isset($_POST['value']) ? $_POST['value'] : '';

// Validate expense ID
if ($expense_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid expense ID']);
    exit();
}

// Validate field name
$allowed_fields = ['amount', 'mode_of_transport', 'distance'];
if (!in_array($field, $allowed_fields)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid field name']);
    exit();
}

// Validate field value based on field type
$error = '';
switch ($field) {
    case 'amount':
        if (!is_numeric($value) || floatval($value) <= 0) {
            $error = 'Amount must be a positive number';
        }
        break;
    case 'distance':
        if (!is_numeric($value) || floatval($value) < 0) {
            $error = 'Distance must be a non-negative number';
        }
        break;
    case 'mode_of_transport':
        if (empty($value)) {
            $error = 'Mode of transport cannot be empty';
        }
        break;
}

if (!empty($error)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $error]);
    exit();
}

// Prepare value for database based on field type
$db_value = $value;
if ($field === 'amount' || $field === 'distance') {
    $db_value = floatval($value);
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Update the field in the database
    $stmt = $conn->prepare("UPDATE travel_expenses SET {$field} = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param($field === 'amount' || $field === 'distance' ? "di" : "si", $db_value, $expense_id);
    $stmt->execute();
    
    // Check if update was successful
    if ($stmt->affected_rows > 0) {
        // Log the change
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'] ?? 'Unknown';
        $log_stmt = $conn->prepare("INSERT INTO expense_edit_logs (expense_id, user_id, username, field_name, old_value, new_value, edit_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        // Get the old value first
        $old_value_stmt = $conn->prepare("SELECT {$field} FROM travel_expenses WHERE id = ?");
        $old_value_stmt->bind_param("i", $expense_id);
        $old_value_stmt->execute();
        $old_value_result = $old_value_stmt->get_result();
        $old_value = '';
        
        if ($row = $old_value_result->fetch_assoc()) {
            $old_value = $row[$field];
        }
        
        // Insert log entry
        $log_stmt->bind_param("iissss", $expense_id, $user_id, $username, $field, $old_value, $value);
        $log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Field updated successfully']);
        exit();
    } else {
        // No rows affected - could be because the value didn't change or expense doesn't exist
        $check_stmt = $conn->prepare("SELECT id FROM travel_expenses WHERE id = ?");
        $check_stmt->bind_param("i", $expense_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception('Expense not found');
        } else {
            // Expense exists but value didn't change
            $conn->commit();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'No changes were made']);
            exit();
        }
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>