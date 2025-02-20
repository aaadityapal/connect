<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

try {
    $substage_id = $_POST['substage_id'];
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    date_default_timezone_set('Asia/Kolkata');
    $current_time = date('Y-m-d H:i:s');

    // Get task_id through task_stages using stage_id from task_substages
    $task_query = $conn->prepare("
        SELECT ts.task_id 
        FROM task_stages ts 
        INNER JOIN task_substages tss ON ts.id = tss.stage_id 
        WHERE tss.id = ?
    ");
    $task_query->bind_param('i', $substage_id);
    $task_query->execute();
    $task_result = $task_query->get_result();
    
    if ($task_result->num_rows === 0) {
        throw new Exception('Substage not found');
    }
    
    $task_row = $task_result->fetch_assoc();
    $task_id = $task_row['task_id'];

    $conn->begin_transaction();

    // Insert comment if provided
    if (!empty($comment)) {
        $query = $conn->prepare("
            INSERT INTO task_status_history 
            (entity_type, entity_id, old_status, new_status, changed_by, changed_at, comment) 
            VALUES ('substage', ?, 'comment', 'comment', ?, ?, ?)
        ");
        $query->bind_param('iiss', $substage_id, $user_id, $current_time, $comment);
        $query->execute();
    }

    // Handle file uploads
    if (!empty($_FILES['files'])) {
        $upload_dir = '../../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            $original_name = $_FILES['files']['name'][$key];
            $file_size = $_FILES['files']['size'][$key];
            $file_type = $_FILES['files']['type'][$key];
            
            // Generate unique filename
            $file_name = uniqid() . '_' . $original_name;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($tmp_name, $file_path)) {
                // Insert file record into substage_files table
                $query = $conn->prepare("
                    INSERT INTO substage_files 
                    (substage_id, file_name, file_path, original_name, file_type, file_size, uploaded_by, uploaded_at, task_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $query->bind_param('issssisii', 
                    $substage_id, 
                    $file_name,
                    $file_name, // file_path is same as file_name
                    $original_name, 
                    $file_type, 
                    $file_size, 
                    $user_id, 
                    $current_time,
                    $task_id
                );
                $query->execute();
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Comment and files added successfully']);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 