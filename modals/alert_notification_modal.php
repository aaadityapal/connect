<?php
/**
 * Alert Notification Modal
 * This modal displays alert notifications for various actions in the system
 */
?>

<!-- Alert Notification Modal -->
<div class="alert-notification-modal-overlay" id="alertNotificationModal">
    <div class="alert-notification-modal-content">
        <div class="alert-notification-modal-header">
            <h4 id="alertNotificationTitle">Notification</h4>
            <button class="alert-notification-close-btn" id="closeAlertNotificationModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="alert-notification-modal-body">
            <div class="alert-icon">
                <i class="fas fa-bell" id="alertNotificationIcon"></i>
            </div>
            <div class="alert-content">
                <h5 id="alertNotificationMessage">Notification Message</h5>
                <p id="alertNotificationDetail" class="alert-detail"></p>
                <p id="alertNotificationThought" class="alert-thought"></p>
            </div>
        </div>
        <div class="alert-notification-modal-footer">
            <button class="alert-notification-ok-btn" id="alertNotificationOkBtn">OK</button>
        </div>
    </div>
</div>

<style>
.alert-notification-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10001;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.alert-notification-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.alert-notification-modal-content {
    background-color: #fff;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transform: translateY(-20px);
    transition: transform 0.3s ease;
}

.alert-notification-modal-overlay.active .alert-notification-modal-content {
    transform: translateY(0);
}

.alert-notification-modal-header {
    padding: 15px 20px;
    background-color: #3498db;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.alert-notification-modal-header h4 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.alert-notification-close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease;
}

.alert-notification-close-btn:hover {
    transform: rotate(90deg);
}

.alert-notification-modal-body {
    padding: 25px 20px;
    text-align: center;
}

.alert-icon {
    margin-bottom: 20px;
}

.alert-icon i {
    font-size: 3rem;
    color: #3498db;
}

.alert-content h5 {
    margin: 0 0 10px 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
}

.alert-detail {
    margin: 0 0 15px 0;
    font-size: 0.95rem;
    color: #7f8c8d;
}

.alert-thought {
    margin: 0;
    font-size: 1rem;
    font-style: italic;
    color: #3498db;
    font-weight: 500;
}

.alert-notification-modal-footer {
    padding: 15px 20px;
    background: #f9f9f9;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-shrink: 0;
}

.alert-notification-ok-btn {
    padding: 10px 25px;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.alert-notification-ok-btn:hover {
    background-color: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
}

/* Success state */
.alert-notification-modal-content.success .alert-notification-modal-header {
    background-color: #2ecc71;
}

.alert-notification-modal-content.success .alert-icon i {
    color: #2ecc71;
}

.alert-notification-modal-content.success .alert-thought {
    color: #27ae60;
}

.alert-notification-modal-content.success .alert-notification-ok-btn {
    background-color: #2ecc71;
}

.alert-notification-modal-content.success .alert-notification-ok-btn:hover {
    background-color: #27ae60;
}

/* Warning state */
.alert-notification-modal-content.warning .alert-notification-modal-header {
    background-color: #e74c3c;
}

.alert-notification-modal-content.warning .alert-icon i {
    color: #e74c3c;
}

.alert-notification-modal-content.warning .alert-thought {
    color: #c0392b;
}

.alert-notification-modal-content.warning .alert-notification-ok-btn {
    background-color: #e74c3c;
}

.alert-notification-modal-content.warning .alert-notification-ok-btn:hover {
    background-color: #c0392b;
}

@media (max-width: 576px) {
    .alert-notification-modal-content {
        width: 95%;
        max-width: 95%;
    }
    
    .alert-notification-modal-header h4 {
        font-size: 1.1rem;
    }
    
    .alert-content h5 {
        font-size: 1rem;
    }
    
    .alert-detail {
        font-size: 0.9rem;
    }
    
    .alert-thought {
        font-size: 0.95rem;
    }
}
</style>

<script>
// Function to show alert notification modal
function showAlertNotification(type, title, message, detail, thought) {
    const modal = document.getElementById('alertNotificationModal');
    const modalContent = modal.querySelector('.alert-notification-modal-content');
    const titleElement = document.getElementById('alertNotificationTitle');
    const iconElement = document.getElementById('alertNotificationIcon');
    const messageElement = document.getElementById('alertNotificationMessage');
    const detailElement = document.getElementById('alertNotificationDetail');
    const thoughtElement = document.getElementById('alertNotificationThought');
    
    // Reset classes
    modalContent.className = 'alert-notification-modal-content';
    
    // Set content based on type
    if (type === 'success') {
        modalContent.classList.add('success');
        iconElement.className = 'fas fa-check-circle';
    } else if (type === 'warning') {
        modalContent.classList.add('warning');
        iconElement.className = 'fas fa-exclamation-triangle';
    } else {
        iconElement.className = 'fas fa-bell';
    }
    
    titleElement.textContent = title;
    messageElement.textContent = message;
    detailElement.textContent = detail;
    thoughtElement.textContent = thought;
    
    // Show modal
    modal.classList.add('active');
}

// Function to close alert notification modal
function closeAlertNotificationModal() {
    const modal = document.getElementById('alertNotificationModal');
    modal.classList.remove('active');
}

// Add event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const closeBtn = document.getElementById('closeAlertNotificationModal');
    const okBtn = document.getElementById('alertNotificationOkBtn');
    const modal = document.getElementById('alertNotificationModal');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeAlertNotificationModal);
    }
    
    if (okBtn) {
        okBtn.addEventListener('click', closeAlertNotificationModal);
    }
    
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeAlertNotificationModal();
            }
        });
    }
});
</script>