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
    // Get all attachments for the task, including those from messages
    $query = "SELECT 
                a.id,
                a.file_name,
                a.file_path,
                a.uploaded_at,
                COALESCE(u.username, 'Unknown') as uploaded_by,
                'task' as source
              FROM task_attachments a
              LEFT JOIN users u ON u.id = ?
              WHERE a.task_id = ?
              UNION
              SELECT 
                ma.id,
                ma.file_name,
                ma.file_path,
                ma.uploaded_at,
                COALESCE(u.username, 'Unknown') as uploaded_by,
                'message' as source
              FROM message_attachments ma
              JOIN task_messages tm ON ma.message_id = tm.id
              LEFT JOIN users u ON tm.user_id = u.id
              WHERE tm.task_id = ?
              ORDER BY uploaded_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $_SESSION['user_id'], $task_id, $task_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Error executing query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $attachments = [];
    
    while ($row = $result->fetch_assoc()) {
        // Verify file exists
        if (file_exists($row['file_path'])) {
            $row['uploaded_at'] = date('M d, Y H:i', strtotime($row['uploaded_at']));
            $attachments[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'attachments' => $attachments
    ]);

} catch (Exception $e) {
    error_log("Error in get_task_attachments.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading attachments: ' . $e->getMessage()
    ]);
}
?>
