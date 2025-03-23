<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$postData = json_decode(file_get_contents('php://input'), true);

if (!isset($postData['document_id']) || !isset($postData['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$documentId = (int)$postData['document_id'];
$status = $postData['status'];
$userId = $_SESSION['user_id'];

// Validate status
$validStatuses = ['pending', 'acknowledged', 'accepted', 'rejected'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }

    // Check if record already exists
    $checkStmt = $db->prepare("SELECT id FROM policy_acknowledgment_status WHERE policy_id = ? AND user_id = ?");
    $checkStmt->bind_param('ii', $documentId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $stmt = $db->prepare("UPDATE policy_acknowledgment_status SET status = ?, acknowledged_at = NOW() WHERE policy_id = ? AND user_id = ?");
        $stmt->bind_param('sii', $status, $documentId, $userId);
    } else {
        // Insert new record
        $stmt = $db->prepare("INSERT INTO policy_acknowledgment_status (policy_id, user_id, status, acknowledged_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('iis', $documentId, $userId, $status);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update policy status: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Policy status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}