// Work Progress Media Upload Handler
function handleWorkMediaUpload(workProgressId, fileInput, description = '') {
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
    formData.append('work_progress_id', workProgressId);
    formData.append('description', description);
    
    // Append each file
    for (let i = 0; i < files.length; i++) {
        formData.append('work_media_file[]', files[i]);
    }
    
    // Find the media container
    const mediaContainer = fileInput.closest('.media-container');
    if (!mediaContainer) {
        console.error('Media container not found');
        return;
    }
    
    // Show loading state
    const previewContainer = mediaContainer.querySelector('.media-preview');
    if (previewContainer) {
        previewContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Uploading...</div>';
    }
    
    // Send AJAX request
    fetch('includes/process_work_media.php', {
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
        if (data.success) {
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
                previewContainer.innerHTML = `<div class="alert alert-danger">${data.error || 'Upload failed'}</div>`;
            }
            console.error('Upload error:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (previewContainer) {
            previewContainer.innerHTML = `<div class="alert alert-danger">Upload failed: ${error.message}. Please try again.</div>`;
        }
    });
}

// Add event listener for file input changes
document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('work-media-file')) {
        const workProgressForm = e.target.closest('.work-progress-form');
        if (!workProgressForm) {
            console.error('Work progress form not found');
            return;
        }
        
        const workProgressId = workProgressForm.dataset.workNumber;
        if (!workProgressId) {
            console.error('Work progress ID not found');
            return;
        }
        
        const descriptionField = workProgressForm.querySelector('textarea[name="work_remarks[]"]');
        const description = descriptionField ? descriptionField.value : '';
        handleWorkMediaUpload(workProgressId, e.target, description);
    }
}); 