<?php
// Prevent any output before JSON response
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once 'config/database.php';

try {
    // Validate POST data
    if (!isset($_POST['task_id']) || !isset($_POST['stage']) || !isset($_POST['comment'])) {
        throw new Exception('Missing required fields');
    }

    $taskId = intval($_POST['task_id']);
    $stage = intval($_POST['stage']);
    $comment = trim($_POST['comment']);
    $substage = isset($_POST['substage']) ? intval($_POST['substage']) : null;
    
    // Basic validation
    if (empty($comment)) {
        throw new Exception('Comment cannot be empty');
    }

    if ($taskId <= 0 || $stage <= 0) {
        throw new Exception('Invalid task_id or stage');
    }

    // Set default user ID if not in session
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

    // Prepare and execute query
    $query = "INSERT INTO task_timeline 
              (task_id, stage, substage, type, comment, user_id, created_at) 
              VALUES (?, ?, ?, 'comment', ?, ?, NOW())";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        $taskId,
        $stage,
        $substage,
        $comment,
        $userId
    ]);

    if (!$result) {
        throw new Exception('Failed to save comment');
    }

    // Clear any output buffers
    ob_clean();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully'
    ]);

} catch (Exception $e) {
    // Clear any output buffers
    ob_clean();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffer
ob_end_flush();
?> 