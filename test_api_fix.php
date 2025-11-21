<?php
/**
 * Test the fixed get_payment_entries.php API
 */

session_start();
$_SESSION['user_id'] = 1; // Fake session for testing

echo "Testing get_payment_entries.php API...\n\n";

// Test 1: No filters
echo "TEST 1: No filters\n";
$_GET = ['limit' => 10, 'offset' => 0];
ob_start();
include 'get_payment_entries.php';
$response1 = ob_get_clean();
$data1 = json_decode($response1, true);
echo "Success: " . ($data1['success'] ? 'YES' : 'NO') . "\n";
echo "Response: " . substr($response1, 0, 200) . "...\n\n";

// Test 2: With vendor category filter
echo "TEST 2: With vendor category filter\n";
$_GET = ['limit' => 10, 'offset' => 0, 'vendorCategory' => 'labour'];
ob_start();
include 'get_payment_entries.php';
$response2 = ob_get_clean();
$data2 = json_decode($response2, true);
echo "Success: " . ($data2['success'] ? 'YES' : 'NO') . "\n";
if (!$data2['success']) {
    echo "Error: " . $data2['message'] . "\n";
}
echo "Response: " . substr($response2, 0, 200) . "...\n\n";

// Test 3: With multiple filters
echo "TEST 3: With multiple filters\n";
$_GET = ['limit' => 10, 'offset' => 0, 'vendorCategory' => 'labour', 'status' => 'approved'];
ob_start();
include 'get_payment_entries.php';
$response3 = ob_get_clean();
$data3 = json_decode($response3, true);
echo "Success: " . ($data3['success'] ? 'YES' : 'NO') . "\n";
if (!$data3['success']) {
    echo "Error: " . $data3['message'] . "\n";
}
echo "Response: " . substr($response3, 0, 200) . "...\n\n";

echo "\nAll tests completed!";
?>
