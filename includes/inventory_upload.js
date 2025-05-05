// Inventory Media Upload Handler
function handleInventoryMediaUpload(inventoryId, fileInput, description = '') {
    const files = fileInput.files;
    if (!files.length) return;

    // Check if the selected files are valid
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // Check file size - limit to 1GB
        if (file.size > 1024 * 1024 * 1024) {
            alert(`File "${file.name}" exceeds the 1GB limit. Please select a smaller file.`);
            // Clear the file input
            fileInput.value = '';
            return;
        }
        
        // Validate file type
        const fileType = file.type;
        if (!fileType.startsWith('image/') && !fileType.startsWith('video/')) {
            alert(`File "${file.name}" is not a valid image or video.`);
            // Clear the file input
            fileInput.value = '';
            return;
        }
    }

    const formData = new FormData();
    formData.append('inventory_id', inventoryId);
    
    // Find the media container
    const mediaContainer = fileInput.closest('.media-container');
    if (!mediaContainer) {
        console.error('Media container not found');
        return;
    }
    
    // Get captions from the container
    const captionInputs = mediaContainer.querySelectorAll('input[name="inventory_media_caption[]"]');
    
    // Append each file and its caption
    for (let i = 0; i < files.length; i++) {
        formData.append('inventory_media_file[]', files[i]);
        if (captionInputs && captionInputs[i]) {
            formData.append('inventory_media_caption[]', captionInputs[i].value);
        } else {
            formData.append('inventory_media_caption[]', '');
        }
    }
    
    // Show loading state
    const previewContainer = mediaContainer.querySelector('.media-preview');
    if (previewContainer) {
        previewContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Uploading...</div>';
    }
    
    // Send AJAX request
    fetch('includes/process_inventory_media.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // First check if the response is valid
        if (!response.ok) {
            throw new Error(`Server responded with status: ${response.status}`);
        }
        
        // Try to parse the JSON
        return response.text().then(text => {
            try {
                if (!text) throw new Error('Empty response from server');
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        if (data && data.success) {
            // Handle successful upload
            if (previewContainer) {
                let previewHTML = '';
                if (data.results && Array.isArray(data.results)) {
                    data.results.forEach(result => {
                        if (result && result.success) {
                            if (result.media_type === 'image') {
                                previewHTML += `<div class="preview-item">
                                    <img src="${result.file_path}" class="img-fluid rounded mb-2" alt="Image" onerror="this.onerror=null; this.src='assets/img/image-placeholder.png';">
                                    <span class="badge bg-success">Image Uploaded</span>
                                </div>`;
                            } else if (result.media_type === 'video') {
                                previewHTML += `<div class="preview-item">
                                    <video src="${result.file_path}" class="img-fluid rounded mb-2" controls onerror="this.onerror=null; this.style.display='none'; this.parentNode.innerHTML += '<div class=\\'alert alert-warning\\'>Video preview not available</div>';">
                                        Your browser does not support the video tag.
                                    </video>
                                    <span class="badge bg-success">Video Uploaded</span>
                                </div>`;
                            }
                        }
                    });
                }
                
                if (previewHTML) {
                    previewContainer.innerHTML = previewHTML;
                    previewContainer.classList.remove('d-none');
                } else {
                    previewContainer.innerHTML = '<div class="alert alert-info">No preview available</div>';
                }
            }
            
            // Clear file input for next upload
            fileInput.value = '';
            
            // Add success message
            const messageContainer = document.createElement('div');
            messageContainer.className = 'alert alert-success mt-2';
            messageContainer.innerHTML = data.message || 'Files uploaded successfully';
            if (mediaContainer) {
                mediaContainer.appendChild(messageContainer);
                
                // Remove message after 3 seconds
                setTimeout(() => {
                    messageContainer.remove();
                }, 3000);
            }
            
        } else {
            // Handle error
            if (previewContainer) {
                previewContainer.innerHTML = `<div class="alert alert-danger">${data && data.error ? data.error : 'Upload failed'}</div>`;
            }
            console.error('Upload error:', data ? data.error : 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (previewContainer) {
            previewContainer.innerHTML = `<div class="alert alert-danger">Upload failed: ${error.message}. Please try again.</div>`;
        }
    });
}

// Event listeners for inventory media uploads
document.addEventListener('DOMContentLoaded', function() {
    // Handle inventory media file selection
    document.body.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('inventory-media-file')) {
            const fileInput = e.target;
            const mediaItem = fileInput.closest('.media-item');
            if (!mediaItem) {
                console.error('Media item container not found');
                return;
            }
            
            const previewContainer = mediaItem.querySelector('.media-preview');
            const imgPreview = previewContainer ? mediaItem.querySelector('.img-preview') : null;
            const videoPreview = previewContainer ? mediaItem.querySelector('.video-preview') : null;
            
            if (fileInput.files && fileInput.files[0]) {
                const file = fileInput.files[0];
                
                // Check file size - limit to 1GB
                if (file.size > 1024 * 1024 * 1024) {
                    alert(`File "${file.name}" exceeds the 1GB limit. Please select a smaller file.`);
                    // Clear the file input
                    fileInput.value = '';
                    return;
                }
                
                const reader = new FileReader();
                
                // Update file name display
                const fileNameDisplay = mediaItem.querySelector('.text-muted');
                if (fileNameDisplay) {
                    fileNameDisplay.textContent = file.name;
                }
                
                if (previewContainer && imgPreview && videoPreview) {
                    reader.onload = function(e) {
                        // Show preview container
                        previewContainer.classList.remove('d-none');
                        
                        // Check if file is image or video
                        if (file.type.startsWith('image/')) {
                            imgPreview.src = e.target.result;
                            imgPreview.style.display = 'block';
                            videoPreview.style.display = 'none';
                        } else if (file.type.startsWith('video/')) {
                            videoPreview.src = e.target.result;
                            videoPreview.style.display = 'block';
                            imgPreview.style.display = 'none';
                        }
                    };
                    
                    reader.readAsDataURL(file);
                }
            }
        }
    });
    
    // Add more media items button
    document.body.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.add-inventory-media-btn')) {
            e.preventDefault();
            const button = e.target.closest('.add-inventory-media-btn');
            const mediaSection = button.closest('.media-upload-section');
            if (!mediaSection) {
                console.error('Media upload section not found');
                return;
            }
            
            const mediaContainer = mediaSection.querySelector('.media-items-container');
            if (mediaContainer) {
                addInventoryMediaItem(mediaContainer);
            }
        }
    });
    
    // Remove media item button
    document.body.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.remove-media-btn')) {
            e.preventDefault();
            const button = e.target.closest('.remove-media-btn');
            const mediaItem = button.closest('.media-item');
            if (mediaItem) {
                mediaItem.remove();
            }
        }
    });
});

// Function to add a new media item
function addInventoryMediaItem(mediaContainer) {
    if (!mediaContainer) return;
    
    const mediaItemTemplate = `
        <div class="media-item mb-3 p-2 border rounded bg-white">
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="custom-file-upload">
                        <label class="file-label">
                            <input type="file" class="inventory-media-file" name="inventory_media_file[]" accept="image/*,video/*">
                            <span class="file-custom">
                                <i class="fas fa-photo-video me-2"></i> Choose Photo/Video
                            </span>
                        </label>
                        <small class="text-muted d-block mt-1">No file chosen</small>
                    </div>
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control" name="inventory_media_caption[]" placeholder="Caption (optional)">
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-media-btn">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
            <div class="media-preview mt-2 d-none">
                <img src="#" alt="Preview" class="img-preview img-fluid rounded">
                <video src="#" class="video-preview img-fluid rounded" controls style="display:none;"></video>
            </div>
        </div>
    `;
    
    // Create new element from template
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = mediaItemTemplate.trim();
    const newItem = tempDiv.firstChild;
    
    // Append to container
    mediaContainer.appendChild(newItem);
} 