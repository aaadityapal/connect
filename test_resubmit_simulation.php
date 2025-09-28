<?php
// Test file to simulate the exact resubmit POST request
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Find a rejected expense to test with
$stmt = $conn->prepare("SELECT * FROM travel_expenses WHERE user_id = ? AND status = 'rejected' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $response = array(
        'success' => false,
        'message' => 'No rejected expenses found to test with',
        'suggestion' => 'Create a rejected expense first, then try this test'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$rejected_expense = $result->fetch_assoc();
$expense_id = $rejected_expense['id'];
$stmt->close();

// Now simulate the exact resubmit process
try {
    // Start a transaction
    $conn->begin_transaction();
    
    // Create a new expense entry with the same details but pending status
    $insert_stmt = $conn->prepare("
        INSERT INTO travel_expenses (
            user_id, purpose, mode_of_transport, from_location, 
            to_location, travel_date, distance, amount, status, notes, 
            bill_file_path, manager_status, accountant_status, hr_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', 'pending', 'pending', NOW())
    ");
    
    if (!$insert_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters
    $bind_result = $insert_stmt->bind_param(
        "isssssddss",
        $rejected_expense['user_id'],
        $rejected_expense['purpose'],
        $rejected_expense['mode_of_transport'],
        $rejected_expense['from_location'],
        $rejected_expense['to_location'],
        $rejected_expense['travel_date'],
        $rejected_expense['distance'],
        $rejected_expense['amount'],
        $rejected_expense['notes'] ?? '',
        $rejected_expense['bill_file_path'] ?? ''
    );
    
    if (!$bind_result) {
        throw new Exception("Bind failed: " . $insert_stmt->error);
    }
    
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
        'message' => 'Test resubmission completed successfully',
        'original_expense_id' => $expense_id,
        'new_expense_id' => $new_expense_id,
        'original_purpose' => $rejected_expense['purpose'],
        'test_mode' => true
    );
    
} catch (Exception $e) {
    // Rollback the transaction
    $conn->rollback();
    
    // Error response
    $response = array(
        'success' => false,
        'message' => 'Test resubmission failed: ' . $e->getMessage(),
        'original_expense_id' => $expense_id,
        'debug_info' => array(
            'error_type' => get_class($e),
            'error_line' => $e->getLine(),
            'error_file' => basename($e->getFile()),
            'rejected_expense_data' => $rejected_expense
        )
    );
    
    // Log the error
    error_log("Test resubmit error: " . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
if (ob_get_length()) {
    ob_clean();
}
echo json_encode($response, JSON_PRETTY_PRINT);
exit();
?>