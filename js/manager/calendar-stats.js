/**
 * Calendar Stats JavaScript
 * Handles calendar navigation, event display, and interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize calendar functionality
    initCalendar();
    
    // Set up event listeners
    setupEventListeners();
    
    // Initialize event modal
    initEventModal();
    
    // Initialize the calendar event modal
    if (typeof initCalendarEventModal === 'function') {
        initCalendarEventModal();
    }
    
    // Listen for calendar event creation
    document.addEventListener('calendarEventCreated', handleCalendarEventCreated);
});

/**
 * Calendar state
 */
const calendarState = {
    currentDate: new Date(),
    selectedDate: null,
    events: {},  // Will store events by date
    
    // Sample events data - in a real app, this would come from the server
    sampleEvents: [
        { 
            id: 1, 
            title: 'Site Inspection', 
            date: '2023-05-15', 
            startTime: '10:00', 
            endTime: '12:00',
            location: 'Residential Tower',
            description: 'Routine inspection of construction progress'
        },
        { 
            id: 2, 
            title: 'Team Meeting', 
            date: '2023-05-08', 
            startTime: '14:00', 
            endTime: '15:30',
            location: 'Conference Room',
            description: 'Weekly progress review with supervisors'
        },
        { 
            id: 3, 
            title: 'Material Delivery', 
            date: '2023-05-12', 
            startTime: '09:00', 
            endTime: '11:00',
            location: 'Main Site',
            description: 'Cement and steel delivery from Supplier A'
        },
        { 
            id: 4, 
            title: 'Client Meeting', 
            date: '2023-05-20', 
            startTime: '13:00', 
            endTime: '14:30',
            location: 'Head Office',
            description: 'Project progress presentation to the client'
        },
        { 
            id: 5, 
            title: 'Safety Training', 
            date: '2023-05-25', 
            startTime: '09:00', 
            endTime: '12:00',
            location: 'Training Center',
            description: 'Mandatory safety training for all site workers'
        }
    ]
};

/**
 * Handle calendar event created
 */
function handleCalendarEventCreated(e) {
    const newEvent = e.detail.event;
    
    // Add default fields if not present
    if (!newEvent.startTime) newEvent.startTime = '09:00';
    if (!newEvent.endTime) newEvent.endTime = '10:00';
    if (!newEvent.location) newEvent.location = 'Not specified';
    if (!newEvent.description) newEvent.description = 'No description provided';
    
    // Add event to calendar state
    if (!calendarState.events[newEvent.date]) {
        calendarState.events[newEvent.date] = [];
    }
    calendarState.events[newEvent.date].push(newEvent);
    
    // Update calendar UI
    renderCalendar(calendarState.currentDate);
    
    // Update event stats
    updateEventStats();
}

/**
 * Initialize the calendar
 */
function initCalendar() {
    // Load events for current month
    loadEvents(calendarState.currentDate);
    
    // Render the calendar with current month
    renderCalendar(calendarState.currentDate);
    
    // Update calendar header
    updateCalendarHeader(calendarState.currentDate);
}

/**
 * Load events for the given month
 */
function loadEvents(date) {
    const year = date.getFullYear();
    const month = date.getMonth() + 1; // JavaScript months are 0-indexed
    
    // Clear existing events
    calendarState.events = {};
    
    // Show loading indicator
    const calendarGrid = document.querySelector('.calendar-grid');
    if (calendarGrid) {
        calendarGrid.classList.add('loading');
    }
    
    // Fetch events from API
    fetch(`backend/get_calendar_events.php?year=${year}&month=${month}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Process events
                data.events.forEach(event => {
                    const eventDate = event.date;
                    
                    // Initialize array for this date if it doesn't exist
                    if (!calendarState.events[eventDate]) {
                        calendarState.events[eventDate] = [];
                    }
                    
                    // Add event to the calendar state
                    calendarState.events[eventDate].push({
                        id: event.id,
                        title: event.title,
                        date: eventDate,
                        type: event.type,
                        startTime: '09:00', // Default time if not provided
                        endTime: '10:00',   // Default time if not provided
                        location: 'Not specified',
                        description: `Created by: ${event.created_by.name}`,
                        createdBy: event.created_by
                    });
                });
                
                // Update calendar UI
                renderCalendar(calendarState.currentDate);
                
                // Update event stats
                updateEventStats();
            } else {
                // Show error notification
                showNotification('Failed to load events: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error fetching events:', error);
            showNotification('Error loading events. Please try again.', 'error');
        })
        .finally(() => {
            // Remove loading indicator
            if (calendarGrid) {
                calendarGrid.classList.remove('loading');
            }
        });
}

/**
 * Update the event statistics in the Month Overview section
 */
function updateEventStats() {
    // Count total events
    let totalEvents = 0;
    for (const date in calendarState.events) {
        totalEvents += calendarState.events[date].length;
    }
    
    // Update the stats
    const totalEventsElement = document.querySelector('.stats-item:nth-child(1) .stats-value');
    if (totalEventsElement) {
        totalEventsElement.textContent = totalEvents;
    }
    
    // In a real app, you would fetch these values from the server
    // For now, we'll just set some sample values
    const completedTasksElement = document.querySelector('.stats-item:nth-child(2) .stats-value');
    const pendingTasksElement = document.querySelector('.stats-item:nth-child(3) .stats-value');
    const teamMeetingsElement = document.querySelector('.stats-item:nth-child(4) .stats-value');
    
    if (completedTasksElement) completedTasksElement.textContent = '8';
    if (pendingTasksElement) pendingTasksElement.textContent = '5';
    if (teamMeetingsElement) teamMeetingsElement.textContent = '3';
}

/**
 * Set up event listeners for calendar controls
 */
function setupEventListeners() {
    // Previous month button
    const prevMonthBtn = document.getElementById('prevMonth');
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', navigateToPreviousMonth);
    }
    
    // Next month button
    const nextMonthBtn = document.getElementById('nextMonth');
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', navigateToNextMonth);
    }
    
    // Current month (today) button
    const currentMonthBtn = document.getElementById('currentMonth');
    if (currentMonthBtn) {
        currentMonthBtn.addEventListener('click', navigateToCurrentMonth);
    }
    
    // Refresh calendar button
    const refreshCalendarBtn = document.getElementById('refreshCalendarStats');
    if (refreshCalendarBtn) {
        refreshCalendarBtn.addEventListener('click', refreshCalendar);
    }
    
    // Add click event for calendar days (event delegation)
    const calendarGrid = document.querySelector('.calendar-grid');
    if (calendarGrid) {
        calendarGrid.addEventListener('click', handleCalendarDayClick);
    }
}

/**
 * Navigate to the previous month
 */
function navigateToPreviousMonth() {
    const newDate = new Date(calendarState.currentDate);
    newDate.setMonth(newDate.getMonth() - 1);
    calendarState.currentDate = newDate;
    
    // Load events for the new month
    loadEvents(calendarState.currentDate);
    
    updateCalendarHeader(calendarState.currentDate);
    animateCalendarChange('slide-right');
}

/**
 * Navigate to the next month
 */
function navigateToNextMonth() {
    const newDate = new Date(calendarState.currentDate);
    newDate.setMonth(newDate.getMonth() + 1);
    calendarState.currentDate = newDate;
    
    // Load events for the new month
    loadEvents(calendarState.currentDate);
    
    updateCalendarHeader(calendarState.currentDate);
    animateCalendarChange('slide-left');
}

/**
 * Navigate to the current month (today)
 */
function navigateToCurrentMonth() {
    calendarState.currentDate = new Date();
    
    // Load events for current month
    loadEvents(calendarState.currentDate);
    
    updateCalendarHeader(calendarState.currentDate);
    animateCalendarChange('fade');
}

/**
 * Refresh the calendar data and view
 */
function refreshCalendar() {
    // In a real app, this would fetch fresh data from the server
    // For now, just re-render the calendar
    renderCalendar(calendarState.currentDate);
    
    // Show a refresh animation on the button
    const refreshBtn = document.getElementById('refreshCalendarStats');
    if (refreshBtn) {
        const icon = refreshBtn.querySelector('i');
        if (icon) {
            icon.classList.add('fa-spin');
            setTimeout(() => {
                icon.classList.remove('fa-spin');
            }, 1000);
        }
    }
    
    // Show a notification
    showNotification('Calendar refreshed successfully', 'success');
}

/**
 * Add animation effect when changing calendar month
 */
function animateCalendarChange(effect) {
    const calendarGrid = document.querySelector('.calendar-grid');
    if (!calendarGrid) return;
    
    // Add animation class
    calendarGrid.classList.add(effect);
    
    // Remove the class after animation completes
    setTimeout(() => {
        calendarGrid.classList.remove(effect);
    }, 500);
}

/**
 * Update the calendar header with the current month and year
 */
function updateCalendarHeader(date) {
    const monthYearHeader = document.querySelector('.calendar-header h3');
    if (monthYearHeader) {
        monthYearHeader.textContent = formatMonthYear(date);
    }
}

/**
 * Format date as Month Year (e.g., "May 2023")
 */
function formatMonthYear(date) {
    const options = { month: 'long', year: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

/**
 * Format date as YYYY-MM-DD
 */
function formatDateToYYYYMMDD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Handle click on a calendar day
 * @param {Event} e - The click event
 */
function handleCalendarDayClick(e) {
    // Check if the click was on a calendar day or its child elements
    const dayElement = e.target.closest('.calendar-day');
    
    // Ignore clicks on empty days
    if (!dayElement || dayElement.classList.contains('empty')) {
        return;
    }
    
    // Check if the click was on the plus sign (after pseudo-element)
    const rect = dayElement.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    // Check if the click is in the top-right corner where the plus sign appears
    const isPlusSignClick = x >= rect.width - 25 && y <= 25;
    
    // Get the date from the clicked day
    const day = dayElement.querySelector('.day-number').textContent;
    const month = calendarState.currentDate.getMonth() + 1; // Month is 0-indexed
    const year = calendarState.currentDate.getFullYear();
    
    // Format the date as YYYY-MM-DD
    const dateStr = formatDateToYYYYMMDD(new Date(year, month - 1, day));
    
    if (isPlusSignClick) {
        // If the plus sign was clicked, show the add event modal
        if (typeof showCalendarEventModal === 'function') {
            // Use the calendar-event-modal.js function if available
            showCalendarEventModal(dateStr);
        } else {
            // Fallback to the old function
            showAddEventModal(dateStr);
        }
    } else {
        // If the day was clicked (not the plus sign), show events for that day
        const events = calendarState.events[dateStr] || [];
        if (events.length > 0) {
            showEventsModal(dateStr, events);
        } else {
            // If no events, show the add event modal
            if (typeof showCalendarEventModal === 'function') {
                // Use the calendar-event-modal.js function if available
                showCalendarEventModal(dateStr);
            } else {
                // Fallback to the old function
                showAddEventModal(dateStr);
            }
        }
    }
}

/**
 * Show modal for adding a new event
 * @deprecated Use showCalendarEventModal from calendar-event-modal.js instead
 */
function showAddEventModal(date) {
    // Format date for display
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const formattedDate = date.toLocaleDateString('en-US', options);
    
    // Create modal if it doesn't exist
    if (!document.getElementById('addEventModal')) {
        const modalHTML = `
            <div id="addEventModal" class="event-modal">
                <div class="event-modal-content">
                    <div class="event-modal-header">
                        <h5 class="event-modal-title">Add Event for <span id="addEventModalDate"></span></h5>
                        <button type="button" class="event-modal-close" id="closeAddEventModal">&times;</button>
                    </div>
                    <div class="event-modal-body">
                        <form id="addEventForm">
                            <div class="form-group mb-3">
                                <label for="eventTitle">Event Title</label>
                                <input type="text" class="form-control" id="eventTitle" placeholder="Enter event title" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label for="eventStartTime">Start Time</label>
                                    <input type="time" class="form-control" id="eventStartTime" required>
                                </div>
                                <div class="col-6">
                                    <label for="eventEndTime">End Time</label>
                                    <input type="time" class="form-control" id="eventEndTime" required>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="eventLocation">Location</label>
                                <input type="text" class="form-control" id="eventLocation" placeholder="Enter location">
                            </div>
                            <div class="form-group mb-3">
                                <label for="eventDescription">Description</label>
                                <textarea class="form-control" id="eventDescription" rows="3" placeholder="Event description"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="event-modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancelAddEventBtn">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveEventBtn">Save Event</button>
                    </div>
                </div>
            </div>
        `;
        
        // Append modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add event listeners for modal
        document.getElementById('closeAddEventModal').addEventListener('click', closeAddEventModal);
        document.getElementById('cancelAddEventBtn').addEventListener('click', closeAddEventModal);
        document.getElementById('saveEventBtn').addEventListener('click', saveNewEvent);
        
        // Close modal when clicking outside
        document.getElementById('addEventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddEventModal();
            }
        });
    }
    
    // Set the date in the modal
    document.getElementById('addEventModalDate').textContent = formattedDate;
    
    // Set default times (current time rounded to nearest half hour for start, +1 hour for end)
    const now = new Date();
    const roundedMinutes = Math.ceil(now.getMinutes() / 30) * 30;
    now.setMinutes(roundedMinutes, 0, 0);
    
    const startTime = now.toTimeString().substring(0, 5);
    
    const endTime = new Date(now);
    endTime.setHours(endTime.getHours() + 1);
    const endTimeStr = endTime.toTimeString().substring(0, 5);
    
    document.getElementById('eventStartTime').value = startTime;
    document.getElementById('eventEndTime').value = endTimeStr;
    
    // Show modal
    document.getElementById('addEventModal').classList.add('show');
}

/**
 * Close the add event modal
 * @deprecated Use functions from calendar-event-modal.js instead
 */
function closeAddEventModal() {
    const modal = document.getElementById('addEventModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Save a new event
 * @deprecated Use functions from calendar-event-modal.js instead
 */
function saveNewEvent() {
    // Get form values
    const title = document.getElementById('eventTitle').value.trim();
    const startTime = document.getElementById('eventStartTime').value;
    const endTime = document.getElementById('eventEndTime').value;
    const location = document.getElementById('eventLocation').value.trim();
    const description = document.getElementById('eventDescription').value.trim();
    
    // Validate form
    if (!title || !startTime || !endTime) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    // Format date
    const dateStr = formatDateToYYYYMMDD(calendarState.selectedDate);
    
    // Create new event object
    const newEvent = {
        id: Date.now(), // Simple ID generation
        title: title,
        date: dateStr,
        startTime: startTime,
        endTime: endTime,
        location: location || 'Not specified',
        description: description || 'No description provided'
    };
    
    // Add event to calendar state
    if (!calendarState.events[dateStr]) {
        calendarState.events[dateStr] = [];
    }
    calendarState.events[dateStr].push(newEvent);
    
    // Update calendar UI
    renderCalendar(calendarState.currentDate);
    
    // Update event stats
    updateEventStats();
    
    // Close modal
    closeAddEventModal();
    
    // Show success notification
    showNotification('Event added successfully', 'success');
    
    // Show the events modal with the updated events list
    showEventsModal(calendarState.selectedDate, calendarState.events[dateStr]);
}

/**
 * Initialize the event modal
 */
function initEventModal() {
    // Create modal if it doesn't exist
    if (!document.getElementById('eventModal')) {
        const modalHTML = `
            <div id="eventModal" class="event-modal">
                <div class="event-modal-content">
                    <div class="event-modal-header">
                        <h5 class="event-modal-title">Events for <span id="eventModalDate"></span></h5>
                        <button type="button" class="event-modal-close" id="closeEventModal">&times;</button>
                    </div>
                    <div class="event-modal-body">
                        <div id="eventsList"></div>
                        <div id="noEvents" style="display: none;">
                            <p class="text-muted text-center my-4">No events scheduled for this day.</p>
                        </div>
                    </div>
                    <div class="event-modal-footer">
                        <button type="button" class="btn btn-secondary" id="closeEventModalBtn">Close</button>
                        <button type="button" class="btn btn-primary" id="addEventBtn">Add Event</button>
                    </div>
                </div>
            </div>
        `;
        
        // Append modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add event listeners for modal
        document.getElementById('closeEventModal').addEventListener('click', closeEventsModal);
        document.getElementById('closeEventModalBtn').addEventListener('click', closeEventsModal);
        document.getElementById('addEventBtn').addEventListener('click', handleAddEvent);
        
        // Close modal when clicking outside
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEventsModal();
            }
        });
    }
}

/**
 * Show the events modal with events for the selected date
 */
function showEventsModal(date, events) {
    const modal = document.getElementById('eventModal');
    const modalDate = document.getElementById('eventModalDate');
    const eventsList = document.getElementById('eventsList');
    const noEvents = document.getElementById('noEvents');
    
    // Convert string date to Date object if it's not already
    let dateObj;
    if (typeof date === 'string') {
        dateObj = new Date(date);
    } else {
        dateObj = date;
    }
    
    // Format date for display
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    modalDate.textContent = dateObj.toLocaleDateString('en-US', options);
    
    // Clear previous events
    eventsList.innerHTML = '';
    
    // Show events or no events message
    if (events.length > 0) {
        eventsList.innerHTML = events.map(event => `
            <div class="event-item" data-event-id="${event.id}">
                <div class="event-date">
                    <span class="event-day">${dateObj.getDate()}</span>
                    <span class="event-month">${dateObj.toLocaleDateString('en-US', { month: 'short' })}</span>
                </div>
                <div class="event-details">
                    <h5>${event.title}</h5>
                    <p><i class="fas fa-clock"></i> ${event.startTime} - ${event.endTime}</p>
                    <p><i class="fas fa-map-marker-alt"></i> ${event.location}</p>
                    <p class="mt-2">${event.description}</p>
                </div>
            </div>
        `).join('');
        
        eventsList.style.display = 'block';
        noEvents.style.display = 'none';
    } else {
        eventsList.style.display = 'none';
        noEvents.style.display = 'block';
    }
    
    // Show modal
    modal.classList.add('show');
}

/**
 * Close the events modal
 */
function closeEventsModal() {
    const modal = document.getElementById('eventModal');
    modal.classList.remove('show');
}

/**
 * Handle add event button click
 */
function handleAddEvent() {
    // Close the events modal
    closeEventsModal();
    
    // Use the external modal if available
    if (typeof showCalendarEventModal === 'function') {
        showCalendarEventModal(calendarState.selectedDate);
    } else {
        // Fallback to the old modal
        showAddEventModal(calendarState.selectedDate);
    }
}

/**
 * Render the calendar for the given month
 */
function renderCalendar(date) {
    const calendarGrid = document.querySelector('.calendar-grid');
    if (!calendarGrid) return;
    
    // Clear existing calendar days (except weekday headers)
    const weekdayHeaders = calendarGrid.querySelectorAll('.calendar-weekday');
    calendarGrid.innerHTML = '';
    
    // Re-add weekday headers
    weekdayHeaders.forEach(header => {
        calendarGrid.appendChild(header.cloneNode(true));
    });
    
    // Get the first day of the month
    const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
    const firstDayIndex = firstDay.getDay(); // 0 for Sunday, 1 for Monday, etc.
    
    // Get the last day of the month
    const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    const daysInMonth = lastDay.getDate();
    
    // Get today's date for highlighting
    const today = new Date();
    const isCurrentMonth = today.getMonth() === date.getMonth() && today.getFullYear() === date.getFullYear();
    
    // Add empty cells for days before the 1st
    for (let i = 0; i < firstDayIndex; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarGrid.appendChild(emptyDay);
    }
    
    // Add days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        // Create date string to check for events
        const currentDate = new Date(date.getFullYear(), date.getMonth(), day);
        const dateStr = formatDateToYYYYMMDD(currentDate);
        
        // Check if this day has events
        const hasEvents = calendarState.events[dateStr] && calendarState.events[dateStr].length > 0;
        const eventCount = hasEvents ? calendarState.events[dateStr].length : 0;
        
        // Check if this is today
        const isToday = isCurrentMonth && today.getDate() === day;
        
        // Create day element
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        if (isToday) dayElement.classList.add('today');
        if (hasEvents) dayElement.classList.add('has-events');
        
        // Add day number
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = day;
        dayElement.appendChild(dayNumber);
        
        // Add event indicator if there are events
        if (hasEvents) {
            const eventIndicator = document.createElement('div');
            eventIndicator.className = 'event-indicator';
            
            const eventDot = document.createElement('span');
            eventDot.className = 'event-dot';
            eventIndicator.appendChild(eventDot);
            
            if (eventCount > 1) {
                const eventCount = document.createElement('span');
                eventCount.className = 'event-count';
                eventCount.textContent = '+' + calendarState.events[dateStr].length;
                eventIndicator.appendChild(eventCount);
            }
            
            dayElement.appendChild(eventIndicator);
        }
        
        // Add day element to the calendar
        calendarGrid.appendChild(dayElement);
    }
    
    // Add empty cells for days after the last day to complete the grid
    const totalCells = firstDayIndex + daysInMonth;
    const cellsToAdd = 42 - totalCells; // 42 = 6 rows × 7 days
    
    for (let i = 0; i < cellsToAdd; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarGrid.appendChild(emptyDay);
    }
}

/**
 * Show a notification message
 */
function showNotification(message, type = 'info') {
    // Check if notification container exists
    let container = document.querySelector('.notification-container');
    
    // Create container if it doesn't exist
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Set icon based on type
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';
    
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        <div class="notification-message">${message}</div>
    `;
    
    // Add to container
    container.appendChild(notification);
    
    // Show notification with animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Remove after delay
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
} 