<?php
session_start();
require_once '../../config/db_connect.php';
require_once 'project_history.php';

header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Senior Manager (Studio)') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Decode the JSON data from POST request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid data received');
    }

    // Start transaction
    $conn->begin_transaction();

    // 1. Insert into projects table
    $project_query = "INSERT INTO projects (
        title, 
        description, 
        project_type, 
        category_id,
        start_date, 
        end_date, 
        created_by, 
        assigned_to,
        status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'not_started', NOW())";

    $stmt = $conn->prepare($project_query);
    $stmt->bind_param(
        "sssissii",
        $data['title'],
        $data['description'],
        $data['projectType'],
        $data['category'],
        $data['startDate'],
        $data['dueDate'],
        $_SESSION['user_id'],
        $data['assignee']
    );
    $stmt->execute();
    $project_id = $conn->insert_id;

    // Record project creation
    recordProjectHistory(
        $project_id,
        'created',
        null,
        json_encode($data),
        $conn
    );

    // Record initial status
    recordStatusChange(
        $project_id,
        null,
        null,
        'not_started',
        'pending',
        'Project created',
        $conn
    );

    // Log activity
    logProjectActivity(
        $project_id,
        null,
        null,
        'other',
        'Project created with ' . count($data['stages']) . ' stages',
        $conn
    );

    // 2. Insert stages
    if (!empty($data['stages'])) {
        $stage_query = "INSERT INTO project_stages (
            project_id,
            stage_number,
            assigned_to,
            start_date,
            end_date,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'not_started', NOW())";

        $stage_stmt = $conn->prepare($stage_query);

        foreach ($data['stages'] as $index => $stage) {
            $stage_number = $index + 1;
            $stage_stmt->bind_param(
                "iiiss",
                $project_id,
                $stage_number,
                $stage['assignee'],
                $stage['startDate'],
                $stage['dueDate']
            );
            $stage_stmt->execute();
            $stage_id = $conn->insert_id;

            // Record stage creation
            logProjectActivity(
                $project_id,
                $stage_id,
                null,
                'other',
                'Stage created with ' . count($stage['substages']) . ' substages',
                $conn
            );

            // 3. Insert substages if any
            if (!empty($stage['substages'])) {
                $substage_query = "INSERT INTO project_substages (
                    stage_id,
                    substage_number,
                    title,
                    assigned_to,
                    start_date,
                    end_date,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'not_started', NOW())";

                $substage_stmt = $conn->prepare($substage_query);

                foreach ($stage['substages'] as $sub_index => $substage) {
                    $substage_number = $sub_index + 1;
                    $substage_stmt->bind_param(
                        "iisiss",
                        $stage_id,
                        $substage_number,
                        $substage['title'],
                        $substage['assignee'],
                        $substage['startDate'],
                        $substage['dueDate']
                    );
                    $substage_stmt->execute();
                    $substage_id = $conn->insert_id;

                    // Record substage creation
                    logProjectActivity(
                        $project_id,
                        $stage_id,
                        $substage_id,
                        'other',
                        'Substage created',
                        $conn
                    );
                }
            }
        }
    }

    // 4. Handle file uploads if any
    if (!empty($data['files'])) {
        $file_query = "INSERT INTO project_files (
            project_id,
            stage_id,
            substage_id,
            file_name,
            file_path,
            uploaded_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $file_stmt = $conn->prepare($file_query);

        foreach ($data['files'] as $file) {
            $file_stmt->bind_param(
                "iiissi",
                $project_id,
                $file['stageId'],
                $file['substageId'],
                $file['fileName'],
                $file['filePath'],
                $_SESSION['user_id']
            );
            $file_stmt->execute();
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Project created successfully',
        'project_id' => $project_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_error) {
        $conn->rollback();
    }
    
    error_log("Error creating project: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create project: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 