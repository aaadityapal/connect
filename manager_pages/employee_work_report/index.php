<?php
session_start();
// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/db_connect.php';

// Fetch active users only
$stmt = $pdo->prepare("SELECT id, unique_id, username, email, role FROM users WHERE status = 'active' ORDER BY username ASC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Common variables for filters
$currentYear = (int)date('Y');
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Work Report | Connect</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    
    <!-- Common CSS -->
    <link rel="stylesheet" href="../../studio_users/header.css">
    <link rel="stylesheet" href="../../studio_users/components/sidebar.css">
    
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #fafafa; 
            margin: 0; 
            color: #171717;
        }
        .dashboard-container { display: flex; height: 100vh; overflow: hidden; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .main-content-scroll { overflow-y: auto; flex: 1; padding: 2.5rem 3rem; }
        
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 1.5rem;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: #111;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.01em;
        }
        .page-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin: 0;
        }

        /* Minimalist Filters */
        .filter-bar {
            display: flex;
            gap: 1.25rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .filter-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #737373;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .filter-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d4d4d8;
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.9rem;
            color: #262626;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
            min-width: 140px;
        }
        .filter-input:focus {
            border-color: #a3a3a3;
        }
        
        .search-input-wrapper {
            position: relative;
            min-width: 260px;
        }
        .search-input-wrapper svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #a3a3a3;
        }
        .search-input-wrapper .filter-input {
            width: 100%;
            padding-left: 2.2rem;
            box-sizing: border-box;
        }

        /* Minimalist Table */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 1rem;
        }
        th, td { 
            padding: 1.25rem 0; 
            text-align: left; 
            border-bottom: 1px solid #f0f0f0;
        }
        th { 
            font-size: 0.8rem;
            font-weight: 500; 
            color: #737373; 
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding-bottom: 0.75rem;
        }
        td {
            font-size: 0.9rem;
            color: #262626;
        }
        tr:hover td {
            background-color: transparent;
        }
        
        /* Action Buttons */
        .actions-cell {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: 1px solid transparent;
            background: transparent;
        }
        
        .btn-view {
            background: #f4f4f5;
            color: #18181b;
        }
        .btn-view:hover {
            background: #e4e4e7;
        }
        
        /* Outline buttons for Excel/PDF to keep it minimal */
        .btn-excel {
            color: #059669;
            border-color: #d1fae5;
        }
        .btn-excel:hover {
            background: #ecfdf5;
            border-color: #10b981;
        }
        
        .btn-pdf {
            color: #dc2626;
            border-color: #fee2e2;
        }
        .btn-pdf:hover {
            background: #fef2f2;
            border-color: #ef4444;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            background: #f4f4f5;
            color: #52525b;
            border-radius: 4px;
            font-size: 0.75rem;
            border: 1px solid #e4e4e7;
        }
        
        /* Header reset to neutral */
        .dh-nav-header {
            background: #fff;
            padding: 1rem 2rem;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(3px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .modal-overlay.active {
            opacity: 1;
        }
        .modal-container {
            background: #fff;
            width: 100%;
            max-width: 800px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(20px);
            transition: transform 0.2s ease;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }
        .modal-overlay.active .modal-container {
            transform: translateY(0);
        }
        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #111;
        }
        .modal-close-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #737373;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem;
        }
        .modal-close-btn:hover {
            color: #111;
        }
        .modal-body {
            padding: 1.5rem 2rem;
            overflow-y: auto;
        }
        .employee-info-bar {
            display: flex;
            gap: 2rem;
            background: #fafafa;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #f0f0f0;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .info-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #737373;
            text-transform: uppercase;
        }
        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #111;
        }
        .report-details-table th, .report-details-table td {
            border-bottom: 1px solid #f0f0f0;
        }
        .report-details-table td {
            padding: 1rem;
        }
        .report-details-table th {
            padding: 1rem;
        }
        .modal-footer {
            padding: 1.25rem 2rem;
            border-top: 1px solid #eaeaea;
            display: flex;
            justify-content: flex-end;
            background: #fafafa;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }
        .btn-cancel {
            padding: 0.5rem 1.25rem;
            background: #fff;
            border: 1px solid #d4d4d8;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 500;
            color: #262626;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-cancel:hover {
            background: #f4f4f5;
        }
    </style>
</head>
<body class="el-1">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div id="sidebar-mount"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Mobile Trigger Only -->
            <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar" style="display:none;position:absolute;top:2rem;left:2rem;z-index:900;background:none;border:none;cursor:pointer;">
                <i data-lucide="menu" style="width:18px;height:18px;color:#18181b;"></i>
            </button>

            <div class="main-content-scroll">
                
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Employee Work Report</h1>
                        <p class="page-subtitle">Analyze monthly workforce performance and generate specific reports.</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-bar">
                    <div class="filter-group" style="flex: 1; max-width: 320px;">
                        <span class="filter-label">Search</span>
                        <div class="search-input-wrapper">
                            <i data-lucide="search" style="width: 14px; height: 14px;"></i>
                            <input type="text" id="searchInput" class="filter-input" placeholder="Search by name or email">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <span class="filter-label">Role</span>
                        <select id="roleFilter" class="filter-input" style="cursor: pointer;">
                            <option value="">All Roles</option>
                            <?php 
                                $roles = array_unique(array_filter(array_column($users, 'role')));
                                sort($roles);
                                foreach($roles as $role): 
                            ?>
                                <option value="<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars($role); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <span class="filter-label">Month</span>
                        <select id="monthFilter" class="filter-input" style="cursor: pointer;">
                            <?php 
                                $currentM = (int)date('n');
                                foreach($months as $num => $name): 
                                    $selected = ($num === $currentM) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $num; ?>" <?php echo $selected; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <span class="filter-label">Year</span>
                        <select id="yearFilter" class="filter-input" style="cursor: pointer;">
                            <?php 
                                for($y = $currentYear; $y >= $currentYear - 5; $y--): 
                            ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Data Table -->
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">S.No</th>
                            <th style="width: 120px;">Emp Code</th>
                            <th>Employee</th>
                            <th>Role</th>
                            <th style="width:280px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sno = 1;
                        foreach($users as $user): 
                        ?>
                        <tr>
                            <td style="color: #737373; font-weight: 500;"><?php echo $sno++; ?></td>
                            <td>
                                <span style="font-family: inherit; font-size: 0.85rem; color: #52525b; background: #f4f4f5; border: 1px solid #e4e4e7; padding: 0.2rem 0.4rem; border-radius: 4px;">
                                    <?php echo htmlspecialchars($user['unique_id'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 500; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #737373; margin-top: 3px;"><?php echo htmlspecialchars($user['email'] ?? 'No email'); ?></div>
                            </td>
                            <td>
                                <span class="role-badge"><?php echo htmlspecialchars($user['role'] ?? 'User'); ?></span>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action btn-view" title="View Full Report" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-role="<?php echo htmlspecialchars($user['role'] ?? 'User'); ?>">
                                        View
                                    </button>
                                    <button class="btn-action btn-excel" title="Download Excel">
                                        <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px;"></i>
                                        Excel
                                    </button>
                                    <button class="btn-action btn-pdf" title="Download PDF">
                                        <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                                        PDF
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <?php include 'modals/view_report_modal.php'; ?>

    <!-- Scripts -->
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js"></script>
    <script src="js/script.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
