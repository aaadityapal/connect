<!-- Leave Action Modal (Approve/Reject) -->
<div id="leaveActionModal" class="modal-overlay" style="display: none;">
    <div class="modal-content action-modal">
        <header class="modal-header">
            <div class="header-left">
                <i id="actionIcon" data-lucide="check-circle" class="header-icon"></i>
                <h2 id="actionModalTitle">Approve Leave</h2>
            </div>
            <button class="btn-close" onclick="closeActionModal()">
                <i data-lucide="x"></i>
            </button>
        </header>

        <form id="leaveActionForm" onsubmit="handleLeaveAction(event)">
            <input type="hidden" id="actionRequestId" name="request_id">
            <input type="hidden" id="actionType" name="action_type"> <!-- 'approve' or 'reject' -->

            <div class="modal-body">
                <div class="request-summary-mini">
                    <p>Updating request for <strong id="actionEmployeeName">Employee</strong></p>
                    <span id="actionLeaveType" class="badge">Casual Leave</span>
                </div>

                <!-- Manager Reason -->
                <div class="form-group">
                    <label id="mgrReasonLabel" for="mgrReason">Manager's Remarks <span id="mgrReasonStatus"></span></label>
                    <textarea id="mgrReason" name="manager_reason" rows="3" placeholder="Enter your remarks here..."></textarea>
                    <small id="mgrReasonWarning" class="warning-text" style="display: none;">Rejection requires at least 10 words.</small>
                </div>

                <!-- HR Reason (Admin only) -->
                <div class="form-group" id="hrReasonSection" style="display: none;">
                    <label for="hrReason">HR Remarks (Admin override) <span id="hrReasonStatus"></span></label>
                    <textarea id="hrReason" name="hr_reason" rows="3" placeholder="Final HR remarks..."></textarea>
                    <small id="hrReasonWarning" class="warning-text" style="display: none;">Rejection requires at least 10 words for HR as well.</small>
                </div>
            </div>

            <footer class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeActionModal()">Cancel</button>
                <button type="submit" id="actionSubmitBtn" class="btn-primary">Confirm Approval</button>
            </footer>
        </form>
    </div>
</div>

<style>
/* ─── Action Modal Styling ─── */
.action-modal { max-width: 500px; }

.request-summary-mini {
    background: #f1f5f9;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.request-summary-mini p { margin: 0; font-size: 0.9rem; color: #475569; }
.badge { font-size: 0.75rem; font-weight: 600; color: var(--primary); background: #e0e7ff; padding: 2px 10px; border-radius: 20px; }

.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.9rem;
    color: #1e293b;
    resize: vertical;
    transition: all 0.2s;
}
.form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

.warning-text { color: var(--danger); font-size: 0.75rem; margin-top: 4px; display: block; font-weight: 500; }

/* Dynamic Header States */
.header-icon.approve { color: var(--success); }
.header-icon.reject { color: var(--danger); }

/* Buttons */
.modal-footer {
    padding: 1.25rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.btn-secondary { 
    background: #fff; 
    border: 1px solid #cbd5e1; 
    color: #475569; 
    padding: 10px 20px; 
    border-radius: 8px; 
    cursor: pointer; 
    font-weight: 500; 
    transition: all 0.2s; 
}
.btn-secondary:hover { background: #f1f5f9; color: #1e293b; }

.btn-primary { 
    background: var(--primary); 
    border: none; 
    color: white; 
    padding: 10px 24px; 
    border-radius: 8px; 
    cursor: pointer; 
    font-weight: 600; 
    transition: all 0.2s; 
    box-shadow: 0 4px 6px -1px var(--primary-light); 
}
.btn-primary:hover { background: #4338ca; transform: translateY(-1px); }

.btn-danger { 
    background: var(--danger); 
    border: none; 
    color: white; 
    padding: 10px 24px; 
    border-radius: 8px; 
    cursor: pointer; 
    font-weight: 600; 
    transition: all 0.2s; 
    box-shadow: 0 4px 6px -1px var(--danger-light); 
}
.btn-danger:hover { background: #dc2626; transform: translateY(-1px); }
</style>
