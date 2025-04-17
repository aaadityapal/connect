<?php
/**
 * Get Project Team Members API
 * 
 * Retrieves all team members for a specific project including profile pictures
 * 
 * @param int project_id - The ID of the project
 * @param bool include_profile_pictures - Whether to include profile pictures in the response
 * @return JSON Response with team members data
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit();
}

// Database connection
require_once 'config/db_connect.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate project ID
$project_id = isset($data['project_id']) ? intval($data['project_id']) : 0;
$include_profile_pictures = isset($data['include_profile_pictures']) ? (bool)$data['include_profile_pictures'] : false;

if ($project_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid project ID'
    ]);
    exit();
}

// Begin transaction
$conn->begin_transaction();

try {
    $team_members = [];
    
    // Get project owner
    $project_query = "SELECT p.id, p.assigned_to, u.username as name, u.profile_picture 
                      FROM projects p
                      LEFT JOIN users u ON p.assigned_to = u.id
                      WHERE p.id = ? AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($project_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project_result = $stmt->get_result();
    
    if ($project_row = $project_result->fetch_assoc()) {
        if ($project_row['assigned_to']) {
            $team_members[] = [
                'id' => $project_row['assigned_to'],
                'name' => $project_row['name'],
                'role' => 'Project Assigned',
                'profile_picture' => $include_profile_pictures ? $project_row['profile_picture'] : null
            ];
        }
    }
    
    // Get stage owners
    $stages_query = "SELECT ps.id, ps.stage_number, ps.assigned_to, u.username as name, u.profile_picture 
                     FROM project_stages ps
                     LEFT JOIN users u ON ps.assigned_to = u.id
                     WHERE ps.project_id = ? AND ps.deleted_at IS NULL";
    
    $stmt = $conn->prepare($stages_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $stages_result = $stmt->get_result();
    
    while ($stage_row = $stages_result->fetch_assoc()) {
        if ($stage_row['assigned_to']) {
            // Add the user as a stage owner regardless of whether they're already in the team
            // This allows users to have multiple roles (e.g., owner of multiple stages)
            $team_members[] = [
                'id' => $stage_row['assigned_to'],
                'name' => $stage_row['name'],
                'role' => 'Stage Assigned',
                'stage_number' => $stage_row['stage_number'],
                'profile_picture' => $include_profile_pictures ? $stage_row['profile_picture'] : null
            ];
        }
    }
    
    // Get substage owners
    $substages_query = "SELECT pss.id, pss.substage_number, pss.title, pss.assigned_to, 
                        u.username as name, u.profile_picture, ps.stage_number
                        FROM project_substages pss
                        LEFT JOIN project_stages ps ON pss.stage_id = ps.id
                        LEFT JOIN users u ON pss.assigned_to = u.id
                        WHERE ps.project_id = ? AND pss.deleted_at IS NULL";
    
    $stmt = $conn->prepare($substages_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $substages_result = $stmt->get_result();
    
    while ($substage_row = $substages_result->fetch_assoc()) {
        if ($substage_row['assigned_to']) {
            // Add the user as a substage owner regardless of whether they're already in the team
            // This allows users to have multiple roles
            $team_members[] = [
                'id' => $substage_row['assigned_to'],
                'name' => $substage_row['name'],
                'role' => 'Substage Assigned',
                'stage_number' => $substage_row['stage_number'],
                'substage_number' => $substage_row['substage_number'],
                'substage_title' => $substage_row['title'],
                'profile_picture' => $include_profile_pictures ? $substage_row['profile_picture'] : null
            ];
        }
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Return success with team members
    echo json_encode([
        'success' => true,
        'team_members' => $team_members
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving team members: ' . $e->getMessage()
    ]);
}

// Close the connection
$conn->close(); 