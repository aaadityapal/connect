<?php
/**
 * AUDIT DISTANCE VERIFICATION
 * manager_pages/travel_expenses_approval/api/check_audit.php
 */
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // If no IDs provided in GET, default to the production IDs requested
    if (isset($_GET['ids']) && !empty($_GET['ids'])) {
        $ids = explode(',', $_GET['ids']);
    } else {
        $ids = [2664, 2665];
    }

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $query = "
        SELECT 
            id, 
            user_id,
            distance as claimed_distance,
            confirmed_distance as manager_distance,
            hr_confirmed_distance as hr_distance,
            distance_confirmed_at,
            hr_confirmed_at
        FROM travel_expenses 
        WHERE id IN ($placeholders)
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($ids);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate discrepancies
    foreach ($results as &$row) {
        $row['discrepancies'] = [
            'mgr_vs_claim' => abs(floatval($row['manager_distance'] ?? 0) - floatval($row['claimed_distance'])),
            'hr_vs_mgr' => ($row['hr_distance'] !== null && $row['manager_distance'] !== null) ? abs(floatval($row['hr_distance']) - floatval($row['manager_distance'])) : null
        ];
    }

    echo json_encode(['success' => true, 'data' => $results]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
