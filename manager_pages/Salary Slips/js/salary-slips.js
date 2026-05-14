"use strict";

const exportButton = document.getElementById("exportPdfBtn");
const employeeSelect = document.getElementById("employeeSelect");
const monthSelect = document.getElementById("salaryMonth");
const yearSelect = document.getElementById("salaryYear");

const textFallback = "--";

const setFieldText = (field, value) => {
	const nodes = document.querySelectorAll(`[data-field="${field}"]`);
	if (!nodes.length) return;
	const nextValue = value && String(value).trim().length ? value : textFallback;
	nodes.forEach((node) => {
		node.textContent = nextValue;
	});
};

const applyEmployeeDetails = (data) => {
	setFieldText("name", data.name);
	setFieldText("employee_id", data.employee_id);
	setFieldText("designation", data.designation);
	setFieldText("department", data.department);
	setFieldText("mobile", data.mobile);
	setFieldText("email", data.email);
	setFieldText("pan", data.pan);
	setFieldText("bank", data.bank);
	setFieldText("account", data.account);
	setFieldText("ifsc", data.ifsc);
	setFieldText("joining_date", data.joining_date);
	setFieldText("branch", data.branch);
};

const applySalarySnapshot = (data) => {
	setFieldText("fees_amount", data.fees_amount);
	setFieldText("tds_amount", data.tds_amount);
	setFieldText("net_amount", data.net_amount);
	setFieldText("net_words", data.net_words);
};

const updatePeriodLine = () => {
	if (!monthSelect || !yearSelect) return;
	const monthIndex = parseInt(monthSelect.value, 10);
	const yearValue = yearSelect.value;
	const monthNames = [
		"January",
		"February",
		"March",
		"April",
		"May",
		"June",
		"July",
		"August",
		"September",
		"October",
		"November",
		"December"
	];
	const monthLabel = monthNames[monthIndex - 1] || "";
	const periodText = monthLabel ? `CONSULTANCY FEE FOR THE MONTH OF ${monthLabel} ${yearValue}` : "CONSULTANCY FEE";
	setFieldText("period_line", periodText.toUpperCase());
};

const fetchEmployeeDetails = async (userId) => {
	if (!userId) {
		applyEmployeeDetails({});
		applySalarySnapshot({});
		return;
	}

	try {
		const response = await fetch(`api/get_user_details.php?user_id=${encodeURIComponent(userId)}`);
		const payload = await response.json();
		if (!response.ok || !payload.success) {
			throw new Error(payload.message || "Unable to load employee details");
		}
		applyEmployeeDetails(payload.data || {});
	} catch (error) {
		console.error("Salary slips: employee fetch failed", error);
		alert("Unable to load employee details. Please try again.");
	}
};

const fetchSalarySnapshot = async (userId) => {
	if (!userId) {
		applySalarySnapshot({});
		return;
	}

	const month = monthSelect ? monthSelect.value : "";
	const year = yearSelect ? yearSelect.value : "";
	const query = new URLSearchParams({
		user_id: userId,
		month,
		year
	});

	try {
		const response = await fetch(`api/get_salary_snapshot.php?${query.toString()}`);
		const payload = await response.json();
		if (!response.ok || !payload.success) {
			throw new Error(payload.message || "Unable to load salary snapshot");
		}
		applySalarySnapshot(payload.data || {});
	} catch (error) {
		console.error("Salary slips: snapshot fetch failed", error);
		applySalarySnapshot({});
	}
};

const openPdfWindow = async () => {
	const slip = document.getElementById("pdfSlip");
	if (!slip) return;

	const clone = slip.cloneNode(true);

	// Absolutize image paths so logo works in the blank popup
	clone.querySelectorAll("img[src]").forEach((img) => {
		img.src = new URL(img.getAttribute("src"), window.location.href).href;
	});

	// ── Restructure for a clean 50/50 split ──────────────────────────────────
	// Wrap all "original slip" nodes (everything before .scissors-line) in a
	// single div so CSS flex can give each half exactly 50% of the page height.
	const scissors = clone.querySelector(".scissors-line");
	const duplicate = clone.querySelector(".slp-duplicate");

	if (scissors && duplicate) {
		const originalWrap = document.createElement("div");
		originalWrap.className = "slip-half slip-original-half";

		// Move nodes that come before the scissors line
		while (clone.firstChild && clone.firstChild !== scissors) {
			originalWrap.appendChild(clone.firstChild);
		}
		clone.insertBefore(originalWrap, scissors);
		duplicate.classList.add("slip-half", "slip-duplicate-half");
	}

	// ── Inline CSS so styles are present before print() fires ────────────────
	const baseUrl = new URL("./", window.location.href);
	const cssUrls = [
		new URL("../employees_profile/style.css", baseUrl).href,
		new URL("css/salary-slips.css", baseUrl).href,
	];

	let combinedCss = "";
	try {
		const sheets = await Promise.all(
			cssUrls.map((u) => fetch(u).then((r) => (r.ok ? r.text() : "")).catch(() => ""))
		);
		combinedCss = sheets.join("\n");
	} catch (e) {
		console.warn("PDF export: CSS fetch failed", e);
	}

	const printWindow = window.open("", "_blank");
	if (!printWindow) {
		alert("Popup blocked — please allow popups for this site.");
		return;
	}

	printWindow.document.open();
	printWindow.document.write(`
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<title>Salary Slip</title>
			<style>
				${combinedCss}

				/* ── Base export resets ── */
				* { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
				html, body { background: #fff !important; margin: 0; padding: 0; }

				/* Always two-column field layout */
				.slp-partitions {
					display: flex !important;
					flex-wrap: nowrap !important;
					grid-template-columns: unset !important;
				}
				.slp-left {
					flex: 1 !important;
					border-right: 2px solid #94a3b8 !important;
					padding-right: 10px !important;
					overflow: visible !important;
				}
				.slp-right { 
					flex: 1 !important;
					padding-left: 10px !important;
					overflow: visible !important;
				}

				.slp-preview {
					border: none !important;
					box-shadow: none !important;
					border-radius: 0 !important;
					width: 100% !important;
					padding: 0 !important;
					box-sizing: border-box !important;
				}
				.pdf-export-page {
					padding-top: 85px !important;
				}
				.slp-block { border-radius: 0 !important; }

				/* Logo: pull out of absolute so it sits ABOVE the period line in normal flow */
				.slip-half .slp-logo {
					position: relative !important;
					display: block !important;
					top: auto !important;
					right: auto !important;
					width: 350px !important;
					max-width: 70% !important;
					margin: 1rem 0.75rem 0.1rem auto !important;
					/* Sharpen raster image during print scaling */
					image-rendering: -webkit-optimize-contrast !important;
					image-rendering: crisp-edges !important;
				}

				/* Footer — full-width left-aligned address in 3 lines (Image 2 style) */
				.footer-content {
					display: block !important;
				}
				.footer-left {
					display: none !important;
				}
				.footer-right {
					text-align: right !important;
					width: auto !important;
					margin-left: auto !important;
					line-height: 1.5 !important;
					font-size: 16px !important;
				}

				/* Period heading — larger & bold */
				.slp-period-line {
					font-size: 26px !important;
					font-weight: 800 !important;
					letter-spacing: 0.04em !important;
				}

				/* Employee detail rows — increase by 3px for readability */
				.field-row {
					font-size: 19px !important;
					display: flex !important;
					white-space: nowrap !important;
					align-items: baseline !important;
					margin-bottom: 2px !important;
				}
				.field-row span {
					width: 130px !important;
					flex-shrink: 0 !important;
					margin-right: 8px !important;
					font-size: 19px !important;
				}
				.field-row strong {
					font-size: 19px !important;
				}

				/* Amount section — match field size; make total bold & larger */
				.amount-due {
					font-size: 23px !important;
				}
				.amount-total {
					font-size: 25px !important;
					font-weight: 800 !important;
				}
				.amount-total strong {
					font-size: 25px !important;
					font-weight: 800 !important;
				}

				/* Receipt section */
				.receipt-title {
					font-size: 22px !important;
					font-weight: 800 !important;
				}
				.receipt-text {
					font-size: 20px !important;
				}
				.receipt-table td {
					font-size: 19px !important;
					white-space: nowrap !important;
					padding: 0.25rem 0.75rem !important;
				}
				.receipt-amount {
					font-size: 19px !important;
				}

				/* Center the receipt content; center the table within the section */
				.receipt-section {
					text-align: center !important;
				}
				.receipt-table {
					width: auto !important;
					min-width: 380px !important;
					margin: 0.4rem auto !important;
					text-align: left !important;
				}
				.receipt-text {
					text-align: left !important;
					font-size: 20px !important;
				}

				/* Border line above NET PAYABLE — like a subtotal separator */
				.receipt-total td {
					border-top: 1.5px solid #333 !important;
					padding-top: 4px !important;
				}

				/* ── 50 / 50 split layout ── */
				.slp-preview {
					display: flex !important;
					flex-direction: column !important;
				}
				.slip-half {
					flex: 1 !important;
					min-height: 0 !important;
					position: relative !important;
				}
				.slip-duplicate-half {
					margin-top: 0 !important;
					padding-top: 0 !important;
				}
				.scissors-line {
					flex: 0 0 auto !important;
					margin: 15px 0 !important;
				}

				/* Reduce excess whitespace inside each half */
				.amount-section   { min-height: 35px !important; margin-top: 2px !important; }
				.signature-section{ min-height: 25px !important; margin-top: 2px !important; }
				.signature-label, .receipt-signature {
					font-size: 18px !important;
					font-weight: 800 !important;
				}
				.receipt-section  { margin-top: 2px !important; }
				.slp-footer-block { margin-top: 2px !important; }

				@media print {
					@page { size: A4 portrait; margin: 5mm; }

					/* Adjusted zoom to comfortably fit both enhanced slips on a single page */
					html { zoom: 0.58; }
					body { background: #fff !important; }

					/* Allow content to determine height naturally without clipping or page overflowing */
					.slp-preview {
						height: auto !important;
						max-height: none !important;
					}
					.slip-half {
						height: auto !important;
						max-height: none !important;
					}
					.slip-duplicate-half {
						height: auto !important;
						max-height: none !important;
					}
					.scissors-line {
						page-break-after: avoid !important;
						break-after: avoid !important;
						margin: 30px 0 !important;
						border-top: 3px dashed #334155 !important;
					}
				}
			</style>
		</head>
		<body>
			<div class="pdf-export-page">${clone.outerHTML}</div>
		</body>
		</html>
	`);

	// Attach BEFORE document.close() — close() triggers the load event
	printWindow.onload = () => {
		setTimeout(() => {
			printWindow.focus();
			printWindow.print();
		}, 250);
	};

	printWindow.document.close();
};

if (employeeSelect) {
	employeeSelect.addEventListener("change", (event) => {
		const userId = event.target.value;
		fetchEmployeeDetails(userId);
		fetchSalarySnapshot(userId);
	});
}

if (monthSelect && yearSelect) {
	const refreshSnapshot = () => {
		updatePeriodLine();
		if (employeeSelect && employeeSelect.value) {
			fetchSalarySnapshot(employeeSelect.value);
		}
	};

	monthSelect.addEventListener("change", refreshSnapshot);
	yearSelect.addEventListener("change", refreshSnapshot);
	updatePeriodLine();
}

if (exportButton) {
	exportButton.addEventListener("click", openPdfWindow);
}
