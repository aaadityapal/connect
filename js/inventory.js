/**
 * Inventory Management JavaScript
 * This file handles interactive functionality for the inventory management page
 */

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    if (typeof $ !== 'undefined' && typeof $.fn.tooltip !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Handle file input change to show selected filename
    const fileInputs = document.querySelectorAll('.custom-file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const numFiles = this.files.length;
            const label = this.nextElementSibling;
            
            if (numFiles > 0) {
                label.textContent = numFiles === 1 
                    ? this.files[0].name 
                    : numFiles + ' files selected';
            } else {
                label.textContent = 'Choose files';
            }
        });
    });
    
    // Handle add inventory form submission
    const addInventoryForm = document.getElementById('addInventoryForm');
    if (addInventoryForm) {
        addInventoryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate form
            if (!validateInventoryForm(this)) {
                return;
            }
            
            // Submit form using AJAX
            const formData = new FormData(this);
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            fetch('process_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                if (data.success) {
                    // Show success alert
                    showAlert('success', 'Inventory item added successfully!');
                    
                    // Close modal and reload page after delay
                    $('#addInventoryModal').modal('hide');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Show error alert
                    showAlert('danger', data.message || 'Error adding inventory item.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showAlert('danger', 'An unexpected error occurred. Please try again.');
            });
        });
    }
    
    // Handle edit inventory form submission
    const editInventoryForm = document.getElementById('editInventoryForm');
    if (editInventoryForm) {
        editInventoryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate form
            if (!validateInventoryForm(this)) {
                return;
            }
            
            // Submit form using AJAX
            const formData = new FormData(this);
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            fetch('process_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                if (data.success) {
                    // Show success alert
                    showAlert('success', 'Inventory item updated successfully!');
                    
                    // Close modal and reload page after delay
                    $('#editInventoryModal').modal('hide');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Show error alert
                    showAlert('danger', data.message || 'Error updating inventory item.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showAlert('danger', 'An unexpected error occurred. Please try again.');
            });
        });
    }
    
    // Handle delete item confirmation
    const deleteForm = document.getElementById('deleteInventoryForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Submit form using AJAX
            const formData = new FormData(this);
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
            
            fetch('process_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                if (data.success) {
                    // Show success alert
                    showAlert('success', 'Inventory item deleted successfully!');
                    
                    // Close modal and reload page after delay
                    $('#deleteConfirmModal').modal('hide');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Show error alert
                    showAlert('danger', data.message || 'Error deleting inventory item.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showAlert('danger', 'An unexpected error occurred. Please try again.');
            });
        });
    }
    
    // Set up edit item clicks to load data
    setupEditItemButtons();
    
    // Set up delete item clicks to load data
    setupDeleteItemButtons();
    
    // Set up view details clicks
    setupViewDetailsButtons();
});

/**
 * Validate the inventory form
 */
function validateInventoryForm(form) {
    // Check required fields
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
            
            // Add or update error message
            let errorDiv = field.nextElementSibling;
            if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                field.parentNode.insertBefore(errorDiv, field.nextSibling);
            }
            errorDiv.textContent = 'This field is required.';
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    // Check file size and count
    const fileInput = form.querySelector('input[type="file"]');
    if (fileInput && fileInput.files.length > 0) {
        if (fileInput.files.length > 5) {
            isValid = false;
            fileInput.classList.add('is-invalid');
            
            // Add or update error message
            let errorDiv = fileInput.nextElementSibling.nextElementSibling;
            if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                fileInput.parentNode.insertBefore(errorDiv, fileInput.nextElementSibling.nextSibling);
            }
            errorDiv.textContent = 'Maximum 5 files allowed.';
        } else {
            // Check each file size
            for (let i = 0; i < fileInput.files.length; i++) {
                const file = fileInput.files[i];
                if (file.size > 5 * 1024 * 1024) { // 5MB
                    isValid = false;
                    fileInput.classList.add('is-invalid');
                    
                    // Add or update error message
                    let errorDiv = fileInput.nextElementSibling.nextElementSibling;
                    if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        fileInput.parentNode.insertBefore(errorDiv, fileInput.nextElementSibling.nextSibling);
                    }
                    errorDiv.textContent = `File "${file.name}" exceeds 5MB limit.`;
                    break;
                }
            }
        }
    }
    
    return isValid;
}

/**
 * Show an alert message
 */
function showAlert(type, message) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show inventory-alert`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    // Add to page
    document.querySelector('.container-fluid').prepend(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => {
            alertDiv.remove();
        }, 150);
    }, 5000);
}

/**
 * Set up edit item buttons to load data
 */
function setupEditItemButtons() {
    const editButtons = document.querySelectorAll('.edit-item');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const inventoryId = this.getAttribute('data-inventory-id');
            
            // Show loading in modal
            const modalBody = document.querySelector('#editInventoryModal .modal-body');
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Loading inventory data...</p>
                </div>
            `;
            
            // Fetch item data
            fetch(`get_inventory_item.php?id=${inventoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate form
                        populateEditForm(data.item, data.media);
                    } else {
                        modalBody.innerHTML = `
                            <div class="alert alert-danger">
                                ${data.message || 'Error loading inventory data.'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            An unexpected error occurred. Please try again.
                        </div>
                    `;
                });
        });
    });
}

/**
 * Populate the edit form with item data
 */
function populateEditForm(item, media) {
    // Get the modal body
    const modalBody = document.querySelector('#editInventoryModal .modal-body');
    
    // Create form HTML
    const formHtml = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="edit_event_id" class="form-label">Site/Event <span class="text-danger">*</span></label>
                <select name="event_id" id="edit_event_id" class="form-control" required>
                    ${generateEventOptions(item.event_id)}
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="edit_inventory_type" class="form-label">Inventory Type <span class="text-danger">*</span></label>
                <select name="inventory_type" id="edit_inventory_type" class="form-control" required>
                    <option value="received" ${item.inventory_type === 'received' ? 'selected' : ''}>Received</option>
                    <option value="consumed" ${item.inventory_type === 'consumed' ? 'selected' : ''}>Consumed</option>
                    <option value="other" ${item.inventory_type === 'other' ? 'selected' : ''}>Other</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="edit_material_type" class="form-label">Material Type <span class="text-danger">*</span></label>
                <input type="text" name="material_type" id="edit_material_type" class="form-control" value="${item.material_type}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="edit_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="quantity" id="edit_quantity" class="form-control" step="0.01" min="0" value="${item.quantity}" required>
                    <div class="input-group-append">
                        <input type="text" name="unit" id="edit_unit" class="form-control" placeholder="Unit (kg, pcs, etc)" value="${item.unit}" required>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 mb-3">
                <label for="edit_remarks" class="form-label">Remarks</label>
                <textarea name="remarks" id="edit_remarks" class="form-control" rows="3">${item.remarks || ''}</textarea>
            </div>
        </div>
        <div class="row">
            <div class="col-12 mb-3">
                <label class="form-label">Current Media Files</label>
                <div class="current-media-files">
                    ${media.length > 0 ? generateMediaPreview(media) : '<p class="text-muted">No media files attached</p>'}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 mb-3">
                <label class="form-label">Upload Additional Images/Bills</label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" name="media_files[]" id="edit_mediaFiles" multiple accept="image/*,.pdf">
                    <label class="custom-file-label" for="edit_mediaFiles">Choose files</label>
                </div>
                <small class="form-text text-muted">You can upload multiple images or PDF files. Maximum 5 files, each max 5MB.</small>
                <div class="upload-preview mt-3" id="edit_uploadPreview"></div>
            </div>
        </div>
        <input type="hidden" name="inventory_id" value="${item.inventory_id}">
        <input type="hidden" name="action" value="update">
    `;
    
    // Set HTML to modal body
    modalBody.innerHTML = formHtml;
    
    // Reinitialize file input change event
    const fileInput = document.getElementById('edit_mediaFiles');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const files = this.files;
            const previewDiv = document.getElementById('edit_uploadPreview');
            previewDiv.innerHTML = '';
            
            if (files.length > 0) {
                const fileLabel = this.nextElementSibling;
                fileLabel.textContent = files.length + ' files selected';
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const reader = new FileReader();
                    
                    const fileDiv = document.createElement('div');
                    fileDiv.className = 'upload-preview-item';
                    
                    reader.onload = function(e) {
                        if (file.type.startsWith('image/')) {
                            fileDiv.innerHTML = `
                                <img src="${e.target.result}" class="img-thumbnail">
                                <p class="file-name">${file.name}</p>
                            `;
                        } else {
                            fileDiv.innerHTML = `
                                <div class="file-thumbnail"><i class="fas fa-file-pdf"></i></div>
                                <p class="file-name">${file.name}</p>
                            `;
                        }
                    };
                    
                    reader.readAsDataURL(file);
                    previewDiv.appendChild(fileDiv);
                }
            } else {
                const fileLabel = this.nextElementSibling;
                fileLabel.textContent = 'Choose files';
            }
        });
    }
}

/**
 * Generate options for event select
 */
function generateEventOptions(selectedEventId) {
    // This needs to be populated with data from the server
    // For now, just return a placeholder
    return `<option value="${selectedEventId}" selected>Current Event</option>`;
}

/**
 * Generate HTML for media preview
 */
function generateMediaPreview(media) {
    if (!media || media.length === 0) {
        return '<p class="text-muted">No media files attached</p>';
    }
    
    let html = '<div class="media-files-grid">';
    
    media.forEach(file => {
        const isImage = file.media_type === 'photo' || (file.file_name.match(/\.(jpeg|jpg|gif|png)$/i));
        const isPdf = file.media_type === 'bill' || file.file_name.match(/\.pdf$/i);
        
        html += `
            <div class="media-file-item">
                <div class="media-thumbnail">
                    ${isImage 
                        ? `<img src="${file.file_path}" alt="${file.file_name}" class="img-thumbnail">` 
                        : `<div class="file-icon"><i class="fas fa-${isPdf ? 'file-pdf' : 'file'} fa-2x"></i></div>`
                    }
                </div>
                <div class="media-file-info">
                    <p class="media-file-name">${file.file_name}</p>
                    <div class="media-actions">
                        <a href="${file.file_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-media" data-media-id="${file.media_id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    return html;
}

/**
 * Set up delete item buttons
 */
function setupDeleteItemButtons() {
    const deleteButtons = document.querySelectorAll('.delete-item');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const inventoryId = this.getAttribute('data-inventory-id');
            const deleteModal = document.getElementById('deleteConfirmModal');
            
            if (deleteModal) {
                const form = deleteModal.querySelector('form');
                const inventoryIdInput = form.querySelector('input[name="inventory_id"]');
                
                if (inventoryIdInput) {
                    inventoryIdInput.value = inventoryId;
                }
            }
        });
    });
}

/**
 * Set up view details buttons
 */
function setupViewDetailsButtons() {
    const viewButtons = document.querySelectorAll('.view-details');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const inventoryId = this.getAttribute('data-inventory-id');
            
            // Show loading in modal
            const modalBody = document.querySelector('#viewItemModal .modal-body');
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Loading inventory details...</p>
                </div>
            `;
            
            // Fetch item data
            fetch(`get_inventory_item.php?id=${inventoryId}&view=true`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show details
                        displayItemDetails(data.item, data.media);
                    } else {
                        modalBody.innerHTML = `
                            <div class="alert alert-danger">
                                ${data.message || 'Error loading inventory details.'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            An unexpected error occurred. Please try again.
                        </div>
                    `;
                });
        });
    });
}

/**
 * Display item details in the view modal
 */
function displayItemDetails(item, media) {
    // Get the modal body
    const modalBody = document.querySelector('#viewItemModal .modal-body');
    
    // Format details HTML
    const detailsHtml = `
        <div class="item-details">
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Site/Event:</label>
                        <div class="detail-value">${item.site_name || 'Unknown'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Date:</label>
                        <div class="detail-value">${item.event_date ? new Date(item.event_date).toLocaleDateString() : 'Unknown'}</div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Material Type:</label>
                        <div class="detail-value">${item.material_type || 'Not specified'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Inventory Type:</label>
                        <div class="detail-value">
                            <span class="badge badge-${
                                item.inventory_type === 'received' ? 'success' : 
                                item.inventory_type === 'consumed' ? 'warning' : 'info'
                            }">${item.inventory_type.charAt(0).toUpperCase() + item.inventory_type.slice(1)}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Quantity:</label>
                        <div class="detail-value">${item.quantity} ${item.unit}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Date Added:</label>
                        <div class="detail-value">${new Date(item.created_at).toLocaleString()}</div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <div class="detail-group">
                        <label>Remarks:</label>
                        <div class="detail-value">${item.remarks || 'No remarks'}</div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <label class="section-label">Media Files</label>
                    <div class="media-gallery">
                        ${generateDetailMediaGallery(media)}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Set HTML to modal body
    modalBody.innerHTML = detailsHtml;
    
    // Initialize lightbox for images
    if (typeof lightbox !== 'undefined') {
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': 'Media %1 of %2'
        });
    }
}

/**
 * Generate HTML for media gallery in item details
 */
function generateDetailMediaGallery(media) {
    if (!media || media.length === 0) {
        return '<p class="text-muted">No media files attached</p>';
    }
    
    let html = '<div class="row">';
    
    media.forEach(file => {
        const isImage = file.media_type === 'photo' || (file.file_name.match(/\.(jpeg|jpg|gif|png)$/i));
        const isPdf = file.media_type === 'bill' || file.file_name.match(/\.pdf$/i);
        
        html += `
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="media-card">
                    <div class="media-img">
                        ${isImage 
                            ? `<a href="${file.file_path}" data-lightbox="inventory-gallery" data-title="${file.file_name}">
                                <img src="${file.file_path}" alt="${file.file_name}" class="img-fluid">
                               </a>` 
                            : `<a href="${file.file_path}" target="_blank" class="file-link">
                                <div class="file-icon-lg">
                                    <i class="fas fa-${isPdf ? 'file-pdf' : 'file'} fa-3x"></i>
                                </div>
                               </a>`
                        }
                        <span class="media-badge ${file.media_type === 'bill' ? 'badge-danger' : 'badge-primary'}">${file.media_type}</span>
                    </div>
                    <div class="media-info">
                        <div class="media-name">${file.file_name}</div>
                        <a href="${file.file_path}" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    return html;
} 