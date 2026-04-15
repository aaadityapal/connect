<?php
// ============================================
// delete_notice.php — Delete an existing notice
// POST: { id }
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM hr_notices WHERE id=?');
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Notice deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
