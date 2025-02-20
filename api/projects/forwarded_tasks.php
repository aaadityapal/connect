<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    
    // Debug log
    error_log("Fetching forwarded tasks for user ID: " . $user_id);

    $query = "
        SELECT 
            pal.*, 
            p.title as project_title,
            ps.stage_number,
            ps.title as stage_title,
            pss.substage_number,
            pss.title as substage_title,
            u.username as forwarded_by_name,
            COALESCE(ps.assigned_to, pss.assigned_to) as assigned_to
        FROM project_activity_log pal
        JOIN projects p ON pal.project_id = p.id
        LEFT JOIN project_stages ps ON pal.stage_id = ps.id
        LEFT JOIN project_substages pss ON pal.substage_id = pss.id
        JOIN users u ON pal.performed_by = u.id
        WHERE 
            pal.activity_type = 'forward' 
            AND COALESCE(ps.assigned_to, pss.assigned_to) = ?
        ORDER BY pal.performed_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Debug log
    error_log("Query executed, found " . $result->num_rows . " rows");

    $forwarded_tasks = [];
    while ($row = $result->fetch_assoc()) {
        // Debug log
        error_log("Processing row: " . json_encode($row));
        
        $forwarded_tasks[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'stage_id' => $row['stage_id'],
            'substage_id' => $row['substage_id'],
            'project_title' => $row['project_title'],
            'title' => $row['substage_id'] ? $row['substage_title'] : ($row['stage_title'] ?? "Stage " . $row['stage_number']),
            'description' => $row['description'],
            'forwarded_by_name' => $row['forwarded_by_name'],
            'performed_at' => $row['performed_at']
        ];
    }

    // Debug log
    error_log("Returning data: " . json_encode($forwarded_tasks));

    echo json_encode($forwarded_tasks);

} catch (Exception $e) {
    error_log("Error in forwarded_tasks.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 