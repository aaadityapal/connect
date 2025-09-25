<?php
// Check what payment entries exist
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db_connect.php';

try {
    $query = "SELECT payment_id, project_id, payment_amount, payment_date FROM hr_payment_entries ORDER BY payment_id DESC LIMIT 10";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($payments) . " payment entries:\n";
    foreach ($payments as $payment) {
        echo "ID: {$payment['payment_id']}, Project: {$payment['project_id']}, Amount: {$payment['payment_amount']}, Date: {$payment['payment_date']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>