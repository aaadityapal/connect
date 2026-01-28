<?php
/**
 * Test Punch Out Notification Specifically
 * 
 * Verifies that the WhatsAppService can send the 'missing_punch_out_alert' template.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';

// Target for testing (Use a test number or your own)
// Replace with the number you want to receive the test message
// Suggestion: Use the number from the user ID 21 (Aditya) if available, or a hardcoded test number
$targetName = "Test User";
$targetPhone = "919999999999";
$punchInTime = "09:00 AM";
$date = date('d-m-Y');

// Ensure we have a CLI argument for the phone number to be safe
if (isset($argv[1])) {
    $targetPhone = $argv[1];
} else {
    echo "Usage: php test_send_punchout_alert.php <phone_number>\n";
    echo "Example: php test_send_punchout_alert.php 919876543210\n";
    echo "Using default/dummy: $targetPhone\n\n";
}

echo "===== TESTING MISSING PUNCH OUT ALERT =====\n";
echo "Sending to: $targetPhone\n";
echo "Template: missing_punch_out_alert\n";
echo "Params: Name=$targetName, Date=$date, LastPunchIn=$punchInTime\n";
echo "-------------------------------------------\n";

try {
    $waService = new WhatsAppService();

    // Template variables: {{1}}=Name, {{2}}=Date, {{3}}=Last Punch In Time
    $params = [
        $targetName,
        $date,
        $punchInTime
    ];

    $result = $waService->sendTemplateMessage(
        $targetPhone,
        'missing_punch_out_alert',
        'en_US',
        $params
    );

    if ($result['success']) {
        echo "[SUCCESS] Message sent successfully!\n";
        echo "Response: " . print_r($result['response'], true) . "\n";
    } else {
        echo "[FAILURE] Failed to send message.\n";
        echo "Error: " . print_r($result, true) . "\n";
    }

} catch (Exception $e) {
    echo "[EXCEPTION] " . $e->getMessage() . "\n";
}
