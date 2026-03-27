<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Settings - Connect</title>
    <!-- Sidebar Requirements -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>window.SIDEBAR_BASE_PATH = '../';</script>
    <script src="../components/sidebar-loader.js" defer></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Modular CSS -->
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/table.css">

    <style>
        .settings-card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        /* Tabs Styling */
        .tabs-header {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 25px;
        }

        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: none;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .tab-btn.active {
            color: var(--clr-blue);
            border-bottom-color: var(--clr-blue);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .role-list,
        .rate-list {
            display: grid;
            gap: 12px;
            margin-top: 15px;
        }

        .role-item,
        .rate-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .role-info,
        .rate-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .role-icon,
        .rate-icon {
            width: 36px;
            height: 36px;
            background: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 14px;
            border: 1px solid #e2e8f0;
        }

        .role-name,
        .rate-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }

        /* Inputs */
        .rate-input-wrap {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 2px 10px;
            width: 140px;
            transition: border 0.2s;
        }

        .rate-input-wrap:focus-within {
            border-color: var(--clr-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .rate-currency {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            margin-right: 5px;
        }

        .rate-input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 14px;
            font-weight: 600;
            text-align: right;
            color: #1e293b;
        }

        .rate-unit {
            color: #94a3b8;
            font-size: 11px;
            margin-left: 5px;
        }

        /* Switch styling */
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

        input:checked+.slider {
            background-color: #16a34a;
        }

        input:checked+.slider:before {
            transform: translateX(20px);
        }

        .role-badge {
            font-size: 11px;
            padding: 2px 8px;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 2px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div id="sidebar-mount"></div>
        <button class="mobile-hamburger-btn" id="mobileMenuBtn"><i data-lucide="menu"></i></button>
        <main class="main-content">
            <header class="header-banner"
                style="background:#1e293b; padding: 30px; border-radius: 16px; margin-bottom: 24px; color: #fff;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h1 style="font-size: 24px; margin-bottom: 5px;">Travel Expense Settings</h1>
                        <p style="color: #94a3b8; font-size: 14px;">Define permissions and mileage rates</p>
                    </div>
                </div>
            </header>

            <div class="settings-card">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="roles">
                        <i class="fa-solid fa-users-gear" style="margin-right:8px;"></i>Role Requirements
                    </button>
                    <button class="tab-btn" data-tab="rates">
                        <i class="fa-solid fa-gas-pump" style="margin-right:8px;"></i>Per KM Rates
                    </button>
                </div>

                <!-- Tab 1: Roles -->
                <div id="roles" class="tab-content active">
                    <div
                        style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:15px;">
                        <h3 style="font-size: 14px; color: #475569;">Enable roles that MUST upload Meter Start/End
                            photos</h3>
                    </div>
                    <div class="role-list" id="role-list-container">
                        <p style="text-align:center; color:#94a3b8; padding: 20px;">Loading roles...</p>
                    </div>
                </div>

                <!-- Tab 2: Rates -->
                <div id="rates" class="tab-content">
                    <div
                        style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:15px;">
                        <h3 style="font-size: 14px; color: #475569;">Set per-kilometer reimbursement rates for each mode
                        </h3>
                    </div>
                    <div class="rate-list" id="rate-list-container">
                        <p style="text-align:center; color:#94a3b8; padding: 20px;">Loading rates...</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetchRoles();
            fetchRates();
            initTabs();
        });

        function initTabs() {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                    btn.classList.add('active');
                    document.getElementById(btn.dataset.tab).classList.add('active');
                });
            });
        }

        async function fetchRoles() {
            try {
                const response = await fetch('../api/fetch_travel_role_config.php');
                const data = await response.json();
                if (data.success) renderRoles(data.configs);
            } catch (e) { console.error(e); }
        }

        function renderRoles(configs) {
            const container = document.getElementById('role-list-container');
            container.innerHTML = configs.map(role => `
                <div class="role-item">
                    <div class="role-info">
                        <div class="role-icon"><i class="fa-solid fa-user-tag"></i></div>
                        <div>
                            <div class="role-name">${role.role_name}</div>
                            <div class="role-badge">Requires Photos: ${role.require_meters == 1 ? 'YES' : 'NO'}</div>
                        </div>
                    </div>
                    <div>
                        <label class="switch">
                            <input type="checkbox" ${role.require_meters == 1 ? 'checked' : ''} onchange="updateRole('${role.role_name}', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            `).join('');
        }

        async function updateRole(roleName, checked) {
            try {
                await fetch('../api/update_travel_role_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role_name: roleName, require_meters: checked ? 1 : 0 })
                });
                fetchRoles();
            } catch (e) { console.error(e); }
        }

        // --- Per KM Rates Logic ---
        async function fetchRates() {
            try {
                const response = await fetch('../api/fetch_travel_transport_rates.php');
                const data = await response.json();
                if (data.success) renderRates(data.rates);
            } catch (e) { console.error(e); }
        }

        function renderRates(rates) {
            const container = document.getElementById('rate-list-container');
            container.innerHTML = rates.map(rate => `
                <div class="rate-item">
                    <div class="rate-info">
                        <div class="rate-icon"><i class="fa-solid fa-car-side"></i></div>
                        <div>
                            <div class="rate-name">${rate.transport_mode}</div>
                        </div>
                    </div>
                    <div class="rate-input-wrap">
                        <span class="rate-currency">₹</span>
                        <input type="number" step="0.01" class="rate-input" value="${rate.rate_per_km}" 
                               onblur="updateRate('${rate.transport_mode}', this.value)">
                        <span class="rate-unit">/ km</span>
                    </div>
                </div>
            `).join('');
        }

        async function updateRate(mode, rate) {
            try {
                const response = await fetch('../api/update_travel_transport_rate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ transport_mode: mode, rate_per_km: rate })
                });
                const data = await response.json();
                if (data.success) fetchRates();
            } catch (e) { console.error(e); }
        }
    </script>
</body>

</html>
</body>

</html>