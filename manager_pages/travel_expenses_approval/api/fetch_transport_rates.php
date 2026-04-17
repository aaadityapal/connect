<?php
/**
 * FETCH TRAVEL TRANSPORT RATES
 * manager_pages/travel_expenses_approval/api/fetch_transport_rates.php
 */
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $stmt = $pdo->query("SELECT transport_mode, rate_per_km FROM travel_transport_rates ORDER BY transport_mode ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'rates' => $rows
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
