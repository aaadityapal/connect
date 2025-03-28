<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'User not authenticated']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['file_id']) || !isset($data['status'])) {
        throw new Exception('File ID and status are required');
    }

    $fileId = $data['file_id'];
    $status = $data['status'];
    $userId = $_SESSION['user_id'];
    
    // Validate status
    $allowedStatuses = ['approved', 'rejected'];
    if (!in_array($status, $allowedStatuses)) {
        throw new Exception('Invalid status');
    }

    // Get file details
    $stmt = $pdo->prepare("
        SELECT sf.*, 
               ps.stage_id,
               pst.project_id
        FROM substage_files sf
        JOIN project_substages ps ON sf.substage_id = ps.id
        JOIN project_stages pst ON ps.stage_id = pst.id
        WHERE sf.id = ? AND sf.deleted_at IS NULL
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        throw new Exception('File not found');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update file status
        $stmt = $pdo->prepare("
            UPDATE substage_files 
            SET status = :status,
                updated_at = CURRENT_TIMESTAMP(),
                last_modified_by = :user_id
            WHERE id = :file_id
        ");

        $stmt->execute([
            'status' => $status,
            'user_id' => $userId,
            'file_id' => $fileId
        ]);

        // Create activity log entry
        $description = $status === 'approved' ? 
            "Approved file: {$file['file_name']}" : 
            "Rejected file: {$file['file_name']}";

        $stmt = $pdo->prepare("
            INSERT INTO project_activity_log (
                project_id,
                stage_id,
                substage_id,
                activity_type,
                description,
                performed_by,
                performed_at
            ) VALUES (
                :project_id,
                :stage_id,
                :substage_id,
                :activity_type,
                :description,
                :performed_by,
                CURRENT_TIMESTAMP()
            )
        ");

        $stmt->execute([
            'project_id' => $file['project_id'],
            'stage_id' => $file['stage_id'],
            'substage_id' => $file['substage_id'],
            'activity_type' => $status === 'approved' ? 'file_approved' : 'file_rejected',
            'description' => $description,
            'performed_by' => $userId
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'File status updated successfully',
            'status' => $status
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>