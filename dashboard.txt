<?php
// Start session
session_start();

// Function to check user role
function isSeniorSalesManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Senior Manager (Sales)';
}

// Check if user is logged in and has correct role
if (!isset($_SESSION['user_id']) || !isSeniorSalesManager()) {
    // Redirect to login page or unauthorized page
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales Dashboard - Senior Manager</title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        .sidebar {
            height: 100vh;
            width: 220px;
            background: white;
            position: fixed;
            left: 0;
            top: 0;
            transition: width 0.3s ease;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            overflow: visible;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }

        .section-title {
            color: #8a8a8a;
            font-size: 12px;
            padding: 20px 25px 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .menu-item {
            padding: 12px 25px;
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 14px;
            transition: all 0.2s ease;
            white-space: nowrap;
            position: relative;
            z-index: 99;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            color: #dc3545;
            background: #fff5f5;
            border-left: 3px solid #dc3545;
        }

        .menu-item i {
            min-width: 20px;
            margin-right: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .menu-item:hover i {
            color: #dc3545;
            transform: translateX(2px);
        }

        .menu-item span {
            opacity: 1;
            transition: opacity 0.2s ease;
        }

        /* Sidebar Closed State */
        .sidebar.closed {
            width: 60px;
        }

        .sidebar.closed .section-title {
            padding: 20px 0 10px;
            font-size: 10px;
            text-align: center;
            opacity: 0.6;
        }

        .sidebar.closed .menu-item {
            padding: 12px 0;
            justify-content: center;
        }

        .sidebar.closed .menu-item i {
            margin-right: 0;
        }

        .sidebar.closed .menu-item span {
            opacity: 0;
            width: 0;
            display: none;
        }

        /* Toggle Button */
        .toggle-btn {
            position: absolute;
            right: -10px;
            top: 12px;
            background: #f5f5f5;
            color: #666;
            width: 20px;
            height: 20px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 101;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .toggle-btn i {
            font-size: 10px;
        }

        .sidebar.closed .toggle-btn {
            transform: rotate(180deg);
        }

        /* Main Content */
        .main-content {
            margin-left: 220px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 60px;
        }

        /* Update closed sidebar hover states */
        .sidebar.closed .menu-item:hover {
            background: #fff5f5;
            border-left: 3px solid #dc3545;
        }

        .sidebar.closed .menu-item:hover i {
            color: #dc3545;
            transform: scale(1.1);
        }

        /* Active menu item state */
        .menu-item.active {
            color: #dc3545;
            background: #fff5f5;
            border-left: 3px solid #dc3545;
        }

        .menu-item.active i {
            color: #dc3545;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
        }

        .logout-btn {
            padding: 15px 25px;
            color: #dc3545;  /* Red color for logout */
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 14px;
            transition: all 0.2s ease;
            white-space: nowrap;
            border-top: 1px solid #eee;
            margin-top: auto;
        }

        .logout-btn i {
            min-width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        .logout-btn:hover {
            background: #fff5f5;
            color: #dc3545;
        }

        .sidebar.closed .logout-btn {
            padding: 15px 0;
            justify-content: center;
        }

        .sidebar.closed .logout-btn i {
            margin-right: 0;
        }

        .sidebar.closed .logout-btn span {
            display: none;
        }

        /* Add these media query styles at the end of your existing styles */

        /* For tablets (portrait) and smaller laptops */
        @media screen and (max-width: 1024px) {
            .sidebar {
                width: 280px;
            }

            .main-content {
                margin-left: 280px;
            }

            .menu-item {
                padding: 14px 25px;
                font-size: 14px;
            }

            .menu-item i {
                font-size: 18px;
                margin-right: 15px;
            }

            .section-title {
                padding: 20px 25px 10px;
                font-size: 13px;
            }
        }

        /* For mobile devices and small tablets */
        @media screen and (max-width: 768px) {
            .sidebar {
                width: 300px;
                left: -300px;
            }

            .sidebar.closed {
                width: 300px;
                left: -300px;
            }

            .menu-item {
                padding: 16px 25px;
                font-size: 15px;
            }

            .menu-item i {
                font-size: 20px;
                margin-right: 15px;
            }

            .section-title {
                padding: 25px 25px 12px;
                font-size: 14px;
            }

            /* Larger mobile menu button */
            .mobile-menu-btn {
                padding: 12px;
                font-size: 18px;
            }

            /* Adjust logout button */
            .logout-btn {
                padding: 20px 25px;
                font-size: 15px;
            }

            .logout-btn i {
                font-size: 20px;
            }
        }

        /* For very small mobile devices */
        @media screen and (max-width: 375px) {
            .sidebar {
                width: 90%;
                left: -90%;
            }

            .sidebar.closed {
                width: 90%;
                left: -90%;
            }

            .menu-item {
                padding: 16px 20px;
            }

            .section-title {
                padding: 20px 20px 12px;
            }
        }

        .greeting-section {
            padding: 20px;
            margin-bottom: 20px;
        }

        .greeting-card {
            background: #fcfcfc;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
        }

        .content-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .left-content {
            flex: 1;
        }

        .greeting-line {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .greeting {
            font-size: 15px;
            color: #555;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .greeting i {
            font-size: 14px;
            color: #ff6b6b;  /* Softer red */
        }

        .greeting-line h1 {
            font-size: 15px;
            color: #333;
            margin: 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .greeting-line h1 i {
            font-size: 14px;
            color: #ff6b6b;  /* Softer red */
        }

        .info-lines {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .time-line, .date-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #777;
        }

        .time-line i, .date-line i {
            font-size: 13px;
            color: #ff6b6b;  /* Softer red */
        }

        /* Punch section styles */
        .punch-section {
            text-align: right;
        }

        .last-punch {
            font-size: 14px;
            color: #777;
            margin-bottom: 8px;
        }

        .punch-time {
            font-weight: 500;
            color: #333;
        }

        .punch-btn {
            background: #fff;
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .punch-btn:hover {
            background: #ff6b6b;
            color: #fff;
        }

        .punch-btn i {
            font-size: 14px;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 768px) {
            .content-wrapper {
                flex-direction: column;
                gap: 20px;
            }

            .punch-section {
                width: 100%;
                text-align: left;
            }

            .punch-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php if (isSeniorSalesManager()): ?>
        <div class="sidebar" id="sidebar">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="fas fa-chevron-left" id="toggle-icon"></i>
            </button>
            
            <div class="sidebar-content">
                <div class="section-title">MAIN</div>
                <a href="#" class="menu-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-tags"></i>
                    <span>Orders</span>
                </a>

                <div class="section-title">ANALYTICS</div>
                <a href="#" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-bullseye"></i>
                    <span>Targets</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Statistics</span>
                </a>

                <div class="section-title">SETTINGS</div>
                <a href="#" class="menu-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>

            <!-- Add logout button at bottom -->
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <div class="main-content" id="main-content">
            <div class="greeting-section">
                <div class="greeting-card">
                    <div class="content-wrapper">
                        <div class="left-content">
                            <div class="greeting-line">
                                <span class="greeting">
                                    <i class="fas fa-sun"></i>
                                    Good Morning,
                                </span>
                                <h1><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
                            </div>
                            <div class="info-lines">
                                <div class="time-line">
                                    <i class="far fa-clock"></i>
                                    <span id="ist-time"></span>
                                </div>
                                <div class="date-line">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('l, d M Y'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="punch-section">
                            <div class="last-punch">Last punch in: <span class="punch-time">10:44 AM</span></div>
                            <button class="punch-btn">
                                <i class="fas fa-fingerprint"></i>
                                <span>Punch Out</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add mobile menu button and overlay -->
        <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="sidebar-overlay" onclick="toggleMobileSidebar()"></div>
    <?php endif; ?>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleIcon = document.getElementById('toggle-icon');
            
            sidebar.classList.toggle('closed');
            mainContent.classList.toggle('expanded');
            
            // Change arrow direction
            if (sidebar.classList.contains('closed')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        }

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            
            if (!sidebar.contains(event.target) && 
                !mobileBtn.contains(event.target) && 
                sidebar.classList.contains('mobile-open')) {
                toggleMobileSidebar();
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            }
        });

        function updateISTTime() {
            const options = {
                timeZone: 'Asia/Kolkata',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            
            const istTime = new Date().toLocaleTimeString('en-US', options);
            document.getElementById('ist-time').textContent = istTime + ' IST';
        }

        // Update time immediately and then every second
        updateISTTime();
        setInterval(updateISTTime, 1000);
    </script>
</body>
</html>
