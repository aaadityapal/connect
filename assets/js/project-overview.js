class ProjectOverview {
    constructor() {
        this.initializeOverview();
        this.initializeFilters();
        this.initializeViewToggle();
        this.initializeCalendar();
        this.currentDate = new Date();
        this.userProjects = [];
        this.fetchUserProjects();
        
        // Add dummy data for testing - remove later
        setTimeout(() => {
            this.addDummyData();
        }, 1000);
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
            
            daysHtml += `
                <div class="calendar-day${isToday ? ' today' : ''}${hasEvents ? ' has-events' : ''}${hasOverdue ? ' has-overdue' : ''}" 
                     data-date="${date}">
                    <div class="calendar-day-number">${day}</div>
                    ${this.getEventsSummary(year, month, day)}
                    ${this.generateCountTags(dayCounts)}
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
        
        let projectCount = 0;
        let stageCount = 0;
        let substageCount = 0;
        
        // Count projects
        this.userProjects.forEach(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            if (projectEndDate === dateString) {
                projectCount++;
            }
            
            // Count stages
            project.stages.forEach(stage => {
                const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                if (stageEndDate === dateString) {
                    stageCount++;
                }
                
                // Count substages
                stage.substages.forEach(substage => {
                    const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                    if (substageEndDate === dateString) {
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
        
        // Check projects
        const hasProjectsDue = this.userProjects.some(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            return projectEndDate === dateString;
        });
        
        if (hasProjectsDue) return true;
        
        // Check stages
        const hasStagesDue = this.userProjects.some(project => {
            return project.stages.some(stage => {
                const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                return stageEndDate === dateString;
            });
        });
        
        if (hasStagesDue) return true;
        
        // Check substages
        const hasSubstagesDue = this.userProjects.some(project => {
            return project.stages.some(stage => {
                return stage.substages.some(substage => {
                    const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                    return substageEndDate === dateString;
                });
            });
        });
        
        return hasSubstagesDue;
    }

    getEventsSummary(year, month, day) {
        const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        // Find all projects, stages and substages due on this date
        const dueItems = {
            projects: [],
            stages: [],
            substages: []
        };
        
        // Get projects due today
        this.userProjects.forEach(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            if (projectEndDate === dateString) {
                dueItems.projects.push(project);
            }
            
            // Get stages due today
            project.stages.forEach(stage => {
                const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                if (stageEndDate === dateString) {
                    dueItems.stages.push({
                        stage: stage,
                        project: project
                    });
                }
                
                // Get substages due today
                stage.substages.forEach(substage => {
                    const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                    if (substageEndDate === dateString) {
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

    handleDayClick(dateString) {
        // Find projects due on this date
        const projectsDueToday = this.userProjects.filter(project => {
            const projectEndDate = project.end_date ? project.end_date.split(' ')[0] : null;
            return projectEndDate === dateString;
        });
        
        // Find stages and their substages due on this date
        const stagesWithSubstagesDueToday = [];
        this.userProjects.forEach(project => {
            project.stages.forEach(stage => {
                const stageEndDate = stage.end_date ? stage.end_date.split(' ')[0] : null;
                const substagesDueToday = stage.substages.filter(substage => {
                    const substageEndDate = substage.end_date ? substage.end_date.split(' ')[0] : null;
                    return substageEndDate === dateString;
                });
                
                // Include stage if it's due today or has substages due today
                if (stageEndDate === dateString || substagesDueToday.length > 0) {
                    stagesWithSubstagesDueToday.push({
                        project: project,
                        stage: stage,
                        stageDueToday: stageEndDate === dateString,
                        substagesDueToday: substagesDueToday
                    });
                }
            });
        });
        
        if (projectsDueToday.length === 0 && stagesWithSubstagesDueToday.length === 0) return;
        
        // Create HTML for modal
        let contentHtml = '';
        
        // Get the current date for comparing due dates
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(dateString);
        const isOverdue = selectedDate < today;
        
        // Add projects section if there are any projects due
        if (projectsDueToday.length > 0) {
            contentHtml += `
                <div class="proj_calendar_section">
                    <h3 class="proj_calendar_section_title">Projects (${projectsDueToday.length})</h3>
                    <div class="proj_calendar_items_list">
            `;
            
            projectsDueToday.forEach(project => {
                const statusClass = `proj_calendar_status_${project.status.replace(/\s+/g, '_')}`;
                const overdueClass = isOverdue ? 'proj_calendar_item_overdue' : '';
                
                contentHtml += `
                    <div class="proj_calendar_item ${overdueClass}" data-type="project" data-id="${project.id}">
                        <div class="proj_calendar_item_title">${project.title}</div>
                        <div class="proj_calendar_item_dates">
                            <span class="proj_calendar_date_label">Start:</span>
                            <span class="proj_calendar_date_value">${project.start_date ? this.formatDate(project.start_date) : 'Not set'}</span>
                            <span class="proj_calendar_date_label">Due:</span>
                            <span class="proj_calendar_date_value ${isOverdue ? 'proj_calendar_date_overdue' : ''}">${this.formatDate(project.end_date)}</span>
                        </div>
                        <div class="proj_calendar_item_details">
                            <span class="proj_calendar_item_status ${statusClass}">${project.status}</span>
                        </div>
                    </div>
                `;
            });
            
            contentHtml += `
                    </div>
                </div>
            `;
        }
        
        // Add stages with substages section
        if (stagesWithSubstagesDueToday.length > 0) {
            contentHtml += `
                <div class="proj_calendar_section">
                    <h3 class="proj_calendar_section_title">Stages & Substages</h3>
                    <div class="proj_calendar_items_list">
            `;
            
            stagesWithSubstagesDueToday.forEach(item => {
                const stage = item.stage;
                const project = item.project;
                const stageStatusClass = `proj_calendar_status_${stage.status.replace(/\s+/g, '_')}`;
                const stageOverdueClass = isOverdue && item.stageDueToday ? 'proj_calendar_item_overdue' : '';
                
                contentHtml += `
                    <div class="proj_calendar_item ${stageOverdueClass}" 
                         data-type="stage" data-id="${stage.id}" data-project-id="${project.id}">
                        <div class="proj_calendar_item_title">Project: ${project.title}</div>
                        <div class="proj_calendar_item_title">Stage ${stage.stage_number}</div>
                        <div class="proj_calendar_item_dates">
                            <span class="proj_calendar_date_label">Due:</span>
                            <span class="proj_calendar_date_value ${isOverdue && item.stageDueToday ? 'proj_calendar_date_overdue' : ''}">${this.formatDate(stage.end_date)}</span>
                        </div>
                        <div class="proj_calendar_item_details">
                            <span class="proj_calendar_item_status ${stageStatusClass}">${stage.status}</span>
                        </div>
                    </div>
                `;
                
                // Add substages if any are due today
                item.substagesDueToday.forEach(substage => {
                    const substageStatusClass = `proj_calendar_status_${substage.status.replace(/\s+/g, '_')}`;
                    const substageOverdueClass = isOverdue ? 'proj_calendar_item_overdue' : '';
                    
                    contentHtml += `
                        <div class="proj_calendar_item ${substageOverdueClass}" 
                             data-type="substage" data-id="${substage.id}" data-stage-id="${stage.id}" data-project-id="${project.id}">
                            <div class="proj_calendar_item_title">${substage.title || `Substage ${substage.substage_number}`}</div>
                            <div class="proj_calendar_item_dates">
                                <span class="proj_calendar_date_label">Due:</span>
                                <span class="proj_calendar_date_value ${isOverdue ? 'proj_calendar_date_overdue' : ''}">${this.formatDate(substage.end_date)}</span>
                            </div>
                            <div class="proj_calendar_item_details">
                                <span class="proj_calendar_item_status ${substageStatusClass}">${substage.status}</span>
                            </div>
                        </div>
                    `;
                });
            });
            
            contentHtml += `
                    </div>
                </div>
            `;
        }
        
        // Display modal with all items
        Swal.fire({
            title: `Due on ${this.formatDisplayDate(dateString)}`,
            html: `<div class="proj_calendar_modal_content">${contentHtml}</div>`,
            width: '600px',
            showCloseButton: true,
            showConfirmButton: false
        });
        
        // Add click handlers for items
        setTimeout(() => {
            // Click handlers for items
            document.querySelectorAll('.proj_calendar_item').forEach(item => {
                item.addEventListener('click', () => {
                    const itemType = item.dataset.type;
                    const itemId = item.dataset.id;
                    const projectId = item.dataset.projectId;
                    
                    if (itemType === 'project') {
                        window.location.href = `project_details.php?id=${itemId}`;
                    } else if (itemType === 'stage') {
                        window.location.href = `project_details.php?id=${projectId}&stage_id=${itemId}`;
                    } else if (itemType === 'substage') {
                        const stageId = item.dataset.stageId;
                        window.location.href = `project_details.php?id=${projectId}&stage_id=${stageId}&substage_id=${itemId}`;
                    }
                });
            });
        }, 100);
    }

    formatDate(dateString) {
        if (!dateString) return 'Not set';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
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

    // Dummy data for testing - remove later
    addDummyData() {
        // Generate dates for this month and next month
        const currentDate = new Date();
        const currentYear = currentDate.getFullYear();
        const currentMonth = currentDate.getMonth();
        const nextMonth = (currentMonth + 1) % 12;
        const nextMonthYear = currentMonth === 11 ? currentYear + 1 : currentYear;
        
        // Get a few dates from this month
        const date1 = new Date(currentYear, currentMonth, 10);
        const date2 = new Date(currentYear, currentMonth, 15);
        const date3 = new Date(currentYear, currentMonth, 15); // Same day as date2
        const date4 = new Date(currentYear, currentMonth, 15); // Same day as date2
        const date5 = new Date(currentYear, currentMonth, 20);
        const date6 = new Date(currentYear, currentMonth, 25);
        
        // Get a few dates from next month
        const nextDate1 = new Date(nextMonthYear, nextMonth, 5);
        const nextDate2 = new Date(nextMonthYear, nextMonth, 12);
        
        // Format dates
        const formatDate = (date) => {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} 00:00:00`;
        };
        
        // Create dummy projects
        const dummyProjects = [
            {
                id: 1001,
                title: "Modern Office Renovation",
                description: "Complete renovation of a 3-floor office building with modern design elements",
                project_type: "architecture",
                category_id: 1,
                start_date: formatDate(new Date(currentYear, currentMonth - 1, 15)),
                end_date: formatDate(date1),
                status: "in_progress",
                created_by: 1,
                assigned_to: 1,
                stages: [
                    {
                        id: 2001,
                        project_id: 1001,
                        stage_number: 1,
                        assigned_to: 1,
                        start_date: formatDate(new Date(currentYear, currentMonth - 1, 15)),
                        end_date: formatDate(date2),
                        status: "in_progress",
                        substages: [
                            {
                                id: 3001,
                                stage_id: 2001,
                                substage_number: 1,
                                title: "Initial Concept Design",
                                assigned_to: 1,
                                start_date: formatDate(new Date(currentYear, currentMonth - 1, 15)),
                                end_date: formatDate(date1),
                                status: "completed",
                                substage_identifier: "CD-001",
                                drawing_number: "A-101"
                            },
                            {
                                id: 3002,
                                stage_id: 2001,
                                substage_number: 2,
                                title: "Detailed Floor Plans",
                                assigned_to: 1,
                                start_date: formatDate(new Date(currentYear, currentMonth - 1, 20)),
                                end_date: formatDate(date2),
                                status: "in_progress",
                                substage_identifier: "CD-002",
                                drawing_number: "A-102"
                            }
                        ]
                    }
                ]
            },
            {
                id: 1002,
                title: "Luxury Apartment Complex",
                description: "Design and construction of a 12-unit luxury apartment complex",
                project_type: "architecture",
                category_id: 1,
                start_date: formatDate(new Date(currentYear, currentMonth - 2, 1)),
                end_date: formatDate(date3),
                status: "in_progress",
                created_by: 1,
                assigned_to: 1,
                stages: [
                    {
                        id: 2002,
                        project_id: 1002,
                        stage_number: 1,
                        assigned_to: 1,
                        start_date: formatDate(new Date(currentYear, currentMonth - 2, 1)),
                        end_date: formatDate(date2),
                        status: "in_progress",
                        substages: []
                    }
                ]
            },
            {
                id: 1003,
                title: "Corporate Headquarters Interior",
                description: "Interior design for new corporate headquarters",
                project_type: "interior",
                category_id: 2,
                start_date: formatDate(new Date(currentYear, currentMonth - 1, 5)),
                end_date: formatDate(date4),
                status: "in_review",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            {
                id: 1004,
                title: "Retail Store Renovation",
                description: "Complete renovation of flagship retail store",
                project_type: "construction",
                category_id: 3,
                start_date: formatDate(new Date(currentYear, currentMonth, 1)),
                end_date: formatDate(date5),
                status: "not_started",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            {
                id: 1005,
                title: "Residential Remodel",
                description: "Complete remodel of a 4-bedroom residential home",
                project_type: "construction",
                category_id: 3,
                start_date: formatDate(new Date(currentYear, currentMonth, 5)),
                end_date: formatDate(date6),
                status: "not_started",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            {
                id: 1006,
                title: "Urban Park Design",
                description: "Design of a 5-acre urban park with recreational facilities",
                project_type: "architecture",
                category_id: 1,
                start_date: formatDate(new Date(currentYear, currentMonth, 15)),
                end_date: formatDate(nextDate1),
                status: "not_started",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            {
                id: 1007,
                title: "Hotel Lobby Redesign",
                description: "Redesign of the main lobby of a luxury hotel",
                project_type: "interior",
                category_id: 2,
                start_date: formatDate(new Date(currentYear, currentMonth, 20)),
                end_date: formatDate(nextDate2),
                status: "not_started",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            // Additional projects for the 15th to test scrolling
            {
                id: 1008,
                title: "Community Center Expansion",
                description: "Expansion of community center with new gym and recreational areas",
                project_type: "architecture",
                category_id: 1,
                start_date: formatDate(new Date(currentYear, currentMonth, 1)),
                end_date: formatDate(date2), // 15th
                status: "in_progress",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            {
                id: 1009,
                title: "Restaurant Kitchen Remodel",
                description: "Complete remodel of restaurant kitchen with modern equipment",
                project_type: "construction",
                category_id: 3,
                start_date: formatDate(new Date(currentYear, currentMonth, 5)),
                end_date: formatDate(date2), // 15th
                status: "not_started",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            {
                id: 1010,
                title: "Executive Office Suite",
                description: "Design and construction of executive office suite",
                project_type: "interior",
                category_id: 2,
                start_date: formatDate(new Date(currentYear, currentMonth, 8)),
                end_date: formatDate(date2), // 15th
                status: "not_started",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            {
                id: 1011,
                title: "Museum Gallery Renovation",
                description: "Renovation of main gallery space in city museum",
                project_type: "architecture",
                category_id: 1,
                start_date: formatDate(new Date(currentYear, currentMonth, 10)),
                end_date: formatDate(date2), // 15th
                status: "not_started",
                created_by: 1,
                assigned_to: 1,
                stages: []
            },
            {
                id: 1012,
                title: "Medical Office Building",
                description: "New construction of a 3-story medical office building",
                project_type: "construction",
                category_id: 3,
                start_date: formatDate(new Date(currentYear, currentMonth, 1)),
                end_date: formatDate(date2), // 15th
                status: "in_progress",
                created_by: 1,
                assigned_to: 1,
                stages: []
            }
        ];
        
        // Add dummy projects to the user projects array
        this.userProjects = [...this.userProjects, ...dummyProjects];
        
        // Re-render calendar with dummy data
        this.renderCalendar();
        
        console.log("Dummy data added for testing multiple projects. Remove before production.");
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.projectOverview = new ProjectOverview();
}); 