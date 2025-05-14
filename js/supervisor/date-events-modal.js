/**
 * Date Events Modal JavaScript
 * Handles displaying calendar events for a specific date in a modal
 */

// Create a self-executing function to avoid polluting global scope
(function() {
    // Modal HTML template
    const modalTemplate = `
        <div id="dateEventsModal" class="date-events-modal">
            <div class="date-events-container">
                <div class="date-events-header">
                    <h3 class="date-events-title">Events for <span id="dateEventsDate"></span></h3>
                    <button class="date-events-close" id="dateEventsClose">×</button>
                </div>
                <div class="date-events-body" id="dateEventsBody">
                    <div class="date-events-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading events...</p>
                    </div>
                </div>
                <div class="date-events-footer">
                    <button class="date-events-add-btn" id="dateEventsAddBtn">
                        <i class="fas fa-plus-circle"></i> Add New Event
                    </button>
                </div>
            </div>
        </div>
    `;

    // Event type icons
    const eventTypeIcons = {
        'inspection': 'fas fa-hard-hat',
        'delivery': 'fas fa-truck',
        'meeting': 'fas fa-users',
        'report': 'fas fa-clipboard',
        'issue': 'fas fa-exclamation-triangle'
    };

    // Initialize the modal when the document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Append modal HTML to the body
        if (!document.getElementById('dateEventsModal')) {
            document.body.insertAdjacentHTML('beforeend', modalTemplate);
        }

        // Load CSS file for the modal if not already loaded
        if (!document.getElementById('date-events-modal-css')) {
            const link = document.createElement('link');
            link.id = 'date-events-modal-css';
            link.rel = 'stylesheet';
            link.href = 'css/supervisor/date-events-modal.css';
            document.head.appendChild(link);
        }

        // Get modal elements
        const modal = document.getElementById('dateEventsModal');
        const closeBtn = document.getElementById('dateEventsClose');
        const addBtn = document.getElementById('dateEventsAddBtn');

        // Add event listeners
        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Add event listener to date cells in the calendar
        setupDateCellListeners();
        
        // Add button click handler to open calendar-events-modal.js modal
        addBtn.addEventListener('click', function() {
            // Get the currently displayed date
            const dateText = document.getElementById('dateEventsDate').textContent;
            const dateParts = document.getElementById('dateEventsDate').getAttribute('data-date').split('-');
            
            if (dateParts.length === 3) {
                const year = parseInt(dateParts[0]);
                const month = parseInt(dateParts[1]);
                const day = parseInt(dateParts[2]);
                
                // Close this modal
                closeModal();
                
                // Check if calendar event modal exists and has an open method
                if (typeof window.openCalendarEventModal === 'function') {
                    console.log('Opening calendar event modal for', day, month, year);
                    // Open the calendar event modal for adding a new event
                    window.openCalendarEventModal(day, month, year);
                } else {
                    console.error('Calendar event modal function not found. Make sure calendar-events-modal.js is loaded before date-events-modal.js');
                    alert('Could not open the add event form. Please refresh the page and try again.');
                }
            } else {
                console.error('Invalid date format', dateParts);
            }
        });

        // Setup keyboard event to close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
    });

    // Function to set up click listeners on calendar date cells
    function setupDateCellListeners() {
        // Wait a bit to ensure the calendar is rendered
        setTimeout(function() {
            // Add click event to calendar day cells
            document.querySelectorAll('.supervisor-calendar-day').forEach(cell => {
                cell.addEventListener('click', function(e) {
                    // Skip if the click was on the add button
                    if (e.target.classList.contains('supervisor-add-event-btn') || 
                        e.target.closest('.supervisor-add-event-btn')) {
                        return;
                    }
                    
                    // Skip if clicking on an event
                    if (e.target.classList.contains('supervisor-calendar-event') || 
                        e.target.closest('.supervisor-calendar-event')) {
                        return;
                    }
                    
                    // Get date information
                    const day = this.getAttribute('data-day');
                    const month = this.getAttribute('data-month');
                    const year = this.getAttribute('data-year');
                    
                    // Skip other month days if they have the other-month class
                    if (this.classList.contains('other-month')) {
                        return;
                    }
                    
                    // Format the date as YYYY-MM-DD
                    const formattedDate = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                    
                    // Open the modal for this date
                    openModal(formattedDate);
                });
            });
            
            // Listen for clicks on the "View Event" buttons that might be inside events
            document.querySelectorAll('.date-event-action-btn.view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const eventId = this.getAttribute('data-event-id');
                    viewEventDetails(eventId);
                });
            });
            
            // Listen for clicks on the "More events" indicator
            document.querySelectorAll('.supervisor-event-more').forEach(moreLink => {
                moreLink.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent calendar day click
                    
                    // Get date from parent cell
                    const dateCell = this.closest('.supervisor-calendar-day');
                    if (dateCell) {
                        const day = dateCell.getAttribute('data-day');
                        const month = dateCell.getAttribute('data-month');
                        const year = dateCell.getAttribute('data-year');
                        
                        // Format the date as YYYY-MM-DD
                        const formattedDate = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                        
                        // Open date events modal for this date
                        openModal(formattedDate);
                    }
                });
            });
        }, 300);
    }

    // Function to open the modal
    function openModal(dateString) {
        const modal = document.getElementById('dateEventsModal');
        const dateDisplay = document.getElementById('dateEventsDate');
        const modalBody = document.getElementById('dateEventsBody');
        
        // Set the date display
        const date = new Date(dateString);
        const formattedDate = date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        dateDisplay.textContent = formattedDate;
        dateDisplay.setAttribute('data-date', dateString);
        
        // Show loading state
        modalBody.innerHTML = `
            <div class="date-events-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading events...</p>
            </div>
        `;
        
        // Show the modal
        modal.classList.add('active');
        
        // Fetch events for this date
        fetchDateEvents(dateString);
    }

    // Function to close the modal
    function closeModal() {
        const modal = document.getElementById('dateEventsModal');
        modal.classList.remove('active');
    }

    // Function to fetch events for a specific date
    function fetchDateEvents(dateString) {
        const url = `backend/get_daily_events.php?date=${dateString}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Render the events
                    renderEvents(data);
                } else {
                    // Show error
                    showError(data.message || 'Failed to load events');
                }
            })
            .catch(error => {
                console.error('Error fetching date events:', error);
                showError('Network error. Please try again.');
            });
    }

    // Function to render events
    function renderEvents(data) {
        const modalBody = document.getElementById('dateEventsBody');
        const events = data.events || [];
        
        if (events.length === 0) {
            // Show empty state
            modalBody.innerHTML = `
                <div class="date-events-empty">
                    <i class="far fa-calendar-times"></i>
                    <p>No events scheduled for this date</p>
                    <div class="add-event-suggestion">
                        <button class="date-events-empty-add-btn" id="dateEventsEmptyAddBtn">
                            <i class="fas fa-calendar-plus"></i> Create New Event
                        </button>
                    </div>
                </div>
            `;
            
            // Add click handler for the empty state add button
            const emptyAddBtn = document.getElementById('dateEventsEmptyAddBtn');
            if (emptyAddBtn) {
                emptyAddBtn.addEventListener('click', function() {
                    document.getElementById('dateEventsAddBtn').click();
                });
            }
            return;
        }
        
        // Create event list
        let eventListHTML = '<ul class="date-events-list">';
        
        events.forEach(event => {
            // Get appropriate icon for event type
            const iconClass = eventTypeIcons[event.type] || 'fas fa-calendar-day';
            
            // Build event HTML
            eventListHTML += `
                <li class="date-event-item date-event-type-${event.type}" data-event-id="${event.id}">
                    <div class="date-event-header">
                        <div class="date-event-type-icon date-event-type-${event.type}">
                            <i class="${iconClass}"></i>
                        </div>
                        <div class="date-event-info">
                            <h4 class="date-event-title">${event.title}</h4>
                            <div class="date-event-creator">
                                <i class="fas fa-user"></i> Created by ${event.created_by.name || 'Unknown'}
                            </div>
                        </div>
                    </div>
                    <div class="date-event-details">
            `;
            
            // Add event details based on counts
            if (event.counts.vendors > 0) {
                eventListHTML += `
                    <div class="date-event-detail">
                        <i class="fas fa-building"></i> ${event.counts.vendors} Vendor${event.counts.vendors > 1 ? 's' : ''}
                    </div>
                `;
            }
            
            if (event.counts.company_labours > 0) {
                eventListHTML += `
                    <div class="date-event-detail">
                        <i class="fas fa-hard-hat"></i> ${event.counts.company_labours} Labour${event.counts.company_labours > 1 ? 's' : ''}
                    </div>
                `;
            }
            
            if (event.counts.beverages > 0) {
                eventListHTML += `
                    <div class="date-event-detail">
                        <i class="fas fa-coffee"></i> ${event.counts.beverages} Beverage${event.counts.beverages > 1 ? 's' : ''}
                    </div>
                `;
            }
            
            if (event.counts.inventory_count > 0) {
                eventListHTML += `
                    <div class="date-event-detail">
                        <i class="fas fa-boxes"></i> ${event.counts.inventory_count} Inventory Item${event.counts.inventory_count > 1 ? 's' : ''}
                    </div>
                `;
            }
            
            if (event.counts.work_progress_count > 0) {
                eventListHTML += `
                    <div class="date-event-detail">
                        <i class="fas fa-tasks"></i> ${event.counts.work_progress_count} Work Item${event.counts.work_progress_count > 1 ? 's' : ''}
                    </div>
                `;
            }
            
            // Add created date
            eventListHTML += `
                    <div class="date-event-detail">
                        <i class="far fa-clock"></i> Created: ${event.created_at}
                    </div>
                </div>
                <div class="date-event-actions">
                    <button class="date-event-action-btn view-btn" data-event-id="${event.id}" title="View Details">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="date-event-action-btn edit-btn" data-event-id="${event.id}" title="Edit Event">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </div>
            </li>
            `;
        });
        
        eventListHTML += '</ul>';
        
        // Update modal body
        modalBody.innerHTML = eventListHTML;
        
        // Add click event to action buttons
        document.querySelectorAll('.date-event-action-btn.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const eventId = this.getAttribute('data-event-id');
                viewEventDetails(eventId);
            });
        });
        
        document.querySelectorAll('.date-event-action-btn.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const eventId = this.getAttribute('data-event-id');
                editEvent(eventId);
            });
        });
    }

    // Function to show error message
    function showError(message) {
        const modalBody = document.getElementById('dateEventsBody');
        
        modalBody.innerHTML = `
            <div class="date-events-empty">
                <i class="fas fa-exclamation-circle"></i>
                <p>Error: ${message}</p>
            </div>
        `;
    }

    // Function to view event details
    function viewEventDetails(eventId) {
        // Close this modal
        closeModal();
        
        // Instead of redirecting to the event detail page, open the event view modal
        if (typeof showEventViewModal === 'function') {
            // Call the event view modal function with the event ID
            showEventViewModal(eventId, 'default', ''); // Type and title will be fetched by the modal
        } else if (typeof showViewEventModal === 'function') {
            // Alternative function name
            showViewEventModal(eventId);
        } else if (window.eventViewModal && typeof window.eventViewModal.show === 'function') {
            // Direct modal object method
            window.eventViewModal.show(eventId);
        } else {
            // Fallback to old behavior only if no modal function is available
            window.location.href = `view_site_event.php?id=${eventId}`;
        }
    }
    
    // Function to edit an event
    function editEvent(eventId) {
        // Close this modal
        closeModal();
        
        // Redirect to the edit event page
        window.location.href = `edit_site_event.php?id=${eventId}`;
    }

    // Add functions to global scope - to be accessed by other scripts
    window.openDateEventsModal = openModal;
    window.setupDateCellListeners = setupDateCellListeners;
})();

// Re-initialize date cell listeners whenever the calendar is refreshed
document.addEventListener('calendarRefreshed', function() {
    if (typeof window.setupDateCellListeners === 'function') {
        window.setupDateCellListeners();
    }
}); 