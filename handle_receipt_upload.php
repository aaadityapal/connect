<?php
session_start();
require_once 'config/db_connect.php';

// Function to handle file upload
function uploadReceipt($file) {
    // Check if the upload directory exists, if not create it
    $uploadDir = 'uploads/receipts/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Get file info
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    
    // Check if file extension is allowed
    if (in_array($fileExt, $allowedExts)) {
        // Check if there was no error during upload
        if ($fileError === 0) {
            // Check file size (max 2MB)
            if ($fileSize <= 2097152) {
                // Create a unique file name to prevent overwriting
                $newFileName = uniqid('receipt_') . '.' . $fileExt;
                $fileDestination = $uploadDir . $newFileName;
                
                // Move uploaded file to destination
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    return $fileDestination;
                } else {
                    return ['error' => 'Failed to move uploaded file'];
                }
            } else {
                return ['error' => 'File size too large. Maximum file size is 2MB'];
            }
        } else {
            return ['error' => 'There was an error uploading your file'];
        }
    } else {
        return ['error' => 'Invalid file type. Allowed types: JPG, JPEG, PNG, PDF'];
    }
}

// Check if file was uploaded
if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] !== 4) { // 4 means no file was uploaded
    $uploadResult = uploadReceipt($_FILES['receipt']);
    
    if (isset($uploadResult['error'])) {
        echo json_encode(['status' => 'error', 'message' => $uploadResult['error']]);
    } else {
        echo json_encode(['status' => 'success', 'filePath' => $uploadResult]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
}
?> 