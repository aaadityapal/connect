<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$projectId = $data['project_id'] ?? null;

if (!$projectId) {
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // First check if project exists and is archived
    $checkStmt = $pdo->prepare("SELECT id, status FROM projects WHERE id = ?");
    $checkStmt->execute([$projectId]);
    $project = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception('Project not found');
    }

    if ($project['status'] !== 'archived') {
        throw new Exception('Project is not archived');
    }

    // Update project status to active
    $stmt = $pdo->prepare("
        UPDATE projects 
        SET status = 'active',
            archived_date = NULL 
        WHERE id = ?
    ");
    
    $stmt->execute([$projectId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Project restored successfully']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 