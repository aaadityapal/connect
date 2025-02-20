<?php
require_once 'config.php';

$start = $_GET['start'];
$end = $_GET['end'];

$query = "
    SELECT lr.*, u.username 
    FROM leave_request lr
    JOIN users u ON lr.user_id = u.id
    WHERE lr.status = 'approved'
    AND lr.start_date <= ?
    AND lr.end_date >= ?
    ORDER BY lr.start_date ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$end, $start]);
$leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

$leavesByDate = [];

foreach ($leaves as $leave) {
    $startDate = new DateTime($leave['start_date']);
    $endDate = new DateTime($leave['end_date']);
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
    
    foreach ($dateRange as $date) {
        $dateStr = $date->format('Y-m-d');
        if (!isset($leavesByDate[$dateStr])) {
            $leavesByDate[$dateStr] = [];
        }
        $leavesByDate[$dateStr][] = $leave;
    }
}

header('Content-Type: application/json');
echo json_encode($leavesByDate); 