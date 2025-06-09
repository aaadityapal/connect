<?php
// Test script for batch expense approval
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/db_connect.php';

// Function to simulate form submission
function simulateFormSubmission($url, $postData) {
    echo "<h3>Testing: $url</h3>";
    echo "<pre>POST data: " . print_r($postData, true) . "</pre>";
    
    // Create context for POST request
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($postData)
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Send the request
    try {
        $result = file_get_contents("http://{$_SERVER['HTTP_HOST']}/hr/$url", false, $context);
        echo "<p>Response:</p><pre>" . htmlspecialchars($result) . "</pre>";
        return $result;
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
        return null;
    }
}

// Get some test expense IDs
$test_expense_ids = [];
$query = "SELECT id FROM travel_expenses WHERE manager_status = 'pending' LIMIT 3";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $test_expense_ids[] = $row['id'];
    }
}

if (empty($test_expense_ids)) {
    echo "<p>No pending expenses found for testing.</p>";
    exit;
}

// Display test expense IDs
echo "<h2>Test Expense IDs</h2>";
echo "<pre>" . print_r($test_expense_ids, true) . "</pre>";

// Test single expense approval
echo "<h2>Test 1: Single Expense Approval</h2>";
$single_expense_data = [
    'expense_id' => $test_expense_ids[0],
    'action_type' => 'approve',
    'notes' => 'Test approval via test script'
];
simulateFormSubmission('process_expense_action.php', $single_expense_data);

// Test batch expense approval
echo "<h2>Test 2: Batch Expense Approval</h2>";
$batch_expense_data = [
    'expense_id' => $test_expense_ids[0],
    'action_type' => 'approve',
    'notes' => 'Test batch approval via test script',
    'all_expense_ids' => json_encode($test_expense_ids)
];
simulateFormSubmission('process_expense_approval.php', $batch_expense_data);

// Check expense_action_logs table
echo "<h2>Expense Action Logs</h2>";
$logs_query = "SELECT * FROM expense_action_logs ORDER BY created_at DESC LIMIT 10";
$logs_result = $conn->query($logs_query);

if ($logs_result && $logs_result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Expense ID</th><th>User ID</th><th>Action</th><th>Notes</th><th>Created At</th></tr>";
    
    while ($row = $logs_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['expense_id']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>{$row['action_type']}</td>";
        echo "<td>{$row['notes']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No logs found.</p>";
}

// Check updated expense statuses
echo "<h2>Updated Expense Statuses</h2>";
$expense_query = "SELECT id, manager_status, accountant_status, hr_status, updated_at FROM travel_expenses WHERE id IN (" . implode(',', $test_expense_ids) . ")";
$expense_result = $conn->query($expense_query);

if ($expense_result && $expense_result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Manager Status</th><th>Accountant Status</th><th>HR Status</th><th>Updated At</th></tr>";
    
    while ($row = $expense_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['manager_status']}</td>";
        echo "<td>{$row['accountant_status']}</td>";
        echo "<td>{$row['hr_status']}</td>";
        echo "<td>{$row['updated_at']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No expenses found.</p>";
}
?> 