<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if expense ID is provided
if (!isset($_POST['expense_id']) || !is_numeric($_POST['expense_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid expense ID']);
    exit();
}

$expense_id = intval($_POST['expense_id']);
$user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First check if the expense belongs to the user and is in pending status
    $check_stmt = $conn->prepare("
        SELECT status, bill_file_path FROM travel_expenses 
        WHERE id = ? AND user_id = ?
    ");
    
    $check_stmt->bind_param("ii", $expense_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Only allow deletion of pending expenses
        if ($row['status'] !== 'pending') {
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Only pending expenses can be deleted']);
            exit();
        }
        
        // Store bill file path to delete it after database record is deleted
        $bill_file_path = $row['bill_file_path'];
        
        // Delete the expense record
        $delete_stmt = $conn->prepare("DELETE FROM travel_expenses WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $expense_id, $user_id);
        $delete_stmt->execute();
        
        if ($delete_stmt->affected_rows > 0) {
            // Delete the bill file if it exists
            if (!empty($bill_file_path) && file_exists($bill_file_path)) {
                unlink($bill_file_path);
            }
            
            $conn->commit();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete expense']);
        }
    } else {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Expense not found or does not belong to you']);
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
