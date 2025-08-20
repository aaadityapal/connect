<?php
/**
 * Include file for mandatory password change
 * This file should be included in all dashboard pages
 */

// Include the modal HTML
require_once 'mandatory_password_change.php';

// Add JavaScript to check if password change is required
?>

<!-- Make sure jQuery is loaded first -->
<script>
if (typeof jQuery === 'undefined' && typeof $ === 'undefined') {
    document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
}
</script>

<!-- Include Bootstrap JS if not already loaded -->
<script>
if (typeof bootstrap === 'undefined') {
    document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"><\/script>');
}
</script>

<!-- Include the mandatory password change JavaScript -->
<script src="js/mandatory_password_change.js"></script>

<script>
// Set a flag to indicate that password change is required
// In a real implementation, this would be set based on server-side check
document.addEventListener('DOMContentLoaded', function() {
    // Ensure we have jQuery available
    const jq = (typeof $ !== 'undefined') ? $ : (typeof jQuery !== 'undefined' ? jQuery : null);
    
    if (!jq) {
        console.error('jQuery is not available. Password change functionality requires jQuery.');
        return;
    }
    
    // Check if password change is required via AJAX
    jq.ajax({
        url: 'password_change_handler.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'check_password_change'
        },
        success: function(response) {
            console.log('Password change check response:', response);
            
            // Only show modal if server explicitly says password change is required
            if (response.success && response.password_change_required === true) {
                // Check if password was recently updated (within 3 months) - client-side check
                const passwordUpdated = sessionStorage.getItem('password_updated');
                const updateTime = sessionStorage.getItem('password_update_time');
                
                if (passwordUpdated && updateTime) {
                    const threeMonthsInMs = 90 * 24 * 60 * 60 * 1000; // 90 days in milliseconds
                    const timeSinceUpdate = new Date().getTime() - parseInt(updateTime);
                    
                    // If less than 3 months have passed, don't show the modal
                    if (timeSinceUpdate < threeMonthsInMs) {
                        console.log('Password recently updated, not showing modal');
                        return;
                    } else {
                        // Clear the old session storage if more than 3 months have passed
                        sessionStorage.removeItem('password_updated');
                        sessionStorage.removeItem('password_update_time');
                    }
                }
                
                // Show the password change modal
                console.log('Showing password change modal');
                const modalElement = document.getElementById('mandatoryPasswordChangeModal');
                if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                    // Bootstrap 5
                    const modal = new bootstrap.Modal(modalElement, {
                        backdrop: 'static',
                        keyboard: false
                    });
                    modal.show();
                } else {
                    // Bootstrap 4 or jQuery UI
                    jq('#mandatoryPasswordChangeModal').modal({
                        backdrop: 'static',
                        keyboard: false
                    });
                }
            } else {
                console.log('Password change not required, modal will not show');
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to check if password change is required:', error);
            console.error('Response:', xhr.responseText);
        }
    });
    
    // Handle form submission via AJAX
    jq('#mandatoryPasswordChangeForm').on('submit', function(e) {
        e.preventDefault();
        
        const currentPassword = jq('#current_password').val();
        const newPassword = jq('#new_password').val();
        const confirmPassword = jq('#confirm_password').val();
        
        // Basic validation
        if (!currentPassword || !newPassword || !confirmPassword) {
            showError('All fields are required');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showError('Passwords do not match');
            return;
        }
        
        // Show loading state
        const submitButton = jq('#updatePasswordBtn');
        const originalButtonText = submitButton.html();
        submitButton.html('<i class="fas fa-spinner fa-spin mr-2"></i>Updating...');
        submitButton.prop('disabled', true);
        
        // Submit via AJAX
        jq.ajax({
            url: 'password_change_handler.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update_password',
                current_password: currentPassword,
                new_password: newPassword
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    jq('#password-error').text(response.message);
                    jq('#password-error').removeClass('alert-danger').addClass('alert-success').show();
                    
                    // Store a flag in sessionStorage to prevent modal from showing again
                    sessionStorage.setItem('password_updated', 'true');
                    sessionStorage.setItem('password_update_time', new Date().getTime());
                    
                    // Close modal after delay
                    setTimeout(function() {
                        // Check Bootstrap version and use appropriate method
                        const modalElement = document.getElementById('mandatoryPasswordChangeModal');
                        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                            // Bootstrap 5
                            const modalInstance = bootstrap.Modal.getInstance(modalElement);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                        } else {
                            // Bootstrap 4 or jQuery UI
                            jq('#mandatoryPasswordChangeModal').modal('hide');
                        }
                        
                        // Reload the page to ensure the modal doesn't show again
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    }, 2000);
                } else {
                    // Show error message
                    showError(response.message);
                    submitButton.html(originalButtonText);
                    submitButton.prop('disabled', false);
                }
            },
            error: function() {
                showError('An error occurred. Please try again.');
                submitButton.html(originalButtonText);
                submitButton.prop('disabled', false);
            }
        });
    });
    
    function showError(message) {
        jq('#password-error').text(message);
        jq('#password-error').removeClass('alert-success').addClass('alert-danger').show();
        
        // Add shake animation
        jq('#password-error').addClass('shake');
        setTimeout(function() {
            jq('#password-error').removeClass('shake');
        }, 600);
    }
});
</script>
