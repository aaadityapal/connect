<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

try {
    // Get total allocated leaves for the month
    $stmt = $pdo->prepare("
        SELECT leave_allocation 
        FROM leave_allocations 
        WHERE user_id = ? AND YEAR(valid_from) = ? AND MONTH(valid_from) = ?
    ");
    $stmt->execute([$user_id, $year, $month]);
    $allocation = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalLeaves = $allocation ? $allocation['leave_allocation'] : 2; // Default monthly allocation

    // Get taken leaves
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as taken_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
        FROM leaves 
        WHERE user_id = ? 
        AND YEAR(start_date) = ? 
        AND MONTH(start_date) = ?
        AND status != 'rejected'
    ");
    $stmt->execute([$user_id, $year, $month]);
    $leaveCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get pending leaves details
    $stmt = $pdo->prepare("
        SELECT 
            id,
            leave_type,
            start_date,
            end_date,
            reason,
            created_at
        FROM leaves 
        WHERE user_id = ? 
        AND status = 'pending'
        AND YEAR(start_date) = ? 
        AND MONTH(start_date) = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id, $year, $month]);
    $pendingLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $totalLeaves,
            'taken' => $leaveCount['taken_count'] ?? 0,
            'remaining' => $totalLeaves - ($leaveCount['taken_count'] ?? 0),
            'pending_count' => $leaveCount['pending_count'] ?? 0,
            'pending_leaves' => $pendingLeaves
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
