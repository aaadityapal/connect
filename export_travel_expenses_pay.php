<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit();
}

require_once 'config/db_connect.php';

// Collect filters from query string
$filterMonth = isset($_GET['month']) ? $_GET['month'] : '';
$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filterUser = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$filterPaymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$filterFromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$filterToDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Build conditions
$conditions = [];
$params = [];

if (!empty($filterUser)) {
	$conditions[] = 'te.user_id = ?';
	$params[] = $filterUser;
}

if (!empty($filterFromDate) && !empty($filterToDate)) {
	$conditions[] = 'te.travel_date BETWEEN ? AND ?';
	$params[] = $filterFromDate;
	$params[] = $filterToDate;
} else {
	if (!empty($filterMonth)) {
		$conditions[] = 'MONTH(te.travel_date) = ?';
		$params[] = $filterMonth;
	}
	$conditions[] = 'YEAR(te.travel_date) = ?';
	$params[] = $filterYear;
}

// Restrict to the same base list shown on payout page: Approved only
$conditions[] = "te.status = 'Approved'";

// Payment status filter mirrors the UI (Paid | Pending)
if (!empty($filterPaymentStatus)) {
	if ($filterPaymentStatus === 'Paid') {
		$conditions[] = "te.payment_status = 'Paid'";
	} elseif ($filterPaymentStatus === 'Pending') {
		$conditions[] = "(te.payment_status IS NULL OR te.payment_status = 'Pending' OR te.payment_status = '')";
	}
}

$where = count($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$sql = "
    SELECT 
        te.id,
        u.username AS employee,
        u.unique_id AS employee_id,
        te.purpose,
        te.mode_of_transport AS mode,
        te.from_location,
        te.to_location,
        te.travel_date AS date,
        te.distance,
        te.amount,
        te.status,
        te.manager_status,
        te.accountant_status,
        te.hr_status,
        te.payment_status,
        te.created_at
    FROM travel_expenses te
    JOIN users u ON te.user_id = u.id
    $where
    ORDER BY te.travel_date DESC, te.user_id
";

// Build file name
$fileNameParts = ['Travel_Expenses_Payout'];
if (!empty($filterFromDate) && !empty($filterToDate)) {
	$fileNameParts[] = date('Ymd', strtotime($filterFromDate)) . '-' . date('Ymd', strtotime($filterToDate));
} else {
	if (!empty($filterMonth)) {
		$fileNameParts[] = date('M', mktime(0,0,0,(int)$filterMonth,1));
	}
	$fileNameParts[] = (string)$filterYear;
}
$fileNameParts[] = date('Ymd_His');
$fileName = implode('_', $fileNameParts) . '.xls';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

try {
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	ob_start();
	echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
	echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
	echo '<style>table{border-collapse:collapse;border:1px solid #000}th,td{border:1px solid #000;padding:5px}th{background:#f2f2f2;font-weight:bold;font-size:12pt}.paid-row{background-color:#dcfce7 !important}.pending-row{background-color:#fee2e2 !important}.report-title{font-size:18pt;font-weight:bold;margin-bottom:10px}.report-subtitle{font-size:11pt;margin-bottom:12px}.grand-total td{font-weight:bold;font-size:12pt}.grand-total-amount{font-weight:bold;font-size:14pt}</style>';
	echo '</head><body>';

	// Header text
	$header = 'Travel Expenses Payout Export';
	$sub = [];
	if (!empty($filterFromDate) && !empty($filterToDate)) {
		$sub[] = 'Date: ' . date('d M Y', strtotime($filterFromDate)) . ' - ' . date('d M Y', strtotime($filterToDate));
	} else {
		if (!empty($filterMonth)) { $sub[] = 'Month: ' . date('F', mktime(0,0,0,(int)$filterMonth,1)); }
		if (!empty($filterYear)) { $sub[] = 'Year: ' . $filterYear; }
	}
	if (!empty($filterUser)) {
		$userStmt = $pdo->prepare('SELECT username, unique_id FROM users WHERE id = ?');
		$userStmt->execute([$filterUser]);
		if ($u = $userStmt->fetch(PDO::FETCH_ASSOC)) {
			$sub[] = 'Employee: ' . $u['username'] . ' (' . $u['unique_id'] . ')';
		}
	}
	if (!empty($filterPaymentStatus)) { $sub[] = 'Payment Status: ' . $filterPaymentStatus; }

	echo '<div class="report-title">' . htmlspecialchars($header) . '</div>';
	echo '<div class="report-subtitle">' . htmlspecialchars(implode(' | ', $sub)) . '</div>';

    echo '<table><thead><tr>';
    echo '<th>ID</th><th>Employee</th><th>Employee ID</th><th>Purpose</th><th>Mode</th><th>From</th><th>To</th><th>Date</th><th>Distance (km)</th><th>Amount (₹)</th><th>Status</th><th>Payment</th><th>Created At</th>';
	echo '</tr></thead><tbody>';

    $total = 0;
    foreach ($rows as $r) {
        $total += (float)$r['amount'];
        $paymentStatusLower = strtolower((string)($r['payment_status'] ?? ''));
        $rowClass = ($paymentStatusLower === 'paid') ? 'paid-row' : 'pending-row';
        $displayPayment = ($paymentStatusLower === 'paid') ? 'Paid' : 'Pending';
        echo '<tr class="'.$rowClass.'">';
		echo '<td>'.htmlspecialchars($r['id']).'</td>';
		echo '<td>'.htmlspecialchars($r['employee']).'</td>';
		echo '<td>'.htmlspecialchars($r['employee_id']).'</td>';
		echo '<td>'.htmlspecialchars($r['purpose']).'</td>';
		echo '<td>'.htmlspecialchars($r['mode']).'</td>';
		echo '<td>'.htmlspecialchars($r['from_location']).'</td>';
		echo '<td>'.htmlspecialchars($r['to_location']).'</td>';
		echo '<td>'.htmlspecialchars(date('Y-m-d', strtotime($r['date']))).'</td>';
		echo '<td>'.htmlspecialchars($r['distance']).'</td>';
		echo '<td>'.htmlspecialchars(number_format((float)$r['amount'], 2)).'</td>';
		echo '<td>'.htmlspecialchars($r['status']).'</td>';
        echo '<td>'.htmlspecialchars($displayPayment).'</td>';
		echo '<td>'.htmlspecialchars(date('Y-m-d H:i:s', strtotime($r['created_at']))).'</td>';
		echo '</tr>';
	}

	// Summary row
	echo '<tr class="grand-total"><td colspan="9" style="text-align:right">Total</td><td class="grand-total-amount">'.htmlspecialchars(number_format($total, 2)).'</td><td colspan="3"></td></tr>';

	echo '</tbody></table></body></html>';
	ob_end_flush();
} catch (Throwable $e) {
	header('Content-Type: text/html');
	echo '<h1>Error exporting data</h1>';
	echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
}


