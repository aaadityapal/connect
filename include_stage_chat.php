<?php
/**
 * Include file for stage chat functionality
 * This file adds the necessary JavaScript for the stage chat
 */
?>

<!-- Stage Chat JavaScript -->
<script src="assets/js/stage-chat.js"></script>

<!-- Ensure the StageChat is available globally -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if StageChat is available and initialize it
    if (typeof StageChat === 'function') {
        window.stageChat = new StageChat();
    } else {
        console.error('StageChat class not loaded');
    }
});
</script> 