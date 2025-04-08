<div class="notification-backdrop" id="notificationBackdrop"></div>
<div class="notification-modal" id="notificationModal">
    <div class="notification-modal-header">
        <div class="notification-title">
            <i class="fas fa-bell"></i> Notifications
        </div>
        <div class="notification-actions">
            <button id="markAllReadBtn" title="Mark all as read">
                <i class="fas fa-check-double"></i> <span class="action-text">Mark all read</span>
            </button>
            <button id="closeNotificationBtn" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <div class="notification-tabs">
        <button class="tab-btn active" data-tab="all">All</button>
        <button class="tab-btn" data-tab="unread">Unread</button>
        <button class="tab-btn" data-tab="assignments">Assignments</button>
    </div>
    
    <div class="notification-list" id="notificationList">
        <!-- Notification items will be loaded dynamically -->
        <div class="notification-empty loading">
            <i class="fas fa-spinner fa-pulse"></i>
            <p>Loading notifications...</p>
        </div>
    </div>
    
    <div class="notification-footer">
        <a href="notifications.php">View all notifications</a>
    </div>
</div> 