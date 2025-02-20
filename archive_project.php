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
    // Update project status to archived
    $stmt = $pdo->prepare("
        UPDATE projects 
        SET status = :status,
            archived_date = CURRENT_TIMESTAMP 
        WHERE id = :id
    ");
    
    $result = $stmt->execute([
        ':status' => 'archived',
        ':id' => $projectId
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Project archived successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to archive project']);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
?>
