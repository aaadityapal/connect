<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/db_connect.php';

$userId = $_SESSION['user_id'];
$docId = $_POST['document_id'] ?? null;

if (!$docId) {
    echo json_encode(['status' => 'error', 'message' => 'Document ID is required']);
    exit;
}

try {
    // 1. First, verify the document exists and get its name for logging
    $stmt = $pdo->prepare("SELECT original_name FROM hr_documents WHERE id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();

    if (!$doc) {
        throw new Exception("Document not found.");
    }

    $docName = $doc['original_name'] ?: 'Official Document';

    // 2. Check if already acknowledged
    $checkStmt = $pdo->prepare("SELECT id FROM document_acknowledgments WHERE user_id = ? AND document_id = ?");
    $checkStmt->execute([$userId, $docId]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['status' => 'success', 'message' => 'Already acknowledged.']);
        exit;
    }

    // 3. Insert acknowledgment
    $ackStmt = $pdo->prepare("INSERT INTO document_acknowledgments (user_id, document_id, acknowledged_at, status) VALUES (?, ?, NOW(), 'acknowledged')");
    $ackStmt->execute([$userId, $docId]);

    // 4. Log to global activity log
    try {
        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, ?, ?)");
        $logStmt->execute([$userId, 'document_acknowledgment', "Acknowledged official HR document: $docName"]);
    } catch (Exception $e) {}

    echo json_encode(['status' => 'success', 'message' => 'Acknowledgment recorded!']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
