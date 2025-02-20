class StageTimeline {
    constructor() {
        this.modalId = 'timelineModal';
    }

    async show(stageId) {
        try {
            console.log('Loading timeline for stage:', stageId);

            const response = await fetch(`api/tasks/get_timeline.php?stage_id=${stageId}`);
            const responseText = await response.text();
            console.log('Raw timeline response:', responseText);

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Timeline parse error:', e);
                throw new Error('Invalid timeline response format');
            }

            if (!data.success) {
                throw new Error(data.error || 'Failed to load timeline');
            }

            if (!data.stage || !data.timeline) {
                throw new Error('Invalid timeline data structure');
            }

            const timelineHTML = this.generateTimelineHTML(data.stage, data.timeline);

            Swal.fire({
                title: `Timeline: Stage ${data.stage.stage_number}`,
                html: timelineHTML,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    container: 'timeline-modal'
                }
            });

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

    generateTimelineHTML(stage, timeline) {
        return `
            <div class="timeline-wrapper">
                <div class="stage-info">
                    <h4>Task: ${stage.task_title || 'N/A'}</h4>
                    <p>Stage ${stage.stage_number || 'N/A'}</p>
                    <span class="status-badge ${stage.status?.toLowerCase() || 'none'}">${stage.status || 'None'}</span>
                </div>
                
                <div class="timeline-items">
                    ${timeline.length > 0 ? this.generateTimelineItems(timeline) : 
                      '<div class="no-timeline">No timeline entries found</div>'}
                </div>
            </div>
        `;
    }

    generateTimelineItems(timeline) {
        return timeline.map(event => {
            const date = new Date(event.date).toLocaleString();
            
            switch(event.type) {
                case 'status_change':
                    return `
                        <div class="timeline-item">
                            <div class="timeline-date">${date}</div>
                            <div class="timeline-content">
                                <div class="status-change">
                                    <strong>${event.entity_type === 'stage' ? 'Stage' : 'Substage'}:</strong> 
                                    ${event.entity_description || ''}
                                    <div class="status-update">
                                        Status changed from 
                                        <span class="status-badge ${event.old_status?.toLowerCase() || 'none'}">${event.old_status || 'None'}</span>
                                        to 
                                        <span class="status-badge ${event.new_status.toLowerCase()}">${event.new_status}</span>
                                    </div>
                                    <div class="change-meta">
                                        Changed by ${event.changed_by || 'System'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                default:
                    return '';
            }
        }).join('');
    }
}

// Initialize timeline
const timeline = new StageTimeline();

// Add click handler for timeline buttons/icons
document.addEventListener('click', function(e) {
    if (e.target.matches('.view-timeline-btn') || e.target.matches('.timeline-icon')) {
        const stageId = e.target.closest('[data-stage-id]')?.dataset.stageId;
        if (stageId) {
            timeline.show(stageId);
        } else {
            console.error('No stage ID found for timeline');
        }
    }
});

// Add timeline styles if not already present
const timelineStyles = `
    .timeline-wrapper {
        padding: 20px;
        max-height: 500px;
        overflow-y: auto;
    }

    .stage-info {
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .timeline-items {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .timeline-item {
        display: flex;
        gap: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
    }

    .timeline-date {
        min-width: 150px;
        color: #666;
    }

    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 0.85em;
    }

    .status-badge.pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-badge.in_progress {
        background: #cce5ff;
        color: #004085;
    }

    .status-badge.completed {
        background: #d4edda;
        color: #155724;
    }

    .change-meta {
        margin-top: 5px;
        font-size: 0.85em;
        color: #666;
    }

    .no-timeline {
        text-align: center;
        padding: 20px;
        color: #666;
        font-style: italic;
    }
`;

if (!document.querySelector('#timeline-styles')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'timeline-styles';
    styleElement.textContent = timelineStyles;
    document.head.appendChild(styleElement);
} 