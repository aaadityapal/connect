<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get POST data
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$penalty_date = isset($_POST['penalty_date']) ? $_POST['penalty_date'] : '';

// Validate required fields
if (!$user_id || !$penalty_date) {
    echo json_encode(['success' => false, 'error' => 'User ID and penalty date are required']);
    exit;
}

try {
    // Delete the record for this user and date
    $deleteQuery = "DELETE FROM penalty_reasons WHERE user_id = ? AND penalty_date = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $result = $deleteStmt->execute([$user_id, $penalty_date]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to remove penalty reason']);
    }
} catch (PDOException $e) {
    error_log("Error removing penalty reason: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>