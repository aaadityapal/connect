class StageTimeline {
    constructor() {
        this.bindEvents();
    }

    bindEvents() {
        document.addEventListener('click', (e) => {
            const timelineIcon = e.target.closest('.timeline-icon');
            if (timelineIcon) {
                const stageId = timelineIcon.dataset.stageId;
                if (stageId) {
                    this.viewStageTimeline(stageId);
                }
            }
        });
    }

    async viewStageTimeline(stageId) {
        try {
            Swal.fire({
                title: 'Loading Timeline...',
                didOpen: () => {
                    Swal.showLoading();
                },
                allowOutsideClick: false
            });

            const response = await fetch(`api/tasks/get_stage_timeline.php?stage_id=${stageId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to load timeline data');
            }

            this.renderTimeline(data);
        } catch (error) {
            console.error('Timeline Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to load timeline data',
                confirmButtonText: 'OK'
            });
        }
    }

    renderTimeline(data) {
        const { stage, substages, stageFiles, substageFiles } = data;
        
        const timelineHTML = `
            <div class="timeline-wrapper">
                ${this.renderStageSection(stage, stageFiles)}
                ${this.renderSubstagesSection(substages, substageFiles)}
            </div>
        `;

        Swal.fire({
            title: `Stage ${stage.stage_number} - ${stage.task_title}`,
            html: timelineHTML,
            width: '800px',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                container: 'timeline-modal',
                popup: 'timeline-popup'
            }
        });
    }

    renderStageSection(stage, files) {
        return `
            <div class="timeline-stage-section">
                <div class="stage-header">
                    <div class="stage-info">
                        <h3>Stage Information</h3>
                        <div class="stage-badges">
                            <span class="status-badge ${stage.status.toLowerCase()}">${stage.status}</span>
                            <span class="priority-badge ${stage.priority.toLowerCase()}">${stage.priority}</span>
                        </div>
                    </div>
                    ${stage.assigned_user ? this.renderAssignedUser(stage.assigned_user) : ''}
                </div>
                
                <div class="stage-details">
                    <div class="detail-item">
                        <span class="label">Created</span>
                        <span class="value">${this.formatDate(stage.created_at)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Start Date</span>
                        <span class="value">${stage.start_date ? this.formatDate(stage.start_date) : 'Not set'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Due Date</span>
                        <span class="value">${this.formatDate(stage.due_date)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Last Updated</span>
                        <span class="value">${this.formatDate(stage.updated_at)}</span>
                    </div>
                </div>

                ${this.renderFilesSection(files, 'Stage Files')}
            </div>
        `;
    }

    renderAssignedUser(user) {
        return `
            <div class="assigned-user">
                <span class="user-avatar">
                    <i class="fas fa-user"></i>
                </span>
                <div class="user-info">
                    <span class="user-name">${user.username}</span>
                    <span class="user-role">${user.role}</span>
                </div>
            </div>
        `;
    }

    renderSubstagesSection(substages, allSubstageFiles) {
        if (!substages || substages.length === 0) {
            return '<div class="no-substages">No substages found</div>';
        }

        return `
            <div class="timeline-substages-section">
                <h3>Substages (${substages.length})</h3>
                ${substages.map(substage => {
                    const substageFiles = allSubstageFiles.filter(file => 
                        file.substage_id === substage.id
                    );
                    return this.renderSubstage(substage, substageFiles);
                }).join('')}
            </div>
        `;
    }

    renderSubstage(substage, files) {
        return `
            <div class="substage-item" data-status="${substage.status.toLowerCase()}">
                <div class="substage-header">
                    <div class="substage-info">
                        <span class="substage-status ${substage.status.toLowerCase()}"></span>
                        <p class="substage-description">${substage.description}</p>
                    </div>
                    <div class="substage-badges">
                        <span class="status-badge ${substage.status.toLowerCase()}">${substage.status}</span>
                        <span class="priority-badge ${substage.priority.toLowerCase()}">${substage.priority}</span>
                    </div>
                </div>

                <div class="substage-details">
                    <div class="detail-item">
                        <span class="label">Start Date</span>
                        <span class="value">${substage.start_date ? this.formatDate(substage.start_date) : 'Not set'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">End Date</span>
                        <span class="value">${substage.end_date ? this.formatDate(substage.end_date) : 'Not set'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Created</span>
                        <span class="value">${this.formatDate(substage.created_at)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Last Updated</span>
                        <span class="value">${this.formatDate(substage.updated_at)}</span>
                    </div>
                </div>

                ${this.renderFilesSection(files, 'Substage Files')}
            </div>
        `;
    }

    renderFilesSection(files, title) {
        if (!files || files.length === 0) {
            return `
                <div class="files-section">
                    <h4>${title}</h4>
                    <div class="no-files">No files attached</div>
                </div>
            `;
        }

        return `
            <div class="files-section">
                <h4>${title} (${files.length})</h4>
                <div class="files-grid">
                    ${files.map(file => this.renderFileItem(file)).join('')}
                </div>
            </div>
        `;
    }

    renderFileItem(file) {
        return `
            <div class="file-item">
                <div class="file-icon">
                    <i class="${this.getFileIcon(file.file_type)}"></i>
                </div>
                <div class="file-info">
                    <a href="${file.file_path}" target="_blank" class="file-name" title="${file.original_name}">
                        ${file.original_name}
                    </a>
                    <div class="file-meta">
                        <span class="file-size">${this.formatFileSize(file.file_size)}</span>
                        <span class="file-date">Uploaded: ${this.formatDate(file.uploaded_at)}</span>
                        <span class="file-uploader">By: ${file.uploader_name}</span>
                    </div>
                </div>
            </div>
        `;
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    getFileIcon(fileType) {
        const icons = {
            'application/pdf': 'fas fa-file-pdf',
            'application/msword': 'fas fa-file-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'fas fa-file-word',
            'application/vnd.ms-excel': 'fas fa-file-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'fas fa-file-excel',
            'image/': 'fas fa-file-image'
        };
        
        for (const [type, icon] of Object.entries(icons)) {
            if (fileType.startsWith(type)) return icon;
        }
        return 'fas fa-file';
    }
}

// Initialize timeline when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.stageTimeline = new StageTimeline();
});