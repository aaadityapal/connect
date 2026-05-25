<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['sequence_id']) || !isset($data['client_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input data"]);
    exit;
}

try {
    $sequence_id = $data['sequence_id'];
    $client_id = $data['client_id'];

    // 1. Fetch sequence name
    $stmt = $conn->prepare("SELECT name FROM sequences WHERE id = ?");
    $stmt->execute([$sequence_id]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence_name = $seq ? $seq['name'] : 'Unknown Sequence';

    // 2. Fetch client details for snapshot
    $stmt = $conn->prepare("SELECT name, phone FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        throw new Exception("Client not found");
    }

    $client_name = $client['name'];
    $client_phone = $client['phone'];

    // 3. Find first step of sequence
    $stmt = $conn->prepare("SELECT id FROM sequence_steps WHERE sequence_id = ? ORDER BY step_order ASC LIMIT 1");
    $stmt->execute([$sequence_id]);
    $step = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_step_id = $step ? $step['id'] : NULL;

    // 4. Enroll client (INSERT IGNORE)
    $stmt = $conn->prepare("INSERT IGNORE INTO sequence_subscriptions 
        (sequence_id, sequence_name, client_id, client_name, client_phone, current_step_id, status, next_send_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'Running', NOW())");
    
    $stmt->execute([
        $sequence_id,
        $sequence_name,
        $client_id,
        $client_name,
        $client_phone,
        $current_step_id
    ]);

    echo json_encode(["success" => true, "message" => "Client enrolled successfully"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
