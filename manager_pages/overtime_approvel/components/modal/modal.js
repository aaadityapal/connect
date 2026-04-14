// components/modal/modal.js

/* ─────────────────────────────────────────────────────
   Modal state
   ───────────────────────────────────────────────────── */
let backdrop = null;

function getInitials(name) {
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function badgeHTML(status) {
    const cls = status ? status.toLowerCase() : '';
    return `<span class="ot-modal-badge ${cls}">${status || '—'}</span>`;
}
        
/* ─────────────────────────────────────────────────────
   Build modal HTML from a data row
   ───────────────────────────────────────────────────── */
function buildModal(row) {
    const initials = getInitials(row.employee);
    const dateFormatted = formatDate(row.date);
    const status = row.status || 'Pending';
    const statusLower = status.toLowerCase();
    const isSubmitted = row.request_id !== null && row.request_id !== '';
    
    let bgColor = '#ffffff';
    if (statusLower === 'approved' || statusLower === 'accepted') bgColor = '#f0fdf4';
    else if (statusLower === 'rejected') bgColor = '#fef2f2';
    else if (statusLower === 'expired') bgColor = '#f8fafc';
    else if (statusLower === 'pending' && !isSubmitted) bgColor = '#fffbeb';
    else if (statusLower === 'pending' && isSubmitted) bgColor = '#ffffff';

    const field = (icon, label, value) => `
        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; display: flex; align-items: center; gap: 0.35rem; text-transform: uppercase; letter-spacing: 0.05em;">
                <i class="ph-bold ${icon}" style="font-size: 0.85rem;"></i> ${label}
            </span>
            <span style="font-size: 0.95rem; font-weight: 600; color: #334155;">${value || '—'}</span>
        </div>`;

    return `
    <div class="modal-backdrop" id="detail-modal" role="dialog" aria-modal="true">
        <div class="modal-dialog prompt-dialog minimal-dialog" style="max-width: 680px;">

            <!-- ── HEADER ── -->
            <div class="prompt-header minimal-header" style="border-bottom: 2px solid #f1f5f9; padding: 1.25rem 2.5rem; background: ${bgColor};">
                <div class="prompt-header-title">
                    <i class="ph-bold ph-identification-card" style="color: #6366f1; font-size: 1.4rem;"></i>
                    <div style="margin-left: 0.75rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a;">${row.employee}</h3>
                        <p style="font-size: 0.8rem; color: #64748b; font-weight: 500; margin-top: 2px;">${row.employeeId || ''} &nbsp;·&nbsp; ${row.role || ''}</p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span class="ot-badge ot-badge-${statusLower}" style="font-size: 0.7rem; padding: 0.4rem 0.8rem; border-radius: 8px;">${status}</span>
                    <button class="modal-close" id="modal-close-btn" style="background: rgba(0,0,0,0.05); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #64748b;">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
            </div>

            <!-- ── BODY ── -->
            <div class="prompt-body minimal-body scrollable-body" style="background: ${bgColor}; padding: 2.25rem 2.5rem; max-height: 65vh; overflow-y: auto;">
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2.5rem; padding: 1.5rem; background: #f8fafc; border-radius: 16px; border: 1.5px solid #f1f5f9; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                    <div style="text-align: center; border-right: 1.5px solid #e2e8f0;">
                        <p style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.05em;">System Calc</p>
                        <p style="font-size: 1.5rem; font-weight: 800; color: #1e293b;">${row.otHours}<span style="font-size: 0.9rem; color: #94a3b8; margin-left:1px;">h</span></p>
                    </div>
                    <div style="text-align: center; border-right: 1.5px solid #e2e8f0;">
                        <p style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Final / Sub</p>
                        <p style="font-size: 1.5rem; font-weight: 800; color: #6366f1;">${row.submittedOt}<span style="font-size: 0.9rem; color: #94a3b8; margin-left:1px;">h</span></p>
                    </div>
                    <div style="text-align: center;">
                        <p style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Overtime Date</p>
                        <p style="font-size: 1.1rem; font-weight: 700; color: #334155; margin-top: 0.35rem;">${dateFormatted}</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; margin-bottom: 2.5rem;">
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <h4 style="font-size: 0.8rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; border-left: 3px solid #6366f1; padding-left: 0.75rem;">Clocking Information</h4>
                        ${field('ph-clock', 'Assigned Shift', row.shift)}
                        ${field('ph-sign-in', 'Punch In', row.punchIn)}
                        ${field('ph-sign-out', 'Punch Out', row.punchOut)}
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <h4 style="font-size: 0.8rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; border-left: 3px solid #8b5cf6; padding-left: 0.75rem;">Location & Meta</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; display: flex; align-items: center; gap: 0.35rem; text-transform: uppercase;">
                                <i class="ph-bold ph-map-pin"></i> Punch Out Address
                            </span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #6366f1; cursor: pointer; text-decoration: underline; text-underline-offset: 3px;" onclick="window.openMapModal('${row.punchOutLat}', '${row.punchOutLng}', '${row.punchOutAddress?.replace(/'/g, "\\'")}')">
                                ${row.punchOutAddress || 'Not Captured'}
                            </span>
                        </div>
                        ${field('ph-calendar-check', 'Submitted At', row.submittedAt)}
                        ${field('ph-clock-afternoon', 'Last Updated', row.updatedAt)}
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                            <i class="ph-fill ph-file-text" style="color: #6366f1;"></i> Daily Work Report
                        </span>
                        <div style="font-size: 0.9rem; color: #475569; line-height: 1.6; background: #f8fafc; border: 1px solid #f1f5f9; padding: 1.25rem; border-radius: 12px; font-style: italic;">
                            "${row.workReport || 'No report submitted'}"
                        </div>
                    </div>

                    <div>
                        <span style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                            <i class="ph-fill ph-chat-circle-dots" style="color: #f59e0b;"></i> Reason for Overtime
                        </span>
                        <div style="font-size: 0.9rem; color: #475569; line-height: 1.6; background: #fffbeb; border: 1px solid #fef3c7; padding: 1.25rem; border-radius: 12px;">
                            ${row.otDescription || row.otReason || 'No reason provided'}
                        </div>
                    </div>

                    ${row.managerComment ? `
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                            <i class="ph-fill ph-shield-check" style="color: #10b981;"></i> Actioned Remarks
                        </span>
                        <div style="font-size: 0.9rem; color: #065f46; line-height: 1.6; background: #f0fdf4; border: 1px solid #dcfce7; padding: 1.25rem; border-radius: 12px; font-weight: 600;">
                            "${row.managerComment}"
                        </div>
                    </div>` : ''}
                </div>

            </div>

            <!-- ── FOOTER ── -->
            <div class="prompt-footer" style="padding: 1.5rem 2.5rem; background: #fff; border-top: 1px solid #f1f5f9; gap: 1rem; border-radius: 0 0 20px 20px; display: flex; justify-content: center;">
                <button class="modal-btn" id="modal-footer-close" 
                        onmouseover="this.style.background='#ef4444'; this.style.color='#fff'" 
                        onmouseout="this.style.background='#fef2f2'; this.style.color='#ef4444'"
                        style="min-width: 160px; background: #fef2f2; border: 1.5px solid #fecaca; color: #ef4444; padding: 0.85rem 2rem; font-size: 0.85rem; font-weight: 700; border-radius: 12px; cursor: pointer; transition: all 0.2s; text-align: center;">
                    Close Window
                </button>
                <div style="display: flex; gap: 0.75rem; border-left: 1.5px solid #f1f5f9; padding-left: 1rem; ${statusLower!=='pending'?'display:none;':''}">
                    <button class="modal-btn" id="modal-footer-reject" style="background: #fff; border: 1.5px solid #ef4444; color: #ef4444; padding: 0.85rem 1.5rem; font-size: 0.85rem; font-weight: 600; border-radius: 10px;">Reject</button>
                    <button class="modal-btn" id="modal-footer-approve" style="background: #1e293b; color: #fff; border: none; padding: 0.85rem 1.5rem; font-size: 0.85rem; font-weight: 600; border-radius: 10px; box-shadow: 0 4px 12px rgba(30,41,59,0.2);">Approve Request</button>
                </div>
            </div>

        </div>
    </div>`;
}

/* ─────────────────────────────────────────────────────
   Public API
   ───────────────────────────────────────────────────── */
export function openDetailModal(row) {
    // Remove existing modal if any
    closeDetailModal();

    // Inject into body
    const wrapper = document.createElement('div');
    wrapper.innerHTML = buildModal(row);
    backdrop = wrapper.firstElementChild;
    document.body.appendChild(backdrop);

    // Trigger open animation on next frame
    requestAnimationFrame(() => backdrop.classList.add('is-open'));

    // Wire close triggers
    const closeFn = () => closeDetailModal();
    document.getElementById('modal-close-btn')?.addEventListener('click', closeFn);
    document.getElementById('modal-footer-close')?.addEventListener('click', closeFn);

    // Clicking the backdrop outside the dialog also closes
    backdrop.addEventListener('click', e => {
        if (e.target === backdrop) closeFn();
    });

    // Escape key
    const onKey = e => { if (e.key === 'Escape') closeFn(); };
    document.addEventListener('keydown', onKey, { once: true });

    // Reject / Approve stubs (wire real logic here later)
    document.getElementById('modal-footer-reject')?.addEventListener('click', () => {
        console.info('Reject clicked for', row.employee, row.date);
        closeFn();
    });
    document.getElementById('modal-footer-approve')?.addEventListener('click', () => {
        console.info('Approve clicked for', row.employee, row.date);
        closeFn();
    });
}

export function closeDetailModal() {
    if (!backdrop) return;
    const el = backdrop;   // capture local ref before nulling module state
    backdrop = null;       // clear immediately so re-open calls work
    el.classList.remove('is-open');
    el.addEventListener('transitionend', () => el.remove(), { once: true });
}

/* ─────────────────────────────────────────────────────
   EDIT MODAL
   ───────────────────────────────────────────────────── */
function buildEditModal(row) {
    const currentOt = row.submittedOt !== 'N/A' ? parseFloat(row.submittedOt) : parseFloat(row.otHours) || 0;
    
    return `
    <div class="modal-backdrop" id="edit-modal-backdrop" role="dialog" aria-modal="true">
        <div class="modal-dialog prompt-dialog minimal-dialog" style="max-width: 480px;">

            <!-- ── HEADER ── -->
            <div class="prompt-header minimal-header" style="border-bottom: 2px solid #f1f5f9;">
                <div class="prompt-header-title">
                    <i class="ph-bold ph-pencil-simple" style="color: #6366f1; font-size: 1.3rem;"></i>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-left: 0.5rem;">Edit Overtime</h3>
                </div>
                <button class="modal-close" id="edit-close-x" title="Close" aria-label="Close">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <!-- ── BODY ── -->
            <div class="prompt-body minimal-body" style="background: #fff; padding: 2rem 2.5rem;">
                
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                        <i class="ph-bold ph-user" style="color: #94a3b8; font-size: 1rem;"></i>
                        <span style="font-size: 0.9rem; color: #64748b;">Adjusting for <strong>${row.employee}</strong></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="ph-bold ph-timer" style="color: #94a3b8; font-size: 1rem;"></i>
                        <span style="font-size: 0.9rem; color: #64748b;">Originally submitted: <strong>${currentOt}h</strong></span>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; align-items: center; gap: 1.5rem; padding: 1rem 0;">
                    <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em;">Final Overtime Hours</span>
                    
                    <div style="display: flex; align-items: center; gap: 2.5rem;">
                        <button class="stepper-btn" id="stepper-minus" style="width: 44px; height: 44px; border: 1.5px solid #e2e8f0; background: #fff; border-radius: 12px; color: #64748b; cursor: pointer; transition: all 0.2s;">
                            <i class="ph-bold ph-minus"></i>
                        </button>
                        
                        <div id="stepper-val" style="font-size: 4rem; font-weight: 800; color: #1e293b; line-height: 1; letter-spacing: -2px;">
                            ${currentOt.toFixed(1)}<span style="font-size: 1.25rem; color: #94a3b8; font-weight: 600; letter-spacing: -0.5px; margin-left: 0.25rem;">h</span>
                        </div>

                        <button class="stepper-btn" id="stepper-plus" style="width: 44px; height: 44px; border: 1.5px solid #1e293b; background: #1e293b; border-radius: 12px; color: #fff; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                            <i class="ph-bold ph-plus"></i>
                        </button>
                    </div>

                    <div style="width: 100%; padding: 0.5rem 0;">
                        <input type="range" id="stepper-slider" min="1.5" max="${Math.max(currentOt + 6, 8)}" step="0.5" value="${currentOt}" 
                               style="width: 100%; height: 6px; background: #f1f5f9; border-radius: 99px; appearance: none; -webkit-appearance: none; cursor: pointer; outline: none; accent-color: #6366f1;">
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 0.4rem; color: #94a3b8;">
                        <i class="ph ph-info"></i>
                        <span style="font-size: 0.75rem;">Adjust in 0.5h steps (Absolute Min 1.5h)</span>
                    </div>
                </div>
            </div>

            <!-- ── FOOTER ── -->
            <div class="prompt-footer" style="padding: 1.5rem 2.5rem; background: #fff; border-top: 1px solid #f1f5f9; gap: 1rem;">
                <button class="modal-btn" id="edit-cancel-btn" style="flex: 1; background: #fff; border: 1px solid #e2e8f0; color: #64748b; padding: 0.8rem; font-size: 0.85rem; font-weight: 600; border-radius: 10px;">Cancel</button>
                <button class="modal-btn" id="edit-save-btn" style="flex: 1.5; background: #1e293b; color: #fff; border: none; padding: 0.8rem; font-size: 0.85rem; font-weight: 600; border-radius: 10px; box-shadow: 0 4px 12px rgba(30,41,59,0.2);">Save Changes</button>
            </div>

        </div>
    </div>`;
}

export function openEditModal(row, onSave) {
    closeDetailModal();
    
    const wrapper = document.createElement('div');
    wrapper.innerHTML = buildEditModal(row);
    backdrop = wrapper.firstElementChild;
    document.body.appendChild(backdrop);

    requestAnimationFrame(() => backdrop.classList.add('is-open'));

    const submissionVal = row.submittedOt !== 'N/A' ? parseFloat(row.submittedOt) : parseFloat(row.otHours) || 0;
    const initialFloor = 1.5; // Hard floor as requested
    let currentVal = submissionVal;
    const maxVal = Math.max(submissionVal + 6, 8); 

    const btnMinus   = document.getElementById('stepper-minus');
    const btnPlus    = document.getElementById('stepper-plus');
    const valDisp    = document.getElementById('stepper-val');
    const progressEl = document.getElementById('stepper-progress');
    const slider     = document.getElementById('stepper-slider');

    function updateState() {
        // Update display (keep unit span)
        valDisp.innerHTML = `${currentVal.toFixed(1)}<span style="font-size: 1.25rem; color: #94a3b8; font-weight: 600; letter-spacing: -0.5px; margin-left: 0.25rem;">h</span>`;
        btnMinus.disabled = (currentVal <= initialFloor);
        // Update slider
        if (slider) slider.value = currentVal;
        // Update progress bar
        const pct = Math.min(((currentVal - initialFloor) / (maxVal - initialFloor)) * 100, 100);
        if (progressEl) progressEl.style.width = pct + '%';
    }

    btnMinus.addEventListener('click', () => {
        if (currentVal > initialFloor) { currentVal -= 0.5; updateState(); }
    });

    btnPlus.addEventListener('click', () => {
        if (currentVal < maxVal) { currentVal += 0.5; updateState(); }
    });

    slider?.addEventListener('input', (e) => {
        currentVal = parseFloat(e.target.value);
        updateState();
    });

    const closeFn = () => closeDetailModal();
    document.getElementById('edit-close-x')?.addEventListener('click', closeFn);
    document.getElementById('edit-cancel-btn')?.addEventListener('click', closeFn);

    backdrop.addEventListener('click', e => {
        if (e.target === backdrop) closeFn();
    });

    const onKey = e => { if (e.key === 'Escape') closeFn(); };
    document.addEventListener('keydown', onKey);

    document.getElementById('edit-save-btn')?.addEventListener('click', () => {
        if (onSave) onSave(currentVal.toFixed(1));
        closeFn();
        document.removeEventListener('keydown', onKey);
    });
}

/* ─────────────────────────────────────────────────────
   CONFIRM MODALS (Approve / Reject)
   ───────────────────────────────────────────────────── */
function buildConfirmModal(type, row) {
    const isApprove = type === 'approve';
    
    // ============================================
    // REJECT MODAL
    // ============================================
    if (!isApprove) {
        return `
        <div class="modal-backdrop" id="confirm-modal-backdrop" role="dialog" aria-modal="true">
            <div class="modal-dialog prompt-dialog minimal-dialog" style="max-width: 480px;">
                
                <div class="prompt-header minimal-header" style="border-bottom: 2px solid #f1f5f9;">
                    <div class="prompt-header-title">
                        <i class="ph-bold ph-prohibit" style="color: #ef4444; font-size: 1.4rem;"></i>
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-left: 0.5rem;">Reject Request</h3>
                    </div>
                    <button class="modal-close" id="confirm-cancel-x">
                        <i class="ph ph-x"></i>
                    </button>
                </div>

                <div class="prompt-body minimal-body" style="background: #fff; padding: 1.5rem 2rem;">
                    
                    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <i class="ph-bold ph-user" style="color: #94a3b8; font-size: 1rem;"></i>
                            <span style="font-size: 0.9rem; color: #475569;">Rejecting for <strong>${row.employee}</strong></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <i class="ph-bold ph-calendar" style="color: #94a3b8; font-size: 1rem;"></i>
                            <span style="font-size: 0.9rem; color: #475569;">Date: <strong>${row.date || '—'}</strong></span>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label style="font-size: 0.75rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.75rem;">
                            Reason for Rejection
                        </label>
                        <textarea id="reject-reason" placeholder="Please provide a detailed reason for the rejection (e.g., Unauthorised OT, Task not verified)..." 
                                  style="width: 100%; min-height: 120px; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 0.9rem; font-family: inherit; resize: none; outline: none; transition: all 0.2s; background: #fbfcfe; color: #1e293b;"></textarea>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.6rem;">
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">Minimum 10 words required</span>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8;">Words: <span id="word-count-val">0</span></span>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-top: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 10px; cursor: pointer; transition: all 0.2s;">
                            <input type="checkbox" id="chk-incorrect" style="width: 16px; height: 16px; accent-color: #ef4444;">
                            <span style="font-size: 0.8rem; color: #475569; font-weight: 500;">Incorrect hours claimed</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 10px; cursor: pointer; transition: all 0.2s;">
                            <input type="checkbox" id="chk-policy" style="width: 16px; height: 16px; accent-color: #ef4444;">
                            <span style="font-size: 0.8rem; color: #475569; font-weight: 500;">Policy non-compliance</span>
                        </label>
                    </div>

                </div>

                <div class="prompt-footer" style="padding: 1.25rem 2rem; background: #fff; border-top: 1px solid #f1f5f9; gap: 1rem;">
                    <button class="modal-btn" id="confirm-cancel-btn" style="flex: 1; background: #fff; border: 1px solid #e2e8f0; color: #64748b; padding: 0.8rem; font-size: 0.85rem; font-weight: 600; border-radius: 10px;">Cancel</button>
                    <button class="modal-btn" id="confirm-action-btn" style="flex: 1.5; background: #ef4444; color: #fff; border: none; padding: 0.8rem; font-size: 0.85rem; font-weight: 600; border-radius: 10px; box-shadow: 0 4px 12px rgba(239,68,68,0.2);">Confirm Rejection</button>
                </div>

            </div>
        </div>`;
    }

    // ============================================
    // ACCEPT MODAL (Minimalistic)
    // ============================================
    const hours = row.submittedOt !== 'N/A' ? row.submittedOt : row.otHours;
    
    return `
    <div class="modal-backdrop" id="confirm-modal-backdrop" role="dialog" aria-modal="true">
        <div class="modal-dialog prompt-dialog minimal-dialog">
            
            <div class="prompt-header minimal-header" style="border-bottom: 2px solid #f1f5f9;">
                <div class="prompt-header-title">
                    <i class="ph-bold ph-seal-check" style="color: #10b981; font-size: 1.4rem;"></i>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-left: 0.5rem;">Approve Request</h3>
                </div>
                <button class="modal-close" id="confirm-cancel-x">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <div class="prompt-body minimal-body scrollable-body" style="background: #fff; padding: 1.5rem 2rem;">
                
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; align-items: baseline; padding-bottom: 0.5rem; border-bottom: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="ph-bold ph-user" style="color: #6366f1;"></i> EMPLOYEE
                            </span>
                            <span style="font-size: 0.9rem; font-weight: 700; color: #1e293b;">${row.employee}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; padding-bottom: 0.5rem; border-bottom: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="ph-bold ph-calendar" style="color: #f59e0b;"></i> DATE
                            </span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #334155;">${row.date || '—'}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; padding-bottom: 0.5rem; border-bottom: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="ph-bold ph-clock" style="color: #3b82f6;"></i> SHIFT
                            </span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #334155;">${row.startTime || '—'} - ${row.endTime || '—'}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; padding-bottom: 0.5rem; border-bottom: 1px dashed #e2e8f0;">
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="ph-bold ph-sign-out" style="color: #ef4444;"></i> PUNCH OUT
                            </span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #334155;">${row.punchOut || '—'}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0;">
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="ph-bold ph-map-pin" style="color: #8b5cf6;"></i> LOCATION
                            </span>
                            <span class="location-link" id="view-map-link" style="font-size: 0.85rem; font-weight: 600; color: #6366f1; cursor: pointer; text-decoration: underline; text-underline-offset: 3px; text-align: right; max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="Click to view on map">
                                ${row.punchOutAddress || 'Not Captured'}
                            </span>
                        </div>
                        
                        <div style="margin-top: 1rem; padding: 1.5rem; background: #f8fafc; border-radius: 16px; border: 1.5px solid #f1f5f9;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-size: 0.85rem; font-weight: 700; color: #475569;">Adjust Overtime</span>
                                    <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;">Min: 1.5h • Step: 0.5h</span>
                                </div>
                                <div id="stepper-val" style="font-size: 2rem; font-weight: 800; color: #1e293b; background: #fff; padding: 0.25rem 0.75rem; border-radius: 10px; border: 1px solid #e2e8f0; line-height: 1.2;">
                                    ${hours}<span style="font-size: 0.9rem; color: #94a3b8; font-weight: 600; margin-left: 0.2rem;">h</span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <button class="stepper-btn" id="stepper-minus" style="width: 38px; height: 38px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; color: #64748b; cursor: pointer; transition: all 0.2s;">
                                    <i class="ph-bold ph-minus"></i>
                                </button>
                                <div style="flex: 1; padding: 0 0.5rem; display: flex; align-items: center;">
                                    <input type="range" id="stepper-slider" min="1.5" max="${Math.max(parseFloat(hours) + 6, 8)}" step="0.5" value="${hours}" 
                                           style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 99px; appearance: none; -webkit-appearance: none; cursor: pointer; outline: none; accent-color: #6366f1;">
                                </div>
                                <button class="stepper-btn" id="stepper-plus" style="width: 38px; height: 38px; border: 1px solid #1e293b; background: #1e293b; border-radius: 10px; color: #fff; cursor: pointer; transition: all 0.2s;">
                                    <i class="ph-bold ph-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
                    <span style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                        <i class="ph-fill ph-file-text" style="color: #94a3b8;"></i> Work Report
                    </span>
                    <div style="font-size: 0.85rem; color: #475569; line-height: 1.7; background: #fafbfc; border: 1px solid #f1f5f9; padding: 1.25rem; border-radius: 14px; font-style: italic; border-left: 4px solid #cbd5e1;">
                        "${row.workReport || 'No work report submitted'}"
                    </div>
                </div>

                <div style="margin-top: 1.25rem;">
                    <span style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                        <i class="ph-fill ph-chat-circle-dots" style="color: #94a3b8;"></i> Overtime Reason
                    </span>
                    <div style="font-size: 0.85rem; color: #475569; line-height: 1.7; background: #fafbfc; border: 1px solid #f1f5f9; padding: 1.25rem; border-radius: 14px; font-style: italic; border-left: 4px solid #94a3b8;">
                        "${row.otReason || 'No reason provided'}"
                    </div>
                </div>

            </div>

            <div class="prompt-footer" style="padding: 1.25rem 2rem; background: #fff; border-top: 1px solid #f1f5f9; gap: 1rem;">
                <button class="modal-btn" id="confirm-cancel-btn" style="flex: 1; background: #fff; border: 1px solid #e2e8f0; color: #64748b; padding: 0.8rem; font-size: 0.85rem; font-weight: 600; border-radius: 10px;">
                    Cancel
                </button>
                <button class="modal-btn" id="confirm-action-btn" style="flex: 1.5; background: #1e293b; color: #fff; border: none; padding: 0.8rem; font-size: 0.85rem; font-weight: 600; border-radius: 10px; box-shadow: 0 4px 12px rgba(30,41,59,0.2);">
                    Approve & Confirm
                </button>
            </div>

        </div>
    </div>`;
}

export function openConfirmModal(type, row, onConfirm, onEdit) {
    closeDetailModal();
    
    const wrapper = document.createElement('div');
    wrapper.innerHTML = buildConfirmModal(type, row);
    backdrop = wrapper.firstElementChild;
    document.body.appendChild(backdrop);

    requestAnimationFrame(() => backdrop.classList.add('is-open'));

    const closeFn = () => {
        closeDetailModal();
        document.removeEventListener('keydown', onKey);
    };

    const initialVal = row.submittedOt !== 'N/A' ? parseFloat(row.submittedOt) : parseFloat(row.otHours) || 0;
    const initialFloor = 1.5;
    let currentVal = initialVal;
    const maxVal = Math.max(initialVal + 6, 8);

    // If it's an approve modal, handle the internal stepper
    if (type === 'approve') {
        const btnMinus = document.getElementById('stepper-minus');
        const btnPlus  = document.getElementById('stepper-plus');
        const valDisp  = document.getElementById('stepper-val');
        const slider   = document.getElementById('stepper-slider');

        const updateUI = () => {
            valDisp.innerHTML = `${currentVal.toFixed(1)}<span style="font-size: 0.9rem; color: #94a3b8; font-weight: 600; margin-left: 0.2rem;">h</span>`;
            btnMinus.disabled = (currentVal <= initialFloor);
            if (slider) slider.value = currentVal;
        };

        btnMinus?.addEventListener('click', () => {
            if (currentVal > initialFloor) { currentVal -= 0.5; updateUI(); }
        });
        btnPlus?.addEventListener('click', () => {
            if (currentVal < maxVal) { currentVal += 0.5; updateUI(); }
        });
        slider?.addEventListener('input', (e) => {
            currentVal = parseFloat(e.target.value);
            updateUI();
        });
        updateUI(); // Initial sync
    }

    document.getElementById('confirm-cancel-btn')?.addEventListener('click', closeFn);
    document.getElementById('confirm-cancel-x')?.addEventListener('click', closeFn);

    backdrop.addEventListener('click', e => {
        if (e.target === backdrop) closeFn();
    });

    const onKey = e => { if (e.key === 'Escape') closeFn(); };
    document.addEventListener('keydown', onKey);

    // For reject modal: live word count & validation
    if (type === 'reject') {
        const textarea   = document.getElementById('reject-reason');
        const wordCount  = document.getElementById('word-count-val');
        const actionBtn  = document.getElementById('confirm-action-btn');
        
        if (textarea && wordCount && actionBtn) {
            actionBtn.disabled = true;
            actionBtn.style.opacity = '0.5';
            
            textarea.addEventListener('input', () => {
                const words = textarea.value.trim().split(/\s+/).filter(w => w.length > 0);
                const count = words.length;
                wordCount.textContent = count;
                
                const valid = count >= 10;
                actionBtn.disabled = !valid;
                actionBtn.style.opacity = valid ? '1' : '0.5';
                wordCount.style.color = valid ? '#10b981' : '#94a3b8';
            });
        }
    }

    // Map wiring
    document.getElementById('view-map-link')?.addEventListener('click', () => {
        if (row.punchOutLat && row.punchOutLng) {
            window.openMapModal(row.punchOutLat, row.punchOutLng, row.punchOutAddress);
        } else {
            alert('Coordinates not available for this session.');
        }
    });

    document.getElementById('confirm-action-btn')?.addEventListener('click', () => {
        let comment = '';
        let hoursToSave = currentVal.toFixed(1);

        if (type === 'reject') {
            comment = document.getElementById('reject-reason')?.value || '';
            hoursToSave = initialVal;
        }
        
        if (onConfirm) onConfirm(comment, hoursToSave);
        closeFn();
    });
}

/**
 * Opens a small, elegant map modal with an embedded Google Maps view.
 */
window.openMapModal = function (lat, lng, address) {
    const mapUrl = `https://www.google.com/maps?q=${lat},${lng}&hl=en&z=15&output=embed`;
    
    const mapModalHtml = `
        <div class="modal-backdrop" id="map-modal-backdrop" style="z-index: 10005; opacity: 1; transition: opacity 0.3s ease;">
            <div class="modal-dialog" style="max-width: 550px; height: 500px; border-radius: 20px; overflow: hidden; transform: scale(1); opacity: 1;">
                <div class="modal-header" style="background: #fff; border-bottom: 1px solid #f1f5f9; padding: 1rem 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="ph-fill ph-map-pin" style="color: #ef4444; font-size: 1.2rem;"></i>
                        <h3 style="margin:0; font-size: 1rem; font-weight: 700;">Punch Out Location</h3>
                    </div>
                    <button class="modal-close" id="map-close-btn" style="background: #f1f5f9; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer;">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 0; flex: 1; background: #f8fafc; display: flex; flex-direction: column;">
                    <iframe 
                        width="100%" 
                        height="100%" 
                        style="border:0; flex: 1;" 
                        src="${mapUrl}" 
                        allowfullscreen 
                        loading="lazy">
                    </iframe>
                    <div style="padding: 1.25rem; background: #fff; border-top: 1px solid #f1f5f9;">
                        <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.4rem;">VERIFIED ADDRESS</span>
                        <p style="margin:0; font-size: 0.85rem; color: #475569; line-height: 1.5; font-weight: 500;">${address}</p>
                    </div>
                </div>
            </div>
        </div>
    `;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = mapModalHtml;
    const mapBackdrop = wrapper.firstElementChild;
    document.body.appendChild(mapBackdrop);

    // Close logic for Map Modal
    const closeMap = () => {
        mapBackdrop.style.opacity = '0';
        setTimeout(() => mapBackdrop.remove(), 300);
    };

    document.getElementById('map-close-btn').onclick = closeMap;
    mapBackdrop.onclick = (e) => { if(e.target === mapBackdrop) closeMap(); };
}
