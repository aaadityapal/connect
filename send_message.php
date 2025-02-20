<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

// Debug log
error_log("Received POST request: " . print_r($_POST, true));
error_log("Received FILES: " . print_r($_FILES, true));

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['receiver_id'])) {
    $response['message'] = 'No receiver specified';
    echo json_encode($response);
    exit;
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'];
$message = $_POST['message'] ?? '';

// Debug log
error_log("Processing message from {$sender_id} to {$receiver_id}: {$message}");

// Handle file uploads
$file_paths = [];
if (isset($_FILES['files'])) {
    $upload_dir = 'uploads/chat/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['files']['name'][$key];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_name = uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_name;
            
            if (move_uploaded_file($tmp_name, $upload_path)) {
                $file_paths[] = $upload_path;
            }
        }
    }
}

try {
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, file_path) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $file_path = !empty($file_paths) ? json_encode($file_paths) : null;
    $stmt->bind_param("iiss", $sender_id, $receiver_id, $message, $file_path);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Message sent successfully';
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Database error: " . $e->getMessage());
}

echo json_encode($response); 