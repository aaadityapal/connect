<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (isset($_GET['task_id'])) {
    try {
        // Get task details
        $stmt = $pdo->prepare("
            SELECT 
                tasks.*,
                assigned.username as assigned_to_name,
                creator.username as created_by_name
            FROM tasks 
            JOIN users assigned ON tasks.assigned_to = assigned.id
            JOIN users creator ON tasks.created_by = creator.id
            WHERE tasks.id = ?
        ");
        $stmt->execute([$_GET['task_id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            // Get status updates
            $stmt = $pdo->prepare("
                SELECT * FROM task_status_updates 
                WHERE task_id = ? 
                ORDER BY updated_at DESC
            ");
            $stmt->execute([$_GET['task_id']]);
            $task['status_updates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get comments
            $stmt = $pdo->prepare("
                SELECT 
                    comments.*,
                    users.username as user_name
                FROM task_comments comments
                JOIN users ON comments.user_id = users.id
                WHERE task_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$_GET['task_id']]);
            $task['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'task' => $task]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Task ID not provided']);
}
?>

