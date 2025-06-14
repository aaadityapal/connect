<?php
// Include database connection
include 'config/db_connect.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'payment_debug.log');

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Get form data
    $manager_id = isset($_POST['manager_id']) ? $_POST['manager_id'] : null;
    $project_id = isset($_POST['project_id']) ? $_POST['project_id'] : null;
    $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : null;
    $project_stage = isset($_POST['project_stage']) ? $_POST['project_stage'] : null;
    $payment_mode = isset($_POST['payment_mode']) ? $_POST['payment_mode'] : null;
    $amount = isset($_POST['amount']) ? $_POST['amount'] : null;
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Log the received data
    error_log("Payment request received - Manager ID: $manager_id, Project ID: $project_id, Amount: $amount");
    
    // Validate required fields
    if (!$manager_id || !$project_id || !$payment_date || !$project_stage || !$payment_mode || !$amount) {
        echo json_encode([
            'success' => false,
            'message' => 'All required fields must be filled'
        ]);
        error_log("Payment validation failed - missing required fields");
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    error_log("Transaction started");
    
    // Check if a payment for this project was already made today
    $checkDuplicateQuery = "SELECT id FROM manager_payments 
                           WHERE project_id = ? 
                           AND manager_id = ? 
                           AND payment_date = ? 
                           AND amount = ?
                           LIMIT 1";
    $checkDuplicateStmt = $conn->prepare($checkDuplicateQuery);
    $checkDuplicateStmt->bind_param("iisd", $project_id, $manager_id, $payment_date, $amount);
    $checkDuplicateStmt->execute();
    $checkDuplicateResult = $checkDuplicateStmt->get_result();
    
    if ($checkDuplicateResult->num_rows > 0) {
        error_log("Duplicate payment detected - aborting");
        echo json_encode([
            'success' => false,
            'message' => 'A payment with the same details has already been processed today. Please check your records.'
        ]);
        $conn->rollback();
        exit;
    }
    
    // Fetch project details to get project_name, project_type, and client_name
    $projectQuery = "SELECT project_name, project_type, client_name, amount as total_amount, remaining_amount FROM project_payouts WHERE id = ?";
    $projectStmt = $conn->prepare($projectQuery);
    $projectStmt->bind_param("i", $project_id);
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    $projectData = $projectResult->fetch_assoc();
    
    if (!$projectData) {
        error_log("Project not found - ID: $project_id");
        echo json_encode([
            'success' => false,
            'message' => 'Project not found'
        ]);
        $conn->rollback();
        exit;
    }
    
    // Calculate remaining amount
    $currentRemaining = $projectData['remaining_amount'] ?? $projectData['total_amount'];
    $newRemaining = max(0, $currentRemaining - $amount);
    error_log("Calculating remaining amount - Current: $currentRemaining, Payment: $amount, New: $newRemaining");
    
    // Update the remaining amount and manager_id in project_payouts
    $updateQuery = "UPDATE project_payouts SET remaining_amount = ?, manager_id = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("dii", $newRemaining, $manager_id, $project_id);
    $updateResult = $updateStmt->execute();
    
    if (!$updateResult) {
        error_log("Failed to update project_payouts - Error: " . $conn->error);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update project details'
        ]);
        $conn->rollback();
        exit;
    }
    
    error_log("Project updated successfully - ID: $project_id, New remaining: $newRemaining");
    
    // Insert record into manager_payments table
    $paymentQuery = "INSERT INTO manager_payments (
                manager_id, 
                project_id, 
                payment_date, 
                amount, 
                payment_mode, 
                notes
              ) VALUES (
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?
              )";
    
    $paymentStmt = $conn->prepare($paymentQuery);
    $paymentStmt->bind_param("iisdss", $manager_id, $project_id, $payment_date, $amount, $payment_mode, $notes);
    $paymentResult = $paymentStmt->execute();
    
    if (!$paymentResult) {
        error_log("Failed to insert payment record - Error: " . $conn->error);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to record payment'
        ]);
        $conn->rollback();
        exit;
    }
    
    // Get the ID of the inserted payment
    $payment_id = $conn->insert_id;
    error_log("Payment record inserted - ID: $payment_id");
    
    // Commit transaction
    $conn->commit();
    error_log("Transaction committed successfully");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'payment_id' => $payment_id,
        'remaining_amount' => $newRemaining
    ]);
    
} catch (Exception $e) {
    // Roll back transaction
    if ($conn->connect_errno == 0) {
        $conn->rollback();
    }
    
    // Return error message
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?> 