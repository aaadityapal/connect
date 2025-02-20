<?php
require_once '../../config/db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $project_id = $_GET['project_id'];
    
    // Fetch project details with creator and assignee information
    $project_query = "SELECT 
        p.*,
        creator.username as created_by_username,
        assignee.username as assigned_to_username
    FROM projects p
    LEFT JOIN users creator ON p.created_by = creator.id
    LEFT JOIN users assignee ON p.assigned_to = assignee.id
    WHERE p.id = ? AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($project_query);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();

    if (!$project) {
        throw new Exception('Project not found');
    }

    // Fetch stages with assignee information
    $stages_query = "SELECT 
        ps.*,
        u.username as assignee_name
    FROM project_stages ps
    LEFT JOIN users u ON ps.assigned_to = u.id
    WHERE ps.project_id = ? AND ps.deleted_at IS NULL
    ORDER BY ps.stage_number";
    
    $stmt = $conn->prepare($stages_query);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $stages_result = $stmt->get_result();
    $project['stages'] = [];

    while ($stage = $stages_result->fetch_assoc()) {
        // Fetch substages for each stage with assignee information
        $substages_query = "SELECT 
            pss.*,
            u.username as assignee_name
        FROM project_substages pss
        LEFT JOIN users u ON pss.assigned_to = u.id
        WHERE pss.stage_id = ? AND pss.deleted_at IS NULL
        ORDER BY pss.substage_number";
        
        $substages_stmt = $conn->prepare($substages_query);
        $substages_stmt->bind_param('i', $stage['id']);
        $substages_stmt->execute();
        $substages_result = $substages_stmt->get_result();
        $stage['substages'] = [];

        while ($substage = $substages_result->fetch_assoc()) {
            $stage['substages'][] = [
                'id' => $substage['id'],
                'substage_number' => $substage['substage_number'],
                'title' => $substage['title'],
                'assignee_name' => $substage['assignee_name'],
                'start_date' => $substage['start_date'],
                'end_date' => $substage['end_date'],
                'status' => $substage['status'],
                'substage_identifier' => $substage['substage_identifier']
            ];
        }

        $project['stages'][] = [
            'id' => $stage['id'],
            'stage_number' => $stage['stage_number'],
            'assignee_name' => $stage['assignee_name'],
            'start_date' => $stage['start_date'],
            'end_date' => $stage['end_date'],
            'status' => $stage['status'],
            'substages' => $stage['substages']
        ];
    }

    // Format dates
    $project['start_date'] = date('Y-m-d', strtotime($project['start_date']));
    $project['end_date'] = date('Y-m-d', strtotime($project['end_date']));
    $project['created_at'] = date('Y-m-d H:i:s', strtotime($project['created_at']));

    // Add default values for missing fields
    $project['status'] = $project['status'] ?? 'Pending';
    $project['project_type'] = $project['project_type'] ?? 'N/A';

    echo json_encode([
        'success' => true,
        'project' => [
            'id' => $project['id'],
            'title' => $project['title'],
            'description' => $project['description'],
            'project_type' => $project['project_type'],
            'start_date' => $project['start_date'],
            'end_date' => $project['end_date'],
            'status' => $project['status'],
            'created_by_username' => $project['created_by_username'],
            'assigned_to_username' => $project['assigned_to_username'],
            'created_at' => $project['created_at'],
            'stages' => $project['stages']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_project_details.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching project details: ' . $e->getMessage()
    ]);
    exit;
} 