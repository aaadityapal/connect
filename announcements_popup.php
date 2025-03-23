<?php
// Fetch active announcements that haven't expired
function getLatestAnnouncements($conn) {
    $query = "SELECT a.*, u.username as creator_name 
              FROM announcements a
              LEFT JOIN users u ON a.created_by = u.id 
              WHERE a.status = 'active' 
              AND (a.display_until IS NULL OR a.display_until >= CURDATE())
              ORDER BY 
                CASE a.priority
                    WHEN 'high' THEN 1
                    WHEN 'normal' THEN 2
                    WHEN 'low' THEN 3
                END,
                a.created_at DESC";
    
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>

<div id="announcementPopup" class="announcement-popup">
    <div class="announcement-content">
        <div class="announcement-header">
            <h3><i class="fas fa-bullhorn"></i> Important Announcements</h3>
            <button class="close-announcement" onclick="closeAnnouncementPopup()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="announcement-body">
            <?php 
            $announcements = getLatestAnnouncements($conn);
            if (!empty($announcements)): 
            ?>
                <div class="announcement-list">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item priority-<?php echo $announcement['priority']; ?>">
                            <div class="announcement-icon">
                                <?php
                                $icon = 'fa-bell';
                                if ($announcement['priority'] === 'high') {
                                    $icon = 'fa-exclamation-circle';
                                } elseif ($announcement['priority'] === 'low') {
                                    $icon = 'fa-info-circle';
                                }
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="announcement-details">
                                <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                <p class="announcement-message">
                                    <?php echo htmlspecialchars($announcement['message']); ?>
                                </p>
                                <?php if (!empty($announcement['content'])): ?>
                                    <div class="announcement-content">
                                        <?php echo $announcement['content']; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="announcement-meta">
                                    <span class="date">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                    </span>
                                    <?php if ($announcement['display_until']): ?>
                                        <span class="expiry">
                                            <i class="far fa-calendar-alt"></i>
                                            Valid until: <?php echo date('M d, Y', strtotime($announcement['display_until'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="creator">
                                        <i class="far fa-user"></i>
                                        By: <?php echo htmlspecialchars($announcement['creator_name']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-announcements">
                    <i class="fas fa-info-circle"></i>
                    <p>No active announcements</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="assets/js/announcements_popup.js"></script> 