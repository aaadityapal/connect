<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Settings - Connect</title>
    <!-- Sidebar Requirements -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>window.SIDEBAR_BASE_PATH = '../';</script>
    <script src="../components/sidebar-loader.js" defer></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Modular CSS -->
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/table.css">

    <style>
        .settings-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-top: 20px; }
        
        /* Tabs Styling */
        .tabs-header { display: flex; gap: 20px; border-bottom: 2px solid #f1f5f9; margin-bottom: 25px; }
        .tab-btn { padding: 12px 20px; border: none; background: none; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: var(--clr-blue); border-bottom-color: var(--clr-blue); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .role-list, .rate-list { display: grid; gap: 12px; margin-top: 15px; }
        .role-item, .rate-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; transition: all 0.2s; }
        .role-info, .rate-info { display: flex; align-items: center; gap: 12px; }
        .role-icon, .rate-icon { width: 36px; height: 36px; background: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 14px; border: 1px solid #e2e8f0; }
        .role-name, .rate-name { font-weight: 600; color: #1e293b; font-size: 14px; }
        
        /* Inputs */
        .rate-input-wrap { display: flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2px 10px; width: 140px; transition: border 0.2s; }
        .rate-input-wrap:focus-within { border-color: var(--clr-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .rate-currency { color: #64748b; font-size: 12px; font-weight: 700; margin-right: 5px; }
        .rate-input { border: none; outline: none; width: 100%; font-size: 14px; font-weight: 600; text-align: right; color: #1e293b; }
        .rate-unit { color: #94a3b8; font-size: 11px; margin-left: 5px; }

        /* Switch styling */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #16a34a; }
        input:checked + .slider:before { transform: translateX(20px); }
        .role-badge { font-size: 11px; padding: 2px 8px; background: #eff6ff; color: #2563eb; border-radius: 4px; font-weight: 700; text-transform: uppercase; margin-top: 2px; }

        /* Approval Schedule Styles */
        .day-chip { cursor: pointer; position: relative; user-select: none; }
        .day-chip input { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
        .day-chip span { display: inline-block; padding: 8px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; font-size: 13px; font-weight: 600; color: #64748b; transition: all 0.2s; }
        .day-chip input:checked + span { background: #2563eb; color: #fff; border-color: #2563eb; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
        .day-chip:hover span { border-color: #2563eb; }
        
        .time-input:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        .mapping-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .mapping-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; transition: transform 0.2s, box-shadow 0.2s; }
        .mapping-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); border-color: #cbd5e1; }
        .mapping-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
        .mapping-avatar { width: 40px; height: 40px; background: #eff6ff; color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; }
        .mapping-emp-info h5 { margin: 0; font-size: 14px; color: #1e293b; font-weight: 700; }
        .mapping-emp-info span { font-size: 11px; color: #64748b; font-weight: 500; }
        .mapping-flow { display: flex; flex-direction: column; gap: 8px; }
        .flow-step { display: flex; align-items: center; gap: 10px; font-size: 12px; color: #475569; }
        .flow-step i { width: 14px; text-align: center; color: #94a3b8; font-size: 10px; }
        .flow-step .step-label { width: 70px; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; }
        .flow-step .step-user { font-weight: 600; color: #1e293b; flex: 1; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>
        <button class="mobile-hamburger-btn" id="mobileMenuBtn"><i data-lucide="menu"></i></button>
        <main class="main-content">
            <header class="header-banner" style="background:#1e293b; padding: 30px; border-radius: 16px; margin-bottom: 24px; color: #fff;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h1 style="font-size: 24px; margin-bottom: 5px;">Travel Expense Settings</h1>
                        <p style="color: #94a3b8; font-size: 14px;">Define permissions and mileage rates</p>
                    </div>
                </div>
            </header>

            <div class="settings-card">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="rates">
                        <i class="fa-solid fa-gas-pump" style="margin-right:8px;"></i>Per KM Rates
                    </button>
                    <button class="tab-btn" data-tab="auth">
                        <i class="fa-solid fa-file-invoice-dollar" style="margin-right:8px;"></i>Payment Permissions
                    </button>
                    <button class="tab-btn" data-tab="approval">
                        <i class="fa-solid fa-calendar-check" style="margin-right:8px;"></i>Approval Window
                    </button>
                </div>
                
                <!-- Tab 2: Rates -->
                <div id="rates" class="tab-content active">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:15px;">
                        <h3 style="font-size: 14px; color: #475569;">Set per-kilometer reimbursement rates for each mode</h3>
                    </div>
                    <div class="rate-list" id="rate-list-container">
                        <p style="text-align:center; color:#94a3b8; padding: 20px;">Loading rates...</p>
                    </div>
                </div>

                <!-- Tab 3: Auth -->
                <div id="auth" class="tab-content">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:15px;">
                        <h3 style="font-size: 14px; color: #475569;">Grant specific users the permission to mark travel expenses as "Paid"</h3>
                    </div>
                    <div class="role-list" id="auth-list-container">
                        <p style="text-align:center; color:#94a3b8; padding: 20px;">Loading users...</p>
                    </div>
                </div>

                <!-- Tab 4: Approval Day and Time -->
                <div id="approval" class="tab-content">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:15px;">
                        <h3 style="font-size: 14px; color: #475569;">Configure approval schedule windows per individual approver</h3>
                    </div>

                    <div>
                        <div class="mapping-grid" id="mapping-list-container" style="grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));">
                            <p style="text-align:center; color:#94a3b8; padding: 20px;">Loading approvers...</p>
                        </div>
                    </div>
                </div>


            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetchRates();
            fetchAuths();
            fetchMapping();
            initTabs();
        });

        function initTabs() {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    btn.classList.add('active');
                    document.getElementById(btn.dataset.tab).classList.add('active');
                });
            });
        }



        // --- Per KM Rates Logic ---
        async function fetchRates() {
            try {
                const response = await fetch('../api/fetch_travel_transport_rates.php');
                const data = await response.json();
                if (data.success) renderRates(data.rates);
            } catch (e) { console.error(e); }
        }

        function renderRates(rates) {
            const container = document.getElementById('rate-list-container');
            container.innerHTML = rates.map(rate => `
                <div class="rate-item">
                    <div class="rate-info">
                        <div class="rate-icon"><i class="fa-solid fa-car-side"></i></div>
                        <div>
                            <div class="rate-name">${rate.transport_mode}</div>
                        </div>
                    </div>
                    <div class="rate-input-wrap">
                        <span class="rate-currency">₹</span>
                        <input type="number" step="0.01" class="rate-input" value="${rate.rate_per_km}" 
                               onblur="updateRate('${rate.transport_mode}', this.value)">
                        <span class="rate-unit">/ km</span>
                    </div>
                </div>
            `).join('');
        }

        async function updateRate(mode, rate) {
            try {
                const response = await fetch('../api/update_travel_transport_rate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ transport_mode: mode, rate_per_km: rate })
                });
                const data = await response.json();
                if (data.success) fetchRates();
            } catch (e) { console.error(e); }
        }

        // --- Payment Auth Logic ---
        async function fetchAuths() {
            try {
                const response = await fetch('../api/fetch_payment_auth.php');
                const data = await response.json();
                if (data.success) renderAuths(data.auth_users);
            } catch (e) { console.error(e); }
        }

        function renderAuths(users) {
            const container = document.getElementById('auth-list-container');
            container.innerHTML = users.map(user => `
                <div class="role-item">
                    <div class="role-info">
                        <div class="role-icon" style="background:var(--clr-blue); color:#fff; border:none;"><i class="fa-solid fa-user-lock"></i></div>
                        <div>
                            <div class="role-name">${user.username} <span style="font-size:0.75rem; color:#94a3b8; margin-left:4px;">(${user.unique_id || user.employee_id})</span></div>
                            <div class="role-badge" style="background:#f1f5f9; color:#475569;">Role: ${user.role}</div>
                        </div>
                    </div>
                    <div>
                        <label class="switch">
                            <input type="checkbox" ${user.can_pay == 1 ? 'checked' : ''} onchange="updateAuth(${user.id}, this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            `).join('');
        }

        async function updateAuth(userId, checked) {
            try {
                await fetch('../api/update_payment_auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, can_pay: checked ? 1 : 0 })
                });
                fetchAuths();
            } catch (e) { console.error(e); }
        }

        // --- Approval Schedule & Mapping Logic ---
        async function fetchMapping() {
            try {
                const response = await fetch('../api/fetch_travel_mapping.php');
                const data = await response.json();
                if (data.success) renderMapping(data.approvers);
            } catch (e) { console.error(e); }
        }

        function renderMapping(approvers) {
            const container = document.getElementById('mapping-list-container');
            if (!approvers || approvers.length === 0) {
                container.innerHTML = '<p style="grid-column: 1/-1; text-align:center; color:#94a3b8; padding: 40px; background:#f8fafc; border-radius:12px; border:1px dashed #e2e8f0;">No active approvers found.</p>';
                return;
            }

            const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            const dayShort = { Monday:'Mon', Tuesday:'Tue', Wednesday:'Wed', Thursday:'Thu', Friday:'Fri', Saturday:'Sat', Sunday:'Sun' };

            container.innerHTML = approvers.map(a => {
                const sched = a.day_schedule || {};

                const dayRows = days.map(day => {
                    const d = sched[day] || { is_active: (day === 'Saturday' || day === 'Sunday') ? 0 : 1, start_time: '09:00', end_time: '18:00' };
                    return `
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 10px; font-size:13px; font-weight:600; color:#475569; width:90px;">${dayShort[day]}</td>
                        <td style="padding: 8px 10px; text-align:center;">
                            <label class="switch" style="margin:0;">
                                <input type="checkbox" class="day-active" data-day="${day}" ${d.is_active ? 'checked' : ''}
                                    onchange="toggleDayRow(this, '${a.id}', '${day}')">
                                <span class="slider"></span>
                            </label>
                        </td>
                        <td style="padding: 8px 10px;">
                            <input type="time" class="time-input day-start" data-day="${day}"
                                id="start-${a.id}-${day}" value="${d.start_time}"
                                ${d.is_active ? '' : 'disabled'}
                                style="width:100%; padding:6px 8px; border-radius:6px; border:1px solid #e2e8f0; font-size:13px; font-weight:600; color:#1e293b; ${d.is_active ? '' : 'opacity:0.4;'}">
                        </td>
                        <td style="padding: 8px 10px;">
                            <input type="time" class="time-input day-end" data-day="${day}"
                                id="end-${a.id}-${day}" value="${d.end_time}"
                                ${d.is_active ? '' : 'disabled'}
                                style="width:100%; padding:6px 8px; border-radius:6px; border:1px solid #e2e8f0; font-size:13px; font-weight:600; color:#1e293b; ${d.is_active ? '' : 'opacity:0.4;'}">
                        </td>
                    </tr>`;
                }).join('');

                return `
                <div class="mapping-card" style="display:flex; flex-direction:column; gap:14px;">
                    <div style="display:flex; align-items:center; gap:12px; padding-bottom:12px; border-bottom:1px solid #f1f5f9;">
                        <div class="role-icon" style="background:#eff6ff; color:#2563eb; width:44px; height:44px; font-size:16px;"><i class="fa-solid fa-user-tie"></i></div>
                        <div>
                            <div style="font-weight:700; color:#1e293b; font-size:15px;">${a.username} <span style="font-size:12px; color:#64748b; font-weight:500;">(${a.unique_id || a.employee_id})</span></div>
                            <div class="role-badge" style="background:#f1f5f9; color:#475569; margin-top:4px; display:inline-block;">${a.role}</div>
                        </div>
                    </div>

                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:8px 10px; text-align:left; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Day</th>
                                <th style="padding:8px 10px; text-align:center; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Active</th>
                                <th style="padding:8px 10px; text-align:left; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Start</th>
                                <th style="padding:8px 10px; text-align:left; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">End</th>
                            </tr>
                        </thead>
                        <tbody id="sched-body-${a.id}">${dayRows}</tbody>
                    </table>

                    <button onclick="updateApproverSchedule(${a.id})"
                        style="background:#f8fafc; color:#2563eb; border:1px solid #cbd5e1; padding:10px; border-radius:8px; font-weight:600; cursor:pointer; transition:all 0.2s;"
                        onmouseover="this.style.background='#2563eb'; this.style.color='#fff';"
                        onmouseout="this.style.background='#f8fafc'; this.style.color='#2563eb';">
                        <i class="fa-solid fa-floppy-disk" style="margin-right:6px;"></i>Save Schedule
                    </button>
                </div>`;
            }).join('');
        }

        function toggleDayRow(checkbox, approverId, day) {
            const active = checkbox.checked;
            const startEl = document.getElementById(`start-${approverId}-${day}`);
            const endEl   = document.getElementById(`end-${approverId}-${day}`);
            if (startEl) { startEl.disabled = !active; startEl.style.opacity = active ? '1' : '0.4'; }
            if (endEl)   { endEl.disabled   = !active; endEl.style.opacity   = active ? '1' : '0.4'; }
        }

        async function updateApproverSchedule(userId) {
            const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            const schedule = {};

            days.forEach(day => {
                const activeEl = document.querySelector(`#sched-body-${userId} .day-active[data-day="${day}"]`);
                const startEl  = document.getElementById(`start-${userId}-${day}`);
                const endEl    = document.getElementById(`end-${userId}-${day}`);
                if (!activeEl) return;
                schedule[day] = {
                    is_active:  activeEl.checked ? 1 : 0,
                    start_time: startEl ? startEl.value : '09:00',
                    end_time:   endEl   ? endEl.value   : '18:00'
                };
            });

            try {
                const response = await fetch('../api/update_approver_schedule.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ approver_id: userId, schedule })
                });
                const data = await response.json();
                if (data.success) {
                    alert('Schedule saved successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (e) { console.error(e); }
        }


    </script>
</body>
</html>
</body>
</html>
