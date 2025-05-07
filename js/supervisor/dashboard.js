/**
 * Site Supervisor Dashboard JavaScript
 * Handles responsive sidebar functionality and other dashboard interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Cache DOM elements
    const leftPanel = document.getElementById('leftPanel');
    const mainContent = document.getElementById('mainContent');
    const toggleIcon = document.getElementById('toggleIcon');
    
    // Set up event listeners for checkboxes
    setupTaskCheckboxes();
    
    // Initialize any charts or data visualizations
    initializeCharts();
    
    // Initialize tooltips if using Bootstrap
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

/**
 * Toggle left panel for desktop view
 */
function togglePanel() {
    const panel = document.getElementById('leftPanel');
    const mainContent = document.getElementById('mainContent');
    const icon = document.getElementById('toggleIcon');
    
    panel.classList.toggle('collapsed');
    
    if (mainContent) {
        mainContent.classList.toggle('collapsed');
    }
    
    // Toggle icon direction
    icon.classList.toggle('fa-chevron-left');
    icon.classList.toggle('fa-chevron-right');
}

/**
 * Toggle left panel for mobile view
 */
function toggleMobilePanel() {
    const panel = document.getElementById('leftPanel');
    panel.classList.toggle('mobile-visible');
    
    // Add overlay for mobile when panel is visible
    if (panel.classList.contains('mobile-visible')) {
        createOverlay();
    } else {
        removeOverlay();
    }
}

/**
 * Create overlay when mobile menu is open
 */
function createOverlay() {
    // Remove existing overlay if any
    removeOverlay();
    
    // Create overlay element
    const overlay = document.createElement('div');
    overlay.id = 'mobileOverlay';
    overlay.className = 'mobile-overlay';
    overlay.classList.add('active');
    
    // Add click event to close menu when clicking overlay
    overlay.addEventListener('click', toggleMobilePanel);
    
    // Append to body
    document.body.appendChild(overlay);
}

/**
 * Remove mobile overlay
 */
function removeOverlay() {
    const existingOverlay = document.getElementById('mobileOverlay');
    if (existingOverlay) {
        existingOverlay.remove();
    }
}

/**
 * Handle window resize events for responsive behavior
 */
window.addEventListener('resize', function() {
    const panel = document.getElementById('leftPanel');
    const width = window.innerWidth;
    
    // On larger screens, remove mobile classes
    if (width > 768) {
        panel.classList.remove('mobile-visible');
        removeOverlay();
    }
});

/**
 * Setup task checkboxes to save state
 */
function setupTaskCheckboxes() {
    const checkboxes = document.querySelectorAll('.task-item input[type="checkbox"]');
    
    // Load saved states
    checkboxes.forEach(checkbox => {
        const savedState = localStorage.getItem(`task_${checkbox.id}`);
        if (savedState === 'true') {
            checkbox.checked = true;
            checkbox.closest('.task-item').classList.add('completed');
        }
        
        // Add change event listener
        checkbox.addEventListener('change', function() {
            // Save state to localStorage
            localStorage.setItem(`task_${this.id}`, this.checked);
            
            // Update UI
            if (this.checked) {
                this.closest('.task-item').classList.add('completed');
            } else {
                this.closest('.task-item').classList.remove('completed');
            }
        });
    });
}

/**
 * Initialize any charts or data visualizations
 * This is a placeholder for future chart implementations
 */
function initializeCharts() {
    // Placeholder for chart initialization
    // If using a library like Chart.js, this would set up any charts
    
    // Example (commented out as it requires Chart.js):
    /*
    if (typeof Chart !== 'undefined') {
        // Project Progress Chart
        const projectProgressCtx = document.getElementById('projectProgressChart');
        if (projectProgressCtx) {
            new Chart(projectProgressCtx, {
                type: 'bar',
                data: {
                    labels: ['Building A', 'Building B', 'Interior', 'Electrical'],
                    datasets: [{
                        label: 'Progress (%)',
                        data: [75, 45, 30, 60],
                        backgroundColor: [
                            'rgba(46, 204, 113, 0.7)',
                            'rgba(243, 156, 18, 0.7)',
                            'rgba(231, 76, 60, 0.7)',
                            'rgba(52, 152, 219, 0.7)'
                        ],
                        borderColor: [
                            'rgba(46, 204, 113, 1)',
                            'rgba(243, 156, 18, 1)',
                            'rgba(231, 76, 60, 1)',
                            'rgba(52, 152, 219, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }
    */
}

/**
 * Add notification to the system
 * @param {string} message - Notification message
 * @param {string} type - Type of notification (success, warning, danger, info)
 */
function addNotification(message, type = 'info') {
    // Check if notifications container exists, if not create it
    let notificationsContainer = document.querySelector('.notifications-container');
    
    if (!notificationsContainer) {
        notificationsContainer = document.createElement('div');
        notificationsContainer.className = 'notifications-container';
        notificationsContainer.style.position = 'fixed';
        notificationsContainer.style.top = '70px';
        notificationsContainer.style.right = '20px';
        notificationsContainer.style.zIndex = '9999';
        document.body.appendChild(notificationsContainer);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${getIconForType(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">×</button>
    `;
    
    // Style notification
    notification.style.backgroundColor = getColorForType(type);
    notification.style.color = '#fff';
    notification.style.padding = '12px 15px';
    notification.style.borderRadius = '5px';
    notification.style.marginBottom = '10px';
    notification.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
    notification.style.display = 'flex';
    notification.style.justifyContent = 'space-between';
    notification.style.alignItems = 'center';
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(50px)';
    notification.style.transition = 'all 0.3s ease';
    
    // Add to container
    notificationsContainer.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Add close button functionality
    const closeButton = notification.querySelector('.notification-close');
    closeButton.style.background = 'none';
    closeButton.style.border = 'none';
    closeButton.style.color = '#fff';
    closeButton.style.fontSize = '20px';
    closeButton.style.cursor = 'pointer';
    
    closeButton.addEventListener('click', () => {
        removeNotification(notification);
    });
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        removeNotification(notification);
    }, 5000);
}

/**
 * Remove a notification with animation
 * @param {HTMLElement} notification - The notification element to remove
 */
function removeNotification(notification) {
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(50px)';
    
    setTimeout(() => {
        notification.remove();
    }, 300);
}

/**
 * Get icon class for notification type
 * @param {string} type - Notification type
 * @returns {string} - FontAwesome icon class
 */
function getIconForType(type) {
    switch (type) {
        case 'success': return 'fa-check-circle';
        case 'warning': return 'fa-exclamation-triangle';
        case 'danger': return 'fa-times-circle';
        case 'info': 
        default: return 'fa-info-circle';
    }
}

/**
 * Get color for notification type
 * @param {string} type - Notification type
 * @returns {string} - CSS color value
 */
function getColorForType(type) {
    switch (type) {
        case 'success': return '#2ecc71';
        case 'warning': return '#f39c12';
        case 'danger': return '#e74c3c';
        case 'info': 
        default: return '#3498db';
    }
} 