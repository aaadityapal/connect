<?php
// Set header to JSON
header('Content-Type: application/json');

// Capture raw input
$input = file_get_contents('php://input');

// Log the request details
error_log("AJAX Request Debug: " . print_r($_REQUEST, true));
error_log("Raw POST data: " . $input);

// Try to decode JSON
$decoded = json_decode($input, true);
$jsonError = json_last_error();
$jsonErrorMsg = json_last_error_msg();

// Prepare the response
$response = [
    'status' => 'success',
    'message' => 'Debug info collected',
    'data' => [
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? 'Not provided',
        'raw_input' => $input,
        'json_decoded' => $decoded,
        'json_error' => $jsonError,
        'json_error_message' => $jsonErrorMsg,
        'post_data' => $_POST,
        'get_data' => $_GET,
        'headers' => getallheaders()
    ]
];

// Send response
echo json_encode($response, JSON_PRETTY_PRINT);
?> 