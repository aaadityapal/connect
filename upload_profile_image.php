<?php
session_start();
require_once 'config.php';

// Check if user is HR
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_FILES['profile_image']) || !isset($_POST['employee_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
    exit();
}

try {
    $employeeId = $_POST['employee_id'];
    $file = $_FILES['profile_image'];
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = 'uploads/profile_images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $employeeId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Delete old profile image if exists
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = :id");
    $stmt->execute(['id' => $employeeId]);
    $oldImage = $stmt->fetchColumn();
    
    if ($oldImage && file_exists($uploadDir . $oldImage)) {
        unlink($uploadDir . $oldImage);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Error uploading file');
    }

    // Update database
    $stmt = $pdo->prepare("UPDATE users SET profile_image = :image WHERE id = :id");
    $stmt->execute([
        'image' => $filename,
        'id' => $employeeId
    ]);

    echo json_encode([
        'success' => true,
        'image_url' => $filepath
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
