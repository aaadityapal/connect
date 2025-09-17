<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1" aria-labelledby="addVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addVendorModalLabel">
                    <i class="fas fa-building me-2"></i>
                    Add New Vendor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Loader -->
                <div class="loader-overlay" id="vendorLoader" style="display: none;">
                    <div class="loader-content">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Processing your request...</p>
                    </div>
                </div>
                <form id="addVendorForm">
                    <!-- Basic Information Section -->
                    <div class="vendor-section">
                        <h6 class="section-title mb-3">
                            <i class="fas fa-user me-2"></i>
                            Basic Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vendorFullName" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" class="form-control" id="vendorFullName" name="fullName" placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vendorNumber" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" class="form-control" id="vendorNumber" name="phoneNumber" placeholder="Enter phone number" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vendorAltNumber" class="form-label">Alternative Number</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-mobile-alt input-icon"></i>
                                    <input type="tel" class="form-control" id="vendorAltNumber" name="alternativeNumber" placeholder="Enter alternative number">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vendorEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" class="form-control" id="vendorEmail" name="email" placeholder="Enter email address" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="vendorType" class="form-label">Vendor Type <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper" id="vendorTypeWrapper">
                                    <i class="fas fa-tags input-icon" id="vendorTypeIcon"></i>
                                    <select class="form-select" id="vendorType" name="vendorType" required onchange="handleVendorTypeChange()">
                                        <option value="">Select Vendor Type</option>
                                        <option value="supplier">General Supplier</option>
                                        <option value="contractor">Contractor</option>
                                        <option value="service_provider">Service Provider</option>
                                        <option value="consultant">Consultant</option>
                                        <option value="material_supplier">Material Supplier</option>
                                        <option value="equipment_rental">Equipment Rental</option>
                                        <option value="cement_supplier">Cement Supplier</option>
                                        <option value="brick_supplier">Brick Supplier</option>
                                        <option value="tile_supplier">Tile Supplier</option>
                                        <option value="tile_vendor">Tile Vendor</option>
                                        <option value="steel_supplier">Steel Supplier</option>
                                        <option value="sand_supplier">Sand Supplier</option>
                                        <option value="gravel_supplier">Gravel Supplier</option>
                                        <option value="paint_supplier">Paint Supplier</option>
                                        <option value="electrical_supplier">Electrical Supplier</option>
                                        <option value="plumbing_supplier">Plumbing Supplier</option>
                                        <option value="hardware_supplier">Hardware Supplier</option>
                                        <option value="timber_supplier">Timber Supplier</option>
                                        <option value="glass_supplier">Glass Supplier</option>
                                        <option value="roofing_supplier">Roofing Supplier</option>
                                        <option value="concrete_supplier">Concrete Supplier</option>
                                        <option value="flooring_supplier">Flooring Supplier</option>
                                        <option value="insulation_supplier">Insulation Supplier</option>
                                        <option value="door_supplier">Door Supplier</option>
                                        <option value="window_supplier">Window Supplier</option>
                                        <option value="security_supplier">Security Equipment Supplier</option>
                                        <option value="hvac_supplier">HVAC Supplier</option>
                                        <option value="landscaping_supplier">Landscaping Supplier</option>
                                        <option value="cleaning_supplier">Cleaning Supplier</option>
                                        <option value="safety_equipment_supplier">Safety Equipment Supplier</option>
                                        <option value="excavation_contractor">Excavation Contractor</option>
                                        <option value="masonry_contractor">Masonry Contractor</option>
                                        <option value="carpentry_contractor">Carpentry Contractor</option>
                                        <option value="painting_contractor">Painting Contractor</option>
                                        <option value="transportation_vendor">Transportation Vendor</option>
                                        <option value="waste_management">Waste Management</option>
                                        <option value="other">Other</option>
                                        <option value="custom">Custom Type</option>
                                    </select>
                                    <input type="text" class="form-control" id="vendorTypeCustom" name="vendorTypeCustom" 
                                           placeholder="Enter custom vendor type" style="display: none;" required>
                                    <button type="button" class="custom-back-btn" id="vendorTypeBackBtn" 
                                            onclick="backToDropdown()" style="display: none;" title="Back to dropdown">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Banking Details Section -->
                    <div class="vendor-section">
                        <div class="section-header" onclick="toggleVendorSection('banking')">
                            <h6 class="section-title mb-0">
                                <i class="fas fa-university me-2"></i>
                                Banking Details
                            </h6>
                            <button type="button" class="section-toggle-btn collapsed" id="bankingToggleBtn">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="section-content collapsed" id="bankingContent">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="bankName" class="form-label">Bank Name</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-university input-icon"></i>
                                        <input type="text" class="form-control" id="bankName" name="bankName" placeholder="Enter bank name">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="accountNumber" class="form-label">Account Number</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-credit-card input-icon"></i>
                                        <input type="text" class="form-control" id="accountNumber" name="accountNumber" placeholder="Enter account number">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="routingNumber" class="form-label">Routing Number / IFSC Code</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-code-branch input-icon"></i>
                                        <input type="text" class="form-control" id="routingNumber" name="routingNumber" placeholder="Enter IFSC / routing code">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="accountType" class="form-label">Account Type</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-wallet input-icon"></i>
                                        <select class="form-select" id="accountType" name="accountType">
                                            <option value="">Select Account Type</option>
                                            <option value="savings">Savings</option>
                                            <option value="current">Current</option>
                                            <option value="business">Business</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Section -->
                    <div class="vendor-section">
                        <div class="section-header" onclick="toggleVendorSection('address')">
                            <h6 class="section-title mb-0">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                Address Details
                            </h6>
                            <button type="button" class="section-toggle-btn collapsed" id="addressToggleBtn">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="section-content collapsed" id="addressContent">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="streetAddress" class="form-label">Street Address</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-road input-icon"></i>
                                        <input type="text" class="form-control" id="streetAddress" name="streetAddress" placeholder="Enter street address">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-city input-icon"></i>
                                        <input type="text" class="form-control" id="city" name="city" placeholder="Enter city">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="state" class="form-label">State/Province</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-map input-icon"></i>
                                        <input type="text" class="form-control" id="state" name="state" placeholder="Enter state/province">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="zipCode" class="form-label">ZIP/Postal Code</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-mail-bulk input-icon"></i>
                                        <input type="text" class="form-control" id="zipCode" name="zipCode" placeholder="Enter ZIP/postal code">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="country" class="form-label">Country</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-globe input-icon"></i>
                                        <select class="form-select" id="country" name="country">
                                            <option value="">Select Country</option>
                                            <option value="India">India</option>
                                            <option value="USA">United States</option>
                                            <option value="UK">United Kingdom</option>
                                            <option value="Canada">Canada</option>
                                            <option value="Australia">Australia</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes Section -->
                    <div class="vendor-section">
                        <h6 class="section-title mb-3">
                            <i class="fas fa-sticky-note me-2"></i>
                            Additional Notes
                        </h6>
                        <div class="mb-3">
                            <label for="additionalNotes" class="form-label">Notes</label>
                            <div class="input-icon-wrapper textarea-wrapper">
                                <i class="fas fa-sticky-note input-icon textarea-icon"></i>
                                <textarea class="form-control" id="additionalNotes" name="additionalNotes" rows="4" 
                                    placeholder="Enter any additional information about the vendor..."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="submitVendorForm()">
                    <i class="fas fa-save me-2"></i>
                    Save Vendor
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Add Vendor Modal Styles */
.modal-lg {
    max-width: 800px;
}

.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

/* Increase modal z-index to ensure it appears above other elements */
#addVendorModal {
    z-index: 1060 !important;
}

.modal-header {
    border-bottom: 1px solid #f1f3f4;
    padding: 1.5rem 2rem;
    border-radius: 16px 16px 0 0;
    background-color: #fafbfc;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.modal-body {
    padding: 2rem;
    background-color: #ffffff;
}

.modal-footer {
    border-top: 1px solid #f1f3f4;
    padding: 1.5rem 2rem;
    border-radius: 0 0 16px 16px;
    background-color: #fafbfc;
}

.vendor-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #f1f3f4;
}

.vendor-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.section-title i {
    color: #6b7280;
    margin-right: 0.5rem;
    font-size: 1rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    padding: 0.75rem 1rem;
    margin: 0 -1rem 1rem;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.section-header:hover {
    background-color: #f9fafb;
}

.section-toggle-btn {
    background: none;
    border: none;
    font-size: 1rem;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0.5rem;
    border-radius: 6px;
}

.section-toggle-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.section-toggle-btn i {
    transition: transform 0.3s ease;
}

.section-toggle-btn.collapsed i {
    transform: rotate(-90deg);
}

.section-content {
    overflow: hidden;
    transition: all 0.3s ease;
    margin-top: 1rem;
}

.section-content.collapsed {
    max-height: 0;
    margin-top: 0;
    margin-bottom: 0;
}

.form-label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.input-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1rem;
    color: #9ca3af;
    font-size: 0.9rem;
    z-index: 2;
    transition: color 0.2s ease;
}

.textarea-icon {
    top: 1rem;
    position: absolute;
}

.custom-back-btn {
    position: absolute;
    right: 0.75rem;
    background: none;
    border: none;
    color: #6b7280;
    font-size: 0.9rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.2s ease;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
}

.custom-back-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.custom-back-btn:active {
    transform: scale(0.95);
}

.form-control, .form-select {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 0.875rem 1rem 0.875rem 2.75rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    background-color: #fafbfc;
    box-shadow: none;
}

.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    background-color: #ffffff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

.form-control:focus + .input-icon,
.form-select:focus + .input-icon {
    color: #3b82f6;
}

.textarea-wrapper textarea {
    padding-left: 2.75rem;
    resize: vertical;
    min-height: 100px;
}

.text-danger {
    color: #ef4444 !important;
}

.btn {
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
}

.btn-secondary:hover {
    background-color: #e5e7eb;
    color: #1f2937;
    border-color: #d1d5db;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: #6b7280;
    opacity: 1;
    transition: all 0.2s ease;
    padding: 0.5rem;
    border-radius: 6px;
}

.btn-close:hover {
    background-color: #f3f4f6;
    color: #374151;
}

/* Remove default select arrow for custom styling */
.form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 16px 12px;
    padding-right: 2.5rem;
}

/* Validation styles */
.is-invalid {
    border-color: #ef4444 !important;
    background-color: #fef2f2 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

.is-invalid + .input-icon {
    color: #ef4444 !important;
}

/* Animation for smooth form interactions */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
    animation: fadeIn 0.3s ease-out;
}

/* Responsive design */
@media (max-width: 768px) {
    .modal-lg {
        max-width: 95%;
        margin: 1rem auto;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-header,
    .modal-footer {
        padding: 1rem 1.5rem;
    }
}

/* Loader Styles */
.loader-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    border-radius: 16px;
}

.loader-content {
    text-align: center;
    color: #374151;
}

.loader-content p {
    margin: 10px 0 0;
    font-weight: 500;
}
</style>

<script>
// Handle vendor type change
function handleVendorTypeChange() {
    const vendorTypeSelect = document.getElementById('vendorType');
    const vendorTypeCustom = document.getElementById('vendorTypeCustom');
    const vendorTypeIcon = document.getElementById('vendorTypeIcon');
    const backBtn = document.getElementById('vendorTypeBackBtn');
    
    if (vendorTypeSelect.value === 'custom') {
        // Hide dropdown and show text input
        vendorTypeSelect.style.display = 'none';
        vendorTypeCustom.style.display = 'block';
        vendorTypeCustom.style.paddingRight = '3rem';
        backBtn.style.display = 'flex';
        
        // Change icon to edit icon
        vendorTypeIcon.className = 'fas fa-edit input-icon';
        
        // Focus on the text input
        setTimeout(() => {
            vendorTypeCustom.focus();
        }, 100);
        
        // Clear the select value and set custom input as required
        vendorTypeSelect.removeAttribute('required');
        vendorTypeCustom.setAttribute('required', 'required');
    }
}

// Go back to dropdown from custom input
function backToDropdown() {
    const vendorTypeSelect = document.getElementById('vendorType');
    const vendorTypeCustom = document.getElementById('vendorTypeCustom');
    const vendorTypeIcon = document.getElementById('vendorTypeIcon');
    const backBtn = document.getElementById('vendorTypeBackBtn');
    
    // Show dropdown and hide text input
    vendorTypeSelect.style.display = 'block';
    vendorTypeCustom.style.display = 'none';
    backBtn.style.display = 'none';
    
    // Reset icon to tags icon
    vendorTypeIcon.className = 'fas fa-tags input-icon';
    
    // Reset values and requirements
    vendorTypeSelect.value = '';
    vendorTypeCustom.value = '';
    vendorTypeSelect.setAttribute('required', 'required');
    vendorTypeCustom.removeAttribute('required');
    
    // Remove validation classes if any
    vendorTypeCustom.classList.remove('is-invalid');
    vendorTypeSelect.classList.remove('is-invalid');
}
function toggleVendorSection(sectionType) {
    const content = document.getElementById(sectionType + 'Content');
    const toggleBtn = document.getElementById(sectionType + 'ToggleBtn');
    
    content.classList.toggle('collapsed');
    toggleBtn.classList.toggle('collapsed');
    
    // Store the state in localStorage
    const isCollapsed = content.classList.contains('collapsed');
    localStorage.setItem('vendor' + sectionType.charAt(0).toUpperCase() + sectionType.slice(1) + 'Collapsed', isCollapsed);
}

// Initialize vendor section states
function initializeVendorSections() {
    const sections = ['banking', 'address'];
    
    sections.forEach(section => {
        const isCollapsed = localStorage.getItem('vendor' + section.charAt(0).toUpperCase() + section.slice(1) + 'Collapsed') === 'true';
        
        if (isCollapsed) {
            const content = document.getElementById(section + 'Content');
            const toggleBtn = document.getElementById(section + 'ToggleBtn');
            
            if (content && toggleBtn) {
                content.classList.add('collapsed');
                toggleBtn.classList.add('collapsed');
            }
        }
    });
}

// Submit vendor form
function submitVendorForm() {
    // Show loader
    const loader = document.getElementById('vendorLoader');
    if (loader) {
        loader.style.display = 'flex';
    }
    
    const form = document.getElementById('addVendorForm');
    const formData = new FormData(form);
    
    // Handle custom vendor type
    const vendorTypeSelect = document.getElementById('vendorType');
    const vendorTypeCustom = document.getElementById('vendorTypeCustom');
    
    let vendorTypeValue = '';
    if (vendorTypeCustom.style.display !== 'none' && vendorTypeCustom.value.trim()) {
        vendorTypeValue = vendorTypeCustom.value.trim();
        formData.set('vendorType', vendorTypeValue);
    } else if (vendorTypeSelect.value) {
        vendorTypeValue = vendorTypeSelect.value;
    }
    
    // Basic validation
    const requiredFields = ['fullName', 'phoneNumber', 'email'];
    let isValid = true;
    
    // Validate basic required fields
    requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    // Validate vendor type (either from dropdown or custom input)
    if (!vendorTypeValue) {
        if (vendorTypeCustom.style.display !== 'none') {
            vendorTypeCustom.classList.add('is-invalid');
        } else {
            vendorTypeSelect.classList.add('is-invalid');
        }
        isValid = false;
    } else {
        vendorTypeCustom.classList.remove('is-invalid');
        vendorTypeSelect.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        showNotification('warning', 'Please fill in all required fields.');
        return;
    }
    
    // Send the data to the server using AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../api/save_vendor.php', true);
    xhr.onload = function() {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    // Show success message with animation
                    showNotification('success', 'Vendor added successfully!');
                    
                    // Update recipient if we're adding from payment entry modal
                    if (window.currentRecipientToUpdate) {
                        updateRecipientAfterAdd(response.vendor_id, document.getElementById('vendorFullName').value, 'vendor');
                    }
                    
                    // Reset form and close modal
                    form.reset();
                    backToDropdown(); // Reset vendor type to dropdown
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addVendorModal'));
                    modal.hide();
                    
                    // Refresh vendor list if available
                    if (typeof loadVendors === 'function') {
                        loadVendors();
                    }
                    
                    // If this was a custom vendor type, update all vendor type dropdowns in payment entry modal
                    if (vendorTypeCustom.style.display !== 'none' && vendorTypeCustom.value.trim()) {
                        updateVendorTypesInPaymentEntries(vendorTypeCustom.value.trim());
                    }
                } else {
                    showNotification('error', 'Error: ' + response.message);
                }
            } catch (e) {
                showNotification('error', 'An error occurred while processing the response.');
                console.error(e);
            }
        } else {
            showNotification('error', 'Request failed. Please try again.');
        }
    };
    xhr.onerror = function() {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        showNotification('error', 'Request failed. Please check your connection.');
    };
    xhr.send(formData);
}

// Function to update vendor types in all payment entry recipient dropdowns
function updateVendorTypesInPaymentEntries(newVendorType) {
    // Check if the payment entry modal is open and has recipients
    const paymentEntryModal = document.getElementById('addPaymentEntryModal');
    if (paymentEntryModal && paymentEntryModal.classList.contains('show')) {
        // Get all recipient items
        const recipientItems = document.querySelectorAll('.recipient-item');
        
        recipientItems.forEach(recipientItem => {
            const recipientId = recipientItem.getAttribute('data-recipient-id');
            const categorySelect = document.getElementById(`recipientCategory_${recipientId}`);
            const typeSelect = document.getElementById(`recipientType_${recipientId}`);
            
            // If this recipient is a vendor, update its type options
            if (categorySelect && categorySelect.value === 'vendor' && typeSelect) {
                // Check if the new vendor type already exists in the dropdown
                const existingOption = Array.from(typeSelect.options).find(option => 
                    option.textContent.toLowerCase() === newVendorType.toLowerCase());
                
                // If not, add it to the dropdown
                if (!existingOption) {
                    const optionElement = document.createElement('option');
                    optionElement.value = newVendorType.toLowerCase().replace(/\s+/g, '_');
                    optionElement.textContent = newVendorType;
                    typeSelect.appendChild(optionElement);
                }
            }
        });
    }
}

// Initialize when modal is shown
document.getElementById('addVendorModal').addEventListener('shown.bs.modal', function () {
    initializeVendorSections();
    // Reset vendor type to dropdown when modal opens
    backToDropdown();
});

// Add notification system to the page
function createNotificationSystem() {
    // Create notification container if it doesn't exist
    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }

    // Add notification styles
    const style = document.createElement('style');
    style.textContent = `
        .is-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
        }
        
        .is-invalid + .input-icon {
            color: #ef4444 !important;
        }
        
        .notification {
            padding: 15px 25px;
            margin-bottom: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
            display: flex;
            align-items: center;
            min-width: 300px;
            max-width: 500px;
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.4s ease-out;
            pointer-events: auto;
            position: relative;
            overflow: hidden;
        }
        
        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .notification::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .notification::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: rgba(255, 255, 255, 0.6);
            transform-origin: left;
            animation: countdown linear forwards;
        }
        
        .notification.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .notification.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .notification.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .notification-icon {
            margin-right: 15px;
            font-size: 1.5rem;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .notification-message {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: white;
            opacity: 0.7;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0;
            margin-left: 10px;
            transition: opacity 0.2s;
        }
        
        .notification-close:hover {
            opacity: 1;
        }
        
        @keyframes countdown {
            from {
                transform: scaleX(1);
            }
            to {
                transform: scaleX(0);
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .notification.error.show {
            animation: shake 0.8s ease;
        }
    `;
    document.head.appendChild(style);
}

// Show notification function
function showNotification(type, message, duration = 5000) {
    // Create notification container if it doesn't exist
    if (!document.getElementById('notification-container')) {
        createNotificationSystem();
    }
    
    const container = document.getElementById('notification-container');
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Set icon based on notification type
    let icon = '';
    let title = '';
    
    switch(type) {
        case 'success':
            icon = '<i class="fas fa-check-circle notification-icon"></i>';
            title = 'Success';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle notification-icon"></i>';
            title = 'Error';
            break;
        case 'info':
            icon = '<i class="fas fa-info-circle notification-icon"></i>';
            title = 'Information';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle notification-icon"></i>';
            title = 'Warning';
            break;
    }
    
    // Set notification content
    notification.innerHTML = `
        ${icon}
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add notification to container
    container.appendChild(notification);
    
    // Set animation duration for countdown
    notification.style.setProperty('--duration', `${duration}ms`);
    const afterElement = notification.querySelector('.notification::after');
    if (afterElement) {
        afterElement.style.animationDuration = `${duration}ms`;
    }
    
    // Show notification with animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Handle close button click
    const closeButton = notification.querySelector('.notification-close');
    closeButton.addEventListener('click', () => {
        removeNotification(notification);
    });
    
    // Auto-remove notification after duration
    setTimeout(() => {
        removeNotification(notification);
    }, duration);
}

// Remove notification with animation
function removeNotification(notification) {
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(50px)';
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.parentElement.removeChild(notification);
        }
    }, 400);
}

// Initialize notification system
createNotificationSystem();
</script>