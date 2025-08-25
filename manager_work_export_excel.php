<?php
session_start();
require_once 'config.php';

// Authorization: Only HR and Senior Manager (Studio)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR', 'Senior Manager (Studio)'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}

// Read filters (defaults match work_report.php)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$user_id    = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

// Basic validation for dates (fallback to safe defaults)
foreach ([&$start_date, &$end_date] as &$d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        $d = date('Y-m-d');
    }
}
unset($d);

// Build query
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
if ($user_id !== '') {
    $query .= " AND a.user_id = ?";
    $params[] = $user_id;
}

$query .= " ORDER BY a.date DESC, u.username ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare Excel download
$filename = 'work_reports_' . date('Ymd_His') . '.xls';
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
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 11pt; }
        th { background-color: #4472C4; color: #fff; font-weight: 700; }
        .subtitle { margin: 10px 0; font-weight: 600; }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    </head>
<body>
    <?php
    $rangeText = sprintf(
        'Showing reports from %s to %s %s',
        htmlspecialchars(date('d M Y', strtotime($start_date))),
        htmlspecialchars(date('d M Y', strtotime($end_date))),
        ($user_id !== '' ? '(Filtered by selected employee)' : '(All employees)')
    );
    ?>
    <div class="subtitle"><?php echo $rangeText; ?></div>
    <table>
        <tr>
            <th>Unique ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Date</th>
            <th>Day</th>
            <th>Work Report</th>
        </tr>
        <?php if (empty($reports)): ?>
            <tr>
                <td colspan="6">No work reports found for the selected criteria.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($reports as $row): ?>
                <?php
                    $dateStr = $row['date'];
                    $dateDisplay = date('d M Y', strtotime($dateStr));
                    $dayName = date('l', strtotime($dateStr));
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['unique_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['role']); ?></td>
                    <td><?php echo htmlspecialchars($dateDisplay); ?></td>
                    <td><?php echo htmlspecialchars($dayName); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($row['work_report'])); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>
</html>
<?php exit(); ?>
