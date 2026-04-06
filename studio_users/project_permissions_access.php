<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db_connect.php';

$user_id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, role, email FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit();
}

$username = $user['username'] ?? 'User';
$email = $user['email'] ?? '';
$role = trim((string)($user['role'] ?? ''));
$isAdmin = strtolower($role) === 'admin';

$canAccess = $isAdmin;
if (!$canAccess) {
    $permStmt = $pdo->prepare("SELECT can_access FROM sidebar_permissions WHERE role = ? AND menu_id = 'project-permissions' LIMIT 1");
    $permStmt->execute([$role]);
    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
    $canAccess = $permRow && isset($permRow['can_access']) && (int)$permRow['can_access'] === 1;
}

if (!$canAccess) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Permissions | Connect Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script src="components/sidebar-loader.js" defer></script>
    <style>
        .admin-page-container { padding: 1.5rem; width: 100%; box-sizing: border-box; }
        .admin-card {
            background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.04);
            overflow: hidden; border: 1px solid #f0f2f5; min-height: calc(100vh - 120px);
            display: flex; flex-direction: column;
        }
        .card-head {
            padding: 1.2rem 1.5rem; border-bottom: 1px solid #eef2f6;
            background: linear-gradient(135deg,#f8fafc 0%,#f5f8ff 100%);
            display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
        }
        .card-title { margin: 0; font-size: 1.06rem; color: #1e293b; }
        .card-sub { margin: 0.2rem 0 0; color: #64748b; font-size: 0.86rem; }
        .card-body { padding: 1.3rem; }
        .project-access-toolbar {
            margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;
            background: #f1f5f9; padding: 0.75rem 1rem; border-radius: 12px;
        }
        .project-access-search {
            width: 100%; max-width: 460px; border: 1px solid #cbd5e1;
            border-radius: 10px; padding: 0.62rem 0.8rem; outline: none; font-family: inherit;
            font-size: 0.92rem;
        }
        .project-perm-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1rem; padding-bottom: 1rem;
        }
        .user-perm-card {
            border: 1px solid #e5eaf1; border-radius: 14px; padding: 0.9rem;
            background: #fff; display: flex; align-items: center; justify-content: space-between; gap: 0.9rem;
        }
        .user-perm-meta { min-width: 0; }
        .user-perm-name {
            margin: 0; font-size: 0.95rem; font-weight: 700; color: #1e293b;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .user-perm-sub {
            margin: 0.2rem 0 0; font-size: 0.8rem; color: #64748b;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pill-role {
            display: inline-flex; align-items: center; gap: 0.3rem; background: #eef2ff; color: #4f46e5;
            border: 1px solid #dde3ff; border-radius: 999px; padding: 0.12rem 0.45rem;
            margin-top: 0.35rem; font-size: 0.74rem; font-weight: 600;
        }
        .user-perm-right { display: flex; flex-direction: column; gap: 0.45rem; align-items: flex-end; flex-shrink: 0; }
        .perm-switch-row {
            display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.76rem;
            color: #475569; font-weight: 600;
        }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1; transition: .3s; border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: white; transition: .3s; border-radius: 50%;
        }
        input:checked + .slider { background-color: #6366f1; }
        input:checked + .slider:before { transform: translateX(20px); }
        .save-wrap {
            display: flex; justify-content: flex-end; background: #fff; padding: 1rem 0 0.4rem;
            border-top: 1px solid #f0f2f5; margin-top: 0.5rem;
        }
        .save-btn {
            background: #6366f1; color: #fff; border: none; padding: 0.8rem 2rem; border-radius: 8px;
            font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(99,102,241,0.2); display: flex; align-items: center; gap: 0.5rem;
        }
        .save-btn:hover { background: #4f46e5; transform: translateY(-1px); }
        .save-btn:disabled { background: #94a3b8; cursor: not-allowed; box-shadow: none; }
        .empty-box {
            grid-column: 1 / -1; text-align: center; padding: 2rem; border: 1px dashed #d6e0eb;
            border-radius: 12px; color: #6b7f93; background: #fbfdff;
        }
        .loader-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.7); display: none; align-items: center; justify-content: center;
            z-index: 9999;
        }
        #toast {
            position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 2rem; border-radius: 12px;
            background: #1e293b; color: white; font-weight: 500; box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transform: translateY(200%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); z-index: 10000;
        }
        #toast.show { transform: translateY(0); }
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
                    <div class="dh-user-info">
                        <div class="dh-icon-orange"><i data-lucide="shield-check" style="width:15px;height:15px;"></i></div>
                        <div class="dh-greeting">
                            <span class="dh-greeting-text">Project Permission Control</span>
                            <span class="dh-greeting-name"><?php echo htmlspecialchars($username); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="admin-page-container">
                <div class="admin-card">
                    <div class="card-head">
                        <div>
                            <h2 class="card-title">Project Permissions</h2>
                            <p class="card-sub">Manage who can create projects and upload substage media files.</p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="project-access-toolbar">
                            <label for="projectPermSearch" style="font-weight:600;color:#475569;">Search User:</label>
                            <input type="text" id="projectPermSearch" class="project-access-search" placeholder="Search by username, email, or role...">
                        </div>

                        <div id="projectPermissionsContainer" class="project-perm-grid">
                            <div class="empty-box">
                                <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.3rem; margin-bottom: 0.6rem;"></i>
                                <p>Loading project permissions...</p>
                            </div>
                        </div>

                        <div class="save-wrap">
                            <button id="saveProjectPermBtn" class="save-btn">
                                <i data-lucide="shield-check" style="width:18px;height:18px;"></i>
                                Save Project Permission
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
            const projectPermSearch = document.getElementById('projectPermSearch');
            const projectPermissionsContainer = document.getElementById('projectPermissionsContainer');
            const saveProjectPermBtn = document.getElementById('saveProjectPermBtn');
            const loader = document.getElementById('loaderOverlay');
            const toast = document.getElementById('toast');

            let projectPermissionUsers = [];
            let projectPermissionState = {};

            function escapeHtml(str) {
                return String(str)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function showToast(msg, type = 'success') {
                toast.textContent = msg;
                toast.style.background = type === 'success' ? '#1e293b' : '#ef4444';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 3000);
            }

            function renderProjectPermissions() {
                const query = String(projectPermSearch.value || '').toLowerCase().trim();
                const rows = projectPermissionUsers.filter((u) => {
                    if (!query) return true;
                    const userText = `${u.username || ''} ${u.email || ''} ${u.role || ''}`.toLowerCase();
                    return userText.includes(query);
                });

                if (!rows.length) {
                    projectPermissionsContainer.innerHTML = `<div class="empty-box">No users found.</div>`;
                    return;
                }

                projectPermissionsContainer.innerHTML = rows.map((u) => {
                    const uid = Number(u.id) || 0;
                    const state = projectPermissionState[uid] || { can_create_project: 0, can_upload_substage_media: 0 };
                    const createChecked = Number(state.can_create_project || 0) === 1;
                    const mediaChecked = Number(state.can_upload_substage_media || 0) === 1;
                    const safeName = escapeHtml(u.username || `User ${uid}`);
                    const safeEmail = escapeHtml(u.email || '-');
                    const safeRole = escapeHtml(u.role || 'N/A');

                    return `
                        <div class="user-perm-card">
                            <div class="user-perm-meta">
                                <p class="user-perm-name">${safeName}</p>
                                <p class="user-perm-sub">${safeEmail}</p>
                                <span class="pill-role"><i data-lucide="badge-check" style="width:12px;height:12px;"></i>${safeRole}</span>
                            </div>
                            <div class="user-perm-right">
                                <label class="perm-switch-row" title="Allow this user to create projects">
                                    <span>Create</span>
                                    <span class="switch">
                                        <input type="checkbox" class="project-perm-checkbox" data-user-id="${uid}" data-perm="can_create_project" ${createChecked ? 'checked' : ''}>
                                        <span class="slider"></span>
                                    </span>
                                </label>
                                <label class="perm-switch-row" title="Allow this user to upload substage media">
                                    <span>Media Upload</span>
                                    <span class="switch">
                                        <input type="checkbox" class="project-perm-checkbox" data-user-id="${uid}" data-perm="can_upload_substage_media" ${mediaChecked ? 'checked' : ''}>
                                        <span class="slider"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    `;
                }).join('');

                document.querySelectorAll('.project-perm-checkbox').forEach((cb) => {
                    cb.addEventListener('change', function () {
                        const uid = Number(this.dataset.userId || 0);
                        const permKey = String(this.dataset.perm || '').trim();
                        if (!uid) return;
                        if (!projectPermissionState[uid]) {
                            projectPermissionState[uid] = { can_create_project: 0, can_upload_substage_media: 0 };
                        }
                        if (permKey !== 'can_create_project' && permKey !== 'can_upload_substage_media') return;
                        projectPermissionState[uid][permKey] = this.checked ? 1 : 0;
                    });
                });

                if (window.lucide) lucide.createIcons();
            }

            async function fetchProjectPermissions() {
                try {
                    const response = await fetch('api/get_project_create_permissions.php');
                    const data = await response.json();

                    if (!data.success) throw new Error(data.message || 'Failed to fetch project permissions');

                    projectPermissionUsers = Array.isArray(data.users) ? data.users : [];
                    projectPermissionState = {};
                    projectPermissionUsers.forEach((u) => {
                        const uid = Number(u.id) || 0;
                        if (uid > 0) {
                            projectPermissionState[uid] = {
                                can_create_project: Number(u.can_create_project || 0) === 1 ? 1 : 0,
                                can_upload_substage_media: Number(u.can_upload_substage_media || 0) === 1 ? 1 : 0
                            };
                        }
                    });

                    renderProjectPermissions();
                } catch (error) {
                    projectPermissionsContainer.innerHTML = `<div class="empty-box">Failed to load project permissions.</div>`;
                    showToast(error.message || 'Failed to load project permissions', 'error');
                }
            }

            async function saveProjectPermissions() {
                loader.style.display = 'flex';
                saveProjectPermBtn.disabled = true;

                try {
                    const response = await fetch('api/save_project_create_permissions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ permissions: projectPermissionState })
                    });

                    const data = await response.json();
                    if (!data.success) throw new Error(data.message || 'Failed to save project permissions');

                    showToast('Project permissions saved successfully!');
                } catch (error) {
                    showToast(error.message || 'Error saving project permissions', 'error');
                } finally {
                    loader.style.display = 'none';
                    saveProjectPermBtn.disabled = false;
                }
            }

            projectPermSearch.addEventListener('input', renderProjectPermissions);
            saveProjectPermBtn.addEventListener('click', saveProjectPermissions);

            fetchProjectPermissions();
        });
    </script>
</body>
</html>
