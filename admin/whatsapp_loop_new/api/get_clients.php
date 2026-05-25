<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$query = "SELECT id, name, phone, email, notes, status, DATE_FORMAT(created_at, '%b %d, %Y') as added_date, DATE_FORMAT(created_at, '%Y-%m') as added_month, DATE_FORMAT(created_at, '%M %Y') as added_month_label FROM clients ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();

$clients = array();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Fetch tags for this client
    $tag_query = "SELECT t.name FROM tags t JOIN client_tags ct ON t.id = ct.tag_id WHERE ct.client_id = :client_id";
    $tag_stmt = $conn->prepare($tag_query);
    $tag_stmt->bindParam(":client_id", $row['id']);
    $tag_stmt->execute();
    $row['tags'] = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Generate initials for the frontend
    $words = explode(" ", $row['name']);
    $initials = "";
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
    }
    $row['initials'] = substr($initials, 0, 2);
    
    $clients[] = $row;
}

echo json_encode($clients);
?>
