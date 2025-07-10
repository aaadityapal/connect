<?php
// Include database connection
require_once '../config/db_connect.php';

// Set headers to return JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize response
$response = [
    'success' => false,
    'message' => 'An error occurred while processing the request'
];

// Make sure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

try {
    // Get transaction ID from request
    $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
    
    // Validate transaction ID
    if (!$transaction_id) {
        $response['message'] = 'Invalid transaction ID provided';
        echo json_encode($response);
        exit;
    }
    
    // Start a transaction
    $conn->begin_transaction();
    
    // First, check if this is the only stage for the project
    $checkQuery = "SELECT COUNT(*) as stage_count, project_id FROM hrm_project_stage_payment_transactions 
                  WHERE project_id = (SELECT project_id FROM hrm_project_stage_payment_transactions WHERE transaction_id = ?)";
    
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $transaction_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    
    // If this is the only stage for the project, we should delete the entire project
    $isOnlyStage = ($checkResult['stage_count'] <= 1);
    $project_id = $checkResult['project_id'];
    
    // Delete payment entries first (foreign key constraint)
    $deletePaymentsQuery = "DELETE FROM hrm_project_payment_entries WHERE transaction_id = ?";
    $deletePaymentsStmt = $conn->prepare($deletePaymentsQuery);
    $deletePaymentsStmt->bind_param('i', $transaction_id);
    $deletePaymentsStmt->execute();
    
    // Delete the transaction record
    $deleteTransactionQuery = "DELETE FROM hrm_project_stage_payment_transactions WHERE transaction_id = ?";
    $deleteTransactionStmt = $conn->prepare($deleteTransactionQuery);
    $deleteTransactionStmt->bind_param('i', $transaction_id);
    $deleteTransactionStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    // Success response
    $response = [
        'success' => true,
        'message' => $isOnlyStage ? 'Project deleted successfully' : 'Project stage deleted successfully',
        'was_only_stage' => $isOnlyStage,
        'project_id' => $project_id
    ];
    
} catch (Exception $e) {
    // Roll back the transaction in case of error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
} finally {
    // Close any open statements
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($deletePaymentsStmt)) $deletePaymentsStmt->close();
    if (isset($deleteTransactionStmt)) $deleteTransactionStmt->close();
}

// Return JSON response
echo json_encode($response); 