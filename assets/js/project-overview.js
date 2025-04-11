class ProjectOverview {
    constructor() {
        this.initializeOverview();
        this.initializeFilters();
        this.initializeViewToggle();
        this.initializeCalendar();
        this.currentDate = new Date();
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

        // Add days of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const isToday = this.isToday(year, month, day);
            const hasEvents = this.checkForEvents(year, month, day);
            const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            daysHtml += `
                <div class="calendar-day${isToday ? ' today' : ''}${hasEvents ? ' has-events' : ''}" 
                     data-date="${date}">
                    <div class="calendar-day-number">${day}</div>
                    ${hasEvents ? this.getEventsSummary(year, month, day) : ''}
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

    isToday(year, month, day) {
        const today = new Date();
        return year === today.getFullYear() && 
               month === today.getMonth() && 
               day === today.getDate();
    }

    checkForEvents(year, month, day) {
        // This should be implemented to check if there are events on this day
        // For now, return random true/false for demonstration
        return Math.random() > 0.7;
    }

    getEventsSummary(year, month, day) {
        // This should be implemented to get actual events
        // For now, return dummy data for demonstration
        const numProjects = Math.floor(Math.random() * 3) + 1;
        return `
            <div class="calendar-day-events">
                ${numProjects} Project${numProjects > 1 ? 's' : ''} Due
            </div>
        `;
    }

    handleDayClick(dateString) {
        // This should be implemented to handle day clicks
        // For now, just log the date
        console.log('Day clicked:', dateString);
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.projectOverview = new ProjectOverview();
}); 