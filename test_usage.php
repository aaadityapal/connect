<?php
require_once 'config/db_connect.php';

$stmt = $pdo->query("SELECT user_id, start_date, duration, lt.name 
                     FROM leave_request lr 
                     JOIN leave_types lt ON lr.leave_type = lt.id 
                     WHERE lt.name LIKE '%Casual%' OR lt.name LIKE '%Short%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($rows);
