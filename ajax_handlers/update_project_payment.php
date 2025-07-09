<?php
/**
 * Update Project Payment Handler
 * Handles updating project payment data from the manager_payouts.php edit form
 */

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
    'message' => 'An error occurred while processing the request',
    'affected_stages' => 0
];

// Make sure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

try {
    // Start a transaction
    $conn->begin_transaction();
    
    // Get form data
    $transaction_id = $_POST['transaction_id'] ?? 0;
    $project_id = $_POST['project_id'] ?? 0;
    $project_name = $_POST['project_name'] ?? '';
    $project_type = $_POST['project_type'] ?? '';
    $client_name = $_POST['client_name'] ?? '';
    $stage_number = $_POST['stage_number'] ?? 0;
    $stage_date = $_POST['stage_date'] ?? '';
    $stage_notes = $_POST['stage_notes'] ?? '';
    $total_project_amount = isset($_POST['total_project_amount']) && !empty($_POST['total_project_amount']) 
                          ? floatval($_POST['total_project_amount']) 
                          : null;
    $payments = isset($_POST['payments']) ? json_decode($_POST['payments'], true) : [];
    $new_stage = isset($_POST['new_stage']) ? json_decode($_POST['new_stage'], true) : null;

    // Basic validation
    if (empty($transaction_id) || !is_numeric($transaction_id)) {
        throw new Exception('Invalid transaction ID');
    }

    if (empty($project_name) || empty($project_type) || empty($client_name)) {
        throw new Exception('Missing required project information');
    }

    if (empty($stage_number) || empty($stage_date)) {
        throw new Exception('Missing required stage information');
    }

    // First, get the current transaction data to check project_id
    $checkQuery = "SELECT * FROM hrm_project_stage_payment_transactions WHERE transaction_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $transaction_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Transaction not found');
    }
    
    $transaction = $result->fetch_assoc();
    $project_id = $transaction['project_id'];
    
    // Update transaction record
    $updateTransactionQuery = "UPDATE hrm_project_stage_payment_transactions SET 
                              project_name = ?,
                              project_type = ?,
                              client_name = ?,
                              stage_number = ?,
                              stage_date = ?,
                              stage_notes = ?,
                              total_project_amount = ?
                              WHERE transaction_id = ?";
    
    $updateStmt = $conn->prepare($updateTransactionQuery);
    $updateStmt->bind_param('sssisidi', 
        $project_name, 
        $project_type, 
        $client_name, 
        $stage_number, 
        $stage_date, 
        $stage_notes, 
        $total_project_amount, 
        $transaction_id
    );
    
    $updateStmt->execute();
    
    // If we're updating the project name and client name, update all transactions for this project
    $affected_stages = 0;
    if ($project_name !== $transaction['project_name'] || $project_type !== $transaction['project_type'] || $client_name !== $transaction['client_name']) {
        $updateAllQuery = "UPDATE hrm_project_stage_payment_transactions SET 
                          project_name = ?,
                          project_type = ?,
                          client_name = ?
                          WHERE project_id = ? AND transaction_id != ?";
        
        $updateAllStmt = $conn->prepare($updateAllQuery);
        $updateAllStmt->bind_param('sssii', 
            $project_name, 
            $project_type, 
            $client_name, 
            $project_id, 
            $transaction_id
        );
        
        $updateAllStmt->execute();
        $affected_stages = $updateAllStmt->affected_rows;
    }
    
    // Process payments - first delete existing payments
    $deletePaymentsQuery = "DELETE FROM hrm_project_payment_entries WHERE transaction_id = ?";
    $deletePaymentsStmt = $conn->prepare($deletePaymentsQuery);
    $deletePaymentsStmt->bind_param('i', $transaction_id);
    $deletePaymentsStmt->execute();
    
    // Then insert the new/updated payments
    if (!empty($payments)) {
        foreach ($payments as $payment) {
            $insertPaymentQuery = "INSERT INTO hrm_project_payment_entries 
                                 (transaction_id, payment_date, payment_amount, payment_mode) 
                                 VALUES (?, ?, ?, ?)";
            
            $paymentStmt = $conn->prepare($insertPaymentQuery);
            
            $payment_date = $payment['payment_date'] ?? date('Y-m-d');
            $payment_amount = floatval($payment['payment_amount']);
            $payment_mode = $payment['payment_mode'];
            
            $paymentStmt->bind_param('isds', 
                $transaction_id, 
                $payment_date, 
                $payment_amount, 
                $payment_mode
            );
            
            $paymentStmt->execute();
        }
    }
    
    // Handle new stage creation if provided
    $new_stage_transaction_id = null;
    if ($new_stage && !empty($new_stage['payments'])) {
        // Insert new stage transaction record
        $insertNewStageQuery = "INSERT INTO hrm_project_stage_payment_transactions 
                              (project_id, project_name, project_type, client_name, stage_number, 
                               stage_date, stage_notes, total_project_amount, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $newStageStmt = $conn->prepare($insertNewStageQuery);
        
        // Get user ID (assuming the user is logged in and you have a session)
        $user_id = $_SESSION['user_id'] ?? 1; // Default to 1 if not available
        
        $newStageStmt->bind_param('isssisidi', 
            $project_id, 
            $project_name, 
            $project_type, 
            $client_name, 
            $new_stage['stage_number'], 
            $new_stage['stage_date'], 
            $new_stage['stage_notes'], 
            $total_project_amount, 
            $user_id
        );
        
        $newStageStmt->execute();
        $new_stage_transaction_id = $conn->insert_id;
        
        // Insert payment entries for the new stage
        foreach ($new_stage['payments'] as $payment) {
            if (empty($payment['payment_amount']) || empty($payment['payment_mode'])) {
                continue; // Skip invalid payments
            }
            
            $insertPaymentQuery = "INSERT INTO hrm_project_payment_entries 
                                 (transaction_id, payment_date, payment_amount, payment_mode) 
                                 VALUES (?, ?, ?, ?)";
            
            $paymentStmt = $conn->prepare($insertPaymentQuery);
            
            $payment_date = $payment['payment_date'] ?? date('Y-m-d');
            $payment_amount = floatval($payment['payment_amount']);
            $payment_mode = $payment['payment_mode'];
            
            $paymentStmt->bind_param('isds', 
                $new_stage_transaction_id, 
                $payment_date, 
                $payment_amount, 
                $payment_mode
            );
            
            $paymentStmt->execute();
        }
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Success response
    $response = [
        'success' => true,
        'message' => $new_stage_transaction_id ? 'Project payment updated and new stage added successfully' : 'Project payment updated successfully',
        'affected_stages' => $affected_stages,
        'transaction_id' => $transaction_id,
        'new_stage_transaction_id' => $new_stage_transaction_id
    ];
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Update project payment error: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    // Return JSON response
    echo json_encode($response);
}
?> 