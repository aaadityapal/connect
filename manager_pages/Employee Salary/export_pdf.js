(function () {
    function notify(type, title, message) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(title, message, type);
            return;
        }
        alert(title + ': ' + message);
    }

    function getSelectedMonthYear() {
        const monthSelect = document.getElementById('month');
        const yearInput = document.getElementById('year');
        const month = monthSelect ? monthSelect.value : '';
        const year = yearInput ? yearInput.value : '';
        const monthName = monthSelect
            ? monthSelect.options[monthSelect.selectedIndex].text
            : '';
        return { month, year, monthName };
    }

    function buildFilename(monthName, year, ext) {
        const safeMonth = (monthName || 'Month').replace(/\s+/g, '_');
        const safeYear = year || 'Year';
        return `Employee_Salary_${safeMonth}_${safeYear}.${ext}`;
    }

    function saveHtmlToUploads(html, filename) {
        const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        const formData = new FormData();
        formData.append('html_file', blob, filename);
        formData.append('filename', filename);

        return fetch('save_export_html.php', {
            method: 'POST',
            body: formData
        }).then((response) => {
            if (!response.ok) {
                throw new Error('Upload failed');
            }
            return response.json();
        });
    }

    function getTable() {
        return document.querySelector('.analytics-table');
    }

    function getCleanText(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    function normalizeAmount(text) {
        const cleaned = String(text || '')
            .replace(/[₹\s]/g, '')
            .replace(/[^0-9,.-]/g, '');
        return cleaned.replace(/\s+/g, '');
    }

    function normalizeNumber(text) {
        const match = String(text || '').match(/-?\d+(?:\.\d+)?/);
        return match ? match[0] : '';
    }

    function isAmountHeader(header) {
        const h = header.toLowerCase();
        return h.includes('salary') || h.includes('deduction') || h.includes('amount') || h.includes('tds') || h.includes('payable');
    }

    function isCountHeader(header) {
        const h = header.toLowerCase();
        return h.includes('days') || h.includes('hours') || h.includes('late') || h.includes('leave taken');
    }

    function getCleanHeader(th) {
        // Clone the header cell and strip tooltip/icon spans so we only get the label text
        const clone = th.cloneNode(true);
        clone.querySelectorAll('.info-tooltip, .info-icon, small').forEach(el => el.remove());
        return getCleanText(clone.textContent);
    }

    function getTableData(table) {
        const headerCells = Array.from(table.querySelectorAll('thead th'));

        // Build header list, marking which column indices to SKIP (Action column)
        const allHeaders = headerCells.map((th) => getCleanHeader(th));
        const skipIndices = new Set(
            allHeaders
                .map((h, i) => (/^actions?$/i.test(h.trim()) ? i : -1))
                .filter(i => i >= 0)
        );

        const headers = allHeaders.filter((_, i) => !skipIndices.has(i));

        const allRows = Array.from(table.querySelectorAll('tbody tr'));
        const rows = [];
        let totalsRow = null;

        allRows.forEach((tr) => {
            const isTotal = tr.classList.contains('totals-row');
            const rowCells = Array.from(tr.cells);

            if (isTotal) {
                // Expand colspan cells so indices align 1-to-1 with allHeaders
                const expandedRow = [];
                rowCells.forEach((td) => {
                    const span = td.colSpan || 1;
                    const text = getCleanText(td.textContent);
                    for (let s = 0; s < span; s++) {
                        expandedRow.push(s === 0 ? text : '');
                    }
                });
                // Drop skipped (Action) column indices
                totalsRow = expandedRow.filter((_, i) => !skipIndices.has(i));
            } else {
                // Normalize data for data rows, skip Action column
                const normalizedRow = rowCells
                    .filter((_, idx) => !skipIndices.has(idx))
                    .map((td, idx) => {
                        const header = headers[idx] || '';
                        const text = getCleanText(td.textContent);
                        if (isAmountHeader(header) && !header.toLowerCase().includes('tds (%)')) {
                            return normalizeAmount(text);
                        }
                        if (isCountHeader(header)) {
                            return normalizeNumber(text);
                        }
                        return text;
                    });
                rows.push(normalizedRow);
            }
        });

        return { headers, rows, totalsRow };
    }

    function formatTotalsRow(headers, totalsRow) {
        if (!totalsRow || totalsRow.length === 0) return null;

        const salaryCalcIndex = headers.findIndex((h) => 
            h.toLowerCase().includes('salary calculated days')
        );

        // Create new totals row with empty cells before salary calculated days
        const formattedRow = new Array(headers.length).fill('');
        
        // Set "Total" label in the first column
        formattedRow[0] = 'Total';
        
        // Copy all totals data starting from salary calculated days index
        if (salaryCalcIndex >= 0) {
            for (let i = salaryCalcIndex; i < totalsRow.length; i++) {
                formattedRow[i] = totalsRow[i];
            }
        } else {
            // If salary calculated days not found, just place totals starting from index 1
            for (let i = 1; i < Math.min(totalsRow.length, headers.length); i++) {
                formattedRow[i] = totalsRow[i];
            }
        }

        return formattedRow;
    }

    window.exportToHtml = function exportToHtml() {
        const { month, year, monthName } = getSelectedMonthYear();
        if (!month || !year) {
            notify('warning', 'Warning', 'Please select month and year');
            return;
        }

        const table = getTable();
        if (!table) {
            notify('error', 'Error', 'Analytics table not found');
            return;
        }

        const bodyRows = table.querySelectorAll('tbody tr');
        if (!bodyRows.length || (bodyRows.length === 1 && /loading|no data/i.test(bodyRows[0].textContent))) {
            notify('warning', 'Warning', 'No data available to export');
            return;
        }

        const data = getTableData(table);
        const formattedTotalsRow = formatTotalsRow(data.headers, data.totalsRow);

        const title = `Employees Salary - ${monthName} ${year}`;
        const filename = buildFilename(monthName, year, 'html');

        const html = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .page { padding: 20px; }
        h1 { font-size: 18px; margin: 0 0 12px 0; }
        .scroll-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: auto; max-height: calc(100vh - 110px); }
        table { border-collapse: collapse; width: 100%; min-width: 1400px; }
        th, td { padding: 8px 10px; border: 1px solid #e2e8f0; font-size: 12px; white-space: nowrap; text-align: center; }
        th { background: #2d3748; color: #fff; position: sticky; top: 0; z-index: 1; font-weight: bold; }
        tr:nth-child(even) td { background: #f8fafc; }
        tr.totals td { background: #f1f5f9; font-weight: bold; border-top: 2px solid #2d3748; }
    </style>
</head>
<body>
    <div class="page">
        <h1>${title}</h1>
        <div class="scroll-wrap">
            <table>
                <thead>
                    <tr>
                        ${data.headers.map((h) => `<th>${h}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
                    ${data.rows.map((row) => `
                        <tr>
                            ${row.map((cell) => `<td>${cell}</td>`).join('')}
                        </tr>
                    `).join('')}
                    ${formattedTotalsRow ? `
                        <tr class="totals">
                            ${formattedTotalsRow.map((cell) => `<td>${cell}</td>`).join('')}
                        </tr>
                    ` : ''}
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
        `.trim();

        const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        const downloadUrl = URL.createObjectURL(blob);
        const downloadLink = document.createElement('a');
        downloadLink.href = downloadUrl;
        downloadLink.download = filename;
        document.body.appendChild(downloadLink);
        downloadLink.click();
        downloadLink.remove();
        URL.revokeObjectURL(downloadUrl);

        const win = window.open('', '_blank');
        if (!win) {
            notify('error', 'Error', 'Popup blocked. Please allow popups for this site.');
            return;
        }

        win.document.open();
        win.document.write(html);
        win.document.close();
        win.document.title = filename;

        saveHtmlToUploads(html, filename).catch(() => {
            notify('warning', 'Warning', 'Exported file saved locally, but failed to save to uploads');
        });

        notify('success', 'Success', 'PDF exported and downloaded');
    };
})();
