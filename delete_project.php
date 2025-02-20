<?php
session_start();
require_once 'config.php';

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
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND status = 'archived'");
    $result = $stmt->execute([$projectId]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete project']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
