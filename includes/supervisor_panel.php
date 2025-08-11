<!-- Site Supervisor Left Panel with Professional Styling -->
<style>
:root {
    --panel-width: 280px;
    --panel-collapsed: 70px;
}

/* Hide scrollbars while maintaining functionality */
* {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}
*::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
}

/* Left Panel Styles - Professional gradient design */
.left-panel {
    width: var(--panel-width);
    background: linear-gradient(180deg, #2a4365, #1a365d);
    color: #fff;
    height: 100vh;
    transition: all 0.3s ease;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    display: flex;
    flex-direction: column;
    z-index: 1000;
    overflow-y: auto;
    overflow-x: hidden;
    
    /* Hide scrollbar but keep functionality */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

/* Hide scrollbar for Chrome, Safari and Opera */
.left-panel::-webkit-scrollbar {
    display: none;
    width: 0;
}

.left-panel.collapsed {
    width: var(--panel-collapsed);
}

.left-panel .brand-logo {
    padding: 20px 25px;
    margin-bottom: 0;
}

.left-panel .brand-logo img {
    max-height: 30px;
    width: auto;
}

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
    text-decoration: none;
    color: #fff;
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.1);
    border-left: 4px solid rgba(255, 255, 255, 0.8);
    padding-left: 30px;
}

.menu-item.active {
    background: rgba(255, 255, 255, 0.15);
    border-left: 4px solid #fff;
}

.menu-item i {
    margin-right: 15px;
    width: 16px;
    font-size: 1em;
    text-align: center;
    position: relative;
    z-index: 1;
    color: rgba(255, 255, 255, 0.85);
    display: inline-block;
    opacity: 0.9;
}

.menu-text {
    transition: all 0.3s ease;
    font-size: 0.95em;
    letter-spacing: 0.3px;
    font-weight: 500;
    position: relative;
    z-index: 1;
    white-space: nowrap;
    padding-left: 5px;
}

.left-panel.collapsed .menu-text {
    display: none;
}

.left-panel.collapsed .menu-item i {
    width: 100%;
    margin-right: 0;
    font-size: 1.1em;
    opacity: 1;
}

.logout-item {
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(197, 48, 48, 0.1);
}

.logout-item:hover {
    background: rgba(197, 48, 48, 0.2);
    border-left: 4px solid #c53030 !important;
}

.logout-item i {
    color: #f56565 !important;
}

.menu-item.section-start {
    margin-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 20px;
}

.toggle-btn {
    position: absolute;
    right: -18px;
    top: 25px;
    background: #ffffff;
    border: none;
    color: #2a4365;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1001;
    overflow: visible;
}

.toggle-btn:hover {
    transform: scale(1.15);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    background: #f0f4f8;
}

.toggle-btn i {
    font-size: 14px;
    transition: all 0.3s ease;
    display: inline-block;
    line-height: 1;
}

/* Main Content Adjustments */
.main-content {
    margin-left: var(--panel-width);
    transition: margin-left 0.3s ease;
    min-height: 100vh;
}

.main-content.expanded { 
    margin-left: var(--panel-collapsed); 
}

/* Mobile Hamburger Menu */
.hamburger-menu {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1100;
    background: #2a4365;
    color: white;
    width: 45px;
    height: 45px;
    border-radius: 8px;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    border: none;
}

.hamburger-menu:hover {
    background: #1a365d;
    transform: scale(1.05);
}

.hamburger-menu i {
    font-size: 1.2rem;
    transition: transform 0.3s ease;
}

/* Mobile Overlay */
.mobile-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(45, 55, 72, 0.7);
    z-index: 999;
    backdrop-filter: blur(3px);
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mobile-overlay.active {
    display: block;
    opacity: 1;
}

/* Mobile Close Button inside panel */
.mobile-close-btn {
    display: none;
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    z-index: 1002;
    transition: all 0.3s ease;
}

.mobile-close-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
    .main-content {
        padding: 15px;
    }
}

@media (max-width: 768px) {
    .left-panel { 
        transform: translateX(-100%); 
        box-shadow: none;
        width: 280px !important;
        transition: transform 0.3s ease;
    }
    
    .left-panel.mobile-visible {
        transform: translateX(0);
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.3);
    }
    
    .main-content { 
        margin-left: 0 !important;
        padding: 70px 15px 15px 15px;
    }
    
    .main-content.expanded { 
        margin-left: 0 !important; 
    }
    
    .toggle-btn {
        display: none;
    }
    
    .hamburger-menu {
        display: flex;
    }
    
    .mobile-close-btn {
        display: flex;
    }
    
    /* Adjust menu items for mobile */
    .menu-item {
        padding: 18px 25px;
        font-size: 1rem;
    }
    
    .menu-item i {
        font-size: 1.1em;
        margin-right: 18px;
    }
    
    .menu-text {
        font-size: 1em;
    }
}

/* iPhone XR (414x896) and similar */
@media (max-width: 414px) and (min-height: 800px) {
    .hamburger-menu {
        width: 42px;
        height: 42px;
        top: 12px;
        left: 12px;
    }
    
    .main-content {
        padding: 65px 12px 12px 12px;
    }
    
    .left-panel {
        width: 300px !important;
    }
    
    .menu-item {
        padding: 16px 20px;
    }
    
    .menu-text {
        font-size: 0.95em;
    }
    
    .brand-logo {
        padding: 15px 20px !important;
    }
}

/* iPhone SE (375x667) and smaller screens */
@media (max-width: 375px) {
    .hamburger-menu {
        width: 40px;
        height: 40px;
        top: 10px;
        left: 10px;
    }
    
    .hamburger-menu i {
        font-size: 1.1rem;
    }
    
    .main-content {
        padding: 60px 10px 10px 10px;
    }
    
    .left-panel {
        width: 280px !important;
    }
    
    .menu-item {
        padding: 14px 18px;
    }
    
    .menu-item i {
        font-size: 1em;
        margin-right: 15px;
    }
    
    .menu-text {
        font-size: 0.9em;
    }
    
    .brand-logo {
        padding: 12px 18px !important;
    }
    
    .brand-logo img {
        max-height: 25px !important;
    }
    
    .mobile-close-btn {
        width: 32px;
        height: 32px;
        top: 12px;
        right: 12px;
    }
}

/* Very small screens (320px and below) */
@media (max-width: 320px) {
    .hamburger-menu {
        width: 38px;
        height: 38px;
        top: 8px;
        left: 8px;
    }
    
    .main-content {
        padding: 55px 8px 8px 8px;
    }
    
    .left-panel {
        width: 260px !important;
    }
    
    .menu-item {
        padding: 12px 15px;
    }
    
    .menu-text {
        font-size: 0.85em;
    }
    
    .brand-logo {
        padding: 10px 15px !important;
    }
}
</style>

<!-- Mobile Hamburger Menu -->
<button class="hamburger-menu" id="hamburgerMenu" onclick="toggleMobilePanel()">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="closeMobilePanel()"></div>

<div class="left-panel" id="leftPanel">
    <div class="brand-logo">
        <img src="#" alt="Company Logo" style="max-width: 150px; height: auto;">
    </div>
    <button class="toggle-btn" onclick="togglePanel()">
        <i class="fas fa-chevron-left" id="toggleIcon"></i>
    </button>
    
    <!-- Mobile Close Button -->
    <button class="mobile-close-btn" onclick="closeMobilePanel()">
        <i class="fas fa-times"></i>
    </button>
    
    <!-- Main Navigation -->
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_supervisor_dashboard.php' ? 'active' : ''; ?>" onclick="window.location.href='site_supervisor_dashboard.php'">
        <i class="fas fa-home"></i>
        <span class="menu-text">Dashboard</span>
    </div>
    
    <!-- Site Management Section -->
    <div class="menu-item section-start">
        <i class="fas fa-hard-hat"></i>
        <span class="menu-text">Site Management</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_inspections.php' ? 'active' : ''; ?>" onclick="window.location.href='site_inspections.php'">
        <i class="fas fa-clipboard-check"></i>
        <span class="menu-text">Site Inspections</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_issues.php' ? 'active' : ''; ?>" onclick="window.location.href='site_issues.php'">
        <i class="fas fa-exclamation-triangle"></i>
        <span class="menu-text">Issues & Reports</span>
    </div>
    
    <!-- Worker Management Section -->
    <div class="menu-item section-start">
        <i class="fas fa-users"></i>
        <span class="menu-text">Worker Management</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'worker_attendance.php' ? 'active' : ''; ?>" onclick="window.location.href='worker_attendance.php'">
        <i class="fas fa-user-clock"></i>
        <span class="menu-text">Attendance</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'worker_assignment.php' ? 'active' : ''; ?>" onclick="window.location.href='worker_assignment.php'">
        <i class="fas fa-tasks"></i>
        <span class="menu-text">Task Assignment</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'worker_performance.php' ? 'active' : ''; ?>" onclick="window.location.href='worker_performance.php'">
        <i class="fas fa-chart-bar"></i>
        <span class="menu-text">Performance</span>
    </div>
    
    <!-- Materials & Equipment Section -->
    <div class="menu-item section-start">
        <i class="fas fa-tools"></i>
        <span class="menu-text">Materials & Equipment</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'materials_inventory.php' ? 'active' : ''; ?>" onclick="window.location.href='materials_inventory.php'">
        <i class="fas fa-warehouse"></i>
        <span class="menu-text">Inventory</span>
    </div>
    
    <!-- Reports Section -->
    <div class="menu-item section-start">
        <i class="fas fa-file-alt"></i>
        <span class="menu-text">Reports</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'view_travel_expenses.php' ? 'active' : ''; ?>" onclick="window.location.href='view_travel_expenses.php'">
        <i class="fas fa-file-invoice-dollar"></i>
        <span class="menu-text">Travel Expenses</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance_visualizer.php' ? 'active' : ''; ?>" onclick="window.location.href='attendance_visualizer.php'">
        <i class="fas fa-user-clock"></i>
        <span class="menu-text">Attendance Reports</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'supervisors_overtime.php' ? 'active' : ''; ?>" onclick="window.location.href='supervisors_overtime.php'">
        <i class="fas fa-clock"></i>
        <span class="menu-text">Overtime Reports</span>
    </div>
    
    <!-- Settings & Personal Section -->
    <div class="menu-item section-start">
        <i class="fas fa-user-cog"></i>
        <span class="menu-text">Personal</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_supervisor_profile.php' ? 'active' : ''; ?>" onclick="window.location.href='site_supervisor_profile.php'">
        <i class="fas fa-user-circle"></i>
        <span class="menu-text">My Profile</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_supervisor_recent_leaves.php' ? 'active' : ''; ?>" onclick="window.location.href='site_supervisor_recent_leaves.php'">
        <i class="fas fa-calendar-alt"></i>
        <span class="menu-text">Apply Leave</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" onclick="window.location.href='settings.php'">
        <i class="fas fa-cog"></i>
        <span class="menu-text">Settings</span>
    </div>
    
    <!-- Logout at the bottom -->
    <div class="menu-item logout-item" onclick="window.location.href='logout.php'">
        <i class="fas fa-sign-out-alt"></i>
        <span class="menu-text">Logout</span>
    </div>
</div> 

<script>
// Mobile panel functionality
function toggleMobilePanel() {
    const leftPanel = document.getElementById('leftPanel');
    const mobileOverlay = document.getElementById('mobileOverlay');
    
    leftPanel.classList.toggle('mobile-visible');
    mobileOverlay.classList.toggle('active');
    
    // Prevent body scroll when panel is open
    if (leftPanel.classList.contains('mobile-visible')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

function closeMobilePanel() {
    const leftPanel = document.getElementById('leftPanel');
    const mobileOverlay = document.getElementById('mobileOverlay');
    
    leftPanel.classList.remove('mobile-visible');
    mobileOverlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Desktop panel functionality
function togglePanel() {
    const leftPanel = document.getElementById('leftPanel');
    const mainContent = document.querySelector('.main-content');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (window.innerWidth > 768) {
        leftPanel.classList.toggle('collapsed');
        if (mainContent) {
            mainContent.classList.toggle('expanded');
        }
        if (toggleIcon) {
            toggleIcon.classList.toggle('fa-chevron-right');
            toggleIcon.classList.toggle('fa-chevron-left');
        }
    }
}

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        // Desktop mode - close mobile panel if open
        closeMobilePanel();
        
        // Reset any mobile states
        const leftPanel = document.getElementById('leftPanel');
        if (leftPanel) {
            leftPanel.classList.remove('mobile-visible');
        }
    } else {
        // Mobile mode - ensure desktop states are reset
        const leftPanel = document.getElementById('leftPanel');
        const mainContent = document.querySelector('.main-content');
        
        if (leftPanel) {
            leftPanel.classList.remove('collapsed');
        }
        if (mainContent) {
            mainContent.classList.remove('expanded');
        }
    }
});

// Close mobile panel when clicking on menu items
document.addEventListener('DOMContentLoaded', function() {
    const menuItems = document.querySelectorAll('.menu-item:not(.section-start)');
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    closeMobilePanel();
                }, 100); // Small delay to allow navigation
            }
        });
    });
});
</script>