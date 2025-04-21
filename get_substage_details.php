<?php
/**
 * API endpoint to fetch substage details for the stage detail modal
 */

session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$projectId = isset($data['project_id']) ? intval($data['project_id']) : 0;
$stageId = isset($data['stage_id']) ? intval($data['stage_id']) : 0;
$substageId = isset($data['substage_id']) ? intval($data['substage_id']) : 0;

// Validate required parameters
if (!$projectId || !$stageId || !$substageId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

try {
    // Fetch project details
    $projectQuery = "SELECT p.id, p.title, p.description, p.start_date, p.end_date, p.status, 
                    p.assigned_to, u1.username as assigned_to_name, 
                    p.created_by, u2.username as created_by_name,
                    p.client_name, p.client_address, p.project_location, p.plot_area, p.contact_number
                    FROM projects p
                    LEFT JOIN users u1 ON p.assigned_to = u1.id
                    LEFT JOIN users u2 ON p.created_by = u2.id
                    WHERE p.id = ? AND p.deleted_at IS NULL";
    
    $projectStmt = $conn->prepare($projectQuery);
    $projectStmt->bind_param("i", $projectId);
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    
    if ($projectResult->num_rows === 0) {
        throw new Exception('Project not found');
    }
    
    $project = $projectResult->fetch_assoc();
    
    // Fetch stage details
    $stageQuery = "SELECT ps.*, u.username as assigned_to_name, u.profile_picture as assigned_to_profile,
                  u2.username as created_by_name, u2.profile_picture as created_by_profile
                  FROM project_stages ps
                  LEFT JOIN users u ON ps.assigned_to = u.id
                  LEFT JOIN users u2 ON ps.created_by = u2.id
                  WHERE ps.id = ? AND ps.project_id = ? AND ps.deleted_at IS NULL";
    
    $stageStmt = $conn->prepare($stageQuery);
    $stageStmt->bind_param("ii", $stageId, $projectId);
    $stageStmt->execute();
    $stageResult = $stageStmt->get_result();
    
    if ($stageResult->num_rows === 0) {
        throw new Exception('Stage not found');
    }
    
    $stage = $stageResult->fetch_assoc();
    
    // Fetch substage details
    $substageQuery = "SELECT ps.*, u.username as assigned_to_name, u.profile_picture as assigned_to_profile,
                     u2.username as created_by_name, u2.profile_picture as created_by_profile
                     FROM project_substages ps
                     LEFT JOIN users u ON ps.assigned_to = u.id
                     LEFT JOIN users u2 ON ps.created_by = u2.id
                     WHERE ps.id = ? AND ps.stage_id = ? AND ps.deleted_at IS NULL";
    
    $substageStmt = $conn->prepare($substageQuery);
    $substageStmt->bind_param("ii", $substageId, $stageId);
    $substageStmt->execute();
    $substageResult = $substageStmt->get_result();
    
    if ($substageResult->num_rows === 0) {
        throw new Exception('Substage not found');
    }
    
    $substage = $substageResult->fetch_assoc();
    
    // Fetch files attached to this substage
    $filesQuery = "SELECT *
                  FROM substage_files
                  WHERE substage_id = ? 
                  ORDER BY uploaded_at DESC";
    
    $filesStmt = $conn->prepare($filesQuery);
    $filesStmt->bind_param("i", $substageId);
    $filesStmt->execute();
    $filesResult = $filesStmt->get_result();
    
    $files = [];
    while ($file = $filesResult->fetch_assoc()) {
        $files[] = $file;
    }
    
    // Add files to substage data
    $substage['files'] = $files;
    
    // Set empty comments array since there's no comments table
    $substage['comments'] = [];
    
    // Return success response with data
    echo json_encode([
        'success' => true,
        'project' => $project,
        'stage' => $stage,
        'substage' => $substage
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
} 