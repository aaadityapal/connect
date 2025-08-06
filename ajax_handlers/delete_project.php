<?php
require_once '../config/db_connect.php';

if (!isset($_POST['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

$project_id = $_POST['project_id'];

try {
    // Soft delete by updating deleted_at
    $sql = "UPDATE projects SET deleted_at = NOW() WHERE id = :project_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':project_id' => $project_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Project not found or already deleted']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>