<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['substage_id'])) {
        throw new Exception('Substage ID is required');
    }

    $substageId = intval($_GET['substage_id']);

    // Get substage details with project information
    $query = "
        SELECT 
            ps.*,
            pst.project_id,
            pst.stage_number,
            p.title as project_title,
            p.status as project_status
        FROM project_substages ps
        JOIN project_stages pst ON ps.stage_id = pst.id
        JOIN projects p ON pst.project_id = p.id
        WHERE ps.id = ? AND ps.deleted_at IS NULL";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $substageId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Substage not found');
    }

    $row = $result->fetch_assoc();

    // Prepare response data
    $response = [
        'success' => true,
        'substage' => [
            'id' => $row['id'],
            'stage_id' => $row['stage_id'],
            'substage_number' => $row['substage_number'],
            'title' => $row['title'],
            'assigned_to' => $row['assigned_to'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $row['status']
        ],
        'stage' => [
            'id' => $row['stage_id'],
            'project_id' => $row['project_id'],
            'stage_number' => $row['stage_number']
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