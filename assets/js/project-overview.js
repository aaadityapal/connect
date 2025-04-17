class ProjectOverview {
    constructor() {
        this.initializeOverview();
        this.initializeFilters();
        this.initializeViewToggle();
        this.initializeCalendar();
        this.currentDate = new Date();
        this.userProjects = [];
        this.fetchUserProjects();
    }

    initializeOverview() {
        // Initialize any event listeners or dynamic functionality
        this.setupCardAnimations();
        this.initializeDataRefresh();
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
        if (!daysContainer) return;

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
                    project.stages.forEach(stage => {
                        const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                        if (stageEndDate && stageEndDate < today && 
                            (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress')) {
                            overdueCount++;
                        }
                        
                        // Check overdue substages
                        stage.substages.forEach(substage => {
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
            
            daysHtml += `
                <div class="calendar-day${isToday ? ' today' : ''}${hasEvents ? ' has-events' : ''}${hasOverdue ? ' has-overdue' : ''}${hasOverdueItems ? ' has-overdue-items' : ''}" 
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

        // Add click handlers for days
        document.querySelectorAll('.calendar-day:not(.empty)').forEach(day => {
            day.addEventListener('click', () => this.handleDayClick(day.dataset.date));
        });
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
            
            // Count stages
            project.stages.forEach(stage => {
                const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                const stageDueDate = stageEndDate ? new Date(stageEndDate) : null;
                const isStagePastDue = stageDueDate && stageDueDate < today;
                
                // Check if this stage should be shown on this day
                const showStageOnThisDay = 
                    // Original due date matches this day
                    (stageEndDate === dateString) ||
                    // OR it's today's date AND stage is past due AND status is pending/not_started
                    (isToday && isStagePastDue && (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'));
                
                if (showStageOnThisDay) {
                    stageCount++;
                }
                
                // Count substages
                stage.substages.forEach(substage => {
                    const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                    const substageDueDate = substageEndDate ? new Date(substageEndDate) : null;
                    const isSubstagePastDue = substageDueDate && substageDueDate < today;
                    
                    // Check if this substage should be shown on this day
                    const showSubstageOnThisDay = 
                        // Original due date matches this day
                        (substageEndDate === dateString) ||
                        // OR it's today's date AND substage is past due AND status is pending/not_started
                        (isToday && isSubstagePastDue && (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'));
                    
                    if (showSubstageOnThisDay) {
                        substageCount++;
                    }
                });
            });
        });
        
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
        
        // Check projects
        const hasProjectsDue = this.userProjects.some(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            const projectDueDate = projectEndDate ? new Date(projectEndDate) : null;
            const isPastDue = projectDueDate && projectDueDate < today;
            
            return (projectEndDate === dateString) || 
                   (isToday && isPastDue && (project.status === 'pending' || project.status === 'not_started' || project.status === 'in_progress'));
        });
        
        if (hasProjectsDue) return true;
        
        // Check stages
        const hasStagesDue = this.userProjects.some(project => {
            return project.stages.some(stage => {
                const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                const stageDueDate = stageEndDate ? new Date(stageEndDate) : null;
                const isStagePastDue = stageDueDate && stageDueDate < today;
                
                return (stageEndDate === dateString) || 
                       (isToday && isStagePastDue && (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'));
            });
        });
        
        if (hasStagesDue) return true;
        
        // Check substages
        const hasSubstagesDue = this.userProjects.some(project => {
            return project.stages.some(stage => {
                return stage.substages.some(substage => {
                    const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                    const substageDueDate = substageEndDate ? new Date(substageEndDate) : null;
                    const isSubstagePastDue = substageDueDate && substageDueDate < today;
                    
                    return (substageEndDate === dateString) || 
                           (isToday && isSubstagePastDue && (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'));
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
            project.stages.forEach(stage => {
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
                
                // Get substages due today - including past due substages shifted to today
                stage.substages.forEach(substage => {
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
                });
            });
        });
        
        const totalCount = dueItems.projects.length + dueItems.stages.length + dueItems.substages.length;
        if (totalCount === 0) return '';
        
        // Display all project titles with priority order
        const allItems = [
            ...dueItems.projects.map(p => ({ type: 'project', title: p.title, priority: 1 })),
            ...dueItems.stages.map(s => ({ type: 'stage', title: s.project.title, priority: 2 })),
            ...dueItems.substages.map(s => ({ type: 'substage', title: s.project.title, priority: 3 }))
        ];
        
        // Get unique project titles with highest priority
        const uniqueProjects = {};
        allItems.forEach(item => {
            if (!uniqueProjects[item.title] || uniqueProjects[item.title].priority > item.priority) {
                uniqueProjects[item.title] = item;
            }
        });
        
        const uniqueProjectsArray = Object.values(uniqueProjects);
        
        // Sort by priority (project > stage > substage)
        uniqueProjectsArray.sort((a, b) => a.priority - b.priority);
        
        // Add the has-overflow class for cells with more than 3 projects
        const hasOverflow = uniqueProjectsArray.length > 3;
        
        // Start with an empty display area
        let summary = `<div class="calendar-day-events">`;
        
        // Show up to 3 projects
        const displayProjects = uniqueProjectsArray.slice(0, 3);
        
        displayProjects.forEach(item => {
            summary += `<div class="calendar-day-event-item">${this.truncateText(item.title, 15)}</div>`;
        });
        
        // If there are more projects, add a count indicator
        if (uniqueProjectsArray.length > 3) {
            summary += `<div class="calendar-day-event-item">+${uniqueProjectsArray.length - 3} more</div>`;
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
            
            // Add stages and substages
            project.stages.forEach(stage => {
                // Only include stages assigned to the current user
                const isStageAssignedToUser = stage.assigned_to && 
                    (currentUserId === null || stage.assigned_to.toString() === currentUserId.toString());
                
                const stageEndDate = stage.end_date ? new Date(stage.end_date.split(' ')[0]) : null;
                const isStageDueToday = stageEndDate && stageEndDate.getTime() === clickedDate.getTime();
                const isPastDueStage = stageEndDate && stageEndDate < today;
                const shouldShowStage = (isStageDueToday || 
                                       (isToday && isPastDueStage && 
                                        (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'))) &&
                                       isStageAssignedToUser;
                
                if (shouldShowStage || shouldShowProject) {
                    const stageItem = {
                        stage: stage,
                        isDueToday: isStageDueToday,
                        isPastDue: isPastDueStage && (stage.status === 'pending' || stage.status === 'not_started' || stage.status === 'in_progress'),
                        isAssigned: isStageAssignedToUser,
                        substages: []
                    };
                    
                    // Add substages
                    stage.substages.forEach(substage => {
                        // Only include substages assigned to the current user
                        const isSubstageAssignedToUser = substage.assigned_to && 
                            (currentUserId === null || substage.assigned_to.toString() === currentUserId.toString());
                            
                        const substageEndDate = substage.end_date ? new Date(substage.end_date.split(' ')[0]) : null;
                        const isSubstageDueToday = substageEndDate && substageEndDate.getTime() === clickedDate.getTime();
                        const isPastDueSubstage = substageEndDate && substageEndDate < today;
                        const shouldShowSubstage = (isSubstageDueToday || 
                                                 (isToday && isPastDueSubstage && 
                                                  (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'))) &&
                                                  isSubstageAssignedToUser;
                        
                        if (shouldShowSubstage) {
                            stageItem.substages.push({
                                substage: substage,
                                isDueToday: isSubstageDueToday,
                                isPastDue: isPastDueSubstage && (substage.status === 'pending' || substage.status === 'not_started' || substage.status === 'in_progress'),
                                isAssigned: isSubstageAssignedToUser
                            });
                        }
                    });
                    
                    if (stageItem.substages.length > 0 || shouldShowStage) {
                        projectsWithItems[project.id].stages.push(stageItem);
                    }
                }
            });
            
            // Remove project if it has no stages and is not directly due
            if (!shouldShowProject && projectsWithItems[project.id].stages.length === 0) {
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
            const response = await fetch('get_user_projects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.userProjects = data.projects;
                this.renderCalendar();
            }
        } catch (error) {
            console.error('Error fetching user projects:', error);
        }
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.projectOverview = new ProjectOverview();
});