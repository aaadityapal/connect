<?php
session_start();
if (!isset($_SESSION['user_id'])) {
	header("Location: ../../login.php");
	exit();
}

require_once __DIR__ . '/../../config/db_connect.php';

$currentMonth = (int) date('n');
$currentYear = (int) date('Y');
$monthNames = [
	1 => 'January',
	2 => 'February',
	3 => 'March',
	4 => 'April',
	5 => 'May',
	6 => 'June',
	7 => 'July',
	8 => 'August',
	9 => 'September',
	10 => 'October',
	11 => 'November',
	12 => 'December'
];

$activeUsers = [];
try {
	$stmt = $pdo->prepare("SELECT id, username, employee_id FROM users WHERE deleted_at IS NULL AND LOWER(status) = 'active' ORDER BY username ASC");
	$stmt->execute();
	$activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$activeUsers = [];
}

// Placeholder data until DB integration is wired in.
$companyLogo = "assets/company-logo.png";
$companyName = "ArchitectsHive";

$employee = [
	"name" => "--",
	"employee_id" => "--",
	"designation" => "--",
	"department" => "--",
	"mobile" => "--",
	"email" => "--",
	"pan" => "--",
	"bank" => "--",
	"account" => "--",
	"ifsc" => "--",
	"branch" => "--",
	"joining_date" => "--",
];

$salarySlip = [
	"period" => "December 2026",
	"slip_no" => "SLIP-2026-12-0007",
	"paid_days" => "30",
	"working_days" => "30",
	"earnings" => [
		["label" => "Basic Salary", "amount" => 42000],
		["label" => "HRA", "amount" => 18000],
		["label" => "Conveyance", "amount" => 1600],
		["label" => "Special Allowance", "amount" => 6400],
	],
	"deductions" => [
		["label" => "PF", "amount" => 1800],
		["label" => "Professional Tax", "amount" => 200],
		["label" => "TDS", "amount" => 1500],
	],
];

$earningsTotal = array_sum(array_column($salarySlip["earnings"], "amount"));
$deductionsTotal = array_sum(array_column($salarySlip["deductions"], "amount"));
$netPay = $earningsTotal - $deductionsTotal;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Salary Slips | Connect</title>
	<link rel="stylesheet" href="../employees_profile/style.css">
	<link rel="stylesheet" href="css/salary-slips.css">
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
	<script>
		window.SIDEBAR_BASE_PATH = '../../studio_users/';
	</script>
	<script src="../../studio_users/components/sidebar-loader.js" defer></script>
	<script src="js/salary-slips.js" defer></script>
</head>
<body class="el-1">
	<div class="dashboard-container">
		<div id="sidebar-mount"></div>

		<main class="main-content">
			<header class="dh-nav-header">
				<div class="dh-nav-left" style="display:flex;align-items:center;gap:0.75rem;">
					<button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
						<i data-lucide="menu" style="width:18px;height:18px;"></i>
					</button>
					<div>
						<div class="dh-user-info">
							<div class="dh-icon-orange">
								<i data-lucide="file-text" style="width:15px;height:15px;"></i>
							</div>
							<div class="dh-greeting">
								<span class="dh-greeting-text">Salary Slips</span>
								<span class="dh-greeting-name">Manager</span>
							</div>
						</div>
					</div>
				</div>
				<div class="dh-nav-right">
					<div class="dh-profile-box" id="profileDropdownContainer">
						<div class="dh-profile-avatar" id="profileAvatarBtn">
							<i data-lucide="user" style="width:17px;height:17px;"></i>
						</div>
					</div>
				</div>
			</header>

			<div class="page-shell">
				<div class="page-card">
					<h2 style="margin:0;">Salary Slips</h2>
					<p style="margin:0.6rem 0 0;color:#64748b;">Preview the current month salary slip and employee details.</p>
					<div class="export-actions">
						<div class="filters-row">
							<div class="filter-item">
								<label class="select-label" for="salaryMonth">Month</label>
								<select id="salaryMonth" class="employee-select">
									<?php foreach ($monthNames as $value => $label): ?>
										<option value="<?php echo $value; ?>" <?php echo $value === $currentMonth ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="filter-item">
								<label class="select-label" for="salaryYear">Year</label>
								<select id="salaryYear" class="employee-select">
									<?php for ($year = $currentYear - 5; $year <= $currentYear + 1; $year++): ?>
										<option value="<?php echo $year; ?>" <?php echo $year === $currentYear ? 'selected' : ''; ?>>
											<?php echo $year; ?>
										</option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
						<button class="export-btn" id="exportPdfBtn" type="button">Export to PDF</button>
					</div>

					<div class="salary-grid">
						<section class="info-panel">
							<h3>Employee Details</h3>
							<div class="info-list">
								<div class="info-item">
									<label class="select-label" for="employeeSelect">Select Employee</label>
									<select id="employeeSelect" class="employee-select">
										<option value="">Choose active employee</option>
										<?php foreach ($activeUsers as $user): ?>
											<option value="<?php echo htmlspecialchars($user['id']); ?>">
												<?php
													$label = $user['username'] ?? 'User';
													$empId = $user['employee_id'] ?? '';
													echo htmlspecialchars($empId ? "{$label} ({$empId})" : $label);
												?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="info-item">
									<span>Name</span>
									<strong id="detailName" data-field="name"><?php echo htmlspecialchars($employee["name"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Employee ID</span>
									<strong id="detailEmployeeId" data-field="employee_id"><?php echo htmlspecialchars($employee["employee_id"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Designation</span>
									<strong id="detailDesignation" data-field="designation"><?php echo htmlspecialchars($employee["designation"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Department</span>
									<strong id="detailDepartment" data-field="department"><?php echo htmlspecialchars($employee["department"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Mobile</span>
									<strong id="detailMobile" data-field="mobile"><?php echo htmlspecialchars($employee["mobile"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Email</span>
									<strong id="detailEmail" data-field="email"><?php echo htmlspecialchars($employee["email"]); ?></strong>
								</div>
								<div class="info-item">
									<span>PAN</span>
									<strong id="detailPan" data-field="pan"><?php echo htmlspecialchars($employee["pan"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Bank</span>
									<strong id="detailBank" data-field="bank"><?php echo htmlspecialchars($employee["bank"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Account</span>
									<strong id="detailAccount" data-field="account"><?php echo htmlspecialchars($employee["account"]); ?></strong>
								</div>
								<div class="info-item">
									<span>IFSC</span>
									<strong id="detailIfsc" data-field="ifsc"><?php echo htmlspecialchars($employee["ifsc"]); ?></strong>
								</div>
							</div>
						</section>

						<section class="slp-preview" id="pdfSlip">
							<img class="slp-logo" src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Company logo">

							<div class="slp-period-line" data-field="period_line">
								CONSULTANCY FEE FOR THE MONTH OF <?php echo strtoupper($monthNames[$currentMonth] . ' ' . $currentYear); ?>
							</div>

							<div class="slp-block">
								<div class="slp-partitions">
									<div class="slp-left">
										<div class="field-row"><span>Name:</span><strong id="slipName" data-field="name"><?php echo htmlspecialchars($employee["name"]); ?></strong></div>
										<div class="field-row"><span>M. No.:</span><strong id="slipMobile" data-field="mobile"><?php echo htmlspecialchars($employee["mobile"]); ?></strong></div>
										<div class="field-row"><span>Email ID:</span><strong id="slipEmail" data-field="email"><?php echo htmlspecialchars($employee["email"]); ?></strong></div>
										<div class="field-row"><span>Employee ID:</span><strong id="slipEmployeeId" data-field="employee_id"><?php echo htmlspecialchars($employee["employee_id"]); ?></strong></div>
										<div class="field-row"><span>Joining Date:</span><strong id="slipJoiningDate" data-field="joining_date"><?php echo htmlspecialchars($employee["joining_date"]); ?></strong></div>
									</div>
									<div class="slp-right">
										<div class="field-row"><span>Designation:</span><strong id="slipDesignation" data-field="designation"><?php echo htmlspecialchars($employee["designation"]); ?></strong></div>
										<div class="field-row"><span>PAN:</span><strong id="slipPan" data-field="pan"><?php echo htmlspecialchars($employee["pan"]); ?></strong></div>
										<div class="field-row"><span>Bank A/c No:</span><strong id="slipAccount" data-field="account"><?php echo htmlspecialchars($employee["account"]); ?></strong></div>
										<div class="field-row"><span>IFSC:</span><strong id="slipIfsc" data-field="ifsc"><?php echo htmlspecialchars($employee["ifsc"]); ?></strong></div>
										<div class="field-row"><span>Bank Branch:</span><strong id="slipBranch" data-field="branch"><?php echo htmlspecialchars($employee["branch"]); ?></strong></div>
									</div>
								</div>

								<div class="amount-section">
									<div class="amount-due">Amount due for&nbsp;&nbsp;General Consultancy Services rendered</div>
									<div class="amount-total">Total: <strong>Rs. <span data-field="net_amount">--</span></strong></div>
								</div>

								<div class="signature-section">
									<div class="signature-label">Signature&nbsp;&nbsp;<span class="sig-line">________________________</span></div>
								</div>

								<div class="receipt-section">
									<div class="receipt-title">RECEIPT</div>
									<p class="receipt-text">
										Received with thanks from M/S ArchitectsHive a sum of Rs. <span data-field="net_amount">--</span>/-
										( Rupees <span data-field="net_words">--</span> Only) through IMPS to A/C No. <span data-field="account">--</span>,
										<span data-field="bank">--</span> <span data-field="branch">--</span> towards monthly consultancy fee:
									</p>
									<table class="receipt-table">
										<tr>
											<td>FEES:</td>
											<td class="receipt-amount">Rs. <span data-field="fees_amount">--</span></td>
										</tr>
										<tr>
											<td>TDS:</td>
											<td class="receipt-amount">Rs. <span data-field="tds_amount">--</span></td>
										</tr>
										<tr class="receipt-note-row">
											<td colspan="2"><span class="receipt-note">(As / Section (b) 194)</span></td>
										</tr>
										<tr class="receipt-total">
											<td>NET PAYABLE:</td>
											<td class="receipt-amount">Rs. <span data-field="net_amount">--</span></td>
										</tr>
									</table>
									<div class="receipt-signature">Signature&nbsp;&nbsp;<span class="sig-line">________________________</span></div>
								</div>
							</div>

							<div class="slp-footer-block">
								<div class="footer-line"></div>
								<div class="footer-content">
									<div class="footer-left"></div>
									<div class="footer-right">
										F-52, First Floor, Near Gurudwara, Madhu Vihar,<br>
										I. P. Extension, Delhi-110092 Phone: 9958600397, 7503477154<br>
										E-Mail: <a href="mailto:contact@architectshive.com">contact@architectshive.com</a> Website: <a href="https://www.architectshive.com" target="_blank">www.architectshive.com</a>
									</div>
								</div>
							</div>

							<div class="scissors-line" aria-hidden="true">
								<span class="scissors-icon">&#9986;</span>
							</div>

							<div class="slp-duplicate">
								<img class="slp-logo" src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Company logo">

									<div class="slp-period-line" data-field="period_line">
										CONSULTANCY FEE FOR THE MONTH OF <?php echo strtoupper($monthNames[$currentMonth] . ' ' . $currentYear); ?>
									</div>

								<div class="slp-block">
									<div class="slp-partitions">
										<div class="slp-left">
											<div class="field-row"><span>Name:</span><strong data-field="name"><?php echo htmlspecialchars($employee["name"]); ?></strong></div>
											<div class="field-row"><span>M. No.:</span><strong data-field="mobile"><?php echo htmlspecialchars($employee["mobile"]); ?></strong></div>
											<div class="field-row"><span>Email ID:</span><strong data-field="email"><?php echo htmlspecialchars($employee["email"]); ?></strong></div>
											<div class="field-row"><span>Employee ID:</span><strong data-field="employee_id"><?php echo htmlspecialchars($employee["employee_id"]); ?></strong></div>
											<div class="field-row"><span>Joining Date:</span><strong data-field="joining_date"><?php echo htmlspecialchars($employee["joining_date"]); ?></strong></div>
										</div>
										<div class="slp-right">
											<div class="field-row"><span>Designation:</span><strong data-field="designation"><?php echo htmlspecialchars($employee["designation"]); ?></strong></div>
											<div class="field-row"><span>PAN:</span><strong data-field="pan"><?php echo htmlspecialchars($employee["pan"]); ?></strong></div>
											<div class="field-row"><span>Bank A/c No:</span><strong data-field="account"><?php echo htmlspecialchars($employee["account"]); ?></strong></div>
											<div class="field-row"><span>IFSC:</span><strong data-field="ifsc"><?php echo htmlspecialchars($employee["ifsc"]); ?></strong></div>
											<div class="field-row"><span>Bank Branch:</span><strong data-field="branch"><?php echo htmlspecialchars($employee["branch"]); ?></strong></div>
										</div>
									</div>

									<div class="amount-section">
										<div class="amount-due">Amount due for&nbsp;&nbsp;General Consultancy Services rendered</div>
										<div class="amount-total">Total: <strong>Rs. <span data-field="net_amount">--</span></strong></div>
									</div>

									<div class="signature-section">
										<div class="signature-label">Signature&nbsp;&nbsp;<span class="sig-line">________________________</span></div>
									</div>

									<div class="receipt-section">
										<div class="receipt-title">RECEIPT</div>
										<p class="receipt-text">
										Received with thanks from M/S ArchitectsHive a sum of Rs. <span data-field="net_amount">--</span>/-
										( Rupees <span data-field="net_words">--</span> Only) through IMPS to A/C No. <span data-field="account">--</span>,
										<span data-field="bank">--</span> <span data-field="branch">--</span> towards monthly consultancy fee:
									</p>
										<table class="receipt-table">
											<tr>
												<td>FEES:</td>
											<td class="receipt-amount">Rs. <span data-field="fees_amount">--</span></td>
											</tr>
											<tr>
												<td>TDS:</td>
											<td class="receipt-amount">Rs. <span data-field="tds_amount">--</span></td>
											</tr>
											<tr class="receipt-note-row">
												<td colspan="2"><span class="receipt-note">(As / Section (b) 194)</span></td>
											</tr>
											<tr class="receipt-total">
												<td>NET PAYABLE:</td>
											<td class="receipt-amount">Rs. <span data-field="net_amount">--</span></td>
											</tr>
										</table>
										<div class="receipt-signature">Signature&nbsp;&nbsp;<span class="sig-line">________________________</span></div>
									</div>
								</div>

								<div class="slp-footer-block">
									<div class="footer-line"></div>
									<div class="footer-content">
										<div class="footer-left"></div>
										<div class="footer-right">
											F-52, First Floor, Near Gurudwara, Madhu Vihar,<br>
											I. P. Extension, Delhi-110092 Phone: 9958600397, 7503477154<br>
											E-Mail: <a href="mailto:contact@architectshive.com">contact@architectshive.com</a> Website: <a href="https://www.architectshive.com" target="_blank">www.architectshive.com</a>
										</div>
									</div>
								</div>
							</div>

						</section>
					</div>
				</div>
			</div>
		</main>
	</div>
</body>
</html>
