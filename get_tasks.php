<?php
session_start();
require_once 'config/db_connect.php';

$category_id = $_GET['category_id'] ?? 0;
$statuses = explode(',', $_GET['statuses'] ?? '');
$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM tasks 
          WHERE category_id = ? 
          AND created_by = ?";

if (!empty($statuses)) {
    $query .= " AND status IN (" . str_repeat('?,', count($statuses) - 1) . "?)";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);

$params = [$category_id, $user_id];
if (!empty($statuses)) {
    $params = array_merge($params, $statuses);
}

$types = "ii" . str_repeat("s", count($statuses));
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();
$tasks = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($tasks);
?>
