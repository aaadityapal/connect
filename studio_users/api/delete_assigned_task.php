<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['task_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing task ID']);
    exit();
}

$taskId = $data['task_id'];

try {
    // Check user designation again for security on server side
    $stmtUser = $pdo->prepare("SELECT designation FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch();
    
    $designation = $user ? strtolower($user['designation']) : '';
    $isManager = (strpos($designation, 'manager') !== false);

    if (!$isManager) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Only managers can delete tasks']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE studio_assigned_tasks SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$taskId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Task not found or already deleted']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error', 'details' => $e->getMessage()]);
}
?>
