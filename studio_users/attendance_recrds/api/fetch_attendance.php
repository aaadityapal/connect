<?php
session_start();
require_once '../../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$status = isset($_GET['status']) ? $_GET['status'] : 'All Status';

try {
    $sql = "SELECT a.*, s.start_time AS shift_start_time, s.end_time AS shift_end_time 
            FROM attendance a
            LEFT JOIN user_shifts us 
                ON a.user_id = us.user_id 
                AND a.date >= us.effective_from 
                AND (us.effective_to IS NULL OR a.date <= us.effective_to)
            LEFT JOIN shifts s ON us.shift_id = s.id
            WHERE a.user_id = :user_id AND MONTH(a.date) = :month AND YEAR(a.date) = :year";
    
    $sql .= " ORDER BY a.date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id, 'month' => $month, 'year' => $year]);
    $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch approved leaves for the user in the selected month
    $leave_sql = "SELECT lr.start_date, lr.end_date, lt.name AS leave_name, lr.duration_type, lr.day_type, lr.time_from, lr.time_to 
                  FROM leave_request lr
                  LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                  WHERE lr.user_id = :user_id 
                  AND lr.status = 'approved' 
                  AND (
                      (MONTH(lr.start_date) = :month1 AND YEAR(lr.start_date) = :year1)
                      OR (MONTH(lr.end_date) = :month2 AND YEAR(lr.end_date) = :year2)
                  )";
    $leave_stmt = $pdo->prepare($leave_sql);
    $leave_stmt->execute([
        'user_id' => $user_id, 
        'month1'  => $month, 
        'year1'   => $year,
        'month2'  => $month, 
        'year2'   => $year
    ]);
    $leaves = $leave_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch office holidays
    $hol_sql = "SELECT holiday_date, holiday_name FROM office_holidays WHERE MONTH(holiday_date) = :month AND YEAR(holiday_date) = :year";
    $hol_stmt = $pdo->prepare($hol_sql);
    $hol_stmt->execute(['month' => $month, 'year' => $year]);
    $holidays_raw = $hol_stmt->fetchAll(PDO::FETCH_ASSOC);
    $holidays_map = [];
    foreach ($holidays_raw as $hol) {
        $holidays_map[$hol['holiday_date']] = $hol['holiday_name'];
    }
    
    $records = [];
    foreach ($all_records as $rec) {
        // Check for Short Leave of morning to waive late penalty
        $is_morning_leave_waived = false;
        foreach ($leaves as $leave) {
            if ($rec['date'] >= $leave['start_date'] && $rec['date'] <= $leave['end_date']) {
                $leave_name = strtolower($leave['leave_name'] ?? '');
                
                // Short leave check
                if (strpos($leave_name, 'short') !== false || strpos($leave_name, 'half') !== false) {
                    if (!empty($leave['time_from'])) {
                        $time_from_hour = (int)date('H', strtotime($leave['time_from']));
                        if ($time_from_hour < 13) {
                            $is_morning_leave_waived = true;
                        }
                    } else {
                        // If no time is specified, safely assume it could be morning
                        $is_morning_leave_waived = true;
                    }
                }
                
                // Explicit first half check
                if ($leave['duration_type'] === 'first_half' || $leave['day_type'] === 'first_half') {
                    $is_morning_leave_waived = true;
                }
            }
        }
        
        // Dynamic Late Calculation (15 mins tolerance)
        if (!empty($rec['punch_in'])) {
            $shift_start = !empty($rec['shift_start_time']) ? $rec['shift_start_time'] : null;
            if ($shift_start) {
                $shiftStartTimestamp = strtotime($rec['date'] . ' ' . $shift_start);
                $punchInTimestamp = strtotime($rec['date'] . ' ' . $rec['punch_in']);
                
                // > 15 minutes (900 seconds) - waived if morning short leave applied
                if ($punchInTimestamp > ($shiftStartTimestamp + 900) && !$is_morning_leave_waived) {
                    $rec['status'] = 'Late';
                } elseif ($rec['status'] !== 'Absent' && $rec['status'] !== 'Leave') {
                    $rec['status'] = 'On Time';
                }
            }
        } else {
             // Overwrite Absent with Holiday if applicable
             if (isset($holidays_map[$rec['date']])) {
                 $rec['status'] = 'Holiday';
                 $rec['remarks'] = $holidays_map[$rec['date']];
             }
        }
        
        // Apply status filter matching earlier SQL behavior
        if ($status !== 'All Status') {
            if ($rec['status'] !== $status) {
                continue;
            }
        }
        
        $records[] = $rec;
    }

    // Calculate KPIs
    $present_days = 0;
    $total_hours = 0;
    $total_minutes = 0;
    $overtime_hours = 0;
    $overtime_minutes = 0;
    $late_punches = 0;
    $leaves_taken = 0;
    
    $late_details = [];
    $leave_details = [];
    $overtime_details = [];

    // Pre-process $leaves to inject them into leave_details simply
    foreach ($leaves as $lv) {
        $leave_details[] = [
            'date' => date('d M Y', strtotime($lv['start_date'])) . ($lv['start_date'] != $lv['end_date'] ? ' to ' . date('d M Y', strtotime($lv['end_date'])) : ''),
            'type' => $lv['leave_name'] ?? 'Leave',
            'duration' => $lv['duration_type'] ?? 'full day'
        ];
    }
    
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $current_date = strtotime(date('Y-m-d'));
    
    $working_days = 0;
    for ($i = 1; $i <= $days_in_month; $i++) {
        $date_string = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-" . str_pad($i, 2, "0", STR_PAD_LEFT);
        $loop_date = strtotime($date_string);
        // exclude sunday and only evaluate till current date
        if (date('w', $loop_date) != 0 && $loop_date <= $current_date) { 
            $working_days++;
        }
    }
    if ($working_days == 0) $working_days = 1;

    $chart_data = [];

    foreach ($records as &$record) { // Reference to modify $record
        // Auto-calculate working hours if punch_in and punch_out exist but working_hours is empty
        if ((empty($record['working_hours']) || $record['working_hours'] === '00:00:00') && !empty($record['punch_in']) && !empty($record['punch_out'])) {
            $in = strtotime($record['punch_in']);
            $out = strtotime($record['punch_out']);
            if ($out > $in) {
                $diff = $out - $in;
                $h = floor($diff / 3600);
                $m = floor(($diff / 60) % 60);
                $record['working_hours'] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ':00';
            }
        }
        
        if ($record['status'] !== 'Absent' && $record['status'] !== 'Leave') {
            $present_days++;
        }
        
        if ($record['status'] === 'Leave') {
            $leaves_taken++;
        }
        
        if ($record['status'] === 'Late') {
            $late_punches++;
            $late_details[] = [
                'date' => date('d/m/Y', strtotime($record['date'])),
                'punch_in' => date('h:i A', strtotime($record['punch_in'])),
                'shift_start' => !empty($record['shift_start_time']) ? date('h:i A', strtotime($record['shift_start_time'])) : 'N/A'
            ];
        }
        
        // Sum working hours
        if (!empty($record['working_hours'])) {
            $parts = explode(':', $record['working_hours']);
            if (count($parts) >= 2) {
                $total_hours += (int)$parts[0];
                $total_minutes += (int)$parts[1];
            }
            
            // For chart
            $day = (int)date('d', strtotime($record['date']));
            $hours_dec = (int)$parts[0] + ((int)$parts[1] / 60);
            $chart_data[] = [
                'day' => $day,
                'hours' => $hours_dec,
                'date' => date('d/m', strtotime($record['date']))
            ];
        }
        
        // Dynamic Overtime logic
        if (!empty($record['punch_out']) && !empty($record['shift_end_time'])) {
            $shift_endTimestamp = strtotime($record['date'] . ' ' . $record['shift_end_time']);
            $punchOutTimestamp = strtotime($record['date'] . ' ' . $record['punch_out']);
            
            if ($punchOutTimestamp > $shift_endTimestamp) {
                $diff_minutes = floor(($punchOutTimestamp - $shift_endTimestamp) / 60);
                if ($diff_minutes >= 90) { // >= 1 hour 30 minutes
                    $overtime_mins_rounded = floor($diff_minutes / 30) * 30; // 90, 120, 150...
                    $h = floor($overtime_mins_rounded / 60);
                    $m = $overtime_mins_rounded % 60;
                    
                    $record['overtime_hours'] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ':00';
                    
                    $overtime_details[] = [
                        'date' => date('d/m/Y', strtotime($record['date'])),
                        'punch_out' => date('h:i A', strtotime($record['punch_out'])),
                        'overtime_hours' => $h . ' hour ' . ($m > 0 ? $m . ' minutes' : '')
                    ];
                } else {
                     $record['overtime_hours'] = '00:00:00';
                }
            } else {
                 $record['overtime_hours'] = '00:00:00';
            }
        }
        
        // Sum overtime hours
        if (!empty($record['overtime_hours']) && $record['overtime_hours'] !== '00:00:00') {
            $parts = explode(':', $record['overtime_hours']);
            if (count($parts) >= 2) {
                $overtime_hours += (int)$parts[0];
                $overtime_minutes += (int)$parts[1];
            }
        }
    }
    
    // Adjust minutes to hours
    $total_hours += floor($total_minutes / 60);
    $total_minutes = $total_minutes % 60;
    
    $overtime_hours += floor($overtime_minutes / 60);
    $overtime_minutes = $overtime_minutes % 60;
    
    $attendance_rate = round(($present_days / $working_days) * 100);
    if ($attendance_rate > 100) $attendance_rate = 100;
    
    $kpis = [
        'present_days' => $present_days,
        'total_hours' => str_pad($total_hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($total_minutes, 2, '0', STR_PAD_LEFT),
        'overtime_hours' => str_pad($overtime_hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($overtime_minutes, 2, '0', STR_PAD_LEFT),
        'attendance_rate' => $attendance_rate,
        'late_punches' => $late_punches,
        'late_details' => $late_details,
        'leaves_taken' => $leaves_taken,
        'leave_details' => $leave_details,
        'overtime_details' => $overtime_details
    ];
    
    // Sort chart data ascending by day
    usort($chart_data, function($a, $b) {
        return $a['day'] <=> $b['day'];
    });
    
    $final_chart = array_slice($chart_data, -7);
    
    echo json_encode([
        'success' => true, 
        'data' => $records, 
        'kpis' => $kpis,
        'chart_data' => $final_chart
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
