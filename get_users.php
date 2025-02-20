<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config/db_connect.php';

// Verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authorized']));
}

$term = isset($_GET['term']) ? $_GET['term'] : '';
$term = $conn->real_escape_string($term);

$query = "SELECT id, username, type 
          FROM users 
          WHERE (username LIKE '%$term%' OR type LIKE '%$term%') 
          ORDER BY username ASC";

$result = $conn->query($query);
$users = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'username' => htmlspecialchars($row['username']),
            'type' => htmlspecialchars($row['type'])
        ];
    }
}

echo json_encode($users);
?>
