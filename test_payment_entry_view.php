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
                            <input type="number" class="form-control" id="paymentId" placeholder="Enter payment entry ID" value="1">
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
            
            fetch(`./api/get_payment_entry_details.php?id=${paymentId}`)
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
            
            // Fetch payment entry details from API
            fetch(`./api/get_payment_entry_details.php?id=${id}`)
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
                            <div class=\"recipient-item p-3\">
                                <div class=\"recipient-header p-2 mb-3\">
                                    <div class=\"row align-items-center\">
                                        <div class=\"col-md-8\">
                                            <h6 class=\"mb-1\">
                                                <i class=\"fas fa-user me-2\"></i>
                                                ${escapeHtml(recipient.name)}
                                            </h6>
                                            <div class=\"mb-2\">
                                                <span class=\"badge badge-category me-2\">${escapeHtml(recipient.display_category)}</span>
                                                <span class=\"badge badge-type\">${escapeHtml(recipient.display_type)}</span>
                                                ${recipient.custom_type ? '<span class=\"badge bg-secondary ms-2\">' + escapeHtml(recipient.custom_type) + '</span>' : ''}
                                            </div>
                                            <p class=\"mb-1 text-muted\">Payment for: ${escapeHtml(recipient.payment_for || 'Not specified')}</p>
                                        </div>
                                        <div class=\"col-md-4 text-end\">
                                            <div class=\"amount-highlight text-success\">${recipient.formatted_amount}</div>
                                            <small class=\"text-muted\">${escapeHtml(recipient.display_payment_mode)}</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Splits -->
                                ${recipient.splits.length > 0 ? `
                                    <div class=\"mb-3\">
                                        <h6 class=\"text-warning mb-2\">
                                            <i class=\"fas fa-divide me-2\"></i>
                                            Payment Splits (${recipient.splits.length})
                                        </h6>
                                        ${recipient.splits.map(split => `
                                            <div class=\"split-item p-2 mb-2\">
                                                <div class=\"row align-items-center\">
                                                    <div class=\"col-md-6\">
                                                        <strong>Split ID: ${split.split_id}</strong>
                                                        <br><small class=\"text-muted\">${split.display_payment_mode}</small>
                                                    </div>
                                                    <div class=\"col-md-3\">
                                                        <strong>${split.formatted_amount}</strong>
                                                    </div>
                                                    <div class=\"col-md-3 text-end\">
                                                        <small class=\"text-muted\">${split.formatted_date}</small>
                                                        ${split.proof_file ? '<br><small class=\"text-info\"><i class=\"fas fa-file\"></i> Proof attached</small>' : ''}
                                                    </div>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : ''}
                                
                                <!-- Documents -->
                                ${recipient.documents.length > 0 ? `
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-folder-open text-info me-2 fs-5"></i>
                                                <span class="fw-semibold text-info">Documents</span>
                                            </div>
                                            <span class="badge bg-info rounded-pill">${recipient.documents.length}</span>
                                        </div>
                                        <div class="documents-grid">
                                            ${recipient.documents.map(doc => {
                                                const isImage = doc.file_type.toLowerCase().includes('image');
                                                const fileIcon = getFileIconClass(doc.file_type);
                                                const escapedFileName = escapeHtml(doc.file_name);
                                                const escapedFilePath = doc.file_path.replace(/'/g, "\\\\'");
                                                
                                                return `
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
                                                            </div>
                                                            <button class="btn btn-sm btn-outline-primary download-btn" onclick="downloadDocument('${escapedFilePath}', '${escapedFileName}')" title="Download document">
                                                                <i class="fas fa-download"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                `;
                                            }).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <div class=\"text-end\">
                                    <small class=\"text-muted\">Added: ${recipient.formatted_date}</small>
                                </div>
                            </div>
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