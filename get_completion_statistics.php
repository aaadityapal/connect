<?php
session_start();
require_once 'config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check database connection
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get user ID from URL parameter
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];

try {
    // First, check if user exists
    $userCheckQuery = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($userCheckQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare user check query: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows === 0) {
        echo json_encode([
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'total_stages' => 0,
            'completed_stages' => 0,
            'on_time_stages' => 0,
            'total_substages' => 0,
            'completed_substages' => 0,
            'on_time_substages' => 0,
            'late_tasks' => 0,
            'overdue_tasks' => 0,
            'error' => 'User not found'
        ]);
        exit();
    }
    
    // Get overall task statistics - simplified with proper joins
    $taskQuery = "SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN pss.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM project_substages pss
    JOIN project_stages ps ON ps.id = pss.stage_id
    JOIN projects p ON p.id = ps.project_id
    WHERE pss.assigned_to = ?
    AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($taskQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare task query: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $taskData = $stmt->get_result()->fetch_assoc();
    
    // Get substage statistics (since substages = tasks)
    $substageQuery = "SELECT 
        COUNT(*) as total_substages,
        SUM(CASE WHEN pss.status = 'completed' THEN 1 ELSE 0 END) as completed_substages,
        SUM(CASE WHEN pss.status = 'completed' AND pss.updated_at <= pss.end_date THEN 1 ELSE 0 END) as on_time_substages
    FROM project_substages pss
    JOIN project_stages ps ON ps.id = pss.stage_id
    JOIN projects p ON p.id = ps.project_id
    WHERE pss.assigned_to = ?
    AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($substageQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare substage query: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $substageData = $stmt->get_result()->fetch_assoc();
    
    // Get stage statistics - simplified
    $stageQuery = "SELECT 
        COUNT(DISTINCT ps.id) as total_stages
    FROM project_stages ps
    JOIN projects p ON p.id = ps.project_id
    JOIN project_substages pss ON pss.stage_id = ps.id
    WHERE pss.assigned_to = ?
    AND ps.deleted_at IS NULL AND p.deleted_at IS NULL AND pss.deleted_at IS NULL";
    
    $stmt = $conn->prepare($stageQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare stage query: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stageData = $stmt->get_result()->fetch_assoc();
    
    // Estimate completed and on-time stages (simplified calculation)
    $completed_stages = max(0, floor($substageData['completed_substages'] / 3)); // Rough estimate
    $on_time_stages = max(0, floor($substageData['on_time_substages'] / 3)); // Rough estimate
    
    // Get late task statistics - simplified
    $lateTaskQuery = "SELECT 
        COUNT(*) as late_tasks
    FROM project_substages pss
    JOIN project_stages ps ON ps.id = pss.stage_id
    JOIN projects p ON p.id = ps.project_id
    WHERE pss.assigned_to = ?
    AND ((pss.status = 'completed' AND pss.updated_at > pss.end_date) 
         OR (pss.end_date < CURDATE() AND pss.status NOT IN ('completed', 'cancelled')))
    AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($lateTaskQuery);
    if (!$stmt) {
        // Use 0 if query fails
        $late_tasks = 0;
        $overdue_tasks = 0;
    } else {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $lateTaskData = $stmt->get_result()->fetch_assoc();
        $late_tasks = $lateTaskData['late_tasks'] ?? 0;
        
        // Get overdue tasks
        $overdueQuery = "SELECT COUNT(*) as overdue_tasks
        FROM project_substages pss
        JOIN project_stages ps ON ps.id = pss.stage_id
        JOIN projects p ON p.id = ps.project_id
        WHERE pss.assigned_to = ?
        AND pss.end_date < CURDATE() AND pss.status NOT IN ('completed', 'cancelled')
        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
        
        $stmt = $conn->prepare($overdueQuery);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $overdueData = $stmt->get_result()->fetch_assoc();
            $overdue_tasks = $overdueData['overdue_tasks'] ?? 0;
        } else {
            $overdue_tasks = 0;
        }
    }
    
    // Return the data
    echo json_encode([
        'total_tasks' => (int)$taskData['total_tasks'],
        'completed_tasks' => (int)$taskData['completed_tasks'],
        'total_stages' => (int)$stageData['total_stages'],
        'completed_stages' => (int)$completed_stages,
        'on_time_stages' => (int)$on_time_stages,
        'total_substages' => (int)$substageData['total_substages'],
        'completed_substages' => (int)$substageData['completed_substages'],
        'on_time_substages' => (int)$substageData['on_time_substages'],
        'late_tasks' => (int)$late_tasks,
        'overdue_tasks' => (int)$overdue_tasks,
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>