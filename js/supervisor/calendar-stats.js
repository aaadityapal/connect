/**
 * Calendar Stats JavaScript
 * Handles the calendar display in the stats section
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize calendar functionality
    initSupervisorCalendar();
});

/**
 * Initialize the supervisor calendar functionality
 */
function initSupervisorCalendar() {
    const calendarContainer = document.getElementById('supervisorCalendar');
    const currentMonthDisplay = document.getElementById('currentMonthCalStats');
    const prevMonthBtn = document.getElementById('prevMonthCalStats');
    const nextMonthBtn = document.getElementById('nextMonthCalStats');
    
    // Set initial date to current month/year
    let currentDate = new Date();
    
    // Event listeners for navigation buttons
    prevMonthBtn.addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderSupervisorCalendar();
    });
    
    nextMonthBtn.addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderSupervisorCalendar();
    });
    
    // Initial render
    renderSupervisorCalendar();
    
    /**
     * Function to render the calendar
     */
    function renderSupervisorCalendar() {
        // Get current month and year
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Update the month display
        const monthNames = ["January", "February", "March", "April", "May", "June",
                           "July", "August", "September", "October", "November", "December"];
        currentMonthDisplay.textContent = `${monthNames[month]} ${year}`;
        
        // Get the first day of the month
        const firstDay = new Date(year, month, 1);
        const startingDay = firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.
        
        // Get the number of days in the month
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        // Get the number of days in the previous month
        const prevMonth = month === 0 ? 11 : month - 1;
        const prevYear = month === 0 ? year - 1 : year;
        const daysInPrevMonth = new Date(prevYear, prevMonth + 1, 0).getDate();
        
        // Create calendar HTML
        let calendarHTML = `
            <div class="supervisor-calendar-header">
                <div class="supervisor-calendar-header-cell">Sun</div>
                <div class="supervisor-calendar-header-cell">Mon</div>
                <div class="supervisor-calendar-header-cell">Tue</div>
                <div class="supervisor-calendar-header-cell">Wed</div>
                <div class="supervisor-calendar-header-cell">Thu</div>
                <div class="supervisor-calendar-header-cell">Fri</div>
                <div class="supervisor-calendar-header-cell">Sat</div>
            </div>
            <div class="supervisor-calendar-body">
        `;
        
        // Get today's date for highlighting
        const today = new Date();
        const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;
        
        // Generate days from previous month (if needed)
        let dayCount = 1;
        for (let i = 0; i < startingDay; i++) {
            const prevMonthDay = daysInPrevMonth - startingDay + i + 1;
            const prevMonthFormatted = prevMonth + 1; // Convert to 1-indexed for display
            calendarHTML += createSupervisorDayCell(prevMonthDay, true, false, [], prevMonthFormatted, prevYear);
        }
        
        // Fetch real events from backend for current month view
        const monthFormatted = month + 1; // Convert to 1-indexed for display
        
        // Show loading indicator while fetching events
        calendarContainer.innerHTML = '<div class="calendar-loading"><i class="fas fa-spinner fa-spin"></i> Loading calendar events...</div>';
        
        // Fetch events from the backend
        fetchCalendarEvents(year, monthFormatted)
            .then(eventsData => {
                // Process the events by day
                const eventsByDay = {};
                
                if (eventsData && eventsData.status === 'success' && eventsData.events) {
                    eventsData.events.forEach(event => {
                        // Extract day from event date (format: YYYY-MM-DD)
                        const eventDate = new Date(event.date);
                        const eventDay = eventDate.getDate();
                        
                        // Initialize array for this day if not exists
                        if (!eventsByDay[eventDay]) {
                            eventsByDay[eventDay] = [];
                        }
                        
                        // Add event to the day
                        eventsByDay[eventDay].push({
                            id: event.id,
                            title: event.title,
                            type: event.type,
                            time: "All day" // Default time if not specified
                        });
                    });
                }
                
                // Generate days for current month with real events
        for (let day = 1; day <= daysInMonth; day++) {
            const isToday = isCurrentMonth && today.getDate() === day;
                    const dayEvents = eventsByDay[day] || [];
                    calendarHTML += createSupervisorDayCell(day, false, isToday, dayEvents, monthFormatted, year);
            dayCount++;
        }
        
        // Generate days for next month (if needed)
        const totalCells = Math.ceil((startingDay + daysInMonth) / 7) * 7;
        const nextMonthDays = totalCells - (startingDay + daysInMonth);
        const nextMonth = month === 11 ? 0 : month + 1;
        const nextYear = month === 11 ? year + 1 : year;
                const nextMonthFormatted = nextMonth + 1; // Convert to 1-indexed for display
        
        for (let day = 1; day <= nextMonthDays; day++) {
            calendarHTML += createSupervisorDayCell(day, true, false, [], nextMonthFormatted, nextYear);
        }
        
        calendarHTML += `</div>`;
        
        // Update the calendar
        calendarContainer.innerHTML = calendarHTML;
        
                // Add click events for calendar interactions
                setupSupervisorCalendarInteractions();
                
                // Dispatch event to notify other components that calendar has been refreshed
                document.dispatchEvent(new CustomEvent('calendarRefreshed'));
            })
            .catch(error => {
                console.error('Error fetching calendar events:', error);
                // Fallback to sample events in case of error
                const sampleEvents = generateSampleCalendarEvents(year, month, daysInMonth);
                
                // Generate days for current month with sample events
                for (let day = 1; day <= daysInMonth; day++) {
                    const isToday = isCurrentMonth && today.getDate() === day;
                    const dayEvents = sampleEvents[day] || [];
                    calendarHTML += createSupervisorDayCell(day, false, isToday, dayEvents, monthFormatted, year);
                    dayCount++;
                }
                
                // Generate days for next month (if needed)
                const totalCells = Math.ceil((startingDay + daysInMonth) / 7) * 7;
                const nextMonthDays = totalCells - (startingDay + daysInMonth);
                const nextMonth = month === 11 ? 0 : month + 1;
                const nextYear = month === 11 ? year + 1 : year;
                const nextMonthFormatted = nextMonth + 1; // Convert to 1-indexed for display
                
                for (let day = 1; day <= nextMonthDays; day++) {
                    calendarHTML += createSupervisorDayCell(day, true, false, [], nextMonthFormatted, nextYear);
                }
                
                calendarHTML += `</div>`;
                
                // Update the calendar with sample data
                calendarContainer.innerHTML = calendarHTML;
                
                // Add click events for calendar interactions
                setupSupervisorCalendarInteractions();
            });
    }
    
    /**
     * Create a day cell for the calendar
     */
    function createSupervisorDayCell(day, isOtherMonth, isToday, events, month, year) {
        const hasEvents = events.length > 0;
        let cellClass = 'supervisor-calendar-day';
        
        if (isOtherMonth) cellClass += ' other-month';
        if (isToday) cellClass += ' today';
        if (hasEvents) cellClass += ' has-events';
        
        // Format day and month as two digits with leading zeros
        const dayFormatted = day.toString().padStart(2, '0');
        const monthFormatted = month.toString().padStart(2, '0');
        
        // Create the cell HTML
        let cellHTML = `<div class="${cellClass}" data-day="${dayFormatted}" data-month="${monthFormatted}" data-year="${year}">
            <div class="supervisor-calendar-date-container">
                <div class="supervisor-calendar-date">${day}</div>
                <button class="supervisor-add-event-btn" data-day="${dayFormatted}" data-month="${monthFormatted}" data-year="${year}"></button>
            </div>`;
        
        if (hasEvents) {
            cellHTML += `<div class="supervisor-calendar-events">`;
            
            // Show max 2 events, with the option to view more
            const displayCount = Math.min(2, events.length);
            for (let i = 0; i < displayCount; i++) {
                const event = events[i];
                // Remove any href attributes and use JavaScript to handle click
                cellHTML += `<div class="supervisor-calendar-event event-${event.type}" 
                                  data-event-id="${event.id}" 
                                  title="${event.time}: ${event.title}">
                    ${event.title}
                </div>`;
            }
            
            if (events.length > 2) {
                cellHTML += `<div class="supervisor-event-more">+${events.length - 2} more</div>`;
            }
            
            cellHTML += `</div>`;
        }
        
        cellHTML += `</div>`;
        
        return cellHTML;
    }
    
    /**
     * Set up calendar interactions
     */
    function setupSupervisorCalendarInteractions() {
        // Add event button click handler
        document.querySelectorAll('.supervisor-add-event-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent triggering the day click
                
                const day = this.getAttribute('data-day');
                const month = this.getAttribute('data-month');
                const year = this.getAttribute('data-year');
                
                // Format date for display
                const dateStr = `${year}-${month}-${day}`;
                
                // Show add event form (functionality would be in another file)
                if (typeof window.openCalendarEventModal === 'function') {
                    window.openCalendarEventModal(parseInt(day), parseInt(month), parseInt(year));
                } else {
                    alert(`Add new event on ${dateStr}`);
                }
            });
        });
        
        // Add click handler for individual events in calendar days
        document.querySelectorAll('.supervisor-calendar-event').forEach(eventElement => {
            eventElement.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent triggering the day click
                
                // Get event ID from data attribute
                const eventId = this.getAttribute('data-event-id');
                if (!eventId) return;
                
                // Get date information from parent day cell
                const dayCell = this.closest('.supervisor-calendar-day');
                if (!dayCell) return;
                
                const day = dayCell.getAttribute('data-day');
                const month = dayCell.getAttribute('data-month');
                const year = dayCell.getAttribute('data-year');
                
                // Format date for display
                const dateStr = `${year}-${month}-${day}`;
                const formattedDate = new Date(parseInt(year), parseInt(month) - 1, parseInt(day))
                    .toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                
                // Open enhanced event view modal if available
                if (typeof window.openEnhancedEventView === 'function') {
                    window.openEnhancedEventView(eventId, formattedDate);
                } else {
                    // Fallback behavior
                    alert(`View event ${eventId} on ${dateStr}`);
                }
            });
        });
        
        // Add click handler for "more events" indicators
        document.querySelectorAll('.supervisor-event-more').forEach(moreElement => {
            moreElement.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent triggering the day click
                
                // Get date information from parent day cell
                const dayCell = this.closest('.supervisor-calendar-day');
                if (!dayCell) return;
                
                const day = dayCell.getAttribute('data-day');
                const month = dayCell.getAttribute('data-month');
                const year = dayCell.getAttribute('data-year');
                
                // Format date string for the modal
                const dateStr = `${year}-${month}-${day}`;
                
                // Open the date events modal if available
                if (typeof window.openDateEventsModal === 'function') {
                    window.openDateEventsModal(dateStr);
                } else {
                    // Fallback behavior
                    alert(`Multiple events on ${dateStr}`);
                }
            });
        });
        
        // Add click event to calendar day cells to show all events for that day
        document.querySelectorAll('.supervisor-calendar-day').forEach(cell => {
            cell.addEventListener('click', function(e) {
                // Don't handle if we clicked on a specific element that has its own handler
                if (e.target.classList.contains('supervisor-add-event-btn') || 
                    e.target.closest('.supervisor-add-event-btn') ||
                    e.target.classList.contains('supervisor-calendar-event') || 
                    e.target.closest('.supervisor-calendar-event') ||
                    e.target.classList.contains('supervisor-event-more') ||
                    e.target.closest('.supervisor-event-more')) {
                    return;
                }
                
                const day = this.getAttribute('data-day');
                const month = this.getAttribute('data-month');
                const year = this.getAttribute('data-year');
                
                // Skip other month days
                if (this.classList.contains('other-month')) {
                    return;
                }
                
                // Format date for the date events modal
                const dateStr = `${year}-${month}-${day}`;
                
                    // Open the date events modal
                if (typeof window.openDateEventsModal === 'function') {
                    window.openDateEventsModal(dateStr);
                } else {
                    // Fallback behavior
                    const hasEvents = this.querySelector('.supervisor-calendar-events');
                    if (hasEvents && hasEvents.children.length > 0) {
                        alert(`Events on ${dateStr}`);
                    } else {
                        alert(`No events on ${dateStr}`);
                    }
                }
            });
        });
    }
}

/**
 * Fetch calendar events from the backend
 * @param {number} year - The year to fetch events for
 * @param {number} month - The month to fetch events for (1-12)
 * @returns {Promise} - A promise that resolves to the events data
 */
function fetchCalendarEvents(year, month) {
    // Format month to ensure it's two digits
    const formattedMonth = month.toString().padStart(2, '0');
    
    // Make API request to get events
    return fetch(`backend/get_calendar_events.php?year=${year}&month=${formattedMonth}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Error fetching calendar events:', error);
            // Return empty data structure on error
            return {
                status: 'error',
                message: error.message,
                events: []
            };
        });
}

/**
 * Generate sample calendar events for testing
 * Used as fallback if API call fails
 */
    function generateSampleCalendarEvents(year, month, daysInMonth) {
        const events = {};
        const eventTypes = ['inspection', 'delivery', 'meeting', 'report', 'issue'];
        const eventTitles = {
            'inspection': ['Safety Inspection', 'Quality Check', 'Equipment Inspection'],
            'delivery': ['Material Delivery', 'Equipment Arrival', 'Supplies Delivery'],
            'meeting': ['Team Meeting', 'Client Review', 'Planning Session'],
            'report': ['Progress Report', 'Financial Report', 'Weekly Report'],
            'issue': ['Plumbing Issue', 'Electrical Problem', 'Structural Concern']
        };
        
        // Add 15-20 random events throughout the month
        const numEvents = 15 + Math.floor(Math.random() * 6);
        
        for (let i = 0; i < numEvents; i++) {
            const day = Math.floor(Math.random() * daysInMonth) + 1;
            const eventType = eventTypes[Math.floor(Math.random() * eventTypes.length)];
            const eventTitle = eventTitles[eventType][Math.floor(Math.random() * eventTitles[eventType].length)];
            
            // Random time between 8 AM and 5 PM
            const hour = 8 + Math.floor(Math.random() * 10);
            const minute = Math.floor(Math.random() * 4) * 15; // 0, 15, 30, 45
            const time = `${hour}:${minute === 0 ? '00' : minute} ${hour >= 12 ? 'PM' : 'AM'}`;
            
            if (!events[day]) events[day] = [];
            
            events[day].push({
            id: Math.floor(Math.random() * 10000) + 1, // Simulated event ID
                type: eventType,
                title: eventTitle,
                time: time
            });
        }
        
        // Sort events by time
        for (const day in events) {
            events[day].sort((a, b) => {
                return a.time.localeCompare(b.time);
            });
        }
        
        return events;
    }
    
// Expose functions to global scope for other scripts to use
window.generateSampleCalendarEvents = generateSampleCalendarEvents;
window.fetchCalendarEvents = fetchCalendarEvents; 