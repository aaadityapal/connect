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
$penalty_amount = isset($_POST['penalty_amount']) ? floatval($_POST['penalty_amount']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Validate required fields
if (!$user_id || !$penalty_date || !$reason) {
    echo json_encode(['success' => false, 'error' => 'User ID, penalty date, and reason are required']);
    exit;
}

try {
    // Check if a record already exists for this user and date
    $checkQuery = "SELECT id FROM penalty_reasons WHERE user_id = ? AND penalty_date = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$user_id, $penalty_date]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingRecord) {
        // Update existing record
        $updateQuery = "UPDATE penalty_reasons SET penalty_amount = ?, reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $updateStmt = $pdo->prepare($updateQuery);
        $result = $updateStmt->execute([$penalty_amount, $reason, $existingRecord['id']]);
    } else {
        // Insert new record
        $insertQuery = "INSERT INTO penalty_reasons (user_id, penalty_date, penalty_amount, reason) VALUES (?, ?, ?, ?)";
        $insertStmt = $pdo->prepare($insertQuery);
        $result = $insertStmt->execute([$user_id, $penalty_date, $penalty_amount, $reason]);
    }
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save penalty reason']);
    }
} catch (PDOException $e) {
    error_log("Error saving penalty reason: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>