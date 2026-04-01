/*
 * =====================================================
 *  LEAVE APPROVAL — Interactivity
 *  manager_pages/leave_approval/js/script.js
 * =====================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    let leaveRequests = [];

    const tableBody = document.getElementById('leaveTableBody');
    const searchInput = document.getElementById('leaveSearchInput');
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const fromDateFilter = document.getElementById('fromDateFilter');
    const toDateFilter = document.getElementById('toDateFilter');
    const refreshBtn = document.getElementById('refreshBtn');

    /**
     * Initial Setup: Set default date range (1st to Last day of current month)
     */
    function setDefaultDates() {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        const formatDate = (d) => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        if (fromDateFilter) fromDateFilter.value = formatDate(firstDay);
        if (toDateFilter) toDateFilter.value = formatDate(lastDay);
    }

    /**
     * Fetch leave requests from backend
     */
    async function fetchLeaveRequests() {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 4rem; color: #94a3b8;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                        <div class="fa-spin" style="font-size: 1.5rem; color: var(--primary);"><i class="fa-solid fa-spinner"></i></div>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">Fetching leave requests...</p>
                    </div>
                </td>
            </tr>
        `;

        try {
            const resp = await fetch('api/fetch_leave_requests.php');
            const res = await resp.json();
            
            if (res.success) {
                leaveRequests = res.data;
                populateTypeFilter();
                renderTable();
                updateStats();
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 4rem; color: #ef4444;">
                            <p>${res.message || 'Failed to load requests.'}</p>
                        </td>
                    </tr>
                `;
            }
        } catch (e) {
            console.error('Fetch error:', e);
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:2rem; color:red;">System Error: Check Console</td></tr>`;
        }
    }

    /**
     * Dynamically populate the Type filter dropdown
     */
    function populateTypeFilter() {
        if (!typeFilter) return;
        const types = [...new Set(leaveRequests.map(r => r.type))];
        typeFilter.innerHTML = '<option value="All">All Types</option>' + 
            types.map(t => `<option value="${t}">${t}</option>`).join('');
    }

    /**
     * Render the table with optional filtering
     */
    function renderTable() {
        const filter = searchInput.value.toLowerCase();
        const status = statusFilter.value;
        const type   = typeFilter.value;
        const fromDate = fromDateFilter.value;
        const toDate   = toDateFilter.value;

        tableBody.innerHTML = '';
        
        const filteredData = leaveRequests.filter(req => {
            // Search match
            const matchesSearch = req.employee.toLowerCase().includes(filter) || 
                                req.id.toLowerCase().includes(filter);
            
            // Status match
            const matchesStatus = status === 'All' || req.manager_status === status;
            
            // Type match
            const matchesType = type === 'All' || req.type === type;

            // Date Range match
            const reqStart = req.dates.split(' to ')[0];
            const isWithinRange = (!fromDate || reqStart >= fromDate) && 
                                 (!toDate || reqStart <= toDate);

            return matchesSearch && matchesStatus && matchesType && isWithinRange;
        });

        if (filteredData.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 5rem; color: #94a3b8;">
                        <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.15;"></i>
                        <p style="font-size: 0.95rem;">No leave requests found for selected filters.</p>
                    </td>
                </tr>
            `;
            if (window.lucide) lucide.createIcons();
            return;
        }

        filteredData.forEach(req => {
            // Calculate row background class
            let rowClass = 'row-pending';
            const mStatus = req.manager_status.toLowerCase();
            const hStatus = req.hr_status.toLowerCase();

            if (mStatus === 'approved' && hStatus === 'approved') {
                rowClass = 'row-approved';
            } else if (mStatus === 'rejected' || hStatus === 'rejected') {
                rowClass = 'row-rejected';
            } else if (mStatus === 'approved' || hStatus === 'approved') {
                rowClass = 'row-half-approved';
            }

            const row = document.createElement('tr');
            row.className = rowClass;
            row.innerHTML = `
                <td>
                    <div class="employee-cell">
                        <div class="avatar">${req.employee.charAt(0)}</div>
                        <div class="employee-info">
                            <span class="employee-name">${req.employee}</span>
                            <span class="employee-id">${req.user_role.toUpperCase()}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="font-weight: 500;">${req.type}</span>
                </td>
                <td>
                    <div style="font-size: 0.85rem; color: var(--text-muted); font-variant-numeric: tabular-nums;">
                        ${req.dates}
                    </div>
                    <div style="font-weight: 600; font-size: 0.75rem; margin-top: 4px; color: var(--primary);">
                        ${req.duration_label}
                    </div>
                </td>
                <td>
                    <span class="status-tag ${req.manager_status.toLowerCase()}">${req.manager_status}</span>
                </td>
                <td>
                    <span class="status-tag ${req.hr_status.toLowerCase()}">${req.hr_status}</span>
                </td>
                <td>
                    <p style="font-size: 0.8rem; color: var(--text-muted); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${req.reason}">
                        ${req.reason}
                    </p>
                </td>
                <td>
                    <div class="actions">
                        <button class="btn-icon view" title="View Request Details" onclick='openDetailsModal(${JSON.stringify(req).replace(/'/g, "&apos;")})'>
                            <i data-lucide="eye" style="width: 16px; height: 16px;"></i>
                        </button>
                        <button class="btn-icon approve" title="Approve Request" onclick='openActionModal("approve", ${JSON.stringify(req).replace(/'/g, "&apos;")})'>
                            <i data-lucide="check" style="width: 16px; height: 16px;"></i>
                        </button>
                        <button class="btn-icon reject" title="Reject Request" onclick='openActionModal("reject", ${JSON.stringify(req).replace(/'/g, "&apos;")})'>
                            <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                        </button>
                    </div>
                </td>
            `;
            tableBody.appendChild(row);
        });

        // Re-initialize icons
        if (window.lucide) lucide.createIcons();
    }

    /**
     * Modal Logic
     */
    window.openDetailsModal = function(data) {
        const modal = document.getElementById('leaveDetailsModal');
        if (!modal) return;

        document.getElementById('modalAvatar').innerText = data.employee.charAt(0);
        document.getElementById('modalEmployeeName').innerText = data.employee;
        document.getElementById('modalUserRole').innerText = data.user_role.toUpperCase();
        
        const mgrStatusEl = document.getElementById('modalMgrStatus');
        const hrStatusEl = document.getElementById('modalHrStatus');

        mgrStatusEl.innerText = data.manager_status;
        mgrStatusEl.className = `status-tag ${data.manager_status.toLowerCase()}`;
        
        hrStatusEl.innerText = data.hr_status;
        hrStatusEl.className = `status-tag ${data.hr_status.toLowerCase()}`;

        document.getElementById('modalLeaveType').innerText = data.type;
        document.getElementById('modalDates').innerText = data.dates;
        document.getElementById('modalDuration').innerText = data.duration_label;
        document.getElementById('modalReason').innerText = data.reason;

        // Attach buttons in modal to open action modal
        document.getElementById('modalRejectBtn').onclick = () => openActionModal('reject', data);
        document.getElementById('modalApproveBtn').onclick = () => openActionModal('approve', data);

        // Attachments
        const attWrap = document.getElementById('modalAttachments');
        attWrap.innerHTML = '';
        if (data.attachments && data.attachments.length > 0) {
            data.attachments.forEach(att => {
                const link = document.createElement('a');
                link.href = '../../' + att.path;
                link.target = '_blank';
                link.className = 'attachment-link';
                link.innerHTML = `<i data-lucide="paperclip"></i> ${att.name}`;
                attWrap.appendChild(link);
            });
            if (window.lucide) lucide.createIcons();
        } else {
            attWrap.innerHTML = '<span class="no-data">None uploaded</span>';
        }

        modal.style.display = 'flex';
    };

    window.closeDetailsModal = function() {
        const modal = document.getElementById('leaveDetailsModal');
        if (modal) modal.style.display = 'none';
    };

    // Close on click outside
    window.onclick = function(event) {
        const modal = document.getElementById('leaveDetailsModal');
        const actionModal = document.getElementById('leaveActionModal');
        if (event.target == modal) closeDetailsModal();
        if (event.target == actionModal) closeActionModal();
    };

    /**
     * Action Modal (Approve/Reject)
     */
    window.openActionModal = function(type, data) {
        const modal = document.getElementById('leaveActionModal');
        if (!modal) return;

        const isApprove = type === 'approve';
        const userRole = document.getElementById('currentUserRole').value;
        const isAdmin  = userRole === 'admin';

        // Set IDs and meta
        document.getElementById('actionRequestId').value = data.id;
        document.getElementById('actionType').value = type;
        document.getElementById('actionEmployeeName').innerText = data.employee;
        document.getElementById('actionLeaveType').innerText = data.type;

        // UI Setup
        const title = document.getElementById('actionModalTitle');
        const icon  = document.getElementById('actionIcon');
        const submitBtn = document.getElementById('actionSubmitBtn');

        title.innerText = isApprove ? 'Approve Leave' : 'Reject Leave';
        icon.className = `header-icon ${isApprove ? 'approve' : 'reject'}`;
        icon.setAttribute('data-lucide', isApprove ? 'check-circle' : 'x-circle');
        submitBtn.innerText = isApprove ? 'Confirm Approval' : 'Confirm Rejection';
        submitBtn.className = isApprove ? 'btn-primary' : 'btn-danger';

        // Remarks Requirements
        const mgrLabelStatus = document.getElementById('mgrReasonStatus');
        const hrLabelStatus = document.getElementById('hrReasonStatus');
        
        mgrLabelStatus.innerText = isApprove ? '(Optional)' : '(Required - 10 words min)';
        mgrLabelStatus.style.color = isApprove ? '#94a3b8' : '#ef4444';

        if (hrLabelStatus) {
            hrLabelStatus.innerText = isApprove ? '(Optional)' : '(Required - 10 words min)';
            hrLabelStatus.style.color = isApprove ? '#94a3b8' : '#ef4444';
        }

        // Show/Hide sections
        document.getElementById('hrReasonSection').style.display = isAdmin ? 'block' : 'none';

        // Clear previous
        document.getElementById('mgrReason').value = '';
        document.getElementById('hrReason').value = '';
        document.getElementById('mgrReasonWarning').style.display = 'none';
        document.getElementById('hrReasonWarning').style.display = 'none';

        modal.style.display = 'flex';
        if (window.lucide) lucide.createIcons();
    };

    window.closeActionModal = function() {
        const modal = document.getElementById('leaveActionModal');
        if (modal) modal.style.display = 'none';
    };

    window.handleLeaveAction = async function(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const isReject = data.action_type === 'reject';
        const userRole = document.getElementById('currentUserRole').value;
        const isAdmin  = userRole === 'admin';

        // Validation logic
        const countWords = (str) => str.trim().split(/\s+/).filter(w => w.length > 0).length;
        
        if (isReject) {
            if (countWords(data.manager_reason) < 10) {
                document.getElementById('mgrReasonWarning').style.display = 'block';
                return;
            }
            if (isAdmin && countWords(data.hr_reason) < 10) {
                document.getElementById('hrReasonWarning').style.display = 'block';
                return;
            }
        }

        // Submit to API
        try {
            const resp = await fetch('api/update_leave_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const res = await resp.json();

            if (res.success) {
                closeActionModal();
                closeDetailsModal();
                fetchLeaveRequests(); // Refresh table
                // Optional: Show toast
            } else {
                alert(res.message || 'Operation failed');
            }
        } catch (e) {
            console.error(e);
            alert('System error - check console');
        }
    };

    /**
     * Update the KPI stats based on the current data
     */
    function updateStats() {
        const counts = { Pending: 0, Approved: 0, Rejected: 0 };
        leaveRequests.forEach(req => {
            if (counts[req.manager_status] !== undefined) counts[req.manager_status]++;
        });

        const pendingEl  = document.getElementById('stat-pending');
        const approvedEl = document.getElementById('stat-approved');
        const rejectedEl = document.getElementById('stat-rejected');

        if (pendingEl)  pendingEl.innerText = counts.Pending;
        if (approvedEl) approvedEl.innerText = counts.Approved;
        if (rejectedEl) rejectedEl.innerText = counts.Rejected;
    }

    // Event listeners for filtering
    [searchInput, statusFilter, typeFilter, fromDateFilter, toDateFilter].forEach(el => {
        if (!el) return;
        const ev = (el.tagName === 'SELECT' || el.type === 'date') ? 'change' : 'input';
        el.addEventListener(ev, renderTable);
    });

    if (refreshBtn) {
        refreshBtn.addEventListener('click', fetchLeaveRequests);
    }

    const statsCards = {
        Pending: document.querySelector('.stat-card:has(.pending)') || document.querySelector('.stat-card:nth-child(1)'),
        Approved: document.querySelector('.stat-card:has(.approved)') || document.querySelector('.stat-card:nth-child(2)'),
        Rejected: document.querySelector('.stat-card:has(.rejected)') || document.querySelector('.stat-card:nth-child(3)')
    };

    Object.entries(statsCards).forEach(([status, card]) => {
        if (card) {
            card.style.cursor = 'pointer';
            card.addEventListener('click', () => {
                statusFilter.value = status;
                renderTable();
            });
        }
    });

    // Initial render
    setDefaultDates();
    fetchLeaveRequests();

    /* =====================================================
     *  LEAVE BANK — Logic
     * ===================================================== */
    const lbTableBody = document.getElementById('leaveBankTableBody');
    const lbUserFilter = document.getElementById('lbUserFilter');
    const lbYearFilter = document.getElementById('lbYearFilter');
    const lbMonthFilter = document.getElementById('lbMonthFilter');
    const lbRefreshBtn = document.getElementById('refreshLeaveBankBtn');

    async function fetchLeaveBank() {
        if (!lbTableBody) return;

        lbTableBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 3rem;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                        <div class="fa-spin" style="font-size: 1.5rem; color: var(--primary);"><i class="fa-solid fa-spinner"></i></div>
                        <p style="color: var(--text-muted); font-size: 0.85rem;">Loading leave bank data...</p>
                    </div>
                </td>
            </tr>
        `;

        const user = lbUserFilter.value;
        const year = lbYearFilter.value;
        const month = lbMonthFilter.value;

        try {
            const resp = await fetch(`api/fetch_leave_bank.php?user=${user}&year=${year}&month=${month}`);
            const res = await resp.json();

            if (res.success) {
                // Populate user filter if empty (first load)
                if ((lbUserFilter.options.length <= 1 || lbUserFilter.value === "") && res.users) {
                    lbUserFilter.innerHTML = ''; // Clear "Select User..."
                    res.users.forEach((u, index) => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.username;
                        lbUserFilter.appendChild(opt);
                    });
                    
                    // If we just populated and had no user, re-fetch for the newly selected first user
                    if (user === "" && lbUserFilter.value) {
                        return fetchLeaveBank();
                    }
                }
                renderLeaveBank(res.data);
            } else {
                lbTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:2rem; color:red;">${res.message}</td></tr>`;
            }
        } catch (e) {
            console.error('Leave Bank fetch error:', e);
            lbTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:2rem; color:red;">System Error</td></tr>`;
        }
    }

    function renderLeaveBank(data) {
        lbTableBody.innerHTML = '';

        if (!data || data.length === 0) {
            lbTableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 4rem; color: #94a3b8;">
                        <p>No leave bank records found for selected filters.</p>
                    </td>
                </tr>
            `;
            return;
        }

        data.forEach(row => {
            const tr = document.createElement('tr');
            
            // Calculate used leaves (optional - for now show remaining vs total)
            const total = parseFloat(row.total_balance) || 0;
            const remaining = parseFloat(row.remaining_balance) || 0;
            const used = (total - remaining).toFixed(1);

            tr.innerHTML = `
                <td>
                    <div class="employee-cell">
                        <div class="avatar" style="background: #f1f5f9; color: #64748b; font-size: 0.7rem; font-weight: 700;">${row.username.charAt(0)}</div>
                        <div class="employee-info">
                            <span class="employee-name" style="font-size: 0.85rem;">${row.username}</span>
                            <span class="employee-id" style="font-size: 0.7rem;">${row.unique_id}</span>
                        </div>
                    </div>
                </td>
                <td><span style="font-weight: 500; font-size: 0.85rem;">${row.leave_type_name}</span></td>
                <td><span style="font-variant-numeric: tabular-nums; font-weight: 600;">${total}</span></td>
                <td><span style="font-variant-numeric: tabular-nums; color: #ef4444; font-weight: 500;">${used}</span></td>
                <td><span style="font-variant-numeric: tabular-nums; color: #10b981; font-weight: 700;">${remaining}</span></td>
                <td><span style="font-size: 0.85rem; color: #64748b;">${row.year}</span></td>
            `;
            lbTableBody.appendChild(tr);
        });
    }

    // Listeners for Leave Bank
    [lbUserFilter, lbYearFilter, lbMonthFilter].forEach(el => {
        if (el) el.addEventListener('change', fetchLeaveBank);
    });

    if (lbRefreshBtn) {
        lbRefreshBtn.addEventListener('click', fetchLeaveBank);
    }

    // Load initial bank data
    fetchLeaveBank();

});

