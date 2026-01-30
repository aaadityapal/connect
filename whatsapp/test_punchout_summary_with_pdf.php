<?php
/**
 * Test Punch-Out Summary with PDF Generation
 * 
 * This script tests the new punch-out summary notification system with PDF attachments
 * 
 * Usage: http://localhost/connect/whatsapp/test_punchout_summary_with_pdf.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test Punch-Out Summary with PDF</h1>";
echo "<p>Testing the new admin punch-out summary notification system with PDF attachments...</p>";
echo "<hr>";

try {
    $pdo = getDBConnection();
    $date = date('Y-m-d'); // Today

    echo "<h2>Testing for Date: $date</h2>";

    // Test Field Team
    echo "<h3>1. Testing Field Team Summary</h3>";
    $fieldResult = sendScheduledPunchOutSummary($pdo, $date, 'Field');
    if ($fieldResult) {
        echo "<p style='color: green;'>✓ Field team summary sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to send field team summary</p>";
    }

    echo "<hr>";

    // Test Studio Team
    echo "<h3>2. Testing Studio Team Summary</h3>";
    $studioResult = sendScheduledPunchOutSummary($pdo, $date, 'Studio');
    if ($studioResult) {
        echo "<p style='color: green;'>✓ Studio team summary sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to send studio team summary</p>";
    }

    echo "<hr>";

    // Show generated PDFs
    echo "<h3>3. Generated PDF Files</h3>";
    $pdfDir = __DIR__ . '/../uploads/punchout_summaries';

    if (file_exists($pdfDir)) {
        $files = array_diff(scandir($pdfDir), ['.', '..']);
        $files = array_reverse($files); // Show newest first

        if (!empty($files)) {
            echo "<ul>";
            foreach (array_slice($files, 0, 10) as $file) { // Show last 10 files
                $filePath = $pdfDir . '/' . $file;
                $fileSize = filesize($filePath);
                $fileTime = date('Y-m-d H:i:s', filemtime($filePath));
                $fileUrl = "/connect/uploads/punchout_summaries/" . $file;

                echo "<li>";
                echo "<strong>$file</strong><br>";
                echo "Size: " . number_format($fileSize / 1024, 2) . " KB<br>";
                echo "Created: $fileTime<br>";
                echo "<a href='$fileUrl' target='_blank'>View PDF</a>";
                echo "</li><br>";
            }
            echo "</ul>";
        } else {
            echo "<p>No PDF files found.</p>";
        }
    } else {
        echo "<p>PDF directory does not exist yet.</p>";
    }

    echo "<hr>";

    // Show logs
    echo "<h3>4. Recent Log Entries</h3>";
    $logFile = __DIR__ . '/whatsapp.log';

    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $logLines = explode("\n", $logContent);
        $recentLogs = array_slice(array_reverse($logLines), 0, 20);

        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto;'>";
        foreach ($recentLogs as $line) {
            if (!empty(trim($line))) {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p>No log file found.</p>";
    }

    echo "<hr>";
    echo "<h3>Summary</h3>";
    echo "<p><strong>Template Names Used:</strong></p>";
    echo "<ul>";
    echo "<li>Field Team: <code>admin_punchout_summary_field</code></li>";
    echo "<li>Studio Team: <code>admin_punchout_summary_studio</code></li>";
    echo "</ul>";

    echo "<p><strong>Template Parameters:</strong></p>";
    echo "<ul>";
    echo "<li>{{1}} - Date (e.g., 'January 30, 2026')</li>";
    echo "<li>{{2}} - Summary text (e.g., 'Total employees punched out: 5. Please see attached PDF for detailed work reports.')</li>";
    echo "</ul>";

    echo "<p><strong>PDF Attachment:</strong></p>";
    echo "<ul>";
    echo "<li>Professional PDF with company branding</li>";
    echo "<li>Table showing S.No, Employee Name, Punch-Out Time, and Work Report</li>";
    echo "<li>Stored in: <code>/uploads/punchout_summaries/</code></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
