<!-- Add Payment Entry Modal -->
<div class="modal fade" id="addPaymentEntryModal" tabindex="-1" aria-labelledby="addPaymentEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPaymentEntryModalLabel">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    Add Payment Entry
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Loader -->
                <div class="loader-overlay" id="paymentEntryLoader" style="display: none;">
                    <div class="loader-content">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Processing your request...</p>
                    </div>
                </div>
                <form id="addPaymentEntryForm">
                    <!-- Payment Information Section -->
                    <div class="payment-section">
                        <h6 class="section-title mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Payment Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="projectType" class="form-label">Project Type <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-project-diagram input-icon"></i>
                                    <select class="form-select" id="projectType" name="projectType" required onchange="loadProjectNames()">
                                        <option value="">Select Project Type</option>
                                        <option value="architecture">Architecture</option>
                                        <option value="interior">Interior</option>
                                        <option value="construction">Construction</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="projectName" class="form-label">Project Name <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-building input-icon"></i>
                                    <select class="form-select" id="projectName" name="projectName" required disabled>
                                        <option value="">First select project type</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="paymentDate" class="form-label">Date <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-calendar-alt input-icon"></i>
                                    <input type="date" class="form-control" id="paymentDate" name="paymentDate" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="paymentAmount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-rupee-sign input-icon"></i>
                                    <input type="number" class="form-control" id="paymentAmount" name="paymentAmount" 
                                           placeholder="Enter amount" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="paymentDoneVia" class="form-label">Payment Done Via <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-user-tie input-icon"></i>
                                    <select class="form-select" id="paymentDoneVia" name="paymentDoneVia" required>
                                        <option value="">Loading users...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="paymentMode" class="form-label">Payment Mode <span class="text-danger">*</span></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-credit-card input-icon"></i>
                                    <select class="form-select" id="paymentMode" name="paymentMode" required>
                                        <option value="">Select Payment Mode</option>
                                        <option value="split_payment">Split Payment</option>
                                        <option value="cash">Cash</option>
                                        <option value="upi">UPI (Unified Payments Interface)</option>
                                        <option value="neft">NEFT (National Electronic Funds Transfer)</option>
                                        <option value="rtgs">RTGS (Real Time Gross Settlement)</option>
                                        <option value="imps">IMPS (Immediate Payment Service)</option>
                                        <option value="net_banking">Net Banking</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="demand_draft">Demand Draft (DD)</option>
                                        <option value="debit_card">Debit Card</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="paytm">Paytm</option>
                                        <option value="phonepe">PhonePe</option>
                                        <option value="gpay">Google Pay</option>
                                        <option value="bhim">BHIM UPI</option>
                                        <option value="amazon_pay">Amazon Pay</option>
                                        <option value="mobikwik">MobiKwik</option>
                                        <option value="freecharge">FreeCharge</option>
                                        <option value="wallet">Digital Wallet</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="money_order">Money Order</option>
                                        <option value="postal_order">Postal Order</option>
                                        <option value="prepaid_card">Prepaid Card</option>
                                        <option value="ecs">ECS (Electronic Clearing Service)</option>
                                        <option value="ach">ACH (Automated Clearing House)</option>
                                        <option value="nach">NACH (National Automated Clearing House)</option>
                                        <option value="aeps">AEPS (Aadhaar Enabled Payment System)</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recipients Container -->
                        <div class="recipients-container" id="recipientsContainer">
                            <!-- Recipients will be added here dynamically -->
                        </div>
                        
                        <!-- Add Recipient Button -->
                        <div class="row" id="addRecipientButtonRow">
                            <div class="col-12 mb-3">
                                <button type="button" class="btn btn-outline-primary" id="addRecipientBtn" onclick="addNewRecipient()">
                                    <i class="fas fa-plus me-2"></i>
                                    Add Recipient
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="submitPaymentEntryForm()">
                    <i class="fas fa-save me-2"></i>
                    Save Payment Entry
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Add Payment Entry Modal Styles */
.payment-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #f1f3f4;
}

.payment-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.section-title i {
    color: #6b7280;
    margin-right: 0.5rem;
    font-size: 1rem;
}

.form-label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.input-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1rem;
    color: #9ca3af;
    font-size: 0.9rem;
    z-index: 2;
    transition: color 0.2s ease;
}

.form-control, .form-select {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 0.875rem 1rem 0.875rem 2.75rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    background-color: #fafbfc;
    box-shadow: none;
}

.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    background-color: #ffffff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

.form-control:focus + .input-icon,
.form-select:focus + .input-icon {
    color: #3b82f6;
}

.text-danger {
    color: #ef4444 !important;
}

.btn {
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
}

.btn-secondary:hover {
    background-color: #e5e7eb;
    color: #1f2937;
    border-color: #d1d5db;
}

/* Remove default select arrow for custom styling */
.form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 16px 12px;
    padding-right: 2.5rem;
}

/* Validation styles */
.is-invalid {
    border-color: #ef4444 !important;
    background-color: #fef2f2 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

.is-invalid + .input-icon {
    color: #ef4444 !important;
}

/* Modal specific styles */
.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.modal-header {
    border-bottom: 1px solid #f1f3f4;
    padding: 1.5rem 2rem;
    border-radius: 16px 16px 0 0;
    background-color: #fafbfc;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.modal-body {
    padding: 2rem;
    background-color: #ffffff;
}

.modal-footer {
    border-top: 1px solid #f1f3f4;
    padding: 1.5rem 2rem;
    border-radius: 0 0 16px 16px;
    background-color: #fafbfc;
}

/* Recipients Container Styles */
.recipients-container {
    margin-top: 1rem;
}

.recipient-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    background-color: #f9fafb;
    position: relative;
    transition: all 0.3s ease;
}

.recipient-item:hover {
    border-color: #d1d5db;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.recipient-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.recipient-title {
    color: #059669;
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
}

.recipient-title i {
    color: #059669;
    margin-right: 0.5rem;
}

.remove-recipient-btn {
    background: none;
    border: none;
    color: #dc2626;
    font-size: 1rem;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
}

.remove-recipient-btn:hover {
    background-color: #fef2f2;
    color: #b91c1c;
}

#addRecipientBtn {
    border-color: #3b82f6;
    color: #3b82f6;
    transition: all 0.2s ease;
}

#addRecipientBtn:hover {
    background-color: #3b82f6;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Custom Type Back Button */
.custom-back-btn {
    position: absolute;
    right: 0.75rem;
    background: none;
    border: none;
    color: #6b7280;
    font-size: 0.9rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.2s ease;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
}

.custom-back-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

/* Recipient Type Required Indicator */
.recipient-type-required {
    transition: all 0.2s ease;
}

/* File Upload Styles for Recipients */
.file-upload-wrapper {
    position: relative;
    cursor: pointer;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}

.file-upload-display {
    border: 2px dashed #e5e7eb;
    border-radius: 14px;
    padding: 1.5rem;
    text-align: center;
    background-color: #fafbfc;
    transition: all 0.2s ease;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.file-upload-display:hover {
    border-color: #3b82f6;
    background-color: #f8faff;
}

.file-upload-wrapper:hover .file-upload-display {
    border-color: #3b82f6;
    background-color: #f8faff;
}

.file-icon {
    font-size: 1.5rem;
    color: #9ca3af;
    margin-bottom: 0.5rem;
    transition: color 0.2s ease;
}

/* Add Payment Entry Modal Styles */
.loader-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    border-radius: 16px;
}

.loader-content {
    text-align: center;
    color: #374151;
}

.loader-content p {
    margin: 10px 0 0;
    font-weight: 500;
}

.payment-section {
    background-color: #f3f4f6;
    color: #374151;
}

/* Recipient Type Required Indicator */
.recipient-type-required {
    transition: all 0.2s ease;
}

/* File Upload Styles for Recipients */
.file-upload-wrapper {
    position: relative;
    cursor: pointer;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}

.file-upload-display {
    border: 2px dashed #e5e7eb;
    border-radius: 14px;
    padding: 1.5rem;
    text-align: center;
    background-color: #fafbfc;
    transition: all 0.2s ease;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.file-upload-display:hover {
    border-color: #3b82f6;
    background-color: #f8faff;
}

.file-upload-wrapper:hover .file-upload-display {
    border-color: #3b82f6;
    background-color: #f8faff;
}

.file-icon {
    font-size: 1.5rem;
    color: #9ca3af;
    margin-bottom: 0.5rem;
    transition: color 0.2s ease;
}

.file-upload-display:hover .file-icon {
    color: #3b82f6;
}

.file-text {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.file-upload-display:hover .file-text {
    color: #3b82f6;
}

.file-upload-wrapper.has-file .file-upload-display {
    border-color: #10b981;
    background-color: #f0fdf4;
}

.file-upload-wrapper.has-file .file-icon {
    color: #10b981;
}

.file-upload-wrapper.has-file .file-text {
    color: #10b981;
}

/* Selected Files Display */
.selected-files-container {
    margin-top: 1rem;
    padding: 1rem;
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.selected-files-header {
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.selected-files-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.file-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    background-color: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    max-width: 200px;
}

.file-item-info {
    flex: 1;
    min-width: 0;
}

.file-item-name {
    font-weight: 500;
    color: #374151;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.125rem;
}

.file-item-size {
    font-size: 0.75rem;
    color: #6b7280;
}

.file-item-remove {
    background: none;
    border: none;
    color: #dc2626;
    font-size: 0.875rem;
    cursor: pointer;
    padding: 0.25rem;
    margin-left: 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.file-item-remove:hover {
    background-color: #fef2f2;
    color: #b91c1c;
}

.file-item-icon {
    margin-right: 0.5rem;
    color: #059669;
}

/* File count indicator */
.file-count-badge {
    display: inline-block;
    background-color: #10b981;
    color: white;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    margin-left: 0.5rem;
    font-weight: 500;
}

/* Split Payment Styles */
.split-payment-section {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    background-color: #fafbfc;
}

.split-payments-container {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.split-payment-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 0.75rem;
}

.split-payment-item:last-child {
    margin-bottom: 0;
}

.split-payment-amount {
    flex: 1;
    min-width: 120px;
}

.split-payment-mode {
    flex: 2;
    min-width: 150px;
}

.split-payment-proof {
    flex: 1;
    min-width: 100px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.file-input-split {
    font-size: 0.75rem;
    padding: 0.25rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    margin-bottom: 0.25rem;
}

.file-name-display {
    font-size: 0.7rem;
    color: #6b7280;
    text-align: center;
    max-width: 100px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-name-display.has-file {
    color: #059669;
    font-weight: 500;
}

.split-payment-remove {
    background: none;
    border: none;
    color: #dc2626;
    font-size: 0.9rem;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
}

.split-payment-remove:hover {
    background-color: #fef2f2;
    color: #b91c1c;
}

.split-payment-summary {
    background-color: #f0f9ff;
    border: 1px solid #e0f2fe;
    border-radius: 6px;
    padding: 0.75rem;
    margin-top: 1rem;
    font-size: 0.875rem;
}

.split-payment-total {
    font-weight: 600;
    color: #0369a1;
}

.split-payment-remaining {
    color: #dc2626;
}

.split-payment-complete {
    color: #059669;
}

/* Animation for adding/removing recipients */
.recipient-item.fade-in {
    animation: fadeInSlide 0.3s ease-out;
}

.recipient-item.fade-out {
    animation: fadeOutSlide 0.3s ease-out;
}

@keyframes fadeInSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeOutSlide {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}

/* Responsive design */
@media (max-width: 768px) {
    .modal-lg {
        max-width: 95%;
        margin: 1rem auto;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-header,
    .modal-footer {
        padding: 1rem 1.5rem;
    }
}
</style>

<script>
// Handle multiple file uploads for recipients
function updateRecipientFileName(input, recipientId) {
    const wrapper = input.closest('.file-upload-wrapper');
    const fileText = wrapper.querySelector('.file-text');
    const fileIcon = wrapper.querySelector('.file-icon');
    const selectedFilesContainer = document.getElementById(`selectedFiles_${recipientId}`);
    const filesList = document.getElementById(`filesList_${recipientId}`);
    
    // Clear previous files display
    filesList.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const maxFiles = 10;
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        const validFiles = [];
        
        // Validate file count
        if (input.files.length > maxFiles) {
            alert(`You can upload maximum ${maxFiles} files at once`);
            input.value = '';
            return;
        }
        
        // Validate each file
        for (let i = 0; i < input.files.length; i++) {
            const file = input.files[i];
            
            // Validate file size
            if (file.size > maxSize) {
                alert(`File "${file.name}" is too large. Maximum size is 5MB.`);
                input.value = '';
                return;
            }
            
            // Validate file type
            if (!allowedTypes.includes(file.type)) {
                alert(`File "${file.name}" is not a supported format. Please use JPG, PNG, or PDF.`);
                input.value = '';
                return;
            }
            
            validFiles.push(file);
        }
        
        // All files are valid, display them
        wrapper.classList.add('has-file');
        fileText.innerHTML = `${validFiles.length} file${validFiles.length > 1 ? 's' : ''} selected <span class="file-count-badge">${validFiles.length}</span>`;
        fileIcon.className = 'fas fa-check-circle file-icon';
        
        // Show selected files container
        selectedFilesContainer.style.display = 'block';
        
        // Display each file
        validFiles.forEach((file, index) => {
            const fileSize = (file.size / 1024).toFixed(1) + ' KB';
            const fileExtension = file.name.split('.').pop().toUpperCase();
            
            const fileItemHtml = `
                <div class="file-item" id="fileItem_${recipientId}_${index}">
                    <i class="fas fa-file-${getFileIcon(file.type)} file-item-icon"></i>
                    <div class="file-item-info">
                        <div class="file-item-name" title="${file.name}">${file.name}</div>
                        <div class="file-item-size">${fileSize} • ${fileExtension}</div>
                    </div>
                    <button type="button" class="file-item-remove" onclick="removeSelectedFile(${recipientId}, ${index})" title="Remove file">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            filesList.insertAdjacentHTML('beforeend', fileItemHtml);
        });
    } else {
        // No files selected
        wrapper.classList.remove('has-file');
        fileText.textContent = 'Choose bill or payment proof (multiple files allowed)';
        fileIcon.className = 'fas fa-camera file-icon';
        selectedFilesContainer.style.display = 'none';
    }
}

// Get appropriate file icon based on file type
function getFileIcon(fileType) {
    if (fileType === 'application/pdf') {
        return 'pdf';
    } else if (fileType.startsWith('image/')) {
        return 'image';
    } else {
        return 'alt';
    }
}

// Remove individual selected file
function removeSelectedFile(recipientId, fileIndex) {
    const input = document.getElementById(`recipientBillImage_${recipientId}`);
    const fileItem = document.getElementById(`fileItem_${recipientId}_${fileIndex}`);
    
    // Create new FileList without the removed file
    const dt = new DataTransfer();
    const files = Array.from(input.files);
    
    files.forEach((file, index) => {
        if (index !== fileIndex) {
            dt.items.add(file);
        }
    });
    
    input.files = dt.files;
    
    // Remove the file item from display
    fileItem.remove();
    
    // Update the display
    updateRecipientFileName(input, recipientId);
}

// Global variables for split payment tracking
let splitPaymentCounters = {};

// Toggle split payment section
function toggleSplitPayment(recipientId) {
    const splitContainer = document.getElementById(`splitPaymentsContainer_${recipientId}`);
    const splitBtn = document.getElementById(`splitPaymentBtn_${recipientId}`);
    const mainAmountField = document.getElementById(`recipientAmount_${recipientId}`);
    const mainPaymentMode = document.getElementById(`recipientPaymentMode_${recipientId}`);
    
    if (splitContainer.style.display === 'none' || splitContainer.style.display === '') {
        // Show split payment section
        splitContainer.style.display = 'block';
        splitBtn.innerHTML = '<i class="fas fa-minus me-1"></i>Remove Split Payment';
        splitBtn.classList.remove('btn-outline-success');
        splitBtn.classList.add('btn-outline-danger');
        
        // Set main payment mode to "Split Payment" when split is active
        mainPaymentMode.value = "split_payment";
        // Keep the field enabled but make it readonly to ensure it's included in form submission
        mainPaymentMode.disabled = false;
        mainPaymentMode.readOnly = true;
        // Add a visual indicator that this is auto-filled
        mainPaymentMode.style.backgroundColor = "#f8f9fa";
        mainPaymentMode.style.cursor = "not-allowed";
        
        // Initialize split payment counter
        if (!splitPaymentCounters[recipientId]) {
            splitPaymentCounters[recipientId] = 0;
        }
        
        // Add first split payment if none exist
        const splitItems = document.getElementById(`splitPaymentItems_${recipientId}`);
        if (splitItems.children.length === 0) {
            addSplitPayment(recipientId);
        }
    } else {
        // Hide split payment section
        splitContainer.style.display = 'none';
        splitBtn.innerHTML = '<i class="fas fa-plus me-1"></i>Split Payment';
        splitBtn.classList.remove('btn-outline-danger');
        splitBtn.classList.add('btn-outline-success');
        
        // Re-enable main payment mode and clear the value
        mainPaymentMode.disabled = false;
        mainPaymentMode.readOnly = false;
        mainPaymentMode.value = "";
        mainPaymentMode.style.backgroundColor = "";
        mainPaymentMode.style.cursor = "";
        mainPaymentMode.setAttribute('required', 'required');
        
        // Clear all split payments
        const splitItems = document.getElementById(`splitPaymentItems_${recipientId}`);
        splitItems.innerHTML = '';
        splitPaymentCounters[recipientId] = 0;
    }
}

// Add new split payment method
function addSplitPayment(recipientId) {
    if (!splitPaymentCounters[recipientId]) {
        splitPaymentCounters[recipientId] = 0;
    }
    
    splitPaymentCounters[recipientId]++;
    const splitId = splitPaymentCounters[recipientId];
    
    const splitItems = document.getElementById(`splitPaymentItems_${recipientId}`);
    
    const splitHtml = `
        <div class="split-payment-item" id="splitPayment_${recipientId}_${splitId}">
            <div class="split-payment-amount">
                <input type="number" class="form-control form-control-sm" 
                       id="splitAmount_${recipientId}_${splitId}" 
                       name="recipients[${recipientId}][splitPayments][${splitId}][amount]" 
                       placeholder="Amount" min="0" step="0.01" required 
                       onchange="updateSplitSummary(${recipientId})">
            </div>
            <div class="split-payment-mode">
                <select class="form-select form-select-sm" 
                        id="splitMode_${recipientId}_${splitId}" 
                        name="recipients[${recipientId}][splitPayments][${splitId}][mode]" required>
                    <option value="">Select Payment Mode</option>
                    <option value="cash">Cash</option>
                    <option value="upi">UPI</option>
                    <option value="neft">NEFT</option>
                    <option value="rtgs">RTGS</option>
                    <option value="imps">IMPS</option>
                    <option value="net_banking">Net Banking</option>
                    <option value="cheque">Cheque</option>
                    <option value="demand_draft">Demand Draft</option>
                    <option value="debit_card">Debit Card</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="paytm">Paytm</option>
                    <option value="phonepe">PhonePe</option>
                    <option value="gpay">Google Pay</option>
                    <option value="bhim">BHIM UPI</option>
                    <option value="amazon_pay">Amazon Pay</option>
                    <option value="mobikwik">MobiKwik</option>
                    <option value="wallet">Digital Wallet</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="split-payment-proof">
                <input type="file" class="form-control form-control-sm file-input-split" 
                       id="splitProof_${recipientId}_${splitId}" 
                       name="recipients[${recipientId}][splitPayments][${splitId}][proof]" 
                       accept=".pdf,.jpg,.jpeg,.png" 
                       onchange="updateSplitFileName(this, ${recipientId}, ${splitId})" 
                       title="Upload payment proof">
                <small class="file-name-display" id="fileName_${recipientId}_${splitId}">No file</small>
            </div>
            <button type="button" class="split-payment-remove" onclick="removeSplitPayment(${recipientId}, ${splitId})" title="Remove Payment Method">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    splitItems.insertAdjacentHTML('beforeend', splitHtml);
    updateSplitSummary(recipientId);
}

// Remove split payment method
function removeSplitPayment(recipientId, splitId) {
    const splitItem = document.getElementById(`splitPayment_${recipientId}_${splitId}`);
    if (splitItem) {
        splitItem.remove();
        updateSplitSummary(recipientId);
    }
}

// Handle file upload for split payments
function updateSplitFileName(input, recipientId, splitId) {
    const fileNameDisplay = document.getElementById(`fileName_${recipientId}_${splitId}`);
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file size
        if (file.size > maxSize) {
            alert('File size should not exceed 5MB');
            input.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid file type (JPG, PNG, PDF)');
            input.value = '';
            return;
        }
        
        fileNameDisplay.textContent = file.name.length > 12 ? file.name.substring(0, 12) + '...' : file.name;
        fileNameDisplay.classList.add('has-file');
    } else {
        fileNameDisplay.textContent = 'No file';
        fileNameDisplay.classList.remove('has-file');
    }
}

// Update split payment summary
function updateSplitSummary(recipientId) {
    const mainAmount = parseFloat(document.getElementById(`recipientAmount_${recipientId}`).value) || 0;
    const splitItems = document.querySelectorAll(`#splitPaymentItems_${recipientId} .split-payment-item`);
    
    let totalSplitAmount = 0;
    splitItems.forEach(item => {
        const amountInput = item.querySelector('input[type="number"]');
        totalSplitAmount += parseFloat(amountInput.value) || 0;
    });
    
    // Remove existing summary
    const existingSummary = document.getElementById(`splitSummary_${recipientId}`);
    if (existingSummary) {
        existingSummary.remove();
    }
    
    // Add summary if there are split payments
    if (splitItems.length > 0) {
        const remaining = mainAmount - totalSplitAmount;
        const splitContainer = document.getElementById(`splitPaymentsContainer_${recipientId}`);
        
        const summaryHtml = `
            <div class="split-payment-summary" id="splitSummary_${recipientId}">
                <div class="split-payment-total">Total Amount: ₹${mainAmount.toFixed(2)}</div>
                <div>Split Total: ₹${totalSplitAmount.toFixed(2)}</div>
                <div class="${remaining === 0 ? 'split-payment-complete' : 'split-payment-remaining'}">
                    ${remaining === 0 ? '✓ Amount fully allocated' : `Remaining: ₹${remaining.toFixed(2)}`}
                </div>
            </div>
        `;
        
        splitContainer.insertAdjacentHTML('beforeend', summaryHtml);
    }
}

// Global variable to track recipient count and IDs
let recipientCounter = 0;
let activeRecipientIds = new Set();

// Add new recipient function
function addNewRecipient() {
    recipientCounter++;
    activeRecipientIds.add(recipientCounter);
    
    const recipientsContainer = document.getElementById('recipientsContainer');
    const addButtonRow = document.getElementById('addRecipientButtonRow');
    
    // Get current recipient count for display numbering
    const currentRecipientNumber = activeRecipientIds.size;
    
    const recipientHtml = `
        <div class="recipient-item fade-in" id="recipient-${recipientCounter}" data-recipient-id="${recipientCounter}">
            <div class="recipient-header">
                <h6 class="recipient-title">
                    <i class="fas fa-user-plus"></i>
                    Recipient #${currentRecipientNumber}
                </h6>
                <button type="button" class="remove-recipient-btn" onclick="removeRecipient(${recipientCounter})" title="Remove Recipient">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="recipientCategory_${recipientCounter}" class="form-label">Receipt Category <span class="text-danger">*</span></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-tags input-icon"></i>
                        <select class="form-select" id="recipientCategory_${recipientCounter}" name="recipients[${recipientCounter}][category]" required onchange="handleCategoryChange(${recipientCounter})">
                            <option value="">Select Category</option>
                            <option value="vendor">Vendor</option>
                            <option value="supplier">Supplier</option>
                            <option value="contractor">Contractor</option>
                            <option value="employee">Employee</option>
                            <option value="labour">Labour</option>
                            <option value="service_provider">Service Provider</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="recipientType_${recipientCounter}" class="form-label">Type <span class="text-danger recipient-type-required" style="display: none;">*</span></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-list input-icon" id="recipientTypeIcon_${recipientCounter}"></i>
                        <select class="form-select" id="recipientType_${recipientCounter}" name="recipients[${recipientCounter}][type]" disabled>
                            <option value="">First select category</option>
                        </select>
                        <input type="text" class="form-control" id="recipientTypeCustom_${recipientCounter}" name="recipients[${recipientCounter}][customType]" 
                               placeholder="Enter custom type" style="display: none;">
                        <button type="button" class="custom-back-btn" id="recipientTypeBackBtn_${recipientCounter}" 
                                onclick="backToTypeDropdown(${recipientCounter})" style="display: none;" title="Back to dropdown">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="recipientName_${recipientCounter}" class="form-label">Name <span class="text-danger">*</span></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" id="recipientName_${recipientCounter}" name="recipients[${recipientCounter}][name]" 
                               placeholder="Enter recipient name" required autocomplete="off" onkeyup="debounceSearch(event, ${recipientCounter})">
                        <input type="hidden" id="recipientId_${recipientCounter}" name="recipients[${recipientCounter}][id]" value="">
                        <div class="search-results-dropdown" id="searchResults_${recipientCounter}"></div>
                    </div>
                    <div class="invalid-feedback" id="nameError_${recipientCounter}">Please select a valid name from the list</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="paymentFor_${recipientCounter}" class="form-label">Payment For <span class="text-danger">*</span></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-info-circle input-icon"></i>
                        <input type="text" class="form-control" id="paymentFor_${recipientCounter}" name="recipients[${recipientCounter}][paymentFor]" 
                               placeholder="Enter payment purpose" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="recipientAmount_${recipientCounter}" class="form-label">Amount <span class="text-danger">*</span></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-rupee-sign input-icon"></i>
                        <input type="number" class="form-control" id="recipientAmount_${recipientCounter}" name="recipients[${recipientCounter}][amount]" 
                               placeholder="Enter amount" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="recipientPaymentMode_${recipientCounter}" class="form-label">Payment Mode <span class="text-danger">*</span></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-credit-card input-icon"></i>
                        <select class="form-select" id="recipientPaymentMode_${recipientCounter}" name="recipients[${recipientCounter}][paymentMode]" required>
                                            <option value="">Select Payment Mode</option>
                                            <option value="split_payment">Split Payment</option>
                                            <option value="cash">Cash</option>
                                            <option value="upi">UPI (Unified Payments Interface)</option>
                                            <option value="neft">NEFT (National Electronic Funds Transfer)</option>
                                            <option value="rtgs">RTGS (Real Time Gross Settlement)</option>
                                            <option value="imps">IMPS (Immediate Payment Service)</option>
                                            <option value="net_banking">Net Banking</option>
                                            <option value="cheque">Cheque</option>
                                            <option value="demand_draft">Demand Draft (DD)</option>
                                            <option value="debit_card">Debit Card</option>
                                            <option value="credit_card">Credit Card</option>
                                            <option value="paytm">Paytm</option>
                                            <option value="phonepe">PhonePe</option>
                                            <option value="gpay">Google Pay</option>
                                            <option value="bhim">BHIM UPI</option>
                                            <option value="amazon_pay">Amazon Pay</option>
                                            <option value="mobikwik">MobiKwik</option>
                                            <option value="freecharge">FreeCharge</option>
                                            <option value="wallet">Digital Wallet</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="money_order">Money Order</option>
                                            <option value="postal_order">Postal Order</option>
                                            <option value="prepaid_card">Prepaid Card</option>
                                            <option value="ecs">ECS (Electronic Clearing Service)</option>
                                            <option value="ach">ACH (Automated Clearing House)</option>
                                            <option value="nach">NACH (National Automated Clearing House)</option>
                                            <option value="aeps">AEPS (Aadhaar Enabled Payment System)</option>
                                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Split Payment Option -->
            <div class="row">
                <div class="col-12 mb-3">
                    <label for="recipientBillImage_${recipientCounter}" class="form-label">Bill/Payment Proof</label>
                    <div class="file-upload-wrapper">
                        <input type="file" class="form-control file-input" id="recipientBillImage_${recipientCounter}" name="recipients[${recipientCounter}][billImages][]" 
                               accept=".pdf,.jpg,.jpeg,.png" multiple onchange="updateRecipientFileName(this, ${recipientCounter})">
                        <div class="file-upload-display">
                            <i class="fas fa-camera file-icon"></i>
                            <span class="file-text">Choose bill or payment proof (multiple files allowed)</span>
                        </div>
                    </div>
                    <div class="selected-files-container" id="selectedFiles_${recipientCounter}" style="display: none;">
                        <div class="selected-files-header">
                            <small class="text-muted">Selected Files:</small>
                        </div>
                        <div class="selected-files-list" id="filesList_${recipientCounter}">
                            <!-- Selected files will be displayed here -->
                        </div>
                    </div>
                    <small class="text-muted">Supported formats: PDF, JPG, PNG (Max 5MB each, up to 10 files)</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="split-payment-section">
                        <button type="button" class="btn btn-outline-success btn-sm" id="splitPaymentBtn_${recipientCounter}" onclick="toggleSplitPayment(${recipientCounter})">
                            <i class="fas fa-plus me-1"></i>
                            Split Payment
                        </button>
                        <div class="split-payments-container" id="splitPaymentsContainer_${recipientCounter}" style="display: none;">
                            <div class="split-payment-header mt-3 mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Split the amount across multiple payment methods
                                </small>
                            </div>
                            <div class="split-payment-items" id="splitPaymentItems_${recipientCounter}">
                                <!-- Split payment items will be added here -->
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addSplitPayment(${recipientCounter})">
                                <i class="fas fa-plus me-1"></i>
                                Add Payment Method
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Insert the new recipient before the add button
    addButtonRow.insertAdjacentHTML('beforebegin', recipientHtml);
    
    // Scroll to the new recipient
    setTimeout(() => {
        const newRecipient = document.getElementById(`recipient-${recipientCounter}`);
        newRecipient.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

// Remove specific recipient
function removeRecipient(recipientId) {
    const recipientElement = document.getElementById(`recipient-${recipientId}`);
    if (recipientElement) {
        recipientElement.classList.add('fade-out');
        setTimeout(() => {
            recipientElement.remove();
            // Remove from active IDs set
            activeRecipientIds.delete(recipientId);
            // Update recipient numbers
            updateRecipientNumbers();
        }, 300);
    }
}

// Update recipient numbers after removal
function updateRecipientNumbers() {
    const recipients = document.querySelectorAll('.recipient-item');
    recipients.forEach((recipient, index) => {
        const title = recipient.querySelector('.recipient-title');
        if (title) {
            title.innerHTML = `<i class="fas fa-user-plus"></i>Recipient #${index + 1}`;
        }
    });
}

// Reset all recipients
function resetAllRecipients() {
    const recipientsContainer = document.getElementById('recipientsContainer');
    recipientsContainer.innerHTML = '';
    recipientCounter = 0;
    activeRecipientIds.clear();
    splitPaymentCounters = {}; // Reset split payment counters
}

// Handle category change for dynamic type loading
function handleCategoryChange(recipientId) {
    const categorySelect = document.getElementById(`recipientCategory_${recipientId}`);
    const typeSelect = document.getElementById(`recipientType_${recipientId}`);
    const typeLabel = typeSelect.closest('.mb-3').querySelector('.form-label');
    const requiredIndicator = typeLabel.querySelector('.recipient-type-required');
    const nameInput = document.getElementById(`recipientName_${recipientId}`);
    const nameIdInput = document.getElementById(`recipientId_${recipientId}`);
    
    // Reset name field when category changes
    nameInput.value = '';
    nameIdInput.value = '';
    
    const category = categorySelect.value;
    
    // Clear existing options
    typeSelect.innerHTML = '<option value="">Select Type</option>';
    
    if (category === '') {
        typeSelect.disabled = true;
        typeSelect.innerHTML = '<option value="">First select category</option>';
        requiredIndicator.style.display = 'none';
        return;
    }
    
    // Show required indicator
    requiredIndicator.style.display = 'inline';
    typeSelect.disabled = false;
    typeSelect.setAttribute('required', 'required');
    
    // Load options based on category
    let options = [];
    
    switch(category) {
        case 'vendor':
        case 'supplier':
            // For vendor category, fetch vendor types from database
            if (category === 'vendor') {
                fetch('../api/get_vendor_types.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Always add default options first
                    const defaultOptions = [
                        'Cement Supplier', 'Brick Supplier', 'Tile Supplier', 'Tile Vendor', 'Steel Supplier',
                        'Sand Supplier', 'Gravel Supplier', 'Concrete Supplier', 'Timber Supplier', 'Paint Supplier',
                        'Electrical Supplier', 'Plumbing Supplier', 'Hardware Supplier', 'Glass Supplier', 'Roofing Supplier',
                        'Flooring Supplier', 'Insulation Supplier', 'Door Supplier', 'Window Supplier', 'Security Equipment Supplier',
                        'HVAC Supplier', 'Landscaping Supplier', 'Cleaning Supplier', 'Safety Equipment Supplier', 'Transportation Vendor',
                        'Waste Management', 'General Supplier', 'Other', 'Custom Type'
                    ];
                    
                    // Add default options
                    defaultOptions.forEach(option => {
                        const optionElement = document.createElement('option');
                        optionElement.value = option.toLowerCase().replace(/\s+/g, '_');
                        optionElement.textContent = option;
                        typeSelect.appendChild(optionElement);
                    });
                    
                    // Add fetched vendor types if available
                    if (data.status === 'success' && data.vendor_types && Array.isArray(data.vendor_types) && data.vendor_types.length > 0) {
                        data.vendor_types.forEach(vendorType => {
                            // Only add if not already in the default options
                            const existingOption = Array.from(typeSelect.options).find(opt => 
                                opt.textContent.toLowerCase() === vendorType.toLowerCase());
                            
                            if (!existingOption) {
                                const optionElement = document.createElement('option');
                                optionElement.value = vendorType.toLowerCase().replace(/\s+/g, '_');
                                optionElement.textContent = vendorType;
                                typeSelect.appendChild(optionElement);
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading vendor types:', error);
                    // Fallback to default options if API fails
                    const defaultOptions = [
                        'Cement Supplier', 'Brick Supplier', 'Tile Supplier', 'Tile Vendor', 'Steel Supplier',
                        'Sand Supplier', 'Gravel Supplier', 'Concrete Supplier', 'Timber Supplier', 'Paint Supplier',
                        'Electrical Supplier', 'Plumbing Supplier', 'Hardware Supplier', 'Glass Supplier', 'Roofing Supplier',
                        'Flooring Supplier', 'Insulation Supplier', 'Door Supplier', 'Window Supplier', 'Security Equipment Supplier',
                        'HVAC Supplier', 'Landscaping Supplier', 'Cleaning Supplier', 'Safety Equipment Supplier', 'Transportation Vendor',
                        'Waste Management', 'General Supplier', 'Other', 'Custom Type'
                    ];
                    
                    defaultOptions.forEach(option => {
                        const optionElement = document.createElement('option');
                        optionElement.value = option.toLowerCase().replace(/\s+/g, '_');
                        optionElement.textContent = option;
                        typeSelect.appendChild(optionElement);
                    });
                });
            } else {
                // For supplier category, use default options
                options = [
                    'Cement Supplier', 'Brick Supplier', 'Tile Supplier', 'Tile Vendor', 'Steel Supplier',
                    'Sand Supplier', 'Gravel Supplier', 'Concrete Supplier', 'Timber Supplier', 'Paint Supplier',
                    'Electrical Supplier', 'Plumbing Supplier', 'Hardware Supplier', 'Glass Supplier', 'Roofing Supplier',
                    'Flooring Supplier', 'Insulation Supplier', 'Door Supplier', 'Window Supplier', 'Security Equipment Supplier',
                    'HVAC Supplier', 'Landscaping Supplier', 'Cleaning Supplier', 'Safety Equipment Supplier', 'Transportation Vendor',
                    'Waste Management', 'General Supplier', 'Other', 'Custom Type'
                ];
                
                // Add options to select
                options.forEach(option => {
                    const optionElement = document.createElement('option');
                    optionElement.value = option.toLowerCase().replace(/\s+/g, '_');
                    optionElement.textContent = option;
                    typeSelect.appendChild(optionElement);
                });
            }
            break;
            
        case 'contractor':
            options = [
                'Excavation Contractor', 'Masonry Contractor', 'Carpentry Contractor', 'Painting Contractor',
                'Electrical Contractor', 'Plumbing Contractor', 'Roofing Contractor', 'Flooring Contractor',
                'HVAC Contractor', 'Landscaping Contractor', 'Security Contractor', 'General Contractor',
                'Other', 'Custom Type'
            ];
            break;
            
        case 'employee':
            options = [
                'Site Supervisor', 'Project Manager', 'Engineer', 'Architect', 'Safety Officer',
                'Quality Control', 'Administrator', 'Accountant', 'HR Personnel', 'Security Guard',
                'Other', 'Custom Type'
            ];
            break;
            
        case 'labour':
            options = [
                'Permanent Labour', 'Chowk Labour', 'Vendor Labour',
                'Mason', 'Carpenter', 'Electrician', 'Plumber', 'Painter', 'Welder', 'Driver',
                'Construction Worker', 'Helper', 'Foreman', 'Crane Operator', 'Excavator Operator',
                'Other', 'Custom Type'
            ];
            break;
            
        case 'service_provider':
            options = [
                'Consulting Services', 'Design Services', 'Engineering Services', 'Legal Services',
                'Financial Services', 'Insurance Services', 'Transportation Services', 'Maintenance Services',
                'Cleaning Services', 'Security Services', 'Other', 'Custom Type'
            ];
            break;
            
        case 'other':
            options = ['Government Fee', 'Permit Fee', 'License Fee', 'Utility Bill', 'Rent', 'Insurance', 'Other', 'Custom Type'];
            break;
    }
    
    // Add options to select for non-vendor categories
    if (category !== 'vendor') {
        options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option.toLowerCase().replace(/\s+/g, '_');
            optionElement.textContent = option;
            typeSelect.appendChild(optionElement);
        });
    }
    
    // Add change handler for custom type and to reset name field when type changes
    typeSelect.onchange = function() {
        // Reset name field when type changes
        nameInput.value = '';
        nameIdInput.value = '';
        
        if (this.value === 'custom_type') {
            handleCustomType(recipientId);
        }
        
        // Clear any previous search results
        const searchResults = document.getElementById(`searchResults_${recipientId}`);
        if (searchResults) {
            searchResults.innerHTML = '';
            searchResults.style.display = 'none';
        }
    };
}

// Handle custom type selection
function handleCustomType(recipientId) {
    const typeSelect = document.getElementById(`recipientType_${recipientId}`);
    const typeCustom = document.getElementById(`recipientTypeCustom_${recipientId}`);
    const typeIcon = document.getElementById(`recipientTypeIcon_${recipientId}`);
    const backBtn = document.getElementById(`recipientTypeBackBtn_${recipientId}`);
    
    typeSelect.style.display = 'none';
    typeCustom.style.display = 'block';
    typeCustom.style.paddingRight = '3rem';
    backBtn.style.display = 'flex';
    typeIcon.className = 'fas fa-edit input-icon';
    
    setTimeout(() => typeCustom.focus(), 100);
    
    typeSelect.removeAttribute('required');
    typeCustom.setAttribute('required', 'required');
}

// Back to type dropdown
function backToTypeDropdown(recipientId) {
    const typeSelect = document.getElementById(`recipientType_${recipientId}`);
    const typeCustom = document.getElementById(`recipientTypeCustom_${recipientId}`);
    const typeIcon = document.getElementById(`recipientTypeIcon_${recipientId}`);
    const backBtn = document.getElementById(`recipientTypeBackBtn_${recipientId}`);
    
    typeSelect.style.display = 'block';
    typeCustom.style.display = 'none';
    backBtn.style.display = 'none';
    typeIcon.className = 'fas fa-list input-icon';
    
    typeSelect.value = '';
    typeCustom.value = '';
    
    typeSelect.setAttribute('required', 'required');
    typeCustom.removeAttribute('required');
    typeCustom.classList.remove('is-invalid');
    typeSelect.classList.remove('is-invalid');
}

// Load authorized users for payment done via dropdown
function loadAuthorizedUsers() {
    const paymentDoneViaSelect = document.getElementById('paymentDoneVia');
    
    // Make AJAX request to fetch authorized users
    fetch('../api/get_authorized_users.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        paymentDoneViaSelect.innerHTML = '<option value="">Select Authorized User</option>';
        
        if (data.success && data.users.length > 0) {
            data.users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.username} (${user.role})`;
                paymentDoneViaSelect.appendChild(option);
            });
        } else {
            paymentDoneViaSelect.innerHTML = '<option value="">No authorized users found</option>';
        }
    })
    .catch(error => {
        console.error('Error loading authorized users:', error);
        paymentDoneViaSelect.innerHTML = '<option value="">Error loading users</option>';
    });
}

// Load project names based on selected project type
function loadProjectNames() {
    const projectType = document.getElementById('projectType').value;
    const projectNameSelect = document.getElementById('projectName');
    
    // Clear existing options
    projectNameSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (projectType === '') {
        projectNameSelect.innerHTML = '<option value="">First select project type</option>';
        projectNameSelect.disabled = true;
        return;
    }
    
    // Make AJAX request to fetch project names
    fetch('../api/get_projects.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            project_type: projectType
        })
    })
    .then(response => response.json())
    .then(data => {
        projectNameSelect.innerHTML = '<option value="">Select Project Name</option>';
        
        if (data.success && data.projects.length > 0) {
            data.projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.title;
                projectNameSelect.appendChild(option);
            });
            projectNameSelect.disabled = false;
        } else {
            projectNameSelect.innerHTML = '<option value="">No projects found</option>';
            projectNameSelect.disabled = true;
        }
    })
    .catch(error => {
        console.error('Error loading projects:', error);
        projectNameSelect.innerHTML = '<option value="">Error loading projects</option>';
        projectNameSelect.disabled = true;
    });
}

// Submit payment entry form
// Debounce function to limit API calls
let debounceTimeout = null;
function debounceSearch(event, recipientId) {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        searchRecipient(event.target.value, recipientId);
    }, 300); // 300ms delay
}

// Search for recipients based on name input
function searchRecipient(query, recipientId) {
    if (!query || query.length < 2) {
        const searchResults = document.getElementById(`searchResults_${recipientId}`);
        searchResults.innerHTML = '';
        searchResults.style.display = 'none';
        return;
    }
    
    const categorySelect = document.getElementById(`recipientCategory_${recipientId}`);
    const typeSelect = document.getElementById(`recipientType_${recipientId}`);
    const category = categorySelect.value;
    const type = typeSelect.value;
    
    // Only search for vendor or labour categories
    if (category !== 'vendor' && category !== 'supplier' && category !== 'labour') {
        return;
    }
    
    // Make API call to search for recipients
    const searchEndpoint = category === 'labour' ? '../api/search_labour.php' : '../api/search_vendor.php';
    
    fetch(searchEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            query: query,
            type: type
        })
    })
    .then(response => response.json())
    .then(data => {
        const searchResults = document.getElementById(`searchResults_${recipientId}`);
        searchResults.innerHTML = '';
        
        if (data.status === 'success' && data.results && data.results.length > 0) {
            searchResults.style.display = 'block';
            
            data.results.forEach(result => {
                const resultItem = document.createElement('div');
                resultItem.className = 'search-result-item';
                resultItem.textContent = result.name;
                resultItem.addEventListener('click', () => {
                    selectRecipient(result.id, result.name, recipientId);
                });
                searchResults.appendChild(resultItem);
            });
            
            // Add option to add new if not found
            const addNewItem = document.createElement('div');
            addNewItem.className = 'search-result-item add-new';
            addNewItem.innerHTML = `<i class="fas fa-plus-circle me-2"></i>Add new ${category}`;
            addNewItem.addEventListener('click', () => {
                openAddNewModal(category, query, recipientId);
            });
            searchResults.appendChild(addNewItem);
        } else if (query.length >= 2) {
            searchResults.style.display = 'block';
            const noResults = document.createElement('div');
            noResults.className = 'search-result-item no-results';
            noResults.innerHTML = `No ${category} found. <span class="add-new-text">Add new?</span>`;
            noResults.addEventListener('click', () => {
                openAddNewModal(category, query, recipientId);
            });
            searchResults.appendChild(noResults);
        } else {
            searchResults.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error searching recipients:', error);
    });
}

// Select a recipient from search results
function selectRecipient(id, name, recipientId) {
    const nameInput = document.getElementById(`recipientName_${recipientId}`);
    const idInput = document.getElementById(`recipientId_${recipientId}`);
    const searchResults = document.getElementById(`searchResults_${recipientId}`);
    
    nameInput.value = name;
    idInput.value = id;
    searchResults.style.display = 'none';
    
    // Remove any validation errors
    nameInput.classList.remove('is-invalid');
    document.getElementById(`nameError_${recipientId}`).style.display = 'none';
}

// Open modal to add new vendor or labour
function openAddNewModal(category, name, recipientId) {
    // Store the recipient ID to update after adding
    window.currentRecipientToUpdate = recipientId;
    
    if (category === 'labour') {
        // Open labour modal and pre-fill name
        const labourModal = new bootstrap.Modal(document.getElementById('addLabourModal'));
        document.getElementById('labourFullName').value = name;
        labourModal.show();
    } else {
        // Open vendor modal and pre-fill name
        const vendorModal = new bootstrap.Modal(document.getElementById('addVendorModal'));
        document.getElementById('vendorFullName').value = name;
        vendorModal.show();
    }
}

function submitPaymentEntryForm() {
    // Show loader
    const loader = document.getElementById('paymentEntryLoader');
    if (loader) {
        loader.style.display = 'flex';
    }
    
    const form = document.getElementById('addPaymentEntryForm');
    const formData = new FormData(form);
    
    // Basic validation for main payment fields
    const requiredFields = ['projectType', 'projectName', 'paymentDate', 'paymentAmount', 'paymentDoneVia', 'paymentMode'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    // Validate all recipient fields
    const recipientItems = document.querySelectorAll('.recipient-item');
    
    // If no recipients, show error
    if (recipientItems.length === 0) {
        showNotification('warning', 'Please add at least one recipient');
        isValid = false;
    }
    
    recipientItems.forEach((recipientItem, index) => {
        const recipientId = recipientItem.getAttribute('data-recipient-id');
        
        // Ensure form data contains this recipient's data
        formData.append(`recipients[${recipientId}][index]`, index + 1);
        
        const requiredInputs = recipientItem.querySelectorAll('input[required], select[required]');
        
        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
                console.log(`Missing required field: ${input.name}`);
            } else {
                input.classList.remove('is-invalid');
                
                // Make sure the value is properly added to formData
                if (input.name && !input.name.includes('[]')) {
                    // Extract the field name from the input name
                    const fieldMatch = input.name.match(/recipients\[(\d+)\]\[([^\]]+)\]/);
                    if (fieldMatch) {
                        const recipId = fieldMatch[1];
                        const fieldName = fieldMatch[2];
                        console.log(`Ensuring field ${fieldName} for recipient ${recipId} is set to "${input.value.trim()}"`);
                        formData.set(`recipients[${recipId}][${fieldName}]`, input.value.trim());
                    } else {
                        formData.set(input.name, input.value.trim());
                    }
                }
            }
        });
        
        // Special validation for custom type fields
        const typeCustom = document.getElementById(`recipientTypeCustom_${recipientId}`);
        if (typeCustom && typeCustom.style.display !== 'none' && typeCustom.hasAttribute('required')) {
            if (!typeCustom.value.trim()) {
                typeCustom.classList.add('is-invalid');
                isValid = false;
            } else {
                typeCustom.classList.remove('is-invalid');
            }
        }
        
        // Validate that vendor/labour recipients have an ID selected
        const category = document.getElementById(`recipientCategory_${recipientId}`).value;
        const nameInput = document.getElementById(`recipientName_${recipientId}`);
        const idInput = document.getElementById(`recipientId_${recipientId}`);
        
        if ((category === 'vendor' || category === 'supplier' || category === 'labour') && 
            nameInput.value.trim() && !idInput.value.trim()) {
            nameInput.classList.add('is-invalid');
            document.getElementById(`nameError_${recipientId}`).style.display = 'block';
            isValid = false;
        }
        
        // Validate split payments if active
        const splitContainer = document.getElementById(`splitPaymentsContainer_${recipientId}`);
        if (splitContainer && splitContainer.style.display !== 'none') {
            const splitItems = splitContainer.querySelectorAll('.split-payment-item');
            const mainAmount = parseFloat(document.getElementById(`recipientAmount_${recipientId}`).value) || 0;
            let totalSplitAmount = 0;
            
            // If using split payments, make sure the main payment mode is set
            const recipientPaymentMode = document.getElementById(`recipientPaymentMode_${recipientId}`);
            if (recipientPaymentMode) {
                // Force set a value for the payment mode when using splits
                recipientPaymentMode.value = "split_payment";
                recipientPaymentMode.classList.remove('is-invalid');
                
                // Ensure it's in the form data
                formData.set(`recipients[${recipientId}][paymentMode]`, "split_payment");
                console.log(`Setting payment mode for recipient ${recipientId} to "split_payment"`);
                
                // Make sure it's visually indicated as filled
                recipientPaymentMode.readOnly = true;
                recipientPaymentMode.style.backgroundColor = "#f8f9fa";
                recipientPaymentMode.style.cursor = "not-allowed";
            }
            
            // Validate each split payment
            splitItems.forEach((splitItem, splitIndex) => {
                const amountInput = splitItem.querySelector('input[type="number"]');
                const modeSelect = splitItem.querySelector('select');
                const splitId = splitItem.id.split('_').pop();
                
                if (!amountInput.value.trim() || parseFloat(amountInput.value) <= 0) {
                    amountInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    amountInput.classList.remove('is-invalid');
                    totalSplitAmount += parseFloat(amountInput.value);
                    
                    // Ensure the split amount is in formData
                    formData.set(`recipients[${recipientId}][splitPayments][${splitId}][amount]`, amountInput.value.trim());
                }
                
                if (!modeSelect.value.trim()) {
                    modeSelect.classList.add('is-invalid');
                    isValid = false;
                } else {
                    modeSelect.classList.remove('is-invalid');
                    
                    // Ensure the split mode is in formData
                    formData.set(`recipients[${recipientId}][splitPayments][${splitId}][mode]`, modeSelect.value.trim());
                }
            });
            
            // Validate total split amount matches main amount
            if (Math.abs(totalSplitAmount - mainAmount) > 0.01) {
                showNotification('warning', `Recipient #${Array.from(recipientItems).indexOf(recipientItem) + 1}: Split payment total (₹${totalSplitAmount.toFixed(2)}) must equal the main amount (₹${mainAmount.toFixed(2)})`);
                isValid = false;
            }
        }
    });
    
    if (!isValid) {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        showNotification('warning', 'Please fill in all required fields.');
        return;
    }
    
    // Count recipients
    const recipientCount = recipientItems.length;
    formData.set('recipientCount', recipientCount);
    
    // Debug form data before sending
    console.log('Form data to be sent:', Object.fromEntries(formData));
    
    // Send the data to the server using AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../api/save_payment_entry.php', true);
    
    // Add event listener for debugging
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            console.log('Response status:', xhr.status);
            console.log('Response text:', xhr.responseText);
        }
    };
    
    xhr.onload = function() {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    // Show success message with animation
                    let message = 'Payment entry added successfully!';
                    if (recipientCount > 0) {
                        message += ` with ${recipientCount} recipient${recipientCount > 1 ? 's' : ''}.`;
                    }
                    showNotification('success', message);
                    
                    // Reset form and close modal on success
                    form.reset();
                    // Reset project name dropdown
                    const projectNameSelect = document.getElementById('projectName');
                    projectNameSelect.innerHTML = '<option value="">First select project type</option>';
                    projectNameSelect.disabled = true;
                    
                    // Reset all recipients
                    resetAllRecipients();
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addPaymentEntryModal'));
                    modal.hide();
                } else {
                    showNotification('error', 'Error: ' + response.message);
                }
            } catch (e) {
                showNotification('error', 'An error occurred while processing the response.');
                console.error(e);
            }
        } else {
            showNotification('error', 'Request failed. Please try again.');
        }
    };
    xhr.onerror = function() {
        // Hide loader
        if (loader) {
            loader.style.display = 'none';
        }
        
        showNotification('error', 'Request failed. Please check your connection.');
    };
    xhr.send(formData);
}

// Add CSS for search results dropdown
const searchResultsStyles = `
.search-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    margin-top: 5px;
}

.search-result-item {
    padding: 10px 15px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.search-result-item:hover {
    background-color: #f3f4f6;
}

.search-result-item.add-new {
    border-top: 1px solid #e5e7eb;
    color: #3b82f6;
}

.search-result-item.add-new:hover {
    background-color: #f3f4f6;
}

.search-result-item.no-results {
    color: #9ca3af;
}

.search-result-item.no-results:hover {
    background-color: transparent;
}

.search-result-item.no-results .add-new-text {
    color: #3b82f6;
    cursor: pointer;
}

.search-result-item.no-results .add-new-text:hover {
    text-decoration: underline;
}

`;

// Inject CSS into the document
const styleSheet = document.createElement('style');
styleSheet.type = 'text/css';
if (styleSheet.styleSheet){
    styleSheet.styleSheet.cssText = searchResultsStyles;
} else {
    styleSheet.appendChild(document.createTextNode(searchResultsStyles));
}

// Add the styles to the document
const styleElement = document.createElement('style');
styleElement.textContent = searchResultsStyles;
document.head.appendChild(styleElement);

// Initialize when modal is shown
document.getElementById('addPaymentEntryModal').addEventListener('shown.bs.modal', function () {
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('paymentDate').value = today;
    
    // Reset project name dropdown
    const projectNameSelect = document.getElementById('projectName');
    projectNameSelect.innerHTML = '<option value="">First select project type</option>';
    projectNameSelect.disabled = true;
    
    // Reset all recipients
    resetAllRecipients();
    
    // Load authorized users for payment done via
    loadAuthorizedUsers();
});

// Modify vendor and labour form submissions to update recipient after adding
// Store original functions if they exist
const originalVendorFormSubmit = typeof window.submitVendorForm === 'function' ? window.submitVendorForm : null;

// Define a new global submitVendorForm function that will be used by the vendor modal
window.submitVendorForm = function() {
    const form = document.getElementById('addVendorForm');
    const formData = new FormData(form);
    
    // Handle custom vendor type
    const vendorTypeSelect = document.getElementById('vendorType');
    const vendorTypeCustom = document.getElementById('vendorTypeCustom');
    
    let vendorTypeValue = '';
    if (vendorTypeCustom.style.display !== 'none' && vendorTypeCustom.value.trim()) {
        vendorTypeValue = vendorTypeCustom.value.trim();
        formData.set('vendorType', vendorTypeValue);
    } else if (vendorTypeSelect.value) {
        vendorTypeValue = vendorTypeSelect.value;
    }
    
    // Basic validation
    const requiredFields = ['fullName', 'phoneNumber', 'email'];
    let isValid = true;
    
    // Validate basic required fields
    requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    // Validate vendor type (either from dropdown or custom input)
    if (!vendorTypeValue) {
        if (vendorTypeCustom.style.display !== 'none') {
            vendorTypeCustom.classList.add('is-invalid');
        } else {
            vendorTypeSelect.classList.add('is-invalid');
        }
        isValid = false;
    } else {
        vendorTypeCustom.classList.remove('is-invalid');
        vendorTypeSelect.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        showNotification('warning', 'Please fill in all required fields.');
        return;
    }
    
    // Send the data to the server using AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../api/save_vendor.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    // Show success message with animation
                    showNotification('success', 'Vendor added successfully!');
                    
                    // Update recipient if we're adding from payment entry modal
                    if (window.currentRecipientToUpdate) {
                        updateRecipientAfterAdd(response.vendor_id, document.getElementById('vendorFullName').value, 'vendor');
                    }
                    
                    // Reset form and close modal
                    form.reset();
                    backToDropdown(); // Reset vendor type to dropdown
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addVendorModal'));
                    modal.hide();
                    
                    // Refresh vendor list if available
                    if (typeof loadVendors === 'function') {
                        loadVendors();
                    }
                    
                    // If this was a custom vendor type, update all vendor type dropdowns in payment entry modal
                    if (vendorTypeCustom.style.display !== 'none' && vendorTypeCustom.value.trim()) {
                        updateVendorTypesInPaymentEntries(vendorTypeCustom.value.trim());
                    }
                } else {
                    showNotification('error', 'Error: ' + response.message);
                }
            } catch (e) {
                showNotification('error', 'An error occurred while processing the response.');
                console.error(e);
            }
        } else {
            showNotification('error', 'Request failed. Please try again.');
        }
    };
    xhr.onerror = function() {
        showNotification('error', 'Request failed. Please check your connection.');
    };
    xhr.send(formData);
};


// Function to update vendor types in all payment entry recipient dropdowns
function updateVendorTypesInPaymentEntries(newVendorType) {
    // Check if the payment entry modal is open and has recipients
    const paymentEntryModal = document.getElementById('addPaymentEntryModal');
    if (paymentEntryModal && paymentEntryModal.classList.contains('show')) {
        // Get all recipient items
        const recipientItems = document.querySelectorAll('.recipient-item');
        
        recipientItems.forEach(recipientItem => {
            const recipientId = recipientItem.getAttribute('data-recipient-id');
            const categorySelect = document.getElementById(`recipientCategory_${recipientId}`);
            const typeSelect = document.getElementById(`recipientType_${recipientId}`);
            
            // If this recipient is a vendor, update its type options
            if (categorySelect && categorySelect.value === 'vendor' && typeSelect) {
                // Check if the new vendor type already exists in the dropdown
                const existingOption = Array.from(typeSelect.options).find(option => 
                    option.textContent === newVendorType);
                
                // If not, add it to the dropdown
                if (!existingOption) {
                    const optionElement = document.createElement('option');
                    optionElement.value = newVendorType.toLowerCase().replace(/\s+/g, '_');
                    optionElement.textContent = newVendorType;
                    typeSelect.appendChild(optionElement);
                }
            }
        });
    }
}

// Store original labour form submit function
const originalLabourFormSubmit = typeof window.submitLabourForm === 'function' ? window.submitLabourForm : null;

// Define a new global submitLabourForm function that will be used by the labour modal
window.submitLabourForm = function() {
    const form = document.getElementById('addLabourForm');
    const formData = new FormData(form);
    
    const positionSelect = document.getElementById('labourPosition');
    const positionCustom = document.getElementById('labourPositionCustom');
    
    let positionValue = '';
    if (positionCustom.style.display !== 'none' && positionCustom.value.trim()) {
        positionValue = positionCustom.value.trim();
        formData.set('position', positionValue);
    } else if (positionSelect.value) {
        positionValue = positionSelect.value;
    }
    
    const requiredFields = ['fullName', 'phoneNumber', 'joinDate', 'labourType'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    if (!positionValue) {
        if (positionCustom.style.display !== 'none') {
            positionCustom.classList.add('is-invalid');
        } else {
            positionSelect.classList.add('is-invalid');
        }
        isValid = false;
    } else {
        positionCustom.classList.remove('is-invalid');
        positionSelect.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        showNotification('warning', 'Please fill in all required fields.');
        return;
    }
    
    // Send the data to the server using AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../api/save_labour.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    // Show success message with animation
                    showNotification('success', 'Labour added successfully!');
                    
                    // Update recipient if we're adding from payment entry modal
                    if (window.currentRecipientToUpdate) {
                        updateRecipientAfterAdd(response.labour_id, document.getElementById('labourFullName').value, 'labour');
                    }
                    
                    // Reset form and close modal
                    form.reset();
                    backToPositionDropdown();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addLabourModal'));
                    modal.hide();
                    
                    // Refresh labour list if available
                    if (typeof loadLabours === 'function') {
                        loadLabours();
                    }
                } else {
                    showNotification('error', 'Error: ' + response.message);
                }
            } catch (e) {
                showNotification('error', 'An error occurred while processing the response.');
                console.error(e);
            }
        } else {
            showNotification('error', 'Request failed. Please try again.');
        }
    };
    xhr.onerror = function() {
        showNotification('error', 'Request failed. Please check your connection.');
    };
    xhr.send(formData);
};
</script>