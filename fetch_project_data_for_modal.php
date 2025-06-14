<?php
// Include database connection
include 'config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

try {
    // Query to get projects from the project_payouts table
    $query = "SELECT 
                id, 
                project_name, 
                project_type, 
                client_name
              FROM 
                project_payouts 
              GROUP BY 
                project_name
              ORDER BY 
                project_name ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    // Fetch all projects
    $projects = array();
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
    
    // Return projects as JSON
    echo json_encode($projects);
} catch (Exception $e) {
    // Return error message with more details
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?> 