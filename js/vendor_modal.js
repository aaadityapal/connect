/**
 * Vendor Modal JavaScript Functions
 * 
 * This file contains all JavaScript functions for the vendor modal
 * Can be included in any page that needs vendor functionality
 */

// Global vendor modal functions
window.VendorModal = {
    
    /**
     * Initialize vendor modal functionality
     * Call this after including the modal HTML
     */
    init: function(apiEndpoint = 'payment_expenses.php') {
        this.apiEndpoint = apiEndpoint;
        this.bindEvents();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function() {
        // Save vendor functionality
        const saveBtn = document.getElementById('saveVendorBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.saveVendor();
            });
        }

        // Financial section toggle functionality
        const toggleBtn = document.getElementById('toggleFinancialSection');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                this.toggleFinancialSection();
            });
        }

        // Vendor type change functionality
        const vendorTypeSelect = document.getElementById('vendorType');
        if (vendorTypeSelect) {
            vendorTypeSelect.addEventListener('change', () => {
                this.handleVendorTypeChange();
            });
        }

        // Back to list functionality
        const backToListBtn = document.getElementById('backToList');
        if (backToListBtn) {
            backToListBtn.addEventListener('click', () => {
                this.backToVendorList();
            });
        }

        // Reset form when modal is hidden
        const modal = document.getElementById('addVendorModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', () => {
                this.resetForm();
            });
        }
    },

    /**
     * Handle vendor type selection change
     */
    handleVendorTypeChange: function() {
        const vendorTypeSelect = document.getElementById('vendorType');
        const customTypeInput = document.getElementById('vendorCustomType');
        const backToListBtn = document.getElementById('backToList');
        
        if (vendorTypeSelect && customTypeInput && backToListBtn) {
            if (vendorTypeSelect.value === 'custom') {
                // Show custom input and back button
                vendorTypeSelect.style.display = 'none';
                customTypeInput.style.display = 'block';
                backToListBtn.style.display = 'inline-flex';
                customTypeInput.focus();
                
                // Make custom input required
                customTypeInput.setAttribute('required', 'required');
                vendorTypeSelect.removeAttribute('required');
            }
        }
    },

    /**
     * Go back to vendor type list
     */
    backToVendorList: function() {
        const vendorTypeSelect = document.getElementById('vendorType');
        const customTypeInput = document.getElementById('vendorCustomType');
        const backToListBtn = document.getElementById('backToList');
        
        if (vendorTypeSelect && customTypeInput && backToListBtn) {
            // Show select and hide custom input
            vendorTypeSelect.style.display = 'block';
            customTypeInput.style.display = 'none';
            backToListBtn.style.display = 'none';
            
            // Reset values and requirements
            vendorTypeSelect.value = '';
            customTypeInput.value = '';
            vendorTypeSelect.setAttribute('required', 'required');
            customTypeInput.removeAttribute('required');
        }
    },

    /**
     * Toggle financial section visibility
     */
    toggleFinancialSection: function() {
        const financialContent = document.getElementById('financialContent');
        const toggleIcon = document.getElementById('financialToggleIcon');
        const toggleBtn = document.getElementById('toggleFinancialSection');
        
        if (financialContent && toggleIcon && toggleBtn) {
            if (financialContent.classList.contains('collapsed')) {
                // Show the section
                financialContent.classList.remove('collapsed');
                toggleIcon.classList.remove('rotated');
                toggleBtn.title = 'Hide Financial Information';
            } else {
                // Hide the section
                financialContent.classList.add('collapsed');
                toggleIcon.classList.add('rotated');
                toggleBtn.title = 'Show Financial Information';
            }
        }
    },

    /**
     * Show the vendor modal
     */
    show: function() {
        const modal = document.getElementById('addVendorModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    },

    /**
     * Hide the vendor modal
     */
    hide: function() {
        const modal = document.getElementById('addVendorModal');
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    },

    /**
     * Reset the form
     */
    resetForm: function() {
        const form = document.getElementById('addVendorForm');
        if (form) {
            form.reset();
        }
        
        // Reset vendor type fields to default state
        const vendorTypeSelect = document.getElementById('vendorType');
        const customTypeInput = document.getElementById('vendorCustomType');
        const backToListBtn = document.getElementById('backToList');
        
        if (vendorTypeSelect && customTypeInput && backToListBtn) {
            vendorTypeSelect.style.display = 'block';
            customTypeInput.style.display = 'none';
            backToListBtn.style.display = 'none';
            customTypeInput.value = '';
            vendorTypeSelect.setAttribute('required', 'required');
            customTypeInput.removeAttribute('required');
        }
        
        // Reset financial section to closed state
        const financialContent = document.getElementById('financialContent');
        const toggleIcon = document.getElementById('financialToggleIcon');
        const toggleBtn = document.getElementById('toggleFinancialSection');
        
        if (financialContent && toggleIcon && toggleBtn) {
            financialContent.classList.add('collapsed');
            toggleIcon.classList.add('rotated');
            toggleBtn.title = 'Show Financial Information';
        }
    },

    /**
     * Save vendor to database
     */
    saveVendor: function() {
        const form = document.getElementById('addVendorForm');
        const formData = new FormData(form);
        
        // Validate required fields
        const fullName = formData.get('full_name');
        const phoneNumber = formData.get('phone_number');
        // Get vendor type (either from select or custom input)
        const vendorTypeSelect = document.getElementById('vendorType');
        const customTypeInput = document.getElementById('vendorCustomType');
        let vendorType = '';
        
        if (vendorTypeSelect.style.display === 'none' && customTypeInput.style.display === 'block') {
            // Custom type is active
            vendorType = customTypeInput.value.trim();
        } else {
            // Regular select is active
            vendorType = formData.get('vendor_type');
        }
        
        if (!fullName || !phoneNumber || !vendorType) {
            this.showErrorMessage('Please fill in all required fields (Full Name, Phone Number, and Vendor Type).');
            return;
        }
        
        // Validate phone number format (only digits, spaces, dashes, parentheses)
        const phoneRegex = /^[\d\s\-\(\)]+$/;
        if (!phoneRegex.test(phoneNumber)) {
            this.showErrorMessage('Please enter a valid phone number (numbers only).');
            return;
        }
        
        // Check phone number length (should be reasonable)
        const cleanPhone = phoneNumber.replace(/[\s\-\(\)]/g, '');
        if (cleanPhone.length < 7 || cleanPhone.length > 15) {
            this.showErrorMessage('Phone number should be between 7 and 15 digits.');
            return;
        }
        
        // Validate email if provided
        const email = formData.get('email');
        if (email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                this.showErrorMessage('Please enter a valid email address.');
                return;
            }
        }
        
        // Show loading state
        const saveBtn = document.getElementById('saveVendorBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Saving...';
        saveBtn.disabled = true;
        
        // Prepare vendor data with vendor type
        const vendorData = {};
        for (let [key, value] of formData.entries()) {
            vendorData[key] = value;
        }
        // Override vendor_type with the correct type (custom or selected)
        vendorData['vendor_type'] = vendorType;
        
        // Send AJAX request
        fetch(this.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=add_vendor&vendor_data=' + encodeURIComponent(JSON.stringify(vendorData))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                this.showSuccessMessage(data.message);
                
                // Close modal and reset form
                this.hide();
                this.resetForm();
                
                // Trigger custom event for pages to listen to
                const event = new CustomEvent('vendorAdded', {
                    detail: {
                        vendorId: data.vendor_id,
                        message: data.message
                    }
                });
                document.dispatchEvent(event);
                
                console.log('Vendor added with ID:', data.vendor_id);
            } else {
                this.showErrorMessage(data.message || 'Failed to add vendor. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error saving vendor:', error);
            this.showErrorMessage('An error occurred while saving the vendor. Please try again.');
        })
        .finally(() => {
            // Reset button state
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    },

    /**
     * Show success message
     */
    showSuccessMessage: function(message) {
        // Remove any existing alerts first
        this.removeExistingAlerts();
        
        // Create and show a success alert
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed vendor-alert';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.maxWidth = '400px';
        alertDiv.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    },

    /**
     * Show error message
     */
    showErrorMessage: function(message) {
        // Remove any existing alerts first
        this.removeExistingAlerts();
        
        // Create and show an error alert
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed vendor-alert';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.maxWidth = '400px';
        alertDiv.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 7 seconds (longer for errors)
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 7000);
    },

    /**
     * Remove existing vendor alerts
     */
    removeExistingAlerts: function() {
        const existingAlerts = document.querySelectorAll('.vendor-alert');
        existingAlerts.forEach(alert => alert.remove());
    }
};

// Auto-initialize if Bootstrap is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined' && document.getElementById('addVendorModal')) {
        VendorModal.init();
    }
});