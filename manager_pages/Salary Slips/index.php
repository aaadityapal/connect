<?php
session_start();
if (!isset($_SESSION['user_id'])) {
	header("Location: ../../login.php");
	exit();
}

// Placeholder data until DB integration is wired in.
$companyLogo = "assets/company-logo.png";
$companyName = "ArchitectsHive";

$employee = [
	"name" => "Aditya Pal",
	"employee_id" => "EMP-1024",
	"designation" => "Project Manager",
	"department" => "Operations",
	"mobile" => "+91 98765 43210",
	"email" => "aditya.pal@architectshive.in",
	"pan" => "APYPR9870P",
	"bank" => "HDFC Bank",
	"account" => "XXXXXX3481",
	"ifsc" => "HDFC0001234",
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
						<button class="export-btn" id="exportPdfBtn" type="button">Export to PDF</button>
					</div>

					<div class="salary-grid">
						<section class="info-panel">
							<h3>Employee Details</h3>
							<div class="info-list">
								<div class="info-item">
									<span>Name</span>
									<strong><?php echo htmlspecialchars($employee["name"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Employee ID</span>
									<strong><?php echo htmlspecialchars($employee["employee_id"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Designation</span>
									<strong><?php echo htmlspecialchars($employee["designation"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Department</span>
									<strong><?php echo htmlspecialchars($employee["department"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Mobile</span>
									<strong><?php echo htmlspecialchars($employee["mobile"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Email</span>
									<strong><?php echo htmlspecialchars($employee["email"]); ?></strong>
								</div>
								<div class="info-item">
									<span>PAN</span>
									<strong><?php echo htmlspecialchars($employee["pan"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Bank</span>
									<strong><?php echo htmlspecialchars($employee["bank"]); ?></strong>
								</div>
								<div class="info-item">
									<span>Account</span>
									<strong><?php echo htmlspecialchars($employee["account"]); ?></strong>
								</div>
								<div class="info-item">
									<span>IFSC</span>
									<strong><?php echo htmlspecialchars($employee["ifsc"]); ?></strong>
								</div>
							</div>
						</section>

						<section class="slp-preview" id="pdfSlip">
							<img class="slp-logo" src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Company logo">

							<div class="slp-period-line">
								CONSULTANCY FEE FOR THE MONTH OF DECEMBER 2022
							</div>

							<div class="slp-block">
								<div class="slp-partitions">
									<div class="slp-left">
										<div class="field-row"><span>Name:</span><strong><?php echo htmlspecialchars($employee["name"]); ?></strong></div>
										<div class="field-row"><span>M. No.:</span><strong><?php echo htmlspecialchars($employee["mobile"]); ?></strong></div>
										<div class="field-row"><span>Email ID:</span><strong><?php echo htmlspecialchars($employee["email"]); ?></strong></div>
										<div class="field-row"><span>Employee ID:</span><strong><?php echo htmlspecialchars($employee["employee_id"]); ?></strong></div>
										<div class="field-row"><span>Joining Date:</span><strong>--</strong></div>
									</div>
									<div class="slp-right">
										<div class="field-row"><span>Designation:</span><strong><?php echo htmlspecialchars($employee["designation"]); ?></strong></div>
										<div class="field-row"><span>PAN:</span><strong><?php echo htmlspecialchars($employee["pan"]); ?></strong></div>
										<div class="field-row"><span>Bank A/c No:</span><strong><?php echo htmlspecialchars($employee["account"]); ?></strong></div>
										<div class="field-row"><span>IFSC:</span><strong><?php echo htmlspecialchars($employee["ifsc"]); ?></strong></div>
										<div class="field-row"><span>Bank Branch:</span><strong>--</strong></div>
									</div>
								</div>

								<div class="amount-section">
									<div class="amount-due">Amount due for&nbsp;&nbsp;General Consultancy Services rendered</div>
									<div class="amount-total">Total: <strong>Rs. <?php echo number_format($netPay, 0); ?></strong></div>
								</div>

								<div class="signature-section">
									<div class="signature-label">Signature&nbsp;&nbsp;<span class="sig-line">________________________</span></div>
								</div>

								<div class="receipt-section">
									<div class="receipt-title">RECEIPT</div>
									<p class="receipt-text">
										Received with thanks from C. P. Kukreja Architects a sum of Rs. __________/-
										( Rupees __________ Only) through RTGS to S/B A/C on 07/12/2022 in Canara Bank,
										Green Park, New Delhi-110016 towards monthly consultancy fee:
									</p>
									<table class="receipt-table">
										<tr>
											<td>FEES:</td>
											<td class="receipt-amount">Rs. <?php echo number_format($earningsTotal, 0); ?></td>
										</tr>
										<tr>
											<td>TDS:</td>
											<td class="receipt-amount">Rs. <?php echo number_format($deductionsTotal, 0); ?></td>
										</tr>
										<tr class="receipt-total">
											<td>NET PAYABLE:</td>
											<td class="receipt-amount">Rs. <?php echo number_format($netPay, 0); ?></td>
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

								<div class="slp-period-line">
									CONSULTANCY FEE FOR THE MONTH OF DECEMBER 2022
								</div>

								<div class="slp-block">
									<div class="slp-partitions">
										<div class="slp-left">
											<div class="field-row"><span>Name:</span><strong><?php echo htmlspecialchars($employee["name"]); ?></strong></div>
											<div class="field-row"><span>M. No.:</span><strong><?php echo htmlspecialchars($employee["mobile"]); ?></strong></div>
											<div class="field-row"><span>Email ID:</span><strong><?php echo htmlspecialchars($employee["email"]); ?></strong></div>
											<div class="field-row"><span>Employee ID:</span><strong><?php echo htmlspecialchars($employee["employee_id"]); ?></strong></div>
											<div class="field-row"><span>Joining Date:</span><strong>--</strong></div>
										</div>
										<div class="slp-right">
											<div class="field-row"><span>Designation:</span><strong><?php echo htmlspecialchars($employee["designation"]); ?></strong></div>
											<div class="field-row"><span>PAN:</span><strong><?php echo htmlspecialchars($employee["pan"]); ?></strong></div>
											<div class="field-row"><span>Bank A/c No:</span><strong><?php echo htmlspecialchars($employee["account"]); ?></strong></div>
											<div class="field-row"><span>IFSC:</span><strong><?php echo htmlspecialchars($employee["ifsc"]); ?></strong></div>
											<div class="field-row"><span>Bank Branch:</span><strong>--</strong></div>
										</div>
									</div>

									<div class="amount-section">
										<div class="amount-due">Amount due for&nbsp;&nbsp;General Consultancy Services rendered</div>
										<div class="amount-total">Total: <strong>Rs. <?php echo number_format($netPay, 0); ?></strong></div>
									</div>

									<div class="signature-section">
										<div class="signature-label">Signature&nbsp;&nbsp;<span class="sig-line">________________________</span></div>
									</div>

									<div class="receipt-section">
										<div class="receipt-title">RECEIPT</div>
										<p class="receipt-text">
											Received with thanks from C. P. Kukreja Architects a sum of Rs. __________/-
											( Rupees __________ Only) through RTGS to S/B A/C on 07/12/2022 in Canara Bank,
											Green Park, New Delhi-110016 towards monthly consultancy fee:
										</p>
										<table class="receipt-table">
											<tr>
												<td>FEES:</td>
												<td class="receipt-amount">Rs. <?php echo number_format($earningsTotal, 0); ?></td>
											</tr>
											<tr>
												<td>TDS:</td>
												<td class="receipt-amount">Rs. <?php echo number_format($deductionsTotal, 0); ?></td>
											</tr>
											<tr class="receipt-total">
												<td>NET PAYABLE:</td>
												<td class="receipt-amount">Rs. <?php echo number_format($netPay, 0); ?></td>
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
