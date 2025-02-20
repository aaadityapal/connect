<?php
// Prevent any output before our JSON response
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

require_once 'config.php';

// Ensure proper content type
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($data) {
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_GET['stage_id'])) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Stage ID is required'
    ]);
}

$stage_id = intval($_GET['stage_id']);

try {
    // First get the stage details
    $stage_query = $conn->prepare("
        SELECT ts.*, t.title as task_title, t.id as task_id
        FROM task_stages ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.id = ?
    ");

    if (!$stage_query) {
        throw new Exception("Failed to prepare stage query: " . $conn->error);
    }

    $stage_query->bind_param("i", $stage_id);
    
    if (!$stage_query->execute()) {
        throw new Exception("Failed to execute stage query: " . $stage_query->error);
    }

    $stage_result = $stage_query->get_result()->fetch_assoc();

    if (!$stage_result) {
        throw new Exception("Stage not found");
    }

    // Get all status changes for this stage and its substages
    $history_query = $conn->prepare("
        SELECT 
            tsh.*,
            u.username as changed_by_name,
            CASE 
                WHEN tsh.entity_type = 'stage' THEN ts.stage_number
                WHEN tsh.entity_type = 'substage' THEN tss.description
            END as entity_description,
            ts.task_id
        FROM task_status_history tsh
        LEFT JOIN users u ON tsh.changed_by = u.id
        LEFT JOIN task_stages ts ON (tsh.entity_type = 'stage' AND tsh.entity_id = ts.id)
        LEFT JOIN task_substages tss ON (tsh.entity_type = 'substage' AND tsh.entity_id = tss.id)
        WHERE 
            (tsh.entity_type = 'stage' AND tsh.entity_id = ?) OR
            (tsh.entity_type = 'substage' AND tsh.entity_id IN (
                SELECT id FROM task_substages WHERE stage_id = ?
            ))
        ORDER BY tsh.changed_at DESC
    ");

    if (!$history_query) {
        throw new Exception("Failed to prepare history query: " . $conn->error);
    }

    $history_query->bind_param("ii", $stage_id, $stage_id);
    
    if (!$history_query->execute()) {
        throw new Exception("Failed to execute history query: " . $history_query->error);
    }

    $history_result = $history_query->get_result();
    $timeline = [];

    while ($row = $history_result->fetch_assoc()) {
        $timeline[] = [
            'type' => 'status_change',
            'date' => $row['changed_at'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'],
            'entity_description' => $row['entity_description'],
            'old_status' => $row['old_status'],
            'new_status' => $row['new_status'],
            'changed_by' => $row['changed_by_name'],
            'task_id' => $row['task_id']
        ];
    }

    error_log("Timeline data: " . print_r(['stage' => $stage_result, 'timeline' => $timeline], true));

    sendJsonResponse([
        'success' => true,
        'stage' => $stage_result,
        'timeline' => $timeline
    ]);

} catch (Exception $e) {
    error_log("Timeline error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 