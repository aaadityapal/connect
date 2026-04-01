<?php
// CRITICAL: Ensure no whitespace or output before this tag
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Unauthorized - No session found';
    exit();
}

$current_user_id = $_SESSION['user_id'];
$is_admin = in_array($_SESSION['role'], ['admin', 'HR', 'Senior Manager (Studio)']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $current_user_id;

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

if ($is_admin && $filter_user_id != 'all') {
    $query .= " AND a.user_id = ?";
    $params[] = $filter_user_id;
} else if (!$is_admin) {
    $query .= " AND a.user_id = ?";
    $params[] = $current_user_id;
}
$query .= " ORDER BY a.date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reports_by_date = [];
foreach ($reports as $report) {
    $reports_by_date[$report['date']] = $report;
}

$holidays_query = "SELECT holiday_date, holiday_name FROM office_holidays WHERE holiday_date BETWEEN ? AND ?";
$holidays_stmt = $pdo->prepare($holidays_query);
$holidays_stmt->execute([$start_date, $end_date]);
$holidays = $holidays_stmt->fetchAll(PDO::FETCH_ASSOC);

$holidays_by_date = [];
foreach ($holidays as $holiday) {
    $holidays_by_date[$holiday['holiday_date']] = $holiday['holiday_name'];
}

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
    WHERE LOWER(lr.status) = 'approved'
    AND (
        (lr.start_date BETWEEN ? AND ?) OR
        (lr.end_date BETWEEN ? AND ?) OR
        (lr.start_date <= ? AND lr.end_date >= ?)
    )
";

$leaves_params = [$start_date, $end_date, $start_date, $end_date, $start_date, $end_date];

if ($is_admin && $filter_user_id != 'all') {
    $leaves_query .= " AND lr.user_id = ?";
    $leaves_params[] = $filter_user_id;
} else if (!$is_admin) {
    $leaves_query .= " AND lr.user_id = ?";
    $leaves_params[] = $current_user_id;
}

$leaves_stmt = $pdo->prepare($leaves_query);
$leaves_stmt->execute($leaves_params);
$leaves = $leaves_stmt->fetchAll(PDO::FETCH_ASSOC);

$leaves_by_date = [];
foreach ($leaves as $leave) {
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $end->modify('+1 day');
    
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval, $end);
    
    foreach ($daterange as $date) {
        $date_str = $date->format('Y-m-d');
        if ($date_str >= $start_date && $date_str <= $end_date) {
            if (!isset($leaves_by_date[$date_str])) {
                $leaves_by_date[$date_str] = [];
            }
            $leaves_by_date[$date_str][] = $leave;
        }
    }
}

$dates_in_range = [];
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    new DateTime($end_date . ' +1 day')
);

foreach ($period as $date) {
    $dates_in_range[] = $date->format('Y-m-d');
}

$user_info = null;
if ($filter_user_id != 'all' && $filter_user_id) {
    $user_query = "SELECT username, unique_id, role FROM users WHERE id = ?";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$filter_user_id]);
    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
}

$month_name = date('F', strtotime($start_date));
if ($filter_user_id == 'all' || !$filter_user_id) {
    $filename = "AllUsers_{$month_name}_workreport.pdf";
} else {
    $safe_username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $user_info['username'] ?? 'User');
    $filename = "{$safe_username}_{$month_name}_workreport.pdf";
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #1f2328; margin: 0; padding: 0; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; table-layout: fixed; }
        th, td { border: 1px solid #d0d7de; padding: 8px 10px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background-color: #f6f8fa; font-weight: bold; text-transform: uppercase; font-size: 9pt; color: #1f2328; }
        .header { font-size: 18pt; font-weight: bold; margin-bottom: 5px; color: #111; }
        .subtitle { font-size: 11pt; color: #656d76; margin-bottom: 20px; border-bottom: 2px solid #eaeaea; padding-bottom: 15px; }
        .footer { font-size: 9pt; color: #656d76; margin-top: 20px; text-align: center; border-top: 1px solid #eaeaea; padding-top: 10px; }
        .holiday td { background-color: #fffbeb; color: #b45309; }
        .no-report td { background-color: #fff5f5; color: #b91c1c; }
        .leave-row td { background-color: #f0f9ff; color: #0369a1; }
    </style>
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
        'Report Period: <strong>%s</strong> to <strong>%s</strong> &nbsp;|&nbsp; Employee: <strong>%s</strong>',
        htmlspecialchars(date('d M Y', strtotime($start_date))),
        htmlspecialchars(date('d M Y', strtotime($end_date))),
        htmlspecialchars($userText)
    );
    ?>
    <div class="subtitle"><?php echo $rangeText; ?></div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Date</th>
                <th style="width: 60px;">Day</th>
                <th style="width: 80px;">Emp ID</th>
                <th style="width: 120px;">Name</th>
                <th style="width: 80px;">Status</th>
                <th>Work Report / Leave Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dates_in_range)): ?>
                <tr><td colspan="6" style="text-align: center;">Invalid date range.</td></tr>
            <?php else: ?>
                <?php 
                $report_count = 0;
                $holiday_count = 0;
                $leave_count = 0;
                $dates_in_range_sorted = array_reverse($dates_in_range);
                foreach ($dates_in_range_sorted as $date): 
                    $report = isset($reports_by_date[$date]) ? $reports_by_date[$date] : null;
                    $holiday_name = isset($holidays_by_date[$date]) ? $holidays_by_date[$date] : null;
                    $date_leaves = isset($leaves_by_date[$date]) ? $leaves_by_date[$date] : [];
                    
                    $u_id = $report['unique_id'] ?? ($date_leaves[0]['unique_id'] ?? ($user_info['unique_id'] ?? '-'));
                    $u_name = $report['username'] ?? ($date_leaves[0]['username'] ?? ($user_info['username'] ?? '-'));

                    if ($report) $report_count++;
                    if ($holiday_name) $holiday_count++;
                    if (!empty($date_leaves)) $leave_count++;
                    
                    $dateDisplay = date('d M', strtotime($date));
                    $dayName = date('D', strtotime($date));
                ?>
                    <?php if ($holiday_name): ?>
                        <tr class="holiday">
                            <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td><?php echo htmlspecialchars($u_id); ?></td>
                            <td><?php echo htmlspecialchars($u_name); ?></td>
                            <td><strong>Holiday</strong></td>
                            <td><?php echo htmlspecialchars($holiday_name); ?></td>
                        </tr>
                    <?php elseif (!empty($date_leaves)): ?>
                        <?php foreach ($date_leaves as $leave): ?>
                        <tr class="leave-row">
                            <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td><?php echo htmlspecialchars($leave['unique_id']); ?></td>
                            <td><?php echo htmlspecialchars($leave['username']); ?></td>
                            <td><strong>Leave</strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($leave['leave_type_name'] ?? $leave['leave_type']); ?>:</strong> 
                                <?php echo htmlspecialchars($leave['reason']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php elseif ($report): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td><?php echo htmlspecialchars($u_id); ?></td>
                            <td><?php echo htmlspecialchars($u_name); ?></td>
                            <td><strong>Present</strong></td>
                            <td style="line-height: 1.4;"><?php echo htmlspecialchars($report['work_report']); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr class="no-report">
                            <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td><?php echo htmlspecialchars($u_id); ?></td>
                            <td><?php echo htmlspecialchars($u_name); ?></td>
                            <td><strong>Absent</strong></td>
                            <td><em>Missing report</em></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        Generated on <?php echo date('d M Y H:i:s'); ?> &nbsp;|&nbsp; 
        Reports Submitted: <?php echo $report_count; ?> &nbsp;|&nbsp; 
        Holidays: <?php echo $holiday_count; ?> &nbsp;|&nbsp; 
        Leave Days: <?php echo $leave_count; ?>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Clear any accidental output
if (ob_get_length()) ob_end_clean();

$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

try {
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($filename, ["Attachment" => true]);
} catch (Exception $e) {
    echo "PDF Generation Error: " . $e->getMessage();
}
exit();
?>
