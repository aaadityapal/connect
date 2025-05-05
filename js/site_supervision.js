/**
 * Site Supervision Dashboard JavaScript
 * Handles the interactive features of the site supervision page
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips if Bootstrap's tooltip component is available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Initialize date input to today's date
    document.getElementById('updateDate').valueAsDate = new Date();

    // Add site update button click handler
    const addUpdateBtns = document.querySelectorAll('.add-update-btn');
    addUpdateBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const siteId = this.getAttribute('data-site-id');
            const siteName = this.getAttribute('data-site-name');
            
            // Set form values
            document.getElementById('siteId').value = siteId;
            document.getElementById('siteName').value = siteName;
            
            // Show modal
            const addUpdateModal = new bootstrap.Modal(document.getElementById('addUpdateModal'));
            addUpdateModal.show();
        });
    });

    // Save update button click handler
    document.getElementById('saveUpdateBtn').addEventListener('click', function() {
        const form = document.getElementById('updateForm');
        
        // Basic validation
        if (!form.checkValidity()) {
            // Trigger browser's native validation
            form.reportValidity();
            return;
        }
        
        // Get form data
        const siteId = document.getElementById('siteId').value;
        const updateDate = document.getElementById('updateDate').value;
        const notes = document.getElementById('notes').value;
        
        // In a real application, you would send this data to the server
        // For now, we'll just show a success message
        console.log('Saving update:', {
            site_id: siteId,
            update_date: updateDate,
            notes: notes
        });
        
        // Close modal
        const addUpdateModal = bootstrap.Modal.getInstance(document.getElementById('addUpdateModal'));
        addUpdateModal.hide();
        
        // Show success message (would typically be added after successful AJAX call)
        showNotification('Update saved successfully!', 'success');
        
        // In a real application, you would refresh the updates list after saving
    });

    // View update button click handler
    const viewUpdateBtns = document.querySelectorAll('.view-update-btn');
    viewUpdateBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const updateId = this.getAttribute('data-update-id');
            
            // In a real application, you would fetch the update details from the server
            // For this demo, we'll use the data already in the table row
            const row = this.closest('tr');
            const siteName = row.cells[0].textContent;
            const updateDate = row.cells[1].textContent;
            let notes = row.cells[2].querySelector('[data-toggle="tooltip"]') 
                ? row.cells[2].querySelector('[data-toggle="tooltip"]').getAttribute('title')
                : row.cells[2].textContent;
            
            // Set modal content
            document.getElementById('viewSiteName').textContent = siteName;
            document.getElementById('viewUpdateDate').textContent = updateDate;
            document.getElementById('viewNotes').textContent = notes;
            
            // Show modal
            const viewUpdateModal = new bootstrap.Modal(document.getElementById('viewUpdateModal'));
            viewUpdateModal.show();
        });
    });

    // Edit update button click handler
    const editUpdateBtns = document.querySelectorAll('.edit-update-btn');
    editUpdateBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const updateId = this.getAttribute('data-update-id');
            
            // In a real application, you would redirect to an edit page or show an edit modal
            // For this demo, we'll just show a notification
            showNotification('Edit functionality would be implemented in a real application.', 'info');
        });
    });

    // Site link click handler
    const siteLinks = document.querySelectorAll('.site-link');
    siteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const siteId = this.getAttribute('data-site-id');
            
            // In a real application, you would redirect to a site details page
            // For this demo, we'll just show a notification
            showNotification('Site details would be shown in a real application.', 'info');
        });
    });

    // Supervision task click handler
    const taskItems = document.querySelectorAll('.task-item');
    taskItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const taskName = this.querySelector('h5').textContent;
            
            // In a real application, you would show a task details page or modal
            // For this demo, we'll just show a notification
            showNotification(`Task "${taskName}" details would be shown in a real application.`, 'info');
        });
    });

    // Function to show notifications
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification`;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.2)';
        notification.style.borderRadius = '10px';
        notification.style.padding = '15px 20px';
        notification.style.animation = 'fadeInRight 0.5s ease-out forwards';
        
        // Add icon based on type
        let icon = '';
        switch(type) {
            case 'success':
                icon = '<i class="fas fa-check-circle me-2"></i>';
                break;
            case 'warning':
                icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
                break;
            case 'danger':
                icon = '<i class="fas fa-times-circle me-2"></i>';
                break;
            default:
                icon = '<i class="fas fa-info-circle me-2"></i>';
        }
        
        notification.innerHTML = `${icon}${message}`;
        
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close';
        closeBtn.style.position = 'absolute';
        closeBtn.style.top = '10px';
        closeBtn.style.right = '10px';
        closeBtn.addEventListener('click', function() {
            document.body.removeChild(notification);
        });
        notification.appendChild(closeBtn);
        
        // Add to body
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            if (document.body.contains(notification)) {
                notification.style.animation = 'fadeOutRight 0.5s ease-in forwards';
                setTimeout(function() {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 500);
            }
        }, 5000);
    }

    // Add animations to document
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(50px);
            }
        }
    `;
    document.head.appendChild(style);
}); 