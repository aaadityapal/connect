(function(global) {
    let modalInjected = false;

    function injectTaskModal() {
        if (modalInjected) return;
        modalInjected = true;

        // Inject CSS
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'components/modals/task-modal.css';
        document.head.appendChild(link);

        // Fetch HTML and append to body
        fetch('components/modals/task-modal.html')
            .then(res => res.text())
            .then(html => {
                const div = document.createElement('div');
                div.innerHTML = html;
                document.body.appendChild(div.firstElementChild);
                bindModalEvents();
            })
            .catch(err => console.error("Error loading task modal:", err));
    }

    function bindModalEvents() {
        const overlay = document.getElementById('taskModalOverlay');
        const closeBtn = document.getElementById('taskModalClose');
        const cancelBtn = document.getElementById('taskModalCancel');

        function close() {
            overlay.classList.remove('open');
            // Hide dropdowns too
            document.querySelectorAll('.task-dropdown').forEach(d => d.classList.remove('show'));
            setTimeout(() => { overlay.style.display = 'none'; }, 200);
        }

        closeBtn.addEventListener('click', close);
        if(cancelBtn) cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });

        const markDoneBtn = document.getElementById('taskModalMarkDone');
    }

    function openTaskModal(taskData) {
        const overlay = document.getElementById('taskModalOverlay');
        if (!overlay) {
            console.warn("Task modal not yet loaded.");
            return;
        }

        // Hydrate data
        document.getElementById('tModalTitle').textContent = taskData.projectStage || taskData.title || 'Untitled Task';
        
        const personEl = document.querySelector('.task-modal-person');
        if (taskData.hideAssignedTo) {
            if (personEl) personEl.style.display = 'none';
        } else {
            if (personEl) personEl.style.display = 'flex';
            document.getElementById('tModalPerson').textContent = taskData.person || 'Unassigned';
        }
        
        document.getElementById('tModalDot').style.background = taskData.dotColor || '#cbd5e1';
        
        const badge = document.getElementById('tModalStatus');
        if (badge) {
            const st = taskData.status || 'Pending';
            badge.textContent = st;
            let bg = '#feebc8', col = '#9c4221', bd = '#fbd38d';
            if (st === 'Completed') {
                bg = '#dcfce7'; col = '#16a34a'; bd = '#bbf7d0';
            } else if (st === 'In Progress') {
                bg = '#dbeafe'; col = '#1d4ed8'; bd = '#bfdbfe';
            } else if (st === 'Cancelled') {
                bg = '#fee2e2'; col = '#dc2626'; bd = '#fecaca';
            } else if (st === 'Pending') {
                bg = '#e0f2fe'; col = '#0284c7'; bd = '#bae6fd';
            }
            badge.style.background = bg;
            badge.style.color = col;
            badge.style.borderColor = bd;
        }

        const abEl = document.getElementById('tModalAssignedBy');
        if (abEl) abEl.textContent = taskData.assignedBy || 'Manager John';
        
        const fromEl = document.getElementById('tModalDateFrom');
        if (fromEl) fromEl.textContent = taskData.dateFrom || 'Not set';
        
        const toEl = document.getElementById('tModalDateTo');
        if (toEl) {
            toEl.textContent = taskData.dateTo || taskData.dateFrom || 'Not set';
        }

        let durStr = '1 hr';
        if (taskData.durationStr) {
            durStr = taskData.durationStr;
        } else if (taskData.duration) {
            const h = Math.floor(taskData.duration / 60);
            const m = taskData.duration % 60;
            if (h > 0 && m > 0) durStr = `${h} hr ${m} mins`;
            else if (h > 0) durStr = `${h} hr`;
            else durStr = `${m} mins`;
        }
        else if (taskData.durationDays) durStr = taskData.durationDays + ' Day' + (taskData.durationDays > 1 ? 's' : '');
        document.getElementById('tModalDuration').textContent = durStr;

        const descEl = document.getElementById('tModalDesc');
        if (descEl) {
            descEl.textContent = taskData.desc || 'No description provided for this task.';
        }

        // ── Extension History toggle & panel ──────────────────────────────
        const histPanel  = document.getElementById('tModalHistoryPanel');
        const histList   = document.getElementById('tModalHistoryList');
        let   histToggle = document.getElementById('tModalHistoryToggle');

        // Always reset panel state on each open
        if (histPanel)  histPanel.style.display = 'none';
        if (histToggle) histToggle.classList.remove('active');

        const history = (taskData.extension_history && Array.isArray(taskData.extension_history))
            ? taskData.extension_history : [];

        if (histToggle) {
            // Clone first to remove any old event listeners
            const newToggle = histToggle.cloneNode(true);
            histToggle.parentNode.replaceChild(newToggle, histToggle);
            histToggle = newToggle; // point to the live DOM node

            if (history.length > 0) {
                // Update inner span text on the cloned node
                // (cannot use #id selector inside a subtree — use tag/position instead)
                const spans = newToggle.querySelectorAll('span');
                if (spans[0]) spans[0].textContent = history.length;          // count
                if (spans[1]) spans[1].textContent = history.length === 1 ? '' : 's'; // plural

                newToggle.style.display = 'inline-flex';

                newToggle.addEventListener('click', () => {
                    const isOpen = histPanel.style.display !== 'none';
                    if (isOpen) {
                        histPanel.style.display = 'none';
                        newToggle.classList.remove('active');
                    } else {
                        _renderHistoryTimeline(histList, history);
                        histPanel.style.display = 'block';
                        newToggle.classList.add('active');
                    }
                });
            } else {
                newToggle.style.display = 'none';
            }
        }


        // Handle assignee statuses

        const assigneesArea = document.getElementById('tModalAssigneesArea');
        const assigneesList = document.getElementById('tModalAssigneesList');
        if (assigneesArea && assigneesList) {
            if (taskData.assignee_statuses && taskData.assignee_statuses.length > 1) {
                assigneesList.innerHTML = '';
                taskData.assignee_statuses.forEach(ast => {
                    const row = document.createElement('div');
                    Object.assign(row.style, {
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                        background: '#f8fafc', padding: '6px 12px', borderRadius: '6px', fontSize: '0.8rem'
                    });
                    
                    const nameSpan = document.createElement('span');
                    nameSpan.textContent = ast.name;
                    nameSpan.style.color = '#334155';
                    nameSpan.style.fontWeight = '500';
                    
                    const statusSpan = document.createElement('span');
                    statusSpan.textContent = ast.status;
                    if (ast.status === 'Completed') {
                        statusSpan.style.color = '#16a34a';
                        statusSpan.style.fontWeight = '600';
                        statusSpan.innerHTML = '<i class="fa-solid fa-check" style="margin-right:4px;"></i> Done';
                    } else {
                        statusSpan.style.color = '#64748b';
                        statusSpan.style.fontWeight = '500';
                        statusSpan.innerHTML = '<i class="fa-regular fa-clock" style="margin-right:4px;"></i> Pending';
                    }
                    
                    const rightBox = document.createElement('div');
                    rightBox.style.display = 'flex';
                    rightBox.style.alignItems = 'center';
                    rightBox.style.gap = '8px';

                    if (ast.extended) {
                        const extSpan = document.createElement('span');
                        extSpan.innerHTML = `<i class="fa-solid fa-clock-rotate-left"></i> ${ast.extension_count || ''}`;
                        extSpan.title = `Extended ${ast.extension_count} time(s)`;
                        extSpan.style.display = 'flex';
                        extSpan.style.alignItems = 'center';
                        extSpan.style.gap = '4px';
                        extSpan.style.color = '#854d0e';
                        extSpan.style.background = '#fef08a';
                        extSpan.style.padding = '4px 8px';
                        extSpan.style.borderRadius = '6px';
                        extSpan.style.fontSize = '0.75rem';
                        extSpan.style.fontWeight = '700';
                        rightBox.appendChild(extSpan);
                    }
                    
                    rightBox.appendChild(statusSpan);

                    row.appendChild(nameSpan);
                    row.appendChild(rightBox);
                    assigneesList.appendChild(row);
                });
                assigneesArea.style.display = 'block';
            } else {
                assigneesArea.style.display = 'none';
            }
        }

        // Setup the Mark as Done button
        const markDoneBtn = document.getElementById('taskModalMarkDone');
        if (markDoneBtn) {
            let disableUndo = false;
            let isCompleted = (taskData.status === 'Completed');
            // Use individual completion time for the undo timer
            const completionTime = taskData.my_completed_at;
            if (isCompleted && completionTime) {
                const diffMs = new Date() - new Date(completionTime);
                if (diffMs > 30 * 60 * 1000) {
                    disableUndo = true; // 30 mins passed for THIS user
                }
            }

            // Remove previous event listeners by cloning
            const newMarkDoneBtn = markDoneBtn.cloneNode(true);
            markDoneBtn.parentNode.replaceChild(newMarkDoneBtn, markDoneBtn);
            
            if (isCompleted) {
                if (disableUndo) {
                    newMarkDoneBtn.innerHTML = '<i class="fa-solid fa-lock"></i> Locked';
                    newMarkDoneBtn.title = 'Cannot undo after 30 mins';
                    newMarkDoneBtn.style.cursor = 'not-allowed';
                    newMarkDoneBtn.style.opacity = '0.6';
                    newMarkDoneBtn.style.background = '#f1f5f9';
                    newMarkDoneBtn.style.color = '#64748b';
                    newMarkDoneBtn.style.borderColor = '#cbd5e1';
                } else {
                    newMarkDoneBtn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Undo';
                    newMarkDoneBtn.title = 'Mark Pending';
                    newMarkDoneBtn.style.cursor = 'pointer';
                    newMarkDoneBtn.style.opacity = '1';
                    newMarkDoneBtn.style.background = '#fffbeb';
                    newMarkDoneBtn.style.color = '#b45309';
                    newMarkDoneBtn.style.borderColor = '#fde68a';
                }
            } else {
                newMarkDoneBtn.innerHTML = '<i class="fa-solid fa-check"></i> Mark as Done';
                newMarkDoneBtn.title = 'Mark Done';
                newMarkDoneBtn.style.cursor = 'pointer';
                newMarkDoneBtn.style.opacity = '1';
                newMarkDoneBtn.style.background = '';
                newMarkDoneBtn.style.color = '';
                newMarkDoneBtn.style.borderColor = '';
            }

            newMarkDoneBtn.style.display = 'inline-flex';
            
            newMarkDoneBtn.addEventListener('click', () => {
                if (isCompleted && disableUndo) return;

                if (!taskData.id) {
                    alert('Cannot update task (Missing ID)');
                    return;
                }
                
                const newStatus = isCompleted ? 'Pending' : 'Completed';
                
                if (!isCompleted) {
                    try { new Audio('tones/task_done.wav').play(); } catch(e){}
                }

                fetch('api/update_task_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ task_id: taskData.id, status: newStatus })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('tModalStatus');
                        if (badge) {
                            badge.textContent = newStatus;
                            if (newStatus === 'Completed') {
                                badge.style.background = '#dcfce7';
                                badge.style.color = '#16a34a';
                                badge.style.borderColor = '#bbf7d0';
                            } else {
                                badge.style.background = '#e0f2fe';
                                badge.style.color = '#0284c7';
                                badge.style.borderColor = '#bae6fd';
                            }
                        }
                        
                        // Toggle local state
                        isCompleted = !isCompleted;
                        if (isCompleted) {
                            newMarkDoneBtn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Undo';
                            newMarkDoneBtn.style.background = '#fffbeb';
                            newMarkDoneBtn.style.color = '#b45309';
                            newMarkDoneBtn.style.borderColor = '#fde68a';
                        } else {
                            newMarkDoneBtn.innerHTML = '<i class="fa-solid fa-check"></i> Mark as Done';
                            newMarkDoneBtn.style.background = ''; // reset to default CSS
                            newMarkDoneBtn.style.color = '';
                            newMarkDoneBtn.style.borderColor = '';
                        }
                        
                        // Close modal after a delay and refresh tasks if on tasks page
                        setTimeout(() => {
                            const overlay = document.getElementById('taskModalOverlay');
                            overlay.classList.remove('open');
                            setTimeout(() => { overlay.style.display = 'none'; }, 200);
                            
                            // Rehydrate timelines if ScheduleTimeline object exists
                            if (window.ScheduleTimeline && typeof window.ScheduleTimeline.init === 'function') {
                                window.ScheduleTimeline.init();
                            }
                            
                            // ── SYNC: Refresh the "My Tasks" card if it exists
                            if (window.fetchMyTasks) {
                                window.fetchMyTasks(window.currentFilter || new Date().toISOString().split('T')[0]);
                            }

                            // If neither component exists, fallback to reload
                            if (!window.ScheduleTimeline && !window.fetchMyTasks) {
                                window.location.reload();
                            }
                        }, 500);
                    } else {
                        alert('Error updating task: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => console.warn('[TaskModal] Status update failed:', err));
            });
        }

        // Setup the Extend button
        const extendBtn = document.getElementById('taskModalExtend');
        if (extendBtn) {
            let isUserDone = (taskData.status === 'Completed');
            
            // If there's a list of assignee statuses, check the logged-in user specifically
            if (taskData.assignee_statuses && window.loggedUserName) {
                const myEntry = taskData.assignee_statuses.find(a => a.name === window.loggedUserName);
                if (myEntry && myEntry.status === 'Completed') {
                    isUserDone = true;
                }
            } else if (taskData.person === window.loggedUserName && taskData.status === 'Completed') {
                isUserDone = true;
            }

            if (isUserDone) {
                extendBtn.style.display = 'none';
            } else {
                extendBtn.style.display = ''; // Restore default display
                const newExtendBtn = extendBtn.cloneNode(true);
                extendBtn.parentNode.replaceChild(newExtendBtn, extendBtn);
            
                newExtendBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (typeof window.openExtendDeadlineModal === 'function') {
                        // Close the current modal first to prevent overlapping UI bugs
                        overlay.classList.remove('open');
                        setTimeout(() => { 
                            overlay.style.display = 'none'; 
                            
                            // Pass taskData mimicking the task object expected by my-tasks
                            const simulatedTask = {
                                id: taskData.id,
                                title: taskData.projectStage || taskData.title,
                                desc: taskData.desc,
                                status: taskData.status,
                                due_date: taskData.due_date,
                                due_time_24: taskData.due_time_24,
                                extension_count: taskData.extension_count,
                                previous_due_date: taskData.previous_due_date,
                                previous_due_time: taskData.previous_due_time
                            };
                            window.openExtendDeadlineModal(simulatedTask, 'schedule');
                        }, 200);
                    } else {
                        alert("Extend deadline modal is not loaded.");
                    }
                });
            }
        }

        // Open
        overlay.style.display = 'flex';
        // forced reflow
        void overlay.offsetWidth;
        overlay.classList.add('open');
    }

    // ── Extension History Renderer ─────────────────────────────────────────
    function _renderHistoryTimeline(container, history) {
        if (!container) return;
        container.innerHTML = '';

        function _fmtDate(dateStr) {
            if (!dateStr) return 'N/A';
            const d = new Date(dateStr);
            if (isNaN(d)) return dateStr;
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function _fmtTime(timeStr) {
            if (!timeStr) return '';
            // timeStr is "HH:MM:SS" or "HH:MM"
            const parts = timeStr.split(':');
            let h = parseInt(parts[0], 10);
            const m = parts[1] || '00';
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return ` ${h}:${m} ${ampm}`;
        }

        function _fmtAt(dtStr) {
            if (!dtStr) return '';
            const d = new Date(dtStr);
            if (isNaN(d)) return dtStr;
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                 + ' at '
                 + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        history.forEach((entry, i) => {
            const wrap = document.createElement('div');
            wrap.className = 'tl-history-entry';
            wrap.style.animationDelay = (i * 60) + 'ms';

            // Node
            const node = document.createElement('div');
            node.className = 'tl-history-node';
            node.innerHTML = '<i class="fa-solid fa-arrow-up-right-from-square"></i>';

            // Card
            const card = document.createElement('div');
            card.className = 'tl-history-card';

            // Card top row — badge + user
            const top = document.createElement('div');
            top.className = 'tl-history-card-top';

            const badge = document.createElement('span');
            badge.className = 'tl-history-badge';
            badge.innerHTML = `<i class="fa-solid fa-clock-rotate-left"></i> Extension #${entry.extension_number}`;

            const user = document.createElement('span');
            user.className = 'tl-history-user';
            user.innerHTML = `<i class="fa-solid fa-user"></i> ${entry.user_name || 'Unknown'}`;

            top.appendChild(badge);
            top.appendChild(user);

            // Dates row
            const dates = document.createElement('div');
            dates.className = 'tl-history-dates';

            const fromLabel = document.createElement('span');
            fromLabel.className = 'tl-history-from';
            fromLabel.textContent = _fmtDate(entry.previous_due_date) + _fmtTime(entry.previous_due_time);

            const arrow = document.createElement('i');
            arrow.className = 'fa-solid fa-arrow-right tl-history-arrow';

            const toLabel = document.createElement('span');
            toLabel.className = 'tl-history-to';
            toLabel.textContent = _fmtDate(entry.new_due_date) + _fmtTime(entry.new_due_time);

            dates.appendChild(fromLabel);
            dates.appendChild(arrow);
            dates.appendChild(toLabel);

            // Days-added delta chip
            if (entry.days_added !== null && entry.days_added !== undefined) {
                const delta = document.createElement('span');
                delta.className = 'tl-history-delta';
                const sign = entry.days_added >= 0 ? '+' : '';
                delta.textContent = `${sign}${entry.days_added}d`;
                dates.appendChild(delta);
            }

            // Timestamp
            const ts = document.createElement('div');
            ts.className = 'tl-history-time';
            ts.innerHTML = `<i class="fa-regular fa-clock" style="margin-right:4px;"></i>${_fmtAt(entry.extended_at)}`;

            card.appendChild(top);
            card.appendChild(dates);
            card.appendChild(ts);

            wrap.appendChild(node);
            wrap.appendChild(card);
            container.appendChild(wrap);
        });
    }
    // ──────────────────────────────────────────────────────────────────────────

    // Expose API
    global.TaskModal = {
        init: injectTaskModal,
        open: openTaskModal
    };

    // Auto-init on load
    window.addEventListener('DOMContentLoaded', () => {
        global.TaskModal.init();
    });
})(window);
