<?php
session_start();
require_once 'config/db_connect.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$today = date('Y-m-d');

$query = "SELECT 
            u.id, 
            u.username,
            u.role,
            u.designation,
            u.profile_image,
            u.status,
            a.punch_in,
            a.punch_out
          FROM users u 
          LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
          WHERE u.id != ? 
            AND u.deleted_at IS NULL 
            AND u.status = 'active' ";

if ($search) {
    $query .= "AND (
        u.username LIKE ? 
        OR u.role LIKE ? 
        OR u.designation LIKE ?
    ) ";
}

$query .= "ORDER BY a.punch_in DESC, u.username ASC";

// Debug: Print the query
error_log("Query: " . $query);

$stmt = $conn->prepare($query);

if ($search) {
    $searchParam = "%$search%";
    $stmt->bind_param("sisss", 
        $today,
        $_SESSION['user_id'], 
        $searchParam, 
        $searchParam, 
        $searchParam
    );
} else {
    $stmt->bind_param("si", $today, $_SESSION['user_id']);
}

$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    // Debug: Print each row
    error_log("User row: " . print_r($row, true));
    
    // User is online if they have punched in but not punched out today
    $is_online = !empty($row['punch_in']) && empty($row['punch_out']);
    
    $users[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'role' => $row['role'],
        'designation' => $row['designation'],
        'profile_image' => $row['profile_image'] ?? null,
        'is_online' => $is_online,
        'punch_in' => $row['punch_in']
    ];
}

// Debug: Print final users array
error_log("Final users array: " . print_r($users, true));

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'users' => $users
]); 