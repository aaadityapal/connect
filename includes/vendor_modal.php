<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1" aria-labelledby="addVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content vendor-modal">
            <div class="modal-header vendor-modal-header">
                <h5 class="modal-title vendor-modal-title" id="addVendorModalLabel">
                    <i class="bi bi-person-plus me-2"></i>
                    Add New Vendor
                </h5>
                <button type="button" class="btn-close vendor-close-btn" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body vendor-modal-body">
                <form id="addVendorForm">
                    <div class="row g-4">
                        <!-- Basic Information -->
                        <div class="col-12">
                            <div class="vendor-section-header">
                                <i class="bi bi-person me-2"></i>
                                <span>Basic Information</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorFullName" class="vendor-form-label">Full Name <span class="vendor-required">*</span></label>
                                <input type="text" class="vendor-form-control" id="vendorFullName" name="full_name" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorPhone" class="vendor-form-label">Phone Number <span class="vendor-required">*</span></label>
                                <input type="tel" class="vendor-form-control" id="vendorPhone" name="phone_number" placeholder="Enter phone number" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorEmail" class="vendor-form-label">Email Address</label>
                                <input type="email" class="vendor-form-control" id="vendorEmail" name="email">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorType" class="vendor-form-label">Vendor Type <span class="vendor-required">*</span></label>
                                <select class="vendor-form-control" id="vendorType" name="vendor_type" required>
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
                                    <option value="custom">Custom Vendor Type</option>
                                </select>
                                <input type="text" class="vendor-form-control vendor-custom-type" id="vendorCustomType" name="custom_vendor_type" placeholder="Enter custom vendor type" style="display: none; margin-top: 0.5rem;">
                                <button type="button" class="vendor-back-to-list" id="backToList" style="display: none; margin-top: 0.5rem;">
                                    <i class="bi bi-arrow-left me-1"></i>
                                    Back to List
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="vendor-form-group">
                                <label for="vendorCompany" class="vendor-form-label">Company Name</label>
                                <input type="text" class="vendor-form-control" id="vendorCompany" name="company_name">
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="vendor-form-group">
                                <label for="vendorAddress" class="vendor-form-label">Address</label>
                                <textarea class="vendor-form-control vendor-textarea" id="vendorAddress" name="address" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <!-- Financial Information -->
                        <div class="col-12 mt-4">
                            <div class="vendor-section-header vendor-financial-header">
                                <div class="vendor-section-title">
                                    <i class="bi bi-credit-card me-2"></i>
                                    <span>Financial Information</span>
                                </div>
                                <button type="button" class="vendor-section-toggle" id="toggleFinancialSection" title="Show Financial Information">
                                    <i class="bi bi-chevron-up rotated" id="financialToggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="vendor-financial-content collapsed" id="financialContent">
                            <div class="row g-4">
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorGST" class="vendor-form-label">GST Number</label>
                                <input type="text" class="vendor-form-control" id="vendorGST" name="gst_number" placeholder="07AAACH7409R1ZX">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorPAN" class="vendor-form-label">PAN Number</label>
                                <input type="text" class="vendor-form-control" id="vendorPAN" name="pan_number" placeholder="AAACH7409R">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorBankAccount" class="vendor-form-label">Bank Account</label>
                                <input type="text" class="vendor-form-control" id="vendorBankAccount" name="bank_account_number">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorBankName" class="vendor-form-label">Bank Name</label>
                                <input type="text" class="vendor-form-control" id="vendorBankName" name="bank_name">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorIFSC" class="vendor-form-label">IFSC Code</label>
                                <input type="text" class="vendor-form-control" id="vendorIFSC" name="ifsc_code" placeholder="SBIN0000123">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="vendor-form-group">
                                <label for="vendorPaymentTerms" class="vendor-form-label">Payment Terms</label>
                                <select class="vendor-form-control" id="vendorPaymentTerms" name="payment_terms">
                                    <option value="Immediate">Immediate</option>
                                    <option value="Net 7">Net 7 days</option>
                                    <option value="Net 15">Net 15 days</option>
                                    <option value="Net 30" selected>Net 30 days</option>
                                    <option value="Net 45">Net 45 days</option>
                                    <option value="Net 60">Net 60 days</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="vendor-form-group">
                                <label for="vendorNotes" class="vendor-form-label">Notes</label>
                                <textarea class="vendor-form-control vendor-textarea" id="vendorNotes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer vendor-modal-footer">
                <button type="button" class="vendor-btn vendor-btn-cancel" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="vendor-btn vendor-btn-save" id="saveVendorBtn">
                    Add Vendor
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Minimalistic Vendor Modal Styles */
.vendor-modal {
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    overflow: hidden;
}

.vendor-modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    padding: 1.5rem 2rem;
    border-radius: 12px 12px 0 0;
}

.vendor-modal-title {
    font-size: 1.1rem;
    font-weight: 500;
    color: #495057;
    margin: 0;
}

.vendor-close-btn {
    background: #ffffff !important;
    border: 2px solid #dc3545 !important;
    color: #dc3545 !important;
    font-size: 1.8rem !important;
    font-weight: bold !important;
    opacity: 1 !important;
    transition: all 0.3s ease !important;
    border-radius: 6px !important;
    padding: 0.4rem 0.6rem !important;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2) !important;
    min-width: 36px !important;
    min-height: 36px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: relative !important;
}

.vendor-close-btn::before {
    display: none !important;
}

.vendor-close-btn span {
    font-size: 1.8rem !important;
    line-height: 1 !important;
    color: #dc3545 !important;
}

.vendor-close-btn:hover {
    background: #dc3545 !important;
    color: #ffffff !important;
    border-color: #c82333 !important;
    transform: scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3) !important;
}

.vendor-close-btn:hover span {
    color: #ffffff !important;
}

.vendor-close-btn:focus {
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.25) !important;
    background: #dc3545 !important;
    color: #ffffff !important;
}

.vendor-close-btn:focus span {
    color: #ffffff !important;
}

.vendor-modal-body {
    padding: 2rem;
    background: #fdfdfd;
}

.vendor-section-header {
    font-size: 0.9rem;
    font-weight: 500;
    color: #6c757d;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.vendor-section-header i {
    color: #495057;
    font-size: 0.85rem;
}

.vendor-form-group {
    margin-bottom: 0;
}

.vendor-form-label {
    font-size: 0.85rem;
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.5rem;
    display: block;
}

.vendor-required {
    color: #dc3545;
    font-weight: 400;
}

.vendor-form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    color: #495057;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.vendor-form-control:focus {
    outline: none;
    border-color: #a8d5f2;
    box-shadow: 0 0 0 3px rgba(168, 213, 242, 0.15), 0 2px 8px rgba(0, 0, 0, 0.08);
    background: linear-gradient(145deg, #ffffff 0%, #f0f8ff 100%);
    transform: translateY(-1px);
}

.vendor-form-control:hover:not(:focus) {
    border-color: #d6e9f7;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    background: linear-gradient(145deg, #ffffff 0%, #f5f9fc 100%);
}

.vendor-form-control::placeholder {
    color: #adb5bd;
    font-size: 0.85rem;
    font-style: italic;
}

/* Special styling for select dropdowns */
select.vendor-form-control {
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 1rem;
    appearance: none;
    cursor: pointer;
}

select.vendor-form-control:focus {
    background: linear-gradient(145deg, #ffffff 0%, #f0f8ff 100%);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%233b82f6' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 1rem;
}

.vendor-textarea {
    resize: vertical;
    min-height: 80px;
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    transition: all 0.3s ease;
}

.vendor-textarea:focus {
    background: linear-gradient(145deg, #ffffff 0%, #f0f8ff 100%);
    border-color: #a8d5f2;
    box-shadow: 0 0 0 3px rgba(168, 213, 242, 0.15), 0 2px 8px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.vendor-modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 1.25rem 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.vendor-btn {
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 100px;
}

.vendor-btn-cancel {
    background: #ffffff;
    color: #6c757d;
    border: 1px solid #dee2e6;
}

.vendor-btn-cancel:hover {
    background: #f8f9fa;
    color: #495057;
    border-color: #adb5bd;
}

.vendor-btn-save {
    background: linear-gradient(135deg, #495057 0%, #343a40 100%);
    color: #ffffff;
    border: 1px solid transparent;
}

.vendor-btn-save:hover {
    background: linear-gradient(135deg, #343a40 0%, #212529 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(52, 58, 64, 0.3);
}

.vendor-btn-save:disabled {
    background: #adb5bd;
    color: #ffffff;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.vendor-custom-type {
    animation: slideDown 0.3s ease;
}

.vendor-back-to-list {
    background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    color: #495057;
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
}

.vendor-back-to-list:hover {
    background: linear-gradient(145deg, #e9ecef 0%, #dee2e6 100%);
    color: #343a40;
    transform: translateY(-1px);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Financial Section Toggle Styles */
.vendor-financial-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    padding: 0.75rem 1rem;
    margin-bottom: 0;
    border-radius: 8px;
    transition: background-color 0.2s ease;
}

.vendor-financial-header:hover {
    background-color: #f8f9fa;
}

.vendor-section-title {
    display: flex;
    align-items: center;
}

.vendor-section-toggle {
    background: transparent;
    border: none;
    color: #6c757d;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.vendor-section-toggle:hover {
    background-color: #e9ecef;
    color: #495057;
    transform: scale(1.1);
}

.vendor-section-toggle i {
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
    font-size: 0.9rem;
}

.vendor-section-toggle i.rotated {
    transform: rotate(180deg);
}

.vendor-financial-content {
    transition: all 0.3s ease;
    overflow: hidden;
}

.vendor-financial-content.collapsed {
    display: none;
}

.vendor-financial-content .row {
    margin-left: 0;
    margin-right: 0;
}

.vendor-financial-content .row > * {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .vendor-modal-header,
    .vendor-modal-body,
    .vendor-modal-footer {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }
    
    .vendor-modal-body {
        padding-top: 1.5rem;
        padding-bottom: 1.5rem;
    }
}
</style>