/**
 * Calendar Events Modal JS
 * Handles event interactions, modals, and form processing for calendar events
 */

document.addEventListener('DOMContentLoaded', function() {
    // Load the enhanced CSS file dynamically
    loadEnhancedStyles();
    
    // Create modal container elements if they don't exist
    createModalElements();

    // Initialize event handlers for both types of modals
    initializeEventHandlers();
});

/**
 * Loads the enhanced CSS file for the calendar events modal
 */
function loadEnhancedStyles() {
    if (!document.getElementById('calendar-events-modal-enhanced-css')) {
        const link = document.createElement('link');
        link.id = 'calendar-events-modal-enhanced-css';
        link.rel = 'stylesheet';
        link.href = 'css/supervisor/calendar-events-modal-enhanced.css';
        document.head.appendChild(link);
    }
}

/**
 * Creates the necessary modal elements and adds them to the DOM
 */
function createModalElements() {
    // Toast container for notifications
    if (!document.getElementById('calendar-toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.id = 'calendar-toast-container';
        toastContainer.className = 'calendar-toast-container';
        document.body.appendChild(toastContainer);
        
        // Add toast styles
        if (!document.getElementById('calendar-toast-styles')) {
            const style = document.createElement('style');
            style.id = 'calendar-toast-styles';
            style.textContent = `
                .calendar-toast-container {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 1060;
                }
                .calendar-toast {
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
                    padding: 15px 20px;
                    margin-bottom: 12px;
                    min-width: 280px;
                    max-width: 350px;
                    display: flex;
                    align-items: center;
                    transform: translateX(100%);
                    opacity: 0;
                    transition: all 0.3s ease;
                }
                .calendar-toast.show {
                    transform: translateX(0);
                    opacity: 1;
                }
                .calendar-toast-success {
                    border-left: 4px solid #38a169;
                }
                .calendar-toast-error {
                    border-left: 4px solid #e53e3e;
                }
                .calendar-toast-warning {
                    border-left: 4px solid #e67e22;
                }
                .calendar-toast-icon {
                    margin-right: 12px;
                    font-size: 1.3rem;
                }
                .calendar-toast-success .calendar-toast-icon {
                    color: #38a169;
                }
                .calendar-toast-error .calendar-toast-icon {
                    color: #e53e3e;
                }
                .calendar-toast-warning .calendar-toast-icon {
                    color: #e67e22;
                }
                .calendar-toast-content {
                    flex: 1;
                }
                .calendar-toast-title {
                    font-weight: 600;
                    margin-bottom: 3px;
                    font-size: 1rem;
                }
                .calendar-toast-message {
                    font-size: 0.9rem;
                    color: #666;
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Add Event Modal
    if (!document.getElementById('add-event-modal-container')) {
        const addEventModalHTML = `
            <div id="add-event-modal-container" class="calendar-event-modal-overlay">
                <div class="add-event-modal">
                    <div class="add-event-modal-header">
                        <h3><i class="fas fa-calendar-plus"></i> Add New Event</h3>
                        <button class="add-event-modal-close">&times;</button>
                    </div>
                    <div class="add-event-modal-body">
                        <form id="add-event-form">
                            <div class="event-form-row">
                                <div class="event-form-col">
                            <div class="add-event-form-group">
                                        <label for="event-title"><i class="fas fa-bookmark"></i> Event Title</label>
                                <input type="text" id="event-title" name="event-title" class="add-event-form-control" placeholder="Enter event title" required>
                            </div>
                                </div>
                                <div class="event-form-col">
                            <div class="add-event-form-group">
                                        <label for="event-date-display"><i class="fas fa-calendar-alt"></i> Event Date</label>
                                        <input type="text" id="event-date-display" class="add-event-form-control" readonly>
                                        <input type="hidden" id="event-date" name="event-date">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vendors and Labours Section -->
                            <div class="calendar-event-vendors-section">
                                <div class="calendar-event-section-header">
                                    <h4><i class="fas fa-truck"></i> Vendors and Labours</h4>
                            </div>
                            
                                <div id="calendar-vendors-container" class="calendar-vendors-container">
                                    <!-- Vendor items will be added here dynamically -->
                            </div>
                            
                                <button type="button" id="calendar-add-vendor-btn" class="calendar-add-vendor-btn">
                                    <i class="fas fa-plus-circle"></i> Add Vendor
                                </button>
                            </div>
                            
                            <!-- Company Labour Section -->
                            <div class="calendar-event-company-section">
                                <div class="calendar-event-section-header">
                                    <h4><i class="fas fa-building"></i> Company Labour</h4>
                            </div>
                            
                                <div id="calendar-company-container" class="calendar-company-container">
                                    <!-- Company labour items will be added here dynamically -->
                                </div>
                                
                                <button type="button" id="calendar-add-company-btn" class="calendar-add-company-btn">
                                    <i class="fas fa-plus-circle"></i> Add Company Labour
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="add-event-modal-footer">
                        <button class="add-event-btn add-event-btn-cancel"><i class="fas fa-times-circle"></i> Cancel</button>
                        <button class="add-event-btn event-submit-btn"><i class="fas fa-check-circle"></i> Save Event</button>
                    </div>
                </div>
            </div>
        `;
        
        const addEventModalElement = document.createElement('div');
        addEventModalElement.innerHTML = addEventModalHTML;
        document.body.appendChild(addEventModalElement.firstElementChild);
    }
    
    // View Event Modal
    if (!document.getElementById('view-event-modal-container')) {
        const viewEventModalHTML = `
            <div id="view-event-modal-container" class="calendar-event-modal-overlay">
                <div class="view-event-modal">
                    <div class="view-event-modal-header" id="view-event-header">
                        <h3 id="view-event-title">Event Title</h3>
                        <button class="view-event-modal-close">&times;</button>
                    </div>
                    <div class="view-event-modal-body">
                        <div class="view-event-detail">
                            <div class="view-event-detail-label"><i class="fas fa-calendar-day"></i> Date & Time</div>
                            <div class="view-event-detail-value" id="view-event-datetime">May 15, 2023 - 10:00 AM</div>
                        </div>
                        
                        <div class="view-event-detail">
                            <div class="view-event-detail-label"><i class="fas fa-hourglass-half"></i> Duration</div>
                            <div class="view-event-detail-value" id="view-event-duration">60 minutes</div>
                        </div>
                        
                        <div class="view-event-detail">
                            <div class="view-event-detail-label"><i class="fas fa-tag"></i> Type</div>
                            <div class="view-event-detail-value" id="view-event-type">Inspection</div>
                        </div>
                        
                        <div class="view-event-detail">
                            <div class="view-event-detail-label"><i class="fas fa-map-marker-alt"></i> Location</div>
                            <div class="view-event-detail-value" id="view-event-location">Building A, Floor 2</div>
                        </div>
                        
                        <div class="view-event-detail">
                            <div class="view-event-detail-label"><i class="fas fa-align-left"></i> Description</div>
                            <div class="view-event-detail-value" id="view-event-description">Safety check for electrical installations</div>
                        </div>
                        
                        <div class="view-event-detail">
                            <div class="view-event-detail-label"><i class="fas fa-users"></i> Participants</div>
                            <div class="view-event-detail-value" id="view-event-participants">John Doe, Jane Smith</div>
                        </div>
                    </div>
                    <div class="view-event-modal-footer">
                        <button class="view-event-btn view-event-btn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                        <button class="view-event-btn view-event-btn-edit"><i class="fas fa-edit"></i> Edit</button>
                        <button class="view-event-btn view-event-btn-close"><i class="fas fa-times"></i> Close</button>
                    </div>
                </div>
            </div>
        `;
        
        const viewEventModalElement = document.createElement('div');
        viewEventModalElement.innerHTML = viewEventModalHTML;
        document.body.appendChild(viewEventModalElement.firstElementChild);
    }
    
    // Add vendor template to use for creating new vendor entries
    window.vendorTemplate = `
        <div class="calendar-vendor-item supervisor-vendor-entry">
            <div class="calendar-vendor-header">
                <h5><span class="calendar-vendor-number">1</span> Vendor Information</h5>
                <button type="button" class="calendar-vendor-remove-btn" aria-label="Remove vendor">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="calendar-vendor-form-row">
                <div class="calendar-vendor-form-group">
                    <label><i class="fas fa-tags"></i> Vendor Type</label>
                    <select class="calendar-vendor-type-select">
                        <option value="">Select Vendor Type</option>
                        <option value="material">Material Supplier</option>
                        <option value="equipment">Equipment Supplier</option>
                        <option value="labour">Labour Contractor</option>
                        <option value="transport">Transport Service</option>
                        <option value="custom">Custom Vendor Type</option>
                    </select>
                    <input type="text" class="calendar-vendor-custom-type" placeholder="Enter custom vendor type" style="display: none;">
                </div>
                <div class="calendar-vendor-form-group">
                    <label><i class="fas fa-building"></i> Vendor Name</label>
                    <input type="text" class="calendar-vendor-name" placeholder="Enter vendor name">
                </div>
                <div class="calendar-vendor-form-group">
                    <label><i class="fas fa-phone-alt"></i> Contact Number</label>
                    <input type="tel" class="calendar-vendor-contact" placeholder="Enter contact number">
                </div>
            </div>
            
            <!-- Vendor Material Section -->
            <div class="calendar-vendor-material-section">
                <div class="calendar-vendor-section-subheader">
                    <h6><i class="fas fa-boxes"></i> Vendor Material</h6>
                </div>
                <div class="calendar-vendor-form-row">
                    <div class="calendar-vendor-form-group">
                        <label><i class="fas fa-image"></i> Material Pictures</label>
                        <div class="calendar-vendor-file-upload">
                            <input type="file" class="calendar-vendor-material-pics" accept="image/*" multiple>
                            <div class="calendar-vendor-file-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click to upload pictures</span>
                            </div>
                        </div>
                    </div>
                    <div class="calendar-vendor-form-group">
                        <label><i class="fas fa-comment-alt"></i> Remarks</label>
                        <textarea class="calendar-vendor-remarks" placeholder="Enter remarks about the material"></textarea>
                    </div>
                </div>
                <div class="calendar-vendor-form-row">
                    <div class="calendar-vendor-form-group">
                        <label><i class="fas fa-rupee-sign"></i> Amount</label>
                        <input type="number" class="calendar-vendor-amount" placeholder="Enter amount">
                    </div>
                    <div class="calendar-vendor-form-group">
                        <label><i class="fas fa-file-invoice"></i> Bill Picture</label>
                        <div class="calendar-vendor-file-upload">
                            <input type="file" class="calendar-vendor-bill-pic" accept="image/*">
                            <div class="calendar-vendor-file-label">
                                <i class="fas fa-receipt"></i>
                                <span>Upload bill image</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Labour Section -->
            <div class="supervisor-labour-section">
                <div class="supervisor-labour-header">
                    <h6><i class="fas fa-hard-hat"></i> Labour Management</h6>
                </div>
                
                <!-- Labour Container - Labour entries will be added here -->
                <div class="supervisor-labour-container"></div>
                
                <!-- Add Labour Button -->
                <button type="button" class="supervisor-add-labour-btn">
                    <i class="fas fa-user-plus"></i> Add Labour
                </button>
            </div>
        </div>
    `;

    // Labour entry template
    window.labourTemplate = `
        <div class="supervisor-labour-entry">
            <div class="supervisor-labour-entry-header">
                <span class="supervisor-labour-number">1</span>
                <button type="button" class="supervisor-labour-remove-btn" aria-label="Remove labour">
                    <i class="fas fa-user-minus"></i>
                </button>
            </div>
            <div class="supervisor-labour-form-row">
                <div class="supervisor-labour-form-group">
                    <label><i class="fas fa-user"></i> Labour Name</label>
                    <input type="text" class="supervisor-labour-name" placeholder="Enter labour name">
                </div>
                <div class="supervisor-labour-form-group">
                    <label><i class="fas fa-phone"></i> Labour Number</label>
                    <input type="tel" class="supervisor-labour-number-input" placeholder="Enter contact number">
                </div>
            </div>
            <div class="supervisor-labour-form-row">
                <div class="supervisor-labour-form-group supervisor-attendance-group">
                    <label><i class="fas fa-sun"></i> Morning Attendance</label>
                    <div class="supervisor-attendance-options">
                        <label class="supervisor-attendance-option">
                            <input type="radio" name="morning-attendance-\${Date.now()}" value="present" checked>
                            <span>Present</span>
                        </label>
                        <label class="supervisor-attendance-option">
                            <input type="radio" name="morning-attendance-\${Date.now()}" value="absent">
                            <span>Absent</span>
                        </label>
                    </div>
                </div>
                <div class="supervisor-labour-form-group supervisor-attendance-group">
                    <label><i class="fas fa-moon"></i> Evening Attendance</label>
                    <div class="supervisor-attendance-options">
                        <label class="supervisor-attendance-option">
                            <input type="radio" name="evening-attendance-\${Date.now()}" value="present" checked>
                            <span>Present</span>
                        </label>
                        <label class="supervisor-attendance-option">
                            <input type="radio" name="evening-attendance-\${Date.now()}" value="absent">
                            <span>Absent</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Wages Section -->
            <div class="slw-wages-section">
                <div class="slw-section-title">
                    <i class="fas fa-money-bill-wave"></i> Wages Details
                </div>
                <div class="supervisor-labour-form-row">
                    <div class="supervisor-labour-form-group">
                        <label><i class="fas fa-rupee-sign"></i> Wages per Day</label>
                        <input type="number" class="slw-wages-per-day" placeholder="Enter daily wage amount" min="0" step="10">
                    </div>
                    <div class="supervisor-labour-form-group">
                        <label><i class="fas fa-calculator"></i> Total Day Wages</label>
                        <input type="number" class="slw-total-day-wages" placeholder="Calculated total" readonly>
                    </div>
                </div>
            </div>
            
            <!-- Overtime Section -->
            <div class="slw-overtime-section">
                <div class="slw-section-title">
                    <i class="fas fa-business-time"></i> Overtime Details
                </div>
                <div class="supervisor-labour-form-row">
                    <div class="supervisor-labour-form-group slw-ot-hours-group">
                        <label><i class="fas fa-hourglass-half"></i> OT Hours</label>
                        <input type="number" class="slw-ot-hours" placeholder="Hours" min="0" max="24">
                    </div>
                    <div class="supervisor-labour-form-group slw-ot-minutes-group">
                        <label><i class="fas fa-clock"></i> OT Minutes</label>
                        <input type="number" class="slw-ot-minutes" placeholder="Minutes" min="0" max="59">
                    </div>
                    <div class="supervisor-labour-form-group">
                        <label><i class="fas fa-rupee-sign"></i> OT Rate/Hour</label>
                        <input type="number" class="slw-ot-rate" placeholder="Rate per hour" min="0" step="10">
                    </div>
                </div>
                <div class="supervisor-labour-form-row">
                    <div class="supervisor-labour-form-group">
                        <label><i class="fas fa-calculator"></i> Total OT Amount</label>
                        <input type="number" class="slw-total-ot-amount" placeholder="Calculated amount" readonly>
                    </div>
                </div>
            </div>
            
            <!-- Travel Expenses Section -->
            <div class="slw-travel-section">
                <div class="slw-section-title">
                    <i class="fas fa-route"></i> Travel Expenses of Labour
                </div>
                <div class="supervisor-labour-form-row">
                    <div class="supervisor-labour-form-group">
                        <label><i class="fas fa-bus"></i> Mode of Transport</label>
                        <select class="slw-transport-mode">
                            <option value="">Select mode</option>
                            <option value="bus">Bus</option>
                            <option value="train">Train</option>
                            <option value="auto">Auto</option>
                            <option value="taxi">Taxi</option>
                            <option value="own-vehicle">Own Vehicle</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="supervisor-labour-form-group">
                        <label><i class="fas fa-rupee-sign"></i> Travel Amount</label>
                        <input type="number" class="slw-travel-amount" placeholder="Enter travel amount" min="0">
                    </div>
                </div>
            </div>
            
            <!-- Grand Total Section -->
            <div class="slw-grand-total-section">
                <div class="slw-section-title">
                    <i class="fas fa-file-invoice-dollar"></i> Payment Summary
                </div>
                <div class="slw-grand-total-row">
                    <div class="slw-grand-total-label">Grand Total</div>
                    <div class="slw-grand-total-amount">₹ <span class="slw-calculated-grand-total">0.00</span></div>
                </div>
            </div>
        </div>
    `;

    // Company labour template to use for creating new company labour entries
    window.companyLabourTemplate = `
        <div class="calendar-company-item supervisor-company-entry">
            <div class="calendar-company-header">
                <h5><span class="calendar-company-number">1</span> Company Labour Information</h5>
                <button type="button" class="calendar-company-remove-btn" aria-label="Remove company labour">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Company Labour Container - Labour entries will be added here -->
            <div class="scl-labour-container"></div>
            
            <!-- Add Company Labour Button -->
            <button type="button" class="scl-add-labour-btn">
                <i class="fas fa-user-plus"></i> Add Company Labour
            </button>
        </div>
    `;
    
    // Company labour entry template
    window.companyLabourEntryTemplate = `
        <div class="scl-labour-entry">
            <div class="scl-labour-entry-header">
                <span class="scl-labour-number">1</span>
                <button type="button" class="scl-labour-remove-btn" aria-label="Remove labour">
                    <i class="fas fa-user-minus"></i>
                </button>
            </div>
            <div class="scl-labour-form-row">
                <div class="scl-labour-form-group">
                    <label><i class="fas fa-user"></i> Labour Name</label>
                    <input type="text" class="scl-labour-name" placeholder="Enter labour name">
                </div>
                <div class="scl-labour-form-group">
                    <label><i class="fas fa-phone"></i> Labour Number</label>
                    <input type="tel" class="scl-labour-number-input" placeholder="Enter contact number">
                </div>
            </div>
            <div class="scl-labour-form-row">
                <div class="scl-labour-form-group scl-attendance-group">
                    <label><i class="fas fa-sun"></i> Morning Attendance</label>
                    <div class="scl-attendance-options">
                        <label class="scl-attendance-option">
                            <input type="radio" name="scl-morning-attendance-\${Date.now()}" value="present" checked>
                            <span>Present</span>
                        </label>
                        <label class="scl-attendance-option">
                            <input type="radio" name="scl-morning-attendance-\${Date.now()}" value="absent">
                            <span>Absent</span>
                        </label>
                    </div>
                </div>
                <div class="scl-labour-form-group scl-attendance-group">
                    <label><i class="fas fa-moon"></i> Evening Attendance</label>
                    <div class="scl-attendance-options">
                        <label class="scl-attendance-option">
                            <input type="radio" name="scl-evening-attendance-\${Date.now()}" value="present" checked>
                            <span>Present</span>
                        </label>
                        <label class="scl-attendance-option">
                            <input type="radio" name="scl-evening-attendance-\${Date.now()}" value="absent">
                            <span>Absent</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Wages Section -->
            <div class="scl-wages-section">
                <div class="scl-section-title">
                    <i class="fas fa-money-bill-wave"></i> Wages Details
                </div>
                <div class="scl-labour-form-row">
                    <div class="scl-labour-form-group">
                        <label><i class="fas fa-rupee-sign"></i> Wages per Day</label>
                        <input type="number" class="scl-wages-per-day" placeholder="Enter daily wage amount" min="0" step="10">
                    </div>
                    <div class="scl-labour-form-group">
                        <label><i class="fas fa-calculator"></i> Total Day Wages</label>
                        <input type="number" class="scl-total-day-wages" placeholder="Calculated total" readonly>
                    </div>
                </div>
            </div>
            
            <!-- Overtime Section -->
            <div class="scl-overtime-section">
                <div class="scl-section-title">
                    <i class="fas fa-business-time"></i> Overtime Details
                </div>
                <div class="scl-labour-form-row">
                    <div class="scl-labour-form-group scl-ot-hours-group">
                        <label><i class="fas fa-hourglass-half"></i> OT Hours</label>
                        <input type="number" class="scl-ot-hours" placeholder="Hours" min="0" max="24">
                    </div>
                    <div class="scl-labour-form-group scl-ot-minutes-group">
                        <label><i class="fas fa-clock"></i> OT Minutes</label>
                        <input type="number" class="scl-ot-minutes" placeholder="Minutes" min="0" max="59">
                    </div>
                    <div class="scl-labour-form-group">
                        <label><i class="fas fa-rupee-sign"></i> OT Rate/Hour</label>
                        <input type="number" class="scl-ot-rate" placeholder="Rate per hour" min="0" step="10">
                    </div>
                </div>
                <div class="scl-labour-form-row">
                    <div class="scl-labour-form-group">
                        <label><i class="fas fa-calculator"></i> Total OT Amount</label>
                        <input type="number" class="scl-total-ot-amount" placeholder="Calculated amount" readonly>
                    </div>
                </div>
            </div>
            
            <!-- Travel Expenses Section -->
            <div class="scl-travel-section">
                <div class="scl-section-title">
                    <i class="fas fa-route"></i> Travel Expenses of Labour
                </div>
                <div class="scl-labour-form-row">
                    <div class="scl-labour-form-group">
                        <label><i class="fas fa-bus"></i> Mode of Transport</label>
                        <select class="scl-transport-mode">
                            <option value="">Select mode</option>
                            <option value="bus">Bus</option>
                            <option value="train">Train</option>
                            <option value="auto">Auto</option>
                            <option value="taxi">Taxi</option>
                            <option value="own-vehicle">Own Vehicle</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="scl-labour-form-group">
                        <label><i class="fas fa-rupee-sign"></i> Travel Amount</label>
                        <input type="number" class="scl-travel-amount" placeholder="Enter travel amount" min="0">
                    </div>
                </div>
            </div>
            
            <!-- Grand Total Section -->
            <div class="scl-grand-total-section">
                <div class="scl-section-title">
                    <i class="fas fa-file-invoice-dollar"></i> Payment Summary
                </div>
                <div class="scl-grand-total-row">
                    <div class="scl-grand-total-label">Grand Total</div>
                    <div class="scl-grand-total-amount">₹ <span class="scl-calculated-grand-total">0.00</span></div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Initialize all event handlers for modals and buttons
 */
function initializeEventHandlers() {
    // Add click events for the add event buttons on calendar days
    document.addEventListener('click', function(event) {
        // Handle opening add event modal when + button is clicked
        if (event.target.classList.contains('supervisor-add-event-btn')) {
            event.stopPropagation(); // Prevent calendar day click event
            
            const day = event.target.getAttribute('data-day');
            const month = event.target.getAttribute('data-month');
            const year = event.target.getAttribute('data-year');
            
            showAddEventModal(day, month, year);
        }
        
        // Handle opening view event modal when an event is clicked
        if (event.target.classList.contains('supervisor-calendar-event')) {
            event.stopPropagation(); // Prevent calendar day click event
            
            const eventId = event.target.getAttribute('data-event-id');
            const eventType = event.target.classList.contains('event-inspection') ? 'inspection' : 
                              event.target.classList.contains('event-delivery') ? 'delivery' :
                              event.target.classList.contains('event-meeting') ? 'meeting' :
                              event.target.classList.contains('event-report') ? 'report' : 'issue';
            
            showViewEventModal(eventId, eventType, event.target.textContent.trim());
        }
        
        // Handle opening view event modal when calendar day is clicked (if it has events)
        if (event.target.classList.contains('supervisor-calendar-day') || 
            (event.target.closest('.supervisor-calendar-day') && !event.target.classList.contains('supervisor-add-event-btn'))) {
            
            const calendarDay = event.target.classList.contains('supervisor-calendar-day') ? 
                               event.target : event.target.closest('.supervisor-calendar-day');
            
            // Only handle this if the day has events
            if (calendarDay.classList.contains('has-events')) {
                // Get all events in this day
                const events = calendarDay.querySelectorAll('.supervisor-calendar-event');
                if (events.length > 0) {
                    // Get first event's details
                        const eventId = events[0].getAttribute('data-event-id');
                        const eventType = events[0].classList.contains('event-inspection') ? 'inspection' : 
                                      events[0].classList.contains('event-delivery') ? 'delivery' :
                                      events[0].classList.contains('event-meeting') ? 'meeting' :
                                      events[0].classList.contains('event-report') ? 'report' : 'issue';
                                      
                        showViewEventModal(eventId, eventType, events[0].textContent.trim());
                }
            }
        }
        
        // Handle vendor type selection change
        if (event.target.classList.contains('calendar-vendor-type-select')) {
            const select = event.target;
            const customInput = select.parentNode.querySelector('.calendar-vendor-custom-type');
            
            if (select.value === 'custom') {
                customInput.style.display = 'block';
                customInput.focus();
                    } else {
                customInput.style.display = 'none';
            }
        }
        
        // Handle remove vendor button
        if (event.target.classList.contains('calendar-vendor-remove-btn') || 
            event.target.closest('.calendar-vendor-remove-btn')) {
            const button = event.target.classList.contains('calendar-vendor-remove-btn') ? 
                           event.target : event.target.closest('.calendar-vendor-remove-btn');
            const vendorItem = button.closest('.calendar-vendor-item');
            
            if (vendorItem) {
                vendorItem.remove();
                // Update the numbering of remaining vendors
                updateVendorNumbers();
            }
        }
        
        // Handle add labour button clicks
        if (event.target.classList.contains('supervisor-add-labour-btn') || 
            event.target.closest('.supervisor-add-labour-btn')) {
            const button = event.target.classList.contains('supervisor-add-labour-btn') ? 
                          event.target : event.target.closest('.supervisor-add-labour-btn');
            const vendorItem = button.closest('.supervisor-vendor-entry');
            
            if (vendorItem) {
                addNewLabour(vendorItem);
            }
        }
        
        // Handle remove labour button clicks
        if (event.target.classList.contains('supervisor-labour-remove-btn') || 
            event.target.closest('.supervisor-labour-remove-btn')) {
            const button = event.target.classList.contains('supervisor-labour-remove-btn') ? 
                          event.target : event.target.closest('.supervisor-labour-remove-btn');
            const labourEntry = button.closest('.supervisor-labour-entry');
            const vendorItem = button.closest('.supervisor-vendor-entry');
            
            if (labourEntry && vendorItem) {
                labourEntry.remove();
                // Update the numbering of remaining labour entries
                updateLabourNumbers(vendorItem);
            }
        }
        
        // Handle add labour button clicks for company labour
        if (event.target.classList.contains('scl-add-labour-btn') || 
            event.target.closest('.scl-add-labour-btn')) {
            const button = event.target.classList.contains('scl-add-labour-btn') ? 
                          event.target : event.target.closest('.scl-add-labour-btn');
            const companyItem = button.closest('.supervisor-company-entry');
            
            if (companyItem) {
                addNewCompanyLabour(companyItem);
            }
        }
        
        // Handle remove company labour button clicks
        if (event.target.classList.contains('scl-labour-remove-btn') || 
            event.target.closest('.scl-labour-remove-btn')) {
            const button = event.target.classList.contains('scl-labour-remove-btn') ? 
                          event.target : event.target.closest('.scl-labour-remove-btn');
            const labourEntry = button.closest('.scl-labour-entry');
            const companyItem = button.closest('.supervisor-company-entry');
            
            if (labourEntry && companyItem) {
                labourEntry.remove();
                // Update the numbering of remaining labour entries
                updateCompanyLabourNumbers(companyItem);
            }
        }
        
        // Handle add company button
        if (event.target.id === 'calendar-add-company-btn' || 
            event.target.closest('#calendar-add-company-btn')) {
            addNewCompanyItem();
        }
        
        // Handle remove company button
        if (event.target.classList.contains('calendar-company-remove-btn') || 
            event.target.closest('.calendar-company-remove-btn')) {
            const button = event.target.classList.contains('calendar-company-remove-btn') ? 
                           event.target : event.target.closest('.calendar-company-remove-btn');
            const companyItem = button.closest('.calendar-company-item');
            
            if (companyItem) {
                companyItem.remove();
                // Update the numbering of remaining company items
                updateCompanyNumbers();
            }
        }
    });
    
    // Add vendor button
    document.addEventListener('click', function(event) {
        if (event.target.id === 'calendar-add-vendor-btn' || 
            event.target.closest('#calendar-add-vendor-btn')) {
            addNewVendor();
        }
    });
    
    // File input change handler
    document.addEventListener('change', function(event) {
        // Handle file input changes
        if (event.target.classList.contains('calendar-vendor-material-pics')) {
            handleMaterialPicsChange(event.target);
        } else if (event.target.classList.contains('calendar-vendor-bill-pic')) {
            handleBillPicChange(event.target);
        }
    });
    
    // Add event modal close button
    document.querySelector('.add-event-modal-close').addEventListener('click', hideAddEventModal);
    
    // Add event modal cancel button
    document.querySelector('.add-event-btn-cancel').addEventListener('click', hideAddEventModal);
    
    // Add event modal submit button
    document.querySelector('.event-submit-btn').addEventListener('click', saveEvent);
    
    // View event modal close button
    document.querySelector('.view-event-modal-close').addEventListener('click', hideViewEventModal);
    
    // View event modal close button in footer
    document.querySelector('.view-event-btn-close').addEventListener('click', hideViewEventModal);
    
    // View event modal edit button
    document.querySelector('.view-event-btn-edit').addEventListener('click', function() {
        const eventId = this.getAttribute('data-event-id');
        editEvent(eventId);
    });
    
    // View event modal delete button
    document.querySelector('.view-event-btn-delete').addEventListener('click', function() {
        const eventId = this.getAttribute('data-event-id');
        deleteEvent(eventId);
    });
    
    // Add input event listeners for wage and overtime calculations
    document.addEventListener('input', function(event) {
        // Handle wages calculation
        if (event.target.classList.contains('slw-wages-per-day')) {
            calculateTotalDayWages(event.target);
        }
        
        // Handle overtime calculation
        if (event.target.classList.contains('slw-ot-hours') || 
            event.target.classList.contains('slw-ot-minutes') || 
            event.target.classList.contains('slw-ot-rate')) {
            calculateOvertimeAmount(event.target);
        }
        
        // Handle travel amount changes
        if (event.target.classList.contains('slw-travel-amount') ||
            event.target.classList.contains('slw-wages-per-day') ||
            event.target.classList.contains('slw-ot-hours') ||
            event.target.classList.contains('slw-ot-minutes') ||
            event.target.classList.contains('slw-ot-rate')) {
            // Any of these changes should trigger a grand total recalculation
            updateGrandTotal(event.target);
        }
        
        // Handle wages calculation for company labour
        if (event.target.classList.contains('scl-wages-per-day')) {
            calculateCompanyTotalDayWages(event.target);
        }
        
        // Handle overtime calculation for company labour
        if (event.target.classList.contains('scl-ot-hours') || 
            event.target.classList.contains('scl-ot-minutes') || 
            event.target.classList.contains('scl-ot-rate')) {
            calculateCompanyOvertimeAmount(event.target);
        }
        
        // Handle travel amount changes for company labour
        if (event.target.classList.contains('scl-travel-amount') ||
            event.target.classList.contains('scl-wages-per-day') ||
            event.target.classList.contains('scl-ot-hours') ||
            event.target.classList.contains('scl-ot-minutes') ||
            event.target.classList.contains('scl-ot-rate')) {
            // Any of these changes should trigger a grand total recalculation
            updateCompanyGrandTotal(event.target);
        }
    });
}

/**
 * Handle material pictures file selection
 */
function handleMaterialPicsChange(inputElement) {
    const fileUploadDiv = inputElement.closest('.calendar-vendor-file-upload');
    const fileLabel = fileUploadDiv.querySelector('.calendar-vendor-file-label');
    
    // Check if there are files selected
    if (inputElement.files && inputElement.files.length > 0) {
        // Update the label text
        fileLabel.innerHTML = `
            <i class="fas fa-check"></i>
            <span>${inputElement.files.length} file(s) selected</span>
        `;
        
        // Add selected class to file upload div
        fileUploadDiv.classList.add('has-files');
    } else {
        // Reset to original state
        fileLabel.innerHTML = `
            <i class="fas fa-cloud-upload-alt"></i>
            <span>Click to upload pictures</span>
        `;
        fileUploadDiv.classList.remove('has-files');
    }
}

/**
 * Handle bill picture file selection
 */
function handleBillPicChange(inputElement) {
    const fileUploadDiv = inputElement.closest('.calendar-vendor-file-upload');
    const fileLabel = fileUploadDiv.querySelector('.calendar-vendor-file-label');
    
    // Check if there is a file selected
    if (inputElement.files && inputElement.files.length > 0) {
        const fileName = inputElement.files[0].name;
        // Update the label text with file name
        fileLabel.innerHTML = `
            <i class="fas fa-check"></i>
            <span>${fileName}</span>
        `;
        
        // Add selected class to file upload div
        fileUploadDiv.classList.add('has-files');
    } else {
        // Reset to original state
        fileLabel.innerHTML = `
            <i class="fas fa-receipt"></i>
            <span>Upload bill image</span>
        `;
        fileUploadDiv.classList.remove('has-files');
    }
}

/**
 * Add a new vendor to the vendors container
 */
function addNewVendor() {
    const vendorsContainer = document.getElementById('calendar-vendors-container');
    
    // Create temporary container to hold the template HTML
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = window.vendorTemplate;
    
    // Get the vendor item element from the container
    const vendorItem = tempContainer.firstElementChild;
    
    // Update the vendor number based on existing vendors
    const existingVendors = vendorsContainer.querySelectorAll('.supervisor-vendor-entry');
    const vendorNumber = existingVendors.length + 1;
    vendorItem.querySelector('.calendar-vendor-number').textContent = vendorNumber;
    
    // Add unique ID to this vendor item
    vendorItem.id = `supervisor-vendor-${vendorNumber}`;
    
    // Add to the vendors container
    vendorsContainer.appendChild(vendorItem);
    
    // Focus the vendor type select
    const vendorTypeSelect = vendorItem.querySelector('.calendar-vendor-type-select');
    vendorTypeSelect.focus();
}

/**
 * Update the vendor numbers after removing a vendor
 */
function updateVendorNumbers() {
    const vendorsContainer = document.getElementById('calendar-vendors-container');
    const vendorItems = vendorsContainer.querySelectorAll('.supervisor-vendor-entry');
    
    vendorItems.forEach((item, index) => {
        // Update the number in the span
        item.querySelector('.calendar-vendor-number').textContent = index + 1;
        
        // Update the ID
        item.id = `supervisor-vendor-${index + 1}`;
    });
}

/**
 * Shows the add event modal with the selected date
 */
function showAddEventModal(day, month, year) {
    // Format the date for display
    const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    const dateObj = new Date(year, month - 1, day);
    const formattedDate = `${months[month - 1]} ${day}, ${year}`;
    
    // Set the date values
    document.getElementById('event-date').value = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
    document.getElementById('event-date-display').value = formattedDate;
    
    // Clear existing vendors
    const vendorsContainer = document.getElementById('calendar-vendors-container');
    vendorsContainer.innerHTML = '';
    
    // Show the modal
    const modal = document.getElementById('add-event-modal-container');
    modal.classList.add('active');
    
    // Focus on the title field
    setTimeout(() => {
        document.getElementById('event-title').focus();
    }, 100);
}

/**
 * Hides the add event modal
 */
function hideAddEventModal() {
    const modal = document.getElementById('add-event-modal-container');
    modal.classList.remove('active');
    
    // Reset form
    document.getElementById('add-event-form').reset();
    
    // Clear vendors and company labour
    const vendorsContainer = document.getElementById('calendar-vendors-container');
    vendorsContainer.innerHTML = '';
    
    const companyContainer = document.getElementById('calendar-company-container');
    if (companyContainer) {
        companyContainer.innerHTML = '';
    }
}

/**
 * Saves the event data
 */
function saveEvent() {
    // Get form values
    const eventDate = document.getElementById('event-date').value;
    const eventTitle = document.getElementById('event-title').value;
    
    // Basic validation
    if (!eventTitle.trim()) {
        showToast('Error', 'Please enter an event title', 'error');
        return;
    }
    
    // Show loading indicator for file uploads
    showUploadLoader();
    
    // Get vendor data (if any)
    const vendorItems = document.querySelectorAll('.supervisor-vendor-entry');
    const vendors = [];
    
    vendorItems.forEach(item => {
        const typeSelect = item.querySelector('.calendar-vendor-type-select');
        const customTypeInput = item.querySelector('.calendar-vendor-custom-type');
        const nameInput = item.querySelector('.calendar-vendor-name');
        const contactInput = item.querySelector('.calendar-vendor-contact');
        
        // Get vendor material information
        const remarksInput = item.querySelector('.calendar-vendor-remarks');
        const amountInput = item.querySelector('.calendar-vendor-amount');
        const materialPicsInput = item.querySelector('.calendar-vendor-material-pics');
        const billPicInput = item.querySelector('.calendar-vendor-bill-pic');
        
        // Get the vendor type (either from select or custom input)
        let vendorType = typeSelect.value;
        if (vendorType === 'custom') {
            vendorType = customTypeInput.value.trim();
        }
        
        const vendorName = nameInput.value.trim();
        const vendorContact = contactInput.value.trim();
        
        // Get material info
        const remarks = remarksInput ? remarksInput.value.trim() : '';
        const amount = amountInput ? amountInput.value : '';
        
        // Properly handle file inputs with actual files
        const materialImageFile = materialPicsInput && materialPicsInput.files && materialPicsInput.files.length > 0 
                                ? materialPicsInput.files[0] : null;
        const billImageFile = billPicInput && billPicInput.files && billPicInput.files.length > 0
                            ? billPicInput.files[0] : null;
        
        // Get labour data
        const labourEntries = item.querySelectorAll('.supervisor-labour-entry');
        const labours = [];
        
        labourEntries.forEach(labourEntry => {
            const labourName = labourEntry.querySelector('.supervisor-labour-name').value.trim();
            const labourNumber = labourEntry.querySelector('.supervisor-labour-number-input').value.trim();
            
            // Get attendance data
            const morningAttendance = labourEntry.querySelector('input[name^="morning-attendance-"]:checked').value;
            const eveningAttendance = labourEntry.querySelector('input[name^="evening-attendance-"]:checked').value;
            
            // Get wages and overtime data
            const wagesPerDay = parseFloat(labourEntry.querySelector('.slw-wages-per-day').value) || 0;
            const totalDayWages = parseFloat(labourEntry.querySelector('.slw-total-day-wages').value) || 0;
            
            const otHours = parseFloat(labourEntry.querySelector('.slw-ot-hours').value) || 0;
            const otMinutes = parseFloat(labourEntry.querySelector('.slw-ot-minutes').value) || 0;
            const otRate = parseFloat(labourEntry.querySelector('.slw-ot-rate').value) || 0;
            const totalOTAmount = parseFloat(labourEntry.querySelector('.slw-total-ot-amount').value) || 0;
            
            // Get travel data
            const transportMode = labourEntry.querySelector('.slw-transport-mode').value;
            const travelAmount = parseFloat(labourEntry.querySelector('.slw-travel-amount').value) || 0;
            
            // Get grand total
            const grandTotal = parseFloat(labourEntry.querySelector('.slw-calculated-grand-total').textContent) || 0;
            
            // Only add if there's at least a name
            if (labourName) {
                labours.push({
                    name: labourName,
                    contactNumber: labourNumber,
                    attendance: {
                        morning: morningAttendance,
                        evening: eveningAttendance
                    },
                    wages: {
                        perDay: wagesPerDay,
                        totalDay: totalDayWages
                    },
                    overtime: {
                        hours: otHours,
                        minutes: otMinutes,
                        rate: otRate,
                        totalAmount: totalOTAmount
                    },
                    travel: {
                        mode: transportMode,
                        amount: travelAmount
                    },
                    grandTotal: grandTotal
                });
            }
        });
        
        // Only add if there's at least a type or name
        if (vendorType || vendorName) {
            vendors.push({
                type: vendorType,
                name: vendorName,
                contact: vendorContact,
                material: {
                    remarks: remarks,
                    amount: amount,
                    imageFile: materialImageFile,
                    billFile: billImageFile
                },
                labours: labours
            });
        }
    });
    
    // Get company labour data (if any)
    const companyItems = document.querySelectorAll('.supervisor-company-entry');
    const companies = [];
    
    companyItems.forEach((companyItem, companyIndex) => {
        const companyNumber = companyIndex + 1;
    
        // Get company labour entries
        const labourEntries = companyItem.querySelectorAll('.scl-labour-entry');
        const labours = [];
        
        labourEntries.forEach(labourEntry => {
            const labourName = labourEntry.querySelector('.scl-labour-name').value.trim();
            const labourNumber = labourEntry.querySelector('.scl-labour-number-input').value.trim();
            
            // Get attendance data
            const morningAttendance = labourEntry.querySelector('input[name^="scl-morning-attendance-"]:checked').value;
            const eveningAttendance = labourEntry.querySelector('input[name^="scl-evening-attendance-"]:checked').value;
            
            // Get wages and overtime data
            const wagesPerDay = parseFloat(labourEntry.querySelector('.scl-wages-per-day').value) || 0;
            const totalDayWages = parseFloat(labourEntry.querySelector('.scl-total-day-wages').value) || 0;
            
            const otHours = parseFloat(labourEntry.querySelector('.scl-ot-hours').value) || 0;
            const otMinutes = parseFloat(labourEntry.querySelector('.scl-ot-minutes').value) || 0;
            const otRate = parseFloat(labourEntry.querySelector('.scl-ot-rate').value) || 0;
            const totalOTAmount = parseFloat(labourEntry.querySelector('.scl-total-ot-amount').value) || 0;
    
            // Get travel data
            const transportMode = labourEntry.querySelector('.scl-transport-mode').value;
            const travelAmount = parseFloat(labourEntry.querySelector('.scl-travel-amount').value) || 0;
            
            // Get grand total
            const grandTotal = parseFloat(labourEntry.querySelector('.scl-calculated-grand-total').textContent) || 0;
            
            // Only add if there's at least a name
            if (labourName) {
                labours.push({
                    name: labourName,
                    contactNumber: labourNumber,
                    attendance: {
                        morning: morningAttendance,
                        evening: eveningAttendance
                    },
                    wages: {
                        perDay: wagesPerDay,
                        totalDay: totalDayWages
                    },
                    overtime: {
                        hours: otHours,
                        minutes: otMinutes,
                        rate: otRate,
                        totalAmount: totalOTAmount
                    },
                    travel: {
                        mode: transportMode,
                        amount: travelAmount
                    },
                    grandTotal: grandTotal
                });
            }
        });
        
        // Only add company if it has labour entries
        if (labours.length > 0) {
            companies.push({
                number: companyNumber,
                labours: labours
            });
        }
    });
    
    // Prepare event data object
    const eventData = {
        title: eventTitle,
        date: eventDate,
        vendors: vendors,
        companies: companies
    };
    
    // Use the saveCalendarEvent function from calendar-events-save.js
    if (typeof saveCalendarEvent === 'function') {
        // For file uploads, we need to create a FormData object
        const formData = new FormData();
        formData.append('event_title', eventTitle);
        formData.append('event_date', eventDate);
        
        // Add vendors with files
        if (vendors.length > 0) {
            formData.append('vendor_count', vendors.length);
            
            vendors.forEach((vendor, index) => {
                const vendorNum = index + 1;
                formData.append(`vendor_type_${vendorNum}`, vendor.type);
                formData.append(`vendor_name_${vendorNum}`, vendor.name);
                formData.append(`contact_number_${vendorNum}`, vendor.contact || '');
                
                // Add material data if available
                if (vendor.material && (vendor.material.remarks || vendor.material.amount || 
                                      vendor.material.imageFile || vendor.material.billFile)) {
                    formData.append(`material_count_${vendorNum}`, 1);
                    const materialKey = `material_${vendorNum}_1`;
                    
                    formData.append(`remarks_${materialKey}`, vendor.material.remarks || '');
                    formData.append(`amount_${materialKey}`, vendor.material.amount || '');
                    
                    // Add material image file if available
                    if (vendor.material.imageFile) {
                        formData.append(`material_images_${materialKey}`, vendor.material.imageFile);
                    }
                    
                    // Add bill image file if available
                    if (vendor.material.billFile) {
                        formData.append(`bill_image_${materialKey}`, vendor.material.billFile);
                    }
                }
                
                // Add labour data
                if (vendor.labours && vendor.labours.length > 0) {
                    formData.append(`labour_count_${vendorNum}`, vendor.labours.length);
                    
                    vendor.labours.forEach((labour, labourIndex) => {
                        const labourNum = labourIndex + 1;
                        const labourKey = `labour_${vendorNum}_${labourNum}`;
                        
                        // Add labour fields
                        formData.append(`labour_name_${labourKey}`, labour.name || '');
                        formData.append(`labour_number_${labourKey}`, labour.contactNumber || '');
                        formData.append(`morning_attendance_${labourKey}`, labour.attendance?.morning || 'present');
                        formData.append(`evening_attendance_${labourKey}`, labour.attendance?.evening || 'present');
                        
                        // Add other labour data (wages, overtime, travel)
                        if (labour.wages) {
                            formData.append(`daily_wage_${labourKey}`, labour.wages.perDay || '0');
                            formData.append(`total_day_wage_${labourKey}`, labour.wages.totalDay || '0');
                        }
                        
                        if (labour.overtime) {
                            formData.append(`ot_hours_${labourKey}`, labour.overtime.hours || '0');
                            formData.append(`ot_minutes_${labourKey}`, labour.overtime.minutes || '0');
                            formData.append(`ot_rate_${labourKey}`, labour.overtime.rate || '0');
                            formData.append(`total_ot_amount_${labourKey}`, labour.overtime.totalAmount || '0');
                        }
                        
                        if (labour.travel) {
                            formData.append(`transport_mode_${labourKey}`, labour.travel.mode || '');
                            formData.append(`travel_amount_${labourKey}`, labour.travel.amount || '0');
                        }
                        
                        formData.append(`grand_total_${labourKey}`, labour.grandTotal || '0');
                    });
                }
            });
        }
        
        // Add company labour data
        if (companies && companies.length > 0) {
            let totalCompanyLabours = 0;
            
            // Count total company labours
            companies.forEach(company => {
                if (company.labours) totalCompanyLabours += company.labours.length;
            });
            
            formData.append('company_labour_count', totalCompanyLabours);
            
            let companyLabourIndex = 1;
            
            // Flatten company labours into a single list for the backend
            companies.forEach(company => {
                if (company.labours && company.labours.length > 0) {
                    company.labours.forEach(labour => {
                        formData.append(`company_labour_name_${companyLabourIndex}`, labour.name || '');
                        formData.append(`company_labour_number_${companyLabourIndex}`, labour.contactNumber || '');
                        formData.append(`company_morning_attendance_${companyLabourIndex}`, labour.attendance?.morning || 'present');
                        formData.append(`company_evening_attendance_${companyLabourIndex}`, labour.attendance?.evening || 'present');
                        
                        // Add wage information if available
                        if (labour.wages) {
                            formData.append(`company_daily_wage_${companyLabourIndex}`, labour.wages.perDay || '0');
                            formData.append(`company_total_day_wage_${companyLabourIndex}`, labour.wages.totalDay || '0');
                        }
                        
                        // Add overtime information if available
                        if (labour.overtime) {
                            formData.append(`company_ot_hours_${companyLabourIndex}`, labour.overtime.hours || '0');
                            formData.append(`company_ot_minutes_${companyLabourIndex}`, labour.overtime.minutes || '0');
                            formData.append(`company_ot_rate_${companyLabourIndex}`, labour.overtime.rate || '0');
                            formData.append(`company_total_ot_amount_${companyLabourIndex}`, labour.overtime.totalAmount || '0');
                        }
                        
                        // Add travel information if available
                        if (labour.travel) {
                            formData.append(`company_transport_mode_${companyLabourIndex}`, labour.travel.mode || '');
                            formData.append(`company_travel_amount_${companyLabourIndex}`, labour.travel.amount || '0');
                        }
                        
                        // Add grand total
                        formData.append(`company_grand_total_${companyLabourIndex}`, labour.grandTotal || '0');
                        
                        companyLabourIndex++;
                    });
                }
            });
        }
        
        saveCalendarEvent(formData, 
            // Success callback
            function(data) {
                hideUploadLoader();
                hideAddEventModal(); // Changed from closeEventModal to hideAddEventModal
                showToast('Success', 'Event saved successfully!', 'success');
                if (typeof loadEvents === 'function') {
                    loadEvents();
                }
            },
            // Error callback
            function(error) {
                hideUploadLoader();
                showToast('Error', error.message || 'Failed to save event', 'error');
            }
        );
    } else {
        hideUploadLoader();
        console.error('saveCalendarEvent function not found!');
        showToast('Error', 'Calendar save functionality not available', 'error');
    }
}

/**
 * Add an event to the calendar display
 */
function addEventToCalendar(eventId, dateStr, title, type, time) {
    // Parse the date
    const [year, month, day] = dateStr.split('-').map(num => parseInt(num, 10));
    
    // Find the calendar day cell
    const calendarDay = document.querySelector(`.supervisor-calendar-day[data-day="${day}"][data-month="${month}"][data-year="${year}"]`);
    
    if (calendarDay) {
        // Get or create the events container
        let eventsContainer = calendarDay.querySelector('.supervisor-calendar-events');
        if (!eventsContainer) {
            eventsContainer = document.createElement('div');
            eventsContainer.className = 'supervisor-calendar-events';
            calendarDay.appendChild(eventsContainer);
        }
        
        // Create the event element
        const eventElement = document.createElement('div');
        eventElement.className = `supervisor-calendar-event event-${type}`;
        eventElement.setAttribute('data-event-id', eventId);
        eventElement.setAttribute('title', `${time}: ${title}`);
        eventElement.textContent = title;
        
        // Add to the events container
        eventsContainer.appendChild(eventElement);
        
        // Add the has-events class to the day
        calendarDay.classList.add('has-events');
        
        // Check if we need to add a "more" indicator
        const visibleEvents = eventsContainer.querySelectorAll('.supervisor-calendar-event:not(.supervisor-event-more)');
        if (visibleEvents.length > 2) {
            // Check if we already have a "more" indicator
            let moreIndicator = eventsContainer.querySelector('.supervisor-event-more');
            if (!moreIndicator) {
                moreIndicator = document.createElement('div');
                moreIndicator.className = 'supervisor-event-more';
                eventsContainer.appendChild(moreIndicator);
            }
            
            // Update the count
            moreIndicator.textContent = `+${visibleEvents.length - 2} more`;
        }
    }
}

/**
 * Show the View Event Modal with event details
 */
function showViewEventModal(eventId, eventType, eventTitle) {
    // In a real app, we would fetch the event details from the server
    // For now, we'll use mock data
    const eventDetails = getMockEventDetails(eventId, eventType, eventTitle);
    
    // Update modal header class based on event type
    const header = document.getElementById('view-event-header');
    header.className = 'view-event-modal-header';
    header.classList.add(`event-${eventType}`);
    
    // Set the event ID on the modal for reference (for edit/delete)
    document.querySelector('.view-event-modal').setAttribute('data-event-id', eventId);
    
    // Update modal content
    document.getElementById('view-event-title').textContent = eventDetails.title;
    document.getElementById('view-event-datetime').textContent = eventDetails.datetime;
    document.getElementById('view-event-duration').textContent = eventDetails.duration;
    document.getElementById('view-event-type').textContent = eventDetails.type;
    document.getElementById('view-event-location').textContent = eventDetails.location || 'Not specified';
    document.getElementById('view-event-description').textContent = eventDetails.description || 'No description provided';
    document.getElementById('view-event-participants').textContent = eventDetails.participants || 'None';
    
    // Show the modal
    document.getElementById('view-event-modal-container').classList.add('active');
}

/**
 * Hide the View Event Modal
 */
function hideViewEventModal() {
    document.getElementById('view-event-modal-container').classList.remove('active');
}

/**
 * Edit an existing event
 */
function editEvent(eventId) {
    // In a real app, we would fetch the event details from the server
    // and populate the edit form
    
    // For now, just hide the view modal and show a toast
    hideViewEventModal();
    showToast('Edit Event', `Editing event ${eventId}`, 'warning');
}

/**
 * Delete an event
 */
function deleteEvent(eventId) {
    // Confirm deletion
    if (confirm('Are you sure you want to delete this event?')) {
        // In a real app, we would send a delete request to the server
        
        // For demo purposes, remove the event from the calendar
        const eventElement = document.querySelector(`.supervisor-calendar-event[data-event-id="${eventId}"]`);
        if (eventElement) {
            const eventsContainer = eventElement.parentElement;
            const calendarDay = eventsContainer.parentElement;
            
            // Remove the event element
            eventElement.remove();
            
            // Update or remove the "more" indicator
            const remainingEvents = eventsContainer.querySelectorAll('.supervisor-calendar-event:not(.supervisor-event-more)');
            const moreIndicator = eventsContainer.querySelector('.supervisor-event-more');
            
            if (remainingEvents.length <= 2 && moreIndicator) {
                moreIndicator.remove();
            } else if (moreIndicator) {
                moreIndicator.textContent = `+${remainingEvents.length - 2} more`;
            }
            
            // If no events left, remove the has-events class
            if (remainingEvents.length === 0) {
                calendarDay.classList.remove('has-events');
            }
        }
        
        // Hide the modal
        hideViewEventModal();
        
        // Show success message
        showToast('Success', 'Event deleted successfully');
    }
}

/**
 * Get mock event details for demonstration
 */
function getMockEventDetails(eventId, eventType, eventTitle) {
    // Map event types to readable names
    const typeNames = {
        'inspection': 'Inspection',
        'delivery': 'Delivery',
        'meeting': 'Meeting',
        'report': 'Report',
        'issue': 'Issue'
    };
    
    // Generate a random future date (within the next 30 days)
    const today = new Date();
    const futureDate = new Date(today);
    futureDate.setDate(today.getDate() + Math.floor(Math.random() * 30));
    
    // Format the date
    const formattedDate = `${futureDate.toLocaleString('en-US', { month: 'long' })} ${futureDate.getDate()}, ${futureDate.getFullYear()}`;
    
    // Generate a random time
    const hour = 8 + Math.floor(Math.random() * 10); // 8 AM to 6 PM
    const minute = Math.floor(Math.random() * 4) * 15; // 0, 15, 30, 45
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : hour;
    const formattedTime = `${displayHour}:${minute === 0 ? '00' : minute} ${period}`;
    
    // Generate a random duration
    const durations = ['30 minutes', '45 minutes', '1 hour', '1.5 hours', '2 hours'];
    const randomDuration = durations[Math.floor(Math.random() * durations.length)];
    
    // Sample location based on event type
    const locations = {
        'inspection': ['Building A', 'Site 2', 'North Wing', 'Foundation Area'],
        'delivery': ['Loading Dock', 'Storage Area', 'Building B Entrance', 'Construction Zone'],
        'meeting': ['Main Office', 'Conference Room', 'Site Trailer', 'Virtual (Zoom)'],
        'report': ['Main Office', 'Site Office', 'Administration Building'],
        'issue': ['Electrical Room', 'Plumbing Line', 'South Wing', 'Building C Entrance']
    };
    
    const randomLocation = locations[eventType][Math.floor(Math.random() * locations[eventType].length)];
    
    // Sample descriptions based on event type
    const descriptions = {
        'inspection': ['Safety inspection for compliance', 'Quality check of recent construction', 'Equipment certification review', 'Final approval inspection'],
        'delivery': ['Material delivery for phase 2', 'Equipment arrival for installation', 'Supply restocking', 'New tools delivery'],
        'meeting': ['Weekly progress review', 'Client project update', 'Team coordination meeting', 'Planning session for next phase'],
        'report': ['Monthly progress submission', 'Safety incident documentation', 'Budget review report', 'Completion certificate'],
        'issue': ['Water leak investigation', 'Electrical circuit problem', 'Structural concern assessment', 'Material quality issue']
    };
    
    const randomDescription = descriptions[eventType][Math.floor(Math.random() * descriptions[eventType].length)];
    
    // Sample participant names
    const participants = [
        'John Smith, Mary Johnson',
        'Raj Patel, Sarah Wilson',
        'Carlos Rodriguez, Emma Davis',
        'Team Leaders',
        'Project Manager, Site Engineers',
        'Client Representatives',
        'Inspection Team'
    ];
    
    const randomParticipants = participants[Math.floor(Math.random() * participants.length)];
    
    return {
        id: eventId,
        title: eventTitle || `${typeNames[eventType]} - ${randomDescription.split(' ').slice(0, 3).join(' ')}`,
        datetime: `${formattedDate} - ${formattedTime}`,
        duration: randomDuration,
        type: typeNames[eventType],
        location: randomLocation,
        description: randomDescription,
        participants: randomParticipants
    };
}

// Function to show a toast notification
function showToast(title, message, type = 'success') {
    const toastContainer = document.getElementById('calendar-toast-container');
    
    const toast = document.createElement('div');
    toast.className = `calendar-toast calendar-toast-${type}`;
    
    let icon = '';
    switch (type) {
        case 'success':
            icon = '<i class="fas fa-check-circle calendar-toast-icon"></i>';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle calendar-toast-icon"></i>';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle calendar-toast-icon"></i>';
            break;
    }
    
    toast.innerHTML = `
        ${icon}
        <div class="calendar-toast-content">
            <div class="calendar-toast-title">${title}</div>
            <div class="calendar-toast-message">${message}</div>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Show the toast with a small delay for animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Add a new labour entry to a vendor
 */
function addNewLabour(vendorItem) {
    const labourContainer = vendorItem.querySelector('.supervisor-labour-container');
    
    // Create temporary container to hold the template HTML
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = window.labourTemplate;
    
    // Get the labour entry element from the container
    const labourEntry = tempContainer.firstElementChild;
    
    // Update the labour number based on existing entries
    const existingLabours = labourContainer.querySelectorAll('.supervisor-labour-entry');
    const labourNumber = existingLabours.length + 1;
    labourEntry.querySelector('.supervisor-labour-number').textContent = labourNumber;
    
    // Generate unique IDs for radio buttons to ensure they work correctly
    const timestamp = Date.now() + '_' + Math.floor(Math.random() * 1000);
    const morningRadios = labourEntry.querySelectorAll('[name^="morning-attendance-"]');
    const eveningRadios = labourEntry.querySelectorAll('[name^="evening-attendance-"]');
    
    morningRadios.forEach(radio => {
        radio.name = `morning-attendance-${timestamp}`;
    });
    
    eveningRadios.forEach(radio => {
        radio.name = `evening-attendance-${timestamp}`;
    });
    
    // Add to the labour container
    labourContainer.appendChild(labourEntry);
    
    // Focus the labour name input
    const nameInput = labourEntry.querySelector('.supervisor-labour-name');
    nameInput.focus();

    // Add event listeners for calculation after the element is added to the DOM
    setTimeout(() => {
        // Trigger initial calculation of total wages if wage per day is already set
        const wagesPerDayInput = labourEntry.querySelector('.slw-wages-per-day');
        if (wagesPerDayInput.value) {
            calculateTotalDayWages(wagesPerDayInput);
        }
        
        // Trigger initial calculation of overtime if values are already set
        const otHoursInput = labourEntry.querySelector('.slw-ot-hours');
        if (otHoursInput.value) {
            calculateOvertimeAmount(otHoursInput);
        }
        
        // Update grand total
        updateGrandTotal(wagesPerDayInput);
    }, 0);
}

/**
 * Update the labour entry numbers after removing a labour
 */
function updateLabourNumbers(vendorItem) {
    const labourContainer = vendorItem.querySelector('.supervisor-labour-container');
    const labourEntries = labourContainer.querySelectorAll('.supervisor-labour-entry');
    
    labourEntries.forEach((entry, index) => {
        // Update the number in the span
        entry.querySelector('.supervisor-labour-number').textContent = index + 1;
    });
}

/**
 * Calculate the total day wages based on daily wage amount
 */
function calculateTotalDayWages(wagesInput) {
    const labourEntry = wagesInput.closest('.supervisor-labour-entry');
    const totalDayWages = labourEntry.querySelector('.slw-total-day-wages');
    
    // Get the daily wage amount
    const wagesPerDay = parseFloat(wagesInput.value) || 0;
    
    // For now, we'll assume a full day of work
    // In a real app, this might be adjusted based on attendance
    const morningPresent = labourEntry.querySelector('input[name^="morning-attendance-"]:checked').value === 'present';
    const eveningPresent = labourEntry.querySelector('input[name^="evening-attendance-"]:checked').value === 'present';
    
    // Calculate based on attendance
    let dayMultiplier = 0;
    if (morningPresent && eveningPresent) {
        dayMultiplier = 1.0; // Full day
    } else if (morningPresent || eveningPresent) {
        dayMultiplier = 0.5; // Half day
    }
    
    // Calculate total day wages
    const totalAmount = wagesPerDay * dayMultiplier;
    
    // Update the total day wages field
    totalDayWages.value = totalAmount.toFixed(2);
    
    // Update radio button listeners for attendance changes
    const attendanceRadios = labourEntry.querySelectorAll('input[type="radio"][name^="morning-attendance-"], input[type="radio"][name^="evening-attendance-"]');
    attendanceRadios.forEach(radio => {
        if (!radio.hasAttendanceListener) {
            radio.hasAttendanceListener = true;
            radio.addEventListener('change', function() {
                calculateTotalDayWages(wagesInput);
            });
        }
    });
}

/**
 * Calculate the overtime amount based on hours, minutes, and rate
 */
function calculateOvertimeAmount(input) {
    const labourEntry = input.closest('.supervisor-labour-entry');
    const hoursInput = labourEntry.querySelector('.slw-ot-hours');
    const minutesInput = labourEntry.querySelector('.slw-ot-minutes');
    const rateInput = labourEntry.querySelector('.slw-ot-rate');
    const totalOTAmount = labourEntry.querySelector('.slw-total-ot-amount');
    
    // Get values from inputs
    const hours = parseFloat(hoursInput.value) || 0;
    const minutes = parseFloat(minutesInput.value) || 0;
    const rate = parseFloat(rateInput.value) || 0;
    
    // Calculate total hours including minutes
    const totalHours = hours + (minutes / 60);
    
    // Calculate total overtime amount
    const otAmount = totalHours * rate;
    
    // Update the total OT amount field
    totalOTAmount.value = otAmount.toFixed(2);
}

/**
 * Update the grand total amount
 */
function updateGrandTotal(input) {
    const labourEntry = input.closest('.supervisor-labour-entry');
    const totalDayWages = parseFloat(labourEntry.querySelector('.slw-total-day-wages').value) || 0;
    const totalOTAmount = parseFloat(labourEntry.querySelector('.slw-total-ot-amount').value) || 0;
    const travelAmount = parseFloat(labourEntry.querySelector('.slw-travel-amount').value) || 0;
    
    // Calculate grand total
    const grandTotal = totalDayWages + totalOTAmount + travelAmount;
    
    // Update the grand total display
    labourEntry.querySelector('.slw-calculated-grand-total').textContent = grandTotal.toFixed(2);
}

/**
 * Add a new company item to the company container
 */
function addNewCompanyItem() {
    const companyContainer = document.getElementById('calendar-company-container');
    
    // Create temporary container to hold the template HTML
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = window.companyLabourTemplate;
    
    // Get the company item element from the container
    const companyItem = tempContainer.firstElementChild;
    
    // Update the company number based on existing companies
    const existingCompanies = companyContainer.querySelectorAll('.supervisor-company-entry');
    const companyNumber = existingCompanies.length + 1;
    companyItem.querySelector('.calendar-company-number').textContent = companyNumber;
    
    // Add unique ID to this company item
    companyItem.id = `supervisor-company-${companyNumber}`;
    
    // Add to the company container
    companyContainer.appendChild(companyItem);
}

/**
 * Update the company numbers after removing a company
 */
function updateCompanyNumbers() {
    const companyContainer = document.getElementById('calendar-company-container');
    const companyItems = companyContainer.querySelectorAll('.supervisor-company-entry');
    
    companyItems.forEach((item, index) => {
        // Update the number in the span
        item.querySelector('.calendar-company-number').textContent = index + 1;
        
        // Update the ID
        item.id = `supervisor-company-${index + 1}`;
    });
}

/**
 * Add a new company labour entry
 */
function addNewCompanyLabour(companyItem) {
    const labourContainer = companyItem.querySelector('.scl-labour-container');
    
    // Create temporary container to hold the template HTML
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = window.companyLabourEntryTemplate;
    
    // Get the labour entry element from the container
    const labourEntry = tempContainer.firstElementChild;
    
    // Update the labour number based on existing entries
    const existingLabours = labourContainer.querySelectorAll('.scl-labour-entry');
    const labourNumber = existingLabours.length + 1;
    labourEntry.querySelector('.scl-labour-number').textContent = labourNumber;
    
    // Generate unique IDs for radio buttons to ensure they work correctly
    const timestamp = Date.now() + '_' + Math.floor(Math.random() * 1000);
    const morningRadios = labourEntry.querySelectorAll('[name^="scl-morning-attendance-"]');
    const eveningRadios = labourEntry.querySelectorAll('[name^="scl-evening-attendance-"]');
    
    morningRadios.forEach(radio => {
        radio.name = `scl-morning-attendance-${timestamp}`;
    });
    
    eveningRadios.forEach(radio => {
        radio.name = `scl-evening-attendance-${timestamp}`;
    });
    
    // Add to the labour container
    labourContainer.appendChild(labourEntry);
    
    // Focus the labour name input
    const nameInput = labourEntry.querySelector('.scl-labour-name');
    nameInput.focus();
    
    // Add event listeners for calculation after the element is added to the DOM
    setTimeout(() => {
        // Trigger initial calculation of total wages if wage per day is already set
        const wagesPerDayInput = labourEntry.querySelector('.scl-wages-per-day');
        if (wagesPerDayInput.value) {
            calculateCompanyTotalDayWages(wagesPerDayInput);
        }
        
        // Trigger initial calculation of overtime if values are already set
        const otHoursInput = labourEntry.querySelector('.scl-ot-hours');
        if (otHoursInput.value) {
            calculateCompanyOvertimeAmount(otHoursInput);
        }
        
        // Update grand total
        updateCompanyGrandTotal(wagesPerDayInput);
    }, 0);
}

/**
 * Update the company labour entry numbers after removing a labour
 */
function updateCompanyLabourNumbers(companyItem) {
    const labourContainer = companyItem.querySelector('.scl-labour-container');
    const labourEntries = labourContainer.querySelectorAll('.scl-labour-entry');
    
    labourEntries.forEach((entry, index) => {
        // Update the number in the span
        entry.querySelector('.scl-labour-number').textContent = index + 1;
    });
}

/**
 * Calculate the total day wages for company labour
 */
function calculateCompanyTotalDayWages(wagesInput) {
    const labourEntry = wagesInput.closest('.scl-labour-entry');
    const totalDayWages = labourEntry.querySelector('.scl-total-day-wages');
    
    // Get the daily wage amount
    const wagesPerDay = parseFloat(wagesInput.value) || 0;
    
    // For now, we'll assume a full day of work
    // In a real app, this might be adjusted based on attendance
    const morningPresent = labourEntry.querySelector('input[name^="scl-morning-attendance-"]:checked').value === 'present';
    const eveningPresent = labourEntry.querySelector('input[name^="scl-evening-attendance-"]:checked').value === 'present';
    
    // Calculate based on attendance
    let dayMultiplier = 0;
    if (morningPresent && eveningPresent) {
        dayMultiplier = 1.0; // Full day
    } else if (morningPresent || eveningPresent) {
        dayMultiplier = 0.5; // Half day
    }
    
    // Calculate total day wages
    const totalAmount = wagesPerDay * dayMultiplier;
    
    // Update the total day wages field
    totalDayWages.value = totalAmount.toFixed(2);
    
    // Update radio button listeners for attendance changes
    const attendanceRadios = labourEntry.querySelectorAll('input[type="radio"][name^="scl-morning-attendance-"], input[type="radio"][name^="scl-evening-attendance-"]');
    attendanceRadios.forEach(radio => {
        if (!radio.hasAttendanceListener) {
            radio.hasAttendanceListener = true;
            radio.addEventListener('change', function() {
                calculateCompanyTotalDayWages(wagesInput);
            });
        }
    });
}

/**
 * Calculate the overtime amount for company labour
 */
function calculateCompanyOvertimeAmount(input) {
    const labourEntry = input.closest('.scl-labour-entry');
    const hoursInput = labourEntry.querySelector('.scl-ot-hours');
    const minutesInput = labourEntry.querySelector('.scl-ot-minutes');
    const rateInput = labourEntry.querySelector('.scl-ot-rate');
    const totalOTAmount = labourEntry.querySelector('.scl-total-ot-amount');
    
    // Get values from inputs
    const hours = parseFloat(hoursInput.value) || 0;
    const minutes = parseFloat(minutesInput.value) || 0;
    const rate = parseFloat(rateInput.value) || 0;
    
    // Calculate total hours including minutes
    const totalHours = hours + (minutes / 60);
    
    // Calculate total overtime amount
    const otAmount = totalHours * rate;
    
    // Update the total OT amount field
    totalOTAmount.value = otAmount.toFixed(2);
}

/**
 * Update the grand total amount for company labour
 */
function updateCompanyGrandTotal(input) {
    const labourEntry = input.closest('.scl-labour-entry');
    const totalDayWages = parseFloat(labourEntry.querySelector('.scl-total-day-wages').value) || 0;
    const totalOTAmount = parseFloat(labourEntry.querySelector('.scl-total-ot-amount').value) || 0;
    const travelAmount = parseFloat(labourEntry.querySelector('.scl-travel-amount').value) || 0;
    
    // Calculate grand total
    const grandTotal = totalDayWages + totalOTAmount + travelAmount;
    
    // Update the grand total display
    labourEntry.querySelector('.scl-calculated-grand-total').textContent = grandTotal.toFixed(2);
} 