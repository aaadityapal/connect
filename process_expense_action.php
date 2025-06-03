<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Senior Manager (Site)', 'Purchase Manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if required parameters are provided
if (!isset($_POST['expense_id']) || !is_numeric($_POST['expense_id']) || !isset($_POST['action'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

$expense_id = intval($_POST['expense_id']);
$action = $_POST['action'];
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$manager_id = $_SESSION['user_id'];
$status = ($action === 'approve') ? 'approved' : 'rejected';

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Update expense status
    $stmt = $conn->prepare("
        UPDATE travel_expenses 
        SET status = ?, 
            approved_by = ?, 
            approval_notes = ?,
            approval_date = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param("sisi", $status, $manager_id, $notes, $expense_id);
    $stmt->execute();
    
    // Check if any rows were affected
    if ($stmt->affected_rows === 0) {
        throw new Exception("Expense not found or already processed");
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 