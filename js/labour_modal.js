/**
 * Labour Modal JavaScript Functions - Simplified Version
 * 
 * This file contains all JavaScript functions for the labour modal
 * Can be included in any page that needs labour functionality
 */

// Global labour modal functions
const LabourModal = {
    
    /**
     * Initialize labour modal functionality
     * Call this after including the modal HTML
     */
    init: function(apiEndpoint = 'payment_expenses.php') {
        console.log('LabourModal.init() called with endpoint:', apiEndpoint);
        this.apiEndpoint = apiEndpoint;
        this.bindEvents();
        console.log('LabourModal.init() completed');
    },

    /**
     * Bind event listeners
     */
    bindEvents: function() {
        // Save labour functionality
        const saveBtn = document.getElementById('saveLabourBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.saveLabour();
            });
        }

        // Close modal when clicking backdrop
        const modal = document.getElementById('addLabourModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                // Close if clicking on the modal backdrop (not the container)
                if (e.target === modal || e.target.classList.contains('labour-modal-backdrop')) {
                    this.hide();
                }
            });
        }
    },

    /**
     * Show the labour modal
     */
    show: function() {
        // Close any other modals first
        this.closeOtherModals();
        
        const modal = document.getElementById('addLabourModal');
        
        if (modal) {
            // Reset all styles and classes first
            modal.className = '';
            modal.style.cssText = '';
            
            // Set the modal classes and styles correctly
            modal.classList.add('labour-modal-visible');
            
            // Apply positioning and display styles with highest priority
            modal.style.cssText = `
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                z-index: 999999 !important;
                font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            `;
            
            // Ensure backdrop styling is applied to the container
            const backdrop = modal.querySelector('.labour-modal-backdrop');
            if (backdrop) {
                backdrop.style.cssText = `
                    position: absolute !important;
                    top: 0 !important;
                    left: 0 !important;
                    width: 100% !important;
                    height: 100% !important;
                    background-color: rgba(0, 0, 0, 0.5) !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    padding: 20px !important;
                    box-sizing: border-box !important;
                `;
            }
            
            // Ensure body doesn't scroll
            document.body.style.overflow = 'hidden';
            
            // Force DOM reflow
            modal.offsetHeight;
        }
    },

    /**
     * Close other modals that might be open
     */
    closeOtherModals: function() {
        // Close vendor modal if it exists and is open
        const vendorModal = document.getElementById('addVendorModal');
        if (vendorModal) {
            // Try Bootstrap modal close first
            try {
                if (typeof bootstrap !== 'undefined') {
                    const bsVendorModal = bootstrap.Modal.getInstance(vendorModal);
                    if (bsVendorModal) {
                        bsVendorModal.hide();
                    }
                }
            } catch (error) {
                // Bootstrap modal close failed, using direct method
            }
            
            // Force close vendor modal
            vendorModal.style.display = 'none';
            vendorModal.classList.remove('show');
            
            // Remove any vendor modal backdrops
            const vendorBackdrops = document.querySelectorAll('.modal-backdrop');
            vendorBackdrops.forEach(backdrop => backdrop.remove());
        }
        
        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
    },

    /**
     * Hide the labour modal
     */
    hide: function() {
        const modal = document.getElementById('addLabourModal');
        if (modal) {
            // Remove visible class and add hidden class
            modal.classList.remove('labour-modal-visible');
            modal.classList.add('labour-modal-hidden');
            
            // Reset all inline styles completely
            modal.style.cssText = 'display: none !important;';
            
            // Reset backdrop styles if exists
            const backdrop = modal.querySelector('.labour-modal-backdrop');
            if (backdrop) {
                backdrop.style.cssText = '';
            }
            
            // Restore body scroll
            document.body.style.overflow = '';
            
            this.resetForm();
        }
    },

    /**
     * Reset the form
     */
    resetForm: function() {
        const form = document.getElementById('addLabourForm');
        if (form) {
            form.reset();
        }
    },

    /**
     * Save labour to database
     */
    saveLabour: function() {
        const form = document.getElementById('addLabourForm');
        const formData = new FormData(form);
        
        // Validate required fields
        const fullName = formData.get('full_name');
        const phoneNumber = formData.get('phone_number');
        
        if (!fullName || !phoneNumber) {
            this.showErrorMessage('Please fill in all required fields (Full Name and Phone Number).');
            return;
        }
        
        // Validate phone number format (only digits, spaces, dashes, parentheses)
        const phoneRegex = /^[\d\s\-\(\)\+]+$/;
        if (!phoneRegex.test(phoneNumber)) {
            this.showErrorMessage('Please enter a valid phone number.');
            return;
        }
        
        // Show loading state
        const saveBtn = document.getElementById('saveLabourBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
        saveBtn.disabled = true;
        
        // Prepare labour data
        const labourData = {};
        for (let [key, value] of formData.entries()) {
            labourData[key] = value;
        }
        
        // Send AJAX request
        fetch(this.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=add_labour&labour_data=' + encodeURIComponent(JSON.stringify(labourData))
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
                const event = new CustomEvent('labourAdded', {
                    detail: {
                        labourId: data.labour_id,
                        message: data.message
                    }
                });
                document.dispatchEvent(event);
            } else {
                this.showErrorMessage(data.message || 'Failed to add labour worker. Please try again.');
            }
        })
        .catch(error => {
            this.showErrorMessage('An error occurred while saving the labour worker. Please try again.');
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
        // Simple alert for now - can be enhanced later
        alert('Success: ' + message);
    },

    /**
     * Create inline modal as last resort
     */
    createInlineModal: function() {
        console.log('Creating inline modal as fallback...');
        
        // Remove existing modal
        const existing = document.getElementById('addLabourModal');
        if (existing) {
            existing.remove();
        }
        
        // Create new modal with inline styles
        const modalHTML = `
            <div id="addLabourModal" style="position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; z-index: 999999 !important; background: rgba(0,0,0,0.6) !important; display: flex !important; align-items: center !important; justify-content: center !important; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;">
                <div style="background: white !important; border-radius: 8px !important; width: 90% !important; max-width: 600px !important; max-height: 90vh !important; box-shadow: 0 8px 32px rgba(0,0,0,0.3) !important; overflow: hidden !important;">
                    <div style="background: #f8f9fa !important; border-bottom: 1px solid #e9ecef !important; padding: 20px !important; display: flex !important; justify-content: space-between !important; align-items: center !important;">
                        <h5 style="margin: 0 !important; font-size: 1.1rem !important; font-weight: 500 !important; color: #495057 !important;">
                            <i class="bi bi-people-fill" style="margin-right: 8px !important;"></i>
                            Add New Labour Worker
                        </h5>
                        <button type="button" onclick="LabourModal.hide()" style="background: none !important; border: 2px solid #dc3545 !important; color: #dc3545 !important; font-size: 20px !important; font-weight: bold !important; width: 32px !important; height: 32px !important; border-radius: 4px !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important;">&times;</button>
                    </div>
                    <div style="padding: 20px !important; max-height: 60vh !important; overflow-y: auto !important;">
                        <form id="addLabourForm">
                            <div style="margin-bottom: 15px !important;">
                                <label style="font-size: 0.9rem !important; font-weight: 500 !important; color: #495057 !important; margin-bottom: 5px !important; display: block !important;">Full Name <span style="color: #dc3545 !important;">*</span></label>
                                <input type="text" id="labourFullName" name="full_name" required style="width: 100% !important; padding: 8px 12px !important; border: 1px solid #ced4da !important; border-radius: 4px !important; font-size: 0.9rem !important;">
                            </div>
                            <div style="margin-bottom: 15px !important;">
                                <label style="font-size: 0.9rem !important; font-weight: 500 !important; color: #495057 !important; margin-bottom: 5px !important; display: block !important;">Phone Number <span style="color: #dc3545 !important;">*</span></label>
                                <input type="tel" id="labourPhone" name="phone_number" required style="width: 100% !important; padding: 8px 12px !important; border: 1px solid #ced4da !important; border-radius: 4px !important; font-size: 0.9rem !important;">
                            </div>
                            <div style="margin-bottom: 15px !important;">
                                <label style="font-size: 0.9rem !important; font-weight: 500 !important; color: #495057 !important; margin-bottom: 5px !important; display: block !important;">Email Address</label>
                                <input type="email" id="labourEmail" name="email" style="width: 100% !important; padding: 8px 12px !important; border: 1px solid #ced4da !important; border-radius: 4px !important; font-size: 0.9rem !important;">
                            </div>
                            <div style="margin-bottom: 15px !important;">
                                <label style="font-size: 0.9rem !important; font-weight: 500 !important; color: #495057 !important; margin-bottom: 5px !important; display: block !important;">Skill Type</label>
                                <select id="labourSkillType" name="skill_type" style="width: 100% !important; padding: 8px 12px !important; border: 1px solid #ced4da !important; border-radius: 4px !important; font-size: 0.9rem !important;">
                                    <option value="unskilled">Unskilled</option>
                                    <option value="semi_skilled">Semi-Skilled</option>
                                    <option value="skilled">Skilled</option>
                                    <option value="supervisor">Supervisor</option>
                                    <option value="helper">Helper</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 15px !important;">
                                <label style="font-size: 0.9rem !important; font-weight: 500 !important; color: #495057 !important; margin-bottom: 5px !important; display: block !important;">Notes</label>
                                <textarea id="labourNotes" name="notes" rows="3" style="width: 100% !important; padding: 8px 12px !important; border: 1px solid #ced4da !important; border-radius: 4px !important; font-size: 0.9rem !important; resize: vertical !important;"></textarea>
                            </div>
                        </form>
                    </div>
                    <div style="background: #f8f9fa !important; border-top: 1px solid #e9ecef !important; padding: 15px 20px !important; display: flex !important; justify-content: flex-end !important; gap: 10px !important;">
                        <button type="button" onclick="LabourModal.hide()" style="padding: 8px 16px !important; border-radius: 4px !important; font-size: 0.9rem !important; border: none !important; cursor: pointer !important; background-color: #6c757d !important; color: white !important;">Cancel</button>
                        <button type="button" id="saveLabourBtn" style="padding: 8px 16px !important; border-radius: 4px !important; font-size: 0.9rem !important; border: none !important; cursor: pointer !important; background-color: #0d6efd !important; color: white !important;">Add Labour Worker</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Re-bind save button event
        const saveBtn = document.getElementById('saveLabourBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.saveLabour();
            });
        }
        
        console.log('Inline modal created and displayed');
    },

    /**
     * Show error message
     */
    showErrorMessage: function(message) {
        // Simple alert for now - can be enhanced later
        alert('Error: ' + message);
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking for labour modal...');
    const modalElement = document.getElementById('addLabourModal');
    if (modalElement) {
        console.log('Labour modal element found, initializing LabourModal...');
        LabourModal.init();
        console.log('LabourModal initialized successfully');
        
        // Make LabourModal globally accessible
        window.LabourModal = LabourModal;
        console.log('LabourModal set on window object');
    } else {
        console.error('Labour modal element not found!');
    }
});