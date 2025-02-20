<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['stage_id'])) {
        throw new Exception('Stage ID is required');
    }

    $stageId = intval($_GET['stage_id']);

    // Get stage details
    $stageQuery = "
        SELECT ps.*, p.title as project_title, p.status as project_status 
        FROM project_stages ps
        JOIN projects p ON ps.project_id = p.id
        WHERE ps.id = ? AND ps.deleted_at IS NULL";

    $stmt = $conn->prepare($stageQuery);
    $stmt->bind_param("i", $stageId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Stage not found');
    }

    $row = $result->fetch_assoc();

    // Prepare response data
    $response = [
        'success' => true,
        'stage' => [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'stage_number' => $row['stage_number'],
            'assigned_to' => $row['assigned_to'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $row['status']
        ],
        'project' => [
            'id' => $row['project_id'],
            'title' => $row['project_title'],
            'status' => $row['project_status']
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close(); 