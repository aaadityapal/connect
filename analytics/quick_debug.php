<?php
require_once '../config/db_connect.php';

// Check all leave types
$query = "SELECT id, name FROM leave_types ORDER BY name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Leave Types:\n";
foreach ($types as $type) {
    echo "ID: {$type['id']}, Name: '{$type['name']}'\n";
}

// Check specific user's leaves
echo "\nUser 1 Leaves for 2024-08:\n";
$user_query = "SELECT lr.*, lt.name as leave_type_name 
               FROM leave_request lr
               LEFT JOIN leave_types lt ON lr.leave_type = lt.id
               WHERE lr.user_id = 1 
               AND lr.status = 'approved'
               AND DATE_FORMAT(lr.start_date, '%Y-%m') = '2024-08'";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute();
$leaves = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($leaves as $leave) {
    echo "Type: '{$leave['leave_type_name']}', Duration: {$leave['duration_type']}, Start: {$leave['start_date']}, End: {$leave['end_date']}\n";
}
?>