class TaskTimeline {
    static async viewStageTimeline(stageId, taskId) {
        try {
            // Show loading state
            Swal.fire({
                title: 'Loading Timeline...',
                html: '<div class="timeline-loading"><i class="fas fa-spinner"></i> Loading stage details...</div>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            // Log the request parameters
            console.log('Requesting timeline for:', { stageId, taskId });

            const response = await fetch(`api/timeline/get_stage_timeline.php?stage_id=${stageId}&task_id=${taskId}`);
            const responseText = await response.text();
            
            // Log the raw response
            console.log('Raw API Response:', responseText);

            // Try to parse the JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('JSON Parse Error:', e);
                throw new Error('Invalid JSON response from server');
            }

            // Log the parsed data
            console.log('Parsed Timeline Data:', data);

            if (data.success) {
                const timelineHTML = this.generateStageTimelineHTML(data.timeline);
                this.showTimelineModal('Stage Timeline', timelineHTML);
            } else {
                throw new Error(data.message || 'Failed to load timeline');
            }
        } catch (error) {
            console.error('Timeline error:', error);
            Swal.fire('Error', error.message || 'Failed to load timeline', 'error');
        }
    }

    static async viewSubstageTimeline(substageId, stageId) {
        try {
            Swal.fire({
                title: 'Loading Timeline...',
                html: '<div class="timeline-loading"><i class="fas fa-spinner"></i> Loading substage details...</div>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            const response = await fetch(`api/timeline/get_substage_timeline.php?substage_id=${substageId}&stage_id=${stageId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to load substage timeline');
            }

            const timelineHTML = this.generateSubstageTimelineHTML(data.timeline);
            
            Swal.fire({
                title: 'Substage Timeline',
                html: timelineHTML,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    container: 'timeline-modal-container',
                    popup: 'timeline-modal-popup'
                }
            });
        } catch (error) {
            console.error('Substage Timeline Error:', error);
            Swal.fire('Error', 'Failed to load substage timeline', 'error');
        }
    }

    static generateStageTimelineHTML(timeline) {
        const { stage, files, status_history = [], substages = [] } = timeline;
        
        // Add console log to debug the data
        console.log('Generating HTML with:', {
            stage,
            files,
            status_history,
            substages
        });
        
        return `
            <div class="timeline-container">
                <div class="timeline-section">
                    <h4>Status History</h4>
                    ${this.generateStatusHistoryHTML(status_history)}
                </div>
                
                <div class="timeline-section">
                    <h4>Stage Files</h4>
                    <div class="files-container">
                        ${this.generateFilesHTML(files, stage.id, stage.task_id)}
                    </div>
                </div>
                
                ${substages.length > 0 ? this.generateSubstagesHTML(substages) : ''}
            </div>
        `;
    }

    static generateSubstageTimelineHTML(timeline) {
        const { substage, files, status_history } = timeline;

        return `
            <div class="timeline-container">
                <div class="timeline-header">
                    <div class="substage-info">
                        <div class="substage-main-info">
                            <h3>${substage.description}</h3>
                            <span class="status-badge ${substage.status.toLowerCase()}">${substage.status}</span>
                        </div>
                        <div class="substage-meta">
                            <span class="priority-badge ${substage.priority.toLowerCase()}">
                                <i class="fas fa-flag"></i> ${substage.priority}
                            </span>
                        </div>
                    </div>
                    
                    <div class="substage-dates">
                        <span>
                            <i class="fas fa-calendar-alt"></i>
                            Start: ${this.formatDate(substage.start_date) || 'Not started'}
                        </span>
                        <span>
                            <i class="fas fa-calendar-check"></i>
                            Due: ${this.formatDate(substage.end_date) || 'Not set'}
                        </span>
                        <span>
                            <i class="fas fa-clock"></i>
                            Last Updated: ${this.formatDate(substage.updated_at)}
                        </span>
                    </div>
                </div>

                <div class="timeline-section">
                    <h4>
                        <i class="fas fa-paperclip"></i>
                        Attached Files
                    </h4>
                    <div class="files-container">
                        ${this.generateFilesHTML(files, substage.id, substage.task_id)}
                    </div>
                </div>

                <div class="timeline-section">
                    <h4>
                        <i class="fas fa-history"></i>
                        Status History
                    </h4>
                    <div class="status-history">
                        ${this.generateStatusHistoryHTML(status_history)}
                    </div>
                </div>
            </div>
        `;
    }

    static generateFilesHTML(files, stageId = null, taskId = null) {
        if (!files || files.length === 0) {
            return '<p class="no-files">No files attached</p>';
        }

        return files.map(file => `
            <div class="file-item">
                <div class="file-icon">
                    <i class="fas ${this.getFileIcon(file.file_type)}"></i>
                </div>
                <div class="file-info">
                    <div class="file-details">
                        <span class="file-name">${file.original_name}</span>
                        <span class="file-meta">
                            <span class="file-size">${this.formatFileSize(file.file_size)}</span>
                            <span class="file-date">Uploaded: ${this.formatDate(file.uploaded_at)}</span>
                        </span>
                    </div>
                </div>
                <div class="file-actions">
                    <a href="${file.file_path}" class="action-btn download-btn" download title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <a href="#" class="action-btn comment-btn" onclick="TaskTimeline.toggleComments(this)" title="Comments">
                        <i class="fas fa-comment-dots"></i>
                    </a>
                </div>
            </div>
            <div class="file-comments" style="display: none;">
                <div class="comments-section">
                    <div class="comments-list">
                        ${file.comments ? this.generateCommentsHTML(file.comments) : '<p class="no-comments">No comments yet</p>'}
                    </div>
                </div>
                <div class="comment-form">
                    <textarea class="comment-input" placeholder="Add a comment about this file..."></textarea>
                    <button class="btn btn-sm btn-primary add-comment-btn" 
                            onclick="TaskTimeline.addFileComment(${file.id}, ${stageId}, this)">
                        Add Comment
                    </button>
                </div>
            </div>
        `).join('');
    }

    static generateSubstagesHTML(substages) {
        if (!substages || substages.length === 0) {
            return '<p class="no-substages">No substages found</p>';
        }

        return substages.map(substage => `
            <div class="substage-timeline-item">
                <div class="substage-header">
                    <h5>${substage.description}</h5>
                    <span class="status-badge ${substage.status.toLowerCase()}">${substage.status}</span>
                </div>
                <div class="substage-dates">
                    <span>Start: ${substage.start_date || 'Not started'}</span>
                    <span>End: ${substage.end_date || 'Not set'}</span>
                </div>
                <div class="substage-files">
                    ${this.generateFilesHTML(substage.files, substage.id, substage.task_id)}
                </div>
            </div>
        `).join('');
    }

    static getFileIcon(fileType) {
        const iconMap = {
            'image': 'fa-image',
            'pdf': 'fa-file-pdf',
            'doc': 'fa-file-word',
            'docx': 'fa-file-word',
            'xls': 'fa-file-excel',
            'xlsx': 'fa-file-excel',
            'txt': 'fa-file-alt'
        };

        const type = fileType.toLowerCase();
        return iconMap[type] || 'fa-file';
    }

    static formatFileSize(bytes) {
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 Byte';
        const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }

    static formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    static showTimelineModal(title, content) {
        Swal.fire({
            title,
            html: content,
            width: '800px',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                container: 'timeline-modal-container',
                popup: 'timeline-modal-popup'
            }
        });
    }

    static generateStatusHistoryHTML(history = []) {
        if (!history || history.length === 0) {
            return '<p class="no-history">No status changes recorded</p>';
        }

        return `
            <div class="status-timeline">
                ${history.map((item, index) => `
                    <div class="status-item ${index === 0 ? 'current' : ''}">
                        <div class="status-marker"></div>
                        <div class="status-content">
                            <div class="status-change">
                                <span class="status-badge ${item.old_status?.toLowerCase() || ''}">
                                    ${item.old_status || 'Initial Status'}
                                </span>
                                <i class="fas fa-arrow-right"></i>
                                <span class="status-badge ${item.new_status.toLowerCase()}">
                                    ${item.new_status}
                                </span>
                            </div>
                            <div class="status-meta">
                                <span class="status-user">
                                    <i class="fas fa-user"></i> ${item.changed_by}
                                </span>
                                <span class="status-date">
                                    <i class="fas fa-clock"></i> ${this.formatDate(item.changed_at)}
                                </span>
                                <span class="entity-type">
                                    ${item.entity_type === 'stage' ? 'Stage' : 'Substage'} #${item.entity_id}
                                </span>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    static toggleComments(button) {
        const fileItem = button.closest('.file-item');
        const commentsSection = fileItem.querySelector('.file-comments');
        const isHidden = commentsSection.style.display === 'none';
        
        commentsSection.style.display = isHidden ? 'block' : 'none';
        button.title = isHidden ? 'Hide Comments' : 'Show Comments';
    }
}

// Export the class for use in other files
window.TaskTimeline = TaskTimeline; 