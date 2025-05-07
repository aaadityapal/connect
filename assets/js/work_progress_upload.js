// Work Progress Media Upload Handler
function handleWorkMediaUpload(workProgressId, fileInput, description = '') {
    const files = fileInput.files;
    if (!files.length) {
        console.error('No files selected');
        return;
    }

    // Find the media container early to show potential errors
    const mediaContainer = fileInput.closest('.media-container');
    if (!mediaContainer) {
        console.error('Media container not found');
        return;
    }
    
    // Find the preview container early
    const previewContainer = mediaContainer.querySelector('.media-preview');
    if (previewContainer) {
        previewContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Uploading...</div>';
    }

    // Check if the selected files are valid
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // Check file size - limit to 1GB
        if (file.size > 1024 * 1024 * 1024) {
            showError(previewContainer, `File "${file.name}" exceeds the 1GB limit. Please select a smaller file.`);
            // Clear the file input
            fileInput.value = '';
            return;
        }
        
        // Validate file type
        const fileType = file.type;
        if (!fileType.startsWith('image/') && !fileType.startsWith('video/')) {
            showError(previewContainer, `File "${file.name}" is not a valid image or video.`);
            // Clear the file input
            fileInput.value = '';
            return;
        }
    }

    const formData = new FormData();
    formData.append('work_progress_id', workProgressId);
    formData.append('description', description || ''); // Ensure we're not sending undefined
    
    // Log what's being sent
    console.log(`Uploading files for work_progress_id: ${workProgressId}`);
    console.log(`Description length: ${description ? description.length : 0} characters`);
    
    // Append each file
    for (let i = 0; i < files.length; i++) {
        // Use a direct name without array notation for single file upload
        if (files.length === 1) {
            formData.append('work_media_file', files[i]);
        } else {
            formData.append('work_media_file[]', files[i]);
        }
        console.log(`Adding file: ${files[i].name}, type: ${files[i].type}, size: ${files[i].size} bytes`);
    }
    
    // Use the mysqli version of the handler
    const uploadUrl = 'mysqli_process_work_media.php';
    
    // Send AJAX request
    fetch(uploadUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Log raw response for debugging
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // First check if the response is valid
        if (!response.ok) {
            throw new Error(`Server responded with status: ${response.status}`);
        }
        
        // Try to parse the JSON
        return response.text().then(text => {
            console.log('Raw response text:', text);
            try {
                if (!text) throw new Error('Empty response from server');
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error(`Invalid JSON response from server: ${e.message}`);
            }
        });
    })
    .then(data => {
        console.log('Parsed response data:', data);
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
                        } else if (result && !result.success) {
                            // Show individual file error
                            previewHTML += `<div class="alert alert-warning">
                                File upload issue: ${result.error || 'Unknown error'}
                            </div>`;
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
            showError(previewContainer, data.error || 'Upload failed');
            console.error('Upload error:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError(previewContainer, `Upload failed: ${error.message}. Please try again.`);
        
        // Check if the server might be rejecting due to file size
        let totalSize = 0;
        for (let i = 0; i < files.length; i++) {
            totalSize += files[i].size;
        }
        
        if (totalSize > 50 * 1024 * 1024) {  // If total is over 50MB
            showError(previewContainer, 'Total file size may be too large for server limits. Try uploading fewer or smaller files.');
        }
    });
}

// Helper function to show errors consistently
function showError(container, message) {
    if (container) {
        container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }
    console.error(message);
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