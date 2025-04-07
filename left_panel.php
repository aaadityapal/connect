<!-- Add Font Awesome CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Left Panel -->
<div class="left-panel" id="leftPanel">
    <div class="brand-logo" style="padding: 20px 25px; margin-bottom: 20px;">
        <img src="" alt="Logo" style="max-width: 150px; height: auto;">
    </div>
    <button class="toggle-btn" onclick="togglePanel()">
        <i class="fas fa-chevron-left" id="toggleIcon"></i>
    </button>
    
    <!-- Main Navigation -->
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'similar_dashboard.php' ? 'active' : ''; ?>" onclick="window.location.href='similar_dashboard.php'">
        <i class="fas fa-home"></i>
        <span class="menu-text">Dashboard</span>
    </div>
    
    <!-- Personal Section -->
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" onclick="window.location.href='profile.php'">
        <i class="fas fa-user-circle"></i>
        <span class="menu-text">My Profile</span>
    </div>
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'leave.php' ? 'active' : ''; ?>" onclick="window.location.href='leave.php'">
        <i class="fas fa-calendar-alt"></i>
        <span class="menu-text">Apply Leave</span>
    </div>
    
    <!-- Work Section -->
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'taskss.php' ? 'active' : ''; ?>" onclick="window.location.href='tasks.php'">
        <i class="fas fa-tasks"></i>
        <span class="menu-text">My Tasks</span>
    </div>
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'work_sheet.php' ? 'active' : ''; ?>" onclick="window.location.href='work_sheet.php'">
        <i class="fas fa-file-alt"></i>
        <span class="menu-text">Work Sheet & Attendance</span>
    </div>
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'performance.php' ? 'active' : ''; ?>" onclick="window.location.href='performance.php'">
        <i class="fas fa-chart-bar"></i>
        <span class="menu-text">Performance</span>
    </div>
    <!-- Settings & Support -->
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" onclick="window.location.href='settings.php'">
        <i class="fas fa-cog"></i>
        <span class="menu-text">Settings</span>
    </div>
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'active' : ''; ?>" onclick="window.location.href='support.php'">
        <i class="fas fa-question-circle"></i>
        <span class="menu-text">Help & Support</span>
    </div>
    
    <!-- Logout at the bottom -->
    <div class="menu-item logout-item" onclick="window.location.href='logout.php'">
        <i class="fas fa-sign-out-alt"></i>
        <span class="menu-text">Logout</span>
    </div>
</div>

<style>
/* Reset and Base Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.left-panel {
    width: 280px;
    background: linear-gradient(180deg, #2c3e50, #34495e);
    color: #fff;
    height: 100vh;
    transition: all 0.3s ease;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    z-index: 1000;
    overflow: visible; /* Changed to visible to show toggle button */
}

.left-panel.collapsed {
    width: 70px;
}

.left-panel.collapsed + .main-content {
    margin-left: 70px;
}

/* Updated Toggle Button Styles */
.toggle-btn {
    position: absolute;
    right: -18px;
    top: 25px;
    background: #fff;
    border: none;
    color: #2c3e50;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1001; /* Ensure button stays above other elements */
    overflow: visible;
}

.toggle-btn:hover {
    transform: scale(1.15);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    background: #f8f9fa;
}

.toggle-btn i {
    font-size: 14px;
    transition: all 0.3s ease;
    display: inline-block; /* Ensure icon is visible */
    line-height: 1;
}

.toggle-btn:hover i {
    color: #1a237e;
    transform: scale(1.2);
}

/* Updated Menu Item Styles */
.menu-item {
    padding: 16px 25px;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    margin: 5px 0;
    position: relative;
    overflow: hidden;
    text-decoration: none; /* Added for link styling */
    color: #fff; /* Added for link styling */
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.1);
    border-left: 4px solid #3498db;
    padding-left: 30px;
}

.menu-item.active {
    background: rgba(255, 255, 255, 0.15);
    border-left: 4px solid #3498db;
}

.menu-item::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    background: rgba(255, 255, 255, 0.1);
    transform: scaleX(0);
    transform-origin: right;
    transition: transform 0.3s ease;
    z-index: 0;
}

.menu-item:hover::after {
    transform: scaleX(1);
    transform-origin: left;
}

.menu-item i {
    margin-right: 15px;
    width: 20px;
    font-size: 1.2em;
    text-align: center;
    position: relative;
    z-index: 1;
    color: #3498db;
    display: inline-block; /* Ensure icon is visible */
}

.menu-text {
    transition: all 0.3s ease;
    font-size: 0.95em;
    letter-spacing: 0.3px;
    font-weight: 500;
    position: relative;
    z-index: 1;
    color: #fff;
}

.left-panel.collapsed .menu-text {
    display: none;
}

.logout-item {
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 0, 0, 0.1);
}

.logout-item:hover {
    background: rgba(255, 0, 0, 0.2);
    border-left: 4px solid #ff4444 !important;
}

.logout-item i {
    color: #ff4444 !important;
}

.menu-item.section-start {
    margin-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 20px;
}

/* Ensure proper icon display */
.fas, .far, .fab {
    display: inline-block !important;
    line-height: 1 !important;
}
</style>

<script>
function togglePanel() {
    const panel = document.getElementById('leftPanel');
    const mainContent = document.getElementById('mainContent');
    const icon = document.getElementById('toggleIcon');
    panel.classList.toggle('collapsed');
    if (mainContent) {
        mainContent.classList.toggle('collapsed');
    }
    icon.classList.toggle('fa-chevron-left');
    icon.classList.toggle('fa-chevron-right');
}

// Remove the click event listeners since we're using onclick in the HTML
</script> 