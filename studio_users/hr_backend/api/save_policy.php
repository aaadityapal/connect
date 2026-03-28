<?php
header('Content-Type: application/json');
require_once 'db.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data payload']);
    exit;
}

$heading = trim($data['heading'] ?? '');
$shortDesc = trim($data['shortDesc'] ?? '');
$longDesc = trim($data['longDesc'] ?? '');

if (empty($heading) || empty($longDesc)) {
    echo json_encode(['success' => false, 'message' => 'Heading and Long Description are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO hr_policies (heading, short_desc, long_desc) VALUES (?, ?, ?)");
    $stmt->execute([$heading, $shortDesc, $longDesc]);

    echo json_encode(['success' => true, 'message' => 'Policy successfully published!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
