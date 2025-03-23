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

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing document ID']);
    exit;
}

$docId = (int)$_GET['id'];

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }

    $stmt = $db->prepare("SELECT 
        id, 
        policy_name, 
        policy_type, 
        original_filename,
        status,
        DATE_FORMAT(created_at, '%d %b %Y') as upload_date,
        DATE_FORMAT(updated_at, '%d %b %Y') as last_updated
        FROM policy_documents 
        WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $db->error);
    }

    $stmt->bind_param('i', $docId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Policy document not found');
    }

    $document = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'document' => $document
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}