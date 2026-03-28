document.addEventListener("DOMContentLoaded", () => {

    // ─────────────────────────────────────────────
    // 3. Custom Dropdowns  (fixed-position portal)
    // ─────────────────────────────────────────────
    const floatingMenu = document.createElement('div');
    floatingMenu.id = 'dd-floating-menu';
    Object.assign(floatingMenu.style, {
        position: 'fixed', zIndex: '99999',
        background: '#fff', border: '1px solid #e5e7eb',
        borderRadius: '8px', boxShadow: '0 8px 32px rgba(0,0,0,0.16)',
        padding: '5px', minWidth: '170px', maxHeight: '240px',
        overflowY: 'auto', display: 'none', scrollbarWidth: 'thin',
    });
    document.body.appendChild(floatingMenu);

    let activeDd = null;
    let shiftInfo = { morning_range: '09:00 - 10:30', evening_range: '16:30 - 18:00' };

    const fetchShiftInfo = async () => {
        try {
            const resp = await fetch('../api/fetch_user_shift.php');
            const res = await resp.json();
            if (res.success) {
                shiftInfo = res.data;
            }
        } catch (e) { console.error("Shift info error:", e); }
    };
    fetchShiftInfo();

    function openDd(dd) {
        if (activeDd === dd) { closeDd(); return; }
        closeDd();
        activeDd = dd;
        dd.classList.add('open');

        const srcMenu = dd.querySelector('.dropdown-menu');
        floatingMenu.innerHTML = srcMenu.innerHTML;

        floatingMenu.querySelectorAll('.dropdown-item').forEach(item => {
            item.style.cssText = 'padding:8px 12px;border-radius:6px;font-size:0.82rem;cursor:pointer;color:#374151;transition:background 0.12s;';
            item.addEventListener('mouseenter', () => { item.style.background = '#fff5f5'; item.style.color = '#c62828'; });
            item.addEventListener('mouseleave', () => { item.style.background = ''; item.style.color = '#374151'; });
        });

        const trigger = dd.querySelector('.dropdown-trigger');
        const rect = trigger.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;

        floatingMenu.style.display = 'block';
        floatingMenu.style.left = rect.left + 'px';
        floatingMenu.style.minWidth = rect.width + 'px';

        if (spaceBelow > 150) {
            floatingMenu.style.top = (rect.bottom + 4) + 'px';
            floatingMenu.style.bottom = 'auto';
        } else {
            floatingMenu.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
            floatingMenu.style.top = 'auto';
        }
    }

    function closeDd() {
        if (activeDd) { activeDd.classList.remove('open'); activeDd = null; }
        floatingMenu.style.display = 'none';
        floatingMenu.innerHTML = '';
    }

    document.addEventListener('click', e => {
        const trigger = e.target.closest('.dropdown-trigger');
        const floatItem = floatingMenu.contains(e.target) ? e.target.closest('.dropdown-item') : null;

        if (trigger) {
            e.stopPropagation();
            openDd(trigger.closest('.custom-dropdown'));

        } else if (floatItem) {
            e.stopPropagation();
            if (floatItem.classList.contains('disabled')) return; // Prevents selecting locked types
            
            const dd = activeDd;
            const selectedSpan = dd.querySelector('.selected-value');
            const srcMenu = dd.querySelector('.dropdown-menu');

            selectedSpan.textContent = floatItem.dataset.value;
            srcMenu.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('active'));
            const mirror = [...srcMenu.querySelectorAll('.dropdown-item')]
                .find(i => i.dataset.value === floatItem.dataset.value);
            if (mirror) mirror.classList.add('active');

            closeDd();

            if (dd.id === 'bank-month-dropdown' || dd.id === 'bank-year-dropdown') {
                updateLeaveBank();
            }

            if (dd.id === 'month-dropdown' || dd.id === 'year-dropdown') {
                // For history, usually we wait for 'Apply' button, but let's keep it consistent
            }

            if (dd.closest('#generated-dates-body')) {
                if (dd.classList.contains('leave-type-dropdown')) {
                    const row = dd.closest('tr');
                    const dayTypeDd = row.querySelector('.day-type-dropdown');
                    const dayTypeSpan = dayTypeDd.querySelector('.selected-value');
                    const dayTypeMenu = dayTypeDd.querySelector('.dropdown-menu');

                    if (floatItem.dataset.value === 'Short Leave') {
                        const opts = [`Morning (${shiftInfo.morning_range})`, `Evening (${shiftInfo.evening_range})` ];
                        dayTypeSpan.textContent = opts[0];
                        dayTypeMenu.innerHTML = opts.map(o => `<div class="dropdown-item ${o === opts[0] ? 'active' : ''}" data-value="${o}">${o}</div>`).join('');
                    } 
 else if (floatItem.dataset.value === 'Half Day Leave') {
                        const opts = ['First Half', 'Second Half'];
                        dayTypeSpan.textContent = opts[0];
                        dayTypeMenu.innerHTML = opts.map(o => `<div class="dropdown-item ${o === opts[0] ? 'active' : ''}" data-value="${o}">${o}</div>`).join('');
                    } else {
                        const opts = ['Full Day', 'First Half', 'Second Half'];
                        dayTypeSpan.textContent = opts[0];
                        dayTypeMenu.innerHTML = opts.map(o => `<div class="dropdown-item ${o === opts[0] ? 'active' : ''}" data-value="${o}">${o}</div>`).join('');
                    }
                }
                calculateDynamicDuration();
            }
        } else {
            closeDd();
        }
    });

    window.addEventListener('scroll', (e) => {
        // Don't close if the user is scrolling inside the floating menu itself
        if (floatingMenu.contains(e.target) || e.target === floatingMenu) return;
        closeDd();
    }, true);
    window.addEventListener('resize', closeDd);


    const calculateDynamicDuration = () => {
        let total = 0;
        let hasSickLeave = false;
        const rows = document.querySelectorAll('#generated-dates-body tr');
        rows.forEach(row => {
            const checkbox = row.querySelector('input[type="checkbox"]');
            if (checkbox && checkbox.checked) {
                const dropdowns = row.querySelectorAll('.custom-dropdown');
                if (dropdowns.length >= 2) {
                    const leaveType = dropdowns[0].querySelector('.selected-value').textContent.trim();
                    if (leaveType.toLowerCase().includes('sick')) hasSickLeave = true;

                    const selectedValue = dropdowns[1].querySelector('.selected-value').textContent.trim();
                    if (selectedValue === 'Full Day') {
                        total += 1;
                    } else if (selectedValue === 'First Half' || selectedValue === 'Second Half') {
                        total += 0.5;
                    } else if (selectedValue.includes('Morning') || selectedValue.includes('Evening')) {
                        total += 1.5 / 9; // 1.5 hours out of a 9-hour working day
                    }
                }
            }
        });

        const uploadSection = document.getElementById('sick-leave-upload-section');
        if (uploadSection) {
            uploadSection.style.display = hasSickLeave ? 'block' : 'none';
        }
        
        const totalMinutes = Math.round(total * 9 * 60);
        const days    = Math.floor(totalMinutes / (9 * 60));
        const hours   = Math.floor((totalMinutes % (9 * 60)) / 60);
        const minutes = totalMinutes % 60;

        const parts = [];
        if (days)    parts.push(`${days}d`);
        if (hours)   parts.push(`${hours}h`);
        if (minutes) parts.push(`${minutes}m`);

        const badge = document.querySelector('.duration-badge');
        badge.textContent   = parts.length ? parts.join(' ') : '0m';
        badge.dataset.raw   = total; // exact decimal-day value used for submission
    };

    // ─────────────────────────────────────────────
    // 5. Leave Bank & Balances
    // ─────────────────────────────────────────────
    const months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    
    window.updateLeaveBank = async () => {
        const monthDropdown = document.getElementById('bank-month-dropdown');
        const yearDropdown = document.getElementById('bank-year-dropdown');
        
        const month = monthDropdown ? monthDropdown.querySelector('.selected-value').textContent.trim() : months[new Date().getMonth()];
        const year = yearDropdown ? yearDropdown.querySelector('.selected-value').textContent.trim() : new Date().getFullYear();
        
        const monthIdx = months.indexOf(month);

        try {
            const resp = await fetch(`../api/get_leave_balances.php?year=${year}&month=${monthIdx}`);
            const res = await resp.json();
            console.log('Balance Update Received:', res);
            if (res.success) {
                const balances = res.data;
                const usage = res.this_month_usage;

                // Update the Balance/Stat cards
                const listItems = document.querySelectorAll('.balance-list li');
                listItems.forEach(li => {
                    const name = li.querySelector('.bl-name').textContent.trim();
                    const valDiv = li.querySelector('.bl-val');
                    
                    // Match by name similarity
                    const leave = balances.find(b => b.leave_type.toLowerCase().includes(name.toLowerCase())) 
                               || balances.find(b => name.toLowerCase().includes(b.leave_type.toLowerCase()));
                               
                    if (leave) {
                        const usedRaw = usage[leave.leave_type] || 0;
                        const usedFloat = parseFloat(usedRaw);
                        const used = Number.isInteger(usedFloat) ? usedFloat : usedFloat.toFixed(1);
                        
                        const limit = 2; // For Short/Casual
                        
                        let badgeClass = 'usage-badge';
                        if (used >= limit) badgeClass += ' limit-reached';
                        else if (used > 0) badgeClass += ' near-limit';

                        let usageHtml = '';
                        if (name.includes('Casual') || name.includes('Short')) {
                            usageHtml = `<div class="${badgeClass}">${used}/${limit} used in ${month}</div>`;
                        }

                        let html = '';
                        if (leave.is_locked) {
                            html = `<span style="color:#d32f2f; background:#fef2f2; padding:3px 8px; border-radius:12px; font-size:0.75rem; border:1px solid #fecaca; font-weight:500;">${leave.lockMessage}</span>`;
                        } else {
                            html = `${Math.floor(leave.remaining_balance)} days`;
                            if (name.includes('Short')) {
                                 const prog = (parseFloat(leave.remaining_balance) / 2) * 100;
                                 html = `${parseFloat(leave.remaining_balance)} <div class="mini-prog"><div class="mini-prog-fill" style="width:${prog}%"></div></div>`;
                            }
                        }

                        valDiv.innerHTML = html;
                        const infoDiv = li.querySelector('.bl-info');
                        // Remove any old badges first
                        const oldBadge = infoDiv.querySelector('.usage-badge');
                        if (oldBadge) oldBadge.remove();
                        if (usageHtml && !leave.is_locked) infoDiv.innerHTML += usageHtml;

                        // Update Top Stats too
                        if (name.includes('Casual')) {
                            const stat = document.getElementById('stat-casual');
                            if (stat) stat.textContent = Math.floor(leave.remaining_balance);
                        } else if (name.includes('Short')) {
                             const stat = document.getElementById('stat-short');
                             if (stat) stat.textContent = parseFloat(leave.remaining_balance);
                        } else if (name.includes('Compensation')) {
                             const stat = document.getElementById('stat-comp');
                             if (stat) stat.textContent = Math.floor(leave.remaining_balance);
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Balance fetch error:', e);
        }
    };
    // Initial fetch on load
    const setupInitialBank = () => {
        const months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
        const d = new Date();
        const mo = months[d.getMonth()];
        const yr = d.getFullYear();

        const bMo = document.getElementById('bank-month-dropdown');
        const bYr = document.getElementById('bank-year-dropdown');
        if (bMo) bMo.querySelector('.selected-value').textContent = mo;
        if (bYr) bYr.querySelector('.selected-value').textContent = yr;
        
        updateLeaveBank();
    };
    setupInitialBank();

    // ─────────────────────────────────────────────
    // 4. Generate Date Rows
    // ─────────────────────────────────────────────
    // Leave types will be loaded from the backend
    let leaveTypeMap = {}; // Cache for { name: id } mapping
    let leaveTypes = ['Loading...'];
    const dayTypes = ['Full Day', 'First Half', 'Second Half'];

    const fetchLeaveTypes = async () => {
        try {
            const res = await fetch('../api/fetch_leave_types.php');
            const data = await res.json();
            if (data.success && data.data.length > 0) {
                leaveTypes = data.data.map(type => {
                    leaveTypeMap[type.name] = type.id;
                    return {
                        name: type.name, 
                        disabled: type.disabled || false, 
                        lockMessage: type.lockMessage || ''
                    };
                });
                console.log('Leave types loaded:', leaveTypes);
            }
        } catch (err) {
            console.error('Error fetching leave types:', err);
            leaveTypes = ['Casual Leave', 'Short Leave', 'Sick Leave'];
        }
    };

    // Initial setup for leave types and approvers
    fetchLeaveTypes();

    const fetchApprovers = async () => {
        try {
            const res = await fetch('../api/fetch_approvers.php');
            const data = await res.json();
            if (data.success) {
                const select = document.getElementById('mrf_approver');
                if (!select) return;
                
                select.innerHTML = data.approvers.map(app => {
                    const isSelected = String(app.id) === String(data.assigned_id) ? 'selected' : '';
                    return `<option value="${app.id}" ${isSelected}>${app.name} — ${app.position || 'Manager'}</option>`;
                }).join('');

                if (data.approvers.length === 0) {
                    select.innerHTML = '<option value="">No managers found</option>';
                }
            } else {
                console.error('API Fail:', data.error);
            }
        } catch (err) {
            console.error('Approver fetch error:', err);
        }
    };

    fetchApprovers();

    const mkSelect = (opts, cls, def = null) => {
        const getVal = o => typeof o === 'string' ? o : o.name;
        const isDis = o => typeof o === 'object' && o.disabled;
        const getMsg = o => typeof o === 'object' ? (o.lockMessage || '') : '';
        
        // Find default or fallback to first valid option
        let validOpts = opts.filter(o => !isDis(o));
        if (validOpts.length === 0) validOpts = opts; 
        const selected = def && opts.find(o => getVal(o) === def && !isDis(o)) ? def : getVal(validOpts[0]);
        
        return `
            <div class="custom-dropdown ${cls}">
                <button class="dropdown-trigger" type="button" style="padding: 5px 8px; width: 100%; justify-content: space-between;">
                    <span class="selected-value" style="font-size: 0.79rem; margin-right: 8px;">${selected}</span>
                    <svg class="chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="dropdown-menu">
                    ${opts.map(o => {
                        const val = getVal(o);
                        if (isDis(o)) {
                            return '<div class="dropdown-item disabled" style="opacity:0.65; cursor:not-allowed; background:#f9fafb;" data-value="' + val + '">' +
                                val +
                                '<span style="display:block; font-size:0.65rem; color:#d32f2f; margin-top:2px;">' + getMsg(o) + '</span>' +
                            '</div>';
                        } else {
                            const actCls = (val === selected) ? 'active' : '';
                            return '<div class="dropdown-item ' + actCls + '" data-value="' + val + '">' + val + '</div>';
                        }
                    }).join('')}
                </div>
            </div>`;
    };

    document.querySelector('.js-generate-dates').addEventListener('click', () => {
        const from = document.getElementById('mrf_from_date').value;
        const to   = document.getElementById('mrf_to_date').value;

        if (!from || !to) { alert('Please select both From and To dates.'); return; }

        const start = new Date(from);
        const end   = new Date(to);
        if (start > end) { alert('From Date cannot be after To Date.'); return; }

        // List of 2026 holidays based on user input
        const holidays2026 = {
            '2026-01-01': 'New Year',
            '2026-01-26': 'Republic Day',
            '2026-02-15': 'Maha Shivaratri',
            '2026-03-04': 'Holi',
            '2026-03-26': 'Ram Navmi',
            '2026-08-15': 'Independence Day',
            '2026-08-28': 'Raksha Bandhan',
            '2026-09-04': 'Krishna Janmashtami',
            '2026-10-02': 'Gandhi Jayanti',
            '2026-10-20': 'Dussehra (Vijayadashami)',
            '2026-11-08': 'Diwali',
            '2026-11-09': 'Govardhan Puja',
            '2026-11-11': 'Bhai Dooj'
        };

        const userOffDays = (shiftInfo.weekly_offs || "Saturday,Sunday").split(',').map(d => d.trim().toLowerCase());

        let rows = '', count = 0;
        for (let dt = new Date(start); dt <= end; dt.setDate(dt.getDate() + 1)) {
            const dateStr  = dt.toISOString().split('T')[0];
            const dayBasicName  = dt.toLocaleDateString('en-US', { weekday: 'long' });
            const isWeeklyOff = userOffDays.includes(dayBasicName.toLowerCase());
            const holidayName = holidays2026[dateStr];
            
            const isOff = isWeeklyOff || holidayName;
            
            // Format holiday name neatly or fallback to basic day name
            let displayDay = holidayName ? 
                `<span style="color:var(--red);font-weight:600;">${dayBasicName} · ${holidayName}</span>` : 
                dayBasicName;

            // Priority: select "Compensate" if available, else first type
            const validLeaves = leaveTypes.filter(t => !(typeof t === 'object' && t.disabled));
            const getTName = t => typeof t === 'string' ? t : t.name;
            const defLeaveObj = validLeaves.find(t => getTName(t).toLowerCase().includes('compensate')) || validLeaves[0];
            const defLeave = getTName(defLeaveObj);

            rows += `
                <tr ${isOff ? 'style="opacity:.6; background:#fffafa;"' : ''}>
                    <td><input type="checkbox" ${isOff ? '' : 'checked'}></td>
                    <td style="font-variant-numeric:tabular-nums;font-size:.83rem;white-space:nowrap;">${dateStr}</td>
                    <td style="color:var(--text-secondary);font-size:.8rem;">${displayDay}</td>
                    <td>${mkSelect(leaveTypes, 'table-select leave-type-dropdown', defLeave)}</td>
                    <td>${mkSelect(dayTypes,   'table-select day-type-dropdown')}</td>
                </tr>`;
            count++;
        }

        document.getElementById('generated-dates-body').innerHTML = rows;
        calculateDynamicDuration();
    });

    // Recalculate when checkboxes in the table are toggled manually
    document.getElementById('generated-dates-body').addEventListener('change', e => {
        if (e.target.type === 'checkbox') {
            calculateDynamicDuration();
        }
    });

    // Select-all
    document.getElementById('select-all-dates').addEventListener('change', function () {
        document.querySelectorAll('#generated-dates-body input[type="checkbox"]')
            .forEach(cb => cb.checked = this.checked);
        calculateDynamicDuration();
    });

    // Cancel
    document.getElementById('cancel-btn').addEventListener('click', () => {
        document.getElementById('application-form').reset();
        document.getElementById('generated-dates-body').innerHTML =
            `<tr><td colspan="5" class="empty-state">Select a date range and click <strong>Generate</strong></td></tr>`;
        const badge = document.querySelector('.duration-badge');
        badge.textContent = '0m';
        badge.dataset.raw = 0;
    });

    // ─────────────────────────────────────────────
    // 5. Fetch & Render History
    // ─────────────────────────────────────────────
    const statusConfig = {
        'Approved':        { cls: 'badge-green', label: 'Approved' },
        'Pending':         { cls: 'badge-gray',  label: 'Pending' },
        'Rejected':        { cls: 'badge-red',   label: 'Rejected' },
        'No Action Taken': { cls: 'badge-amber', label: 'No Action' },
    };

    const renderBadge = status => {
        const s = statusConfig[status] || { cls: 'badge-gray', label: status };
        return `<span class="badge ${s.cls}">${s.label}</span>`;
    };

    const fetchHistory = async () => {
        try {
            const res = await fetch('../api/fetch_leave_history.php');
            const json = await res.json();
            
            if (!json.success || !Array.isArray(json.data)) {
                console.error('History fetch failed:', json.message || 'Unknown error');
                const tbody = document.getElementById('history-table-body');
                if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="empty-state">${json.message || 'Error loading history.'}</td></tr>`;
                return;
            }

            const allData = json.data;
            
            // Store globally for the view/edit modals to use
            window.allLeaveHistoryData = allData; // All data for this user

            const tbody = document.getElementById('history-table-body');
            tbody.innerHTML = '';

            // Get selected filters from the history card dropdowns
            const moEl = document.querySelector('#month-dropdown .selected-value');
            const yrEl = document.querySelector('#year-dropdown .selected-value');
            const monthVal = moEl ? moEl.textContent.trim() : 'March';
            const yearVal = yrEl ? yrEl.textContent.trim() : '2026';
            
            const monthsMap = { 'January':'01', 'February':'02', 'March':'03', 'April':'04', 'May':'05', 'June':'06', 'July':'07', 'August':'08', 'September':'09', 'October':'10', 'November':'11', 'December':'12' };
            const filterPrefix = `${yearVal}-${monthsMap[monthVal]}`;

            const data = allData.filter(item => item.date && item.date.startsWith(filterPrefix));

            const total    = allData.length;
            const approved = allData.filter(d => d.status === 'Approved').length;
            const pending  = allData.filter(d => d.status === 'Pending').length;

            const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

            if (!data || data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="empty-state">No leave history found for this period.</td></tr>`;
                return;
            }

            window.leaveHistoryData = data; // Store filtered data for modal access

            tbody.innerHTML = data.map((item, index) => {
                const isPending = item.status === 'Pending';
                return `
                <tr>
                    <td style="font-size:0.82rem; color:var(--text-primary); font-weight:500; white-space:nowrap; min-width:110px;">${item.date}</td>
                    <td><span class="badge badge-gray">${item.leaveType}</span></td>
                    <td style="font-weight:600; color:var(--text-primary);">${item.duration}</td>
                    <td>${renderBadge(item.status)}</td>
                    <td>${renderBadge(item.managerStatus)}</td>
                    <td><div class="reason-cell" title="${item.reason}">${item.reason}</div></td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button class="btn btn-ghost btn-icon" onclick="openViewModal(${index})" title="View Details">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                            ${isPending ? `
                                <button class="btn btn-ghost btn-icon" style="color:#d32f2f;" onclick="openEditModal(${index})" title="Edit Request">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                <button class="btn btn-ghost btn-icon" style="color:#666;" onclick="openDeleteModal(${index})" title="Cancel/Delete">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>`;
            }).join('');
        } catch (e) {
            console.error('History error:', e);
        }
    };

    fetchHistory();
    document.getElementById('load-history-btn').addEventListener('click', fetchHistory);

    // ─────────────────────────────────────────────
    // 5.1 File Upload Preview & Tracking
    // ─────────────────────────────────────────────
    let selectedFiles = [];
    const fileInput = document.getElementById('sick-leave-files');
    const previewContainer = document.getElementById('file-list-preview');

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const files = Array.from(fileInput.files);
            files.forEach(file => {
                if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                    selectedFiles.push(file);
                }
            });
            renderFilePreviews();
            fileInput.value = ''; // Reset to allow same file again if removed
        });
    }

    function renderFilePreviews() {
        if (!previewContainer) return;
        previewContainer.innerHTML = selectedFiles.map((file, idx) => `
            <div class="file-chip">
                <span title="${file.name}">${file.name}</span>
                <div class="remove-file" onclick="removeSelectedFile(${idx})">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </div>
            </div>
        `).join('');
    }

    window.removeSelectedFile = (idx) => {
        selectedFiles.splice(idx, 1);
        renderFilePreviews();
    };

    // ─────────────────────────────────────────────
    // 6. Form Submit
    // ─────────────────────────────────────────────
    document.getElementById('application-form').addEventListener('submit', async e => {
        e.preventDefault();

        const from     = document.getElementById('mrf_from_date').value;
        const to       = document.getElementById('mrf_to_date').value;
        const reason   = document.getElementById('reason').value;
        const approver = document.getElementById('mrf_approver').value;
        const badge    = document.querySelector('.duration-badge');
        const duration = parseFloat(badge.dataset.raw) || 0;

        if (!from || !to || !reason || !approver) { 
            alert('Please fill in all required fields (Dates, Reason, Approver).'); 
            return; 
        }

        const rows = document.querySelectorAll('#generated-dates-body tr');
        const dates = [];
        rows.forEach(row => {
            const chk = row.querySelector('input[type="checkbox"]');
            if (chk && chk.checked) {
                const dateStr = row.cells[1].textContent.trim();
                const typeName = row.querySelector('.leave-type-dropdown .selected-value').textContent.trim();
                const dayType = row.querySelector('.day-type-dropdown .selected-value').textContent.trim();
                dates.push({
                    date: dateStr,
                    type_name: typeName,
                    type_id: leaveTypeMap[typeName],
                    day_type: dayType
                });
            }
        });

        if (dates.length === 0) {
            alert('Please select at least one date from the breakdown table.');
            return;
        }

        // Sick Leave Mandatory File Check
        const requiresUpload = dates.some(d => d.type_name.toLowerCase().includes('sick'));
        if (requiresUpload && selectedFiles.length === 0) {
            alert('Please upload medical documents for your Sick Leave request.');
            return;
        }

        const btn = document.getElementById('submit-btn');
        btn.textContent = 'Processing...';
        btn.disabled = true;

        const funnyQuotes = [
            "Waking up your manager (hope they had coffee)...",
            "Shocking your manager with this request...",
            "Hiding your leave from the group chat...",
            "Polishing your excuses...",
            "Counting your remaining sanity (and leaves)...",
            "Briefing the team on why you're 'working from home'...",
            "Convincing the database that you deserve this break...",
            "Practicing your 'I am sick' voice...",
            "Checking if there's a holiday we missed..."
        ];

        let quoteIdx = 0;
        const loader = document.getElementById('submit-loader');
        const quoteEl = document.getElementById('loader-quote');
        quoteEl.textContent = funnyQuotes[0];
        loader.classList.add('active');
        
        const timer = setInterval(() => {
            quoteIdx = (quoteIdx + 1) % funnyQuotes.length;
            quoteEl.textContent = funnyQuotes[quoteIdx];
        }, 800);

        const minWait = new Promise(resolve => setTimeout(resolve, 3000));

        try {
            const formData = new FormData();
            formData.append('reason', reason);
            formData.append('approver_id', approver);
            formData.append('dates', JSON.stringify(dates));
            
            selectedFiles.forEach(file => {
                formData.append('sick_leave_files[]', file);
            });

            const resPromise = fetch('../api/save_leave_request.php', {
                method:  'POST',
                body:    formData // FormData automatically sets correct headers
            });

            const [res] = await Promise.all([resPromise, minWait]);
            const data = await res.json();

            clearInterval(timer);
            loader.classList.remove('active');

            if (data.success) {
                showResultModal('Success!', data.message, 'success');
                document.getElementById('application-form').reset();
                selectedFiles = [];
                renderFilePreviews();
                document.getElementById('generated-dates-body').innerHTML =
                    '<tr><td colspan="5" class="nlr-empty-state"><p>All set! Application submitted.</p></td></tr>';
                const dBadge = document.querySelector('.duration-badge');
                dBadge.textContent = '0m';
                dBadge.dataset.raw = 0;
                fetchHistory();
                updateLeaveBank();
            } else {
                showResultModal('Submission Failed', data.message, 'error');
            }
        } catch (err) {
            if (timer) clearInterval(timer);
            loader.classList.remove('active');
            console.error(err);
            showResultModal('Error', 'An unexpected error occurred. Please try again.', 'error');
        } finally {
            btn.textContent = 'Submit Application →';
            btn.disabled = false;
        }
    });

    function showResultModal(title, desc, type) {
        const modal = document.getElementById('result-modal');
        const iconWrap = document.getElementById('result-icon');
        document.getElementById('result-title').textContent = title;
        document.getElementById('result-desc').textContent = desc;
        
        iconWrap.className = 'nlr-modal-icon ' + type;
        iconWrap.innerHTML = type === 'success' 
            ? '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
            : '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        
        modal.classList.add('active');
    }

    window.closeResultModal = () => {
        document.getElementById('result-modal').classList.remove('active');
    };

    // ─────────────────────────────────────────────
    // 7. Modals
    // ─────────────────────────────────────────────
    window.closeModal = (id) => {
        document.getElementById(id).classList.remove('active');
    };

    window.openViewModal = (index) => {
        const item = window.leaveHistoryData[index];
        const content = `
            <div class="view-modal-grid">
                <div class="view-item">
                    <div class="view-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        Date
                    </div>
                    <div class="view-value">${item.date}</div>
                </div>
                <div class="view-item">
                    <div class="view-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Duration
                    </div>
                    <div class="view-value" style="font-weight:700; color:var(--nlr-accent);">${item.duration}</div>
                </div>
                <div class="view-item">
                    <div class="view-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        Leave Type
                    </div>
                    <div class="view-value"><span class="badge badge-gray">${item.leaveType}</span></div>
                </div>
                <div class="view-item">
                    <div class="view-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        Status
                    </div>
                    <div class="view-value">${renderBadge(item.status)}</div>
                </div>
                <div class="view-item full">
                    <div class="view-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Manager Status
                    </div>
                    <div class="view-value">${renderBadge(item.managerStatus)}</div>
                </div>
                <div class="view-item full">
                    <div class="view-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        Reason
                    </div>
                    <div class="view-value reason-block">${item.reason}</div>
                </div>
                ${item.attachments && item.attachments.length > 0 ? `
                <div class="view-item full">
                    <div class="view-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                        Attachments
                    </div>
                    <div class="view-attachments" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                        ${item.attachments.map(att => {
                            const isImg = att.type.includes('image');
                            const icon = isImg ? '🖼️' : '📄';
                            const path = '../../' + att.path; // Relative to studio_users/leave_pages/
                            return `
                            <a href="${path}" target="_blank" class="att-link" style="display:flex; align-items:center; gap:8px; padding:6px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; text-decoration:none; color:#1e293b; font-size:0.78rem; transition:all 0.15s; font-weight:500;">
                                <span>${icon}</span>
                                <span style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${att.name}</span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="color:#94a3b8;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            </a>`;
                        }).join('')}
                    </div>
                </div>` : ''}
            </div>
        `;
        document.getElementById('view-modal-content').innerHTML = content;
        document.getElementById('view-modal').classList.add('active');
    };

    let editingIndex = null;
    window.openEditModal = (index) => {
        editingIndex = index;
        const item = window.leaveHistoryData[index];
        const reasonInput = document.getElementById('edit_reason');
        if (reasonInput) reasonInput.value = item.reason || '';
        
        // Populate dates for reference (read-only)
        let fromDate = '', toDate = '';
        if(item.date) {
            const parts = item.date.split(' to ');
            fromDate = parts[0] || '';
            toDate = parts[1] || parts[0] || '';
        }
        const fromIn = document.getElementById('edit_from_date');
        const toIn = document.getElementById('edit_to_date');
        if (fromIn) { fromIn.value = fromDate; fromIn.setAttribute('readonly', true); }
        if (toIn) { toIn.value = toDate; toIn.setAttribute('readonly', true); }
        
        document.getElementById('edit-modal').classList.add('active');
    };

    window.saveEditLeave = async () => {
        if(editingIndex === null) return;
        const item = window.leaveHistoryData[editingIndex];
        const reason = document.getElementById('edit_reason').value;

        try {
             const res = await fetch('../api/update_leave_request.php', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json' }, // Added headers for consistency
                 body: JSON.stringify({ id: item.id, reason: reason })
             });
             const data = await res.json();
             if (data.success) {
                 showResultModal('Updated!', data.message, 'success');
                 closeModal('edit-modal');
                 fetchHistory();
             } else {
                 showResultModal('Update Failed', data.message, 'error');
             }
        } catch (e) {
             console.error(e); // Added console.error for debugging
             showResultModal('Error', 'Connection failed', 'error');
        }
    };

    let deletingIndex = null;
    window.openDeleteModal = (index) => {
        deletingIndex = index;
        document.getElementById('delete-modal').classList.add('active');
    };

    window.confirmDeleteLeave = async () => {
        if(deletingIndex === null) return;
        
        try {
            const item = window.leaveHistoryData[deletingIndex];
            const res = await fetch('../api/delete_leave_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: item.id })
            });
            const data = await res.json();
            if (data.success) {
                showResultModal('Deleted!', data.message, 'success');
                fetchHistory();
                updateLeaveBank();
                closeModal('delete-modal');
            } else {
                showResultModal('Deletion Failed', data.message, 'error');
            }
        } catch(e) {
            console.error(e);
            showResultModal('Error', 'Connection failed', 'error');
        } finally {
            deletingIndex = null;
        }
    };

    // ─────────────────────────────────────────────
    // 6. Holiday Overview - Wheel Scroll (Desktop)
    // ─────────────────────────────────────────────
    const holidaysMarquee = document.querySelector('.holidays-marquee');
    if (holidaysMarquee) {
        holidaysMarquee.addEventListener('wheel', (e) => {
            if (e.deltaY !== 0) {
                // Only intercept if we're on a device that doesn't natively do mixed-axis scrolling
                // (Most mouse wheels only provide deltaY).
                e.preventDefault();
                holidaysMarquee.scrollLeft += (e.deltaY * 0.8); // Adjust speed factor if needed
            }
        }, { passive: false });
    }

});
