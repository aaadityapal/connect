/**
 * Calendar Events Save Handler
 * Handles form submission for calendar events with vendors, materials, and labours
 */

// Global variables to track form state
let vendorCount = 0;
const materialCounts = {};
const labourCounts = {};

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the form handler system
    initCalendarEventSaveHandler();

    // Add event listener to the form if it exists
    const calendarEventForm = document.getElementById('calendar-event-form');
    if (calendarEventForm) {
        calendarEventForm.addEventListener('submit', handleFormSubmit);
    }

    // Event listeners for dynamic buttons
    document.addEventListener('click', function(event) {
        // Add vendor button
        if (event.target.matches('.add-vendor-btn')) {
            addVendor();
        }
        
        // Add material button
        if (event.target.matches('.add-material-btn')) {
            const vendorId = event.target.dataset.vendorId;
            addMaterial(vendorId);
        }
        
        // Add labour button
        if (event.target.matches('.add-labour-btn')) {
            const vendorId = event.target.dataset.vendorId;
            addLabour(vendorId);
        }
        
        // Remove buttons
        if (event.target.matches('.remove-vendor-btn')) {
            const vendorId = event.target.dataset.vendorId;
            removeVendor(vendorId);
        }
        
        if (event.target.matches('.remove-material-btn')) {
            const materialKey = event.target.dataset.materialKey;
            removeMaterial(materialKey);
        }
        
        if (event.target.matches('.remove-labour-btn')) {
            const labourKey = event.target.dataset.labourKey;
            removeLabour(labourKey);
        }
        
        // Handle file input changes
        if (event.target.matches('.material-image-input, .bill-image-input')) {
            handleFileInputChange(event.target);
        }
        
        // Handle vendor type selection
        if (event.target.matches('.vendor-type-select')) {
            handleVendorTypeChange(event.target);
        }
    });
});

/**
 * Initialize the calendar event save handler system
 * This will expose global functions for the modal to use
 */
function initCalendarEventSaveHandler() {
    // Make saveCalendarEvent function globally available for the modal to call
    window.saveCalendarEvent = saveCalendarEvent;
}

/**
 * Save a calendar event from any source
 * This can be called directly by the modal's saveEvent function
 * @param {Object} eventData - The event data to save
 * @param {Function} successCallback - Callback function on success
 * @param {Function} errorCallback - Callback function on error
 */
function saveCalendarEvent(eventData, successCallback, errorCallback) {
    // Show loading indicator or toast if needed
    console.log('Saving calendar event:', eventData);
    
    // Create FormData object to handle all data including files
    let formData;
    
    if (eventData instanceof FormData) {
        // If eventData is already FormData, use it directly
        formData = eventData;
    } else {
        // Otherwise create new FormData and add eventData properties
        formData = new FormData();
        
        // Add event_title and event_date
        formData.append('event_title', eventData.title || '');
        formData.append('event_date', eventData.date || '');
        
        // Process vendors if available
        if (eventData.vendors && eventData.vendors.length > 0) {
            formData.append('vendor_count', eventData.vendors.length);
            
            eventData.vendors.forEach((vendor, vendorIndex) => {
                const vendorNum = vendorIndex + 1;
                
                // Basic vendor info
                formData.append(`vendor_type_${vendorNum}`, vendor.type || '');
                formData.append(`vendor_name_${vendorNum}`, vendor.name || '');
                formData.append(`contact_number_${vendorNum}`, vendor.contact || '');
                
                // Process materials if available
                if (vendor.materials && vendor.materials.length > 0) {
                    formData.append(`material_count_${vendorNum}`, vendor.materials.length);
                    
                    vendor.materials.forEach((material, materialIndex) => {
                        const materialNum = materialIndex + 1;
                        const materialKey = `material_${vendorNum}_${materialNum}`;
                        
                        formData.append(`remarks_${materialKey}`, material.remarks || '');
                        formData.append(`amount_${materialKey}`, material.amount || '0');
                        
                        // Add material files if available
                        if (material.imageFile) {
                            formData.append(`material_images_${materialKey}`, material.imageFile);
                        }
                        
                        // Add bill files if available
                        if (material.billFile) {
                            formData.append(`bill_image_${materialKey}`, material.billFile);
                        }
                    });
                } else if (vendor.material) {
                    // For backward compatibility with the older single material format
                    formData.append(`material_count_${vendorNum}`, 1);
                    const materialKey = `material_${vendorNum}_1`;
                    formData.append(`remarks_${materialKey}`, vendor.material.remarks || '');
                    formData.append(`amount_${materialKey}`, vendor.material.amount || '0');
                    
                    // Handle material image if available
                    if (vendor.material.imageFile) {
                        formData.append(`material_images_${materialKey}`, vendor.material.imageFile);
                    }
                    
                    // Handle bill image if available
                    if (vendor.material.billFile) {
                        formData.append(`bill_image_${materialKey}`, vendor.material.billFile);
                    }
                }
                
                // Process labours if available
                if (vendor.labours && vendor.labours.length > 0) {
                    formData.append(`labour_count_${vendorNum}`, vendor.labours.length);
                    
                    vendor.labours.forEach((labour, labourIndex) => {
                        const labourNum = labourIndex + 1;
                        const labourKey = `labour_${vendorNum}_${labourNum}`;
                        
                        formData.append(`labour_name_${labourKey}`, labour.name || '');
                        formData.append(`labour_number_${labourKey}`, labour.contactNumber || '');
                        formData.append(`morning_attendance_${labourKey}`, labour.attendance?.morning || 'present');
                        formData.append(`evening_attendance_${labourKey}`, labour.attendance?.evening || 'present');
                        
                        // Add wage information if available
                        if (labour.wages) {
                            formData.append(`daily_wage_${labourKey}`, labour.wages.perDay || '0');
                            formData.append(`total_day_wage_${labourKey}`, labour.wages.totalDay || '0');
                        }
                        
                        // Add overtime information if available
                        if (labour.overtime) {
                            formData.append(`ot_hours_${labourKey}`, labour.overtime.hours || '0');
                            formData.append(`ot_minutes_${labourKey}`, labour.overtime.minutes || '0');
                            formData.append(`ot_rate_${labourKey}`, labour.overtime.rate || '0');
                            formData.append(`total_ot_amount_${labourKey}`, labour.overtime.totalAmount || '0');
                        }
                        
                        // Add travel information if available
                        if (labour.travel) {
                            formData.append(`transport_mode_${labourKey}`, labour.travel.mode || '');
                            formData.append(`travel_amount_${labourKey}`, labour.travel.amount || '0');
                        }
                        
                        // Add grand total
                        formData.append(`grand_total_${labourKey}`, labour.grandTotal || '0');
                    });
                }
            });
        }
        
        // Process company labours if available
        if (eventData.companies && eventData.companies.length > 0) {
            let totalCompanyLabours = 0;
            
            // Count total company labours
            eventData.companies.forEach(company => {
                if (company.labours) totalCompanyLabours += company.labours.length;
            });
            
            formData.append('company_labour_count', totalCompanyLabours);
            
            let companyLabourIndex = 1;
            
            // Flatten company labours into a single list for the backend
            eventData.companies.forEach(company => {
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
    }
    
    // Debug log
    console.log('Form data prepared for submission');
    
    // Send AJAX request to save data
    fetch('backend/save_calendar_event.php', {
        method: 'POST',
        body: formData,
        // No need to set Content-Type header for FormData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('Calendar event saved successfully:', data);
            
            // Call success callback if provided
            if (typeof successCallback === 'function') {
                successCallback(data);
            } else {
                // Default success action
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Event saved successfully!');
                }
            }
        } else {
            console.error('Error saving calendar event:', data);
            
            // Call error callback if provided
            if (typeof errorCallback === 'function') {
                errorCallback(data);
            } else {
                // Default error action
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Error: ' + data.message);
                }
            }
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        
        // Call error callback if provided
        if (typeof errorCallback === 'function') {
            errorCallback({status: 'error', message: 'Network or server error'});
        } else {
            // Default error action
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'An unexpected error occurred. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('An unexpected error occurred. Please try again.');
            }
        }
    });
}

/**
 * Add a new vendor to the form
 */
function addVendor() {
    vendorCount++;
    const vendorId = vendorCount;
    
    // Create vendor HTML
    const vendorHtml = `
        <div class="vendor-entry" id="vendor-${vendorId}" data-vendor-id="${vendorId}">
            <div class="vendor-header">
                <h4 class="vendor-number">${vendorId}</h4>
                <button type="button" class="remove-vendor-btn btn btn-danger btn-sm" data-vendor-id="${vendorId}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="vendor-type-${vendorId}">Vendor Type</label>
                    <select class="form-control vendor-type-select" id="vendor-type-${vendorId}" name="vendor_type_${vendorId}">
                        <option value="">Select Vendor Type</option>
                        <option value="labour">Labour</option>
                        <option value="material">Material</option>
                        <option value="equipment">Equipment</option>
                        <option value="rental">Rental</option>
                        <option value="custom">Custom</option>
                    </select>
                    <input type="text" class="form-control custom-vendor-type-input" id="custom-vendor-type-${vendorId}" 
                           style="display: none; margin-top: 10px;" placeholder="Enter Custom Vendor Type">
                </div>
                <div class="form-group col-md-4">
                    <label for="vendor-name-${vendorId}">Vendor Name</label>
                    <input type="text" class="form-control" id="vendor-name-${vendorId}" name="vendor_name_${vendorId}" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="contact-number-${vendorId}">Contact Number</label>
                    <input type="text" class="form-control" id="contact-number-${vendorId}" name="contact_number_${vendorId}">
                </div>
            </div>
            
            <div class="materials-section">
                <h5>Vendor Materials</h5>
                <div class="materials-container" id="materials-container-${vendorId}"></div>
                <button type="button" class="btn btn-info add-material-btn" data-vendor-id="${vendorId}">
                    <i class="fas fa-plus"></i> Add Material
                </button>
            </div>
            
            <div class="labours-section">
                <h5>Vendor Labours</h5>
                <div class="labours-container" id="labours-container-${vendorId}"></div>
                <button type="button" class="btn btn-info add-labour-btn" data-vendor-id="${vendorId}">
                    <i class="fas fa-plus"></i> Add Labour
                </button>
            </div>
        </div>
    `;
    
    // Add the vendor to the page
    const vendorsContainer = document.getElementById('vendors-container');
    vendorsContainer.insertAdjacentHTML('beforeend', vendorHtml);
    
    // Initialize counts for this vendor
    materialCounts[vendorId] = 0;
    labourCounts[vendorId] = 0;
    
    // Update vendor count hidden field
    updateVendorCountField();
}

/**
 * Add a new material to a vendor
 */
function addMaterial(vendorId) {
    if (!materialCounts[vendorId]) {
        materialCounts[vendorId] = 0;
    }
    
    materialCounts[vendorId]++;
    const materialNumber = materialCounts[vendorId];
    const materialKey = `material_${vendorId}_${materialNumber}`;
    
    // Create material HTML
    const materialHtml = `
        <div class="material-entry" id="${materialKey}" data-material-key="${materialKey}">
            <div class="material-header">
                <h6 class="material-number">Material ${materialNumber}</h6>
                <button type="button" class="remove-material-btn btn btn-danger btn-sm" data-material-key="${materialKey}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="material-images-${materialKey}">Material Picture</label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input material-image-input" id="material-images-${materialKey}" 
                               name="material_images_${materialKey}" accept="image/*">
                        <label class="custom-file-label" for="material-images-${materialKey}">Choose file</label>
                    </div>
                    <div class="material-image-preview mt-2" id="material-image-preview-${materialKey}"></div>
                </div>
                <div class="form-group col-md-6">
                    <label for="remarks-${materialKey}">Remarks</label>
                    <textarea class="form-control" id="remarks-${materialKey}" name="remarks_${materialKey}" rows="2"></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="amount-${materialKey}">Amount</label>
                    <input type="number" class="form-control" id="amount-${materialKey}" name="amount_${materialKey}" step="0.01">
                </div>
                <div class="form-group col-md-6">
                    <label for="bill-image-${materialKey}">Bill Picture</label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input bill-image-input" id="bill-image-${materialKey}" 
                               name="bill_image_${materialKey}" accept="image/*">
                        <label class="custom-file-label" for="bill-image-${materialKey}">Choose file</label>
                    </div>
                    <div class="bill-image-preview mt-2" id="bill-image-preview-${materialKey}"></div>
                </div>
            </div>
        </div>
    `;
    
    // Add the material to the vendor's materials container
    const materialsContainer = document.getElementById(`materials-container-${vendorId}`);
    materialsContainer.insertAdjacentHTML('beforeend', materialHtml);
    
    // Update material count hidden field
    updateMaterialCountField(vendorId);
}

/**
 * Add a new labour to a vendor
 */
function addLabour(vendorId) {
    if (!labourCounts[vendorId]) {
        labourCounts[vendorId] = 0;
    }
    
    labourCounts[vendorId]++;
    const labourNumber = labourCounts[vendorId];
    const labourKey = `labour_${vendorId}_${labourNumber}`;
    
    // Create labour HTML
    const labourHtml = `
        <div class="labour-entry" id="${labourKey}" data-labour-key="${labourKey}">
            <div class="labour-header">
                <h6 class="labour-number">Labour ${labourNumber}</h6>
                <button type="button" class="remove-labour-btn btn btn-danger btn-sm" data-labour-key="${labourKey}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="labour-name-${labourKey}">Labour Name</label>
                    <input type="text" class="form-control" id="labour-name-${labourKey}" name="labour_name_${labourKey}" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="labour-number-${labourKey}">Labour Number</label>
                    <input type="text" class="form-control" id="labour-number-${labourKey}" name="labour_number_${labourKey}">
                </div>
                <div class="form-group col-md-4">
                    <label>Attendance</label>
                    <div class="attendance-container">
                        <div class="attendance-section">
                            <span>Morning:</span>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="morning_attendance_${labourKey}" id="morning-present-${labourKey}" value="present" checked>
                                <label class="form-check-label" for="morning-present-${labourKey}">Present</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="morning_attendance_${labourKey}" id="morning-absent-${labourKey}" value="absent">
                                <label class="form-check-label" for="morning-absent-${labourKey}">Absent</label>
                            </div>
                        </div>
                        <div class="attendance-section">
                            <span>Evening:</span>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="evening_attendance_${labourKey}" id="evening-present-${labourKey}" value="present" checked>
                                <label class="form-check-label" for="evening-present-${labourKey}">Present</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="evening_attendance_${labourKey}" id="evening-absent-${labourKey}" value="absent">
                                <label class="form-check-label" for="evening-absent-${labourKey}">Absent</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add the labour to the vendor's labours container
    const laboursContainer = document.getElementById(`labours-container-${vendorId}`);
    laboursContainer.insertAdjacentHTML('beforeend', labourHtml);
    
    // Update labour count hidden field
    updateLabourCountField(vendorId);
}

/**
 * Remove a vendor from the form
 */
function removeVendor(vendorId) {
    const vendorElement = document.getElementById(`vendor-${vendorId}`);
    if (vendorElement) {
        vendorElement.remove();
        
        // Delete counts for this vendor
        delete materialCounts[vendorId];
        delete labourCounts[vendorId];
        
        // Update vendor count hidden field
        updateVendorCountField();
        
        // Renumber remaining vendors
        renumberVendors();
    }
}

/**
 * Remove a material from a vendor
 */
function removeMaterial(materialKey) {
    const materialElement = document.getElementById(materialKey);
    if (materialElement) {
        const vendorId = materialKey.split('_')[1];
        materialElement.remove();
        
        // Renumber remaining materials
        renumberMaterials(vendorId);
        
        // Update material count hidden field
        updateMaterialCountField(vendorId);
    }
}

/**
 * Remove a labour from a vendor
 */
function removeLabour(labourKey) {
    const labourElement = document.getElementById(labourKey);
    if (labourElement) {
        const vendorId = labourKey.split('_')[1];
        labourElement.remove();
        
        // Renumber remaining labours
        renumberLabours(vendorId);
        
        // Update labour count hidden field
        updateLabourCountField(vendorId);
    }
}

/**
 * Renumber vendors after removal
 */
function renumberVendors() {
    const vendorEntries = document.querySelectorAll('.vendor-entry');
    vendorEntries.forEach((vendor, index) => {
        const vendorNumber = index + 1;
        const vendorNumberElement = vendor.querySelector('.vendor-number');
        if (vendorNumberElement) {
            vendorNumberElement.textContent = vendorNumber;
        }
    });
}

/**
 * Renumber materials after removal
 */
function renumberMaterials(vendorId) {
    const materialEntries = document.querySelectorAll(`#materials-container-${vendorId} .material-entry`);
    materialEntries.forEach((material, index) => {
        const materialNumber = index + 1;
        const materialNumberElement = material.querySelector('.material-number');
        if (materialNumberElement) {
            materialNumberElement.textContent = `Material ${materialNumber}`;
        }
    });
}

/**
 * Renumber labours after removal
 */
function renumberLabours(vendorId) {
    const labourEntries = document.querySelectorAll(`#labours-container-${vendorId} .labour-entry`);
    labourEntries.forEach((labour, index) => {
        const labourNumber = index + 1;
        const labourNumberElement = labour.querySelector('.labour-number');
        if (labourNumberElement) {
            labourNumberElement.textContent = `Labour ${labourNumber}`;
        }
    });
}

/**
 * Update vendor count hidden field
 */
function updateVendorCountField() {
    const activeVendors = document.querySelectorAll('.vendor-entry').length;
    let vendorCountField = document.getElementById('vendor-count');
    
    if (!vendorCountField) {
        // Create field if it doesn't exist
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.id = 'vendor-count';
        hiddenField.name = 'vendor_count';
        hiddenField.value = activeVendors;
        calendarEventForm.appendChild(hiddenField);
    } else {
        vendorCountField.value = activeVendors;
    }
}

/**
 * Update material count hidden field for a vendor
 */
function updateMaterialCountField(vendorId) {
    const activeMaterials = document.querySelectorAll(`#materials-container-${vendorId} .material-entry`).length;
    let materialCountField = document.getElementById(`material-count-${vendorId}`);
    
    if (!materialCountField) {
        // Create field if it doesn't exist
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.id = `material-count-${vendorId}`;
        hiddenField.name = `material_count_${vendorId}`;
        hiddenField.value = activeMaterials;
        calendarEventForm.appendChild(hiddenField);
    } else {
        materialCountField.value = activeMaterials;
    }
}

/**
 * Update labour count hidden field for a vendor
 */
function updateLabourCountField(vendorId) {
    const activeLabours = document.querySelectorAll(`#labours-container-${vendorId} .labour-entry`).length;
    let labourCountField = document.getElementById(`labour-count-${vendorId}`);
    
    if (!labourCountField) {
        // Create field if it doesn't exist
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.id = `labour-count-${vendorId}`;
        hiddenField.name = `labour_count_${vendorId}`;
        hiddenField.value = activeLabours;
        calendarEventForm.appendChild(hiddenField);
    } else {
        labourCountField.value = activeLabours;
    }
}

/**
 * Handle file input change to show preview
 */
function handleFileInputChange(input) {
    const file = input.files[0];
    const fileLabel = input.nextElementSibling;
    const previewContainer = document.getElementById(input.id + '-preview');
    
    if (fileLabel) {
        fileLabel.textContent = file ? file.name : 'Choose file';
    }
    
    if (previewContainer && file) {
        // Clear previous preview
        previewContainer.innerHTML = '';
        
        // Create image preview
        const img = document.createElement('img');
        img.classList.add('img-preview');
        img.file = file;
        previewContainer.appendChild(img);
        
        // Read the file
        const reader = new FileReader();
        reader.onload = (function(aImg) {
            return function(e) {
                aImg.src = e.target.result;
            };
        })(img);
        reader.readAsDataURL(file);
    }
}

/**
 * Handle vendor type change
 */
function handleVendorTypeChange(select) {
    const vendorId = select.id.split('-').pop();
    const customInput = document.getElementById(`custom-vendor-type-${vendorId}`);
    
    if (select.value === 'custom') {
        customInput.style.display = 'block';
        customInput.name = `vendor_type_${vendorId}`;
        select.name = '';
    } else {
        customInput.style.display = 'none';
        customInput.name = '';
        select.name = `vendor_type_${vendorId}`;
    }
}

/**
 * Handle form submission
 */
function handleFormSubmit(event) {
    event.preventDefault();
    
    // Show loading state
    const submitButton = event.target.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    
    // Create FormData object to handle file uploads
    const formData = new FormData(event.target);
    
    // Use the common save function
    saveCalendarEvent(formData, 
        // Success callback
        function(data) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Redirect to calendar page or clear form
                    window.location.href = 'calendar.php';
                });
            } else {
                alert('Event saved successfully!');
                window.location.href = 'calendar.php';
            }
        },
        // Error callback
        function(data) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'An error occurred',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Error: ' + (data.message || 'An error occurred'));
            }
            
            // Reset button state
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    );
} 