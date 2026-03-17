<?php
require_once '/Applications/XAMPP/xamppfiles/htdocs/connect/config.php';
$stmt = $pdo->query("SELECT * FROM attendance ORDER BY date DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
