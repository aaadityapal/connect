<?php
// ============================================
// update_notice.php — Update an existing notice
// POST: { id, title, shortDesc, longDesc }
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

$id        = isset($input['id'])        ? (int)$input['id']              : 0;
$title     = isset($input['title'])     ? trim($input['title'])          : '';
$shortDesc = isset($input['shortDesc']) ? trim($input['shortDesc'])      : '';
$longDesc  = isset($input['longDesc'])  ? trim($input['longDesc'])       : '';

if ($id <= 0 || !$title || !$longDesc) {
    echo json_encode(['success' => false, 'message' => 'ID, Title and Description are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE hr_notices SET title=?, short_desc=?, long_desc=? WHERE id=?');
    if ($stmt->execute([$title, $shortDesc, $longDesc, $id])) {
        echo json_encode(['success' => true, 'message' => 'Notice updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
