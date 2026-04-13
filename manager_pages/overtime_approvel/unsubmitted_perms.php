<?php
require_once __DIR__ . '/../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../../index.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT username, role, email FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit();
}

$username = $user['username'] ?? 'User';
$role = trim((string)($user['role'] ?? ''));
$isAdmin = strtolower($role) === 'admin';

// Default access: Only Admin can access this page
// Unless it's explicitly granted in sidebar_permissions for other roles
$canAccess = $isAdmin;
if (!$canAccess) {
    $permStmt = $pdo->prepare("SELECT can_access FROM sidebar_permissions WHERE role = ? AND menu_id = 'unsubmitted-ot-permissions' LIMIT 1");
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
    <title>Overtime Action Permissions | Connect Admin</title>
    <link rel="stylesheet" href="../../studio_users/style.css">
    <link rel="stylesheet" href="../../studio_users/header.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script>window.SIDEBAR_BASE_PATH = '../../studio_users/';</script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
    <style>
        .admin-page-container { padding: 1.5rem; width: 100%; box-sizing: border-box; }
        .admin-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); overflow: hidden; border: 1px solid #f0f2f5; min-height: calc(100vh - 120px); display: flex; flex-direction: column; }
        .card-head { padding: 1.2rem 1.5rem; border-bottom: 1px solid #eef2f6; background: linear-gradient(135deg,#f8fafc 0%,#f5f8ff 100%); }
        .card-title { margin: 0; font-size: 1.06rem; color: #1e293b; }
        .card-sub { margin: 0.2rem 0 0; color: #64748b; font-size: 0.86rem; }
        .card-body { padding: 1.3rem; }
        .toolbar { margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; background: #f1f5f9; padding: 0.75rem 1rem; border-radius: 12px; }
        .search { width: 100%; max-width: 460px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0.62rem 0.8rem; outline: none; font-family: inherit; font-size: 0.92rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1rem; padding-bottom: 1rem; }
        .card { border: 1px solid #e5eaf1; border-radius: 14px; padding: 0.9rem; background: #fff; display: flex; align-items: center; justify-content: space-between; gap: 0.9rem; }
        .meta { min-width: 0; }
        .name { margin: 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sub { margin: 0.2rem 0 0; font-size: 0.8rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pill { display: inline-flex; align-items: center; gap: 0.3rem; background: #eef2ff; color: #4f46e5; border: 1px solid #dde3ff; border-radius: 999px; padding: 0.12rem 0.45rem; margin-top: 0.35rem; font-size: 0.74rem; font-weight: 600; }
        .right { display: flex; flex-direction: column; gap: 0.45rem; align-items: flex-end; flex-shrink: 0; }
        .switch-row { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.76rem; color: #475569; font-weight: 600; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #6366f1; }
        input:checked + .slider:before { transform: translateX(20px); }
        .save-wrap { display: flex; justify-content: flex-end; background: #fff; padding: 1rem 0 0.4rem; border-top: 1px solid #f0f2f5; margin-top: 0.5rem; }
        .save-btn { background: #6366f1; color: #fff; border: none; padding: 0.8rem 2rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(99,102,241,0.2); display: flex; align-items: center; gap: 0.5rem; }
        .save-btn:disabled { background: #94a3b8; cursor: not-allowed; box-shadow: none; }
        .empty { grid-column: 1 / -1; text-align: center; padding: 2rem; border: 1px dashed #d6e0eb; border-radius: 12px; color: #6b7f93; background: #fbfdff; }
        .loader-overlay { position: fixed; inset: 0; background: rgba(255,255,255,0.7); display: none; align-items: center; justify-content: center; z-index: 9999; }
        #toast { position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 2rem; border-radius: 12px; background: #1e293b; color: white; font-weight: 500; box-shadow: 0 8px 32px rgba(0,0,0,0.1); transform: translateY(200%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); z-index: 10000; }
        #toast.show { transform: translateY(0); }
    </style>
</head>
<body class="el-1">
<div class="dashboard-container">
    <div id="sidebar-mount"></div>
    <main class="main-content">
        <header class="dh-nav-header">
            <div class="dh-nav-left" style="display:flex;align-items:center;gap:0.75rem;">
                <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar"><i data-lucide="menu" style="width:18px;height:18px;"></i></button>
                <div class="dh-user-info">
                    <div class="dh-icon-orange"><i data-lucide="shield-alert" style="width:15px;height:15px;"></i></div>
                    <div class="dh-greeting">
                        <span class="dh-greeting-text">Unsubmitted OT Permissions</span>
                        <span class="dh-greeting-name"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-page-container">
            <div class="admin-card">
                <div class="card-head">
                    <h2 class="card-title">Unsubmitted Overtime Action Permissions</h2>
                    <p class="card-sub">Configure which users can approve or reject overtime even before the employee has submitted their formal report.</p>
                </div>
                <div class="card-body">
                    <div class="toolbar">
                        <label for="search" style="font-weight:600;color:#475569;">Search User:</label>
                        <input type="text" id="search" class="search" placeholder="Search by username, email, or role...">
                    </div>

                    <div id="container" class="grid">
                        <div class="empty"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.3rem;margin-bottom:0.6rem;"></i><p>Loading permissions...</p></div>
                    </div>

                    <div class="save-wrap">
                        <button id="saveBtn" class="save-btn"><i data-lucide="save" style="width:18px;height:18px;"></i>Save Permissions</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="toast">Changes saved successfully!</div>
<div class="loader-overlay" id="loader"><i class="fa-solid fa-spinner fa-spin" style="font-size: 3rem; color: #6366f1;"></i></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const search = document.getElementById('search');
    const container = document.getElementById('container');
    const saveBtn = document.getElementById('saveBtn');
    const loader = document.getElementById('loader');
    const toast = document.getElementById('toast');

    let users = [];
    let state = {};

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    }
    function showToast(msg, type='success') {
        toast.textContent = msg;
        toast.style.background = type === 'success' ? '#1e293b' : '#ef4444';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function render() {
        const q = String(search.value || '').toLowerCase().trim();
        const filteredUsers = users.filter(u => !q || `${u.username||''} ${u.email||''} ${u.role||''}`.toLowerCase().includes(q));
        
        if (!filteredUsers.length) {
            container.innerHTML = `<div class="empty">No users found.</div>`;
            return;
        }

        container.innerHTML = filteredUsers.map(u => {
            const uid = Number(u.id) || 0;
            const st = state[uid] || { can_action_unsubmitted: 0, can_action_expired: 0, can_action_completed: 0 };
            return `
                <div class="card" style="height: auto; flex-direction: column; align-items: stretch; gap: 1rem; padding: 1.2rem;">
                    <div class="meta" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <div>
                            <p class="name">${escapeHtml(u.username || ('User ' + uid))}</p>
                            <p class="sub">${escapeHtml(u.email || '-')}</p>
                            <span class="pill"><i data-lucide="badge-check" style="width:12px;height:12px;"></i>${escapeHtml(u.role || 'N/A')}</span>
                        </div>
                    </div>
                    <div class="right" style="flex-direction: row; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-top: 1px solid #f1f5f9; padding-top: 1rem; align-items: center; width: 100%;">
                        <label class="switch-row" style="flex: 1; min-width: 140px; white-space: nowrap;">
                            <span>Bypass Submit</span>
                            <span class="switch" style="transform: scale(0.9);">
                                <input type="checkbox" class="perm" data-user-id="${uid}" data-key="can_action_unsubmitted" ${Number(st.can_action_unsubmitted)===1?'checked':''}>
                                <span class="slider"></span>
                            </span>
                        </label>
                        <label class="switch-row" style="flex: 1; min-width: 140px; white-space: nowrap;">
                            <span>Bypass Expiry</span>
                            <span class="switch" style="transform: scale(0.9);">
                                <input type="checkbox" class="perm" data-user-id="${uid}" data-key="can_action_expired" ${Number(st.can_action_expired)===1?'checked':''}>
                                <span class="slider"></span>
                            </span>
                        </label>
                        <label class="switch-row" style="flex: 1; min-width: 140px; white-space: nowrap;">
                            <span>Modify Completed</span>
                            <span class="switch" style="transform: scale(0.9);">
                                <input type="checkbox" class="perm" data-user-id="${uid}" data-key="can_action_completed" ${Number(st.can_action_completed)===1?'checked':''}>
                                <span class="slider"></span>
                            </span>
                        </label>
                    </div>
                </div>`;
        }).join('');

        document.querySelectorAll('.perm').forEach(cb => {
            cb.addEventListener('change', function() {
                const uid = Number(this.dataset.userId || 0);
                const key = String(this.dataset.key || '');
                if (!uid || !key) return;
                if (!state[uid]) state[uid] = { can_action_unsubmitted: 0, can_action_expired: 0, can_action_completed: 0 };
                state[uid][key] = this.checked ? 1 : 0;
            });
        });

        if (window.lucide) lucide.createIcons();
    }

    async function fetchData() {
        try {
            const res = await fetch('api/get_unsubmitted_perms.php');
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to fetch data');
            users = Array.isArray(data.users) ? data.users : [];
            state = {};
            users.forEach(u => {
                const uid = Number(u.id) || 0;
                if (uid > 0) {
                    state[uid] = {
                        can_action_unsubmitted: Number(u.can_action_unsubmitted || 0) === 1 ? 1 : 0,
                        can_action_expired: Number(u.can_action_expired || 0) === 1 ? 1 : 0,
                        can_action_completed: Number(u.can_action_completed || 0) === 1 ? 1 : 0
                    };
                }
            });
            render();
        } catch (e) {
            container.innerHTML = `<div class="empty">Failed to load permissions.</div>`;
            showToast(e.message || 'Failed to load', 'error');
        }
    }

    async function saveData() {
        loader.style.display = 'flex';
        saveBtn.disabled = true;
        try {
            const res = await fetch('api/save_unsubmitted_perms.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ permissions: state })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to save');
            showToast('Permissions saved successfully!');
        } catch (e) {
            showToast(e.message || 'Error saving permissions', 'error');
        } finally {
            loader.style.display = 'none';
            saveBtn.disabled = false;
        }
    }

    search.addEventListener('input', render);
    saveBtn.addEventListener('click', saveData);
    fetchData();
});
</script>
</body>
</html>
