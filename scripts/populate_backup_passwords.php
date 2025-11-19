<?php
// Run this script from the command line to populate `backup_password` for all users.
// Usage (from project root):
// php scripts/populate_backup_passwords.php

require_once __DIR__ . '/../config.php';

try {
    // Shared backup plain-text password
    $backup_plain = '@rchitectshive@750';

    // Fetch user ids
    $stmt = $pdo->query("SELECT id FROM users");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $update = $pdo->prepare("UPDATE users SET backup_password = :hash WHERE id = :id");

    $count = 0;
    foreach ($ids as $id) {
        $hash = password_hash($backup_plain, PASSWORD_DEFAULT);
        $update->execute([
            ':hash' => $hash,
            ':id' => $id
        ]);
        $count++;
    }

    echo "Populated backup_password for {$count} users.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
