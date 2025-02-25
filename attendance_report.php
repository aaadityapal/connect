<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Set default filter values
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
$show_all = ($is_hr && $user_id === 'all');
$is_hr = ($_SESSION['role'] === 'HR' || isset($_SESSION['temp_admin_access']));

// Fetch users for HR view
$users = [];
if ($is_hr) {
    $stmt = $pdo->query("SELECT id, username, unique_id FROM users WHERE deleted_at IS NULL ORDER BY username");
    $users = $stmt->fetchAll();
}

// Fetch all active users for the dropdown
$users_query = "SELECT id, username, unique_id FROM users WHERE deleted_at IS NULL ORDER BY username";
$users_stmt = $pdo->query($users_query);
$all_users = $users_stmt->fetchAll();

// Fetch attendance records
$query = "
    SELECT 
        a.*,
        u.username,
        u.unique_id,
        s.shift_name,
        s.start_time as shift_start,
        s.end_time as shift_end,
        us.weekly_offs,
        us.effective_from as shift_effective_from,
        us.effective_to as shift_effective_to,
        a.overtime_hours
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN user_shifts us ON (
        u.id = us.user_id 
        AND a.date >= us.effective_from 
        AND (us.effective_to IS NULL OR a.date <= us.effective_to)
    )
    LEFT JOIN shifts s ON us.shift_id = s.id
    WHERE DATE_FORMAT(a.date, '%Y-%m') = :month
    " . ($user_id !== 'all' ? "AND a.user_id = :user_id" : "") . "
    ORDER BY a.date DESC, u.username ASC
";

$params = ['month' => $month];
if ($user_id !== 'all') {
    $params['user_id'] = $user_id;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Calculate monthly totals
$total_working_hours = 0;
$total_overtime = 0;
foreach ($records as $record) {
    $total_working_hours += is_numeric($record['working_hours']) ? 
        $record['working_hours'] : 
        convertTimeToDecimal($record['working_hours']);
    
    $total_overtime += is_numeric($record['overtime_hours']) ? 
        $record['overtime_hours'] : 
        convertTimeToDecimal($record['overtime_hours']);
}

// Add this helper function
function convertTimeToDecimal($timeString) {
    if (empty($timeString)) return 0;
    
    if (strpos($timeString, ':') !== false) {
        $parts = explode(':', $timeString);
        if (count($parts) >= 2) {
            $hours = (int)$parts[0];
            $minutes = (int)$parts[1];
            $seconds = isset($parts[2]) ? (int)$parts[2] : 0;
            
            return $hours + ($minutes / 60) + ($seconds / 3600);
        }
    }
    
    return 0;
}

// Add function to get weekly offs for a specific date and user
function getWeeklyOffsForDate($pdo, $user_id, $date) {
    $stmt = $pdo->prepare("
        SELECT weekly_offs 
        FROM user_shifts 
        WHERE user_id = ? 
        AND effective_from <= ?
        AND (effective_to IS NULL OR effective_to >= ?)
        ORDER BY effective_from DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id, $date, $date]);
    $result = $stmt->fetch();
    return $result ? $result['weekly_offs'] : '';
}

// Add function to check if a date is a weekly off
function isWeeklyOff($date, $weekly_offs) {
    if (empty($weekly_offs)) return false;
    
    $weekly_offs_array = explode(',', $weekly_offs);
    $day_of_week = date('l', strtotime($date));
    return in_array($day_of_week, $weekly_offs_array);
}

// Add this helper function near the top of the file with other functions
function formatHoursAndMinutes($timeString) {
    if (empty($timeString)) return '-';
    
    // If it's already a decimal number, just format it
    if (is_numeric($timeString)) {
        return number_format((float)$timeString, 2);
    }
    
    // Handle HH:MM:SS format
    if (strpos($timeString, ':') !== false) {
        $parts = explode(':', $timeString);
        if (count($parts) >= 2) {
            $hours = (int)$parts[0];
            $minutes = (int)$parts[1];
            $seconds = isset($parts[2]) ? (int)$parts[2] : 0;
            
            $decimal = $hours + ($minutes / 60) + ($seconds / 3600);
            return number_format($decimal, 2);
        }
    }
    
    // If we can't parse it, return the original string
    return $timeString;
}

// Add this new helper function
function formatDecimalToTime($timeString) {
    if (empty($timeString)) return '-';
    
    // If it's already in HH:MM:SS format, just format it to HH:MM
    if (strpos($timeString, ':') !== false) {
        $parts = explode(':', $timeString);
        if (count($parts) >= 2) {
            return sprintf("%02d:%02d", (int)$parts[0], (int)$parts[1]);
        }
        return $timeString;
    }
    
    // Handle decimal format
    if (is_numeric($timeString)) {
        $hours = floor($timeString);
        $minutes = round(($timeString - $hours) * 60);
        
        return sprintf("%02d:%02d", $hours, $minutes);
    }
    
    return $timeString;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .attendance-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .overtime {
            color: #dc3545;
            font-weight: bold;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .summary-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .summary-item h3 {
            margin: 0;
            color: #6c757d;
        }

        .summary-item p {
            margin: 10px 0 0;
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        @media (max-width: 768px) {
            .attendance-table {
                display: block;
                overflow-x: auto;
            }
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .summary-table th,
        .summary-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .summary-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .department-header {
            background-color: #f8f9fa;
            padding: 10px;
            margin: 20px 0 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        .attendance-table tr.user-row td {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .weekly-offs-display {
            margin-left: 15px;
            color: #666;
            font-style: italic;
        }

        .weekly-off-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
        }

        .weekly-off-row {
            background-color: #f8f9fa;
        }

        .status-weekly-off {
            color: #6c757d;
            font-style: italic;
        }

        .user-row td {
            background-color: #f8f9fa;
            font-weight: bold;
            border-top: 2px solid #dee2e6;
        }

        .user-row:first-child td {
            border-top: none;
        }

        .attendance-table td small {
            color: #6c757d;
            display: block;
            margin-top: 3px;
        }

        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .weekly-offs-info {
            font-size: 0.9em;
            color: #666;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            display: inline-block;
            margin: 2px;
        }

        .badge.weekly-off {
            background-color: #e9ecef;
            color: #495057;
        }

        .badge.worked {
            background-color: #ffc107;
            color: #000;
        }

        .badge.working-day {
            background-color: #28a745;
            color: #fff;
        }

        .weekly-off-row {
            background-color: #f8f9fa;
        }

        .weekly-off-status {
            white-space: nowrap;
        }

        .user-row td {
            background-color: #f8f9fa;
            padding: 15px;
        }

        .filters-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .filters-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .filters-container select,
        .filters-container input {
            width: 100%;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .filters-container .row > div {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Attendance Report</h2>

        <div class="filters-container mb-4">
            <form method="GET" class="form-inline">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label for="month">Select Month:</label>
                        <input type="month" id="month" name="month" class="form-control" 
                               value="<?php echo htmlspecialchars($month); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="user_filter">Select Employee:</label>
                        <select name="user_id" id="user_filter" class="form-control">
                            <?php if ($is_hr): ?>
                                <option value="all" <?php echo $user_id === 'all' ? 'selected' : ''; ?>>All Employees</option>
                            <?php endif; ?>
                            
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['unique_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="summary-card">
            <div class="summary-grid">
                <div class="summary-item">
                    <h3>Total Working Hours</h3>
                    <p><?php echo number_format($total_working_hours, 2); ?></p>
                </div>
                <div class="summary-item">
                    <h3>Total Overtime</h3>
                    <p class="overtime"><?php echo formatDecimalToTime($total_overtime); ?></p>
                </div>
                <div class="summary-item">
                    <h3>Days Present</h3>
                    <p><?php echo count($records); ?></p>
                </div>
            </div>
        </div>

        <?php if ($show_all): ?>
            <div class="summary-card">
                <h3>Department-wise Summary</h3>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Total Employees</th>
                            <th>Total Working Hours</th>
                            <th>Total Overtime</th>
                            <th>Average Working Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $dept_summary = [];
                        foreach ($records as $record) {
                            $dept = $record['department'] ?? 'Unassigned';
                            if (!isset($dept_summary[$dept])) {
                                $dept_summary[$dept] = [
                                    'employees' => [],
                                    'working_hours' => 0,
                                    'overtime' => 0
                                ];
                            }
                            $dept_summary[$dept]['employees'][$record['user_id']] = true;
                            
                            // Convert working hours if needed
                            $working_hours = is_numeric($record['working_hours']) ? 
                                $record['working_hours'] : 
                                convertTimeToDecimal($record['working_hours']);
                            
                            // Convert overtime if needed
                            $overtime = is_numeric($record['overtime_hours']) ? 
                                $record['overtime_hours'] : 
                                convertTimeToDecimal($record['overtime_hours']);
                            
                            $dept_summary[$dept]['working_hours'] += $working_hours;
                            $dept_summary[$dept]['overtime'] += $overtime;
                        }

                        foreach ($dept_summary as $dept => $summary):
                            $emp_count = count($summary['employees']);
                            $avg_hours = $emp_count > 0 ? $summary['working_hours'] / $emp_count : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dept); ?></td>
                                <td><?php echo $emp_count; ?></td>
                                <td><?php echo number_format($summary['working_hours'], 2); ?></td>
                                <td class="overtime"><?php echo number_format($summary['overtime'], 2); ?></td>
                                <td><?php echo number_format($avg_hours, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Shift</th>
                    <th>Weekly Off Status</th>
                    <th>Punch In</th>
                    <th>Punch Out</th>
                    <th>Working Hours</th>
                    <th>Overtime</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_user = null;
                foreach ($records as $record):
                    // Get weekly offs for this specific date
                    $weekly_offs = getWeeklyOffsForDate($pdo, $record['user_id'], $record['date']);
                    $is_weekly_off = isWeeklyOff($record['date'], $weekly_offs);
                    
                    if ($show_all && $current_user !== $record['username']):
                        $current_user = $record['username'];
                ?>
                        <tr class="user-row">
                            <td colspan="9">
                                <div class="user-header">
                                    <span class="user-name">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($record['username'] . ' (' . $record['unique_id'] . ')'); ?>
                                    </span>
                                    <span class="weekly-offs-info">
                                        Current Weekly Offs: 
                                        <?php 
                                        $current_weekly_offs = getWeeklyOffsForDate($pdo, $record['user_id'], date('Y-m-d'));
                                        echo !empty($current_weekly_offs) 
                                            ? htmlspecialchars(implode(', ', explode(',', $current_weekly_offs))) 
                                            : 'Not Set';
                                        ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr class="<?php echo $is_weekly_off ? 'weekly-off-row' : ''; ?>">
                        <td><?php echo date('d M Y (D)', strtotime($record['date'])); ?></td>
                        <td><?php echo htmlspecialchars($record['username']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($record['shift_name']); ?>
                            <br>
                            <small>
                                <?php 
                                if ($record['shift_start'] && $record['shift_end']) {
                                    echo date('h:i A', strtotime($record['shift_start'])) . ' - ' . 
                                         date('h:i A', strtotime($record['shift_end']));
                                } else {
                                    echo 'No Shift';
                                }
                                ?>
                            </small>
                        </td>
                        <td class="weekly-off-status">
                            <?php if ($is_weekly_off): ?>
                                <span class="badge weekly-off">Weekly Off</span>
                                <?php if ($record['punch_in']): ?>
                                    <span class="badge worked">Worked</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge working-day">Working Day</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $record['punch_in'] ? date('h:i A', strtotime($record['punch_in'])) : '-'; ?></td>
                        <td><?php echo $record['punch_out'] ? date('h:i A', strtotime($record['punch_out'])) : '-'; ?></td>
                        <td><?php echo formatHoursAndMinutes($record['working_hours']); ?></td>
                        <td><?php 
                            echo isset($record['overtime_hours']) ? $record['overtime_hours'] : '00:00:00';
                        ?></td>
                        <td>
                            <span class="status-badge <?php echo strtolower($record['status']); ?>">
                                <?php echo htmlspecialchars($record['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($records)): ?>
            <div class="alert alert-info">
                No attendance records found for the selected period.
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('.filters-container form');
            const filterInputs = filterForm.querySelectorAll('select, input');

            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>
</body>
</html>         