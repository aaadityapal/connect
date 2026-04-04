(function(global) {
    let modalInjected = false;
    let taskCountdownTimer = null;
    let progressSaveTimer = null;

    function clearTaskCountdown() {
        if (taskCountdownTimer) {
            clearInterval(taskCountdownTimer);
            taskCountdownTimer = null;
        }
    }

    function getFallbackDurationText(taskData) {
        let durStr = '1 hr';
        if (taskData.durationStr) {
            durStr = taskData.durationStr;
        } else if (taskData.duration) {
            const h = Math.floor(taskData.duration / 60);
            const m = taskData.duration % 60;
            if (h > 0 && m > 0) durStr = `${h} hr ${m} mins`;
            else if (h > 0) durStr = `${h} hr`;
            else durStr = `${m} mins`;
        } else if (taskData.durationDays) {
            durStr = taskData.durationDays + ' Day' + (taskData.durationDays > 1 ? 's' : '');
        }
        return durStr;
    }

    function parseDueDateTime(taskData) {
        if (taskData && taskData.due_date) {
            const timePart = taskData.due_time_24 ? String(taskData.due_time_24).slice(0, 8) : '23:59:59';
            const isoLike = `${taskData.due_date}T${timePart.length === 5 ? timePart + ':00' : timePart}`;
            const due = new Date(isoLike);
            if (!isNaN(due.getTime())) return due;
        }

        if (taskData && taskData.dateTo) {
            const parsed = new Date(String(taskData.dateTo).replace(' - ', ' '));
            if (!isNaN(parsed.getTime())) return parsed;
        }

        return null;
    }

    function formatRemaining(ms) {
        const total = Math.max(0, Math.floor(ms / 1000));
        let remaining = total;

        const yearSec = 365 * 24 * 60 * 60;
        const monthSec = 30 * 24 * 60 * 60;
        const weekSec = 7 * 24 * 60 * 60;
        const daySec = 24 * 60 * 60;
        const hourSec = 60 * 60;

        const y = Math.floor(remaining / yearSec); remaining %= yearSec;
        const mo = Math.floor(remaining / monthSec); remaining %= monthSec;
        const w = Math.floor(remaining / weekSec); remaining %= weekSec;
        const d = Math.floor(remaining / daySec); remaining %= daySec;
        const h = Math.floor(remaining / hourSec); remaining %= hourSec;
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;

        const parts = [];
        if (y > 0) parts.push(`${y}y`);
        if (mo > 0) parts.push(`${mo}mo`);
        if (w > 0) parts.push(`${w}w`);
        if (d > 0) parts.push(`${d}d`);
        if (h > 0 || parts.length) parts.push(`${h}h`);
        if (m > 0 || parts.length) parts.push(`${m}m`);
        parts.push(`${String(s).padStart(2, '0')}s`);

        return `${parts.join(' ')} left`;
    }

    function getCountdownTheme(diffMs) {
        if (diffMs <= 0) {
            return { bg: '#fef2f2', color: '#b91c1c', border: '#fecaca' }; // expired
        }
        if (diffMs <= 60 * 60 * 1000) {
            return { bg: '#fff1f2', color: '#be123c', border: '#fecdd3' }; // <1h
        }
        if (diffMs <= 24 * 60 * 60 * 1000) {
            return { bg: '#fff7ed', color: '#c2410c', border: '#fed7aa' }; // <24h
        }
        if (diffMs <= 7 * 24 * 60 * 60 * 1000) {
            return { bg: '#fffbeb', color: '#a16207', border: '#fde68a' }; // <7d
        }
        return { bg: '#ecfdf5', color: '#047857', border: '#a7f3d0' }; // safe
    }

    function applyDurationTheme(el, theme) {
        if (!el || !theme) return;
        el.style.padding = '2px 8px';
        el.style.borderRadius = '999px';
        el.style.fontWeight = '700';
        el.style.border = `1px solid ${theme.border}`;
        el.style.background = theme.bg;
        el.style.color = theme.color;
    }

    function normalizeProgress(val) {
        const n = Number(val);
        if (!Number.isFinite(n)) return 0;
        const clamped = Math.max(0, Math.min(100, n));
        return Math.round(clamped / 5) * 5;
    }

    function getProgressTheme(progress) {
        if (progress >= 100) return { bar: 'linear-gradient(90deg, #22c55e 0%, #16a34a 100%)', text: '#166534' };
        if (progress >= 75) return { bar: 'linear-gradient(90deg, #14b8a6 0%, #0f766e 100%)', text: '#0f766e' };
        if (progress >= 50) return { bar: 'linear-gradient(90deg, #60a5fa 0%, #2563eb 100%)', text: '#1d4ed8' };
        if (progress >= 25) return { bar: 'linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%)', text: '#b45309' };
        return { bar: 'linear-gradient(90deg, #fda4af 0%, #ef4444 100%)', text: '#b91c1c' };
    }

    function persistTaskProgress(taskData, progress) {
        if (!taskData || !taskData.id) return;
        fetch('api/update_task_progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskData.id, progress_percent: progress })
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) {
                console.warn('[TaskModal] Progress save failed:', data?.error || 'Unknown');
            }
        })
        .catch(err => console.warn('[TaskModal] Progress network error:', err));
    }

    function initProgressUI(taskData, canActOnTask) {
        const section = document.getElementById('tModalProgressSection');
        const fill = document.getElementById('tModalProgressBarFill');
        const valueEl = document.getElementById('tModalProgressValue');
        const range = document.getElementById('tModalProgressRange');
        const minus = document.getElementById('tModalProgressMinus');
        const plus = document.getElementById('tModalProgressPlus');

        if (!section || !fill || !valueEl || !range || !minus || !plus) return;

        const rawInitial = Number(taskData?.progress ?? taskData?.progress_percent ?? taskData?.completion_percent ?? 0);
        let progress = Number.isFinite(rawInitial) ? Math.max(0, Math.min(100, rawInitial)) : 0;
        if (canActOnTask) {
            progress = normalizeProgress(progress);
        }

        const freshMinus = minus.cloneNode(true);
        const freshPlus = plus.cloneNode(true);
        const freshRange = range.cloneNode(true);
        minus.parentNode.replaceChild(freshMinus, minus);
        plus.parentNode.replaceChild(freshPlus, plus);
        range.parentNode.replaceChild(freshRange, range);

        freshRange.min = '0';
        freshRange.max = '100';
        freshRange.step = '5';

        const schedulePersist = () => {
            if (!canActOnTask) return;
            if (progressSaveTimer) clearTimeout(progressSaveTimer);
            progressSaveTimer = setTimeout(() => {
                persistTaskProgress(taskData, progress);
            }, 220);
        };

        const render = () => {
            if (canActOnTask) {
                progress = normalizeProgress(progress);
            } else {
                progress = Math.max(0, Math.min(100, Number(progress) || 0));
            }

            const theme = getProgressTheme(progress);
            fill.style.width = `${progress}%`;
            fill.style.background = theme.bar;
            const displayText = Number.isInteger(progress) ? String(progress) : String(Math.round(progress * 100) / 100).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
            valueEl.textContent = `${displayText}%`;
            valueEl.style.color = theme.text;
            freshRange.value = String(canActOnTask ? progress : normalizeProgress(progress));
            taskData.progress = progress; // keep latest locally for UI flow

            freshMinus.disabled = !canActOnTask || progress <= 0;
            freshPlus.disabled = !canActOnTask || progress >= 100;
            freshRange.disabled = !canActOnTask;
            section.classList.toggle('readonly', !canActOnTask);
        };

        freshMinus.addEventListener('click', () => {
            if (!canActOnTask) return;
            progress = normalizeProgress(progress - 5);
            render();
            schedulePersist();
        });

        freshPlus.addEventListener('click', () => {
            if (!canActOnTask) return;
            progress = normalizeProgress(progress + 5);
            render();
            schedulePersist();
        });

        freshRange.addEventListener('input', () => {
            if (!canActOnTask) return;
            progress = normalizeProgress(freshRange.value);
            render();
            schedulePersist();
        });

        render();
    }

    function attachTaskCountdown(taskData) {
        const durEl = document.getElementById('tModalDuration');
        if (!durEl) return;

        clearTaskCountdown();

        const status = String(taskData?.status || '').trim().toLowerCase();
        if (status === 'completed') {
            durEl.textContent = 'Completed';
            applyDurationTheme(durEl, { bg: '#dcfce7', color: '#166534', border: '#bbf7d0' });
            return;
        }
        if (status === 'cancelled') {
            durEl.textContent = 'Cancelled';
            applyDurationTheme(durEl, { bg: '#fee2e2', color: '#b91c1c', border: '#fecaca' });
            return;
        }

        const due = parseDueDateTime(taskData);
        if (!due) {
            durEl.textContent = getFallbackDurationText(taskData);
            applyDurationTheme(durEl, { bg: '#eef2ff', color: '#4338ca', border: '#c7d2fe' });
            return;
        }

        const tick = () => {
            const now = new Date();
            const diff = due.getTime() - now.getTime();
            if (diff <= 0) {
                durEl.textContent = 'Time up';
                applyDurationTheme(durEl, getCountdownTheme(diff));
                clearTaskCountdown();
                return;
            }
            durEl.textContent = formatRemaining(diff);
            applyDurationTheme(durEl, getCountdownTheme(diff));
        };

        tick();
        taskCountdownTimer = setInterval(tick, 1000);
    }

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
            clearTaskCountdown();
            if (progressSaveTimer) {
                clearTimeout(progressSaveTimer);
                progressSaveTimer = null;
            }
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

        clearTaskCountdown();
        if (progressSaveTimer) {
            clearTimeout(progressSaveTimer);
            progressSaveTimer = null;
        }

        // Permission gate: only assigned users can perform task actions.
        // Prefer backend-provided flag; fallback to username match when needed.
        let canActOnTask = false;
        if (typeof taskData.can_act === 'boolean') {
            canActOnTask = taskData.can_act;
        } else if (Array.isArray(taskData.assignee_statuses) && window.loggedUserName) {
            const me = String(window.loggedUserName).trim().toLowerCase();
            canActOnTask = taskData.assignee_statuses.some(a => String(a.name || '').trim().toLowerCase() === me);
        } else if (Array.isArray(taskData.assignees) && window.loggedUserName) {
            const me = String(window.loggedUserName).trim().toLowerCase();
            canActOnTask = taskData.assignees.some(n => String(n || '').trim().toLowerCase() === me);
        }

        const readOnlyHint = document.getElementById('taskModalReadOnlyHint');
        if (readOnlyHint) {
            readOnlyHint.style.display = canActOnTask ? 'none' : 'inline-flex';
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

        attachTaskCountdown(taskData);

        const descEl = document.getElementById('tModalDesc');
        if (descEl) {
            descEl.textContent = taskData.desc || 'No description provided for this task.';
        }

        initProgressUI(taskData, canActOnTask);

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
            if (!canActOnTask) {
                const hiddenBtn = markDoneBtn.cloneNode(true);
                markDoneBtn.parentNode.replaceChild(hiddenBtn, markDoneBtn);
                hiddenBtn.style.display = 'none';
            } else {
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
                            clearTaskCountdown();
                            overlay.classList.remove('open');
                            setTimeout(() => { overlay.style.display = 'none'; }, 200);

                            // Trigger global event for other components (like Action Required modal)
                            window.dispatchEvent(new Event('taskUpdate'));
                            
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
        }

        // Setup the Extend button
        const extendBtn = document.getElementById('taskModalExtend');
        if (extendBtn) {
            if (!canActOnTask) {
                const hiddenExt = extendBtn.cloneNode(true);
                extendBtn.parentNode.replaceChild(hiddenExt, extendBtn);
                hiddenExt.style.display = 'none';
            } else {
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
                        clearTaskCountdown();
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
