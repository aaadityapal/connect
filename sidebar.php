<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not authorized
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar Component</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Fix body and html to prevent overflow */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }

        /* Dashboard container fixes */
        .dashboard {
            display: flex;
            height: 100vh;
            overflow: hidden;
            max-height: 100vh;
            position: relative;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            border-right: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            height: 100vh;
            overflow: visible !important;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .toggle-btn {
            position: absolute;
            top: 10px;
            right: -15px;
            background-color: #ffffff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            border: 1px solid #e0e0e0;
        }

        .sidebar.collapsed .toggle-btn {
            display: flex !important;
            opacity: 1 !important;
            right: -15px !important;
        }

        .sidebar-header {
            padding: 20px 15px 10px;
            color: #888;
            font-size: 12px;
            flex-shrink: 0;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 20px 0 10px;
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            flex-shrink: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-menu li a:hover {
            background-color: #f5f5f5;
        }

        .sidebar-menu li.active a {
            background-color: #f9f9f9;
            color: #ff3e3e;
            border-left: 3px solid #ff3e3e;
        }

        .sidebar-menu li a i {
            margin-right: 10px;
            font-size: 18px;
            min-width: 25px;
            text-align: center;
        }

        .sidebar.collapsed .sidebar-menu li a {
            padding: 12px 0;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-menu li a i {
            margin-right: 0;
            font-size: 20px;
        }

        .sidebar::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 1px;
            background-color: #e0e0e0;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar .toggle-btn {
            z-index: 10;
        }

        .toggle-btn i {
            transition: all 0.3s ease;
            font-size: 14px;
        }

        /* Logout button styling */
        .sidebar-footer {
            margin-top: auto;
            padding: 10px 0;
            flex-shrink: 0;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            color: #ff3e3e !important;
            padding: 12px 15px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #ffefef;
        }

        .logout-btn i {
            margin-right: 10px;
            font-size: 18px;
            min-width: 25px;
            text-align: center;
        }

        .sidebar.collapsed .logout-btn {
            justify-content: center;
            padding: 12px 0;
        }

        .sidebar.collapsed .logout-btn i {
            margin-right: 0;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .sidebar .sidebar-text {
                display: none;
            }

            .sidebar .sidebar-menu li a {
                padding: 12px 0;
                justify-content: center;
            }

            .sidebar .sidebar-menu li a i {
                margin-right: 0;
                font-size: 20px;
            }

            .toggle-btn {
                display: none;
            }

            .sidebar.expanded {
                width: 250px;
                z-index: 1000;
            }

            .sidebar.expanded .sidebar-text {
                display: inline;
            }

            .sidebar.expanded .sidebar-menu li a {
                padding: 12px 15px;
                justify-content: flex-start;
            }

            .sidebar.expanded .sidebar-menu li a i {
                margin-right: 10px;
                font-size: 18px;
            }
        }

        /* Content container for demo purposes */
        .content-container {
            flex: 1;
            background-color: #f9f9f9;
            padding: 20px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" id="toggle-btn">
                <i class="fas fa-chevron-left"></i>
            </div>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">MAIN</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li class="active">
                    <a href="#">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="leave.php">
                        <i class="fas fa-calendar-check"></i>
                        <span class="sidebar-text">Leaves</span>
                    </a>
                </li>
                <li>
                    <a href="employee.php">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-text">Employees</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-box"></i>
                        <span class="sidebar-text">Projects</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">ANALYTICS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i>
                        <span class="sidebar-text"> Employee Reports</span>
                    </a>
                </li>
                <li>
                    <a href="work_report.php">
                        <i class="fas fa-file-invoice"></i>
                        <span class="sidebar-text"> Work Reports</span>
                    </a>
                </li>
                <li>
                    <a href="attendance_report.php">
                        <i class="fas fa-clock"></i>
                        <span class="sidebar-text"> Attendance Reports</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">SETTINGS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span class="sidebar-text">Profile</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-bell"></i>
                        <span class="sidebar-text">Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="manager_settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
            </ul>

            <!-- Add logout at the end of sidebar -->
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="sidebar-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="content-container">
            <h1>Main Content Area</h1>
            <p>This is where your page content would go.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggle-btn');
            
            // Toggle sidebar collapse/expand
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                
                // Change icon direction based on sidebar state
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
            
            // For mobile: click outside to close expanded sidebar
            document.addEventListener('click', function(e) {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile && !sidebar.contains(e.target) && sidebar.classList.contains('expanded')) {
                    sidebar.classList.remove('expanded');
                }
            });
            
            // For mobile: toggle expanded class
            if (window.innerWidth <= 768) {
                sidebar.addEventListener('click', function(e) {
                    if (e.target.closest('a')) return; // Allow clicking links
                    
                    if (!sidebar.classList.contains('expanded')) {
                        e.stopPropagation();
                        sidebar.classList.add('expanded');
                    }
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('expanded');
                }
            });
        });
    </script>
</body>
</html> 