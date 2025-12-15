<?php
// site/get_task_history.php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }

    $taskId = $_GET['task_id'] ?? null;

    if (!$taskId) {
        throw new Exception('Task ID is required');
    }

    // Fetch logs
    $query = "
        SELECT 
            l.action_type,
            l.old_status,
            l.new_status,
            l.details,
            l.created_at,
            u.username as performed_by_name,
            u.role as performed_by_role
        FROM construction_task_logs l
        LEFT JOIN users u ON l.performed_by = u.id
        WHERE l.task_id = ?
        ORDER BY l.created_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$taskId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>