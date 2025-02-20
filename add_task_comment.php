<?php
session_start();
require_once 'config.php';

try {
    // Log incoming data
    error_log("Received POST data: " . print_r($_POST, true));

    // Validate required fields
    if (!isset($_POST['task_id']) || !isset($_POST['stage_number']) || !isset($_POST['comment_text'])) {
        throw new Exception('Missing required fields');
    }

    // Get and validate inputs
    $task_id = filter_var($_POST['task_id'], FILTER_VALIDATE_INT);
    $stage_number = filter_var($_POST['stage_number'], FILTER_VALIDATE_INT);
    $comment_text = trim($_POST['comment_text']);
    $substage_id = isset($_POST['substage_id']) ? filter_var($_POST['substage_id'], FILTER_VALIDATE_INT) : null;
    $created_by = $_SESSION['user_id'] ?? null;

    // Validate data
    if ($task_id === false || $stage_number === false) {
        throw new Exception('Invalid task_id or stage_number');
    }

    if (empty($comment_text)) {
        throw new Exception('Comment text cannot be empty');
    }

    if (!$created_by) {
        throw new Exception('User not authenticated');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert comment with explicit stage_number
        $insert_query = "
            INSERT INTO task_comments (
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
            )
        ";

        $stmt = $pdo->prepare($insert_query);
        $result = $stmt->execute([
            ':task_id' => $task_id,
            ':stage_number' => $stage_number,
            ':substage_id' => $substage_id,
            ':comment_text' => $comment_text,
            ':created_by' => $created_by
        ]);

        if (!$result) {
            throw new Exception('Failed to insert comment');
        }

        $comment_id = $pdo->lastInsertId();

        // Get the inserted comment with user details
        $select_query = "
            SELECT 
                tc.*,
                u.name as user_name
            FROM task_comments tc
            LEFT JOIN users u ON tc.created_by = u.id
            WHERE tc.id = :comment_id
        ";

        $select_stmt = $pdo->prepare($select_query);
        $select_stmt->execute([':comment_id' => $comment_id]);
        $comment = $select_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            throw new Exception('Failed to retrieve inserted comment');
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully',
            'comment' => $comment
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Error in add_task_comment.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 