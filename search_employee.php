<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authorized']));
}

$term = isset($_GET['term']) ? $_GET['term'] : '';
$term = $conn->real_escape_string($term);

$query = "SELECT id, name, designation 
          FROM employees 
          WHERE status = 'active' 
          AND (name LIKE '%$term%' OR designation LIKE '%$term%') 
          ORDER BY name ASC";

$result = $conn->query($query);
$employees = [];

while ($row = $result->fetch_assoc()) {
    $employees[] = [
        'id' => $row['id'],
        'name' => htmlspecialchars($row['name']),
        'designation' => htmlspecialchars($row['designation'])
    ];
}

echo json_encode($employees);
?>
