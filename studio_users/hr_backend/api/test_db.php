<?php
// Test DB from API folder
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
global $pdo;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM hr_policies");
    $count = $stmt->fetchColumn();
    echo "SUCCESS: Found $count policies.";
} catch (Exception $e) {
    echo "FAILURE: " . $e->getMessage();
}
?>
