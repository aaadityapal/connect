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
$role     = trim((string)($user['role'] ?? ''));
$isAdmin  = strtolower($role) === 'admin';

// Check access via sidebar_permissions OR admin
$canAccess = $isAdmin;
if (!$canAccess) {
    $permStmt = $pdo->prepare("SELECT can_access FROM sidebar_permissions WHERE role = ? AND menu_id = 'travel-meter-mode-permissions' LIMIT 1");
    $permStmt->execute([$role]);
    $permRow   = $permStmt->fetch(PDO::FETCH_ASSOC);
    $canAccess = $permRow && isset($permRow['can_access']) && (int)$permRow['can_access'] === 1;
}

if (!$canAccess) {
    header("Location: index.php");
    exit();
}

// Only Bike and Car modes are relevant for meter photo mode selection
$meterModes = ['Bike', 'Car'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Meter Mode Permissions | Connect Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script src="components/sidebar-loader.js" defer></script>
    <style>
        /* ── Layout ── */
        .admin-page-container { padding: 1.5rem; width: 100%; box-sizing: border-box; }
        .admin-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); overflow: hidden; border: 1px solid #f0f2f5; min-height: calc(100vh - 120px); display: flex; flex-direction: column; }
        .card-head { padding: 1.2rem 1.5rem; border-bottom: 1px solid #eef2f6; background: linear-gradient(135deg,#f8fafc 0%,#f5f8ff 100%); }
        .card-title { margin: 0; font-size: 1.06rem; color: #1e293b; font-weight: 700; }
        .card-sub { margin: 0.2rem 0 0; color: #64748b; font-size: 0.86rem; }
        .card-body { padding: 1.3rem; flex: 1; display: flex; flex-direction: column; }

        /* ── Info Banner ── */
        .info-banner {
            display: flex; align-items: flex-start; gap: 0.75rem;
            background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px;
            padding: 1rem 1.2rem; margin-bottom: 1.2rem;
            font-size: 0.88rem; color: #0c4a6e;
        }
        .info-banner i { flex-shrink: 0; margin-top: 2px; color: #0284c7; }
        .info-banner strong { display: block; font-weight: 700; margin-bottom: 0.2rem; }

        /* ── Legend Bar ── */
        .legend-bar { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; padding: 0.65rem 1rem; background: #f8fafc; border: 1px solid #eef2f6; border-radius: 10px; margin-bottom: 1.2rem; font-size: 0.82rem; color: #475569; font-weight: 500; }
        .legend-item { display: flex; align-items: center; gap: 0.4rem; }
        .legend-dot { width: 8px; height: 8px; border-radius: 50%; }
        .dot-on  { background: #6366f1; }
        .dot-off { background: #cbd5e1; }

        /* ── Toolbar ── */
        .toolbar { margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; background: #f1f5f9; padding: 0.75rem 1rem; border-radius: 12px; flex-wrap: wrap; }
        .search { width: 100%; max-width: 460px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0.62rem 0.8rem; outline: none; font-family: inherit; font-size: 0.92rem; }

        /* ── Grid ── */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); gap: 1.1rem; padding-bottom: 1rem; }

        /* ── User Card ── */
        .ucard {
            border: 1px solid #e8edf4; border-radius: 16px; padding: 1rem 1.1rem;
            background: #fff; display: flex; flex-direction: column; gap: 0.75rem;
            transition: box-shadow 0.2s, border-color 0.2s;
        }
        .ucard:hover { box-shadow: 0 4px 16px rgba(99,102,241,0.07); border-color: #e0e7ff; }
        .ucard-top { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
        .ucard-meta { min-width: 0; }
        .ucard-name { margin: 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ucard-sub  { margin: 0.15rem 0 0; font-size: 0.78rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pill { display: inline-flex; align-items: center; gap: 0.3rem; background: #eef2ff; color: #4f46e5; border: 1px solid #dde3ff; border-radius: 999px; padding: 0.12rem 0.5rem; margin-top: 0.3rem; font-size: 0.73rem; font-weight: 600; }

        /* ── Modes inside card ── */
        .modes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.5rem; }
        .mode-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.55rem 0.75rem; border: 1px solid #f1f5f9; border-radius: 9px;
            background: #fafbfc; font-size: 0.83rem; color: #334155; font-weight: 500; gap: 0.5rem;
            transition: border-color 0.2s, background 0.2s;
        }
        .mode-row:hover { border-color: #e0e7ff; background: #f5f8ff; }
        .mode-row-label { display: flex; align-items: center; gap: 0.5rem; }
        .mode-state-label { font-size: 0.7rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 999px; }
        .state-on  { background: #eef2ff; color: #4f46e5; }
        .state-off { background: #f1f5f9; color: #64748b; }

        /* ── Toggle Switch ── */
        .switch { position: relative; display: inline-block; width: 38px; height: 21px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background-color: #cbd5e1; transition: .25s; border-radius: 21px; }
        .slider:before { position: absolute; content: ""; height: 15px; width: 15px; left: 3px; bottom: 3px; background-color: white; transition: .25s; border-radius: 50%; }
        input:checked + .slider { background-color: #6366f1; }
        input:checked + .slider:before { transform: translateX(17px); }

        /* ── Footer ── */
        .save-wrap { display: flex; justify-content: flex-end; background: #fff; padding: 1rem 0 0.4rem; border-top: 1px solid #f0f2f5; margin-top: auto; }
        .save-btn { background: #6366f1; color: #fff; border: none; padding: 0.8rem 2rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(99,102,241,0.2); display: flex; align-items: center; gap: 0.5rem; }
        .save-btn:hover { background: #4f46e5; transform: translateY(-1px); }
        .save-btn:disabled { background: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

        /* ── Empty / Loader ── */
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
                <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar">
                    <i data-lucide="menu" style="width:18px;height:18px;"></i>
                </button>
                <div class="dh-user-info">
                    <div class="dh-icon-orange">
                        <i data-lucide="gauge" style="width:15px;height:15px;"></i>
                    </div>
                    <div class="dh-greeting">
                        <span class="dh-greeting-text">Travel Meter Mode Permissions</span>
                        <span class="dh-greeting-name"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-page-container">
            <div class="admin-card">
                <div class="card-head">
                    <h2 class="card-title">Travel Meter Mode Permissions</h2>
                    <p class="card-sub">Control how meter photos are sourced in the approval modal for Bike and Car travel expenses — per user.</p>
                </div>
                <div class="card-body">

                    <div class="info-banner">
                        <i data-lucide="info" style="width:16px;height:16px;"></i>
                        <div>
                            <strong>How this works</strong>
                            When <strong>ON</strong> — the approval modal shows the <em>punch-in and punch-out attendance photos</em> captured at the start/end of the workday.<br>
                            When <strong>OFF</strong> — the modal shows the <em>uploaded meter start &amp; end photos</em> the user manually captured when submitting the expense.
                        </div>
                    </div>

                    <div class="legend-bar">
                        <span style="font-weight:600;color:#1e293b;">Toggle meaning:</span>
                        <span class="legend-item"><span class="legend-dot dot-on"></span> ON = Show punch-in/out attendance photos</span>
                        <span class="legend-item"><span class="legend-dot dot-off"></span> OFF = Show uploaded meter photos</span>
                    </div>

                    <div class="toolbar">
                        <label for="search" style="font-weight:600;color:#475569;white-space:nowrap;">Search User:</label>
                        <input type="text" id="search" class="search" placeholder="Search by name, email, or role...">
                    </div>

                    <div id="container" class="grid">
                        <div class="empty">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size:1.3rem;margin-bottom:0.6rem;"></i>
                            <p>Loading permissions...</p>
                        </div>
                    </div>

                    <div class="save-wrap">
                        <button id="saveBtn" class="save-btn">
                            <i data-lucide="save" style="width:18px;height:18px;"></i>
                            Save Meter Mode Permissions
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="toast"></div>
<div class="loader-overlay" id="loader">
    <i class="fa-solid fa-spinner fa-spin" style="font-size:3rem;color:#6366f1;"></i>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const search    = document.getElementById('search');
    const container = document.getElementById('container');
    const saveBtn   = document.getElementById('saveBtn');
    const loader    = document.getElementById('loader');
    const toast     = document.getElementById('toast');

    // Only Bike and Car are relevant for meter mode
    const METER_MODES = <?php echo json_encode($meterModes); ?>;

    const MODE_ICONS = {
        'Bike': 'fa-bicycle',
        'Car':  'fa-car'
    };

    let users = [];
    // state[userId][mode]: 0 = punch photos (toggle ON), 1 = meter photos (toggle OFF)
    let state = {};

    function esc(str) {
        return String(str).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    }

    function showToast(msg, type = 'success') {
        toast.textContent = msg;
        toast.style.background = type === 'success' ? '#1e293b' : '#ef4444';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function render() {
        const q = String(search.value || '').toLowerCase().trim();
        const filtered = users.filter(u => !q || `${u.username||''} ${u.email||''} ${u.role||''}`.toLowerCase().includes(q));

        if (!filtered.length) {
            container.innerHTML = `<div class="empty">No users found.</div>`;
            return;
        }

        container.innerHTML = filtered.map(u => {
            const uid = Number(u.id) || 0;
            const userState = state[uid] || {};

            const modesHtml = METER_MODES.map(mode => {
                // isOn = true means toggle ON = punch photos (meter_mode = 0 in DB)
                const isOn  = (userState[mode] || 0) === 0 && userState.hasOwnProperty(mode);
                const icon  = MODE_ICONS[mode] || 'fa-circle';
                const stateLabel = isOn
                    ? `<span class="mode-state-label state-on">Punch Photo</span>`
                    : `<span class="mode-state-label state-off">Meter Photo</span>`;
                return `
                <div class="mode-row" id="row-${uid}-${esc(mode)}">
                    <div class="mode-row-label">
                        <i class="fa-solid ${icon}" style="font-size:0.78rem;color:#94a3b8;width:14px;text-align:center;"></i>
                        <span>${esc(mode)}</span>
                        ${stateLabel}
                    </div>
                    <label class="switch">
                        <input type="checkbox" class="perm"
                            data-user-id="${uid}"
                            data-mode="${esc(mode)}"
                            ${isOn ? 'checked' : ''}>
                        <span class="slider"></span>
                    </label>
                </div>`;
            }).join('');

            return `
            <div class="ucard">
                <div class="ucard-top">
                    <div class="ucard-meta">
                        <p class="ucard-name">${esc(u.username || ('User ' + uid))}</p>
                        <p class="ucard-sub">${esc(u.email || '-')}</p>
                        <span class="pill">
                            <i data-lucide="badge-check" style="width:11px;height:11px;"></i>
                            ${esc(u.role || 'N/A')}
                        </span>
                    </div>
                </div>
                <div class="modes-grid">
                    ${modesHtml}
                </div>
            </div>`;
        }).join('');

        // Attach change listeners — also update the state label text live
        document.querySelectorAll('.perm').forEach(cb => {
            cb.addEventListener('change', function () {
                const uid  = Number(this.dataset.userId || 0);
                const mode = String(this.dataset.mode || '');
                if (!uid || !mode) return;
                if (!state[uid]) state[uid] = {};
                // Toggle ON (checked) = punch photos → store meter_mode = 0
                // Toggle OFF (unchecked) = meter photos → store meter_mode = 1
                state[uid][mode] = this.checked ? 0 : 1;

                // Update label live
                const row = document.getElementById(`row-${uid}-${mode}`);
                if (row) {
                    const lbl = row.querySelector('.mode-state-label');
                    if (lbl) {
                        lbl.textContent  = this.checked ? 'Punch Photo' : 'Meter Photo';
                        lbl.className    = 'mode-state-label ' + (this.checked ? 'state-on' : 'state-off');
                    }
                }
            });
        });

        if (window.lucide) lucide.createIcons();
    }

    async function fetchData() {
        try {
            const res  = await fetch('api/get_travel_meter_mode_perms.php');
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to fetch');

            users = Array.isArray(data.users) ? data.users : [];
            state = {};

            users.forEach(u => {
                const uid = Number(u.id) || 0;
                if (!uid) return;
                state[uid] = {};
                // Default: meter_mode = 1 = meter photos (toggle OFF) for all modes
                METER_MODES.forEach(m => { state[uid][m] = 1; });
                if (Array.isArray(u.perms)) {
                    u.perms.forEach(p => {
                        if (METER_MODES.includes(p.mode)) {
                            state[uid][p.mode] = p.meter_mode;
                        }
                    });
                }
            });

            render();
        } catch (e) {
            container.innerHTML = `<div class="empty">Failed to load: ${esc(e.message)}</div>`;
            showToast(e.message || 'Failed to load', 'error');
        }
    }

    async function saveData() {
        loader.style.display = 'flex';
        saveBtn.disabled = true;
        try {
            const res  = await fetch('api/save_travel_meter_mode_perms.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ permissions: state })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to save');
            showToast('Meter mode permissions saved successfully!');
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
