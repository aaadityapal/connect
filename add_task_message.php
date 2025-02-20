<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $task_id = $_POST['task_id'] ?? null;
        $message = $_POST['message'] ?? '';
        $user_id = $_SESSION['user_id'];

        if (!$task_id) {
            throw new Exception('Task ID is required');
        }

        // Start transaction
        $conn->begin_transaction();

        // Insert message
        $query = "INSERT INTO task_messages (task_id, user_id, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iis", $task_id, $user_id, $message);
        
        if (!$stmt->execute()) {
            throw new Exception('Error saving message: ' . $stmt->error);
        }
        
        $message_id = $conn->insert_id;

        // Handle file attachments
        if (!empty($_FILES['attachments'])) {
            $upload_dir = 'uploads/messages/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }

            // Make sure the directory is writable
            if (!is_writable($upload_dir)) {
                throw new Exception('Upload directory is not writable');
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'text/plain', 'application/zip'];
            $max_size = 10 * 1024 * 1024; // 10MB

            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $file_name = $_FILES['attachments']['name'][$key];
                $file_size = $_FILES['attachments']['size'][$key];
                $file_type = $_FILES['attachments']['type'][$key];

                // Validate file
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("File type not allowed: $file_name");
                }

                if ($file_size > $max_size) {
                    throw new Exception("File too large: $file_name");
                }

                // Generate unique filename
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $unique_filename;

                // Move file
                if (!move_uploaded_file($tmp_name, $file_path)) {
                    throw new Exception("Failed to upload file: $file_name");
                }

                // Save file record
                $file_query = "INSERT INTO message_attachments (message_id, file_name, file_path) 
                             VALUES (?, ?, ?)";
                $file_stmt = $conn->prepare($file_query);
                $file_stmt->bind_param("iss", $message_id, $file_name, $file_path);
                
                if (!$file_stmt->execute()) {
                    throw new Exception('Error saving file record: ' . $file_stmt->error);
                }
            }
        }

        // Add to timeline
        $timeline_action = "Message added by " . $_SESSION['username'];
        if (!empty($_FILES['attachments'])) {
            $file_count = count(array_filter($_FILES['attachments']['name']));
            $timeline_action .= " with $file_count attachment(s)";
        }
        
        $timeline_query = "INSERT INTO task_timeline (task_id, action) VALUES (?, ?)";
        $timeline_stmt = $conn->prepare($timeline_query);
        $timeline_stmt->bind_param("is", $task_id, $timeline_action);
        
        if (!$timeline_stmt->execute()) {
            throw new Exception('Error updating timeline: ' . $timeline_stmt->error);
        }

        $conn->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in add_task_message.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
