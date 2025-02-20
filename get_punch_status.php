<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

date_default_timezone_set('Asia/Kolkata');
$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT * FROM attendance_logs 
        WHERE user_id = ? 
        AND DATE(punch_in) = ?
        ORDER BY punch_in DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id, $current_date]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode(['status' => 'none']); // No punch record today
    } else if ($record['punch_out'] === null) {
        echo json_encode(['status' => 'punched_in']); // Punched in, not out
    } else {
        echo json_encode(['status' => 'punched_out']); // Punched out
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
}
