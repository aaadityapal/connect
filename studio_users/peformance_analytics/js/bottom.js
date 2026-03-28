// ─── Bottom: Custom Range Dropdown + Search ────────────
(function initRangeDropdown() {
    const wrap     = document.getElementById('range-select');
    const trigger  = document.getElementById('range-trigger');
    const dropdown = document.getElementById('range-dropdown');
    const valText  = document.getElementById('range-value-text');
    const hidden   = document.getElementById('completion-range');

    if (!wrap || !trigger || !dropdown) return;

    // Toggle open / close on trigger click
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        wrap.classList.toggle('open');
    });

    // Item selection
    dropdown.querySelectorAll('.range-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.stopPropagation();

            // Mark active
            dropdown.querySelectorAll('.range-item').forEach(function(i) {
                i.classList.remove('active');
            });
            item.classList.add('active');

            // Update visible label + hidden input
            if (valText)  valText.textContent = item.textContent.trim();
            if (hidden)   hidden.value        = item.dataset.value;

            // Close
            wrap.classList.remove('open');

            // Callback
            filterCompletions(item.dataset.value);
        });
    });

    // Close when clicking outside the dropdown
    document.addEventListener('click', function(e) {
        if (!wrap.contains(e.target)) {
            wrap.classList.remove('open');
        }
    });
})();

// ─── Project search ─────────────────────────────────────
window.filterProjects = function(query) {
    const q = (query || '').trim().toLowerCase();
    document.querySelectorAll('.project-row').forEach(function(row) {
        const name = (row.dataset.name || '').toLowerCase();
        row.style.display = name.includes(q) ? '' : 'none';
    });
};

function formatDaysAgo(dateStr) {
    if(!dateStr) return '';
    const diff = new Date() - new Date(dateStr);
    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
    if(d === 0) return 'Today';
    if(d === 1) return 'Yesterday';
    return d + ' days ago';
}

function getStatusBadge(status) {
    const map = {
        'Completed': '<span style="color:#10b981;background:rgba(16,185,129,0.1);padding:4px 8px;border-radius:4px;font-size:12px;">Completed</span>',
        'In Progress': '<span style="color:#3b82f6;background:rgba(59,130,246,0.1);padding:4px 8px;border-radius:4px;font-size:12px;">In Progress</span>',
        'Pending': '<span style="color:#f59e0b;background:rgba(245,158,11,0.1);padding:4px 8px;border-radius:4px;font-size:12px;">Pending</span>',
        'Not Started': '<span style="color:#64748b;background:rgba(100,116,139,0.1);padding:4px 8px;border-radius:4px;font-size:12px;">Not Started</span>'
    };
    return map[status] || status;
}

// ─── Render Projects and Tasks ──────────────────────────
(function renderBottom() {
    const tasks = window.apiData ? window.apiData.tasks : [];
    
    const pPanel = document.getElementById('projects-panel');
    const cPanel = document.getElementById('completions-panel');
    
    if (tasks.length > 0) {
        document.getElementById('projects-empty').style.display = 'none';
        
        // Render all projects
        let pHTML = '<div class="projects-list" style="margin-top:16px;">';
        tasks.forEach(t => {
            pHTML += `<div class="project-row" data-name="${t.project_name} ${t.task_name}" style="padding:12px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-weight:600;color:#1e293b;">${t.project_name}</div>
                    <div style="font-size:13px;color:#64748b;margin-top:4px;">${t.task_name} • Due: ${t.due_date}</div>
                </div>
                <div>${getStatusBadge(t.status)}</div>
            </div>`;
        });
        pHTML += '</div>';
        pPanel.insertAdjacentHTML('beforeend', pHTML);
        
        // Render completions
        document.getElementById('completions-empty').style.display = 'none';
        
        const completions = tasks.filter(t => t.status === 'Completed');
        let cHTML = `<div class="completions-list" id="completions-list" style="margin-top:16px;">`;
        completions.forEach(t => {
            const daysAgoText = formatDaysAgo(t.completed_date);
            cHTML += `<div class="completion-row" data-days="${Math.floor((new Date() - new Date(t.completed_date)) / (1000 * 60 * 60 * 24))}" style="padding:12px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-weight:600;color:#1e293b;">${t.task_name}</div>
                    <div style="font-size:13px;color:#64748b;margin-top:4px;">${t.project_name}</div>
                </div>
                <div style="font-size:13px;color:#64748b;">${daysAgoText}</div>
            </div>`;
        });
        cHTML += '</div>';
        cPanel.insertAdjacentHTML('beforeend', cHTML);
    }
})();

// ─── Completions range filter ────────────────────────────
window.filterCompletions = function(days) {
    const d = parseInt(days, 10);
    document.querySelectorAll('.completion-row').forEach(row => {
        const rowDays = parseInt(row.dataset.days, 10);
        row.style.display = rowDays <= d ? '' : 'none';
    });
};

filterCompletions(document.getElementById('completion-range')?.value || 30);
