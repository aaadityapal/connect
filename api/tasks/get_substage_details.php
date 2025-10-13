<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    if (!isset($_GET['substage_id'])) {
        throw new Exception('Substage ID is required');
    }

    $substage_id = intval($_GET['substage_id']);

    // Get substage details
    $substage_query = $conn->prepare("
        SELECT 
            s.id,
            s.stage_id,
            s.description,
            s.status,
            s.created_at,
            s.updated_at,
            s.priority,
            s.start_date,
            s.end_date,
            s.assignee_id
        FROM task_substages s
        WHERE s.id = ?
    ");

    if (!$substage_query) {
        throw new Exception("Failed to prepare substage query: " . $conn->error);
    }

    $substage_query->bind_param('i', $substage_id);
    
    if (!$substage_query->execute()) {
        throw new Exception("Failed to execute substage query: " . $substage_query->error);
    }

    $result = $substage_query->get_result();
    $substage = $result->fetch_assoc();

    if (!$substage) {
        throw new Exception('Substage not found');
    }

    // Get all history items including status changes, comments, and files
    $history_query = "
        SELECT 
            tsh.*,
            u.username as changed_by_name,
            u.designation as changed_by_designation,
            CASE 
                WHEN tsh.file_path IS NOT NULL THEN 'file'
                WHEN tsh.old_status = 'comment' THEN 'comment'
                ELSE 'status'
            END as entry_type
        FROM 
            task_status_history tsh
        LEFT JOIN 
            users u ON tsh.changed_by = u.id
        WHERE 
            tsh.entity_type = 'substage' 
            AND tsh.entity_id = ?
        ORDER BY 
            tsh.changed_at DESC";

    $stmt = $conn->prepare($history_query);
    $stmt->bind_param('i', $substage_id);
    $stmt->execute();
    $history_result = $stmt->get_result();

    $history = [];
    $files = [];

    while ($row = $history_result->fetch_assoc()) {
        $item = [
            'id' => $row['id'],
            'old_status' => $row['old_status'],
            'new_status' => $row['new_status'],
            'changed_at' => $row['changed_at'],
            'comment' => $row['comment'],
            'changed_by' => [
                'name' => $row['changed_by_name'],
                'designation' => $row['changed_by_designation']
            ]
        ];

        // If this entry has a file_path, add it to the files array
        if ($row['file_path']) {
            $files[] = [
                'id' => $row['id'],
                'file_path' => $row['file_path'],
                'original_name' => basename($row['file_path']), // Extract filename from path
                'uploaded_at' => $row['changed_at'],
                'uploaded_by' => [
                    'name' => $row['changed_by_name'],
                    'designation' => $row['changed_by_designation']
                ]
            ];
        }
        
        // Add to history array if it's a status change or comment
        if ($row['entry_type'] !== 'file') {
            $history[] = $item;
        }
    }

    // Format the response
    $response = [
        'success' => true,
        'substage' => [
            'id' => $substage['id'],
            'stage_id' => $substage['stage_id'],
            'description' => $substage['description'],
            'status' => $substage['status'],
            'created_at' => $substage['created_at'],
            'updated_at' => $substage['updated_at'],
            'priority' => $substage['priority'],
            'start_date' => $substage['start_date'],
            'end_date' => $substage['end_date'],
            'assignee_id' => $substage['assignee_id']
        ],
        'history' => $history,
        'files' => $files
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_substage_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 