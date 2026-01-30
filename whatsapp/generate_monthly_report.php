<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tcpdf/tcpdf.php';

// Set Base URL for document links (Adjust this to your actual Production URL)
if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://conneqts.io');
}

/**
 * Generate Monthly Work Report PDF
 * 
 * @param int $userId
 * @param int $month
 * @param int $year
 * @param PDO $pdo
 * @return array ['success' => bool, 'url' => string, 'file_path' => string, 'stats' => array]
 */
function generateMonthlyReportPDF($userId, $month, $year, $pdo)
{
    try {
        // 1. Fetch User Details
        $stmt = $pdo->prepare("SELECT unique_id, username, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'error' => "User not found"];
        }

        // 2. Fetch Helper Data (Month Name, Days)
        $monthName = date('F', mktime(0, 0, 0, $month, 10));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $startDate = "$year-$month-01";
        $endDate = "$year-$month-$daysInMonth";

        // 3. Fetch Attendance Data
        $attQuery = "SELECT date, punch_in, punch_out, status, work_report 
                     FROM attendance 
                     WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
                     ORDER BY date ASC";
        $attStmt = $pdo->prepare($attQuery);
        $attStmt->execute([$userId, $month, $year]);
        $attendanceRecords = $attStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE); // Group by date

        // 4. Fetch Leave Data
        $leaveQuery = "SELECT lr.start_date, lr.end_date, lt.name as leave_name, lr.reason, lr.status 
                       FROM leave_request lr
                       JOIN leave_types lt ON lr.leave_type = lt.id
                       WHERE lr.user_id = ? 
                       AND lr.status = 'approved'
                       AND (
                           (MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?)
                           OR 
                           (MONTH(lr.end_date) = ? AND YEAR(lr.end_date) = ?)
                       )";
        $leaveStmt = $pdo->prepare($leaveQuery);
        $leaveStmt->execute([$userId, $month, $year, $month, $year]);
        $leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

        // Map leaves to dates
        $leaveDays = [];
        foreach ($leaves as $leave) {
            $current = strtotime($leave['start_date']);
            $end = strtotime($leave['end_date']);
            while ($current <= $end) {
                $d = date('Y-m-d', $current);
                if (date('m', $current) == $month && date('Y', $current) == $year) {
                    $leaveDays[$d] = $leave['leave_name'];
                }
                $current = strtotime('+1 day', $current);
            }
        }

        // 5. Fetch Holidays
        $holidayQuery = "SELECT holiday_date, holiday_name FROM office_holidays 
                         WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?";
        $holStmt = $pdo->prepare($holidayQuery);
        $holStmt->execute([$month, $year]);
        $holidays = $holStmt->fetchAll(PDO::FETCH_KEY_PAIR); // Date => Name

        // 6. Fetch Weekly Offs (Approximation from current setting)
        // Note: Ideally we should track historical shift data, but using current shift for report is standard practice if history table not robust
        $shiftStmt = $pdo->prepare("SELECT us.weekly_offs, s.start_time 
                                   FROM user_shifts us
                                   JOIN shifts s ON us.shift_id = s.id
                                   WHERE us.user_id = ? 
                                   ORDER BY us.effective_from DESC LIMIT 1");
        $shiftStmt->execute([$userId]);
        $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
        $weeklyOffs = isset($shift['weekly_offs']) ? array_map('trim', explode(',', $shift['weekly_offs'])) : [];
        $shiftStartTime = isset($shift['start_time']) ? strtotime($shift['start_time']) : null;

        // 7. Calculate Stats & Prepare Rows
        $presentCount = 0;
        $absentCount = 0;
        $workingDaysCount = 0;
        $holidayCount = 0;
        $weeklyOffCount = 0;
        $leaveCount = 0;
        $reportData = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $d);
            $dayName = date('l', strtotime($currentDate));
            $isSunday = ($dayName === 'Sunday'); // Example fix if needed, but we rely on weeklyOffs

            $status = '-'; // Default
            $inTime = '-';
            $outTime = '-';
            $remarks = '';
            $report = '';
            $rowColor = [255, 255, 255]; // White

            // Holidays
            if (isset($holidays[$currentDate])) {
                $status = 'Holiday';
                $remarks = $holidays[$currentDate];
                $rowColor = [240, 248, 255]; // Light Blue
                $holidayCount++;
            }
            // Weekly Off
            elseif (in_array($dayName, $weeklyOffs)) {
                $status = 'Weekly Off';
                $rowColor = [240, 240, 240]; // Light Grey
                $weeklyOffCount++;
            } else {
                // It's a working day
                $workingDaysCount++;
                $status = 'Absent'; // Default until proven present
                $rowColor = [255, 235, 238]; // Light Red (Absent)

                // Check Attendance
                if (isset($attendanceRecords[$currentDate])) {
                    $att = $attendanceRecords[$currentDate];
                    $presentCount++;
                    $status = 'Present';
                    $rowColor = [255, 255, 255]; // Reset to White

                    $inTime = date('H:i', strtotime($att['punch_in']));
                    $outTime = $att['punch_out'] ? date('H:i', strtotime($att['punch_out'])) : 'Missing';
                    $report = $att['work_report'];

                    // Late Check
                    if ($shiftStartTime) {
                        $punchInTs = strtotime($att['punch_in']);
                        // Re-construct shift start for this day
                        $dayShiftStart = strtotime($currentDate . ' ' . date('H:i:s', $shiftStartTime));

                        // Grace Period: 15 minutes inclusive.
                        // 09:15:00 is OK. 09:15:59 is OK. 09:16:00 is Late.
                        $graceTime = $dayShiftStart + (15 * 60) + 59;

                        // We compare timestamps
                        if ($punchInTs > $graceTime) {
                            $remarks = 'Late In';
                            $rowColor = [255, 249, 196]; // Yellowish for Late
                        }
                    }
                }
                // Check Leave
                elseif (isset($leaveDays[$currentDate])) {
                    $status = 'Leave';
                    $remarks = $leaveDays[$currentDate]; // Show Leave Name (e.g., "Sick Leave")
                    $rowColor = [225, 245, 254]; // Light Blue
                    // Fix: Decrement working days, as approved leave is not a "Working Day" in some contexts,
                    // OR keep it as working day but status is Leave.
                    // User request: "in status show leaves".
                    // Let's count it as a Leave.
                    $leaveCount++;
                    $workingDaysCount--; // Determine if leave counts as 'working day' needed to be present. Usually Paid Leave is part of payroll but not 'Days Worked'.
                    // For this report, 'Working Days' usually means 'Schduled to Work'. 
                    // If on leave, you are excused. So maybe don't count in 'Working Days' stat if that stat means 'Days you CAME'. 
                    // But 'Working Days' usually means 'Possible Working Days'. Leave implies you missed a working day validly.
                    // Let's keep it simple: If Status = Leave, it is NOT 'Absent', NOT 'Present'.
                } else {
                    // Start date check: Don't mark absent if before joining? (Optional refinement)
                    // For now, simple logic.
                    $absentCount++;
                }
            }

            $reportData[] = [
                'date' => $currentDate,
                'day' => $dayName,
                'status' => $status,
                'in' => $inTime,
                'out' => $outTime,
                'remarks' => $remarks,
                'report' => $report,
                'color' => $rowColor
            ];
        }

        // 8. Generate PDF using TCPDF
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // Landscape for more space

        // Metadata
        $pdf->SetCreator('Conneqts CRM');
        $pdf->SetAuthor('Conneqts');
        $pdf->SetTitle("Monthly Report - $monthName $year - {$user['username']}");

        // Header/Footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Margins
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 10);

        $pdf->AddPage();

        // Title Section
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, "Monthly Attendance & Work Report", 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, "Employee: {$user['username']} ({$user['unique_id']})", 0, 1, 'C');
        $pdf->Cell(0, 7, "Month: $monthName $year", 0, 1, 'C');
        $pdf->Ln(5);

        // Stats Summary Box
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 8, "Working Days", 1, 0, 'C', 1);
        $pdf->Cell(30, 8, "Present Days", 1, 0, 'C', 1);
        $pdf->Cell(30, 8, "Absent Days", 1, 0, 'C', 1);
        $pdf->Cell(25, 8, "Leaves", 1, 0, 'C', 1);
        $pdf->Cell(25, 8, "Holidays", 1, 0, 'C', 1);
        $pdf->Cell(25, 8, "Weekly Offs", 1, 0, 'C', 1);
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(30, 8, $workingDaysCount, 1, 0, 'C');
        $pdf->Cell(30, 8, $presentCount, 1, 0, 'C');
        $pdf->Cell(30, 8, $absentCount, 1, 0, 'C');

        // Use calculated counts
        $pdf->Cell(25, 8, $leaveCount, 1, 0, 'C');
        $pdf->Cell(25, 8, $holidayCount, 1, 0, 'C');
        $pdf->Cell(25, 8, $weeklyOffCount, 1, 1, 'C');
        $pdf->Ln(8);

        // Main Table Header
        $colWidths = [25, 25, 25, 20, 20, 30, 40, 90]; // Date, Day, Status, In, Out, Remarks, Report
        // Wait, 7 cols or 8? 
        // 1. Date (25)
        // 2. Day (25)
        // 3. Status (25)
        // 4. In (20)
        // 5. Out (20)
        // 6. Remarks (40)
        // 7. Work Report (Remaining ~120) A4 Landscape is 297mm - 20mm margin = 277mm
        // 25+25+25+20+20+40 = 155. Remaining = 122. Good.
        // Let's adjust to fit nicely.

        $w = [25, 25, 25, 18, 18, 35, 131];
        $headers = ['Date', 'Day', 'Status', 'In', 'Out', 'Remarks', 'Work Report'];

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(50, 50, 50);
        $pdf->SetTextColor(255);
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        $pdf->SetTextColor(0);

        // Table Rows
        $pdf->SetFont('helvetica', '', 8);

        foreach ($reportData as $row) {
            // Determine height based on MultiCell for Work Report
            // We use GetNumLines to calculate max height
            $textReport = $row['report'];
            $nb = $pdf->getNumLines($textReport, $w[6]);
            $h = 6 * $nb; // Base height 6mm
            if ($h < 6)
                $h = 6;

            // Check page break
            if ($pdf->GetY() + $h > $pdf->GetPageHeight() - 15) {
                $pdf->AddPage();
                // Reprint header
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(50, 50, 50);
                $pdf->SetTextColor(255);
                for ($i = 0; $i < count($headers); $i++) {
                    $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', 1);
                }
                $pdf->Ln();
                $pdf->SetTextColor(0);
                $pdf->SetFont('helvetica', '', 8);
            }

            // Set Row Color
            $pdf->SetFillColor($row['color'][0], $row['color'][1], $row['color'][2]);

            // Draw Cells
            // We need to keep track of X, Y because MultiCell moves cursor
            $startX = $pdf->GetX();
            $startY = $pdf->GetY();

            // Single line cells
            $pdf->Cell($w[0], $h, $row['date'], 1, 0, 'C', 1);
            $pdf->Cell($w[1], $h, $row['day'], 1, 0, 'C', 1);

            // Status Color Logic (Red text for Absent)
            if ($row['status'] == 'Absent')
                $pdf->SetTextColor(200, 0, 0);
            elseif ($row['status'] == 'Present')
                $pdf->SetTextColor(0, 100, 0);
            else
                $pdf->SetTextColor(0);

            $pdf->Cell($w[2], $h, $row['status'], 1, 0, 'C', 1);
            $pdf->SetTextColor(0); // Reset

            $pdf->Cell($w[3], $h, $row['in'], 1, 0, 'C', 1);
            $pdf->Cell($w[4], $h, $row['out'], 1, 0, 'C', 1);
            $pdf->Cell($w[5], $h, $row['remarks'], 1, 0, 'C', 1);

            // MultiCell for Report
            $x = $pdf->GetX();
            $pdf->MultiCell($w[6], $h, $textReport, 1, 'L', 1, 1, null, null, true, 0, false, true, $h, 'M');

            // The MultiCell moved Y down by $h, but we need to ensure next row starts correctly if we used Cells before it.
            // Actually, MultiCell with ln=1 moves to next line. But the previous Cells didn't move Y!
            // Wait, standard FPDF/TCPDF method for row with MultiCell:
            // Draw MultiCell LAST. Or use manual XY.
            // Simplified approach:
            // Since we used ln=0 for others, text report is last.
            // But we need to ensure the others have same height!
            // The simple Cell() call draws a box of height $h. That's fine.
            // The MultiCell is drawing at current X, Y.
            // We just need to make sure we don't drift.

            // Ensure Y is correct for specific row logic:
            // Since we calculated $h using getNumLines, simpler Cells match height.
            // That works.
        }

        // 9. Output/Save file
        $uploadDir = __DIR__ . '/../uploads/reports';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = "Monthly_Report_{$userId}_{$month}_{$year}.pdf";
        $filePath = $uploadDir . '/' . $fileName;

        $pdf->Output($filePath, 'F'); // Save to file

        // Return URL
        $fileUrl = BASE_URL . "/uploads/reports/" . $fileName;

        return [
            'success' => true,
            'url' => $fileUrl,
            'file_path' => $filePath,
            'stats' => [
                'present' => $presentCount,
                'absent' => $absentCount,
                'working' => $workingDaysCount
            ]
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
