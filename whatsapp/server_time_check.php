<?php
/**
 * Server Time Configuration Checker
 * Use this to verify why Cron Jobs might be running at the wrong time.
 */

// Set Content Type
header('Content-Type: text/html; charset=utf-8');

// 1. Get PHP Time Info
$phpTime = date('Y-m-d H:i:s');
$phpTimezone = date_default_timezone_get();
$phpTimestamp = time();

// 2. Get System Time Info (via shell)
$systemDateCmd = shell_exec('date');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Time Check</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            background: #f4f4f4;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        h2 {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
        }

        .highlight {
            color: #d63384;
            font-weight: bold;
        }

        .ok {
            color: green;
            font-weight: bold;
        }

        .warn {
            color: orange;
            font-weight: bold;
        }

        .error {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>🕒 Server Time Diagnostic</h1>

    <div class="card">
        <h2>PHP Configuration</h2>
        <p><strong>Current PHP Time:</strong> <span class="highlight">
                <?php echo $phpTime; ?>
            </span></p>
        <p><strong>Configured Timezone:</strong>
            <?php echo $phpTimezone; ?>
        </p>
        <p><em>This is the time used by your scripts (date() function).</em></p>
    </div>

    <div class="card">
        <h2>System Configuration</h2>
        <p><strong>System Terminal Time:</strong> <span class="highlight">
                <?php echo trim($systemDateCmd); ?>
            </span></p>
        <p><em>This is the time used by Cron (unless PHP overrides it).</em></p>
    </div>

    <div class="card">
        <h2>Diagnostics</h2>
        <?php
        $targetTimezone = 'Asia/Kolkata'; // Assuming IST based on context
        $date = new DateTime(null, new DateTimeZone($targetTimezone));
        $istTime = $date->format('Y-m-d H:i:s');

        echo "<p><strong>Current IST Time:</strong> $istTime</p>";

        // Check PHP Timezone
        if ($phpTimezone === 'Asia/Kolkata' || $phpTimezone === 'IST') {
            echo "<p class='ok'>✅ PHP Timezone is correctly set to Asia/Kolkata.</p>";
        } else {
            echo "<p class='error'>❌ PHP Timezone is '$phpTimezone'. It should likely be 'Asia/Kolkata'.</p>";
            echo "<p>If your Cron runs at 18:20 (6:20 PM) based on this timezone, it might be running at the wrong real-world time.</p>";
        }

        // Check if System time matches PHP time
        $systemTimestamp = strtotime($systemDateCmd);
        $diff = abs($systemTimestamp - $phpTimestamp);

        if ($diff > 60) {
            echo "<p class='warn'>⚠️ System time and PHP time are different by " . round($diff / 60) . " minutes.</p>";
            echo "<p>Cron relies on System Time. Ensure your Crontab schedule matches the SYSTEM time, not just the PHP time.</p>";
        } else {
            echo "<p class='ok'>✅ System time and PHP time are synced.</p>";
        }
        ?>
    </div>

    <div class="card">
        <h2>Correct Cron Schedule Calculator</h2>
        <p>If your server is in <strong>UTC</strong> (Universal Time), here is when you should schedule your jobs to
            match India time:</p>
        <ul>
            <li><strong>06:20 PM IST</strong> = 12:50 PM UTC (`50 12 * * *`)</li>
            <li><strong>07:15 PM IST</strong> = 01:45 PM UTC (`45 13 * * *`)</li>
            <li><strong>09:00 PM IST</strong> = 03:30 PM UTC (`30 15 * * *`)</li>
        </ul>
        <br>
        <p>If your server is in <strong>IST</strong> (Asia/Kolkata), use the normal times:</p>
        <ul>
            <li>`20 18 * * *`</li>
            <li>`15 19 * * *`</li>
            <li>`0 21 * * *`</li>
        </ul>
    </div>
</body>

</html>