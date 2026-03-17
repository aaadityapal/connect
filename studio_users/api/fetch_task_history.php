<?php
// =====================================================
// api/fetch_task_history.php
// Fetches the history of deadline extensions for a task
// =====================================================
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

if (!$taskId) {
    echo json_encode(['success' => false, 'error' => 'Invalid task ID']);
    exit();
}

try {
    // Fetch logs from global_activity_logs for this task
    $stmt = $pdo->prepare("
        SELECT 
            gal.created_at,
            gal.description,
            gal.metadata,
            u.username AS author_name
        FROM global_activity_logs gal
        LEFT JOIN users u ON gal.user_id = u.id
        WHERE gal.entity_type = 'task' 
          AND gal.entity_id = :taskId 
          AND gal.action_type = 'extend_deadline'
        ORDER BY gal.created_at DESC
    ");
    $stmt->execute([':taskId' => $taskId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $history = [];
    foreach ($logs as $log) {
        $meta = json_decode($log['metadata'] ?? '{}', true);
        
        $history[] = [
            'timestamp'    => $log['created_at'],
            'description'  => $log['description'],
            'author'       => $log['author_name'] ?? 'System',
            'old_deadline' => isset($meta['old_date']) ? ($meta['old_date'] . ($meta['old_time'] ? ' ' . $meta['old_time'] : '')) : null,
            'new_deadline' => isset($meta['new_date']) ? ($meta['new_date'] . ($meta['new_time'] ? ' ' . $meta['new_time'] : '')) : null
        ];
    }

    echo json_encode([
        'success' => true,
        'task_id' => $taskId,
        'count'   => count($history),
        'history' => $history
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
