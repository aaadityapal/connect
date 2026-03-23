<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n') - 1; // 0-indexed

try {
    // 1. Fetch balances from leave_bank
    $queryRaw = "SELECT lb.remaining_balance, lb.total_balance, lt.name as leave_type 
                 FROM leave_bank lb 
                 JOIN leave_types lt ON lb.leave_type_id = lt.id 
                 WHERE lb.user_id = ? AND lb.year = ?";
    $stmt = $pdo->prepare($queryRaw);
    $stmt->execute([$user_id, $year]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch usage for the selected month/year
    // $month is 0-indexed, so Jan = 0. DateTime handles this.
    $dateObj = new DateTime("$year-" . ($month + 1) . "-01");
    $monthStart = $dateObj->format('Y-m-01');
    $monthEnd = $dateObj->format('Y-m-t');

    // Correcting the usage calculation:
    // Short Leaves are stored with 0 duration, so we count them as 1 unit per row.
    // Others use their duration (handles full/half days).
    $queryUsed = "SELECT lt.name, SUM(CASE WHEN lt.name = 'Short Leave' THEN 1 ELSE lr.duration END) as used 
                  FROM leave_request lr 
                  JOIN leave_types lt ON lr.leave_type = lt.id 
                  WHERE lr.user_id = ? 
                  AND lr.start_date BETWEEN ? AND ? 
                  AND lr.status != 'rejected'
                  GROUP BY lt.name";
    
    $stmtUsed = $pdo->prepare($queryUsed);
    $stmtUsed->execute([$user_id, $monthStart, $monthEnd]);
    $usage = $stmtUsed->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'success' => true,
        'data' => $balances,
        'this_month_usage' => $usage
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
