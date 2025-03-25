document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggle-btn');
    
    // Toggle sidebar collapse/expand
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
    });
    
    // For mobile: click outside to close expanded sidebar
    document.addEventListener('click', function(e) {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile && !sidebar.contains(e.target) && sidebar.classList.contains('expanded')) {
            sidebar.classList.remove('expanded');
        }
    });
    
    // For mobile: toggle expanded class
    if (window.innerWidth <= 768) {
        sidebar.addEventListener('click', function(e) {
            if (e.target.closest('a')) return; // Allow clicking links
            
            if (!sidebar.classList.contains('expanded')) {
                e.stopPropagation();
                sidebar.classList.add('expanded');
            }
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('expanded');
        }
    });
    
    // Add greeting and punch-in functionality
    function updateGreeting() {
        const greetingElement = document.getElementById('greeting-text');
        const timeElement = document.getElementById('current-time');
        const dateElement = document.getElementById('current-date');
        const sunIcon = document.querySelector('.sun-icon-container i');
        
        const now = new Date();
        const hour = now.getHours();
        
        // Set greeting based on time of day
        let greeting;
        if (hour < 12) {
            greeting = "Good morning";
            // Morning sun
            sunIcon.className = 'fas fa-sun rotating-sun';
        } else if (hour < 18) {
            greeting = "Good afternoon";
            // Afternoon sun with cloud
            sunIcon.className = 'fas fa-cloud-sun rotating-sun';
        } else {
            greeting = "Good evening";
            // Evening moon
            sunIcon.className = 'fas fa-moon rotating-sun';
        }
        
        // Format time
        const timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
        const formattedTime = now.toLocaleTimeString(undefined, timeOptions);
        
        // Format date
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const formattedDate = now.toLocaleDateString(undefined, dateOptions);
        
        // Update DOM
        greetingElement.innerHTML = `
            <span class="sun-icon-container">
                <i class="${sunIcon.className}"></i>
            </span>
            ${greeting}
        `;
        timeElement.textContent = formattedTime;
        dateElement.textContent = formattedDate;
    }
    
    // Initial update
    updateGreeting();
    
    // Update every minute
    setInterval(updateGreeting, 60000);
    
    // Punch In/Out functionality
    const punchButton = document.getElementById('punch-button');
    const punchContainer = document.querySelector('.punch-in-container');
    const punchStatus = document.querySelector('.punch-status');
    let isPunchedIn = false;
    
    punchButton.addEventListener('click', function() {
        isPunchedIn = !isPunchedIn;
        
        if (isPunchedIn) {
            punchContainer.classList.add('punched-in');
            punchButton.querySelector('.punch-text').textContent = 'Punch Out';
            punchButton.querySelector('.punch-icon i').classList.remove('fa-fingerprint');
            punchButton.querySelector('.punch-icon i').classList.add('fa-sign-out-alt');
            
            const now = new Date();
            const timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
            const punchInTime = now.toLocaleTimeString(undefined, timeOptions);
            punchStatus.textContent = `Punched in at ${punchInTime}`;
        } else {
            punchContainer.classList.remove('punched-in');
            punchButton.querySelector('.punch-text').textContent = 'Punch In';
            punchButton.querySelector('.punch-icon i').classList.remove('fa-sign-out-alt');
            punchButton.querySelector('.punch-icon i').classList.add('fa-fingerprint');
            
            const now = new Date();
            const timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
            const punchOutTime = now.toLocaleTimeString(undefined, timeOptions);
            punchStatus.textContent = `Punched out at ${punchOutTime}`;
        }
    });
    
    // Enhanced Calendar functionality
    const calendarDays = document.getElementById('calendar-days');
    const calendarMonth = document.getElementById('calendar-month');
    const prevBtn = document.querySelector('.calendar-nav.prev');
    const nextBtn = document.querySelector('.calendar-nav.next');
    
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();
    
    // Sample events data (for demonstration)
    const events = [
        { date: new Date(currentYear, currentMonth, 15), type: 'meeting' },
        { date: new Date(currentYear, currentMonth, 20), type: 'holiday' },
        { date: new Date(currentYear, currentMonth, 25), type: 'leave' }
    ];
    
    function generateCalendar(month, year) {
        // Clear previous days
        calendarDays.innerHTML = '';
        
        // Set the month and year in the header
        const monthName = new Date(year, month).toLocaleString('default', { month: 'long' });
        calendarMonth.textContent = `${monthName} ${year}`;
        
        // Get the first day of the month
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        
        // Get the day of the week for the first day (0-6, where 0 is Sunday)
        const startingDay = firstDay.getDay();
        
        // Get the total number of days in the month
        const monthLength = lastDay.getDate();
        
        // Get the day of the last day of the previous month
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        
        // Calculate the number of rows needed
        const totalDays = 42; // 6 rows of 7 days
        
        // Create days from previous month
        for (let i = startingDay - 1; i >= 0; i--) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day other-month';
            dayElement.textContent = prevMonthLastDay - i;
            calendarDays.appendChild(dayElement);
        }
        
        // Create days of current month
        for (let i = 1; i <= monthLength; i++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            
            // Check if it's today
            if (i === currentDate.getDate() && month === currentDate.getMonth() && year === currentDate.getFullYear()) {
                dayElement.classList.add('today');
            }
            
            // Check if there are events on this day
            const currentDateCheck = new Date(year, month, i);
            const hasEvent = events.some(event => 
                event.date.getDate() === currentDateCheck.getDate() && 
                event.date.getMonth() === currentDateCheck.getMonth() && 
                event.date.getFullYear() === currentDateCheck.getFullYear()
            );
            
            if (hasEvent) {
                dayElement.classList.add('event');
            }
            
            dayElement.textContent = i;
            dayElement.dataset.date = `${year}-${month + 1}-${i}`;
            
            // Add click event listener
            dayElement.addEventListener('click', function() {
                const selectedDay = document.querySelector('.calendar-day.selected');
                if (selectedDay) {
                    selectedDay.classList.remove('selected');
                }
                this.classList.add('selected');
                // Here you could show events for the selected day
            });
            
            calendarDays.appendChild(dayElement);
        }
        
        // Calculate how many days from next month we need to complete the calendar
        const remainingDays = totalDays - (startingDay + monthLength);
        
        // Create days from next month
        for (let i = 1; i <= remainingDays; i++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day other-month';
            dayElement.textContent = i;
            calendarDays.appendChild(dayElement);
        }
    }
    
    // Navigate to previous month
    prevBtn.addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    // Navigate to next month
    nextBtn.addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    // Initialize calendar with current month and year
    generateCalendar(currentMonth, currentYear);
    
    // Update the calendar every day at midnight
    function scheduleCalendarUpdate() {
        const now = new Date();
        const tomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
        const timeUntilMidnight = tomorrow - now;
        
        setTimeout(function() {
            currentDate = new Date();
            generateCalendar(currentMonth, currentYear);
            scheduleCalendarUpdate(); // Schedule the next update
        }, timeUntilMidnight);
    }
    
    scheduleCalendarUpdate();
    
    // Date Filter functionality
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.classList.toggle('active');
            // Here you can add logic to show/hide the date picker
            // or custom dropdown menu
        });
    });
    
    // Close filters when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.date-filter')) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
        }
    });
    
    // Tooltip functionality
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach(card => {
        const tooltipId = card.getAttribute('data-tooltip');
        const tooltip = document.getElementById(tooltipId);
        
        if (!tooltip) {
            console.warn(`Tooltip with id ${tooltipId} not found`);
            return;
        }
        
        card.addEventListener('mouseenter', (e) => {
            // Hide all other tooltips first
            document.querySelectorAll('.tooltip').forEach(t => {
                t.style.visibility = 'hidden';
                t.style.opacity = '0';
            });
            
            // Position the tooltip
            const cardRect = card.getBoundingClientRect();
            tooltip.style.top = `${cardRect.bottom + 10}px`;
            tooltip.style.left = `${cardRect.left}px`;
            
            // Show the tooltip
            tooltip.style.visibility = 'visible';
            tooltip.style.opacity = '1';
        });
        
        // Handle tooltip hover
        tooltip.addEventListener('mouseenter', () => {
            tooltip.style.visibility = 'visible';
            tooltip.style.opacity = '1';
        });
        
        tooltip.addEventListener('mouseleave', () => {
            tooltip.style.visibility = 'hidden';
            tooltip.style.opacity = '0';
        });
    });
    
    // Handle card mouseleave
    document.addEventListener('mouseover', (e) => {
        if (!e.target.closest('.stat-card') && !e.target.closest('.tooltip')) {
            document.querySelectorAll('.tooltip').forEach(tooltip => {
                tooltip.style.visibility = 'hidden';
                tooltip.style.opacity = '0';
            });
        }
    });
    
    // Project tooltip functionality
    const projectStatCards = document.querySelectorAll('.project-stat-card');
    
    projectStatCards.forEach(card => {
        const tooltipId = card.getAttribute('data-tooltip');
        const tooltip = document.getElementById(tooltipId);
        
        if (!tooltip) {
            console.warn(`Project tooltip with id ${tooltipId} not found`);
            return;
        }
        
        card.addEventListener('mouseenter', (e) => {
            // Hide all other tooltips first
            document.querySelectorAll('.project-tooltip').forEach(t => {
                t.style.visibility = 'hidden';
                t.style.opacity = '0';
            });
            
            // Position the tooltip
            const cardRect = card.getBoundingClientRect();
            tooltip.style.top = `${cardRect.bottom + 10}px`;
            tooltip.style.left = `${cardRect.left}px`;
            
            // Show the tooltip
            tooltip.style.visibility = 'visible';
            tooltip.style.opacity = '1';
        });
        
        // Handle tooltip hover
        tooltip.addEventListener('mouseenter', () => {
            tooltip.style.visibility = 'visible';
            tooltip.style.opacity = '1';
        });
        
        tooltip.addEventListener('mouseleave', () => {
            tooltip.style.visibility = 'hidden';
            tooltip.style.opacity = '0';
        });
    });
    
    // Handle card mouseleave
    document.addEventListener('mouseover', (e) => {
        if (!e.target.closest('.project-stat-card') && !e.target.closest('.project-tooltip')) {
            document.querySelectorAll('.project-tooltip').forEach(tooltip => {
                tooltip.style.visibility = 'hidden';
                tooltip.style.opacity = '0';
            });
        }
    });
    
    // Project View Toggle Functionality - Using unique function names
    const projectViewToggle = document.getElementById('projectViewToggleSwitch');
    const projectViewStatusLabel = document.getElementById('projectViewStatusLabel');
    const projectViewDeptLabel = document.getElementById('projectViewDeptLabel');
    const projectStatisticsView = document.getElementById('projectStatisticsView');
    const projectDepartmentCalendarView = document.getElementById('projectDepartmentCalendarView');
    
    if (projectViewToggle) {
        projectViewToggle.addEventListener('change', function() {
            // Toggle the active class for labels
            projectViewStatusLabel.classList.toggle('active');
            projectViewDeptLabel.classList.toggle('active');
            
            // Switch between views
            if (this.checked) {
                // Show calendar view, hide stats view
                fadeViewsTransition(projectStatisticsView, projectDepartmentCalendarView);
                initializeProjectCalendarView();
            } else {
                // Show stats view, hide calendar view
                fadeViewsTransition(projectDepartmentCalendarView, projectStatisticsView);
            }
        });
    }
    
    // Helper function for smooth transition between views
    function fadeViewsTransition(elementToHide, elementToShow) {
        elementToHide.style.opacity = 0;
        
        setTimeout(() => {
            elementToHide.style.display = 'none';
            elementToShow.style.display = 'block';
            
            // Force reflow
            void elementToShow.offsetWidth;
            
            elementToShow.style.opacity = 1;
        }, 300);
    }
    
    // Initialize the project calendar view
    function initializeProjectCalendarView() {
        if (!window.projectCalendarInitialized) {
            generateProjectCalendarDays();
            setupProjectCalendarControls();
            window.projectCalendarInitialized = true;
        }
    }
    
    // Generate calendar days
    function generateProjectCalendarDays() {
        const calendarDaysContainer = document.querySelector('.project-calendar-days');
        if (!calendarDaysContainer) return;
        
        calendarDaysContainer.innerHTML = '';
        
        // Current month data
        const currentDate = new Date();
        const currentMonth = currentDate.getMonth();
        const currentYear = currentDate.getFullYear();
        
        // First day of the month
        const firstDay = new Date(currentYear, currentMonth, 1);
        // Last day of the month
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        
        // Days from previous month
        const startingDayOfWeek = firstDay.getDay(); // 0 (Sunday) to 6 (Saturday)
        
        // Sample project data - would come from your backend
        const projectEvents = [
            { date: '2025-03-05', title: 'Website Redesign', department: 'Engineering', color: '#4361ee' },
            { date: '2025-03-10', title: 'Launch Campaign', department: 'Marketing', color: '#10B981' },
            { date: '2025-03-15', title: 'Client Meeting', department: 'Sales', color: '#F59E0B' },
            { date: '2025-03-20', title: 'UI Mockups', department: 'Design', color: '#EC4899' },
            { date: '2025-03-25', title: 'Sprint Review', department: 'Engineering', color: '#4361ee' }
        ];
        
        // Create days from previous month
        for (let i = startingDayOfWeek - 1; i >= 0; i--) {
            const dayElement = document.createElement('div');
            dayElement.className = 'project-calendar-day other-month';
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'project-calendar-day-number';
            dayNumber.textContent = new Date(currentYear, currentMonth, -i).getDate();
            
            dayElement.appendChild(dayNumber);
            calendarDaysContainer.appendChild(dayElement);
        }
        
        // Create days of current month
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'project-calendar-day';
            
            // Check if it's today
            const currentDay = new Date();
            if (day === currentDay.getDate() && currentMonth === currentDay.getMonth() && currentYear === currentDay.getFullYear()) {
                dayElement.classList.add('today');
            }
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'project-calendar-day-number';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            // Add events for this day
            const dayString = `2025-03-${day.toString().padStart(2, '0')}`;
            const dayEvents = projectEvents.filter(event => event.date === dayString);
            
            dayEvents.forEach(event => {
                const eventElement = document.createElement('div');
                eventElement.className = 'project-calendar-event';
                eventElement.textContent = event.title;
                eventElement.style.backgroundColor = event.color;
                dayElement.appendChild(eventElement);
            });
            
            calendarDaysContainer.appendChild(dayElement);
        }
        
        // Fill remaining grid with days from next month
        const daysAdded = startingDayOfWeek + lastDay.getDate();
        const remainingCells = 42 - daysAdded; // 6 rows of 7 days
        
        for (let day = 1; day <= remainingCells; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'project-calendar-day other-month';
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'project-calendar-day-number';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            calendarDaysContainer.appendChild(dayElement);
        }
    }
    
    // Setup calendar navigation and view controls
    function setupProjectCalendarControls() {
        const prevBtn = document.querySelector('.project-calendar-prev-btn');
        const nextBtn = document.querySelector('.project-calendar-next-btn');
        const viewBtns = document.querySelectorAll('.project-calendar-view-btn');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                // Navigate to previous month
                console.log('Navigate to previous month');
                // In a real implementation, you would update the calendar
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                // Navigate to next month
                console.log('Navigate to next month');
                // In a real implementation, you would update the calendar
            });
        }
        
        if (viewBtns.length) {
            viewBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons
                    viewBtns.forEach(b => b.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const viewType = this.getAttribute('data-view');
                    console.log(`Switch to ${viewType} view`);
                    // In a real implementation, you would switch the calendar view
                });
            });
        }
    }
    
    // Date picker initialization
    const dateInputs = document.querySelectorAll('.date-input');
    const datePickerButtons = document.querySelectorAll('.date-picker-button');
    
    // For a real implementation, you would use a date picker library
    // This is a simplified example that toggles a class for visual feedback
    dateInputs.forEach((input, index) => {
        input.addEventListener('click', function() {
            this.classList.toggle('active');
            // Here you would open your date picker
        });
        
        datePickerButtons[index].addEventListener('click', function() {
            dateInputs[index].classList.toggle('active');
            // Here you would open your date picker
        });
    });
    
    // Apply filter button
    const applyFilterBtn = document.querySelector('.apply-filter-btn');
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', function() {
            const fromDate = document.getElementById('project-date-from').value;
            const toDate = document.getElementById('project-date-to').value;
            
            console.log(`Filtering projects from ${fromDate} to ${toDate}`);
            // Add logic to filter projects by date range
        });
    }
});