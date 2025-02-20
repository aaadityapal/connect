<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once '../config/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Validate inputs
if (!isset($_POST['substageId']) || !isset($_POST['fileName']) || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $substageId = $_POST['substageId'];
    $fileName = $_POST['fileName'];
    $columnType = $_POST['columnType'];
    $file = $_FILES['file'];
    $currentDateTime = date('Y-m-d H:i:s');

    // First verify that the substage exists
    $checkStmt = $conn->prepare(
        "SELECT s.project_id, ps.status, ps.deleted_at 
         FROM project_substages ps 
         JOIN project_stages s ON ps.stage_id = s.id 
         WHERE ps.id = ?"
    );
    
    if (!$checkStmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $checkStmt->bind_param('i', $substageId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Substage ID does not exist');
    }

    $substageData = $result->fetch_assoc();

    // Check substage status
    if ($substageData['deleted_at'] !== null) {
        throw new Exception('Substage has been deleted');
    }

    if ($substageData['status'] === 'completed') {
        throw new Exception('Cannot upload files to completed substage');
    }

    $projectId = $substageData['project_id'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    // Create upload directory structure
    $uploadDir = "../uploads/projects/{$projectId}/stages/{$substageId}/";
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueFileName = uniqid() . '_' . $fileName . '.' . $fileExtension;
    $targetPath = $uploadDir . $uniqueFileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Error moving uploaded file');
    }

    // Save file info to database
    $sql = "INSERT INTO substage_files (
                substage_id, 
                file_name, 
                file_path, 
                type, 
                uploaded_by, 
                uploaded_at,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $relativePath = "uploads/projects/{$projectId}/stages/{$substageId}/" . $uniqueFileName;
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param(
        'isssiss',
        $substageId,
        $fileName,
        $relativePath,
        $columnType,
        $_SESSION['user_id'],
        $currentDateTime,
        $currentDateTime
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $stmt->error);
    }

    // Update the substage status to in_progress if it was pending
    $updateStmt = $conn->prepare(
        "UPDATE project_substages 
         SET status = 'in_progress', 
             updated_at = ? 
         WHERE id = ? 
         AND status = 'pending'"
    );
    
    if ($updateStmt) {
        $updateStmt->bind_param('si', $currentDateTime, $substageId);
        $updateStmt->execute();
    }

    echo json_encode([
        'success' => true, 
        'message' => 'File uploaded successfully',
        'file' => [
            'name' => $fileName,
            'path' => $relativePath,
            'type' => $columnType
        ]
    ]);

} catch (Exception $e) {
    error_log('File upload error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error uploading file: ' . $e->getMessage()
    ]);
} 