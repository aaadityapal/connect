<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Validate required fields
    if (empty($_POST['title']) || empty($_POST['description'])) {
        throw new Exception('Title and description are required');
    }

    // Handle file upload if present
    $attachment_path = null;
    if (isset($_FILES['attachment_path']) && $_FILES['attachment_path']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/circulars/'; // Make sure this directory exists
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['attachment_path']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        // Move uploaded file
        if (move_uploaded_file($_FILES['attachment_path']['tmp_name'], $target_path)) {
            $attachment_path = $target_path;
        }
    }

    // Prepare the SQL query
    $query = "INSERT INTO circulars (
        title, 
        description, 
        attachment_path, 
        valid_until, 
        created_by, 
        status
    ) VALUES (
        :title, 
        :description, 
        :attachment_path, 
        :valid_until, 
        :created_by, 
        'active'
    )";

    $stmt = $pdo->prepare($query);
    
    // Execute the query with parameters
    $result = $stmt->execute([
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'attachment_path' => $attachment_path,
        'valid_until' => !empty($_POST['valid_until']) ? $_POST['valid_until'] : null,
        'created_by' => $_SESSION['user_id']
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Circular added successfully'
        ]);
    } else {
        throw new Exception('Failed to add circular');
    }

} catch (Exception $e) {
    error_log('Error adding circular: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'post_data' => $_POST,
            'files' => isset($_FILES) ? $_FILES : 'No files uploaded',
            'error' => $e->getMessage()
        ]
    ]);
} 