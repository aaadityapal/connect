<?php
require_once 'config.php';
header('Content-Type: application/json');

$today = date('Y-m-d');

// Get present count
$presentQuery = "SELECT COUNT(DISTINCT user_id) as count 
                FROM attendance 
                WHERE date = '$today' 
                AND punch_in IS NOT NULL";
$presentCount = $conn->query($presentQuery)->fetch_assoc()['count'] ?? 0;

// Get late count
$lateQuery = "SELECT COUNT(DISTINCT user_id) as count 
              FROM attendance 
              WHERE date = '$today' 
              AND TIME(punch_in) > '09:30:00'";
$lateCount = $conn->query($lateQuery)->fetch_assoc()['count'] ?? 0;

// Get on leave count
$leaveQuery = "SELECT COUNT(DISTINCT user_id) as count 
               FROM leaves 
               WHERE '$today' BETWEEN from_date AND to_date 
               AND status = 'Approved'";
$onLeaveCount = $conn->query($leaveQuery)->fetch_assoc()['count'] ?? 0;

echo json_encode([
    'presentCount' => (int)$presentCount,
    'lateCount' => (int)$lateCount,
    'onLeaveCount' => (int)$onLeaveCount,
    'lastUpdated' => date('h:i A')
]);