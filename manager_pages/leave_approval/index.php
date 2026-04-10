<?php
session_start();
// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$userId    = $_SESSION['user_id'];
$username  = $_SESSION['username'] ?? 'Manager';
$user_role = $_SESSION['role'] ?? 'user';

// Permission Check: Admin or specific Manual Leave permission
require_once '../../config/db_connect.php';
$isManagerAdmin = (strtolower($user_role) === 'admin');
$hasManualLeavePermission = $isManagerAdmin;

if (!$hasManualLeavePermission) {
    $pStmt = $pdo->prepare("SELECT can_add_manual_leave FROM manual_leave_permissions WHERE user_id = ?");
    $pStmt->execute([$userId]);
    $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
    $hasManualLeavePermission = ($pRow && (int)$pRow['can_add_manual_leave'] === 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approval Management | Connect</title>
    
    <!-- Modern Fonts (matching project style) -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Lucide Icons (Universal project icons) -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    
    <!-- Reusable Sidebar Loader -->
    <script>
        // Set the base path for sidebar assets relative to this page
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
</head>
<body>

    <div class="dashboard-container">
        <!-- Sidebar will be injected into this mount point by sidebar-loader.js -->
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <!-- ─── Page Header ─── -->
            <header class="page-header">
                <div class="header-title">
                    <h1>Leave Approvals</h1>
                    <p>Manage and review employee leave requests for your team.</p>
                    <input type="hidden" id="currentUserRole" value="<?php echo $user_role; ?>">
                </div>
                <div class="header-actions">
                    <?php if ($hasManualLeavePermission): ?>
                    <button class="btn-primary-gradient" id="addLeaveManualBtn">
                        <i data-lucide="plus-circle"></i>
                        <span>Add Users Leaves Manually</span>
                    </button>
                    <?php endif; ?>
                </div>
            </header>

            <!-- ─── Summary Cards ─── -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i data-lucide="clock-4"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pending</h3>
                        <div class="value" id="stat-pending">0</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon approved">
                        <i data-lucide="check-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Approved</h3>
                        <div class="value" id="stat-approved">0</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i data-lucide="x-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Rejected</h3>
                        <div class="value" id="stat-rejected">0</div>
                    </div>
                </div>
            </section>

            <!-- ─── Leave Request List Section ─── -->
            <div class="section-card">
                <div class="card-header">
                    <div class="card-title">Leave Request List</div>
                </div>
                
                <div class="filter-bar">
                    <div class="filter-group search-group">
                        <div class="search-box">
                            <i data-lucide="search"></i>
                            <input type="text" id="leaveSearchInput" placeholder="Search employee...">
                        </div>
                    </div>

                    <div class="filter-group date-group">
                        <div class="date-input-wrap">
                            <i data-lucide="calendar"></i>
                            <input type="date" id="fromDateFilter" class="filter-date">
                        </div>
                        <span class="date-sep">to</span>
                        <div class="date-input-wrap">
                            <i data-lucide="calendar"></i>
                            <input type="date" id="toDateFilter" class="filter-date">
                        </div>
                    </div>

                    <div class="filter-group select-group">
                        <select class="filter-select" id="employeeFilter">
                            <option value="All">All Employees</option>
                        </select>
                        <select class="filter-select" id="statusFilter">
                            <option value="All">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                        <select class="filter-select" id="typeFilter">
                            <option value="All">All Types</option>
                        </select>
                    </div>
                    
                    <button class="btn-refresh" id="refreshBtn" title="Refresh Data">
                        <i data-lucide="refresh-ccw"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Manager Status</th>
                                <th>HR Status</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="leaveTableBody">
                            <tr>
                                <td colspan="7" style="padding: 2rem; text-align: center;">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                                        <div class="fa-spin" style="font-size: 1.5rem; color: var(--primary);"><i class="fa-solid fa-spinner"></i></div>
                                        <p style="color: var(--text-muted); font-size: 0.9rem;">Initializing data...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ─── Leave Bank Overview Section ─── -->
            <div class="section-card" style="margin-top: 2rem;">
                <div class="card-header">
                    <div class="card-title">Leave Bank Overview</div>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">View remaining and total leave balances for all users.</p>
                </div>

                <div class="filter-bar">
                    <!-- User Filter -->
                    <div class="filter-group">
                        <select class="filter-select" id="lbUserFilter">
                            <!-- Populated dynamically -->
                            <option value="">Select User...</option>
                        </select>
                    </div>

                    <!-- Year Filter -->
                    <div class="filter-group">
                        <select class="filter-select" id="lbYearFilter">
                            <?php 
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                                echo "<option value='$y'>$y</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Month Filter (Optional for leave_bank table which usually tracks annual, but added as per request) -->
                    <div class="filter-group">
                        <select class="filter-select" id="lbMonthFilter">
                            <option value="All">All Months</option>
                            <?php 
                            for ($m = 1; $m <= 12; $m++) {
                                $monthName = date('F', mktime(0, 0, 0, $m, 1));
                                echo "<option value='$m'>$monthName</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <button class="btn-refresh" id="refreshLeaveBankBtn" title="Refresh Bank">
                        <i data-lucide="refresh-ccw"></i>
                    </button>
                </div>

                <div id="leaveBankCardsGrid" class="leave-bank-grid">
                    <div style="grid-column: 1 / -1; padding: 4rem; text-align: center;">
                        <div class="fa-spin" style="font-size: 1.5rem; color: var(--primary);"><i class="fa-solid fa-spinner"></i></div>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 1rem;">Loading leave bank...</p>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Modals -->
    <?php include 'modals/details_modal.php'; ?>
    <?php include 'modals/action_modal.php'; ?>
    <?php include 'modals/manual_leave_modal.php'; ?>
    <?php include 'modals/response_modal.php'; ?>

    <!-- Global Loader Overlay -->
    <div id="globalLoader" class="loader-overlay" style="display: none;">
        <div class="loader-content">
            <div class="spinner-modern"></div>
            <p>Processing request...</p>
        </div>
    </div>

    <!-- Layout script for interactivity -->
    <script src="js/script.js" defer></script>
</body>
</html>
