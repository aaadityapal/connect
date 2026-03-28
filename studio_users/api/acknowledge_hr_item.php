<?php
require_once '../../config/db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = $_SESSION['username'];
$itemId = $data['item_id'] ?? null;
$itemType = $data['item_type'] ?? null; // 'policy' or 'notice'

if (!$itemId || !$itemType) {
    echo json_encode(['success' => false, 'message' => 'Missing ID or Type']);
    exit;
}

try {
    global $pdo;
    
    // Insert into the new unique table: hr_user_compliance_records
    $stmt = $pdo->prepare("INSERT INTO hr_user_compliance_records (user_uid, document_id, document_type, completion_timestamp) 
                           VALUES (?, ?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE completion_timestamp = NOW()");
    $stmt->execute([$username, $itemId, $itemType]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
