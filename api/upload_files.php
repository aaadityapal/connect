<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['file'];
    $fileName = uniqid() . '_' . basename($file['name']);
    $uploadDir = '../uploads/';
    $uploadPath = $uploadDir . $fileName;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode([
            'status' => 'success',
            'file_path' => $uploadPath
        ]);
    } else {
        throw new Exception('Failed to move uploaded file');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;