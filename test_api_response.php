<?php
// Test the actual API response for payment entry details
session_start();

// Check if user is logged in (simulate for testing)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Simulate logged in user for testing
}

echo "<h1>API Response Test</h1>";

// Make a proper API call using cURL
$paymentId = 56; // Use the payment ID from debug output
$apiUrl = "http://localhost/hr/api/get_payment_entry_details.php?id=$paymentId";

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>Raw API Response (HTTP $httpCode):</h2>";
echo "<pre style='background-color: #f5f5f5; padding: 15px; border: 1px solid #ddd; overflow-x: auto;'>";
echo htmlspecialchars($apiResponse);
echo "</pre>";

echo "<h2>Parsed Response:</h2>";
if ($httpCode == 200) {
    $responseData = json_decode($apiResponse, true);
} else {
    $responseData = null;
    echo "<p style='color: red;'>HTTP Error: $httpCode</p>";
}

if ($responseData && isset($responseData['recipients'])) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Recipient</th>";
    echo "<th>Raw Category</th>";
    echo "<th>Raw Type</th>";
    echo "<th>Display Category</th>";
    echo "<th>Display Type</th>";
    echo "<th>Name</th>";
    echo "</tr>";
    
    foreach ($responseData['recipients'] as $index => $recipient) {
        echo "<tr>";
        echo "<td>" . ($index + 1) . "</td>";
        echo "<td style='background-color: " . ($recipient['category'] == 'vendor' ? '#ffcccc' : ($recipient['category'] == 'labour' ? '#ccffcc' : '#ccccff')) . ";'>" . htmlspecialchars($recipient['category']) . "</td>";
        echo "<td>" . htmlspecialchars($recipient['type']) . "</td>";
        echo "<td style='background-color: yellow; font-weight: bold;'>" . htmlspecialchars($recipient['display_category']) . "</td>";
        echo "<td style='background-color: yellow; font-weight: bold;'>" . htmlspecialchars($recipient['display_type']) . "</td>";
        echo "<td>" . htmlspecialchars($recipient['name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Expected Frontend Display:</h3>";
    foreach ($responseData['recipients'] as $index => $recipient) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px; background-color: #f9f9f9;'>";
        echo "<strong>Recipient " . ($index + 1) . ":</strong><br>";
        echo "Category Tag: <span style='background-color: #007bff; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;'>" . strtoupper($recipient['display_category']) . "</span><br>";
        echo "Type Tag: <span style='background-color: #e91e63; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;'>" . strtoupper($recipient['display_type']) . "</span>";
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>Error: Could not parse API response or no recipients found.</p>";
    echo "<p>Response status: " . ($responseData['status'] ?? 'unknown') . "</p>";
    if (isset($responseData['message'])) {
        echo "<p>Message: " . htmlspecialchars($responseData['message']) . "</p>";
    }
}

echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo "table { margin: 10px 0; }";
echo "th, td { padding: 8px; text-align: left; }";
echo "</style>";
?>