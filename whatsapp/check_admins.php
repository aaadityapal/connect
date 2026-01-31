<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
    echo "Checking admin_notifications table...\n";

    $stmt = $pdo->query("SELECT * FROM admin_notifications WHERE is_active = 1");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($admins) . " active admins:\n";
    foreach ($admins as $admin) {
        echo "ID: {$admin['id']}, Name: {$admin['admin_name']}, Phone: {$admin['phone']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
