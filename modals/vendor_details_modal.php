<?php
/**
 * Vendor Details Modal
 * Displays comprehensive vendor information in a modal dialog
 * Triggered when user clicks the "view" icon on a vendor row
 */
?>

<div id="vendorDetailsModal" class="vendor-details-modal">
    <div class="vendor-details-modal-overlay"></div>
    <div class="vendor-details-modal-content">
        <!-- Modal Header -->
        <div class="vendor-details-modal-header">
            <h2>Vendor Details</h2>
            <button class="vendor-details-close-btn" onclick="closeVendorDetailsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body with Loading State -->
        <div class="vendor-details-modal-body">
            <div id="vendorDetailsContainer">
                <div class="vendor-details-loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading vendor details...</p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="vendor-details-modal-footer">
            <button class="vendor-details-btn vendor-details-btn-secondary" onclick="closeVendorDetailsModal()">
                <i class="fas fa-times"></i> Close
            </button>
            <button class="vendor-details-btn vendor-details-btn-primary" onclick="editVendorFromModal()">
                <i class="fas fa-edit"></i> Edit
            </button>
        </div>
    </div>
</div>

<!-- Full Screen Image Viewer -->
<div id="imageFullscreenViewer" class="image-fullscreen-viewer">
    <button class="image-fullscreen-close" onclick="closeImageFullscreen()">
        <i class="fas fa-times"></i>
    </button>
    <div class="image-fullscreen-content">
        <img id="fullscreenImage" class="image-fullscreen-img" src="" alt="Full Screen Image">
        <div class="image-fullscreen-controls">
            <button class="image-fullscreen-btn" onclick="downloadFullscreenImage()">
                <i class="fas fa-download"></i> Download
            </button>
            <div class="image-fullscreen-info">
                Click anywhere or press ESC to close
            </div>
        </div>
    </div>
</div>

<style>
    /* Vendor Details Modal Styles */
    .vendor-details-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
    }

    .vendor-details-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .vendor-details-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        cursor: pointer;
    }

    .vendor-details-modal-content {
        position: relative;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        max-width: 800px;
        width: 90%;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .vendor-details-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px;
        border-bottom: 1px solid #e2e8f0;
    }

    .vendor-details-modal-header h2 {
        margin: 0;
        font-size: 1.5em;
        color: #2a4365;
        font-weight: 600;
    }

    .vendor-details-close-btn {
        background: none;
        border: none;
        font-size: 1.5em;
        color: #a0aec0;
        cursor: pointer;
        transition: color 0.2s ease;
        padding: 5px;
    }

    .vendor-details-close-btn:hover {
        color: #2a4365;
    }

    .vendor-details-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 25px;
    }

    .vendor-details-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 20px 25px;
        border-top: 1px solid #e2e8f0;
    }

    /* Loading State */
    .vendor-details-loading {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }

    .vendor-details-loading i {
        font-size: 2.5em;
        color: #2a4365;
        animation: spin 1s linear infinite;
        display: block;
        margin-bottom: 15px;
    }

    /* Details Sections */
    .vendor-details-section {
        margin-bottom: 20px;
    }

    .vendor-details-section-title {
        font-size: 1em;
        font-weight: 600;
        color: #2a4365;
        margin-bottom: 0;
        padding: 12px 15px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        background: #f7fafc;
        border-radius: 6px 6px 0 0;
        transition: all 0.2s ease;
    }

    .vendor-details-section-title:hover {
        background: #edf2f7;
    }

    .vendor-details-section-title i {
        font-size: 1.1em;
        margin-right: 10px;
        color: #2a4365;
        width: 20px;
        text-align: center;
    }

    .vendor-details-section-title-text {
        display: flex;
        align-items: center;
        flex: 1;
        gap: 10px;
    }

    .vendor-details-section-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        color: #718096;
        transition: transform 0.3s ease;
    }

    .vendor-details-section.collapsed .vendor-details-section-toggle {
        transform: rotate(-90deg);
    }

    .vendor-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        padding: 15px;
        background: white;
        border-bottom: 1px solid #e2e8f0;
        border-left: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        max-height: 1000px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .vendor-details-section.collapsed .vendor-details-grid {
        max-height: 0;
        padding: 0 15px;
        border: none;
    }

    .vendor-detail-item {
        display: flex;
        flex-direction: column;
    }

    .vendor-detail-label {
        font-size: 0.75em;
        color: #a0aec0;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 6px;
    }

    .vendor-detail-value {
        font-size: 0.95em;
        color: #2a4365;
        font-weight: 500;
        word-break: break-word;
        line-height: 1.4;
    }

    .vendor-detail-value.empty {
        color: #cbd5e0;
        font-style: italic;
        font-size: 0.9em;
    }

    /* QR Code Image Container */
    .vendor-qr-container {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .vendor-qr-image {
        max-width: 150px;
        max-height: 150px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        padding: 5px;
        background: white;
    }

    .vendor-qr-placeholder {
        width: 150px;
        height: 150px;
        border-radius: 6px;
        border: 2px dashed #cbd5e0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #a0aec0;
        font-size: 0.9em;
        text-align: center;
        padding: 10px;
    }

    /* Full Screen Image Viewer */
    .image-fullscreen-viewer {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    }

    .image-fullscreen-viewer.active {
        display: flex;
    }

    .image-fullscreen-content {
        position: relative;
        max-width: 90vw;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }

    .image-fullscreen-img {
        max-width: 90vw;
        max-height: 80vh;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
    }

    .image-fullscreen-controls {
        display: flex;
        gap: 15px;
        justify-content: center;
        align-items: center;
    }

    .image-fullscreen-close {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 1.5em;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .image-fullscreen-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }

    .image-fullscreen-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.4);
        color: white;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9em;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .image-fullscreen-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    .image-fullscreen-info {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9em;
        text-align: center;
    }

    /* Status Badge in Modal */
    .vendor-details-status {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 24px;
        font-size: 0.85em;
        font-weight: 600;
        text-transform: capitalize;
        width: fit-content;
    }

    .vendor-details-status.active {
        background-color: #c6f6d5;
        color: #22543d;
    }

    .vendor-details-status.inactive {
        background-color: #fed7d7;
        color: #742a2a;
    }

    .vendor-details-status.suspended {
        background-color: #feebc8;
        color: #7c2d12;
    }

    .vendor-details-status.archived {
        background-color: #cbd5e0;
        color: #2d3748;
    }

    /* Modal Buttons */
    .vendor-details-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 0.9em;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .vendor-details-btn-primary {
        background: #2a4365;
        color: white;
    }

    .vendor-details-btn-primary:hover {
        background: #1a365d;
        transform: translateY(-2px);
    }

    .vendor-details-btn-secondary {
        background: #e2e8f0;
        color: #2a4365;
    }

    .vendor-details-btn-secondary:hover {
        background: #cbd5e0;
    }

    /* Error State */
    .vendor-details-error {
        background-color: #fff5f5;
        border: 1px solid #fed7d7;
        color: #742a2a;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }

    .vendor-details-error i {
        font-size: 2em;
        display: block;
        margin-bottom: 10px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .vendor-details-modal-content {
            width: 95%;
            max-height: 90vh;
        }

        .vendor-details-grid {
            grid-template-columns: 1fr;
        }

        .vendor-details-modal-header h2 {
            font-size: 1.2em;
        }

        .vendor-details-modal-footer {
            flex-direction: column-reverse;
        }

        .vendor-details-btn {
            width: 100%;
            justify-content: center;
        }
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
</style>

<script>
    // Store current vendor ID
    let currentVendorId = null;

    /**
     * Open vendor details modal and fetch vendor data
     * @param {number} vendorId - The vendor ID to display
     */
    function openVendorDetailsModal(vendorId) {
        currentVendorId = vendorId;
        const modal = document.getElementById('vendorDetailsModal');
        const container = document.getElementById('vendorDetailsContainer');

        // Show modal with loading state
        modal.classList.add('active');
        container.innerHTML = `
            <div class="vendor-details-loading">
                <i class="fas fa-spinner"></i>
                <p>Loading vendor details...</p>
            </div>
        `;

        // Fetch vendor details from backend
        fetch(`fetch_vendor_details.php?vendor_id=${vendorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayVendorDetails(data.data);
                    
                    // After details are displayed, load payment records
                    setTimeout(() => {
                        const paymentContainer = document.querySelector('[data-payment-section-container]');
                        if (paymentContainer && typeof injectPaymentRecordsIntoModal === 'function') {
                            injectPaymentRecordsIntoModal(vendorId, paymentContainer);
                        }
                    }, 100);
                } else {
                    showVendorDetailsError(data.message || 'Failed to load vendor details');
                }
            })
            .catch(error => {
                console.error('Error fetching vendor details:', error);
                showVendorDetailsError('An error occurred while loading vendor details. Please try again.');
            });

        // Close modal when overlay is clicked
        document.querySelector('.vendor-details-modal-overlay').addEventListener('click', closeVendorDetailsModal);
    }

    /**
     * Display vendor details in the modal
     * @param {object} vendor - Vendor data object
     */
    function displayVendorDetails(vendor) {
        const container = document.getElementById('vendorDetailsContainer');
        const statusClass = vendor.vendor_status ? vendor.vendor_status.toLowerCase() : 'active';

        let html = `
            <!-- Header with Vendor Code and Status -->
            <div class="vendor-details-section">
                <div class="vendor-details-grid" style="grid-template-columns: 1fr 1fr; align-items: start; padding: 20px 15px;">
                    <div>
                        <div class="vendor-detail-item">
                            <div class="vendor-detail-label">Vendor Code</div>
                            <div class="vendor-detail-value" style="font-size: 1.1em; font-weight: 600;">${vendor.vendor_unique_code || 'N/A'}</div>
                        </div>
                    </div>
                    <div>
                        <div class="vendor-detail-item">
                            <div class="vendor-detail-label">Status</div>
                            <span class="vendor-details-status ${statusClass}">${vendor.vendor_status || 'Active'}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Basic Information Section (Always Open) -->
            <div class="vendor-details-section">
                <div class="vendor-details-section-title">
                    <div class="vendor-details-section-title-text">
                        <i class="fas fa-user-circle"></i>
                        <span>Basic Information</span>
                    </div>
                </div>
                <div class="vendor-details-grid">
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Full Name</div>
                        <div class="vendor-detail-value">${vendor.vendor_full_name || 'N/A'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Vendor Type</div>
                        <div class="vendor-detail-value">${vendor.vendor_type_category || 'N/A'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Category Type</div>
                        <div class="vendor-detail-value">${vendor.vendor_category_type || 'N/A'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Email</div>
                        <div class="vendor-detail-value">${vendor.vendor_email_address || 'N/A'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Primary Phone</div>
                        <div class="vendor-detail-value">${vendor.vendor_phone_primary || 'N/A'}</div>
                    </div>
                    ${vendor.vendor_phone_alternate ? `
                        <div class="vendor-detail-item">
                            <div class="vendor-detail-label">Alternate Phone</div>
                            <div class="vendor-detail-value">${vendor.vendor_phone_alternate}</div>
                        </div>
                    ` : ''}
                </div>
            </div>

            <!-- Banking Details Section (Collapsible, Closed by default) -->
            <div class="vendor-details-section collapsed">
                <div class="vendor-details-section-title" onclick="toggleSection(this)">
                    <div class="vendor-details-section-title-text">
                        <i class="fas fa-university"></i>
                        <span>Banking Details</span>
                    </div>
                    <span class="vendor-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="vendor-details-grid">
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Bank Name</div>
                        <div class="vendor-detail-value ${!vendor.bank_name ? 'empty' : ''}">${vendor.bank_name || 'Not provided'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Account Number</div>
                        <div class="vendor-detail-value ${!vendor.bank_account_number ? 'empty' : ''}">${vendor.bank_account_number || 'Not provided'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">IFSC Code</div>
                        <div class="vendor-detail-value ${!vendor.bank_ifsc_code ? 'empty' : ''}">${vendor.bank_ifsc_code || 'Not provided'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Account Type</div>
                        <div class="vendor-detail-value ${!vendor.bank_account_type ? 'empty' : ''}">${vendor.bank_account_type || 'Not provided'}</div>
                    </div>
                    ${vendor.bank_qr_code_path ? `
                        <div class="vendor-detail-item" style="grid-column: 1 / -1;">
                            <div class="vendor-detail-label">QR Code</div>
                            <div class="vendor-qr-container">
                                <img src="${vendor.bank_qr_code_path}" 
                                     alt="Bank QR Code" 
                                     class="vendor-qr-image"
                                     loading="lazy"
                                     style="cursor: pointer; transition: transform 0.2s ease;"
                                     onmouseover="this.style.transform='scale(1.05)'"
                                     onmouseout="this.style.transform='scale(1)'"
                                     onclick="openImageFullscreen('${vendor.bank_qr_code_path}')"
                                     onerror="handleQRImageError(this, '${vendor.bank_qr_code_path}')">
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>

            <!-- GST Details Section (Collapsible, Closed by default) -->
            <div class="vendor-details-section collapsed">
                <div class="vendor-details-section-title" onclick="toggleSection(this)">
                    <div class="vendor-details-section-title-text">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>GST Details</span>
                    </div>
                    <span class="vendor-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="vendor-details-grid">
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">GST Number</div>
                        <div class="vendor-detail-value ${!vendor.gst_number ? 'empty' : ''}">${vendor.gst_number || 'Not provided'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">GST State</div>
                        <div class="vendor-detail-value ${!vendor.gst_state ? 'empty' : ''}">${vendor.gst_state || 'Not provided'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">GST Type</div>
                        <div class="vendor-detail-value ${!vendor.gst_type_category ? 'empty' : ''}">${vendor.gst_type_category || 'Not provided'}</div>
                    </div>
                </div>
            </div>

            <!-- Address Details Section (Collapsible, Closed by default) -->
            <div class="vendor-details-section collapsed">
                <div class="vendor-details-section-title" onclick="toggleSection(this)">
                    <div class="vendor-details-section-title-text">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Address</span>
                    </div>
                    <span class="vendor-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="vendor-details-grid">
                    <div class="vendor-detail-item" style="grid-column: 1 / -1;">
                        <div class="vendor-detail-label">Street Address</div>
                        <div class="vendor-detail-value ${!vendor.address_street ? 'empty' : ''}">${vendor.address_street || 'Not provided'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">City</div>
                        <div class="vendor-detail-value ${!vendor.address_city ? 'empty' : ''}">${vendor.address_city || 'Not provided'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">State</div>
                        <div class="vendor-detail-value ${!vendor.address_state ? 'empty' : ''}">${vendor.address_state || 'Not provided'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Postal Code</div>
                        <div class="vendor-detail-value ${!vendor.address_postal_code ? 'empty' : ''}">${vendor.address_postal_code || 'Not provided'}</div>
                    </div>
                </div>
            </div>

            <!-- Payment Records Section (Collapsible, Closed by default) -->
            <div class="vendor-details-section collapsed">
                <div class="vendor-details-section-title" onclick="toggleSection(this)">
                    <div class="vendor-details-section-title-text">
                        <i class="fas fa-credit-card"></i>
                        <span>Payment Records</span>
                    </div>
                    <span class="vendor-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="vendor-details-grid" data-payment-section-container="true" style="padding: 0; border: none;">
                    <div style="grid-column: 1 / -1; width: 100%;">
                        <div class="empty-state" style="text-align: center; padding: 30px 20px; color: #a0aec0;">
                            <i class="fas fa-history" style="font-size: 2em; color: #cbd5e0; margin-bottom: 10px; display: block;"></i>
                            <p>Loading payment records...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metadata Section (Collapsible, Closed by default) -->
            <div class="vendor-details-section collapsed">
                <div class="vendor-details-section-title" onclick="toggleSection(this)">
                    <div class="vendor-details-section-title-text">
                        <i class="fas fa-info-circle"></i>
                        <span>Additional Information</span>
                    </div>
                    <span class="vendor-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="vendor-details-grid">
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Created Date</div>
                        <div class="vendor-detail-value">${vendor.created_date_time ? new Date(vendor.created_date_time).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</div>
                    </div>
                    <div class="vendor-detail-item">
                        <div class="vendor-detail-label">Last Updated</div>
                        <div class="vendor-detail-value">${vendor.updated_date_time ? new Date(vendor.updated_date_time).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    /**
     * Show error message in vendor details modal
     * @param {string} message - Error message to display
     */
    function showVendorDetailsError(message) {
        const container = document.getElementById('vendorDetailsContainer');
        container.innerHTML = `
            <div class="vendor-details-error">
                <i class="fas fa-exclamation-circle"></i>
                <p>${message}</p>
            </div>
        `;
    }

    /**
     * Close vendor details modal
     */
    function closeVendorDetailsModal() {
        const modal = document.getElementById('vendorDetailsModal');
        modal.classList.remove('active');
        currentVendorId = null;
    }

    /**
     * Edit vendor from modal - opens edit modal
     */
    function editVendorFromModal() {
        if (currentVendorId) {
            // Store vendor ID before closing
            const vendorIdToEdit = currentVendorId;
            // Close the details modal
            closeVendorDetailsModal();
            // Open the edit modal with stored vendor ID
            openVendorEditModal(vendorIdToEdit);
        } else {
            alert('Error: Vendor ID not found');
        }
    }

    /**
     * Handle QR code image loading errors
     */
    function handleQRImageError(imgElement, imagePath) {
        console.error('Failed to load QR code image from:', imagePath);
        imgElement.style.display = 'none';
        
        // Show placeholder instead
        const container = imgElement.parentElement;
        const placeholder = document.createElement('div');
        placeholder.className = 'vendor-qr-placeholder';
        placeholder.innerHTML = `
            <div>
                <i class="fas fa-exclamation-circle" style="font-size: 2em; color: #e53e3e; margin-bottom: 10px; display: block;"></i>
                <div style="font-size: 0.9em;">Image not available</div>
                <small style="color: #718096; margin-top: 5px; display: block; word-break: break-all;">Path: ${imagePath}</small>
            </div>
        `;
        container.appendChild(placeholder);
    }

    /**
     * Open image in full screen viewer
     * @param {string} imagePath - The path to the image
     */
    function openImageFullscreen(imagePath) {
        const viewer = document.getElementById('imageFullscreenViewer');
        const img = document.getElementById('fullscreenImage');
        
        img.src = imagePath;
        img.dataset.imagePath = imagePath;
        viewer.classList.add('active');
        
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close full screen image viewer
     */
    function closeImageFullscreen() {
        const viewer = document.getElementById('imageFullscreenViewer');
        viewer.classList.remove('active');
        
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }

    /**
     * Download the full screen image
     */
    function downloadFullscreenImage() {
        const img = document.getElementById('fullscreenImage');
        const imagePath = img.src;
        
        // Create a temporary link and trigger download
        const link = document.createElement('a');
        link.href = imagePath;
        link.download = imagePath.split('/').pop(); // Get filename from path
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Toggle collapsible section open/closed
     */
    function toggleSection(titleElement) {
        const section = titleElement.closest('.vendor-details-section');
        section.classList.toggle('collapsed');
    }

    /**
     * Close full screen viewer when clicking overlay
     */
    document.addEventListener('DOMContentLoaded', function() {
        const viewer = document.getElementById('imageFullscreenViewer');
        
        if (viewer) {
            // Close when clicking on the background
            viewer.addEventListener('click', function(e) {
                if (e.target === viewer) {
                    closeImageFullscreen();
                }
            });
        }
    });

    // Close modal when Escape key is pressed
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('vendorDetailsModal');
            if (modal && modal.classList.contains('active')) {
                closeVendorDetailsModal();
            }
            
            // Also close full screen image viewer
            const viewer = document.getElementById('imageFullscreenViewer');
            if (viewer && viewer.classList.contains('active')) {
                closeImageFullscreen();
            }
        }
    });

    // ==================== VENDOR PAYMENT RECORDS INTEGRATION ====================
    
    /**
     * Fetch vendor payment records from backend
     */
    async function fetchVendorPaymentRecords(vendorId) {
        try {
            console.log(`[Payment Records] Fetching for vendor ID: ${vendorId}`);
            const response = await fetch(`fetch_vendor_payment_records.php?vendor_id=${vendorId}`);
            
            if (!response.ok) {
                console.error(`[Payment Records] HTTP Error: ${response.status}`);
                return { success: false, data: [], message: 'Failed to fetch payment records' };
            }

            const data = await response.json();
            console.log(`[Payment Records] Response received:`, data);
            console.log(`[Payment Records] Records count: ${data.count}`);
            console.log(`[Payment Records] Debug info:`, data.debug);
            return data;
        } catch (error) {
            console.error('[Payment Records] Error fetching vendor payment records:', error);
            return { success: false, data: [], message: 'Error fetching payment records' };
        }
    }

    /**
     * Generate HTML for payment records - Timeline View
     */
    function generatePaymentRecordsHTML(paymentRecords) {
        if (!paymentRecords || paymentRecords.length === 0) {
            return `
                <div class="empty-state" style="text-align: center; padding: 30px 20px; color: #9ca3af;">
                    <i class="fas fa-history" style="font-size: 2em; color: #d1d5db; margin-bottom: 10px; display: block;"></i>
                    <p style="color: #6b7280;">No payment records found</p>
                </div>
            `;
        }

        let html = `
            <div class="payment-timeline" style="position: relative; padding: 20px 0; padding-left: 40px;">
                <!-- Timeline Line -->
                <div style="position: absolute; left: 14px; top: 0; bottom: 0; width: 2px; background: #d1d5db;"></div>
        `;

        paymentRecords.forEach((record, index) => {
            const recordDate = new Date(record.payment_date_logged).toLocaleDateString('en-IN', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            const amount = record.line_item_amount ? 
                parseFloat(record.line_item_amount).toFixed(2) : 
                parseFloat(record.payment_amount_base).toFixed(2);

            // Get project name with proper priority order
            let project = 'N/A';
            if (record.project_name && record.project_name.trim()) {
                // Use project name from join
                project = record.project_name;
            } else if (record.project_name_reference && record.project_name_reference.trim() && isNaN(record.project_name_reference)) {
                // Use project_name_reference if it's not a number
                project = record.project_name_reference;
            } else if (record.project_type_category && record.project_type_category.trim()) {
                // Use category as fallback
                project = record.project_type_category;
            }
            
            const paymentMode = record.line_item_payment_mode || record.payment_mode_selected || 'N/A';
            const status = record.entry_status_current || record.line_item_status || 'pending';
            const statusClass = getStatusBadgeClass(status);
            const statusDisplay = status.charAt(0).toUpperCase() + status.slice(1);
            
            // Determine timeline dot color based on status (minimalistic grayscale)
            let dotColor = '#6b7280';
            if (statusClass === 'approved') dotColor = '#374151';
            else if (statusClass === 'rejected') dotColor = '#9ca3af';
            else if (statusClass === 'pending') dotColor = '#d1d5db';

            html += `
                <!-- Timeline Item -->
                <div style="margin-bottom: 24px; position: relative;">
                    <!-- Timeline Dot -->
                    <div style="position: absolute; left: -33px; top: 0; width: 30px; height: 30px; background: white; border: 3px solid ${dotColor}; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; color: white; font-weight: bold; background: ${dotColor}; z-index: 2;">
                        ${index + 1}
                    </div>

                    <!-- Card Content -->
                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'; this.style.transform='translateY(0)';">
                        
                        <!-- Header: Date and Status -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: #1f2937;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 6px; color: #6b7280;"></i>
                                    ${recordDate}
                                </div>
                            </div>
                            <span class="payment-status-badge ${statusClass}" style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: capitalize;">
                                ${statusDisplay}
                            </span>
                        </div>

                        <!-- Project Info -->
                        <div style="margin-bottom: 12px;">
                            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500;">Project</div>
                            <div style="font-size: 14px; color: #1f2937; font-weight: 500;">
                                <i class="fas fa-briefcase" style="margin-right: 8px; color: #6b7280;"></i>
                                ${project}
                            </div>
                        </div>

                        <!-- Amount and Mode Row -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; padding-top: 12px; border-top: 1px solid #f3f4f6;">
                            <div>
                                <div style="font-size: 12px; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500;">Amount</div>
                                <div style="font-size: 16px; color: #374151; font-weight: 700;">
                                    ₹${amount}
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #9ca3af; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500;">Mode</div>
                                <div style="font-size: 14px; color: #1f2937;">
                                    <i class="fas fa-credit-card" style="margin-right: 6px; color: #6b7280;"></i>
                                    ${paymentMode}
                                </div>
                            </div>
                        </div>

                        <!-- Acceptance Methods -->
                        ${record.acceptance_methods && record.acceptance_methods.length > 0 ? `
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #f3f4f6;">
                            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500;">
                                <i class="fas fa-money-check" style="margin-right: 6px;"></i>Payment Methods
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                ${record.acceptance_methods.map(method => {
                                    const methodAmount = parseFloat(method.amount).toFixed(2);
                                    return `
                                        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px; font-size: 12px;">
                                            <div style="color: #6b7280; font-weight: 600; margin-bottom: 2px;">${method.method_type}</div>
                                            <div style="color: #374151; font-weight: 600;">₹${methodAmount}</div>
                                            ${method.reference ? `<div style="color: #d1d5db; font-size: 11px;">Ref: ${method.reference}</div>` : ''}
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                        ` : ''}

                    </div>
                </div>
            `;
        });

        html += `
            </div>
        `;

        return html;
    }

    /**
     * Get CSS class for status badge
     */
    function getStatusBadgeClass(status) {
        const statusMap = {
            'draft': 'draft',
            'submitted': 'submitted',
            'pending': 'pending',
            'approved': 'approved',
            'rejected': 'rejected',
            'verified': 'approved',
            'active': 'approved',
            'inactive': 'rejected'
        };

        return statusMap[status?.toLowerCase()] || 'pending';
    }

    /**
     * Inject payment records into vendor details modal
     */
    async function injectPaymentRecordsIntoModal(vendorId, container) {
        if (!container) {
            console.log('[Payment Records] Container not found');
            return;
        }

        console.log(`[Payment Records] Injecting for vendor ${vendorId}`);

        // Show loading state
        container.innerHTML = `
            <div style="text-align: center; padding: 30px 20px;">
                <i class="fas fa-spinner" style="font-size: 2em; color: #6b7280; animation: spin 1s linear infinite; display: block; margin-bottom: 15px;"></i>
                <p style="color: #9ca3af;">Loading payment records...</p>
            </div>
        `;

        // Fetch payment records
        const result = await fetchVendorPaymentRecords(vendorId);

        console.log(`[Payment Records] Inject result:`, result);

        if (result.success && result.data && result.data.length > 0) {
            console.log(`[Payment Records] Displaying ${result.data.length} records`);
            // Generate and inject HTML
            const html = generatePaymentRecordsHTML(result.data);
            container.innerHTML = html;

            // Inject CSS styles for payment status badges
            if (!document.getElementById('payment-status-badge-styles')) {
                const style = document.createElement('style');
                style.id = 'payment-status-badge-styles';
                style.textContent = `
                    .payment-status-badge.draft {
                        background-color: #f3f4f6;
                        color: #6b7280;
                    }
                    
                    .payment-status-badge.submitted {
                        background-color: #e5e7eb;
                        color: #4b5563;
                    }
                    
                    .payment-status-badge.pending {
                        background-color: #d1d5db;
                        color: #374151;
                    }
                    
                    .payment-status-badge.approved {
                        background-color: #9ca3af;
                        color: #ffffff;
                    }
                    
                    .payment-status-badge.rejected {
                        background-color: #6b7280;
                        color: #ffffff;
                    }
                `;
                document.head.appendChild(style);
            }
        } else {
            // Show empty state
            container.innerHTML = `
                <div class="empty-state" style="text-align: center; padding: 30px 20px; color: #a0aec0;">
                    <i class="fas fa-history" style="font-size: 2em; color: #cbd5e0; margin-bottom: 10px; display: block;"></i>
                    <p>No payment records found</p>
                </div>
            `;
        }
    }

</script>

