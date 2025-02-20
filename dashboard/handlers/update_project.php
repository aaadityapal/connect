<?php
require_once '../../config/db_connect.php';
require_once '../../helpers/auth_helper.php';

// Ensure user is authenticated
session_start();
if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get current user ID
$current_user_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['project_id']) || !isset($input['stages'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

try {
    $db->beginTransaction();

    $project_id = $input['project_id'];

    // First, verify if the project exists and user has permission
    $stmt = $db->prepare("SELECT created_by FROM projects WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception('Project not found');
    }

    // Update existing stages and add new ones
    foreach ($input['stages'] as $stage_data) {
        $stage_number = $stage_data['stage_number'];
        
        // Check if stage exists
        $stmt = $db->prepare("SELECT id FROM project_stages WHERE project_id = ? AND stage_number = ? AND deleted_at IS NULL");
        $stmt->execute([$project_id, $stage_number]);
        $existing_stage = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_stage) {
            // Update existing stage
            $stmt = $db->prepare("
                UPDATE project_stages 
                SET assigned_to = ?,
                    end_date = ?,
                    updated_at = NOW(),
                    updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $stage_data['assignee'],
                $stage_data['due_date'],
                $current_user_id,
                $existing_stage['id']
            ]);
            $stage_id = $existing_stage['id'];
        } else {
            // Insert new stage
            $stmt = $db->prepare("
                INSERT INTO project_stages 
                (project_id, stage_number, assigned_to, start_date, end_date, status, created_at, created_by)
                VALUES (?, ?, ?, NOW(), ?, 'pending', NOW(), ?)
            ");
            $stmt->execute([
                $project_id,
                $stage_number,
                $stage_data['assignee'],
                $stage_data['due_date'],
                $current_user_id
            ]);
            $stage_id = $db->lastInsertId();

            // Log new stage creation
            logActivity($db, $project_id, $stage_id, null, 'stage_created', 'New stage created', $current_user_id);
        }

        // Handle substages
        if (isset($stage_data['substages'])) {
            $substage_number = 1;
            foreach ($stage_data['substages'] as $substage_data) {
                // Generate unique substage identifier
                $substage_identifier = generateSubstageIdentifier($project_id, $stage_number, $substage_number);

                // Check if substage exists
                $stmt = $db->prepare("
                    SELECT id 
                    FROM project_substages 
                    WHERE stage_id = ? AND substage_number = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$stage_id, $substage_number]);
                $existing_substage = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_substage) {
                    // Update existing substage
                    $stmt = $db->prepare("
                        UPDATE project_substages 
                        SET title = ?,
                            assigned_to = ?,
                            end_date = ?,
                            updated_at = NOW(),
                            updated_by = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $substage_data['title'],
                        $substage_data['assignee'],
                        $substage_data['due_date'],
                        $current_user_id,
                        $existing_substage['id']
                    ]);
                } else {
                    // Insert new substage
                    $stmt = $db->prepare("
                        INSERT INTO project_substages 
                        (stage_id, substage_number, title, assigned_to, start_date, end_date, 
                         status, created_at, created_by, substage_identifier)
                        VALUES (?, ?, ?, ?, NOW(), ?, 'pending', NOW(), ?, ?)
                    ");
                    $stmt->execute([
                        $stage_id,
                        $substage_number,
                        $substage_data['title'],
                        $substage_data['assignee'],
                        $substage_data['due_date'],
                        $current_user_id,
                        $substage_identifier
                    ]);

                    $substage_id = $db->lastInsertId();
                    
                    // Log new substage creation
                    logActivity($db, $project_id, $stage_id, $substage_id, 'substage_created', 
                              'New substage created: ' . $substage_data['title'], $current_user_id);
                }
                $substage_number++;
            }
        }
    }

    // Update project's updated_at timestamp
    $stmt = $db->prepare("
        UPDATE projects 
        SET updated_at = NOW(), 
            updated_by = ? 
        WHERE id = ?
    ");
    $stmt->execute([$current_user_id, $project_id]);

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Project updated successfully']);

} catch (Exception $e) {
    $db->rollBack();
    error_log('Project update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update project: ' . $e->getMessage()]);
}

// Helper function to log activity
function logActivity($db, $project_id, $stage_id, $substage_id, $activity_type, $description, $performed_by) {
    $stmt = $db->prepare("
        INSERT INTO project_activity_log 
        (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $project_id,
        $stage_id,
        $substage_id,
        $activity_type,
        $description,
        $performed_by
    ]);
}

// Helper function to generate unique substage identifier
function generateSubstageIdentifier($project_id, $stage_number, $substage_number) {
    return sprintf('P%d-S%d-SS%d', $project_id, $stage_number, $substage_number);
}
?> 