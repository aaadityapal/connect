<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db_connect.php';

// Role-based access: only admin
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, role, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || strtolower($user['role'] ?? '') !== 'admin') {
    header("Location: index.php"); // Redirect non-admins to dashboard
    exit();
}

$username = $user['username'];
$email = $user['email'];

$menu_items = [
    'Main' => [
        'index' => ['label' => 'Dashboard', 'icon' => 'layout-dashboard']
    ],
    'Personal' => [
        'profile' => ['label' => 'My Profile', 'icon' => 'user-round']
    ],
    'Leave & Expenses' => [
        'apply-leave' => ['label' => 'Apply Leave', 'icon' => 'calendar-check'],
        'travel-expenses' => ['label' => 'Travel Expenses', 'icon' => 'receipt'],
        'overtime' => ['label' => 'Overtime', 'icon' => 'alarm-clock']
    ],
    'Work' => [
        'projects' => ['label' => 'Projects', 'icon' => 'folder-kanban'],
        'site-updates' => ['label' => 'Site Updates', 'icon' => 'megaphone'],
        'my-tasks' => ['label' => 'My Tasks', 'icon' => 'circle-check-big'],
        'worksheet' => ['label' => 'Work Sheet & Attendance', 'icon' => 'file-spreadsheet'],
        'analytics' => ['label' => 'Performance Analytics', 'icon' => 'trending-up']
    ],
    'HR & Admin' => [
        'hr-corner' => ['label' => 'HR Corner', 'icon' => 'briefcase']
    ],
    'Management' => [
        'leave-approval-mng' => ['label' => 'Leave Approval (Mng)', 'icon' => 'calendar-days'],
        'travel-exp-approval-mng' => ['label' => 'Travel Expense Approval (Mng)', 'icon' => 'wallet'],
        'hierarchy' => ['label' => 'Team Hierarchy', 'icon' => 'network'],
        'manager-mapping' => ['label' => 'Manager Mapping', 'icon' => 'users-2'],
        'overtime-mapping' => ['label' => 'Overtime Mapping', 'icon' => 'git-merge'],
        'travel-exp-mapping' => ['label' => 'Travel Expense Mapping', 'icon' => 'git-branch'],
        'travel-exp-settings' => ['label' => 'Travel Expense Settings', 'icon' => 'settings'],
        'password-reset-mng' => ['label' => 'Password Reset', 'icon' => 'key-round'],
        'employee-work-report' => ['label' => 'Employee Work Report', 'icon' => 'file-text'],
        'employees-profile' => ['label' => 'Employees Profile', 'icon' => 'users'],
        'employees-confiedential-documents' => ['label' => 'Employees Confiedential Documents', 'icon' => 'shield'],
        'employees-attendance' => ['label' => 'Employees Attendance', 'icon' => 'user-check'],
        'overtime-approval-mng' => ['label' => 'Overtime Approval', 'icon' => 'clock-8'],
        'shifts-management' => ['label' => 'Shift Management', 'icon' => 'calendar-clock'],
        'saturday-agenda' => ['label' => 'Saturday Agenda', 'icon' => 'calendar-days'],
        'site-expenses' => ['label' => 'Site Expenses', 'icon' => 'receipt'],
        'employees-performance' => ['label' => 'Employee Performance', 'icon' => 'bar-chart-3'],
    ],
    'System' => [
        'settings'                      => ['label' => 'Settings',                      'icon' => 'settings-2'],
        'confiedential-docs-permission' => ['label' => 'Confiedential Docs Permission', 'icon' => 'shield'],
        'geofence-locations'            => ['label' => 'Geofence Settings',             'icon' => 'map-pin'],
        'geofence-approval-mapping'     => ['label' => 'Geofence Approval Mapping',     'icon' => 'map-pinned'],
        'project-permissions'           => ['label' => 'Project Permission',             'icon' => 'shield-check'],
        'attendance-action-permissions' => ['label' => 'Attendance Action Permission',   'icon' => 'user-check'],
        'manual-leave-permissions'      => ['label' => 'Manual Leave Permission',        'icon' => 'file-edit'],
        'unsubmitted-ot-permissions'    => ['label' => 'Unsubmitted OT Permission',      'icon' => 'shield-alert'],
        'travel-meter-permissions'      => ['label' => 'Travel Meter Permission',        'icon' => 'camera'],
        'travel-meter-mode-permissions'  => ['label' => 'Meter Mode Permission',          'icon' => 'gauge'],
        'help'                          => ['label' => 'Help & Support',                 'icon' => 'help-circle'],
        'logout'                        => ['label' => 'Logout',                         'icon' => 'power']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar Role Access | Connect Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script src="components/sidebar-loader.js" defer></script>
    
    <style>
        .admin-page-container {
            padding: 1.5rem;
            width: 100%;
            box-sizing: border-box;
        }
        
        .admin-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.04);
            overflow: hidden;
            border: 1px solid #f0f2f5;
            min-height: calc(100vh - 120px);
            display: flex;
            flex-direction: column;
        }
        
        .tab-nav {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid #eef2f6;
            padding: 0 1.5rem;
        }
        
        .tab-btn {
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            background: none;
            border: none;
            outline: none;
        }
        
        .tab-btn.active {
            color: #6366f1;
            border-bottom-color: #6366f1;
        }
        
        .tab-content {
            padding: 2rem;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }
        
        .role-selector-wrap {
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #f1f5f9;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
        }
        
        .role-select {
            padding: 0.6rem 1rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 0.95rem;
            min-width: 250px;
            outline: none;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
            padding-bottom: 2rem;
        }
        
        .category-block {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #eef2f6;
            transition: transform 0.2s, box-shadow 0.2s;
            height: fit-content;
        }

        .category-block:hover {
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.05);
            border-color: #e0e7ff;
        }
        
        .category-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid #eef2f6;
            padding-bottom: 0.5rem;
        }
        
        .menu-perm-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0;
            transition: all 0.2s;
        }
        
        .menu-perm-item:not(:last-child) {
            border-bottom: 1px solid rgba(0,0,0,0.02);
        }
        
        .menu-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #475569;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .menu-icon-wrap {
            width: 32px;
            height: 32px;
            background: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            color: #6366f1;
        }
        
        /* Modern Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #6366f1;
        }
        
        input:checked + .slider:before {
            transform: translateX(20px);
        }
        
        .save-btn {
            background: #6366f1;
            color: #fff;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
            margin-top: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .save-btn:hover {
            background: #4f46e5;
            transform: translateY(-1px);
        }
        
        .save-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }
        
        /* Toast Notification */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 1rem 2rem;
            border-radius: 12px;
            background: #1e293b;
            color: white;
            font-weight: 500;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transform: translateY(200%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10000;
        }
        
        #toast.show {
            transform: translateY(0);
        }

        .project-access-toolbar {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f1f5f9;
            padding: 0.75rem 1rem;
            border-radius: 12px;
        }

        .project-access-search {
            width: 100%;
            max-width: 420px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0.62rem 0.8rem;
            outline: none;
            font-family: inherit;
            font-size: 0.92rem;
        }

        .project-perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
            padding-bottom: 1rem;
        }

        .user-perm-card {
            border: 1px solid #e5eaf1;
            border-radius: 14px;
            padding: 0.9rem;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.9rem;
        }

        .user-perm-right {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            align-items: flex-end;
            flex-shrink: 0;
        }

        .perm-switch-row {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.76rem;
            color: #475569;
            font-weight: 600;
        }

        .user-perm-meta {
            min-width: 0;
        }

        .user-perm-name {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-perm-sub {
            margin: 0.2rem 0 0;
            font-size: 0.8rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pill-role {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #eef2ff;
            color: #4f46e5;
            border: 1px solid #dde3ff;
            border-radius: 999px;
            padding: 0.12rem 0.45rem;
            margin-top: 0.35rem;
            font-size: 0.74rem;
            font-weight: 600;
        }

        .empty-box {
            grid-column: 1 / -1;
            text-align: center;
            padding: 2rem;
            border: 1px dashed #d6e0eb;
            border-radius: 12px;
            color: #6b7f93;
            background: #fbfdff;
        }
    </style>
</head>
<body class="el-1">

    <div class="dashboard-container">
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <header class="dh-nav-header">
                <div class="dh-nav-left" style="display:flex;align-items:center;gap:0.75rem;">
                    <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
                        <i data-lucide="menu" style="width:18px;height:18px;"></i>
                    </button>
                    <div>
                        <div class="dh-user-info">
                            <div class="dh-icon-orange">
                                <i data-lucide="shield-check" style="width:15px;height:15px;"></i>
                            </div>
                            <div class="dh-greeting">
                                <span class="dh-greeting-text">Admin Settings</span>
                                <span class="dh-greeting-name"><?php echo htmlspecialchars($username); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dh-nav-right">
                    <div class="dh-profile-box" id="profileDropdownContainer">
                        <div class="dh-profile-avatar" id="profileAvatarBtn">
                            <i data-lucide="user" style="width:17px;height:17px;"></i>
                        </div>
                    </div>
                </div>
            </header>

            <div class="admin-page-container">
                <div class="admin-card">
                    <nav class="tab-nav">
                        <button class="tab-btn active" data-tab="sidebar-access">Sidebar Access Role</button>
                    </nav>

                    <div class="tab-content tab-pane active" id="sidebar-access-tab">
                        <div class="role-selector-wrap">
                            <label for="roleSelector" style="font-weight: 600; color: #475569;">Select Role to Configure:</label>
                            <select id="roleSelector" class="role-select">
                                <option value="" disabled selected>Loading roles...</option>
                            </select>
                        </div>

                        <div id="permissionsContainer" class="permissions-grid">
                            <!-- Categories and items will be injected here -->
                            <div style="grid-column: 1 / -1; text-align: center; padding: 4rem; color: #94a3b8;">
                                <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>Loading menu items and permissions...</p>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; sticky: bottom; background: #fff; padding: 1.5rem 0; border-top: 1px solid #f0f2f5; margin-top: auto;">
                            <button id="savePermissionsBtn" class="save-btn" style="margin-top: 0;">
                                <i data-lucide="save" style="width:18px;height:18px;"></i>
                                Save All Changes
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <div id="toast">Changes saved successfully!</div>
    <div class="loader-overlay" id="loaderOverlay">
        <i class="fa-solid fa-spinner fa-spin" style="font-size: 3rem; color: #6366f1;"></i>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelector = document.getElementById('roleSelector');
            const permissionsContainer = document.getElementById('permissionsContainer');
            const saveBtn = document.getElementById('savePermissionsBtn');
            const loader = document.getElementById('loaderOverlay');
            const toast = document.getElementById('toast');
            
            let allPermissions = {};
            let roles = [];
            const menuStructure = <?php echo json_encode($menu_items); ?>;

            function escapeHtml(str) {
                return String(str)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function ensureRolePermObject(role) {
                if (!allPermissions || typeof allPermissions !== 'object') {
                    allPermissions = {};
                }
                if (!allPermissions[role] || Array.isArray(allPermissions[role]) || typeof allPermissions[role] !== 'object') {
                    allPermissions[role] = {};
                }
                return allPermissions[role];
            }

            function showToast(msg, type = 'success') {
                toast.textContent = msg;
                toast.style.background = type === 'success' ? '#1e293b' : '#ef4444';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 3000);
            }

            async function fetchPermissions() {
                try {
                    const response = await fetch('api/get_sidebar_permissions.php');
                    const data = await response.json();
                    
                    if (data.success) {
                        roles = data.roles;
                        allPermissions = data.permissions || {};

                        // Normalize empty role buckets to plain objects (not arrays)
                        roles.forEach(r => ensureRolePermObject(r));
                        
                        // Populate roles dropdown
                        roleSelector.innerHTML = roles.map(role => {
                            const safeRole = escapeHtml(role);
                            return `<option value="${safeRole}">${safeRole}</option>`;
                        }).join('');
                        
                        // Set current role to first one or admin if exists
                        const defaultRole = roles.includes('admin') ? 'admin' : roles[0];
                        roleSelector.value = defaultRole;
                        
                        renderPermissions(defaultRole);
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('Failed to fetch data', 'error');
                }
            }

            function renderPermissions(role) {
                let html = '';
                const rolePerms = ensureRolePermObject(role);

                for (const category in menuStructure) {
                    html += `
                        <div class="category-block">
                            <div class="category-title">
                                ${category}
                            </div>
                    `;

                    for (const menuId in menuStructure[category]) {
                        const item = menuStructure[category][menuId];
                        const hasAccess = rolePerms[menuId] === 1;
                        
                        html += `
                            <div class="menu-perm-item">
                                <div class="menu-info">
                                    <div class="menu-icon-wrap">
                                        <i data-lucide="${item.icon}" style="width:16px;height:16px;"></i>
                                    </div>
                                    <span>${item.label}</span>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" class="perm-checkbox" data-menu="${menuId}" ${hasAccess ? 'checked' : ''}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        `;
                    }

                    html += `</div>`;
                }

                permissionsContainer.innerHTML = html;
                lucide.createIcons();
                
                // Add event listeners to checkboxes to update object
                document.querySelectorAll('.perm-checkbox').forEach(cb => {
                    cb.addEventListener('change', function() {
                        const mid = this.dataset.menu;


                        
                        const bucket = ensureRolePermObject(roleSelector.value);
                        bucket[mid] = this.checked ? 1 : 0;
                    });
                });
            }

            roleSelector.addEventListener('change', function() {
                renderPermissions(this.value);
            });

            saveBtn.addEventListener('click', async function() {
                loader.style.display = 'flex';
                saveBtn.disabled = true;
                
                try {
                    const selectedRole = roleSelector.value;
                    const selectedPerms = ensureRolePermObject(selectedRole);
                    const response = await fetch('api/save_sidebar_permissions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        // Send only the currently selected role to avoid unrelated-role failures.
                        body: JSON.stringify({ role: selectedRole, permissions: selectedPerms })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        showToast('Permissions saved successfully!');
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('Error saving permissions', 'error');
                } finally {
                    loader.style.display = 'none';
                    saveBtn.disabled = false;
                }
            });

            fetchPermissions();
        });
    </script>
</body>
</html>
