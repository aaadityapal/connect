<?php
/**
 * Payment Entry Files Registry Modal
 * Displays all attachments/files associated with a payment entry
 * File Type: Modal Template
 * Unique ID: payment_entry_files_registry_modal
 */
?>

<div id="paymentEntryFilesRegistryModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="width: 90%; max-width: 900px; max-height: 80vh; overflow-y: auto;">
        <!-- Modal Header -->
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding: 20px; background: #f7fafc;">
            <h2 style="margin: 0; color: #2d3748; font-size: 1.5em;">
                <i class="fas fa-file-archive" style="color: #4299e1; margin-right: 10px;"></i>
                Payment Entry Files
            </h2>
            <button class="modal-close-btn" onclick="closePaymentFilesModal()" style="background: none; border: none; font-size: 1.5em; cursor: pointer; color: #718096;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body" style="padding: 20px;">
            <input type="hidden" id="paymentEntryIdForFiles" value="">

            <!-- File Stats Section -->
            <div id="fileStatsSection" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <div class="stat-card" style="background: #edf2f7; padding: 15px; border-radius: 8px; border-left: 4px solid #4299e1;">
                    <div style="font-size: 0.85em; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Total Files</div>
                    <div id="totalFilesCount" style="font-size: 2em; font-weight: 700; color: #2d3748;">0</div>
                </div>
                <div class="stat-card" style="background: #fef5e7; padding: 15px; border-radius: 8px; border-left: 4px solid #f6ad55;">
                    <div style="font-size: 0.85em; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Total Size</div>
                    <div id="totalFilesSize" style="font-size: 2em; font-weight: 700; color: #2d3748;">0 MB</div>
                </div>
                <div class="stat-card" style="background: #f0fff4; padding: 15px; border-radius: 8px; border-left: 4px solid #48bb78;">
                    <div style="font-size: 0.85em; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Verified</div>
                    <div id="verifiedFilesCount" style="font-size: 2em; font-weight: 700; color: #2d3748;">0</div>
                </div>
            </div>

            <!-- File Filter Section -->
            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="file-type-filter" data-filter="all" style="padding: 8px 16px; border: 2px solid #cbd5e0; background: #edf2f7; border-radius: 6px; cursor: pointer; font-weight: 500; color: #2d3748; transition: all 0.3s;">
                    All Files
                </button>
                <button class="file-type-filter" data-filter="proof_image" style="padding: 8px 16px; border: 2px solid #cbd5e0; background: white; border-radius: 6px; cursor: pointer; font-weight: 500; color: #718096; transition: all 0.3s;">
                    <i class="fas fa-image"></i> Proof Images
                </button>
                <button class="file-type-filter" data-filter="acceptance_method_media" style="padding: 8px 16px; border: 2px solid #cbd5e0; background: white; border-radius: 6px; cursor: pointer; font-weight: 500; color: #718096; transition: all 0.3s;">
                    <i class="fas fa-check-circle"></i> Acceptance Media
                </button>
                <button class="file-type-filter" data-filter="line_item_media" style="padding: 8px 16px; border: 2px solid #cbd5e0; background: white; border-radius: 6px; cursor: pointer; font-weight: 500; color: #718096; transition: all 0.3s;">
                    <i class="fas fa-list"></i> Line Item Media
                </button>
                <button class="file-type-filter" data-filter="line_item_method_media" style="padding: 8px 16px; border: 2px solid #cbd5e0; background: white; border-radius: 6px; cursor: pointer; font-weight: 500; color: #718096; transition: all 0.3s;">
                    <i class="fas fa-cogs"></i> Method Media
                </button>
            </div>

            <!-- Files List Section -->
            <div id="filesListContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: #a0aec0;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2em; margin-bottom: 10px;"></i>
                    <p>Loading files...</p>
                </div>
            </div>

            <!-- Alternative Table View for Large Files -->
            <div id="filesTableContainer" style="display: none; overflow-x: auto; margin-top: 20px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                    <thead>
                        <tr style="background: #edf2f7; border-bottom: 2px solid #cbd5e0;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #2d3748;">File Name</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #2d3748;">Type</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #2d3748;">Size</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #2d3748;">Status</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #2d3748;">Uploaded</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #2d3748;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="filesTableBody">
                        <tr>
                            <td colspan="6" style="padding: 20px; text-align: center; color: #a0aec0;">
                                <i class="fas fa-spinner fa-spin"></i> Loading files...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- No Files Message -->
            <div id="noFilesMessage" style="display: none; text-align: center; padding: 40px 20px; background: #f7fafc; border-radius: 8px; border: 2px dashed #cbd5e0;">
                <i class="fas fa-inbox" style="font-size: 3em; color: #cbd5e0; margin-bottom: 15px;"></i>
                <p style="color: #718096; font-size: 1.1em; margin: 0;">No files attached to this payment entry yet.</p>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer" style="padding: 20px; background: #f7fafc; border-top: 2px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
            <button onclick="closePaymentFilesModal()" style="padding: 10px 20px; background: #e2e8f0; color: #2d3748; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s;">
                Close
            </button>
            <button onclick="downloadAllPaymentFiles()" id="downloadAllBtn" style="padding: 10px 20px; background: #4299e1; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s; display: none;">
                <i class="fas fa-download"></i> Download All
            </button>
        </div>
    </div>
</div>

<style>
    #paymentEntryFilesRegistryModal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .file-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .file-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
        border-color: #4299e1;
    }

    .file-icon {
        font-size: 2.5em;
        margin-bottom: 10px;
        transition: all 0.3s ease;
    }

    .file-card:hover .file-icon {
        transform: scale(1.1);
    }

    .file-name {
        font-weight: 500;
        color: #2d3748;
        margin-bottom: 8px;
        word-break: break-word;
        font-size: 0.9em;
    }

    .file-meta {
        font-size: 0.8em;
        color: #a0aec0;
        margin-bottom: 8px;
    }

    .file-actions {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .file-action-btn {
        padding: 6px 10px;
        border: 1px solid #cbd5e0;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.8em;
        transition: all 0.3s;
    }

    .file-action-btn:hover {
        background: #edf2f7;
        border-color: #4299e1;
        color: #4299e1;
    }

    .file-status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75em;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .status-verified {
        background: #c6f6d5;
        color: #22543d;
    }

    .status-pending {
        background: #bee3f8;
        color: #2c5282;
    }

    .status-quarantined {
        background: #fed7d7;
        color: #742a2a;
    }

    .file-type-filter.active {
        background: #4299e1;
        color: white;
        border-color: #3182ce;
    }
</style>

<script>
/**
 * Open payment entry files modal
 */
function openPaymentFilesModal(paymentEntryId, recipientFilter = null) {
    const modal = document.getElementById('paymentEntryFilesRegistryModal');
    const inputField = document.getElementById('paymentEntryIdForFiles');
    
    if (!modal || !inputField) {
        console.error('Payment files modal or input field not found');
        return;
    }

    inputField.value = paymentEntryId;
    
    // Store recipient filter for API calls
    window.currentRecipientFilter = recipientFilter;
    
    modal.style.display = 'flex';

    // Fetch and display files
    fetchPaymentEntryFiles(paymentEntryId, recipientFilter);

    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closePaymentFilesModal();
        }
    });
}

/**
 * Close payment entry files modal
 */
function closePaymentFilesModal() {
    const modal = document.getElementById('paymentEntryFilesRegistryModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Fetch files for a specific payment entry
 */
function fetchPaymentEntryFiles(paymentEntryId, recipientFilter = null) {
    const filesListContainer = document.getElementById('filesListContainer');
    const filesTableContainer = document.getElementById('filesTableContainer');
    const filesTableBody = document.getElementById('filesTableBody');
    const noFilesMessage = document.getElementById('noFilesMessage');

    if (!filesListContainer) {
        console.error('Files list container not found');
        return;
    }

    // Show loading state
    filesListContainer.innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: #a0aec0;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2em; margin-bottom: 10px;"></i>
            <p>Loading files...</p>
        </div>
    `;

    // Build API URL with optional recipient filter
    let apiUrl = `get_payment_entry_files.php?payment_entry_id=${encodeURIComponent(paymentEntryId)}`;
    
    if (recipientFilter) {
        // Filter by recipient name/ID
        apiUrl += `&recipient_filter=${encodeURIComponent(recipientFilter.name || recipientFilter.id)}`;
        apiUrl += `&recipient_type=${encodeURIComponent(recipientFilter.type || '')}`;
    }

    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.length > 0) {
                displayPaymentFiles(data.data);
                updateFileStats(data.data);
                noFilesMessage.style.display = 'none';
                filesListContainer.style.display = 'grid';
                
                // Show download all button if there are files
                const downloadBtn = document.getElementById('downloadAllBtn');
                if (downloadBtn) {
                    downloadBtn.style.display = 'inline-block';
                }
            } else {
                filesListContainer.innerHTML = '';
                filesTableContainer.style.display = 'none';
                noFilesMessage.style.display = 'block';
                
                // Hide download button
                const downloadBtn = document.getElementById('downloadAllBtn');
                if (downloadBtn) {
                    downloadBtn.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching payment entry files:', error);
            filesListContainer.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: #e53e3e;">
                    <i class="fas fa-exclamation-circle" style="font-size: 2em; margin-bottom: 10px;"></i>
                    <p>Error loading files: ${error.message}</p>
                </div>
            `;
        });
}

/**
 * Display payment files in card grid
 */
function displayPaymentFiles(files) {
    const filesListContainer = document.getElementById('filesListContainer');
    let html = '';

    files.forEach(file => {
        const fileIcon = getFileIcon(file.attachment_file_extension);
        const fileSize = formatFileSize(file.attachment_file_size_bytes);
        const uploadDate = new Date(file.attachment_upload_timestamp).toLocaleDateString('en-GB', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        const statusClass = 'status-' + (file.attachment_verification_status || 'pending');
        const statusLabel = (file.attachment_verification_status || 'pending').toUpperCase();

        html += `
            <div class="file-card" data-file-type="${file.attachment_type_category}">
                <div class="file-status-badge ${statusClass}">${statusLabel}</div>
                <div class="file-icon">${fileIcon}</div>
                <div class="file-name" title="${file.attachment_file_original_name}">${truncateFileName(file.attachment_file_original_name, 20)}</div>
                <div class="file-meta">
                    <div>${fileSize}</div>
                    <div style="font-size: 0.75em; color: #cbd5e0; margin-top: 4px;">${uploadDate}</div>
                </div>
                <div class="file-actions">
                    <button class="file-action-btn" onclick="downloadPaymentFile(${file.attachment_id}, '${file.attachment_file_original_name.replace(/'/g, "\\'")}')">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button class="file-action-btn" onclick="previewPaymentFile(${file.attachment_id}, '${file.attachment_file_extension}')">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>
            </div>
        `;
    });

    filesListContainer.innerHTML = html;

    // Add filter event listeners
    addFileFilterListeners();
}

/**
 * Add file type filter event listeners
 */
function addFileFilterListeners() {
    const filterButtons = document.querySelectorAll('.file-type-filter');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.style.background = 'white');
            this.style.background = '#edf2f7';

            const filterType = this.getAttribute('data-filter');
            const fileCards = document.querySelectorAll('.file-card');

            fileCards.forEach(card => {
                if (filterType === 'all' || card.getAttribute('data-file-type') === filterType) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
}

/**
 * Update file statistics
 */
function updateFileStats(files) {
    let totalSize = 0;
    let verifiedCount = 0;

    files.forEach(file => {
        totalSize += parseInt(file.attachment_file_size_bytes) || 0;
        if (file.attachment_verification_status === 'verified') {
            verifiedCount++;
        }
    });

    document.getElementById('totalFilesCount').textContent = files.length;
    document.getElementById('totalFilesSize').textContent = formatFileSize(totalSize);
    document.getElementById('verifiedFilesCount').textContent = verifiedCount;
}

/**
 * Get file icon based on extension
 */
function getFileIcon(extension) {
    const ext = (extension || '').toLowerCase();
    
    const iconMap = {
        'pdf': '<i class="fas fa-file-pdf" style="color: #e53e3e;"></i>',
        'doc': '<i class="fas fa-file-word" style="color: #2b6cb0;"></i>',
        'docx': '<i class="fas fa-file-word" style="color: #2b6cb0;"></i>',
        'xls': '<i class="fas fa-file-excel" style="color: #107c10;"></i>',
        'xlsx': '<i class="fas fa-file-excel" style="color: #107c10;"></i>',
        'jpg': '<i class="fas fa-file-image" style="color: #f6ad55;"></i>',
        'jpeg': '<i class="fas fa-file-image" style="color: #f6ad55;"></i>',
        'png': '<i class="fas fa-file-image" style="color: #f6ad55;"></i>',
        'gif': '<i class="fas fa-file-image" style="color: #f6ad55;"></i>',
        'mp4': '<i class="fas fa-file-video" style="color: #c53030;"></i>',
        'avi': '<i class="fas fa-file-video" style="color: #c53030;"></i>',
        'mov': '<i class="fas fa-file-video" style="color: #c53030;"></i>',
        'zip': '<i class="fas fa-file-archive" style="color: #744210;"></i>',
        'rar': '<i class="fas fa-file-archive" style="color: #744210;"></i>',
        'txt': '<i class="fas fa-file-alt" style="color: #4299e1;"></i>',
    };

    return iconMap[ext] || '<i class="fas fa-file" style="color: #718096;"></i>';
}

/**
 * Format file size in human readable format
 */
function formatFileSize(bytes) {
    bytes = parseInt(bytes) || 0;
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Truncate filename for display
 */
function truncateFileName(name, maxLength) {
    if (name.length > maxLength) {
        const ext = name.substring(name.lastIndexOf('.'));
        const nameWithoutExt = name.substring(0, name.lastIndexOf('.'));
        return nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
    }
    return name;
}

/**
 * Download a specific payment file
 */
function downloadPaymentFile(attachmentId, fileName) {
    const link = document.createElement('a');
    link.href = `download_payment_file.php?attachment_id=${encodeURIComponent(attachmentId)}`;
    link.download = fileName || 'download';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Preview a payment file
 */
function previewPaymentFile(attachmentId, extension) {
    const ext = (extension || '').toLowerCase();
    const previewExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

    if (previewExtensions.includes(ext)) {
        window.open(`preview_payment_file.php?attachment_id=${encodeURIComponent(attachmentId)}`, '_blank');
    } else {
        alert('Preview not available for this file type. Please download to view.');
    }
}

/**
 * Download all payment files as ZIP
 */
function downloadAllPaymentFiles() {
    const paymentEntryId = document.getElementById('paymentEntryIdForFiles').value;
    if (!paymentEntryId) {
        alert('Payment entry ID not found');
        return;
    }

    const link = document.createElement('a');
    link.href = `download_payment_files_zip.php?payment_entry_id=${encodeURIComponent(paymentEntryId)}`;
    link.download = `payment_entry_${paymentEntryId}_files.zip`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
