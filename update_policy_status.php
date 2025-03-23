<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
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

$docId = (int)$postData['document_id'];
$status = $postData['status'];

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

    $stmt = $db->prepare("UPDATE policy_documents SET status = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $db->error);
    }

    $stmt->bind_param('si', $status, $docId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update policy status: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made or document not found');
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