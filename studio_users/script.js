document.addEventListener("DOMContentLoaded", () => {
    // Global HR Elements
    const hrCard = document.querySelector('.hr-card');
    const hrPolicyText = document.getElementById('hrPolicyText');

    // ── Task assignment / edit success sound ──────────────────────────
    const _taskSound = new Audio('tones/task_allotment.mp3');
    _taskSound.preload = 'auto';
    function playTaskSound() {
        try {
            _taskSound.currentTime = 0;
            _taskSound.play().catch(function () { }); // suppress autoplay policy errors
        } catch (e) { }
    }
    window.playTaskSound = playTaskSound; // expose to other script files
    // ─────────────────────────────────────────────────────────────────

    // ── Process incomplete / carried-over tasks (runs silently on login) ──
    // Runs 5 seconds after page load so it doesn't block initial render.
    // Finds tasks missed before last Sunday 8 PM, marks them Incomplete,
    // and creates a new Monday 08:30 AM carry-forward task automatically.
    setTimeout(() => {
        fetch('api/process_incomplete_tasks.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.processed > 0) {
                    console.log(`[System] ${data.processed} incomplete task(s) carried forward to ${data.monday}`);
                    // Refresh task list so the new Monday task appears
                    if (typeof window.fetchMyTasks === 'function') {
                        window.fetchMyTasks(window.currentFilter || new Date().toISOString().split('T')[0]);
                    }
                }
            })
            .catch(() => {}); // Silent failure — non-critical
    }, 5000);
    // ─────────────────────────────────────────────────────────────────

    // ══════════════════════════════════════════════════════
    // Load Recently Assigned Tasks (filtered by date)
    // ══════════════════════════════════════════════════════
    function isAssignedTaskLocked(taskLike) {
        const status = String(taskLike?.status || taskLike?.taskStatus || '').trim().toLowerCase();
        const completedByRaw = String(taskLike?.completed_by || taskLike?.taskCompletedBy || '').trim();
        const completedByList = completedByRaw
            ? completedByRaw.split(',').map(v => v.trim()).filter(Boolean)
            : [];

        let historyCount = 0;
        try {
            const historyRaw = taskLike?.completion_history ?? taskLike?.taskCompletionHistory ?? '{}';
            const historyObj = typeof historyRaw === 'string' ? JSON.parse(historyRaw || '{}') : (historyRaw || {});
            if (historyObj && typeof historyObj === 'object' && !Array.isArray(historyObj)) {
                historyCount = Object.keys(historyObj).length;
            }
        } catch (_) {}

        return status === 'completed' || completedByList.length > 0 || historyCount > 0;
    }

    function loadRecentTasks(dateStr) {
        const list = document.getElementById('assignedTasksList');
        const counter = document.getElementById('assignedTasksCount');
        const picker = document.getElementById('assignedTasksDateFilter');
        if (!list) return;

        // Use supplied date, or fall back to the picker's value, or today (local)
        const _d = new Date();
        const todayLocal = _d.getFullYear() + '-'
            + String(_d.getMonth() + 1).padStart(2, '0') + '-'
            + String(_d.getDate()).padStart(2, '0');
        const date = dateStr || (picker ? picker.value : '') || todayLocal;

        // Show loading state
        list.innerHTML = `<div id="assignedTasksLoader" style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:0.9rem;">
            <i class="fa-solid fa-spinner fa-spin" style="margin-right:0.5rem;"></i> Loading tasks...
        </div>`;
        if (counter) counter.textContent = '...';

        fetch(`api/fetch_recent_tasks.php?date=${encodeURIComponent(date)}`)
            .then(res => res.json())
            .then(data => {
                list.innerHTML = ''; // clear loader

                if (!data.success || !data.tasks || data.tasks.length === 0) {
                    // Format the date nicely for the empty state message
                    const displayDate = new Date(date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                    list.innerHTML = `
                        <div style="padding:2rem;text-align:center;color:#94a3b8;">
                            <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;margin-bottom:0.75rem;display:block;opacity:0.4;"></i>
                            <span style="font-size:0.9rem;font-weight:600;display:block;margin-bottom:0.25rem;">No tasks assigned</span>
                            <span style="font-size:0.8rem;opacity:0.75;">${displayDate}</span>
                        </div>`;
                    if (counter) counter.textContent = '0 tasks';
                    return;
                }

                // Render each task card
                data.tasks.forEach(task => {
                    const title = (task.project_name || 'General Task') + (task.stage_number ? ' — Stage ' + task.stage_number : '');
                    const desc = task.task_description || '';
                    const assignees = task.assigned_names || 'Unassigned';
                    const date = task.due_date_formatted || 'No Deadline';
                    const time = task.due_time_formatted || '';
                    const status = task.status || 'Pending';
                    const priority = task.priority || 'Low';
                    const isLocked = isAssignedTaskLocked(task);

                    const card = document.createElement('div');
                    card.className = 'assigned-task-item';
                    if (isLocked) card.classList.add('assigned-task-locked');
                    card.dataset.taskId = task.id;
                    card.dataset.taskName = title;
                    card.dataset.taskProjectId = task.project_id || '';
                    card.dataset.taskProjectName = task.project_name || '';
                    card.dataset.taskStageId = task.stage_id || '';
                    card.dataset.taskStageNumber = task.stage_number || '';
                    card.dataset.taskPriority = priority;
                    card.dataset.taskDescription = desc;
                    card.dataset.taskDate = task.due_date || '';
                    card.dataset.taskTime = time;
                    card.dataset.taskStatus = status;
                    card.dataset.taskCompletedBy = task.completed_by || '';
                    card.dataset.taskAssignedTo = task.assigned_to || '';
                    card.dataset.taskCompletionHistory = task.completion_history || '{}';
                    card.dataset.taskAssigneeNames = task.assigned_names || '';

                    card.innerHTML = `
                        <div class="atl-left">
                            <div class="atl-task-name" title="${title}">${title}</div>
                            <div class="atl-task-desc" style="font-size:0.85rem;color:#64748b;margin-top:5px;margin-bottom:5px;white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;" title="${desc}">${desc}</div>
                            <div class="atl-meta" style="margin-top:8px; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
                                <span class="atl-assignee"><i class="fa-solid fa-users"></i> ${assignees}</span>
                                <span class="atl-date"><i class="fa-regular fa-calendar"></i> ${date}</span>
                                ${time ? `<span class="atl-date"><i class="fa-regular fa-clock"></i> ${time}</span>` : ''}
                            </div>
                        </div>
                        <div class="atl-right">
                            <button class="atl-edit-btn assigned-edit-btn unique-edit-assigned-btn" title="${isLocked ? 'Task cannot be edited after partial/full completion' : 'Edit Task'}" ${isLocked ? 'disabled' : ''}>
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                            <button class="atl-delete-btn unique-delete-assigned-btn" title="${isLocked ? 'Task cannot be deleted after partial/full completion' : 'Delete Task'}" ${isLocked ? 'disabled' : ''}>
                                <i class="fa-solid fa-trash-can"></i> Delete
                            </button>
                        </div>
                    `;
                    list.appendChild(card);
                });

                // Update count badge
                if (counter) {
                    const c = data.tasks.length;
                    counter.textContent = c + (c === 1 ? ' task' : ' tasks');
                }
            })
            .catch(err => {
                console.error('Error loading tasks:', err);
                list.innerHTML = '<div style="padding:1rem;color:#ef4444;font-size:0.85rem;">Failed to load tasks.</div>';
                if (counter) counter.textContent = '0 tasks';
            });
    }

    // ── Init: set picker to today (local date) and load today's tasks ──
    const _picker = document.getElementById('assignedTasksDateFilter');
    const _todayLocal = (function () {
        const d = new Date();
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0');
    })();

    if (_picker) {
        _picker.value = _todayLocal;           // show today in the picker
        _picker.addEventListener('change', function () {
            loadRecentTasks(this.value);       // re-fetch on date change
        });
        _picker.addEventListener('focus', function () {
            this.style.borderColor = '#f97316';
            this.style.boxShadow = '0 0 0 3px rgba(249,115,22,0.12)';
        });
        _picker.addEventListener('blur', function () {
            this.style.borderColor = '#e2e8f0';
            this.style.boxShadow = 'none';
        });
    }

    // Fire immediately with today's local date
    loadRecentTasks(_todayLocal);

    // --- Resizable Split Layout Logic ---
    const gutter = document.getElementById('resizeGutter');
    const splitLayout = document.querySelector('.split-layout');

    if (gutter && splitLayout) {
        let isResizing = false;

        gutter.addEventListener('mousedown', (e) => {
            isResizing = true;
            document.body.style.cursor = 'col-resize';
            // Disable selection while dragging
            document.body.style.userSelect = 'none';
        });

        let rafId = null;

        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return;

            // Calculate percentage based on container width
            const containerRect = splitLayout.getBoundingClientRect();
            const x = e.clientX - containerRect.left;

            // Grid column track logic: x pixels (Project), 8px (Gutter), Remaining (Tasks)
            // Enforce min width for project (e.g. 400px) and tasks (e.g. 250px)
            const minLeft = 400;
            const minRight = 250;
            const availableWidth = containerRect.width - 8; // minus gutter

            if (x > minLeft && (availableWidth - x) > minRight) {
                const totalWidth = containerRect.width;
                const leftPercent = (x / totalWidth) * 100;
                const gutterWidth = (8 / totalWidth) * 100;
                // Use percentage-based columns for fluidity
                splitLayout.style.gridTemplateColumns = `${leftPercent}% 8px 1fr`;
            }
        });

        document.addEventListener('mouseup', () => {
            if (isResizing) {
                isResizing = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    }

    // --- Task Logic ---
    const taskContainer = document.getElementById("taskListContainer");

    // Dropdown Elements
    const dropdownTrigger = document.getElementById('dropdownTrigger');
    const dropdownMenu = document.getElementById('dropdownMenu');
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    const currentFilterText = document.getElementById('currentFilterText');
    window.currentFilter = 'daily'; // Default

    // ── Stored GPS coordinates for punch in / out API calls ────────────────
    let _punchInLat = null, _punchInLon = null, _punchInAcc = null, _punchInAddr = null;
    let _punchOutLat = null, _punchOutLon = null, _punchOutAcc = null, _punchOutAddr = null;

    // ── Geofence result cache (set when the modal checks the user's location) ────
    let _punchInGeofenceId = null, _punchInWithinGeofence = 0, _punchInDistance = null;
    let _punchOutGeofenceId = null, _punchOutWithinGeofence = 0, _punchOutDistance = null;

    // ── Geofence data cache (loaded once from DB on page load) ───────────────
    let _geofenceLocations = [];

    async function loadGeofenceLocations() {
        try {
            const res = await fetch('../api/get_geofence_locations.php');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.locations) && data.locations.length > 0) {
                _geofenceLocations = data.locations;
                console.log('[Geofence] Loaded ' + _geofenceLocations.length + ' zone(s)');
            } else {
                _geofenceLocations = [];
            }
        } catch (e) {
            console.warn('[Geofence] Could not load zones:', e.message);
            _geofenceLocations = [];
        }
        return _geofenceLocations;
    }

    // Find nearest geofence zone and check if user is inside it
    function checkGeofence(userLat, userLon) {
        if (!_geofenceLocations.length) return null;
        let nearest = null, nearestDist = Infinity;
        _geofenceLocations.forEach(zone => {
            const dist = calculateDistance(userLat, userLon, zone.latitude, zone.longitude);
            if (dist < nearestDist) { nearestDist = dist; nearest = zone; }
        });
        return { zone: nearest, distance: nearestDist, isInside: nearestDist <= nearest.radius };
    }

    // Pre-load geofence zones immediately
    loadGeofenceLocations();

    /**
     * Reverse-geocode coordinates to a human-readable address.
     * Uses OpenStreetMap Nominatim (same API as punch.php server-side).
     * Returns the address string, or a fallback coordinate string on failure.
     */
    async function reverseGeocode(lat, lon) {
        try {
            const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lon + '&zoom=18&addressdetails=1';
            const res = await fetch(url, {
                headers: { 'Accept-Language': 'en', 'User-Agent': 'HR-Attendance-System/1.0' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            return data.display_name || (lat.toFixed(5) + ', ' + lon.toFixed(5));
        } catch (e) {
            console.warn('[reverseGeocode] Failed:', e.message);
            return lat.toFixed(5) + ', ' + lon.toFixed(5);
        }
    }


    // ══════════════════════════════════════════════════════════════════════════
    // MY TASKS — moved to components/my-tasks.js (loaded via index.php)
    // window.tasksData, window.renderTasks, window.selectTaskPeriod,
    // applyMyTaskStatusFilter, initMyTaskStatusFilter, drag-and-drop
    // ══════════════════════════════════════════════════════════════════════════

    let mandatoryPolicies = [];

    // --- Dynamic HR Corner is now fully synchronized with the database in loadHRDashboardData() ---

    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // SCHEDULE TIMELINE — now handled by components/schedule-loader.js
    // (HTML: components/my-schedule.html + components/team-schedule.html)
    // (JS:   components/schedule-timeline.js)

    // --- Add Task Logic ---
    const addTaskInput = document.getElementById('newTaskInput');
    const addTaskBtn = document.getElementById('addTaskBtn');
    const addTaskDate = document.getElementById('newTaskDate');
    const addTaskTime = document.getElementById('newTaskTime');

    // Setup constraints for Date and Time
    if (addTaskDate && addTaskTime) {
        const setTimeConstraints = () => {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const strToday = `${yyyy}-${mm}-${dd}`;

            // Default to today and set min date
            if (!addTaskDate.value) addTaskDate.value = strToday;
            addTaskDate.min = strToday;

            // Restrict max time to 6:00 PM
            addTaskTime.max = '18:00';

            // Restrict time if today is selected
            if (addTaskDate.value === strToday) {
                const hh = String(today.getHours()).padStart(2, '0');
                const mn = String(today.getMinutes()).padStart(2, '0');
                addTaskTime.min = `${hh}:${mn}`;
            } else {
                addTaskTime.min = '';
            }
        };

        // Initialize strictly
        setTimeConstraints();

        // Check constantly on change
        addTaskDate.addEventListener('change', setTimeConstraints);

        // Prevent manual past time input and after 6 PM input
        addTaskTime.addEventListener('change', function () {
            if (addTaskDate.value === addTaskDate.min && this.value && this.value < this.min) {
                try {
                    if (typeof showCustomAlert === 'function') {
                        showCustomAlert('Invalid Time', 'You cannot assign a time in the past for today.', 'error');
                    } else if (typeof showToast === 'function') {
                        showToast('Cannot select a time in the past.', 'warning');
                    }
                } catch (e) { }
                this.value = this.min;
            }

            if (this.value && this.value > '18:00') {
                try {
                    if (typeof showCustomAlert === 'function') {
                        showCustomAlert('Invalid Time', 'Task deadline cannot be set after 06:00 PM.', 'error');
                    } else if (typeof showToast === 'function') {
                        showToast('Task deadline cannot be set after 06:00 PM.', 'warning');
                    }
                } catch (e) { }
                this.value = '18:00';
            }
        });
    }

    // WhatsApp style growing textarea
    if (addTaskInput) {
        addTaskInput.addEventListener('input', function () {
            this.style.height = '48px'; // Base min-height
            const scrollHeight = this.scrollHeight;

            // 3 lines (24px line-height * 3 = 72px) + 24px padding = 96px limit
            if (scrollHeight <= 96) {
                this.style.height = scrollHeight + 'px';
                this.style.overflowY = 'hidden';
            } else {
                this.style.height = '96px';
                this.style.overflowY = 'auto';
            }
        });
    }


    function addNewTask() {
        const text = addTaskInput.value.trim();
        if (!text) return;

        const dateVal = addTaskDate.value;
        const timeVal = addTaskTime ? addTaskTime.value : '';

        // Restriction: Task deadline cannot be after 06:00 PM
        if (timeVal && timeVal > '18:00') {
            if (typeof showCustomAlert === 'function') {
                showCustomAlert('Invalid Time', 'Task deadline cannot be set after 06:00 PM.', 'error');
            } else if (typeof showToast === 'function') {
                showToast('Task deadline cannot be set after 06:00 PM.', 'error');
            } else {
                alert('Task deadline cannot be set after 06:00 PM.');
            }
            return;
        }

        // Auto-calculate priority based on task time limit
        let priority = "Low";
        if (dateVal) {
            const now = new Date();
            let deadlineDate;
            if (timeVal) {
                deadlineDate = new Date(`${dateVal}T${timeVal}`);
            } else {
                deadlineDate = new Date(`${dateVal}T23:59:59`);
            }
            const diffHours = (deadlineDate - now) / (1000 * 60 * 60);

            if (diffHours < 3) {
                priority = "High";
            } else if (diffHours <= 6) {
                priority = "Med";
            }
        }
        let formattedTime = '';
        if (timeVal) {
            let parts = timeVal.split(':').map(Number);
            let h24 = parts[0], m = parts[1];
            let period = h24 >= 12 ? 'PM' : 'AM';
            let h12 = h24 % 12 || 12;
            formattedTime = String(h12).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ' ' + period;
        }

        let displayTime = "No Date";
        if (dateVal) {
            const dateObj = new Date(dateVal);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Check if today
            const isToday = dateObj.getDate() === today.getDate() &&
                dateObj.getMonth() === today.getMonth() &&
                dateObj.getFullYear() === today.getFullYear();

            if (isToday) {
                displayTime = "Today";
            } else {
                displayTime = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
        } else {
            displayTime = "No Deadline";
        }

        const projectInput = document.getElementById('projectSearchInput');
        const projectId = projectInput ? projectInput.dataset.projectId : null;
        const projectVal = projectInput ? projectInput.value.trim() : '';

        // ── Validation: Project is required & must be from search results ──
        if (!projectId || !projectVal) {
            if (typeof showCustomAlert === 'function') {
                showCustomAlert('Missing Project', 'Please search and select a valid project from the list.', 'info');
            } else {
                alert('Please search and select a valid project from the list.');
            }
            return;
        }

        const stageSelect = document.getElementById('stageSelect');
        const stageVal = stageSelect && stageSelect.value ? stageSelect.options[stageSelect.selectedIndex].text : '';

        let projectInfo = projectVal;
        if (stageVal) {
            projectInfo += ' - ' + stageVal;
        }

        const repeatVal = document.getElementById('repeatSelectText') ? document.getElementById('repeatSelectText').textContent.trim() : 'No';
        const frequencySelectText = document.getElementById('frequencySelectText');
        const freqVal = frequencySelectText ? frequencySelectText.textContent.trim() : 'Weekly';

        // --- Custom Recurrence logic ---
        let finalRecurringFreq = repeatVal === 'Yes' ? freqVal : null;
        let customNumValue = null;
        let customUnitStr = null;

        if (repeatVal === 'Yes') {
            const customGroup = document.getElementById('customFreqGroup');
            // If custom group is visible, we override the freq string with the precise "Every X Unit" format
            if (customGroup && customGroup.style.display !== 'none') {
                const numEl = document.getElementById('customFreqNum');
                const unitBtn = document.querySelector('#customFreqUnit .cfu-btn.cfu-active');
                if (numEl && unitBtn) {
                    customNumValue = parseInt(numEl.value) || 1;
                    customUnitStr = unitBtn.dataset.unit;
                    const label = customNumValue === 1 ? customUnitStr : customUnitStr + 's';
                    finalRecurringFreq = `Every ${customNumValue} ${label}`;
                }
            }
        }

        // Collect mentioned employee IDs — stored as dataset on each chip
        let assignedIds = [];
        const chips = document.querySelectorAll('#mentionWrapper .m-chip');
        chips.forEach(chip => {
            if (chip.dataset.userId) assignedIds.push(chip.dataset.userId);
        });

        // ── Validation: Assignee is required ──
        if (assignedIds.length === 0) {
            if (typeof showCustomAlert === 'function') {
                showCustomAlert('Missing Assignee', 'Please mention at least one team member using @name.', 'info');
            } else {
                alert('Please mention at least one team member using @name.');
            }
            return;
        }

        let selectedAssignees = window._getMentionedEmployees ? window._getMentionedEmployees() : [];

        const newTask = {
            id: Date.now(),
            title: projectInfo,
            desc: text,
            badge: priority,
            time: displayTime,
            rawDate: dateVal,
            checked: false,
            assignees: selectedAssignees,
            recurring: finalRecurringFreq || 'None'
        };

        const payload = {
            project_id: parseInt(projectId),
            project_name: projectVal,
            stage_id: stageSelect && stageSelect.value ? parseInt(stageSelect.value) : null,
            stage_number: stageVal || null,
            task_description: text,
            priority: priority === 'Med' ? 'Medium' : priority,
            assigned_to: assignedIds.join(','),
            assigned_names: selectedAssignees.join(','),
            due_date: dateVal || null,
            due_time: timeVal || null,
            is_recurring: repeatVal === 'Yes',
            recurrence_freq: finalRecurringFreq,
            custom_freq_value: customNumValue,
            custom_freq_unit: customUnitStr
        };

        fetch('api/save_task.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    console.error('Task save error:', data.error, data.details || '');
                } else {
                    console.log('✅ Task saved to DB with ID:', data.task_id);
                    playTaskSound(); // 🔔 play assignment tone

                    // Fetch fresh DB data to ensure it only appears here if the current user is assigned
                    if (typeof window.fetchMyTasks === 'function') {
                        window.fetchMyTasks(window.currentFilter);
                    }
                }
            })
            .catch(err => console.error('Network error saving task:', err));

        // Reset Inputs
        addTaskInput.value = '';
        addTaskInput.style.height = '48px';
        if (document.getElementById('projectSearchInput')) {
            document.getElementById('projectSearchInput').value = '';
            delete document.getElementById('projectSearchInput').dataset.projectId;
        }
        if (document.getElementById('stageSelectContainer')) document.getElementById('stageSelectContainer').style.display = 'none';
        if (document.getElementById('stageSelect')) document.getElementById('stageSelect').innerHTML = '<option value="">Select a stage...</option>';
        if (addTaskDate) {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            addTaskDate.value = `${yyyy}-${mm}-${dd}`;
            addTaskDate.dispatchEvent(new Event('change'));
        }
        if (addTaskTime) addTaskTime.value = '';
        if (document.getElementById('repeatSelectText')) document.getElementById('repeatSelectText').textContent = 'No';
        if (document.getElementById('frequencySelectText')) document.getElementById('frequencySelectText').textContent = 'Weekly';
        if (document.getElementById('frequencyGroup')) document.getElementById('frequencyGroup').style.display = 'none';
        if (window._clearMentionedEmployees) window._clearMentionedEmployees();



        // Simple animation for the new item
        const firstTask = taskContainer.querySelector('.task-item');
        if (firstTask) {
            firstTask.style.animation = 'fadeIn 0.4s ease-out';
        }

        // ── Also prepend a card to the "Recently Assigned" list ──
        const assignedList = document.getElementById('assignedTasksList');
        const assignedCount = document.getElementById('assignedTasksCount');
        if (assignedList) {
            const priorityLower = priority.toLowerCase();
            const badgeClass = priorityLower === 'high' ? 'high' : priorityLower === 'med' || priorityLower === 'medium' ? 'medium' : 'low';
            const badgeLabel = priorityLower === 'high' ? 'High' : priorityLower === 'med' || priorityLower === 'medium' ? 'Medium' : 'Low';
            const assigneeNames = selectedAssignees.length > 0 ? selectedAssignees.join(', ') : 'Unassigned';
            const dispDate = dateVal ? new Date(dateVal).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'No Deadline';

            const card = document.createElement('div');
            card.className = 'assigned-task-item';
            card.dataset.taskId = newTask.id;
            card.dataset.taskName = projectInfo;
            card.dataset.taskPriority = badgeLabel;
            card.dataset.taskDescription = text;
            card.dataset.taskDate = dateVal || '';
            card.dataset.taskTime = formattedTime || '';
            card.dataset.taskStatus = 'Pending';
            card.dataset.taskCompletedBy = '';
            card.dataset.taskCompletionHistory = '{}';
            card.dataset.taskAssignedTo = assignedIds.join(',');
            card.dataset.taskAssigneeNames = selectedAssignees.join(',');
            card.innerHTML = `
                <div class="atl-left">
                    <div class="atl-task-name" title="${projectInfo}">${projectInfo}</div>
                    <div class="atl-task-desc" style="font-size: 0.85rem; color: #64748b; margin-top: 5px; margin-bottom: 5px; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;" title="${text}">${text}</div>
                    <div class="atl-meta" style="margin-top: 8px;">
                        <span class="atl-assignee"><i class="fa-solid fa-users"></i> ${assigneeNames}</span>
                        <span class="atl-date"><i class="fa-regular fa-calendar"></i> ${dispDate}</span>
                        ${formattedTime ? `<span class="atl-date"><i class="fa-regular fa-clock"></i> ${formattedTime}</span>` : ''}
                    </div>
                </div>
                <div class="atl-right">
                    <button class="atl-edit-btn assigned-edit-btn unique-edit-assigned-btn" title="Edit Task"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                    <button class="atl-delete-btn unique-delete-assigned-btn" title="Delete Task">
                        <i class="fa-solid fa-trash-can"></i> Delete
                    </button>
                </div>
            `;
            assignedList.insertBefore(card, assignedList.firstChild);

            // Update count badge
            if (assignedCount) {
                const count = assignedList.querySelectorAll('.assigned-task-item').length;
                assignedCount.textContent = count + (count === 1 ? ' task' : ' tasks');
            }
        }
    }

    // --- @Mention Chip Input Logic ---
    (function () {
        const _w = document.getElementById('mentionWrapper');
        const _i = document.getElementById('multiSelectInput');
        const _m = document.getElementById('multiSelectMenu');
        if (!_w || !_i || !_m) return;

        let EMP = [];
        let selected = [];

        // Fetch real active users from the database
        fetch('api/fetch_users.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.users) {
                    EMP = data.users;
                }
            })
            .catch(err => console.error('Error fetching users:', err));

        // Inject styles once
        if (!document.getElementById('mention-styles')) {
            const s = document.createElement('style');
            s.id = 'mention-styles';
            s.textContent = `
                @keyframes chipIn {
                    from { opacity:0; transform:scale(0.75) translateX(-6px); }
                    to   { opacity:1; transform:scale(1) translateX(0); }
                }
                #mentionWrapper:focus-within {
                    border-color: var(--primary-color, #4f46e5) !important;
                    box-shadow: 0 0 0 3px rgba(79,70,229,0.12) !important;
                }
                .m-chip {
                    display:inline-flex; align-items:center; gap:5px;
                    border-radius:20px; padding:2px 10px 2px 4px;
                    font-size:0.78rem; font-weight:700; flex-shrink:0;
                    animation: chipIn 0.2s cubic-bezier(.22,1,.36,1) both;
                    white-space: nowrap;
                }
                .m-chip-x { cursor:pointer; opacity:0.55; font-size:0.72rem; margin-left:2px; transition:opacity 0.15s; line-height:1; }
                .m-chip-x:hover { opacity:1; }
                .m-opt { display:flex; align-items:center; gap:0.6rem; padding:0.45rem 1rem; cursor:pointer; transition:background 0.15s; }
                .m-opt:hover, .m-opt.m-opt-active { background:#f3f4f6; }
                .m-opt.m-opt-active { outline: 2px solid #6366f1 !important; border-radius: 6px; }
            `;
            document.head.appendChild(s);
        }

        function getActiveMention() {
            const val = _i.value;
            const lastAt = val.lastIndexOf('@');
            if (lastAt === -1) return null;
            const after = val.substring(lastAt + 1);
            if (after.includes(' ')) return null;
            return { start: lastAt, term: after.toLowerCase() };
        }

        let activeIdx = -1;   // keyboard navigation index

        function setActiveOption(opts, idx) {
            opts.forEach(o => o.classList.remove('m-opt-active'));
            if (idx >= 0 && idx < opts.length) {
                opts[idx].classList.add('m-opt-active');
                opts[idx].scrollIntoView({ block: 'nearest' });
            }
        }

        function renderMenu(term) {
            // @all magic — select everyone instantly
            if (term === 'all') {
                EMP.filter(e => !selected.includes(e.name)).forEach(e => {
                    selected.push(e.name);
                    addChip(e.name);
                });
                const mention = getActiveMention();
                if (mention) _i.value = _i.value.substring(0, mention.start);
                hideMenu();
                _i.focus();
                return;
            }

            const filtered = EMP.filter(e =>
                !selected.includes(e.name) &&
                (term === '' ||
                    e.name.toLowerCase().split(' ').some(w => w.startsWith(term)) ||
                    e.name.toLowerCase().includes(term))
            );
            if (!filtered.length) { hideMenu(); return; }

            activeIdx = -1;
            _m.innerHTML = filtered.map(e => `
                <div class="m-opt" data-name="${e.name}">
                    <div style="width:32px;height:32px;border-radius:50%;background:${e.color};color:#fff;font-size:0.65rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">${e.initials}</div>
                    <div style="display:flex; flex-direction:column; gap:0.1rem; flex:1;">
                        <span style="font-size:0.88rem;font-weight:600;color:#1e293b;line-height:1.2;">${e.name}</span>
                        ${e.role ? `<span style="font-size:0.72rem;color:#6366f1;font-weight:500;">${e.role}</span>` : ''}
                    </div>
                </div>
            `).join('');

            _m.querySelectorAll('.m-opt').forEach(opt => {
                opt.addEventListener('mousedown', ev => {
                    ev.preventDefault();
                    pickEmployee(opt.dataset.name);
                });
                opt.addEventListener('mouseenter', () => {
                    const opts = [..._m.querySelectorAll('.m-opt')];
                    activeIdx = opts.indexOf(opt);
                    setActiveOption(opts, activeIdx);
                });
            });
            _m.style.display = 'block';
        }

        function hideMenu() {
            _m.style.display = 'none';
            _m.innerHTML = '';
        }

        function pickEmployee(name) {
            if (selected.includes(name)) { hideMenu(); return; }
            selected.push(name);
            const mention = getActiveMention();
            if (mention) _i.value = _i.value.substring(0, mention.start);
            addChip(name);
            hideMenu();
            _i.focus();
        }

        function addChip(name) {
            const emp = EMP.find(e => e.name === name) || { color: '#94a3b8', initials: name.substring(0, 2).toUpperCase(), id: null };
            const chip = document.createElement('span');
            chip.className = 'm-chip';
            chip.dataset.name = name;
            if (emp.id) chip.dataset.userId = emp.id;  // Store DB id for saving
            chip.style.cssText = `background:${emp.color}18;border:1px solid ${emp.color}55;color:${emp.color};`;
            chip.innerHTML = `
                <span style="width:20px;height:20px;border-radius:50%;background:${emp.color};color:#fff;font-size:0.58rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">${emp.initials}</span>
                <span>${name.split(' ')[0]}</span>
                <span class="m-chip-x" data-name="${name}">&#x2715;</span>
            `;
            chip.querySelector('.m-chip-x').addEventListener('click', e => {
                e.stopPropagation();
                removeChip(name);
            });
            _w.insertBefore(chip, _i);
        }

        function removeChip(name) {
            selected = selected.filter(n => n !== name);
            const chip = _w.querySelector('.m-chip[data-name="' + name + '"]');
            if (chip) chip.remove();
        }

        _w.addEventListener('click', () => _i.focus());

        _i.addEventListener('input', () => {
            const m = getActiveMention();
            if (m !== null) renderMenu(m.term);
            else hideMenu();
        });

        _i.addEventListener('keydown', e => {
            const menuOpen = _m.style.display === 'block';
            const opts = [..._m.querySelectorAll('.m-opt')];

            if (menuOpen && e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = (activeIdx + 1) % opts.length;
                setActiveOption(opts, activeIdx);
                return;
            }
            if (menuOpen && e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = (activeIdx - 1 + opts.length) % opts.length;
                setActiveOption(opts, activeIdx);
                return;
            }
            if (menuOpen && e.key === 'Enter') {
                e.preventDefault();
                if (activeIdx >= 0 && activeIdx < opts.length) {
                    pickEmployee(opts[activeIdx].dataset.name);
                } else if (opts.length === 1) {
                    // Only one result — auto-pick it
                    pickEmployee(opts[0].dataset.name);
                }
                return;
            }
            if (e.key === 'Backspace' && _i.value === '' && selected.length > 0) {
                removeChip(selected[selected.length - 1]);
            }
            if (e.key === 'Escape') { hideMenu(); activeIdx = -1; }
        });

        _i.addEventListener('blur', () => setTimeout(hideMenu, 160));

        window._getMentionedEmployees = () => [...selected];
        window._clearMentionedEmployees = () => {
            selected = [];
            _w.querySelectorAll('.m-chip').forEach(c => c.remove());
            _i.value = '';
            hideMenu();
        };
    })();

    // --- Autocomplete Logic for Project Search ---
    const projectSearchInput = document.getElementById('projectSearchInput');
    const projectSearchMenu = document.getElementById('projectSearchMenu');
    let searchTimeout = null;

    if (projectSearchInput && projectSearchMenu) {

        // Function to fetch project stages
        const fetchProjectStages = async (projectId) => {
            const stageContainer = document.getElementById('stageSelectContainer');
            const stageSelect = document.getElementById('stageSelect');
            if (!stageContainer || !stageSelect) return;

            try {
                const response = await fetch(`api/fetch_project_stages.php?project_id=${projectId}`);
                const data = await response.json();

                if (data.success && data.stages && data.stages.length > 0) {
                    stageSelect.innerHTML = '<option value="">Select a stage...</option>' + data.stages.map(s =>
                        `<option value="${s.id}">${s.stage_number || ('Stage ' + s.id)}</option>`
                    ).join('');
                    stageContainer.style.display = 'block';
                } else {
                    stageSelect.innerHTML = '<option value="">Select a stage...</option>';
                    stageContainer.style.display = 'none';
                }
            } catch (err) {
                console.error("Error fetching stages:", err);
                stageContainer.style.display = 'none';
            }
        };
        // Function to fetch projects
        const fetchProjects = async (query) => {
            try {
                const response = await fetch(`api/search_projects.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.success && data.projects.length > 0) {
                    projectSearchMenu.innerHTML = data.projects.map(project => `
                        <div class="project-search-item" data-id="${project.id}" data-title="${project.title.replace(/"/g, '&quot;')}" style="padding: 0.6rem 1rem; cursor: pointer; transition: background 0.2s; font-size: 0.9rem; font-weight: 500; display: flex; flex-direction: column; gap: 0.2rem;">
                            <span style="color: #1e293b;">${project.title}</span>
                            ${project.project_type ? `<span style="font-size: 0.75rem; color: #64748b; font-weight: normal;">Type: ${project.project_type}</span>` : ''}
                        </div>
                    `).join('');

                    projectSearchMenu.style.display = 'block';

                    // Add click handlers to items
                    document.querySelectorAll('.project-search-item').forEach(item => {
                        item.addEventListener('click', (e) => {
                            e.stopPropagation();
                            projectSearchInput.value = item.getAttribute('data-title');
                            const projId = item.getAttribute('data-id');
                            // Store the id in a dataset attribute in case it's needed later
                            projectSearchInput.dataset.projectId = projId;
                            projectSearchMenu.style.display = 'none';
                            // Fetch stages for the selected project
                            fetchProjectStages(projId);
                        });
                        item.addEventListener('mouseenter', () => {
                            item.style.background = '#f3f4f6';
                        });
                        item.addEventListener('mouseleave', () => {
                            item.style.background = 'transparent';
                        });
                    });
                } else {
                    projectSearchMenu.innerHTML = '<div style="padding: 0.6rem 1rem; color: #64748b; font-size: 0.9rem;">No projects found</div>';
                    projectSearchMenu.style.display = 'block';
                }
            } catch (error) {
                console.error("Error fetching projects:", error);
            }
        };

        // Handle typing
        projectSearchInput.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            // Clear any id mapping if user alters text manually
            delete projectSearchInput.dataset.projectId;

            // Hide and reset stages on input change
            const stageContainer = document.getElementById('stageSelectContainer');
            if (stageContainer) stageContainer.style.display = 'none';
            const stageSelect = document.getElementById('stageSelect');
            if (stageSelect) stageSelect.innerHTML = '<option value="">Select a stage...</option>';

            clearTimeout(searchTimeout);

            if (val.length < 2) {
                projectSearchMenu.style.display = 'none';
                return;
            }

            // Debounce api calls
            searchTimeout = setTimeout(() => {
                fetchProjects(val);
            }, 300);
        });

        // Close menu on click outside
        document.addEventListener('click', (e) => {
            if (!projectSearchInput.contains(e.target) && !projectSearchMenu.contains(e.target)) {
                projectSearchMenu.style.display = 'none';
            }
        });

        // Show menu if input already has value when focused
        projectSearchInput.addEventListener('focus', (e) => {
            const val = e.target.value.trim();
            if (val.length >= 2) {
                fetchProjects(val);
            }
        });

        // ── Validation: Clear input if no project was selected from results ──
        projectSearchInput.addEventListener('blur', () => {
            // Small delay to allow menu clicks to register before clearing
            setTimeout(() => {
                if (!projectSearchInput.dataset.projectId) {
                    projectSearchInput.value = '';
                    const stageContainer = document.getElementById('stageSelectContainer');
                    if (stageContainer) stageContainer.style.display = 'none';
                }
            }, 200);
        });
    }

    // --- Custom Single-Select Dropdown Logic for Repeat Task ---
    const repeatSelectTrigger = document.getElementById('repeatSelectTrigger');
    const repeatSelectMenu = document.getElementById('repeatSelectMenu');
    const repeatSelectText = document.getElementById('repeatSelectText');
    const repeatOptions = document.querySelectorAll('.repeat-option');
    let repeatDropdownOpen = false;

    if (repeatSelectTrigger && repeatSelectMenu) {
        repeatSelectTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            repeatDropdownOpen = !repeatDropdownOpen;
            if (repeatDropdownOpen) {
                repeatSelectMenu.style.display = 'block';
                void repeatSelectMenu.offsetWidth;
                repeatSelectMenu.style.opacity = '1';
                repeatSelectMenu.style.transform = 'scaleY(1)';
            } else {
                repeatSelectMenu.style.opacity = '0';
                repeatSelectMenu.style.transform = 'scaleY(0)';
                setTimeout(() => {
                    if (!repeatDropdownOpen) repeatSelectMenu.style.display = 'none';
                }, 300);
            }
            const icon = repeatSelectTrigger.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = repeatDropdownOpen ? 'rotate(180deg)' : 'rotate(0deg)';
        });

        document.addEventListener('click', (e) => {
            if (repeatDropdownOpen && !repeatSelectTrigger.contains(e.target) && !repeatSelectMenu.contains(e.target)) {
                repeatDropdownOpen = false;
                repeatSelectMenu.style.opacity = '0';
                repeatSelectMenu.style.transform = 'scaleY(0)';
                setTimeout(() => {
                    if (!repeatDropdownOpen) repeatSelectMenu.style.display = 'none';
                }, 300);
                const icon = repeatSelectTrigger.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0deg)';
            }
        });

        repeatOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const val = option.getAttribute('data-value');
                repeatSelectText.textContent = val;

                // Show/hide frequency based on yes/no
                const freqGroup = document.getElementById('frequencyGroup');
                if (freqGroup) {
                    freqGroup.style.display = val === 'Yes' ? 'flex' : 'none';
                }

                repeatDropdownOpen = false;
                repeatSelectMenu.style.opacity = '0';
                repeatSelectMenu.style.transform = 'scaleY(0)';
                setTimeout(() => {
                    if (!repeatDropdownOpen) repeatSelectMenu.style.display = 'none';
                }, 300);
                const icon = repeatSelectTrigger.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0deg)';
            });
        });
    }

    // --- Custom Single-Select Dropdown Logic for Frequency ---
    const frequencySelectTrigger = document.getElementById('frequencySelectTrigger');
    const frequencySelectMenu = document.getElementById('frequencySelectMenu');
    const frequencySelectText = document.getElementById('frequencySelectText');
    const frequencyOptions = document.querySelectorAll('.frequency-option');
    let frequencyDropdownOpen = false;

    if (frequencySelectTrigger && frequencySelectMenu) {
        frequencySelectTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            frequencyDropdownOpen = !frequencyDropdownOpen;
            if (frequencyDropdownOpen) {
                frequencySelectMenu.style.display = 'block';
                void frequencySelectMenu.offsetWidth;
                frequencySelectMenu.style.opacity = '1';
                frequencySelectMenu.style.transform = 'scaleY(1)';
            } else {
                frequencySelectMenu.style.opacity = '0';
                frequencySelectMenu.style.transform = 'scaleY(0)';
                setTimeout(() => {
                    if (!frequencyDropdownOpen) frequencySelectMenu.style.display = 'none';
                }, 300);
            }
            const icon = frequencySelectTrigger.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = frequencyDropdownOpen ? 'rotate(180deg)' : 'rotate(0deg)';
        });

        document.addEventListener('click', (e) => {
            if (frequencyDropdownOpen && !frequencySelectTrigger.contains(e.target) && !frequencySelectMenu.contains(e.target)) {
                frequencyDropdownOpen = false;
                frequencySelectMenu.style.opacity = '0';
                frequencySelectMenu.style.transform = 'scaleY(0)';
                setTimeout(() => {
                    if (!frequencyDropdownOpen) frequencySelectMenu.style.display = 'none';
                }, 300);
                const icon = frequencySelectTrigger.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0deg)';
            }
        });

        // Inject unit-button styles
        if (!document.getElementById('cfu-styles')) {
            const _cs = document.createElement('style');
            _cs.id = 'cfu-styles';
            _cs.textContent = `
                .cfu-btn {
                    border: 1px solid var(--border-color);
                    background: #fff;
                    color: var(--text-muted);
                    border-radius: 20px;
                    padding: 3px 12px;
                    font-size: 0.78rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.15s;
                    font-family: inherit;
                }
                .cfu-btn:hover { background: #f3f4f6; color: var(--text-main); }
                .cfu-btn.cfu-active {
                    background: var(--primary-color, #4f46e5);
                    color: #fff;
                    border-color: var(--primary-color, #4f46e5);
                }
                #customFreqGroup { animation: chipIn 0.2s ease both; }
            `;
            document.head.appendChild(_cs);
        }

        function closeFreqDropdown() {
            frequencyDropdownOpen = false;
            frequencySelectMenu.style.opacity = '0';
            frequencySelectMenu.style.transform = 'scaleY(0)';
            setTimeout(() => { if (!frequencyDropdownOpen) frequencySelectMenu.style.display = 'none'; }, 300);
            const icon = frequencySelectTrigger.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = 'rotate(0deg)';
        }

        function updateCustomPreview() {
            const num = document.getElementById('customFreqNum');
            const preview = document.getElementById('customFreqPreview');
            const activeBtn = document.querySelector('#customFreqUnit .cfu-btn.cfu-active');
            if (!num || !preview || !activeBtn) return;
            const n = parseInt(num.value) || 1;
            const unit = activeBtn.dataset.unit;
            const label = n === 1 ? unit : unit + 's';
            const text = `Every ${n} ${label}`;
            preview.textContent = text;
            if (frequencySelectText) frequencySelectText.textContent = text;
        }

        // Wire unit buttons
        document.querySelectorAll('#customFreqUnit .cfu-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#customFreqUnit .cfu-btn').forEach(b => b.classList.remove('cfu-active'));
                btn.classList.add('cfu-active');
                updateCustomPreview();
            });
        });

        // Wire number input
        const customFreqNum = document.getElementById('customFreqNum');
        if (customFreqNum) {
            customFreqNum.addEventListener('input', updateCustomPreview);
        }

        // Delegate frequency option clicks (includes dynamically-added Custom)
        frequencySelectMenu.addEventListener('click', (e) => {
            const option = e.target.closest('.frequency-option');
            if (!option) return;
            e.stopPropagation();
            const val = option.getAttribute('data-value');
            const customGroup = document.getElementById('customFreqGroup');

            if (val === 'Custom') {
                frequencySelectText.textContent = 'Custom...';
                if (customGroup) { customGroup.style.display = 'flex'; updateCustomPreview(); }
            } else {
                frequencySelectText.textContent = val;
                if (customGroup) customGroup.style.display = 'none';
            }
            closeFreqDropdown();
        });

        // Remove the old forEach-based listeners (replaced by delegation above)
        // frequencyOptions.forEach is intentionally omitted.
    }

    // =====================================================
    // PREMIUM TASK LIST - Filter & Sort Dropdown Logic
    // =====================================================
    (function () {
        const filterBtn = document.getElementById('taskFilterBtn');
        const filterPanel = document.getElementById('filterDropdown');
        const sortBtn = document.getElementById('taskSortBtn');
        const sortPanel = document.getElementById('sortDropdown');

        if (!filterBtn || !filterPanel || !sortBtn || !sortPanel) return;

        // Helper: open a panel, close its sibling
        function openPanel(btn, panel, siblingBtn, siblingPanel) {
            // Close sibling first
            siblingPanel.classList.remove('tl-panel-visible');
            siblingBtn.classList.remove('tl-open');

            const isOpen = panel.classList.contains('tl-panel-visible');
            if (isOpen) {
                panel.classList.remove('tl-panel-visible');
                btn.classList.remove('tl-open');
            } else {
                panel.classList.add('tl-panel-visible');
                btn.classList.add('tl-open');
            }
        }

        filterBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openPanel(filterBtn, filterPanel, sortBtn, sortPanel);
        });

        sortBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openPanel(sortBtn, sortPanel, filterBtn, filterPanel);
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!filterBtn.contains(e.target) && !filterPanel.contains(e.target)) {
                filterPanel.classList.remove('tl-panel-visible');
                filterBtn.classList.remove('tl-open');
            }
            if (!sortBtn.contains(e.target) && !sortPanel.contains(e.target)) {
                sortPanel.classList.remove('tl-panel-visible');
                sortBtn.classList.remove('tl-open');
            }
        });

        // ── Shared Filter/Search Logic ──
        let currentStatusFilter = 'All';
        let currentSearchQuery = '';

        const searchInput = document.getElementById('taskSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                currentSearchQuery = e.target.value.toLowerCase();
                applyFilterAndSearch();
            });
        }

        function applyFilterAndSearch() {
            // Apply to all rows in both daily and manager tables
            const rows = document.querySelectorAll('.task-list-row');
            rows.forEach(row => {
                const statusStr = row.getAttribute('data-task-status') || '';
                const nameStr = row.getAttribute('data-task-name') || '';

                const matchesStatus = (currentStatusFilter === 'All') || (statusStr.toLowerCase() === currentStatusFilter.toLowerCase());
                const matchesSearch = nameStr.toLowerCase().includes(currentSearchQuery);

                if (matchesStatus && matchesSearch) {
                    // Restore to default display by removing inline override
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // ── Filter Options ──
        const filterOptions = filterPanel.querySelectorAll('.tl-dropdown-option');
        filterOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                // Update active state
                filterOptions.forEach(o => o.classList.remove('mtpd-active'));
                opt.classList.add('mtpd-active');

                currentStatusFilter = opt.getAttribute('data-status');
                applyFilterAndSearch();

                // Close panel with small delay for checkmark pop animation
                setTimeout(() => {
                    filterPanel.classList.remove('tl-panel-visible');
                    filterBtn.classList.remove('tl-open');
                }, 220);
            });
        });

        // ── Sort Options ──
        const sortOptions = sortPanel.querySelectorAll('.tl-dropdown-option');

        sortOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                sortOptions.forEach(o => o.classList.remove('mtpd-active'));
                opt.classList.add('mtpd-active');
                const activeSortOption = opt.getAttribute('data-sort');

                // Sort both tables individually
                ['taskListTableBody', 'managerAssignedTableBody'].forEach(tableId => {
                    const tbody = document.getElementById(tableId);
                    if (!tbody) return;

                    const rows = Array.from(tbody.querySelectorAll('.task-list-row'));
                    const priorityOrder = { 'high': 0, 'medium': 1, 'med': 1, 'low': 2 };
                    const statusOrder = { 'in progress': 0, 'pending': 1, 'completed': 2 };

                    rows.sort((a, b) => {
                        if (activeSortOption === 'Priority') {
                            const pa = (a.getAttribute('data-task-priority') || '').toLowerCase();
                            const pb = (b.getAttribute('data-task-priority') || '').toLowerCase();
                            return (priorityOrder[pa] ?? 99) - (priorityOrder[pb] ?? 99);
                        } else if (activeSortOption === 'DueDate') {
                            const da = a.getAttribute('data-task-date') || '';
                            const db = b.getAttribute('data-task-date') || '';
                            return new Date(da) - new Date(db);
                        } else if (activeSortOption === 'Status') {
                            const sa = (a.getAttribute('data-task-status') || '').toLowerCase();
                            const sb = (b.getAttribute('data-task-status') || '').toLowerCase();
                            return (statusOrder[sa] ?? 99) - (statusOrder[sb] ?? 99);
                        }
                        return 0;
                    });

                    rows.forEach(r => tbody.appendChild(r));
                });

                setTimeout(() => {
                    sortPanel.classList.remove('tl-panel-visible');
                    sortBtn.classList.remove('tl-open');
                }, 220);
            });
        });
    })();

    if (addTaskBtn && addTaskInput) {
        addTaskBtn.addEventListener('click', addNewTask);
        // Removed: Enter key submitting task. Now Enter adds a new line.
        // Optional: Submit on Ctrl+Enter
        addTaskInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                addNewTask();
            }
        });

        // Submit Work Report Button Logic
        const submitReportBtn = document.getElementById('submitReportBtn');
        if (submitReportBtn) {
            submitReportBtn.addEventListener('click', () => {
                // Placeholder for report submission logic
                alert("Submit Report feature coming soon!");
            });
        }

        // --- Notification Drawer Logic ---
        const notifBtn = document.getElementById('notifBtn');
        const notifBadge = document.getElementById('notifBadge');
        const notifDrawer = document.getElementById('notifDrawer');
        const notifContent = document.getElementById('notifContent');
        const drawerOverlay = document.getElementById('drawerOverlay');
        const closeNotif = document.getElementById('closeNotif');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const clearNotifBtn = document.getElementById('clearNotifBtn');
        
        // --- Notification Sound ---
        const notifSound = new Audio('tones/global_notification.mp3');
        notifSound.volume = 1.0;
        let lastSeenMaxId = 0;       // tracks highest notif ID seen so far
        let audioUnlocked = false;   // browsers block audio until first user interaction
        let pendingSound = false;    // play sound on next interaction if blocked
        let isDrawerOpen = false;
        let initialNotifScanDone = false;
        let loginBacklogAlertTimer = null;

        function parseAssigneeNames(raw) {
            if (Array.isArray(raw)) return raw.map(n => String(n || '').trim()).filter(Boolean);
            if (typeof raw === 'string') return raw.split(',').map(n => n.trim()).filter(Boolean);
            return [];
        }

        function isTaskAssignedForCurrentUser(log) {
            if (!log || log.action_type !== 'task_assigned') return false;

            const desc = String(log.description || '');
            const isCreatorLog = /^\s*you assigned\s*:/i.test(desc);
            if (isCreatorLog) return true;

            const meta = (typeof log._meta === 'object' && log._meta)
                ? log._meta
                : (typeof log.metadata === 'object' ? log.metadata : {});

            const names = parseAssigneeNames(meta.assigned_names || meta.assignees || meta.team_members);
            const me = String(window.loggedUserName || '').trim().toLowerCase();

            if (!me) return true;
            if (!names.length) {
                // Fallback heuristic for older metadata payloads
                return /been assigned|assigned a task/i.test(desc);
            }
            return names.some(n => n.toLowerCase() === me);
        }

        function toTaskAlertLog(log) {
            if (!log) return null;
            const c = { ...log };
            if (typeof c.metadata === 'string') {
                try { c.metadata = JSON.parse(c.metadata); } catch (_) { c.metadata = {}; }
            } else if (!c.metadata || typeof c.metadata !== 'object') {
                c.metadata = {};
            }
            return c;
        }

        async function showOfflineAssignedTaskAlertAfterDelay() {
            try {
                const res = await fetch('api/fetch_activity_logs.php');
                const result = await res.json();
                const rows = (result && result.status === 'success' && Array.isArray(result.data)) ? result.data : [];
                if (!rows.length) return;

                const pendingOfflineTask = rows.find(log =>
                    log &&
                    log.action_type === 'task_assigned' &&
                    String(log.is_read) === '0' &&
                    isTaskAssignedForCurrentUser(log)
                );

                if (pendingOfflineTask && typeof TaskAssignedAlert !== 'undefined') {
                    TaskAssignedAlert.show(toTaskAlertLog(pendingOfflineTask));
                }
            } catch (_) {
                // Non-blocking
            }
        }

        // Unlock audio engine on first user gesture (click or keydown)
        function unlockAudio() {
            audioUnlocked = true;
            if (pendingSound && !isDrawerOpen) {
                pendingSound = false;
                notifSound.currentTime = 0;
                notifSound.play().catch(() => {});
            }
            document.removeEventListener('click', unlockAudio);
            document.removeEventListener('keydown', unlockAudio);
        }
        document.addEventListener('click', unlockAudio);
        document.addEventListener('keydown', unlockAudio);

        function playNotifSound() {
            if (isDrawerOpen) return;
            if (audioUnlocked) {
                notifSound.currentTime = 0;
                notifSound.play().catch(() => {});
            } else {
                pendingSound = true; // will fire on next interaction
            }
        }

        async function fetchNotifications() {
            try {
                const res = await fetch('api/fetch_activity_logs.php');
                const result = await res.json();
                if(result.status === 'success') {
                    renderNotifications(result.data);
                }
            } catch(e) {
                console.error("Failed to load notifications", e);
            }
        }
        window.fetchNotifications = fetchNotifications;

        function renderNotifications(logs) {
            if(!notifContent) return;
            notifContent.innerHTML = '';
            let unreadCount = 0;

            function safeParseMetadata(raw) {
                if (!raw) return {};
                if (typeof raw === 'object') return raw;
                if (typeof raw !== 'string') return {};
                try {
                    return JSON.parse(raw);
                } catch (_) {
                    return {};
                }
            }

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
            
            if(!logs || logs.length === 0) {
                notifContent.innerHTML = '<div style="padding: 20px; text-align: center; color: #94a3b8;"><i class="fa-regular fa-bell-slash" style="font-size:1.5rem; margin-bottom:8px; display:block;"></i> No recent notifications</div>';
                if(notifBadge) { notifBadge.style.display = 'none'; notifBadge.textContent = '0'; }
                lastSeenMaxId = 0;
                initialNotifScanDone = true;
                return;
            }

            // Categorize
            const todayLogs = [];
            const yesterdayLogs = [];
            const olderLogs = [];
            
            const today = new Date();
            today.setHours(0,0,0,0);
            
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);

            // Find max ID in this batch to detect truly new notifications
            let batchMaxId = 0;
            logs.forEach(log => {
                const logId = parseInt(log.id) || 0;
                if(logId > batchMaxId) batchMaxId = logId;
                if(log.is_read == 0) unreadCount++;

                // Parse metadata once for each log (used by UI rendering)
                log._meta = safeParseMetadata(log.metadata);

                const logDate = new Date(log.created_at);
                logDate.setHours(0,0,0,0);
                if(logDate.getTime() === today.getTime()) {
                    todayLogs.push(log);
                } else if(logDate.getTime() === yesterday.getTime()) {
                    yesterdayLogs.push(log);
                } else {
                    olderLogs.push(log);
                }
            });

            // ------ Sound + Modal on new notification ------
            if (lastSeenMaxId === 0) {
                // First load — register silently, queue sound if there are unread ones
                if (unreadCount > 0) pendingSound = true;

                // If user was offline and already has unread task assignments,
                // show one onboarding reminder modal after 120 seconds.
                if (!initialNotifScanDone && !loginBacklogAlertTimer) {
                    const hasOfflineTaskAssigned = logs.some(log =>
                        log &&
                        String(log.is_read) === '0' &&
                        isTaskAssignedForCurrentUser(log)
                    );

                    if (hasOfflineTaskAssigned) {
                        loginBacklogAlertTimer = setTimeout(() => {
                            showOfflineAssignedTaskAlertAfterDelay();
                        }, 30000);
                    }
                }
            } else if (batchMaxId > lastSeenMaxId) {
                // Genuinely new notification arrived since last poll
                playNotifSound();

                // Find the newest task_assigned log in this batch that's truly new
                const newTaskLog = logs.find(log =>
                    log.action_type === 'task_assigned' &&
                    (parseInt(log.id) || 0) > lastSeenMaxId &&
                    isTaskAssignedForCurrentUser(log)
                );
                if (newTaskLog && typeof TaskAssignedAlert !== 'undefined') {
                    TaskAssignedAlert.show(toTaskAlertLog(newTaskLog));
                }
            }
            lastSeenMaxId = batchMaxId;
            initialNotifScanDone = true;
            // ------------------------------------------------

            // Update badge
            if(notifBadge) {
                if(unreadCount > 0) {
                    notifBadge.style.display = 'flex';
                    notifBadge.textContent = unreadCount;
                } else {
                    notifBadge.style.display = 'none';
                    notifBadge.textContent = '0';
                }
            }
            
            function buildLogHTML(log) {
                // Color + icon mapping per action type
                const typeConfig = {
                    // ── Attendance ─────────────────────────────────────────────────────
                    'punch_in':                  { color: '#10b981', bg: '#f0fdf4', icon: 'fa-solid fa-fingerprint',          label: 'Punched In'              },
                    'punch_out':                 { color: '#ef4444', bg: '#fff5f5', icon: 'fa-solid fa-right-from-bracket',   label: 'Punched Out'             },
                    'update_attendance':         { color: '#8b5cf6', bg: '#faf5ff', icon: 'fa-solid fa-user-pen',             label: 'Attendance Modified'     },
                    'attendance_geofence_approved': { color: '#16a34a', bg: '#f0fdf4', icon: 'fa-solid fa-circle-check',      label: 'Geofence Approved'       },
                    'attendance_geofence_rejected': { color: '#dc2626', bg: '#fef2f2', icon: 'fa-solid fa-ban',               label: 'Geofence Rejected'       },

                    // ── Tasks ──────────────────────────────────────────────────────────
                    'task_assigned':             { color: '#8b5cf6', bg: '#f5f3ff', icon: 'fa-solid fa-list-check',           label: 'Task Assigned'           },
                    'task_created':              { color: '#3b82f6', bg: '#eff6ff', icon: 'fa-solid fa-circle-plus',          label: 'Task Created'            },
                    'task_completed':            { color: '#10b981', bg: '#f0fdf4', icon: 'fa-solid fa-circle-check',         label: 'Task Completed'          },
                    'task_partially_completed':  { color: '#f59e0b', bg: '#fffbeb', icon: 'fa-solid fa-users-between-lines',  label: 'Task Partially Completed'},
                    'task_still_pending':        { color: '#fb7185', bg: '#fff1f2', icon: 'fa-solid fa-hourglass-half',       label: 'Pending Your Completion' },
                    'task_completed_for_approval':{ color: '#f59e0b', bg: '#fffbeb', icon: 'fa-solid fa-file-signature',      label: 'Approval Needed'         },
                    'task_completion_approved':   { color: '#16a34a', bg: '#f0fdf4', icon: 'fa-solid fa-thumbs-up',            label: 'Task Completion Approved'},
                    'task_completion_rejected':   { color: '#ef4444', bg: '#fef2f2', icon: 'fa-solid fa-ban',                  label: 'Task Completion Rejected'},
                    'task_progress_updated':      { color: '#2563eb', bg: '#eff6ff', icon: 'fa-solid fa-sliders',             label: 'Task Progress Updated'   },
                    'task_deleted':              { color: '#ef4444', bg: '#fef2f2', icon: 'fa-solid fa-trash-can',             label: 'Task Deleted'            },
                    'deadline_snooze':           { color: '#f59e0b', bg: '#fffbeb', icon: 'fa-solid fa-calendar-xmark',       label: 'Deadline Snoozed'        },
                    'extend_deadline':           { color: '#f59e0b', bg: '#fffbeb', icon: 'fa-solid fa-clock-rotate-left',    label: 'Deadline Extended'       },

                    // ── Travel Expenses ────────────────────────────────────────────────
                    'travel_added':              { color: '#0ea5e9', bg: '#f0f9ff', icon: 'fa-solid fa-plane-departure',      label: 'Travel Added'            },
                    'travel_updated':            { color: '#6366f1', bg: '#eef2ff', icon: 'fa-solid fa-file-pen',             label: 'Travel Updated'          },
                    'travel_deleted':            { color: '#f43f5e', bg: '#fff1f2', icon: 'fa-solid fa-plane-slash',          label: 'Travel Deleted'          },
                    'travel_approved':           { color: '#10b981', bg: '#f0fdf4', icon: 'fa-solid fa-plane-circle-check',   label: 'Travel Approved'         },
                    'travel_rejected':           { color: '#ef4444', bg: '#fef2f2', icon: 'fa-solid fa-plane-circle-xmark',   label: 'Travel Rejected'         },
                    'travel_paid':               { color: '#16a34a', bg: '#f0fdf4', icon: 'fa-solid fa-money-bill-transfer',  label: 'Travel Paid'             },
                    // aliases (kept for safety)
                    'travel_expense_added':      { color: '#0ea5e9', bg: '#f0f9ff', icon: 'fa-solid fa-plane-departure',      label: 'Travel Added'            },
                    'travel_expense_edited':     { color: '#6366f1', bg: '#eef2ff', icon: 'fa-solid fa-file-pen',             label: 'Travel Updated'          },
                    'travel_expense_deleted':    { color: '#f43f5e', bg: '#fff1f2', icon: 'fa-solid fa-plane-slash',          label: 'Travel Deleted'          },

                    // ── Overtime ───────────────────────────────────────────────────────
                    'overtime_submitted':        { color: '#f97316', bg: '#fff7ed', icon: 'fa-solid fa-business-time',        label: 'Overtime Submitted'      },
                    'overtime_added':            { color: '#f97316', bg: '#fff7ed', icon: 'fa-solid fa-business-time',        label: 'Overtime Submitted'      },
                    'overtime_approved':         { color: '#16a34a', bg: '#f0fdf4', icon: 'fa-solid fa-user-check',           label: 'Overtime Approved'       },
                    'overtime_rejected':         { color: '#dc2626', bg: '#fef2f2', icon: 'fa-solid fa-user-xmark',           label: 'Overtime Rejected'       },
                    'overtime_edited':           { color: '#d97706', bg: '#fffbeb', icon: 'fa-solid fa-pen-ruler',            label: 'Overtime Edited'         },

                    // ── Leave ──────────────────────────────────────────────────────────
                    'leave_applied':             { color: '#22c55e', bg: '#f0fdf4', icon: 'fa-solid fa-calendar-check',       label: 'Leave Applied'           },
                    'leave_approved':            { color: '#22c55e', bg: '#f0fdf4', icon: 'fa-solid fa-calendar-check',       label: 'Leave Approved'          },
                    'leave_edited':              { color: '#a855f7', bg: '#faf5ff', icon: 'fa-solid fa-pen-to-square',        label: 'Leave Edited'            },
                    'leave_rejected':            { color: '#e11d48', bg: '#fff1f2', icon: 'fa-solid fa-calendar-xmark',       label: 'Leave Rejected'          },
                    'leave_deleted':             { color: '#6b7280', bg: '#f9fafb', icon: 'fa-solid fa-calendar-minus',       label: 'Leave Deleted'           },
                };
                const hasOvertimeReport = log.action_type === 'punch_out' && !!(log._meta && log._meta.overtime_report);
                const cfg = hasOvertimeReport
                    ? typeConfig['overtime_submitted']
                    : (typeConfig[log.action_type] || { color: '#64748b', bg: '', icon: 'fa-solid fa-bell', label: formatActionType(log.action_type) });

                const logDate = new Date(log.created_at);
                const timeStr = logDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const dateStr = logDate.toLocaleDateString([], {month:'short', day:'numeric'});
                const fullTimeStr = `${dateStr} at ${timeStr}`;

                let displayDescription = log.description;
                if (log.action_type === 'update_attendance' && log._meta && log._meta.admin_name) {
                    const loggedInName = window.loggedUserName || '';
                    const aName = log._meta.admin_name;
                    const tName = log._meta.target_name;
                    const dDate = log._meta.date || '';
                    const updates = log._meta.updates_string || '';
                    
                    if (aName.toLowerCase() === loggedInName.toLowerCase()) {
                        displayDescription = `You modified attendance for ${tName} on ${dDate}. Updates: ${updates}.`;
                    } else if (tName.toLowerCase() === loggedInName.toLowerCase()) {
                        displayDescription = `${aName} modified your attendance on ${dDate}. Updates: ${updates}.`;
                    } else {
                        displayDescription = `${aName} modified attendance for ${tName} on ${dDate}. Updates: ${updates}.`;
                    }
                }

                const overtimeText = hasOvertimeReport
                    ? `<br><span style="display:block; margin-top:4px; color:#9a3412;"><i class="fa-solid fa-business-time" style="margin-right:4px;"></i>Overtime: ${escapeHtml(log._meta.overtime_report)}</span>`
                    : '';

                const unreadDot = log.is_read == 0
                    ? `<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${cfg.color}; margin-left:6px; vertical-align:middle;"></span>`
                    : '';
                const bgStyle = log.is_read == 0 ? `background:${cfg.bg};` : 'background:#fff;';

                return `
                <div class="notif-item" style="${bgStyle} border-left: 4px solid ${cfg.color}; padding: 12px 16px; margin: 4px 8px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                        <i class="${cfg.icon}" style="color:${cfg.color}; font-size:0.85rem;"></i>
                        <h4 style="margin:0; color:#1e293b; font-size:0.88rem; font-weight:600;">${cfg.label}${unreadDot}</h4>
                    </div>
                    <p style="margin:0 0 6px; font-size:0.8rem; color:#475569; line-height:1.4;">${displayDescription}${overtimeText}</p>
                    <span class="notif-time" style="font-size:0.7rem; color:#94a3b8;"><i class="fa-regular fa-clock"></i> ${fullTimeStr}</span>
                </div>`;
            }

            function formatActionType(type) {
                return type.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
            }

            if(todayLogs.length > 0) {
                notifContent.innerHTML += `<div style="padding: 10px 16px; font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase; background: #f8fafc; border-bottom: 1px solid #f1f5f9;">Today</div>`;
                todayLogs.forEach(l => notifContent.innerHTML += buildLogHTML(l));
            }
            if(yesterdayLogs.length > 0) {
                notifContent.innerHTML += `<div style="padding: 10px 16px; font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase; background: #f8fafc; border-bottom: 1px solid #f1f5f9; border-top: 1px solid #e2e8f0;">Yesterday</div>`;
                yesterdayLogs.forEach(l => notifContent.innerHTML += buildLogHTML(l));
            }
            if(olderLogs.length > 0) {
                notifContent.innerHTML += `<div style="padding: 10px 16px; font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase; background: #f8fafc; border-bottom: 1px solid #f1f5f9; border-top: 1px solid #e2e8f0;">Earlier</div>`;
                olderLogs.forEach(l => notifContent.innerHTML += buildLogHTML(l));
            }
        }

        // Action Handlers
        async function runNotifAction(action) {
            try {
                await fetch('api/notification_actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action})
                });
                fetchNotifications();
            } catch(e) { console.error('Error with notif action', e); }
        }

        if(markAllReadBtn) {
            markAllReadBtn.addEventListener('click', () => { runNotifAction('mark_all_read'); });
        }
        if(clearNotifBtn) {
            clearNotifBtn.addEventListener('click', () => { runNotifAction('clear_all'); });
        }

        // Poll or initial fetch
        fetchNotifications();
        // Optional: Poll every 30 seconds for real-time vibe
        setInterval(fetchNotifications, 15000);

        if (notifBtn && notifDrawer && drawerOverlay) {
            notifBtn.addEventListener('click', () => {
                notifDrawer.classList.add('open');
                drawerOverlay.classList.add('visible');
                document.body.style.overflow = 'hidden'; // Prevent scroll
                isDrawerOpen = true;

                // Trigger read action when opened to clear the badge fully
                runNotifAction('mark_all_read');
            });

            const closeAction = () => {
                notifDrawer.classList.remove('open');
                drawerOverlay.classList.remove('visible');
                document.body.style.overflow = '';
                isDrawerOpen = false;
            };

            if (closeNotif) closeNotif.addEventListener('click', closeAction);
            drawerOverlay.addEventListener('click', closeAction);
        }

        // HR Send Notification Logic
        const hrNotifBtn = document.getElementById('hrNotifBtn');
        const sendNotifModal = document.getElementById('sendNotifModal');
        const closeNotifModal = document.getElementById('closeNotifModal');
        const submitNewNotif = document.getElementById('submitNewNotif');

        if (hrNotifBtn && sendNotifModal) {
            hrNotifBtn.addEventListener('click', () => {
                sendNotifModal.classList.add('visible');
                sendNotifModal.classList.add('open');
            });

            if (closeNotifModal) {
                closeNotifModal.addEventListener('click', () => {
                    sendNotifModal.classList.remove('visible');
                    sendNotifModal.classList.remove('open');
                });
            }

            if (submitNewNotif) {
                submitNewNotif.addEventListener('click', () => {
                    const title = document.getElementById('notifTitle').value;
                    const type = document.getElementById('notifType').value;
                    const msg = document.getElementById('notifMessage').value;

                    if (title && msg) {
                        // Create Notification Item
                        const item = document.createElement('div');
                        item.className = 'notif-item';
                        // Border color based on type
                        let borderColor = '#2563eb'; // info
                        if (type === 'warning') borderColor = '#ca8a04';
                        if (type === 'success') borderColor = '#16a34a';

                        item.style.borderLeftColor = borderColor;
                        item.innerHTML = `
                            <h4>${title}</h4>
                            <p>${msg}</p>
                            <span class="notif-time">Just now</span>
                        `;

                        // Add to list
                        if (notifContent) {
                            notifContent.insertBefore(item, notifContent.firstChild);
                        }

                        // Update Badge
                        notifCount++;
                        if (notifBadge) {
                            notifBadge.style.display = 'flex';
                            notifBadge.textContent = notifCount;
                            notifBadge.classList.remove('pulse'); // Reset animation
                            void notifBadge.offsetWidth; // Trigger reflow
                            notifBadge.classList.add('pulse');
                        }

                        // --- INTEGRATION: Update HR Corner ---
                        // Set global tracking variable (defined below)
                        if (typeof setLatestHRNotification === 'function') {
                            setLatestHRNotification({
                                title: title,
                                message: msg,
                                type: type,
                                acknowledged: false
                            });
                        }

                        // Close Modal
                        sendNotifModal.classList.remove('visible');
                        sendNotifModal.classList.remove('open');

                        alert("Notification Sent Successfully!");

                        // Reset Inputs
                        document.getElementById('notifTitle').value = '';
                        document.getElementById('notifMessage').value = '';
                    } else {
                        alert("Please enter a title and message.");
                    }
                });
            }
        }

        // Add color change based on priority
        const addTaskPriority = document.getElementById('addTaskPriority');
        if (addTaskPriority) {
            const updatePriorityColor = () => {
                const val = addTaskPriority.value;
                if (val === 'High') addTaskPriority.style.color = '#dc2626'; // Red
                else if (val === 'Med') addTaskPriority.style.color = '#d97706'; // Yellow (Darker for readability)
                else addTaskPriority.style.color = '#16a34a'; // Green
            };

            addTaskPriority.addEventListener('change', updatePriorityColor);
            // Init color
            updatePriorityColor();
        }
    }

    // --- Extend Deadline Modal Logic ---
    // Moved to components/modals/extend-deadline-modal.js

    // --- Team Modal Logic ---
    const teamModal = document.getElementById('teamModal');
    const viewTeamBtn = document.getElementById('viewTeamBtn');
    const closeTeamModal = document.getElementById('closeTeamModal');
    const teamListContainer = document.getElementById('teamListContainer');

    const teamData = [
        { name: "Alex Johnson", role: "UI Designer", status: "online", avatar: "AJ" },
        { name: "Sarah Williams", role: "Frontend Dev", status: "busy", avatar: "SW" },
        { name: "David Miller", role: "Backend Dev", status: "offline", avatar: "DM" },
        { name: "Mike Davis", role: "Product Manager", status: "online", avatar: "MD" },
        { name: "Anna Tailor", role: "QA Engineer", status: "offline", avatar: "AT" }
    ];

    function renderTeamList(specificNames = null) {
        if (!teamListContainer) return;
        teamListContainer.innerHTML = '';

        let displayList = teamData;

        // If specific names are provided (from task click), filter or generate them
        if (specificNames && Array.isArray(specificNames) && specificNames.length > 0) {
            displayList = specificNames.map(name => {
                // Find existing member data or create dummy data
                const existing = teamData.find(m => m.name.includes(name) || m.name === name);
                if (existing) return existing;

                // Parse simple name to initials
                const parts = name.split(' ');
                const avatar = parts.length > 1 ? parts[0][0] + parts[1][0] : name.substring(0, 2).toUpperCase();

                return {
                    name: name,
                    role: "Team Member",
                    status: Math.random() > 0.5 ? "online" : "offline",
                    avatar: avatar
                };
            });
        }

        displayList.forEach(member => {
            const el = document.createElement('div');
            el.className = 'team-member-item';

            // Status Color
            let statusColor = '#9ca3af'; // offline
            if (member.status === 'online') statusColor = '#16a34a';
            if (member.status === 'busy') statusColor = '#ca8a04';

            el.innerHTML = `
                <div class="member-avatar" style="background:${stringToColor(member.name)}">
                    ${member.avatar}
                    <span class="status-dot" style="background:${statusColor}"></span>
                </div>
                <div class="member-info">
                    <h4>${member.name}</h4>
                    <p>${member.role}</p>
                </div>
            `;
            teamListContainer.appendChild(el);
        });
    }

    // Helper to generate consistent pastel colors from string
    function stringToColor(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const c = (hash & 0x00FFFFFF).toString(16).toUpperCase();
        return '#' + "00000".substring(0, 6 - c.length) + c;
    }

    if (teamModal) {
        if (viewTeamBtn) {
            viewTeamBtn.addEventListener('click', (e) => {
                e.preventDefault();
                renderTeamList();
                teamModal.classList.add('open');
            });
        }

        if (closeTeamModal) {
            closeTeamModal.addEventListener('click', () => {
                teamModal.classList.remove('open');
            });
        }

        teamModal.addEventListener('click', (e) => {
            if (e.target === teamModal) teamModal.classList.remove('open');
        });

        // Add handler for task assignee click (delegation)
        // Attached to document to ensure it catches clicks even if DOM layout changes
        document.addEventListener('click', (e) => {
            const assigneeBadge = e.target.closest('.task-assignee');

            if (assigneeBadge) {
                const teamModal = document.getElementById('teamModal');
                if (teamModal) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Parse data-assignees
                    let assignees = null;
                    try {
                        const attr = assigneeBadge.getAttribute('data-assignees');
                        if (attr) assignees = JSON.parse(attr);
                    } catch (err) {
                        console.error("Error parsing assignees", err);
                    }

                    // If parsing failed or no attribute, fall back to title or text
                    if (!assignees) {
                        const text = assigneeBadge.innerText.trim();
                        assignees = [text];
                    }

                    if (typeof renderTeamList === 'function') {
                        renderTeamList(assignees);
                        teamModal.classList.add('open');
                    }
                }
            }
        });
    }

    const assignedTaskModal = document.getElementById('assignedTaskModal');
    const closeAssignedTaskModal = document.getElementById('closeAssignedTaskModal');
    if (assignedTaskModal) {
        if (closeAssignedTaskModal) {
            closeAssignedTaskModal.addEventListener('click', () => {
                assignedTaskModal.classList.remove('visible', 'open');
            });
        }
        assignedTaskModal.addEventListener('click', (e) => {
            if (e.target === assignedTaskModal) {
                assignedTaskModal.classList.remove('visible', 'open');
            }
        });
    }

    // --- HR Corner Logic ---
    let latestHRNotification = null; // Store active notification
    // Helper to set from other scope
    window.setLatestHRNotification = (data) => {
        latestHRNotification = data;
        if (window.updateHRCornerDisplay) window.updateHRCornerDisplay();
    };

    // --- Combined Dynamic HR Items (Policies + Notices) ---
    let dynamicHRItems = [];

    async function loadHRDashboardData() {
        try {
            const res = await fetch('api/get_hr_dashboard_content.php?v=' + Date.now());
            const data = await res.json();
            console.log("[HR Sync Debug]", data);
            
            if (data.success) {
                dynamicHRItems = [];
                window.dynamicHRItems = dynamicHRItems;
                
                // --- Add Hardcoded Policies to general rotation for visibility ---
                mandatoryPolicies.forEach(p => {
                    if (!p.db_id) dynamicHRItems.push(`🏢 Policy: ${p.title} — General Information`);
                });

                // --- Sync DB Mandatory Policies to Local Mandatory Array ---
                data.policies.forEach(p => {
                    const localSessionAccepted = sessionStorage.getItem(`hrPolicy_db_accepted_${p.id}`) === 'true';
                    const isFullyAccepted = (p.is_acknowledged == 1) || localSessionAccepted;

                    if (p.is_mandatory == 1 && !isFullyAccepted) {
                        const exists = mandatoryPolicies.find(mp => mp.db_id === p.id);
                        if (!exists) {
                            mandatoryPolicies.push({
                                db_id: p.id,
                                id: mandatoryPolicies.length + 1,
                                title: p.heading,
                                version: "v" + new Date(p.updated_at).toLocaleDateString().replace(/\//g, '.'),
                                content: `<h3>${p.heading}</h3><p>${p.short_desc}</p><br>${p.long_desc}`,
                                accepted: false
                            });
                        }
                    }
                    dynamicHRItems.push(`🏢 Policy: ${p.heading} — ${p.short_desc}`);
                });
                
                // --- Sync DB Mandatory Notices to Local Mandatory Array ---
                data.notices.forEach(n => {
                    const localSessionAccepted = sessionStorage.getItem(`hrNotice_db_accepted_${n.id}`) === 'true';
                    const isFullyAccepted = (n.is_acknowledged == 1) || localSessionAccepted;

                    if (n.is_mandatory == 1 && !isFullyAccepted) {
                        const exists = mandatoryPolicies.find(mp => mp.notif_db_id === n.id);
                        if (!exists) {
                            let noticeBody = `<h3>Notice: ${n.title}</h3><p>${n.short_desc}</p><br>${n.long_desc}`;
                            if (n.attachment) noticeBody += `<br><br><a href="${n.attachment}" target="_blank" style="color:#2563eb; font-weight:700; text-decoration:underline;"><i class="fa-solid fa-file-pdf"></i> Download/View PDF Attachment</a>`;

                            mandatoryPolicies.push({
                                notif_db_id: n.id,
                                id: mandatoryPolicies.length + 1,
                                title: "📢 " + n.title,
                                version: "Broadcast: " + new Date(n.created_at).toLocaleDateString(),
                                content: noticeBody,
                                accepted: false
                            });
                        }
                    }

                    // Add to rotation
                    let noticeText = `📢 Notice: ${n.title}`;
                    if (n.attachment) {
                        noticeText += ` <span class="pdf-badge" onclick="window.open('${n.attachment}', '_blank'); event.stopPropagation();" style="cursor:pointer; background:#ef4444; color:#fff; padding:1px 6px; border-radius:4px; font-size:0.7rem; font-weight:700; display:inline-flex; align-items:center; gap:3px;"><i class="fa-solid fa-file-pdf"></i> PDF</span>`;
                    }
                    dynamicHRItems.push(noticeText);
                });

                // Keep global reference fresh for rotation restart
                window.dynamicHRItems = dynamicHRItems;

                // Update the compliance display immediately after syncing
                if (typeof updateHRCornerDisplay === 'function') updateHRCornerDisplay();

                if (dynamicHRItems.length > 0) {
                    showHRItem(0);
                    if (window.hrInterval) clearInterval(window.hrInterval);
                    window.hrInterval = setInterval(() => {
                        window.hrIdx = (window.hrIdx + 1) % dynamicHRItems.length;
                        showHRItem(window.hrIdx);
                    }, 6500); 
                } else {
                    if (hrPolicyText) hrPolicyText.textContent = "No recent HR updates.";
                }
            } else {
                const msg = (data && data.message) ? String(data.message) : 'Unable to load HR updates.';
                console.error('[HR Corner] API returned error:', msg, data);
                if (hrPolicyText) hrPolicyText.textContent = msg;
            }
        } catch (e) {
            console.error("[HR Corner] Error fetching data:", e);
            if (hrPolicyText) hrPolicyText.textContent = "Error loading updates.";
        }
    }

    // click handler for the entire HR Card
    if (hrCard) {
        hrCard.addEventListener('click', () => {
            const pendingPolicy = mandatoryPolicies.find(p => !p.accepted);
            if (pendingPolicy) {
                const idx = mandatoryPolicies.indexOf(pendingPolicy);
                if (typeof loadPolicy === 'function') loadPolicy(idx);
                const pModal = document.getElementById('policyModal');
                if (pModal) { pModal.classList.add('visible'); pModal.classList.add('open'); }
            }
        });
        hrCard.style.cursor = 'pointer';
    }

    // Reference to existing rotation logic but adapted for dynamic data
    window.hrIdx = 0;
    function showHRItem(index) {
        if (!hrPolicyText || !dynamicHRItems[index]) return;
        
        const content = dynamicHRItems[index];
        const card = document.querySelector('.hr-card');
        const iconBox = card.querySelector('.icon-box');

        // Dynamic Sizing Logic (similar to original showPolicy)
        const plainText = content.replace(/<[^>]*>/g, ''); // strip HTML for length check
        if (plainText.length > 100) {
            hrPolicyText.style.fontSize = '0.9rem';
        } else {
            hrPolicyText.style.fontSize = '1.05rem';
        }

        hrPolicyText.style.opacity = '0';
        hrPolicyText.style.transform = 'translateY(8px)';

        setTimeout(() => {
            hrPolicyText.innerHTML = content;
            hrPolicyText.style.opacity = '1';
            hrPolicyText.style.transform = 'translateY(0)';
        }, 300);
    }

    if (hrPolicyText) {
        window.hrIdx = 0;
        window.hrInterval = null;

        function updateHRCornerDisplay() {
            const pendingPolicy = mandatoryPolicies.find(p => !p.accepted);
            if (pendingPolicy) {
                if (window.hrInterval) { clearInterval(window.hrInterval); window.hrInterval = null; }
                const card = document.querySelector('.hr-card');
                const announcementEl = document.getElementById('hrAnnouncement');
                if (announcementEl) announcementEl.style.display = 'flex';
                if (hrPolicyText) {
                    hrPolicyText.style.opacity = '1';
                    hrPolicyText.style.transform = 'translateY(0)';
                    hrPolicyText.innerHTML = `
                        <div style="font-size:0.75rem; color:#ca8a04; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.5rem;">
                            <i class="fa-solid fa-bullhorn"></i> Important Action
                        </div>
                        <div style="padding-bottom: 0.5rem; line-height: 1.3;">
                            ${pendingPolicy.title}
                        </div>
                        <button id="cornerAcceptBtn" class="policy-btn primary" style="
                            margin-top: 0.5rem; padding: 0.3rem 0.8rem; font-size: 0.75rem; 
                            animation: pulse 1.5s infinite; width: auto; display: inline-block;
                            height: auto; min-height: unset; box-shadow: 0 0 10px rgba(37, 99, 235, 0.3);">
                            Review & Accept
                        </button>
                    `;
                }
                if (card) {
                    card.style.border = '1px solid #ca8a04';
                    card.style.boxShadow = '0 0 15px rgba(202, 138, 4, 0.15)';
                }
                const btn = document.getElementById('cornerAcceptBtn');
                if (btn) {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation(); e.preventDefault();
                        const idx = mandatoryPolicies.indexOf(pendingPolicy);
                        if (typeof loadPolicy === 'function') loadPolicy(idx);
                        const pModal = document.getElementById('policyModal');
                        if (pModal) { pModal.classList.add('visible'); pModal.classList.add('open'); }
                    });
                }
            } else {
                const card = document.querySelector('.hr-card');
                if (card) { card.style.border = ''; card.style.boxShadow = ''; }
                if (!window.hrInterval && (window.dynamicHRItems || []).length > 0) {
                    showHRItem(window.hrIdx % window.dynamicHRItems.length);
                    window.hrInterval = setInterval(() => {
                        window.hrIdx = (window.hrIdx + 1) % window.dynamicHRItems.length;
                        showHRItem(window.hrIdx);
                    }, 6500);
                }
            }
        }
        updateHRCornerDisplay();
        loadHRDashboardData();
    }

    // --- Policy Acknowledgement System ---
    const policyModal = document.getElementById('policyModal');
    const openPolicyBtn = document.getElementById('openPolicyBtn');
    const closePolicyBtn = document.getElementById('closePolicyModal');

    // UI Elements
    const policyTitleDisplay = document.getElementById('policyTitleDisplay');
    const policyVersionDisplay = document.getElementById('policyVersionDisplay');
    const policyContentArea = document.getElementById('policyContentArea');
    const policyTextContent = document.getElementById('policyTextContent');
    const acceptanceWrapper = document.getElementById('acceptanceWrapper');
    const scrollWarning = document.getElementById('scrollWarning');
    const policyAckCheckbox = document.getElementById('policyAckCheckbox');
    const nextPolicyBtn = document.getElementById('nextPolicyBtn');
    const policyNameLabel = document.getElementById('policyNameLabel');
    const policyBadge = document.getElementById('policyBadge');

    // Progress UI
    const policyStepsList = document.getElementById('policyStepsList');
    const policyStepText = document.getElementById('policyStepText');
    const policyProgressPercent = document.getElementById('policyProgressPercent');
    const policyProgressFill = document.getElementById('policyProgressFill');

    // Data


    let currentPolicyIndex = 0;

    function renderPolicySteps() {
        if (!policyStepsList) return;
        policyStepsList.innerHTML = '';

        if (!mandatoryPolicies || mandatoryPolicies.length === 0) {
            if (policyStepText) policyStepText.textContent = '0 of 0';
            if (policyProgressPercent) policyProgressPercent.textContent = '0%';
            if (policyProgressFill) policyProgressFill.style.width = '0%';
            if (policyBadge) policyBadge.style.display = 'none';
            return;
        }

        mandatoryPolicies.forEach((p, index) => {
            const el = document.createElement('div');
            el.className = `policy-step-item ${index === currentPolicyIndex ? 'active' : ''} ${p.accepted ? 'completed' : ''}`;

            // Icon
            let iconContent = index + 1;
            if (p.accepted) iconContent = '<i class="fa-solid fa-check"></i>';
            else if (index === currentPolicyIndex) iconContent = '<i class="fa-solid fa-play" style="font-size:0.6rem"></i>';
            else if (index > currentPolicyIndex) iconContent = '<i class="fa-solid fa-lock"></i>';

            el.innerHTML = `
                <div class="step-icon">${iconContent}</div>
                <div class="step-label">${p.title}</div>
            `;
            policyStepsList.appendChild(el);
        });

        // Update Progress Bar
        const acceptedCount = mandatoryPolicies.filter(p => p.accepted).length;
        const total = mandatoryPolicies.length;
        const pct = total > 0 ? Math.round((acceptedCount / total) * 100) : 0;

        if (policyStepText) policyStepText.textContent = `Step ${currentPolicyIndex + 1} of ${total}`;
        if (policyProgressPercent) policyProgressPercent.textContent = `${pct}%`;
        if (policyProgressFill) policyProgressFill.style.width = `${pct}%`;

        if (policyBadge) {
            const pending = total - acceptedCount;
            if (pending > 0) {
                policyBadge.style.display = 'flex';
                policyBadge.textContent = pending;
            } else {
                policyBadge.style.display = 'none';
            }
        }

        // Update HR Corner as well
        if (window.updateHRCornerDisplay) window.updateHRCornerDisplay();
    }

    function loadPolicy(index) {
        if (index < 0 || index >= mandatoryPolicies.length) return;

        const p = mandatoryPolicies[index];
        currentPolicyIndex = index;

        // Content
        policyTitleDisplay.textContent = p.title;
        policyVersionDisplay.textContent = p.version;
        policyTextContent.innerHTML = p.content;
        policyNameLabel.textContent = p.title;

        // Reset scroll
        policyContentArea.scrollTop = 0;

        // Reset controls
        policyAckCheckbox.checked = p.accepted;
        policyAckCheckbox.disabled = true; // Always disable initially until scroll
        nextPolicyBtn.disabled = true;

        if (p.accepted) {
            // If already accepted, allow moving freely
            policyAckCheckbox.disabled = true; // Keep checked and disabled
            nextPolicyBtn.disabled = false;
            nextPolicyBtn.textContent = index === mandatoryPolicies.length - 1 ? "Close" : "Next Policy";
            acceptanceWrapper.classList.remove('disabled');
            scrollWarning.style.display = 'none';
        } else {
            nextPolicyBtn.textContent = index === mandatoryPolicies.length - 1 ? "Accept & Finish" : "Accept & Continue";
            acceptanceWrapper.classList.add('disabled');
            scrollWarning.style.display = 'flex';

            // Check if content fits without scrolling (more forgiving: 50px tolerance)
            setTimeout(() => {
                if (policyContentArea.scrollHeight <= policyContentArea.clientHeight + 50) {
                    policyAckCheckbox.disabled = false;
                    acceptanceWrapper.classList.remove('disabled');
                    scrollWarning.style.display = 'none';
                }
            }, 300); // Increased delay slightly to ensure render
        }

        renderPolicySteps();
    }

    // Scroll Detection
    if (policyContentArea) {
        policyContentArea.addEventListener('scroll', () => {
            const p = mandatoryPolicies[currentPolicyIndex];
            if (p.accepted) return; // Already done

            // Check if scrolled to bottom with generous tolerance (80px)
            // Or if the content is so short that we are effectively at the bottom
            const distanceToBottom = policyContentArea.scrollHeight - (policyContentArea.scrollTop + policyContentArea.clientHeight);

            if (distanceToBottom <= 80 || policyContentArea.scrollHeight <= policyContentArea.clientHeight + 50) {
                // Scrolled to bottom or content is short
                if (policyAckCheckbox.disabled) {
                    policyAckCheckbox.disabled = false;
                    acceptanceWrapper.classList.remove('disabled');
                    scrollWarning.style.display = 'none';
                    // Optional: Highlight checkbox
                    policyAckCheckbox.parentElement.style.animation = 'pulse 1s';
                }
            }
        });
    }

    // Checkbox Logic
    if (policyAckCheckbox) {
        policyAckCheckbox.addEventListener('change', (e) => {
            nextPolicyBtn.disabled = !e.target.checked;
        });
    }

    // Next Button Logic
    if (nextPolicyBtn) {
        nextPolicyBtn.addEventListener('click', async () => {
            const p = mandatoryPolicies[currentPolicyIndex];
            p.accepted = true;

            // 1. Persist to SessionStorage (Immediate)
            if (p.notif_db_id) {
                sessionStorage.setItem(`hrNotice_db_accepted_${p.notif_db_id}`, 'true');
            } else if (p.db_id) {
                sessionStorage.setItem(`hrPolicy_db_accepted_${p.db_id}`, 'true');
            } else {
                sessionStorage.setItem(`hrPolicy_accepted_${p.id}`, 'true');
            }

            // 2. Persist to Database (Background)
            if (p.db_id || p.notif_db_id) {
                try {
                    fetch('api/acknowledge_hr_item.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            item_id: p.db_id || p.notif_db_id,
                            item_type: p.db_id ? 'policy' : 'notice'
                        })
                    });
                } catch (e) { console.error("Failed to save HR ack:", e); }
            }

            // Check if last
            if (currentPolicyIndex === mandatoryPolicies.length - 1) {
                // Close
                if (closePolicyModal) closePolicyModal.click();
                if (typeof renderPolicySteps === 'function') renderPolicySteps(); 
                if (typeof updateHRCornerDisplay === 'function') updateHRCornerDisplay();
                alert("All HR updates acknowledged successfully!");
            } else {
                // Next
                if (typeof loadPolicy === 'function') loadPolicy(currentPolicyIndex + 1);
            }
        });
    }

    // Modal Controls
    if (openPolicyBtn && policyModal) {
        openPolicyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (!mandatoryPolicies || mandatoryPolicies.length === 0) {
                alert('No pending HR policies/notices to acknowledge.');
                return;
            }

            // Find first unaccepted or start at 0
            const firstPending = mandatoryPolicies.findIndex(p => !p.accepted);
            loadPolicy(firstPending !== -1 ? firstPending : 0);
            policyModal.classList.add('visible');
            policyModal.classList.add('open');
        });
    }

    if (closePolicyBtn) {
        closePolicyBtn.addEventListener('click', () => {
            policyModal.classList.remove('visible');
            policyModal.classList.remove('open');
        });
    }

    // Upload Logic (Mock)
    const hrUploadBtn = document.getElementById('hrUploadBtn');
    const uploadModal = document.getElementById('uploadPolicyModal');
    const closeUploadModal = document.getElementById('closeUploadModal');
    const submitNewPolicy = document.getElementById('submitNewPolicy');

    if (hrUploadBtn && uploadModal) {
        hrUploadBtn.addEventListener('click', () => {
            uploadModal.classList.add('visible');
            uploadModal.classList.add('open');
        });

        if (closeUploadModal) {
            closeUploadModal.addEventListener('click', () => {
                uploadModal.classList.remove('visible');
                uploadModal.classList.remove('open');
            });
        }

        if (submitNewPolicy) {
            submitNewPolicy.addEventListener('click', () => {
                const title = document.getElementById('upPolicyTitle').value;
                const version = document.getElementById('upPolicyVersion').value;
                const content = document.getElementById('upPolicyContent').value;

                if (title && content) {
                    const newId = mandatoryPolicies.length + 1;
                    mandatoryPolicies.push({
                        id: newId,
                        title: title,
                        version: version || "v1.0",
                        content: `<h3>${newId}. ${title}</h3><p>${content.replace(/\n/g, '<br>')}</p><br><br><br><br><p><em>End of Section.</em></p>`,
                        accepted: false
                    });

                    alert("Policy Uploaded Successfully!");
                    uploadModal.classList.remove('visible');
                    uploadModal.classList.remove('open');
                    // Refresh current view if open
                    renderPolicySteps();
                } else {
                    alert("Please fill in Title and Content");
                }
            });
        }
    }

    // Init
    // Tasks rendered by components/my-tasks.js (fetchMyTasks called on DOMContentLoaded)
    if (typeof window.fetchMyTasks === 'function') window.fetchMyTasks('daily');
    // Initial Badge Update
    renderPolicySteps();


    // Punch In / Punch Out Toggle Logic
    const punchBtn = document.getElementById('punchBtn');
    const punchIcon = document.getElementById('punchIcon');
    const punchText = document.getElementById('punchText');

    let isPunchedIn = false; // Initial state: Not punched in
    let isPunchedOutForToday = false; // Lock out further punches today
    let _shiftEndTime = '18:00:00'; // Default shift end time

    // ── Check punch-in status on page load ─────────────────────────────────
    (async function checkInitialPunchStatus() {
        try {
            const res = await fetch('../punch.php?action=check_status');
            if (!res.ok) return;
            const data = await res.json();
            if (data.is_punched_in) {
                isPunchedIn = true;
                if (data.shift_end_time) _shiftEndTime = data.shift_end_time;

                if (punchBtn) {
                    punchBtn.classList.remove('dh-punch-in-state');
                    punchBtn.classList.add('dh-punch-out-state');
                }
                if (punchIcon) {
                    punchIcon.classList.remove('fa-right-to-bracket');
                    punchIcon.classList.add('fa-right-from-bracket');
                }
                if (punchText) punchText.textContent = 'Punch Out';
            } else if (data.already_punched_out) {
                isPunchedOutForToday = true;
                if (data.shift_end_time) _shiftEndTime = data.shift_end_time;

                if (punchBtn) {
                    punchBtn.classList.remove('dh-punch-in-state', 'dh-punch-out-state');
                    punchBtn.style.opacity = '0.6';
                    punchBtn.style.cursor = 'not-allowed';
                    punchBtn.style.backgroundColor = '#9ca3af';
                    punchBtn.style.color = '#ffffff';
                }
                if (punchIcon) {
                    punchIcon.classList.remove('fa-right-to-bracket', 'fa-right-from-bracket');
                    punchIcon.classList.add('fa-solid', 'fa-check-double');
                }
                if (punchText) punchText.textContent = 'Shift Completed';
            }
        } catch (e) {
            console.warn('Could not fetch punch status:', e);
        }
    })();

    // Shift Timer Countdown
    function updateShiftTimer() {
        const now = new Date();
        const endOfShift = new Date(now);

        // Parse the dynamic shift end time
        if (_shiftEndTime) {
            const timeParts = _shiftEndTime.split(':');
            endOfShift.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]), parseInt(timeParts[2] || 0), 0);
        } else {
            endOfShift.setHours(18, 0, 0, 0); // fallback to 6:00 PM
        }

        let diff = Math.floor((endOfShift - now) / 1000);

        const shiftTimerEl = document.getElementById('shiftTimer');
        const shiftTextLabel = document.getElementById('shiftTextLabel');
        const shiftTimerContainer = document.getElementById('shiftTimerContainer');
        const shiftTimerIcon = document.getElementById('shiftTimerIcon');

        if (diff <= 0 && isPunchedIn) {
            // Overtime logic
            let overtimeSeconds = Math.abs(diff);
            let h = Math.floor(overtimeSeconds / 3600);
            let m = Math.floor((overtimeSeconds % 3600) / 60);
            let s = overtimeSeconds % 60;

            h = h < 10 ? '0' + h : h;
            m = m < 10 ? '0' + m : m;
            s = s < 10 ? '0' + s : s;

            if (shiftTimerEl) shiftTimerEl.textContent = h + ' : ' + m + ' : ' + s;
            if (shiftTextLabel) shiftTextLabel.textContent = 'Overtime: ';

            if (shiftTimerContainer) shiftTimerContainer.classList.add('dh-overtime-active');
            if (shiftTimerIcon) {
                shiftTimerIcon.classList.remove('fa-hourglass-half');
                shiftTimerIcon.classList.add('fa-hourglass');
            }
        } else {
            // Normal shift countdown logic
            let shiftRemaining = Math.max(0, diff);
            let h = Math.floor(shiftRemaining / 3600);
            let m = Math.floor((shiftRemaining % 3600) / 60);
            let s = shiftRemaining % 60;

            h = h < 10 ? '0' + h : h;
            m = m < 10 ? '0' + m : m;
            s = s < 10 ? '0' + s : s;

            if (shiftTimerEl) shiftTimerEl.textContent = h + ':' + m + ':' + s;
            if (shiftTextLabel) shiftTextLabel.textContent = 'Shift ends in: ';

            if (shiftTimerContainer) shiftTimerContainer.classList.remove('dh-overtime-active');
            if (shiftTimerIcon) {
                shiftTimerIcon.classList.remove('fa-hourglass');
                shiftTimerIcon.classList.add('fa-hourglass-half');
            }
        }
    }
    setInterval(updateShiftTimer, 1000);
    updateShiftTimer();

    function executePunchIn() {
        isPunchedIn = true;

        punchBtn.classList.remove('dh-punch-in-state');
        punchBtn.classList.add('dh-punch-out-state');

        if (punchIcon) {
            punchIcon.classList.remove('fa-right-to-bracket');
            punchIcon.classList.add('fa-right-from-bracket');
        }

        if (punchText) {
            punchText.textContent = 'Punch Out';
        }

        const audio = new Audio('tones/punch_in.mp3');
        audio.play().catch(e => console.log("Audio play failed:", e));
    }

    function executePunchOut() {
        isPunchedIn = false;
        isPunchedOutForToday = true;

        punchBtn.classList.remove('dh-punch-in-state', 'dh-punch-out-state');
        punchBtn.style.opacity = '0.6';
        punchBtn.style.cursor = 'not-allowed';
        punchBtn.style.backgroundColor = '#9ca3af';
        punchBtn.style.color = '#ffffff';

        if (punchIcon) {
            punchIcon.classList.remove('fa-right-to-bracket', 'fa-right-from-bracket');
            punchIcon.classList.add('fa-solid', 'fa-check-double');
        }

        if (punchText) {
            punchText.textContent = 'Shift Completed';
        }

        const audio = new Audio('tones/punch_out.mp3');
        audio.play().catch(e => console.log("Audio play failed:", e));
    }

    if (punchBtn) {
        punchBtn.addEventListener('click', () => {
            if (isPunchedOutForToday) return; // Prevent clicks if shift is completed

            if (isPunchedIn) {
                // User is trying to punch out, enforce HR policy/notification rule
                const unacceptedPolicy = mandatoryPolicies.some(p => !p.accepted);
                const unacknowledgedNotif = latestHRNotification && !latestHRNotification.acknowledged;

                if (unacceptedPolicy || unacknowledgedNotif) {
                    console.log("[Compliance Trace]", { 
                        mandatoryPolicies, 
                        latestHRNotification, 
                        unacceptedPolicy, 
                        unacknowledgedNotif 
                    });
                    alert("You cannot punch out yet. Please review and accept all pending HR policies and notifications in the HR Corner.");
                    
                    // Directly open the Compliance Hub for the user
                    const pendingPolicy = mandatoryPolicies.find(p => !p.accepted);
                    if (pendingPolicy) {
                        const idx = mandatoryPolicies.indexOf(pendingPolicy);
                        if (typeof loadPolicy === 'function') loadPolicy(idx);
                        const pModal = document.getElementById('policyModal');
                        if (pModal) { 
                            pModal.classList.add('visible'); 
                            pModal.classList.add('open'); 
                        }
                    }
                    return; // Stop here, do not toggle state
                }
                const startPunchOutSequence = () => {
                    const punchOutModal = document.getElementById('punchOutModal');
                    if (punchOutModal) {
                        punchOutModal.classList.add('visible');
                        punchOutModal.classList.add('open');

                        const statusText = document.getElementById('punchOutStatus');
                        const video = document.getElementById('cameraPreviewOut');
                        const canvas = document.getElementById('cameraCanvasOut');
                        const grantBtn = document.getElementById('grantAccessBtnOut');
                        const switchCameraBtnOut = document.getElementById('switchCameraBtnOut');
                        const capturePicBtnOut = document.getElementById('capturePicBtnOut');
                        const retakeWrapperOut = document.getElementById('retakeWrapperOut');
                        const submitBtn = document.getElementById('submitPunchOutBtn');
                        const summaryTA = document.getElementById('punchOutSummary');

                        if (statusText) statusText.style.display = 'none';
                        if (video) {
                            video.style.display = 'none';
                            if (video.srcObject) {
                                video.srcObject.getTracks().forEach(track => track.stop());
                                video.srcObject = null;
                            }
                        }
                        if (canvas) canvas.style.display = 'none';
                        if (retakeWrapperOut) retakeWrapperOut.style.display = 'none';
                        if (capturePicBtnOut) capturePicBtnOut.style.display = 'none';
                        if (switchCameraBtnOut) switchCameraBtnOut.style.display = 'flex';
                        if (grantBtn) grantBtn.textContent = 'Grant Camera & Location Access';

                        if (submitBtn) {
                            submitBtn.style.display = 'none';
                            submitBtn.disabled = true;
                        }
                        const sendOvertimeBtn = document.getElementById('sendOvertimeBtn');
                        if (sendOvertimeBtn) {
                            sendOvertimeBtn.style.display = 'none';
                        }

                        if (summaryTA) {
                            summaryTA.value = '';
                            const wc = document.getElementById('wordCount');
                            if (wc) wc.textContent = '0';
                        }

                        // Generate smart work-report suggestions from completed tasks
                        generateWorkReportSuggestions();

                        if (localStorage.getItem('punchInAccessGranted') === 'true') {
                            if (grantBtn) grantBtn.style.display = 'none';
                            requestPunchOutAccess();
                        } else {
                            if (grantBtn) {
                                grantBtn.style.display = 'flex';
                                grantBtn.textContent = 'Grant Camera & Location Access';
                            }
                        }
                    } else {
                        executePunchOut();
                    }
                };

                // ── Task Deadline Check ──
                // If the user has any tasks extended to 8:00 PM today, block punch-out.
                if (typeof window.showUpcomingDeadlinesBeforePunchOut === 'function') {
                    window.showUpcomingDeadlinesBeforePunchOut(startPunchOutSequence);
                } else {
                    startPunchOutSequence();
                }
            } else {
                // User is trying to punch in, open modal to ask for camera and location
                const punchInModal = document.getElementById('punchInModal');
                if (punchInModal) {
                    punchInModal.classList.add('visible');
                    punchInModal.classList.add('open');

                    const video = document.getElementById('cameraPreview');
                    const canvas = document.getElementById('cameraCanvas');
                    const grantBtn = document.getElementById('grantAccessBtn');
                    const clickPicBtn = document.getElementById('clickPicBtn');

                    window.checkPunchInValidity = function () {
                        const clickPicBtn = document.getElementById('clickPicBtn');
                        const takePhotoBtn = document.getElementById('takePhotoBtnClick');
                        const punchInReason = document.getElementById('punchInReason');
                        const wordCountInReason = document.getElementById('wordCountInReason');
                        if (!clickPicBtn) return;

                        const rDiv = document.getElementById('outOfRangeReasonInDiv');
                        let needsReason = rDiv && rDiv.style.display !== 'none';

                        let reasonValid = true;
                        if (needsReason && punchInReason) {
                            let text = punchInReason.value.trim();
                            let words = text.split(/\s+/).filter(word => word.match(/[a-zA-Z0-9]/));
                            let count = text === '' ? 0 : words.length;
                            if (wordCountInReason) wordCountInReason.textContent = count;
                            if (count < 10) reasonValid = false;
                        }

                        let photoTaken = takePhotoBtn && takePhotoBtn.style.display === 'none';

                        if (photoTaken && reasonValid) {
                            clickPicBtn.disabled = false;
                            clickPicBtn.classList.add('active');
                            clickPicBtn.textContent = 'PUNCH IN';
                        } else {
                            clickPicBtn.disabled = true;
                            clickPicBtn.classList.remove('active');
                            if (!photoTaken) {
                                clickPicBtn.textContent = 'PLEASE TAKE A PHOTO';
                            } else if (!reasonValid) {
                                clickPicBtn.textContent = 'PROVIDE REASON';
                            }
                        }
                    };

                    document.addEventListener('input', function (e) {
                        if (e.target.id === 'punchInReason') window.checkPunchInValidity();
                    });

                    const takePhotoBtn = document.getElementById('takePhotoBtnClick');

                    const camControls = document.getElementById('cameraControlsGrp');
                    const camPlaceholder = document.getElementById('cameraPlaceholder');

                    if (video) {
                        video.style.display = 'none';
                        if (video.srcObject) {
                            video.srcObject.getTracks().forEach(track => track.stop());
                            video.srcObject = null;
                        }
                    }
                    if (canvas) canvas.style.display = 'none';
                    if (camControls) {
                        camControls.style.pointerEvents = 'none';
                        camControls.style.opacity = '0.5';
                    }
                    if (camPlaceholder) {
                        camPlaceholder.innerHTML = `<i class="fa-solid fa-camera-viewfinder" style="font-size: 3rem; margin-bottom: 12px; opacity: 0.7;"></i>
                                                    <span style="font-size: 0.9rem; font-weight: 500; letter-spacing: 0.5px;">Camera Feed Offline</span>`;
                    }

                    if (clickPicBtn) {
                        clickPicBtn.style.display = 'flex';
                        clickPicBtn.textContent = 'PLEASE TAKE A PHOTO';
                        clickPicBtn.classList.remove('active');
                        clickPicBtn.disabled = true;
                    }

                    if (takePhotoBtn) takePhotoBtn.style.display = 'none';

                    if (localStorage.getItem('punchInAccessGranted') === 'true') {
                        if (grantBtn) grantBtn.style.display = 'none';
                        if (clickPicBtn) clickPicBtn.style.display = 'flex';
                        requestPunchInAccess();
                    } else {
                        if (grantBtn) {
                            grantBtn.style.display = 'flex';
                            grantBtn.textContent = 'Grant Camera & Location Access';
                        }
                        if (clickPicBtn) clickPicBtn.style.display = 'none';
                    }
                } else {
                    executePunchIn();
                }
            }
        });
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3; // metres
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) + Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    async function requestPunchInAccess() {
        const video = document.getElementById('cameraPreview');
        const grantBtn = document.getElementById('grantAccessBtn');
        const clickPicBtn = document.getElementById('clickPicBtn');

        window.checkPunchInValidity = function () {
            const clickPicBtn = document.getElementById('clickPicBtn');
            const takePhotoBtn = document.getElementById('takePhotoBtnClick');
            const punchInReason = document.getElementById('punchInReason');
            const wordCountInReason = document.getElementById('wordCountInReason');
            if (!clickPicBtn) return;

            const rDiv = document.getElementById('outOfRangeReasonInDiv');
            let needsReason = rDiv && rDiv.style.display !== 'none';

            let reasonValid = true;
            if (needsReason && punchInReason) {
                let text = punchInReason.value.trim();
                let words = text.split(/\s+/).filter(word => word.match(/[a-zA-Z0-9]/));
                let count = text === '' ? 0 : words.length;
                if (wordCountInReason) wordCountInReason.textContent = count;
                if (count < 10) reasonValid = false;
            }

            let photoTaken = takePhotoBtn && takePhotoBtn.style.display === 'none';

            if (photoTaken && reasonValid) {
                clickPicBtn.disabled = false;
                clickPicBtn.classList.add('active');
                clickPicBtn.textContent = 'PUNCH IN';
            } else {
                clickPicBtn.disabled = true;
                clickPicBtn.classList.remove('active');
                if (!photoTaken) {
                    clickPicBtn.textContent = 'PLEASE TAKE A PHOTO';
                } else if (!reasonValid) {
                    clickPicBtn.textContent = 'PROVIDE REASON';
                }
            }
        };

        document.addEventListener('input', function (e) {
            if (e.target.id === 'punchInReason') window.checkPunchInValidity();
        });

        const takePhotoBtn = document.getElementById('takePhotoBtnClick');


        const geoCoordsText = document.getElementById('geoCoordsText');
        const geoAddressText = document.getElementById('geoAddressText');
        const geoStatusBanner = document.getElementById('geoStatusBanner');
        const geoStatusIcon = document.getElementById('geoStatusIcon');
        const geoStatusText = document.getElementById('geoStatusText');

        if (geoStatusBanner) geoStatusBanner.style.display = 'none';

        if (grantBtn) grantBtn.style.display = 'none';
        if (clickPicBtn) {
            clickPicBtn.style.display = 'flex';
            clickPicBtn.textContent = 'Requesting access...';
            clickPicBtn.disabled = true;
        }

        try {
            const locationPromise = new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error("Geolocation not supported."));
                } else {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: false,
                        timeout: 10000,
                        maximumAge: 60000
                    });
                }
            });

            const cameraPromise = navigator.mediaDevices && navigator.mediaDevices.getUserMedia
                ? navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
                : Promise.reject(new Error("Camera API not supported."));

            const stream = await cameraPromise;
            if (video) {
                video.srcObject = stream;
                video.style.display = 'block';
            }

            const camControls = document.getElementById('cameraControlsGrp');
            if (camControls) {
                camControls.style.pointerEvents = 'auto';
                camControls.style.opacity = '1';
            }

            localStorage.setItem('punchInAccessGranted', 'true');

            if (clickPicBtn) clickPicBtn.textContent = 'Fetching location...';
            const position = await locationPromise;

            if (position && position.coords) {
                const userLat = position.coords.latitude;
                const userLon = position.coords.longitude;
                const userAcc = position.coords.accuracy || null;

                // Store GPS for API call
                _punchInLat = userLat;
                _punchInLon = userLon;
                _punchInAcc = userAcc;
                _punchInAddr = userLat.toFixed(5) + ', ' + userLon.toFixed(5); // fallback until geocode resolves

                if (geoCoordsText) geoCoordsText.innerHTML = userLat.toFixed(6) + ', ' + userLon.toFixed(6);

                // Show placeholder immediately, then resolve real address in background
                if (geoAddressText) geoAddressText.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:5px;color:#94a3b8;"></i><em style="color:#94a3b8;">Fetching address...</em>';
                reverseGeocode(userLat, userLon).then(addr => {
                    _punchInAddr = addr;
                    if (geoAddressText) geoAddressText.innerHTML = addr;
                });

                // Dynamic geofence check from DB
                if (!_geofenceLocations.length) await loadGeofenceLocations();
                const geoResult = checkGeofence(userLat, userLon);

                if (!geoResult) {
                    // No zones configured — allow freely
                    if (geoStatusBanner) { geoStatusBanner.style.display = 'flex'; geoStatusBanner.style.background = '#f0fdf4'; }
                    if (geoStatusIcon) { geoStatusIcon.className = 'fa-solid fa-circle-check'; geoStatusIcon.style.color = '#22c55e'; }
                    if (geoStatusText) { geoStatusText.innerHTML = 'No geofence restriction. You may punch in.'; geoStatusText.style.color = '#166534'; }
                    const rDivNo = document.getElementById('outOfRangeReasonInDiv');
                    if (rDivNo) rDivNo.style.display = 'none';
                    if (takePhotoBtn) takePhotoBtn.style.display = 'flex';
                    if (clickPicBtn) { clickPicBtn.textContent = 'PLEASE TAKE A PHOTO'; clickPicBtn.disabled = true; clickPicBtn.classList.remove('active'); }
                    if (window.checkPunchInValidity) window.checkPunchInValidity();
                } else {
                    const zone = geoResult.zone;
                    const distM = Math.round(geoResult.distance);
                    const isInside = geoResult.isInside;
                    const distDisplay = distM >= 1000 ? (distM / 1000).toFixed(2) + ' km' : distM + ' m';

                    if (geoStatusBanner) geoStatusBanner.style.display = 'flex';

                    if (isInside) {
                        // Store geofence result for API payload
                        _punchInGeofenceId = zone.id;
                        _punchInWithinGeofence = 1;
                        _punchInDistance = Math.round(geoResult.distance);
                        if (geoStatusBanner) { geoStatusBanner.className = 'c-pi-status'; geoStatusBanner.style.background = '#f0fdf4'; }
                        if (geoStatusIcon) { geoStatusIcon.className = 'fa-solid fa-circle-check'; geoStatusIcon.style.color = '#22c55e'; }
                        if (geoStatusText) { geoStatusText.innerHTML = 'Within <strong>' + zone.name + '</strong> (' + distDisplay + ' &bull; radius: ' + zone.radius + 'm)'; geoStatusText.style.color = '#166534'; }
                        const rDivIn = document.getElementById('outOfRangeReasonInDiv');
                        if (rDivIn) rDivIn.style.display = 'none';
                        if (takePhotoBtn) takePhotoBtn.style.display = 'flex';
                        if (clickPicBtn) { clickPicBtn.textContent = 'PLEASE TAKE A PHOTO'; clickPicBtn.disabled = true; clickPicBtn.classList.remove('active'); }
                        if (window.checkPunchInValidity) window.checkPunchInValidity();
                    } else {
                        // Store geofence result for API payload
                        _punchInGeofenceId = zone.id;
                        _punchInWithinGeofence = 0;
                        _punchInDistance = Math.round(geoResult.distance);
                        if (geoStatusBanner) { geoStatusBanner.className = 'c-pi-status'; geoStatusBanner.style.background = '#fef2f2'; }
                        if (geoStatusIcon) { geoStatusIcon.className = 'fa-solid fa-circle-xmark'; geoStatusIcon.style.color = '#ef4444'; }
                        if (geoStatusText) { geoStatusText.innerHTML = 'Outside <strong>' + zone.name + '</strong> (' + distDisplay + ' away &bull; allowed: ' + zone.radius + 'm) &mdash; Reason required'; geoStatusText.style.color = '#991b1b'; }
                        const rDivOut = document.getElementById('outOfRangeReasonInDiv');
                        if (rDivOut) rDivOut.style.display = 'block';
                        if (takePhotoBtn) takePhotoBtn.style.display = 'flex';
                        if (clickPicBtn) { clickPicBtn.textContent = 'PLEASE TAKE A PHOTO'; clickPicBtn.disabled = true; clickPicBtn.classList.remove('active'); }
                        if (window.checkPunchInValidity) window.checkPunchInValidity();
                    }
                }
            }

        } catch (err) {
            let errorMsg = err.message;
            if (err.name === 'NotAllowedError' || err.code === err.PERMISSION_DENIED) errorMsg = "Permission denied.";

            const camPlaceholder = document.getElementById('cameraPlaceholder');
            if (camPlaceholder) {
                camPlaceholder.innerHTML = `<i class="fa-solid fa-triangle-exclamation" style="font-size: 2.5rem; margin-bottom: 12px; opacity: 0.8; color: #ef4444;"></i>
                                            <span style="font-size: 0.9rem; font-weight: 500; letter-spacing: 0.5px;">Access Denied</span>
                                            <span style="font-size: 0.75rem; color: #94a3b8; margin-top: 5px; text-align: center; max-width: 80%;">Please allow camera & location access in Chrome to punch in.</span>`;
            }

            if (geoStatusBanner) {
                geoStatusBanner.style.display = 'flex';
                geoStatusBanner.className = 'c-pi-status';
                geoStatusBanner.style.background = '#fef2f2';
                if (geoStatusIcon) {
                    geoStatusIcon.className = 'fa-solid fa-circle-xmark c-pi-icon-green';
                    geoStatusIcon.style.color = '#ef4444';
                }
                if (geoStatusText) {
                    geoStatusText.innerHTML = `Verification Failed: ${errorMsg}`;
                    geoStatusText.style.color = '#991b1b';
                }
            }

            if (grantBtn) {
                grantBtn.style.display = 'block';
                grantBtn.textContent = 'Retry Permissions';
            }
            if (clickPicBtn) clickPicBtn.style.display = 'none';
            localStorage.removeItem('punchInAccessGranted');
        }
    }


    function showDailyTasksPopup() {
        const modal = document.getElementById('dailyTasksModal');
        const listContainer = document.getElementById('dailyTasksListModal');
        const closeBtn = document.getElementById('closeDailyTasksModal');
        const ackBtn = document.getElementById('acknowledgeTasksBtn');

        if (!modal || !listContainer) return;

        // Clear previous tasks
        listContainer.innerHTML = '';

        // Filter and display daily tasks (e.g. pending ones)
        const pendingDaily = (tasksData.daily || []).filter(t => !t.checked);

        if (pendingDaily.length === 0) {
            listContainer.innerHTML = '<div style="padding: 15px; text-align: center; color: #64748b;">No pending recurring tasks for today! Great job!</div>';
        } else {
            pendingDaily.forEach(task => {
                const badgeColor = task.badge.toLowerCase() === 'high' ? '#ef4444' : task.badge.toLowerCase() === 'med' ? '#f59e0b' : '#3b82f6';
                const badgeBg = task.badge.toLowerCase() === 'high' ? 'rgba(239, 68, 68, 0.1)' : task.badge.toLowerCase() === 'med' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(59, 130, 246, 0.1)';

                const html = `
                    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: transform 0.2s;">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 600; color: #1e293b; font-size: 0.95rem;">${task.title}</span>
                                <span style="background-color: ${badgeBg}; color: ${badgeColor}; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">${task.badge}</span>
                            </div>
                            <span style="color: #64748b; font-size: 0.85rem;">${task.desc}</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="display: inline-flex; align-items: center; gap: 4px; color: #475569; font-weight: 600; font-size: 0.85rem; background-color: #f1f5f9; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <i class="fa-regular fa-clock" style="color: #94a3b8;"></i> ${task.time.replace(/<[^>]*>?/gm, '').trim()}
                            </span>
                        </div>
                    </div>
                `;
                listContainer.insertAdjacentHTML('beforeend', html);
            });
        }

        modal.classList.add('visible');
        modal.classList.add('open');

        function closeModal() {
            modal.classList.remove('visible');
            modal.classList.remove('open');
        }

        if (closeBtn) closeBtn.onclick = closeModal;
        if (ackBtn) ackBtn.onclick = closeModal;
    }

    // --- Punch Out Modal Logic ---
    async function requestPunchOutAccess() {
        const statusText = document.getElementById('punchOutStatus');
        const video = document.getElementById('cameraPreviewOut');
        const grantBtn = document.getElementById('grantAccessBtnOut');
        const switchCameraBtnOut = document.getElementById('switchCameraBtnOut');

        const geoCoordsText = document.getElementById('geoCoordsTextOut');
        const geoAddressText = document.getElementById('geoAddressTextOut');
        const geoStatusBanner = document.getElementById('geoStatusBannerOut');
        const geoStatusIcon = document.getElementById('geoStatusIconOut');
        const geoStatusText = document.getElementById('geoStatusTextOut');

        if (geoStatusBanner) geoStatusBanner.style.display = 'none';

        if (statusText) {
            statusText.style.display = 'block';
            statusText.style.color = '#3b82f6';
            statusText.textContent = 'Requesting camera and location...';
        }
        if (grantBtn) grantBtn.style.display = 'none';
        if (switchCameraBtnOut) switchCameraBtnOut.style.display = 'flex';

        try {
            const locationPromise = new Promise((resolve, reject) => {
                if (!navigator.geolocation) reject(new Error("Geolocation not supported."));
                else navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
            });
            const activeFacingModeOut = window._punchOutFacingMode || 'user';
            const cameraPromise = navigator.mediaDevices && navigator.mediaDevices.getUserMedia
                ? navigator.mediaDevices.getUserMedia({ video: { facingMode: activeFacingModeOut } })
                : Promise.reject(new Error("Camera API not supported."));

            // Display camera immediately so UI feels snappier
            const stream = await cameraPromise;
            if (video) {
                video.srcObject = stream;
                video.style.display = 'block';
            }
            if (switchCameraBtnOut) switchCameraBtnOut.style.display = 'flex';
            localStorage.setItem('punchInAccessGranted', 'true');

            // Wait for location in the background
            const position = await locationPromise;

            if (statusText) statusText.style.display = 'none';

            if (position && position.coords) {
                const userLat = position.coords.latitude;
                const userLon = position.coords.longitude;
                const userAcc = position.coords.accuracy || null;

                // Store GPS for API call
                _punchOutLat = userLat;
                _punchOutLon = userLon;
                _punchOutAcc = userAcc;
                _punchOutAddr = userLat.toFixed(5) + ', ' + userLon.toFixed(5); // fallback until geocode resolves

                if (geoCoordsText) geoCoordsText.innerHTML = 'Coordinates: ' + userLat.toFixed(6) + ', ' + userLon.toFixed(6);

                // Show placeholder immediately, then resolve real address in background
                if (geoAddressText) geoAddressText.innerHTML = 'Address: <i class="fa-solid fa-spinner fa-spin" style="margin-right:5px;color:#94a3b8;"></i><em style="color:#94a3b8;">Fetching address...</em>';
                reverseGeocode(userLat, userLon).then(addr => {
                    _punchOutAddr = addr;
                    if (geoAddressText) geoAddressText.innerHTML = 'Address: ' + addr;
                });

                // Dynamic geofence check from DB
                if (!_geofenceLocations.length) await loadGeofenceLocations();
                const geoResultOut = checkGeofence(userLat, userLon);

                const capturePicBtnOut = document.getElementById('capturePicBtnOut');
                const retakeWrapperOut = document.getElementById('retakeWrapperOut');
                const submitBtn = document.getElementById('submitPunchOutBtn');

                if (!geoResultOut) {
                    // No zones configured — allow freely
                    if (geoAddressText) geoAddressText.innerHTML = 'Address: <i class="fa-solid fa-spinner fa-spin" style="margin-right:5px;color:#94a3b8;"></i><em style="color:#94a3b8;">Fetching address...</em>';
                    if (geoStatusBanner) { geoStatusBanner.style.display = 'flex'; geoStatusBanner.style.background = '#f0fdf4'; }
                    if (geoStatusIcon) { geoStatusIcon.className = 'fa-solid fa-circle-check'; geoStatusIcon.style.color = '#22c55e'; }
                    if (geoStatusText) { geoStatusText.innerHTML = 'No geofence restriction. You may punch out.'; geoStatusText.style.color = '#166534'; }
                    const rDivFree = document.getElementById('outOfRangeReasonOutDiv');
                    if (rDivFree) rDivFree.style.display = 'none';
                    if (capturePicBtnOut) capturePicBtnOut.style.display = 'flex';
                    if (switchCameraBtnOut) switchCameraBtnOut.style.display = 'flex';
                    if (retakeWrapperOut) retakeWrapperOut.style.display = 'none';
                    if (submitBtn) { submitBtn.style.display = 'block'; checkPunchOutValidity(); }
                } else {
                    const zone = geoResultOut.zone;
                    const distM = Math.round(geoResultOut.distance);
                    const isInside = geoResultOut.isInside;
                    const distDisplay = distM >= 1000 ? (distM / 1000).toFixed(2) + ' km' : distM + ' m';

                    if (geoStatusBanner) geoStatusBanner.style.display = 'flex';

                    if (isInside) {
                        // Store geofence result for API payload
                        _punchOutGeofenceId = zone.id;
                        _punchOutWithinGeofence = 1;
                        _punchOutDistance = Math.round(geoResultOut.distance);
                        if (geoStatusBanner) { geoStatusBanner.style.background = '#f0fdf4'; }
                        if (geoStatusIcon) { geoStatusIcon.className = 'fa-solid fa-circle-check'; geoStatusIcon.style.color = '#22c55e'; }
                        if (geoStatusText) { geoStatusText.innerHTML = 'Within <strong>' + zone.name + '</strong> (' + distDisplay + ' &bull; radius: ' + zone.radius + 'm)'; geoStatusText.style.color = '#166534'; }
                        const rDivIn = document.getElementById('outOfRangeReasonOutDiv');
                        if (rDivIn) rDivIn.style.display = 'none';
                        if (capturePicBtnOut) capturePicBtnOut.style.display = 'flex';
                        if (switchCameraBtnOut) switchCameraBtnOut.style.display = 'flex';
                        if (retakeWrapperOut) retakeWrapperOut.style.display = 'none';
                        if (submitBtn) { submitBtn.style.display = 'block'; checkPunchOutValidity(); }
                    } else {
                        // Store geofence result for API payload
                        _punchOutGeofenceId = zone.id;
                        _punchOutWithinGeofence = 0;
                        _punchOutDistance = Math.round(geoResultOut.distance);
                        if (geoStatusBanner) { geoStatusBanner.style.background = '#fef2f2'; }
                        if (geoStatusIcon) { geoStatusIcon.className = 'fa-solid fa-circle-xmark'; geoStatusIcon.style.color = '#ef4444'; }
                        if (geoStatusText) { geoStatusText.innerHTML = 'Outside <strong>' + zone.name + '</strong> (' + distDisplay + ' away &bull; allowed: ' + zone.radius + 'm) &mdash; Reason required'; geoStatusText.style.color = '#991b1b'; }
                        const rDivOut2 = document.getElementById('outOfRangeReasonOutDiv');
                        if (rDivOut2) rDivOut2.style.display = 'block';
                        if (capturePicBtnOut) capturePicBtnOut.style.display = 'flex';
                        if (switchCameraBtnOut) switchCameraBtnOut.style.display = 'flex';
                        if (retakeWrapperOut) retakeWrapperOut.style.display = 'none';
                        if (submitBtn) { submitBtn.style.display = 'block'; checkPunchOutValidity(); }
                    }
                }
            }
        } catch (err) {
            let errorMsg = err.message;
            if (err.code === err.PERMISSION_DENIED) errorMsg = "Permission denied.";
            if (statusText) {
                statusText.style.color = '#dc2626';
                statusText.textContent = 'Access failed: ' + errorMsg + ' Please allow permissions.';
            }
            if (grantBtn) {
                grantBtn.style.display = 'flex';
                grantBtn.textContent = 'Try Again';
            }
            if (switchCameraBtnOut) switchCameraBtnOut.style.display = 'flex';
            localStorage.removeItem('punchInAccessGranted');
        }
    }


    // ── Work Report Smart Suggestions ─────────────────────────────────────────
    function generateWorkReportSuggestions() {
        const panel = document.getElementById('workReportSuggestionsPanel');
        const list = document.getElementById('workReportSuggestionsList');
        const noSuggest = document.getElementById('workReportNoSuggestions');
        if (!panel || !list) return;

        // Gather ALL completed tasks across every period (no limit)
        const allCompleted = [];
        Object.values(window.tasksData || {}).forEach(periodTasks => {
            periodTasks.forEach(t => {
                if (t.checked) allCompleted.push(t);
            });
        });

        list.innerHTML = '';

        if (allCompleted.length === 0) {
            list.style.display = 'none';
            noSuggest.style.display = 'block';
            panel.style.display = 'block';
            return;
        }

        noSuggest.style.display = 'none';
        list.style.display = 'flex';
        panel.style.display = 'block';

        const count = allCompleted.length;
        const shortWords = (text, maxWords = 3) => {
            const words = String(text || '').trim().split(/\s+/).filter(Boolean);
            return words.slice(0, maxWords).join(' ');
        };

        const cleanTitle = (t) => String(t?.title || 'Task').trim();
        const conciseBullets = allCompleted.map(t => {
            const title = cleanTitle(t);
            const mini = shortWords(t?.desc, 3);
            return mini ? `• ${title} — ${mini}` : `• ${title}`;
        }).join('\n');

        const standardBullets = allCompleted.map(t => {
            const title = cleanTitle(t);
            const detail = shortWords(t?.desc, 8);
            return detail ? `• ${title} — ${detail}` : `• ${title}`;
        }).join('\n');

        const titleOnlyBullets = allCompleted
            .map(t => `• ${cleanTitle(t)}`)
            .join('\n');

        // 3 concise, professional variations using only completed-task data
        const variations = [
            {
                label: '📝 Formal',
                badge: '#1e40af',
                badgeBg: '#dbeafe',
                text: `Today I completed ${count} assigned task${count > 1 ? 's' : ''}:\n${conciseBullets}`
            },
            {
                label: '📋 Detailed',
                badge: '#065f46',
                badgeBg: '#d1fae5',
                text: `Work completed today (${count}):\n${standardBullets}`
            },
            {
                label: '⚡ Brief',
                badge: '#92400e',
                badgeBg: '#fef3c7',
                text: `Completed ${count} task${count > 1 ? 's' : ''}:\n${titleOnlyBullets}`
            }
        ];

        variations.forEach(v => {
            const chip = document.createElement('div');
            chip.style.cssText = [
                'background: linear-gradient(135deg, #f5f3ff, #ede9fe)',
                'border: 1px solid #c4b5fd',
                'border-radius: 10px',
                'padding: 11px 13px',
                'font-size: 0.82rem',
                'color: #3730a3',
                'cursor: pointer',
                'line-height: 1.5',
                'transition: all 0.18s ease',
                'display: flex',
                'flex-direction: column',
                'gap: 6px',
            ].join(';');

            chip.innerHTML = `
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:0.7rem; font-weight:700; color:${v.badge}; background:${v.badgeBg};
                                 padding:2px 8px; border-radius:20px; letter-spacing:0.4px;">${v.label}</span>
                    <i class="fa-solid fa-hand-pointer" style="color:#a5b4fc; font-size:0.7rem; margin-left:auto;"></i>
                    <span style="font-size:0.7rem; color:#a5b4fc;">click to use</span>
                </div>
                <span style="color:#374151; white-space:pre-line;">${v.text}</span>
            `;

            // Hover
            chip.addEventListener('mouseenter', () => {
                chip.style.background = 'linear-gradient(135deg, #ede9fe, #ddd6fe)';
                chip.style.borderColor = '#818cf8';
                chip.style.transform = 'translateY(-1px)';
                chip.style.boxShadow = '0 4px 14px rgba(99,102,241,0.18)';
            });
            chip.addEventListener('mouseleave', () => {
                chip.style.background = 'linear-gradient(135deg, #f5f3ff, #ede9fe)';
                chip.style.borderColor = '#c4b5fd';
                chip.style.transform = 'translateY(0)';
                chip.style.boxShadow = 'none';
            });

            // Click → fill textarea & trigger word-count
            chip.addEventListener('click', () => {
                const ta = document.getElementById('punchOutSummary');
                if (ta) {
                    ta.value = v.text;
                    ta.dispatchEvent(new Event('input'));
                    ta.focus();
                    ta.scrollTop = 0;
                }
                // Flash selected state
                chip.style.background = 'linear-gradient(135deg, #6366f1, #818cf8)';
                chip.style.borderColor = '#6366f1';
                chip.querySelectorAll('span, i').forEach(el => el.style.color = '#fff');
                setTimeout(() => {
                    chip.style.background = 'linear-gradient(135deg, #ede9fe, #ddd6fe)';
                    chip.style.borderColor = '#818cf8';
                    chip.querySelectorAll('span, i').forEach(el => el.style.color = '');
                }, 700);
            });

            list.appendChild(chip);
        });
    }


    // --- Task List Table Interactions (Search, Filter, Sort) ---
    // --- Task List Date Range Filter ---
    const applyDateBtn = document.getElementById('applyDateBtn');
    const taskDateFrom = document.getElementById('taskDateFrom');
    const taskDateTo = document.getElementById('taskDateTo');

    if (applyDateBtn && taskDateFrom && taskDateTo) {
        applyDateBtn.addEventListener('click', () => {
            const from = taskDateFrom.value;
            const to = taskDateTo.value;
            const url = new URL(window.location.href);
            url.searchParams.set('from', from);
            url.searchParams.set('to', to);
            // Append hash to jump back to the task list section
            window.location.href = url.toString() + '#taskTableSection';
        });
    }

    const taskSearchInput = document.getElementById('taskSearchInput');
    const taskFilterBtn = document.getElementById('taskFilterBtn');
    const filterDropdown = document.getElementById('filterDropdown');
    const filterOptions = document.querySelectorAll('.filter-option');
    const taskSortBtn = document.getElementById('taskSortBtn');
    const sortDropdown = document.getElementById('sortDropdown');
    const sortOptions = document.querySelectorAll('.sort-option');
    const taskListTableBody = document.getElementById('taskListTableBody');

    if (taskListTableBody) {
        let currentStatusFilter = 'All';
        let customSearchTerm = '';

        function updateTableDisplay() {
            const rows = taskListTableBody.querySelectorAll('.task-list-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const statusCell = row.cells[4].textContent.trim();

                const matchesSearch = text.includes(customSearchTerm);
                const matchesFilter = currentStatusFilter === 'All' || statusCell === currentStatusFilter;

                row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
            });
        }

        if (taskSearchInput) {
            taskSearchInput.addEventListener('input', (e) => {
                customSearchTerm = e.target.value.toLowerCase();
                updateTableDisplay();
            });
        }

        // --- Task Modal Click Handler ---
        taskListTableBody.addEventListener('click', (e) => {
            const row = e.target.closest('.task-list-row');
            if (row && row.hasAttribute('data-task-json')) {
                try {
                    const taskData = JSON.parse(row.getAttribute('data-task-json'));
                    if (window.TaskModal && window.TaskModal.open) {
                        window.TaskModal.open(taskData);
                    }
                } catch (err) {
                    console.error("[TaskList] Error parsing task JSON:", err);
                }
            }
        });

        if (taskFilterBtn && filterDropdown) {
            taskFilterBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                filterDropdown.style.display = filterDropdown.style.display === 'none' ? 'block' : 'none';
                if (sortDropdown) sortDropdown.style.display = 'none'; // Close other
            });

            filterOptions.forEach(opt => {
                opt.addEventListener('click', (e) => {
                    const targetOption = e.currentTarget;
                    currentStatusFilter = targetOption.getAttribute('data-status');
                    updateTableDisplay();

                    // Update checkmarks
                    filterOptions.forEach(o => {
                        o.classList.remove('mtpd-active');
                        const check = o.querySelector('.mtpd-check');
                        if (check) check.style.display = 'none';
                    });

                    targetOption.classList.add('mtpd-active');
                    const activeCheck = targetOption.querySelector('.mtpd-check');
                    if (activeCheck) activeCheck.style.display = 'inline-block';

                    setTimeout(() => {
                        filterDropdown.style.display = 'none';
                    }, 150);
                });

                // Add hover effect
                opt.addEventListener('mouseenter', () => {
                    opt.style.background = '#f8fafc';
                });
                opt.addEventListener('mouseleave', () => {
                    opt.style.background = 'transparent';
                });
            });
        }

        if (taskSortBtn && sortDropdown) {
            taskSortBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sortDropdown.style.display = sortDropdown.style.display === 'none' ? 'block' : 'none';
                if (filterDropdown) filterDropdown.style.display = 'none'; // Close other
            });

            sortOptions.forEach(opt => {
                opt.addEventListener('click', (e) => {
                    const sortKey = e.target.getAttribute('data-sort');
                    sortDropdown.style.display = 'none';

                    const rows = Array.from(taskListTableBody.querySelectorAll('.task-list-row'));
                    rows.sort((a, b) => {
                        if (sortKey === 'Priority') {
                            const pA = a.cells[1].textContent.trim();
                            const pB = b.cells[1].textContent.trim();
                            const pVals = { 'High': 3, 'Medium': 2, 'Low': 1 };
                            return (pVals[pB] || 0) - (pVals[pA] || 0); // descending
                        } else if (sortKey === 'DueDate') {
                            const dA = new Date(a.cells[2].textContent.trim());
                            const dB = new Date(b.cells[2].textContent.trim());
                            return dA - dB; // ascending
                        } else if (sortKey === 'Status') {
                            const sA = a.cells[4].textContent.trim();
                            const sB = b.cells[4].textContent.trim();
                            return sA.localeCompare(sB); // alphanumeric
                        }
                        return 0;
                    });

                    rows.forEach(row => taskListTableBody.appendChild(row));
                });
            });
        }

        document.addEventListener('click', (e) => {
            if (filterDropdown && !taskFilterBtn.contains(e.target)) filterDropdown.style.display = 'none';
            if (sortDropdown && !taskSortBtn.contains(e.target)) sortDropdown.style.display = 'none';
        });
    }

    // --- Dynamic Schedule Timeline Logic ---
    const scheduleScrollWrapper = document.getElementById('scheduleScrollWrapper');
    const timelineHeaders = document.getElementById('timelineHeaders');
    const timelineGrid = document.getElementById('timelineGrid');
    const timelineEvents = document.getElementById('timelineEvents');
    const currentTimeIndicator = document.getElementById('currentTimeIndicator');
    const scrollScheduleLeft = document.getElementById('scrollScheduleLeft');
    const scrollScheduleRight = document.getElementById('scrollScheduleRight');

    if (scheduleScrollWrapper && timelineHeaders && timelineGrid && timelineEvents) {
        // Timeline config spans from 12:00 AM to 12:00 AM (24 hours)
        const startHour = 0;
        const endHour = 24;
        const totalHours = endHour - startHour;

        // Mock Tasks Data (with specific start times and durations)
        const scheduleTasks = [
            { id: 1, title: "Finalize Presentation Slides", start: "07:00", duration: 90, type: "purple", lane: 0, priority: "High", deadline: "Today, 10:00 AM", assignee: "Sarah Williams" },
            { id: 2, title: "Respond to Client Emails", start: "07:20", duration: 60, type: "gray", lane: 1, priority: "Medium", deadline: "Today, 12:00 PM", assignee: "Alex Johnson" },
            { id: 3, title: "Update Social Media Profiles", start: "09:00", duration: 120, type: "yellow", lane: 2, priority: "Low", deadline: "Tomorrow, 5:00 PM", assignee: "Mike Davis" },
            { id: 4, title: "Conduct Team Meeting", start: "11:00", duration: 60, type: "purple", lane: 0, priority: "High", deadline: "Today, 11:00 AM", assignee: "Team" },
            { id: 5, title: "Complete Daily Report", start: "12:10", duration: 45, type: "blue", lane: 1, priority: "Medium", deadline: "Today, 6:00 PM", assignee: "David Miller" },
            { id: 6, title: "Lunch Break", start: "13:30", duration: 30, type: "gray", lane: 2, priority: "Low", deadline: "Today, 2:00 PM", assignee: "You" },
            { id: 7, title: "Project Alpha Sync", start: "16:00", duration: 45, type: "yellow", lane: 0, priority: "High", deadline: "Today, 5:00 PM", assignee: "Anna Tailor" }
        ];

        const colors = {
            purple: { bg: '#f4f4f4', border: '#e5e5e5', text: '#000', badgeBg: '#000', badgeText: '#fff', accent: '#a855f7' },
            yellow: { bg: '#f4f4f4', border: '#e5e5e5', text: '#000', badgeBg: '#000', badgeText: '#fff', accent: '#f59e0b' },
            gray: { bg: '#f4f4f4', border: '#e5e5e5', text: '#000', badgeBg: '#000', badgeText: '#fff', accent: '#64748b' },
            blue: { bg: '#f4f4f4', border: '#e5e5e5', text: '#000', badgeBg: '#000', badgeText: '#fff', accent: '#3b82f6' }
        };

        function formatHour(hour) {
            const h = hour % 12 || 12;
            const ampm = (hour < 12 || hour === 24) ? 'AM' : 'PM';
            return `${h < 10 ? '0' + h : h}:00 ${ampm}`;
        }

        function formatTimeMin(timeStr) {
            const [hStr, mStr] = timeStr.split(':');
            const hour = parseInt(hStr);
            const h = hour % 12 || 12;
            const ampm = (hour < 12 || hour === 24) ? 'AM' : 'PM';
            return `${h < 10 ? '0' + h : h}:${mStr} ${ampm}`;
        }

        function timeToPercent(timeStr) {
            const [h, m] = timeStr.split(':').map(Number);
            const totalMinutes = (h - startHour) * 60 + m;
            return (totalMinutes / (totalHours * 60)) * 100;
        }

        function renderTimeline() {
            timelineHeaders.innerHTML = '';
            timelineGrid.innerHTML = '';
            timelineEvents.innerHTML = '';

            // Render Headers & Grid Lines
            for (let i = 0; i <= totalHours; i++) {
                const hour = startHour + i;
                const percent = (i / totalHours) * 100;

                // Header
                const headerSpan = document.createElement('span');
                headerSpan.style.position = 'absolute';
                headerSpan.style.left = `calc(${percent}% - 22px)`; // Center text approx
                headerSpan.style.whiteSpace = 'nowrap';
                headerSpan.style.color = '#aaa';
                headerSpan.style.fontSize = '0.8rem';
                headerSpan.style.fontWeight = '500';
                headerSpan.textContent = formatHour(hour);
                timelineHeaders.appendChild(headerSpan);

                // Grid line
                const gridLine = document.createElement('div');
                gridLine.style.position = 'absolute';
                gridLine.style.left = `${percent}%`;
                gridLine.style.top = '0';
                gridLine.style.bottom = '0';
                gridLine.style.width = '1px';
                gridLine.style.background = '#f0f0f0';
                timelineGrid.appendChild(gridLine);
            }

            // Render Events
            scheduleTasks.forEach(task => {
                const percentLeft = timeToPercent(task.start);
                const percentWidth = (task.duration / (totalHours * 60)) * 100;
                const topVal = task.lane * 3.5; // spaced vertically by lane

                const theme = colors[task.type] || colors.gray;
                const formattedTime = formatTimeMin(task.start);

                const eventCard = document.createElement('div');
                eventCard.style.position = 'absolute';
                eventCard.style.left = `${percentLeft}%`;
                eventCard.style.width = `calc(${percentWidth}% - 10px)`;
                eventCard.style.minWidth = 'max-content';
                eventCard.style.paddingRight = '1rem';
                eventCard.style.top = `${topVal}rem`;
                eventCard.style.background = theme.bg;
                eventCard.style.boxShadow = `0 2px 5px rgba(0,0,0,0.05), inset 0 0 0 1px ${theme.border}`;
                eventCard.style.padding = '0.5rem';
                eventCard.style.borderRadius = '0.5rem';
                eventCard.style.fontSize = '0.8rem';
                eventCard.style.fontWeight = '600';
                eventCard.style.display = 'flex';
                eventCard.style.alignItems = 'center';
                eventCard.style.gap = '0.5rem';
                eventCard.style.overflow = 'hidden';
                eventCard.style.whiteSpace = 'nowrap';
                eventCard.style.cursor = 'pointer';
                eventCard.title = `${formattedTime} - ${task.title}`;

                let badgeInlineStyle = `background: ${theme.badgeBg}; color: ${theme.badgeText}; padding: 0.2rem 0.4rem; border-radius: 0.25rem; flex-shrink: 0;`;
                if (task.type === 'purple' && theme.badgeBg === '#fff' && task.title === 'Conduct Team Meeting') {
                    badgeInlineStyle = `background: #e9d5ff; color: #000; padding: 0.2rem 0.4rem; border-radius: 0.25rem; flex-shrink: 0;`;
                }

                eventCard.innerHTML = `
                    <span style="${badgeInlineStyle}">${formattedTime}</span>
                    <div style="width: 10px; height: 10px; border-radius: 50%; background-color: ${theme.accent}; flex-shrink: 0;" title="Task Category / Manager"></div>
                    <span style="overflow: hidden; text-overflow: ellipsis; flex-grow: 1; color: ${theme.text};">${task.title}</span>
                `;

                // Set click event to open details popup
                eventCard.addEventListener('click', () => {
                    const modal = document.getElementById('scheduleModal');
                    const titleEl = document.getElementById('scheduleModalTitle');
                    const taskNameEl = document.getElementById('scheduleModalTaskName');
                    const timeEl = document.getElementById('scheduleModalTime');
                    const durationEl = document.getElementById('scheduleModalDuration');
                    const assigneeEl = document.getElementById('scheduleModalAssignee');
                    const priorityEl = document.getElementById('scheduleModalPriority');
                    const deadlineEl = document.getElementById('scheduleModalDeadline');

                    if (modal && titleEl && timeEl && durationEl) {
                        titleEl.textContent = 'Task Details'; // Revert to static header
                        if (taskNameEl) taskNameEl.textContent = task.title; // Bind actual task name
                        timeEl.textContent = formattedTime;
                        durationEl.textContent = task.duration + ' Minutes';

                        if (assigneeEl) {
                            assigneeEl.innerHTML = `<span style="display:flex; align-items:center; gap:0.5rem;"><img src="https://ui-avatars.com/api/?name=${encodeURIComponent(task.assignee || 'Unassigned')}&background=random&color=fff&rounded=true&size=32" style="width:24px; height:24px; border-radius:50%;">${task.assignee || 'Unassigned'}</span>`;
                        }

                        if (priorityEl) {
                            const badgeColor = (task.priority || "Normal").toLowerCase() === 'high' ? '#ef4444' :
                                (task.priority || "Normal").toLowerCase() === 'medium' ? '#f59e0b' :
                                    '#10b981';
                            const badgeBg = (task.priority || "Normal").toLowerCase() === 'high' ? '#fef2f2' :
                                (task.priority || "Normal").toLowerCase() === 'medium' ? '#fffbeb' :
                                    '#ecfdf5';
                            priorityEl.innerHTML = `<span style="background:${badgeBg}; color:${badgeColor}; border: 1px solid ${badgeColor}30; padding:4px 10px; border-radius:6px; font-size:0.85rem; display:inline-block;">${task.priority || 'Normal'}</span>`;
                        }

                        if (deadlineEl) {
                            deadlineEl.innerHTML = `<span>${task.deadline || 'No Deadline'}</span>`;
                        }

                        modal.classList.add('visible', 'open');
                    }
                });

                timelineEvents.appendChild(eventCard);
            });
        }

        function updateCurrentTime() {
            const now = new Date();
            const h = now.getHours();
            const m = now.getMinutes();

            if (h >= startHour && h <= endHour) {
                const percent = timeToPercent(`${h}:${m}`);
                currentTimeIndicator.style.display = 'block';
                currentTimeIndicator.style.left = `${percent}%`;

                const headers = timelineHeaders.querySelectorAll('span');
                const currentIndex = h - startHour;
                headers.forEach((span, idx) => {
                    if (idx === currentIndex) {
                        span.style.color = '#ef4444';
                        span.style.fontWeight = '700';
                    } else {
                        span.style.color = '#aaa';
                        span.style.fontWeight = '500';
                    }
                });
            } else {
                currentTimeIndicator.style.display = 'none';
            }
        }

        renderTimeline();
        updateCurrentTime();

        // Auto scroll to current time position on load
        setTimeout(() => {
            const now = new Date();
            let h = now.getHours();
            if (h < startHour) h = startHour;
            if (h > endHour) h = endHour;

            const positionPercent = timeToPercent(`${h}:00`);
            const scrollWidth = scheduleScrollWrapper.scrollWidth;
            const viewWidth = scheduleScrollWrapper.clientWidth;

            let scrollPos = (scrollWidth * (positionPercent / 100)) - (viewWidth / 2);
            scheduleScrollWrapper.scrollTo({ left: scrollPos, behavior: 'smooth' });
        }, 300);

        setInterval(updateCurrentTime, 60000);

        if (scrollScheduleLeft) {
            scrollScheduleLeft.addEventListener('click', () => {
                scheduleScrollWrapper.scrollBy({ left: -300, behavior: 'smooth' });
            });
        }
        if (scrollScheduleRight) {
            scrollScheduleRight.addEventListener('click', () => {
                scheduleScrollWrapper.scrollBy({ left: 300, behavior: 'smooth' });
            });
        }

        const scheduleModal = document.getElementById('scheduleModal');
        const closeScheduleModal = document.getElementById('closeScheduleModal');
        if (scheduleModal) {
            if (closeScheduleModal) {
                closeScheduleModal.addEventListener('click', () => {
                    scheduleModal.classList.remove('visible', 'open');
                });
            }
            scheduleModal.addEventListener('click', (e) => {
                if (e.target === scheduleModal) {
                    scheduleModal.classList.remove('visible', 'open');
                }
            });
        }
    }

    // --- Dynamic Team Schedule Logic (with Zoom) ---
    const teamScheduleScrollWrapper = document.getElementById('teamScheduleScrollWrapper');
    const teamTimelineHeaders = document.getElementById('teamTimelineHeaders');
    const teamTimelineGrid = document.getElementById('teamTimelineGrid');
    const teamTimelineEvents = document.getElementById('teamTimelineEvents');
    const teamCurrentTimeIndicator = document.getElementById('teamCurrentTimeIndicator');
    const teamZoomInBtn = document.getElementById('teamZoomInBtn');
    const teamZoomOutBtn = document.getElementById('teamZoomOutBtn');
    const teamZoomLevelText = document.getElementById('teamZoomLevelText');
    const teamScrollLeft = document.getElementById('teamScrollScheduleLeft');
    const teamScrollRight = document.getElementById('teamScrollScheduleRight');

    if (teamScheduleScrollWrapper && teamTimelineHeaders && teamTimelineGrid && teamTimelineEvents) {
        let zoomLevels = ['day', 'week', 'year'];
        let currentZoomIndex = 0; // Starts at 'day'

        const teamMembers = [
            { id: 1, name: "Dhruv", lane: 0, avatar: "https://ui-avatars.com/api/?name=Dhruv&background=3b82f6&color=fff&rounded=true&size=32", color: "#3b82f6" },
            { id: 2, name: "Nikhil", lane: 1, avatar: "https://ui-avatars.com/api/?name=Nikhil&background=ef4444&color=fff&rounded=true&size=32", color: "#ef4444" },
            { id: 3, name: "Aditya", lane: 2, avatar: "https://ui-avatars.com/api/?name=Aditya&background=10b981&color=fff&rounded=true&size=32", color: "#10b981" },
            { id: 4, name: "Priya", lane: 3, avatar: "https://ui-avatars.com/api/?name=Priya&background=f59e0b&color=fff&rounded=true&size=32", color: "#f59e0b" },
            { id: 5, name: "Priti", lane: 4, avatar: "https://ui-avatars.com/api/?name=Priti&background=8b5cf6&color=fff&rounded=true&size=32", color: "#8b5cf6" },
            { id: 6, name: "Neha", lane: 5, avatar: "https://ui-avatars.com/api/?name=Neha&background=ec4899&color=fff&rounded=true&size=32", color: "#ec4899" }
        ];

        // We define tasks with properties generic enough for Day/Week/Year scaling
        const teamTasks = [
            // task properties: startHour (0-24), durationMin, startDay (0-6), durationDays, startMonth (0-11), durationMonths
            { id: 101, title: "Product Launch", type: "purple", lane: 0, startHour: 9, durationMin: 180, startDay: 1, durationDays: 3, startMonth: 9, durationMonths: 1, assignees: [1, 2, 3] },
            { id: 102, title: "Client Demo", type: "yellow", lane: 1, startHour: 13, durationMin: 120, startDay: 2, durationDays: 1, startMonth: 9, durationMonths: 1, assignees: [4] },
            { id: 103, title: "App Redesign", type: "blue", lane: 2, startHour: 10, durationMin: 240, startDay: 0, durationDays: 5, startMonth: 8, durationMonths: 3, assignees: [1, 5, 6] },
            { id: 104, title: "Annual Review", type: "gray", lane: 3, startHour: 15, durationMin: 60, startDay: 4, durationDays: 1, startMonth: 11, durationMonths: 1, assignees: [2, 4] },
            { id: 105, title: "Q4 Planning", type: "purple", lane: 4, startHour: 14, durationMin: 120, startDay: 3, durationDays: 2, startMonth: 9, durationMonths: 2, assignees: [1, 3, 4, 6] },
            { id: 106, title: "Leave", type: "gray", lane: 5, startHour: 9, durationMin: 480, startDay: 4, durationDays: 2, startMonth: 10, durationMonths: 0.5, assignees: [2] },
        ];

        const colors = {
            purple: { bg: '#f4f4f4', border: '#e5e5e5', text: '#000', badgeBg: '#000', badgeText: '#fff', accent: '#a855f7' },
            yellow: { bg: '#f4f4f4', border: '#e5e5e5', text: '#000', badgeBg: '#000', badgeText: '#fff', accent: '#f59e0b' },
            gray: { bg: '#f4f4f4', border: '#e5e5e5', text: '#000', badgeBg: '#000', badgeText: '#fff', accent: '#64748b' },
            blue: { bg: '#f4f4f4', border: '#e5e5e5', text: '#000', badgeBg: '#000', badgeText: '#fff', accent: '#3b82f6' }
        };

        function formatHour(hour) {
            const h = hour % 12 || 12;
            const ampm = (hour < 12 || hour === 24) ? 'AM' : 'PM';
            return `${h < 10 ? '0' + h : h}:00 ${ampm}`;
        }

        const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        function renderTeamTimeline() {
            teamTimelineHeaders.innerHTML = '';
            teamTimelineGrid.innerHTML = '';
            teamTimelineEvents.innerHTML = '';
            teamCurrentTimeIndicator.style.display = 'none';

            let mode = zoomLevels[currentZoomIndex];

            // Re-adjust container width based on mode to simulate zooming
            if (mode === 'day') {
                document.querySelector('#teamScheduleScrollWrapper .schedule-timeline-container').style.minWidth = '2160px';
            } else if (mode === 'week') {
                document.querySelector('#teamScheduleScrollWrapper .schedule-timeline-container').style.minWidth = '1200px';
            } else if (mode === 'year') {
                document.querySelector('#teamScheduleScrollWrapper .schedule-timeline-container').style.minWidth = '1400px';
            }

            let segments = 24;
            if (mode === 'week') segments = 7;
            if (mode === 'year') segments = 12;

            // Render Headers & Grid
            for (let i = 0; i < segments; i++) {
                const percent = (i / segments) * 100;

                const headerSpan = document.createElement('span');
                headerSpan.style.position = 'absolute';
                headerSpan.style.left = `calc(${percent}% + 10px)`;
                headerSpan.style.whiteSpace = 'nowrap';
                headerSpan.style.color = '#aaa';
                headerSpan.style.fontSize = '0.8rem';
                headerSpan.style.fontWeight = '500';

                if (mode === 'day') {
                    headerSpan.textContent = formatHour(i);
                    headerSpan.style.left = `calc(${percent}% - 22px)`;
                } else if (mode === 'week') {
                    headerSpan.textContent = days[i];
                } else if (mode === 'year') {
                    headerSpan.textContent = months[i];
                }

                teamTimelineHeaders.appendChild(headerSpan);

                const gridLine = document.createElement('div');
                gridLine.style.position = 'absolute';
                gridLine.style.left = `${percent}%`;
                gridLine.style.top = '0';
                gridLine.style.bottom = '0';
                gridLine.style.width = '1px';
                gridLine.style.background = '#f0f0f0';
                teamTimelineGrid.appendChild(gridLine);
            }
            if (mode === 'day') {
                const headerSpan = document.createElement('span');
                headerSpan.style.position = 'absolute';
                headerSpan.style.left = `calc(100% - 22px)`;
                headerSpan.style.whiteSpace = 'nowrap';
                headerSpan.style.color = '#aaa';
                headerSpan.style.fontSize = '0.8rem';
                headerSpan.style.fontWeight = '500';
                headerSpan.textContent = formatHour(24);
                teamTimelineHeaders.appendChild(headerSpan);
                const gridLine = document.createElement('div');
                gridLine.style.position = 'absolute';
                gridLine.style.left = `100%`;
                gridLine.style.top = '0';
                gridLine.style.bottom = '0';
                gridLine.style.width = '1px';
                gridLine.style.background = '#f0f0f0';
                teamTimelineGrid.appendChild(gridLine);
            }

            // Render Events
            // Add padding so events dont overlap avatars
            const eventLeftPadding = 0; // px since hierarchy is in separate fixed 30% area
            const containerWidth = document.querySelector('#teamScheduleScrollWrapper .schedule-timeline-container').clientWidth;
            const usableWidth = containerWidth - eventLeftPadding;

            teamTasks.forEach(task => {
                const theme = colors[task.type] || colors.gray;
                const topVal = task.lane * 3.5 + 2.3;

                let percentLeft = 0;
                let percentWidth = 0;
                let displayLabel = task.title;
                let timeBadgeHTML = '';

                if (mode === 'day') {
                    percentLeft = (task.startHour / 24) * 100;
                    percentWidth = ((task.durationMin / 60) / 24) * 100;

                    const h1 = task.startHour % 12 || 12;
                    const ampm1 = task.startHour < 12 || task.startHour === 24 ? 'AM' : 'PM';
                    const startTimeStr = `${h1 < 10 ? '0' + h1 : h1}:00 ${ampm1}`;

                    timeBadgeHTML = `<span style="background: ${theme.badgeBg}; color: ${theme.badgeText}; padding: 0.2rem 0.4rem; border-radius: 0.25rem; flex-shrink: 0;">${startTimeStr}</span>`;
                } else if (mode === 'week') {
                    percentLeft = (task.startDay / 7) * 100;
                    percentWidth = (task.durationDays / 7) * 100;
                } else if (mode === 'year') {
                    percentLeft = (task.startMonth / 12) * 100;
                    percentWidth = (task.durationMonths / 12) * 100;
                }

                // convert percent back to pixel to apply padding
                let leftPx = eventLeftPadding + (usableWidth * (percentLeft / 100));
                let widthPx = usableWidth * (percentWidth / 100);

                const eventCard = document.createElement('div');
                eventCard.style.position = 'absolute';
                eventCard.style.left = `${leftPx}px`;
                eventCard.style.width = `calc(${widthPx}px - 10px)`;
                eventCard.style.minWidth = 'max-content';
                eventCard.style.paddingRight = '1rem';
                eventCard.style.top = `${topVal}rem`;
                eventCard.style.background = theme.bg;
                eventCard.style.boxShadow = `0 2px 5px rgba(0,0,0,0.05), inset 0 0 0 1px ${theme.border}`;
                eventCard.style.padding = '0.4rem 0.5rem';
                eventCard.style.borderRadius = '0.5rem';
                eventCard.style.fontSize = '0.75rem';
                eventCard.style.fontWeight = '600';
                eventCard.style.display = 'flex';
                eventCard.style.alignItems = 'center';
                eventCard.style.gap = '0.5rem';
                eventCard.style.overflow = 'hidden';
                eventCard.style.whiteSpace = 'nowrap';
                eventCard.style.color = theme.text;
                eventCard.title = displayLabel;

                let assigneeDots = '';
                if (task.assignees) {
                    assigneeDots = '<div style="display:flex; gap:2px; margin-left: 0.5rem;">';
                    task.assignees.forEach(memberId => {
                        const member = teamMembers.find(m => m.id === memberId);
                        if (member) {
                            assigneeDots += `<div style="width: 10px; height: 10px; border-radius: 50%; background-color: ${member.color}; border: 1px solid #fff;" title="${member.name}"></div>`;
                        }
                    });
                    assigneeDots += '</div>';
                }

                eventCard.innerHTML = `
                    ${timeBadgeHTML}
                    <div style="width: 10px; height: 10px; border-radius: 50%; background-color: ${theme.accent}; flex-shrink: 0;" title="Task Category / Manager"></div>
                    <span style="overflow: hidden; text-overflow: ellipsis; flex-grow: 1;">${displayLabel}</span>
                    ${assigneeDots}
                `;

                teamTimelineEvents.appendChild(eventCard);
            });

            if (mode === 'day') {
                const now = new Date();
                const h = now.getHours();
                const m = now.getMinutes();
                const percent = ((h * 60 + m) / (24 * 60)) * 100;
                teamCurrentTimeIndicator.style.display = 'block';
                teamCurrentTimeIndicator.style.left = `${percent}%`;
            }
        }

        const legendContainer = document.getElementById('teamScheduleLegendContainer');
        if (legendContainer) {
            legendContainer.innerHTML = '';
            const legendFlex = document.createElement('div');
            legendFlex.style.display = 'flex';
            legendFlex.style.gap = '1rem';
            legendFlex.style.alignItems = 'center';
            legendFlex.style.justifyContent = 'center';
            legendFlex.style.flexWrap = 'wrap';

            teamMembers.forEach(member => {
                const item = document.createElement('div');
                item.style.display = 'flex';
                item.style.alignItems = 'center';
                item.style.gap = '0.5rem';
                item.innerHTML = `<div style="width: 12px; height: 12px; border-radius: 50%; background-color: ${member.color};"></div><span style="font-size: 0.8rem; color: #555; font-weight: 500;">${member.name}</span>`;
                legendFlex.appendChild(item);
            });
            legendContainer.appendChild(legendFlex);
        }

        renderTeamTimeline();

        teamZoomOutBtn.addEventListener('click', () => {
            if (currentZoomIndex < zoomLevels.length - 1) {
                currentZoomIndex++;
                teamZoomLevelText.textContent = zoomLevels[currentZoomIndex].charAt(0).toUpperCase() + zoomLevels[currentZoomIndex].slice(1);
                renderTeamTimeline();
            }
        });

        teamZoomInBtn.addEventListener('click', () => {
            if (currentZoomIndex > 0) {
                currentZoomIndex--;
                teamZoomLevelText.textContent = zoomLevels[currentZoomIndex].charAt(0).toUpperCase() + zoomLevels[currentZoomIndex].slice(1);
                renderTeamTimeline();
            }
        });

        if (teamScrollLeft) {
            teamScrollLeft.addEventListener('click', () => {
                teamScheduleScrollWrapper.scrollBy({ left: -300, behavior: 'smooth' });
            });
        }
        if (teamScrollRight) {
            teamScrollRight.addEventListener('click', () => {
                teamScheduleScrollWrapper.scrollBy({ left: 300, behavior: 'smooth' });
            });
        }
    }

    // =====================================================
    // EDIT ASSIGNED TASK — Modal Logic moved to component
    // See: components/modals/edit-task-modal.js
    // =====================================================
    // TASK SECTION TOGGLE LOGIC
    // =====================================================
    const dailyTasksToggle = document.getElementById('dailyTasksToggle');
    const managerTasksToggle = document.getElementById('managerTasksToggle');
    const myDailyTasksSection = document.getElementById('myDailyTasksSection');
    const managerAssignedTasksSection = document.getElementById('managerAssignedTasksSection');

    if (dailyTasksToggle && managerTasksToggle && myDailyTasksSection && managerAssignedTasksSection) {
        function updateTaskSectionVisibility() {
            if (dailyTasksToggle.checked) {
                myDailyTasksSection.style.display = '';
                managerAssignedTasksSection.style.display = 'none';
            } else {
                myDailyTasksSection.style.display = 'none';
                managerAssignedTasksSection.style.display = '';
            }
        }

        dailyTasksToggle.addEventListener('change', updateTaskSectionVisibility);
        managerTasksToggle.addEventListener('change', updateTaskSectionVisibility);
    }


    window.initPunchModalEvents = function () {
        // Punch In Modal Interactions
        const punchInModal = document.getElementById('punchInModal');
        const closePunchInModal = document.getElementById('closePunchInModal');
        const actualClosePunchInBtn = document.getElementById('actualClosePunchInBtn');
        const grantAccessBtn = document.getElementById('grantAccessBtn');
        const clickPicBtn = document.getElementById('clickPicBtn');

        function closePunchIn() {
            if (punchInModal) {
                punchInModal.classList.remove('visible');
                punchInModal.classList.remove('open');
                const video = document.getElementById('cameraPreview');
                if (video && video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                    video.srcObject = null;
                }
            }
        }

        if (closePunchInModal) closePunchInModal.addEventListener('click', closePunchIn);
        if (actualClosePunchInBtn) actualClosePunchInBtn.addEventListener('click', closePunchIn);

        // Also close on overlay click
        if (punchInModal) {
            punchInModal.addEventListener('click', (e) => {
                if (e.target === punchInModal) closePunchIn();
            });
        }

        if (grantAccessBtn) {
            grantAccessBtn.addEventListener('click', requestPunchInAccess);
        }

        let currentFacingMode = 'user';
        const switchCameraBtn = document.getElementById('switchCameraBtn');
        if (switchCameraBtn) {
            switchCameraBtn.addEventListener('click', async () => {
                const video = document.getElementById('cameraPreview');
                if (video && video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                }
                currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: currentFacingMode } });
                    if (video) video.srcObject = stream;
                } catch (err) {
                    console.error('Camera switch failed', err);
                }
            });
        }


        window.checkPunchInValidity = function () {
            const clickPicBtn = document.getElementById('clickPicBtn');
            const takePhotoBtn = document.getElementById('takePhotoBtnClick');
            const punchInReason = document.getElementById('punchInReason');
            const wordCountInReason = document.getElementById('wordCountInReason');
            if (!clickPicBtn) return;

            const rDiv = document.getElementById('outOfRangeReasonInDiv');
            let needsReason = rDiv && rDiv.style.display !== 'none';

            let reasonValid = true;
            if (needsReason && punchInReason) {
                let text = punchInReason.value.trim();
                let words = text.split(/\s+/).filter(word => word.match(/[a-zA-Z0-9]/));
                let count = text === '' ? 0 : words.length;
                if (wordCountInReason) wordCountInReason.textContent = count;
                if (count < 10) reasonValid = false;
            }

            let photoTaken = takePhotoBtn && takePhotoBtn.style.display === 'none';

            if (photoTaken && reasonValid) {
                clickPicBtn.disabled = false;
                clickPicBtn.classList.add('active');
                clickPicBtn.textContent = 'PUNCH IN';
            } else {
                clickPicBtn.disabled = true;
                clickPicBtn.classList.remove('active');
                if (!photoTaken) {
                    clickPicBtn.textContent = 'PLEASE TAKE A PHOTO';
                } else if (!reasonValid) {
                    clickPicBtn.textContent = 'PROVIDE REASON';
                }
            }
        };

        document.addEventListener('input', function (e) {
            if (e.target.id === 'punchInReason') window.checkPunchInValidity();
        });

        const takePhotoBtn = document.getElementById('takePhotoBtnClick');

        if (takePhotoBtn) {
            takePhotoBtn.addEventListener('click', () => {
                const video = document.getElementById('cameraPreview');
                const canvas = document.getElementById('cameraCanvas');
                const submitBtn = document.getElementById('clickPicBtn');

                if (video && canvas) {
                    const context = canvas.getContext('2d');
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;

                    // Front camera stream can appear mirrored on some devices.
                    // Normalize saved image orientation only for user-facing camera.
                    if (currentFacingMode === 'user') {
                        context.translate(canvas.width, 0);
                        context.scale(-1, 1);
                    }
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    if (currentFacingMode === 'user') {
                        context.setTransform(1, 0, 0, 1, 0, 0);
                    }

                    video.style.display = 'none';
                    canvas.style.display = 'block';

                    takePhotoBtn.style.display = 'none';
                    if (switchCameraBtn) switchCameraBtn.style.display = 'none';

                    if (submitBtn) {
                        if (window.checkPunchInValidity) window.checkPunchInValidity();
                    }
                }
            });
        }

        if (clickPicBtn) {
            clickPicBtn.addEventListener('click', async () => {
                clickPicBtn.disabled = true;
                clickPicBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Punching in...';

                // Grab photo from canvas
                const canvas = document.getElementById('cameraCanvas');
                const photoData = canvas ? canvas.toDataURL('image/jpeg', 0.85) : null;

                // Grab out-of-geofence reason if visible
                const rDiv = document.getElementById('outOfRangeReasonInDiv');
                const reasonTA = document.getElementById('punchInReason');
                const outOfGeofenceReason = (rDiv && rDiv.style.display !== 'none' && reasonTA) ? reasonTA.value.trim() : null;

                const payload = {
                    action: 'punch_in',
                    latitude: _punchInLat,
                    longitude: _punchInLon,
                    accuracy: _punchInAcc,
                    address: _punchInAddr,
                    punch_in_photo: photoData,
                    geofence_id: _punchInGeofenceId,
                    within_geofence: _punchInWithinGeofence,
                    distance_from_geofence: _punchInDistance
                };
                if (outOfGeofenceReason) payload.out_of_geofence_reason = outOfGeofenceReason;

                try {
                    const res = await fetch('../punch.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();

                    if (data.success) {
                        executePunchIn();
                        closePunchIn();
                        showDailyTasksPopup();
                        // Show pending tasks/approvals modal in blocking mode after punch-in
                        if (typeof window.showUpcomingDeadlinesAfterPunchIn === 'function') {
                            window.showUpcomingDeadlinesAfterPunchIn();
                        }
                    } else {
                        // Show error inline in modal
                        clickPicBtn.disabled = false;
                        clickPicBtn.innerHTML = '<i class="fa-solid fa-check-circle"></i> PUNCH IN';
                        const geoStatus = document.getElementById('geoStatusBanner');
                        if (geoStatus) {
                            geoStatus.style.display = 'flex';
                            geoStatus.style.background = '#fef2f2';
                            const icon = document.getElementById('geoStatusIcon');
                            if (icon) { icon.className = 'fa-solid fa-circle-xmark'; icon.style.color = '#ef4444'; }
                            const txt = document.getElementById('geoStatusText');
                            if (txt) { txt.textContent = data.message || 'Punch in failed. Please try again.'; txt.style.color = '#991b1b'; }
                        }
                    }
                } catch (err) {
                    clickPicBtn.disabled = false;
                    clickPicBtn.innerHTML = '<i class="fa-solid fa-check-circle"></i> PUNCH IN';
                    console.error('Punch in error:', err);
                    alert('Network error. Please check your connection and try again.');
                }
            });
        }

        const punchOutModal = document.getElementById('punchOutModal');
        const closePunchOutModal = document.getElementById('closePunchOutModal');
        const grantAccessBtnOut = document.getElementById('grantAccessBtnOut');
        const switchCameraBtnOut = document.getElementById('switchCameraBtnOut');

        function closePunchOut() {
            if (punchOutModal) {
                punchOutModal.classList.remove('visible');
                punchOutModal.classList.remove('open');
                const video = document.getElementById('cameraPreviewOut');
                if (video && video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                    video.srcObject = null;
                }
            }
        }

        if (closePunchOutModal) closePunchOutModal.addEventListener('click', closePunchOut);
        if (punchOutModal) punchOutModal.addEventListener('click', (e) => { if (e.target === punchOutModal) closePunchOut(); });
        if (grantAccessBtnOut) grantAccessBtnOut.addEventListener('click', requestPunchOutAccess);

        let currentFacingModeOut = window._punchOutFacingMode || 'user';
        if (switchCameraBtnOut) {
            switchCameraBtnOut.addEventListener('click', async () => {
                const video = document.getElementById('cameraPreviewOut');
                const canvas = document.getElementById('cameraCanvasOut');
                if (canvas) canvas.style.display = 'none';
                if (video) video.style.display = 'none';

                if (video && video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                    video.srcObject = null;
                }

                currentFacingModeOut = currentFacingModeOut === 'user' ? 'environment' : 'user';
                window._punchOutFacingMode = currentFacingModeOut;
                await requestPunchOutAccess();
            });
        }

        const retakePicBtnOut = document.getElementById('retakePicBtnOut');
        if (retakePicBtnOut) {
            retakePicBtnOut.addEventListener('click', () => {
                const canvas = document.getElementById('cameraCanvasOut');
                if (canvas) canvas.style.display = 'none';
                requestPunchOutAccess();
            });
        }

        const capturePicBtnOut = document.getElementById('capturePicBtnOut');
        if (capturePicBtnOut) {
            capturePicBtnOut.addEventListener('click', () => {
                const video = document.getElementById('cameraPreviewOut');
                const canvas = document.getElementById('cameraCanvasOut');
                const retakeWrapperOut = document.getElementById('retakeWrapperOut');
                const statusText = document.getElementById('punchOutStatus');

                if (video && canvas) {
                    const context = canvas.getContext('2d');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;

                    // Front camera stream can appear mirrored on some devices.
                    // Normalize saved image orientation only for user-facing camera.
                    if (currentFacingModeOut === 'user') {
                        context.translate(canvas.width, 0);
                        context.scale(-1, 1);
                    }
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    if (currentFacingModeOut === 'user') {
                        context.setTransform(1, 0, 0, 1, 0, 0);
                    }

                    video.style.display = 'none';
                    canvas.style.display = 'block';

                    capturePicBtnOut.style.display = 'none';
                    const switchBtnOut = document.getElementById('switchCameraBtnOut');
                    if (switchBtnOut) switchBtnOut.style.display = 'none';
                    if (retakeWrapperOut) retakeWrapperOut.style.display = 'flex';

                    if (statusText) {
                        statusText.style.color = '#16a34a';
                        statusText.textContent = 'Picture captured! Ready to punch out.';
                        statusText.style.display = 'block';
                    }
                    checkPunchOutValidity();
                }
            });
        }

        const punchOutSummary = document.getElementById('punchOutSummary');
        const submitPunchOutBtn = document.getElementById('submitPunchOutBtn');
        const wordCountSpan = document.getElementById('wordCount');
        const workReportProjectMenu = document.getElementById('workReportProjectMenu');

        let wrProjectItems = [];
        let wrActiveIdx = -1;

        function hideWorkReportProjectMenu() {
            if (!workReportProjectMenu) return;
            workReportProjectMenu.style.display = 'none';
            workReportProjectMenu.innerHTML = '';
            wrProjectItems = [];
            wrActiveIdx = -1;
        }

        function parseCurrentHashToken() {
            if (!punchOutSummary) return null;
            const text = punchOutSummary.value || '';
            const caret = punchOutSummary.selectionStart || 0;
            const before = text.slice(0, caret);
            const hashIndex = before.lastIndexOf('#');
            if (hashIndex === -1) return null;

            const prevChar = hashIndex > 0 ? before[hashIndex - 1] : ' ';
            if (!/\s/.test(prevChar) && hashIndex !== 0) return null;

            const token = before.slice(hashIndex + 1);
            if (/\s/.test(token)) return null;

            return {
                query: token,
                hashIndex,
                caret
            };
        }

        async function fetchWorkReportProjects(query) {
            try {
                const res = await fetch(`api/search_project_hashtags.php?q=${encodeURIComponent(query || '')}`);
                const data = await res.json();
                if (!data || !data.success || !Array.isArray(data.projects)) return [];
                return data.projects;
            } catch (e) {
                return [];
            }
        }

        function renderWorkReportProjectMenu(items) {
            if (!workReportProjectMenu) return;
            workReportProjectMenu.innerHTML = '';

            if (!items.length) {
                hideWorkReportProjectMenu();
                return;
            }

            items.forEach((p, idx) => {
                const row = document.createElement('div');
                row.className = 'wr-hashtag-item';
                row.dataset.idx = String(idx);
                row.innerHTML = `<span class="wr-hashtag-hash">#</span><span>${p.title || ''}</span>`;
                row.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    applyProjectHashtag(p.title || '');
                });
                workReportProjectMenu.appendChild(row);
            });

            workReportProjectMenu.style.display = 'block';
            wrActiveIdx = -1;
        }

        function applyProjectHashtag(projectTitle) {
            if (!punchOutSummary || !projectTitle) return;
            const info = parseCurrentHashToken();
            if (!info) return;

            const text = punchOutSummary.value || '';
            const beforeHash = text.slice(0, info.hashIndex);
            const afterCaret = text.slice(info.caret);
            const inserted = `#${projectTitle}`;
            const needsSpaceAfter = afterCaret.length > 0 && !/^\s/.test(afterCaret);
            const nextText = beforeHash + inserted + (needsSpaceAfter ? ' ' : '') + afterCaret;

            punchOutSummary.value = nextText;
            const newCaret = (beforeHash + inserted + (needsSpaceAfter ? ' ' : '')).length;
            punchOutSummary.focus();
            punchOutSummary.setSelectionRange(newCaret, newCaret);

            hideWorkReportProjectMenu();
            checkPunchOutValidity();
        }

        async function maybeShowWorkReportProjects() {
            if (!punchOutSummary || !workReportProjectMenu) return;
            const info = parseCurrentHashToken();
            if (!info) {
                hideWorkReportProjectMenu();
                return;
            }

            const list = await fetchWorkReportProjects(info.query || '');
            wrProjectItems = list;
            renderWorkReportProjectMenu(wrProjectItems);
        }

        function countAlnumOnlyWords(text) {
            const tokens = String(text || '').trim().split(/\s+/).filter(Boolean);
            // Count only plain alphanumeric words. Emojis/special-char tokens are ignored.
            return tokens.filter(token => /^[A-Za-z0-9]+$/.test(token)).length;
        }

        function checkPunchOutValidity() {
            if (!punchOutSummary || !submitPunchOutBtn) return;

            let minWords = 20;
            let text = punchOutSummary.value.trim();
            let count = text === '' ? 0 : countAlnumOnlyWords(text);

            if (wordCountSpan) wordCountSpan.textContent = count;

            let reportValid = (count >= minWords);

            const rDivOut = document.getElementById('outOfRangeReasonOutDiv');
            const punchOutReason = document.getElementById('punchOutReason');
            const wordCountOutReason = document.getElementById('wordCountOutReason');

            let needsReason = rDivOut && rDivOut.style.display !== 'none';
            let reasonValid = true;
            if (needsReason && punchOutReason) {
                let reasonText = punchOutReason.value.trim();
                let reasonWords = reasonText.split(/\s+/).filter(w => w.match(/[a-zA-Z0-9]/));
                let reasonCount = reasonText === '' ? 0 : reasonWords.length;
                if (wordCountOutReason) wordCountOutReason.textContent = reasonCount;
                if (reasonCount < 10) reasonValid = false;
            }

            const retakeWrapperOut = document.getElementById('retakeWrapperOut');
            let photoTaken = retakeWrapperOut && retakeWrapperOut.style.display !== 'none';

            if (reportValid && reasonValid && photoTaken) {
                submitPunchOutBtn.disabled = false;
            } else {
                submitPunchOutBtn.disabled = true;
            }
        }

        document.addEventListener('input', function (e) {
            if (e.target.id === 'punchOutReason') checkPunchOutValidity();
        });

        if (punchOutSummary) {
            // Prevent copy/paste style shortcuts in work summary
            ['paste', 'copy', 'cut', 'drop'].forEach(evt => {
                punchOutSummary.addEventListener(evt, (e) => {
                    e.preventDefault();
                });
            });

            punchOutSummary.addEventListener('keydown', (e) => {
                const isMacCmd = e.metaKey;
                const isCtrl = e.ctrlKey;
                const k = String(e.key || '').toLowerCase();
                if ((isMacCmd || isCtrl) && (k === 'v' || k === 'c' || k === 'x' || k === 'insert')) {
                    e.preventDefault();
                }
            });

            punchOutSummary.addEventListener('input', () => {
                checkPunchOutValidity();
                maybeShowWorkReportProjects();
            });

            punchOutSummary.addEventListener('click', () => {
                maybeShowWorkReportProjects();
            });

            punchOutSummary.addEventListener('keydown', (e) => {
                if (!workReportProjectMenu || workReportProjectMenu.style.display !== 'block') return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!wrProjectItems.length) return;
                    wrActiveIdx = (wrActiveIdx + 1) % wrProjectItems.length;
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!wrProjectItems.length) return;
                    wrActiveIdx = (wrActiveIdx - 1 + wrProjectItems.length) % wrProjectItems.length;
                } else if (e.key === 'Enter') {
                    if (wrActiveIdx >= 0 && wrProjectItems[wrActiveIdx]) {
                        e.preventDefault();
                        applyProjectHashtag(wrProjectItems[wrActiveIdx].title || '');
                    }
                    return;
                } else if (e.key === 'Escape') {
                    hideWorkReportProjectMenu();
                    return;
                } else {
                    return;
                }

                const rows = workReportProjectMenu.querySelectorAll('.wr-hashtag-item');
                rows.forEach((row, i) => {
                    if (i === wrActiveIdx) row.classList.add('active');
                    else row.classList.remove('active');
                });
            });

            punchOutSummary.addEventListener('blur', () => {
                setTimeout(() => hideWorkReportProjectMenu(), 120);
            });
        }

        document.addEventListener('click', (e) => {
            if (!workReportProjectMenu || !punchOutSummary) return;
            if (e.target === punchOutSummary) return;
            if (workReportProjectMenu.contains(e.target)) return;
            hideWorkReportProjectMenu();
        });

        if (submitPunchOutBtn) {
            submitPunchOutBtn.addEventListener('click', async () => {
                // Check overtime eligibility upon clicking submit
                let isOvertimeEligible = false;
                let otStr = "";
                if (_shiftEndTime) {
                    const now = new Date();
                    const endOfShift = new Date(now);
                    const timeParts = _shiftEndTime.split(':');
                    endOfShift.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]), parseInt(timeParts[2] || 0), 0);
                    let diffSeconds = Math.floor((now - endOfShift) / 1000);
                    if (diffSeconds >= 5400) { // 1 hour 30 minutes
                        isOvertimeEligible = true;
                        let h = Math.floor(diffSeconds / 3600);
                        let m = Math.floor((diffSeconds % 3600) / 60);
                        otStr = `${h} hr ${m} min`;
                    }
                }

                if (isOvertimeEligible) {
                    // Show OT Modal first
                    const otModal = document.getElementById('overtimePromptModal');
                    if (otModal) {
                        otModal.style.display = 'flex';
                        otModal.classList.add('visible', 'open');

                        const durText = document.getElementById('otDurationText');
                        if (durText) durText.textContent = otStr;

                        // Reset UI
                        document.getElementById('otPromptSection').style.display = 'block';
                        document.getElementById('otReportSection').style.display = 'none';
                        document.getElementById('otFooterSubmit').style.display = 'none';
                        const otTA = document.getElementById('punchOutOvertimeReason');
                        if (otTA) otTA.value = '';
                        document.getElementById('wordCountOT').textContent = '0';
                        document.getElementById('submitOtAndPunchOutBtn').disabled = true;
                    }
                    return; // Stop here and wait for OT Modal flow
                } else {
                    // No OT -> Normal submit
                    processPunchOutSubmission();
                }
            });
        }

        // ── OVERTIME MODAL LOGIC ───────────────────────────────────────
        const closeOtModalBtn = document.getElementById('closeOvertimePromptModal');
        if (closeOtModalBtn) {
            closeOtModalBtn.addEventListener('click', () => {
                const otModal = document.getElementById('overtimePromptModal');
                if (otModal) otModal.classList.remove('visible', 'open');
            });
        }

        const otSkipBtn = document.getElementById('otSkipBtn');
        if (otSkipBtn) {
            otSkipBtn.addEventListener('click', () => {
                const otModal = document.getElementById('overtimePromptModal');
                if (otModal) otModal.classList.remove('visible', 'open');
                // User skipped OT submission -> submit punch out normally
                processPunchOutSubmission();
            });
        }

        const otProceedBtn = document.getElementById('otProceedBtn');
        if (otProceedBtn) {
            otProceedBtn.addEventListener('click', async () => {
                document.getElementById('otPromptSection').style.display = 'none';
                document.getElementById('otReportSection').style.display = 'block';
                document.getElementById('otFooterSubmit').style.display = 'flex';

                // ── Load managers into dropdown ──────────────────────────────
                const otMgrSelect  = document.getElementById('otManagerSelect');
                const otMgrLoading = document.getElementById('otManagerLoading');
                if (otMgrSelect && otMgrSelect.options.length <= 1) { // Only fetch if not already populated
                    try {
                        if (otMgrLoading) otMgrLoading.style.display = 'block';
                        const res  = await fetch('overtime_page/api_overtime.php');
                        const data = await res.json();
                        if (data.managers && Array.isArray(data.managers)) {
                            otMgrSelect.innerHTML = '<option value="">Select Manager</option>';
                            data.managers.forEach(mgr => {
                                const opt = document.createElement('option');
                                opt.value = mgr.id;
                                opt.textContent = mgr.name;
                                // Pre-select the mapped manager if available
                                if (data.assigned_manager_id && parseInt(mgr.id) === parseInt(data.assigned_manager_id)) {
                                    opt.selected = true;
                                }
                                otMgrSelect.appendChild(opt);
                            });
                        }
                    } catch (e) {
                        console.error('Failed to load managers for OT modal:', e);
                    } finally {
                        if (otMgrLoading) otMgrLoading.style.display = 'none';
                    }
                }
            });
        }

        const otTA = document.getElementById('punchOutOvertimeReason');
        const otSubmit = document.getElementById('submitOtAndPunchOutBtn');
        const otWC = document.getElementById('wordCountOT');

        if (otTA && otSubmit) {
            otTA.addEventListener('input', () => {
                let text = otTA.value.trim();
                let words = text.split(/\s+/).filter(word => word.match(/[a-zA-Z0-9]/));
                let count = text === '' ? 0 : words.length;
                if (otWC) otWC.textContent = count;

                if (count >= 15) {
                    otSubmit.disabled = false;
                } else {
                    otSubmit.disabled = true;
                }
            });

            otSubmit.addEventListener('click', () => {
                const otModal = document.getElementById('overtimePromptModal');
                if (otModal) otModal.classList.remove('visible', 'open');
                const otMgrId = document.getElementById('otManagerSelect')?.value || '';
                processPunchOutSubmission(otTA.value.trim(), otMgrId);
            });
        }

        // Extracted Submission API fetch
        async function processPunchOutSubmission(overtimeReasonText = null, otManagerId = null) {
            const statusText = document.getElementById('punchOutStatus');
            if (submitPunchOutBtn) submitPunchOutBtn.disabled = true;

            if (statusText) {
                statusText.style.color = '#3b82f6';
                statusText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving to database...';
                statusText.style.display = 'flex';
            }

            // Grab punch-out photo from canvas
            const canvasOut = document.getElementById('cameraCanvasOut');
            const photoDataOut = canvasOut ? canvasOut.toDataURL('image/jpeg', 0.85) : null;

            // Grab work summary
            const summaryTA = document.getElementById('punchOutSummary');
            const workReport = summaryTA ? summaryTA.value.trim() : '';

            // Grab out-of-geofence reason if visible
            const rDivOut = document.getElementById('outOfRangeReasonOutDiv');
            const reasonTAOut = document.getElementById('punchOutReason');
            const outOfGeofenceReasonOut = (rDivOut && rDivOut.style.display !== 'none' && reasonTAOut) ? reasonTAOut.value.trim() : null;

            const payload = {
                action: 'punch_out',
                work_report: workReport,
                latitude: _punchOutLat,
                longitude: _punchOutLon,
                accuracy: _punchOutAcc,
                address: _punchOutAddr,
                punch_out_photo: photoDataOut,
                geofence_id: _punchOutGeofenceId,
                within_geofence: _punchOutWithinGeofence,
                distance_from_geofence: _punchOutDistance
            };

            if (outOfGeofenceReasonOut) payload.out_of_geofence_reason = outOfGeofenceReasonOut;
            if (overtimeReasonText) payload.overtime_report = overtimeReasonText;

            try {
                const res = await fetch('../punch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    if (statusText) {
                        statusText.style.color = '#16a34a';
                        statusText.style.background = '#f0fdf4';
                        statusText.style.borderRadius = '8px';
                        statusText.style.padding = '10px 14px';
                        statusText.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#22c55e;"></i>
                        <span>${data.message}</span>`;
                    }

                    // ── Submit OT to api_submit_overtime.php if user filled OT report ──
                    if (overtimeReasonText && data.attendance_id && otManagerId) {
                        try {
                            const otPayload = new FormData();
                            otPayload.append('attendance_id', data.attendance_id);
                            otPayload.append('manager_id',    otManagerId);
                            otPayload.append('report',        overtimeReasonText);
                            const otRes  = await fetch('overtime_page/api_submit_overtime.php', {
                                method: 'POST',
                                body: otPayload
                            });
                            const otData = await otRes.json();
                            if (otData.status === 'success') {
                                console.log('[OT Bot] Overtime submitted successfully via punch-out.');
                            } else {
                                console.warn('[OT Bot] OT submit returned:', otData.message || otData);
                            }
                        } catch (otErr) {
                            console.error('[OT Bot] Failed to submit overtime after punch-out:', otErr);
                        }
                    }

                    setTimeout(() => {
                        executePunchOut();
                        const punchOutModal = document.getElementById('punchOutModal');
                        if (punchOutModal) {
                            punchOutModal.classList.remove('visible', 'open');

                            const video = document.getElementById('cameraPreviewOut');
                            if (video && video.srcObject) {
                                video.srcObject.getTracks().forEach(track => track.stop());
                                video.srcObject = null;
                            }
                        }
                    }, 1200);
                } else {
                    // Show error
                    if (statusText) {
                        statusText.style.color = '#dc2626';
                        statusText.style.background = '#fef2f2';
                        statusText.style.borderRadius = '8px';
                        statusText.style.padding = '10px 14px';
                        statusText.innerHTML = `<i class="fa-solid fa-circle-xmark" style="color:#ef4444;"></i>
                        <span>${data.message || 'Punch out failed. Please try again.'}</span>`;
                    }
                    if (submitPunchOutBtn) submitPunchOutBtn.disabled = false;
                }
            } catch (err) {
                if (statusText) {
                    statusText.style.color = '#dc2626';
                    statusText.textContent = 'Network error. Check your connection.';
                }
                if (submitPunchOutBtn) submitPunchOutBtn.disabled = false;
                console.error('Punch out error:', err);
            }
        }

    };

});
