/**
 * Punch System Functionality
 * Handles punch in/out button, sounds, and notifications
 */
document.addEventListener('DOMContentLoaded', function() {
    // Sound effects using Web Audio API
    function createAudioContext() {
        // Create audio context
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        return new AudioContext();
    }

    // Function to play punch sound
    function playPunchSound() {
        try {
            const audioCtx = createAudioContext();
            
            // Create oscillator
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            
            // Set type and frequency
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(400, audioCtx.currentTime + 0.2);
            
            // Set volume envelope
            gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);
            
            // Connect nodes
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            // Start and stop
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.2);
        } catch (e) {
            console.log('Error playing punch sound:', e);
        }
    }

    // Function to play notification sound
    function playNotificationSound() {
        try {
            const audioCtx = createAudioContext();
            
            // Create oscillator
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            
            // Set type and frequency
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(1200, audioCtx.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(900, audioCtx.currentTime + 0.1);
            
            // Set volume envelope
            gainNode.gain.setValueAtTime(0.2, audioCtx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.3);
            
            // Connect nodes
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            // Start and stop
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.3);
        } catch (e) {
            console.log('Error playing notification sound:', e);
        }
    }

    // Function to add a notification to the menu
    function addNotificationToMenu(title, time, icon, bgColor) {
        // Play notification sound
        playNotificationSound();
        
        // Get notification container
        const notificationItems = document.querySelector('.notification-items-container');
        if (!notificationItems) return;
        
        // Create new notification item
        const notificationItem = document.createElement('div');
        notificationItem.className = 'notification-item unread';
        
        // Set notification content
        notificationItem.innerHTML = `
            <div class="notification-icon bg-${bgColor}">
                <i class="fas fa-${icon}"></i>
            </div>
            <div class="notification-content">
                <p class="notification-text">${title}</p>
                <p class="notification-time">${time}</p>
            </div>
        `;
        
        // Add to the top of the notification list
        notificationItems.prepend(notificationItem);
        
        // Update notification count badge
        updateNotificationBadge();
        
        // Add click handler for the new notification
        notificationItem.addEventListener('click', function() {
            this.classList.remove('unread');
            updateNotificationBadge();
        });
    }

    // Function to update notification badge count
    function updateNotificationBadge() {
        const unreadCount = document.querySelectorAll('.notification-item.unread').length;
        const badge = document.querySelector('.notification-badge');
        
        if (badge) {
            badge.textContent = unreadCount;
            badge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }
    }

    // Helper function to get current time formatted
    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
    }

    // Notification function
    function showNotification(message, type) {
        // Check if the notification container exists
        let notificationContainer = document.querySelector('.notification-container');
        
        // Create notification container if it doesn't exist
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.className = 'notification-container';
            document.body.appendChild(notificationContainer);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        // Set icon based on notification type
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        if (type === 'error') icon = 'fa-exclamation-circle';
        if (type === 'warning') icon = 'fa-exclamation-triangle';
        
        notification.innerHTML = `
            <i class="fas ${icon}"></i>
            <div class="notification-message">${message}</div>
        `;
        
        // Add to container
        notificationContainer.appendChild(notification);
        
        // Show notification
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Punch Button Functionality
    const punchButton = document.getElementById('punchButton');
    let isPunchedIn = false; // Track punch status

    if (punchButton) {
        punchButton.addEventListener('click', function() {
            // Toggle punch status
            isPunchedIn = !isPunchedIn;
            
            // Play punch sound
            playPunchSound();
            
            // Get current time
            const currentTime = getCurrentTime();
            const timeAgo = 'just now';
            
            // Update button appearance
            if (isPunchedIn) {
                // Change to Punch Out
                punchButton.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out';
                punchButton.style.backgroundColor = 'var(--danger-color, #dc3545)';
                
                // Add notification to menu
                addNotificationToMenu('Punched in successfully', timeAgo, 'sign-in-alt', 'success');
                
                // Show success notification
                showNotification('Punched in successfully at ' + currentTime, 'success');
            } else {
                // Change back to Punch In
                punchButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In';
                punchButton.style.backgroundColor = 'var(--success-color, #28a745)';
                
                // Add notification to menu
                addNotificationToMenu('Punched out successfully', timeAgo, 'sign-out-alt', 'info');
                
                // Show success notification
                showNotification('Punched out successfully at ' + currentTime, 'success');
            }
        });
    }

    // Mark all notifications as read
    const markAllBtn = document.querySelector('.mark-all');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            // Prevent event from closing the dropdown
            e.stopPropagation();
            
            // Mark all notifications as read
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Update notification badge
            updateNotificationBadge();
        });
    }

    // Notification item click handler
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            this.classList.remove('unread');
            updateNotificationBadge();
        });
    });

    // Initialize notification badge count
    updateNotificationBadge();
}); 