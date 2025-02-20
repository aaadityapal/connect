<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = $_GET['task_id'] ?? null;

if (!$task_id) {
    echo json_encode(['success' => false, 'message' => 'Task ID required']);
    exit();
}

try {
    $query = "SELECT m.*, u.username, GROUP_CONCAT(
                JSON_OBJECT(
                    'name', a.file_name,
                    'path', a.file_path
                )
              ) as attachments
              FROM task_messages m
              LEFT JOIN users u ON m.user_id = u.id
              LEFT JOIN message_attachments a ON m.id = a.message_id
              WHERE m.task_id = ?
              GROUP BY m.id
              ORDER BY m.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
        $row['attachments'] = $row['attachments'] ? json_decode('[' . $row['attachments'] . ']', true) : null;
        $messages[] = $row;
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
