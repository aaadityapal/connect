<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager-Employee Mapping | Connect</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        .mapping-container {
            padding: 2.5rem 3rem;
            max-width: none;
            width: 100%;
            animation: fadeIn 0.5s ease-out;
            box-sizing: border-box;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { font-size: 1.8rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; }
        .page-header p { color: #64748b; font-size: 1rem; }
        
        .manager-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 2rem; 
        }
        @media (max-width: 992px) {
            .manager-grid { grid-template-columns: 1fr; }
        }

        .manager-card { 
            background: #ffffff; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            padding: 1.5rem; 
            box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05);
            display: flex; 
            flex-direction: column; 
            height: 520px; 
            transition: all 0.3s ease;
        }
        .manager-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }

        .manager-info { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            margin-bottom: 1.25rem; 
            padding-bottom: 1rem; 
            border-bottom: 1px solid #f1f5f9; 
        }
        .manager-avatar { 
            width: 48px; 
            height: 48px; 
            border-radius: 12px; 
            background: #6366f1; 
            color: #ffffff; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 700; 
            font-size: 1.1rem;
        }
        .manager-meta h3 { font-size: 1.1rem; color: #1e293b; margin: 0; }
        .manager-meta span { font-size: 0.8rem; color: #64748b; font-weight: 500; }
        
        .search-box { margin-bottom: 1rem; position: relative; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 14px; height: 14px; }
        .search-box input { 
            width: 100%; 
            padding: 0.65rem 1rem 0.65rem 2.5rem; 
            border: 1px solid #e2e8f0; 
            border-radius: 10px; 
            font-size: 0.85rem; 
            background: #f8fafc;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-box input:focus { border-color: #6366f1; background: #ffffff; }

        .user-list-wrapper { 
            flex: 1; 
            overflow-y: auto; 
            padding-right: 6px; 
            margin-bottom: 1.25rem; 
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.25rem 0.75rem;
            align-content: start;
        }
        .user-item { 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
            padding: 0.5rem 0.75rem; 
            border-radius: 10px;
            cursor: pointer; 
            transition: background 0.15s;
            border: 1px solid transparent;
        }
        .user-item:hover { background: #f8fafc; border-color: #f1f5f9; }
        .user-item input[type="checkbox"] { 
            width: 18px; 
            height: 18px; 
            cursor: pointer; 
            border-radius: 4px;
            accent-color: #6366f1;
        }
        .user-item label { 
            flex: 1; 
            font-size: 0.9rem; 
            color: #334151; 
            cursor: pointer; 
            font-weight: 500;
        }
        
        .card-footer { display: flex; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid #f1f5f9; }
        .save-btn { 
            background: #0f172a; 
            color: #ffffff; 
            border: none; 
            padding: 0.6rem 1.25rem; 
            border-radius: 10px; 
            font-weight: 600; 
            font-size: 0.85rem; 
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .save-btn:hover { background: #334155; transform: scale(1.02); }
        .save-btn:active { transform: scale(0.98); }
        .save-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        
        /* Custom scrollbar */
        .user-list-wrapper::-webkit-scrollbar { width: 4px; }
        .user-list-wrapper::-webkit-scrollbar-track { background: #f8fafc; }
        .user-list-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .loading-container {
            grid-column: 1/-1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15vh 0;
            color: #64748b;
        }
        .spin { animation: rotate 2s linear infinite; }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="dashboard-container" style="display: flex;">
        <div id="sidebar-mount" style="position: sticky; top: 0; height: 100vh;"></div>
        
        <main class="main-content" style="flex: 1; background: #f8fafc; min-height: 100vh;">
            <div class="mapping-container">
                <div class="page-header">
                    <h1>Manager Assignment Matrix</h1>
                    <p>Assign employees to their respective team managers to automate leave approval notifications.</p>
                </div>
                
                <div id="managerGrid" class="manager-grid">
                    <!-- Loaded dynamically -->
                    <div class="loading-container">
                        <i data-lucide="loader-2" class="spin" style="width: 48px; height: 48px; margin-bottom: 1rem;"></i>
                        <p>Synchronizing organizational structure...</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="components/sidebar-loader.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let allUsers = [];
            let currentMappings = [];
            
            async function loadData() {
                try {
                    const res = await fetch('api/fetch_manager_mapping.php');
                    const data = await res.json();
                    if (data.success) {
                        allUsers = data.users;
                        currentMappings = data.mappings;
                        renderManagers();
                    } else {
                        console.error('API Error:', data.error);
                        document.getElementById('managerGrid').innerHTML = `<p style="grid-column:1/-1; text-align:center; color:red;">Failed to load data: ${data.error}</p>`;
                    }
                } catch (err) {
                    console.error('Network error:', err);
                }
            }

            function renderManagers() {
                const grid = document.getElementById('managerGrid');
                grid.innerHTML = '';
                
                // Identify managers: Designation contains "Manager" or Admin role
                const managers = allUsers.filter(u => 
                    (u.position && u.position.toLowerCase().includes('manager')) || 
                    (u.role && u.role.toLowerCase().includes('manager')) ||
                    (u.role && u.role.toLowerCase() === 'admin')
                );

                if (managers.length === 0) {
                    grid.innerHTML = '<div class="loading-container"><i data-lucide="info" style="margin-bottom:1rem;"></i><p>No users with Manager/Admin designations found.</p></div>';
                    lucide.createIcons();
                    return;
                }

                managers.forEach(mgr => {
                    const card = document.createElement('div');
                    card.className = 'manager-card';
                    card.dataset.managerId = mgr.id;

                    const subordinateIds = currentMappings.filter(m => String(m.manager_id) === String(mgr.id)).map(m => String(m.subordinate_id));

                    card.innerHTML = `
                        <div class="manager-info">
                            <div class="manager-avatar" style="background: ${getHSL(mgr.name)}">${getInitials(mgr.name)}</div>
                            <div class="manager-meta">
                                <h3>${mgr.name}</h3>
                                <span>${mgr.position || (mgr.role === 'admin' ? 'System Administrator' : 'Manager')}</span>
                            </div>
                        </div>
                        <div class="search-box">
                            <i data-lucide="search"></i>
                            <input type="text" placeholder="Search team members..." oninput="filterUserList(this)">
                        </div>
                        <div class="user-list-wrapper">
                            ${renderUserCheckboxes(mgr.id, subordinateIds)}
                        </div>
                        <div class="card-footer">
                            <button class="save-btn" onclick="saveMapping(${mgr.id}, this)">
                                <i data-lucide="save" style="width:16px; height:16px;"></i>
                                Update Roster
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
                lucide.createIcons();
            }

            function renderUserCheckboxes(managerId, alreadyMapped) {
                // Don't show the manager in their own list
                return allUsers
                    .filter(u => String(u.id) !== String(managerId))
                    .map(u => {
                        const isChecked = alreadyMapped.includes(String(u.id)) ? 'checked' : '';
                        return `
                            <div class="user-item">
                                <input type="checkbox" id="m${managerId}_u${u.id}" value="${u.id}" ${isChecked}>
                                <label for="m${managerId}_u${u.id}">${u.name}</label>
                            </div>
                        `;
                    }).join('');
            }

            window.filterUserList = (input) => {
                const term = input.value.toLowerCase();
                const card = input.closest('.manager-card');
                const items = card.querySelectorAll('.user-item');
                items.forEach(item => {
                    const name = item.querySelector('label').textContent.toLowerCase();
                    item.style.display = name.includes(term) ? 'flex' : 'none';
                });
            };

            window.saveMapping = async (managerId, btn) => {
                const card = btn.closest('.manager-card');
                const checked = card.querySelectorAll('input[type="checkbox"]:checked');
                const selectedIds = Array.from(checked).map(cb => cb.value);

                btn.disabled = true;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="loader-2" class="spin" style="width:16px; height:16px;"></i> Syncing...';
                lucide.createIcons();

                try {
                    const res = await fetch('api/update_manager_mapping.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ manager_id: managerId, subordinates: selectedIds })
                    });
                    const data = await res.json();
                    
                    if (data.success) {
                        btn.style.background = '#059669';
                        btn.innerHTML = '<i data-lucide="check-circle" style="width:16px; height:16px;"></i> Updated';
                        lucide.createIcons();
                        setTimeout(() => {
                            btn.style.background = '#0f172a';
                            btn.innerHTML = originalHtml;
                            btn.disabled = false;
                            lucide.createIcons();
                        }, 2500);
                    } else {
                        alert('Operation failed: ' + (data.error || 'Server error'));
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        lucide.createIcons();
                    }
                } catch (err) {
                    console.error(err);
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    lucide.createIcons();
                }
            };

            function getInitials(name) { return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2); }
            function getHSL(str) {
                let hash = 0;
                for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
                return `hsl(${Math.abs(hash % 360)}, 65%, 45%)`;
            }

            loadData();
        });
    </script>
</body>
</html>
