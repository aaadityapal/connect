<?php
// Simplified and fixed resubmit travel expense functionality
error_reporting(E_ALL);
ini_set('display_errors', 0);
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse([
        'success' => false,
        'message' => 'User not logged in'
    ]);
}

// Include database connection
include_once('includes/db_connect.php');

// Check if database connection is successful
if ($conn->connect_error) {
    sendResponse([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

// Get expense ID from POST
$expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;

if ($expense_id <= 0) {
    sendResponse([
        'success' => false,
        'message' => 'Invalid expense ID'
    ]);
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

try {
    // Fetch the rejected expense details
    $stmt = $conn->prepare("SELECT * FROM travel_expenses WHERE id = ? AND user_id = ? AND status = 'rejected'");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $expense_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception("Rejected expense not found or you don't have permission to resubmit it");
    }
    
    $expense = $result->fetch_assoc();
    $stmt->close();
    
    // Check if maximum resubmissions reached - use safe defaults
    $current_count = isset($expense['resubmission_count']) ? intval($expense['resubmission_count']) : 0;
    $max_allowed = isset($expense['max_resubmissions']) ? intval($expense['max_resubmissions']) : 3;
    
    if ($current_count >= $max_allowed) {
        throw new Exception("Maximum resubmissions reached. This expense has already been resubmitted {$current_count} times (limit: {$max_allowed}).");
    }
    
    // Check if the expense is within 15 days from present date
    $travel_date = new DateTime($expense['travel_date']);
    $current_date = new DateTime();
    $date_diff = $current_date->diff($travel_date)->days;
    
    if ($date_diff > 15) {
        throw new Exception("Cannot resubmit expense. Travel date is {$date_diff} days old (maximum 15 days allowed for resubmission).");
    }
    
    // Check if this expense was already resubmitted and still pending/approved
    $root_id = isset($expense['original_expense_id']) && $expense['original_expense_id'] ? $expense['original_expense_id'] : $expense['id'];
    
    $pending_stmt = $conn->prepare("
        SELECT id, status, resubmission_count FROM travel_expenses 
        WHERE (original_expense_id = ? OR (id = ? AND original_expense_id IS NULL)) 
        AND user_id = ? 
        AND id != ?
        AND status IN ('pending', 'approved')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    
    if (!$pending_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $pending_stmt->bind_param("iiii", $root_id, $root_id, $user_id, $expense_id);
    
    if (!$pending_stmt->execute()) {
        throw new Exception("Execute failed: " . $pending_stmt->error);
    }
    
    $pending_result = $pending_stmt->get_result();
    
    if ($pending_result->num_rows > 0) {
        $pending_expense = $pending_result->fetch_assoc();
        $status_text = ucfirst($pending_expense['status']);
        $pending_stmt->close();
        throw new Exception("This expense has already been resubmitted and is currently {$status_text} (Expense #{$pending_expense['id']}). Please wait for the current submission to be processed before resubmitting again.");
    }
    
    $pending_stmt->close();
    
    // Start transaction
    if (!$conn->begin_transaction()) {
        throw new Exception("Failed to start transaction: " . $conn->error);
    }

    // Create basic resubmission - only required fields first
    $insert_stmt = $conn->prepare("
        INSERT INTO travel_expenses (
            user_id, purpose, mode_of_transport, from_location, 
            to_location, travel_date, distance, amount, status, notes, 
            bill_file_path, manager_status, accountant_status, hr_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', 'pending', 'pending', NOW())
    ");
    
    if (!$insert_stmt) {
        $conn->rollback();
        throw new Exception("Insert prepare failed: " . $conn->error);
    }
    
    $notes = isset($expense['notes']) ? $expense['notes'] : '';
    $bill_path = isset($expense['bill_file_path']) ? $expense['bill_file_path'] : '';
    
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
        throw new Exception("Bind param failed: " . $insert_stmt->error);
    }
    
    if (!$insert_stmt->execute()) {
        $conn->rollback();
        throw new Exception("Insert execute failed: " . $insert_stmt->error);
    }
    
    $new_expense_id = $conn->insert_id;
    $insert_stmt->close();

    // Update with resubmission tracking
    $new_count = $current_count + 1;
    
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
        throw new Exception("Failed to commit transaction: " . $conn->error);
    }

    sendResponse([
        'success' => true,
        'message' => 'Expense resubmitted successfully',
        'new_expense_id' => $new_expense_id,
        'resubmission_count' => $new_count,
        'remaining_resubmissions' => 3 - $new_count
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction if it was started
    if (isset($conn) && !$conn->connect_error) {
        try {
            $conn->rollback();
        } catch (Exception $rollback_error) {
            // Ignore rollback errors
        }
    }
    
    // Error response
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// This should never be reached
sendResponse([
    'success' => false,
    'message' => 'Unexpected end of script'
]);
?>