<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Log all POST data
    error_log('POST data received: ' . print_r($_POST, true));
    error_log('FILES data received: ' . print_r($_FILES, true));

    // Get form data
    $projectId = $_POST['project_id'] ?? null;
    $stageId = $_POST['stage_id'] ?? null;
    $substageId = $_POST['substage_id'] ?? null;
    $fileName = $_POST['file_name'] ?? null;

    error_log("Parsed values - Project ID: '$projectId', Stage ID: '$stageId', Substage ID: '$substageId'");

    // Debug log
    error_log("Received values - Project ID: $projectId, Stage ID: $stageId, Substage ID: $substageId");

    // Validate required fields
    if (!$projectId || !$stageId || !$substageId || !$fileName || empty($_FILES['file'])) {
        throw new Exception('Missing required fields');
    }

    // Debug query
    $checkQuery = "
        SELECT 
            p.id as project_id,
            ps.id as stage_id,
            psub.id as substage_id
        FROM project_substages psub
        JOIN project_stages ps ON psub.stage_id = ps.id
        JOIN projects p ON ps.project_id = p.id
        WHERE psub.id = :substage_id
        AND ps.id = :stage_id
        AND p.id = :project_id
        AND p.deleted_at IS NULL
        AND ps.deleted_at IS NULL
        AND psub.deleted_at IS NULL
    ";

    $stmt = $pdo->prepare($checkQuery);
    $stmt->bindValue(':substage_id', $substageId, PDO::PARAM_INT);
    $stmt->bindValue(':stage_id', $stageId, PDO::PARAM_INT);
    $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
    
    // Debug log the query
    error_log("Executing query: " . str_replace([':substage_id', ':stage_id', ':project_id'], 
                                              [$substageId, $stageId, $projectId], 
                                              $checkQuery));
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        // Debug log
        error_log("No matching records found for Project ID: $projectId, Stage ID: $stageId, Substage ID: $substageId");
        throw new Exception('Invalid substage');
    }

    // Debug log
    error_log("Validation successful. Found record: " . print_r($result, true));

    // File handling
    $file = $_FILES['file'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Invalid file type');
    }

    // Create unique file name
    $uniqueFileName = uniqid('file_') . '.' . $fileExtension;
    $uploadDir = 'uploads/project_files/' . $projectId . '/' . $stageId . '/' . $substageId . '/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filePath = $uploadDir . $uniqueFileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload file');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Insert file record
    $stmt = $pdo->prepare("
        INSERT INTO project_files (
            project_id, 
            stage_id, 
            substage_id, 
            file_name, 
            file_path, 
            file_type, 
            file_size, 
            uploaded_by,
            uploaded_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $projectId,
        $stageId,
        $substageId,
        $fileName,
        $filePath,
        $fileExtension,
        $file['size'],
        $_SESSION['user_id']
    ]);

    $fileId = $pdo->lastInsertId();

    // Log the activity
    $stmt = $pdo->prepare("
        INSERT INTO project_activity_log (
            project_id, 
            stage_id, 
            substage_id, 
            activity_type, 
            description, 
            performed_by,
            performed_at
        ) VALUES (?, ?, ?, 'file_upload', ?, ?, NOW())
    ");
    
    $stmt->execute([
        $projectId,
        $stageId,
        $substageId,
        "File '{$fileName}' uploaded",
        $_SESSION['user_id']
    ]);

    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'file' => [
            'id' => $fileId,
            'name' => $fileName,
            'path' => $filePath,
            'size' => $file['size'],
            'type' => $fileExtension,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Delete uploaded file if it exists
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Log error with more details
    error_log("File upload error: " . $e->getMessage() . 
              " Project ID: $projectId, Stage ID: $stageId, Substage ID: $substageId");
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 