<?php
session_start();

// Use shared DB connection (provides $pdo and $conn)
require_once __DIR__ . '/config/db_connect.php';

// Optional: basic auth gate if your app expects login
if (!isset($_SESSION['user_id'])) {
	// header('Location: login.php'); exit;
}

// Helpers
function respondWithFlash($type, $message) {
	$_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
	if (!isset($_SESSION['flash'])) {
		return null;
	}
	$flash = $_SESSION['flash'];
	unset($_SESSION['flash']);
	return $flash;
}

// Handle Add / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = isset($_POST['action']) ? trim($_POST['action']) : '';
	$holidayName = isset($_POST['holiday_name']) ? trim($_POST['holiday_name']) : '';
	$holidayDate = isset($_POST['holiday_date']) ? trim($_POST['holiday_date']) : '';

	try {
		if ($holidayName === '' || $holidayDate === '') {
			throw new Exception('Holiday name and date are required.');
		}

		// Validate date (expecting YYYY-MM-DD)
		$dt = DateTime::createFromFormat('Y-m-d', $holidayDate);
		if (!$dt || $dt->format('Y-m-d') !== $holidayDate) {
			throw new Exception('Invalid date format. Use YYYY-MM-DD.');
		}

		if ($action === 'add') {
			$stmt = $pdo->prepare("
				INSERT INTO office_holidays (holiday_date, holiday_name, created_at, updated_at)
				VALUES (:holiday_date, :holiday_name, NOW(), NOW())
			");
			$stmt->execute([
				':holiday_date' => $holidayDate,
				':holiday_name' => $holidayName
			]);
			respondWithFlash('success', 'Holiday added successfully.');
			header('Location: ' . basename(__FILE__));
			exit;
		} elseif ($action === 'update') {
			$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
			if ($id <= 0) {
				throw new Exception('Invalid holiday ID.');
			}
			$stmt = $pdo->prepare("
				UPDATE office_holidays
				SET holiday_date = :holiday_date,
					holiday_name = :holiday_name,
					updated_at = NOW()
				WHERE id = :id
			");
			$stmt->execute([
				':holiday_date' => $holidayDate,
				':holiday_name' => $holidayName,
				':id' => $id
			]);
			respondWithFlash('success', 'Holiday updated successfully.');
			header('Location: ' . basename(__FILE__));
			exit;
		} else {
			throw new Exception('Unknown action.');
		}
	} catch (Exception $e) {
		respondWithFlash('error', $e->getMessage());
		// If editing, preserve query string for edit mode
		$redirect = basename(__FILE__);
		if (!empty($_POST['id'])) {
			$redirect .= '?edit_id=' . urlencode((string)$_POST['id']);
		}
		header('Location: ' . $redirect);
		exit;
	}
}

// If edit_id is present, load that holiday
$editHoliday = null;
if (isset($_GET['edit_id'])) {
	$editId = (int)$_GET['edit_id'];
	if ($editId > 0) {
		$stmt = $pdo->prepare("SELECT id, holiday_date, holiday_name FROM office_holidays WHERE id = :id");
		$stmt->execute([':id' => $editId]);
		$editHoliday = $stmt->fetch();
	}
}

// Fetch all holidays
$holidays = [];
try {
	$query = "SELECT id, holiday_date, holiday_name, created_at, updated_at
		FROM office_holidays
		ORDER BY holiday_date DESC, id DESC";
	$holidays = $pdo->query($query)->fetchAll();
} catch (Exception $e) {
	respondWithFlash('error', 'Failed to fetch holidays: ' . $e->getMessage());
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Office Holidays</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/modern-normalize/2.0.0/modern-normalize.min.css">
	<style>
		body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#f5f6f8; color:#111; }
		.container { max-width: 1100px; margin: 32px auto; padding: 0 16px; }
		.card { background:#fff; border-radius:12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); padding:20px; }
		h1 { margin: 0 0 16px 0; font-size: 22px; }
		.grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
		@media (min-width: 900px) { .grid { grid-template-columns: 360px 1fr; } }
		.form-row { display:flex; gap:12px; }
		.input { width:100%; padding:10px 12px; border:1px solid #dcdfe4; border-radius:8px; font-size:14px; }
		.label { font-size:12px; color:#555; margin-bottom:6px; display:block; }
		.btn { display:inline-block; padding:10px 14px; border-radius:8px; border:0; cursor:pointer; font-weight:600; }
		.btn-primary { background:#2563eb; color:#fff; }
		.btn-secondary { background:#6b7280; color:#fff; }
		.btn-link { background:transparent; border:0; color:#2563eb; padding:0; cursor:pointer; }
		.table { width:100%; border-collapse: collapse; }
		.table th, .table td { padding:12px 10px; border-bottom:1px solid #eef0f3; font-size:14px; text-align:left; }
		.table th { color:#555; font-weight:600; background:#fafbfc; }
		.badge { background:#eef2ff; color:#3730a3; padding:4px 8px; border-radius:999px; font-size:12px; }
		.flash { padding:12px 14px; border-radius:8px; margin-bottom:16px; }
		.flash.success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
		.flash.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
		.actions a { margin-right:12px; text-decoration:none; font-weight:600; }
	</style>
	<!-- If your app has a shared header CSS/JS, you can include it here -->
</head>
<body>
	<div class="container">
		<?php if ($flash): ?>
			<div class="flash <?php echo htmlspecialchars($flash['type']); ?>">
				<?php echo htmlspecialchars($flash['message']); ?>
			</div>
		<?php endif; ?>

		<div class="grid">
			<div class="card">
				<h1><?php echo $editHoliday ? 'Edit Holiday' : 'Add New Holiday'; ?></h1>
				<form method="post" action="<?php echo htmlspecialchars(basename(__FILE__)); ?>">
					<?php if ($editHoliday): ?>
						<input type="hidden" name="action" value="update">
						<input type="hidden" name="id" value="<?php echo (int)$editHoliday['id']; ?>">
					<?php else: ?>
						<input type="hidden" name="action" value="add">
					<?php endif; ?>
					
					<div class="form-group" style="margin-bottom:12px;">
						<label class="label" for="holiday_name">Holiday Name</label>
						<input class="input" type="text" id="holiday_name" name="holiday_name" required maxlength="255"
							placeholder="e.g., Republic Day"
							value="<?php echo htmlspecialchars($editHoliday['holiday_name'] ?? ''); ?>">
					</div>
					
					<div class="form-row" style="margin-bottom:12px;">
						<div style="flex:1;">
							<label class="label" for="holiday_date">Holiday Date</label>
							<input class="input" type="date" id="holiday_date" name="holiday_date" required
								value="<?php echo htmlspecialchars($editHoliday['holiday_date'] ?? ''); ?>">
						</div>
					</div>

					<div style="display:flex; gap:8px;">
						<button type="submit" class="btn btn-primary">
							<?php echo $editHoliday ? 'Update Holiday' : 'Add Holiday'; ?>
						</button>
						<?php if ($editHoliday): ?>
							<a class="btn btn-secondary" href="<?php echo htmlspecialchars(basename(__FILE__)); ?>">Cancel</a>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<div class="card">
				<h1>All Holidays <span class="badge"><?php echo count($holidays); ?></span></h1>
				<div style="overflow:auto;">
					<table class="table">
						<thead>
							<tr>
								<th>ID</th>
								<th>Date</th>
								<th>Name</th>
								<th>Created</th>
								<th>Updated</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!$holidays): ?>
								<tr><td colspan="6" style="text-align:center; color:#6b7280;">No holidays found</td></tr>
							<?php else: ?>
								<?php foreach ($holidays as $h): ?>
									<tr>
										<td><?php echo (int)$h['id']; ?></td>
										<td><?php echo htmlspecialchars($h['holiday_date']); ?></td>
										<td><?php echo htmlspecialchars($h['holiday_name']); ?></td>
										<td><?php echo htmlspecialchars($h['created_at']); ?></td>
										<td><?php echo htmlspecialchars($h['updated_at']); ?></td>
										<td class="actions">
											<a href="<?php echo htmlspecialchars(basename(__FILE__) . '?edit_id=' . (int)$h['id']); ?>" class="btn-link">Edit</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</body>
</html>


