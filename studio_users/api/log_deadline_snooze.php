<?php
/**
 * log_deadline_snooze.php
 * Records a snooze event in the global_activity_logs table.
 */
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

try {
    $actionType = 'Deadline Snooze';
    $entityType = 'System';
    $description = "$username snoozed the upcoming deadline modal for 2 hours.";
    $metadata = json_encode([
        'snooze_duration' => '2 hours',
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    $query = "
        INSERT INTO global_activity_logs (user_id, action_type, entity_type, description, metadata, created_at)
        VALUES (:userId, :actionType, :entityType, :description, :metadata, NOW())
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'userId' => $userId,
        'actionType' => $actionType,
        'entityType' => $entityType,
        'description' => $description,
        'metadata' => $metadata
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
