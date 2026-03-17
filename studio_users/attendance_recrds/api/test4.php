<?php
require_once '../../../config.php';

$sql = "SELECT lr.start_date, lr.end_date, lt.name AS leave_name, lr.duration_type, lr.day_type, lr.time_from, lr.time_to 
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.status = 'approved' LIMIT 10";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
