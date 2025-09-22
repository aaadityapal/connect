<?php
// Start session to get current user
session_start();

// Database connection
require_once '../config/db_connect.php';

// Set content type to JSON and configure error handling for production
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Production error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/payment_entry_errors.log');
error_reporting(E_ALL);

// Create logs directory if it doesn't exist
$logsDir = '../logs/';
if (!file_exists($logsDir)) {
    @mkdir($logsDir, 0777, true);
}

// Function to log errors with context
function logPaymentError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 'unknown';
    $logMessage = "[$timestamp] [User: $userId] $message";
    if (!empty($context)) {
        $logMessage .= " | Context: " . json_encode($context);
    }
    error_log($logMessage . "\n", 3, '../logs/payment_entry_errors.log');
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logPaymentError('Invalid request method attempted', ['method' => $_SERVER['REQUEST_METHOD']]);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    logPaymentError('Unauthenticated access attempt', ['session_data' => $_SESSION]);
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}
    
    $created_by = $_SESSION['user_id'];
    $updated_by = $_SESSION['user_id'];
    
    // Log payment entry attempt
    logPaymentError('Payment entry save attempt started', [
        'user_id' => $created_by,
        'post_data_keys' => array_keys($_POST),
        'files_data_keys' => array_keys($_FILES)
    ]);
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Collect main payment data
        $projectType = isset($_POST['projectType']) ? $_POST['projectType'] : '';
        $projectId = isset($_POST['projectName']) ? $_POST['projectName'] : 0;
        $paymentDate = isset($_POST['paymentDate']) ? $_POST['paymentDate'] : '';
        $paymentAmount = isset($_POST['paymentAmount']) ? $_POST['paymentAmount'] : 0;
        $paymentDoneVia = isset($_POST['paymentDoneVia']) ? $_POST['paymentDoneVia'] : 0;
        $paymentMode = isset($_POST['paymentMode']) ? $_POST['paymentMode'] : '';
        $recipientCount = isset($_POST['recipientCount']) ? $_POST['recipientCount'] : 0;
        
        // Validate required fields
        if (empty($projectType) || empty($projectId) || empty($paymentDate) || 
            empty($paymentAmount) || empty($paymentDoneVia) || empty($paymentMode)) {
            logPaymentError('Required main payment fields missing', [
                'projectType' => !empty($projectType),
                'projectId' => !empty($projectId),
                'paymentDate' => !empty($paymentDate),
                'paymentAmount' => !empty($paymentAmount),
                'paymentDoneVia' => !empty($paymentDoneVia),
                'paymentMode' => !empty($paymentMode)
            ]);
            throw new Exception('Required main payment fields are missing');
        }
        
        // Insert main payment entry
        $stmt = $pdo->prepare("
            INSERT INTO hr_payment_entries (
                project_type, 
                project_id, 
                payment_date, 
                payment_amount, 
                payment_done_via, 
                payment_mode,
                recipient_count,
                created_by,
                updated_by
            ) VALUES (
                :project_type,
                :project_id,
                :payment_date,
                :payment_amount,
                :payment_done_via,
                :payment_mode,
                :recipient_count,
                :created_by,
                :updated_by
            )
        ");
        
        $stmt->execute([
            ':project_type' => $projectType,
            ':project_id' => $projectId,
            ':payment_date' => $paymentDate,
            ':payment_amount' => $paymentAmount,
            ':payment_done_via' => $paymentDoneVia,
            ':payment_mode' => $paymentMode,
            ':recipient_count' => $recipientCount,
            ':created_by' => $created_by,
            ':updated_by' => $updated_by
        ]);
        
        $paymentId = $pdo->lastInsertId();
        
        // Process recipients
        if ($recipientCount > 0) {
            // Get all recipient keys
            $recipientKeys = array_keys($_POST['recipients'] ?? []);
            
            // Log recipient data for debugging
            logPaymentError('Processing recipients', [
                'recipient_count' => $recipientCount,
                'recipient_keys' => $recipientKeys,
                'recipients_data' => $_POST['recipients'] ?? []
            ]);
            
            foreach ($recipientKeys as $i) {
                // Check if this recipient exists in the form data
                if (!isset($_POST['recipients'][$i]) || !is_array($_POST['recipients'][$i])) {
                    continue;
                }
                
                $recipient = $_POST['recipients'][$i];
                
                // Extract recipient data
                $category = isset($recipient['category']) ? $recipient['category'] : '';
                $type = isset($recipient['type']) ? $recipient['type'] : '';
                $customType = isset($recipient['customType']) ? $recipient['customType'] : '';
                $entityId = isset($recipient['id']) ? $recipient['id'] : null;
                $name = isset($recipient['name']) ? $recipient['name'] : '';
                $paymentFor = isset($recipient['paymentFor']) ? $recipient['paymentFor'] : '';
                $amount = isset($recipient['amount']) ? $recipient['amount'] : 0;
                $recipientPaymentMode = isset($recipient['paymentMode']) ? $recipient['paymentMode'] : '';
                
                // For split payments, use "split_payment" as the payment mode if not set
                if (isset($recipient['splitPayments']) && is_array($recipient['splitPayments']) && !empty($recipient['splitPayments']) && empty($recipientPaymentMode)) {
                    $recipientPaymentMode = "split_payment";
                }
                
                // Validate required recipient fields
                if (empty($category) || empty($name) || empty($paymentFor) || empty($amount) || empty($recipientPaymentMode)) {
                    // Create detailed error message
                    $missingFields = [];
                    if (empty($category)) $missingFields[] = 'Category';
                    if (empty($name)) $missingFields[] = 'Name';
                    if (empty($paymentFor)) $missingFields[] = 'Payment For';
                    if (empty($amount)) $missingFields[] = 'Amount';
                    if (empty($recipientPaymentMode)) $missingFields[] = 'Payment Mode';
                    
                    throw new Exception("Required fields missing for recipient #$i: " . implode(', ', $missingFields));
                }
                
                // Insert recipient
                $stmt = $pdo->prepare("
                    INSERT INTO hr_payment_recipients (
                        payment_id,
                        category,
                        type,
                        custom_type,
                        entity_id,
                        name,
                        payment_for,
                        amount,
                        payment_mode,
                        created_by,
                        updated_by
                    ) VALUES (
                        :payment_id,
                        :category,
                        :type,
                        :custom_type,
                        :entity_id,
                        :name,
                        :payment_for,
                        :amount,
                        :payment_mode,
                        :created_by,
                        :updated_by
                    )
                ");
                
                $stmt->execute([
                    ':payment_id' => $paymentId,
                    ':category' => $category,
                    ':type' => $type,
                    ':custom_type' => $customType,
                    ':entity_id' => $entityId,
                    ':name' => $name,
                    ':payment_for' => $paymentFor,
                    ':amount' => $amount,
                    ':payment_mode' => $recipientPaymentMode,
                    ':created_by' => $created_by,
                    ':updated_by' => $updated_by
                ]);
                
                $recipientId = $pdo->lastInsertId();
                
                // Process bill/payment proof files
                if (isset($_FILES['recipients']) && isset($_FILES['recipients']['name'][$i]['billImages']) && 
                    is_array($_FILES['recipients']['name'][$i]['billImages'])) {
                    $files = $_FILES['recipients']['name'][$i]['billImages'];
                    $filesCount = count($files);
                    
                    for ($j = 0; $j < $filesCount; $j++) {
                        if (isset($_FILES['recipients']['error'][$i]['billImages'][$j]) && 
                            $_FILES['recipients']['error'][$i]['billImages'][$j] === UPLOAD_ERR_OK) {
                            $fileName = $_FILES['recipients']['name'][$i]['billImages'][$j];
                            $fileType = $_FILES['recipients']['type'][$i]['billImages'][$j];
                            $fileSize = $_FILES['recipients']['size'][$i]['billImages'][$j];
                            $fileTmpName = $_FILES['recipients']['tmp_name'][$i]['billImages'][$j];
                            
                            // Create organized directory structure
                            $uploadDir = "../uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/";
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($uploadDir)) {
                                if (!mkdir($uploadDir, 0777, true)) {
                                    logPaymentError('Failed to create upload directory', [
                                        'directory' => $uploadDir,
                                        'parent_exists' => file_exists(dirname($uploadDir)),
                                        'parent_writable' => file_exists(dirname($uploadDir)) ? is_writable(dirname($uploadDir)) : false
                                    ]);
                                    throw new Exception('Failed to create upload directory: ' . $uploadDir);
                                }
                            }
                            
                            // Check if directory is writable
                            if (!is_writable($uploadDir)) {
                                logPaymentError('Upload directory not writable', [
                                    'directory' => $uploadDir,
                                    'permissions' => substr(sprintf('%o', fileperms($uploadDir)), -4)
                                ]);
                                throw new Exception('Upload directory is not writable: ' . $uploadDir);
                            }
                            
                            // Generate organized filename
                            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                            $cleanFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                            $newFileName = $cleanFileName . '_' . uniqid() . '_' . time() . '.' . $fileExtension;
                            $destination = $uploadDir . $newFileName;
                            
                            // Store relative path for database
                            $relativePath = "uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/{$newFileName}";
                            
                            // Move uploaded file
                            if (move_uploaded_file($fileTmpName, $destination)) {
                                // Save file info to database
                                $stmt = $pdo->prepare("
                                    INSERT INTO hr_payment_documents (
                                        recipient_id,
                                        file_name,
                                        file_path,
                                        file_type,
                                        file_size
                                    ) VALUES (
                                        :recipient_id,
                                        :file_name,
                                        :file_path,
                                        :file_type,
                                        :file_size
                                    )
                                ");
                                
                                $stmt->execute([
                                    ':recipient_id' => $recipientId,
                                    ':file_name' => $fileName,
                                    ':file_path' => $relativePath,
                                    ':file_type' => $fileType,
                                    ':file_size' => $fileSize
                                ]);
                            }
                        }
                    }
                }
                
                // Process split payments if any
                if (isset($recipient['splitPayments']) && is_array($recipient['splitPayments'])) {
                    // Log split payment data for debugging
                    logPaymentError('Processing split payments for recipient', [
                        'recipient_id' => $recipientId,
                        'split_data' => $recipient['splitPayments']
                    ]);
                    
                    foreach ($recipient['splitPayments'] as $splitId => $split) {
                        // Skip if not a valid array element
                        if (!is_array($split)) {
                            logPaymentError('Skipping invalid split payment', [
                                'split_id' => $splitId,
                                'split_data' => $split
                            ]);
                            continue;
                        }
                        
                        $splitAmount = isset($split['amount']) ? $split['amount'] : 0;
                        $splitMode = isset($split['mode']) ? $split['mode'] : '';
                        $splitPaymentFor = isset($split['payment_for']) ? $split['payment_for'] : '';
                        
                        // Log extracted split data
                        logPaymentError('Split payment data extracted', [
                            'split_id' => $splitId,
                            'amount' => $splitAmount,
                            'mode' => $splitMode,
                            'payment_for' => $splitPaymentFor
                        ]);
                        
                        if (empty($splitAmount) || empty($splitMode) || empty($splitPaymentFor)) {
                            logPaymentError('Skipping split payment - missing required data', [
                                'split_id' => $splitId,
                                'amount_empty' => empty($splitAmount),
                                'mode_empty' => empty($splitMode),
                                'payment_for_empty' => empty($splitPaymentFor)
                            ]);
                            continue;
                        }
                        
                        // Insert split payment
                        $stmt = $pdo->prepare("
                            INSERT INTO hr_payment_splits (
                                recipient_id,
                                amount,
                                payment_mode,
                                payment_for
                            ) VALUES (
                                :recipient_id,
                                :amount,
                                :payment_mode,
                                :payment_for
                            )
                        ");
                        
                        $result = $stmt->execute([
                            ':recipient_id' => $recipientId,
                            ':amount' => $splitAmount,
                            ':payment_mode' => $splitMode,
                            ':payment_for' => $splitPaymentFor
                        ]);
                        
                        $splitPaymentId = $pdo->lastInsertId();
                        
                        // Log successful insertion
                        logPaymentError('Split payment inserted successfully', [
                            'split_payment_id' => $splitPaymentId,
                            'recipient_id' => $recipientId,
                            'amount' => $splitAmount,
                            'mode' => $splitMode,
                            'payment_for' => $splitPaymentFor
                        ]);
                        
                        // Process split payment proof if any
                        if (isset($_FILES['recipients']['name'][$i]['splitPayments'][$splitId]['proof']) && 
                            isset($_FILES['recipients']['error'][$i]['splitPayments'][$splitId]['proof']) &&
                            $_FILES['recipients']['error'][$i]['splitPayments'][$splitId]['proof'] === UPLOAD_ERR_OK) {
                            
                            $fileName = $_FILES['recipients']['name'][$i]['splitPayments'][$splitId]['proof'];
                            $fileTmpName = $_FILES['recipients']['tmp_name'][$i]['splitPayments'][$splitId]['proof'];
                            
                            // Create organized directory structure for split payments
                            $uploadDir = "../uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/splits/";
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            // Generate organized filename
                            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                            $cleanFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                            $newFileName = 'split_' . $splitPaymentId . '_' . $cleanFileName . '_' . uniqid() . '.' . $fileExtension;
                            $destination = $uploadDir . $newFileName;
                            
                            // Store relative path for database
                            $relativePath = "uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/splits/{$newFileName}";
                            
                            // Move uploaded file
                            if (move_uploaded_file($fileTmpName, $destination)) {
                                // Update split payment with proof file
                                $stmt = $pdo->prepare("
                                    UPDATE hr_payment_splits 
                                    SET proof_file = :proof_file 
                                    WHERE split_id = :split_id
                                ");
                                
                                $stmt->execute([
                                    ':proof_file' => $relativePath,
                                    ':split_id' => $splitPaymentId
                                ]);
                            }
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment entry added successfully',
            'payment_id' => $paymentId
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        // Log the error for debugging
        logPaymentError('Payment entry save failed', [
            'error_message' => $e->getMessage(),
            'user_id' => $_SESSION['user_id'],
            'post_data_count' => count($_POST),
            'files_count' => count($_FILES)
        ]);
        
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
?>