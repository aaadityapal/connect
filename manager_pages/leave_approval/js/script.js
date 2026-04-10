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
    const employeeFilter = document.getElementById('employeeFilter');
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
        const employee = employeeFilter ? employeeFilter.value : 'All';
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

            // Employee match
            const matchesEmployee = employee === 'All' || req.employee === employee;

            // Date Range match
            const reqStart = req.dates.split(' to ')[0];
            const isWithinRange = (!fromDate || reqStart >= fromDate) && 
                                 (!toDate || reqStart <= toDate);

            return matchesSearch && matchesStatus && matchesType && matchesEmployee && isWithinRange;
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
        const userRole = document.getElementById('currentUserRole').value.toLowerCase();
        const isAdmin  = userRole === 'admin';

        // Set IDs and meta
        document.getElementById('actionRequestId').value = data.id;
        document.getElementById('actionType').value = type;
        document.getElementById('actionEmployeeName').innerText = data.employee;
        document.getElementById('actionLeaveType').innerText = data.type;

        // Quick Overview for HR/Admin
        const overview = document.getElementById('quickOverviewSection');
        if (overview && (userRole === 'hr' || userRole === 'admin')) {
            overview.style.display = 'block';
            document.getElementById('actionApplicantReason').innerText = data.reason || 'None provided';
            document.getElementById('actionApplicantAt').innerText = data.created_at ? new Date(data.created_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '';
            
            document.getElementById('actionLeaveDates').innerText = data.dates;
            document.getElementById('actionLeaveDuration').innerText = data.duration_label;
            
            const mgrReasonBox = document.getElementById('actionManagerReasonBox');
            if (data.manager_status === 'Approved' || data.manager_status === 'Rejected') {
                mgrReasonBox.style.display = 'block';
                document.getElementById('actionManagerReason').innerText = data.manager_reason || 'No remarks provided';
                document.getElementById('actionManagerAt').innerText = data.manager_at ? new Date(data.manager_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '';
            } else {
                mgrReasonBox.style.display = 'none';
            }
        } else if (overview) {
            overview.style.display = 'none';
        }

        // UI Setup
        const title = document.getElementById('actionModalTitle');
        const icon  = document.getElementById('actionIcon');
        const submitBtn = document.getElementById('actionSubmitBtn');
        const form = document.getElementById('leaveActionForm');

        // Workflow Warning for HR
        form.querySelector('.workflow-warning')?.remove();
        if (isApprove && userRole === 'hr' && data.manager_status !== 'Approved') {
            const warningDiv = document.createElement('div');
            warningDiv.className = 'workflow-warning';
            warningDiv.style = 'background: #fff7ed; color: #c2410c; padding: 10px; border-radius: 8px; border: 1px solid #fed7aa; margin-bottom: 1rem; font-size: 0.8rem; font-weight: 500; display: flex; align-items: center; gap: 8px;';
            warningDiv.innerHTML = `<i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i> <span>Manager Approval Pending: You cannot approve this yet.</span>`;
            form.prepend(warningDiv);
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
            if (window.lucide) lucide.createIcons();
        } else {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        }

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
        const loader = document.getElementById('globalLoader');
        if (loader) loader.style.display = 'flex';

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
                showResponseModal('success', 'Action Applied', res.message || 'Leave status updated successfully.');
                await fetchLeaveRequests(); // Refresh table
                await fetchLeaveBank();     // Refresh bank if visible
            } else {
                showResponseModal('error', 'Action Failed', res.message || 'Error updating status.');
            }
        } catch (error) {
            console.error('Error in handleLeaveAction:', error);
            showResponseModal('error', 'System Error', 'Failed to communicate with the server.');
        } finally {
            if (loader) loader.style.display = 'none';
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
    [searchInput, statusFilter, typeFilter, employeeFilter, fromDateFilter, toDateFilter].forEach(el => {
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
    const leaveBankGrid = document.getElementById('leaveBankCardsGrid');
    const lbUserFilter = document.getElementById('lbUserFilter');
    const lbYearFilter = document.getElementById('lbYearFilter');
    const lbMonthFilter = document.getElementById('lbMonthFilter');
    const lbRefreshBtn = document.getElementById('refreshLeaveBankBtn');

    async function fetchLeaveBank() {
        if (!leaveBankGrid) return;

        leaveBankGrid.innerHTML = `
            <div style="grid-column: 1 / -1; padding: 4rem; text-align: center;">
                <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                    <div class="fa-spin" style="font-size: 1.5rem; color: var(--primary);"><i class="fa-solid fa-spinner"></i></div>
                    <p style="color: var(--text-muted); font-size: 0.85rem;">Loading leave bank data...</p>
                </div>
            </div>
        `;

        const user = lbUserFilter.value;
        const year = lbYearFilter.value;
        const month = lbMonthFilter.value;

        try {
            const resp = await fetch(`api/fetch_leave_bank.php?user=${user}&year=${year}&month=${month}`);
            const res = await resp.json();

            if (res.success) {
                // Populate both filters if they were unpopulated
                const filtersToPopulate = [lbUserFilter, employeeFilter];
                let justPopulated = false;

                filtersToPopulate.forEach(selectEl => {
                    if (selectEl && (selectEl.options.length <= 1 || selectEl.getAttribute('data-populated') !== 'true') && res.users) {
                        const previousVal = selectEl.value;
                        selectEl.innerHTML = (selectEl === employeeFilter) ? '<option value="All">All Employees</option>' : '';
                        
                        if (res.users.length === 0) {
                            const opt = document.createElement('option');
                            opt.value = "";
                            opt.textContent = "No employees assigned";
                            opt.disabled = true;
                            selectEl.appendChild(opt);
                        } else {
                            res.users.forEach(u => {
                                const opt = document.createElement('option');
                                opt.value = (selectEl === employeeFilter) ? u.username : u.id;
                                opt.textContent = u.username;
                                selectEl.appendChild(opt);
                            });
                            selectEl.setAttribute('data-populated', 'true');
                            justPopulated = true;
                            
                            // Restore or select first
                            if (previousVal && [...selectEl.options].some(o => o.value === previousVal)) {
                                selectEl.value = previousVal;
                            }
                        }
                    }
                });

                // Force re-fetch on first ever population to ensure the screen isn't empty
                if (justPopulated && lbUserFilter && lbUserFilter.value) {
                    return fetchLeaveBank();
                }

                renderLeaveBank(res.data);
            } else {
                leaveBankGrid.innerHTML = `<div style="grid-column: 1 / -1; padding: 2rem; color: red; text-align: center;">${res.message}</div>`;
            }
        } catch (e) {
            console.error('Leave Bank fetch error:', e);
            leaveBankGrid.innerHTML = `<div style="grid-column: 1 / -1; padding: 2rem; color: red; text-align: center;">System Error</div>`;
        }
    }

    function renderLeaveBank(data) {
        leaveBankGrid.innerHTML = '';

        if (!data || data.length === 0) {
            leaveBankGrid.innerHTML = `
                <div style="grid-column: 1 / -1; padding: 4rem; text-align: center; color: #94a3b8;">
                    <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.2;"></i>
                    <p>No leave balances found.</p>
                </div>
            `;
            if (window.lucide) lucide.createIcons();
            return;
        }

        // Add a minimalistic Profile Header at the start of the grid
        const first = data[0];
        const profileHeader = document.createElement('div');
        profileHeader.className = 'lb-profile-header';
        profileHeader.style = 'grid-column: 1 / -1; margin-bottom: 0.5rem; padding: 0.5rem 1.25rem;';
        profileHeader.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 42px; height: 42px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #475569; font-size: 0.9rem;">${first.username.charAt(0)}</div>
                <div>
                    <h2 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">${first.username}</h2>
                    <p style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">${first.user_role || 'Employee'}</p>
                </div>
            </div>
        `;
        leaveBankGrid.appendChild(profileHeader);

        data.forEach(row => {
            const total = parseFloat(row.total_balance) || 0;
            const remaining = parseFloat(row.remaining_balance) || 0;
            const used = (total - remaining).toFixed(1);
            const name = row.leave_type_name.toLowerCase();
            
            // Icon & Color mapping
            let icon = 'file-text';
            let color = '#6366f1'; // Default Indigo
            let bgColor = '#eef2ff';

            if (name.includes('casual')) {
                icon = 'coffee';
                color = '#0ea5e9'; // Sky
                bgColor = '#f0f9ff';
            } else if (name.includes('sick')) {
                icon = 'thermometer';
                color = '#f59e0b'; // Amber
                bgColor = '#fffbeb';
            } else if (name.includes('short')) {
                icon = 'clock';
                color = '#8b5cf6'; // Violet
                bgColor = '#f5f3ff';
            } else if (name.includes('comp')) {
                icon = 'award';
                color = '#10b981'; // Emerald
                bgColor = '#ecfdf5';
            } else if (name.includes('parental') || name.includes('paternity') || name.includes('maternity')) {
                icon = 'baby';
                color = '#ec4899'; // Pink
                bgColor = '#fdf2f8';
            }

            const card = document.createElement('div');
            card.className = 'lb-card';
            card.innerHTML = `
                <div class="lb-card-header">
                    <div class="lb-icon-box" style="background: ${bgColor}; color: ${color};">
                        <i data-lucide="${icon}" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div class="lb-title-wrap">
                        <span class="lb-type-name">${row.leave_type_name}</span>
                        <span class="lb-year-text">Cycle Year ${row.year}</span>
                    </div>
                </div>
                
                <div class="lb-main-stat">
                    <span class="lb-value-big">${remaining}</span>
                    <span class="lb-label-small">Days Left</span>
                </div>
                
                <div class="lb-footer">
                    <div class="lb-sub-stat">
                        <span class="lb-sub-label">Used</span>
                        <span class="lb-sub-value">${used}</span>
                    </div>
                    <div class="lb-sub-stat">
                        <span class="lb-sub-label">Total</span>
                        <span class="lb-sub-value">${total}</span>
                    </div>
                </div>

                <div class="lb-trend-indicator">
                     <i data-lucide="${icon}" style="width: 40px; height: 40px; color: ${color};"></i>
                </div>
            `;
            leaveBankGrid.appendChild(card);
        });

        if (window.lucide) lucide.createIcons();
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

    /* =====================================================
     *  GLOBAL RESPONSE MODAL
     * ===================================================== */
    window.showResponseModal = function(type, title, message) {
        const modal = document.getElementById('responseModal');
        if (!modal) return;

        // Hide all icons first
        document.getElementById('resIconSuccess').style.display = 'none';
        document.getElementById('resIconError').style.display = 'none';
        document.getElementById('resIconWarning').style.display = 'none';

        // Show relevant icon
        if (type === 'success') document.getElementById('resIconSuccess').style.display = 'flex';
        else if (type === 'error') document.getElementById('resIconError').style.display = 'flex';
        else document.getElementById('resIconWarning').style.display = 'flex';

        document.getElementById('resTitle').textContent = title || 'Notification';
        document.getElementById('resMessage').textContent = message || '';
        
        modal.style.display = 'flex';
        if (window.lucide) lucide.createIcons();
    };

    window.closeResponseModal = function() {
        const modal = document.getElementById('responseModal');
        if (modal) modal.style.display = 'none';
    };

    /* =====================================================
     *  MANUAL LEAVE ENTRY Logic
     * ===================================================== */
    const manualBtn = document.getElementById('addLeaveManualBtn');
    const manualModal = document.getElementById('manualLeaveModal');
    const manualUserSelect = document.getElementById('manualUserSelect');
    const manualDetails = document.getElementById('manualLeaveDetails');
    const manualNone = document.getElementById('manualUserNone');
    const manualSubmit = document.getElementById('manualSubmitBtn');

    window.openManualLeaveModal = function() {
        if (!manualModal) return;
        
        // Populate user select from lbUserFilter if populated
        if (lbUserFilter && lbUserFilter.options.length > 0) {
            const currentUsers = [...lbUserFilter.options];
            manualUserSelect.innerHTML = '<option value="">Select Employee...</option>';
            currentUsers.forEach(opt => {
                if (opt.value) {
                    const newOpt = document.createElement('option');
                    newOpt.value = opt.value;
                    newOpt.textContent = opt.textContent;
                    manualUserSelect.appendChild(newOpt);
                }
            });
        }

        // Reset fields
        manualUserSelect.value = '';
        manualDetails.style.display = 'none';
        manualNone.style.display = 'block';
        manualSubmit.style.display = 'none';
        document.getElementById('manualLeaveForm').reset();
        
        manualModal.style.display = 'flex';
        if (window.lucide) lucide.createIcons();
    };

    window.closeManualLeaveModal = function() {
        if (manualModal) manualModal.style.display = 'none';
    };

    if (manualBtn) {
        manualBtn.onclick = openManualLeaveModal;
    }

    window.onManualUserChange = async function() {
        const userId = manualUserSelect.value;
        if (!userId) {
            manualDetails.style.display = 'none';
            manualNone.style.display = 'block';
            manualSubmit.style.display = 'none';
            return;
        }

        const balContainer = document.getElementById('manualBalanceSummary');
        const typeSelect = document.getElementById('manualTypeSelect');
        
        balContainer.innerHTML = '<p style="font-size: 0.8rem; color: #64748b;">Fetching balances...</p>';
        typeSelect.innerHTML = '<option value="">Loading...</option>';

        try {
            const year = new Date().getFullYear();
            const resp = await fetch(`api/fetch_leave_bank.php?user=${userId}&year=${year}&show_all=1`);
            const res = await resp.json();

            if (res.success && res.data) {
                // Store shift info for dynamic short leave logic
                window.currentManualShiftInfo = res.shift_info || { morning_range: '09:00 - 10:30', evening_range: '16:30 - 18:00' };

                // Populate Balance Cards
                balContainer.innerHTML = res.data.map(row => `
                    <div class="mini-bal-card" title="${row.leave_type_name}">
                        <span class="bal-value">${row.remaining_balance}</span>
                        <span class="bal-label">${row.leave_type_name.split(' ')[0]}</span>
                    </div>
                `).join('');

                // Populate Type Dropdown
                typeSelect.innerHTML = '<option value="">Select Category...</option>' + 
                    res.data.map(row => `<option value="${row.leave_type_id}">${row.leave_type_name}</option>`).join('');

                manualDetails.style.display = 'block';
                manualNone.style.display = 'none';
                manualSubmit.style.display = 'inline-flex';
            } else {
                balContainer.innerHTML = '<p style="color: red; font-size: 0.8rem;">Failed to load user bank.</p>';
            }
        } catch (e) {
            console.error(e);
            balContainer.innerHTML = '<p style="color: red; font-size: 0.8rem;">Error.</p>';
        }
    };

    // Dynamic Duration Options for Short Leave
    const mTypeSel = document.getElementById('manualTypeSelect');
    const mDaySel  = document.getElementById('manualDayType');

    if (mTypeSel && mDaySel) {
        mTypeSel.addEventListener('change', function() {
            const txt = mTypeSel.options[mTypeSel.selectedIndex].text.toLowerCase();
            const sInfo = window.currentManualShiftInfo || { morning_range: '09:00 - 10:30', evening_range: '16:30 - 18:00' };

            if (txt.includes('short')) {
                // Short Leave: Show Morning/Evening with times
                mDaySel.innerHTML = `
                    <option value="Short Leave">Morning (${sInfo.morning_range})</option>
                    <option value="Short Leave">Evening (${sInfo.evening_range})</option>
                `;
            } else {
                // Regular Leave: Show Full/Half options
                mDaySel.innerHTML = `
                    <option value="Full Day">Full Day</option>
                    <option value="First Half">First Half</option>
                    <option value="Second Half">Second Half</option>
                `;
            }
            toggleManualTimeFields();
        });
    }

    window.toggleManualTimeFields = function() {
        const dayType = mDaySel ? mDaySel.value : 'Full Day';
        const dayTypeText = mDaySel ? mDaySel.options[mDaySel.selectedIndex].text : '';
        
        const timeFields = document.getElementById('manualTimeFields');
        const endDateGroup = document.getElementById('endDateGroup');
        
        if (dayType === 'Short Leave' || dayTypeText.toLowerCase().includes('morning') || dayTypeText.toLowerCase().includes('evening')) {
            timeFields.style.display = 'grid';
            endDateGroup.style.display = 'none';
            document.getElementById('manualEndDate').required = false;

            // Optional: Auto-fill times from shift info if needed
            const sInfo = window.currentManualShiftInfo;
            if (sInfo) {
                const range = dayTypeText.includes('Morning') ? sInfo.morning_range : sInfo.evening_range;
                if (range && range.includes('-')) {
                    const [start, end] = range.split('-').map(t => t.trim());
                    document.getElementById('manualTimeFrom').value = start;
                    document.getElementById('manualTimeTo').value = end;
                }
            }
        } else {
            timeFields.style.display = 'none';
            endDateGroup.style.display = 'block';
            document.getElementById('manualEndDate').required = (dayType === 'Full Day');
        }
    };

    window.handleManualLeaveSubmit = async function(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // Basic Word Count Check
        const words = (data.reason || '').trim().split(/\s+/).filter(w => w.length > 0).length;
        if (words < 10) {
            document.getElementById('manualReasonWarning').style.display = 'block';
            return;
        } else {
            document.getElementById('manualReasonWarning').style.display = 'none';
        }

        // Add Type Name for the API (if needed)
        const typeEl = document.getElementById('manualTypeSelect');
        const selectedOption = typeEl.options[typeEl.selectedIndex];
        data.type_name = selectedOption ? selectedOption.getAttribute('data-name') : '';

        // Show Global Loader
        const loader = document.getElementById('globalLoader');
        if (loader) loader.style.display = 'flex';

        try {
            const resp = await fetch('api/manual_leave_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const res = await resp.json();

            if (res.success) {
                closeManualLeaveModal();
                await fetchLeaveRequests();
                await fetchLeaveBank();
                showResponseModal('success', 'Entry Saved', res.message || 'Manual leave entry recorded successfully.');
            } else {
                showResponseModal('warning', 'Review Needed', res.message || 'Failed to submit manual leave');
            }
        } catch (e) {
            console.error(e);
            showResponseModal('error', 'Critical Error', 'An unexpected system error occurred.');
        } finally {
            if (loader) loader.style.display = 'none';
        }
    };

    // Global Modal Click-away
    const oldOnClick = window.onclick;
    window.onclick = function(event) {
        if (oldOnClick) oldOnClick(event);
        if (event.target == manualModal) closeManualLeaveModal();
    };

});

