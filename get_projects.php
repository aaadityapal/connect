<?php
// Include database connection
include 'includes/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

try {
    // Query to get projects from the database
    $query = "SELECT p.id, p.project_name, p.project_type, c.client_name 
              FROM project_payouts p 
              LEFT JOIN clients c ON p.client_id = c.id 
              ORDER BY p.project_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Fetch all projects
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return projects as JSON
    echo json_encode($projects);
} catch (PDOException $e) {
    // Return error message
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

// Close connection
$conn = null;
?> 