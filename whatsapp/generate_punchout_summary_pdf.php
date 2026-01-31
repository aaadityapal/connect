<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tcpdf/tcpdf.php';

// Set Base URL for document links
if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://conneqts.io');
}

/**
 * Generate Punch-Out Summary PDF for Admin Notifications
 * 
 * @param array $punchOutData Array of punch-out records with username, punch_out, work_report
 * @param string $date Date for the report (Y-m-d format)
 * @param string $teamType 'Studio' or 'Field'
 * @return array ['success' => bool, 'url' => string, 'file_path' => string]
 */
function generatePunchOutSummaryPDF($punchOutData, $date, $teamType)
{
    try {
        // Format date for display
        $dateFormatted = date('l, F j, Y', strtotime($date));
        $currentTime = date('h:i A');

        // Create PDF instance
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false); // Portrait

        // Set document metadata
        $pdf->SetCreator('ArchitectsHive CRM');
        $pdf->SetAuthor('ArchitectsHive');
        $pdf->SetTitle("Punch-Out Summary - $teamType Team - $dateFormatted");
        $pdf->SetSubject('Daily Punch-Out Work Report');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add page
        $pdf->AddPage();

        // ===== HEADER SECTION =====
        // Company Name
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(41, 128, 185); // Professional blue
        $pdf->Cell(0, 10, 'ArchitectsHive', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'Attendance & Work Report System', 0, 1, 'C');

        // Divider line
        $pdf->SetDrawColor(41, 128, 185);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);
        $pdf->Ln(5);

        // ===== TITLE SECTION =====
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, "Daily Punch-Out Summary", 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->Cell(0, 6, "$teamType Team", 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, $dateFormatted, 0, 1, 'C');
        $pdf->Cell(0, 5, "Generated at: $currentTime", 0, 1, 'C');
        $pdf->Ln(8);

        // ===== SUMMARY BOX =====
        $totalEmployees = count($punchOutData);

        $pdf->SetFillColor(236, 240, 241);
        $pdf->SetDrawColor(189, 195, 199);
        $pdf->SetLineWidth(0.3);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->Cell(90, 8, 'Total Employees Punched Out:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(90, 8, $totalEmployees, 1, 1, 'C', 1);
        $pdf->Ln(5);

        // ===== TABLE HEADER =====
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(52, 73, 94); // Dark blue-gray
        $pdf->SetTextColor(255, 255, 255); // White text
        $pdf->SetDrawColor(52, 73, 94);
        $pdf->SetLineWidth(0.3);

        // Column widths: S.No (15), Name (50), Time (30), Work Report (85)
        $w = [15, 50, 30, 85];
        $headers = ['S.No', 'Employee Name', 'Punch-Out Time', 'Work Report'];

        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();

        // ===== TABLE ROWS =====
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);

        if (empty($punchOutData)) {
            // No punch-outs - show message
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 20, 'No punch-outs recorded for this team today.', 0, 1, 'C');
        } else {
            // Show punch-out data
            $rowNum = 1;
            foreach ($punchOutData as $record) {
                // Alternate row colors for better readability
                if ($rowNum % 2 == 0) {
                    $pdf->SetFillColor(249, 249, 249); // Light gray
                } else {
                    $pdf->SetFillColor(255, 255, 255); // White
                }

                $pdf->SetDrawColor(189, 195, 199); // Light border

                // Format punch-out time
                $punchOutTime = date('h:i A', strtotime($record['punch_out']));

                // Clean and prepare work report
                $workReport = $record['work_report'] ?? 'No report submitted';
                $workReport = trim($workReport);
                if (empty($workReport)) {
                    $workReport = 'No report submitted';
                }

                // Calculate row height based on work report content
                $nb = $pdf->getNumLines($workReport, $w[3]);
                $h = max(8, 6 * $nb); // Minimum 8mm height

                // Check for page break
                if ($pdf->GetY() + $h > $pdf->GetPageHeight() - 20) {
                    $pdf->AddPage();

                    // Reprint header on new page
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetFillColor(52, 73, 94);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetDrawColor(52, 73, 94);

                    for ($i = 0; $i < count($headers); $i++) {
                        $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', 1);
                    }
                    $pdf->Ln();

                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetTextColor(0, 0, 0);
                }

                // Store current position
                $startX = $pdf->GetX();
                $startY = $pdf->GetY();

                // Draw cells with same height
                $pdf->Cell($w[0], $h, $rowNum, 1, 0, 'C', 1);
                $pdf->Cell($w[1], $h, $record['username'], 1, 0, 'L', 1);
                $pdf->Cell($w[2], $h, $punchOutTime, 1, 0, 'C', 1);

                // MultiCell for work report (allows text wrapping)
                $pdf->MultiCell($w[3], $h, $workReport, 1, 'L', 1, 1, null, null, true, 0, false, true, $h, 'M');

                $rowNum++;
            }
        }

        // ===== FOOTER SECTION =====
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 5, 'This is an automated report generated by Conneqts.io', 0, 1, 'C');
        $pdf->Cell(0, 5, '© ' . date('Y') . ' Conneqts.io. All rights reserved.', 0, 1, 'C');

        // Developer credit
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(200, 100, 100);
        $pdf->Cell(0, 5, 'Made With Love By Aditya', 0, 1, 'C');

        // ===== SAVE FILE =====
        $uploadDir = __DIR__ . '/../uploads/punchout_summaries';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = "PunchOut_Summary_{$teamType}_" . date('Y-m-d', strtotime($date)) . "_" . date('His') . ".pdf";
        $filePath = $uploadDir . '/' . $fileName;

        $pdf->Output($filePath, 'F'); // Save to file

        // Return URL and file path
        $fileUrl = BASE_URL . "/uploads/punchout_summaries/" . $fileName;

        return [
            'success' => true,
            'url' => $fileUrl,
            'file_path' => $filePath,
            'file_name' => $fileName
        ];

    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
