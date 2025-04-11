<?php
// Add these lines at the very top for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
error_log("Starting get_all_project_assignments.php");

// Prevent any output before JSON
if (ob_get_level()) ob_clean();
header('Content-Type: application/json');

// Include the correct database connection file
require_once '../config/db_connect.php';

try {
    // Determine which connection to use (PDO or MySQLi)
    $usePdo = isset($pdo);
    $connection = $usePdo ? 'PDO' : (isset($conn) ? 'MySQLi' : 'None');
    error_log("Using connection: $connection to get all assignments");
    
    // Get all unique user IDs assigned to projects, stages, or substages
    if ($usePdo) {
        // Using PDO connection
        $query = "
            SELECT DISTINCT assigned_to 
            FROM (
                SELECT assigned_to FROM projects WHERE deleted_at IS NULL AND assigned_to IS NOT NULL
                UNION
                SELECT assigned_to FROM project_stages WHERE deleted_at IS NULL AND assigned_to IS NOT NULL
                UNION
                SELECT assigned_to FROM project_substages WHERE deleted_at IS NULL AND assigned_to IS NOT NULL
            ) AS all_assignments
            WHERE assigned_to != '0' AND assigned_to != ''
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } else if (isset($conn)) {
        // Using MySQLi connection
        $query = "
            SELECT DISTINCT assigned_to 
            FROM (
                SELECT assigned_to FROM projects WHERE deleted_at IS NULL AND assigned_to IS NOT NULL
                UNION
                SELECT assigned_to FROM project_stages WHERE deleted_at IS NULL AND assigned_to IS NOT NULL
                UNION
                SELECT assigned_to FROM project_substages WHERE deleted_at IS NULL AND assigned_to IS NOT NULL
            ) AS all_assignments
            WHERE assigned_to != '0' AND assigned_to != ''
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $results = [];
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $results[] = $row[0];
        }
    } else {
        throw new Exception("No database connection available");
    }
    
    // Ensure all values are strings
    $assignedUserIds = array_map('strval', $results);
    
    error_log("Found " . count($assignedUserIds) . " unique user IDs in assignments");
    
    echo json_encode([
        'status' => 'success',
        'data' => $assignedUserIds
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_all_project_assignments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch assignments: ' . $e->getMessage()
    ]);
}
exit; 