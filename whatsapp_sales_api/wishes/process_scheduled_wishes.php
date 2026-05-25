<?php
// whatsapp_sales_api/wishes/process_scheduled_wishes.php

file_put_contents(__DIR__ . '/cron_started.txt', "Script start at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

require_once __DIR__ . '/../helper.php';

// Disable timeout for cron execution
set_time_limit(0);
date_default_timezone_set('Asia/Kolkata');

$conn = getDBConnection();
// Use dynamic path for logs compatible with both Local and Prod
$logDir = __DIR__ . '/../../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/whatsapp_sales_cron.log';

function logCron($msg)
{
    global $logFile;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

logCron("Starting scheduled wishes check...");

// Select pending wishes ready to send (scheduled_time <= NOW())
// Limit 50 per execution to avoid throttling issues (Cron runs every minute)
$sql = "SELECT * FROM scheduled_wishes
WHERE status = 'pending'
AND scheduled_time <= NOW()
LIMIT 50";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    logCron("Found " . $result->num_rows . " pending wishes.");

    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $to = $row['whatsapp_number'];
        $name = $row['name'];
        $imageLink = $row['image_link'];
        $templateName = $row['template_name'];

        // Prepare Message
        $components = [
            [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => [
                            'link' => $imageLink
                        ]
                    ]
                ]
            ],
            [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $name
                    ]
                ]
            ]
        ];

        // Send Message using helper
        $sendResult = sendSalesWhatsAppMessage($to, $templateName, 'en_US', $components);

        // Update Status
        if ($sendResult['success']) {
            $status = 'sent';
            $responseData = json_encode($sendResult['response']);
            logCron("Sent wish to ID: $id ($to)");
        } else {
            $status = 'failed';
            $responseData = json_encode($sendResult['error'] ?? 'Unknown Error');
            logCron("Failed wish ID: $id ($to). Error: " . $responseData);
        }

        // Update record
        $updateStmt = $conn->prepare("UPDATE scheduled_wishes SET status = ?, response_data = ? WHERE id = ?");
        $updateStmt->bind_param("ssi", $status, $responseData, $id);
        $updateStmt->execute();

        // Sleep to respect rate limits (e.g. 5 messages per second is safe)
        usleep(200000); // 0.2s
    }
} else {
    logCron("No pending wishes found.");
}

$conn->close();
?>