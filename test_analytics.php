<?php
// Test script to verify fetch_monthly_analytics_data is working
session_start();
$_SESSION['user_id'] = 1;

require_once 'config/db_connect.php';

// Get analytics data for October 2025
$url = "http://localhost/connect/fetch_monthly_analytics_data.php?month=10&year=2025";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['status'] === 'success') {
    echo "<h2>Analytics Data for October 2025</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Name</th><th>Base Salary</th><th>Leave Taken</th><th>Leave Deduction</th></tr>";
    
    foreach ($data['data'] as $emp) {
        if ($emp['name'] === 'Preeti Choudhary') {
            echo "<tr style='background:yellow;'>";
            echo "<td><strong>" . $emp['name'] . "</strong></td>";
            echo "<td>" . $emp['base_salary'] . "</td>";
            echo "<td>" . $emp['leave_taken'] . "</td>";
            echo "<td><strong>" . $emp['leave_deduction'] . "</strong></td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    echo "<h3>Full data for Preeti:</h3>";
    foreach ($data['data'] as $emp) {
        if ($emp['name'] === 'Preeti Choudhary') {
            echo "<pre>";
            print_r($emp);
            echo "</pre>";
        }
    }
} else {
    echo "<p>Error: " . $data['message'] . "</p>";
}
?>
