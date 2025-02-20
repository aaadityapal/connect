<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once '../config/db_connect.php';

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validate project ID
    $project_id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$project_id) {
        throw new Exception('Project ID is required');
    }

    // Debug output
    error_log("Fetching project ID: " . $project_id);

    // Get project details
    $query = "SELECT p.*, 
                     c.username as creator_name,
                     c.role as creator_role,
                     a.username as assigned_to_name,
                     a.role as assigned_to_role
              FROM projects p
              LEFT JOIN users c ON p.created_by = c.id
              LEFT JOIN users a ON p.assigned_to = a.id
              WHERE p.id = ? AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $project_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $project = $result->fetch_assoc();

    if (!$project) {
        throw new Exception('Project not found');
    }

    // Debug output
    error_log("Project found: " . json_encode($project));

    // Get project stages
    $stages_query = "SELECT * FROM project_stages 
                    WHERE project_id = ? AND deleted_at IS NULL 
                    ORDER BY stage_number ASC";
    $stages_stmt = $conn->prepare($stages_query);
    if (!$stages_stmt) {
        throw new Exception("Prepare stages failed: " . $conn->error);
    }

    $stages_stmt->bind_param("i", $project_id);
    $stages_stmt->execute();
    $stages_result = $stages_stmt->get_result();
    $project['stages'] = [];
    
    while ($stage = $stages_result->fetch_assoc()) {
        // Get substages for each stage
        $substages_query = "SELECT * FROM project_substages 
                           WHERE stage_id = ? AND deleted_at IS NULL 
                           ORDER BY substage_number ASC";
        $substages_stmt = $conn->prepare($substages_query);
        $substages_stmt->bind_param("i", $stage['id']);
        $substages_stmt->execute();
        $substages_result = $substages_stmt->get_result();
        $stage['substages'] = [];
        
        while ($substage = $substages_result->fetch_assoc()) {
            // Get files for each substage
            $files_query = "SELECT * FROM substage_files 
                           WHERE substage_id = ? AND deleted_at IS NULL";
            $files_stmt = $conn->prepare($files_query);
            $files_stmt->bind_param("i", $substage['id']);
            $files_stmt->execute();
            $files_result = $files_stmt->get_result();
            $substage['files'] = [];
            
            while ($file = $files_result->fetch_assoc()) {
                $substage['files'][] = $file;
            }
            
            $stage['substages'][] = $substage;
        }
        
        $project['stages'][] = $stage;
    }

    // Get recent activities
    $activity_query = "SELECT al.*, u.username as performer_name 
                      FROM project_activity_log al
                      LEFT JOIN users u ON al.performed_by = u.id
                      WHERE al.project_id = ?
                      ORDER BY al.performed_at DESC LIMIT 10";
    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->bind_param("i", $project_id);
    $activity_stmt->execute();
    $activity_result = $activity_stmt->get_result();
    $project['recent_activities'] = [];
    
    while ($activity = $activity_result->fetch_assoc()) {
        $project['recent_activities'][] = $activity;
    }

    // Calculate project statistics
    $project['statistics'] = [
        'total_stages' => count($project['stages']),
        'total_substages' => array_sum(array_map(function($stage) {
            return count($stage['substages']);
        }, $project['stages'])),
        'total_files' => array_sum(array_map(function($stage) {
            return array_sum(array_map(function($substage) {
                return count($substage['files']);
            }, $stage['substages']));
        }, $project['stages']))
    ];

    // Check if user can edit
    $project['can_edit'] = isset($_SESSION['user_role']) && 
        (in_array($_SESSION['user_role'], ['Admin', 'Project Manager']) || 
         $_SESSION['user_id'] === $project['created_by'] ||
         $_SESSION['user_id'] === $project['assigned_to']);

    echo json_encode($project);

} catch (Exception $e) {
    error_log("Project Details Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 