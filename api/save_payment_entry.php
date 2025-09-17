<?php
// Database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                recipient_count
            ) VALUES (
                :project_type,
                :project_id,
                :payment_date,
                :payment_amount,
                :payment_done_via,
                :payment_mode,
                :recipient_count
            )
        ");
        
        $stmt->execute([
            ':project_type' => $projectType,
            ':project_id' => $projectId,
            ':payment_date' => $paymentDate,
            ':payment_amount' => $paymentAmount,
            ':payment_done_via' => $paymentDoneVia,
            ':payment_mode' => $paymentMode,
            ':recipient_count' => $recipientCount
        ]);
        
        $paymentId = $pdo->lastInsertId();
        
        // Log all POST data for debugging
        error_log('POST data: ' . print_r($_POST, true));
        
        // Process recipients
        if ($recipientCount > 0) {
            // Get all recipient keys
            $recipientKeys = array_keys($_POST['recipients'] ?? []);
            error_log('Recipient keys: ' . print_r($recipientKeys, true));
            
            foreach ($recipientKeys as $i) {
                // Check if this recipient exists in the form data
                if (!isset($_POST['recipients'][$i]) || !is_array($_POST['recipients'][$i])) {
                    error_log("Skipping recipient $i - not an array or not set");
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
                    error_log("Setting payment mode to 'split_payment' for recipient #$i with split payments");
                }
                
                // Validate required recipient fields
                if (empty($category) || empty($name) || empty($paymentFor) || empty($amount) || empty($recipientPaymentMode)) {
                    // Log the missing fields for debugging
                    error_log("Missing fields for recipient #$i - Category: $category, Name: $name, PaymentFor: $paymentFor, Amount: $amount, Mode: $recipientPaymentMode");
                    error_log("Full recipient data: " . print_r($recipient, true));
                    
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
                        payment_mode
                    ) VALUES (
                        :payment_id,
                        :category,
                        :type,
                        :custom_type,
                        :entity_id,
                        :name,
                        :payment_for,
                        :amount,
                        :payment_mode
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
                    ':payment_mode' => $recipientPaymentMode
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
                            
                            // Generate unique filename
                            $newFileName = uniqid() . '_' . time() . '_' . $fileName;
                            $uploadDir = '../uploads/payment_documents/';
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            $destination = $uploadDir . $newFileName;
                            
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
                                    ':file_path' => $newFileName,
                                    ':file_type' => $fileType,
                                    ':file_size' => $fileSize
                                ]);
                            }
                        }
                    }
                }
                
                // Process split payments if any
                if (isset($recipient['splitPayments']) && is_array($recipient['splitPayments'])) {
                    foreach ($recipient['splitPayments'] as $splitId => $split) {
                        // Skip if not a valid array element
                        if (!is_array($split)) {
                            continue;
                        }
                        
                        $splitAmount = isset($split['amount']) ? $split['amount'] : 0;
                        $splitMode = isset($split['mode']) ? $split['mode'] : '';
                        
                        if (empty($splitAmount) || empty($splitMode)) {
                            continue;
                        }
                        
                        // Insert split payment
                        $stmt = $pdo->prepare("
                            INSERT INTO hr_payment_splits (
                                recipient_id,
                                amount,
                                payment_mode
                            ) VALUES (
                                :recipient_id,
                                :amount,
                                :payment_mode
                            )
                        ");
                        
                        $stmt->execute([
                            ':recipient_id' => $recipientId,
                            ':amount' => $splitAmount,
                            ':payment_mode' => $splitMode
                        ]);
                        
                        $splitPaymentId = $pdo->lastInsertId();
                        
                        // Process split payment proof if any
                        if (isset($_FILES['recipients']['name'][$i]['splitPayments'][$splitId]['proof']) && 
                            isset($_FILES['recipients']['error'][$i]['splitPayments'][$splitId]['proof']) &&
                            $_FILES['recipients']['error'][$i]['splitPayments'][$splitId]['proof'] === UPLOAD_ERR_OK) {
                            
                            $fileName = $_FILES['recipients']['name'][$i]['splitPayments'][$splitId]['proof'];
                            $fileTmpName = $_FILES['recipients']['tmp_name'][$i]['splitPayments'][$splitId]['proof'];
                            
                            // Generate unique filename
                            $newFileName = 'split_' . uniqid() . '_' . time() . '_' . $fileName;
                            $uploadDir = '../uploads/payment_documents/';
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            $destination = $uploadDir . $newFileName;
                            
                            // Move uploaded file
                            if (move_uploaded_file($fileTmpName, $destination)) {
                                // Update split payment with proof file
                                $stmt = $pdo->prepare("
                                    UPDATE hr_payment_splits 
                                    SET proof_file = :proof_file 
                                    WHERE split_id = :split_id
                                ");
                                
                                $stmt->execute([
                                    ':proof_file' => $newFileName,
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
        
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>
