<?php
session_start();
// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$username  = $_SESSION['username'] ?? 'Manager';
$user_role = $_SESSION['role'] ?? 'user';
$user_id   = $_SESSION['user_id'];

// Check explicit payment permission
require_once '../../config/db_connect.php';
$is_admin = (strtolower($user_role) === 'admin' || strtolower($user_role) === 'administrator');
$pay_auth_stmt = $pdo->prepare("SELECT 1 FROM travel_payment_auth WHERE user_id = ?");
$pay_auth_stmt->execute([$user_id]);
$can_pay_auth = $pay_auth_stmt->fetchColumn() ? true : false;
if ($is_admin) $can_pay_auth = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expense Approval | Connect</title>
    
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    
    <!-- Reusable Sidebar Loader -->
    <script>
        window.SIDEBAR_BASE_PATH = '../../studio_users/';
    </script>
    <script src="../../studio_users/components/sidebar-loader.js" defer></script>
    
    <!-- PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <!-- Excel Generation Library (ExcelJS for styling) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
</head>
<body>

    <div class="dashboard-container">
        <!-- Sidebar mount -->
        <div id="sidebar-mount"></div>

        <main class="main-content">
            <!-- Page Header -->
            <header class="page-header">
                <div class="header-title">
                    <h1>Travel Expense Approvals</h1>
                    <p>Review and manage travel reimbursement requests from your team.</p>
                    <input type="hidden" id="currentUserRole" value="<?php echo $user_role; ?>">
                </div>
                <div class="header-actions">
                    <button class="btn-icon" title="Refresh list" id="refreshBtn">
                        <i data-lucide="refresh-cw"></i>
                    </button>
                </div>
            </header>


            <!-- Quick Overview Dashboard -->
            <section class="overview-section">
                <div class="overview-header">
                    <div class="title-wrap">
                        <i data-lucide="gauge" style="width: 24px; height: 24px; color: var(--primary);"></i>
                        <h2 style="font-size: 1.15rem; font-weight: 700; color: #1e293b;">Quick Overview</h2>
                        <span class="year-badge" id="stat-total-year">2026</span>
                    </div>
                </div>

                <!-- Status Counts Sub-section -->
                <div class="overview-subtitle">
                    <i data-lucide="bar-chart-2" style="width: 14px; height: 14px;"></i>
                    Status Counts
                </div>
                <div class="overview-grid">
                    <!-- Pending Approval -->
                    <div class="overview-card">
                        <div class="overview-card-content">
                            <div class="overview-card-header">
                                <div class="card-label-wrap">
                                    <i data-lucide="clock" style="width: 12px; height: 12px;"></i>
                                    Pending Approval
                                </div>
                                <div class="card-icon-badge pending">
                                    <i data-lucide="hourglass" style="width: 14px; height: 14px;"></i>
                                </div>
                            </div>
                            <div class="card-value" id="stat-pending-count">0</div>
                            <div class="card-subtext">Awaiting review</div>
                        </div>
                        <div class="overview-card-footer footer-pending">
                            <i data-lucide="arrow-right-circle" style="width: 12px; height: 12px;"></i>
                            Requires attention
                        </div>
                    </div>

                    <!-- Approved -->
                    <div class="overview-card">
                        <div class="overview-card-content">
                            <div class="overview-card-header">
                                <div class="card-label-wrap">
                                    <i data-lucide="check-square" style="width: 12px; height: 12px;"></i>
                                    Approved
                                </div>
                                <div class="card-icon-badge approved">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                </div>
                            </div>
                            <div class="card-value" id="stat-approved-count">0</div>
                            <div class="card-subtext">Fully processed</div>
                        </div>
                        <div class="overview-card-footer footer-approved">
                            <i data-lucide="check" style="width: 12px; height: 12px;"></i>
                            Completed successfully
                        </div>
                    </div>

                    <!-- Rejected -->
                    <div class="overview-card">
                        <div class="overview-card-content">
                            <div class="overview-card-header">
                                <div class="card-label-wrap">
                                    <i data-lucide="x-circle" style="width: 12px; height: 12px;"></i>
                                    Rejected
                                </div>
                                <div class="card-icon-badge rejected">
                                    <i data-lucide="info" style="width: 14px; height: 14px;"></i>
                                </div>
                            </div>
                            <div class="card-value" id="stat-rejected-count">0</div>
                            <div class="card-subtext">Not approved</div>
                        </div>
                        <div class="overview-card-footer footer-rejected">
                            <i data-lucide="alert-circle" style="width: 12px; height: 12px;"></i>
                            Requires revision
                        </div>
                    </div>

                    <!-- Total Amount -->
                    <div class="overview-card">
                        <div class="overview-card-content">
                            <div class="overview-card-header">
                                <div class="card-label-wrap">
                                    <i data-lucide="banknote" style="width: 12px; height: 12px;"></i>
                                    Total Amount
                                </div>
                                <div class="card-icon-badge amount">
                                    <i data-lucide="credit-card" style="width: 14px; height: 14px;"></i>
                                </div>
                            </div>
                            <div class="card-value" id="stat-total-amount">₹ 0.00</div>
                            <div class="card-subtext">All expenses</div>
                        </div>
                        <div class="overview-card-footer footer-amount">
                            <i data-lucide="zap" style="width: 12px; height: 12px;"></i>
                            Total expenditure
                        </div>
                    </div>
                </div>

                <!-- Financial Overview Sub-section -->
                <div class="overview-subtitle">
                    <i data-lucide="pie-chart" style="width: 14px; height: 14px;"></i>
                    Financial Overview
                </div>
                <div class="overview-grid">
                    <!-- Approved Amount -->
                    <div class="overview-card">
                        <div class="overview-card-content">
                            <div class="overview-card-header">
                                <div class="card-label-wrap">
                                    <i data-lucide="check-circle" style="width: 12px; height: 12px;"></i>
                                    Approved Amount
                                </div>
                            </div>
                            <div class="card-value" id="stat-approved-amount">₹ 0.00</div>
                            <div class="card-percentage" id="stat-approved-percent">0% of total</div>
                        </div>
                        <div class="overview-card-footer footer-approved">
                            <i data-lucide="check" style="width: 12px; height: 12px;"></i>
                            Approved expenses
                        </div>
                    </div>

                    <!-- Rejected Amount -->
                    <div class="overview-card">
                        <div class="overview-card-content">
                            <div class="overview-card-header">
                                <div class="card-label-wrap">
                                    <i data-lucide="minus-circle" style="width: 12px; height: 12px;"></i>
                                    Rejected Amount
                                </div>
                                <div class="card-icon-badge rejected" style="background: transparent;">
                                    <i data-lucide="copy" style="width: 18px; height: 18px; color: #ef4444; opacity: 0.5;"></i>
                                </div>
                            </div>
                            <div class="card-value" id="stat-rejected-amount">₹ 0.00</div>
                            <div class="card-percentage" id="stat-rejected-percent">0% of total</div>
                        </div>
                        <div class="overview-card-footer footer-rejected">
                            <i data-lucide="slash" style="width: 12px; height: 12px;"></i>
                            Declined expenses
                        </div>
                    </div>

                    <!-- Paid Amount -->
                    <div class="overview-card">
                        <div class="overview-card-content">
                            <div class="overview-card-header">
                                <div class="card-label-wrap">
                                    <i data-lucide="check-circle" style="width: 12px; height: 12px;"></i>
                                    Paid Amount
                                </div>
                                <div class="card-icon-badge amount" style="background: transparent;">
                                    <i data-lucide="tablet" style="width: 18px; height: 18px; color: var(--primary); opacity: 0.5;"></i>
                                </div>
                            </div>
                            <div class="card-value" id="stat-paid-amount">₹ 0.00</div>
                            <div class="card-percentage" id="stat-paid-percent">0% of approved</div>
                        </div>
                        <div class="overview-card-footer footer-amount" style="background: #ecfdf5; color: #047857; border-top-color: #a7f3d0;">
                            <i data-lucide="circle-dot" style="width: 12px; height: 12px;"></i>
                            Disbursed funds
                        </div>
                    </div>

                    <!-- Unpaid Payment -->
                    <div class="overview-card">
                        <div class="overview-card-content">
                            <div class="overview-card-header">
                                <div class="card-label-wrap">
                                    <i data-lucide="clock" style="width: 12px; height: 12px;"></i>
                                    Unpaid Payment
                                </div>
                                <div class="card-icon-badge" style="background: transparent;">
                                    <i data-lucide="hourglass" style="width: 18px; height: 18px; color: #64748b; opacity: 0.5;"></i>
                                </div>
                            </div>
                            <div class="card-value" id="stat-unpaid-amount">₹ 0.00</div>
                            <div class="card-percentage" id="stat-unpaid-percent">0% of approved</div>
                        </div>
                        <div class="overview-card-footer" style="background: #f8fafc; color: #64748b; border-top: 1px solid #e2e8f0;">
                            <i data-lucide="rotate-ccw" style="width: 12px; height: 12px;"></i>
                            Unpaid approved expenses
                        </div>
                    </div>
                </div>
            </section>

            <!-- Enhanced Filter Section -->
            <section class="section-card filter-section" id="filterSection">
                <div class="card-header">
                    <div class="card-title" style="display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="filter" style="width: 18px; height: 18px; color: var(--text-muted);"></i>
                        <span>Filter Expenses</span>
                    </div>
                    <button class="btn-text-blue" id="toggleFiltersBtn">
                        <i data-lucide="settings-2" style="width: 14px; height: 14px;"></i>
                        Toggle Filters
                    </button>
                </div>
                <div class="card-body">
                    <div class="filter-controls-inner" id="filterControlsInner">
                        <!-- Global Search -->
                        <div class="search-wrap-full" style="margin-bottom: 1.5rem;">
                            <div class="search-box" style="width: 100%;">
                                <i data-lucide="search"></i>
                                <input type="text" id="globalSearchInput" placeholder="Search by employee name, expense type, location, etc..." style="width: 100%;">
                            </div>
                        </div>

                        <!-- Dropdown Grid -->
                        <div class="filter-controls-grid">
                            <div class="control-group">
                                <label>Employee</label>
                                <select class="filter-select" id="employeeFilter" style="width: 100%;">
                                    <option value="All">All Employees</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label>Status</label>
                                <select class="filter-select" id="expenseStatusFilter" style="width: 100%;">
                                    <option value="All">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label>Month</label>
                                <select class="filter-select" id="monthFilter" style="width: 100%;">
                                    <option value="All">All Months</option>
                                    <option value="01">January</option>
                                    <option value="02">February</option>
                                    <option value="03">March</option>
                                    <option value="04">April</option>
                                    <option value="05">May</option>
                                    <option value="06">June</option>
                                    <option value="07">July</option>
                                    <option value="08">August</option>
                                    <option value="09">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label>Week</label>

                                <select class="filter-select" id="weekFilter" style="width: 100%;">
                                    <option value="All">All Weeks</option>
                                    <option value="1">Week 1 (1-7)</option>
                                    <option value="2">Week 2 (8-14)</option>
                                    <option value="3">Week 3 (15-21)</option>
                                    <option value="4">Week 4 (22-28)</option>
                                    <option value="5">Week 5 (29-End)</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label>Year</label>
                                <select class="filter-select" id="yearFilter" style="width: 100%;">
                                    <option value="2024">2024</option>
                                    <option value="2025">2025</option>
                                    <option value="2026">2026</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label>Approval Status</label>
                                <select class="filter-select" id="approvalLevelFilter" style="width: 100%;">
                                    <option value="All">All Approvals</option>
                                    <option value="l1">Manager Pending</option>
                                    <option value="l2">HR Pending</option>
                                    <option value="l3">Sr. Manager Pending</option>
                                </select>
                            </div>
                            <?php if ($can_pay_auth): ?>
                            <div class="control-group">
                                <label>Payment Status</label>
                                <select class="filter-select" id="paymentStatusFilter" style="width: 100%;">
                                    <option value="All">All Statuses</option>
                                    <option value="Paid">Paid</option>
                                    <option value="Pending">Pending</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer Actions -->
                        <div class="card-footer-actions" style="border-top: 1px solid var(--border); padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem;">
                            <button class="btn-minimal-with-icon" id="clearAllFiltersBtn">
                                <i data-lucide="x-circle" style="width: 16px; height: 16px;"></i>
                                Clear All
                            </button>
                            <button class="btn-primary-blue" id="applyFiltersBtn">
                                <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Expense Request List -->
            <div class="section-card">
                <div class="card-header" style="justify-content: space-between; align-items: center;">
                    <div class="card-title">Reimbursement Requests</div>
                    <div class="export-actions" style="display: flex; gap: 8px;">
                        <button class="btn-export pdf" id="exportPdfBtn" title="Export to PDF">
                            <i data-lucide="file-text"></i> PDF
                        </button>
                        <button class="btn-export excel" id="exportExcelBtn" title="Export to Excel">
                            <i data-lucide="file-spreadsheet"></i> Excel
                        </button>
                    </div>
                </div>
                        <div class="table-responsive">
                    <table>
                        <colgroup>
                            <col style="width: 14%;">
                            <col style="width: 16%;">
                            <col style="width: 8%;">
                            <col style="width: 8%;">
                            <col style="width: 8%;">
                            <col style="width: 10%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                            <col style="width: 9%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Trip/Purpose</th>
                                <th>Manager</th>
                                <th>HR</th>
                                <th>Sr. Manager</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Overall Status</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="expenseTableBody">
                            <!-- Mock Data for UI Design -->
                            <tr>
                                <td>
                                    <div style="font-weight: 600;">Alex Johnson</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">EMP1024</div>
                                </td>
                                <td>Client Meeting - Mumbai</td>
                                <td><span class="status-tag approved">Approved</span></td>
                                <td><span class="status-tag pending">Pending</span></td>
                                <td><span class="status-tag pending">Pending</span></td>
                                <td>28 Mar 2026</td>
                                <td class="amount">₹ 2,450.00</td>
                                <td><span class="status-tag pending">Pending</span></td>
                                <td><span class="status-tag pending">Unpaid</span></td>
                                <td class="actions">
                                    <div class="actions-group">
                                        <button class="btn-icon view" title="View Details" onclick="openDetails(0)"><i data-lucide="eye"></i></button>
                                        <button class="btn-icon approve" title="Approve" disabled><i data-lucide="check"></i></button>
                                        <button class="btn-icon reject" title="Reject" disabled><i data-lucide="x"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;">Sarah Miller</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">EMP1035</div>
                                </td>
                                <td>Tech Conference 2026</td>
                                <td><span class="status-tag approved">Approved</span></td>
                                <td><span class="status-tag approved">Approved</span></td>
                                <td><span class="status-tag pending">Pending</span></td>
                                <td>25 Mar 2026</td>
                                <td class="amount">₹ 8,900.00</td>
                                <td><span class="status-tag approved">Approved</span></td>
                                <td class="actions">
                                    <button class="btn-icon view" title="View Details"><i data-lucide="eye"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;">David Chen</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">EMP1012</div>
                                </td>
                                <td>On-site Support</td>
                                <td><span class="status-tag rejected">Rejected</span></td>
                                <td><span class="status-tag pending">Pending</span></td>
                                <td><span class="status-tag pending">Pending</span></td>
                                <td>22 Mar 2026</td>
                                <td class="amount">₹ 1,200.00</td>
                                <td><span class="status-tag rejected">Rejected</span></td>
                                <td class="actions">
                                    <button class="btn-icon view" title="View Details"><i data-lucide="eye"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Expense Detail Modal -->
    <div id="expenseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-info">
                    <span class="modal-label" id="modalDisplayId">EXP-0000</span>
                    <h2 id="modalEmployeeName">Employee Name</h2>
                </div>
                <button class="modal-close" onclick="closeModal()"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <!-- Global Rejection Notice -->
                <div id="rejectionNotice" style="display: none; margin-bottom: 25px; padding: 15px; background: var(--danger-light); border: 1px solid #fca5a5; border-radius: 12px; color: var(--danger);">
                    <div style="font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 1.05rem;">
                        <i data-lucide="x-octagon" style="width: 20px; height: 20px;"></i>
                        Claim Rejected by <span id="rejecterRole">Manager</span>
                    </div>
                    <div style="font-size: 0.9rem; padding-left: 28px; color: #b91c1c;">
                        <strong>Reason:</strong> <span id="rejectionReasonText">No reason provided.</span>
                    </div>
                </div>

                <div class="meter-photos-section" style="margin-bottom: 25px;">
                    <h3 style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="gauge" style="width: 18px; height: 18px;"></i>
                        Meter Picture Photo
                    </h3>
                    <div id="modalMeterPhotos" class="meters-grid">
                        <!-- Meter photos will be injected here -->
                    </div>
                </div>

                <!-- Attachments Section (Moved up for verification) -->
                <div class="attachments-section" style="margin-bottom: 25px;">
                    <h3 style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="paperclip" style="width: 18px; height: 18px;"></i>
                        Proof of Travel
                    </h3>
                    <div id="modalAttachments" class="attachments-grid">
                        <!-- Other attachments will be injected here -->
                    </div>
                </div>

                <!-- Distance Verification Section -->
                <div class="verification-info-bar" id="verificationBar">
                    <div class="header">
                        <i data-lucide="shield-alert" style="width: 16px; height: 16px; color: var(--primary-color);"></i>
                        Distance Verification Required
                    </div>
                    <div class="input-group">
                        <div class="distance-input-wrapper">
                            <i data-lucide="image" style="width: 16px; height: 16px; color: #94a3b8; margin-right: 10px;"></i>
                            <input type="number" id="verifyDistanceInput" placeholder="Enter distance calculated from meter..." step="0.01">
                            <span class="unit">KM</span>
                        </div>
                        <button class="btn-verify" id="verifyDistanceBtn" onclick="verifyDistance()">
                            <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                            I Checked
                        </button>
                    </div>
                    <div id="verificationMsg" class="verification-message warning">
                        <i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i>
                        Please enter the distance to view expense details
                    </div>
                    <div id="verificationHelperTxt" class="verification-message info">
                        <i data-lucide="info" style="width: 14px; height: 14px;"></i>
                        <span>Verify if odometer readings match the claimed distance.</span>
                    </div>
                </div>

                <div id="blurTarget" class="blur-content">
                    <!-- Info Grid -->
                    <div class="info-grid">
                        <div class="info-item full">
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="map-pin" style="width: 13px; height: 13px;"></i>
                                Purpose/Trip
                            </label>
                            <p id="modalPurpose">Purpose of the trip here...</p>
                        </div>
                        <div class="info-item">
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="navigation" style="width: 13px; height: 13px;"></i>
                                From
                            </label>
                            <p id="modalFrom">Starting Point</p>
                        </div>
                        <div class="info-item">
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="map" style="width: 13px; height: 13px;"></i>
                                To
                            </label>
                            <p id="modalTo">Destination</p>
                        </div>
                        <div class="info-item">
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="car-front" style="width: 13px; height: 13px;"></i>
                                Transport Mode
                            </label>
                            <p id="modalMode">Mode</p>
                        </div>
                        <div class="info-item">
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="route" style="width: 13px; height: 13px;"></i>
                                Distance
                            </label>
                            <p id="modalKm">0.00 KM</p>
                        </div>
                        <div class="info-item">
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="calendar-days" style="width: 13px; height: 13px;"></i>
                                Travel Date
                            </label>
                            <p id="modalDate">Jan 01, 2026</p>
                        </div>
                        <div class="info-item" id="modalResubWrapper" style="display: none;">
                            <label style="display: flex; align-items: center; gap: 6px; color: #3b82f6;">
                                <i data-lucide="rotate-ccw" style="width: 13px; height: 13px;"></i>
                                Resubmissions
                            </label>
                            <p id="modalResubCount" style="color: #2563eb; font-weight: 600;">0 / 3</p>
                        </div>
                        <div class="info-item highlight">
                            <label style="display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="banknote" style="width: 13px; height: 13px;"></i>
                                Total Amount
                            </label>
                            <p id="modalAmount">₹ 0.00</p>
                        </div>
                    </div>

                    <!-- Approval Status Section -->
                    <div class="approval-history" style="margin-top: 30px;">
                        <h3 style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="history" style="width: 18px; height: 18px;"></i>
                            Review History
                        </h3>
                        <div class="history-steps">
                            <div class="history-step" id="step-manager">
                                <div class="step-icon"><i data-lucide="user-check"></i></div>
                                <div class="step-info">
                                    <h4>Manager Approval</h4>
                                    <div class="step-status" id="managerStatus">Pending</div>
                                    <div class="step-reason" id="managerReason">No reason provided.</div>
                                </div>
                            </div>
                            <div class="history-step" id="step-hr">
                                <div class="step-icon"><i data-lucide="shield-check"></i></div>
                                <div class="step-info">
                                    <h4>HR Verification</h4>
                                    <div class="step-status" id="hrStatus">Pending</div>
                                    <div class="step-reason" id="hrReason">No reason provided.</div>
                                </div>
                            </div>
                            <div class="history-step" id="step-senior">
                                <div class="step-icon"><i data-lucide="award"></i></div>
                                <div class="step-info">
                                    <h4>Senior Manager Approval</h4>
                                    <div class="step-status" id="seniorStatus">Pending</div>
                                    <div class="step-reason" id="seniorReason">No reason provided.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="modalFooterActions">
                <!-- Action buttons will be injected here -->
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal" style="z-index: 2500;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <div class="modal-header-info">
                    <h2>Approve Claim</h2>
                </div>
                <button class="modal-close" onclick="closeActionModal('approveModal')"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px; font-size: 0.9rem; color: var(--text-muted);">Please confirm the following checks before approving the travel expense claim.</p>
                <div class="checkpoints" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" class="approve-chk" style="margin-top: 3px; accent-color: var(--success);">
                        <span>I have verified that the claimed distance matches the meter readings.</span>
                    </label>
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" class="approve-chk" style="margin-top: 3px; accent-color: var(--success);">
                        <span>I confirm that the bill amount and purpose are legitimate.</span>
                    </label>
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" class="approve-chk" style="margin-top: 3px; accent-color: var(--success);">
                        <span>I approve this travel expense according to company policy.</span>
                    </label>
                </div>
                <div class="input-group" style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Additional Comments (Optional)</label>
                    <textarea id="approveReason" rows="3" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem; resize: vertical;" placeholder="Add any comments here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-minimal" onclick="closeActionModal('approveModal')">Cancel</button>
                <button class="btn-icon approve" id="confirmApproveBtn" onclick="submitApprove()" disabled style="opacity: 0.5; cursor: not-allowed; border: 1px solid var(--success);"><i data-lucide="check"></i> Confirm Approval</button>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal" style="z-index: 2500;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <div class="modal-header-info">
                    <h2>Reject Claim</h2>
                </div>
                <button class="modal-close" onclick="closeActionModal('rejectModal')"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px; font-size: 0.9rem; color: var(--text-muted);">Please confirm the following checks before rejecting the travel expense claim.</p>
                <div class="checkpoints" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" class="reject-chk" style="margin-top: 3px; accent-color: var(--danger);">
                        <span>I have reviewed the claimed distance and proofs provided.</span>
                    </label>
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" class="reject-chk" style="margin-top: 3px; accent-color: var(--danger);">
                        <span>I have found discrepancies or policy violations in this claim.</span>
                    </label>
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" class="reject-chk" style="margin-top: 3px; accent-color: var(--danger);">
                        <span>I understand that this action will notify the employee.</span>
                    </label>
                </div>
                <div class="input-group" style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Reason for Rejection (Mandatory, min 10 words) <span style="color: var(--danger);">*</span></label>
                    <textarea id="rejectReason" rows="3" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem; resize: vertical;" placeholder="Please provide a detailed reason..."></textarea>
                    <div id="rejectWordCount" style="font-size: 0.75rem; color: var(--danger); text-align: right; font-weight: 600;">0 / 10 words</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-minimal" onclick="closeActionModal('rejectModal')">Cancel</button>
                <button class="btn-icon reject" id="confirmRejectBtn" onclick="submitReject()" disabled style="opacity: 0.5; cursor: not-allowed; border: 1px solid var(--danger);"><i data-lucide="x"></i> Confirm Rejection</button>
            </div>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div id="editExpenseModal" class="modal" style="z-index: 2600;">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <div class="modal-header-info">
                    <h2>Edit Travel Expense</h2>
                </div>
                <button class="modal-close" onclick="closeActionModal('editExpenseModal')"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editExpenseId">

                <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;">
                    <div style="grid-column: 1 / -1; display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">Purpose/Trip</label>
                        <textarea id="editPurpose" rows="2" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem; resize: vertical;"></textarea>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">From</label>
                        <input type="text" id="editFrom" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem;">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">To</label>
                        <input type="text" id="editTo" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem;">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">Transport Mode</label>
                        <input type="text" id="editMode" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem;">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">Travel Date</label>
                        <input type="date" id="editDate" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem;">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">Distance (KM)</label>
                        <input type="number" id="editDistance" step="0.01" min="0" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem;">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">Amount (INR)</label>
                        <input type="number" id="editAmount" step="0.01" min="0" style="width: 100%; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; font-family: inherit; font-size: 0.88rem;">
                        <div id="editAmountHint" style="font-size: 0.75rem; color: var(--text-muted);">For Car/Bike, amount is auto-calculated from configured per-km rate.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-minimal" onclick="closeActionModal('editExpenseModal')">Cancel</button>
                <button class="btn-icon edit" onclick="submitExpenseEdit()"><i data-lucide="pencil"></i> Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Stack Details Modal -->
    <div id="stackModal" class="modal" style="z-index: 2550;">
        <div class="modal-content" style="max-width: 950px;">
            <div class="modal-header">
                <div class="modal-header-info">
                    <span class="modal-label" id="stackModalMeta">Travel Stack</span>
                    <h2 id="stackModalTitle">Grouped Expenses</h2>
                </div>
                <button class="modal-close" onclick="closeStackModal()"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body" style="background: #f8fafc;">
                <div id="stackModalSummary" style="margin-bottom: 1rem;"></div>
                <div id="stackBulkApproveSection" style="margin-bottom: 1rem;"></div>
                <div id="stackModalList" class="stack-list-grid"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-minimal" onclick="closeStackModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Interactivity Script -->
    <script src="js/script.js" defer></script>
</body>
</html>
