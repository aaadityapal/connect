(function() {
    let alertedIds = JSON.parse(localStorage.getItem('alertedDeadlineTasks') || '[]');

    document.addEventListener('DOMContentLoaded', () => {
        // Initial check after 3 seconds
        setTimeout(checkUpcomingDeadlines, 3000);
        
        // Check every 60 seconds
        setInterval(checkUpcomingDeadlines, 60000);

        // Listen for task updates from other modals for immediate refresh
        window.addEventListener('taskUpdate', () => {
            console.log('[Upcoming Deadline] Task update event received, checking...');
            checkUpcomingDeadlines();
        });
    });

    function checkUpcomingDeadlines() {
        Promise.all([
            fetch('api/check_upcoming_deadlines.php').then(r => r.json()).catch(() => ({ success: false, tasks: [] })),
            fetch('api/check_pending_task_approvals.php').then(r => r.json()).catch(() => ({ success: false, tasks: [] }))
        ])
        .then(([actionRes, approvalRes]) => {
            const actionTasks = (actionRes && actionRes.success && Array.isArray(actionRes.tasks)) ? actionRes.tasks : [];
            const approvalTasks = (approvalRes && approvalRes.success && Array.isArray(approvalRes.tasks)) ? approvalRes.tasks : [];

            if (actionTasks.length > 0 || approvalTasks.length > 0) {
                showSplitModal(actionTasks, approvalTasks);
            } else {
                closeUpcomingModal();
            }
        })
        .catch(err => console.warn('[Upcoming Deadline Check] failed:', err));
    }

    function showSplitModal(actionTasks, approvalTasks) {
        const overlay = document.getElementById('upcomingDeadlineModalOverlay');
        const box = overlay ? overlay.querySelector('.udm-box') : null;
        const actionContainer = document.getElementById('udmActionList');
        const approvalContainer = document.getElementById('udmApprovalList');
        const actionCountEl = document.getElementById('udmActionCount');
        const approvalCountEl = document.getElementById('udmApprovalCount');
        const actionEmptyEl = document.getElementById('udmActionEmpty');
        const approvalEmptyEl = document.getElementById('udmApprovalEmpty');
        const tabAction = document.getElementById('udmTabAction');
        const tabApproval = document.getElementById('udmTabApproval');
        const tabActionCount = document.getElementById('udmTabActionCount');
        const tabApprovalCount = document.getElementById('udmTabApprovalCount');

        if (!overlay || !actionContainer || !approvalContainer) return;

        // Update tab counts (tablet UI)
        if (tabActionCount) tabActionCount.textContent = String(actionTasks.length || 0);
        if (tabApprovalCount) tabApprovalCount.textContent = String(approvalTasks.length || 0);

        // Default active tab: action if any, else approvals
        if (box && !box.dataset.activeTab) {
            box.dataset.activeTab = (actionTasks.length > 0) ? 'action' : 'approvals';
        } else if (box) {
            // If current tab has no items but the other does, swap
            const active = box.dataset.activeTab;
            if (active === 'action' && actionTasks.length === 0 && approvalTasks.length > 0) box.dataset.activeTab = 'approvals';
            if (active === 'approvals' && approvalTasks.length === 0 && actionTasks.length > 0) box.dataset.activeTab = 'action';
        }

        // Tabs click wiring (once)
        if (box && tabAction && tabApproval && !box.dataset.tabsBound) {
            const setActive = (tab) => {
                box.dataset.activeTab = tab;
                tabAction.setAttribute('aria-selected', tab === 'action' ? 'true' : 'false');
                tabApproval.setAttribute('aria-selected', tab === 'approvals' ? 'true' : 'false');
            };

            tabAction.addEventListener('click', () => setActive('action'));
            tabApproval.addEventListener('click', () => setActive('approvals'));
            box.dataset.tabsBound = '1';
        }

        // Ensure aria-selected matches current state
        if (box && tabAction && tabApproval) {
            const active = box.dataset.activeTab || 'action';
            tabAction.setAttribute('aria-selected', active === 'action' ? 'true' : 'false');
            tabApproval.setAttribute('aria-selected', active === 'approvals' ? 'true' : 'false');
        }
        
        // Header title
        const overdueCount = (actionTasks || []).filter(t => (t.titlePrefix || '').includes('Missed')).length;
        const totalAll = (actionTasks.length || 0) + (approvalTasks.length || 0);
        const titleEl = document.querySelector('.udm-title');
        if (titleEl) {
            if (overdueCount > 0) {
                titleEl.innerHTML = `Action Required! ⚠️ <span style="font-size:0.8rem; font-weight:500; opacity:0.7; margin-left:8px;">(${totalAll} items)</span>`;
            } else if (actionTasks.length > 0) {
                titleEl.innerHTML = `Deadlines Approaching ⏱️ <span style="font-size:0.8rem; font-weight:500; opacity:0.7; margin-left:8px;">(${totalAll} items)</span>`;
            } else {
                titleEl.innerHTML = `Approvals Pending ✅ <span style="font-size:0.8rem; font-weight:500; opacity:0.7; margin-left:8px;">(${totalAll} items)</span>`;
            }
        }

        // Counts + empty states
        if (actionCountEl) actionCountEl.textContent = `(${actionTasks.length})`;
        if (approvalCountEl) approvalCountEl.textContent = `(${approvalTasks.length})`;

        if (actionEmptyEl) actionEmptyEl.style.display = actionTasks.length ? 'none' : 'block';
        if (approvalEmptyEl) approvalEmptyEl.style.display = approvalTasks.length ? 'none' : 'block';

        // Render left pane (Action Required)
        actionContainer.innerHTML = '';
        (actionTasks || []).forEach(task => {
            const mustExtendFirst = !!task.requires_extension_before_completion || Number(task.completion_reject_count || 0) >= 2;

            const item = document.createElement('div');
            item.className = 'udm-task-item';
            item.style.borderLeft = `4px solid ${task.dotColor || '#e11d48'}`;

            item.innerHTML = `
                <div class="udm-task-info">
                    <div class="udm-task-name">${task.projectStage || task.title || 'Untitled Task'}</div>
                    <div class="udm-task-badge" style="background:${task.bgColor || '#fff1f2'}; color:${task.dotColor || '#e11d48'};">
                        <i class="fa-regular fa-clock" style="margin-right:4px;"></i>${task.time_remaining_label || ''}
                    </div>
                    ${mustExtendFirst ? `
                    <div class="udm-task-badge" style="margin-top:6px; background:#fff7ed; color:#9a3412; border-color:#fed7aa;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i>
                        Mandatory: Extend deadline first (rejected ${Number(task.completion_reject_count || 0)} times)
                    </div>` : ''}
                </div>
                <div class="udm-task-actions">
                    <button class="udm-action-btn udm-secondary-btn" data-action="extend">
                        <i class="fa-solid fa-clock-rotate-left"></i> Extend
                    </button>
                    ${mustExtendFirst ? '' : `
                    <button class="udm-action-btn udm-primary-btn" data-action="finish">
                        <i class="fa-solid fa-check"></i> Mark as Done
                    </button>`}
                </div>
            `;

            item.querySelector('[data-action="extend"]').onclick = () => {
                if (typeof window.openExtendDeadlineModal === 'function') {
                    window.openExtendDeadlineModal(task, 'schedule');
                }
            };

            const finishBtn = item.querySelector('[data-action="finish"]');
            if (finishBtn) {
                finishBtn.onclick = () => {
                    if (window.TaskModal && typeof window.TaskModal.open === 'function') {
                        window.TaskModal.open(task);
                    }
                };
            }

            actionContainer.appendChild(item);
        });

        // Render right pane (Approvals)
        approvalContainer.innerHTML = '';
        (approvalTasks || []).forEach(task => {
            const item = document.createElement('div');
            item.className = 'udm-task-item compact';
            item.style.borderLeft = `4px solid ${task.dotColor || '#16a34a'}`;

            item.innerHTML = `
                <div class="udm-task-info">
                    <div class="udm-task-name">${task.projectStage || task.title || 'Untitled Task'}</div>
                    <div class="udm-task-badge" style="background:${task.bgColor || '#dcfce7'}; color:${task.dotColor || '#16a34a'};">
                        <i class="fa-solid fa-circle-check" style="margin-right:6px;"></i>${task.time_remaining_label || 'Completed'}
                    </div>
                </div>
                <div class="udm-task-actions">
                    <button class="udm-action-btn udm-secondary-btn" data-action="view" title="View" aria-label="View">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button class="udm-action-btn udm-approve-btn" data-action="approve" title="Approve" aria-label="Approve">
                        <i class="fa-solid fa-thumbs-up"></i>
                    </button>
                    <button class="udm-action-btn udm-reject-btn" data-action="reject" title="Reject" aria-label="Reject">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                </div>
            `;

            item.querySelector('[data-action="view"]').onclick = () => {
                if (window.TaskModal && typeof window.TaskModal.open === 'function') {
                    window.TaskModal.open(task);
                }
            };

            item.querySelector('[data-action="approve"]').onclick = () => {
                approveTaskCompletion(task);
            };

            item.querySelector('[data-action="reject"]').onclick = () => {
                rejectTaskCompletion(task);
            };

            approvalContainer.appendChild(item);
        });

        
        overlay.style.display = 'flex';
        
        // Play notification sound with interaction check
        playNotificationSound();
    }

    function approveTaskCompletion(task) {
        if (!task || !task.id) return;

        fetch('api/approve_task_completion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: task.id })
        })
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success) {
                alert((data && data.error) ? data.error : 'Failed to approve task');
                return;
            }

            // Refresh both panes immediately
            window.dispatchEvent(new Event('taskUpdate'));
        })
        .catch(err => console.warn('[Approve Task] failed:', err));
    }

    function rejectTaskCompletion(task) {
        if (!task || !task.id) return;

        const ok = confirm('Reject this completion? The task will be moved back to Pending.');
        if (!ok) return;

        fetch('api/reject_task_completion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: task.id })
        })
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success) {
                alert((data && data.error) ? data.error : 'Failed to reject task');
                return;
            }

            // Refresh both panes immediately
            window.dispatchEvent(new Event('taskUpdate'));
        })
        .catch(err => console.warn('[Reject Task] failed:', err));
    }

    function playNotificationSound() {
        const audio = new Audio('tones/task_popup.wav');
        audio.play().catch(e => {
            // Silently catch autoplay restrictions to avoid console errors or 
            // unexpected playback later on a random click.
        });
    }

    function closeUpcomingModal() {
        const overlay = document.getElementById('upcomingDeadlineModalOverlay');
        if(overlay) overlay.style.display = 'none';
    }

    // Export to global for direct calling if needed
    window.checkUpcomingDeadlines = checkUpcomingDeadlines;
    window.closeUpcomingDeadlineModal = closeUpcomingModal;
})();
