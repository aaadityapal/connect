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

// Permission check: can_upload_substage_media must be granted
$permTableResult = $conn->query("SHOW TABLES LIKE 'project_permissions'");
$hasPermTable = $permTableResult && $permTableResult->num_rows > 0;
if (!$hasPermTable) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Upload permission table missing']);
    exit;
}

$permColResult = $conn->query("SHOW COLUMNS FROM project_permissions LIKE 'can_upload_substage_media'");
$hasMediaPermColumn = $permColResult && $permColResult->num_rows > 0;
if (!$hasMediaPermColumn) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Missing can_upload_substage_media permission column. Please run migration file.']);
    exit;
}

$permStmt = $conn->prepare("SELECT can_upload_substage_media FROM project_permissions WHERE user_id = ? LIMIT 1");
if (!$permStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Permission check failed']);
    exit;
}

$permStmt->bind_param('i', $_SESSION['user_id']);
$permStmt->execute();
$permResult = $permStmt->get_result();
$permRow = $permResult ? $permResult->fetch_assoc() : null;
$canUploadMedia = $permRow && isset($permRow['can_upload_substage_media']) && (int)$permRow['can_upload_substage_media'] === 1;

if (!$canUploadMedia) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to upload media files']);
    exit;
}

// Validate inputs
if (!isset($_POST['substageId']) || !isset($_POST['fileName']) || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $substageId = (int)$_POST['substageId'];
    $fileName = trim((string)$_POST['fileName']);
    $columnType = isset($_POST['columnType']) ? trim((string)$_POST['columnType']) : 'general';
    $file = $_FILES['file'];
    $currentDateTime = date('Y-m-d H:i:s');

    // First verify that the substage exists
    $checkStmt = $conn->prepare(
        "SELECT
            s.project_id,
            s.stage_number,
            p.title AS project_title,
            ps.stage_id,
            ps.substage_number,
            ps.title AS substage_title,
            ps.status,
            ps.deleted_at
         FROM project_substages ps
         JOIN project_stages s ON ps.stage_id = s.id
         JOIN projects p ON s.project_id = p.id
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

    $projectId = (int)$substageData['project_id'];
    $stageId = (int)$substageData['stage_id'];
    $stageNumber = isset($substageData['stage_number']) ? (int)$substageData['stage_number'] : 0;
    $substageNumber = isset($substageData['substage_number']) ? (int)$substageData['substage_number'] : 0;
    $projectTitle = (string)($substageData['project_title'] ?? '');
    $substageTitle = (string)($substageData['substage_title'] ?? '');

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

    $uploadedFileId = (int)$conn->insert_id;

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

    // Log detailed upload activity in global_activity_logs
    try {
        $metadata = [
            'event' => 'substage_media_uploaded',
            'project_id' => $projectId,
            'project_title' => $projectTitle,
            'stage_id' => $stageId,
            'stage_number' => $stageNumber,
            'substage_id' => $substageId,
            'substage_number' => $substageNumber,
            'substage_title' => $substageTitle,
            'upload' => [
                'substage_file_id' => $uploadedFileId,
                'custom_file_name' => $fileName,
                'original_file_name' => (string)($file['name'] ?? ''),
                'stored_file_name' => $uniqueFileName,
                'relative_path' => $relativePath,
                'mime_type' => (string)($file['type'] ?? ''),
                'category' => $columnType,
                'size_bytes' => isset($file['size']) ? (int)$file['size'] : 0,
                'uploaded_at' => $currentDateTime
            ]
        ];

        $description = sprintf(
            'Media uploaded to %s (Project: %s, Stage %d, Substage %d): %s',
            ($substageTitle !== '' ? $substageTitle : ('Substage #' . $substageId)),
            ($projectTitle !== '' ? $projectTitle : ('Project #' . $projectId)),
            $stageNumber,
            $substageNumber,
            $fileName
        );

        $logStmt = $conn->prepare(
            "INSERT INTO global_activity_logs (
                user_id,
                action_type,
                entity_type,
                entity_id,
                description,
                metadata,
                created_at,
                is_read,
                is_dismissed
            ) VALUES (?, 'substage_media_uploaded', 'substage_file', ?, ?, ?, NOW(), 0, 0)"
        );

        if ($logStmt) {
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $actorId = (int)$_SESSION['user_id'];
            $entityId = $uploadedFileId > 0 ? $uploadedFileId : $substageId;
            $logStmt->bind_param('iiss', $actorId, $entityId, $description, $metadataJson);
            $logStmt->execute();
        }
    } catch (Throwable $logError) {
        error_log('Activity log insert failed for substage upload: ' . $logError->getMessage());
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