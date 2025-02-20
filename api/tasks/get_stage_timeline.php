<?php
require_once 'config/db_connect.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['stage_id'])) {
        throw new Exception('Stage ID is required');
    }

    $stageId = intval($_GET['stage_id']);

    // Get stage details with task information
    $stageQuery = "
        SELECT ts.*, t.title as task_title 
        FROM task_stages ts
        LEFT JOIN tasks t ON ts.task_id = t.id
        WHERE ts.id = ?";
    $stageStmt = $conn->prepare($stageQuery);
    $stageStmt->bind_param('i', $stageId);
    $stageStmt->execute();
    $stage = $stageStmt->get_result()->fetch_assoc();

    if (!$stage) {
        throw new Exception('Stage not found');
    }

    // Get substages
    $substagesQuery = "SELECT * FROM task_substages WHERE stage_id = ? ORDER BY created_at";
    $substagesStmt = $conn->prepare($substagesQuery);
    $substagesStmt->bind_param('i', $stageId);
    $substagesStmt->execute();
    $substages = $substagesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get stage files
    $stageFilesQuery = "
        SELECT sf.*, u.username as uploader_name
        FROM stage_files sf
        LEFT JOIN users u ON sf.uploaded_by = u.id
        WHERE sf.stage_id = ?
        ORDER BY sf.uploaded_at DESC";
    $stageFilesStmt = $conn->prepare($stageFilesQuery);
    $stageFilesStmt->bind_param('i', $stageId);
    $stageFilesStmt->execute();
    $stageFiles = $stageFilesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get substage files
    $substageFiles = [];
    if (!empty($substages)) {
        $substageIds = array_column($substages, 'id');
        $placeholders = str_repeat('?,', count($substageIds) - 1) . '?';
        $substageFilesQuery = "
            SELECT sf.*, u.username as uploader_name
            FROM substage_files sf
            LEFT JOIN users u ON sf.uploaded_by = u.id
            WHERE sf.substage_id IN ($placeholders)
            ORDER BY sf.uploaded_at DESC";
        $substageFilesStmt = $conn->prepare($substageFilesQuery);
        
        $types = str_repeat('i', count($substageIds));
        $substageFilesStmt->bind_param($types, ...$substageIds);
        $substageFilesStmt->execute();
        $substageFiles = $substageFilesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get assigned user information
    if ($stage['assigned_to']) {
        $userQuery = "SELECT id, username, role FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param('i', $stage['assigned_to']);
        $userStmt->execute();
        $assignedUser = $userStmt->get_result()->fetch_assoc();
        $stage['assigned_user'] = $assignedUser;
    }

    echo json_encode([
        'success' => true,
        'stage' => $stage,
        'substages' => $substages,
        'stageFiles' => $stageFiles,
        'substageFiles' => $substageFiles
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}