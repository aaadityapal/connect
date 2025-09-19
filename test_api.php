<?php
// Simple test to verify our API endpoint
echo "Testing API endpoint...\n";

// Use cURL to test the API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:8000/api/get_recent_vendors.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response:\n" . $response . "\n";
?>