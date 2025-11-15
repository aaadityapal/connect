<?php
/**
 * Labour Details Modal
 * Displays comprehensive labour information in a modal dialog
 * Triggered when user clicks the "view" icon on a labour row
 */
?>

<div id="labourDetailsModal" class="labour-details-modal">
    <div class="labour-details-modal-overlay"></div>
    <div class="labour-details-modal-content">
        <!-- Modal Header -->
        <div class="labour-details-modal-header">
            <h2>Labour Details</h2>
            <button class="labour-details-close-btn" onclick="closeLabourDetailsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body with Loading State -->
        <div class="labour-details-modal-body">
            <div id="labourDetailsContainer">
                <div class="labour-details-loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading labour details...</p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="labour-details-modal-footer">
            <button class="labour-details-btn labour-details-btn-secondary" onclick="closeLabourDetailsModal()">
                <i class="fas fa-times"></i> Close
            </button>
            <button class="labour-details-btn labour-details-btn-primary" onclick="editLabourFromModal()">
                <i class="fas fa-edit"></i> Edit
            </button>
        </div>
    </div>
</div>

<style>
    /* Labour Details Modal Styles */
    .labour-details-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
    }

    .labour-details-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .labour-details-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        cursor: pointer;
    }

    .labour-details-modal-content {
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

    .labour-details-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px;
        border-bottom: 1px solid #e2e8f0;
    }

    .labour-details-modal-header h2 {
        margin: 0;
        font-size: 1.5em;
        color: #2a4365;
        font-weight: 600;
    }

    .labour-details-close-btn {
        background: none;
        border: none;
        font-size: 1.5em;
        color: #a0aec0;
        cursor: pointer;
        transition: color 0.2s ease;
        padding: 5px;
    }

    .labour-details-close-btn:hover {
        color: #2a4365;
    }

    .labour-details-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 25px;
    }

    .labour-details-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 20px 25px;
        border-top: 1px solid #e2e8f0;
    }

    /* Loading State */
    .labour-details-loading {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }

    .labour-details-loading i {
        font-size: 2.5em;
        color: #2a4365;
        animation: spin 1s linear infinite;
        display: block;
        margin-bottom: 15px;
    }

    /* Details Sections */
    .labour-details-section {
        margin-bottom: 20px;
    }

    .labour-details-section-title {
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

    .labour-details-section-title:hover {
        background: #edf2f7;
    }

    .labour-details-section-title i {
        font-size: 1.1em;
        margin-right: 10px;
        color: #2a4365;
        width: 20px;
        text-align: center;
    }

    .labour-details-section-title-text {
        display: flex;
        align-items: center;
        flex: 1;
        gap: 10px;
    }

    .labour-details-section-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        color: #718096;
        transition: transform 0.3s ease;
    }

    .labour-details-section.collapsed .labour-details-section-toggle {
        transform: rotate(-90deg);
    }

    .labour-details-grid {
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

    .labour-details-section.collapsed .labour-details-grid {
        max-height: 0;
        padding: 0 15px;
        border: none;
    }

    .labour-details-documents-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        padding: 20px;
        background: white;
        border-bottom: 1px solid #e2e8f0;
        border-left: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        max-height: 1000px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .labour-details-section.collapsed .labour-details-documents-grid {
        max-height: 0;
        padding: 0 20px;
        border: none;
    }

    .labour-detail-item {
        display: flex;
        flex-direction: column;
    }

    .labour-detail-label {
        font-size: 0.75em;
        color: #a0aec0;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 6px;
    }

    .labour-detail-value {
        font-size: 0.95em;
        color: #2a4365;
        font-weight: 500;
        word-break: break-word;
        line-height: 1.4;
    }

    .labour-detail-value.empty {
        color: #cbd5e0;
        font-style: italic;
        font-size: 0.9em;
    }

    /* Status Badge in Modal */
    .labour-details-status {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 24px;
        font-size: 0.85em;
        font-weight: 600;
        text-transform: capitalize;
        width: fit-content;
    }

    .labour-details-status.active {
        background-color: #c6f6d5;
        color: #22543d;
    }

    .labour-details-status.inactive {
        background-color: #fed7d7;
        color: #742a2a;
    }

    .labour-details-status.suspended {
        background-color: #feebc8;
        color: #7c2d12;
    }

    /* Modal Buttons */
    .labour-details-btn {
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

    .labour-details-btn-primary {
        background: #2a4365;
        color: white;
    }

    .labour-details-btn-primary:hover {
        background: #1a365d;
        transform: translateY(-2px);
    }

    .labour-details-btn-secondary {
        background: #e2e8f0;
        color: #2a4365;
    }

    .labour-details-btn-secondary:hover {
        background: #cbd5e0;
    }

    /* Error State */
    .labour-details-error {
        background-color: #fff5f5;
        border: 1px solid #fed7d7;
        color: #742a2a;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }

    .labour-details-error i {
        font-size: 2em;
        display: block;
        margin-bottom: 10px;
    }

    /* Document Images */
    .labour-document-image {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .labour-document-image:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .labour-details-modal-content {
            width: 95%;
            max-height: 90vh;
        }

        .labour-details-grid {
            grid-template-columns: 1fr;
        }

        .labour-details-modal-header h2 {
            font-size: 1.2em;
        }

        .labour-details-modal-footer {
            flex-direction: column-reverse;
        }

        .labour-details-btn {
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
    // Store current labour ID
    let currentLabourId = null;

    /**
     * Open labour details modal and fetch labour data
     * @param {number} labourId - The labour ID to display
     */
    function openLabourDetailsModal(labourId) {
        currentLabourId = labourId;
        const modal = document.getElementById('labourDetailsModal');
        const container = document.getElementById('labourDetailsContainer');

        // Show modal with loading state
        modal.classList.add('active');
        container.innerHTML = `
            <div class="labour-details-loading">
                <i class="fas fa-spinner"></i>
                <p>Loading labour details...</p>
            </div>
        `;

        // Fetch labour details from backend
        fetch(`fetch_labour_details.php?labour_id=${labourId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayLabourDetails(data.data);
                } else {
                    showLabourDetailsError(data.message || 'Failed to load labour details');
                }
            })
            .catch(error => {
                console.error('Error fetching labour details:', error);
                showLabourDetailsError('An error occurred while loading labour details. Please try again.');
            });

        // Close modal when overlay is clicked
        document.querySelector('.labour-details-modal-overlay').addEventListener('click', closeLabourDetailsModal);
    }

    /**
     * Display labour details in the modal
     * @param {object} labour - Labour data object
     */
    function displayLabourDetails(labour) {
        const container = document.getElementById('labourDetailsContainer');
        const statusClass = labour.status ? labour.status.toLowerCase() : 'active';

        let html = `
            <!-- Header with Labour Code and Status -->
            <div class="labour-details-section">
                <div class="labour-details-grid" style="grid-template-columns: 1fr 1fr; align-items: start; padding: 20px 15px;">
                    <div>
                        <div class="labour-detail-item">
                            <div class="labour-detail-label">Labour Code</div>
                            <div class="labour-detail-value" style="font-size: 1.1em; font-weight: 600;">${labour.labour_unique_code || 'N/A'}</div>
                        </div>
                    </div>
                    <div>
                        <div class="labour-detail-item">
                            <div class="labour-detail-label">Status</div>
                            <span class="labour-details-status ${statusClass}">${labour.status || 'Active'}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Basic Information Section (Always Open) -->
            <div class="labour-details-section">
                <div class="labour-details-section-title">
                    <div class="labour-details-section-title-text">
                        <i class="fas fa-user-circle"></i>
                        <span>Basic Information</span>
                    </div>
                </div>
                <div class="labour-details-grid">
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">Full Name</div>
                        <div class="labour-detail-value">${labour.full_name || 'N/A'}</div>
                    </div>
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">Labour Type</div>
                        <div class="labour-detail-value">${labour.labour_type || 'N/A'}</div>
                    </div>
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">Contact Number</div>
                        <div class="labour-detail-value">${labour.contact_number || 'N/A'}</div>
                    </div>
                    ${labour.alt_number ? `
                        <div class="labour-detail-item">
                            <div class="labour-detail-label">Alternate Number</div>
                            <div class="labour-detail-value">${labour.alt_number}</div>
                        </div>
                    ` : ''}
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">Daily Salary</div>
                        <div class="labour-detail-value">${labour.daily_salary ? '₹' + parseFloat(labour.daily_salary).toFixed(2) : 'Not provided'}</div>
                    </div>
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">Join Date</div>
                        <div class="labour-detail-value ${!labour.join_date ? 'empty' : ''}">${labour.join_date ? new Date(labour.join_date).toLocaleDateString('en-IN') : 'Not provided'}</div>
                    </div>
                </div>
            </div>

            <!-- Address Details Section (Collapsible, Closed by default) -->
            <div class="labour-details-section collapsed">
                <div class="labour-details-section-title" onclick="toggleLabourSection(this)">
                    <div class="labour-details-section-title-text">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Address</span>
                    </div>
                    <span class="labour-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="labour-details-grid">
                    <div class="labour-detail-item" style="grid-column: 1 / -1;">
                        <div class="labour-detail-label">Street Address</div>
                        <div class="labour-detail-value ${!labour.street_address ? 'empty' : ''}">${labour.street_address || 'Not provided'}</div>
                    </div>
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">City</div>
                        <div class="labour-detail-value ${!labour.city ? 'empty' : ''}">${labour.city || 'Not provided'}</div>
                    </div>
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">State</div>
                        <div class="labour-detail-value ${!labour.state ? 'empty' : ''}">${labour.state || 'Not provided'}</div>
                    </div>
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">Zip Code</div>
                        <div class="labour-detail-value ${!labour.zip_code ? 'empty' : ''}">${labour.zip_code || 'Not provided'}</div>
                    </div>
                </div>
            </div>

            <!-- Documents Section (Collapsible, Closed by default) -->
            <div class="labour-details-section collapsed">
                <div class="labour-details-section-title" onclick="toggleLabourSection(this)">
                    <div class="labour-details-section-title-text">
                        <i class="fas fa-file-alt"></i>
                        <span>Documents</span>
                    </div>
                    <span class="labour-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="labour-details-documents-grid">
                    ${labour.aadhar_card ? `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                            <div style="width: 100%; aspect-ratio: 4/3; border-radius: 6px; border: 1px solid #e2e8f0; overflow: hidden; background: #f7fafc; display: flex; align-items: center; justify-content: center;">
                                <img src="${labour.aadhar_card}" 
                                     alt="Aadhar Card" 
                                     class="labour-document-image"
                                     loading="lazy"
                                     style="cursor: pointer; width: 100%; height: 100%; object-fit: cover;"
                                     onerror="handleDocumentImageError(this, 'Aadhar Card')"
                                     onclick="openImageFullscreen(this.src)">
                            </div>
                            <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">Aadhar Card</div>
                        </div>
                    ` : `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; opacity: 0.5;">
                            <div style="width: 100%; aspect-ratio: 4/3; border-radius: 6px; border: 2px dashed #cbd5e0; background: #f7fafc; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 2em; color: #cbd5e0;"></i>
                            </div>
                            <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">Aadhar Card</div>
                        </div>
                    `}
                    ${labour.pan_card ? `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                            <div style="width: 100%; aspect-ratio: 4/3; border-radius: 6px; border: 1px solid #e2e8f0; overflow: hidden; background: #f7fafc; display: flex; align-items: center; justify-content: center;">
                                <img src="${labour.pan_card}" 
                                     alt="PAN Card" 
                                     class="labour-document-image"
                                     loading="lazy"
                                     style="cursor: pointer; width: 100%; height: 100%; object-fit: cover;"
                                     onerror="handleDocumentImageError(this, 'PAN Card')"
                                     onclick="openImageFullscreen(this.src)">
                            </div>
                            <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">PAN Card</div>
                        </div>
                    ` : `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; opacity: 0.5;">
                            <div style="width: 100%; aspect-ratio: 4/3; border-radius: 6px; border: 2px dashed #cbd5e0; background: #f7fafc; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 2em; color: #cbd5e0;"></i>
                            </div>
                            <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">PAN Card</div>
                        </div>
                    `}
                    ${labour.voter_id ? `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                            <div style="width: 100%; aspect-ratio: 4/3; border-radius: 6px; border: 1px solid #e2e8f0; overflow: hidden; background: #f7fafc; display: flex; align-items: center; justify-content: center;">
                                <img src="${labour.voter_id}" 
                                     alt="Voter ID" 
                                     class="labour-document-image"
                                     loading="lazy"
                                     style="cursor: pointer; width: 100%; height: 100%; object-fit: cover;"
                                     onerror="handleDocumentImageError(this, 'Voter ID')"
                                     onclick="openImageFullscreen(this.src)">
                            </div>
                            <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">Voter ID</div>
                        </div>
                    ` : `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; opacity: 0.5;">
                            <div style="width: 100%; aspect-ratio: 4/3; border-radius: 6px; border: 2px dashed #cbd5e0; background: #f7fafc; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 2em; color: #cbd5e0;"></i>
                            </div>
                            <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">Voter ID</div>
                        </div>
                    `}
                    ${labour.other_document ? `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                            <div style="width: 100%; aspect-ratio: 4/3; border-radius: 6px; border: 1px solid #e2e8f0; overflow: hidden; background: #f7fafc; display: flex; align-items: center; justify-content: center;">
                                <img src="${labour.other_document}" 
                                     alt="Other Document" 
                                     class="labour-document-image"
                                     loading="lazy"
                                     style="cursor: pointer; width: 100%; height: 100%; object-fit: cover;"
                                     onerror="handleDocumentImageError(this, 'Other Document')"
                                     onclick="openImageFullscreen(this.src)">
                            </div>
                            <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">Other Document</div>
                        </div>
                    ` : `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; opacity: 0.5;">
                            <div style="width: 100%; aspect-ratio: 4/3; border-radius: 6px; border: 2px dashed #cbd5e0; background: #f7fafc; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 2em; color: #cbd5e0;"></i>
                            </div>
                            <div style="font-size: 0.75em; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">Other Document</div>
                        </div>
                    `}
                </div>
            </div>

            <!-- Additional Information Section (Collapsible, Closed by default) -->
            <div class="labour-details-section collapsed">
                <div class="labour-details-section-title" onclick="toggleLabourSection(this)">
                    <div class="labour-details-section-title-text">
                        <i class="fas fa-info-circle"></i>
                        <span>Additional Information</span>
                    </div>
                    <span class="labour-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="labour-details-grid">
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">Created Date</div>
                        <div class="labour-detail-value">${labour.created_at ? new Date(labour.created_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</div>
                    </div>
                    <div class="labour-detail-item">
                        <div class="labour-detail-label">Last Updated</div>
                        <div class="labour-detail-value">${labour.updated_at ? new Date(labour.updated_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</div>
                    </div>
                </div>
            </div>

            <!-- Payment Section (Collapsible, Closed by default) -->
            <div class="labour-details-section collapsed">
                <div class="labour-details-section-title" onclick="toggleLabourSection(this)">
                    <div class="labour-details-section-title-text">
                        <i class="fas fa-credit-card"></i>
                        <span>Payment Records</span>
                    </div>
                    <span class="labour-details-section-toggle"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="labour-details-grid">
                    <div class="labour-detail-item" style="grid-column: 1 / -1;">
                        <div class="empty-state" style="text-align: center; padding: 30px 20px; color: #a0aec0;">
                            <i class="fas fa-history" style="font-size: 2em; color: #cbd5e0; margin-bottom: 10px; display: block;"></i>
                            <p>No payment records found</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    /**
     * Show error message in labour details modal
     * @param {string} message - Error message to display
     */
    function showLabourDetailsError(message) {
        const container = document.getElementById('labourDetailsContainer');
        container.innerHTML = `
            <div class="labour-details-error">
                <i class="fas fa-exclamation-circle"></i>
                <p>${message}</p>
            </div>
        `;
    }

    /**
     * Close labour details modal
     */
    function closeLabourDetailsModal() {
        const modal = document.getElementById('labourDetailsModal');
        modal.classList.remove('active');
        currentLabourId = null;
    }

    /**
     * Toggle collapsible section open/closed
     */
    function toggleLabourSection(titleElement) {
        const section = titleElement.closest('.labour-details-section');
        section.classList.toggle('collapsed');
    }

    /**
     * Handle document image loading errors
     */
    function handleDocumentImageError(imgElement, documentName) {
        console.error(`Failed to load ${documentName} from:`, imgElement.src);
        imgElement.style.display = 'none';
        
        // Show error placeholder
        const container = imgElement.parentElement;
        const errorDiv = document.createElement('div');
        errorDiv.style.cssText = 'background: #fff5f5; border: 1px solid #fed7d7; color: #742a2a; padding: 15px; border-radius: 6px; text-align: center;';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-circle" style="font-size: 1.5em; margin-bottom: 8px; display: block;"></i>
            <p style="margin: 0; font-size: 0.9em;">${documentName} not found</p>
            <small style="color: #a0aec0; display: block; margin-top: 5px; word-break: break-all;">${imgElement.src}</small>
        `;
        container.appendChild(errorDiv);
    }

    /**
     * Edit labour from modal - opens edit modal
     */
    function editLabourFromModal() {
        if (currentLabourId) {
            // Store labour ID before closing
            const labourIdToEdit = currentLabourId;
            // Close the details modal
            closeLabourDetailsModal();
            // Open the edit modal with stored labour ID
            openLabourEditModal(labourIdToEdit);
        } else {
            alert('Error: Labour ID not found');
        }
    }

    // Close modal when Escape key is pressed
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('labourDetailsModal');
            if (modal && modal.classList.contains('active')) {
                closeLabourDetailsModal();
            }
        }
    });
</script>
