<?php
require_once '../../config.php';

try {
    $pdo = getDBConnection();
    
    echo "<h3>Attendance table columns:</h3>";
    $columns = $pdo->query("DESCRIBE attendance")->fetchAll();
    foreach ($columns as $c) {
        echo "{$c['Field']} ({$c['Type']})<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
