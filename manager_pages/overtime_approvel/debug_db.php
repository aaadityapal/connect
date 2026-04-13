<?php
require_once '../../config.php';

echo "<h2>Database Debug Information</h2>";

try {
    $pdo = getDBConnection();
    
    // Check if session is set
    session_start();
    echo "Current Session User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "NOT SET") . "<br><br>";

    // Count records in tables
    $tables = ['users', 'attendance', 'overtime_notifications', 'shifts'];
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "Table <b>$table</b> has <b>$count</b> records.<br>";
    }

    echo "<h3>Sample Notifications:</h3>";
    $notifs = $pdo->query("SELECT * FROM `overtime_notifications` LIMIT 5")->fetchAll();
    if (empty($notifs)) {
        echo "No records found in overtime_notifications.<br>";
    } else {
        echo "<pre>" . print_r($notifs, true) . "</pre>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
