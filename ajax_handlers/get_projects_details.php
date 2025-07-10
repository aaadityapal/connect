<?php
// Include database connection file
require_once '../config/db_connect.php';

// Set response header as JSON
header('Content-Type: application/json');

// Default response (error)
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Check if project_name parameter is provided
if (isset($_GET['project_name']) && !empty($_GET['project_name'])) {
    $projectName = trim($_GET['project_name']);
    
    try {
        // Prepare and execute query to fetch project details
        $query = "SELECT project_id, project_name, project_type, client_name 
                  FROM hrm_project_stage_payment_transactions 
                  WHERE project_name = ? 
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $projectName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Check if project exists
        if ($result->num_rows > 0) {
            $project = $result->fetch_assoc();
            
            // Set success response with project details
            $response = [
                'success' => true,
                'project_id' => $project['project_id'],
                'project_name' => $project['project_name'],
                'project_type' => $project['project_type'],
                'client_name' => $project['client_name']
            ];
        } else {
            // Project not found
            $response = [
                'success' => false,
                'message' => 'Project not found'
            ];
        }
    } catch (Exception $e) {
        // Database error
        $response = [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

// Return JSON response
echo json_encode($response); 