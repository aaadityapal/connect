// calendar-event-edit-modal.js - Part 1: Modal Structure and Initialization

/**
 * Calendar Event Edit Modal
 * Allows editing of existing calendar events with all their associated data
 */
class CalendarEventEditModal {
    constructor() {
        this.eventId = null;
        this.eventData = null;
        this.vendorCounter = 0;
        this.labourerCounter = 0;
        this.companyLabourCounter = 0;
        this.beverageCounter = 0;
        this.workProgressCounter = 0;
        this.inventoryCounter = 0;
        this.init();
    }

    init() {
        // Create modal structure if it doesn't exist
        this.createModalStructure();
        
        // Initialize event listeners
        this.setupEventListeners();
    }

    createModalStructure() {
        // Check if modal already exists in the DOM
        if (document.getElementById('calendarEventEditModal')) {
            return;
        }

        const modalHTML = `
            <div id="calendarEventEditModal" class="calendar-event-edit-modal">
                <div class="calendar-event-edit-content">
                    <div class="calendar-event-edit-header">
                        <h3 id="editModalTitle">Edit Calendar Event</h3>
                        <button id="closeEditModalBtn" class="close-edit-modal-btn">&times;</button>
                    </div>
                    <div class="calendar-event-edit-body">
                        <div id="editModalLoader" class="edit-modal-loader">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p>Loading event details...</p>
                        </div>
                        <div id="editModalError" class="edit-modal-error" style="display:none;">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span id="editModalErrorText">An error occurred</span>
                            </div>
                        </div>
                        <form id="eventEditForm" class="event-edit-form">
                            <input type="hidden" id="editEventId" name="event_id">
                            
                            <!-- Main Event Details -->
                            <div class="form-section">
                                <h4 class="section-title">Event Details</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-8">
                                        <label for="editEventTitle">Title</label>
                                        <input type="text" class="form-control" id="editEventTitle" name="title" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="editEventDate">Date</label>
                                        <input type="date" class="form-control" id="editEventDate" name="event_date" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tabs for different sections -->
                            <ul class="nav nav-tabs" id="eventEditTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="vendors-tab" data-toggle="tab" href="#vendors-content" role="tab">
                                        Vendors & Workers
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="company-labours-tab" data-toggle="tab" href="#company-labours-content" role="tab">
                                        Company Workers
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="beverages-tab" data-toggle="tab" href="#beverages-content" role="tab">
                                        Beverages
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="work-progress-tab" data-toggle="tab" href="#work-progress-content" role="tab">
                                        Work Progress
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="inventory-tab" data-toggle="tab" href="#inventory-content" role="tab">
                                        Inventory
                                    </a>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="eventEditTabContent">
                                <!-- Vendors Tab -->
                                <div class="tab-pane fade show active" id="vendors-content" role="tabpanel">
                                    <div class="form-section">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5>Vendors</h5>
                                            <button type="button" class="btn btn-sm btn-primary" id="addVendorBtn">
                                                <i class="fas fa-plus"></i> Add Vendor
                                            </button>
                                        </div>
                                        <div id="vendorsContainer" class="vendors-container">
                                            <!-- Vendor items will be added here -->
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Company Workers Tab -->
                                <div class="tab-pane fade" id="company-labours-content" role="tabpanel">
                                    <div class="form-section">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5>Company Workers</h5>
                                            <button type="button" class="btn btn-sm btn-primary" id="addCompanyLabourBtn">
                                                <i class="fas fa-plus"></i> Add Worker
                                            </button>
                                        </div>
                                        <div id="companyLaboursContainer" class="company-labours-container">
                                            <!-- Company workers will be added here -->
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Beverages Tab -->
                                <div class="tab-pane fade" id="beverages-content" role="tabpanel">
                                    <div class="form-section">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5>Beverages</h5>
                                            <button type="button" class="btn btn-sm btn-primary" id="addBeverageBtn">
                                                <i class="fas fa-plus"></i> Add Beverage
                                            </button>
                                        </div>
                                        <div id="beveragesContainer" class="beverages-container">
                                            <!-- Beverage items will be added here -->
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Work Progress Tab -->
                                <div class="tab-pane fade" id="work-progress-content" role="tabpanel">
                                    <div class="form-section">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5>Work Progress</h5>
                                            <button type="button" class="btn btn-sm btn-primary" id="addWorkProgressBtn">
                                                <i class="fas fa-plus"></i> Add Work Item
                                            </button>
                                        </div>
                                        <div id="workProgressContainer" class="work-progress-container">
                                            <!-- Work progress items will be added here -->
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Inventory Tab -->
                                <div class="tab-pane fade" id="inventory-content" role="tabpanel">
                                    <div class="form-section">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5>Inventory</h5>
                                            <button type="button" class="btn btn-sm btn-primary" id="addInventoryBtn">
                                                <i class="fas fa-plus"></i> Add Inventory Item
                                            </button>
                                        </div>
                                        <div id="inventoryContainer" class="inventory-container">
                                            <!-- Inventory items will be added here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="calendar-event-edit-footer">
                        <button type="button" class="btn btn-secondary" id="cancelEditBtn">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveEventBtn">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add modal to the body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add the necessary styles if they don't exist
        if (!document.getElementById('calendarEventEditModalStyles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'calendarEventEditModalStyles';
            styleSheet.innerHTML = `
                .calendar-event-edit-modal {
                    display: none;
                    position: fixed;
                    z-index: 1050;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    overflow: auto;
                    background-color: rgba(0,0,0,0.7);
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }
                
                .calendar-event-edit-modal.active {
                    opacity: 1;
                }
                
                .calendar-event-edit-content {
                    position: relative;
                    background-color: #fff;
                    margin: 30px auto;
                    width: 90%;
                    max-width: 900px;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                    overflow: hidden;
                    max-height: 90vh;
                    display: flex;
                    flex-direction: column;
                }
                
                .calendar-event-edit-header {
                    padding: 15px 20px;
                    background-color: #3498db;
                    color: white;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-shrink: 0;
                }
                
                .calendar-event-edit-header h3 {
                    margin: 0;
                    font-size: 1.4rem;
                }
                
                .close-edit-modal-btn {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 1.8rem;
                    line-height: 1;
                    cursor: pointer;
                }
                
                .calendar-event-edit-body {
                    padding: 20px;
                    overflow-y: auto;
                    flex-grow: 1;
                }
                
                .calendar-event-edit-footer {
                    padding: 15px 20px;
                    background-color: #f8f9fa;
                    border-top: 1px solid #dee2e6;
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                    flex-shrink: 0;
                }
                
                .edit-modal-loader {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 30px;
                }
                
                .form-section {
                    background-color: #f8f9fa;
                    border-radius: 6px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                
                .section-title {
                    font-size: 1.1rem;
                    margin-bottom: 15px;
                    color: #495057;
                }
                
                .vendor-item, .labour-item, .company-labour-item, .beverage-item, .work-progress-item, .inventory-item {
                    background-color: #fff;
                    border-radius: 6px;
                    padding: 15px;
                    margin-bottom: 15px;
                    border: 1px solid #dee2e6;
                    position: relative;
                }
                
                .remove-item-btn {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background-color: #f8d7da;
                    color: #721c24;
                    border: none;
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                }
                
                .material-section, .labour-section {
                    margin-top: 15px;
                    border-top: 1px solid #eee;
                    padding-top: 15px;
                }
                
                .media-upload-container {
                    margin-top: 10px;
                    padding: 10px;
                    border: 1px dashed #ccc;
                    border-radius: 4px;
                }
                
                .existing-media-container {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    margin-top: 10px;
                }
                
                .media-item {
                    width: 80px;
                    height: 80px;
                    border-radius: 4px;
                    overflow: hidden;
                    position: relative;
                    border: 1px solid #dee2e6;
                }
                
                .media-item img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                
                .remove-media-btn {
                    position: absolute;
                    top: 2px;
                    right: 2px;
                    background-color: rgba(255,255,255,0.7);
                    color: #721c24;
                    border: none;
                    width: 20px;
                    height: 20px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 10px;
                    cursor: pointer;
                }
                
                .attendance-toggle {
                    display: flex;
                    gap: 10px;
                    margin-top: 10px;
                }
                
                .attendance-toggle label {
                    display: flex;
                    align-items: center;
                    cursor: pointer;
                }
                
                .attendance-toggle input {
                    margin-right: 5px;
                }
                
                .nav-tabs {
                    margin-bottom: 20px;
                }
                
                .tab-content {
                    padding-top: 10px;
                }
                
                @media (max-width: 768px) {
                    .calendar-event-edit-content {
                        width: 95%;
                        margin: 10px auto;
                    }
                    
                    .calendar-event-edit-header {
                        padding: 10px 15px;
                    }
                    
                    .calendar-event-edit-body {
                        padding: 15px;
                    }
                    
                    .nav-tabs .nav-link {
                        padding: 0.5rem 0.5rem;
                        font-size: 0.85rem;
                    }
                }
            `;
            document.head.appendChild(styleSheet);
        }
    }

    setupEventListeners() {
        // Close modal buttons
        document.getElementById('closeEditModalBtn').addEventListener('click', () => this.hideModal());
        document.getElementById('cancelEditBtn').addEventListener('click', () => this.hideModal());
        
        // Close on backdrop click
        document.getElementById('calendarEventEditModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('calendarEventEditModal')) {
                this.hideModal();
            }
        });
        
        // Add item buttons
        document.getElementById('addVendorBtn').addEventListener('click', () => this.addVendorItem());
        document.getElementById('addCompanyLabourBtn').addEventListener('click', () => this.addCompanyLabourItem());
        document.getElementById('addBeverageBtn').addEventListener('click', () => this.addBeverageItem());
        document.getElementById('addWorkProgressBtn').addEventListener('click', () => this.addWorkProgressItem());
        document.getElementById('addInventoryBtn').addEventListener('click', () => this.addInventoryItem());
        
        // Save button
        document.getElementById('saveEventBtn').addEventListener('click', () => this.saveEventChanges());
    }

    showModal(eventId) {
        this.eventId = eventId;
        
        // Reset form and counters
        this.resetForm();
        
        // Show loading state
        document.getElementById('editModalLoader').style.display = 'flex';
        document.getElementById('editModalError').style.display = 'none';
        document.getElementById('eventEditForm').style.display = 'none';
        
        // Set event ID in form
        document.getElementById('editEventId').value = eventId;
        
        // Show the modal with animation
        const modal = document.getElementById('calendarEventEditModal');
        modal.style.display = 'block';
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        
        // Fetch and display event data
        this.fetchEventDetails(eventId);
    }

    hideModal() {
        const modal = document.getElementById('calendarEventEditModal');
        modal.classList.remove('active');
        
        // Wait for animation to finish before hiding completely
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }

    resetForm() {
        // Reset counters
        this.vendorCounter = 0;
        this.labourerCounter = 0;
        this.companyLabourCounter = 0;
        this.beverageCounter = 0;
        this.workProgressCounter = 0;
        this.inventoryCounter = 0;
        
        // Clear containers
        document.getElementById('vendorsContainer').innerHTML = '';
        document.getElementById('companyLaboursContainer').innerHTML = '';
        document.getElementById('beveragesContainer').innerHTML = '';
        document.getElementById('workProgressContainer').innerHTML = '';
        document.getElementById('inventoryContainer').innerHTML = '';
        
        // Reset form fields
        document.getElementById('editEventTitle').value = '';
        document.getElementById('editEventDate').value = '';
    }

    // calendar-event-edit-modal.js - Part 2: Data Fetching and Form Population

    async fetchEventDetails(eventId) {
        try {
            // Show loading state
            document.getElementById('editModalLoader').style.display = 'flex';
            document.getElementById('editModalError').style.display = 'none';
            document.getElementById('eventEditForm').style.display = 'none';
            
            // Make API call to fetch event details
            const response = await fetch(`backend/get_event_details.php?event_id=${eventId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            });
            
            // Get response text
            const responseText = await response.text();
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Error parsing JSON response:', parseError);
                console.log('Raw response text:', responseText);
                throw new Error(`Failed to parse server response as JSON. Server returned: ${responseText.substring(0, 100)}...`);
            }
            
            if (data.status === 'success') {
                this.eventData = data.event;
                this.populateForm(data.event);
            } else {
                this.showErrorMessage(data.message || 'Failed to load event details');
            }
        } catch (error) {
            console.error('Error fetching event details:', error);
            this.showErrorMessage(`An error occurred while fetching event details: ${error.message}`);
        } finally {
            // Hide the loader
            document.getElementById('editModalLoader').style.display = 'none';
        }
    }

    populateForm(event) {
        // Populate main event fields
        document.getElementById('editEventTitle').value = event.title || '';
        document.getElementById('editEventDate').value = event.event_date || '';
        
        // Populate vendors and their related items
        if (event.vendors && event.vendors.length > 0) {
            event.vendors.forEach(vendor => {
                this.addVendorItem(vendor);
            });
        }
        
        // Populate company labourers
        if (event.company_labours && event.company_labours.length > 0) {
            event.company_labours.forEach(labour => {
                this.addCompanyLabourItem(labour);
            });
        }
        
        // Populate beverages
        if (event.beverages && event.beverages.length > 0) {
            event.beverages.forEach(beverage => {
                this.addBeverageItem(beverage);
            });
        }
        
        // Populate work progress items
        if (event.work_progress && event.work_progress.length > 0) {
            event.work_progress.forEach(workItem => {
                this.addWorkProgressItem(workItem);
            });
        }
        
        // Populate inventory items
        if (event.inventory && event.inventory.length > 0) {
            event.inventory.forEach(inventoryItem => {
                this.addInventoryItem(inventoryItem);
            });
        }
        
        // Show the form
        document.getElementById('eventEditForm').style.display = 'block';
    }

    addVendorItem(vendorData = null) {
        const vendorIndex = this.vendorCounter++;
        const vendorId = vendorData ? vendorData.vendor_id : '';
        const vendorName = vendorData ? vendorData.vendor_name : '';
        const vendorType = vendorData ? vendorData.vendor_type : '';
        const contactNumber = vendorData ? vendorData.contact_number : '';
        
        const vendorHTML = `
            <div class="vendor-item" id="vendor-${vendorIndex}">
                <button type="button" class="remove-item-btn" onclick="window.calendarEventEditModal.removeVendorItem(${vendorIndex})">
                    <i class="fas fa-times"></i>
                </button>
                
                <input type="hidden" name="vendors[${vendorIndex}][vendor_id]" value="${vendorId}">
                
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Vendor Name</label>
                        <input type="text" class="form-control" name="vendors[${vendorIndex}][vendor_name]" value="${this.escapeHtml(vendorName)}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Vendor Type</label>
                        <select class="form-control" name="vendors[${vendorIndex}][vendor_type]" required>
                            <option value="Supplier" ${vendorType === 'Supplier' ? 'selected' : ''}>Supplier</option>
                            <option value="Contractor" ${vendorType === 'Contractor' ? 'selected' : ''}>Contractor</option>
                            <option value="Consultant" ${vendorType === 'Consultant' ? 'selected' : ''}>Consultant</option>
                            <option value="Laborer" ${vendorType === 'Laborer' ? 'selected' : ''}>Laborer</option>
                            <option value="Other" ${vendorType === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Contact Number</label>
                        <input type="text" class="form-control" name="vendors[${vendorIndex}][contact_number]" value="${this.escapeHtml(contactNumber)}">
                    </div>
                </div>
                
                <!-- Material Section -->
                <div class="material-section">
                    <h6><i class="fas fa-boxes"></i> Materials</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Amount</label>
                            <input type="number" step="0.01" class="form-control" name="vendors[${vendorIndex}][material_amount]" 
                                value="${vendorData && vendorData.material ? vendorData.material.amount || '' : ''}">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Remarks</label>
                            <textarea class="form-control" name="vendors[${vendorIndex}][material_remarks]">${vendorData && vendorData.material ? this.escapeHtml(vendorData.material.remarks || '') : ''}</textarea>
                        </div>
                    </div>
                    
                    <!-- Material Images -->
                    <div class="form-group">
                        <label>Material Images</label>
                        <div class="media-upload-container">
                            <input type="file" class="form-control-file" name="vendors[${vendorIndex}][material_images][]" multiple accept="image/*">
                            ${this.renderExistingMedia(vendorData, 'materialPictures', vendorIndex, 'material')}
                        </div>
                    </div>
                    
                    <!-- Bill Images -->
                    <div class="form-group">
                        <label>Bill Images</label>
                        <div class="media-upload-container">
                            <input type="file" class="form-control-file" name="vendors[${vendorIndex}][bill_images][]" multiple accept="image/*,application/pdf">
                            ${this.renderExistingMedia(vendorData, 'billPictures', vendorIndex, 'bill')}
                        </div>
                    </div>
                </div>
                
                <!-- Laborers Section -->
                <div class="labour-section">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6><i class="fas fa-hard-hat"></i> Laborers</h6>
                        <button type="button" class="btn btn-sm btn-info" onclick="window.calendarEventEditModal.addLabourerItem(${vendorIndex})">
                            <i class="fas fa-plus"></i> Add Laborer
                        </button>
                    </div>
                    <div id="laborers-container-${vendorIndex}" class="laborers-container">
                        <!-- Laborers will be added here -->
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('vendorsContainer').insertAdjacentHTML('beforeend', vendorHTML);
        
        // Add laborers if they exist
        if (vendorData && vendorData.labourers && vendorData.labourers.length > 0) {
            vendorData.labourers.forEach(laborer => {
                this.addLabourerItem(vendorIndex, laborer);
            });
        }
    }

    addLabourerItem(vendorIndex, labourerData = null) {
        const labourerIndex = this.labourerCounter++;
        const labourId = labourerData ? labourerData.labour_id : '';
        const labourName = labourerData ? labourerData.labour_name : '';
        const contactNumber = labourerData ? labourerData.contact_number : '';
        const morningAttendance = labourerData ? labourerData.morning_attendance : 'present';
        const eveningAttendance = labourerData ? labourerData.evening_attendance : 'present';
        
        // Get wages and overtime data if available
        const wages = labourerData && labourerData.wages ? labourerData.wages : {};
        const overtime = labourerData && labourerData.overtime ? labourerData.overtime : {};
        const travel = labourerData && labourerData.travel ? labourerData.travel : {};
        
        const labourerHTML = `
            <div class="labour-item" id="labour-${labourerIndex}">
                <button type="button" class="remove-item-btn" onclick="window.calendarEventEditModal.removeLabourerItem(${labourerIndex})">
                    <i class="fas fa-times"></i>
                </button>
                
                <input type="hidden" name="vendors[${vendorIndex}][labourers][${labourerIndex}][labour_id]" value="${labourId}">
                
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Laborer Name</label>
                        <input type="text" class="form-control" name="vendors[${vendorIndex}][labourers][${labourerIndex}][labour_name]" value="${this.escapeHtml(labourName)}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Contact Number</label>
                        <input type="text" class="form-control" name="vendors[${vendorIndex}][labourers][${labourerIndex}][contact_number]" value="${this.escapeHtml(contactNumber)}">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Morning Attendance</label>
                        <div class="attendance-toggle">
                            <label>
                                <input type="radio" name="vendors[${vendorIndex}][labourers][${labourerIndex}][morning_attendance]" value="present" ${morningAttendance === 'present' ? 'checked' : ''}>
                                Present
                            </label>
                            <label>
                                <input type="radio" name="vendors[${vendorIndex}][labourers][${labourerIndex}][morning_attendance]" value="absent" ${morningAttendance === 'absent' ? 'checked' : ''}>
                                Absent
                            </label>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Evening Attendance</label>
                        <div class="attendance-toggle">
                            <label>
                                <input type="radio" name="vendors[${vendorIndex}][labourers][${labourerIndex}][evening_attendance]" value="present" ${eveningAttendance === 'present' ? 'checked' : ''}>
                                Present
                            </label>
                            <label>
                                <input type="radio" name="vendors[${vendorIndex}][labourers][${labourerIndex}][evening_attendance]" value="absent" ${eveningAttendance === 'absent' ? 'checked' : ''}>
                                Absent
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Wages Section -->
                <div class="wages-section">
                    <h6><i class="fas fa-money-bill-wave"></i> Wages</h6>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Daily Wage (₹)</label>
                            <input type="number" step="0.01" class="form-control daily-wage" 
                                name="vendors[${vendorIndex}][labourers][${labourerIndex}][daily_wage]" 
                                value="${wages.perDay || ''}" 
                                onchange="window.calendarEventEditModal.calculateWageTotals(${vendorIndex}, ${labourerIndex})">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Total Day Wage (₹)</label>
                            <input type="number" step="0.01" class="form-control total-day-wage" 
                                name="vendors[${vendorIndex}][labourers][${labourerIndex}][total_day_wage]" 
                                value="${wages.totalDay || ''}" readonly>
                        </div>
                    </div>
                    
                    <!-- Overtime Section -->
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>OT Hours</label>
                            <input type="number" min="0" class="form-control ot-hours" 
                                name="vendors[${vendorIndex}][labourers][${labourerIndex}][ot_hours]" 
                                value="${overtime.hours || '0'}" 
                                onchange="window.calendarEventEditModal.calculateOTAmount(${vendorIndex}, ${labourerIndex})">
                        </div>
                        <div class="form-group col-md-3">
                            <label>OT Minutes</label>
                            <input type="number" min="0" max="59" class="form-control ot-minutes" 
                                name="vendors[${vendorIndex}][labourers][${labourerIndex}][ot_minutes]" 
                                value="${overtime.minutes || '0'}" 
                                onchange="window.calendarEventEditModal.calculateOTAmount(${vendorIndex}, ${labourerIndex})">
                        </div>
                        <div class="form-group col-md-3">
                            <label>OT Rate (₹/hr)</label>
                            <input type="number" step="0.01" class="form-control ot-rate" 
                                name="vendors[${vendorIndex}][labourers][${labourerIndex}][ot_rate]" 
                                value="${overtime.rate || ''}" 
                                onchange="window.calendarEventEditModal.calculateOTAmount(${vendorIndex}, ${labourerIndex})">
                        </div>
                        <div class="form-group col-md-3">
                            <label>OT Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control ot-amount" 
                                name="vendors[${vendorIndex}][labourers][${labourerIndex}][ot_amount]" 
                                value="${overtime.total || '0'}" readonly>
                        </div>
                    </div>
                    
                    <!-- Travel Section -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Travel Mode</label>
                            <select class="form-control" name="vendors[${vendorIndex}][labourers][${labourerIndex}][travel_mode]">
                                <option value="" ${!travel.mode ? 'selected' : ''}>-- Select Mode --</option>
                                <option value="bus" ${travel.mode === 'bus' ? 'selected' : ''}>Bus</option>
                                <option value="train" ${travel.mode === 'train' ? 'selected' : ''}>Train</option>
                                <option value="auto" ${travel.mode === 'auto' ? 'selected' : ''}>Auto Rickshaw</option>
                                <option value="taxi" ${travel.mode === 'taxi' ? 'selected' : ''}>Taxi/Cab</option>
                                <option value="own" ${travel.mode === 'own' ? 'selected' : ''}>Own Vehicle</option>
                                <option value="other" ${travel.mode === 'other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Travel Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control travel-amount" 
                                name="vendors[${vendorIndex}][labourers][${labourerIndex}][travel_amount]" 
                                value="${travel.amount || '0'}" 
                                onchange="window.calendarEventEditModal.calculateGrandTotal(${vendorIndex}, ${labourerIndex})">
                        </div>
                    </div>
                    
                    <!-- Grand Total -->
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label>Grand Total (₹)</label>
                            <input type="number" step="0.01" class="form-control grand-total" 
                                name="vendors[${vendorIndex}][labourers][${labourerIndex}][grand_total]" 
                                value="${wages.grand_total || '0'}" readonly>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById(`laborers-container-${vendorIndex}`).insertAdjacentHTML('beforeend', labourerHTML);
    }

    removeVendorItem(index) {
        const vendorItem = document.getElementById(`vendor-${index}`);
        if (vendorItem) {
            vendorItem.remove();
            this.vendorCounter--;
        }
    }

    removeLabourerItem(vendorIndex, labourerIndex) {
        const labourerItem = document.getElementById(`labour-${labourerIndex}`);
        if (labourerItem) {
            labourerItem.remove();
            this.labourerCounter--;
        }
    }

    addCompanyLabourItem(labourData = null) {
        const labourIndex = this.companyLabourCounter++;
        const labourId = labourData ? labourData.company_labour_id : '';
        const labourName = labourData ? labourData.labour_name : '';
        const contactNumber = labourData ? labourData.contact_number : '';
        const morningAttendance = labourData ? labourData.morning_attendance : 'present';
        const eveningAttendance = labourData ? labourData.evening_attendance : 'present';
        const dailyWage = labourData ? labourData.daily_wage || '' : '';
        
        const labourHTML = `
            <div class="company-labour-item" id="company-labour-${labourIndex}">
                <button type="button" class="remove-item-btn" onclick="window.calendarEventEditModal.removeCompanyLabourItem(${labourIndex})">
                    <i class="fas fa-times"></i>
                </button>
                
                <input type="hidden" name="company_labours[${labourIndex}][company_labour_id]" value="${labourId}">
                
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Worker Name</label>
                        <input type="text" class="form-control" name="company_labours[${labourIndex}][labour_name]" value="${this.escapeHtml(labourName)}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Contact Number</label>
                        <input type="text" class="form-control" name="company_labours[${labourIndex}][contact_number]" value="${this.escapeHtml(contactNumber)}">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Morning Attendance</label>
                        <div class="attendance-toggle">
                            <label>
                                <input type="radio" name="company_labours[${labourIndex}][morning_attendance]" value="present" ${morningAttendance === 'present' ? 'checked' : ''}>
                                Present
                            </label>
                            <label>
                                <input type="radio" name="company_labours[${labourIndex}][morning_attendance]" value="absent" ${morningAttendance === 'absent' ? 'checked' : ''}>
                                Absent
                            </label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Evening Attendance</label>
                        <div class="attendance-toggle">
                            <label>
                                <input type="radio" name="company_labours[${labourIndex}][evening_attendance]" value="present" ${eveningAttendance === 'present' ? 'checked' : ''}>
                                Present
                            </label>
                            <label>
                                <input type="radio" name="company_labours[${labourIndex}][evening_attendance]" value="absent" ${eveningAttendance === 'absent' ? 'checked' : ''}>
                                Absent
                            </label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Daily Wage (₹)</label>
                        <input type="number" step="0.01" class="form-control" name="company_labours[${labourIndex}][daily_wage]" value="${dailyWage}">
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('companyLaboursContainer').insertAdjacentHTML('beforeend', labourHTML);
    }

    removeCompanyLabourItem(index) {
        const labourItem = document.getElementById(`company-labour-${index}`);
        if (labourItem) {
            labourItem.remove();
            this.companyLabourCounter--;
        }
    }

    addBeverageItem(beverageData = null) {
        const beverageIndex = this.beverageCounter++;
        const beverageId = beverageData ? beverageData.beverage_id : '';
        const beverageType = beverageData ? beverageData.beverage_type : '';
        const beverageName = beverageData ? beverageData.beverage_name : '';
        const amount = beverageData ? beverageData.amount : '';
        
        const beverageHTML = `
            <div class="beverage-item" id="beverage-${beverageIndex}">
                <button type="button" class="remove-item-btn" onclick="window.calendarEventEditModal.removeBeverageItem(${beverageIndex})">
                    <i class="fas fa-times"></i>
                </button>
                
                <input type="hidden" name="beverages[${beverageIndex}][beverage_id]" value="${beverageId}">
                
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Beverage Type</label>
                        <input type="text" class="form-control" name="beverages[${beverageIndex}][beverage_type]" value="${this.escapeHtml(beverageType)}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Beverage Name</label>
                        <input type="text" class="form-control" name="beverages[${beverageIndex}][beverage_name]" value="${this.escapeHtml(beverageName)}">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Amount (₹)</label>
                        <input type="number" step="0.01" class="form-control" name="beverages[${beverageIndex}][amount]" value="${amount}">
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('beveragesContainer').insertAdjacentHTML('beforeend', beverageHTML);
    }

    addWorkProgressItem(workData = null) {
        const workIndex = this.workProgressCounter++;
        const workId = workData ? workData.work_id : '';
        const workCategory = workData ? workData.work_category : '';
        const workType = workData ? workData.work_type : '';
        const workDone = workData ? workData.work_done : 'yes';
        const remarks = workData ? workData.remarks : '';
        
        const workHTML = `
            <div class="work-progress-item" id="work-progress-${workIndex}">
                <button type="button" class="remove-item-btn" onclick="window.calendarEventEditModal.removeWorkProgressItem(${workIndex})">
                    <i class="fas fa-times"></i>
                </button>
                
                <input type="hidden" name="work_progress[${workIndex}][work_id]" value="${workId}">
                
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Work Category</label>
                        <input type="text" class="form-control" name="work_progress[${workIndex}][work_category]" value="${this.escapeHtml(workCategory)}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Work Type</label>
                        <input type="text" class="form-control" name="work_progress[${workIndex}][work_type]" value="${this.escapeHtml(workType)}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Status</label>
                        <div class="attendance-toggle">
                            <label>
                                <input type="radio" name="work_progress[${workIndex}][work_done]" value="yes" ${workDone === 'yes' ? 'checked' : ''}>
                                Completed
                            </label>
                            <label>
                                <input type="radio" name="work_progress[${workIndex}][work_done]" value="no" ${workDone === 'no' ? 'checked' : ''}>
                                Not Completed
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label>Remarks</label>
                        <textarea class="form-control" name="work_progress[${workIndex}][remarks]">${this.escapeHtml(remarks)}</textarea>
                    </div>
                </div>
                
                <!-- Work Progress Media -->
                <div class="form-group">
                    <label>Media Files (Images/Videos)</label>
                    <div class="media-upload-container">
                        <input type="file" class="form-control-file" name="work_progress[${workIndex}][media][]" multiple accept="image/*,video/*">
                        ${this.renderExistingWorkMedia(workData, workIndex)}
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('workProgressContainer').insertAdjacentHTML('beforeend', workHTML);
    }

    removeWorkProgressItem(index) {
        const workItem = document.getElementById(`work-progress-${index}`);
        if (workItem) {
            workItem.remove();
            this.workProgressCounter--;
        }
    }

    addInventoryItem(inventoryData = null) {
        const inventoryIndex = this.inventoryCounter++;
        const inventoryId = inventoryData ? inventoryData.inventory_id : '';
        const inventoryType = inventoryData ? inventoryData.inventory_type : 'received';
        const materialType = inventoryData ? inventoryData.material_type : '';
        const quantity = inventoryData ? inventoryData.quantity : '';
        const unit = inventoryData ? inventoryData.unit : '';
        const remarks = inventoryData ? inventoryData.remarks : '';
        
        const inventoryHTML = `
            <div class="inventory-item" id="inventory-${inventoryIndex}">
                <button type="button" class="remove-item-btn" onclick="window.calendarEventEditModal.removeInventoryItem(${inventoryIndex})">
                    <i class="fas fa-times"></i>
                </button>
                
                <input type="hidden" name="inventory[${inventoryIndex}][inventory_id]" value="${inventoryId}">
                
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Inventory Type</label>
                        <select class="form-control" name="inventory[${inventoryIndex}][inventory_type]" required>
                            <option value="received" ${inventoryType === 'received' ? 'selected' : ''}>Received</option>
                            <option value="consumed" ${inventoryType === 'consumed' ? 'selected' : ''}>Consumed</option>
                            <option value="other" ${inventoryType === 'other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Material Type</label>
                        <input type="text" class="form-control" name="inventory[${inventoryIndex}][material_type]" value="${this.escapeHtml(materialType)}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Quantity</label>
                        <input type="number" step="0.01" class="form-control" name="inventory[${inventoryIndex}][quantity]" value="${quantity}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Unit</label>
                        <input type="text" class="form-control" name="inventory[${inventoryIndex}][unit]" value="${this.escapeHtml(unit)}">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label>Remarks</label>
                        <textarea class="form-control" name="inventory[${inventoryIndex}][remarks]">${this.escapeHtml(remarks)}</textarea>
                    </div>
                </div>
                
                <!-- Inventory Media -->
                <div class="form-group">
                    <label>Media Files (Images/Bills/Videos)</label>
                    <div class="media-upload-container">
                        <input type="file" class="form-control-file" name="inventory[${inventoryIndex}][media][]" multiple accept="image/*,video/*,application/pdf">
                        ${this.renderExistingInventoryMedia(inventoryData, inventoryIndex)}
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('inventoryContainer').insertAdjacentHTML('beforeend', inventoryHTML);
    }

    removeInventoryItem(index) {
        const inventoryItem = document.getElementById(`inventory-${index}`);
        if (inventoryItem) {
            inventoryItem.remove();
            this.inventoryCounter--;
        }
    }

    renderExistingMedia(vendorData, mediaType, vendorIndex, mediaCategory) {
        if (!vendorData || !vendorData.material || !vendorData.material[mediaType] || !vendorData.material[mediaType].length) {
            return '';
        }
        
        const media = vendorData.material[mediaType];
        let html = '<div class="existing-media-container">';
        
        media.forEach((item, index) => {
            html += `
                <div class="media-item">
                    <img src="uploads/${mediaCategory === 'material' ? 'material_images' : 'bill_images'}/${item.name}" alt="${item.name}">
                    <input type="hidden" name="vendors[${vendorIndex}][existing_${mediaCategory}_media][]" value="${item.name}">
                    <button type="button" class="remove-media-btn" onclick="window.calendarEventEditModal.removeMedia(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }

    renderExistingWorkMedia(workData, workIndex) {
        if (!workData || !workData.media || !workData.media.length) {
            return '';
        }
        
        const media = workData.media;
        let html = '<div class="existing-media-container">';
        
        media.forEach((item, index) => {
            const isImage = item.media_type === 'image';
            const mediaPath = item.file_path || `uploads/work_progress/${item.file_name}`;
            
            html += `
                <div class="media-item">
                    ${isImage ? 
                    `<img src="${mediaPath}" alt="${item.file_name}">` : 
                    `<div class="video-thumbnail"><i class="fas fa-video"></i></div>`}
                    <input type="hidden" name="work_progress[${workIndex}][existing_media][]" value="${item.file_name}">
                    <input type="hidden" name="work_progress[${workIndex}][existing_media_type][]" value="${item.media_type}">
                    <button type="button" class="remove-media-btn" onclick="window.calendarEventEditModal.removeMedia(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }

    renderExistingInventoryMedia(inventoryData, inventoryIndex) {
        if (!inventoryData || !inventoryData.media || !inventoryData.media.length) {
            return '';
        }
        
        const media = inventoryData.media;
        let html = '<div class="existing-media-container">';
        
        media.forEach((item, index) => {
            const isImage = item.media_type === 'photo' || item.media_type === 'bill';
            const isPdf = item.file_name.toLowerCase().endsWith('.pdf');
            
            let thumbnailContent;
            if (isPdf) {
                thumbnailContent = `<div class="pdf-thumbnail"><i class="fas fa-file-pdf"></i></div>`;
            } else if (isImage) {
                const mediaPath = item.file_path || `uploads/inventory_${item.media_type === 'bill' ? 'bills' : 'images'}/${item.file_name}`;
                thumbnailContent = `<img src="${mediaPath}" alt="${item.file_name}">`;
            } else {
                thumbnailContent = `<div class="video-thumbnail"><i class="fas fa-video"></i></div>`;
            }
            
            html += `
                <div class="media-item">
                    ${thumbnailContent}
                    <input type="hidden" name="inventory[${inventoryIndex}][existing_media][]" value="${item.file_name}">
                    <input type="hidden" name="inventory[${inventoryIndex}][existing_media_type][]" value="${item.media_type}">
                    <button type="button" class="remove-media-btn" onclick="window.calendarEventEditModal.removeMedia(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }

    removeMedia(button) {
        const mediaItem = button.closest('.media-item');
        if (mediaItem) {
            if (confirm('Are you sure you want to remove this media file?')) {
                mediaItem.remove();
            }
        }
    }

    escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    showErrorMessage(message) {
        const errorDiv = document.getElementById('editModalError');
        const errorText = document.getElementById('editModalErrorText');
        errorText.textContent = message;
        errorDiv.style.display = 'block';
    }

    async saveEventChanges() {
        try {
            // Show loading state
            document.getElementById('saveEventBtn').disabled = true;
            document.getElementById('saveEventBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            // Get the form and create FormData object
            const form = document.getElementById('eventEditForm');
            const formData = new FormData(form);
            
            // Add action parameter
            formData.append('action', 'update_event');
            
            // Send the data to server
            const response = await fetch('backend/update_calendar_event.php', {
                method: 'POST',
                body: formData
            });
            
            // Parse response
            const result = await response.json();
            
            if (result.status === 'success') {
                // Close the modal
                this.hideModal();
                
                // Show success message
                alert('Event updated successfully!');
                
                // Refresh the calendar
                if (typeof refreshCalendar === 'function') {
                    refreshCalendar();
                } else if (typeof loadCalendarEvents === 'function') {
                    loadCalendar();
                } else {
                    // Fallback to reloading the page
                    window.location.reload();
                }
            } else {
                // Show error message
                alert(`Error: ${result.message || 'Unknown error occurred while updating the event.'}`);
                
                // Re-enable save button
                document.getElementById('saveEventBtn').disabled = false;
                document.getElementById('saveEventBtn').innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }
        } catch (error) {
            console.error('Error saving event changes:', error);
            alert(`An error occurred while saving: ${error.message}`);
            
            // Re-enable save button
            document.getElementById('saveEventBtn').disabled = false;
            document.getElementById('saveEventBtn').innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    }

    // Helper method to escape HTML
    escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}

// Initialize the modal when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.calendarEventEditModal = new CalendarEventEditModal();
    
    // Define the global function to open the modal from other scripts
    window.openEventEditModal = function(eventId) {
        window.calendarEventEditModal.showModal(eventId);
    };
});
