<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

try {
    $stmt = $pdo->query("SELECT * FROM travel_transport_rates ORDER BY transport_mode ASC");
    $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'rates' => $rates
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>