<!-- View Payment Entry Modal -->
<div class="modal fade" id="viewPaymentEntryModal" tabindex="-1" aria-labelledby="viewPaymentEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content pmt-modal-shell">
            <div class="modal-header pmt-header-zone">
                <h5 class="modal-title pmt-title-text" id="viewPaymentEntryModalLabel">
                    <i class="fas fa-money-check-alt pmt-title-icon"></i>Payment Transaction Details
                </h5>
                <button type="button" class="btn-close pmt-close-btn" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body pmt-body-container">
                <!-- Loading State -->
                <div id="paymentEntryDetailsLoader" class="pmt-loading-state">
                    <div class="pmt-spinner">
                        <div class="pmt-spinner-ring"></div>
                        <div class="pmt-spinner-ring"></div>
                        <div class="pmt-spinner-ring"></div>
                    </div>
                    <p class="pmt-loading-text">Fetching payment details...</p>
                </div>

                <!-- Error State -->
                <div id="paymentEntryDetailsError" class="pmt-error-banner" style="display: none;">
                    <div class="pmt-error-content">
                        <i class="fas fa-exclamation-triangle pmt-error-icon"></i>
                        <span id="paymentEntryErrorMessage" class="pmt-error-message">Failed to load payment entry details</span>
                    </div>
                </div>

                <!-- Content -->
                <div id="paymentEntryDetailsContent" class="pmt-main-content" style="display: none;">
                    <!-- Payment Overview Section -->
                    <div class="pmt-overview-panel">
                        <div class="pmt-panel-header">
                            <h6 class="pmt-panel-title">
                                <i class="fas fa-chart-line pmt-panel-icon"></i>
                                Transaction Overview
                            </h6>
                        </div>
                        <div class="pmt-info-matrix">
                            <!-- Primary Info Row -->
                            <div class="pmt-info-row">
                                <div class="pmt-info-cell">
                                    <div class="pmt-field-tag">Project</div>
                                    <div class="pmt-field-data">
                                        <div id="viewProjectTitle" class="pmt-project-name">-</div>
                                        <small class="pmt-project-meta" id="viewProjectType">-</small>
                                    </div>
                                </div>
                                <div class="pmt-info-cell">
                                    <div class="pmt-field-tag">Payment Date</div>
                                    <div class="pmt-field-data" id="viewPaymentDate">-</div>
                                </div>
                            </div>
                            
                            <!-- Amount Showcase -->
                            <div class="pmt-amount-showcase">
                                <div class="pmt-amount-header">Total Amount</div>
                                <div class="pmt-amount-value" id="viewPaymentAmount">₹0</div>
                                <div class="pmt-proof-indicator" id="paymentProofClip" onclick="showPaymentProof()" style="display: none;">
                                    <i class="fas fa-file-image pmt-proof-icon"></i>
                                    <span class="pmt-proof-label">View Payment Proof</span>
                                </div>
                            </div>
                            
                            <!-- Secondary Info Row -->
                            <div class="pmt-info-row">
                                <div class="pmt-info-cell">
                                    <div class="pmt-field-tag">Payment Via</div>
                                    <div class="pmt-field-data" id="viewPaymentVia">-</div>
                                </div>
                                <div class="pmt-info-cell">
                                    <div class="pmt-field-tag">Payment Mode</div>
                                    <div class="pmt-field-data" id="viewPaymentMode">-</div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Recipients Section -->
                    <div class="pmt-recipients-panel" id="recipientsSection">
                        <div class="pmt-panel-header">
                            <h6 class="pmt-panel-title">
                                <i class="fas fa-users pmt-panel-icon"></i>
                                Payment Recipients
                            </h6>
                            <span class="pmt-count-indicator" id="recipientCount">0</span>
                        </div>
                        <div class="pmt-table-wrapper">
                            <table class="pmt-data-table pmt-recipients-table">
                                <thead>
                                    <tr>
                                        <th class="pmt-table-header">Recipient Details</th>
                                    </tr>
                                </thead>
                                <tbody id="recipientsList" class="pmt-table-body">
                                    <!-- Recipients will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Documents Section -->
                    <div class="pmt-documents-panel" id="documentsSection" style="display: none;">
                        <div class="pmt-panel-header">
                            <h6 class="pmt-panel-title">
                                <i class="fas fa-folder-open pmt-panel-icon"></i>
                                Supporting Documents
                            </h6>
                            <span class="pmt-count-indicator" id="documentsCount">0</span>
                        </div>
                        <div class="pmt-table-wrapper">
                            <table class="pmt-data-table pmt-documents-table">
                                <thead>
                                    <tr>
                                        <th class="pmt-table-header">Document Name</th>
                                        <th class="pmt-table-header">Type</th>
                                        <th class="pmt-table-header">Size</th>
                                        <th class="pmt-table-header">Upload Date</th>
                                        <th class="pmt-table-header pmt-center-text">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="documentsList" class="pmt-table-body">
                                    <!-- Documents will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Audit Trail Section -->
                    <div class="pmt-audit-panel">
                        <div class="pmt-panel-header">
                            <h6 class="pmt-panel-title">
                                <i class="fas fa-history pmt-panel-icon"></i>
                                Audit Trail
                            </h6>
                        </div>
                        <div class="pmt-audit-grid">
                            <div class="pmt-audit-row">
                                <div class="pmt-audit-cell">
                                    <span class="pmt-audit-label">Created By</span>
                                    <span class="pmt-audit-value" id="viewCreatedBy">-</span>
                                </div>
                                <div class="pmt-audit-cell">
                                    <span class="pmt-audit-label">Updated By</span>
                                    <span class="pmt-audit-value" id="viewUpdatedBy">-</span>
                                </div>
                            </div>
                            <div class="pmt-audit-row">
                                <div class="pmt-audit-cell">
                                    <span class="pmt-audit-label">Created At</span>
                                    <span class="pmt-audit-value" id="viewCreatedAt">-</span>
                                </div>
                                <div class="pmt-audit-cell">
                                    <span class="pmt-audit-label">Updated At</span>
                                    <span class="pmt-audit-value" id="viewUpdatedAt">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer pmt-footer-zone">
                <button type="button" class="btn btn-primary pmt-action-btn pmt-edit-btn" id="editPaymentEntryFromView">
                    <i class="fas fa-edit pmt-btn-icon"></i>Edit Payment
                </button>
                <button type="button" class="btn btn-secondary pmt-action-btn pmt-close-btn-alt" data-bs-dismiss="modal">
                    <i class="fas fa-times pmt-btn-icon"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="pmt-image-viewer">
    <span class="pmt-viewer-close" onclick="closeImagePreview()">
        <i class="fas fa-times"></i>
    </span>
    <div class="pmt-viewer-content">
        <img id="imagePreviewImg" class="pmt-viewer-image" src="" alt="">
    </div>
    <div id="imagePreviewTitle" class="pmt-viewer-caption"></div>
</div>

<script>
// Helper function to safely set text content
function safeSetText(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value || '-';
    }
}

function safeSetHTML(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = value || '-';
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function to get appropriate file icon based on file type
function getFileIcon(fileType) {
    const type = fileType.toLowerCase();
    if (type.includes('pdf')) return 'fa-file-pdf';
    if (type.includes('word') || type.includes('doc')) return 'fa-file-word';
    if (type.includes('excel') || type.includes('sheet')) return 'fa-file-excel';
    if (type.includes('powerpoint') || type.includes('presentation')) return 'fa-file-powerpoint';
    if (type.includes('text') || type.includes('txt')) return 'fa-file-alt';
    if (type.includes('zip') || type.includes('rar') || type.includes('archive')) return 'fa-file-archive';
    if (type.includes('video')) return 'fa-file-video';
    if (type.includes('audio')) return 'fa-file-audio';
    return 'fa-file';
}

// Helper function for file icon (alternative name used in some files)
function getFileIconClass(fileType) {
    return getFileIcon(fileType);
}

// Function to show payment proof
function showPaymentProof() {
    const proofClip = document.getElementById('paymentProofClip');
    const proofPath = proofClip.dataset.proofPath;
    const proofName = proofClip.dataset.proofName || 'Payment Proof';
    
    if (proofPath) {
        const fileExtension = proofPath.split('.').pop().toLowerCase();
        
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
            // Show image in preview modal
            openImagePreview(proofPath, proofName);
        } else if (fileExtension === 'pdf') {
            // Open PDF in new tab
            window.open(proofPath, '_blank');
        } else {
            // Download other file types
            downloadDocument(proofPath, proofName);
        }
    }
}

// Function to open image preview
function openImagePreview(imagePath, fileName) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('imagePreviewImg');
    const title = document.getElementById('imagePreviewTitle');
    
    if (modal && img && title) {
        img.src = imagePath;
        img.alt = fileName;
        title.textContent = fileName;
        modal.style.display = 'block';
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
}

// Function to close image preview
function closeImagePreview() {
    const modal = document.getElementById('imagePreviewModal');
    if (modal) {
        modal.style.display = 'none';
        
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }
}

// Function to download document
function downloadDocument(filePath, fileName) {
    const link = document.createElement('a');
    link.href = filePath;
    link.download = fileName;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Close image preview when clicking outside the image
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('imagePreviewModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeImagePreview();
            }
        });
    }
    
    // Close image preview with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImagePreview();
        }
    });
});
</script>

<style>
/* Modern Payment Modal Design - Part 1: Core Structure */
.pmt-modal-shell {
    border: none;
    border-radius: 16px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
    background: #ffffff;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.pmt-header-zone {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.pmt-title-text {
    font-weight: 700;
    font-size: 1.25rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pmt-title-icon {
    font-size: 1.4rem;
    opacity: 0.9;
}

.pmt-close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.pmt-close-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

.pmt-body-container {
    background: #f8fafc;
    flex: 1;
    overflow-y: auto;
    max-height: calc(90vh - 160px);
}

/* Loading States */
.pmt-loading-state {
    text-align: center;
    padding: 4rem 2rem;
}

.pmt-spinner {
    position: relative;
    width: 60px;
    height: 60px;
    margin: 0 auto 1.5rem;
}

.pmt-spinner-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border: 3px solid transparent;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    animation: pmt-spin 1.2s linear infinite;
}

@keyframes pmt-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.pmt-loading-text {
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
    margin: 0;
}

/* Error States */
.pmt-error-banner {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 1px solid #f87171;
    border-radius: 12px;
    margin: 1.5rem;
    padding: 1.25rem;
}

.pmt-error-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pmt-error-icon {
    color: #dc2626;
    font-size: 1.25rem;
}

.pmt-error-message {
    color: #7f1d1d;
    font-weight: 500;
    font-size: 0.9rem;
}

/* Ensure proper scrolling for modal content */
.pmt-main-content {
    padding: 0;
    min-height: 0;
}

/* Loading States */
.pmt-loading-state {
    text-align: center;
    padding: 4rem 2rem;
}

.pmt-spinner {
    position: relative;
    width: 60px;
    height: 60px;
    margin: 0 auto 1.5rem;
}

.pmt-spinner-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border: 3px solid transparent;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    animation: pmt-spin 1.2s linear infinite;
}

@keyframes pmt-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.pmt-loading-text {
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
    margin: 0;
}

/* Error States */
.pmt-error-banner {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 1px solid #f87171;
    border-radius: 12px;
    margin: 1.5rem;
    padding: 1.25rem;
}

.pmt-error-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pmt-error-icon {
    color: #dc2626;
    font-size: 1.25rem;
}

.pmt-error-message {
    color: #7f1d1d;
    font-weight: 500;
    font-size: 0.9rem;
}

/* Main Content */
.pmt-main-content {
    padding: 0;
}

/* Bootstrap Modal Class Overrides for Custom Styling */
.modal-content.pmt-modal-shell {
    border: none;
    border-radius: 16px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
    background: #ffffff;
    overflow: hidden;
}

.modal-header.pmt-header-zone {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem 2rem;
    border-bottom: none;
}

.modal-title.pmt-title-text {
    font-weight: 700;
    font-size: 1.25rem;
    margin: 0;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.btn-close.pmt-close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    opacity: 1;
    filter: none;
    box-shadow: none;
}

.btn-close.pmt-close-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
    opacity: 1;
}

.modal-body.pmt-body-container {
    background: #f8fafc;
    padding: 0;
}

.modal-footer.pmt-footer-zone {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 1px solid #dee2e6;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Button styling overrides */
.btn.pmt-action-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn.pmt-action-btn.pmt-edit-btn {
    background: linear-gradient(135deg, #4299e1, #3182ce);
    color: white;
    box-shadow: 0 3px 10px rgba(66, 153, 225, 0.3);
}

.btn.pmt-action-btn.pmt-edit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(66, 153, 225, 0.4);
    background: linear-gradient(135deg, #3182ce, #2c5282);
}

.btn.pmt-action-btn.pmt-close-btn-alt {
    background: linear-gradient(135deg, #a0aec0, #718096);
    color: white;
    box-shadow: 0 3px 10px rgba(160, 174, 192, 0.3);
}

.btn.pmt-action-btn.pmt-close-btn-alt:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(160, 174, 192, 0.4);
    background: linear-gradient(135deg, #718096, #4a5568);
}

/* Panel Structure */
.pmt-overview-panel,
.pmt-recipients-panel,
.pmt-documents-panel,
.pmt-audit-panel {
    background: white;
    margin-bottom: 2px;
    border-left: 4px solid transparent;
    position: relative;
    overflow: hidden;
}

.pmt-overview-panel {
    border-left-color: #667eea;
}

.pmt-recipients-panel {
    border-left-color: #f093fb;
}

.pmt-documents-panel {
    border-left-color: #20c997;
}

.pmt-audit-panel {
    border-left-color: #fd7e14;
}

.pmt-panel-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.25rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #dee2e6;
}

.pmt-panel-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pmt-panel-icon {
    background: linear-gradient(135deg, #4299e1, #3182ce);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
}

.pmt-count-indicator {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    min-width: 40px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

/* Info Matrix for Overview */
.pmt-info-matrix {
    padding: 2rem;
}

.pmt-info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    margin-bottom: 2rem;
}

.pmt-info-row:last-child {
    margin-bottom: 0;
}

.pmt-info-cell {
    text-align: center;
}

.pmt-field-tag {
    font-size: 0.75rem;
    font-weight: 700;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 0.75rem;
}

.pmt-field-data {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    line-height: 1.4;
}

.pmt-project-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: #4299e1;
    margin-bottom: 0.5rem;
}

.pmt-project-meta {
    color: #a0aec0;
    font-size: 0.85rem;
}

/* Amount Showcase */
.pmt-amount-showcase {
    text-align: center;
    margin: 2.5rem 0;
    padding: 2rem;
    background: linear-gradient(135deg, #edf2f7 0%, #e2e8f0 100%);
    border-radius: 16px;
    border: 2px solid #cbd5e0;
    position: relative;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.pmt-amount-header {
    font-size: 0.8rem;
    font-weight: 700;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 0.75rem;
}

.pmt-amount-value {
    font-size: 2rem;
    font-weight: 800;
    color: #1a202c;
    font-family: 'Segoe UI', system-ui, sans-serif;
    margin-bottom: 1rem;
    letter-spacing: 0.5px;
}

.pmt-proof-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1.5rem;
    background: linear-gradient(135deg, #4299e1, #3182ce);
    color: white;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
    border: none;
}

.pmt-proof-indicator:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(66, 153, 225, 0.4);
    background: linear-gradient(135deg, #3182ce, #2c5282);
}

.pmt-proof-icon {
    font-size: 1.1rem;
    animation: pmt-float 2s ease-in-out infinite;
}

@keyframes pmt-float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-3px); }
}

.pmt-proof-label {
    font-size: 0.85rem;
}

/* Table Styling & Component Styles */
.pmt-table-wrapper {
    max-height: none;
    overflow-y: visible;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin: 0 1.5rem 1.5rem;
}

.pmt-data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
    margin: 0;
}

.pmt-table-header {
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    color: #4a5568;
    font-weight: 700;
    font-size: 0.8rem;
    padding: 1.25rem 1.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #cbd5e0;
    position: sticky;
    top: 0;
    z-index: 10;
}

.pmt-table-body tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f1f5f9;
}

.pmt-table-body tr:hover {
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.pmt-table-body td {
    padding: 1.5rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

.pmt-center-text {
    text-align: center;
}

/* Tags & Badges */
.pmt-category-tag,
.badge-category {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #805ad5, #6b46c1);
    color: white;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 6px rgba(128, 90, 213, 0.3);
    margin-right: 0.5rem;
}

.pmt-type-tag,
.badge-type {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #ec4899, #db2777);
    color: white;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 6px rgba(236, 72, 153, 0.3);
}

/* Split Proof Links */
.pmt-split-proof,
.split-proof-link {
    color: #4299e1;
    text-decoration: underline;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.pmt-split-proof:hover,
.split-proof-link:hover {
    color: #3182ce;
    transform: translateY(-1px);
    text-shadow: 0 1px 3px rgba(66, 153, 225, 0.3);
}

/* Audit Grid */
.pmt-audit-grid {
    padding: 2rem;
}

.pmt-audit-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    margin-bottom: 1.5rem;
}

.pmt-audit-cell {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pmt-audit-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pmt-audit-value {
    font-size: 0.9rem;
    font-weight: 500;
    color: #2d3748;
}





/* Image Viewer */
.pmt-image-viewer {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    backdrop-filter: blur(5px);
}

.pmt-viewer-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    z-index: 10001;
    background: rgba(0, 0, 0, 0.5);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.pmt-viewer-content {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 40px;
}

.pmt-viewer-image {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

.pmt-viewer-caption {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    color: white;
    background: rgba(0, 0, 0, 0.8);
    padding: 1rem 2rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .pmt-info-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .pmt-amount-value {
        font-size: 1.75rem;
    }
    
    .pmt-audit-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .pmt-footer-zone {
        flex-direction: column;
        gap: 0.75rem;
    }
}

@media (max-width: 576px) {
    .pmt-amount-value {
        font-size: 1.5rem;
    }
    
    .pmt-category-tag,
    .pmt-type-tag {
        padding: 0.375rem 0.75rem;
        font-size: 0.7rem;
        margin: 0.25rem 0.25rem 0.25rem 0;
    }
}

/* Professional Recipient Details Styling */
.pmt-recipient-details {
    padding: 1.5rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin: 0.5rem 0;
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
}

.pmt-recipient-details:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

/* 1. Vendor/Labour Name */
.pmt-recipient-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a202c;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.pmt-name-icon {
    background: linear-gradient(135deg, #4299e1, #3182ce);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

/* 2. Vendor/Labour Type */
.pmt-type-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.pmt-custom-tag {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    background: linear-gradient(135deg, #718096, #4a5568);
    color: white;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: capitalize;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 4px rgba(113, 128, 150, 0.2);
}

/* 3. Payment For */
.pmt-payment-purpose {
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.pmt-purpose-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 0.25rem;
}

.pmt-purpose-text {
    font-size: 0.9rem;
    color: #2d3748;
    font-weight: 500;
}

/* 4. Split Payments Section */
.pmt-splits-section {
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.pmt-splits-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    color: #ed8936;
    font-weight: 600;
    font-size: 0.85rem;
}

.pmt-splits-icon {
    font-size: 1rem;
    animation: pmt-bounce 2s ease-in-out infinite;
}

@keyframes pmt-bounce {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-2px); }
}

.pmt-splits-title {
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pmt-splits-list {
    display: grid;
    gap: 0.5rem;
}

.pmt-split-item {
    background: linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%);
    border: 1px solid #fdba74;
    border-radius: 8px;
    padding: 0.75rem;
    border-left: 4px solid #ea580c;
    transition: all 0.3s ease;
}

.pmt-split-item:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(234, 88, 12, 0.2);
}

.pmt-split-info {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 1rem;
    align-items: center;
    font-size: 0.85rem;
}

.pmt-split-mode {
    color: #9a3412;
    font-weight: 500;
}

.pmt-split-amount {
    font-weight: 700;
    color: #ea580c;
    font-size: 0.95rem;
}

.pmt-split-date {
    color: #7c2d12;
    font-size: 0.8rem;
}

/* 5. Total Amount */
.pmt-total-amount {
    margin-bottom: 0.75rem;
    padding: 1rem;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    border-left: 4px solid #10b981;
    text-align: center;
}

.pmt-amount-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #047857;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 0.5rem;
}

.pmt-amount-value {
    font-size: 1.4rem;
    font-weight: 800;
    color: #065f46;
    font-family: 'Segoe UI', system-ui, sans-serif;
    letter-spacing: 0.5px;
}

/* 6. Date and Time */
.pmt-timestamp {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 8px;
    border: 1px solid #cbd5e0;
    margin-bottom: 1rem;
}

.pmt-time-icon {
    color: #4299e1;
    font-size: 1rem;
}

.pmt-payment-mode {
    font-size: 0.85rem;
    font-weight: 600;
    color: #2d3748;
    background: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    border: 1px solid #e2e8f0;
}

.pmt-date-time {
    font-size: 0.8rem;
    color: #64748b;
    font-style: italic;
    margin-left: auto;
}

/* Documents Section */
.pmt-documents-section {
    margin-top: 1rem;
}

.pmt-documents-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    color: #0891b2;
    font-weight: 600;
    font-size: 0.9rem;
}

.pmt-docs-icon {
    font-size: 1.1rem;
    animation: pmt-pulse 2s ease-in-out infinite;
}

@keyframes pmt-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.pmt-docs-title {
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pmt-documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.pmt-document-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}

.pmt-document-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    border-color: #0891b2;
}

.pmt-doc-preview {
    height: 120px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.pmt-doc-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.pmt-doc-image:hover {
    transform: scale(1.05);
}

.pmt-doc-icon-container {
    text-align: center;
}

.pmt-doc-icon {
    font-size: 2.5rem;
    color: #0891b2;
    margin-bottom: 0.5rem;
}

.pmt-file-ext {
    background: #0891b2;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.pmt-doc-info {
    padding: 1rem;
}

.pmt-doc-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pmt-doc-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 0.75rem;
    color: #64748b;
}

.pmt-download-btn {
    width: 100%;
    padding: 0.5rem;
    background: linear-gradient(135deg, #0891b2, #0e7490);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.8rem;
    font-weight: 600;
}

.pmt-download-btn:hover {
    background: linear-gradient(135deg, #0e7490, #155e75);
    transform: translateY(-1px);
}

/* Mobile Responsive for Recipient Details */
@media (max-width: 768px) {
    .pmt-recipient-details {
        padding: 1rem;
    }
    
    .pmt-split-info {
        grid-template-columns: 1fr;
        gap: 0.5rem;
        text-align: left;
    }
    
    .pmt-timestamp {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .pmt-date-time {
        margin-left: 0;
    }
    
    .pmt-documents-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .pmt-recipient-name {
        font-size: 1rem;
    }
    
    .pmt-amount-value {
        font-size: 1.2rem;
    }
    
    .pmt-type-tags {
        gap: 0.25rem;
    }
}
</style>;




</style>