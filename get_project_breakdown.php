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
        echo json_encode(['error' => 'User not found', 'projects' => []]);
        exit();
    }
    
    // Get project breakdown for the selected user - using Title column for project names
    $projectQuery = "SELECT DISTINCT 
        p.id, 
        p.assigned_to,
        p.title as project_name,
        p.description, 
        p.start_date, 
        p.end_date, 
        p.status as project_status
    FROM projects p
    JOIN project_stages ps ON ps.project_id = p.id
    JOIN project_substages pss ON pss.stage_id = ps.id
    WHERE pss.assigned_to = ?
    AND p.deleted_at IS NULL AND ps.deleted_at IS NULL AND pss.deleted_at IS NULL
    ORDER BY p.title
    LIMIT 20";
    
    $stmt = $conn->prepare($projectQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare project query: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $projectResult = $stmt->get_result();
    
    $projects = [];
    
    while ($project = $projectResult->fetch_assoc()) {
        $project_id = $project['id'];
        
        // Get basic statistics for this project - simplified
        $statsQuery = "SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN pss.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN pss.status = 'completed' AND pss.updated_at <= pss.end_date THEN 1 ELSE 0 END) as on_time_tasks
        FROM project_substages pss
        JOIN project_stages ps ON ps.id = pss.stage_id
        WHERE ps.project_id = ? AND pss.assigned_to = ?
        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL";
        
        $stmt = $conn->prepare($statsQuery);
        if (!$stmt) {
            // Skip this project if query fails
            continue;
        }
        $stmt->bind_param("ii", $project_id, $user_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        // Calculate efficiency
        $efficiency = 0;
        if ($stats['completed_tasks'] > 0) {
            $efficiency = round(($stats['on_time_tasks'] / $stats['completed_tasks']) * 100);
        }
        
        // Get project progress percentage
        $progress = 0;
        if ($stats['total_tasks'] > 0) {
            $progress = round(($stats['completed_tasks'] / $stats['total_tasks']) * 100);
        }
        
        // Get simple stage and substage counts
        $countQuery = "SELECT 
            COUNT(DISTINCT ps.id) as total_stages,
            COUNT(DISTINCT pss.id) as total_substages
        FROM project_stages ps
        JOIN project_substages pss ON pss.stage_id = ps.id
        WHERE ps.project_id = ? AND pss.assigned_to = ?
        AND ps.deleted_at IS NULL AND pss.deleted_at IS NULL";
        
        $stmt = $conn->prepare($countQuery);
        if ($stmt) {
            $stmt->bind_param("ii", $project_id, $user_id);
            $stmt->execute();
            $countData = $stmt->get_result()->fetch_assoc();
            $total_stages = $countData['total_stages'] ?? 0;
            $total_substages = $countData['total_substages'] ?? 0;
        } else {
            $total_stages = 0;
            $total_substages = 0;
        }
        
        $projects[] = [
            'id' => $project['id'],
            'name' => $project['project_name'], // Using joined username from users table
            'assigned_to' => $project['assigned_to'],
            'description' => $project['description'],
            'start_date' => $project['start_date'],
            'end_date' => $project['end_date'],
            'project_status' => $project['project_status'],
            'total_tasks' => (int)$stats['total_tasks'],
            'completed_tasks' => (int)$stats['completed_tasks'],
            'on_time_tasks' => (int)$stats['on_time_tasks'],
            'total_stages' => (int)$total_stages,
            'total_substages' => (int)$total_substages,
            'efficiency' => $efficiency,
            'progress' => $progress
        ];
    }
    
    // Sort projects by efficiency (highest first)
    if (!empty($projects)) {
        usort($projects, function($a, $b) {
            return $b['efficiency'] - $a['efficiency'];
        });
    }
    
    // Return the data
    echo json_encode([
        'projects' => $projects,
        'total_projects' => count($projects),
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>