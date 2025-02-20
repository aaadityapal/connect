<?php
require_once 'config.php';

try {
    // Validate input
    $taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $stageNumber = isset($_POST['stage_number']) ? intval($_POST['stage_number']) : null;
    $substageId = isset($_POST['substage_id']) ? intval($_POST['substage_id']) : null;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    if (!$taskId || !$comment) {
        throw new Exception('Invalid input parameters');
    }

    // Insert the comment
    $query = "INSERT INTO task_comments (
        task_id, 
        stage_number, 
        substage_id, 
        comment_text, 
        created_by, 
        created_at
    ) VALUES (
        :task_id,
        :stage_number,
        :substage_id,
        :comment_text,
        :created_by,
        NOW()
    )";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'task_id' => $taskId,
        'stage_number' => $stageNumber,
        'substage_id' => $substageId,
        'comment_text' => $comment,
        'created_by' => $_SESSION['user_id'] // Assuming you have user session
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 