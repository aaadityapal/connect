<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $teamType = $_POST['team_type'];
    $testDate = $_POST['test_date'] ?? date('Y-m-d');

    try {
        $pdo = getDBConnection();
        $result = sendScheduledAdminSummary($pdo, $testDate, $teamType);

        if ($result) {
            $success = "✅ {$teamType} team summary sent successfully!";
        } else {
            $error = "❌ Failed to send {$teamType} team summary";
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
    <title>Test Scheduled Notifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }

        .schedule-item .time {
            font-weight: 600;
            color: #667eea;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Test Scheduled Notifications</h1>
            <p>Send admin summaries at scheduled times</p>
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
                <h3>📅 Notification Schedule</h3>

                <h4 style="margin-top: 15px; color: #666;">🚧 Field Team:</h4>
                <div class="schedule-item">
                    <span>First Reminder</span>
                    <span class="time">08:50 AM</span>
                </div>
                <div class="schedule-item">
                    <span>Second Reminder</span>
                    <span class="time">09:15 AM</span>
                </div>
                <div class="schedule-item">
                    <span>Final Reminder</span>
                    <span class="time">10:00 AM</span>
                </div>

                <h4 style="margin-top: 15px; color: #666;">🏢 Studio Team:</h4>
                <div class="schedule-item">
                    <span>First Reminder</span>
                    <span class="time">09:15 AM</span>
                </div>
                <div class="schedule-item">
                    <span>Second Reminder</span>
                    <span class="time">10:00 AM</span>
                </div>
                <div class="schedule-item">
                    <span>Final Reminder</span>
                    <span class="time">10:30 AM</span>
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

                <button type="submit" name="send_test" class="btn">
                    Send Test Notification
                </button>
            </form>

            <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 8px; font-size: 14px;">
                <strong>💡 Note:</strong> This will send a notification to all active admins immediately,
                regardless of the scheduled time. Use this to test before setting up cron jobs.
            </div>
        </div>
    </div>
</body>

</html>