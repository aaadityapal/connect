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

        // ===== MINIMALIST HEADER SECTION =====
        // Company Name
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor(79, 70, 229); // Vibrant Indigo
        $pdf->Cell(0, 8, 'ArchitectsHive', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(107, 114, 128); // Soft Gray
        $pdf->Cell(0, 5, 'Team Attendance & Activity Report', 0, 1, 'L');

        // Clean subtle divider
        $pdf->Ln(2);
        $pdf->SetDrawColor(224, 231, 255); // Super light indigo border
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(8);

        // ===== TITLE SECTION =====
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor(17, 24, 39); // Very dark slate
        $pdf->Cell(0, 10, "Daily Punch-Out Summary", 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(236, 72, 153); // Vibrant Pink accent for team name
        $pdf->Cell(0, 6, strtoupper($teamType) . " TEAM", 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->Cell(0, 6, $dateFormatted . "  •  Generated at " . $currentTime, 0, 1, 'L');
        $pdf->Ln(6);

        // ===== SUMMARY BOX =====
        $totalEmployees = count($punchOutData);

        // A beautiful soft indigo card
        $pdf->SetFillColor(238, 242, 255); // Indigo 50
        $pdf->SetDrawColor(238, 242, 255); // No visible outer border
        $pdf->SetLineWidth(0.1);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(67, 56, 202); // Deep Indigo
        // Render a pill-like card
        $pdf->Cell(0, 14, "   Total Employees Punched Out: " . $totalEmployees, 1, 1, 'L', 1);
        $pdf->Ln(8);

        // ===== TABLE HEADER =====
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(79, 70, 229); // Vibrant Indigo header
        $pdf->SetTextColor(255, 255, 255); // White text
        $pdf->SetDrawColor(99, 102, 241); // Slightly lighter indigo border to blend beautifully
        $pdf->SetLineWidth(0.2);

        // Sleeker column widths
        $w = [12, 48, 30, 90]; 
        $headers = ['#', 'Employee Name', 'Punch-Out', 'Work Report'];

        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($w[$i], 9, $headers[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();

        // ===== TABLE ROWS =====
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(55, 65, 81); // Slate gray for optimal reading

        if (empty($punchOutData)) {
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->SetTextColor(156, 163, 175);
            $pdf->Cell(0, 20, 'No punch-outs recorded for this team today. Go grab a coffee! ☕', 0, 1, 'C');
        } else {
            $rowNum = 1;
            foreach ($punchOutData as $record) {
                // Soft pastel row alternation
                if ($rowNum % 2 == 0) {
                    $pdf->SetFillColor(249, 250, 251); // Gray 50
                } else {
                    $pdf->SetFillColor(255, 255, 255); // Clean White
                }

                $pdf->SetDrawColor(229, 231, 235); // Very soft gray borders

                $punchOutTime = date('h:i A', strtotime($record['punch_out']));

                $workReport = $record['work_report'] ?? 'No report submitted';
                $workReport = trim($workReport);
                if (empty($workReport)) {
                    $workReport = 'No report submitted';
                }

                $nb = $pdf->getNumLines($workReport, $w[3]);
                $h = max(10, 6 * $nb); // Added slightly more padding to rows (min 10mm)

                // Page break logic with reprinted clean header
                if ($pdf->GetY() + $h > $pdf->GetPageHeight() - 20) {
                    $pdf->AddPage();

                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetFillColor(79, 70, 229);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetDrawColor(99, 102, 241);

                    for ($i = 0; $i < count($headers); $i++) {
                        $pdf->Cell($w[$i], 9, $headers[$i], 1, 0, 'C', 1);
                    }
                    $pdf->Ln();

                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetTextColor(55, 65, 81);
                }

                $pdf->Cell($w[0], $h, $rowNum, 1, 0, 'C', 1);
                $pdf->Cell($w[1], $h, '  ' . $record['username'], 1, 0, 'L', 1);
                $pdf->Cell($w[2], $h, $punchOutTime, 1, 0, 'C', 1);
                $pdf->MultiCell($w[3], $h, $workReport, 1, 'L', 1, 1, null, null, true, 0, false, true, $h, 'M');

                $rowNum++;
            }
        }

        // ===== FOOTER SECTION =====
        $pdf->Ln(12);
        
        // Separator above footer
        $pdf->SetDrawColor(243, 244, 246);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(4);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(156, 163, 175); // Slate 400
        $pdf->Cell(0, 4, 'Automated report generated by Conneqts.io © ' . date('Y') . '. All rights reserved.', 0, 1, 'C');

        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(244, 114, 182); // Sweet pink for signature
        $pdf->Cell(0, 4, 'Made With Love By Aditya 💖', 0, 1, 'C');

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
