<!-- ===== PAGE HEADER ===== -->
<div class="page-header dark-header">
    <div class="header-left">
        <h2>Overtime Management System</h2>
    </div>
    <div class="header-right" style="display: flex; gap: 8px;">
        <div class="user-info-box"
            style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: var(--radius-sm); min-width: max-content;">
            <div style="font-size: 0.625rem; color: #cbd5e1;">Current User</div>
            <div style="font-size: 0.8125rem; color: #fff; font-weight: 600; line-height: 1.2;">
                <?php echo htmlspecialchars($current_username); ?></div>
        </div>
        <div class="date-info-box"
            style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: var(--radius-sm); min-width: max-content;">
            <div style="font-size: 0.625rem; color: #cbd5e1;">Today</div>
            <div id="current-date-display"
                style="font-size: 0.8125rem; color: #fff; font-weight: 600; line-height: 1.2;">
                <?php echo $current_date; ?></div>
        </div>
    </div>
</div>