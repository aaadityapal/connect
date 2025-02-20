<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $conn->begin_transaction();

    // Insert project details
    $project_query = "INSERT INTO projects (
        contract_number, project_type, project_name, client_name, 
        client_guardian_name, client_email, client_mobile, 
        got_project_from, created_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($project_query);
    $stmt->bind_param("sssssssii", 
        $_POST['contract_number'],
        $_POST['project_type'],
        $_POST['project_name'],
        $_POST['client_name'],
        $_POST['client_guardian_name'],
        $_POST['client_email'],
        $_POST['client_mobile'],
        $_POST['got_project_from'],
        $_SESSION['user_id']
    );
    $stmt->execute();
    $project_id = $conn->insert_id;

    // Insert team members with roles
    if (isset($_POST['team_members']) && is_array($_POST['team_members'])) {
        $team_query = "INSERT INTO project_team_members (project_id, user_id, role) VALUES (?, ?, ?)";
        $team_stmt = $conn->prepare($team_query);
        
        foreach ($_POST['team_members'] as $member) {
            if (!empty($member['user_id']) && !empty($member['role'])) {
                $team_stmt->bind_param("iis", 
                    $project_id, 
                    $member['user_id'],
                    $member['role']
                );
                $team_stmt->execute();
            }
        }
    }

    // Insert stages
    if (isset($_POST['stages'])) {
        foreach ($_POST['stages'] as $stage) {
            // Insert stage
            $stage_query = "INSERT INTO project_stages (
                project_id, name, assigned_to, due_date, status
            ) VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($stage_query);
            $stmt->bind_param("isiss", 
                $project_id,
                $stage['name'],
                $stage['assigned_to'],
                $stage['due_date'],
                $stage['status']
            );
            $stmt->execute();
            $stage_id = $conn->insert_id;

            // Insert sub-stages if any
            if (isset($stage['sub_stages'])) {
                foreach ($stage['sub_stages'] as $sub_stage) {
                    $sub_stage_query = "INSERT INTO project_sub_stages (
                        stage_id, name, assigned_to, due_date, status
                    ) VALUES (?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($sub_stage_query);
                    $stmt->bind_param("isiss", 
                        $stage_id,
                        $sub_stage['name'],
                        $sub_stage['assigned_to'],
                        $sub_stage['due_date'],
                        $sub_stage['status']
                    );
                    $stmt->execute();
                }
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 