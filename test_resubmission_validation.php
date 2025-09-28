<?php
// Test script to verify resubmission validation works correctly
session_start();
include_once('includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

$user_id = $_SESSION['user_id'];

echo "<h2>Resubmission Validation Test</h2>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Create a test rejected expense
echo "<h3>Test 1: Creating a test rejected expense</h3>";

$test_stmt = $conn->prepare("
    INSERT INTO travel_expenses (
        user_id, purpose, mode_of_transport, from_location, 
        to_location, travel_date, distance, amount, status, notes, 
        manager_status, accountant_status, hr_status, 
        resubmission_count, max_resubmissions, created_at
    ) VALUES (?, 'Test Purpose', 'Car', 'Test From', 'Test To', ?, 10, 100, 'rejected', 'Test notes', 
             'rejected', 'pending', 'pending', 0, 3, NOW())
");

$travel_date = date('Y-m-d', strtotime('-5 days')); // 5 days ago
$test_stmt->bind_param("is", $user_id, $travel_date);

if ($test_stmt->execute()) {
    $test_expense_id = $conn->insert_id;
    echo "<p>✓ Created test expense with ID: {$test_expense_id}</p>";
} else {
    echo "<p>✗ Failed to create test expense: " . $test_stmt->error . "</p>";
    exit;
}
$test_stmt->close();

// Test 2: Try to resubmit the expense (should work)
echo "<h3>Test 2: First resubmission attempt (should succeed)</h3>";

// Simulate POST request
$_POST['expense_id'] = $test_expense_id;
$_SERVER['REQUEST_METHOD'] = 'POST';

// Capture output from resubmit script
ob_start();
include 'resubmit_travel_expense_fixed.php';
$output = ob_get_clean();

echo "<p>Response from resubmit script:</p>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Parse the JSON response
$response = json_decode($output, true);
if ($response && $response['success']) {
    $new_expense_id = $response['new_expense_id'];
    echo "<p>✓ First resubmission successful. New expense ID: {$new_expense_id}</p>";
    
    // Test 3: Try to resubmit again while the new expense is still pending (should fail)
    echo "<h3>Test 3: Second resubmission attempt while first is pending (should fail)</h3>";
    
    // Reset POST data
    $_POST['expense_id'] = $test_expense_id;
    
    ob_start();
    include 'resubmit_travel_expense_fixed.php';
    $second_output = ob_get_clean();
    
    echo "<p>Response from second resubmit attempt:</p>";
    echo "<pre>" . htmlspecialchars($second_output) . "</pre>";
    
    $second_response = json_decode($second_output, true);
    if ($second_response && !$second_response['success']) {
        echo "<p>✓ Second resubmission correctly blocked: " . $second_response['message'] . "</p>";
    } else {
        echo "<p>✗ Second resubmission should have been blocked but wasn't!</p>";
    }
    
    // Test 4: Test the canResubmit function
    echo "<h3>Test 4: Testing canResubmit() function</h3>";
    
    // Fetch the original expense data
    $check_stmt = $conn->prepare("SELECT * FROM travel_expenses WHERE id = ?");
    $check_stmt->bind_param("i", $test_expense_id);
    $check_stmt->execute();
    $expense_data = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    // Include the view functions
    include_once('view_travel_expenses.php');
    
    $can_resubmit = canResubmit($expense_data);
    echo "<p>canResubmit() function result: " . ($can_resubmit ? 'TRUE (should be FALSE)' : 'FALSE (correct)') . "</p>";
    
    if (!$can_resubmit) {
        echo "<p>✓ canResubmit() function correctly prevents resubmission while pending</p>";
    } else {
        echo "<p>✗ canResubmit() function should return FALSE but returned TRUE</p>";
    }
    
} else {
    echo "<p>✗ First resubmission failed: " . ($response['message'] ?? 'Unknown error') . "</p>";
}

// Cleanup: Remove test expenses
echo "<h3>Cleanup</h3>";
$cleanup_stmt = $conn->prepare("DELETE FROM travel_expenses WHERE user_id = ? AND purpose = 'Test Purpose'");
$cleanup_stmt->bind_param("i", $user_id);
if ($cleanup_stmt->execute()) {
    echo "<p>✓ Test expenses cleaned up</p>";
} else {
    echo "<p>✗ Failed to clean up test expenses</p>";
}
$cleanup_stmt->close();

echo "<h3>Test Complete</h3>";
?>