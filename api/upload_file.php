<?php
// Prevent any unwanted output
ob_start();

require_once '../config/db_connect.php';

function handleFileUpload() {
    global $conn;
    
    try {
        // Start transaction
        $conn->begin_transaction();

        // Get POST data and file
        $fileName = $_POST['fileName'] ?? '';
        $substageId = $_POST['substageId'] ?? 0;
        $taskId = $_POST['taskId'] ?? 0;
        $userId = $_SESSION['user_id'] ?? 0; 

        // Validate inputs
        if (empty($fileName) || empty($substageId) || empty($taskId) || empty($userId)) {
            throw new Exception('Missing required fields');
        }
        
        // Handle file upload
        if (!isset($_FILES['file'])) {
            throw new Exception('No file uploaded');
        }

        $file = $_FILES['file'];
        $originalName = $conn->real_escape_string($file['name']);
        $fileType = $conn->real_escape_string($file['type']);
        $fileSize = $file['size'];
        $tmpPath = $file['tmp_name'];
        
        // Generate unique filename
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueFileName = uniqid() . '_' . time() . '.' . $extension;
        $uploadPath = '../uploads/' . $uniqueFileName;
        
        // Move uploaded file
        if (!move_uploaded_file($tmpPath, $uploadPath)) {
            throw new Exception('Failed to upload file');
        }

        // Insert into substages_files
        $stmt = $conn->prepare("
            INSERT INTO substages_files (
                substage_id,
                file_name,
                file_path,
                original_name,
                file_type,
                file_size,
                uploaded_by,
                uploaded_at,
                task_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "issssiis",
            $substageId,
            $fileName,
            $uniqueFileName,
            $originalName,
            $fileType,
            $fileSize,
            $userId,
            $taskId
        );
        
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        
        $fileId = $conn->insert_id;

        // Insert into task_status_history
        $stmt = $conn->prepare("
            INSERT INTO task_status_history (
                entity_type,
                entity_id,
                old_status,
                new_status,
                changed_by,
                changed_at,
                task_id,
                comment,
                file_path
            ) VALUES ('file', ?, NULL, 'uploaded', ?, NOW(), ?, 'New file uploaded', ?)
        ");
        
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "iiis",
            $fileId,
            $userId,
            $taskId,
            $uniqueFileName
        );
        
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        // Commit transaction
        $conn->commit();
        
        // Clear any output buffers
        ob_clean();
        
        // Set proper JSON header
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'fileId' => $fileId
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        // Clear any output buffers
        ob_clean();
        
        // Set proper JSON header
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleFileUpload();
} else {
    // Clear any output buffers
    ob_clean();
    
    // Set proper JSON header
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

// End output buffering
ob_end_flush();
?> 