<?php
/**
 * Vendor Edit Modal
 * Allows users to edit and update all vendor details
 * Triggered when user clicks the "edit" icon on a vendor row or the edit button in details modal
 */
?>

<div id="vendorEditModal" class="vendor-edit-modal">
    <div class="vendor-edit-modal-overlay"></div>
    <div class="vendor-edit-modal-content">
        <!-- Modal Header -->
        <div class="vendor-edit-modal-header">
            <h2>Edit Vendor Details</h2>
            <button class="vendor-edit-close-btn" onclick="closeVendorEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body with Loading State -->
        <div class="vendor-edit-modal-body">
            <div id="vendorEditContainer">
                <div class="vendor-edit-loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading vendor details...</p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="vendor-edit-modal-footer">
            <button class="vendor-edit-btn vendor-edit-btn-secondary" onclick="closeVendorEditModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="vendor-edit-btn vendor-edit-btn-primary" onclick="submitVendorEdit()">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<style>
    /* Vendor Edit Modal Styles */
    .vendor-edit-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9998;
    }

    .vendor-edit-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .vendor-edit-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        cursor: pointer;
    }

    .vendor-edit-modal-content {
        position: relative;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        max-width: 900px;
        width: 90%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .vendor-edit-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px;
        border-bottom: 1px solid #e2e8f0;
    }

    .vendor-edit-modal-header h2 {
        margin: 0;
        font-size: 1.5em;
        color: #2a4365;
        font-weight: 600;
    }

    .vendor-edit-close-btn {
        background: none;
        border: none;
        font-size: 1.5em;
        color: #a0aec0;
        cursor: pointer;
        transition: color 0.2s ease;
        padding: 5px;
    }

    .vendor-edit-close-btn:hover {
        color: #2a4365;
    }

    .vendor-edit-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 25px;
    }

    .vendor-edit-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 20px 25px;
        border-top: 1px solid #e2e8f0;
    }

    /* Loading State */
    .vendor-edit-loading {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }

    .vendor-edit-loading i {
        font-size: 2.5em;
        color: #2a4365;
        animation: spin 1s linear infinite;
        display: block;
        margin-bottom: 15px;
    }

    /* Form Styles */
    .vendor-edit-form {
        display: grid;
        gap: 20px;
    }

    .vendor-edit-section {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        background: #f7fafc;
    }

    .vendor-edit-section-title {
        font-size: 1.1em;
        font-weight: 600;
        color: #2a4365;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .vendor-edit-section-title i {
        font-size: 1.2em;
        color: #2a4365;
    }

    .vendor-edit-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
    }

    .vendor-edit-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .vendor-edit-form-group.full-width {
        grid-column: 1 / -1;
    }

    .vendor-edit-label {
        font-size: 0.85em;
        font-weight: 600;
        color: #2a4365;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .vendor-edit-label.required::after {
        content: ' *';
        color: #e53e3e;
    }

    .vendor-edit-input,
    .vendor-edit-select,
    .vendor-edit-textarea {
        padding: 10px 12px;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        font-size: 0.95em;
        font-family: inherit;
        color: #2a4365;
        transition: all 0.2s ease;
    }

    .vendor-edit-input:focus,
    .vendor-edit-select:focus,
    .vendor-edit-textarea:focus {
        outline: none;
        border-color: #2a4365;
        box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
    }

    .vendor-edit-input:disabled,
    .vendor-edit-select:disabled {
        background-color: #edf2f7;
        color: #a0aec0;
        cursor: not-allowed;
    }

    .vendor-edit-textarea {
        resize: vertical;
        min-height: 80px;
    }

    /* Status Selector */
    .vendor-edit-status-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
    }

    .vendor-edit-status-option {
        position: relative;
    }

    .vendor-edit-status-option input[type="radio"] {
        display: none;
    }

    .vendor-edit-status-label {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px 15px;
        border: 2px solid #cbd5e0;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s ease;
        text-align: center;
        min-height: 45px;
    }

    .vendor-edit-status-option input[type="radio"]:checked + .vendor-edit-status-label {
        border-color: #2a4365;
        background-color: #edf2f7;
        color: #2a4365;
    }

    /* Modal Buttons */
    .vendor-edit-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 0.9em;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .vendor-edit-btn-primary {
        background: #2a4365;
        color: white;
    }

    .vendor-edit-btn-primary:hover {
        background: #1a365d;
        transform: translateY(-2px);
    }

    .vendor-edit-btn-primary:disabled {
        background: #cbd5e0;
        cursor: not-allowed;
        transform: none;
    }

    .vendor-edit-btn-secondary {
        background: #e2e8f0;
        color: #2a4365;
    }

    .vendor-edit-btn-secondary:hover {
        background: #cbd5e0;
    }

    /* Error and Success States */
    .vendor-edit-error {
        background-color: #fff5f5;
        border: 1px solid #fed7d7;
        color: #742a2a;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        display: none;
    }

    .vendor-edit-error.show {
        display: block;
    }

    .vendor-edit-error i {
        margin-right: 8px;
    }

    .vendor-edit-success {
        background-color: #f0fff4;
        border: 1px solid #9ae6b4;
        color: #22543d;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        display: none;
    }

    .vendor-edit-success.show {
        display: block;
    }

    .vendor-edit-success i {
        margin-right: 8px;
    }

    /* Validation Feedback */
    .vendor-edit-form-group.error .vendor-edit-input,
    .vendor-edit-form-group.error .vendor-edit-select,
    .vendor-edit-form-group.error .vendor-edit-textarea {
        border-color: #e53e3e;
        background-color: #fff5f5;
    }

    .vendor-edit-error-message {
        font-size: 0.8em;
        color: #e53e3e;
        margin-top: 4px;
        display: none;
    }

    .vendor-edit-form-group.error .vendor-edit-error-message {
        display: block;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .vendor-edit-modal-content {
            width: 95%;
            max-height: 95vh;
        }

        .vendor-edit-grid {
            grid-template-columns: 1fr;
        }

        .vendor-edit-modal-header h2 {
            font-size: 1.2em;
        }

        .vendor-edit-modal-footer {
            flex-direction: column-reverse;
        }

        .vendor-edit-btn {
            width: 100%;
            justify-content: center;
        }
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
</style>

<script>
    // Store current vendor ID being edited
    let vendorEditFormData = {};

    /**
     * Open vendor edit modal and load vendor data
     * @param {number} vendorId - The vendor ID to edit
     */
    function openVendorEditModal(vendorId) {
        currentVendorId = vendorId;
        const modal = document.getElementById('vendorEditModal');
        const container = document.getElementById('vendorEditContainer');

        // Show modal with loading state
        modal.classList.add('active');
        container.innerHTML = `
            <div class="vendor-edit-loading">
                <i class="fas fa-spinner"></i>
                <p>Loading vendor details...</p>
            </div>
        `;

        // Fetch vendor details from backend
        fetch(`fetch_vendor_details.php?vendor_id=${vendorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    vendorEditFormData = data.data;
                    displayVendorEditForm(data.data);
                } else {
                    showVendorEditError(data.message || 'Failed to load vendor details');
                }
            })
            .catch(error => {
                console.error('Error fetching vendor details:', error);
                showVendorEditError('An error occurred while loading vendor details. Please try again.');
            });

        // Close modal when overlay is clicked
        document.querySelector('.vendor-edit-modal-overlay').addEventListener('click', closeVendorEditModal);
    }

    /**
     * Display vendor edit form
     * @param {object} vendor - Vendor data object
     */
    function displayVendorEditForm(vendor) {
        const container = document.getElementById('vendorEditContainer');

        let html = `
            <form id="vendorEditForm" class="vendor-edit-form" onsubmit="return submitVendorEdit(event);">
                <!-- Error Message Container -->
                <div id="vendorEditErrorMsg" class="vendor-edit-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorText"></span>
                </div>

                <!-- Success Message Container -->
                <div id="vendorEditSuccessMsg" class="vendor-edit-success">
                    <i class="fas fa-check-circle"></i>
                    <span id="successText"></span>
                </div>

                <!-- Basic Information Section -->
                <div class="vendor-edit-section">
                    <div class="vendor-edit-section-title">
                        <i class="fas fa-user-circle"></i>
                        <span>Basic Information</span>
                    </div>
                    <div class="vendor-edit-grid">
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label required">Vendor Code</label>
                            <input type="text" class="vendor-edit-input" id="vendor_unique_code" value="${vendor.vendor_unique_code || ''}" placeholder="Enter vendor code" readonly disabled>
                            <small style="color: #a0aec0; font-size: 0.8em;">Auto-generated (Read-only)</small>
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label required">Full Name</label>
                            <input type="text" class="vendor-edit-input" id="vendor_full_name" value="${vendor.vendor_full_name || ''}" placeholder="Enter full name">
                            <span class="vendor-edit-error-message">Full name is required</span>
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label required">Vendor Type</label>
                            <select class="vendor-edit-select" id="vendor_type_category">
                                <option value="">Select vendor type</option>
                                
                                <!-- Labour Contractor Options -->
                                <optgroup label="Labour Contractor">
                                    <option value="labour_skilled">Skilled Labour</option>
                                    <option value="labour_unskilled">Unskilled Labour</option>
                                    <option value="labour_semi_skilled">Semi-Skilled Labour</option>
                                    <option value="labour_specialized">Specialized Labour</option>
                                </optgroup>
                                
                                <!-- Material Contractor Options -->
                                <optgroup label="Material Contractor">
                                    <option value="material_concrete">Concrete Supplier</option>
                                    <option value="material_steel">Steel & Rebar Supplier</option>
                                    <option value="material_bricks">Bricks & Blocks Supplier</option>
                                    <option value="material_general">General Materials Contractor</option>
                                </optgroup>
                                
                                <!-- Material Supplier Options -->
                                <optgroup label="Material Supplier">
                                    <option value="supplier_equipment">Equipment Supplier</option>
                                    <option value="supplier_cement">Cement Supplier</option>
                                    <option value="supplier_sand_aggregate">Sand & Aggregate Supplier</option>
                                    <option value="supplier_tools">Tools & Hardware Supplier</option>
                                </optgroup>
                            </select>
                            <span class="vendor-edit-error-message">Vendor type is required</span>
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">Category Type</label>
                            <select class="vendor-edit-select" id="vendor_category_type">
                                <option value="">Select category type</option>
                                <option value="Labour Contractor">Labour Contractor</option>
                                <option value="Material Contractor">Material Contractor</option>
                                <option value="Material Supplier">Material Supplier</option>
                                <option value="Equipment Supplier">Equipment Supplier</option>
                                <option value="Service Provider">Service Provider</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label required">Email Address</label>
                            <input type="email" class="vendor-edit-input" id="vendor_email_address" value="${vendor.vendor_email_address || ''}" placeholder="Enter email address">
                            <span class="vendor-edit-error-message">Valid email is required</span>
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label required">Primary Phone</label>
                            <input type="tel" class="vendor-edit-input" id="vendor_phone_primary" value="${vendor.vendor_phone_primary || ''}" placeholder="Enter primary phone number">
                            <span class="vendor-edit-error-message">Phone number is required</span>
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">Alternate Phone</label>
                            <input type="tel" class="vendor-edit-input" id="vendor_phone_alternate" value="${vendor.vendor_phone_alternate || ''}" placeholder="Enter alternate phone number">
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label required">Status</label>
                            <div class="vendor-edit-status-options">
                                <div class="vendor-edit-status-option">
                                    <input type="radio" id="status_active" name="vendor_status" value="active" ${vendor.vendor_status === 'active' ? 'checked' : ''}>
                                    <label for="status_active" class="vendor-edit-status-label">Active</label>
                                </div>
                                <div class="vendor-edit-status-option">
                                    <input type="radio" id="status_inactive" name="vendor_status" value="inactive" ${vendor.vendor_status === 'inactive' ? 'checked' : ''}>
                                    <label for="status_inactive" class="vendor-edit-status-label">Inactive</label>
                                </div>
                                <div class="vendor-edit-status-option">
                                    <input type="radio" id="status_suspended" name="vendor_status" value="suspended" ${vendor.vendor_status === 'suspended' ? 'checked' : ''}>
                                    <label for="status_suspended" class="vendor-edit-status-label">Suspended</label>
                                </div>
                                <div class="vendor-edit-status-option">
                                    <input type="radio" id="status_archived" name="vendor_status" value="archived" ${vendor.vendor_status === 'archived' ? 'checked' : ''}>
                                    <label for="status_archived" class="vendor-edit-status-label">Archived</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Banking Details Section -->
                <div class="vendor-edit-section">
                    <div class="vendor-edit-section-title">
                        <i class="fas fa-university"></i>
                        <span>Banking Details</span>
                    </div>
                    <div class="vendor-edit-grid">
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">Bank Name</label>
                            <input type="text" class="vendor-edit-input" id="bank_name" value="${vendor.bank_name || ''}" placeholder="Enter bank name">
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">Account Number</label>
                            <input type="text" class="vendor-edit-input" id="bank_account_number" value="${vendor.bank_account_number || ''}" placeholder="Enter account number">
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">IFSC Code</label>
                            <input type="text" class="vendor-edit-input" id="bank_ifsc_code" value="${vendor.bank_ifsc_code || ''}" placeholder="Enter IFSC code">
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">Account Type</label>
                            <select class="vendor-edit-select" id="bank_account_type">
                                <option value="">Select account type</option>
                                <option value="savings" ${vendor.bank_account_type === 'savings' ? 'selected' : ''}>Savings</option>
                                <option value="current" ${vendor.bank_account_type === 'current' ? 'selected' : ''}>Current</option>
                                <option value="business" ${vendor.bank_account_type === 'business' ? 'selected' : ''}>Business</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- GST Details Section -->
                <div class="vendor-edit-section">
                    <div class="vendor-edit-section-title">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>GST Details</span>
                    </div>
                    <div class="vendor-edit-grid">
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">GST Number</label>
                            <input type="text" class="vendor-edit-input" id="gst_number" value="${vendor.gst_number || ''}" placeholder="Enter GST number">
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">GST State</label>
                            <input type="text" class="vendor-edit-input" id="gst_state" value="${vendor.gst_state || ''}" placeholder="Enter GST state">
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">GST Type</label>
                            <select class="vendor-edit-select" id="gst_type_category">
                                <option value="">Select GST type</option>
                                <option value="registered" ${vendor.gst_type_category === 'registered' ? 'selected' : ''}>Registered</option>
                                <option value="unregistered" ${vendor.gst_type_category === 'unregistered' ? 'selected' : ''}>Unregistered</option>
                                <option value="composition" ${vendor.gst_type_category === 'composition' ? 'selected' : ''}>Composition</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Address Details Section -->
                <div class="vendor-edit-section">
                    <div class="vendor-edit-section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Address Details</span>
                    </div>
                    <div class="vendor-edit-grid">
                        <div class="vendor-edit-form-group full-width">
                            <label class="vendor-edit-label">Street Address</label>
                            <textarea class="vendor-edit-textarea" id="address_street" placeholder="Enter street address">${vendor.address_street || ''}</textarea>
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">City</label>
                            <input type="text" class="vendor-edit-input" id="address_city" value="${vendor.address_city || ''}" placeholder="Enter city">
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">State</label>
                            <input type="text" class="vendor-edit-input" id="address_state" value="${vendor.address_state || ''}" placeholder="Enter state">
                        </div>
                        <div class="vendor-edit-form-group">
                            <label class="vendor-edit-label">Postal Code</label>
                            <input type="text" class="vendor-edit-input" id="address_postal_code" value="${vendor.address_postal_code || ''}" placeholder="Enter postal code">
                        </div>
                    </div>
                </div>
            </form>
        `;

        container.innerHTML = html;

        // Set selected values for dropdowns after form is rendered
        setTimeout(() => {
            const vendorTypeSelect = document.getElementById('vendor_type_category');
            const vendorCategorySelect = document.getElementById('vendor_category_type');
            
            if (vendorTypeSelect && vendor.vendor_type_category) {
                vendorTypeSelect.value = vendor.vendor_type_category;
            }
            
            if (vendorCategorySelect && vendor.vendor_category_type) {
                vendorCategorySelect.value = vendor.vendor_category_type;
            }
        }, 0);
    }

    /**
     * Show error message in vendor edit modal
     * @param {string} message - Error message to display
     */
    function showVendorEditError(message) {
        const container = document.getElementById('vendorEditContainer');
        container.innerHTML = `
            <div style="background-color: #fff5f5; border: 1px solid #fed7d7; color: #742a2a; padding: 20px; border-radius: 8px; text-align: center;">
                <i class="fas fa-exclamation-circle" style="font-size: 2em; display: block; margin-bottom: 10px;"></i>
                <p>${message}</p>
            </div>
        `;
    }

    /**
     * Close vendor edit modal
     */
    function closeVendorEditModal() {
        const modal = document.getElementById('vendorEditModal');
        modal.classList.remove('active');
        currentVendorId = null;
        vendorEditFormData = {};
    }

    /**
     * Validate vendor edit form
     * @returns {boolean} - True if form is valid
     */
    function validateVendorEditForm() {
        const form = document.getElementById('vendorEditForm');
        if (!form) return false;

        // Clear previous error states
        document.querySelectorAll('.vendor-edit-form-group').forEach(group => {
            group.classList.remove('error');
        });

        let isValid = true;
        const errors = [];

        // Get form values
        const vendor_full_name = document.getElementById('vendor_full_name').value.trim();
        const vendor_type_category = document.getElementById('vendor_type_category').value;
        const vendor_email_address = document.getElementById('vendor_email_address').value.trim();
        const vendor_phone_primary = document.getElementById('vendor_phone_primary').value.trim();
        const vendor_status = document.querySelector('input[name="vendor_status"]:checked');

        // Validate Full Name
        if (!vendor_full_name) {
            document.getElementById('vendor_full_name').closest('.vendor-edit-form-group').classList.add('error');
            errors.push('Full name is required');
            isValid = false;
        }

        // Validate Vendor Type
        if (!vendor_type_category) {
            document.getElementById('vendor_type_category').closest('.vendor-edit-form-group').classList.add('error');
            errors.push('Vendor type is required');
            isValid = false;
        }

        // Validate Email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!vendor_email_address) {
            document.getElementById('vendor_email_address').closest('.vendor-edit-form-group').classList.add('error');
            errors.push('Email address is required');
            isValid = false;
        } else if (!emailRegex.test(vendor_email_address)) {
            document.getElementById('vendor_email_address').closest('.vendor-edit-form-group').classList.add('error');
            errors.push('Valid email address is required');
            isValid = false;
        }

        // Validate Phone
        if (!vendor_phone_primary) {
            document.getElementById('vendor_phone_primary').closest('.vendor-edit-form-group').classList.add('error');
            errors.push('Primary phone number is required');
            isValid = false;
        } else if (!/^\d{10,}$/.test(vendor_phone_primary.replace(/\D/g, ''))) {
            document.getElementById('vendor_phone_primary').closest('.vendor-edit-form-group').classList.add('error');
            errors.push('Phone number must be at least 10 digits');
            isValid = false;
        }

        // Validate Status
        if (!vendor_status) {
            errors.push('Vendor status is required');
            isValid = false;
        }

        // Show error message if validation fails
        if (!isValid) {
            showFormValidationError(errors.join('<br>'));
        }

        return isValid;
    }

    /**
     * Show form validation error
     * @param {string} message - Error message to display
     */
    function showFormValidationError(message) {
        const errorContainer = document.getElementById('vendorEditErrorMsg');
        const errorText = document.getElementById('errorText');
        if (errorContainer && errorText) {
            errorText.innerHTML = message;
            errorContainer.classList.add('show');
        }
    }

    /**
     * Clear error message display
     */
    function clearFormError() {
        const errorContainer = document.getElementById('vendorEditErrorMsg');
        if (errorContainer) {
            errorContainer.classList.remove('show');
        }
    }

    /**
     * Submit vendor edit form
     * @param {Event} event - Form submit event (optional)
     */
    function submitVendorEdit(event) {
        if (event) {
            event.preventDefault();
        }

        // Clear previous errors
        clearFormError();

        // Validate form
        if (!validateVendorEditForm()) {
            return false;
        }

        // Disable submit button
        const submitBtn = document.querySelector('.vendor-edit-btn-primary');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Saving...';

        // Collect form data
        const formData = {
            vendor_id: currentVendorId,
            vendor_full_name: document.getElementById('vendor_full_name').value.trim(),
            vendor_type_category: document.getElementById('vendor_type_category').value,
            vendor_category_type: document.getElementById('vendor_category_type').value.trim(),
            vendor_email_address: document.getElementById('vendor_email_address').value.trim(),
            vendor_phone_primary: document.getElementById('vendor_phone_primary').value.trim(),
            vendor_phone_alternate: document.getElementById('vendor_phone_alternate').value.trim(),
            vendor_status: document.querySelector('input[name="vendor_status"]:checked').value,
            bank_name: document.getElementById('bank_name').value.trim(),
            bank_account_number: document.getElementById('bank_account_number').value.trim(),
            bank_ifsc_code: document.getElementById('bank_ifsc_code').value.trim(),
            bank_account_type: document.getElementById('bank_account_type').value,
            gst_number: document.getElementById('gst_number').value.trim(),
            gst_state: document.getElementById('gst_state').value.trim(),
            gst_type_category: document.getElementById('gst_type_category').value,
            address_street: document.getElementById('address_street').value.trim(),
            address_city: document.getElementById('address_city').value.trim(),
            address_state: document.getElementById('address_state').value.trim(),
            address_postal_code: document.getElementById('address_postal_code').value.trim()
        };

        // Submit data to backend
        fetch('update_vendor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                const successContainer = document.getElementById('vendorEditSuccessMsg');
                const successText = document.getElementById('successText');
                if (successContainer && successText) {
                    successText.innerHTML = data.message || 'Vendor details updated successfully!';
                    successContainer.classList.add('show');
                }

                // Close modal after 1.5 seconds
                setTimeout(() => {
                    closeVendorEditModal();
                    
                    // Refresh the vendor details modal if it was open
                    if (currentVendorId) {
                        openVendorDetailsModal(currentVendorId);
                    }
                }, 1500);
            } else {
                showFormValidationError(data.message || 'Failed to update vendor details. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error updating vendor:', error);
            showFormValidationError('An error occurred while updating vendor details. Please try again.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });

        return false;
    }

    /**
     * Close modal when overlay is clicked
     */
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.querySelector('.vendor-edit-modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', closeVendorEditModal);
        }
    });

    // Close modal when Escape key is pressed
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('vendorEditModal');
            if (modal && modal.classList.contains('active')) {
                closeVendorEditModal();
            }
        }
    });
</script>
```