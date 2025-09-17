<?php
// Database connection
require_once '../config/db_connect.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    function handleFileUpload($fileInput, $targetDir = '../uploads/labour_documents/') {
        if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] == UPLOAD_ERR_NO_FILE) {
            return '';
        }
        
        // Create directory if it doesn't exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
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
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $targetFilePath = $targetDir . $newFileName;
        
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
    
    // Handle document uploads if files are submitted
    if (isset($_FILES) && !empty($_FILES)) {
        if (isset($_FILES['aadharCard']) && $_FILES['aadharCard']['error'] != UPLOAD_ERR_NO_FILE) {
            $aadharResult = handleFileUpload('aadharCard');
            if (is_array($aadharResult) && isset($aadharResult['error'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Aadhar Card: ' . $aadharResult['error']
                ]);
                exit;
            }
            $aadharCard = $aadharResult;
        }
        
        if (isset($_FILES['panCard']) && $_FILES['panCard']['error'] != UPLOAD_ERR_NO_FILE) {
            $panResult = handleFileUpload('panCard');
            if (is_array($panResult) && isset($panResult['error'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'PAN Card: ' . $panResult['error']
                ]);
                exit;
            }
            $panCard = $panResult;
        }
        
        if (isset($_FILES['voterCard']) && $_FILES['voterCard']['error'] != UPLOAD_ERR_NO_FILE) {
            $voterResult = handleFileUpload('voterCard');
            if (is_array($voterResult) && isset($voterResult['error'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Voter ID: ' . $voterResult['error']
                ]);
                exit;
            }
            $voterId = $voterResult;
        }
        
        if (isset($_FILES['otherDocument']) && $_FILES['otherDocument']['error'] != UPLOAD_ERR_NO_FILE) {
            $otherResult = handleFileUpload('otherDocument');
            if (is_array($otherResult) && isset($otherResult['error'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Other Document: ' . $otherResult['error']
                ]);
                exit;
            }
            $otherDocument = $otherResult;
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
    
    // Insert data into the labours table
    $sql = "INSERT INTO hr_labours (
                full_name, 
                position, 
                position_custom,
                phone_number, 
                alternative_number, 
                join_date,
                labour_type,
                daily_salary,
                aadhar_card,
                pan_card,
                voter_id,
                other_document,
                address,
                city,
                state,
                notes
            ) VALUES (
                '$fullName', 
                '$position', 
                '$positionCustom',
                '$phoneNumber', 
                '$alternativeNumber', 
                '$joinDate',
                '$labourType',
                $dailySalary,
                '$aadharCard',
                '$panCard',
                '$voterId',
                '$otherDocument',
                '$address',
                '$city',
                '$state',
                '$notes'
            )";
    
    if (mysqli_query($conn, $sql)) {
        $labour_id = mysqli_insert_id($conn);
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
