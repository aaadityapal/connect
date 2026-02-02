<?php
/**
 * Test Monthly Report Generation & Send for Single User
 * Supports CLI (php test_monthly_report.php 21)
 * Supports Web (http://.../test_monthly_report.php?user_id=21)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/generate_monthly_report_v2.php';

// 1. Get User ID
// Check GET, then CLI Argument
$targetUserId = $_GET['user_id'] ?? $argv[1] ?? null;
$isWeb = isset($_SERVER['HTTP_HOST']);

// Format for Browser
if ($isWeb) {
    echo "<!DOCTYPE html><html><head><title>Monthly Report Test</title>";
    echo "<style>body{font-family:sans-serif;line-height:1.6;padding:20px;} pre{background:#f4f4f4;padding:10px;border-radius:5px;} .btn{display:inline-block;padding:10px 15px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin-top:10px;}</style>";
    echo "</head><body>";
    echo "<h2>Monthly Report Test</h2>";
}

if (!$targetUserId) {
    echo $isWeb ? "<p><strong>Usage:</strong> Please provide a user_id in the URL.</p><p>Example: <code>?user_id=21</code></p>" : "Usage:\nCLI: php test_monthly_report.php <user_id>\nWeb: ?user_id=<user_id>\n";
    exit;
}

if ($isWeb)
    echo "<pre>";
echo "===== TESTING MONTHLY REPORT (User ID: $targetUserId) =====\n";

try {
    // Database Connection Logic (Support both direct variable and function)
    if (isset($pdo)) {
        // $pdo is already set by config.php
    } elseif (function_exists('getDBConnection')) {
        $pdo = getDBConnection();
    } else {
        throw new Exception("Database connection (\$pdo) not found in config.php");
    }

    // Test for CURRENT month
    $month = date('m');
    $year = date('Y');

    echo "Generating Report for $month / $year...\n";

    // 1. Generate Info
    $result = generateMonthlyReportPDF($targetUserId, $month, $year, $pdo);

    if ($result['success']) {
        echo "[SUCCESS] PDF Generated: " . $result['file_path'] . "\n";
        echo "Production URL (for WhatsApp): " . $result['url'] . "\n";

        if ($isWeb)
            echo "</pre>";

        // Helper link for testing
        if ($isWeb) {
            // Production URL suggestion
            $prodTestUrl = "https://conneqts.io/whatsapp/test_monthly_report.php?user_id=" . $targetUserId;
            echo "<div style='margin:20px 0; padding:15px; border:1px solid #ccc; background:#e9ffe9; border-radius:8px;'>";
            echo "<strong>✅ Success!</strong><br><br>";
            echo "The PDF has been generated.<br>";
            echo "File: " . $result['file_path'] . "<br><br>";
            echo "To test on Production, visit: <a href='$prodTestUrl' target='_blank'>$prodTestUrl</a>";
            echo "</div>";
            echo "<pre>";
        }

        echo "Stats: " . json_encode($result['stats']) . "\n";

        // 2. Fetch User Phone
        $stmt = $pdo->prepare("SELECT username, phone FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $user = $stmt->fetch();

        if ($user && !empty($user['phone'])) {
            echo "Sending WhatsApp to {$user['username']} ({$user['phone']})...\n";

            $waService = new WhatsAppService();
            $monthNameFull = date('F Y');

            $params = [
                $user['username'],
                $monthNameFull,
                $result['stats']['present'],
                $result['stats']['absent'],
                $result['stats']['working']
            ];

            $headerParams = [
                [
                    'type' => 'document',
                    'document' => [
                        'link' => $result['url'],
                        'filename' => basename($result['file_path'])
                    ]
                ]
            ];

            $res = $waService->sendTemplateMessage($user['phone'], 'monthly_work_report', 'en_US', $params, $headerParams);

            if ($res['success']) {
                echo "[SUCCESS] Message Sent!";
            } else {
                echo "[FAIL] Message Send Failed: " . print_r($res, true);
            }
        } else {
            echo "[FAIL] User not found or no phone.";
        }

    } else {
        echo "[FAIL] Logic Error: " . $result['error'];
    }

} catch (Exception $e) {
    echo "[EXCEPTION] " . $e->getMessage();
}

if ($isWeb)
    echo "</pre></body></html>";
