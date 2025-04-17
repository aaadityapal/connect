<?php
/**
 * Include file for project brief modal
 * This file adds the necessary CSS and JavaScript files for the project brief modal functionality
 */
?>

<!-- Project Brief Modal CSS -->
<link rel="stylesheet" href="assets/css/project-brief-modal.css">

<!-- Project Brief Modal JavaScript -->
<script src="assets/js/project-brief-modal.js"></script>

<!-- Ensure the ProjectBriefModal is available globally -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if ProjectBriefModal is available and initialize it
    if (typeof ProjectBriefModal === 'function') {
        window.projectBriefModal = new ProjectBriefModal();
    } else {
        console.error('ProjectBriefModal class not loaded');
    }
});
</script> 