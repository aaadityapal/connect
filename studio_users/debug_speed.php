<?php
require_once '../config/db_connect.php';
$user_id = 45; // Test with a user ID

$start = microtime(true);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM studio_assigned_tasks");
$stmt->execute();
$count = $stmt->fetchColumn();
echo "Total Rows in studio_assigned_tasks: $count (Took: " . (microtime(true) - $start) . "s)\n";

$fromDate = date('Y-m-d', strtotime('monday this week'));
$toDate   = date('Y-m-d', strtotime('sunday this week'));

$start = microtime(true);
$baseSQL = "FROM studio_assigned_tasks WHERE deleted_at IS NULL AND FIND_IN_SET(?, REPLACE(assigned_to, ', ', ',')) AND due_date BETWEEN ? AND ?";
$stmtTotal = $pdo->prepare("SELECT COUNT(*) " . $baseSQL);
$stmtTotal->execute([$user_id, $fromDate, $toDate]);
$res = $stmtTotal->fetchColumn();
echo "KPI Query (FIND_IN_SET): $res rows (Took: " . (microtime(true) - $start) . "s)\n";

$start = microtime(true);
$tlQuery = "SELECT sat.*, u.username as creator_name 
            FROM studio_assigned_tasks sat
            LEFT JOIN users u ON sat.created_by = u.id
            WHERE sat.deleted_at IS NULL 
            AND FIND_IN_SET(?, REPLACE(sat.assigned_to, ', ', ',')) 
            AND sat.due_date BETWEEN ? AND ?
            ORDER BY sat.created_at DESC LIMIT 50";
$tlStmt = $pdo->prepare($tlQuery);
$tlStmt->execute([$user_id, $fromDate, $toDate]);
$rows = $tlStmt->fetchAll();
echo "Task List Query (FIND_IN_SET + JOIN): " . count($rows) . " rows (Took: " . (microtime(true) - $start) . "s)\n";
?>
