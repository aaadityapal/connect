<?php
// Calendar Event Debug Test Page
// This file checks the setup and configuration of calendar event saving

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Helper function
function check_item($name, $condition, $success_message, $failure_message, $solution = '') {
    echo "<tr>";
    echo "<td><strong>$name</strong></td>";
    echo "<td>" . ($condition ? "<span style='color:green'>✓</span>" : "<span style='color:red'>✗</span>") . "</td>";
    echo "<td>" . ($condition ? $success_message : $failure_message);
    if (!$condition && !empty($solution)) {
        echo "<br><strong>Solution:</strong> $solution";
    }
    echo "</td>";
    echo "</tr>";
    return $condition;
}

// Check if backend directory exists
$backend_dir_exists = is_dir('backend');

// Check if save_calendar_event.php exists
$save_script_exists = file_exists('backend/save_calendar_event.php');

// Check if calendar-events-save.js exists
$save_js_exists = file_exists('js/supervisor/calendar-events-save.js');

// Check if calendar-events-modal.js exists
$modal_js_exists = file_exists('js/supervisor/calendar-events-modal.js');

// Check file permissions
$backend_permissions = $save_script_exists ? substr(sprintf('%o', fileperms('backend/save_calendar_event.php')), -4) : 'N/A';
$backend_writeable = $save_script_exists ? is_writable('backend/save_calendar_event.php') : false;

// Check for processWorkMediaFile function in the save_calendar_event.php file
$process_work_media_file_exists = false;
if ($save_script_exists) {
    $save_php_content = file_get_contents('backend/save_calendar_event.php');
    
    // The function exists, but let's confirm it's not commented out
    $function_pos = strpos($save_php_content, 'function processWorkMediaFile');
    if ($function_pos !== false) {
        // Check if the line isn't commented out (no // before it)
        $line_start = strrpos(substr($save_php_content, 0, $function_pos), "\n") + 1;
        $line = substr($save_php_content, $line_start, $function_pos - $line_start);
        $process_work_media_file_exists = strpos(trim($line), '//') !== 0;
    }
}

// Check if saveCalendarEvent function is properly exposed in calendar-events-save.js
$save_calendar_event_exposed = false;
if ($save_js_exists) {
    $save_js_content = file_get_contents('js/supervisor/calendar-events-save.js');
    $save_calendar_event_exposed = strpos($save_js_content, 'window.saveCalendarEvent = saveCalendarEvent') !== false;
}

// Check if the calendar-events-modal.js file properly uses the saveCalendarEvent function
$modal_uses_save_function = false;
if ($modal_js_exists) {
    $modal_js_content = file_get_contents('js/supervisor/calendar-events-modal.js');
    $modal_uses_save_function = strpos($modal_js_content, 'saveCalendarEvent(') !== false;
}

// Check upload directory
$upload_dir = 'uploads/calendar_events';
$upload_dir_exists = is_dir($upload_dir);
$upload_dir_writeable = $upload_dir_exists ? is_writable($upload_dir) : false;

// Check database connection
$db_connected = false;
$db_error = '';
try {
    require_once 'config/db_connect.php';
    $db_connected = isset($pdo) && $pdo instanceof PDO;
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

// Check for required tables
$tables_exist = false;
$missing_tables = [];
if ($db_connected) {
    $required_tables = ['sv_calendar_events', 'sv_work_progress', 'sv_work_progress_media'];
    $existing_tables = [];
    
    try {
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $existing_tables[] = $row[0];
        }
        
        foreach ($required_tables as $table) {
            if (!in_array($table, $existing_tables)) {
                $missing_tables[] = $table;
            }
        }
        
        $tables_exist = count($missing_tables) === 0;
    } catch (Exception $e) {
        $db_error = $e->getMessage();
    }
}

// API test
$api_test_result = null;
$api_success = false;
$api_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_api'])) {
    // Create simple test data
    $test_data = [
        'event_title' => 'Test Event from Debug',
        'event_date' => date('Y-m-d'),
        'vendor_count' => 1,
        'vendor_type_1' => 'Tester',
        'vendor_name_1' => 'Debug Test Vendor',
        'contact_number_1' => '0000000000',
        'work_progress_count' => 1,
        'work_category_1' => 'testing',
        'work_type_1' => 'debug',
        'work_done_1' => 'yes',
        'work_remarks_1' => 'Test from debug script',
        '_testing_mode' => '1'
    ];
    
    // Initialize cURL
    $ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . '/hr/backend/save_calendar_event.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $test_data);
    // Add additional headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);
    
    // Process response
    $api_test_result = [
        'http_code' => $http_code,
        'response' => $response,
        'curl_error' => $curl_error
    ];
    
    // Try to parse response as JSON
    if (!empty($response)) {
        try {
            $json_response = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $api_success = isset($json_response['status']) && $json_response['status'] === 'success';
                $api_message = isset($json_response['message']) ? $json_response['message'] : '';
            } else {
                $api_message = 'Invalid JSON response: ' . json_last_error_msg();
            }
        } catch (Exception $e) {
            $api_message = 'Error parsing response: ' . $e->getMessage();
        }
    } else {
        $api_message = 'Empty response from server';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Event Debug</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
        }
        h1, h2 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .test-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
        }
        .test-button:hover {
            background-color: #45a049;
        }
        pre {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .error {
            color: #e53e3e;
        }
        .success {
            color: #38a169;
        }
    </style>
</head>
<body>
    <h1>Calendar Event Debug Test</h1>
    <p>This page runs tests to help identify issues with the calendar event saving system.</p>
    
    <h2>File System Checks</h2>
    <table>
        <tr>
            <th>Item</th>
            <th>Status</th>
            <th>Details</th>
        </tr>
        <?php
        check_item('Backend Directory', $backend_dir_exists, 'Backend directory exists', 'Backend directory does not exist', 'Create the directory backend/ in the root of your project');
        check_item('Save Calendar Event PHP Script', $save_script_exists, 'Script exists', 'Script does not exist', 'Create or restore the file backend/save_calendar_event.php');
        check_item('Save Calendar Event JS Script', $save_js_exists, 'Script exists', 'Script does not exist', 'Create or restore the file js/supervisor/calendar-events-save.js');
        check_item('Calendar Events Modal JS Script', $modal_js_exists, 'Script exists', 'Script does not exist', 'Create or restore the file js/supervisor/calendar-events-modal.js');
        check_item('Backend Script Permissions', $backend_writeable, "Current permissions: $backend_permissions", "Current permissions: $backend_permissions (not writable)", 'Set proper permissions on the file, typically 644 or 664');
        check_item('Upload Directory', $upload_dir_exists, 'Directory exists', 'Directory does not exist', "Create the directory $upload_dir with proper write permissions");
        check_item('Upload Directory Writable', $upload_dir_writeable, 'Directory is writable', 'Directory is not writable', "Set write permissions on the $upload_dir directory");
        ?>
    </table>
    
    <h2>Database Checks</h2>
    <table>
        <tr>
            <th>Item</th>
            <th>Status</th>
            <th>Details</th>
        </tr>
        <?php
        check_item('Database Connection', $db_connected, 'Connected to database', "Failed to connect to database: $db_error", 'Check your database credentials in config/db_connect.php');
        check_item('Required Tables', $tables_exist, 'All required tables exist', 'Missing tables: ' . implode(', ', $missing_tables), 'Create the missing tables or check database schema');
        ?>
    </table>
    
    <h2>Code Checks</h2>
    <table>
        <tr>
            <th>Item</th>
            <th>Status</th>
            <th>Details</th>
        </tr>
        <?php
        check_item('processWorkMediaFile Function', $process_work_media_file_exists, 'Function exists in save_calendar_event.php', 'Function does not exist in save_calendar_event.php', 'Add the processWorkMediaFile function to save_calendar_event.php');
        check_item('saveCalendarEvent Export', $save_calendar_event_exposed, 'Function is properly exposed globally', 'Function is not properly exposed globally', 'Make sure window.saveCalendarEvent = saveCalendarEvent is in calendar-events-save.js');
        check_item('Modal Uses Save Function', $modal_uses_save_function, 'Modal script calls saveCalendarEvent', 'Modal script does not call saveCalendarEvent', 'Update calendar-events-modal.js to use saveCalendarEvent');
        ?>
    </table>
    
    <h2>API Test</h2>
    <form method="post">
        <button type="submit" name="test_api" class="test-button">Test API Endpoint</button>
    </form>
    
    <?php if ($api_test_result): ?>
    <div style="margin-top: 20px;">
        <h3>API Test Result: <?php echo $api_success ? '<span class="success">Success</span>' : '<span class="error">Failed</span>'; ?></h3>
        <p><?php echo $api_message; ?></p>
        <pre><?php 
        echo "HTTP Code: {$api_test_result['http_code']}\n";
        if (!empty($api_test_result['curl_error'])) {
            echo "cURL Error: {$api_test_result['curl_error']}\n";
        }
        echo "\nResponse:\n";
        echo htmlspecialchars($api_test_result['response']); 
        ?></pre>
    </div>
    <?php endif; ?>
    
    <h2>Manual Test</h2>
    <p>Use the test page to manually test the calendar event saving functionality:</p>
    <a href="test_calendar_save.php" class="test-button" style="display: inline-block; text-decoration: none;">Open Test Page</a>
</body>
</html> 