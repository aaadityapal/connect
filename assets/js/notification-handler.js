/**
 * Notification System
 * Handles the fetching, displaying, and interaction with notifications
 */
class NotificationSystem {
    constructor() {
        // DOM elements
        this.notificationIcon = document.querySelector('.notification-icon');
        this.notificationBadge = document.querySelector('.notification-badge');
        this.notificationModal = document.getElementById('notificationModal');
        this.notificationList = document.getElementById('notificationList');
        this.closeNotificationBtn = document.getElementById('closeNotificationBtn');
        this.markAllReadBtn = document.getElementById('markAllReadBtn');
        
        // State
        this.isModalOpen = false;
        this.notifications = [];
        this.unreadCount = 0;
        
        // Tab elements
        this.tabButtons = document.querySelectorAll('.tab-btn');
        this.currentTab = 'all'; // Default tab
        
        // Initialize
        this.init();
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (this.isModalOpen) {
                this.positionModal();
            }
        });
        
        // Tab handling
        this.tabs = document.querySelectorAll('.tab-btn');
        if (this.tabs) {
            this.tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabType = tab.dataset.tab;
                    this.switchTab(tabType);
                });
            });
        }
    }
    
    init() {
        // Attach event listeners
        this.attachEventListeners();
        
        // Initial fetch of notification count
        this.fetchNotificationCount();
        
        // Set up polling for new notifications
        this.startPolling();
    }
    
    attachEventListeners() {
        // Toggle notification modal
        this.notificationIcon.addEventListener('click', () => {
            if (this.isModalOpen) {
                this.closeModal();
            } else {
                this.openModal();
            }
        });
        
        // Close modal on click outside
        document.addEventListener('click', (e) => {
            if (this.isModalOpen && 
                !this.notificationModal.contains(e.target) && 
                !this.notificationIcon.contains(e.target)) {
                this.closeModal();
            }
        });
        
        // Close button
        this.closeNotificationBtn.addEventListener('click', () => {
            this.closeModal();
        });
        
        // Mark all as read
        this.markAllReadBtn.addEventListener('click', () => {
            this.markAllAsRead();
        });
        
        // Tab switching
        this.tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabType = button.dataset.tab;
                this.switchTab(tabType);
            });
        });
    }
    
    openModal() {
        // Create backdrop if it doesn't exist
        if (!document.querySelector('.notification-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'notification-backdrop';
            document.body.appendChild(backdrop);
        }
        
        // Show backdrop
        document.querySelector('.notification-backdrop').style.display = 'block';
        
        // Show modal with animation
        this.notificationModal.style.display = 'block';
        this.isModalOpen = true;
        this.fetchNotifications();
        
        // Add subtle entrance effect to the modal
        this.notificationModal.style.animation = 'notificationPopIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)';
        
        // Position the notification modal relative to the notification icon
        this.positionModal();
        
        // Activate the last selected tab
        this.tabButtons.forEach(btn => {
            if (btn.dataset.tab === this.currentTab) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        
        // Set default tab if none is active
        const activeTab = document.querySelector('.tab-btn.active');
        if (!activeTab) {
            this.switchTab('all');
        }
    }
    
    closeModal() {
        // Hide backdrop
        const backdrop = document.querySelector('.notification-backdrop');
        if (backdrop) {
            backdrop.style.display = 'none';
        }
        
        // Add exit animation
        this.notificationModal.style.animation = 'notificationPopOut 0.2s ease forwards';
        
        // Hide modal after animation completes
        setTimeout(() => {
            this.notificationModal.style.display = 'none';
            this.isModalOpen = false;
        }, 200);
    }
    
    positionModal() {
        // Get the position of the notification icon
        const iconRect = this.notificationIcon.getBoundingClientRect();
        
        // Calculate the ideal position - centered below the icon
        const modalRight = window.innerWidth - iconRect.right + (iconRect.width / 2);
        
        // Apply position
        this.notificationModal.style.right = `${modalRight}px`;
    }
    
    async fetchNotificationCount() {
        try {
            const response = await fetch('assets/api/combined-notifications.php?count_only=1');
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('Detailed notification counts:', data.debug_counts);
                const count = parseInt(data.count) || 0;
                this.updateNotificationBadge(count);
            }
        } catch (error) {
            console.error('Error fetching notification count:', error);
            this.updateNotificationBadge(0);
        }
    }
    
    async fetchNotifications() {
        try {
            // Show loading state
            this.notificationList.innerHTML = `
                <div class="notification-empty loading">
                    <i class="fas fa-spinner fa-pulse"></i>
                    <p>Loading notifications...</p>
                </div>
            `;
            
            // Get the active tab (all, unread, or assignments)
            const activeTab = document.querySelector('.tab-btn.active');
            let filter = 'all';
            
            if (activeTab) {
                if (activeTab.dataset.tab === 'unread') {
                    filter = 'unread';
                } else if (activeTab.dataset.tab === 'assignments') {
                    filter = 'assignments';
                }
            }
            
            // Fetch notifications with filter if needed
            let url = 'assets/api/combined-notifications.php';
            if (filter !== 'all') {
                url += `?filter=${filter}`;
            }
            
            console.log('Fetching notifications with URL:', url);
            
            const response = await fetch(url);
            const responseText = await response.text();
            
            console.log('Raw API response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
                console.log('Parsed API response:', data);
            } catch (parseError) {
                console.error('Error parsing response:', parseError);
                this.showErrorState('Invalid JSON response: ' + responseText.substring(0, 100) + '...');
                return;
            }
            
            if (data.status === 'success') {
                this.notifications = data.notifications;
                this.renderNotifications();
                console.log('Loaded notifications:', this.notifications.length);
            } else {
                console.log('No notifications found. Status:', data.status);
                this.showEmptyState();
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
            this.showErrorState(error.message);
        }
    }
    
    renderNotifications() {
        if (!this.notifications || this.notifications.length === 0) {
            this.showEmptyState();
            return;
        }

        let html = '';
        
        this.notifications.forEach(notification => {
            // Get expiration date based on notification type
            let expirationDate = this.getExpirationDate(notification);
            let expirationText = expirationDate ? `${expirationDate}` : '';
            
            // Determine read status class
            const readStatusClass = parseInt(notification.read_status) === 1 ? 'read' : '';
            
            html += `
                <div class="notification-item ${readStatusClass}" 
                     data-id="${notification.source_id}" 
                     data-type="${notification.source_type}">
                    <div class="notification-icon ${notification.type}">
                        <i class="${notification.icon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-meta">
                            <span class="notification-time">${notification.time_display || 'Just now'}</span>
                            ${expirationText ? `<span class="notification-expiry">${expirationText}</span>` : ''}
                            <span class="source-badge ${notification.source_type}">${this.getSourceLabel(notification.source_type)}</span>
                        </div>
                    </div>
                </div>
            `;
        });

        this.notificationList.innerHTML = html;
        
        // Add click handlers
        const items = document.querySelectorAll('.notification-item');
        items.forEach(item => {
            item.addEventListener('click', () => {
                const id = item.dataset.id;
                const type = item.dataset.type;
                const notification = this.notifications.find(n => n.source_id == id && n.source_type == type);
                if (notification) {
                    this.handleNotificationClick(notification);
                }
            });
        });
    }
    
    showEmptyState() {
        // Get the active tab
        const activeTab = document.querySelector('.tab-btn.active');
        const tabType = activeTab ? activeTab.dataset.tab : 'all';
        
        // Show different empty state based on tab
        if (tabType === 'assignments') {
            this.notificationList.innerHTML = `
                <div class="notification-empty assignments-empty">
                    <i class="fas fa-tasks"></i>
                    <p>No assignment notifications yet</p>
                    <p class="empty-subtitle">When you're assigned to projects, stages, or tasks, they'll appear here</p>
                </div>
            `;
        } else if (tabType === 'unread') {
            this.notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="far fa-check-circle"></i>
                    <p>No unread notifications</p>
                </div>
            `;
        } else {
            this.notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            `;
        }
    }
    
    showErrorState(message = '') {
        this.notificationList.innerHTML = `
            <div class="notification-empty error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Failed to load notifications</p>
                ${message ? `<p class="error-details">${message}</p>` : ''}
            </div>
        `;
    }
    
    async handleNotificationClick(notification) {
        // Mark as read if not already read
        if (!notification.read_status) {
            try {
                // Find the DOM element for this notification
                const notificationElement = document.querySelector(`.notification-item[data-id="${notification.source_id}"][data-type="${notification.source_type}"]`);
                
                // Mark as read visually immediately
                if (notificationElement) {
                    notificationElement.classList.add('read');
                }
                
                // Update in database
                const response = await fetch('assets/api/notification-status-updater.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'mark_read',
                        notification_type: notification.source_type,
                        source_id: notification.source_id
                    })
                });

                const data = await response.json();
                if (data.status === 'success') {
                    // Update in memory
                    notification.read_status = 1;
                    
                    // Get current unread count and update badge
                    await this.fetchNotificationCount();
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }
        
        // Show notification detail modal
        this.showNotificationDetail(notification);
    }
    
    async showNotificationDetail(notification) {
        try {
            // Fetch full details if needed
            const detailedNotification = await this.fetchNotificationDetail(notification);
            
            // Create detail modal if it doesn't exist
            if (!this.detailModal) {
                this.createDetailModal();
            }
            
            // Populate the modal with notification details
            this.populateDetailModal(detailedNotification || notification);
            
            // Show the modal
            this.detailModal.style.display = 'block';
        } catch (error) {
            console.error('Error showing notification detail:', error);
        }
    }
    
    createDetailModal() {
        const modal = document.createElement('div');
        modal.className = 'notification-detail-modal';
        modal.innerHTML = `
            <div class="detail-modal-content">
                <div class="detail-modal-header">
                    <h3 class="detail-title"></h3>
                    <button class="close-detail-btn">&times;</button>
                </div>
                <div class="detail-modal-body">
                    <div class="detail-meta">
                        <span class="detail-source"></span>
                        <span class="detail-date"><i class="fas fa-clock"></i> <span class="date-text"></span></span>
                        <span class="detail-expiry"><i class="fas fa-hourglass-end"></i> <span class="expiry-text"></span></span>
                    </div>
                    <div class="detail-message"></div>
                    <div class="detail-content"></div>
                </div>
            </div>
        `;
        
        // Add event listeners
        const closeBtn = modal.querySelector('.close-detail-btn');
        closeBtn.addEventListener('click', () => {
            this.detailModal.style.display = 'none';
        });
        
        // Close when clicking outside modal or pressing ESC
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.detailModal.style.display = 'none';
            }
        });
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.detailModal && this.detailModal.style.display === 'block') {
                this.detailModal.style.display = 'none';
            }
        });
        
        // Add to document
        document.body.appendChild(modal);
        this.detailModal = modal;
    }
    
    populateDetailModal(notification) {
        // Set basic information
        this.detailModal.querySelector('.detail-title').textContent = notification.title;
        this.detailModal.querySelector('.detail-message').textContent = notification.message;
        
        // Set metadata with enhanced source badge
        this.detailModal.querySelector('.detail-source').innerHTML = `
            <span class="source-badge ${notification.source_type}">
                <i class="${this.getSourceIcon(notification.source_type)}"></i> 
                ${this.getSourceLabel(notification.source_type)}
            </span>
        `;
        
        // Format date nicely
        const date = new Date(notification.created_at);
        const formattedDate = date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
        const formattedTime = date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        
        this.detailModal.querySelector('.date-text').textContent = 
            `Posted: ${formattedDate} at ${formattedTime}`;
        
        // Set expiration if available
        const expiryElem = this.detailModal.querySelector('.detail-expiry');
        const expiryTextElem = expiryElem.querySelector('.expiry-text');
        
        if (notification.expiration_date) {
            const expiryDate = new Date(notification.expiration_date);
            const formattedExpiryDate = expiryDate.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
            expiryTextElem.textContent = `Expires on ${formattedExpiryDate}`;
            expiryElem.style.display = 'flex';
        } else {
            expiryElem.style.display = 'none';
        }
        
        // Additional content based on notification type
        const contentElem = this.detailModal.querySelector('.detail-content');
        
        // If we have detailed content, display it
        if (notification.detailed_content) {
            contentElem.innerHTML = notification.detailed_content;
            contentElem.style.display = 'block';
            
            // Check for attachment (specifically for circulars)
            if (notification.source_type === 'circular' && notification.file_attachment) {
                // Create attachment element
                const attachmentHTML = `
                    <div class="attachment-section">
                        <p>
                            <strong>Attachment:</strong> 
                            <a href="${notification.file_attachment}" target="_blank">
                                <i class="fas fa-download"></i> ${this.getFileName(notification.file_attachment)}
                            </a>
                        </p>
                    </div>
                `;
                contentElem.innerHTML += attachmentHTML;
            }
        } else {
            // Even if no detailed content, still show attachment for circulars
            if (notification.source_type === 'circular' && notification.file_attachment) {
                const attachmentHTML = `
                    <div class="attachment-section">
                        <p>
                            <strong>Attachment:</strong> 
                            <a href="${notification.file_attachment}" target="_blank">
                                <i class="fas fa-download"></i> ${this.getFileName(notification.file_attachment)}
                            </a>
                        </p>
                    </div>
                `;
                contentElem.innerHTML = attachmentHTML;
                contentElem.style.display = 'block';
            } else {
                contentElem.style.display = 'none';
            }
        }
    }
    
    async fetchNotificationDetail(notification) {
        try {
            const response = await fetch(`assets/api/notification-detail.php?type=${notification.source_type}&id=${notification.source_id}`);
            const text = await response.text();
            
            // Debug log
            console.log('Notification detail API response:', text);
            
            try {
                const data = JSON.parse(text);
                
                if (data.status === 'success') {
                    console.log('Parsed notification details:', data.notification);
                    return { ...notification, ...data.notification };
                }
            } catch (parseError) {
                console.error('Error parsing notification detail response:', parseError);
            }
            
            return notification;
        } catch (error) {
            console.error('Error fetching notification details:', error);
            return notification;
        }
    }
    
    async markAllAsRead() {
        try {
            const response = await fetch('assets/api/notification-status-updater.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            });

            const data = await response.json();
            if (data.status === 'success') {
                // Update UI immediately
                this.notifications.forEach(notification => {
                    notification.read_status = true;
                });
                
                // Update badge count
                this.updateNotificationBadge(0);
                
                // Refresh the notifications list
                await this.fetchNotifications();
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }
    
    showConfirmationMessage(message) {
        // Create a temporary message that fades out
        const messageElement = document.createElement('div');
        messageElement.className = 'notification-confirmation';
        messageElement.style.cssText = `
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #4caf50;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1001;
        `;
        messageElement.textContent = message;
        
        document.body.appendChild(messageElement);
        
        // Fade in
        setTimeout(() => {
            messageElement.style.opacity = '1';
        }, 10);
        
        // Fade out and remove
        setTimeout(() => {
            messageElement.style.opacity = '0';
            setTimeout(() => {
                document.body.removeChild(messageElement);
            }, 300);
        }, 2000);
    }
    
    updateBadge() {
        if (this.unreadCount > 0) {
            this.notificationBadge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            this.notificationBadge.style.display = 'flex';
        } else {
            this.notificationBadge.style.display = 'none';
        }
    }
    
    startPolling() {
        // Poll for new notifications every 30 seconds
        setInterval(() => {
            this.fetchNotificationCount();
            
            // If modal is open, refresh the notifications
            if (this.isModalOpen) {
                this.fetchNotifications();
            }
        }, 30000);
    }
    
    getSourceLabel(sourceType) {
        switch (sourceType) {
            case 'announcement':
                return '<span class="source-badge announcement">Announcement</span>';
            case 'circular':
                return '<span class="source-badge circular">Circular</span>';
            case 'event':
                return '<span class="source-badge event">Event</span>';
            case 'holiday':
                return '<span class="source-badge holiday">Holiday</span>';
            case 'project':
                return '<span class="source-badge project">Project</span>';
            case 'stage':
                return '<span class="source-badge stage">Stage</span>';
            case 'substage':
                return '<span class="source-badge substage">Task</span>';
            default:
                return '';
        }
    }
    
    switchTab(tabType) {
        // Update active tab
        const tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(tab => {
            if (tab.dataset.tab === tabType) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        // Fetch notifications based on new tab
        this.fetchNotifications();
    }

    updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'block';
            } else {
                badge.textContent = '';
                badge.style.display = 'none';
            }
        }
    }

    getExpirationDate(notification) {
        if (!notification.expiration_date) {
            return null;
        }

        // Format the date
        try {
            const date = new Date(notification.expiration_date);
            
            // If invalid date, return null
            if (isNaN(date.getTime())) {
                return null;
            }
            
            // Get today's date without time
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Get tomorrow's date
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            // Format date based on how soon it is
            if (date.getTime() === today.getTime()) {
                return "Expires today";
            } else if (date.getTime() === tomorrow.getTime()) {
                return "Expires tomorrow";
            } else {
                // Format as Month Day, Year
                const options = { month: 'short', day: 'numeric', year: 'numeric' };
                return `Expires on ${date.toLocaleDateString(undefined, options)}`;
            }
        } catch (e) {
            console.error("Error formatting date:", e);
            return null;
        }
    }

    getSourceIcon(sourceType) {
        switch (sourceType) {
            case 'announcement':
                return 'fas fa-bullhorn';
            case 'circular':
                return 'fas fa-file-alt';
            case 'event':
                return 'fas fa-calendar-day';
            case 'holiday':
                return 'fas fa-calendar-check';
            case 'project':
                return 'fas fa-project-diagram';
            case 'stage':
                return 'fas fa-layer-group';
            case 'substage':
                return 'fas fa-tasks';
            case 'assignment_project':
                return 'fas fa-user-plus';
            case 'assignment_stage':
                return 'fas fa-user-tag';
            case 'assignment_substage':
                return 'fas fa-user-check';
            default:
                return 'fas fa-bell';
        }
    }

    // Add this helper method to extract filename from full path
    getFileName(path) {
        if (!path) return 'Download';
        
        // Check if path contains slashes
        if (path.includes('/')) {
            return path.split('/').pop();
        } 
        // Check if path contains backslashes
        else if (path.includes('\\')) {
            return path.split('\\').pop();
        }
        
        // If no slashes, return the original string
        return path;
    }
}

// Initialize the notification system when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Add the notification modal to DOM if not already present
    if (!document.getElementById('notificationModal')) {
        // Fetch the modal HTML via AJAX and append to body
        fetch('components/notification-modal.php')
            .then(response => response.text())
            .then(html => {
                document.body.insertAdjacentHTML('beforeend', html);
                
                // Initialize after modal is added
                window.notificationSystem = new NotificationSystem();
                
                // Add custom styling based on the dashboard theme
                applyCustomStyling();
            })
            .catch(error => {
                console.error('Error loading notification modal:', error);
            });
    } else {
        // Modal already exists, just initialize
        window.notificationSystem = new NotificationSystem();
        applyCustomStyling();
    }
});

// Function to apply custom styling based on dashboard theme
function applyCustomStyling() {
    // Get the primary color from the dashboard theme
    const computedStyle = getComputedStyle(document.documentElement);
    const primaryColor = computedStyle.getPropertyValue('--primary-color') || '#3498db';
    const secondaryColor = computedStyle.getPropertyValue('--secondary-color') || '#2c3e50';
    
    // Apply custom styling to notification elements
    document.querySelectorAll('.tab-btn.active:after').forEach(el => {
        el.style.background = primaryColor;
    });
    
    document.querySelectorAll('.notification-modal-header').forEach(el => {
        el.style.background = `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`;
    });
    
    document.querySelectorAll('.notification-footer a').forEach(el => {
        el.style.color = primaryColor;
    });
    
    document.querySelectorAll('.notification-item.unread::after').forEach(el => {
        el.style.backgroundColor = primaryColor;
    });
} 