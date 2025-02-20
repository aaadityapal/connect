<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'Authentication required']));
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$document_id = $data['document_id'] ?? null;

if (!$document_id) {
    die(json_encode(['success' => false, 'error' => 'Document ID is required']));
}

try {
    $pdo->beginTransaction();

    // Check if an acknowledgment record already exists
    $checkStmt = $pdo->prepare("SELECT id, status FROM document_acknowledgments WHERE document_id = ? AND user_id = ?");
    $checkStmt->execute([$document_id, $_SESSION['user_id']]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Update existing record
        $updateStmt = $pdo->prepare("UPDATE document_acknowledgments 
            SET status = 'acknowledged', 
                acknowledged_at = NOW() 
            WHERE id = ?");
        $updateStmt->execute([$existing['id']]);
    } else {
        // Insert new record
        $insertStmt = $pdo->prepare("INSERT INTO document_acknowledgments 
            (document_id, user_id, status, acknowledged_at, created_at) 
            VALUES (?, ?, 'acknowledged', NOW(), NOW())");
        $insertStmt->execute([$document_id, $_SESSION['user_id']]);
    }

    // Log the acknowledgment in hr_documents_log
    $logStmt = $pdo->prepare("INSERT INTO hr_documents_log 
        (document_id, action, action_by, action_date, document_type) 
        SELECT ?, 'acknowledge', ?, NOW(), type 
        FROM hr_documents 
        WHERE id = ?");
    $logStmt->execute([$document_id, $_SESSION['user_id'], $document_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Document successfully acknowledged'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in acknowledge_document.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error acknowledging document'
    ]);
} 