<?php
// Simple test script to debug the API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing API directly...\n";

// Include the API file and see what happens
$_GET['id'] = 27;

// Capture output
ob_start();
include 'api/get_ui_payment_entry_details.php';
$output = ob_get_clean();

echo "API Output:\n";
echo $output;

if (empty($output)) {
    echo "\nNo output captured - possible fatal error occurred.\n";
}
?>