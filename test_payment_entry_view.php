<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Payment Entry View Modal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Test: Payment Entry View Modal</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="paymentId" class="form-label">Payment Entry ID</label>
                            <input type="number" class="form-control" id="paymentId" placeholder="Enter payment entry ID" value="26">
                        </div>
                        <button class="btn btn-primary" onclick="testViewPaymentEntry()">
                            <i class="fas fa-eye me-1"></i>
                            View Payment Entry
                        </button>
                        <button class="btn btn-secondary ms-2" onclick="testApiDirectly()">
                            <i class="fas fa-code me-1"></i>
                            Test API Directly
                        </button>
                        <hr>
                        <div id="results"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the payment entry view modal -->
    <?php include './includes/view_payment_entry_modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Helper function to get appropriate file icon based on file type
        function getFileIconClass(fileType) {
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
            
            if (modal && img && title) {
                img.src = imagePath;
                img.alt = fileName;
                title.textContent = fileName;
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        // Function to close image preview
        function closeImagePreview() {
            const modal = document.getElementById('imagePreviewModal');
            if (modal) {
                modal.style.display = 'none';
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

        function testApiDirectly() {
            const paymentId = document.getElementById('paymentId').value;
            const resultsDiv = document.getElementById('results');
            
            if (!paymentId) {
                resultsDiv.innerHTML = '<div class="alert alert-warning">Please enter a payment entry ID.</div>';
                return;
            }
            
            resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Testing API...</p></div>';
            
            fetch(`./api/get_payment_entry_details.php?id=${paymentId}&_cache_bust=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    console.log('API Response:', data);
                    
                    let html = '<h6 class="mb-3">API Response:</h6>';
                    html += '<pre class="bg-light p-3 border rounded" style="max-height: 400px; overflow-y: auto;">';
                    html += JSON.stringify(data, null, 2);
                    html += '</pre>';
                    
                    if (data.status === 'success') {
                        html += '<div class="alert alert-success mt-3">';
                        html += '<i class="fas fa-check-circle me-2"></i>';
                        html += `Successfully loaded payment entry with ${data.recipients.length} recipients, ${data.summary.total_splits} splits, and ${data.summary.total_documents} documents.`;
                        html += `<br><strong>Payment Via:</strong> ${data.payment_entry.display_payment_done_via || 'Not specified'}`;
                        html += '</div>';
                    } else {
                        html += `<div class="alert alert-danger mt-3">Error: ${data.message}</div>`;
                    }
                    
                    resultsDiv.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsDiv.innerHTML = `<div class="alert alert-danger">Network error: ${error.message}</div>`;
                });
        }

        function testViewPaymentEntry() {
            const paymentId = document.getElementById('paymentId').value;
            
            if (!paymentId) {
                alert('Please enter a payment entry ID.');
                return;
            }
            
            // Call the same function that would be called from the dashboard
            viewEntry(paymentId);
        }
        
        // Copy the exact viewEntry function from the dashboard
        function viewEntry(id) {
            console.log('Viewing payment entry:', id);
            
            // Show the view payment entry modal
            const modal = new bootstrap.Modal(document.getElementById('viewPaymentEntryModal'));
            modal.show();
            
            // Show loading state
            document.getElementById('paymentEntryDetailsLoader').style.display = 'block';
            document.getElementById('paymentEntryDetailsContent').style.display = 'none';
            document.getElementById('paymentEntryDetailsError').style.display = 'none';
            
            // Update modal title with payment ID
            document.getElementById('viewPaymentEntryModalLabel').innerHTML = `
                Payment Entry Details - ID: ${id}
            `;
            
            // Fetch payment entry details from API (with cache busting)
            fetch(`./api/get_payment_entry_details.php?id=${id}&_cache_bust=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading state
                    document.getElementById('paymentEntryDetailsLoader').style.display = 'none';
                    
                    if (data.status === 'success') {
                        // Populate payment entry details
                        populatePaymentEntryDetails(data.payment_entry, data.recipients, data.summary);
                        document.getElementById('paymentEntryDetailsContent').style.display = 'block';
                        
                        // Store payment ID for edit functionality
                        document.getElementById('editPaymentEntryFromView').setAttribute('data-payment-id', id);
                    } else {
                        // Show error message
                        document.getElementById('paymentEntryErrorMessage').textContent = data.message || 'Failed to load payment entry details';
                        document.getElementById('paymentEntryDetailsError').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching payment entry details:', error);
                    document.getElementById('paymentEntryDetailsLoader').style.display = 'none';
                    document.getElementById('paymentEntryErrorMessage').textContent = 'Network error. Please try again later.';
                    document.getElementById('paymentEntryDetailsError').style.display = 'block';
                });
        }
        
        // Copy the populatePaymentEntryDetails function from the dashboard
        function populatePaymentEntryDetails(paymentEntry, recipients, summary) {
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
            
            // Main Payment Entry Information
            safeSetText('viewPaymentId', paymentEntry.payment_id);
            safeSetText('viewProjectTitle', paymentEntry.project_title || 'Project #' + paymentEntry.project_id);
            safeSetText('viewProjectType', paymentEntry.display_project_type);
            safeSetText('viewPaymentAmount', paymentEntry.formatted_payment_amount);
            safeSetText('viewPaymentDate', paymentEntry.formatted_payment_date);
            safeSetText('viewPaymentMode', paymentEntry.display_payment_mode);
            safeSetText('viewPaymentVia', paymentEntry.display_payment_done_via);
            
            // Handle payment proof image and clip icon
            const paymentProofClip = document.getElementById('paymentProofClip');
            if (paymentProofClip && paymentEntry.payment_proof_image) {
                // Set the proof data
                paymentProofClip.dataset.proofPath = paymentEntry.payment_proof_image;
                paymentProofClip.dataset.proofName = 'Payment Proof';
                
                // Show the clip icon
                paymentProofClip.style.display = 'inline-flex';
            } else if (paymentProofClip) {
                // Hide the clip icon if no proof image
                paymentProofClip.style.display = 'none';
            }
            
            // System Information
            safeSetText('viewCreatedBy', paymentEntry.created_by_username || 'System');
            safeSetText('viewUpdatedBy', paymentEntry.updated_by_username || 'System');
            safeSetText('viewCreatedAt', paymentEntry.formatted_created_at);
            safeSetText('viewUpdatedAt', paymentEntry.formatted_updated_at);
            
            // Summary Statistics
            safeSetText('recipientCount', summary.total_recipients);
            safeSetText('summaryRecipients', summary.total_recipients);
            safeSetText('summarySplits', summary.total_splits);
            safeSetText('summaryDocuments', summary.total_documents);
            safeSetText('summaryAmount', summary.formatted_total_recipient_amount);
            
            // Show/hide documents section based on available documents
            const documentsSection = document.getElementById('documentsSection');
            const documentsList = document.getElementById('documentsList');
            const documentsCount = document.getElementById('documentsCount');
            
            if (summary.total_documents > 0) {
                // Collect all documents from all recipients
                let allDocuments = [];
                recipients.forEach(recipient => {
                    if (recipient.documents && recipient.documents.length > 0) {
                        recipient.documents.forEach(doc => {
                            allDocuments.push({
                                ...doc,
                                recipient_name: recipient.name
                            });
                        });
                    }
                });
                
                if (allDocuments.length > 0) {
                    documentsSection.style.display = 'block';
                    documentsCount.textContent = allDocuments.length;
                    
                    let documentsHTML = '';
                    allDocuments.forEach(doc => {
                        const isImage = doc.file_type.toLowerCase().includes('image');
                        const fileIcon = getFileIconClass(doc.file_type);
                        const escapedFileName = escapeHtml(doc.file_name);
                        const escapedFilePath = doc.file_path.replace(/'/g, "\'");
                        
                        documentsHTML += `
                            <div class="document-card">
                                <div class="document-preview-container">
                                    ${isImage ? 
                                        `<img src="${doc.file_path}" class="document-image" alt="${escapedFileName}" onerror="this.parentElement.innerHTML='<div class=&quot;document-icon-fallback&quot;><i class=&quot;fas fa-image fs-1 text-muted&quot;></i></div>';" onclick="openImagePreview('${escapedFilePath}', '${escapedFileName}')" style="cursor: pointer;" title="Click to view full size">` :
                                        `<div class="document-icon-container">
                                            <i class="fas ${fileIcon} fs-1 text-info"></i>
                                            <div class="file-extension">${doc.display_file_type}</div>
                                        </div>`
                                    }
                                </div>
                                <div class="document-info">
                                    <div class="document-name" title="${escapedFileName}">${escapedFileName}</div>
                                    <div class="document-meta">
                                        <span class="file-size">${doc.formatted_file_size}</span>
                                        <span class="upload-date">${doc.formatted_upload_date}</span>
                                        <div class="text-muted small mt-1">From: ${escapeHtml(doc.recipient_name)}</div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary download-btn" onclick="downloadDocument('${escapedFilePath}', '${escapedFileName}')" title="Download document">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    documentsList.innerHTML = documentsHTML;
                } else {
                    documentsSection.style.display = 'none';
                }
            } else {
                documentsSection.style.display = 'none';
            }
            
            // Populate Recipients List
            const recipientsList = document.getElementById('recipientsList');
            if (recipientsList) {
                if (recipients.length === 0) {
                    recipientsList.innerHTML = '<div class=\"p-4 text-center text-muted\">No recipients found for this payment entry.</div>';
                } else {
                    let recipientsHTML = '';
                    
                    recipients.forEach((recipient, index) => {
                        recipientsHTML += `
                            <tr class="pmt-table-row">
                                <td class="pmt-table-cell">
                                    <div class="pmt-recipient-details">
                                        <!-- 1. Vendor/Labour Name -->
                                        <div class="pmt-recipient-name">
                                            <i class="fas fa-user pmt-name-icon"></i>
                                            ${escapeHtml(recipient.name)}
                                        </div>
                                        
                                        <!-- 2. Vendor/Labour Type -->
                                        <div class="pmt-type-tags">
                                            <span class="pmt-category-tag">${escapeHtml(recipient.display_category)}</span>
                                            <span class="pmt-type-tag">${escapeHtml(recipient.display_type)}</span>
                                            ${recipient.custom_type ? '<span class="pmt-custom-tag">' + escapeHtml(recipient.custom_type) + '</span>' : ''}
                                        </div>
                                        
                                        <!-- 3. Payment For -->
                                        <div class="pmt-payment-purpose">
                                            <span class="pmt-purpose-label">Payment for:</span>
                                            <span class="pmt-purpose-text">${escapeHtml(recipient.payment_for || 'Not specified')}</span>
                                        </div>
                                        
                                        <!-- 4. Split Payments (if applicable) -->
                                        ${recipient.splits.length > 0 ? `
                                            <div class="pmt-splits-section">
                                                <div class="pmt-splits-header">
                                                    <i class="fas fa-money-bill-wave pmt-splits-icon"></i>
                                                    <span class="pmt-splits-title">Payment Splits (${recipient.splits.length})</span>
                                                </div>
                                                <div class="pmt-splits-list">
                                                    ${recipient.splits.map(split => `
                                                        <div class="pmt-split-item">
                                                            <div class="pmt-split-info">
                                                                <span class="pmt-split-mode">${split.display_payment_mode}</span>
                                                                <span class="pmt-split-amount">${split.formatted_amount}</span>
                                                                <span class="pmt-split-date">${split.formatted_date}</span>
                                                                ${split.proof_file ? `<span class="pmt-split-proof" onclick="showSplitProof('${split.proof_file}', 'Split Payment Proof')"><i class="fas fa-paperclip"></i> Proof attached</span>` : ''}
                                                            </div>
                                                        </div>
                                                    `).join('')}
                                                </div>
                                            </div>
                                        ` : ''}
                                        
                                        <!-- 5. Total Amount -->
                                        <div class="pmt-total-amount">
                                            <span class="pmt-amount-label">Total Amount:</span>
                                            <span class="pmt-amount-value">${recipient.formatted_amount}</span>
                                        </div>
                                        
                                        <!-- 6. Date and Time -->
                                        <div class="pmt-timestamp">
                                            <i class="fas fa-clock pmt-time-icon"></i>
                                            <span class="pmt-payment-mode">${escapeHtml(recipient.display_payment_mode)}</span>
                                            <span class="pmt-date-time">Added: ${recipient.formatted_date}</span>
                                        </div>
                                        
                                        <!-- Documents Section -->
                                        ${recipient.documents.length > 0 ? `
                                            <div class="pmt-documents-section">
                                                <div class="pmt-documents-header">
                                                    <i class="fas fa-folder-open pmt-docs-icon"></i>
                                                    <span class="pmt-docs-title">Documents (${recipient.documents.length})</span>
                                                </div>
                                                <div class="pmt-documents-grid">
                                                    ${recipient.documents.map(doc => {
                                                        const isImage = doc.file_type.toLowerCase().includes('image');
                                                        const fileIcon = getFileIconClass(doc.file_type);
                                                        const escapedFileName = escapeHtml(doc.file_name);
                                                        const escapedFilePath = doc.file_path.replace(/'/g, "\\'");
                                                        
                                                        return `
                                                            <div class="pmt-document-card">
                                                                <div class="pmt-doc-preview">
                                                                    ${isImage ? 
                                                                        `<img src="${doc.file_path}" class="pmt-doc-image" alt="${escapedFileName}" onclick="openImagePreview('${escapedFilePath}', '${escapedFileName}')" title="Click to view full size">` :
                                                                        `<div class="pmt-doc-icon-container">
                                                                            <i class="fas ${fileIcon} pmt-doc-icon"></i>
                                                                            <div class="pmt-file-ext">${doc.display_file_type}</div>
                                                                        </div>`
                                                                    }
                                                                </div>
                                                                <div class="pmt-doc-info">
                                                                    <div class="pmt-doc-name" title="${escapedFileName}">${escapedFileName}</div>
                                                                    <div class="pmt-doc-meta">
                                                                        <span class="pmt-file-size">${doc.formatted_file_size}</span>
                                                                        <span class="pmt-upload-date">${doc.formatted_upload_date}</span>
                                                                    </div>
                                                                    <button class="pmt-download-btn" onclick="downloadDocument('${escapedFilePath}', '${escapedFileName}')" title="Download document">
                                                                        <i class="fas fa-download"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        `;
                                                    }).join('')}
                                                </div>
                                            </div>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    
                    recipientsList.innerHTML = recipientsHTML;
                }
            }
            
            // Update modal title with payment entry name
            const modalTitle = document.getElementById('viewPaymentEntryModalLabel');
            if (modalTitle) {
                modalTitle.innerHTML = `
                    <i class=\"fas fa-eye me-2\"></i>
                    ${paymentEntry.project_title || 'Payment Entry #' + paymentEntry.payment_id}
                    <small class=\"ms-2 text-muted\">(${paymentEntry.formatted_payment_amount})</small>
                `;
            }
        }
        
        // Function to show split payment proof
        function showSplitProof(proofPath, proofName) {
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
        
        // Add event listeners when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const imageModal = document.getElementById('imagePreviewModal');
            if (imageModal) {
                imageModal.addEventListener('click', function(e) {
                    if (e.target === imageModal) {
                        closeImagePreview();
                    }
                });
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeImagePreview();
                }
            });
        });
    </script>
</body>
</html>