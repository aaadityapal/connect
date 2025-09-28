<?php
// Disable error display to prevent JSON corruption and enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Start output buffering to catch any unexpected output
ob_start();

// Start session to get user data
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = array(
        'success' => false,
        'message' => 'User not logged in'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if database connection is successful
if ($conn->connect_error) {
    $response = array(
        'success' => false,
        'message' => 'Database connection failed'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = array(
        'success' => false,
        'message' => 'Invalid request method'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get expense ID from POST
$expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;

if ($expense_id <= 0) {
    $response = array(
        'success' => false,
        'message' => 'Invalid expense ID'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

try {
    // First, check if resubmission columns exist
    $column_check = $conn->query("SHOW COLUMNS FROM travel_expenses LIKE 'resubmission_count'");
    $has_resubmission_columns = $column_check->num_rows > 0;
    
    if (!$has_resubmission_columns) {
        throw new Exception("Resubmission columns not found. Please run the database migration first. Visit check_resubmission_schema.php to auto-fix.");
    }
    
    // Fetch the rejected expense details and check resubmission count
    $stmt = $conn->prepare("
        SELECT *
        FROM travel_expenses 
        WHERE id = ? AND user_id = ? AND status = 'rejected'
    ");
    
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
    
    // Check 15-day restriction - rejected expenses older than 15 days cannot be resubmitted
    $rejectionDate = new DateTime($expense['updated_at']);
    $currentDate = new DateTime();
    $daysSinceRejection = $currentDate->diff($rejectionDate)->days;
    
    if ($daysSinceRejection > 15) {
        throw new Exception("This expense was rejected more than 15 days ago ({$daysSinceRejection} days). Expenses older than 15 days cannot be resubmitted. Please submit a new expense instead.");
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
        throw new Exception("This expense has already been resubmitted and is currently {$status_text} (Expense #{$pending_expense['id']}). Please wait for the current submission to be processed before resubmitting again.");
    }
    
    $pending_stmt->close();
    
    // Check if maximum resubmissions reached - use safe defaults
    $current_count = isset($expense['resubmission_count']) ? intval($expense['resubmission_count']) : 0;
    $max_allowed = isset($expense['max_resubmissions']) ? intval($expense['max_resubmissions']) : 3;
    $root_id = isset($expense['original_expense_id']) && $expense['original_expense_id'] ? $expense['original_expense_id'] : $expense['id'];
    
    if ($current_count >= $max_allowed) {
        throw new Exception("Maximum resubmissions reached. This expense has already been resubmitted {$current_count} times (limit: {$max_allowed}).");
    }
    
    // Start a transaction
    $conn->begin_transaction();
    
    // Create a new expense entry with the same details but pending status
    $insert_stmt = $conn->prepare("
        INSERT INTO travel_expenses (
            user_id, purpose, mode_of_transport, from_location, 
            to_location, travel_date, distance, amount, status, notes, 
            bill_file_path, manager_status, accountant_status, hr_status, 
            original_expense_id, resubmission_count, is_resubmitted, 
            resubmitted_from, resubmission_date, max_resubmissions, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', 'pending', 'pending', ?, ?, 1, ?, NOW(), ?, NOW())
    ");
    
    if (!$insert_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters
    $notes = isset($expense['notes']) ? $expense['notes'] : '';
    $bill_path = isset($expense['bill_file_path']) ? $expense['bill_file_path'] : '';
    $new_resubmission_count = $current_count + 1;
    
    $insert_stmt->bind_param(
        "isssssddssiiiii",
        $expense['user_id'],
        $expense['purpose'],
        $expense['mode_of_transport'],
        $expense['from_location'],
        $expense['to_location'],
        $expense['travel_date'],
        $expense['distance'],
        $expense['amount'],
        $notes,
        $bill_path,
        $root_id,              // original_expense_id
        $new_resubmission_count, // resubmission_count
        $expense_id,           // resubmitted_from
        $max_allowed           // max_resubmissions
    );
    
    // Execute the insert statement
    if (!$insert_stmt->execute()) {
        throw new Exception("Execute failed: " . $insert_stmt->error);
    }
    
    $new_expense_id = $conn->insert_id;
    $insert_stmt->close();
    
    // Commit the transaction
    $conn->commit();
    
    // Success response
    $response = array(
        'success' => true,
        'message' => "Expense resubmitted successfully (Resubmission #{$new_resubmission_count})",
        'new_expense_id' => $new_expense_id,
        'resubmission_count' => $new_resubmission_count,
        'remaining_resubmissions' => $max_allowed - $new_resubmission_count,
        'original_expense_id' => $root_id
    );
    
} catch (Exception $e) {
    // Rollback the transaction if it was started
    if ($conn->connect_error === false) {
        try {
            $conn->rollback();
        } catch (Exception $rollback_error) {
            // Ignore rollback errors
        }
    }
    
    // Error response
    $response = array(
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => array(
            'error_type' => get_class($e),
            'error_line' => $e->getLine(),
            'error_file' => basename($e->getFile()),
            'php_version' => PHP_VERSION
        )
    );
    
    // Log the error
    error_log("Error resubmitting travel expense: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

// Return JSON response
header('Content-Type: application/json');

// Clear any output buffer to prevent malformed JSON
if (ob_get_length()) {
    $buffer_content = ob_get_contents();
    if (!empty(trim($buffer_content))) {
        // Log any unexpected output
        error_log("Unexpected output in resubmit_travel_expense.php: " . $buffer_content);
    }
    ob_clean();
}

// Ensure no additional output after this point
echo json_encode($response);
exit();
?>