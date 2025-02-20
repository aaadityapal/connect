<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start with a clean output buffer
ob_start();

require_once 'config/db_connect.php';

// Add function to calculate overtime rate
function calculateOvertimeRate($baseSalary, $workingDays, $shiftHours) {
    if ($workingDays <= 0 || $shiftHours <= 0) return 0;
    return ($baseSalary / ($workingDays * $shiftHours)) * 2;
}

function getDayNumber($dayName) {
    $days = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7
    ];
    return $days[strtolower(trim($dayName))] ?? null;
}

function calculateWorkingDays($start_date, $end_date, $user_id) {
    global $conn;
    
    try {
        // Get user's weekly offs
        $query = "SELECT weekly_offs FROM user_shifts WHERE user_id = ? 
                  AND (effective_to IS NULL OR effective_to >= ?) 
                  AND effective_from <= ?
                  ORDER BY effective_from DESC LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $weekly_offs = $row ? $row['weekly_offs'] : '';
        
        // Convert weekly offs string to array of day numbers
        $weekly_off_days = [];
        if (!empty($weekly_offs)) {
            $weekly_off_days = array_map('getDayNumber', explode(',', $weekly_offs));
            $weekly_off_days = array_filter($weekly_off_days); // Remove null values
        }
        
        // Get holidays between dates
        $holiday_query = "SELECT date FROM holidays WHERE date BETWEEN ? AND ?";
        $stmt = $conn->prepare($holiday_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $holidays = [];
        while ($row = $result->fetch_assoc()) {
            $holidays[] = $row['date'];
        }
        
        // Calculate working days
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($start, $interval, $end->modify('+1 day'));
        
        $working_days = 0;
        foreach ($daterange as $date) {
            // Skip if it's a weekly off
            if (in_array($date->format('N'), $weekly_off_days)) {
                continue;
            }
            
            // Skip if it's a holiday
            if (in_array($date->format('Y-m-d'), $holidays)) {
                continue;
            }
            
            $working_days++;
        }
        
        return [
            'working_days' => $working_days,
            'holidays' => count($holidays),
            'weekly_offs' => count($weekly_off_days)
        ];
    } catch (Exception $e) {
        error_log("Error in calculateWorkingDays: " . $e->getMessage());
        return ['working_days' => 0, 'holidays' => 0, 'weekly_offs' => 0];
    }
}

// Turn off output buffering and clear any previous output
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Validate input dates
$start_date = isset($_GET['start']) ? $_GET['start'] : null;
$end_date = isset($_GET['end']) ? $_GET['end'] : null;

if (!$start_date || !$end_date) {
    throw new Exception('Start and end dates are required');
}

try {
    $query = "SELECT 
        users.id,
        users.username,
        users.base_salary,
        COALESCE(users.overtime_rate, 0) as overtime_rate,
        us.weekly_offs,
        TIMESTAMPDIFF(HOUR, s.start_time, s.end_time) as shift_hours,
        COUNT(DISTINCT CASE 
            WHEN a.status = 'present' 
            AND DATE(a.date) BETWEEN ? AND ?
            THEN DATE(a.date) 
        END) as present_days,
        (
            SELECT COUNT(DISTINCT DATE(att.date))
            FROM attendance att
            WHERE att.user_id = users.id
            AND att.status = 'present'
            AND DATE(att.date) BETWEEN ? AND ?
            AND TIME(att.punch_in) > ADDTIME(s.start_time, '00:15:00')
        ) as late_days,
        COALESCE((
            SELECT SUM(duration)
            FROM leave_request
            WHERE user_id = users.id
            AND status = 'approved'
            AND hr_approval = 'approved'
            AND manager_approval = 'approved'
            AND (
                (start_date BETWEEN ? AND ?) OR
                (end_date BETWEEN ? AND ?) OR
                (start_date <= ? AND end_date >= ?)
            )
        ), 0) as leaves_taken,
        COALESCE((
            SELECT CONCAT(
                FLOOR(SUM(
                    CASE 
                        WHEN TIME_TO_SEC(overtime_hours) >= (90 * 60)
                        THEN TIME_TO_SEC(overtime_hours)
                        ELSE 0 
                    END
                )/3600),
                ':',
                LPAD(FLOOR(MOD(
                    SUM(
                        CASE 
                            WHEN TIME_TO_SEC(overtime_hours) >= (90 * 60)
                            THEN TIME_TO_SEC(overtime_hours)
                            ELSE 0 
                        END
                    ), 3600)/60), 2, '0')
            )
        ), '0:00') as overtime_hours
        FROM users 
        LEFT JOIN user_shifts us ON users.id = us.user_id 
            AND (us.effective_to IS NULL OR us.effective_to >= ?)
            AND us.effective_from <= ?
        LEFT JOIN shifts s ON us.shift_id = s.id
        LEFT JOIN attendance a ON users.id = a.user_id
        WHERE users.status = 'active' 
        AND users.deleted_at IS NULL 
        GROUP BY users.id, users.username, users.base_salary
        ORDER BY users.username";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Fix: Correct number of parameters (12 pairs of dates)
    $stmt->bind_param('ssssssssssss', 
        $start_date, $end_date,      // For present days count
        $start_date, $end_date,      // For late days count
        $start_date, $end_date,      // For leaves start date range
        $start_date, $end_date,      // For leaves end date range
        $start_date, $end_date,      // For leaves spanning entire range
        $start_date, $end_date       // For shift effective dates
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $workingDaysInfo = calculateWorkingDays($start_date, $end_date, $row['id']);
        
        // Base salary (ensure it's a number)
        $base_salary = floatval($row['base_salary']);
        
        // Get actual working days for the date range (Feb 1-12)
        $working_days = (int)$workingDaysInfo['working_days'];
        
        // Calculate per day salary based on standard month (assuming 30 days)
        $perDaySalary = $base_salary / 30;
        
        // Calculate monthly salary based on present days
        $present_days = intval($row['present_days'] ?? 0);
        $monthSalary = $perDaySalary * $present_days;

        // Calculate overtime with safety checks
        $shift_hours = max(floatval($row['shift_hours'] ?: 8), 1); // Standard 8-hour shift
        $overtimeRate = ($base_salary / (30 * $shift_hours)) * 2; // Double rate for overtime

        // Parse overtime hours
        $overtime_hours = $row['overtime_hours'] ?: '0:00';
        list($hours, $minutes) = explode(':', $overtime_hours);
        $decimal_hours = floatval($hours) + (floatval($minutes) / 60);
        $overtimeAmount = $decimal_hours * $overtimeRate;
        
        // Calculate late deduction (0.5 day salary for every 3 late days)
        $lateDays = intval($row['late_days'] ?? 0);
        $deductionDays = floor($lateDays / 3); // Every 3 late days = 0.5 day salary deduction
        $lateDeduction = $deductionDays * ($perDaySalary * 0.5);
        
        // Calculate total salary
        $totalSalary = $monthSalary + $overtimeAmount - $lateDeduction;
        
        $data[] = [
            'Employee_Name' => trim($row['username']),
            'Base_Salary' => number_format($base_salary, 2, '.', ''),
            'Working_Days' => $working_days,
            'Present_Days' => $present_days,
            'Late_Days' => $lateDays,
            'Late_Deduction' => number_format($lateDeduction, 2, '.', ''),
            'Leaves_Taken' => number_format(floatval($row['leaves_taken'] ?? 0), 1, '.', ''),
            'Monthly_Salary' => number_format($monthSalary, 2, '.', ''),
            'Overtime_Hours' => $overtime_hours,
            'Overtime_Rate' => number_format($overtimeRate, 2, '.', ''),
            'Overtime_Amount' => number_format($overtimeAmount, 2, '.', ''),
            'Total_Salary' => number_format($totalSalary, 2, '.', '')
        ];
    }

    // Before outputting JSON, clean the buffer
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output the JSON with proper encoding options
    echo json_encode($data, 
        JSON_NUMERIC_CHECK | 
        JSON_UNESCAPED_UNICODE | 
        JSON_UNESCAPED_SLASHES | 
        JSON_PRESERVE_ZERO_FRACTION
    );

} catch (Exception $e) {
    // Clean output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set error headers
    header('Content-Type: application/json');
    header('HTTP/1.1 500 Internal Server Error');

    // Return error message
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'trace' => DEBUG ? $e->getTraceAsString() : null
    ]);
}

// Ensure no additional output
exit; 