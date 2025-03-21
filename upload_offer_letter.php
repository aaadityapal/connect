<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['document'];
    $user_id = $_POST['user_id'] ?? null;

    if (!$user_id) {
        throw new Exception('User ID is required');
    }

    // Validate file type
    $allowed_types = ['application/pdf'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('Only PDF files are allowed');
    }

    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/uploads/offer_letters/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid('offer_letter_') . '.' . $file_extension;
    $file_path = $upload_dir . $unique_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Insert into database
    $query = "INSERT INTO offer_letters (user_id, file_name, original_name, file_path, file_size, upload_date, status) 
              VALUES (?, ?, ?, ?, ?, NOW(), 'pending')";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $user_id,
        $unique_filename,
        $file['name'],
        'uploads/offer_letters/' . $unique_filename,
        $file['size']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Offer letter uploaded successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 