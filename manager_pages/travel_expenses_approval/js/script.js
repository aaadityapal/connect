/**
 * TRAVEL EXPENSES APPROVAL - Manager Interactivity
 * manager_pages/travel_expenses_approval/js/script.js
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('⚡ Travel Expense Approval initialized');

    // --- State ---
    const state = {
        expenses: [],
        filteredExpenses: [],
        currentUserRole: document.getElementById('currentUserRole')?.value || 'user'
    };

    let currentItem = null;
    let currentActionId = null;

    // --- Initialize Lucide Icons ---
    const initIcons = () => {
        if (window.lucide) {
            lucide.createIcons();
        }
    };
    initIcons();

    // --- DOM Elements ---
    const globalSearch = document.getElementById('globalSearchInput');
    const employeeFilter = document.getElementById('employeeFilter');
    const expenseStatusFilter = document.getElementById('expenseStatusFilter');
    const monthFilter = document.getElementById('monthFilter');
    const weekFilter = document.getElementById('weekFilter');
    const yearFilter = document.getElementById('yearFilter');
    const approvalLevelFilter = document.getElementById('approvalLevelFilter');
    
    const applyFiltersBtn = document.getElementById('applyFiltersBtn');
    const clearAllBtn = document.getElementById('clearAllFiltersBtn');
    const toggleFiltersBtn = document.getElementById('toggleFiltersBtn');
    const filterControlsInner = document.getElementById('filterControlsInner');

    const refreshBtn = document.getElementById('refreshBtn');
    const tableBody = document.getElementById('expenseTableBody');
    const paymentStatusFilter = document.getElementById('paymentStatusFilter');

    // Stats Elements


    // --- Initial Defaults ---
    const setFilterDefaults = () => {
        const now = new Date();
        const currentDay = now.getDate();
        const currentYear = String(now.getFullYear());
        const currentMonth = String(now.getMonth() + 1).padStart(2, '0');

        if (yearFilter) yearFilter.value = currentYear;
        if (monthFilter) monthFilter.value = currentMonth;
        
        updateWeekOptions(); // This will populate the weeks

        // Auto-select current week
        const weeks = calculateWeeks(parseInt(currentMonth), parseInt(currentYear));
        const currentWeek = weeks.find(w => currentDay >= w.start && currentDay <= w.end);
        if (currentWeek && weekFilter) {
            weekFilter.value = `${currentWeek.start}-${currentWeek.end}`;
        }
    };

    // --- Dynamic Week Population ---
    const updateWeekOptions = () => {
        if (!weekFilter || !monthFilter || !yearFilter) return;

        const month = parseInt(monthFilter.value);
        const year = parseInt(yearFilter.value);
        
        if (isNaN(month) || isNaN(year)) {
            weekFilter.innerHTML = '<option value="All">All Weeks</option>';
            return;
        }

        const weeks = calculateWeeks(month, year);
        let html = '<option value="All">All Weeks</option>';
        weeks.forEach((w, index) => {
            html += `<option value="${w.start}-${w.end}">Week ${index + 1} (${w.start}-${w.end})</option>`;
        });
        
        weekFilter.innerHTML = html;
        if (window.lucide) lucide.createIcons();
    };

    const calculateWeeks = (month, year) => {
        const weeks = [];
        const lastDay = new Date(year, month, 0).getDate();
        
        let start = 1;
        for (let day = 1; day <= lastDay; day++) {
            const date = new Date(year, month - 1, day);
            if (date.getDay() === 6 || day === lastDay) {
                weeks.push({ start, end: day });
                start = day + 1;
            }
        }
        return weeks;
    };

    // --- Filter logic ---
    const applyFilters = () => {
        const searchTerm = (globalSearch?.value || '').toLowerCase();
        const statusVal = expenseStatusFilter?.value || 'All';
        const empVal = employeeFilter?.value || 'All';
        const monthVal = monthFilter?.value || 'All';
        const weekVal = weekFilter?.value || 'All';
        const yearVal = yearFilter?.value || 'All';
        const approvalVal = approvalLevelFilter?.value || 'All';
        const paymentVal = paymentStatusFilter?.value || 'All';

        const filtered = state.expenses.filter(item => {
            const matchesSearch = item.employee_name.toLowerCase().includes(searchTerm) || 
                                 item.purpose.toLowerCase().includes(searchTerm) ||
                                 item.id.toString().includes(searchTerm);
            
            const matchesStatus = statusVal === 'All' || item.status.toLowerCase() === statusVal.toLowerCase();
            const matchesEmployee = empVal === 'All' || item.employee_name === empVal;

            const d = new Date(item.date);
            const matchesMonth = monthVal === 'All' || (String(d.getMonth() + 1).padStart(2, '0') === monthVal);
            const matchesYear = yearVal === 'All' || (String(d.getFullYear()) === yearVal);

            const day = d.getDate();
            let matchesWeek = true;
            if (weekVal !== 'All' && weekVal.includes('-')) {
                const [start, end] = weekVal.split('-').map(Number);
                matchesWeek = (day >= start && day <= end);
            }

            let matchesApproval = true;
            if (approvalVal !== 'All') {
                if (approvalVal === 'l1') matchesApproval = item.manager_status.toLowerCase() === 'pending';
                if (approvalVal === 'l2') matchesApproval = item.hr_status.toLowerCase() === 'pending';
                if (approvalVal === 'l3') matchesApproval = item.accountant_status.toLowerCase() === 'pending';
            }

            const matchesPayment = paymentVal === 'All' || item.payment_status.toLowerCase() === paymentVal.toLowerCase();

            return matchesSearch && matchesStatus && matchesEmployee && matchesMonth && matchesYear && matchesWeek && matchesApproval && matchesPayment;
        });

        state.filteredExpenses = filtered;
        renderTable(filtered);
        updateStats(filtered); // Connect analytics to filtered set
    };

    // --- PDF Export Logic ---
    const exportToPdf = () => {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4'); // Landscape for better table fit
        const data = state.filteredExpenses;
        
        // Settings/Colors
        const primaryColor = [79, 70, 229]; // #4f46e5 (Indigo/Blue)
        const secondaryColor = [30, 41, 59]; // #1e293b (Dark Gray)
        const mutedColor = [100, 116, 139]; // #64748b (Slate)

        // Header
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(22);
        doc.setTextColor(...primaryColor);
        doc.text('Travel Reimbursement Report', 14, 20);

        doc.setFontSize(10);
        doc.setTextColor(...mutedColor);
        doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, 28);
        
        // Active Filters Subheading
        const monthLabel = monthFilter?.options[monthFilter.selectedIndex]?.text || 'All Months';
        const weekLabel = weekFilter?.options[weekFilter.selectedIndex]?.text || 'All Weeks';
        const yearVal = yearFilter?.value || '2026';
        doc.setFontSize(9);
        doc.setTextColor(...secondaryColor);
        doc.text(`Timeline: ${monthLabel}, ${weekLabel} (${yearVal})`, 14, 35);

        // Stats Summary
        const totalAmt = data.reduce((sum, e) => sum + parseFloat(e.amount), 0);
        doc.setFontSize(11);
        doc.setTextColor(...secondaryColor);
        doc.text(`Records Found: ${data.length}`, 14, 45);
        doc.text(`Total Expenditure: INR ${totalAmt.toLocaleString('en-IN', {minimumFractionDigits: 2})}`, 283, 45, { align: 'right' });

        // Draw Line
        doc.setDrawColor(...primaryColor);
        doc.setLineWidth(0.5);
        doc.line(14, 48, 283, 48);

        // Table Data
        const rows = data.map(item => [
            item.employee_name,
            item.purpose,
            item.from,
            item.to,
            item.date,
            `INR ${parseFloat(item.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}`,
            item.status,
            item.payment_status
        ]);

        doc.autoTable({
            startY: 55,
            head: [['Employee', 'Purpose', 'From', 'To', 'Date', 'Amount', 'Status', 'Payment']],
            body: rows,
            foot: [['', '', '', '', 'TOTAL', `INR ${totalAmt.toLocaleString('en-IN', {minimumFractionDigits: 2})}`, '', '']],
            showFoot: 'lastPage',
            headStyles: {
                fillColor: primaryColor,
                textColor: [255, 255, 255],
                fontSize: 10,
                fontStyle: 'bold',
                halign: 'left'
            },
            footStyles: {
                fillColor: [241, 245, 249],
                textColor: primaryColor,
                fontSize: 10,
                fontStyle: 'bold',
                halign: 'right'
            },
            alternateRowStyles: {
                fillColor: [248, 250, 252]
            },
            margin: { top: 10, left: 14, right: 14 },
            styles: {
                fontSize: 9,
                cellPadding: 4,
                textColor: secondaryColor
            },
            columnStyles: {
                5: { fontStyle: 'bold', halign: 'right' }, // Amount column
                6: { halign: 'center' }, // Status
                7: { halign: 'center' }  // Payment
            },
            didParseCell: function(data) {
                if(data.section === 'head' && data.column.index === 5) {
                    data.cell.styles.halign = 'right';
                }
                // Special alignment for footer labels vs amount
                if(data.section === 'foot') {
                    if(data.column.index === 4) data.cell.styles.halign = 'right';
                    if(data.column.index === 5) data.cell.styles.halign = 'right';
                }
            }
        });

        // Add page numbers
        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(...mutedColor);
            doc.text(`Page ${i} of ${pageCount}`, doc.internal.pageSize.getWidth() / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });
        }

        doc.save(`Travel_Expenses_Report_${yearVal}_${monthLabel}.pdf`);
    };

    // --- Excel Export Logic ---
    const exportToExcel = async () => {
        const data = state.filteredExpenses;
        if (data.length === 0) {
            alert('No data found to export');
            return;
        }

        const workbook = new ExcelJS.Workbook();
        const sheet = workbook.addWorksheet('Travel Expenses');

        // Settings
        const BRAND_COLOR = 'FF4F46E5'; // Indigo/Blue hex without #
        const TEXT_COLOR = 'FF1E293B'; // Dark Slate hex
        const monthLabel = monthFilter?.options[monthFilter.selectedIndex]?.text || 'All Months';
        const weekLabel = weekFilter?.options[weekFilter.selectedIndex]?.text || 'All Weeks';
        const yearVal = yearFilter?.value || '2026';
        const totalAmt = data.reduce((sum, e) => sum + parseFloat(e.amount), 0);

        // 1. Report Title
        const titleRow = sheet.addRow(['TRAVEL REIMBURSEMENT REPORT']);
        titleRow.font = { name: 'Helvetica', size: 20, bold: true, color: { argb: BRAND_COLOR } };
        sheet.mergeCells('A1:H1'); // Adjusted for 8 columns (A-H)
        titleRow.height = 35;
        titleRow.alignment = { vertical: 'middle', horizontal: 'center' };

        // 2. Metadata Rows
        const meta1 = sheet.addRow([`Generated on: ${new Date().toLocaleString()}`]);
        const meta2 = sheet.addRow([`Timeline: ${monthLabel}, ${weekLabel} (${yearVal})`]);
        const meta3 = sheet.addRow([`Records Found: ${data.length}`, "", "", "", "", "", "Total Expenditure:", `INR ${totalAmt.toLocaleString('en-IN', {minimumFractionDigits: 2})}`]);
        sheet.mergeCells(`A4:G4`); // Merge to position Total at right

        [meta1, meta2, meta3].forEach(row => {
            row.font = { name: 'Helvetica', size: 10, color: { argb: 'FF64748B' } }; // Slate/Muted
        });
        meta3.font = { name: 'Helvetica', size: 11, bold: true, color: { argb: TEXT_COLOR } };

        sheet.addRow([]); // Blank spacer

        // 3. Table Header
        const headerRow = sheet.addRow(["Employee", "Purpose", "From", "To", "Date", "Amount (INR)", "Status", "Payment Status"]);
        headerRow.eachCell((cell) => {
            cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: BRAND_COLOR } };
            cell.alignment = { vertical: 'middle', horizontal: 'left' };
        });
        headerRow.getCell(6).alignment = { vertical: 'middle', horizontal: 'right' }; // Amount header right

        // 4. Data Rows
        data.forEach(item => {
            const row = sheet.addRow([
                item.employee_name,
                item.purpose,
                item.from,
                item.to,
                item.date,
                parseFloat(item.amount),
                item.status,
                item.payment_status
            ]);
            row.font = { name: 'Helvetica', size: 9 };
            row.getCell(6).numFmt = '#,##0.00'; // Format number
            row.getCell(6).alignment = { horizontal: 'right' };
        });

        sheet.addRow([]); // Spacer

        // 5. Grand Total Row (Bold & Large)
        const totalRow = sheet.addRow(["", "", "", "", "GRAND TOTAL", parseFloat(totalAmt), "", ""]);
        totalRow.font = { name: 'Helvetica', size: 14, bold: true, color: { argb: BRAND_COLOR } };
        totalRow.getCell(6).numFmt = '#,##0.00';
        totalRow.getCell(6).alignment = { horizontal: 'right' };
        totalRow.getCell(5).alignment = { horizontal: 'right' };

        // Column Widths
        sheet.columns = [
            { width: 25 }, { width: 25 }, { width: 20 }, { width: 20 },
            { width: 15 }, { width: 18 }, { width: 12 }, { width: 15 }
        ];

        // Export/Download
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `Travel_Expenses_${yearVal}_${monthLabel}.xlsx`;
        link.click();
    };

    const clearFilters = () => {
        if (globalSearch) globalSearch.value = '';
        if (employeeFilter) employeeFilter.value = 'All';
        if (expenseStatusFilter) expenseStatusFilter.value = 'All';
        setFilterDefaults();
        if (approvalLevelFilter) approvalLevelFilter.value = 'All';
        if (paymentStatusFilter) paymentStatusFilter.value = 'All';
        applyFilters();
    };

    const updateStats = (data) => {
        // Counts
        const pending = data.filter(e => e.needs_action); // Only items where THIS user needs to act
        const approved = data.filter(e => e.status.toLowerCase() === 'approved');
        const rejected = data.filter(e => e.status.toLowerCase() === 'rejected');
        
        // Financials
        const totalAmount = data.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
        const approvedAmount = approved.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
        const rejectedAmount = rejected.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
        const paidAmount = approved.filter(e => e.payment_status.toLowerCase() === 'paid')
                                   .reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
        const unpaidAmount = approvedAmount - paidAmount;

        // Populate DOM
        const setVal = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = val; };
        const formatCur = (num) => `₹ ${parseFloat(num).toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
        const calcPercent = (part, total) => total > 0 ? ((part / total) * 100).toFixed(1) + '%' : '0%';

        setVal('stat-pending-count', pending.length);
        setVal('stat-approved-count', approved.length);
        setVal('stat-rejected-count', rejected.length);
        setVal('stat-total-amount', formatCur(totalAmount));
        setVal('stat-total-year', yearFilter?.value || '2026');

        setVal('stat-approved-amount', formatCur(approvedAmount));
        setVal('stat-rejected-amount', formatCur(rejectedAmount));
        setVal('stat-paid-amount', formatCur(paidAmount));
        setVal('stat-unpaid-amount', formatCur(unpaidAmount));

        setVal('stat-approved-percent', `${calcPercent(approvedAmount, totalAmount)} of total`);
        setVal('stat-rejected-percent', `${calcPercent(rejectedAmount, totalAmount)} of total`);
        setVal('stat-paid-percent', `${calcPercent(paidAmount, approvedAmount)} of approved`);
        setVal('stat-unpaid-percent', `${calcPercent(unpaidAmount, approvedAmount)} of approved`);
    };

    // --- Listeners for dynamic updates ---
    if (monthFilter) monthFilter.addEventListener('change', () => {
        updateWeekOptions();
        applyFilters();
    });
    if (yearFilter) yearFilter.addEventListener('change', () => {
        updateWeekOptions();
        applyFilters();
    });
    if (weekFilter) weekFilter.addEventListener('change', applyFilters);
    if (expenseStatusFilter) expenseStatusFilter.addEventListener('change', applyFilters);
    if (employeeFilter) employeeFilter.addEventListener('change', applyFilters);
    if (approvalLevelFilter) approvalLevelFilter.addEventListener('change', applyFilters);
    if (paymentStatusFilter) paymentStatusFilter.addEventListener('change', applyFilters);

    const exportPdfBtn = document.getElementById('exportPdfBtn');
    if (exportPdfBtn) exportPdfBtn.addEventListener('click', exportToPdf);

    const exportExcelBtn = document.getElementById('exportExcelBtn');
    if (exportExcelBtn) exportExcelBtn.addEventListener('click', exportToExcel);

    if (toggleFiltersBtn) {
        toggleFiltersBtn.addEventListener('click', () => {
            filterControlsInner.classList.toggle('collapsed');
            const isCollapsed = filterControlsInner.classList.contains('collapsed');
            toggleFiltersBtn.innerHTML = isCollapsed ? 
                '<i data-lucide="settings-2" style="width:14px; height:14px;"></i> Show Filters' : 
                '<i data-lucide="settings-2" style="width:14px; height:14px;"></i> Hide Filters';
            initIcons();
        });
    }

    const populateEmployeeFilter = () => {
        if (!employeeFilter) return;
        const currentVal = employeeFilter.value;
        const employees = [...new Set(state.expenses.map(e => e.employee_name))].sort();
        employeeFilter.innerHTML = '<option value="All">All Employees</option>' + 
            employees.map(name => `<option value="${name}">${name}</option>`).join('');
        employeeFilter.value = employees.includes(currentVal) ? currentVal : 'All';
    };


    // --- Table Rendering ---
    const renderTable = (expenses) => {
        if (!tableBody) return;

        if (expenses.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted);"><div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;"><i data-lucide="layers" style="width: 48px; height: 48px; opacity: 0.2;"></i><p>No reimbursement requests found.</p></div></td></tr>`;
            initIcons();
            return;
        }

        tableBody.innerHTML = expenses.map(item => {
            const mStat = item.manager_status.toLowerCase();
            const hStat = item.hr_status.toLowerCase();
            const aStat = item.accountant_status.toLowerCase();
            const overallStatus = item.status.toLowerCase();

            return `
            <tr data-id="${item.id}">
                <td>
                    <div style="font-weight: 600;">${item.employee_name}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${item.employee_role}</div>
                </td>
                <td>
                    <div>${item.purpose}</div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);">${item.from} → ${item.to}</div>
                </td>
                <td><span class="status-tag ${mStat}">${item.manager_status}</span></td>
                <td><span class="status-tag ${hStat}">${item.hr_status}</span></td>
                <td><span class="status-tag ${aStat}">${item.accountant_status}</span></td>
                <td>${formatDate(item.date)}</td>
                <td class="amount">₹ ${parseFloat(item.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                <td>
                    <span class="status-tag ${overallStatus}">${item.status}</span>
                    ${(!item.can_act && item.needs_action) ? `
                        <div style="font-size: 0.65rem; color: #ea580c; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 3px;">
                            <i data-lucide="clock" style="width: 10px; height: 10px;"></i> 
                            ${item.window_message.replace('Approval window ', '')}
                        </div>
                    ` : ''}
                </td>
                <td><span class="status-tag ${item.payment_status.toLowerCase()}">${item.payment_status}</span></td>
                <td class="actions">
                    <div class="actions-group">
                        <button class="btn-icon view" title="View Details" onclick="openDetails(${item.id})"><i data-lucide="eye"></i></button>
                        ${item.needs_action ? (
                            item.can_act ? `
                                <button class="btn-icon approve" title="Approve" onclick="updateStatus(${item.id}, 'Approved')"><i data-lucide="check"></i></button>
                                <button class="btn-icon reject" title="Reject" onclick="updateStatus(${item.id}, 'Rejected')"><i data-lucide="x"></i></button>
                            ` : `
                                <button class="btn-icon locked" title="${item.window_message}" style="background: #fff7ed; color: #ea580c; border: 1px dashed #fed7aa; cursor: help;">
                                    <i data-lucide="lock" style="width: 14px; height: 14px;"></i>
                                </button>
                            `
                        ) : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');
        initIcons();
    };

    // --- Verify Distance Logic ---
    window.verifyDistance = async () => {
        const distanceInput = document.getElementById('verifyDistanceInput');
        const distanceValue = distanceInput?.value;
        const btn = document.getElementById('verifyDistanceBtn');
        const msgEl = document.getElementById('verificationMsg');

        const showError = (text) => {
            if (msgEl) {
                msgEl.innerHTML = `<i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i> ${text}`;
                msgEl.style.color = '#ef4444'; // Danger Red
                msgEl.className = 'verification-message error';
                initIcons();
            }
        };

        if (!distanceValue || isNaN(distanceValue) || parseFloat(distanceValue) <= 0) {
            showError('Please enter a valid distance calculated from the photos.');
            return;
        }

        if (!currentItem) {
            showError('Reference error: No expense selected.');
            return;
        }

        try {
            if (btn) btn.disabled = true;
            if (msgEl) {
                msgEl.innerHTML = `<i data-lucide="loader-2" class="spin" style="width: 14px; height: 14px;"></i> Verifying distance...`;
                msgEl.style.color = 'var(--primary)';
                initIcons();
            }

            const response = await fetch('api/verify_distance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: currentItem.id,
                    confirmed_distance: distanceValue,
                    acting_level: currentItem.acting_level 
                })
            });
            
            const result = await response.json();
            if (result.success) {
                 // On success, we can show a brief success then refresh
                if (msgEl) {
                    msgEl.innerHTML = `<i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> Verified Successfully!`;
                    msgEl.style.color = '#10b981';
                    initIcons();
                }

                setTimeout(async () => {
                    await fetchExpenses(); 
                    if (currentItem) {
                        openDetails(currentItem.id); 
                    }
                }, 800);
            } else {
                showError(result.message);
                if (btn) btn.disabled = false;
            }
        } catch (e) {
            console.error(e);
            showError('Failed to connect to the server.');
            if (btn) btn.disabled = false;
        }
    };

    // --- Data Fetching ---
    const fetchExpenses = async () => {
        try {
            if (refreshBtn) refreshBtn.classList.add('fa-spin');
            const response = await fetch('api/fetch_approvals.php');
            const result = await response.json();
            if (result.success) {
                state.expenses = result.data;
                populateEmployeeFilter();
                applyFilters();
            } else {
                showToast(result.message || 'Failed to fetch expenses', 'error');
            }
        } catch (error) {
            console.error('Fetch error:', error);
            showToast('Network error while fetching data', 'error');
        } finally {
            if (refreshBtn) refreshBtn.classList.remove('fa-spin');
        }
    };

    // --- Action Handlers (Open Modals) ---
    window.updateStatus = (id, status) => {
        if (status === 'Approved') {
            window.showApprovalConfirm(id);
        } else {
            window.showRejectionModal(id);
        }
    };

    window.showApprovalConfirm = (id) => {
        const modal = document.getElementById('approveModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
            currentActionId = id;
            
            const checks = modal.querySelectorAll('.approve-chk');
            const confirmBtn = document.getElementById('confirmApproveBtn');
            checks.forEach(c => c.checked = false);
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.style.opacity = '0.5';
                confirmBtn.style.cursor = 'not-allowed';
            }

            checks.forEach(check => {
                check.onclick = () => {
                    const allChecked = Array.from(checks).every(c => c.checked);
                    if (confirmBtn) {
                        confirmBtn.disabled = !allChecked;
                        confirmBtn.style.opacity = allChecked ? '1' : '0.5';
                        confirmBtn.style.cursor = allChecked ? 'pointer' : 'not-allowed';
                    }
                };
            });
        }
    };

    window.showRejectionModal = (id) => {
        const modal = document.getElementById('rejectModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
            currentActionId = id;
            
            const textarea = document.getElementById('rejectReason');
            const counter = document.getElementById('rejectWordCount');
            const confirmBtn = document.getElementById('confirmRejectBtn');

            if (textarea) {
                textarea.value = '';
                // Internal listener for word count
                textarea.oninput = () => {
                    const words = textarea.value.trim().split(/\s+/).filter(w => w.length > 0);
                    const count = words.length;
                    
                    if (counter) {
                        counter.textContent = `${count} / 10 words`;
                        counter.style.color = count >= 10 ? 'var(--success)' : 'var(--danger)';
                    }
                    
                    if (confirmBtn) {
                        confirmBtn.disabled = count < 10;
                        confirmBtn.style.opacity = count >= 10 ? '1' : '0.5';
                        confirmBtn.style.cursor = count >= 10 ? 'pointer' : 'not-allowed';
                    }
                };

                // Initial reset of UI
                if (counter) {
                    counter.textContent = '0 / 10 words';
                    counter.style.color = 'var(--danger)';
                }
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                    confirmBtn.style.opacity = '0.5';
                }
            }
        }
    };

    window.closeActionModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
    };

    window.submitApprove = async () => {
        const reason = document.getElementById('approveReason')?.value.trim() || '';
        await performStatusUpdate(currentActionId, 'Approved', reason);
        closeActionModal('approveModal');
    };

    window.submitReject = async () => {
        const reason = document.getElementById('rejectReason')?.value.trim() || '';
        if (!reason) { alert('Please provide a reason for rejection.'); return; }
        await performStatusUpdate(currentActionId, 'Rejected', reason);
        closeActionModal('rejectModal');
    };

    const performStatusUpdate = async (id, status, reason) => {
        try {
            const response = await fetch('api/update_approval_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, status, reason })
            });
            const result = await response.json();
            if (result.success) {
                showToast(result.message, 'success');
                if (currentItem && currentItem.id == id) closeModal();
                fetchExpenses(); // CRITICAL: Refresh the Main List
            } else {
                showToast(result.message || 'Update failed', 'error');
            }
        } catch (error) {
            showToast('Network error during update', 'error');
        }
    };

    // --- Modal Detail Handlers ---
    window.openDetails = (id) => {
        const item = state.expenses.find(e => e.id == id);
        if (!item) return;
        currentItem = item;
        const modal = document.getElementById('expenseModal');
        modal.classList.add('active');
        
        document.getElementById('modalDisplayId').textContent = item.display_id || `EXP-${item.id}`;
        document.getElementById('modalEmployeeName').textContent = item.employee_name;
        document.getElementById('modalPurpose').textContent = item.purpose;
        document.getElementById('modalFrom').textContent = item.from;
        document.getElementById('modalTo').textContent = item.to;
        document.getElementById('modalMode').textContent = item.mode;
        document.getElementById('modalKm').textContent = `${parseFloat(item.distance || 0).toFixed(2)} KM`;
        document.getElementById('modalDate').textContent = formatDate(item.date);
        document.getElementById('modalAmount').textContent = `₹ ${parseFloat(item.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}`;

        // Rejection Notice (General)
        const rejectNotice = document.getElementById('rejectionNotice');
        if (item.status.toLowerCase() === 'rejected') {
            rejectNotice.style.display = 'block';
            document.getElementById('rejectionReasonText').textContent = item.manager_reason || item.hr_reason || item.accountant_reason || 'No reason provided.';
        } else {
            rejectNotice.style.display = 'none';
        }

        // --- NEW: Sync Review History Timeline ---
        const setStatusColor = (id, status, reasonId, reason) => {
            const el = document.getElementById(id);
            const resEl = document.getElementById(reasonId);
            if (!el) return;
            
            // Apply color to the entire step (icon + text)
            const parent = el.closest('.history-step');
            if (parent) {
                parent.className = 'history-step ' + status.toLowerCase();
            }

            el.textContent = status.toUpperCase();
            
            if (resEl) {
                resEl.textContent = reason || 'No reason provided.';
            }
        };

        setStatusColor('managerStatus', item.manager_status, 'managerReason', item.manager_reason);
        setStatusColor('hrStatus', item.hr_status, 'hrReason', item.hr_reason);
        setStatusColor('seniorStatus', item.accountant_status, 'seniorReason', item.accountant_reason);


        // Meter Photos rendering
        const meterGrid = document.getElementById('modalMeterPhotos');
        meterGrid.innerHTML = '';
        if (item.require_meters || item.meter_mode === 1) {
            if (item.meter_mode === 1) {
                // Manual Meter Photos (Uploaded during claim)
                if (item.meter_start_photo_path) meterGrid.innerHTML += `<div class="attachment-card"><img src="../../${item.meter_start_photo_path}" alt="Manual Start" style="max-height:120px; object-fit:cover; width:100%; border-radius:8px; cursor:pointer;" onclick="window.open('../../${item.meter_start_photo_path}', '_blank')"><div class="label" style="text-align:center; font-size:0.8rem; margin-top:5px; color:#64748b; font-weight:600;">Original Meter Start</div></div>`;
                if (item.meter_end_photo_path) meterGrid.innerHTML += `<div class="attachment-card"><img src="../../${item.meter_end_photo_path}" alt="Manual End" style="max-height:120px; object-fit:cover; width:100%; border-radius:8px; cursor:pointer;" onclick="window.open('../../${item.meter_end_photo_path}', '_blank')"><div class="label" style="text-align:center; font-size:0.8rem; margin-top:5px; color:#64748b; font-weight:600;">Original Meter End</div></div>`;
                if (!item.meter_start_photo_path && !item.meter_end_photo_path) meterGrid.innerHTML = '<p class="text-muted" style="font-size:0.9rem;">No manual meter photos uploaded.</p>';
            } else {
                // Attendance Punches (Linked to day)
                if (item.punch_in_photo) meterGrid.innerHTML += `<div class="attachment-card"><img src="../../${item.punch_in_photo}" alt="Punch In" style="max-height:120px; object-fit:cover; width:100%; border-radius:8px; cursor:pointer;" onclick="window.open('../../${item.punch_in_photo}', '_blank')"><div class="label" style="text-align:center; font-size:0.8rem; margin-top:5px; color:#64748b; font-weight:600;">Opening Meter</div></div>`;
                if (item.punch_out_photo) meterGrid.innerHTML += `<div class="attachment-card"><img src="../../${item.punch_out_photo}" alt="Punch Out" style="max-height:120px; object-fit:cover; width:100%; border-radius:8px; cursor:pointer;" onclick="window.open('../../${item.punch_out_photo}', '_blank')"><div class="label" style="text-align:center; font-size:0.8rem; margin-top:5px; color:#64748b; font-weight:600;">Closing Meter</div></div>`;
                if (!item.punch_in_photo && !item.punch_out_photo) meterGrid.innerHTML = '<p class="text-muted" style="font-size:0.9rem;">No attendance photos uploaded.</p>';
            }
            document.querySelector('.meter-photos-section').style.display = 'block';
        } else {
            document.querySelector('.meter-photos-section').style.display = 'none';
        }

        // Attachments rendering
        const attachGrid = document.getElementById('modalAttachments');
        attachGrid.innerHTML = '';
        if (item.attachments && item.attachments.length > 0) {
            item.attachments.forEach(att => {
                const path = att.path.startsWith('http') ? att.path : `../../${att.path}`;
                attachGrid.innerHTML += `
                    <div class="attachment-card doc-attachment" onclick="window.open('${path}', '_blank')" style="background:#f8fafc; border:1px solid #e2e8f0; padding:15px; border-radius:8px; cursor:pointer; display:flex; flex-direction:column; align-items:center;">
                        <i data-lucide="file-text" style="width:32px; height:32px; margin-bottom:10px; color:var(--primary);"></i>
                        <div class="label" style="word-break:break-all; text-align:center; font-size:0.75rem;">${att.path.split('/').pop()}</div>
                    </div>
                `;
            });
            document.querySelector('.attachments-section').style.display = 'block';
        } else {
            attachGrid.innerHTML = '<p class="text-muted" style="font-size:0.9rem;">No extra proofs attached.</p>';
        }

        // Verification logic (Bluring)
        const blurTarget = document.getElementById('blurTarget');
        const vBar = document.getElementById('verificationBar');
        
        // --- BLIND VERIFICATION RESTORATION ---
        // A user only 'unlocks' the details for THEMSELVES after THEY enter the distance.
        let isVerified = false;
        if (item.acting_level === 'Manager (L1)') {
            isVerified = (item.confirmed_distance !== null && item.confirmed_distance !== "");
        } else if (item.acting_level === 'HR (L2)') {
            isVerified = (item.hr_confirmed_distance !== null && item.hr_confirmed_distance !== "");
        } else if (item.acting_level === 'Senior Manager (L3)') {
            isVerified = (item.senior_confirmed_distance !== null && item.senior_confirmed_distance !== "");
        } else {
            // Admin see details if it was their turn to act 
            isVerified = true; 
        }

        if (isVerified) {
            if (blurTarget) blurTarget.classList.remove('blur-content');
            if (vBar) vBar.style.display = 'none'; // Lock box disappears for this user
        } else {
            if (blurTarget) blurTarget.classList.add('blur-content');
            if (vBar) {
                vBar.style.display = 'block';
                vBar.classList.remove('verified-success');
            }
        }

        renderFooterActions(item, isVerified);
        initIcons();
    };

    window.closeModal = () => {
        document.getElementById('expenseModal').classList.remove('active');
    };

    const renderFooterActions = (item, isVerified) => {
        const footer = document.getElementById('modalFooterActions');
        footer.innerHTML = '<button class="btn-minimal" onclick="closeModal()">Dismiss</button>';
        if (item.needs_action) {
            if (item.can_act) {
                footer.innerHTML += `
                    <button class="btn-icon approve" ${isVerified ? '' : 'disabled'} onclick="showApprovalConfirm(${item.id})"><i data-lucide="check"></i> Approve</button>
                    <button class="btn-icon reject" onclick="updateStatus(${item.id}, 'Rejected')"><i data-lucide="x"></i> Reject</button>
                `;
            } else {
                footer.innerHTML += `
                    <div style="color:var(--danger); font-size:0.85rem; font-weight:600; display:flex; align-items:center; gap:6px; margin-left:auto;"><i data-lucide="lock" style="width:14px; height:14px;"></i> ${item.window_message}</div>
                `;
            }
        }
        initIcons();
    };

    // Removed redundant listeners already handled by global functions



    // --- Helper Functions ---
    function formatDate(dateStr) {
        return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.cssText = `position:fixed; bottom:20px; right:20px; padding:12px 24px; border-radius:8px; color:#fff; z-index:10000; background:${type==='success'?'#10b981':type==='error'?'#ef4444':'#3b82f6'};`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // --- Event Listeners ---
    if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', applyFilters);
    if (clearAllBtn) clearAllBtn.addEventListener('click', clearFilters);
    if (globalSearch) globalSearch.addEventListener('input', applyFilters);
    if (refreshBtn) refreshBtn.addEventListener('click', fetchExpenses);


    // Initial load
    setFilterDefaults();
    fetchExpenses();
});
