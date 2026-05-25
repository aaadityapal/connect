<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$query = "SELECT id, name FROM tags ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();

$tags = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tags[] = $row;
}

echo json_encode($tags);
?>
