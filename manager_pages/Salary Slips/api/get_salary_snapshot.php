<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode([
		'success' => false,
		'message' => 'Unauthorized'
	]);
	exit();
}

$userId = $_GET['user_id'] ?? '';
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

if (!ctype_digit((string) $userId)) {
	http_response_code(400);
	echo json_encode([
		'success' => false,
		'message' => 'Invalid user'
	]);
	exit();
}

if ($month < 1 || $month > 12 || $year < 2000 || $year > (int) date('Y') + 5) {
	http_response_code(400);
	echo json_encode([
		'success' => false,
		'message' => 'Invalid month or year'
	]);
	exit();
}

require_once __DIR__ . '/../../../config/db_connect.php';

function getIndianCurrency($number) {
	$no = floor($number);
	$point = round($number - $no, 2) * 100;
	$hundred = null;
	$digits_1 = strlen($no);
	$i = 0;
	$str = array();
	$words = array('0' => '', '1' => 'one', '2' => 'two',
		'3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
		'7' => 'seven', '8' => 'eight', '9' => 'nine',
		'10' => 'ten', '11' => 'eleven', '12' => 'twelve',
		'13' => 'thirteen', '14' => 'fourteen',
		'15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
		'18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
		'30' => 'thirty', '40' => 'forty', '50' => 'fifty',
		'60' => 'sixty', '70' => 'seventy',
		'80' => 'eighty', '90' => 'ninety');
	$digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
	while ($i < $digits_1) {
		$divider = ($i == 2) ? 10 : 100;
		$number = floor($no % $divider);
		$no = floor($no / $divider);
		$i += ($divider == 10) ? 1 : 2;
		if ($number) {
			$plural = (($counter = count($str)) && $number > 9) ? 's' : null;
			$hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
			$str [] = ($number < 21) ? $words[$number] .
				" " . $digits[$counter] . $plural . " " . $hundred
				:
				$words[floor($number / 10) * 10]
				. " " . $words[$number % 10] . " "
				. $digits[$counter] . $plural . " " . $hundred;
		} else $str[] = null;
	}
	$str = array_reverse($str);
	$result = implode('', $str);
	$paise = ($point) ?
		"." . $words[$point / 10] . " " .
		$words[$point = $point % 10] : '';
	return ucwords($result) . "Rupees" . ($paise ? " and " . $paise . " Paise" : "");
}

$tableName = 'employee_salary_snapshot_records_20260513';

try {
	$stmt = $pdo->prepare("SELECT total_payable_salary, total_tds_amount FROM `$tableName` WHERE user_id = :user_id AND month = :month AND year = :year LIMIT 1");
	$stmt->execute([
		':user_id' => (int) $userId,
		':month' => $month,
		':year' => $year
	]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$row) {
		$stmt = $pdo->prepare("SELECT total_payable_salary, total_tds_amount FROM `$tableName` WHERE user_id = :user_id ORDER BY year DESC, month DESC LIMIT 1");
		$stmt->execute([':user_id' => (int) $userId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
	}

	if (!$row) {
		echo json_encode([
			'success' => true,
			'data' => [
				'fees_amount' => '',
				'tds_amount' => '',
				'net_amount' => '',
				'net_words' => ''
			]
		]);
		exit();
	}

	$fees = (float) $row['total_payable_salary'];
	$tds = (float) $row['total_tds_amount'];
	$net = $fees - $tds;

	echo json_encode([
		'success' => true,
		'data' => [
			'fees_amount' => number_format($fees, 0),
			'tds_amount' => number_format($tds, 0),
			'net_amount' => number_format($net, 0),
			'net_words' => getIndianCurrency($net)
		]
	]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => 'Server error'
	]);
}
