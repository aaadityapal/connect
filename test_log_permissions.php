<?php
/**
 * Quick WhatsApp Log Permission Test
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>WhatsApp Log File Permission Test</h1>";
echo "<hr>";

$log_file = __DIR__ . '/whatsapp/whatsapp.log';

echo "<h2>Testing Log File: $log_file</h2>";

// Check if file exists
if (file_exists($log_file)) {
    echo "<p style='color: green;'>✅ File exists</p>";

    // Check if writable
    if (is_writable($log_file)) {
        echo "<p style='color: green;'>✅ File is writable</p>";
    } else {
        echo "<p style='color: red;'>❌ File is NOT writable</p>";
        echo "<p>Current permissions: " . substr(sprintf('%o', fileperms($log_file)), -4) . "</p>";
    }

    // Try to write to it
    $test_message = "[" . date('Y-m-d H:i:s') . "] TEST: Permission test write\n";
    $result = @file_put_contents($log_file, $test_message, FILE_APPEND);

    if ($result !== false) {
        echo "<p style='color: green;'>✅ Successfully wrote test message to log</p>";
        echo "<p>Bytes written: $result</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to write to log file</p>";
        $error = error_get_last();
        if ($error) {
            echo "<pre>" . print_r($error, true) . "</pre>";
        }
    }

} else {
    echo "<p style='color: orange;'>⚠️ File does not exist, attempting to create...</p>";

    // Try to create the file
    $result = @file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Log file created\n");

    if ($result !== false) {
        echo "<p style='color: green;'>✅ Successfully created log file</p>";
        echo "<p>Bytes written: $result</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create log file</p>";
        $error = error_get_last();
        if ($error) {
            echo "<pre>" . print_r($error, true) . "</pre>";
        }
    }
}

// Check directory permissions
$dir = dirname($log_file);
echo "<hr>";
echo "<h2>Directory Permissions: $dir</h2>";

if (is_dir($dir)) {
    echo "<p style='color: green;'>✅ Directory exists</p>";
    echo "<p>Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "</p>";

    if (is_writable($dir)) {
        echo "<p style='color: green;'>✅ Directory is writable</p>";
    } else {
        echo "<p style='color: red;'>❌ Directory is NOT writable</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Directory does not exist</p>";
}

// Test WhatsAppService
echo "<hr>";
echo "<h2>Testing WhatsAppService</h2>";

try {
    require_once __DIR__ . '/whatsapp/WhatsAppService.php';
    $service = new WhatsAppService();
    echo "<p style='color: green;'>✅ WhatsAppService loaded successfully</p>";

    // Try to send a test template message (won't actually send, just test the logging)
    echo "<p>Attempting to log a test message...</p>";
    $result = $service->sendTemplateMessage('919876543210', 'test_template', 'en_US', ['Test', 'Param']);

    echo "<p>Result:</p>";
    echo "<pre>" . print_r($result, true) . "</pre>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Show recent log entries
echo "<hr>";
echo "<h2>Recent Log Entries</h2>";

if (file_exists($log_file) && is_readable($log_file)) {
    $logs = file($log_file);
    $recent = array_slice($logs, -10);

    echo "<div style='background: #2c3e50; color: #ecf0f1; padding: 15px; overflow-x: auto;'>";
    echo "<pre>";
    foreach ($recent as $log) {
        echo htmlspecialchars($log);
    }
    echo "</pre>";
    echo "</div>";
} else {
    echo "<p style='color: orange;'>⚠️ Cannot read log file</p>";
}

echo "<hr>";
echo "<h2>Fix Commands (if needed)</h2>";
echo "<p>If you see permission errors above, run these commands in terminal:</p>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
echo "cd /Applications/XAMPP/xamppfiles/htdocs/connect\n";
echo "chmod 777 whatsapp/\n";
echo "chmod 777 whatsapp/whatsapp.log\n";
echo "# Or create the file if it doesn't exist:\n";
echo "touch whatsapp/whatsapp.log\n";
echo "chmod 777 whatsapp/whatsapp.log\n";
echo "</pre>";
?>