<?php
require_once '../config/db_connect.php';
session_start();

header('Content-Type: application/json');

// Get JSON data from POST request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

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

        $project_id = $data['projectId'];

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

        // Update project details
        $update_project_sql = "UPDATE projects SET 
            title = ?,
            description = ?,
            project_type = ?,
            category_id = ?,
            start_date = ?,
            end_date = ?,
            assigned_to = ?,
            updated_at = NOW()
            WHERE id = ?";

        $stmt = $conn->prepare($update_project_sql);
        $stmt->bind_param('sssisssi', 
            $data['projectTitle'],
            $data['projectDescription'],
            $data['projectType'],
            $data['projectCategory'],
            $data['startDate'],
            $data['dueDate'],
            $data['assignTo'],
            $project_id
        );
        $stmt->execute();

        // Track which stage IDs we're keeping
        $kept_stage_ids = [];

        // Handle stages
        if (isset($data['stages']) && is_array($data['stages'])) {
            foreach ($data['stages'] as $stage) {
                if (isset($stage['id']) && $stage['id']) {
                    // Update existing stage
                    $update_stage_sql = "UPDATE project_stages SET 
                        assigned_to = ?,
                        start_date = ?,
                        end_date = ?,
                        stage_number = ?
                        WHERE id = ? AND project_id = ?";
                    
                    $stmt = $conn->prepare($update_stage_sql);
                    $stmt->bind_param('sssiii',
                        $stage['assignTo'],
                        $stage['startDate'],
                        $stage['dueDate'],
                        $stage['stage_number'],
                        $stage['id'],
                        $project_id
                    );
                    $stmt->execute();
                    $stage_id = $stage['id'];
                    $kept_stage_ids[] = $stage_id;
                } else {
                    // Insert new stage
                    $insert_stage_sql = "INSERT INTO project_stages 
                        (project_id, assigned_to, start_date, end_date, stage_number) 
                        VALUES (?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($insert_stage_sql);
                    $stmt->bind_param('isssi',
                        $project_id,
                        $stage['assignTo'],
                        $stage['startDate'],
                        $stage['dueDate'],
                        $stage['stage_number']
                    );
                    $stmt->execute();
                    $stage_id = $conn->insert_id;
                    $kept_stage_ids[] = $stage_id;
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
                            // Update existing substage
                            $update_substage_sql = "UPDATE project_substages SET 
                                title = ?,
                                assigned_to = ?,
                                start_date = ?,
                                end_date = ?,
                                substage_number = ?
                                WHERE id = ? AND stage_id = ?";
                            
                            $stmt = $conn->prepare($update_substage_sql);
                            $stmt->bind_param('ssssiii',
                                $substage['title'],
                                $substage['assignTo'],
                                $substage['startDate'],
                                $substage['dueDate'],
                                $substage['substage_number'],
                                $substage['id'],
                                $stage_id
                            );
                            $stmt->execute();
                            $kept_substage_ids[] = $substage['id'];
                        } else {
                            // Insert new substage
                            $insert_substage_sql = "INSERT INTO project_substages 
                                (stage_id, title, assigned_to, start_date, end_date, substage_number) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                            
                            $stmt = $conn->prepare($insert_substage_sql);
                            $stmt->bind_param('issssi',
                                $stage_id,
                                $substage['title'],
                                $substage['assignTo'],
                                $substage['startDate'],
                                $substage['dueDate'],
                                $substage['substage_number']
                            );
                            $stmt->execute();
                            $kept_substage_ids[] = $conn->insert_id;
                        }
                    }
                }

                // Delete substages that weren't kept
                $substages_to_delete = array_diff($existing_substages, $kept_substage_ids);
                if (!empty($substages_to_delete)) {
                    $delete_substages_sql = "DELETE FROM project_substages WHERE id IN (" . 
                        implode(',', array_fill(0, count($substages_to_delete), '?')) . ")";
                    $stmt = $conn->prepare($delete_substages_sql);
                    $types = str_repeat('i', count($substages_to_delete));
                    $stmt->bind_param($types, ...$substages_to_delete);
                    $stmt->execute();
                }
            }
        }

        // Delete stages that weren't kept
        $stages_to_delete = array_diff($existing_stage_ids, $kept_stage_ids);
        if (!empty($stages_to_delete)) {
            // First delete associated substages
            $delete_substages_sql = "DELETE FROM project_substages WHERE stage_id IN (" . 
                implode(',', array_fill(0, count($stages_to_delete), '?')) . ")";
            $stmt = $conn->prepare($delete_substages_sql);
            $types = str_repeat('i', count($stages_to_delete));
            $stmt->bind_param($types, ...$stages_to_delete);
            $stmt->execute();

            // Then delete the stages
            $delete_stages_sql = "DELETE FROM project_stages WHERE id IN (" . 
                implode(',', array_fill(0, count($stages_to_delete), '?')) . ")";
            $stmt = $conn->prepare($delete_stages_sql);
            $stmt->bind_param($types, ...$stages_to_delete);
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