<?php
/**
 * Server-side proxy for OpenStreetMap Nominatim API
 * This avoids CORS issues when making requests from the browser
 */

// Set headers to allow CORS from our domain and specify JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // In production, replace * with your domain

// Check if latitude and longitude parameters are provided
if (!isset($_GET['lat']) || !isset($_GET['lon'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing latitude or longitude parameters'
    ]);
    exit;
}

// Get and validate coordinates
$latitude = floatval($_GET['lat']);
$longitude = floatval($_GET['lon']);

// Validate coordinate ranges
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid coordinates'
    ]);
    exit;
}

// Prepare the Nominatim API URL
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=18";

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'HR Attendance System'); // Required by Nominatim usage policy
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout to 10 seconds

// Execute cURL request
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error connecting to geocoding service: ' . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

// Get HTTP status code
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check if request was successful
if ($httpCode !== 200) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geocoding service returned error code: ' . $httpCode
    ]);
    exit;
}

// Parse the JSON response
$data = json_decode($response, true);

// Check if parsing was successful and display_name exists
if ($data && isset($data['display_name'])) {
    echo json_encode([
        'status' => 'success',
        'address' => $data['display_name'],
        'lat' => $latitude,
        'lon' => $longitude
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not determine address from coordinates',
        'lat' => $latitude,
        'lon' => $longitude
    ]);
} 