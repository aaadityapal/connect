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
if (!ctype_digit((string) $userId)) {
	http_response_code(400);
	echo json_encode([
		'success' => false,
		'message' => 'Invalid user'
	]);
	exit();
}

require_once __DIR__ . '/../../../config/db_connect.php';

try {
	$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
	$stmt->execute([':id' => $userId]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$user) {
		http_response_code(404);
		echo json_encode([
			'success' => false,
			'message' => 'User not found'
		]);
		exit();
	}

	$bankDetails = json_decode($user['bank_details'] ?? '{}', true);
	if (!is_array($bankDetails)) {
		$bankDetails = [];
	}

	$panValue = $user['pan'] ?? ($user['pan_number'] ?? '');
	if (!$panValue) {
		$panValue = $bankDetails['pan'] ?? ($bankDetails['pan_number'] ?? ($bankDetails['pan_card_number'] ?? ($bankDetails['pan_card'] ?? '')));
	}

	$response = [
		'name' => $user['username'] ?? '',
		'employee_id' => $user['employee_id'] ?? ($user['unique_id'] ?? ''),
		'designation' => $user['role'] ?? ($user['designation'] ?? ''),
		'department' => $user['department'] ?? '',
		'mobile' => $user['phone'] ?? ($user['mobile'] ?? ''),
		'email' => $user['email'] ?? '',
		'pan' => $panValue,
		'joining_date' => $user['joining_date'] ?? '',
		'bank' => $bankDetails['bank_name'] ?? '',
		'account' => $bankDetails['account_number'] ?? '',
		'ifsc' => $bankDetails['ifsc_code'] ?? '',
		'branch' => $bankDetails['branch_name'] ?? ''
	];

	echo json_encode([
		'success' => true,
		'data' => $response
	]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => 'Server error'
	]);
}
