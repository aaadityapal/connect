<?php
require_once 'config/db_connect.php';

// Validate and set selected month
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get the selected role filter
$selected_role = isset($_GET['role']) ? $_GET['role'] : 'all';

// Validate the format of selected month (ensure it's YYYY-MM format)
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = date('Y-m');
}

// Ensure the month value is properly sanitized
$selected_month = htmlspecialchars($selected_month, ENT_QUOTES, 'UTF-8');

// Validate the month is within reasonable range
$selected_date = new DateTime($selected_month . '-01');
$min_date = new DateTime('-2 years');
$max_date = new DateTime('+2 years');

if ($selected_date < $min_date || $selected_date > $max_date) {
    $selected_month = date('Y-m');
}

$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Add these functions from test_working_days.php
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
    return $days[strtolower($dayName)] ?? null;
}

function calculateWorkingDays($monthStart, $monthEnd, $userId) {
    global $conn;
    
    $query = "SELECT 
        DAY(LAST_DAY(?)) - 
        (
            SELECT COUNT(*)
            FROM (
                SELECT DATE_ADD(?, INTERVAL n-1 DAY) as date
                FROM (
                    SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
                    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
                    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
                    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
                    UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25
                    UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
                    UNION SELECT 31
                ) numbers
                WHERE n <= DAY(LAST_DAY(?))
            ) dates
            LEFT JOIN user_shifts us ON us.user_id = ?
                AND us.effective_from <= dates.date
                AND (us.effective_to IS NULL OR us.effective_to >= dates.date)
            LEFT JOIN office_holidays oh ON dates.date = oh.holiday_date
            WHERE DAYOFWEEK(dates.date) = CAST(
                CASE LOWER(us.weekly_offs)
                    WHEN 'monday' THEN 2
                    WHEN 'tuesday' THEN 3
                    WHEN 'wednesday' THEN 4
                    WHEN 'thursday' THEN 5
                    WHEN 'friday' THEN 6
                    WHEN 'saturday' THEN 7
                    WHEN 'sunday' THEN 1
                END AS UNSIGNED
            )
            OR oh.holiday_date IS NOT NULL
        ) as working_days";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssi', $monthStart, $monthStart, $monthStart, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_array();
    
    return [
        'total_days' => date('t', strtotime($monthStart)),
        'off_days' => $row[0],
        'working_days' => $row[0]
    ];
}

// Add this function to calculate leave deductions
function calculateLeaveDeductions($leavesTaken, $perDaySalary) {
    if (empty($leavesTaken)) {
        return 0;
    }

    $deduction = 0;
    $leaves = explode("\n", $leavesTaken);
    
    // Initialize counters
    $casualLeaveCount = 0;
    $shortLeaveCount = 0;
    $compensateLeaveCount = 0;
    $compensateLeaveAllowed = 0;
    $halfDayLeaveCount = 0;  // New counter for half day leaves
    
    // First pass: Count leaves by type
    foreach ($leaves as $leave) {
        if (preg_match('/^(.*?): (\d+)\/(\d+)/', $leave, $matches)) {
            $leaveType = $matches[1];
            $taken = (int)$matches[2];
            $allowed = (int)$matches[3];

            if ($leaveType === 'C.L.') {
                $casualLeaveCount += $taken;
            } elseif ($leaveType === 'SH.L.') {
                $shortLeaveCount += $taken;
            } elseif ($leaveType === 'CO.L.') {
                $compensateLeaveCount += $taken;
                $compensateLeaveAllowed = max($compensateLeaveAllowed, $allowed);
            } elseif ($leaveType === 'U.L.') {
                $deduction += ($perDaySalary * $taken);
            } elseif ($leaveType === 'H.L.') {  // New condition for half day leave
                $deduction += ($perDaySalary * 0.5 * $taken);  // Deduct half day salary for each H.L.
            }
        }
    }
    
    // Apply deductions based on total counts
    // Casual Leave: deduct if total is more than 1 per month
    if ($casualLeaveCount > 1) {
        $deduction += $perDaySalary * ($casualLeaveCount - 1);
    }
    
    // Short Leave: deduct half day salary if more than 2
    if ($shortLeaveCount > 2) {
        $deduction += ($perDaySalary * 0.5) * ($shortLeaveCount - 2);
    }
    
    // Compensate Leave: deduct if total exceeds allowed
    if ($compensateLeaveCount > $compensateLeaveAllowed) {
        $deduction += $perDaySalary * ($compensateLeaveCount - $compensateLeaveAllowed);
    }
    
    return $deduction;
}

// Modify the main query to include role filtering
$query = "WITH user_leaves AS (
    SELECT 
        lr.user_id,
        CONCAT(
            CASE lt.id
                WHEN '2' THEN 'S.L.'  -- Sick Leave
                WHEN '3' THEN 
                    CASE 
                        WHEN SUM(lr.duration) > 1 THEN 'C.L.'
                        ELSE 'C.L.'   -- Casual Leave
                    END
                WHEN '4' THEN 'E.L.'  -- Emergency Leave
                WHEN '5' THEN 'M.L.'  -- Maternity Leave
                WHEN '6' THEN 'P.L.'  -- Paternity Leave
                WHEN '11' THEN 'SH.L.' -- Short Leave
                WHEN '12' THEN 'CO.L.' -- Compensate Leave
                WHEN '13' THEN 'U.L.'  -- Unpaid Leave
                WHEN '14' THEN 'H.L.'  -- Half Day Leave
                ELSE CONCAT('Type ', lt.id)
            END,
            ': ',
            CASE 
                WHEN lt.id = '11' THEN COUNT(*)
                WHEN lt.id = '14' THEN COUNT(*)  -- Count number of half day leaves
                ELSE SUM(lr.duration)
            END,
            '/',
            CASE 
                WHEN lt.id = '3' THEN 1          -- Casual Leave: 1 per month
                WHEN lt.id = '11' THEN 2         -- Short Leave: 2 per month
                WHEN lt.id = '14' THEN 30        -- Half Day Leave: no limit (using 30 as max)
                WHEN lt.id = '12' THEN (         -- Compensate Leave: based on worked weekly offs
                    SELECT COUNT(*) 
                    FROM attendance a2
                    JOIN user_shifts us3 ON a2.user_id = us3.user_id
                        AND a2.date >= us3.effective_from
                        AND (us3.effective_to IS NULL OR a2.date <= us3.effective_to)
                    WHERE a2.user_id = lr.user_id 
                    AND a2.status = 'present'
                    AND DAYNAME(a2.date) = us3.weekly_offs
                    AND YEAR(a2.date) = YEAR(CURRENT_DATE())
                )
                ELSE lt.max_days
            END,
            ' days'
        ) as leave_info
    FROM leave_request lr
    JOIN leave_types lt ON lt.id = lr.leave_type
    WHERE lr.status = 'approved'
    AND lr.hr_approval = 'approved'
    AND lr.manager_approval = 'approved'
    AND (
        (lr.start_date BETWEEN ? AND ?) OR
        (lr.end_date BETWEEN ? AND ?) OR
        (lr.start_date <= ? AND lr.end_date >= ?)
    )
    GROUP BY lr.user_id, lt.id
)

SELECT 
    users.id, 
    users.username,
    users.role,
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
        INNER JOIN user_shifts us2 ON us2.user_id = att.user_id
            AND att.date >= us2.effective_from 
            AND (us2.effective_to IS NULL OR att.date <= us2.effective_to)
        INNER JOIN shifts s2 ON s2.id = us2.shift_id
        WHERE att.user_id = users.id
        AND att.status = 'present'
        AND DATE(att.date) BETWEEN ? AND ?
        AND TIME(att.punch_in) > ADDTIME(s2.start_time, '00:15:00')
    ) as late_days,
    (
        SELECT GROUP_CONCAT(leave_info SEPARATOR '\n')
        FROM user_leaves
        WHERE user_id = users.id
    ) as leaves_taken,
    (
        SELECT CONCAT(
            FLOOR(SUM(
                CASE 
                    -- Only count time after shift's end time as overtime, rounded to nearest 30 min
                    WHEN att.punch_out IS NOT NULL AND TIME(att.punch_out) > s2.end_time AND TIME_TO_SEC(TIMEDIFF(TIME(att.punch_out), s2.end_time)) >= (90 * 60)
                    THEN 
                        -- Round to nearest 30 minute increment (0 or 30)
                        FLOOR(TIME_TO_SEC(TIMEDIFF(TIME(att.punch_out), s2.end_time)) / 1800) * 1800
                    ELSE 0 
                END
            )/3600),
            ':',
            -- The minutes will now always be either 00 or 30
            CASE 
                WHEN FLOOR(MOD(
                    SUM(
                        CASE 
                            WHEN att.punch_out IS NOT NULL AND TIME(att.punch_out) > s2.end_time AND TIME_TO_SEC(TIMEDIFF(TIME(att.punch_out), s2.end_time)) >= (90 * 60)
                            THEN 
                                FLOOR(TIME_TO_SEC(TIMEDIFF(TIME(att.punch_out), s2.end_time)) / 1800) * 1800
                            ELSE 0 
                        END
                    ), 3600)/60) = 30 THEN '30'
                ELSE '00'
            END
        )
        FROM attendance att
        INNER JOIN user_shifts us2 ON us2.user_id = att.user_id
            AND att.date >= us2.effective_from 
            AND (us2.effective_to IS NULL OR att.date <= us2.effective_to)
        INNER JOIN shifts s2 ON s2.id = us2.shift_id
        WHERE att.user_id = users.id
        AND DATE(att.date) BETWEEN ? AND ?
        AND att.status = 'present'
    ) as overtime_hours
FROM users 
LEFT JOIN user_shifts us ON users.id = us.user_id 
    AND (us.effective_to IS NULL OR us.effective_to >= ?)
    AND us.effective_from <= ?
LEFT JOIN shifts s ON us.shift_id = s.id
LEFT JOIN attendance a ON users.id = a.user_id
WHERE users.deleted_at IS NULL 
AND (users.status = 'active' OR 
    (users.status = 'inactive' AND 
     DATE_FORMAT(users.status_changed_date, '%Y-%m') >= ?))";

// Add role filter condition if a specific role is selected
if ($selected_role !== 'all') {
    if ($selected_role === 'site_supervisor') {
        $query .= " AND users.role = 'Site Supervisor'";
    } elseif ($selected_role === 'except_site_supervisor') {
        $query .= " AND (users.role != 'Site Supervisor' OR users.role IS NULL)";
    }
}

$query .= " GROUP BY users.id, users.username, users.base_salary
ORDER BY users.username";

$stmt = $conn->prepare($query);

// Create array of parameters
$params = array();
$params[0] = ''; // Type string placeholder

// Add all parameters in order
$params[] = $month_start;  // Leave subquery first range (2)
$params[] = $month_end;
$params[] = $month_start;  // Leave subquery second range (2)
$params[] = $month_end;
$params[] = $month_start;  // Leave subquery third range (2)
$params[] = $month_end;
$params[] = $month_start;  // Present days (2)
$params[] = $month_end;
$params[] = $month_start;  // Late days (2)
$params[] = $month_end;
$params[] = $month_start;  // Overtime hours (2)
$params[] = $month_end;
$params[] = $month_end;    // Effective dates (2)
$params[] = $month_start;
$params[] = $selected_month;    // Add this new parameter for the status_changed_date condition

// Build the type string based on actual parameter count
$params[0] = str_repeat('s', count($params) - 1);

// Convert to references
$refs = array();
$refs[0] = &$params[0]; // Type string reference

for($i = 1; $i < count($params); $i++) {
    $refs[$i] = &$params[$i];
}

// Debug output
echo "Number of parameters: " . (count($refs) - 1) . "\n";
echo "Type string length: " . strlen($refs[0]) . "\n";

// Bind parameters
call_user_func_array([$stmt, 'bind_param'], $refs);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// After fetching users, get any existing penalty data for this month
$penalties = [];
$penaltyQuery = "SELECT user_id, penalty_days, leave_penalty_days FROM salary_penalties WHERE penalty_month = ?";
$penaltyStmt = $conn->prepare($penaltyQuery);
$penaltyStmt->bind_param('s', $selected_month);
$penaltyStmt->execute();
$penaltyResult = $penaltyStmt->get_result();

while ($row = $penaltyResult->fetch_assoc()) {
    $penalties[$row['user_id']] = [
        'penalty_days' => $row['penalty_days'],
        'leave_penalty_days' => $row['leave_penalty_days'] ?? 0
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['salary'] as $userId => $data) {
        $baseSalary = $data['base_salary'] ?? 0;
        $overtimeRate = $data['overtime_rate'] ?? 0;
        $penaltyDays = isset($data['penalty_days']) ? (float)$data['penalty_days'] : 0;
        $leavePenaltyDays = isset($data['leave_penalty_days']) ? (float)$data['leave_penalty_days'] : 0;
        
        // Update user's base salary and overtime rate
        $updateQuery = "UPDATE users 
                       SET base_salary = ?, 
                           overtime_rate = ?
                       WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ddi', $baseSalary, $overtimeRate, $userId);
        $stmt->execute();
        
        // If penalty days are set, record them in a separate table for reference
        if ($penaltyDays > 0 || $leavePenaltyDays > 0) {
            // Check if we already have a penalty record for this month/user
            $checkQuery = "SELECT id FROM salary_penalties 
                          WHERE user_id = ? AND penalty_month = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('is', $userId, $selected_month);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $penaltyId = $result->fetch_assoc()['id'];
                $updatePenaltyQuery = "UPDATE salary_penalties 
                                      SET penalty_days = ?,
                                          leave_penalty_days = ?
                                      WHERE id = ?";
                $updateStmt = $conn->prepare($updatePenaltyQuery);
                $updateStmt->bind_param('ddi', $penaltyDays, $leavePenaltyDays, $penaltyId);
                $updateStmt->execute();
            } else {
                // Insert new record
                $insertQuery = "INSERT INTO salary_penalties 
                               (user_id, penalty_month, penalty_days, leave_penalty_days) 
                               VALUES (?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param('isdd', $userId, $selected_month, $penaltyDays, $leavePenaltyDays);
                $insertStmt->execute();
            }
        }
    }
    
    header('Location: salary_info.php?success=1&month=' . urlencode($selected_month) . '&role=' . urlencode($selected_role));
    exit;
}

// Function to calculate overtime rate
function calculateOvertimeRate($baseSalary, $totalWorkingDays, $shiftHours) {
    error_log("Calculate Overtime Rate - Input values:");
    error_log("Base Salary: $baseSalary");
    error_log("Total Working Days: $totalWorkingDays");
    error_log("Shift Hours: $shiftHours");
    
    if ($totalWorkingDays <= 0 || $shiftHours <= 0) {
        error_log("Warning: Invalid working days ($totalWorkingDays) or shift hours ($shiftHours)");
        return 0;
    }
    
    $perDaySalary = $baseSalary / $totalWorkingDays;
    $perHourSalary = $perDaySalary / $shiftHours;
    
    error_log("Per Day Salary: $perDaySalary");
    error_log("Per Hour Salary: $perHourSalary");
    
    return round($perHourSalary, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Information</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* Root Variables */
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #343a40;
            --light: #f8f9fa;
            --border: #e9ecef;
            --text: #212529;
            --text-muted: #6c757d;
            --shadow: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px);
        }

        .sidebar nav a {
            text-decoration: none;
        }

        .nav-link {
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: #4361ee;
            background-color: #F3F4FF;
        }

        .nav-link.active {
            background-color: #F3F4FF;
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #4361ee;
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout Link */
        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            color: black!important;
            background-color: #D22B2B;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background-color: #f5f8fa;
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        /* Toggle Button */
        .toggle-sidebar {
            position: fixed;
            left: calc(var(--sidebar-width) - 16px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1001;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle-sidebar:hover {
            background: var(--primary);
            color: white;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        /* Your existing styles */
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #059669;
            --background-color: #f1f5f9;
            --border-color: #e2e8f0;
            --text-color: #1e293b;
            --text-light: #64748b;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            line-height: 1.5;
        }

        .container {
            max-width: 98%;
            margin: 0 auto;
            padding: 0 10px;
        }

        h1 {
            color: var(--text-color);
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        h1 i {
            color: var(--primary-color);
            font-size: 2rem;
        }

        .page-header {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .month-navigation {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--white);
            padding: 1rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .date-picker {
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-color);
            background-color: var(--white);
            cursor: pointer;
            transition: all 0.2s;
        }

        .date-picker:hover {
            border-color: var(--primary-color);
        }

        .date-picker:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .salary-form {
            overflow-x: auto;
            background: var(--white);
            padding: 1rem;
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
        }

        table {
            width: 100%;
            min-width: auto;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0.5rem 0;
        }

        th, td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
            min-width: 90px;
            max-width: 120px;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .form-control {
            padding: 0.5rem 0.625rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            transition: all 0.2s;
            width: 90px;
            box-sizing: border-box;
            text-align: right;
        }

        .form-control:hover {
            border-color: var(--primary-color);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .rate-info {
            font-size: 0.6875rem;
            color: var(--text-light);
        }

        .success-message {
            background: #ecfdf5;
            color: var(--success-color);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease-out;
        }

        .info-tooltip {
            color: var(--text-light);
            cursor: help;
            position: relative;
            display: inline-flex;
            align-items: center;
            margin-left: 0.125rem;
            font-size: 0.75rem;
        }

        .info-tooltip:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.4rem 0.6rem;
            background: var(--text-color);
            color: var(--white);
            border-radius: 0.375rem;
            font-size: 0.7rem;
            white-space: nowrap;
            z-index: 10;
            max-width: 200px;
            white-space: normal;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-primary {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: 1rem;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Specific column widths */
        th:first-child, td:first-child {
            min-width: 130px;
        }

        th:nth-child(2), td:nth-child(2) {
            min-width: 110px;
        }

        th:nth-child(3), td:nth-child(3),
        th:nth-child(4), td:nth-child(4),
        th:nth-child(5), td:nth-child(5) {
            min-width: 80px;
        }

        /* Adjust input widths */
        .form-control {
            padding: 0.5rem 0.625rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            transition: all 0.2s;
            width: 90px;
            box-sizing: border-box;
            text-align: right;
        }

        /* Specific adjustments for different types of inputs */
        input[type="number"].form-control {
            width: 90px;
        }

        /* For read-only or display values */
        span.form-control {
            width: 90px;
            background: #f9fafb;
            display: inline-block;
        }

        .rate-info {
            font-size: 0.6875rem;
        }

        /* Adjust tooltip text */
        .info-tooltip {
            margin-left: 0.125rem;
        }

        /* Make table more compact */
        .salary-form {
            padding: 1rem;
        }

        /* Ensure numbers align properly */
        td .form-control {
            text-align: right;
        }

        /* Optimize for different screen sizes */
        @media (min-width: 1600px) {
            .container {
                max-width: 95%;
            }
        }

        @media (min-width: 1800px) {
            .container {
                max-width: 90%;
            }
        }

        /* Adjust table header text */
        th {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.75rem 0.5rem;
            white-space: nowrap;
            color: var(--text-color);
        }

        /* Adjust info icon size */
        th .fas {
            font-size: 0.75rem;
        }

        /* Style for the export button */
        #exportBtn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        #exportBtn:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        #exportBtn .fas {
            font-size: 1rem;
        }

        .export-range-container {
            margin-bottom: 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: var(--white);
            margin: 15% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 80%;
            max-width: 500px;
            position: relative;
            box-shadow: var(--shadow-lg);
        }

        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-light);
        }

        .close:hover {
            color: var(--text-color);
        }

        .date-range-inputs {
            display: flex;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .date-range-inputs .input-group {
            flex: 1;
        }

        .date-range-inputs label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-size: 0.875rem;
        }

        .date-range-inputs .date-picker {
            width: 100%;
        }

        #exportRangeBtn {
            width: 100%;
            justify-content: center;
        }

        /* Specific adjustments for leaves taken column */
        th:nth-child(7), td:nth-child(7) {
            min-width: 150px;  /* increased from default */
            width: auto;
        }

        /* Add style for leave deduction column */
        th:nth-child(8), td:nth-child(8) {
            min-width: 120px;
            width: auto;
        }

        /* Add style for the role filter */
        .role-filter {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
            padding-left: 1rem;
            border-left: 1px solid var(--border-color);
        }

        .role-select {
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-color);
            background-color: var(--white);
            cursor: pointer;
            transition: all 0.2s;
        }

        .role-select:hover {
            border-color: var(--primary-color);
        }

        .role-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* CSS for penalty amount display */
        .penalty-amount, .leave-penalty-amount {
            font-size: 0.8125rem;
            color: var(--danger, #f72585);
            margin-top: 0.25rem;
            text-align: right;
            padding-right: 0.5rem;
        }

        /* Make the leave penalty display slightly different */
        .leave-penalty-amount {
            color: #9c1458; /* darker shade for distinction */
        }

        /* Make sure total values are properly highlighted */
        .monthly-salary, .total-salary {
            font-weight: 500;
            color: var(--text-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="bi bi-hexagon-fill"></i>
            HR Portal
        </div>
        
        <nav>
            <a href="hr_dashboard.php" class="nav-link">
                <i class="bi bi-grid-1x2-fill"></i>
                Dashboard
            </a>
            <a href="employee.php" class="nav-link">
                <i class="bi bi-people-fill"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
            <a href="shifts.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="salary_overview.php" class="nav-link active">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="#" class="nav-link">
                <i class="bi bi-file-earmark-text-fill"></i>
                Reports
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container">
            <div class="page-header">
                <h1>
                    <i class="fas fa-money-bill-alt"></i>
                    Salary Information
                </h1>
                <button id="exportBtn" class="btn btn-outline-primary">
                    <i class="fas fa-file-excel"></i>
                    Export to Excel
                </button>
            </div>

            <div class="month-navigation">
                <i class="fas fa-calendar-alt"></i>
                <input type="month" 
                       class="date-picker" 
                       value="<?php echo $selected_month; ?>"
                       name="month">
                
                <!-- Add role filter dropdown -->
                <div class="role-filter">
                    <i class="fas fa-users"></i>
                    <select id="role-filter" class="role-select">
                        <option value="all">All Users</option>
                        <option value="site_supervisor">Site Supervisors</option>
                        <option value="except_site_supervisor">All Except Site Supervisors</option>
                    </select>
                </div>
            </div>

            <div class="export-range-container">
                <button id="showExportRangeBtn" class="btn btn-outline-primary">
                    <i class="fas fa-calendar-range"></i>
                    Export Date Range
                </button>
                <div id="exportRangeModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h3>Select Date Range for Export</h3>
                        <div class="date-range-inputs">
                            <div class="input-group">
                                <label for="startDate">From Date:</label>
                                <input type="date" id="startDate" class="date-picker">
                            </div>
                            <div class="input-group">
                                <label for="endDate">To Date:</label>
                                <input type="date" id="endDate" class="date-picker">
                            </div>
                        </div>
                        <button id="exportRangeBtn" class="btn btn-primary">
                            <i class="fas fa-file-excel"></i>
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    Salary information updated successfully!
                </div>
            <?php endif; ?>

            <form method="POST" class="salary-form">
                <table>
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Base Salary</th>
                            <th>Working Days</th>
                            <th>Present Days</th>
                            <th>
                                Late Days
                                <span class="info-tooltip" title="Days when punch-in was more than 15 minutes after shift start time">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Late Deduction
                                <span class="info-tooltip" title="Half day salary deducted for each late day after 3 late days">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Leaves Taken
                                <span class="info-tooltip" title="Leave Types:
                                • C.L. - Casual Leave
                                • SH.L. - Short Leave
                                • CO.L. - Compensate Leave
                                • U.L. - Unpaid Leave
                                • S.L. - Sick Leave
                                • E.L. - Emergency Leave
                                • M.L. - Maternity Leave
                                • P.L. - Paternity Leave

Leave Deduction Rules:
                                • Casual Leave: Deducts 1 day salary if more than 1 leave per month
                                • Short Leave: Deducts half day salary if more than 2 leaves
                                • Compensate Leave: Deducts 1 day salary if exceeds allowed limit
                                • Unpaid Leave: Deducts 1 day salary per leave">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Leave Deduction
                                <span class="info-tooltip" title="Salary deductions for leaves">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Half Day Deduction
                                <span class="info-tooltip" title="Salary deductions for half day leaves">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Penalty Deduction
                                <span class="info-tooltip" title="Enter number of days to deduct as penalty for the month. Half-day values (0.5) are also supported.">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Leave Penalty
                                <span class="info-tooltip" title="Enter number of days to deduct as leave penalty for the month. Half-day values (0.5) are also supported.">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>Monthly Salary</th>
                            <th>
                                Overtime Hours
                                <span class="info-tooltip" title="Total overtime hours for selected month">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Overtime Rate
                                <span class="info-tooltip" title="Automatically calculated based on base salary, working days, and shift hours">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Overtime Amount
                                <span class="info-tooltip" title="Calculated as Overtime Hours × Overtime Rate">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                            <th>
                                Total Salary
                                <span class="info-tooltip" title="Monthly Salary + Overtime Amount">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $workingDaysInfo = calculateWorkingDays($month_start, $month_end, $user['id']);
                            // Calculate per day salary
                            $perDaySalary = $workingDaysInfo['working_days'] > 0 ? 
                                ($user['base_salary'] / $workingDaysInfo['working_days']) : 0;
                            
                            // Count casual leaves taken
                            $casualLeaveCount = 0;
                            if (!empty($user['leaves_taken'])) {
                                $leaves = explode("\n", $user['leaves_taken']);
                                foreach ($leaves as $leave) {
                                    if (preg_match('/^C\.L\.: (\d+)\//', $leave, $matches)) {
                                        $casualLeaveCount = (int)$matches[1];
                                        break;
                                    }
                                }
                            }
                            
                            // Count short leaves taken
                            $shortLeaveCount = 0;
                            if (!empty($user['leaves_taken'])) {
                                $leaves = explode("\n", $user['leaves_taken']);
                                foreach ($leaves as $leave) {
                                    if (preg_match('/^SH\.L\.: (\d+)\//', $leave, $matches)) {
                                        $shortLeaveCount = (int)$matches[1];
                                        break;
                                    }
                                }
                            }
                            
                            // Calculate adjusted present days (add casual leaves to present days)
                            $presentDays = ($user['present_days'] ?? 0);
                            $adjustedPresentDays = $presentDays + $casualLeaveCount;
                            
                            // Calculate adjusted late days (subtract short leaves up to 2)
                            $lateDays = $user['late_days'] ?? 0;
                            $adjustedLateDays = max(0, $lateDays - min(2, $shortLeaveCount));
                            
                            // Calculate late deduction based on adjusted late days
                            $deductionDays = floor($adjustedLateDays / 3); // Get number of complete sets of 3 late days
                            if ($workingDaysInfo['working_days'] > 0) {
                                $perDaySalary = $user['base_salary'] / $workingDaysInfo['working_days'];
                                $lateDeduction = $deductionDays * ($perDaySalary * 0.5); // Half day salary for each set of 3 late days
                            } else {
                                $lateDeduction = 0;
                            }
                            
                            // Calculate leave deductions excluding half day leaves
                            $leaveDeductionWithoutHalfDay = 0;
                            if (!empty($user['leaves_taken'])) {
                                $leaves = explode("\n", $user['leaves_taken']);
                                foreach ($leaves as $leave) {
                                    if (preg_match('/^(.*?): (\d+)\/(\d+)/', $leave, $matches)) {
                                        $leaveType = $matches[1];
                                        $taken = (int)$matches[2];
                                        $allowed = (int)$matches[3];

                                        if ($leaveType === 'C.L.' && $taken > 1) {
                                            $leaveDeductionWithoutHalfDay += $perDaySalary * ($taken - 1);
                                        } elseif ($leaveType === 'SH.L.' && $taken > 2) {
                                            $leaveDeductionWithoutHalfDay += ($perDaySalary * 0.5) * ($taken - 2);
                                        } elseif ($leaveType === 'CO.L.' && $taken > $allowed) {
                                            $leaveDeductionWithoutHalfDay += $perDaySalary * ($taken - $allowed);
                                        } elseif ($leaveType === 'U.L.') {
                                            $leaveDeductionWithoutHalfDay += ($perDaySalary * $taken);
                                        }
                                    }
                                }
                            }
                            
                            // Calculate half day deductions
                            $halfDayDeduction = 0;
                            if (!empty($user['leaves_taken'])) {
                                $leaves = explode("\n", $user['leaves_taken']);
                                foreach ($leaves as $leave) {
                                    if (preg_match('/^H\.L\.: (\d+)\/(\d+)/', $leave, $matches)) {
                                        $taken = (int)$matches[1];
                                        $halfDayDeduction = $perDaySalary * 0.5 * $taken;
                                        break;
                                    }
                                }
                            }
                            
                            // Update monthly salary calculation to use adjusted present days and include half day deduction
                            $monthSalary = ($perDaySalary * $adjustedPresentDays) - $lateDeduction - $halfDayDeduction;

                            // Calculate overtime amount
                            $shiftHours = $user['shift_hours'] ?? 8;
                            $suggestedRate = calculateOvertimeRate(
                                $user['base_salary'], 
                                $workingDaysInfo['working_days'],
                                $shiftHours
                            );
                            $overtime_hours = $user['overtime_hours'] ?: '0:00';
                            list($hours, $minutes) = explode(':', $overtime_hours);
                            // Only consider full hours and half hours (30 min)
                            $decimal_hours = floatval($hours) + ($minutes == '30' ? 0.5 : 0);
                            $overtime_amount = $decimal_hours * $suggestedRate;
                            
                            // Calculate leaves taken
                            $leavesTaken = $user['leaves_taken'] ?? 0;
                            
                            // Calculate total salary - include monthly salary (which already has late deduction) and overtime only
                            $totalSalary = $monthSalary + $overtime_amount;

                            // Calculate adjusted monthly salary
                            $penaltyDays = isset($penalties[$user['id']]['penalty_days']) ? (float)$penalties[$user['id']]['penalty_days'] : 0;
                            $penaltyAmount = $penaltyDays * $perDaySalary;
                            $adjustedMonthSalary = $monthSalary - $penaltyAmount;
                            $adjustedTotalSalary = $adjustedMonthSalary + $overtime_amount;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <input type="number" 
                                           name="salary[<?php echo $user['id']; ?>][base_salary]" 
                                           value="<?php echo $user['base_salary']; ?>" 
                                           class="form-control base-salary"
                                           data-user-id="<?php echo $user['id']; ?>"
                                           onchange="updateOvertimeRate(this)">
                                </td>
                                <td>
                                    <?php echo $workingDaysInfo['working_days']; ?>
                                </td>
                                <td>
                                    <?php echo $presentDays; ?> 
                                    <?php if ($casualLeaveCount > 0): ?>
                                        <span class="rate-info">(<?php echo $adjustedPresentDays; ?> with C.L.)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $user['late_days'] ?? 0; ?> 
                                    <?php if ($shortLeaveCount > 0 && $adjustedLateDays != $lateDays): ?>
                                        <span class="rate-info">(<?php echo $adjustedLateDays; ?> after SH.L.)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo '₹' . number_format($lateDeduction, 2); ?>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="form-control" style="background: #f9fafb; white-space: pre-line; height: auto; min-height: 38px; width: 150px; /* increased width */">
                                            <?php 
                                            echo $user['leaves_taken'] ? 
                                                htmlspecialchars($user['leaves_taken']) : 
                                                '0 days';
                                            ?>
                                        </span>
                                        <div class="rate-info">Days</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="form-control" style="background: #f9fafb;">
                                            <?php 
                                            // Calculate leave deductions excluding half day leaves
                                            $leaveDeductionWithoutHalfDay = 0;
                                            if (!empty($user['leaves_taken'])) {
                                                $leaves = explode("\n", $user['leaves_taken']);
                                                foreach ($leaves as $leave) {
                                                    if (preg_match('/^(.*?): (\d+)\/(\d+)/', $leave, $matches)) {
                                                        $leaveType = $matches[1];
                                                        $taken = (int)$matches[2];
                                                        $allowed = (int)$matches[3];

                                                        if ($leaveType === 'C.L.' && $taken > 1) {
                                                            $leaveDeductionWithoutHalfDay += $perDaySalary * ($taken - 1);
                                                        } elseif ($leaveType === 'SH.L.' && $taken > 2) {
                                                            $leaveDeductionWithoutHalfDay += ($perDaySalary * 0.5) * ($taken - 2);
                                                        } elseif ($leaveType === 'CO.L.' && $taken > $allowed) {
                                                            $leaveDeductionWithoutHalfDay += $perDaySalary * ($taken - $allowed);
                                                        } elseif ($leaveType === 'U.L.') {
                                                            $leaveDeductionWithoutHalfDay += ($perDaySalary * $taken);
                                                        }
                                                    }
                                                }
                                            }
                                            echo '₹' . number_format($leaveDeductionWithoutHalfDay, 2); 
                                            ?>
                                        </span>
                                        <div class="rate-info">Amount</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="form-control" style="background: #f9fafb;">
                                            <?php 
                                            // Calculate half day deductions
                                            $halfDayDeduction = 0;
                                            if (!empty($user['leaves_taken'])) {
                                                $leaves = explode("\n", $user['leaves_taken']);
                                                foreach ($leaves as $leave) {
                                                    if (preg_match('/^H\.L\.: (\d+)\/(\d+)/', $leave, $matches)) {
                                                        $taken = (int)$matches[1];
                                                        $halfDayDeduction = $perDaySalary * 0.5 * $taken;
                                                        break;
                                                    }
                                                }
                                            }
                                            echo '₹' . number_format($halfDayDeduction, 2); 
                                            ?>
                                        </span>
                                        <div class="rate-info">Amount</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" 
                                               name="salary[<?php echo $user['id']; ?>][penalty_days]" 
                                               value="<?php echo isset($penalties[$user['id']]['penalty_days']) ? $penalties[$user['id']]['penalty_days'] : 0; ?>" 
                                               min="0" 
                                               max="31" 
                                               step="0.5"
                                               class="form-control penalty-days"
                                               data-user-id="<?php echo $user['id']; ?>"
                                               data-per-day-salary="<?php echo $perDaySalary; ?>"
                                               onchange="calculatePenalty(this)">
                                        <div class="rate-info">Days</div>
                                    </div>
                                    <?php 
                                    $penaltyAmount = $penaltyDays * $perDaySalary;
                                    ?>
                                    <div class="penalty-amount" id="penalty-amount-<?php echo $user['id']; ?>">
                                        <?php echo $penaltyDays > 0 ? '₹' . number_format($penaltyAmount, 2) : '₹0.00'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" 
                                               name="salary[<?php echo $user['id']; ?>][leave_penalty_days]" 
                                               value="<?php echo isset($penalties[$user['id']]['leave_penalty_days']) ? $penalties[$user['id']]['leave_penalty_days'] : 0; ?>" 
                                               min="0" 
                                               max="31" 
                                               step="0.5"
                                               class="form-control leave-penalty-days"
                                               data-user-id="<?php echo $user['id']; ?>"
                                               data-per-day-salary="<?php echo $perDaySalary; ?>"
                                               onchange="calculateLeavePenalty(this)">
                                        <div class="rate-info">Days</div>
                                    </div>
                                    <?php 
                                    $leavePenaltyDays = isset($penalties[$user['id']]['leave_penalty_days']) ? (float)$penalties[$user['id']]['leave_penalty_days'] : 0;
                                    $leavePenaltyAmount = $leavePenaltyDays * $perDaySalary;
                                    ?>
                                    <div class="leave-penalty-amount" id="leave-penalty-amount-<?php echo $user['id']; ?>">
                                        <?php echo $leavePenaltyDays > 0 ? '₹' . number_format($leavePenaltyAmount, 2) : '₹0.00'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="monthly-salary" id="monthly-salary-<?php echo $user['id']; ?>" data-original="<?php echo $monthSalary; ?>">
                                        ₹<?php echo number_format($adjustedMonthSalary, 2); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="form-control" style="background: #f9fafb;">
                                            <?php echo $user['overtime_hours'] ?: '0:00'; ?>
                                        </span>
                                        <div class="rate-info">Hours:Minutes</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <?php 
                                        $suggestedRate = calculateOvertimeRate(
                                            $user['base_salary'], 
                                            $workingDaysInfo['working_days'],
                                            $shiftHours
                                        );
                                        ?>
                                        <input type="number" 
                                               name="salary[<?php echo $user['id']; ?>][overtime_rate]" 
                                               value="<?php echo $suggestedRate; ?>" 
                                               class="form-control overtime-rate"
                                               data-user-id="<?php echo $user['id']; ?>"
                                               step="0.01">
                                        <span class="rate-info">
                                            (Auto-calculated)
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="form-control" style="background: #f9fafb;">
                                            <?php
                                            $overtime_hours = $user['overtime_hours'] ?: '0:00';
                                            list($hours, $minutes) = explode(':', $overtime_hours);
                                            // Only consider full hours and half hours (30 min)
                                            $decimal_hours = floatval($hours) + ($minutes == '30' ? 0.5 : 0);
                                            
                                            // Add debug output
                                            error_log("Debug - User: {$user['username']}");
                                            error_log("Overtime hours: $overtime_hours");
                                            error_log("Decimal hours: $decimal_hours");
                                            error_log("Suggested rate: $suggestedRate");
                                            
                                            $overtime_amount = $decimal_hours * $suggestedRate;
                                            echo number_format($overtime_amount, 2);
                                            ?>
                                        </span>
                                        <div class="rate-info">Amount</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="form-control total-salary" style="background: #f9fafb;" id="total-salary-<?php echo $user['id']; ?>" data-overtime="<?php echo $overtime_amount; ?>">
                                            ₹<?php echo number_format($adjustedTotalSalary, 2); ?>
                                        </span>
                                        <div class="rate-info">Total</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                    <a href="salary_overview.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Overview
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <script>
    // Calculate penalty amount based on days
    function calculatePenalty(penaltyInput) {
        const userId = penaltyInput.dataset.userId;
        const penaltyDays = parseFloat(penaltyInput.value) || 0;
        const perDaySalary = parseFloat(penaltyInput.dataset.perDaySalary) || 0;
        
        const penaltyAmount = penaltyDays * perDaySalary;
        
        // Update penalty amount display
        const penaltyAmountElement = document.getElementById(`penalty-amount-${userId}`);
        if (penaltyAmountElement) {
            penaltyAmountElement.textContent = `₹${penaltyAmount.toFixed(2)}`;
        }
        
        // Get leave penalty amount (if any)
        const leavePenaltyInput = document.querySelector(`.leave-penalty-days[data-user-id="${userId}"]`);
        const leavePenaltyDays = parseFloat(leavePenaltyInput?.value) || 0;
        const leavePenaltyAmount = leavePenaltyDays * perDaySalary;
        
        // Combined penalty amount
        const totalPenaltyAmount = penaltyAmount + leavePenaltyAmount;
        
        // Update monthly salary
        const monthlySalaryElement = document.getElementById(`monthly-salary-${userId}`);
        if (monthlySalaryElement) {
            const originalSalary = parseFloat(monthlySalaryElement.dataset.original) || 0;
            const newSalary = originalSalary - totalPenaltyAmount;
            monthlySalaryElement.textContent = `₹${newSalary.toFixed(2)}`;
            
            // Also update total salary (monthly salary + overtime)
            const totalSalaryElement = document.getElementById(`total-salary-${userId}`);
            if (totalSalaryElement) {
                const overtimeAmount = parseFloat(totalSalaryElement.dataset.overtime) || 0;
                const newTotalSalary = newSalary + overtimeAmount;
                totalSalaryElement.textContent = `₹${newTotalSalary.toFixed(2)}`;
            }
        }
    }

    // Calculate leave penalty amount based on days
    function calculateLeavePenalty(leavePenaltyInputElem) {
        const userId = leavePenaltyInputElem.dataset.userId;
        const leavePenaltyDays = parseFloat(leavePenaltyInputElem.value) || 0;
        const perDaySalary = parseFloat(leavePenaltyInputElem.dataset.perDaySalary) || 0;
        
        const leavePenaltyAmount = leavePenaltyDays * perDaySalary;
        
        // Update leave penalty amount display
        const leavePenaltyAmountElement = document.getElementById(`leave-penalty-amount-${userId}`);
        if (leavePenaltyAmountElement) {
            leavePenaltyAmountElement.textContent = `₹${leavePenaltyAmount.toFixed(2)}`;
        }
        
        // Get regular penalty amount (if any)
        const penaltyInputElem = document.querySelector(`.penalty-days[data-user-id="${userId}"]`);
        const penaltyDays = parseFloat(penaltyInputElem?.value) || 0;
        const penaltyAmount = penaltyDays * perDaySalary;
        
        // Combined penalty amount
        const totalPenaltyAmount = penaltyAmount + leavePenaltyAmount;
        
        // Update monthly salary
        const monthlySalaryElement = document.getElementById(`monthly-salary-${userId}`);
        if (monthlySalaryElement) {
            const originalSalary = parseFloat(monthlySalaryElement.dataset.original) || 0;
            const newSalary = originalSalary - totalPenaltyAmount;
            monthlySalaryElement.textContent = `₹${newSalary.toFixed(2)}`;
            
            // Also update total salary (monthly salary + overtime)
            const totalSalaryElement = document.getElementById(`total-salary-${userId}`);
            if (totalSalaryElement) {
                const overtimeAmount = parseFloat(totalSalaryElement.dataset.overtime) || 0;
                const newTotalSalary = newSalary + overtimeAmount;
                totalSalaryElement.textContent = `₹${newTotalSalary.toFixed(2)}`;
            }
        }
    }
    
    // Then keep the rest of your existing code here...

    function updateOvertimeRate(baseSalaryInput) {
        const userId = baseSalaryInput.dataset.userId;
        const baseSalary = parseFloat(baseSalaryInput.value) || 0;
        const workingDays = parseInt(baseSalaryInput.closest('tr').querySelector('td:nth-child(3)').textContent) || 0;
        const shiftHours = <?php echo $user['shift_hours'] ?? 8 ?>;
        
        let overtimeRate = 0;
        if (workingDays > 0 && shiftHours > 0) {
            const perDaySalary = baseSalary / workingDays;
            overtimeRate = (perDaySalary / shiftHours).toFixed(2);
        }
        
        const overtimeInput = document.querySelector(`.overtime-rate[data-user-id="${userId}"]`);
        if (overtimeInput) {
            overtimeInput.value = overtimeRate;
        }
    }

    // Initialize all overtime rates on page load
    document.addEventListener('DOMContentLoaded', function() {
        const baseSalaryInputs = document.querySelectorAll('.base-salary');
        baseSalaryInputs.forEach(input => updateOvertimeRate(input));
    });

    // Month picker change handler
    document.addEventListener('DOMContentLoaded', function() {
        const datePicker = document.querySelector('.date-picker');
        const roleFilter = document.getElementById('role-filter');
        
        // Set the selected role from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const roleParam = urlParams.get('role');
        if (roleParam) {
            roleFilter.value = roleParam;
        }
        
        if (datePicker) {
            datePicker.addEventListener('change', function() {
                const selectedMonth = this.value;
                if (selectedMonth) {
                    // Include the role parameter when redirecting
                    const selectedRole = roleFilter.value;
                    window.location.href = `salary_info.php?month=${encodeURIComponent(selectedMonth)}&role=${selectedRole}`;
                }
            });

            // Set max date to prevent selection beyond reasonable future dates
            const today = new Date();
            const maxYear = today.getFullYear() + 2; // Allow selection up to 2 years in future
            const maxMonth = String(today.getMonth() + 1).padStart(2, '0');
            datePicker.setAttribute('max', `${maxYear}-${maxMonth}`);
            
            // Set min date to prevent selection of very old dates
            const minYear = today.getFullYear() - 2; // Allow selection up to 2 years in past
            datePicker.setAttribute('min', `${minYear}-01`);
        }
        
        // Add event listener for role filter changes
        if (roleFilter) {
            roleFilter.addEventListener('change', function() {
                const selectedRole = this.value;
                const selectedMonth = datePicker.value;
                window.location.href = `salary_info.php?month=${encodeURIComponent(selectedMonth)}&role=${selectedRole}`;
            });
        }
    });

    document.getElementById('exportBtn').addEventListener('click', function() {
        // Get the table
        const table = document.querySelector('.salary-form table');
        
        // Get the selected month from the date picker
        const monthPicker = document.querySelector('.date-picker');
        const selectedMonth = monthPicker.value;
        
        // Get the selected role
        const roleFilter = document.getElementById('role-filter');
        const selectedRole = roleFilter.value;
        
        // Convert table to worksheet
        const ws = XLSX.utils.table_to_sheet(table);
        
        // Create workbook and add the worksheet
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Salary Info");
        
        // Format the filename with the month and role
        let filename = `Salary_Information_${selectedMonth}`;
        if (selectedRole !== 'all') {
            filename += `_${selectedRole}`;
        }
        filename += ".xlsx";
        
        // Export to Excel file
        XLSX.writeFile(wb, filename);
    });

    // Add these new functions for date range export
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('exportRangeModal');
        const showModalBtn = document.getElementById('showExportRangeBtn');
        const closeBtn = document.querySelector('.close');
        const exportRangeBtn = document.getElementById('exportRangeBtn');
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');

        // Set default date range (current month)
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        startDate.value = firstDay.toISOString().split('T')[0];
        endDate.value = lastDay.toISOString().split('T')[0];

        // Show modal
        showModalBtn.onclick = function() {
            modal.style.display = "block";
        }

        // Close modal
        closeBtn.onclick = function() {
            modal.style.display = "none";
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Handle range export
        exportRangeBtn.onclick = async function() {
            const start = startDate.value;
            const end = endDate.value;
            const roleFilter = document.getElementById('role-filter');
            const selectedRole = roleFilter.value;

            if (!start || !end) {
                alert('Please select both start and end dates');
                return;
            }

            if (new Date(start) > new Date(end)) {
                alert('Start date cannot be after end date');
                return;
            }

            try {
                // Show loading indicator
                exportRangeBtn.disabled = true;
                exportRangeBtn.innerHTML = 'Exporting...';

                // Build URL with parameters
                const params = new URLSearchParams({
                    start: start,
                    end: end,
                    role: selectedRole
                });

                // Make the request
                const response = await fetch('get_salary_data.php?' + params.toString());
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Get the response as text first for debugging
                const responseText = await response.text();
                
                // Try to parse the JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('Raw response:', responseText);
                    throw new Error('Failed to parse server response as JSON');
                }

                // Check if we got valid data
                if (!Array.isArray(data)) {
                    throw new Error('Invalid data format received from server');
                }

                // Create the worksheet from the data
                const ws = XLSX.utils.json_to_sheet(data);
                
                // Create workbook and add the worksheet
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Salary Info");
                
                // Generate filename with date range and role
                let filename = `Salary_Information_${start}_to_${end}`;
                if (selectedRole !== 'all') {
                    filename += `_${selectedRole}`;
                }
                filename += ".xlsx";
                
                // Export to Excel file
                XLSX.writeFile(wb, filename);
                
                // Close modal
                modal.style.display = "none";

            } catch (error) {
                console.error('Export error:', error);
                alert('Error exporting data: ' + error.message);
            } finally {
                // Reset button state
                exportRangeBtn.disabled = false;
                exportRangeBtn.innerHTML = '<i class="fas fa-file-excel"></i> Export';
            }
        }
    });

    // Add Sidebar Toggle Script
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar functionality
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            sidebarToggle.classList.toggle('collapsed');
            
            // Change icon direction
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('bi-chevron-left');
                icon.classList.add('bi-chevron-right');
            } else {
                icon.classList.remove('bi-chevron-right');
                icon.classList.add('bi-chevron-left');
            }
        });
        
        // Handle responsive behavior
        function checkWidth() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                sidebarToggle.classList.remove('collapsed');
            }
        }
        
        // Check on load
        checkWidth();
        
        // Check on resize
        window.addEventListener('resize', checkWidth);
        
        // Handle click outside on mobile
        document.addEventListener('click', function(e) {
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile && !sidebar.contains(e.target) && !sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            }
        });
    });

    // Add this function to handle overtime calculation with 30-minute rounding
    function calculateOvertimeWithRounding(hoursMinutes) {
        if (!hoursMinutes || hoursMinutes === '0:00') {
            return 0;
        }
        
        const [hours, minutes] = hoursMinutes.split(':');
        // Minutes will only be 00 or 30 based on our SQL query changes
        const decimalHours = parseInt(hours) + (minutes === '30' ? 0.5 : 0);
        return decimalHours;
    }

    // Initialize all penalty calculations on page load
    document.addEventListener('DOMContentLoaded', function() {
        const penaltyInputs = document.querySelectorAll('.penalty-days');
        penaltyInputs.forEach(input => {
            if (parseFloat(input.value) > 0) {
                calculatePenalty(input);
            }
        });
        
        const leavePenaltyInputs = document.querySelectorAll('.leave-penalty-days');
        leavePenaltyInputs.forEach(input => {
            if (parseFloat(input.value) > 0) {
                calculateLeavePenalty(input);
            }
        });
    });
    </script>
</body>
</html> 