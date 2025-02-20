<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

$userId = (int)$_GET['user_id'];

try {
    // Get user details
    $userQuery = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $userResult = $stmt->get_result();
    $user = $userResult->fetch_assoc();

    // Get tasks statistics
    $statsQuery = "SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN due_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_tasks
    FROM tasks 
    WHERE assigned_to = ?";
    
    $stmt = $conn->prepare($statsQuery);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $statsResult = $stmt->get_result();
    $stats = $statsResult->fetch_assoc();

    // Get detailed tasks
    $tasksQuery = "SELECT 
        t.*,
        u.username as assigned_by_name
    FROM tasks t
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.assigned_to = ?
    ORDER BY 
        CASE t.status
            WHEN 'Pending' THEN 1
            WHEN 'In Progress' THEN 2
            WHEN 'On Hold' THEN 3
            WHEN 'Completed' THEN 4
            ELSE 5
        END,
        t.due_date ASC";

    $stmt = $conn->prepare($tasksQuery);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $tasksResult = $stmt->get_result();
    
    $tasks = [];
    while ($task = $tasksResult->fetch_assoc()) {
        $tasks[] = [
            'id' => $task['id'],
            'title' => htmlspecialchars($task['title']),
            'description' => htmlspecialchars($task['description']),
            'due_date' => date('d M Y', strtotime($task['due_date'])),
            'due_time' => date('h:i A', strtotime($task['due_time'])),
            'priority' => $task['priority'],
            'status' => $task['status'],
            'assigned_by' => htmlspecialchars($task['assigned_by_name'])
        ];
    }

    echo json_encode([
        'username' => $user['username'],
        'stats' => $stats,
        'tasks' => $tasks
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 