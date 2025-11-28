<?php
/**
 * Test API: fetch_complete_payment_entry_data_comprehensive.php
 * 
 * Usage:
 * 1. Make sure you're logged in
 * 2. Visit: http://localhost/connect/test_payment_entry_api.php?payment_entry_id=1
 * 
 * This will show you the API response for debugging
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('You must be logged in to test this API. Please login first.');
}

$payment_entry_id = intval($_GET['payment_entry_id'] ?? 0);

if (!$payment_entry_id) {
    die('Please provide payment_entry_id as URL parameter: ?payment_entry_id=123');
}

// Call the API
$response = file_get_contents(
    'http://localhost/connect/fetch_complete_payment_entry_data_comprehensive.php?payment_entry_id=' . $payment_entry_id,
    false,
    stream_context_create([
        'http' => [
            'header' => 'Cookie: PHPSESSID=' . session_id() . "\r\n"
        ]
    ])
);

// Display the response
header('Content-Type: application/json');
echo $response;
?>
