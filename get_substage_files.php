<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Validate request parameters
$projectId = $_GET['project_id'] ?? null;
$stageId = $_GET['stage_id'] ?? null;
$substageId = $_GET['substage_id'] ?? null;

if (!$projectId || !$stageId || !$substageId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

try {
    // Query to get files
    $query = "
        SELECT 
            pf.id,
            pf.file_name,
            pf.file_path,
            pf.file_type,
            pf.file_size,
            pf.uploaded_by,
            pf.uploaded_at
        FROM project_files pf
        WHERE pf.project_id = :project_id 
        AND pf.stage_id = :stage_id 
        AND pf.substage_id = :substage_id
        AND pf.deleted_at IS NULL
        ORDER BY pf.uploaded_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':project_id' => $projectId,
        ':stage_id' => $stageId,
        ':substage_id' => $substageId
    ]);

    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'files' => $files
    ]);

} catch (Exception $e) {
    error_log("Error fetching substage files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching files'
    ]);
} 