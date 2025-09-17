<?php
// Simple test export to identify the issue
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    die("Access denied. HR role required.");
}

// Include database connection
require_once '../config/db_connect.php';

// Get the filter month
$selected_filter_month = $_GET['filter_month'] ?? date('Y-m');

try {
    // Test database connection
    $test_query = "SELECT COUNT(*) as user_count FROM users WHERE status = 'active'";
    $test_stmt = $pdo->prepare($test_query);
    $test_stmt->execute();
    $result = $test_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Set headers for download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="test_export_' . $selected_filter_month . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Simple HTML table
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Test Export</title>
        <style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000; padding: 8px; }
            th { background-color: #4472C4; color: white; }
        </style>
    </head>
    <body>
        <h2>Test Export - ' . date('F Y', strtotime($selected_filter_month)) . '</h2>
        <table>
            <tr>
                <th>Test</th>
                <th>Result</th>
            </tr>
            <tr>
                <td>Database Connection</td>
                <td>Success</td>
            </tr>
            <tr>
                <td>Active Users Count</td>
                <td>' . $result['user_count'] . '</td>
            </tr>
            <tr>
                <td>Filter Month</td>
                <td>' . $selected_filter_month . '</td>
            </tr>
        </table>
    </body>
    </html>';
    
} catch (Exception $e) {
    header('Content-Type: text/html');
    echo "<h3>Error in test export</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>