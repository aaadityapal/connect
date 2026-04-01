<?php
require_once 'config/db_connect.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS travel_payment_auth (
        user_id INT PRIMARY KEY,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
