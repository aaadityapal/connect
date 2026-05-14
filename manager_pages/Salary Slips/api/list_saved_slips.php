<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit;
}

$baseDir = __DIR__ . '/../../../uploads/salary_slips/';
$archives = [];

if (file_exists($baseDir) && is_dir($baseDir)) {
	$userFolders = array_diff(scandir($baseDir), ['.', '..']);
	foreach ($userFolders as $folder) {
		$folderPath = $baseDir . $folder;
		if (is_dir($folderPath)) {
			// Extract user display name from folder name (e.g., Aditya_Kumar_Pal_21 -> Aditya Kumar Pal)
			$parts = explode('_', $folder);
			$userId = is_numeric(end($parts)) ? array_pop($parts) : '';
			$userName = implode(' ', $parts);
			if (empty(trim($userName))) {
				$userName = $folder;
			}

			$slips = [];
			$files = array_diff(scandir($folderPath), ['.', '..']);
			foreach ($files as $file) {
				if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
					// Parse nice readable title from filename Salary_Slip_4_2026_130805.html
					$title = $file;
					$monthYear = '';
					if (preg_match('/Salary_Slip_([a-zA-Z0-9]+)_([0-9]{4})/', $file, $matches)) {
						$months = [
							1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
							5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
							9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
						];
						$m = is_numeric($matches[1]) ? (int)$matches[1] : $matches[1];
						$monthName = $months[$m] ?? $matches[1];
						$title = "$monthName {$matches[2]}";
						$monthYear = "{$matches[2]}-" . (is_numeric($m) ? str_pad($m, 2, '0', STR_PAD_LEFT) : $m);
					}

					$filePath = $folderPath . '/' . $file;
					$slips[] = [
						'filename' => $file,
						'title' => $title,
						'sort_key' => $monthYear ?: filemtime($filePath),
						'size' => round(filesize($filePath) / 1024) . ' KB',
						'date' => date('d M Y, h:i A', filemtime($filePath)),
						'url' => '../../uploads/salary_slips/' . rawurlencode($folder) . '/' . rawurlencode($file)
					];
				}
			}

			if (!empty($slips)) {
				// Sort slips descending by sort_key
				usort($slips, function($a, $b) {
					return $b['sort_key'] <=> $a['sort_key'];
				});

				$archives[] = [
					'user_id' => $userId,
					'user_name' => ucwords(strtolower($userName)),
					'folder' => $folder,
					'slips' => $slips
				];
			}
		}
	}
}

// Sort user groups alphabetically
usort($archives, function($a, $b) {
	return strcasecmp($a['user_name'], $b['user_name']);
});

echo json_encode([
	'success' => true,
	'data' => $archives
]);
