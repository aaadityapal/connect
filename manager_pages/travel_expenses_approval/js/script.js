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
        renderedStacks: [],
        currentUserRole: document.getElementById('currentUserRole')?.value || 'user',
        stackUi: {
            activeStackIndex: null,
            reopenAfterDetailsClose: false
        }
    };

    let currentItem = null;
    let currentActionId = null;
    const transportRates = {};

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

    const groupExpensesByUserDate = (expenses) => {
        const map = new Map();

        expenses.forEach((item) => {
            const key = `${item.employee_name}__${item.date}`;
            if (!map.has(key)) {
                map.set(key, {
                    key,
                    employee_name: item.employee_name,
                    employee_role: item.employee_role,
                    date: item.date,
                    items: []
                });
            }
            map.get(key).items.push(item);
        });

        const groups = Array.from(map.values()).map((group) => {
            group.items.sort((a, b) => {
                const aTs = parseBackendDateTime(a.created_at) || parseBackendDateTime(a.updated_at) || parseBackendDateTime(a.date) || Number(a.id);
                const bTs = parseBackendDateTime(b.created_at) || parseBackendDateTime(b.updated_at) || parseBackendDateTime(b.date) || Number(b.id);
                return aTs - bTs;
            });
            return group;
        });

        groups.sort((a, b) => {
            const dateDiff = new Date(b.date) - new Date(a.date);
            if (dateDiff !== 0) return dateDiff;
            return a.employee_name.localeCompare(b.employee_name);
        });

        return groups;
    };

    const resolveStackStatus = (items, field) => {
        const values = [...new Set(items.map((it) => String(it[field] || '').toLowerCase()))];
        if (values.length === 1) return values[0] || 'pending';
        return 'mixed';
    };

    const resolveStackOverallStatus = (items) => {
        if (items.some((it) => String(it.status).toLowerCase() === 'rejected')) return 'rejected';
        if (items.every((it) => String(it.status).toLowerCase() === 'approved')) return 'approved';
        return 'pending';
    };

    const resolveStackPaymentStatus = (items) => {
        const statuses = items.map((it) => String(it.payment_status || '').toLowerCase());
        if (statuses.every((s) => s === 'paid')) return 'paid';
        if (statuses.every((s) => s === 'pending')) return 'pending';
        return 'mixed';
    };

    const hasDistanceVerification = (item) => {
        if (!item) return false;

        // L3 and admin are exempt from distance lock in current workflow.
        if (item.acting_level === 'Senior Manager (L3)') return true;
        if (item.acting_level === 'Administrator (Oversight)') return true;

        const normalizeDayKey = (value) => {
            if (!value) return '';
            const raw = String(value).trim();
            if (!raw) return '';
            // Handles both YYYY-MM-DD and YYYY-MM-DD HH:mm:ss like values.
            return raw.slice(0, 10);
        };

        // Reuse verification only for the same TRAVEL DATE for the same user.
        // This prevents verification from leaking across different travel dates.
        const getTravelDayKey = (row) => (
            normalizeDayKey(row.date) ||
            normalizeDayKey(row.travel_date) ||
            ''
        );

        const dayKey = getTravelDayKey(item);
        const sameDayExpenses = state.expenses.filter((exp) => {
            const userMatchById = String(exp.user_id ?? '') === String(item.user_id ?? '');
            const userMatchByName = String(exp.employee_name ?? '') === String(item.employee_name ?? '');
            const sameUser = userMatchById || userMatchByName;
            const sameDate = getTravelDayKey(exp) === dayKey;
            return sameUser && sameDate;
        });

        // Fallback if user_id is missing for any reason.
        const pool = sameDayExpenses.length > 0 ? sameDayExpenses : [item];

        const hasValue = (v) => v !== null && v !== undefined && String(v).trim() !== '';

        if (item.acting_level === 'Manager (L1)') {
            return pool.some((exp) => hasValue(exp.confirmed_distance));
        }

        if (item.acting_level === 'HR (L2)') {
            return pool.some((exp) => hasValue(exp.hr_confirmed_distance));
        }

        return false;
    };

    const normalizeMode = (mode) => (mode || '').toString().trim().toLowerCase();

    const isRateLockedMode = (mode) => {
        const normalized = normalizeMode(mode);
        return normalized === 'car' || normalized === 'bike';
    };

    const loadTransportRates = async () => {
        if (Object.keys(transportRates).length > 0) return;

        try {
            const response = await fetch('api/fetch_transport_rates.php');
            const result = await response.json();
            if (result.success && Array.isArray(result.rates)) {
                result.rates.forEach((r) => {
                    const key = normalizeMode(r.transport_mode);
                    const rate = parseFloat(r.rate_per_km);
                    if (key && Number.isFinite(rate)) {
                        transportRates[key] = rate;
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load transport rates:', error);
        }
    };

    const applyEditAmountRules = () => {
        const modeInput = document.getElementById('editMode');
        const distanceInput = document.getElementById('editDistance');
        const amountInput = document.getElementById('editAmount');
        const hintEl = document.getElementById('editAmountHint');

        if (!modeInput || !distanceInput || !amountInput) return;

        const mode = normalizeMode(modeInput.value);
        const isLockedMode = isRateLockedMode(mode);

        if (isLockedMode) {
            const distance = parseFloat(distanceInput.value || '0');
            const rate = parseFloat(transportRates[mode] ?? '0');
            const autoAmount = Number.isFinite(distance) && Number.isFinite(rate)
                ? Math.max(0, distance * rate)
                : 0;

            amountInput.value = autoAmount.toFixed(2);
            amountInput.readOnly = true;
            amountInput.style.background = '#f8fafc';
            amountInput.style.cursor = 'not-allowed';

            if (hintEl) {
                hintEl.textContent = `Auto-calculated: ${mode.toUpperCase()} rate ${rate.toFixed(2)} per KM × distance.`;
                hintEl.style.color = '#0f766e';
            }
        } else {
            amountInput.readOnly = false;
            amountInput.style.background = '#ffffff';
            amountInput.style.cursor = 'text';

            if (hintEl) {
                hintEl.textContent = 'You can edit amount for this mode.';
                hintEl.style.color = '#64748b';
            }
        }
    };


    // --- Table Rendering ---
    const renderTable = (expenses) => {
        if (!tableBody) return;

        if (expenses.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted);"><div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;"><i data-lucide="layers" style="width: 48px; height: 48px; opacity: 0.2;"></i><p>No reimbursement requests found.</p></div></td></tr>`;
            initIcons();
            return;
        }

        const stacks = groupExpensesByUserDate(expenses);
        state.renderedStacks = stacks;

        tableBody.innerHTML = stacks.map((stack, idx) => {
            const lead = stack.items[0];
            const totalAmount = stack.items.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
            const managerStatus = resolveStackStatus(stack.items, 'manager_status');
            const hrStatus = resolveStackStatus(stack.items, 'hr_status');
            const seniorStatus = resolveStackStatus(stack.items, 'accountant_status');
            const overallStatus = resolveStackOverallStatus(stack.items);
            const paymentStatus = resolveStackPaymentStatus(stack.items);
            const hasActionBlocked = stack.items.some((it) => it.needs_action && !it.can_act);
            const blockedMessageItem = stack.items.find((it) => it.needs_action && !it.can_act);

            return `
            <tr data-stack-key="${stack.key}" class="stack-row-clickable" onclick="openStackDetails(${idx})" title="Open grouped expenses">
                <td>
                    <div style="font-weight: 600;">${stack.employee_name}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${stack.employee_role || ''}</div>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span>${lead.purpose}</span>
                        <span class="stack-count-badge">${stack.items.length} ${stack.items.length > 1 ? 'Entries' : 'Entry'}</span>
                    </div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);">${formatDate(stack.date)} • ${stack.items.map(i => i.from + ' → ' + i.to).slice(0, 2).join(' | ')}${stack.items.length > 2 ? ' ...' : ''}</div>
                </td>
                <td><span class="status-tag ${managerStatus}">${managerStatus}</span></td>
                <td><span class="status-tag ${hrStatus}">${hrStatus}</span></td>
                <td><span class="status-tag ${seniorStatus}">${seniorStatus}</span></td>
                <td>${formatDate(stack.date)}</td>
                <td class="amount">₹ ${totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                <td>
                    <span class="status-tag ${overallStatus}">${overallStatus}</span>
                    ${hasActionBlocked && blockedMessageItem ? `
                        <div style="font-size: 0.65rem; color: #ea580c; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 3px;">
                            <i data-lucide="clock" style="width: 10px; height: 10px;"></i> 
                            ${blockedMessageItem.window_message.replace('Approval window ', '')}
                        </div>
                    ` : ''}
                </td>
                <td><span class="status-tag ${paymentStatus}">${paymentStatus}</span></td>
                <td class="actions">
                    <div class="actions-group">
                        <button class="btn-icon view" title="Open Stack" onclick="event.stopPropagation(); openStackDetails(${idx})"><i data-lucide="layers"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');
        initIcons();
    };

    window.openStackDetails = (stackIndex) => {
        const stack = state.renderedStacks[stackIndex];
        if (!stack) return;

        state.stackUi.activeStackIndex = stackIndex;

        const modal = document.getElementById('stackModal');
        const title = document.getElementById('stackModalTitle');
        const meta = document.getElementById('stackModalMeta');
        const summary = document.getElementById('stackModalSummary');
        const bulkSection = document.getElementById('stackBulkApproveSection');
        const list = document.getElementById('stackModalList');

        const totalAmount = stack.items.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);

        title.textContent = `${stack.employee_name} • ${formatDate(stack.date)}`;
        meta.textContent = `${stack.items.length} ${stack.items.length > 1 ? 'Expenses' : 'Expense'} in this stack`;
        summary.innerHTML = `
            <div class="stack-summary">
                <div class="stack-summary-item">
                    <div class="summary-label-wrap"><i data-lucide="layers" style="width:14px; height:14px;"></i><span class="label">Total Entries</span></div>
                    <span class="value">${stack.items.length}</span>
                </div>
                <div class="stack-summary-item">
                    <div class="summary-label-wrap"><i data-lucide="banknote" style="width:14px; height:14px;"></i><span class="label">Total Amount</span></div>
                    <span class="value">₹ ${totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
            </div>
        `;

        const bulkEligibleItems = stack.items.filter((item) => {
            const pendingOverall = String(item.status || '').toLowerCase() === 'pending';
            return item.needs_action && item.can_act && pendingOverall && hasDistanceVerification(item);
        });

        if (bulkEligibleItems.length > 0) {
            bulkSection.innerHTML = `
                <div class="bulk-approve-panel">
                    <div class="bulk-approve-header">
                        <div class="bulk-approve-title" id="bulkPanelTitle"><i data-lucide="list-checks" style="width:16px; height:16px;"></i> Bulk Actions (Verified Only)</div>
                        <div class="bulk-header-right">
                            <span class="bulk-selected-meta" id="bulkSelectedCount">Selected ${bulkEligibleItems.length} of ${bulkEligibleItems.length}</span>
                            <label class="bulk-select-all">
                                <input type="checkbox" id="bulkSelectAllExpenses" checked>
                                <span>Select all verified (${bulkEligibleItems.length})</span>
                            </label>
                        </div>
                    </div>

                    <div class="bulk-approve-list" id="bulkApproveList">
                        ${bulkEligibleItems.map((item) => `
                            <label class="bulk-expense-item">
                                <input type="checkbox" class="bulk-expense-check" value="${item.id}" checked>
                                <span class="id">${item.display_id || `EXP-${item.id}`}</span>
                                <span class="text">${item.purpose}</span>
                                <span class="amount">₹ ${parseFloat(item.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
                            </label>
                        `).join('')}
                    </div>

                    <div class="bulk-checkpoints">
                        <label><input type="checkbox" class="bulk-checkpoint"> I verified meter-based distance for selected entries.</label>
                        <label><input type="checkbox" class="bulk-checkpoint"> I confirm purpose and route are valid for selected entries.</label>
                        <label><input type="checkbox" class="bulk-checkpoint"> I reviewed selected entries and confirm policy compliance.</label>
                    </div>

                    <div class="bulk-actions-row">
                        <textarea id="bulkApproveReason" rows="2" placeholder="Optional common note (required for reject, min 10 words)..."></textarea>
                        <div class="bulk-action-buttons">
                            <button class="btn-bulk-action approve" id="bulkApproveBtn" disabled onclick="submitBulkApprove('${stack.key}')"><i data-lucide="check-check"></i><span>Approve Selected</span></button>
                            <button class="btn-bulk-action reject" id="bulkRejectBtn" disabled onclick="submitBulkReject('${stack.key}')"><i data-lucide="x-circle"></i><span>Reject Selected</span></button>
                        </div>
                    </div>
                </div>
            `;
        } else {
            bulkSection.innerHTML = `
                <div class="bulk-approve-panel muted">
                    <div class="bulk-approve-title"><i data-lucide="info" style="width:16px; height:16px;"></i> Bulk Approve</div>
                    <div class="bulk-empty-text">No entries are currently eligible for bulk approve. Only distance-verified, pending, actionable entries are shown here.</div>
                </div>
            `;
        }

        list.innerHTML = stack.items.map((item) => {
            const canQuickApprove = item.needs_action && item.can_act && hasDistanceVerification(item);
            const canQuickReject = item.needs_action && item.can_act && hasDistanceVerification(item);
            const canMarkPaid = Boolean(item.can_pay) && String(item.status || '').toLowerCase() === 'approved' && String(item.payment_status || '').toLowerCase() !== 'paid';

            return `
                <div class="stack-expense-card">
                    <div class="stack-expense-head">
                        <div>
                            <div class="stack-expense-id"><i data-lucide="file-badge-2" style="width:12px; height:12px;"></i>${item.display_id || `EXP-${item.id}`}</div>
                            <div class="stack-expense-purpose">${item.purpose}</div>
                        </div>
                        <div class="stack-expense-amount"><i data-lucide="indian-rupee" style="width:14px; height:14px;"></i>₹ ${parseFloat(item.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                    </div>
                    <div class="stack-expense-meta-grid">
                        <div class="meta-item"><i data-lucide="map-pin" style="width:13px; height:13px;"></i><span>${item.from}</span></div>
                        <div class="meta-item"><i data-lucide="arrow-right" style="width:13px; height:13px;"></i><span>${item.to}</span></div>
                        <div class="meta-item"><i data-lucide="car-front" style="width:13px; height:13px;"></i><span>${item.mode}</span></div>
                        <div class="meta-item"><i data-lucide="calendar-days" style="width:13px; height:13px;"></i><span>${formatDate(item.date)}</span></div>
                        <div class="meta-item meta-item-wide"><i data-lucide="clock-3" style="width:13px; height:13px;"></i><span>Filled on: ${formatDateTime(item.created_at || item.updated_at || item.date)}</span></div>
                    </div>
                    <div class="stack-expense-statuses">
                        <span class="status-tag ${String(item.manager_status).toLowerCase()}">Manager: ${item.manager_status}</span>
                        <span class="status-tag ${String(item.hr_status).toLowerCase()}">HR: ${item.hr_status}</span>
                        <span class="status-tag ${String(item.accountant_status).toLowerCase()}">Senior Manager: ${item.accountant_status}</span>
                        <span class="status-tag ${String(item.status).toLowerCase()}">Overall: ${item.status}</span>
                    </div>
                    <div class="stack-expense-actions">
                        <button class="btn-icon view" title="View Details" onclick="openDetailsFromStack(${item.id}, ${stackIndex})"><i data-lucide="eye"></i><span>View</span></button>
                        ${canMarkPaid ? `<button class="btn-icon pay" title="Mark as Paid" onclick="markExpensePaid(${item.id}, '${stack.key}')"><i data-lucide="badge-indian-rupee"></i><span>Mark Paid</span></button>` : ''}
                        ${item.needs_action ? (
                            item.can_act ? `
                                <button class="btn-icon approve" title="${canQuickApprove ? 'Approve' : 'Verify distance first'}" ${canQuickApprove ? '' : 'disabled'} onclick="updateStatus(${item.id}, 'Approved'); closeStackModal();"><i data-lucide="check"></i><span>Approve</span></button>
                                <button class="btn-icon reject" title="${canQuickReject ? 'Reject' : 'Verify distance first'}" ${canQuickReject ? '' : 'disabled'} onclick="updateStatus(${item.id}, 'Rejected'); closeStackModal();"><i data-lucide="x"></i><span>Reject</span></button>
                            ` : `
                                <button class="btn-icon locked" title="${item.window_message}" style="background: #fff7ed; color: #ea580c; border: 1px dashed #fed7aa; cursor: help; width: auto; padding: 0 12px;">
                                    <i data-lucide="lock" style="width: 14px; height: 14px;"></i><span>Locked</span>
                                </button>
                            `
                        ) : ''}
                    </div>
                </div>
            `;
        }).join('');

        modal.classList.add('active');
        initIcons();

        const refreshBulkBtnState = () => {
            const approveBtn = document.getElementById('bulkApproveBtn');
            const rejectBtn = document.getElementById('bulkRejectBtn');
            const checks = Array.from(document.querySelectorAll('.bulk-expense-check'));
            const selectedCount = checks.filter((c) => c.checked).length;
            const totalCount = checks.length;
            const checkpoints = Array.from(document.querySelectorAll('.bulk-checkpoint'));
            const allCheckpointDone = checkpoints.length > 0 && checkpoints.every((c) => c.checked);
            const canProceed = selectedCount > 0 && allCheckpointDone;
            if (approveBtn) approveBtn.disabled = !canProceed;
            if (rejectBtn) rejectBtn.disabled = !canProceed;

            const selectedMeta = document.getElementById('bulkSelectedCount');
            if (selectedMeta) {
                selectedMeta.textContent = `Selected ${selectedCount} of ${totalCount}`;
            }
        };

        const selectAll = document.getElementById('bulkSelectAllExpenses');
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                document.querySelectorAll('.bulk-expense-check').forEach((el) => {
                    el.checked = selectAll.checked;
                });
                refreshBulkBtnState();
            });
        }

        document.querySelectorAll('.bulk-expense-check').forEach((el) => {
            el.addEventListener('change', () => {
                const checks = Array.from(document.querySelectorAll('.bulk-expense-check'));
                const checked = checks.filter((c) => c.checked).length;
                if (selectAll) selectAll.checked = checked > 0 && checked === checks.length;
                refreshBulkBtnState();
            });
        });

        document.querySelectorAll('.bulk-checkpoint').forEach((el) => {
            el.addEventListener('change', refreshBulkBtnState);
        });

        refreshBulkBtnState();
    };

    window.submitBulkApprove = async (stackKey) => {
        await submitBulkDecision(stackKey, 'Approved');
    };

    window.submitBulkReject = async (stackKey) => {
        await submitBulkDecision(stackKey, 'Rejected');
    };

    window.markExpensePaid = async (id, stackKey = '') => {
        const item = state.expenses.find((e) => e.id == id);
        if (!item) {
            showToast('Expense not found', 'error');
            return;
        }

        const canMarkPaid = Boolean(item.can_pay) && String(item.status || '').toLowerCase() === 'approved' && String(item.payment_status || '').toLowerCase() !== 'paid';
        if (!canMarkPaid) {
            showToast('This expense is not eligible to be marked as paid.', 'error');
            return;
        }

        try {
            const form = new FormData();
            form.append('id', String(id));

            const response = await fetch('api/pay_expense.php', {
                method: 'POST',
                body: form
            });
            const result = await response.json();

            if (!result.success) {
                showToast(result.message || 'Failed to mark as paid.', 'error');
                return;
            }

            showToast(result.message || 'Expense marked as paid.', 'success');
            await fetchExpenses();

            if (currentItem && currentItem.id == id) {
                openDetails(id);
            }

            if (stackKey) {
                const newIndex = state.renderedStacks.findIndex((s) => s.key === stackKey);
                if (newIndex >= 0) {
                    openStackDetails(newIndex);
                }
            }
        } catch (error) {
            console.error('Mark paid error:', error);
            showToast('Network error while marking expense as paid.', 'error');
        }
    };

    const submitBulkDecision = async (stackKey, decision) => {
        const selected = Array.from(document.querySelectorAll('.bulk-expense-check:checked')).map((el) => Number(el.value));
        if (!selected.length) {
            showToast('Select at least one verified entry.', 'error');
            return;
        }

        const approveBtn = document.getElementById('bulkApproveBtn');
        const rejectBtn = document.getElementById('bulkRejectBtn');
        const reason = document.getElementById('bulkApproveReason')?.value.trim() || '';

        if (decision === 'Rejected') {
            const words = reason.split(/\s+/).filter(Boolean);
            if (words.length < 10) {
                showToast('For bulk reject, reason is mandatory with at least 10 words.', 'error');
                return;
            }
        }

        if (approveBtn) approveBtn.disabled = true;
        if (rejectBtn) rejectBtn.disabled = true;

        let successCount = 0;
        let failureCount = 0;

        for (const id of selected) {
            try {
                const response = await fetch('api/update_approval_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, status: decision, reason })
                });
                const result = await response.json();
                if (result.success) successCount += 1;
                else failureCount += 1;
            } catch (e) {
                failureCount += 1;
            }
        }

        await fetchExpenses();

        if (successCount > 0) {
            const label = decision.toLowerCase();
            showToast(`${successCount} expense(s) ${label} successfully${failureCount ? `, ${failureCount} failed.` : '.'}`, failureCount ? 'info' : 'success');
        } else {
            showToast(`Bulk ${decision.toLowerCase()} failed for selected entries.`, 'error');
        }

        const newIndex = state.renderedStacks.findIndex((s) => s.key === stackKey);
        if (newIndex >= 0) {
            openStackDetails(newIndex);
        } else {
            closeStackModal();
        }
    };

    window.closeStackModal = () => {
        const modal = document.getElementById('stackModal');
        if (modal) modal.classList.remove('active');
    };

    window.openDetailsFromStack = (id, stackIndex) => {
        state.stackUi.activeStackIndex = Number.isFinite(stackIndex) ? stackIndex : null;
        state.stackUi.reopenAfterDetailsClose = true;
        closeStackModal();
        openDetails(id);
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
        const item = state.expenses.find(e => e.id == id);
        if (!item) {
            showToast('Expense not found', 'error');
            return;
        }

        const isVerified = hasDistanceVerification(item);
        if (!isVerified) {
            showToast('Please complete distance verification first.', 'error');
            openDetails(id);
            return;
        }

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

    window.showEditExpenseModal = (id) => {
        const item = state.expenses.find(e => e.id == id);
        if (!item) {
            showToast('Expense not found', 'error');
            return;
        }

        if (!hasDistanceVerification(item)) {
            showToast('Please complete distance verification first.', 'error');
            return;
        }

        document.getElementById('editExpenseId').value = item.id;
        document.getElementById('editPurpose').value = item.purpose || '';
        document.getElementById('editFrom').value = item.from || '';
        document.getElementById('editTo').value = item.to || '';
        document.getElementById('editMode').value = item.mode || '';
        document.getElementById('editDate').value = item.date || '';
        document.getElementById('editDistance').value = item.distance ?? '';
        document.getElementById('editAmount').value = item.amount ?? '';

        const modal = document.getElementById('editExpenseModal');
        modal.style.display = 'flex';
        modal.classList.add('active');
        loadTransportRates().then(() => {
            applyEditAmountRules();
        });
        initIcons();
    };

    window.submitExpenseEdit = async () => {
        applyEditAmountRules();

        const id = document.getElementById('editExpenseId')?.value;
        const purpose = document.getElementById('editPurpose')?.value.trim() || '';
        const from_location = document.getElementById('editFrom')?.value.trim() || '';
        const to_location = document.getElementById('editTo')?.value.trim() || '';
        const mode_of_transport = document.getElementById('editMode')?.value.trim() || '';
        const travel_date = document.getElementById('editDate')?.value || '';
        const distance = parseFloat(document.getElementById('editDistance')?.value || '0');
        const amount = parseFloat(document.getElementById('editAmount')?.value || '0');

        if (!id) {
            showToast('Missing expense reference', 'error');
            return;
        }

        if (!purpose || !from_location || !to_location || !mode_of_transport || !travel_date) {
            showToast('Please fill all required fields.', 'error');
            return;
        }

        if (!Number.isFinite(distance) || distance < 0) {
            showToast('Distance must be a valid non-negative number.', 'error');
            return;
        }

        if (!Number.isFinite(amount) || amount < 0) {
            showToast('Amount must be a valid non-negative number.', 'error');
            return;
        }

        try {
            const response = await fetch('api/update_expense_details.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id,
                    purpose,
                    from_location,
                    to_location,
                    mode_of_transport,
                    travel_date,
                    distance,
                    amount
                })
            });

            const result = await response.json();
            if (!result.success) {
                showToast(result.message || 'Failed to update expense', 'error');
                return;
            }

            closeActionModal('editExpenseModal');
            showToast(result.message || 'Expense updated successfully', 'success');

            await fetchExpenses();
            openDetails(id);
        } catch (error) {
            console.error('Edit expense error:', error);
            showToast('Network error while updating expense', 'error');
        }
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
        
        // A user only unlocks details for themselves after they enter their own distance.
        const isVerified = hasDistanceVerification(item);

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

        if (state.stackUi.reopenAfterDetailsClose && state.stackUi.activeStackIndex !== null) {
            const indexToOpen = state.stackUi.activeStackIndex;
            state.stackUi.reopenAfterDetailsClose = false;
            openStackDetails(indexToOpen);
        }
    };

    const renderFooterActions = (item, isVerified) => {
        const footer = document.getElementById('modalFooterActions');
        footer.innerHTML = '<button class="btn-minimal" onclick="closeModal()">Dismiss</button>';
        const canMarkPaid = Boolean(item.can_pay) && String(item.status || '').toLowerCase() === 'approved' && String(item.payment_status || '').toLowerCase() !== 'paid';

        if (canMarkPaid) {
            footer.innerHTML += `<button class="btn-icon pay" onclick="markExpensePaid(${item.id})"><i data-lucide="badge-indian-rupee"></i> Mark as Paid</button>`;
        }

        if (item.can_edit !== false) {
            footer.innerHTML += `<button class="btn-icon edit" title="${isVerified ? 'Edit expense' : 'Verify distance first in details'}" ${isVerified ? '' : 'disabled'} onclick="showEditExpenseModal(${item.id})"><i data-lucide="pencil"></i> Edit</button>`;
        }
        if (item.needs_action) {
            if (item.can_act) {
                footer.innerHTML += `
                    <button class="btn-icon approve" ${isVerified ? '' : 'disabled'} onclick="showApprovalConfirm(${item.id})"><i data-lucide="check"></i> Approve</button>
                    <button class="btn-icon reject" ${isVerified ? '' : 'disabled'} onclick="updateStatus(${item.id}, 'Rejected')"><i data-lucide="x"></i> Reject</button>
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

    function parseBackendDateTime(dateTimeStr) {
        if (!dateTimeStr) return null;
        const raw = String(dateTimeStr).trim();
        if (!raw) return null;

        // Supports MySQL formats: YYYY-MM-DD and YYYY-MM-DD HH:mm:ss
        let normalized = raw;
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)) {
            normalized = raw.replace(' ', 'T');
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            normalized = `${raw}T00:00:00`;
        }

        const ts = new Date(normalized).getTime();
        return Number.isNaN(ts) ? null : ts;
    }

    function formatDateTime(dateTimeStr) {
        const ts = parseBackendDateTime(dateTimeStr);
        if (!ts) return 'Not available';
        const dt = new Date(ts);
        return dt.toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
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

    const editModeInput = document.getElementById('editMode');
    const editDistanceInput = document.getElementById('editDistance');
    if (editModeInput) editModeInput.addEventListener('input', applyEditAmountRules);
    if (editDistanceInput) editDistanceInput.addEventListener('input', applyEditAmountRules);


    // Initial load
    setFilterDefaults();
    fetchExpenses();
});
