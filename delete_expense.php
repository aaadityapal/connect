<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Check if ID parameter exists
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid expense ID.";
    header("Location: view_travel_expenses.php");
    exit();
}

$expense_id = intval($_GET['id']);

// First check if the expense exists and belongs to the current user
$check_stmt = $conn->prepare("
    SELECT id, status FROM travel_expenses 
    WHERE id = ? AND user_id = ?
");

if (!$check_stmt) {
    $_SESSION['error_message'] = "Database error: " . $conn->error;
    header("Location: view_travel_expenses.php");
    exit();
}

$check_stmt->bind_param("ii", $expense_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Expense not found or you don't have permission to delete it.";
    header("Location: view_travel_expenses.php");
    exit();
}

$expense = $result->fetch_assoc();
$check_stmt->close();

// Only allow deletion if the expense is in 'pending' status
if ($expense['status'] !== 'pending') {
    $_SESSION['error_message'] = "Only pending expenses can be deleted.";
    header("Location: view_travel_expenses.php");
    exit();
}

// Start a transaction
$conn->begin_transaction();

try {
    // First delete any approval records
    $delete_approval_stmt = $conn->prepare("
        DELETE FROM travel_expense_approvals 
        WHERE expense_id = ?
    ");
    
    if (!$delete_approval_stmt) {
        throw new Exception("Error preparing approval deletion: " . $conn->error);
    }
    
    $delete_approval_stmt->bind_param("i", $expense_id);
    $delete_approval_stmt->execute();
    $delete_approval_stmt->close();
    
    // Then delete any attachments
    $delete_attachment_stmt = $conn->prepare("
        DELETE FROM travel_expense_attachments 
        WHERE expense_id = ?
    ");
    
    if (!$delete_attachment_stmt) {
        throw new Exception("Error preparing attachment deletion: " . $conn->error);
    }
    
    $delete_attachment_stmt->bind_param("i", $expense_id);
    $delete_attachment_stmt->execute();
    $delete_attachment_stmt->close();
    
    // Finally delete the expense
    $delete_expense_stmt = $conn->prepare("
        DELETE FROM travel_expenses 
        WHERE id = ? AND user_id = ?
    ");
    
    if (!$delete_expense_stmt) {
        throw new Exception("Error preparing expense deletion: " . $conn->error);
    }
    
    $delete_expense_stmt->bind_param("ii", $expense_id, $user_id);
    $delete_expense_stmt->execute();
    
    if ($delete_expense_stmt->affected_rows === 0) {
        throw new Exception("Expense not deleted. Please try again.");
    }
    
    $delete_expense_stmt->close();
    
    // Commit the transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['success_message'] = "Expense deleted successfully.";
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    
    // Set error message
    $_SESSION['error_message'] = "Error deleting expense: " . $e->getMessage();
}

// Redirect back to the expenses list
header("Location: view_travel_expenses.php");
exit();
?>