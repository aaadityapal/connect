<?php
// Test script to verify resubmission count inheritance
session_start();
include_once('includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

$user_id = $_SESSION['user_id'];

echo "<h2>Resubmission Count Inheritance Test</h2>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Create an original rejected expense
echo "<h3>Test 1: Creating an original rejected expense</h3>";

$original_stmt = $conn->prepare("
    INSERT INTO travel_expenses (
        user_id, purpose, mode_of_transport, from_location, 
        to_location, travel_date, distance, amount, status, notes, 
        manager_status, accountant_status, hr_status, 
        resubmission_count, max_resubmissions, created_at
    ) VALUES (?, 'Test Inheritance', 'Car', 'Test From', 'Test To', ?, 10, 100, 'rejected', 'Original expense', 
             'rejected', 'pending', 'pending', 0, 3, NOW())
");

$travel_date = date('Y-m-d', strtotime('-3 days')); // 3 days ago
$original_stmt->bind_param("is", $user_id, $travel_date);

if ($original_stmt->execute()) {
    $original_expense_id = $conn->insert_id;
    echo "<p>✓ Created original expense with ID: {$original_expense_id}</p>";
} else {
    echo "<p>✗ Failed to create original expense: " . $original_stmt->error . "</p>";
    exit;
}
$original_stmt->close();

// Test 2: First resubmission
echo "<h3>Test 2: First resubmission (should have count = 1)</h3>";

// Simulate resubmission
$_POST['expense_id'] = $original_expense_id;
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
include 'resubmit_travel_expense_fixed.php';
$output1 = ob_get_clean();

echo "<p>Response from first resubmission:</p>";
echo "<pre>" . htmlspecialchars($output1) . "</pre>";

$response1 = json_decode($output1, true);
if ($response1 && $response1['success']) {
    $first_resubmit_id = $response1['new_expense_id'];
    $first_count = $response1['resubmission_count'];
    $first_remaining = $response1['remaining_resubmissions'];
    
    echo "<p>✓ First resubmission successful:</p>";
    echo "<ul>";
    echo "<li>New expense ID: {$first_resubmit_id}</li>";
    echo "<li>Resubmission count: {$first_count} (should be 1)</li>";
    echo "<li>Remaining resubmissions: {$first_remaining} (should be 2)</li>";
    echo "</ul>";
    
    // Verify database values
    $verify_stmt = $conn->prepare("SELECT resubmission_count, max_resubmissions, original_expense_id FROM travel_expenses WHERE id = ?");
    $verify_stmt->bind_param("i", $first_resubmit_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result()->fetch_assoc();
    $verify_stmt->close();
    
    echo "<p>Database verification:</p>";
    echo "<ul>";
    echo "<li>DB resubmission_count: {$verify_result['resubmission_count']}</li>";
    echo "<li>DB max_resubmissions: {$verify_result['max_resubmissions']}</li>";
    echo "<li>DB original_expense_id: {$verify_result['original_expense_id']}</li>";
    echo "</ul>";
    
    // Test 3: Reject the first resubmission and resubmit again
    echo "<h3>Test 3: Rejecting first resubmission and creating second resubmission</h3>";
    
    $reject_stmt = $conn->prepare("UPDATE travel_expenses SET status = 'rejected' WHERE id = ?");
    $reject_stmt->bind_param("i", $first_resubmit_id);
    if ($reject_stmt->execute()) {
        echo "<p>✓ First resubmission marked as rejected</p>";
    } else {
        echo "<p>✗ Failed to reject first resubmission</p>";
    }
    $reject_stmt->close();
    
    // Second resubmission
    $_POST['expense_id'] = $first_resubmit_id;
    
    ob_start();
    include 'resubmit_travel_expense_fixed.php';
    $output2 = ob_get_clean();
    
    echo "<p>Response from second resubmission:</p>";
    echo "<pre>" . htmlspecialchars($output2) . "</pre>";
    
    $response2 = json_decode($output2, true);
    if ($response2 && $response2['success']) {
        $second_resubmit_id = $response2['new_expense_id'];
        $second_count = $response2['resubmission_count'];
        $second_remaining = $response2['remaining_resubmissions'];
        
        echo "<p>✓ Second resubmission successful:</p>";
        echo "<ul>";
        echo "<li>New expense ID: {$second_resubmit_id}</li>";
        echo "<li>Resubmission count: {$second_count} (should be 2)</li>";
        echo "<li>Remaining resubmissions: {$second_remaining} (should be 1)</li>";
        echo "</ul>";
        
        // Verify second resubmission database values
        $verify2_stmt = $conn->prepare("SELECT resubmission_count, max_resubmissions, original_expense_id FROM travel_expenses WHERE id = ?");
        $verify2_stmt->bind_param("i", $second_resubmit_id);
        $verify2_stmt->execute();
        $verify2_result = $verify2_stmt->get_result()->fetch_assoc();
        $verify2_stmt->close();
        
        echo "<p>Second resubmission database verification:</p>";
        echo "<ul>";
        echo "<li>DB resubmission_count: {$verify2_result['resubmission_count']}</li>";
        echo "<li>DB max_resubmissions: {$verify2_result['max_resubmissions']}</li>";
        echo "<li>DB original_expense_id: {$verify2_result['original_expense_id']} (should be {$original_expense_id})</li>";
        echo "</ul>";
        
        // Test if the counts are correct
        if ($second_count == 2 && $second_remaining == 1 && $verify2_result['original_expense_id'] == $original_expense_id) {
            echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> Resubmission count inheritance is working correctly!</p>";
        } else {
            echo "<p style='color: red;'>❌ <strong>FAILURE:</strong> Resubmission count inheritance is not working correctly!</p>";
        }
        
    } else {
        echo "<p>✗ Second resubmission failed: " . ($response2['message'] ?? 'Unknown error') . "</p>";
    }
    
} else {
    echo "<p>✗ First resubmission failed: " . ($response1['message'] ?? 'Unknown error') . "</p>";
}

// Cleanup
echo "<h3>Cleanup</h3>";
$cleanup_stmt = $conn->prepare("DELETE FROM travel_expenses WHERE user_id = ? AND purpose = 'Test Inheritance'");
$cleanup_stmt->bind_param("i", $user_id);
if ($cleanup_stmt->execute()) {
    echo "<p>✓ Test expenses cleaned up</p>";
} else {
    echo "<p>✗ Failed to clean up test expenses</p>";
}
$cleanup_stmt->close();

echo "<h3>Test Complete</h3>";
?>