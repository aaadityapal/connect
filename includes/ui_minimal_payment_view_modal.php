<!-- New Minimalistic Payment Entry View Modal -->
<div class="modal fade" id="uiMinimalPaymentViewModal" tabindex="-1" aria-labelledby="uiMinimalPaymentViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content ui-minimal-modal-wrapper">
            <!-- Modal Header -->
            <div class="ui-minimal-modal-header">
                <div class="ui-modal-title-section">
                    <div class="ui-payment-indicator">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="ui-title-content">
                        <h5 class="ui-modal-title" id="uiMinimalPaymentViewModalLabel">Payment Details</h5>
                        <span class="ui-modal-subtitle">Entry #PE-001</span>
                    </div>
                </div>
                <button type="button" class="ui-close-btn" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="ui-minimal-modal-body">
                <!-- Loading State -->
                <div class="ui-loading-state" id="uiPaymentDetailsLoader">
                    <div class="ui-spinner"></div>
                    <p class="ui-loading-text">Loading...</p>
                </div>

                <!-- Error State -->
                <div class="ui-error-state" id="uiPaymentDetailsError" style="display: none;">
                    <div class="ui-error-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <p class="ui-error-text" id="uiPaymentErrorMessage">Unable to load payment details</p>
                    <button class="ui-retry-btn" onclick="retryLoadPaymentDetails()">
                        <i class="fas fa-redo me-2"></i>Retry
                    </button>
                </div>

                <!-- Payment Details Content -->
                <div class="ui-payment-details-content" id="uiPaymentDetailsContent" style="display: none;">
                    <!-- Payment Overview -->
                    <div class="ui-detail-section">
                        <div class="ui-section-header">
                            <i class="fas fa-money-check-alt ui-section-icon"></i>
                            <span class="ui-section-title">Payment Overview</span>
                        </div>
                        <div class="ui-overview-layout">
                            <!-- First Row: Project Name and Date -->
                            <div class="ui-overview-row">
                                <div class="ui-overview-left">
                                    <span class="ui-overview-label">Project Name</span>
                                    <span class="ui-overview-value" id="uiPaymentProject">-</span>
                                </div>
                                <div class="ui-overview-right">
                                    <span class="ui-overview-label">Date</span>
                                    <span class="ui-overview-value" id="uiPaymentDate">-</span>
                                </div>
                            </div>
                            <!-- Second Row: Amount (Centered) -->
                            <div class="ui-overview-row ui-overview-center">
                                <div class="ui-overview-amount">
                                    <span class="ui-overview-amount-label">Total Amount</span>
                                    <span class="ui-overview-amount-value" id="uiPaymentAmount">-</span>
                                    <a href="#" class="ui-screenshot-link" id="uiViewScreenshot" onclick="viewPaymentScreenshot()">
                                        <i class="fas fa-image me-1"></i>View Screenshot
                                    </a>
                                </div>
                            </div>
                            <!-- Third Row: Payment Via and Payment Mode -->
                            <div class="ui-overview-row">
                                <div class="ui-overview-left">
                                    <span class="ui-overview-label">Payment Via</span>
                                    <span class="ui-overview-value" id="uiPaymentMethod">-</span>
                                </div>
                                <div class="ui-overview-right">
                                    <span class="ui-overview-label">Payment Mode</span>
                                    <div class="ui-payment-mode-wrapper">
                                        <span class="ui-overview-value" id="uiPaymentType">-</span>
                                        <button class="ui-split-toggle" id="uiSplitToggle" onclick="toggleSplitDetails()" style="display: none;">
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Split Payment Details Section -->
                    <div class="ui-detail-section ui-split-section" id="uiSplitSection" style="display: none;">
                        <div class="ui-section-header">
                            <i class="fas fa-layer-group ui-section-icon"></i>
                            <span class="ui-section-title">Payment Splits</span>
                        </div>
                        <div class="ui-split-container" id="uiSplitContainer">
                            <!-- Split items will be loaded here -->
                            <div class="ui-split-loading" id="uiSplitLoading">
                                <div class="ui-mini-spinner"></div>
                                <span>Loading split details...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment To Section -->
                    <div class="ui-detail-section" id="uiPaymentToSection">
                        <div class="ui-section-header">
                            <i class="fas fa-users ui-section-icon"></i>
                            <span class="ui-section-title">Payment To</span>
                            <span class="ui-recipients-count-badge" id="uiRecipientsCount">0</span>
                        </div>
                        <div class="ui-recipients-container" id="uiRecipientsContainer">
                            <!-- Recipients will be populated here -->
                        </div>
                        <div class="ui-no-recipients" id="uiNoRecipients" style="display: none;">
                            <div class="ui-empty-state">
                                <i class="fas fa-users-slash ui-empty-icon"></i>
                                <p class="ui-empty-text">No recipients found for this payment</p>
                            </div>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="ui-detail-section" id="uiNotesSection" style="display: none;">
                        <div class="ui-section-header">
                            <i class="fas fa-sticky-note ui-section-icon"></i>
                            <span class="ui-section-title">Notes</span>
                        </div>
                        <div class="ui-notes-container">
                            <p class="ui-notes-text" id="uiPaymentNotes">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="ui-minimal-modal-footer">
                <button type="button" class="ui-btn ui-btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="ui-btn ui-btn-primary" id="uiEditPaymentBtn" onclick="editPaymentFromView()">
                    <i class="fas fa-edit me-2"></i>Edit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Styles -->
<style>
    /* UI Minimal Modal Styles */
    .ui-minimal-modal-wrapper {
        border: none;
        border-radius: 12px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        background: #ffffff;
    }

    .ui-minimal-modal-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: #1e293b;
        padding: 1.25rem 1.75rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .ui-modal-title-section {
        display: flex;
        align-items: center;
        gap: 0.875rem;
    }

    .ui-payment-indicator {
        width: 42px;
        height: 42px;
        background: #3b82f6;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.125rem;
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    }

    .ui-title-content {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .ui-modal-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #1e293b;
        letter-spacing: -0.025em;
    }

    .ui-modal-subtitle {
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 500;
    }

    .ui-close-btn {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        cursor: pointer;
        font-size: 0.875rem;
    }

    .ui-close-btn:hover {
        background: #e2e8f0;
        border-color: #cbd5e1;
        color: #475569;
        transform: scale(1.05);
    }

    .ui-minimal-modal-body {
        padding: 1.5rem;
        background: #ffffff;
        min-height: 350px;
    }

    .ui-loading-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem;
        text-align: center;
    }

    .ui-spinner {
        width: 32px;
        height: 32px;
        border: 2px solid #f1f5f9;
        border-top: 2px solid #3b82f6;
        border-radius: 50%;
        animation: ui-spin 0.8s linear infinite;
        margin-bottom: 0.875rem;
    }

    @keyframes ui-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .ui-loading-text {
        color: #64748b;
        margin: 0;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .ui-error-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem;
        text-align: center;
    }

    .ui-error-icon {
        width: 52px;
        height: 52px;
        background: #fef2f2;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.875rem;
        color: #ef4444;
        font-size: 1.25rem;
    }

    .ui-error-text {
        color: #64748b;
        margin: 0 0 1.25rem 0;
        font-size: 0.875rem;
    }

    .ui-retry-btn {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    .ui-retry-btn:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .ui-payment-details-content {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* Section Layout */
    .ui-detail-section {
        background: white;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
        overflow: hidden;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .ui-detail-section:hover {
        border-color: #e2e8f0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    }

    .ui-section-header {
        background: #f8fafc;
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .ui-section-icon {
        color: #3b82f6;
        font-size: 0.875rem;
        width: 16px;
        text-align: center;
    }

    .ui-section-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin: 0;
    }

    /* Overview Layout Styles */
    .ui-overview-layout {
        padding: 1.5rem;
    }

    .ui-overview-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        padding: 1rem 0;
    }

    .ui-overview-row:last-child {
        margin-bottom: 0;
    }

    .ui-overview-center {
        justify-content: center;
        padding: 1.5rem 0;
    }

    .ui-overview-left,
    .ui-overview-right {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        flex: 1;
    }

    .ui-overview-right {
        align-items: flex-end;
        text-align: right;
    }

    .ui-overview-label {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .ui-overview-value {
        font-size: 1rem;
        color: #1e293b;
        font-weight: 600;
        line-height: 1.4;
        border-bottom: 2px solid #cbd5e1;
        padding-bottom: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .ui-overview-amount {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }

    .ui-overview-amount-label {
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .ui-overview-amount-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #16a34a;
        text-align: center;
        line-height: 1.2;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        border-bottom: 4px solid #16a34a;
        padding-bottom: 1rem;
        margin-bottom: 0.75rem;
        min-width: 200px;
    }

    .ui-screenshot-link {
        font-size: 0.75rem;
        color: #3b82f6;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        border: 1px solid #dbeafe;
        border-radius: 6px;
        background-color: #f8faff;
        transition: all 0.2s ease;
        font-weight: 500;
    }

    .ui-screenshot-link:hover {
        color: #2563eb;
        background-color: #eff6ff;
        border-color: #bfdbfe;
        text-decoration: none;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .ui-screenshot-link i {
        font-size: 0.7rem;
    }

    /* Split Payment Styles */
    .ui-payment-mode-wrapper {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .ui-split-toggle {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
        width: 24px;
        height: 24px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.7rem;
    }

    .ui-split-toggle:hover {
        background: #e2e8f0;
        color: #475569;
        transform: scale(1.05);
    }

    .ui-split-toggle.expanded {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }

    .ui-split-toggle.expanded i {
        transform: rotate(180deg);
    }

    .ui-split-section {
        border-left: 4px solid #f59e0b;
    }

    .ui-split-container {
        padding: 1.25rem;
    }

    .ui-split-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 2rem;
        color: #64748b;
        font-size: 0.875rem;
    }

    .ui-mini-spinner {
        width: 20px;
        height: 20px;
        border: 2px solid #f1f5f9;
        border-top: 2px solid #3b82f6;
        border-radius: 50%;
        animation: ui-spin 0.8s linear infinite;
    }

    .ui-split-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .ui-split-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
        transition: all 0.2s ease;
    }

    .ui-split-item:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .ui-split-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .ui-split-number {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        background: #e2e8f0;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    .ui-split-amount {
        font-size: 1.125rem;
        font-weight: 600;
        color: #16a34a;
    }

    .ui-split-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .ui-split-detail {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .ui-split-label {
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .ui-split-value {
        font-size: 0.875rem;
        color: #1e293b;
        font-weight: 500;
    }

    .ui-split-proof {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .ui-split-proof-link {
        font-size: 0.75rem;
        color: #3b82f6;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border: 1px solid #dbeafe;
        border-radius: 4px;
        background-color: #f8faff;
        transition: all 0.2s ease;
    }

    .ui-split-proof-link:hover {
        color: #2563eb;
        background-color: #eff6ff;
        border-color: #bfdbfe;
        text-decoration: none;
    }

    /* Add separators between rows */
    .ui-overview-row:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 1.5rem;
        right: 1.5rem;
        bottom: -0.75rem;
        height: 1px;
        background: linear-gradient(to right, transparent, #e2e8f0, transparent);
    }

    .ui-overview-layout {
        position: relative;
    }

    .ui-detail-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem;
        background: #f8fafc;
        border-radius: 8px;
        transition: all 0.2s ease;
        border: 1px solid #f1f5f9;
    }

    .ui-detail-item:hover {
        background: #f1f5f9;
        border-color: #e2e8f0;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .ui-detail-icon {
        width: 32px;
        height: 32px;
        background: #dbeafe;
        color: #3b82f6;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        flex-shrink: 0;
        margin-top: 0.125rem;
    }

    .ui-amount-icon {
        background: #dcfce7;
        color: #16a34a;
    }

    .ui-detail-content {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
        flex: 1;
    }

    .ui-detail-label {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .ui-detail-value {
        font-size: 0.875rem;
        color: #1e293b;
        font-weight: 500;
        line-height: 1.4;
        word-break: break-word;
    }

    .ui-amount-value {
        color: #16a34a;
        font-weight: 600;
        font-size: 1rem;
    }

    .ui-detail-badge {
        display: inline-flex;
        padding: 0.25rem 0.625rem;
        background: #dbeafe;
        color: #1e40af;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        width: fit-content;
        text-transform: capitalize;
        border: 1px solid #bfdbfe;
    }

    .ui-status-badge {
        display: inline-flex;
        padding: 0.25rem 0.625rem;
        background: #dcfce7;
        color: #15803d;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        width: fit-content;
        text-transform: capitalize;
        border: 1px solid #bbf7d0;
    }

    /* Notes Section */
    .ui-notes-container {
        padding: 1.25rem;
    }

    .ui-notes-text {
        color: #475569;
        line-height: 1.6;
        margin: 0;
        font-size: 0.875rem;
        background: #f8fafc;
        padding: 0.875rem;
        border-radius: 8px;
        border-left: 3px solid #3b82f6;
        border: 1px solid #f1f5f9;
    }

    /* Footer */
    .ui-minimal-modal-footer {
        background: #f8fafc;
        padding: 1rem 1.75rem;
        border-top: 1px solid #f1f5f9;
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }

    .ui-btn {
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.875rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        letter-spacing: -0.025em;
    }

    .ui-btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .ui-btn-secondary:hover {
        background: #e2e8f0;
        color: #334155;
        border-color: #cbd5e1;
    }

    .ui-btn-primary {
        background: #3b82f6;
        color: white;
        border: 1px solid #3b82f6;
    }

    .ui-btn-primary:hover {
        background: #2563eb;
        border-color: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .ui-minimal-modal-header {
            padding: 1rem 1.25rem;
        }

        .ui-minimal-modal-body {
            padding: 1rem;
        }

        .ui-detail-grid {
            grid-template-columns: 1fr;
            gap: 0.75rem;
            padding: 1rem;
        }

        .ui-minimal-modal-footer {
            padding: 0.875rem 1.25rem;
            flex-direction: column-reverse;
        }

        .ui-btn {
            justify-content: center;
        }

        .ui-detail-item {
            padding: 0.625rem;
        }

        .ui-section-header {
            padding: 0.75rem 1rem;
        }

        .ui-notes-container {
            padding: 1rem;
        }

        /* Overview Layout Responsive */
        .ui-overview-layout {
            padding: 1rem;
        }

        .ui-overview-row {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }

        .ui-overview-left,
        .ui-overview-right {
            align-items: flex-start;
            text-align: left;
        }

        .ui-overview-amount-value {
            font-size: 2rem;
        }

        .ui-overview-row:not(:last-child)::after {
            left: 1rem;
            right: 1rem;
        }
    }

    @media (max-width: 480px) {
        .ui-modal-title {
            font-size: 1rem;
        }

        .ui-modal-subtitle {
            font-size: 0.75rem;
        }

        .ui-payment-indicator {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
    }

    /* Payment To Section Styles */
    .ui-recipients-count-badge {
        background: #e0f2fe;
        color: #0277bd;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        min-width: 24px;
        text-align: center;
        margin-left: auto;
    }

    .ui-recipients-container {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding: 1rem;
    }

    .ui-recipient-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1rem;
        transition: all 0.2s ease;
        position: relative;
    }

    .ui-recipient-card:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .ui-recipient-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .ui-recipient-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .ui-recipient-category-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .ui-recipient-category-badge.vendor {
        background: #fef3c7;
        color: #92400e;
    }

    .ui-recipient-category-badge.supplier {
        background: #ddd6fe;
        color: #6b21a8;
    }

    .ui-recipient-category-badge.labour {
        background: #d1fae5;
        color: #065f46;
    }

    .ui-recipient-category-badge.contractor {
        background: #fde2e8;
        color: #be185d;
    }

    .ui-recipient-category-badge.employee {
        background: #dbeafe;
        color: #1e40af;
    }

    .ui-recipient-name {
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        font-size: 0.875rem;
    }

    .ui-recipient-amount {
        font-weight: 700;
        color: #059669;
        font-size: 0.875rem;
    }

    .ui-recipient-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #e2e8f0;
    }

    .ui-recipient-detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .ui-recipient-detail-label {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .ui-recipient-detail-value {
        font-size: 0.8rem;
        color: #334155;
        font-weight: 500;
    }

    /* Split Payment within Recipient Card Styles */
    .ui-recipient-split-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #475569;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .ui-recipient-split-toggle:hover {
        background: #e2e8f0;
        border-color: #cbd5e1;
        color: #334155;
    }

    .ui-recipient-split-toggle.expanded {
        background: #dbeafe;
        border-color: #93c5fd;
        color: #1e40af;
    }

    .ui-recipient-split-toggle i {
        transition: transform 0.2s ease;
    }

    .ui-recipient-split-toggle.expanded i {
        transform: rotate(180deg);
    }

    .ui-recipient-split-content {
        margin-top: 0.75rem;
        padding: 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        display: none;
    }

    .ui-recipient-split-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 1rem;
        color: #64748b;
        font-size: 0.8rem;
    }

    .ui-recipient-split-loading .ui-mini-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid #f1f5f9;
        border-top: 2px solid #3b82f6;
        border-radius: 50%;
        animation: ui-spin 0.8s linear infinite;
    }

    .ui-split-items-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .ui-split-item-mini {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 0.75rem;
    }

    .ui-split-item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .ui-split-item-number {
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
    }

    .ui-split-item-amount {
        font-size: 0.8rem;
        font-weight: 700;
        color: #059669;
    }

    .ui-split-item-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.375rem;
    }

    .ui-split-item-detail {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .ui-split-item-label {
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .ui-split-item-value {
        font-size: 0.75rem;
        color: #334155;
        font-weight: 500;
    }

    .ui-split-proof-mini {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid #f1f5f9;
    }

    .ui-split-proof-link-mini {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        color: #3b82f6;
        text-decoration: none;
        font-size: 0.7rem;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    .ui-split-proof-link-mini:hover {
        color: #2563eb;
        text-decoration: underline;
    }

    .ui-split-empty-mini {
        text-align: center;
        padding: 1rem;
        color: #64748b;
        font-size: 0.8rem;
    }

    .ui-split-error-mini {
        text-align: center;
        padding: 1rem;
        color: #dc2626;
        font-size: 0.8rem;
    }

    .ui-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        text-align: center;
    }

    .ui-empty-icon {
        font-size: 2.5rem;
        color: #cbd5e1;
        margin-bottom: 0.75rem;
    }

    .ui-empty-text {
        color: #64748b;
        margin: 0;
        font-size: 0.875rem;
        font-weight: 500;
    }

    /* Split Payment Section Styles */
    .ui-split-toggle-btn {
        background: none;
        border: none;
        color: #64748b;
        padding: 0.25rem;
        border-radius: 4px;
        transition: all 0.2s ease;
        cursor: pointer;
        margin-left: auto;
    }

    .ui-split-toggle-btn:hover {
        background: #f1f5f9;
        color: #334155;
    }

    .ui-split-toggle-btn.expanded {
        transform: rotate(180deg);
    }

    .ui-split-content {
        padding: 1rem;
        background: #f8fafc;
        border-radius: 8px;
        margin-top: 0.75rem;
    }

    .ui-split-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        text-align: center;
    }

    .ui-split-container {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .ui-split-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
        transition: all 0.2s ease;
    }

    .ui-split-item:hover {
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .ui-split-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .ui-split-number {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
    }

    .ui-split-amount {
        font-weight: 700;
        color: #059669;
        font-size: 0.875rem;
    }

    .ui-split-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.5rem;
    }

    .ui-split-detail {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .ui-split-label {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .ui-split-value {
        font-size: 0.8rem;
        color: #334155;
        font-weight: 500;
    }

    .ui-split-proof {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
    }

    .ui-split-proof-link {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        color: #3b82f6;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    .ui-split-proof-link:hover {
        color: #2563eb;
        text-decoration: underline;
    }

    .ui-split-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        text-align: center;
        color: #64748b;
        font-size: 0.875rem;
    }

    .ui-split-empty i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: #cbd5e1;
    }
</style>