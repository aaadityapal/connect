<?php
// chat_file_upload_handler.php

session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}

try {
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileType = $file['type'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    $fileSize = $file['size'];

    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Generate unique filename
    $uniqueId = uniqid('chat_', true);
    $newFileName = $uniqueId . '_' . time() . '.' . $fileExt;

    // Define allowed file types
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];

    // Validate file extension
    if (!in_array($fileExt, $allowed)) {
        throw new Exception('File type not allowed');
    }

    // Validate file size (max 10MB)
    if ($fileSize > 10000000) {
        throw new Exception('File too large. Maximum size is 10MB');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = 'uploads/chat_files/' . date('Y/m/d');
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Move file to upload directory
    $destination = $uploadDir . '/' . $newFileName;
    if (!move_uploaded_file($fileTmpName, $destination)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Store file information in database
    $query = "INSERT INTO chat_files (
        file_name,
        original_name,
        file_type,
        file_size,
        file_path,
        uploaded_by,
        uploaded_at
    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "sssssi",
        $newFileName,
        $fileName,
        $fileType,
        $fileSize,
        $destination,
        $_SESSION['user_id']
    );

    if (!$stmt->execute()) {
        // Delete uploaded file if database insert fails
        unlink($destination);
        throw new Exception('Failed to save file information');
    }

    $fileId = $stmt->insert_id;

    echo json_encode([
        'success' => true,
        'file' => [
            'id' => $fileId,
            'name' => $fileName,
            'type' => $fileType,
            'size' => $fileSize,
            'url' => $destination
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}