<?php
/**
 * Test PDF Generation Only (No WhatsApp Sending)
 * This tests the PDF creation locally without needing production setup
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/generate_punchout_summary_pdf.php';

header('Content-Type: text/html; charset=utf-8');

// Get date from query parameter or use today
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

echo "<h1>Test PDF Generation (Local)</h1>";
echo "<p>This will generate PDFs without sending WhatsApp messages.</p>";

// Date selector form
echo "<form method='GET' style='margin: 20px 0; padding: 15px; background: #f5f5f5; border: 1px solid #ddd;'>";
echo "<label><strong>Select Date:</strong> ";
echo "<input type='date' name='date' value='$date' max='" . date('Y-m-d') . "'>";
echo " <button type='submit'>Load Data</button>";
echo "</label>";
echo "</form>";

echo "<hr>";

try {
    $pdo = getDBConnection();

    echo "<h2>Date: $date (" . date('l, F j, Y', strtotime($date)) . ")</h2>";

    // Get punch-out data for both teams
    require_once __DIR__ . '/send_punch_notification.php';

    // Test Studio Team
    echo "<h3>Studio Team</h3>";
    $studioData = getPunchOutDataByTeam($pdo, $date, 'Studio');

    if (!empty($studioData)) {
        echo "<p>Found " . count($studioData) . " punch-outs:</p>";
        echo "<ul>";
        foreach ($studioData as $record) {
            $time = date('h:i A', strtotime($record['punch_out']));
            echo "<li><strong>{$record['username']}</strong> - {$time}<br>";
            echo "<em>" . ($record['work_report'] ?: 'No report') . "</em></li>";
        }
        echo "</ul>";

        // Generate PDF
        $pdfResult = generatePunchOutSummaryPDF($studioData, $date, 'Studio');

        if ($pdfResult['success']) {
            echo "<p style='color: green;'>✓ PDF Generated Successfully!</p>";
            echo "<p><strong>File:</strong> {$pdfResult['file_name']}</p>";
            echo "<p><strong>Path:</strong> {$pdfResult['file_path']}</p>";
            echo "<p><a href='/connect/uploads/punchout_summaries/{$pdfResult['file_name']}' target='_blank'>📄 View Studio Team PDF</a></p>";
        } else {
            echo "<p style='color: red;'>✗ PDF Generation Failed: " . ($pdfResult['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p>No punch-outs found for Studio team.</p>";
    }

    echo "<hr>";

    // Test Field Team
    echo "<h3>Field Team</h3>";
    $fieldData = getPunchOutDataByTeam($pdo, $date, 'Field');

    if (!empty($fieldData)) {
        echo "<p>Found " . count($fieldData) . " punch-outs:</p>";
        echo "<ul>";
        foreach ($fieldData as $record) {
            $time = date('h:i A', strtotime($record['punch_out']));
            echo "<li><strong>{$record['username']}</strong> - {$time}<br>";
            echo "<em>" . ($record['work_report'] ?: 'No report') . "</em></li>";
        }
        echo "</ul>";

        // Generate PDF
        $pdfResult = generatePunchOutSummaryPDF($fieldData, $date, 'Field');

        if ($pdfResult['success']) {
            echo "<p style='color: green;'>✓ PDF Generated Successfully!</p>";
            echo "<p><strong>File:</strong> {$pdfResult['file_name']}</p>";
            echo "<p><strong>Path:</strong> {$pdfResult['file_path']}</p>";
            echo "<p><a href='/connect/uploads/punchout_summaries/{$pdfResult['file_name']}' target='_blank'>📄 View Field Team PDF</a></p>";
        } else {
            echo "<p style='color: red;'>✗ PDF Generation Failed: " . ($pdfResult['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p>No punch-outs found for Field team.</p>";
    }

    echo "<hr>";
    echo "<h3>Summary</h3>";
    echo "<p><strong>To test WhatsApp sending:</strong></p>";
    echo "<ol>";
    echo "<li>Push code to production server</li>";
    echo "<li>Create templates in Meta Business Manager:";
    echo "<ul>";
    echo "<li><code>admin_punchout_summary_studio</code></li>";
    echo "<li><code>admin_punchout_summary_field</code></li>";
    echo "</ul></li>";
    echo "<li>Wait for template approval</li>";
    echo "<li>Run: <code>php cron_punchout_summary.php both</code></li>";
    echo "</ol>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
