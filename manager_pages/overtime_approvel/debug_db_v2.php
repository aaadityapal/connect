<?php
require_once '../../config.php';
// Not starting session for CLI as it may fail

try {
    $pdo = getDBConnection();
    
    echo "<h3>Managers current notifications:</h3>";
    $notifs = $pdo->query("SELECT manager_id, COUNT(*) as count FROM `overtime_notifications` GROUP BY manager_id")->fetchAll();
    foreach ($notifs as $n) {
        echo "Manager ID {$n['manager_id']} has <b>{$n['count']}</b> pending notification(s).<br>";
    }

    echo "<h3>Sample notification data:</h3>";
    $samples = $pdo->query("SELECT n.*, u.full_name as employee_name, a.date as attendance_date 
                           FROM overtime_notifications n 
                           JOIN users u ON n.employee_id = u.id 
                           JOIN attendance a ON n.overtime_id = a.id 
                           LIMIT 5")->fetchAll();
    
    foreach ($samples as $s) {
        echo "ID: {$s['id']} | Emp: {$s['employee_name']} | Date: {$s['attendance_date']} | Manager: {$s['manager_id']} | Status: {$s['status']}<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
