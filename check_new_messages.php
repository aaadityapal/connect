<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'hasNew' => false];

if (!isset($_SESSION['user_id']) || !isset($_GET['user_id'])) {
    echo json_encode($response);
    exit;
}

$current_user = $_SESSION['user_id'];
$other_user = $_GET['user_id'];
$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : 0;

try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM chat_messages
        WHERE ((sender_id = ? AND receiver_id = ?) 
            OR (sender_id = ? AND receiver_id = ?))
            AND UNIX_TIMESTAMP(created_at) > ?
    ");

    $stmt->bind_param("iiiii", $current_user, $other_user, $other_user, $current_user, $last_check);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $response['success'] = true;
    $response['hasNew'] = $row['count'] > 0;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response); 