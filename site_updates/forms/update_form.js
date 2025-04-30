/**
 * Site Updates Form JavaScript
 * Handles the modal functionality and form validation
 */

// Initialize form functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Main JS loaded');
    initUpdateForm();
});

// Counter for vendor IDs
let vendorCounter = 0;
// Object to track laborer counters for each vendor
let laborerCounters = {};

/**
 * Initialize update form functionality
 */
function initUpdateForm() {
    // Get form elements
    const modal = document.getElementById('update-form-modal');
    const form = document.getElementById('update-form');
    const closeBtn = document.querySelector('.modal-close');
    const cancelBtn = document.getElementById('cancel-update');
    const addVendorBtn = document.getElementById('add-vendor-btn');
    const bottomAddVendorBtn = document.getElementById('bottom-add-vendor-btn');
    
    console.log('Init update form');
    console.log('Add vendor button: ', addVendorBtn);
    
    // Initialize date picker for the form
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#update-date', {
            dateFormat: 'Y-m-d',
            maxDate: 'today'
        });
    }
    
    // Add form submission handler
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state if form is valid
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
        });
    }
    
    // Setup add vendor button
    if (addVendorBtn) {
        console.log('Adding event listener to add vendor button');
        addVendorBtn.addEventListener('click', function() {
            console.log('Add vendor button clicked from main JS');
            addVendor();
        });
    }
    
    // Setup bottom add vendor button
    if (bottomAddVendorBtn) {
        console.log('Adding event listener to bottom add vendor button');
        bottomAddVendorBtn.addEventListener('click', function() {
            console.log('Bottom add vendor button clicked from main JS');
            addVendor();
        });
    }
    
    // Close modal on X button click
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            hideUpdateModal();
        });
    }
    
    // Close modal on Cancel button click
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            hideUpdateModal();
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            hideUpdateModal();
        }
    });
    
    // Handle escape key to close modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal && modal.classList.contains('show')) {
            hideUpdateModal();
        }
    });
}

/**
 * Show the update form modal
 */
function showUpdateModal() {
    const modal = document.getElementById('update-form-modal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
        
        // Focus on first input field after modal appears
        setTimeout(() => {
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);
        
        // Reset vendors and counters
        resetVendors();
    }
}

/**
 * Hide the update form modal
 */
function hideUpdateModal() {
    const modal = document.getElementById('update-form-modal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Re-enable scrolling
        
        // Reset form on close
        const form = document.getElementById('update-form');
        if (form) {
            form.reset();
            resetVendors();
        }
    }
}

/**
 * Reset all vendors and laborers
 */
function resetVendors() {
    const vendorsContainer = document.getElementById('vendors-container');
    if (vendorsContainer) {
        vendorsContainer.innerHTML = '';
    }
    
    // Reset counters
    vendorCounter = 0;
    laborerCounters = {};
}

/**
 * Add a new vendor to the form
 */
function addVendor() {
    console.log('addVendor function called');
    vendorCounter++;
    const vendorId = vendorCounter;
    
    // Initialize laborer counter for this vendor
    laborerCounters[vendorId] = 0;
    
    // Get the template 
    const templateDiv = document.getElementById('vendor-template');
    if (!templateDiv) {
        console.error("Vendor template not found");
        return;
    }
    
    // Get the HTML and replace placeholders
    const templateContent = templateDiv.innerHTML;
    const vendorHtml = templateContent
        .replace(/{VENDOR_ID}/g, vendorId)
        .replace(/{VENDOR_NUMBER}/g, vendorId);
    
    // Create a temporary container to hold the HTML
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = vendorHtml;
    
    // Get the actual vendor element
    const vendorElement = tempContainer.firstChild;
    
    // Append to the vendors container
    const vendorsContainer = document.getElementById('vendors-container');
    if (!vendorsContainer) {
        console.error("Vendors container not found");
        return;
    }
    
    vendorsContainer.appendChild(vendorElement);
    console.log('Vendor added with ID: ' + vendorId);
    
    // Focus on the vendor type dropdown
    setTimeout(() => {
        const typeField = document.getElementById(`vendor-type-${vendorId}`);
        if (typeField) {
            typeField.focus();
        }
        
        // Scroll to the newly added vendor
        vendorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 100);
}

/**
 * Remove a vendor from the form
 * 
 * @param {number} vendorId - The ID of the vendor to remove
 */
function removeVendor(vendorId) {
    const vendorElement = document.querySelector(`.vendor-item[data-vendor-id="${vendorId}"]`);
    if (vendorElement) {
        // Ask for confirmation
        if (confirm("Are you sure you want to remove this vendor and all associated laborers?")) {
            vendorElement.remove();
            // Remove laborer counter for this vendor
            delete laborerCounters[vendorId];
            
            // Renumber remaining vendors
            const remainingVendors = document.querySelectorAll('.vendor-item');
            remainingVendors.forEach((vendor, index) => {
                const vendorNumber = index + 1;
                const numberElement = vendor.querySelector('.vendor-number');
                if (numberElement) {
                    numberElement.textContent = vendorNumber;
                }
                
                // Update vendor title text if needed
                const titleText = vendor.querySelector('.vendor-title span');
                if (titleText) {
                    const typeSelect = vendor.querySelector('.vendor-type-select');
                    const vendorType = typeSelect ? typeSelect.value : '';
                    if (vendorType) {
                        titleText.textContent = `${vendorType} #${vendorNumber}`;
                    } else {
                        titleText.textContent = `Vendor #${vendorNumber}`;
                    }
                }
            });
        }
    }
}

/**
 * Calculate the overtime amount and total for a laborer
 * 
 * @param {number} vendorId - The ID of the vendor
 * @param {number} laborerId - The ID of the laborer
 */
function calculateLaborerTotal(vendorId, laborerId) {
    // Get all input fields
    const morningAttendanceSelect = document.getElementById(`laborer-morning-${vendorId}-${laborerId}`);
    const eveningAttendanceSelect = document.getElementById(`laborer-evening-${vendorId}-${laborerId}`);
    const wagesInput = document.getElementById(`laborer-wages-${vendorId}-${laborerId}`);
    const dayTotalInput = document.getElementById(`laborer-day-total-${vendorId}-${laborerId}`);
    const otHoursInput = document.getElementById(`laborer-ot-hours-${vendorId}-${laborerId}`);
    const otRateInput = document.getElementById(`laborer-ot-rate-${vendorId}-${laborerId}`);
    const otAmountInput = document.getElementById(`laborer-ot-amount-${vendorId}-${laborerId}`);
    const totalInput = document.getElementById(`laborer-total-${vendorId}-${laborerId}`);
    
    // Get values from inputs
    const morningAttendance = morningAttendanceSelect.value;
    const eveningAttendance = eveningAttendanceSelect.value;
    const wages = parseFloat(wagesInput.value) || 0;
    const otHours = parseFloat(otHoursInput.value) || 0;
    let otRate = parseFloat(otRateInput.value) || 0;
    
    // Auto-calculate OT rate if not provided or changed (1.5x hourly wage, assuming 8-hour day)
    if (!otRateInput.value || otRate === 0) {
        otRate = wages / 8 * 1.5;
        otRateInput.value = otRate.toFixed(2);
    }
    
    // Calculate morning attendance factor (0 = absent, 0.5 = present)
    let morningFactor = morningAttendance === 'P' ? 0.5 : 0;
    
    // Calculate evening attendance factor (0 = absent, 0.5 = present)
    let eveningFactor = eveningAttendance === 'P' ? 0.5 : 0;
    
    // Combined attendance factor (morning and afternoon each count for half a day)
    const combinedFactor = morningFactor + eveningFactor;
    
    // Calculate day wages based on attendance
    const dayTotal = wages * combinedFactor;
    
    // Calculate overtime amount
    const otAmount = otHours * otRate;
    
    // Calculate final total
    const total = dayTotal + otAmount;
    
    // Update the fields
    dayTotalInput.value = dayTotal.toFixed(2);
    otAmountInput.value = otAmount.toFixed(2);
    totalInput.value = total.toFixed(2);
    
    console.log(`Calculated totals for laborer ${laborerId}:`, { 
        wages, 
        morningAttendance,
        morningFactor,
        eveningAttendance,
        eveningFactor,
        combinedFactor,
        dayTotal,
        otHours, 
        otRate, 
        otAmount, 
        total 
    });
    
    // Ensure values are stored in the form fields
    dayTotalInput.setAttribute('value', dayTotal.toFixed(2));
    otAmountInput.setAttribute('value', otAmount.toFixed(2));
    totalInput.setAttribute('value', total.toFixed(2));
}

/**
 * Add a new laborer to a vendor
 * 
 * @param {number} vendorId - The ID of the vendor to add the laborer to
 */
function addLaborer(vendorId) {
    // Increment the laborer counter for this vendor
    laborerCounters[vendorId] = (laborerCounters[vendorId] || 0) + 1;
    const laborerId = laborerCounters[vendorId];
    
    // Get the template
    const templateDiv = document.getElementById('laborer-template');
    if (!templateDiv) {
        console.error("Laborer template not found");
        return;
    }
    
    // Get the HTML and replace placeholders
    const templateContent = templateDiv.innerHTML;
    const laborerHtml = templateContent
        .replace(/{VENDOR_ID}/g, vendorId)
        .replace(/{LABORER_ID}/g, laborerId)
        .replace(/{LABORER_NUMBER}/g, laborerId);
    
    // Create a temporary container to hold the HTML
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = laborerHtml;
    
    // Get the actual laborer element
    const laborerElement = tempContainer.firstChild;
    
    // Append to the laborers container for this vendor
    const laborersContainer = document.getElementById(`laborers-container-${vendorId}`);
    if (!laborersContainer) {
        console.error(`Laborers container for vendor ${vendorId} not found`);
        return;
    }
    
    laborersContainer.appendChild(laborerElement);
    console.log('Laborer added for vendor: ' + vendorId + ', laborer ID: ' + laborerId);
    
    // Focus on the name field and scroll to the newly added laborer
    setTimeout(() => {
        const nameField = document.getElementById(`laborer-name-${vendorId}-${laborerId}`);
        if (nameField) {
            nameField.focus();
        }
        
        // Scroll to the newly added laborer
        laborerElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 100);
}

/**
 * Remove a laborer from a vendor
 * 
 * @param {number} vendorId - The ID of the vendor
 * @param {number} laborerId - The ID of the laborer to remove
 */
function removeLaborer(vendorId, laborerId) {
    const laborerElement = document.querySelector(`#laborers-container-${vendorId} .laborer-item[data-laborer-id="${laborerId}"]`);
    if (laborerElement) {
        laborerElement.remove();
        
        // Renumber remaining laborers for this vendor
        const remainingLaborers = document.querySelectorAll(`#laborers-container-${vendorId} .laborer-item`);
        remainingLaborers.forEach((laborer, index) => {
            const laborerNumber = index + 1;
            const numberElement = laborer.querySelector('.laborer-number');
            if (numberElement) {
                numberElement.textContent = laborerNumber;
            }
        });
    }
}

/**
 * Update the vendor icon based on the selected vendor type
 * 
 * @param {HTMLSelectElement} selectElement - The select element that changed
 * @param {number} vendorId - The ID of the vendor
 */
function updateVendorIcon(selectElement, vendorId) {
    if (!selectElement) return;
    
    const vendorType = selectElement.value;
    const vendorItemElement = selectElement.closest('.vendor-item');
    if (!vendorItemElement) return;
    
    const vendorTitle = vendorItemElement.querySelector('.vendor-title i');
    if (!vendorTitle) return;
    
    // Set the appropriate icon based on vendor type
    let iconClass = 'fas fa-hard-hat'; // Default icon
    
    switch (vendorType) {
        case 'Carpenter':
            iconClass = 'fas fa-hammer';
            break;
        case 'Electrician':
            iconClass = 'fas fa-bolt';
            break;
        case 'Plumber':
            iconClass = 'fas fa-faucet';
            break;
        case 'Mason':
            iconClass = 'fas fa-brick';
            break;
        case 'Painter':
            iconClass = 'fas fa-paint-roller';
            break;
        case 'HVAC':
            iconClass = 'fas fa-temperature-high';
            break;
        case 'Roofer':
            iconClass = 'fas fa-home';
            break;
        case 'Landscaper':
            iconClass = 'fas fa-leaf';
            break;
        case 'Concrete':
            iconClass = 'fas fa-truck-container';
            break;
        case 'Other':
            iconClass = 'fas fa-tools';
            break;
    }
    
    // Update the icon class
    vendorTitle.className = iconClass;
    
    // Also update the vendor title text
    const titleText = vendorItemElement.querySelector('.vendor-title span');
    if (titleText) {
        if (vendorType) {
            titleText.textContent = `${vendorType} #${vendorId}`;
        } else {
            titleText.textContent = `Vendor #${vendorId}`;
        }
    }
}

/**
 * Validate the update form
 * @returns {boolean} True if form is valid, false otherwise
 */
function validateForm() {
    const siteNameField = document.getElementById('site-name');
    const updateDateField = document.getElementById('update-date');
    
    let isValid = true;
    
    // Reset any existing error styles
    const fields = [siteNameField, updateDateField];
    fields.forEach(field => {
        if (field) {
            field.classList.remove('is-invalid');
            const errorMsg = field.parentElement ? field.parentElement.querySelector('.error-message') : null;
            if (errorMsg) {
                errorMsg.remove();
            }
        }
    });
    
    // Validate site name
    if (siteNameField && !siteNameField.value.trim()) {
        showError(siteNameField, 'Site name is required');
        isValid = false;
    }
    
    // Validate update date
    if (updateDateField && !updateDateField.value.trim()) {
        showError(updateDateField, 'Date is required');
        isValid = false;
    }
    
    // Validate vendors if any are added
    const vendors = document.querySelectorAll('.vendor-item');
    vendors.forEach(vendor => {
        if (!vendor || !vendor.dataset) return;
        
        const vendorId = vendor.dataset.vendorId;
        if (!vendorId) return;
        
        // Required fields for each vendor
        const vendorTypeField = document.getElementById(`vendor-type-${vendorId}`);
        const vendorNameField = document.getElementById(`vendor-name-${vendorId}`);
        const vendorContactField = document.getElementById(`vendor-contact-${vendorId}`);
        
        // Validate vendor type
        if (vendorTypeField && !vendorTypeField.value) {
            showError(vendorTypeField, 'Vendor type is required');
            isValid = false;
        }
        
        // Validate vendor name
        if (vendorNameField && !vendorNameField.value.trim()) {
            showError(vendorNameField, 'Vendor name is required');
            isValid = false;
        }
        
        // Validate vendor contact
        if (vendorContactField && !vendorContactField.value.trim()) {
            showError(vendorContactField, 'Contact number is required');
            isValid = false;
        }
        
        // Validate laborers if any are added
        const laborers = document.querySelectorAll(`#laborers-container-${vendorId} .laborer-item`);
        laborers.forEach(laborer => {
            if (!laborer || !laborer.dataset) return;
            
            const laborerId = laborer.dataset.laborerId;
            if (!laborerId) return;
            
            // Required fields for each laborer
            const laborerNameField = document.getElementById(`laborer-name-${vendorId}-${laborerId}`);
            
            // Validate laborer name
            if (laborerNameField && !laborerNameField.value.trim()) {
                showError(laborerNameField, 'Laborer name is required');
                isValid = false;
            }
        });
    });
    
    return isValid;
}

/**
 * Show error message for invalid field
 * @param {HTMLElement} field - The form field with error
 * @param {string} message - The error message to display
 */
function showError(field, message) {
    if (!field || !field.parentElement) return;
    
    // Add error class to field
    field.classList.add('is-invalid');
    
    // Create and append error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    field.parentElement.appendChild(errorDiv);
    
    // Focus the first invalid field
    if (!document.querySelector('.is-invalid:focus') && field) {
        field.focus();
    }
} 