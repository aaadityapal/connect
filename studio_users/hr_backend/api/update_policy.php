<?php
// ============================================
// update_policy.php — Update an existing policy
// POST: { id, heading, shortDesc, longDesc }
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
$heading   = isset($input['heading'])   ? trim($input['heading'])   : '';
$shortDesc = isset($input['shortDesc']) ? trim($input['shortDesc']) : '';
$longDesc  = isset($input['longDesc'])  ? trim($input['longDesc'])  : '';

if ($id <= 0 || !$heading || !$shortDesc || !$longDesc) {
    echo json_encode(['success' => false, 'message' => 'All fields and ID are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE hr_policies SET heading=?, short_desc=?, long_desc=? WHERE id=?');
    if ($stmt->execute([$heading, $shortDesc, $longDesc, $id])) {
        echo json_encode(['success' => true, 'message' => 'Policy updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
