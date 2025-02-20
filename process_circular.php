<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Log all incoming data
error_log('POST Data: ' . print_r($_POST, true));
error_log('FILES Data: ' . print_r($_FILES, true));
error_log('SESSION Data: ' . print_r($_SESSION, true));

try {
    // Check login
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please log in first');
    }

    // Validate required fields
    if (empty($_POST['title']) || empty($_POST['content']) || 
        empty($_POST['valid_until']) || empty($_POST['status'])) {
        throw new Exception('Please fill all required fields');
    }

    // Prepare data
    $title = $_POST['title'];
    $content = $_POST['content'];
    $valid_until = $_POST['valid_until'];
    $status = $_POST['status'];
    $created_by = $_SESSION['user_id'];
    $attachment_path = null;

    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/circulars/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = uniqid() . '_' . $_FILES['attachment']['name'];
        $attachment_path = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
            throw new Exception('Failed to upload file');
        }
    }

    // Insert into database
    $sql = "INSERT INTO circulars (title, content, valid_until, status, attachment, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("sssssi", $title, $content, $valid_until, $status, $attachment_path, $created_by);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to save circular: " . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Circular saved successfully'
    ]);

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
