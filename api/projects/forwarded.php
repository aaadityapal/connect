<?php
session_start();
require_once '../../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Query to fetch forwarded tasks with project, stage, substage and detailed user information
    $query = "
        SELECT 
            ft.id,
            ft.project_id,
            ft.stage_id,
            ft.substage_id,
            ft.forwarded_by,
            ft.forwarded_to,
            ft.type,
            ft.status as forward_status,
            ft.created_at as forwarded_at,
            ft.updated_at,
            
            -- Project details
            p.title as project_title,
            p.description as project_description,
            p.project_type,
            p.status as project_status,
            p.start_date as project_start_date,
            p.end_date as project_end_date,
            
            -- Stage details
            ps.stage_number,
            ps.start_date as stage_start_date,
            ps.end_date as stage_end_date,
            ps.status as stage_status,
            
            -- Substage details
            pss.substage_number,
            pss.title as substage_title,
            pss.start_date as substage_start_date,
            pss.end_date as substage_end_date,
            pss.status as substage_status,
            pss.substage_identifier,
            
            -- Forwarded by user details
            u1.username as forwarded_by_name,
            u1.position as forwarded_by_position,
            u1.department as forwarded_by_department,
            u1.designation as forwarded_by_designation,
            u1.profile_picture as forwarded_by_profile,
            
            -- Forwarded to user details
            u2.username as forwarded_to_name,
            u2.position as forwarded_to_position,
            u2.department as forwarded_to_department,
            u2.designation as forwarded_to_designation,
            u2.profile_picture as forwarded_to_profile,
            
            -- Project creator details
            u3.username as project_created_by_name,
            u3.position as project_created_by_position,
            u3.department as project_created_by_department,
            
            -- Project assignee details
            u4.username as project_assigned_to_name,
            u4.position as project_assigned_to_position,
            u4.department as project_assigned_to_department,
            
            -- Stage assignee details
            u5.username as stage_assigned_to_name,
            u5.position as stage_assigned_to_position,
            u5.department as stage_assigned_to_department,
            
            -- Substage assignee details
            u6.username as substage_assigned_to_name,
            u6.position as substage_assigned_to_position,
            u6.department as substage_assigned_to_department
            
        FROM forward_tasks ft
        JOIN projects p ON ft.project_id = p.id
        LEFT JOIN projects_stages ps ON ft.stage_id = ps.id
        LEFT JOIN project_substages pss ON ft.substage_id = pss.id
        JOIN users u1 ON ft.forwarded_by = u1.id
        JOIN users u2 ON ft.forwarded_to = u2.id
        JOIN users u3 ON p.created_by = u3.id
        LEFT JOIN users u4 ON p.assigned_to = u4.id
        LEFT JOIN users u5 ON ps.assigned_to = u5.id
        LEFT JOIN users u6 ON pss.assigned_to = u6.id
        WHERE ft.forwarded_to = ? 
        AND ft.status = 'pending'
        AND p.deleted_at IS NULL
        AND (ps.deleted_at IS NULL OR ps.id IS NULL)
        AND (pss.deleted_at IS NULL OR pss.id IS NULL)
        AND u1.deleted_at IS NULL
        AND u2.deleted_at IS NULL
        ORDER BY ft.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        // Format the task data
        $task = [
            'id' => $row['id'],
            'type' => $row['type'],
            'status' => $row['forward_status'],
            'created_at' => $row['forwarded_at'],
            'updated_at' => $row['updated_at'],
            
            // Project information
            'project' => [
                'id' => $row['project_id'],
                'title' => $row['project_title'],
                'description' => $row['project_description'],
                'type' => $row['project_type'],
                'status' => $row['project_status'],
                'start_date' => $row['project_start_date'],
                'end_date' => $row['project_end_date'],
                'created_by' => [
                    'name' => $row['project_created_by_name'],
                    'position' => $row['project_created_by_position'],
                    'department' => $row['project_created_by_department']
                ],
                'assigned_to' => [
                    'name' => $row['project_assigned_to_name'],
                    'position' => $row['project_assigned_to_position'],
                    'department' => $row['project_assigned_to_department']
                ]
            ],
            
            // Users involved in forwarding
            'forwarded_by' => [
                'id' => $row['forwarded_by'],
                'name' => $row['forwarded_by_name'],
                'position' => $row['forwarded_by_position'],
                'department' => $row['forwarded_by_department'],
                'designation' => $row['forwarded_by_designation'],
                'profile_picture' => $row['forwarded_by_profile']
            ],
            'forwarded_to' => [
                'id' => $row['forwarded_to'],
                'name' => $row['forwarded_to_name'],
                'position' => $row['forwarded_to_position'],
                'department' => $row['forwarded_to_department'],
                'designation' => $row['forwarded_to_designation'],
                'profile_picture' => $row['forwarded_to_profile']
            ]
        ];

        // Add stage details if exists
        if ($row['stage_id']) {
            $task['stage'] = [
                'id' => $row['stage_id'],
                'number' => $row['stage_number'],
                'start_date' => $row['stage_start_date'],
                'end_date' => $row['stage_end_date'],
                'status' => $row['stage_status'],
                'assigned_to' => [
                    'name' => $row['stage_assigned_to_name'],
                    'position' => $row['stage_assigned_to_position'],
                    'department' => $row['stage_assigned_to_department']
                ]
            ];
        }

        // Add substage details if exists
        if ($row['substage_id']) {
            $task['substage'] = [
                'id' => $row['substage_id'],
                'number' => $row['substage_number'],
                'title' => $row['substage_title'],
                'start_date' => $row['substage_start_date'],
                'end_date' => $row['substage_end_date'],
                'status' => $row['substage_status'],
                'assigned_to' => [
                    'name' => $row['substage_assigned_to_name'],
                    'position' => $row['substage_assigned_to_position'],
                    'department' => $row['substage_assigned_to_department']
                ],
                'identifier' => $row['substage_identifier']
            ];
        }

        // Build context path based on what's being forwarded
        $context = [$row['project_title']];
        if ($row['stage_number']) $context[] = "Stage " . $row['stage_number'];
        if ($row['substage_number']) $context[] = $row['substage_title'] . " (" . $row['substage_identifier'] . ")";
        
        $task['context'] = implode(' > ', $context);
        $task['forward_type'] = ucfirst($row['type']); // Capitalize type (stage/substage)

        $tasks[] = $task;
    }

    // Return success response with tasks
    echo json_encode([
        'success' => true,
        'data' => $tasks
    ]);

} catch (Exception $e) {
    // Log the error (you should implement proper error logging)
    error_log("Error in forwarded tasks API: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch forwarded tasks'
    ]);
}

// Close database connection
$conn->close(); 