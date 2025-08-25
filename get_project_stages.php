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

// Get project ID from URL parameter
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid project ID']);
    exit();
}

try {
    // First, verify the project exists and user has access
    $projectCheckQuery = "SELECT p.id, p.title as project_name 
                         FROM projects p 
                         JOIN project_stages ps ON ps.project_id = p.id 
                         JOIN project_substages pss ON pss.stage_id = ps.id 
                         WHERE p.id = ? AND pss.assigned_to = ? 
                         AND p.deleted_at IS NULL AND ps.deleted_at IS NULL AND pss.deleted_at IS NULL 
                         LIMIT 1";
    
    $stmt = $conn->prepare($projectCheckQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare project check query: " . $conn->error);
    }
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $projectResult = $stmt->get_result();
    
    if ($projectResult->num_rows === 0) {
        echo json_encode(['error' => 'Project not found or access denied', 'stages' => []]);
        exit();
    }
    
    $projectData = $projectResult->fetch_assoc();
    
    // Get all stages for this project with user assignments
    $stagesQuery = "SELECT DISTINCT 
        ps.id,
        ps.project_id,
        ps.stage_number,
        ps.assigned_to,
        ps.start_date,
        ps.end_date,
        ps.status,
        ps.created_at,
        ps.updated_at,
        ps.updated_by,
        ps.assignment_status,
        ps.created_by,
        ps.deleted_by,
        u.username as assigned_username
    FROM project_stages ps
    LEFT JOIN users u ON u.id = ps.assigned_to
    WHERE ps.project_id = ?
    AND ps.deleted_at IS NULL
    AND EXISTS (
        SELECT 1 FROM project_substages pss 
        WHERE pss.stage_id = ps.id 
        AND pss.assigned_to = ? 
        AND pss.deleted_at IS NULL
    )
    ORDER BY ps.stage_number ASC, ps.id ASC";
    
    $stmt = $conn->prepare($stagesQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare stages query: " . $conn->error);
    }
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $stagesResult = $stmt->get_result();
    
    $stages = [];
    
    while ($stage = $stagesResult->fetch_assoc()) {
        $stage_id = $stage['id'];
        
        // Get substages for this stage assigned to the user
        $substagesQuery = "SELECT 
            pss.id,
            pss.stage_id,
            pss.substage_number,
            pss.title,
            pss.assigned_to,
            pss.start_date,
            pss.end_date,
            pss.status,
            pss.created_at,
            pss.updated_at,
            pss.substage_identifier,
            pss.drawing_number,
            pss.updated_by,
            pss.assignment_status,
            pss.created_by,
            pss.deleted_by,
            u.username as assigned_username
        FROM project_substages pss
        LEFT JOIN users u ON u.id = pss.assigned_to
        WHERE pss.stage_id = ? AND pss.assigned_to = ?
        AND pss.deleted_at IS NULL
        ORDER BY pss.substage_number ASC, pss.id ASC";
        
        $stmt = $conn->prepare($substagesQuery);
        if ($stmt) {
            $stmt->bind_param("ii", $stage_id, $user_id);
            $stmt->execute();
            $substagesResult = $stmt->get_result();
            
            $substages = [];
            while ($substage = $substagesResult->fetch_assoc()) {
                $substages[] = [
                    'id' => (int)$substage['id'],
                    'stage_id' => (int)$substage['stage_id'],
                    'substage_number' => $substage['substage_number'],
                    'title' => $substage['title'],
                    'assigned_to' => (int)$substage['assigned_to'],
                    'assigned_username' => $substage['assigned_username'],
                    'start_date' => $substage['start_date'],
                    'end_date' => $substage['end_date'],
                    'status' => $substage['status'],
                    'created_at' => $substage['created_at'],
                    'updated_at' => $substage['updated_at'],
                    'substage_identifier' => $substage['substage_identifier'],
                    'drawing_number' => $substage['drawing_number'],
                    'updated_by' => $substage['updated_by'],
                    'assignment_status' => $substage['assignment_status'],
                    'created_by' => $substage['created_by'],
                    'deleted_by' => $substage['deleted_by']
                ];
            }
            
            // Only include stages that have substages assigned to the user
            if (!empty($substages)) {
                $stages[] = [
                    'id' => (int)$stage['id'],
                    'project_id' => (int)$stage['project_id'],
                    'stage_number' => $stage['stage_number'],
                    'assigned_to' => (int)$stage['assigned_to'],
                    'assigned_username' => $stage['assigned_username'],
                    'start_date' => $stage['start_date'],
                    'end_date' => $stage['end_date'],
                    'status' => $stage['status'],
                    'created_at' => $stage['created_at'],
                    'updated_at' => $stage['updated_at'],
                    'updated_by' => $stage['updated_by'],
                    'assignment_status' => $stage['assignment_status'],
                    'created_by' => $stage['created_by'],
                    'deleted_by' => $stage['deleted_by'],
                    'substages' => $substages,
                    'total_substages' => count($substages),
                    'completed_substages' => array_reduce($substages, function($carry, $item) {
                        return $carry + ($item['status'] === 'completed' ? 1 : 0);
                    }, 0)
                ];
            }
        }
    }
    
    // Return the data
    echo json_encode([
        'project_id' => $project_id,
        'project_name' => $projectData['project_name'],
        'stages' => $stages,
        'total_stages' => count($stages),
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>