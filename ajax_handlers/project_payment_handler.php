<?php
/**
 * Project Payment Handler
 * Handles saving project payment data from the manager_payouts.php form
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
    'transaction_id' => null
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
    $project_name = $_POST['project_name'] ?? '';
    $project_type = $_POST['project_type'] ?? '';
    $client_name = $_POST['client_name'] ?? '';
    $stages = json_decode($_POST['stages'] ?? '{}', true);

    // Basic validation
    if (empty($project_name) || empty($project_type) || empty($client_name)) {
        throw new Exception('Missing required project information');
    }

    if (empty($stages)) {
        throw new Exception('No stage data provided');
    }

    // Get user ID (assuming the user is logged in and you have a session)
    $user_id = $_SESSION['user_id'] ?? 1; // Default to 1 if not available

    // Determine the project_id - for now, we'll create a new one
    // You might want to extend this to allow connecting to existing projects
    $project_id = time(); // Simple temp ID, you may want to generate this differently
    
    // Process each stage
    foreach ($stages as $stage_number => $stage_data) {
        // Check if stage has payments
        if (empty($stage_data['payments'])) {
            continue; // Skip stages without payments
        }
        
        // Insert stage transaction record
        $insertStageQuery = "INSERT INTO hrm_project_stage_payment_transactions 
                            (project_id, project_name, project_type, client_name, stage_number, 
                             stage_date, stage_notes, total_project_amount, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stageStmt = $conn->prepare($insertStageQuery);
        
        if (!$stageStmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stage_date = $stage_data['date'] ?? date('Y-m-d');
        $stage_notes = $stage_data['notes'] ?? null;
        $total_project_amount = !empty($stage_data['total_amount']) ? floatval($stage_data['total_amount']) : null;
        
        $stageStmt->bind_param('isssisidi', 
            $project_id, 
            $project_name, 
            $project_type, 
            $client_name, 
            $stage_number, 
            $stage_date, 
            $stage_notes, 
            $total_project_amount, 
            $user_id
        );
        
        $stageStmt->execute();
        $transaction_id = $conn->insert_id;
        
        // Insert payment entries for this stage
        foreach ($stage_data['payments'] as $payment) {
            if (empty($payment['amount']) || empty($payment['payment_mode'])) {
                continue; // Skip invalid payments
            }
            
            $insertPaymentQuery = "INSERT INTO hrm_project_payment_entries 
                                  (transaction_id, payment_date, payment_amount, payment_mode) 
                                  VALUES (?, ?, ?, ?)";
            
            $paymentStmt = $conn->prepare($insertPaymentQuery);
            
            if (!$paymentStmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $payment_date = $payment['date'] ?? date('Y-m-d');
            $payment_amount = floatval($payment['amount']);
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
    
    // Commit the transaction
    $conn->commit();
    
    // Success response
    $response = [
        'success' => true,
        'message' => 'Project payment data saved successfully',
        'transaction_id' => $transaction_id,
        'project_id' => $project_id
    ];
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Project payment handler error: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    // Return JSON response
    echo json_encode($response);
}
?> 