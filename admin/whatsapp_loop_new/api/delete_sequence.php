<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input data"]);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM sequences WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
