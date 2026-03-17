<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$username = $user ? $user['username'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrix Hierarchy Canvas | Connect</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="components/modals/custom-alert-modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --canvas-bg: #f8fafc;
            --sidebar-bg: #ffffff;
            --accent: #6366f1;
            --accent-soft: rgba(99, 102, 241, 0.08);
            --border: #e2e8f0;
            --node-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--canvas-bg);
            margin: 0;
            overflow: hidden;
        }

        .matrix-layout {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* Staff Roster Sidebar */
        .roster-sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 100;
            box-shadow: 10px 0 30px rgba(0,0,0,0.02);
        }

        .roster-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }

        .roster-header h2 {
            margin: 0;
            font-size: 1.1rem;
            color: #1e293b;
        }

        .roster-search {
            margin-top: 15px;
            position: relative;
        }

        .roster-search input {
            width: 100%;
            padding: 8px 12px 8px 34px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 0.85rem;
            outline: none;
        }

        .roster-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .roster-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* Draggable Basic User Card */
        .roster-item {
            background: white;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: grab;
            transition: all 0.2s;
        }

        .roster-item:hover {
            border-color: var(--accent);
            transform: translateX(4px);
            box-shadow: 0 4px 6px -1px var(--accent-soft);
        }

        /* Main Workspace */
        .canvas-area {
            flex: 1;
            position: relative;
            overflow: auto;
            background-image: 
                radial-gradient(#e2e8f0 1.5px, transparent 1.5px);
            background-size: 40px 40px;
            padding: 100px;
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
        }

        /* Zoom Controls */
        .zoom-panel {
            position: fixed;
            bottom: 30px;
            left: 310px; /* offset for roster sidebar */
            background: white;
            padding: 8px;
            border-radius: 100px;
            display: flex;
            gap: 10px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0,0,0,0.05);
            z-index: 1000;
            pointer-events: auto;
        }

        .zoom-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid #f1f5f9;
            background: white;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .zoom-btn:hover {
            background: #f8fafc;
            color: var(--accent);
            border-color: var(--accent-soft);
        }

        .zoom-level {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1e293b;
            padding: 0 10px;
            min-width: 50px;
            justify-content: center;
        }

        .canvas-container {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 80px;
            margin: 0 auto;
            min-width: min-content;
            transform-origin: top center;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Top Bar Overlay */
        .canvas-controls {
            position: fixed;
            top: 20px;
            left: 580px; /* sidebar + roster padding */
            right: 40px;
            pointer-events: none;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
        }

        .controls-panel {
            pointer-events: auto;
            background: white;
            padding: 10px 24px;
            border-radius: 100px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Manager Zone Components */
        .manager-card-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .user-canvas-node {
            background: white;
            padding: 14px 18px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: var(--node-shadow);
            display: flex;
            align-items: center;
            gap: 14px;
            width: 260px;
            position: relative;
            z-index: 10;
        }

        .btn-remove-relation {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            cursor: pointer;
            border: 2px solid white;
            transition: transform 0.2s;
            opacity: 0;
        }

        .user-canvas-node:hover .btn-remove-relation {
            opacity: 1;
            transform: scale(1.1);
        }

        .node-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
        }

        .node-content { flex: 1; min-width: 0; }
        .node-name { display: block; font-weight: 700; color: #1e293b; font-size: 0.95rem; }
        .node-role { font-size: 0.75rem; color: #64748b; }

        .drop-zone {
            margin-top: 20px;
            padding: 20px;
            min-height: 100px;
            min-width: 300px;
            border: 2px dashed #e2e8f0;
            border-radius: 20px;
            background: rgba(248, 250, 252, 0.5);
            display: flex;
            justify-content: center;
            gap: 40px;
            transition: all 0.3s;
        }

        .drop-zone.drag-over {
            border-color: var(--accent);
            background: var(--accent-soft);
        }

        /* Ghost & Animation */
        .sortable-ghost {
            opacity: 0.4;
            transform: scale(0.95);
        }

        .save-btn {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            color: white;
            padding: 10px 24px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .save-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4); }

        /* Toast */
        #hierarchyToast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1e293b;
            color: white;
            padding: 14px 24px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            transform: translateY(150%);
            transition: transform 0.3s cubic-bezier(0.18, 0.89, 0.32, 1.28);
            z-index: 10000;
        }
        #hierarchyToast.show { transform: translateY(0); }

        @media (max-width: 1024px) {
            .roster-sidebar { display: none; }
            .canvas-controls { left: 100px; }
        }
    </style>
</head>
<body>

    <div class="matrix-layout dashboard-container">
        <!-- Sidebar -->
        <div id="sidebar-mount"></div>

        <!-- Roster Sidebar -->
        <aside class="roster-sidebar">
            <div class="roster-header">
                <h2>Team Roster</h2>
                <div class="roster-search">
                    <i class="fa-solid fa-user-plus"></i>
                    <input type="text" id="staffFilter" placeholder="Search to add...">
                </div>
            </div>
            <div id="rosterList" class="roster-list">
                <!-- Populated by JS -->
                <div style="text-align: center; color: #94a3b8; padding-top: 40px;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                </div>
            </div>
        </aside>

        <!-- Canvas Controls -->
        <div class="canvas-controls">
            <div class="controls-panel">
                <div style="font-weight: 700; font-size: 0.95rem; color: #1e293b;">Matrix Hierarchy</div>
                <div style="width: 1px; height: 20px; background: #e2e8f0;"></div>
                <div id="changeStats" style="font-size: 0.75rem; color: #64748b;">
                    Drag users from the roster to any manager
                </div>
                <button id="saveMatrixBtn" class="save-btn">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    Sync Matrix
                </button>
            </div>
        </div>

        <!-- Canvas Area -->
        <main class="canvas-area">
            <!-- Zoom Panel -->
            <div class="zoom-panel">
                <button id="zoomOut" class="zoom-btn" title="Zoom Out"><i class="fa-solid fa-minus"></i></button>
                <div class="zoom-level" id="zoomLevel">100%</div>
                <button id="zoomIn" class="zoom-btn" title="Zoom In"><i class="fa-solid fa-plus"></i></button>
                <div style="width: 1px; height: 20px; background: #f1f5f9; align-self: center;"></div>
                <button id="zoomReset" class="zoom-btn" title="Reset View"><i class="fa-solid fa-maximize"></i></button>
            </div>

            <div id="canvasRoot" class="canvas-container">
                <!-- Managers and their nested reports will bloom from here -->
                <div style="padding: 100px; text-align: center; color: #94a3b8; font-size: 1rem;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    Synthesizing organizational matrix...
                </div>
            </div>
        </main>
    </div>

    <!-- Toast -->
    <div id="hierarchyToast">
        <i class="fa-solid fa-circle-check" style="color: #22c55e;"></i>
        <span id="toastMsg">Matrix Hierarchy Updated!</span>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="components/sidebar-loader.js" defer></script>
    <script src="components/modals/custom-alert-modal.js" defer></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let allUsers = [];
            let currentRelations = []; // Array of {subordinate_id, manager_id}
            let pendingUpdates = []; // Track changes to sync
            
            const rosterList = document.getElementById('rosterList');
            const canvasRoot = document.getElementById('canvasRoot');
            const saveBtn = document.getElementById('saveMatrixBtn');
            const staffFilter = document.getElementById('staffFilter');

            // --- DATA FLOW ---
            async function loadMatrix() {
                try {
                    const response = await fetch('api/fetch_hierarchy.php');
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch(e) {
                        console.error("Malformed JSON:", text);
                        canvasRoot.innerHTML = `<div style="padding:40px; color:#ef4444; text-align:center;">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size:2rem; margin-bottom:1rem;"></i>
                            <p>Failed to load data. The server returned an invalid response.</p>
                        </div>`;
                        return;
                    }

                    if (data.success) {
                        allUsers = data.users || [];
                        currentRelations = data.relations || [];
                        renderUI();
                    } else {
                        const errMsg = data.details ? `${data.error}: ${data.details}` : data.error;
                        throw new Error(errMsg || 'Unknown API Error');
                    }
                } catch (err) {
                    console.error("Load Task Failed:", err);
                    canvasRoot.innerHTML = `<div style="padding:40px; color:#ef4444; text-align:center;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size:2rem; margin-bottom:1rem;"></i>
                        <p><b>Error:</b> ${err.message}</p>
                        <button onclick="location.reload()" style="margin-top:1rem; padding:8px 16px; border-radius:8px; background:#ef4444; color:white; border:none; cursor:pointer;">Retry</button>
                    </div>`;
                }
            }

            function renderUI() {
                renderRoster();
                renderCanvas();
                initDragDrop();
            }

            // --- ROSTER (Library of Users) ---
            function renderRoster() {
                rosterList.innerHTML = '';
                const term = staffFilter.value.toLowerCase();
                
                if (allUsers.length === 0) {
                    rosterList.innerHTML = '<div style="text-align:center; color:#94a3b8; font-size:0.8rem; padding:20px;">No users found.</div>';
                    return;
                }

                allUsers.forEach(user => {
                    if (term && !user.username.toLowerCase().includes(term)) return;
                    
                    const div = document.createElement('div');
                    div.className = 'roster-item';
                    div.id = `roster-user-${user.id}`;
                    div.dataset.userId = user.id;
                    div.dataset.role = user.role || '';
                    
                    div.innerHTML = `
                        <div class="node-avatar" style="background: ${getAvatarColor(user.username)}; width:30px; height:30px; font-size:0.7rem;">
                            ${getInitials(user.username)}
                        </div>
                        <div class="node-content">
                            <span class="node-name" style="font-size:0.8rem;">${user.username}</span>
                            <span class="node-role" style="font-size:0.65rem;">${user.position || 'Staff'}</span>
                        </div>
                        <i class="fa-solid fa-plus" style="color:#cbd5e1; font-size:0.75rem;"></i>
                    `;
                    rosterList.appendChild(div);
                });
            }

            // --- CANVAS (The Organization) ---
            function renderCanvas() {
                canvasRoot.innerHTML = '';
                
                const admins = allUsers.filter(u => u.role?.toLowerCase() === 'admin');
                
                if (admins.length === 0) {
                    canvasRoot.innerHTML = `<div style="text-align:center; padding:100px; color:#64748b;">
                        <i class="fa-solid fa-crown" style="font-size:3rem; color:#e2e8f0; margin-bottom:1.5rem; display:block;"></i>
                        <h3 style="margin:0; color:#1e293b;">No Admin Structure Found</h3>
                        <p style="max-width:300px; margin:10px auto; font-size:0.9rem;">To start your hierarchy, ensure you have users with the <b>Admin</b> role.</p>
                    </div>`;
                    return;
                }

                const topLevelWrapper = document.createElement('div');
                topLevelWrapper.style.display = 'flex';
                topLevelWrapper.style.gap = '60px'; // Wider gap for matrix
                
                admins.forEach(admin => {
                    renderBranch(admin, topLevelWrapper);
                });
                
                canvasRoot.appendChild(topLevelWrapper);
                
                // Auto-center
                setTimeout(() => {
                    const canvas = document.querySelector('.canvas-area');
                    const root = document.getElementById('canvasRoot');
                    if (canvas && root && root.offsetWidth > canvas.offsetWidth) {
                        const centerX = (root.offsetWidth - canvas.offsetWidth) / 2;
                        canvas.scrollLeft = Math.max(0, centerX);
                    }
                }, 100);
            }

            function renderBranch(user, container) {
                const wrapper = document.createElement('div');
                wrapper.className = 'manager-card-wrapper';
                
                const node = document.createElement('div');
                node.className = 'user-canvas-node';
                if (user.role?.toLowerCase() === 'admin') node.style.borderColor = '#6366f1';
                
                node.innerHTML = `
                    <div class="node-avatar" style="background: ${getAvatarColor(user.username)}">
                        ${getInitials(user.username)}
                    </div>
                    <div class="node-content">
                        <span class="node-name">${user.username}</span>
                        <span class="node-role">${user.role || 'Staff'}</span>
                    </div>
                `;
                
                // Add Remove Button only if it's a subordinate (not top level admin)
                // In this simplified view, we'll let user's remove ANY relation.
                
                wrapper.appendChild(node);
                
                const reportsZone = document.createElement('div');
                reportsZone.className = 'drop-zone';
                reportsZone.dataset.managerId = user.id;
                
                // Find everyone reporting to this user
                const subordinates = currentRelations
                    .filter(r => String(r.manager_id) === String(user.id))
                    .map(r => allUsers.find(u => String(u.id) === String(r.subordinate_id)))
                    .filter(u => u != null);
                
                subordinates.forEach(sub => {
                    // Create a "Leaf" node or a nested branch
                    // Matrix check: prevent deep recursion loops by limited depth or checking parents
                    renderBranch(sub, reportsZone);
                });
                
                // Special Remove Logic: If this branch is inside a drop-zone, it means it's a reporting line
                if (container.classList.contains('drop-zone')) {
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'btn-remove-relation';
                    removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                    removeBtn.onclick = (e) => {
                        e.stopPropagation();
                        removeRelation(user.id, container.dataset.managerId);
                    };
                    node.appendChild(removeBtn);
                }

                wrapper.appendChild(reportsZone);
                container.appendChild(wrapper);
            }

            // --- INTERACTION ---
            function initDragDrop() {
                // Roster: Cloneable
                new Sortable(rosterList, {
                    group: { name: 'team', pull: 'clone', put: false },
                    sort: false,
                    animation: 150
                });

                // Drop Zones: Accept items from Roster OR other zones
                document.querySelectorAll('.drop-zone').forEach(zone => {
                    new Sortable(zone, {
                        group: 'team',
                        animation: 250,
                        handle: '.user-canvas-node',
                        onAdd: (evt) => {
                            const userId = evt.item.dataset.userId;
                            const managerId = zone.dataset.managerId;
                            
                            // Matrix Rule: Simply add this relation if not exists
                            addRelation(userId, managerId);
                            
                            // Re-render UI to cleanup the cloned roster item and show proper nested node
                            renderUI();
                        },
                        onEnd: (evt) => {
                            // If moved from one zone to another
                            if (evt.from !== evt.to) {
                                const userId = evt.item.dataset.userId;
                                const oldManagerId = evt.from.dataset.managerId;
                                const newManagerId = evt.to.dataset.managerId;
                                
                                if (oldManagerId) removeRelation(userId, oldManagerId, false);
                                addRelation(userId, newManagerId);
                                renderUI();
                            }
                        }
                    });
                });
            }

            async function addRelation(subId, mgrId) {
                // Prevent duplicate relations
                if (currentRelations.find(r => String(r.subordinate_id) === String(subId) && String(r.manager_id) === String(mgrId))) return;
                
                currentRelations.push({ subordinate_id: subId, manager_id: mgrId });
                trackUpdate(subId, mgrId, 'add');
            }

            async function removeRelation(subId, mgrId, autoRender = true) {
                currentRelations = currentRelations.filter(r => !(String(r.subordinate_id) === String(subId) && String(r.manager_id) === String(mgrId)));
                trackUpdate(subId, mgrId, 'remove');
                if (autoRender) renderUI();
            }

            function trackUpdate(uid, mid, type) {
                pendingUpdates.push({ user_id: uid, manager_id: mid, action: type });
                updateCounter();
            }

            function updateCounter() {
                const stats = document.getElementById('changeStats');
                const count = pendingUpdates.length;
                if (count > 0) {
                    stats.innerHTML = `<span style="color: #6366f1; font-weight: 700;">${count} matrix changes pending</span>`;
                    saveBtn.style.transform = 'scale(1.05)';
                } else {
                    stats.textContent = 'Drag users from the roster to any manager';
                    saveBtn.style.transform = '';
                }
            }

            saveBtn.addEventListener('click', async () => {
                if (pendingUpdates.length === 0) return;
                
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Syncing Matrix...';
                
                try {
                    const promises = pendingUpdates.map(upd => 
                        fetch('api/update_hierarchy.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(upd)
                        }).then(r => r.json())
                    );
                    
                    await Promise.all(promises);
                    showToast("Matrix successfully updated!");
                    pendingUpdates = [];
                    updateCounter();
                    loadMatrix(); // Refresh final state
                } catch(err) {
                    console.error(err);
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Sync Matrix';
                }
            });

            // --- UI UTILS ---
            function getAvatarColor(str) {
                let hash = 0;
                for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
                return `hsl(${Math.abs(hash % 360)}, 65%, 45%)`;
            }
            function getInitials(name) {
                return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
            }
            function showToast(msg) {
                const toast = document.getElementById('hierarchyToast');
                document.getElementById('toastMsg').textContent = msg;
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 3000);
            }

            // --- ZOOM LOGIC ---
            let currentZoom = 1;
            const zoomInBtn = document.getElementById('zoomIn');
            const zoomOutBtn = document.getElementById('zoomOut');
            const zoomResetBtn = document.getElementById('zoomReset');
            const zoomLevelText = document.getElementById('zoomLevel');
            const canvasRootEl = document.getElementById('canvasRoot');

            function updateZoom() {
                canvasRootEl.style.transform = `scale(${currentZoom})`;
                zoomLevelText.textContent = `${Math.round(currentZoom * 100)}%`;
            }

            zoomInBtn.addEventListener('click', () => {
                if (currentZoom < 1.5) {
                    currentZoom += 0.1;
                    updateZoom();
                }
            });

            zoomOutBtn.addEventListener('click', () => {
                if (currentZoom > 0.5) {
                    currentZoom -= 0.1;
                    updateZoom();
                }
            });

            zoomResetBtn.addEventListener('click', () => {
                currentZoom = 1;
                updateZoom();
                // Re-center
                setTimeout(() => {
                    const canvas = document.querySelector('.canvas-area');
                    if (canvas && canvasRootEl) {
                        const centerX = (canvasRootEl.offsetWidth - canvas.offsetWidth) / 2;
                        canvas.scrollLeft = Math.max(0, centerX);
                    }
                }, 350);
            });

            staffFilter.addEventListener('input', renderRoster);

            loadMatrix();
        });
    </script>
</body>
</html>
