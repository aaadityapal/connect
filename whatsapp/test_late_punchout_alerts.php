<?php
/**
 * Test Late Punch-Out Alerts
 * 
 * This script tests the late punch-out alert functionality
 * Run manually: php test_late_punchout_alerts.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';

echo "=== Testing Late Punch-Out Alerts ===\n\n";

try {
    $pdo = getDBConnection();
    $waService = new WhatsAppService();

    // Get today's date
    $today = date('Y-m-d');
    $summaryTime = $today . ' 21:00:00';

    echo "Checking for punch-outs after: " . date('h:i A', strtotime($summaryTime)) . "\n\n";

    // Fetch admins
    $adminStmt = $pdo->prepare("SELECT id, admin_name, phone FROM admin_notifications WHERE is_active = 1");
    $adminStmt->execute();
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Active Admins: " . count($admins) . "\n";
    foreach ($admins as $admin) {
        echo "  - {$admin['admin_name']} ({$admin['phone']})\n";
    }
    echo "\n";

    // Get late punch-outs
    $query = "SELECT 
                u.id,
                u.username,
                a.punch_out,
                a.date
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.date = ?
            AND a.punch_out IS NOT NULL
            AND a.punch_out > ?
            ORDER BY a.punch_out ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$today, $summaryTime]);
    $latePunchOuts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Late Punch-Outs Found: " . count($latePunchOuts) . "\n\n";

    if (empty($latePunchOuts)) {
        echo "✅ No late punch-outs to report.\n";
        exit;
    }

    // Display late punch-outs
    foreach ($latePunchOuts as $record) {
        $employeeName = $record['username'];
        $punchOutTime = date('h:i A', strtotime($record['punch_out']));
        $punchOutDate = date('l, F j, Y', strtotime($record['date']));

        echo "📌 Employee: {$employeeName}\n";
        echo "   Time: {$punchOutTime}\n";
        echo "   Date: {$punchOutDate}\n";
        echo "   Will notify " . count($admins) . " admin(s)\n\n";
    }

    // Ask for confirmation
    echo "\n⚠️  This will send " . (count($latePunchOuts) * count($admins)) . " WhatsApp messages.\n";
    echo "Do you want to proceed? (yes/no): ";

    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));

    if (strtolower($line) !== 'yes') {
        echo "\n❌ Test cancelled.\n";
        exit;
    }

    echo "\n🚀 Sending alerts...\n\n";

    $totalSent = 0;

    // Send alerts
    foreach ($latePunchOuts as $record) {
        $employeeName = $record['username'];
        $punchOutTime = date('h:i A', strtotime($record['punch_out']));
        $punchOutDate = date('l, F j, Y', strtotime($record['date']));

        foreach ($admins as $admin) {
            $params = [
                $employeeName,
                $punchOutTime,
                $punchOutDate
            ];

            $result = $waService->sendTemplateMessage(
                $admin['phone'],
                'employee_punchout_alert',
                'en_US',
                $params
            );

            if ($result['success']) {
                $totalSent++;
                echo "✅ Sent to {$admin['admin_name']} for {$employeeName}\n";
            } else {
                echo "❌ Failed to send to {$admin['admin_name']} for {$employeeName}\n";
                echo "   Response: " . ($result['response'] ?? 'Unknown error') . "\n";
            }
        }
    }

    echo "\n✅ Test completed. Total alerts sent: {$totalSent}\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
