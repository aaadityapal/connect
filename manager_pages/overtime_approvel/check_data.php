<?php
require_once '../../config.php';

try {
    $pdo = getDBConnection();
    
    echo "<h3>All Overtime Notifications:</h3>";
    $samples = $pdo->query("SELECT n.*, u.username as employee_name, a.date as attendance_date 
                           FROM overtime_notifications n 
                           JOIN users u ON n.employee_id = u.id 
                           JOIN attendance a ON n.overtime_id = a.id 
                           LIMIT 10")->fetchAll();
    
    if (empty($samples)) {
        echo "No notifications found in the database.";
    } else {
        foreach ($samples as $s) {
            echo "ID: {$s['id']} | Emp: {$s['employee_name']} | Date: {$s['attendance_date']} | Manager ID: {$s['manager_id']} | Status: {$s['status']}<br>";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
