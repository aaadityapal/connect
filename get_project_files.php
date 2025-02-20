<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $projectId = $_GET['project_id'] ?? null;
    $stageId = $_GET['stage_id'] ?? null;
    $substageId = $_GET['substage_id'] ?? null;

    if (!$projectId || !$stageId || !$substageId) {
        throw new Exception('Missing required parameters');
    }

    $query = "
        SELECT 
            pf.id,
            pf.project_id,
            pf.stage_id,
            pf.substage_id,
            pf.file_name,
            pf.file_path,
            pf.file_type,
            pf.file_size,
            pf.uploaded_by,
            pf.uploaded_at,
            u.username as uploaded_by_name
        FROM project_files pf
        JOIN users u ON pf.uploaded_by = u.id
        WHERE pf.project_id = ?
        AND pf.stage_id = ?
        AND pf.substage_id = ?
        AND pf.deleted_at IS NULL
        ORDER BY pf.uploaded_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$projectId, $stageId, $substageId]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($files as &$file) {
        if (isset($file['uploaded_at'])) {
            $file['uploaded_at'] = date('Y-m-d H:i:s', strtotime($file['uploaded_at']));
        }
        
        if (strpos($file['file_path'], '../') === 0) {
            $file['file_path'] = substr($file['file_path'], 3);
        }
        if (strpos($file['file_path'], './') === 0) {
            $file['file_path'] = substr($file['file_path'], 2);
        }
    }
    unset($file);

    echo json_encode([
        'success' => true,
        'files' => $files
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false
    ]);
} 