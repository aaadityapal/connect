<!-- View Payment Entry Modal -->
<div class="modal fade" id="viewPaymentEntryModal" tabindex="-1" aria-labelledby="viewPaymentEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 py-4">
                <h4 class="modal-title fw-light text-dark" id="viewPaymentEntryModalLabel">
                    Payment Entry Details
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-0">
                <!-- Loading State -->
                <div id="paymentEntryDetailsLoader" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted fs-6">Loading payment details...</p>
                </div>

                <!-- Error State -->
                <div id="paymentEntryDetailsError" class="alert alert-danger border-0 rounded-3" style="display: none;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-3 fs-5"></i>
                        <span id="paymentEntryErrorMessage">Failed to load payment entry details</span>
                    </div>
                </div>

                <!-- Content -->
                <div id="paymentEntryDetailsContent" style="display: none;">
                    <!-- Payment Entry Summary -->
                    <div class="payment-summary-section mb-4">
                        <div class="section-header mb-3">
                            <h6 class="section-title">Payment Overview</h6>
                        </div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label">Payment ID</div>
                                <div class="summary-value" id="viewPaymentId">-</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Project</div>
                                <div class="summary-value" id="viewProjectTitle">-</div>
                                <div class="summary-subtitle" id="viewProjectType">-</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Total Amount</div>
                                <div class="summary-value amount-highlight" id="viewPaymentAmount">-</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Payment Date</div>
                                <div class="summary-value" id="viewPaymentDate">-</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Payment Mode</div>
                                <div class="summary-value" id="viewPaymentMode">-</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Payment Via</div>
                                <div class="summary-value" id="viewPaymentVia">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Recipients Section -->
                    <div class="recipients-section mb-4">
                        <div class="section-header mb-3">
                            <h6 class="section-title">Recipients</h6>
                            <span class="count-badge" id="recipientCount">0</span>
                        </div>
                        <div class="recipients-container">
                            <div id="recipientsList">
                                <!-- Recipients will be populated here -->
                            </div>
                        </div>
                    </div>

                    <!-- Summary Statistics -->
                    <div class="stats-section mb-4">
                        <div class="section-header mb-3">
                            <h6 class="section-title">Quick Stats</h6>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number" id="summaryRecipients">0</div>
                                    <div class="stat-label">Recipients</div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-divide"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number" id="summarySplits">0</div>
                                    <div class="stat-label">Splits</div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number" id="summaryDocuments">0</div>
                                    <div class="stat-label">Documents</div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-rupee-sign"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number" id="summaryAmount">₹0</div>
                                    <div class="stat-label">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Section -->
                    <div class="documents-section mb-4" id="documentsSection" style="display: none;">
                        <div class="section-header mb-3">
                            <h6 class="section-title">Documents</h6>
                            <span class="count-badge" id="documentsCount">0</span>
                        </div>
                        <div class="documents-grid" id="documentsList">
                            <!-- Documents will be populated here -->
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="system-info-section">
                        <div class="section-header mb-3">
                            <h6 class="section-title">Audit Trail</h6>
                        </div>
                        <div class="audit-grid">
                            <div class="audit-item">
                                <div class="audit-label">Created By</div>
                                <div class="audit-value" id="viewCreatedBy">-</div>
                            </div>
                            <div class="audit-item">
                                <div class="audit-label">Created At</div>
                                <div class="audit-value" id="viewCreatedAt">-</div>
                            </div>
                            <div class="audit-item">
                                <div class="audit-label">Updated By</div>
                                <div class="audit-value" id="viewUpdatedBy">-</div>
                            </div>
                            <div class="audit-item">
                                <div class="audit-label">Updated At</div>
                                <div class="audit-value" id="viewUpdatedAt">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3">
                <button type="button" class="btn btn-outline-primary rounded-pill px-4" id="editPaymentEntryFromView">
                    <i class="fas fa-edit me-2"></i>
                    Edit Payment
                </button>
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="image-preview-modal">
    <span class="image-preview-close" onclick="closeImagePreview()">&times;</span>
    <div class="image-preview-content">
        <img id="imagePreviewImg" class="image-preview-img" src="" alt="">
    </div>
    <div id="imagePreviewTitle" class="image-preview-title"></div>
</div>

<script>
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

// Function to open image preview
function openImagePreview(imagePath, fileName) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('imagePreviewImg');
    const title = document.getElementById('imagePreviewTitle');
    
    img.src = imagePath;
    img.alt = fileName;
    title.textContent = fileName;
    modal.style.display = 'block';
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

// Function to close image preview
function closeImagePreview() {
    const modal = document.getElementById('imagePreviewModal');
    modal.style.display = 'none';
    
    // Restore body scroll
    document.body.style.overflow = 'auto';
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
/* Professional Modal Styling */
.modal-content {
    border-radius: 16px !important;
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.modal-title {
    font-weight: 300;
    letter-spacing: 0.5px;
}

/* Section Headers */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 2px solid #f8f9fa;
}

.section-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.count-badge {
    background: #667eea;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Payment Summary Grid */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 10px;
}

.summary-item {
    background: #f8f9fc;
    border: 1px solid #e9ecf3;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.summary-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
    border-color: #667eea;
}

.summary-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.summary-label {
    font-size: 0.8rem;
    color: #8898aa;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.summary-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    line-height: 1.2;
}

.summary-subtitle {
    font-size: 0.85rem;
    color: #8898aa;
    margin-top: 4px;
}

.amount-highlight {
    font-size: 1.4rem !important;
    color: #27ae60 !important;
    font-weight: 700 !important;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
}

.stat-item {
    background: white;
    border: 1px solid #e9ecf3;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}

.stat-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    color: white;
    font-size: 1.1rem;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 0.8rem;
    color: #8898aa;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Recipients Container */
.recipients-container {
    background: #f8f9fc;
    border-radius: 12px;
    border: 1px solid #e9ecf3;
    overflow: hidden;
}

.recipient-item {
    background: white;
    border: none;
    border-radius: 0;
    margin: 0;
    border-bottom: 1px solid #f1f3f4;
    transition: all 0.3s ease;
}

.recipient-item:last-child {
    border-bottom: none;
}

.recipient-item:hover {
    background: #f8f9fc;
    transform: translateX(4px);
}

.recipient-header {
    background: none;
    border: none;
    padding: 24px;
}

.recipient-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
}

.badge-category {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    padding: 4px 10px;
    font-size: 0.75rem;
    border-radius: 6px;
}

.badge-type {
    background: #27ae60;
    border: none;
    padding: 4px 10px;
    font-size: 0.75rem;
    border-radius: 6px;
}

.amount-display {
    font-size: 1.3rem;
    font-weight: 700;
    color: #27ae60;
}

/* Split Items */
.split-item {
    background: #fff9e6;
    border: 1px solid #ffeaa0;
    border-radius: 8px;
    margin: 8px 0;
    padding: 16px;
    border-left: 4px solid #f39c12;
}

/* Document Items */
.document-item {
    background: #f0f8ff;
    border: 1px solid #b3d9ff;
    border-radius: 8px;
    margin: 8px 0;
    padding: 16px;
    border-left: 4px solid #3498db;
}

/* Enhanced Document Grid */
.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 12px;
}

.document-card {
    background: white;
    border: 2px solid #e9ecf3;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.document-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(52, 152, 219, 0.15);
    border-color: #3498db;
}

.document-preview-container {
    height: 120px;
    background: linear-gradient(135deg, #f8f9fc 0%, #e9ecf3 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.document-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.document-image:hover {
    transform: scale(1.05);
}

.document-icon-container {
    text-align: center;
    padding: 20px;
}

.document-icon-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    background: #f8f9fc;
}

.file-extension {
    background: #3498db;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    margin-top: 8px;
    display: inline-block;
    text-transform: uppercase;
}

.document-info {
    padding: 12px;
    position: relative;
}

.document-name {
    font-weight: 600;
    font-size: 0.85rem;
    color: #2c3e50;
    margin-bottom: 6px;
    line-height: 1.2;
    max-height: 2.4em;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.document-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-bottom: 8px;
}

.file-size {
    font-size: 0.75rem;
    color: #8898aa;
    font-weight: 500;
}

.upload-date {
    font-size: 0.7rem;
    color: #95a5a6;
}

.download-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    padding: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.3s ease;
}

.document-card:hover .download-btn {
    opacity: 1;
}

/* Image Preview Modal */
.image-preview-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    animation: fadeIn 0.3s ease;
}

.image-preview-content {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 20px;
}

.image-preview-img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.image-preview-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s ease;
}

.image-preview-close:hover {
    color: #ccc;
}

.image-preview-title {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    color: white;
    background: rgba(0, 0, 0, 0.7);
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.9rem;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.document-preview {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e9ecf3;
}

/* Audit Grid */
.audit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    background: #f8f9fc;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e9ecf3;
}

.audit-item {
    text-align: center;
}

.audit-label {
    font-size: 0.8rem;
    color: #8898aa;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.audit-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2c3e50;
}

/* Responsive Design */
@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .audit-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-dialog {
        margin: 10px;
    }
    
    .summary-item {
        padding: 16px;
    }
    
    .stat-item {
        padding: 16px;
    }
    
    .documents-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 12px;
    }
    
    .document-preview-container {
        height: 100px;
    }
    
    .document-info {
        padding: 10px;
    }
    
    .document-name {
        font-size: 0.8rem;
    }
    
    .image-preview-close {
        font-size: 30px;
        top: 10px;
        right: 15px;
    }
}

/* Loading and Error States */
.spinner-border {
    border-width: 3px;
}

.alert {
    border: none;
    padding: 20px;
}

/* Button Styling */
.btn {
    font-weight: 500;
    letter-spacing: 0.3px;
    transition: all 0.3s ease;
}

.btn-outline-primary {
    border-color: #667eea;
    color: #667eea;
}

.btn-outline-primary:hover {
    background: #667eea;
    border-color: #667eea;
    transform: translateY(-1px);
}

.btn-light {
    border: 1px solid #e9ecf3;
}

.btn-light:hover {
    background: #f8f9fc;
    transform: translateY(-1px);
}
</style>