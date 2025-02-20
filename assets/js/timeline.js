// Timeline functionality
class StageTimeline {
    constructor() {
        this.baseUrl = 'api/tasks';
    }

    async show(stageId) {
        try {
            // Show loading state
            Swal.fire({
                title: 'Loading Timeline...',
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false
            });

            // Fetch timeline data
            const response = await fetch(`${this.baseUrl}/get_timeline.php?stage_id=${stageId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load timeline');
            }

            // Display timeline
            Swal.fire({
                title: `Timeline: Stage ${data.stage.stage_number}`,
                html: this.generateTimelineHTML(data),
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    container: 'timeline-modal',
                    popup: 'timeline-popup'
                }
            });

        } catch (error) {
            console.error('Timeline Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to load timeline'
            });
        }
    }

    generateTimelineHTML(data) {
        const { stage, timeline } = data;
        
        return `
            <div class="timeline-container">
                <!-- Stage Information -->
                <div class="timeline-section stage-info">
                    <h3>Stage Information</h3>
                    <div class="stage-details">
                        <div class="stage-header">
                            <span class="stage-number">Stage ${stage.stage_number}</span>
                            <span class="stage-status ${stage.status.toLowerCase()}">${stage.status}</span>
                        </div>
                        <div class="stage-description">${stage.description || 'No description available'}</div>
                    </div>
                </div>

                <!-- Stage Files -->
                ${this.generateFilesSection(timeline.filter(item => 
                    item.item_type === 'stage_file'
                ), 'Stage Files')}

                <!-- Substages -->
                ${this.generateSubstagesSection(timeline.filter(item => 
                    item.item_type === 'substage' || item.item_type === 'substage_file'
                ))}
            </div>
        `;
    }

    generateFilesSection(files, title) {
        if (!files.length) return '';

        return `
            <div class="timeline-section files-section">
                <h3>${title}</h3>
                <div class="files-container">
                    ${files.map(file => this.generateFileCard(file)).join('')}
                </div>
            </div>
        `;
    }

    generateFileCard(file) {
        const fileInfo = file.file_info;
        const fileIcon = this.getFileIcon(fileInfo.file_type);
        
        return `
            <div class="file-card">
                <div class="file-icon">
                    <i class="fas ${fileIcon}"></i>
                </div>
                <div class="file-details">
                    <div class="file-name">${fileInfo.original_name}</div>
                    <div class="file-meta">
                        <span class="file-size">${this.formatFileSize(fileInfo.file_size)}</span>
                        <span class="file-date">${this.formatDate(file.event_time)}</span>
                    </div>
                </div>
                <div class="file-actions">
                    <a href="${fileInfo.file_path}" download="${fileInfo.original_name}" 
                       class="file-action-btn" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    ${this.canPreviewFile(fileInfo.file_type) ? `
                        <button class="file-action-btn" 
                                onclick="stageTimeline.previewFile('${fileInfo.file_path}', '${fileInfo.file_type}')"
                                title="Preview">
                            <i class="fas fa-eye"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    generateSubstagesSection(items) {
        if (!items.length) return '';

        // Group substages and their files
        const substages = {};
        items.forEach(item => {
            if (item.item_type === 'substage') {
                substages[item.id] = {
                    ...item,
                    files: []
                };
            } else if (item.item_type === 'substage_file') {
                if (substages[item.parent_substage_id]) {
                    substages[item.parent_substage_id].files.push(item);
                }
            }
        });

        return `
            <div class="timeline-section substages-section">
                <h3>Substages</h3>
                ${Object.values(substages).map(substage => `
                    <div class="substage-container">
                        <div class="substage-header">
                            <h4>Substage ${substage.substage_number}</h4>
                            <span class="substage-status ${substage.status.toLowerCase()}">
                                ${substage.status}
                            </span>
                        </div>
                        <div class="substage-description">
                            ${substage.description || 'No description available'}
                        </div>
                        ${this.generateFilesSection(substage.files, 'Substage Files')}
                    </div>
                `).join('')}
            </div>
        `;
    }

    getFileIcon(fileType) {
        const icons = {
            'pdf': 'fa-file-pdf',
            'doc': 'fa-file-word',
            'docx': 'fa-file-word',
            'xls': 'fa-file-excel',
            'xlsx': 'fa-file-excel',
            'jpg': 'fa-file-image',
            'jpeg': 'fa-file-image',
            'png': 'fa-file-image',
            'gif': 'fa-file-image'
        };
        return icons[fileType.toLowerCase()] || 'fa-file';
    }

    formatFileSize(bytes) {
        if (!bytes) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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

    canPreviewFile(fileType) {
        return ['jpg', 'jpeg', 'png', 'gif', 'pdf'].includes(fileType.toLowerCase());
    }

    previewFile(filePath, fileType) {
        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileType.toLowerCase());
        const isPdf = fileType.toLowerCase() === 'pdf';

        let previewHTML = '';
        if (isImage) {
            previewHTML = `<img src="${filePath}" alt="Preview" class="file-preview-image">`;
        } else if (isPdf) {
            previewHTML = `<iframe src="${filePath}" class="file-preview-pdf"></iframe>`;
        }

        Swal.fire({
            html: `<div class="file-preview-container">${previewHTML}</div>`,
            width: '800px',
            height: '600px',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                container: 'file-preview-modal'
            }
        });
    }
}

// Initialize the timeline
const stageTimeline = new StageTimeline();

// Global function to show timeline
function viewStageTimeline(stageId) {
    stageTimeline.show(stageId);
} 