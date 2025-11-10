<?php
// Resubmit travel expense by updating the existing record
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
include_once('../includes/db_connect.php');

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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Get expense ID from input
$expense_id = isset($input['expense_id']) ? intval($input['expense_id']) : 0;

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
    
    // Start transaction
    if (!$conn->begin_transaction()) {
        throw new Exception("Failed to start transaction: " . $conn->error);
    }
    
    // Update the existing expense record to reset its status to pending
    $update_stmt = $conn->prepare("
        UPDATE travel_expenses 
        SET status = 'pending',
            manager_status = 'pending',
            accountant_status = 'pending',
            hr_status = 'pending',
            resubmission_count = resubmission_count + 1,
            resubmission_date = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    if (!$update_stmt) {
        $conn->rollback();
        throw new Exception("Update prepare failed: " . $conn->error);
    }
    
    if (!$update_stmt->bind_param("i", $expense_id)) {
        $conn->rollback();
        throw new Exception("Bind param failed: " . $update_stmt->error);
    }
    
    if (!$update_stmt->execute()) {
        $conn->rollback();
        throw new Exception("Update execute failed: " . $update_stmt->error);
    }
    
    $update_stmt->close();
    
    if (!$conn->commit()) {
        throw new Exception("Failed to commit transaction: " . $conn->error);
    }
    
    $new_count = $current_count + 1;
    
    sendResponse([
        'success' => true,
        'message' => "Expense resubmitted successfully (Resubmission #{$new_count})",
        'resubmission_count' => $new_count,
        'remaining_resubmissions' => $max_allowed - $new_count
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