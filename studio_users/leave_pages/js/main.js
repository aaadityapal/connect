document.addEventListener("DOMContentLoaded", () => {

    // ─────────────────────────────────────────────
    // 0. Set Current Month/Year for Leave History
    // ─────────────────────────────────────────────
    const allMonths = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    const currentMonth = allMonths[new Date().getMonth()];
    const currentYear = new Date().getFullYear().toString();

    // Set Leave History dropdowns to current month/year
    const historyMonthDropdown = document.getElementById('month-dropdown');
    const historyYearDropdown = document.getElementById('year-dropdown');
    
    if (historyMonthDropdown) {
        // Update displayed value
        historyMonthDropdown.querySelector('.selected-value').textContent = currentMonth;
        // Update active class in dropdown menu
        historyMonthDropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.value === currentMonth) {
                item.classList.add('active');
            }
        });
    }
    
    if (historyYearDropdown) {
        // Update displayed value
        historyYearDropdown.querySelector('.selected-value').textContent = currentYear;
        // Update active class in dropdown menu
        historyYearDropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.value === currentYear) {
                item.classList.add('active');
            }
        });
    }

    // Set Leave Bank dropdowns to current month/year
    const bankMonthDropdown = document.getElementById('bank-month-dropdown');
    const bankYearDropdown = document.getElementById('bank-year-dropdown');
    
    if (bankMonthDropdown) {
        bankMonthDropdown.querySelector('.selected-value').textContent = currentMonth;
        bankMonthDropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.value === currentMonth) {
                item.classList.add('active');
            }
        });
    }
    
    if (bankYearDropdown) {
        bankYearDropdown.querySelector('.selected-value').textContent = currentYear;
        bankYearDropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.value === currentYear) {
                item.classList.add('active');
            }
        });
    }

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
                    const selectedLeaveType = floatItem.dataset.value;

                    // Get valid day types based on leave type and balance
                    const validDayTypes = getValidDayTypes(selectedLeaveType);
                    const opts = validDayTypes.options;
                    const helpText = validDayTypes.helpText;

                    dayTypeSpan.textContent = opts[0];
                    dayTypeMenu.innerHTML = opts.map(o => `<div class="dropdown-item ${o === opts[0] ? 'active' : ''}" data-value="${o}">${o}</div>`).join('');
                    
                    // Add help text if there's a balance restriction
                    if (helpText) {
                        let helpEl = row.querySelector('.day-type-help');
                        if (!helpEl) {
                            helpEl = document.createElement('div');
                            helpEl.className = 'day-type-help';
                            dayTypeDd.parentElement.appendChild(helpEl);
                        }
                        helpEl.textContent = helpText;
                        helpEl.style.fontSize = '0.85em';
                        helpEl.style.color = '#e67e22';
                        helpEl.style.marginTop = '2px';
                    } else {
                        const existingHelp = row.querySelector('.day-type-help');
                        if (existingHelp) existingHelp.remove();
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

    // ─────────────────────────────────────────────
    // Helper: Get valid day types based on leave type and balance
    // ─────────────────────────────────────────────
    const getValidDayTypes = (leaveTypeName) => {
        if (leaveTypeName === 'Short Leave') {
            return {
                options: [`Morning (${shiftInfo.morning_range})`, `Evening (${shiftInfo.evening_range})`],
                helpText: null
            };
        } 
        else if (leaveTypeName === 'Half Day Leave') {
            return {
                options: ['First Half', 'Second Half'],
                helpText: null
            };
        } 
        else {
            // For other leave types, check if they have fractional balance
            const category = getLeaveCategory(leaveTypeName);
            let balance = 0;
            
            if (category === 'special') {
                // Special types (Sick, Paternity, etc.) default to 1 day
                return {
                    options: ['Full Day', 'First Half', 'Second Half'],
                    helpText: null
                };
            } else if (category === 'flexible') {
                // Check current balance for flexible types
                balance = currentLeaveBalances[leaveTypeName] || 0;
                
                if (balance === 0) {
                    return {
                        options: ['Full Day', 'First Half', 'Second Half'],
                        helpText: null
                    };
                } else if (balance < 1) {
                    // Fractional balance (0.5, etc) - only half days allowed
                    return {
                        options: ['First Half', 'Second Half'],
                        helpText: `Only ${balance} day available - select First/Second Half`
                    };
                } else if (balance % 1 !== 0) {
                    // Has fractional part (1.5, 2.5, etc)
                    return {
                        options: ['Full Day', 'First Half', 'Second Half'],
                        helpText: `Balance: ${balance} days (${Math.floor(balance)} full + ${(balance % 1).toFixed(1)} half)`
                    };
                } else {
                    // Full day balance
                    return {
                        options: ['Full Day', 'First Half', 'Second Half'],
                        helpText: null
                    };
                }
            }
            
            return {
                options: ['Full Day', 'First Half', 'Second Half'],
                helpText: null
            };
        }
    };

    // ─────────────────────────────────────────────
    // Helper: Validate day type matches available balance
    // ─────────────────────────────────────────────
    const validateDayTypeForBalance = (leaveTypeName, dayType) => {
        if (leaveTypeName === 'Short Leave' || leaveTypeName === 'Half Day Leave') {
            // These types handle their own day types
            return { valid: true, reason: null };
        }
        
        const category = getLeaveCategory(leaveTypeName);
        if (category !== 'flexible') {
            return { valid: true, reason: null };
        }
        
        const balance = currentLeaveBalances[leaveTypeName] || 0;
        
        if (dayType === 'Full Day' && balance < 1) {
            return { 
                valid: false, 
                reason: `You only have ${balance} day(s) of ${leaveTypeName} available. Please select "First Half" or "Second Half" instead.` 
            };
        }
        
        return { valid: true, reason: null };
    };

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
            if (res.success) {
                const balances = res.data;
                const usage = res.this_month_usage;


                // ─── UPDATE TOP STAT CARDS DIRECTLY FROM API ───
                // Set defaults first
                const statShort = document.getElementById('stat-short');
                const statComp = document.getElementById('stat-comp');
                const statCasual = document.getElementById('stat-casual');
                const statSick = document.getElementById('stat-sick');
                
                // Default values if not found in API
                let shortLeaveFound = false;
                let compLeaveFound = false;
                let casualLeaveFound = false;

                balances.forEach(leave => {
                    const leaveName = leave.leave_type.toLowerCase();
                    
                    
                    // Update Short Leave stat
                    if (leaveName.includes('short')) {
                        if (statShort) statShort.textContent = parseFloat(leave.remaining_balance);
                        shortLeaveFound = true;
                    }
                    
                    // Update Compensation Leave stat
                    if (leaveName.includes('compensation') || leaveName.includes('comp off') || leaveName.includes('compensate')) {
                        if (statComp) statComp.textContent = Number(leave.remaining_balance);
                        compLeaveFound = true;
                    }
                    
                    // Update Casual Leave stat
                    if (leaveName.includes('casual')) {
                        if (statCasual) statCasual.textContent = Number(leave.remaining_balance);
                        casualLeaveFound = true;
                        
                        const usedRaw = usage[leave.leave_type] || 0;
                        const used = Number.isInteger(parseFloat(usedRaw)) ? parseInt(usedRaw) : parseFloat(usedRaw).toFixed(1);
                        const statCard = statCasual?.closest('.stat-card');
                        if (statCard) {
                            const tag = statCard.querySelector('.stat-tag');
                            if (tag) tag.textContent = `${used}/2 used this month`;
                        }
                    }
                    
                    // Update Sick Leave stat
                    if (leaveName.includes('sick')) {
                        if (statSick) statSick.textContent = Number(leave.remaining_balance);
                    }
                });

                // If Short Leave not found in API response, check if it should default to 2
                if (!shortLeaveFound && statShort) {
                    statShort.textContent = '2';
                }

                // If Compensation Leave not found, default to 0
                if (!compLeaveFound && statComp) {
                    statComp.textContent = '0';
                }

                // If Casual Leave not found, try to calculate
                if (!casualLeaveFound && statCasual) {
                }

                // ─── UPDATE LEAVE BANK LIST ───
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
                            html = `<div style="display:flex; flex-direction:column; align-items:flex-end;">
                                        <span style="font-weight:600; color:#333;">${Number(leave.remaining_balance)} days</span>
                                        <span style="color:#d32f2f; background:#fef2f2; padding:3px 8px; border-radius:12px; font-size:0.7rem; border:1px solid #fecaca; font-weight:500; margin-top:4px;">${leave.lockMessage}</span>
                                    </div>`;
                        } else {
                            html = `${Number(leave.remaining_balance)} days`;
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
                    const isAssigned = String(app.id) === String(data.assigned_id);
                    const isSelected = isAssigned ? 'selected' : '';
                    const isDisabled = !isAssigned ? 'disabled' : '';
                    const roleName = app.role ? app.role.charAt(0).toUpperCase() + app.role.slice(1) : 'Manager';
                    return `<option value="${app.id}" ${isSelected} ${isDisabled}>${app.name} — ${roleName}</option>`;
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

    document.querySelector('.js-generate-dates').addEventListener('click', async () => {
        const from = document.getElementById('mrf_from_date').value;
        const to   = document.getElementById('mrf_to_date').value;

        if (!from || !to) { alert('Please select both From and To dates.'); return; }

        // Refresh balances before generating dates
        await fetchCurrentBalances();

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

        // Collect all dates with their properties
        const allDates = [];
        for (let dt = new Date(start); dt <= end; dt.setDate(dt.getDate() + 1)) {
            const dateStr  = dt.toISOString().split('T')[0];
            const dayBasicName  = dt.toLocaleDateString('en-US', { weekday: 'long' });
            const isWeeklyOff = userOffDays.includes(dayBasicName.toLowerCase());
            const holidayName = holidays2026[dateStr];
            const isOff = isWeeklyOff || (holidayName ? true : false); // Ensure boolean
            
            allDates.push({ dateStr, dayBasicName, holidayName, isOff });
        }

        // Get working dates only for distribution
        const workingDates = allDates.filter(d => !d.isOff);

        // Distribute leave types and day types based on available balance (including fractions)
        const getTName = t => typeof t === 'string' ? t : t.name;
        const validLeaves = leaveTypes.filter(t => !(typeof t === 'object' && t.disabled));
        
        // Track remaining balance for distribution (allow fractions)
        let remainingBalance = { ...currentLeaveBalances };
        const leaveDistribution = []; // Array of { dateStr, leaveType, dayType }
        
        // Assign leave types to each WORKING date based on priority, ONE row per day
        workingDates.forEach((dateInfo, dateIdx) => {
            const dateStr = dateInfo.dateStr;
            let selectedLeaveType = null;
            let dayType = 'Full Day';
            
            // Priority 1: Compensation Leave
            const compLeave = validLeaves.find(t => getTName(t).toLowerCase().includes('compensate'));
            const compName = compLeave ? getTName(compLeave) : null;
            const compBalance = compName ? (remainingBalance[compName] || 0) : 0;
            
            if (compLeave && compBalance > 0) {
                selectedLeaveType = compName;
                if (compBalance < 1) dayType = 'First Half';
                remainingBalance[compName] = Math.max(0, compBalance - (dayType === 'Full Day' ? 1 : 0.5));
            }
            
            // Priority 2: Casual Leave (if Comp not available)
            if (!selectedLeaveType) {
                const casualLeave = validLeaves.find(t => getTName(t).toLowerCase().includes('casual'));
                const casualName = casualLeave ? getTName(casualLeave) : null;
                const casualBalance = casualName ? (remainingBalance[casualName] || 0) : 0;
                
                if (casualLeave && casualBalance > 0) {
                    selectedLeaveType = casualName;
                    if (casualBalance < 1) dayType = 'First Half';
                    remainingBalance[casualName] = Math.max(0, casualBalance - (dayType === 'Full Day' ? 1 : 0.5));
                }
            }
            
            // Priority 3: Fallback Leave (Unpaid)
            if (!selectedLeaveType) {
                let fallbackLeave = validLeaves.find(t => getTName(t).toLowerCase().includes('unpaid'));
                if (!fallbackLeave) {
                    fallbackLeave = validLeaves.find(t => !getTName(t).toLowerCase().includes('compensation') && !getTName(t).toLowerCase().includes('casual'));
                }
                if (!fallbackLeave) fallbackLeave = validLeaves[0];
                
                selectedLeaveType = getTName(fallbackLeave);
            }
            
            leaveDistribution.push({ dateStr, leaveType: selectedLeaveType, dayType });
        });

        // Render rows based on distribution
        let rows = '';
        allDates.forEach(dateInfo => {
            const { dateStr, dayBasicName, holidayName, isOff } = dateInfo;
            
            // Format holiday name neatly or fallback to basic day name
            let displayDay = holidayName ? 
                `<span style="color:var(--red);font-weight:600;">${dayBasicName} · ${holidayName}</span>` : 
                dayBasicName;

            // Get all distribution entries for this date
            const distributionsForDate = leaveDistribution.filter(d => d.dateStr === dateStr);
            
            if (distributionsForDate.length > 0) {
                // Has distribution entries - render them
                distributionsForDate.forEach((dist, idx) => {
                    // Get valid day types based on leave type balance
                    const validDayTypesObj = getValidDayTypes(dist.leaveType);
                    const validDayOptions = validDayTypesObj.options;
                    
                    rows += `
                        <tr ${isOff ? 'style="opacity:.6; background:#fffafa;"' : ''}>
                            <td><input type="checkbox" ${isOff ? '' : 'checked'}></td>
                            <td style="font-variant-numeric:tabular-nums;font-size:.83rem;white-space:nowrap;">${idx === 0 ? dateStr : ''}</td>
                            <td style="color:var(--text-secondary);font-size:.8rem;">${idx === 0 ? displayDay : ''}</td>
                            <td>${mkSelect(leaveTypes, 'table-select leave-type-dropdown', dist.leaveType)}</td>
                            <td>${mkSelect(validDayOptions,   'table-select day-type-dropdown', dist.dayType)}</td>
                        </tr>`;
                });
            } else if (isOff) {
                // OFF day - show it but grayed out
                rows += `
                    <tr style="opacity:.6; background:#fffafa;">
                        <td><input type="checkbox"></td>
                        <td style="font-variant-numeric:tabular-nums;font-size:.83rem;white-space:nowrap;">${dateStr}</td>
                        <td style="color:var(--text-secondary);font-size:.8rem;">${displayDay}</td>
                        <td>${mkSelect(leaveTypes, 'table-select leave-type-dropdown', getTName(validLeaves[0]))}</td>
                        <td>${mkSelect(dayTypes,   'table-select day-type-dropdown')}</td>
                    </tr>`;
            }
        });

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
            
            // Default to current month/year if not set
            const allMonths = ["January","February","March","April","May","June","July","August","September","October","November","December"];
            const currentMonth = allMonths[new Date().getMonth()];
            const currentYear = new Date().getFullYear().toString();
            
            const monthVal = moEl ? moEl.textContent.trim() : currentMonth;
            const yearVal = yrEl ? yrEl.textContent.trim() : currentYear;
            
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
    // ─────────────────────────────────────────────
    // 5B. LEAVE BALANCE VALIDATION & WARNING SYSTEM
    // ─────────────────────────────────────────────
    let currentLeaveBalances = {}; // Will store { 'Casual Leave': 12, 'Compensation Leave': 5, ... }
    let pendingLeaveUsage = {}; // Will store pending leave usage by type

    // Function to fetch current leave balances
    const fetchCurrentBalances = async () => {
        try {
            const months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
            const d = new Date();
            const mo = months[d.getMonth()];
            const yr = d.getFullYear();
            const monthIdx = d.getMonth();

            const resp = await fetch(`../api/get_leave_balances.php?year=${yr}&month=${monthIdx}`);
            const res = await resp.json();
            
            if (res.success && res.data) {
                res.data.forEach(leave => {
                    currentLeaveBalances[leave.leave_type] = parseFloat(leave.remaining_balance) || 0;
                    if (leave.is_locked) {
                        if (!window.leaveLocks) window.leaveLocks = {};
                        window.leaveLocks[leave.leave_type] = leave.lockMessage;
                    }
                });
            }
        } catch (e) {
            console.error('Error fetching balances:', e);
        }
    };

    // Fetch balances on page load
    fetchCurrentBalances();

    // Function to identify leave category
    const getLeaveCategory = (leaveType) => {
        const type = leaveType.toLowerCase();
        if (type.includes('short')) return 'short';
        if (type.includes('sick')) return 'sick';
        if (type.includes('paternity')) return 'paternity';
        if (type.includes('maternity')) return 'maternity';
        if (type.includes('compensation') || type.includes('comp off')) return 'compensation';
        if (type.includes('casual')) return 'casual';
        if (type.includes('unpaid')) return 'unpaid';
        return 'other';
    };

    // Function to validate leave balance with direct rules and precise decimal calculations
    const validateLeaveBalance = (selectedDates) => {
        const warnings = [];
        const errors = [];
        
        // Separate dates by leave type
        const leavesByType = {};
        selectedDates.forEach(date => {
            const type = date.type_name;
            if (!leavesByType[type]) leavesByType[type] = [];
            leavesByType[type].push(date);
        });

        // Process each leave type independently
        Object.entries(leavesByType).forEach(([leaveType, dates]) => {
            const category = getLeaveCategory(leaveType);
            
            // Calculate accurate total days based on the dropdown fraction
            let totalDays = 0;
            dates.forEach(d => {
                const dt = d.day_type || 'Full Day';
                if (dt === 'Full Day') totalDays += 1;
                else if (dt === 'First Half' || dt === 'Second Half') totalDays += 0.5;
                else if (dt.includes('Morning') || dt.includes('Evening')) totalDays += 1; // Counted as 1 use for Short leaves natively
                else totalDays += 1; 
            });

            const balance = currentLeaveBalances[leaveType] || 0;

            // Strict Validation for all Paid Leaves (Includes Casual & Compensate)
            if (category !== 'unpaid') {
                if (category === 'short') {
                    if (balance < dates.length) {
                        errors.push({
                            type: leaveType,
                            message: `<strong>Insufficient ${leaveType} balance.</strong><br>This cannot happen because you only have ${balance} uses remaining, but you are attempting to request ${dates.length} uses.`
                        });
                    }
                } else {
                    if (balance < totalDays) {
                        errors.push({
                            type: leaveType,
                            message: `<strong>Insufficient ${leaveType} balance.</strong><br>This cannot happen because only ${Number(balance)} days remain in your leave bank, but you are attempting to apply for ${Number(totalDays)} days.<br><br>Please reduce your selection or switch the out-of-balance days to Unpaid Leave.`
                        });
                    }
                }
            } else {
                // Friendly warning for explicit Unpaid Leave picks
                warnings.push({
                    type: leaveType,
                    message: `⚠️ Please Review:\n\nYou are explicitly requesting ${Number(totalDays)} day(s) of Unpaid Leave.\n\nClick "Confirm & Submit" to proceed anyway.`
                });
            }
        });

        // --- ENFORCE COMPENSATION LEAVE PRIORITY ---
        let hasCasual = false;
        let casualLocked = false;
        let compensateRequested = 0;
        let compensateBalance = 0;
        let compensateName = 'Compensate Leave';

        Object.entries(leavesByType).forEach(([leaveType, dates]) => {
            const category = getLeaveCategory(leaveType);
            let tDays = 0;
            dates.forEach(d => {
                const dt = d.day_type || 'Full Day';
                if (dt === 'Full Day') tDays += 1;
                else if (dt === 'First Half' || dt === 'Second Half') tDays += 0.5;
                else if (dt.includes('Morning') || dt.includes('Evening')) tDays += 1;
                else tDays += 1; 
            });

            if (category === 'casual') {
                hasCasual = true;
                if (window.leaveLocks && window.leaveLocks[leaveType]) {
                    casualLocked = true;
                }
            }
            if (category === 'compensation' || category === 'compensate') {
                compensateRequested = tDays;
            }
        });

        // Search for dynamic balance of compensate
        Object.entries(currentLeaveBalances).forEach(([k, v]) => {
            if (k.toLowerCase().includes('compensate') || k.toLowerCase().includes('comp off') || k.toLowerCase().includes('compensation')) {
                compensateBalance = Number(v);
                compensateName = k;
            }
        });

        if (hasCasual && compensateBalance > 0 && !casualLocked) {
            if (compensateRequested < compensateBalance) {
                const unspent = compensateBalance - compensateRequested;
                errors.push({
                    type: 'Policy',
                    message: `<strong>Compensation Priority Rule.</strong><br>You are attempting to use Casual Leave while you still have ${Number(unspent)} ${compensateName}(s) unassigned.<br><br>Company policy requires you to exhaust your banked extra hours before you are allowed to use standard paid Casual Leaves.`
                });
            }
        }

        // --- ENFORCE PROBATION / MATERNITY LEAVE LOCKS LOCALLY ---
        Object.entries(leavesByType).forEach(([leaveType, dates]) => {
            if (window.leaveLocks && window.leaveLocks[leaveType]) {
                const lmsg = window.leaveLocks[leaveType];
                errors.push({
                    type: 'Policy',
                    message: `<strong>${leaveType} on Probation.</strong><br>You cannot use this leave right now. ${lmsg}!<br><br>Don't worry, your leaves are safely collecting and saving in your bank under your name right now.`
                });
            }
        });

        return { warnings, errors };
    };

    // Professional Warning Modal
    let warningModalInstance = null;
    
    const createProfessionalWarningModal = () => {
        const backdrop = document.createElement('div');
        backdrop.id = 'warning-backdrop';
        backdrop.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            z-index: 99998;
            animation: fadeIn 0.2s ease;
        `;
        document.body.appendChild(backdrop);

        const modal = document.createElement('div');
        modal.id = 'warning-modal-pro';
        modal.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 480px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            z-index: 99999;
            display: none;
            flex-direction: column;
            animation: slideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        `;
        modal.innerHTML = `
            <div style="padding: 32px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                    <div style="width: 48px; height: 48px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3.05h16.94a2 2 0 0 0 1.71-3.05L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <h2 style="margin: 0; font-size: 1.3rem; font-weight: 700; color: #1f2937;">Leave Balance Warning</h2>
                </div>
                <p id="warning-message-pro" style="color: #6b7280; font-size: 0.95rem; line-height: 1.6; margin: 20px 0; white-space: pre-wrap;"></p>
                <p style="color: #9ca3af; font-size: 0.85rem; margin: 16px 0 0 0;">Click OK to proceed with submission, or Cancel to go back.</p>
            </div>
            <div style="display: flex; gap: 12px; padding: 20px 32px; border-top: 1px solid #e5e7eb; background: #f9fafb; border-radius: 0 0 16px 16px;">
                <button id="warning-cancel-pro" style="flex: 1; padding: 12px 20px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.95rem; transition: all 0.2s;">Cancel</button>
                <button id="warning-ok-pro" style="flex: 1; padding: 12px 20px; border: none; background: #d97706; color: white; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.95rem; transition: all 0.2s;">Confirm & Submit</button>
            </div>
        `;
        document.body.appendChild(modal);

        // Add hover effects
        const okBtn = modal.querySelector('#warning-ok-pro');
        const cancelBtn = modal.querySelector('#warning-cancel-pro');
        
        okBtn.addEventListener('mouseover', () => okBtn.style.background = '#c2620a');
        okBtn.addEventListener('mouseout', () => okBtn.style.background = '#d97706');
        
        cancelBtn.addEventListener('mouseover', () => cancelBtn.style.background = '#f3f4f6');
        cancelBtn.addEventListener('mouseout', () => cancelBtn.style.background = 'white');

        return { modal, backdrop };
    };

    const showProfessionalWarningModal = (message) => {
        if (!warningModalInstance) {
            warningModalInstance = createProfessionalWarningModal();
        }
        
        const { modal, backdrop } = warningModalInstance;
        document.getElementById('warning-message-pro').textContent = message;
        
        modal.style.display = 'flex';
        backdrop.style.display = 'block';
        
    };

    const closeProfessionalWarningModal = () => {
        if (warningModalInstance) {
            warningModalInstance.modal.style.display = 'none';
            warningModalInstance.backdrop.style.display = 'none';
        }
    };

    // Show validation warning using professional modal
    let warningModalPromise = null;
    
    const showValidationWarning = (validation) => {
        return new Promise((resolve) => {
            const { warnings, errors } = validation;

            if (errors.length > 0) {
                let errorMsg = '';
                errors.forEach(err => {
                    errorMsg += `<div style="text-align:left; background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; padding:12px 16px; border-radius:6px; margin-bottom:12px; color:#991b1b; font-size:0.9rem; line-height:1.5;">${err.message}</div>`;
                });
                showResultModal('Cannot Submit', errorMsg, 'error');
                resolve(false);
                return;
            }

            if (warnings.length > 0) {
                let warningMsg = '⚠️ WARNING - PLEASE REVIEW:\n\n';
                warnings.forEach(warn => {
                    warningMsg += `• ${warn.message}\n`;
                });
                
                showProfessionalWarningModal(warningMsg);
                
                // Setup button handlers
                if (warningModalInstance) {
                    const { modal } = warningModalInstance;
                    const okBtn = modal.querySelector('#warning-ok-pro');
                    const cancelBtn = modal.querySelector('#warning-cancel-pro');
                    
                    // Remove old listeners
                    okBtn.replaceWith(okBtn.cloneNode(true));
                    cancelBtn.replaceWith(cancelBtn.cloneNode(true));
                    
                    const newOkBtn = modal.querySelector('#warning-ok-pro');
                    const newCancelBtn = modal.querySelector('#warning-cancel-pro');
                    
                    newOkBtn.addEventListener('click', () => {
                        closeProfessionalWarningModal();
                        resolve(true);
                    });
                    
                    newCancelBtn.addEventListener('click', () => {
                        closeProfessionalWarningModal();
                        resolve(false);
                    });
                }
                return;
            }

            resolve(true); // No issues, proceed
        });
    };

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
    `;
    document.head.appendChild(style);

    // Warning modal
    const createWarningModal = () => {
        // Always remove old modal if it exists
        let oldModal = document.getElementById('warning-modal');
        if (oldModal) oldModal.remove();

        const modal = document.createElement('div');
        modal.id = 'warning-modal';
        modal.style.cssText = `
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0;
            background: rgba(0,0,0,0.5); 
            display: none; 
            flex-direction: column;
            align-items: center; 
            justify-content: center;
            z-index: 99999 !important;
        `;
        modal.innerHTML = `
            <div class="nlr-result-modal" style="max-width: 450px; background: white; border-radius: 12px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <div class="nlr-modal-icon warning" style="width: 48px; height: 48px; margin: 0 auto 16px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3.05h16.94a2 2 0 0 0 1.71-3.05L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h3 class="nlr-modal-title" style="text-align: center; color: #d97706; margin-bottom: 12px;">Leave Balance Warning</h3>
                <p class="nlr-modal-desc" id="warning-message" style="white-space: pre-wrap; line-height: 1.6; color: #6b7280; font-size: 0.9rem; margin-bottom: 24px; text-align: left;"></p>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="nlr-modal-btn warning-cancel-btn" style="background: #e5e7eb; color: #374151; flex: 1; padding: 14px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem;">Cancel</button>
                    <button type="button" class="nlr-modal-btn warning-confirm-btn" style="background: #d97706; color: white; flex: 1; padding: 14px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem;">Confirm & Submit</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Attach event listeners with proper context
        const confirmBtn = modal.querySelector('.warning-confirm-btn');
        const cancelBtn = modal.querySelector('.warning-cancel-btn');
        
        confirmBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            window.confirmWarningSubmit();
        }, false);
        
        cancelBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            window.closeWarningModal();
        }, false);
        
        return modal;
    };

    const showWarningModal = (message) => {
        const modal = createWarningModal();
        document.getElementById('warning-message').textContent = message;
        modal.style.display = 'flex';
    };

    window.closeWarningModal = () => {
        const modal = document.getElementById('warning-modal');
        if (modal) modal.style.display = 'none';
    };

    let pendingFormSubmit = null;

    window.confirmWarningSubmit = async () => {
        closeWarningModal();
        if (pendingFormSubmit) {
            await pendingFormSubmit();
            pendingFormSubmit = null;
        } else {
            console.error('❌ No pending form submit found!');
        }
    };

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
            console.error('Missing required fields');
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
            console.error('No dates selected');
            alert('Please select at least one date from the breakdown table.');
            return;
        }

        // Validate day types match available balance - STRICT VALIDATION
        const dayTypeValidationErrors = [];
        dates.forEach(d => {
            const validation = validateDayTypeForBalance(d.type_name, d.day_type);
            if (!validation.valid) {
                dayTypeValidationErrors.push(validation.reason);
            }
        });
        
        if (dayTypeValidationErrors.length > 0) {
            console.error('❌ Day type validation failed:', dayTypeValidationErrors);
            // Show professional error modal instead of alert
            let errorMsg = '❌ INVALID DAY TYPE SELECTION:\n\n';
            dayTypeValidationErrors.forEach(err => {
                errorMsg += `• ${err}\n`;
            });
            showResultModal('Invalid Day Type', errorMsg, 'error');
            return;
        }

        // Sick Leave Mandatory File Check
        const requiresUpload = dates.some(d => d.type_name.toLowerCase().includes('sick'));
        if (requiresUpload && selectedFiles.length === 0) {
            console.error('Sick leave requires file upload');
            alert('Please upload medical documents for your Sick Leave request.');
            return;
        }

        // ─── VALIDATE LEAVE BALANCE & SHOW WARNINGS ───
        const validation = validateLeaveBalance(dates);
        
        // If validation shows warnings, ask for confirmation asynchronously
        if (validation.warnings.length > 0 || validation.errors.length > 0) {
            const canProceed = await showValidationWarning(validation);
            if (!canProceed) {
                return;
            }
            if (validation.errors.length > 0) {
                return;
            }
        }

        // No warnings or errors, or user confirmed - proceed with submission
        await performFormSubmission(dates, reason, approver, requiresUpload);
    });

    // Helper function to perform actual form submission
    const performFormSubmission = async (dates, reason, approver, requiresUpload) => {
        
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
        
        let timer = null;
        let timeoutHandle = null;
        
        try {
            timer = setInterval(() => {
                quoteIdx = (quoteIdx + 1) % funnyQuotes.length;
                quoteEl.textContent = funnyQuotes[quoteIdx];
            }, 800);

            const minWait = new Promise(resolve => setTimeout(resolve, 3000));
            
            // Set a timeout for the fetch (30 seconds max)
            const timeoutPromise = new Promise((_, reject) => {
                timeoutHandle = setTimeout(() => reject(new Error('Request timeout after 30 seconds')), 30000);
            });

            const formData = new FormData();
            formData.append('reason', reason);
            formData.append('approver_id', approver);
            formData.append('dates', JSON.stringify(dates));
            
            selectedFiles.forEach(file => {
                formData.append('sick_leave_files[]', file);
            });


            const resPromise = fetch('../api/save_leave_request.php', {
                method:  'POST',
                body:    formData
            });

            const [res] = await Promise.race([
                Promise.all([resPromise, minWait]),
                timeoutPromise
            ]);
            
            
            if (!res) {
                throw new Error('No response from server');
            }
            
            if (!res.ok) {
                console.error('❌ HTTP Error:', res.status, res.statusText);
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            
            const data = await res.json();

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
                console.error('❌ Server returned success=false:', data.message);
                showResultModal('Submission Failed', data.message || 'Please try again.', 'error');
            }
        } catch (err) {
            console.error('❌ Submission error:', err);
            const errorMsg = err.message || 'An unexpected error occurred. Please try again.';
            showResultModal('Error', errorMsg, 'error');
        } finally {
            if (timer) clearInterval(timer);
            if (timeoutHandle) clearTimeout(timeoutHandle);
            if (loader) loader.classList.remove('active');
            btn.textContent = 'Submit Application →';
            btn.disabled = false;
        }
    };

    function showResultModal(title, desc, type) {
        const modal = document.getElementById('result-modal');
        const iconWrap = document.getElementById('result-icon');
        document.getElementById('result-title').textContent = title;
        document.getElementById('result-desc').innerHTML = desc;
        
        iconWrap.className = 'nlr-modal-icon ' + type;
        iconWrap.innerHTML = type === 'success' 
            ? '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
            : '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        
        modal.classList.add('active');
        
        // Auto-close after 8 seconds for success, 10 for error
        const autoCloseDelay = type === 'success' ? 8000 : 10000;
        setTimeout(() => {
            if (modal.classList.contains('active')) {
                window.closeResultModal();
            }
        }, autoCloseDelay);
    }

    window.closeResultModal = () => {
        const modal = document.getElementById('result-modal');
        modal.classList.remove('active');
    };

    // ─────────────────────────────────────────────
    // 7. Modals
    // ─────────────────────────────────────────────
    window.closeModal = (id) => {
        document.getElementById(id).classList.remove('active');
    };

    // Add keyboard support: ESC to close result modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const resultModal = document.getElementById('result-modal');
            if (resultModal && resultModal.classList.contains('active')) {
                window.closeResultModal();
            }
        }
    });

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
