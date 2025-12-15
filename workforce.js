const state = {
    currentDate: new Date(),
    tasks: [],
    currentSite: 'site1'
};

// Helper to get date string relative to current month
// dayOffset: 1 = 1st day of current month
function getRelDate(dayOffset) {
    const d = new Date();
    d.setDate(dayOffset);
    return formatDate(d);
}

document.addEventListener('DOMContentLoaded', () => {
    init();
});

function init() {
    // Event Listeners
    document.getElementById('prevMonth').addEventListener('click', () => changeMonth(-1));
    document.getElementById('nextMonth').addEventListener('click', () => changeMonth(1));
    document.getElementById('todayBtn').addEventListener('click', () => {
        state.currentDate = new Date();
        render();
    });

    const addTaskBtn = document.getElementById('addTaskBtn');
    if (addTaskBtn) {
        addTaskBtn.addEventListener('click', () => openModal());
    }
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelModal').addEventListener('click', closeModal);

    document.getElementById('taskForm').addEventListener('submit', handleFormSubmit);

    // Word Count Listener
    const descInput = document.getElementById('taskDesc');
    if (descInput) {
        descInput.addEventListener('input', updateWordCount);
    }

    // Close Alert Listener
    document.getElementById('closeAlertBtn').addEventListener('click', closeAlert);

    // Site Selector Listener - Load projects from API
    const siteSelect = document.getElementById('siteSelect');
    if (siteSelect) {
        loadProjectsFromAPI().then(() => {
            // After projects load, render the calendar
            render();
        });
        loadUsersForAssignee(); // Load users for assignee dropdown
        siteSelect.addEventListener('change', (e) => {
            state.currentSite = e.target.value;
            render(); // Re-render with new site data
        });
    } else {
        // Fallback if siteSelect doesn't exist
        render();
    }

    // Window resize listener to re-render calendar for responsive task display
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            renderCalendar(); // Re-render only the calendar, not fetch tasks again
        }, 250); // Debounce for 250ms
    });

    // Initial Load removed - now called after projects load
}

async function loadProjectsFromAPI() {
    try {
        const response = await fetch('site/get_projects.php');
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            const siteSelect = document.getElementById('siteSelect');
            siteSelect.innerHTML = ''; // Clear loading option

            result.data.forEach((project) => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.title;
                siteSelect.appendChild(option);
            });

            // Set first project as default
            if (result.data.length > 0) {
                state.currentSite = result.data[0].id;
                siteSelect.value = state.currentSite;
            }

            // Store projects for later use
            window.projectsData = result.data;
        } else {
            document.getElementById('siteSelect').innerHTML = '<option value="">No construction projects found</option>';
        }
    } catch (error) {
        console.error('Error loading projects:', error);
        document.getElementById('siteSelect').innerHTML = '<option value="">Error loading projects</option>';
    }
}

async function loadUsersForAssignee() {
    try {
        const response = await fetch('site/get_users.php');
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
            const assignToSelect = document.getElementById('taskAssignTo');

            // Keep the default option
            assignToSelect.innerHTML = '<option value="">Select an assignee...</option>';

            result.data.forEach((user) => {
                const option = document.createElement('option');
                option.value = user.username;
                option.setAttribute('data-user-id', user.id);
                option.textContent = user.username;
                assignToSelect.appendChild(option);
            });

            // Store users for later use
            window.usersData = result.data;
        } else {
            console.warn('No users found or error loading users');
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}



function changeMonth(delta) {
    state.currentDate.setMonth(state.currentDate.getMonth() + delta);
    render();
}

async function render() {
    updateHeader();
    await fetchTasks();
    renderCalendar();
}

function updateHeader() {
    const options = { month: 'long', year: 'numeric' };
    document.getElementById('currentMonthYear').textContent = state.currentDate.toLocaleDateString('en-US', options);
}

async function fetchTasks() {
    // Fetch tasks for the selected project/site from database
    if (!state.currentSite) {
        state.tasks = [];
        return;
    }

    try {
        let url = `site/get_tasks.php?project_id=${state.currentSite}`;
        if (window.PERMISSIONS && window.PERMISSIONS.onlyMyTasks) {
            url += '&my_tasks=true';
        }
        const response = await fetch(url);
        const result = await response.json();

        if (result.success && result.data) {
            state.tasks = result.data;
        } else {
            state.tasks = [];
        }
    } catch (error) {
        console.error('Error fetching tasks:', error);
        state.tasks = [];
    }
}

function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    const year = state.currentDate.getFullYear();
    const month = state.currentDate.getMonth();

    // First day of the month
    const firstDay = new Date(year, month, 1);
    // Last day of the month
    const lastDay = new Date(year, month + 1, 0);

    // JS getDay(): 0=Sun, 1=Mon. We want Mon start.
    let startDayIndex = firstDay.getDay() - 1;
    if (startDayIndex === -1) startDayIndex = 6; // Sunday

    const totalDays = lastDay.getDate();

    // Pad previous month
    for (let i = 0; i < startDayIndex; i++) {
        const cell = document.createElement('div');
        cell.className = 'calendar-day empty';
        grid.appendChild(cell);
    }

    // Render Actual Days
    for (let day = 1; day <= totalDays; day++) {
        const cell = document.createElement('div');
        cell.className = 'calendar-day';

        // Check if today
        const todayCheck = new Date();
        if (day === todayCheck.getDate() && month === todayCheck.getMonth() && year === todayCheck.getFullYear()) {
            cell.classList.add('today');
        }

        // Date Number
        const num = document.createElement('div');
        num.className = 'day-number';
        num.textContent = day;
        cell.appendChild(num);

        // Render Tasks for this day
        const currentDayDateStr = formatDate(new Date(year, month, day));
        // Also get today's date string for comparison
        const todayStr = formatDate(new Date());

        const dayTasks = state.tasks.filter(task => {
            return currentDayDateStr >= task.start_date && currentDayDateStr <= task.end_date;
        });

        // Sort tasks: put delayed/late/blocked first for visibility?
        // simple sort by status?
        dayTasks.sort((a, b) => {
            if (a.status === 'blocked') return -1;
            return 0;
        });

        // Add task counter badge if there are multiple tasks
        if (dayTasks.length > 1) {
            const badge = document.createElement('div');
            badge.className = 'task-count-badge';
            badge.textContent = dayTasks.length;
            badge.title = `${dayTasks.length} tasks on this day`;
            cell.appendChild(badge);
        }

        // Determine how many tasks to show based on screen size
        const isMobile = window.innerWidth <= 768;
        const maxVisibleTasks = isMobile ? 2 : dayTasks.length;
        const visibleTasks = dayTasks.slice(0, maxVisibleTasks);
        const hiddenTasksCount = dayTasks.length - maxVisibleTasks;

        // Debug logging (remove in production)
        if (dayTasks.length > 0) {
            console.log(`Day ${day}: ${dayTasks.length} tasks, showing ${visibleTasks.length}, mobile: ${isMobile}, width: ${window.innerWidth}`);
        }

        visibleTasks.forEach(task => {
            const chip = document.createElement('div');

            // Determine if delayed: End date is in past AND status is NOT completed
            let isDelayed = false;
            // Note: simple string comparison works for ISO YYYY-MM-DD
            if (task.status !== 'completed' && task.end_date < todayStr) {
                isDelayed = true;
            }

            chip.className = `task-chip ${task.status} ${isDelayed ? 'delayed' : ''}`;

            // Icon
            const iconSvg = getTaskIcon(task.title);
            chip.innerHTML = `${iconSvg} <span>${task.title}</span>`;

            // Tooltip for quick info
            chip.title = `${task.title}\nStatus: ${task.status}${isDelayed ? ' (DELAYED)' : ''}\n${task.end_date < todayStr ? `Due: ${task.end_date}` : ''}\n${task.description || ''}`;

            chip.addEventListener('click', (e) => {
                e.stopPropagation();
                openModal(null, task); // Edit mode
            });
            cell.appendChild(chip);
        });

        // Add "more tasks" indicator if there are hidden tasks
        if (hiddenTasksCount > 0) {
            const moreIndicator = document.createElement('div');
            moreIndicator.className = 'more-tasks-indicator';
            moreIndicator.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/>
            </svg> <span>+${hiddenTasksCount} more</span>`;
            moreIndicator.title = `Click to view all ${dayTasks.length} tasks`;

            moreIndicator.addEventListener('click', (e) => {
                e.stopPropagation();
                // Show all tasks for this day in a modal or expand inline
                showAllTasksForDay(dayTasks, new Date(year, month, day));
            });
            cell.appendChild(moreIndicator);
        }

        // Click on empty cell to add task starting that day
        cell.addEventListener('click', () => {
            // Check permission (default to true if undefined)
            if (window.PERMISSIONS && window.PERMISSIONS.canAdd === false) {
                return;
            }
            openModal(new Date(year, month, day));
        });

        grid.appendChild(cell);
    }
}

function getTaskIcon(title) {
    const t = title.toLowerCase();
    // Simple SVG icons (14x14)
    if (t.includes('survey') || t.includes('staking')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/></svg>';
    if (t.includes('excavation') || t.includes('digging') || t.includes('shed')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6"/><path d="M3 7V3h4l8 8a2 2 0 0 1 0 2.8l-6.4 6.4a2 2 0 0 1-2.8 0L3 7z"/></svg>';
    if (t.includes('foundation') || t.includes('concrete') || t.includes('cement') || t.includes('slab')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 22h20"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="2" width="12" height="8" rx="2"/></svg>';
    if (t.includes('plumbing') || t.includes('water')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.74 5.88a5.81 5.81 0 0 1-8.21 8.21l-5.88-5.74a5.81 5.81 0 0 1 8.35-8.35z"/><path d="M11 13a6 6 0 0 0-6 6"/></svg>';
    if (t.includes('electrical') || t.includes('wiring') || t.includes('conduit')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
    if (t.includes('painting') || t.includes('glazing')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22v-8"/><path d="M5 2h14"/><path d="M22 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2"/><path d="M14 14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2"/></svg>';
    if (t.includes('delivery') || t.includes('material')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2" ry="2"/><line x1="16" y1="8" x2="20" y2="8"/><line x1="16" y1="16" x2="23" y2="16"/><path d="M16 12h4"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="10.5" cy="18.5" r="2.5"/></svg>';
    if (t.includes('fencing') || t.includes('column') || t.includes('frame')) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 2v20"/><path d="M8 2v20"/><path d="M12 2v20"/><path d="M16 2v20"/><path d="M20 2v20"/><path d="M2 12h20"/><path d="M2 6h20"/><path d="M2 18h20"/></svg>';

    // Default
    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
}

function openModal(date = null, task = null) {
    const modal = document.getElementById('taskModal');
    const form = document.getElementById('taskForm');
    const title = document.getElementById('modalTitle');

    // Reset form & images
    form.reset();
    document.getElementById('taskId').value = '';


    if (task) {
        // Edit Mode
        if (window.PERMISSIONS && window.PERMISSIONS.canAdd === false) {
            title.textContent = 'Task Details';
        } else {
            title.textContent = 'Edit Task';
        }
        modal.setAttribute('data-mode', 'edit');
        document.getElementById('taskId').value = task.id;
        document.getElementById('taskTitle').value = task.title;
        document.getElementById('taskStart').value = task.start_date;
        document.getElementById('taskEnd').value = task.end_date;
        document.getElementById('taskStatus').value = task.status;
        document.getElementById('taskDesc').value = task.description || '';
        document.getElementById('taskDesc').value = task.description || '';
        document.getElementById('taskAssignTo').value = task.assign_to || '';

        // Populate Detail View Elements (if they exist)
        const detailContainer = document.getElementById('taskDetailView');
        if (detailContainer) {
            document.getElementById('detailTitle').textContent = task.title;
            // Format Created At
            const createdDate = new Date(task.created_at);
            const allottedWhen = createdDate.toLocaleDateString() + ' ' + createdDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            document.getElementById('detailAllottedWhen').textContent = allottedWhen;

            // Whom (Assigned by)
            const allottedBy = task.creator_name ? `${task.creator_name} (${task.creator_role || 'Manager'})` : 'Unknown';
            document.getElementById('detailAllottedBy').textContent = allottedBy;

            // Description
            document.getElementById('detailDesc').textContent = task.description || 'No description provided.';
        }

        // Store original status for validation
        document.getElementById('taskForm').setAttribute('data-original-status', task.status);

        // Check permissions for restricted edit
        if (window.PERMISSIONS && window.PERMISSIONS.canAdd === false) {
            // Disable all fields except status AND description (description required for status change)
            document.getElementById('taskTitle').disabled = true;
            document.getElementById('taskStart').disabled = true;
            document.getElementById('taskEnd').disabled = true;
            document.getElementById('taskAssignTo').disabled = true;
            // Ensure status and description are enabled
            document.getElementById('taskDesc').disabled = false;
            document.getElementById('taskStatus').disabled = false;

            // Disable all fields except status AND description (description required for status change)
            // actually we want to HIDE everything and show the new flow
            document.getElementById('taskTitle').closest('.form-group').style.display = 'none';
            document.getElementById('taskStart').closest('.form-group').style.display = 'none';
            document.getElementById('taskEnd').closest('.form-group').style.display = 'none';
            document.getElementById('taskAssignTo').closest('.form-group').style.display = 'none';

            // HIDE the standard Status and Description fields for this new flow
            document.getElementById('taskStatus').closest('.form-group').style.display = 'none';
            document.getElementById('taskDesc').closest('.form-group').style.display = 'none';

            // Hide the default modal footer (Save Task button)
            const defaultFooter = document.querySelector('.modal-footer');
            if (defaultFooter) defaultFooter.style.display = 'none';

            let detailView = document.getElementById('taskDetailView');
            if (!detailView) {
                // Create Detail View if not exists
                const form = document.getElementById('taskForm');
                detailView = document.createElement('div');
                detailView.id = 'taskDetailView';
                detailView.className = 'task-detail-view';
                // Inline styles for minimalistic card look
                detailView.style.padding = '20px';
                detailView.style.marginBottom = '20px';
                detailView.style.backgroundColor = '#f8fafc'; // Very light gray/blue
                detailView.style.borderRadius = '8px';
                detailView.style.border = '1px solid #e2e8f0';

                detailView.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                         <h4 id="detailTitle" style="font-size: 1.25rem; font-weight: 700; color: #1e293b; line-height: 1.4; flex:1;"></h4>
                         <button id="btnTaskTimeline" style="background:none; border:none; color:#64748b; cursor:pointer; padding:4px;" title="View Timeline">
                            <i class="fas fa-history" style="font-size:1.1rem;"></i>
                         </button>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <div style="color: #64748b; margin-top: 2px;"><i class="far fa-calendar-alt"></i></div>
                            <div>
                                <span style="display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Allotted On</span>
                                <span id="detailAllottedWhen" style="font-size: 0.95rem; color: #334155; font-weight: 500;"></span>
                            </div>
                        </div>

                         <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <div style="color: #64748b; margin-top: 2px;"><i class="far fa-user"></i></div>
                            <div>
                                <span style="display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Allotted By</span>
                                <span id="detailAllottedBy" style="font-size: 0.95rem; color: #334155; font-weight: 500;"></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                            <div style="color: #64748b;"><i class="far fa-file-alt"></i></div>
                            <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Description</span>
                        </div>
                        <p id="detailDesc" style="font-size: 0.95rem; color: #334155; line-height: 1.6; background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;"></p>
                    </div>
                `;
                form.insertBefore(detailView, form.firstChild);

                // Create Supervisor Actions Container
                const actionsContainer = document.createElement('div');
                actionsContainer.id = 'supervisorActionsContainer';
                actionsContainer.style.marginTop = '20px';

                // Review Input Area (Initially Hidden)
                const reviewArea = document.createElement('div');
                reviewArea.id = 'supervisorReviewArea';
                reviewArea.style.display = 'none';
                reviewArea.style.marginBottom = '20px';
                reviewArea.innerHTML = `
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:#374151;">Write Review <span id="reviewActionText"></span></label>
                    <textarea id="supervisorReviewText" name="supervisor_notes" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:10px;" placeholder="Write your review here..."></textarea>
                    <div id="supWordCount" style="text-align:right; font-size:0.75rem; color:#6b7280; margin-top:4px;">Words: 0/15</div>
                `;

                // Buttons Area
                const buttonsGrid = document.createElement('div');
                buttonsGrid.id = 'supervisorButtonsGrid';
                buttonsGrid.style.display = 'flex';
                buttonsGrid.style.gap = '15px';
                buttonsGrid.style.borderTop = '1px solid #e5e7eb';
                buttonsGrid.style.paddingTop = '15px';

                buttonsGrid.innerHTML = `
                    <button type="button" id="btnActionNotDone" style="flex:1; padding:12px; background:#fff; border:1px solid #ef4444; color:#ef4444; border-radius:6px; font-weight:600; cursor:pointer; transition:all 0.2s;">
                        <i class="fas fa-times me-2"></i> Not Done
                    </button>
                    <button type="button" id="btnActionDone" style="flex:1; padding:12px; background:#10b981; border:none; color:#fff; border-radius:6px; font-weight:600; cursor:pointer; transition:all 0.2s;">
                        <i class="fas fa-check me-2"></i> Done
                    </button>
                    <button type="button" id="btnSubmitReview" style="display:none; flex:1; padding:12px; background:#3b82f6; border:none; color:#fff; border-radius:6px; font-weight:600; cursor:pointer; transition:all 0.2s;">
                        Submit Review
                    </button>
                `;

                actionsContainer.appendChild(reviewArea);
                actionsContainer.appendChild(buttonsGrid);
                form.appendChild(actionsContainer);

                // --- Event Handlers for New Buttons ---

                // Helper to setup review mode
                const setupReviewMode = (status, actionText) => {
                    // Set hidden standard inputs
                    document.getElementById('taskStatus').value = status;

                    // Show Review Box
                    document.getElementById('supervisorReviewArea').style.display = 'block';
                    document.getElementById('reviewActionText').textContent = actionText;

                    // Hide original Action Buttons
                    document.getElementById('btnActionNotDone').style.display = 'none';
                    document.getElementById('btnActionDone').style.display = 'none';

                    // Show Submit Button
                    const submitBtn = document.getElementById('btnSubmitReview');
                    submitBtn.style.display = 'block';
                    submitBtn.textContent = 'Submit ' + (status === 'completed' ? 'Completion' : 'Report');

                    // Focus textarea
                    document.getElementById('supervisorReviewText').focus();
                };

                document.getElementById('btnActionDone').addEventListener('click', () => {
                    setupReviewMode('completed', '(Completion)');
                });

                document.getElementById('btnActionNotDone').addEventListener('click', () => {
                    setupReviewMode('in_progress', '(Issue/Hold)');
                });

                // Sync Review Text to hidden standard description field
                document.getElementById('supervisorReviewText').addEventListener('input', function () {
                    const val = this.value;
                    // document.getElementById('taskDesc').value = val;

                    // Word Count Logic
                    const wcDisplay = document.getElementById('supWordCount');
                    const words = val.match(/[a-zA-Z0-9]+/g) || [];
                    const count = words.length;
                    wcDisplay.textContent = `Words: ${count}/15`;
                    wcDisplay.style.color = count < 15 ? '#ef4444' : '#10b981';
                });

                document.getElementById('btnSubmitReview').addEventListener('click', () => {
                    // Trigger the standard form submit
                    const fakeEvent = new Event('submit', { cancelable: true });
                    document.getElementById('taskForm').dispatchEvent(fakeEvent);
                });
            }

            // --- Reset State on Open ---
            detailView.style.display = 'block';
            document.getElementById('supervisorActionsContainer').style.display = 'block';
            document.getElementById('supervisorReviewArea').style.display = 'none';
            document.getElementById('btnActionNotDone').style.display = 'block';
            document.getElementById('btnActionDone').style.display = 'block';
            document.getElementById('btnSubmitReview').style.display = 'none';
            document.getElementById('supervisorReviewText').value = '';
            document.getElementById('supWordCount').textContent = 'Words: 0/15';

            // Re-populate Detail View
            if (task) {
                document.getElementById('detailTitle').textContent = task.title;
                const createdDate = new Date(task.created_at);
                const allottedWhen = createdDate.toLocaleDateString() + ' ' + createdDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                document.getElementById('detailAllottedWhen').textContent = allottedWhen;
                const allottedBy = task.creator_name ? `${task.creator_name}` : 'Unknown';
                document.getElementById('detailAllottedBy').textContent = allottedBy;
                document.getElementById('detailDesc').textContent = task.description || 'No description.';

                // Attach Timeline Handler
                const btnTimeline = document.getElementById('btnTaskTimeline');
                if (btnTimeline) {
                    btnTimeline.onclick = (e) => {
                        e.preventDefault();
                        openTaskHistory(task.id);
                    };
                }
            }
            // Ensure standard hidden Status and Description are enabled so they post
            document.getElementById('taskDesc').disabled = false;
            document.getElementById('taskStatus').disabled = false;
            // Clear standard desc so it takes new review
            // document.getElementById('taskDesc').value = '';

        } else {
            // Manager View: Show Form Fields, Hide Detail View & Supervisor Actions
            if (document.getElementById('taskDetailView')) {
                document.getElementById('taskDetailView').style.display = 'none';
            }
            if (document.getElementById('supervisorActionsContainer')) {
                document.getElementById('supervisorActionsContainer').style.display = 'none';
            }
            // Restore Default Footer
            const defaultFooter = document.querySelector('.modal-footer');
            if (defaultFooter) defaultFooter.style.display = 'flex';

            // Show all standard fields
            document.getElementById('taskTitle').closest('.form-group').style.display = 'block';
            document.getElementById('taskStart').closest('.form-group').style.display = 'block';
            document.getElementById('taskEnd').closest('.form-group').style.display = 'block';
            document.getElementById('taskAssignTo').closest('.form-group').style.display = 'block';
            document.getElementById('taskDesc').closest('.form-group').style.display = 'block';
            document.getElementById('taskStatus').closest('.form-group').style.display = 'block';

            document.querySelector("label[for='taskDesc']").textContent = "Work Description";

            // Enable all fields (reset)
            document.getElementById('taskTitle').disabled = false;
            document.getElementById('taskStart').disabled = false;
            document.getElementById('taskEnd').disabled = false;
            document.getElementById('taskAssignTo').disabled = false;
            document.getElementById('taskDesc').disabled = false;
            document.getElementById('taskStatus').disabled = false;

            // Hide word count for managers (optional, but requested context implies it's for the mandatory part)
            const wc = document.getElementById('descriptionWordCount');
            if (wc) wc.style.display = 'block';
        }

        // Initialize word count
        updateWordCount();


    } else {
        // Add Mode
        title.textContent = 'Add New Task';
        modal.setAttribute('data-mode', 'add');

        // Ensure standard fields visible for Add Mode (Managers only usually)
        if (document.getElementById('taskDetailView')) document.getElementById('taskDetailView').style.display = 'none';
        if (document.getElementById('supervisorActionsContainer')) document.getElementById('supervisorActionsContainer').style.display = 'none';

        const defaultFooter = document.querySelector('.modal-footer');
        if (defaultFooter) defaultFooter.style.display = 'flex';

        // Show fields (in case they were hidden by Supervisor view previously)
        document.querySelectorAll('.form-group').forEach(el => el.style.display = 'block');


        if (date) {
            const dateStr = formatDate(date);
            document.getElementById('taskStart').value = dateStr;
            document.getElementById('taskEnd').value = dateStr;
        } else {
            const todayStr = formatDate(new Date());
            document.getElementById('taskStart').value = todayStr;
            document.getElementById('taskEnd').value = todayStr;
        }
    }

    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('taskModal').classList.remove('active');
}

function handleFormSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    // Validation: Require Description on Status Change for Supervisors
    if (window.PERMISSIONS && window.PERMISSIONS.requireDesc) {
        const form = document.getElementById('taskForm');
        // If we are in supervisor mode (checked by window permissions normally, but logic is robust enough)
        // We enforce the 15 word count if description is changed

        // For Supervisor, the 'review' is now in 'supervisor_notes' (from supervisorReviewText)
        // The standard 'description' field holds the original description.

        const review = data.supervisor_notes || '';
        // Count words excluding special characters and emojis (alphanumeric sequences only)
        const words = review.match(/[a-zA-Z0-9]+/g) || [];

        if (words.length < 15) {
            showCustomAlert(`Review/Description must contain at least 15 words. Current count: ${words.length}`, 'error');
            return;
        }
    }

    // Add project ID
    data.project_id = state.currentSite;

    // Remove empty taskId for new tasks
    if (!data.id || data.id === '') {
        delete data.id;
    }

    saveTaskToDatabase(data);
}

async function saveTaskToDatabase(data) {
    try {
        console.log('Saving task data:', data); // Debug log

        const response = await fetch('site/save_task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        console.log('API Response:', result); // Debug log

        if (result.success) {
            closeModal();
            render();
            // Play notification sound immediately
            const soundFile = data.id ? 'notification/task_update_real.mp3' : 'notification/task_update.mp3';
            const audio = new Audio(soundFile);
            audio.play().catch(e => console.log('Audio play failed:', e));

            showCustomAlert('Task saved successfully!');
        } else {
            showCustomAlert('Error saving task: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error saving task:', error);
        showCustomAlert('Error saving task. Please check the console for details.', 'error');
    }
}

function showCustomAlert(message, type = 'success') {
    const modal = document.getElementById('alertModal');
    const icon = document.getElementById('alertIcon');
    const title = document.getElementById('alertTitle');
    const msg = document.getElementById('alertMessage');

    // Set content
    msg.textContent = message;

    // Set style based on type
    icon.className = 'alert-icon ' + type;
    title.textContent = type === 'success' ? 'Success' : 'Error';

    // Show modal
    modal.classList.add('active');
}

function closeAlert() {
    document.getElementById('alertModal').classList.remove('active');
}

function showAllTasksForDay(tasks, date) {
    const modal = document.getElementById('alertModal');
    const icon = document.getElementById('alertIcon');
    const title = document.getElementById('alertTitle');
    const msg = document.getElementById('alertMessage');

    // Format the date nicely
    const dateStr = date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    // Set title
    title.textContent = `Tasks on ${dateStr}`;
    icon.style.display = 'none'; // Hide the icon for this use case

    // Build task list HTML
    let tasksHTML = '<div style="text-align: left; max-height: 400px; overflow-y: auto;">';

    tasks.forEach((task, index) => {
        const statusColors = {
            'planned': '#60a5fa',
            'in_progress': '#f59e0b',
            'completed': '#10b981',
            'blocked': '#ef4444',
            'on_hold': '#8b5cf6',
            'review': '#06b6d4',
            'cancelled': '#6b7280'
        };

        const color = statusColors[task.status] || '#6b7280';
        const todayStr = formatDate(new Date());
        const isDelayed = task.status !== 'completed' && task.end_date < todayStr;

        tasksHTML += `
            <div style="
                margin-bottom: 12px; 
                padding: 12px; 
                background: #f8fafc; 
                border-left: 4px solid ${color}; 
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s;
            " 
            onclick="document.getElementById('alertModal').classList.remove('active'); openModal(null, ${JSON.stringify(task).replace(/"/g, '&quot;')});"
            onmouseover="this.style.background='#f1f5f9'"
            onmouseout="this.style.background='#f8fafc'"
            >
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                    <strong style="color: #1e293b; font-size: 0.95rem;">${task.title}</strong>
                    ${isDelayed ? '<span style="color: #ef4444; font-size: 0.75rem; font-weight: 600;">⚠ DELAYED</span>' : ''}
                </div>
                <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 4px;">
                    Status: <span style="
                        background: ${color}; 
                        color: white; 
                        padding: 2px 8px; 
                        border-radius: 12px; 
                        font-size: 0.7rem;
                        font-weight: 600;
                    ">${task.status.replace('_', ' ').toUpperCase()}</span>
                </div>
                ${task.description ? `<div style="font-size: 0.8rem; color: #475569; margin-top: 6px;">${task.description.substring(0, 100)}${task.description.length > 100 ? '...' : ''}</div>` : ''}
                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 6px;">
                    ${task.start_date} → ${task.end_date}
                    ${task.assign_to ? ` • Assigned to: ${task.assign_to}` : ''}
                </div>
            </div>
        `;
    });

    tasksHTML += '</div>';
    msg.innerHTML = tasksHTML;

    // Modify the close button text
    const closeBtn = document.getElementById('closeAlertBtn');
    closeBtn.textContent = 'Close';

    // Show modal
    modal.classList.add('active');

    // Reset icon display when modal closes
    modal.addEventListener('transitionend', function resetIcon() {
        if (!modal.classList.contains('active')) {
            icon.style.display = 'flex';
            closeBtn.textContent = 'OK';
            modal.removeEventListener('transitionend', resetIcon);
        }
    });
}

function formatDate(date) {
    const d = new Date(date);
    let month = '' + (d.getMonth() + 1);
    let day = '' + d.getDate();
    const year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;
    return [year, month, day].join('-');
}

function updateWordCount() {
    const desc = document.getElementById('taskDesc').value || '';
    const wcDisplay = document.getElementById('descriptionWordCount');
    if (!wcDisplay) return;

    // Count words excluding special characters and emojis
    const words = desc.match(/[a-zA-Z0-9]+/g) || [];
    const count = words.length;

    wcDisplay.textContent = `Words: ${count}/15`;

    if (count < 15) {
        wcDisplay.style.color = '#ef4444'; // Red
    } else {
        wcDisplay.style.color = '#10b981'; // Green
    }
}

// --- Timeline / History Logic ---

function openTaskHistory(taskId) {
    let drawer = document.getElementById('timelineDrawer');
    if (!drawer) {
        createTimelineDrawer();
        drawer = document.getElementById('timelineDrawer');
    }

    // Show drawer (slide in)
    drawer.classList.add('active');

    const overlay = document.getElementById('timelineOverlay');
    if (overlay) overlay.style.display = 'block';

    // Show loading state
    const container = document.getElementById('timelineContent');
    container.innerHTML = '<div style="padding:20px; text-align:center; color:#64748b;">Loading history...</div>';

    // Fetch Data
    fetch(`site/get_task_history.php?task_id=${taskId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderTimeline(data.data);
            } else {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:#ef4444;">Error loading history.</div>';
            }
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div style="padding:20px; text-align:center; color:#ef4444;">Connection error.</div>';
        });
}

function closeTimelinedrawer() {
    const drawer = document.getElementById('timelineDrawer');
    if (drawer) drawer.classList.remove('active');
    const overlay = document.getElementById('timelineOverlay');
    if (overlay) overlay.style.display = 'none';
}

function createTimelineDrawer() {
    // Inject Styles if needed
    if (!document.getElementById('timelineStyles')) {
        const style = document.createElement('style');
        style.id = 'timelineStyles';
        style.textContent = `
            .timeline-drawer {
                position: fixed;
                top: 0;
                right: -400px;
                width: 350px;
                height: 100vh;
                background: white;
                box-shadow: -5px 0 25px rgba(0,0,0,0.15);
                z-index: 2000;
                transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                display: flex;
                flex-direction: column;
                border-left: 1px solid #e2e8f0;
            }
            .timeline-drawer.active {
                right: 0;
            }
            .timeline-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.2);
                z-index: 1999;
                display: none;
            }
            .timeline-drawer.active + .timeline-overlay, 
            .timeline-overlay.active { 
                display: block; 
            }
            .timeline-item {
                position: relative;
                padding-left: 24px;
                margin-bottom: 20px;
            }
            .timeline-item::before {
                content: '';
                position: absolute;
                left: 6px;
                top: 24px;
                bottom: -24px;
                width: 2px;
                background: #e2e8f0;
            }
            .timeline-item:last-child::before {
                display: none;
            }
            .timeline-dot {
                position: absolute;
                left: 0;
                top: 4px;
                width: 14px;
                height: 14px;
                border-radius: 50%;
                border: 2px solid #fff;
                box-shadow: 0 0 0 1px #cbd5e1;
            }
            .timeline-date {
                font-size: 0.75rem;
                color: #94a3b8;
                margin-bottom: 2px;
            }
            .timeline-content {
                background: #f8fafc;
                padding: 10px;
                border-radius: 6px;
                border: 1px solid #f1f5f9;
                font-size: 0.85rem;
                color: #334155;
            }
        `;
        document.head.appendChild(style);
    }

    // Overlay
    const overlay = document.createElement('div');
    overlay.id = 'timelineOverlay';
    overlay.className = 'timeline-overlay';
    overlay.onclick = closeTimelinedrawer;
    document.body.appendChild(overlay);

    // Drawer
    const drawer = document.createElement('div');
    drawer.id = 'timelineDrawer';
    drawer.className = 'timeline-drawer';
    drawer.innerHTML = `
        <div style="padding: 16px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fff;">
            <h3 style="font-weight: 700; color: #1e293b; margin:0;">Task History</h3>
            <button onclick="closeTimelinedrawer()" style="background:none; border:none; color:#64748b; cursor:pointer; font-size:1.2rem;">&times;</button>
        </div>
        <div id="timelineContent" style="flex: 1; overflow-y: auto; padding: 20px;">
            <!-- Content goes here -->
        </div>
    `;
    document.body.appendChild(drawer);

    document.body.appendChild(drawer);

    // Patch removed
}

function renderTimeline(logs) {
    const container = document.getElementById('timelineContent');
    if (!logs || logs.length === 0) {
        container.innerHTML = '<div style="text-align:center; color:#94a3b8; margin-top:20px;">No history available.</div>';
        return;
    }

    let html = '';
    logs.forEach(log => {
        const date = new Date(log.created_at);
        const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        let dotColor = '#cbd5e1'; // gray
        if (log.action_type === 'CREATED') dotColor = '#10b981';
        if (log.action_type === 'STATUS_CHANGE') dotColor = '#3b82f6';
        if (log.action_type === 'UPDATED') dotColor = '#f59e0b';

        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: ${dotColor}; box-shadow: 0 0 0 1px ${dotColor};"></div>
                <div class="timeline-date">${dateStr}</div>
                <div style="font-weight:600; color:#1e293b; font-size:0.85rem; margin-bottom:2px;">
                    ${log.action_type.replace('_', ' ')}
                    <span style="font-weight:400; color:#64748b; font-size:0.75rem;">by ${log.performed_by_name || 'Unknown'}</span>
                </div>
                <div class="timeline-content">
                    ${log.details || 'No details'}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}
