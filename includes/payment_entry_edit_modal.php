<!-- Payment Entry Edit Modal -->
<div class="modal fade" id="paymentEntryEditModal" tabindex="-1" aria-labelledby="paymentEntryEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5 pe-edit-modal-title" id="paymentEntryEditModalLabel">
                    <i class="fas fa-edit me-2 text-primary"></i>
                    Edit Payment Entry
                </h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Loading State -->
                <div id="peEditLoadingState" class="pe-edit-loading-container">
                    <div class="pe-edit-loading-content">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading payment entry details...</p>
                    </div>
                </div>

                <!-- Error State -->
                <div id="peEditErrorState" class="pe-edit-error-container" style="display: none;">
                    <div class="pe-edit-error-content">
                        <div class="pe-edit-error-icon">
                            <i class="fas fa-exclamation-triangle text-danger"></i>
                        </div>
                        <h6 class="pe-edit-error-title">Error Loading Payment Entry</h6>
                        <p class="pe-edit-error-message" id="peEditErrorMessage">Unable to load payment entry details. Please try again.</p>
                        <button type="button" class="btn btn-outline-primary" onclick="retryLoadPaymentEntryForEdit()">
                            <i class="fas fa-redo me-2"></i>
                            Retry
                        </button>
                    </div>
                </div>

                <!-- Edit Form -->
                <form id="paymentEntryEditForm" style="display: none;">
                    <input type="hidden" id="peEditPaymentId" name="payment_id">
                    
                    <!-- Payment Entry Information -->
                    <div class="pe-edit-section">
                        <div class="pe-edit-section-header">
                            <h6 class="pe-edit-section-title">
                                <i class="fas fa-info-circle me-2"></i>
                                Payment Entry Information
                            </h6>
                        </div>
                        <div class="pe-edit-section-content">
                            <div class="row">
                                <!-- Project Selection -->
                                <div class="col-md-6 mb-3">
                                    <label for="peEditProjectId" class="form-label pe-edit-required">Project</label>
                                    <select class="form-select pe-edit-select" id="peEditProjectId" name="project_id" required>
                                        <option value="">Select a project...</option>
                                        <!-- Options will be populated via JavaScript -->
                                    </select>
                                    <div class="pe-edit-field-help">
                                        <small class="text-muted">Select the project this payment is associated with</small>
                                    </div>
                                </div>

                                <!-- Payment Date -->
                                <div class="col-md-6 mb-3">
                                    <label for="peEditPaymentDate" class="form-label pe-edit-required">Payment Date</label>
                                    <input type="date" class="form-control pe-edit-input" id="peEditPaymentDate" name="payment_date" required>
                                    <div class="pe-edit-field-help">
                                        <small class="text-muted">Date when the payment was made</small>
                                    </div>
                                </div>

                                <!-- Payment Amount -->
                                <div class="col-md-6 mb-3">
                                    <label for="peEditPaymentAmount" class="form-label pe-edit-required">Payment Amount (₹)</label>
                                    <input type="number" class="form-control pe-edit-input" id="peEditPaymentAmount" name="payment_amount" min="0" step="0.01" required>
                                    <div class="pe-edit-field-help">
                                        <small class="text-muted">Total payment amount in Indian Rupees</small>
                                    </div>
                                </div>

                                <!-- Payment Mode -->
                                <div class="col-md-6 mb-3">
                                    <label for="peEditPaymentMode" class="form-label pe-edit-required">Payment Mode</label>
                                    <select class="form-select pe-edit-select" id="peEditPaymentMode" name="payment_mode" required>
                                        <option value="">Select payment mode...</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="upi">UPI</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="debit_card">Debit Card</option>
                                        <option value="split_payment">Split Payment</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <div class="pe-edit-field-help">
                                        <small class="text-muted">Method used for payment</small>
                                    </div>
                                </div>

                                <!-- Payment Done Via -->
                                <div class="col-md-6 mb-3">
                                    <label for="peEditPaymentVia" class="form-label pe-edit-required">Payment Done Via</label>
                                    <select class="form-select pe-edit-select" id="peEditPaymentVia" name="payment_done_via" required>
                                        <option value="">Select user...</option>
                                        <!-- Options will be populated via JavaScript -->
                                    </select>
                                    <div class="pe-edit-field-help">
                                        <small class="text-muted">User who made the payment</small>
                                    </div>
                                </div>

                                <!-- Project Type -->
                                <div class="col-md-6 mb-3">
                                    <label for="peEditProjectType" class="form-label">Project Type</label>
                                    <select class="form-select pe-edit-select" id="peEditProjectType" name="project_type">
                                        <option value="">Select project type...</option>
                                        <option value="architecture">Architecture</option>
                                        <option value="interior">Interior</option>
                                        <option value="construction">Construction</option>
                                    </select>
                                    <div class="pe-edit-field-help">
                                        <small class="text-muted">This field will auto-populate based on selected project</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Proof -->
                    <div class="pe-edit-section">
                        <div class="pe-edit-section-header">
                            <h6 class="pe-edit-section-title">
                                <i class="fas fa-image me-2"></i>
                                Payment Proof
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary pe-toggle-btn" onclick="toggleSection('peEditPaymentProofContent', this)">
                                <i class="fas fa-chevron-down"></i>
                                <span class="ms-1">Show</span>
                            </button>
                        </div>
                        <div class="pe-edit-section-content" id="peEditPaymentProofContent" style="display: none;">
                            <!-- Current Payment Proof -->
                            <div id="peEditCurrentProofSection" class="pe-edit-current-proof mb-3" style="display: none;">
                                <label class="form-label">Current Payment Proof</label>
                                <div class="pe-edit-current-proof-display">
                                    <div id="peEditCurrentProofPreview" class="pe-edit-proof-preview">
                                        <!-- Current proof preview will be populated here -->
                                    </div>
                                    <div class="pe-edit-current-proof-actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="peEditViewCurrentProof">
                                            <i class="fas fa-eye me-1"></i>
                                            View Current
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="peEditRemoveCurrentProof">
                                            <i class="fas fa-trash me-1"></i>
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- New Payment Proof Upload -->
                            <div class="mb-3">
                                <label for="peEditPaymentProof" class="form-label">
                                    Upload New Payment Proof 
                                    <span class="pe-edit-optional">(Optional)</span>
                                </label>
                                <input type="file" class="form-control pe-edit-file-input" id="peEditPaymentProof" name="payment_proof" accept="image/*,.pdf">
                                <div class="pe-edit-field-help">
                                    <small class="text-muted">
                                        Supported formats: JPG, PNG, PDF. Maximum file size: 5MB.
                                        <span id="peEditProofReplaceNote" style="display: none;">This will replace the current payment proof.</span>
                                    </small>
                                </div>
                                <!-- File preview -->
                                <div id="peEditNewProofPreview" class="pe-edit-file-preview mt-2" style="display: none;">
                                    <!-- New file preview will be shown here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Split Payment Section -->
                    <div class="pe-edit-section" id="peEditSplitPaymentSection" style="display: none;">
                        <div class="pe-edit-section-header">
                            <h6 class="pe-edit-section-title">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                Split Payment Details
                            </h6>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="peEditAddSplitBtn">
                                    <i class="fas fa-plus me-1"></i>
                                    Add Split
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary pe-toggle-btn" onclick="toggleSection('peEditSplitPaymentContent', this)">
                                    <i class="fas fa-chevron-down"></i>
                                    <span class="ms-1">Show</span>
                                </button>
                            </div>
                        </div>
                        <div class="pe-edit-section-content" id="peEditSplitPaymentContent" style="display: none;">
                            <div class="pe-edit-field-help mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Split payments allow you to break down the total payment into multiple payment modes.
                                </small>
                            </div>
                            
                            <!-- Current Split Payments -->
                            <div id="peEditCurrentSplitsContainer" class="mb-3" style="display: none;">
                                <h6 class="fw-semibold mb-2">Current Split Payments</h6>
                                <div id="peEditCurrentSplitsList">
                                    <!-- Existing splits will be loaded here -->
                                </div>
                            </div>
                            
                            <!-- New Split Payments -->
                            <div id="peEditNewSplitsContainer">
                                <h6 class="fw-semibold mb-2">New Split Payments</h6>
                                <div id="peEditNewSplitsList">
                                    <!-- New splits will be added here -->
                                </div>
                                <div class="pe-edit-split-summary mt-3" id="peEditSplitSummary" style="display: none;">
                                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-calculator me-2"></i>
                                            <strong>Total Split Amount: <span id="peEditTotalSplitAmount">₹0</span></strong>
                                        </span>
                                        <span class="text-muted">Main Payment: <span id="peEditMainPaymentAmount">₹0</span></span>
                                    </div>
                                    <div id="peEditSplitValidation">
                                        <!-- Validation messages will appear here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recipients Information (Read-only display for context) -->
                    <div class="pe-edit-section">
                        <div class="pe-edit-section-header">
                            <h6 class="pe-edit-section-title">
                                <i class="fas fa-users me-2"></i>
                                Payment Recipients
                                <span class="pe-edit-recipients-count badge bg-secondary ms-2" id="peEditRecipientsCount">0</span>
                            </h6>
                            <small class="text-muted">Recipients information is managed separately</small>
                        </div>
                        <div class="pe-edit-section-content">
                            <div id="peEditRecipientsDisplay" class="pe-edit-recipients-readonly">
                                <!-- Recipients will be displayed here for context -->
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Success State -->
                <div id="peEditSuccessState" class="pe-edit-success-container" style="display: none;">
                    <div class="pe-edit-success-content">
                        <div class="pe-edit-success-icon">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                        <h6 class="pe-edit-success-title">Payment Entry Updated Successfully!</h6>
                        <p class="pe-edit-success-message" id="peEditSuccessMessage">The payment entry has been updated with your changes.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="pe-edit-modal-footer-content">
                    <!-- Loading Footer -->
                    <div id="peEditFooterLoading" class="pe-edit-footer-section">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>

                    <!-- Error Footer -->
                    <div id="peEditFooterError" class="pe-edit-footer-section" style="display: none;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="retryLoadPaymentEntryForEdit()">
                            <i class="fas fa-redo me-2"></i>
                            Retry
                        </button>
                    </div>

                    <!-- Form Footer -->
                    <div id="peEditFooterForm" class="pe-edit-footer-section" style="display: none;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="peEditSaveChanges">
                            <i class="fas fa-save me-2"></i>
                            Save Changes
                        </button>
                    </div>

                    <!-- Success Footer -->
                    <div id="peEditFooterSuccess" class="pe-edit-footer-section" style="display: none;">
                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                            <i class="fas fa-check me-2"></i>
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles for Payment Entry Edit Modal -->
<style>
.pe-edit-loading-container,
.pe-edit-error-container,
.pe-edit-success-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    padding: 2rem;
}

.pe-edit-loading-content,
.pe-edit-error-content,
.pe-edit-success-content {
    text-align: center;
    max-width: 400px;
}

.pe-edit-error-icon,
.pe-edit-success-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.pe-edit-error-title,
.pe-edit-success-title {
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.pe-edit-error-message,
.pe-edit-success-message {
    color: #6b7280;
    margin-bottom: 1.5rem;
}

.pe-edit-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.pe-edit-section-header {
    background: #ffffff;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pe-edit-section-title {
    font-weight: 600;
    color: #374151;
    margin: 0;
    display: flex;
    align-items: center;
}

.pe-edit-section-title i {
    color: #6366f1;
}

.pe-edit-section-content {
    padding: 1.25rem;
}

.pe-edit-required::after {
    content: " *";
    color: #dc2626;
}

.pe-edit-optional {
    color: #6b7280;
    font-weight: 400;
    font-size: 0.875rem;
}

.pe-edit-input,
.pe-edit-select {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0.75rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.pe-edit-input:focus,
.pe-edit-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

.pe-edit-field-help {
    margin-top: 0.25rem;
}

.pe-edit-field-help small {
    color: #6b7280;
}

.pe-edit-current-proof {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 1rem;
}

.pe-edit-current-proof-display {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pe-edit-proof-preview {
    flex: 1;
}

.pe-edit-proof-preview img {
    max-width: 100px;
    max-height: 80px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.pe-edit-current-proof-actions {
    display: flex;
    gap: 0.5rem;
}

.pe-edit-file-input {
    border: 2px dashed #d1d5db;
    border-radius: 6px;
    padding: 0.75rem;
    transition: border-color 0.2s ease;
}

.pe-edit-file-input:hover {
    border-color: #6366f1;
}

.pe-edit-file-preview {
    background: #f9fafb;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.75rem;
}

.pe-edit-recipients-readonly {
    max-height: 200px;
    overflow-y: auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 1rem;
}

.pe-edit-recipient-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.pe-edit-recipient-item:last-child {
    border-bottom: none;
}

.pe-edit-recipient-info {
    flex: 1;
}

.pe-edit-recipient-name {
    font-weight: 500;
    color: #374151;
}

.pe-edit-recipient-details {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.pe-edit-recipient-amount {
    font-weight: 600;
    color: #059669;
}

.pe-edit-recipients-count {
    font-size: 0.75rem;
}

.pe-edit-modal-footer-content {
    width: 100%;
}

.pe-edit-footer-section {
    display: flex;
    justify-content: end;
    gap: 0.75rem;
}

.pe-edit-modal-title {
    display: flex;
    align-items: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .pe-edit-current-proof-display {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .pe-edit-current-proof-actions {
        align-self: stretch;
        justify-content: space-between;
    }
    
    .pe-edit-footer-section {
        flex-direction: column;
    }
    
    .pe-edit-footer-section button {
        width: 100%;
    }
}

/* File upload enhancement */
.pe-edit-file-input[type="file"]::-webkit-file-upload-button {
    background: #6366f1;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    margin-right: 1rem;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.pe-edit-file-input[type="file"]::-webkit-file-upload-button:hover {
    background: #4f46e5;
}

/* Loading states */
.pe-edit-loading-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Form validation feedback */
.pe-edit-input.is-invalid,
.pe-edit-select.is-invalid {
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.pe-edit-input.is-valid,
.pe-edit-select.is-valid {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}

.pe-edit-feedback {
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.pe-edit-feedback.invalid {
    color: #dc2626;
}

.pe-edit-feedback.valid {
    color: #059669;
}

/* Enhanced recipient display */
.pe-edit-recipient-item {
    transition: background-color 0.2s ease;
}

.pe-edit-recipient-item:hover {
    background-color: #f9fafb;
}

/* Modal backdrop enhancement */
.modal-backdrop {
    backdrop-filter: blur(2px);
}

/* Split Payment Styles - Enhanced */
.pe-split-payment-item {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    position: relative;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    animation: slideInUp 0.3s ease-out;
}

.pe-split-payment-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    border-color: #6366f1;
}

.pe-split-payment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #f1f5f9;
}

.pe-split-payment-title {
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

.pe-split-payment-title::before {
    content: "💳";
    margin-right: 0.5rem;
    font-size: 1.2rem;
}

.pe-split-remove-btn {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}

.pe-split-remove-btn:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.4);
}

.pe-edit-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.pe-split-amount,
.pe-split-mode,
.pe-split-proof {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #ffffff;
}

.pe-split-amount:focus,
.pe-split-mode:focus,
.pe-split-proof:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    background: #fefeff;
}

.pe-split-amount {
    font-weight: 600;
    color: #059669;
}

.pe-split-existing-proof {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pe-split-existing-proof small {
    color: #0369a1;
    font-weight: 500;
}

/* Enhanced Section Header */
.pe-edit-section-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 1.25rem 1.5rem;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pe-edit-section-title {
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    font-size: 1.1rem;
}

.pe-edit-section-title i {
    color: #6366f1;
    margin-right: 0.5rem;
    font-size: 1.2rem;
}

/* Toggle Button Styling */
.pe-toggle-btn {
    border-radius: 6px !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
    font-size: 0.875rem !important;
    padding: 0.5rem 0.75rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.25rem !important;
}

.pe-toggle-btn:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
}

.pe-toggle-btn i {
    transition: transform 0.3s ease !important;
    font-size: 0.75rem !important;
}

.pe-toggle-btn.btn-outline-primary {
    border-color: #6366f1 !important;
    color: #6366f1 !important;
    background: rgba(99, 102, 241, 0.05) !important;
}

.pe-toggle-btn.btn-outline-primary:hover {
    background: #6366f1 !important;
    color: white !important;
    border-color: #6366f1 !important;
}

.pe-toggle-btn.btn-outline-secondary {
    border-color: #6b7280 !important;
    color: #6b7280 !important;
    background: rgba(107, 114, 128, 0.05) !important;
}

.pe-toggle-btn.btn-outline-secondary:hover {
    background: #6b7280 !important;
    color: white !important;
    border-color: #6b7280 !important;
}

/* Section Content Animation */
.pe-edit-section-content {
    transition: all 0.3s ease;
    opacity: 1;
    transform: translateY(0);
}

.pe-edit-section-content[style*="display: none"] {
    opacity: 0;
    transform: translateY(-10px);
}

/* Enhanced Split Payment Header */
.pe-edit-section-header .d-flex {
    align-items: center;
}

.pe-edit-section-header .gap-2 {
    gap: 0.5rem !important;
}

#peEditAddSplitBtn {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.3);
}

#peEditAddSplitBtn:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(99, 102, 241, 0.4);
}

#peEditAddSplitBtn i {
    margin-right: 0.5rem;
}

/* Enhanced Alert Styling */
.alert {
    border-radius: 8px;
    border: none;
    padding: 1rem 1.25rem;
    margin-top: 1rem;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.alert-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border-left: 4px solid #f59e0b;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border-left: 4px solid #059669;
}

.alert-info {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: #1e40af;
    border-left: 4px solid #3b82f6;
}

/* Summary Enhancement */
.pe-edit-split-summary {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.pe-edit-split-summary .alert-info {
    margin-top: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
}

.pe-edit-split-summary strong {
    font-size: 1.1rem;
    color: #1e40af;
}

.pe-edit-split-summary .text-muted {
    color: #64748b !important;
    font-weight: 500;
}

/* Animations */
@keyframes slideInUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.loading {
    animation: pulse 1.5s ease-in-out infinite;
}

/* File Upload Enhancement */
.pe-split-proof {
    position: relative;
    overflow: hidden;
}

.pe-split-proof::after {
    content: "📎 Choose Proof File";
    position: absolute;
    top: 50%;
    left: 1rem;
    transform: translateY(-50%);
    color: #6b7280;
    pointer-events: none;
    z-index: 1;
    transition: opacity 0.3s ease;
}

.pe-split-proof:focus::after,
.pe-split-proof[value]:not([value=""])::after {
    opacity: 0;
}

/* Currency Symbol Styling */
.currency-symbol {
    color: #059669;
    font-weight: 700;
    margin-right: 0.25rem;
}

/* Legacy class compatibility */
.pe-edit-split-item {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
}

.pe-edit-split-item.current-split {
    border-left: 4px solid #059669;
    background: #f0fdf4;
}

.pe-edit-split-item.new-split {
    border-left: 4px solid #2563eb;
}

/* Responsive Design */
@media (max-width: 768px) {
    .pe-split-payment-item {
        padding: 1rem;
    }
    
    .pe-split-payment-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .pe-split-remove-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
    }
    
    .pe-edit-section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .pe-edit-section-header .d-flex {
        width: 100%;
        justify-content: space-between;
    }
    
    #peEditAddSplitBtn {
        flex: 1;
    }
    
    .pe-toggle-btn {
        min-width: 80px;
    }
    
    .pe-edit-split-summary .alert-info {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
}
</style>