class TaskNotificationDialog {
    constructor() {
        // Add a default avatar data URI
        this.defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MCIgaGVpZ2h0PSI1MCIgdmlld0JveD0iMCAwIDUwIDUwIj48Y2lyY2xlIGN4PSIyNSIgY3k9IjE3IiByPSI5IiBmaWxsPSIjY2NjIi8+PHBhdGggZD0iTTI1IDMwYy03IDAtMTMgMy0xNiA3djZoMzJ2LTZjLTMtNC05LTctMTYtN3oiIGZpbGw9IiNjY2MiLz48L3N2Zz4=';
        this.init();
    }

    init() {
        document.addEventListener('click', (e) => {
            const bellIcon = e.target.closest('.fas.fa-bell');
            if (bellIcon) {
                e.preventDefault();
                e.stopPropagation();
                
                const stageElement = bellIcon.closest('[data-stage-id]');
                if (stageElement) {
                    const stageId = stageElement.dataset.stageId;
                    this.showNotificationDialog(stageId);
                }
            }
        });
    }

    async showNotificationDialog(stageId) {
        try {
            const data = await this.fetchTaskDetails(stageId);
            if (data.success) {
                this.renderDialog(data.task);
            } else {
                throw new Error(data.message || 'Failed to load task details');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to load task details'
            });
        }
    }

    async fetchTaskDetails(stageId) {
        const response = await fetch(`api/tasks/get_task_notifications.php?stage_id=${stageId}`);
        return await response.json();
    }

    renderDialog(task) {
        if (!task) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No task data available'
            });
            return;
        }

        const dialogContent = `
            <div class="notification-container">
                <div class="task-overview">
                    <div class="task-header">
                        <div class="task-title">${this.sanitize(task.title)}</div>
                        <div class="task-meta">
                            <span><i class="far fa-calendar"></i> Created: ${this.formatDate(task.created_at)}</span>
                            <span><i class="far fa-clock"></i> Due: ${this.formatDate(task.due_date)}</span>
                            <span><i class="fas fa-flag"></i> Priority: ${this.sanitize(task.priority)}</span>
                        </div>
                    </div>
                    <div class="task-description">${this.sanitize(task.description)}</div>
                </div>

                ${task.stage ? this.renderStage(task.stage) : '<p>No stage information available</p>'}
                
                ${task.substages && task.substages.length > 0 ? `
                    <div class="substages-container">
                        <h3>Substages</h3>
                        ${this.renderSubstages(task.substages)}
                    </div>
                ` : '<p>No substages available</p>'}

                ${task.activities && task.activities.length > 0 ? `
                    <div class="activity-timeline">
                        <h3>Recent Activity</h3>
                        ${this.renderTimeline(task.activities)}
                    </div>
                ` : '<p>No activities available</p>'}
            </div>
        `;

        Swal.fire({
            title: 'Task Details & Activity',
            html: dialogContent,
            width: '800px',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                container: 'notification-dialog'
            }
        });
    }

    renderStage(stage) {
        if (!stage) return '<p>No stage information available</p>';

        return `
            <div class="stage-item">
                <div class="stage-header">
                    <div class="stage-title">Stage ${stage.stage_number}</div>
                    <div class="stage-status status-${stage.status.toLowerCase()}">${stage.status}</div>
                </div>
                <div class="stage-details">
                    <div class="stage-dates">
                        <span>Start: ${this.formatDate(stage.start_date)}</span>
                        <span>Due: ${this.formatDate(stage.due_date)}</span>
                    </div>
                    <div class="stage-assignee">
                        <img src="${stage.assignee?.picture || this.defaultAvatar}" 
                             alt="Assignee" 
                             class="assignee-avatar"
                             onerror="this.src='${this.defaultAvatar}'">
                        <span>${stage.assignee?.name || 'Unassigned'}</span>
                        <span class="assignee-position">${stage.assignee?.position || ''}</span>
                    </div>
                </div>
                ${this.renderFiles(stage.files, 'Stage Files')}
            </div>
        `;
    }

    renderSubstages(substages) {
        if (!substages || substages.length === 0) return '<p>No substages found</p>';
        
        console.log('Substages data:', substages);
        
        return substages.map(substage => {
            console.log('Substage files:', substage.files);
            return `
                <div class="substage-item">
                    <div class="substage-header">
                        <div class="substage-title">${substage.description}</div>
                        <div class="substage-status status-${substage.status.toLowerCase()}">${substage.status}</div>
                    </div>
                    <div class="substage-details">
                        <div class="substage-dates">
                            <span>Start: ${this.formatDate(substage.start_date)}</span>
                            <span>Due: ${this.formatDate(substage.end_date)}</span>
                        </div>
                        <div class="substage-assignee">
                            <img src="${substage.assignee?.picture || this.defaultAvatar}" 
                                 alt="Assignee" 
                                 class="assignee-avatar"
                                 onerror="this.src='${this.defaultAvatar}'">
                            <span>${substage.assignee?.name || 'Unassigned'}</span>
                            <span class="assignee-position">${substage.assignee?.position || ''}</span>
                        </div>
                    </div>
                    ${this.renderFiles(substage.files, 'Substage Files')}
                </div>
            `;
        }).join('');
    }

    renderFiles(files, title) {
        if (!files || files.length === 0) return '';
        
        // Add debug logging
        console.log('Files data:', files);
        
        return `
            <div class="files-container">
                <h4>${title}</h4>
                ${files.map(file => {
                    // Log individual file data
                    console.log('File object:', file);
                    return `
                        <div class="file-item">
                            <i class="fas fa-file file-icon"></i>
                            <div class="file-info">
                                <div class="file-name">${this.sanitize(file.name || file.original_name)}</div>
                                <div class="file-meta">
                                    <span>${this.formatFileSize(file.size || file.file_size)}</span>
                                    <span>Uploaded by ${this.sanitize(file.uploaded_by || file.uploaded_by_name)} on ${this.formatDate(file.uploaded_at)}</span>
                                </div>
                            </div>
                            <div class="file-actions">
                                <a href="${this.getFileUrl(file)}" 
                                   target="_blank"
                                   class="file-download-link">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    // Add a helper method to construct the correct file URL
    getFileUrl(file) {
        const filePath = file.url || file.file_path;
        console.log('Original file path:', filePath);
        
        // If the path already starts with http/https, return as is
        if (filePath.startsWith('http')) {
            return this.sanitize(filePath);
        }
        
        // If the path already includes 'uploads', don't add it again
        if (filePath.includes('uploads/')) {
            return this.sanitize(filePath);
        }
        
        // Otherwise, construct the path
        return `/uploads/${this.sanitize(filePath)}`;
    }

    renderTimeline(activities) {
        if (!activities || activities.length === 0) return '<p>No activities found</p>';
        
        return activities.map(activity => {
            if (!activity) return '';
            
            return `
                <div class="timeline-item">
                    <div class="timeline-header">
                        <img src="${activity.user?.picture || this.defaultAvatar}" 
                             alt="User" 
                             class="timeline-user-avatar"
                             onerror="this.src='${this.defaultAvatar}'">
                        <div class="timeline-user-info">
                            <span class="timeline-user-name">${activity.user?.name || 'Unknown User'}</span>
                            <span class="timeline-user-position">${activity.user?.position || ''}</span>
                        </div>
                        <span class="timeline-time">${this.formatDate(activity.timestamp)}</span>
                    </div>
                    <div class="timeline-content">
                        ${this.formatActivityContent(activity)}
                    </div>
                </div>
            `;
        }).join('');
    }

    formatActivityContent(activity) {
        if (!activity) return 'No activity details available';

        if (activity.type === 'status_change') {
            const entityText = this.sanitize(activity.entity_type || 'item');
            let statusChange = '';
            
            if (activity.old_status && activity.new_status) {
                statusChange = `
                    <div class="status-change">
                        ${entityText} status changed from 
                        <span class="status-badge status-${(activity.old_status || '').toLowerCase()}">${this.sanitize(activity.old_status)}</span> 
                        to 
                        <span class="status-badge status-${(activity.new_status || '').toLowerCase()}">${this.sanitize(activity.new_status)}</span>
                    </div>
                `;
            }

            return `
                <div class="activity-content">
                    ${statusChange}
                    ${activity.comment ? `
                        <div class="activity-comment">
                            <i class="fas fa-comment"></i> ${this.sanitize(activity.comment)}
                        </div>
                    ` : ''}
                    ${activity.file_path ? `
                        <div class="activity-file">
                            <a href="/uploads/${this.sanitize(activity.file_path)}" 
                               target="_blank"
                               class="file-download-link">
                                <i class="fas fa-paperclip"></i> View Attachment
                            </a>
                        </div>
                    ` : ''}
                </div>
            `;
        }
        return this.sanitize(activity.comment) || 'No details available';
    }

    sanitize(text) {
        if (text === null || text === undefined) return 'N/A';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            return new Date(dateString).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return 'Invalid Date';
        }
    }

    formatFileSize(bytes) {
        if (!bytes) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Initialize the notification dialog system
document.addEventListener('DOMContentLoaded', () => {
    // Create a global instance of TaskNotificationDialog
    if (!window.taskNotificationDialog) {
        window.taskNotificationDialog = new TaskNotificationDialog();
    }
});

// Add a global function to handle notification dialog clicks
function showNotificationDialog(stageId) {
    if (window.taskNotificationDialog) {
        window.taskNotificationDialog.showNotificationDialog(stageId);
    } else {
        console.error('TaskNotificationDialog not initialized');
    }
} 