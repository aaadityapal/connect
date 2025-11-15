<?php
/**
 * Labour Edit Modal
 * Allows users to edit and update all labour details
 * Triggered when user clicks the "edit" button in labour details modal
 */
?>

<div id="labourEditModal" class="labour-edit-modal">
    <div class="labour-edit-modal-overlay"></div>
    <div class="labour-edit-modal-content">
        <!-- Modal Header -->
        <div class="labour-edit-modal-header">
            <h2>Edit Labour Details</h2>
            <button class="labour-edit-close-btn" onclick="closeLabourEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body with Loading State -->
        <div class="labour-edit-modal-body">
            <div id="labourEditContainer">
                <div class="labour-edit-loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading labour details...</p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="labour-edit-modal-footer">
            <button class="labour-edit-btn labour-edit-btn-secondary" onclick="closeLabourEditModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="labour-edit-btn labour-edit-btn-primary" onclick="submitLabourEdit()">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<style>
    /* Labour Edit Modal Styles */
    .labour-edit-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9998;
    }

    .labour-edit-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .labour-edit-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        cursor: pointer;
    }

    .labour-edit-modal-content {
        position: relative;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        max-width: 800px;
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

    .labour-edit-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px;
        border-bottom: 1px solid #e2e8f0;
    }

    .labour-edit-modal-header h2 {
        margin: 0;
        font-size: 1.5em;
        color: #2a4365;
        font-weight: 600;
    }

    .labour-edit-close-btn {
        background: none;
        border: none;
        font-size: 1.5em;
        color: #a0aec0;
        cursor: pointer;
        transition: color 0.2s ease;
        padding: 5px;
    }

    .labour-edit-close-btn:hover {
        color: #2a4365;
    }

    .labour-edit-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 25px;
    }

    .labour-edit-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 20px 25px;
        border-top: 1px solid #e2e8f0;
    }

    /* Loading State */
    .labour-edit-loading {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }

    .labour-edit-loading i {
        font-size: 2.5em;
        color: #2a4365;
        animation: spin 1s linear infinite;
        display: block;
        margin-bottom: 15px;
    }

    /* Form Styles */
    .labour-edit-form {
        display: grid;
        gap: 20px;
    }

    .labour-edit-section {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        background: #f7fafc;
    }

    .labour-edit-section-title {
        font-size: 1.1em;
        font-weight: 600;
        color: #2a4365;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .labour-edit-section-title i {
        font-size: 1.2em;
        color: #2a4365;
    }

    .labour-edit-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
    }

    .labour-edit-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .labour-edit-form-group.full-width {
        grid-column: 1 / -1;
    }

    .labour-edit-label {
        font-size: 0.85em;
        font-weight: 600;
        color: #2a4365;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .labour-edit-label.required::after {
        content: ' *';
        color: #e53e3e;
    }

    .labour-edit-input,
    .labour-edit-select,
    .labour-edit-textarea {
        padding: 10px 12px;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        font-size: 0.95em;
        font-family: inherit;
        color: #2a4365;
        transition: all 0.2s ease;
    }

    .labour-edit-input:focus,
    .labour-edit-select:focus,
    .labour-edit-textarea:focus {
        outline: none;
        border-color: #2a4365;
        box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
    }

    .labour-edit-input:disabled,
    .labour-edit-select:disabled {
        background-color: #edf2f7;
        color: #a0aec0;
        cursor: not-allowed;
    }

    .labour-edit-textarea {
        resize: vertical;
        min-height: 80px;
    }

    /* Status Selector */
    .labour-edit-status-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
    }

    .labour-edit-status-option {
        position: relative;
    }

    .labour-edit-status-option input[type="radio"] {
        display: none;
    }

    .labour-edit-status-label {
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

    .labour-edit-status-option input[type="radio"]:checked + .labour-edit-status-label {
        border-color: #2a4365;
        background-color: #edf2f7;
        color: #2a4365;
    }

    /* Modal Buttons */
    .labour-edit-btn {
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

    .labour-edit-btn-primary {
        background: #2a4365;
        color: white;
    }

    .labour-edit-btn-primary:hover {
        background: #1a365d;
        transform: translateY(-2px);
    }

    .labour-edit-btn-primary:disabled {
        background: #cbd5e0;
        cursor: not-allowed;
        transform: none;
    }

    .labour-edit-btn-secondary {
        background: #e2e8f0;
        color: #2a4365;
    }

    .labour-edit-btn-secondary:hover {
        background: #cbd5e0;
    }

    /* Error and Success States */
    .labour-edit-error {
        background-color: #fff5f5;
        border: 1px solid #fed7d7;
        color: #742a2a;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        display: none;
    }

    .labour-edit-error.show {
        display: block;
    }

    .labour-edit-error i {
        margin-right: 8px;
    }

    .labour-edit-success {
        background-color: #f0fff4;
        border: 1px solid #9ae6b4;
        color: #22543d;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        display: none;
    }

    .labour-edit-success.show {
        display: block;
    }

    .labour-edit-success i {
        margin-right: 8px;
    }

    /* Validation Feedback */
    .labour-edit-form-group.error .labour-edit-input,
    .labour-edit-form-group.error .labour-edit-select,
    .labour-edit-form-group.error .labour-edit-textarea {
        border-color: #e53e3e;
        background-color: #fff5f5;
    }

    .labour-edit-error-message {
        font-size: 0.8em;
        color: #e53e3e;
        margin-top: 4px;
        display: none;
    }

    .labour-edit-form-group.error .labour-edit-error-message {
        display: block;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .labour-edit-modal-content {
            width: 95%;
            max-height: 95vh;
        }

        .labour-edit-grid {
            grid-template-columns: 1fr;
        }

        .labour-edit-modal-header h2 {
            font-size: 1.2em;
        }

        .labour-edit-modal-footer {
            flex-direction: column-reverse;
        }

        .labour-edit-btn {
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
    // Store current labour ID being edited
    let labourEditFormData = {};

    /**
     * Open labour edit modal and load labour data
     * @param {number} labourId - The labour ID to edit
     */
    function openLabourEditModal(labourId) {
        currentLabourId = labourId;
        const modal = document.getElementById('labourEditModal');
        const container = document.getElementById('labourEditContainer');

        // Show modal with loading state
        modal.classList.add('active');
        container.innerHTML = `
            <div class="labour-edit-loading">
                <i class="fas fa-spinner"></i>
                <p>Loading labour details...</p>
            </div>
        `;

        // Fetch labour details from backend
        fetch(`fetch_labour_details.php?labour_id=${labourId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    labourEditFormData = data.data;
                    displayLabourEditForm(data.data);
                } else {
                    showLabourEditError(data.message || 'Failed to load labour details');
                }
            })
            .catch(error => {
                console.error('Error fetching labour details:', error);
                showLabourEditError('An error occurred while loading labour details. Please try again.');
            });

        // Close modal when overlay is clicked
        document.querySelector('.labour-edit-modal-overlay').addEventListener('click', closeLabourEditModal);
    }

    /**
     * Display labour edit form
     * @param {object} labour - Labour data object
     */
    function displayLabourEditForm(labour) {
        const container = document.getElementById('labourEditContainer');

        let html = `
            <form id="labourEditForm" class="labour-edit-form" onsubmit="return submitLabourEdit(event);">
                <!-- Error Message Container -->
                <div id="labourEditErrorMsg" class="labour-edit-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorText"></span>
                </div>

                <!-- Success Message Container -->
                <div id="labourEditSuccessMsg" class="labour-edit-success">
                    <i class="fas fa-check-circle"></i>
                    <span id="successText"></span>
                </div>

                <!-- Basic Information Section -->
                <div class="labour-edit-section">
                    <div class="labour-edit-section-title">
                        <i class="fas fa-user-circle"></i>
                        <span>Basic Information</span>
                    </div>
                    <div class="labour-edit-grid">
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label required">Labour Code</label>
                            <input type="text" class="labour-edit-input" id="labour_unique_code" value="${labour.labour_unique_code || ''}" placeholder="Enter labour code" readonly disabled>
                            <small style="color: #a0aec0; font-size: 0.8em;">Auto-generated (Read-only)</small>
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label required">Full Name</label>
                            <input type="text" class="labour-edit-input" id="full_name" value="${labour.full_name || ''}" placeholder="Enter full name">
                            <span class="labour-edit-error-message">Full name is required</span>
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label required">Labour Type</label>
                            <select class="labour-edit-select" id="labour_type">
                                <option value="">Select labour type</option>
                                <option value="permanent" ${labour.labour_type === 'permanent' ? 'selected' : ''}>Permanent</option>
                                <option value="temporary" ${labour.labour_type === 'temporary' ? 'selected' : ''}>Temporary</option>
                                <option value="vendor" ${labour.labour_type === 'vendor' ? 'selected' : ''}>Vendor</option>
                                <option value="other" ${labour.labour_type === 'other' ? 'selected' : ''}>Other</option>
                            </select>
                            <span class="labour-edit-error-message">Labour type is required</span>
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label required">Contact Number</label>
                            <input type="tel" class="labour-edit-input" id="contact_number" value="${labour.contact_number || ''}" placeholder="Enter contact number" pattern="[0-9]{10}">
                            <span class="labour-edit-error-message">Valid 10-digit phone number is required</span>
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label">Alternate Number</label>
                            <input type="tel" class="labour-edit-input" id="alt_number" value="${labour.alt_number || ''}" placeholder="Enter alternate number" pattern="[0-9]{10}">
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label">Daily Salary</label>
                            <input type="number" class="labour-edit-input" id="daily_salary" value="${labour.daily_salary || ''}" placeholder="Enter daily salary" step="0.01" min="0">
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label">Join Date</label>
                            <input type="date" class="labour-edit-input" id="join_date" value="${labour.join_date || ''}">
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label required">Status</label>
                            <div class="labour-edit-status-options">
                                <div class="labour-edit-status-option">
                                    <input type="radio" id="status_active" name="status" value="active" ${labour.status === 'active' ? 'checked' : ''}>
                                    <label for="status_active" class="labour-edit-status-label">Active</label>
                                </div>
                                <div class="labour-edit-status-option">
                                    <input type="radio" id="status_inactive" name="status" value="inactive" ${labour.status === 'inactive' ? 'checked' : ''}>
                                    <label for="status_inactive" class="labour-edit-status-label">Inactive</label>
                                </div>
                                <div class="labour-edit-status-option">
                                    <input type="radio" id="status_suspended" name="status" value="suspended" ${labour.status === 'suspended' ? 'checked' : ''}>
                                    <label for="status_suspended" class="labour-edit-status-label">Suspended</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Details Section -->
                <div class="labour-edit-section">
                    <div class="labour-edit-section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Address Details</span>
                    </div>
                    <div class="labour-edit-grid">
                        <div class="labour-edit-form-group full-width">
                            <label class="labour-edit-label">Street Address</label>
                            <textarea class="labour-edit-textarea" id="street_address" placeholder="Enter street address">${labour.street_address || ''}</textarea>
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label">City</label>
                            <input type="text" class="labour-edit-input" id="city" value="${labour.city || ''}" placeholder="Enter city">
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label">State</label>
                            <input type="text" class="labour-edit-input" id="state" value="${labour.state || ''}" placeholder="Enter state">
                        </div>
                        <div class="labour-edit-form-group">
                            <label class="labour-edit-label">Zip Code</label>
                            <input type="text" class="labour-edit-input" id="zip_code" value="${labour.zip_code || ''}" placeholder="Enter zip code" pattern="[0-9]{6}">
                        </div>
                    </div>
                </div>
            </form>
        `;

        container.innerHTML = html;
    }

    /**
     * Show error message in labour edit modal
     * @param {string} message - Error message to display
     */
    function showLabourEditError(message) {
        const container = document.getElementById('labourEditContainer');
        container.innerHTML = `
            <div style="background-color: #fff5f5; border: 1px solid #fed7d7; color: #742a2a; padding: 20px; border-radius: 8px; text-align: center;">
                <i class="fas fa-exclamation-circle" style="font-size: 2em; display: block; margin-bottom: 10px;"></i>
                <p>${message}</p>
            </div>
        `;
    }

    /**
     * Close labour edit modal
     */
    function closeLabourEditModal() {
        const modal = document.getElementById('labourEditModal');
        modal.classList.remove('active');
        currentLabourId = null;
        labourEditFormData = {};
    }

    /**
     * Validate labour edit form
     * @returns {boolean} - True if form is valid
     */
    function validateLabourEditForm() {
        const form = document.getElementById('labourEditForm');
        if (!form) return false;

        // Clear previous error states
        document.querySelectorAll('.labour-edit-form-group').forEach(group => {
            group.classList.remove('error');
        });

        let isValid = true;
        const errors = [];

        // Get form values
        const full_name = document.getElementById('full_name').value.trim();
        const labour_type = document.getElementById('labour_type').value;
        const contact_number = document.getElementById('contact_number').value.trim();
        const status = document.querySelector('input[name="status"]:checked');

        // Validate Full Name
        if (!full_name) {
            document.getElementById('full_name').closest('.labour-edit-form-group').classList.add('error');
            errors.push('Full name is required');
            isValid = false;
        }

        // Validate Labour Type
        if (!labour_type) {
            document.getElementById('labour_type').closest('.labour-edit-form-group').classList.add('error');
            errors.push('Labour type is required');
            isValid = false;
        }

        // Validate Phone
        if (!contact_number) {
            document.getElementById('contact_number').closest('.labour-edit-form-group').classList.add('error');
            errors.push('Contact number is required');
            isValid = false;
        } else if (!/^\d{10}$/.test(contact_number.replace(/\D/g, ''))) {
            document.getElementById('contact_number').closest('.labour-edit-form-group').classList.add('error');
            errors.push('Phone number must be exactly 10 digits');
            isValid = false;
        }

        // Validate Status
        if (!status) {
            errors.push('Labour status is required');
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
        const errorContainer = document.getElementById('labourEditErrorMsg');
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
        const errorContainer = document.getElementById('labourEditErrorMsg');
        if (errorContainer) {
            errorContainer.classList.remove('show');
        }
    }

    /**
     * Submit labour edit form
     * @param {Event} event - Form submit event (optional)
     */
    function submitLabourEdit(event) {
        if (event) {
            event.preventDefault();
        }

        // Clear previous errors
        clearFormError();

        // Validate form
        if (!validateLabourEditForm()) {
            return false;
        }

        // Disable submit button
        const submitBtn = document.querySelector('.labour-edit-btn-primary');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Saving...';

        // Collect form data
        const formData = {
            labour_id: currentLabourId,
            full_name: document.getElementById('full_name').value.trim(),
            labour_type: document.getElementById('labour_type').value,
            contact_number: document.getElementById('contact_number').value.trim(),
            alt_number: document.getElementById('alt_number').value.trim(),
            daily_salary: document.getElementById('daily_salary').value || null,
            join_date: document.getElementById('join_date').value || null,
            status: document.querySelector('input[name="status"]:checked').value,
            street_address: document.getElementById('street_address').value.trim(),
            city: document.getElementById('city').value.trim(),
            state: document.getElementById('state').value.trim(),
            zip_code: document.getElementById('zip_code').value.trim()
        };

        // Submit data to backend
        fetch('update_labour.php', {
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
                const successContainer = document.getElementById('labourEditSuccessMsg');
                const successText = document.getElementById('successText');
                if (successContainer && successText) {
                    successText.innerHTML = data.message || 'Labour details updated successfully!';
                    successContainer.classList.add('show');
                }

                // Close modal after 1.5 seconds
                setTimeout(() => {
                    closeLabourEditModal();
                    
                    // Refresh the labour details modal if it was open
                    if (currentLabourId) {
                        openLabourDetailsModal(currentLabourId);
                    }
                }, 1500);
            } else {
                showFormValidationError(data.message || 'Failed to update labour details. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error updating labour:', error);
            showFormValidationError('An error occurred while updating labour details. Please try again.');
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
        const overlay = document.querySelector('.labour-edit-modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', closeLabourEditModal);
        }
    });

    // Close modal when Escape key is pressed
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('labourEditModal');
            if (modal && modal.classList.contains('active')) {
                closeLabourEditModal();
            }
        }
    });
</script>
