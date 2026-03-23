<?php
require_once 'config/db_connect.php';
try {
    echo "document_acknowledgments:\n";
    $stmt = $pdo->query("DESCRIBE document_acknowledgments");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\nhr_documents:\n";
    $stmt = $pdo->query("DESCRIBE hr_documents");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
