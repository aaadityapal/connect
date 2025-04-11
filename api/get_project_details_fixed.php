<?php
// Add these lines at the very top for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
error_log("Starting get_project_details_fixed.php");

// Prevent any output before JSON
if (ob_get_level()) ob_clean();
header('Content-Type: application/json');

// Include the correct database connection file
require_once '../config/db_connect.php';

// Get project ID from request
$projectId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$projectId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Project ID is required'
    ]);
    exit;
}

try {
    // Determine which connection to use (PDO or MySQLi)
    $usePdo = isset($pdo);
    $connection = $usePdo ? 'PDO' : (isset($conn) ? 'MySQLi' : 'None');
    error_log("Using connection: $connection for project $projectId");
    
    // Get project details
    if ($usePdo) {
        // Using PDO connection
        $query = "SELECT * FROM projects WHERE id = :id AND deleted_at IS NULL";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            throw new Exception("Project not found");
        }
        
        error_log("Project assigned_to before conversion: " . var_export($project['assigned_to'], true));
        
        // Get stages
        $stageQuery = "SELECT * FROM project_stages WHERE project_id = :project_id AND deleted_at IS NULL ORDER BY stage_number";
        $stageStmt = $pdo->prepare($stageQuery);
        $stageStmt->bindParam(':project_id', $projectId, PDO::PARAM_INT);
        $stageStmt->execute();
        $stages = $stageStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get substages for each stage
        foreach ($stages as &$stage) {
            error_log("Stage ID " . $stage['id'] . " assigned_to before conversion: " . var_export($stage['assigned_to'], true));
            
            $substageQuery = "SELECT * FROM project_substages WHERE stage_id = :stage_id AND deleted_at IS NULL ORDER BY substage_number";
            $substageStmt = $pdo->prepare($substageQuery);
            $substageStmt->bindParam(':stage_id', $stage['id'], PDO::PARAM_INT);
            $substageStmt->execute();
            $substages = $substageStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure assigned_to is always a string
            foreach ($substages as &$substage) {
                error_log("Substage ID " . $substage['id'] . " assigned_to before conversion: " . var_export($substage['assigned_to'], true));
                // Convert to string to prevent JS comparison issues
                $substage['assigned_to'] = (string)$substage['assigned_to'];
                error_log("Substage ID " . $substage['id'] . " assigned_to after conversion: " . var_export($substage['assigned_to'], true));
            }
            
            $stage['substages'] = $substages;
            
            // Ensure stage assigned_to is always a string
            $stage['assigned_to'] = (string)$stage['assigned_to'];
            error_log("Stage ID " . $stage['id'] . " assigned_to after conversion: " . var_export($stage['assigned_to'], true));
        }
        
        // Ensure project assigned_to is always a string
        $project['assigned_to'] = (string)$project['assigned_to'];
        error_log("Project assigned_to after conversion: " . var_export($project['assigned_to'], true));
        
    } else if (isset($conn)) {
        // Using MySQLi connection
        $query = "SELECT * FROM projects WHERE id = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $projectId);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        
        if (!$project) {
            throw new Exception("Project not found");
        }
        
        error_log("Project assigned_to before conversion: " . var_export($project['assigned_to'], true));
        
        // Get stages
        $stageQuery = "SELECT * FROM project_stages WHERE project_id = ? AND deleted_at IS NULL ORDER BY stage_number";
        $stageStmt = $conn->prepare($stageQuery);
        $stageStmt->bind_param('i', $projectId);
        $stageStmt->execute();
        $stageResult = $stageStmt->get_result();
        
        $stages = [];
        while ($stage = $stageResult->fetch_assoc()) {
            error_log("Stage ID " . $stage['id'] . " assigned_to before conversion: " . var_export($stage['assigned_to'], true));
            
            // Ensure stage assigned_to is always a string
            $stage['assigned_to'] = (string)$stage['assigned_to'];
            error_log("Stage ID " . $stage['id'] . " assigned_to after conversion: " . var_export($stage['assigned_to'], true));
            
            // Get substages for this stage
            $substageQuery = "SELECT * FROM project_substages WHERE stage_id = ? AND deleted_at IS NULL ORDER BY substage_number";
            $substageStmt = $conn->prepare($substageQuery);
            $substageStmt->bind_param('i', $stage['id']);
            $substageStmt->execute();
            $substageResult = $substageStmt->get_result();
            
            $substages = [];
            while ($substage = $substageResult->fetch_assoc()) {
                error_log("Substage ID " . $substage['id'] . " assigned_to before conversion: " . var_export($substage['assigned_to'], true));
                
                // Ensure substage assigned_to is always a string
                $substage['assigned_to'] = (string)$substage['assigned_to'];
                error_log("Substage ID " . $substage['id'] . " assigned_to after conversion: " . var_export($substage['assigned_to'], true));
                
                $substages[] = $substage;
            }
            
            $stage['substages'] = $substages;
            $stages[] = $stage;
        }
        
        // Ensure project assigned_to is always a string
        $project['assigned_to'] = (string)$project['assigned_to'];
        error_log("Project assigned_to after conversion: " . var_export($project['assigned_to'], true));
    } else {
        throw new Exception("No database connection available");
    }
    
    // Add stages to project
    $project['stages'] = $stages;
    
    // Log the entire project data for debugging
    error_log("Complete project data: " . json_encode($project));
    
    echo json_encode([
        'status' => 'success',
        'data' => $project
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_project_details_fixed.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch project: ' . $e->getMessage()
    ]);
}
exit; 