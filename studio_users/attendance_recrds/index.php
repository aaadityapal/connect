<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Sheet History | Premium Dashboard</title>
    
    <!-- Modern Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Desktop Styles (Loaded on screens > 768px) -->
    <link rel="stylesheet" href="css/desktop.css" media="screen and (min-width: 769px)">
    
    <!-- Mobile Styles (Loaded on screens <= 768px) -->
    <link rel="stylesheet" href="css/mobile.css" media="screen and (max-width: 768px)">

    <!-- Sidebar Dependencies -->
    <link rel="stylesheet" href="../components/sidebar.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>window.SIDEBAR_BASE_PATH = '../';</script>
    <script src="../components/sidebar-loader.js" defer></script>
</head>
<body class="ws-body-container">
    <div class="layout-wrapper" style="display: flex; height: 100vh; overflow: hidden;">
        <div id="sidebar-mount"></div>
        <main class="ws-main-layout" style="flex: 1; overflow-y: auto;">
            
            <!-- Header Section -->
        <header class="ws-header-wrapper">
            <div class="ws-header-title-box">
                <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar" style="background:transparent; border:none; cursor:pointer; margin-right:8px; align-items: center; justify-content: center;">
                    <i data-lucide="menu" style="width:24px;height:24px;color:var(--ws-primary);"></i>
                </button>
                <div class="ws-header-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <h1 class="ws-main-heading">Work Sheet History</h1>
            </div>
            
            <div class="ws-controls-container">
                <div class="ws-dropdown-wrapper" id="monthDropdown">
                    <div class="ws-dropdown-toggle">
                        <span class="ws-dropdown-selected">March</span>
                        <i class="fa-solid fa-chevron-down ws-dropdown-arrow"></i>
                    </div>
                    <ul class="ws-dropdown-menu">
                        <li class="ws-dropdown-item">January</li>
                        <li class="ws-dropdown-item">February</li>
                        <li class="ws-dropdown-item active">March</li>
                        <li class="ws-dropdown-item">April</li>
                        <li class="ws-dropdown-item">May</li>
                        <li class="ws-dropdown-item">June</li>
                        <li class="ws-dropdown-item">July</li>
                        <li class="ws-dropdown-item">August</li>
                        <li class="ws-dropdown-item">September</li>
                        <li class="ws-dropdown-item">October</li>
                        <li class="ws-dropdown-item">November</li>
                        <li class="ws-dropdown-item">December</li>
                    </ul>
                </div>
                
                <div class="ws-dropdown-wrapper" id="yearDropdown">
                    <div class="ws-dropdown-toggle">
                        <span class="ws-dropdown-selected">2026</span>
                        <i class="fa-solid fa-chevron-down ws-dropdown-arrow"></i>
                    </div>
                    <ul class="ws-dropdown-menu">
                        <li class="ws-dropdown-item active">2026</li>
                        <li class="ws-dropdown-item">2025</li>
                        <li class="ws-dropdown-item">2024</li>
                        <li class="ws-dropdown-item">2023</li>
                        <li class="ws-dropdown-item">2022</li>
                        <li class="ws-dropdown-item">2021</li>
                    </ul>
                </div>
                
                <div class="ws-dropdown-wrapper" id="filterDropdown">
                    <button class="ws-filter-button ws-dropdown-toggle">
                        <i class="fa-solid fa-filter"></i> <span class="ws-dropdown-selected">Filter</span>
                    </button>
                    <ul class="ws-dropdown-menu">
                        <li class="ws-dropdown-item active">All Status</li>
                        <li class="ws-dropdown-item">On Time</li>
                        <li class="ws-dropdown-item">Late</li>
                        <li class="ws-dropdown-item">Absent</li>
                        <li class="ws-dropdown-item">Leave</li>
                        <li class="ws-dropdown-item">Holiday</li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="ws-content-wrapper">
            
            <!-- Key Performance Indicators (Cards) -->
            <section class="ws-kpi-section">
                <h2 class="ws-section-subtitle"><i class="fa-solid fa-chart-pie"></i> Attendance Overview</h2>
                
                <div class="ws-kpi-grid">
                    <!-- Card 1 -->
                    <div class="ws-kpi-card ws-card-present">
                        <div class="ws-kpi-icon"><i class="fa-solid fa-calendar-check"></i></div>
                        <div class="ws-kpi-details">
                            <span class="ws-kpi-label">Present Days</span>
                            <span class="ws-kpi-value">6</span>
                        </div>
                    </div>
                    
                    <!-- Card 2 -->
                    <div class="ws-kpi-card ws-card-hours">
                        <div class="ws-kpi-icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="ws-kpi-details">
                            <span class="ws-kpi-label">Total Hours</span>
                            <span class="ws-kpi-value">43:00</span>
                        </div>
                    </div>
                    
                    <!-- Card 3 -->
                    <div class="ws-kpi-card ws-card-overtime">
                        <div class="ws-kpi-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div class="ws-kpi-details">
                            <span class="ws-kpi-label">Overtime Hours (&ge;1:30) <i class="fa-solid fa-circle-info ws-info-btn" id="overtimeInfoBtn" style="cursor: pointer; color: var(--ws-primary-color); margin-left: 5px;" title="View Overtime Details"></i></span>
                            <span class="ws-kpi-value">00:00</span>
                        </div>
                    </div>
                    
                    <!-- Card 4 -->
                    <div class="ws-kpi-card ws-card-rate">
                        <div class="ws-kpi-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <div class="ws-kpi-details">
                            <span class="ws-kpi-label">Attendance Rate</span>
                            <span class="ws-kpi-value ws-highlight-green">27%</span>
                        </div>
                    </div>
                    
                    <!-- Card 5 -->
                    <div class="ws-kpi-card ws-card-late">
                        <div class="ws-kpi-icon"><i class="fa-solid fa-user-clock"></i></div>
                        <div class="ws-kpi-details">
                            <span class="ws-kpi-label">Late Punches (15+ min) <i class="fa-solid fa-circle-info ws-info-btn" id="lateInfoBtn" style="cursor: pointer; color: var(--ws-primary-color); margin-left: 5px;" title="View Late Details"></i></span>
                            <span class="ws-kpi-value ws-highlight-red">6</span>
                            <span class="ws-kpi-micro-text">Excludes short leaves</span>
                            <span class="ws-kpi-micro-text ws-muted">2 Short Leaves available</span>
                        </div>
                    </div>
                    
                    <!-- Card 6 -->
                    <div class="ws-kpi-card ws-card-leaves">
                        <div class="ws-kpi-icon"><i class="fa-solid fa-person-walking-arrow-right"></i></div>
                        <div class="ws-kpi-details">
                            <span class="ws-kpi-label">Leaves Taken <i class="fa-solid fa-circle-info ws-info-btn" id="leaveInfoBtn" style="cursor: pointer; color: var(--ws-primary-color); margin-left: 5px;" title="View Leave Details"></i></span>
                            <span class="ws-kpi-value ws-highlight-orange">3</span>
                            <span class="ws-kpi-badge">Unpaid Leave: 3</span>
                            <span class="ws-kpi-micro-text ws-muted">Shows only approved leaves</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Charts Placeholder Section -->
            <section class="ws-charts-section">
                <div class="ws-chart-container ws-bar-chart">
                    <div class="ws-chart-header">
                        <span class="ws-chart-title">Working Hours</span>
                    </div>
                    <div class="ws-chart-mockup">
                        <div class="ws-bar-wrapper"><div class="ws-bar" style="height: 80%;"></div><span class="ws-bar-label">2/3</span></div>
                        <div class="ws-bar-wrapper"><div class="ws-bar" style="height: 90%;"></div><span class="ws-bar-label">3/3</span></div>
                        <div class="ws-bar-wrapper"><div class="ws-bar" style="height: 85%;"></div><span class="ws-bar-label">5/3</span></div>
                        <div class="ws-bar-wrapper"><div class="ws-bar" style="height: 80%;"></div><span class="ws-bar-label">6/3</span></div>
                        <div class="ws-bar-wrapper"><div class="ws-bar" style="height: 88%;"></div><span class="ws-bar-label">11/3</span></div>
                    </div>
                </div>
                <div class="ws-chart-container ws-pie-chart">
                    <div class="ws-chart-header">
                        <span class="ws-chart-title ws-legend-blue">Regular Hours</span>
                        <span class="ws-chart-title ws-legend-red">Overtime Hours</span>
                    </div>
                    <div class="ws-chart-mockup ws-pie-mockup">
                        <div class="ws-donut-ring"></div>
                    </div>
                </div>
            </section>

            <!-- Data Table Section -->
            <section class="ws-table-section">
                <div class="ws-table-container">
                    <table class="ws-data-table">
                        <thead class="ws-table-head">
                            <tr class="ws-table-header-row">
                                <th class="ws-th">Date</th>
                                <th class="ws-th">Shift</th>
                                <th class="ws-th">Punch In</th>
                                <th class="ws-th">Punch In Location</th>
                                <th class="ws-th">Punch Out</th>
                                <th class="ws-th">Punch Out Location</th>
                                <th class="ws-th">Working Hours</th>
                                <th class="ws-th">Overtime</th>
                                <th class="ws-th">Work Report</th>
                                <th class="ws-th">Status</th>
                            </tr>
                        </thead>
                        <tbody class="ws-table-body">
                            <!-- Row 1 -->
                            <tr class="ws-table-row">
                                <td class="ws-td ws-font-medium">12-03-2026</td>
                                <td class="ws-td ws-text-subtle">09:00 AM - 06:00 PM</td>
                                <td class="ws-td">
                                    <div class="ws-punch-cell">
                                        <span class="ws-time">09:50 AM</span>
                                        <div class="ws-punch-image-wrapper ws-tooltip-trigger">
                                            <i class="fa-solid fa-image ws-icon-action"></i>
                                            <div class="ws-tooltip">View Punch In Selfie</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-location-text ws-tooltip-trigger">
                                        <i class="fa-solid fa-location-dot ws-location-icon"></i> New York, office
                                        <div class="ws-tooltip">123 Broadway, NY 10001</div>
                                    </div>
                                </td>
                                <td class="ws-td ws-text-muted">-</td>
                                <td class="ws-td ws-text-muted">-</td>
                                <td class="ws-td ws-text-muted">-</td>
                                <td class="ws-td ws-text-muted">-</td>
                                <td class="ws-td ws-text-muted">-</td>
                                <td class="ws-td"><span class="ws-status-badge ws-badge-late"><i class="fa-solid fa-circle-xmark"></i> Late</span></td>
                            </tr>

                            <!-- Row 2 -->
                            <tr class="ws-table-row">
                                <td class="ws-td ws-font-medium">11-03-2026</td>
                                <td class="ws-td ws-text-subtle">09:00 AM - 06:00 PM</td>
                                <td class="ws-td">
                                    <div class="ws-punch-cell">
                                        <span class="ws-time">09:46 AM</span>
                                        <div class="ws-punch-image-wrapper ws-tooltip-trigger">
                                            <img src="https://i.pravatar.cc/150?img=11" alt="Punch In Image" class="ws-punch-avatar">
                                            <div class="ws-tooltip">View Punch In Selfie</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-location-text ws-tooltip-trigger">
                                        <i class="fa-solid fa-location-dot ws-location-icon"></i> New York, office
                                        <div class="ws-tooltip">123 Broadway, NY 10001</div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-punch-cell">
                                        <span class="ws-time">06:33 PM</span>
                                        <div class="ws-punch-image-wrapper ws-tooltip-trigger">
                                            <img src="https://i.pravatar.cc/150?img=12" alt="Punch Out Image" class="ws-punch-avatar">
                                            <div class="ws-tooltip">View Punch Out Selfie</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-location-text ws-tooltip-trigger">
                                        <i class="fa-solid fa-location-dot ws-location-icon"></i> New York, office
                                        <div class="ws-tooltip">123 Broadway, NY 10001</div>
                                    </div>
                                </td>
                                <td class="ws-td ws-font-semibold">08:47</td>
                                <td class="ws-td ws-text-muted">-</td>
                                <td class="ws-td ws-report-text" data-full-report="Create ui of dashboard for managers Add task list section Fix the sidebar issue with icons Solve mobile responsive issues Update theme.">Create ui of dashboard for managers Add task...</td>
                                <td class="ws-td"><span class="ws-status-badge ws-badge-late"><i class="fa-solid fa-circle-xmark"></i> Late</span></td>
                            </tr>
                            
                            <!-- Row 3 -->
                            <tr class="ws-table-row">
                                <td class="ws-td ws-font-medium">06-03-2026</td>
                                <td class="ws-td ws-text-subtle">09:00 AM - 06:00 PM</td>
                                <td class="ws-td">
                                    <div class="ws-punch-cell">
                                        <span class="ws-time">09:46 AM</span>
                                        <div class="ws-punch-image-wrapper ws-tooltip-trigger">
                                            <img src="https://i.pravatar.cc/150?img=32" alt="Punch In Image" class="ws-punch-avatar">
                                            <div class="ws-tooltip">View Punch In Selfie</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-location-text ws-tooltip-trigger">
                                        <i class="fa-solid fa-location-dot ws-location-icon"></i> Client Site A
                                        <div class="ws-tooltip">400 Wall St, NY 10005</div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-punch-cell">
                                        <span class="ws-time">06:13 PM</span>
                                        <div class="ws-punch-image-wrapper ws-tooltip-trigger">
                                            <img src="https://i.pravatar.cc/150?img=33" alt="Punch Out Image" class="ws-punch-avatar">
                                            <div class="ws-tooltip">View Punch Out Selfie</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-location-text ws-tooltip-trigger">
                                        <i class="fa-solid fa-location-dot ws-location-icon"></i> Client Site A
                                        <div class="ws-tooltip">400 Wall St, NY 10005</div>
                                    </div>
                                </td>
                                <td class="ws-td ws-font-semibold">08:26</td>
                                <td class="ws-td ws-text-muted">-</td>
                                <td class="ws-td ws-report-text" data-full-report="Meeting with prabhat sir about new project Researching ui for the task management app and testing user interactions.">Meeting with prabhat sir about new project Re...</td>
                                <td class="ws-td"><span class="ws-status-badge ws-badge-late"><i class="fa-solid fa-circle-xmark"></i> Late</span></td>
                            </tr>

                            <!-- Row 4 -->
                            <tr class="ws-table-row">
                                <td class="ws-td ws-font-medium">05-03-2026</td>
                                <td class="ws-td ws-text-subtle">09:00 AM - 06:00 PM</td>
                                <td class="ws-td">
                                    <div class="ws-punch-cell">
                                        <span class="ws-time">09:36 AM</span>
                                        <div class="ws-punch-image-wrapper ws-tooltip-trigger">
                                            <img src="https://i.pravatar.cc/150?img=44" alt="Punch In Image" class="ws-punch-avatar">
                                            <div class="ws-tooltip">View Punch In Selfie</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-location-text ws-tooltip-trigger">
                                        <i class="fa-solid fa-location-dot ws-location-icon"></i> Sector 62, Noida
                                        <div class="ws-tooltip">B-82, Sector 62, Noida, UP</div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-punch-cell">
                                        <span class="ws-time">06:23 PM</span>
                                        <div class="ws-punch-image-wrapper ws-tooltip-trigger">
                                            <img src="https://i.pravatar.cc/150?img=45" alt="Punch Out Image" class="ws-punch-avatar">
                                            <div class="ws-tooltip">View Punch Out Selfie</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ws-td">
                                    <div class="ws-location-text ws-tooltip-trigger">
                                        <i class="fa-solid fa-location-dot ws-location-icon"></i> Sector 62, Noida
                                        <div class="ws-tooltip">B-82, Sector 62, Noida, UP</div>
                                    </div>
                                </td>
                                <td class="ws-td ws-font-semibold">08:46</td>
                                <td class="ws-td ws-text-muted">-</td>
                                <td class="ws-td ws-report-text" data-full-report="Result on news letter section Developer ui of new section Testing new modal popups for selfes.">Result on news letter section Developer...</td>
                                <td class="ws-td"><span class="ws-status-badge ws-badge-late"><i class="fa-solid fa-circle-xmark"></i> Late</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
        <!-- Javascript logic has been moved to api fetcher -->

        <!-- Dynamic Selfie Modal Overlay -->
        <div id="punchModal" class="ws-modal-overlay">
            <div class="ws-modal-content">
                <button class="ws-modal-close" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></button>
                <div class="ws-modal-header">
                    <h3><span id="punchModalTitle">Punch Details</span></h3>
                    <span id="punchModalTime" class="ws-time">00:00 AM</span>
                </div>
                <div class="ws-modal-body">
                    <div class="ws-modal-image-container">
                        <img id="punchModalImg" src="" alt="Punch Selfie" />
                    </div>
                    <div class="ws-modal-location-container">
                        <i class="fa-solid fa-location-dot ws-location-icon"></i>
                        <div class="ws-modal-location-text">
                            <strong id="punchModalLocTitle">Location</strong>
                            <p id="punchModalLocDesc">Address string goes here</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Report Modal Overlay -->
        <div id="reportModal" class="ws-modal-overlay">
            <div class="ws-modal-content">
                <button id="reportModalClose" class="ws-modal-close" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></button>
                <div class="ws-modal-header">
                    <h3><i class="fa-solid fa-file-invoice" style="color: var(--ws-chart-bar);"></i> <span>Work Report</span></h3>
                    <span id="reportModalDate" class="ws-time">00-00-0000</span>
                </div>
                <div class="ws-modal-body">
                    <div class="ws-report-full-content" id="reportModalContent">
                        Work report content goes here...
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Detailed KPI Modal Overlay -->
        <div id="detailsModal" class="ws-modal-overlay">
            <div class="ws-modal-content" style="max-width: 500px;">
                <button id="detailsModalClose" class="ws-modal-close" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></button>
                <div class="ws-modal-header">
                    <h3><i class="fa-solid fa-list" style="color: var(--ws-primary-color);"></i> <span id="detailsModalTitle">Details</span></h3>
                </div>
                <div class="ws-modal-body" style="max-height: 400px; overflow-y: auto;">
                    <div class="ws-report-full-content" id="detailsModalContent">
                        <!-- Dynamic List Content goes here -->
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Custom Logic for Attendance List -->
<script>
// --- Selfie Modal Setup ---
const punchModalOverlay = document.getElementById('punchModal');
const punchModalCloseBtn = document.querySelector('.ws-modal-close');
window.openPunchModal = function(type, time, imgSrc, addressObj) {
    document.getElementById('punchModalTitle').textContent = type;
    document.getElementById('punchModalTime').textContent = time;
    document.getElementById('punchModalImg').src = imgSrc;
    document.getElementById('punchModalLocTitle').textContent = addressObj.title;
    document.getElementById('punchModalLocDesc').textContent = addressObj.desc;
    punchModalOverlay.classList.add('active');
};
const closePunchModal = () => punchModalOverlay.classList.remove('active');
punchModalCloseBtn.addEventListener('click', closePunchModal);
punchModalOverlay.addEventListener('click', (e) => {
    if (e.target === punchModalOverlay) closePunchModal();
});

// --- Report Modal Setup ---
const reportModalInstance = document.getElementById('reportModal');
const reportModalCloseBtnObj = document.getElementById('reportModalClose');
const closeReportModalFunc = () => reportModalInstance.classList.remove('active');
reportModalCloseBtnObj.addEventListener('click', closeReportModalFunc);
reportModalInstance.addEventListener('click', (e) => {
    if (e.target === reportModalInstance) closeReportModalFunc();
});

// --- Details Modal Setup ---
const detailsModalInstance = document.getElementById('detailsModal');
const detailsModalCloseBtnObj = document.getElementById('detailsModalClose');
const closeDetailsModalFunc = () => detailsModalInstance.classList.remove('active');
detailsModalCloseBtnObj.addEventListener('click', closeDetailsModalFunc);
detailsModalInstance.addEventListener('click', (e) => {
    if (e.target === detailsModalInstance) closeDetailsModalFunc();
});

// --- Dropdown setup UI ---
const dropdowns = document.querySelectorAll('.ws-dropdown-wrapper');
dropdowns.forEach(dropdown => {
    const toggle = dropdown.querySelector('.ws-dropdown-toggle');
    const menu = dropdown.querySelector('.ws-dropdown-menu');
    const selectedText = dropdown.querySelector('.ws-dropdown-selected');
    const items = dropdown.querySelectorAll('.ws-dropdown-item');
    
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdowns.forEach(other => {
            if (other !== dropdown) {
                other.querySelector('.ws-dropdown-menu').classList.remove('active');
                other.querySelector('.ws-dropdown-arrow')?.classList.remove('rotated');
            }
        });
        menu.classList.toggle('active');
        dropdown.querySelector('.ws-dropdown-arrow')?.classList.toggle('rotated');
    });
    
    items.forEach(item => {
        item.addEventListener('click', () => {
            if (!toggle.classList.contains('ws-filter-button')) {
                selectedText.textContent = item.textContent;
            }
            items.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            menu.classList.remove('active');
            dropdown.querySelector('.ws-dropdown-arrow')?.classList.remove('rotated');
        });
    });
});
document.addEventListener('click', () => {
    dropdowns.forEach(dropdown => {
        dropdown.querySelector('.ws-dropdown-menu').classList.remove('active');
        dropdown.querySelector('.ws-dropdown-arrow')?.classList.remove('rotated');
    });
});
</script>
<script src="js/attendance_script.js"></script>
</body>
</html>
