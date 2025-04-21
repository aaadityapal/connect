class ProjectOverview {
    constructor() {
        this.initializeOverview();
        this.initializeFilters();
        this.initializeViewToggle();
        this.initializeCalendar();
        this.currentDate = new Date();
        this.userProjects = [];
        this.loadAnimateCSS();
        this.fetchUserProjects();
    }

    initializeOverview() {
        // Initialize any event listeners or dynamic functionality
        this.setupCardAnimations();
        this.initializeDataRefresh();
        this.setupProjectTooltip();
        this.setupStageTooltip();
        this.setupSubstageTooltip();
        this.setupStagesDueTooltip();
        this.setupSubstagesDueTooltip();
    }

    initializeFilters() {
        // Setup year filter
        const yearFilter = document.getElementById('overviewYearFilter');
        const yearDropdown = document.getElementById('overviewYearDropdown');
        
        // Setup month filter
        const monthFilter = document.getElementById('overviewMonthFilter');
        const monthDropdown = document.getElementById('overviewMonthDropdown');

        // Toggle dropdowns
        yearFilter?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown(yearDropdown);
            monthDropdown.style.display = 'none';
        });

        monthFilter?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown(monthDropdown);
            yearDropdown.style.display = 'none';
        });

        // Handle option selection
        document.querySelectorAll('.overview-filter-option').forEach(option => {
            option.addEventListener('click', (e) => {
                const value = e.target.dataset.value;
                const filterType = e.target.closest('.overview-filter-wrapper').querySelector('.overview-filter');
                
                // Update display text
                filterType.querySelector('span').textContent = e.target.textContent;
                
                // Update selected state
                e.target.parentElement.querySelectorAll('.overview-filter-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                e.target.classList.add('selected');
                
                // Hide dropdown
                e.target.closest('.overview-filter-dropdown').style.display = 'none';
                
                // Refresh data with new filters
                this.refreshOverviewData();
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            yearDropdown.style.display = 'none';
            monthDropdown.style.display = 'none';
        });

        // Prevent dropdown from closing when clicking inside
        yearDropdown?.addEventListener('click', (e) => e.stopPropagation());
        monthDropdown?.addEventListener('click', (e) => e.stopPropagation());
    }

    toggleDropdown(dropdown) {
        if (!dropdown) return;
        const isVisible = dropdown.style.display === 'block';
        dropdown.style.display = isVisible ? 'none' : 'block';
    }

    setupCardAnimations() {
        const cards = document.querySelectorAll('.overview-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    }

    initializeDataRefresh() {
        // Refresh data every 5 minutes
        setInterval(() => {
            this.refreshOverviewData();
        }, 300000);
    }

    async refreshOverviewData() {
        try {
            const yearFilter = document.getElementById('overviewYearFilter');
            const monthFilter = document.getElementById('overviewMonthFilter');
            
            const year = yearFilter?.querySelector('span').textContent;
            const month = monthFilter?.querySelector('span').textContent;

            const response = await fetch('get_project_overview.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    year: year,
                    month: month
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.updateCards(data.overview);
            }
        } catch (error) {
            console.error('Error refreshing project overview data:', error);
        }
    }

    updateCards(data) {
        // Update each card with new data
        Object.keys(data).forEach(cardId => {
            const card = document.getElementById(cardId);
            if (card) {
                const valueElement = card.querySelector('.card-value');
                const trendElement = card.querySelector('.trend-indicator');
                
                if (valueElement) {
                    valueElement.textContent = data[cardId].value;
                }
                
                if (trendElement) {
                    trendElement.textContent = data[cardId].trend;
                    this.updateTrendClass(trendElement, data[cardId].trend_direction);
                }
            }
        });
    }

    updateTrendClass(element, direction) {
        element.classList.remove('trend-up', 'trend-down', 'trend-neutral');
        element.classList.add(`trend-${direction}`);
    }

    // Helper method to format numbers
    formatNumber(number) {
        return new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 1,
            notation: 'compact',
            compactDisplay: 'short'
        }).format(number);
    }

    // Helper method to calculate percentage change
    calculatePercentageChange(current, previous) {
        if (previous === 0) return 100;
        return ((current - previous) / previous) * 100;
    }

    initializeViewToggle() {
        const toggleLabels = document.querySelectorAll('.view-toggle-label');
        const container = document.getElementById('projectOverviewSection');

        toggleLabels.forEach(label => {
            label.addEventListener('click', () => {
                // Update toggle state
                toggleLabels.forEach(l => l.classList.remove('active'));
                label.classList.add('active');

                // Update view
                const view = label.dataset.view;
                container.className = `project-overview-section ${view}-view-active`;

                if (view === 'calendar') {
                    this.renderCalendar();
                }
            });
        });
    }

    initializeCalendar() {
        const prevBtn = document.getElementById('prevMonth');
        const nextBtn = document.getElementById('nextMonth');

        prevBtn?.addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.renderCalendar();
        });

        nextBtn?.addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.renderCalendar();
        });
    }

    renderCalendar() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        // Debug data integrity
        console.log(`Rendering calendar for ${month+1}/${year}`);
        console.log(`User projects data available: ${this.userProjects.length > 0}`);
        
        // Check if any stages/substages exist
        let hasStages = false;
        let hasSubstages = false;
        
        this.userProjects.forEach(project => {
            if (project.stages && project.stages.length > 0) {
                hasStages = true;
                project.stages.forEach(stage => {
                    if (stage.substages && stage.substages.length > 0) {
                        hasSubstages = true;
                    }
                });
            }
        });
        
        console.log(`Data has stages: ${hasStages}, has substages: ${hasSubstages}`);
        
        // Update calendar title
        const titleEl = document.getElementById('calendarTitle');
        if (titleEl) {
            titleEl.textContent = new Date(year, month, 1).toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });
        }

        // Get first day of month and total days
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const lastDayPrevMonth = new Date(year, month, 0).getDate();
        
        // Generate calendar days
        const daysContainer = document.getElementById('calendarDays');
        if (!daysContainer) {
            console.error('Calendar days container not found!');
            return;
        }

        let daysHtml = '';
        
        // Add empty cells for days before the first day of month
        for (let i = firstDay - 1; i >= 0; i--) {
            const prevMonthDay = lastDayPrevMonth - i;
            daysHtml += `
                <div class="calendar-day empty">
                    <div class="calendar-day-number">${prevMonthDay}</div>
                </div>`;
        }

        // Get today's date for comparison
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Add days of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const isToday = this.isToday(year, month, day);
            const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            // Check for events and overdue projects
            const hasEvents = this.checkForEvents(year, month, day);
            
            // Get counts of projects, stages, and substages
            const dayCounts = this.getDayCounts(year, month, day);
            
            // Check for overdue projects
            const currentDate = new Date(year, month, day);
            const hasOverdue = currentDate < today && hasEvents;

            // For today's date, check if there are piled up overdue items
            let overdueCounter = '';
            let hasOverdueItems = false;
            
            if (isToday) {
                // Count overdue projects, stages and substages
                let overdueCount = 0;
                
                this.userProjects.forEach(project => {
                    // Check overdue projects
                    const projectEndDate = project.end_date ? new Date(project.end_date.split(' ')[0]) : null;
                    if (projectEndDate && projectEndDate < today && 
                        (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress')) {
                        overdueCount++;
                    }
                    
                    // Check overdue stages
                    project.stages?.forEach(stage => {
                        const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                        if (stageEndDate && stageEndDate < today && 
                            (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress')) {
                            overdueCount++;
                        }
                        
                        // Check overdue substages
                        stage.substages?.forEach(substage => {
                            const substageEndDate = substage.end_date ? new Date(substage.end_date.split(' ')[0]) : null;
                            if (substageEndDate && substageEndDate < today && 
                                (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress')) {
                                overdueCount++;
                            }
                        });
                    });
                });
                
                if (overdueCount > 0) {
                    hasOverdueItems = true;
                    overdueCounter = `<div class="overdue-pileup-counter"><i class="fas fa-exclamation-triangle"></i>${overdueCount} overdue</div>`;
                }
            }

            // Add debug class for days with content
            const debugClass = (dayCounts.projects > 0 || dayCounts.stages > 0 || dayCounts.substages > 0) ? ' debug-has-content' : '';
            
            daysHtml += `
                <div class="calendar-day${isToday ? ' today' : ''}${hasEvents ? ' has-events' : ''}${hasOverdue ? ' has-overdue' : ''}${hasOverdueItems ? ' has-overdue-items' : ''}${debugClass}" 
                     data-date="${date}">
                    <div class="calendar-day-number">${day}</div>
                    ${this.getEventsSummary(year, month, day)}
                    ${this.generateCountTags(dayCounts)}
                    ${overdueCounter}
                </div>
            `;
        }

        // Add empty cells for the next month to complete the grid
        const totalDays = firstDay + daysInMonth;
        const remainingDays = Math.ceil(totalDays / 7) * 7 - totalDays;
        
        for (let day = 1; day <= remainingDays; day++) {
            daysHtml += `
                <div class="calendar-day empty">
                    <div class="calendar-day-number">${day}</div>
                </div>`;
        }

        daysContainer.innerHTML = daysHtml;
        console.log('Calendar days rendered');

        // Add click handlers for days and "more" links
        document.querySelectorAll('.calendar-day:not(.empty)').forEach(day => {
            day.addEventListener('click', (e) => {
                // For all calendar day clicks, show the day preview modal instead of the full modal
                // Don't trigger the click handler if clicking specifically on the "more" link (it has its own handler)
                if (!e.target.closest('.more-items-link')) {
                    e.stopPropagation();
                    this.showDayItemsPreview(day.dataset.date);
                }
            });
        });
        
        // Add specific click handlers for "more" links
        document.querySelectorAll('.more-items-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.stopPropagation();
                this.showDayItemsPreview(link.dataset.date);
            });
        });
        
        // Check for overflow in event containers and add indicator class
        document.querySelectorAll('.calendar-day-events').forEach(container => {
            if (container.scrollHeight > container.clientHeight) {
                container.classList.add('has-overflow');
            } else {
                container.classList.remove('has-overflow');
            }
        });

        // Add CSS for debug class if missing
        const styleElement = document.getElementById('project-overview-debug-styles');
        if (!styleElement) {
            const style = document.createElement('style');
            style.id = 'project-overview-debug-styles';
            style.innerHTML = `
                .calendar-day.debug-expected-content {
                    border: 2px dashed red !important;
                }
                .date-label-2025-04-19 {
                    position: relative;
                }
                .date-label-2025-04-19::after {
                    content: "Expected!";
                    position: absolute;
                    bottom: -15px;
                    left: 0;
                    font-size: 10px;
                    color: red;
                }
            `;
            document.head.appendChild(style);
        }
    }

    generateCountTags(counts) {
        if (!counts || (counts.projects === 0 && counts.stages === 0 && counts.substages === 0)) {
            return '';
        }
        
        let tagsHtml = '<div class="calendar-day-count-tags">';
        
        if (counts.projects > 0) {
            tagsHtml += `<div class="calendar-count-tag proj-count-tag">${counts.projects}${counts.projects === 1 ? ' Proj' : ' Projs'}</div>`;
        }
        
        if (counts.stages > 0) {
            tagsHtml += `<div class="calendar-count-tag stage-count-tag">${counts.stages}${counts.stages === 1 ? ' Stage' : ' Stages'}</div>`;
        }
        
        if (counts.substages > 0) {
            tagsHtml += `<div class="calendar-count-tag substage-count-tag">${counts.substages}${counts.substages === 1 ? ' Sub' : ' Subs'}</div>`;
        }
        
        tagsHtml += '</div>';
        return tagsHtml;
    }

    getDayCounts(year, month, day) {
        const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const currentDate = new Date(year, month, day);
        const isToday = this.isToday(year, month, day);
        const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
        
        // Special debug for this specific day
        if (dateString === '2025-04-19') {
            console.log(`Special date check for 2025-04-19, user ID: ${currentUserId}`);
        }
        
        // On first day of month, log debug info
        if (day === 1) {
            console.log(`Calculating counts for ${month+1}/${year}, User ID: ${currentUserId}`);
            console.log(`Total projects in data: ${this.userProjects.length}`);
        }
        
        let projectCount = 0;
        let stageCount = 0;
        let substageCount = 0;
        
        // Count projects
        this.userProjects.forEach(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            const projectDueDate = projectEndDate ? new Date(projectEndDate) : null;
            const isPastDue = projectDueDate && projectDueDate < today;
            
            // Check if this project should be shown on this day
            const showOnThisDay = 
                // Original due date matches this day
                (projectEndDate === dateString) ||
                // OR it's today's date AND project is past due AND status is pending/not_started
                (isToday && isPastDue && (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress'));
            
            if (showOnThisDay) {
                projectCount++;
            }
            
            // Count stages - only if assigned to the current user (regardless of project assignment)
            project.stages?.forEach(stage => {
                // Check if this stage is assigned to the current user - handle both string and number comparisons
                const stageAssignedTo = stage.assigned_to || '';
                let isStageAssignedToUser = false;
                
                // Handle different formats of assigned_to (single ID, comma-separated list, etc.)
                if (currentUserId) {
                    if (typeof stageAssignedTo === 'string' && stageAssignedTo.includes(',')) {
                        // Comma-separated list of IDs
                        isStageAssignedToUser = stageAssignedTo.split(',').some(id => 
                            id.toString().trim() === currentUserId.toString());
                    } else {
                        // Single ID
                        isStageAssignedToUser = stageAssignedTo.toString() === currentUserId.toString();
                    }
                }
                
                // Special check for date 2025-04-19
                if (dateString === '2025-04-19' && currentUserId == 15) {
                    console.log(`Stage check for 2025-04-19: ID=${stage.id}, assigned_to=${stageAssignedTo}, isAssigned=${isStageAssignedToUser}`);
                }
                    
                if (!isStageAssignedToUser) return false;
                
                const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                // Special debug for date check
                if (currentUserId == 15 && stageEndDate) {
                    console.log(`Stage end date: ${stageEndDate}, Looking for: ${dateString}`);
                    console.log(`Date match: ${stageEndDate === dateString}`);
                }
                
                const stageDueDate = stageEndDate ? new Date(stageEndDate) : null;
                const isStagePastDue = stageDueDate && stageDueDate < today;
                
                const shouldShow = (stageEndDate === dateString) || 
                                  (isToday && isStagePastDue && (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'));
                
                if (shouldShow && currentUserId == 15) {
                    console.log(`Stage should show on ${dateString}: Stage ${stage.stage_number || ''} in project ${project.title}`);
                }
                
                if (shouldShow) {
                    stageCount++;
                    // Log for the specific date we're interested in
                    if (dateString === '2025-04-19') {
                        console.log(`Found stage due on 2025-04-19: Stage ${stage.stage_number || ''} in Project ${project.title}`);
                    }
                }
            });
            
            // Count substages - only if assigned to the current user (regardless of project or stage assignment)
            project.stages?.forEach(stage => {
                stage.substages?.forEach(substage => {
                    // Check if this substage is assigned to the current user - handle both string and number comparisons
                    const substageAssignedTo = substage.assigned_to || '';
                    let isSubstageAssignedToUser = false;
                    
                    // Handle different formats of assigned_to (single ID, comma-separated list, etc.)
                    if (currentUserId) {
                        if (typeof substageAssignedTo === 'string' && substageAssignedTo.includes(',')) {
                            // Comma-separated list of IDs
                            isSubstageAssignedToUser = substageAssignedTo.split(',').some(id => 
                                id.toString().trim() === currentUserId.toString());
                        } else {
                            // Single ID
                            isSubstageAssignedToUser = substageAssignedTo.toString() === currentUserId.toString();
                        }
                    }
                    
                    // Special check for date 2025-04-19
                    if (dateString === '2025-04-19' && currentUserId == 15) {
                        console.log(`Substage check for 2025-04-19: ID=${substage.id}, assigned_to=${substageAssignedTo}, isAssigned=${isSubstageAssignedToUser}`);
                    }
                    
                    if (!isSubstageAssignedToUser) return false;
                    
                    const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                    // Special debug for this specific date
                    if (dateString === '2025-04-19' && currentUserId == 15) {
                        console.log(`Substage end date: ${substageEndDate}, comparing to ${dateString}`);
                    }
                    
                    const substageDueDate = substageEndDate ? new Date(substageEndDate) : null;
                    const isSubstagePastDue = substageDueDate && substageDueDate < today;
                    
                    const shouldShow = (substageEndDate === dateString) || 
                                     (isToday && isSubstagePastDue && (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'));
                    
                    if (shouldShow && currentUserId == 15) {
                        console.log(`Substage should show on ${dateString}: Substage ${substage.title || ''} in project ${project.id}`);
                    }
                    
                    if (shouldShow) {
                        substageCount++;
                        // Log for the specific date we're interested in
                        if (dateString === '2025-04-19') {
                            console.log(`Found substage due on 2025-04-19: ${substage.title || 'Substage'} in Project ${project.title}`);
                        }
                    }
                });
            });
        });
        
        // If we found assigned items on this day, log it
        if (stageCount > 0 || substageCount > 0) {
            console.log(`For day ${dateString}: Found ${projectCount} projects, ${stageCount} stages, ${substageCount} substages`);
        }
        
        return {
            projects: projectCount,
            stages: stageCount,
            substages: substageCount
        };
    }

    isToday(year, month, day) {
        const today = new Date();
        return year === today.getFullYear() && 
               month === today.getMonth() && 
               day === today.getDate();
    }

    checkForEvents(year, month, day) {
        // Check if there are projects or stages due on this day
        const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const currentDate = new Date(year, month, day);
        const isToday = this.isToday(year, month, day);
        const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
        
        console.log(`Checking events for ${dateString}, user ID: ${currentUserId}`);
        
        // Check projects
        const hasProjectsDue = this.userProjects.some(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            const projectDueDate = projectEndDate ? new Date(projectEndDate) : null;
            const isPastDue = projectDueDate && projectDueDate < today;
            
            return (projectEndDate === dateString) || 
                   (isToday && isPastDue && (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress'));
        });
        
        if (hasProjectsDue) return true;
        
        // Check stages - only assigned to current user (regardless of project assignment)
        const hasStagesDue = this.userProjects.some(project => {
            return project.stages?.some(stage => {
                // Check if stage is assigned to current user - handle both string and number comparisons
                const stageAssignedTo = stage.assigned_to || '';
                let isStageAssignedToUser = false;
                
                // Handle different formats of assigned_to (single ID, comma-separated list, etc.)
                if (currentUserId) {
                    if (typeof stageAssignedTo === 'string' && stageAssignedTo.includes(',')) {
                        // Comma-separated list of IDs
                        isStageAssignedToUser = stageAssignedTo.split(',').some(id => 
                            id.toString().trim() === currentUserId.toString());
                    } else {
                        // Single ID
                        isStageAssignedToUser = stageAssignedTo.toString() === currentUserId.toString();
                    }
                }
                
                // Special check for User ID 15 for debugging
                if (currentUserId == 15) {
                    console.log(`Stage assigned_to: ${stageAssignedTo}, isAssigned: ${isStageAssignedToUser}`);
                }
                    
                if (!isStageAssignedToUser) return false;
                
                const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                // Special debug for date check
                if (currentUserId == 15 && stageEndDate) {
                    console.log(`Stage end date: ${stageEndDate}, Looking for: ${dateString}`);
                    console.log(`Date match: ${stageEndDate === dateString}`);
                }
                
                const stageDueDate = stageEndDate ? new Date(stageEndDate) : null;
                const isStagePastDue = stageDueDate && stageDueDate < today;
                
                const shouldShow = (stageEndDate === dateString) || 
                                  (isToday && isStagePastDue && (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'));
                
                if (shouldShow && currentUserId == 15) {
                    console.log(`Stage should show on ${dateString}: Stage ${stage.stage_number || ''} in project ${project.id}`);
                }
                
                return shouldShow;
            });
        });
        
        if (hasStagesDue) return true;
        
        // Check substages - only assigned to current user (regardless of project or stage assignment)
        const hasSubstagesDue = this.userProjects.some(project => {
            return project.stages?.some(stage => {
                return stage.substages?.some(substage => {
                    // Check if substage is assigned to current user - handle both string and number comparisons
                    const substageAssignedTo = substage.assigned_to || '';
                    let isSubstageAssignedToUser = false;
                    
                    // Handle different formats of assigned_to (single ID, comma-separated list, etc.)
                    if (currentUserId) {
                        if (typeof substageAssignedTo === 'string' && substageAssignedTo.includes(',')) {
                            // Comma-separated list of IDs
                            isSubstageAssignedToUser = substageAssignedTo.split(',').some(id => 
                                id.toString().trim() === currentUserId.toString());
                        } else {
                            // Single ID
                            isSubstageAssignedToUser = substageAssignedTo.toString() === currentUserId.toString();
                        }
                    }
                    
                    // Special check for User ID 15 for debugging
                    if (currentUserId == 15) {
                        console.log(`Substage assigned_to: ${substageAssignedTo}, isAssigned: ${isSubstageAssignedToUser}`);
                    }
                        
                    if (!isSubstageAssignedToUser) return false;
                    
                    const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                    // Special debug for date check
                    if (currentUserId == 15 && substageEndDate) {
                        console.log(`Substage end date: ${substageEndDate}, Looking for: ${dateString}`);
                        console.log(`Date match: ${substageEndDate === dateString}`);
                    }
                    
                    const substageDueDate = substageEndDate ? new Date(substageEndDate) : null;
                    const isSubstagePastDue = substageDueDate && substageDueDate < today;
                    
                    const shouldShow = (substageEndDate === dateString) || 
                                     (isToday && isSubstagePastDue && (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'));
                    
                    if (shouldShow && currentUserId == 15) {
                        console.log(`Substage should show on ${dateString}: Substage ${substage.title || ''} in project ${project.id}`);
                    }
                    
                    return shouldShow;
                });
            });
        });
        
        return hasSubstagesDue;
    }

    getEventsSummary(year, month, day) {
        const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const currentDate = new Date(year, month, day);
        const isToday = this.isToday(year, month, day);
        const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
        
        // Find all projects, stages and substages due on this date
        const dueItems = {
            projects: [],
            stages: [],
            substages: []
        };
        
        // Get projects due today - including past due projects shifted to today
        this.userProjects.forEach(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            const projectDueDate = projectEndDate ? new Date(projectEndDate) : null;
            const isPastDue = projectDueDate && projectDueDate < today;
            
            // Include if original due date OR if it's past due and today
            if (projectEndDate === dateString || 
                (isToday && isPastDue && (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress'))) {
                dueItems.projects.push(project);
            }
            
            // Get stages due today - including past due stages shifted to today
            // Show all stages assigned to current user, regardless of project assignment
            project.stages.forEach(stage => {
                // Check if stage is assigned to the current user
                const isStageAssignedToUser = stage.assigned_to && 
                    (currentUserId === null || stage.assigned_to.toString() === currentUserId.toString());
                    
                if (isStageAssignedToUser) {
                    const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                    const stageDueDate = stageEndDate ? new Date(stageEndDate) : null;
                    const isStagePastDue = stageDueDate && stageDueDate < today;
                    
                    if (stageEndDate === dateString || 
                        (isToday && isStagePastDue && (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'))) {
                        dueItems.stages.push({
                            stage: stage,
                            project: project
                        });
                    }
                }
                
                // Get substages due today - including past due substages shifted to today
                // Show all substages assigned to current user, regardless of project or stage assignment
                stage.substages.forEach(substage => {
                    // Check if substage is assigned to the current user
                    const isSubstageAssignedToUser = substage.assigned_to && 
                        (currentUserId === null || substage.assigned_to.toString() === currentUserId.toString());
                        
                    if (isSubstageAssignedToUser) {
                        const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                        const substageDueDate = substageEndDate ? new Date(substageEndDate) : null;
                        const isSubstagePastDue = substageDueDate && substageDueDate < today;
                        
                        if (substageEndDate === dateString || 
                            (isToday && isSubstagePastDue && (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'))) {
                            dueItems.substages.push({
                                substage: substage,
                                stage: stage,
                                project: project
                            });
                        }
                    }
                });
            });
        });
        
        const totalCount = dueItems.projects.length + dueItems.stages.length + dueItems.substages.length;
        if (totalCount === 0) return '';
        
        // Create modified display items that show more specific details
        let displayItems = [];
        
        // Add projects
        dueItems.projects.forEach(project => {
            displayItems.push({
                title: project.title,
                type: 'project',
                priority: 1
            });
        });
        
        // Add stages with more descriptive titles
        dueItems.stages.forEach(item => {
            displayItems.push({
                title: `Stage ${item.stage.stage_number || ''} - ${item.project.title}`,
                type: 'stage',
                priority: 2
            });
        });
        
        // Add substages with more descriptive titles
        dueItems.substages.forEach(item => {
            displayItems.push({
                title: `${item.substage.title || 'Substage'} - ${item.project.title}`,
                type: 'substage',
                priority: 3
            });
        });
        
        // Sort by priority (project > stage > substage)
        displayItems.sort((a, b) => a.priority - b.priority);
        
        // Start with an empty display area
        let summary = `<div class="calendar-day-events">`;
        
        // Show up to 5 items now that we have scrolling capability
        const itemsToDisplay = displayItems.slice(0, 5);
        
        itemsToDisplay.forEach(item => {
            // Add CSS class based on item type
            const itemTypeClass = `calendar-day-event-${item.type}`;
            summary += `<div class="calendar-day-event-item ${itemTypeClass}">${this.truncateText(item.title, 15)}</div>`;
        });
        
        // If there are more items, add a count indicator with data attributes
        if (displayItems.length > 5) {
            summary += `<div class="calendar-day-event-item more-items-link" data-date="${dateString}" data-count="${displayItems.length - 5}">+${displayItems.length - 5} more</div>`;
        }
        
        summary += '</div>';
        
        return summary;
    }

    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }

    handleDayClick(info) {
        const clickedDate = new Date(info);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Check if clicked date is today
        const isToday = clickedDate.getTime() === today.getTime();
        
        // Get current user ID from the global context if available
        const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
        
        // Group all items by project for hierarchical display
        const projectsWithItems = {};
        
        // Get all items due on this date
        this.userProjects.forEach(project => {
            const projectEndDate = project.end_date ? new Date(project.end_date.split(' ')[0]) : null;
            const isProjectDueToday = projectEndDate && projectEndDate.getTime() === clickedDate.getTime();
            const isPastDueProject = projectEndDate && projectEndDate < today;
            const shouldShowProject = isProjectDueToday || 
                                     (isToday && isPastDueProject && 
                                      (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress'));
            
            // Initialize project entry if needed
            if (!projectsWithItems[project.id]) {
                projectsWithItems[project.id] = {
                    project: project,
                    isDueToday: isProjectDueToday,
                    isPastDue: isPastDueProject && (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress'),
                    stages: []
                };
            }
            
            // Add stages and substages - only those assigned to the current user
            project.stages.forEach(stage => {
                // Only include stages assigned to the current user, regardless of project assignment
                const isStageAssignedToUser = stage.assigned_to && 
                    (currentUserId === null || stage.assigned_to.toString() === currentUserId.toString());
                
                // Only process stages assigned to the current user
                if (isStageAssignedToUser) {
                    const stageEndDate = stage.end_date ? new Date(stage.end_date.split(' ')[0]) : null;
                    const isStageDueToday = stageEndDate && stageEndDate.getTime() === clickedDate.getTime();
                    const isPastDueStage = stageEndDate && stageEndDate < today;
                    const shouldShowStage = (isStageDueToday || 
                                           (isToday && isPastDueStage && 
                                            (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress')));
                    
                    if (shouldShowStage) {
                        const stageItem = {
                            stage: stage,
                            isDueToday: isStageDueToday,
                            isPastDue: isPastDueStage && (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'),
                            isAssigned: isStageAssignedToUser,
                            substages: []
                        };
                        
                        // Add substages - only those assigned to the current user
                        stage.substages.forEach(substage => {
                            // Only include substages assigned to the current user, regardless of project or stage assignment
                            const isSubstageAssignedToUser = substage.assigned_to && 
                                (currentUserId === null || substage.assigned_to.toString() === currentUserId.toString());
                                
                            // Only process substages assigned to the current user
                            if (isSubstageAssignedToUser) {
                                const substageEndDate = substage.end_date ? new Date(substage.end_date.split(' ')[0]) : null;
                                const isSubstageDueToday = substageEndDate && substageEndDate.getTime() === clickedDate.getTime();
                                const isPastDueSubstage = substageEndDate && substageEndDate < today;
                                const shouldShowSubstage = (isSubstageDueToday || 
                                                         (isToday && isPastDueSubstage && 
                                                          (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress')));
                                
                                if (shouldShowSubstage) {
                                    stageItem.substages.push({
                                        substage: substage,
                                        isDueToday: isSubstageDueToday,
                                        isPastDue: isPastDueSubstage && (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'),
                                        isAssigned: isSubstageAssignedToUser
                                    });
                                }
                            }
                        });
                        
                        if (stageItem.substages.length > 0 || shouldShowStage) {
                            // Make sure the project entry exists if we're adding a stage but the project wasn't due today
                            if (!projectsWithItems[project.id]) {
                                projectsWithItems[project.id] = {
                                    project: project,
                                    isDueToday: false,
                                    isPastDue: false,
                                    stages: []
                                };
                            }
                            projectsWithItems[project.id].stages.push(stageItem);
                        }
                    }
                }
            });
            
            // Remove project if it has no stages and is not directly due
            if (!shouldShowProject && (!projectsWithItems[project.id] || projectsWithItems[project.id].stages.length === 0)) {
                delete projectsWithItems[project.id];
            }
        });
        
        // Convert to array and filter out empty projects
        const projectsList = Object.values(projectsWithItems).filter(item => 
            item.isDueToday || item.isPastDue || item.stages.length > 0
        );
        
        // Format the date for display
        const formattedDate = clickedDate.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        
        // Create modal content with hierarchical structure
        const modalContent = `
            <div class="pr-calendar-modal">
                <div class="pr-calendar-modal-header">
                    <h3 class="pr-calendar-modal-title">Items Due on ${formattedDate}</h3>
                    ${isToday ? '<div class="pr-calendar-modal-overdue-indicator">Includes overdue items</div>' : ''}
                    <button class="pr-calendar-modal-close">&times;</button>
                </div>
                <div class="pr-calendar-modal-content">
                    ${projectsList.length > 0 ? 
                        `<div class="pr-calendar-hierarchical-list">
                            ${projectsList.map(projectItem => {
                                const project = projectItem.project;
                                const isPastDue = projectItem.isPastDue;
                                const overdueClass = isPastDue ? 'overdue' : '';
                                const hasStages = projectItem.stages.length > 0;
                                
                                return `
                                    <div class="pr-calendar-project-group">
                                        <div class="pr-calendar-modal-item project-item ${overdueClass}" data-project-id="${project.id}">
                                            <div class="pr-calendar-modal-item-header">
                                                <div class="pr-calendar-modal-item-title">${project.title}</div>
                                                ${hasStages ? 
                                                    `<button class="pr-calendar-toggle-btn" data-toggle="stages-${project.id}">
                                                        <i class="fas fa-chevron-down"></i>
                                                    </button>` : ''
                                                }
                                            </div>
                                            <div class="pr-calendar-modal-item-meta">
                                                <span class="pr-calendar-modal-item-status ${project.status}">${project.status}</span>
                                                <span class="pr-calendar-modal-item-date">Due: ${this.formatDate(project.end_date)} ${isPastDue ? '<span class="overdue">(Overdue)</span>' : ''}</span>
                                            </div>
                                        </div>
                                        
                                        ${hasStages ? `
                                            <div class="pr-calendar-stages-container" id="stages-${project.id}">
                                                ${projectItem.stages.map(stageItem => {
                                                    const stage = stageItem.stage;
                                                    const isPastDueStage = stageItem.isPastDue;
                                                    const isAssignedStage = stageItem.isAssigned;
                                                    const overdueStageClass = isPastDueStage ? 'overdue' : '';
                                                    const assignedStageClass = isAssignedStage ? 'assigned' : '';
                                                    const hasSubstages = stageItem.substages.length > 0;
                                                    
                                                    return `
                                                        <div class="pr-calendar-stage-group">
                                                            <div class="pr-calendar-modal-item stage-item ${overdueStageClass} ${assignedStageClass}" data-project-id="${project.id}" data-stage-id="${stage.id}">
                                                                <div class="pr-calendar-modal-item-header">
                                                                    <div class="pr-calendar-modal-item-title">Stage ${stage.stage_number || ''}</div>
                                                                    ${hasSubstages && isAssignedStage ? 
                                                                        `<button class="pr-calendar-toggle-btn" data-toggle="substages-${stage.id}">
                                                                            <i class="fas fa-chevron-down"></i>
                                                                        </button>` : ''
                                                                    }
                                                                </div>
                                                                <div class="pr-calendar-modal-item-meta">
                                                                    <span class="pr-calendar-modal-item-status ${stage.status}">${stage.status}</span>
                                                                    <span class="pr-calendar-modal-item-date">Due: ${this.formatDate(stage.end_date)} ${isPastDueStage ? '<span class="overdue">(Overdue)</span>' : ''}</span>
                                                                    ${isAssignedStage ? '<span class="pr-calendar-modal-assigned-indicator"><i class="fas fa-user-check"></i> Assigned to you</span>' : ''}
                                                                </div>
                                                            </div>
                                                            
                                                            ${hasSubstages && isAssignedStage ? `
                                                                <div class="pr-calendar-substages-container" id="substages-${stage.id}">
                                                                    ${stageItem.substages.map(substageItem => {
                                                                        const substage = substageItem.substage;
                                                                        const isPastDueSubstage = substageItem.isPastDue;
                                                                        const isAssignedSubstage = substageItem.isAssigned;
                                                                        const overdueSubstageClass = isPastDueSubstage ? 'overdue' : '';
                                                                        const assignedSubstageClass = isAssignedSubstage ? 'assigned' : '';
                                                                        
                                                                        return `
                                                                            <div class="pr-calendar-modal-item substage-item ${overdueSubstageClass} ${assignedSubstageClass}" 
                                                                                data-project-id="${project.id}" 
                                                                                data-stage-id="${stage.id}" 
                                                                                data-substage-id="${substage.id}">
                                                                                <div class="pr-calendar-modal-item-title">${substage.title || `Substage ${substage.substage_number || ''}`}</div>
                                                                                <div class="pr-calendar-modal-item-meta">
                                                                                    <span class="pr-calendar-modal-item-status ${substage.status}">${substage.status}</span>
                                                                                    <span class="pr-calendar-modal-item-date">Due: ${this.formatDate(substage.end_date)} ${isPastDueSubstage ? '<span class="overdue">(Overdue)</span>' : ''}</span>
                                                                                    ${isAssignedSubstage ? '<span class="pr-calendar-modal-assigned-indicator"><i class="fas fa-user-check"></i> Assigned to you</span>' : ''}
                                                                                </div>
                                                                            </div>
                                                                        `;
                                                                    }).join('')}
                                                                </div>
                                                            ` : ''}
                                                        </div>
                                                    `;
                                                }).join('')}
                                            </div>
                                        ` : ''}
                                    </div>
                                `;
                            }).join('')}
                        </div>`
                    : '<div class="pr-calendar-modal-empty">No items due on this date</div>'}
                </div>
            </div>
        `;

        // Show modal
        const modal = document.createElement('div');
        modal.className = 'pr-calendar-modal-overlay';
        modal.innerHTML = modalContent;
        document.body.appendChild(modal);

        // Add toggle functionality for hierarchical view
        modal.querySelectorAll('.pr-calendar-toggle-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent item click event
                const targetId = btn.dataset.toggle;
                const targetContainer = document.getElementById(targetId);
                
                if (!targetContainer) {
                    console.error('Container not found:', targetId);
                    return;
                }
                
                console.log('Toggling container:', targetId, targetContainer);
                
                // Toggle container visibility
                if (targetContainer.classList.contains('open')) {
                    targetContainer.classList.remove('open');
                    btn.querySelector('i').classList.remove('fa-chevron-up');
                    btn.querySelector('i').classList.add('fa-chevron-down');
                } else {
                    targetContainer.classList.add('open');
                    btn.querySelector('i').classList.remove('fa-chevron-down');
                    btn.querySelector('i').classList.add('fa-chevron-up');
                }
            });
        });
        
        // Remove the auto-open first project functionality
        // Let users click toggle buttons to open content themselves
        
        // Add animation delay to each item for a staggered appearance
        modal.querySelectorAll('.pr-calendar-modal-item').forEach((item, index) => {
            item.style.animationDelay = `${index * 0.05}s`;
            item.style.animation = 'pr-fade-in 0.3s ease-out forwards';
        });

        // Add click handlers for modal items
        modal.querySelectorAll('.pr-calendar-modal-item').forEach(item => {
            item.addEventListener('click', (e) => {
                // Only navigate if the click wasn't on a toggle button
                if (!e.target.closest('.pr-calendar-toggle-btn')) {
                    const projectId = item.dataset.projectId;
                    const stageId = item.dataset.stageId;
                    const substageId = item.dataset.substageId;
                    
                    // Prevent the default link behavior
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Open the appropriate modal based on what was clicked
                    if (substageId) {
                        // Open substage modal
                        if (window.stageDetailModal) {
                            window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                        } else {
                            // Initialize if not already done
                            window.stageDetailModal = new StageDetailModal();
                            window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                        }
                    } else if (stageId) {
                        // Open stage modal
                        if (window.stageDetailModal) {
                            window.stageDetailModal.openStageModal(projectId, stageId);
                        } else {
                            // Initialize if not already done
                            window.stageDetailModal = new StageDetailModal();
                            window.stageDetailModal.openStageModal(projectId, stageId);
                        }
                    } else {
                        // Open project brief modal instead of navigating to project details page
                        if (window.projectBriefModal) {
                            window.projectBriefModal.openProjectModal(projectId);
                        } else {
                            // If ProjectBriefModal class is available but not initialized
                            if (typeof ProjectBriefModal === 'function') {
                                window.projectBriefModal = new ProjectBriefModal();
                                window.projectBriefModal.openProjectModal(projectId);
                            } else {
                                // Fallback if ProjectBriefModal is not available - use direct navigation
                                console.warn('ProjectBriefModal not available, falling back to direct navigation');
                                window.location.href = `project-details.php?id=${projectId}`;
                            }
                        }
                    }
                }
            });
        });

        // Close button handler
        const closeButton = modal.querySelector('.pr-calendar-modal-close');
        closeButton.addEventListener('click', () => {
            modal.remove();
        });

        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    formatDate(dateString) {
        if (!dateString) return 'Not set';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    formatDateTime(dateString) {
        if (!dateString) return 'Not set';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    formatDisplayDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    async fetchUserProjects() {
        try {
            console.log('Fetching user projects...');
            const response = await fetch('get_user_projects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.userProjects = data.projects;
                console.log('User projects fetched successfully:', this.userProjects);
                
                // Set the USER_ID global variable if it's provided in the response
                if (data.user_id && typeof window.USER_ID === 'undefined') {
                    window.USER_ID = data.user_id;
                    console.log('Set USER_ID global variable to:', window.USER_ID);
                }
                
                // Check specifically for items due on Apr 19, 2025
                const targetDate = '2025-04-19';
                let foundStagesOn2025 = false;
                let foundSubstagesOn2025 = false;
                
                this.userProjects.forEach(project => {
                    // Check all stages
                    project.stages?.forEach(stage => {
                        const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                        if (stageEndDate === targetDate) {
                            foundStagesOn2025 = true;
                            console.log(`Found stage with due date ${targetDate}: Stage ${stage.stage_number} (ID: ${stage.id})`);
                            console.log(`Stage assigned_to: ${stage.assigned_to}, current user: ${data.user_id}`);
                        }
                        
                        // Check all substages
                        stage.substages?.forEach(substage => {
                            const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                            if (substageEndDate === targetDate) {
                                foundSubstagesOn2025 = true;
                                console.log(`Found substage with due date ${targetDate}: ${substage.title} (ID: ${substage.id})`);
                                console.log(`Substage assigned_to: ${substage.assigned_to}, current user: ${data.user_id}`);
                            }
                        });
                    });
                });
                
                console.log(`Items found on 2025-04-19: Stages=${foundStagesOn2025}, Substages=${foundSubstagesOn2025}`);
                
                // Log assigned stages and substages for debugging
                let assignedStagesCount = 0;
                let assignedSubstagesCount = 0;
                const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
                
                this.userProjects.forEach(project => {
                    project.stages?.forEach(stage => {
                        // Handle different formats of assigned_to
                        const stageAssignedTo = stage.assigned_to || '';
                        let isStageAssignedToUser = false;
                        
                        if (currentUserId) {
                            if (typeof stageAssignedTo === 'string' && stageAssignedTo.includes(',')) {
                                isStageAssignedToUser = stageAssignedTo.split(',').some(id => 
                                    id.toString().trim() === currentUserId.toString());
                            } else {
                                isStageAssignedToUser = stageAssignedTo.toString() === currentUserId.toString();
                            }
                        }
                        
                        if (isStageAssignedToUser) {
                            assignedStagesCount++;
                            console.log(`Found assigned stage: ${stage.stage_number || ''} in project: ${project.title} (End date: ${stage.end_date})`);
                        }
                        
                        stage.substages?.forEach(substage => {
                            // Handle different formats of assigned_to
                            const substageAssignedTo = substage.assigned_to || '';
                            let isSubstageAssignedToUser = false;
                            
                            if (currentUserId) {
                                if (typeof substageAssignedTo === 'string' && substageAssignedTo.includes(',')) {
                                    isSubstageAssignedToUser = substageAssignedTo.split(',').some(id => 
                                        id.toString().trim() === currentUserId.toString());
                                } else {
                                    isSubstageAssignedToUser = substageAssignedTo.toString() === currentUserId.toString();
                                }
                            }
                            
                            if (isSubstageAssignedToUser) {
                                assignedSubstagesCount++;
                                console.log(`Found assigned substage: ${substage.title || 'Substage'} in project: ${project.title} (End date: ${substage.end_date})`);
                            }
                        });
                    });
                });
                
                console.log(`Total assigned stages: ${assignedStagesCount}, Total assigned substages: ${assignedSubstagesCount}`);
                
                // Force redraw the calendar
                this.renderCalendar();
            } else {
                console.error('Error in fetch response:', data.message || 'Unknown error');
            }
        } catch (error) {
            console.error('Error fetching user projects:', error);
        }
    }

    // Add new method to show all items in a day
    showDayItemsPreview(dateString) {
        const clickedDate = new Date(dateString);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Check if clicked date is today
        const isToday = clickedDate.getTime() === today.getTime();
        const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
        
        // Format the date for display
        const formattedDate = clickedDate.toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        
        // Find all projects, stages and substages due on this date
        const dueItems = {
            projects: [],
            stages: [],
            substages: []
        };
        
        // Collect all items due on this date (similar to getEventsSummary)
        this.userProjects.forEach(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            const projectDueDate = projectEndDate ? new Date(projectEndDate) : null;
            const isPastDue = projectDueDate && projectDueDate < today;
            
            // Include if original due date OR if it's past due and today
            if (projectEndDate === dateString || 
                (isToday && isPastDue && (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress'))) {
                dueItems.projects.push(project);
            }
            
            // Get stages due today - only if assigned to current user, regardless of project assignment
            project.stages.forEach(stage => {
                // Check if this stage is assigned to the current user
                const isStageAssignedToUser = stage.assigned_to && 
                    (currentUserId === null || stage.assigned_to.toString() === currentUserId.toString());
                
                if (isStageAssignedToUser) {
                    const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                    const stageDueDate = stageEndDate ? new Date(stageEndDate) : null;
                    const isStagePastDue = stageDueDate && stageDueDate < today;
                    
                    if (stageEndDate === dateString || 
                        (isToday && isStagePastDue && (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'))) {
                        dueItems.stages.push({
                            stage: stage,
                            project: project
                        });
                    }
                }
                
                // Get substages due today - only if assigned to current user, regardless of project or stage assignment
                stage.substages.forEach(substage => {
                    // Check if this substage is assigned to the current user
                    const isSubstageAssignedToUser = substage.assigned_to && 
                        (currentUserId === null || substage.assigned_to.toString() === currentUserId.toString());
                        
                    if (isSubstageAssignedToUser) {
                        // Changed variable names to avoid redeclaration
                        const substageEndDateStr = substage.end_date ? substage.end_date.split(' ')[0] : null;
                        const substageEndDate = substageEndDateStr ? new Date(substageEndDateStr) : null;
                        const isSubstagePastDue = substageEndDate && substageEndDate < today;
                        
                        if (substageEndDateStr === dateString || 
                            (isToday && isSubstagePastDue && (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'))) {
                            dueItems.substages.push({
                                substage: substage,
                                stage: stage,
                                project: project
                            });
                        }
                    }
                });
            });
        });
        
        // Create a list of all items with their details
        let allItemsHtml = '';
        
        // Add projects
        if (dueItems.projects.length > 0) {
            allItemsHtml += '<div class="day-preview-section"><h4>Projects</h4>';
            dueItems.projects.forEach(project => {
                allItemsHtml += `
                    <div class="day-preview-item day-preview-project" data-project-id="${project.id}">
                        <div class="day-preview-item-title">${project.title}</div>
                        <div class="day-preview-item-meta">
                            <span class="day-preview-item-status ${project.status}">${project.status}</span>
                            <span class="day-preview-item-due">Due: ${this.formatDate(project.end_date)}</span>
                        </div>
                    </div>
                `;
            });
            allItemsHtml += '</div>';
        }
        
        // Add stages
        if (dueItems.stages.length > 0) {
            allItemsHtml += '<div class="day-preview-section"><h4>Stages</h4>';
            dueItems.stages.forEach(item => {
                allItemsHtml += `
                    <div class="day-preview-item day-preview-stage" data-project-id="${item.project.id}" data-stage-id="${item.stage.id}">
                        <div class="day-preview-item-title">Stage ${item.stage.stage_number || ''} - ${item.project.title}</div>
                        <div class="day-preview-item-meta">
                            <span class="day-preview-item-status ${item.stage.status}">${item.stage.status}</span>
                            <span class="day-preview-item-due">Due: ${this.formatDate(item.stage.end_date)}</span>
                        </div>
                    </div>
                `;
            });
            allItemsHtml += '</div>';
        }
        
        // Add substages
        if (dueItems.substages.length > 0) {
            allItemsHtml += '<div class="day-preview-section"><h4>Substages</h4>';
            dueItems.substages.forEach(item => {
                allItemsHtml += `
                    <div class="day-preview-item day-preview-substage" 
                         data-project-id="${item.project.id}" 
                         data-stage-id="${item.stage.id}" 
                         data-substage-id="${item.substage.id}">
                        <div class="day-preview-item-title">${item.substage.title || 'Substage'} - ${item.project.title}</div>
                        <div class="day-preview-item-meta">
                            <span class="day-preview-item-status ${item.substage.status}">${item.substage.status}</span>
                            <span class="day-preview-item-due">Due: ${this.formatDate(item.substage.end_date)}</span>
                        </div>
                    </div>
                `;
            });
            allItemsHtml += '</div>';
        }
        
        // Create and show the preview modal
        const modalContent = `
            <div class="day-preview-modal">
                <div class="day-preview-header">
                    <h3>Items Due on ${formattedDate}</h3>
                    <button class="day-preview-close">&times;</button>
                </div>
                <div class="day-preview-content">
                    ${allItemsHtml || '<div class="day-preview-empty">No items due on this date</div>'}
                </div>
                <div class="day-preview-footer">
                    <button class="day-preview-details-btn">View Detailed Hierarchy</button>
                </div>
            </div>
        `;
        
        // Create and append the modal
        const modal = document.createElement('div');
        modal.className = 'day-preview-overlay';
        modal.innerHTML = modalContent;
        document.body.appendChild(modal);
        
        // Add event handler for close button
        modal.querySelector('.day-preview-close').addEventListener('click', () => {
            modal.remove();
        });
        
        // Add event handler for details button
        modal.querySelector('.day-preview-details-btn').addEventListener('click', () => {
            modal.remove();
            this.handleDayClick(dateString);
        });
        
        // Close when clicking outside the modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // Add click handlers for items to open the full detail modal
        modal.querySelectorAll('.day-preview-item').forEach(item => {
            item.addEventListener('click', () => {
                const projectId = item.dataset.projectId;
                const stageId = item.dataset.stageId;
                const substageId = item.dataset.substageId;
                
                // Close the preview modal
                modal.remove();
                
                // Open the appropriate detail modal based on what was clicked
                if (substageId) {
                    // Open substage modal
                    if (window.stageDetailModal) {
                        window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                    } else {
                        // Initialize if not already done
                        window.stageDetailModal = new StageDetailModal();
                        window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                    }
                } else if (stageId) {
                    // Open stage modal
                    if (window.stageDetailModal) {
                        window.stageDetailModal.openStageModal(projectId, stageId);
                    } else {
                        // Initialize if not already done
                        window.stageDetailModal = new StageDetailModal();
                        window.stageDetailModal.openStageModal(projectId, stageId);
                    }
                } else {
                    // Open project brief modal
                    if (window.projectBriefModal) {
                        window.projectBriefModal.openProjectModal(projectId);
                    } else {
                        // If ProjectBriefModal class is available but not initialized
                        if (typeof ProjectBriefModal === 'function') {
                            window.projectBriefModal = new ProjectBriefModal();
                            window.projectBriefModal.openProjectModal(projectId);
                        } else {
                            // Fallback to direct navigation
                            window.location.href = `project-details.php?id=${projectId}`;
                        }
                    }
                }
            });
        });
    }

    setupProjectTooltip() {
        // Get the projects card element
        const projectsCard = document.getElementById('projectsCard');
        if (!projectsCard) return;
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'projects-tooltip';
        tooltip.style.display = 'none';
        document.body.appendChild(tooltip);
        
        // Variables to track tooltip state
        let isCardHovered = false;
        let isTooltipHovered = false;
        let tooltipTimer = null;
        
        const showTooltip = () => {
            // Only show if we have project data
            if (this.userProjects && this.userProjects.length > 0) {
                const rect = projectsCard.getBoundingClientRect();
                const projectsToShow = this.userProjects.slice(0, 5);
                
                // Add "and X more" if there are more than 5 projects
                let tooltipContent = '<div class="tooltip-header">Your Assigned Projects</div>';
                tooltipContent += '<ul class="tooltip-project-list">';
                
                projectsToShow.forEach(project => {
                    tooltipContent += `<li data-project-id="${project.id}">${project.title}</li>`;
                });
                tooltipContent += '</ul>';
                
                if (this.userProjects.length > 5) {
                    tooltipContent += `<div class="tooltip-footer" id="viewAllProjects">View all ${this.userProjects.length} projects</div>`;
                }
                
                tooltip.innerHTML = tooltipContent;
                tooltip.style.display = 'block';
                
                // Calculate best position for tooltip based on screen space
                // Try to position on the right side first, fallback to left if not enough space
                const windowWidth = window.innerWidth;
                const tooltipWidth = 300; // from CSS
                
                if (rect.right + tooltipWidth + 20 > windowWidth) {
                    // Not enough space on right, position to the left of the card
                    tooltip.style.left = `${rect.left - tooltipWidth - 10}px`;
                } else {
                    // Position to the right of the card
                    tooltip.style.left = `${rect.right + 10}px`;
                }
                
                // Position vertically centered to the card
                const tooltipHeight = tooltip.offsetHeight;
                const verticalCenter = rect.top + (rect.height / 2) - (tooltipHeight / 2);
                tooltip.style.top = `${Math.max(10, verticalCenter)}px`;
                
                // Add click events to projects in tooltip
                tooltip.querySelectorAll('.tooltip-project-list li').forEach(item => {
                    item.addEventListener('click', (e) => {
                        const projectId = e.target.dataset.projectId;
                        this.openProjectDetails(projectId);
                        // Hide tooltip
                        tooltip.style.display = 'none';
                        e.stopPropagation();
                    });
                });
                
                // Add click event to "View all" footer
                const viewAllLink = tooltip.querySelector('#viewAllProjects');
                if (viewAllLink) {
                    viewAllLink.addEventListener('click', (e) => {
                        this.showAllProjectsModal();
                        // Hide tooltip
                        tooltip.style.display = 'none';
                        e.stopPropagation();
                    });
                }
            }
        };
        
        const hideTooltip = () => {
            // Only hide if both card and tooltip are not hovered
            if (!isCardHovered && !isTooltipHovered) {
                tooltip.style.display = 'none';
            }
        };
        
        // Add event listeners for card hover
        projectsCard.addEventListener('mouseenter', () => {
            isCardHovered = true;
            showTooltip();
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        projectsCard.addEventListener('mouseleave', () => {
            isCardHovered = false;
            // Use timeout to allow mouse to move to tooltip
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
        
        // Add event listeners for tooltip hover
        tooltip.addEventListener('mouseenter', () => {
            isTooltipHovered = true;
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        tooltip.addEventListener('mouseleave', () => {
            isTooltipHovered = false;
            // Use timeout to allow mouse to move back to card
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
    }
    
    openProjectDetails(projectId) {
        // Open the project detail using existing functionality if available
        if (window.projectBriefModal) {
            window.projectBriefModal.openProjectModal(projectId);
        } else {
            // If ProjectBriefModal is available but not initialized
            if (typeof ProjectBriefModal === 'function') {
                window.projectBriefModal = new ProjectBriefModal();
                window.projectBriefModal.openProjectModal(projectId);
            } else {
                // Fallback - redirect to project details page
                window.location.href = `project-details.php?id=${projectId}`;
            }
        }
    }
    
    showAllProjectsModal() {
        // Only proceed if we have projects
        if (!this.userProjects || this.userProjects.length === 0) return;
        
        // Create modal HTML
        let modalContent = `
            <div class="po-modal po-projects-modal">
                <div class="po-modal-header">
                    <h3>All Assigned Projects</h3>
                    <p>You have ${this.userProjects.length} projects assigned to you</p>
                </div>
                <div class="po-modal-list po-projects-list">
        `;
        
        // Add each project
        this.userProjects.forEach(project => {
            // Determine status class
            const statusClass = project.status || 'pending';
            
            // Format dates
            const startDate = project.start_date ? this.formatDate(project.start_date) : 'Not set';
            const endDate = project.end_date ? this.formatDate(project.end_date) : 'Not set';
            
            // Check if project is overdue
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const projectEndDate = project.end_date ? new Date(project.end_date.split(' ')[0]) : null;
            const isOverdue = projectEndDate && projectEndDate < today && 
                              (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress');
            
            const overdueClass = isOverdue ? 'po-item-overdue' : '';
            
            modalContent += `
                <div class="po-modal-item po-project-item ${overdueClass}" data-project-id="${project.id}">
                    <div class="po-item-title">${project.title}</div>
                    <div class="po-item-meta">
                        <span class="po-item-status ${statusClass}">${project.status ? project.status.replace('_', ' ') : 'pending'}</span>
                        <span class="po-item-date">Due: ${endDate}</span>
                    </div>
                </div>
            `;
        });
        
        modalContent += `
                </div>
            </div>
        `;
        
        // Disable body scrolling
        document.body.style.overflow = 'hidden';
        
        // Show modal with SweetAlert2 with enhanced animation
        Swal.fire({
            html: modalContent,
            showConfirmButton: false,
            showCloseButton: true,
            width: '650px',
            position: 'center',
            backdrop: 'rgba(15, 23, 42, 0.4)',
            showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            },
            customClass: {
                container: 'po-modal-container',
                popup: 'po-modal-popup po-projects-modal-popup',
                closeButton: 'po-modal-close',
                htmlContainer: 'po-modal-html-container'
            },
            didClose: () => {
                // Re-enable body scrolling when modal is closed
                document.body.style.overflow = '';
            }
        });
        
        // Add click event listeners to project items
        document.querySelectorAll('.po-project-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const projectId = e.currentTarget.dataset.projectId;
                
                // Close the modal
                Swal.close();
                
                // Open the project detail using existing functionality if available
                this.openProjectDetails(projectId);
            });
        });
    }

    // Load animate.css if not already loaded
    loadAnimateCSS() {
        if (!document.getElementById('animate-css')) {
            const link = document.createElement('link');
            link.id = 'animate-css';
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css';
            document.head.appendChild(link);
        }
    }

    setupStageTooltip() {
        // Get the stages card element
        const stagesCard = document.getElementById('stagesCard');
        if (!stagesCard) return;
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'projects-tooltip stages-tooltip';
        tooltip.style.display = 'none';
        document.body.appendChild(tooltip);
        
        // Variables to track tooltip state
        let isCardHovered = false;
        let isTooltipHovered = false;
        let tooltipTimer = null;
        
        const showTooltip = () => {
            // Only show if we have project data
            if (this.userProjects && this.userProjects.length > 0) {
                const rect = stagesCard.getBoundingClientRect();
                
                // Gather all stages assigned to current user
                let assignedStages = [];
                const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
                
                this.userProjects.forEach(project => {
                    project.stages?.forEach(stage => {
                        // Check if stage is assigned to current user
                        const stageAssignedTo = stage.assigned_to || '';
                        let isStageAssignedToUser = false;
                        
                        if (currentUserId) {
                            if (typeof stageAssignedTo === 'string' && stageAssignedTo.includes(',')) {
                                isStageAssignedToUser = stageAssignedTo.split(',').some(id => 
                                    id.toString().trim() === currentUserId.toString());
                            } else {
                                isStageAssignedToUser = stageAssignedTo.toString() === currentUserId.toString();
                            }
                        }
                        
                        if (isStageAssignedToUser) {
                            assignedStages.push({
                                stage: stage,
                                projectTitle: project.title,
                                projectId: project.id,
                                stageId: stage.id
                            });
                        }
                    });
                });
                
                // Sort stages by due date
                assignedStages.sort((a, b) => {
                    if (!a.stage.end_date) return 1;
                    if (!b.stage.end_date) return -1;
                    return new Date(a.stage.end_date) - new Date(b.stage.end_date);
                });
                
                // Limit to 5 stages for display
                const stagesToShow = assignedStages.slice(0, 5);
                
                // Add "and X more" if there are more than 5 stages
                let tooltipContent = '<div class="tooltip-header">Your Assigned Stages</div>';
                tooltipContent += '<ul class="tooltip-project-list">';
                
                stagesToShow.forEach(item => {
                    tooltipContent += `<li data-project-id="${item.projectId}" data-stage-id="${item.stageId}">
                        Stage ${item.stage.stage_number || ''} - ${item.projectTitle}
                    </li>`;
                });
                tooltipContent += '</ul>';
                
                if (assignedStages.length > 5) {
                    tooltipContent += `<div class="tooltip-footer" id="viewAllStages">View all ${assignedStages.length} stages</div>`;
                }
                
                tooltip.innerHTML = tooltipContent;
                tooltip.style.display = 'block';
                
                // Calculate best position for tooltip based on screen space
                const windowWidth = window.innerWidth;
                const tooltipWidth = 300; // from CSS
                
                if (rect.right + tooltipWidth + 20 > windowWidth) {
                    // Not enough space on right, position to the left of the card
                    tooltip.style.left = `${rect.left - tooltipWidth - 10}px`;
                } else {
                    // Position to the right of the card
                    tooltip.style.left = `${rect.right + 10}px`;
                }
                
                // Position vertically centered to the card
                const tooltipHeight = tooltip.offsetHeight;
                const verticalCenter = rect.top + (rect.height / 2) - (tooltipHeight / 2);
                tooltip.style.top = `${Math.max(10, verticalCenter)}px`;
                
                // Add click events to stages in tooltip
                tooltip.querySelectorAll('.tooltip-project-list li').forEach(item => {
                    item.addEventListener('click', (e) => {
                        const projectId = e.target.dataset.projectId;
                        const stageId = e.target.dataset.stageId;
                        
                        // Open stage modal
                        if (window.stageDetailModal) {
                            window.stageDetailModal.openStageModal(projectId, stageId);
                        } else {
                            // Initialize if not already done
                            window.stageDetailModal = new StageDetailModal();
                            window.stageDetailModal.openStageModal(projectId, stageId);
                        }
                        
                        // Hide tooltip
                        tooltip.style.display = 'none';
                        e.stopPropagation();
                    });
                });
                
                // Add click event to "View all" footer
                const viewAllLink = tooltip.querySelector('#viewAllStages');
                if (viewAllLink) {
                    viewAllLink.addEventListener('click', (e) => {
                        this.showAllStagesModal(assignedStages);
                        // Hide tooltip
                        tooltip.style.display = 'none';
                        e.stopPropagation();
                    });
                }
            }
        };
        
        const hideTooltip = () => {
            // Only hide if both card and tooltip are not hovered
            if (!isCardHovered && !isTooltipHovered) {
                tooltip.style.display = 'none';
            }
        };
        
        // Add event listeners for card hover
        stagesCard.addEventListener('mouseenter', () => {
            isCardHovered = true;
            showTooltip();
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        stagesCard.addEventListener('mouseleave', () => {
            isCardHovered = false;
            // Use timeout to allow mouse to move to tooltip
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
        
        // Add event listeners for tooltip hover
        tooltip.addEventListener('mouseenter', () => {
            isTooltipHovered = true;
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        tooltip.addEventListener('mouseleave', () => {
            isTooltipHovered = false;
            // Use timeout to allow mouse to move back to card
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
    }
    
    setupSubstageTooltip() {
        // Get the substages card element - assuming an ID of 'substagesCard'
        const substagesCard = document.getElementById('substagesCard');
        if (!substagesCard) return;
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'projects-tooltip substages-tooltip';
        tooltip.style.display = 'none';
        document.body.appendChild(tooltip);
        
        // Variables to track tooltip state
        let isCardHovered = false;
        let isTooltipHovered = false;
        let tooltipTimer = null;
        
        const showTooltip = () => {
            // Only show if we have project data
            if (this.userProjects && this.userProjects.length > 0) {
                const rect = substagesCard.getBoundingClientRect();
                
                // Gather all substages assigned to current user
                let assignedSubstages = [];
                const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
                
                this.userProjects.forEach(project => {
                    project.stages?.forEach(stage => {
                        stage.substages?.forEach(substage => {
                            // Check if substage is assigned to current user
                            const substageAssignedTo = substage.assigned_to || '';
                            let isSubstageAssignedToUser = false;
                            
                            if (currentUserId) {
                                if (typeof substageAssignedTo === 'string' && substageAssignedTo.includes(',')) {
                                    isSubstageAssignedToUser = substageAssignedTo.split(',').some(id => 
                                        id.toString().trim() === currentUserId.toString());
                                } else {
                                    isSubstageAssignedToUser = substageAssignedTo.toString() === currentUserId.toString();
                                }
                            }
                            
                            if (isSubstageAssignedToUser) {
                                assignedSubstages.push({
                                    substage: substage,
                                    stage: stage,
                                    projectTitle: project.title,
                                    projectId: project.id,
                                    stageId: stage.id,
                                    substageId: substage.id
                                });
                            }
                        });
                    });
                });
                
                // Sort substages by due date
                assignedSubstages.sort((a, b) => {
                    if (!a.substage.end_date) return 1;
                    if (!b.substage.end_date) return -1;
                    return new Date(a.substage.end_date) - new Date(b.substage.end_date);
                });
                
                // Limit to 5 substages for display
                const substagesToShow = assignedSubstages.slice(0, 5);
                
                // Add "and X more" if there are more than 5 substages
                let tooltipContent = '<div class="tooltip-header">Your Assigned Substages</div>';
                tooltipContent += '<ul class="tooltip-project-list">';
                
                substagesToShow.forEach(item => {
                    tooltipContent += `<li data-project-id="${item.projectId}" data-stage-id="${item.stageId}" data-substage-id="${item.substageId}">
                        ${item.substage.title || 'Substage'} - ${item.projectTitle}
                    </li>`;
                });
                tooltipContent += '</ul>';
                
                if (assignedSubstages.length > 5) {
                    tooltipContent += `<div class="tooltip-footer" id="viewAllSubstages">View all ${assignedSubstages.length} substages</div>`;
                }
                
                tooltip.innerHTML = tooltipContent;
                tooltip.style.display = 'block';
                
                // Calculate best position for tooltip based on screen space
                const windowWidth = window.innerWidth;
                const tooltipWidth = 300; // from CSS
                
                if (rect.right + tooltipWidth + 20 > windowWidth) {
                    // Not enough space on right, position to the left of the card
                    tooltip.style.left = `${rect.left - tooltipWidth - 10}px`;
                } else {
                    // Position to the right of the card
                    tooltip.style.left = `${rect.right + 10}px`;
                }
                
                // Position vertically centered to the card
                const tooltipHeight = tooltip.offsetHeight;
                const verticalCenter = rect.top + (rect.height / 2) - (tooltipHeight / 2);
                tooltip.style.top = `${Math.max(10, verticalCenter)}px`;
                
                // Add click events to substages in tooltip
                tooltip.querySelectorAll('.tooltip-project-list li').forEach(item => {
                    item.addEventListener('click', (e) => {
                        const projectId = e.target.dataset.projectId;
                        const stageId = e.target.dataset.stageId;
                        const substageId = e.target.dataset.substageId;
                        
                        // Open substage modal
                        if (window.stageDetailModal) {
                            window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                        } else {
                            // Initialize if not already done
                            window.stageDetailModal = new StageDetailModal();
                            window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                        }
                        
                        // Hide tooltip
                        tooltip.style.display = 'none';
                        e.stopPropagation();
                    });
                });
                
                // Add click event to "View all" footer
                const viewAllLink = tooltip.querySelector('#viewAllSubstages');
                if (viewAllLink) {
                    viewAllLink.addEventListener('click', (e) => {
                        this.showAllSubstagesModal(assignedSubstages);
                        // Hide tooltip
                        tooltip.style.display = 'none';
                        e.stopPropagation();
                    });
                }
            }
        };
        
        const hideTooltip = () => {
            // Only hide if both card and tooltip are not hovered
            if (!isCardHovered && !isTooltipHovered) {
                tooltip.style.display = 'none';
            }
        };
        
        // Add event listeners for card hover
        substagesCard.addEventListener('mouseenter', () => {
            isCardHovered = true;
            showTooltip();
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        substagesCard.addEventListener('mouseleave', () => {
            isCardHovered = false;
            // Use timeout to allow mouse to move to tooltip
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
        
        // Add event listeners for tooltip hover
        tooltip.addEventListener('mouseenter', () => {
            isTooltipHovered = true;
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        tooltip.addEventListener('mouseleave', () => {
            isTooltipHovered = false;
            // Use timeout to allow mouse to move back to card
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
    }

    showAllStagesModal(assignedStages) {
        // Only proceed if we have stages
        if (!assignedStages || assignedStages.length === 0) return;
        
        // Create modal HTML
        let modalContent = `
            <div class="po-modal po-stages-modal">
                <div class="po-modal-header">
                    <h3>All Assigned Stages</h3>
                    <p>You have ${assignedStages.length} stages assigned to you</p>
                </div>
                <div class="po-modal-list po-stages-list">
        `;
        
        // Add each stage
        assignedStages.forEach(item => {
            // Determine status class
            const statusClass = item.stage.status || 'pending';
            
            // Format dates
            const startDate = item.stage.start_date ? this.formatDate(item.stage.start_date) : 'Not set';
            const endDate = item.stage.end_date ? this.formatDate(item.stage.end_date) : 'Not set';
            
            // Check if stage is overdue
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const stageEndDate = item.stage.end_date ? new Date(item.stage.end_date.split(' ')[0]) : null;
            const isOverdue = stageEndDate && stageEndDate < today && 
                             (item.stage.status === 'pending' || item.stage.status === 'not_started' || item.stage.status === 'in_progress');
            
            const overdueClass = isOverdue ? 'po-item-overdue' : '';
            
            modalContent += `
                <div class="po-modal-item po-stage-item ${overdueClass}" 
                     data-project-id="${item.projectId}" 
                     data-stage-id="${item.stageId}">
                    <div class="po-item-title">Stage ${item.stage.stage_number || ''} - ${item.projectTitle}</div>
                    <div class="po-item-meta">
                        <span class="po-item-status ${statusClass}">${item.stage.status ? item.stage.status.replace('_', ' ') : 'pending'}</span>
                        <span class="po-item-date">Due: ${endDate}</span>
                    </div>
                </div>
            `;
        });
        
        modalContent += `
                </div>
            </div>
        `;
        
        // Disable body scrolling
        document.body.style.overflow = 'hidden';
        
        // Show modal with SweetAlert2 with enhanced animation
        Swal.fire({
            html: modalContent,
            showConfirmButton: false,
            showCloseButton: true,
            width: '650px',
            position: 'center',
            backdrop: 'rgba(15, 23, 42, 0.4)',
            showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            },
            customClass: {
                container: 'po-modal-container',
                popup: 'po-modal-popup po-stages-modal-popup',
                closeButton: 'po-modal-close',
                htmlContainer: 'po-modal-html-container'
            },
            didClose: () => {
                // Re-enable body scrolling when modal is closed
                document.body.style.overflow = '';
            }
        });
        
        // Add click event listeners to stage items
        document.querySelectorAll('.po-stage-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const projectId = e.currentTarget.dataset.projectId;
                const stageId = e.currentTarget.dataset.stageId;
                
                // Close the modal
                Swal.close();
                
                // Open the stage detail modal
                if (window.stageDetailModal) {
                    window.stageDetailModal.openStageModal(projectId, stageId);
                } else {
                    // Initialize if not already done
                    window.stageDetailModal = new StageDetailModal();
                    window.stageDetailModal.openStageModal(projectId, stageId);
                }
            });
        });
    }
    
    showAllSubstagesModal(assignedSubstages) {
        // Only proceed if we have substages
        if (!assignedSubstages || assignedSubstages.length === 0) return;
        
        // Create modal HTML
        let modalContent = `
            <div class="po-modal po-substages-modal">
                <div class="po-modal-header">
                    <h3>All Assigned Substages</h3>
                    <p>You have ${assignedSubstages.length} substages assigned to you</p>
                </div>
                <div class="po-modal-list po-substages-list">
        `;
        
        // Add each substage
        assignedSubstages.forEach(item => {
            // Determine status class
            const statusClass = item.substage.status || 'pending';
            
            // Format dates
            const startDate = item.substage.start_date ? this.formatDate(item.substage.start_date) : 'Not set';
            const endDate = item.substage.end_date ? this.formatDate(item.substage.end_date) : 'Not set';
            
            // Check if substage is overdue
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const substageEndDate = item.substage.end_date ? new Date(item.substage.end_date.split(' ')[0]) : null;
            const isOverdue = substageEndDate && substageEndDate < today && 
                             (item.substage.status === 'pending' || item.substage.status === 'not_started' || item.substage.status === 'in_progress');
            
            const overdueClass = isOverdue ? 'po-item-overdue' : '';
            
            modalContent += `
                <div class="po-modal-item po-substage-item ${overdueClass}" 
                     data-project-id="${item.projectId}" 
                     data-stage-id="${item.stageId}"
                     data-substage-id="${item.substageId}">
                    <div class="po-item-title">${item.substage.title || 'Substage'} - ${item.projectTitle}</div>
                    <div class="po-item-meta">
                        <span class="po-item-status ${statusClass}">${item.substage.status ? item.substage.status.replace('_', ' ') : 'pending'}</span>
                        <span class="po-item-date">Due: ${endDate}</span>
                    </div>
                </div>
            `;
        });
        
        modalContent += `
                </div>
            </div>
        `;
        
        // Disable body scrolling
        document.body.style.overflow = 'hidden';
        
        // Show modal with SweetAlert2 with enhanced animation
        Swal.fire({
            html: modalContent,
            showConfirmButton: false,
            showCloseButton: true,
            width: '650px',
            position: 'center',
            backdrop: 'rgba(15, 23, 42, 0.4)',
            showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            },
            customClass: {
                container: 'po-modal-container',
                popup: 'po-modal-popup po-substages-modal-popup',
                closeButton: 'po-modal-close',
                htmlContainer: 'po-modal-html-container'
            },
            didClose: () => {
                // Re-enable body scrolling when modal is closed
                document.body.style.overflow = '';
            }
        });
        
        // Add click event listeners to substage items
        document.querySelectorAll('.po-substage-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const projectId = e.currentTarget.dataset.projectId;
                const stageId = e.currentTarget.dataset.stageId;
                const substageId = e.currentTarget.dataset.substageId;
                
                // Close the modal
                Swal.close();
                
                // Open the substage detail modal
                if (window.stageDetailModal) {
                    window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                } else {
                    // Initialize if not already done
                    window.stageDetailModal = new StageDetailModal();
                    window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                }
            });
        });
    }

    setupStagesDueTooltip() {
        // Get the stages due card element
        const stagesDueCard = document.getElementById('stagesDueCard');
        if (!stagesDueCard) return;
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'projects-tooltip stages-due-tooltip';
        tooltip.style.display = 'none';
        document.body.appendChild(tooltip);
        
        // Variables to track tooltip state
        let isCardHovered = false;
        let isTooltipHovered = false;
        let tooltipTimer = null;
        
        const showTooltip = () => {
            // Only show if we have project data
            if (this.userProjects && this.userProjects.length > 0) {
                const rect = stagesDueCard.getBoundingClientRect();
                
                // Get today's date and 7 days from now for filtering
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const oneWeekLater = new Date(today);
                oneWeekLater.setDate(today.getDate() + 7);
                
                // Gather all stages due within next 7 days (only upcoming, not past due)
                let dueStages = [];
                const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
                
                this.userProjects.forEach(project => {
                    project.stages?.forEach(stage => {
                        // Check if stage is assigned to current user
                        const stageAssignedTo = stage.assigned_to || '';
                        let isStageAssignedToUser = false;
                        
                        if (currentUserId) {
                            if (typeof stageAssignedTo === 'string' && stageAssignedTo.includes(',')) {
                                isStageAssignedToUser = stageAssignedTo.split(',').some(id => 
                                    id.toString().trim() === currentUserId.toString());
                            } else {
                                isStageAssignedToUser = stageAssignedTo.toString() === currentUserId.toString();
                            }
                        }
                        
                        // Only include if it's assigned to user, not completed, and due within 7 days
                        if (isStageAssignedToUser && stage.status !== 'completed') {
                            const stageEndDate = stage.end_date ? new Date(stage.end_date.split(' ')[0]) : null;
                            
                            // Only include stages due TODAY or in the FUTURE (within next 7 days)
                            if (stageEndDate && stageEndDate >= today && stageEndDate <= oneWeekLater) {
                                // Calculate how many days until due
                                const daysUntilDue = Math.ceil((stageEndDate - today) / (1000 * 60 * 60 * 24));
                                
                                dueStages.push({
                                    stage: stage,
                                    projectTitle: project.title,
                                    projectId: project.id,
                                    stageId: stage.id,
                                    dueDate: stageEndDate,
                                    daysUntilDue: daysUntilDue
                                });
                            }
                        }
                    });
                });
                
                // Sort stages by due date (earliest first)
                dueStages.sort((a, b) => a.dueDate - b.dueDate);
                
                // Check if we have any due stages
                if (dueStages.length === 0) {
                    tooltip.innerHTML = '<div class="tooltip-header">Upcoming Stages (Next 7 Days)</div><div class="tooltip-empty">No stages due in the next 7 days.</div>';
                } else {
                    // Count urgent (due within 2 days) stages
                    const urgentStages = dueStages.filter(item => item.daysUntilDue <= 2).length;
                    const otherStages = dueStages.length - urgentStages;
                    
                    // Create the tooltip content
                    let tooltipContent = '<div class="tooltip-header">Upcoming Stages (Next 7 Days)</div>';
                    tooltipContent += '<div class="tooltip-summary">';
                    
                    if (urgentStages > 0) {
                        tooltipContent += `<div class="tooltip-stat critical"><i class="fas fa-exclamation-triangle"></i> ${urgentStages} due soon</div>`;
                    }
                    
                    if (otherStages > 0) {
                        tooltipContent += `<div class="tooltip-stat upcoming"><i class="fas fa-calendar-alt"></i> ${otherStages} upcoming</div>`;
                    }
                    
                    tooltipContent += '</div>';
                    tooltipContent += '<ul class="tooltip-project-list">';
                    
                    // Get up to 5 items to show
                    const stagesToShow = dueStages.slice(0, 5);
                    stagesToShow.forEach(item => {
                        const urgentClass = item.daysUntilDue <= 2 ? 'tooltip-item-urgent' : '';
                        const dueDateFormatted = this.formatDate(item.stage.end_date);
                        const daysText = item.daysUntilDue === 0 ? 'Today' : 
                                        item.daysUntilDue === 1 ? 'Tomorrow' : 
                                        `In ${item.daysUntilDue} days`;
                        
                        tooltipContent += `
                            <li data-project-id="${item.projectId}" data-stage-id="${item.stageId}" class="${urgentClass}">
                                <div class="tooltip-item-title">Stage ${item.stage.stage_number || ''} - ${item.projectTitle}</div>
                                <div class="tooltip-item-date">Due: ${dueDateFormatted} <span class="tooltip-days-until">(${daysText})</span></div>
                            </li>
                        `;
                    });
                    
                    tooltipContent += '</ul>';
                    
                    if (dueStages.length > 5) {
                        tooltipContent += `<div class="tooltip-footer" id="viewAllDueStages">View all ${dueStages.length} due stages</div>`;
                    }
                    
                    tooltip.innerHTML = tooltipContent;
                }
                
                tooltip.style.display = 'block';
                
                // Calculate best position for tooltip based on screen space
                const windowWidth = window.innerWidth;
                const tooltipWidth = 320; // from CSS
                
                if (rect.right + tooltipWidth + 20 > windowWidth) {
                    // Not enough space on right, position to the left of the card
                    tooltip.style.left = `${rect.left - tooltipWidth - 10}px`;
                } else {
                    // Position to the right of the card
                    tooltip.style.left = `${rect.right + 10}px`;
                }
                
                // Position vertically centered to the card
                const tooltipHeight = tooltip.offsetHeight;
                const verticalCenter = rect.top + (rect.height / 2) - (tooltipHeight / 2);
                tooltip.style.top = `${Math.max(10, verticalCenter)}px`;
                
                // Add click events to stages in tooltip
                tooltip.querySelectorAll('.tooltip-project-list li').forEach(item => {
                    item.addEventListener('click', (e) => {
                        const element = e.currentTarget;
                        const projectId = element.dataset.projectId;
                        const stageId = element.dataset.stageId;
                        
                        // Open stage modal
                        if (window.stageDetailModal) {
                            window.stageDetailModal.openStageModal(projectId, stageId);
                        } else {
                            // Initialize if not already done
                            window.stageDetailModal = new StageDetailModal();
                            window.stageDetailModal.openStageModal(projectId, stageId);
                        }
                        
                        // Hide tooltip
                        tooltip.style.display = 'none';
                    });
                });
                
                // Add click event to "View all" footer
                const viewAllLink = tooltip.querySelector('#viewAllDueStages');
                if (viewAllLink) {
                    viewAllLink.addEventListener('click', () => {
                        this.showAllDueStagesModal(dueStages);
                        // Hide tooltip
                        tooltip.style.display = 'none';
                    });
                }
            }
        };
        
        const hideTooltip = () => {
            if (!isCardHovered && !isTooltipHovered) {
                tooltip.style.display = 'none';
            }
        };
        
        // Add event listeners for card hover
        stagesDueCard.addEventListener('mouseenter', () => {
            isCardHovered = true;
            showTooltip();
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        stagesDueCard.addEventListener('mouseleave', () => {
            isCardHovered = false;
            // Use timeout to allow mouse to move to tooltip
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
        
        // Add event listeners for tooltip hover
        tooltip.addEventListener('mouseenter', () => {
            isTooltipHovered = true;
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        tooltip.addEventListener('mouseleave', () => {
            isTooltipHovered = false;
            // Use timeout to allow mouse to move back to card
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
    }

    setupSubstagesDueTooltip() {
        // Get the substages due card element
        const substagesDueCard = document.getElementById('substagesDueCard');
        if (!substagesDueCard) return;
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'projects-tooltip substages-due-tooltip';
        tooltip.style.display = 'none';
        document.body.appendChild(tooltip);
        
        // Variables to track tooltip state
        let isCardHovered = false;
        let isTooltipHovered = false;
        let tooltipTimer = null;
        
        const showTooltip = () => {
            // Only show if we have project data
            if (this.userProjects && this.userProjects.length > 0) {
                const rect = substagesDueCard.getBoundingClientRect();
                
                // Get today's date and 7 days from now for filtering
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const oneWeekLater = new Date(today);
                oneWeekLater.setDate(today.getDate() + 7);
                
                // Gather all substages due within 7 days
                let dueSubstages = [];
                const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : null;
                
                this.userProjects.forEach(project => {
                    project.stages?.forEach(stage => {
                        stage.substages?.forEach(substage => {
                            // Check if substage is assigned to current user
                            const substageAssignedTo = substage.assigned_to || '';
                            let isSubstageAssignedToUser = false;
                            
                            if (currentUserId) {
                                if (typeof substageAssignedTo === 'string' && substageAssignedTo.includes(',')) {
                                    isSubstageAssignedToUser = substageAssignedTo.split(',').some(id => 
                                        id.toString().trim() === currentUserId.toString());
                                } else {
                                    isSubstageAssignedToUser = substageAssignedTo.toString() === currentUserId.toString();
                                }
                            }
                            
                            // Only include if it's assigned to user, not completed, and due within 7 days
                            if (isSubstageAssignedToUser && substage.status !== 'completed') {
                                const substageEndDate = substage.end_date ? new Date(substage.end_date.split(' ')[0]) : null;
                                
                                // Only include substages due TODAY or in the FUTURE (within next 7 days)
                                if (substageEndDate && substageEndDate >= today && substageEndDate <= oneWeekLater) {
                                    // Calculate how many days until due
                                    const daysUntilDue = Math.ceil((substageEndDate - today) / (1000 * 60 * 60 * 24));
                                    
                                    dueSubstages.push({
                                        substage: substage,
                                        stage: stage,
                                        projectTitle: project.title,
                                        projectId: project.id,
                                        stageId: stage.id,
                                        substageId: substage.id,
                                        dueDate: substageEndDate,
                                        daysUntilDue: daysUntilDue
                                    });
                                }
                            }
                        });
                    });
                });
                
                // Sort substages by due date (earliest first)
                dueSubstages.sort((a, b) => a.dueDate - b.dueDate);
                
                // Check if we have any due substages
                if (dueSubstages.length === 0) {
                    tooltip.innerHTML = '<div class="tooltip-header">Upcoming Substages (Next 7 Days)</div><div class="tooltip-empty">No substages due in the next 7 days.</div>';
                } else {
                    // Count urgent (due within 2 days) substages
                    const urgentSubstages = dueSubstages.filter(item => item.daysUntilDue <= 2).length;
                    const otherSubstages = dueSubstages.length - urgentSubstages;
                    
                    // Create the tooltip content
                    let tooltipContent = '<div class="tooltip-header">Upcoming Substages (Next 7 Days)</div>';
                    tooltipContent += '<div class="tooltip-summary">';
                    
                    if (urgentSubstages > 0) {
                        tooltipContent += `<div class="tooltip-stat critical"><i class="fas fa-exclamation-triangle"></i> ${urgentSubstages} due soon</div>`;
                    }
                    
                    if (otherSubstages > 0) {
                        tooltipContent += `<div class="tooltip-stat upcoming"><i class="fas fa-calendar-alt"></i> ${otherSubstages} upcoming</div>`;
                    }
                    
                    tooltipContent += '</div>';
                    tooltipContent += '<ul class="tooltip-project-list">';
                    
                    // Get up to 5 items to show
                    const substagesToShow = dueSubstages.slice(0, 5);
                    substagesToShow.forEach(item => {
                        const urgentClass = item.daysUntilDue <= 2 ? 'tooltip-item-urgent' : '';
                        const dueDateFormatted = this.formatDate(item.substage.end_date);
                        const daysText = item.daysUntilDue === 0 ? 'Today' : 
                                        item.daysUntilDue === 1 ? 'Tomorrow' : 
                                        `In ${item.daysUntilDue} days`;
                        
                        tooltipContent += `
                            <li data-project-id="${item.projectId}" data-stage-id="${item.stageId}" data-substage-id="${item.substageId}" class="${urgentClass}">
                                <div class="tooltip-item-title">${item.substage.title || 'Substage'} - ${item.projectTitle}</div>
                                <div class="tooltip-item-date">Due: ${dueDateFormatted} <span class="tooltip-days-until">(${daysText})</span></div>
                            </li>
                        `;
                    });
                    
                    tooltipContent += '</ul>';
                    
                    if (dueSubstages.length > 5) {
                        tooltipContent += `<div class="tooltip-footer" id="viewAllDueSubstages">View all ${dueSubstages.length} due substages</div>`;
                    }
                    
                    tooltip.innerHTML = tooltipContent;
                }
                
                tooltip.style.display = 'block';
                
                // Calculate best position for tooltip based on screen space
                const windowWidth = window.innerWidth;
                const tooltipWidth = 320; // from CSS
                
                if (rect.right + tooltipWidth + 20 > windowWidth) {
                    // Not enough space on right, position to the left of the card
                    tooltip.style.left = `${rect.left - tooltipWidth - 10}px`;
                } else {
                    // Position to the right of the card
                    tooltip.style.left = `${rect.right + 10}px`;
                }
                
                // Position vertically centered to the card
                const tooltipHeight = tooltip.offsetHeight;
                const verticalCenter = rect.top + (rect.height / 2) - (tooltipHeight / 2);
                tooltip.style.top = `${Math.max(10, verticalCenter)}px`;
                
                // Add click events to substages in tooltip
                tooltip.querySelectorAll('.tooltip-project-list li').forEach(item => {
                    item.addEventListener('click', (e) => {
                        const element = e.currentTarget;
                        const projectId = element.dataset.projectId;
                        const stageId = element.dataset.stageId;
                        const substageId = element.dataset.substageId;
                        
                        // Open substage modal
                        if (window.stageDetailModal) {
                            window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                        } else {
                            // Initialize if not already done
                            window.stageDetailModal = new StageDetailModal();
                            window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                        }
                        
                        // Hide tooltip
                        tooltip.style.display = 'none';
                    });
                });
                
                // Add click event to "View all" footer
                const viewAllLink = tooltip.querySelector('#viewAllDueSubstages');
                if (viewAllLink) {
                    viewAllLink.addEventListener('click', () => {
                        this.showAllDueSubstagesModal(dueSubstages);
                        // Hide tooltip
                        tooltip.style.display = 'none';
                    });
                }
            }
        };
        
        const hideTooltip = () => {
            if (!isCardHovered && !isTooltipHovered) {
                tooltip.style.display = 'none';
            }
        };
        
        // Add event listeners for card hover
        substagesDueCard.addEventListener('mouseenter', () => {
            isCardHovered = true;
            showTooltip();
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        substagesDueCard.addEventListener('mouseleave', () => {
            isCardHovered = false;
            // Use timeout to allow mouse to move to tooltip
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
        
        // Add event listeners for tooltip hover
        tooltip.addEventListener('mouseenter', () => {
            isTooltipHovered = true;
            // Clear any existing hide timer
            if (tooltipTimer) {
                clearTimeout(tooltipTimer);
                tooltipTimer = null;
            }
        });
        
        tooltip.addEventListener('mouseleave', () => {
            isTooltipHovered = false;
            // Use timeout to allow mouse to move back to card
            tooltipTimer = setTimeout(hideTooltip, 100);
        });
    }

    showAllDueStagesModal(dueStages) {
        // Only proceed if we have stages
        if (!dueStages || dueStages.length === 0) return;
        
        // Create modal HTML
        let modalContent = `
            <div class="po-modal po-stages-due-modal">
                <div class="po-modal-header">
                    <h3>Upcoming Stages (Next 7 Days)</h3>
                    <p>You have ${dueStages.length} stages coming due in the next 7 days</p>
                </div>
                <div class="po-modal-list po-stages-due-list">
        `;
        
        // Add each stage
        dueStages.forEach(item => {
            // Determine status class
            const statusClass = item.stage.status || 'pending';
            
            // Format date
            const endDate = item.stage.end_date ? this.formatDate(item.stage.end_date) : 'Not set';
            
            // Add urgent class if due within 2 days
            const urgentClass = item.daysUntilDue <= 2 ? 'po-item-urgent' : '';
            
            // Create days-until text
            const daysText = item.daysUntilDue === 0 ? 'Today' : 
                            item.daysUntilDue === 1 ? 'Tomorrow' : 
                            `In ${item.daysUntilDue} days`;
            
            modalContent += `
                <div class="po-modal-item po-stage-due-item ${urgentClass}" 
                     data-project-id="${item.projectId}" 
                     data-stage-id="${item.stageId}">
                    <div class="po-item-title">Stage ${item.stage.stage_number || ''} - ${item.projectTitle}</div>
                    <div class="po-item-meta">
                        <span class="po-item-status ${statusClass}">${item.stage.status ? item.stage.status.replace('_', ' ') : 'pending'}</span>
                        <span class="po-item-date">Due: ${endDate} <span class="po-days-until">(${daysText})</span></span>
                    </div>
                </div>
            `;
        });
        
        modalContent += `
                </div>
            </div>
        `;
        
        // Disable body scrolling
        document.body.style.overflow = 'hidden';
        
        // Show modal with SweetAlert2 with enhanced animation
        Swal.fire({
            html: modalContent,
            showConfirmButton: false,
            showCloseButton: true,
            width: '650px',
            position: 'center',
            backdrop: 'rgba(15, 23, 42, 0.4)',
            showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            },
            customClass: {
                container: 'po-modal-container',
                popup: 'po-modal-popup po-stages-due-modal-popup',
                closeButton: 'po-modal-close',
                htmlContainer: 'po-modal-html-container'
            },
            didClose: () => {
                // Re-enable body scrolling when modal is closed
                document.body.style.overflow = '';
            }
        });
        
        // Add click event listeners to stage items
        document.querySelectorAll('.po-stage-due-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const projectId = e.currentTarget.dataset.projectId;
                const stageId = e.currentTarget.dataset.stageId;
                
                // Close the modal
                Swal.close();
                
                // Open the stage detail modal
                if (window.stageDetailModal) {
                    window.stageDetailModal.openStageModal(projectId, stageId);
                } else {
                    // Initialize if not already done
                    window.stageDetailModal = new StageDetailModal();
                    window.stageDetailModal.openStageModal(projectId, stageId);
                }
            });
        });
    }
    
    showAllDueSubstagesModal(dueSubstages) {
        // Only proceed if we have substages
        if (!dueSubstages || dueSubstages.length === 0) return;
        
        // Create modal HTML
        let modalContent = `
            <div class="po-modal po-substages-due-modal">
                <div class="po-modal-header">
                    <h3>Upcoming Substages (Next 7 Days)</h3>
                    <p>You have ${dueSubstages.length} substages coming due in the next 7 days</p>
                </div>
                <div class="po-modal-list po-substages-due-list">
        `;
        
        // Add each substage
        dueSubstages.forEach(item => {
            // Determine status class
            const statusClass = item.substage.status || 'pending';
            
            // Format date
            const endDate = item.substage.end_date ? this.formatDate(item.substage.end_date) : 'Not set';
            
            // Add urgent class if due within 2 days
            const urgentClass = item.daysUntilDue <= 2 ? 'po-item-urgent' : '';
            
            // Create days-until text
            const daysText = item.daysUntilDue === 0 ? 'Today' : 
                            item.daysUntilDue === 1 ? 'Tomorrow' : 
                            `In ${item.daysUntilDue} days`;
            
            modalContent += `
                <div class="po-modal-item po-substage-due-item ${urgentClass}" 
                     data-project-id="${item.projectId}" 
                     data-stage-id="${item.stageId}"
                     data-substage-id="${item.substageId}">
                    <div class="po-item-title">${item.substage.title || 'Substage'} - ${item.projectTitle}</div>
                    <div class="po-item-meta">
                        <span class="po-item-status ${statusClass}">${item.substage.status ? item.substage.status.replace('_', ' ') : 'pending'}</span>
                        <span class="po-item-date">Due: ${endDate} <span class="po-days-until">(${daysText})</span></span>
                    </div>
                </div>
            `;
        });
        
        modalContent += `
                </div>
            </div>
        `;
        
        // Disable body scrolling
        document.body.style.overflow = 'hidden';
        
        // Show modal with SweetAlert2 with enhanced animation
        Swal.fire({
            html: modalContent,
            showConfirmButton: false,
            showCloseButton: true,
            width: '650px',
            position: 'center',
            backdrop: 'rgba(15, 23, 42, 0.4)',
            showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            },
            customClass: {
                container: 'po-modal-container',
                popup: 'po-modal-popup po-substages-due-modal-popup',
                closeButton: 'po-modal-close',
                htmlContainer: 'po-modal-html-container'
            },
            didClose: () => {
                // Re-enable body scrolling when modal is closed
                document.body.style.overflow = '';
            }
        });
        
        // Add click event listeners to substage items
        document.querySelectorAll('.po-substage-due-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const projectId = e.currentTarget.dataset.projectId;
                const stageId = e.currentTarget.dataset.stageId;
                const substageId = e.currentTarget.dataset.substageId;
                
                // Close the modal
                Swal.close();
                
                // Open the substage detail modal
                if (window.stageDetailModal) {
                    window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                } else {
                    // Initialize if not already done
                    window.stageDetailModal = new StageDetailModal();
                    window.stageDetailModal.openSubstageModal(projectId, stageId, substageId);
                }
            });
        });
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if USER_ID is defined, if not try to get it from any available source
    if (typeof USER_ID === 'undefined') {
        console.log('USER_ID not defined, checking for alternative sources...');
        
        // Try to get from a data attribute if available
        const userIdElement = document.querySelector('[data-user-id]');
        if (userIdElement && userIdElement.dataset.userId) {
            window.USER_ID = userIdElement.dataset.userId;
            console.log('Set USER_ID from data attribute:', window.USER_ID);
        }
    }
    
    window.projectOverview = new ProjectOverview();
});