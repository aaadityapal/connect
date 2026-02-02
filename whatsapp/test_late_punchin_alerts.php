<?php
/**
 * Test Late Punch-In Alerts
 * 
 * This script tests the late punch-in alert functionality
 * Run manually: php test_late_punchin_alerts.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';

echo "=== Testing Late Punch-In Alerts ===\n\n";

try {
    $pdo = getDBConnection();
    $waService = new WhatsAppService();

    // Get today's date
    $today = date('Y-m-d');
    $summaryTime = $today . ' 10:45:00';

    echo "Checking for punch-ins after: " . date('h:i A', strtotime($summaryTime)) . "\n\n";

    // Fetch admins
    $adminStmt = $pdo->prepare("SELECT id, admin_name, phone FROM admin_notifications WHERE is_active = 1");
    $adminStmt->execute();
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Active Admins: " . count($admins) . "\n";
    foreach ($admins as $admin) {
        echo "  - {$admin['admin_name']} ({$admin['phone']})\n";
    }
    echo "\n";

    // Get late punch-ins
    $query = "SELECT 
                u.id,
                u.username,
                a.punch_in,
                a.date
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.date = ?
            AND a.punch_in IS NOT NULL
            AND a.punch_in > ?
            ORDER BY a.punch_in ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$today, $summaryTime]);
    $latePunchIns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Late Punch-Ins Found: " . count($latePunchIns) . "\n\n";

    if (empty($latePunchIns)) {
        echo "✅ No late punch-ins to report.\n";
        exit;
    }

    // Display late punch-ins
    foreach ($latePunchIns as $record) {
        $employeeName = $record['username'];
        $punchInTime = date('h:i A', strtotime($record['punch_in']));
        $punchInDate = date('l, F j, Y', strtotime($record['date']));

        echo "📌 Employee: {$employeeName}\n";
        echo "   Time: {$punchInTime}\n";
        echo "   Date: {$punchInDate}\n";
        echo "   Will notify " . count($admins) . " admin(s)\n\n";
    }

    // Ask for confirmation
    echo "\n⚠️  This will send " . (count($latePunchIns) * count($admins)) . " WhatsApp messages.\n";
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
    foreach ($latePunchIns as $record) {
        $employeeName = $record['username'];
        $punchInTime = date('h:i A', strtotime($record['punch_in']));
        $punchInDate = date('l, F j, Y', strtotime($record['date']));

        foreach ($admins as $admin) {
            $params = [
                $employeeName,
                $punchInTime,
                $punchInDate
            ];

            $result = $waService->sendTemplateMessage(
                $admin['phone'],
                'employee_punchin_alert',
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
