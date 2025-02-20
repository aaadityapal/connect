<?php
session_start();
require_once 'config.php';

if (!isset($_POST['date'])) {
    exit('No date specified');
}

$date = $_POST['date'];
$user_id = $_SESSION['user_id'];

try {
    // Get attendance data
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $date]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get leaves data
    $stmt = $pdo->prepare("SELECT * FROM leaves WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $date]);
    $leaves = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'punch_in' => $attendance['punch_in'] ?? null,
        'punch_out' => $attendance['punch_out'] ?? null,
        'total_hours' => $attendance['total_hours'] ?? null,
        'leave_status' => $leaves['status'] ?? null,
        'leave_type' => $leaves['type'] ?? null
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
