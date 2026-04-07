<?php
session_start();
// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/db_connect.php';

// Fetch users for the dropdown filter
$stmtUsers = $pdo->prepare("SELECT id, username FROM users WHERE status = 'active' ORDER BY username ASC");
$stmtUsers->execute();
$filterUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Current date for UI display
$currentDate = date('l, F j, Y');
$htmlDateValue = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees Attendance | Connect</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    
    <!-- Common CSS -->
    <link rel="stylesheet" href="../../studio_users/header.css">
    <link rel="stylesheet" href="../../studio_users/components/sidebar.css">
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        body { 
            font-family: 'Outfit', sans-serif; 
            background: #f8fafc; 
            margin: 0; 
            color: #1e293b;
        }
        .dashboard-container { display: flex; height: 100vh; overflow: hidden; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .main-content-scroll { overflow-y: auto; flex: 1; padding: 1.5rem 2rem; }
        
        /* Minimalistic Colorful Header */
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
            padding: 1.25rem 1.75rem;
            border-radius: 12px;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -30px; right: 40px;
            width: 100px; height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .page-title {
            font-size: 1.35rem;
            font-weight: 600;
            margin: 0 0 0.15rem 0;
            position: relative;
            z-index: 2;
        }
        .page-subtitle {
            font-size: 0.85rem;
            color: #e0e7ff;
            margin: 0;
            font-weight: 400;
            position: relative;
            z-index: 2;
        }
        
        .date-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(4px);
            padding: 0.4rem 0.85rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            z-index: 2;
        }
        .date-display i {
            color: #c7d2fe;
        }

        /* Compact Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.05);
        }
        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        
        /* Vibrant but minimalistic solid/light gradients */
        .icon-total { background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%); }
        .icon-present { background: linear-gradient(135deg, #34d399 0%, #10b981 100%); }
        .icon-late { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
        .icon-absent { background: linear-gradient(135deg, #f87171 0%, #ef4444 100%); }
        
        .stat-info { display: flex; flex-direction: column; }
        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
            margin: 0 0 0.1rem 0;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Compact Filters */
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            background: #fff;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .filter-label {
            font-size: 0.7rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
        }
        .filter-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.85rem;
            color: #1e293b;
            background: #f8fafc;
            outline: none;
            transition: all 0.2s;
            min-width: 140px;
        }
        .filter-input:focus {
            border-color: #818cf8;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.15);
        }
        
        .search-input-wrapper {
            position: relative;
            min-width: 240px;
        }
        .search-input-wrapper svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            width: 14px;
            height: 14px;
        }
        .search-input-wrapper .filter-input {
            width: 100%;
            padding-left: 2rem;
            box-sizing: border-box;
        }

        /* Compact Table */
        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 0px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        th, td { 
            padding: 0.85rem 1.25rem; 
            text-align: left; 
        }
        th { 
            font-size: 0.75rem;
            font-weight: 600; 
            color: #475569; 
            text-transform: uppercase;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            font-size: 0.85rem;
            color: #334155;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background-color: #f8fafc;
        }

        /* Avatar Info Cell */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .user-avatar {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: #e0e7ff; /* Very light purple/indigo */
            color: #4f46e5;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid #c7d2fe;
        }
        .user-name {
            font-weight: 500;
            color: #0f172a;
            font-size: 0.9rem;
        }
        .user-email {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 1px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid #e2e8f0;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-present {
            background: #d1fae5;
            color: #065f46;
        }
        .status-absent {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-late {
            background: #fef3c7;
            color: #92400e;
        }
        .status-leave {
            background: #dbeafe; /* Pale blue/indigo */
            color: #1e40af;
        }
        
        .time-box {
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.25rem 0.45rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            color: #475569;
            display: inline-flex;
        }

        /* Header reset to neutral */
        .dh-nav-header {
            background: #fff;
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .emp-code {
            font-family: inherit; 
            font-weight: 500;
            font-size: 0.8rem; 
            color: #64748b; 
        }

        /* Map Modal Styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px);
            z-index: 9999; display: none; align-items: center; justify-content: center;
        }
        .modal-content {
            background: #fff; border-radius: 12px; width: 90%; max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden;
            animation: modalPop 0.2s ease-out;
            display: flex; flex-direction: column;
        }
        @keyframes modalPop {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .modal-header {
            padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
            background: #f8fafc;
        }
        .modal-title { margin: 0; font-size: 1.05rem; font-weight: 600; color: #0f172a; }
        .modal-close {
            background: none; border: none; color: #64748b; cursor: pointer; padding: 0.25rem;
            display: flex; align-items: center; border-radius: 6px; transition: 0.2s;
        }
        .modal-close:hover { background: #f1f5f9; color: #ef4444; }
        .modal-body { padding: 0; height: 350px; background: #e2e8f0; position: relative; }
        .modal-body iframe { width: 100%; height: 100%; border: none; position: absolute; top:0; left:0; }
        .modal-footer {
            padding: 0.85rem; background: #fff; border-top: 1px solid #e2e8f0;
            font-size: 0.85rem; color: #475569; text-align: center;
            line-height: 1.4;
        }

        /* Responsive UI Optimization */
        @media (max-width: 1024px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.25rem;
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-input-wrapper, .filter-input {
                width: 100%;
                min-width: 100%;
                box-sizing: border-box;
            }
        }
        @media (max-width: 768px) {
            .main-content-scroll {
                padding: 4.5rem 1rem 1.5rem 1rem; /* Provide space for the mobile hamburger menu */
            }
            #mobileMenuBtn {
                display: flex !important;
                background: #fff !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                padding: 0.5rem !important;
                box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
                position: fixed !important;
                top: 0.85rem !important;
                left: 1rem !important;
                z-index: 999 !important;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            /* Make table smoothly horizontally scrollable */
            table { 
                min-width: 1000px;
            }
            th, td { 
                white-space: nowrap; 
            }
        }
        @media (max-width: 414px) { /* Tailored for iPhone XR and SE */
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                padding: 1.25rem;
            }
            .date-display {
                width: 100%;
                justify-content: center;
                box-sizing: border-box;
            }
            .user-avatar {
                display: none; /* Strip out visual fluff to preserve critical data rendering space */
            }
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
            <button class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open sidebar" style="display:none;position:absolute;top:1.5rem;left:1.5rem;z-index:900;background:none;border:none;cursor:pointer;">
                <i data-lucide="menu" style="width:16px;height:16px;color:#18181b;"></i>
            </button>

            <div class="main-content-scroll">
                
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Employees Attendance</h1>
                        <p class="page-subtitle">Real-time overview of workforce presence and daily logs.</p>
                    </div>
                    <div class="date-display">
                        <i data-lucide="calendar-clock" style="width: 14px; height: 14px;"></i>
                        <?php echo $currentDate; ?>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon icon-total">
                            <i data-lucide="users" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value" id="statTotal">-</h3>
                            <span class="stat-label">Total Staff</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-present">
                            <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value" id="statOnTime">-</h3>
                            <span class="stat-label">On Time</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-late">
                            <i data-lucide="clock-alert" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value" id="statLate">-</h3>
                            <span class="stat-label">Late</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-absent">
                            <i data-lucide="user-x" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 class="stat-value" id="statAbsent">-</h3>
                            <span class="stat-label">Absent</span>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-bar" style="align-items: flex-end; gap: 0.75rem;">
                    
                    <div class="filter-group">
                        <span class="filter-label">Select Employee</span>
                        <select id="userFilter" class="filter-input" style="cursor: pointer;">
                            <option value="">All Employees</option>
                            <?php foreach($filterUsers as $fuser): ?>
                                <option value="<?php echo $fuser['id']; ?>"><?php echo htmlspecialchars($fuser['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <span class="filter-label">Filter by Status</span>
                        <select id="statusFilter" class="filter-input" style="cursor: pointer;">
                            <option value="">All Statuses</option>
                            <option value="on time">On Time</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                            <option value="leave">On Leave</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <span class="filter-label">From Date</span>
                        <input type="date" id="fromDate" class="filter-input" value="<?php echo $htmlDateValue; ?>">
                    </div>

                    <div class="filter-group">
                        <span class="filter-label">To Date</span>
                        <input type="date" id="toDate" class="filter-input" value="<?php echo $htmlDateValue; ?>">
                    </div>

                    <div class="filter-group" style="flex: 1; min-width: 150px;">
                        <span class="filter-label">Quick Search</span>
                        <div class="search-input-wrapper">
                            <i data-lucide="search"></i>
                            <input type="text" id="searchInput" class="filter-input" placeholder="Search name or ID...">
                        </div>
                    </div>
                </div>
                
                <!-- Data Table -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center; padding-right: 0.5rem;">S.No</th>
                                <th style="width: 80px; padding-left: 0.5rem;">Date</th>
                                <th>Employee Name</th>
                                <th style="text-align: center;">Punch In Time</th>
                                <th>Punch In Location</th>
                                <th style="text-align: center;">In Geofence</th>
                                <th style="text-align: center;">Punch In Photo</th>
                                <th style="text-align: center;">Punch Out Time</th>
                                <th>Punch Out Location</th>
                                <th style="text-align: center;">Out Geofence</th>
                                <th style="text-align: center;">Punch Out Photo</th>
                                <th style="text-align: center;">Work Report</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <!-- Populated dynamically via JS API call -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Dynamic Map location Modal Overlay -->
            <div id="locationModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Geographic Location</h3>
                        <button class="modal-close" onclick="closeLocationModal()">
                            <i data-lucide="x" style="width:20px;height:20px;"></i>
                        </button>
                    </div>
                    <div class="modal-body" id="modalMapContainer">
                        <!-- iframe embedded via script -->
                    </div>
                    <div class="modal-footer" id="modalAddressText">
                        <!-- text embedded via script -->
                    </div>
                </div>
            </div>

            <!-- Dynamic Work Report Modal Overlay -->
            <div id="reportModal" class="modal-overlay">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Work Report</h3>
                        <button class="modal-close" onclick="closeReportModal()">
                            <i data-lucide="x" style="width:20px;height:20px;"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="padding: 1.75rem; background: #fff; height: auto; min-height: 150px; max-height: 400px; overflow-y: auto;">
                        <p id="modalReportText" style="margin:0; font-size: 0.95rem; color: #334155; line-height: 1.6; white-space: pre-wrap; font-family: inherit; font-weight: 500;"></p>
                    </div>
                </div>
            </div>

            <!-- Geofence Approve/Reject Modal Overlay -->
            <div id="geofenceDecisionModal" class="modal-overlay">
                <div class="modal-content" style="max-width: 560px;">
                    <div class="modal-header">
                        <h3 class="modal-title" id="geofenceDecisionTitle">Geofence Decision</h3>
                        <button class="modal-close" onclick="closeGeofenceDecisionModal()">
                            <i data-lucide="x" style="width:20px;height:20px;"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="padding: 1.25rem; background: #fff; height: auto; min-height: 180px; max-height: 70vh; overflow-y: auto;">
                        <input type="hidden" id="geofenceActionType" value="approve">
                        <input type="hidden" id="geofenceAttendanceId" value="">

                        <div style="display:grid; gap: 0.85rem;">
                            <div id="geofencePunchInCard" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.9rem;">
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem; margin-bottom:0.35rem;">
                                    <i data-lucide="log-in" style="width:14px;height:14px;color:#3b82f6;"></i>
                                    <strong style="font-size:0.82rem; color:#0f172a; flex:1;">Punch In (Outside Geofence)</strong>
                                    <label style="display:flex; align-items:center; gap:0.3rem; font-size:0.76rem; color:#334155; cursor:pointer;">
                                        <input type="checkbox" id="geofenceSelectPunchIn" checked style="cursor:pointer;"> Select
                                    </label>
                                </div>
                                <div id="geofencePunchInReason" style="font-size:0.82rem; color:#334155; white-space:pre-wrap; line-height:1.5;">-</div>
                            </div>

                            <div id="geofencePunchOutCard" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.9rem;">
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem; margin-bottom:0.35rem;">
                                    <i data-lucide="log-out" style="width:14px;height:14px;color:#8b5cf6;"></i>
                                    <strong style="font-size:0.82rem; color:#0f172a; flex:1;">Punch Out (Outside Geofence)</strong>
                                    <label style="display:flex; align-items:center; gap:0.3rem; font-size:0.76rem; color:#334155; cursor:pointer;">
                                        <input type="checkbox" id="geofenceSelectPunchOut" checked style="cursor:pointer;"> Select
                                    </label>
                                </div>
                                <div id="geofencePunchOutReason" style="font-size:0.82rem; color:#334155; white-space:pre-wrap; line-height:1.5;">-</div>
                            </div>
                        </div>

                        <div style="margin-top: 0.95rem;">
                            <label for="geofenceDecisionComment" style="display:block; margin-bottom:0.4rem; font-size:0.76rem; font-weight:600; color:#475569;">Comment (required for reject, minimum 10 words)</label>
                            <textarea id="geofenceDecisionComment" rows="3" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:0.65rem; box-sizing:border-box; font-family:inherit; font-size:0.84rem; resize:vertical;" placeholder="Add decision comment..."></textarea>
                            <div id="geofenceDecisionMessage" style="display:none; margin-top:0.45rem; font-size:0.78rem; font-weight:500;"></div>
                        </div>
                    </div>
                    <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:0.6rem; padding: 0.8rem 1rem;">
                        <button type="button" onclick="closeGeofenceDecisionModal()" style="border:1px solid #cbd5e1; background:#fff; color:#334155; border-radius:8px; padding:0.45rem 0.8rem; font-size:0.82rem; cursor:pointer;">Cancel</button>
                        <button type="button" id="geofenceDecisionRejectBtn" onclick="submitGeofenceDecision('reject')" style="border:none; background:#ef4444; color:#fff; border-radius:8px; padding:0.45rem 0.85rem; font-size:0.82rem; font-weight:600; cursor:pointer;">Reject</button>
                        <button type="button" id="geofenceDecisionApproveBtn" onclick="submitGeofenceDecision('approve')" style="border:none; background:#10b981; color:#fff; border-radius:8px; padding:0.45rem 0.85rem; font-size:0.82rem; font-weight:600; cursor:pointer;">Approve</button>
                    </div>
                </div>
            </div>

            <!-- Include Action Modals architecture -->
            <?php include 'modals/view_details_modal.php'; ?>
            <?php include 'modals/edit_attendance_modal.php'; ?>
            
        </main>
    </div>

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
