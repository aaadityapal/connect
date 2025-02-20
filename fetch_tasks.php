<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
session_start();

// Ensure no output before JSON
ob_start();
header('Content-Type: application/json');

try {
    // Debug session
    error_log("Session data: " . print_r($_SESSION, true));
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }
    
    $user_id = $_SESSION['user_id'];
    error_log("User ID: " . $user_id);
    
    // Test database connection
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Modified query to use due_date from task_stages
    $query = "
        SELECT 
            t.id, 
            t.title, 
            t.description, 
            tst.due_date,      -- Using due_date from task_stages
            t.created_by,
            tst.stage_number,
            tst.assigned_to,
            tst.priority as priority,
            tst.status as status,
            COALESCE(tp.priority_name, tst.priority) as priority_name,
            COALESCE(tp.priority_color, '#666666') as priority_color,
            COALESCE(ts.status_name, tst.status) as status_name,
            COALESCE(ts.status_color, '#666666') as status_color,
            (SELECT COUNT(*) FROM task_attachments ta WHERE ta.task_id = t.id) as attachment_count,
            (SELECT COUNT(*) FROM task_comments tc WHERE tc.task_id = t.id) as comment_count
        FROM tasks t
        INNER JOIN task_stages tst ON t.id = tst.task_id
        LEFT JOIN task_priorities tp ON tst.priority = tp.priority_name
        LEFT JOIN task_status ts ON tst.status = ts.status_name
        WHERE tst.assigned_to = :user_id
        GROUP BY t.id
        ORDER BY t.due_date ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug information
    error_log("Found " . count($tasks) . " tasks for user $user_id");
    if (count($tasks) > 0) {
        error_log("Sample task: " . print_r($tasks[0], true));
    }
    
    // Clear any output buffers
    ob_clean();
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'debug' => [
            'user_id' => $user_id,
            'task_count' => count($tasks),
            'first_task' => count($tasks) > 0 ? $tasks[0] : null
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in fetch_tasks.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clear any output buffers
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

// Ensure all output is sent
ob_end_flush();
exit;
?> 