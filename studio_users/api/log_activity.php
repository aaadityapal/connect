<?php
/**
 * api/log_activity.php
 * Reusable activity logger — inserts a row into global_activity_logs.
 *
 * Accepted POST body (JSON):
 *   action_type  string  required  e.g. 'task_assigned', 'task_edited'
 *   entity_type  string  required  e.g. 'task'
 *   entity_id    int     optional  e.g. the task ID
 *   description  string  required  human-readable description
 *   metadata     array   optional  any extra key-value pairs (stored as JSON)
 */

session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['action_type']) || empty($input['entity_type']) || empty($input['description'])) {
    echo json_encode(['success' => false, 'error' => 'action_type, entity_type and description are required']);
    exit();
}

try {
    $user_id     = intval($_SESSION['user_id']);
    $action_type = trim($input['action_type']);
    $entity_type = trim($input['entity_type']);
    $entity_id   = !empty($input['entity_id']) ? intval($input['entity_id']) : null;
    $description = trim($input['description']);
    $metadata    = !empty($input['metadata']) ? json_encode($input['metadata']) : null;

    $stmt = $pdo->prepare(
        "INSERT INTO global_activity_logs
            (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
         VALUES
            (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0)"
    );

    $stmt->execute([
        'user_id'     => $user_id,
        'action_type' => $action_type,
        'entity_type' => $entity_type,
        'entity_id'   => $entity_id,
        'description' => $description,
        'metadata'    => $metadata,
    ]);

    echo json_encode(['success' => true, 'log_id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
