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
    <title>Food Reimbursement Mapping | Connect</title>
    <meta name="description" content="Manage food reimbursement approval routing — assign which manager and HR approver reviews each employee's late-night food reimbursement claims.">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons & Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <!-- Sidebar base path: two levels up from manager_pages/food_reimbursement_mapping/ -->
    <script>window.SIDEBAR_BASE_PATH = '../../studio_users/';</script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>

    <style>
        /* ─── Reset & Base ─────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #f0f4f8;
            color: #1e293b;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* ─── Sidebar Mount ─────────────────────────────────────── */
        #sidebar-mount {
            position: sticky;
            top: 0;
            height: 100vh;
            flex-shrink: 0;
        }

        /* ─── Main Area ─────────────────────────────────────────── */
        .main-content {
            flex: 1;
            background: #f0f4f8;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ─── Top Header ────────────────────────────────────────── */
        .page-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 2.5rem;
            background: #ffffff;
            border-bottom: 1px solid #e8edf5;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 8px rgba(0,0,0,0.05);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-icon-wrap {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f97316, #ea580c);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 14px rgba(249,115,22,0.35);
            flex-shrink: 0;
        }

        .topbar-title-block h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .topbar-title-block p {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 400;
            margin-top: 2px;
        }

        /* ─── Page Content ──────────────────────────────────────── */
        .mapping-container {
            padding: 2rem 2.5rem;
            animation: fadeSlideUp 0.45s ease-out;
        }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── Stats Strip ───────────────────────────────────────── */
        .stats-strip {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
        }

        .stat-pill {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.65rem 1.1rem;
            font-size: 0.88rem;
            font-weight: 600;
            color: #334155;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            transition: transform 0.2s;
        }

        .stat-pill:hover { transform: translateY(-1px); }

        .stat-pill .pill-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
        }

        .stat-pill .pill-num {
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
        }

        /* ─── Card ──────────────────────────────────────────────── */
        .card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 28px -6px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        /* ─── Filter Bar ────────────────────────────────────────── */
        .filter-bar {
            padding: 1.25rem 1.75rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 220px;
            max-width: 420px;
        }

        .search-box .s-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px; height: 15px;
            color: #94a3b8;
        }

        .search-box input {
            width: 100%;
            padding: 0.62rem 1rem 0.62rem 38px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            font-size: 0.88rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s;
        }

        .search-box input:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249,115,22,0.12);
        }

        .filter-right {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-left: auto;
        }

        .filter-badge {
            font-size: 0.78rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* ─── Table ─────────────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        th {
            padding: 1rem 1.5rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.055em;
            color: #64748b;
            white-space: nowrap;
        }

        td {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tbody tr {
            transition: background 0.15s;
        }

        tbody tr:hover {
            background: #fafbff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Employee Cell */
        .emp-cell {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .emp-avatar {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: #fff;
            flex-shrink: 0;
        }

        .emp-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.92rem;
        }

        .emp-role {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* Approver select */
        .approver-wrap { display: flex; flex-direction: column; gap: 4px; }

        .level-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            width: fit-content;
        }

        .level-label.mgr  { background: #eff6ff; color: #2563eb; }
        .level-label.hr   { background: #f0fdf4; color: #15803d; }

        .approver-select {
            padding: 0.55rem 0.9rem;
            border-radius: 9px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 0.85rem;
            font-family: inherit;
            min-width: 200px;
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
            color: #334155;
        }

        .approver-select:focus {
            border-color: #f97316;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(249,115,22,0.1);
        }

        /* Save Button */
        .save-row-btn {
            background: #1e293b;
            color: white;
            border: none;
            padding: 0.6rem 1.1rem;
            border-radius: 9px;
            font-size: 0.83rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            white-space: nowrap;
        }

        .save-row-btn:hover {
            background: #334155;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .save-row-btn:active { transform: scale(0.97); }
        .save-row-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

        /* Mapped badge */
        .mapped-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
        }

        .unmapped-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 6px;
            background: #fef3c7;
            color: #92400e;
        }

        /* ─── Loading & Empty States ────────────────────────────── */
        .state-cell {
            text-align: center;
            padding: 5rem 2rem;
            color: #94a3b8;
        }

        .state-cell .spin {
            animation: spin360 1.4s linear infinite;
            display: inline-block;
            margin-bottom: 1rem;
        }

        @keyframes spin360 {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* ─── Toast ─────────────────────────────────────────────── */
        #frm-toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 0.9rem 1.6rem;
            border-radius: 12px;
            background: #1e293b;
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            transform: translateY(120%);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        #frm-toast.show { transform: translateY(0); }
        #frm-toast.success { background: #059669; }
        #frm-toast.error   { background: #dc2626; }

        /* ─── Responsive ────────────────────────────────────────── */
        @media (max-width: 768px) {
            .mapping-container { padding: 1.25rem 1rem; }
            .page-topbar { padding: 1rem 1.25rem; }
            .filter-bar { padding: 1rem; }
            th, td { padding: 0.9rem 1rem; }
            .approver-select { min-width: 150px; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar injected here -->
    <div id="sidebar-mount" style="position:sticky;top:0;height:100vh;flex-shrink:0;"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="page-topbar">
            <div class="topbar-left">
                <div class="topbar-icon-wrap">
                    <i data-lucide="utensils" style="width:20px;height:20px;"></i>
                </div>
                <div class="topbar-title-block">
                    <h1>Food Reimbursement Mapping</h1>
                    <p>Assign who approves each employee's late-night food reimbursement claim</p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="mapping-container">

            <!-- Stats -->
            <div class="stats-strip" id="statsStrip">
                <div class="stat-pill">
                    <span class="pill-dot" style="background:#6366f1;"></span>
                    <span class="pill-num" id="statTotal">—</span>
                    <span>Total Employees</span>
                </div>
                <div class="stat-pill">
                    <span class="pill-dot" style="background:#10b981;"></span>
                    <span class="pill-num" id="statMapped">—</span>
                    <span>Fully Mapped</span>
                </div>
                <div class="stat-pill">
                    <span class="pill-dot" style="background:#f59e0b;"></span>
                    <span class="pill-num" id="statUnmapped">—</span>
                    <span>Pending Mapping</span>
                </div>
            </div>

            <!-- Main Card -->
            <div class="card">
                <div class="filter-bar">
                    <div class="search-box">
                        <i data-lucide="search" class="s-icon"></i>
                        <input type="text" id="empSearch" placeholder="Search employee by name or role…">
                    </div>
                    <div class="filter-right">
                        <span class="filter-badge">
                            <i data-lucide="info" style="width:13px;height:13px;"></i>
                            Select approvers per row, then click Save
                        </span>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Level 1 — Manager</th>
                                <th>Level 2 — HR Approver</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="mappingTableBody">
                            <tr>
                                <td colspan="5" class="state-cell">
                                    <div class="spin">
                                        <i data-lucide="loader-2" style="width:40px;height:40px;"></i>
                                    </div>
                                    <p>Loading roster…</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /mapping-container -->
    </main>
</div>

<!-- Toast -->
<div id="frm-toast">
    <i data-lucide="check-circle" style="width:16px;height:16px;" id="toastIcon"></i>
    <span id="toastMsg">Saved!</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    /* ─── State ─────────────────────────────────────────── */
    let allUsers     = [];
    let approvers    = [];
    let mappings     = {};   // keyed by employee_id
    let defaultHrId  = '';   // auto-detected first HR approver

    /* ─── DOM refs ──────────────────────────────────────── */
    const tbody       = document.getElementById('mappingTableBody');
    const searchInput = document.getElementById('empSearch');
    const statTotal   = document.getElementById('statTotal');
    const statMapped  = document.getElementById('statMapped');
    const statUnmapped= document.getElementById('statUnmapped');
    const toast       = document.getElementById('frm-toast');
    const toastMsg    = document.getElementById('toastMsg');
    const toastIcon   = document.getElementById('toastIcon');

    /* ─── Helpers ───────────────────────────────────────── */
    function getInitials(name) {
        return (name || '').split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    }

    function getHSL(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return `hsl(${Math.abs(hash % 360)}, 58%, 44%)`;
    }

    function showToast(msg, type = 'success') {
        toastMsg.textContent = msg;
        toast.className = `show ${type}`;
        toastIcon.setAttribute('data-lucide', type === 'success' ? 'check-circle' : 'alert-circle');
        if (window.lucide) lucide.createIcons();
        setTimeout(() => { toast.className = ''; }, 3200);
    }

    function buildApproverOptions(selectedId) {
        return approvers.map(a => {
            const sel = parseInt(selectedId) === parseInt(a.id) ? 'selected' : '';
            const label = `${a.name} (${a.position || a.role})`;
            return `<option value="${a.id}" ${sel}>${label}</option>`;
        }).join('');
    }

    /* ─── Stats ─────────────────────────────────────────── */
    function updateStats(users) {
        let mapped = 0;
        users.forEach(u => {
            const m = mappings[u.id];
            if (m && m.manager_id && m.hr_id) mapped++;
        });
        statTotal.textContent   = users.length;
        statMapped.textContent  = mapped;
        statUnmapped.textContent = users.length - mapped;
    }

    /* ─── Render Table ──────────────────────────────────── */
    function renderTable(filter = '') {
        const term = filter.toLowerCase().trim();
        const filtered = allUsers.filter(u =>
            u.name.toLowerCase().includes(term) ||
            (u.role     || '').toLowerCase().includes(term) ||
            (u.position || '').toLowerCase().includes(term)
        );

        updateStats(allUsers); // stats always over full list

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="state-cell">
                <i data-lucide="search-x" style="width:36px;height:36px;margin-bottom:0.75rem;"></i>
                <p>No employees match your search.</p>
            </td></tr>`;
            if (window.lucide) lucide.createIcons();
            return;
        }

        tbody.innerHTML = filtered.map(user => {
            const m = mappings[user.id] || {};
            const managerId = m.manager_id || '';
            // Use saved HR if present, otherwise auto-select the default HR approver
            const hrId      = m.hr_id      || defaultHrId;
            const isMapped  = managerId && hrId;

            const avatarColor = getHSL(user.name);
            const initials    = getInitials(user.name);
            const roleLabel   = user.position || user.role || '—';

            const statusBadge = isMapped
                ? `<span class="mapped-badge"><i data-lucide="check" style="width:11px;height:11px;"></i>Mapped</span>`
                : `<span class="unmapped-badge"><i data-lucide="alert-triangle" style="width:11px;height:11px;"></i>Unmapped</span>`;

            return `
            <tr data-uid="${user.id}">
                <td>
                    <div class="emp-cell">
                        <div class="emp-avatar" style="background:${avatarColor};">${initials}</div>
                        <div>
                            <div class="emp-name">${user.name}</div>
                            <div class="emp-role">${roleLabel}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="approver-wrap">
                        <span class="level-label mgr">Manager</span>
                        <select class="approver-select" data-level="manager">
                            <option value="">— Select Manager —</option>
                            ${buildApproverOptions(managerId)}
                        </select>
                    </div>
                </td>
                <td>
                    <div class="approver-wrap">
                        <span class="level-label hr">HR</span>
                        <select class="approver-select" data-level="hr">
                            <option value="">— Select HR —</option>
                            ${buildApproverOptions(hrId)}
                        </select>
                    </div>
                </td>
                <td>${statusBadge}</td>
                <td>
                    <button class="save-row-btn" onclick="saveMapping(${user.id}, this)">
                        <i data-lucide="save" style="width:14px;height:14px;"></i>
                        Save
                    </button>
                </td>
            </tr>`;
        }).join('');

        if (window.lucide) lucide.createIcons();
    }

    /* ─── Save Individual Mapping ───────────────────────── */
    window.saveMapping = async (employeeId, btn) => {
        const row       = btn.closest('tr');
        const managerId = row.querySelector('[data-level="manager"]').value;
        const hrId      = row.querySelector('[data-level="hr"]').value;

        if (!managerId || !hrId) {
            showToast('Please select both a Manager and an HR approver.', 'error');
            return;
        }

        btn.disabled = true;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i data-lucide="loader-2" style="width:14px;height:14px;" class="spin-icon"></i> Saving…';
        if (window.lucide) lucide.createIcons();

        try {
            const res = await fetch('api/update_food_mapping.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ employee_id: employeeId, manager_id: managerId, hr_id: hrId })
            });
            const data = await res.json();

            if (data.success) {
                // Update local state
                mappings[employeeId] = { manager_id: managerId, hr_id: hrId };
                // Refresh status badge
                const statusCell = row.querySelector('td:nth-child(4)');
                statusCell.innerHTML = `<span class="mapped-badge"><i data-lucide="check" style="width:11px;height:11px;"></i>Mapped</span>`;
                if (window.lucide) lucide.createIcons();

                // Animate button
                btn.style.background = '#059669';
                btn.innerHTML = '<i data-lucide="check" style="width:14px;height:14px;"></i> Saved!';
                if (window.lucide) lucide.createIcons();

                showToast('Mapping saved successfully!', 'success');
                updateStats(allUsers);

                setTimeout(() => {
                    btn.style.background = '';
                    btn.innerHTML = origHtml;
                    btn.disabled = false;
                    if (window.lucide) lucide.createIcons();
                }, 2500);

            } else {
                showToast('Error: ' + (data.error || 'Save failed'), 'error');
                btn.disabled = false;
                btn.innerHTML = origHtml;
                if (window.lucide) lucide.createIcons();
            }

        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = origHtml;
            if (window.lucide) lucide.createIcons();
        }
    };

    /* ─── Load Data ─────────────────────────────────────── */
    async function loadData() {
        try {
            const res  = await fetch('api/get_food_mapping_data.php');
            const data = await res.json();

            if (data.success) {
                allUsers  = data.users;
                approvers = data.approvers;
                mappings  = data.mappings || {};

                // Auto-detect the first approver whose role is exactly 'HR'
                const hrApprover = approvers.find(
                    a => (a.role || '').trim().toLowerCase() === 'hr'
                );
                defaultHrId = hrApprover ? String(hrApprover.id) : '';

                renderTable();
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="state-cell" style="color:#dc2626;">
                    <i data-lucide="alert-circle" style="width:32px;height:32px;margin-bottom:0.5rem;"></i>
                    <p>${data.error || 'Failed to load data.'}</p>
                </td></tr>`;
                if (window.lucide) lucide.createIcons();
            }

        } catch (err) {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="5" class="state-cell" style="color:#dc2626;">
                <i data-lucide="wifi-off" style="width:32px;height:32px;margin-bottom:0.5rem;"></i>
                <p>Network error — could not load mapping data.</p>
            </td></tr>`;
            if (window.lucide) lucide.createIcons();
        }
    }

    /* ─── Search ────────────────────────────────────────── */
    searchInput.addEventListener('input', e => renderTable(e.target.value));

    /* ─── Init ──────────────────────────────────────────── */
    loadData();

    // Spin animation for inline loaders
    const style = document.createElement('style');
    style.textContent = `.spin-icon { animation: spin360 1s linear infinite; }
    @keyframes spin360 { from{transform:rotate(0deg);} to{transform:rotate(360deg);} }`;
    document.head.appendChild(style);
});
</script>
</body>
</html>
