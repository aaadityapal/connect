<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $teamType = $_POST['team_type'];
    $testDate = $_POST['test_date'] ?? date('Y-m-d');

    try {
        $pdo = getDBConnection();
        // Call the Punch-Out Summary function instead of Punch-In
        $result = sendScheduledPunchOutSummary($pdo, $testDate, $teamType);

        if ($result) {
            $success = "✅ {$teamType} Punch-Out summary sent successfully!";
        } else {
            $error = "❌ Failed to send {$teamType} Punch-Out summary. Check logs.";
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
    <title>Test Punch-Out Summary</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #FF6B6B 0%, #556270 100%);
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
            background: linear-gradient(135deg, #FF6B6B 0%, #556270 100%);
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
            background: linear-gradient(135deg, #FF6B6B 0%, #556270 100%);
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
            <h1>📤 Test Punch-Out Summary</h1>
            <p>Send punch-out summaries (normally scheduled via cron)</p>
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
                    Send Punch-Out Summary Now
                </button>
            </form>

            <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 8px; font-size: 14px;">
                <strong>💡 Debugging:</strong> If this works, but the automated schedule doesn't, the issue is your
                <strong>Cron Job configuration</strong> (e.g., incorrect path or server time).
            </div>
        </div>
    </div>
</body>

</html>