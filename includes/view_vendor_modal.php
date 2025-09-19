<!-- View Vendor Details Modal -->
<div class="modal fade" id="viewVendorModal" tabindex="-1" aria-labelledby="viewVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content view-vendor-modal">
            <div class="modal-header view-vendor-modal-header">
                <h5 class="modal-title view-vendor-modal-title" id="viewVendorModalLabel">
                    <i class="fas fa-eye me-2"></i>
                    Vendor Details
                </h5>
                <button type="button" class="btn-close view-vendor-close-btn" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body view-vendor-modal-body">
                <!-- Loading Indicator -->
                <div class="text-center py-4" id="vendorDetailsLoader" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading vendor details...</p>
                </div>
                
                <!-- Error Message -->
                <div class="alert alert-danger" id="vendorDetailsError" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="vendorErrorMessage">Failed to load vendor details</span>
                </div>
                
                <!-- Vendor Details Content -->
                <div id="vendorDetailsContent" style="display: none;">
                    <!-- Basic Information Section -->
                    <div class="vendor-details-section">
                        <div class="vendor-details-header">
                            <i class="fas fa-user me-2"></i>
                            <span>Basic Information</span>
                        </div>
                        <div class="vendor-details-grid">
                            <div class="vendor-detail-item">
                                <label>Full Name</label>
                                <span id="viewVendorFullName">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Vendor Type</label>
                                <span id="viewVendorType" class="vendor-type-badge">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Phone Number</label>
                                <span id="viewVendorPhone">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Alternative Number</label>
                                <span id="viewVendorAltPhone">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Email Address</label>
                                <span id="viewVendorEmail">-</span>
                            </div>
                            <!-- Company name field hidden as it doesn't exist in database
                            <div class="vendor-detail-item">
                                <label>Company Name</label>
                                <span id="viewVendorCompany">-</span>
                            </div>
                            -->
                        </div>
                    </div>
                    
                    <!-- Contact & Address Information -->
                    <div class="vendor-details-section">
                        <div class="vendor-details-header">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span>Address Information</span>
                        </div>
                        <div class="vendor-details-grid">
                            <div class="vendor-detail-item full-width">
                                <label>Street Address</label>
                                <span id="viewVendorAddress">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>City</label>
                                <span id="viewVendorCity">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>State</label>
                                <span id="viewVendorState">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>ZIP Code</label>
                                <span id="viewVendorZip">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Country</label>
                                <span id="viewVendorCountry">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Information -->
                    <div class="vendor-details-section">
                        <div class="vendor-details-header">
                            <i class="fas fa-credit-card me-2"></i>
                            <span>Financial Information</span>
                        </div>
                        <div class="vendor-details-grid">
                            <!-- GST and PAN fields hidden as they don't exist in database
                            <div class="vendor-detail-item">
                                <label>GST Number</label>
                                <span id="viewVendorGST">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>PAN Number</label>
                                <span id="viewVendorPAN">-</span>
                            </div>
                            -->
                            <div class="vendor-detail-item">
                                <label>Bank Name</label>
                                <span id="viewVendorBankName">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Account Number</label>
                                <span id="viewVendorAccountNumber" class="masked-data">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Account Type</label>
                                <span id="viewVendorAccountType">-</span>
                            </div>
                            <!-- IFSC and Payment Terms fields hidden as they don't exist in database
                            <div class="vendor-detail-item">
                                <label>IFSC Code</label>
                                <span id="viewVendorIFSC">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Payment Terms</label>
                                <span id="viewVendorPaymentTerms" class="payment-terms-badge">-</span>
                            </div>
                            -->
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="vendor-details-section">
                        <div class="vendor-details-header">
                            <i class="fas fa-info-circle me-2"></i>
                            <span>Additional Information</span>
                        </div>
                        <div class="vendor-details-grid">
                            <div class="vendor-detail-item full-width">
                                <label>Notes</label>
                                <span id="viewVendorNotes" class="notes-content">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Account Age</label>
                                <span id="viewVendorAccountAge" class="account-age-badge">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Created Date</label>
                                <span id="viewVendorCreatedAt">-</span>
                            </div>
                            <div class="vendor-detail-item">
                                <label>Last Updated</label>
                                <span id="viewVendorUpdatedAt">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer view-vendor-modal-footer">
                <button type="button" class="vendor-btn vendor-btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Close
                </button>
                <button type="button" class="vendor-btn vendor-btn-primary" id="editVendorFromView">
                    <i class="fas fa-edit me-2"></i>
                    Edit Vendor
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* View Vendor Modal Styles */
.view-vendor-modal {
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    overflow: hidden;
}

.view-vendor-modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    padding: 1.5rem 2rem;
    border-radius: 12px 12px 0 0;
}

.view-vendor-modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #495057;
    margin: 0;
    display: flex;
    align-items: center;
}

.view-vendor-close-btn {
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

.view-vendor-close-btn:hover {
    background: #6c757d !important;
    color: #ffffff !important;
    border-color: #5a6268 !important;
    transform: scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3) !important;
}

.view-vendor-modal-body {
    padding: 2rem;
    background: #fdfdfd;
    max-height: 70vh;
    overflow-y: auto;
}

.vendor-details-section {
    margin-bottom: 2rem;
    background: #ffffff;
    border: 1px solid #f1f3f4;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.vendor-details-section:last-child {
    margin-bottom: 0;
}

.vendor-details-header {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f8f9fa;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.vendor-details-header i {
    color: #6c757d;
    font-size: 0.9rem;
}

.vendor-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
}

.vendor-detail-item {
    display: flex;
    flex-direction: column;
}

.vendor-detail-item.full-width {
    grid-column: 1 / -1;
}

.vendor-detail-item label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vendor-detail-item span {
    font-size: 0.95rem;
    color: #495057;
    padding: 0.75rem 1rem;
    background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    border: 1px solid #e9ecef;
    min-height: 2.5rem;
    display: flex;
    align-items: center;
    word-break: break-word;
}

.vendor-type-badge {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
    color: #1976d2 !important;
    font-weight: 500 !important;
    border: 1px solid #90caf9 !important;
}

.payment-terms-badge {
    background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%) !important;
    color: #7b1fa2 !important;
    font-weight: 500 !important;
    border: 1px solid #ce93d8 !important;
}

.account-age-badge {
    background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%) !important;
    color: #388e3c !important;
    font-weight: 500 !important;
    border: 1px solid #a5d6a7 !important;
}

.masked-data {
    font-family: 'Courier New', monospace !important;
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%) !important;
    color: #f57c00 !important;
    font-weight: 500 !important;
    border: 1px solid #ffcc02 !important;
}

.notes-content {
    white-space: pre-wrap;
    max-height: 100px;
    overflow-y: auto;
    line-height: 1.5;
}

.view-vendor-modal-footer {
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
    min-width: 120px;
    text-decoration: none;
}

.vendor-btn-secondary {
    background: #ffffff;
    color: #6c757d;
    border: 1px solid #dee2e6;
}

.vendor-btn-secondary:hover {
    background: #f8f9fa;
    color: #495057;
    border-color: #adb5bd;
    transform: translateY(-1px);
}

.vendor-btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: #ffffff;
    border: 1px solid transparent;
}

.vendor-btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .view-vendor-modal-body {
        padding: 1.5rem;
        max-height: 60vh;
    }
    
    .vendor-details-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .vendor-details-section {
        padding: 1rem;
    }
    
    .view-vendor-modal-footer {
        padding: 1rem 1.5rem;
        flex-direction: column;
    }
    
    .vendor-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Scrollbar Styling */
.view-vendor-modal-body::-webkit-scrollbar {
    width: 6px;
}

.view-vendor-modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.view-vendor-modal-body::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.view-vendor-modal-body::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.notes-content::-webkit-scrollbar {
    width: 4px;
}

.notes-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}

.notes-content::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 2px;
}
</style>