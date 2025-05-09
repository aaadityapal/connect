<?php
// Calendar Event Debug Tool
// This tool helps debug issues with calendar event saving functionality

// Include database configuration
require_once 'config.php';

// Start session
session_start();

// Force login if needed (for testing)
if (!isset($_SESSION['user_id']) && isset($_GET['force_login'])) {
    $_SESSION['user_id'] = 1; // Set a test user ID
    $_SESSION['username'] = 'Test User';
}

// Function to output debug info
function outputDebugInfo($title, $data, $success = true) {
    echo '<div style="margin-bottom: 15px; padding: 10px; border-radius: 5px; background-color: ' . 
         ($success ? '#d4edda' : '#f8d7da') . '; color: ' . 
         ($success ? '#155724' : '#721c24') . ';">';
    echo '<h3 style="margin-top: 0;">' . $title . '</h3>';
    
    if (is_array($data) || is_object($data)) {
        echo '<pre>' . print_r($data, true) . '</pre>';
    } else {
        echo '<p>' . $data . '</p>';
    }
    
    echo '</div>';
}

// Check for POST request from test form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log the received data
        error_log('Received form submission: ' . json_encode($_POST));
        
        // Save test event
        require_once 'backend/save_calendar_event.php';
        
        // If we reach here without error, it means save_calendar_event.php didn't output or exit
        outputDebugInfo('Save Process Completed', 'The save_calendar_event.php script was included and executed without errors.');
    } catch (Exception $e) {
        outputDebugInfo('Error', 'Exception caught: ' . $e->getMessage(), false);
    }
    exit;
}

// Main debug page HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Event Debug Tool</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .test-btn { margin-top: 10px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; max-height: 300px; overflow: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Calendar Event Debug Tool</h1>
        
        <div class="section">
            <h2>Session Information</h2>
            <?php
            if (isset($_SESSION['user_id'])) {
                outputDebugInfo('Session Status', [
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'] ?? 'N/A'
                ]);
            } else {
                outputDebugInfo('Session Status', 'No active user session found. <a href="?force_login=1">Click here</a> to force a test login.', false);
            }
            ?>
        </div>
        
        <div class="section">
            <h2>Database Connection Test</h2>
            <?php
            try {
                // Test the connection
                $stmt = $pdo->query("SELECT 1");
                outputDebugInfo('Database Connection', 'Successfully connected to database using PDO');
                
                // Check if the required tables exist
                $tables = [
                    'sv_calendar_events',
                    'sv_event_vendors',
                    'sv_vendor_materials',
                    'sv_material_images',
                    'sv_bill_images',
                    'sv_vendor_labours',
                    'sv_labour_wages',
                    'sv_company_labours',
                    'sv_company_wages'
                ];
                
                $existing_tables = [];
                $missing_tables = [];
                
                foreach ($tables as $table) {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        $existing_tables[] = $table;
                    } else {
                        $missing_tables[] = $table;
                    }
                }
                
                if (empty($missing_tables)) {
                    outputDebugInfo('Database Tables', 'All required tables exist: ' . implode(', ', $existing_tables));
                } else {
                    outputDebugInfo('Database Tables', 'Missing tables: ' . implode(', ', $missing_tables) . 
                                     '<br><a href="run_schema.php" target="_blank">Run schema installation</a> to create the missing tables.', false);
                }
                
            } catch (PDOException $e) {
                outputDebugInfo('Database Connection Error', $e->getMessage(), false);
            }
            ?>
        </div>
        
        <div class="section">
            <h2>Backend Script Check</h2>
            <?php
            $backend_file = 'backend/save_calendar_event.php';
            if (file_exists($backend_file)) {
                $file_size = filesize($backend_file);
                $file_modified = date("Y-m-d H:i:s", filemtime($backend_file));
                
                outputDebugInfo('Backend Script', [
                    'File' => $backend_file,
                    'Size' => $file_size . ' bytes',
                    'Last Modified' => $file_modified
                ]);
                
                // Check for known issues in the backend script
                $script_content = file_get_contents($backend_file);
                $issues = [];
                
                if (strpos($script_content, '$conn->begin_transaction()') !== false) {
                    $issues[] = 'Using mysqli "begin_transaction" method instead of PDO "beginTransaction"';
                }
                
                if (strpos($script_content, '$conn->insert_id') !== false) {
                    $issues[] = 'Using mysqli "insert_id" property instead of PDO "lastInsertId()" method';
                }
                
                if (strpos($script_content, 'bind_param') !== false) {
                    $issues[] = 'Using mysqli "bind_param" method instead of PDO parameter binding';
                }
                
                if (strpos($script_content, 'require_once "../config.php"') !== false && 
                    strpos($script_content, '$conn = $pdo') === false) {
                    $issues[] = 'May not be using the PDO connection from config.php properly';
                }
                
                if (!empty($issues)) {
                    outputDebugInfo('Potential Issues Found', $issues, false);
                } else {
                    outputDebugInfo('Backend Script Analysis', 'No common issues detected');
                }
                
            } else {
                outputDebugInfo('Backend Script', 'File not found: ' . $backend_file, false);
            }
            ?>
        </div>
        
        <div class="section">
            <h2>JavaScript Files Check</h2>
            <?php
            $js_files = [
                'js/supervisor/calendar-events-save.js',
                'js/supervisor/calendar-events-modal.js'
            ];
            
            $js_status = [];
            
            foreach ($js_files as $js_file) {
                if (file_exists($js_file)) {
                    $file_size = filesize($js_file);
                    $file_modified = date("Y-m-d H:i:s", filemtime($js_file));
                    
                    $js_status[$js_file] = [
                        'Status' => 'Found',
                        'Size' => $file_size . ' bytes',
                        'Last Modified' => $file_modified
                    ];
                } else {
                    $js_status[$js_file] = [
                        'Status' => 'Not Found',
                        'Error' => 'File does not exist'
                    ];
                }
            }
            
            // Check for integration between the files
            $integration_issues = [];
            
            if (isset($js_status['js/supervisor/calendar-events-save.js']['Status']) && 
                $js_status['js/supervisor/calendar-events-save.js']['Status'] === 'Found') {
                
                $save_js = file_get_contents('js/supervisor/calendar-events-save.js');
                
                if (strpos($save_js, 'window.saveCalendarEvent') === false) {
                    $integration_issues[] = 'calendar-events-save.js does not expose the saveCalendarEvent function globally';
                }
            }
            
            if (isset($js_status['js/supervisor/calendar-events-modal.js']['Status']) && 
                $js_status['js/supervisor/calendar-events-modal.js']['Status'] === 'Found') {
                
                $modal_js = file_get_contents('js/supervisor/calendar-events-modal.js');
                
                if (strpos($modal_js, 'window.saveCalendarEvent') === false) {
                    $integration_issues[] = 'calendar-events-modal.js does not use the saveCalendarEvent function';
                }
            }
            
            outputDebugInfo('JavaScript Files', $js_status);
            
            if (!empty($integration_issues)) {
                outputDebugInfo('JavaScript Integration Issues', $integration_issues, false);
            }
            
            // Check dashboard file for correct script loading order
            $dashboard_file = 'site_supervisor_dashboard.php';
            if (file_exists($dashboard_file)) {
                $dashboard_content = file_get_contents($dashboard_file);
                
                if (strpos($dashboard_content, 'calendar-events-save.js') === false) {
                    outputDebugInfo('Script Loading', 'The calendar-events-save.js file is not included in ' . $dashboard_file, false);
                } else {
                    // Check if save.js is loaded before modal.js
                    $save_pos = strpos($dashboard_content, 'calendar-events-save.js');
                    $modal_pos = strpos($dashboard_content, 'calendar-events-modal.js');
                    
                    if ($save_pos > $modal_pos) {
                        outputDebugInfo('Script Loading Order', 'calendar-events-save.js is loaded AFTER calendar-events-modal.js which may cause issues', false);
                    } else {
                        outputDebugInfo('Script Loading Order', 'Scripts are loaded in the correct order');
                    }
                }
            } else {
                outputDebugInfo('Dashboard File', 'File not found: ' . $dashboard_file, false);
            }
            ?>
        </div>
        
        <div class="section">
            <h2>Test Event Submission</h2>
            <form id="test-event-form" method="post" action="">
                <div class="form-group">
                    <label for="event-title">Event Title</label>
                    <input type="text" class="form-control" id="event-title" name="event_title" value="Test Event" required>
                </div>
                <div class="form-group">
                    <label for="event-date">Event Date</label>
                    <input type="date" class="form-control" id="event-date" name="event_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <input type="hidden" name="vendor_count" value="1">
                <input type="hidden" name="vendor_type_1" value="material">
                <input type="hidden" name="vendor_name_1" value="Test Vendor">
                <input type="hidden" name="contact_number_1" value="1234567890">
                
                <input type="hidden" name="material_count_1" value="1">
                <input type="hidden" name="remarks_material_1_1" value="Test Material">
                <input type="hidden" name="amount_material_1_1" value="100">
                
                <input type="hidden" name="labour_count_1" value="1">
                <input type="hidden" name="labour_name_labour_1_1" value="Test Labour">
                <input type="hidden" name="labour_number_labour_1_1" value="9876543210">
                <input type="hidden" name="morning_attendance_labour_1_1" value="present">
                <input type="hidden" name="evening_attendance_labour_1_1" value="present">
                
                <button type="submit" class="btn btn-primary test-btn">Test Event Submission</button>
            </form>
        </div>
        
        <div class="section">
            <h2>Manual Integration Test</h2>
            <p>This test will check if the integration between calendar-events-modal.js and calendar-events-save.js is working correctly.</p>
            <button id="manual-test-btn" class="btn btn-info test-btn">Run Manual Integration Test</button>
            <div id="manual-test-result" class="mt-3"></div>
        </div>
    </div>
    
    <!-- Include required JavaScript files for testing -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="js/supervisor/calendar-events-save.js"></script>
    <script>
        $(document).ready(function() {
            // Manual integration test
            $('#manual-test-btn').click(function() {
                const result = $('#manual-test-result');
                result.html('<div class="alert alert-info">Testing integration...</div>');
                
                // Check if saveCalendarEvent is accessible
                if (typeof window.saveCalendarEvent === 'function') {
                    // Create a test event
                    const testEvent = {
                        title: 'Integration Test Event',
                        date: '<?php echo date('Y-m-d'); ?>',
                        vendors: [{
                            type: 'test',
                            name: 'Integration Test Vendor',
                            contact: '123456789'
                        }]
                    };
                    
                    // Try to call the function but intercept the actual AJAX call
                    const originalFetch = window.fetch;
                    window.fetch = function(url, options) {
                        // Restore original fetch
                        window.fetch = originalFetch;
                        
                        // Show success
                        result.html('<div class="alert alert-success">' +
                            '<strong>Integration Success!</strong> ' +
                            '<p>saveCalendarEvent function was called correctly and would send the following:</p>' +
                            '<pre>' + JSON.stringify(testEvent, null, 2) + '</pre>' +
                        '</div>');
                        
                        // Return a promise that never resolves to prevent actual API call
                        return new Promise(() => {});
                    };
                    
                    // Call the function
                    try {
                        window.saveCalendarEvent(testEvent, 
                            function() { /* success */ }, 
                            function() { /* error */ }
                        );
                    } catch (e) {
                        // Restore original fetch if error
                        window.fetch = originalFetch;
                        
                        result.html('<div class="alert alert-danger">' +
                            '<strong>Integration Error!</strong> ' +
                            '<p>Error when calling saveCalendarEvent: ' + e.message + '</p>' +
                        '</div>');
                    }
                } else {
                    result.html('<div class="alert alert-danger">' +
                        '<strong>Integration Error!</strong> ' +
                        '<p>The saveCalendarEvent function is not available globally. ' +
                        'Make sure calendar-events-save.js is loaded and properly exposes the function.</p>' +
                    '</div>');
                }
            });
        });
    </script>
</body>
</html> 