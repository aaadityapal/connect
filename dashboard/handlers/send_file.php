<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['file_id']) || !isset($input['manager_ids']) || !is_array($input['manager_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$fileId = filter_var($input['file_id'], FILTER_SANITIZE_NUMBER_INT);
$managerIds = array_map('intval', $input['manager_ids']);

try {
    // Start transaction
    $conn->begin_transaction();

    // Update file status
    $updateStatusQuery = "UPDATE substage_files SET status = 'sent_for_approval' WHERE id = ?";
    $statusStmt = $conn->prepare($updateStatusQuery);
    $statusStmt->bind_param("i", $fileId);
    $statusStmt->execute();

    // Get file and project details
    $query = "SELECT sf.id, sf.substage_id, ps.stage_id, s.project_id 
              FROM substage_files sf
              JOIN project_substages ps ON sf.substage_id = ps.id
              JOIN project_stages s ON ps.stage_id = s.id
              WHERE sf.id = ? AND sf.deleted_at IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $fileDetails = $result->fetch_assoc();

    if (!$fileDetails) {
        throw new Exception('File not found');
    }

    // Update substage status to in_review
    $updateSubstageQuery = "UPDATE project_substages SET status = 'in_review' WHERE id = ?";
    $substageStmt = $conn->prepare($updateSubstageQuery);
    $substageStmt->bind_param("i", $fileDetails['substage_id']);
    $substageStmt->execute();

    // Log the activity for each manager
    $logStmt = $conn->prepare(
        "INSERT INTO project_activity_log 
        (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at) 
        VALUES (?, ?, ?, 'file_sent', ?, ?, NOW())"
    );

    foreach ($managerIds as $managerId) {
        // Get manager name
        $managerStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $managerStmt->bind_param("i", $managerId);
        $managerStmt->execute();
        $managerResult = $managerStmt->get_result();
        $managerName = $managerResult->fetch_assoc()['username'];

        $description = "File sent to manager: " . $managerName;
        
        $logStmt->bind_param(
            "iiisi",
            $fileDetails['project_id'],
            $fileDetails['stage_id'],
            $fileDetails['substage_id'],
            $description,
            $_SESSION['user_id']
        );
        
        $logStmt->execute();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'File sent successfully',
        'substage_id' => $fileDetails['substage_id']
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error sending file: ' . $e->getMessage()
    ]);
}

$conn->close(); 