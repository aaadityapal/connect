<?php
// Set content type
header('Content-Type: application/json');

// Return JSON data for testing
echo json_encode([
    'success' => true,
    'message' => 'PHP is executing correctly in the includes directory',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_info' => [
        'version' => phpversion(),
        'sapi' => php_sapi_name()
    ],
    'file_path' => __FILE__
]);
exit; 