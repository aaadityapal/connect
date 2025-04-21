<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Debug log
    error_log('Received project data: ' . json_encode($data));
    
    // Validate category ID
    if (empty($data['projectCategory'])) {
        throw new Exception('Category ID is required');
    }

    // Verify user exists and is active
    $userQuery = "SELECT id FROM users WHERE id = :user_id AND status = 'active'";
    $stmt = $pdo->prepare($userQuery);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Invalid user');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert into projects table
    $projectQuery = "INSERT INTO projects (
        title, 
        description, 
        project_type, 
        category_id, 
        start_date, 
        end_date, 
        created_by, 
        assigned_to, 
        status,
        created_at,
        client_name,
        client_address,
        project_location,
        plot_area,
        contact_number
    ) VALUES (
        :title,
        :description,
        :project_type,
        :category_id,
        :start_date,
        :end_date,
        :created_by,
        :assigned_to,
        'pending',
        NOW(),
        :client_name,
        :client_address,
        :project_location,
        :plot_area,
        :contact_number
    )";
    
    // Convert assignTo value 0 to NULL for database storage
    $assignedTo = (!empty($data['assignTo']) && $data['assignTo'] !== '0') ? $data['assignTo'] : null;
    
    $stmt = $pdo->prepare($projectQuery);
    $result = $stmt->execute([
        ':title' => $data['projectTitle'],
        ':description' => $data['projectDescription'],
        ':project_type' => $data['projectType'],
        ':category_id' => $data['projectCategory'],
        ':start_date' => $data['startDate'],
        ':end_date' => $data['dueDate'],
        ':created_by' => $_SESSION['user_id'] ?? 1,
        ':assigned_to' => $assignedTo,
        ':client_name' => $data['client_name'] ?? null,
        ':client_address' => $data['client_address'] ?? null,
        ':project_location' => $data['project_location'] ?? null,
        ':plot_area' => $data['plot_area'] ?? null,
        ':contact_number' => $data['contact_number'] ?? null
    ]);

    if (!$result) {
        throw new Exception('Failed to insert project: ' . json_encode($stmt->errorInfo()));
    }
    
    $projectId = $pdo->lastInsertId();
    
    // Log in project_activity_log
    $activityQuery = "INSERT INTO project_activity_log (
        project_id,
        activity_type,
        description,
        performed_by,
        performed_at
    ) VALUES (
        :project_id,
        'create',
        'Project created',
        :performed_by,
        NOW()
    )";
    
    $stmt = $pdo->prepare($activityQuery);
    $stmt->execute([
        ':project_id' => $projectId,
        ':performed_by' => $_SESSION['user_id']  // Use session user ID
    ]);
    
    // Log in project_history
    $historyQuery = "INSERT INTO project_history (
        project_id,
        action_type,
        new_value,
        changed_by,
        changed_at
    ) VALUES (
        :project_id,
        'create',
        :new_value,
        :changed_by,
        NOW()
    )";
    
    $stmt = $pdo->prepare($historyQuery);
    $stmt->execute([
        ':project_id' => $projectId,
        ':new_value' => json_encode($data),
        ':changed_by' => $_SESSION['user_id']  // Use session user ID
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Project created successfully',
        'project_id' => $projectId
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Error creating project: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create project: ' . $e->getMessage()
    ]);
}
exit; 