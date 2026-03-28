function initTable() {
    const refreshBtn = document.querySelector('.btn-icon[title="Refresh"]');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            const icon = refreshBtn.querySelector('i');
            icon.style.transition = 'transform 0.6s ease';
            icon.style.transform  = 'rotate(360deg)';
            setTimeout(() => {
                icon.style.transition = 'none';
                icon.style.transform  = 'rotate(0deg)';
            }, 620);
            
            // Actually fetch live records on clicking refresh
            fetchTableData();
        });
    }
    
    // Automatically perform initial data fetch on setup
    fetchTableData();
}

    window.allManagers = [];
    window.assignedManagerId = null;

async function fetchTableData() {
    const tbody = document.querySelector('.data-table tbody');
    if (!tbody) return;
    
    try {
        // Show loading state immediately to provide visual feedback
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="ph ph-circle-notch" style="animation: spin 1s linear infinite; font-size: 24px; color: var(--text-muted);"></i>
                    <div style="margin-top:10px; font-size: 0.85rem;">Validating records...</div>
                </td>
            </tr>
        `;

        let filterParams = '';
        const monthVal = document.querySelector('#month-dropdown .selected-value')?.textContent;
        const yearVal = document.querySelector('#year-dropdown .selected-value')?.textContent;
        
        if (monthVal && yearVal) {
            const monthNum = new Date(`${monthVal} 1, 2000`).getMonth() + 1;
            filterParams = `&month=${monthNum}&year=${yearVal}`;
        }
    
        // Prevent browser caching using a timestamp so updates always show instantly
        const response = await fetch(`api_overtime.php?v=${Date.now()}${filterParams}`, { cache: "no-store" });
        const json = await response.json();
        
        if (json.status === 'success' && json.data.length > 0) {
            window.allManagers = json.managers || [];
            window.assignedManagerId = json.assigned_manager_id;
            
            tbody.innerHTML = ''; // Wipe out "Empty state"
            
            let pendingRequests = 0;
            let approvedHours = 0;
            let rejectedRequests = 0;
            let expiredHours = 0;
            let totalOtHours = 0;
            
            json.data.forEach(record => {
                const tr = document.createElement('tr');
                
                // Cleanly format SQL DATE string to readable format
                const fancyDate = new Date(record.submission_date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric'});
                
                // Truncate SQL TIME seconds ("18:00:00" -> "18:00")
                const formatTime = (timeStr) => timeStr.substring(0, 5);
                
                // 1. Determine Badge styling and accumulate calculated stats based on UI logic
                let badgeClass = 'badge-pending';
                let statusName = (record.status || 'pending').charAt(0).toUpperCase() + (record.status || 'pending').slice(1);
                
                const isExplicitlyExpired = record.is_expired === true || record.is_expired === 1 || String(record.status).toLowerCase() === 'expired';
                
                let rowBgClass = '';

                if (isExplicitlyExpired) {
                    badgeClass = 'badge-expired';
                    statusName = 'Expired';
                    expiredHours += parseFloat(record.calculated_ot || 0);
                    rowBgClass = 'row-expired';
                }
                else if (record.status === 'approved') { 
                    badgeClass = 'badge-approved'; 
                    statusName = 'Approved';
                    const hrs = parseFloat(record.accepted_ot !== null ? record.accepted_ot : record.calculated_ot);
                    approvedHours += hrs;
                    totalOtHours += hrs;
                    rowBgClass = 'row-approved';
                }
                else if (record.status === 'rejected') { 
                    badgeClass = 'badge-rejected'; 
                    statusName = 'Rejected';
                    rejectedRequests++;
                    rowBgClass = 'row-rejected';
                } 
                else if (record.status === 'submitted') {
                    badgeClass = 'badge-submitted';
                    rowBgClass = 'row-submitted';
                }
                else {
                    badgeClass = 'badge-pending';
                    rowBgClass = 'row-pending';
                    pendingRequests++; // ONLY strictly pending counts here
                }

                tr.className = rowBgClass;

                const canSubmit = (record.status === 'pending' || record.status === 'submitted' || !statusName || statusName === 'Pending') && !isExplicitlyExpired;

                let displayAccepted = (record.accepted_ot !== null) ? record.accepted_ot : record.calculated_ot;
                if (record.status === 'pending' || record.status === 'submitted' || record.status === 'rejected') {
                    displayAccepted = record.calculated_ot;
                }
                
                // NOT adding anything to totalOtHours here anymore because it's only for APPROVED now.

                // Construct Row
                tr.innerHTML = `
                    <td>${fancyDate}</td>
                    <td>${formatTime(record.end_time)}</td>
                    <td>${formatTime(record.punch_out_time)}</td>
                    <td style="font-weight: 600;">${record.calculated_ot}h</td>
                    <td style="color: ${record.status === 'approved' ? '#059669' : 'inherit'}; font-weight: 600;">${displayAccepted}h</td>
                    <td><div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${record.work_report}</div></td>
                    <td><div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${record.overtime_report}</div></td>
                    <td><span class="badge ${badgeClass}">${statusName}</span></td>
                    <td>
                        <div class="action-icons">
                            <button class="icon-btn view-btn" title="View Details" data-record='${JSON.stringify(record).replace(/'/g, "&#39;")}'><i class="ph ph-eye"></i></button>
                            ${canSubmit ? `<button class="icon-btn send-btn" title="Send Report" data-record='${JSON.stringify(record).replace(/'/g, "&#39;")}' style="color: #6366f1; border-color: #e0e7ff; background: #eef2ff;"><i class="ph ph-paper-plane-right"></i></button>` : ''}
                            ${record.status === 'rejected' ? `<button class="icon-btn resubmit-btn" title="Resubmit Overtime" data-record='${JSON.stringify(record).replace(/'/g, "&#39;")}' style="color: #f97316; border-color: #ffedd5; background: #fff7ed;"><i class="ph ph-arrows-counter-clockwise"></i></button>` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            // INSTANT SYNC PREVIOUSLY STATIC TOP STAT CARDS USING THE DYNAMIC DB SUMMARY
            const pS = document.querySelector('.blue-card .stat-value'); 
            if(pS) { pS.dataset.value = pendingRequests; pS.textContent = pendingRequests; }
            
            const aS = document.querySelector('.green-card .stat-value'); 
            if(aS) { aS.dataset.value = approvedHours.toFixed(1); aS.textContent = approvedHours.toFixed(1); }
            
            const rS = document.querySelector('.red-card .stat-value'); 
            if(rS) { rS.dataset.value = rejectedRequests; rS.textContent = rejectedRequests; }

            const eS = document.querySelector('.yellow-card .stat-value');
            if(eS) { eS.dataset.value = expiredHours.toFixed(1); eS.textContent = expiredHours.toFixed(1); }

            const tS = document.querySelector('.purple-card .stat-value');
            if(tS) { tS.dataset.value = totalOtHours.toFixed(1); tS.textContent = totalOtHours.toFixed(1); }

            // Re-trigger premium numeric animation
            if (typeof window.animateCards === 'function') { window.animateCards(); }

        } else if (json.status === 'success' && json.data.length === 0) {
            // Restore Empty state and Reset Counters
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="empty-cell">
                        <div class="empty-state">
                            <p class="empty-desc" style="margin-top: 10px;">No overtime data found for the selected period.</p>
                        </div>
                    </td>
                </tr>
            `;
            const pS = document.querySelector('.blue-card .stat-value'); if(pS) { pS.dataset.value = 0; pS.textContent = '0'; }
            const aS = document.querySelector('.green-card .stat-value'); if(aS) { aS.dataset.value = 0.0; aS.textContent = '0.0'; }
            const rS = document.querySelector('.red-card .stat-value'); if(rS) { rS.dataset.value = 0; rS.textContent = '0'; }
            
            const eS = document.querySelector('.yellow-card .stat-value'); if(eS) { eS.dataset.value = 0; eS.textContent = '0.0'; }
            const tS = document.querySelector('.purple-card .stat-value'); if(tS) { tS.dataset.value = 0; tS.textContent = '0.0'; }

        } else if (json.status === 'error') {
            tbody.innerHTML = `<tr><td colspan="9" class="empty-cell" style="padding:40px;"><div style="color:var(--text-muted);"><i>Database error: ${json.message || 'Unknown error occurred.'}</i></div></td></tr>`;
        }
        
    } catch (err) {
        console.error("Could not fetch API successfully. Is MySQL offline?", err);
    }
}

// Ensure the Send Modal exists and is wired up at runtime
function setupSendModal() {
    if (document.getElementById('ot-send-modal')) return;

    const modalHTML = `
    <div id="ot-send-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div class="ot-modal-content" style="background:var(--bg-surface); padding:24px; border-radius:var(--radius-lg); width:90%; max-width:480px; box-shadow:0 10px 25px rgba(0,0,0,0.1); position:relative;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:1.1rem; font-weight:600;">Submit Overtime Report</h3>
                <button id="ot-close-btn" style="background:none; border:none; cursor:pointer; font-size:1.4rem; color:var(--text-muted); padding:0; line-height:1;">&times;</button>
            </div>
            
            <div class="ot-details" id="ot-modal-details" style="margin-bottom:16px; font-size:0.875rem; color:var(--text-secondary); background: #f8fafc; padding:12px; border-radius:10px;">
            </div>

            <div class="manager-selection" style="margin-bottom:16px;">
                <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; margin-bottom:6px;">Select Approving Manager *</label>
                <select id="ot-manager-select" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--bg-muted); font-size:0.875rem; color:var(--text-primary); outline:none; cursor:pointer;">
                    <option value="">-- Choose Manager --</option>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px;">Fill overtime report (Min 15 words) *</label>
                <textarea id="ot-report-input" placeholder="Explain the reason for overtime in at least 15 words..." 
                    style="width:100%; height:110px; border:1px solid var(--bg-muted); border-radius:8px; padding:12px; font-size:0.875rem; color:var(--text-primary); outline:none; transition:border-color 0.2s; resize:none;"></textarea>
                <div id="ot-word-count" style="font-size:0.75rem; color:var(--text-muted); text-align:right; margin-top:6px;">0 / 15 words</div>
            </div>

            <div style="text-align:right;">
                <button id="ot-submit-btn" class="btn-primary" style="background:#f97316; display:inline-flex; align-items:center; gap:6px; outline:none;">
                    <i class="ph ph-paper-plane-right"></i> Send Report
                </button>
            </div>

            <!-- Success State (Hidden) -->
            <div id="ot-success-view" style="display:none; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px 0;">
                <div class="success-icon-wrapper" style="width:64px; height:64px; background:#dcfce7; color:#10b981; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <i class="ph ph-check-circle" style="font-size:3rem;"></i>
                </div>
                <h3 style="font-size:1.2rem; color:var(--text-primary); margin-bottom:8px;">Sent Successfully!</h3>
                <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:20px;">Your overtime request has been sent to the manager. Please wait for approval.</p>
                <button id="ot-ok-btn" class="btn-primary" style="background:#0f172a; width:120px;">Okay</button>
            </div>
        </div>
    </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modal = document.getElementById('ot-send-modal');
    const closeBtn = document.getElementById('ot-close-btn');
    const submitBtn = document.getElementById('ot-submit-btn');
    const input = document.getElementById('ot-report-input');
    const wordCount = document.getElementById('ot-word-count');

    // Auto-close bindings
    closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    // Validate 15 words visually dynamically
    input.addEventListener('input', () => {
        const text = input.value.trim();
        const words = text === "" ? 0 : text.split(/\s+/).length;
        wordCount.textContent = `${words} / 15 words`;
        wordCount.style.color = (words >= 15) ? '#10b981' : '#ef4444'; 
    });

    // Handle form submission mapped seamlessly back to the database
    submitBtn.addEventListener('click', async () => {
        const text = input.value.trim();
        const words = text === "" ? 0 : text.split(/\s+/).length;
        
        if (words < 15) { alert("Your report is too short. Please write at least 15 words. (Current count: " + words + ")"); return; }

        const recordId = submitBtn.dataset.recordId;
        const managerId = document.getElementById('ot-manager-select').value;
        
        if (!managerId) { alert("Please select a manager for approval."); return; }

        // Switch to "Sending" Animation State
        const modalContainer = document.querySelector('.ot-modal-content');
        const mainElements = modalContainer.querySelectorAll('.ot-details, .manager-selection, div:has(textarea), #ot-submit-btn, label, h3'); 
        const successView = document.getElementById('ot-success-view');

        // Capture original states to hide them
        const originalDisplay = [];
        mainElements.forEach(el => { 
            if (el.id !== 'ot-success-view' && !el.closest('#ot-success-view')) {
                originalDisplay.push({ el, display: el.style.display });
                el.style.display = 'none';
            }
        });

        const loadingMsg = document.createElement('div');
        loadingMsg.id = 'ot-loading-msg';
        loadingMsg.innerHTML = `
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px 0;">
                <i class="ph ph-circle-notch" style="font-size:3rem; color:#f97316; animation: spin 1s linear infinite; margin-bottom:16px;"></i>
                <p style="font-size:1rem; color:var(--text-secondary); font-weight:500;">Sending your overtime to the manager...</p>
            </div>
        `;
        modalContainer.appendChild(loadingMsg);

        try {
            const fd = new FormData();
            fd.append('attendance_id', recordId);
            fd.append('report', text);
            fd.append('manager_id', managerId);
            const res = await fetch('api_submit_overtime.php', { method: 'POST', body: fd, cache: 'no-store' });
            const result = await res.json();
            
            loadingMsg.remove();

            if (result.status === 'success') {
                successView.style.display = 'flex';
                // Attach close logic
                document.getElementById('ot-ok-btn').onclick = () => {
                   document.getElementById('ot-send-modal').style.display = 'none';
                   fetchTableData(); // Refresh the list
                };
            } else {
                // Restore UI on error
                originalDisplay.forEach(item => item.el.style.display = item.display);
                alert(result.message || "Failed to submit report.");
            }
        } catch (err) {
            loadingMsg.remove();
            originalDisplay.forEach(item => item.el.style.display = item.display);
            alert("Network error. Please try again.");
        }
    });
}

// Intercept clicks directly on the table wrapper (Event Delegation) so dynamic rows don't require slow binding
document.addEventListener('click', (e) => {
    const sendBtn = e.target.closest('.send-btn');
    const viewBtn = e.target.closest('.view-btn');
    const resubmitBtn = e.target.closest('.resubmit-btn');
    
    if (sendBtn || viewBtn || resubmitBtn) {
        setupSendModal();
        
        const btn = sendBtn || viewBtn || resubmitBtn;
        const record = JSON.parse(btn.dataset.record);
        const modal = document.getElementById('ot-send-modal');
        const details = document.getElementById('ot-modal-details');
        const submitBtn = document.getElementById('ot-submit-btn');
        const input = document.getElementById('ot-report-input');
        const wordCount = document.getElementById('ot-word-count');
        const mgrSelect = document.getElementById('ot-manager-select');
        const successView = document.getElementById('ot-success-view');

        // Reset visibility on open
        successView.style.display = 'none';
        const modalContainer = document.querySelector('.ot-modal-content');
        if (modalContainer) {
            modalContainer.querySelectorAll('.ot-details, .manager-selection, div:has(textarea), #ot-submit-btn, label, h3').forEach(el => {
                if (el.id !== 'ot-success-view' && !el.closest('#ot-success-view')) {
                    el.style.display = '';
                }
            });
        }
        const existingLoader = document.getElementById('ot-loading-msg');
        if (existingLoader) existingLoader.remove();

        // Populate Managers Dropdown
        mgrSelect.innerHTML = '<option value="">-- Choose Manager --</option>';
        if (window.allManagers && window.allManagers.length > 0) {
            // Determine which ID to use as default (Record-specific selection vs Global assignment)
            const targetManagerId = record.overtime_manager_id || window.assignedManagerId;

            window.allManagers.forEach(mgr => {
                const opt = document.createElement('option');
                opt.value = mgr.id;
                opt.textContent = mgr.name;
                if (parseInt(mgr.id) === parseInt(targetManagerId)) {
                    opt.selected = true;
                }
                mgrSelect.appendChild(opt);
            });
        }

        const fancyDate = new Date(record.submission_date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric'});
        const workReport = record.work_report ? record.work_report.replace(/"/g, '&quot;') : 'No daily report available';

        // 1. Update Core Details
        let detailHTML = `
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span><strong>Date:</strong> ${fancyDate}</span> 
                <span><strong>OT Hours:</strong> ${record.calculated_ot}h</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                <span><strong>Shift End:</strong> ${record.end_time.substring(0,5)}</span> 
                <span><strong>Punch Out:</strong> ${record.punch_out_time.substring(0,5)}</span>
            </div>
            <div style="border-top: 1px solid rgba(0,0,0,0.05); padding-top: 8px; margin-bottom: 8px;">
                <strong style="color:var(--text-primary);">Daily Work Report:</strong>
                <div style="margin-top: 4px; font-style: italic; color: var(--text-muted); line-height: 1.5; word-break: break-word;">"${workReport}"</div>
            </div>
        `;

        if (record.status === 'rejected') {
            const reasonText = record.rejection_reason || 'No specific feedback provided by the manager.';
            detailHTML += `
                <div style="border-top: 1px solid rgba(220, 38, 38, 0.1); padding: 12px; border-radius: 8px; background: #fff1f2; margin-top: 8px;">
                    <strong style="color:#e11d48; display:flex; align-items:center; gap:5px;"><i class="ph ph-warning-circle"></i> Manager's Feedback:</strong>
                    <div style="margin-top: 4px; color: #9f1239; line-height: 1.5; font-size: 0.85rem;">${reasonText}</div>
                </div>
            `;
        }

        details.innerHTML = detailHTML;
        
        // 2. Handle View vs Submit Mode
        const isExplicitlyExpired = record.is_expired === true || record.is_expired === 1 || String(record.status).toLowerCase() === 'expired';
        const currentResubmits = parseInt(record.resubmit_count) || 0;
        const isClosed = isExplicitlyExpired || record.status === 'approved' || (record.status === 'rejected' && currentResubmits >= 2);
        const shouldBeViewOnly = isClosed || (!!viewBtn && record.status !== 'rejected');

        if (shouldBeViewOnly) {
            // View Only Mode
            input.value = (record.overtime_report === 'No Reason Provided') ? 'No overtime report submitted.' : record.overtime_report;
            input.disabled = true;
            mgrSelect.disabled = true;
            wordCount.style.display = 'none';
            
            // Adjust Submit button for visibility only OR hide it
            if (isClosed) {
                const statusLabel = isExplicitlyExpired ? 'Expired' : record.status.charAt(0).toUpperCase() + record.status.slice(1);
                const statusColor = isExplicitlyExpired ? '#92400e' : (record.status === 'approved' ? '#059669' : '#dc2626');
                submitBtn.innerHTML = `This request is ${statusLabel}`;
                submitBtn.style.background = '#f3f4f6';
                submitBtn.style.color = statusColor;
                submitBtn.style.borderColor = '#e5e7eb';
                submitBtn.disabled = true;
                submitBtn.style.display = 'inline-flex';
            } else {
                submitBtn.style.display = 'none';
            }
        } else {
            // Submit/Resubmit Mode
            input.disabled = false;
            mgrSelect.disabled = false;
            input.value = (record.overtime_report === 'No Reason Provided') ? '' : record.overtime_report;
            wordCount.style.display = 'block';
            submitBtn.style.display = 'inline-flex';
            submitBtn.disabled = false;
            
            if (record.status === 'rejected') {
                const currentResubmits = parseInt(record.resubmit_count) || 0;
                if (currentResubmits >= 2) {
                    submitBtn.style.background = '#f3f4f6';
                    submitBtn.style.color = '#ef4444';
                    submitBtn.style.borderColor = '#fee2e2';
                    submitBtn.innerHTML = '<i class="ph ph-warning-circle"></i> Permanently Rejected';
                    submitBtn.disabled = true;
                    wordCount.innerHTML = `<strong>Max resubmissions (2/2) reached.</strong>`;
                    wordCount.style.color = '#ef4444';
                } else {
                    submitBtn.style.background = '#f97316';
                    submitBtn.style.color = '#fff';
                    submitBtn.innerHTML = `<i class="ph ph-arrows-counter-clockwise"></i> Resubmit Overtime (${currentResubmits}/2)`;
                    wordCount.innerHTML = `${input.value.trim() === '' ? 0 : input.value.trim().split(/\s+/).length} / 15 words`;
                }
            } else {
                submitBtn.style.background = '#f97316';
                submitBtn.style.color = '#fff';
                submitBtn.innerHTML = '<i class="ph ph-paper-plane-right"></i> Send Report';
            }

            if (record.status !== 'rejected') {
                const words = input.value.trim() === '' ? 0 : input.value.trim().split(/\s+/).length;
                wordCount.textContent = `${words} / 15 words`;
            }
        }

        submitBtn.dataset.recordId = record.id;
        modal.style.display = 'flex';
    }
});
