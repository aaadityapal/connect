<?php
require_once 'config/db_connect.php';

echo "Starting Collation Fix Script...\n";
echo "Database: " . $dbname . "\n";
echo "Target Collation: utf8mb4_unicode_ci\n\n";

try {
    // 1. Change Database Default Collation
    $pdo->exec("ALTER DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✔ Database default collation set to utf8mb4_unicode_ci\n";

    // 2. Get all tables
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbname' AND TABLE_TYPE = 'BASE TABLE'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Change Table Default Collation
        $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $pdo->exec($sql);
        echo "✔ Converted table: $table\n";
    }

    echo "\nAll tables have been converted successfully to match utf8mb4_unicode_ci!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
