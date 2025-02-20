<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$date = $_POST['date'] ?? '';
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$assigned_to = $_POST['assigned_to'] ?? '';
$created_by = $_SESSION['user_id'] ?? 0;

if (empty($title) || empty($date) || empty($assigned_to)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$query = "INSERT INTO tasks (title, description, date, assigned_to, created_by, created_at) 
          VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($query);
$stmt->bind_param("sssii", $title, $description, $date, $assigned_to, $created_by);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
} 