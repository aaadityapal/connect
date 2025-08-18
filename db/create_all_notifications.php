<?php
// Creates the all_notifications table
require_once __DIR__ . '/../config/db_connect.php';

try {
    $sql = file_get_contents(__DIR__ . '/create_all_notifications.sql');
    if ($pdo->exec($sql) === false) {
        throw new Exception('Failed to execute SQL');
    }
    echo "all_notifications table ensured.\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
<?php


