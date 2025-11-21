<?php
/**
 * Recipient Files Modal
 * Displays only files/attachments for a specific recipient (labour/vendor)
 * Data fetched from:
 * - tbl_payment_entry_line_items_detail (for line item uploads)
 * - tbl_payment_acceptance_methods_line_items (for acceptance method uploads)
 */
?>

<div id="recipientFilesModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); justify-content: center; align-items: center; z-index: 1000;">
    <div class="modal-content" style="width: 90%; max-width: 800px; max-height: 75vh; overflow-y: auto; background: white; border-radius: 8px; box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);">
        
        <!-- Modal Header -->
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding: 20px; background: #f7fafc;">
            <div>
                <h2 id="recipientName" style="margin: 0; color: #2d3748; font-size: 1.5em;">Recipient Files</h2>
                <p id="recipientType" style="margin: 5px 0 0 0; color: #718096; font-size: 0.9em;"></p>
            </div>
            <button onclick="closeRecipientFilesModal()" style="background: none; border: none; font-size: 1.5em; cursor: pointer; color: #718096;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body" style="padding: 20px;">
            <input type="hidden" id="recipientFileEntryId" value="">
            <input type="hidden" id="recipientFileLineItemId" value="">
            <input type="hidden" id="recipientFileRecipientName" value="">
            <input type="hidden" id="recipientFileRecipientType" value="">

            <!-- File Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
                <div style="background: #edf2f7; padding: 12px; border-radius: 6px; border-left: 4px solid #4299e1;">
                    <div style="font-size: 0.75em; color: #718096; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">No Split Payments</div>
                    <div id="lineItemFileCount" style="font-size: 1.8em; font-weight: 700; color: #2d3748;">0</div>
                </div>
                <div style="background: #fef5e7; padding: 12px; border-radius: 6px; border-left: 4px solid #f6ad55;">
                    <div style="font-size: 0.75em; color: #718096; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">Split Payments</div>
                    <div id="acceptanceFileCount" style="font-size: 1.8em; font-weight: 700; color: #2d3748;">0</div>
                </div>
                <div style="background: #f0fff4; padding: 12px; border-radius: 6px; border-left: 4px solid #48bb78;">
                    <div style="font-size: 0.75em; color: #718096; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">Total Size</div>
                    <div id="recipientTotalSize" style="font-size: 1.8em; font-weight: 700; color: #2d3748;">0 KB</div>
                </div>
            </div>

            <!-- Tabs for different file types -->
            <div style="border-bottom: 2px solid #e2e8f0; margin-bottom: 15px; display: flex; gap: 0;">
                <button class="recipient-file-tab" data-tab="line-item" style="flex: 1; padding: 12px; background: #edf2f7; border: none; cursor: pointer; font-weight: 600; color: #2d3748; border-bottom: 3px solid #4299e1;">
                    <i class="fas fa-list"></i> No Split Payments
                </button>
                <button class="recipient-file-tab" data-tab="acceptance" style="flex: 1; padding: 12px; background: white; border: none; cursor: pointer; font-weight: 600; color: #718096;">
                    <i class="fas fa-check-circle"></i> Split Payments
                </button>
            </div>

            <!-- Line Item Files Section -->
            <div id="lineItemFilesSection" class="recipient-file-section" style="display: block;">
                <div id="lineItemFilesContainer" style="display: grid; gap: 12px;">
                    <div style="text-align: center; padding: 30px; color: #a0aec0;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p>Loading line item files...</p>
                    </div>
                </div>
            </div>

            <!-- Acceptance Method Files Section -->
            <div id="acceptanceFilesSection" class="recipient-file-section" style="display: none;">
                <div id="acceptanceFilesContainer" style="display: grid; gap: 12px;">
                    <div style="text-align: center; padding: 30px; color: #a0aec0;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p>Loading acceptance files...</p>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div id="recipientNoFilesMessage" style="display: none; text-align: center; padding: 40px 20px; background: #f7fafc; border-radius: 8px; border: 2px dashed #cbd5e0;">
                <i class="fas fa-inbox" style="font-size: 3em; color: #cbd5e0; margin-bottom: 15px;"></i>
                <p style="color: #718096; font-size: 1.1em; margin: 0;">No files attached to this recipient.</p>
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="padding: 15px 20px; background: #f7fafc; border-top: 2px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
            <button onclick="closeRecipientFilesModal()" style="padding: 10px 16px; background: #e2e8f0; color: #2d3748; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                Close
            </button>
        </div>
    </div>
</div>

<style>
    /* Modal overlay - hidden by default, shown when .active class is added */
    #recipientFilesModal {
        display: none !important;
    }

    #recipientFilesModal.active {
        display: flex !important;
    }

    .recipient-file-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }

    .recipient-file-item:hover {
        border-color: #4299e1;
        box-shadow: 0 4px 12px rgba(66, 153, 225, 0.1);
    }

    .recipient-file-info {
        flex: 1;
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .recipient-file-icon {
        font-size: 1.8em;
        min-width: 40px;
        text-align: center;
    }

    .recipient-file-details {
        flex: 1;
    }

    .recipient-file-name {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 4px;
        word-break: break-word;
    }

    .recipient-file-meta {
        font-size: 0.8em;
        color: #a0aec0;
        display: flex;
        gap: 10px;
    }

    .recipient-file-actions {
        display: flex;
        gap: 6px;
    }

    .recipient-file-action-btn {
        padding: 6px 10px;
        border: 1px solid #cbd5e0;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.8em;
        transition: all 0.2s;
        color: #2d3748;
    }

    .recipient-file-action-btn:hover {
        background: #4299e1;
        border-color: #4299e1;
        color: white;
    }

    .recipient-file-type-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 0.7em;
        font-weight: 600;
        text-transform: uppercase;
        background: #edf2f7;
        color: #2d3748;
        margin-right: 8px;
    }
</style>

<script>
/**
 * Open recipient files modal
 * Called from expanded entry details when user clicks "Proofs" button
 */
function openRecipientFilesModal(paymentEntryId, recipientIndex, recipientJsonString) {
    try {
        const modal = document.getElementById('recipientFilesModal');
        
        if (!modal) {
            console.error('Recipient files modal not found in DOM');
            alert('Modal not loaded. Please refresh the page.');
            return;
        }

        // Parse recipient data from JSON string
        let recipient;
        try {
            recipient = JSON.parse(recipientJsonString);
        } catch (e) {
            console.error('Error parsing recipient data:', e, 'Raw string:', recipientJsonString);
            alert('Error loading recipient data');
            return;
        }

        // Get line item ID - should be in recipient object or we use index
        const lineItemId = recipient.line_item_id || recipientIndex;

        // Store IDs for API calls with null checks
        const entryIdField = document.getElementById('recipientFileEntryId');
        const lineItemIdField = document.getElementById('recipientFileLineItemId');
        const recipientNameField = document.getElementById('recipientFileRecipientName');
        const recipientTypeField = document.getElementById('recipientFileRecipientType');
        
        if (entryIdField) entryIdField.value = paymentEntryId;
        if (lineItemIdField) lineItemIdField.value = lineItemId;
        if (recipientNameField) recipientNameField.value = recipient.name || 'Unknown';
        if (recipientTypeField) recipientTypeField.value = recipient.type || 'unknown';

        // Update modal header with null checks
        const recipientNameElement = document.getElementById('recipientName');
        const recipientTypeElement = document.getElementById('recipientType');
        
        if (recipientNameElement) {
            recipientNameElement.textContent = recipient.name || 'Unknown Recipient';
        }
        if (recipientTypeElement) {
            recipientTypeElement.textContent = 
                `${(recipient.type || 'unknown').toUpperCase()} - ${recipient.category || recipient.vendor_category || 'N/A'}`;
        }

        // Show modal by adding active class
        modal.classList.add('active');

        // Reset sections with null checks
        const lineItemSection = document.getElementById('recipientFilesSection');
        const acceptanceSection = document.getElementById('acceptanceFilesSection');
        
        if (lineItemSection) lineItemSection.style.display = 'block';
        if (acceptanceSection) acceptanceSection.style.display = 'none';

        // Fetch files for this recipient
        fetchRecipientFiles(paymentEntryId, lineItemId, recipient);

        // Add tab click handlers
        setupRecipientFileTabs();

        // Close on outside click - with null check
        if (modal.onClickBound !== true) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeRecipientFilesModal();
                }
            });
            modal.onClickBound = true;
        }
    } catch (error) {
        console.error('Error in openRecipientFilesModal:', error);
        alert('An error occurred while opening the files modal');
    }
}

/**
 * Close recipient files modal
 */
function closeRecipientFilesModal() {
    const modal = document.getElementById('recipientFilesModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

/**
 * Setup tab switching
 */
function setupRecipientFileTabs() {
    const tabs = document.querySelectorAll('.recipient-file-tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Update active tab styling
            tabs.forEach(t => {
                t.style.background = 'white';
                t.style.color = '#718096';
                t.style.borderBottom = 'none';
            });
            this.style.background = '#edf2f7';
            this.style.color = '#2d3748';
            this.style.borderBottom = '3px solid #4299e1';

            // Show/hide sections
            document.getElementById('lineItemFilesSection').style.display = 
                tabName === 'line-item' ? 'block' : 'none';
            document.getElementById('acceptanceFilesSection').style.display = 
                tabName === 'acceptance' ? 'block' : 'none';
        });
    });
}

/**
 * Fetch files for specific recipient
 * GET data from two tables:
 * 1. tbl_payment_entry_line_items_detail (line item files)
 * 2. tbl_payment_acceptance_methods_line_items (acceptance method files)
 */
function fetchRecipientFiles(paymentEntryId, lineItemId, recipient) {
    try {
        const lineItemContainer = document.getElementById('lineItemFilesContainer');
        const acceptanceContainer = document.getElementById('acceptanceFilesContainer');
        const noFilesMsg = document.getElementById('recipientNoFilesMessage');

        if (!lineItemContainer || !acceptanceContainer) {
            console.error('File containers not found in DOM');
            return;
        }

        // Use recipient.id as the actual recipient identifier
        const recipientId = recipient.id || lineItemId;

        // Fetch line item files - use recipient_id parameter
        fetch(`get_recipient_line_item_files.php?payment_entry_id=${paymentEntryId}&recipient_id=${recipientId}&recipient_name=${encodeURIComponent(recipient.name)}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success && data.line_item_files && data.line_item_files.length > 0) {
                    displayRecipientLineItemFiles(data.line_item_files);
                    const counter = document.getElementById('lineItemFileCount');
                    if (counter) counter.textContent = data.line_item_files.length;
                } else {
                    lineItemContainer.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #a0aec0;">
                            <i class="fas fa-inbox" style="font-size: 1.5em; margin-bottom: 8px;"></i>
                            <p style="margin: 0;">No line item files attached</p>
                        </div>
                    `;
                    const counter = document.getElementById('lineItemFileCount');
                    if (counter) counter.textContent = '0';
                }

                // Fetch acceptance method files - use recipient_id parameter
                return fetch(`get_recipient_acceptance_files.php?payment_entry_id=${paymentEntryId}&recipient_id=${recipientId}&recipient_name=${encodeURIComponent(recipient.name)}`);
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success && data.acceptance_files && data.acceptance_files.length > 0) {
                    displayRecipientAcceptanceFiles(data.acceptance_files);
                    const counter = document.getElementById('acceptanceFileCount');
                    if (counter) counter.textContent = data.acceptance_files.length;
                    if (noFilesMsg) noFilesMsg.style.display = 'none';
                } else {
                    acceptanceContainer.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #a0aec0;">
                            <i class="fas fa-inbox" style="font-size: 1.5em; margin-bottom: 8px;"></i>
                            <p style="margin: 0;">No acceptance method files attached</p>
                        </div>
                    `;
                    const counter = document.getElementById('acceptanceFileCount');
                    if (counter) counter.textContent = '0';
                }

                // Calculate total size
                calculateRecipientTotalSize();
            })
            .catch(error => {
                console.error('Error fetching recipient files:', error);
                if (lineItemContainer) {
                    lineItemContainer.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #e53e3e;">
                            <i class="fas fa-exclamation-circle" style="font-size: 1.5em; margin-bottom: 8px;"></i>
                            <p>Error loading files: ${error.message}</p>
                        </div>
                    `;
                }
            });
    } catch (error) {
        console.error('Error in fetchRecipientFiles:', error);
    }
}

/**
 * Display line item files
 * Data from: tbl_payment_entry_line_items_detail
 * Columns: line_item_media_upload_path, line_item_media_original_filename, etc
 */
function displayRecipientLineItemFiles(files) {
    const container = document.getElementById('lineItemFilesContainer');
    
    if (!container || !files || files.length === 0) {
        return;
    }

    let html = '';

    files.forEach(file => {
        try {
            const icon = getRecipientFileIcon(file.line_item_media_mime_type);
            const size = formatRecipientFileSize(file.line_item_media_filesize_bytes);
            const fileName = file.line_item_media_original_filename || 'Unknown File';

            html += `
                <div class="recipient-file-item" data-file-id="${file.line_item_entry_id}">
                    <div class="recipient-file-info">
                        <div class="recipient-file-icon">${icon}</div>
                        <div class="recipient-file-details">
                            <div class="recipient-file-name" title="${fileName}">${fileName}</div>
                            <div class="recipient-file-meta">
                                <span class="recipient-file-type-badge">Line Item</span>
                                <span>${size}</span>
                            </div>
                        </div>
                    </div>
                    <div class="recipient-file-actions">
                        <button class="recipient-file-action-btn" onclick="downloadRecipientFile(${file.line_item_entry_id}, 'line_item', '${fileName}')">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="recipient-file-action-btn" onclick="previewRecipientFile(${file.line_item_entry_id}, 'line_item', '${file.line_item_media_mime_type}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error rendering line item file:', error, file);
        }
    });

    container.innerHTML = html;
}

/**
 * Display acceptance method files
 * Data from: tbl_payment_acceptance_methods_line_items
 * Columns: method_supporting_media_path, method_supporting_media_filename, etc
 */
function displayRecipientAcceptanceFiles(files) {
    const container = document.getElementById('acceptanceFilesContainer');
    
    if (!container || !files || files.length === 0) {
        return;
    }

    let html = '';

    files.forEach(file => {
        try {
            const icon = getRecipientFileIcon(file.method_supporting_media_type);
            const size = formatRecipientFileSize(file.method_supporting_media_size);
            const fileName = file.method_supporting_media_filename || 'Unknown File';
            const methodType = file.method_type_category || 'Unknown';

            html += `
                <div class="recipient-file-item" data-file-id="${file.line_item_acceptance_method_id}">
                    <div class="recipient-file-info">
                        <div class="recipient-file-icon">${icon}</div>
                        <div class="recipient-file-details">
                            <div class="recipient-file-name" title="${fileName}">${fileName}</div>
                            <div class="recipient-file-meta">
                                <span class="recipient-file-type-badge">${methodType}</span>
                                <span>${size}</span>
                            </div>
                        </div>
                    </div>
                    <div class="recipient-file-actions">
                        <button class="recipient-file-action-btn" onclick="downloadRecipientFile(${file.line_item_acceptance_method_id}, 'acceptance', '${fileName}')">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="recipient-file-action-btn" onclick="previewRecipientFile(${file.line_item_acceptance_method_id}, 'acceptance', '${file.method_supporting_media_type}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error rendering acceptance method file:', error, file);
        }
    });

    container.innerHTML = html;
}

/**
 * Calculate total file size for recipient
 */
function calculateRecipientTotalSize() {
    try {
        let totalSize = 0;

        // Get line item files size
        const lineItemItems = document.querySelectorAll('#lineItemFilesContainer .recipient-file-meta span:last-child');
        if (lineItemItems) {
            lineItemItems.forEach(item => {
                const sizeText = item.textContent;
                totalSize += parseRecipientFileSize(sizeText);
            });
        }

        // Get acceptance files size
        const acceptanceItems = document.querySelectorAll('#acceptanceFilesContainer .recipient-file-meta span:last-child');
        if (acceptanceItems) {
            acceptanceItems.forEach(item => {
                const sizeText = item.textContent;
                totalSize += parseRecipientFileSize(sizeText);
            });
        }

        const totalElement = document.getElementById('recipientTotalSize');
        if (totalElement) {
            totalElement.textContent = formatRecipientFileSize(totalSize);
        }
    } catch (error) {
        console.error('Error calculating total size:', error);
    }
}

/**
 * Get file icon based on MIME type
 */
function getRecipientFileIcon(mimeType) {
    const type = (mimeType || '').toLowerCase();
    
    if (type.includes('pdf')) return '<i class="fas fa-file-pdf" style="color: #e53e3e;"></i>';
    if (type.includes('word') || type.includes('document')) return '<i class="fas fa-file-word" style="color: #2b6cb0;"></i>';
    if (type.includes('image')) return '<i class="fas fa-file-image" style="color: #f6ad55;"></i>';
    if (type.includes('video')) return '<i class="fas fa-file-video" style="color: #c53030;"></i>';
    if (type.includes('excel') || type.includes('spreadsheet')) return '<i class="fas fa-file-excel" style="color: #107c10;"></i>';
    if (type.includes('zip') || type.includes('archive')) return '<i class="fas fa-file-archive" style="color: #744210;"></i>';
    
    return '<i class="fas fa-file" style="color: #718096;"></i>';
}

/**
 * Format file size
 */
function formatRecipientFileSize(bytes) {
    bytes = parseInt(bytes) || 0;
    if (bytes === 0) return '0 B';
    
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Parse file size string back to bytes (for total calculation)
 */
function parseRecipientFileSize(sizeStr) {
    const parts = sizeStr.trim().split(' ');
    if (parts.length !== 2) return 0;
    
    const value = parseFloat(parts[0]);
    const unit = parts[1].toUpperCase();
    const multipliers = { 'B': 1, 'KB': 1024, 'MB': 1024*1024, 'GB': 1024*1024*1024 };
    
    return value * (multipliers[unit] || 1);
}

/**
 * Normalize file path to proper URL
 */
function normalizeFilePath(filePath) {
    if (!filePath) return '';
    
    // Remove leading/trailing slashes
    let path = filePath.trim();
    
    // If already a full URL, return as is
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    
    // If it's just a filename, prefix with uploads directory
    if (!path.includes('/')) {
        path = 'uploads/entry_media/' + path;
    }
    
    // Remove leading slash if present
    if (path.startsWith('/')) {
        path = path.substring(1);
    }
    
    // Ensure we have the connect directory prefix
    const baseUrl = window.location.origin + '/connect/';
    
    return baseUrl + path;
}

/**
 * Download recipient file using handler script
 * @param fileId - ID of the file (line_item_entry_id or line_item_acceptance_method_id)
 * @param fileType - Type of file ('line_item' or 'acceptance')
 * @param fileName - Original filename for download
 */
function downloadRecipientFile(fileId, fileType, fileName) {
    try {
        const link = document.createElement('a');
        link.href = `download_recipient_file.php?file_id=${encodeURIComponent(fileId)}&file_type=${encodeURIComponent(fileType)}`;
        link.download = fileName || 'download';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (error) {
        console.error('Error downloading file:', error);
        alert('Error downloading file: ' + error.message);
    }
}

/**
 * Preview recipient file using handler script
 * @param fileId - ID of the file (line_item_entry_id or line_item_acceptance_method_id)
 * @param fileType - Type of file ('line_item' or 'acceptance')
 * @param mimeType - MIME type of the file
 */
function previewRecipientFile(fileId, fileType, mimeType) {
    try {
        const previewMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        
        if (previewMimes.includes(mimeType)) {
            const url = `preview_recipient_file.php?file_id=${encodeURIComponent(fileId)}&file_type=${encodeURIComponent(fileType)}`;
            window.open(url, '_blank');
        } else {
            alert('Preview not available for this file type (' + mimeType + '). Please download to view.');
        }
    } catch (error) {
        console.error('Error previewing file:', error);
        alert('Error previewing file: ' + error.message);
    }
}
</script>
