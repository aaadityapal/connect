/**
 * Event View Modal JavaScript
 * Handles displaying event details in a modal
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize event modal functionality
    initEventViewModal();
});

/**
 * Initialize the event view modal functionality
 */
function initEventViewModal() {
    // Create the modal element if it doesn't exist
    createEventViewModal();
    
    // Set up event listeners for viewing events
    setupEventViewListeners();
}

/**
 * Create the event view modal HTML structure
 */
function createEventViewModal() {
    // Check if modal already exists
    if (document.getElementById('eventViewModal')) {
        return;
    }
    
    // Create modal container
    const modal = document.createElement('div');
    modal.id = 'eventViewModal';
    modal.className = 'event-view-modal';
    
    // Create modal HTML content
    modal.innerHTML = `
        <div class="event-view-modal-content">
            <div class="event-view-header">
                <h2 id="eventViewTitle">Event Details</h2>
                <span class="event-view-close">&times;</span>
            </div>
            <div class="event-view-body">
                <div class="event-view-tabs">
                    <div class="event-view-tab active" data-tab="overview">Overview</div>
                    <div class="event-view-tab" data-tab="vendors">Vendors</div>
                    <div class="event-view-tab" data-tab="labor">Labor</div>
                    <div class="event-view-tab" data-tab="materials">Materials</div>
                    <div class="event-view-tab" data-tab="work-progress">Work Progress</div>
                    <div class="event-view-tab" data-tab="inventory">Inventory</div>
                    <div class="event-view-tab" data-tab="expenses">Expenses</div>
                </div>
                
                <!-- Tab Content -->
                <div id="overviewTab" class="event-view-tab-content active">
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Event Information</h3>
                        <div id="eventInfo">Loading event information...</div>
                    </div>
                </div>
                
                <div id="vendorsTab" class="event-view-tab-content">
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Vendor Details</h3>
                        <div id="vendorsList">Loading vendor information...</div>
                    </div>
                </div>
                
                <div id="laborTab" class="event-view-tab-content">
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Vendor Labor</h3>
                        <div id="vendorLaborList">Loading vendor labor information...</div>
                    </div>
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Company Labor</h3>
                        <div id="companyLaborList">Loading company labor information...</div>
                    </div>
                </div>
                
                <div id="materialsTab" class="event-view-tab-content">
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Material Details</h3>
                        <div id="materialsList">Loading material information...</div>
                    </div>
                </div>
                
                <div id="workProgressTab" class="event-view-tab-content">
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Work Progress Details</h3>
                        <div id="workProgressList">Loading work progress information...</div>
                    </div>
                </div>
                
                <div id="inventoryTab" class="event-view-tab-content">
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Inventory Items</h3>
                        <div id="inventoryList">Loading inventory information...</div>
                    </div>
                </div>
                
                <div id="expensesTab" class="event-view-tab-content">
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Expenses Summary</h3>
                        <div id="expensesSummary">Loading expenses information...</div>
                    </div>
                    <div class="event-view-section">
                        <h3 class="event-view-section-title">Beverage Expenses</h3>
                        <div id="beveragesList">Loading beverage information...</div>
                    </div>
                </div>
            </div>
            <div class="event-view-footer">
                <button type="button" class="btn btn-secondary event-view-close-btn">Close</button>
                <button type="button" class="btn btn-primary" id="editEventBtn">Edit Event</button>
            </div>
        </div>
    `;
    
    // Add modal to the document body
    document.body.appendChild(modal);
    
    // Set up event listeners for modal
    setupModalEventListeners();
}

/**
 * Set up event listeners for the modal
 */
function setupModalEventListeners() {
    // Close button functionality
    const modal = document.getElementById('eventViewModal');
    const closeButtons = modal.querySelectorAll('.event-view-close, .event-view-close-btn');
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            closeEventViewModal();
        });
    });
    
    // Tab switching functionality
    const tabs = modal.querySelectorAll('.event-view-tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            switchTab(tabId);
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeEventViewModal();
        }
    });
    
    // Edit event button
    document.getElementById('editEventBtn').addEventListener('click', function() {
        const eventId = this.getAttribute('data-event-id');
        if (eventId) {
            closeEventViewModal();
            // This would normally open an edit form
            alert('Edit functionality would open here for event ID: ' + eventId);
        }
    });
}

/**
 * Switch between tabs in the modal
 */
function switchTab(tabId) {
    // Remove active class from all tabs and content
    const tabs = document.querySelectorAll('.event-view-tab');
    const tabContents = document.querySelectorAll('.event-view-tab-content');
    
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Add active class to selected tab and content
    document.querySelector(`.event-view-tab[data-tab="${tabId}"]`).classList.add('active');
    document.getElementById(`${tabId}Tab`).classList.add('active');
}

/**
 * Set up event listeners for viewing events on calendar
 */
function setupEventViewListeners() {
    // Add event listeners to calendar day cells and events
    document.querySelectorAll('.supervisor-calendar-day').forEach(day => {
        day.addEventListener('click', function(e) {
            // Don't trigger if clicking on the add event button
            if (e.target.closest('.supervisor-add-event-btn') || e.target.classList.contains('supervisor-add-event-btn')) {
                return;
            }
            
            const day = this.getAttribute('data-day');
            const month = this.getAttribute('data-month');
            const year = this.getAttribute('data-year');
            
            // Don't open for other month days
            if (this.classList.contains('other-month')) {
                return;
            }
            
            // Format date for API call
            const formattedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
            
            // Load and show events for this date
            loadEventsForDate(formattedDate);
        });
    });
    
    // Add event listeners for individual calendar events
    document.querySelectorAll('.supervisor-calendar-event').forEach(event => {
        event.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent triggering the day click
            
            const eventId = this.getAttribute('data-event-id');
            if (eventId) {
                loadEventDetails(eventId);
            }
        });
    });
}

/**
 * Load events for a specific date and display in the modal
 */
function loadEventsForDate(date) {
    // Show loading state
    showEventViewModal();
    document.getElementById('eventViewTitle').textContent = `Events on ${formatDisplayDate(date)}`;
    
    // Fetch events from the server
    fetch(`get_events.php?date=${date}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.events && data.events.length > 0) {
                displayEventsOverview(data.events);
            } else {
                displayNoEventsMessage();
            }
        })
        .catch(error => {
            console.error('Error fetching events:', error);
            displayErrorMessage('Failed to load events. Please try again later.');
        });
}

/**
 * Load details for a specific event and display in the modal
 */
function loadEventDetails(eventId) {
    // Show loading state
    showEventViewModal();
    document.getElementById('eventViewTitle').textContent = 'Loading Event Details...';
    
    // Fetch event details from the server
    fetch(`get_event_details.php?event_id=${eventId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.event) {
                displayEventDetails(data.event);
                // Store event ID for edit button
                document.getElementById('editEventBtn').setAttribute('data-event-id', eventId);
            } else {
                displayErrorMessage('Event not found.');
            }
        })
        .catch(error => {
            console.error('Error fetching event details:', error);
            displayErrorMessage('Failed to load event details. Please try again later.');
        });
}

/**
 * Display event details in the modal
 */
function displayEventDetails(event) {
    // Update modal title
    document.getElementById('eventViewTitle').textContent = `${event.title} - ${formatDisplayDate(event.event_date)}`;
    
    // Display overview information
    displayOverviewTab(event);
    
    // Display vendors information if available
    if (event.vendors && event.vendors.length > 0) {
        displayVendorsTab(event.vendors);
    } else {
        document.getElementById('vendorsList').innerHTML = '<p>No vendor information available for this event.</p>';
    }
    
    // Display labor information if available
    if (event.vendor_labor && event.vendor_labor.length > 0) {
        displayVendorLaborTab(event.vendor_labor);
    } else {
        document.getElementById('vendorLaborList').innerHTML = '<p>No vendor labor information available for this event.</p>';
    }
    
    if (event.company_labor && event.company_labor.length > 0) {
        displayCompanyLaborTab(event.company_labor);
    } else {
        document.getElementById('companyLaborList').innerHTML = '<p>No company labor information available for this event.</p>';
    }
    
    // Display materials information if available
    if (event.materials && event.materials.length > 0) {
        displayMaterialsTab(event.materials);
    } else {
        document.getElementById('materialsList').innerHTML = '<p>No materials information available for this event.</p>';
    }
    
    // Display work progress information if available
    if (event.work_progress && event.work_progress.length > 0) {
        displayWorkProgressTab(event.work_progress);
    } else {
        document.getElementById('workProgressList').innerHTML = '<p>No work progress information available for this event.</p>';
    }
    
    // Display inventory information if available
    if (event.inventory && event.inventory.length > 0) {
        displayInventoryTab(event.inventory);
    } else {
        document.getElementById('inventoryList').innerHTML = '<p>No inventory information available for this event.</p>';
    }
    
    // Display expenses information
    displayExpensesTab(event);
}

/* Functions to display data in each tab - these would be implemented with actual data rendering */
function displayOverviewTab(event) {
    let html = `
        <div class="additional-info">
            <ul class="info-list">
                <li class="info-item">
                    <span class="info-label">Event Date:</span>
                    <span class="info-value">${formatDisplayDate(event.event_date)}</span>
                </li>
                <li class="info-item">
                    <span class="info-label">Created By:</span>
                    <span class="info-value">${event.created_by_name || 'Unknown'}</span>
                </li>
                <li class="info-item">
                    <span class="info-label">Created On:</span>
                    <span class="info-value">${formatDisplayDateTime(event.created_at)}</span>
                </li>
                <li class="info-item">
                    <span class="info-label">Last Updated:</span>
                    <span class="info-value">${formatDisplayDateTime(event.updated_at)}</span>
                </li>
            </ul>
        </div>
    `;
    
    document.getElementById('eventInfo').innerHTML = html;
}

// Utility functions
function formatDisplayDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long',
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
    });
}

function formatDisplayDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showEventViewModal() {
    const modal = document.getElementById('eventViewModal');
    modal.style.display = 'block';
    
    // Always reset to overview tab
    switchTab('overview');
}

function closeEventViewModal() {
    const modal = document.getElementById('eventViewModal');
    modal.style.display = 'none';
}

// Add functions to global scope for access by other scripts
window.loadEventDetails = loadEventDetails;
window.showEventViewModal = showEventViewModal;
window.hideEventViewModal = closeEventViewModal;

// Create a displayErrorMessage function if it doesn't exist
function displayErrorMessage(message) {
    document.getElementById('overviewTab').classList.add('active');
    document.getElementById('eventInfo').innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            ${message}
        </div>
    `;
} 