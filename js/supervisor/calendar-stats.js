/**
 * Calendar Stats JavaScript
 * This file contains the functionality for the calendar stats section in the site supervisor dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Calendar Stats functionality
    const calendarContainer = document.getElementById('supervisorCalendar');
    const currentMonthDisplay = document.getElementById('currentMonthCalStats');
    const prevMonthBtn = document.getElementById('prevMonthCalStats');
    const nextMonthBtn = document.getElementById('nextMonthCalStats');
    
    // Exit if elements don't exist (not on the right page)
    if (!calendarContainer || !currentMonthDisplay || !prevMonthBtn || !nextMonthBtn) {
        return;
    }
    
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
    
    // Function to render the calendar
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
        
        // Debug log
        console.log('Rendering calendar for:', `${monthNames[month]} ${year}`);
        
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
            const prevMonthFormatted = prevMonth + 1; // +1 because we need 1-indexed month
            const yearForPrevMonth = prevYear;
            calendarHTML += createSupervisorDayCell(prevMonthDay, true, false, [], prevMonthFormatted, yearForPrevMonth);
        }
        
        // Generate days for current month
        const sampleEvents = generateSampleCalendarEvents(year, month, daysInMonth);
        
        for (let day = 1; day <= daysInMonth; day++) {
            const isToday = isCurrentMonth && today.getDate() === day;
            const dayEvents = sampleEvents[day] || [];
            const currentMonthFormatted = month + 1; // +1 because we need 1-indexed month
            calendarHTML += createSupervisorDayCell(day, false, isToday, dayEvents, currentMonthFormatted, year);
            dayCount++;
        }
        
        // Generate days for next month (if needed)
        const totalCells = Math.ceil((startingDay + daysInMonth) / 7) * 7;
        const nextMonthDays = totalCells - (startingDay + daysInMonth);
        const nextMonth = month === 11 ? 0 : month + 1;
        const nextYear = month === 11 ? year + 1 : year;
        const nextMonthFormatted = nextMonth + 1; // +1 because we need 1-indexed month
        
        for (let day = 1; day <= nextMonthDays; day++) {
            calendarHTML += createSupervisorDayCell(day, true, false, [], nextMonthFormatted, nextYear);
        }
        
        calendarHTML += `</div>`;
        
        // Update the calendar
        calendarContainer.innerHTML = calendarHTML;
        
        // Add click events for day cells
        setupSupervisorCalendarInteractions(sampleEvents, month, year);
    }
    
    // Function to set up calendar interactions
    function setupSupervisorCalendarInteractions(events, month, year) {
        // Add click event for day cells
        document.querySelectorAll('.supervisor-calendar-day').forEach(cell => {
            cell.addEventListener('click', function(e) {
                // Skip if the click was on the add button
                if (e.target.classList.contains('supervisor-add-event-btn') || 
                    e.target.closest('.supervisor-add-event-btn')) {
                    return;
                }
                
                const dayNumber = this.getAttribute('data-day');
                const monthNumber = parseInt(this.getAttribute('data-month'));
                const yearNumber = parseInt(this.getAttribute('data-year'));
                const isOtherMonth = this.classList.contains('other-month');
                
                if (isOtherMonth) {
                    // Navigate to the clicked month
                    currentDate = new Date(yearNumber, monthNumber - 1, 1);
                    renderSupervisorCalendar();
                    return;
                }
                
                // Calendar day clicks are now handled by calendar-events-modal.js
                // This handler is just for month navigation
            });
        });
        
        // Note: We don't need to add click events for the + buttons anymore
        // They are now handled by calendar-events-modal.js
    }
    
    // Function to create a day cell
    function createSupervisorDayCell(day, isOtherMonth, isToday, events, monthValue, yearValue) {
        const hasEvents = events.length > 0;
        let cellClass = 'supervisor-calendar-day';
        
        if (isOtherMonth) cellClass += ' other-month';
        if (isToday) cellClass += ' today';
        if (hasEvents) cellClass += ' has-events';
        
        let cellHTML = `<div class="${cellClass}" data-day="${day}" data-month="${monthValue}" data-year="${yearValue}">
            <div class="supervisor-calendar-date-container">
                <div class="supervisor-calendar-date">${day}</div>
                <button class="supervisor-add-event-btn" data-day="${day}" data-month="${monthValue}" data-year="${yearValue}"></button>
            </div>`;
        
        if (hasEvents) {
            cellHTML += `<div class="supervisor-calendar-events">`;
            
            // Show max 2 events on larger screens
            const displayCount = Math.min(2, events.length);
            for (let i = 0; i < displayCount; i++) {
                const event = events[i];
                cellHTML += `<div class="supervisor-calendar-event event-${event.type}" title="${event.time}: ${event.title}" data-event-id="${event.id}">
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
    
    // Function to generate sample events (this would be replaced with real data)
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
            
            // Generate a unique ID for each event
            const eventId = `event_${month}_${day}_${i}_${new Date().getTime()}`;
            
            events[day].push({
                id: eventId,
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
    
    // Update stats based on calendar data
    function updateCalendarStats() {
        // In a real implementation, this would fetch data from the server
        // For now, we'll just show some static data
        
        // Update event counts
        document.querySelectorAll('.stats-summary-item .badge').forEach((badge, index) => {
            const counts = [12, 8, 15, 6, 3]; // Sample counts for each event type
            badge.textContent = counts[index];
            
            // Update progress bars
            const progressPercentages = [75, 60, 85, 45, 25]; // Sample percentages
            const progressBar = badge.closest('.stats-summary-item').querySelector('.progress-bar');
            progressBar.style.width = progressPercentages[index] + '%';
            progressBar.setAttribute('aria-valuenow', progressPercentages[index]);
        });
    }
    
    // Initial render
    renderSupervisorCalendar();
    updateCalendarStats();
}); 