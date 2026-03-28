<?php
// ============================================
// save_custom.php — Save/Update HR Custom Request
// POST: { title, shortDesc, longDesc }
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

$title     = isset($input['title'])     ? trim($input['title'])     : '';
$shortDesc = isset($input['shortDesc']) ? trim($input['shortDesc']) : '';
$longDesc  = isset($input['longDesc'])  ? trim($input['longDesc'])  : '';

if (!$title || !$shortDesc || !$longDesc) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    'INSERT INTO hr_customs (title, short_desc, long_desc) VALUES (?, ?, ?)'
);
$stmt->bind_param('sss', $title, $shortDesc, $longDesc);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Custom update saved.', 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $db->error]);
}

$stmt->close();
$db->close();
