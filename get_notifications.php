<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'all';
$userId = $_SESSION['user_id'];

// Modified query to include user and attendance details
$query = "
    SELECT 
        n.id,
        n.type,
        n.message,
        n.created_at,
        n.read_status as `read`,
        u.username,
        u.unique_id,
        a.punch_in,
        a.punch_out,
        a.date as attendance_date,
        a.location,
        a.status as attendance_status
    FROM notifications n
    LEFT JOIN attendance a ON n.related_id = a.id
    LEFT JOIN users u ON a.user_id = u.id
    WHERE n.user_id = :userId
";

if ($type === 'punch') {
    $query .= " AND n.type IN ('punch_in', 'punch_out')";
} elseif ($type === 'tasks') {
    $query .= " AND n.type = 'task'";
}

$query .= " ORDER BY n.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($query);
$params = ['userId' => $userId];
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format the notifications with additional details
foreach ($notifications as &$notification) {
    if (in_array($notification['type'], ['punch_in', 'punch_out'])) {
        $time = $notification['type'] === 'punch_in' ? $notification['punch_in'] : $notification['punch_out'];
        $formattedTime = date('h:i A', strtotime($time));
        $formattedDate = date('d M Y', strtotime($notification['attendance_date']));
        
        $notification['details'] = [
            'time' => $formattedTime,
            'date' => $formattedDate,
            'location' => $notification['location'],
            'employee' => [
                'name' => $notification['username'],
                'id' => $notification['unique_id']
            ],
            'status' => $notification['attendance_status']
        ];
    }
}

echo json_encode($notifications); 