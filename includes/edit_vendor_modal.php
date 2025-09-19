<!-- Edit Vendor Modal -->
<div class="modal fade" id="editVendorModal" tabindex="-1" aria-labelledby="editVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content edit-vendor-modal">
            <div class="modal-header edit-vendor-modal-header">
                <h5 class="modal-title edit-vendor-modal-title" id="editVendorModalLabel">
                    <i class="fas fa-edit me-2"></i>
                    Edit Vendor Details
                </h5>
                <button type="button" class="btn-close edit-vendor-close-btn" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body edit-vendor-modal-body">
                <!-- Loading Indicator -->
                <div class="text-center py-4" id="editVendorLoader" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading vendor details...</p>
                </div>
                
                <!-- Success Message -->
                <div class="alert alert-success" id="editVendorSuccess" style="display: none;">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="editSuccessMessage">Vendor updated successfully!</span>
                </div>
                
                <!-- Error Message -->
                <div class="alert alert-danger" id="editVendorError" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="editErrorMessage">Failed to update vendor</span>
                </div>
                
                <!-- Edit Form -->
                <form id="editVendorForm" style="display: none;">
                    <input type="hidden" id="editVendorId" name="vendor_id">
                    
                    <!-- Basic Information Section -->
                    <div class="vendor-edit-section">
                        <div class="vendor-edit-header">
                            <i class="fas fa-user me-2"></i>
                            <span>Basic Information</span>
                        </div>
                        <div class="vendor-edit-grid">
                            <div class="vendor-edit-item">
                                <label for="editFullName">Full Name <span class="required">*</span></label>
                                <input type="text" class="vendor-edit-control" id="editFullName" name="full_name" required>
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editVendorType">Vendor Type <span class="required">*</span></label>
                                <select class="vendor-edit-control" id="editVendorType" name="vendor_type" required>
                                    <option value="">Select Type</option>
                                    <option value="supplier">Supplier</option>
                                    <option value="contractor">Contractor</option>
                                    <option value="service_provider">Service Provider</option>
                                    <option value="consultant">Consultant</option>
                                    <option value="freelancer">Freelancer</option>
                                    <option value="tile_contractor">Tile Contractor</option>
                                    <option value="cement_supplier">Cement Supplier</option>
                                    <option value="labour_supplier">Labour Supplier</option>
                                    <option value="electrical_contractor">Electrical Contractor</option>
                                    <option value="plumbing_contractor">Plumbing Contractor</option>
                                    <option value="paint_contractor">Paint Contractor</option>
                                    <option value="steel_supplier">Steel Supplier</option>
                                    <option value="sand_supplier">Sand Supplier</option>
                                    <option value="brick_supplier">Brick Supplier</option>
                                    <option value="hardware_supplier">Hardware Supplier</option>
                                    <option value="equipment_rental">Equipment Rental</option>
                                    <option value="transport_contractor">Transport Contractor</option>
                                    <option value="security_services">Security Services</option>
                                    <option value="cleaning_services">Cleaning Services</option>
                                    <option value="catering_services">Catering Services</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editPhoneNumber">Phone Number <span class="required">*</span></label>
                                <input type="tel" class="vendor-edit-control" id="editPhoneNumber" name="phone_number" required>
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editAltPhoneNumber">Alternative Number</label>
                                <input type="tel" class="vendor-edit-control" id="editAltPhoneNumber" name="alternative_number">
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editEmail">Email Address</label>
                                <input type="email" class="vendor-edit-control" id="editEmail" name="email">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Information Section -->
                    <div class="vendor-edit-section">
                        <div class="vendor-edit-header">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span>Address Information</span>
                        </div>
                        <div class="vendor-edit-grid">
                            <div class="vendor-edit-item full-width">
                                <label for="editStreetAddress">Street Address</label>
                                <textarea class="vendor-edit-control" id="editStreetAddress" name="street_address" rows="2"></textarea>
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editCity">City</label>
                                <input type="text" class="vendor-edit-control" id="editCity" name="city">
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editState">State</label>
                                <input type="text" class="vendor-edit-control" id="editState" name="state">
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editZipCode">ZIP Code</label>
                                <input type="text" class="vendor-edit-control" id="editZipCode" name="zip_code">
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editCountry">Country</label>
                                <input type="text" class="vendor-edit-control" id="editCountry" name="country">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Information Section -->
                    <div class="vendor-edit-section">
                        <div class="vendor-edit-header">
                            <i class="fas fa-credit-card me-2"></i>
                            <span>Financial Information</span>
                        </div>
                        <div class="vendor-edit-grid">
                            <div class="vendor-edit-item">
                                <label for="editBankName">Bank Name</label>
                                <input type="text" class="vendor-edit-control" id="editBankName" name="bank_name">
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editAccountNumber">Account Number</label>
                                <input type="text" class="vendor-edit-control" id="editAccountNumber" name="account_number">
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editRoutingNumber">Routing Number</label>
                                <input type="text" class="vendor-edit-control" id="editRoutingNumber" name="routing_number">
                            </div>
                            <div class="vendor-edit-item">
                                <label for="editAccountType">Account Type</label>
                                <select class="vendor-edit-control" id="editAccountType" name="account_type">
                                    <option value="">Select Type</option>
                                    <option value="checking">Checking</option>
                                    <option value="savings">Savings</option>
                                    <option value="business">Business</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information Section -->
                    <div class="vendor-edit-section">
                        <div class="vendor-edit-header">
                            <i class="fas fa-info-circle me-2"></i>
                            <span>Additional Information</span>
                        </div>
                        <div class="vendor-edit-grid">
                            <div class="vendor-edit-item full-width">
                                <label for="editAdditionalNotes">Notes</label>
                                <textarea class="vendor-edit-control" id="editAdditionalNotes" name="additional_notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer edit-vendor-modal-footer">
                <button type="button" class="vendor-btn vendor-btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancel
                </button>
                <button type="button" class="vendor-btn vendor-btn-success" id="saveVendorChanges" style="display: none;">
                    <i class="fas fa-save me-2"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Edit Vendor Modal Styles */
.edit-vendor-modal {
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    overflow: hidden;
}

.edit-vendor-modal-header {
    background: linear-gradient(135deg, #f0f8ff 0%, #e1f5fe 100%);
    border-bottom: 1px solid #dee2e6;
    padding: 1.5rem 2rem;
    border-radius: 12px 12px 0 0;
}

.edit-vendor-modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1976d2;
    margin: 0;
    display: flex;
    align-items: center;
}

.edit-vendor-close-btn {
    background: #ffffff !important;
    border: 2px solid #6c757d !important;
    color: #6c757d !important;
    font-size: 1.8rem !important;
    font-weight: bold !important;
    opacity: 1 !important;
    transition: all 0.3s ease !important;
    border-radius: 6px !important;
    padding: 0.4rem 0.6rem !important;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.2) !important;
    min-width: 36px !important;
    min-height: 36px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.edit-vendor-close-btn:hover {
    background: #6c757d !important;
    color: #ffffff !important;
    border-color: #5a6268 !important;
    transform: scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3) !important;
}

.edit-vendor-modal-body {
    padding: 2rem;
    background: #fdfdfd;
    max-height: 70vh;
    overflow-y: auto;
}

.vendor-edit-section {
    margin-bottom: 2rem;
    background: #ffffff;
    border: 1px solid #f1f3f4;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.vendor-edit-section:last-child {
    margin-bottom: 0;
}

.vendor-edit-header {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f8f9fa;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.vendor-edit-header i {
    color: #6c757d;
    font-size: 0.9rem;
}

.vendor-edit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
}

.vendor-edit-item {
    display: flex;
    flex-direction: column;
}

.vendor-edit-item.full-width {
    grid-column: 1 / -1;
}

.vendor-edit-item label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.required {
    color: #dc3545;
    font-weight: 400;
}

.vendor-edit-control {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #ffffff;
    color: #495057;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.vendor-edit-control:focus {
    outline: none;
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.15), 0 2px 8px rgba(0, 0, 0, 0.08);
    background: #fafafa;
    transform: translateY(-1px);
}

.vendor-edit-control:hover:not(:focus) {
    border-color: #bbb;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

select.vendor-edit-control {
    cursor: pointer;
}

textarea.vendor-edit-control {
    resize: vertical;
    min-height: 60px;
}

.edit-vendor-modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 1.25rem 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.vendor-btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: #ffffff;
    border: 1px solid transparent;
}

.vendor-btn-success:hover {
    background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.vendor-btn-success:disabled {
    background: #adb5bd;
    color: #ffffff;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .edit-vendor-modal-body {
        padding: 1.5rem;
        max-height: 60vh;
    }
    
    .vendor-edit-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .vendor-edit-section {
        padding: 1rem;
    }
    
    .edit-vendor-modal-footer {
        padding: 1rem 1.5rem;
        flex-direction: column;
    }
    
    .vendor-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Scrollbar Styling */
.edit-vendor-modal-body::-webkit-scrollbar {
    width: 6px;
}

.edit-vendor-modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.edit-vendor-modal-body::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.edit-vendor-modal-body::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>