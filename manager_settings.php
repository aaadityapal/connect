<?php
session_start();
// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Senior Manager (Studio)') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #343a40;
            --light: #f8f9fa;
            --border: #e9ecef;
            --text: #212529;
            --text-muted: #6c757d;
            --shadow: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow: hidden;
        }

        /* Dashboard Layout */
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
            flex-shrink: 0;
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

        /* Main Content */
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            background-color: #f5f8fa;
            width: calc(100% - 250px);
            transition: width 0.3s ease;
        }

        .sidebar.collapsed + .main-content {
            width: calc(100% - 70px);
        }

        .settings-container {
            padding: 20px;
            width: 100%;
            max-width: none;
            margin: 0;
        }

        /* Header Styles */
        .settings-container h2 {
            margin: 20px 0 30px 0;
            padding: 20px 0;
            border-bottom: 2px solid var(--primary-light);
            font-size: 28px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .settings-container h2 i {
            color: var(--primary);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.1);
            transition: all 0.3s ease;
            border: 1px solid #e5e9ff;
        }

        .settings-card:hover {
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.15);
            transform: translateY(-2px);
        }

        .settings-card h3 {
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }

        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text);
        }

        .select-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e5e9ff;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            background-color: white;
            color: #333;
        }

        .select-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .save-btn {
            background: linear-gradient(135deg, #4361ee, #3a4ee0);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 14px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
            margin-top: 15px;
        }

        .save-btn:hover {
            background: linear-gradient(135deg, #3a4ee0, #2f44d9);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.3);
        }

        @media (max-width: 768px) {
            .main-content {
                width: calc(100% - 70px);
            }

            .sidebar.expanded + .main-content {
                width: calc(100% - 250px);
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

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
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" id="toggle-btn">
                <i class="fas fa-chevron-left"></i>
            </div>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">MAIN</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="real.php">
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
                <li class="active">
                    <a href="manager_settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
            </ul>

            <!-- Logout -->
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

        <div class="main-content">
            <div class="settings-container">
                <h2><i class="fas fa-cog"></i> Manager Settings</h2>
                
                <div class="settings-grid">
                    <!-- Notification Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                        <form class="settings-form" id="notificationForm">
                            <div class="form-group">
                                <label>Email Notifications</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_notifications" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Push Notifications</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="push_notifications" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Leave Request Alerts</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="leave_alerts" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <button type="submit" class="save-btn">Save Notification Settings</button>
                        </form>
                    </div>

                    <!-- Display Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-desktop"></i> Display Settings</h3>
                        <form class="settings-form" id="displayForm">
                            <div class="form-group">
                                <label>Theme Color</label>
                                <input type="color" class="color-picker" name="theme_color" value="#2196F3">
                            </div>
                            <div class="form-group">
                                <label>Default View</label>
                                <select class="select-input" name="default_view">
                                    <option value="grid">Grid View</option>
                                    <option value="list">List View</option>
                                    <option value="calendar">Calendar View</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Dark Mode</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="dark_mode">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <button type="submit" class="save-btn">Save Display Settings</button>
                        </form>
                    </div>

                    <!-- Project Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-project-diagram"></i> Project Settings</h3>
                        <form class="settings-form" id="projectForm">
                            <div class="form-group">
                                <label>Default Project View</label>
                                <select class="select-input" name="project_view">
                                    <option value="kanban">Kanban Board</option>
                                    <option value="list">List View</option>
                                    <option value="timeline">Timeline</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Auto-assign Projects</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="auto_assign">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Project Reminders</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="project_reminders" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <button type="submit" class="save-btn">Save Project Settings</button>
                        </form>
                    </div>

                    <!-- Team Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-users"></i> Team Settings</h3>
                        <form class="settings-form" id="teamForm">
                            <div class="form-group">
                                <label>Team View Layout</label>
                                <select class="select-input" name="team_layout">
                                    <option value="grid">Grid</option>
                                    <option value="list">List</option>
                                    <option value="org">Organization Chart</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Show Team Statistics</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="team_stats" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Team Member Status</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="member_status" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <button type="submit" class="save-btn">Save Team Settings</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle
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

        // Handle form submissions
        document.querySelectorAll('.settings-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formId = e.target.id;
                const formData = new FormData(e.target);
                
                try {
                    const response = await fetch('save_settings.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            type: formId,
                            settings: Object.fromEntries(formData)
                        }),
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        showNotification('Settings saved successfully', 'success');
                    } else {
                        throw new Error(data.message || 'Failed to save settings');
                    }
                } catch (error) {
                    showNotification(error.message, 'error');
                }
            });
        });

        // Show notification function
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `settings-notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html> 