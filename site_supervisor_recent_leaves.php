<?php
// Site Supervisor "Apply Leave" page
// Fetch leave types from database
session_start();

// Check if user is logged in and has an allowed role for this page
$allowedRoles = ['Site Supervisor', 'Site Coordinator', 'Purchase manager', 'Purchase Manager'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
    header("Location: unauthorized.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Test database connection and tables
try {
    // Test if tables exist
    $test_stmt = $pdo->query("SHOW TABLES LIKE 'leave_types'");
    $leave_types_exists = $test_stmt->rowCount() > 0;
    
    $test_stmt = $pdo->query("SHOW TABLES LIKE 'leave_request'");
    $leave_requests_exists = $test_stmt->rowCount() > 0;
    
    error_log("Debug - leave_types table exists: " . ($leave_types_exists ? 'YES' : 'NO'));
    error_log("Debug - leave_requests table exists: " . ($leave_requests_exists ? 'YES' : 'NO'));
    
} catch (Exception $e) {
    error_log("Database connection test failed: " . $e->getMessage());
}

// Fetch active leave types from database
$leave_types = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, max_days FROM leave_types WHERE status = 'active' ORDER BY name ASC");
    $stmt->execute();
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching leave types: " . $e->getMessage());
    $leave_types = [];
}

// Fetch Senior Manager (Site) approver
$senior_manager = null;
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'Senior Manager (Site)' ORDER BY id LIMIT 1");
    $stmt->execute();
    $senior_manager = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Exception $e) {
    error_log("Error fetching senior manager: " . $e->getMessage());
}

// Calculate leave balances for the current user
$leave_balances = [];
$current_year = date('Y');
$user_id = $_SESSION['user_id'];

// Compute earned Compensate Leave (Comp-Off) for current year
$earned_comp_off = 0;
// Also collect earned dates to allow selection while applying
$earned_comp_off_dates = [];
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.date) AS earned
        FROM attendance a
        JOIN user_shifts us
          ON us.user_id = a.user_id
         AND a.date >= us.effective_from
         AND (us.effective_to IS NULL OR a.date <= us.effective_to)
        WHERE a.user_id = ?
          AND YEAR(a.date) = ?
          AND (
                a.is_weekly_off = 1
             OR DAYNAME(a.date) = us.weekly_offs
          )
          AND (a.punch_in IS NOT NULL OR a.punch_out IS NOT NULL)
    ");
    $stmt->execute([$user_id, $current_year]);
    $earned_comp_off = (int)$stmt->fetchColumn();
    // Fetch the actual list of dates (descending)
    $stmt = $pdo->prepare("
        SELECT a.date
        FROM attendance a
        JOIN user_shifts us
          ON us.user_id = a.user_id
         AND a.date >= us.effective_from
         AND (us.effective_to IS NULL OR a.date <= us.effective_to)
        WHERE a.user_id = ?
          AND YEAR(a.date) = ?
          AND (
                a.is_weekly_off = 1
             OR DAYNAME(a.date) = us.weekly_offs
          )
          AND (a.punch_in IS NOT NULL OR a.punch_out IS NOT NULL)
        ORDER BY a.date DESC
    ");
    $stmt->execute([$user_id, $current_year]);
    $earned_comp_off_dates = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
} catch (Exception $e) {
    error_log('Error calculating earned comp-off: ' . $e->getMessage());
}

// Fetch user's current shift (for Short Leave time window)
$user_shift = [
    'start_time' => null,
    'end_time' => null
];
try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("\n        SELECT s.start_time, s.end_time\n        FROM user_shifts us\n        JOIN shifts s ON s.id = us.shift_id\n        WHERE us.user_id = ?\n          AND us.effective_from <= ?\n          AND (us.effective_to IS NULL OR us.effective_to >= ?)\n        ORDER BY us.effective_from DESC\n        LIMIT 1\n    ");
    $stmt->execute([$user_id, $today, $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $user_shift['start_time'] = $row['start_time'];
        $user_shift['end_time'] = $row['end_time'];
    }
} catch (Exception $e) {
    error_log('Error fetching user shift: ' . $e->getMessage());
}

// Short Leave monthly policy: limit 2 per month
$short_leave_month_limit = 2;
$short_leave_type_id = null;
$short_month_approved = 0;
$short_month_pending = 0;
$short_month_used = 0;
try {
    // Try to find Short Leave type id
    $stmt = $pdo->prepare("SELECT id FROM leave_types WHERE status='active' AND LOWER(name) LIKE '%short%'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $short_leave_type_id = (int)$row['id'];
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');
        $stmt = $pdo->prepare("\n            SELECT\n                SUM(CASE WHEN LOWER(status)='approved' THEN 1 ELSE 0 END) AS approved_cnt,\n                SUM(CASE WHEN LOWER(status)='pending'  THEN 1 ELSE 0 END) AS pending_cnt\n            FROM leave_request\n            WHERE user_id = ? AND leave_type = ? AND start_date BETWEEN ? AND ?\n        ");
        $stmt->execute([$user_id, $short_leave_type_id, $monthStart, $monthEnd]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['approved_cnt'=>0,'pending_cnt'=>0];
        $short_month_approved = (int)$c['approved_cnt'];
        $short_month_pending  = (int)$c['pending_cnt'];
        $short_month_used     = $short_month_approved + $short_month_pending;
    }
} catch (Exception $e) {
    error_log('Error calculating short leave monthly usage: ' . $e->getMessage());
}

try {
    // Get ALL leave types with detailed usage breakdown
    $stmt = $pdo->prepare("
        SELECT 
            lt.id,
            lt.name,
            lt.max_days,
            COALESCE(SUM(
                CASE 
                    WHEN LOWER(lr.status) IN ('approved', 'pending') 
                    THEN lr.duration 
                    ELSE 0 
                END
            ), 0) as used_days,
            COALESCE(SUM(
                CASE 
                    WHEN LOWER(lr.status) = 'approved' 
                    THEN lr.duration 
                    ELSE 0 
                END
            ), 0) as approved_days,
            COALESCE(SUM(
                CASE 
                    WHEN LOWER(lr.status) = 'pending' 
                    THEN lr.duration 
                    ELSE 0 
                END
            ), 0) as pending_days
        FROM leave_types lt
        LEFT JOIN leave_request lr ON lt.id = lr.leave_type 
            AND lr.user_id = ? 
            AND YEAR(lr.start_date) = ?
        WHERE lt.status = 'active'
        GROUP BY lt.id, lt.name, lt.max_days
        ORDER BY lt.name ASC
    ");
    $stmt->execute([$user_id, $current_year]);
    $balance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format balance data for display - including ALL leave types
    foreach ($balance_data as $balance) {
        $isCompOff = stripos($balance['name'], 'comp') !== false; // match Compensate/Comp Off
        $isShort   = stripos($balance['name'], 'short') !== false;
        $maxDays = (int)$balance['max_days'];
        if ($isCompOff) {
            $maxDays = $earned_comp_off;
        } elseif ($isShort) {
            $maxDays = $short_leave_month_limit;
        }

        $effective_used = $balance['used_days'];
        $effective_approved = $balance['approved_days'];
        $effective_pending = $balance['pending_days'];
        if ($isShort) {
            $effective_used = $short_month_used;
            $effective_approved = $short_month_approved;
            $effective_pending = $short_month_pending;
        }

        if ($maxDays > 0) {
            $remaining = max(0, $maxDays - $effective_used);
            $percentage = ($maxDays > 0) ? (($remaining / $maxDays) * 100) : 0;
            $is_unlimited = false;
        } else {
            // For zero allowance, show 0 remaining and 0%
            $remaining = $isCompOff ? 0 : 'No limit';
            $percentage = $isCompOff ? 0 : 100;
            $is_unlimited = !$isCompOff && ($balance['max_days'] == 0);
        }
        
        $leave_balances[] = [
            'id' => $balance['id'],
            'name' => $balance['name'],
            'max_days' => $maxDays,
            'used_days' => $effective_used,
            'approved_days' => $effective_approved,
            'pending_days' => $effective_pending,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'is_unlimited' => $is_unlimited
        ];
    }
} catch (Exception $e) {
    error_log("Error calculating leave balances: " . $e->getMessage());
    $leave_balances = [];
}

// Debug information removed

// If no balance data was calculated (all zero usage), ensure all leave types are still shown
if (empty($leave_balances) && !empty($leave_types)) {
    // Create entries for leave types with zero usage
    foreach ($leave_types as $type) {
        $isCompOff = stripos($type['name'], 'comp') !== false;
        $maxDays = $isCompOff ? $earned_comp_off : (int)$type['max_days'];

        if ($maxDays > 0) {
            $remaining = $maxDays;
            $percentage = 100;
            $is_unlimited = false;
        } else {
            $remaining = $isCompOff ? 0 : 'No limit';
            $percentage = $isCompOff ? 0 : 100;
            $is_unlimited = !$isCompOff && ($type['max_days'] == 0);
        }
        
        $leave_balances[] = [
            'id' => $type['id'],
            'name' => $type['name'],
            'max_days' => $maxDays,
            'used_days' => 0,
            'approved_days' => 0,
            'pending_days' => 0,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'is_unlimited' => $is_unlimited
        ];
    }
    error_log("Debug - Created zero-usage balances for all leave types (no leave requests found).");
}

// Build a quick lookup for balances by leave type id (used by the form preview/help)
$leave_balance_map = [];
foreach ($leave_balances as $b) {
    $leave_balance_map[$b['id']] = $b;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Site Supervisor – Apply Leave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --ss-primary: #3b82f6;
            --ss-muted: #6b7280;
            --card-radius: 14px;
            --surface: #ffffff;
            --bg: #f6f7fb;
            --panel-width: 280px;
            --panel-collapsed: 70px;
        }
        body {
            background: radial-gradient(1200px 600px at 100% -10%, rgba(59,130,246,.10), transparent 60%), var(--bg);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        /* Hide scrollbars while maintaining functionality */
        * {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        *::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        /* Left Panel Styles - Matching dashboard.css exactly */
        .left-panel {
            width: var(--panel-width);
            background: linear-gradient(180deg, #2a4365, #1a365d);
            color: #fff;
            height: 100vh;
            transition: all 0.3s ease;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            
            /* Hide scrollbar but keep functionality */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .left-panel::-webkit-scrollbar {
            display: none;
            width: 0;
        }

        .left-panel.collapsed {
            width: var(--panel-collapsed);
        }

        .left-panel .brand-logo {
            padding: 20px 25px;
            margin-bottom: 0;
        }

        .left-panel .brand-logo img {
            max-height: 30px;
            width: auto;
        }

        .menu-item {
            padding: 16px 25px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin: 5px 0;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: #fff;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid rgba(255, 255, 255, 0.8);
            padding-left: 30px;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #fff;
        }

        .menu-item i {
            margin-right: 15px;
            width: 16px;
            font-size: 1em;
            text-align: center;
            position: relative;
            z-index: 1;
            color: rgba(255, 255, 255, 0.85);
            display: inline-block;
            opacity: 0.9;
        }

        .menu-text {
            transition: all 0.3s ease;
            font-size: 0.95em;
            letter-spacing: 0.3px;
            font-weight: 500;
            position: relative;
            z-index: 1;
            white-space: nowrap;
            padding-left: 5px;
        }

        .left-panel.collapsed .menu-text {
            display: none;
        }

        .left-panel.collapsed .menu-item i {
            width: 100%;
            margin-right: 0;
            font-size: 1.1em;
            opacity: 1;
        }

        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(197, 48, 48, 0.1);
        }

        .logout-item:hover {
            background: rgba(197, 48, 48, 0.2);
            border-left: 4px solid #c53030 !important;
        }

        .logout-item i {
            color: #f56565 !important;
        }

        .menu-item.section-start {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        .toggle-btn {
            position: absolute;
            right: -18px;
            top: 25px;
            background: #ffffff;
            border: none;
            color: #2a4365;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
            overflow: visible;
        }

        .toggle-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            background: #f0f4f8;
        }

        .toggle-btn i {
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
            line-height: 1;
        }
        /* Main Content Adjustments */
        .main-content {
            margin-left: var(--panel-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            background: var(--bg);
            width: calc(100vw - var(--panel-width));
            max-width: none;
        }
        .main-content.expanded { 
            margin-left: var(--panel-collapsed);
            width: calc(100vw - var(--panel-collapsed));
        }
        /* Full width layout for larger screens */
        @media (min-width: 1200px) {
            .container {
                max-width: none;
                padding-left: 30px;
                padding-right: 30px;
            }
            .ss-card {
                padding: 25px !important;
            }
            .hero-strip {
                padding: 20px 25px;
            }
        }

        @media (min-width: 1400px) {
            .container {
                padding-left: 40px;
                padding-right: 40px;
            }
            .ss-card {
                padding: 30px !important;
            }
            .hero-strip {
                padding: 25px 30px;
            }
        }

        /* Responsive Design for all screen sizes */
        @media (max-width: 1024px) {
            .main-content {
                width: auto !important;
            }
            .container {
                padding-left: 20px;
                padding-right: 20px;
            }
            .ss-card {
                padding: 20px !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                width: 100vw !important;
                margin-left: 0 !important;
            }
            .hero-strip {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                padding: 15px;
            }
            
            .row.g-4 {
                flex-direction: column;
            }
            
            .col-lg-8, .col-lg-4 {
                max-width: 100%;
                flex: 0 0 100%;
            }
            
            .ss-card {
                padding: 15px !important;
                margin-bottom: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .chip {
                font-size: 0.8rem;
                padding: 0.25rem 0.5rem;
            }
            
            .avatar {
                width: 35px;
                height: 35px;
            }
        }

        /* iPhone XR (414x896) and similar large phones */
        @media (max-width: 414px) and (min-height: 800px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .hero-strip {
                padding: 12px;
            }
            
            .ss-card {
                padding: 12px !important;
            }
            
            .page-title {
                font-size: 1.4rem;
            }
            
            .toolbar .row {
                margin: 0 -8px;
            }
            
            .toolbar .col-6,
            .toolbar .col-12 {
                padding: 0 8px;
                margin-bottom: 15px;
            }
            
            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 10px 12px;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
            
            .d-flex.gap-2 {
                flex-wrap: wrap;
                gap: 8px !important;
            }
        }

        /* iPhone SE (375x667) and smaller screens */
        @media (max-width: 375px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            .hero-strip {
                padding: 10px;
            }
            
            .ss-card {
                padding: 10px !important;
            }
            
            .page-title {
                font-size: 1.3rem;
                line-height: 1.3;
            }
            
            .avatar {
                width: 30px;
                height: 30px;
            }
            
            .form-control, .form-select {
                font-size: 16px;
                padding: 8px 10px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            
            .chip {
                font-size: 0.75rem;
                padding: 0.2rem 0.4rem;
            }
            
            .d-flex.gap-2.flex-wrap {
                gap: 6px !important;
            }
            
            .toolbar .form-label {
                font-size: 0.9rem;
                margin-bottom: 5px;
            }
            
            .badge {
                font-size: 0.7rem;
                padding: 3px 6px;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .progress-sm {
                height: 0.4rem;
            }
        }

        /* Very small screens (320px and below) */
        @media (max-width: 320px) {
            .container {
                padding-left: 8px;
                padding-right: 8px;
            }
            
            .hero-strip {
                padding: 8px;
            }
            
            .ss-card {
                padding: 8px !important;
            }
            
            .page-title {
                font-size: 1.2rem;
            }
            
            .form-control, .form-select {
                font-size: 14px;
                padding: 6px 8px;
            }
            
            .btn {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .chip {
                font-size: 0.7rem;
                padding: 0.15rem 0.3rem;
            }
            
            .avatar {
                width: 25px;
                height: 25px;
            }
            
            .toolbar .col-6,
            .toolbar .col-12 {
                margin-bottom: 10px;
            }
        }

        /* Landscape orientation for mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .container {
                padding-top: 10px;
                padding-bottom: 10px;
            }
            
            .hero-strip {
                padding: 8px 15px;
            }
            
            .ss-card {
                padding: 10px !important;
            }
            
            .page-title {
                font-size: 1.2rem;
            }
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .page-title {
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .ss-card {
            background: var(--surface);
            border: 1px solid #eef0f4;
            border-radius: var(--card-radius);
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        }
        .hero-strip {
            background: linear-gradient(90deg, rgba(59,130,246,.15), rgba(59,130,246,.03));
            border: 1px solid rgba(59,130,246,.15);
            border-radius: 12px;
            padding: .75rem 1rem;
        }
        .toolbar .form-control, .toolbar .form-select { border-radius: 10px; }
        .btn-outline-primary { border-color: var(--ss-primary); color: var(--ss-primary); }
        .btn-outline-primary:hover { background: var(--ss-primary); color: #fff; }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--ss-muted);
        }
        .badge-status {
            background: #eef2ff;
            color: #1f2937;
            border: 1px solid #e5e7eb;
        }
        .table thead th { color: #374151; font-weight: 600; }
        .table > :not(caption) > * > * { padding: 0.9rem 1rem; }
        .rounded-xl { border-radius: 12px; }
        .section-title { font-weight: 600; color: #374151; }
        .hint { color: var(--ss-muted); font-size: .875rem; }
        .chip { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .7rem; border-radius:999px; background:#eef2ff; color:#1f2937; border:1px solid #e5e7eb; font-size:.85rem; cursor:pointer; user-select:none; }
        .chip:hover { background:#e0e7ff; }
        .avatar { width:40px; height:40px; border-radius:50%; background:#e5e7eb; display:inline-flex; align-items:center; justify-content:center; color:#374151; font-weight:700; }
        .progress-sm { height: .5rem; }
    </style>
    <!-- Note: Replace inline styles with project-wide styles if available -->
</head>
<body>
<?php
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Site Supervisor') {
        include 'includes/supervisor_panel.php';
    } else {
        include 'includes/manager_panel.php';
    }
?>

<!-- Serialized leave balance map for JS to read -->
<script id="leaveBalanceMap" type="application/json">
<?= json_encode($leave_balance_map ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<div class="main-content">
<div class="container py-4 py-md-5">
    <div class="page-header mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 text-muted small mb-1">
                <a href="site_supervisor_dashboard.php" class="text-decoration-none text-secondary"><i class="bi bi-arrow-left"></i> Dashboard</a>
                <span class="text-secondary">/</span>
                <a href="#" class="text-decoration-none text-secondary">Leaves</a>
                <span class="text-secondary">/</span>
                <span class="text-dark">Apply</span>
            </div>
            <h1 class="h4 h3-md page-title m-0">Site Supervisor – Apply Leave</h1>
        </div>
        <div class="d-flex align-items-center gap-2"></div>
    </div>

    <div class="hero-strip mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar"><i class="bi bi-person"></i></div>
            <div>
                <div class="fw-semibold">Apply for Leave</div>
                <div class="small text-secondary">Fill the form and submit for approval</div>
            </div>
        </div>
        <div class="text-secondary small">Typical approval time: 1 business day</div>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-8">
        <div class="ss-card p-3 p-md-4 mb-4">
        <form id="apply-leave-form" class="row g-3 toolbar">
            <div class="col-12 col-md-3">
                <label class="form-label">Approver</label>
                <select class="form-select" name="approver">
                    <?php if ($senior_manager): ?>
                        <option value="<?= (int)$senior_manager['id'] ?>" selected>
                            <?= htmlspecialchars($senior_manager['username']) ?> (Senior Manager (Site))
                        </option>
                    <?php else: ?>
                        <option value="" selected>Senior Manager (Site)</option>
                    <?php endif; ?>
                    <option value="manager">Manager</option>
                    <option value="hr">HR</option>
                </select>
            </div>
            
            <div class="col-12">
                <label class="form-label">Reason</label>
                <textarea class="form-control" name="reason" rows="3" placeholder="Brief reason for leave" required></textarea>
            </div>
            
            <div class="col-6 col-md-3">
                <label class="form-label">From</label>
                <input type="date" class="form-control" name="date_from" required />
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">To</label>
                <input type="date" class="form-control" name="date_to" required />
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="generateDateList">
                    <i class="bi bi-calendar-check me-1"></i>Generate Date List
                </button>
            </div>
            
            <!-- Date list with leave type options -->
            <div class="col-12 mt-3" id="dateListContainer" style="display:none;">
                <div class="card border">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="m-0">Leave Date Selection</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllDates">
                                <i class="bi bi-check-all me-1"></i>Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="clearAllDates">
                                <i class="bi bi-x-lg me-1"></i>Clear All
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" id="leaveDateTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="checkAllDates" checked>
                                            </div>
                                        </th>
                                        <th width="20%">Date</th>
                                        <th width="20%">Day</th>
                                        <th width="25%">Leave Type</th>
                                        <th width="30%">Day Type</th>
                                    </tr>
                                </thead>
                                <tbody id="dateListBody">
                                    <!-- Date rows will be added here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-4" id="defaultLeaveTypeContainer">
                <label class="form-label">Leave Type</label>
                <select class="form-select" name="leave_type" id="defaultLeaveType" required>
                    <option value="" selected disabled>Select leave type</option>
                    <?php foreach ($leave_types as $leave_type): ?>
                        <option value="<?= htmlspecialchars($leave_type['id']) ?>" 
                                data-max-days="<?= htmlspecialchars($leave_type['max_days']) ?>"
                                title="<?= htmlspecialchars($leave_type['description']) ?>">
                            <?= htmlspecialchars($leave_type['name']) ?>
                            <?php if ($leave_type['max_days'] > 0): ?>
                                (Max: <?= $leave_type['max_days'] ?> days)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($leave_types)): ?>
                        <option value="" disabled>No leave types available</option>
                    <?php endif; ?>
                </select>
                <div id="leaveTypeHint" class="small text-muted mt-1">Select the leave type for your request.</div>
                <div id="compOffHint" class="small text-primary mt-2" style="display:none;"></div>
            </div>

            <!-- Compensate days are now auto-selected -->
            <input type="hidden" name="comp_off_source_date" id="compOffSourceDate" value="" />

            <!-- Short Leave session (visible only when Short Leave is selected) -->
            <div class="col-12 col-md-6" id="shortLeaveTypeWrapper" style="display:none;">
                <label class="form-label">Short Leave Session</label>
                <select class="form-select" id="shortLeaveSession">
                    <option value="morning" selected>Morning short leave</option>
                    <option value="evening">Evening short leave</option>
                </select>
                <div class="hint mt-1" id="shortLeaveAutoText">
                    <?php if ($user_shift['start_time'] && $user_shift['end_time']): ?>
                        Shift: <?= htmlspecialchars(substr($user_shift['start_time'],0,5)) ?>–<?= htmlspecialchars(substr($user_shift['end_time'],0,5)) ?>. 1h 30m will be auto-set.
                    <?php else: ?>
                        1h 30m will be auto-set from shift timings.
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12">
                <div class="p-3 border rounded-3 d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-secondary">Calculated duration:</span>
                        <span class="badge bg-primary-subtle text-primary-emphasis border" id="durationBadge">0 days</span>
                    </div>
                    <div class="text-secondary small">Inclusive of start and end dates. Half-day reduces total to 0.5 day when applicable.</div>
                </div>
            </div>

            <div class="col-12 d-flex gap-2 justify-content-end pt-2">
                <a href="site_supervisor_dashboard.php" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="ss-card p-3 p-md-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="fw-semibold">Your Leave Balance</div>
                <span class="badge text-bg-light border">FY <?= $current_year ?></span>
            </div>
            
            <?php if (empty($leave_balances)): ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-calendar-x fs-4 d-block mb-2"></i>
                    <div>No leave balance data available</div>
                    <div class="small mt-2">
                        <?php if (empty($leave_types)): ?>
                            No leave types configured in system
                        <?php else: ?>
                            No leave types with day limits found
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Debug info (remove in production) -->
                <div class="small text-danger mt-2" style="display: none;">
                    Debug: Leave types: <?= count($leave_types) ?>, 
                    User ID: <?= $user_id ?>, 
                    Year: <?= $current_year ?>
                </div>
            <?php else: ?>
                <?php foreach ($leave_balances as $index => $balance): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small">
                            <span class="fw-medium">
                                <span data-leave-type-id="<?= $balance['id'] ?>"><?= htmlspecialchars($balance['name']) ?></span>
                                <?php if ($balance['used_days'] > 0): ?>
                                    <button class="btn btn-link p-0 ms-1 text-info" 
                                            style="font-size: 0.8rem; line-height: 1;"
                                            onclick="toggleLeaveDetails(<?= $balance['id'] ?>)"
                                            title="View detailed leave history">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </span>
                            <span>
                                <?php if ($balance['is_unlimited']): ?>
                                    <strong id="bal-<?= $balance['id'] ?>">No limit</strong>
                                    <?php if ($balance['used_days'] > 0): ?>
                                        <span class="text-danger fw-bold">(Used: <?= $balance['used_days'] ?> days)</span>
                                    <?php else: ?>
                                        <span class="text-success">(Not used)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <strong id="bal-<?= $balance['id'] ?>" class="<?= $balance['used_days'] > 0 ? 'text-primary' : 'text-success' ?>">
                                        <?= $balance['remaining'] ?>
                                    </strong> 
                                    / <?= $balance['max_days'] ?> days
                                    <?php if ($balance['used_days'] > 0): ?>
                                        <span class="text-danger fw-bold">(Used: <?= $balance['used_days'] ?>)</span>
                                    <?php else: ?>
                                        <span class="text-success">(Full balance)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <!-- Collapsible leave details -->
                        <?php if ($balance['used_days'] > 0): ?>
                            <div id="leave-details-<?= $balance['id'] ?>" class="leave-details mt-2" style="display: none;">
                                <div class="small text-muted">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    <strong>Leave Details:</strong>
                                </div>
                                <div class="mt-1">
                                    <?php if ($balance['approved_days'] > 0): ?>
                                        <span class="badge text-bg-success me-1">
                                            <i class="bi bi-check-circle me-1"></i>Approved: <?= $balance['approved_days'] ?> days
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($balance['pending_days'] > 0): ?>
                                        <span class="badge text-bg-warning me-1">
                                            <i class="bi bi-clock me-1"></i>Pending: <?= $balance['pending_days'] ?> days
                                        </span>
                                    <?php endif; ?>
                                    <div class="small text-secondary mt-1">
                                        Total leave taken: <strong><?= $balance['used_days'] ?> days</strong> in <?= $current_year ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$balance['is_unlimited']): ?>
                            <div class="progress progress-sm">
                                <?php 
                                    $progressColor = 'bg-success';
                                    if ($balance['percentage'] < 30) {
                                        $progressColor = 'bg-danger';
                                    } elseif ($balance['percentage'] < 60) {
                                        $progressColor = 'bg-warning';
                                    }
                                ?>
                                <div class="progress-bar <?= $progressColor ?>" 
                                     style="width: <?= $balance['percentage'] ?>%"
                                     title="<?= round($balance['percentage'], 1) ?>% remaining">
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-info" 
                                     style="width: 100%"
                                     title="No limit leave type">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                

            <?php endif; ?>
            
            <div class="small text-secondary mt-3">
                <i class="bi bi-info-circle me-1"></i>
                Balances include approved and pending requests for <?= $current_year ?>. Rejected leaves do not reduce your balance.
            </div>
            <div class="small text-secondary mt-1">
                <i class="bi bi-bookmark-check me-1"></i>
                Policy: Casual Leave – up to <strong>1</strong> per month; Short Leave – up to <strong>2</strong> per month.
            </div>
        </div>


      </div>
    </div>

    <!-- Recent Leave History Section -->
    <div class="ss-card p-3 p-md-4 mt-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-calendar-week me-2 text-primary"></i>
                <h5 class="section-title m-0">Your Recent Leave History</h5>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select id="statusFilter" class="form-select form-select-sm rounded-pill border-light" style="width: auto; background-color: #f8f9fa;">
                    <option value="all" selected>All Status</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select id="monthFilter" class="form-select form-select-sm rounded-pill border-light" style="width: auto; background-color: #f8f9fa;">
                    <option value="all" selected>All Months</option>
                    <option value="1">Jan</option>
                    <option value="2">Feb</option>
                    <option value="3">Mar</option>
                    <option value="4">Apr</option>
                    <option value="5">May</option>
                    <option value="6">Jun</option>
                    <option value="7">Jul</option>
                    <option value="8">Aug</option>
                    <option value="9">Sep</option>
                    <option value="10">Oct</option>
                    <option value="11">Nov</option>
                    <option value="12">Dec</option>
                </select>
                <select id="yearFilter" class="form-select form-select-sm rounded-pill border-light" style="width: auto; background-color: #f8f9fa;">
                    <option value="all">All Years</option>
                    <?php $y=(int)$current_year; for($i=0;$i<5;$i++): $yy=$y-$i; ?>
                        <option value="<?= $yy ?>" <?= $i===0 ? 'selected' : '' ?>><?= $yy ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <div id="leaveHistoryContainer" class="rounded-3 overflow-hidden">
            <div class="text-center py-5">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="mt-2 text-muted small">Loading your leave history</div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top" id="paginationContainer" style="display: none;">
            <div class="small text-muted">
                Showing <span id="showingStart" class="fw-medium text-dark">1</span>-<span id="showingEnd" class="fw-medium text-dark">10</span> of <span id="totalRecords" class="fw-medium text-dark">0</span> leaves
            </div>
            <div class="pagination-controls">
                <button class="btn btn-sm btn-light rounded-pill border-0 shadow-sm px-3" id="prevPageBtn" disabled>
                    <i class="bi bi-chevron-left me-1"></i> Previous
                </button>
                <button class="btn btn-sm btn-primary rounded-pill border-0 shadow-sm px-3 ms-2" id="nextPageBtn" disabled>
                    Next <i class="bi bi-chevron-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-end">
        <a href="site_supervisor_dashboard.php" class="btn btn-light rounded-xl border"><i class="bi bi-speedometer2"></i> Back to Dashboard</a>
    </div>
</div>
</div>

<!-- Leave History Modal -->
<div class="modal fade" id="leaveHistoryModal" tabindex="-1" aria-labelledby="leaveHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaveHistoryModalLabel">
                    <i class="bi bi-calendar-check me-2"></i>
                    <span id="modalLeaveTypeName">Leave History</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2">Loading leave history...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Leave Modal -->
<div class="modal fade" id="editLeaveModal" tabindex="-1" aria-labelledby="editLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLeaveModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Leave
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editLeaveForm" class="row g-3">
                    <input type="hidden" id="editLeaveId" />
                    <div class="col-12">
                        <label class="form-label">Leave Type</label>
                        <select class="form-select" id="editLeaveTypeSelect" required>
                            <?php if (!empty($leave_types)): ?>
                                <?php foreach ($leave_types as $lt): ?>
                                    <option value="<?= (int)$lt['id'] ?>">
                                        <?= htmlspecialchars($lt['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No leave types available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" id="editStartDate" required />
                    </div>
                    <div class="col-6">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" id="editEndDate" required />
                    </div>
                    <div class="col-12 d-flex align-items-center gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="editHalfDayToggle">
                            <label class="form-check-label" for="editHalfDayToggle">Half day</label>
                        </div>
                        <div id="editHalfDaySessionWrapper" style="display:none;">
                            <select class="form-select form-select-sm" id="editHalfDaySession" style="width:auto;">
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                            </select>
                        </div>
                        <span class="badge bg-light text-dark border" id="editDurationBadge">0 days</span>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" id="editReason" rows="3" placeholder="Reason" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEditLeaveBtn">Save Changes</button>
            </div>
        </div>
    </div>
    </div>

<!-- Delete Leave Confirmation Modal -->
<div class="modal fade" id="deleteLeaveModal" tabindex="-1" aria-labelledby="deleteLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteLeaveModalLabel">
                    <i class="bi bi-trash3 me-2 text-danger"></i>Delete Leave
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                    <div>
                        <div class="fw-semibold">Are you sure you want to delete this leave?</div>
                        <div class="text-muted small">This action cannot be undone.</div>
                    </div>
                </div>
                <input type="hidden" id="deleteLeaveId" />
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteLeaveBtn">Delete</button>
            </div>
        </div>
    </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Global function to show leave history modal
function toggleLeaveDetails(leaveTypeId) {
    // Get leave type name from the page
    const leaveTypeName = document.querySelector(`[data-leave-type-id="${leaveTypeId}"]`)?.textContent?.trim() || 'Leave';
    
    // Update modal title
    document.getElementById('modalLeaveTypeName').textContent = `${leaveTypeName} History`;
    
    // Reset modal body to loading state
    document.getElementById('modalBody').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2">Loading leave history...</div>
        </div>
    `;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('leaveHistoryModal'));
    modal.show();
    
    // Fetch leave history data
    fetch(`ajax_handlers/fetch_leave_history_modal_v1.php?leave_type_id=${leaveTypeId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderLeaveHistoryModal(data);
            } else {
                let errorMessage = data.error || 'Unknown error';
                let debugInfo = '';
                
                // Add debug info if available
                if (data.debug) {
                    debugInfo = `<div class="small text-muted mt-2">${data.debug.message || ''}</div>`;
                }
                
                document.getElementById('modalBody').innerHTML = `
                    <div class="text-center py-5 text-danger">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <div class="mt-2">Error loading leave history</div>
                        <div class="small text-muted">${errorMessage}</div>
                        ${debugInfo}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('modalBody').innerHTML = `
                <div class="text-center py-5 text-danger">
                    <i class="bi bi-wifi-off fs-1"></i>
                    <div class="mt-2">Failed to load leave history</div>
                    <div class="small text-muted">Error: ${error.message}</div>
                    <div class="small text-muted mt-2">Please check the server logs for more details</div>
                </div>
            `;
        });
}

// Function to render the leave history modal content
function renderLeaveHistoryModal(data) {
    const { leave_type, summary, requests, year, comp_off_earned_dates, comp_off_earned_count } = data;
    
    let modalContent = `
        <!-- Summary Section -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body p-3">
                        <h6 class="card-title text-primary mb-3">
                            <i class="bi bi-bar-chart me-2"></i>Leave Summary ${year}
                        </h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="small text-muted">Available</div>
                                <div class="fw-bold">${summary.is_unlimited ? 'Unlimited' : summary.max_days + ' days'}</div>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted">Remaining</div>
                                <div class="fw-bold text-success">${summary.remaining} ${summary.is_unlimited ? '' : 'days'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body p-3">
                        <h6 class="card-title text-secondary mb-3">
                            <i class="bi bi-calendar-event me-2"></i>Usage Breakdown
                        </h6>
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-success">Approved: ${summary.total_approved}</span>
                            <span class="badge bg-warning">Pending: ${summary.total_pending}</span>
                            <span class="badge bg-danger">Rejected: ${summary.total_rejected}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Comp-Off earned dates section
    if (comp_off_earned_dates && Array.isArray(comp_off_earned_dates)) {
        modalContent += `
            <div class="card border-0 bg-light mt-2 mb-3">
                <div class="card-body p-3">
                    <h6 class="card-title mb-2"><i class="bi bi-calendar2-week me-2"></i>Weekly-Off Days Worked (${comp_off_earned_count || comp_off_earned_dates.length})</h6>
                    ${comp_off_earned_dates.length ? `
                        <div class="small text-muted mb-2">You worked on these weekly-off dates in ${year}:</div>
                        <div class="d-flex flex-wrap gap-2">
                            ${comp_off_earned_dates.map(d => `<span class="badge text-bg-light border">${new Date(d).toLocaleDateString('en-US',{weekday:'short',day:'numeric',month:'short',year:'numeric'})}</span>`).join('')}
                        </div>
                    ` : `<div class="small text-muted">No weekly-off work days found.</div>`}
                </div>
            </div>
        `;
    }

    if (requests.length === 0) {
        modalContent += `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-1"></i>
                <div class="mt-2">No leave requests found</div>
                <div class="small">You haven't applied for ${leave_type.name} in ${year}</div>
            </div>
        `;
    } else {
        modalContent += `
            <h6 class="mb-3">
                <i class="bi bi-list-ul me-2"></i>Leave Request History
            </h6>
            <div class="list-group list-group-flush">
        `;
        
        requests.forEach(request => {
            const statusColor = {
                'approved': 'success',
                'pending': 'warning',
                'rejected': 'danger'
            }[request.status] || 'secondary';
            
            const statusIcon = {
                'approved': 'check-circle',
                'pending': 'clock',
                'rejected': 'x-circle'
            }[request.status] || 'circle';
            
            const formatDate = (dateStr) => {
                return new Date(dateStr).toLocaleDateString('en-US', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
            };
            
            modalContent += `
                <div class="list-group-item border-0 px-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-${statusColor} me-2">
                                    <i class="bi bi-${statusIcon} me-1"></i>${request.status.charAt(0).toUpperCase() + request.status.slice(1)}
                                </span>
                                <strong>${formatDate(request.start_date)}</strong>
                                ${request.start_date !== request.end_date ? ` - <strong>${formatDate(request.end_date)}</strong>` : ''}
                                <span class="text-muted ms-2">(${request.duration} day${request.duration !== 1 ? 's' : ''})</span>
                            </div>
                            ${request.reason ? `
                                <div class="small text-muted mb-1">
                                    <i class="bi bi-chat-quote me-1"></i>
                                    <strong>Reason:</strong> ${request.reason}
                                </div>
                            ` : ''}
                            <div class="small text-secondary">
                                Applied: ${formatDate(request.applied_date)}
                                ${request.approved_date ? ` | ${request.status.charAt(0).toUpperCase() + request.status.slice(1)}: ${formatDate(request.approved_date)}` : ''}
                                ${request.approver_name ? ` by ${request.approver_name}` : ''}
                            </div>
                            ${request.admin_comments ? `
                                <div class="small text-info mt-1">
                                    <i class="bi bi-chat-left-text me-1"></i>
                                    <strong>Admin Notes:</strong> ${request.admin_comments}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        modalContent += `</div>`;
    }
    
    document.getElementById('modalBody').innerHTML = modalContent;
}

// Function to fetch and display all leave history
function loadAllLeaveHistory() {
    const historyContainer = document.getElementById('leaveHistoryContainer');
    
    // Show loading state
    historyContainer.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2">Loading your leave history...</div>
        </div>
    `;
    
    // Fetch all leave types first to get comprehensive history
    fetch('ajax_handlers/fetch_leave_history_modal_v1.php?leave_type_id=all')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderLeaveHistoryTable(data.requests);
            } else {
                historyContainer.innerHTML = `
                    <div class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle fs-3"></i>
                        <div class="mt-2">Error loading leave history</div>
                        <div class="small text-muted">${data.error || 'Unknown error'}</div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            historyContainer.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="bi bi-wifi-off fs-3"></i>
                    <div class="mt-2">Failed to load leave history</div>
                    <div class="small text-muted">Error: ${error.message}</div>
                </div>
            `;
        });
}

// Global variable to store all leave requests
let allLeaveRequests = [];
let currentPage = 1;
let itemsPerPage = 10;
let currentFilter = 'all';
let currentMonth = 'all'; // 1-12 or 'all'
let currentYear = 'all';

// Function to render the leave history table with pagination and filtering
function renderLeaveHistoryTable(requests) {
    const historyContainer = document.getElementById('leaveHistoryContainer');
    const paginationContainer = document.getElementById('paginationContainer');
    
    // Store all requests globally
    allLeaveRequests = requests || [];
    
    if (!requests || requests.length === 0) {
        historyContainer.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="bi bi-calendar-x fs-3"></i>
                <div class="mt-2">No leave history found</div>
                <div class="small">You haven't applied for any leaves yet</div>
            </div>
        `;
        paginationContainer.style.display = 'none';
        return;
    }
    
    // Apply status and month filters
    let filteredRequests = requests.filter(request => {
        const statusOk = currentFilter === 'all' || request.status.toLowerCase() === currentFilter.toLowerCase();
        if (!statusOk) return false;
        // Year check
        if (currentYear !== 'all') {
            const y = new Date(request.start_date).getFullYear();
            if (String(y) !== String(currentYear)) return false;
        }
        // Month check
        if (currentMonth !== 'all') {
            const m = new Date(request.start_date).getMonth() + 1; // 1-12
            if (String(m) !== String(currentMonth)) return false;
        }
        return true;
    });
    
    // Reset to page 1 if filtered results are less than current page
    const totalPages = Math.ceil(filteredRequests.length / itemsPerPage);
    if (currentPage > totalPages) {
        currentPage = 1;
    }
    
    // Calculate pagination
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, filteredRequests.length);
    const paginatedRequests = filteredRequests.slice(startIndex, endIndex);
    
    // Update pagination display
    document.getElementById('showingStart').textContent = filteredRequests.length > 0 ? startIndex + 1 : 0;
    document.getElementById('showingEnd').textContent = endIndex;
    document.getElementById('totalRecords').textContent = filteredRequests.length;
    document.getElementById('prevPageBtn').disabled = currentPage === 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
    
    // Show/hide pagination based on results
    if (filteredRequests.length > itemsPerPage) {
        paginationContainer.style.display = 'flex';
    } else {
        paginationContainer.style.display = filteredRequests.length > 0 ? 'flex' : 'none';
    }
    
    // Format date helper function
    const formatDate = (dateStr) => {
        return new Date(dateStr).toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };
    
    // Add custom styles for the table
    const tableStyles = `
        <style>
            .leave-table {
                border-collapse: separate;
                border-spacing: 0;
                width: 100%;
                font-size: 0.95rem;
            }
            .leave-table th {
                font-weight: 600;
                color: #4b5563;
                border-bottom: 1px solid #e5e7eb;
                padding: 12px 16px;
                text-align: left;
                font-size: 0.85rem;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }
            .leave-table td {
                padding: 14px 16px;
                border-bottom: 1px solid #f0f0f0;
                color: #1f2937;
            }
            .leave-table tbody tr {
                transition: all 0.2s;
            }
            .leave-table tbody tr:hover {
                background-color: #f9fafb;
            }
            .leave-table tbody tr:nth-child(odd) {
                background-color: #fafbfc;
            }
            .leave-table tbody tr:nth-child(odd):hover {
                background-color: #f5f7fa;
            }
            .leave-status {
                display: inline-flex;
                align-items: center;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 0.8rem;
                font-weight: 500;
                white-space: nowrap;
            }
            .leave-status i {
                margin-right: 4px;
                font-size: 0.75rem;
            }
            .leave-status.pending {
                background-color: #fff8e6;
                color: #92400e;
                border: 1px solid rgba(234, 179, 8, 0.2);
            }
            .leave-status.approved {
                background-color: #ecfdf5;
                color: #065f46;
                border: 1px solid rgba(16, 185, 129, 0.2);
            }
            .leave-status.rejected {
                background-color: #fef2f2;
                color: #991b1b;
                border: 1px solid rgba(239, 68, 68, 0.2);
            }
            .leave-action-btn {
                width: 32px;
                height: 32px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                background-color: #f3f4f6;
                color: #4b5563;
                border: none;
                transition: all 0.2s;
                margin: 0 2px;
            }
            .leave-action-btn:hover:not(:disabled) {
                background-color: #e5e7eb;
                color: #1f2937;
            }
            .leave-action-btn:disabled {
                opacity: 0.4;
                cursor: not-allowed;
            }
            .leave-action-btn.view-btn:hover {
                background-color: #e1effe;
                color: #1e40af;
            }
            .leave-action-btn.edit-btn:hover:not(:disabled) {
                background-color: #e0f2fe;
                color: #0369a1;
            }
            .leave-action-btn.delete-btn:hover:not(:disabled) {
                background-color: #fee2e2;
                color: #b91c1c;
            }
            .leave-type {
                font-weight: 500;
            }
            .leave-date {
                color: #4b5563;
            }
            .leave-duration {
                font-weight: 500;
            }
            .empty-state {
                padding: 40px 0;
                text-align: center;
                color: #6b7280;
                background: #fafbfc;
                border-radius: 8px;
            }
            .empty-state i {
                font-size: 2rem;
                margin-bottom: 12px;
                opacity: 0.5;
            }
        </style>
    `;
    
    // Build table HTML
    let tableHTML = tableStyles + `
        <div class="table-responsive">
            <table class="leave-table">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (paginatedRequests.length === 0) {
        tableHTML += `
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <i class="bi bi-filter-circle d-block"></i>
                        <div>No leaves found matching the selected filter</div>
                    </div>
                </td>
            </tr>
        `;
    } else {
        paginatedRequests.forEach(request => {
            const statusClass = {
                'approved': 'approved',
                'pending': 'pending',
                'rejected': 'rejected'
            }[request.status.toLowerCase()] || 'secondary';
            
            const statusIcon = {
                'approved': 'check-circle-fill',
                'pending': 'clock-fill',
                'rejected': 'x-circle-fill'
            }[request.status.toLowerCase()] || 'circle-fill';
            
            tableHTML += `
                <tr>
                    <td><span class="leave-type">${request.leave_type_name || 'Leave'}</span></td>
                    <td class="leave-date">
                        ${formatDate(request.start_date)}
                        ${request.start_date !== request.end_date ? `<span class="text-muted mx-1">to</span>${formatDate(request.end_date)}` : ''}
                    </td>
                    <td><span class="leave-duration">${request.duration} day${request.duration !== 1 ? 's' : ''}</span></td>
                    <td>
                        <span class="leave-status ${statusClass}">
                            <i class="bi bi-${statusIcon}"></i>${request.status}
                        </span>
                    </td>
                    <td class="leave-date">${formatDate(request.applied_date)}</td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center">
                            <button class="leave-action-btn view-btn" 
                                    onclick="viewLeaveDetails(${request.id})" 
                                    title="View details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="leave-action-btn edit-btn" 
                                    onclick="editLeave(${request.id})" 
                                    title="Edit leave"
                                    ${['approved', 'rejected'].includes(request.status.toLowerCase()) ? 'disabled' : ''}>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="leave-action-btn delete-btn" 
                                    onclick="confirmDeleteLeave(${request.id})" 
                                    title="Cancel leave"
                                    ${['approved', 'rejected'].includes(request.status.toLowerCase()) ? 'disabled' : ''}>
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    tableHTML += `
                </tbody>
            </table>
        </div>
    `;
    
    historyContainer.innerHTML = tableHTML;
}

// Function to handle pagination and filtering
function handlePagination(direction) {
    if (direction === 'prev' && currentPage > 1) {
        currentPage--;
    } else if (direction === 'next') {
        const totalPages = Math.ceil(allLeaveRequests.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
        }
    }
    renderLeaveHistoryTable(allLeaveRequests);
}

// Function to handle status filter changes
function handleStatusFilter(status) {
    currentFilter = status;
    currentPage = 1; // Reset to first page when filter changes
    renderLeaveHistoryTable(allLeaveRequests);
}

// Function to view details of a specific leave
function viewLeaveDetails(leaveId) {
    // Find the leave in the allLeaveRequests array (normalize id types)
    const targetId = String(leaveId);
    const leave = allLeaveRequests.find(req => String(req.id) === targetId);
    
    if (!leave) {
        console.error('Leave not found with ID:', leaveId);
        return;
    }
    
    // Update modal title with leave type and ID
    document.getElementById('modalLeaveTypeName').textContent = `${leave.leave_type_name} Leave Details`;
    
    // Format date helper function
    const formatDate = (dateStr) => {
        return new Date(dateStr).toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };
    
    // Get status styling
    const statusClass = {
        'approved': 'success',
        'pending': 'warning',
        'rejected': 'danger'
    }[leave.status.toLowerCase()] || 'secondary';
    
    const statusIcon = {
        'approved': 'check-circle-fill',
        'pending': 'clock-fill',
        'rejected': 'x-circle-fill'
    }[leave.status.toLowerCase()] || 'circle-fill';
    
    // Build minimal, clean modal content
    let modalContent = `
        <style>
            .ld-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
            @media (max-width: 576px) { .ld-grid { grid-template-columns: 1fr; } }
            .ld-label { color: #6b7280; font-size: 0.85rem; }
            .ld-value { color: #111827; font-weight: 500; }
            .ld-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: .8rem; border: 1px solid #e5e7eb; background: #f9fafb; }
            .ld-badge.success { background: #ecfdf5; border-color: rgba(16,185,129,.2); color: #065f46; }
            .ld-badge.warning { background: #fff8e6; border-color: rgba(234,179,8,.2); color: #92400e; }
            .ld-badge.danger  { background: #fef2f2; border-color: rgba(239,68,68,.2); color: #991b1b; }
            .ld-section { padding: 12px 0; border-top: 1px solid #f1f5f9; }
            .ld-section:first-of-type { border-top: none; }
            .ld-note { background: #f8fafc; border: 1px solid #eef2f7; border-radius: 10px; padding: 12px; }
        </style>
        <div class="ld-grid">
            <div>
                <div class="ld-label">Leave Type</div>
                <div class="ld-value">${leave.leave_type_name}</div>
            </div>
            <div>
                <div class="ld-label">Status</div>
                <div class="ld-value">
                    <span class="ld-badge ${statusClass}"><i class="bi bi-${statusIcon}"></i>${leave.status}</span>
                </div>
            </div>
            <div>
                <div class="ld-label">From</div>
                <div class="ld-value">${formatDate(leave.start_date)}</div>
            </div>
            <div>
                <div class="ld-label">To</div>
                <div class="ld-value">${formatDate(leave.end_date)}</div>
            </div>
            <div>
                <div class="ld-label">Duration</div>
                <div class="ld-value">${leave.duration} day${leave.duration !== 1 ? 's' : ''}</div>
            </div>
            <div>
                <div class="ld-label">Applied On</div>
                <div class="ld-value">${formatDate(leave.applied_date)}</div>
            </div>
        </div>
    `;
    
    // Add reason section if available
    if (leave.reason) {
        modalContent += `
            <div class="ld-section">
                <div class="ld-label mb-1">Reason</div>
                <div class="ld-note">${leave.reason}</div>
            </div>
        `;
    }
    
    // Add admin comments section if available
    if (leave.admin_comments) {
        modalContent += `
            <div class="ld-section">
                <div class="ld-label mb-1">Admin Comments</div>
                <div class="ld-note fst-italic">${leave.admin_comments}</div>
            </div>
        `;
    }
    
    // Add approver information if available
    if (leave.approver_name && leave.status.toLowerCase() !== 'pending') {
        modalContent += `
            <div class="ld-section">
                <div class="ld-label mb-1">Approval</div>
                <div class="ld-grid" style="grid-template-columns:1fr 1fr">
                    <div>
                        <div class="ld-label">Reviewed By</div>
                        <div class="ld-value">${leave.approver_name}</div>
                    </div>
                    <div>
                        <div class="ld-label">Reviewed On</div>
                        <div class="ld-value">${leave.approved_date ? formatDate(leave.approved_date) : 'N/A'}</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Update modal body
    document.getElementById('modalBody').innerHTML = modalContent;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('leaveHistoryModal'));
    modal.show();
}

// Function to handle leave editing
function editLeave(leaveId) {
    // Find the leave in the allLeaveRequests array (normalize id types)
    const targetId = String(leaveId);
    const leave = allLeaveRequests.find(req => String(req.id) === targetId);
    
    if (!leave) {
        console.error('Leave not found with ID:', leaveId);
        return;
    }
    
    // Don't allow editing approved or rejected leaves
    if (['approved', 'rejected'].includes(leave.status.toLowerCase())) {
        alert(`${leave.status} leaves cannot be edited.`);
        return;
    }
    
    // Populate modal fields
    document.getElementById('editLeaveId').value = leave.id;
    // Set leave type in dropdown (match by id)
    const typeSelect = document.getElementById('editLeaveTypeSelect');
    if (typeSelect) {
        typeSelect.value = String(leave.leave_type);
    }
    document.getElementById('editStartDate').value = leave.start_date;
    document.getElementById('editEndDate').value = leave.end_date;
    document.getElementById('editReason').value = leave.reason || '';

    // Half-day detection (assumes 0.5 duration for single-day)
    const isSameDay = leave.start_date === leave.end_date;
    const halfDayToggle = document.getElementById('editHalfDayToggle');
    const halfDayWrapper = document.getElementById('editHalfDaySessionWrapper');
    halfDayToggle.checked = isSameDay && Number(leave.duration) === 0.5;
    halfDayWrapper.style.display = (halfDayToggle.checked && isSameDay) ? '' : 'none';

    // Initialize duration badge
    recalcEditDuration();

    // Show modal
    const editModal = new bootstrap.Modal(document.getElementById('editLeaveModal'));
    editModal.show();
}

// Function to handle leave deletion/cancellation
function confirmDeleteLeave(leaveId) {
    // Find the leave in the allLeaveRequests array (normalize id types)
    const targetId = String(leaveId);
    const leave = allLeaveRequests.find(req => String(req.id) === targetId);
    
    if (!leave) {
        console.error('Leave not found with ID:', leaveId);
        return;
    }
    
    // Don't allow deleting approved or rejected leaves
    if (['approved', 'rejected'].includes(leave.status.toLowerCase())) {
        alert(`${leave.status} leaves cannot be cancelled.`);
        return;
    }
    
    // Populate and open confirmation modal
    document.getElementById('deleteLeaveId').value = leaveId;
    const delModal = new bootstrap.Modal(document.getElementById('deleteLeaveModal'));
    delModal.show();
}

document.addEventListener('DOMContentLoaded', () => {
    // Edit modal controls
    const editStart = document.getElementById('editStartDate');
    const editEnd = document.getElementById('editEndDate');
    const editHalf = document.getElementById('editHalfDayToggle');
    const editHalfWrapper = document.getElementById('editHalfDaySessionWrapper');
    const editDurationBadge = document.getElementById('editDurationBadge');

    window.recalcEditDuration = function recalcEditDuration() {
        const s = editStart.value ? new Date(editStart.value) : null;
        const e = editEnd.value ? new Date(editEnd.value) : null;
        if (!s || !e) { editDurationBadge.textContent = '0 days'; return; }
        s.setHours(12,0,0,0); e.setHours(12,0,0,0);
        const diff = e - s; if (diff < 0) { editDurationBadge.textContent = '0 days'; return; }
        let d = Math.floor(diff / (1000*60*60*24)) + 1;
        if (editHalf.checked && d === 1) editDurationBadge.textContent = '0.5 day';
        else editDurationBadge.textContent = d === 1 ? '1 day' : `${d} days`;
    }

    editStart?.addEventListener('change', () => {
        if (editStart.value) editEnd.setAttribute('min', editStart.value);
        recalcEditDuration();
        const same = editStart.value && editEnd.value && editStart.value === editEnd.value;
        editHalfWrapper.style.display = (editHalf.checked && same) ? '' : 'none';
    });
    editEnd?.addEventListener('change', () => {
        recalcEditDuration();
        const same = editStart.value && editEnd.value && editStart.value === editEnd.value;
        editHalfWrapper.style.display = (editHalf.checked && same) ? '' : 'none';
    });
    editHalf?.addEventListener('change', () => {
        const same = editStart.value && editEnd.value && editStart.value === editEnd.value;
        editHalfWrapper.style.display = (editHalf.checked && same) ? '' : 'none';
        recalcEditDuration();
    });

    // Save edit - call API
    document.getElementById('saveEditLeaveBtn')?.addEventListener('click', async () => {
        const id = document.getElementById('editLeaveId').value;
        const payload = {
            id: Number(id),
            leave_type: Number(document.getElementById('editLeaveTypeSelect').value),
            start_date: editStart.value,
            end_date: editEnd.value,
            reason: document.getElementById('editReason').value.trim(),
            half_day: editHalf.checked,
            half_day_type: (editHalf.checked && editStart.value === editEnd.value) ? (
                document.getElementById('editHalfDaySession').value === 'afternoon' ? 'second_half' : 'first_half'
            ) : null
        };

        try {
            const res = await fetch('api/update_leave_request_20250810.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || 'Failed to update');
            bootstrap.Modal.getInstance(document.getElementById('editLeaveModal'))?.hide();
            loadAllLeaveHistory();
        } catch (e) {
            alert('Failed to save changes: ' + (e?.message || 'Unexpected error'));
        }
    });

    // Confirm delete - call API
    document.getElementById('confirmDeleteLeaveBtn')?.addEventListener('click', async () => {
        const id = Number(document.getElementById('deleteLeaveId').value);
        try {
            const res = await fetch('api/delete_leave_request_20250810.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || 'Failed to delete');
            bootstrap.Modal.getInstance(document.getElementById('deleteLeaveModal'))?.hide();
            loadAllLeaveHistory();
        } catch (e) {
            alert('Failed to delete leave: ' + (e?.message || 'Unexpected error'));
        }
    });
    // Load all leave history when page loads
    loadAllLeaveHistory();
    
    // Set up pagination event handlers
    document.getElementById('prevPageBtn').addEventListener('click', () => handlePagination('prev'));
    document.getElementById('nextPageBtn').addEventListener('click', () => handlePagination('next'));
    
    // Set up filter event handler
    document.getElementById('statusFilter').addEventListener('change', (e) => {
        handleStatusFilter(e.target.value);
    });
    document.getElementById('monthFilter').addEventListener('change', (e) => {
        currentMonth = e.target.value;
        currentPage = 1;
        renderLeaveHistoryTable(allLeaveRequests);
    });
    document.getElementById('yearFilter').addEventListener('change', (e) => {
        currentYear = e.target.value;
        currentPage = 1;
        renderLeaveHistoryTable(allLeaveRequests);
    });
    
    // Panel toggle functionality
    const leftPanel = document.querySelector('.left-panel');
    const mainContent = document.querySelector('.main-content');
    const toggleIcon = document.getElementById('toggleIcon');

    function togglePanel() {
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            leftPanel.classList.toggle('expanded');
        } else {
            leftPanel.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleIcon.classList.toggle('fa-chevron-right');
            toggleIcon.classList.toggle('fa-chevron-left');
        }
    }

    // Make togglePanel available globally
    window.togglePanel = togglePanel;

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                leftPanel.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        }, 250);
    });

    // Form handling
    const form = document.getElementById('apply-leave-form');
    const dateFrom = form.querySelector('input[name="date_from"]');
    const dateTo = form.querySelector('input[name="date_to"]');
    const durationBadge = document.getElementById('durationBadge');
    const quickChips = document.querySelectorAll('.chip[data-quick]');
    
    // Function to check if multiple days are selected
    function isMultipleDaysSelected() {
        const fromDate = dateFrom.value;
        const toDate = dateTo.value;
        
        if (!fromDate || !toDate) {
            return false;
        }
        
        const start = new Date(fromDate);
        const end = new Date(toDate);
        
        // Set time to noon to avoid DST issues
        start.setHours(12, 0, 0, 0);
        end.setHours(12, 0, 0, 0);
        
        // Calculate difference in days
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        return diffDays > 0;
    }
    
    // Function to toggle the visibility of default leave type field
    function toggleDefaultLeaveTypeVisibility() {
        const defaultLeaveTypeContainer = document.getElementById('defaultLeaveTypeContainer');
        const dateListContainer = document.getElementById('dateListContainer');
        const leaveTypeHint = document.getElementById('leaveTypeHint');
        
        if (isMultipleDaysSelected()) {
            // Multiple days selected - show date list, hide default leave type
            defaultLeaveTypeContainer.classList.add('d-none');
            dateListContainer.style.display = '';
            
            // Generate and show date list if not already done
            if (generatedDates.length === 0 && dateFrom.value && dateTo.value) {
                // Just show the Generate Date List button instead of auto-generating
                document.getElementById('generateDateList').click();
            }
        } else {
            // Single day selected - show default leave type, hide date list
            defaultLeaveTypeContainer.classList.remove('d-none');
            dateListContainer.style.display = 'none';
            
            // Update hint text
            if (leaveTypeHint) {
                leaveTypeHint.textContent = "Select the leave type for your single day request.";
            }
        }
    }


    function formatDuration(value) {
        return value === 1 ? '1 day' : `${value} days`;
    }

    function calculateDuration() {
        const start = dateFrom.value ? new Date(dateFrom.value) : null;
        const end = dateTo.value ? new Date(dateTo.value) : null;
        if (!start || !end) {
            durationBadge.textContent = '0 days';
            return;
        }
        // Normalize time to noon to avoid DST issues
        start.setHours(12,0,0,0);
        end.setHours(12,0,0,0);
        const diffMs = end - start;
        if (diffMs < 0) {
            durationBadge.textContent = '0 days';
            return;
        }
        let days = Math.floor(diffMs / (1000*60*60*24)) + 1; // inclusive
        durationBadge.textContent = formatDuration(days);
    }

    // Date constraints
    const todayStr = new Date().toISOString().slice(0,10);
    dateFrom.setAttribute('min', todayStr);
    dateTo.setAttribute('min', todayStr);
    
    // Initialize visibility of default leave type field
    toggleDefaultLeaveTypeVisibility();

    dateFrom.addEventListener('change', () => {
        if (dateFrom.value) {
            dateTo.setAttribute('min', dateFrom.value);
            if (!dateTo.value) dateTo.value = dateFrom.value;
        }
        calculateDuration();
        toggleDefaultLeaveTypeVisibility(); // Toggle visibility based on date range
    });
    dateTo.addEventListener('change', () => {
        calculateDuration();
        validateMaxDays(); // Validate when dates change
        toggleDefaultLeaveTypeVisibility(); // Toggle visibility based on date range
    });


    // Quick selects
    quickChips.forEach(chip => chip.addEventListener('click', () => {
        const type = chip.getAttribute('data-quick');
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        function toStr(d){ return d.toISOString().slice(0,10); }
        if (type === 'today') {
            dateFrom.value = toStr(today);
            dateTo.value = toStr(today);
        } else if (type === 'tomorrow') {
            const t = new Date(today); t.setDate(t.getDate()+1);
            dateFrom.value = toStr(t);
            dateTo.value = toStr(t);
        } else if (type === 'same') {
            if (dateFrom.value) dateTo.value = dateFrom.value;
        } else if (type === 'two') {
            if (dateFrom.value) {
                const start = new Date(dateFrom.value);
                const end = new Date(start); end.setDate(end.getDate()+1);
                dateTo.value = toStr(end);
            }
        }
        calculateDuration();
        validateMaxDays(); // Validate when quick selections change dates
    }));

    form.querySelector('select[name="leave_type"]').addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        const maxDays = selectedOption?.getAttribute('data-max-days');
        const selectedId = selectedOption?.value;
        
        // Show remaining Comp-Off balance when Compensate selected
        try {
            const balanceMap = JSON.parse(document.getElementById('leaveBalanceMap').textContent || '{}');
            const entry = balanceMap[selectedId];
            const isComp = (entry && entry.name && entry.name.toLowerCase().includes('comp'));
            const isShort = (entry && entry.name && entry.name.toLowerCase().includes('short'));
            const hint = document.getElementById('compOffHint');
            if (isComp && hint) {
                const remaining = (entry.remaining !== undefined) ? entry.remaining : 0;
                hint.style.display = '';
                hint.textContent = `Remaining Compensate Leave: ${remaining} day${remaining == 1 ? '' : 's'}`;
                // Show comp-off date selector
                document.getElementById('compOffDateWrapper')?.style && (document.getElementById('compOffDateWrapper').style.display = '');
                // Hide short leave time as we're compensating
                document.getElementById('shortLeaveTimeWrapper')?.classList.add('d-none');
                document.getElementById('shortLeaveTimeWrapper').style.display = 'none';
            } else if (hint && !isShort) {
                hint.style.display = 'none';
                hint.textContent = '';
                // Hide comp-off date selector
                document.getElementById('compOffDateWrapper')?.style && (document.getElementById('compOffDateWrapper').style.display = 'none');
            }

            // Toggle short leave time UI
            const shortWrap = document.getElementById('shortLeaveTypeWrapper');
            if (isShort && shortWrap) {
                shortWrap.classList.remove('d-none');
                shortWrap.style.display = '';
                // Auto-calc window text
                updateShortLeaveAutoTimes();
            } else if (shortWrap) {
                shortWrap.classList.add('d-none');
                shortWrap.style.display = 'none';
            }
        } catch (_) { /* ignore */ }
        
        // Show max days info if available
        if (maxDays && maxDays > 0) {
            const maxDaysInfo = document.createElement('div');
            maxDaysInfo.className = 'text-info small mt-1';
            maxDaysInfo.textContent = `Maximum allowed: ${maxDays} days`;
            
            // Remove any existing max days info
            const existingInfo = e.target.parentNode.querySelector('.text-info');
            if (existingInfo) {
                existingInfo.remove();
            }
            
            // Add new max days info
            e.target.parentNode.appendChild(maxDaysInfo);
        }
        
        // Validate current duration against max days
        validateMaxDays();
    });

    // Validate short leave time window (1h30m from shift start, or 1h30m before shift end)
    // Morning/Evening short leave auto time window
    const shortSession = document.getElementById('shortLeaveSession');
    function toMinutes(hhmm){ const [h,m]=hhmm.split(':').map(x=>parseInt(x,10)); return h*60+m; }
    function toHHMM(min){ const h=Math.floor(min/60).toString().padStart(2,'0'); const m=(min%60).toString().padStart(2,'0'); return `${h}:${m}`; }
    function fmt(hhmm){
        try { return new Date(`2000-01-01T${hhmm}:00`).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'}); }
        catch { return hhmm; }
    }
    function updateShortLeaveAutoTimes(){
        const wrap = document.getElementById('shortLeaveTypeWrapper');
        if (!wrap || wrap.classList.contains('d-none') || wrap.style.display==='none') return;
        const startStr = '<?= $user_shift['start_time'] ? substr($user_shift['start_time'],0,5) : '' ?>';
        const endStr   = '<?= $user_shift['end_time'] ? substr($user_shift['end_time'],0,5) : '' ?>';
        if (!startStr || !endStr) return;
        const start = toMinutes(startStr);
        const end = toMinutes(endStr);
        const ninety = 90;
        let from = start, to = start+ninety;
        if (shortSession?.value === 'evening') {
            from = end-ninety; to = end;
        }
        const autoText = document.getElementById('shortLeaveAutoText');
        if (autoText) autoText.innerHTML = `Short Leave window: <strong>${fmt(toHHMM(from))} to ${fmt(toHHMM(to))}</strong> (1h 30m)`;
        // Store into hidden payload (we'll reuse time_from/time_to fields in backend insert)
        // Overwrite payload time_from/time_to at submit
        window.__shortLeaveAutoFrom = toHHMM(from);
        window.__shortLeaveAutoTo = toHHMM(to);
    }
    shortSession?.addEventListener('change', updateShortLeaveAutoTimes);
    
    // Date list generation and management
    const generateDateListBtn = document.getElementById('generateDateList');
    const dateListContainer = document.getElementById('dateListContainer');
    const dateListBody = document.getElementById('dateListBody');
    const checkAllDates = document.getElementById('checkAllDates');
    const selectAllDatesBtn = document.getElementById('selectAllDates');
    const clearAllDatesBtn = document.getElementById('clearAllDates');
    const defaultLeaveType = document.getElementById('defaultLeaveType');
    
    // Store generated dates and their settings
    let generatedDates = [];
    
    // Generate list of dates between from and to
    function generateDateRange(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const dates = [];
        
        // Set time to noon to avoid DST issues
        start.setHours(12, 0, 0, 0);
        end.setHours(12, 0, 0, 0);
        
        // Generate all dates in the range
        let currentDate = new Date(start);
        while (currentDate <= end) {
            dates.push(new Date(currentDate));
            currentDate.setDate(currentDate.getDate() + 1);
        }
        
        return dates;
    }
    
    // Format date as YYYY-MM-DD
    function formatDateYMD(date) {
        return date.toISOString().slice(0, 10);
    }
    
    // Format date as readable string (e.g., "Mon, 15 Apr 2025")
    function formatDateReadable(date) {
        return date.toLocaleDateString('en-US', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }
    
    // Get day of week
    function getDayOfWeek(date) {
        return date.toLocaleDateString('en-US', { weekday: 'long' });
    }
    
    // Get leave balances from the serialized data
    function getLeaveBalances() {
        try {
            return JSON.parse(document.getElementById('leaveBalanceMap').textContent || '{}');
        } catch (err) {
            console.error('Error parsing leave balances:', err);
            return {};
        }
    }
    
    // Find compensate leave type ID and remaining balance
    function findCompensateLeaveInfo() {
        const balances = getLeaveBalances();
        for (const id in balances) {
            const leave = balances[id];
            if (leave && leave.name && leave.name.toLowerCase().includes('comp')) {
                return {
                    id: id,
                    name: leave.name,
                    remaining: leave.remaining !== undefined ? Number(leave.remaining) : 0
                };
            }
        }
        return null;
    }
    
    // Find casual leave type ID and remaining balance
    function findCasualLeaveInfo() {
        const balances = getLeaveBalances();
        for (const id in balances) {
            const leave = balances[id];
            if (leave && leave.name && leave.name.toLowerCase().includes('casual')) {
                return {
                    id: id,
                    name: leave.name,
                    remaining: leave.remaining !== undefined ? Number(leave.remaining) : 0
                };
            }
        }
        return null;
    }
    
    // Count casual leaves used in a specific month
    function countCasualLeavesInMonth(year, month) {
        return generatedDates.filter(dateInfo => {
            const date = new Date(dateInfo.date);
            return date.getFullYear() === year && 
                   date.getMonth() === month && 
                   dateInfo.checked && 
                   dateInfo.leaveTypeId === findCasualLeaveInfo()?.id;
        }).length;
    }
    
    // Generate leave type options for a select element
    function generateLeaveTypeOptions(selectedId) {
        const balances = getLeaveBalances();
        let options = '';
        
        for (const id in balances) {
            const leave = balances[id];
            const selected = id === selectedId ? 'selected' : '';
            const remaining = leave.is_unlimited ? 'No limit' : `${leave.remaining}/${leave.max_days}`;
            options += `<option value="${id}" ${selected}>${leave.name} (${remaining})</option>`;
        }
        
        return options;
    }
    
    // Check if a leave type is a short leave
    function isShortLeave(leaveTypeId) {
        const balances = getLeaveBalances();
        const leave = balances[leaveTypeId];
        return leave && leave.name && leave.name.toLowerCase().includes('short');
    }
    
    // Check if a leave type is a compensate leave
    function isCompensateLeave(leaveTypeId) {
        const balances = getLeaveBalances();
        const leave = balances[leaveTypeId];
        return leave && leave.name && leave.name.toLowerCase().includes('comp');
    }
    
    // Generate day type options based on leave type
    function getDayTypeOptions(leaveTypeId, selectedDayType) {
        // If short leave, show morning/evening short leave options with shift times
        if (isShortLeave(leaveTypeId)) {
            // Get user shift times
            const shiftStartTime = '<?= $user_shift['start_time'] ? substr($user_shift['start_time'],0,5) : "09:00" ?>';
            const shiftEndTime = '<?= $user_shift['end_time'] ? substr($user_shift['end_time'],0,5) : "18:00" ?>';
            
            // Calculate short leave times
            const morningShortLeaveEnd = calculateTimeOffset(shiftStartTime, 90); // 1h30m from shift start
            const eveningShortLeaveStart = calculateTimeOffset(shiftEndTime, -90); // 1h30m before shift end
            
            return `
                <option value="morning" ${selectedDayType === 'morning' ? 'selected' : ''}>Morning Short Leave (${formatTime(shiftStartTime)} - ${formatTime(morningShortLeaveEnd)})</option>
                <option value="evening" ${selectedDayType === 'evening' ? 'selected' : ''}>Evening Short Leave (${formatTime(eveningShortLeaveStart)} - ${formatTime(shiftEndTime)})</option>
            `;
        } 
        // Otherwise show regular full/half day options
        else {
            return `
                <option value="full" ${selectedDayType === 'full' ? 'selected' : ''}>Full Day</option>
                <option value="morning" ${selectedDayType === 'morning' ? 'selected' : ''}>Morning Half-Day</option>
                <option value="evening" ${selectedDayType === 'evening' ? 'selected' : ''}>Evening Half-Day</option>
            `;
        }
    }
    
    // Calculate time offset in minutes (positive or negative)
    function calculateTimeOffset(timeStr, offsetMinutes) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        const totalMinutes = hours * 60 + minutes + offsetMinutes;
        
        const newHours = Math.floor(totalMinutes / 60) % 24;
        const newMinutes = totalMinutes % 60;
        
        return `${String(newHours).padStart(2, '0')}:${String(newMinutes).padStart(2, '0')}`;
    }
    
    // Format time for display (12-hour format)
    function formatTime(timeStr) {
        try {
            const [hours, minutes] = timeStr.split(':').map(Number);
            const period = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            return `${displayHours}:${String(minutes).padStart(2, '0')} ${period}`;
        } catch (e) {
            return timeStr;
        }
    }
    
    // Update day type options when leave type changes
    function updateDayTypeOptions(index) {
        const leaveTypeId = generatedDates[index].leaveTypeId;
        const dayType = generatedDates[index].dayType;
        const dayTypeSelect = document.querySelector(`.day-type-select[data-index="${index}"]`);
        
        if (dayTypeSelect) {
            // Save current value if possible
            const currentValue = dayTypeSelect.value;
            
            // Update options
            dayTypeSelect.innerHTML = getDayTypeOptions(leaveTypeId, dayType);
            
            // If current value is valid in new options, keep it
            if (Array.from(dayTypeSelect.options).some(opt => opt.value === currentValue)) {
                dayTypeSelect.value = currentValue;
                generatedDates[index].dayType = currentValue;
            } else {
                // Otherwise use first option
                generatedDates[index].dayType = dayTypeSelect.options[0].value;
            }
        }
    }
    
    // Validate leave type selection based on rules
    function validateLeaveTypeSelection(index, newLeaveTypeId) {
        const date = new Date(generatedDates[index].date);
        const monthKey = `${date.getFullYear()}-${date.getMonth()}`;
        
        // Get leave type info
        const compInfo = findCompensateLeaveInfo();
        const casualInfo = findCasualLeaveInfo();
        
        // If no compensate or casual leave info, skip validation
        if (!compInfo || !casualInfo) {
            return { valid: true };
        }
        
        // Check if new leave type is casual
        const isNewCasual = newLeaveTypeId === casualInfo.id;
        
        // Rule 1: Check if there are any remaining compensate leaves
        // If yes, then user must use compensate leave first
        if (isNewCasual && compInfo.remaining > 0) {
            return {
                valid: false,
                message: `You must use all your Compensate Leave (${compInfo.remaining} days remaining) before using Casual Leave.`
            };
        }
        
        // Rule 2: Check if selecting casual leave would exceed the monthly limit (2 per month)
        if (isNewCasual) {
            // Count how many casual leaves are already selected for this month
            const casualLeavesInMonth = generatedDates.filter(dateInfo => {
                const d = new Date(dateInfo.date);
                const dMonthKey = `${d.getFullYear()}-${d.getMonth()}`;
                return dMonthKey === monthKey && 
                       dateInfo.checked && 
                       dateInfo.leaveTypeId === casualInfo.id &&
                       generatedDates.indexOf(dateInfo) !== index; // Exclude current date
            }).length;
            
            if (casualLeavesInMonth >= 2) {
                return {
                    valid: false,
                    message: `You can only use 2 Casual Leaves per month. You have already selected ${casualLeavesInMonth} Casual Leaves for ${date.toLocaleString('default', { month: 'long', year: 'numeric' })}.`
                };
            }
        }
        
        return { valid: true };
    }
    
    // Create a row for a date in the table
    function createDateRow(dateInfo, index) {
        const { date, checked, leaveTypeId, dayType } = dateInfo;
        const dateObj = new Date(date);
        const dateFormatted = formatDateReadable(dateObj);
        const dayOfWeek = getDayOfWeek(dateObj);
        const isWeekend = dayOfWeek === 'Saturday' || dayOfWeek === 'Sunday';
        const rowClass = isWeekend ? 'table-warning' : '';
        
        return `
            <tr class="${rowClass}" data-index="${index}">
                <td>
                    <div class="form-check">
                        <input class="form-check-input date-checkbox" type="checkbox" ${checked ? 'checked' : ''} 
                               data-index="${index}" id="date-check-${index}">
                    </div>
                </td>
                <td>${dateFormatted}</td>
                <td>${dayOfWeek}</td>
                <td>
                    <select class="form-select form-select-sm leave-type-select" data-index="${index}">
                        ${generateLeaveTypeOptions(leaveTypeId)}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm day-type-select" data-index="${index}">
                        ${getDayTypeOptions(leaveTypeId, dayType)}
                    </select>
                </td>
            </tr>
        `;
    }
    
    // Render the date list table
    function renderDateList() {
        if (generatedDates.length === 0) {
            dateListContainer.style.display = 'none';
            return;
        }
        
        let tableContent = '';
        generatedDates.forEach((dateInfo, index) => {
            tableContent += createDateRow(dateInfo, index);
        });
        
        dateListBody.innerHTML = tableContent;
        dateListContainer.style.display = '';
        
        // Add event listeners to the new elements
        document.querySelectorAll('.date-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const index = parseInt(this.getAttribute('data-index'), 10);
                generatedDates[index].checked = this.checked;
                updateFormData();
            });
        });
        
        document.querySelectorAll('.leave-type-select').forEach(select => {
            select.addEventListener('change', function() {
                const index = parseInt(this.getAttribute('data-index'), 10);
                const newLeaveTypeId = this.value;
                
                // Validate leave type selection
                const validation = validateLeaveTypeSelection(index, newLeaveTypeId);
                
                if (validation.valid) {
                    // Apply the change
                    generatedDates[index].leaveTypeId = newLeaveTypeId;
                    
                    // Update day type options when leave type changes
                    updateDayTypeOptions(index);
                    
                    // If switching to compensate leave, auto-assign a compensate day
                    if (isCompensateLeave(newLeaveTypeId)) {
                        // Get list of earned compensate days
                        const earnedCompDates = <?= json_encode($earned_comp_off_dates ?? []) ?>;
                        
                        // Find a compensate day that hasn't been used yet
                        const usedCompDates = generatedDates
                            .filter(d => d.compOffSourceDate && d !== generatedDates[index])
                            .map(d => d.compOffSourceDate);
                        
                        const availableCompDates = earnedCompDates.filter(d => !usedCompDates.includes(d));
                        
                        if (availableCompDates.length > 0) {
                            // Assign the oldest available compensate day
                            generatedDates[index].compOffSourceDate = availableCompDates[0];
                        } else {
                            // No available compensate days, show error
                            alert('No available compensate days left to assign.');
                            // Revert the selection
                            generatedDates[index].leaveTypeId = '';
                            this.value = '';
                            return;
                        }
                    } else {
                        // If not compensate leave, remove any assigned compensate day
                        delete generatedDates[index].compOffSourceDate;
                    }
                    
                    // If switching to short leave, set the time information based on day type
                    if (isShortLeave(newLeaveTypeId)) {
                        const dayType = generatedDates[index].dayType;
                        const shiftStartTime = '<?= $user_shift['start_time'] ? substr($user_shift['start_time'],0,5) : "09:00" ?>';
                        const shiftEndTime = '<?= $user_shift['end_time'] ? substr($user_shift['end_time'],0,5) : "18:00" ?>';
                        
                        if (dayType === 'morning') {
                            generatedDates[index].timeFrom = shiftStartTime;
                            generatedDates[index].timeTo = calculateTimeOffset(shiftStartTime, 90);
                        } else if (dayType === 'evening') {
                            generatedDates[index].timeFrom = calculateTimeOffset(shiftEndTime, -90);
                            generatedDates[index].timeTo = shiftEndTime;
                        }
                    } else {
                        // For non-short leaves, remove time information
                        delete generatedDates[index].timeFrom;
                        delete generatedDates[index].timeTo;
                    }
                    
                    // Remove any previous error message
                    const row = this.closest('tr');
                    const errorRow = document.getElementById(`error-row-${index}`);
                    if (errorRow) {
                        errorRow.remove();
                    }
                    
                    updateFormData();
                } else {
                    // Revert the selection
                    this.value = generatedDates[index].leaveTypeId;
                    
                    // Show error message
                    const row = this.closest('tr');
                    let errorRow = document.getElementById(`error-row-${index}`);
                    
                    if (!errorRow) {
                        errorRow = document.createElement('tr');
                        errorRow.id = `error-row-${index}`;
                        errorRow.className = 'error-message';
                        row.parentNode.insertBefore(errorRow, row.nextSibling);
                    }
                    
                    errorRow.innerHTML = `
                        <td colspan="5" class="text-danger p-2" style="background-color: #fff3f3;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            ${validation.message}
                        </td>
                    `;
                }
            });
        });
        
        document.querySelectorAll('.day-type-select').forEach(select => {
            select.addEventListener('change', function() {
                const index = parseInt(this.getAttribute('data-index'), 10);
                const selectedDayType = this.value;
                generatedDates[index].dayType = selectedDayType;
                
                // If this is a short leave, calculate and store the time information
                const leaveTypeId = generatedDates[index].leaveTypeId;
                if (isShortLeave(leaveTypeId)) {
                    const shiftStartTime = '<?= $user_shift['start_time'] ? substr($user_shift['start_time'],0,5) : "09:00" ?>';
                    const shiftEndTime = '<?= $user_shift['end_time'] ? substr($user_shift['end_time'],0,5) : "18:00" ?>';
                    
                    if (selectedDayType === 'morning') {
                        // Morning short leave: from shift start to shift start + 1h30m
                        generatedDates[index].timeFrom = shiftStartTime;
                        generatedDates[index].timeTo = calculateTimeOffset(shiftStartTime, 90);
                    } else if (selectedDayType === 'evening') {
                        // Evening short leave: from shift end - 1h30m to shift end
                        generatedDates[index].timeFrom = calculateTimeOffset(shiftEndTime, -90);
                        generatedDates[index].timeTo = shiftEndTime;
                    }
                } else {
                    // For non-short leaves, remove time information
                    delete generatedDates[index].timeFrom;
                    delete generatedDates[index].timeTo;
                }
                
                updateFormData();
            });
        });
    }
    
    // Update the form data based on the selected dates
    function updateFormData() {
        // Calculate total days selected
        const selectedDates = generatedDates.filter(d => d.checked);
        const totalDays = selectedDates.length;
        
        // Update duration badge
        durationBadge.textContent = totalDays === 1 ? '1 day' : `${totalDays} days`;
        
        // Create hidden input with the JSON data for form submission
        let dateListInput = document.getElementById('dateListInput');
        if (!dateListInput) {
            dateListInput = document.createElement('input');
            dateListInput.type = 'hidden';
            dateListInput.name = 'date_list';
            dateListInput.id = 'dateListInput';
            form.appendChild(dateListInput);
        }
        
        // Store the selected dates in the hidden input
        dateListInput.value = JSON.stringify(selectedDates);
    }
    
    // Auto-allocate leave types based on available balances
    function autoAllocateLeaveTypes() {
        if (generatedDates.length === 0) return;
        
        // Get compensate leave info
        const compInfo = findCompensateLeaveInfo();
        const casualInfo = findCasualLeaveInfo();
        const defaultLeaveTypeId = defaultLeaveType.value;
        
        // Get list of earned compensate days
        const earnedCompDates = <?= json_encode($earned_comp_off_dates ?? []) ?>;
        let availableCompDates = [...earnedCompDates]; // Make a copy so we can remove as we use them
        
        // Count how many compensate leaves we can allocate
        let compLeaveRemaining = compInfo ? compInfo.remaining : 0;
        let casualLeaveCount = {};  // Track casual leaves by month
        
        // Sort dates chronologically to ensure consistent allocation
        generatedDates.sort((a, b) => new Date(a.date) - new Date(b.date));
        
        // First pass: Allocate compensate leave until exhausted
        generatedDates.forEach((dateInfo, index) => {
            const date = new Date(dateInfo.date);
            const monthKey = `${date.getFullYear()}-${date.getMonth()}`;
            
            // Initialize casual leave count for this month if not exists
            if (!casualLeaveCount[monthKey]) {
                casualLeaveCount[monthKey] = 0;
            }
            
            // If compensate leave is available, use it first (must use all compensate leave before casual)
            if (compLeaveRemaining > 0 && availableCompDates.length > 0) {
                dateInfo.leaveTypeId = compInfo.id;
                
                // Auto-assign a compensate day (take the oldest one first)
                dateInfo.compOffSourceDate = availableCompDates.shift(); // Remove and return first element
                
                compLeaveRemaining--;
            }
            // Otherwise use casual leave if available (max 2 per month)
            else if (casualInfo && casualLeaveCount[monthKey] < 2) {
                dateInfo.leaveTypeId = casualInfo.id;
                casualLeaveCount[monthKey]++;
            }
            // Otherwise use default leave type
            else if (defaultLeaveTypeId) {
                dateInfo.leaveTypeId = defaultLeaveTypeId;
            }
        });
        
        // Render the updated list
        renderDateList();
        updateFormData();
    }
    

    
    // Function to generate the date list
    function generateDateList() {
        const fromDate = dateFrom.value;
        const toDate = dateTo.value;
        
        if (!fromDate || !toDate) {
            alert('Please select both From and To dates.');
            return;
        }
        
        // Generate date range
        const dateRange = generateDateRange(fromDate, toDate);
        
        // Create date info objects
        generatedDates = dateRange.map(date => ({
            date: formatDateYMD(date),
            checked: true,
            leaveTypeId: '',
            dayType: 'full',
            timeFrom: null,
            timeTo: null
        }));
        
        // Auto-allocate leave types
        autoAllocateLeaveTypes();
        
        // Render the date list
        renderDateList();
    }
    
    // Generate date list button click handler
    generateDateListBtn.addEventListener('click', function() {
        generateDateList();
    });
    
    // Check/uncheck all dates
    checkAllDates.addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('.date-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
            const index = parseInt(checkbox.getAttribute('data-index'), 10);
            generatedDates[index].checked = checked;
        });
        updateFormData();
    });
    
    // Select all dates button
    selectAllDatesBtn.addEventListener('click', function() {
        checkAllDates.checked = true;
        document.querySelectorAll('.date-checkbox').forEach(checkbox => {
            checkbox.checked = true;
            const index = parseInt(checkbox.getAttribute('data-index'), 10);
            generatedDates[index].checked = true;
        });
        updateFormData();
    });
    
    // Clear all dates button
    clearAllDatesBtn.addEventListener('click', function() {
        checkAllDates.checked = false;
        document.querySelectorAll('.date-checkbox').forEach(checkbox => {
            checkbox.checked = false;
            const index = parseInt(checkbox.getAttribute('data-index'), 10);
            generatedDates[index].checked = false;
        });
        updateFormData();
    });
    
    // Default leave type change handler
    defaultLeaveType.addEventListener('change', function() {
        // Re-allocate leave types when default changes
        autoAllocateLeaveTypes();
    });
    
    // Function to validate max days and balance
    function validateMaxDays() {
        const selectedOption = form.querySelector('select[name="leave_type"]').selectedOptions[0];
        const maxDays = selectedOption?.getAttribute('data-max-days');
        const leaveTypeId = selectedOption?.value;
        const selectedText = selectedOption?.text?.toLowerCase() || '';
        
        try {
            const balanceMap = JSON.parse(document.getElementById('leaveBalanceMap').textContent || '{}');
            const entry = balanceMap[leaveTypeId];
            
            // Check if this is a casual leave and if compensate leave is available
            const isCasual = (entry && entry.name && entry.name.toLowerCase().includes('casual')) || selectedText.includes('casual');
            
            // Find compensate leave balance
            let compLeaveEntry = null;
            let compLeaveRemaining = 0;
            
            // Look through all leave types to find compensate leave
            for (const id in balanceMap) {
                const leave = balanceMap[id];
                if (leave && leave.name && leave.name.toLowerCase().includes('comp')) {
                    compLeaveEntry = leave;
                    compLeaveRemaining = leave.remaining !== undefined ? Number(leave.remaining) : 0;
                    break;
                }
            }
            
            // If this is casual leave and comp leave is available, show warning
            if (isCasual && compLeaveRemaining > 0) {
                let casualWarn = document.getElementById('casualLeaveWarning');
                if (!casualWarn) {
                    casualWarn = document.createElement('div');
                    casualWarn.id = 'casualLeaveWarning';
                    durationBadge.parentNode.appendChild(casualWarn);
                }
                casualWarn.className = 'alert alert-danger small mt-2';
                casualWarn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>You cannot apply for Casual Leave until you consume all your Compensate Leave balance (' + compLeaveRemaining + ' days remaining).';
                
                // Disable the submit button
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
            } else {
                // Remove warning if not applicable
                const casualWarn = document.getElementById('casualLeaveWarning');
                if (casualWarn) casualWarn.remove();
                
                // Re-enable submit button if it was disabled
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) submitBtn.disabled = false;
            }
            
            // Enforce Short Leave monthly cap (2 per month)
            const isShort = (entry && entry.name && entry.name.toLowerCase().includes('short')) || selectedText.includes('short');
            if (isShort && entry) {
                const remainingShort = (entry.remaining !== undefined) ? Number(entry.remaining) : 0;
                let shortWarn = document.getElementById('shortLeaveMonthlyWarning');
                if (remainingShort <= 0) {
                    if (!shortWarn) {
                        shortWarn = document.createElement('div');
                        shortWarn.id = 'shortLeaveMonthlyWarning';
                        durationBadge.parentNode.appendChild(shortWarn);
                    }
                    shortWarn.className = 'alert alert-danger small mt-2';
                    shortWarn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>You have reached the monthly limit for Short Leave (2 per month).';
                } else if (shortWarn) {
                    shortWarn.remove();
                }
            } else {
                const shortWarn = document.getElementById('shortLeaveMonthlyWarning');
                if (shortWarn) shortWarn.remove();
            }
        } catch (err) {
            console.error('Error validating leave types:', err);
        }

        if (maxDays && maxDays > 0 && leaveTypeId) {
            const start = dateFrom.value ? new Date(dateFrom.value) : null;
            const end = dateTo.value ? new Date(dateTo.value) : null;
            
            if (start && end) {
                start.setHours(12,0,0,0);
                end.setHours(12,0,0,0);
                const diffMs = end - start;
                
                if (diffMs >= 0) {
                    let days = Math.floor(diffMs / (1000*60*60*24)) + 1;
                    
                    // Get current balance for this leave type
                    const balanceElement = document.getElementById(`bal-${leaveTypeId}`);
                    const remainingBalance = balanceElement ? parseFloat(balanceElement.textContent) : maxDays;
                    
                    // Show warnings
                    let warningElement = document.getElementById('maxDaysWarning');
                    let warningMessage = '';
                    let warningType = '';
                    
                    if (days > remainingBalance) {
                        warningType = 'danger';
                        warningMessage = `<i class="bi bi-exclamation-triangle me-1"></i>Duration (${days} days) exceeds your remaining balance (${remainingBalance} days) for this leave type.`;
                    } else if (days > maxDays) {
                        warningType = 'warning';
                        warningMessage = `<i class="bi bi-exclamation-triangle me-1"></i>Duration (${days} days) exceeds maximum allowed (${maxDays} days) for this leave type.`;
                    }
                    
                    if (warningMessage) {
                        if (!warningElement) {
                            warningElement = document.createElement('div');
                            warningElement.id = 'maxDaysWarning';
                            durationBadge.parentNode.appendChild(warningElement);
                        }
                        warningElement.className = `alert alert-${warningType} small mt-2`;
                        warningElement.innerHTML = warningMessage;
                    } else if (warningElement) {
                        warningElement.remove();
                    }
                }
            }
        }
    }



    form.addEventListener('submit', (e) => {
        e.preventDefault();

        // Check if casual leave warning is active
        const casualWarn = document.getElementById('casualLeaveWarning');
        if (casualWarn) {
            // Show a more prominent error
            const existingAlert = form.parentElement.querySelector('.apply-leave-alert');
            if (existingAlert) existingAlert.remove();
            
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger ss-card mt-3 apply-leave-alert';
            alert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>You must use all your Compensate Leave before applying for Casual Leave.';
            form.parentElement.appendChild(alert);
            
            // Scroll to the warning
            casualWarn.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return; // Stop form submission
        }

        // Disable submit while processing
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

        // Gather payload
        const defaultLeaveTypeId = parseInt(form.querySelector('select[name="leave_type"]').value, 10);
        const startDate = dateFrom.value;
        const endDate = dateTo.value;
        const reason = (form.querySelector('textarea[name="reason"]').value || '').trim();
        
        // Check if we're using the date list or the simple form
        // We use date list for multiple days, simple form for single day
        const isUsingDateList = isMultipleDaysSelected();
        
        let payload;
        
        if (isUsingDateList) {
            // Using the detailed date list
            try {
                const dateList = JSON.parse(dateListInput.value || '[]');
                
                // Check if any dates are selected
                const selectedDates = dateList.filter(d => d.checked);
                if (selectedDates.length === 0) {
                    throw new Error('No dates selected. Please select at least one date.');
                }
                
                // Validate that all selected dates follow the rules
                const compInfo = findCompensateLeaveInfo();
                const casualInfo = findCasualLeaveInfo();
                
                // Check if compensate leave is being used first
                if (compInfo && compInfo.remaining > 0) {
                    // Group dates by month
                    const datesByMonth = {};
                    selectedDates.forEach(dateInfo => {
                        const date = new Date(dateInfo.date);
                        const monthKey = `${date.getFullYear()}-${date.getMonth()}`;
                        
                        if (!datesByMonth[monthKey]) {
                            datesByMonth[monthKey] = [];
                        }
                        
                        datesByMonth[monthKey].push(dateInfo);
                    });
                    
                    // Check if any casual leaves are being used while compensate leave is available
                    const casualDates = selectedDates.filter(d => d.leaveTypeId === casualInfo?.id);
                    if (casualDates.length > 0) {
                        throw new Error(`You must use all your Compensate Leave (${compInfo.remaining} days remaining) before using Casual Leave.`);
                    }
                }
                
                // Check if casual leaves per month exceed limit
                if (casualInfo) {
                    const casualLeavesByMonth = {};
                    selectedDates.forEach(dateInfo => {
                        if (dateInfo.leaveTypeId === casualInfo.id) {
                            const date = new Date(dateInfo.date);
                            const monthKey = `${date.getFullYear()}-${date.getMonth()}`;
                            const monthName = date.toLocaleString('default', { month: 'long', year: 'numeric' });
                            
                            if (!casualLeavesByMonth[monthKey]) {
                                casualLeavesByMonth[monthKey] = {
                                    count: 0,
                                    name: monthName
                                };
                            }
                            
                            casualLeavesByMonth[monthKey].count++;
                        }
                    });
                    
                    // Check if any month exceeds the limit
                    for (const monthKey in casualLeavesByMonth) {
                        const monthData = casualLeavesByMonth[monthKey];
                        if (monthData.count > 2) {
                            throw new Error(`You can only use 2 Casual Leaves per month. You have selected ${monthData.count} Casual Leaves for ${monthData.name}.`);
                        }
                    }
                }
                
                payload = {
                    reason,
                    use_date_list: true,
                    date_list: selectedDates,
                    start_date: startDate,  // Keep these for compatibility
                    end_date: endDate
                };
            } catch (err) {
                alert('Error processing date list: ' + err.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                return;
            }
        } else {
            // Using the simple form (single leave type)
            // If short leave, map auto times into time_from/time_to
            let payloadTimeFrom = null;
            let payloadTimeTo = null;
            try {
                const balanceMap = JSON.parse(document.getElementById('leaveBalanceMap').textContent || '{}');
                const sel = form.querySelector('select[name="leave_type"]').value;
                const entry = balanceMap[sel];
                if (entry && entry.name && entry.name.toLowerCase().includes('short')) {
                    payloadTimeFrom = window.__shortLeaveAutoFrom || null;
                    payloadTimeTo = window.__shortLeaveAutoTo || null;
                }
            } catch (_) {}
            
            // If this is a compensate leave, auto-assign a compensate day
            let compOffSourceDate = null;
            if (isCompensateLeave(defaultLeaveTypeId)) {
                // Get list of earned compensate days
                const earnedCompDates = <?= json_encode($earned_comp_off_dates ?? []) ?>;
                
                if (earnedCompDates.length > 0) {
                    // Assign the oldest available compensate day
                    compOffSourceDate = earnedCompDates[0];
                } else {
                    alert('No available compensate days to assign.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    return;
                }
            }
            
            payload = {
                leave_type: defaultLeaveTypeId,
                start_date: startDate,
                end_date: endDate,
                reason,
                use_date_list: false,
                time_from: payloadTimeFrom,
                time_to: payloadTimeTo,
                comp_off_source_date: compOffSourceDate
            };
        }

        // Remove any prior alert
        const existingAlert = form.parentElement.querySelector('.apply-leave-alert');
        if (existingAlert) existingAlert.remove();

        fetch('api/submit_leave_request_20250810.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(async (res) => {
                const errorId = res.headers.get('X-Error-ID');
                const raw = await res.text();
                let data = {};
                try { data = JSON.parse(raw); } catch (_) {}

                if (!res.ok || !data.success) {
                    const msg = (data && (data.error || data.message)) || `Request failed (${res.status})`;
                    if (errorId) {
                        console.error('Leave submit failed', { errorId, status: res.status, body: raw });
                    } else {
                        console.error('Leave submit failed', { status: res.status, body: raw });
                    }
                    const displayMsg = errorId ? `${msg} [Error ID: ${errorId}]` : msg;
                    throw new Error(displayMsg);
                }
                return data;
            })
            .then((data) => {
                // Reset short leave warning
                const slw = document.getElementById('shortLeaveTimeWarning');
                if (slw) slw.classList.add('d-none');
                const alert = document.createElement('div');
                alert.className = 'alert alert-success ss-card mt-3 apply-leave-alert';
                alert.innerHTML = '<i class="bi bi-check-circle me-2"></i> Leave application submitted successfully.';
                form.parentElement.appendChild(alert);

                // Reset key inputs
                form.reset();
                const warn = document.getElementById('maxDaysWarning');
                if (warn) warn.remove();
            })
            .catch((err) => {
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger ss-card mt-3 apply-leave-alert';
                alert.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i> ${err.message}`;
                form.parentElement.appendChild(alert);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
    });
});
</script>
</body>
</html>





