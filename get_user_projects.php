<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Query to get projects assigned to the user
$query = "SELECT p.* 
          FROM projects p 
          WHERE p.assigned_to = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $project = [
        'id' => $row['id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'project_type' => $row['project_type'],
        'category_id' => $row['category_id'],
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
        'status' => $row['status'],
        'created_by' => $row['created_by'],
        'assigned_to' => $row['assigned_to'],
        'stages' => []
    ];
    
    // Get project stages
    $stages_query = "SELECT * FROM project_stages WHERE project_id = ? AND (assigned_to = ? OR assigned_to IS NULL)";
    $stages_stmt = $conn->prepare($stages_query);
    $stages_stmt->bind_param("ii", $row['id'], $user_id);
    $stages_stmt->execute();
    $stages_result = $stages_stmt->get_result();
    
    while ($stage = $stages_result->fetch_assoc()) {
        $stage_data = [
            'id' => $stage['id'],
            'project_id' => $stage['project_id'],
            'stage_number' => $stage['stage_number'],
            'assigned_to' => $stage['assigned_to'],
            'start_date' => $stage['start_date'],
            'end_date' => $stage['end_date'],
            'status' => $stage['status'],
            'substages' => []
        ];
        
        // Get substages for this stage
        $substages_query = "SELECT * FROM project_substages WHERE stage_id = ? AND (assigned_to = ? OR assigned_to IS NULL)";
        $substages_stmt = $conn->prepare($substages_query);
        $substages_stmt->bind_param("ii", $stage['id'], $user_id);
        $substages_stmt->execute();
        $substages_result = $substages_stmt->get_result();
        
        while ($substage = $substages_result->fetch_assoc()) {
            $stage_data['substages'][] = [
                'id' => $substage['id'],
                'stage_id' => $substage['stage_id'],
                'substage_number' => $substage['substage_number'],
                'title' => $substage['title'],
                'assigned_to' => $substage['assigned_to'],
                'start_date' => $substage['start_date'],
                'end_date' => $substage['end_date'],
                'status' => $substage['status'],
                'substage_identifier' => $substage['substage_identifier'],
                'drawing_number' => $substage['drawing_number']
            ];
        }
        $substages_stmt->close();
        
        $project['stages'][] = $stage_data;
    }
    $stages_stmt->close();
    
    $projects[] = $project;
}

echo json_encode([
    'success' => true,
    'projects' => $projects
]);

$stmt->close();
$conn->close();
?> 