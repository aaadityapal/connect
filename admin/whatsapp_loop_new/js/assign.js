/* ═══════════════════════════════════════════
   Assign Loop Section – JavaScript
═══════════════════════════════════════════ */

let ALL_SUBSCRIPTIONS = [];
let ALL_LOOPS_ASSIGN  = [];
let ALL_CLIENTS_ASSIGN= [];
let ASSIGNMENT_MAP = new Map();

// ── IST Timezone Helpers ──────────────────────────────────────────────────
// MySQL DATETIME strings have no timezone; append +05:30 so JS parses as IST
function parseISTDate(dbStr) {
  if (!dbStr) return null;
  // e.g. "2026-05-18 07:42:19" → "2026-05-18T07:42:19+05:30"
  return new Date(String(dbStr).replace(' ', 'T') + '+05:30');
}

const IST_FMT_SHORT = { timeZone: 'Asia/Kolkata', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
const IST_FMT_FULL  = { timeZone: 'Asia/Kolkata' };

function formatIST(date, opts) {
  if (!date) return '—';
  return date.toLocaleString('en-IN', opts || IST_FMT_SHORT);
}
// ─────────────────────────────────────────────────────────────────────────

async function initAssign() {
  await loadAssignData();
}

async function loadAssignData() {
  try {
    const [subRes, loopRes, clientRes] = await Promise.all([
      fetch(API_BASE + 'get_master_loop_assignments.php'),
      fetch(API_BASE + 'get_master_loops.php'),
      fetch(API_BASE + 'get_clients.php')
    ]);

    const subData = await subRes.json();
    ALL_SUBSCRIPTIONS  = subData.success ? (subData.data || []) : [];
    const loopData     = await loopRes.json();
    ALL_LOOPS_ASSIGN   = loopData.success ? (loopData.data || []) : [];
    ALL_CLIENTS_ASSIGN = await clientRes.json();

    populateAssignDropdowns();
    populateAssignYearFilter(ALL_SUBSCRIPTIONS);
    renderAssignTable(ALL_SUBSCRIPTIONS);
    updateAssignStats();
  } catch (err) {
    console.error('loadAssignData error:', err);
    showToast('Failed to load assignment data', 'error');
  }
}

function populateAssignYearFilter(subs) {
  const yearFilter = document.getElementById('assign-year-filter');
  if (!yearFilter) return;

  const prev = yearFilter.value || '';
  const years = [...new Set((subs || [])
    .map(s => parseISTDate(s.assigned_at))
    .filter(Boolean)
    .map(d => d.getFullYear()))]
    .sort((a, b) => b - a);

  yearFilter.innerHTML = '<option value="">All Years</option>';
  years.forEach(y => {
    yearFilter.innerHTML += `<option value="${y}">${y}</option>`;
  });

  if (prev && years.includes(parseInt(prev, 10))) {
    yearFilter.value = prev;
  }
}

function getFilterValue(...ids) {
  for (const id of ids) {
    const el = document.getElementById(id);
    if (el && typeof el.value === 'string' && el.value.trim() !== '') {
      return el.value;
    }
  }
  return '';
}

function populateAssignDropdowns() {
  renderAssignClientChecklist();

  // Loop dropdown
  const lSel = document.getElementById('assign-loop-select');
  const lFilter = document.getElementById('assign-loop-filter');
  if (lSel) {
    lSel.innerHTML = '<option value="">-- Choose Loop --</option>';
    if (lFilter) lFilter.innerHTML = '<option value="">All Loops</option>';
    ALL_LOOPS_ASSIGN.forEach(l => {
      lSel.innerHTML  += `<option value="${l.id}">${l.name}</option>`;
      if (lFilter) lFilter.innerHTML += `<option value="${l.id}">${l.name}</option>`;
    });
    lSel.onchange = previewLoopTimeline;
  }
}

function renderAssignClientChecklist() {
  const list = document.getElementById('assign-client-checklist');
  if (!list) return;

  if (!Array.isArray(ALL_CLIENTS_ASSIGN) || ALL_CLIENTS_ASSIGN.length === 0) {
    list.innerHTML = '<div class="assign-client-empty">No clients found.</div>';
    updateAssignClientSelectedCount();
    return;
  }

  list.innerHTML = ALL_CLIENTS_ASSIGN.map(c => `
    <label class="assign-client-option">
      <input type="checkbox" class="assign-client-checkbox" value="${c.id}" onchange="updateAssignClientSelectedCount()" />
      <span class="assign-client-meta">
        <span class="assign-client-name">${c.name}</span>
        <span class="assign-client-phone">${c.phone}</span>
      </span>
    </label>
  `).join('');

  updateAssignClientSelectedCount();
}

function getSelectedAssignClientIds() {
  return Array.from(document.querySelectorAll('.assign-client-checkbox:checked'))
    .map(cb => parseInt(cb.value, 10))
    .filter(Number.isFinite);
}

function updateAssignClientSelectedCount() {
  const countEl = document.getElementById('assign-client-selected-count');
  if (!countEl) return;
  const selected = getSelectedAssignClientIds().length;
  countEl.textContent = `${selected} selected`;
}

function toggleAllAssignClients(checked) {
  document.querySelectorAll('.assign-client-checkbox').forEach(cb => {
    cb.checked = !!checked;
  });
  updateAssignClientSelectedCount();
}

async function previewLoopTimeline() {
  const loopId  = parseInt(document.getElementById('assign-loop-select').value);
  const preview = document.getElementById('assign-loop-preview');
  const stepsEl = document.getElementById('assign-loop-preview-steps');
  if (!loopId || !preview || !stepsEl) return;

  try {
    const res = await fetch(API_BASE + `get_master_loop.php?id=${loopId}`);
    const data = await res.json();
    const loop = data.success ? data.data : null;
    if (!loop || !loop.steps || loop.steps.length === 0) {
      preview.style.display = 'none';
      return;
    }

    preview.style.display = 'block';
    let runDate = new Date();
    stepsEl.innerHTML = loop.steps.map((s, i) => {
      const val  = parseInt(s.delay_value) || 0;
      const unit = s.delay_unit || 'days';
      if (val > 0) runDate = new Date(runDate.getTime());
      if (unit === 'minutes') runDate.setMinutes(runDate.getMinutes() + val);
      else if (unit === 'hours') runDate.setHours(runDate.getHours() + val);
      else if (unit === 'weeks') runDate.setDate(runDate.getDate() + val * 7);
      else if (unit === 'months') runDate.setMonth(runDate.getMonth() + val);
      else runDate.setDate(runDate.getDate() + val);

      const dateStr = runDate.toLocaleDateString('en-US', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' });
      const media   = s.media_filename ? `<span style="color:var(--green);margin-left:4px;">${s.media_filename}</span>` : '';
      return `
        <div style="display:flex;gap:8px;align-items:flex-start;">
          <span style="background:var(--green-light);color:var(--green-dark);border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;flex-shrink:0;margin-top:1px;">${i+1}</span>
          <div>
            <div style="font-weight:600;color:var(--text);">${s.template_key}</div>
            <div style="color:var(--text-muted);">${dateStr}${media}</div>
          </div>
        </div>`;
    }).join('');
  } catch (err) {
    preview.style.display = 'none';
  }
}

async function assignLoopToClient() {
  const clientIds = getSelectedAssignClientIds();
  const loopId= document.getElementById('assign-loop-select').value;

  if (clientIds.length === 0 || !loopId) {
    showToast('Please select at least one client and one loop.', 'error'); return;
  }

  try {
    const results = await Promise.all(clientIds.map(async (id) => {
      const res = await fetch(API_BASE + 'assign_master_loop.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ master_loop_id: parseInt(loopId, 10), client_id: id })
      });
      const data = await res.json();
      return { id, success: !!data.success, error: data.error || '' };
    }));

    const successCount = results.filter(r => r.success).length;
    const failCount = results.length - successCount;

    if (successCount > 0) {
      showToast(`✅ Enrolled ${successCount} client(s).${failCount ? ` Failed: ${failCount}` : ''}`, failCount ? 'info' : 'success');
      if (failCount) {
        const uniqueErrors = [...new Set(results.filter(r => !r.success).map(r => r.error).filter(Boolean))];
        const shortMsg = uniqueErrors.length > 0 ? uniqueErrors.slice(0, 2).join(' | ') : 'Some clients could not be enrolled.';
        showToast(shortMsg, 'error');
      }
      toggleAllAssignClients(false);
      await loadAssignData();
    } else {
      const firstErr = results.find(r => !r.success)?.error;
      showToast(firstErr || 'Enrollment failed', 'error');
    }
  } catch (err) {
    showToast('Network error during enrollment', 'error');
  }
}

function renderAssignTable(subs) {
  const tbody = document.getElementById('assign-table-body');
  if (!tbody) return;

  if (!subs || subs.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--text-muted);">No enrollments found. Enroll a client above to get started.</td></tr>`;
    return;
  }

  ASSIGNMENT_MAP = new Map(subs.map(s => [String(s.id), s]));

  tbody.innerHTML = subs.map(sub => {
    const totalSteps   = sub.total_steps   || 0;
    const doneSteps    = sub.done_steps    || 0;
    const pct          = totalSteps > 0 ? Math.round((doneSteps / totalSteps) * 100) : 0;
    const nextSend     = sub.next_send_at ? formatIST(parseISTDate(sub.next_send_at), IST_FMT_SHORT) : '—';
    const statusColor  = { Assigned:'var(--green)', Completed:'var(--blue)' }[sub.status] || 'var(--text-muted)';

    return `
      <tr style="border-top:1px solid var(--border);" data-assign-id="${sub.id}" onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background=''">
        <td style="padding:14px 16px;">
          <div style="font-weight:700;">${sub.client_name}</div>
          <div style="font-size:.75rem;color:var(--text-muted);">${sub.client_phone}</div>
        </td>
        <td style="padding:14px 16px;color:var(--text);">${sub.master_loop_name}</td>
        <td style="padding:14px 16px;min-width:140px;">
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="flex:1;height:6px;background:var(--border);border-radius:3px;">
              <div style="height:6px;background:${statusColor};border-radius:3px;width:${pct}%;transition:width .4s;"></div>
            </div>
            <span style="font-size:.75rem;color:var(--text-muted);flex-shrink:0;">${doneSteps}/${totalSteps}</span>
          </div>
        </td>
        <td style="padding:14px 16px;font-size:.82rem;color:var(--text-muted);">${nextSend}</td>
        <td style="padding:14px 16px;">
          <span style="background:${statusColor}22;color:${statusColor};font-size:.72rem;font-weight:700;padding:3px 8px;border-radius:20px;">${sub.status}</span>
        </td>
        <td style="padding:14px 16px;">
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button onclick="event.stopPropagation();deleteMasterLoopAssignment(${sub.id},'${sub.client_name}')" title="Delete assignment" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:.75rem;color:#b91c1c;">
              🗑 Delete
            </button>
          </div>
        </td>
      </tr>`;
  }).join('');

  tbody.querySelectorAll('tr[data-assign-id]').forEach(row => {
    row.addEventListener('click', () => {
      const id = row.dataset.assignId;
      const assign = ASSIGNMENT_MAP.get(String(id));
      if (assign) openAssignTimeline(assign);
    });
  });
}

function closeAssignTimeline() {
  const modal = document.getElementById('assign-timeline-modal');
  if (modal) modal.classList.remove('show');
}

function buildTimelineDates(assignedAt, steps) {
  // Parse the DB timestamp as IST (not UTC)
  let runDate = parseISTDate(assignedAt) || new Date();
  return steps.map((s) => {
    const val  = parseInt(s.delay_value) || 0;
    const unit = s.delay_unit || 'days';
    if (val > 0) runDate = new Date(runDate.getTime());
    if (unit === 'minutes') runDate.setMinutes(runDate.getMinutes() + val);
    else if (unit === 'hours') runDate.setHours(runDate.getHours() + val);
    else if (unit === 'weeks') runDate.setDate(runDate.getDate() + val * 7);
    else if (unit === 'months') runDate.setMonth(runDate.getMonth() + val);
    else runDate.setDate(runDate.getDate() + val);
    return runDate;
  });
}

async function openAssignTimeline(assign) {
  const modal = document.getElementById('assign-timeline-modal');
  const title = document.getElementById('assign-modal-title');
  const sub = document.getElementById('assign-modal-sub');
  const stats = document.getElementById('assign-modal-stats');
  const body = document.getElementById('assign-modal-body');
  if (!modal || !title || !sub || !stats || !body) return;

  modal.classList.add('show');
  title.textContent = `${assign.client_name} - ${assign.master_loop_name}`;
  sub.textContent = `Phone: ${assign.client_phone}`;
  body.innerHTML = '<div class="assign-modal-loading">Loading timeline...</div>';

  try {
    const res = await fetch(API_BASE + `get_master_loop.php?id=${assign.master_loop_id}`);
    const data = await res.json();
    const loop = data.success ? data.data : null;
    const steps = loop && Array.isArray(loop.steps) ? loop.steps : [];

    if (steps.length === 0) {
      body.innerHTML = '<div class="assign-modal-loading">No steps found for this loop.</div>';
      stats.textContent = '';
      return;
    }

    const assignedAt = assign.assigned_at || new Date().toISOString();
    const dates = buildTimelineDates(assignedAt, steps);
    const sentCount = Math.max((parseInt(assign.current_step_order) || 1) - 1, 0);
    stats.textContent = `Assigned: ${formatIST(parseISTDate(assignedAt), IST_FMT_FULL)} | Sent: ${sentCount} of ${steps.length}`;

    body.innerHTML = steps.map((s, idx) => {
      const scheduled = dates[idx];
      const dateStr = formatIST(scheduled, IST_FMT_SHORT);
      const badge = idx + 1 < (parseInt(assign.current_step_order) || 1)
        ? '<span class="assign-timeline-badge sent">Sent</span>'
        : (idx + 1 === (parseInt(assign.current_step_order) || 1)
          ? '<span class="assign-timeline-badge next">Next</span>'
          : '<span class="assign-timeline-badge">Upcoming</span>');

      return `
        <div class="assign-timeline-item">
          <div class="assign-timeline-step">${idx + 1}</div>
          <div>
            <div class="assign-timeline-title">${s.template_key}</div>
            <div class="assign-timeline-meta">${dateStr}</div>
          </div>
          ${badge}
        </div>
      `;
    }).join('');
  } catch (err) {
    body.innerHTML = '<div class="assign-modal-loading">Failed to load timeline.</div>';
  }
}

function filterAssignTable() {
  const search  = getFilterValue('assign-table-search', 'assign-search').toLowerCase();
  const status  = document.getElementById('assign-status-filter')?.value || '';
  const loopId  = document.getElementById('assign-loop-filter')?.value || '';
  const month   = parseInt(document.getElementById('assign-month-filter')?.value || '', 10) || 0;
  const year    = parseInt(document.getElementById('assign-year-filter')?.value || '', 10) || 0;

  const filtered = ALL_SUBSCRIPTIONS.filter(sub => {
    const matchSearch = !search || sub.client_name.toLowerCase().includes(search) || sub.client_phone.includes(search);
    const matchStatus = !status || sub.status === status;
    const matchLoop   = !loopId || String(sub.master_loop_id) === loopId;

    const assignedDate = parseISTDate(sub.assigned_at);
    const matchMonth = !month || (assignedDate && (assignedDate.getMonth() + 1) === month);
    const matchYear = !year || (assignedDate && assignedDate.getFullYear() === year);

    return matchSearch && matchStatus && matchLoop && matchMonth && matchYear;
  });

  renderAssignTable(filtered);
}

function updateAssignStats() {
  const activeEl    = document.getElementById('assign-stat-active');
  const completedEl = document.getElementById('assign-stat-completed');
  const msgEl       = document.getElementById('assign-stat-messages');

  if (activeEl)    activeEl.textContent    = ALL_SUBSCRIPTIONS.length;
  if (completedEl) completedEl.textContent = ALL_SUBSCRIPTIONS.filter(s => s.status === 'Completed').length;
  if (msgEl)       msgEl.textContent       = ALL_SUBSCRIPTIONS.reduce((sum, s) => sum + (parseInt(s.done_steps) || 0), 0);
}

async function deleteMasterLoopAssignment(assignId, clientName) {
  if (!confirm(`Delete assignment for ${clientName}?`)) return;

  try {
    const res = await fetch(API_BASE + 'delete_master_loop_assignment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: assignId })
    });
    const data = await res.json();
    if (data.success) {
      showToast('Assignment deleted.', 'success');
      loadAssignData();
    } else {
      showToast(data.error || 'Delete failed', 'error');
    }
  } catch (err) {
    showToast('Delete failed', 'error');
  }
}

window.initAssign = initAssign;
window.closeAssignTimeline = closeAssignTimeline;
window.updateAssignClientSelectedCount = updateAssignClientSelectedCount;
window.toggleAllAssignClients = toggleAllAssignClients;
