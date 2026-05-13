"use strict";

const exportButton = document.getElementById("exportPdfBtn");

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
					width: 240px !important;
					max-width: 55% !important;
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
				}

				/* Period heading — larger & bold */
				.slp-period-line {
					font-size: 16px !important;
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
					font-size: 19px !important;
				}
				.amount-total {
					font-size: 20px !important;
					font-weight: 800 !important;
				}
				.amount-total strong {
					font-size: 20px !important;
					font-weight: 800 !important;
				}

				/* Receipt section */
				.receipt-title {
					font-size: 18px !important;
					font-weight: 800 !important;
				}
				.receipt-text {
					font-size: 17px !important;
				}
				.receipt-table td {
					font-size: 17px !important;
				}
				.receipt-amount {
					font-size: 17px !important;
				}

				/* Center the receipt content; center the table within the section */
				.receipt-section {
					text-align: center !important;
				}
				.receipt-table {
					margin: 0.4rem auto !important;
					text-align: left !important;
				}
				.receipt-text {
					text-align: left !important;
					font-size: 17px !important;
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

					/* Adjusted zoom to 0.70 to fit large top margin and shifted cut */
					html { zoom: 0.70; }
					body { background: #fff !important; }

					/* Full page height split 50/50 */
					.slp-preview {
						height: calc((297mm - 10mm) / 0.78) !important;
					}
					.slip-half {
						height: 56% !important;
						max-height: 56% !important;
					}
					.slip-duplicate-half {
						height: 44% !important;
						max-height: 44% !important;
					}
					.scissors-line {
						page-break-after: avoid !important;
						break-after: avoid !important;
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

if (exportButton) {
	exportButton.addEventListener("click", openPdfWindow);
}
