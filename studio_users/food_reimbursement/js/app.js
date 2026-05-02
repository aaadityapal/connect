/**
 * =====================================================
 * FOOD REIMBURSEMENT MODULE — js/app.js
 * =====================================================
 * Fetches attendance rows where user punched out after
 * 10:00 PM and displays them as eligible food-reimbursement
 * claims.
 */

(function () {
  'use strict';

  /* ── State ─────────────────────────────────────────────── */
  let allClaims    = [];
  let filteredData = [];
  let currentPage  = 1;
  const PAGE_SIZE  = 10;

  /* ── DOM References ─────────────────────────────────────── */
  const claimsTableBody = document.getElementById('claimsTableBody');
  const tableEmpty      = document.getElementById('tableEmpty');
  const tableLoading    = document.getElementById('tableLoading');
  const tableFooter     = document.getElementById('tableFooter');
  const tableCount      = document.getElementById('tableCount');
  const pagination      = document.getElementById('pagination');
  const tableSearch     = document.getElementById('tableSearch');

  const statTotal    = document.getElementById('statTotal');
  const statPending  = document.getElementById('statPending');
  const statApproved = document.getElementById('statApproved');

  const filterStatus   = null; // removed from UI
  const filterMonth    = document.getElementById('filterMonth');
  const filterCategory = null; // removed from UI

  // View modal
  const viewModalBackdrop = document.getElementById('viewModalBackdrop');
  const viewModalBody     = document.getElementById('viewModalBody');

  // Send modal
  const sendModalBackdrop = document.getElementById('sendModalBackdrop');
  const sendModalSummary  = document.getElementById('sendModalSummary');
  const sendNote          = document.getElementById('sendNote');
  let   pendingSendId     = null;

  // Toast
  const toast = document.getElementById('toast');

  /* ── Utility: Toast ──────────────────────────────────────── */
  function showToast(msg, type = 'success') {
    toast.textContent = msg;
    toast.className   = `toast ${type} show`;
    setTimeout(() => toast.classList.remove('show'), 3500);
  }

  /* ── Utility: Format Duration ────────────────────────────── */
  function fmtLateMinutes(mins) {
    if (!mins || mins <= 0) return '—';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0) return `${h}h ${m}m past 9 PM`;
    return `${m}m past 9 PM`;
  }

  /* ── Utility: Status Pill ────────────────────────────────── */
  function statusPill(value, type) {
    if (!value) {
      return `<span class="status-pill status-pill--pending">Pending</span>`;
    }

    const v = String(value).toLowerCase().trim();

    // Colour map shared across all status types
    const map = {
      draft:       { cls: 'pending',   label: 'Draft'       },
      expired:     { cls: 'rejected',  label: 'Expired'     },
      pending:     { cls: 'pending',   label: 'Pending'     },
      submitted:   { cls: 'submitted', label: 'Submitted'   },
      resubmitted: { cls: 'review',    label: 'Resubmitted' },
      approved:    { cls: 'approved',  label: 'Approved'    },
      rejected:    { cls: 'rejected',  label: 'Rejected'    },
      paid:        { cls: 'paid',      label: 'Paid'        },
      unpaid:      { cls: 'pending',   label: 'Unpaid'      },
      review:      { cls: 'review',    label: 'In Review'   },
      notsent:     { cls: 'pending',   label: '—'           }
    };

    const m = map[v] || { cls: 'pending', label: value };
    // Mute visual weight for 'Not Sent' / draft dashes
    if (v === 'notsent') return `<span style="color:var(--text-muted);font-weight:600;">—</span>`;

    return `<span class="status-pill status-pill--${m.cls}">${m.label}</span>`;
  }

  /* ── Utility: Final Status (derived) ─────────────────────── */
  /**
   * Computes the overall claim status from both HR and Manager decisions.
   *
   * Rules (in priority order):
   *  1. Either party rejected  → "Rejected"
   *  2. Both approved          → "Approved"
   *  3. One approved, one pending → "In Review"
   *  4. Neither acted yet      → "Pending"
   */
  function isExpired(dateStr) {
      if (!dateStr) return false;
      const attDate = new Date(dateStr);
      // Strip time from today to get a pure day-level difference
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      attDate.setHours(0, 0, 0, 0);
      
      const diffTime = today - attDate;
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)); 
      return diffDays > 15;
  }

  function finalStatus(hrStatus, mgrStatus, claimStatus, resubmitCount, attDate) {
    const hr  = (hrStatus  || '').toLowerCase().trim();
    const mgr = (mgrStatus || '').toLowerCase().trim();
    const clm = (claimStatus || '').toLowerCase().trim();

    if (!clm || clm === 'draft') {
        if (isExpired(attDate)) return 'expired';
        return 'draft';
    }

    if (hr === 'rejected' || mgr === 'rejected') return 'rejected';
    if (hr === 'approved' && mgr === 'approved') return 'approved';
    if (hr === 'approved' || mgr === 'approved') return 'review';
    
    if (clm === 'submitted') {
        return (resubmitCount > 0) ? 'resubmitted' : 'submitted';
    }
    return 'pending';
  }

  /* ── Utility: Punch-out time colour ─────────────────────── */
  function punchOutTag(timeStr) {
    if (!timeStr) return '—';
    // timeStr is "HH:MM:SS"
    const h = parseInt(timeStr.split(':')[0], 10);
    let cls = 'clr-late-mild';
    if (h >= 23)      cls = 'clr-late-severe';
    else if (h >= 22) cls = 'clr-late-high';
    return `<span class="punch-time-tag ${cls}">${timeStr.substring(0,5)}</span>`;
  }

  /* ── Load Claims from API ────────────────────────────────── */
  function loadClaims() {
    tableLoading.style.display = 'flex';
    tableEmpty.style.display   = 'none';
    tableFooter.style.display  = 'none';
    claimsTableBody.innerHTML  = '';

    const month = filterMonth.value || '';
    const url   = `api/get_eligible_dates.php${month ? '?month=' + encodeURIComponent(month) : ''}`;

    fetch(url)
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(data => {
        tableLoading.style.display = 'none';

        if (!data.success) {
          showToast(data.message || 'Failed to load data.', 'error');
          tableEmpty.style.display = 'flex';
          return;
        }

        allClaims = data.data || [];
        applyFilters();
        if (window.lucide) lucide.createIcons();
      })
      .catch(err => {
        tableLoading.style.display = 'none';
        tableEmpty.style.display   = 'flex';
        showToast('Could not load attendance data.', 'error');
        console.error('[FoodReimbursement]', err);
      });
  }

  function updateSummaryCards() {
    let unsubmittedCount = 0;
    let unsubmittedAmt = 0;
    let paidAmt = 0;
    let unpaidAmt = 0;

    filteredData.forEach(c => {
        // Fallback to price_per_meal if amount is not explicitly set by the user
        const amt = parseFloat(c.amount || c.price_per_meal || 100);
        const limit = parseFloat(c.price_per_meal || 100);

        if (!c.claim_status || c.claim_status === 'draft') {
            if (!isExpired(c.date)) {
                unsubmittedCount++;
                unsubmittedAmt += limit;
            }
        } else if (c.payment_status === 'paid') {
            paidAmt += amt;
        } else {
            // Submitted but not paid yet
            unpaidAmt += amt;
        }
    });

    animateCounter(document.getElementById('statUnsubmitted'), unsubmittedCount);
    animateCounter(document.getElementById('statUnsubmittedAmt'), unsubmittedAmt, true);
    animateCounter(document.getElementById('statPaidAmt'), paidAmt, true);
    animateCounter(document.getElementById('statUnpaidAmt'), unpaidAmt, true);
  }

  function animateCounter(el, target, isCurrency = false) {
    if (!el) return;
    const duration = 600;
    const start    = Date.now();
    const tick = () => {
      const elapsed  = Date.now() - start;
      const progress = Math.min(elapsed / duration, 1);
      const current = target * progress;
      if (isCurrency) {
          el.textContent = '₹' + current.toFixed(2);
      } else {
          el.textContent = Math.round(current);
      }
      if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  /* ── Apply Filters & Search ──────────────────────────────── */
  function applyFilters() {
    const searchVal = tableSearch.value.trim().toLowerCase();

    filteredData = allClaims.filter(c => {
      if (!searchVal) return true;
      const haystack = [c.date_fmt, c.punch_in_fmt, c.punch_out_fmt, c.work_report].join(' ').toLowerCase();
      return haystack.includes(searchVal);
    });

    currentPage = 1;
    renderTable();
    updateSummaryCards();
  }

  /* ── Render Table ────────────────────────────────────────── */
  function renderTable() {
    claimsTableBody.innerHTML = '';

    if (filteredData.length === 0) {
      tableEmpty.style.display  = 'flex';
      tableFooter.style.display = 'none';
      return;
    }

    tableEmpty.style.display  = 'none';
    tableFooter.style.display = 'flex';

    const totalPages = Math.ceil(filteredData.length / PAGE_SIZE);
    const start      = (currentPage - 1) * PAGE_SIZE;
    const pageData   = filteredData.slice(start, start + PAGE_SIZE);

    pageData.forEach((c, idx) => {
      const punchOutRaw  = c.punch_out || '';
      const punchOutHour = parseInt((punchOutRaw || '00:00:00').split(':')[0], 10);

      // Row highlight class based on how late
      let rowClass = '';
      if (punchOutHour >= 23)      rowClass = 'row-severe';
      else                         rowClass = 'row-mild';

      const tr = document.createElement('tr');
      tr.className = rowClass;
      tr.innerHTML = `
        <td style="font-weight:600;color:var(--text-muted);">${start + idx + 1}</td>
        <td>
          <div style="font-weight:600;color:var(--text-primary);">${escHtml(c.date_fmt)}</div>
          <div style="font-size:0.74rem;color:var(--text-muted);">${getDayName(c.date)}</div>
        </td>
        <td>
          <span class="time-badge time-out">
            <i data-lucide="log-out" style="width:11px;height:11px;"></i>
            ${escHtml(c.punch_out_fmt)}
          </span>
        </td>
        <td>${statusPill(c.punch_out_fmt ? finalStatus(c.hr_status, c.manager_status, c.claim_status, c.resubmit_count, c.date) : null, 'status')}</td>
        <td>${statusPill((!c.claim_status || c.claim_status === 'draft') ? 'notsent' : c.hr_status,       'hr')}</td>
        <td>${statusPill((!c.claim_status || c.claim_status === 'draft') ? 'notsent' : c.manager_status,  'manager')}</td>
        <td>${statusPill((!c.claim_status || c.claim_status === 'draft') ? 'notsent' : c.payment_status,  'payment')}</td>
        <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-secondary);" title="${escHtml(c.work_report || '')}">
          ${c.work_report ? escHtml(c.work_report) : '<span style="color:var(--text-muted);font-size:0.8rem;">—</span>'}
        </td>
        <td>
          <div class="action-btns">
            <button class="icon-btn" title="View Details" data-action="view" data-id="${c.id}">
              <i data-lucide="eye" style="width:13px;height:13px;"></i>
            </button>
            ${(isExpired(c.date) && (!c.claim_status || c.claim_status === 'draft' || c.manager_status === 'rejected' || c.hr_status === 'rejected'))
              ? `<button class="icon-btn" title="Claim expired (past 15 days)" disabled style="opacity:0.35;cursor:not-allowed;">
                   <i data-lucide="ban" style="width:13px;height:13px;color:var(--clr-rose);"></i>
                 </button>`
              : (c.manager_status === 'rejected' || c.hr_status === 'rejected') 
              ? `<button class="icon-btn" title="${(c.resubmit_count < 3) ? 'Resubmit Claim' : 'Maximum resubmissions reached'}" data-action="resubmit" data-id="${c.id}" ${(c.resubmit_count >= 3) ? 'disabled style="opacity:0.35;cursor:not-allowed;"' : 'style="color:var(--clr-brand);"'}>
                   <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i>
                 </button>`
              : `
                <button class="icon-btn send" title="${(c.claim_status === 'submitted') ? 'Already Sent' : 'Send Claim'}" ${c.claim_status === 'submitted' ? 'disabled style="opacity:0.35;cursor:not-allowed;"' : `data-action="send" data-id="${c.id}"`}>
                  <i data-lucide="send" style="width:13px;height:13px;"></i>
                </button>
                ${canEdit(c)
                  ? `<button class="icon-btn edit" title="Edit Claim" data-action="edit" data-id="${c.id}">
                       <i data-lucide="pencil" style="width:13px;height:13px;"></i>
                     </button>`
                  : `<button class="icon-btn edit" title="${editLockReason(c)}" disabled style="opacity:0.35;cursor:not-allowed;">
                       <i data-lucide="lock" style="width:13px;height:13px;"></i>
                     </button>`
                }
                ${canUndo(c)
                  ? `<button class="icon-btn undo" title="Undo Submission" data-action="undo" data-id="${c.id}">
                       <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
                     </button>`
                  : `<button class="icon-btn undo" title="${undoLockReason(c)}" disabled style="opacity:0.35;cursor:not-allowed;">
                       <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
                     </button>`
                }
              `
            }
          </div>
        </td>
      `;
      claimsTableBody.appendChild(tr);
    });

    tableCount.textContent = `Showing ${start + 1}–${Math.min(start + pageData.length, filteredData.length)} of ${filteredData.length} night${filteredData.length !== 1 ? 's' : ''}`;
    renderPagination(totalPages);

    if (window.lucide) lucide.createIcons();
  }

  /* ── Day Name Helper ─────────────────────────────────────── */
  function getDayName(dateStr) {
    if (!dateStr) return '';
    const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    return days[new Date(dateStr).getDay()];
  }

  function renderPagination(totalPages) {
    pagination.innerHTML = '';
    if (totalPages <= 1) return;

    const addBtn = (label, page, disabled = false, active = false) => {
      const btn = document.createElement('button');
      btn.className = `page-btn${active ? ' active' : ''}`;
      btn.disabled  = disabled;
      btn.innerHTML = label;
      btn.addEventListener('click', () => { currentPage = page; renderTable(); });
      pagination.appendChild(btn);
    };

    addBtn('&laquo;', currentPage - 1, currentPage === 1);
    for (let i = 1; i <= totalPages; i++) addBtn(i, i, false, i === currentPage);
    addBtn('&raquo;', currentPage + 1, currentPage === totalPages);
  }

  /* ── HTML Escape ─────────────────────────────────────────── */
  function escHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  /* ── Open View Modal ─────────────────────────────────────── */
  function openViewModal(id) {
    const c = allClaims.find(c => c.id == id);
    if (!c) return;

    const punchOutHour = parseInt((c.punch_out || '00:00:00').split(':')[0], 10);
    let severity = 'Mild (10–11 PM)';
    let sevColor  = 'var(--clr-amber)';
    if (punchOutHour >= 23)      { severity = 'Severe (11 PM+)'; sevColor = 'var(--clr-red)'; }

    viewModalBody.innerHTML = `
      <div class="detail-grid">
        <div class="detail-item">
          <label>Date</label>
          <span style="font-weight:700;">${escHtml(c.date_fmt)}</span>
        </div>
        <div class="detail-item">
          <label>Day</label>
          <span>${getDayName(c.date)}</span>
        </div>
        <div class="detail-item">
          <label>Punch In</label>
          <span style="font-weight:600;color:var(--text-primary);display:inline-flex;align-items:center;gap:4px;">
            <i data-lucide="log-in" style="width:14px;height:14px;color:var(--text-muted);"></i>
            ${escHtml(c.punch_in_fmt)}
          </span>
        </div>
        <div class="detail-item">
          <label>Punch Out</label>
          <span style="font-weight:600;color:var(--text-primary);display:inline-flex;align-items:center;gap:4px;">
            <i data-lucide="log-out" style="width:14px;height:14px;color:var(--text-muted);"></i>
            ${escHtml(c.punch_out_fmt)}
          </span>
        </div>
      </div>
      
      ${c.work_report ? `
      <div style="margin-top:20px;">
        <label style="display:block;font-size:0.74rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Work Report</label>
        <div style="font-size:0.92rem;color:var(--text-primary);line-height:1.6;">${escHtml(c.work_report)}</div>
      </div>` : ''}

      ${c.notes ? `
        <div style="margin-top:20px;">
          <label style="display:block;font-size:0.74rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Your Note</label>
          <div style="font-size:0.92rem;color:var(--text-secondary);line-height:1.6;">${escHtml(c.notes)}</div>
        </div>
      ` : ''}
      
      ${c.manager_note ? `
        <div style="margin-top:20px;">
          <label style="display:block;font-size:0.74rem;font-weight:700;color:#b45309;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Manager's Note</label>
          <div style="font-size:0.92rem;color:var(--text-primary);line-height:1.6;">${escHtml(c.manager_note)}</div>
        </div>
      ` : ''}

      ${c.hr_note ? `
        <div style="margin-top:20px;">
          <label style="display:block;font-size:0.74rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">HR's Note</label>
          <div style="font-size:0.92rem;color:var(--text-primary);line-height:1.6;">${escHtml(c.hr_note)}</div>
        </div>
      ` : ''}

      <!-- Status Track -->
    `;

    openModal(viewModalBackdrop);
    if (window.lucide) lucide.createIcons();
  }

  /* ── Modal Helpers ───────────────────────────────────────── */
  function openModal(el) {
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
    if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
  }
  function closeModal(el) {
    el.classList.remove('open');
    document.body.style.overflow = '';
  }

  let isResubmitMode = false;

  function sendClaim(id, isResubmit = false) {
    const c = allClaims.find(c => c.id == id);
    if (!c) return;

    pendingSendId = id;
    sendNote.value = '';
    isResubmitMode = isResubmit;

    if (isResubmit && c.resubmit_count >= 3) {
      showToast('Maximum resubmissions reached.', 'error');
      return;
    }

    const titleText = isResubmit ? 'Resubmit Food Reimbursement' : 'Send Claim';
    document.querySelector('#sendModalBackdrop .modal-header h3').textContent = titleText;

    const btnText = isResubmit ? 'Confirm Resubmit' : 'Confirm Send';
    document.getElementById('confirmSendBtn').textContent = btnText;

    const infoText = isResubmit 
      ? 'Are you sure you want to resubmit this Food Reimbursement? It will be sent to your Manager and HR for a new approval.'
      : 'This claim will be sent to HR and your Manager for approval. You may add an optional note below.';

    // Populate modal summary
    sendModalSummary.innerHTML = `
      <div class="send-summary">
        <div class="send-summary-row">
          <span class="send-summary-label">Date</span>
          <span class="send-summary-value">${escHtml(c.date_fmt)} &nbsp;<span style="color:var(--text-muted);font-size:0.8rem;">${getDayName(c.date)}</span></span>
        </div>
        <div class="send-summary-row">
          <span class="send-summary-label">Punch Out</span>
          <span class="send-summary-value">
            <span class="time-badge time-out" style="display:inline-flex;">
              <i data-lucide="log-out" style="width:11px;height:11px;"></i>
              ${escHtml(c.punch_out_fmt)}
            </span>
          </span>
        </div>
        <div class="send-summary-row">
          <span class="send-summary-label">Current Status</span>
          <span class="send-summary-value">${statusPill(finalStatus(c.hr_status, c.manager_status, c.claim_status, c.resubmit_count), 'status')}</span>
        </div>
        <div class="send-summary-row">
          <span class="send-summary-label">Manager</span>
          <span class="send-summary-value" style="font-weight:600;color:var(--clr-blue);">${c.manager_name ? escHtml(c.manager_name) : '<i>Not Assigned</i>'}</span>
        </div>
        <div class="send-summary-row">
          <span class="send-summary-label">Max Allowed Amount</span>
          <span class="send-summary-value" style="font-weight:700;color:var(--clr-emerald);">₹${parseFloat(c.price_per_meal || 100).toFixed(2)}</span>
        </div>
        ${c.work_report ? `
        <div class="send-summary-row">
          <span class="send-summary-label">Work Report</span>
          <span class="send-summary-value" style="color:var(--text-secondary);font-size:0.875rem;">${escHtml(c.work_report)}</span>
        </div>` : ''}
      </div>

      <div class="send-info-box">
        <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0;"></i>
        <span>${infoText}</span>
      </div>
    `;

    openModal(sendModalBackdrop);
    if (window.lucide) lucide.createIcons();
  }

  /* ── Undo Availability Helpers ──────────────────────────── */
  /**
   * Undo is allowed ONLY when:
   *  - Claim has been submitted  AND
   *  - Neither HR nor Manager has approved yet
   */
  function canUndo(c) {
    const submitted = (c.claim_status || '').toLowerCase() === 'submitted';
    const hr  = (c.hr_status      || '').toLowerCase();
    const mgr = (c.manager_status || '').toLowerCase();
    return submitted && hr !== 'approved' && mgr !== 'approved';
  }

  function undoLockReason(c) {
    const submitted = (c.claim_status || '').toLowerCase() === 'submitted';
    if (!submitted) return 'Nothing to undo — claim has not been submitted yet';
    const hr  = (c.hr_status      || '').toLowerCase();
    const mgr = (c.manager_status || '').toLowerCase();
    if (hr  === 'approved') return 'Cannot undo — HR has already approved this claim';
    if (mgr === 'approved') return 'Cannot undo — Manager has already approved this claim';
    return 'Undo not available';
  }
  /**
   * Edit is allowed ONLY when:
   *  - The claim has been submitted (claim_status = 'submitted')  AND
   *  - Neither HR nor Manager has approved or rejected yet
   */
  function canEdit(c) {
    const submitted = (c.claim_status || '').toLowerCase() === 'submitted';
    const hr  = (c.hr_status      || '').toLowerCase();
    const mgr = (c.manager_status || '').toLowerCase();
    const hrActed  = hr  === 'approved';
    const mgrActed = mgr === 'approved';
    return submitted && !hrActed && !mgrActed;
  }

  function editLockReason(c) {
    const submitted = (c.claim_status || '').toLowerCase() === 'submitted';
    if (!submitted) return 'Submit the claim first before editing';
    const hr  = (c.hr_status      || '').toLowerCase();
    const mgr = (c.manager_status || '').toLowerCase();
    if (hr  === 'approved') return 'Cannot edit — HR has already approved this claim';
    if (mgr === 'approved') return 'Cannot edit — Manager has already approved this claim';
    return 'Editing not available';
  }

  let editMode = 'edit'; // 'edit' or 'resubmit'

  function openEditModal(id, mode = 'edit') {
    const c = allClaims.find(c => c.id == id);
    if (!c) return;

    if (mode === 'edit' && !canEdit(c)) {
      showToast(editLockReason(c), 'error');
      return;
    }

    if (mode === 'resubmit' && c.resubmit_count >= 3) {
      showToast('Maximum resubmissions reached.', 'error');
      return;
    }

    editMode = mode;
    document.querySelector('#editModalBackdrop .modal-header h3').textContent = (mode === 'resubmit') ? 'Resubmit Rejected Claim' : 'Edit Claim Details';
    document.getElementById('saveEditBtn').textContent = (mode === 'resubmit') ? 'Submit Revised Claim' : 'Save Details';

    // Store id for submit handler
    document.getElementById('editClaimId').value = c.id;

    // Pre-fill fields
    document.getElementById('editCategory').value    = c.category    || '';
    
    const amtInput = document.getElementById('editAmount');
    amtInput.value = c.amount || '';
    amtInput.max   = c.price_per_meal || 100;
    amtInput.placeholder = `Max ₹${parseFloat(c.price_per_meal || 100).toFixed(2)}`;

    document.getElementById('editMealType').value    = c.meal_type   || '';
    document.getElementById('editVendor').value      = c.vendor_name || '';
    document.getElementById('editDescription').value = c.description || '';
    document.getElementById('editNotes').value       = c.notes       || '';

    // Show date (read-only)
    document.getElementById('editDateDisplay').textContent =
      `${c.date_fmt}  •  ${getDayName(c.date)}  •  Punch Out: ${c.punch_out_fmt}`;

    openModal(document.getElementById('editModalBackdrop'));
    if (window.lucide) lucide.createIcons();
  }

  /* ── Undo Submission ─────────────────────────────────────── */
  function undoClaim(id) {
    const c = allClaims.find(c => c.id == id);
    if (!c) return;

    if (!canUndo(c)) {
      showToast(undoLockReason(c), 'error');
      return;
    }

    if (!confirm(`Undo submission for ${c.date_fmt}? The claim will return to draft.`)) return;

    fetch('api/undo_claim.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ attendance_id: c.id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            c.claim_status = 'draft';
            applyFilters();
            showToast(`Submission for ${c.date_fmt} has been undone.`, 'success');
        } else {
            showToast(data.message || 'Error undoing claim.', 'error');
        }
    })
    .catch(() => showToast('Network error.', 'error'));
  }

  /* ── Event Delegation: Table Buttons ────────────────────── */
  claimsTableBody.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const { action, id } = btn.dataset;
    if (action === 'view') openViewModal(id);
    if (action === 'send') sendClaim(id, false);
    if (action === 'edit') openEditModal(id, 'edit');
    if (action === 'resubmit') sendClaim(id, true);
    if (action === 'undo') undoClaim(id);
  });

  /* ── View Modal Close ────────────────────────────────────── */
  document.getElementById('viewModalClose').addEventListener('click', () => closeModal(viewModalBackdrop));
  document.getElementById('closeViewBtn').addEventListener('click',  () => closeModal(viewModalBackdrop));
  viewModalBackdrop.addEventListener('click', e => { if (e.target === viewModalBackdrop) closeModal(viewModalBackdrop); });

  /* ── Send Modal Close / Confirm ──────────────────────────── */
  document.getElementById('sendModalClose').addEventListener('click',  () => closeModal(sendModalBackdrop));
  document.getElementById('cancelSendBtn').addEventListener('click',   () => closeModal(sendModalBackdrop));
  sendModalBackdrop.addEventListener('click', e => { if (e.target === sendModalBackdrop) closeModal(sendModalBackdrop); });

  document.getElementById('confirmSendBtn').addEventListener('click', () => {
    const c = allClaims.find(c => c.id == pendingSendId);
    if (!c) return;
    const note = sendNote.value.trim();
    
    const endpoint = isResubmitMode ? 'api/resubmit_claim.php' : 'api/submit_claim.php';

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ attendance_id: c.id, note: note })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeModal(sendModalBackdrop);
            showToast(isResubmitMode ? 'Claim resubmitted successfully.' : `Claim for ${c.date_fmt} sent for approval.`, 'success');
            loadClaims();
        } else {
            showToast(data.message || 'Error processing request.', 'error');
        }
        pendingSendId = null;
    })
    .catch(() => {
        showToast('Network error.', 'error');
        pendingSendId = null;
    });
  });

  /* ── Edit Modal Close / Save ─────────────────────────────── */
  const editModalBackdrop = document.getElementById('editModalBackdrop');

  document.getElementById('editModalClose').addEventListener('click',  () => closeModal(editModalBackdrop));
  document.getElementById('cancelEditBtn').addEventListener('click',   () => closeModal(editModalBackdrop));
  editModalBackdrop.addEventListener('click', e => { if (e.target === editModalBackdrop) closeModal(editModalBackdrop); });

  document.getElementById('saveEditBtn').addEventListener('click', () => {
    const id = document.getElementById('editClaimId').value;
    const c  = allClaims.find(c => c.id == id);
    if (!c) return;

    // Read updated values
    const payload = {
        attendance_id: id,
        category:    document.getElementById('editCategory').value,
        amount:      document.getElementById('editAmount').value,
        meal_type:   document.getElementById('editMealType').value,
        vendor_name: document.getElementById('editVendor').value,
        description: document.getElementById('editDescription').value,
        notes:       document.getElementById('editNotes').value
    };

    const endpoint = (editMode === 'resubmit') ? 'api/resubmit_claim.php' : 'api/update_claim.php';

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeModal(editModalBackdrop);
            showToast((editMode === 'resubmit') ? 'Claim resubmitted successfully!' : 'Claim updated successfully!', 'success');
            loadClaims(); // Re-fetch all data to get updated statuses/counts
        } else {
            showToast(data.message || 'Error updating claim.', 'error');
        }
    })
    .catch(() => showToast('Network error.', 'error'));
  });

  /* ── Filter / Search Listeners ───────────────────────────── */
  document.getElementById('applyFilters').addEventListener('click', loadClaims);

  document.getElementById('resetFilters').addEventListener('click', () => {
    filterMonth.value    = new Date().toISOString().substring(0, 7);
    tableSearch.value    = '';
    loadClaims();
  });

  let searchDebounce;
  tableSearch.addEventListener('input', () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 300);
  });

  /* ── Init ────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', loadClaims);

})();
