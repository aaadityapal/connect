<?php
session_start();
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['document_id']) || !isset($data['status']) || !isset($data['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Check if the document exists and is assigned to the current user
    $checkStmt = $db->prepare("
        SELECT assigned_user_id 
        FROM official_documents 
        WHERE id = ? AND status = 'pending'
    ");
    $checkStmt->bind_param('i', $data['document_id']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $document = $result->fetch_assoc();

    if (!$document) {
        throw new Exception('Document not found or already processed');
    }

    // Allow both HR users and the assigned user to update the status
    if ($_SESSION['role'] !== 'HR' && $document['assigned_user_id'] != $_SESSION['user_id']) {
        throw new Exception('You are not authorized to update this document');
    }

    // Continue with your existing update code
    $stmt = $db->prepare("UPDATE official_documents SET status = ?, last_modified = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('si', $data['status'], $data['document_id']);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update document status');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 