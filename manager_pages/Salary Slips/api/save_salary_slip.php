<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed']);
	exit;
}

if (!isset($_FILES['html_file']) || !is_array($_FILES['html_file'])) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'HTML file payload is required']);
	exit;
}

$file = $_FILES['html_file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'File upload processing failed']);
	exit;
}

$userId = trim((string) ($_POST['user_id'] ?? ''));
$userName = trim((string) ($_POST['user_name'] ?? ''));
$month = trim((string) ($_POST['month'] ?? ''));
$year = trim((string) ($_POST['year'] ?? ''));

if ($userId === '') {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'User ID is required']);
	exit;
}

// Prepare user-wise upload directory path
$safeUserName = preg_replace('/[^a-zA-Z0-9]/', '_', $userName);
if ($safeUserName === '') {
	$safeUserName = 'user';
}
$userDirName = $safeUserName . '_' . $userId;
$uploadDir = __DIR__ . '/../../../uploads/salary_slips/' . $userDirName . '/';

if (!file_exists($uploadDir)) {
	if (!mkdir($uploadDir, 0777, true)) {
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Failed to initialize user storage directory']);
		exit;
	}
}

$requestedName = trim((string) ($_FILES['html_file']['name'] ?? ''));
if ($requestedName === '') {
	$safeMonth = $month !== '' ? preg_replace('/[^a-zA-Z0-9]/', '', $month) : 'Month';
	$safeYear = $year !== '' ? preg_replace('/[^0-9]/', '', $year) : date('Y');
	$requestedName = "Salary_Slip_{$safeMonth}_{$safeYear}.html";
}

$baseName = pathinfo($requestedName, PATHINFO_FILENAME);
$safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
$finalName = $safeBase . '_' . date('His') . '.html';
$targetPath = $uploadDir . $finalName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to persist salary slip record']);
	exit;
}

echo json_encode([
	'success' => true,
	'filename' => $finalName,
	'user_folder' => $userDirName,
	'path' => $targetPath,
	'message' => 'Salary slip successfully archived'
]);
