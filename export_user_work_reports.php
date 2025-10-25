<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}

// Get current user ID
$current_user_id = $_SESSION['user_id'];

// Check if user is admin or HR
$is_admin = in_array($_SESSION['role'], ['admin', 'HR', 'Senior Manager (Studio)']);

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $current_user_id;

// Validate dates
foreach ([&$start_date, &$end_date] as &$d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        $d = date('Y-m-d');
    }
}
unset($d);

// Build query for work reports
$query = "
    SELECT 
        a.date,
        a.work_report,
        u.username,
        u.role,
        u.unique_id
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.work_report IS NOT NULL 
    AND a.date BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

// Add user filter
if ($is_admin && $filter_user_id != 'all') {
    $query .= " AND a.user_id = ?";
    $params[] = $filter_user_id;
} else if (!$is_admin) {
    // Regular users can only see their own reports
    $query .= " AND a.user_id = ?";
    $params[] = $current_user_id;
}

$query .= " ORDER BY a.date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of dates to reports for easier lookup
$reports_by_date = [];
foreach ($reports as $report) {
    $reports_by_date[$report['date']] = $report;
}

// Fetch holidays in the date range
$holidays_query = "SELECT holiday_date, holiday_name FROM office_holidays WHERE holiday_date BETWEEN ? AND ?";
$holidays_stmt = $pdo->prepare($holidays_query);
$holidays_stmt->execute([$start_date, $end_date]);
$holidays = $holidays_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of dates to holidays for easier lookup
$holidays_by_date = [];
foreach ($holidays as $holiday) {
    $holidays_by_date[$holiday['holiday_date']] = $holiday['holiday_name'];
}

// Fetch leaves in the date range
$leaves_query = "
    SELECT 
        lr.id,
        lr.user_id,
        lr.leave_type,
        lr.start_date,
        lr.end_date,
        lr.reason,
        lr.status,
        lr.duration,
        lr.time_from,
        lr.time_to,
        u.username,
        u.role,
        u.unique_id,
        lt.name as leave_type_name
    FROM leave_request lr
    JOIN users u ON lr.user_id = u.id
    LEFT JOIN leave_types lt ON lr.leave_type = lt.id
    WHERE lr.status IN ('approved', 'pending')
    AND (
        (lr.start_date BETWEEN ? AND ?) OR
        (lr.end_date BETWEEN ? AND ?) OR
        (lr.start_date <= ? AND lr.end_date >= ?)
    )
";

$leaves_params = [$start_date, $end_date, $start_date, $end_date, $start_date, $end_date];

// Add user filter for leaves
if ($is_admin && $filter_user_id != 'all') {
    $leaves_query .= " AND lr.user_id = ?";
    $leaves_params[] = $filter_user_id;
} else if (!$is_admin) {
    // Regular users can only see their own leaves
    $leaves_query .= " AND lr.user_id = ?";
    $leaves_params[] = $current_user_id;
}

$leaves_stmt = $pdo->prepare($leaves_query);
$leaves_stmt->execute($leaves_params);
$leaves = $leaves_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of dates to leaves for easier lookup
$leaves_by_date = [];
foreach ($leaves as $leave) {
    // Calculate all dates covered by this leave
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $end->modify('+1 day'); // Include the end date
    
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval, $end);
    
    foreach ($daterange as $date) {
        $date_str = $date->format('Y-m-d');
        // Only include dates within our filter range
        if ($date_str >= $start_date && $date_str <= $end_date) {
            if (!isset($leaves_by_date[$date_str])) {
                $leaves_by_date[$date_str] = [];
            }
            $leaves_by_date[$date_str][] = $leave;
        }
    }
}

// Generate all dates in the range
$dates_in_range = [];
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    new DateTime($end_date . ' +1 day')
);

foreach ($period as $date) {
    $dates_in_range[] = $date->format('Y-m-d');
}

// Get user info for filename
$user_info = null;
if ($filter_user_id != 'all' && $filter_user_id) {
    $user_query = "SELECT username, unique_id FROM users WHERE id = ?";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$filter_user_id]);
    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
}

// Prepare Excel download
if ($filter_user_id == 'all' || !$filter_user_id) {
    $filename = 'all_work_reports_' . date('Ymd_His') . '.xls';
} else {
    $filename = 'work_reports_' . ($user_info['unique_id'] ?? 'user') . '_' . date('Ymd_His') . '.xls';
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output HTML table compatible with Excel
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        table { 
            border-collapse: collapse; 
            width: 100%; 
            font-family: 'Calibri', 'Segoe UI', sans-serif;
            font-size: 11pt;
        }
        th, td { 
            border: 1px solid #d0d7de; 
            padding: 8px 12px; 
            text-align: left; 
        }
        th { 
            background-color: #f6f8fa; 
            color: #1f2328; 
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10pt;
        }
        .header { 
            font-size: 14pt; 
            font-weight: 600; 
            margin-bottom: 15px;
            color: #1f2328;
        }
        .subtitle { 
            font-size: 10pt; 
            color: #656d76; 
            margin-bottom: 20px;
            border-bottom: 1px solid #d0d7de;
            padding-bottom: 10px;
        }
        .footer {
            font-size: 9pt;
            color: #656d76;
            margin-top: 20px;
            font-style: italic;
        }
        .no-report {
            background-color: #fff5f5;
            color: #c53030;
            font-style: italic;
        }
        .holiday {
            background-color: #fffbeb;
            color: #d97706;
            font-weight: 600;
        }
        .leave-approved {
            background-color: #f0f9ff;
            color: #0369a1;
        }
        .leave-pending {
            background-color: #fffbeb;
            color: #b45309;
        }
    </style>
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Work Reports</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <title>Work Reports Export</title>
</head>
<body>
    <div class="header">Work Reports</div>
    
    <?php
    $userText = '';
    if ($filter_user_id == 'all') {
        $userText = 'All Employees';
    } elseif ($user_info) {
        $userText = $user_info['username'] . ' (' . $user_info['unique_id'] . ')';
    } else {
        $userText = 'My Reports';
    }
    
    $rangeText = sprintf(
        'Report Period: %s to %s | %s',
        htmlspecialchars(date('d M Y', strtotime($start_date))),
        htmlspecialchars(date('d M Y', strtotime($end_date))),
        htmlspecialchars($userText)
    );
    ?>
    <div class="subtitle"><?php echo $rangeText; ?></div>
    
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Status</th>
                <th>Holiday</th>
                <th>Leave Type</th>
                <th>Leave Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dates_in_range)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; font-style: italic; color: #656d76;">Invalid date range.</td>
                </tr>
            <?php else: ?>
                <?php 
                $report_count = 0;
                $holiday_count = 0;
                $leave_count = 0;
                // Reverse the array to show dates in ascending order
                $dates_in_range = array_reverse($dates_in_range);
                foreach ($dates_in_range as $date): 
                    $report = isset($reports_by_date[$date]) ? $reports_by_date[$date] : null;
                    $holiday_name = isset($holidays_by_date[$date]) ? $holidays_by_date[$date] : null;
                    $date_leaves = isset($leaves_by_date[$date]) ? $leaves_by_date[$date] : [];
                    if ($report) $report_count++;
                    if ($holiday_name) $holiday_count++;
                    if (!empty($date_leaves)) $leave_count++;
                    
                    $dateDisplay = date('d M Y', strtotime($date));
                    $dayName = date('l', strtotime($date));
                ?>
                    <?php if ($holiday_name): ?>
                        <tr class="holiday">
                            <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td colspan="6" style="text-align: center;">Office Holiday</td>
                            <td><?php echo htmlspecialchars($holiday_name); ?></td>
                            <td>No report required</td>
                        </tr>
                    <?php elseif (!empty($date_leaves)): ?>
                        <?php foreach ($date_leaves as $leave): ?>
                        <tr class="<?php echo $leave['status'] == 'approved' ? 'leave-approved' : 'leave-pending'; ?>">
                            <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td><?php echo htmlspecialchars($leave['unique_id']); ?></td>
                            <td><?php echo htmlspecialchars($leave['username']); ?></td>
                            <td><?php echo htmlspecialchars($leave['role']); ?></td>
                            <td>Leave</td>
                            <td>No</td>
                            <td><?php echo htmlspecialchars($leave['leave_type_name'] ?? $leave['leave_type']); ?></td>
                            <td><?php echo ucfirst($leave['status']); ?></td>
                            <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php elseif ($report): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td><?php echo htmlspecialchars($report['unique_id']); ?></td>
                            <td><?php echo htmlspecialchars($report['username']); ?></td>
                            <td><?php echo htmlspecialchars($report['role']); ?></td>
                            <td>Submitted</td>
                            <td>No</td>
                            <td>No Leave</td>
                            <td>N/A</td>
                            <td><?php echo htmlspecialchars($report['work_report']); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr class="no-report">
                            <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td colspan="6" style="text-align: center;">No work report submitted for this day</td>
                            <td>No</td>
                            <td>Missing report</td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if ($report_count == 0 && $holiday_count == 0 && $leave_count == 0): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; font-style: italic; color: #656d76;">
                            <?php if ($filter_user_id == 'all'): ?>
                                No employees have submitted work reports for the selected period.
                            <?php else: ?>
                                No work reports found for the selected criteria.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        Generated on <?php echo date('d M Y H:i:s'); ?> | 
        Total Days: <?php echo count($dates_in_range); ?> | 
        Reports Submitted: <?php echo $report_count; ?> | 
        Holidays: <?php echo $holiday_count; ?> |
        Leave Days: <?php echo $leave_count; ?>
    </div>
</body>
</html>
<?php exit(); ?>