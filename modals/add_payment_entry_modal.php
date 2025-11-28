<?php
// Add Payment Entry Modal
// This modal is included in purchase_manager_dashboard.php
// It provides a form for adding new payment entries with project, vendor, and payment details
?>

<div id="paymentEntryModalOverlay" class="payment-entry-overlay">
    <div class="payment-entry-modal-container">
        <!-- Modal Header -->
        <div class="payment-entry-modal-header">
            <h2 class="payment-entry-modal-title">Add Payment Entry</h2>
            <button type="button" class="payment-entry-close-btn" id="closePaymentEntryModal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="payment-entry-modal-body">
            <form id="paymentEntryForm" class="payment-entry-form-wrapper">
                <!-- Single Payment Form -->
                <div id="singlePaymentContent" class="payment-entry-content-section active">
                    <div class="payment-entry-form-grid">
                        <!-- Project Type -->
                        <div class="payment-entry-form-group">
                            <label for="paymentProjectType" class="payment-entry-form-label">
                                <i class="fas fa-folder-open"></i> Project Type <span class="payment-entry-required">*</span>
                            </label>
                            <select id="paymentProjectType" name="projectType" class="payment-entry-select-field" required>
                                <option value="">Select Project Type</option>
                                <option value="architecture">Architecture</option>
                                <option value="interior">Interior</option>
                                <option value="construction">Construction</option>
                            </select>
                            <span class="payment-entry-error-message" id="paymentProjectTypeError"></span>
                        </div>

                        <!-- Project Name -->
                        <div class="payment-entry-form-group">
                            <label for="paymentProjectName" class="payment-entry-form-label">
                                <i class="fas fa-tasks"></i> Project Name <span class="payment-entry-required">*</span>
                            </label>
                            <select id="paymentProjectName" name="projectName" class="payment-entry-select-field" required disabled>
                                <option value="">First select project type</option>
                            </select>
                            <span class="payment-entry-error-message" id="paymentProjectNameError"></span>
                        </div>

                        <!-- Payment Date -->
                        <div class="payment-entry-form-group">
                            <label for="paymentDate" class="payment-entry-form-label">
                                <i class="fas fa-calendar-alt"></i> Date <span class="payment-entry-required">*</span>
                            </label>
                            <input type="date" id="paymentDate" name="paymentDate" class="payment-entry-text-input" required>
                            <span class="payment-entry-error-message" id="paymentDateError"></span>
                        </div>

                        <!-- Amount -->
                        <div class="payment-entry-form-group">
                            <label for="paymentAmount" class="payment-entry-form-label">
                                <i class="fas fa-rupee-sign"></i> Amount <span class="payment-entry-required">*</span>
                            </label>
                            <input type="number" id="paymentAmount" name="amount" class="payment-entry-text-input" placeholder="Enter amount" step="0.01" min="0" required>
                            <span class="payment-entry-error-message" id="paymentAmountError"></span>
                        </div>

                        <!-- Payment Done Via (Authorized User) -->
                        <div class="payment-entry-form-group">
                            <label for="paymentAuthorizedUser" class="payment-entry-form-label">
                                <i class="fas fa-user-check"></i> Payment Done By <span class="payment-entry-required">*</span>
                            </label>
                            <select id="paymentAuthorizedUser" name="authorizedUserId" class="payment-entry-select-field" required>
                                <option value="">Select Authorized User</option>
                            </select>
                            <span class="payment-entry-error-message" id="paymentAuthorizedUserError"></span>
                        </div>

                        <!-- Payment Mode -->
                        <div class="payment-entry-form-group">
                            <label for="paymentMode" class="payment-entry-form-label">
                                <i class="fas fa-credit-card"></i> Payment Mode <span class="payment-entry-required">*</span>
                            </label>
                            <select id="paymentMode" name="paymentMode" class="payment-entry-select-field" required>
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
                            <span class="payment-entry-error-message" id="paymentModeError"></span>
                        </div>
                    </div>

                    <!-- Multiple Acceptance Section -->
                    <div id="multipleAcceptanceContent" class="payment-entry-content-section">
                        <div class="payment-entry-form-section">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
                                <div style="width: 4px; height: 28px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 2px;"></div>
                                <div>
                                    <h3 style="margin: 0; color: #2d3748; font-size: 1.1em; font-weight: 700;">Multiple Acceptance</h3>
                                    <p style="margin: 4px 0 0 0; color: #718096; font-size: 0.85em;">Specify how the payment is received through multiple payment methods</p>
                                </div>
                            </div>

                            <div class="payment-entry-multiple-acceptance-container">
                                <!-- Methods Table Header -->
                                <div style="display: grid; grid-template-columns: 2fr 2fr 1.5fr 1.5fr auto; gap: 15px; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0;">
                                    <div style="font-size: 0.75em; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px;">Payment Method</div>
                                    <div style="font-size: 0.75em; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px;">Amount Received (₹)</div>
                                    <div style="font-size: 0.75em; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px;">Reference/Cheque No.</div>
                                    <div style="font-size: 0.75em; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px;">Media</div>
                                    <div></div>
                                </div>

                                <div class="payment-entry-acceptance-methods" id="acceptanceMethodsContainer">
                                    <!-- Acceptance method rows will be added here -->
                                </div>

                                <button type="button" class="payment-entry-btn payment-entry-btn-add-method" id="addAcceptanceMethodBtn" style="margin-top: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9em; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                                    <i class="fas fa-plus"></i> Add Payment Method
                                </button>
                            </div>

                            <!-- Total Acceptance Summary -->
                            <div style="margin-top: 25px; padding: 20px; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 12px; border: 1px solid #e2e8f0;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                                    <div style="padding: 15px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
                                        <label style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">Total Amount</label>
                                        <div style="font-size: 1.4em; font-weight: 800; color: #2d3748;">₹ <span id="acceptanceTotalAmount">0.00</span></div>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
                                        <label style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">Amount Received</label>
                                        <div style="font-size: 1.4em; font-weight: 800; color: #22863a;">₹ <span id="acceptanceReceivedAmount">0.00</span></div>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
                                        <label style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">Difference</label>
                                        <div style="font-size: 1.4em; font-weight: 800; color: #e53e3e;">₹ <span id="acceptanceDifference">0.00</span></div>
                                    </div>
                                </div>
                                <div style="margin-top: 15px; padding: 12px 15px; background-color: #fff5f5; border-left: 4px solid #e53e3e; border-radius: 6px; display: none;" id="acceptanceWarning">
                                    <span style="color: #e53e3e; font-size: 0.85em; font-weight: 600;"><i class="fas fa-exclamation-circle"></i> Total received amount does not match total amount</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Proof Image -->
                    <div class="payment-entry-form-section" style="margin-top: 20px;">
                        <h3 class="payment-entry-section-title">
                            <i class="fas fa-image"></i> Payment Proof Image
                        </h3>

                        <div class="payment-entry-file-upload-wrapper" id="paymentProofUploadArea">
                            <input type="file" id="paymentProofImage" name="proofImage" class="payment-entry-file-input" accept=".pdf,.jpg,.jpeg,.png">
                            <label for="paymentProofImage" class="payment-entry-file-label">
                                <div class="payment-entry-file-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <span class="payment-entry-file-main-text">Drop file here or click to browse</span>
                                <small class="payment-entry-file-sub-text">PDF, JPG, PNG • Max 5MB</small>
                            </label>
                            <div id="paymentProofPreview" class="payment-entry-file-preview"></div>
                            <span class="payment-entry-error-message" id="paymentProofError"></span>
                        </div>
                    </div>
                    <!-- Additional Entries Container -->
                    <div id="additionalEntriesContainer">
                        <!-- Add More Entry Button will be inserted here -->
                    </div>
                </div>

                <!-- Multiple Payments Content (Hidden by default) -->
                <div id="multiplePaymentContent" class="payment-entry-content-section">
                    <div class="payment-entry-multiple-payments-table">
                        <div class="payment-entry-table-controls">
                            <button type="button" class="payment-entry-btn payment-entry-btn-add-row" id="addPaymentRowBtn">
                                <i class="fas fa-plus"></i> Add Payment Row
                            </button>
                        </div>

                        <div class="payment-entry-table-wrapper">
                            <table class="payment-entry-payments-table">
                                <thead>
                                    <tr>
                                        <th>Sr. No</th>
                                        <th>Project Type</th>
                                        <th>Project Name</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Mode</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentRowsContainer">
                                    <!-- Rows will be added here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="payment-entry-form-actions">
                    <button type="button" class="payment-entry-btn payment-entry-btn-cancel" id="cancelPaymentEntryBtn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="payment-entry-btn payment-entry-btn-submit" id="submitPaymentEntryBtn">
                        <i class="fas fa-check"></i> Save Payment Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Payment Entry Modal Styles */
    
    .payment-entry-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        overflow-y: auto;
        animation: payment-entry-fade-in 0.3s ease;
        padding: 20px;
    }

    .payment-entry-overlay.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @keyframes payment-entry-fade-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .payment-entry-modal-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        width: 100%;
        max-width: 1400px;
        max-height: 90vh;
        overflow-y: auto;
        animation: payment-entry-slide-up 0.3s ease;
    }

    @keyframes payment-entry-slide-up {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .payment-entry-modal-header {
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

    .payment-entry-modal-title {
        font-size: 1.5em;
        font-weight: 700;
        color: #1a365d;
        margin: 0;
    }

    .payment-entry-close-btn {
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

    .payment-entry-close-btn:hover {
        background-color: #f0f4f8;
        color: #1a365d;
    }

    .payment-entry-modal-body {
        padding: 30px;
    }

    /* Toggle Section */
    .payment-entry-toggle-section {
        display: flex;
        justify-content: center;
        margin-bottom: 30px;
        padding-bottom: 25px;
        border-bottom: 1px solid #e2e8f0;
    }

    .payment-entry-toggle-group {
        display: flex;
        align-items: center;
        gap: 20px;
        background: #f8f9fa;
        padding: 15px 30px;
        border-radius: 30px;
    }

    .payment-entry-toggle-label {
        font-size: 0.95em;
        font-weight: 500;
        color: #4a5568;
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
    }

    .payment-entry-toggle-label.active {
        color: #2a4365;
        font-weight: 600;
    }

    .payment-entry-toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }

    .payment-entry-toggle-checkbox {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .payment-entry-toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e0;
        transition: all 0.3s ease;
        border-radius: 26px;
    }

    .payment-entry-toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: all 0.3s ease;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .payment-entry-toggle-checkbox:checked + .payment-entry-toggle-slider {
        background-color: #667eea;
    }

    .payment-entry-toggle-checkbox:checked + .payment-entry-toggle-slider:before {
        transform: translateX(24px);
    }

    .payment-entry-form-wrapper {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .payment-entry-content-section {
        display: none;
    }

    .payment-entry-content-section.active {
        display: block;
    }

    .payment-entry-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
    }

    .payment-entry-form-grid-5col {
        grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
    }

    .payment-entry-form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .payment-entry-form-label {
        font-size: 0.9em;
        font-weight: 600;
        color: #2a4365;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .payment-entry-required {
        color: #e53e3e;
        font-weight: 700;
    }

    .payment-entry-form-label i {
        color: #667eea;
        font-size: 0.95em;
    }

    .payment-entry-text-input,
    .payment-entry-select-field {
        padding: 12px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.95em;
        font-family: inherit;
        transition: all 0.2s ease;
        background-color: white;
    }

    .payment-entry-text-input:focus,
    .payment-entry-select-field:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        background-color: #f9fafb;
    }

    .payment-entry-text-input:disabled,
    .payment-entry-select-field:disabled {
        background-color: #f0f4f8;
        cursor: not-allowed;
        color: #718096;
    }

    .payment-entry-error-message {
        font-size: 0.8em;
        color: #e53e3e;
        display: none;
        margin-top: 4px;
    }

    .payment-entry-textarea-field {
        width: 100%;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-family: inherit;
        font-size: 14px;
        resize: vertical;
        min-height: 80px;
        transition: border-color 0.3s;
    }

    .payment-entry-textarea-field:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .payment-entry-textarea-field-small {
        width: 100%;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
        resize: vertical;
        min-height: 50px;
        transition: border-color 0.3s;
    }

    .payment-entry-textarea-field-small:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .payment-entry-error-message.show {
        display: block;
    }

    /* Form Section */
    .payment-entry-form-section {
        padding: 20px;
        background-color: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }

    .payment-entry-section-title {
        font-size: 1em;
        font-weight: 600;
        color: #2a4365;
        margin: 0 0 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .payment-entry-section-title i {
        color: #667eea;
        font-size: 1.1em;
    }

    /* File Upload */
    .payment-entry-file-upload-wrapper {
        border: 2px dashed #cbd5e0;
        border-radius: 8px;
        padding: 40px 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: #f9fafb;
    }

    .payment-entry-file-upload-wrapper:hover {
        border-color: #667eea;
        background-color: #f0f4f8;
    }

    .payment-entry-file-upload-wrapper.drag-over {
        border-color: #667eea;
        background-color: #edf2f7;
    }

    .payment-entry-file-input {
        display: none;
    }

    .payment-entry-file-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        cursor: pointer;
    }

    .payment-entry-file-icon {
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

    .payment-entry-file-main-text {
        font-size: 0.95em;
        font-weight: 600;
        color: #2a4365;
    }

    .payment-entry-file-sub-text {
        font-size: 0.85em;
        color: #718096;
    }

    .payment-entry-file-preview {
        margin-top: 15px;
        display: none;
    }

    .payment-entry-file-preview.show {
        display: block;
    }

    /* Multiple Payments Table */
    .payment-entry-multiple-payments-table {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .payment-entry-table-controls {
        display: flex;
        justify-content: flex-start;
    }

    .payment-entry-btn-add-row {
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
    }

    .payment-entry-btn-add-row:hover {
        background: #5568d3;
        transform: translateY(-2px);
    }

    .payment-entry-table-wrapper {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .payment-entry-payments-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }

    .payment-entry-payments-table thead {
        background-color: #f7fafc;
        border-bottom: 2px solid #e2e8f0;
    }

    .payment-entry-payments-table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #2a4365;
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .payment-entry-payments-table td {
        padding: 15px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.9em;
    }

    .payment-entry-payments-table tbody tr:hover {
        background-color: #f9fafb;
    }

    /* Form Actions */
    .payment-entry-form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
        margin-top: 10px;
    }

    .payment-entry-btn {
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

    .payment-entry-btn i {
        font-size: 0.9em;
    }

    .payment-entry-btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .payment-entry-btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }

    .payment-entry-btn-submit:active {
        transform: translateY(0);
    }

    .payment-entry-btn-cancel {
        background-color: #e2e8f0;
        color: #2a4365;
    }

    .payment-entry-btn-cancel:hover {
        background-color: #cbd5e0;
    }

    /* Multiple Acceptance Styles */
    .payment-entry-multiple-acceptance-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .payment-entry-acceptance-methods {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .payment-entry-acceptance-method-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 0.3fr;
        gap: 12px;
        align-items: end;
        padding: 15px;
        background-color: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .payment-entry-acceptance-method-row:hover {
        border-color: #cbd5e0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .payment-entry-btn-add-method {
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

    .payment-entry-btn-add-method:hover {
        background: #5568d3;
        transform: translateY(-2px);
    }

    .payment-entry-btn-remove-method {
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

    .payment-entry-btn-remove-method:hover {
        background: #fc8181;
        transform: scale(1.05);
    }

    .payment-entry-acceptance-method-label {
        font-size: 0.85em;
        font-weight: 600;
        color: #2a4365;
        margin-bottom: 6px;
        display: block;
    }

    .payment-entry-acceptance-method-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.9em;
        transition: all 0.2s ease;
    }

    .payment-entry-acceptance-method-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    /* Additional Entry Styles */
    .payment-entry-additional-entry {
        padding: 20px;
        margin-top: 15px;
        background-color: #f8f9fa;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        border-left: 4px solid #48bb78;
        position: relative;
    }

    .payment-entry-additional-entry-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #cbd5e0;
    }

    .payment-entry-additional-entry-title {
        font-size: 0.95em;
        font-weight: 600;
        color: #2a4365;
        margin: 0;
    }

    .payment-entry-btn-remove-entry {
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

    .payment-entry-btn-remove-entry:hover {
        background: #fc8181;
        transform: scale(1.05);
    }

    /* Media Upload Styles */
    .payment-entry-media-upload label {
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
    }

    .payment-entry-media-upload label:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .payment-entry-media-upload label:active {
        transform: translateY(0);
        box-shadow: 0 2px 6px rgba(102, 126, 234, 0.2);
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .payment-entry-form-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .payment-entry-modal-container {
            max-width: 95vw;
            max-height: 95vh;
        }

        .payment-entry-modal-header {
            padding: 15px 20px;
        }

        .payment-entry-modal-title {
            font-size: 1.2em;
        }

        .payment-entry-modal-body {
            padding: 15px;
        }

        .payment-entry-form-grid {
            grid-template-columns: 1fr;
        }

        .payment-entry-toggle-group {
            gap: 10px;
            padding: 10px 20px;
        }

        .payment-entry-form-actions {
            flex-direction: column;
        }

        .payment-entry-btn {
            width: 100%;
            justify-content: center;
        }

        .payment-entry-toggle-switch {
            width: 45px;
            height: 24px;
        }

        .payment-entry-toggle-slider:before {
            height: 18px;
            width: 18px;
        }

        .payment-entry-toggle-checkbox:checked + .payment-entry-toggle-slider:before {
            transform: translateX(21px);
        }
    }

</style>

<script>
    // Payment Entry Modal JavaScript Functions
    
    // Get modal elements
    const paymentEntryModalOverlay = document.getElementById('paymentEntryModalOverlay');
    const paymentEntryForm = document.getElementById('paymentEntryForm');
    const closePaymentEntryModalBtn = document.getElementById('closePaymentEntryModal');
    const cancelPaymentEntryBtn = document.getElementById('cancelPaymentEntryBtn');
    const submitPaymentEntryBtn = document.getElementById('submitPaymentEntryBtn');

    // Toggle elements
    const paymentModeToggle = document.getElementById('paymentModeToggle');
    const singlePaymentContent = document.getElementById('singlePaymentContent');
    const multiplePaymentContent = document.getElementById('multiplePaymentContent');
    const singlePaymentLabel = document.getElementById('singlePaymentLabel');
    const multiplePaymentLabel = document.getElementById('multiplePaymentLabel');

    // Form fields
    const paymentProjectTypeSelect = document.getElementById('paymentProjectType');
    const paymentProjectNameInput = document.getElementById('paymentProjectName');
    const paymentDateInput = document.getElementById('paymentDate');
    const paymentAmountInput = document.getElementById('paymentAmount');
    const paymentAuthorizedUserSelect = document.getElementById('paymentAuthorizedUser');
    const paymentModeSelect = document.getElementById('paymentMode');
    const paymentProofImageInput = document.getElementById('paymentProofImage');
    const paymentProofUploadArea = document.getElementById('paymentProofUploadArea');

    // Multiple payments
    const addPaymentRowBtn = document.getElementById('addPaymentRowBtn');
    const paymentRowsContainer = document.getElementById('paymentRowsContainer');

    // Open Payment Entry Modal
    function openPaymentEntryModal() {
        if (paymentEntryModalOverlay) {
            paymentEntryModalOverlay.classList.add('active');
        }
        document.body.style.overflow = 'hidden';
        initializeFormDefaults();
    }

    // Close Payment Entry Modal
    function closePaymentEntryModal() {
        if (paymentEntryModalOverlay) {
            paymentEntryModalOverlay.classList.remove('active');
        }
        document.body.style.overflow = 'auto';
        if (paymentEntryForm) {
            paymentEntryForm.reset();
        }
        if (singlePaymentContent) {
            singlePaymentContent.classList.add('active');
        }
        if (multiplePaymentContent) {
            multiplePaymentContent.classList.remove('active');
        }
        if (multipleAcceptanceContent) {
            multipleAcceptanceContent.classList.remove('active');
        }
        if (paymentModeToggle) {
            paymentModeToggle.checked = false;
        }
        updateToggleLabels();
        // Clear acceptance methods
        if (acceptanceMethodsContainer) {
            acceptanceMethodsContainer.innerHTML = '';
        }
        // Clear additional entries
        if (additionalEntriesContainer) {
            additionalEntriesContainer.innerHTML = '';
            entryCount = 0;
        }
    }

    // Make functions globally accessible
    window.openPaymentEntryModal = openPaymentEntryModal;
    window.closePaymentEntryModal = closePaymentEntryModal;

    // Initialize form defaults
    function initializeFormDefaults() {
        const today = new Date().toISOString().split('T')[0];
        if (paymentDateInput) {
            paymentDateInput.value = today;
        }

        // Load authorized users
        loadAuthorizedUsers();
        
        // Load vendor categories for all type dropdowns (this is called once on modal open)
        loadVendorCategoriesForTypeDropdown();
    }

    // Event listener for close button
    if (closePaymentEntryModalBtn) {
        closePaymentEntryModalBtn.addEventListener('click', closePaymentEntryModal);
    }

    if (cancelPaymentEntryBtn) {
        cancelPaymentEntryBtn.addEventListener('click', closePaymentEntryModal);
    }

    // Close modal when clicking on overlay
    if (paymentEntryModalOverlay) {
        paymentEntryModalOverlay.addEventListener('click', function(event) {
            if (event.target === paymentEntryModalOverlay) {
                closePaymentEntryModal();
            }
        });
    }

    // Toggle between Single and Multiple Payments
    if (paymentModeToggle) {
        paymentModeToggle.addEventListener('change', function() {
            if (this.checked) {
                singlePaymentContent.classList.remove('active');
                multiplePaymentContent.classList.add('active');
            } else {
                singlePaymentContent.classList.add('active');
                multiplePaymentContent.classList.remove('active');
            }
            updateToggleLabels();
        });
    }

    // Update toggle labels styling
    function updateToggleLabels() {
        if (paymentModeToggle && paymentModeToggle.checked) {
            if (singlePaymentLabel) {
                singlePaymentLabel.classList.remove('active');
            }
            if (multiplePaymentLabel) {
                multiplePaymentLabel.classList.add('active');
            }
        } else {
            if (singlePaymentLabel) {
                singlePaymentLabel.classList.add('active');
            }
            if (multiplePaymentLabel) {
                multiplePaymentLabel.classList.remove('active');
            }
        }
    }

    // Handle project type change
    if (paymentProjectTypeSelect) {
        paymentProjectTypeSelect.addEventListener('change', function() {
            const projectType = this.value;
            paymentProjectNameInput.disabled = !projectType;
            
            if (projectType) {
                paymentProjectNameInput.placeholder = 'Loading projects...';
                loadProjectsByType(projectType);
            } else {
                paymentProjectNameInput.placeholder = 'First select project type';
                paymentProjectNameInput.value = '';
                paymentProjectNameInput.innerHTML = '';
            }
        });
    }

    // Load projects by type
    function loadProjectsByType(projectType) {
        fetch(`get_projects_by_type.php?projectType=${encodeURIComponent(projectType)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.projects.length > 0) {
                    let html = '<option value="">Select Project Name</option>';
                    data.projects.forEach(project => {
                        html += `<option value="${project.id}" data-title="${project.title}">${project.title}</option>`;
                    });
                    paymentProjectNameInput.innerHTML = html;
                    paymentProjectNameInput.placeholder = 'Select Project Name';
                } else {
                    paymentProjectNameInput.innerHTML = '<option value="">No projects available</option>';
                    paymentProjectNameInput.placeholder = 'No projects found';
                }
            })
            .catch(error => {
                console.error('Error loading projects:', error);
                paymentProjectNameInput.innerHTML = '<option value="">Error loading projects</option>';
                paymentProjectNameInput.placeholder = 'Error loading projects';
            });
    }

    // Handle project name change
    if (paymentProjectNameInput) {
        paymentProjectNameInput.addEventListener('change', function() {
            // The value is already set by the select element, no need to modify it
            // Just keep the selected value as-is
        });
    }

    // Load authorized users
    function loadAuthorizedUsers() {
        fetch('get_active_users.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users.length > 0) {
                    let html = '<option value="">Select Authorized User</option>';
                    data.users.forEach(user => {
                        html += `<option value="${user.id}" data-username="${user.username}">${user.username}</option>`;
                    });
                    paymentAuthorizedUserSelect.innerHTML = html;
                    
                    // Add event listener to main authorized user select to update all entry fields (one-directional)
                    paymentAuthorizedUserSelect.addEventListener('change', function() {
                        if (this.value) {
                            // Update all entry "Paid Via" fields with the main field's value
                            const allEntries = additionalEntriesContainer.querySelectorAll('.payment-entry-additional-entry');
                            allEntries.forEach(entry => {
                                const entryPaidViaSelect = entry.querySelector('.entry-paid-via');
                                if (entryPaidViaSelect) {
                                    entryPaidViaSelect.value = this.value;
                                }
                            });
                        }
                    });
                } else {
                    paymentAuthorizedUserSelect.innerHTML = '<option value="">No active users found</option>';
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
                paymentAuthorizedUserSelect.innerHTML = '<option value="">Error loading users</option>';
            });
    }

    // Load authorized users for entry "Paid Via" field
    function loadAuthorizedUsersForEntry(selectElement) {
        if (!selectElement) return;

        selectElement.innerHTML = '<option value="">Loading users...</option>';
        selectElement.disabled = true;

        fetch('get_active_users.php')
            .then(response => response.json())
            .then(data => {
                selectElement.disabled = false;
                if (data.success && data.users.length > 0) {
                    let html = '<option value="">Select User</option>';
                    data.users.forEach(user => {
                        html += `<option value="${user.id}" data-username="${user.username}">${user.username}</option>`;
                    });
                    selectElement.innerHTML = html;
                } else {
                    selectElement.innerHTML = '<option value="">No active users found</option>';
                }
            })
            .catch(error => {
                console.error('Error loading users for entry:', error);
                selectElement.disabled = false;
                selectElement.innerHTML = '<option value="">Error loading users</option>';
            });
    }

    // Load vendor categories and populate OptGroups in Type dropdowns
    function loadVendorCategoriesForTypeDropdown() {
        fetch('get_vendor_categories_for_entry.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.categories) {
                    const categories = data.categories;

                    // Find ALL entry Type selects (both in main entry and additional entries)
                    const allTypeSelects = document.querySelectorAll('.entry-type');
                    
                    allTypeSelects.forEach(typeSelect => {
                        // Only process OptGroups that are not Labour (Labour options are static)
                        const optGroups = typeSelect.querySelectorAll('optgroup:not([label="Labour"])');
                        
                        optGroups.forEach(optGroup => {
                            const label = optGroup.getAttribute('label');
                            
                            // Check if this label exists in categories
                            if (categories[label]) {
                                const categoryArray = categories[label];
                                
                                // Clear existing options (except disabled divider if present)
                                optGroup.innerHTML = '';
                                
                                categoryArray.forEach(categoryType => {
                                    const option = document.createElement('option');
                                    option.value = categoryType;
                                    option.textContent = categoryType;
                                    optGroup.appendChild(option);
                                });
                            }
                        });
                    });
                } else {
                    console.error('Error loading vendor categories:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error fetching vendor categories:', error);
            });
    }

    // Load vendor categories for a specific entry dropdown only
    function loadVendorCategoriesForEntryDropdown(typeSelect) {
        if (!typeSelect) return;

        fetch('get_vendor_categories_for_entry.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.categories) {
                    const categories = data.categories;

                    // Only process OptGroups that are not Labour (Labour options are static)
                    const optGroups = typeSelect.querySelectorAll('optgroup:not([label="Labour"])');
                    
                    optGroups.forEach(optGroup => {
                        const label = optGroup.getAttribute('label');
                        
                        // Check if this label exists in categories
                        if (categories[label]) {
                            const categoryArray = categories[label];
                            
                            // Clear existing options
                            optGroup.innerHTML = '';
                            
                            categoryArray.forEach(categoryType => {
                                const option = document.createElement('option');
                                option.value = categoryType;
                                option.textContent = categoryType;
                                optGroup.appendChild(option);
                            });
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching vendor categories:', error);
            });
    }

    // Load recipients based on selected type
    function loadRecipientsByType(type) {
        const recipientSelect = document.getElementById('paymentRecipient');
        if (!recipientSelect) return;

        if (!type) {
            recipientSelect.innerHTML = '<option value="">Select Recipient</option>';
            return;
        }

        recipientSelect.innerHTML = '<option value="">Loading...</option>';
        recipientSelect.disabled = true;

        // Determine which endpoint to call based on type
        let endpoint = '';
        
        if (type === 'labour') {
            endpoint = 'get_labour_recipients.php?type=labour';
        } else if (type === 'labour_skilled') {
            endpoint = 'get_vendor_recipients.php?type=labour_skilled';
        } else if (type === 'material_steel') {
            endpoint = 'get_vendor_recipients.php?type=material_steel';
        } else if (type === 'material_bricks') {
            endpoint = 'get_vendor_recipients.php?type=material_bricks';
        } else if (type === 'supplier_cement') {
            endpoint = 'get_vendor_recipients.php?type=supplier_cement';
        } else if (type === 'supplier_sand_aggregate') {
            endpoint = 'get_vendor_recipients.php?type=supplier_sand_aggregate';
        }

        if (!endpoint) {
            recipientSelect.innerHTML = '<option value="">Select Recipient</option>';
            recipientSelect.disabled = false;
            return;
        }

        console.log('Loading recipients from endpoint:', endpoint);

        fetch(endpoint)
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                recipientSelect.disabled = false;
                if (data.success && data.recipients && data.recipients.length > 0) {
                    let html = '<option value="">Select Recipient</option>';
                    data.recipients.forEach(recipient => {
                        html += `<option value="${recipient.id}">${recipient.name}</option>`;
                    });
                    recipientSelect.innerHTML = html;
                    console.log('Populated ' + data.recipients.length + ' recipients');
                } else {
                    recipientSelect.innerHTML = '<option value="">No recipients found</option>';
                    console.log('No recipients found in response');
                }
            })
            .catch(error => {
                console.error('Error loading recipients:', error);
                recipientSelect.disabled = false;
                recipientSelect.innerHTML = '<option value="">Error loading recipients</option>';
            });
    }

    // Event listeners for additional fields
    const paymentAmount = document.getElementById('paymentAmount');

    if (paymentAmount) {
        paymentAmount.addEventListener('change', function() {
            // Additional fields sync removed since we now use Add More Entry
        });
        paymentAmount.addEventListener('input', function() {
            // Additional fields sync removed since we now use Add More Entry
        });
    }

    // Multiple Acceptance functionality
    const multipleAcceptanceContent = document.getElementById('multipleAcceptanceContent');
    const acceptanceMethodsContainer = document.getElementById('acceptanceMethodsContainer');
    const addAcceptanceMethodBtn = document.getElementById('addAcceptanceMethodBtn');

    // Payment modes available for multiple acceptance
    const acceptancePaymentModes = [
        { value: 'cash', label: 'Cash' },
        { value: 'cheque', label: 'Cheque' },
        { value: 'bank_transfer', label: 'Bank Transfer' },
        { value: 'credit_card', label: 'Credit Card' },
        { value: 'online', label: 'Online Payment' },
        { value: 'upi', label: 'UPI' }
    ];

    // Handle payment mode change to show/hide multiple acceptance
    if (paymentModeSelect) {
        paymentModeSelect.addEventListener('change', function() {
            if (this.value === 'multiple_acceptance') {
                // Show multiple acceptance section
                if (multipleAcceptanceContent) {
                    multipleAcceptanceContent.classList.add('active');
                    // Initialize with one empty row if container is empty
                    if (acceptanceMethodsContainer.children.length === 0) {
                        addAcceptanceMethod();
                    }
                }
            } else {
                // Hide multiple acceptance section
                if (multipleAcceptanceContent) {
                    multipleAcceptanceContent.classList.remove('active');
                }
            }
        });
    }

    // Add acceptance method row
    function addAcceptanceMethod() {
        const rowIndex = acceptanceMethodsContainer.children.length;
        const methodRow = document.createElement('div');
        methodRow.className = 'payment-entry-acceptance-method-row';
        methodRow.dataset.rowIndex = rowIndex;
        methodRow.style.cssText = 'display: grid; grid-template-columns: 2fr 2fr 1.5fr 1.5fr auto; gap: 15px; margin-bottom: 12px; padding: 15px; background: white; border-radius: 8px; border: 1px solid #e2e8f0; align-items: center; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);';

        let modeOptions = '<option value="">Select Payment Method</option>';
        acceptancePaymentModes.forEach(mode => {
            modeOptions += `<option value="${mode.value}">${mode.label}</option>`;
        });

        const mediaId = `acceptance_media_${rowIndex}`;

        methodRow.innerHTML = `
            <div>
                <select class="payment-entry-acceptance-method-input acceptance-method-select" data-row="${rowIndex}" style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9em; background-color: white; color: #2d3748;" required>
                    ${modeOptions}
                </select>
            </div>
            <div>
                <input type="number" class="payment-entry-acceptance-method-input acceptance-method-amount" data-row="${rowIndex}" placeholder="0.00" step="0.01" min="0" style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9em;" required>
            </div>
            <div>
                <input type="text" class="payment-entry-acceptance-method-input acceptance-method-reference" data-row="${rowIndex}" placeholder="CHQ123456" style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9em;">
            </div>
            <div>
                <label for="${mediaId}" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 6px; cursor: pointer; font-size: 0.85em; font-weight: 600; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);" title="Upload supporting document">
                    <i class="fas fa-paperclip" style="font-size: 0.9em;"></i>
                    <span>Upload</span>
                </label>
                <input type="file" id="${mediaId}" data-row="${rowIndex}" accept=".pdf,.jpg,.jpeg,.png,.mp4,.mov,.avi" style="display: none;" class="acceptance-method-media">
                <div class="acceptance-media-preview" data-row="${rowIndex}" style="margin-top: 8px; font-size: 0.75em; color: #718096;"></div>
            </div>
            <button type="button" class="payment-entry-btn-remove-method" data-row="${rowIndex}" style="background: transparent; color: #e53e3e; border: none; padding: 6px; cursor: pointer; opacity: 0.7; transition: all 0.2s ease;">
                <i class="fas fa-trash" style="font-size: 1em;"></i>
            </button>
        `;

        acceptanceMethodsContainer.appendChild(methodRow);

        // Add event listeners to the new row
        const amountInput = methodRow.querySelector('.acceptance-method-amount');
        const removeBtn = methodRow.querySelector('.payment-entry-btn-remove-method');
        const mediaInput = methodRow.querySelector('.acceptance-method-media');

        if (amountInput) {
            amountInput.addEventListener('input', updateAcceptanceTotals);
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                methodRow.remove();
                updateAcceptanceTotals();
            });
        }

        if (mediaInput) {
            mediaInput.addEventListener('change', function(e) {
                handleAcceptanceMediaUpload(rowIndex, this.files[0]);
            });
        }

        // Add hover effect
        methodRow.addEventListener('mouseenter', function() {
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
        });

        methodRow.addEventListener('mouseleave', function() {
            this.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';
        });
    }

    // Handle acceptance media upload
    function handleAcceptanceMediaUpload(rowIndex, file) {
        const previewDiv = document.querySelector(`.acceptance-media-preview[data-row="${rowIndex}"]`);
        if (!previewDiv) return;

        if (!file) {
            previewDiv.innerHTML = '';
            return;
        }

        const maxSize = 50 * 1024 * 1024; // 50MB
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'video/mp4', 'video/quicktime', 'video/x-msvideo'];

        if (file.size > maxSize) {
            previewDiv.innerHTML = `<span style="color: #e53e3e;"><i class="fas fa-exclamation-circle"></i> File too large (max 50MB)</span>`;
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            previewDiv.innerHTML = `<span style="color: #e53e3e;"><i class="fas fa-exclamation-circle"></i> Invalid file type</span>`;
            return;
        }

        // Show file info
        const fileSize = (file.size / 1024).toFixed(2);
        previewDiv.innerHTML = `<span style="color: #22863a;"><i class="fas fa-check-circle"></i> ${file.name} (${fileSize} KB)</span>`;
    }

    // Update acceptance totals
    function updateAcceptanceTotals() {
        const amountInputs = acceptanceMethodsContainer.querySelectorAll('.acceptance-method-amount');
        let totalReceived = 0;

        amountInputs.forEach(input => {
            if (input.value) {
                totalReceived += parseFloat(input.value) || 0;
            }
        });

        const totalAmount = parseFloat(paymentAmountInput.value) || 0;
        const difference = totalAmount - totalReceived;
        const receivedSpan = document.getElementById('acceptanceReceivedAmount');
        const totalSpan = document.getElementById('acceptanceTotalAmount');
        const differenceSpan = document.getElementById('acceptanceDifference');
        const warningDiv = document.getElementById('acceptanceWarning');

        if (totalSpan) totalSpan.textContent = totalAmount.toFixed(2);
        if (receivedSpan) receivedSpan.textContent = totalReceived.toFixed(2);
        if (differenceSpan) {
            differenceSpan.textContent = Math.abs(difference).toFixed(2);
            differenceSpan.parentElement.style.color = difference === 0 ? '#22863a' : '#e53e3e';
        }

        // Show warning if amounts don't match
        if (warningDiv && totalAmount > 0) {
            if (Math.abs(totalAmount - totalReceived) > 0.01) {
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }
        }
    }

    // Add event listener to add method button
    if (addAcceptanceMethodBtn) {
        addAcceptanceMethodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addAcceptanceMethod();
        });
    }

    // Update acceptance totals when main amount changes
    if (paymentAmountInput) {
        paymentAmountInput.addEventListener('change', function() {
            updateAcceptanceTotals();
        });
        paymentAmountInput.addEventListener('input', function() {
            updateAcceptanceTotals();
        });
    }

    // Handle file upload with drag and drop
    if (paymentProofUploadArea) {
        paymentProofUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        paymentProofUploadArea.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        paymentProofUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            if (e.dataTransfer.files.length > 0) {
                paymentProofImageInput.files = e.dataTransfer.files;
                handlePaymentProofFileUpload(e.dataTransfer.files[0]);
            }
        });
    }

    if (paymentProofImageInput) {
        paymentProofImageInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handlePaymentProofFileUpload(this.files[0]);
            }
        });
    }

    // Handle file upload
    function handlePaymentProofFileUpload(file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        
        const errorElement = document.getElementById('paymentProofError');
        const previewElement = document.getElementById('paymentProofPreview');
        
        errorElement.classList.remove('show');
        previewElement.classList.remove('show');
        previewElement.innerHTML = '';
        
        if (file) {
            if (file.size > maxSize) {
                errorElement.textContent = 'File size exceeds 5MB limit';
                errorElement.classList.add('show');
                paymentProofImageInput.value = '';
                return;
            }
            
            if (!allowedTypes.includes(file.type)) {
                errorElement.textContent = 'Only PDF, JPG, and PNG files are allowed';
                errorElement.classList.add('show');
                paymentProofImageInput.value = '';
                return;
            }
            
            // Show file preview
            previewElement.innerHTML = `
                <div style="padding: 10px; background-color: #c6f6d5; border-radius: 6px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="color: #22543d; font-size: 1.2em;"></i>
                    <span style="color: #22543d;"><strong>${file.name}</strong> (${(file.size / 1024).toFixed(2)} KB)</span>
                </div>
            `;
            previewElement.classList.add('show');
        }
    }

    // Handle form submission
    if (paymentEntryForm) {
        paymentEntryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (paymentModeToggle && paymentModeToggle.checked) {
                // Multiple payments submission
                submitMultiplePayments();
            } else {
                // Single payment submission
                submitSinglePayment();
            }
        });
    }

    // Validate that total entry amounts don't exceed main payment amount
    function validateEntryAmounts() {
        const mainAmount = parseFloat(paymentAmountInput.value) || 0;
        const entries = additionalEntriesContainer.querySelectorAll('.payment-entry-additional-entry');
        let totalEntryAmount = 0;
        let hasError = false;

        entries.forEach(entry => {
            const entryAmountInput = entry.querySelector('.entry-amount');
            if (entryAmountInput && entryAmountInput.value) {
                const entryAmount = parseFloat(entryAmountInput.value) || 0;
                totalEntryAmount += entryAmount;

                // Check if individual entry exceeds main amount
                if (entryAmount > mainAmount) {
                    entryAmountInput.style.borderColor = '#e53e3e';
                    entryAmountInput.style.backgroundColor = '#fff5f5';
                    hasError = true;
                } else {
                    entryAmountInput.style.borderColor = '#e2e8f0';
                    entryAmountInput.style.backgroundColor = 'white';
                }
            }
        });

        // Show warning if total entries exceed main amount
        if (totalEntryAmount > mainAmount && mainAmount > 0) {
            const warning = document.createElement('div');
            warning.className = 'entry-total-validation-warning';
            
            // Remove previous warning if exists
            const prevWarning = additionalEntriesContainer.querySelector('.entry-total-validation-warning');
            if (prevWarning) {
                prevWarning.remove();
            }

            const excess = totalEntryAmount - mainAmount;
            warning.style.cssText = 'padding: 12px 15px; background-color: #fff5f5; border-left: 4px solid #e53e3e; border-radius: 4px; margin-bottom: 15px; color: #e53e3e; font-size: 0.9em; font-weight: 600; display: flex; align-items: center; gap: 8px;';
            warning.innerHTML = `
                <i class="fas fa-exclamation-triangle" style="font-size: 1em;"></i>
                <span>Total entry amount (₹${totalEntryAmount.toFixed(2)}) exceeds main payment amount (₹${mainAmount.toFixed(2)}) by ₹${excess.toFixed(2)}</span>
            `;
            
            additionalEntriesContainer.insertBefore(warning, additionalEntriesContainer.firstChild);
            return false;
        } else {
            // Remove warning if exists and amounts are valid
            const prevWarning = additionalEntriesContainer.querySelector('.entry-total-validation-warning');
            if (prevWarning) {
                prevWarning.remove();
            }
            return true;
        }
    }

    // Submit single payment
    function submitSinglePayment() {
        const projectType = paymentProjectTypeSelect.value;
        const projectName = paymentProjectNameInput.value;
        const amount = paymentAmountInput.value;
        const paymentDate = paymentDateInput.value;
        const authorizedUser = paymentAuthorizedUserSelect.value;
        const paymentMode = paymentModeSelect.value;

        let isValid = true;

        if (!projectType) {
            showError('paymentProjectTypeError', 'Please select project type');
            isValid = false;
        }

        if (!projectName) {
            showError('paymentProjectNameError', 'Please enter project name');
            isValid = false;
        }

        if (!amount || parseFloat(amount) <= 0) {
            showError('paymentAmountError', 'Please enter valid amount');
            isValid = false;
        }

        if (!paymentDate) {
            showError('paymentDateError', 'Please select payment date');
            isValid = false;
        }

        if (!authorizedUser) {
            showError('paymentAuthorizedUserError', 'Please select authorized user');
            isValid = false;
        }

        if (!paymentMode) {
            showError('paymentModeError', 'Please select payment mode');
            isValid = false;
        }

        // Validate multiple acceptance if selected
        if (paymentMode === 'multiple_acceptance') {
            const methods = acceptanceMethodsContainer.querySelectorAll('.payment-entry-acceptance-method-row');
            if (methods.length === 0) {
                alert('Please add at least one payment method for Multiple Acceptance');
                isValid = false;
            } else {
                let hasValidMethod = false;
                methods.forEach(method => {
                    const modeSelect = method.querySelector('.acceptance-method-select');
                    const amountInput = method.querySelector('.acceptance-method-amount');
                    if (modeSelect && modeSelect.value && amountInput && amountInput.value) {
                        hasValidMethod = true;
                    }
                });
                if (!hasValidMethod) {
                    alert('Please fill in all payment methods and amounts');
                    isValid = false;
                }
            }
        }

        // Validate that total entry amounts don't exceed main payment amount
        const entryValidation = validateEntryAmounts();
        if (!entryValidation) {
            alert('Total entry amounts cannot exceed the main payment amount. Please adjust entry amounts.');
            isValid = false;
        }

        if (!isValid) {
            return;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('paymentType', 'single');
        formData.append('projectType', projectType);
        formData.append('projectName', projectName);
        formData.append('projectId', paymentProjectNameInput.value); // Add project ID
        formData.append('amount', amount);
        formData.append('paymentDate', paymentDate);
        formData.append('authorizedUserId', authorizedUser);
        formData.append('paymentMode', paymentMode);
        
        // Add payment proof image only if file is selected (optional)
        if (paymentProofImageInput.files && paymentProofImageInput.files.length > 0) {
            formData.append('proofImage', paymentProofImageInput.files[0]);
        }

        // Add multiple acceptance data if applicable
        if (paymentMode === 'multiple_acceptance') {
            const methods = acceptanceMethodsContainer.querySelectorAll('.payment-entry-acceptance-method-row');
            const acceptanceMethods = [];
            let acceptanceFileIndex = 0;
            
            methods.forEach((method, index) => {
                const modeSelect = method.querySelector('.acceptance-method-select');
                const amountInput = method.querySelector('.acceptance-method-amount');
                const referenceInput = method.querySelector('.acceptance-method-reference');
                const mediaInput = method.querySelector('.acceptance-method-media');
                
                if (modeSelect.value && amountInput.value) {
                    const methodData = {
                        method: modeSelect.value,
                        amount: amountInput.value,
                        reference: referenceInput.value || ''
                    };

                    // Add media file if present
                    if (mediaInput && mediaInput.files.length > 0) {
                        const file = mediaInput.files[0];
                        formData.append(`acceptanceMedia_${acceptanceFileIndex}`, file);
                        methodData.mediaFile = `acceptanceMedia_${acceptanceFileIndex}`;
                        acceptanceFileIndex++;
                    }

                    acceptanceMethods.push(methodData);
                }
            });
            
            formData.append('multipleAcceptance', JSON.stringify(acceptanceMethods));
        }

        // Collect additional entries
        const additionalEntries = [];
        const entryDivs = additionalEntriesContainer.querySelectorAll('.payment-entry-additional-entry');
        
        entryDivs.forEach((entry, entry_index) => {
            const typeSelect = entry.querySelector('.entry-type');
            const recipientSelect = entry.querySelector('.entry-recipient');
            const descriptionInput = entry.querySelector('.entry-description');
            const amountInput = entry.querySelector('.entry-amount');
            const modeSelect = entry.querySelector('.entry-mode');
            const paidViaSelect = entry.querySelector('.entry-paid-via');
            const mediaFileInput = entry.querySelector('.entry-media-file');

            if (typeSelect && typeSelect.value && amountInput && amountInput.value) {
                // Get recipient name from selected option
                const recipientOption = recipientSelect.options[recipientSelect.selectedIndex];
                const recipientName = recipientOption ? recipientOption.text : '';
                
                const entryData = {
                    type: typeSelect.value,
                    recipientId: recipientSelect.value || '',
                    recipientName: recipientName || '',
                    description: descriptionInput.value || '',
                    amount: amountInput.value,
                    paymentMode: modeSelect.value || '',
                    paidViaUserId: paidViaSelect && paidViaSelect.value ? paidViaSelect.value : null
                };

                // Add multiple acceptance methods if selected
                if (modeSelect.value === 'multiple_acceptance') {
                    const entryId = entry.id;
                    const methodRows = entry.querySelectorAll('.entry-acceptance-method-row');
                    const acceptanceMethods = [];
                    
                    methodRows.forEach((methodRow, method_index) => {
                        const methodSelect = methodRow.querySelector('.entry-method-select');
                        const amountInputMethod = methodRow.querySelector('.entry-method-amount');
                        const mediaInputMethod = methodRow.querySelector('.entry-method-media');
                        
                        if (methodSelect && methodSelect.value && amountInputMethod && amountInputMethod.value) {
                            const methodData = {
                                method: methodSelect.value,
                                amount: amountInputMethod.value
                            };

                            // Add media file if present - Use correct file key matching backend
                            if (mediaInputMethod && mediaInputMethod.files.length > 0) {
                                const file = mediaInputMethod.files[0];
                                const fileKey = `entryMethodMedia_${entry_index}_${method_index}`;
                                formData.append(fileKey, file);
                                methodData.mediaFile = fileKey;
                            }

                            acceptanceMethods.push(methodData);
                        }
                    });
                    
                    if (acceptanceMethods.length > 0) {
                        entryData.acceptanceMethods = acceptanceMethods;
                    }
                }

                // Add entry media file if present
                if (mediaFileInput && mediaFileInput.files.length > 0) {
                    const file = mediaFileInput.files[0];
                    const fileKey = `entryMedia_${entry_index}`;
                    formData.append(fileKey, file);
                    entryData.mediaFile = fileKey;
                }

                additionalEntries.push(entryData);
            }
        });

        if (additionalEntries.length > 0) {
            formData.append('additionalEntries', JSON.stringify(additionalEntries));
        }

        // Submit form
        submitPaymentEntryBtn.disabled = true;
        submitPaymentEntryBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('handlers/payment_entry_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Payment entry added successfully!');
                closePaymentEntryModal();
            } else {
                alert('Error: ' + (data.message || 'Failed to add payment entry'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding payment entry: ' + error.message);
        })
        .finally(() => {
            submitPaymentEntryBtn.disabled = false;
            submitPaymentEntryBtn.innerHTML = '<i class="fas fa-check"></i> Save Payment Entry';
        });
    }

    // Submit multiple payments
    function submitMultiplePayments() {
        const rows = paymentRowsContainer.querySelectorAll('tr');
        
        if (rows.length === 0) {
            alert('Please add at least one payment row');
            return;
        }

        alert('Multiple payments feature will be implemented soon');
    }

    // Add More Entry functionality
    const additionalEntriesContainer = document.getElementById('additionalEntriesContainer');
    let entryCount = 0;
    let addMoreEntryBtn = null;

    // Create Add More Entry button
    function createAddMoreEntryButton() {
        if (addMoreEntryBtn) {
            addMoreEntryBtn.remove();
        }
        addMoreEntryBtn = document.createElement('div');
        addMoreEntryBtn.style.display = 'flex';
        addMoreEntryBtn.style.justifyContent = 'flex-start';
        addMoreEntryBtn.style.marginTop = '20px';
        addMoreEntryBtn.style.gap = '10px';
        addMoreEntryBtn.id = 'addMoreEntryBtnWrapper';
        addMoreEntryBtn.innerHTML = `
            <button type="button" class="payment-entry-btn" id="addMoreEntryBtnElement" style="background: #48bb78; color: white; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-plus"></i> Add More Entry
            </button>
        `;
        additionalEntriesContainer.appendChild(addMoreEntryBtn);
        
        const btnElement = addMoreEntryBtn.querySelector('#addMoreEntryBtnElement');
        if (btnElement) {
            btnElement.addEventListener('click', function(e) {
                e.preventDefault();
                addMoreEntry();
            });
        }
    }

    // Initialize button on page load
    document.addEventListener('DOMContentLoaded', function() {
        createAddMoreEntryButton();
    });

    function addMoreEntry() {
        // Get actual count of existing entries to reset numbering
        const existingEntries = additionalEntriesContainer.querySelectorAll('.payment-entry-additional-entry');
        const nextEntryNumber = existingEntries.length + 1;
        
        entryCount = nextEntryNumber;
        const entryId = `entry_${entryCount}`;
        const entryDiv = document.createElement('div');
        entryDiv.className = 'payment-entry-additional-entry';
        entryDiv.id = entryId;
        entryDiv.innerHTML = `
            <div class="payment-entry-additional-entry-header">
                <h4 class="payment-entry-additional-entry-title">Entry #${nextEntryNumber}</h4>
                <button type="button" class="payment-entry-btn-remove-entry" onclick="removeEntry('${entryId}')">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="payment-entry-form-grid payment-entry-form-grid-3col">
                <!-- Type -->
                <div class="payment-entry-form-group">
                    <label class="payment-entry-form-label">
                        <i class="fas fa-tag"></i> Type <span class="payment-entry-required">*</span>
                    </label>
                    <select class="payment-entry-select-field entry-type" data-entry="${entryId}">
                        <option value="">Select Type</option>
                        <optgroup label="Labour">
                            <option value="Permanent">Permanent</option>
                            <option value="Temporary">Temporary</option>
                            <option value="Vendor">Vendor</option>
                        </optgroup>
                        <optgroup label="Material Supplier">
                            <!-- Options will be loaded dynamically -->
                        </optgroup>
                        <optgroup label="Material Contractor">
                            <!-- Options will be loaded dynamically -->
                        </optgroup>
                        <optgroup label="Labour Contractor">
                            <!-- Options will be loaded dynamically -->
                        </optgroup>
                    </select>
                </div>

                <!-- Recipient -->
                <div class="payment-entry-form-group">
                    <label class="payment-entry-form-label">
                        <i class="fas fa-user"></i> To <span class="payment-entry-required">*</span>
                    </label>
                    <select class="payment-entry-select-field entry-recipient" data-entry="${entryId}">
                        <option value="">Select Recipient</option>
                        <option value="add_labour" disabled>─────────────────</option>
                        <option value="add_labour">+ Add Labour</option>
                    </select>
                </div>

                <!-- Paid Via -->
                <div class="payment-entry-form-group">
                    <label class="payment-entry-form-label">
                        <i class="fas fa-user-check"></i> Payment Done Via <span class="payment-entry-required">*</span>
                    </label>
                    <select class="payment-entry-select-field entry-paid-via" data-entry="${entryId}">
                        <option value="">Select User</option>
                    </select>
                </div>

                <!-- Description -->
                <div class="payment-entry-form-group">
                    <label class="payment-entry-form-label">
                        <i class="fas fa-clipboard"></i> For
                    </label>
                    <textarea class="payment-entry-textarea-field-small entry-description" data-entry="${entryId}" placeholder="Describe what this payment is for..."></textarea>
                </div>

                <!-- Amount -->
                <div class="payment-entry-form-group">
                    <label class="payment-entry-form-label">
                        <i class="fas fa-rupee-sign"></i> Amount <span class="payment-entry-required">*</span>
                    </label>
                    <input type="number" class="payment-entry-text-input entry-amount" data-entry="${entryId}" placeholder="0.00" step="0.01" min="0">
                </div>

                <!-- Payment Mode -->
                <div class="payment-entry-form-group">
                    <label class="payment-entry-form-label">
                        <i class="fas fa-credit-card"></i> Mode <span class="payment-entry-required">*</span>
                    </label>
                    <select class="payment-entry-select-field entry-mode" data-entry="${entryId}">
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
                </div>
            </div>

            <!-- Multiple Acceptance Section for Entry -->
            <div class="entry-multiple-acceptance-section" data-entry="${entryId}" style="display: none; margin-top: 15px; padding: 0;">
                <div class="entry-acceptance-methods-container" data-entry="${entryId}">
                    <!-- Acceptance method rows will be added here -->
                </div>

                <button type="button" class="payment-entry-btn payment-entry-btn-add-entry-method" data-entry="${entryId}" style="margin-top: 8px; background: none; color: #667eea; padding: 6px 0; border: none; cursor: pointer; font-size: 0.85em; font-weight: 600; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease;">
                    <i class="fas fa-plus" style="font-size: 0.8em;"></i> Add Method
                </button>

                <!-- Entry Acceptance Summary -->
                <div class="entry-acceptance-summary" data-entry="${entryId}" style="margin-top: 12px; padding: 10px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
                    <div>
                        <label style="font-size: 0.75em; color: #a0aec0; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Entry Amount</label>
                        <div style="font-size: 1em; font-weight: 600; color: #2d3748; margin-top: 4px;">₹ <span class="entry-total-amount" data-entry="${entryId}">0.00</span></div>
                    </div>
                    <div>
                        <label style="font-size: 0.75em; color: #a0aec0; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Accepted Amount</label>
                        <div style="font-size: 1em; font-weight: 600; color: #2d3748; margin-top: 4px;">₹ <span class="entry-received-amount" data-entry="${entryId}">0.00</span></div>
                    </div>
                </div>
                <div class="entry-acceptance-warning" data-entry="${entryId}" style="margin-top: 8px; padding: 8px 10px; background-color: transparent; border-left: 2px solid #e53e3e; border-radius: 0; display: none;">
                    <span style="color: #e53e3e; font-size: 0.8em;"><i class="fas fa-exclamation-circle"></i> Amount mismatch</span>
                </div>
            </div>

            <!-- Media Upload -->
            <div class="payment-entry-media-upload" style="margin-top: 15px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <label for="entry_media_${entryId}" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 6px; cursor: pointer; font-size: 0.85em; font-weight: 600; transition: all 0.2s ease; white-space: nowrap; border: none;">
                        <i class="fas fa-paperclip" style="font-size: 0.9em;"></i>
                        <span>Attach File</span>
                    </label>
                    <input type="file" class="entry-media-file" id="entry_media_${entryId}" data-entry="${entryId}" accept=".pdf,.jpg,.jpeg,.png,.mp4,.mov,.avi" style="display: none;">
                    <div class="entry-media-preview" data-entry="${entryId}" style="font-size: 0.8em; color: #718096; flex: 1;"></div>
                </div>
            </div>
        `;

        // Insert entry before button
        if (addMoreEntryBtn) {
            additionalEntriesContainer.insertBefore(entryDiv, addMoreEntryBtn);
        } else {
            additionalEntriesContainer.appendChild(entryDiv);
        }

        // Populate vendor categories for the new entry's Type dropdown only
        const typeSelect = entryDiv.querySelector('.entry-type');
        if (typeSelect) {
            loadVendorCategoriesForEntryDropdown(typeSelect);
            
            // Add event listener to type select for loading recipients
            typeSelect.addEventListener('change', function() {
                const recipientSelect = entryDiv.querySelector('.entry-recipient');
                loadRecipientsByTypeForEntry(this.value, recipientSelect);
            });
        }

        // Add event listener to payment mode select for multiple acceptance
        const modeSelect = entryDiv.querySelector('.entry-mode');
        if (modeSelect) {
            modeSelect.addEventListener('change', function() {
                const multipleAcceptanceSection = entryDiv.querySelector('.entry-multiple-acceptance-section');
                if (this.value === 'multiple_acceptance') {
                    multipleAcceptanceSection.style.display = 'block';
                    // Add first method if none exist
                    if (entryDiv.querySelectorAll('.entry-acceptance-method-row').length === 0) {
                        addEntryAcceptanceMethod(entryId);
                    }
                } else {
                    multipleAcceptanceSection.style.display = 'none';
                }
            });
        }

        // Load users for "Paid Via" dropdown
        const paidViaSelect = entryDiv.querySelector('.entry-paid-via');
        if (paidViaSelect) {
            loadAuthorizedUsersForEntry(paidViaSelect);
            
            // Auto-populate entry "Paid Via" with main "Payment Done By" value when entry is created
            setTimeout(function() {
                if (paymentAuthorizedUserSelect && paymentAuthorizedUserSelect.value) {
                    paidViaSelect.value = paymentAuthorizedUserSelect.value;
                }
            }, 100);
            
            // Entry field is independent - user can change it without affecting main field
            // No event listener needed here - changes are local to this entry only
        }

        // Add event listener to entry amount for validation against main payment amount
        const entryAmountInput = entryDiv.querySelector('.entry-amount');
        if (entryAmountInput) {
            entryAmountInput.addEventListener('change', function() {
                validateEntryAmounts();
            });
            entryAmountInput.addEventListener('input', function() {
                validateEntryAmounts();
            });
        }

        // Add event listener to "Add Method" button
        const addMethodBtn = entryDiv.querySelector('.payment-entry-btn-add-entry-method');
        if (addMethodBtn) {
            addMethodBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addEntryAcceptanceMethod(entryId);
            });
        }

        // Add event listener to media file upload
        const mediaFileInput = entryDiv.querySelector('.entry-media-file');
        if (mediaFileInput) {
            mediaFileInput.addEventListener('change', function(e) {
                handleEntryMediaUpload(entryId, this.files[0]);
            });
        }

        // Add event listener to recipient dropdown for "Add Labour" option
        const recipientSelect = entryDiv.querySelector('.entry-recipient');
        if (recipientSelect) {
            recipientSelect.addEventListener('change', function(e) {
                // Only trigger if the change was user-initiated, not programmatic
                if (this.value === 'add_labour') {
                    // Store the current selected value before resetting
                    const previousValue = this.value;
                    
                    // Reset dropdown to empty after a brief delay
                    setTimeout(() => {
                        this.value = '';
                    }, 50);
                    
                    // Open the Add Labour modal
                    if (typeof window.openAddLabourModal === 'function') {
                        window.openAddLabourModal();
                    } else {
                        alert('Add Labour modal not available. Please ensure add_labour_modal.php is included.');
                    }
                }
            }, true); // Use capturing phase to catch event early
        }
    }

    // Add acceptance method to entry
    function addEntryAcceptanceMethod(entryId) {
        const methodContainer = document.querySelector(`.entry-acceptance-methods-container[data-entry="${entryId}"]`);
        if (!methodContainer) return;

        const methodCount = methodContainer.querySelectorAll('.entry-acceptance-method-row').length + 1;
        const methodId = `${entryId}_method_${methodCount}`;

        const methodRow = document.createElement('div');
        methodRow.className = 'entry-acceptance-method-row';
        methodRow.id = methodId;
        methodRow.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr 1.2fr auto; gap: 12px; margin-bottom: 10px; padding: 0; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;';
        
        const mediaId = `entry_acceptance_media_${methodId}`;

        methodRow.innerHTML = `
            <div>
                <select class="entry-method-select payment-entry-select-field" data-method="${methodId}" style="width: 100%; border: 1px solid #e2e8f0; padding: 8px 10px; border-radius: 4px; font-size: 0.9em; background-color: white;">
                    <option value="">Select Method</option>
                    <option value="cash">Cash</option>
                    <option value="cheque">Cheque</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="online">Online Payment</option>
                    <option value="upi">UPI</option>
                </select>
            </div>
            <div>
                <input type="number" class="entry-method-amount payment-entry-text-input" data-method="${methodId}" placeholder="0.00" step="0.01" min="0" style="width: 100%; border: 1px solid #e2e8f0; padding: 8px 10px; border-radius: 4px; font-size: 0.9em;">
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <label for="${mediaId}" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 4px; cursor: pointer; font-size: 0.8em; font-weight: 600; transition: all 0.2s ease; white-space: nowrap; flex: 1;" title="Upload supporting document">
                    <i class="fas fa-paperclip" style="font-size: 0.8em;"></i>
                    <span>Upload</span>
                </label>
                <input type="file" id="${mediaId}" data-method="${methodId}" accept=".pdf,.jpg,.jpeg,.png,.mp4,.mov,.avi" style="display: none;" class="entry-method-media">
                <div class="entry-method-media-preview" data-method="${methodId}" style="font-size: 0.7em; color: #718096; margin-left: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
            </div>
            <button type="button" class="entry-remove-method-btn" data-method="${methodId}" style="background-color: transparent; color: #e53e3e; border: none; padding: 6px 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.9em; transition: all 0.2s ease; opacity: 0.6;" title="Remove this method">
                <i class="fas fa-trash" style="font-size: 0.85em;"></i>
            </button>
        `;

        methodContainer.appendChild(methodRow);

        // Add event listener to amount input
        const amountInput = methodRow.querySelector('.entry-method-amount');
        if (amountInput) {
            amountInput.addEventListener('change', function() {
                updateEntryAcceptanceTotals(entryId);
            });
        }

        // Add event listener to media file upload
        const mediaInput = methodRow.querySelector('.entry-method-media');
        if (mediaInput) {
            mediaInput.addEventListener('change', function(e) {
                handleEntryAcceptanceMediaUpload(methodId, this.files[0]);
            });
        }

        // Add event listener to remove button
        const removeBtn = methodRow.querySelector('.entry-remove-method-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                methodRow.remove();
                updateEntryAcceptanceTotals(entryId);
            });
        }
    }

    // Handle entry acceptance media upload
    function handleEntryAcceptanceMediaUpload(methodId, file) {
        const previewDiv = document.querySelector(`.entry-method-media-preview[data-method="${methodId}"]`);
        if (!previewDiv) return;

        if (!file) {
            previewDiv.innerHTML = '';
            return;
        }

        const maxSize = 50 * 1024 * 1024; // 50MB
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'video/mp4', 'video/quicktime', 'video/x-msvideo'];

        if (file.size > maxSize) {
            previewDiv.innerHTML = `<span style="color: #e53e3e;"><i class="fas fa-exclamation-circle"></i> File too large</span>`;
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            previewDiv.innerHTML = `<span style="color: #e53e3e;"><i class="fas fa-exclamation-circle"></i> Invalid file type</span>`;
            return;
        }

        // Show file info
        const fileSize = (file.size / 1024).toFixed(2);
        previewDiv.innerHTML = `<span style="color: #22863a;"><i class="fas fa-check-circle"></i> ${file.name} (${fileSize} KB)</span>`;
    }

    // Update entry acceptance totals
    function updateEntryAcceptanceTotals(entryId) {
        const entryDiv = document.getElementById(entryId);
        if (!entryDiv) return;

        // Get entry amount
        const entryAmountInput = entryDiv.querySelector('.entry-amount');
        const entryAmount = parseFloat(entryAmountInput?.value || 0);

        // Get total accepted amount from all methods
        let totalAccepted = 0;
        const methodAmounts = entryDiv.querySelectorAll('.entry-method-amount');
        methodAmounts.forEach(input => {
            totalAccepted += parseFloat(input.value || 0);
        });

        // Update display
        const totalAmountSpan = entryDiv.querySelector('.entry-total-amount');
        const receivedAmountSpan = entryDiv.querySelector('.entry-received-amount');
        const warningDiv = entryDiv.querySelector('.entry-acceptance-warning');

        if (totalAmountSpan) totalAmountSpan.textContent = entryAmount.toFixed(2);
        if (receivedAmountSpan) receivedAmountSpan.textContent = totalAccepted.toFixed(2);

        // Show/hide warning
        if (warningDiv) {
            if (Math.abs(entryAmount - totalAccepted) > 0.01) {
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }
        }
    }

    // Remove entry
    function removeEntry(entryId) {
        const entryDiv = document.getElementById(entryId);
        if (entryDiv) {
            entryDiv.remove();
            // Renumber remaining entries
            renumberEntries();
        }
    }

    // Renumber entries
    function renumberEntries() {
        const entries = additionalEntriesContainer.querySelectorAll('.payment-entry-additional-entry');
        entries.forEach((entry, index) => {
            const titleElement = entry.querySelector('.payment-entry-additional-entry-title');
            if (titleElement) {
                titleElement.textContent = `Entry #${index + 1}`;
            }
        });
    }

    // Handle entry media upload
    function handleEntryMediaUpload(entryId, file) {
        const previewDiv = document.querySelector(`.entry-media-preview[data-entry="${entryId}"]`);
        if (!previewDiv) return;

        if (!file) {
            previewDiv.innerHTML = '';
            return;
        }

        const maxSize = 50 * 1024 * 1024; // 50MB
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'video/mp4', 'video/quicktime', 'video/x-msvideo'];

        if (file.size > maxSize) {
            previewDiv.innerHTML = `<span style="color: #e53e3e;"><i class="fas fa-exclamation-circle"></i> File too large (max 50MB)</span>`;
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            previewDiv.innerHTML = `<span style="color: #e53e3e;"><i class="fas fa-exclamation-circle"></i> Invalid file type</span>`;
            return;
        }

        // Show file info
        const fileSize = (file.size / 1024).toFixed(2);
        previewDiv.innerHTML = `<span style="color: #22863a;"><i class="fas fa-check-circle"></i> ${file.name} (${fileSize} KB)</span>`;
    }

    // Load recipients for additional entries - fetches based on type (labour or vendor)
    function loadRecipientsByTypeForEntry(type, recipientSelect) {
        if (!recipientSelect) return;

        if (!type) {
            recipientSelect.innerHTML = '<option value="">Select Recipient</option>';
            return;
        }

        recipientSelect.innerHTML = '<option value="">Loading...</option>';
        recipientSelect.disabled = true;

        let endpoint = '';
        
        // For Labour types, fetch from labour_records table
        if (type === 'Permanent' || type === 'Temporary' || type === 'Vendor') {
            endpoint = `get_labour_recipients.php?labour_type=${encodeURIComponent(type)}`;
        } else {
            // For Vendor types (Material Supplier, Labour Contractor, Material Contractor)
            // Use the type as vendor_category_type
            endpoint = `get_vendor_recipients.php?vendor_category_type=${encodeURIComponent(type)}`;
        }

        fetch(endpoint)
            .then(response => response.json())
            .then(data => {
                recipientSelect.disabled = false;
                if (data.success && data.recipients && data.recipients.length > 0) {
                    let html = '<option value="">Select Recipient</option>';
                    data.recipients.forEach(recipient => {
                        html += `<option value="${recipient.id}">${recipient.full_name}</option>`;
                    });
                    // Add divider and Add Labour option only for labour types
                    if (type === 'Permanent' || type === 'Temporary' || type === 'Vendor') {
                        html += '<option value="add_labour" disabled>─────────────────</option>';
                        html += '<option value="add_labour">+ Add Labour</option>';
                    }
                    recipientSelect.innerHTML = html;
                } else {
                    let html = '<option value="">No recipients found</option>';
                    // Still show Add Labour option only for labour types even if no recipients
                    if (type === 'Permanent' || type === 'Temporary' || type === 'Vendor') {
                        html += '<option value="add_labour" disabled>─────────────────</option>';
                        html += '<option value="add_labour">+ Add Labour</option>';
                    }
                    recipientSelect.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading recipients:', error);
                recipientSelect.disabled = false;
                let html = '<option value="">Error loading recipients</option>';
                // Still show Add Labour option only for labour types even on error
                if (type === 'Permanent' || type === 'Temporary' || type === 'Vendor') {
                    html += '<option value="add_labour" disabled>─────────────────</option>';
                    html += '<option value="add_labour">+ Add Labour</option>';
                }
                recipientSelect.innerHTML = html;
            });
    }

    // Refresh recipient dropdowns after adding new vendor or labour
    function refreshEntryRecipients() {
        const allEntries = document.querySelectorAll('.payment-entry-additional-entry');
        allEntries.forEach(entry => {
            const typeSelect = entry.querySelector('.entry-type');
            const recipientSelect = entry.querySelector('.entry-recipient');
            
            if (typeSelect && recipientSelect && typeSelect.value) {
                // Reload recipients for this entry
                loadRecipientsByTypeForEntry(typeSelect.value, recipientSelect);
            }
        });
    }
    
    // Expose refreshEntryRecipients globally so add_labour_modal and add_vendor_modal can call it
    window.refreshEntryRecipients = refreshEntryRecipients;

    // Add payment row for multiple payments
    if (addPaymentRowBtn) {
        addPaymentRowBtn.addEventListener('click', function() {
            const rowCount = paymentRowsContainer.querySelectorAll('tr').length + 1;
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>${rowCount}</td>
                <td><select class="payment-entry-table-select"><option>Select Type</option></select></td>
                <td><input type="text" class="payment-entry-table-input" placeholder="Enter project name"></td>
                <td><input type="number" class="payment-entry-table-input" placeholder="Enter amount"></td>
                <td><input type="date" class="payment-entry-table-input"></td>
                <td><select class="payment-entry-table-select"><option>Select Mode</option></select></td>
                <td><button type="button" class="payment-entry-btn-delete-row"><i class="fas fa-trash"></i></button></td>
            `;
            paymentRowsContainer.appendChild(newRow);
        });
    }

    // Helper function to show error message
    function showError(elementId, message) {
        const errorElement = document.getElementById(elementId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
            setTimeout(() => {
                errorElement.classList.remove('show');
            }, 5000);
        }
    }

    // Initialize toggle labels on load
    document.addEventListener('DOMContentLoaded', function() {
        updateToggleLabels();
    });
</script>
