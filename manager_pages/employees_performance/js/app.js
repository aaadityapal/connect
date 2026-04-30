/**
 * app.js — Employees Performance Module
 * Handles UI rendering, filtering, mock data (UI-only phase)
 */

'use strict';

function toggleTaskList(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    const isHidden = el.style.display === 'none';
    el.style.display = isHidden ? 'block' : 'none';
    
    if (btn) {
        btn.innerHTML = isHidden ? 
            '<i data-lucide="chevron-up" style="width:12px;height:12px;"></i> Hide' : 
            '<i data-lucide="chevron-down" style="width:12px;height:12px;"></i> Details';
    }
    if (window.lucide) lucide.createIcons();
}

let MOCK_EMPLOYEES = [];
let MOCK_PROJECTS = ['All Projects'];
let ROLES = ['All'];
let PERIODS = ['This Month', 'Last Month', 'Q1 2026', 'Q4 2025']; // Removed week periods to replace with dedicated week filter

function generateCurrentMonthWeeks(targetYear, targetMonth) {
    const weeks = [];
    const year = targetYear || new Date().getFullYear();
    const month = (targetMonth !== undefined) ? targetMonth - 1 : new Date().getMonth();
    
    let d = new Date(year, month, 1);
    let weekNum = 1;
    
    while (d.getMonth() === month) {
        const start = new Date(d);
        while (d.getDay() !== 0 && d.getMonth() === month) {
            d.setDate(d.getDate() + 1);
        }
        if (d.getMonth() !== month) {
            d.setDate(0);
        }
        const end = new Date(d);
        
        const startStr = start.getFullYear() + '-' + String(start.getMonth()+1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0');
        const endStr = end.getFullYear() + '-' + String(end.getMonth()+1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0');
        
        const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const label = `Week ${weekNum} (${start.getDate()} ${monthNames[month]} - ${end.getDate()} ${monthNames[month]})`;
        
        weeks.push({ label, startStr, endStr });
        
        d.setDate(d.getDate() + 1);
        weekNum++;
    }
    return weeks;
}

let CURRENT_WEEKS = generateCurrentMonthWeeks(new Date().getFullYear(), new Date().getMonth() + 1);

/* ─────────────────────────────────────────
   STATE
   ───────────────────────────────────────── */
let state = {
    search:    '',
    role:      'All',
    projects:  ['All Projects'],
    period:    'This Month',
    week:      'All Weeks',
    sortBy:    'score',
    sortDir:   'desc',
    selected:  null,   // selected employee ID for modal
    month:     new Date().getMonth() + 1,
    year:      new Date().getFullYear()
};

/* ─────────────────────────────────────────
   RENDER — METRICS
   ───────────────────────────────────────── */
function renderMetrics() {
    const cards = [
        { label: 'Avg. Overall Score', value: avgMetric('score') + '%', icon: 'trending-up',  accent: '#7c3aed', iconBg: 'rgba(124,58,237,0.12)', iconClr: '#7c3aed', delta: '+2.4%', up: true },
        { label: 'Avg. Attendance',    value: avgMetric('attendance') + '%', icon: 'user-check', accent: '#10b981', iconBg: 'rgba(16,185,129,0.12)',  iconClr: '#10b981', delta: '+1.1%', up: true },
        { label: 'Task Completion',    value: avgMetric('tasks') + '%',      icon: 'circle-check-big', accent: '#0ea5e9', iconBg: 'rgba(14,165,233,0.12)', iconClr: '#0ea5e9', delta: '+3.2%', up: true },
        { label: 'Total Employees',    value: filteredSorted().length,       icon: 'users',       accent: '#ec4899', iconBg: 'rgba(236,72,153,0.12)',   iconClr: '#ec4899', delta: 'Stable', up: true },
    ];

    const html = cards.map((c, i) => `
        <div class="metric-card" style="--card-accent:${c.accent};--icon-bg:${c.iconBg};--icon-color:${c.iconClr}; animation-delay:${i * 0.07}s">
            <div class="metric-icon">
                <i data-lucide="${c.icon}" style="width:20px;height:20px;"></i>
            </div>
            <div class="metric-value">${c.value}</div>
            <div class="metric-label">${c.label}</div>
            <div class="metric-delta ${c.up ? 'delta-up' : 'delta-down'}">
                <i data-lucide="${c.up ? 'arrow-up-right' : 'arrow-down-right'}" style="width:12px;height:12px;"></i>
                ${c.delta} vs last month
            </div>
        </div>
    `).join('');

    document.getElementById('metrics-mount').innerHTML = `<div class="metrics-grid">${html}</div>`;
}

/* ─────────────────────────────────────────
   RENDER — FILTER BAR
   ───────────────────────────────────────── */
function renderFilters() {
    const roleOptions = ROLES.map(r => `<option value="${r}" ${r === state.role ? 'selected' : ''}>${r}</option>`).join('');
    const periodOptions = PERIODS.map(p => `<option value="${p}" ${p === state.period ? 'selected' : ''}>${p}</option>`).join('');
    const weekOptions = `<option value="All Weeks">All Weeks</option>` + CURRENT_WEEKS.map(w => {
        const val = `${w.startStr}_${w.endStr}`;
        return `<option value="${val}" ${val === state.week ? 'selected' : ''}>${w.label}</option>`;
    }).join('');

    // Custom Project Multi-select UI
    const isAll = state.projects.includes('All Projects');
    const displayLabel = isAll ? 'All Projects' : 
                         state.projects.length === 1 ? state.projects[0] : 
                         `${state.projects.length} Projects Selected`;

    const projectItems = MOCK_PROJECTS.map(p => {
        const checked = state.projects.includes(p);
        return `
            <div class="multiselect-item ${checked ? 'selected' : ''}" data-value="${p}">
                <input type="checkbox" ${checked ? 'checked' : ''} readonly>
                <span>${p}</span>
            </div>
        `;
    }).join('');

    document.getElementById('filters-mount').innerHTML = `
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Period</span>
                <select id="filter-period" class="filter-select">${periodOptions}</select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Week (Current M)</span>
                <select id="filter-week" class="filter-select">${weekOptions}</select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Projects</span>
                <div class="custom-multiselect" id="project-multiselect">
                    <div class="multiselect-trigger" id="project-trigger">
                        <span id="project-label">${displayLabel}</span>
                        <i data-lucide="chevron-down" style="width:14px;height:14px;opacity:0.6;"></i>
                    </div>
                    <div class="multiselect-dropdown" id="project-dropdown">
                        ${projectItems}
                    </div>
                </div>
            </div>
            <div class="filter-group">
                <span class="filter-label">Role</span>
                <select id="filter-role" class="filter-select">${roleOptions}</select>
            </div>
            <div class="filter-search-wrap">
                <i data-lucide="search" class="filter-search-icon" style="width:15px;height:15px;"></i>
                <input id="filter-search" class="filter-input" placeholder="Search employees…" value="${state.search}">
            </div>
            <button id="filter-export" class="btn btn-outline" title="Export CSV">
                <i data-lucide="download" style="width:15px;height:15px;"></i> Export
            </button>
        </div>
    `;

    // Dropdown Toggle
    const trigger = document.getElementById('project-trigger');
    const dropdown = document.getElementById('project-dropdown');
    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#project-multiselect')) {
            dropdown.classList.remove('show');
        }
    });

    // Item Selection Logic
    document.querySelectorAll('.multiselect-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            const val = item.dataset.value;
            let next = [...state.projects];

            if (val === 'All Projects') {
                next = ['All Projects'];
            } else {
                // Remove 'All Projects' if we select a specific one
                next = next.filter(p => p !== 'All Projects');
                if (next.includes(val)) {
                    next = next.filter(p => p !== val);
                } else {
                    next.push(val);
                }
                // If nothing left, default to All
                if (next.length === 0) next = ['All Projects'];
            }

            state.projects = next;
            renderFilters(); // Re-render local UI
            updateAll(); // Update table/metrics
            document.getElementById('project-dropdown').classList.add('show'); // Keep open
        });
    });

    document.getElementById('filter-period').addEventListener('change', e => { state.period = e.target.value; state.week = 'All Weeks'; renderFilters(); loadPerformanceData(); });
    document.getElementById('filter-week').addEventListener('change', e => { state.week = e.target.value; loadPerformanceData(); });
    
    document.getElementById('filter-role').addEventListener('change', e => { state.role = e.target.value; updateAll(); });
    document.getElementById('filter-search').addEventListener('input', e => { state.search = e.target.value; updateAll(); });
    document.getElementById('filter-export').addEventListener('click', exportCSV);
}

/* ─────────────────────────────────────────
   RENDER — TABLE
   ───────────────────────────────────────── */
function renderTable() {
    const data = filteredSorted();

    const sortArrow = (key) => {
        if (state.sortBy !== key) return '<i data-lucide="chevrons-up-down" style="width:12px;height:12px;opacity:0.4;"></i>';
        return state.sortDir === 'asc'
            ? '<i data-lucide="chevron-up" style="width:12px;height:12px;"></i>'
            : '<i data-lucide="chevron-down" style="width:12px;height:12px;"></i>';
    };

    const thBtn = (key, label) =>
        `<th><button class="th-sort-btn" data-sort="${key}" style="background:none;border:none;cursor:pointer;font:inherit;display:flex;align-items:center;gap:0.3rem;color:inherit;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">${label} ${sortArrow(key)}</button></th>`;

    const rows = data.length ? data.map(emp => {
        let tasks = emp.tasks;
        let score = emp.score;
        let pLabel = '';

        const hasProjectFilter = state.projects && !state.projects.includes('All Projects');
        if (hasProjectFilter) {
            const sub = emp.projects.filter(pr => state.projects.includes(pr.name));
            if (sub.length) {
                tasks = Math.round(sub.reduce((acc, curr) => acc + curr.tasks, 0) / sub.length);
                score = Math.round(sub.reduce((acc, curr) => acc + curr.score, 0) / sub.length);
                const label = sub.length === 1 ? sub[0].name : `${sub.length} Projects`;
                pLabel = `<div style="font-size:0.75rem; color:var(--brand); margin-top:0.2rem; font-weight:600;"><i data-lucide="folder" style="width:10px;height:10px;display:inline;"></i> ${label}</div>`;
            }
        }

        const g = getGrade(score);
        return `
            <tr data-emp-id="${emp.id}">
                <td>
                    <div class="employee-cell">
                        <div class="emp-avatar" style="background:${emp.avatar_color}">${initials(emp.name)}</div>
                        <div>
                            <div class="emp-name">${emp.name}</div>
                            <div class="emp-role">${emp.role}</div>
                            ${pLabel}
                        </div>
                    </div>
                </td>
                <td><span style="font-size:0.82rem;color:var(--text-muted);">${emp.role}</span></td>
                <td>
                    <div class="progress-bar-wrap">
                        <div class="progress-track"><div class="progress-fill" style="--bar-width:${emp.attendance}%;width:${emp.attendance}%;background:${progressColor(emp.attendance)};"></div></div>
                        <span class="progress-pct">${emp.attendance}%</span>
                    </div>
                </td>
                <td>
                    <div class="progress-bar-wrap">
                        <div class="progress-track"><div class="progress-fill" style="--bar-width:${tasks}%;width:${tasks}%;background:${progressColor(tasks)};"></div></div>
                        <span class="progress-pct">${tasks}%</span>
                    </div>
                </td>

                <td style="font-weight:800;font-size:1rem;color:var(--text-primary);">${score}%</td>
                <td><span class="perf-badge ${g.cls}">${g.label}</span></td>
                <td>
                    <button class="btn btn-outline view-detail-btn" data-emp-id="${emp.id}" style="padding:0.35rem 0.75rem;font-size:0.8rem;">
                        <i data-lucide="eye" style="width:13px;height:13px;"></i> View
                    </button>
                </td>
            </tr>
        `;
    }).join('') : `
        <tr><td colspan="8">
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="users" style="width:26px;height:26px;color:var(--text-muted);"></i></div>
                <div class="empty-state-title">No employees found</div>
                <div class="empty-state-sub">Try adjusting your filters.</div>
            </div>
        </td></tr>
    `;

    document.getElementById('table-mount').innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-lucide="table-2" style="width:17px;height:17px;color:var(--brand);"></i> Performance Records</span>
                <span style="font-size:0.82rem;color:var(--text-muted);">${data.length} employee${data.length !== 1 ? 's' : ''}</span>
            </div>
            <div class="perf-table-wrap">
                <table class="perf-table">
                    <thead>
                        <tr>
                            ${thBtn('name', 'Employee')}
                            <th>Role</th>
                            ${thBtn('attendance', 'Attendance')}
                            ${thBtn('tasks', 'Tasks')}
                            ${thBtn('score', 'Score')}
                            <th>Grade</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    `;

    // Sort listeners
    document.querySelectorAll('.th-sort-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.sort;
            if (state.sortBy === key) state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
            else { state.sortBy = key; state.sortDir = 'desc'; }
            updateAll();
        });
    });

    // View detail
    document.querySelectorAll('.view-detail-btn').forEach(btn => {
        btn.addEventListener('click', () => openModal(parseInt(btn.dataset.empId)));
    });

    if (window.lucide) lucide.createIcons();
}

/* ─────────────────────────────────────────
   RENDER — SIDEBAR PANELS
   ───────────────────────────────────────── */
function renderSidebar() {
    const sorted = [...filteredSorted()].sort((a, b) => {
        let valA = a.score; let valB = b.score;
        const hasProjectFilter = state.projects && !state.projects.includes('All Projects');
        if (hasProjectFilter) {
            const subA = a.projects.filter(p => state.projects.includes(p.name));
            const subB = b.projects.filter(p => state.projects.includes(p.name));
            if (subA.length) valA = Math.round(subA.reduce((acc, curr) => acc + curr.score, 0) / subA.length);
            else valA = 0;
            if (subB.length) valB = Math.round(subB.reduce((acc, curr) => acc + curr.score, 0) / subB.length);
            else valB = 0;
        }
        return valB - valA;
    });

    const topHtml = sorted.slice(0, 5).map((emp, i) => {
        let score = emp.score;
        const hasProjectFilter = state.projects && !state.projects.includes('All Projects');
        if (hasProjectFilter) {
            const sub = emp.projects.filter(pr => state.projects.includes(pr.name));
            if (sub.length) score = Math.round(sub.reduce((acc, curr) => acc + curr.score, 0) / sub.length);
        }

        const rankCls = i === 0 ? 'rank-1' : i === 1 ? 'rank-2' : i === 2 ? 'rank-3' : 'rank-n';
        return `
            <div class="top-performer-item">
                <div class="rank-badge ${rankCls}">${i + 1}</div>
                <div class="emp-avatar" style="background:${emp.avatar_color};width:32px;height:32px;font-size:0.75rem;">${initials(emp.name)}</div>
                <div class="tp-info">
                    <div class="tp-name">${emp.name}</div>
                    <div class="tp-role">${emp.role}</div>
                </div>
                <div class="tp-score">${score}%</div>
            </div>
        `;
    }).join('');

    const metrics = ['attendance', 'tasks'];
    const metricLabels = { attendance: 'Attendance', tasks: 'Task Completion' };
    const statHtml = metrics.map(m => {
        const avg = avgMetric(m);
        const color = progressColor(avg);
        return `
            <div class="stat-row">
                <div class="stat-row-header">
                    <span class="stat-name">${metricLabels[m]}</span>
                    <span class="stat-score" style="color:${color};">${avg}%</span>
                </div>
                <div class="progress-track" style="height:8px;">
                    <div class="progress-fill" style="--bar-width:${avg}%;width:${avg}%;background:${color};height:8px;"></div>
                </div>
            </div>
        `;
    }).join('');

    // Simple donut via SVG
    const avgScore = avgMetric('score');
    const circumference = 2 * Math.PI * 40;
    const dashArr = (avgScore / 100) * circumference;

    document.getElementById('sidebar-panels').innerHTML = `
        <!-- Top Performers -->
        <div class="card" style="animation-delay:0.1s;">
            <div class="card-header">
                <span class="card-title"><i data-lucide="trophy" style="width:16px;height:16px;color:#f59e0b;"></i> Top Performers</span>
            </div>
            <div class="card-body">
                <div class="top-performers">${topHtml || '<div class="text-muted" style="text-align:center;font-size:0.85rem;padding:1rem;">No top performers found</div>'}</div>
            </div>
        </div>

        <!-- Avg Score Donut -->
        <div class="card" style="animation-delay:0.18s;">
            <div class="card-header">
                <span class="card-title"><i data-lucide="pie-chart" style="width:16px;height:16px;color:var(--brand);"></i> Avg. Score ${state.projects && !state.projects.includes('All Projects') ? `<span style="font-size:0.7rem;font-weight:400;color:var(--text-muted);margin-left:auto;">(${state.projects.length} Selected)</span>` : ''}</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;align-items:center;gap:1.2rem;">
                <div class="donut-wrap">
                    <svg width="110" height="110" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" fill="none" stroke="#f1f5f9" stroke-width="10"/>
                        <circle cx="50" cy="50" r="40" fill="none" stroke="#7c3aed" stroke-width="10"
                            stroke-linecap="round"
                            stroke-dasharray="${dashArr.toFixed(1)} ${circumference.toFixed(1)}"
                            stroke-dashoffset="${circumference / 4}"
                            style="transition: stroke-dasharray 1s ease;"/>
                    </svg>
                    <div class="donut-center">
                        <div class="donut-pct">${avgScore}%</div>
                        <div class="donut-lbl">Overall</div>
                    </div>
                </div>
                <div class="stat-list" style="width:100%;">${statHtml}</div>
            </div>
        </div>
    `;

    if (window.lucide) lucide.createIcons();
}

/* ─────────────────────────────────────────
   MODAL — Employee Detail
   ───────────────────────────────────────── */
function openModal(empId) {
    const emp = MOCK_EMPLOYEES.find(e => e.id === empId);
    if (!emp) return;
    const g = getGrade(emp.score);

    const metrics = [
        { key: 'attendance',   label: 'Attendance',      icon: 'user-check', val: emp.attendance },
        { key: 'tasks',        label: 'Task Completion', icon: 'circle-check-big', val: emp.tasks },
    ];

    const metricRows = metrics.map(m => {
        const val = m.val;
        const color = progressColor(val);
        return `
            <div class="stat-row">
                <div class="stat-row-header">
                    <span class="stat-name" style="display:flex;align-items:center;gap:0.4rem;">
                        <i data-lucide="${m.icon}" style="width:14px;height:14px;color:${color};"></i>
                        ${m.label}
                    </span>
                    <span class="stat-score" style="color:${color};">${val}%</span>
                </div>
                <div class="progress-track" style="height:8px;">
                    <div class="progress-fill" style="--bar-width:${val}%;width:${val}%;background:${color};height:8px;"></div>
                </div>
            </div>
        `;
    }).join('');
    
    const projectsHtml = emp.projects.map((p, idx) => {
        const pColor = progressColor(p.score);
        const taskId = `task-list-${idx}`;
        return `
            <div style="border:1px solid var(--border); padding:1rem; border-radius:var(--radius-sm); margin-bottom:0.75rem; background:white;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <div style="font-weight:700; color:var(--text-primary); font-size:0.95rem; display:flex; align-items:center; gap:0.4rem;">
                        <i data-lucide="folder" style="width:16px;height:16px;color:var(--brand);"></i> ${p.name}
                    </div>
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <div style="font-weight:800; color:${pColor}; font-size:1.1rem;">${p.score}%</div>
                        <button onclick="toggleTaskList('${taskId}', this)" class="btn btn-outline" style="padding:0.2rem 0.5rem; font-size:0.7rem; height:auto;">
                            <i data-lucide="chevron-down" style="width:12px;height:12px;"></i> Details
                        </button>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.2rem;">Task Completion</div>
                        <div class="progress-bar-wrap">
                            <div class="progress-track"><div class="progress-fill" style="--bar-width:${p.tasks}%;width:${p.tasks}%;background:${progressColor(p.tasks)};"></div></div>
                            <span class="progress-pct" style="font-size:0.75rem;">${p.tasks}%</span>
                        </div>
                    </div>
                </div>

                <div id="${taskId}" class="modal-task-list" style="border-top:1px dashed var(--border); padding-top:0.75rem; margin-top:0.75rem; display:none;">
                    <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; margin-bottom:0.5rem; letter-spacing:0.04em;">Assigned Tasks Detail</div>
                    <div style="display:flex; flex-direction:column; gap:0.4rem;">
                        ${(p.tasks_list || []).map((t, tidx) => {
                            const statusColor = t.status === 'Completed' ? '#10b981' : (t.status === 'Incomplete' ? '#ef4444' : '#f59e0b');
                            const extId = `ext-hist-${idx}-${tidx}`;
                            return `
                                <div style="font-size:0.8rem; background:rgba(248,250,252,0.6); padding:0.6rem; border-radius:4px; border-left:3px solid ${statusColor}; display:flex; flex-direction:column; gap:0.2rem;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                        <span style="font-weight:600; color:var(--text-primary); line-height:1.2;">${t.desc}</span>
                                        <span style="font-size:0.7rem; font-weight:700; color:${statusColor}; text-transform:uppercase; background:white; padding:0.1rem 0.3rem; border-radius:3px; border:1px solid ${statusColor};">${t.status}</span>
                                    </div>
                                    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; color:var(--text-muted); font-size:0.75rem; margin-top:0.2rem;">
                                        <span><i data-lucide="clock" style="width:10px;height:10px;vertical-align:middle;"></i> Done in: <b>${t.duration}</b></span>
                                        <span style="cursor:pointer; text-decoration:underline dashed; color:var(--brand); font-weight:600;" onclick="toggleTaskList('${extId}')">
                                            <i data-lucide="repeat" style="width:10px;height:10px;vertical-align:middle;"></i> Extensions: <b>${t.extensions}</b>
                                        </span>
                                        <span><i data-lucide="calendar-days" style="width:10px;height:10px;vertical-align:middle;"></i> Created: <b>${t.created_at.split(' ')[0]}</b></span>
                                    </div>
                                    <div id="${extId}" style="display:none; font-size:0.75rem; background:#fff; padding:0.5rem; border:1px solid var(--border); border-radius:4px; margin-top:0.3rem;">
                                        <div style="font-weight:700; color:var(--text-muted); margin-bottom:0.5rem; font-size:0.65rem; text-transform:uppercase;">Extension History</div>
                                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                            ${(() => {
                                                try {
                                                    const hist = JSON.parse(t.extension_history || '[]');
                                                    if (!hist.length) return '<div style="color:var(--text-muted); font-style:italic;">No detailed history recorded.</div>';
                                                    return hist.map(h => `
                                                        <div style="border-left:2px solid var(--brand); padding-left:0.5rem; background:#f8fafc; padding:0.4rem; border-radius:3px;">
                                                            <div style="display:flex; justify-content:space-between; margin-bottom:0.1rem;">
                                                                <span style="font-weight:700; color:var(--brand);">Ext #${h.extension_number}</span>
                                                                <span style="font-size:0.65rem; color:var(--text-muted);">${h.extended_at}</span>
                                                            </div>
                                                            <div style="color:var(--text-secondary); font-size:0.7rem;">
                                                                Due: <span style="text-decoration:line-through; opacity:0.6;">${h.previous_due_date}</span> &rarr; <b>${h.new_due_date}</b>
                                                            </div>
                                                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:0.1rem;">
                                                                Added by: <b>${h.user_name}</b> (+${h.days_added} days)
                                                            </div>
                                                        </div>
                                                    `).join('');
                                                } catch(e) {
                                                    return `<div style="color:var(--text-muted); font-style:italic;">${t.extension_history || 'No detailed history found.'}</div>`;
                                                }
                                            })()}
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('') || '<div style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">No detailed tasks found.</div>'}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    document.getElementById('modal-backdrop').innerHTML = `
        <div class="modal-box">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div class="emp-avatar" style="background:${emp.avatar_color};width:44px;height:44px;font-size:1rem;">${initials(emp.name)}</div>
                    <div>
                        <div class="modal-title">${emp.name}</div>
                        <div style="font-size:0.82rem;color:var(--text-muted);">${emp.role} &bull; ${emp.dept}</div>
                    </div>
                </div>
                <button class="modal-close" id="modal-close-btn">
                    <i data-lucide="x" style="width:15px;height:15px;"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem;background:var(--brand-light);border-radius:var(--radius-sm);border:1px solid rgba(124,58,237,0.15);">
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;font-weight:600;">Overall Average Score</div>
                        <div style="font-size:2rem;font-weight:800;color:var(--brand);line-height:1.1;">${emp.score}%</div>
                    </div>
                    <span class="perf-badge ${g.cls}" style="font-size:0.85rem;padding:0.4rem 1rem;">${g.label}</span>
                </div>
                
                <h4 style="margin-top:0.5rem; font-size:0.9rem; color:var(--text-primary);">Core Metrics</h4>
                <div style="display:flex;flex-direction:column;gap:0.9rem;">
                    ${metricRows}
                </div>
                
                ${emp.projects && emp.projects.length > 0 ? `
                <h4 style="margin-top:1rem; font-size:0.9rem; color:var(--text-primary);">Project Performance</h4>
                <div>
                    ${projectsHtml}
                </div>
                ` : ''}
            </div>
        </div>
    `;

    document.getElementById('modal-backdrop').classList.remove('hidden');
    document.getElementById('modal-close-btn').addEventListener('click', closeModal);
    document.getElementById('modal-backdrop').addEventListener('click', e => { if (e.target.id === 'modal-backdrop') closeModal(); });
    if (window.lucide) lucide.createIcons();
}

function closeModal() {
    document.getElementById('modal-backdrop').classList.add('hidden');
    document.getElementById('modal-backdrop').innerHTML = '';
}

/* ─────────────────────────────────────────
   EXPORT CSV
   ───────────────────────────────────────── */
function exportCSV() {
    const data = filteredSorted();
    const header = ['Name', 'Role', 'Project Filter', 'Attendance %', 'Tasks %', 'Score %', 'Grade'];
    const rows = data.map(e => {
        let tasks = e.tasks;
        let score = e.score;
        const hasProjectFilter = state.projects && !state.projects.includes('All Projects');
        if (hasProjectFilter) {
            const sub = e.projects.filter(pr => state.projects.includes(pr.name));
            if (sub.length) {
                tasks = Math.round(sub.reduce((acc, curr) => acc + curr.tasks, 0) / sub.length);
                score = Math.round(sub.reduce((acc, curr) => acc + curr.score, 0) / sub.length);
            }
        }
        return [e.name, e.role, hasProjectFilter ? state.projects.join(';') : 'All Projects', e.attendance, tasks, score, getGrade(score).label];
    });
    const csv = [header, ...rows].map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `performance_report_${state.period.replace(/\s+/g, '_')}.csv`;
    a.click();
    showToast('CSV exported successfully!');
}

/* ─────────────────────────────────────────
   UPDATE ALL
   ───────────────────────────────────────── */
function updateAll() {
    renderMetrics();
    renderTable();
    renderSidebar();
}

/* ─────────────────────────────────────────
   INIT & FETCH
   ───────────────────────────────────────── */
async function loadPerformanceData() {
    try {
        let url = 'api/get_performance.php?period=' + encodeURIComponent(state.period);
        
        // Month/Year derived from header
        const startDate = `${state.year}-${String(state.month).padStart(2, '0')}-01`;
        const lastDay = new Date(state.year, state.month, 0).getDate();
        const endDate = `${state.year}-${String(state.month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
        
        url += '&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
        
        if (state.week && state.week !== 'All Weeks') {
            const parts = state.week.split('_');
            url += '&start_date=' + encodeURIComponent(parts[0]) + '&end_date=' + encodeURIComponent(parts[1]);
        }
        
        const response = await fetch(url);
        const res = await response.json();
        
        if (res.success) {
            MOCK_EMPLOYEES = res.employees;
            MOCK_PROJECTS = ['All Projects', ...res.projects];
            
            // Extract unique roles
            const roles = new Set(MOCK_EMPLOYEES.map(e => e.role));
            ROLES = ['All', ...roles];
            
            renderFilters();
            updateAll();
        } else {
            showToast(res.message || 'Error loading performance data', 'error');
        }
    } catch (err) {
        showToast('Failed to connect to backend', 'error');
        console.error(err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Sync Header selects with initial state
    const monthSel = document.getElementById('header-month-select');
    const yearSel = document.getElementById('header-year-select');
    if (monthSel) {
        monthSel.value = state.month;
        monthSel.addEventListener('change', (e) => {
            state.month = parseInt(e.target.value);
            CURRENT_WEEKS = generateCurrentMonthWeeks(state.year, state.month);
            renderFilters();
            loadPerformanceData();
        });
    }
    if (yearSel) {
        yearSel.value = state.year;
        yearSel.addEventListener('change', (e) => {
            state.year = parseInt(e.target.value);
            CURRENT_WEEKS = generateCurrentMonthWeeks(state.year, state.month);
            renderFilters();
            loadPerformanceData();
        });
    }

    // Initial UI state (empty)
    renderFilters(); 
    updateAll();
    
    // Load real data
    loadPerformanceData();

    if (window.lucide) lucide.createIcons();
    // Re-render icons after sidebar loads (sidebar-loader fires async)
    setTimeout(() => { if (window.lucide) lucide.createIcons(); }, 300);
});
