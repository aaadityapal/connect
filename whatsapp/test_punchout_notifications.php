<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $teamType = $_POST['team_type'];
    $testDate = $_POST['test_date'] ?? date('Y-m-d');

    try {
        $pdo = getDBConnection();
        $result = sendScheduledPunchOutSummary($pdo, $testDate, $teamType);

        if ($result) {
            $success = "✅ {$teamType} team punch-out summary sent successfully!";
        } else {
            $error = "❌ Failed to send {$teamType} team punch-out summary (check logs - might be no punch-outs)";
        }
    } catch (Exception $e) {
        $error = "❌ Exception: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Punch-Out Notifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .schedule-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .schedule-info h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .schedule-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #11998e;
        }

        .schedule-item .time {
            font-weight: 600;
            color: #11998e;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        select,
        input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        ul {
            margin-left: 20px;
            margin-top: 10px;
            color: #555;
        }

        li {
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🌙 Test Punch-Out Notifications</h1>
            <p>Send admin punch-out summaries at scheduled times</p>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="schedule-info">
                <h3>📅 Notification Schedule (Both Teams)</h3>

                <div class="schedule-item">
                    <span>First Summary</span>
                    <span class="time">06:20 PM</span>
                </div>
                <div class="schedule-item">
                    <span>Second Summary</span>
                    <span class="time">07:15 PM</span>
                </div>
                <div class="schedule-item">
                    <span>Final Summary</span>
                    <span class="time">09:00 PM</span>
                </div>

                <div style="margin-top: 15px;">
                    <strong>Message Content:</strong>
                    <ul>
                        <li>Contains list of employees who punched out today.</li>
                        <li>Includes punch-out time and work report.</li>
                        <li>Format: <em>Name - Time<br>Report: Content</em></li>
                    </ul>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="team_type">Select Team:</label>
                    <select id="team_type" name="team_type" required>
                        <option value="Field">🚧 Field Team</option>
                        <option value="Studio">🏢 Studio Team</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="test_date">Date:</label>
                    <input type="date" id="test_date" name="test_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <button type="submit" name="preview_data" class="btn"
                        style="background: linear-gradient(135deg, #FF9800 0%, #F44336 100%);">
                        preview Data (No Send)
                    </button>
                    <button type="submit" name="send_test" class="btn">
                        Send Punch-Out Summary
                    </button>
                </div>
            </form>

            <?php if (isset($_POST['preview_data'])): ?>
                <?php
                $teamType = $_POST['team_type'];
                $testDate = $_POST['test_date'] ?? date('Y-m-d');
                try {
                    $pdo = getDBConnection();
                    $stats = getPunchOutStatsByTeam($pdo, $testDate, $teamType);
                    ?>
                    <div class="schedule-info" style="margin-top: 30px; border-left: 4px solid #FF9800;">
                        <h3>📊 Preview for <?php echo htmlspecialchars($teamType); ?> Team</h3>
                        <p><strong>Date:</strong> <?php echo htmlspecialchars($testDate); ?></p>
                        <p><strong>Total Punch-outs:</strong> <?php echo $stats['total']; ?></p>

                        <div
                            style="margin-top: 15px; background: #f1f1f1; padding: 15px; border-radius: 8px; white-space: pre-wrap; font-family: monospace;">
                            <?php echo htmlspecialchars($stats['list_formatted'] ?: 'No punch-outs recorded yet.'); ?>
                        </div>
                    </div>
                <?php
                } catch (Exception $e) {
                    echo '<div class="alert error">Error fetching preview: ' . $e->getMessage() . '</div>';
                }
                ?>
            <?php endif; ?>

            <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 8px; font-size: 14px;">
                <strong>💡 Note:</strong> Unlike punch-in summaries, this message contains actual work reports. If no
                one has punched out for the selected date, no message will be sent (check logs).
            </div>
        </div>
    </div>
</body>

</html>