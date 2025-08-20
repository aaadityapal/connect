<?php
/**
 * Include Manager Password Update Modal
 * This file includes the password update modal and related JavaScript
 */

// Include the modal HTML
require_once 'manager_pwd_update_modal.php';
?>

<!-- Include the JavaScript file -->
<script src="js/manager_pwd_update.js"></script>

<!-- Check if jQuery and Bootstrap are loaded, if not, load them -->
<script>
    // Check if jQuery is loaded
    if (typeof jQuery === 'undefined') {
        document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
    }
    
    // Check if Bootstrap JS is loaded
    if (typeof bootstrap === 'undefined' && typeof jQuery.fn.modal === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"><\/script>');
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get jQuery instance - use jQuery if available, otherwise use $ if it exists
        const jq = (typeof jQuery !== 'undefined') ? jQuery : (typeof $ !== 'undefined' ? $ : null);
        
        // Check if password change is required via AJAX
        if (jq) {
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
                        console.log('Showing manager password update modal');
                        const modalElement = document.getElementById('managerPwdUpdateModal');
                        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                            // Bootstrap 5
                            const modal = new bootstrap.Modal(modalElement, {
                                backdrop: 'static',
                                keyboard: false
                            });
                            modal.show();
                        } else {
                            // Bootstrap 4 or jQuery UI
                            jq('#managerPwdUpdateModal').modal({
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
        } else {
            // Fallback to fetch API if jQuery is not available
            fetch('password_change_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'check_password_change'
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Password change check response:', data);
                
                // Only show modal if server explicitly says password change is required
                if (data.success && data.password_change_required === true) {
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
                    
                    // Show the password change modal using Bootstrap 5 API if available
                    console.log('Showing manager password update modal');
                    const modalElement = document.getElementById('managerPwdUpdateModal');
                    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                        const modal = new bootstrap.Modal(modalElement, {
                            backdrop: 'static',
                            keyboard: false
                        });
                        modal.show();
                    }
                } else {
                    console.log('Password change not required, modal will not show');
                }
            })
            .catch(error => {
                console.error('Failed to check if password change is required:', error);
            });
        }
    });
</script>
