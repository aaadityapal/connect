<?php
/**
 * Test Admin Daily Summary
 * 
 * This script allows you to manually test the admin daily summary notification
 * Access via browser: http://localhost/connect/whatsapp/test_admin_summary.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Admin Daily Summary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }

        .form-group {
            margin: 20px 0;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        input[type="date"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
            font-size: 14px;
        }

        button {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        button:hover {
            background: #45a049;
        }

        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 4px;
        }

        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .stats-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .stats-table th,
        .stats-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .stats-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .team-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .team-section h3 {
            margin-top: 0;
            color: #333;
        }

        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔔 Test Admin Daily Summary Notification</h1>

        <form method="POST">
            <div class="form-group">
                <label for="test_date">Select Date:</label>
                <input type="date" id="test_date" name="test_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <button type="submit" name="send_summary">Send Admin Summary</button>
            <button type="submit" name="preview_data">Preview Data (No Send)</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $testDate = $_POST['test_date'] ?? date('Y-m-d');

            try {
                $pdo = getDBConnection();

                // Preview Data
                if (isset($_POST['preview_data'])) {
                    echo '<div class="result info">';
                    echo '<h2>📊 Preview for ' . htmlspecialchars($testDate) . '</h2>';

                    // Get admins
                    $adminStmt = $pdo->prepare("SELECT id, admin_name, phone, is_active FROM admin_notifications ORDER BY admin_name");
                    $adminStmt->execute();
                    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

                    echo '<h3>Admins who will receive notifications:</h3>';
                    echo '<table class="stats-table">';
                    echo '<tr><th>Name</th><th>Phone</th><th>Status</th></tr>';
                    foreach ($admins as $admin) {
                        $status = $admin['is_active'] ? '✅ Will receive' : '❌ Inactive';
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($admin['admin_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($admin['phone']) . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';

                    // Studio Team Stats
                    $studioStats = getPunchInStatsByTeam($pdo, $testDate, 'Studio');
                    echo '<div class="team-section">';
                    echo '<h3>🏢 Studio Team Summary</h3>';
                    echo '<p><strong>Total Employees:</strong> ' . $studioStats['total'] . '</p>';
                    echo '<p><strong>On-time Punch-ins:</strong></p>';
                    echo '<pre>' . htmlspecialchars($studioStats['ontime_list']) . '</pre>';
                    echo '<p><strong>Late Punch-ins:</strong></p>';
                    echo '<pre>' . htmlspecialchars($studioStats['late_list']) . '</pre>';
                    echo '</div>';

                    // Field Team Stats
                    $fieldStats = getPunchInStatsByTeam($pdo, $testDate, 'Field');
                    echo '<div class="team-section">';
                    echo '<h3>🚧 Field Team Summary</h3>';
                    echo '<p><strong>Total Employees:</strong> ' . $fieldStats['total'] . '</p>';
                    echo '<p><strong>On-time Punch-ins:</strong></p>';
                    echo '<pre>' . htmlspecialchars($fieldStats['ontime_list']) . '</pre>';
                    echo '<p><strong>Late Punch-ins:</strong></p>';
                    echo '<pre>' . htmlspecialchars($fieldStats['late_list']) . '</pre>';
                    echo '</div>';

                    echo '</div>';
                }

                // Send Summary
                if (isset($_POST['send_summary'])) {
                    $result = sendAdminDailySummary($pdo, $testDate);

                    if ($result) {
                        echo '<div class="result success">';
                        echo '<h2>✅ Success!</h2>';
                        echo '<p>Admin daily summary notifications have been sent successfully for <strong>' . htmlspecialchars($testDate) . '</strong></p>';
                        echo '<p>Check the WhatsApp log file for details: <code>whatsapp/whatsapp.log</code></p>';
                        echo '</div>';
                    } else {
                        echo '<div class="result error">';
                        echo '<h2>❌ Error</h2>';
                        echo '<p>Failed to send admin daily summary. Check the error logs for details.</p>';
                        echo '</div>';
                    }
                }

            } catch (Exception $e) {
                echo '<div class="result error">';
                echo '<h2>❌ Exception</h2>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        ?>

        <div
            style="margin-top: 40px; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
            <h3>📝 Instructions:</h3>
            <ol>
                <li><strong>Preview Data:</strong> Click "Preview Data" to see what data will be sent without actually
                    sending notifications</li>
                <li><strong>Send Summary:</strong> Click "Send Admin Summary" to actually send WhatsApp notifications to
                    all admins</li>
                <li><strong>Department Configuration:</strong> Make sure to adjust the department filters in
                    <code>getPunchInStatsByTeam()</code> function to match your actual department names
                </li>
                <li><strong>Cron Job Setup:</strong> Once tested, set up the cron job using
                    <code>cron_admin_daily_summary.php</code>
                </li>
            </ol>
        </div>
    </div>
</body>

</html>