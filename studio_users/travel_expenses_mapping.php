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
    <title>Travel Expenses Mapping | Connect</title>
    
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    
    <style>
        .mapping-container {
            padding: 2.5rem 3rem;
            max-width: none;
            width: 100%;
            margin: 0;
            animation: fadeIn 0.5s ease-out;
            box-sizing: border-box;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .page-header { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem; 
        }
        .page-header h1 { font-size: 1.8rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; }
        .page-header p { color: #64748b; font-size: 0.95rem; }

        .card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 25px -5px rgba(0,0,0,0.04);
            overflow: hidden;
        }

        .filter-bar {
            padding: 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            width: 16px;
            height: 16px;
        }
        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 42px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            background: white;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s;
        }
        .search-box input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 1.25rem 1.5rem;
            background: #f8fafc;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .employee-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #eef2ff;
            color: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .role-select {
            width: 100%;
            min-width: 180px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 0.85rem;
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .role-select:focus {
            border-color: #6366f1;
            background: white;
        }

        .step-arrow {
            color: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .save-btn {
            background: #6366f1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .save-btn:hover {
            background: #4f46e5;
            transform: translateY(-1px);
        }

        .save-all-btn {
            background: #0f172a;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .save-all-btn:hover {
            background: #1e293b;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .badge-step {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            background: #f1f5f9;
            color: #64748b;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container" style="display: flex;">
        <div id="sidebar-mount" style="position: sticky; top: 0; height: 100vh;"></div>
        
        <main class="main-content" style="flex: 1; background: #f8fafc; min-height: 100vh;">
            <div class="mapping-container">
                <div class="page-header">
                    <div>
                        <h1>Travel Expenses Workflow Mapping</h1>
                        <p>Route reimbursement claims through the correct approval hierarchy.</p>
                    </div>
                    <button class="save-all-btn">
                        <i data-lucide="shield-check"></i>
                        Save Master Mapping
                    </button>
                </div>

                <div class="card">
                    <div class="filter-bar">
                        <div class="search-box">
                            <i data-lucide="search"></i>
                            <input type="text" id="employeeSearch" placeholder="Filter by employee name or ID...">
                        </div>
                        <div style="flex: 1;"></div>
                        <p style="font-size: 0.85rem; color: #64748b;">
                            <i data-lucide="info" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i>
                            Changes are tracked per row. Click save to commit.
                        </p>
                    </div>

                    <div class="table-wrapper">
                        <table id="mappingTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Level 1: Manager</th>
                                    <th>Level 2: HR</th>
                                    <th>Level 3: Sr. Manager</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="mappingTableBody">
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 3rem; color: #64748b;">
                                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                        <p>Synchronizing organizational data...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="components/sidebar-loader.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('mappingTableBody');
            const searchInput = document.getElementById('employeeSearch');
            let allUsers = [];
            let approvers = [];
            let currentMappings = {};

            // Fetch Data
            async function loadData() {
                try {
                    const response = await fetch('api/get_travel_mapping_data.php');
                    const data = await response.json();
                    
                    if (data.success) {
                        allUsers = data.users;
                        approvers = data.approvers;
                        currentMappings = data.mappings;
                        renderTable();
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#ef4444;padding:2rem;">${data.error}</td></tr>`;
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#ef4444;padding:2rem;">Network error fetching data.</td></tr>`;
                }
            }

            function renderTable(filterTerm = '') {
                const term = filterTerm.toLowerCase();
                const filtered = allUsers.filter(u => 
                    u.name.toLowerCase().includes(term) || 
                    (u.email || '').toLowerCase().includes(term) || 
                    (u.position || '').toLowerCase().includes(term)
                );

                if (filtered.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:#94a3b8;">No matching employees found.</td></tr>`;
                    return;
                }

                const defaultPurchaseManager = approvers.find(a => 
                    (a.role || '').toLowerCase() === 'purchase manager' || 
                    (a.position || '').toLowerCase().includes('purchase manager')
                );

                const defaultHR = approvers.find(a => 
                    (a.role || '').toLowerCase() === 'hr' || 
                    (a.position || '').toLowerCase().includes('hr')
                );

                tableBody.innerHTML = filtered.map(user => {
                    const mapping = currentMappings[user.id] || {};
                    // Defaults logic: If no mapping, use specific defaults if found
                    const level1Id = mapping.manager_id || (defaultPurchaseManager ? defaultPurchaseManager.id : '');
                    const level2Id = mapping.hr_id || (defaultHR ? defaultHR.id : '');

                    return `
                        <tr data-employee-id="${user.id}">
                            <td>
                                <div class="employee-cell">
                                    <div class="avatar" style="background: ${getHSL(user.name)}; color: #fff;">${getInitials(user.name)}</div>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b;">${user.name}</div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;">${user.position || user.role}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="badge-step">Direct Manager</div>
                                <select class="role-select" data-level="manager">
                                    <option value="">Select Level 1...</option>
                                    ${renderApproverOptions(level1Id)}
                                </select>
                            </td>
                            <td>
                                <div class="badge-step">HR Approval</div>
                                <select class="role-select" data-level="hr">
                                    <option value="">Select Level 2...</option>
                                    ${renderApproverOptions(level2Id)}
                                </select>
                            </td>
                            <td>
                                <div class="badge-step">Sr. Manager</div>
                                <select class="role-select" data-level="senior">
                                    <option value="">Select Level 3...</option>
                                    ${renderApproverOptions(mapping.senior_manager_id)}
                                </select>
                            </td>
                            <td>
                                <button class="save-btn" onclick="saveIndividualMapping(${user.id}, this)">
                                    <i data-lucide="save" style="width: 16px; height:16px;"></i> Save
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
                
                if (window.lucide) lucide.createIcons();
            }

            function renderApproverOptions(selectedId) {
                return approvers.map(a => `
                    <option value="${a.id}" ${parseInt(selectedId) === parseInt(a.id) ? 'selected' : ''}>
                        ${a.name} (${a.position || a.role})
                    </option>
                `).join('');
            }

            window.saveIndividualMapping = async (employeeId, btn) => {
                const row = btn.closest('tr');
                const managerId = row.querySelector('[data-level="manager"]').value;
                const hrId = row.querySelector('[data-level="hr"]').value;
                const seniorId = row.querySelector('[data-level="senior"]').value;

                if (!managerId || !hrId || !seniorId) {
                    alert('Please select all three approval levels.');
                    return;
                }

                btn.disabled = true;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                try {
                    const response = await fetch('api/update_travel_mapping.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            employee_id: employeeId,
                            manager_id: managerId,
                            hr_id: hrId,
                            senior_manager_id: seniorId
                        })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        btn.style.background = '#10b981';
                        btn.innerHTML = '<i data-lucide="check" style="width:16px;"></i> OK';
                        if (window.lucide) lucide.createIcons();
                        setTimeout(() => {
                            btn.style.background = '';
                            btn.innerHTML = originalHtml;
                            btn.disabled = false;
                            if (window.lucide) lucide.createIcons();
                        }, 2000);
                    } else {
                        alert('Error: ' + data.error);
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                } catch (err) {
                    console.error(err);
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            };

            searchInput.addEventListener('input', (e) => renderTable(e.target.value));

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
