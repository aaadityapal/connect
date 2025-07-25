<!-- Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalLabel">Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="notificationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="unread-tab" data-bs-toggle="tab" data-bs-target="#unread" type="button" role="tab" aria-controls="unread" aria-selected="true">
                            Unread <span class="badge bg-danger unread-count">0</span>
            </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="false">
                            All
            </button>
                    </li>
                </ul>
                <div class="tab-content mt-3" id="notificationTabContent">
                    <div class="tab-pane fade show active" id="unread" role="tabpanel" aria-labelledby="unread-tab">
                        <div id="unread-notifications">
                            <div class="text-center py-4 loading-spinner">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading notifications...</p>
                            </div>
                            <div class="no-notifications text-center py-4" style="display: none;">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p>You have no unread notifications.</p>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="all" role="tabpanel" aria-labelledby="all-tab">
                        <div id="all-notifications">
                            <div class="text-center py-4 loading-spinner">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading notifications...</p>
                            </div>
                            <div class="no-notifications text-center py-4" style="display: none;">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <p>You have no notifications.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="markAllReadBtn">Mark All as Read</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.notification-item {
    padding: 15px;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #f0f7ff;
}

.notification-item .notification-title {
    font-weight: 600;
    margin-bottom: 5px;
}

.notification-item .notification-content {
    color: #555;
    margin-bottom: 5px;
}

.notification-item .notification-time {
    color: #888;
    font-size: 0.85rem;
}

.notification-item .notification-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 0.75rem;
    margin-right: 8px;
}

.notification-badge.attendance {
    background-color: #e3f2fd;
    color: #0d6efd;
}

.notification-badge.approval {
    background-color: #fff3cd;
    color: #856404;
}

.notification-item .notification-actions {
    margin-top: 10px;
}

.notification-actions .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.notification-icon.attendance_approval {
    background-color: #fff3cd;
    color: #856404;
}

.notification-icon.attendance_approved {
    background-color: #d4edda;
    color: #155724;
}

.notification-icon.attendance_rejected {
    background-color: #f8d7da;
    color: #721c24;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to load notifications
    function loadNotifications() {
        // Load unread notifications
        fetch('ajax_handlers/get_notifications.php?type=unread')
            .then(response => response.json())
            .then(data => {
                const unreadContainer = document.getElementById('unread-notifications');
                unreadContainer.querySelector('.loading-spinner').style.display = 'none';
                
                if (data.notifications && data.notifications.length > 0) {
                    // Update unread count
                    const unreadCount = document.querySelector('.unread-count');
                    unreadCount.textContent = data.notifications.length;
                    
                    // Create notification items
                    const notificationsList = document.createElement('div');
                    notificationsList.className = 'notifications-list';
                    
                    data.notifications.forEach(notification => {
                        const notificationItem = createNotificationItem(notification, true);
                        notificationsList.appendChild(notificationItem);
                    });
                    
                    unreadContainer.appendChild(notificationsList);
                } else {
                    unreadContainer.querySelector('.no-notifications').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error loading unread notifications:', error);
                const unreadContainer = document.getElementById('unread-notifications');
                unreadContainer.querySelector('.loading-spinner').style.display = 'none';
                unreadContainer.innerHTML += '<div class="alert alert-danger">Failed to load notifications. Please try again later.</div>';
            });
        
        // Load all notifications
        fetch('ajax_handlers/get_notifications.php?type=all')
            .then(response => response.json())
            .then(data => {
                const allContainer = document.getElementById('all-notifications');
                allContainer.querySelector('.loading-spinner').style.display = 'none';
                
                if (data.notifications && data.notifications.length > 0) {
                    // Create notification items
                    const notificationsList = document.createElement('div');
                    notificationsList.className = 'notifications-list';
                    
                    data.notifications.forEach(notification => {
                        const notificationItem = createNotificationItem(notification, false);
                        notificationsList.appendChild(notificationItem);
                    });
                    
                    allContainer.appendChild(notificationsList);
                } else {
                    allContainer.querySelector('.no-notifications').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error loading all notifications:', error);
                const allContainer = document.getElementById('all-notifications');
                allContainer.querySelector('.loading-spinner').style.display = 'none';
                allContainer.innerHTML += '<div class="alert alert-danger">Failed to load notifications. Please try again later.</div>';
            });
    }
    
    // Function to create a notification item
    function createNotificationItem(notification, isUnread) {
        const item = document.createElement('div');
        item.className = 'notification-item d-flex' + (isUnread ? ' unread' : '');
        item.dataset.id = notification.id;
        
        // Determine icon based on notification type
        let iconClass = 'fas fa-bell';
        if (notification.type === 'attendance_approval') {
            iconClass = 'fas fa-clipboard-check';
        } else if (notification.type === 'attendance_approved') {
            iconClass = 'fas fa-check-circle';
        } else if (notification.type === 'attendance_rejected') {
            iconClass = 'fas fa-times-circle';
        }
        
        // Create notification content
        const iconDiv = document.createElement('div');
        iconDiv.className = 'notification-icon ' + notification.type;
        iconDiv.innerHTML = `<i class="${iconClass}"></i>`;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'notification-content-wrapper flex-grow-1';
        
        // Format the notification time
        const notificationTime = new Date(notification.created_at);
        const timeFormatted = formatTimeAgo(notificationTime);
        
        contentDiv.innerHTML = `
            <div class="notification-title">
                ${notification.title}
                ${isUnread ? '<span class="badge bg-primary ms-2">New</span>' : ''}
    </div>
            <div class="notification-content">${notification.content}</div>
            <div class="notification-time">${timeFormatted}</div>
            ${notification.link ? `
            <div class="notification-actions">
                <a href="${notification.link}" class="btn btn-sm btn-primary">View Details</a>
                <button class="btn btn-sm btn-outline-secondary mark-read-btn" data-id="${notification.id}">
                    Mark as Read
                </button>
            </div>` : ''}
        `;
        
        item.appendChild(iconDiv);
        item.appendChild(contentDiv);
        
        // Add event listener for mark as read button
        setTimeout(() => {
            const markReadBtn = item.querySelector('.mark-read-btn');
            if (markReadBtn) {
                markReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    markAsRead(notification.id);
                });
            }
        }, 0);
        
        return item;
    }
    
    // Function to mark a notification as read
    function markAsRead(notificationId) {
        fetch('ajax_handlers/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove notification from unread tab
                const unreadItem = document.querySelector(`#unread-notifications .notification-item[data-id="${notificationId}"]`);
                if (unreadItem) {
                    unreadItem.remove();
                }
                
                // Update unread count
                const unreadCount = document.querySelector('.unread-count');
                const currentCount = parseInt(unreadCount.textContent);
                unreadCount.textContent = Math.max(0, currentCount - 1);
                
                // Show "no notifications" message if no more unread notifications
                const unreadContainer = document.getElementById('unread-notifications');
                const unreadItems = unreadContainer.querySelectorAll('.notification-item');
                if (unreadItems.length === 0) {
                    unreadContainer.querySelector('.no-notifications').style.display = 'block';
                }
                
                // Update the notification in the "all" tab
                const allItem = document.querySelector(`#all-notifications .notification-item[data-id="${notificationId}"]`);
                if (allItem) {
                    allItem.classList.remove('unread');
                    const badge = allItem.querySelector('.badge.bg-primary');
                    if (badge) {
                        badge.remove();
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    // Function to mark all notifications as read
    function markAllAsRead() {
        fetch('ajax_handlers/mark_all_notifications_read.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear unread notifications
                const unreadContainer = document.getElementById('unread-notifications');
                const notificationsList = unreadContainer.querySelector('.notifications-list');
                if (notificationsList) {
                    notificationsList.remove();
                }
                
                // Show "no notifications" message
                unreadContainer.querySelector('.no-notifications').style.display = 'block';
                
                // Update unread count
                const unreadCount = document.querySelector('.unread-count');
                unreadCount.textContent = '0';
                
                // Remove "unread" class and badges from all notifications
                const allItems = document.querySelectorAll('#all-notifications .notification-item');
                allItems.forEach(item => {
                    item.classList.remove('unread');
                    const badge = item.querySelector('.badge.bg-primary');
                    if (badge) {
                        badge.remove();
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
    }
    
    // Format time ago function
    function formatTimeAgo(date) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) {
            return 'just now';
        }
        
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        if (diffInMinutes < 60) {
            return diffInMinutes + ' minute' + (diffInMinutes > 1 ? 's' : '') + ' ago';
        }
        
        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) {
            return diffInHours + ' hour' + (diffInHours > 1 ? 's' : '') + ' ago';
        }
        
        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 30) {
            return diffInDays + ' day' + (diffInDays > 1 ? 's' : '') + ' ago';
        }
        
        const diffInMonths = Math.floor(diffInDays / 30);
        if (diffInMonths < 12) {
            return diffInMonths + ' month' + (diffInMonths > 1 ? 's' : '') + ' ago';
        }
        
        const diffInYears = Math.floor(diffInMonths / 12);
        return diffInYears + ' year' + (diffInYears > 1 ? 's' : '') + ' ago';
    }
    
    // Load notifications when the modal is shown
    const notificationModal = document.getElementById('notificationModal');
    if (notificationModal) {
        notificationModal.addEventListener('show.bs.modal', function() {
            // Clear previous notifications
            document.getElementById('unread-notifications').innerHTML = `
                <div class="text-center py-4 loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading notifications...</p>
        </div>
                <div class="no-notifications text-center py-4" style="display: none;">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p>You have no unread notifications.</p>
    </div>
            `;
            
            document.getElementById('all-notifications').innerHTML = `
                <div class="text-center py-4 loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading notifications...</p>
    </div>
                <div class="no-notifications text-center py-4" style="display: none;">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <p>You have no notifications.</p>
</div> 
            `;
            
            // Load notifications
            loadNotifications();
        });
        
        // Mark all as read button
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', markAllAsRead);
        }
    }
    
    // Check for new notifications periodically (every 2 minutes)
    setInterval(function() {
        fetch('ajax_handlers/get_notification_count.php')
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    // Update the notification count in the header
                    const notificationBadge = document.querySelector('#notification-badge');
                    if (notificationBadge) {
                        notificationBadge.textContent = data.count;
                        notificationBadge.style.display = 'inline-block';
                    }
                    
                    // Update the unread count in the modal
                    const unreadCount = document.querySelector('.unread-count');
                    if (unreadCount) {
                        unreadCount.textContent = data.count;
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for new notifications:', error);
            });
    }, 120000); // 2 minutes
});
</script> 