<?php
// Very basic error handling to log errors but not display them
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Basic functionality without advanced features that might not be supported
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Not authenticated',
        'todo' => [],
        'in_progress' => [],
        'in_review' => [],
        'done' => []
    ]);
    exit();
}

// Simple request parsing
$request_data = file_get_contents('php://input');
$input = json_decode($request_data, true);

// Get filter parameters with defaults
$year = isset($input['year']) ? (int)$input['year'] : (int)date('Y');
$month = isset($input['month']) ? $input['month'] : 'all';
$user_id = (int)$_SESSION['user_id'];

// Basic sanitization function
function clean_string($str) {
    if (is_string($str)) {
        // Basic cleaning
        return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
    } elseif (is_null($str)) {
        return "";
    } else {
        return $str;
    }
}

// Prepare empty response structure
$response = [
    'todo' => [],
    'in_progress' => [],
    'in_review' => [],
    'done' => []
];

try {
    // 1. Get todo tasks
    $todo_query = "SELECT DISTINCT 
        p.id, p.title, p.description, p.project_type, p.status, p.end_date,
        COUNT(DISTINCT ps.id) as total_stages,
        COUNT(DISTINCT pss.id) as total_substages,
        u.username as creator_name
        FROM projects p
        LEFT JOIN project_stages ps ON p.id = ps.project_id
        LEFT JOIN project_substages pss ON ps.id = pss.stage_id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE (p.assigned_to = ? OR ps.assigned_to = ? OR pss.assigned_to = ?)
        AND p.status IN ('pending', 'not_started')
        AND p.deleted_at IS NULL";
    
    // Add year filter    
    $todo_query .= " AND YEAR(p.end_date) = ?";
    
    // Add month filter if specific month selected
    if ($month !== 'all') {
        $todo_query .= " AND MONTH(p.end_date) = ?";
    }
    
    $todo_query .= " GROUP BY p.id ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($todo_query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    // Bind parameters
    if ($month === 'all') {
        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $year);
    } else {
        $month_num = (int)$month + 1; // JavaScript months are 0-indexed
        $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $year, $month_num);
    }
    
    $stmt->execute();
    $todo_result = $stmt->get_result();
    
    while ($project = $todo_result->fetch_assoc()) {
        // Add to response with basic sanitization
        $response['todo'][] = [
            'id' => (int)$project['id'],
            'title' => clean_string($project['title']),
            'description' => clean_string($project['description']),
            'project_type' => clean_string($project['project_type']),
            'status' => clean_string($project['status']),
            'total_stages' => (int)$project['total_stages'],
            'total_substages' => (int)$project['total_substages'],
            'creator_name' => clean_string($project['creator_name']),
            'due_date' => date('M d', strtotime($project['end_date']))
        ];
    }
    
    // 2. Get in progress tasks (similar to todo)
    $progress_query = "SELECT DISTINCT 
        p.id, p.title, p.description, p.project_type, p.end_date,
        COUNT(DISTINCT ps.id) as total_stages,
        COUNT(DISTINCT pss.id) as total_substages,
        COUNT(DISTINCT CASE WHEN ps.status = 'in_progress' THEN ps.id END) as in_progress_stages,
        COUNT(DISTINCT CASE WHEN pss.status = 'in_progress' THEN pss.id END) as in_progress_substages,
        u.username as creator_name
        FROM projects p
        LEFT JOIN project_stages ps ON p.id = ps.project_id
        LEFT JOIN project_substages pss ON ps.id = pss.stage_id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE (p.assigned_to = ? OR ps.assigned_to = ? OR pss.assigned_to = ?)
        AND p.deleted_at IS NULL
        AND (ps.status = 'in_progress' OR pss.status = 'in_progress')";
    
    // Add year filter
    $progress_query .= " AND YEAR(p.end_date) = ?";
    
    // Add month filter if specific month selected
    if ($month !== 'all') {
        $progress_query .= " AND MONTH(p.end_date) = ?";
    }
    
    $progress_query .= " GROUP BY p.id ORDER BY p.updated_at DESC";
    
    $stmt = $conn->prepare($progress_query);
    
    // Bind parameters
    if ($month === 'all') {
        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $year);
    } else {
        $month_num = (int)$month + 1;
        $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $year, $month_num);
    }
    
    $stmt->execute();
    $progress_result = $stmt->get_result();
    
    while ($project = $progress_result->fetch_assoc()) {
        // Calculate progress percentage
        $total_items = (int)$project['total_stages'] + (int)$project['total_substages'];
        $in_progress_items = (int)$project['in_progress_stages'] + (int)$project['in_progress_substages'];
        $progress_percentage = $total_items > 0 ? round(($in_progress_items / $total_items) * 100) : 0;
        
        $response['in_progress'][] = [
            'id' => (int)$project['id'],
            'title' => clean_string($project['title']),
            'description' => clean_string($project['description']),
            'project_type' => clean_string($project['project_type']),
            'status' => 'in_progress',
            'total_stages' => (int)$project['total_stages'],
            'total_substages' => (int)$project['total_substages'],
            'in_progress_stages' => (int)$project['in_progress_stages'],
            'in_progress_substages' => (int)$project['in_progress_substages'],
            'progress_percentage' => (int)$progress_percentage,
            'creator_name' => clean_string($project['creator_name']),
            'due_date' => date('M d', strtotime($project['end_date']))
        ];
    }
    
    // 3. Get in review items
    $review_query = "SELECT 
        p.id as project_id, p.title as project_title, p.project_type,
        ps.id as stage_id, ps.stage_number,
        pss.id as substage_id, pss.title as substage_title, pss.substage_number, pss.end_date,
        u.username as reviewer_name
        FROM project_substages pss
        JOIN project_stages ps ON pss.stage_id = ps.id
        JOIN projects p ON ps.project_id = p.id
        LEFT JOIN users u ON pss.assigned_to = u.id
        WHERE (p.assigned_to = ? OR ps.assigned_to = ? OR pss.assigned_to = ?)
        AND pss.status = 'in_review'
        AND p.deleted_at IS NULL";
    
    // Add year filter
    $review_query .= " AND YEAR(pss.end_date) = ?";
    
    // Add month filter if specific month selected
    if ($month !== 'all') {
        $review_query .= " AND MONTH(pss.end_date) = ?";
    }
    
    $review_query .= " ORDER BY pss.updated_at DESC";
    
    $stmt = $conn->prepare($review_query);
    
    if ($month === 'all') {
        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $year);
    } else {
        $month_num = (int)$month + 1;
        $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $year, $month_num);
    }
    
    $stmt->execute();
    $review_result = $stmt->get_result();
    
    while ($substage = $review_result->fetch_assoc()) {
        $response['in_review'][] = [
            'project_id' => (int)$substage['project_id'],
            'project_title' => clean_string($substage['project_title']),
            'project_type' => clean_string($substage['project_type']),
            'substage_id' => (int)$substage['substage_id'],
            'substage_title' => clean_string($substage['substage_title']),
            'substage_number' => (int)$substage['substage_number'],
            'stage_number' => (int)$substage['stage_number'],
            'reviewer_name' => clean_string($substage['reviewer_name']),
            'due_date' => date('M d', strtotime($substage['end_date']))
        ];
    }
    
    // 4. Get done items
    $done_query = "SELECT 
        p.id, p.title, p.description, p.project_type,
        COUNT(DISTINCT ps.id) as total_stages,
        COUNT(DISTINCT pss.id) as total_substages,
        MAX(GREATEST(ps.updated_at, pss.updated_at)) as last_completed_at,
        u.username as creator_name
        FROM projects p
        LEFT JOIN project_stages ps ON p.id = ps.project_id
        LEFT JOIN project_substages pss ON ps.id = pss.stage_id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE (p.assigned_to = ? OR ps.assigned_to = ? OR pss.assigned_to = ?)
        AND p.deleted_at IS NULL";
    
    // Add year filter
    $done_query .= " AND YEAR(p.end_date) = ?";
    
    // Add month filter if specific month selected
    if ($month !== 'all') {
        $done_query .= " AND MONTH(p.end_date) = ?";
    }
    
    $done_query .= " GROUP BY p.id
        HAVING 
            (COUNT(DISTINCT ps.id) = COUNT(DISTINCT CASE WHEN ps.status = 'completed' THEN ps.id END) AND COUNT(DISTINCT ps.id) > 0)
            AND (COUNT(DISTINCT pss.id) = COUNT(DISTINCT CASE WHEN pss.status = 'completed' THEN pss.id END) AND COUNT(DISTINCT pss.id) > 0)
        ORDER BY last_completed_at DESC LIMIT 10";
    
    $stmt = $conn->prepare($done_query);
    
    if ($month === 'all') {
        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $year);
    } else {
        $month_num = (int)$month + 1;
        $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $year, $month_num);
    }
    
    $stmt->execute();
    $done_result = $stmt->get_result();
    
    while ($project = $done_result->fetch_assoc()) {
        $response['done'][] = [
            'id' => (int)$project['id'],
            'title' => clean_string($project['title']),
            'description' => clean_string($project['description']),
            'project_type' => clean_string($project['project_type']),
            'status' => 'completed',
            'total_stages' => (int)$project['total_stages'],
            'total_substages' => (int)$project['total_substages'],
            'creator_name' => clean_string($project['creator_name']),
            'completion_date' => date('M d', strtotime($project['last_completed_at']))
        ];
    }
    
    // Output the response
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log the error to the server's error log
    error_log("Filter error: " . $e->getMessage());
    
    // Return a simple error response
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'todo' => [],
        'in_progress' => [],
        'in_review' => [],
        'done' => []
    ]);
} 