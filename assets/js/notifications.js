class NotificationManager {
    constructor() {
        this.panel = document.getElementById('notificationPanel');
        this.list = document.getElementById('notificationList');
        this.count = document.getElementById('notificationCount');
        this.initializeEventListeners();
        this.activeTab = 'all';
    }

    initializeEventListeners() {
        // Initialize tab switching
        document.querySelectorAll('.notification-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => this.switchTab(btn.dataset.tab));
        });

        // Clear all button
        document.querySelector('.clear-all').addEventListener('click', () => this.clearAll());
    }

    switchTab(tabName) {
        this.activeTab = tabName;
        // Update active tab button
        document.querySelectorAll('.notification-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
        // Filter notifications
        this.filterNotifications(tabName);
    }

    filterNotifications(type) {
        const items = this.list.querySelectorAll('.notification-item');
        items.forEach(item => {
            if (type === 'all' || item.dataset.type === type) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    addNotification(data) {
        const item = this.createNotificationElement(data);
        this.list.insertBefore(item, this.list.firstChild);
        this.updateCount(1);
        
        // Show if matches current filter
        if (this.activeTab !== 'all' && this.activeTab !== data.type) {
            item.style.display = 'none';
        }
    }

    createNotificationElement(data) {
        const item = document.createElement('div');
        item.className = 'notification-item unread';
        item.dataset.type = data.type;
        
        let detailsHTML = '';
        switch (data.type) {
            case 'attendance':
                detailsHTML = this.createAttendanceDetails(data);
                break;
            case 'leave':
                detailsHTML = this.createLeaveDetails(data);
                break;
            // Add more cases for different notification types
        }

        item.innerHTML = detailsHTML;
        this.attachDetailToggle(item);
        return item;
    }

    createAttendanceDetails(data) {
        return `
            <div class="notification-content">
                <div class="notification-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="notification-text">
                    <div class="notification-title">${data.employee_name} ${data.action}</div>
                    <div class="notification-time">${this.formatTimeAgo(data.timestamp)}</div>
                </div>
                <button class="details-toggle">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="notification-details hidden">
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span>Time: ${this.formatTime(data.punch_time)}</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-id-card"></i>
                    <span>Employee ID: ${data.employee_id}</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Location: ${data.location || 'Office'}</span>
                </div>
            </div>
        `;
    }

    createLeaveDetails(data) {
        return `
            <div class="notification-content">
                <div class="notification-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="notification-text">
                    <div class="notification-title">${data.message}</div>
                    <div class="notification-time">${this.formatTimeAgo(data.timestamp)}</div>
                </div>
                <button class="details-toggle">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="notification-details hidden">
                <div class="detail-item">
                    <i class="fas fa-user"></i>
                    <span>Employee: ${data.employee_name}</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <span>Duration: ${this.formatDateRange(data.start_date, data.end_date)}</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-info-circle"></i>
                    <span>Type: ${data.leave_type}</span>
                </div>
            </div>
        `;
    }

    attachDetailToggle(item) {
        const toggleBtn = item.querySelector('.details-toggle');
        const details = item.querySelector('.notification-details');
        if (toggleBtn && details) {
            toggleBtn.addEventListener('click', () => {
                details.classList.toggle('hidden');
                toggleBtn.querySelector('i').classList.toggle('fa-chevron-up');
                toggleBtn.querySelector('i').classList.toggle('fa-chevron-down');
            });
        }
    }

    updateCount(increment) {
        const currentCount = parseInt(this.count.textContent) || 0;
        this.count.textContent = currentCount + increment;
        this.count.classList.toggle('has-new', (currentCount + increment) > 0);
    }

    clearAll() {
        this.list.innerHTML = '';
        this.count.textContent = '0';
        this.count.classList.remove('has-new');
    }

    formatTimeAgo(timestamp) {
        const now = new Date();
        const date = new Date(timestamp);
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        return date.toLocaleDateString();
    }

    formatTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    formatDateRange(start, end) {
        const startDate = new Date(start).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
        const endDate = new Date(end).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
        return `${startDate} - ${endDate}`;
    }

    togglePanel() {
        const panel = this.panel;
        if (panel.classList.contains('show')) {
            panel.classList.remove('show');
        } else {
            panel.classList.add('show');
            // Mark notifications as read when opening
            this.markAllAsRead();
        }
    }

    markAllAsRead() {
        const unreadItems = this.list.querySelectorAll('.notification-item.unread');
        unreadItems.forEach(item => {
            item.classList.remove('unread');
        });
        this.updateCount(0); // Reset counter
    }
}

// Initialize notification manager when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // First, let's make sure we can find the notification elements
    const notificationIcon = document.querySelector('.notification-icon'); // Update selector to match your bell icon
    const notificationPanel = document.getElementById('notificationPanel');
    
    if (!notificationIcon || !notificationPanel) {
        console.error('Notification elements not found!');
        return;
    }

    // Toggle notification panel
    notificationIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationPanel.classList.toggle('show');
        console.log('Notification icon clicked'); // Debug log
    });

    // Close panel when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationPanel.contains(e.target) && !notificationIcon.contains(e.target)) {
            notificationPanel.classList.remove('show');
        }
    });

    // For testing purposes, let's add some dummy notifications
    const dummyNotifications = [
        {
            id: 1,
            type: 'attendance',
            title: 'New Attendance Request',
            message: 'John Doe has requested attendance correction',
            timestamp: new Date(Date.now() - 1000 * 60 * 30).toISOString(), // 30 minutes ago
            read: false
        },
        {
            id: 2,
            type: 'leave',
            title: 'Leave Application',
            message: 'Jane Smith has applied for annual leave',
            timestamp: new Date(Date.now() - 1000 * 60 * 60).toISOString(), // 1 hour ago
            read: false
        }
    ];

    function renderNotifications(notifications = dummyNotifications) {
        const notificationList = document.getElementById('notificationList');
        if (!notificationList) return;

        notificationList.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-content">
                    <div class="notification-icon">
                        <i class="fas ${getNotificationIcon(notification.type)}"></i>
                    </div>
                    <div class="notification-details">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-meta">
                            <span class="notification-time">
                                <i class="far fa-clock"></i>
                                ${formatTimeAgo(notification.timestamp)}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function getNotificationIcon(type) {
        const icons = {
            attendance: 'fa-user-clock',
            leave: 'fa-calendar-alt',
            task: 'fa-tasks',
            default: 'fa-bell'
        };
        return icons[type] || icons.default;
    }

    function formatTimeAgo(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        let interval = Math.floor(seconds / 31536000);
        if (interval > 1) return interval + ' years ago';
        
        interval = Math.floor(seconds / 2592000);
        if (interval > 1) return interval + ' months ago';
        
        interval = Math.floor(seconds / 86400);
        if (interval > 1) return interval + ' days ago';
        
        interval = Math.floor(seconds / 3600);
        if (interval > 1) return interval + ' hours ago';
        
        interval = Math.floor(seconds / 60);
        if (interval > 1) return interval + ' minutes ago';
        
        return 'just now';
    }

    // Initial render
    renderNotifications();
}); 