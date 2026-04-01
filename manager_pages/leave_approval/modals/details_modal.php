<!-- Leave Details Modal -->
<div id="leaveDetailsModal" class="modal-overlay" style="display: none;">
    <div class="modal-content details-modal">
        <header class="modal-header">
            <div class="header-left">
                <i data-lucide="file-text" class="header-icon"></i>
                <h2>Leave Request Details</h2>
            </div>
            <button class="btn-close" onclick="closeDetailsModal()">
                <i data-lucide="x"></i>
            </button>
        </header>

        <div class="modal-body">
            <!-- Top Section: Applicant Info -->
            <div class="detail-section applicant-heading">
                <div class="avatar-large" id="modalAvatar">A</div>
                <div class="applicant-info">
                    <h3 id="modalEmployeeName">Loading...</h3>
                    <p id="modalUserRole" class="role-tag">MANAGER</p>
                </div>
                <div class="status-summary">
                    <div class="status-block">
                        <label>Manager Status</label>
                        <span id="modalMgrStatus" class="status-tag">Pending</span>
                    </div>
                    <div class="status-block">
                        <label>HR Status</label>
                        <span id="modalHrStatus" class="status-tag">Pending</span>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <!-- Middle Section: Leave Metadata -->
            <div class="info-grid">
                <div class="info-item">
                    <label><i data-lucide="calendar-check"></i> Leave Type</label>
                    <span id="modalLeaveType">Casual Leave</span>
                </div>
                <div class="info-item">
                    <label><i data-lucide="calendar"></i> Dates</label>
                    <span id="modalDates">2025-04-10 to 2025-04-12</span>
                </div>
                <div class="info-item">
                    <label><i data-lucide="clock"></i> Total Duration</label>
                    <span id="modalDuration">3 Days</span>
                </div>
                <div class="info-item">
                    <label><i data-lucide="link"></i> Attachments</label>
                    <div id="modalAttachments" class="attachment-list">
                        <!-- Populated by JS -->
                        <span class="no-data">None uploaded</span>
                    </div>
                </div>
            </div>

            <div class="detail-section reason-section">
                <label>Reason for Leave</label>
                <div class="reason-bubble" id="modalReason">
                    Attending a family function.
                </div>
            </div>
        </div>

        <footer class="modal-footer">
            <button class="btn-secondary" onclick="closeDetailsModal()">Close</button>
            <div class="action-buttons">
                <button class="btn-danger-outline" id="modalRejectBtn">Reject</button>
                <button class="btn-primary" id="modalApproveBtn">Approve Request</button>
            </div>
        </footer>
    </div>
</div>

<script>
    // These IDs are inside the modal, we'll attach listeners via the main script
</script>

<style>
/* ─── Modal Layout ─── */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1.5rem;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 650px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
    overflow: hidden;
    animation: modalPop 0.3s ease-out;
}

@keyframes modalPop {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

/* ─── Header ─── */
.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-icon { color: var(--primary); width: 20px; height: 20px; }
.modal-header h2 { font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0; }

.btn-close {
    background: #f1f5f9;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s;
}

.btn-close:hover { background: #e2e8f0; color: #0f172a; }

/* ─── Body ─── */
.modal-body { padding: 1.5rem; }

.applicant-heading {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.avatar-large {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), #818cf8);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
}

.applicant-info { flex: 1; }
.applicant-info h3 { margin: 0 0 4px 0; font-size: 1.1rem; color: #0f172a; }
.role-tag { 
    display: inline-block;
    font-size: 0.7rem; 
    font-weight: 700; 
    color: var(--primary); 
    background: var(--primary-light); 
    padding: 2px 8px; 
    border-radius: 4px; 
    letter-spacing: 0.5px;
}

.status-summary { display: flex; gap: 1.5rem; }
.status-block { display: flex; flex-direction: column; gap: 4px; }
.status-block label { font-size: 0.7rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; }

.divider { border: none; border-top: 1px solid #f1f5f9; margin: 1.5rem 0; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem 2rem;
    margin-bottom: 2rem;
}

.info-item { display: flex; flex-direction: column; gap: 6px; }
.info-item label { 
    font-size: 0.85rem; 
    font-weight: 600; 
    color: #64748b; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
}
.info-item label i { width: 16px; height: 16px; color: #94a3b8; }
.info-item span { font-size: 0.95rem; color: #1e293b; font-weight: 500; padding-left: 24px; }

.reason-section label { font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 8px; display: block; }
.reason-bubble { 
    background: #f8fafc; 
    padding: 1rem; 
    border-radius: 12px; 
    border: 1px solid #f1f5f9; 
    font-size: 0.9rem; 
    color: #475569; 
    line-height: 1.5;
}

.attachment-list { padding-left: 24px; }
.attachment-link { 
    display: flex; 
    align-items: center; 
    gap: 6px; 
    color: var(--primary); 
    text-decoration: none; 
    font-size: 0.85rem; 
    font-weight: 500; 
    margin-bottom: 4px;
}
.attachment-link:hover { text-decoration: underline; }

/* ─── Footer ─── */
.modal-footer {
    padding: 1.25rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.action-buttons { display: flex; gap: 0.75rem; }

.btn-secondary { background: #fff; border: 1px solid #cbd5e1; color: #475569; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
.btn-secondary:hover { background: #f1f5f9; color: #1e293b; }

.btn-primary { background: var(--primary); border: none; color: white; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; box-shadow: 0 4px 6px -1px var(--primary-light); }
.btn-primary:hover { background: #4f46e5; transform: translateY(-1px); }

.btn-danger-outline { background: white; border: 1px solid #fee2e2; color: var(--danger); padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
.btn-danger-outline:hover { background: #fef2f2; border-color: var(--danger); }
</style>
