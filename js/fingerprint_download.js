/**
 * Fingerprint Download Handler
 * Provides the functionality for secure downloads with unique filenames
 */

/**
 * Simple notification function
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, info, warning)
 * @returns {HTMLElement} - The notification element that was created
 */
function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Choose icon based on notification type
    let icon;
    switch(type) {
        case 'success':
            icon = 'fa-check-circle';
            break;
        case 'warning':
            icon = 'fa-exclamation-triangle';
            break;
        case 'error':
            icon = 'fa-exclamation-circle';
            break;
        case 'info':
            icon = 'fa-info-circle';
            break;
        default:
            icon = 'fa-info-circle';
    }
    
    // Create notification content
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${icon}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Remove after delay
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
    
    return notification;
}

/**
 * Handles the fingerprint download for a file
 * @param {number} fileId - The ID of the file to download
 */
function fingerprintDownload(fileId) {
    // Show loading indicator or animation if needed
    showNotification('Preparing secure download...', 'info');
    
    // Send request to fingerprint download handler
    window.location.href = `fingerprint_download.php?file_id=${fileId}`;
    
    // Log the download for analytics (optional)
    logFileActivity(fileId, 'fingerprint_download');
}

/**
 * Logs file activity for tracking
 * @param {number} fileId - The ID of the file being accessed
 * @param {string} action - The action being performed
 */
function logFileActivity(fileId, action) {
    // This is optional but can be useful for tracking
    fetch('api/log_file_activity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            file_id: fileId,
            action: action,
            timestamp: new Date().toISOString()
        })
    }).catch(error => {
        console.error('Error logging file activity:', error);
    });
} 