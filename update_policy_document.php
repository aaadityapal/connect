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

if (!isset($postData['id']) || !isset($postData['policy_name']) || !isset($postData['policy_type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$docId = (int)$postData['id'];
$policyName = trim($postData['policy_name']);
$policyType = trim($postData['policy_type']);

if (empty($policyName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Policy name cannot be empty']);
    exit;
}

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }

    $stmt = $db->prepare("UPDATE policy_documents SET policy_name = ?, policy_type = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $db->error);
    }

    $stmt->bind_param('ssi', $policyName, $policyType, $docId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update policy document: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made or document not found');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Policy document updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}