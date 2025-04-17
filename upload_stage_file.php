<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Validate stage_id
    if (!isset($_POST['stage_id'])) {
        throw new Exception('Stage ID is required');
    }

    // Validate file name
    if (!isset($_POST['file_name']) || empty($_POST['file_name'])) {
        throw new Exception('File name is required');
    }

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $stageId = $_POST['stage_id'];
    $fileName = $_POST['file_name'];
    $uploadedBy = $_SESSION['user_id'];
    $file = $_FILES['file'];

    // Validate stage exists and get project_id
    $stmt = $pdo->prepare("SELECT ps.id, ps.project_id 
                          FROM project_stages ps 
                          WHERE ps.id = ? AND ps.deleted_at IS NULL");
    $stmt->execute([$stageId]);
    $stageData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stageData) {
        throw new Exception('Invalid stage');
    }

    // Get file information
    $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'];
    
    // Validate file type
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
    }

    // Create upload directory if it doesn't exist
    $uploadDir = 'uploads/stage_files/' . $stageId . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate a unique filename
    function generateUniqueFileName($originalName, $stageId) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $sanitizedName = preg_replace("/[^a-zA-Z0-9]/", "_", pathinfo($originalName, PATHINFO_FILENAME));
        $sanitizedName = substr($sanitizedName, 0, 30);
        
        return sprintf(
            'stage_%d_%s_%s_%s.%s',
            $stageId,
            $timestamp,
            $random,
            $sanitizedName,
            $extension
        );
    }

    // Generate unique filename and path
    $uniqueFileName = generateUniqueFileName($file['name'], $stageId);
    $filePath = $uploadDir . $uniqueFileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert file record into database
        $stmt = $pdo->prepare("
            INSERT INTO stage_files (
                stage_id, 
                file_name, 
                original_name,
                file_path, 
                file_type, 
                file_size,
                uploaded_by, 
                uploaded_at,
                status,
                created_at
            ) VALUES (
                :stage_id,
                :file_name,
                :original_name,
                :file_path,
                :file_type,
                :file_size,
                :uploaded_by,
                NOW(),
                'pending',
                NOW()
            )
        ");

        $stmt->execute([
            'stage_id' => $stageId,
            'file_name' => $uniqueFileName,
            'original_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'file_size' => $file['size'],
            'uploaded_by' => $uploadedBy
        ]);

        // Get the inserted file ID
        $fileId = $pdo->lastInsertId();

        // Create file upload activity log using project_activity_log table
        $stmt = $pdo->prepare("
            INSERT INTO project_activity_log (
                project_id,
                stage_id,
                activity_type,
                description,
                performed_by,
                performed_at
            ) VALUES (
                :project_id,
                :stage_id,
                'file_upload',
                :description,
                :performed_by,
                CURRENT_TIMESTAMP()
            )
        ");

        $stmt->execute([
            'project_id' => $stageData['project_id'],
            'stage_id' => $stageId,
            'description' => "Uploaded file: {$fileName}",
            'performed_by' => $uploadedBy
        ]);

        // Commit transaction
        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'file' => [
                'id' => $fileId,
                'name' => $fileName,
                'path' => $filePath,
                'type' => $fileType,
                'size' => $file['size'],
                'status' => 'pending'
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        // Delete uploaded file if database insertion fails
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        throw $e;
    }

} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 