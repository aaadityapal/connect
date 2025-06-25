<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

// Include config file
require_once 'config/db_connect.php';

// Process the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Mark expense as paid
        if ($action === 'mark_paid' && isset($_POST['expense_id'])) {
            try {
                $expenseId = $_POST['expense_id'];
                $currentDate = date('Y-m-d H:i:s');
                $userId = $_SESSION['user_id']; // User who marked as paid
                
                // Check if the expense exists and is approved
                $checkQuery = "SELECT id, amount FROM travel_expenses 
                               WHERE id = ? AND (manager_status = 'Approved' OR status = 'Approved')";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([$expenseId]);
                
                if ($checkStmt->rowCount() === 0) {
                    echo json_encode(['success' => false, 'error' => 'Expense not found or not approved']);
                    exit();
                }
                
                $expense = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // Update the expense record to mark as paid
                $updateQuery = "UPDATE travel_expenses 
                                SET payment_status = 'Paid', 
                                    paid_on_date = ?, 
                                    paid_by = ?, 
                                    updated_at = ?
                                WHERE id = ?";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([$currentDate, $userId, $currentDate, $expenseId]);
                
                // Log the payment
                $logQuery = "INSERT INTO expense_payment_logs 
                            (expense_id, amount, paid_on_date, paid_by, created_at) 
                            VALUES (?, ?, ?, ?, ?)";
                $logStmt = $pdo->prepare($logQuery);
                $logStmt->execute([$expenseId, $expense['amount'], $currentDate, $userId, $currentDate]);
                
                echo json_encode(['success' => true, 'message' => 'Expense marked as paid successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action or missing parameters']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No action specified']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?> 