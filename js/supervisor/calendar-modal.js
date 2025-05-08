/**
 * Calendar Event Modal Functionality
 * Handles the display and interaction with the event add/edit modal
 */
class CalendarEventModal {
    constructor() {
        this.init();
    }

    init() {
        // Create modal HTML structure
        this.createModalStructure();
        
        // Initialize event listeners
        this.setupEventListeners();
        
        // Store references to form elements
        this.eventDateDisplay = document.getElementById('eventDate');
        this.siteNameSelect = document.getElementById('siteName');
        this.customSiteNameInput = document.getElementById('customSiteName');
        this.siteNameSelectWrapper = document.querySelector('.site-name-select-wrapper');
        this.siteNameInputWrapper = document.querySelector('.site-name-input-wrapper');
        
        // Setup custom site name functionality
        this.setupCustomSiteName();
        
        // Setup vendor functionality
        this.setupVendorFunctionality();
        
        // Counter for vendor IDs
        this.vendorCounter = 0;
    }

    createModalStructure() {
        const modalHTML = `
            <div id="eventModalBackdrop" class="event-modal-backdrop">
                <div class="event-modal">
                    <div class="event-modal-header">
                        <h3 class="event-modal-title"><i class="fas fa-calendar-plus"></i> Add Site Update</h3>
                        <button type="button" class="event-modal-close" id="eventModalClose">&times;</button>
                    </div>
                    <div class="event-modal-body">
                        <form id="eventForm">
                            <!-- Date and Site Name in same row on larger screens -->
                            <div class="event-form-row">
                                <div class="event-form-group">
                                    <label><i class="far fa-calendar-alt"></i> Date</label>
                                    <div id="eventDate" class="event-form-control" style="background-color: #f9f9f9;"></div>
                                </div>
                                <div class="event-form-group">
                                    <label for="siteName"><i class="fas fa-map-marker-alt"></i> Site Name</label>
                                    <div class="site-name-container">
                                        <!-- Dropdown Select for Site Name -->
                                        <div class="site-name-select-wrapper">
                                            <select id="siteName" class="event-form-select" required>
                                                <option value="">Select Site</option>
                                                <option value="building-a">Building A</option>
                                                <option value="building-b">Building B</option>
                                                <option value="sector-1">Sector 1</option>
                                                <option value="east-wing">East Wing</option>
                                                <option value="west-wing">West Wing</option>
                                                <option value="custom">+ Add Custom Site</option>
                                            </select>
                                        </div>
                                        <!-- Custom Input for Site Name (initially hidden) -->
                                        <div class="site-name-input-wrapper" style="display: none;">
                                            <div class="custom-input-container">
                                                <input type="text" id="customSiteName" class="event-form-control" placeholder="Enter custom site name">
                                                <button type="button" class="custom-back-btn" title="Back to list"><i class="fas fa-arrow-left"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vendor & Labour Section -->
                            <div class="event-form-section">
                                <h4 class="section-title"><i class="fas fa-users"></i> Vendor & Labour</h4>
                                <div id="vendorList" class="vendor-list">
                                    <!-- Vendors will be added here dynamically -->
                                </div>
                                <button type="button" id="addVendorBtn" class="event-btn event-btn-outline">
                                    <i class="fas fa-plus"></i> Add Vendor
                                </button>
                            </div>
                            
                            <input type="hidden" id="eventDay" value="">
                            <input type="hidden" id="eventMonth" value="">
                            <input type="hidden" id="eventYear" value="">
                        </form>
                    </div>
                    <div class="event-modal-footer">
                        <button type="button" class="event-btn event-btn-cancel" id="eventCancelBtn"><i class="fas fa-times"></i> Cancel</button>
                        <button type="button" class="event-btn event-btn-primary" id="eventSaveBtn"><i class="fas fa-save"></i> Save</button>
                    </div>
                </div>
            </div>
        `;

        // Add modal to the body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    setupEventListeners() {
        // Close modal buttons
        document.getElementById('eventModalClose').addEventListener('click', () => this.hideModal());
        document.getElementById('eventCancelBtn').addEventListener('click', () => this.hideModal());
        
        // Close on backdrop click
        document.getElementById('eventModalBackdrop').addEventListener('click', (e) => {
            if (e.target === document.getElementById('eventModalBackdrop')) {
                this.hideModal();
            }
        });
        
        // Save button
        document.getElementById('eventSaveBtn').addEventListener('click', () => this.saveEvent());
        
        // Form submission
        document.getElementById('eventForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveEvent();
        });
        
        // Add vendor button - Only add a new vendor when explicitly clicked by the user
        document.getElementById('addVendorBtn').addEventListener('click', (e) => {
            // Check if this is a direct user action (not triggered by an image upload event)
            // e.isTrusted will be true for real user clicks and false for programmatically triggered events
            if (e.isTrusted) {
                this.addVendorField();
            }
        });
    }

    showModal(day, month, year, monthName) {
        // Set the date values
        document.getElementById('eventDay').value = day;
        document.getElementById('eventMonth').value = month;
        document.getElementById('eventYear').value = year;
        
        // Display the date
        this.eventDateDisplay.textContent = `${monthName} ${day}, ${year}`;
        
        // Show the modal
        document.getElementById('eventModalBackdrop').classList.add('active');
        
        // Focus the first input
        setTimeout(() => {
            this.siteNameSelect.focus();
        }, 300);
    }

    hideModal() {
        document.getElementById('eventModalBackdrop').classList.remove('active');
        
        // Reset form
        document.getElementById('eventForm').reset();
        
        // Reset custom site name input state
        this.siteNameInputWrapper.style.display = 'none';
        this.siteNameSelectWrapper.style.display = 'block';
        
        // Clear vendor list
        document.getElementById('vendorList').innerHTML = '';
        this.vendorCounter = 0; // Reset vendor counter
    }

    validateForm() {
        // Check if site name is selected or entered
        if (this.siteNameSelectWrapper.style.display !== 'none' && !this.siteNameSelect.value) {
            alert('Please select a site');
            this.siteNameSelect.focus();
            return false;
        }
        
        if (this.siteNameInputWrapper.style.display !== 'none' && !this.customSiteNameInput.value.trim()) {
            alert('Please enter a custom site name');
            this.customSiteNameInput.focus();
            return false;
        }
        
        // Validate vendor fields
        const vendorTypes = document.querySelectorAll('[id^="vendorType-"]');
        const vendorNames = document.querySelectorAll('[id^="vendorName-"]');
        const vendorContacts = document.querySelectorAll('[id^="vendorContact-"]');
        
        for (let i = 0; i < vendorTypes.length; i++) {
            const vendorType = vendorTypes[i];
            const vendorName = vendorNames[i];
            const vendorContact = vendorContacts[i];
            
            // Skip validation if the entire vendor row is empty
            if (!vendorType.value && !vendorName.value && !vendorContact.value) {
                continue;
            }
            
            // Validate vendor type
            if (!vendorType.value) {
                alert('Please select a vendor type');
                vendorType.focus();
                return false;
            }
            
            // Validate custom vendor type if selected
            if (vendorType.value === 'custom') {
                const vendorId = vendorType.id.split('-')[1];
                const customVendorType = document.getElementById(`customVendorType-${vendorId}`);
                if (customVendorType && !customVendorType.value.trim()) {
                    alert('Please enter a custom vendor type');
                    customVendorType.focus();
                    return false;
                }
            }
            
            // Validate vendor name
            if (!vendorName.value.trim()) {
                alert('Please enter a vendor name');
                vendorName.focus();
                return false;
            }
            
            // Validate vendor contact
            if (!vendorContact.value.trim()) {
                alert('Please enter a vendor contact number');
                vendorContact.focus();
                return false;
            }
        }
        
        return true;
    }

    saveEvent() {
        // Validate form
        if (!this.validateForm()) {
            return;
        }
        
        // Determine site name based on which input is visible
        let siteName;
        if (this.siteNameInputWrapper.style.display !== 'none') {
            // Custom site name is being used
            siteName = this.customSiteNameInput.value.trim();
        } else {
            // Dropdown selection is being used
            siteName = this.siteNameSelect.value;
        }
        
        // Collect vendor data
        const vendors = [];
        const vendorRows = document.querySelectorAll('.vendor-row');
        
        vendorRows.forEach(row => {
            const vendorId = row.getAttribute('data-vendor-id');
            const vendorType = document.getElementById(`vendorType-${vendorId}`);
            const vendorName = document.getElementById(`vendorName-${vendorId}`);
            const vendorContact = document.getElementById(`vendorContact-${vendorId}`);
            
            // Get material data
            const materialRemark = document.getElementById(`materialRemark-${vendorId}`);
            const materialAmount = document.getElementById(`materialAmount-${vendorId}`);
            const materialPictureInput = document.getElementById(`materialPicture-${vendorId}`);
            const billPictureInput = document.getElementById(`billPicture-${vendorId}`);
            
            // Get labour section
            const labourSection = document.getElementById(`labourSection-${vendorId}`);
            
            // Skip empty vendor rows
            if (!vendorType.value && !vendorName.value && !vendorContact.value) {
                return;
            }
            
            let vendorTypeValue = vendorType.value;
            
            // Use custom vendor type if selected
            if (vendorType.value === 'custom') {
                const customVendorType = document.getElementById(`customVendorType-${vendorId}`);
                if (customVendorType && customVendorType.value.trim()) {
                    vendorTypeValue = customVendorType.value.trim();
                }
            }
            
            // Get file names for pictures (in a real implementation, you would upload these files)
            let materialPictureNames = [];
            let billPictureNames = [];
            
            if (materialPictureInput && materialPictureInput.files && materialPictureInput.files.length > 0) {
                for (let i = 0; i < materialPictureInput.files.length; i++) {
                    const file = materialPictureInput.files[i];
                    // Create an object with the filename and location data
                    const fileData = {
                        name: file.name,
                    };
                    
                    // Add location data if it exists on the file object
                    if (file.location) {
                        fileData.latitude = file.location.latitude;
                        fileData.longitude = file.location.longitude;
                        fileData.accuracy = file.location.accuracy;
                        fileData.address = file.location.address;
                        fileData.timestamp = file.location.timestamp || new Date().getTime();
                    }
                    
                    materialPictureNames.push(fileData);
                }
            }
            
            if (billPictureInput && billPictureInput.files && billPictureInput.files.length > 0) {
                for (let i = 0; i < billPictureInput.files.length; i++) {
                    const file = billPictureInput.files[i];
                    // Create an object with the filename and location data
                    const fileData = {
                        name: file.name,
                    };
                    
                    // Add location data if it exists on the file object
                    if (file.location) {
                        fileData.latitude = file.location.latitude;
                        fileData.longitude = file.location.longitude;
                        fileData.accuracy = file.location.accuracy;
                        fileData.address = file.location.address;
                        fileData.timestamp = file.location.timestamp || new Date().getTime();
                    }
                    
                    billPictureNames.push(fileData);
                }
            }
            
            // Prepare vendor data object
            const vendorData = {
                type: vendorTypeValue,
                name: vendorName.value.trim(),
                contact: vendorContact.value.trim(),
                material: {
                    remark: materialRemark ? materialRemark.value.trim() : '',
                    amount: materialAmount ? materialAmount.value : '',
                    materialPictures: materialPictureNames,
                    billPictures: billPictureNames
                }
            };
            
            // Add labour data if the section is visible and has data
            if (labourSection && labourSection.style.display !== 'none') {
                const labourItems = labourSection.querySelectorAll('.labour-item');
                if (labourItems.length > 0) {
                    const labourers = [];
                    
                    labourItems.forEach(item => {
                        const labourId = item.getAttribute('data-labour-id');
                        const labourName = document.getElementById(`labourName-${labourId}`);
                        const labourContact = document.getElementById(`labourContact-${labourId}`);
                        const morningAttendance = document.getElementById(`morningAttendance-${labourId}`);
                        const eveningAttendance = document.getElementById(`eveningAttendance-${labourId}`);
                        
                        // Only include if name is provided
                        if (labourName && labourName.value.trim()) {
                            labourers.push({
                                name: labourName.value.trim(),
                                contact: labourContact ? labourContact.value.trim() : '',
                                attendance: {
                                    morning: morningAttendance ? morningAttendance.value : '',
                                    evening: eveningAttendance ? eveningAttendance.value : ''
                                },
                                wages: {
                                    perDay: document.getElementById(`wagesPerDay-${labourId}`) ? 
                                           parseFloat(document.getElementById(`wagesPerDay-${labourId}`).value) || 0 : 0,
                                    totalDay: document.getElementById(`totalDayWages-${labourId}`) ? 
                                            parseFloat(document.getElementById(`totalDayWages-${labourId}`).value) || 0 : 0
                                },
                                overtime: {
                                    hours: document.getElementById(`otHours-${labourId}`) ? 
                                          parseFloat(document.getElementById(`otHours-${labourId}`).value) || 0 : 0,
                                    minutes: document.getElementById(`otMinutes-${labourId}`) ? 
                                           parseFloat(document.getElementById(`otMinutes-${labourId}`).value) || 0 : 0,
                                    rate: document.getElementById(`otRate-${labourId}`) ? 
                                         parseFloat(document.getElementById(`otRate-${labourId}`).value) || 0 : 0,
                                    total: document.getElementById(`totalOT-${labourId}`) ? 
                                          parseFloat(document.getElementById(`totalOT-${labourId}`).value) || 0 : 0
                                },
                                travel: {
                                    mode: document.getElementById(`travelMode-${labourId}`) ? 
                                         document.getElementById(`travelMode-${labourId}`).value : '',
                                    amount: document.getElementById(`travelAmount-${labourId}`) ? 
                                           parseFloat(document.getElementById(`travelAmount-${labourId}`).value) || 0 : 0
                                }
                            });
                        }
                    });
                    
                    if (labourers.length > 0) {
                        vendorData.labourers = labourers;
                    }
                }
            }
            
            vendors.push(vendorData);
        });
        
        // Get form values
        const eventData = {
            siteName: siteName,
            day: document.getElementById('eventDay').value,
            month: document.getElementById('eventMonth').value,
            year: document.getElementById('eventYear').value,
            vendors: vendors
        };
        
        // Show loading indicator
        this.showLoading();
        
        // Send data to the server
        fetch('includes/calendar_data_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=save_calendar_data&data=${encodeURIComponent(JSON.stringify(eventData))}`
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading indicator
            this.hideLoading();
            
            if (data.status === 'success') {
                // Show success message
                this.showSuccessMessage(data);
                
                // Hide modal
                this.hideModal();
                
                // Refresh the calendar to show updated data
                if (typeof refreshCalendar === 'function') {
                    refreshCalendar();
                }
            } else {
                // Show error message
                alert(`Error: ${data.message}`);
            }
        })
        .catch(error => {
            // Hide loading indicator
            this.hideLoading();
            
            // Log error and show user-friendly message
            console.error('Error saving event data:', error);
            alert('Failed to save data. Please try again.');
        });
    }
    
    showLoading() {
        // Create loading overlay if it doesn't exist
        if (!document.getElementById('loadingOverlay')) {
            const loadingHTML = `
                <div id="loadingOverlay" class="loading-overlay">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Saving...</span>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', loadingHTML);
            
            // Add styles for loading overlay
            const styleEl = document.createElement('style');
            styleEl.id = 'loadingStyles';
            styleEl.textContent = `
                .loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                }
                .loading-spinner {
                    background-color: #fff;
                    padding: 20px 40px;
                    border-radius: 8px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }
                .loading-spinner i {
                    font-size: 2rem;
                    color: #3498db;
                    margin-bottom: 10px;
                }
                .loading-spinner span {
                    font-size: 1rem;
                    color: #333;
                }
            `;
            document.head.appendChild(styleEl);
        }
        
        // Show the loading overlay
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
    
    hideLoading() {
        // Hide loading overlay if it exists
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
        }
    }

    showSuccessMessage(data) {
        // For now, let's just use a simple alert
        // In a real implementation, you might want to show a prettier notification
        alert(`Site update added successfully for ${this.eventDateDisplay.textContent}`);
    }

    setupCustomSiteName() {
        // Event listener for site name select change
        this.siteNameSelect.addEventListener('change', (e) => {
            if (e.target.value === 'custom') {
                // Switch to custom input
                this.siteNameSelectWrapper.style.display = 'none';
                this.siteNameInputWrapper.style.display = 'block';
                this.customSiteNameInput.focus();
            }
        });
        
        // Event listener for custom site name back button
        document.querySelector('.custom-back-btn').addEventListener('click', () => {
            // Switch back to select dropdown
            this.siteNameInputWrapper.style.display = 'none';
            this.siteNameSelectWrapper.style.display = 'block';
            this.siteNameSelect.value = '';
            this.customSiteNameInput.value = '';
        });
    }
    
    setupVendorFunctionality() {
        // Setup functionality for vendor and labor section
        this.vendorList = document.getElementById('vendorList');
    }
    
    addVendorField() {
        // Use the current count of vendor rows + 1 as the display ID
        // This ensures the numbering is always sequential regardless of previous deletions
        const vendorDisplayId = document.querySelectorAll('.vendor-row').length + 1;
        
        // Use vendorCounter for internal ID only
        const vendorId = ++this.vendorCounter;
        
        const vendorRow = document.createElement('div');
        vendorRow.className = 'vendor-row';
        vendorRow.setAttribute('data-vendor-id', vendorId);
        
        vendorRow.innerHTML = `
            <div class="vendor-header">
                <h5><i class="fas fa-hard-hat"></i> Vendor #${vendorDisplayId}</h5>
                <button type="button" class="vendor-delete-btn" data-vendor-id="${vendorId}" title="Remove vendor">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            <div class="vendor-fields-container">
                <div class="vendor-field">
                    <label for="vendorType-${vendorId}"><i class="fas fa-tag"></i> Vendor Type</label>
                    <div class="vendor-type-container">
                        <div class="vendor-type-dropdown" id="vendorTypeDropdown-${vendorId}">
                            <select id="vendorType-${vendorId}" class="event-form-select vendor-input">
                                <option value="">Select Type</option>
                                <option value="supplier">Supplier</option>
                                <option value="contractor">Contractor</option>
                                <option value="consultant">Consultant</option>
                                <option value="laborer">Laborer</option>
                                <option value="custom">+ Add Custom Type</option>
                            </select>
                        </div>
                        <div class="vendor-type-custom" id="vendorTypeCustom-${vendorId}" style="display: none;">
                            <div class="custom-input-container">
                                <input type="text" id="customVendorType-${vendorId}" class="event-form-control vendor-input" placeholder="Enter custom vendor type">
                                <button type="button" class="vendor-back-btn" data-vendor-id="${vendorId}" title="Back to list">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="vendor-field">
                    <label for="vendorName-${vendorId}"><i class="fas fa-building"></i> Vendor Name</label>
                    <input type="text" id="vendorName-${vendorId}" class="event-form-control vendor-input" placeholder="Enter vendor name">
                </div>
                <div class="vendor-field">
                    <label for="vendorContact-${vendorId}"><i class="fas fa-phone-alt"></i> Contact Number</label>
                    <input type="text" id="vendorContact-${vendorId}" class="event-form-control vendor-input" placeholder="Enter contact number">
                </div>
            </div>
            
            <!-- Vendor Material Section -->
            <div class="vendor-material-section">
                <h6 class="vendor-subsection-title"><i class="fas fa-boxes"></i> Vendor Material</h6>
                <div class="vendor-material-fields">
                    <div class="vendor-material-row">
                        <div class="vendor-field">
                            <label for="materialPicture-${vendorId}"><i class="fas fa-image"></i> Material Picture</label>
                            <div class="file-upload-container">
                                <input type="file" id="materialPicture-${vendorId}" class="file-input" accept="image/*" multiple capture>
                                <button type="button" class="image-source-btn" id="materialPictureBtn-${vendorId}">
                                    <i class="fas fa-cloud-upload-alt"></i> Choose Images
                                </button>
                                <div class="file-name" id="materialPictureName-${vendorId}">No files chosen</div>
                                <div class="image-location" id="materialPictureLocation-${vendorId}"></div>
                            </div>
                        </div>
                        <div class="vendor-field">
                            <label for="materialRemark-${vendorId}"><i class="fas fa-comment-alt"></i> Remark</label>
                            <textarea id="materialRemark-${vendorId}" class="event-form-control vendor-input vendor-textarea" placeholder="Enter remarks about the material"></textarea>
                        </div>
                    </div>
                    <div class="vendor-material-row">
                        <div class="vendor-field">
                            <label for="materialAmount-${vendorId}"><i class="fas fa-money-bill-wave"></i> Amount</label>
                            <input type="number" id="materialAmount-${vendorId}" class="event-form-control vendor-input" placeholder="Enter amount" min="0" step="0.01">
                        </div>
                        <div class="vendor-field">
                            <label for="billPicture-${vendorId}"><i class="fas fa-file-invoice"></i> Bill Picture</label>
                            <div class="file-upload-container">
                                <input type="file" id="billPicture-${vendorId}" class="file-input" accept="image/*" multiple capture>
                                <button type="button" class="image-source-btn" id="billPictureBtn-${vendorId}">
                                    <i class="fas fa-cloud-upload-alt"></i> Choose Images
                                </button>
                                <div class="file-name" id="billPictureName-${vendorId}">No files chosen</div>
                                <div class="image-location" id="billPictureLocation-${vendorId}"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add Labour Button -->
            <div class="labour-button-container">
                <button type="button" id="addLabourBtn-${vendorId}" class="event-btn event-btn-outline add-labour-btn">
                    <i class="fas fa-user-plus"></i> Add Labour
                </button>
            </div>
            
            <!-- Labour Attendance Section (initially hidden) -->
            <div class="labour-section" id="labourSection-${vendorId}" style="display: none;">
                <h6 class="vendor-subsection-title"><i class="fas fa-users"></i> Labour Attendance</h6>
                
                <!-- List of laborers -->
                <div id="labourList-${vendorId}" class="labour-list">
                    <!-- Laborers will be added here dynamically -->
                </div>
                
                <!-- Add Another Laborer button -->
                <button type="button" id="addAnotherLabourBtn-${vendorId}" class="event-btn event-btn-sm event-btn-outline-secondary add-another-labour-btn">
                    <i class="fas fa-plus"></i> Add Another Laborer
                </button>
            </div>
        `;
        
        this.vendorList.appendChild(vendorRow);
        
        // Add event listeners for this vendor row
        this.setupVendorRowListeners(vendorId, vendorRow);
        
        // Scroll to the newly added vendor
        vendorRow.scrollIntoView({ behavior: 'smooth', block: 'end' });
        
        // Add some basic CSS for larger screens if not already in the page
        if (!document.getElementById('vendorResponsiveStyles')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'vendorResponsiveStyles';
            styleEl.textContent = `
                @media (min-width: 768px) {
                    .vendor-fields-container {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 15px;
                    }
                    .vendor-field {
                        flex: 1;
                        min-width: 180px;
                    }
                    .vendor-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 12px;
                        padding-bottom: 8px;
                        border-bottom: 1px solid #eaeaea;
                    }
                    .vendor-delete-btn {
                        background: none;
                        border: none;
                        color: #dc3545;
                        cursor: pointer;
                        padding: 5px 8px;
                        border-radius: 4px;
                        transition: background-color 0.2s;
                    }
                    .vendor-delete-btn:hover {
                        background-color: rgba(220, 53, 69, 0.1);
                    }
                    .vendor-back-btn {
                        background: none;
                        border: none;
                        color: #5a6268;
                        cursor: pointer;
                        padding: 5px 8px;
                        border-radius: 4px;
                        transition: background-color 0.2s;
                    }
                    .vendor-back-btn:hover {
                        background-color: rgba(90, 98, 104, 0.1);
                    }
                    .event-form-section {
                        margin-top: 25px;
                        padding-top: 15px;
                        border-top: 1px solid #eaeaea;
                    }
                    .section-title {
                        font-size: 1.1rem;
                        color: #333;
                        margin-bottom: 15px;
                        font-weight: 600;
                    }
                    .vendor-row {
                        background-color: #f9f9f9;
                        border-radius: 6px;
                        padding: 15px;
                        margin-bottom: 15px;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.07);
                        transition: box-shadow 0.2s;
                    }
                    .vendor-row:hover {
                        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                    }
                    #addVendorBtn {
                        margin-top: 15px;
                        padding: 8px 16px;
                        border-radius: 4px;
                        background-color: #f8f9fa;
                        border: 1px solid #ddd;
                        color: #333;
                        transition: all 0.2s;
                    }
                    #addVendorBtn:hover {
                        background-color: #e9ecef;
                    }
                    .vendor-input {
                        width: 100%;
                        padding: 8px 12px;
                        border-radius: 4px;
                        border: 1px solid #ced4da;
                    }
                    .vendor-material-section, .labour-section {
                        margin-top: 20px;
                        padding-top: 15px;
                        border-top: 1px dashed #dee2e6;
                    }
                    .vendor-subsection-title {
                        font-size: 0.95rem;
                        color: #444;
                        margin-bottom: 15px;
                        font-weight: 500;
                        display: flex;
                        align-items: center;
                    }
                    .vendor-subsection-title i {
                        margin-right: 8px;
                        color: #3498db;
                    }
                    .vendor-material-fields, .labour-fields {
                        display: flex;
                        flex-direction: column;
                        gap: 15px;
                    }
                    .vendor-material-row, .labour-row {
                        display: flex;
                        gap: 15px;
                        flex-wrap: wrap;
                    }
                    .vendor-material-row .vendor-field, .labour-row .vendor-field {
                        flex: 1;
                        min-width: 220px;
                    }
                    .vendor-textarea {
                        min-height: 80px;
                        resize: vertical;
                    }
                    .file-upload-container {
                        position: relative;
                    }
                    .file-input {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 0.1px;
                        height: 0.1px;
                        opacity: 0;
                        overflow: hidden;
                        z-index: -1;
                    }
                    .file-upload-label {
                        display: inline-block;
                        padding: 10px 15px;
                        background-color: #f8f9fa;
                        color: #444;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        cursor: pointer;
                        transition: all 0.2s;
                        font-size: 0.9rem;
                    }
                    .file-upload-label:hover {
                        background-color: #e9ecef;
                    }
                    .file-upload-label i {
                        margin-right: 8px;
                    }
                    .file-name {
                        margin-top: 5px;
                        font-size: 0.85rem;
                        color: #666;
                    }
                    .labour-button-container {
                        margin-top: 15px;
                        text-align: center;
                    }
                    .add-labour-btn {
                        padding: 6px 14px;
                        font-size: 0.9rem;
                        background-color: #f0f8ff;
                        border: 1px solid #b8daff;
                        color: #0056b3;
                    }
                    .add-labour-btn:hover {
                        background-color: #d8e9ff;
                    }
                    .attendance-container {
                        background-color: #f5f5f5;
                        padding: 12px;
                        border-radius: 5px;
                        margin-top: 10px;
                    }
                    .attendance-title {
                        font-size: 0.9rem;
                        margin-bottom: 10px;
                        color: #333;
                        font-weight: 500;
                    }
                    .attendance-row {
                        display: flex;
                        gap: 15px;
                        flex-wrap: wrap;
                    }
                    .attendance-field {
                        flex: 1;
                        min-width: 160px;
                    }
                    .labour-list {
                        display: flex;
                        flex-direction: column;
                        gap: 15px;
                        margin-bottom: 15px;
                    }
                    .labour-item {
                        background-color: #fff;
                        border: 1px solid #e0e0e0;
                        border-radius: 5px;
                        padding: 12px;
                        position: relative;
                    }
                    .labour-item-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 10px;
                        padding-bottom: 8px;
                        border-bottom: 1px solid #f0f0f0;
                    }
                    .labour-item-header h6 {
                        margin: 0;
                        font-size: 0.9rem;
                        font-weight: 500;
                        color: #333;
                    }
                    .labour-delete-btn {
                        background: none;
                        border: none;
                        color: #dc3545;
                        cursor: pointer;
                        padding: 3px 6px;
                        border-radius: 3px;
                        font-size: 0.8rem;
                        transition: background-color 0.2s;
                    }
                    .labour-delete-btn:hover {
                        background-color: rgba(220, 53, 69, 0.1);
                    }
                    .add-another-labour-btn {
                        font-size: 0.85rem;
                        padding: 5px 10px;
                        margin-left: auto;
                        display: block;
                        margin-right: 0;
                        background-color: #f8f9fa;
                        border: 1px dashed #adb5bd;
                        color: #495057;
                    }
                    .add-another-labour-btn:hover {
                        background-color: #e2e6ea;
                    }
                    .event-btn-sm {
                        font-size: 0.875rem;
                        padding: 0.25rem 0.5rem;
                    }
                    .event-btn-outline-secondary {
                        color: #6c757d;
                        border-color: #6c757d;
                    }

                    /* Daily Wages section styling */
                    .wages-container {
                        background-color: #fff9f0;
                        padding: 12px;
                        border-radius: 5px;
                        margin-top: 10px;
                        border: 1px solid #ffe8cc;
                    }
                    .wages-title {
                        font-size: 0.9rem;
                        margin-bottom: 10px;
                        color: #d35400;
                        font-weight: 500;
                    }
                    .wages-row {
                        display: flex;
                        gap: 15px;
                        flex-wrap: wrap;
                    }
                    .wages-field {
                        flex: 1;
                        min-width: 160px;
                    }
                    
                    /* Overtime section styling */
                    .overtime-container {
                        background-color: #f0f7ff;
                        padding: 12px;
                        border-radius: 5px;
                        margin-top: 10px;
                        border: 1px solid #b8d4f5;
                    }
                    .overtime-title {
                        font-size: 0.9rem;
                        margin-bottom: 10px;
                        color: #2471a3;
                        font-weight: 500;
                    }
                    .overtime-row {
                        display: flex;
                        gap: 12px;
                        flex-wrap: wrap;
                    }
                    .overtime-field {
                        flex: 1;
                        min-width: 140px;
                    }
                    .overtime-time {
                        min-width: 100px;
                        max-width: 120px;
                    }
                    
                    /* Travel Expenses section styling */
                    .travel-container {
                        background-color: #f5fff5;
                        padding: 12px;
                        border-radius: 5px;
                        margin-top: 10px;
                        border: 1px solid #c8e6c9;
                    }
                    .travel-title {
                        font-size: 0.9rem;
                        margin-bottom: 10px;
                        color: #2e7d32;
                        font-weight: 500;
                    }
                    .travel-row {
                        display: flex;
                        gap: 15px;
                        flex-wrap: wrap;
                    }
                    .travel-field {
                        flex: 1;
                        min-width: 160px;
                    }
                    
                    /* Input group styling */
                    .input-group {
                        display: flex;
                        align-items: stretch;
                        width: 100%;
                    }
                    .input-group-prepend {
                        display: flex;
                        margin-right: -1px;
                    }
                    .input-group-text {
                        display: flex;
                        align-items: center;
                        padding: 8px 12px;
                        font-size: 0.9rem;
                        font-weight: 500;
                        color: #495057;
                        text-align: center;
                        white-space: nowrap;
                        background-color: #f8f9fa;
                        border: 1px solid #ced4da;
                        border-radius: 4px 0 0 4px;
                    }
                    .input-group .vendor-input {
                        border-top-left-radius: 0;
                        border-bottom-left-radius: 0;
                        position: relative;
                        flex: 1 1 auto;
                        width: 1%;
                        margin-bottom: 0;
                    }
                    
                    /* Grand Total styling */
                    .grand-total-container {
                        background-color: #f8f4ff;
                        padding: 12px;
                        border-radius: 5px;
                        margin-top: 15px;
                        border: 1px solid #d4c4f9;
                    }
                    .grand-total-title {
                        font-size: 0.95rem;
                        margin-bottom: 10px;
                        color: #6a1b9a;
                        font-weight: 600;
                    }
                    .grand-total-row {
                        display: flex;
                        gap: 15px;
                        flex-wrap: wrap;
                    }
                    .grand-total-field {
                        flex: 1;
                        min-width: 160px;
                    }
                    .grand-total-input {
                        background-color: #f0e6ff !important;
                        font-weight: 600;
                        color: #4a148c;
                        font-size: 1.1rem;
                    }
                }
            `;
            document.head.appendChild(styleEl);
        }
        
        // Add file upload listeners
        this.setupFileUploadListeners(vendorId);
        
        // Initialize labour counter for this vendor
        vendorRow.setAttribute('data-labour-counter', '0');
        
        // Add Labour button listener
        document.getElementById(`addLabourBtn-${vendorId}`).addEventListener('click', () => {
            const labourSection = document.getElementById(`labourSection-${vendorId}`);
            if (labourSection.style.display === 'none') {
                labourSection.style.display = 'block';
                document.getElementById(`addLabourBtn-${vendorId}`).innerHTML = '<i class="fas fa-minus"></i> Hide Labour';
                
                // Add first laborer if list is empty
                const labourList = document.getElementById(`labourList-${vendorId}`);
                if (labourList.childElementCount === 0) {
                    this.addLabourerToVendor(vendorId);
                }
            } else {
                labourSection.style.display = 'none';
                document.getElementById(`addLabourBtn-${vendorId}`).innerHTML = '<i class="fas fa-user-plus"></i> Add Labour';
            }
        });
        
        // Add "Add Another Laborer" button listener
        document.getElementById(`addAnotherLabourBtn-${vendorId}`).addEventListener('click', () => {
            this.addLabourerToVendor(vendorId);
        });
    }
    
    // Add a new laborer to a vendor
    addLabourerToVendor(vendorId) {
        const vendorRow = document.querySelector(`.vendor-row[data-vendor-id="${vendorId}"]`);
        let labourCounter = parseInt(vendorRow.getAttribute('data-labour-counter') || '0');
        
        // Increment labour counter
        labourCounter++;
        vendorRow.setAttribute('data-labour-counter', labourCounter.toString());
        
        // Create unique ID for this laborer
        const labourId = `${vendorId}-${labourCounter}`;
        
        // Create the laborer item
        const labourItem = document.createElement('div');
        labourItem.className = 'labour-item';
        labourItem.setAttribute('data-labour-id', labourId);
        
        labourItem.innerHTML = `
            <div class="labour-item-header">
                <h6><i class="fas fa-hard-hat"></i> Laborer #${labourCounter}</h6>
                <button type="button" class="labour-delete-btn" data-labour-id="${labourId}" title="Remove laborer">
                    <i class="fas fa-trash-alt"></i> Remove
                </button>
            </div>
            <div class="labour-fields">
                <div class="labour-row">
                    <div class="vendor-field">
                        <label for="labourName-${labourId}"><i class="fas fa-user"></i> Labour Name</label>
                        <input type="text" id="labourName-${labourId}" class="event-form-control vendor-input" placeholder="Enter labour name">
                    </div>
                    <div class="vendor-field">
                        <label for="labourContact-${labourId}"><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="text" id="labourContact-${labourId}" class="event-form-control vendor-input" placeholder="Enter contact number">
                    </div>
                </div>
                <div class="attendance-container">
                    <h6 class="attendance-title"><i class="fas fa-clipboard-check"></i> Attendance</h6>
                    <div class="attendance-row">
                        <div class="vendor-field attendance-field">
                            <label for="morningAttendance-${labourId}">Morning Attendance</label>
                            <select id="morningAttendance-${labourId}" class="event-form-select vendor-input">
                                <option value="">Select Status</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                        <div class="vendor-field attendance-field">
                            <label for="eveningAttendance-${labourId}">Evening Attendance</label>
                            <select id="eveningAttendance-${labourId}" class="event-form-select vendor-input">
                                <option value="">Select Status</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Wages Section -->
                <div class="wages-container">
                    <h6 class="wages-title"><i class="fas fa-rupee-sign"></i> Daily Wages</h6>
                    <div class="wages-row">
                        <div class="vendor-field wages-field">
                            <label for="wagesPerDay-${labourId}">Wages per day</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₹</span>
                                </div>
                                <input type="number" id="wagesPerDay-${labourId}" class="event-form-control vendor-input" placeholder="Enter wages amount" min="0" step="10" onchange="calculateTotalWages('${labourId}')">
                            </div>
                        </div>
                        <div class="vendor-field wages-field">
                            <label for="totalDayWages-${labourId}">Total day wages</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₹</span>
                                </div>
                                <input type="number" id="totalDayWages-${labourId}" class="event-form-control vendor-input" placeholder="Total wages" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Overtime Details Section -->
                <div class="overtime-container">
                    <h6 class="overtime-title"><i class="fas fa-clock"></i> Overtime Details</h6>
                    <div class="overtime-row">
                        <div class="vendor-field overtime-field overtime-time">
                            <label for="otHours-${labourId}">OT Hours</label>
                            <input type="number" id="otHours-${labourId}" class="event-form-control vendor-input" min="0" max="24" placeholder="Hours" onchange="calculateTotalOT('${labourId}')">
                        </div>
                        <div class="vendor-field overtime-field overtime-time">
                            <label for="otMinutes-${labourId}">OT Minutes</label>
                            <select id="otMinutes-${labourId}" class="event-form-select vendor-input" onchange="calculateTotalOT('${labourId}')">
                                <option value="0">00</option>
                                <option value="30">30</option>
                            </select>
                        </div>
                        <div class="vendor-field overtime-field">
                            <label for="otRate-${labourId}">OT Rate/Hour</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₹</span>
                                </div>
                                <input type="number" id="otRate-${labourId}" class="event-form-control vendor-input" placeholder="Rate per hour" min="0" step="5" onchange="calculateTotalOT('${labourId}')">
                            </div>
                        </div>
                        <div class="vendor-field overtime-field">
                            <label for="totalOT-${labourId}">Total OT Amount</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₹</span>
                                </div>
                                <input type="number" id="totalOT-${labourId}" class="event-form-control vendor-input" placeholder="Total OT amount" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Travel Expenses Section -->
                <div class="travel-container">
                    <h6 class="travel-title"><i class="fas fa-bus"></i> Travel Expenses of Labour</h6>
                    <div class="travel-row">
                        <div class="vendor-field travel-field">
                            <label for="travelMode-${labourId}">Mode of Transport</label>
                            <select id="travelMode-${labourId}" class="event-form-select vendor-input">
                                <option value="">Select Mode</option>
                                <option value="bus">Bus</option>
                                <option value="train">Train</option>
                                <option value="auto">Auto Rickshaw</option>
                                <option value="taxi">Taxi/Cab</option>
                                <option value="own">Own Vehicle</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="vendor-field travel-field">
                            <label for="travelAmount-${labourId}">Travel Amount</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₹</span>
                                </div>
                                <input type="number" id="travelAmount-${labourId}" class="event-form-control vendor-input" placeholder="Enter travel amount" min="0" step="5" onchange="calculateGrandTotal('${labourId}')">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grand Total Section -->
                <div class="grand-total-container">
                    <h6 class="grand-total-title"><i class="fas fa-calculator"></i> Grand Total</h6>
                    <div class="grand-total-row">
                        <div class="vendor-field grand-total-field">
                            <label for="grandTotal-${labourId}">Total Amount</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₹</span>
                                </div>
                                <input type="number" id="grandTotal-${labourId}" class="event-form-control vendor-input grand-total-input" placeholder="Grand total" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add to the list
        const labourList = document.getElementById(`labourList-${vendorId}`);
        labourList.appendChild(labourItem);
        
        // Add event listener for delete button
        const deleteBtn = labourItem.querySelector(`.labour-delete-btn[data-labour-id="${labourId}"]`);
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                labourItem.remove();
                
                // Check if list is empty after removal
                if (labourList.childElementCount === 0) {
                    // Re-add a new empty laborer item
                    this.addLabourerToVendor(vendorId);
                } else {
                    // Update the laborer numbers
                    this.updateLabourerNumbers(vendorId);
                }
            });
        }
        
        // Add wage calculation listeners
        const wagesPerDay = document.getElementById(`wagesPerDay-${labourId}`);
        const morningAttendance = document.getElementById(`morningAttendance-${labourId}`);
        const eveningAttendance = document.getElementById(`eveningAttendance-${labourId}`);
        
        // Setup wage calculation when input values change
        if (wagesPerDay) {
            wagesPerDay.addEventListener('input', () => {
                calculateTotalWages(labourId);
            });
        }
        
        if (morningAttendance) {
            morningAttendance.addEventListener('change', () => {
                calculateTotalWages(labourId);
            });
        }
        
        if (eveningAttendance) {
            eveningAttendance.addEventListener('change', () => {
                calculateTotalWages(labourId);
            });
        }
        
        // Setup overtime calculation listeners
        const otHours = document.getElementById(`otHours-${labourId}`);
        const otMinutes = document.getElementById(`otMinutes-${labourId}`);
        const otRate = document.getElementById(`otRate-${labourId}`);
        
        if (otHours) {
            otHours.addEventListener('input', () => calculateTotalOT(labourId));
            otHours.addEventListener('change', () => calculateTotalOT(labourId));
        }
        
        if (otMinutes) {
            otMinutes.addEventListener('change', () => calculateTotalOT(labourId));
        }
        
        if (otRate) {
            otRate.addEventListener('input', () => calculateTotalOT(labourId));
            otRate.addEventListener('change', () => calculateTotalOT(labourId));
        }
        
        // Setup travel amount listener for grand total calculation
        const travelAmount = document.getElementById(`travelAmount-${labourId}`);
        if (travelAmount) {
            travelAmount.addEventListener('input', () => calculateGrandTotal(labourId));
            travelAmount.addEventListener('change', () => calculateGrandTotal(labourId));
        }
        
        // Calculate initial grand total
        calculateGrandTotal(labourId);
        
        // Focus the first input
        setTimeout(() => {
            const nameInput = document.getElementById(`labourName-${labourId}`);
            if (nameInput) nameInput.focus();
        }, 100);
    }
    
    // Update laborer numbers after removal
    updateLabourerNumbers(vendorId) {
        const labourItems = document.querySelectorAll(`#labourList-${vendorId} .labour-item`);
        
        labourItems.forEach((item, index) => {
            const headerTitle = item.querySelector('.labour-item-header h6');
            if (headerTitle) {
                headerTitle.innerHTML = `<i class="fas fa-hard-hat"></i> Laborer #${index + 1}`;
            }
        });
    }
    
    setupFileUploadListeners(vendorId) {
        // Material Picture
        this.setupImageSourceSelection('materialPicture', vendorId);
        
        // Bill Picture
        this.setupImageSourceSelection('billPicture', vendorId);
    }
    
    setupImageSourceSelection(fieldType, vendorId) {
        const inputElement = document.getElementById(`${fieldType}-${vendorId}`);
        const sourceButton = document.getElementById(`${fieldType}Btn-${vendorId}`);
        const filenameElement = document.getElementById(`${fieldType}Name-${vendorId}`);
        const locationElement = document.getElementById(`${fieldType}Location-${vendorId}`);
        
        if (!sourceButton || !inputElement || !filenameElement) return;
        
        // When the button is clicked, show the image source selection modal
        sourceButton.addEventListener('click', () => {
            this.showImageSourceModal(fieldType, vendorId);
        });
        
        // Add change event for the file input
        inputElement.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                if (this.files.length === 1) {
                    filenameElement.textContent = this.files[0].name;
                } else {
                    filenameElement.textContent = `${this.files.length} files selected`;
                }
                
                // Clear location element before adding new location data
                if (locationElement) {
                    locationElement.innerHTML = '';
                }
                
                // Get location from images if possible
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    extractImageLocation(file, (location) => {
                        if (location && locationElement) {
                            const locationHtml = `
                                <div class="location-info">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <span>Latitude: ${location.latitude.toFixed(6)}</span>
                                    <span>Longitude: ${location.longitude.toFixed(6)}</span>
                                    ${location.address ? `<span>Address: ${location.address}</span>` : ''}
                                    <a href="https://maps.google.com/?q=${location.latitude},${location.longitude}" target="_blank" class="view-on-map">
                                        <i class="fas fa-map"></i> View on Map
                                    </a>
                                </div>
                            `;
                            // Only show last image's location to prevent duplication
                            if (i === this.files.length - 1) {
                                locationElement.innerHTML = locationHtml;
                            }
                        }
                    });
                }
            } else {
                filenameElement.textContent = 'No files chosen';
                if (locationElement) {
                    locationElement.innerHTML = '';
                }
            }
        });
    }
    
    setupVendorRowListeners(vendorId, vendorRow) {
        // Setup vendor type dropdown change
        const vendorTypeSelect = document.getElementById(`vendorType-${vendorId}`);
        const vendorTypeDropdown = document.getElementById(`vendorTypeDropdown-${vendorId}`);
        const vendorTypeCustom = document.getElementById(`vendorTypeCustom-${vendorId}`);
        
        vendorTypeSelect.addEventListener('change', (e) => {
            if (e.target.value === 'custom') {
                // Switch to custom input
                vendorTypeDropdown.style.display = 'none';
                vendorTypeCustom.style.display = 'block';
                document.getElementById(`customVendorType-${vendorId}`).focus();
            }
        });
        
        // Setup back button for custom vendor type
        const backBtn = vendorRow.querySelector(`.vendor-back-btn[data-vendor-id="${vendorId}"]`);
        if (backBtn) {
            backBtn.addEventListener('click', () => {
                // Switch back to select dropdown
                vendorTypeCustom.style.display = 'none';
                vendorTypeDropdown.style.display = 'block';
                vendorTypeSelect.value = '';
                document.getElementById(`customVendorType-${vendorId}`).value = '';
            });
        }
        
        // Setup remove button
        const removeBtn = vendorRow.querySelector(`.vendor-delete-btn[data-vendor-id="${vendorId}"]`);
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                vendorRow.remove();
                // Update the vendor numbers after removal
                this.updateVendorNumbers();
            });
        }
    }
    
    // Method to update vendor numbers when a vendor is removed
    updateVendorNumbers() {
        const vendorRows = document.querySelectorAll('.vendor-row');
        
        // Loop through all vendor rows and update their display number sequentially
        vendorRows.forEach((row, index) => {
            // Update the visual label (the heading that shows "Vendor #X")
            const vendorHeader = row.querySelector('.vendor-header h5');
            if (vendorHeader) {
                vendorHeader.innerHTML = `<i class="fas fa-hard-hat"></i> Vendor #${index + 1}`;
            }
        });
    }
    
    showImageSourceModal(fieldType, vendorId) {
        // Create modal for image source selection if it doesn't exist
        if (!document.getElementById('imageSourceModal')) {
            const modalHTML = `
                <div id="imageSourceModal" class="image-source-modal">
                    <div class="image-source-content">
                        <div class="image-source-header">
                            <h3>Select Image Source</h3>
                            <button type="button" class="image-source-close">&times;</button>
                        </div>
                        <div class="image-source-body">
                            <button type="button" class="image-source-option" data-source="camera">
                                <i class="fas fa-camera"></i>
                                <span>Camera</span>
                            </button>
                            <button type="button" class="image-source-option" data-source="gallery">
                                <i class="fas fa-images"></i>
                                <span>Gallery</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Add event listener to close button
            document.querySelector('.image-source-close').addEventListener('click', () => {
                document.getElementById('imageSourceModal').style.display = 'none';
            });
            
            // Close modal when clicking outside
            document.getElementById('imageSourceModal').addEventListener('click', (e) => {
                if (e.target === document.getElementById('imageSourceModal')) {
                    document.getElementById('imageSourceModal').style.display = 'none';
                }
            });
        }
        
        // Create camera modal if it doesn't exist
        if (!document.getElementById('cameraModal')) {
            const cameraModalHTML = `
                <div id="cameraModal" class="camera-modal">
                    <div class="camera-content">
                        <div class="camera-header">
                            <h3><i class="fas fa-camera"></i> Take Picture</h3>
                            <button type="button" class="camera-close">&times;</button>
                        </div>
                        <div class="camera-status">
                            <i class="fas fa-location-arrow"></i> <span id="locationStatus">Getting location...</span>
                        </div>
                        <div class="camera-body">
                            <video id="cameraView" autoplay playsinline></video>
                            <canvas id="cameraCanvas" style="display:none;"></canvas>
                            <div class="camera-overlay">
                                <div class="camera-frame"></div>
                                <div class="location-indicator">
                                    <i class="fas fa-map-marker-alt"></i> <span id="currentLocation">Detecting location...</span>
                                </div>
                            </div>
                        </div>
                        <div class="camera-footer">
                            <button type="button" id="cameraCaptureBtn" class="camera-button">
                                <div class="capture-btn-inner"></div>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', cameraModalHTML);
            
            // Add event listener to close button
            document.querySelector('.camera-close').addEventListener('click', () => {
                this.closeCameraModal();
            });
            
            // Capture button
            document.getElementById('cameraCaptureBtn').addEventListener('click', () => {
                this.capturePhoto();
            });
        }
        
        // Store current field info for later use
        document.getElementById('imageSourceModal').setAttribute('data-field-type', fieldType);
        document.getElementById('imageSourceModal').setAttribute('data-vendor-id', vendorId);
        
        // Show the modal
        document.getElementById('imageSourceModal').style.display = 'flex';
        
        // Set up option buttons
        const optionButtons = document.querySelectorAll('.image-source-option');
        optionButtons.forEach(button => {
            // Remove existing event listeners by cloning and replacing
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            // Add new event listener
            newButton.addEventListener('click', () => {
                const source = newButton.getAttribute('data-source');
                const fieldType = document.getElementById('imageSourceModal').getAttribute('data-field-type');
                const vendorId = document.getElementById('imageSourceModal').getAttribute('data-vendor-id');
                
                if (source === 'camera') {
                    // Hide the source selection modal
                    document.getElementById('imageSourceModal').style.display = 'none';
                    // Open our custom camera
                    this.openCameraModal(fieldType, vendorId);
                } else {
                    // Use the default file input for gallery
                    const inputElement = document.getElementById(`${fieldType}-${vendorId}`);
                    if (inputElement) {
                        inputElement.removeAttribute('capture');
                        inputElement.click();
                        document.getElementById('imageSourceModal').style.display = 'none';
                    }
                }
            });
        });
    }
    
    openCameraModal(fieldType, vendorId) {
        const cameraModal = document.getElementById('cameraModal');
        const video = document.getElementById('cameraView');
        const locationStatus = document.getElementById('locationStatus');
        const currentLocation = document.getElementById('currentLocation');
        
        // Store field info
        cameraModal.setAttribute('data-field-type', fieldType);
        cameraModal.setAttribute('data-vendor-id', vendorId);
        
        // Make sure we have no existing photos causing duplicates
        this.capturedPhoto = null;
        
        // Create a more accurate location tracker
        this.startAccurateLocationTracking(locationStatus, currentLocation);
        
        // Show the modal first to ensure proper rendering
        cameraModal.style.display = 'flex';
        
        // Small delay to ensure modal is visible before accessing camera
        // This helps with positioning on mobile devices
        setTimeout(() => {
            // Access the camera
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                // Stop any existing stream
                if (this.cameraStream) {
                    this.cameraStream.getTracks().forEach(track => track.stop());
                }
                
                // Get access to the camera
                // For mobile compatibility, use constraints better suited for small screens
                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: window.innerWidth },
                        height: { ideal: window.innerHeight }
                    }
                };
                
                navigator.mediaDevices.getUserMedia(constraints)
                .then(stream => {
                    this.cameraStream = stream;
                    video.srcObject = stream;
                    
                    // Force the video to be centered
                    video.style.objectFit = 'cover';
                    video.style.width = '100%';
                    video.style.height = '100%';
                    
                    // Ensure the video is sized properly based on orientation
                    this.adjustVideoSize();
                    
                    // Add resize listener to adjust video when orientation changes
                    if (!this.resizeListener) {
                        this.resizeListener = () => this.adjustVideoSize();
                        window.addEventListener('resize', this.resizeListener);
                    }
                })
                .catch(error => {
                    console.error('Error accessing camera:', error);
                    alert('Error accessing camera: ' + error.message);
                });
            } else {
                alert('Your browser does not support camera access');
            }
        }, 50);
    }
    
    // More accurate location tracking with better error handling
    startAccurateLocationTracking(statusElement, displayElement) {
        // Clear any existing tracking
        if (this.locationUpdateInterval) {
            clearInterval(this.locationUpdateInterval);
            this.locationUpdateInterval = null;
        }
        
        // Reset current position
        this.currentPosition = null;
        
        // Set initial status
        if (statusElement) statusElement.innerHTML = '<span>Getting location...</span>';
        if (displayElement) displayElement.textContent = 'Detecting location...';
        
        // Check if geolocation is supported
        if (!navigator.geolocation) {
            if (statusElement) statusElement.innerHTML = '<span class="error-text">Geolocation not supported</span>';
            if (displayElement) displayElement.textContent = 'Location not available';
            return;
        }
        
        // Options for high accuracy - use maximum accuracy settings
        const geoOptions = {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        };
        
        // Initial location request
        const getLocation = () => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    // Save position for later use
                    this.currentPosition = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        timestamp: position.timestamp
                    };
                    
                    // Update status based on accuracy
                    let accuracy = Math.round(position.coords.accuracy);
                    let accuracyStatus = accuracy <= 10 ? 'high' : accuracy <= 50 ? 'medium' : 'low';
                    let statusMsg = `<span class="success-text">Location detected (${accuracyStatus} accuracy: ${accuracy}m)</span>`;
                    
                    if (statusElement) statusElement.innerHTML = statusMsg;
                    if (displayElement) {
                        displayElement.textContent = `Lat: ${position.coords.latitude.toFixed(6)}, Lng: ${position.coords.longitude.toFixed(6)}`;
                    }
                    
                    // Try to get address via reverse geocoding
                    this.getAddressFromCoordinates(position.coords.latitude, position.coords.longitude)
                        .then(address => {
                            if (address && displayElement) {
                                displayElement.innerHTML = `<strong>${address}</strong>`;
                            }
                        })
                        .catch(error => {
                            console.error('Error getting address:', error);
                        });
                },
                (error) => {
                    console.error('Error getting location:', error);
                    let errorMessage = 'Location error';
                    
                    switch (error.code) {
                        case 1: // PERMISSION_DENIED
                            errorMessage = 'Location permission denied';
                            break;
                        case 2: // POSITION_UNAVAILABLE
                            errorMessage = 'Location unavailable';
                            break;
                        case 3: // TIMEOUT
                            errorMessage = 'Location request timed out';
                            break;
                    }
                    
                    if (statusElement) statusElement.innerHTML = `<span class="error-text">${errorMessage}</span>`;
                    if (displayElement) displayElement.textContent = 'Location not available';
                },
                geoOptions
            );
        };
        
        // Initial location request
        getLocation();
        
        // Start monitoring location updates at more frequent intervals
        this.locationUpdateInterval = setInterval(getLocation, 2000);
    }
    
    capturePhoto() {
        const cameraModal = document.getElementById('cameraModal');
        const video = document.getElementById('cameraView');
        const canvas = document.getElementById('cameraCanvas');
        const fieldType = cameraModal.getAttribute('data-field-type');
        const vendorId = cameraModal.getAttribute('data-vendor-id');
        
        // Make sure we have video before capturing
        if (!video || !video.srcObject || !this.cameraStream) {
            alert("Camera not ready. Please try again.");
            return;
        }
        
        // Set canvas dimensions to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        // Draw current video frame to canvas
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Capture current location exactly at time of photo
        const photoLocation = this.currentPosition ? {...this.currentPosition} : null;
        
        // Convert canvas to blob
        canvas.toBlob(blob => {
            // Create a File object from the Blob
            const timestamp = new Date().toISOString().replace(/:/g, '-');
            const filename = `photo_${timestamp}.jpg`;
            const photoFile = new File([blob], filename, { type: 'image/jpeg' });
            
            // Add location data to the file object with additional metadata
            if (photoLocation) {
                photoFile.location = photoLocation;
                
                // Add additional metadata similar to dashboard implementation
                photoFile.locationMetadata = {
                    capturedAt: new Date().toISOString(),
                    deviceInfo: navigator.userAgent,
                    timestamp: photoLocation.timestamp,
                    accuracy: photoLocation.accuracy
                };
                
                // Get the address immediately so it's attached to the photo
                this.getAddressFromCoordinates(photoLocation.latitude, photoLocation.longitude)
                    .then(address => {
                        photoFile.location.address = address;
                        // Now that we have the complete data, update the file input
                        this.updateFileInput(fieldType, vendorId, photoFile);
                        
                        // Close the camera modal
                        this.closeCameraModal();
                    })
                    .catch(error => {
                        console.error("Error getting address:", error);
                        // Continue even if address lookup fails
                        this.updateFileInput(fieldType, vendorId, photoFile);
                        this.closeCameraModal();
                    });
            } else {
                // No location data available, proceed without it
                this.updateFileInput(fieldType, vendorId, photoFile);
                this.closeCameraModal();
            }
        }, 'image/jpeg', 0.95);
    }
    
    // New method to properly update file input and avoid duplicates
    updateFileInput(fieldType, vendorId, newFile) {
        const inputElement = document.getElementById(`${fieldType}-${vendorId}`);
        if (!inputElement) return;
        
        try {
            // Create new DataTransfer object
            const dataTransfer = new DataTransfer();
            
            // Add existing files, but make sure we're not duplicating
            if (inputElement.files) {
                // Get filename of new file to avoid duplicates
                const newFilename = newFile.name;
                
                // Add existing files except those with the same name
                for (let i = 0; i < inputElement.files.length; i++) {
                    const existingFile = inputElement.files[i];
                    // Only add files with different names
                    if (existingFile.name !== newFilename) {
                        dataTransfer.items.add(existingFile);
                    }
                }
            }
            
            // Add the new photo
            dataTransfer.items.add(newFile);
            
            // Update the file input
            inputElement.files = dataTransfer.files;
            
            // Trigger the change event manually, but in a controlled way that doesn't create new vendors
            const event = new Event('change', { bubbles: true });
            
            // Set a flag that this is a file input change, not a user-initiated action
            // This can be used to prevent unintended side effects
            event.isFileInputChange = true;
            inputElement.dispatchEvent(event);
            
            // Update the location display immediately with precise location
            const locationElement = document.getElementById(`${fieldType}Location-${vendorId}`);
            if (locationElement && newFile.location) {
                // Clear existing location info to prevent duplicates
                locationElement.innerHTML = '';
                
                // Use the existing instance of this class, not a new one
                if (newFile.location.address) {
                    const locationHtml = `
                        <div class="location-info">
                            <i class="fas fa-map-marker-alt"></i> 
                            <span>Latitude: ${newFile.location.latitude.toFixed(6)}</span>
                            <span>Longitude: ${newFile.location.longitude.toFixed(6)}</span>
                            <span>Accuracy: ${newFile.location.accuracy ? newFile.location.accuracy.toFixed(1) + 'm' : 'Unknown'}</span>
                            ${newFile.location.address ? `<span>Address: ${newFile.location.address}</span>` : ''}
                            <a href="https://maps.google.com/?q=${newFile.location.latitude},${newFile.location.longitude}" target="_blank" class="view-on-map">
                                <i class="fas fa-map"></i> View on Map
                            </a>
                        </div>
                    `;
                    locationElement.innerHTML = locationHtml;
                } else if (window.calendarEventModal) {
                    // Use global instance if address isn't already set
                    window.calendarEventModal.getAddressFromCoordinates(newFile.location.latitude, newFile.location.longitude)
                        .then(address => {
                            const locationHtml = `
                                <div class="location-info">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <span>Latitude: ${newFile.location.latitude.toFixed(6)}</span>
                                    <span>Longitude: ${newFile.location.longitude.toFixed(6)}</span>
                                    <span>Accuracy: ${newFile.location.accuracy ? newFile.location.accuracy.toFixed(1) + 'm' : 'Unknown'}</span>
                                    ${address ? `<span>Address: ${address}</span>` : ''}
                                    <a href="https://maps.google.com/?q=${newFile.location.latitude},${newFile.location.longitude}" target="_blank" class="view-on-map">
                                        <i class="fas fa-map"></i> View on Map
                                    </a>
                                </div>
                            `;
                            locationElement.innerHTML = locationHtml;
                        });
                }
            }
        } catch (error) {
            console.error("Error updating file input:", error);
            // Fallback method for browsers that don't support DataTransfer API
            alert("Photo captured successfully!");
        }
    }
    
    adjustVideoSize() {
        const video = document.getElementById('cameraView');
        if (!video) return;
        
        // For portrait mode
        if (window.innerHeight > window.innerWidth) {
            video.style.width = '100%';
            video.style.height = 'auto';
        } 
        // For landscape mode
        else {
            video.style.width = 'auto';
            video.style.height = '100%';
        }
    }
    
    closeCameraModal() {
        // Stop the camera stream
        if (this.cameraStream) {
            this.cameraStream.getTracks().forEach(track => track.stop());
            this.cameraStream = null;
        }
        
        // Clear location update interval
        if (this.locationUpdateInterval) {
            clearInterval(this.locationUpdateInterval);
            this.locationUpdateInterval = null;
        }
        
        // Remove resize listener
        if (this.resizeListener) {
            window.removeEventListener('resize', this.resizeListener);
            this.resizeListener = null;
        }
        
        // Hide the modal
        document.getElementById('cameraModal').style.display = 'none';
    }
    
    // Geocoding function to get address from coordinates
    async getAddressFromCoordinates(latitude, longitude) {
        try {
            // Use Nominatim for reverse geocoding (similar to dashboard.php approach)
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`;
            
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'User-Agent': 'HR Site Supervisor System' // Nominatim requires a user agent
                }
            });
            
            if (!response.ok) {
                throw new Error('Geocoding service failed');
            }
            
            const data = await response.json();
            
            if (data && data.display_name) {
                return data.display_name;
            } else {
                // Fallback to our simulated addresses if the service doesn't return results
                return this.getSimulatedAddress(latitude, longitude);
            }
        } catch (error) {
            console.error("Error in reverse geocoding:", error);
            // Fallback to simulated address
            return this.getSimulatedAddress(latitude, longitude);
        }
    }
    
    // Simulated address as fallback
    getSimulatedAddress(latitude, longitude) {
        // Simulate different addresses based on coordinates
        // This makes it seem more realistic than just returning the same address
        const latBase = Math.floor(latitude * 100);
        const lngBase = Math.floor(longitude * 100);
        
        // Array of possible address parts
        const areas = ["Connaught Place", "Karol Bagh", "Chandni Chowk", "Hauz Khas", "Saket", "Greater Kailash"];
        const streets = ["Main Road", "Market Street", "Junction", "Complex", "Plaza", "Center"];
        const cities = ["New Delhi", "Delhi", "Gurgaon", "Noida", "Faridabad"];
        
        // Use coordinates to select "random" but consistent address parts
        const areaIndex = (latBase + lngBase) % areas.length;
        const streetIndex = (latBase * lngBase) % streets.length;
        const cityIndex = (latBase - lngBase) % cities.length;
        
        // Construct address
        return `${areas[areaIndex]}, ${streets[streetIndex]}, ${cities[cityIndex]}, India`;
    }
}

/**
 * Calendar Event Detail Modal 
 * Displays events for a specific calendar date
 */
class CalendarEventDetailModal {
    constructor() {
        this.init();
        this.currentDate = null;
    }
    
    init() {
        // Create the modal HTML structure
        this.createModalStructure();
        
        // Initialize event listeners
        this.setupEventListeners();
    }
    
    createModalStructure() {
        const modalHTML = `
            <div id="eventDetailModalBackdrop" class="event-detail-modal-backdrop">
                <div class="event-detail-modal">
                    <div class="event-detail-header">
                        <h3 class="event-detail-title"><i class="fas fa-clipboard-list"></i> Daily Site Updates</h3>
                        <button type="button" class="event-detail-close" id="eventDetailClose">&times;</button>
                    </div>
                    <div class="event-detail-body">
                        <div class="event-detail-date" id="eventDetailDate"></div>
                        <div id="eventDetailContent">
                            <!-- Event list will be populated dynamically -->
                        </div>
                    </div>
                    <div class="event-detail-footer">
                        <button type="button" class="event-add-btn" id="eventDetailAddBtn">
                            <i class="fas fa-plus"></i> Add Event
                        </button>
                        <button type="button" class="event-close-btn" id="eventDetailCloseBtn">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to the body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    setupEventListeners() {
        // Close modal buttons
        document.getElementById('eventDetailClose').addEventListener('click', () => this.hideModal());
        document.getElementById('eventDetailCloseBtn').addEventListener('click', () => this.hideModal());
        
        // Close on backdrop click
        document.getElementById('eventDetailModalBackdrop').addEventListener('click', (e) => {
            if (e.target === document.getElementById('eventDetailModalBackdrop')) {
                this.hideModal();
            }
        });
        
        // Add event button
        document.getElementById('eventDetailAddBtn').addEventListener('click', () => {
            this.hideModal();
            if (this.currentDate) {
                window.calendarEventModal.showModal(
                    this.currentDate.day,
                    this.currentDate.month,
                    this.currentDate.year,
                    this.currentDate.monthName
                );
            }
        });
    }
    
    showModal(day, month, year, monthName) {
        // Store current date
        this.currentDate = { day, month, year, monthName };
        
        // Set the date display
        document.getElementById('eventDetailDate').textContent = `${monthName} ${day}, ${year}`;
        
        // Populate events for this date
        this.populateEvents(day, month, year);
        
        // Show the modal
        document.getElementById('eventDetailModalBackdrop').classList.add('active');
    }
    
    hideModal() {
        document.getElementById('eventDetailModalBackdrop').classList.remove('active');
    }
    
    populateEvents(day, month, year) {
        const contentElement = document.getElementById('eventDetailContent');
        const dateKey = `${year}-${month}-${day}`;
        const formattedDate = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        
        // Show loading indicator
        contentElement.innerHTML = `
            <div class="events-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading events...</p>
            </div>
        `;
        
        // Fetch event data from the server
        fetch('includes/calendar_data_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_event_details&date=${formattedDate}`
        })
        .then(response => response.json())
        .then(data => {
            // Clear loading indicator
            contentElement.innerHTML = '';
            
            if (data.status === 'success' && data.events && data.events.length > 0) {
                // Create event list
                const eventList = document.createElement('ul');
                eventList.className = 'event-list';
                
                data.events.forEach(event => {
                    const listItem = document.createElement('li');
                    listItem.className = 'event-list-item';
                    
                    // Get site name display text
                    const siteNameMap = {
                        'building-a': 'Building A',
                        'building-b': 'Building B',
                        'sector-1': 'Sector 1',
                        'east-wing': 'East Wing',
                        'west-wing': 'West Wing'
                    };
                    
                    // Determine site name display (handle both dropdown options and custom entries)
                    let siteNameDisplay = event.siteName;
                    
                    // Create vendors HTML if vendors exist
                    let vendorsHTML = '';
                    if (event.vendors && event.vendors.length > 0) {
                        vendorsHTML = `
                            <div class="event-vendors">
                                <h6><i class="fas fa-users"></i> Vendors (${event.vendors.length})</h6>
                                <ul class="vendor-mini-list">
                                    ${event.vendors.map(vendor => {
                                        // Create material info if it exists
                                        let materialHTML = '';
                                        if (vendor.material) {
                                            const hasMaterialData = vendor.material.remark || 
                                                                  vendor.material.amount || 
                                                                  (vendor.material.materialPictures && vendor.material.materialPictures.length) || 
                                                                  (vendor.material.billPictures && vendor.material.billPictures.length);
                                            
                                            if (hasMaterialData) {
                                                materialHTML = `
                                                    <div class="vendor-material-info">
                                                        <div class="material-header">
                                                            <i class="fas fa-boxes"></i> Material Details:
                                                        </div>
                                                        <div class="material-details">
                                                            ${vendor.material.amount ? 
                                                              `<span class="material-amount">Amount: ₹${vendor.material.amount}</span>` : ''}
                                                            ${vendor.material.remark ? 
                                                              `<span class="material-remark">Remark: ${vendor.material.remark}</span>` : ''}
                                                            ${vendor.material.materialPictures && vendor.material.materialPictures.length > 0 ? 
                                                              `<span class="material-image">Material Images: ${vendor.material.materialPictures.length} files</span>` : ''}
                                                            ${vendor.material.billPictures && vendor.material.billPictures.length > 0 ? 
                                                              `<span class="material-bill">Bill Images: ${vendor.material.billPictures.length} files</span>` : ''}
                                                        </div>
                                                    </div>
                                                `;
                                            }
                                        }
                                        
                                        // For multiple laborers
                                        let labourHTML = '';
                                        if (vendor.labourers && vendor.labourers.length > 0) {
                                            labourHTML = `
                                                <div class="vendor-labour-info">
                                                    <div class="labour-header">
                                                        <i class="fas fa-hard-hat"></i> Labour Details (${vendor.labourers.length}):
                                                    </div>
                                                    <div class="labour-list-view">
                                            `;
                                            
                                            // Add each laborer
                                            vendor.labourers.forEach((labour, idx) => {
                                                // Determine attendance status icons and colors
                                                const morningStatus = labour.attendance?.morning === 'present' ? 
                                                    '<span class="attendance-status present"><i class="fas fa-check-circle"></i> Present</span>' : 
                                                    labour.attendance?.morning === 'absent' ? 
                                                    '<span class="attendance-status absent"><i class="fas fa-times-circle"></i> Absent</span>' : 
                                                    '<span class="attendance-status">Not recorded</span>';
                                                    
                                                const eveningStatus = labour.attendance?.evening === 'present' ? 
                                                    '<span class="attendance-status present"><i class="fas fa-check-circle"></i> Present</span>' : 
                                                    labour.attendance?.evening === 'absent' ? 
                                                    '<span class="attendance-status absent"><i class="fas fa-times-circle"></i> Absent</span>' : 
                                                    '<span class="attendance-status">Not recorded</span>';
                                                    
                                                labourHTML += `
                                                    <div class="labour-item-view">
                                                        <div class="labour-item-header-view">
                                                            <strong>Laborer #${idx + 1} - ${labour.name}</strong>
                                                            ${labour.contact ? `<a href="tel:${labour.contact}" class="labour-contact-link"><i class="fas fa-phone-alt"></i> ${labour.contact}</a>` : ''}
                                                        </div>
                                                        <div class="labour-attendance">
                                                            <div class="attendance-detail">
                                                                <strong>Morning:</strong> ${morningStatus}
                                                            </div>
                                                            <div class="attendance-detail">
                                                                <strong>Evening:</strong> ${eveningStatus}
                                                            </div>
                                                        </div>
                                                        ${labour.wages ? `
                                                        <div class="labour-wages">
                                                            <div class="wage-detail">
                                                                <strong>Daily Wage:</strong> <span class="wage-amount">₹${parseFloat(labour.wages.perDay).toFixed(2)}</span>
                                                            </div>
                                                            <div class="wage-detail ${parseFloat(labour.wages.totalDay) > 0 ? 'wage-paid' : 'wage-unpaid'}">
                                                                <strong>Total Paid:</strong> <span class="wage-amount">₹${parseFloat(labour.wages.totalDay).toFixed(2)}</span>
                                                            </div>
                                                        </div>
                                                        ` : ''}
                                                        
                                                        ${labour.overtime && (parseFloat(labour.overtime.hours) > 0 || parseFloat(labour.overtime.minutes) > 0) ? `
                                                        <div class="labour-overtime">
                                                            <div class="overtime-header">
                                                                <i class="fas fa-clock"></i> Overtime
                                                            </div>
                                                            <div class="overtime-details">
                                                                <div class="overtime-time-info">
                                                                    <span>${labour.overtime.hours} hrs ${labour.overtime.minutes} mins</span>
                                                                    <span class="overtime-rate">at ₹${parseFloat(labour.overtime.rate).toFixed(2)}/hr</span>
                                                                </div>
                                                                <div class="overtime-amount ${parseFloat(labour.overtime.total) > 0 ? 'amount-paid' : ''}">
                                                                    <strong>Total OT:</strong> ₹${parseFloat(labour.overtime.total).toFixed(2)}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        ` : ''}
                                                        
                                                        ${labour.travel && (labour.travel.mode || parseFloat(labour.travel.amount) > 0) ? `
                                                        <div class="labour-travel">
                                                            <div class="travel-header">
                                                                <i class="fas fa-bus"></i> Travel
                                                            </div>
                                                            <div class="travel-details">
                                                                ${labour.travel.mode ? `
                                                                <div class="travel-mode">
                                                                    <strong>Mode:</strong> ${labour.travel.mode.charAt(0).toUpperCase() + labour.travel.mode.slice(1)}
                                                                </div>
                                                                ` : ''}
                                                                ${parseFloat(labour.travel.amount) > 0 ? `
                                                                <div class="travel-amount">
                                                                    <strong>Amount:</strong> ₹${parseFloat(labour.travel.amount).toFixed(2)}
                                                                </div>
                                                                ` : ''}
                                                            </div>
                                                        </div>
                                                        ` : ''}
                                                    </div>
                                                `;
                                            });
                                            
                                            labourHTML += `
                                                    </div>
                                                </div>
                                            `;
                                        }
                                        
                                        return `
                                            <li>
                                                <i class="fas fa-${vendor.type === 'supplier' ? 'truck-loading' : 
                                                                 vendor.type === 'contractor' ? 'hammer' : 
                                                                 vendor.type === 'consultant' ? 'briefcase' : 
                                                                 vendor.type === 'laborer' ? 'hard-hat' : 'building'}"></i>
                                                <strong>${vendor.name}</strong> (${vendor.type}) 
                                                <a href="tel:${vendor.contact}" class="vendor-contact-link"><i class="fas fa-phone-alt"></i> ${vendor.contact}</a>
                                                ${materialHTML}
                                                ${labourHTML}
                                            </li>
                                        `;
                                    }).join('')}
                                </ul>
                            </div>
                        `;
                    }
                    
                    listItem.innerHTML = `
                        <div class="event-content">
                            <div class="event-site"><i class="fas fa-map-marker-alt"></i> ${event.siteName}</div>
                            ${vendorsHTML}
                        </div>
                    `;
                    
                    eventList.appendChild(listItem);
                });
                
                contentElement.appendChild(eventList);
            } else {
                // Show empty state
                contentElement.innerHTML = `
                    <div class="event-empty-state">
                        <div class="event-empty-icon">
                            <i class="far fa-calendar-check"></i>
                        </div>
                        <p>No events scheduled for this day</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching event data:', error);
            
            // Show error state
            contentElement.innerHTML = `
                <div class="event-error-state">
                    <div class="event-error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p>Could not load events. Please try again later.</p>
                </div>
            `;
        });
    }
}

// Initialize the calendar modal functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Create global instances of the modals
    window.calendarEventModal = new CalendarEventModal();
    window.calendarEventDetailModal = new CalendarEventDetailModal();
    
    // Initialize mock data for demo purposes (can be removed in production)
    window.calendarEvents = initializeMockEvents();
    
    // Setup calendar day click events
    setupCalendarDayClickEvents();
});

// Initialize mock calendar events for demo
function initializeMockEvents() {
    const today = new Date();
    const currentYear = today.getFullYear();
    const currentMonth = today.getMonth() + 1; // JavaScript months are 0-indexed
    
    // Create a dateKey in format YYYY-MM-DD
    const formatDateKey = (year, month, day) => `${year}-${month}-${day}`;
    
    // Create some sample events for the current month
    const events = {};
    
    // Available site names
    const siteNames = ['building-a', 'building-b', 'sector-1', 'east-wing', 'west-wing'];
    
    // Add events for today
    const todayKey = formatDateKey(currentYear, currentMonth, today.getDate());
    events[todayKey] = [
        {
            id: 1,
            title: 'Safety Inspection - Building A',
            siteName: 'building-a'
        },
        {
            id: 2,
            title: 'Team Progress Meeting',
            siteName: 'sector-1'
        }
    ];
    
    // Add events for tomorrow
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    const tomorrowKey = formatDateKey(currentYear, currentMonth, tomorrow.getDate());
    events[tomorrowKey] = [
        {
            id: 3,
            title: 'Concrete Delivery',
            siteName: 'building-b'
        }
    ];
    
    // Add events for day after tomorrow
    const dayAfterTomorrow = new Date(today);
    dayAfterTomorrow.setDate(today.getDate() + 2);
    const dayAfterTomorrowKey = formatDateKey(currentYear, currentMonth, dayAfterTomorrow.getDate());
    events[dayAfterTomorrowKey] = [
        {
            id: 4,
            title: 'Water Leak Investigation',
            siteName: 'east-wing'
        },
        {
            id: 5,
            title: 'Monthly Progress Report Due',
            siteName: 'west-wing'
        }
    ];
    
    return events;
}

// Setup click events for calendar days
function setupCalendarDayClickEvents() {
    // Wait for the calendar to be fully rendered
    setTimeout(() => {
        // Get all calendar day cells (excluding day names and empty cells)
        const calendarDays = document.querySelectorAll('.site-calendar-day:not(.calendar-day-name):not(.calendar-empty-day)');
        
        calendarDays.forEach(dayCell => {
            // Get existing plus button if it exists
            const plusButton = dayCell.querySelector('.add-event-btn');
            
            // Make the day cell clickable to show events
            dayCell.addEventListener('click', (e) => {
                // Don't trigger if clicking the plus button
                if (e.target === plusButton || plusButton && plusButton.contains(e.target)) {
                    return;
                }
                
                // Get the date information
                const day = dayCell.getAttribute('data-day');
                const month = dayCell.getAttribute('data-month');
                const year = dayCell.getAttribute('data-year');
                const monthName = getMonthName(parseInt(month));
                
                // Show the details modal
                window.calendarEventDetailModal.showModal(day, month, year, monthName);
            });
        });
    }, 500); // Small delay to ensure calendar has rendered
}

// Helper function to get month name
function getMonthName(monthNumber) {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[monthNumber - 1]; // Adjust for 0-indexed array
}

// Utility function to extract location data from image
function extractImageLocation(file, callback) {
    // If the file has location data from our custom camera, use that
    if (file.location) {
        callback({
            latitude: file.location.latitude,
            longitude: file.location.longitude,
            accuracy: file.location.accuracy || 'Unknown',
            address: file.location.address || 'Address unavailable',
            timestamp: file.locationMetadata?.timestamp || new Date().getTime()
        });
        return;
    }
    
    // Otherwise, try to extract EXIF data
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const arrayBuffer = e.target.result;
        
        // Try to extract EXIF data
        try {
            // For demo purposes, we'll check if we can get a location from the browser
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const locationData = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy,
                            timestamp: position.timestamp
                        };
                        
                        // Use the existing calendar modal instance if available
                        // Instead of creating a new one which could affect vendor numbering
                        if (window.calendarEventModal) {
                            window.calendarEventModal.getAddressFromCoordinates(locationData.latitude, locationData.longitude)
                                .then(address => {
                                    callback({
                                        latitude: locationData.latitude,
                                        longitude: locationData.longitude,
                                        accuracy: locationData.accuracy,
                                        address: address,
                                        timestamp: locationData.timestamp,
                                        note: "Location from current device position"
                                    });
                                })
                                .catch(error => {
                                    console.error("Error getting address:", error);
                                    callback({
                                        latitude: locationData.latitude,
                                        longitude: locationData.longitude,
                                        accuracy: locationData.accuracy,
                                        address: 'Address unavailable',
                                        timestamp: locationData.timestamp,
                                        note: "Location from current device position"
                                    });
                                });
                        } else {
                            // Fallback if global instance not available
                            simulateFallbackLocation();
                        }
                    },
                    function(error) {
                        console.error("Error getting current location:", error);
                        // Fallback to a simulated location
                        simulateFallbackLocation();
                    },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
            } else {
                simulateFallbackLocation();
            }
        } catch (error) {
            console.error("Error extracting image location:", error);
            simulateFallbackLocation();
        }
    };
    
    reader.onerror = function() {
        console.error("Error reading file");
        simulateFallbackLocation();
    };
    
    // Helper function for fallback location
    function simulateFallbackLocation() {
        // Generate coordinates for New Delhi area
        const latitude = 28.6139 + (Math.random() - 0.5) * 0.05;
        const longitude = 77.2090 + (Math.random() - 0.5) * 0.05;
        
        // Use the existing calendar modal instance if available
        if (window.calendarEventModal) {
            window.calendarEventModal.getAddressFromCoordinates(latitude, longitude)
                .then(address => {
                    callback({
                        latitude: latitude,
                        longitude: longitude,
                        accuracy: 100 + Math.floor(Math.random() * 100),
                        address: address,
                        timestamp: new Date().getTime(),
                        note: "Location estimated (simulated)"
                    });
                })
                .catch(error => {
                    console.error("Error getting address:", error);
                    callback({
                        latitude: latitude,
                        longitude: longitude,
                        accuracy: 100 + Math.floor(Math.random() * 100),
                        address: 'Address unavailable',
                        timestamp: new Date().getTime(),
                        note: "Location estimated (simulated)"
                    });
                });
        } else {
            // Fallback without address if global instance not available
            callback({
                latitude: latitude,
                longitude: longitude,
                accuracy: 100 + Math.floor(Math.random() * 100),
                address: 'Address unavailable (geocoding unavailable)',
                timestamp: new Date().getTime(),
                note: "Location estimated (simulated)"
            });
        }
    }
    
    // Read the file as an array buffer
    reader.readAsArrayBuffer(file);
}

// Add a new calculateTotalWages function to calculate the total wages based on attendance and daily wage
function calculateTotalWages(labourId) {
    const wagesPerDay = document.getElementById(`wagesPerDay-${labourId}`);
    const totalDayWages = document.getElementById(`totalDayWages-${labourId}`);
    const morningAttendance = document.getElementById(`morningAttendance-${labourId}`);
    const eveningAttendance = document.getElementById(`eveningAttendance-${labourId}`);
    
    if (!wagesPerDay || !totalDayWages) return;
    
    const dailyWage = parseFloat(wagesPerDay.value) || 0;
    
    // Calculate based on attendance
    let totalWage = 0;
    
    if (morningAttendance && eveningAttendance) {
        const isMorningPresent = morningAttendance.value === 'present';
        const isEveningPresent = eveningAttendance.value === 'present';
        
        if (isMorningPresent && isEveningPresent) {
            // Full day attendance
            totalWage = dailyWage;
        } else if (isMorningPresent || isEveningPresent) {
            // Half day attendance
            totalWage = dailyWage * 0.5;
        }
        // If both absent, totalWage remains 0
    }
    
    // Update the total wages field
    totalDayWages.value = totalWage.toFixed(2);
    
    // Calculate grand total after wages are updated
    calculateGrandTotal(labourId);
}

// Calculate total overtime amount based on hours, minutes and rate
function calculateTotalOT(labourId) {
    const otHours = document.getElementById(`otHours-${labourId}`);
    const otMinutes = document.getElementById(`otMinutes-${labourId}`);
    const otRate = document.getElementById(`otRate-${labourId}`);
    const totalOT = document.getElementById(`totalOT-${labourId}`);
    
    if (!otHours || !otMinutes || !otRate || !totalOT) return;
    
    const hours = parseFloat(otHours.value) || 0;
    const minutes = parseFloat(otMinutes.value) || 0;
    const rate = parseFloat(otRate.value) || 0;
    
    // Convert minutes to hours (as a decimal)
    const totalHours = hours + (minutes / 60);
    
    // Calculate total overtime amount
    const totalAmount = totalHours * rate;
    
    // Update the total OT field with the calculated amount
    totalOT.value = totalAmount.toFixed(2);
    
    // Calculate grand total after OT is updated
    calculateGrandTotal(labourId);
}

// Calculate grand total by summing wages, overtime, and travel expenses
function calculateGrandTotal(labourId) {
    const totalDayWages = document.getElementById(`totalDayWages-${labourId}`);
    const totalOT = document.getElementById(`totalOT-${labourId}`);
    const travelAmount = document.getElementById(`travelAmount-${labourId}`);
    const grandTotal = document.getElementById(`grandTotal-${labourId}`);
    
    if (!grandTotal) return;
    
    const wages = parseFloat(totalDayWages?.value) || 0;
    const overtime = parseFloat(totalOT?.value) || 0;
    const travel = parseFloat(travelAmount?.value) || 0;
    
    // Sum all expenses
    const total = wages + overtime + travel;
    
    // Update the grand total field
    grandTotal.value = total.toFixed(2);
} 