<?php
/**
 * Get Project Details Handler
 * Fetches project details for editing in the manager_payouts.php form
 */

// Include database connection
require_once '../config/db_connect.php';

// Set headers to return JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get transaction ID from request
$transaction_id = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0;
$view_all = isset($_GET['view_all']) ? boolval($_GET['view_all']) : false;

// Validate transaction ID
if (!$transaction_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid transaction ID provided'
    ]);
    exit;
}

try {
    // Get the transaction details
    $query = "SELECT * FROM hrm_project_stage_payment_transactions WHERE transaction_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No transaction found with the provided ID'
        ]);
        exit;
    }
    
    $project = $result->fetch_assoc();
    
    // Get payment entries for this transaction
    $paymentsQuery = "SELECT * FROM hrm_project_payment_entries WHERE transaction_id = ? ORDER BY payment_date";
    $paymentsStmt = $conn->prepare($paymentsQuery);
    $paymentsStmt->bind_param('i', $transaction_id);
    $paymentsStmt->execute();
    $paymentsResult = $paymentsStmt->get_result();
    
    $payments = [];
    while ($payment = $paymentsResult->fetch_assoc()) {
        $payments[] = $payment;
    }
    
    // Calculate total paid amount
    $totalPaidQuery = "SELECT SUM(payment_amount) as total_paid FROM hrm_project_payment_entries WHERE transaction_id = ?";
    $totalPaidStmt = $conn->prepare($totalPaidQuery);
    $totalPaidStmt->bind_param('i', $transaction_id);
    $totalPaidStmt->execute();
    $totalPaidResult = $totalPaidStmt->get_result();
    $totalPaid = $totalPaidResult->fetch_assoc()['total_paid'] ?? 0;
    
    // If view_all is true, get all stages for this project
    $allStages = [];
    if ($view_all && isset($project['project_id'])) {
        $allStagesQuery = "SELECT t.*, 
                          (SELECT SUM(pe.payment_amount) 
                           FROM hrm_project_payment_entries pe 
                           WHERE pe.transaction_id = t.transaction_id) as stage_total_paid 
                          FROM hrm_project_stage_payment_transactions t 
                          WHERE t.project_id = ? 
                          ORDER BY t.stage_number";
        $allStagesStmt = $conn->prepare($allStagesQuery);
        $allStagesStmt->bind_param('i', $project['project_id']);
        $allStagesStmt->execute();
        $allStagesResult = $allStagesStmt->get_result();
        
        while ($stage = $allStagesResult->fetch_assoc()) {
            // Get payments for each stage
            $stagePaymentsQuery = "SELECT * FROM hrm_project_payment_entries WHERE transaction_id = ? ORDER BY payment_date";
            $stagePaymentsStmt = $conn->prepare($stagePaymentsQuery);
            $stagePaymentsStmt->bind_param('i', $stage['transaction_id']);
            $stagePaymentsStmt->execute();
            $stagePaymentsResult = $stagePaymentsStmt->get_result();
            
            $stagePayments = [];
            while ($payment = $stagePaymentsResult->fetch_assoc()) {
                $stagePayments[] = $payment;
            }
            
            // Add payments to stage data
            $stage['payments'] = $stagePayments;
            $allStages[] = $stage;
        }
        
        // Calculate total project payments
        $projectTotalPaidQuery = "SELECT SUM(pe.payment_amount) as project_total_paid
                                FROM hrm_project_payment_entries pe
                                JOIN hrm_project_stage_payment_transactions t ON pe.transaction_id = t.transaction_id
                                WHERE t.project_id = ?";
        $projectTotalPaidStmt = $conn->prepare($projectTotalPaidQuery);
        $projectTotalPaidStmt->bind_param('i', $project['project_id']);
        $projectTotalPaidStmt->execute();
        $projectTotalPaidResult = $projectTotalPaidStmt->get_result();
        $projectTotalPaid = $projectTotalPaidResult->fetch_assoc()['project_total_paid'] ?? 0;
    }
    
    // Prepare the response
    $response = [
        'success' => true,
        'project' => $project,
        'payments' => $payments,
        'total_paid' => $totalPaid
    ];
    
    // Add project amount details if available
    if (isset($project['total_project_amount']) && $project['total_project_amount'] > 0) {
        $response['project_total_amount'] = $project['total_project_amount'];
        $response['project_remaining_amount'] = $project['total_project_amount'] - $totalPaid;
    }
    
    // Add all stages if requested
    if ($view_all) {
        $response['all_stages'] = $allStages;
        $response['project_total_paid'] = $projectTotalPaid;
        
        // Find the project total amount from any stage that has it defined
        foreach ($allStages as $stage) {
            if (!empty($stage['total_project_amount'])) {
                $response['project_total_amount'] = $stage['total_project_amount'];
                $response['project_remaining_amount'] = $stage['total_project_amount'] - $projectTotalPaid;
                break;
            }
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error and return error message
    error_log("Error in get_project_details.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving project details: ' . $e->getMessage()
    ]);
}
?> 