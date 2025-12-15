<?php
require_once 'config/db_connect.php';

try {
    $pdo->exec("ALTER TABLE construction_site_tasks ADD COLUMN supervisor_notes LONGTEXT AFTER description");
    echo "Column 'supervisor_notes' added successfully.";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage();
}
?>