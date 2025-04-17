<?php
/**
 * API endpoint to fetch project details for the project brief modal
 */

session_start();
require_once 'config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$projectId = isset($data['project_id']) ? intval($data['project_id']) : 0;

// Validate required parameters
if (!$projectId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameter: project_id']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

try {
    // Check connection before proceeding
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

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
    if (!$projectStmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    
    $projectStmt->bind_param("i", $projectId);
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    
    if ($projectResult->num_rows === 0) {
        throw new Exception('Project not found');
    }
    
    $project = $projectResult->fetch_assoc();
    
    // Fetch stages related to this project
    $stagesQuery = "SELECT ps.*, u.username as assigned_to_name
                   FROM project_stages ps
                   LEFT JOIN users u ON ps.assigned_to = u.id
                   WHERE ps.project_id = ? AND ps.deleted_at IS NULL
                   ORDER BY ps.stage_number ASC";
    
    $stagesStmt = $conn->prepare($stagesQuery);
    if (!$stagesStmt) {
        throw new Exception('Prepare statement failed for stages: ' . $conn->error);
    }
    
    $stagesStmt->bind_param("i", $projectId);
    $stagesStmt->execute();
    $stagesResult = $stagesStmt->get_result();
    
    $stages = [];
    while ($stage = $stagesResult->fetch_assoc()) {
        // Fetch substages for each stage
        $substagesQuery = "SELECT psub.*, u.username as assigned_to_name
                          FROM project_substages psub
                          LEFT JOIN users u ON psub.assigned_to = u.id
                          WHERE psub.stage_id = ? AND psub.deleted_at IS NULL
                          ORDER BY psub.substage_number ASC";
        
        $substagesStmt = $conn->prepare($substagesQuery);
        if (!$substagesStmt) {
            throw new Exception('Prepare statement failed for substages: ' . $conn->error);
        }
        
        $substagesStmt->bind_param("i", $stage['id']);
        $substagesStmt->execute();
        $substagesResult = $substagesStmt->get_result();
        
        $substages = [];
        while ($substage = $substagesResult->fetch_assoc()) {
            $substages[] = $substage;
        }
        
        // Add substages to the stage
        $stage['substages'] = $substages;
        $stages[] = $stage;
    }
    
    // Add stages to project data
    $project['stages'] = $stages;
    
    // Instead of an empty array, use the same approach as in get_project_team.php
    // Get project owner
    $team_members = [];
    
    if ($project['assigned_to']) {
        // Add project owner to team
        $userQuery = "SELECT id, username as name, profile_picture FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userQuery);
        if (!$userStmt) {
            throw new Exception('Prepare statement failed for user query: ' . $conn->error);
        }
        
        $userStmt->bind_param("i", $project['assigned_to']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($user = $userResult->fetch_assoc()) {
            $team_members[] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => 'Project',
                'profile_picture' => $user['profile_picture']
            ];
        }
    }
    
    // Add team members from stages
    foreach ($stages as $stage) {
        if ($stage['assigned_to']) {
            $userQuery = "SELECT id, username as name, profile_picture FROM users WHERE id = ?";
            $userStmt = $conn->prepare($userQuery);
            $userStmt->bind_param("i", $stage['assigned_to']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($user = $userResult->fetch_assoc()) {
                $team_members[] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => 'Stage ' . $stage['stage_number'],
                    'stage_number' => $stage['stage_number'],
                    'profile_picture' => $user['profile_picture']
                ];
            }
        }
        
        // Add team members from substages
        if (isset($stage['substages']) && is_array($stage['substages'])) {
            foreach ($stage['substages'] as $substage) {
                if ($substage['assigned_to']) {
                    $userQuery = "SELECT id, username as name, profile_picture FROM users WHERE id = ?";
                    $userStmt = $conn->prepare($userQuery);
                    $userStmt->bind_param("i", $substage['assigned_to']);
                    $userStmt->execute();
                    $userResult = $userStmt->get_result();
                    
                    if ($user = $userResult->fetch_assoc()) {
                        $team_members[] = [
                            'id' => $user['id'],
                            'name' => $user['name'],
                            'role' => 'Stage ' . $stage['stage_number'],
                            'stage_number' => $stage['stage_number'],
                            'substage_number' => $substage['substage_number'],
                            'profile_picture' => $user['profile_picture']
                        ];
                    }
                }
            }
        }
    }
    
    // Add team members to project data
    $project['team_members'] = $team_members;
    
    // Add empty files array for consistency with stage details
    $project['files'] = [];
    
    // Add empty comments array for consistency with stage details
    $project['comments'] = [];
    
    // Return success response with data
    echo json_encode([
        'success' => true,
        'project' => $project
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString() // Add trace for debugging
    ]);
    exit();
} 