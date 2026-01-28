<?php
/**
 * Test Script for WhatsApp Punch In Notification
 * 
 * This script tests the WhatsApp notification functionality
 * to identify any issues with sending punch in messages.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp Punch Notification Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
            background: #ecf0f1;
            padding: 10px;
            border-left: 4px solid #3498db;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .success {
            color: #27ae60;
            background: #d5f4e6;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #27ae60;
            margin: 10px 0;
        }
        .error {
            color: #c0392b;
            background: #fadbd8;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #c0392b;
            margin: 10px 0;
        }
        .warning {
            color: #d68910;
            background: #fcf3cf;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #d68910;
            margin: 10px 0;
        }
        .info {
            color: #2980b9;
            background: #d6eaf8;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #2980b9;
            margin: 10px 0;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin: 5px 5px 5px 0;
        }
        .badge-success { background: #27ae60; color: white; }
        .badge-error { background: #c0392b; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .badge-info { background: #3498db; color: white; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #34495e;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 WhatsApp Punch Notification Test Suite</h1>
        <p><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . " (IST)</p>
";

// Include required files
require_once 'config/db_connect.php';
require_once __DIR__ . '/whatsapp/send_punch_notification.php';

echo "<h2>📋 Step 1: File Inclusion Check</h2>";
echo "<div class='test-section'>";

if (file_exists('config/db_connect.php')) {
    echo "<div class='success'>✅ db_connect.php found and included</div>";
} else {
    echo "<div class='error'>❌ db_connect.php not found</div>";
}

if (file_exists(__DIR__ . '/whatsapp/send_punch_notification.php')) {
    echo "<div class='success'>✅ send_punch_notification.php found and included</div>";
} else {
    echo "<div class='error'>❌ send_punch_notification.php not found</div>";
}

if (file_exists(__DIR__ . '/whatsapp/WhatsAppService.php')) {
    echo "<div class='success'>✅ WhatsAppService.php found</div>";
} else {
    echo "<div class='error'>❌ WhatsAppService.php not found</div>";
}

echo "</div>";

// Check database connections
echo "<h2>🔌 Step 2: Database Connection Check</h2>";
echo "<div class='test-section'>";

// Check mysqli connection
if (isset($conn) && $conn instanceof mysqli) {
    if ($conn->connect_error) {
        echo "<div class='error'>❌ MySQLi Connection Error: " . $conn->connect_error . "</div>";
    } else {
        echo "<div class='success'>✅ MySQLi connection established</div>";
        echo "<div class='info'>Host: " . $conn->host_info . "</div>";
    }
} else {
    echo "<div class='error'>❌ MySQLi connection not available</div>";
}

// Check PDO connection
if (isset($pdo) && $pdo instanceof PDO) {
    echo "<div class='success'>✅ PDO connection established</div>";
    try {
        $stmt = $pdo->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<div class='info'>Database: " . $result['db_name'] . "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ PDO query error: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>❌ PDO connection not available</div>";
}

echo "</div>";

// Check if function exists
echo "<h2>🔧 Step 3: Function Availability Check</h2>";
echo "<div class='test-section'>";

if (function_exists('sendPunchNotification')) {
    echo "<div class='success'>✅ sendPunchNotification() function is available</div>";
} else {
    echo "<div class='error'>❌ sendPunchNotification() function not found</div>";
}

if (class_exists('WhatsAppService')) {
    echo "<div class='success'>✅ WhatsAppService class is available</div>";
} else {
    echo "<div class='error'>❌ WhatsAppService class not found</div>";
}

echo "</div>";

// Get users with phone numbers
echo "<h2>👥 Step 4: User Data Check</h2>";
echo "<div class='test-section'>";

try {
    $stmt = $pdo->query("SELECT id, username, phone, role FROM users WHERE phone IS NOT NULL AND phone != '' LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        echo "<div class='success'>✅ Found " . count($users) . " users with phone numbers</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Phone</th><th>Role</th><th>Action</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['phone']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td><a href='?test_user=" . $user['id'] . "' style='color: #3498db;'>Test Notification</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ No users found with phone numbers</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error fetching users: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Check WhatsApp Service configuration
echo "<h2>⚙️ Step 5: WhatsApp Service Configuration</h2>";
echo "<div class='test-section'>";

try {
    require_once __DIR__ . '/whatsapp/WhatsAppService.php';
    $waService = new WhatsAppService();
    echo "<div class='success'>✅ WhatsAppService instantiated successfully</div>";

    // Check if the service has required properties/methods
    if (method_exists($waService, 'sendTemplateMessage')) {
        echo "<div class='success'>✅ sendTemplateMessage() method exists</div>";
    } else {
        echo "<div class='error'>❌ sendTemplateMessage() method not found</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ WhatsAppService error: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";

// Test notification if user ID is provided
if (isset($_GET['test_user']) && !empty($_GET['test_user'])) {
    $test_user_id = (int) $_GET['test_user'];

    echo "<h2>🧪 Step 6: Testing Notification for User ID: $test_user_id</h2>";
    echo "<div class='test-section'>";

    try {
        // Get user details
        $stmt = $pdo->prepare("SELECT id, username, phone FROM users WHERE id = ?");
        $stmt->execute([$test_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo "<div class='error'>❌ User not found</div>";
        } else {
            echo "<div class='info'>";
            echo "<strong>Testing for:</strong><br>";
            echo "User ID: " . $user['id'] . "<br>";
            echo "Username: " . htmlspecialchars($user['username']) . "<br>";
            echo "Phone: " . htmlspecialchars($user['phone']) . "<br>";
            echo "</div>";

            // Call the notification function
            echo "<div class='info'>📤 Attempting to send WhatsApp notification...</div>";

            $result = sendPunchNotification($test_user_id, $pdo);

            if ($result) {
                echo "<div class='success'>✅ Notification sent successfully!</div>";
                echo "<div class='info'>Check the user's WhatsApp: " . htmlspecialchars($user['phone']) . "</div>";
            } else {
                echo "<div class='error'>❌ Notification failed to send</div>";
                echo "<div class='warning'>Check error logs for details</div>";
            }
        }

    } catch (Exception $e) {
        echo "<div class='error'>❌ Exception occurred: " . $e->getMessage() . "</div>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }

    echo "</div>";
}

// Check recent attendance records
echo "<h2>📊 Step 7: Recent Attendance Records</h2>";
echo "<div class='test-section'>";

try {
    $stmt = $pdo->query("
        SELECT a.id, a.user_id, u.username, a.date, a.punch_in, a.punch_out 
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.date DESC, a.punch_in DESC
        LIMIT 10
    ");
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($attendance) > 0) {
        echo "<div class='success'>✅ Found " . count($attendance) . " recent attendance records</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>User</th><th>Date</th><th>Punch In</th><th>Punch Out</th></tr>";
        foreach ($attendance as $record) {
            echo "<tr>";
            echo "<td>" . $record['id'] . "</td>";
            echo "<td>" . htmlspecialchars($record['username']) . "</td>";
            echo "<td>" . $record['date'] . "</td>";
            echo "<td>" . ($record['punch_in'] ?? 'N/A') . "</td>";
            echo "<td>" . ($record['punch_out'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ No attendance records found</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error fetching attendance: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Check error logs
echo "<h2>📝 Step 8: Recent Error Logs</h2>";
echo "<div class='test-section'>";

$log_file = ini_get('error_log');
if (empty($log_file)) {
    $log_file = '/Applications/XAMPP/xamppfiles/logs/php_error_log';
}

echo "<div class='info'>Log file location: " . $log_file . "</div>";

if (file_exists($log_file)) {
    $logs = file($log_file);
    $recent_logs = array_slice($logs, -20); // Last 20 lines

    echo "<div class='success'>✅ Error log file found</div>";
    echo "<pre>";
    foreach ($recent_logs as $log) {
        if (stripos($log, 'whatsapp') !== false || stripos($log, 'notification') !== false) {
            echo "<span style='color: #f39c12;'>" . htmlspecialchars($log) . "</span>";
        } else {
            echo htmlspecialchars($log);
        }
    }
    echo "</pre>";
} else {
    echo "<div class='warning'>⚠️ Error log file not found at: $log_file</div>";
}

echo "</div>";

// Manual test form
echo "<h2>🎯 Step 9: Manual Test Form</h2>";
echo "<div class='test-section'>";
echo "<form method='GET' action=''>";
echo "<div class='form-group'>";
echo "<label>Select User to Test:</label>";
echo "<select name='test_user' required>";
echo "<option value=''>-- Select User --</option>";

try {
    $stmt = $pdo->query("SELECT id, username, phone FROM users WHERE phone IS NOT NULL AND phone != '' ORDER BY username");
    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<option value='" . $user['id'] . "'>" .
            htmlspecialchars($user['username']) . " (" . htmlspecialchars($user['phone']) . ")</option>";
    }
} catch (Exception $e) {
    echo "<option value=''>Error loading users</option>";
}

echo "</select>";
echo "</div>";
echo "<button type='submit'>🚀 Send Test Notification</button>";
echo "</form>";
echo "</div>";

// Recommendations
echo "<h2>💡 Troubleshooting Recommendations</h2>";
echo "<div class='test-section'>";
echo "<ol>";
echo "<li><strong>Check WhatsApp API Credentials:</strong> Verify that your WhatsApp Business API credentials are correct in WhatsAppService.php</li>";
echo "<li><strong>Verify Template:</strong> Ensure the template 'employee_punchin_attendance_update' is approved in your WhatsApp Business account</li>";
echo "<li><strong>Phone Number Format:</strong> Make sure phone numbers are in international format (e.g., +919876543210)</li>";
echo "<li><strong>API Endpoint:</strong> Verify the WhatsApp API endpoint is accessible and responding</li>";
echo "<li><strong>Check Logs:</strong> Review the error logs above for any specific error messages</li>";
echo "<li><strong>Network:</strong> Ensure your server can make outbound HTTPS requests</li>";
echo "</ol>";
echo "</div>";

echo "
    </div>
</body>
</html>
";
?>