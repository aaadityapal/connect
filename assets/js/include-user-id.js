/**
 * This file ensures the USER_ID global variable is available for project views
 */
document.addEventListener('DOMContentLoaded', function() {
    // Check if USER_ID is already defined
    if (typeof USER_ID === 'undefined') {
        // Make an AJAX request to get the current user ID
        fetch('get_current_user.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.user_id) {
                    // Set global USER_ID variable
                    window.USER_ID = data.user_id;
                    console.log('USER_ID set from AJAX request:', window.USER_ID);
                    
                    // If projectOverview instance exists, refresh it
                    if (window.projectOverview) {
                        window.projectOverview.fetchUserProjects();
                    }
                } else {
                    console.error('Failed to get user ID from server');
                }
            })
            .catch(error => {
                console.error('Error fetching user ID:', error);
            });
    }
}); 