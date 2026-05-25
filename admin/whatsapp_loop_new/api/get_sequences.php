<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

try {
    // 1. Fetch all sequences
    $stmt = $conn->prepare("SELECT * FROM sequences ORDER BY created_at DESC");
    $stmt->execute();
    $sequences = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $full_data = [];

    foreach ($sequences as $seq) {
        $seq_id = $seq['id'];

        // 2. Fetch steps for this sequence
        $stmt_steps = $conn->prepare("SELECT * FROM sequence_steps WHERE sequence_id = ? ORDER BY step_order ASC");
        $stmt_steps->execute([$seq_id]);
        $steps = $stmt_steps->fetchAll(PDO::FETCH_ASSOC);

        // 3. Fetch enrolled clients and their states
        $stmt_subs = $conn->prepare("SELECT * FROM sequence_subscriptions WHERE sequence_id = ?");
        $stmt_subs->execute([$seq_id]);
        $subs = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);

        $enrolledClients = [];
        $clientStates = [];

        foreach ($subs as $sub) {
            $client_id = $sub['client_id'];
            $enrolledClients[] = (int)$client_id;
            
            // Map state for frontend
            $clientStates[$client_id] = [
                'current_step_id' => $sub['current_step_id'],
                'status' => $sub['status'],
                'client_name' => $sub['client_name'],
                'client_phone' => $sub['client_phone'],
                'sequence_name' => $sub['sequence_name'],
                'nextSendTime' => $sub['next_send_at'] ? date('Y-m-d H:i', strtotime($sub['next_send_at'])) : "Pending"
            ];
        }

        // Remap steps to match the frontend's expected shape
        $mapped_steps = array_map(function($step) {
            return [
                'id'            => (int)$step['id'],
                'title'         => 'Step #' . $step['step_order'] . ' ' . $step['template_name'],
                'templateKey'   => $step['template_name'],   // key == name for Meta
                'delay_value'   => (int)$step['delay_value'],
                'delay_unit'    => $step['delay_unit'],
                'header_type'   => $step['header_type'] ?? 'NONE',
                'media_path'    => $step['media_path'],
                'media_filename'=> $step['media_filename'],
                'media_data'    => '',  // file is on disk, not re-sent to client
            ];
        }, $steps);

        $full_data[] = [
            'id'              => (int)$seq['id'],
            'title'           => $seq['name'],
            'desc'            => $seq['description'],
            'persistOnReply'  => (bool)$seq['is_persistent'],
            'stopOnReply'     => isset($seq['stop_on_reply']) ? (bool)$seq['stop_on_reply'] : true,
            'steps'           => $mapped_steps,
            'enrolledClients' => $enrolledClients,
            'clientStates'    => $clientStates
        ];
    }

    echo json_encode($full_data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
