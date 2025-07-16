/**
 * Calendar Event Modal
 * Handles the creation and management of calendar events
 */

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize calendar event modal
    window.initCalendarEventModal();
    
    // Initialize Bootstrap custom file inputs
    initCustomFileInputs();
});

/**
 * Initialize Bootstrap custom file inputs
 * This adds the filename display functionality to file inputs
 */
function initCustomFileInputs() {
    // Add event listener to document for delegation
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('custom-file-input')) {
            const fileName = e.target.files[0]?.name || 'Choose file';
            e.target.nextElementSibling.textContent = fileName;
        }
    });
}

// Load the CSS file
function loadCalendarEventModalStyles() {
    if (!document.getElementById('calendar-event-modal-css')) {
        const link = document.createElement('link');
        link.id = 'calendar-event-modal-css';
        link.rel = 'stylesheet';
        link.href = 'css/manager/calendar-event-modal.css';
        document.head.appendChild(link);
    }
}

// Event types for the dropdown
const EVENT_TYPES = [
    { value: 'construction-site-sector-80', label: 'Construction Site At Sector 80' },
    { value: 'construction-site-dilshad-garden', label: 'Construction Site At Dilshad Garden' },
    { value: 'construction-site-jasola', label: 'Construction Site At Jasola' },
    { value: 'construction-site-faridabad-sector-91', label: 'Construction Site At Faridabad Sector 91' },
    { value: 'construction-site-ballabgarh', label: 'Construction Site At Ballabgarh' },
    { value: 'construction-site-sector-53', label: 'Construction Site At Sector 53' },
    { value: 'construction-site-supertech-oxford', label: 'Construction Site At Supertech Oxford' },
    { value: 'construction-site-sector-14', label: 'Construction Site At Sector 14' },
    { value: 'custom', label: 'Custom Title' }
];

// Array to store custom titles loaded from the database
let CUSTOM_TITLES = [];

/**
 * Initialize the calendar event modal
 * This function is exposed globally for other modules to use
 */
window.initCalendarEventModal = function() {
    // Load CSS styles
    loadCalendarEventModalStyles();
    
    // Load custom titles from database
    loadCustomTitles();
    
    // Create the modal HTML if it doesn't exist
    if (!document.getElementById('calendarEventModal')) {
        createCalendarEventModal();
        setupCalendarEventModalListeners();
    }
};

/**
 * Load custom titles from the database
 */
function loadCustomTitles() {
    // Make an AJAX request to fetch custom titles
    fetch('backend/get_custom_titles.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success' && Array.isArray(data.titles)) {
                CUSTOM_TITLES = data.titles;
                
                // Update the custom titles dropdown if it exists
                updateCustomTitlesDropdown();
            } else {
                console.error('Failed to load custom titles:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error loading custom titles:', error);
        });
}

/**
 * Update the custom titles dropdown with loaded titles
 */
function updateCustomTitlesDropdown() {
    const siteSelectElement = document.getElementById('siteSelect');
    if (!siteSelectElement) return;
    
    // Keep the default options
    const defaultOptions = Array.from(siteSelectElement.options)
        .filter(option => {
            const value = option.value;
            return !value.startsWith('custom-') || value === '';
        });
    
    // Clear existing options
    siteSelectElement.innerHTML = '';
    
    // Add back default options
    defaultOptions.forEach(option => {
        siteSelectElement.appendChild(option);
    });
    
    // Add custom titles as options
    if (CUSTOM_TITLES.length > 0) {
        // Add a separator if there are custom titles
        const separator = document.createElement('option');
        separator.disabled = true;
        separator.textContent = '─────────────────';
        siteSelectElement.appendChild(separator);
        
        // Add each custom title
        CUSTOM_TITLES.forEach((title, index) => {
            const option = document.createElement('option');
            option.value = `custom-${index}`;
            option.textContent = title;
            siteSelectElement.appendChild(option);
        });
    }
}

/**
 * Create the calendar event modal HTML
 */
function createCalendarEventModal() {
    const modalHTML = `
        <div class="modal fade" id="calendarEventModal" tabindex="-1" role="dialog" aria-labelledby="calendarEventModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="calendarEventModalLabel">
                            <i class="fas fa-calendar-plus"></i> Add New Event
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="calendarEventForm">
                            <div class="form-group" id="siteSelectContainer">
                                <label for="siteSelect"><i class="fas fa-building"></i> Site Location</label>
                                <select class="form-control" id="siteSelect" required>
                                    <option value="" disabled selected>Select site location</option>
                                    ${EVENT_TYPES.map(type => `<option value="${type.value}">${type.label}</option>`).join('')}
                                </select>
                                <small class="form-text text-muted">Choose the site location for this event</small>
                            </div>
                            
                            <div class="form-group custom-title-transition" id="customTitleContainer" style="display: none;">
                                <label for="customEventTitle"><i class="fas fa-edit"></i> Custom Site Name</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <button class="btn btn-outline-secondary" type="button" id="backToDropdownBtn">
                                            <i class="fas fa-arrow-left"></i>
                                        </button>
                                    </div>
                                    <input type="text" class="form-control" id="customEventTitle" placeholder="Enter custom site name">
                                </div>
                                <small class="form-text text-muted">Create your own custom site name</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="eventDate"><i class="fas fa-calendar-alt"></i> Event Date</label>
                                <div class="input-group date">
                                    <input type="date" class="form-control" id="eventDate" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Select the date for this event</small>
                            </div>
                            
                            <!-- Vendor and Labour Section -->
                            <div class="section-divider">
                                <h5><i class="fas fa-users"></i> Vendor and Labour Section</h5>
                            </div>
                            
                            <div id="vendorsContainer">
                                <!-- Vendor items will be added here dynamically -->
                            </div>
                            
                            <div class="form-group text-center mt-4">
                                <button type="button" class="btn btn-primary" id="addVendorBtn">
                                    <i class="fas fa-plus"></i> Add Vendor
                                </button>
                            </div>
                            
                            <!-- Company Labours Section -->
                            <div class="section-divider">
                                <h5><i class="fas fa-building"></i> Company Labours</h5>
                            </div>
                            
                            <div id="companyLaboursContainer">
                                <!-- Company labour items will be added here dynamically -->
                            </div>
                            
                            <div class="form-group text-center mt-4 mb-2">
                                <button type="button" class="btn btn-primary" id="addCompanyLabourBtn">
                                    <i class="fas fa-plus"></i> Add Company Labour
                                </button>
                            </div>
                            
                            <!-- Beverages Section -->
                            <div class="section-divider">
                                <h5><i class="fas fa-coffee"></i> Beverages Section</h5>
                            </div>
                            
                            <div id="beveragesContainer">
                                <!-- Beverage items will be added here dynamically -->
                            </div>
                            
                            <div class="form-group text-center mt-4 mb-2">
                                <button type="button" class="btn btn-primary" id="addBeverageBtn">
                                    <i class="fas fa-plus"></i> Add Beverage
                                </button>
                            </div>
                            
                            <!-- Work Progress Section -->
                            <div class="section-divider">
                                <h5><i class="fas fa-chart-line"></i> Work Progress</h5>
                            </div>
                            
                            <div class="work-progress-container">
                                <!-- Monthly Targets -->
                                <div class="target-section">
                                    <div class="target-header">
                                        <i class="fas fa-calendar-alt"></i>
                                        <h6>Monthly Targets</h6>
                                    </div>
                                    <div class="target-content">
                                        <ul class="target-list">
                                            <li class="target-list-item">
                                                <span class="target-bullet target-on-track"></span>
                                                Construction Progress
                                            </li>
                                            <li class="target-list-item">
                                                <span class="target-bullet target-ahead"></span>
                                                Material Utilization
                                            </li>
                                            <li class="target-list-item">
                                                <span class="target-bullet target-behind"></span>
                                                Budget Utilization
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Weekly Targets -->
                                <div class="target-section">
                                    <div class="target-header">
                                        <i class="fas fa-calendar-week"></i>
                                        <h6>Weekly Targets</h6>
                                    </div>
                                    <div class="target-content">
                                        <ul class="target-list">
                                            <li class="target-list-item">
                                                <span class="target-bullet target-completed"></span>
                                                Foundation Work
                                            </li>
                                            <li class="target-list-item">
                                                <span class="target-bullet target-on-track"></span>
                                                Wall Construction
                                            </li>
                                            <li class="target-list-item">
                                                <span class="target-bullet target-pending"></span>
                                                Electrical Work
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Daily Targets -->
                                <div class="target-section">
                                    <div class="target-header">
                                        <i class="fas fa-calendar-day"></i>
                                        <h6>Daily Targets</h6>
                                    </div>
                                    <div class="target-content">
                                        <ul class="target-list">
                                            <li class="target-list-item">
                                                <span class="target-bullet target-on-track"></span>
                                                Concrete Pouring
                                            </li>
                                            <li class="target-list-item">
                                                <span class="target-bullet target-ahead"></span>
                                                Brick Laying
                                            </li>
                                            <li class="target-list-item">
                                                <span class="target-bullet target-completed"></span>
                                                Site Cleanup
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Work Updates Container -->
                            <div id="workUpdatesContainer">
                                <!-- Work update items will be added here dynamically -->
                            </div>
                            
                            <div class="form-group text-center mt-4 mb-2">
                                <button type="button" class="btn btn-info" id="addWorkUpdateBtn">
                                    <i class="fas fa-plus"></i> Add Work Update
                                </button>
                            </div>
                            
                            <!-- Inventory Section -->
                            <div class="section-divider">
                                <h5><i class="fas fa-boxes"></i> Inventory</h5>
                            </div>
                            
                            <!-- Inventory Container -->
                            <div id="inventoryContainer">
                                <!-- Inventory items will be added here dynamically -->
                            </div>
                            
                            <div class="form-group text-center mt-4 mb-2">
                                <button type="button" class="btn btn-info" id="addInventoryItemBtn">
                                    <i class="fas fa-plus"></i> Add Inventory Item
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveCalendarEvent">Save Event</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Append modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * Set up event listeners for the calendar event modal
 */
function setupCalendarEventModalListeners() {
    // Site selection change
    const siteSelect = document.getElementById('siteSelect');
    if (siteSelect) {
        siteSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            const customTitleContainer = document.getElementById('customTitleContainer');
            const siteSelectContainer = document.getElementById('siteSelectContainer');
            
            if (selectedValue === 'custom') {
                // Show custom title input
                toggleContainerVisibility(customTitleContainer, true);
                toggleContainerVisibility(siteSelectContainer, false);
            } else {
                // Hide custom input
                toggleContainerVisibility(customTitleContainer, false);
                toggleContainerVisibility(siteSelectContainer, true);
            }
        });
    }
    
    // Back to dropdown button
    const backToDropdownBtn = document.getElementById('backToDropdownBtn');
    if (backToDropdownBtn) {
        backToDropdownBtn.addEventListener('click', function() {
            const customTitleContainer = document.getElementById('customTitleContainer');
            const siteSelectContainer = document.getElementById('siteSelectContainer');
            
            // Show dropdown, hide custom input
            toggleContainerVisibility(customTitleContainer, false);
            toggleContainerVisibility(siteSelectContainer, true);
            
            // Reset site selection
            const siteSelect = document.getElementById('siteSelect');
            if (siteSelect) {
                siteSelect.selectedIndex = 0;
            }
        });
    }
    
    // Save event button
    const saveCalendarEventBtn = document.getElementById('saveCalendarEvent');
    if (saveCalendarEventBtn) {
        saveCalendarEventBtn.addEventListener('click', saveCalendarEvent);
    }
    
    // Add vendor button
    const addVendorBtn = document.getElementById('addVendorBtn');
    if (addVendorBtn) {
        addVendorBtn.addEventListener('click', addNewVendor);
    }
    
    // Add company labour button
    const addCompanyLabourBtn = document.getElementById('addCompanyLabourBtn');
    if (addCompanyLabourBtn) {
        addCompanyLabourBtn.addEventListener('click', addCompanyLabour);
    }
    
    // Add beverage button
    const addBeverageBtn = document.getElementById('addBeverageBtn');
    if (addBeverageBtn) {
        addBeverageBtn.addEventListener('click', addBeverage);
    }
    
    // Add work update button
    const addWorkUpdateBtn = document.getElementById('addWorkUpdateBtn');
    if (addWorkUpdateBtn) {
        addWorkUpdateBtn.addEventListener('click', addWorkUpdate);
    }
    
    // Add inventory item button
    const addInventoryItemBtn = document.getElementById('addInventoryItemBtn');
    if (addInventoryItemBtn) {
        addInventoryItemBtn.addEventListener('click', addInventoryItem);
    }
}

/**
 * Toggle container visibility with animation
 * @param {HTMLElement} container - The container element
 * @param {boolean} show - Whether to show or hide the container
 */
function toggleContainerVisibility(container, show) {
    if (!container) return;
    
    if (show) {
        container.style.display = 'block';
        // Use setTimeout to ensure the display change has taken effect
        setTimeout(() => {
            container.classList.remove('hidden-container');
        }, 10);
    } else {
        container.classList.add('hidden-container');
        // Wait for transition to complete before hiding
        setTimeout(() => {
            container.style.display = 'none';
        }, 300);
    }
}

/**
 * Show the calendar event modal with a specific date
 * @param {string} dateStr - The date in YYYY-MM-DD format
 */
function showCalendarEventModal(dateStr) {
    // Get the modal element
    const modal = document.getElementById('calendarEventModal');
    if (!modal) {
        console.error('Calendar event modal not found');
        return;
    }
    
    // Reset the form
    resetCalendarEventForm();
    
    // Set the date if provided
    if (dateStr) {
        const dateInput = document.getElementById('eventDate');
        if (dateInput) {
            dateInput.value = dateStr;
        }
    }
    
    // Show the modal
    $(modal).modal('show');
    
    // Focus on the event type dropdown
    setTimeout(() => {
        const eventTypeSelect = document.getElementById('eventType');
        if (eventTypeSelect) {
            eventTypeSelect.focus();
        }
    }, 300);
}

/**
 * Reset the calendar event form
 */
function resetCalendarEventForm() {
    // Reset form elements
    const eventForm = document.getElementById('calendarEventForm');
    if (eventForm) {
        eventForm.reset();
    }
    
    // Reset site selection
    const siteSelect = document.getElementById('siteSelect');
    if (siteSelect) {
        siteSelect.selectedIndex = 0;
    }
    
    // Hide custom title container
    const customTitleContainer = document.getElementById('customTitleContainer');
    if (customTitleContainer) {
        customTitleContainer.style.display = 'none';
    }
    
    // Show site select container
    const siteSelectContainer = document.getElementById('siteSelectContainer');
    if (siteSelectContainer) {
        siteSelectContainer.style.display = 'block';
    }
    
    // Clear vendors container
    const vendorsContainer = document.getElementById('vendorsContainer');
    if (vendorsContainer) {
        vendorsContainer.innerHTML = '';
    }
    
    // Clear company labours container
    const companyLaboursContainer = document.getElementById('companyLaboursContainer');
    if (companyLaboursContainer) {
        companyLaboursContainer.innerHTML = '';
    }
    
    // Clear beverages container
    const beveragesContainer = document.getElementById('beveragesContainer');
    if (beveragesContainer) {
        beveragesContainer.innerHTML = '';
    }
    
    // Clear work updates container
    const workUpdatesContainer = document.getElementById('workUpdatesContainer');
    if (workUpdatesContainer) {
        workUpdatesContainer.innerHTML = '';
    }
    
    // Clear inventory container
    const inventoryContainer = document.getElementById('inventoryContainer');
    if (inventoryContainer) {
        inventoryContainer.innerHTML = '';
    }
}

/**
 * Add a new vendor to the vendors container
 */
function addNewVendor() {
    const vendorsContainer = document.getElementById('vendorsContainer');
    if (!vendorsContainer) return;
    
    // Get the current vendor count
    const vendorCount = vendorsContainer.querySelectorAll('.vendor-item').length + 1;
    
    // Create a new vendor item
    const vendorItem = document.createElement('div');
    vendorItem.className = 'vendor-item';
    vendorItem.innerHTML = `
        <div class="vendor-header">
            <div class="vendor-number">
                <i class="fas fa-user"></i>
                <span>Vendor ${vendorCount}</span>
            </div>
            <button type="button" class="vendor-remove" aria-label="Remove vendor">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="vendor-fields">
            <div class="form-group vendor-field" id="vendorTypeContainer${vendorCount}">
                <label for="vendorType${vendorCount}">
                    <i class="fas fa-tag"></i> Vendor Type
                </label>
                <select class="form-control vendor-type-select" id="vendorType${vendorCount}" required>
                    <option value="" disabled selected>Select Vendor Type</option>
                    <option value="material">Material Supplier</option>
                    <option value="equipment">Equipment Provider</option>
                    <option value="contractor">Contractor</option>
                    <option value="consultant">Consultant</option>
                    <option value="other">Other</option>
                    <option value="custom">Custom Type</option>
                </select>
            </div>
            
            <div class="form-group vendor-field" id="vendorCustomTypeContainer${vendorCount}" style="display: none;">
                <label for="vendorCustomType${vendorCount}">
                    <i class="fas fa-edit"></i> Custom Type
                </label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <button class="btn btn-outline-secondary back-to-type-btn" type="button" data-count="${vendorCount}">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                    </div>
                    <input type="text" class="form-control" id="vendorCustomType${vendorCount}" placeholder="Enter custom type">
                </div>
            </div>
            
            <div class="form-group vendor-field">
                <label for="vendorName${vendorCount}">
                    <i class="fas fa-building"></i> Vendor Name
                </label>
                <input type="text" class="form-control" id="vendorName${vendorCount}" placeholder="Enter vendor name" required>
            </div>
            
            <div class="form-group vendor-field">
                <label for="vendorContact${vendorCount}">
                    <i class="fas fa-phone"></i> Contact Number
                </label>
                <input type="text" class="form-control" id="vendorContact${vendorCount}" placeholder="Enter contact number">
            </div>
        </div>
        
        <div class="vendor-materials-container" id="vendorMaterialsContainer${vendorCount}">
            <!-- Vendor materials will be added here -->
        </div>
        
        <div class="vendor-labour-container" id="vendorLabourContainer${vendorCount}">
            <!-- Vendor labour will be added here -->
        </div>
        
        <div class="vendor-buttons mt-3">
            <button type="button" class="btn btn-outline-primary btn-sm add-vendor-material" data-count="${vendorCount}">
                <i class="fas fa-plus"></i> Add Vendor Material
            </button>
            <button type="button" class="btn btn-outline-info btn-sm add-vendor-labour" data-count="${vendorCount}">
                <i class="fas fa-plus"></i> Add Vendor Labour
            </button>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = vendorItem.querySelector('.vendor-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            vendorItem.remove();
            updateVendorNumbers();
        });
    }
    
    // Add event listener to vendor type select
    const vendorTypeSelect = vendorItem.querySelector(`.vendor-type-select`);
    if (vendorTypeSelect) {
        vendorTypeSelect.addEventListener('change', function() {
            const customTypeContainer = vendorItem.querySelector(`#vendorCustomTypeContainer${vendorCount}`);
            const typeContainer = vendorItem.querySelector(`#vendorTypeContainer${vendorCount}`);
            
            if (this.value === 'custom') {
                // Show custom type input
                customTypeContainer.style.display = 'block';
                typeContainer.style.display = 'none';
                
                // Focus on the custom type input
                setTimeout(() => {
                    vendorItem.querySelector(`#vendorCustomType${vendorCount}`).focus();
                }, 100);
            }
        });
    }
    
    // Add event listener to back button
    const backBtn = vendorItem.querySelector('.back-to-type-btn');
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            const vendorCount = this.getAttribute('data-count');
            const customTypeContainer = vendorItem.querySelector(`#vendorCustomTypeContainer${vendorCount}`);
            const typeContainer = vendorItem.querySelector(`#vendorTypeContainer${vendorCount}`);
            
            // Show vendor type dropdown
            customTypeContainer.style.display = 'none';
            typeContainer.style.display = 'block';
            
            // Reset vendor type select
            vendorItem.querySelector(`#vendorType${vendorCount}`).selectedIndex = 0;
        });
    }
    
    // Add event listener to add vendor material button
    const addMaterialBtn = vendorItem.querySelector('.add-vendor-material');
    if (addMaterialBtn) {
        addMaterialBtn.addEventListener('click', function() {
            const vendorCount = this.getAttribute('data-count');
            addVendorMaterial(vendorCount);
        });
    }
    
    // Add event listener to add vendor labour button
    const addLabourBtn = vendorItem.querySelector('.add-vendor-labour');
    if (addLabourBtn) {
        addLabourBtn.addEventListener('click', function() {
            const vendorCount = this.getAttribute('data-count');
            addVendorLabour(vendorCount);
        });
    }
    
    // Add the vendor item to the container
    vendorsContainer.appendChild(vendorItem);
    
    // Add animation class
    setTimeout(() => {
        vendorItem.classList.add('fade-in');
    }, 10);
}

/**
 * Update vendor numbers after removing a vendor
 */
function updateVendorNumbers() {
    const vendorsContainer = document.getElementById('vendorsContainer');
    if (!vendorsContainer) return;
    
    const vendorItems = vendorsContainer.querySelectorAll('.vendor-item');
    
    vendorItems.forEach((item, index) => {
        const number = index + 1;
        const vendorNumber = item.querySelector('.vendor-number span');
        if (vendorNumber) {
            vendorNumber.textContent = `Vendor ${number}`;
        }
        
        // Update IDs of form elements
        const vendorType = item.querySelector('select[id^="vendorType"]');
        const vendorName = item.querySelector('input[id^="vendorName"]');
        const vendorContact = item.querySelector('input[id^="vendorContact"]');
        
        if (vendorType) vendorType.id = `vendorType${number}`;
        if (vendorName) vendorName.id = `vendorName${number}`;
        if (vendorContact) vendorContact.id = `vendorContact${number}`;
    });
}

/**
 * Add vendor material to a vendor
 * @param {string} vendorCount - The vendor count
 */
function addVendorMaterial(vendorCount) {
    const materialsContainer = document.getElementById(`vendorMaterialsContainer${vendorCount}`);
    if (!materialsContainer) return;
    
    // Get the current material count
    const materialCount = materialsContainer.querySelectorAll('.vendor-material-item').length + 1;
    
    // Create a new material item
    const materialItem = document.createElement('div');
    materialItem.className = 'vendor-material-item';
    materialItem.innerHTML = `
        <div class="material-header">
            <div class="material-number">
                <i class="fas fa-box"></i>
                <span>Material ${materialCount}</span>
            </div>
            <button type="button" class="material-remove" aria-label="Remove material">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="material-fields">
            <div class="form-group material-field">
                <label for="materialPicture${vendorCount}_${materialCount}">
                    <i class="fas fa-image"></i> Material Picture/Video
                </label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" id="materialPicture${vendorCount}_${materialCount}" accept="image/*,video/*">
                    <label class="custom-file-label" for="materialPicture${vendorCount}_${materialCount}">Choose file</label>
                </div>
            </div>
            
            <div class="form-group material-field">
                <label for="materialBill${vendorCount}_${materialCount}">
                    <i class="fas fa-file-invoice"></i> Material Bill
                </label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" id="materialBill${vendorCount}_${materialCount}" accept="image/*,application/pdf">
                    <label class="custom-file-label" for="materialBill${vendorCount}_${materialCount}">Choose file</label>
                </div>
            </div>
            
            <div class="form-group material-field">
                <label for="materialRemarks${vendorCount}_${materialCount}">
                    <i class="fas fa-comment"></i> Remarks
                </label>
                <textarea class="form-control" id="materialRemarks${vendorCount}_${materialCount}" rows="2" placeholder="Enter remarks"></textarea>
            </div>
            
            <div class="form-group material-field">
                <label for="materialAmount${vendorCount}_${materialCount}">
                    <i class="fas fa-rupee-sign"></i> Amount
                </label>
                <input type="number" class="form-control" id="materialAmount${vendorCount}_${materialCount}" placeholder="Enter amount" min="0" step="0.01">
            </div>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = materialItem.querySelector('.material-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            materialItem.remove();
            updateMaterialNumbers(vendorCount);
        });
    }
    
    // Add event listeners for file inputs
    const fileInputs = materialItem.querySelectorAll('.custom-file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Choose file';
            this.nextElementSibling.textContent = fileName;
        });
    });
    
    // Add the material item to the container
    materialsContainer.appendChild(materialItem);
    
    // Move the vendor buttons to the bottom (after all materials and labour)
    const vendorItem = materialsContainer.closest('.vendor-item');
    if (vendorItem) {
        const vendorButtons = vendorItem.querySelector('.vendor-buttons');
        if (vendorButtons) {
            vendorItem.appendChild(vendorButtons);
        }
    }
    
    // Add animation class
    setTimeout(() => {
        materialItem.classList.add('fade-in');
    }, 10);
}

/**
 * Update material numbers after removing a material
 * @param {string} vendorCount - The vendor count
 */
function updateMaterialNumbers(vendorCount) {
    const materialsContainer = document.getElementById(`vendorMaterialsContainer${vendorCount}`);
    if (!materialsContainer) return;
    
    const materialItems = materialsContainer.querySelectorAll('.vendor-material-item');
    
    materialItems.forEach((item, index) => {
        const number = index + 1;
        const materialNumber = item.querySelector('.material-number span');
        if (materialNumber) {
            materialNumber.textContent = `Material ${number}`;
        }
    });
}

/**
 * Add vendor labour to a vendor
 * @param {string} vendorCount - The vendor count
 */
function addVendorLabour(vendorCount) {
    const labourContainer = document.getElementById(`vendorLabourContainer${vendorCount}`);
    if (!labourContainer) return;
    
    // Get the current labour count
    const labourCount = labourContainer.querySelectorAll('.vendor-labour-item').length + 1;
    
    // Create a new labour item
    const labourItem = document.createElement('div');
    labourItem.className = 'vendor-labour-item';
    labourItem.innerHTML = `
        <div class="labour-header">
            <div class="labour-number">
                <i class="fas fa-hard-hat"></i>
                <span>Labour ${labourCount}</span>
            </div>
            <button type="button" class="labour-remove" aria-label="Remove labour">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="labour-fields">
            <div class="form-group labour-field">
                <label for="labourName${vendorCount}_${labourCount}">
                    <i class="fas fa-user"></i> Labour Name
                </label>
                <input type="text" class="form-control" id="labourName${vendorCount}_${labourCount}" placeholder="Enter labour name" required>
            </div>
            
            <div class="form-group labour-field">
                <label for="labourContact${vendorCount}_${labourCount}">
                    <i class="fas fa-phone"></i> Contact Number
                </label>
                <input type="text" class="form-control" id="labourContact${vendorCount}_${labourCount}" placeholder="Enter contact number">
            </div>
            
            <div class="form-group labour-field">
                <label for="morningAttendance${vendorCount}_${labourCount}">
                    <i class="fas fa-sun"></i> Morning Attendance
                </label>
                <select class="form-control" id="morningAttendance${vendorCount}_${labourCount}">
                    <option value="" disabled selected>Select attendance</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="half">Half Day</option>
                </select>
            </div>
            
            <div class="form-group labour-field">
                <label for="eveningAttendance${vendorCount}_${labourCount}">
                    <i class="fas fa-moon"></i> Evening Attendance
                </label>
                <select class="form-control" id="eveningAttendance${vendorCount}_${labourCount}">
                    <option value="" disabled selected>Select attendance</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="half">Half Day</option>
                </select>
            </div>
        </div>
        
        <!-- Wages Section -->
        <div class="labour-section">
            <h6><i class="fas fa-rupee-sign"></i> Wages Details</h6>
            <div class="labour-fields">
                <div class="form-group labour-field">
                    <label for="dailyWages${vendorCount}_${labourCount}">
                        <i class="fas fa-money-bill-wave"></i> Daily Wages
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="number" class="form-control daily-wages" id="dailyWages${vendorCount}_${labourCount}" 
                            placeholder="Enter daily wages" min="0" step="0.01" data-vendor="${vendorCount}" data-labour="${labourCount}">
                    </div>
                </div>
                
                <div class="form-group labour-field">
                    <label for="totalDailyWages${vendorCount}_${labourCount}">
                        <i class="fas fa-calculator"></i> Total Daily Wages
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="text" class="form-control total-daily-wages" id="totalDailyWages${vendorCount}_${labourCount}" 
                            placeholder="Auto calculated" readonly>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overtime Section -->
        <div class="labour-section">
            <h6><i class="fas fa-clock"></i> Overtime Details</h6>
            <div class="labour-fields">
                <div class="form-group labour-field">
                    <label for="otHours${vendorCount}_${labourCount}">
                        <i class="fas fa-hourglass-half"></i> OT Hours
                    </label>
                    <select class="form-control ot-hours" id="otHours${vendorCount}_${labourCount}" data-vendor="${vendorCount}" data-labour="${labourCount}">
                        <option value="" selected>Select hours</option>
                        ${Array.from({length: 13}, (_, i) => `<option value="${i}">${i}</option>`).join('')}
                    </select>
                </div>
                
                <div class="form-group labour-field">
                    <label for="otMinutes${vendorCount}_${labourCount}">
                        <i class="fas fa-stopwatch"></i> OT Minutes
                    </label>
                    <select class="form-control ot-minutes" id="otMinutes${vendorCount}_${labourCount}" data-vendor="${vendorCount}" data-labour="${labourCount}">
                        <option value="" selected>Select minutes</option>
                        <option value="0">00</option>
                        <option value="30">30</option>
                    </select>
                </div>
                
                <div class="form-group labour-field">
                    <label for="otRate${vendorCount}_${labourCount}">
                        <i class="fas fa-hand-holding-usd"></i> OT Rate/Hour
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="number" class="form-control ot-rate" id="otRate${vendorCount}_${labourCount}" 
                            placeholder="Enter OT rate" min="0" step="0.01" data-vendor="${vendorCount}" data-labour="${labourCount}">
                    </div>
                </div>
                
                <div class="form-group labour-field">
                    <label for="otAmount${vendorCount}_${labourCount}">
                        <i class="fas fa-calculator"></i> OT Amount
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="text" class="form-control ot-amount" id="otAmount${vendorCount}_${labourCount}" 
                            placeholder="Auto calculated" readonly>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Travel Expenses Section -->
        <div class="labour-section">
            <h6><i class="fas fa-route"></i> Travel Expenses (If Any)</h6>
            <div class="labour-fields">
                <div class="form-group labour-field">
                    <label for="travelMode${vendorCount}_${labourCount}">
                        <i class="fas fa-car"></i> Mode of Transport
                    </label>
                    <select class="form-control travel-mode" id="travelMode${vendorCount}_${labourCount}" data-vendor="${vendorCount}" data-labour="${labourCount}">
                        <option value="" selected>Select mode (if applicable)</option>
                        <option value="none">None</option>
                        <option value="bus">Bus</option>
                        <option value="auto">Auto</option>
                        <option value="bike">Bike</option>
                        <option value="taxi">Taxi/Cab</option>
                        <option value="train">Train</option>
                        <option value="metro">Metro</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group labour-field">
                    <label for="travelAmount${vendorCount}_${labourCount}">
                        <i class="fas fa-money-bill-alt"></i> Travel Amount
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="number" class="form-control travel-amount" id="travelAmount${vendorCount}_${labourCount}" 
                            placeholder="Enter amount" min="0" step="0.01" data-vendor="${vendorCount}" data-labour="${labourCount}">
                    </div>
                </div>
                
                <div class="form-group labour-field travel-receipt-container" id="travelReceiptContainer${vendorCount}_${labourCount}" style="display: none;">
                    <label for="travelReceipt${vendorCount}_${labourCount}">
                        <i class="fas fa-file-invoice"></i> Travel Receipt
                    </label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input travel-receipt" id="travelReceipt${vendorCount}_${labourCount}" 
                            accept="image/*,application/pdf" data-vendor="${vendorCount}" data-labour="${labourCount}">
                        <label class="custom-file-label" for="travelReceipt${vendorCount}_${labourCount}">Choose file</label>
                    </div>
                    <small class="form-text text-muted">Please upload receipt for taxi, cab, or train travel</small>
                </div>
            </div>
        </div>
        
        <!-- Total Amount Section -->
        <div class="labour-section total-amount-section">
            <div class="labour-fields">
                <div class="form-group labour-field total-field">
                    <label for="totalAmount${vendorCount}_${labourCount}">
                        <i class="fas fa-money-check-alt"></i> Total Amount
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="text" class="form-control total-amount" id="totalAmount${vendorCount}_${labourCount}" 
                            placeholder="Auto calculated" readonly>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = labourItem.querySelector('.labour-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            labourItem.remove();
            updateLabourNumbers(vendorCount);
        });
    }
    
    // Add the labour item to the container
    labourContainer.appendChild(labourItem);
    
    // Setup calculation listeners
    setupWageCalculations(vendorCount, labourCount);
    
    // Setup travel mode change listener
    setupTravelModeListener(vendorCount, labourCount);
    
    // Move the vendor buttons to the bottom (after all materials and labour)
    const vendorItem = labourContainer.closest('.vendor-item');
    if (vendorItem) {
        const vendorButtons = vendorItem.querySelector('.vendor-buttons');
        if (vendorButtons) {
            vendorItem.appendChild(vendorButtons);
        }
    }
    
    // Add animation class
    setTimeout(() => {
        labourItem.classList.add('fade-in');
    }, 10);
}

/**
 * Setup wage and overtime calculation listeners
 * @param {string} vendorCount - The vendor count
 * @param {string} labourCount - The labour count
 */
function setupWageCalculations(vendorCount, labourCount) {
    // Get elements
    const dailyWagesInput = document.getElementById(`dailyWages${vendorCount}_${labourCount}`);
    const totalDailyWagesInput = document.getElementById(`totalDailyWages${vendorCount}_${labourCount}`);
    const morningAttendance = document.getElementById(`morningAttendance${vendorCount}_${labourCount}`);
    const eveningAttendance = document.getElementById(`eveningAttendance${vendorCount}_${labourCount}`);
    const otHoursSelect = document.getElementById(`otHours${vendorCount}_${labourCount}`);
    const otMinutesSelect = document.getElementById(`otMinutes${vendorCount}_${labourCount}`);
    const otRateInput = document.getElementById(`otRate${vendorCount}_${labourCount}`);
    const otAmountInput = document.getElementById(`otAmount${vendorCount}_${labourCount}`);
    
    // Calculate daily wages based on attendance
    const calculateDailyWages = () => {
        if (!dailyWagesInput || !totalDailyWagesInput || !morningAttendance || !eveningAttendance) return;
        
        const dailyWage = parseFloat(dailyWagesInput.value) || 0;
        const morning = morningAttendance.value;
        const evening = eveningAttendance.value;
        
        let multiplier = 0;
        
        // Calculate multiplier based on attendance
        if (morning === 'present' && evening === 'present') {
            multiplier = 1; // Full day
        } else if (morning === 'present' && evening === 'half') {
            multiplier = 0.75; // 3/4 day
        } else if (morning === 'half' && evening === 'present') {
            multiplier = 0.75; // 3/4 day
        } else if (morning === 'present' && evening === 'absent') {
            multiplier = 0.5; // Half day
        } else if (morning === 'absent' && evening === 'present') {
            multiplier = 0.5; // Half day
        } else if (morning === 'half' && evening === 'half') {
            multiplier = 0.5; // Half day
        } else if (morning === 'half' && evening === 'absent') {
            multiplier = 0.25; // 1/4 day
        } else if (morning === 'absent' && evening === 'half') {
            multiplier = 0.25; // 1/4 day
        }
        
        // Calculate total daily wages
        const totalDailyWage = dailyWage * multiplier;
        totalDailyWagesInput.value = totalDailyWage.toFixed(2);
        
        // Update total amount
        updateTotalAmountWithTravel(vendorCount, labourCount);
    };
    
    // Calculate overtime amount
    const calculateOTAmount = () => {
        if (!otHoursSelect || !otMinutesSelect || !otRateInput || !otAmountInput) return;
        
        const hours = parseInt(otHoursSelect.value) || 0;
        const minutes = parseInt(otMinutesSelect.value) || 0;
        const rate = parseFloat(otRateInput.value) || 0;
        
        // Calculate total hours (hours + minutes as fraction of hour)
        const totalHours = hours + (minutes / 60);
        
        // Calculate OT amount
        const otAmount = totalHours * rate;
        otAmountInput.value = otAmount.toFixed(2);
        
        // Update total amount
        updateTotalAmountWithTravel(vendorCount, labourCount);
    };
    
    // Add event listeners
    if (dailyWagesInput) {
        dailyWagesInput.addEventListener('input', calculateDailyWages);
    }
    
    if (morningAttendance) {
        morningAttendance.addEventListener('change', calculateDailyWages);
    }
    
    if (eveningAttendance) {
        eveningAttendance.addEventListener('change', calculateDailyWages);
    }
    
    if (otHoursSelect) {
        otHoursSelect.addEventListener('change', calculateOTAmount);
    }
    
    if (otMinutesSelect) {
        otMinutesSelect.addEventListener('change', calculateOTAmount);
    }
    
    if (otRateInput) {
        otRateInput.addEventListener('input', calculateOTAmount);
    }
}

/**
 * Update labour numbers after removing a labour
 * @param {string} vendorCount - The vendor count
 */
function updateLabourNumbers(vendorCount) {
    const labourContainer = document.getElementById(`vendorLabourContainer${vendorCount}`);
    if (!labourContainer) return;
    
    const labourItems = labourContainer.querySelectorAll('.vendor-labour-item');
    
    labourItems.forEach((item, index) => {
        const number = index + 1;
        const labourNumber = item.querySelector('.labour-number span');
        if (labourNumber) {
            labourNumber.textContent = `Labour ${number}`;
        }
    });
}

/**
 * Setup travel mode change listener
 * @param {string} vendorCount - The vendor count
 * @param {string} labourCount - The labour count
 */
function setupTravelModeListener(vendorCount, labourCount) {
    const travelModeSelect = document.getElementById(`travelMode${vendorCount}_${labourCount}`);
    const travelReceiptContainer = document.getElementById(`travelReceiptContainer${vendorCount}_${labourCount}`);
    const travelAmountInput = document.getElementById(`travelAmount${vendorCount}_${labourCount}`);
    
    if (travelModeSelect && travelReceiptContainer) {
        travelModeSelect.addEventListener('change', function() {
            const selectedMode = this.value;
            
            // Show receipt upload for taxi, cab, or train
            if (['taxi', 'train'].includes(selectedMode)) {
                // Show with animation
                travelReceiptContainer.style.display = 'block';
                setTimeout(() => {
                    travelReceiptContainer.classList.add('show');
                }, 10);
            } else {
                // Hide with animation
                travelReceiptContainer.classList.remove('show');
                setTimeout(() => {
                    travelReceiptContainer.style.display = 'none';
                }, 300);
            }
            
            // Update total amount when travel mode changes
            updateTotalAmountWithTravel(vendorCount, labourCount);
        });
    }
    
    if (travelAmountInput) {
        travelAmountInput.addEventListener('input', function() {
            updateTotalAmountWithTravel(vendorCount, labourCount);
        });
    }
}

/**
 * Update total amount including travel expenses
 * @param {string} vendorCount - The vendor count
 * @param {string} labourCount - The labour count
 */
function updateTotalAmountWithTravel(vendorCount, labourCount) {
    const totalDailyWagesInput = document.getElementById(`totalDailyWages${vendorCount}_${labourCount}`);
    const otAmountInput = document.getElementById(`otAmount${vendorCount}_${labourCount}`);
    const travelAmountInput = document.getElementById(`travelAmount${vendorCount}_${labourCount}`);
    const totalAmountInput = document.getElementById(`totalAmount${vendorCount}_${labourCount}`);
    
    if (!totalDailyWagesInput || !otAmountInput || !travelAmountInput || !totalAmountInput) return;
    
    const totalDailyWages = parseFloat(totalDailyWagesInput.value) || 0;
    const otAmount = parseFloat(otAmountInput.value) || 0;
    const travelAmount = parseFloat(travelAmountInput.value) || 0;
    
    // Calculate total amount including travel expenses
    const totalAmount = totalDailyWages + otAmount + travelAmount;
    totalAmountInput.value = totalAmount.toFixed(2);
}

/**
 * Save the calendar event data
 */
function saveCalendarEvent() {
    // Get form elements
    const eventForm = document.getElementById('calendarEventForm');
    
    // Check if form exists
    if (!eventForm) {
        console.error('Calendar event form not found');
        showCalendarNotification('Error: Form not found', 'error');
        return;
    }
    
    // Check form validity
    if (!eventForm.checkValidity()) {
        eventForm.reportValidity();
        return;
    }
    
    // Create FormData object to handle file uploads
    const formData = new FormData();
    
    // Add basic event data
    const eventDateElement = document.getElementById('eventDate');
    if (!eventDateElement) {
        console.error('Event date element not found');
        showCalendarNotification('Error: Event date field not found', 'error');
        return;
    }
    const eventDate = eventDateElement.value;
    formData.append('event_date', eventDate);
    
    // Get event title based on selection
    let eventTitle = '';
    let isCustomTitle = false;
    const siteSelectElement = document.getElementById('siteSelect');
    if (!siteSelectElement) {
        console.error('Site select element not found');
        showCalendarNotification('Error: Site selection field not found', 'error');
        return;
    }
    
    const selectedSite = siteSelectElement.value;
    
    if (selectedSite === 'custom') {
        // Get custom title from input
        const customTitleElement = document.getElementById('customEventTitle');
        if (!customTitleElement) {
            console.error('Custom title element not found');
            showCalendarNotification('Error: Custom title field not found', 'error');
            return;
        }
        eventTitle = customTitleElement.value;
        isCustomTitle = true;
    } else if (selectedSite.startsWith('custom-')) {
        // Get title from custom titles array
        const customIndex = parseInt(selectedSite.split('-')[1]);
        if (CUSTOM_TITLES[customIndex]) {
            eventTitle = CUSTOM_TITLES[customIndex];
        } else {
            console.error('Custom title not found in array');
            showCalendarNotification('Error: Custom title not found', 'error');
            return;
        }
    } else {
        // Get title from selected option
        if (!siteSelectElement.options || !siteSelectElement.selectedIndex) {
            console.error('Site select options not found');
            showCalendarNotification('Error: Site options not found', 'error');
            return;
        }
        eventTitle = siteSelectElement.options[siteSelectElement.selectedIndex].text;
    }
    
    formData.append('event_title', eventTitle);
    
    // If this is a new custom title, add a flag to save it
    if (isCustomTitle) {
        formData.append('save_custom_title', '1');
    }
    
    // Safely get element values with null checks
    const getElementValueSafely = (id, defaultValue = '') => {
        const element = document.getElementById(id);
        return element ? element.value : defaultValue;
    };
    
    formData.append('event_description', getElementValueSafely('eventDescription'));
    formData.append('event_location', getElementValueSafely('eventLocation'));
    formData.append('event_start_time', getElementValueSafely('eventStartTime'));
    formData.append('event_end_time', getElementValueSafely('eventEndTime'));
    
    // Process vendors
    const vendorsContainer = document.getElementById('vendorsContainer');
    if (vendorsContainer) {
        const vendorItems = vendorsContainer.querySelectorAll('.vendor-item');
        formData.append('vendor_count', vendorItems.length);
        
        vendorItems.forEach((vendorItem, index) => {
            const vendorNumber = index + 1;
            
            // Add vendor details
            const vendorTypeElement = vendorItem.querySelector(`#vendorType${vendorNumber}`);
            const vendorType = vendorTypeElement ? vendorTypeElement.value : 'regular';
            
            // Check if it's a custom type
            if (vendorType === 'custom') {
                const customTypeElement = vendorItem.querySelector(`#vendorCustomType${vendorNumber}`);
                const customType = customTypeElement && customTypeElement.value ? customTypeElement.value : '';
                formData.append(`vendor_type_${vendorNumber}`, customType);
            } else {
                formData.append(`vendor_type_${vendorNumber}`, vendorType);
            }
            
            // Safely get vendor name and contact
            const vendorNameElement = vendorItem.querySelector(`#vendorName${vendorNumber}`);
            const vendorContactElement = vendorItem.querySelector(`#vendorContact${vendorNumber}`);
            
            formData.append(`vendor_name_${vendorNumber}`, vendorNameElement ? vendorNameElement.value : '');
            formData.append(`contact_number_${vendorNumber}`, vendorContactElement ? vendorContactElement.value : '');
            
            // Process materials
            const materialsContainer = vendorItem.querySelector('.vendor-materials-container');
            if (materialsContainer) {
                const materialItems = materialsContainer.querySelectorAll('.vendor-material-item');
                formData.append(`material_count_${vendorNumber}`, materialItems.length);
                
                materialItems.forEach((materialItem, matIndex) => {
                    const materialNumber = matIndex + 1;
                    const material_key = `material_${vendorNumber}_${materialNumber}`;
                    
                    // Add material details safely
                    const materialRemarksElement = materialItem.querySelector(`#materialRemarks${vendorNumber}_${materialNumber}`);
                    const materialAmountElement = materialItem.querySelector(`#materialAmount${vendorNumber}_${materialNumber}`);
                    
                    formData.append(`remarks_${material_key}`, materialRemarksElement ? materialRemarksElement.value : '');
                    formData.append(`amount_${material_key}`, materialAmountElement ? materialAmountElement.value : '0');
                    
                    // Add material images if any
                    const materialImageInput = materialItem.querySelector(`#materialPicture${vendorNumber}_${materialNumber}`);
                    if (materialImageInput && materialImageInput.files && materialImageInput.files.length > 0) {
                        formData.append(`material_images_${material_key}`, materialImageInput.files[0]);
                    }
                    
                    // Add bill image if any
                    const billImageInput = materialItem.querySelector(`#materialBill${vendorNumber}_${materialNumber}`);
                    if (billImageInput && billImageInput.files && billImageInput.files.length > 0) {
                        formData.append(`bill_image_${material_key}`, billImageInput.files[0]);
                    }
                });
            }
            
            // Process labours
            const laboursContainer = vendorItem.querySelector('.vendor-labour-container');
            if (laboursContainer) {
                const labourItems = laboursContainer.querySelectorAll('.vendor-labour-item');
                formData.append(`labour_count_${vendorNumber}`, labourItems.length);
                
                labourItems.forEach((labourItem, labIndex) => {
                    const labourNumber = labIndex + 1;
                    const labour_key = `labour_${vendorNumber}_${labourNumber}`;
                    
                    // Add labour details safely
                    const labourNameElement = labourItem.querySelector(`#labourName${vendorNumber}_${labourNumber}`);
                    const labourContactElement = labourItem.querySelector(`#labourContact${vendorNumber}_${labourNumber}`);
                    
                    formData.append(`labour_name_${labour_key}`, labourNameElement ? labourNameElement.value : '');
                    formData.append(`labour_number_${labour_key}`, labourContactElement ? labourContactElement.value : '');
                    
                    // Add attendance
                    const morningAttendance = labourItem.querySelector(`#morningAttendance${vendorNumber}_${labourNumber}`);
                    const eveningAttendance = labourItem.querySelector(`#eveningAttendance${vendorNumber}_${labourNumber}`);
                    formData.append(`morning_attendance_${labour_key}`, morningAttendance && morningAttendance.value ? morningAttendance.value : 'present');
                    formData.append(`evening_attendance_${labour_key}`, eveningAttendance && eveningAttendance.value ? eveningAttendance.value : 'present');
                    
                    // Add wage details if available
                    const dailyWage = labourItem.querySelector(`#dailyWages${vendorNumber}_${labourNumber}`);
                    if (dailyWage) {
                        const getValueSafely = (selector, defaultValue = '0') => {
                            const element = labourItem.querySelector(selector);
                            return element ? element.value : defaultValue;
                        };
                        
                        formData.append(`daily_wage_${labour_key}`, dailyWage.value);
                        formData.append(`total_day_wage_${labour_key}`, getValueSafely(`#totalDailyWages${vendorNumber}_${labourNumber}`));
                        formData.append(`ot_hours_${labour_key}`, getValueSafely(`#otHours${vendorNumber}_${labourNumber}`));
                        formData.append(`ot_minutes_${labour_key}`, getValueSafely(`#otMinutes${vendorNumber}_${labourNumber}`));
                        formData.append(`ot_rate_${labour_key}`, getValueSafely(`#otRate${vendorNumber}_${labourNumber}`));
                        formData.append(`total_ot_amount_${labour_key}`, getValueSafely(`#otAmount${vendorNumber}_${labourNumber}`));
                        formData.append(`transport_mode_${labour_key}`, getValueSafely(`#travelMode${vendorNumber}_${labourNumber}`, ''));
                        formData.append(`travel_amount_${labour_key}`, getValueSafely(`#travelAmount${vendorNumber}_${labourNumber}`));
                        formData.append(`grand_total_${labour_key}`, getValueSafely(`#totalAmount${vendorNumber}_${labourNumber}`));
                        
                        // Add travel receipt if any
                        const travelReceiptInput = labourItem.querySelector(`#travelReceipt${vendorNumber}_${labourNumber}`);
                        if (travelReceiptInput && travelReceiptInput.files && travelReceiptInput.files.length > 0) {
                            formData.append(`travel_receipt_${labour_key}`, travelReceiptInput.files[0]);
                        }
                    }
                });
            }
        });
    }
    
    // Process company labours
    const companyLaboursContainer = document.getElementById('companyLaboursContainer');
    if (companyLaboursContainer) {
        const labourItems = companyLaboursContainer.querySelectorAll('.company-labour-item');
        formData.append('company_labour_count', labourItems.length);
        
        labourItems.forEach((labourItem, index) => {
            const labourNumber = index + 1;
            
            // Add labour details safely
            const getValueSafely = (selector, defaultValue = '') => {
                const element = labourItem.querySelector(selector);
                return element ? element.value : defaultValue;
            };
            
            formData.append(`company_labour_name_${labourNumber}`, getValueSafely(`#companyLabourName_${labourNumber}`));
            formData.append(`company_labour_number_${labourNumber}`, getValueSafely(`#companyLabourNumber_${labourNumber}`));
            
            // Add attendance
            const morningAttendance = labourItem.querySelector(`#companyMorningAttendance_${labourNumber}`);
            const eveningAttendance = labourItem.querySelector(`#companyEveningAttendance_${labourNumber}`);
            formData.append(`company_morning_attendance_${labourNumber}`, morningAttendance && morningAttendance.value ? morningAttendance.value : 'present');
            formData.append(`company_evening_attendance_${labourNumber}`, eveningAttendance && eveningAttendance.value ? eveningAttendance.value : 'present');
            
            // Add wage details if available
            const dailyWage = labourItem.querySelector(`#companyDailyWages_${labourNumber}`);
            if (dailyWage) {
                formData.append(`company_daily_wage_${labourNumber}`, dailyWage.value);
                formData.append(`company_total_day_wage_${labourNumber}`, getValueSafely(`#companyTotalDailyWages_${labourNumber}`, '0'));
                formData.append(`company_ot_hours_${labourNumber}`, getValueSafely(`#companyOtHours_${labourNumber}`, '0'));
                formData.append(`company_ot_minutes_${labourNumber}`, getValueSafely(`#companyOtMinutes_${labourNumber}`, '0'));
                formData.append(`company_ot_rate_${labourNumber}`, getValueSafely(`#companyOtRate_${labourNumber}`, '0'));
                formData.append(`company_total_ot_amount_${labourNumber}`, getValueSafely(`#companyOtAmount_${labourNumber}`, '0'));
                formData.append(`company_transport_mode_${labourNumber}`, getValueSafely(`#companyTravelMode_${labourNumber}`, ''));
                formData.append(`company_travel_amount_${labourNumber}`, getValueSafely(`#companyTravelAmount_${labourNumber}`, '0'));
                formData.append(`company_grand_total_${labourNumber}`, getValueSafely(`#companyTotalAmount_${labourNumber}`, '0'));
                
                // Add travel receipt if any
                const travelReceiptInput = labourItem.querySelector(`#companyTravelReceipt_${labourNumber}`);
                if (travelReceiptInput && travelReceiptInput.files && travelReceiptInput.files.length > 0) {
                    formData.append(`company_travel_receipt_${labourNumber}`, travelReceiptInput.files[0]);
                }
            }
        });
    }
    
    // Process beverages
    const beveragesContainer = document.getElementById('beveragesContainer');
    if (beveragesContainer) {
        const beverageItems = beveragesContainer.querySelectorAll('.beverage-item');
        formData.append('beverage_count', beverageItems.length);
        
        beverageItems.forEach((beverageItem, index) => {
            const beverageNumber = index + 1;
            
            // Add beverage details safely
            const getValueSafely = (selector, defaultValue = '') => {
                const element = beverageItem.querySelector(selector);
                return element ? element.value : defaultValue;
            };
            
            formData.append(`beverage_type_${beverageNumber}`, getValueSafely(`#beverageType_${beverageNumber}`));
            formData.append(`beverage_name_${beverageNumber}`, getValueSafely(`#beverageName_${beverageNumber}`));
            formData.append(`beverage_amount_${beverageNumber}`, getValueSafely(`#beverageAmount_${beverageNumber}`, '0'));
        });
    }
    
    // Process work progress updates
    const workUpdatesContainer = document.getElementById('workUpdatesContainer');
    if (workUpdatesContainer) {
        const updateItems = workUpdatesContainer.querySelectorAll('.work-update-item');
        formData.append('work_progress_count', updateItems.length);
        
        updateItems.forEach((updateItem, index) => {
            const updateNumber = index + 1;
            
            // Get work category details safely
            const workCategorySelect = updateItem.querySelector(`#workCategory_${updateNumber}`);
            const workCategory = workCategorySelect && workCategorySelect.value ? workCategorySelect.value : '';
            
            const customCategoryElement = updateItem.querySelector(`#customCategory_${updateNumber}`);
            const customCategory = customCategoryElement && customCategoryElement.value ? customCategoryElement.value : '';
            
            // Get work type details safely
            const workTypeSelect = updateItem.querySelector(`#workType_${updateNumber}`);
            const workType = workTypeSelect && workTypeSelect.value ? workTypeSelect.value : '';
            
            const customTypeElement = updateItem.querySelector(`#customType_${updateNumber}`);
            const customType = customTypeElement && customTypeElement.value ? customTypeElement.value : '';
            
            // Get other details safely
            const workDoneSelect = updateItem.querySelector(`#workDone_${updateNumber}`);
            const workDone = workDoneSelect && workDoneSelect.value ? workDoneSelect.value : 'yes';
            
            const workRemarksElement = updateItem.querySelector(`#workRemarks_${updateNumber}`);
            const workRemarks = workRemarksElement && workRemarksElement.value ? workRemarksElement.value : '';
            
            // Add work progress details
            formData.append(`work_category_${updateNumber}`, workCategory === 'custom' ? customCategory : workCategory);
            formData.append(`work_type_${updateNumber}`, workType === 'custom' ? customType : workType);
            formData.append(`work_done_${updateNumber}`, workDone);
            formData.append(`work_remarks_${updateNumber}`, workRemarks);
            
            // Process media files
            const mediaContainer = updateItem.querySelector(`#workMediaItems_${updateNumber}`);
            if (mediaContainer) {
                const mediaItems = mediaContainer.querySelectorAll('.work-media-item');
                formData.append(`work_media_count_${updateNumber}`, mediaItems.length);
                
                mediaItems.forEach((mediaItem, mediaIndex) => {
                    const mediaNumber = mediaIndex + 1;
                    const mediaKey = `work_media_${updateNumber}_${mediaNumber}`;
                    
                    // Add media file if any
                    const fileInput = mediaItem.querySelector(`#workMedia_${updateNumber}_${mediaNumber}`);
                    if (fileInput && fileInput.files && fileInput.files.length > 0) {
                        formData.append(mediaKey, fileInput.files[0]);
                    }
                });
            }
        });
    }
    
    // Process inventory items
    const inventoryContainer = document.getElementById('inventoryContainer');
    if (inventoryContainer) {
        const inventoryItems = inventoryContainer.querySelectorAll('.inventory-item');
        formData.append('inventory_count', inventoryItems.length);
        
        inventoryItems.forEach((inventoryItem, index) => {
            const itemNumber = index + 1;
            
            // Get inventory details safely
            const getValueSafely = (selector, defaultValue = '') => {
                const element = inventoryItem.querySelector(selector);
                return element && element.value ? element.value : defaultValue;
            };
            
            const inventoryTypeSelect = inventoryItem.querySelector(`#inventoryType_${itemNumber}`);
            const inventoryType = inventoryTypeSelect && inventoryTypeSelect.value ? inventoryTypeSelect.value : 'received';
            
            const buildingMaterialSelect = inventoryItem.querySelector(`#buildingMaterial_${itemNumber}`);
            const buildingMaterial = buildingMaterialSelect && buildingMaterialSelect.value ? buildingMaterialSelect.value : '';
            
            const customMaterial = getValueSafely(`#customMaterial_${itemNumber}`);
            const inventoryQuantity = getValueSafely(`#inventoryQuantity_${itemNumber}`, '0');
            const inventoryUnit = getValueSafely(`#inventoryUnit_${itemNumber}`);
            const remainingQuantity = getValueSafely(`#remainingQuantity_${itemNumber}`, '0');
            const inventoryRemarks = getValueSafely(`#inventoryRemarks_${itemNumber}`);
            
            // Add inventory details
            formData.append(`inventory_type_${itemNumber}`, inventoryType);
            formData.append(`material_type_${itemNumber}`, buildingMaterial === 'custom' ? customMaterial : buildingMaterial);
            formData.append(`quantity_${itemNumber}`, inventoryQuantity);
            formData.append(`unit_${itemNumber}`, inventoryUnit);
            formData.append(`remaining_quantity_${itemNumber}`, remainingQuantity);
            formData.append(`inventory_remarks_${itemNumber}`, inventoryRemarks);
            
            // Add bill upload if any
            const billUpload = inventoryItem.querySelector(`#billUpload_${itemNumber}`);
            if (billUpload && billUpload.files && billUpload.files.length > 0) {
                formData.append(`inventory_bill_${itemNumber}`, billUpload.files[0]);
            }
            
            // Process material media files
            const mediaContainer = inventoryItem.querySelector(`#materialMediaItems_${itemNumber}`);
            if (mediaContainer) {
                const mediaItems = mediaContainer.querySelectorAll('.material-media-item');
                formData.append(`inventory_media_count_${itemNumber}`, mediaItems.length);
                
                mediaItems.forEach((mediaItem, mediaIndex) => {
                    const mediaNumber = mediaIndex + 1;
                    const mediaKey = `inventory_media_${itemNumber}_${mediaNumber}`;
                    
                    // Add media file if any
                    const fileInput = mediaItem.querySelector(`#materialMedia_${itemNumber}_${mediaNumber}`);
                    if (fileInput && fileInput.files && fileInput.files.length > 0) {
                        formData.append(mediaKey, fileInput.files[0]);
                    }
                });
            }
        });
    }
    
    // Show loading indicator
    showCalendarNotification('Saving event data...', 'info');
    
    // Send data to server
    fetch('backend/save_calendar_event.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server responded with status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Server response:', data);
        
        if (data.status === 'success') {
            // Show success notification
            showCalendarNotification('Event saved successfully!', 'success');
            
            // Close the modal using Bootstrap 4 API
            const calendarEventModal = document.getElementById('calendarEventModal');
            if (calendarEventModal) {
                try {
                    // Use jQuery for Bootstrap 4 modal
                    $(calendarEventModal).modal('hide');
                } catch (error) {
                    console.error('Error closing modal:', error);
                    // Fallback method if jQuery fails
                    const closeButton = calendarEventModal.querySelector('[data-dismiss="modal"]');
                    if (closeButton) {
                        closeButton.click();
                    }
                }
            }
            
            // Refresh calendar (if needed)
            if (typeof refreshCalendar === 'function') {
                refreshCalendar();
            }
        } else {
            // Show error notification
            showCalendarNotification(`Error: ${data.message || 'Unknown error occurred'}`, 'error');
        }
    })
    .catch(error => {
        console.error('Error saving event:', error);
        showCalendarNotification(`Error: ${error.message}`, 'error');
    });
}

/**
 * Format date as YYYY-MM-DD
 * @param {Date} date - The date to format
 * @returns {string} Formatted date string
 */
function formatDateToYYYYMMDD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Show a notification message
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (info, success, error, warning)
 */
function showCalendarNotification(message, type = 'info') {
    // Check if the notification container exists
    let notificationContainer = document.querySelector('.notification-container');
    
    // Create notification container if it doesn't exist
    if (!notificationContainer) {
        notificationContainer = document.createElement('div');
        notificationContainer.className = 'notification-container';
        document.body.appendChild(notificationContainer);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Set icon based on notification type
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';
    
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        <div class="notification-message">${message}</div>
    `;
    
    // Add to container
    notificationContainer.appendChild(notification);
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/**
 * Add a company labour to the company labours container
 */
function addCompanyLabour() {
    const companyLaboursContainer = document.getElementById('companyLaboursContainer');
    if (!companyLaboursContainer) return;
    
    // Get the current company labour count
    const labourCount = companyLaboursContainer.querySelectorAll('.company-labour-item').length + 1;
    
    // Create a new company labour item
    const labourItem = document.createElement('div');
    labourItem.className = 'company-labour-item';
    labourItem.innerHTML = `
        <div class="labour-header">
            <div class="labour-number">
                <i class="fas fa-user-tie"></i>
                <span>Company Labour ${labourCount}</span>
            </div>
            <button type="button" class="labour-remove" aria-label="Remove labour">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="labour-fields">
            <div class="form-group labour-field">
                <label for="companyLabourName_${labourCount}">
                    <i class="fas fa-user"></i> Labour Name
                </label>
                <input type="text" class="form-control" id="companyLabourName_${labourCount}" placeholder="Enter labour name" required>
            </div>
            
            <div class="form-group labour-field">
                <label for="companyLabourNumber_${labourCount}">
                    <i class="fas fa-phone"></i> Contact Number
                </label>
                <input type="text" class="form-control" id="companyLabourNumber_${labourCount}" placeholder="Enter contact number">
            </div>
            
            <div class="form-group labour-field">
                <label for="companyMorningAttendance_${labourCount}">
                    <i class="fas fa-sun"></i> Morning Attendance
                </label>
                <select class="form-control" id="companyMorningAttendance_${labourCount}">
                    <option value="" disabled selected>Select attendance</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="half">Half Day</option>
                </select>
            </div>
            
            <div class="form-group labour-field">
                <label for="companyEveningAttendance_${labourCount}">
                    <i class="fas fa-moon"></i> Evening Attendance
                </label>
                <select class="form-control" id="companyEveningAttendance_${labourCount}">
                    <option value="" disabled selected>Select attendance</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="half">Half Day</option>
                </select>
            </div>
        </div>
        
        <!-- Wages Section -->
        <div class="labour-section">
            <h6><i class="fas fa-rupee-sign"></i> Wages Details</h6>
            <div class="labour-fields">
                <div class="form-group labour-field">
                    <label for="companyDailyWages_${labourCount}">
                        <i class="fas fa-money-bill-wave"></i> Daily Wages
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="number" class="form-control daily-wages" id="companyDailyWages_${labourCount}" 
                            placeholder="Enter daily wages" min="0" step="0.01" data-labour="${labourCount}">
                    </div>
                </div>
                
                <div class="form-group labour-field">
                    <label for="companyTotalDailyWages_${labourCount}">
                        <i class="fas fa-calculator"></i> Total Daily Wages
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="text" class="form-control total-daily-wages" id="companyTotalDailyWages_${labourCount}" 
                            placeholder="Auto calculated" readonly>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overtime Section -->
        <div class="labour-section">
            <h6><i class="fas fa-clock"></i> Overtime Details</h6>
            <div class="labour-fields">
                <div class="form-group labour-field">
                    <label for="companyOtHours_${labourCount}">
                        <i class="fas fa-hourglass-half"></i> OT Hours
                    </label>
                    <select class="form-control ot-hours" id="companyOtHours_${labourCount}" data-labour="${labourCount}">
                        <option value="" selected>Select hours</option>
                        ${Array.from({length: 13}, (_, i) => `<option value="${i}">${i}</option>`).join('')}
                    </select>
                </div>
                
                <div class="form-group labour-field">
                    <label for="companyOtMinutes_${labourCount}">
                        <i class="fas fa-stopwatch"></i> OT Minutes
                    </label>
                    <select class="form-control ot-minutes" id="companyOtMinutes_${labourCount}" data-labour="${labourCount}">
                        <option value="" selected>Select minutes</option>
                        <option value="0">00</option>
                        <option value="30">30</option>
                    </select>
                </div>
                
                <div class="form-group labour-field">
                    <label for="companyOtRate_${labourCount}">
                        <i class="fas fa-hand-holding-usd"></i> OT Rate/Hour
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="number" class="form-control ot-rate" id="companyOtRate_${labourCount}" 
                            placeholder="Enter OT rate" min="0" step="0.01" data-labour="${labourCount}">
                    </div>
                </div>
                
                <div class="form-group labour-field">
                    <label for="companyOtAmount_${labourCount}">
                        <i class="fas fa-calculator"></i> OT Amount
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="text" class="form-control ot-amount" id="companyOtAmount_${labourCount}" 
                            placeholder="Auto calculated" readonly>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Travel Expenses Section -->
        <div class="labour-section">
            <h6><i class="fas fa-route"></i> Travel Expenses (If Any)</h6>
            <div class="labour-fields">
                <div class="form-group labour-field">
                    <label for="companyTravelMode_${labourCount}">
                        <i class="fas fa-car"></i> Mode of Transport
                    </label>
                    <select class="form-control travel-mode" id="companyTravelMode_${labourCount}" data-labour="${labourCount}">
                        <option value="" selected>Select mode (if applicable)</option>
                        <option value="none">None</option>
                        <option value="bus">Bus</option>
                        <option value="auto">Auto</option>
                        <option value="bike">Bike</option>
                        <option value="taxi">Taxi/Cab</option>
                        <option value="train">Train</option>
                        <option value="metro">Metro</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group labour-field">
                    <label for="companyTravelAmount_${labourCount}">
                        <i class="fas fa-money-bill-alt"></i> Travel Amount
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="number" class="form-control travel-amount" id="companyTravelAmount_${labourCount}" 
                            placeholder="Enter amount" min="0" step="0.01" data-labour="${labourCount}">
                    </div>
                </div>
                
                <div class="form-group labour-field travel-receipt-container" id="companyTravelReceiptContainer_${labourCount}" style="display: none;">
                    <label for="companyTravelReceipt_${labourCount}">
                        <i class="fas fa-file-invoice"></i> Travel Receipt
                    </label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input travel-receipt" id="companyTravelReceipt_${labourCount}" 
                            accept="image/*,application/pdf" data-labour="${labourCount}">
                        <label class="custom-file-label" for="companyTravelReceipt_${labourCount}">Choose file</label>
                    </div>
                    <small class="form-text text-muted">Please upload receipt for taxi, cab, or train travel</small>
                </div>
            </div>
        </div>
        
        <!-- Total Amount Section -->
        <div class="labour-section total-amount-section">
            <div class="labour-fields">
                <div class="form-group labour-field total-field">
                    <label for="companyTotalAmount_${labourCount}">
                        <i class="fas fa-money-check-alt"></i> Total Amount
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₹</span>
                        </div>
                        <input type="text" class="form-control total-amount" id="companyTotalAmount_${labourCount}" 
                            placeholder="Auto calculated" readonly>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = labourItem.querySelector('.labour-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            labourItem.remove();
            updateCompanyLabourNumbers();
        });
    }
    
    // Add the labour item to the container
    companyLaboursContainer.appendChild(labourItem);
    
    // Setup calculation listeners
    setupCompanyWageCalculations(labourCount);
    
    // Setup travel mode change listener
    setupCompanyTravelModeListener(labourCount);
    
    // Add animation class
    setTimeout(() => {
        labourItem.classList.add('fade-in');
    }, 10);
}

/**
 * Update company labour numbers after removing a labour
 */
function updateCompanyLabourNumbers() {
    const companyLaboursContainer = document.getElementById('companyLaboursContainer');
    if (!companyLaboursContainer) return;
    
    const labourItems = companyLaboursContainer.querySelectorAll('.company-labour-item');
    
    labourItems.forEach((item, index) => {
        const number = index + 1;
        const labourNumber = item.querySelector('.labour-number span');
        if (labourNumber) {
            labourNumber.textContent = `Company Labour ${number}`;
        }
    });
}

/**
 * Setup wage and overtime calculation listeners for company labour
 * @param {string} labourCount - The labour count
 */
function setupCompanyWageCalculations(labourCount) {
    // Get elements
    const dailyWagesInput = document.getElementById(`companyDailyWages_${labourCount}`);
    const totalDailyWagesInput = document.getElementById(`companyTotalDailyWages_${labourCount}`);
    const morningAttendance = document.getElementById(`companyMorningAttendance_${labourCount}`);
    const eveningAttendance = document.getElementById(`companyEveningAttendance_${labourCount}`);
    const otHoursSelect = document.getElementById(`companyOtHours_${labourCount}`);
    const otMinutesSelect = document.getElementById(`companyOtMinutes_${labourCount}`);
    const otRateInput = document.getElementById(`companyOtRate_${labourCount}`);
    const otAmountInput = document.getElementById(`companyOtAmount_${labourCount}`);
    
    // Calculate daily wages based on attendance
    const calculateDailyWages = () => {
        if (!dailyWagesInput || !totalDailyWagesInput || !morningAttendance || !eveningAttendance) return;
        
        const dailyWage = parseFloat(dailyWagesInput.value) || 0;
        const morning = morningAttendance.value;
        const evening = eveningAttendance.value;
        
        let multiplier = 0;
        
        // Calculate multiplier based on attendance
        if (morning === 'present' && evening === 'present') {
            multiplier = 1; // Full day
        } else if (morning === 'present' && evening === 'half') {
            multiplier = 0.75; // 3/4 day
        } else if (morning === 'half' && evening === 'present') {
            multiplier = 0.75; // 3/4 day
        } else if (morning === 'present' && evening === 'absent') {
            multiplier = 0.5; // Half day
        } else if (morning === 'absent' && evening === 'present') {
            multiplier = 0.5; // Half day
        } else if (morning === 'half' && evening === 'half') {
            multiplier = 0.5; // Half day
        } else if (morning === 'half' && evening === 'absent') {
            multiplier = 0.25; // 1/4 day
        } else if (morning === 'absent' && evening === 'half') {
            multiplier = 0.25; // 1/4 day
        }
        
        // Calculate total daily wages
        const totalDailyWage = dailyWage * multiplier;
        totalDailyWagesInput.value = totalDailyWage.toFixed(2);
        
        // Update total amount
        updateCompanyTotalAmountWithTravel(labourCount);
    };
    
    // Calculate overtime amount
    const calculateOTAmount = () => {
        if (!otHoursSelect || !otMinutesSelect || !otRateInput || !otAmountInput) return;
        
        const hours = parseInt(otHoursSelect.value) || 0;
        const minutes = parseInt(otMinutesSelect.value) || 0;
        const rate = parseFloat(otRateInput.value) || 0;
        
        // Calculate total hours (hours + minutes as fraction of hour)
        const totalHours = hours + (minutes / 60);
        
        // Calculate OT amount
        const otAmount = totalHours * rate;
        otAmountInput.value = otAmount.toFixed(2);
        
        // Update total amount
        updateCompanyTotalAmountWithTravel(labourCount);
    };
    
    // Add event listeners
    if (dailyWagesInput) {
        dailyWagesInput.addEventListener('input', calculateDailyWages);
    }
    
    if (morningAttendance) {
        morningAttendance.addEventListener('change', calculateDailyWages);
    }
    
    if (eveningAttendance) {
        eveningAttendance.addEventListener('change', calculateDailyWages);
    }
    
    if (otHoursSelect) {
        otHoursSelect.addEventListener('change', calculateOTAmount);
    }
    
    if (otMinutesSelect) {
        otMinutesSelect.addEventListener('change', calculateOTAmount);
    }
    
    if (otRateInput) {
        otRateInput.addEventListener('input', calculateOTAmount);
    }
}

/**
 * Setup travel mode change listener for company labour
 * @param {string} labourCount - The labour count
 */
function setupCompanyTravelModeListener(labourCount) {
    const travelModeSelect = document.getElementById(`companyTravelMode_${labourCount}`);
    const travelReceiptContainer = document.getElementById(`companyTravelReceiptContainer_${labourCount}`);
    const travelAmountInput = document.getElementById(`companyTravelAmount_${labourCount}`);
    
    if (travelModeSelect && travelReceiptContainer) {
        travelModeSelect.addEventListener('change', function() {
            const selectedMode = this.value;
            
            // Show receipt upload for taxi, cab, or train
            if (['taxi', 'train'].includes(selectedMode)) {
                // Show with animation
                travelReceiptContainer.style.display = 'block';
                setTimeout(() => {
                    travelReceiptContainer.classList.add('show');
                }, 10);
            } else {
                // Hide with animation
                travelReceiptContainer.classList.remove('show');
                setTimeout(() => {
                    travelReceiptContainer.style.display = 'none';
                }, 300);
            }
            
            // Update total amount when travel mode changes
            updateCompanyTotalAmountWithTravel(labourCount);
        });
    }
    
    if (travelAmountInput) {
        travelAmountInput.addEventListener('input', function() {
            updateCompanyTotalAmountWithTravel(labourCount);
        });
    }
}

/**
 * Update total amount including travel expenses for company labour
 * @param {string} labourCount - The labour count
 */
function updateCompanyTotalAmountWithTravel(labourCount) {
    const totalDailyWagesInput = document.getElementById(`companyTotalDailyWages_${labourCount}`);
    const otAmountInput = document.getElementById(`companyOtAmount_${labourCount}`);
    const travelAmountInput = document.getElementById(`companyTravelAmount_${labourCount}`);
    const totalAmountInput = document.getElementById(`companyTotalAmount_${labourCount}`);
    
    if (!totalDailyWagesInput || !otAmountInput || !travelAmountInput || !totalAmountInput) return;
    
    const totalDailyWages = parseFloat(totalDailyWagesInput.value) || 0;
    const otAmount = parseFloat(otAmountInput.value) || 0;
    const travelAmount = parseFloat(travelAmountInput.value) || 0;
    
    // Calculate total amount including travel expenses
    const totalAmount = totalDailyWages + otAmount + travelAmount;
    totalAmountInput.value = totalAmount.toFixed(2);
}

/**
 * Add a beverage to the beverages container
 */
function addBeverage() {
    const beveragesContainer = document.getElementById('beveragesContainer');
    if (!beveragesContainer) return;
    
    // Get the current beverage count
    const beverageCount = beveragesContainer.querySelectorAll('.beverage-item').length + 1;
    
    // Create a new beverage item
    const beverageItem = document.createElement('div');
    beverageItem.className = 'beverage-item';
    beverageItem.innerHTML = `
        <div class="beverage-header">
            <div class="beverage-number">
                <i class="fas fa-glass-cheers"></i>
                <span>Beverage ${beverageCount}</span>
            </div>
            <button type="button" class="beverage-remove" aria-label="Remove beverage">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="beverage-fields">
            <div class="form-group beverage-field">
                <label for="beverageType_${beverageCount}">
                    <i class="fas fa-wine-bottle"></i> Beverage Type
                </label>
                <select class="form-control" id="beverageType_${beverageCount}" required>
                    <option value="" disabled selected>Select beverage type</option>
                    <option value="tea">Tea</option>
                    <option value="coffee">Coffee</option>
                    <option value="water">Water</option>
                    <option value="soft_drink">Soft Drink</option>
                    <option value="juice">Juice</option>
                    <option value="energy_drink">Energy Drink</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group beverage-field">
                <label for="beverageName_${beverageCount}">
                    <i class="fas fa-tag"></i> Beverage Name
                </label>
                <input type="text" class="form-control" id="beverageName_${beverageCount}" placeholder="Enter beverage name">
            </div>
            
            <div class="form-group beverage-field">
                <label for="beverageAmount_${beverageCount}">
                    <i class="fas fa-rupee-sign"></i> Amount
                </label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">₹</span>
                    </div>
                    <input type="number" class="form-control" id="beverageAmount_${beverageCount}" 
                        placeholder="Enter amount" min="0" step="0.01">
                </div>
            </div>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = beverageItem.querySelector('.beverage-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            beverageItem.remove();
            updateBeverageNumbers();
        });
    }
    
    // Add the beverage item to the container
    beveragesContainer.appendChild(beverageItem);
    
    // Add animation class
    setTimeout(() => {
        beverageItem.classList.add('fade-in');
    }, 10);
}

/**
 * Update beverage numbers after removing a beverage
 */
function updateBeverageNumbers() {
    const beveragesContainer = document.getElementById('beveragesContainer');
    if (!beveragesContainer) return;
    
    const beverageItems = beveragesContainer.querySelectorAll('.beverage-item');
    
    beverageItems.forEach((item, index) => {
        const number = index + 1;
        const beverageNumber = item.querySelector('.beverage-number span');
        if (beverageNumber) {
            beverageNumber.textContent = `Beverage ${number}`;
        }
        
        // Update IDs of form elements
        const beverageType = item.querySelector('select[id^="beverageType_"]');
        const beverageName = item.querySelector('input[id^="beverageName_"]');
        const beverageAmount = item.querySelector('input[id^="beverageAmount_"]');
        
        if (beverageType) beverageType.id = `beverageType_${number}`;
        if (beverageName) beverageName.id = `beverageName_${number}`;
        if (beverageAmount) beverageAmount.id = `beverageAmount_${number}`;
    });
}

/**
 * Add a work update to the work updates container
 */
function addWorkUpdate() {
    const workUpdatesContainer = document.getElementById('workUpdatesContainer');
    if (!workUpdatesContainer) return;
    
    // Get the current work update count
    const updateCount = workUpdatesContainer.querySelectorAll('.work-update-item').length + 1;
    
    // Create a new work update item
    const updateItem = document.createElement('div');
    updateItem.className = 'work-update-item';
    updateItem.innerHTML = `
        <div class="work-update-header">
            <div class="work-update-number">
                <i class="fas fa-tasks"></i>
                <span>Work Update ${updateCount}</span>
            </div>
            <button type="button" class="work-update-remove" aria-label="Remove work update">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="work-update-fields">
            <div class="form-group work-update-field">
                <label for="workCategory_${updateCount}">
                    <i class="fas fa-th-list"></i> Work Category
                </label>
                <select class="form-control work-category-select" id="workCategory_${updateCount}" required>
                    <option value="" disabled selected>Select work category</option>
                    <option value="civil">Civil Work</option>
                    <option value="electrical">Electrical Work</option>
                    <option value="plumbing">Plumbing Work</option>
                    <option value="painting">Painting Work</option>
                    <option value="flooring">Flooring Work</option>
                    <option value="roofing">Roofing Work</option>
                    <option value="custom">Custom Category</option>
                </select>
            </div>
            
            <div class="form-group work-update-field" id="customCategoryContainer_${updateCount}" style="display: none;">
                <label for="customCategory_${updateCount}">
                    <i class="fas fa-edit"></i> Custom Category
                </label>
                <input type="text" class="form-control" id="customCategory_${updateCount}" placeholder="Enter custom category">
            </div>
            
            <div class="form-group work-update-field">
                <label for="workType_${updateCount}">
                    <i class="fas fa-tools"></i> Type of Work
                </label>
                <select class="form-control work-type-select" id="workType_${updateCount}" required>
                    <option value="" disabled selected>Select work type</option>
                    <option value="new">New Construction</option>
                    <option value="repair">Repair Work</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="installation">Installation</option>
                    <option value="demolition">Demolition</option>
                    <option value="custom">Custom Type</option>
                </select>
            </div>
            
            <div class="form-group work-update-field" id="customTypeContainer_${updateCount}" style="display: none;">
                <label for="customType_${updateCount}">
                    <i class="fas fa-edit"></i> Custom Work Type
                </label>
                <input type="text" class="form-control" id="customType_${updateCount}" placeholder="Enter custom work type">
            </div>
            
            <div class="form-group work-update-field">
                <label for="workDone_${updateCount}">
                    <i class="fas fa-check-circle"></i> Work Done
                </label>
                <select class="form-control" id="workDone_${updateCount}" required>
                    <option value="" disabled selected>Select status</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>
            
            <div class="form-group work-update-field">
                <label for="workRemarks_${updateCount}">
                    <i class="fas fa-comment"></i> Remarks
                </label>
                <textarea class="form-control" id="workRemarks_${updateCount}" rows="2" placeholder="Enter any remarks about the work"></textarea>
            </div>
        </div>
        
        <div class="work-media-container">
            <h6 class="work-media-title"><i class="fas fa-images"></i> Photos & Videos</h6>
            <div class="work-media-items" id="workMediaItems_${updateCount}">
                <!-- Media items will be added here -->
            </div>
            <div class="work-media-actions">
                <button type="button" class="btn btn-sm btn-outline-primary add-media-btn" data-count="${updateCount}">
                    <i class="fas fa-plus"></i> Add Photo/Video
                </button>
            </div>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = updateItem.querySelector('.work-update-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            updateItem.remove();
            updateWorkUpdateNumbers();
        });
    }
    
    // Add event listener to work category select
    const categorySelect = updateItem.querySelector(`.work-category-select`);
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const customCategoryContainer = updateItem.querySelector(`#customCategoryContainer_${updateCount}`);
            
            if (this.value === 'custom') {
                // Show custom category input
                customCategoryContainer.style.display = 'block';
                
                // Focus on the custom category input
                setTimeout(() => {
                    updateItem.querySelector(`#customCategory_${updateCount}`).focus();
                }, 100);
            } else {
                // Hide custom category input
                customCategoryContainer.style.display = 'none';
            }
        });
    }
    
    // Add event listener to work type select
    const typeSelect = updateItem.querySelector(`.work-type-select`);
    if (typeSelect) {
        typeSelect.addEventListener('change', function() {
            const customTypeContainer = updateItem.querySelector(`#customTypeContainer_${updateCount}`);
            
            if (this.value === 'custom') {
                // Show custom type input
                customTypeContainer.style.display = 'block';
                
                // Focus on the custom type input
                setTimeout(() => {
                    updateItem.querySelector(`#customType_${updateCount}`).focus();
                }, 100);
            } else {
                // Hide custom type input
                customTypeContainer.style.display = 'none';
            }
        });
    }
    
    // Add event listener to add media button
    const addMediaBtn = updateItem.querySelector('.add-media-btn');
    if (addMediaBtn) {
        addMediaBtn.addEventListener('click', function() {
            const updateCount = this.getAttribute('data-count');
            addWorkMedia(updateCount);
        });
    }
    
    // Add the work update item to the container
    workUpdatesContainer.appendChild(updateItem);
    
    // Add animation class
    setTimeout(() => {
        updateItem.classList.add('fade-in');
    }, 10);
}

/**
 * Add a media item to a work update
 * @param {string} updateCount - The work update count
 */
function addWorkMedia(updateCount) {
    const mediaContainer = document.getElementById(`workMediaItems_${updateCount}`);
    if (!mediaContainer) return;
    
    // Get the current media count
    const mediaCount = mediaContainer.querySelectorAll('.work-media-item').length + 1;
    
    // Create a new media item
    const mediaItem = document.createElement('div');
    mediaItem.className = 'work-media-item';
    mediaItem.innerHTML = `
        <div class="work-media-header">
            <div class="work-media-number">
                <i class="fas fa-image"></i>
                <span>Media ${mediaCount}</span>
            </div>
            <button type="button" class="work-media-remove" aria-label="Remove media">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="work-media-content">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="workMedia_${updateCount}_${mediaCount}" 
                    accept="image/*,video/*">
                <label class="custom-file-label" for="workMedia_${updateCount}_${mediaCount}">Choose file</label>
            </div>
            <small class="form-text text-muted">Upload photo or video related to the work</small>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = mediaItem.querySelector('.work-media-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            mediaItem.remove();
            updateWorkMediaNumbers(updateCount);
        });
    }
    
    // Add event listener to file input
    const fileInput = mediaItem.querySelector('.custom-file-input');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Choose file';
            this.nextElementSibling.textContent = fileName;
        });
    }
    
    // Add the media item to the container
    mediaContainer.appendChild(mediaItem);
    
    // Add animation class
    setTimeout(() => {
        mediaItem.classList.add('fade-in');
    }, 10);
}

/**
 * Update work update numbers after removing a work update
 */
function updateWorkUpdateNumbers() {
    const workUpdatesContainer = document.getElementById('workUpdatesContainer');
    if (!workUpdatesContainer) return;
    
    const updateItems = workUpdatesContainer.querySelectorAll('.work-update-item');
    
    updateItems.forEach((item, index) => {
        const number = index + 1;
        const updateNumber = item.querySelector('.work-update-number span');
        if (updateNumber) {
            updateNumber.textContent = `Work Update ${number}`;
        }
        
        // Update IDs of form elements
        const workCategory = item.querySelector('select[id^="workCategory_"]');
        const customCategory = item.querySelector('input[id^="customCategory_"]');
        const customCategoryContainer = item.querySelector('div[id^="customCategoryContainer_"]');
        const workType = item.querySelector('select[id^="workType_"]');
        const customType = item.querySelector('input[id^="customType_"]');
        const customTypeContainer = item.querySelector('div[id^="customTypeContainer_"]');
        const workDone = item.querySelector('select[id^="workDone_"]');
        const workRemarks = item.querySelector('textarea[id^="workRemarks_"]');
        const workMediaItems = item.querySelector('div[id^="workMediaItems_"]');
        const addMediaBtn = item.querySelector('.add-media-btn');
        
        if (workCategory) workCategory.id = `workCategory_${number}`;
        if (customCategory) customCategory.id = `customCategory_${number}`;
        if (customCategoryContainer) customCategoryContainer.id = `customCategoryContainer_${number}`;
        if (workType) workType.id = `workType_${number}`;
        if (customType) customType.id = `customType_${number}`;
        if (customTypeContainer) customTypeContainer.id = `customTypeContainer_${number}`;
        if (workDone) workDone.id = `workDone_${number}`;
        if (workRemarks) workRemarks.id = `workRemarks_${number}`;
        if (workMediaItems) workMediaItems.id = `workMediaItems_${number}`;
        if (addMediaBtn) addMediaBtn.setAttribute('data-count', number);
        
        // Update media items IDs
        const mediaItems = item.querySelectorAll('.work-media-item');
        mediaItems.forEach((mediaItem, mediaIndex) => {
            const mediaNumber = mediaIndex + 1;
            const fileInput = mediaItem.querySelector('input[type="file"]');
            const fileLabel = mediaItem.querySelector('.custom-file-label');
            
            if (fileInput) {
                fileInput.id = `workMedia_${number}_${mediaNumber}`;
            }
            if (fileLabel) {
                fileLabel.setAttribute('for', `workMedia_${number}_${mediaNumber}`);
            }
        });
    });
}

/**
 * Update work media numbers after removing a media item
 * @param {string} updateCount - The work update count
 */
function updateWorkMediaNumbers(updateCount) {
    const mediaContainer = document.getElementById(`workMediaItems_${updateCount}`);
    if (!mediaContainer) return;
    
    const mediaItems = mediaContainer.querySelectorAll('.work-media-item');
    
    mediaItems.forEach((item, index) => {
        const number = index + 1;
        const mediaNumber = item.querySelector('.work-media-number span');
        if (mediaNumber) {
            mediaNumber.textContent = `Media ${number}`;
        }
        
        // Update IDs of form elements
        const fileInput = item.querySelector('.custom-file-input');
        const fileLabel = item.querySelector('.custom-file-label');
        
        if (fileInput) {
            fileInput.id = `workMedia_${updateCount}_${number}`;
        }
        if (fileLabel) {
            fileLabel.setAttribute('for', `workMedia_${updateCount}_${number}`);
        }
    });
}

/**
 * Add an inventory item to the inventory container
 */
function addInventoryItem() {
    const inventoryContainer = document.getElementById('inventoryContainer');
    if (!inventoryContainer) return;
    
    // Get the current inventory item count
    const itemCount = inventoryContainer.querySelectorAll('.inventory-item').length + 1;
    
    // Create a new inventory item
    const inventoryItem = document.createElement('div');
    inventoryItem.className = 'inventory-item';
    inventoryItem.innerHTML = `
        <div class="inventory-header">
            <div class="inventory-number">
                <i class="fas fa-box"></i>
                <span>Inventory Item ${itemCount}</span>
            </div>
            <button type="button" class="inventory-remove" aria-label="Remove inventory item">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="inventory-fields">
            <div class="form-group inventory-field">
                <label for="inventoryType_${itemCount}">
                    <i class="fas fa-exchange-alt"></i> Inventory Type
                </label>
                <select class="form-control" id="inventoryType_${itemCount}" required>
                    <option value="" disabled selected>Select type</option>
                    <option value="received">Received</option>
                    <option value="consumed">Consumed</option>
                </select>
            </div>
            
            <div class="form-group inventory-field">
                <label for="buildingMaterial_${itemCount}">
                    <i class="fas fa-hammer"></i> Building Material
                </label>
                <select class="form-control building-material-select" id="buildingMaterial_${itemCount}" required>
                    <option value="" disabled selected>Select material</option>
                    <option value="cement">Cement</option>
                    <option value="sand">Sand</option>
                    <option value="bricks">Bricks</option>
                    <option value="steel">Steel</option>
                    <option value="concrete">Concrete</option>
                    <option value="wood">Wood</option>
                    <option value="paint">Paint</option>
                    <option value="tiles">Tiles</option>
                    <option value="glass">Glass</option>
                    <option value="pipes">Pipes</option>
                    <option value="wires">Wires</option>
                    <option value="fixtures">Fixtures</option>
                    <option value="custom">Custom Material</option>
                </select>
            </div>
            
            <div class="form-group inventory-field" id="customMaterialContainer_${itemCount}" style="display: none;">
                <label for="customMaterial_${itemCount}">
                    <i class="fas fa-edit"></i> Custom Material
                </label>
                <input type="text" class="form-control" id="customMaterial_${itemCount}" placeholder="Enter custom material">
            </div>
            
            <div class="form-group inventory-field">
                <label for="inventoryQuantity_${itemCount}">
                    <i class="fas fa-balance-scale"></i> Quantity
                </label>
                <input type="number" class="form-control" id="inventoryQuantity_${itemCount}" min="0" step="0.01" required>
            </div>
            
            <div class="form-group inventory-field">
                <label for="inventoryUnit_${itemCount}">
                    <i class="fas fa-ruler-combined"></i> Unit
                </label>
                <select class="form-control" id="inventoryUnit_${itemCount}" required>
                    <option value="" disabled selected>Select unit</option>
                    <option value="kg">Kilogram (kg)</option>
                    <option value="g">Gram (g)</option>
                    <option value="ton">Ton</option>
                    <option value="lb">Pound (lb)</option>
                    <option value="pcs">Pieces (pcs)</option>
                    <option value="nos">Numbers (nos)</option>
                    <option value="m">Meter (m)</option>
                    <option value="cm">Centimeter (cm)</option>
                    <option value="ft">Feet (ft)</option>
                    <option value="in">Inch (in)</option>
                    <option value="sqm">Square Meter (sq.m)</option>
                    <option value="sqft">Square Feet (sq.ft)</option>
                    <option value="cum">Cubic Meter (cu.m)</option>
                    <option value="cuft">Cubic Feet (cu.ft)</option>
                    <option value="l">Liter (L)</option>
                    <option value="ml">Milliliter (mL)</option>
                    <option value="gal">Gallon (gal)</option>
                    <option value="bag">Bag</option>
                    <option value="roll">Roll</option>
                    <option value="bundle">Bundle</option>
                    <option value="box">Box</option>
                    <option value="drum">Drum</option>
                    <option value="truck">Truck Load</option>
                </select>
            </div>
        </div>
        
        <!-- Remaining Material Section -->
        <div class="remaining-material-section">
            <h6 class="remaining-material-title"><i class="fas fa-warehouse"></i> Remaining Material on Site</h6>
            <div class="remaining-material-fields">
                <div class="form-group">
                    <label for="remainingQuantity_${itemCount}">
                        <i class="fas fa-cubes"></i> Remaining Quantity
                    </label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="remainingQuantity_${itemCount}" min="0" step="0.01" readonly>
                        <div class="input-group-append">
                            <span class="input-group-text" id="remainingUnit_${itemCount}">-</span>
                        </div>
                    </div>
                    <small class="form-text text-muted">Automatically fetched based on selected material</small>
                </div>
            </div>
        </div>
        
        <!-- Remarks Section -->
        <div class="inventory-remarks-section">
            <div class="form-group">
                <label for="inventoryRemarks_${itemCount}">
                    <i class="fas fa-comment"></i> Remarks
                </label>
                <textarea class="form-control" id="inventoryRemarks_${itemCount}" rows="2" placeholder="Enter any remarks about this inventory item"></textarea>
            </div>
        </div>
        
        <!-- Bill Upload Section -->
        <div class="bill-upload-section">
            <h6 class="bill-upload-title"><i class="fas fa-file-invoice"></i> Upload Bill</h6>
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="billUpload_${itemCount}" accept="image/*,application/pdf">
                <label class="custom-file-label" for="billUpload_${itemCount}">Choose bill file</label>
            </div>
            <small class="form-text text-muted">Upload bill or invoice for this material (PDF or image)</small>
        </div>
        
        <!-- Material Photos & Videos Section -->
        <div class="material-media-container">
            <h6 class="material-media-title"><i class="fas fa-images"></i> Material Photos & Videos</h6>
            <div class="material-media-items" id="materialMediaItems_${itemCount}">
                <!-- Media items will be added here -->
            </div>
            <div class="material-media-actions">
                <button type="button" class="btn btn-sm btn-outline-warning add-material-media-btn" data-count="${itemCount}">
                    <i class="fas fa-plus"></i> Add Photo/Video
                </button>
            </div>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = inventoryItem.querySelector('.inventory-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            inventoryItem.remove();
            updateInventoryNumbers();
        });
    }
    
    // Add event listener to building material select
    const materialSelect = inventoryItem.querySelector('.building-material-select');
    if (materialSelect) {
        materialSelect.addEventListener('change', function() {
            const customMaterialContainer = inventoryItem.querySelector(`#customMaterialContainer_${itemCount}`);
            
            if (this.value === 'custom') {
                // Show custom material input
                customMaterialContainer.style.display = 'block';
                
                // Focus on the custom material input
                setTimeout(() => {
                    inventoryItem.querySelector(`#customMaterial_${itemCount}`).focus();
                }, 100);
            } else {
                // Hide custom material input
                customMaterialContainer.style.display = 'none';
                
                // Fetch remaining quantity for selected material
                if (this.value) {
                    fetchRemainingQuantity(this.value, itemCount);
                }
            }
        });
    }
    
    // Add event listener to custom material input
    const customMaterialInput = inventoryItem.querySelector(`#customMaterial_${itemCount}`);
    if (customMaterialInput) {
        customMaterialInput.addEventListener('change', function() {
            if (this.value) {
                // Fetch remaining quantity for custom material
                fetchRemainingQuantity(this.value, itemCount, true);
            }
        });
    }
    
    // Add event listener to unit select for updating remaining unit
    const inventoryUnitSelect = inventoryItem.querySelector(`#inventoryUnit_${itemCount}`);
    const remainingUnitSpan = inventoryItem.querySelector(`#remainingUnit_${itemCount}`);
    
    if (inventoryUnitSelect && remainingUnitSpan) {
        inventoryUnitSelect.addEventListener('change', function() {
            remainingUnitSpan.textContent = this.value || '-';
        });
    }
    
    // Add event listener to bill upload input
    const billUploadInput = inventoryItem.querySelector(`#billUpload_${itemCount}`);
    if (billUploadInput) {
        billUploadInput.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Choose bill file';
            this.nextElementSibling.textContent = fileName;
        });
    }
    
    // Add event listener to add material media button
    const addMaterialMediaBtn = inventoryItem.querySelector('.add-material-media-btn');
    if (addMaterialMediaBtn) {
        addMaterialMediaBtn.addEventListener('click', function() {
            const itemCount = this.getAttribute('data-count');
            addMaterialMedia(itemCount);
        });
    }
    
    // Add the inventory item to the container
    inventoryContainer.appendChild(inventoryItem);
    
    // Add animation class
    setTimeout(() => {
        inventoryItem.classList.add('fade-in');
    }, 10);
}

/**
 * Add a media item to a material
 * @param {string} itemCount - The inventory item count
 */
function addMaterialMedia(itemCount) {
    const mediaContainer = document.getElementById(`materialMediaItems_${itemCount}`);
    if (!mediaContainer) return;
    
    // Get the current media count
    const mediaCount = mediaContainer.querySelectorAll('.material-media-item').length + 1;
    
    // Create a new media item
    const mediaItem = document.createElement('div');
    mediaItem.className = 'material-media-item';
    mediaItem.innerHTML = `
        <div class="material-media-header">
            <div class="material-media-number">
                <i class="fas fa-image"></i>
                <span>Media ${mediaCount}</span>
            </div>
            <button type="button" class="material-media-remove" aria-label="Remove media">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="material-media-content">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="materialMedia_${itemCount}_${mediaCount}" 
                    accept="image/*,video/*">
                <label class="custom-file-label" for="materialMedia_${itemCount}_${mediaCount}">Choose file</label>
            </div>
            <small class="form-text text-muted">Upload photo or video of the material</small>
        </div>
    `;
    
    // Add event listener to remove button
    const removeBtn = mediaItem.querySelector('.material-media-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            mediaItem.remove();
            updateMaterialMediaNumbers(itemCount);
        });
    }
    
    // Add event listener to file input
    const fileInput = mediaItem.querySelector('.custom-file-input');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Choose file';
            this.nextElementSibling.textContent = fileName;
        });
    }
    
    // Add the media item to the container
    mediaContainer.appendChild(mediaItem);
    
    // Add animation class
    setTimeout(() => {
        mediaItem.classList.add('fade-in');
    }, 10);
}

/**
 * Update material media numbers after removing a media item
 * @param {string} itemCount - The inventory item count
 */
function updateMaterialMediaNumbers(itemCount) {
    const mediaContainer = document.getElementById(`materialMediaItems_${itemCount}`);
    if (!mediaContainer) return;
    
    const mediaItems = mediaContainer.querySelectorAll('.material-media-item');
    
    mediaItems.forEach((item, index) => {
        const number = index + 1;
        const mediaNumber = item.querySelector('.material-media-number span');
        if (mediaNumber) {
            mediaNumber.textContent = `Media ${number}`;
        }
        
        // Update IDs of form elements
        const fileInput = item.querySelector('.custom-file-input');
        const fileLabel = item.querySelector('.custom-file-label');
        
        if (fileInput) {
            fileInput.id = `materialMedia_${itemCount}_${number}`;
        }
        if (fileLabel) {
            fileLabel.setAttribute('for', `materialMedia_${itemCount}_${number}`);
        }
    });
}

/**
 * Update inventory item numbers after removing an item
 */
function updateInventoryNumbers() {
    const inventoryContainer = document.getElementById('inventoryContainer');
    if (!inventoryContainer) return;
    
    const inventoryItems = inventoryContainer.querySelectorAll('.inventory-item');
    
    inventoryItems.forEach((item, index) => {
        const number = index + 1;
        const inventoryNumber = item.querySelector('.inventory-number span');
        if (inventoryNumber) {
            inventoryNumber.textContent = `Inventory Item ${number}`;
        }
        
        // Update IDs of form elements
        const inventoryType = item.querySelector('select[id^="inventoryType_"]');
        const buildingMaterial = item.querySelector('select[id^="buildingMaterial_"]');
        const customMaterial = item.querySelector('input[id^="customMaterial_"]');
        const customMaterialContainer = item.querySelector('div[id^="customMaterialContainer_"]');
        const inventoryQuantity = item.querySelector('input[id^="inventoryQuantity_"]');
        const inventoryUnit = item.querySelector('select[id^="inventoryUnit_"]');
        const remainingQuantity = item.querySelector('input[id^="remainingQuantity_"]');
        const remainingUnit = item.querySelector('span[id^="remainingUnit_"]');
        const inventoryRemarks = item.querySelector('textarea[id^="inventoryRemarks_"]');
        const billUpload = item.querySelector('input[id^="billUpload_"]');
        const billUploadLabel = item.querySelector(`label[for^="billUpload_"]`);
        const materialMediaItems = item.querySelector('div[id^="materialMediaItems_"]');
        const addMaterialMediaBtn = item.querySelector('.add-material-media-btn');
        
        if (inventoryType) inventoryType.id = `inventoryType_${number}`;
        if (buildingMaterial) buildingMaterial.id = `buildingMaterial_${number}`;
        if (customMaterial) customMaterial.id = `customMaterial_${number}`;
        if (customMaterialContainer) customMaterialContainer.id = `customMaterialContainer_${number}`;
        if (inventoryQuantity) inventoryQuantity.id = `inventoryQuantity_${number}`;
        if (inventoryUnit) inventoryUnit.id = `inventoryUnit_${number}`;
        if (remainingQuantity) {
            remainingQuantity.id = `remainingQuantity_${number}`;
            // Ensure the readonly attribute is maintained
            remainingQuantity.setAttribute('readonly', 'readonly');
        }
        if (remainingUnit) remainingUnit.id = `remainingUnit_${number}`;
        if (inventoryRemarks) inventoryRemarks.id = `inventoryRemarks_${number}`;
        if (billUpload) billUpload.id = `billUpload_${number}`;
        if (billUploadLabel) billUploadLabel.setAttribute('for', `billUpload_${number}`);
        if (materialMediaItems) materialMediaItems.id = `materialMediaItems_${number}`;
        if (addMaterialMediaBtn) addMaterialMediaBtn.setAttribute('data-count', number);
        
        // Update material media items IDs
        const mediaItems = item.querySelectorAll('.material-media-item');
        mediaItems.forEach((mediaItem, mediaIndex) => {
            const mediaNumber = mediaIndex + 1;
            const fileInput = mediaItem.querySelector('input[type="file"]');
            const fileLabel = mediaItem.querySelector('.custom-file-label');
            
            if (fileInput) {
                fileInput.id = `materialMedia_${number}_${mediaNumber}`;
            }
            if (fileLabel) {
                fileLabel.setAttribute('for', `materialMedia_${number}_${mediaNumber}`);
            }
        });
        
        // Re-attach event listeners for building material select
        const materialSelect = item.querySelector('.building-material-select');
        if (materialSelect) {
            // Clone and replace to remove old event listeners
            const newMaterialSelect = materialSelect.cloneNode(true);
            materialSelect.parentNode.replaceChild(newMaterialSelect, materialSelect);
            
            // Add new event listener
            newMaterialSelect.addEventListener('change', function() {
                const customMaterialContainer = item.querySelector(`#customMaterialContainer_${number}`);
                
                if (this.value === 'custom') {
                    // Show custom material input
                    customMaterialContainer.style.display = 'block';
                    
                    // Focus on the custom material input
                    setTimeout(() => {
                        item.querySelector(`#customMaterial_${number}`).focus();
                    }, 100);
                } else {
                    // Hide custom material input
                    customMaterialContainer.style.display = 'none';
                    
                    // Fetch remaining quantity for selected material
                    if (this.value) {
                        fetchRemainingQuantity(this.value, number);
                    }
                }
            });
        }
    });
}

/**
 * Fetch the remaining quantity for a specific building material
 * @param {string} material - The building material
 * @param {number} itemCount - The inventory item count
 * @param {boolean} isCustom - Whether the material is custom
 */
function fetchRemainingQuantity(material, itemCount, isCustom = false) {
    const remainingQuantityInput = document.getElementById(`remainingQuantity_${itemCount}`);
    if (!remainingQuantityInput) return;
    
    // Show loading indicator
    remainingQuantityInput.setAttribute('placeholder', 'Loading...');
    remainingQuantityInput.disabled = true;
    
    // In a real implementation, this would make an API call to fetch the remaining quantity
    // For demonstration purposes, we'll simulate an API call with setTimeout
    setTimeout(() => {
        // Mock data for demonstration
        const mockInventoryData = {
            cement: { quantity: 250, unit: 'bag' },
            sand: { quantity: 15, unit: 'ton' },
            bricks: { quantity: 5000, unit: 'pcs' },
            steel: { quantity: 2.5, unit: 'ton' },
            concrete: { quantity: 30, unit: 'cum' },
            wood: { quantity: 120, unit: 'sqft' },
            paint: { quantity: 45, unit: 'l' },
            tiles: { quantity: 350, unit: 'pcs' },
            glass: { quantity: 75, unit: 'sqft' },
            pipes: { quantity: 200, unit: 'm' },
            wires: { quantity: 500, unit: 'm' },
            fixtures: { quantity: 35, unit: 'pcs' }
        };
        
        // Get the inventory unit select
        const inventoryUnitSelect = document.getElementById(`inventoryUnit_${itemCount}`);
        const remainingUnitSpan = document.getElementById(`remainingUnit_${itemCount}`);
        
        if (isCustom) {
            // For custom materials, we don't have pre-defined data
            // Check local storage for any previously stored data
            const storedData = localStorage.getItem(`inventory_${material}`);
            if (storedData) {
                const parsedData = JSON.parse(storedData);
                remainingQuantityInput.value = parsedData.quantity || '';
                
                // Update unit if available
                if (parsedData.unit && inventoryUnitSelect) {
                    inventoryUnitSelect.value = parsedData.unit;
                    if (remainingUnitSpan) {
                        remainingUnitSpan.textContent = parsedData.unit;
                    }
                }
            } else {
                // No stored data for this custom material
                remainingQuantityInput.value = '';
            }
        } else {
            // For predefined materials, use mock data
            const materialData = mockInventoryData[material];
            
            if (materialData) {
                remainingQuantityInput.value = materialData.quantity;
                
                // Update unit if available
                if (materialData.unit && inventoryUnitSelect) {
                    inventoryUnitSelect.value = materialData.unit;
                    if (remainingUnitSpan) {
                        remainingUnitSpan.textContent = materialData.unit;
                    }
                    
                    // Trigger change event to update any dependent fields
                    const event = new Event('change');
                    inventoryUnitSelect.dispatchEvent(event);
                }
            } else {
                remainingQuantityInput.value = '';
            }
        }
        
        // Remove loading state
        remainingQuantityInput.removeAttribute('placeholder');
        remainingQuantityInput.disabled = false;
    }, 500); // Simulate network delay
}
 