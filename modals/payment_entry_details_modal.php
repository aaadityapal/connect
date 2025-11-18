<?php
/**
 * Payment Entry Details Modal
 * Displays complete payment entry information in a minimalist, attractive design
 * Shows data from all payment entry related tables
 */
?>

<!-- Payment Entry Details Modal -->
<div id="paymentEntryDetailsModal" class="payment-modal-overlay">
    <div class="payment-modal-container">
        <!-- Modal Header -->
        <div class="payment-modal-header">
            <div class="payment-modal-title">
                <i class="fas fa-receipt"></i>
                <h2>Payment Entry Details</h2>
            </div>
            <button class="payment-modal-close" onclick="closePaymentEntryDetailsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body with Tabs -->
        <div class="payment-modal-body">
            <!-- Tab Navigation -->
            <div class="payment-details-tabs">
                <button class="payment-tab-btn active" onclick="switchPaymentTab('overview')">
                    <i class="fas fa-cube"></i> Overview
                </button>
                <button class="payment-tab-btn" onclick="switchPaymentTab('acceptance')">
                    <i class="fas fa-handshake"></i> Acceptance Methods
                </button>
                <button class="payment-tab-btn" onclick="switchPaymentTab('lineItems')">
                    <i class="fas fa-list"></i> Line Items
                </button>
                <button class="payment-tab-btn" onclick="switchPaymentTab('files')">
                    <i class="fas fa-file"></i> Attachments
                </button>
                <button class="payment-tab-btn" onclick="switchPaymentTab('audit')">
                    <i class="fas fa-history"></i> Audit Log
                </button>
            </div>

            <!-- Loading State -->
            <div id="paymentDetailsLoading" class="payment-loading-state">
                <div class="payment-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <p>Loading payment entry details...</p>
            </div>

            <!-- Tab Contents -->
            <div class="payment-tab-content">
                <!-- Overview Tab -->
                <div id="overview-tab" class="payment-tab-pane active">
                    <!-- Main Payment Info Card -->
                    <div class="payment-card">
                        <div class="payment-card-header">
                            <h3>Main Payment Information</h3>
                            <span id="detailsStatusBadge" class="payment-status-badge"></span>
                        </div>
                        <div class="payment-card-body">
                            <div class="payment-grid">
                                <div class="payment-field">
                                    <label>Entry ID</label>
                                    <div class="payment-value" id="detailsEntryId"></div>
                                </div>
                                <div class="payment-field">
                                    <label>Project Type</label>
                                    <div class="payment-value" id="detailsProjectType"></div>
                                </div>
                                <div class="payment-field">
                                    <label>Project Name</label>
                                    <div class="payment-value" id="detailsProjectName"></div>
                                </div>
                                <div class="payment-field">
                                    <label>Payment Date</label>
                                    <div class="payment-value" id="detailsPaymentDate"></div>
                                </div>
                                <div class="payment-field">
                                    <label>Main Amount</label>
                                    <div class="payment-value payment-amount" id="detailsMainAmount"></div>
                                </div>
                                <div class="payment-field">
                                    <label>Payment Mode</label>
                                    <div class="payment-value payment-badge" id="detailsPaymentMode"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Card -->
                    <div class="payment-card">
                        <div class="payment-card-header">
                            <h3>Payment Summary</h3>
                        </div>
                        <div class="payment-card-body">
                            <div class="payment-summary-grid">
                                <div class="payment-summary-item">
                                    <div class="payment-summary-icon" style="background: #ebf8ff; color: #3182ce;">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <div class="payment-summary-info">
                                        <div class="payment-summary-label">Main Payment</div>
                                        <div class="payment-summary-value" id="detailsSummaryMain"></div>
                                    </div>
                                </div>
                                <div class="payment-summary-item">
                                    <div class="payment-summary-icon" style="background: #fef5e7; color: #d69e2e;">
                                        <i class="fas fa-handshake"></i>
                                    </div>
                                    <div class="payment-summary-info">
                                        <div class="payment-summary-label">Acceptance Methods</div>
                                        <div class="payment-summary-value" id="detailsSummaryAcceptance"></div>
                                    </div>
                                </div>
                                <div class="payment-summary-item">
                                    <div class="payment-summary-icon" style="background: #f0fff4; color: #38a169;">
                                        <i class="fas fa-list-ul"></i>
                                    </div>
                                    <div class="payment-summary-info">
                                        <div class="payment-summary-label">Line Items</div>
                                        <div class="payment-summary-value" id="detailsSummaryLineItems"></div>
                                    </div>
                                </div>
                                <div class="payment-summary-item">
                                    <div class="payment-summary-icon" style="background: #fff5f5; color: #e53e3e;">
                                        <i class="fas fa-file"></i>
                                    </div>
                                    <div class="payment-summary-info">
                                        <div class="payment-summary-label">Attachments</div>
                                        <div class="payment-summary-value" id="detailsSummaryFiles"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grand Total Card -->
                    <div class="payment-card payment-grand-total-card">
                        <div class="payment-card-body">
                            <div class="payment-grand-total">
                                <div class="payment-grand-total-label">Grand Total</div>
                                <div class="payment-grand-total-amount" id="detailsGrandTotal"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Metadata Card -->
                    <div class="payment-card">
                        <div class="payment-card-header">
                            <h3>Entry Metadata</h3>
                        </div>
                        <div class="payment-card-body">
                            <div class="payment-grid">
                                <div class="payment-field">
                                    <label>Created By</label>
                                    <div class="payment-value" id="detailsCreatedBy"></div>
                                </div>
                                <div class="payment-field">
                                    <label>Created Date</label>
                                    <div class="payment-value" id="detailsCreatedDate"></div>
                                </div>
                                <div class="payment-field">
                                    <label>Last Updated</label>
                                    <div class="payment-value" id="detailsUpdatedDate"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acceptance Methods Tab -->
                <div id="acceptance-tab" class="payment-tab-pane">
                    <div id="acceptanceMethodsContent" class="payment-acceptance-list"></div>
                </div>

                <!-- Line Items Tab -->
                <div id="lineItems-tab" class="payment-tab-pane">
                    <div id="lineItemsContent" class="payment-line-items-list"></div>
                </div>

                <!-- Files Tab -->
                <div id="files-tab" class="payment-tab-pane">
                    <div id="filesContent" class="payment-files-list"></div>
                </div>

                <!-- Audit Log Tab -->
                <div id="audit-tab" class="payment-tab-pane">
                    <div id="auditContent" class="payment-audit-log"></div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="payment-modal-footer">
            <button class="payment-btn payment-btn-secondary" onclick="closePaymentEntryDetailsModal()">
                <i class="fas fa-times"></i> Close
            </button>
            <button class="payment-btn payment-btn-primary" id="editPaymentBtn">
                <i class="fas fa-edit"></i> Edit Entry
            </button>
        </div>
    </div>
</div>

<style>
    /* Modal Overlay */
    .payment-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        overflow-y: auto;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .payment-modal-overlay.active {
        display: flex;
    }

    /* Modal Container */
    .payment-modal-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        width: 100%;
        max-width: 1000px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Modal Header */
    .payment-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px 30px;
        border-bottom: 2px solid #e2e8f0;
        background: linear-gradient(135deg, #2a4365 0%, #1a365d 100%);
    }

    .payment-modal-title {
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
    }

    .payment-modal-title h2 {
        font-size: 1.4em;
        font-weight: 600;
        margin: 0;
    }

    .payment-modal-title i {
        font-size: 1.3em;
        opacity: 0.9;
    }

    .payment-modal-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2em;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .payment-modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }

    /* Modal Body */
    .payment-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 0;
    }

    /* Tab Navigation */
    .payment-details-tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #e2e8f0;
        background: white;
        padding: 0 30px;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .payment-tab-btn {
        background: transparent;
        border: none;
        padding: 15px 20px;
        font-size: 0.9em;
        font-weight: 500;
        color: #a0aec0;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .payment-tab-btn:hover {
        color: #2a4365;
    }

    .payment-tab-btn.active {
        color: #2a4365;
        border-bottom-color: #2a4365;
    }

    /* Tab Content */
    .payment-tab-content {
        padding: 30px;
    }

    .payment-tab-pane {
        display: none;
    }

    .payment-tab-pane.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Loading State */
    .payment-loading-state {
        text-align: center;
        padding: 60px 20px;
        color: #a0aec0;
    }

    .payment-spinner {
        font-size: 2.5em;
        color: #2a4365;
        margin-bottom: 15px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Cards */
    .payment-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .payment-card:hover {
        box-shadow: 0 4px 12px rgba(42, 67, 101, 0.08);
        border-color: #cbd5e0;
    }

    .payment-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: #f7fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .payment-card-header h3 {
        font-size: 1em;
        font-weight: 600;
        color: #2a4365;
        margin: 0;
    }

    .payment-card-body {
        padding: 20px;
    }

    /* Status Badge */
    .payment-status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        text-transform: uppercase;
    }

    .payment-status-badge.submitted {
        background: #edf2f7;
        color: #2d3748;
    }

    .payment-status-badge.approved {
        background: #c6f6d5;
        color: #22543d;
    }

    .payment-status-badge.rejected {
        background: #fed7d7;
        color: #742a2a;
    }

    .payment-status-badge.pending {
        background: #feebc8;
        color: #7c2d12;
    }

    .payment-status-badge.draft {
        background: #cbd5e0;
        color: #2d3748;
    }

    /* Grid Layout */
    .payment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .payment-field {
        display: flex;
        flex-direction: column;
    }

    .payment-field label {
        font-size: 0.85em;
        color: #718096;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .payment-value {
        font-size: 0.95em;
        color: #2d3748;
        font-weight: 500;
        word-break: break-word;
        min-height: 1.2em;
        padding: 4px 0;
    }

    .payment-amount {
        color: #38a169;
        font-weight: 700;
        font-size: 1.1em;
    }

    .payment-badge {
        display: inline-block;
        background: #f0f4f8;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 0.85em;
        text-transform: uppercase;
        width: fit-content;
    }

    /* Summary Grid */
    .payment-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .payment-summary-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: #f7fafc;
        border-radius: 8px;
    }

    .payment-summary-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3em;
        flex-shrink: 0;
    }

    .payment-summary-info {
        flex: 1;
    }

    .payment-summary-label {
        font-size: 0.85em;
        color: #718096;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .payment-summary-value {
        font-size: 1.2em;
        color: #2a4365;
        font-weight: 700;
    }

    /* Grand Total Card */
    .payment-grand-total-card {
        background: linear-gradient(135deg, #38a169 0%, #2d6a4f 100%);
        border: none;
        margin-bottom: 20px;
    }

    .payment-grand-total {
        text-align: center;
        color: white;
        padding: 10px 0;
    }

    .payment-grand-total-label {
        font-size: 0.9em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.9;
        margin-bottom: 8px;
    }

    .payment-grand-total-amount {
        font-size: 2.5em;
        font-weight: 700;
        letter-spacing: 1px;
    }

    /* Lists */
    .payment-acceptance-list,
    .payment-line-items-list,
    .payment-files-list,
    .payment-audit-log {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .payment-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        transition: all 0.2s ease;
    }

    .payment-item:hover {
        border-color: #cbd5e0;
        box-shadow: 0 2px 8px rgba(42, 67, 101, 0.05);
    }

    .payment-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .payment-item-title {
        font-weight: 600;
        color: #2a4365;
        font-size: 0.95em;
    }

    .payment-item-meta {
        font-size: 0.85em;
        color: #718096;
    }

    .payment-item-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }

    .payment-item-field {
        display: flex;
        flex-direction: column;
    }

    .payment-item-field-label {
        font-size: 0.8em;
        color: #a0aec0;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .payment-item-field-value {
        font-size: 0.9em;
        color: #2d3748;
        font-weight: 500;
    }

    /* Modal Footer */
    .payment-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 20px 30px;
        border-top: 1px solid #e2e8f0;
        background: #f7fafc;
    }

    /* Buttons */
    .payment-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 0.9em;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .payment-btn-primary {
        background: #2a4365;
        color: white;
    }

    .payment-btn-primary:hover {
        background: #1a365d;
        transform: translateY(-1px);
    }

    .payment-btn-secondary {
        background: #e2e8f0;
        color: #2a4365;
    }

    .payment-btn-secondary:hover {
        background: #cbd5e0;
    }

    /* Empty State */
    .payment-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }

    .payment-empty-state i {
        font-size: 2em;
        color: #cbd5e0;
        margin-bottom: 10px;
        display: block;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .payment-modal-container {
            max-width: 100%;
            border-radius: 8px;
        }

        .payment-details-tabs {
            overflow-x: auto;
            padding: 0 15px;
        }

        .payment-tab-btn {
            padding: 12px 15px;
            font-size: 0.85em;
        }

        .payment-tab-content {
            padding: 20px 15px;
        }

        .payment-card-header {
            padding: 12px 15px;
        }

        .payment-card-body {
            padding: 15px;
        }

        .payment-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .payment-modal-footer {
            padding: 15px;
        }

        .payment-grand-total-amount {
            font-size: 1.8em;
        }
    }
</style>

<script>
    // Payment Entry Details Modal Functions
    let currentPaymentEntryId = null;

    function openPaymentEntryDetailsModal(entryId) {
        currentPaymentEntryId = entryId;
        const modal = document.getElementById('paymentEntryDetailsModal');
        const loading = document.getElementById('paymentDetailsLoading');
        
        // Show modal with loading state
        modal.classList.add('active');
        loading.style.display = 'flex';

        // Fetch payment entry details
        fetchPaymentEntryDetails(entryId);
    }

    function closePaymentEntryDetailsModal() {
        const modal = document.getElementById('paymentEntryDetailsModal');
        modal.classList.remove('active');
        currentPaymentEntryId = null;
    }

    function switchPaymentTab(tabName) {
        // Remove active from all tabs and panes
        document.querySelectorAll('.payment-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.payment-tab-pane').forEach(pane => pane.classList.remove('active'));

        // Add active to selected tab and pane
        event.target.closest('.payment-tab-btn').classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
    }

    function fetchPaymentEntryDetails(entryId) {
        fetch(`get_payment_entry_details.php?payment_entry_id=${entryId}`)
            .then(response => {
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    displayPaymentEntryDetails(data.data);
                } else {
                    showPaymentError('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showPaymentError('Failed to load payment entry details: ' + error.message);
            });
    }

    function displayPaymentEntryDetails(data) {
        try {
            const loading = document.getElementById('paymentDetailsLoading');
            if (loading) {
                loading.style.display = 'none';
            }

            const master = data.master_record;
            const summary = data.summary_totals;
            const acceptanceMethods = data.acceptance_methods || [];
            const lineItems = data.line_items || [];
            const files = data.file_attachments || [];
            const auditLog = data.audit_log || [];

            // Overview Tab
            document.getElementById('detailsEntryId').textContent = '#' + master.payment_entry_id;
            document.getElementById('detailsProjectType').textContent = master.project_type_category || 'N/A';
            document.getElementById('detailsProjectName').textContent = master.project_title || master.project_name_reference || 'N/A';
            
            // Format and set payment date
            const paymentDateFormatted = formatDate(master.payment_date_logged);
            const paymentDateElement = document.getElementById('detailsPaymentDate');
            
            if (paymentDateElement) {
                paymentDateElement.textContent = paymentDateFormatted;
            }
            
            document.getElementById('detailsMainAmount').textContent = '₹' + parseFloat(master.payment_amount_base).toFixed(2);
            
            const paymentModeText = master.payment_mode_selected ? master.payment_mode_selected.replace(/_/g, ' ').toUpperCase() : 'N/A';
            document.getElementById('detailsPaymentMode').textContent = paymentModeText;

            // Status Badge
            const statusBadge = document.getElementById('detailsStatusBadge');
            statusBadge.textContent = master.entry_status_current.toUpperCase();
            statusBadge.className = 'payment-status-badge ' + master.entry_status_current;

            // Summary Card
            document.getElementById('detailsSummaryMain').textContent = '₹' + parseFloat(master.payment_amount_base).toFixed(2);
            document.getElementById('detailsSummaryAcceptance').textContent = acceptanceMethods.length;
            document.getElementById('detailsSummaryLineItems').textContent = lineItems.length;
            document.getElementById('detailsSummaryFiles').textContent = files.length;

            // Grand Total
            const grandTotal = summary?.total_amount_grand_aggregate || master.payment_amount_base;
            document.getElementById('detailsGrandTotal').textContent = '₹' + parseFloat(grandTotal).toFixed(2);

            // Metadata
            document.getElementById('detailsCreatedBy').textContent = master.created_by_username || 'System';
            document.getElementById('detailsCreatedDate').textContent = formatDateTime(master.created_timestamp_utc);
            document.getElementById('detailsUpdatedDate').textContent = formatDateTime(master.updated_timestamp_utc);

            // Acceptance Methods Tab
            displayAcceptanceMethods(acceptanceMethods);

            // Line Items Tab
            displayLineItems(lineItems);

            // Files Tab
            displayFileAttachments(files);

            // Audit Log Tab
            displayAuditLog(auditLog);

            // Edit button handler
            document.getElementById('editPaymentBtn').onclick = () => {
                closePaymentEntryDetailsModal();
                editPaymentEntry(currentPaymentEntryId);
            };
        } catch (error) {
            console.error('Display Error:', error);
            showPaymentError('Error displaying payment entry: ' + error.message);
        }
    }

    function displayAcceptanceMethods(methods) {
        const container = document.getElementById('acceptanceMethodsContent');
        
        if (methods.length === 0) {
            container.innerHTML = '<div class="payment-empty-state"><i class="fas fa-inbox"></i><p>No acceptance methods recorded</p></div>';
            return;
        }

        let html = '';
        methods.forEach((method, index) => {
            html += `
                <div class="payment-item">
                    <div class="payment-item-header">
                        <div class="payment-item-title">Method ${index + 1}: ${method.payment_method_type.replace(/_/g, ' ').toUpperCase()}</div>
                        <div class="payment-item-meta">₹${parseFloat(method.amount_received_value).toFixed(2)}</div>
                    </div>
                    <div class="payment-item-content">
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Method Type</div>
                            <div class="payment-item-field-value">${method.payment_method_type}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Amount</div>
                            <div class="payment-item-field-value">₹${parseFloat(method.amount_received_value).toFixed(2)}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Reference</div>
                            <div class="payment-item-field-value">${method.reference_number_cheque || '-'}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Recorded Date</div>
                            <div class="payment-item-field-value">${formatDateTime(method.recorded_timestamp)}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function displayLineItems(items) {
        const container = document.getElementById('lineItemsContent');
        
        if (items.length === 0) {
            container.innerHTML = '<div class="payment-empty-state"><i class="fas fa-inbox"></i><p>No line items recorded</p></div>';
            return;
        }

        let html = '';
        items.forEach((item, index) => {
            html += `
                <div class="payment-item">
                    <div class="payment-item-header">
                        <div class="payment-item-title">Item ${index + 1}: ${item.recipient_type_category}</div>
                        <div class="payment-item-meta">₹${parseFloat(item.line_item_amount).toFixed(2)}</div>
                    </div>
                    <div class="payment-item-content">
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Recipient</div>
                            <div class="payment-item-field-value">${item.recipient_name_display || '-'}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Type</div>
                            <div class="payment-item-field-value">${item.recipient_type_category}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Amount</div>
                            <div class="payment-item-field-value">₹${parseFloat(item.line_item_amount).toFixed(2)}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Description</div>
                            <div class="payment-item-field-value">${item.payment_description_notes || '-'}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function displayFileAttachments(files) {
        const container = document.getElementById('filesContent');
        
        if (files.length === 0) {
            container.innerHTML = '<div class="payment-empty-state"><i class="fas fa-file"></i><p>No file attachments</p></div>';
            return;
        }

        let html = '';
        files.forEach(file => {
            const fileSize = (file.attachment_file_size_bytes / 1024).toFixed(2);
            html += `
                <div class="payment-item">
                    <div class="payment-item-header">
                        <div class="payment-item-title"><i class="fas fa-file"></i> ${file.attachment_file_original_name}</div>
                        <div class="payment-item-meta">${fileSize} KB</div>
                    </div>
                    <div class="payment-item-content">
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Type</div>
                            <div class="payment-item-field-value">${file.attachment_type_category}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">MIME Type</div>
                            <div class="payment-item-field-value">${file.attachment_file_mime_type}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Uploaded</div>
                            <div class="payment-item-field-value">${formatDateTime(file.attachment_upload_timestamp)}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function displayAuditLog(logs) {
        const container = document.getElementById('auditContent');
        
        if (logs.length === 0) {
            container.innerHTML = '<div class="payment-empty-state"><i class="fas fa-history"></i><p>No audit logs available</p></div>';
            return;
        }

        let html = '';
        logs.forEach(log => {
            html += `
                <div class="payment-item">
                    <div class="payment-item-header">
                        <div class="payment-item-title">${log.audit_action_type.replace(/_/g, ' ').toUpperCase()}</div>
                        <div class="payment-item-meta">${formatDateTime(log.audit_action_timestamp_utc)}</div>
                    </div>
                    <div class="payment-item-content">
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Performed By</div>
                            <div class="payment-item-field-value">${log.performed_by_username}</div>
                        </div>
                        <div class="payment-item-field">
                            <div class="payment-item-field-label">Description</div>
                            <div class="payment-item-field-value">${log.audit_change_description || '-'}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function showPaymentError(message) {
        const loading = document.getElementById('paymentDetailsLoading');
        loading.innerHTML = `
            <div class="payment-empty-state">
                <i class="fas fa-exclamation-circle"></i>
                <p>${message}</p>
            </div>
        `;
    }

    // Utility Functions
    function formatDate(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                console.warn('Invalid date:', dateString);
                return dateString;
            }
            return date.toLocaleDateString('en-IN', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        } catch (error) {
            console.error('Date format error:', error);
            return dateString;
        }
    }

    function formatDateTime(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                console.warn('Invalid datetime:', dateString);
                return dateString;
            }
            return date.toLocaleDateString('en-IN', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (error) {
            console.error('DateTime format error:', error);
            return dateString;
        }
    }

    // Close modal when clicking overlay
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('paymentEntryDetailsModal');
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closePaymentEntryDetailsModal();
            }
        });
    });
</script>
