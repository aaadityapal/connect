<?php
// Disable error display in output - log errors instead
ini_set('display_errors', 0);
error_reporting(E_ALL);
// This will log errors to the PHP error log instead of sending them to output
ini_set('log_errors', 1);

// Buffer output to ensure nothing is sent before our JSON response
ob_start();

// Include database connection
require_once '../config/db_connect.php';

// Set headers to return JSON response
header('Content-Type: application/json');

// Default response (error)
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

try {
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Extract and sanitize form data
        $managerId = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
        $managerName = isset($_POST['manager_name']) ? trim($_POST['manager_name']) : '';
        $managerType = isset($_POST['manager_type']) ? trim($_POST['manager_type']) : '';
        
        $projectName = isset($_POST['project_name']) ? trim($_POST['project_name']) : '';
        $projectType = isset($_POST['project_type']) ? trim($_POST['project_type']) : '';
        $clientName = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
        
        $projectStage = isset($_POST['project_stage']) ? intval($_POST['project_stage']) : 0;
        $paymentDate = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d');
        $paymentAmount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0.00;
        $paymentMode = isset($_POST['payment_mode']) ? trim($_POST['payment_mode']) : '';
        $paymentReference = isset($_POST['payment_reference']) ? trim($_POST['payment_reference']) : null;
        
        // Get current user ID (if authentication is implemented)
        $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
        
        // Validate required fields
        if (empty($managerId) || empty($managerName) || empty($projectName) || 
            empty($projectType) || empty($clientName) || empty($projectStage) || 
            empty($paymentDate) || empty($paymentAmount) || empty($paymentMode)) {
            
            $response = [
                'success' => false,
                'message' => 'All fields are required'
            ];
            throw new Exception('Required fields missing');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // First, get the project ID from the project name
        $projectQuery = "SELECT project_id FROM hrm_project_stage_payment_transactions 
                         WHERE project_name = ? LIMIT 1";
        $projectStmt = $conn->prepare($projectQuery);
        if (!$projectStmt) {
            throw new Exception('Failed to prepare project query: ' . $conn->error);
        }
        
        $projectStmt->bind_param('s', $projectName);
        $projectStmt->execute();
        $projectResult = $projectStmt->get_result();
        
        // If project exists, get its ID, otherwise use 0
        $projectId = 0;
        if ($projectResult->num_rows > 0) {
            $projectData = $projectResult->fetch_assoc();
            $projectId = $projectData['project_id'];
        }
        
        // Insert payment transaction
        $insertQuery = "INSERT INTO hrm_manager_payment_transactions (
                            manager_id, manager_name, manager_type, project_id, project_name, 
                            project_type, client_name, project_stage, payment_date, payment_amount, 
                            payment_mode, payment_reference, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insertStmt = $conn->prepare($insertQuery);
        if (!$insertStmt) {
            throw new Exception('Failed to prepare insert query: ' . $conn->error);
        }
        
        $insertStmt->bind_param('issiissisdss', 
            $managerId, $managerName, $managerType, $projectId, $projectName,
            $projectType, $clientName, $projectStage, $paymentDate, $paymentAmount,
            $paymentMode, $paymentReference, $userId
        );
        
        // Execute the insert statement
        if ($insertStmt->execute()) {
            $paymentId = $conn->insert_id;
            
            // Get current month and year from payment date
            $month = date('n', strtotime($paymentDate)); // 1-12
            $year = date('Y', strtotime($paymentDate));
            
            // Check if summary exists for this manager/month/year
            $checkSummaryQuery = "SELECT * FROM hrm_manager_payment_summary 
                                 WHERE manager_id = ? AND month = ? AND year = ?";
            $checkStmt = $conn->prepare($checkSummaryQuery);
            if (!$checkStmt) {
                throw new Exception('Failed to prepare summary check query: ' . $conn->error);
            }
            
            $checkStmt->bind_param('iii', $managerId, $month, $year);
            $checkStmt->execute();
            $summaryResult = $checkStmt->get_result();
            
            // If no summary exists, create one (the trigger should handle this, but as a backup)
            if ($summaryResult->num_rows === 0) {
                try {
                    // Check if the stored procedure exists
                    $procCheckQuery = "SHOW PROCEDURE STATUS WHERE Name = 'recalculate_manager_payments'";
                    $procResult = $conn->query($procCheckQuery);
                    
                    if ($procResult && $procResult->num_rows > 0) {
                        // Call the stored procedure to recalculate payments
                        $recalcQuery = "CALL recalculate_manager_payments(?, ?)";
                        $recalcStmt = $conn->prepare($recalcQuery);
                        if ($recalcStmt) {
                            $recalcStmt->bind_param('ii', $month, $year);
                            $recalcStmt->execute();
                        }
                    } else {
                        // Manual update if stored procedure doesn't exist
                        $updateSummaryQuery = "INSERT INTO hrm_manager_payment_summary
                                              (manager_id, month, year, amount_paid, last_payment_date)
                                              VALUES (?, ?, ?, ?, ?)";
                        $updateStmt = $conn->prepare($updateSummaryQuery);
                        if ($updateStmt) {
                            $updateStmt->bind_param('iiisd', $managerId, $month, $year, $paymentAmount, $paymentDate);
                            $updateStmt->execute();
                        }
                    }
                } catch (Exception $ex) {
                    // Log the error but continue - this is not critical
                    error_log('Error updating payment summary: ' . $ex->getMessage());
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Success response
            $response = [
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment_id' => $paymentId,
                'payment_date' => $paymentDate,
                'amount' => number_format($paymentAmount, 2)
            ];
        } else {
            // Rollback transaction on error
            $conn->rollback();
            throw new Exception('Failed to process payment: ' . $insertStmt->error);
        }
    }
} catch (Exception $e) {
    // Rollback transaction on exception
    if ($conn && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    $response = [
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ];
} finally {
    // Clear any output buffer
    ob_end_clean();
    
    // Return JSON response
    echo json_encode($response);
} 