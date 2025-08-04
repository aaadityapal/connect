<?php
// Timeline Modal
?>
<!-- Timeline Modal -->
<div class="modal fade" id="timelineModal" tabindex="-1" aria-labelledby="timelineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0 bg-white">
                <h6 class="modal-title" id="timelineModalLabel">
                    <i class="bi bi-clock-history me-2"></i>
                    <span class="timeline-date">Travel Timeline</span>
                </h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Timeline content container -->
                <div class="timeline-container">
                    <div class="text-center p-4" id="timeline-loader">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0 small text-secondary">Loading timeline...</p>
                    </div>
                    <div id="timeline-content" class="d-none">
                        <div class="timeline-wrapper">
                            <div class="timeline-header mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="timeline-user-avatar me-2">
                                        <img src="" alt="User" class="rounded-circle" width="32" height="32">
                                    </div>
                                    <div class="timeline-user-info">
                                        <div class="timeline-username small fw-medium"></div>
                                        <div class="timeline-date-info text-muted" style="font-size: 0.7rem;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="timeline-events"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline Modal Styles */
:root {
    /* Core colors */
    --primary-color: #4361ee;
    --primary-light: #eef2ff;
    --primary-dark: #3730a3;
    --secondary-color: #6366f1;
    
    /* Status colors */
    --success-color: #10b981;
    --success-light: #d1fae5;
    --warning-color: #f59e0b;
    --warning-light: #fef3c7;
    --danger-color: #ef4444;
    --danger-light: #fee2e2;
    
    /* Gradient colors */
    --gradient-start: #4361ee;
    --gradient-end: #3730a3;
    
    /* Background colors */
    --background-color: #ffffff;
    --surface-color: #f8fafc;
    --surface-light: #f1f5f9;
    --surface-dark: #e2e8f0;
    
    /* Text colors */
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    --text-light: #cbd5e1;
    
    /* Border colors */
    --border-color: #e2e8f0;
    --border-light: #f1f5f9;
}

#timelineModal .modal-dialog {
    max-width: 480px;
}

#timelineModal .modal-content {
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(67, 97, 238, 0.1);
    background: var(--background-color);
}

#timelineModal .modal-header {
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    color: white;
}

#timelineModal .modal-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--background-color);
    display: flex;
    align-items: center;
    letter-spacing: 0.3px;
}

#timelineModal .modal-title i {
    font-size: 0.9rem;
    color: var(--background-color);
    background: rgba(255, 255, 255, 0.2);
    padding: 0.5rem;
    border-radius: 8px;
    backdrop-filter: blur(4px);
}

#timelineModal .timeline-date {
    color: var(--text-primary);
    margin-left: 0.5rem;
    font-weight: 600;
}

#timelineModal .btn-close {
    padding: 0.5rem;
    font-size: 0.75rem;
    opacity: 0.5;
    background-color: var(--surface-color);
    border-radius: 50%;
}

#timelineModal .btn-close:hover {
    opacity: 0.75;
    background-color: var(--border-color);
}

#timelineModal .modal-body {
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    background: linear-gradient(135deg, var(--surface-color), var(--background-color));
}

#timelineModal .timeline-container {
    min-height: 250px;
    padding: 1rem;
}

/* Custom scrollbar */
#timelineModal .modal-body::-webkit-scrollbar {
    width: 4px;
}

#timelineModal .modal-body::-webkit-scrollbar-track {
    background: var(--surface-color);
}

#timelineModal .modal-body::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 2px;
}

#timelineModal .modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* Animation */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#timelineModal.show .modal-content {
    animation: modalFadeIn 0.2s ease-out forwards;
}

/* Loader styles */
#timeline-loader {
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-color: var(--background-color);
    border-radius: 10px;
    margin: 1rem;
    box-shadow: 0 2px 8px rgba(67, 97, 238, 0.05);
}

#timeline-loader .spinner-border {
    width: 1.5rem;
    height: 1.5rem;
    color: var(--primary-color);
}

#timeline-loader p {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Coming soon placeholder styles */
#timeline-content .text-muted {
    padding: 2rem 1rem;
    background-color: var(--background-color);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(67, 97, 238, 0.05);
}

#timeline-content .bi-tools {
    font-size: 1.5rem;
    color: var(--primary-color);
    background: var(--primary-light);
    padding: 0.75rem;
    border-radius: 10px;
    display: inline-block;
}

#timeline-content h5 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-top: 1rem;
}

#timeline-content p {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    line-height: 1.5;
}

#timeline-content small {
    font-size: 0.7rem;
    color: var(--text-muted);
    background: var(--surface-color);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    display: inline-block;
}

/* Timeline specific styles */
.timeline-wrapper {
    padding: 1rem;
}

.timeline-header {
    background: var(--background-color);
    border-radius: 10px;
    padding: 0.75rem;
    box-shadow: 0 2px 8px rgba(67, 97, 238, 0.05);
}

.timeline-user-avatar img {
    border: 2px solid var(--primary-light);
    padding: 2px;
}

.timeline-events {
    position: relative;
    padding-left: 1.5rem;
    margin-top: 1.5rem;
}

.timeline-events::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border-color);
    border-radius: 1px;
}

.timeline-event {
    position: relative;
    padding-bottom: 1.5rem;
    padding-left: 1.5rem;
}

.timeline-event::before {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 0;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--background-color);
    border: 2px solid var(--primary-color);
    z-index: 1;
    box-shadow: 0 0 0 4px var(--primary-light);
    transition: all 0.3s ease;
}

.timeline-event.event-in::before {
    background: var(--success-color);
    border-color: var(--success-color);
    box-shadow: 0 0 0 4px var(--success-light);
}

.timeline-event.event-out::before {
    background: var(--warning-color);
    border-color: var(--warning-color);
    box-shadow: 0 0 0 4px var(--warning-light);
}

.timeline-event:hover::before {
    transform: scale(1.2);
}

.timeline-event-content {
    background: var(--background-color);
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(67, 97, 238, 0.05);
    border: 1px solid var(--border-light);
    transition: all 0.3s ease;
}

.timeline-event-content:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.1);
    border-color: var(--primary-light);
}

.timeline-event-time {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-bottom: 0.25rem;
}

.timeline-event-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.timeline-event-details {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.timeline-event-location {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--border-color);
}

.timeline-event-location i {
    color: var(--primary-color);
    font-size: 0.8rem;
}

.timeline-event-location-text {
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.timeline-event-distance {
    font-size: 0.65rem;
    color: var(--text-muted);
    background: var(--surface-color);
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    margin-left: auto;
}
</style>