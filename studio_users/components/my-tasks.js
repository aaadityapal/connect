// =====================================================
// MY TASKS — Component Logic
// Data fetched from api/fetch_my_tasks.php
// =====================================================

document.addEventListener('DOMContentLoaded', function () {

    const taskContainer = document.getElementById('taskListContainer');
    if (!taskContainer) return;

    // ── Initialise global state ───────────────────────────────────────────────
    // currentFilter is now a specific date like "2026-03-14", or "daily" for today
    const todayStr = new Date().toISOString().split('T')[0];
    window.currentFilter = window.currentFilter || todayStr;
    window.tasksData     = {}; // Cache by exact date string

    // ─────────────────────────────────────────────────────────────────────────
    // fetchMyTasks(period)
    // Loads tasks from DB and re-renders the list
    // ─────────────────────────────────────────────────────────────────────────
    window.fetchMyTasks = function (period) {
        // period is now expected to be a YYYY-MM-DD string, or 'daily' referencing today
        let dateQuery = period;
        if (!dateQuery || dateQuery === 'daily') {
            dateQuery = todayStr;
        }
        window.currentFilter = dateQuery;

        // Show skeleton loader
        taskContainer.innerHTML = `
            <div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:0.9rem;">
                <i class="fa-solid fa-spinner fa-spin" style="margin-right:0.5rem;"></i> Loading tasks...
            </div>`;

        // Update progress bar to loading state
        const progressText = document.getElementById('progressText');
        const progressPct  = document.getElementById('progressPercentage');
        const fill         = document.getElementById('taskProgressFill');
        if (progressText) progressText.textContent = '...';
        if (progressPct)  progressPct.textContent  = '';
        if (fill)         fill.style.width         = '0%';

        // API will receive exactly ?date=2026-03-14 instead of periodic named strings
        fetch(`api/fetch_my_tasks.php?date=${encodeURIComponent(dateQuery)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    taskContainer.innerHTML = `<div style="padding:1rem;color:#ef4444;font-size:0.85rem;text-align:center;">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right:0.4rem;"></i>Failed to load tasks.</div>`;
                    return;
                }

                // Store in tasksData so renderTasks can use them
                window.tasksData[dateQuery] = data.tasks || [];
                window.renderTasks(dateQuery);
            })
            .catch(err => {
                console.error('[MyTasks] Fetch error:', err);
                taskContainer.innerHTML = `<div style="padding:1rem;color:#ef4444;font-size:0.85rem;text-align:center;">
                    <i class="fa-solid fa-wifi" style="margin-right:0.4rem;"></i>Network error. Please refresh.</div>`;
            });
    };

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: Sort tasks locally duplicating the backend's exact order
    // 'In Progress' -> 'Pending' -> 'Completed' -> 'Cancelled'
    // ─────────────────────────────────────────────────────────────────────────
    function sortTasksByStatusAndDate(tasks) {
        const order = { 'In Progress': 1, 'Pending': 2, 'Completed': 3, 'Cancelled': 4 };
        return [...tasks].sort((a, b) => {
            const rankA = order[a.status || 'Pending'] || 5;
            const rankB = order[b.status || 'Pending'] || 5;
            if (rankA !== rankB) return rankA - rankB;
            
            // If status is same, sort by due date -> due time
            const dateA = a.due_date ? new Date(a.due_date + 'T' + (a.due_time_24 || '23:59:59')) : new Date(8640000000000000);
            const dateB = b.due_date ? new Date(b.due_date + 'T' + (b.due_time_24 || '23:59:59')) : new Date(8640000000000000);
            return dateA - dateB;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // renderTasks(type)
    // Clears + rebuilds #taskListContainer from window.tasksData[type]
    // ─────────────────────────────────────────────────────────────────────────
    window.renderTasks = function (type) {
        taskContainer.innerHTML = '';
        const tasks = window.tasksData[type] || [];

        // ── Progress bar ──────────────────────────────────────────────────────
        const total     = tasks.length;
        const completed = tasks.filter(t => t.checked).length;
        const percent   = total > 0 ? Math.round((completed / total) * 100) : 0;

        const progressText = document.getElementById('progressText');
        const progressPct  = document.getElementById('progressPercentage');
        const fill         = document.getElementById('taskProgressFill');

        if (progressText) progressText.textContent = `${completed}/${total} Completed`;
        if (progressPct)  progressPct.textContent  = `${percent}%`;

        let barGradient, accentColor;
        if      (percent === 100) { barGradient = 'linear-gradient(90deg,#10b981,#34d399)'; accentColor = '#10b981'; }
        else if (percent >= 67)   { barGradient = 'linear-gradient(90deg,#6366f1,#818cf8)'; accentColor = '#6366f1'; }
        else if (percent >= 34)   { barGradient = 'linear-gradient(90deg,#f59e0b,#fbbf24)'; accentColor = '#d97706'; }
        else if (percent > 0)     { barGradient = 'linear-gradient(90deg,#ef4444,#f87171)'; accentColor = '#ef4444'; }
        else                      { barGradient = 'linear-gradient(90deg,#e2e8f0,#e2e8f0)'; accentColor = '#94a3b8'; }

        if (fill) { fill.style.background = barGradient; fill.style.width = `${percent}%`; }
        if (progressPct) progressPct.style.color = accentColor;

        // ── Empty state ───────────────────────────────────────────────────────
        if (tasks.length === 0) {
            let periodLabel = 'on ' + type;
            if (type === todayStr) periodLabel = 'today';

            taskContainer.innerHTML = `
                <div style="text-align:center;padding:2.5rem 1rem;color:#94a3b8;">
                    <i class="fa-regular fa-clipboard" style="font-size:2.2rem;display:block;margin-bottom:0.75rem;opacity:0.4;"></i>
                    <span style="font-weight:600;display:block;margin-bottom:0.25rem;font-size:0.95rem;color:#64748b;">No tasks assigned</span>
                    <span style="font-size:0.82rem;">You have no tasks due ${periodLabel}.</span>
                </div>`;
            return;
        }

        // ── Task cards ────────────────────────────────────────────────────────
        tasks.forEach(task => {
            const el = document.createElement('div');
            el.draggable = true;

            // Urgency dot colour from time-left to deadline
            let priorityClass = 'priority-green';
            let dotColor      = '#10b981'; // Green

            if (task.checked || task.time === 'Completed' || task.time === 'No Deadline') {
                priorityClass = 'priority-green';
                dotColor      = '#10b981';
            } else if (task.deadline) {
                const diffHours = (new Date(task.deadline) - new Date()) / 3600000;
                if      (diffHours < 3)  { priorityClass = 'priority-red';    dotColor = '#ef4444'; }
                else if (diffHours < 6)  { priorityClass = 'priority-orange'; dotColor = '#f97316'; }
                else if (diffHours < 48) { priorityClass = 'priority-yellow'; dotColor = '#eab308'; }
                else                     { priorityClass = 'priority-green';  dotColor = '#10b981'; }
            }
            task.dotColor = dotColor; // Attach for the modal

            el.className = `task-item ${priorityClass}${task.checked ? ' completed-card' : ''}`;

            const badgeLabel = task.badge === 'Med' ? 'Medium' : task.badge;
            const badgeIcon  = task.badge === 'High' ? '<i class="fa-solid fa-fire"></i>'
                             : task.badge === 'Med'  ? '<i class="fa-solid fa-bolt"></i>'
                             :                         '<i class="fa-solid fa-leaf"></i>';

            const assigneeDisplay = (task.assignees || []).join(', ');
            const isCompleted     = task.checked || task.time === 'Completed';
            const timeText        = (task.time || '').replace(/<[^>]*>?/gm, '').trim();

            let disableUndo = false;
            if (isCompleted && task.completed_at) {
                const diffMs = new Date() - new Date(task.completed_at);
                if (diffMs > 30 * 60 * 1000) {
                    disableUndo = true; // 30 mins passed
                }
            }

            el.innerHTML = `
                <div class="task-check">
                    <input type="checkbox" id="t-${task.id}" ${task.checked ? 'checked' : ''} ${disableUndo ? 'disabled' : ''}>
                    <label for="t-${task.id}" ${disableUndo ? 'style="cursor:not-allowed;" title="30 minutes have passed. Cannot undo."' : ''}></label>
                </div>
                <div class="task-urgency-dot"></div>
                <div class="task-content-wrap">
                    <div class="task-item-title ${isCompleted ? 'completed' : ''}">${task.title}</div>
                    <div class="task-item-desc">${task.desc}</div>
                    <div class="task-item-meta">
                        <span class="task-time-pill">
                            ${isCompleted
                                ? '<i class="fa-solid fa-check" style="color:#22c55e;"></i>'
                                : '<i class="fa-regular fa-clock"></i>'}
                            ${timeText}
                        </span>
                        ${assigneeDisplay
                            ? `<span class="task-assignee-label"><i class="fa-solid fa-user" style="font-size:0.58rem;"></i> ${assigneeDisplay}</span>`
                            : ''}
                        <span class="task-status-badge status-${(task.status || 'pending').toLowerCase().replace(' ','-')}"
                              style="font-size:0.7rem;padding:0.15rem 0.5rem;border-radius:999px;font-weight:600;
                                     background:${task.status==='Completed'?'#dcfce7':task.status==='In Progress'?'#dbeafe':task.status==='Cancelled'?'#fee2e2':'#fef9c3'};
                                     color:${task.status==='Completed'?'#16a34a':task.status==='In Progress'?'#1d4ed8':task.status==='Cancelled'?'#dc2626':'#92400e'};">
                            ${task.status || 'Pending'}
                        </span>
                    </div>
                </div>
                <div class="task-item-actions">
                    ${!isCompleted ? `
                    <button class="task-icon-btn extend-btn" title="Extend Deadline">
                        <i class="fa-regular fa-calendar-plus"></i> Extend
                    </button>
                    ` : ''}
                    ${isCompleted && disableUndo ? `
                    <button class="task-icon-btn done-btn" style="cursor:not-allowed;opacity:0.6;" title="Cannot undo after 30 mins" disabled>
                        <i class="fa-solid fa-lock"></i> Locked
                    </button>
                    ` : `
                    <button class="task-icon-btn done-btn ${isCompleted ? 'undo-icon' : 'done-icon'}"
                            title="${isCompleted ? 'Mark Pending' : 'Mark Done'}">
                        <i class="fa-solid ${isCompleted ? 'fa-rotate-left' : 'fa-check'}"></i>
                        ${isCompleted ? 'Undo' : 'Done'}
                    </button>
                    `}
                </div>
            `;

            // Drag
            el.addEventListener('dragstart', () => el.classList.add('dragging'));
            el.addEventListener('dragend',   () => el.classList.remove('dragging'));

            // Open Modal on click (matching the timeline behaviour)
            const openModalNodes = el.querySelectorAll('.task-content-wrap, .task-urgency-dot, .task-item-title');
            openModalNodes.forEach(node => {
                node.style.cursor = 'pointer';
                node.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (window.TaskModal && window.TaskModal.open) {
                        window.TaskModal.open({
                            ...task,
                            person: task.assignees ? task.assignees[0] : 'Unassigned',
                            dateFrom: task.modalDateFrom, 
                            dateTo: task.modalDateTo,
                            desc: task.desc
                        });
                    }
                });
            });

            // Extend deadline
            const extendBtn = el.querySelector('.extend-btn');
            if (extendBtn) {
                extendBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    if (typeof window.openExtendDeadlineModal === 'function') window.openExtendDeadlineModal(task, type);
                });
            }

            const doneBtn = el.querySelector('.done-btn');
            if (doneBtn && !doneBtn.hasAttribute('disabled')) {
                doneBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    if (el.classList.contains('is-updating')) return; 

                    const newStatus = task.checked ? 'Pending' : 'Completed';
                    
                    // ── Show Loading State ──
                    el.classList.add('is-updating');
                    el.style.pointerEvents = 'none'; // Lock interaction
                    const originalHtml = doneBtn.innerHTML;
                    doneBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Processing...`;
                    doneBtn.style.opacity = '0.8';

                    // Persist to DB first
                    fetch('api/update_task_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ task_id: task.id, status: newStatus })
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            // Update local task state
                            task.checked = !task.checked;
                            task.status  = newStatus;
                            task.time    = task.checked ? 'Completed' : (task.due_time || 'No Deadline');
                            
                            if (task.checked) {
                                const now = new Date().toISOString();
                                task.completed_at    = now;
                                task.my_completed_at = now;
                                try { new Audio('tones/task_done.wav').play(); } catch(e){}
                            } else {
                                task.completed_at    = null;
                                task.my_completed_at = null;
                            }

                            // Smooth exit animation
                            el.style.transition = 'all 0.35s cubic-bezier(0.4, 0, 0.2, 1)';
                            el.style.opacity = '0';
                            el.style.transform = 'translateY(15px) scale(0.98)';

                            setTimeout(() => {
                                window.tasksData[type] = sortTasksByStatusAndDate(window.tasksData[type]);
                                window.renderTasks(type);
                                
                                if (window.ScheduleTimeline && typeof window.ScheduleTimeline.init === 'function') {
                                    window.ScheduleTimeline.init();
                                }
                            }, 350);
                        } else {
                            // Revert on failure
                            el.classList.remove('is-updating');
                            el.style.pointerEvents = 'auto';
                            doneBtn.innerHTML = originalHtml;
                            doneBtn.style.opacity = '1';
                            alert('Erro: ' + (res.error || 'Failed to update task.'));
                        }
                    })
                    .catch(err => {
                        console.warn('[MyTasks] Status update failed:', err);
                        el.classList.remove('is-updating');
                        el.style.pointerEvents = 'auto';
                        doneBtn.innerHTML = originalHtml;
                        doneBtn.style.opacity = '1';
                    });
                });
            }

            // Checkbox change
            const checkbox = el.querySelector('input');
            if (checkbox && !checkbox.hasAttribute('disabled')) {
                checkbox.addEventListener('change', ev => {
                    if (el.classList.contains('is-updating')) {
                        ev.preventDefault();
                        return;
                    }

                    const newStatus = ev.target.checked ? 'Completed' : 'Pending';
                    const originalChecked = !ev.target.checked; // To revert if needed

                    // ── Show Loading State ──
                    el.classList.add('is-updating');
                    el.style.pointerEvents = 'none';

                    // Persist to DB first
                    fetch('api/update_task_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ task_id: task.id, status: newStatus })
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            task.checked = ev.target.checked;
                            task.status  = newStatus;
                            task.time    = task.checked ? 'Completed' : (task.due_time || 'No Deadline');
                            
                            if (task.checked) {
                                const now = new Date().toISOString();
                                task.completed_at    = now;
                                task.my_completed_at = now;
                                try { new Audio('tones/task_done.wav').play(); } catch(e){}
                            } else {
                                task.completed_at    = null;
                                task.my_completed_at = null;
                            }

                            // Smooth exit animation
                            el.style.transition = 'all 0.35s cubic-bezier(0.4, 0, 0.2, 1)';
                            el.style.opacity = '0';
                            el.style.transform = 'translateY(15px) scale(0.98)';

                            setTimeout(() => {
                                window.tasksData[type] = sortTasksByStatusAndDate(window.tasksData[type]);
                                window.renderTasks(type);

                                if (window.ScheduleTimeline && typeof window.ScheduleTimeline.init === 'function') {
                                    window.ScheduleTimeline.init();
                                }
                            }, 350);
                        } else {
                            // Revert
                            ev.target.checked = originalChecked;
                            el.classList.remove('is-updating');
                            el.style.pointerEvents = 'auto';
                            alert('Error: ' + (res.error || 'Failed to update task.'));
                        }
                    })
                    .catch(err => {
                        console.warn('[MyTasks] Status update failed:', err);
                        ev.target.checked = originalChecked;
                        el.classList.remove('is-updating');
                        el.style.pointerEvents = 'auto';
                    });
                });
            }

            taskContainer.appendChild(el);
        });

        // ── Recurrence Expiry Alert ───────────────────────────────────────────
        // After rendering, check if any visible task is the last recurrence instance.
        // Show the modal for the first one found (oldest last-recurrence task).
        const expiringTask = tasks.find(t => t.is_last_recurrence && !t.checked);
        if (expiringTask && typeof RecurrenceExpiryModal !== 'undefined') {
            // Small delay so the task list finishes painting first
            setTimeout(() => RecurrenceExpiryModal.show(expiringTask), 600);
        }

        // Re-apply status filter
        applyMyTaskStatusFilter();
    };

    // ─────────────────────────────────────────────────────────────────────────
    // Status filter — All / Pending / In Progress / Done
    // ─────────────────────────────────────────────────────────────────────────
    let myTaskStatusFilter = 'all';

    function applyMyTaskStatusFilter() {
        taskContainer.querySelectorAll('.task-item').forEach(item => {
            if (myTaskStatusFilter === 'all') { item.classList.remove('mytask-hidden'); return; }
            const isDone      = item.querySelector('input[type="checkbox"]')?.checked;
            const timeEl      = item.querySelector('.time');
            const timeText    = timeEl ? timeEl.textContent.trim().toLowerCase() : '';
            const isCompleted = isDone || timeText === 'completed';

            if      (myTaskStatusFilter === 'done')
                item.classList.toggle('mytask-hidden', !isCompleted);
            else if (myTaskStatusFilter === 'pending')
                item.classList.toggle('mytask-hidden', isCompleted);
            else if (myTaskStatusFilter === 'inprogress') {
                const isInProgress = !isCompleted && timeText !== '' && timeText !== 'no deadline';
                item.classList.toggle('mytask-hidden', !isInProgress);
            } else {
                item.classList.remove('mytask-hidden');
            }
        });
    }

    (function initMyTaskStatusFilter() {
        const filterBtn   = document.getElementById('taskStatusFilterBtn');
        const filterPanel = document.getElementById('taskStatusFilterPanel');
        const filterText  = document.getElementById('taskStatusFilterText');
        if (!filterBtn || !filterPanel) return;

        filterBtn.addEventListener('click', e => {
            e.stopPropagation();
            filterPanel.classList.toggle('mytask-panel-open');
            filterBtn .classList.toggle('mytask-open');
        });

        filterPanel.querySelectorAll('.mytask-filter-option').forEach(opt => {
            opt.addEventListener('click', () => {
                filterPanel.querySelectorAll('.mytask-filter-option').forEach(o => o.classList.remove('active'));
                opt.classList.add('active');
                myTaskStatusFilter = opt.getAttribute('data-status');
                const label = opt.querySelector('span:not(.mytask-filter-dot)')?.textContent || 'All';
                if (filterText) filterText.textContent = myTaskStatusFilter === 'all' ? 'All' : label;
                applyMyTaskStatusFilter();
                setTimeout(() => {
                    filterPanel.classList.remove('mytask-panel-open');
                    filterBtn .classList.remove('mytask-open');
                }, 200);
            });
        });

        document.addEventListener('click', e => {
            if (!filterBtn.contains(e.target) && !filterPanel.contains(e.target)) {
                filterPanel.classList.remove('mytask-panel-open');
                filterBtn .classList.remove('mytask-open');
            }
        });
    })();

    // ─────────────────────────────────────────────────────────────────────────
    // Date Picker Input Logic 
    // ─────────────────────────────────────────────────────────────────────────
    const datePicker = document.getElementById('myTasksDatePicker');
    
    if (datePicker) {
        // Set default value to today on load
        datePicker.value = window.currentFilter === 'daily' ? todayStr : window.currentFilter;
        
        // Listen for user calendar selection
        datePicker.addEventListener('change', (e) => {
            const selectedDate = e.target.value;
            if (!selectedDate) return; // Ignore if cleared out

            window.currentFilter = selectedDate;

            // If cached, render immediately
            if (window.tasksData[selectedDate] && window.tasksData[selectedDate].length > 0) {
                window.renderTasks(selectedDate);
            } else {
                window.fetchMyTasks(selectedDate);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Drag & drop reordering (client-side only)
    // ─────────────────────────────────────────────────────────────────────────
    taskContainer.addEventListener('dragover', e => {
        e.preventDefault();
        const after    = getDragAfterElement(taskContainer, e.clientY);
        const dragging = document.querySelector('.dragging');
        if (!dragging) return;
        if (after == null) taskContainer.appendChild(dragging);
        else taskContainer.insertBefore(dragging, after);
    });

    function getDragAfterElement(container, y) {
        return [...container.querySelectorAll('.task-item:not(.dragging)')].reduce((closest, child) => {
            const box    = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            return (offset < 0 && offset > closest.offset) ? { offset, element: child } : closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Boot — fetch today's tasks immediately
    // ─────────────────────────────────────────────────────────────────────────
    window.fetchMyTasks(window.currentFilter);

});
