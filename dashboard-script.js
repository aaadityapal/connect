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
        
        // Get the username from PHP session
        const username = greetingElement.textContent.split(',')[1]?.trim() || '';
        
        // Set greeting based on time of day
        let greeting;
        if (hour < 12) {
            greeting = "Good morning";
            sunIcon.className = 'fas fa-cloud-sun ';
        } else if (hour < 18) {
            greeting = "Good afternoon";
            sunIcon.className = 'fas fa-sun rotating-sun';
        } else {
            greeting = "Good evening";
            sunIcon.className = 'fas fa-moon ';
        }
        
        // Update DOM with greeting and username
        greetingElement.innerHTML = `
            <span class="sun-icon-container">
                <i class="${sunIcon.className}"></i>
            </span>
            ${greeting}, ${username}
        `;
        
        // Format time
        const timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
        const formattedTime = now.toLocaleTimeString(undefined, timeOptions);
        
        // Format date
        const dateOptions = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const formattedDate = now.toLocaleDateString(undefined, dateOptions);
        
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

    // Modify the checkPunchStatus function
    async function checkPunchStatus() {
        try {
            const response = await fetch('punch_attendance.php?action=check_status');
            const data = await response.json();
            
            console.log('Punch status response:', data); // Debug log
            
            if (data.success) {
                if (data.is_punched_in && !data.is_punched_out) {
                    // User is punched in but not out
                    console.log('User is punched in'); // Debug log
                    setPunchedInState(new Date(data.punch_in_time));
                } else if (data.is_punched_out) {
                    // User has completed their punch for the day
                    console.log('User is punched out'); // Debug log
                    setPunchedOutState(new Date(data.punch_out_time), data.working_hours);
                } else {
                    // Not punched in yet
                    console.log('User not punched in'); // Debug log
                    resetPunchState();
                }
            }
        } catch (error) {
            console.error('Error checking punch status:', error);
            showNotification('Error checking punch status', 'error');
        }
    }

    // Add a reset function for punch state
    function resetPunchState() {
        console.log('Resetting punch state'); // Debug log
        punchContainer.classList.remove('punched-in');
        punchButton.querySelector('.punch-text').textContent = 'Punch In';
        
        const punchIcon = punchButton.querySelector('.punch-icon i');
        punchIcon.classList.remove('fa-sign-out-alt');
        punchIcon.classList.add('fa-fingerprint');
        
        punchButton.disabled = false;
        punchStatus.textContent = 'Not punched in today';
    }

    // Update setPunchedInState function
    function setPunchedInState(punchInTime) {
        console.log('Setting punched in state'); // Debug log
            punchContainer.classList.add('punched-in');
            punchButton.querySelector('.punch-text').textContent = 'Punch Out';
        
        // Remove fingerprint icon and add sign-out icon
        const punchIcon = punchButton.querySelector('.punch-icon i');
        punchIcon.classList.remove('fa-fingerprint');
        punchIcon.classList.add('fa-sign-out-alt');
        
        punchButton.disabled = false;
        
            const timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
        const punchInTimeStr = new Date(punchInTime).toLocaleTimeString(undefined, timeOptions);
        punchStatus.textContent = `Punched in at ${punchInTimeStr}`;
    }

    // Update setPunchedOutState function
    function setPunchedOutState(punchOutTime, workingHours) {
        console.log('Setting punched out state'); // Debug log
            punchContainer.classList.remove('punched-in');
            punchButton.querySelector('.punch-text').textContent = 'Punch In';
        
        // Remove sign-out icon and add fingerprint icon
        const punchIcon = punchButton.querySelector('.punch-icon i');
        punchIcon.classList.remove('fa-sign-out-alt');
        punchIcon.classList.add('fa-fingerprint');
        
        punchButton.disabled = true;
        
            const timeOptions = { hour: 'numeric', minute: 'numeric', hour12: true };
        const punchOutTimeStr = new Date(punchOutTime).toLocaleTimeString(undefined, timeOptions);
        
        if (workingHours) {
            punchStatus.innerHTML = `
                <div>Punched out at ${punchOutTimeStr}</div>
                <div class="working-time">Total working time: ${workingHours}</div>
            `;
        } else {
            punchStatus.textContent = `Punched out at ${punchOutTimeStr}`;
        }
    }

    // Show notification
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `punch-notification ${type}`;
        
        // Choose icon based on notification type
        let icon;
        switch(type) {
            case 'success':
                icon = 'fa-check-circle';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                break;
            case 'error':
                icon = 'fa-exclamation-circle';
                break;
            case 'info':
                icon = 'fa-info-circle';
                break;
            default:
                icon = 'fa-info-circle';
        }
        
        notification.innerHTML = `
            <i class="fas ${icon}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    // Add these variables at the top with your other declarations
    const workReportModal = document.getElementById('workReportModal');
    const closeWorkReport = document.getElementById('closeWorkReport');
    const cancelWorkReport = document.getElementById('cancelWorkReport');
    const submitWorkReport = document.getElementById('submitWorkReport');
    const workReportTextarea = document.getElementById('workReport');

    // Update the punch button click handler
    punchButton.addEventListener('click', async function() {
        if (punchButton.disabled) return;
        
        try {
            const isPunchedIn = punchContainer.classList.contains('punched-in');
            const action = isPunchedIn ? 'punch_out' : 'punch_in';
            
            console.log('Punch button clicked:', action); // Debug log
            
            if (action === 'punch_out') {
                workReportModal.style.display = 'flex';
                return;
            }
            
            const response = await fetch('punch_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'punch_in' })
            });
            
            const data = await response.json();
            console.log('Punch in response:', data); // Debug log
            
            if (data.success) {
                setPunchedInState(new Date());
                showNotification('Successfully punched in!');
            } else {
                showNotification(data.message || 'An error occurred', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Failed to process punch request', 'error');
        }
    });

    // Update the work report submission handler
    submitWorkReport.addEventListener('click', async function() {
        const workReport = workReportTextarea.value.trim();
        
        if (!workReport) {
            showNotification('Please enter your work report', 'warning');
            return;
        }
        
        try {
            console.log('Submitting work report'); // Debug log
            
            const response = await fetch('punch_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'punch_out',
                    work_report: workReport
                })
            });
            
            const data = await response.json();
            console.log('Punch out response:', data); // Debug log
            
            if (data.success) {
                workReportModal.style.display = 'none';
                setPunchedOutState(new Date(), data.workingTime);
                showNotification('Successfully punched out!');
                if (data.workingTime) {
                    showNotification(`Total working time: ${data.workingTime}`, 'info');
                }
                workReportTextarea.value = '';
            } else {
                showNotification(data.message || 'An error occurred', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Failed to process punch out', 'error');
        }
    });

    // Handle modal close and cancel
    function closeWorkReportModal() {
        workReportModal.style.display = 'none';
        workReportTextarea.value = '';
    }

    closeWorkReport.addEventListener('click', closeWorkReportModal);
    cancelWorkReport.addEventListener('click', closeWorkReportModal);

    // Close modal when clicking outside
    workReportModal.addEventListener('click', function(e) {
        if (e.target === workReportModal) {
            closeWorkReportModal();
        }
    });

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && workReportModal.style.display === 'flex') {
            closeWorkReportModal();
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
            { date: '2025-03-05', title: 'Modern Villa Design', type: 'Architecture', color: '#4361ee' },
            { date: '2025-03-10', title: 'Office Space Planning', type: 'Interior', color: '#10B981' },
            { date: '2025-03-15', title: 'Residential Complex', type: 'Construction', color: '#F59E0B' },
            { date: '2025-03-20', title: 'Hotel Renovation', type: 'Architecture', color: '#4361ee' },
            { date: '2025-03-25', title: 'Mall Construction', type: 'Construction', color: '#F59E0B' }
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
                eventElement.className = `project-calendar-event project-type-${event.type.toLowerCase()}`;
                eventElement.textContent = event.title;
                
                // Add tooltip with more information
                eventElement.title = `${event.title} (${event.type})`;
                
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
        const monthDisplay = document.querySelector('.project-calendar-month');
        const calendarDays = document.querySelector('.project-calendar-days');
        let currentDate = new Date();
        let projectsData = null;

        // Fetch projects data
        async function fetchProjects() {
            try {
                const response = await fetch('fetch_projects.php');
                const data = await response.json();
                if (data.success) {
                    projectsData = data;
                    updateCalendar(); // Update calendar after fetching data
                } else {
                    console.error('Failed to fetch projects:', data.error);
                }
            } catch (error) {
                console.error('Error fetching projects:', error);
            }
        }

        function getProjectTypeColor(project) {
            // Define colors for different project types
            const typeColors = {
                'Architecture': '#4361ee',
                'Interior': '#10B981',
                'Construction': '#F59E0B'
            };
            return typeColors[project.project_type] || '#4361ee'; // Default color if type not found
        }

        function getProjectsForDate(date) {
            if (!projectsData || !projectsData.projects) {
                return [];
            }
            
            const dateStr = date.toISOString().split('T')[0];
            
            return projectsData.projects.filter(project => {
                const startDate = new Date(project.start_date);
                startDate.setHours(0, 0, 0, 0);
                
                const checkDate = new Date(date);
                checkDate.setHours(0, 0, 0, 0);
                
                return startDate.getTime() === checkDate.getTime();
            });
        }

        function generateCalendarDays(date) {
            calendarDays.innerHTML = '';
            
            const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
            const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
            const startingDay = firstDay.getDay();
            const totalDays = lastDay.getDate();
            const prevMonthLastDay = new Date(date.getFullYear(), date.getMonth(), 0).getDate();

            // Previous month days
            for (let i = startingDay - 1; i >= 0; i--) {
                const dayElement = document.createElement('div');
                dayElement.className = 'project-calendar-day other-month';
                dayElement.innerHTML = `
                    <div class="project-calendar-day-number">${prevMonthLastDay - i}</div>
                `;
                calendarDays.appendChild(dayElement);
            }

            // Current month days
            for (let i = 1; i <= totalDays; i++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'project-calendar-day';
                
                const currentDayDate = new Date(date.getFullYear(), date.getMonth(), i);
                const isToday = currentDayDate.toDateString() === new Date().toDateString();
                if (isToday) {
                    dayElement.classList.add('today');
                }

                // Add day number
                const dayNumber = document.createElement('div');
                dayNumber.className = 'project-calendar-day-number';
                dayNumber.textContent = i;
                dayElement.appendChild(dayNumber);

                // Create projects container
                const projectsContainer = document.createElement('div');
                projectsContainer.className = 'projects-container';

                // Add more indicator
                const moreIndicator = document.createElement('div');
                moreIndicator.className = 'more-projects-indicator';
                dayElement.appendChild(moreIndicator);

                // Get projects for this day
                const dayProjects = getProjectsForDate(currentDayDate);

                // Add projects to the container
                dayProjects.forEach(project => {
                    const projectElement = document.createElement('div');
                    projectElement.className = 'project-calendar-event';
                    projectElement.classList.add(`project-type-${project.project_type.toLowerCase()}`);
                    projectElement.textContent = project.title;
                    projectElement.dataset.projectType = project.project_type;
                    projectElement.addEventListener('click', () => openProjectDetailsModal(project.id));
                    projectsContainer.appendChild(projectElement);
                });

                // Add projects container to day element
                dayElement.appendChild(projectsContainer);

                // Show more indicator if there are more than 2 projects
                if (dayProjects.length > 2) {
                    moreIndicator.style.display = 'block';
                }

                // Add scroll event listener to show/hide indicator
                projectsContainer.addEventListener('scroll', function() {
                    const isScrolledToBottom = this.scrollHeight - this.scrollTop === this.clientHeight;
                    moreIndicator.style.display = isScrolledToBottom ? 'none' : 'block';
                });

                calendarDays.appendChild(dayElement);
            }

            // Next month days
            const remainingDays = 42 - (startingDay + totalDays);
            for (let i = 1; i <= remainingDays; i++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'project-calendar-day other-month';
                dayElement.innerHTML = `
                    <div class="project-calendar-day-number">${i}</div>
                `;
                calendarDays.appendChild(dayElement);
            }
        }

        function updateCalendar() {
            // Update month display
            monthDisplay.textContent = currentDate.toLocaleDateString('en-US', { 
                month: 'long', 
                year: 'numeric' 
            });
            
            // Generate calendar days
            generateCalendarDays(currentDate);
        }

        // Event listeners for navigation
        prevBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            updateCalendar();
        });

        nextBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            updateCalendar();
        });

        // Initial fetch and setup
        fetchProjects();

        // Refresh projects data every 5 minutes
        setInterval(fetchProjects, 300000);
    }

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        if (document.querySelector('.project-calendar-view')) {
            setupProjectCalendarControls();
        }
    });
    
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

    // Avatar dropdown functionality
    const avatarBtn = document.getElementById('avatarBtn');
    const avatarDropdown = document.getElementById('avatarDropdown');

    // Toggle dropdown on avatar click
    avatarBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        avatarDropdown.classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!avatarBtn.contains(e.target) && !avatarDropdown.contains(e.target)) {
            avatarDropdown.classList.remove('show');
        }
    });

    // Prevent dropdown from closing when clicking inside it
    avatarDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Call checkPunchStatus when the page loads
    console.log('Page loaded, checking punch status'); // Debug log
    checkPunchStatus();

    // Add this function after your existing code
    async function updateUserCounts() {
        try {
            const response = await fetch('fetch_user_count.php');
            const data = await response.json();
            
            if (data.success) {
                // Update Present Today card
                const presentTotalElement = document.querySelector('.stat-card[data-tooltip="present-details"] .stat-label');
                if (presentTotalElement) {
                    presentTotalElement.textContent = `/ ${data.total_users} Total Employees`;
                }
                
                // Update Short Leave card
                const shortLeaveTotalElement = document.querySelector('.stat-card[data-tooltip="short-leave-details"] .stat-label');
                if (shortLeaveTotalElement) {
                    shortLeaveTotalElement.textContent = `/ ${data.total_users} Today's Short Leaves`;
                }
            }
        } catch (error) {
            console.error('Error fetching user count:', error);
        }
    }

    // Add this line to update user counts
    updateUserCounts();

    // Update counts every 5 minutes
    setInterval(updateUserCounts, 300000);

    async function updateAttendanceDetails() {
        try {
            const response = await fetch('fetch_attendance_details.php');
            const data = await response.json();
            
            if (data.success) {
                // Update counts
                document.getElementById('present-count').textContent = data.punched_count;
                document.getElementById('present-total').textContent = `/ ${data.total_users} Total Employees`;
                
                // Process attendance data
                let ontimeCount = 0;
                let lateCount = 0;
                const employeesList = document.getElementById('present-employees-list');
                employeesList.innerHTML = ''; // Clear existing content
                
                data.punched_users.forEach(user => {
                    // Determine if user is late based on shift time (you can adjust this logic)
                    const punchInTime = new Date(user.punch_in);
                    const isLate = !isNaN(punchInTime.getTime()) && (punchInTime.getHours() >= 9 && punchInTime.getMinutes() > 30);
                    
                    if (isLate) lateCount++;
                    else ontimeCount++;
                    
                    // Format punch-in time with error handling
                    let formattedTime = "";
                    try {
                        if (user.punch_in && !isNaN(new Date(user.punch_in).getTime())) {
                            formattedTime = new Date(user.punch_in).toLocaleTimeString([], { 
                                hour: '2-digit', 
                                minute: '2-digit' 
                            });
                        } else {
                            formattedTime = "No time";
                        }
                    } catch (err) {
                        console.error('Error formatting punch time:', err);
                        formattedTime = "Invalid time";
                    }
                    
                    // Create employee list item
                    const listItem = document.createElement('div');
                    listItem.className = 'employee-list-item';
                    listItem.innerHTML = `
                        <img src="${user.profile_picture || 'assets/default-avatar.png'}" 
                             alt="${user.username}" 
                             class="employee-avatar">
                        <div class="employee-info">
                            <div class="employee-name">${user.username}</div>
                            <div class="employee-details">
                                <span class="employee-designation">${user.designation || ''}</span>
                            </div>
                        </div>
                        <div class="punch-time">
                            ${formattedTime}
                        </div>
                        <span class="attendance-status ${isLate ? 'status-late' : 'status-ontime'}">
                            ${isLate ? 'Late' : formattedTime}
                        </span>
                    `;
                    
                    employeesList.appendChild(listItem);
                });
                
                // Update statistics
                document.getElementById('ontime-count').textContent = ontimeCount;
                document.getElementById('late-count').textContent = lateCount;
            }
        } catch (error) {
            console.error('Error fetching attendance details:', error);
        }
    }

    // Initial update
    updateAttendanceDetails();
    
    // Update every 5 minutes
    setInterval(updateAttendanceDetails, 300000);

    async function updateLeaveDetails() {
        try {
            // Fetch all leave types
            const [pendingResponse, shortLeaveResponse, onLeaveResponse] = await Promise.all([
                fetch('get_pending_leaves.php'),
                fetch('get_short_leave_details.php'),
                fetch('get_on_leave_details.php')
            ]);

            const pendingData = await pendingResponse.json();
            const shortLeaveData = await shortLeaveResponse.json();
            const onLeaveData = await onLeaveResponse.json();

            // Update Pending Leaves
            if (pendingData.success) {
                document.getElementById('pending-count').textContent = pendingData.count;
                updatePendingLeavesTooltip(pendingData.leaves);
            }

            // Update Short Leaves
            if (shortLeaveData.success) {
                document.getElementById('short-leave-count').textContent = shortLeaveData.count;
                // Update total employees display if available
                if (shortLeaveData.total_users) {
                    document.getElementById('short-leave-total').textContent = `/ ${shortLeaveData.total_users} Total Employees`;
                }
                updateShortLeavesTooltip(shortLeaveData.leaves);
            }

            // Update On Leave
            if (onLeaveData.success) {
                document.getElementById('on-leave-count').textContent = onLeaveData.count;
                updateOnLeavesTooltip(onLeaveData.leaves);
            }

        } catch (error) {
            console.error('Error fetching leave details:', error);
        }
    }

    function formatDateRange(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const options = { month: 'short', day: 'numeric' };
        
        if (startDate === endDate) {
            return start.toLocaleDateString(undefined, options);
        }
        return `${start.toLocaleDateString(undefined, options)} - ${end.toLocaleDateString(undefined, options)}`;
    }

    function updatePendingLeavesTooltip(leaves) {
        const listElement = document.getElementById('pending-leaves-list');
        listElement.innerHTML = leaves.length ? '' : '<div class="no-data">No pending leaves</div>';

        leaves.forEach(leave => {
            const listItem = document.createElement('div');
            listItem.className = 'leave-list-item';
            listItem.innerHTML = `
                <img src="${leave.profile_picture || 'assets/default-avatar.png'}" 
                     alt="${leave.username}" 
                     class="employee-avatar">
                <div class="leave-info">
                    <div class="employee-name">${leave.username}</div>
                    <div class="leave-dates">${formatDateRange(leave.start_date, leave.end_date)}</div>
                    <div class="leave-reason">${leave.reason}</div>
                </div>
                <span class="leave-type-badge" style="background-color: ${leave.color_code}20; color: ${leave.color_code}">
                    ${leave.leave_type}
                </span>
            `;
            listElement.appendChild(listItem);
        });
    }

    function updateShortLeavesTooltip(leaves) {
        const listElement = document.getElementById('short-leaves-list');
        listElement.innerHTML = leaves.length ? '' : '<div class="no-data">No short leaves today</div>';

        leaves.forEach(leave => {
            const listItem = document.createElement('div');
            listItem.className = 'leave-list-item';
            listItem.innerHTML = `
                <img src="${leave.profile_picture || 'assets/default-avatar.png'}" 
                     alt="${leave.username}" 
                     class="employee-avatar">
                <div class="leave-info">
                    <div class="employee-name">${leave.username}</div>
                    <div class="leave-dates">${formatDateRange(leave.start_date, leave.end_date)}</div>
                    <div class="leave-reason">${leave.reason}</div>
                </div>
            `;
            listElement.appendChild(listItem);
        });
    }

    function updateOnLeavesTooltip(leaves) {
        const listElement = document.getElementById('on-leave-list');
        listElement.innerHTML = leaves.length ? '' : '<div class="no-data">No employees on leave</div>';

        leaves.forEach(leave => {
            const listItem = document.createElement('div');
            listItem.className = 'leave-list-item';
            listItem.innerHTML = `
                <img src="${leave.profile_picture || 'assets/default-avatar.png'}" 
                     alt="${leave.username}" 
                     class="employee-avatar">
                <div class="leave-info">
                    <div class="employee-name">${leave.username}</div>
                    <div class="leave-dates">${formatDateRange(leave.start_date, leave.end_date)}</div>
                    <div class="leave-reason">${leave.reason}</div>
                </div>
                <span class="leave-type-badge" style="background-color: ${leave.color_code}20; color: ${leave.color_code}">
                    ${leave.leave_type}
                </span>
            `;
            listElement.appendChild(listItem);
        });
    }

    // Initial update
    updateLeaveDetails();
    
    // Update every 5 minutes
    setInterval(updateLeaveDetails, 300000);

    class EmployeeCalendar {
        constructor() {
            this.currentDate = new Date();
            this.currentMonth = this.currentDate.getMonth();
            this.currentYear = this.currentDate.getFullYear();
            
            this.calendarDays = document.getElementById('calendar-days');
            this.monthDisplay = document.getElementById('calendar-month');
            
            this.initializeCalendar();
        }
        
        async initializeCalendar() {
            // Add event listeners
            document.querySelector('.calendar-nav.prev').addEventListener('click', () => this.navigateMonth(-1));
            document.querySelector('.calendar-nav.next').addEventListener('click', () => this.navigateMonth(1));
            
            // Generate initial calendar
            await this.generateCalendar();
        }
        
        async navigateMonth(direction) {
            this.currentMonth += direction;
            
            if (this.currentMonth > 11) {
                this.currentMonth = 0;
                this.currentYear++;
            } else if (this.currentMonth < 0) {
                this.currentMonth = 11;
                this.currentYear--;
            }
            
            await this.generateCalendar();
        }
        
        async fetchCalendarData() {
            try {
                const response = await fetch(`calendar-handler.php?month=${this.currentMonth + 1}&year=${this.currentYear}`);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                
                // Initialize empty objects if data is missing
                return {
                    success: data.success,
                    attendance: data.attendance || {},
                    leaves: data.leaves || {},
                    holidays: data.holidays || {}
                };
            } catch (error) {
                console.error('Error fetching calendar data:', error);
                // Return empty data structure on error
                return {
                    success: false,
                    attendance: {},
                    leaves: {},
                    holidays: {}
                };
            }
        }
        
        async generateCalendar() {
            const calendarData = await this.fetchCalendarData();
            
            // Update month display
            const monthName = new Date(this.currentYear, this.currentMonth).toLocaleString('default', { month: 'long' });
            this.monthDisplay.textContent = `${monthName} ${this.currentYear}`;
            
            // Clear existing calendar
            this.calendarDays.innerHTML = '';
            
            // Get first day of month and total days
            const firstDay = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
            const startingDay = firstDay.getDay();
            const totalDays = lastDay.getDate();
            
            // Add previous month's days
            for (let i = startingDay - 1; i >= 0; i--) {
                const day = new Date(this.currentYear, this.currentMonth, -i);
                this.createDayElement(day, true);
            }
            
            // Add current month's days
            for (let i = 1; i <= totalDays; i++) {
                const day = new Date(this.currentYear, this.currentMonth, i);
                this.createDayElement(day, false, calendarData);
            }
            
            // Add next month's days
            const remainingDays = 42 - (startingDay + totalDays); // 6 rows of 7 days
            for (let i = 1; i <= remainingDays; i++) {
                const day = new Date(this.currentYear, this.currentMonth + 1, i);
                this.createDayElement(day, true);
            }
        }
        
        createDayElement(date, isOtherMonth, calendarData = null) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            
            if (isOtherMonth) {
                dayElement.classList.add('other-month');
            }
            
            // Check if it's today
            const isToday = date.toDateString() === new Date().toDateString();
            if (isToday) {
                dayElement.classList.add('today');
            }
            
            // Add day number
            const dayNumber = document.createElement('div');
            dayNumber.className = 'calendar-day-number';
            dayNumber.textContent = date.getDate();
            dayElement.appendChild(dayNumber);
            
            // Add events and tooltip if we have calendar data
            if (calendarData && !isOtherMonth) {
                const dateStr = date.toISOString().slice(0, 10);
                
                // Create event dots container
                const eventContainer = document.createElement('div');
                eventContainer.className = 'calendar-day-events';
                
                // Create tooltip
                const tooltip = document.createElement('div');
                tooltip.className = 'date-tooltip';
                
                let hasEvents = false;
                
                // Present users
                if (calendarData.attendance && calendarData.attendance[dateStr]) {
                    const presentCount = calendarData.attendance[dateStr];
                    const presentDot = document.createElement('span');
                    presentDot.className = 'day-event-dot present';
                    eventContainer.appendChild(presentDot);
                    
                    const presentInfo = document.createElement('div');
                    presentInfo.className = 'tooltip-item';
                    presentInfo.innerHTML = `
                        <span class="tooltip-dot" style="background-color: #22c55e;"></span>
                        <span>${presentCount} Present</span>
                    `;
                    tooltip.appendChild(presentInfo);
                    hasEvents = true;
                }
                
                // Leave users
                if (calendarData.leaves && calendarData.leaves[dateStr]) {
                    const leaveCount = calendarData.leaves[dateStr];
                    const leaveDot = document.createElement('span');
                    leaveDot.className = 'day-event-dot leave';
                    eventContainer.appendChild(leaveDot);
                    
                    const leaveInfo = document.createElement('div');
                    leaveInfo.className = 'tooltip-item';
                    leaveInfo.innerHTML = `
                        <span class="tooltip-dot" style="background-color: #f97316;"></span>
                        <span>${leaveCount} On Leave</span>
                    `;
                    tooltip.appendChild(leaveInfo);
                    hasEvents = true;
                }
                
                // Holiday
                if (calendarData.holidays && calendarData.holidays[dateStr]) {
                    const holidayName = calendarData.holidays[dateStr];
                    const holidayDot = document.createElement('span');
                    holidayDot.className = 'day-event-dot holiday';
                    eventContainer.appendChild(holidayDot);
                    
                    const holidayInfo = document.createElement('div');
                    holidayInfo.className = 'tooltip-holiday';
                    holidayInfo.innerHTML = `
                        <span class="tooltip-dot" style="background-color: #ec4899;"></span>
                        <span>${holidayName}</span>
                    `;
                    tooltip.appendChild(holidayInfo);
                    hasEvents = true;
                }
                
                // Only append containers if there are events
                if (eventContainer.children.length > 0) {
                    dayElement.appendChild(eventContainer);
                }
                
                // Always add tooltip for all dates (even without events)
                const dateInfo = document.createElement('div');
                dateInfo.className = 'tooltip-date';
                dateInfo.textContent = date.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                tooltip.insertBefore(dateInfo, tooltip.firstChild);
                
                if (!hasEvents) {
                    const noEventsInfo = document.createElement('div');
                    noEventsInfo.className = 'tooltip-item';
                    noEventsInfo.textContent = 'No events';
                    tooltip.appendChild(noEventsInfo);
                }
                
                dayElement.appendChild(tooltip);
            }
            
            this.calendarDays.appendChild(dayElement);
        }
    }

    // Initialize calendar
    const employeeCalendar = new EmployeeCalendar();

    class LeavesManager {
        constructor() {
            this.leavesGrid = document.getElementById('leavesGrid');
            this.leavesContainer = document.getElementById('leavesContainer');
            this.typeFilter = document.getElementById('leaveTypeFilter');
            this.emptyState = document.querySelector('.leaves-empty-state');
            this.loadingState = document.querySelector('.leaves-loading');
            this.page = 1;
            this.loading = false;
            this.hasMore = true;
            
            this.initializeEventListeners();
            this.fetchLeaves();
        }
        
        initializeEventListeners() {
            this.typeFilter.addEventListener('change', () => {
                this.resetAndFetch();
            });

            // Add scroll event listener
            this.leavesContainer.addEventListener('scroll', () => {
                this.handleScroll();
            });
        }
        
        handleScroll() {
            if (this.loading || !this.hasMore) return;
            
            const container = this.leavesContainer;
            const scrollPosition = container.scrollTop + container.clientHeight;
            const scrollThreshold = container.scrollHeight - 50; // 50px before bottom
            
            if (scrollPosition >= scrollThreshold) {
                this.fetchLeaves();
            }
        }
        
        resetAndFetch() {
            this.page = 1;
            this.hasMore = true;
            this.leavesGrid.innerHTML = '';
            this.fetchLeaves();
        }
        
        async fetchLeaves() {
            if (this.loading || !this.hasMore) return;
            
            try {
                this.loading = true;
                this.showLoading();
                
                const response = await fetch(
                    `fetch_leaves.php?type=${this.typeFilter.value}&page=${this.page}&per_page=10`
                );
                const data = await response.json();
                
                if (data.success) {
                    this.renderLeaves(data.leaves);
                    this.hasMore = data.leaves.length === 10; // Assuming 10 items per page
                    this.page++;
                } else {
                    throw new Error(data.error || 'Failed to fetch leaves');
                }
            } catch (error) {
                console.error('Error fetching leaves:', error);
                this.showError();
            } finally {
                this.loading = false;
                this.hideLoading();
            }
        }
        
        renderLeaves(leaves) {
            if (leaves.length === 0 && this.page === 1) {
                this.showEmptyState();
                return;
            }
            
            leaves.forEach(leave => {
                const card = this.createLeaveCard(leave);
                this.leavesGrid.appendChild(card);
            });
            
            this.hideEmptyState();
        }
        
        createLeaveCard(leave) {
            const card = document.createElement('div');
            card.className = 'leave-card';
            
            const startDate = new Date(leave.start_date);
            const endDate = new Date(leave.end_date);
            
            // Main content
            const content = document.createElement('div');
            content.className = 'leave-card-content';
            content.innerHTML = `
                <div class="leave-card-header">
                    <img src="${leave.profile_picture || 'assets/default-avatar.png'}" 
                         alt="${leave.username}" 
                         class="leave-user-avatar">
                    <div class="leave-user-info">
                        <div class="leave-user-name">${leave.username}</div>
                        <div class="leave-user-designation">${leave.designation}</div>
                    </div>
                </div>
                <div class="leave-type" style="background-color: ${leave.color_code}20; color: ${leave.color_code}">
                    ${leave.leave_type}
                </div>
                <div class="leave-dates">
                    <i class="fas fa-calendar"></i>
                    ${this.formatDateRange(startDate, endDate)}
                </div>
                <div class="leave-reason">${leave.reason}</div>
                <div class="leave-status status-${leave.status.toLowerCase()}">
                    ${leave.status.charAt(0).toUpperCase() + leave.status.slice(1)}
                </div>
            `;
            
            card.appendChild(content);
            
            // Add action buttons for pending leaves
            if (leave.status === 'pending') {
                const actions = document.createElement('div');
                actions.className = 'leave-actions';
                
                // Create approve button
                const approveBtn = document.createElement('button');
                approveBtn.className = 'leave-action-btn approve-btn';
                approveBtn.title = 'Approve Leave';
                approveBtn.innerHTML = '<i class="fas fa-check"></i>';
                approveBtn.addEventListener('click', () => this.showActionModal('approve', leave));
                
                // Create reject button
                const rejectBtn = document.createElement('button');
                rejectBtn.className = 'leave-action-btn reject-btn';
                rejectBtn.title = 'Reject Leave';
                rejectBtn.innerHTML = '<i class="fas fa-times"></i>';
                rejectBtn.addEventListener('click', () => this.showActionModal('reject', leave));
                
                // Append buttons to actions container
                actions.appendChild(approveBtn);
                actions.appendChild(rejectBtn);
                card.appendChild(actions);
            }
            
            return card;
        }
        
        formatDateRange(start, end) {
            const options = { month: 'short', day: 'numeric' };
            if (start.getTime() === end.getTime()) {
                return start.toLocaleDateString(undefined, options);
            }
            return `${start.toLocaleDateString(undefined, options)} - ${end.toLocaleDateString(undefined, options)}`;
        }
        
        showLoading() {
            this.loadingState.style.display = 'block';
            this.leavesGrid.style.display = 'none';
        }
        
        hideLoading() {
            this.loadingState.style.display = 'none';
            this.leavesGrid.style.display = 'grid';
        }
        
        showEmptyState() {
            this.emptyState.style.display = 'block';
            this.leavesGrid.style.display = 'none';
            this.hideLoading();
        }
        
        hideEmptyState() {
            this.emptyState.style.display = 'none';
        }
        
        showError() {
            this.leavesGrid.innerHTML = `
                <div class="leaves-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Failed to load leaves. Please try again later.</p>
                </div>
            `;
            this.hideLoading();
        }

        showActionModal(action, leaveData) {
            const modal = document.getElementById('leaveActionModal');
            const modalTitle = document.getElementById('modalTitle');
            const actionType = document.getElementById('actionType');
            const submitBtn = document.getElementById('submitLeaveAction');
            const reasonInput = document.getElementById('actionReason');
            
            // Set modal content
            modalTitle.textContent = `${action === 'approve' ? 'Approve' : 'Reject'} Leave Request`;
            actionType.textContent = action === 'approve' ? 'approval' : 'rejection';
            
            // Set user details in modal
            document.getElementById('modalUserAvatar').src = leaveData.profile_picture || 'assets/default-avatar.png';
            document.getElementById('modalUsername').textContent = leaveData.username;
            document.getElementById('modalLeaveType').textContent = leaveData.leave_type;
            document.getElementById('modalLeaveDates').textContent = this.formatDateRange(
                new Date(leaveData.start_date),
                new Date(leaveData.end_date)
            );
            
            // Clear previous reason
            reasonInput.value = '';
            
            // Show modal
            modal.style.display = 'flex';
            
            // Handle submission
            const handleSubmit = async () => {
                const reason = reasonInput.value.trim();
                if (!reason) {
                    alert('Please enter a reason');
                    return;
                }
                
                submitBtn.disabled = true;
                
                try {
                    const response = await fetch('handle_leave_approval.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            leave_id: leaveData.id,
                            action: action === 'approve' ? 'accept' : 'reject',
                            reason: reason
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        const actionText = action === 'approve' ? 'Approved' : 'Rejected';
                        const userName = leaveData.username;
                        
                        this.showNotification(
                            `Leave ${actionText}`,
                            `${userName}'s leave request has been ${action === 'approve' ? 'approved' : 'rejected'} successfully.`,
                            'success'
                        );
                        
                        this.resetAndFetch(); // Refresh the leaves list
                        this.closeActionModal();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    this.showNotification(
                        'Action Failed',
                        `Failed to ${action} leave request. Please try again.`,
                        'error'
                    );
                } finally {
                    submitBtn.disabled = false;
                }
            };
            
            // Set up event listeners
            submitBtn.onclick = handleSubmit;
            
            // Close modal handlers
            const closeModal = () => this.closeActionModal();
            document.getElementById('closeLeaveModal').onclick = closeModal;
            document.getElementById('cancelLeaveAction').onclick = closeModal;
            modal.onclick = (e) => {
                if (e.target === modal) closeModal();
            };
        }

        closeActionModal() {
            const modal = document.getElementById('leaveActionModal');
            modal.style.display = 'none';
        }

        showNotification(title, message, type = 'success') {
            // Remove existing notifications
            const existingToasts = document.querySelectorAll('.toast-notification');
            existingToasts.forEach(toast => toast.remove());

            // Create new notification
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            
            // Set icon based on type and action
            let icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        }
    }

    // Initialize Leaves Manager
    const leavesManager = new LeavesManager();

    class ProjectOverview {
        constructor() {
            this.initializeTooltips();
            this.fetchProjectStats();
            
            // Refresh stats every 5 minutes
            setInterval(() => this.fetchProjectStats(), 300000);
        }

        initializeTooltips() {
            // Add tooltip HTML to the document if it doesn't exist
            if (!document.getElementById('overdue-details')) {
                const overdueTooltip = `
                    <div class="project-tooltip" id="overdue-details">
                        <div class="project-tooltip-header">
                            <i class="fas fa-exclamation-circle"></i>
                            <h4>Overdue Projects</h4>
                        </div>
                        <div class="project-tooltip-content">
                            <ul class="project-list">
                                <!-- Will be populated dynamically -->
                            </ul>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', overdueTooltip);
            }
        }

        formatStatus(status) {
            return status.split('_')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }

        updateProjectStats(stats) {
            // Update total projects
            const totalProjectsCard = document.querySelector('[data-tooltip="total-projects-details"] .project-stat-number');
            if (totalProjectsCard) {
                totalProjectsCard.textContent = stats.total;
            }
            
            // Update overdue count
            const overdueCard = document.querySelector('[data-tooltip="overdue-details"] .project-stat-number');
            if (overdueCard) {
                overdueCard.textContent = stats.overdue;
            }
            
            // Update in-progress count
            const inProgressCard = document.querySelector('[data-tooltip="in-progress-details"] .project-stat-number');
            if (inProgressCard) {
                inProgressCard.textContent = stats.in_progress;
            }
            
            // Update completed count
            const completedCard = document.querySelector('[data-tooltip="completed-details"] .project-stat-number');
            if (completedCard) {
                completedCard.textContent = stats.completed;
            }
            
            // Update stages pending count
            const stagesPendingCard = document.querySelector('[data-tooltip="stages-pending-details"] .project-stat-number');
            if (stagesPendingCard) {
                stagesPendingCard.textContent = stats.pending_stages;
            }
            
            // Update substages pending count
            const substagesPendingCard = document.querySelector('[data-tooltip="substages-pending-details"] .project-stat-number');
            if (substagesPendingCard) {
                substagesPendingCard.textContent = stats.pending_substages;
            }
        }

        updateProjectTooltips(data) {
            // Update overdue projects tooltip
            const overdueList = document.querySelector('#overdue-details .project-list');
            if (overdueList) {
                if (data.overdue_projects && data.overdue_projects.length > 0) {
                    overdueList.innerHTML = data.overdue_projects.map(project => {
                        const daysOverdue = project.days_overdue;
                        return `
                            <li class="project-overdue-item">
                                <span class="project-name">${project.title}</span>
                                <div class="project-overdue-info">
                                    <span class="project-status ${project.status.replace('_', '-')}">
                                        ${this.formatStatus(project.status)}
                                    </span>
                                    <span class="overdue-days">
                                        ${daysOverdue} days overdue
                                    </span>
                                </div>
                            </li>
                        `;
                    }).join('');
                } else {
                    overdueList.innerHTML = '<li class="no-projects">No overdue projects</li>';
                }
            }
            
            // Update total projects tooltip
            const projectsList = document.querySelector('#total-projects-details .project-list');
            if (projectsList) {
                if (data.projects && data.projects.length > 0) {
                    projectsList.innerHTML = data.projects.map(project => {
                        const statusClass = project.status.replace('_', '-');
                        return `
                            <li>
                                <span class="project-name">${project.title}</span>
                                <span class="project-status ${statusClass}">
                                    ${this.formatStatus(project.status)}
                                </span>
                            </li>
                        `;
                    }).join('');
                } else {
                    projectsList.innerHTML = '<li class="no-projects">No projects found</li>';
                }
            }

            // Update stages pending tooltip
            const stagesList = document.querySelector('#stages-pending-details .stages-list');
            const stagesCount = document.querySelector('#stages-pending-details .tooltip-count');
            
            if (stagesList && data.pending_stages) {
                if (data.pending_stages.length > 0) {
                    stagesCount.textContent = `${data.pending_stages.length} stages pending`;
                    stagesList.innerHTML = data.pending_stages.map(stage => {
                        const daysText = stage.days_remaining > 0 
                            ? `${stage.days_remaining} days remaining` 
                            : `<span class="overdue">Overdue by ${Math.abs(stage.days_remaining)} days</span>`;
                        
                        return `
                            <li class="stage-item">
                                <div class="stage-header">
                                    <div class="stage-title">
                                        <span class="project-name">${stage.project_title}</span>
                                        <span class="stage-identifier">Stage ${stage.stage_number}</span>
                                    </div>
                                    <span class="project-status ${stage.status.toLowerCase()}">${this.formatStatus(stage.status)}</span>
                                </div>
                                <div class="stage-details">
                                    <div class="stage-dates">
                                        <i class="far fa-calendar-alt"></i>
                                        <span>Due: ${new Date(stage.end_date).toLocaleDateString()}</span>
                                        <span class="days-count ${stage.days_remaining <= 3 ? 'urgent' : ''}">${daysText}</span>
                                    </div>
                                </div>
                            </li>
                        `;
                    }).join('');
                } else {
                    stagesList.innerHTML = '<li class="no-items">No pending stages</li>';
                    stagesCount.textContent = 'No stages pending';
                }
            }

            // Update substages pending tooltip
            const substagesList = document.querySelector('#substages-pending-details .substages-list');
            const substagesCount = document.querySelector('#substages-pending-details .tooltip-count');
            
            if (substagesList && data.pending_substages) {
                if (data.pending_substages.length > 0) {
                    substagesCount.textContent = `${data.pending_substages.length} substages pending`;
                    substagesList.innerHTML = data.pending_substages.map(substage => {
                        const daysText = substage.days_remaining > 0 
                            ? `${substage.days_remaining} days remaining` 
                            : `<span class="overdue">Overdue by ${Math.abs(substage.days_remaining)} days</span>`;
                        
                        return `
                            <li class="substage-item">
                                <div class="substage-header">
                                    <div class="substage-title">
                                        <span class="project-name">${substage.project_title}</span>
                                        <span class="substage-identifier">Stage ${substage.stage_number}.${substage.substage_number}</span>
                                    </div>
                                    <span class="project-status ${substage.status.toLowerCase()}">${this.formatStatus(substage.status)}</span>
                                </div>
                                <div class="substage-details">
                                    <div class="substage-dates">
                                        <i class="far fa-calendar-alt"></i>
                                        <span>Due: ${new Date(substage.end_date).toLocaleDateString()}</span>
                                        <span class="days-count ${substage.days_remaining <= 3 ? 'urgent' : ''}">${daysText}</span>
                                    </div>
                                </div>
                            </li>
                        `;
                    }).join('');
                } else {
                    substagesList.innerHTML = '<li class="no-items">No pending substages</li>';
                    substagesCount.textContent = 'No substages pending';
                }
            }
        }

        async fetchProjectStats() {
            try {
                const response = await fetch('fetch_projects.php');
                const data = await response.json();
                
                if (data.success) {
                    this.updateProjectStats(data.stats);
                    this.updateProjectTooltips(data);
                }
            } catch (error) {
                console.error('Error fetching projects:', error);
            }
        }
    }

    // Initialize when the DOM is loaded
    const projectOverview = new ProjectOverview();

    // Add this to your existing calendar event creation code
    function createProjectEvent(project, color) {
        const eventElement = document.createElement('div');
        eventElement.className = 'project-calendar-event';
        eventElement.style.backgroundColor = color;
        eventElement.textContent = project.title;
        eventElement.setAttribute('data-project-id', project.id);
        
        // Add click event listener
        eventElement.addEventListener('click', () => openProjectDetailsModal(project.id));
        return eventElement;
    }

    // Add these new functions for modal handling
    async function openProjectDetailsModal(projectId) {
        try {
            const response = await fetch(`fetch_project_details.php?project_id=${projectId}`);
            const data = await response.json();
            
            if (data.success) {
                showProjectDetailsModal(data.project);
            }
        } catch (error) {
            console.error('Error fetching project details:', error);
        }
    }

    function showProjectDetailsModal(project) {
        if (!document.getElementById('projectCalendarDetailModal')) {
            const modalHTML = `
                <div id="projectCalendarDetailModal" class="project-calendar-detail-modal">
                    <div class="project-calendar-detail-content">
                        <div class="project-type-indicator"></div>
                        <div class="project-calendar-detail-header">
                            <div class="project-calendar-detail-header-content">
                                <div class="project-type-badge ${project.project_type.toLowerCase()}">
                                    ${project.project_type}
                                </div>
                                <h2 class="project-calendar-detail-title"></h2>
                            </div>
                            <button class="project-calendar-detail-close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="project-calendar-detail-body">
                            <div class="project-description-section">
                                <h3 class="section-title">Project Description</h3>
                                <div class="project-description-content"></div>
                            </div>

                            <div class="project-calendar-detail-info">
                                <div class="project-calendar-detail-grid">
                                    <div class="info-card">
                                        <span class="info-label">Status</span>
                                        <span class="project-calendar-detail-status"></span>
                                    </div>
                                    <div class="info-card">
                                        <span class="info-label">Start Date</span>
                                        <span class="project-start-date"></span>
                                    </div>
                                    <div class="info-card">
                                        <span class="info-label">End Date</span>
                                        <span class="project-end-date"></span>
                                    </div>
                                    <div class="info-card">
                                        <span class="info-label">Assigned To</span>
                                        <span class="project-calendar-detail-assigned"></span>
                                    </div>
                                    <div class="info-card">
                                        <span class="info-label">Assigned By</span>
                                        <span class="project-calendar-detail-assigned-by"></span>
                                    </div>
                                    <div class="info-card update-info">
                                        <span class="info-label">Recently Updated</span>
                                        <div class="update-details">
                                            <span class="project-calendar-detail-updated-by"></span>
                                            <span class="update-time"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="project-calendar-detail-stages">
                                <div class="stages-header">
                                    <h3>Project Timeline</h3>
                                    <div class="stages-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill"></div>
                                        </div>
                                        <span class="progress-text">0% Complete</span>
                                    </div>
                                </div>
                                <div class="project-calendar-detail-stages-list"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        
        const modal = document.getElementById('projectCalendarDetailModal');
        const modalContent = modal.querySelector('.project-calendar-detail-content');
        
        // Remove any existing project type classes
        modalContent.className = 'project-calendar-detail-content';
        
        // Add the current project type class
        modalContent.classList.add(`project-type-${project.project_type.toLowerCase()}`);
        
        // Update project type badge
        const typeBadge = modal.querySelector('.project-type-badge');
        typeBadge.className = `project-type-badge ${project.project_type.toLowerCase()}`;
        typeBadge.textContent = project.project_type;
        
        // Update modal content
        modal.querySelector('.project-calendar-detail-title').textContent = project.title;
        
        // Update description
        const descriptionContent = modal.querySelector('.project-description-content');
        if (project.description) {
            descriptionContent.textContent = project.description;
        } else {
            descriptionContent.innerHTML = '<em class="no-description">No description available</em>';
        }

        // Format dates
        const startDate = new Date(project.start_date).toLocaleDateString('en-US', { 
            day: 'numeric', 
            month: 'short', 
            year: 'numeric' 
        });
        const endDate = new Date(project.end_date).toLocaleDateString('en-US', { 
            day: 'numeric', 
            month: 'short', 
            year: 'numeric' 
        });
        
        modal.querySelector('.project-start-date').textContent = startDate;
        modal.querySelector('.project-end-date').textContent = endDate;
        modal.querySelector('.project-calendar-detail-assigned').textContent = project.assigned_to_name || 'Unassigned';
        modal.querySelector('.project-calendar-detail-assigned-by').textContent = project.created_by_name || 'Unknown';
        
        // Update status with badge
        modal.querySelector('.project-calendar-detail-status').innerHTML = `
            <span class="status-badge ${project.status}">
                ${project.status.charAt(0).toUpperCase() + project.status.slice(1)}
            </span>
        `;
        
        // Calculate and update progress
        if (project.stages && project.stages.length > 0) {
            const totalStages = project.stages.length;
            const completedStages = project.stages.filter(stage => stage.status === 'completed').length;
            const progress = Math.round((completedStages / totalStages) * 100);
            
            modal.querySelector('.progress-fill').style.width = `${progress}%`;
            modal.querySelector('.progress-text').textContent = `${progress}% Complete`;
        }
        
        // Render stages
        const stagesList = modal.querySelector('.project-calendar-detail-stages-list');
        stagesList.innerHTML = '';
        
        if (project.stages && project.stages.length > 0) {
            project.stages.forEach((stage, index) => {
                // Check if stage title is undefined or null
                if (!stage.title || stage.title === 'undefined') {
                    // Set a default title based on stage number
                    stage.title = `Project Stage ${stage.stage_number}`;
                }
                
                // Create stage element with fixed title
                const stageElement = document.createElement('div');
                stageElement.className = 'stage-item';
                
                const isCompleted = stage.status === 'completed';
                const isInProgress = stage.status === 'in_progress';
                
                // Simplified assigned user element
                const assignedUserHTML = stage.stage_assigned_to_name ? `
                    <span class="stage-assigned">
                        <i class="fas fa-user"></i>
                        <span>${stage.stage_assigned_to_name}</span>
                    </span>
                ` : `
                    <span class="stage-assigned not-assigned">
                        <i class="fas fa-user-slash"></i>
                        <span>Not Assigned</span>
                    </span>
                `;

                stageElement.innerHTML = `
                    <div class="stage-timeline">
                        <div class="timeline-dot ${isCompleted ? 'completed' : isInProgress ? 'in-progress' : ''}"></div>
                        ${index < project.stages.length - 1 ? '<div class="timeline-line"></div>' : ''}
                    </div>
                    <div class="stage-content">
                        <div class="stage-header">
                            <div class="stage-header-left">
                                <h4>Stage ${stage.stage_number}: ${stage.title}</h4>
                                <div class="stage-meta">
                                    ${assignedUserHTML}
                                    <span class="stage-due-date">
                                        <i class="fas fa-calendar"></i> 
                                        Due: ${new Date(stage.end_date).toLocaleDateString('en-US', { 
                                            day: 'numeric', 
                                            month: 'short', 
                                            year: 'numeric'
                                        })}
                                    </span>
                                </div>
                            </div>
                            <div class="stage-actions">
                                <button class="chat-icon-btn" title="View Chat">
                                    <i class="fas fa-comment-alt"></i>
                                </button>
                                <button class="activity-log-icon-btn" title="View Activity Log">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button class="stage-substage-toggle-btn" title="Toggle Substages">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <div class="stage-status-dropdown">
                                    <select class="status-select" data-stage-id="${stage.id}">
                                        <option value="not_started" ${stage.status === 'not_started' ? 'selected' : ''}>Not Started</option>
                                        <option value="pending" ${stage.status === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="in_progress" ${stage.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                        <option value="in_review" ${stage.status === 'in_review' ? 'selected' : ''}>In Review</option>
                                        <option value="completed" ${stage.status === 'completed' ? 'selected' : ''}>Completed</option>
                                        <option value="on_hold" ${stage.status === 'on_hold' ? 'selected' : ''}>On Hold</option>
                                        <option value="cancelled" ${stage.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                        <option value="blocked" ${stage.status === 'blocked' ? 'selected' : ''}>Blocked</option>
                                        <option value="freezed" ${stage.status === 'freezed' ? 'selected' : ''}>Freezed</option>
                                        <option value="sent_to_client" ${stage.status === 'sent_to_client' ? 'selected' : ''}>Sent to Client</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="substages-container" style="display: none;">
                            ${stage.substages.map(substage => `
                                <div class="substage-item ${substage.status}">
                                    <div class="substage-content">
                                        <div class="substage-header">
                                            <div class="substage-title-group">
                                                <span class="substage-number">${stage.stage_number}.${substage.substage_number}</span>
                                                <div class="substage-title-wrapper">
                                                    <span class="substage-title">${substage.title}</span>
                                                    ${substage.drawing_number ? 
                                                        `<span class="drawing-number">(Drawing No: ${substage.drawing_number})</span>` 
                                                        : ''}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="substage-meta">
                                            <span class="substage-assigned">
                                                <i class="fas fa-user"></i>
                                                ${substage.substage_assigned_to_name ? 
                                                    `<span>${substage.substage_assigned_to_name}</span>` : 
                                                    `<span class="not-assigned">Not Assigned</span>`
                                                }
                                            </span>
                                            <span class="substage-due-date">
                                                <i class="fas fa-calendar"></i>
                                                Due: ${new Date(substage.end_date).toLocaleDateString('en-US', { 
                                                    day: 'numeric', 
                                                    month: 'short', 
                                                    year: 'numeric'
                                                })}
                                            </span>
                                        </div>
                                        
                                        <!-- Toggle Button for Table -->
                                        <div class="substage-toggle-container">
                                            <button class="substage-toggle-btn" data-substage-id="${substage.id}">
                                                <span class="toggle-text">Show Details</span>
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Expandable Table Section -->
                                        <div class="substage-details-table" id="substage-details-${substage.id}">
                                            <div class="table-responsive">
                                                <table class="details-table">
                                                    <thead>
                                                        <tr>
                                                            <th>S.No</th>
                                                            <th>File Name</th>
                                                            <th>Type</th>
                                                            <th>Status</th>
                                                            <th>
                                                                Actions
                                                                <button class="upload-file-btn" title="Upload New File" data-substage-id="${substage.id}">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${substage.files && substage.files.length > 0 ? 
                                                            substage.files.map((file, index) => `
                                                                <tr>
                                                                    <td>${index + 1}</td>
                                                                    <td>${file.file_name}</td>
                                                                    <td>${file.type}</td>
                                                                    <td><span class="table-status ${file.status.toLowerCase()}">${file.status.replace('_', ' ')}</span></td>
                                                                    <td class="table-actions">
                                                                        
                                                                        <button class="table-action-btn action-fingerprint" title="Secure Download" data-tooltip="Download with unique filename" onclick="fingerprintDownload(${file.id})">
                                                                            <i class="fas fa-download"></i>
                                                                        </button>
                                                                        <button class="table-action-btn action-accept" title="Accept" onclick="updateFileStatus(${file.id}, 'approved')">
                                                                            <i class="fas fa-check"></i>
                                                                        </button>
                                                                        <button class="table-action-btn action-reject" title="Reject" onclick="updateFileStatus(${file.id}, 'rejected')">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            `).join('') 
                                                            : '<tr><td colspan="5" class="no-files-message">No files available for this substage</td></tr>'
                                                        }
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="substage-actions">
                                        <button class="chat-icon-btn" title="View Chat">
                                            <i class="fas fa-comment-alt"></i>
                                        </button>
                                        <button class="activity-log-icon-btn" title="View Activity Log">
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <div class="substage-status-badge ${substage.status}">
                                            ${substage.status.charAt(0).toUpperCase() + substage.status.slice(1).replace('_', ' ')}
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                stagesList.appendChild(stageElement);

                // After rendering the stages and substages, add toggle functionality
                stageElement.querySelectorAll('.substage-toggle-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const substageId = this.dataset.substageId;
                        const detailsTable = document.getElementById(`substage-details-${substageId}`);
                        
                        // Toggle visibility
                        if (detailsTable.classList.contains('active')) {
                            detailsTable.classList.remove('active');
                            this.innerHTML = `<span class="toggle-text">Show Details</span><i class="fas fa-chevron-down"></i>`;
                        } else {
                            detailsTable.classList.add('active');
                            this.innerHTML = `<span class="toggle-text">Hide Details</span><i class="fas fa-chevron-up"></i>`;
                        }
                    });
                });

                // Add toggle functionality for stage substage toggle button
                const stageToggleBtn = stageElement.querySelector('.stage-substage-toggle-btn');
                if (stageToggleBtn) {
                    stageToggleBtn.addEventListener('click', function() {
                        const substagesContainer = stageElement.querySelector('.substages-container');
                        if (substagesContainer) {
                            const isVisible = substagesContainer.style.display !== 'none';
                            substagesContainer.style.display = isVisible ? 'none' : 'block';
                            // Rotate icon
                            const icon = this.querySelector('i');
                            icon.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
                        }
                    });
                }

                // Add event listener for status change
                const statusSelect = stageElement.querySelector('.status-select');
                statusSelect.addEventListener('change', async function() {
                    try {
                        const response = await fetch('update_stage_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                stage_id: this.dataset.stageId,
                                status: this.value
                            })
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            // Update the UI to reflect the new status
                            const timelineDot = stageElement.querySelector('.timeline-dot');
                            timelineDot.className = `timeline-dot ${this.value === 'completed' ? 'completed' : this.value === 'in_progress' ? 'in-progress' : ''}`;
                        }
                    } catch (error) {
                        console.error('Error updating stage status:', error);
                    }
                });

                // After rendering the substage table, add this code inside the stage.substages.map function:
                stageElement.querySelectorAll('.upload-file-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const substageId = this.dataset.substageId;
                        showUploadModal(substageId);
                    });
                });

                // Add chat functionality to stage chat buttons
                stageElement.querySelectorAll('.stage-actions .chat-icon-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation(); // Prevent stage toggle from firing
                        
                        // Initialize StageChat if not already initialized
                        if (!window.stageChat) {
                            window.stageChat = new StageChat();
                        }
                        
                        // Get stage info
                        const stageName = `Stage ${stage.stage_number}: ${stage.title}`;
                        
                        // Open the chat
                        window.stageChat.openChat(project.id, stage.id, stageName, btn);
                    });
                });

                // Add chat functionality to substage chat buttons
                stageElement.querySelectorAll('.substage-actions .chat-icon-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation(); // Prevent propagation
                        
                        // Find the substage elements to get the data
                        const substageItem = btn.closest('.substage-item');
                        const substageId = substageItem.querySelector('.substage-toggle-btn')?.dataset.substageId;
                        const substageTitle = substageItem.querySelector('.substage-title')?.textContent;
                        
                        // Initialize StageChat if not already initialized
                        if (!window.stageChat) {
                            window.stageChat = new StageChat();
                        }
                        
                        // Open the substage chat
                        window.stageChat.openSubstageChat(project.id, stage.id, substageId, substageTitle, btn);
                    });
                });
            });
        }
        
        // Add close button functionality
        const closeBtn = modal.querySelector('.project-calendar-detail-close');
        closeBtn.onclick = () => modal.classList.remove('active');
        
        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
        
        // Show modal
        modal.classList.add('active');

        // Update the "Recently Updated" information
        const updateInfo = modal.querySelector('.project-calendar-detail-updated-by');
        const updateTime = modal.querySelector('.update-time');
        
        if (project.updated_by_name && project.updated_at) {
            const updateDate = new Date(project.updated_at);
            const now = new Date();
            const diffTime = Math.abs(now - updateDate);
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            const diffHours = Math.floor(diffTime / (1000 * 60 * 60));
            const diffMinutes = Math.floor(diffTime / (1000 * 60));

            let timeAgo;
            if (diffDays > 0) {
                timeAgo = `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            } else if (diffHours > 0) {
                timeAgo = `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            } else {
                timeAgo = `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
            }

            updateInfo.textContent = project.updated_by_name;
            updateTime.textContent = timeAgo;
            
            // Add tooltip with exact date and time
            const exactDateTime = updateDate.toLocaleString('en-US', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            modal.querySelector('.update-info').setAttribute('title', `Updated on ${exactDateTime}`);
        } else {
            updateInfo.textContent = 'No updates';
            updateTime.textContent = '';
        }

        // Add this at the beginning of showProjectDetailsModal function after the first modal check
        if (!document.getElementById('fileUploadModal')) {
            const uploadModalHTML = `
                <div id="fileUploadModal" class="file-upload-modal">
                    <div class="file-upload-content">
                        <div class="file-upload-header">
                            <h3>Upload New File</h3>
                            <button class="modal-close-btn" onclick="closeUploadModal()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form id="fileUploadForm" class="file-upload-form">
                            <input type="hidden" id="substageIdInput" name="substage_id">
                            <div class="form-group">
                                <label for="fileName">File Name</label>
                                <input type="text" id="fileName" name="file_name" required 
                                       placeholder="Enter file name">
                            </div>
                            <div class="form-group">
                                <label for="fileInput">Select File</label>
                                <div class="file-input-container">
                                    <input type="file" id="fileInput" name="file" required>
                                    <div class="file-input-placeholder">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Choose a file or drag it here</span>
                                    </div>
                                </div>
                                <span class="selected-file-name"></span>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="cancel-btn" onclick="closeUploadModal()">Cancel</button>
                                <button type="submit" class="upload-btn">
                                    <i class="fas fa-upload"></i> Upload File
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', uploadModalHTML);
        }
    }

    // Add this helper function to check scroll position
    function updateMoreIndicator(container, indicator) {
        const isScrollable = container.scrollHeight > container.clientHeight;
        const isScrolledToBottom = container.scrollHeight - container.scrollTop === container.clientHeight;
        
        if (isScrollable && !isScrolledToBottom) {
            indicator.style.display = 'block';
        } else {
            indicator.style.display = 'none';
        }
    }

    // Updated function to generate action buttons based on status
    function getActionButtons(status) {
        return `
            <button class="table-action-btn action-download" title="Download"><i class="fas fa-download"></i></button>
            <button class="table-action-btn action-accept" title="Accept"><i class="fas fa-check"></i></button>
            <button class="table-action-btn action-reject" title="Reject"><i class="fas fa-times"></i></button>
        `;
    }

    // Example of generating a row with serial number
    function createTableRow(index, fileName, fileType, status) {
        const statusText = status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        
        return `<tr>
            <td>${index}</td>
            <td>${fileName}</td>
            <td>${fileType}</td>
            <td><span class="table-status ${status}">${statusText}</span></td>
            <td class="table-actions">
                ${getActionButtons(status)}
            </td>
        </tr>`;
    }

    // Add these functions after the showProjectDetailsModal function

    async function viewFile(filePath) {
        try {
            // Extract file ID from the file path
            const fileId = filePath.split('/').pop(); // Get the filename
            const actualFileId = fileId.split('_')[0]; // Get the ID part before the first underscore
            
            // Open in new tab with proper error handling
            const viewUrl = `file_handler.php?action=view&file_id=${actualFileId}`;
            const newWindow = window.open(viewUrl, '_blank');
            
            if (newWindow === null) {
                // If popup was blocked, show notification
                showNotification('Error', 'Please allow popups to view files', 'error');
            }
        } catch (error) {
            console.error('Error viewing file:', error);
            showNotification('Error', 'Failed to view file', 'error');
        }
    }

    async function downloadFile(filePath) {
        try {
            // Extract file ID from the file path
            const fileId = filePath.split('/').pop(); // Get the filename
            const actualFileId = fileId.split('_')[0]; // Get the ID part before the first underscore
            
            // Create a temporary anchor element
            const link = document.createElement('a');
            link.href = `file_handler.php?action=download&file_id=${actualFileId}`;
            link.setAttribute('download', ''); // This will force download rather than navigation
            
            // Append to body, click, and remove
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Show success notification
            showNotification('Success', 'File download started', 'success');
        } catch (error) {
            console.error('Error downloading file:', error);
            showNotification('Error', 'Failed to download file', 'error');
        }
    }

    async function updateFileStatus(fileId, status) {
        try {
            const response = await fetch('api/update_file_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    file_id: fileId,
                    status: status
                })
            });

            const data = await response.json();
            console.log('Status update response:', data);

            if (data.success) {
                // Show notification about the file status update
                showNotification(
                    `File ${status === 'approved' ? 'approved' : 'rejected'} successfully`, 
                    'success'
                );
                
                // If the API indicates a substage status change, update UI accordingly
                if (data.substage_updated) {
                    const substageId = data.substage_id;
                    const newStatus = data.new_substage_status;
                    
                    if (newStatus) {
                        // Show notification about substage status change
                        const statusText = newStatus === 'completed' ? 'completed' : 'in progress';
                        showNotification(
                            `Substage status changed to ${statusText}`,
                            'info'
                        );
                        
                        // Find all elements that might need updating (status badges, etc.)
                        document.querySelectorAll(`[data-substage-id="${substageId}"]`).forEach(
                        element => {
                            // Update status badge/indicator
                            const statusBadge = element.querySelector('.substage-status-badge, .status-badge');
                            if (statusBadge) {
                                // Remove all status classes
                                statusBadge.classList.remove('not_started', 'pending', 'in_progress', 'in_review', 'on_hold', 'cancelled', 'blocked', 'completed');
                                // Add new status class
                                statusBadge.classList.add(newStatus);
                                statusBadge.textContent = newStatus === 'completed' ? 'Completed' : 'In Progress';
                            }
                            
                            // Update the substage row styling if applicable
                            const substageItem = element.closest('.substage-item');
                            if (substageItem) {
                                // Remove all status classes
                                substageItem.classList.remove('not_started', 'pending', 'in_progress', 'in_review', 'on_hold', 'cancelled', 'blocked', 'completed');
                                // Add new status class
                                substageItem.classList.add(newStatus);
                            }
                        });
                    }
                }
                
                // Update the file status in the table
                const fileRow = document.querySelector(`tr[data-file-id="${fileId}"]`);
                if (fileRow) {
                    const statusCell = fileRow.querySelector('.table-status');
                    if (statusCell) {
                        // Remove existing status classes
                        statusCell.classList.remove('pending', 'approved', 'rejected', 'sent_for_approval');
                        // Add new status class
                        statusCell.classList.add(status);
                        statusCell.textContent = status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
                    }
                }
                
                // Refresh the project details to show updated status
                if (typeof currentProjectId !== 'undefined' && currentProjectId) {
                    openProjectDetailsModal(currentProjectId);
                }
            } else {
                throw new Error(data.message || 'Failed to update file status');
            }
        } catch (error) {
            console.error('Error updating file status:', error);
            showNotification('Failed to update file status', 'error');
        }
    }

    // Update the file name generation in upload_substage_file.php
    function generateUniqueFileName(originalName, substageId) {
        const timestamp = new Date().getTime();
        const random = Math.random().toString(36).substring(2, 15);
        const extension = originalName.split('.').pop();
        const sanitizedName = originalName
            .split('.')[0]
            .replace(/[^a-zA-Z0-9]/g, '_')
            .substring(0, 30);
        
        return `${substageId}_${timestamp}_${random}_${sanitizedName}.${extension}`;
    }

    // Add these functions after your existing modal-related functions

    function showUploadModal(substageId) {
        const modal = document.getElementById('fileUploadModal');
        const substageIdInput = document.getElementById('substageIdInput');
        const fileInput = document.getElementById('fileInput');
        const fileName = document.getElementById('fileName');
        const selectedFileName = document.querySelector('.selected-file-name');
        
        // Reset form
        substageIdInput.value = substageId;
        fileInput.value = '';
        fileName.value = '';
        selectedFileName.textContent = '';
        
        // Show modal
        modal.classList.add('active');
        
        // File input change handler
        fileInput.onchange = function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                selectedFileName.textContent = file.name;
                if (!fileName.value) {
                    // Set the file name input to the uploaded file's name without extension
                    fileName.value = file.name.replace(/\.[^/.]+$/, "");
                }
            }
        };
        
        // Form submit handler
        const form = document.getElementById('fileUploadForm');
        form.onsubmit = async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                // Log the form data for debugging
                console.log('Form Data:', {
                    substage_id: formData.get('substage_id'),
                    file_name: formData.get('file_name'),
                    file: formData.get('file')
                });

                const response = await fetch('upload_substage_file.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Server Response:', data); // Log the server response
                
                if (data.success) {
                    showNotification('File uploaded successfully', 'success');
                    closeUploadModal();
                    // Refresh the project details to show the new file
                    openProjectDetailsModal(currentProjectId);
                } else {
                    // Log the error details
                    console.error('Upload Error:', {
                        message: data.message,
                        response: data
                    });
                    showNotification('Failed to upload file', 'error');
                }
            } catch (error) {
                // Log the detailed error
                console.error('Upload Error:', {
                    error: error,
                    message: error.message,
                    stack: error.stack
                });
                showNotification('Failed to upload file', 'error');
            }
        };
    }

    function closeUploadModal() {
        const modal = document.getElementById('fileUploadModal');
        modal.classList.remove('active');
    }

    // Add drag and drop functionality
    function initializeDragAndDrop() {
        const dropZone = document.querySelector('.file-input-container');
        const fileInput = document.getElementById('fileInput');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            dropZone.classList.add('drag-over');
        }
        
        function unhighlight(e) {
            dropZone.classList.remove('drag-over');
        }
        
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            fileInput.dispatchEvent(new Event('change'));
        }
    }

    // Initialize drag and drop after modal is added to DOM
    document.addEventListener('DOMContentLoaded', initializeDragAndDrop);

    // Move this function to be at the same level as other functions, not nested inside any other function
    window.showUploadModal = function(substageId) {
        const modal = document.getElementById('fileUploadModal');
        const substageIdInput = document.getElementById('substageIdInput');
        const fileInput = document.getElementById('fileInput');
        const fileName = document.getElementById('fileName');
        const selectedFileName = document.querySelector('.selected-file-name');
        
        // Reset form
        substageIdInput.value = substageId;
        fileInput.value = '';
        fileName.value = '';
        selectedFileName.textContent = '';
        
        // Show modal
        modal.classList.add('active');
        
        // File input change handler
        fileInput.onchange = function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                selectedFileName.textContent = file.name;
                if (!fileName.value) {
                    // Set the file name input to the uploaded file's name without extension
                    fileName.value = file.name.replace(/\.[^/.]+$/, "");
                }
            }
        };
        
        // Form submit handler
        const form = document.getElementById('fileUploadForm');
        form.onsubmit = async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                // Log the form data for debugging
                console.log('Form Data:', {
                    substage_id: formData.get('substage_id'),
                    file_name: formData.get('file_name'),
                    file: formData.get('file')
                });

                const response = await fetch('upload_substage_file.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Server Response:', data); // Log the server response
                
                if (data.success) {
                    showNotification('File uploaded successfully', 'success');
                    closeUploadModal();
                    // Refresh the project details to show the new file
                    openProjectDetailsModal(currentProjectId);
                } else {
                    // Log the error details
                    console.error('Upload Error:', {
                        message: data.message,
                        response: data
                    });
                    showNotification('Failed to upload file', 'error');
                }
            } catch (error) {
                // Log the detailed error
                console.error('Upload Error:', {
                    error: error,
                    message: error.message,
                    stack: error.stack
                });
                showNotification('Failed to upload file', 'error');
            }
        };
    };

    // Also make closeUploadModal globally available
    window.closeUploadModal = function() {
        const modal = document.getElementById('fileUploadModal');
        modal.classList.remove('active');
    };

    // First, define the functions
    function viewFile(fileId) {
        if (!fileId) return;
        window.open(`file_handler.php?action=view&file_id=${fileId}`, '_blank');
    }

    function downloadFile(fileId) {
        if (!fileId) return;
        window.location.href = `file_handler.php?action=download&file_id=${fileId}`;
    }

    function handleStatusUpdate(fileId, status) {
        fetch('api/update_file_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                file_id: fileId,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success notification
                showNotification('Success', `File ${status === 'approved' ? 'approved' : 'rejected'} successfully`);
                
                // If the substage status was updated, show notification
                if (data.substage_updated && data.new_substage_status) {
                    const statusText = data.new_substage_status === 'completed' ? 'completed' : 'in progress';
                    showNotification('Info', `Substage status changed to ${statusText}`);
                }
                
                // Refresh the page after a short delay to show the notifications
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('Error', data.error || 'Failed to update status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error', 'An error occurred while updating the status');
        });
    }

    // Update how you generate the table rows
    function generateFileRow(file, index) {
        return `
            <tr data-file-id="${file.id}">
                <td>${index + 1}</td>
                <td>${file.file_name}</td>
                <td>${file.type || 'N/A'}</td>
                <td>
                    <span class="status-badge ${file.status.toLowerCase()}">
                        ${file.status}
                    </span>
                </td>
                <td class="table-actions">
                    <button class="table-action-btn action-download" 
                        title="Download" 
                        onclick="downloadFile('${file.id}')" type="button">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="table-action-btn action-accept" 
                        title="Accept" 
                        onclick="handleStatusUpdate(${file.id}, 'approved')" type="button">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="table-action-btn action-reject" 
                        title="Reject" 
                        onclick="handleStatusUpdate(${file.id}, 'rejected')" type="button">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    // When populating your table
    function populateFilesTable(files) {
        const tableBody = document.querySelector('#filesTableBody'); // Make sure this ID matches your table's tbody
        if (!files || files.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No files found</td></tr>';
            return;
        }

        tableBody.innerHTML = files.map((file, index) => generateFileRow(file, index)).join('');
    }

    // First, make sure these functions are defined at the top of your script
    document.addEventListener('DOMContentLoaded', function() {
        // Add click event listeners to all action buttons
        document.addEventListener('click', function(e) {
            // Check if the clicked element is a file action button
            if (e.target.closest('.file-action-btn')) {
                const button = e.target.closest('.file-action-btn');
                const fileId = button.dataset.fileId;
                const action = button.dataset.action;

                switch(action) {
                    case 'download':
                        handleDownloadFile(fileId);
                        break;
                    case 'approve':
                        handleStatusUpdate(fileId, 'approved');
                        break;
                    case 'reject':
                        handleStatusUpdate(fileId, 'rejected');
                        break;
                }
            }
        });
    });

    // Handler functions
    function handleDownloadFile(fileId) {
        window.location.href = `file_handler.php?action=download&file_id=${fileId}`;
    }

    // Update how you generate the table rows
    function generateFileRow(file, index) {
        return `
            <tr data-file-id="${file.id}">
                <td>${index + 1}</td>
                <td>${file.file_name}</td>
                <td>${file.type || 'N/A'}</td>
                <td>
                    <span class="status-badge ${file.status.toLowerCase()}">
                        ${file.status}
                    </span>
                </td>
                <td class="table-actions">
                    <button class="table-action-btn action-download" 
                        title="Download" 
                        onclick="downloadFile('${file.id}')" type="button">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="table-action-btn action-accept" 
                        title="Accept" 
                        onclick="handleStatusUpdate(${file.id}, 'approved')" type="button">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="table-action-btn action-reject" 
                        title="Reject" 
                        onclick="handleStatusUpdate(${file.id}, 'rejected')" type="button">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    // Function to populate the table
    function populateFilesTable(files) {
        const tableBody = document.querySelector('#filesTableBody');
        if (!files || files.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No files found</td></tr>';
            return;
        }
        tableBody.innerHTML = files.map((file, index) => generateFileRow(file, index)).join('');
    }

    // Make these functions globally available by attaching them to the window object
    window.viewFile = function(filePath) {
        // View functionality has been removed
        showNotification('Info', 'View functionality has been removed. Please use download instead.', 'info');
    };

    window.downloadFile = function(filePath) {
        const fileId = filePath.split('/').pop().split('_')[0]; // Extract file ID from path
        window.location.href = `file_handler.php?action=download&file_id=${fileId}`;
    };

    window.updateFileStatus = function(fileId, status) {
        fetch('api/update_file_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                file_id: fileId,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success notification
                showNotification('Success', `File ${status === 'approved' ? 'approved' : 'rejected'} successfully`, 'success');
                
                // If the substage status was updated, show notification
                if (data.substage_updated && data.new_substage_status) {
                    const statusText = data.new_substage_status === 'completed' ? 'completed' : 'in progress';
                    showNotification('Substage Updated', `Substage status changed to ${statusText}`, 'info');
                    
                    // Update any UI elements with the substage status
                    const substageId = data.substage_id;
                    document.querySelectorAll(`[data-substage-id="${substageId}"]`).forEach(element => {
                        const statusElement = element.querySelector('.status-badge, .substage-status-badge');
                        if (statusElement) {
                            // Clear existing status classes
                            statusElement.classList.remove('not_started', 'pending', 'in_progress', 'in_review', 'on_hold', 'cancelled', 'blocked', 'completed');
                            // Add new status class
                            statusElement.classList.add(data.new_substage_status);
                            statusElement.textContent = data.new_substage_status === 'completed' ? 'Completed' : 'In Progress';
                        }
                    });
                }
                
                // Refresh the project details
                if (typeof currentProjectId !== 'undefined') {
                    openProjectDetailsModal(currentProjectId);
                }
            } else {
                throw new Error(data.message || 'Failed to update status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error', 'Failed to update file status', 'error');
        });
    };

    // Helper function for notifications
    function showNotification(title, message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }
});