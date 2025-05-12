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
                                <select id="event-title" name="event-title" class="add-event-form-control" required>
                                    <option value="">Select Construction Site</option>
                                    <option value="Construction Site At Sector 80">Construction Site At Sector 80</option>
                                    <option value="Construction Site At Dilshad Garden">Construction Site At Dilshad Garden</option>
                                    <option value="Construction Site At Jasola">Construction Site At Jasola</option>
                                    <option value="Construction Site At Faridabad Sector 91">Construction Site At Faridabad Sector 91</option>
                                    <option value="custom">Custom Title</option>
                                </select>
                                <input type="text" id="custom-event-title" name="custom-event-title" class="add-event-form-control" 
                                       placeholder="Enter custom event title" style="display: none; margin-top: 10px;">
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
                            
                           
                            
                            <!-- Beverages Section -->
                            <div class="calendar-event-beverages-section">
                                <div class="calendar-event-section-header">
                                    <h4><i class="fas fa-coffee"></i> Beverages Section</h4>
                                    <p class="section-description">Add beverages provided at the event</p>
                                </div>
                                <div id="calendar-beverages-container" class="calendar-beverages-container">
                                    <!-- Beverage items will be added dynamically here -->
                                </div>
                                <button type="button" id="add-beverage-btn" class="calendar-add-beverage-btn">
                                    <i class="fas fa-plus-circle"></i> Add Beverage
                                </button>
                            </div>

                            <!-- Work Progress Section HTML -->
                            <div class="ce-work-progress-section">
                                <div class="calendar-event-section-header">
                                    <h4><i class="fas fa-tasks"></i> Work Progress Section</h4>
                                    <p class="section-description">Add details about work progress at the site</p>
                                </div>
                                <div id="ce-work-progress-container" class="ce-work-progress-container">
                                    <!-- Work entries will be added dynamically here -->
                                </div>
                                <button type="button" id="ce-add-work-btn" class="ce-add-work-btn">
                                    <i class="fas fa-plus-circle"></i> Add Work Progress
                                </button>
                            </div>

                            <!-- Inventory Section HTML -->
                            <div class="cei-inventory-section">
                                <div class="calendar-event-section-header">
                                    <h4><i class="fas fa-boxes"></i> Inventory Section</h4>
                                    <p class="section-description">Add details about inventory items received or consumed</p>
                                </div>
                                <div id="ce-inventory-container" class="cei-inventory-container">
                                    <!-- Inventory entries will be added dynamically here -->
                                </div>
                                <button type="button" id="ce-add-inventory-btn" class="cei-add-inventory-btn">
                                    <i class="fas fa-plus-circle"></i> Add Inventory Item
                                </button>
                            </div>
                             <!-- Wages Summary Section -->
                            <div class="sv-wages-summary-section">
                                <div class="sv-wages-summary-inner">
                                    <div class="sv-wages-summary-decoration"></div>
                                    <div class="sv-wages-summary-header">
                                        <div class="sv-wages-summary-header-icon">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div>
                                            <h4 class="sv-wages-summary-title">Wages Summary</h4>
                                            <p class="sv-wages-summary-subtitle">Summary of all labour payments</p>
                                        </div>
                                    </div>
                                    <div id="calendar-wages-summary-container" class="sv-wages-summary-content">
                                        <div class="sv-wages-row">
                                            <div class="sv-wages-label">
                                                <i class="fas fa-users"></i> Vendor Labour Wages
                                            </div>
                                            <div class="sv-wages-value">₹ <span id="vendor-labour-wages">0.00</span></div>
                                        </div>
                                        <div class="sv-wages-row">
                                            <div class="sv-wages-label">
                                                <i class="fas fa-building"></i> Company Labour Wages
                                            </div>
                                            <div class="sv-wages-value">₹ <span id="company-labour-wages">0.00</span></div>
                                        </div>
                                        <div class="sv-wages-row">
                                            <div class="sv-wages-label">
                                                <i class="fas fa-business-time"></i> Overtime Payments
                                            </div>
                                            <div class="sv-wages-value">₹ <span id="overtime-payments">0.00</span></div>
                                        </div>
                                        <div class="sv-wages-row">
                                            <div class="sv-wages-label">
                                                <i class="fas fa-route"></i> Travel Expenses
                                            </div>
                                            <div class="sv-wages-value">₹ <span id="travel-expenses">0.00</span></div>
                                        </div>
                                        
                                        <div class="sv-wages-total-row">
                                            <div class="sv-wages-total-label">
                                                <i class="fas fa-calculator"></i> Total Wages
                                            </div>
                                            <div class="sv-wages-total-value">₹ <span id="total-wages">0.00</span></div>
                                        </div>
                                    </div>
                                </div>
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

    // Beverage template to use for creating new beverage entries
    window.beverageTemplate = `
        <div class="calendar-beverage-item">
            <div class="calendar-beverage-header">
                <h5><span class="calendar-beverage-number">1</span> Beverage Information</h5>
                <button type="button" class="calendar-beverage-remove-btn" aria-label="Remove beverage">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="calendar-beverage-form-row">
                <div class="calendar-beverage-form-group">
                    <label><i class="fas fa-wine-glass-alt"></i> Beverage Type</label>
                    <input type="text" class="calendar-beverage-type" placeholder="Enter beverage type">
                </div>
                <div class="calendar-beverage-form-group">
                    <label><i class="fas fa-tag"></i> Beverage Name</label>
                    <input type="text" class="calendar-beverage-name" placeholder="Enter beverage name">
                </div>
                <div class="calendar-beverage-form-group">
                    <label><i class="fas fa-rupee-sign"></i> Amount</label>
                    <input type="number" class="calendar-beverage-amount" placeholder="Enter amount" min="0" step="0.01">
                </div>
            </div>
        </div>
    `;

    // Add work progress template to window for reuse
    window.workProgressTemplate = `
        <div class="ce-work-entry">
            <div class="ce-work-header">
                <h5><span class="ce-work-number">1</span> Work Progress Information</h5>
                <button type="button" class="ce-work-remove-btn" aria-label="Remove work entry">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="ce-work-form-row">
                <div class="ce-work-form-group">
                    <label><i class="fas fa-th-list"></i> Work Category</label>
                    <select class="ce-work-category-select">
                        <option value="">Select Work Category</option>
                        <option value="structural">Structural Work</option>
                        <option value="electrical">Electrical Work</option>
                        <option value="plumbing">Plumbing Work</option>
                        <option value="interior">Interior Work</option>
                        <option value="exterior">Exterior Work</option>
                        <option value="landscaping">Landscaping</option>
                        <option value="custom">Custom Category</option>
                    </select>
                    <input type="text" class="ce-work-custom-category" placeholder="Enter custom work category" style="display: none; margin-top: 8px;">
                </div>
                <div class="ce-work-form-group">
                    <label><i class="fas fa-tools"></i> Type of Work</label>
                    <select class="ce-work-type-select" disabled>
                        <option value="">Select Type of Work</option>
                        <!-- Options will be populated dynamically -->
                    </select>
                    <input type="text" class="ce-work-custom-type" placeholder="Enter custom work type" style="display: none; margin-top: 8px;">
                </div>
            </div>
            <div class="ce-work-form-row">
                <div class="ce-work-form-group">
                    <label><i class="fas fa-check-circle"></i> Work Done</label>
                    <div class="ce-work-done-options">
                        <label class="ce-work-done-option">
                            <input type="radio" name="work-done-TIMESTAMP" value="yes" checked>
                            <span>Yes</span>
                        </label>
                        <label class="ce-work-done-option">
                            <input type="radio" name="work-done-TIMESTAMP" value="no">
                            <span>No</span>
                        </label>
                    </div>
                </div>
                <div class="ce-work-form-group">
                    <label><i class="fas fa-comment-alt"></i> Remarks</label>
                    <textarea class="ce-work-remarks" placeholder="Enter remarks about the work progress"></textarea>
                </div>
            </div>
            <div class="ce-work-form-row">
                <div class="ce-work-form-group">
                    <label><i class="fas fa-images"></i> Photos & Videos</label>
                    <div class="ce-work-media-container">
                        <!-- Media previews will be added dynamically here -->
                    </div>
                    <label class="ce-work-upload-btn">
                        <i class="fas fa-cloud-upload-alt"></i> Add Photos/Videos (One by One)
                        <input type="file" class="ce-work-upload-input" accept="image/*,video/*">
                    </label>
                    <div class="ce-work-upload-hint">Click the button above to add multiple media files one at a time</div>
                </div>
            </div>
        </div>
    `;

    // Inventory template for adding new inventory entries
    window.inventoryTemplate = `
        <div class="cei-inventory-entry">
            <div class="cei-inventory-header">
                <h5><span class="cei-inventory-number">1</span> Inventory Information</h5>
                <button type="button" class="cei-inventory-remove-btn" aria-label="Remove inventory entry">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="cei-inventory-form-row">
                <div class="cei-inventory-form-group">
                    <label><i class="fas fa-boxes"></i> Inventory Type</label>
                    <select class="cei-inventory-type-select">
                        <option value="received">Received Item</option>
                        <option value="consumed">Consumed Item</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="cei-inventory-form-group">
                    <label><i class="fas fa-tools"></i> Material</label>
                    <select class="cei-inventory-material-select">
                        <option value="">Select Material</option>
                        <option value="cement">Cement</option>
                        <option value="sand">Sand</option>
                        <option value="bricks">Bricks</option>
                        <option value="steel">Steel</option>
                        <option value="gravel">Gravel</option>
                        <option value="concrete">Concrete Mix</option>
                        <option value="stone">Stone Aggregates</option>
                        <option value="timber">Timber</option>
                        <option value="plywood">Plywood</option>
                        <option value="glass">Glass</option>
                        <option value="paint">Paint</option>
                        <option value="pipes">Pipes</option>
                        <option value="wires">Electrical Wires</option>
                        <option value="tiles">Tiles</option>
                        <option value="fixtures">Fixtures</option>
                        <option value="custom">Custom Material</option>
                    </select>
                    <input type="text" class="cei-inventory-custom-material" placeholder="Enter custom material" style="display: none; margin-top: 8px;">
                </div>
            </div>
            <div class="cei-inventory-form-row">
                <div class="cei-inventory-form-group">
                    <label><i class="fas fa-weight"></i> Quantity</label>
                    <input type="number" class="cei-inventory-quantity" placeholder="Enter quantity" min="0" step="0.01">
                </div>
                <div class="cei-inventory-form-group">
                    <label><i class="fas fa-ruler"></i> Unit</label>
                    <select class="cei-inventory-unit">
                        <option value="">Select Unit</option>
                        <option value="kg">Kilograms (kg)</option>
                        <option value="tons">Tons</option>
                        <option value="pieces">Pieces</option>
                        <option value="bags">Bags</option>
                        <option value="boxes">Boxes</option>
                        <option value="meters">Meters</option>
                        <option value="sq_meters">Square Meters</option>
                        <option value="cu_meters">Cubic Meters</option>
                        <option value="liters">Liters</option>
                        <option value="bundles">Bundles</option>
                        <option value="rolls">Rolls</option>
                        <option value="trucks">Trucks</option>
                        <option value="loads">Loads</option>
                    </select>
                </div>
            </div>
            <div class="ceirm-remaining-material-section">
                <div class="ceirm-section-header">
                    <i class="fas fa-boxes"></i> Remaining Material On Site
                </div>
                <div class="ceirm-content-area">
                    <div class="ceirm-loading">Loading material information...</div>
                </div>
            </div>
            <div class="cei-inventory-form-row">
                <div class="cei-inventory-form-group full-width">
                    <label><i class="fas fa-comment-alt"></i> Remarks</label>
                    <textarea class="cei-inventory-remarks" placeholder="Enter remarks about the inventory item"></textarea>
                </div>
            </div>
            <div class="cei-inventory-form-row">
                <div class="cei-inventory-form-group">
                    <label><i class="fas fa-file-invoice"></i> Bill Image</label>
                    <label class="cei-inventory-bill-upload">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Bill Image
                        <input type="file" class="cei-inventory-bill-input" accept="image/*,application/pdf">
                    </label>
                    <div class="cei-inventory-bill-preview"></div>
                </div>
            </div>
            <div class="cei-inventory-form-row">
                <div class="cei-inventory-form-group full-width">
                    <label><i class="fas fa-images"></i> Photos & Videos</label>
                    <div class="cei-inventory-media-container">
                        <!-- Media previews will be added dynamically here -->
                    </div>
                    <label class="cei-inventory-upload-btn">
                        <i class="fas fa-cloud-upload-alt"></i> Add Photos/Videos (One by One)
                        <input type="file" class="cei-inventory-upload-input" accept="image/*,video/*">
                    </label>
                    <div class="cei-inventory-upload-hint">Click the button above to add multiple media files one at a time</div>
                    <div class="cei-inventory-media-counter" style="display: none;">0 files added</div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Initialize all event handlers for modals and buttons
 */
function initializeEventHandlers() {
    // Add Event Modal handlers
    const addEventModalContainer = document.getElementById('add-event-modal-container');
    const addEventModal = addEventModalContainer.querySelector('.add-event-modal');
    const closeBtn = addEventModal.querySelector('.add-event-modal-close');
    const cancelBtn = addEventModal.querySelector('.add-event-btn-cancel');
    const submitBtn = addEventModal.querySelector('.event-submit-btn');
    
    // Modal close buttons
    closeBtn.addEventListener('click', hideAddEventModal);
    cancelBtn.addEventListener('click', hideAddEventModal);
    
    // Event title dropdown change handler
    const eventTitleSelect = document.getElementById('event-title');
    const customEventTitleInput = document.getElementById('custom-event-title');
    
    eventTitleSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customEventTitleInput.style.display = 'block';
            customEventTitleInput.required = true;
            customEventTitleInput.focus();
        } else {
            customEventTitleInput.style.display = 'none';
            customEventTitleInput.required = false;
        }
        
        // Update all existing inventory material sections
        updateAllRemainingMaterialSections();
    });
    
    // Submit button
    submitBtn.addEventListener('click', saveEvent);
    
    // Prevent form submission on enter
    addEventModal.querySelector('form').addEventListener('submit', function(event) {
        event.preventDefault();
        saveEvent();
    });
    
    // Add vendor button
    const addVendorBtn = document.getElementById('calendar-add-vendor-btn');
    addVendorBtn.addEventListener('click', function(event) {
        event.preventDefault();
        // Prevent event bubbling to avoid duplicate calls
        event.stopPropagation();
        addNewVendor();
    });
    
    // Add company button
    const addCompanyBtn = document.getElementById('calendar-add-company-btn');
    addCompanyBtn.addEventListener('click', function(event) {
        event.preventDefault();
        // Prevent event bubbling to avoid duplicate calls
        event.stopPropagation();
        addNewCompanyItem();
    });
    
    // Add beverage button
    document.getElementById('add-beverage-btn').addEventListener('click', function(event) {
        event.preventDefault();
        // Prevent event bubbling to avoid duplicate calls
        event.stopPropagation();
        addNewBeverage();
    });
    
    // Add work progress button
    document.getElementById('ce-add-work-btn').addEventListener('click', function(event) {
        event.preventDefault();
        // Prevent event bubbling to avoid duplicate calls
        event.stopPropagation();
        addWorkProgressEntry();
    });
    
    // Add inventory button
    document.getElementById('ce-add-inventory-btn').addEventListener('click', function(event) {
        event.preventDefault();
        // Prevent event bubbling to avoid duplicate calls
        event.stopPropagation();
        addInventoryEntry();
    });
    
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
        
        // Handle add company button - REMOVED to prevent duplicate event handling
        // Already handled by direct event listener on the button
        
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
            
            // Update wages summary after any wage-related changes
            updateWagesSummary();
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
            
            // Update wages summary after any wage-related changes
            updateWagesSummary();
        }
    });

    // Remove beverage button
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('calendar-beverage-remove-btn') || 
            event.target.closest('.calendar-beverage-remove-btn')) {
            const button = event.target.classList.contains('calendar-beverage-remove-btn') ? 
                           event.target : event.target.closest('.calendar-beverage-remove-btn');
            const beverageItem = button.closest('.calendar-beverage-item');
            if (beverageItem) beverageItem.remove();
            updateBeverageNumbers();
        }
    });

    // Work Progress Remove button
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('ce-work-remove-btn') || 
            event.target.closest('.ce-work-remove-btn')) {
            const button = event.target.classList.contains('ce-work-remove-btn') ? 
                        event.target : event.target.closest('.ce-work-remove-btn');
            const workEntry = button.closest('.ce-work-entry');
            
            if (workEntry) {
                // Animate removal
                workEntry.style.opacity = '0';
                workEntry.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    workEntry.remove();
                    updateWorkEntryNumbers();
                }, 300);
            }
        }
    });
    
    // Inventory Remove button
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('cei-inventory-remove-btn') || 
            event.target.closest('.cei-inventory-remove-btn')) {
            const button = event.target.classList.contains('cei-inventory-remove-btn') ? 
                        event.target : event.target.closest('.cei-inventory-remove-btn');
            const inventoryEntry = button.closest('.cei-inventory-entry');
            
            if (inventoryEntry) {
                // Animate removal
                inventoryEntry.style.opacity = '0';
                inventoryEntry.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    inventoryEntry.remove();
                    updateInventoryEntryNumbers();
                }, 300);
            }
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
    
    // Update wages summary when adding a new vendor
    updateWagesSummary();
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
    const eventTitleElement = document.getElementById('event-title');
    let eventTitle = eventTitleElement.value;
    
    // Check if custom title is selected and get custom value
    if (eventTitle === 'custom') {
        const customTitle = document.getElementById('custom-event-title').value.trim();
        if (!customTitle) {
            showToast('Error', 'Please enter a custom event title', 'error');
        return;
        }
        eventTitle = customTitle;
    }
    
    // Basic validation
    if (!eventTitle.trim()) {
        showToast('Error', 'Please select an event title', 'error');
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
        
        // Get beverage data
        const beverageItems = document.querySelectorAll('.calendar-beverage-item');
        const beverages = [];
        
        beverageItems.forEach(item => {
            const type = item.querySelector('.calendar-beverage-type').value;
            const name = item.querySelector('.calendar-beverage-name').value;
            const amount = item.querySelector('.calendar-beverage-amount').value;
            
            if (type.trim() || name.trim()) {
                beverages.push({
                    type: type,
                    name: name,
                    amount: amount || 0
                });
            }
        });
        
        // Add beverages to form data
        if (beverages.length > 0) {
            formData.append('beverage_count', beverages.length);
            
            beverages.forEach((beverage, index) => {
                const n = index + 1;
                formData.append(`beverage_type_${n}`, beverage.type);
                formData.append(`beverage_name_${n}`, beverage.name);
                formData.append(`beverage_amount_${n}`, beverage.amount);
            });
        }

        // Get work progress data
        const workEntries = document.querySelectorAll('.ce-work-entry');
        const workProgressData = [];
        
        workEntries.forEach(entry => {
            // Get selected work category
            const categorySelect = entry.querySelector('.ce-work-category-select');
            const categoryValue = categorySelect.value;
            let workCategory = categoryValue;
            
            // Handle custom category
            if (categoryValue === 'custom') {
                const customCategoryInput = entry.querySelector('.ce-work-custom-category');
                workCategory = customCategoryInput.value.trim() || 'Custom';
            }
            
            // Get selected work type
            const typeSelect = entry.querySelector('.ce-work-type-select');
            const typeValue = typeSelect.value;
            let workType = typeValue;
            
            // Handle custom type
            if (typeValue === 'custom') {
                const customTypeInput = entry.querySelector('.ce-work-custom-type');
                workType = customTypeInput.value.trim() || 'Custom';
            }
            
            // Get work done status
            const workDoneRadios = entry.querySelectorAll('input[type="radio"][name^="work-done-"]');
            let workDone = 'yes'; // Default value
            
            workDoneRadios.forEach(radio => {
                if (radio.checked) {
                    workDone = radio.value;
                }
            });
            
            // Get remarks
            const remarks = entry.querySelector('.ce-work-remarks').value;
            
            // Get media files
            const mediaPreviews = entry.querySelectorAll('.ce-work-media-preview');
            const mediaFiles = [];
            
            mediaPreviews.forEach(preview => {
                if (preview.file) {
                    mediaFiles.push(preview.file);
                }
            });
            
            // Add to work progress data array if we have at least category or type
            if (workCategory || workType || remarks || mediaFiles.length > 0) {
                workProgressData.push({
                    category: workCategory,
                    type: workType,
                    done: workDone,
                    remarks: remarks,
                    media: mediaFiles
                });
            }
        });
        
        // Add work progress data to form data
        if (workProgressData.length > 0) {
            formData.append('work_progress_count', workProgressData.length);
            
            workProgressData.forEach((work, index) => {
                const n = index + 1;
                formData.append(`work_category_${n}`, work.category);
                formData.append(`work_type_${n}`, work.type);
                formData.append(`work_done_${n}`, work.done);
                formData.append(`work_remarks_${n}`, work.remarks);
                
                // Append media files with proper index
                work.media.forEach((file, fileIndex) => {
                    formData.append(`work_media_${n}_${fileIndex + 1}`, file);
                });
                
                // Store total media count for this work
                formData.append(`work_media_count_${n}`, work.media.length);
            });
        }
        
        // Get inventory data
        const inventoryEntries = document.querySelectorAll('.cei-inventory-entry');
        const inventoryData = [];
        
        inventoryEntries.forEach(entry => {
            const inventoryType = entry.querySelector('.cei-inventory-type-select').value;
            const materialType = entry.querySelector('.cei-inventory-material-select').value === 'custom' 
                ? entry.querySelector('.cei-inventory-custom-material').value 
                : entry.querySelector('.cei-inventory-material-select').value;
            const quantity = entry.querySelector('.cei-inventory-quantity').value;
            const unit = entry.querySelector('.cei-inventory-unit').value;
            const remarks = entry.querySelector('.cei-inventory-remarks').value;
            
            // Collect bill image
            const billFileInput = entry.querySelector('.cei-inventory-bill-input');
            const billFile = billFileInput && billFileInput.files.length > 0 ? billFileInput.files[0] : null;
            
            // Collect media files
            const mediaContainer = entry.querySelector('.cei-inventory-media-container');
            const mediaFiles = [];
            
            if (mediaContainer) {
                const mediaFileInputs = mediaContainer.querySelectorAll('.cei-inventory-media-file');
                mediaFileInputs.forEach(mediaInput => {
                    if (mediaInput && mediaInput.files && mediaInput.files.length > 0) {
                        mediaFiles.push(mediaInput.files[0]);
                    }
                });
            }
            
            // Add to inventory data array if we have at least material type
            if (materialType) {
                inventoryData.push({
                    type: inventoryType,
                    material: materialType,
                    quantity: quantity,
                    unit: unit,
                    remarks: remarks,
                    billFile: billFile,
                    media: mediaFiles
                });
            }
        });
        
        // Add inventory data to form data
        if (inventoryData.length > 0) {
            formData.append('inventory_count', inventoryData.length);
            
            inventoryData.forEach((inventory, index) => {
                const n = index + 1;
                formData.append(`inventory_type_${n}`, inventory.type);
                formData.append(`material_type_${n}`, inventory.material);
                formData.append(`quantity_${n}`, inventory.quantity || 0);
                formData.append(`unit_${n}`, inventory.unit || '');
                formData.append(`inventory_remarks_${n}`, inventory.remarks || '');
                
                // Append bill file if exists
                if (inventory.billFile) {
                    formData.append(`inventory_bill_${n}`, inventory.billFile);
                }
                
                // Append media files with proper index
                inventory.media.forEach((file, fileIndex) => {
                    formData.append(`inventory_media_${n}_${fileIndex + 1}`, file);
                });
                
                // Store total media count for this inventory item
                formData.append(`inventory_media_count_${n}`, inventory.media.length);
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
    
    // Update labour numbers
    updateLabourNumbers(vendorItem);
    
    // Update wages summary when adding new labour
    updateWagesSummary();
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
    
    // Update wages summary when adding a new company
    updateWagesSummary();
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
    
    // Update company labour numbers
    updateCompanyLabourNumbers(companyItem);
    
    // Update wages summary when adding new company labour
    updateWagesSummary();
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

// Add new beverage function
function addNewBeverage() {
    const beveragesContainer = document.getElementById('calendar-beverages-container');
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = window.beverageTemplate;
    const beverageItem = tempContainer.firstElementChild;
    
    // Update number
    const existing = beveragesContainer.querySelectorAll('.calendar-beverage-item');
    const beverageNumber = existing.length + 1;
    beverageItem.querySelector('.calendar-beverage-number').textContent = beverageNumber;
    
    // Add unique ID to this beverage item
    beverageItem.id = `calendar-beverage-${beverageNumber}`;
    
    // Add to the beverages container with a slight animation delay
    beverageItem.style.opacity = '0';
    beverageItem.style.transform = 'translateY(20px)';
    beveragesContainer.appendChild(beverageItem);
    
    // Animate the new beverage entry
    setTimeout(() => {
        beverageItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        beverageItem.style.opacity = '1';
        beverageItem.style.transform = 'translateY(0)';
    }, 10);
    
    // Focus the beverage type input
    setTimeout(() => {
        const typeInput = beverageItem.querySelector('.calendar-beverage-type');
        typeInput.focus();
    }, 300);
    
    // Update wages summary when adding a new beverage
    updateWagesSummary();
}

// Update beverage numbers after remove
function updateBeverageNumbers() {
    const beveragesContainer = document.getElementById('calendar-beverages-container');
    const beverageItems = beveragesContainer.querySelectorAll('.calendar-beverage-item');
    
    beverageItems.forEach((item, index) => {
        // Update the number in the span
        item.querySelector('.calendar-beverage-number').textContent = index + 1;
        
        // Update the ID
        item.id = `calendar-beverage-${index + 1}`;
    });
}

/**
 * Add a new work progress entry
 */
function addWorkProgressEntry() {
    const container = document.getElementById('ce-work-progress-container');
    const tempContainer = document.createElement('div');
    
    // Generate a unique timestamp for radio buttons
    const timestamp = Date.now();
    let template = window.workProgressTemplate.replace(/TIMESTAMP/g, timestamp);
    
    tempContainer.innerHTML = template;
    const workEntry = tempContainer.firstElementChild;
    
    // Update the entry number
    const existing = container.querySelectorAll('.ce-work-entry');
    const entryNumber = existing.length + 1;
    workEntry.querySelector('.ce-work-number').textContent = entryNumber;
    
    // Add unique ID to this entry
    workEntry.id = `ce-work-entry-${entryNumber}`;
    
    // Add to the container with animation
    workEntry.style.opacity = '0';
    workEntry.style.transform = 'translateY(20px)';
    container.appendChild(workEntry);
    
    // Animate the new entry
    setTimeout(() => {
        workEntry.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        workEntry.style.opacity = '1';
        workEntry.style.transform = 'translateY(0)';
    }, 10);
    
    // Set up event listeners
    setupWorkCategoryListeners(workEntry);
    setupMediaUploadListeners(workEntry);
}

/**
 * Set up event listeners for work category and type selections
 */
function setupWorkCategoryListeners(workEntry) {
    const categorySelect = workEntry.querySelector('.ce-work-category-select');
    const typeSelect = workEntry.querySelector('.ce-work-type-select');
    const customCategoryInput = workEntry.querySelector('.ce-work-custom-category');
    const customTypeInput = workEntry.querySelector('.ce-work-custom-type');
    
    // Define work types for each category
    const workTypes = {
        'structural': ['Foundation', 'Columns', 'Beams', 'Slab Work', 'Walls', 'Roofing', 'Custom'],
        'electrical': ['Wiring', 'Conduit Installation', 'Distribution Box', 'Lighting Fixtures', 'Outlets', 'Switches', 'Custom'],
        'plumbing': ['Piping', 'Drainage System', 'Fixture Installation', 'Water Supply', 'Sewage Lines', 'Custom'],
        'interior': ['Flooring', 'Wall Finishing', 'Ceiling Work', 'Painting', 'Door Installation', 'Window Installation', 'Custom'],
        'exterior': ['Facade Work', 'Wall Cladding', 'Waterproofing', 'External Painting', 'Custom'],
        'landscaping': ['Grading', 'Planting', 'Irrigation System', 'Hardscaping', 'Lawn Setup', 'Custom']
    };
    
    // Category change handler
    categorySelect.addEventListener('change', function() {
        const selectedCategory = this.value;
        
        // Handle custom category
        if (selectedCategory === 'custom') {
            customCategoryInput.style.display = 'block';
            customCategoryInput.focus();
            
            // Disable type select
            typeSelect.disabled = true;
            typeSelect.innerHTML = '<option value="">Select Type of Work</option>';
        } else {
            customCategoryInput.style.display = 'none';
            
            // Enable and populate type select
            typeSelect.disabled = false;
            typeSelect.innerHTML = '<option value="">Select Type of Work</option>';
            
            if (selectedCategory && workTypes[selectedCategory]) {
                workTypes[selectedCategory].forEach(type => {
                    const option = document.createElement('option');
                    option.value = type.toLowerCase().replace(' ', '_');
                    option.textContent = type;
                    typeSelect.appendChild(option);
                });
            }
        }
    });
    
    // Type change handler
    typeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        
        // Handle custom type
        if (selectedType === 'custom') {
            customTypeInput.style.display = 'block';
            customTypeInput.focus();
        } else {
            customTypeInput.style.display = 'none';
        }
    });
}

/**
 * Set up listeners for media upload
 */
function setupMediaUploadListeners(workEntry) {
    const uploadInput = workEntry.querySelector('.ce-work-upload-input');
    const mediaContainer = workEntry.querySelector('.ce-work-media-container');
    const uploadBtn = workEntry.querySelector('.ce-work-upload-btn');
    
    // Create media counter
    const mediaCounter = document.createElement('div');
    mediaCounter.className = 'ce-work-media-counter';
    mediaCounter.innerHTML = '0 files added';
    uploadBtn.parentNode.insertBefore(mediaCounter, uploadBtn.nextSibling);
    
    // Function to update the counter
    function updateMediaCounter() {
        const count = mediaContainer.querySelectorAll('.ce-work-media-preview').length;
        mediaCounter.innerHTML = count + (count === 1 ? ' file added' : ' files added');
        
        // Add a class to the counter based on count
        mediaCounter.classList.remove('has-files');
        mediaContainer.classList.remove('has-files');
        
        if (count > 0) {
            mediaCounter.classList.add('has-files');
            mediaContainer.classList.add('has-files');
        }
    }
    
    // Handle file selection
    uploadInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            const file = this.files[0];
            const isVideo = file.type.startsWith('video/');
            
            // Create a preview element
            const preview = document.createElement('div');
            preview.className = 'ce-work-media-preview';
            preview.dataset.filename = file.name;
            
            // Create a remove button
            const removeBtn = document.createElement('div');
            removeBtn.className = 'ce-work-media-remove';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            preview.appendChild(removeBtn);
            
            // Create a type label
            const typeLabel = document.createElement('div');
            typeLabel.className = 'ce-work-media-type';
            typeLabel.textContent = isVideo ? 'Video' : 'Photo';
            preview.appendChild(typeLabel);
            
            // Store the file in the DOM element
            preview.file = file;
            
            if (isVideo) {
                // For videos, use a video element
                const video = document.createElement('video');
                video.controls = false;
                video.muted = true;
                video.preload = 'metadata';
                preview.appendChild(video);
                
                // Create a play button overlay
                const playButton = document.createElement('div');
                playButton.className = 'ce-work-media-play';
                playButton.innerHTML = '<i class="fas fa-play"></i>';
                preview.appendChild(playButton);
                
                // Create a URL for the video
                const videoURL = URL.createObjectURL(file);
                video.src = videoURL;
                
                // Add click to play/pause
                preview.addEventListener('click', function(e) {
                    // Don't trigger if clicking the remove button
                    if (!e.target.closest('.ce-work-media-remove')) {
                        if (video.paused) {
                            // Play the video
                            video.play();
                            playButton.style.opacity = '0';
                        } else {
                            // Pause the video
                            video.pause();
                            playButton.style.opacity = '1';
                        }
                    }
                });
                
                // When video ends, show play button again
                video.addEventListener('ended', function() {
                    playButton.style.opacity = '1';
                });
                
                // When the preview is removed, revoke the URL
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent triggering the preview's click handler
                    URL.revokeObjectURL(videoURL);
                    preview.remove();
                    updateMediaCounter();
                });
            } else {
                // For images, use the FileReader API
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
                
                // Add click handler for removal
                removeBtn.addEventListener('click', function() {
                    preview.remove();
                    updateMediaCounter();
                });
            }
            
            // Add the preview to the container
            mediaContainer.appendChild(preview);
            
            // Update counter
            updateMediaCounter();
            
            // Reset the file input to allow selecting more files
            this.value = '';
        }
    });
    
    // Remove functionality delegated via event bubbling
    mediaContainer.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.ce-work-media-remove');
        if (removeBtn) {
            const preview = removeBtn.closest('.ce-work-media-preview');
            
            // If this is a video, revoke the URL
            const video = preview.querySelector('video');
            if (video && video.src) {
                URL.revokeObjectURL(video.src);
            }
            
            preview.remove();
            updateMediaCounter();
        }
    });
}

/**
 * Update work entry numbers
 */
function updateWorkEntryNumbers() {
    const container = document.getElementById('ce-work-progress-container');
    const entries = container.querySelectorAll('.ce-work-entry');
    
    entries.forEach((entry, index) => {
        // Update number
        entry.querySelector('.ce-work-number').textContent = index + 1;
        
        // Update ID
        entry.id = `ce-work-entry-${index + 1}`;
    });
}

/**
 * Add a new inventory entry
 */
function addInventoryEntry() {
    const container = document.getElementById('ce-inventory-container');
    const tempContainer = document.createElement('div');
    
    tempContainer.innerHTML = window.inventoryTemplate;
    const inventoryEntry = tempContainer.firstElementChild;
    
    // Update the entry number
    const existing = container.querySelectorAll('.cei-inventory-entry');
    const entryNumber = existing.length + 1;
    inventoryEntry.querySelector('.cei-inventory-number').textContent = entryNumber;
    
    // Add unique ID to this entry
    inventoryEntry.id = `cei-inventory-entry-${entryNumber}`;
    
    // Add to the container with animation
    inventoryEntry.style.opacity = '0';
    inventoryEntry.style.transform = 'translateY(20px)';
    container.appendChild(inventoryEntry);
    
    // Animate the new entry
    setTimeout(() => {
        inventoryEntry.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        inventoryEntry.style.opacity = '1';
        inventoryEntry.style.transform = 'translateY(0)';
    }, 10);
    
    // Set up event listeners
    setupInventoryMaterialListeners(inventoryEntry);
    setupInventoryBillUploadListeners(inventoryEntry);
    setupInventoryMediaUploadListeners(inventoryEntry);
    
    // Focus the material select
    setTimeout(() => {
        inventoryEntry.querySelector('.cei-inventory-material-select').focus();
    }, 300);
}

/**
 * Set up material select dropdown listeners
 */
function setupInventoryMaterialListeners(inventoryEntry) {
    const materialSelect = inventoryEntry.querySelector('.cei-inventory-material-select');
    const customMaterialInput = inventoryEntry.querySelector('.cei-inventory-custom-material');
    const inventoryTypeSelect = inventoryEntry.querySelector('.cei-inventory-type-select');
    
    // Show/hide custom material input
    materialSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customMaterialInput.style.display = 'block';
            customMaterialInput.focus();
        } else {
            customMaterialInput.style.display = 'none';
            
            // Fetch remaining material data when material is selected
            fetchRemainingMaterial(inventoryEntry, this.value);
        }
    });
    
    // Update remaining material section when inventory type changes
    inventoryTypeSelect.addEventListener('change', function() {
        const materialValue = materialSelect.value;
        if (materialValue && materialValue !== 'custom') {
            fetchRemainingMaterial(inventoryEntry, materialValue);
        }
    });
}

/**
 * Fetch remaining material data from the server
 */
function fetchRemainingMaterial(inventoryEntry, material) {
    // Get the site from the event title dropdown
    const eventTitleSelect = document.getElementById('event-title');
    let site = eventTitleSelect.value;
    
    // If custom title is selected, do nothing
    if (site === 'custom' || !site) {
        updateRemainingMaterialSection(inventoryEntry, null, 'Please select a construction site first.');
        return;
    }
    
    // Skip for custom materials
    if (material === 'custom' || !material) {
        updateRemainingMaterialSection(inventoryEntry, null, 'Please select a material.');
        return;
    }
    
    // If site is from the dropdown, extract just the site name
    if (site.startsWith('Construction Site At ')) {
        site = site.replace('Construction Site At ', '');
    }
    
    // Show loading state
    const contentArea = inventoryEntry.querySelector('.ceirm-content-area');
    contentArea.innerHTML = '<div class="ceirm-loading">Loading material information...</div>';
    
    // Prepare form data for AJAX request
    const formData = new FormData();
    formData.append('site', site);
    formData.append('material', material);
    
    // Make the AJAX request
    fetch('backend/get_material_inventory.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            updateRemainingMaterialSection(inventoryEntry, data.inventory, null);
        } else {
            updateRemainingMaterialSection(inventoryEntry, null, data.message || 'Error fetching material information.');
        }
    })
    .catch(error => {
        console.error('Error fetching material inventory:', error);
        updateRemainingMaterialSection(inventoryEntry, null, 'Network error. Please try again.');
    });
}

/**
 * Update the remaining material section with fetched data
 */
function updateRemainingMaterialSection(inventoryEntry, inventory, errorMessage) {
    const contentArea = inventoryEntry.querySelector('.ceirm-content-area');
    
    // Handle error case
    if (errorMessage) {
        contentArea.innerHTML = `<div class="ceirm-no-data">${errorMessage}</div>`;
        return;
    }
    
    // Handle empty inventory
    if (!inventory || inventory.length === 0) {
        contentArea.innerHTML = '<div class="ceirm-no-data">No inventory data found for this site and material.</div>';
        return;
    }
    
    // Find the specific material in the inventory array
    const materialSelect = inventoryEntry.querySelector('.cei-inventory-material-select');
    const selectedMaterial = materialSelect.value;
    
    let materialInfo = null;
    for (let item of inventory) {
        if (item.material.toLowerCase() === selectedMaterial.toLowerCase()) {
            materialInfo = item;
            break;
        }
    }
    
    if (!materialInfo) {
        contentArea.innerHTML = `<div class="ceirm-no-data">No data found for ${selectedMaterial} at this site.</div>`;
        return;
    }
    
    // Check if low stock (remaining less than 20% of received)
    const isLowStock = materialInfo.remaining <= (materialInfo.received * 0.2) && materialInfo.received > 0;
    const lowStockClass = isLowStock ? 'ceirm-low-stock' : '';
    
    // Format the HTML
    let html = `
        <div class="ceirm-material-info ${lowStockClass}">
            <div class="ceirm-material-name">${materialInfo.material}</div>
            <div class="ceirm-material-quantity">
                <span class="ceirm-material-value">${materialInfo.remaining.toFixed(2)}</span>
                <span class="ceirm-material-unit">${materialInfo.unit}</span>
            </div>
        </div>
        <div class="ceirm-material-details">
            <div class="ceirm-detail-item">
                <span class="ceirm-detail-label">Received:</span>
                <span class="ceirm-received">${materialInfo.received.toFixed(2)} ${materialInfo.unit}</span>
            </div>
            <div class="ceirm-detail-item">
                <span class="ceirm-detail-label">Consumed:</span>
                <span class="ceirm-consumed">${materialInfo.consumed.toFixed(2)} ${materialInfo.unit}</span>
            </div>
        </div>
    `;
    
    contentArea.innerHTML = html;
    
    // Update quantity suggestion based on remaining material
    const quantityInput = inventoryEntry.querySelector('.cei-inventory-quantity');
    const inventoryTypeSelect = inventoryEntry.querySelector('.cei-inventory-type-select');
    const inventoryUnitSelect = inventoryEntry.querySelector('.cei-inventory-unit');
    
    // Set unit value to match the inventory unit
    if (materialInfo.unit && inventoryUnitSelect.value === '') {
        // Find the option that matches or is closest to the material unit
        const options = inventoryUnitSelect.options;
        let foundExactMatch = false;
        
        for (let i = 0; i < options.length; i++) {
            if (options[i].value === materialInfo.unit) {
                inventoryUnitSelect.selectedIndex = i;
                foundExactMatch = true;
                break;
            }
        }
        
        // If no exact match found but we have a unit, select the first option
        if (!foundExactMatch && materialInfo.unit && options.length > 1) {
            inventoryUnitSelect.selectedIndex = 1; // Select first real option (not the empty one)
        }
    }
    
    // If this is a "consumed" inventory item and quantity is empty,
    // suggest the remaining amount as the quantity
    if (inventoryTypeSelect.value === 'consumed' && (!quantityInput.value || quantityInput.value === '0')) {
        // Only suggest if there's something to consume
        if (materialInfo.remaining > 0) {
            quantityInput.value = materialInfo.remaining.toFixed(2);
            
            // Briefly highlight the field to draw attention
            quantityInput.style.transition = 'background-color 0.5s ease';
            quantityInput.style.backgroundColor = '#e6fffa';
            setTimeout(() => {
                quantityInput.style.backgroundColor = '';
            }, 1500);
        }
    }
}

/**
 * Set up bill image upload listeners
 */
function setupInventoryBillUploadListeners(inventoryEntry) {
    const billInput = inventoryEntry.querySelector('.cei-inventory-bill-input');
    const billPreview = inventoryEntry.querySelector('.cei-inventory-bill-preview');
    
    billInput.addEventListener('change', function() {
        // Clear existing preview
        billPreview.innerHTML = '';
        
        if (this.files && this.files.length > 0) {
            const file = this.files[0];
            const isImage = file.type.startsWith('image/');
            const isPdf = file.type === 'application/pdf';
            
            // Create preview container
            const previewContainer = document.createElement('div');
            previewContainer.className = 'cei-inventory-bill-preview-item';
            
            // Create preview content based on file type
            if (isImage) {
                const img = document.createElement('img');
                img.className = 'cei-inventory-bill-preview-image';
                img.src = URL.createObjectURL(file);
                img.onload = function() {
                    URL.revokeObjectURL(this.src);
                };
                previewContainer.appendChild(img);
            } else if (isPdf) {
                const icon = document.createElement('i');
                icon.className = 'fas fa-file-pdf cei-inventory-bill-preview-pdf';
                previewContainer.appendChild(icon);
                
                const fileName = document.createElement('span');
                fileName.className = 'cei-inventory-bill-preview-filename';
                fileName.textContent = file.name;
                previewContainer.appendChild(fileName);
            }
            
            // Create remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'cei-inventory-bill-preview-remove';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', function() {
                billInput.value = '';
                billPreview.innerHTML = '';
            });
            previewContainer.appendChild(removeBtn);
            
            // Add preview to container
            billPreview.appendChild(previewContainer);
        }
    });
}

/**
 * Set up media upload listeners for inventory
 */
function setupInventoryMediaUploadListeners(inventoryEntry) {
    const uploadInput = inventoryEntry.querySelector('.cei-inventory-upload-input');
    const mediaContainer = inventoryEntry.querySelector('.cei-inventory-media-container');
    const mediaCounter = inventoryEntry.querySelector('.cei-inventory-media-counter');
    
    uploadInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            const file = this.files[0];
            
            // Create a unique ID for this media item
            const mediaId = `inventory-media-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
            
            // Create media preview item
            const mediaPreviewItem = document.createElement('div');
            mediaPreviewItem.className = 'cei-inventory-media-preview-item';
            mediaPreviewItem.dataset.id = mediaId;
            
            // Prepare a hidden input to store the file
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'file';
            hiddenInput.className = 'cei-inventory-media-file';
            hiddenInput.style.display = 'none';
            hiddenInput.dataset.id = mediaId;
            
            // Use DataTransfer to transfer the File object to the new input
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            hiddenInput.files = dataTransfer.files;
            
            // Check if it's an image or video
            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');
            
            if (isImage) {
                // Create image preview
                const img = document.createElement('img');
                img.className = 'cei-inventory-media-preview-image';
                img.src = URL.createObjectURL(file);
                img.onload = function() {
                    URL.revokeObjectURL(this.src);
                };
                mediaPreviewItem.appendChild(img);
            } else if (isVideo) {
                // Create video preview with play icon
                const videoContainer = document.createElement('div');
                videoContainer.className = 'cei-inventory-media-preview-video-container';
                
                const videoPreview = document.createElement('video');
                videoPreview.className = 'cei-inventory-media-preview-video';
                videoPreview.src = URL.createObjectURL(file);
                videoPreview.onload = function() {
                    URL.revokeObjectURL(this.src);
                };
                videoContainer.appendChild(videoPreview);
                
                // Add play icon overlay
                const playIcon = document.createElement('div');
                playIcon.className = 'cei-inventory-media-preview-play-icon';
                playIcon.innerHTML = '<i class="fas fa-play"></i>';
                videoContainer.appendChild(playIcon);
                
                mediaPreviewItem.appendChild(videoContainer);
            }
            
            // Create filename display
            const fileName = document.createElement('div');
            fileName.className = 'cei-inventory-media-preview-filename';
            fileName.textContent = file.name.slice(0, 15) + (file.name.length > 15 ? '...' : '');
            fileName.title = file.name;
            mediaPreviewItem.appendChild(fileName);
            
            // Create remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'cei-inventory-media-preview-remove';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', function() {
                mediaPreviewItem.remove();
                hiddenInput.remove();
                updateInventoryMediaCounter(inventoryEntry);
            });
            mediaPreviewItem.appendChild(removeBtn);
            
            // Add to containers
            mediaContainer.appendChild(mediaPreviewItem);
            mediaContainer.appendChild(hiddenInput);
            
            // Reset the file input for the next upload
            uploadInput.value = '';
            
            // Update media counter
            updateInventoryMediaCounter(inventoryEntry);
        }
    });
}

/**
 * Update the media counter for inventory
 */
function updateInventoryMediaCounter(inventoryEntry) {
    const mediaContainer = inventoryEntry.querySelector('.cei-inventory-media-container');
    const mediaCounter = inventoryEntry.querySelector('.cei-inventory-media-counter');
    const fileCount = mediaContainer.querySelectorAll('.cei-inventory-media-preview-item').length;
    
    if (fileCount > 0) {
        mediaCounter.textContent = `${fileCount} file${fileCount !== 1 ? 's' : ''} added`;
        mediaCounter.style.display = 'block';
    } else {
        mediaCounter.style.display = 'none';
    }
}

/**
 * Update inventory entry numbers
 */
function updateInventoryEntryNumbers() {
    const container = document.getElementById('ce-inventory-container');
    const entries = container.querySelectorAll('.cei-inventory-entry');
    
    entries.forEach((entry, index) => {
        // Update number
        entry.querySelector('.cei-inventory-number').textContent = index + 1;
        
        // Update ID
        entry.id = `cei-inventory-entry-${index + 1}`;
    });
}

/**
 * Update all remaining material sections in the modal
 */
function updateAllRemainingMaterialSections() {
    const inventoryEntries = document.querySelectorAll('.cei-inventory-entry');
    
    inventoryEntries.forEach(entry => {
        const materialSelect = entry.querySelector('.cei-inventory-material-select');
        const materialValue = materialSelect.value;
        
        if (materialValue && materialValue !== 'custom') {
            fetchRemainingMaterial(entry, materialValue);
        } else {
            // Show a message that material needs to be selected
            const contentArea = entry.querySelector('.ceirm-content-area');
            contentArea.innerHTML = '<div class="ceirm-no-data">Please select a material to see remaining inventory.</div>';
        }
    });
}

/**
 * Updates the Wages Summary section with calculated totals
 */
function updateWagesSummary() {
    // Initialize counters
    let vendorLabourWages = 0;
    let companyLabourWages = 0;
    let overtimePayments = 0;
    let travelExpenses = 0;
    let totalWages = 0;
    
    // Calculate vendor labour wages
    const vendorLabourEntries = document.querySelectorAll('.supervisor-labour-entry');
    vendorLabourEntries.forEach(labour => {
        // Get daily wages
        const dailyWages = parseFloat(labour.querySelector('.slw-total-day-wages').value) || 0;
        vendorLabourWages += dailyWages;
        
        // Get overtime amount
        const overtimeAmount = parseFloat(labour.querySelector('.slw-total-ot-amount').value) || 0;
        overtimePayments += overtimeAmount;
        
        // Get travel amount
        const travelAmount = parseFloat(labour.querySelector('.slw-travel-amount').value) || 0;
        travelExpenses += travelAmount;
    });
    
    // Calculate company labour wages
    const companyLabourEntries = document.querySelectorAll('.scl-labour-entry');
    companyLabourEntries.forEach(labour => {
        // Get daily wages
        const dailyWages = parseFloat(labour.querySelector('.scl-total-day-wages').value) || 0;
        companyLabourWages += dailyWages;
        
        // Get overtime amount
        const overtimeAmount = parseFloat(labour.querySelector('.scl-total-ot-amount').value) || 0;
        overtimePayments += overtimeAmount;
        
        // Get travel amount
        const travelAmount = parseFloat(labour.querySelector('.scl-travel-amount').value) || 0;
        travelExpenses += travelAmount;
    });
    
    // Calculate total wages
    totalWages = vendorLabourWages + companyLabourWages + overtimePayments + travelExpenses;
    
    // Update the summary display
    document.getElementById('vendor-labour-wages').textContent = vendorLabourWages.toFixed(2);
    document.getElementById('company-labour-wages').textContent = companyLabourWages.toFixed(2);
    document.getElementById('overtime-payments').textContent = overtimePayments.toFixed(2);
    document.getElementById('travel-expenses').textContent = travelExpenses.toFixed(2);
    document.getElementById('total-wages').textContent = totalWages.toFixed(2);
    
    // Highlight the total wages section with animation
    const totalWagesRow = document.querySelector('.sv-wages-total-row');
    if (totalWagesRow) {
        totalWagesRow.classList.add('sv-highlight-animation');
        setTimeout(() => {
            totalWagesRow.classList.remove('sv-highlight-animation');
        }, 1500);
    }
}
  
