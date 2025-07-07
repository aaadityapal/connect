<?php
/**
 * Reset Overtime Payments Status
 * 
 * This script resets all overtime payments to 'unpaid' status for testing purposes.
 */

// Include database connection
require_once '../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Reset Overtime Payments Status</h1>";

// Check if the overtime_payments table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_payments'");
if (mysqli_num_rows($table_check) == 0) {
    die("<p style='color:red'>Error: The overtime_payments table does not exist!</p>");
}

// Reset all payments to 'unpaid'
$update_query = "UPDATE overtime_payments SET status = 'unpaid'";
$update_result = mysqli_query($conn, $update_query);

if ($update_result) {
    $affected_rows = mysqli_affected_rows($conn);
    echo "<p style='color:green'>Successfully reset $affected_rows payment(s) to 'unpaid' status.</p>";
} else {
    echo "<p style='color:red'>Error resetting payment status: " . mysqli_error($conn) . "</p>";
}

// Show the current status of payments
$select_query = "SELECT id, overtime_id, employee_id, status, payment_date FROM overtime_payments ORDER BY id DESC LIMIT 10";
$select_result = mysqli_query($conn, $select_query);

if ($select_result && mysqli_num_rows($select_result) > 0) {
    echo "<h2>Current Payment Status</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Overtime ID</th><th>Employee ID</th><th>Status</th><th>Payment Date</th></tr>";
    
    while ($row = mysqli_fetch_assoc($select_result)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['overtime_id']}</td>";
        echo "<td>{$row['employee_id']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['payment_date']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No payment records found.</p>";
}

// Close the database connection
mysqli_close($conn);

echo "<p>Script completed.</p>";
?> 