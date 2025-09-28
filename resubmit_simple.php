<?php
// Simple working version of resubmit with better error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

session_start();

// Function to send JSON response and exit
function sendResponse($data) {
    // Clear any previous output
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendResponse(['success' => false, 'message' => 'User not logged in']);
    }

    // Include database connection
    if (!file_exists('includes/db_connect.php')) {
        sendResponse(['success' => false, 'message' => 'Database connection file not found']);
    }
    
    include_once('includes/db_connect.php');

    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        sendResponse(['success' => false, 'message' => 'Database connection failed: ' . ($conn->connect_error ?? 'Connection object not found')]);
    }

    // Check if POST data exists
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['expense_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid request - missing expense_id']);
    }

    $expense_id = intval($_POST['expense_id']);
    $user_id = $_SESSION['user_id'];
    
    if ($expense_id <= 0) {
        sendResponse(['success' => false, 'message' => 'Invalid expense ID']);
    }

    // Get the rejected expense
    $stmt = $conn->prepare("SELECT * FROM travel_expenses WHERE id = ? AND user_id = ? AND status = 'rejected'");
    if (!$stmt) {
        sendResponse(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
    }
    
    $stmt->bind_param("ii", $expense_id, $user_id);
    
    if (!$stmt->execute()) {
        sendResponse(['success' => false, 'message' => 'Database execute failed: ' . $stmt->error]);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        sendResponse(['success' => false, 'message' => 'Rejected expense not found']);
    }
    
    $expense = $result->fetch_assoc();
    $stmt->close();

    // Start transaction
    if (!$conn->begin_transaction()) {
        sendResponse(['success' => false, 'message' => 'Failed to start transaction: ' . $conn->error]);
    }

    // Create basic resubmission - only required fields
    $insert_stmt = $conn->prepare("
        INSERT INTO travel_expenses (
            user_id, purpose, mode_of_transport, from_location, 
            to_location, travel_date, distance, amount, status, notes, 
            bill_file_path, manager_status, accountant_status, hr_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', 'pending', 'pending', NOW())
    ");
    
    if (!$insert_stmt) {
        $conn->rollback();
        sendResponse(['success' => false, 'message' => 'Insert prepare failed: ' . $conn->error]);
    }
    
    $notes = $expense['notes'] ?? '';
    $bill_path = $expense['bill_file_path'] ?? '';
    
    if (!$insert_stmt->bind_param(
        "isssssddss",
        $expense['user_id'],
        $expense['purpose'],
        $expense['mode_of_transport'],
        $expense['from_location'],
        $expense['to_location'],
        $expense['travel_date'],
        $expense['distance'],
        $expense['amount'],
        $notes,
        $bill_path
    )) {
        $conn->rollback();
        sendResponse(['success' => false, 'message' => 'Bind param failed: ' . $insert_stmt->error]);
    }
    
    if (!$insert_stmt->execute()) {
        $conn->rollback();
        sendResponse(['success' => false, 'message' => 'Insert execute failed: ' . $insert_stmt->error]);
    }
    
    $new_expense_id = $conn->insert_id;
    $insert_stmt->close();

    // Update with resubmission tracking if columns exist
    $current_count = intval($expense['resubmission_count'] ?? 0);
    $new_count = $current_count + 1;
    $root_id = $expense['original_expense_id'] ?? $expense['id'];
    
    $update_stmt = $conn->prepare("
        UPDATE travel_expenses 
        SET original_expense_id = ?, 
            resubmission_count = ?, 
            is_resubmitted = 1, 
            resubmitted_from = ?, 
            max_resubmissions = 3
        WHERE id = ?
    ");
    
    if ($update_stmt) {
        $update_stmt->bind_param("iiii", $root_id, $new_count, $expense_id, $new_expense_id);
        if (!$update_stmt->execute()) {
            // Log warning but don't fail the transaction
            error_log("Warning: Failed to update resubmission tracking: " . $update_stmt->error);
        }
        $update_stmt->close();
    }

    if (!$conn->commit()) {
        sendResponse(['success' => false, 'message' => 'Failed to commit transaction: ' . $conn->error]);
    }

    sendResponse([
        'success' => true,
        'message' => 'Expense resubmitted successfully',
        'new_expense_id' => $new_expense_id,
        'resubmission_count' => $new_count,
        'remaining_resubmissions' => 3 - $new_count
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    sendResponse(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
} catch (Error $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    sendResponse(['success' => false, 'message' => 'Fatal Error: ' . $e->getMessage()]);
}

// This should never be reached due to sendResponse calls
sendResponse(['success' => false, 'message' => 'Unexpected end of script']);
?>