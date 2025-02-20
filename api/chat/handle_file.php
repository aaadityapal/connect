<?php
session_start();
require_once '../../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests for debugging
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET params: " . print_r($_GET, true));
error_log("POST params: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

// Set appropriate headers for file downloads
header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Function to get file details from database
function getFileDetails($fileId, $conn) {
    error_log("Searching for file ID: " . $fileId);
    
    // Updated query to match your table structure
    $query = "SELECT id, sender_id, receiver_id, content, file_url, original_filename, message_type 
              FROM messages 
              WHERE id = ? AND file_url IS NOT NULL";
              
    error_log("SQL Query: " . $query . " with ID: " . $fileId);
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("Number of rows found: " . $result->num_rows);
    
    if ($result->num_rows === 0) {
        error_log("No message found with ID: " . $fileId);
        return null;
    }
    
    $file = $result->fetch_assoc();
    error_log("Found message details: " . print_r($file, true));
    
    if (empty($file['file_url'])) {
        error_log("Message found but no file_url present");
        return null;
    }
    
    return $file;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    error_log("File upload started");
    
    $sender_id = $_SESSION['user_id'];
    $receiver_id = $_POST['receiver_id'] ?? null;
    $conversation_id = $_POST['conversation_id'] ?? null;
    $file = $_FILES['file'];
    
    // Create upload directory path
    $upload_dir = dirname(dirname(dirname(__FILE__))) . '/uploads/chat_files/';
    error_log("Upload directory: " . $upload_dir);
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        error_log("Creating upload directory");
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create upload directory");
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
            exit();
        }
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $unique_filename;
    $relative_path = 'uploads/chat_files/' . $unique_filename;
    
    error_log("Attempting to move file to: " . $file_path);
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        error_log("File moved successfully");
        
        // Updated query to match your table structure
        $query = "INSERT INTO messages (
            conversation_id, 
            sender_id, 
            receiver_id, 
            message_type, 
            content, 
            file_url, 
            original_filename, 
            sent_at
        ) VALUES (?, ?, ?, 'file', '', ?, ?, NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiss", 
            $conversation_id,
            $sender_id, 
            $receiver_id, 
            $relative_path, 
            $file['name']
        );
        
        if ($stmt->execute()) {
            error_log("File information saved to database");
            echo json_encode([
                'success' => true,
                'file_url' => $relative_path,
                'original_filename' => $file['name'],
                'message_id' => $conn->insert_id
            ]);
        } else {
            error_log("Database error: " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        error_log("Failed to move uploaded file");
        echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
    }
}

// Handle file download
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['file_id'])) {
    $file_id = $_GET['file_id'];
    error_log("Attempting to download file ID: " . $file_id);
    
    $file_details = getFileDetails($file_id, $conn);
    
    if (!$file_details) {
        error_log("No file details found in database");
        echo json_encode(['success' => false, 'error' => 'File not found in database']);
        exit();
    }
    
    // Get the absolute path to the file
    $file_path = realpath(dirname(dirname(dirname(__FILE__))) . '/' . $file_details['file_url']);
    error_log("Full file path: " . $file_path);
    
    if (!$file_path || !file_exists($file_path)) {
        error_log("File does not exist at path: " . $file_path);
        echo json_encode([
            'success' => false, 
            'error' => 'File not found on server',
            'debug' => [
                'file_path' => $file_path,
                'file_url' => $file_details['file_url'],
                'base_path' => dirname(dirname(dirname(__FILE__)))
            ]
        ]);
        exit();
    }
    
    error_log("File exists, attempting to send...");
    
    // Clear any previous output
    ob_clean();
    flush();
    
    // Set headers for file download
    $mime_type = mime_content_type($file_path) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . basename($file_details['original_filename']) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output file content
    readfile($file_path);
    exit();
}
?> 