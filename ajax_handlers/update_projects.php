<?php
require_once '../config/db_connect.php';
require_once '../functions/assignment_tracking.php';
session_start();

header('Content-Type: application/json');

// Get JSON data from POST request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// RBAC: Only HR and Senior Manager (Studio) can update projects
$allowed_roles = ['HR', 'Senior Manager (Studio)'];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Access denied. You do not have permission to update projects.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [
        'status' => 'error',
        'message' => '',
        'data' => null
    ];

    try {
        $conn->begin_transaction();

        // Validate required fields
        if (!isset($data['projectId'])) {
            throw new Exception('Project ID is required');
        }

        $project_id = (int)$data['projectId'];

        // First, get all existing stage IDs for this project
        $existing_stages_query = "SELECT id FROM project_stages WHERE project_id = ?";
        $stmt = $conn->prepare($existing_stages_query);
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $existing_stages_result = $stmt->get_result();
        $existing_stage_ids = [];
        while ($row = $existing_stages_result->fetch_assoc()) {
            $existing_stage_ids[] = $row['id'];
        }

        // Convert assignTo value 0 to NULL for database storage
        $assignedTo = (!empty($data['assignTo']) && $data['assignTo'] !== '0') ? (int)$data['assignTo'] : null;
        $categoryId = (isset($data['projectCategory']) && $data['projectCategory'] !== '' && $data['projectCategory'] !== null)
            ? (int)$data['projectCategory'] : null;
        $projectStatus = (!empty($data['projectStatus'])) ? $data['projectStatus'] : 'not_started';
        $startDate = (!empty($data['startDate'])) ? $data['startDate'] : null;
        $dueDate = (!empty($data['dueDate'])) ? $data['dueDate'] : null;

        // Update project details
        $update_project_sql = "UPDATE projects SET 
            title = ?,
            description = ?,
            project_type = ?,
            category_id = ?,
            start_date = ?,
            end_date = ?,
            assigned_to = ?,
            assignment_status = CASE WHEN ? IS NULL THEN 'unassigned' ELSE 'assigned' END,
            status = ?,
            updated_at = NOW(),
            updated_by = ?,
            client_name = ?,
            client_address = ?,
            project_location = ?,
            plot_area = ?,
            contact_number = ?
            WHERE id = ?";
        
        $stmt = $conn->prepare($update_project_sql);
        if (!$stmt) { throw new Exception('Prepare failed (update project): ' . $conn->error); }
        // Types: s,s,s,i,s,s,i,i,s,i,s,s,s,s,s,i
        $stmt->bind_param(
            "sssissiisisssssi",
            $data['projectTitle'],
            $data['projectDescription'],
            $data['projectType'],
            $categoryId,
            $startDate,
            $dueDate,
            $assignedTo,
            $assignedTo,
            $projectStatus,
            $_SESSION['user_id'],
            $data['client_name'],
            $data['client_address'],
            $data['project_location'],
            $data['plot_area'],
            $data['contact_number'],
            $project_id
        );
        $stmt->execute();
        
        // Get previous status for the project and log assignment change if needed
        $previousStatus = getPreviousAssignmentStatus($conn, 'project', $project_id);
        $newStatus = $assignedTo ? 'assigned' : 'unassigned';
        logAssignmentStatusChange(
            $conn, 
            'project', 
            $project_id, 
            $previousStatus, 
            $newStatus, 
            $assignedTo, 
            $_SESSION['user_id'], 
            $project_id
        );

        // Track which stage IDs we're keeping
        $kept_stage_ids = [];

        // Handle stages
        if (isset($data['stages']) && is_array($data['stages'])) {
            foreach ($data['stages'] as $stage) {
                if (isset($stage['id']) && $stage['id']) {
                    // Convert assignTo value 0 to NULL for database storage
                    $stageAssignedTo = (!empty($stage['assignTo']) && $stage['assignTo'] !== '0') ? $stage['assignTo'] : null;
                    
                    // Update existing stage
                    $update_stage_sql = "UPDATE project_stages SET 
                        assigned_to = ?,
                        assignment_status = CASE WHEN ? IS NULL THEN 'unassigned' ELSE 'assigned' END,
                        start_date = ?,
                        end_date = ?,
                        status = ?,
                        updated_at = NOW(),
                        updated_by = ?
                        WHERE id = ?";
                    
                    $stmt = $conn->prepare($update_stage_sql);
                    if (!$stmt) { throw new Exception('Prepare failed (update stage): ' . $conn->error); }
                    $stageStatus = (!empty($stage['status'])) ? $stage['status'] : 'not_started';
                    $stageStart = (!empty($stage['startDate'])) ? $stage['startDate'] : null;
                    $stageEnd = (!empty($stage['dueDate'])) ? $stage['dueDate'] : null;
                    $stmt->bind_param("iisssii",
                        $stageAssignedTo,
                        $stageAssignedTo,
                        $stageStart,
                        $stageEnd,
                        $stageStatus,
                        $_SESSION['user_id'],
                        $stage['id']
                    );
                    $stmt->execute();
                    $stage_id = $stage['id'];
                    $kept_stage_ids[] = $stage_id;
                    
                    // Get previous status for the stage and log assignment change if needed
                    $previousStageStatus = getPreviousAssignmentStatus($conn, 'stage', $stage_id);
                    $newStageStatus = $stageAssignedTo ? 'assigned' : 'unassigned';
                    logAssignmentStatusChange(
                        $conn, 
                        'stage', 
                        $stage_id, 
                        $previousStageStatus, 
                        $newStageStatus, 
                        $stageAssignedTo, 
                        $_SESSION['user_id'], 
                        $project_id, 
                        $stage_id
                    );
                } else {
                    // Convert assignTo value 0 to NULL for database storage
                    $stageAssignedTo = (!empty($stage['assignTo']) && $stage['assignTo'] !== '0') ? $stage['assignTo'] : null;
                    
                    // Insert new stage
                    $insert_stage_sql = "INSERT INTO project_stages 
                        (project_id, assigned_to, start_date, end_date, stage_number, status, created_by, created_at, assignment_status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), CASE WHEN ? IS NULL THEN 'unassigned' ELSE 'assigned' END)";
                    
                    $stmt = $conn->prepare($insert_stage_sql);
                    if (!$stmt) { throw new Exception('Prepare failed (insert stage): ' . $conn->error); }
                    // Types: i,i,s,s,i,s,i,i
                    $stageStatus = (!empty($stage['status'])) ? $stage['status'] : 'not_started';
                    $stageStart = (!empty($stage['startDate'])) ? $stage['startDate'] : null;
                    $stageEnd = (!empty($stage['dueDate'])) ? $stage['dueDate'] : null;
                    $stmt->bind_param('iissisii',
                        $project_id,
                        $stageAssignedTo,
                        $stageStart,
                        $stageEnd,
                        $stage['stage_number'],
                        $stageStatus,
                        $_SESSION['user_id'],
                        $stageAssignedTo
                    );
                    $stmt->execute();
                    $stage_id = $conn->insert_id;
                    $kept_stage_ids[] = $stage_id;
                    
                    // Log assignment change for the new stage
                    $newStageStatus = $stageAssignedTo ? 'assigned' : 'unassigned';
                    logAssignmentStatusChange(
                        $conn, 
                        'stage', 
                        $stage_id, 
                        'unassigned', 
                        $newStageStatus, 
                        $stageAssignedTo, 
                        $_SESSION['user_id'], 
                        $project_id, 
                        $stage_id
                    );
                }

                // Get existing substage IDs for this stage
                $existing_substages = [];
                if ($stage_id) {
                    $substages_query = "SELECT id FROM project_substages WHERE stage_id = ?";
                    $stmt = $conn->prepare($substages_query);
                    $stmt->bind_param('i', $stage_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $existing_substages[] = $row['id'];
                    }
                }

                // Track kept substage IDs
                $kept_substage_ids = [];

                // Handle substages
                if (isset($stage['substages']) && is_array($stage['substages'])) {
                    foreach ($stage['substages'] as $substage) {
                        if (isset($substage['id']) && $substage['id']) {
                            // Convert assignTo value 0 to NULL for database storage
                            $substageAssignedTo = (!empty($substage['assignTo']) && $substage['assignTo'] !== '0') ? $substage['assignTo'] : null;
                            
                            // Update existing substage
                            $update_substage_sql = "UPDATE project_substages SET 
                                title = ?,
                                assigned_to = ?,
                                assignment_status = CASE WHEN ? IS NULL THEN 'unassigned' ELSE 'assigned' END,
                                start_date = ?,
                                end_date = ?,
                                drawing_number = ?,
                                status = ?,
                                updated_at = NOW(),
                                updated_by = ?
                                WHERE id = ?";
                            
                            $stmt = $conn->prepare($update_substage_sql);
                            if (!$stmt) { throw new Exception('Prepare failed (update substage): ' . $conn->error); }
                            $subStatus = (!empty($substage['status'])) ? $substage['status'] : 'not_started';
                            $subStart = (!empty($substage['startDate'])) ? $substage['startDate'] : null;
                            $subEnd = (!empty($substage['dueDate'])) ? $substage['dueDate'] : null;
                            $drawingNum = isset($substage['drawingNumber']) ? $substage['drawingNumber'] : null;
                            $stmt->bind_param("siissssii",
                                $substage['title'],
                                $substageAssignedTo,
                                $substageAssignedTo,
                                $subStart,
                                $subEnd,
                                $drawingNum,
                                $subStatus,
                                $_SESSION['user_id'],
                                $substage['id']
                            );
                            $stmt->execute();
                            $kept_substage_ids[] = $substage['id'];
                            
                            // Get previous status for the substage and log assignment change if needed
                            $previousSubstageStatus = getPreviousAssignmentStatus($conn, 'substage', $substage['id']);
                            $newSubstageStatus = $substageAssignedTo ? 'assigned' : 'unassigned';
                            logAssignmentStatusChange(
                                $conn, 
                                'substage', 
                                $substage['id'], 
                                $previousSubstageStatus, 
                                $newSubstageStatus, 
                                $substageAssignedTo, 
                                $_SESSION['user_id'], 
                                $project_id, 
                                $stage_id, 
                                $substage['id']
                            );
                        } else {
                            // Convert assignTo value 0 to NULL for database storage
                            $substageAssignedTo = (!empty($substage['assignTo']) && $substage['assignTo'] !== '0') ? $substage['assignTo'] : null;
                            
                            // Insert new substage
                            $insert_substage_sql = "INSERT INTO project_substages 
                                (stage_id, title, assigned_to, start_date, end_date, drawing_number, substage_number, status, created_by, created_at, assignment_status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), CASE WHEN ? IS NULL THEN 'unassigned' ELSE 'assigned' END)";
                            
                            $stmt = $conn->prepare($insert_substage_sql);
                            if (!$stmt) { throw new Exception('Prepare failed (insert substage): ' . $conn->error); }
                            // Types: i,s,i,s,s,s,i,s,i,i
                            $subStatus = (!empty($substage['status'])) ? $substage['status'] : 'not_started';
                            $subStart = (!empty($substage['startDate'])) ? $substage['startDate'] : null;
                            $subEnd = (!empty($substage['dueDate'])) ? $substage['dueDate'] : null;
                            $drawingNum = isset($substage['drawingNumber']) ? $substage['drawingNumber'] : null;
                            $stmt->bind_param('isisssisii',
                                $stage_id,
                                $substage['title'],
                                $substageAssignedTo,
                                $subStart,
                                $subEnd,
                                $drawingNum,
                                $substage['substage_number'],
                                $subStatus,
                                $_SESSION['user_id'],
                                $substageAssignedTo
                            );
                            $stmt->execute();
                            $substage_id = $conn->insert_id;
                            $kept_substage_ids[] = $substage_id;
                            
                            // Log assignment change for the new substage
                            $newSubstageStatus = $substageAssignedTo ? 'assigned' : 'unassigned';
                            logAssignmentStatusChange(
                                $conn, 
                                'substage', 
                                $substage_id, 
                                'unassigned', 
                                $newSubstageStatus, 
                                $substageAssignedTo, 
                                $_SESSION['user_id'], 
                                $project_id, 
                                $stage_id, 
                                $substage_id
                            );
                        }
                    }
                }

                // Delete substages that weren't kept
                $substages_to_delete = array_diff($existing_substages, $kept_substage_ids);
                if (!empty($substages_to_delete)) {
                    $delete_substages_sql = "UPDATE project_substages SET 
                        deleted_at = NOW(),
                        deleted_by = ?
                        WHERE id IN (" . implode(',', array_fill(0, count($substages_to_delete), '?')) . ")";
                    $stmt = $conn->prepare($delete_substages_sql);
                    $types = 'i' . str_repeat('i', count($substages_to_delete));
                    $params = array_merge([$_SESSION['user_id']], $substages_to_delete);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                }
            }
        }

        // Delete stages that weren't kept
        $stages_to_delete = array_diff($existing_stage_ids, $kept_stage_ids);
        if (!empty($stages_to_delete)) {
            // First mark associated substages as deleted
            $delete_substages_sql = "UPDATE project_substages SET 
                deleted_at = NOW(),
                deleted_by = ?
                WHERE stage_id IN (" . implode(',', array_fill(0, count($stages_to_delete), '?')) . ")";
            $stmt = $conn->prepare($delete_substages_sql);
            $types = 'i' . str_repeat('i', count($stages_to_delete));
            $params = array_merge([$_SESSION['user_id']], $stages_to_delete);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            // Then mark the stages as deleted
            $delete_stages_sql = "UPDATE project_stages SET 
                deleted_at = NOW(),
                deleted_by = ?
                WHERE id IN (" . implode(',', array_fill(0, count($stages_to_delete), '?')) . ")";
            $stmt = $conn->prepare($delete_stages_sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }

        $conn->commit();

        $response['status'] = 'success';
        $response['message'] = 'Project updated successfully';

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Failed to update project: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
} 