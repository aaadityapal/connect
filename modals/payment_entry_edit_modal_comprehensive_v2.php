<?php
// Edit Payment Entry Modal - Comprehensive V2
// This modal allows users to edit existing payment entries
// Fetches all data from backend and displays in editable form
// Unique name: payment_entry_edit_modal_comprehensive_v2.php
?>

<div id="paymentEntryEditModalOverlay" class="payment-edit-overlay">
    <div class="payment-edit-modal-container">
        <!-- Modal Header -->
        <div class="payment-edit-modal-header">
            <h2 class="payment-edit-modal-title">Edit Payment Entry</h2>
            <button type="button" class="payment-edit-close-btn" id="closePaymentEditModal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="payment-edit-modal-body">
            <form id="paymentEditForm" class="payment-edit-form-wrapper">
                <!-- Loading Spinner -->
                <div id="paymentEditLoadingSpinner" class="payment-edit-loading-spinner" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading payment entry details...</p>
                </div>

                <!-- Main Form Content -->
                <div id="paymentEditContent" style="display: none;">
                    <!-- Section 1: Basic Payment Information -->
                    <div class="payment-edit-form-section">
                        <h3 class="payment-edit-section-title">
                            <i class="fas fa-info-circle"></i> Basic Payment Information
                        </h3>

                        <div class="payment-edit-form-grid">
                            <!-- Project Type -->
                            <div class="payment-edit-form-group">
                                <label for="editPaymentProjectType" class="payment-edit-form-label">
                                    <i class="fas fa-folder-open"></i> Project Type <span class="payment-edit-required">*</span>
                                </label>
                                <input type="text" id="editPaymentProjectType" name="projectType" class="payment-edit-text-input" readonly style="background-color: #f0f4f8;">
                                <span class="payment-edit-error-message" id="editPaymentProjectTypeError"></span>
                            </div>

                            <!-- Project Name -->
                            <div class="payment-edit-form-group">
                                <label for="editPaymentProjectName" class="payment-edit-form-label">
                                    <i class="fas fa-tasks"></i> Project Name <span class="payment-edit-required">*</span>
                                </label>
                                <select id="editPaymentProjectName" name="projectName" class="payment-edit-select-field" required>
                                    <option value="">Select Project Name</option>
                                </select>
                                <span class="payment-edit-error-message" id="editPaymentProjectNameError"></span>
                            </div>

                            <!-- Payment Date -->
                            <div class="payment-edit-form-group">
                                <label for="editPaymentDate" class="payment-edit-form-label">
                                    <i class="fas fa-calendar-alt"></i> Date <span class="payment-edit-required">*</span>
                                </label>
                                <input type="date" id="editPaymentDate" name="paymentDate" class="payment-edit-text-input" required>
                                <span class="payment-edit-error-message" id="editPaymentDateError"></span>
                            </div>

                            <!-- Main Amount -->
                            <div class="payment-edit-form-group">
                                <label for="editPaymentAmount" class="payment-edit-form-label">
                                    <i class="fas fa-rupee-sign"></i> Amount <span class="payment-edit-required">*</span>
                                </label>
                                <input type="number" id="editPaymentAmount" name="amount" class="payment-edit-text-input" placeholder="Enter amount" step="0.01" min="0" required>
                                <span class="payment-edit-error-message" id="editPaymentAmountError"></span>
                            </div>

                            <!-- Authorized User -->
                            <div class="payment-edit-form-group">
                                <label for="editPaymentAuthorizedUser" class="payment-edit-form-label">
                                    <i class="fas fa-user-check"></i> Payment Done By <span class="payment-edit-required">*</span>
                                </label>
                                <select id="editPaymentAuthorizedUser" name="authorizedUserId" class="payment-edit-select-field" required>
                                    <option value="">Select Authorized User</option>
                                </select>
                                <span class="payment-edit-error-message" id="editPaymentAuthorizedUserError"></span>
                            </div>

                            <!-- Payment Mode -->
                            <div class="payment-edit-form-group">
                                <label for="editPaymentMode" class="payment-edit-form-label">
                                    <i class="fas fa-credit-card"></i> Payment Mode <span class="payment-edit-required">*</span>
                                </label>
                                <select id="editPaymentMode" name="paymentMode" class="payment-edit-select-field" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="split_payment">Split Payment</option>
                                    <option value="multiple_acceptance">Multiple Acceptance</option>
                                    <option value="cash">Cash</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="online">Online Payment</option>
                                    <option value="upi">UPI</option>
                                </select>
                                <span class="payment-edit-error-message" id="editPaymentModeError"></span>
                            </div>
                        </div>

                        <!-- Payment Proof Attachment -->
                        <div id="editPaymentProofSection" style="margin-top: 20px; padding: 15px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-paperclip" style="color: #d97706; font-size: 1.1em;"></i>
                                    <div>
                                        <strong style="color: #92400e; display: block;">Payment Proof</strong>
                                        <small id="editPaymentProofInfo" style="color: #b45309;">No attachment</small>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" id="editPaymentProofViewBtn" class="payment-edit-attachment-btn-view" onclick="viewPaymentProof()" title="View proof" style="display: none;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" id="editPaymentProofUploadBtn" class="payment-edit-attachment-btn-view" onclick="document.getElementById('editPaymentProofUpload').click()" title="Upload/Replace proof">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="file" id="editPaymentProofUpload" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.mp4,.mov,.avi" style="display: none;">
                        </div>
                    </div>

                    <!-- Section 3: Multiple Acceptance Methods -->
                    <div id="editMultipleAcceptanceSection" class="payment-edit-form-section" style="display: none;">
                        <h3 class="payment-edit-section-title">
                            <i class="fas fa-credit-card"></i> Multiple Acceptance Methods
                        </h3>

                        <!-- Methods Container -->
                        <div id="editAcceptanceMethodsContainer" class="payment-edit-acceptance-methods">
                            <!-- Payment methods will be loaded here -->
                        </div>

                        <button type="button" class="payment-edit-btn-add-method" id="editAddAcceptanceMethodBtn" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Add Payment Method
                        </button>

                        <!-- Total Acceptance Summary -->
                        <div style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                <div style="padding: 12px; background: white; border-radius: 6px; text-align: center; border: 1px solid #e2e8f0;">
                                    <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Total Amount</div>
                                    <div style="font-size: 1.2em; font-weight: 800; color: #2d3748; margin-top: 5px;">₹ <span id="editAcceptanceTotalAmount">0.00</span></div>
                                </div>
                                <div style="padding: 12px; background: white; border-radius: 6px; text-align: center; border: 1px solid #e2e8f0;">
                                    <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Amount Received</div>
                                    <div style="font-size: 1.2em; font-weight: 800; color: #22863a; margin-top: 5px;">₹ <span id="editAcceptanceReceivedAmount">0.00</span></div>
                                </div>
                                <div style="padding: 12px; background: white; border-radius: 6px; text-align: center; border: 1px solid #e2e8f0;">
                                    <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Difference</div>
                                    <div style="font-size: 1.2em; font-weight: 800; color: #e53e3e; margin-top: 5px;">₹ <span id="editAcceptanceDifference">0.00</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Line Items / Additional Entries -->
                    <div id="editLineItemsSection" class="payment-edit-form-section" style="display: none;">
                        <h3 class="payment-edit-section-title">
                            <i class="fas fa-list"></i> Line Items / Additional Entries
                        </h3>

                        <div id="editLineItemsContainer" class="payment-edit-line-items-container">
                            <!-- Line items will be loaded here -->
                        </div>

                        <button type="button" class="payment-edit-btn-add-method" id="editAddLineItemBtn" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Add Line Item
                        </button>

                        <!-- Validation Warning (initially hidden) -->
                        <div id="editLineItemsValidationWarning" style="margin-top: 15px; padding: 12px; background: #fed7d7; border-radius: 6px; border-left: 4px solid #c53030; display: none;">
                            <div style="font-size: 0.85em; color: #742a2a; font-weight: 600;">
                                <i class="fas fa-exclamation-triangle"></i> <span id="editLineItemsWarningText">Line items total exceeds the main payment amount!</span>
                            </div>
                        </div>

                        <!-- Line Items Total -->
                        <div style="margin-top: 15px; padding: 12px; background: #c6f6d5; border-radius: 6px; border-left: 4px solid #22863a;">
                            <div style="font-size: 0.85em; color: #22543d; font-weight: 600;">
                                <i class="fas fa-calculator"></i> Total Line Items Amount: <strong>₹ <span id="editLineItemsTotalAmount">0.00</span></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Status Information (Read-Only) - Moved to Last -->
                    <div class="payment-edit-form-section" style="background-color: #f0f4f8; border-left-color: #3182ce;">
                        <h3 class="payment-edit-section-title">
                            <i class="fas fa-history"></i> Status Information (Read-Only)
                        </h3>

                        <div class="payment-edit-form-grid">
                            <!-- Current Status -->
                            <div class="payment-edit-form-group">
                                <label class="payment-edit-form-label">
                                    <i class="fas fa-badge-check"></i> Current Status
                                </label>
                                <input type="text" id="editPaymentStatus" class="payment-edit-text-input" readonly style="background-color: #e6f2ff; color: #3182ce; font-weight: 600;">
                            </div>

                            <!-- Created At -->
                            <div class="payment-edit-form-group">
                                <label class="payment-edit-form-label">
                                    <i class="fas fa-calendar-check"></i> Created At
                                </label>
                                <input type="text" id="editPaymentCreatedAt" class="payment-edit-text-input" readonly style="background-color: #f0f4f8;">
                            </div>

                            <!-- Updated At -->
                            <div class="payment-edit-form-group">
                                <label class="payment-edit-form-label">
                                    <i class="fas fa-calendar-times"></i> Last Updated At
                                </label>
                                <input type="text" id="editPaymentUpdatedAt" class="payment-edit-text-input" readonly style="background-color: #f0f4f8;">
                            </div>

                            <!-- Created By -->
                            <div class="payment-edit-form-group">
                                <label class="payment-edit-form-label">
                                    <i class="fas fa-user"></i> Created By
                                </label>
                                <input type="text" id="editPaymentCreatedBy" class="payment-edit-text-input" readonly style="background-color: #f0f4f8;">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Form Actions -->
                <div class="payment-edit-form-actions">
                    <button type="button" class="payment-edit-btn payment-edit-btn-cancel" id="cancelPaymentEditBtn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="payment-edit-btn payment-edit-btn-submit" id="submitPaymentEditBtn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Payment Edit Modal Styles */
    
    .payment-edit-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        overflow-y: auto;
        animation: payment-edit-fade-in 0.3s ease;
        padding: 20px;
    }

    .payment-edit-overlay.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @keyframes payment-edit-fade-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .payment-edit-modal-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        width: 100%;
        max-width: 1400px;
        max-height: 90vh;
        overflow-y: auto;
        animation: payment-edit-slide-up 0.3s ease;
    }

    @keyframes payment-edit-slide-up {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .payment-edit-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 25px 30px;
        border-bottom: 2px solid #e2e8f0;
        background: white;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .payment-edit-modal-title {
        font-size: 1.5em;
        font-weight: 700;
        color: #1a365d;
        margin: 0;
    }

    .payment-edit-close-btn {
        background: none;
        border: none;
        color: #718096;
        font-size: 1.5em;
        cursor: pointer;
        padding: 0;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        border-radius: 6px;
    }

    .payment-edit-close-btn:hover {
        background-color: #f0f4f8;
        color: #1a365d;
    }

    .payment-edit-modal-body {
        padding: 30px;
    }

    .payment-edit-loading-spinner {
        text-align: center;
        padding: 60px 20px;
    }

    .payment-edit-loading-spinner i {
        font-size: 3em;
        color: #667eea;
        animation: spin 1s linear infinite;
    }

    .payment-edit-loading-spinner p {
        margin-top: 15px;
        color: #718096;
        font-size: 1em;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .payment-edit-form-wrapper {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .payment-edit-form-section {
        padding: 20px;
        background-color: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
        margin-bottom: 25px;
    }

    .payment-edit-section-title {
        font-size: 1em;
        font-weight: 600;
        color: #2a4365;
        margin: 0 0 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .payment-edit-section-title i {
        color: #667eea;
        font-size: 1.1em;
    }

    .payment-edit-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .payment-edit-form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .payment-edit-form-label {
        font-size: 0.9em;
        font-weight: 600;
        color: #2a4365;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .payment-edit-required {
        color: #e53e3e;
        font-weight: 700;
    }

    .payment-edit-form-label i {
        color: #667eea;
        font-size: 0.95em;
    }

    .payment-edit-text-input,
    .payment-edit-select-field {
        padding: 12px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.95em;
        font-family: inherit;
        transition: all 0.2s ease;
        background-color: white;
    }

    .payment-edit-text-input:focus,
    .payment-edit-select-field:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        background-color: #f9fafb;
    }

    .payment-edit-text-input:disabled,
    .payment-edit-select-field:disabled {
        background-color: #f0f4f8;
        cursor: not-allowed;
        color: #718096;
    }

    .payment-edit-textarea-field {
        width: 100%;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-family: inherit;
        font-size: 0.95em;
        resize: vertical;
        min-height: 100px;
        transition: border-color 0.3s;
    }

    .payment-edit-textarea-field:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .payment-edit-error-message {
        font-size: 0.8em;
        color: #e53e3e;
        display: none;
        margin-top: 4px;
    }

    .payment-edit-error-message.show {
        display: block;
    }

    /* File Upload */
    .payment-edit-file-upload-wrapper {
        border: 2px dashed #cbd5e0;
        border-radius: 8px;
        padding: 40px 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: #f9fafb;
    }

    .payment-edit-file-upload-wrapper:hover {
        border-color: #667eea;
        background-color: #f0f4f8;
    }

    .payment-edit-file-upload-wrapper.drag-over {
        border-color: #667eea;
        background-color: #edf2f7;
    }

    .payment-edit-file-input {
        display: none;
    }

    .payment-edit-file-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        cursor: pointer;
    }

    .payment-edit-file-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8em;
        color: white;
    }

    .payment-edit-file-main-text {
        font-size: 0.95em;
        font-weight: 600;
        color: #2a4365;
    }

    .payment-edit-file-sub-text {
        font-size: 0.85em;
        color: #718096;
    }

    .payment-edit-file-preview {
        margin-top: 15px;
        display: none;
    }

    .payment-edit-file-preview.show {
        display: block;
    }

    /* Acceptance Methods */
    .payment-edit-acceptance-methods {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .payment-edit-acceptance-method-row {
        display: grid;
        grid-template-columns: 2fr 2fr 1.5fr 1.5fr auto;
        gap: 12px;
        padding: 15px;
        background-color: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        align-items: center;
        transition: all 0.2s ease;
    }

    .payment-edit-acceptance-method-row:hover {
        border-color: #cbd5e0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .payment-edit-btn-add-method {
        padding: 10px 20px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 0.9em;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        align-self: flex-start;
    }

    .payment-edit-btn-add-method:hover {
        background: #5568d3;
        transform: translateY(-2px);
    }

    .payment-edit-btn-remove-method {
        padding: 8px 12px;
        background: #fed7d7;
        color: #c53030;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1em;
        min-width: 40px;
        height: 40px;
    }

    .payment-edit-btn-remove-method:hover {
        background: #fc8181;
        transform: scale(1.05);
    }

    /* Attachment Buttons */
    .payment-edit-attachment-btn-view,
    .payment-edit-attachment-btn-delete {
        padding: 6px 8px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9em;
        min-width: 32px;
        height: 32px;
    }

    .payment-edit-attachment-btn-view {
        background: #c3fae8;
        color: #0b5345;
    }

    .payment-edit-attachment-btn-view:hover {
        background: #a6f4c5;
        transform: scale(1.1);
    }

    .payment-edit-attachment-btn-delete {
        background: #ffd6d6;
        color: #c92a2a;
    }

    .payment-edit-attachment-btn-delete:hover {
        background: #ff9e9e;
        transform: scale(1.1);
    }

    /* Line Items */
    .payment-edit-line-items-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .payment-edit-line-item {
        padding: 15px;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        border-left: 4px solid #48bb78;
    }

    /* Form Actions */
    .payment-edit-form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
        margin-top: 10px;
        position: sticky;
        bottom: 0;
        background: white;
        padding: 20px 30px;
        margin: 0 -30px -30px -30px;
        border-radius: 0 0 12px 12px;
    }

    .payment-edit-btn {
        padding: 12px 30px;
        border: none;
        border-radius: 6px;
        font-size: 0.95em;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .payment-edit-btn i {
        font-size: 0.9em;
    }

    .payment-edit-btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .payment-edit-btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }

    .payment-edit-btn-submit:active {
        transform: translateY(0);
    }

    .payment-edit-btn-cancel {
        background-color: #e2e8f0;
        color: #2a4365;
    }

    .payment-edit-btn-cancel:hover {
        background-color: #cbd5e0;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .payment-edit-form-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .payment-edit-acceptance-method-row {
            grid-template-columns: 1fr 1fr auto;
        }
    }

    @media (max-width: 768px) {
        .payment-edit-modal-container {
            max-width: 95vw;
            max-height: 95vh;
        }

        .payment-edit-modal-header {
            padding: 15px 20px;
        }

        .payment-edit-modal-title {
            font-size: 1.2em;
        }

        .payment-edit-modal-body {
            padding: 15px;
        }

        .payment-edit-form-grid {
            grid-template-columns: 1fr;
        }

        .payment-edit-form-actions {
            flex-direction: column;
            padding: 15px;
            margin: 0 -15px -15px -15px;
        }

        .payment-edit-btn {
            width: 100%;
            justify-content: center;
        }

        .payment-edit-acceptance-method-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    // Payment Entry Edit Modal Functions
    
    let paymentEditModalOverlay;
    let paymentEditForm;
    let closePaymentEditModalBtn;
    let cancelPaymentEditBtn;
    let submitPaymentEditBtn;
    let paymentEditLoadingSpinner;
    let paymentEditContent;
    let currentEditingPaymentEntryId = null;

    // Initialize modal elements after DOM is ready
    function initPaymentEditModal() {
        paymentEditModalOverlay = document.getElementById('paymentEntryEditModalOverlay');
        paymentEditForm = document.getElementById('paymentEditForm');
        closePaymentEditModalBtn = document.getElementById('closePaymentEditModal');
        cancelPaymentEditBtn = document.getElementById('cancelPaymentEditBtn');
        submitPaymentEditBtn = document.getElementById('submitPaymentEditBtn');
        paymentEditLoadingSpinner = document.getElementById('paymentEditLoadingSpinner');
        paymentEditContent = document.getElementById('paymentEditContent');
        
        // Load vendor categories from database
        loadVendorCategories();
        
        attachPaymentEditEventListeners();
    }

    // Open Edit Payment Entry Modal
    function openPaymentEditModal(entryId) {
        console.log('Opening edit modal for entry:', entryId);
        if (!paymentEditModalOverlay) {
            console.error('Modal overlay not found!');
            return;
        }
        
        currentEditingPaymentEntryId = entryId;
        
        paymentEditModalOverlay.classList.add('active');
        
        document.body.style.overflow = 'hidden';
        
        // Show loading spinner
        paymentEditLoadingSpinner.style.display = 'block';
        paymentEditContent.style.display = 'none';
        
        // Fetch payment entry details
        fetchPaymentEntryDetails(entryId);
    }

    // Close Edit Payment Entry Modal
    function closePaymentEditModal() {
        if (paymentEditModalOverlay) {
            paymentEditModalOverlay.classList.remove('active');
        }
        document.body.style.overflow = 'auto';
        if (paymentEditForm) {
            paymentEditForm.reset();
        }
        currentEditingPaymentEntryId = null;
    }

    // Make functions globally accessible
    window.openPaymentEditModal = openPaymentEditModal;
    window.closePaymentEditModal = closePaymentEditModal;

    // Fetch payment entry details from backend
    function fetchPaymentEntryDetails(entryId) {
        fetch(`fetch_complete_payment_entry_data_comprehensive.php?payment_entry_id=${entryId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.entry) {
                    populateEditForm(data.entry);
                    
                    // Hide spinner and show content
                    paymentEditLoadingSpinner.style.display = 'none';
                    paymentEditContent.style.display = 'block';
                } else {
                    console.error('API Error:', data);
                    alert('Error loading payment entry: ' + (data.message || 'Unknown error'));
                    closePaymentEditModal();
                }
            })
            .catch(error => {
                console.error('Error fetching payment entry details:', error);
                alert('Error loading payment entry details: ' + error.message);
                closePaymentEditModal();
            });
    }

    // Populate edit form with fetched data
    function populateEditForm(entryData) {
        // Store entry data globally for later use
        window.currentEntryData = entryData;

        // Load authorized users dropdown and projects first
        loadEditAuthorizedUsers(entryData);
        loadEditProjects(entryData);

        // Set project details immediately from API response (before dropdown loads)
        if (entryData.project_title) {
            // Create a temporary option for the current project
            const projectOption = document.createElement('option');
            projectOption.value = entryData.project_id_fk || '';
            projectOption.textContent = entryData.project_title;
            projectOption.selected = true;
            projectOption.setAttribute('data-type', entryData.project_type_name || '');
            document.getElementById('editPaymentProjectName').appendChild(projectOption);
        }
        
        // Set project type immediately from API response
        if (entryData.project_type_name) {
            document.getElementById('editPaymentProjectType').value = entryData.project_type_name;
        }

        // Basic Information - These will be set after dropdowns load
        // Date, Amount, and Payment Mode can be set immediately
        document.getElementById('editPaymentDate').value = entryData.payment_date_logged || '';
        document.getElementById('editPaymentAmount').value = entryData.payment_amount_base || '';
        document.getElementById('editPaymentMode').value = entryData.payment_mode_selected || '';

        // Status Information (Read-Only)
        document.getElementById('editPaymentStatus').value = (entryData.entry_status_current || 'N/A').toUpperCase();
        document.getElementById('editPaymentCreatedAt').value = formatDateTime(entryData.created_timestamp_utc);
        document.getElementById('editPaymentUpdatedAt').value = formatDateTime(entryData.updated_timestamp_utc);
        document.getElementById('editPaymentCreatedBy').value = entryData.created_by_username || 'N/A';

        // Payment Proof Info
        if (entryData.payment_proof_filename_original && entryData.payment_proof_document_path) {
            // Store proof path globally
            window.currentPaymentProofPath = entryData.payment_proof_document_path;
            
            const fileSizeKB = entryData.payment_proof_filesize_bytes ? Math.round(entryData.payment_proof_filesize_bytes / 1024) : 0;
            document.getElementById('editPaymentProofInfo').textContent = entryData.payment_proof_filename_original + ' (' + fileSizeKB + ' KB)';
            document.getElementById('editPaymentProofViewBtn').style.display = 'inline-flex';
        }

        // Load Multiple Acceptance Methods if applicable
        if (entryData.payment_mode_selected === 'multiple_acceptance' && entryData.acceptance_methods) {
            document.getElementById('editMultipleAcceptanceSection').style.display = 'block';
            loadEditAcceptanceMethods(entryData.acceptance_methods);
        }

        // Load Line Items if present
        if (entryData.line_items && entryData.line_items.length > 0) {
            document.getElementById('editLineItemsSection').style.display = 'block';
            loadEditLineItems(entryData.line_items);
        }
    }

    // Load authorized users for edit form
    function loadEditAuthorizedUsers(entryData) {
        fetch('get_active_users.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users.length > 0) {
                    let html = '<option value="">Select Authorized User</option>';
                    data.users.forEach(user => {
                        const selected = user.id == entryData.authorized_user_id_fk ? 'selected' : '';
                        html += `<option value="${user.id}" ${selected}>${user.username}</option>`;
                    });
                    document.getElementById('editPaymentAuthorizedUser').innerHTML = html;
                }
            })
            .catch(error => console.error('Error loading authorized users:', error));
    }

    // Load projects for edit form
    function loadEditProjects(entryData) {
        fetch('get_all_projects.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.projects && data.projects.length > 0) {
                    const projectDropdown = document.getElementById('editPaymentProjectName');
                    
                    // Get existing options (including the pre-added current project)
                    const existingOptions = Array.from(projectDropdown.options).map(opt => opt.value);
                    
                    // Add other projects that aren't already in the dropdown
                    data.projects.forEach(project => {
                        if (!existingOptions.includes(String(project.id))) {
                            const option = document.createElement('option');
                            option.value = project.id;
                            option.textContent = project.title;
                            option.setAttribute('data-type', project.project_type || '');
                            projectDropdown.appendChild(option);
                        }
                    });
                    
                    // Add event listener for project change to update type
                    projectDropdown.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        if (selectedOption && selectedOption.dataset.type) {
                            document.getElementById('editPaymentProjectType').value = selectedOption.dataset.type;
                        }
                    });
                }
            })
            .catch(error => console.error('Error loading projects:', error));
    }

    // Load acceptance methods for edit
    function loadEditAcceptanceMethods(methods) {
        const container = document.getElementById('editAcceptanceMethodsContainer');
        container.innerHTML = '';

        methods.forEach((method, index) => {
            const methodRow = document.createElement('div');
            methodRow.className = 'payment-edit-acceptance-method-row';
            methodRow.dataset.rowIndex = index;
            
            methodRow.innerHTML = `
                <select class="payment-edit-text-input edit-acceptance-method" data-row="${index}" style="width: 100%;">
                    <option value="">Select Payment Method</option>
                    <option value="cash" ${method.payment_method_type === 'cash' ? 'selected' : ''}>Cash</option>
                    <option value="cheque" ${method.payment_method_type === 'cheque' ? 'selected' : ''}>Cheque</option>
                    <option value="bank_transfer" ${method.payment_method_type === 'bank_transfer' ? 'selected' : ''}>Bank Transfer</option>
                    <option value="credit_card" ${method.payment_method_type === 'credit_card' ? 'selected' : ''}>Credit Card</option>
                    <option value="online" ${method.payment_method_type === 'online' ? 'selected' : ''}>Online Payment</option>
                    <option value="upi" ${method.payment_method_type === 'upi' ? 'selected' : ''}>UPI</option>
                </select>
                <input type="number" class="payment-edit-text-input edit-acceptance-amount" data-row="${index}" placeholder="Amount" value="${method.amount_received_value || ''}" step="0.01" min="0">
                <input type="text" class="payment-edit-text-input edit-acceptance-reference" data-row="${index}" placeholder="Reference/Cheque No." value="${method.reference_number_cheque || ''}">
                <div></div>
                <button type="button" class="payment-edit-btn-remove-method" onclick="removeEditAcceptanceMethod(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            `;

            container.appendChild(methodRow);
        });

        // Add event listeners to amount fields
        container.querySelectorAll('.edit-acceptance-amount').forEach(input => {
            input.addEventListener('input', updateEditAcceptanceTotals);
        });
    }

    // Load line items for edit
    function loadEditLineItems(lineItems) {
        const container = document.getElementById('editLineItemsContainer');
        container.innerHTML = '';

        lineItems.forEach((item, index) => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'payment-edit-line-item';
            itemDiv.dataset.lineItemId = item.line_item_entry_id;

            // Build attachment display if file exists
            let attachmentHTML = '';
            if (item.line_item_media_upload_path) {
                const fileName = item.line_item_media_original_filename || 'Attached File';
                const fileSize = item.line_item_media_filesize_bytes ? (Math.round(item.line_item_media_filesize_bytes / 1024)) + ' KB' : 'Unknown size';
                
                // Fix the path to be relative - extract just the filename
                let fixedPath = item.line_item_media_upload_path;
                
                // Extract filename from path (get the last part)
                const pathParts = fixedPath.split('/');
                const justFilename = pathParts[pathParts.length - 1];
                
                // Construct correct path
                fixedPath = 'uploads/entry_media/' + justFilename;
                
                attachmentHTML = `
                    <div style="margin-top: 15px; padding: 12px; background: #e6f7ff; border-radius: 6px; border-left: 4px solid #1890ff;">
                        <div style="display: flex; align-items: center; justify-content: space-between; color: #0050b3; font-size: 0.9em;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-file-alt"></i>
                                <strong>Attached File:</strong>
                                <a href="${fixedPath}" target="_blank" style="color: #1890ff; text-decoration: underline;">
                                    ${fileName}
                                </a>
                                <span style="color: #666; font-size: 0.85em;">(${fileSize})</span>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="payment-edit-attachment-btn-view" onclick="viewLineItemAttachment('${fixedPath}', '${fileName}')" title="View attachment">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="payment-edit-attachment-btn-delete" onclick="deleteLineItemAttachment(${item.line_item_entry_id})" title="Delete attachment">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            itemDiv.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="margin: 0; color: #2a4365; font-weight: 600;">Line Item #${index + 1}</h4>
                    <button type="button" onclick="removeEditLineItem(${item.line_item_entry_id})" class="payment-edit-btn-remove-method">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                <div class="payment-edit-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="payment-edit-form-group">
                        <label class="payment-edit-form-label">Recipient Type</label>
                        <input type="text" class="payment-edit-text-input" value="${item.recipient_type_category || ''}" readonly style="background-color: #f0f4f8;">
                    </div>
                    <div class="payment-edit-form-group">
                        <label class="payment-edit-form-label">Recipient Name</label>
                        <input type="text" class="payment-edit-text-input" value="${item.recipient_name_display || ''}" readonly style="background-color: #f0f4f8;">
                    </div>
                    <div class="payment-edit-form-group">
                        <label class="payment-edit-form-label">Amount</label>
                        <input type="number" class="payment-edit-text-input edit-line-item-amount" data-item-id="${item.line_item_entry_id}" value="${item.line_item_amount || ''}" step="0.01" min="0" required>
                    </div>
                    <div class="payment-edit-form-group">
                        <label class="payment-edit-form-label">Payment Mode</label>
                        <select class="payment-edit-text-input edit-line-item-mode" data-item-id="${item.line_item_entry_id}" required>
                            <option value="">Select Mode</option>
                            <option value="cash" ${item.line_item_payment_mode === 'cash' ? 'selected' : ''}>Cash</option>
                            <option value="cheque" ${item.line_item_payment_mode === 'cheque' ? 'selected' : ''}>Cheque</option>
                            <option value="bank_transfer" ${item.line_item_payment_mode === 'bank_transfer' ? 'selected' : ''}>Bank Transfer</option>
                            <option value="credit_card" ${item.line_item_payment_mode === 'credit_card' ? 'selected' : ''}>Credit Card</option>
                            <option value="online" ${item.line_item_payment_mode === 'online' ? 'selected' : ''}>Online Payment</option>
                            <option value="upi" ${item.line_item_payment_mode === 'upi' ? 'selected' : ''}>UPI</option>
                            <option value="split_payment" ${item.line_item_payment_mode === 'split_payment' ? 'selected' : ''}>Split Payment</option>
                            <option value="multiple_acceptance" ${item.line_item_payment_mode === 'multiple_acceptance' ? 'selected' : ''}>Multiple Acceptance</option>
                        </select>
                    </div>
                    <div class="payment-edit-form-group">
                        <label class="payment-edit-form-label">
                            <i class="fas fa-user-tie"></i> Payment Done Via <span class="payment-edit-required">*</span>
                        </label>
                        <select class="payment-edit-text-input edit-line-item-via" data-item-id="${item.line_item_entry_id}" required>
                            <option value="">Select User</option>
                        </select>
                    </div>
                </div>

                <div class="payment-edit-form-group" style="margin-top: 15px;">
                    <label class="payment-edit-form-label">Description/Notes</label>
                    <textarea class="payment-edit-textarea-field edit-line-item-description" data-item-id="${item.line_item_entry_id}" style="min-height: 60px;">${item.payment_description_notes || ''}</textarea>
                </div>

                <!-- Multiple Acceptance Methods for Line Item -->
                <div class="edit-line-item-acceptance-section" data-item-id="${item.line_item_entry_id}" style="margin-top: 15px; display: ${item.line_item_payment_mode === 'multiple_acceptance' ? 'block' : 'none'}; padding: 15px; background: #f0f7ff; border-radius: 8px; border-left: 4px solid #3182ce;">
                    <div style="font-size: 0.9em; font-weight: 600; color: #2a4365; margin-bottom: 12px;">
                        <i class="fas fa-credit-card"></i> Line Item Acceptance Methods
                    </div>
                    <div class="edit-line-item-acceptance-methods" data-item-id="${item.line_item_entry_id}" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px;">
                        <!-- Acceptance methods will be loaded here -->
                    </div>
                    <button type="button" class="payment-edit-btn-add-method" onclick="addEditLineItemAcceptanceMethod('${item.line_item_entry_id}')" style="font-size: 0.85em; padding: 8px 15px;">
                        <i class="fas fa-plus"></i> Add Payment Method
                    </button>
                </div>

                <div class="payment-edit-form-group" style="margin-top: 15px;">
                    <label class="payment-edit-form-label">
                        <i class="fas fa-paperclip"></i> Attach/Replace File (Optional)
                    </label>
                    <input type="file" class="payment-edit-text-input edit-line-item-file" data-item-id="${item.line_item_entry_id}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.mp4,.mov,.avi" style="padding: 8px;">
                    <small style="color: #718096; font-size: 0.85em;">Supported: PDF, Images, Documents, Videos</small>
                </div>

                ${attachmentHTML}
            `;

            container.appendChild(itemDiv);
        });

        // Add event listeners for amount changes
        container.querySelectorAll('.edit-line-item-amount').forEach(input => {
            input.addEventListener('input', updateEditLineItemsTotals);
        });

        // Add event listeners for payment mode changes to show/hide acceptance methods
        container.querySelectorAll('.edit-line-item-mode').forEach(modeSelect => {
            modeSelect.addEventListener('change', function() {
                const lineItemId = this.getAttribute('data-item-id');
                const acceptanceSection = document.querySelector(`.edit-line-item-acceptance-section[data-item-id="${lineItemId}"]`);
                if (this.value === 'multiple_acceptance') {
                    if (acceptanceSection) {
                        acceptanceSection.style.display = 'block';
                    }
                } else {
                    if (acceptanceSection) {
                        acceptanceSection.style.display = 'none';
                    }
                }
            });
        });

        // Load acceptance methods for each line item if payment mode is multiple_acceptance
        lineItems.forEach(item => {
            if (item.line_item_payment_mode === 'multiple_acceptance' && item.acceptance_methods) {
                const methodsContainer = document.querySelector(`.edit-line-item-acceptance-methods[data-item-id="${item.line_item_entry_id}"]`);
                if (methodsContainer && item.acceptance_methods.length > 0) {
                    item.acceptance_methods.forEach((method, index) => {
                        const methodRow = document.createElement('div');
                        methodRow.className = 'payment-edit-acceptance-method-row';
                        methodRow.dataset.rowIndex = index;
                        methodRow.dataset.itemId = item.line_item_entry_id;
                        
                        methodRow.innerHTML = `
                            <div>
                                <select class="payment-edit-text-input edit-line-item-acceptance-method" data-row="${index}" data-item-id="${item.line_item_entry_id}" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="cash" ${method.acceptance_method === 'cash' ? 'selected' : ''}>Cash</option>
                                    <option value="cheque" ${method.acceptance_method === 'cheque' ? 'selected' : ''}>Cheque</option>
                                    <option value="bank_transfer" ${method.acceptance_method === 'bank_transfer' ? 'selected' : ''}>Bank Transfer</option>
                                    <option value="credit_card" ${method.acceptance_method === 'credit_card' ? 'selected' : ''}>Credit Card</option>
                                    <option value="online" ${method.acceptance_method === 'online' ? 'selected' : ''}>Online Payment</option>
                                    <option value="upi" ${method.acceptance_method === 'upi' ? 'selected' : ''}>UPI</option>
                                </select>
                            </div>
                            <div>
                                <input type="number" class="payment-edit-text-input edit-line-item-acceptance-amount" data-row="${index}" data-item-id="${item.line_item_entry_id}" placeholder="Amount" step="0.01" min="0" value="${method.acceptance_amount || ''}" required>
                            </div>
                            <div>
                                <input type="text" class="payment-edit-text-input edit-line-item-acceptance-reference" data-row="${index}" data-item-id="${item.line_item_entry_id}" placeholder="Cheque No. / Reference" value="${method.acceptance_reference || ''}">
                            </div>
                            <button type="button" class="payment-edit-btn-remove-method" onclick="removeEditLineItemAcceptanceMethod('${item.line_item_entry_id}', ${index})" title="Remove this method">
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                        
                        methodsContainer.appendChild(methodRow);
                    });
                }
            }
        });

        // Load users for all "Payment Done Via" dropdowns
        fetch('get_active_users.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users.length > 0) {
                    let usersHTML = '<option value="">Select User</option>';
                    data.users.forEach(user => {
                        usersHTML += `<option value="${user.id}">${user.username}</option>`;
                    });
                    
                    // Update all "Payment Done Via" dropdowns for existing line items
                    document.querySelectorAll('.edit-line-item-via').forEach(select => {
                        const lineItemId = select.getAttribute('data-item-id');
                        const currentValue = select.value;
                        
                        select.innerHTML = usersHTML;
                        
                        // Restore previous selection if it exists
                        if (currentValue) {
                            select.value = currentValue;
                        }
                        
                        // Set pre-selected value from original data if available
                        if (window.currentEntryData && window.currentEntryData.line_items) {
                            const originalLineItem = window.currentEntryData.line_items.find(item => 
                                item.line_item_entry_id == lineItemId
                            );
                            if (originalLineItem && originalLineItem.line_item_paid_via_user_id) {
                                select.value = originalLineItem.line_item_paid_via_user_id;
                            }
                        }
                    });
                }
            })
            .catch(error => console.error('Error loading users:', error));
    }

    // Update acceptance totals
    function updateEditAcceptanceTotals() {
        const amountInputs = document.querySelectorAll('.edit-acceptance-amount');
        let totalReceived = 0;

        amountInputs.forEach(input => {
            if (input.value) {
                totalReceived += parseFloat(input.value) || 0;
            }
        });

        const totalAmount = parseFloat(document.getElementById('editPaymentAmount').value) || 0;
        const difference = totalAmount - totalReceived;

        document.getElementById('editAcceptanceTotalAmount').textContent = totalAmount.toFixed(2);
        document.getElementById('editAcceptanceReceivedAmount').textContent = totalReceived.toFixed(2);
        document.getElementById('editAcceptanceDifference').textContent = Math.abs(difference).toFixed(2);
    }

    // Update line items totals with validation
    function updateEditLineItemsTotals() {
        const amountInputs = document.querySelectorAll('.edit-line-item-amount');
        let totalAmount = 0;

        amountInputs.forEach(input => {
            if (input.value) {
                totalAmount += parseFloat(input.value) || 0;
            }
        });

        const mainPaymentAmount = parseFloat(document.getElementById('editPaymentAmount').value) || 0;
        
        document.getElementById('editLineItemsTotalAmount').textContent = totalAmount.toFixed(2);
        
        // Validation: Check if line items total exceeds main payment amount
        const warningBox = document.getElementById('editLineItemsValidationWarning');
        const warningText = document.getElementById('editLineItemsWarningText');
        
        if (totalAmount > mainPaymentAmount && mainPaymentAmount > 0) {
            warningBox.style.display = 'block';
            warningText.textContent = `⚠️ Line items total (₹ ${totalAmount.toFixed(2)}) exceeds main payment amount (₹ ${mainPaymentAmount.toFixed(2)})!`;
            document.getElementById('submitPaymentEditBtn').disabled = true;
            document.getElementById('submitPaymentEditBtn').style.opacity = '0.6';
            document.getElementById('submitPaymentEditBtn').style.cursor = 'not-allowed';
            document.getElementById('submitPaymentEditBtn').title = 'Cannot save: Line items total exceeds payment amount';
        } else {
            warningBox.style.display = 'none';
            document.getElementById('submitPaymentEditBtn').disabled = false;
            document.getElementById('submitPaymentEditBtn').style.opacity = '1';
            document.getElementById('submitPaymentEditBtn').style.cursor = 'pointer';
            document.getElementById('submitPaymentEditBtn').title = '';
        }
    }

    // Helper function to format datetime
    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        const date = new Date(dateTimeString);
        return date.toLocaleString('en-GB', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    // Remove acceptance method
    function removeEditAcceptanceMethod(index) {
        const methodRow = document.querySelector(`.payment-edit-acceptance-method-row[data-row-index="${index}"]`);
        if (methodRow) {
            methodRow.remove();
            updateEditAcceptanceTotals();
        }
    }

    // Remove line item
    function removeEditLineItem(lineItemId) {
        const container = document.querySelector(`.payment-edit-line-item[data-line-item-id="${lineItemId}"]`);
        if (container) {
            container.remove();
            updateEditLineItemsTotals();
        }
    }

    // View line item attachment
    function viewLineItemAttachment(filePath, fileName) {
        if (!filePath) {
            alert('No file attached');
            return;
        }
        
        // File path should already be in correct format: uploads/entry_media/filename.ext
        window.open(filePath, '_blank');
    }

    // Delete line item attachment
    function deleteLineItemAttachment(lineItemId) {
        if (!confirm('Are you sure you want to delete this attachment?')) {
            return;
        }

        // Find the attachment div and remove it
        const lineItemDiv = document.querySelector(`.payment-edit-line-item[data-line-item-id="${lineItemId}"]`);
        if (lineItemDiv) {
            const attachmentDiv = lineItemDiv.querySelector('[style*="background: #e6f7ff"]');
            if (attachmentDiv) {
                attachmentDiv.remove();
                // Mark the line item as having deleted attachment
                lineItemDiv.dataset.attachmentDeleted = 'true';
            }
        }
    }

    // View payment proof
    function viewPaymentProof() {
        if (!window.currentPaymentProofPath) {
            alert('No proof attachment available');
            return;
        }

        let proofPath = window.currentPaymentProofPath;
        
        // Extract just the filename from the path
        const pathParts = proofPath.split('/');
        const justFilename = pathParts[pathParts.length - 1];
        
        // Construct correct path - proof files are in payment_proofs folder
        proofPath = 'uploads/payment_proofs/' + justFilename;
        
        window.open(proofPath, '_blank');
    }

    // Handle payment proof file upload
    function handlePaymentProofUpload(event) {
        const fileInput = event.target;
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const fileName = file.name;
            const fileSize = Math.round(file.size / 1024);
            
            // Update the display
            document.getElementById('editPaymentProofInfo').textContent = fileName + ' (' + fileSize + ' KB)';
            document.getElementById('editPaymentProofViewBtn').style.display = 'inline-flex';
            
            // Store the file for upload when form is submitted
            window.newPaymentProofFile = file;
        }
    }

    // Event Listeners Setup
    function attachPaymentEditEventListeners() {
        if (closePaymentEditModalBtn) {
            closePaymentEditModalBtn.addEventListener('click', closePaymentEditModal);
        }

        if (cancelPaymentEditBtn) {
            cancelPaymentEditBtn.addEventListener('click', closePaymentEditModal);
        }

        if (paymentEditModalOverlay) {
            paymentEditModalOverlay.addEventListener('click', function(event) {
                if (event.target === paymentEditModalOverlay) {
                    closePaymentEditModal();
                }
            });
        }

        // Handle project selection to update project type
        const projectNameSelect = document.getElementById('editPaymentProjectName');
        if (projectNameSelect) {
            projectNameSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const projectType = selectedOption.getAttribute('data-type') || '';
                const projectTypeField = document.getElementById('editPaymentProjectType');
                if (projectTypeField) {
                    projectTypeField.value = projectType;
                }
            });
        }

        // Handle payment mode change to show/hide multiple acceptance section
        const paymentModeSelect = document.getElementById('editPaymentMode');
        if (paymentModeSelect) {
            paymentModeSelect.addEventListener('change', function() {
                const multipleAcceptanceSection = document.getElementById('editMultipleAcceptanceSection');
                if (this.value === 'multiple_acceptance') {
                    if (multipleAcceptanceSection) {
                        multipleAcceptanceSection.style.display = 'block';
                    }
                } else {
                    if (multipleAcceptanceSection) {
                        multipleAcceptanceSection.style.display = 'none';
                    }
                }
            });
        }

        // Handle main payment amount change - trigger validation
        const paymentAmountInput = document.getElementById('editPaymentAmount');
        if (paymentAmountInput) {
            paymentAmountInput.addEventListener('input', updateEditLineItemsTotals);
        }

        // Handle Add Payment Method button
        const addAcceptanceMethodBtn = document.getElementById('editAddAcceptanceMethodBtn');
        if (addAcceptanceMethodBtn) {
            addAcceptanceMethodBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addEditAcceptanceMethod();
            });
        }

        // Handle form submission
        if (paymentEditForm) {
            paymentEditForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitPaymentEditForm();
            });
        }

        // Handle Add Line Item button
        const addLineItemBtn = document.getElementById('editAddLineItemBtn');
        if (addLineItemBtn) {
            addLineItemBtn.addEventListener('click', addNewEditLineItem);
        }

        // Handle payment proof file upload
        const proofUploadInput = document.getElementById('editPaymentProofUpload');
        if (proofUploadInput) {
            proofUploadInput.addEventListener('change', handlePaymentProofUpload);
        }
    }

    // Add new acceptance method row
    function addEditAcceptanceMethod() {
        const container = document.getElementById('editAcceptanceMethodsContainer');
        const rowIndex = container.querySelectorAll('.payment-edit-acceptance-method-row').length;
        
        const methodRow = document.createElement('div');
        methodRow.className = 'payment-edit-acceptance-method-row';
        methodRow.dataset.rowIndex = rowIndex;
        
        methodRow.innerHTML = `
            <div>
                <select class="payment-edit-text-input edit-acceptance-method" data-row="${rowIndex}" required>
                    <option value="">Select Payment Method</option>
                    <option value="cash">Cash</option>
                    <option value="cheque">Cheque</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="online">Online Payment</option>
                    <option value="upi">UPI</option>
                </select>
            </div>
            <div>
                <input type="number" class="payment-edit-text-input edit-acceptance-amount" data-row="${rowIndex}" placeholder="Amount" step="0.01" min="0" required>
            </div>
            <div>
                <input type="text" class="payment-edit-text-input edit-acceptance-reference" data-row="${rowIndex}" placeholder="Cheque No. / Reference">
            </div>
            <button type="button" class="payment-edit-btn-remove-method" onclick="removeEditAcceptanceMethod(${rowIndex})" title="Remove this method">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        container.appendChild(methodRow);
        
        // Add event listener to amount input
        const amountInput = methodRow.querySelector('.edit-acceptance-amount');
        if (amountInput) {
            amountInput.addEventListener('input', updateEditAcceptanceTotals);
        }
    }

    // Add new line item
    function addNewEditLineItem() {
        const container = document.getElementById('editLineItemsContainer');
        const lineItemsSection = document.getElementById('editLineItemsSection');
        
        // Show the section if hidden
        if (lineItemsSection) {
            lineItemsSection.style.display = 'block';
        }

        // Generate unique ID for new line item
        const newId = 'new_' + Date.now();
        const lineItemCount = container.querySelectorAll('.payment-edit-line-item').length + 1;

        const itemDiv = document.createElement('div');
        itemDiv.className = 'payment-edit-line-item';
        itemDiv.dataset.lineItemId = newId;
        itemDiv.dataset.isNew = 'true';

        itemDiv.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 style="margin: 0; color: #2a4365; font-weight: 600;">Line Item #${lineItemCount}</h4>
                <button type="button" onclick="removeEditLineItem('${newId}')" class="payment-edit-btn-remove-method">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="payment-edit-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="payment-edit-form-group">
                    <label class="payment-edit-form-label">
                        <i class="fas fa-tag"></i> Recipient Type <span class="payment-edit-required">*</span>
                    </label>
                    <select class="payment-edit-text-input edit-line-item-type" data-item-id="${newId}" required>
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div class="payment-edit-form-group">
                    <label class="payment-edit-form-label">
                        <i class="fas fa-user"></i> Recipient Name / TO <span class="payment-edit-required">*</span>
                    </label>
                    <select class="payment-edit-text-input edit-line-item-name" data-item-id="${newId}" required>
                        <option value="">Select Recipient Name</option>
                    </select>
                </div>
                <div class="payment-edit-form-group">
                    <label class="payment-edit-form-label">
                        <i class="fas fa-user-tie"></i> Payment Done Via <span class="payment-edit-required">*</span>
                    </label>
                    <select class="payment-edit-text-input edit-line-item-via" data-item-id="${newId}" required>
                        <option value="">Select User</option>
                    </select>
                </div>
                <div class="payment-edit-form-group">
                    <label class="payment-edit-form-label">
                        <i class="fas fa-rupee-sign"></i> Amount <span class="payment-edit-required">*</span>
                    </label>
                    <input type="number" class="payment-edit-text-input edit-line-item-amount" data-item-id="${newId}" placeholder="Amount" step="0.01" min="0" required>
                </div>
                <div class="payment-edit-form-group">
                    <label class="payment-edit-form-label">
                        <i class="fas fa-credit-card"></i> Payment Mode <span class="payment-edit-required">*</span>
                    </label>
                    <select class="payment-edit-text-input edit-line-item-mode" data-item-id="${newId}" required>
                        <option value="">Select Mode</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="online">Online Payment</option>
                        <option value="upi">UPI</option>
                    </select>
                </div>
                <div class="payment-edit-form-group">
                    <label class="payment-edit-form-label">
                        <i class="fas fa-file"></i> For / Description <span class="payment-edit-required">*</span>
                    </label>
                    <input type="text" class="payment-edit-text-input edit-line-item-for" data-item-id="${newId}" placeholder="What is this payment for?" required>
                </div>
            </div>

            <div class="payment-edit-form-group" style="margin-top: 15px;">
                <label class="payment-edit-form-label">
                    <i class="fas fa-paperclip"></i> Attach File (Optional)
                </label>
                <input type="file" class="payment-edit-text-input edit-line-item-file" data-item-id="${newId}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="padding: 8px;">
                <small style="color: #718096; font-size: 0.85em;">Supported: PDF, Images (JPG, PNG), Documents (DOC, DOCX)</small>
            </div>

            <div class="payment-edit-form-group" style="margin-top: 15px;">
                <label class="payment-edit-form-label">
                    <i class="fas fa-sticky-note"></i> Notes (Optional)
                </label>
                <textarea class="payment-edit-textarea-field edit-line-item-description" data-item-id="${newId}" placeholder="Add any additional notes..." style="min-height: 60px;"></textarea>
            </div>
        `;

        container.appendChild(itemDiv);

        // Populate Recipient Type dropdown with dynamic optgroups
        const typeSelect = itemDiv.querySelector('.edit-line-item-type');
        populateRecipientTypeDropdown(typeSelect);

        // Populate the "Payment Done Via" dropdown with users
        const viaSelect = itemDiv.querySelector('.edit-line-item-via');
        fetch('get_active_users.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users.length > 0) {
                    let html = '<option value="">Select User</option>';
                    data.users.forEach(user => {
                        html += `<option value="${user.id}">${user.username}</option>`;
                    });
                    viaSelect.innerHTML = html;
                }
            })
            .catch(error => console.error('Error loading users:', error));

        // Add event listener for recipient type change to load recipient names
        const nameSelect = itemDiv.querySelector('.edit-line-item-name');
        
        typeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            if (selectedType) {
                loadRecipientNamesByType(selectedType, nameSelect);
            } else {
                nameSelect.innerHTML = '<option value="">Select Recipient Name</option>';
            }
        });

        // Add event listener for payment mode change to show/hide acceptance methods
        const modeSelect = itemDiv.querySelector('.edit-line-item-mode');
        modeSelect.addEventListener('change', function() {
            const acceptanceSection = itemDiv.querySelector('.edit-line-item-acceptance-section');
            if (this.value === 'multiple_acceptance') {
                if (acceptanceSection) {
                    acceptanceSection.style.display = 'block';
                }
            } else {
                if (acceptanceSection) {
                    acceptanceSection.style.display = 'none';
                }
            }
        });

        // Add event listener for amount changes
        itemDiv.querySelector('.edit-line-item-amount').addEventListener('input', updateEditLineItemsTotals);
    }

    // Add acceptance method to line item
    function addEditLineItemAcceptanceMethod(lineItemId) {
        const methodsContainer = document.querySelector(`.edit-line-item-acceptance-methods[data-item-id="${lineItemId}"]`);
        if (!methodsContainer) return;

        const rowIndex = methodsContainer.querySelectorAll('.payment-edit-acceptance-method-row').length;
        
        const methodRow = document.createElement('div');
        methodRow.className = 'payment-edit-acceptance-method-row';
        methodRow.dataset.rowIndex = rowIndex;
        methodRow.dataset.itemId = lineItemId;
        
        methodRow.innerHTML = `
            <div>
                <select class="payment-edit-text-input edit-line-item-acceptance-method" data-row="${rowIndex}" data-item-id="${lineItemId}" required>
                    <option value="">Select Payment Method</option>
                    <option value="cash">Cash</option>
                    <option value="cheque">Cheque</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="online">Online Payment</option>
                    <option value="upi">UPI</option>
                </select>
            </div>
            <div>
                <input type="number" class="payment-edit-text-input edit-line-item-acceptance-amount" data-row="${rowIndex}" data-item-id="${lineItemId}" placeholder="Amount" step="0.01" min="0" required>
            </div>
            <div>
                <input type="text" class="payment-edit-text-input edit-line-item-acceptance-reference" data-row="${rowIndex}" data-item-id="${lineItemId}" placeholder="Cheque No. / Reference">
            </div>
            <button type="button" class="payment-edit-btn-remove-method" onclick="removeEditLineItemAcceptanceMethod('${lineItemId}', ${rowIndex})" title="Remove this method">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        methodsContainer.appendChild(methodRow);
    }

    // Remove acceptance method from line item
    function removeEditLineItemAcceptanceMethod(lineItemId, rowIndex) {
        const methodRow = document.querySelector(`.payment-edit-acceptance-method-row[data-item-id="${lineItemId}"][data-row-index="${rowIndex}"]`);
        if (methodRow) {
            methodRow.remove();
        }
    }

    // Store vendor categories globally
    let vendorCategoriesCache = null;

    // Load vendor categories with optgroups from database
    function loadVendorCategories() {
        return fetch('get_vendor_categories_with_optgroups.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.categories) {
                    vendorCategoriesCache = data.categories;
                    return data.categories;
                } else {
                    console.error('Failed to load vendor categories');
                    return null;
                }
            })
            .catch(error => {
                console.error('Error loading vendor categories:', error);
                return null;
            });
    }

    // Populate Recipient Type dropdown with dynamic optgroups
    function populateRecipientTypeDropdown(selectElement) {
        if (!vendorCategoriesCache) {
            console.error('Vendor categories not loaded yet');
            return;
        }

        let html = '<option value="">Select Recipient Type</option>';

        // First add Labour static options
        html += '<optgroup label="Labour">';
        html += '<option value="Permanent">Permanent</option>';
        html += '<option value="Temporary">Temporary</option>';
        html += '<option value="Other">Other</option>';
        html += '</optgroup>';

        // Add vendor categories as optgroups from database
        for (const [optgroupLabel, options] of Object.entries(vendorCategoriesCache)) {
            html += `<optgroup label="${optgroupLabel}">`;
            options.forEach(option => {
                html += `<option value="${option.value}">${option.label}</option>`;
            });
            html += '</optgroup>';
        }

        selectElement.innerHTML = html;
    }

    // Load recipient names by type
    function loadRecipientNamesByType(recipientType, selectElement) {
        fetch(`fetch_recipient_names_by_type_comprehensive.php?recipient_type=${encodeURIComponent(recipientType)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.recipients && data.recipients.length > 0) {
                    let html = '<option value="">Select Recipient Name</option>';
                    data.recipients.forEach(recipient => {
                        const displayName = recipient.full_name || recipient.vendor_full_name || 'Unknown';
                        const code = recipient.labour_unique_code || recipient.vendor_unique_code || '';
                        const codeDisplay = code ? ` (${code})` : '';
                        html += `<option value="${recipient.recipient_id}">${displayName}${codeDisplay}</option>`;
                    });
                    selectElement.innerHTML = html;
                } else {
                    selectElement.innerHTML = '<option value="">No recipients found for this type</option>';
                }
            })
            .catch(error => {
                console.error('Error loading recipient names:', error);
                selectElement.innerHTML = '<option value="">Error loading recipients</option>';
            });
    }

    // Submit edit form
    function submitPaymentEditForm() {
        // Validate form
        if (!paymentEditForm.checkValidity()) {
            alert('Please fill in all required fields');
            return;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('payment_entry_id', currentEditingPaymentEntryId);
        formData.append('paymentDate', document.getElementById('editPaymentDate').value);
        formData.append('paymentAmount', document.getElementById('editPaymentAmount').value);
        formData.append('authorizedUserId', document.getElementById('editPaymentAuthorizedUser').value);
        formData.append('paymentMode', document.getElementById('editPaymentMode').value);

        // Debug: Log the values being sent
        console.log('Payment Entry ID:', currentEditingPaymentEntryId);
        console.log('Payment Date:', document.getElementById('editPaymentDate').value);
        console.log('Payment Amount:', document.getElementById('editPaymentAmount').value);

        // Collect acceptance methods
        const acceptanceMethods = [];
        document.querySelectorAll('.payment-edit-acceptance-method-row').forEach((row, index) => {
            const method = row.querySelector('.edit-acceptance-method')?.value;
            const amount = row.querySelector('.edit-acceptance-amount')?.value;
            const reference = row.querySelector('.edit-acceptance-reference')?.value;

            if (method && amount) {
                acceptanceMethods.push({
                    payment_method_type: method,
                    amount_received_value: amount,
                    reference_number_cheque: reference || ''
                });
            }
        });

        if (acceptanceMethods.length > 0) {
            formData.append('acceptanceMethods', JSON.stringify(acceptanceMethods));
        }

        // Collect line items
        const lineItems = [];
        document.querySelectorAll('.payment-edit-line-item').forEach((container, index) => {
            const amountInput = container.querySelector('.edit-line-item-amount');
            const modeSelect = container.querySelector('.edit-line-item-mode');
            const descriptionInput = container.querySelector('.edit-line-item-description');
            const viaSelect = container.querySelector('.edit-line-item-via');
            
            // Get recipient type and name from the line item container data
            let recipientType = '';
            let recipientName = '';
            let recipientId = null;
            let descriptionNotes = '';
            let paidViaUserId = null;
            
            // For existing line items, get data from the original entry data
            const lineItemId = container.dataset.lineItemId;
            if (window.currentEntryData && window.currentEntryData.line_items) {
                const originalLineItem = window.currentEntryData.line_items.find(item => 
                    item.line_item_entry_id == lineItemId
                );
                if (originalLineItem) {
                    recipientType = originalLineItem.recipient_type_category || '';
                    recipientName = originalLineItem.recipient_name_display || '';
                    recipientId = originalLineItem.recipient_id_reference || null;
                    descriptionNotes = originalLineItem.payment_description_notes || '';
                    paidViaUserId = originalLineItem.line_item_paid_via_user_id || null;
                }
            }
            
            // For new line items, try to get from form fields
            if (!recipientType || !recipientId) {
                const typeSelect = container.querySelector('.edit-line-item-type');
                const nameSelect = container.querySelector('.edit-line-item-name');
                
                if (typeSelect) {
                    recipientType = typeSelect.value || '';
                }
                if (nameSelect) {
                    recipientId = nameSelect.value || null;
                    // Get the display text from the selected option
                    const selectedOption = nameSelect.options[nameSelect.selectedIndex];
                    if (selectedOption) {
                        recipientName = selectedOption.text || '';
                    }
                }
            }
            
            // Get description from the textarea - always check the form for updated values
            if (descriptionInput) {
                descriptionNotes = descriptionInput.value || descriptionNotes;
            }
            
            // Get Payment Done Via from dropdown - always check for updated values
            if (viaSelect) {
                paidViaUserId = viaSelect.value ? parseInt(viaSelect.value) : null;
            }

            if (amountInput && amountInput.value && modeSelect && modeSelect.value) {
                lineItems.push({
                    line_item_entry_id: lineItemId,
                    recipient_type_category: recipientType,
                    recipient_id_reference: recipientId ? parseInt(recipientId) : null,
                    recipient_name_display: recipientName,
                    payment_description_notes: descriptionNotes,
                    line_item_amount: amountInput.value,
                    line_item_payment_mode: modeSelect.value,
                    line_item_paid_via_user_id: paidViaUserId,
                    line_item_status: 'pending'
                });
            }
        });

        if (lineItems.length > 0) {
            formData.append('lineItems', JSON.stringify(lineItems));
        }

        console.log('Acceptance Methods:', acceptanceMethods);
        console.log('Line Items:', lineItems);

        // Submit to backend
        submitPaymentEditBtn.disabled = true;
        submitPaymentEditBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('handlers/update_payment_entry_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Payment entry updated successfully!');
                closePaymentEditModal();
                // Reload payment entries with current filters
                if (typeof loadPaymentEntries === 'function') {
                    loadPaymentEntries(
                        entriesPaginationState.limit,
                        entriesPaginationState.currentPage,
                        entriesPaginationState.search,
                        entriesPaginationState.status,
                        entriesPaginationState.dateFrom,
                        entriesPaginationState.dateTo,
                        entriesPaginationState.projectType,
                        entriesPaginationState.vendorCategory,
                        entriesPaginationState.paidBy
                    );
                }
            } else {
                alert('Error updating payment entry: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating payment entry: ' + error.message);
        })
        .finally(() => {
            submitPaymentEditBtn.disabled = false;
            submitPaymentEditBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaymentEditModal);
    } else {
        // DOM is already loaded
        initPaymentEditModal();
    }
</script>