<?php
// Fix Calendar Save Issues
// This script helps diagnose and fix issues with calendar event saving

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Calendar Save Fix Tool</h1>";

// 1. Check session
session_start();
echo "<h2>1. Session Check</h2>";
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red'>⚠️ No user session found. Setting a temporary user ID.</p>";
    $_SESSION['user_id'] = 1;
} else {
    echo "<p style='color:green'>✓ User session found. User ID: {$_SESSION['user_id']}</p>";
}

// 2. Check if backend file exists
echo "<h2>2. Backend File Check</h2>";
$backend_path = "backend/save_calendar_event.php";
if (file_exists($backend_path)) {
    echo "<p style='color:green'>✓ Backend file exists at: {$backend_path}</p>";
    echo "<p>File size: " . filesize($backend_path) . " bytes</p>";
    echo "<p>Last modified: " . date("Y-m-d H:i:s", filemtime($backend_path)) . "</p>";
} else {
    echo "<p style='color:red'>⚠️ Backend file NOT found at: {$backend_path}</p>";
    
    // Check alternative locations
    $alt_paths = [
        "../backend/save_calendar_event.php",
        "./backend/save_calendar_event.php",
        "/backend/save_calendar_event.php",
    ];
    
    foreach ($alt_paths as $path) {
        if (file_exists($path)) {
            echo "<p style='color:orange'>Found at alternative path: {$path}</p>";
        }
    }
}

// 3. Create directory structure check
echo "<h2>3. Directory Structure</h2>";
echo "<pre>";
function listDir($dir, $indent = '') {
    if (!is_dir($dir)) {
        echo "$indent $dir - NOT A DIRECTORY\n";
        return;
    }
    
    echo "$indent $dir/\n";
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            listDir($path, $indent . '  ');
        } else {
            echo "$indent   $file\n";
        }
    }
}

listDir('backend');
echo "</pre>";

// 4. Fix JS paths
echo "<h2>4. JavaScript Path Fix</h2>";
$js_save_path = "js/supervisor/calendar-events-save.js";
$fixed = false;

if (file_exists($js_save_path)) {
    echo "<p style='color:green'>✓ Found calendar-events-save.js</p>";
    
    $content = file_get_contents($js_save_path);
    
    // Look for incorrect fetch URLs
    $patterns = [
        '/fetch\s*\(\s*[\'"]\.\.\/backend\/save_calendar_event\.php[\'"]/i',
        '/fetch\s*\(\s*[\'"]\/backend\/save_calendar_event\.php[\'"]/i'
    ];
    
    $replacement = 'fetch(\'backend/save_calendar_event.php\'';
    
    $new_content = preg_replace($patterns, $replacement, $content, -1, $count);
    
    if ($count > 0) {
        // Save the updated content
        file_put_contents($js_save_path, $new_content);
        echo "<p style='color:green'>✓ Fixed {$count} incorrect path(s) in calendar-events-save.js</p>";
        $fixed = true;
    } else {
        echo "<p>No incorrect paths found in calendar-events-save.js</p>";
    }
} else {
    echo "<p style='color:red'>⚠️ Could not find calendar-events-save.js at {$js_save_path}</p>";
}

// 5. Direct test form
echo "<h2>5. Direct Testing</h2>";
?>

<form action="backend/save_calendar_event.php" method="post">
    <div style="margin-bottom: 10px;">
        <label for="event_title">Event Title:</label>
        <input type="text" id="event_title" name="event_title" value="Test Event" required>
    </div>
    
    <div style="margin-bottom: 10px;">
        <label for="event_date">Event Date:</label>
        <input type="date" id="event_date" name="event_date" value="<?php echo date('Y-m-d'); ?>" required>
    </div>
    
    <!-- Required vendor data -->
    <input type="hidden" name="vendor_count" value="1">
    <input type="hidden" name="vendor_type_1" value="Test Type">
    <input type="hidden" name="vendor_name_1" value="Test Vendor">
    
    <button type="submit" style="padding: 5px 10px; background: #4CAF50; color: white; border: none; cursor: pointer;">
        Test Direct Submission
    </button>
</form>

<h2>6. Next Steps</h2>
<?php if ($fixed): ?>
    <p>The JavaScript file has been updated with correct paths. Try the following steps:</p>
    <ol>
        <li>Clear your browser cache completely (or use incognito/private mode)</li>
        <li>Return to the calendar page and try adding an event again</li>
        <li>If issues persist, try the direct test form above to see if the backend works correctly</li>
        <li>Check your browser's console (F12) for any errors</li>
    </ol>
<?php else: ?>
    <p>Try the following steps:</p>
    <ol>
        <li>Make sure the backend/save_calendar_event.php file exists and is accessible</li>
        <li>Check file permissions - ensure the web server can read the file</li>
        <li>Try the direct test form above to see if the backend works correctly</li>
        <li>Clear your browser cache completely (or use incognito/private mode)</li>
    </ol>
<?php endif; ?>

<p>
    <a href="site_supervisor_dashboard.php" style="color: #3498db;">Return to Dashboard</a> | 
    <a href="debug_calendar_save.php" style="color: #3498db;">Go to Debug Tool</a>
</p> 