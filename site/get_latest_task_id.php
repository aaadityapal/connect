<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;

try {
    // Query to get the latest task ID assigned to this user
    $query = "SELECT MAX(id) as max_id FROM construction_site_tasks WHERE assigned_user_id = ?";

    // If we want to filter by project (optional, but supervisor might work on multiple sites? usually one active site)
    // The previous code implied supervisors view tasks for a specific site. 
    // But notification should probably be global for the user.
    // Let's stick to global for the user.

    $params = [$userId];

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $maxId = $row['max_id'] ? intval($row['max_id']) : 0;

    echo json_encode(['latest_task_id' => $maxId]);

} catch (Exception $e) {
    error_log("Error fetching latest task ID: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>