<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tcpdf/tcpdf.php';

// Ensure India Timezone
date_default_timezone_set('Asia/Kolkata');

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
        // 6. Fetch Shift History (To support shift changes mid-month)
        $shiftStmt = $pdo->prepare("SELECT us.effective_from, us.effective_to, us.weekly_offs, s.start_time 
                                   FROM user_shifts us
                                   JOIN shifts s ON us.shift_id = s.id
                                   WHERE us.user_id = ? 
                                   ORDER BY us.effective_from DESC");
        $shiftStmt->execute([$userId]);
        $allUserShifts = $shiftStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback or Current Settings (for Weekly Offs logic mainly)
        $latestShift = !empty($allUserShifts) ? $allUserShifts[0] : null;

        // Note: Weekly Offs might ALSO change mid-month. 
        // For simplicity in the loop, we might need to check weekly_offs dynamically too. 
        // But for now, let's keep array of weekly offs dynamic if possible, or fallback to latest.
        // Let's make weeklyOffs dynamic in the loop if we want perfect accuracy.
        // For now, let's stick to the request about Start Time logic.
        $weeklyOffs = isset($latestShift['weekly_offs']) ? array_map('trim', explode(',', $latestShift['weekly_offs'])) : [];
        $shiftStartTime = null; // Deprecated variable, logic moved inside loop

        // 7. Calculate Stats & Prepare Rows
        $presentCount = 0;
        $absentCount = 0;
        $workingDaysCount = 0;
        $holidayCount = 0;
        $weeklyOffCount = 0;
        $leaveCount = 0;
        $reportData = [];

        // Track detailed information for summary sections
        $holidayDetails = []; // [date => name]
        $weeklyOffDetails = []; // [date => day_name]
        $leaveDetails = []; // [date => leave_info]

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
                $holidayDetails[$currentDate] = $holidays[$currentDate];
            }
            // Weekly Off
            // Weekly Off
            elseif (in_array($dayName, $weeklyOffs)) {
                // Check if user worked on Weekly Off
                if (isset($attendanceRecords[$currentDate])) {
                    $att = $attendanceRecords[$currentDate];

                    // Verify actual attendance exists (not just empty record)
                    $hasValidPunchIn = !empty($att['punch_in'])
                        && $att['punch_in'] != '0000-00-00 00:00:00'
                        && $att['punch_in'] != '00:00:00';

                    if ($hasValidPunchIn) {
                        // User actually worked on weekly off
                        $status = '(Weekly Off) Present';
                        $presentCount++; // Count as present
                        $rowColor = [255, 255, 255]; // Normal Present Color

                        $inTime = '-';
                        // Robust Time Check (Weekly Off)
                        $ts = strtotime($att['punch_in']);
                        if ($ts && date('Y', $ts) > 1970) {
                            $inTime = date('H:i', $ts);
                        } elseif (strlen($att['punch_in']) >= 5) {
                            $inTime = substr($att['punch_in'], 0, 5);
                        }

                        $outTime = '-';
                        if (!empty($att['punch_out']) && $att['punch_out'] != '0000-00-00 00:00:00' && $att['punch_out'] != '00:00:00') {
                            $ts = strtotime($att['punch_out']);
                            if ($ts && date('Y', $ts) > 1970) {
                                $outTime = date('H:i', $ts);
                            } elseif (strlen($att['punch_out']) >= 5) {
                                $outTime = substr($att['punch_out'], 0, 5);
                            }
                        }
                        $report = $att['work_report'];

                        // Late Check for Weekly Off (same logic as working days)
                        $punchInStr = (strlen($att['punch_in']) <= 8) ? $currentDate . ' ' . $att['punch_in'] : $att['punch_in'];
                        $punchInTs = strtotime($punchInStr);

                        $currentDayShiftStart = null;

                        // Find applicable shift for this specific date
                        foreach ($allUserShifts as $us) {
                            $effFrom = strtotime($us['effective_from']);
                            $effTo = $us['effective_to'] ? strtotime($us['effective_to']) : strtotime('2099-12-31');
                            $currDateTs = strtotime($currentDate);

                            if ($currDateTs >= $effFrom && $currDateTs <= $effTo) {
                                $currentDayShiftStart = $us['start_time'];
                                break;
                            }
                        }

                        if (!$currentDayShiftStart && !empty($allUserShifts)) {
                            $currentDayShiftStart = $allUserShifts[0]['start_time'];
                        }

                        if ($currentDayShiftStart) {
                            $dayShiftStart = strtotime($currentDate . ' ' . $currentDayShiftStart);

                            // Grace Period: 15 minutes inclusive.
                            $graceTime = $dayShiftStart + (15 * 60) + 59;

                            if ($punchInTs > $graceTime) {
                                $remarks = 'Late In';
                                $rowColor = [255, 249, 196]; // Yellow for late
                            }
                        }
                    } else {
                        // Empty attendance record on weekly off
                        $status = 'Weekly Off';
                        $rowColor = [240, 240, 240]; // Light Grey
                        $weeklyOffCount++;
                        $weeklyOffDetails[$currentDate] = $dayName;
                    }
                } else {
                    $status = 'Weekly Off';
                    $rowColor = [240, 240, 240]; // Light Grey
                    $weeklyOffCount++;
                    $weeklyOffDetails[$currentDate] = $dayName;
                }
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

                    // Robust Time Check (Working Day)
                    if (!empty($att['punch_in']) && $att['punch_in'] != '0000-00-00 00:00:00' && $att['punch_in'] != '00:00:00') {
                        $ts = strtotime($att['punch_in']);
                        if ($ts && date('Y', $ts) > 1970) {
                            $inTime = date('H:i', $ts);
                        } elseif (strlen($att['punch_in']) >= 5) {
                            $inTime = substr($att['punch_in'], 0, 5);
                        }
                    }

                    $outTime = '-';
                    if (!empty($att['punch_out']) && $att['punch_out'] != '0000-00-00 00:00:00' && $att['punch_out'] != '00:00:00') {
                        $ts = strtotime($att['punch_out']);
                        if ($ts && date('Y', $ts) > 1970) {
                            $outTime = date('H:i', $ts);
                        } elseif (strlen($att['punch_out']) >= 5) {
                            $outTime = substr($att['punch_out'], 0, 5);
                        }
                    }
                    $report = $att['work_report'];

                    // Late Check
                    // Fix: Combine Date + Time to avoid "Today" default
                    $punchInStr = (strlen($att['punch_in']) <= 8) ? $currentDate . ' ' . $att['punch_in'] : $att['punch_in'];
                    $punchInTs = strtotime($punchInStr);

                    $currentDayShiftStart = null;

                    // Find applicable shift for this specific date
                    foreach ($allUserShifts as $us) {
                        $effFrom = strtotime($us['effective_from']);
                        $effTo = $us['effective_to'] ? strtotime($us['effective_to']) : strtotime('2099-12-31');
                        $currDateTs = strtotime($currentDate);

                        if ($currDateTs >= $effFrom && $currDateTs <= $effTo) {
                            $currentDayShiftStart = $us['start_time'];
                            break;
                        }
                    }

                    if (!$currentDayShiftStart && !empty($allUserShifts)) {
                        $currentDayShiftStart = $allUserShifts[0]['start_time'];
                    }

                    if ($currentDayShiftStart) {
                        $dayShiftStart = strtotime($currentDate . ' ' . $currentDayShiftStart);

                        // Grace Period: 15 minutes inclusive.
                        $graceTime = $dayShiftStart + (15 * 60) + 59;

                        if ($punchInTs > $graceTime) {
                            $remarks = 'Late In';
                            $rowColor = [255, 249, 196];
                        }
                    }
                }
                // Check Leave
                elseif (isset($leaveDays[$currentDate])) {
                    $status = 'Leave';
                    $remarks = $leaveDays[$currentDate]; // Show Leave Name (e.g., "Sick Leave")
                    $rowColor = [225, 245, 254]; // Light Blue
                    $leaveDetails[$currentDate] = $leaveDays[$currentDate];
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
                    // Check if this is a future date
                    $today = date('Y-m-d');
                    if ($currentDate > $today) {
                        // Future date - not yet occurred
                        $status = 'Upcoming';
                        $rowColor = [250, 250, 250]; // Very Light Grey
                        $workingDaysCount--; // Don't count future days as working days
                    } else {
                        // Past date with no attendance - truly absent
                        $absentCount++;
                    }
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
        $pdf->Ln(5);

        // ========== DETAILED SECTIONS ==========

        // 1. Holidays Detail
        if (!empty($holidayDetails)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(240, 248, 255);
            $pdf->Cell(0, 7, 'Holidays:', 0, 1, 'L', 1);
            $pdf->SetFont('helvetica', '', 9);
            foreach ($holidayDetails as $date => $holidayName) {
                $formattedDate = date('d M Y (l)', strtotime($date));
                $pdf->Cell(10, 5, '•', 0, 0, 'L');
                $pdf->Cell(0, 5, "$formattedDate - $holidayName", 0, 1, 'L');
            }
            $pdf->Ln(3);
        }

        // 2. Weekly Offs Taken Detail
        if (!empty($weeklyOffDetails)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(0, 7, 'Weekly Offs Taken:', 0, 1, 'L', 1);
            $pdf->SetFont('helvetica', '', 9);
            foreach ($weeklyOffDetails as $date => $dayName) {
                $formattedDate = date('d M Y', strtotime($date));
                $pdf->Cell(10, 5, '•', 0, 0, 'L');
                $pdf->Cell(0, 5, "$formattedDate ($dayName)", 0, 1, 'L');
            }
            $pdf->Ln(3);
        }

        // 3. Leaves Detail (with leave type and duration)
        if (!empty($leaveDetails)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(225, 245, 254);
            $pdf->Cell(0, 7, 'Leaves Taken:', 0, 1, 'L', 1);
            $pdf->SetFont('helvetica', '', 9);

            // Group consecutive leave dates by leave type
            $leaveGroups = [];
            foreach ($leaves as $leave) {
                $leaveType = $leave['leave_name'];
                $startDate = $leave['start_date'];
                $endDate = $leave['end_date'];
                $reason = $leave['reason'];

                // Calculate days in this month
                $current = strtotime($startDate);
                $end = strtotime($endDate);
                $daysInLeave = 0;

                while ($current <= $end) {
                    $d = date('Y-m-d', $current);
                    if (date('m', $current) == $month && date('Y', $current) == $year) {
                        $daysInLeave++;
                    }
                    $current = strtotime('+1 day', $current);
                }

                $leaveGroups[] = [
                    'type' => $leaveType,
                    'start' => $startDate,
                    'end' => $endDate,
                    'days' => $daysInLeave,
                    'reason' => $reason
                ];
            }

            foreach ($leaveGroups as $leaveGroup) {
                $startFormatted = date('d M Y', strtotime($leaveGroup['start']));
                $endFormatted = date('d M Y', strtotime($leaveGroup['end']));
                $dayText = $leaveGroup['days'] == 1 ? 'day' : 'days';

                if ($leaveGroup['start'] == $leaveGroup['end']) {
                    $dateRange = $startFormatted;
                } else {
                    $dateRange = "$startFormatted to $endFormatted";
                }

                $pdf->Cell(10, 5, '•', 0, 0, 'L');
                $pdf->MultiCell(0, 5, "{$leaveGroup['type']} - $dateRange ({$leaveGroup['days']} $dayText)", 0, 'L');

                if (!empty($leaveGroup['reason'])) {
                    $pdf->Cell(15, 5, '', 0, 0, 'L'); // Indent
                    $pdf->SetFont('helvetica', 'I', 8);
                    $pdf->MultiCell(0, 4, "Reason: {$leaveGroup['reason']}", 0, 'L');
                    $pdf->SetFont('helvetica', '', 9);
                }
            }
            $pdf->Ln(3);
        }

        $pdf->Ln(3);

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
