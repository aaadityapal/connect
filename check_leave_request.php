<?php
require_once 'config/db_connect.php';
try {
    $stmt = $pdo->query("DESCRIBE leave_request");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
