<?php
// Ensure this component can only be accessed through the main dashboard
if (!defined('DASHBOARD_ACCESS')) {
    header('Location: ../index.php');
    exit();
}
?>

<div class="forwarded-tasks-section">
    <div class="section-header">
        <h2><i class="fas fa-share-square"></i> Forwarded Tasks</h2>
        <div class="header-actions">
            <button class="refresh-btn" onclick="ForwardedTasks.refresh()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    <div class="forwarded-tasks-container">
        <!-- Tasks will be loaded here dynamically -->
        <div class="loading-spinner">
            <i class="fas fa-circle-notch fa-spin"></i>
        </div>
    </div>
</div> 