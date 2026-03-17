<?php
// Run this file once in the browser to create the studio_assigned_tasks table.
// URL: http://localhost/connect/studio_users/sql/create_table.php

require_once '../../config/db_connect.php';

$sql = file_get_contents(__DIR__ . '/studio_assigned_tasks.sql');

try {
    $pdo->exec($sql);
    echo "<div style='font-family:sans-serif; padding:2rem; color:green;'>";
    echo "<h2>✅ Table <code>studio_assigned_tasks</code> created successfully!</h2>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div style='font-family:sans-serif; padding:2rem; color:red;'>";
    echo "<h2>❌ Error creating table</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
