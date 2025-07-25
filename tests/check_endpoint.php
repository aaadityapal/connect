<?php
/**
 * Simple test to check if the get_working_hours.php endpoint is working
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Testing get_working_hours.php endpoint</h1>";

// Test direct file inclusion
echo "<h2>Test 1: Direct file inclusion</h2>";
try {
    echo "Attempting to include the file directly...<br>";
    include_once '../ajax_handlers/get_working_hours.php';
    echo "File included successfully (though you should see JSON output above).<br>";
} catch (Exception $e) {
    echo "Error including file: " . $e->getMessage() . "<br>";
}

// Test direct file access
echo "<h2>Test 2: Direct file access</h2>";
echo "Attempting to access the file directly...<br>";
$file_url = '../ajax_handlers/get_working_hours.php';
echo "File URL: " . $file_url . "<br>";
if (file_exists($file_url)) {
    echo "File exists on server.<br>";
} else {
    echo "File does not exist at this path.<br>";
}

// Test file permissions
echo "<h2>Test 3: File permissions</h2>";
if (file_exists($file_url)) {
    echo "File permissions: " . substr(sprintf('%o', fileperms($file_url)), -4) . "<br>";
    echo "File owner: " . fileowner($file_url) . "<br>";
    echo "File group: " . filegroup($file_url) . "<br>";
    echo "File is readable: " . (is_readable($file_url) ? 'Yes' : 'No') . "<br>";
    echo "File is writable: " . (is_writable($file_url) ? 'Yes' : 'No') . "<br>";
    echo "File is executable: " . (is_executable($file_url) ? 'Yes' : 'No') . "<br>";
}

// Test using file_get_contents
echo "<h2>Test 4: Using file_get_contents</h2>";
$absolute_url = 'http://localhost/hr/ajax_handlers/get_working_hours.php';
echo "Absolute URL: " . $absolute_url . "<br>";
echo "Attempting to get contents...<br>";
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'user_id' => 1,
            'date' => date('Y-m-d')
        ]),
        'timeout' => 5
    ]
]);

try {
    $result = @file_get_contents($absolute_url, false, $context);
    if ($result === false) {
        echo "Error getting contents. Error: " . error_get_last()['message'] . "<br>";
    } else {
        echo "Contents retrieved successfully:<br>";
        echo "<pre>" . htmlspecialchars($result) . "</pre>";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}

// Test using cURL with a very short timeout
echo "<h2>Test 5: Using cURL with short timeout</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $absolute_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'user_id' => 1,
    'date' => date('Y-m-d')
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Short 5 second timeout
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

$response = curl_exec($ch);
$curl_info = curl_getinfo($ch);
$curl_error = curl_error($ch);
curl_close($ch);

echo "cURL execution time: " . $curl_info['total_time'] . " seconds<br>";
echo "cURL HTTP code: " . $curl_info['http_code'] . "<br>";
echo "cURL error: " . ($curl_error ?: 'None') . "<br>";

if ($response) {
    echo "Response:<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} else {
    echo "No response received.<br>";
}

// Server info
echo "<h2>Server Information</h2>";
echo "PHP version: " . phpversion() . "<br>";
echo "Server software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "Current working directory: " . getcwd() . "<br>";

// Check for infinite loops or long-running queries
echo "<h2>Check for potential issues in get_working_hours.php</h2>";
echo "Opening file to check for potential issues...<br>";
$file_contents = @file_get_contents($file_url);
if ($file_contents) {
    // Check for potential infinite loops
    $has_while_true = strpos($file_contents, 'while(true)') !== false || 
                      strpos($file_contents, 'while (true)') !== false ||
                      strpos($file_contents, 'for(;;)') !== false;
    
    // Check for sleep or usleep functions
    $has_sleep = strpos($file_contents, 'sleep(') !== false || 
                 strpos($file_contents, 'usleep(') !== false;
    
    // Check for complex database queries
    $has_complex_query = strpos($file_contents, 'JOIN') !== false && 
                         strpos($file_contents, 'GROUP BY') !== false;
    
    echo "Contains potential infinite loops: " . ($has_while_true ? 'Yes' : 'No') . "<br>";
    echo "Contains sleep functions: " . ($has_sleep ? 'Yes' : 'No') . "<br>";
    echo "Contains complex database queries: " . ($has_complex_query ? 'Yes' : 'No') . "<br>";
} else {
    echo "Could not read file contents.<br>";
}
?> 