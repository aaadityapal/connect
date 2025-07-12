<?php
// Set headers to allow CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Get coordinates from query parameters
$latitude = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$longitude = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

// Check if coordinates are valid
if ($latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

// Make the request to OpenStreetMap Nominatim API from the server side
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=18&addressdetails=1";

// Set user agent as required by Nominatim usage policy
$options = [
    'http' => [
        'header' => "User-Agent: HR System Geocoder/1.0\r\n",
        'method' => 'GET'
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get address', 'address' => 'Unknown location']);
    exit;
}

$data = json_decode($response, true);

if (isset($data['display_name'])) {
    echo json_encode(['address' => $data['display_name']]);
} else {
    echo json_encode(['address' => 'Unknown location']);
}
?> 