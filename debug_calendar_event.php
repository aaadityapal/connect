<?php
// Debug file to show detailed errors for save_calendar_event.php

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection to test it
require_once 'config.php';

echo "<h1>Calendar Event Debugging</h1>";

// Check database connection
if ($pdo instanceof PDO) {
    try {
        // Try a simple query to verify connection
        $pdo->query("SELECT 1");
        echo "<p style='color:green'>✓ Database connection is working with PDO</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ Database connection is not a PDO instance</p>";
}

// Check if tables exist
$required_tables = [
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

echo "<h2>Database Tables Check</h2>";
echo "<ul>";
foreach ($required_tables as $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "<li style='color:green'>✓ Table exists: $table</li>";
        } else {
            echo "<li style='color:red'>✗ Table missing: $table</li>";
        }
    } catch (Exception $e) {
        echo "<li style='color:red'>✗ Error checking table $table: " . $e->getMessage() . "</li>";
    }
}
echo "</ul>";

// Check session status
// Session already started in config.php
echo "<h2>Session Check</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p style='color:green'>✓ Session user_id is set to: " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p style='color:red'>✗ Session user_id is not set. This will cause the save process to fail.</p>";
    echo "<p>Setting a test user_id for this session.</p>";
    $_SESSION['user_id'] = 1; // Set a test user ID
}

// Check upload directories
echo "<h2>Upload Directories Check</h2>";
$dirs_to_check = [
    'uploads/' => 'Main uploads directory',
    'uploads/calendar_events/' => 'Calendar events directory',
    'uploads/calendar_events/material_images/' => 'Material images directory',
    'uploads/calendar_events/bill_images/' => 'Bill images directory'
];

foreach ($dirs_to_check as $dir => $description) {
    if (file_exists($dir)) {
        $writable = is_writable($dir) ? "Yes" : "No";
        echo "<p>$description: <span style='color:green'>✓ Exists</span> (Writable: $writable)</p>";
    } else {
        echo "<p>$description: <span style='color:red'>✗ Does not exist</span> - Will try to create</p>";
        if (mkdir($dir, 0777, true)) {
            echo "<p style='margin-left:20px;color:green'>✓ Successfully created directory</p>";
        } else {
            echo "<p style='margin-left:20px;color:red'>✗ Failed to create directory. Check PHP permissions.</p>";
        }
    }
}

// Monitor POST requests to save_calendar_event.php
echo "<h2>POST Request Monitor</h2>";
echo "<p>When you submit the form, any potential PHP errors will be shown here:</p>";

// Display folder and file permissions
echo "<h2>File and Folder Permissions</h2>";
$files_to_check = [
    'backend/save_calendar_event.php' => 'Backend save script',
    'config/db_connect.php' => 'Database connection file',
    'uploads/' => 'Uploads directory (if exists)'
];

echo "<ul>";
foreach ($files_to_check as $path => $description) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] : fileowner($path);
        $group = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($path))['name'] : filegroup($path);
        
        echo "<li>$description ($path): Permissions: $perms, Owner: $owner, Group: $group</li>";
    } else {
        echo "<li>$description ($path): <span style='color:red'>File/folder not found</span></li>";
    }
}
echo "</ul>";

// Check PHP configuration
echo "<h2>PHP Configuration</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>max_execution_time: " . ini_get('max_execution_time') . "</li>";
echo "<li>upload_max_filesize: " . ini_get('upload_max_filesize') . "</li>";
echo "<li>post_max_size: " . ini_get('post_max_size') . "</li>";
echo "</ul>";

// Provide instructions for fixing common issues
echo "<h2>Troubleshooting Steps</h2>";
echo "<ol>";
echo "<li>If tables are missing, run the SQL schema file: <code>calendar_event_schema.sql</code></li>";
echo "<li>If directories can't be created, check that your PHP has write permissions to the server</li>";
echo "<li>Make sure your form's field names exactly match what the backend expects</li>";
echo "<li>Verify that your form has <code>enctype=\"multipart/form-data\"</code> for file uploads</li>";
echo "<li>Check that your user is logged in with a valid session ID</li>";
echo "</ol>";

// Add testing form link
echo "<h2>Testing Links</h2>";
echo "<p><a href='test_direct_save.php' target='_blank'>Open Test Form</a> - Use this to test the backend directly</p>";
echo "<p><a href='test_calendar_event_save.php' target='_blank'>Database Connection Test</a> - Check database connectivity</p>";

?>

<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 20px;
        max-width: 900px;
    }
    h1 {
        color: #2196F3;
        border-bottom: 2px solid #2196F3;
        padding-bottom: 10px;
    }
    h2 {
        color: #1976D2;
        margin-top: 30px;
        border-left: 5px solid #2196F3;
        padding-left: 10px;
    }
    ul, ol {
        margin-left: 20px;
    }
    li {
        margin-bottom: 8px;
    }
    code {
        background-color: #f5f5f5;
        padding: 2px 5px;
        border-radius: 3px;
        font-family: monospace;
    }
</style> 