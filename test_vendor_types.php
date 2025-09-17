<?php
// Simple test file to verify vendor types API
header('Content-Type: text/plain');

echo "Testing vendor types API...\n\n";

// Use file_get_contents to test the API endpoint
$response = file_get_contents('http://localhost/hr/api/get_vendor_types.php');

if ($response === false) {
    echo "Error: Could not connect to API endpoint\n";
} else {
    echo "API Response:\n";
    echo $response . "\n\n";
    
    // Try to decode JSON
    $data = json_decode($response, true);
    if ($data === null) {
        echo "Error: Invalid JSON response\n";
    } else {
        echo "Parsed JSON:\n";
        print_r($data);
    }
}
?>