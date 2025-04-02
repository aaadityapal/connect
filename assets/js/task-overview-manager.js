class TaskOverviewManager {
    constructor() {
        // Get user role safely
        this.userRole = document.body?.dataset?.userRole || 'default'; // Provide a default role
        
        // Initialize only if DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initialize());
        } else {
            this.initialize();
        }

        // Bind the updateStageStatus method to window
        window.updateStageStatus = this.updateStageStatus.bind(this);

        // Initialize with the earliest year from the tasks
        this.selectedYear = 2001; // Set default to 2001 since that's what we see in the data
        this.selectedMonth = 'all'; // Default to 'all'
        this.initializeFilters();
        
        // Set default display text for month filter to "All Months"
        const monthFilter = document.getElementById('monthFilter');
        if (monthFilter) {
            const monthSpan = monthFilter.querySelector('span');
            if (monthSpan) {
                monthSpan.textContent = 'All Months';
            }
        }

        // Set the 'selected' class on the "All Months" option
        const allMonthsOption = document.querySelector('.month-option[data-month="all"]');
        if (allMonthsOption) {
            const monthOptions = document.querySelectorAll('.month-option');
            monthOptions.forEach(opt => opt.classList.remove('selected'));
            allMonthsOption.classList.add('selected');
        }

        // Trigger initial filtering
        this.filterTasks();

        // Add this to your initialization
        this.fetchForwardedTasks();
        
        // Optionally, set up periodic refresh
        setInterval(() => this.fetchForwardedTasks(), 300000); // Refresh every 5 minutes
    }

    initialize() {
        // Move all initialization code here
        this.taskFilters = document.querySelectorAll('.task-filter');
        this.dateFrom = document.getElementById('dateFrom');
        this.dateTo = document.getElementById('dateTo');
        this.applyFilterBtn = document.querySelector('.apply-filter-btn');
        this.clearFilterBtn = document.querySelector('.clear-filter-btn');
        
        this.attachEventListeners();
        this.initializeProgressBars();
        this.updateCardTitles();
        this.fetchAllotedProjects();
        this.fetchProjectStages();
        this.fetchProjectSubstages();
        
        this.attachCardClickHandlers();
        this.initializeSubstageToggles();
        // Initialize forwarded tasks section (placeholder for future functionality)
        this.initializeForwardedTasks();

        // Update user role after DOM is loaded
        this.userRole = document.body.dataset.userRole;

        // Add click handlers for forward icons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('status-forward-icon')) {
                this.handleForward(e);
            }
        });
    }

    attachEventListeners() {
        // Task filter clicks
        if (this.taskFilters) {
            this.taskFilters.forEach(filter => {
                filter.addEventListener('click', () => this.handleFilterClick(filter));
            });
        }

        // Date filter buttons
        if (this.applyFilterBtn) {
            this.applyFilterBtn.addEventListener('click', () => this.applyDateFilter());
        }
        if (this.clearFilterBtn) {
            this.clearFilterBtn.addEventListener('click', () => this.clearDateFilter());
        }
    }

    handleFilterClick(clickedFilter) {
        this.taskFilters.forEach(f => f.classList.remove('active'));
        clickedFilter.classList.add('active');
        // TODO: Implement actual filtering logic
    }

    applyDateFilter() {
        // TODO: Implement date filtering logic
        console.log('Filtering from:', this.dateFrom.value, 'to:', this.dateTo.value);
    }

    clearDateFilter() {
        if (this.dateFrom) this.dateFrom.value = '';
        if (this.dateTo) this.dateTo.value = '';
        // TODO: Reset to default view
    }

    initializeProgressBars() {
        const progressBars = document.querySelectorAll('.progress-fill');
        if (progressBars) {
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        }
    }

    updateCardTitles() {
        const cardTitles = document.querySelectorAll('.task-card .card-header h3');
        if (cardTitles && cardTitles.length >= 3) {
            cardTitles[0].textContent = 'Projects Alloted';
            cardTitles[1].textContent = 'Stages Pending';
            cardTitles[2].textContent = 'Substages Pending';
        }
    }

    async fetchAllotedProjects() {
        try {
            const response = await fetch('api/projects/alloted.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const projectsData = await response.json();
            // Check if we received an error response
            if (projectsData.error) {
                throw new Error(projectsData.error);
            }
            
            this.updateProjectsCard(projectsData);
        } catch (error) {
            console.error('Error fetching alloted projects:', error);
            this.showErrorState();
        }
    }

    updateProjectsCard(projectsData) {
        const firstCard = document.querySelector('.task-card:first-child');
        if (!firstCard) return;

        const descriptionElement = firstCard.querySelector('.task-description');
        const progressFill = firstCard.querySelector('.progress-fill');
        const dueDateElement = firstCard.querySelector('.task-due-date');
        const statusElement = firstCard.querySelector('.task-status');

        if (projectsData.length === 0) {
            descriptionElement.innerHTML = `
                <i class="fas fa-tasks"></i>
                No projects currently assigned
            `;
            progressFill.style.width = '0%';
            statusElement.className = 'task-status status-pending';
            statusElement.textContent = 'No Projects';
            return;
        }

        // Count projects by status
        const pendingProjects = projectsData.filter(project => 
            project.status === 'pending'
        ).length;
        
        const notStartedProjects = projectsData.filter(project => 
            project.status === 'not_started'
        ).length;

        const completedProjects = projectsData.filter(project => 
            project.status === 'completed'
        ).length;

        // Calculate progress percentage
        // Not started = 0%, Pending = 50%, Completed = 100%
        const totalWeight = projectsData.length * 100; // Maximum possible progress
        const currentProgress = (completedProjects * 100) + (pendingProjects * 50); // Pending counts as half progress
        const progressPercentage = (currentProgress / totalWeight) * 100;

        // Update card content
        descriptionElement.innerHTML = `
            <i class="fas fa-tasks"></i>
            Currently assigned to ${projectsData.length} project${projectsData.length !== 1 ? 's' : ''} 
            (${pendingProjects} pending, ${notStartedProjects} not started)
        `;

        // Update progress bar with color based on percentage
        progressFill.style.width = `${progressPercentage}%`;
        if (progressPercentage < 30) {
            progressFill.style.backgroundColor = '#ef4444'; // Red for low progress
        } else if (progressPercentage < 70) {
            progressFill.style.backgroundColor = '#f59e0b'; // Orange for medium progress
        } else {
            progressFill.style.backgroundColor = '#10b981'; // Green for good progress
        }

        // Find and display nearest deadline
        const upcomingDeadline = projectsData
            .filter(project => project.end_date && project.status !== 'completed')
            .sort((a, b) => new Date(a.end_date) - new Date(b.end_date))[0];

        if (upcomingDeadline) {
            const formattedDate = new Date(upcomingDeadline.end_date)
                .toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            dueDateElement.innerHTML = `
                <i class="far fa-calendar"></i>
                Next Due: ${formattedDate}
            `;
        } else {
            dueDateElement.innerHTML = `
                <i class="far fa-calendar"></i>
                No upcoming deadlines
            `;
        }

        // Update status badge
        if (pendingProjects > 0) {
            statusElement.className = 'task-status status-pending';
            statusElement.textContent = 'Pending';
        } else if (notStartedProjects > 0) {
            statusElement.className = 'task-status status-not-started';
            statusElement.textContent = 'Not Started';
        } else if (projectsData.length === completedProjects) {
            statusElement.className = 'task-status status-completed';
            statusElement.textContent = 'All Completed';
        } else {
            statusElement.className = 'task-status status-pending';
            statusElement.textContent = 'Pending';
        }
    }

    showErrorState() {
        const firstCard = document.querySelector('.task-card:first-child');
        if (!firstCard) return;

        const descriptionElement = firstCard.querySelector('.task-description');
        descriptionElement.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            Error loading project data
        `;
    }

    async fetchProjectStages() {
        try {

            const response = await fetch('api/projects/stages.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });

            

            let stagesData;
            try {
                stagesData = JSON.parse(responseText);
            } catch (e) {
                
                throw new Error('Invalid JSON response from server');
            }

            if (!response.ok) {
                throw new Error(`Server error: ${stagesData.error || response.statusText}`);
            }

            // Check if we received an error response
            if (stagesData.error) {
                throw new Error(stagesData.error);
            }
            
            this.updateStagesCard(stagesData);
        } catch (error) {
            console.error('Error fetching project stages:', error);
            this.showStagesErrorState();
        }
    }

    updateStagesCard(stagesData) {
        const secondCard = document.querySelector('.task-card:nth-child(2)');
        if (!secondCard) return;

        const descriptionElement = secondCard.querySelector('.task-description');
        const progressFill = secondCard.querySelector('.progress-fill');
        const dueDateElement = secondCard.querySelector('.task-due-date');
        const statusElement = secondCard.querySelector('.task-status');

        if (stagesData.length === 0) {
            descriptionElement.innerHTML = `
                <i class="fas fa-tasks"></i>
                No pending stages
            `;
            progressFill.style.width = '0%';
            statusElement.className = 'task-status status-completed';
            statusElement.textContent = 'No Pending Stages';
            return;
        }

        // Count stages by status
        const pendingStages = stagesData.filter(stage => 
            stage.status === 'pending'
        ).length;
        
        const notStartedStages = stagesData.filter(stage => 
            stage.status === 'not_started'
        ).length;

        // Calculate progress percentage
        // Not started = 0%, Pending = 50%
        const totalWeight = stagesData.length * 100;
        const currentProgress = pendingStages * 50; // Pending counts as half progress
        const progressPercentage = (currentProgress / totalWeight) * 100;

        // Update card content
        descriptionElement.innerHTML = `
            <i class="fas fa-tasks"></i>
            ${stagesData.length} stage${stagesData.length !== 1 ? 's' : ''} pending 
            (${pendingStages} in progress, ${notStartedStages} not started)
        `;

        // Update progress bar with color based on percentage
        progressFill.style.width = `${progressPercentage}%`;
        if (progressPercentage < 30) {
            progressFill.style.backgroundColor = '#ef4444'; // Red for low progress
        } else if (progressPercentage < 70) {
            progressFill.style.backgroundColor = '#f59e0b'; // Orange for medium progress
        } else {
            progressFill.style.backgroundColor = '#10b981'; // Green for good progress
        }

        // Find and display nearest deadline
        const upcomingDeadline = stagesData
            .filter(stage => stage.end_date)
            .sort((a, b) => new Date(a.end_date) - new Date(b.end_date))[0];

        if (upcomingDeadline) {
            const formattedDate = new Date(upcomingDeadline.end_date)
                .toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            dueDateElement.innerHTML = `
                <i class="far fa-calendar"></i>
                Next Stage Due: ${formattedDate}
                (${upcomingDeadline.project_title})
            `;
        } else {
            dueDateElement.innerHTML = `
                <i class="far fa-calendar"></i>
                No upcoming deadlines
            `;
        }

        // Update status badge
        if (pendingStages > 0) {
            statusElement.className = 'task-status status-pending';
            statusElement.textContent = 'Stages Pending';
        } else if (notStartedStages > 0) {
            statusElement.className = 'task-status status-not-started';
            statusElement.textContent = 'Stages Not Started';
        } else {
            statusElement.className = 'task-status status-completed';
            statusElement.textContent = 'All Stages Complete';
        }
    }

    showStagesErrorState() {
        const secondCard = document.querySelector('.task-card:nth-child(2)');
        if (!secondCard) return;

        const descriptionElement = secondCard.querySelector('.task-description');
        descriptionElement.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            Error loading stages data
        `;
    }

    async fetchProjectSubstages() {
        try {
            const response = await fetch('api/projects/substages.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const substagesData = await response.json();
            if (substagesData.error) {
                throw new Error(substagesData.error);
            }
            
            this.updateSubstagesCard(substagesData);
        } catch (error) {
            console.error('Error fetching project substages:', error);
            this.showSubstagesErrorState();
        }
    }

    updateSubstagesCard(substagesData) {
        const thirdCard = document.querySelector('.task-card:nth-child(3)');
        if (!thirdCard) {
            console.error('Third card element not found');
            return;
        }

        const descriptionElement = thirdCard.querySelector('.task-description');
        const progressFill = thirdCard.querySelector('.progress-fill');
        const dueDateElement = thirdCard.querySelector('.task-due-date');
        const statusElement = thirdCard.querySelector('.task-status');

        // Check if all required elements exist
        if (!descriptionElement || !progressFill || !dueDateElement || !statusElement) {
            console.error('Required elements not found in third card');
            return;
        }

        if (!Array.isArray(substagesData)) {
            console.error('Invalid substages data received');
            this.showSubstagesErrorState();
            return;
        }

        if (substagesData.length === 0) {
            descriptionElement.innerHTML = `
                <i class="fas fa-tasks"></i>
                No pending substages
            `;
            progressFill.style.width = '0%';
            statusElement.className = 'task-status status-completed';
            statusElement.textContent = 'No Pending Substages';
            return;
        }

        // Count substages by status
        const pendingSubstages = substagesData.filter(substage => 
            substage.status === 'pending'
        ).length;
        
        const notStartedSubstages = substagesData.filter(substage => 
            substage.status === 'not_started'
        ).length;

        // Calculate progress percentage
        const totalWeight = substagesData.length * 100;
        const currentProgress = pendingSubstages * 50;
        const progressPercentage = (currentProgress / totalWeight) * 100;

        // Update card content
        descriptionElement.innerHTML = `
            <i class="fas fa-tasks"></i>
            ${substagesData.length} substage${substagesData.length !== 1 ? 's' : ''} pending 
            (${pendingSubstages} in progress, ${notStartedSubstages} not started)
        `;

        // Update progress bar with color based on percentage
        progressFill.style.width = `${progressPercentage}%`;
        if (progressPercentage < 30) {
            progressFill.style.backgroundColor = '#ef4444';
        } else if (progressPercentage < 70) {
            progressFill.style.backgroundColor = '#f59e0b';
        } else {
            progressFill.style.backgroundColor = '#10b981';
        }

        // Find and display nearest deadline
        const upcomingDeadline = substagesData
            .filter(substage => substage.end_date)
            .sort((a, b) => new Date(a.end_date) - new Date(b.end_date))[0];

        if (upcomingDeadline) {
            const formattedDate = new Date(upcomingDeadline.end_date)
                .toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            dueDateElement.innerHTML = `
                <i class="far fa-calendar"></i>
                Next Substage Due: ${formattedDate}
                (${upcomingDeadline.project_title} - Stage ${upcomingDeadline.stage_number})
            `;
        } else {
            dueDateElement.innerHTML = `
                <i class="far fa-calendar"></i>
                No upcoming deadlines
            `;
        }

        // Update status badge
        if (pendingSubstages > 0) {
            statusElement.className = 'task-status status-pending';
            statusElement.textContent = 'Substages Pending';
        } else if (notStartedSubstages > 0) {
            statusElement.className = 'task-status status-not-started';
            statusElement.textContent = 'Substages Not Started';
        } else {
            statusElement.className = 'task-status status-completed';
            statusElement.textContent = 'All Substages Complete';
        }
    }

    showSubstagesErrorState() {
        const thirdCard = document.querySelector('.task-card:nth-child(3)');
        if (!thirdCard) {
            console.error('Third card element not found');
            return;
        }

        const descriptionElement = thirdCard.querySelector('.task-description');
        if (!descriptionElement) {
            console.error('Description element not found in third card');
            return;
        }

        descriptionElement.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            Error loading substages data
        `;
    }

    attachCardClickHandlers() {
        document.addEventListener('click', async (e) => {
            const projectCard = e.target.closest('.project-card');
            if (projectCard) {
                const projectId = projectCard.dataset.projectId;
                if (projectId) {
                    await this.showProjectDetails(projectId);
                }
            }
        });
    }

    async showProjectDetails(projectId) {
        try {
            const response = await fetch(`dashboard/handlers/get_project_details.php?project_id=${projectId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch project details');
            }

            const project = data.project;
            const mainContent = document.querySelector('main');

            // Safety check for mainContent
            if (!mainContent) {
                console.warn('Main content element not found');
            }

            const applyBlur = () => {
                if (mainContent) {
                    mainContent.classList.add('blurred');
                    mainContent.style.filter = 'blur(10px)';
                    mainContent.style.pointerEvents = 'none';
                }
            };

            const removeBlur = () => {
                if (mainContent) {
                    mainContent.classList.remove('blurred');
                    mainContent.style.filter = 'none';
                    mainContent.style.pointerEvents = 'auto';
                }
            };
            
            // Create and show dialog using SweetAlert2
            await Swal.fire({
                title: `<div class="dialog-header">
                           
                            <button type="button" class="custom-close-button" onclick="Swal.close()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>`,
                html: this.generateProjectDetailsHTML(project),
                width: '800px',
                showCloseButton: false,
                showConfirmButton: false,
                allowOutsideClick: true,
                allowEscapeKey: true,
                customClass: {
                    container: 'project-details-dialog',
                    popup: 'project-details-popup',
                    content: 'project-details-content',
                    title: 'project-details-title',
                    closeButton: 'custom-close-button',
                    backdrop: 'project-details-backdrop'
                },
                backdrop: `
                    rgba(0,0,0,0.4)
                    url("")
                    left top
                    no-repeat
                `,
                didOpen: () => {
                    applyBlur();
                },
                willClose: () => {
                    removeBlur();
                    // Ensure all event listeners are working
                    if (this.attachCardClickHandlers) {
                        this.attachCardClickHandlers();
                    }
                }
            });

            // Additional cleanup after dialog is fully closed
            removeBlur();

        } catch (error) {
            console.error('Error fetching project details:', error);
            // Ensure main content is interactive even if there's an error
            const mainContent = document.querySelector('main');
            if (mainContent) {
                mainContent.style.pointerEvents = 'auto';
                mainContent.style.filter = 'none';
                mainContent.classList.remove('blurred');
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load project details. Please try again.',
            });
        }
    }

    generateProjectDetailsHTML(project) {
        const stagesHTML = project.stages.map(stage => {
            const isDisabled = stage.status === 'freezed' || stage.status === 'sent_to_client';
            const disabledReason = stage.status === 'freezed' ? 
                'This stage is currently frozen' : 
                'This stage has been sent to client';

            const substagesHTML = stage.substages.map(substage => {
                // Add disabled state to substages if stage is disabled
                const substageDisabled = isDisabled ? 'disabled' : '';
                const displayStatus = substage.status;

                return `
                    <div class="substage-item" 
                        data-status="${displayStatus}" 
                        data-substage-id="${substage.id}" 
                        data-project-id="${project.id}"
                        ${isDisabled ? `data-disabled-reason="${disabledReason}"` : ''}>
                        <div class="substage-header">
                            <div class="substage-title-wrapper">
                                <span class="substage-number">${substage.substage_identifier}.</span>
                                <span class="substage-title">${this.escapeHtml(substage.title)}</span>
                                <i class="fas fa-forward status-forward-icon"></i>
                                <select class="status-dropdown" 
                                    onchange="updateSubstageStatus(this, '${substage.id}')" 
                                    ${substageDisabled}>
                                    <option value="not_started" ${displayStatus === 'not_started' ? 'selected' : ''}>Not Started</option>
                                    <option value="in_progress" ${displayStatus === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                    <option value="in_review" ${displayStatus === 'in_review' ? 'selected' : ''} disabled>In Review</option>
                                    <option value="completed" ${displayStatus === 'completed' ? 'selected' : ''} disabled>Completed</option>
                                </select>
                            </div>
                            <span class="substage-status status-${displayStatus.toLowerCase()}">
                                ${this.getStatusIcon(displayStatus)}
                                ${this.formatStatus(displayStatus)}
                            </span>
                        </div>
                        <div class="substage-meta">
                            <div class="substage-meta-info">
                                <span class="assignee">
                                    <i class="far fa-user-circle"></i>
                                    ${this.escapeHtml(substage.assignee_name || 'Unassigned')}
                                </span>
                                <span class="dates">
                                    <i class="far fa-calendar-alt"></i>
                                    Start Date: ${this.formatDate(substage.start_date)} - End Date: ${this.formatDate(substage.end_date)}
                                </span>
                            </div>
                            <div class="toggle-container">
                                <button type="button" class="substage-toggle" onclick="toggleSubstageDetails(this)">
                                    <span>Show Details</span>
                                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="substage-details">
                            <div class="details-content">
                                <table class="substage-files-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><i class="far fa-file-alt"></i></th>
                                            <th>Type</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${this.generateFilesTable(substage.files, isDisabled)}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="stage-item" 
                    data-status="${stage.status}" 
                    data-stage-id="${stage.id}" 
                    data-project-id="${project.id}"
                    ${isDisabled ? `data-disabled-reason="${disabledReason}"` : ''}>
                    <div class="stage-header">
                        <div class="stage-title-wrapper">
                            <span class="stage-icon"><i class="fas fa-layer-group"></i></span>
                            <h3>Stage ${stage.stage_number}</h3>
                            <i class="fas fa-forward status-forward-icon"></i>
                            <select class="status-dropdown" 
                                onchange="updateStageStatus(this, '${stage.id}')"
                                ${isDisabled ? 'disabled' : ''}>
                                <option value="not_started" ${stage.status === 'not_started' ? 'selected' : ''}>Not Started</option>
                                <option value="in_progress" ${stage.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                <option value="completed" ${stage.status === 'completed' ? 'selected' : ''} disabled>Completed</option>
                                <option value="freezed" ${stage.status === 'freezed' ? 'selected' : ''} disabled>Freezed</option>
                                <option value="sent_to_client" ${stage.status === 'sent_to_client' ? 'selected' : ''} disabled>Sent to Client</option>
                            </select>
                        </div>
                        <span class="stage-status status-${stage.status.toLowerCase()}">
                            ${this.getStatusIcon(stage.status)}
                            ${this.formatStatus(stage.status)}
                        </span>
                    </div>
                    <div class="stage-meta">
                        <span>
                            <i class="far fa-user-circle"></i>
                            ${this.escapeHtml(stage.assignee_name || 'Unassigned')}
                        </span>
                        <span>
                            <i class="far fa-calendar-alt"></i>
                            Start Date: ${this.formatDate(stage.start_date)} - End Date: ${this.formatDate(stage.end_date)}
                        </span>
                    </div>
                    <div class="substages-container">
                        ${substagesHTML}
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div class="project-details" data-project-id="${project.id}">
                <div class="project-header">
                    <div class="project-meta">
                        <span class="project-type">${this.escapeHtml(project.project_type)}</span>
                        <span class="project-status status-${project.status.toLowerCase()}">${project.status}</span>
                    </div>
                    <div class="project-timeline">
                        <div class="timeline-header">Project Deadline</div>
                        <div class="timeline-dates">
                            <div class="date-block">
                                <span class="date-label">From</span>
                                <span class="date-value">
                                    <i class="far fa-calendar-alt"></i>
                                    ${new Date(project.start_date).toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'short', 
                                        day: 'numeric' 
                                    })}
                                </span>
                            </div>
                            <div class="timeline-separator">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                            <div class="date-block">
                                <span class="date-label">To</span>
                                <span class="date-value">
                                    <i class="far fa-calendar-alt"></i>
                                    ${new Date(project.end_date).toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'short', 
                                        day: 'numeric' 
                                    })}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="project-description">
                    <p>${this.escapeHtml(project.description)}</p>
                </div>
                <div class="project-assignment">
                    <span><i class="fas fa-user-plus"></i> Created by: ${this.escapeHtml(project.created_by_username)}</span>
                    <span><i class="fas fa-user-check"></i> Assigned to: ${this.escapeHtml(project.assigned_to_username || 'Unassigned')}</span>
                </div>
                <div class="stages-container">
                    ${stagesHTML}
                </div>
            </div>
        `;
    }

    generateFilesTable(files = [], isDisabled) {
        console.log('Generating files table with:', files);
        
        // Safety check for files array
        if (!Array.isArray(files)) {
            console.error('Files is not an array:', files);
            files = [];
        }

        let tableContent;
        if (files.length === 0) {
            tableContent = `
                <tr>
                    <td colspan="4" class="no-files">No files available</td>
                </tr>
            `;
        } else {
            tableContent = files.map((file, index) => {
                console.log('Processing file:', file);
                return `
                    <tr data-file-id="${file.id}" class="${file.status === 'sent_for_approval' ? 'file-sent-for-approval' : ''}">
                        <td>${index + 1}</td>
                        <td>${this.escapeHtml(file.file_name || '')}</td>
                        <td class="status-column">
                            <span class="status-badge status-${file.status.toLowerCase()}">
                                ${this.getStatusIcon(file.status)}
                                ${this.formatStatus(file.status)}
                            </span>
                        </td>
                        <td class="action-buttons">
                            <button class="action-btn view-btn" title="View" 
                                onclick="viewFile('${file.id}', '${file.file_path || ''}')"
                                ${isDisabled ? 'disabled data-disabled-reason="Stage is currently locked"' : ''}>
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn download-btn" title="Download" 
                                onclick="downloadFile('${file.id}', '${file.file_path || ''}')"
                                ${isDisabled ? 'disabled data-disabled-reason="Stage is currently locked"' : ''}>
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="action-btn send-btn" title="Send" onclick="sendFile('${file.id}')">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                            <button class="action-btn approve-btn" title="${file.status === 'approved' || file.status === 'rejected' ? 'Action not available' : 'Approve'}" 
                                    onclick="approveFile('${file.id}')"
                                    ${file.status === 'approved' || file.status === 'rejected' ? 'disabled' : ''}>
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="action-btn reject-btn" title="${file.status === 'approved' || file.status === 'rejected' ? 'Action not available' : 'Reject'}" 
                                    onclick="rejectFile('${file.id}')"
                                    ${file.status === 'approved' || file.status === 'rejected' ? 'disabled' : ''}>
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        const nextSerialNumber = files.length > 0 ? files.length + 1 : 1;

        return `
            ${tableContent}
            <tr class="empty-table-row" onclick="taskManager.showUploadForm(this, ${nextSerialNumber})">
                <td>${nextSerialNumber}</td>
                <td class="add-new-cell">
                    <i class="fas fa-plus add-icon"></i>
                </td>
                <td></td>
                <td></td>
            </tr>
            <tr class="upload-form-row" style="display: none;">
                <td colspan="4">
                    <form class="file-upload-form" onsubmit="return taskManager.handleFileUpload(event)">
                        <input type="hidden" name="substageId" value="">
                        <input type="hidden" name="columnType" value="filename">
                        <div class="form-group">
                            <label for="fileName">File Name:</label>
                            <input type="text" id="fileName" name="fileName" required>
                        </div>
                        <div class="form-group">
                            <label for="fileUpload">Choose File:</label>
                            <input type="file" id="fileUpload" name="file" required>
                        </div>
                        <div class="form-buttons">
                            <button type="submit" class="submit-btn">Upload</button>
                            <button type="button" class="cancel-btn" onclick="taskManager.hideUploadForm(this)">Cancel</button>
                        </div>
                    </form>
                </td>
            </tr>
        `;
    }

    showUploadForm(row, serialNumber) {
        const uploadFormRow = row.nextElementSibling;
        const substageItem = row.closest('.substage-item');
        const substageId = substageItem.dataset.substageId;
        
        // Hide any other open forms
        document.querySelectorAll('.upload-form-row').forEach(form => {
            if (form !== uploadFormRow) {
                form.style.display = 'none';
            }
        });

        // Show this form
        uploadFormRow.style.display = 'table-row';
        uploadFormRow.querySelector('input[name="substageId"]').value = substageId;
    }

    hideUploadForm(button) {
        const formRow = button.closest('.upload-form-row');
        formRow.style.display = 'none';
    }

    async handleFileUpload(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const substageId = form.querySelector('input[name="substageId"]').value;

        try {
            const response = await fetch('api/upload_substage_file.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'File uploaded successfully',
                    timer: 2000,
                    showConfirmButton: false
                });

                // Refresh the substage details
                const substageItem = form.closest('.substage-item');
                if (substageItem) {
                    const detailsContainer = substageItem.querySelector('.substage-details');
                    if (detailsContainer) {
                        // Fetch updated files and refresh the content
                        const files = await this.fetchSubstageFiles(substageId);
                        const tableContent = this.generateFilesTable(files);
                        detailsContainer.querySelector('.details-content').innerHTML = `
                            <table class="substage-files-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>File Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableContent}
                                </tbody>
                            </table>
                        `;
                    }
                }
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        } catch (error) {
            console.error('Error uploading file:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to upload file. Please try again.'
            });
        }

        // Hide the form after upload (success or failure)
        this.hideUploadForm(form.querySelector('.cancel-btn'));
        return false;
    }

    escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getStatusIcon(status) {
        console.log('Getting icon for status:', status);
        // Map status to specific icon classes
        const icons = {
            'not_started': '⛔',
            'pending': '⌛',
            'approved': '✅',
            'rejected': '❌',
            'in_review': '🔍',
            'sent_for_approval': '📩',
            'completed': '✅',
            'in_progress': '🔄', // Added in_progress status
            'freezed': '❄️',
            'sent_to_client': '📨',

        };
        
        // Return the full icon HTML or a default icon if status not found
        return icons[status.toLowerCase()] || '📋';
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    formatStatus(status) {
        console.log('Formatting status:', status);
        const formatted = status.split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
        console.log('Formatted status:', formatted);
        return formatted;
    }

    initializeSubstageToggles() {
        window.toggleSubstageDetails = async (button) => {
            try {
                const substageItem = button.closest('.substage-item');
                if (!substageItem) {
                    console.error('Could not find substage item');
                    return;
                }

                const substageId = substageItem.dataset.substageId;
                if (!substageId) {
                    console.error('No substage ID found');
                    return;
                }

                const details = substageItem.querySelector('.substage-details');
                const buttonText = button.querySelector('span');
                
                const isExpanded = details.style.display === 'block';
                
                if (isExpanded) {
                    details.style.display = 'none';
                    buttonText.textContent = 'Show Details';
                } else {
                    const files = await this.fetchSubstageFiles(substageId);
                    const tableContent = this.generateFilesTable(files);
                    
                    details.innerHTML = `
                        <div class="details-content">
                            <table class="substage-files-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>File Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableContent}
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    details.style.display = 'block';
                    buttonText.textContent = 'Hide Details';
                }
            } catch (error) {
                console.error('Error toggling substage details:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load substage details. Please try again.'
                });
            }
        };

        // Add the status update function
        window.updateSubstageStatus = async (selectElement, substageId) => {
            const newStatus = selectElement.value;
            const substageItem = selectElement.closest('.substage-item');
            
            try {
                const response = await fetch('dashboard/handlers/update_substage_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        substage_id: substageId,
                        status: newStatus
                    }),
                    credentials: 'include'
                });

                const data = await response.json();
                
                if (data.success) {
                    // Update the status attribute for color band
                    const substageElement = document.querySelector(`[data-substage-id="${substageId}"]`);
                    if (substageElement) {
                        substageElement.setAttribute('data-status', newStatus);
                    }
                    
                    // Update the status badge
                    const statusBadge = substageItem.querySelector('.substage-status');
                    statusBadge.className = `substage-status status-${newStatus.toLowerCase()}`;
                    statusBadge.innerHTML = `${this.getStatusIcon(newStatus)} ${this.formatStatus(newStatus)}`;
                    
                    // Update the data-status attribute
                    substageItem.dataset.status = newStatus;
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Status Updated',
                        text: 'Substage status has been updated successfully',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    throw new Error(data.message || 'Failed to update status');
                }
            } catch (error) {
                console.error('Error updating substage status:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update status. Please try again.'
                });
                // Revert the select to the previous value
                selectElement.value = substageItem.dataset.status;
            }
        };
    }

    // Define updateStageStatus as a class method
    async updateStageStatus(selectElement, stageId) {
        const newStatus = selectElement.value;
        const stageItem = selectElement.closest('.stage-item');
        
        try {
            const response = await fetch('dashboard/handlers/update_stage_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    stage_id: stageId,
                    status: newStatus
                }),
                credentials: 'include'
            });

            const data = await response.json();
            
            if (data.success) {
                // Update the status badge
                const statusBadge = stageItem.querySelector('.stage-status');
                statusBadge.className = `stage-status status-${newStatus.toLowerCase()}`;
                statusBadge.innerHTML = `${this.getStatusIcon(newStatus)} ${this.formatStatus(newStatus)}`;
                
                // Update the data-status attribute
                stageItem.dataset.status = newStatus;
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Status Updated',
                    text: 'Stage status has been updated successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error(data.message || 'Failed to update status');
            }
        } catch (error) {
            console.error('Error updating stage status:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to update status. Please try again.'
            });
            // Revert the select to the previous value
            selectElement.value = stageItem.dataset.status;
        }
    }

    // Move hasFullStatusAccess into the class as a method
    hasFullStatusAccess(userRole) {
        const allowedRoles = [
            'Senior Manager (Studio)',
            'Senior Manager (Site)',
            'admin',
            'HR'
        ];
        return allowedRoles.includes(userRole);
    }

    async fetchSubstageFiles(substageId) {
        try {
            console.log('Fetching files for substage:', substageId);
            const timestamp = new Date().getTime();
            const response = await fetch(`dashboard/handlers/fetch_substage_files.php?substage_id=${substageId}&t=${timestamp}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'include'
            });

            const responseText = await response.text();
            console.log('Raw API Response:', responseText);

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                
                throw new Error('Invalid JSON response');
            }

            console.log('Parsed Substage Files Data:', data);

            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch files');
            }

            return Array.isArray(data.data) ? data.data : [];
        } catch (error) {
            console.error('Error in fetchSubstageFiles:', error);
            return [];
        }
    }

    initializeFilters() {
        this.initializeYearFilter();
        this.initializeMonthFilter();
        this.setupClickOutside();
    }

    initializeYearFilter() {
        const yearFilter = document.getElementById('yearFilter');
        const yearDropdown = document.getElementById('yearDropdown');

        if (yearFilter && yearDropdown) {
            yearFilter.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Close month dropdown if open
                document.getElementById('monthDropdown').style.display = 'none';
                
                // Toggle year dropdown
                yearDropdown.style.cssText = yearDropdown.style.display === 'block' ? 
                    'display: none;' : `
                    display: block;
                    position: absolute;
                    top: ${yearFilter.offsetHeight + 5}px;
                    left: 0;
                    z-index: 99999;
                `;
            });

            // Handle year selection
            const yearOptions = yearDropdown.querySelectorAll('.year-option');
            yearOptions.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.selectedYear = parseInt(option.dataset.year);
                    yearFilter.querySelector('span').textContent = this.selectedYear;
                    
                    yearOptions.forEach(opt => opt.classList.remove('selected'));
                    option.classList.add('selected');
                    
                    yearDropdown.style.display = 'none';
                    this.filterTasks();
                });
            });
        }
    }

    initializeMonthFilter() {
        const monthFilter = document.getElementById('monthFilter');
        const monthDropdown = document.getElementById('monthDropdown');

        if (monthFilter && monthDropdown) {
            monthFilter.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Close year dropdown if open
                document.getElementById('yearDropdown').style.display = 'none';
                
                // Toggle month dropdown
                monthDropdown.style.cssText = monthDropdown.style.display === 'block' ? 
                    'display: none;' : `
                    display: block;
                    position: absolute;
                    top: ${monthFilter.offsetHeight + 5}px;
                    left: 0;
                    z-index: 99999;
                `;
            });

            // Handle month selection
            const monthOptions = monthDropdown.querySelectorAll('.month-option');
            monthOptions.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.selectedMonth = option.dataset.month === 'all' ? 'all' : parseInt(option.dataset.month);
                    monthFilter.querySelector('span').textContent = option.textContent;
                    
                    monthOptions.forEach(opt => opt.classList.remove('selected'));
                    option.classList.add('selected');
                    
                    monthDropdown.style.display = 'none';
                    this.filterTasks();
                });
            });
        }
    }

    setupClickOutside() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.year-filter') && !e.target.closest('.month-filter')) {
                document.getElementById('yearDropdown').style.display = 'none';
                document.getElementById('monthDropdown').style.display = 'none';
            }
        });
    }

    filterTasks() {
        console.log('Filtering tasks with:', {
            selectedYear: this.selectedYear,
            selectedMonth: this.selectedMonth
        });

        const cards = document.querySelectorAll('.kanban-card');
        console.log('Total cards found:', cards.length);

        cards.forEach(card => {
            const dateElement = card.querySelector('.meta-date');
            if (!dateElement) {
                console.log('No date element found for card:', card);
                return;
            }

            // Extract the date from the "Due: Month Day" format
            const dateText = dateElement.textContent.trim();
            console.log('Raw date text:', dateText);

            // Parse the date from "Due: Feb 14" format
            const [_, month, day] = dateText.match(/Due: (\w+)\s+(\d+)/) || [];
            if (!month || !day) {
                console.log('Could not parse date:', dateText);
                return;
            }

            // Convert month name to number (0-11)
            const monthNames = {
                'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
                'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11
            };
            const cardMonth = monthNames[month];
            const cardYear = 2001; // Hardcoded year since all tasks seem to be from 2001

            console.log('Card date details:', {
                month: month,
                day: day,
                cardMonth: cardMonth,
                cardYear: cardYear
            });

            const yearMatch = cardYear === parseInt(this.selectedYear);
            const monthMatch = this.selectedMonth === 'all' || cardMonth === parseInt(this.selectedMonth);

            console.log('Match results:', {
                yearMatch,
                monthMatch,
                shouldShow: yearMatch && monthMatch
            });

            card.style.display = yearMatch && monthMatch ? 'block' : 'none';
        });
    }

    // Add this method for future implementation
    initializeForwardedTasks() {
        // Placeholder for future forwarded tasks functionality
        const container = document.querySelector('.forwarded-tasks-container');
        if (container) {
            container.innerHTML = 'Forwarded tasks will appear here';
        }
    }

    async handleForward(e) {
        try {
            e.preventDefault();
            
            const stageElement = e.target.closest('.stage-item');
            const substageElement = e.target.closest('.substage-item');
            
            // Try multiple possible parent containers
            const projectContainer = e.target.closest('[data-project-id], .project-details, .project-item, .project');
            
            // Debug logs
            console.log('Elements found:', {
                target: e.target,
                stageElement,
                substageElement,
                projectContainer,
                parentElements: e.target.parentElement ? Array.from(e.target.parentElement.classList) : []
            });

            // If project container not found, try to get project ID from stage or substage
            let projectId;
            if (projectContainer && projectContainer.dataset.projectId) {
                projectId = parseInt(projectContainer.dataset.projectId, 10);
            } else if (stageElement && stageElement.dataset.projectId) {
                projectId = parseInt(stageElement.dataset.projectId, 10);
            } else if (substageElement && substageElement.dataset.projectId) {
                projectId = parseInt(substageElement.dataset.projectId, 10);
            }

            if (!projectId || isNaN(projectId)) {
                // Log the HTML structure for debugging
                console.error('Project ID not found. HTML structure:', e.target.closest('.kanban-board').innerHTML);
                throw new Error('Project ID not found. Please check the HTML structure.');
            }

            let itemData = {
                type: substageElement ? 'substage' : 'stage',
                projectId: projectId
            };

            if (substageElement) {
                // Handle substage forward
                const substageId = parseInt(substageElement.dataset.substageId, 10);
                if (isNaN(substageId)) {
                    throw new Error('Invalid substage ID');
                }
                
                console.log('Fetching substage details:', { substageId, projectId });
                
                const response = await fetch(`dashboard/handlers/get_substage_details.php?substage_id=${substageId}&project_id=${projectId}`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to fetch substage details');
                }

                itemData = {
                    ...itemData,
                    id: substageId,
                    stageId: data.substage.stage_id,
                    title: data.substage.title || `Substage ${data.substage.substage_number}`,
                    currentAssignee: data.substage.assigned_to,
                    projectTitle: data.project.title
                };

            } else if (stageElement) {
                // Handle stage forward
                const stageId = parseInt(stageElement.dataset.stageId, 10);
                if (isNaN(stageId)) {
                    throw new Error('Invalid stage ID');
                }
                
                console.log('Fetching stage details:', { stageId, projectId });
                
                const response = await fetch(`dashboard/handlers/get_stage_details.php?stage_id=${stageId}&project_id=${projectId}`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to fetch stage details');
                }

                itemData = {
                    ...itemData,
                    id: stageId,
                    title: `Stage ${data.stage.stage_number}`,
                    currentAssignee: data.stage.assigned_to,
                    projectTitle: data.project.title
                };
            }

            // Log the final itemData for debugging
            console.log('Final itemData:', itemData);

            // Validate the data before proceeding
            if (!itemData.id || !itemData.projectId) {
                throw new Error('Missing required data');
            }

            await this.showForwardDialog(itemData);

        } catch (error) {
            console.error('Error in handleForward:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to process forward request'
            });
        }
    }

    async handleForwardSubmission(type, id, projectId, selectedUsers) {
        try {
            // Format the data correctly
            const requestData = {
                type: type,
                id: id,
                projectId: projectId,
                selectedUsers: Array.isArray(selectedUsers) ? selectedUsers : [selectedUsers]
            };

            console.log('Sending data:', requestData);

            const response = await fetch('dashboard/handlers/forward_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(requestData),
                credentials: 'include'
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Server returned ' + response.status);
            }

            const data = await response.json();
            
            if (data.success) {
                // Show success message using your notification system
                if (typeof this.showNotification === 'function') {
                    this.showNotification('Task forwarded successfully', 'success');
                } else {
                    alert('Task forwarded successfully');
                }
                // Refresh the forwarded tasks list if the method exists
                if (typeof this.fetchForwardedTasks === 'function') {
                    await this.fetchForwardedTasks();
                }
                return true;
            } else {
                throw new Error(data.error || 'Unknown error occurred');
            }
        } catch (error) {
            console.error('Error in handleForwardSubmission:', error);
            // Use alert if showNotification is not available
            if (typeof this.showNotification === 'function') {
                this.showNotification('Failed to forward task: ' + error.message, 'error');
            } else {
                alert('Failed to forward task: ' + error.message);
            }
            return false;
        }
    }

    // Update showForwardDialog to handle the data more robustly
    async showForwardDialog(itemData) {
        try {
            // Validate required data
            if (!itemData || !itemData.id || !itemData.projectId || !itemData.title) {
                throw new Error('Missing required data for forward dialog');
            }

            const response = await fetch('get_users_for_forward.php');
            if (!response.ok) {
                throw new Error('Failed to fetch users list');
            }

            const data = await response.json();
            if (!data.success || !data.users) {
                throw new Error(data.message || 'Failed to load users data');
            }

            const userListHTML = this.generateUserListHTML(data.users);

            const result = await Swal.fire({
                title: `Forward ${itemData.type === 'substage' ? 'Substage' : 'Stage'}`,
                html: `
                    <div class="forward-dialog">
                        <div class="forward-info">
                            <p class="project-info">Project: ${itemData.projectTitle || 'N/A'}</p>
                            <p>Forward "${itemData.title}" to:</p>
                            ${itemData.currentAssignee ? 
                                `<p class="current-assignee">Currently assigned to: User ID ${itemData.currentAssignee}</p>` 
                                : ''}
                        </div>
                        <div class="managers-list">
                            ${userListHTML}
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Forward',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const selectedUsers = Array.from(document.querySelectorAll('.manager-checkbox:checked'))
                        .map(checkbox => parseInt(checkbox.value));
                    
                    if (selectedUsers.length === 0) {
                        Swal.showValidationMessage('Please select at least one user');
                        return false;
                    }
                    return selectedUsers;
                }
            });

            if (result.isConfirmed && result.value) {
                // Extract only the required data and pass individual parameters
                return await this.handleForwardSubmission(
                    itemData.type,
                    parseInt(itemData.id),
                    parseInt(itemData.projectId),
                    result.value  // This is already an array of user IDs
                );
            }
        } catch (error) {
            console.error('Error in showForwardDialog:', error);
            throw error;
        }
    }

    generateUserListHTML(groupedUsers) {
        if (!groupedUsers || typeof groupedUsers !== 'object') {
            console.error('Invalid users data:', groupedUsers);
            return '<div class="error-message">No users available</div>';
        }

        const defaultProfileImage = 'assets/images/user.png'; // Updated default image path

        return Object.entries(groupedUsers).map(([department, users]) => {
            if (!Array.isArray(users)) {
                console.error('Invalid users array for department:', department, users);
                return '';
            }

            return `
                <div class="manager-department-group">
                    <div class="department-title">${department}</div>
                    <div class="users-grid">
                        ${users.map(user => {
                            if (!user || typeof user !== 'object') {
                                console.error('Invalid user object:', user);
                                return '';
                            }

                            return `
                                <div class="user-item">
                                    <div class="user-checkbox-wrapper">
                                        <input type="checkbox" 
                                               id="user_${user.id}" 
                                               value="${user.id}" 
                                               class="manager-checkbox"
                                               data-employee-id="${user.employeeId || ''}">
                                        <label for="user_${user.id}" class="user-label">
                                            <div class="user-avatar">
                                                <img src="${user.profilePicture || defaultProfileImage}" 
                                                     alt="${user.name || 'User'}" 
                                                     onerror="this.src='${defaultProfileImage}'">
                                            </div>
                                            <div class="user-info">
                                                <div class="user-name">${user.name || 'Unknown User'}</div>
                                                <div class="user-details">
                                                    <span class="user-designation">${user.designation || ''}</span>
                                                    ${user.role ? `<span class="user-role">${user.role}</span>` : ''}
                                                </div>
                                                ${user.employeeId ? `<div class="user-employee-id">ID: ${user.employeeId}</div>` : ''}
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }).join('');
    }

    async fetchForwardedTasks() {
        try {
            const container = document.querySelector('.forwarded-tasks-container');
            if (!container) return;

            // Show loading spinner
            container.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-circle-notch fa-spin"></i>
                </div>
            `;

            const response = await fetch('handlers/get_forwarded_tasks.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });

            const data = await response.json();
            
            if (data.success) {
                this.renderForwardedTasks(data.tasks);
            } else {
                throw new Error(data.message || 'Failed to fetch forwarded tasks');
            }
        } catch (error) {
            console.error('Error fetching forwarded tasks:', error);
            this.showForwardedTasksError();
        }
    }

    renderForwardedTasks(tasks) {
        const container = document.querySelector('.forwarded-tasks-container');
        if (!container) return;

        if (!tasks || tasks.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No forwarded tasks</p>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="forwarded-tasks-list">
                ${tasks.map(task => this.createForwardedTaskHTML(task)).join('')}
            </div>
        `;
    }

    showForwardedTasksError() {
        const container = document.querySelector('.forwarded-tasks-container');
        if (container) {
            container.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Failed to load forwarded tasks. Please try again.</p>
                </div>
            `;
        }
    }

    async viewTaskDetails(taskId, taskType) {
        try {
            // Show loading state
            Swal.fire({
                title: 'Loading...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Fetch task details using the new endpoint
            const response = await fetch(`dashboard/handlers/get_forwarded_task_details.php?task_id=${taskId}&task_type=${taskType}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });

            const data = await response.json();
            
            if (data.success) {
                // Create detailed popup content based on task type
                let detailsHtml = `
                    <div class="task-details-popup">
                        <div class="task-header">
                            <div class="project-info">
                                <h3 class="project-title">${data.project.title}</h3>
                                <span class="project-status ${data.project.status.toLowerCase()}">${data.project.status}</span>
                            </div>
                        </div>
                        
                        <div class="task-info">
                            <div class="info-group">
                                <label>Project Timeline:</label>
                                <p>${this.formatDate(data.project.start_date)} - ${this.formatDate(data.project.end_date)}</p>
                            </div>
                            <div class="info-group">
                                <label>Assigned To:</label>
                                <p>${data.project.assigned_to}</p>
                            </div>`;

                // Add substage details if available
                if (data.substage) {
                    detailsHtml += `
                        <div class="substage-details">
                            <h4>Substage Details</h4>
                            <div class="info-group">
                                <label>Title:</label>
                                <p>${data.substage.title}</p>
                            </div>
                            <div class="info-group">
                                <label>Substage Number:</label>
                                <p>${data.substage.substage_number}</p>
                            </div>
                            <div class="info-group">
                                <label>Status:</label>
                                <span class="substage-status ${data.substage.status.toLowerCase()}">${data.substage.status}</span>
                            </div>
                            <div class="info-group">
                                <label>Timeline:</label>
                                <p>${this.formatDate(data.substage.start_date)} - ${this.formatDate(data.substage.end_date)}</p>
                            </div>
                        </div>`;
                }

                detailsHtml += `
                        </div>
                        <div class="task-actions">
                            <button onclick="taskManager.goToProject(${taskId})" class="action-btn view-project">
                                <i class="fas fa-external-link-alt"></i> View Project
                            </button>
                        </div>
                    </div>`;

                // Show the popup with details
                Swal.fire({
                    title: taskType === 'substage' ? 'Substage Details' : 'Project Details',
                    html: detailsHtml,
                    width: '600px',
                    showConfirmButton: false,
                    showCloseButton: true,
                    customClass: {
                        popup: 'task-details-modal'
                    }
                });
            } else {
                throw new Error(data.message || 'Failed to fetch task details');
            }
        } catch (error) {
            console.error('Error viewing task details:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load task details. Please try again.'
            });
        }
    }

    // Add this helper method to format dates nicely
    formatDate(dateString) {
        if (!dateString) return 'Not set';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    showTaskDetailsPopup(task) {
        Swal.fire({
            title: 'Task Details',
            html: `
                <div class="task-details-popup">
                    <div class="task-header">
                        <div class="task-type ${task.type.toLowerCase()}">${task.type}</div>
                        <div class="task-status ${task.status.toLowerCase()}">${task.status}</div>
                    </div>
                    <h3 class="task-title">${task.project_title}</h3>
                    <div class="task-info">
                        <p><strong>${task.type}:</strong> ${task.type === 'stage' ? task.stage_title : task.substage_title}</p>
                        <p><strong>Forwarded By:</strong> ${task.forwarded_by_name}</p>
                        <p><strong>Date:</strong> ${this.formatDate(task.created_at)}</p>
                        <p><strong>Description:</strong> ${task.description || 'No description available'}</p>
                    </div>
                    ${this.generateTaskActions(task)}
                </div>
            `,
            width: '600px',
            showConfirmButton: false,
            showCloseButton: true,
            customClass: {
                popup: 'task-details-popup'
            }
        });
    }

    generateTaskActions(task) {
        // Add any task-specific actions here
        return `
            <div class="task-action-buttons">
                <button onclick="taskManager.goToProject(${task.project_id})" class="action-btn view-project">
                    <i class="fas fa-external-link-alt"></i> Go to Project
                </button>
            </div>
        `;
    }

    goToProject(projectId) {
        window.location.href = `project_details.php?id=${projectId}`;
    }
}

// Initialize the TaskOverviewManager
document.addEventListener('DOMContentLoaded', function() {
    window.taskManager = new TaskOverviewManager();
    // Fetch forwarded tasks when the page loads
    taskManager.fetchForwardedTasks();
});

// Add refresh button functionality
document.querySelector('.refresh-btn')?.addEventListener('click', () => {
    taskManager.fetchForwardedTasks();
});

// Initialize when DOM is ready
const taskManager = new TaskOverviewManager();

// Update the file action functions
window.viewFile = async (fileId, filePath) => {
    try {
        const response = await fetch(`dashboard/handlers/view_file.php?file_id=${fileId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include'
        });

        const data = await response.json();
        if (data.success) {
            // Open file in new window/tab
            window.open(data.data.file_path, '_blank');
        } else {
            throw new Error(data.message || 'Failed to view file');
        }
    } catch (error) {
        console.error('Error viewing file:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to view file. Please try again.'
        });
    }
};

window.downloadFile = async (fileId, filePath) => {
    try {
        window.location.href = `dashboard/handlers/download_file.php?file_id=${fileId}`;
    } catch (error) {
        console.error('Error downloading file:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to download file. Please try again.'
        });
    }
};

window.sendFile = async (fileId) => {
    try {
        // First fetch the managers
        const managersResponse = await fetch('dashboard/handlers/get_managers.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include'
        });

        const managersData = await managersResponse.json();
        
        if (!managersData.success) {
            throw new Error(managersData.message || 'Failed to fetch managers');
        }

        // Group managers by role
        const managersByRole = managersData.managers.reduce((acc, manager) => {
            if (!acc[manager.role]) {
                acc[manager.role] = [];
            }
            acc[manager.role].push(manager);
            return acc;
        }, {});

        // Create the managers list HTML with role grouping
        const managersListHtml = Object.entries(managersByRole).map(([role, managers]) => `
            <div class="manager-role-group">
                <div class="role-title">${role}</div>
                ${managers.map(manager => `
                    <div class="manager-item">
                        <input type="checkbox" 
                               id="manager_${manager.id}" 
                               value="${manager.id}" 
                               class="manager-checkbox">
                        <label for="manager_${manager.id}">${manager.username}</label>
                    </div>
                `).join('')}
            </div>
        `).join('');

        // Show the popup using SweetAlert2
        const result = await Swal.fire({
            title: 'Send File to Managers',
            html: `
                <div class="managers-list">
                    ${managersListHtml}
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Send',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                const selectedManagers = Array.from(document.querySelectorAll('.manager-checkbox:checked'))
                    .map(checkbox => checkbox.value);
                if (selectedManagers.length === 0) {
                    Swal.showValidationMessage('Please select at least one manager');
                    return false;
                }
                return selectedManagers;
            }
        });

        if (result.isConfirmed) {
            const response = await fetch('dashboard/handlers/send_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    file_id: fileId,
                    manager_ids: result.value
                }),
                credentials: 'include'
            });

            const data = await response.json();
            if (data.success) {
                // Update the file row appearance
                const fileRow = document.querySelector(`tr[data-file-id="${fileId}"]`);
                if (fileRow) {
                    fileRow.classList.add('file-sent-for-approval');
                    
                    // Update file status cell
                    const statusCell = fileRow.querySelector('td:nth-child(3)');
                    if (statusCell) {
                        statusCell.innerHTML = `
                            <span class="status-badge status-sent_for_approval">
                                <i class="fas fa-paper-plane"></i>
                                Sent for Approval
                            </span>
                        `;
                    }

                    // Update the main substage status badge at the top
                    const substageHeader = fileRow.closest('.substage-details')
                        .previousElementSibling;
                    const statusBadge = substageHeader.querySelector('.status-badge');
                    if (statusBadge) {
                        // Remove existing status classes
                        statusBadge.classList.remove('In Progress');
                        // Add new status class
                        statusBadge.className = 'status-badge status-sent_for_approval';
                        statusBadge.innerHTML = `
                            <i class="fas fa-paper-plane"></i>
                            Send For Approval
                        `;
                    }

                    // Also update the dropdown if it exists
                    const statusDropdown = substageHeader.querySelector('select');
                    if (statusDropdown) {
                        statusDropdown.value = 'sent_for_approval';
                        
                        // Create and dispatch a change event
                        const event = new Event('change', { bubbles: true });
                        statusDropdown.dispatchEvent(event);
                    }
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'File sent successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error(data.message || 'Failed to send file');
            }
        }
    } catch (error) {
        console.error('Error sending file:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to send file. Please try again.'
        });
    }
};

window.approveFile = async (fileId) => {
    console.log('=== START MANAGER APPROVE FILE PROCESS ===');
    console.log('FileID:', fileId);
    
    try {
        const taskManager = window.taskOverviewManager;
        if (!taskManager) {
            throw new Error('TaskOverviewManager instance not found');
        }

        const fileRow = document.querySelector(`tr[data-file-id="${fileId}"]`);
        console.log('File row found:', !!fileRow);
        
        const substageItem = fileRow?.closest('.substage-item');
        console.log('Substage item found:', !!substageItem);
        
        const substageId = substageItem?.dataset?.substageId;
        console.log('Substage ID:', substageId);

        // Disable both buttons immediately
        const buttons = fileRow.querySelectorAll('.action-btn');
        buttons.forEach(btn => {
            btn.disabled = true;
        });

        // Manager approval API call
        const response = await fetch('dashboard/handlers/approve_file.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ 
                file_id: fileId,
                substage_id: substageId,
                action: 'manager_approve'
            }),
            credentials: 'include'
        });

        const data = await response.json();
        console.log('Manager approval response:', data);

        if (data.success) {
            // Update the file status to approved
            if (fileRow) {
                const statusCell = fileRow.querySelector('.status-column');
                if (statusCell) {
                    statusCell.innerHTML = `
                        <span class="status-badge status-approved">
                            ${taskManager.getStatusIcon('approved')}
                            Approved
                        </span>
                    `;
                }

                // Check if all files in the substage are approved
                const allFiles = substageItem?.querySelectorAll('tr[data-file-id]') || [];
                console.log('Found files in substage:', allFiles.length);

                let approvedCount = 0;
                allFiles.forEach(row => {
                    const status = row.querySelector('.status-column')?.textContent?.trim();
                    console.log('File status found:', status);
                    if (status?.includes('Approved')) {
                        approvedCount++;
                    }
                });

                console.log(`Approved files: ${approvedCount} out of ${allFiles.length}`);
                const allApproved = approvedCount === allFiles.length;
                console.log('All files approved?', allApproved);

                if (allApproved) {
                    console.log('All files are approved, updating substage status...');
                    
                    // Update the substage status in the UI first
                    const substageHeader = substageItem.querySelector('.substage-header');
                    console.log('Found substage header:', !!substageHeader);

                    if (substageHeader) {
                        // Update the status dropdown if it exists
                        const statusDropdown = substageHeader.querySelector('select');
                        console.log('Found status dropdown:', !!statusDropdown);
                        if (statusDropdown) {
                            statusDropdown.value = 'completed';
                        }

                        // Update the status badge
                        const statusBadge = substageHeader.querySelector('.substage-status');
                        console.log('Found status badge:', !!statusBadge);
                        if (statusBadge) {
                            statusBadge.className = 'substage-status status-completed';
                            statusBadge.innerHTML = `${taskManager.getStatusIcon('completed')} Completed`;
                        }

                        // Update any "In Review" text
                        const statusText = substageHeader.querySelector('.status-text');
                        if (statusText) {
                            statusText.textContent = 'Completed';
                        }
                    }

                    // Now update the status in the database
                    try {
                        const statusResponse = await fetch('dashboard/handlers/update_substage_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                substage_id: substageId,
                                status: 'completed'
                            }),
                            credentials: 'include'
                        });

                        const statusData = await statusResponse.json();
                        console.log('Status update response:', statusData);

                        if (statusData.success) {
                            console.log('Successfully updated substage status to completed');
                            
                            // Refresh the page after a short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            console.error('Failed to update substage status:', statusData);
                        }
                    } catch (error) {
                        console.error('Error updating substage status:', error);
                    }
                }
            }

            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'File approved successfully',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            throw new Error(data.message || 'Failed to approve file');
        }
    } catch (error) {
        console.error('Error in manager approve process:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Failed to approve file. Please try again.'
        });
    }
};

window.rejectFile = async (fileId) => {
    try {
        const response = await fetch('dashboard/handlers/reject_file.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ file_id: fileId }),
            credentials: 'include'
        });

        const data = await response.json();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'File rejected successfully',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            throw new Error(data.message || 'Failed to reject file');
        }
    } catch (error) {
        console.error('Error rejecting file:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to reject file. Please try again.'
        });
    }
};

function updateFileStatus(fileId, action) {
    fetch('handlers/update_file_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            file_id: fileId,
            action: action
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the main status dropdown in the header
            const statusDropdown = document.querySelector('.status-dropdown select, .status-dropdown button');
            if (statusDropdown && data.all_files_approved) {
                if (statusDropdown.tagName === 'SELECT') {
                    statusDropdown.value = 'completed';
                } else {
                    statusDropdown.textContent = 'Completed';
                }
            }

            // Force update the visible status in the UI
            const statusElements = document.querySelectorAll(`[data-substage-id="${data.substage_id}"] .status-text, 
                                                           [data-substage-id="${data.substage_id}"] .substage-status`);
            statusElements.forEach(element => {
                if (data.all_files_approved) {
                    element.textContent = 'Completed';
                    element.className = element.className.replace('in-review', 'completed');
                }
            });

            // Update the dropdown if it exists
            const dropdown = document.querySelector('.status-dropdown .dropdown-toggle');
            if (dropdown && data.all_files_approved) {
                dropdown.textContent = 'Completed';
            }

            // Refresh the page if needed (optional)
            // if (data.all_files_approved) {
            //     location.reload();
            // }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Add this to ensure the status is checked on page load
document.addEventListener('DOMContentLoaded', function() {
    const substages = document.querySelectorAll('[data-substage-id]');
    substages.forEach(substage => {
        const substageId = substage.dataset.substageId;
        const fileStatuses = substage.querySelectorAll('.status-badge');
        const allApproved = Array.from(fileStatuses).every(status => 
            status.textContent.trim().toLowerCase() === 'approved'
        );
        
        if (allApproved && fileStatuses.length > 0) {
            const statusElements = substage.querySelectorAll('.status-text, .substage-status');
            statusElements.forEach(element => {
                element.textContent = 'Completed';
                element.className = element.className.replace('in-review', 'completed');
            });
            
            const dropdown = document.querySelector('.status-dropdown .dropdown-toggle');
            if (dropdown) {
                dropdown.textContent = 'Completed';
            }
        }
    });
});

function renderFileActions(file) {
    return `
        <button class="action-btn approve-btn" title="${file.status === 'approved' || file.status === 'rejected' ? 'Action not available' : 'Approve'}" 
                onclick="approveFile('${file.id}')"
                ${file.status === 'approved' || file.status === 'rejected' ? 'disabled' : ''}>
            <i class="fas fa-check"></i>
        </button>
        <button class="action-btn reject-btn" title="${file.status === 'approved' || file.status === 'rejected' ? 'Action not available' : 'Reject'}" 
                onclick="rejectFile('${file.id}')"
                ${file.status === 'approved' || file.status === 'rejected' ? 'disabled' : ''}>
            <i class="fas fa-times"></i>
        </button>
    `;
}

// Update the approveFile function
function approveFile(fileId) {
    // Disable both buttons immediately
    const fileRow = document.querySelector(`tr[data-file-id="${fileId}"]`);
    const buttons = fileRow.querySelectorAll('.action-btn');
    buttons.forEach(btn => {
        btn.disabled = true;
    });

    updateFileStatus(fileId, 'approve');
}

// Update the rejectFile function
function rejectFile(fileId) {
    // Disable both buttons immediately
    const fileRow = document.querySelector(`tr[data-file-id="${fileId}"]`);
    const buttons = fileRow.querySelectorAll('.action-btn');
    buttons.forEach(btn => {
        btn.disabled = true;
    });

    updateFileStatus(fileId, 'reject');
}

// Add CSS for disabled button styling and tooltip
const style = document.createElement('style');
style.textContent = `
    .action-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: all !important; /* Enable hover effects even when disabled */
    }

    .action-btn:disabled:hover {
        position: relative;
    }

    .action-btn:disabled:hover::after {
        content: attr(title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        padding: 5px 10px;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 1000;
        margin-bottom: 5px;
    }

    /* Add a small arrow to the tooltip */
    .action-btn:disabled:hover::before {
        content: '';
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: rgba(0, 0, 0, 0.8);
        margin-bottom: -5px;
    }
`;
document.head.appendChild(style); 