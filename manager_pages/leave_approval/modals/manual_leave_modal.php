<!-- Manual Leave Application Modal -->
<div id="manualLeaveModal" class="modal-overlay" style="display: none;">
    <div class="modal-content manual-entry-modal" style="max-width: 650px;">
        <header class="modal-header">
            <div class="header-left">
                <i data-lucide="plus-circle" class="header-icon approve"></i>
                <div>
                    <h2 class="modal-title">Manual Leave Entry</h2>
                    <p class="modal-subtitle">Add a leave request on behalf of an employee</p>
                </div>
            </div>
            <button class="btn-close" onclick="closeManualLeaveModal()">
                <i data-lucide="x"></i>
            </button>
        </header>

        <form id="manualLeaveForm" onsubmit="handleManualLeaveSubmit(event)">
            <div class="modal-body">
                
                <!-- Employee Selection -->
                <div class="form-section-wrapper">
                    <label class="form-label">Target Employee <span class="required">*</span></label>
                    <div class="select-wrapper">
                        <select name="user_id" id="manualUserSelect" class="form-control" required onchange="onManualUserChange()">
                            <option value="">Select Employee...</option>
                        </select>
                    </div>
                </div>

                <div id="manualLeaveDetails" style="display: none; margin-top: 1.5rem; border-top: 1px solid #f1f5f9; padding-top: 1.5rem;">
                    
                    <!-- Balance Summary (Mini Grid) -->
                    <div id="manualBalanceSummary" class="manual-balance-grid">
                        <!-- Dynamically populated -->
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Leave Type <span class="required">*</span></label>
                            <select name="leave_type_id" id="manualTypeSelect" class="form-control" required>
                                <option value="">Select Category...</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Duration <span class="required">*</span></label>
                            <select name="day_type" id="manualDayType" class="form-control" required onchange="toggleManualTimeFields()">
                                <option value="Full Day">Full Day</option>
                                <option value="First Half">First Half (Morning)</option>
                                <option value="Second Half">Second Half (Evening)</option>
                                <option value="Short Leave">Short Leave</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date <span class="required">*</span></label>
                            <input type="date" name="start_date" id="manualStartDate" class="form-control" required>
                        </div>
                        <div class="form-group" id="endDateGroup">
                            <label class="form-label">End Date <span class="required">*</span></label>
                            <input type="date" name="end_date" id="manualEndDate" class="form-control" required>
                        </div>
                    </div>

                    <!-- Short Leave Time Fields -->
                    <div id="manualTimeFields" class="form-row" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Time From <span class="required">*</span></label>
                            <input type="time" name="time_from" id="manualTimeFrom" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time To <span class="required">*</span></label>
                            <input type="time" name="time_to" id="manualTimeTo" class="form-control">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 0.5rem;">
                        <label class="form-label">Reason <span class="required">*</span></label>
                        <textarea name="reason" id="manualReason" class="form-control" rows="3" placeholder="Explain the purpose of this leave..." required></textarea>
                        <small id="manualReasonWarning" class="warning-text" style="display: none;">Please provide at least 10 words for manual entry.</small>
                    </div>
                </div>

                <div id="manualUserNone" style="padding: 4rem 2rem; text-align: center; color: #94a3b8;">
                    <div style="background: #f8fafc; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                        <i data-lucide="user-plus" style="width: 32px; height: 32px; opacity: 0.5;"></i>
                    </div>
                    <h4 style="color: #475569; margin-bottom: 4px;">No User Selected</h4>
                    <p style="font-size: 0.85rem;">Select an employee above to configure their leave request.</p>
                </div>
            </div>

            <footer class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeManualLeaveModal()">Cancel</button>
                <button type="submit" id="manualSubmitBtn" class="btn-primary" style="display: none;">Submit Entry</button>
            </footer>
        </form>
    </div>
</div>

<style>
/* ─── Manual Modal Specific Styling ─── */
.manual-entry-modal .modal-body {
    max-height: 70vh;
}

.form-section-wrapper {
    background: #f8fafc;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-top: 1.25rem;
}

.form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.9rem;
    color: #1e293b;
    background: white;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.manual-balance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 1.5rem;
}

.mini-bal-card {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.mini-bal-card .bal-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--primary);
}

.mini-bal-card .bal-label {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-top: 4px;
}

.required { color: #ef4444; }

.warning-text {
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 6px;
    display: block;
    font-weight: 500;
}
</style>
