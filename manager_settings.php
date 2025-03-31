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
    <link rel="stylesheet" href="dashboard-styles.css">
    <style>
        .settings-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .settings-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .settings-card h3 {
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: 500;
            color: #555;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #2196F3;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .save-btn {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 10px;
        }

        .save-btn:hover {
            background-color: #1976D2;
        }

        .color-picker {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .select-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Include your sidebar here -->
        <?php include 'includes/sidebar.php'; ?>

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