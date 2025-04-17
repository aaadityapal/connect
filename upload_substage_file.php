<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Validate substage_id
    if (!isset($_POST['substage_id'])) {
        throw new Exception('Substage ID is required');
    }

    // Validate file name
    if (!isset($_POST['file_name']) || empty($_POST['file_name'])) {
        throw new Exception('File name is required');
    }

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $substageId = $_POST['substage_id'];
    $fileName = $_POST['file_name'];
    $uploadedBy = $_SESSION['user_id'];
    $file = $_FILES['file'];

    // Check if file has content (size > 0)
    if ($file['size'] <= 0) {
        throw new Exception('The uploaded file is empty (0 bytes)');
    }

    // Verify temp file exists and has content
    if (!file_exists($file['tmp_name']) || filesize($file['tmp_name']) <= 0) {
        throw new Exception('The uploaded file was not received properly');
    }

    // Validate substage exists and get project_id
    $stmt = $pdo->prepare("SELECT ps.id, ps.stage_id, pst.project_id 
                          FROM project_substages ps 
                          JOIN project_stages pst ON ps.stage_id = pst.id 
                          WHERE ps.id = ? AND ps.deleted_at IS NULL");
    $stmt->execute([$substageId]);
    $substageData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$substageData) {
        throw new Exception('Invalid substage');
    }

    // Get file information
    $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'];
    
    // Validate file type
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
    }

    // Create upload directory if it doesn't exist
    $uploadDir = 'uploads/substage_files/' . $substageId . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Replace the unique filename generation with this more robust version
    function generateUniqueFileName($originalName, $substageId) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $sanitizedName = preg_replace("/[^a-zA-Z0-9]/", "_", pathinfo($originalName, PATHINFO_FILENAME));
        $sanitizedName = substr($sanitizedName, 0, 30);
        
        return sprintf(
            '%d_%s_%s_%s.%s',
            $substageId,
            $timestamp,
            $random,
            $sanitizedName,
            $extension
        );
    }

    // Then use it in the file upload section
    $uniqueFileName = generateUniqueFileName($file['name'], $substageId);
    $filePath = $uploadDir . $uniqueFileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Verify the moved file exists and has content
    if (!file_exists($filePath) || filesize($filePath) <= 0) {
        throw new Exception('File was moved but is empty or missing');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert file record into database
        $stmt = $pdo->prepare("
            INSERT INTO substage_files (
                substage_id, 
                file_name, 
                file_path, 
                type, 
                uploaded_by, 
                uploaded_at,
                status,
                created_at
            ) VALUES (
                :substage_id,
                :file_name,
                :file_path,
                :type,
                :uploaded_by,
                NOW(),
                'pending',
                NOW()
            )
        ");

        $stmt->execute([
            'substage_id' => $substageId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'type' => $fileType,
            'uploaded_by' => $uploadedBy
        ]);

        // Get the inserted file ID
        $fileId = $pdo->lastInsertId();

        // Create file upload activity log using project_activity_log table
        $stmt = $pdo->prepare("
            INSERT INTO project_activity_log (
                project_id,
                stage_id,
                substage_id,
                activity_type,
                description,
                performed_by,
                performed_at
            ) VALUES (
                :project_id,
                :stage_id,
                :substage_id,
                'file_upload',
                :description,
                :performed_by,
                CURRENT_TIMESTAMP()
            )
        ");

        $stmt->execute([
            'project_id' => $substageData['project_id'],
            'stage_id' => $substageData['stage_id'],
            'substage_id' => $substageId,
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