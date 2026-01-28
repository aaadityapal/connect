<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Punch-In WhatsApp Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 10px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #34495e; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        .success { background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 3px; }
        .error { background: #f8d7da; color: #721c24; padding: 5px 10px; border-radius: 3px; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

require_once 'config/db_connect.php';

echo "<h1>🔍 Punch-In WhatsApp Debug Tool</h1>";

// Get today's date
$today = date('Y-m-d');
echo "<p><strong>Today's Date:</strong> $today</p>";
echo "<hr>";

try {
    // Get recent punch-ins from today
    $query = "
        SELECT 
            a.id,
            a.user_id,
            u.username,
            u.phone,
            a.punch_in,
            a.date,
            a.created_at
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.date = ?
        AND a.punch_in IS NOT NULL
        ORDER BY a.punch_in DESC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$today]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>📊 Today's Punch-Ins (" . count($records) . " records)</h2>";

    if (count($records) > 0) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Attendance ID</th>";
        echo "<th>User ID</th>";
        echo "<th>Username</th>";
        echo "<th>Phone Number</th>";
        echo "<th>Punch In Time</th>";
        echo "<th>WhatsApp Status</th>";
        echo "</tr>";

        // Read WhatsApp log once
        $log_file = __DIR__ . '/whatsapp/whatsapp.log';
        $whatsapp_log = '';
        if (file_exists($log_file)) {
            $whatsapp_log = file_get_contents($log_file);
        }

        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>" . $record['id'] . "</td>";
            echo "<td>" . $record['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($record['username']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($record['phone']) . "</strong></td>";
            echo "<td>" . $record['punch_in'] . "</td>";

            // Check if this phone number appears in today's WhatsApp log
            $phone = $record['phone'];
            $punch_time = $record['punch_in'];

            // Check if phone number appears in log with today's date
            $found = false;
            $status_message = '';

            if (!empty($whatsapp_log)) {
                // Look for this phone number in the log
                if (strpos($whatsapp_log, $phone) !== false) {
                    // Check if it's from today
                    $log_lines = explode("\n", $whatsapp_log);
                    foreach ($log_lines as $line) {
                        if (strpos($line, $phone) !== false && strpos($line, $today) !== false) {
                            $found = true;
                            // Check if it was accepted
                            if (strpos($line, 'message_status') !== false && strpos($line, 'accepted') !== false) {
                                $status_message = '✅ Sent & Accepted';
                            } else if (strpos($line, 'Sending template') !== false) {
                                $status_message = '📤 Sending...';
                            }
                            break;
                        }
                    }
                }
            }

            if ($found) {
                echo "<td><span class='success'>$status_message</span></td>";
            } else {
                echo "<td><span class='error'>❌ Not Found in Log</span></td>";
            }

            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p style='color: orange; font-size: 18px;'>⚠️ No punch-ins found for today ($today)</p>";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>❌ Database Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

// Show WhatsApp log
echo "<hr>";
echo "<h2>📝 WhatsApp Log (Last 30 lines)</h2>";

$log_file = __DIR__ . '/whatsapp/whatsapp.log';
if (file_exists($log_file)) {
    echo "<pre>";
    $logs = file($log_file);
    $recent = array_slice($logs, -30);
    foreach ($recent as $log) {
        // Highlight today's date
        if (strpos($log, $today) !== false) {
            echo "<span style='background: #f39c12; color: #000;'>" . htmlspecialchars($log) . "</span>";
        } else {
            echo htmlspecialchars($log);
        }
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ WhatsApp log file not found at: $log_file</p>";
}

echo "<hr>";
echo "<h2>📋 Summary</h2>";
echo "<ul>";
echo "<li><strong>✅ Green</strong> = WhatsApp message was sent and accepted by API</li>";
echo "<li><strong>❌ Red</strong> = No WhatsApp notification found in log for this punch-in</li>";
echo "<li><strong>Highlighted lines</strong> in log = Today's entries</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='check_recent_punchin.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔄 Refresh Page</a></p>";

echo "</div></body></html>";
?>