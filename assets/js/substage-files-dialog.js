function showSubstageFilesDialog(substageId) {
    fetch(`api/tasks/get_substage_files.php?substage_id=${substageId}`)
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);
            if (!data.success) {
                throw new Error(data.error || 'Failed to load substage files');
            }

            // Ensure data.comments and data.status_history exist
            const timelineItems = [
                ...(data.comments || []).map(comment => ({
                    type: 'comment',
                    date: new Date(comment.changed_at),
                    content: `
                        <div class="sf-comment-item">
                            <div class="sf-comment-header">
                                <span class="sf-commenter-name">${comment.username || 'Unknown User'}</span>
                                <span class="sf-comment-date">${formatDateIST(comment.changed_at)}</span>
                            </div>
                            <div class="sf-comment-text">${comment.comment || ''}</div>
                            ${comment.file_path ? `
                                <div class="sf-file-attachment">
                                    <i class="fas fa-file"></i>
                                    <a href="uploads/${comment.file_path}" target="_blank" class="sf-file-link">
                                        Attached File
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            ` : ''}
                        </div>
                    `
                })),
                ...(data.status_history || []).map(status => ({
                    type: 'status',
                    date: new Date(status.changed_at),
                    content: `
                        <div class="sf-status-item">
                            <div class="sf-status-header">
                                <span class="sf-status-user">${status.changed_by || 'Unknown User'}</span>
                                <span class="sf-status-date">${formatDateIST(status.changed_at)}</span>
                            </div>
                            <div class="sf-status-change">
                                Status changed from 
                                <span class="sf-status-old">${status.old_status || 'Unknown'}</span> to 
                                <span class="sf-status-new">${status.new_status || 'Unknown'}</span>
                            </div>
                        </div>
                    `
                }))
            ];

            // Add debug logging for timeline items
            console.log('Timeline Items:', timelineItems);

            // Sort all items by date (newest first)
            timelineItems.sort((a, b) => b.date - a.date);

            const timelineHTML = timelineItems.map(item => item.content).join('');

            Swal.fire({
                title: 'Substage Timeline',
                html: `
                    <div class="sf-container">
                        <div class="sf-comment-box">
                            <textarea id="sfCommentText" class="sf-comment-input" placeholder="Add a comment..."></textarea>
                            <div class="sf-attachment-area">
                                <input type="file" id="sfFileAttachment" class="sf-file-input">
                                <label for="sfFileAttachment" class="sf-file-label">
                                    <i class="fas fa-paperclip"></i> Attach File
                                </label>
                                <div id="sfSelectedFile" class="sf-selected-file"></div>
                            </div>
                            <button onclick="addSFComment(${substageId})" class="sf-comment-btn">
                                <i class="fas fa-paper-plane"></i> Add Comment
                            </button>
                        </div>
                        <div class="sf-timeline">
                            ${timelineHTML || '<p class="sf-no-data">No activity yet</p>'}
                        </div>
                    </div>
                `,
                width: '700px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    container: 'sf-dialog-container'
                }
            });

            // Add file input change listener after dialog is shown
            setTimeout(() => {
                document.getElementById('sfFileAttachment').addEventListener('change', showSelectedFile);
            }, 100);
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to load substage files'
            });
        });
}

function showSelectedFile(event) {
    const file = event.target.files[0];
    const container = document.getElementById('sfSelectedFile');
    
    if (file) {
        container.innerHTML = `
            <div class="sf-selected-file-item">
                <i class="fas fa-file"></i>
                <span>${file.name}</span>
                <small>(${formatFileSize(file.size)})</small>
            </div>
        `;
    } else {
        container.innerHTML = '';
    }
}

function addSFComment(substageId) {
    const commentText = document.getElementById('sfCommentText').value.trim();
    const fileInput = document.getElementById('sfFileAttachment');
    const file = fileInput.files[0]; // Get single file
    
    if (!commentText && !file) {
        Swal.fire({
            icon: 'warning',
            title: 'Empty Submission',
            text: 'Please enter a comment or attach a file before submitting.'
        });
        return;
    }

    const formData = new FormData();
    formData.append('substage_id', substageId);
    formData.append('comment', commentText);
    if (file) {
        formData.append('file', file);
        // Add file metadata
        formData.append('original_name', file.name);
        formData.append('file_type', file.type);
        formData.append('file_size', file.size);
    }

    fetch('api/tasks/add_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear form
            document.getElementById('sfCommentText').value = '';
            document.getElementById('sfFileAttachment').value = '';
            document.getElementById('sfSelectedFile').innerHTML = '';
            
            // Refresh dialog
            showSubstageFilesDialog(substageId);
            
            // Show success toast
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });

            Toast.fire({
                icon: 'success',
                title: 'Comment added successfully'
            });
        } else {
            throw new Error(data.error || 'Failed to add comment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Failed to add comment'
        });
    });
}

function formatDateIST(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const options = {
        timeZone: 'Asia/Kolkata',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    };
    return date.toLocaleString('en-IN', options);
}

function formatFileSize(size) {
    if (size < 1024) {
        return size + ' bytes';
    } else if (size < 1024 * 1024) {
        return (size / 1024).toFixed(2) + ' KB';
    } else if (size < 1024 * 1024 * 1024) {
        return (size / (1024 * 1024)).toFixed(2) + ' MB';
    } else {
        return (size / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
    }
} 