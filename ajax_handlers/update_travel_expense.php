<?php
/**
 * AJAX handler to update travel expense details
 */

// Include database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set headers for JSON response
header('Content-Type: application/json');

// Debug incoming request
$debug = [
    'post' => $_POST,
    'files' => isset($_FILES) ? array_keys($_FILES) : 'No files'
];
error_log('Update expense debug: ' . json_encode($debug, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

// Check if expense ID is provided
if (!isset($_POST['expense_id']) || empty($_POST['expense_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Expense ID is required'
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

// Get the expense ID
$expenseId = intval($_POST['expense_id']);

try {
    // First check if the expense exists and is in a state that can be updated
    $checkQuery = "SELECT status FROM travel_expenses WHERE id = :id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $expenseId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $expense = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        echo json_encode([
            'success' => false,
            'message' => 'Expense not found'
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        exit;
    }
    
    // Check if the expense is in a state that can be updated
    if (strtolower($expense['status']) !== 'pending') {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot update expense that is not in pending status'
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        exit;
    }
    
    // Get form data
    $purpose = $_POST['purpose'] ?? '';
    $fromLocation = $_POST['from_location'] ?? '';
    $toLocation = $_POST['to_location'] ?? '';
    $modeOfTransport = $_POST['mode_of_transport'] ?? '';
    $travelDate = $_POST['travel_date'] ?? '';
    $distance = !empty($_POST['distance']) ? floatval($_POST['distance']) : null;
    $amount = !empty($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $notes = $_POST['notes'] ?? '';
    
    // Validate required fields
    if (empty($purpose) || empty($fromLocation) || empty($toLocation) || 
        empty($modeOfTransport) || empty($travelDate) || $amount <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fill all required fields'
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update expense details
    $updateQuery = "UPDATE travel_expenses SET 
                    purpose = :purpose,
                    from_location = :from_location,
                    to_location = :to_location,
                    mode_of_transport = :mode_of_transport,
                    travel_date = :travel_date,
                    distance = :distance,
                    amount = :amount,
                    notes = :notes,
                    updated_at = NOW(),
                    updated_by = :updated_by
                    WHERE id = :id";
    
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindValue(':purpose', $purpose);
    $updateStmt->bindValue(':from_location', $fromLocation);
    $updateStmt->bindValue(':to_location', $toLocation);
    $updateStmt->bindValue(':mode_of_transport', $modeOfTransport);
    $updateStmt->bindValue(':travel_date', $travelDate);
    $updateStmt->bindValue(':distance', $distance, PDO::PARAM_STR);
    $updateStmt->bindValue(':amount', $amount);
    $updateStmt->bindValue(':notes', $notes);
    $updateStmt->bindValue(':updated_by', $_POST['user_id'] ?? null, PDO::PARAM_INT);
    $updateStmt->bindValue(':id', $expenseId, PDO::PARAM_INT);
    
    $updateResult = $updateStmt->execute();
    
    // Handle bill file upload if provided
    $billFilePath = null;
    $removeBill = isset($_POST['remove_bill']) && $_POST['remove_bill'] == '1';
    $fileUploadError = false;
    
    // Debug file upload
    if (isset($_FILES['bill_file'])) {
        error_log('File upload info: ' . json_encode($_FILES['bill_file'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
    }
    
    if (isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] === UPLOAD_ERR_OK) {
        // Create uploads directory if it doesn't exist
        $uploadDir = '../uploads/bills/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Failed to create directory: $uploadDir");
                $fileUploadError = true;
            }
        }
        
        if (!$fileUploadError) {
            // Generate unique filename
            $fileExtension = pathinfo($_FILES['bill_file']['name'], PATHINFO_EXTENSION);
            
            // Get user ID from the expense record or use the updated_by value
            $userIdQuery = "SELECT user_id FROM travel_expenses WHERE id = :id";
            $userIdStmt = $pdo->prepare($userIdQuery);
            $userIdStmt->bindValue(':id', $expenseId, PDO::PARAM_INT);
            $userIdStmt->execute();
            $userId = $userIdStmt->fetchColumn();
            
            // If user_id is not available, use the current user ID
            if (!$userId && isset($_POST['user_id'])) {
                $userId = $_POST['user_id'];
            }
            
            // Generate filename with user ID instead of expense ID
            $newFileName = 'bill_' . $userId . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['bill_file']['tmp_name'], $uploadPath)) {
                $billFilePath = 'uploads/bills/' . $newFileName;
                
                // Update bill file path in database
                $updateBillQuery = "UPDATE travel_expenses SET bill_file_path = :bill_file_path WHERE id = :id";
                $updateBillStmt = $pdo->prepare($updateBillQuery);
                $updateBillStmt->bindValue(':bill_file_path', $billFilePath);
                $updateBillStmt->bindValue(':id', $expenseId, PDO::PARAM_INT);
                $updateBillStmt->execute();
            } else {
                // File upload failed, but continue with other updates
                error_log("Failed to move uploaded file to: $uploadPath");
                $fileUploadError = true;
            }
        }
    } else if (isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Log file upload error
        $uploadErrors = [
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk',
            8 => 'A PHP extension stopped the file upload'
        ];
        $errorMessage = isset($uploadErrors[$_FILES['bill_file']['error']]) ? 
                        $uploadErrors[$_FILES['bill_file']['error']] : 
                        'Unknown upload error';
        error_log("File upload error: " . $errorMessage);
        $fileUploadError = true;
    } else if ($removeBill) {
        // Remove bill file path from database
        $removeBillQuery = "UPDATE travel_expenses SET bill_file_path = NULL WHERE id = :id";
        $removeBillStmt = $pdo->prepare($removeBillQuery);
        $removeBillStmt->bindValue(':id', $expenseId, PDO::PARAM_INT);
        $removeBillStmt->execute();
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Get updated expense details
    $getUpdatedQuery = "SELECT te.*, 
                       u.username, 
                       u.designation, 
                       u.profile_picture,
                       u2.username as updated_by_name
                FROM travel_expenses te
                LEFT JOIN users u ON te.user_id = u.id
                LEFT JOIN users u2 ON te.updated_by = u2.id
                WHERE te.id = :id";
    
    $getUpdatedStmt = $pdo->prepare($getUpdatedQuery);
    $getUpdatedStmt->bindValue(':id', $expenseId, PDO::PARAM_INT);
    $getUpdatedStmt->execute();
    
    $updatedExpense = $getUpdatedStmt->fetch(PDO::FETCH_ASSOC);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Expense updated successfully',
        'expense' => $updatedExpense,
        'file_upload_error' => $fileUploadError
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error for debugging
    error_log('Database error in update_travel_expense.php: ' . $e->getMessage());
    
    // Return error response with more details
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . htmlspecialchars($e->getMessage()),
        'error_code' => $e->getCode()
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
} catch (Exception $e) {
    // Handle any other exceptions
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('General error in update_travel_expense.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . htmlspecialchars($e->getMessage())
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
} 