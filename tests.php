<?php
// Enable error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to decode coordinates to address without API
function getAddressFromCoordinates($latitude, $longitude) {
    // Format with more precision and better readability
    $formatted_lat = number_format($latitude, 6);
    $formatted_lng = number_format($longitude, 6);
    
    // Add cardinal directions
    $lat_dir = ($latitude >= 0) ? "N" : "S";
    $lng_dir = ($longitude >= 0) ? "E" : "W";
    
    // Remove negative signs for display
    $formatted_lat = abs($formatted_lat);
    $formatted_lng = abs($formatted_lng);
    
    // Create a more descriptive address string
    $address = "Geo Location: {$formatted_lat}° {$lat_dir}, {$formatted_lng}° {$lng_dir}";
    
    return $address;
}

// Test with sample coordinates
$test_coordinates = [
    ['lat' => 28.636926, 'lng' => 77.302640, 'expected' => 'Delhi area'],
    ['lat' => 19.076090, 'lng' => 72.877426, 'expected' => 'Mumbai area'],
    ['lat' => -33.868820, 'lng' => 151.209290, 'expected' => 'Sydney area'],
    ['lat' => 40.730610, 'lng' => -73.935242, 'expected' => 'New York area']
];

// Run tests
echo "<h2>Address Decoding Test Results</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Coordinates</th><th>Generated Address</th><th>Expected Area</th></tr>";

foreach ($test_coordinates as $test) {
    $address = getAddressFromCoordinates($test['lat'], $test['lng']);
    
    echo "<tr>";
    echo "<td>Lat: {$test['lat']}, Lng: {$test['lng']}</td>";
    echo "<td>$address</td>";
    echo "<td>{$test['expected']}</td>";
    echo "</tr>";
}

echo "</table>";
?>