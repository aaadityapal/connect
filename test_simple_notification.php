<?php
/**
 * Simple Direct Test for WhatsApp Punch Notification
 * 
 * Usage: http://localhost/connect/test_simple_notification.php?user_id=YOUR_USER_ID
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Simple WhatsApp Notification Test</h1>";
echo "<hr>";

// Include required files
require_once 'config/db_connect.php';
require_once __DIR__ . '/whatsapp/send_punch_notification.php';

// Get user ID from URL parameter
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;

if (!$user_id) {
    echo "<p style='color: red;'>❌ Please provide a user_id parameter in the URL</p>";
    echo "<p>Example: test_simple_notification.php?user_id=1</p>";

    // Show available users
    echo "<h3>Available Users:</h3>";
    try {
        $stmt = $pdo->query("SELECT id, username, phone FROM users WHERE phone IS NOT NULL AND phone != '' LIMIT 20");
        echo "<ul>";
        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<li>";
            echo "<a href='?user_id=" . $user['id'] . "'>";
            echo "ID: " . $user['id'] . " - " . htmlspecialchars($user['username']) . " (" . htmlspecialchars($user['phone']) . ")";
            echo "</a>";
            echo "</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    exit;
}

echo "<h2>Testing for User ID: $user_id</h2>";

// Get user details
try {
    $stmt = $pdo->prepare("SELECT id, username, phone, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<p style='color: red;'>❌ User not found with ID: $user_id</p>";
        exit;
    }

    echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #3498db;'>";
    echo "<strong>User Details:</strong><br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Username: " . htmlspecialchars($user['username']) . "<br>";
    echo "Phone: " . htmlspecialchars($user['phone']) . "<br>";
    echo "Role: " . htmlspecialchars($user['role']) . "<br>";
    echo "</div>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
    exit;
}

// Check if PDO connection is available
if (!isset($pdo)) {
    echo "<p style='color: red;'>❌ PDO connection not available</p>";
    exit;
}

echo "<p style='color: blue;'>✓ PDO connection available</p>";

// Check if function exists
if (!function_exists('sendPunchNotification')) {
    echo "<p style='color: red;'>❌ sendPunchNotification function not found</p>";
    exit;
}

echo "<p style='color: blue;'>✓ sendPunchNotification function available</p>";

// Send the notification
echo "<hr>";
echo "<h3>📤 Sending WhatsApp Notification...</h3>";

try {
    $result = sendPunchNotification($user_id, $pdo);

    if ($result) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745;'>";
        echo "<strong>✅ SUCCESS!</strong><br>";
        echo "WhatsApp notification sent successfully to: " . htmlspecialchars($user['phone']) . "<br>";
        echo "Check the user's WhatsApp for the message.";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545;'>";
        echo "<strong>❌ FAILED!</strong><br>";
        echo "The notification function returned false.<br>";
        echo "Check the logs for more details.";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545;'>";
    echo "<strong>❌ EXCEPTION!</strong><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

// Check WhatsApp log file
echo "<hr>";
echo "<h3>📝 WhatsApp Service Logs:</h3>";

$log_file = __DIR__ . '/whatsapp/whatsapp.log';
if (file_exists($log_file)) {
    echo "<div style='background: #2c3e50; color: #ecf0f1; padding: 15px; overflow-x: auto;'>";
    echo "<pre>";
    $logs = file($log_file);
    $recent_logs = array_slice($logs, -30); // Last 30 lines
    foreach ($recent_logs as $log) {
        echo htmlspecialchars($log);
    }
    echo "</pre>";
    echo "</div>";
} else {
    echo "<p style='color: orange;'>⚠️ Log file not found at: $log_file</p>";
}

// Check PHP error log
echo "<hr>";
echo "<h3>📝 PHP Error Logs (Recent):</h3>";

$php_log = ini_get('error_log');
if (empty($php_log)) {
    $php_log = '/Applications/XAMPP/xamppfiles/logs/php_error_log';
}

if (file_exists($php_log)) {
    echo "<p>Log file: $php_log</p>";
    echo "<div style='background: #2c3e50; color: #ecf0f1; padding: 15px; overflow-x: auto;'>";
    echo "<pre>";
    $logs = file($php_log);
    $recent_logs = array_slice($logs, -20); // Last 20 lines
    foreach ($recent_logs as $log) {
        if (stripos($log, 'whatsapp') !== false || stripos($log, 'notification') !== false) {
            echo "<span style='color: #f39c12;'>" . htmlspecialchars($log) . "</span>";
        } else {
            echo htmlspecialchars($log);
        }
    }
    echo "</pre>";
    echo "</div>";
} else {
    echo "<p style='color: orange;'>⚠️ PHP error log not found at: $php_log</p>";
}

echo "<hr>";
echo "<p><a href='test_simple_notification.php'>← Back to user selection</a></p>";
?>