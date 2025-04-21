<?php
// Include database connection

require_once 'config/db_connect.php';


// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize response array
$response = [
    'success' => false,
    'projects' => [],
    'message' => ''
];

// Get user ID from session or POST data
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Debug log
error_log("User ID from session: " . ($user_id ?? 'null'));

// If no user is logged in, return error
if (!$user_id) {
    $response['message'] = 'No user logged in';
    echo json_encode($response);
    exit;
}

try {
    // Get all projects that are either assigned to this user OR have stages/substages assigned to this user
    $projects_query = "
        SELECT DISTINCT p.* 
        FROM projects p
        LEFT JOIN project_stages ps ON p.id = ps.project_id
        LEFT JOIN project_substages ss ON ps.id = ss.stage_id
        WHERE 
            (p.assigned_to LIKE ? OR p.assigned_to LIKE ? OR p.assigned_to LIKE ?) OR
            (ps.assigned_to LIKE ? OR ps.assigned_to LIKE ? OR ps.assigned_to LIKE ?) OR
            (ss.assigned_to LIKE ? OR ss.assigned_to LIKE ? OR ss.assigned_to LIKE ?)
        ORDER BY p.end_date ASC";

    // Prepare parameters for LIKE queries
    $param1 = $user_id;
    $param2 = "%,$user_id";
    $param3 = "$user_id,%";
    
    $stmt = $conn->prepare($projects_query);
    $stmt->bind_param("sssssssss", 
        $param1, $param2, $param3,
        $param1, $param2, $param3,
        $param1, $param2, $param3
    );
    $stmt->execute();
    $projects_result = $stmt->get_result();

    // Debug how many projects were found
    error_log("Projects found: " . $projects_result->num_rows);

    $projects = [];
    
    // Loop through each project and get its stages and substages
    while ($project = $projects_result->fetch_assoc()) {
        // Get stages for this project
        $stages_query = "SELECT * FROM project_stages WHERE project_id = ? ORDER BY stage_number ASC";
        $stages_stmt = $conn->prepare($stages_query);
        $stages_stmt->bind_param("i", $project['id']);
        $stages_stmt->execute();
        $stages_result = $stages_stmt->get_result();
        
        $stages = [];
        while ($stage = $stages_result->fetch_assoc()) {
            // Get substages for this stage
            $substages_query = "SELECT * FROM project_substages WHERE stage_id = ? ORDER BY substage_number ASC";
            $substages_stmt = $conn->prepare($substages_query);
            $substages_stmt->bind_param("i", $stage['id']);
            $substages_stmt->execute();
            $substages_result = $substages_stmt->get_result();
            
            $substages = [];
            while ($substage = $substages_result->fetch_assoc()) {
                $substages[] = $substage;
            }
            
            // Add substages to stage
            $stage['substages'] = $substages;
            $stages[] = $stage;
        }
        
        // Add stages to project
        $project['stages'] = $stages;
        $projects[] = $project;
    }
    
    // Set response
    $response['success'] = true;
    $response['projects'] = $projects;
    
    // Debug if any projects were found
    error_log("Returning " . count($projects) . " projects with stages and substages");
    
    // Output stages & substages counts for debugging
    $stage_count = 0;
    $substage_count = 0;
    
    foreach ($projects as $project) {
        $stage_count += count($project['stages']);
        foreach ($project['stages'] as $stage) {
            $substage_count += count($stage['substages']);
        }
    }
    
    error_log("Total stages: $stage_count, Total substages: $substage_count");
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Error in get_user_projects.php: " . $e->getMessage());
}

// Set the JavaScript USER_ID variable in the response
$response['user_id'] = $user_id;

// Send response
header('Content-Type: application/json');
echo json_encode($response);
?> 