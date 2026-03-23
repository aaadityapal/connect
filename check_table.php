<?php
require_once 'config/db_connect.php';
try {
    $stmt = $pdo->query("DESCRIBE document_acknowledgments");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
