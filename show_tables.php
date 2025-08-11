<?php
require_once 'config/db_connect.php';

// Show all tables
echo "=== ALL TABLES ===\n";
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "- $table\n";
}

// Look for tables related to leave
echo "\n=== LEAVE-RELATED TABLES ===\n";
foreach ($tables as $table) {
    if (stripos($table, 'leave') !== false) {
        echo "- $table\n";
        
        // Show table structure
        echo "  Structure:\n";
        $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        echo "\n";
    }
}
?>
