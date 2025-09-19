<?php
// Start session to get current user
session_start();

// API endpoint to update labour information
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'User not authenticated'
        ]);
        exit;
    }
    
    $updated_by = $_SESSION['user_id'];
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed. Use POST.'
        ]);
        exit;
    }
    
    // Handle both JSON and form data
    $input = [];
    if (isset($_POST['labour_id'])) {
        // Form data (with possible file uploads)
        $input = $_POST;
    } else {
        // JSON data (backward compatibility)
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        if ($jsonInput) {
            $input = $jsonInput;
        }
    }
    
    // Check if labour ID is provided
    if (!isset($input['labour_id']) || empty($input['labour_id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Labour ID is required'
        ]);
        exit;
    }
    
    $labourId = intval($input['labour_id']);
    
    // Validate required fields
    if (empty($input['full_name']) || empty($input['phone_number']) || empty($input['position']) || empty($input['labour_type']) || empty($input['join_date'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Full name, phone number, position, labour type, and join date are required'
        ]);
        exit;
    }
    
    // Validate phone number format
    $phoneRegex = '/^[\d\s\-\(\)]+$/';
    if (!preg_match($phoneRegex, $input['phone_number'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid phone number format'
        ]);
        exit;
    }
    
    // Validate join date format
    if (!DateTime::createFromFormat('Y-m-d', $input['join_date'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid join date format. Use YYYY-MM-DD'
        ]);
        exit;
    }
    
    // Validate daily salary if provided
    if (!empty($input['daily_salary'])) {
        if (!is_numeric($input['daily_salary']) || $input['daily_salary'] < 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Daily salary must be a valid positive number'
            ]);
            exit;
        }
    }
    
    // Handle file uploads if present
    $uploadedFiles = [];
    $uploadMessages = [];
    
    if (isset($_FILES)) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        // Define document types and their corresponding file fields
        $documentTypes = [
            'aadhar_file' => 'aadhar',
            'pan_file' => 'pan',
            'voter_file' => 'voter',
            'other_file' => 'other'
        ];
        
        foreach ($documentTypes as $fileField => $docType) {
            if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fileField];
                
                // Validate file size
                if ($file['size'] > $maxFileSize) {
                    throw new Exception("File {$file['name']} is too large. Maximum size is 5MB.");
                }
                
                // Validate file type
                if (!in_array($file['type'], $allowedTypes)) {
                    throw new Exception("File {$file['name']} has invalid type. Only JPG, PNG, and PDF files are allowed.");
                }
                
                // Create labour-specific directory
                $labourDir = "../uploads/labour_documents/{$labourId}/";
                if (!file_exists($labourDir)) {
                    if (!mkdir($labourDir, 0755, true)) {
                        throw new Exception("Failed to create directory for labour documents.");
                    }
                }
                
                // Get file extension
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // Create standardized filename
                $newFileName = $docType . '.' . $fileExtension;
                $targetPath = $labourDir . $newFileName;
                
                // Remove existing file if it exists
                if (file_exists($targetPath)) {
                    unlink($targetPath);
                }
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $uploadedFiles[$docType] = $newFileName;
                    $uploadMessages[] = "Updated {$docType} document";
                    
                    // Update the input data to store filename in database (following save_labour.php pattern)
                    switch ($docType) {
                        case 'aadhar':
                            $input['aadhar_card'] = $newFileName;
                            break;
                        case 'pan':
                            $input['pan_card'] = $newFileName;
                            break;
                        case 'voter':
                            $input['voter_id'] = $newFileName;
                            break;
                        case 'other':
                            $input['other_document'] = $newFileName;
                            break;
                    }
                } else {
                    throw new Exception("Failed to upload {$docType} document.");
                }
            }
        }
    }
    
    // Fetch current labour data to preserve existing document information
    $currentSql = "SELECT aadhar_card, pan_card, voter_id, other_document FROM hr_labours WHERE labour_id = :labour_id";
    $currentStmt = $pdo->prepare($currentSql);
    $currentStmt->execute([':labour_id' => $labourId]);
    $currentData = $currentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentData) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Labour not found'
        ]);
        exit;
    }
    
    // Preserve existing document data if no new data provided
    // Only update if there's actual new content (not empty and not just whitespace)
    if (empty(trim($input['aadhar_card'] ?? ''))) {
        $input['aadhar_card'] = $currentData['aadhar_card'];
    }
    if (empty(trim($input['pan_card'] ?? ''))) {
        $input['pan_card'] = $currentData['pan_card'];
    }
    if (empty(trim($input['voter_id'] ?? ''))) {
        $input['voter_id'] = $currentData['voter_id'];
    }
    if (empty(trim($input['other_document'] ?? ''))) {
        $input['other_document'] = $currentData['other_document'];
    }
    
    // Debug logging (remove in production)
    error_log("Document preservation - Current data: " . json_encode($currentData));
    error_log("Document preservation - Final input: " . json_encode([
        'aadhar_card' => $input['aadhar_card'],
        'pan_card' => $input['pan_card'],
        'voter_id' => $input['voter_id'],
        'other_document' => $input['other_document']
    ]));
    
    // Prepare update query
    $sql = "UPDATE hr_labours SET 
                full_name = :full_name,
                position = :position,
                position_custom = :position_custom,
                phone_number = :phone_number,
                alternative_number = :alternative_number,
                join_date = :join_date,
                labour_type = :labour_type,
                daily_salary = :daily_salary,
                aadhar_card = :aadhar_card,
                pan_card = :pan_card,
                voter_id = :voter_id,
                other_document = :other_document,
                address = :address,
                city = :city,
                state = :state,
                notes = :notes,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE labour_id = :labour_id";
    
    $stmt = $pdo->prepare($sql);
    
    // Handle daily salary (set to NULL if empty)
    $dailySalary = !empty($input['daily_salary']) ? floatval($input['daily_salary']) : null;
    
    // Bind parameters
    $stmt->bindParam(':labour_id', $labourId, PDO::PARAM_INT);
    $stmt->bindParam(':full_name', $input['full_name'], PDO::PARAM_STR);
    $stmt->bindParam(':position', $input['position'], PDO::PARAM_STR);
    $stmt->bindParam(':position_custom', $input['position_custom'], PDO::PARAM_STR);
    $stmt->bindParam(':phone_number', $input['phone_number'], PDO::PARAM_STR);
    $stmt->bindParam(':alternative_number', $input['alternative_number'], PDO::PARAM_STR);
    $stmt->bindParam(':join_date', $input['join_date'], PDO::PARAM_STR);
    $stmt->bindParam(':labour_type', $input['labour_type'], PDO::PARAM_STR);
    $stmt->bindParam(':daily_salary', $dailySalary, PDO::PARAM_STR);
    $stmt->bindParam(':aadhar_card', $input['aadhar_card'], PDO::PARAM_STR);
    $stmt->bindParam(':pan_card', $input['pan_card'], PDO::PARAM_STR);
    $stmt->bindParam(':voter_id', $input['voter_id'], PDO::PARAM_STR);
    $stmt->bindParam(':other_document', $input['other_document'], PDO::PARAM_STR);
    $stmt->bindParam(':address', $input['address'], PDO::PARAM_STR);
    $stmt->bindParam(':city', $input['city'], PDO::PARAM_STR);
    $stmt->bindParam(':state', $input['state'], PDO::PARAM_STR);
    $stmt->bindParam(':notes', $input['notes'], PDO::PARAM_STR);
    $stmt->bindParam(':updated_by', $updated_by, PDO::PARAM_INT);
    
    // Execute the update
    $result = $stmt->execute();
    
    if ($result) {
        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            $successMessage = 'Labour updated successfully';
            if (!empty($uploadMessages)) {
                $successMessage .= '. ' . implode(', ', $uploadMessages);
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => $successMessage,
                'labour_id' => $labourId,
                'uploaded_files' => $uploadedFiles
            ]);
        } else {
            // No rows affected - labour might not exist or no changes made
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Labour not found or no changes made'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update labour'
        ]);
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error updating labour: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error occurred while updating labour'
    ]);
}
?>