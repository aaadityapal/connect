<?php
session_start();
require_once 'config/db_connect.php';

$category_id = $_GET['category_id'] ?? 0;
$statuses = isset($_GET['statuses']) ? explode(',', $_GET['statuses']) : [];
$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM tasks WHERE category_id = ? AND created_by = ?";
if (!empty($statuses)) {
    $query .= " AND status IN (" . str_repeat('?,', count($statuses) - 1) . "?)";
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    die(json_encode(['error' => 'Query preparation failed']));
}

$types = "ii" . str_repeat("s", count($statuses));
$params = array_merge([$category_id, $user_id], $statuses);
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();
$tasks = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($tasks);
?>
