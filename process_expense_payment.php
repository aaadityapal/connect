<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session variables
error_log("Session variables: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

// Skip role check - allow any logged-in user to access the functionality
// This is a temporary fix until the proper role management is implemented

// Include database connection
require_once 'config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get the action
$action = $_POST['action'] ?? '';

// Process based on action
switch ($action) {
    case 'mark_paid':
        markExpenseAsPaid();
        break;
    case 'mark_multiple_paid':
        markMultipleExpensesAsPaid();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Mark a single expense as paid
 */
function markExpenseAsPaid() {
    global $pdo;
    
    // Get parameters
    $expenseId = $_POST['expense_id'] ?? 0;
    $amountPaid = $_POST['amount_paid'] ?? 0;
    
    // Validate input
    if (empty($expenseId) || $amountPaid <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid expense ID or amount']);
        return;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update the expense record
        $updateQuery = "UPDATE travel_expenses 
                       SET payment_status = 'Paid',
                           paid_on_date = NOW(),
                           amount_paid = ?,
                           paid_by = ?,
                           payment_reference = 'Manual payment'
                       WHERE id = ? 
                       AND (manager_status = 'Approved' OR status = 'Approved')
                       AND (payment_status IS NULL OR payment_status != 'Paid')";
        
        $stmt = $pdo->prepare($updateQuery);
        $userId = $_SESSION['user_id'];
        $stmt->execute([$amountPaid, $userId, $expenseId]);
        
        // Check if update was successful
        if ($stmt->rowCount() > 0) {
            // Commit the transaction
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Expense marked as paid successfully'
            ]);
        } else {
            // Rollback if no rows were updated
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'error' => 'Expense not found or already paid'
            ]);
        }
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Payment Error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Mark multiple expenses as paid
 */
function markMultipleExpensesAsPaid() {
    global $pdo;
    
    // Get parameters
    $expenseIdsJson = $_POST['expense_ids'] ?? '[]';
    
    // Debug: Log the raw input
    error_log("Raw expense_ids input: " . $expenseIdsJson);
    
    // Handle potential special characters and escape sequences
    $expenseIdsJson = stripslashes($expenseIdsJson);
    
    // Try multiple approaches to decode the JSON
    $expenseIds = [];
    $success = false;
    $errorMsg = "";
    
    // Approach 1: Direct decode
    $expenseIds = json_decode($expenseIdsJson, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $success = true;
    } else {
        $errorMsg .= "Direct decode failed: " . json_last_error_msg() . "; ";
        
        // Approach 2: Clean and decode
        $cleanJson = html_entity_decode($expenseIdsJson);
        $expenseIds = json_decode($cleanJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $success = true;
        } else {
            $errorMsg .= "Clean decode failed: " . json_last_error_msg() . "; ";
            
            // Approach 3: Try with trimming
            $expenseIds = json_decode(trim($cleanJson), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $success = true;
            } else {
                $errorMsg .= "Trim decode failed: " . json_last_error_msg() . "; ";
                
                // Approach 4: Manual parsing if it's a simple array
                if (preg_match('/^\[(\d+(?:,\s*\d+)*)\]$/', $cleanJson, $matches)) {
                    $numberStr = $matches[1];
                    $expenseIds = array_map('intval', explode(',', $numberStr));
                    $success = true;
                    error_log("Used manual parsing: " . print_r($expenseIds, true));
                } else {
                    $errorMsg .= "Manual parsing failed; ";
                }
            }
        }
    }
    
    // If all approaches failed
    if (!$success) {
        error_log("All JSON decode attempts failed: " . $errorMsg . " - Raw data: " . $expenseIdsJson);
        echo json_encode(['success' => false, 'error' => 'Could not parse expense IDs: ' . $errorMsg]);
        return;
    }
    
    // Ensure we have an array
    if (!is_array($expenseIds)) {
        $expenseIds = [$expenseIds];
    }
    
    // Debug: Log the decoded array
    error_log("Successfully decoded expense IDs: " . print_r($expenseIds, true));
    
    $paymentReference = $_POST['payment_reference'] ?? 'Batch payment';
    
    // Validate input
    if (empty($expenseIds)) {
        echo json_encode(['success' => false, 'error' => 'No expenses selected']);
        return;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        $successCount = 0;
        $failCount = 0;
        
        // Process each expense
        foreach ($expenseIds as $expenseId) {
            // Ensure expense ID is an integer
            $expenseId = intval($expenseId);
            // Get the expense details to get the amount
            $getExpenseQuery = "SELECT amount FROM travel_expenses WHERE id = ?";
            $expenseStmt = $pdo->prepare($getExpenseQuery);
            $expenseStmt->execute([$expenseId]);
            $expenseData = $expenseStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$expenseData) {
                $failCount++;
                continue;
            }
            
            $amount = $expenseData['amount'];
                
                        // Update the expense record
            $updateQuery = "UPDATE travel_expenses 
                           SET payment_status = 'Paid',
                               paid_on_date = NOW(),
                               amount_paid = ?,
                               paid_by = ?,
                               payment_reference = ?
                           WHERE id = ? 
                           AND (manager_status = 'Approved' OR status = 'Approved')
                           AND (payment_status IS NULL OR payment_status != 'Paid')";
            
            $stmt = $pdo->prepare($updateQuery);
            $userId = $_SESSION['user_id'];
            $stmt->execute([$amount, $userId, $paymentReference, $expenseId]);
                
            // Check if update was successful
            if ($stmt->rowCount() > 0) {
                // Payment successfully updated
                
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        // Commit the transaction
        $pdo->commit();
        
        if ($successCount > 0) {
            $message = "$successCount expenses marked as paid successfully";
            if ($failCount > 0) {
                $message .= " ($failCount failed)";
            }
            
            echo json_encode([
                'success' => true, 
                'message' => $message
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'error' => 'No expenses were updated. They may have already been paid.'
            ]);
        }
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Batch Payment Error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Database error: ' . $e->getMessage()
        ]);
}
} 