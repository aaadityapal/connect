/**
 * Calendar Events Handler
 * 
 * This script manages the fetching and display of site events
 * in the calendar on the site supervision dashboard.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize calendar with current month/year
    let currentMonth = new Date().getMonth() + 1; // JavaScript months are 0-based
    let currentYear = new Date().getFullYear();
    
    // Get calendar elements
    const calendarTable = document.querySelector('.calendar-table tbody');
    const currentMonthDisplay = document.querySelector('.current-month');
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');
    
    // Store all events for the current month view
    let currentEvents = [];
    
    // Create view events modal if it doesn't exist
    createViewEventsModal();
    
    // Initial load
    loadCalendarEvents(currentMonth, currentYear);
    
    // Event listeners for navigation
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            loadCalendarEvents(currentMonth, currentYear);
        });
    }
    
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            loadCalendarEvents(currentMonth, currentYear);
        });
    }
    
    /**
     * Load calendar events for a specific month and year
     */
    function loadCalendarEvents(month, year) {
        // Update the current month display
        updateMonthDisplay(month, year);
        
        // Fetch events from the server
        fetch(`includes/fetch_site_events.php?month=${month}&year=${year}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Store events
                    currentEvents = data.events;
                    
                    // Generate calendar grid
                    generateCalendarGrid(month, year);
                    
                    // Populate events
                    populateEvents(currentEvents);
                } else {
                    console.error('Error fetching events:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching calendar events:', error);
            });
    }
    
    /**
     * Create modal for viewing events on a specific day
     */
    function createViewEventsModal() {
        const modalHtml = `
            <div class="modal fade" id="viewEventsModal" tabindex="-1" aria-labelledby="viewEventsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="viewEventsModalLabel">
                                <i class="fas fa-calendar-day"></i> Events for <span id="viewEventsDate"></span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="eventsContainer">
                                <!-- Events will be populated here -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-primary add-event-btn">
                                <i class="fas fa-plus"></i> Add New Event
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Append modal to body if it doesn't exist
        if (!document.getElementById('viewEventsModal')) {
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Add click handler for the "Add New Event" button
            document.body.addEventListener('click', function(e) {
                if (e.target.closest('.add-event-btn')) {
                    // Get the date from the modal title
                    const dateStr = document.getElementById('viewEventsDate').dataset.date;
                    // Hide view events modal
                    const viewEventsModal = bootstrap.Modal.getInstance(document.getElementById('viewEventsModal'));
                    viewEventsModal.hide();
                    // Open add event modal
                    setTimeout(() => {
                        openAddEventModal(dateStr);
                    }, 500);
                }
            });
            
            // Add mobile swipe-to-close functionality
            const viewEventsModal = document.getElementById('viewEventsModal');
            let touchStartY = 0;
            let touchEndY = 0;
            
            // Add swipe gesture handling for mobile devices
            viewEventsModal.addEventListener('touchstart', function(e) {
                touchStartY = e.changedTouches[0].screenY;
            }, { passive: true });
            
            viewEventsModal.addEventListener('touchend', function(e) {
                touchEndY = e.changedTouches[0].screenY;
                // If swiped down more than 100px, close the modal
                if (touchEndY - touchStartY > 100) {
                    const modal = bootstrap.Modal.getInstance(viewEventsModal);
                    if (modal) modal.hide();
                }
            }, { passive: true });
            
            // Prevent the modal from being too tall on mobile
            const adjustModalHeight = () => {
                if (window.innerWidth <= 768) {
                    const modalBody = viewEventsModal.querySelector('.modal-body');
                    if (modalBody) {
                        const windowHeight = window.innerHeight;
                        const headerHeight = viewEventsModal.querySelector('.modal-header').offsetHeight;
                        const footerHeight = viewEventsModal.querySelector('.modal-footer').offsetHeight;
                        const maxHeight = windowHeight - headerHeight - footerHeight - 40; // 40px for padding
                        modalBody.style.maxHeight = `${maxHeight}px`;
                    }
                }
            };
            
            // Call on window resize
            window.addEventListener('resize', adjustModalHeight);
            
            // Initialize modal with custom options
            viewEventsModal.addEventListener('shown.bs.modal', adjustModalHeight);
        }
    }
    
    /**
     * Update the month and year display in the calendar header
     */
    function updateMonthDisplay(month, year) {
        // Convert month number to month name
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        // Update display
        if (currentMonthDisplay) {
            currentMonthDisplay.textContent = `${months[month-1]} ${year}`;
        }
    }
    
    /**
     * Generate the calendar grid for the specified month and year
     */
    function generateCalendarGrid(month, year) {
        if (!calendarTable) return;
        
        // Clear existing calendar
        calendarTable.innerHTML = '';
        
        // Get first day of the month (0 = Sunday, 1 = Monday, etc.)
        const firstDay = new Date(year, month - 1, 1).getDay();
        
        // Get last day of the month
        const lastDate = new Date(year, month, 0).getDate();
        
        // Get last day of previous month
        const prevMonthLastDate = new Date(year, month - 1, 0).getDate();
        
        // Variables for tracking dates
        let date = 1;
        let nextMonthDate = 1;
        
        // Create calendar rows (6 weeks maximum)
        for (let i = 0; i < 6; i++) {
            // Create a table row
            const row = document.createElement('tr');
            
            // Create cells for each day of the week
            for (let j = 0; j < 7; j++) {
                const cell = document.createElement('td');
                cell.className = 'calendar-day';
                
                // Fill in previous month dates
                if (i === 0 && j < firstDay) {
                    const prevDate = prevMonthLastDate - (firstDay - j - 1);
                    cell.textContent = prevDate;
                    cell.classList.add('prev-month');
                    
                    // Add data attribute for the full date
                    const prevMonthNum = month - 1 === 0 ? 12 : month - 1;
                    const prevMonthYear = month - 1 === 0 ? year - 1 : year;
                    cell.dataset.date = `${prevMonthYear}-${prevMonthNum.toString().padStart(2, '0')}-${prevDate.toString().padStart(2, '0')}`;
                }
                // Fill in current month dates
                else if (date <= lastDate) {
                    // Create div for date number
                    const dateDiv = document.createElement('div');
                    dateDiv.className = 'date-number';
                    dateDiv.textContent = date;
                    cell.appendChild(dateDiv);
                    
                    // We're no longer creating a separate button element
                    // The plus sign is handled by CSS ::after pseudo-element
                    
                    // Highlight today's date
                    const today = new Date();
                    if (date === today.getDate() && month === today.getMonth() + 1 && year === today.getFullYear()) {
                        cell.classList.add('today');
                    }
                    
                    // Add data attribute for the full date
                    const fullDate = `${year}-${month.toString().padStart(2, '0')}-${date.toString().padStart(2, '0')}`;
                    cell.dataset.date = fullDate;
                    
                    // Add click event on the cell to view events
                    cell.addEventListener('click', function(e) {
                        // Check if user clicked on the plus sign (::after pseudo-element)
                        const rect = cell.getBoundingClientRect();
                        const clickX = e.clientX - rect.left;
                        const clickY = e.clientY - rect.top;
                        
                        // Detect if we're on mobile
                        const isMobile = window.innerWidth <= 768;
                        
                        // Define tap area - larger on mobile
                        const buttonSize = isMobile ? 30 : 25;
                        
                        // If click is within the plus button area (bottom right corner)
                        // Use a larger tap area for mobile devices
                        if (clickX >= rect.width - buttonSize && clickY >= rect.height - buttonSize) {
                            e.stopPropagation(); // Prevent regular cell click
                            openAddEventModal(fullDate);
                        } 
                        // Prevent triggering when clicking on events
                        else if (e.target.closest('.calendar-event')) {
                            return;
                        } 
                        else {
                            showEventsForDate(fullDate);
                        }
                    });
                    
                    date++;
                }
                // Fill in next month dates
                else {
                    cell.textContent = nextMonthDate;
                    cell.classList.add('next-month');
                    
                    // Add data attribute for the full date
                    const nextMonthNum = month + 1 === 13 ? 1 : month + 1;
                    const nextMonthYear = month + 1 === 13 ? year + 1 : year;
                    cell.dataset.date = `${nextMonthYear}-${nextMonthNum.toString().padStart(2, '0')}-${nextMonthDate.toString().padStart(2, '0')}`;
                    
                    nextMonthDate++;
                }
                
                row.appendChild(cell);
            }
            
            calendarTable.appendChild(row);
            
            // If all dates of current month are added, break the loop
            if (date > lastDate && i >= 4) {
                break;
            }
        }
    }
    
    /**
     * Populate events onto the calendar
     */
    function populateEvents(events) {
        if (!events || !events.length) return;
        
        // Process each event
        events.forEach(event => {
            // Find the corresponding calendar cell
            const cell = document.querySelector(`.calendar-day[data-date="${event.date}"]`);
            
            if (cell) {
                // Create event element
                const eventElement = document.createElement('div');
                eventElement.className = `calendar-event bg-${event.type}`;
                eventElement.dataset.eventId = event.id;
                
                // Add event content
                eventElement.innerHTML = `
                    <small>${event.title}</small>
                `;
                
                // Add creator info as a tooltip if available
                if (event.created_by) {
                    eventElement.title = `Created by: ${event.created_by}`;
                }
                
                // Add click handler to view event details
                eventElement.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent cell click event
                    viewEventDetails(event.id);
                });
                
                // Add event to calendar cell
                cell.appendChild(eventElement);
                
                // Check if we need to add "more events" indicator
                const events = cell.querySelectorAll('.calendar-event');
                if (events.length > 2) {
                    // Hide events beyond the second one
                    for (let i = 2; i < events.length; i++) {
                        events[i].style.display = 'none';
                    }
                    
                    // Add event count badge if not already present
                    if (!cell.querySelector('.event-count')) {
                        const countBadge = document.createElement('div');
                        countBadge.className = 'event-count';
                        countBadge.textContent = events.length;
                        cell.appendChild(countBadge);
                        
                        // Make the badge clickable to view all events
                        countBadge.addEventListener('click', function(e) {
                            e.stopPropagation(); // Prevent cell click
                            showEventsForDate(cell.dataset.date);
                        });
                    } else {
                        // Update existing badge
                        cell.querySelector('.event-count').textContent = events.length;
                    }
                }
            }
        });
    }
    
    /**
     * Show all events for a specific date in the modal
     */
    function showEventsForDate(dateStr) {
        // Format date for display
        const date = new Date(dateStr);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        
        // On mobile, use a more compact date format
        const isMobile = window.innerWidth <= 768;
        const formattedDate = isMobile 
            ? date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
            : date.toLocaleDateString(undefined, options);
        
        // Get modal elements
        const viewEventsModal = document.getElementById('viewEventsModal');
        const viewEventsDate = document.getElementById('viewEventsDate');
        const eventsContainer = document.getElementById('eventsContainer');
        
        // Set date in modal
        viewEventsDate.textContent = formattedDate;
        viewEventsDate.dataset.date = dateStr;
        
        // Filter events for this date
        const eventsForDate = currentEvents.filter(event => event.date === dateStr);
        
        // Populate events container
        if (eventsForDate.length > 0) {
            let eventsHtml = '';
            eventsForDate.forEach(event => {
                // Format date and time
                const eventDate = new Date(event.created_at);
                const formattedTime = eventDate.toLocaleTimeString(undefined, {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                // For mobile, add touch-friendly classes
                const mobileClass = isMobile ? 'mobile-event-card' : '';
                
                // Use only icons on very small screens
                const isVerySmallScreen = window.innerWidth <= 576;
                const buttonSize = isMobile ? (isVerySmallScreen ? 'btn-sm' : 'btn-md') : 'btn-sm';
                const buttonText = isVerySmallScreen ? '' : (isMobile ? '' : 'Edit');
                const detailsText = isVerySmallScreen ? '' : (isMobile ? '' : 'View');
                
                eventsHtml += `
                    <div class="card mb-3 event-card ${mobileClass}">
                        <div class="card-header bg-${event.type} text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">${event.title}</h6>
                            <div class="event-actions">
                                <a href="edit_site_event.php?id=${event.id}" class="btn ${buttonSize} btn-light me-1" title="Edit">
                                    <i class="fas fa-edit"></i> ${buttonText}
                                </a>
                                <a href="view_site_event.php?id=${event.id}" class="btn ${buttonSize} btn-light" title="View Details">
                                    <i class="fas fa-eye"></i> ${detailsText}
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex ${isMobile ? 'flex-column' : 'justify-content-between'}">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i> ${formattedTime}
                                </small>
                                <small class="text-muted ${isMobile ? 'mt-2' : ''}">
                                    <i class="fas fa-user me-1"></i> Created by: ${event.created_by || 'Unknown'}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            eventsContainer.innerHTML = eventsHtml;
        } else {
            eventsContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No events scheduled for this date.
                </div>
            `;
        }
        
        // Show the modal with appropriate mobile handling
        if (isMobile) {
            // For mobile, adjust the modal dialog position for better touch interaction
            const modalDialog = viewEventsModal.querySelector('.modal-dialog');
            if (modalDialog) {
                modalDialog.style.margin = window.innerWidth <= 576 ? '0' : '0.5rem auto';
                modalDialog.style.maxWidth = window.innerWidth <= 576 ? '100%' : '95%';
            }
        }
        
        const modal = new bootstrap.Modal(viewEventsModal);
        modal.show();
        
        // For mobile, scroll to top of modal content
        if (isMobile) {
            setTimeout(() => {
                const modalBody = viewEventsModal.querySelector('.modal-body');
                if (modalBody) modalBody.scrollTop = 0;
            }, 100);
        }
    }
    
    /**
     * Open modal for adding a new event
     */
    function openAddEventModal(dateStr) {
        // Format date for display
        const date = new Date(dateStr);
        const formattedDate = date.toISOString().split('T')[0]; // YYYY-MM-DD format
        
        // Get the modal
        const addEventModal = document.getElementById('addEventModal');
        
        // Set the date in the form
        if (addEventModal) {
            const dateInput = addEventModal.querySelector('#eventDate');
            if (dateInput) {
                dateInput.value = formattedDate;
            }
            
            // Update modal title to include the date
            const modalTitle = addEventModal.querySelector('.modal-title');
            if (modalTitle) {
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                modalTitle.innerHTML = `<i class="fas fa-calendar-plus"></i> Site Event for ${date.toLocaleDateString(undefined, options)}`;
            }
            
            // Open the modal
            const modal = new bootstrap.Modal(addEventModal);
            modal.show();
        }
    }
    
    /**
     * View event details
     */
    function viewEventDetails(eventId) {
        // Redirect to the detailed view
        window.location.href = `view_site_event.php?id=${eventId}`;
    }
}); 