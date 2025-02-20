<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_id = $_POST['task_id'];
    $title = $_POST['title'];
    $uploaded_by = $_SESSION['user_id'];
    
    // Verify that the task belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        die('Unauthorized access to this task');
    }
    
    // Handle file upload
    $file = $_FILES['document'];
    $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        die('Invalid file type');
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = 'uploads/task_documents/' . $new_filename;
    
    // Create directory if it doesn't exist
    if (!file_exists('uploads/task_documents')) {
        mkdir('uploads/task_documents', 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO task_documents (task_id, title, file_name, file_path, uploaded_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$task_id, $title, $file['name'], $upload_path, $uploaded_by]);
        
        header('Location: employee_dashboard.php?upload=success');
    } else {
        die('Error uploading file');
    }
}

