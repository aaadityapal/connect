<?php
// Start session to get current user
session_start();

// Database connection
require_once '../config/db_connect.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User not authenticated'
        ]);
        exit;
    }
    
    $created_by = $_SESSION['user_id'];
    $updated_by = $_SESSION['user_id'];
    // Collect form data - Basic information
    $fullName = mysqli_real_escape_string($conn, $_POST['fullName']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $positionCustom = isset($_POST['positionCustom']) ? mysqli_real_escape_string($conn, $_POST['positionCustom']) : '';
    $phoneNumber = mysqli_real_escape_string($conn, $_POST['phoneNumber']);
    $alternativeNumber = isset($_POST['alternativeNumber']) ? mysqli_real_escape_string($conn, $_POST['alternativeNumber']) : '';
    $joinDate = mysqli_real_escape_string($conn, $_POST['joinDate']);
    $labourType = mysqli_real_escape_string($conn, $_POST['labourType']);
    $dailySalary = isset($_POST['salary']) && !empty($_POST['salary']) ? mysqli_real_escape_string($conn, $_POST['salary']) : 'NULL';
    
    // Address information
    $address = isset($_POST['address']) ? mysqli_real_escape_string($conn, $_POST['address']) : '';
    $city = isset($_POST['city']) ? mysqli_real_escape_string($conn, $_POST['city']) : '';
    $state = isset($_POST['state']) ? mysqli_real_escape_string($conn, $_POST['state']) : '';
    
    // Additional notes
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';
    
    // File upload handling
    $aadharCard = '';
    $panCard = '';
    $voterId = '';
    $otherDocument = '';
    
    // Function to handle file uploads
    function handleFileUpload($fileInput, $labourId, $documentType, $targetDir = '../uploads/labour_documents/') {
        if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] == UPLOAD_ERR_NO_FILE) {
            return '';
        }
        
        // Create labour-specific directory
        $labourDir = $targetDir . $labourId . '/';
        if (!file_exists($labourDir)) {
            mkdir($labourDir, 0777, true);
        }
        
        $file = $_FILES[$fileInput];
        $fileName = $file['name'];
        $fileType = $file['type'];
        $fileTmpName = $file['tmp_name'];
        $fileError = $file['error'];
        $fileSize = $file['size'];
        
        // Validate file
        $maxSize = 5 * 1024 * 1024; // 5MB
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $newFileName = $documentType . '.' . $fileExtension;
        $targetFilePath = $labourDir . $newFileName;
        
        if ($fileSize > $maxSize) {
            return ['error' => 'File size exceeds the limit of 5MB'];
        }
        
        if (!in_array($fileType, $allowedTypes)) {
            return ['error' => 'Invalid file type. Only JPG, PNG, and PDF are allowed'];
        }
        
        if ($fileError !== UPLOAD_ERR_OK) {
            return ['error' => 'Error uploading file'];
        }
        
        // Upload file
        if (move_uploaded_file($fileTmpName, $targetFilePath)) {
            return $newFileName;
        } else {
            return ['error' => 'Failed to upload file'];
        }
    }
    
    // Validate required fields
    if (empty($fullName) || empty($position) || empty($phoneNumber) || empty($joinDate) || empty($labourType)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Required fields are missing'
        ]);
        exit;
    }
    
    // Insert data into the labours table first (without file names)
    $sql = "INSERT INTO hr_labours (
                full_name, 
                position, 
                position_custom,
                phone_number, 
                alternative_number, 
                join_date,
                labour_type,
                daily_salary,
                address,
                city,
                state,
                notes,
                created_by,
                updated_by
            ) VALUES (
                '$fullName', 
                '$position', 
                '$positionCustom',
                '$phoneNumber', 
                '$alternativeNumber', 
                '$joinDate',
                '$labourType',
                $dailySalary,
                '$address',
                '$city',
                '$state',
                '$notes',
                '$created_by',
                '$updated_by'
            )";
    
    if (mysqli_query($conn, $sql)) {
        $labour_id = mysqli_insert_id($conn);
        
        // Now handle document uploads with the labour ID
        $updateFilesSql = "UPDATE hr_labours SET ";
        $updateFields = [];
        $hasFiles = false;
        
        if (isset($_FILES) && !empty($_FILES)) {
            if (isset($_FILES['aadharCard']) && $_FILES['aadharCard']['error'] != UPLOAD_ERR_NO_FILE) {
                $aadharResult = handleFileUpload('aadharCard', $labour_id, 'aadhar');
                if (is_array($aadharResult) && isset($aadharResult['error'])) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Aadhar Card: ' . $aadharResult['error']
                    ]);
                    exit;
                }
                $updateFields[] = "aadhar_card = '$aadharResult'";
                $hasFiles = true;
            }
            
            if (isset($_FILES['panCard']) && $_FILES['panCard']['error'] != UPLOAD_ERR_NO_FILE) {
                $panResult = handleFileUpload('panCard', $labour_id, 'pan');
                if (is_array($panResult) && isset($panResult['error'])) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'PAN Card: ' . $panResult['error']
                    ]);
                    exit;
                }
                $updateFields[] = "pan_card = '$panResult'";
                $hasFiles = true;
            }
            
            if (isset($_FILES['voterCard']) && $_FILES['voterCard']['error'] != UPLOAD_ERR_NO_FILE) {
                $voterResult = handleFileUpload('voterCard', $labour_id, 'voter');
                if (is_array($voterResult) && isset($voterResult['error'])) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Voter ID: ' . $voterResult['error']
                    ]);
                    exit;
                }
                $updateFields[] = "voter_id = '$voterResult'";
                $hasFiles = true;
            }
            
            if (isset($_FILES['otherDocument']) && $_FILES['otherDocument']['error'] != UPLOAD_ERR_NO_FILE) {
                $otherResult = handleFileUpload('otherDocument', $labour_id, 'other');
                if (is_array($otherResult) && isset($otherResult['error'])) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Other Document: ' . $otherResult['error']
                    ]);
                    exit;
                }
                $updateFields[] = "other_document = '$otherResult'";
                $hasFiles = true;
            }
        }
        
        // Update the record with file names if any files were uploaded
        if ($hasFiles) {
            $updateFilesSql .= implode(', ', $updateFields) . " WHERE labour_id = $labour_id";
            mysqli_query($conn, $updateFilesSql);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Labour added successfully',
            'labour_id' => $labour_id
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}

// Close connection
mysqli_close($conn);
?>
