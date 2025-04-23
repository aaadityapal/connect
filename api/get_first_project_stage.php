<?php
session_start();
require_once '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if project_id parameter exists
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid project ID']);
    exit;
}

$project_id = $_GET['project_id'];
$user_id = $_SESSION['user_id'];

// First try to find a stage assigned to the current user
$query = "SELECT id FROM project_stages 
          WHERE project_id = ? 
          AND assigned_to = ? 
          AND deleted_at IS NULL 
          ORDER BY stage_number ASC 
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// If no stage assigned to current user, get the first stage of the project
if ($result->num_rows === 0) {
    $query = "SELECT id FROM project_stages 
              WHERE project_id = ? 
              AND deleted_at IS NULL 
              ORDER BY stage_number ASC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(['success' => true, 'stage_id' => $row['id']]);
} else {
    echo json_encode(['success' => false, 'error' => 'No stages found for this project']);
}

$stmt->close();
$conn->close();
?> 