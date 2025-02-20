<?php
// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

try {
    $substage_id = isset($_GET['substage_id']) ? intval($_GET['substage_id']) : 0;
    
    if ($substage_id <= 0) {
        throw new Exception('Invalid substage ID');
    }

    // Get files
    $files_query = $conn->prepare("
        SELECT sf.*, u.username as uploaded_by_name 
        FROM substage_files sf
        LEFT JOIN users u ON sf.uploaded_by = u.id
        WHERE sf.substage_id = ?
    ");
    $files_query->bind_param('i', $substage_id);
    $files_query->execute();
    $files_result = $files_query->get_result();
    
    $files = [];
    while ($file = $files_result->fetch_assoc()) {
        $files[] = [
            'id' => $file['id'],
            'original_name' => $file['original_name'],
            'file_path' => $file['file_path'],
            'file_size' => $file['file_size'],
            'uploaded_by' => $file['uploaded_by_name'],
            'uploaded_at' => $file['uploaded_at']
        ];
    }

    // Fetch status history - modified query to use only task_status_history table
    $status_query = "SELECT 
        tsh.*,
        u.username as changed_by
    FROM task_status_history tsh 
    LEFT JOIN users u ON tsh.changed_by = u.id 
    WHERE tsh.entity_type = 'substage' 
    AND tsh.entity_id = ?
    ORDER BY tsh.changed_at DESC";
    
    $stmt = $conn->prepare($status_query);
    $stmt->bind_param("i", $substage_id);
    $stmt->execute();
    $status_result = $stmt->get_result();
    $status_history = [];
    
    while ($row = $status_result->fetch_assoc()) {
        $status_history[] = [
            'changed_at' => $row['changed_at'],
            'changed_by' => $row['changed_by'],
            'old_status' => $row['old_status'],
            'new_status' => $row['new_status']
        ];
    }

    // Get comments
    $comments_query = $conn->prepare("
        SELECT 
            tsh.*, 
            u.username as username,
            tsh.file_path
        FROM task_status_history tsh
        LEFT JOIN users u ON tsh.changed_by = u.id
        WHERE tsh.entity_type = 'substage' 
        AND tsh.entity_id = ?
        ORDER BY tsh.changed_at DESC
    ");
    $comments_query->bind_param('i', $substage_id);
    $comments_query->execute();
    $comments_result = $comments_query->get_result();
    
    $comments = [];
    while ($comment = $comments_result->fetch_assoc()) {
        $comments[] = [
            'comment' => $comment['comment'],
            'username' => $comment['username'],
            'changed_at' => $comment['changed_at'],
            'file_path' => $comment['file_path']
        ];
    }

    // Return the response
    echo json_encode([
        'success' => true,
        'status_history' => $status_history,
        'comments' => $comments ?? [],
        'files' => $files
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 