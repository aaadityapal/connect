<?php
// Include database connection
require_once 'config/db_connect.php';

// Check table structure
echo "=== Table Structure ===\n";
try {
    $stmt = $pdo->prepare("DESCRIBE overtime_notifications");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (PDOException $e) {
    echo "Error checking table structure: " . $e->getMessage() . "\n";
}

// Check recent entries
echo "\n=== Recent Entries ===\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM overtime_notifications ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (PDOException $e) {
    echo "Error checking recent entries: " . $e->getMessage() . "\n";
}
?> 