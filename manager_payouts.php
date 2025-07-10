<?php
// Include database connection
require_once 'config/db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to format currency
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

// Fetch project payment transactions
$query = "SELECT t.*, 
          GROUP_CONCAT(
            CONCAT(
                e.payment_mode, ' (₹', FORMAT(e.payment_amount, 2), 
                CASE WHEN e.payment_date IS NOT NULL THEN CONCAT(', ', DATE_FORMAT(e.payment_date, '%Y-%m-%d')) ELSE '' END
            ) SEPARATOR ', '
          ) as payment_details,
          SUM(e.payment_amount) as total_paid_amount
          FROM hrm_project_stage_payment_transactions t
          LEFT JOIN hrm_project_payment_entries e ON t.transaction_id = e.transaction_id
          GROUP BY t.transaction_id
          ORDER BY t.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
    
    // Group projects with the same name
    $projectGroups = [];
    foreach ($transactions as $transaction) {
        $projectName = $transaction['project_name'];
        
        if (!isset($projectGroups[$projectName])) {
            $projectGroups[$projectName] = [
                'first_transaction' => $transaction,
                'stages' => [$transaction],
                'count' => 1
            ];
        } else {
            $projectGroups[$projectName]['stages'][] = $transaction;
            $projectGroups[$projectName]['count']++;
        }
    }
    
    // Prepare the grouped transactions array for display
    $groupedTransactions = [];
    foreach ($projectGroups as $projectName => $group) {
        $groupedTransactions[] = [
            'transaction' => $group['first_transaction'],
            'additional_stages' => $group['count'] > 1 ? $group['count'] - 1 : 0,
            'all_stages' => $group['stages']
        ];
    }
    
    // Get summary data for the overview cards
    $summaryQuery = "SELECT 
                    SUM(e.payment_amount) as total_amount,
                    SUM(CASE WHEN t.project_type = 'architecture' THEN e.payment_amount ELSE 0 END) as architecture_amount,
                    SUM(CASE WHEN t.project_type = 'interior' THEN e.payment_amount ELSE 0 END) as interior_amount,
                    SUM(CASE WHEN t.project_type = 'construction' THEN e.payment_amount ELSE 0 END) as construction_amount
                    FROM hrm_project_payment_entries e
                    JOIN hrm_project_stage_payment_transactions t ON e.transaction_id = t.transaction_id";
    
    $summaryStmt = $conn->prepare($summaryQuery);
    $summaryStmt->execute();
    $summary = $summaryStmt->get_result()->fetch_assoc();
    
    // Set default values if no data found
    if (!$summary) {
        $summary = [
            'total_amount' => 0,
            'architecture_amount' => 0,
            'interior_amount' => 0,
            'construction_amount' => 0
        ];
    }
    
} catch (Exception $e) {
    error_log("Error fetching payment data: " . $e->getMessage());
    $transactions = [];
    $summary = [
        'total_amount' => 0,
        'architecture_amount' => 0,
        'interior_amount' => 0,
        'construction_amount' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Payouts</title>
    
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    
    <style>
        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #7C3AED;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --dark: #111827;
            --gray: #6B7280;
            --light: #F3F4F6;
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F9FAFB;
            color: #333;
            line-height: 1.6;
        }
        
        /* Modern Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .toggle-sidebar {
            position: fixed;
            left: calc(var(--sidebar-width) - 16px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1001;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle-sidebar:hover {
            background: var(--primary);
            color: white;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link {
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }
        
        /* Header Section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .user-welcome h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .user-welcome p {
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        /* Logout button styles */
        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            color: black!important;
            background-color: #D22B2B;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Update nav container to allow for margin-top: auto on logout */
        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px); /* Adjust based on your logo height */
        }
        
        /* User profile dropdown */
        .profile-dropdown {
            min-width: 240px;
            padding: 0;
            margin-top: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            background: white;
        }

        .profile-dropdown .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: 12px 12px 0 0;
        }

        .profile-dropdown .dropdown-header h6 {
            margin: 0;
            color: #1e293b;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .profile-dropdown .dropdown-header small {
            color: #64748b;
            font-size: 0.825rem;
            margin-top: 2px;
            display: block;
        }

        .profile-dropdown .dropdown-item {
            padding: 0.875rem 1rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .profile-dropdown .dropdown-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .profile-dropdown .dropdown-item:hover {
            background-color: #f1f5f9;
            color: #4f46e5;
        }

        .profile-dropdown .dropdown-item.text-danger {
            color: #dc2626;
        }

        .profile-dropdown .dropdown-item.text-danger:hover {
            background-color: #fef2f2;
            color: #dc2626;
        }

        .profile-dropdown .dropdown-divider {
            margin: 0.5rem 0;
            border-color: #e5e7eb;
        }

        /* Profile Image Styles */
        #profileDropdown {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #e5e7eb;
            padding: 2px;
            border-radius: 50%;
        }

        #profileDropdown:hover {
            border-color: #4f46e5;
            transform: scale(1.05);
        }

        /* Animation for dropdown */
        .dropdown-menu.show {
            animation: dropdownFade 0.2s ease-out;
        }

        @keyframes dropdownFade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .attendance-action {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .attendance-btn {
            padding: 8px 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .attendance-btn i {
            font-size: 1.1rem;
        }

        .attendance-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .punch-time {
            font-size: 0.875rem;
            color: #6B7280;
            white-space: nowrap;
        }
        
        /* Container styles */
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            font-size: 28px;
            color: #333;
            margin-top: 10px;
            text-align: left;
            font-weight: 600;
        }
        
        hr {
            border: 0;
            height: 1px;
            background-color: #ddd;
            margin: 15px 0;
        }

        .filter-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 16px 20px;
            margin-top: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .filter-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .reset-btn {
            background-color: #f8f8f8;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            color: #555;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        
        .reset-btn:hover {
            background-color: #f0f0f0;
        }
        
        .reset-btn.btn-animated {
            background-color: #e8f4ff;
            transform: scale(1.05);
            transition: all 0.2s ease;
        }
        
        /* Active filter styling */
        .filter-select:not([value=""]), 
.filter-input:not(:placeholder-shown) {
    border-color: #4F46E5;
    box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.2);
}

/* Enhanced styling for active filters */
.filter-select:not([value=""]) {
    background-color: rgba(79, 70, 229, 0.05);
    font-weight: 500;
}

/* Highlight the active client filter */
#filterClientName:not([value=""]) {
    background-color: rgba(79, 70, 229, 0.1);
    border-color: #4F46E5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.25);
    font-weight: bold;
}
        
        /* Filter count badge */
        .filter-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background-color: #4F46E5;
            color: white;
            border-radius: 50%;
            font-size: 12px;
            margin-left: 8px;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.2s ease;
        }
        
        .filter-count.active {
            opacity: 1;
            transform: scale(1);
        }

        .filter-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 15px;
        }

        .filter-group {
            flex: 1;
        }

        .filter-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }

        .filter-select, .filter-input {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
            font-size: 14px;
            color: #333;
        }
        
        .filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23555' d='M6 8.825L1.175 4 2.05 3.125 6 7.075 9.95 3.125 10.825 4z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 25px;
        }

        .date-inputs {
            display: flex;
            gap: 8px;
        }

        .date-input {
            flex: 1;
            position: relative;
        }

        .date-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
            font-size: 14px;
            pointer-events: none;
        }
        
        /* Table styles */
        .data-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: separate;
            border-spacing: 0;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .data-table th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background-color: #f5f7fa;
        }
        
        .data-table tr.highlight-row {
            background-color: rgba(79, 70, 229, 0.05);
            transition: background-color 0.3s ease;
        }
        
        .data-table tr.highlight-row:hover {
            background-color: rgba(79, 70, 229, 0.1);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-paid {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .status-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .status-overdue {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .view-btn {
            background-color: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }
        
        .view-btn:hover {
            background-color: rgba(79, 70, 229, 0.2);
        }
        
        .edit-btn {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .edit-btn:hover {
            background-color: rgba(16, 185, 129, 0.2);
        }
        
        /* Quick Overview Styles */
        .overview-section {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin-top: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }
        
        .overview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .overview-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .financial-summary-btn {
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 30px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .financial-summary-btn:hover {
            background-color: #1d4ed8;
        }
        
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .overview-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }
        
        .card-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .card-info {
            flex: 1;
        }
        
        .card-title {
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
            margin: 0 0 8px 0;
        }
        
        .card-amount {
            font-size: 22px;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            margin-left: 12px;
        }
        
        .card-icon i {
            font-size: 20px;
        }
        
        .money-icon {
            background-color: rgba(37, 99, 235, 0.1);
            color: #2563eb;
        }
        
        .architecture-icon {
            background-color: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }
        
        .interior-icon {
            background-color: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }
        
        .construction-icon {
            background-color: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }
        
        .card-progress {
            height: 4px;
            background-color: #f3f4f6;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 2px;
        }
        
        .progress-blue {
            background-color: #2563eb;
            width: 35%;
        }
        
        .progress-gray {
            background-color: #e5e7eb;
            width: 15%;
        }
        
        @media (max-width: 1200px) {
            .overview-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .overview-cards {
                grid-template-columns: 1fr;
            }
            
            .overview-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .financial-summary-btn {
                align-self: flex-end;
            }
        }
        
        @media (max-width: 992px) {
            .filter-row {
                flex-wrap: wrap;
            }
            
            .filter-group {
                min-width: calc(50% - 8px);
                flex: 0 0 calc(50% - 8px);
            }
        }
        
        @media (max-width: 576px) {
            .filter-group {
                min-width: 100%;
                flex: 0 0 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="bi bi-hexagon-fill"></i>
            HR Portal
        </div>
        
        <nav>
            <a href="hr_dashboard.php" class="nav-link">
                <i class="bi bi-grid-1x2-fill"></i>
                Dashboard
            </a>
            <a href="employee.php" class="nav-link">
                <i class="bi bi-people-fill"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
            <a href="shifts.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="payouts.php" class="nav-link active">
                <i class="bi bi-cash-coin"></i>
                Manager Payouts
            </a>
            <a href="company_analytics_dashboard.php" class="nav-link">
                <i class="bi bi-graph-up"></i>
                Company Stats
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="construction_site_overview.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="hr_overtime_approval.php" class="nav-link">
                <i class="bi bi-clock"></i>
                Overtime Approval
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="bi bi-key-fill"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Added Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Add this button after the sidebar div -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Add Toast Notification Container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <span id="toastMessage" class="fw-medium">Project added successfully!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="header">
            <div class="user-welcome">
                <h1>Manager Payouts</h1>
                <p><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="user-actions d-flex align-items-center gap-3">
                <button class="btn btn-light">
                    <i class="bi bi-bell"></i>
                </button>
                <!-- Profile dropdown -->
                <div class="dropdown">
                    <img src="assets/images/default-avatar.png" 
                         alt="Profile" 
                         class="rounded-circle dropdown-toggle" 
                         width="40" 
                         height="40" 
                         role="button" 
                         id="profileDropdown" 
                         data-bs-toggle="dropdown" 
                         aria-expanded="false"
                         style="object-fit: cover;">
                    
                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown" aria-labelledby="profileDropdown">
                        <li class="dropdown-header">
                            <h6 class="mb-0">HR Admin</h6>
                            <small class="text-muted">HR Department</small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="hr_profile.php">
                                <i class="bi bi-person me-2"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="settings.php">
                                <i class="bi bi-gear me-2"></i> Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <hr>
        
        <div class="filter-section">
                <div class="filter-header">
        <div class="filter-title">
            Filter Options
            <span class="filter-count" id="activeFilterCount">0</span>
        </div>
        <button class="reset-btn">
            <span>⟳</span> Reset Filters
        </button>
    </div>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label" for="filterProjectType">Project Type</label>
                    <select class="filter-select" id="filterProjectType">
                        <option value="">All Types</option>
                        <option value="architecture">Architecture</option>
                        <option value="interior">Interior</option>
                        <option value="construction">Construction</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date Range</label>
                    <div class="date-inputs">
                        <div class="date-input">
                            <input type="date" class="filter-input" id="filterStartDate" value="2025-06-09">
                        </div>
                        <div class="date-input">
                            <input type="date" class="filter-input" id="filterEndDate" value="2025-07-09">
                        </div>
                    </div>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="filterClientName">Client Name</label>
                    <select class="filter-select" id="filterClientName">
                        <option value="">All Clients</option>
                        <?php
                        // Fetch unique client names from database
                        $clientQuery = "SELECT DISTINCT client_name FROM hrm_project_stage_payment_transactions ORDER BY client_name";
                        try {
                            $clientStmt = $conn->prepare($clientQuery);
                            $clientStmt->execute();
                            $clientResult = $clientStmt->get_result();
                            
                            while ($client = $clientResult->fetch_assoc()) {
                                $clientName = htmlspecialchars(trim($client['client_name']));
                                if (!empty($clientName)) {
                                    echo '<option value="' . $clientName . '">' . $clientName . '</option>';
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching client names: " . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="filterManager">Senior Manager</label>
                    <select class="filter-select" id="filterManager">
                        <option value="">All Managers</option>
                        <option value="John Smith">John Smith</option>
                        <option value="Sarah Johnson">Sarah Johnson</option>
                        <option value="Michael Brown">Michael Brown</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Quick Overview Section -->
        <div class="overview-section">
            <div class="overview-header">
                <h2 class="overview-title">Section Quick Overview</h2>
                <button class="financial-summary-btn">Financial Summary</button>
            </div>
            
            <div class="overview-cards">
                <div class="overview-card">
                    <div class="card-content">
                        <div class="card-info">
                            <h3 class="card-title">Total Amount Received</h3>
                            <p class="card-amount"><?php echo formatCurrency($summary['total_amount']); ?></p>
                        </div>
                        <div class="card-icon money-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                    <div class="card-progress">
                        <div class="progress-bar progress-blue" style="width: 100%;"></div>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="card-content">
                        <div class="card-info">
                            <h3 class="card-title">Architecture Amount</h3>
                            <p class="card-amount"><?php echo formatCurrency($summary['architecture_amount']); ?></p>
                        </div>
                        <div class="card-icon architecture-icon">
                            <i class="bi bi-building"></i>
                        </div>
                    </div>
                    <div class="card-progress">
                        <?php 
                        $architecturePercentage = ($summary['total_amount'] > 0) ? 
                            ($summary['architecture_amount'] / $summary['total_amount'] * 100) : 0;
                        ?>
                        <div class="progress-bar progress-blue" style="width: <?php echo $architecturePercentage; ?>%;"></div>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="card-content">
                        <div class="card-info">
                            <h3 class="card-title">Interior Amount</h3>
                            <p class="card-amount"><?php echo formatCurrency($summary['interior_amount']); ?></p>
                        </div>
                        <div class="card-icon interior-icon">
                            <i class="bi bi-house-door"></i>
                        </div>
                    </div>
                    <div class="card-progress">
                        <?php 
                        $interiorPercentage = ($summary['total_amount'] > 0) ? 
                            ($summary['interior_amount'] / $summary['total_amount'] * 100) : 0;
                        ?>
                        <div class="progress-bar progress-blue" style="width: <?php echo $interiorPercentage; ?>%;"></div>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="card-content">
                        <div class="card-info">
                            <h3 class="card-title">Construction Amount</h3>
                            <p class="card-amount"><?php echo formatCurrency($summary['construction_amount']); ?></p>
                        </div>
                        <div class="card-icon construction-icon">
                            <i class="bi bi-bricks"></i>
                        </div>
                    </div>
                    <div class="card-progress">
                        <?php 
                        $constructionPercentage = ($summary['total_amount'] > 0) ? 
                            ($summary['construction_amount'] / $summary['total_amount'] * 100) : 0;
                        ?>
                        <div class="progress-bar progress-blue" style="width: <?php echo $constructionPercentage; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Data Table -->
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h3 class="text-primary fw-bold d-flex align-items-center m-0">
            <i class="bi bi-receipt me-2"></i>
            Project Payment Transactions
            <span class="badge bg-primary-subtle text-primary ms-2 fs-6"><?php echo count($transactions); ?> Records</span>
            <div id="activeClientFilter" class="ms-3 d-none">
                <span class="badge bg-info text-white">
                    <i class="bi bi-funnel-fill me-1"></i>
                    Client Filter: <span id="activeClientName"></span>
                    <button type="button" class="btn-close btn-close-white ms-2" aria-label="Clear filter" id="clearClientFilter" style="font-size: 0.6rem;"></button>
                </span>
            </div>
        </h3>
            <div class="d-flex gap-2">
                <button class="btn btn-success d-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle"></i>
                    Add Project
                </button>
                <button class="btn btn-info text-white d-flex align-items-center gap-1">
                    <i class="bi bi-graph-up"></i>
                    Company Stats
                </button>
            </div>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Project Name</th>
                    <th>Project Type</th>
                    <th>Client Name</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Payment Mode</th>
                    <th>Stage</th>
                    <th>Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($groupedTransactions) > 0): ?>
                    <?php foreach ($groupedTransactions as $index => $group): ?>
                        <?php $transaction = $group['transaction']; ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <?php echo htmlspecialchars($transaction['project_name']); ?>
                                <?php if ($group['additional_stages'] > 0): ?>
                                    <a href="#" class="ms-2 badge bg-info text-decoration-none view-stages" data-project-name="<?php echo htmlspecialchars($transaction['project_name']); ?>" data-bs-toggle="modal" data-bs-target="#projectStagesModal">
                                        +<?php echo $group['additional_stages']; ?> more stages
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary">
                                    <i class="bi bi-building"></i> 
                                    <?php echo strtoupper(htmlspecialchars($transaction['project_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['client_name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($transaction['stage_date'])); ?></td>
                            <td><?php echo formatCurrency($transaction['total_paid_amount']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['payment_details']); ?></td>
                            <td><span class="badge bg-primary">Stage <?php echo $transaction['stage_number']; ?></span></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-transaction-id="<?php echo $transaction['transaction_id']; ?>"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-info view-btn" data-transaction-id="<?php echo $transaction['transaction_id']; ?>" data-project-id="<?php echo $transaction['project_id'] ?? 0; ?>"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-transaction-id="<?php echo $transaction['transaction_id']; ?>"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-folder-x text-muted" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-3 mb-1">No project payment records found</h5>
                                <p class="text-muted">Add a new project to get started</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="addProjectModalLabel"><i class="bi bi-plus-circle me-2"></i>Add New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addProjectForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="projectName" class="form-label fw-medium">Project Name</label>
                                <input type="text" class="form-control" id="projectName" placeholder="Enter project name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="projectType" class="form-label fw-medium">Project Type</label>
                                <select class="form-select" id="projectType" required>
                                    <option value="" selected disabled>Select project type</option>
                                    <option value="architecture">Architecture</option>
                                    <option value="interior">Interior</option>
                                    <option value="construction">Construction</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="clientName" class="form-label fw-medium">Client Name</label>
                            <input type="text" class="form-control" id="clientName" placeholder="Enter client name" required>
                        </div>
                        
                        <div class="project-stages-section border rounded p-3 mb-3 bg-light">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold">Project Stages</h6>
                            <button type="button" id="initialAddStageBtn" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add Stage
                            </button>
                        </div>
                        <div id="stagesContainer">
                            <!-- Stages will be added here dynamically -->
                            <p class="text-muted small fst-italic mb-0">No stages added yet. Click "Add Stage" to begin.</p>
                        </div>
                        <!-- Floating Add Stage button will appear here after adding stages -->
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addProjectForm" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Save Project
                    </button>
                </div>
                
                <!-- Success Alert -->
                <div class="alert alert-success alert-dismissible fade d-none" id="saveSuccessAlert" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Project payment data saved successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                
                <!-- Error Alert -->
                <div class="alert alert-danger alert-dismissible fade d-none" id="saveErrorAlert" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span id="errorMessage">An error occurred while saving.</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Project Modal -->
    <div class="modal fade" id="viewProjectModal" tabindex="-1" aria-labelledby="viewProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewProjectModalLabel"><i class="bi bi-eye me-2"></i>Project Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Project details section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Project Information</h6>
                            <span class="badge bg-primary" id="viewProjectType"></span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Project Name</h6>
                                    <p class="fw-medium fs-5" id="viewProjectName"></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Client Name</h6>
                                    <p class="fw-medium fs-5" id="viewClientName"></p>
                                </div>
                            </div>
                            <div class="row" id="viewProjectAmountRow">
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Total Project Amount</h6>
                                    <p class="fw-bold fs-5" id="viewTotalProjectAmount"></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Remaining Amount</h6>
                                    <p class="fw-bold fs-5" id="viewRemainingAmount"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stages & Payments Section -->
                    <div id="viewProjectStages">
                        <h5 class="fw-bold mb-3"><i class="bi bi-layers me-2"></i>Project Stages & Payments</h5>
                        <div class="accordion" id="viewStagesAccordion">
                            <!-- Stages will be added here dynamically -->
                            <div id="loadingStages" class="text-center p-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading project stages...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printProjectDetails">
                        <i class="bi bi-printer me-1"></i> Print Details
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Project Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="editProjectModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editProjectForm">
                        <input type="hidden" id="editTransactionId" name="transaction_id">
                        <input type="hidden" id="editProjectId" name="project_id">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editProjectName" class="form-label fw-medium">
                                    Project Name
                                    <i class="bi bi-info-circle text-muted" 
                                       data-bs-toggle="tooltip" 
                                       title="This will update the project name for all stages of this project"></i>
                                </label>
                                <input type="text" class="form-control" id="editProjectName" placeholder="Enter project name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editProjectType" class="form-label fw-medium">Project Type</label>
                                <select class="form-select" id="editProjectType" required>
                                    <option value="" disabled>Select project type</option>
                                    <option value="architecture">Architecture</option>
                                    <option value="interior">Interior</option>
                                    <option value="construction">Construction</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="editClientName" class="form-label fw-medium">
                                Client Name
                                <i class="bi bi-info-circle text-muted" 
                                   data-bs-toggle="tooltip" 
                                   title="This will update the client name for all stages of this project"></i>
                            </label>
                            <input type="text" class="form-control" id="editClientName" placeholder="Enter client name" required>
                        </div>
                        
                        <div class="project-stages-section border rounded p-3 mb-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold">Current Project Stage</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Stage Number</label>
                                    <input type="number" class="form-control" id="editStageNumber" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control" id="editStageDate" required>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-12">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Stage Payments</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="editAddPaymentBtn">
                                            <i class="bi bi-plus"></i> Add Payment
                                        </button>
                                    </div>
                                    
                                    <div id="editPaymentEntries">
                                        <!-- Payment entries will be added here dynamically -->
                                    </div>
                                    
                                    <div class="stage-total d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                                        <span class="fw-medium">Stage Total:</span>
                                        <span class="fw-bold text-primary" id="editStageTotal">₹0.00</span>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="editRemainingAmountToggle">
                                        <label class="form-check-label" for="editRemainingAmountToggle">
                                            Track total project amount and remaining balance
                                        </label>
                                    </div>
                                    
                                    <div id="editRemainingAmountInfo" class="d-none">
                                        <label class="form-label">Total Project Amount</label>
                                        <input type="number" class="form-control" id="editTotalProjectAmount" 
                                               placeholder="Enter total project amount" min="0" step="0.01">
                                        <small class="text-muted">Enter the total project amount to calculate remaining amount</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <label class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" id="editStageNotes" rows="2" placeholder="Any additional details about this stage or payment"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Add New Stage Section -->
                        <div class="d-flex justify-content-center mb-3">
                            <button type="button" class="btn btn-primary" id="addNewStageBtn">
                                <i class="bi bi-plus-circle me-1"></i> Add New Stage
                            </button>
                        </div>
                        
                        <div id="newStageContainer" class="d-none project-stages-section border rounded p-3 mb-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold">New Project Stage</h6>
                                <button type="button" class="btn-close" id="cancelNewStageBtn" aria-label="Cancel new stage"></button>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Stage Number</label>
                                    <input type="number" class="form-control" id="newStageNumber" min="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control" id="newStageDate" required>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-12">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Stage Payments</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="newStageAddPaymentBtn">
                                            <i class="bi bi-plus"></i> Add Payment
                                        </button>
                                    </div>
                                    
                                    <div id="newStagePaymentEntries">
                                        <!-- New stage payment entries will be added here dynamically -->
                                        <div class="payment-entry card mb-2">
                                            <div class="card-body p-3">
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label small">Date</label>
                                                        <input type="date" class="form-control form-control-sm new-payment-date" 
                                                               name="new_stage_payment_date" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small">Amount (₹)</label>
                                                        <input type="number" class="form-control form-control-sm new-payment-amount" 
                                                               name="new_stage_payment_amount" 
                                                               placeholder="0.00" min="0" step="0.01" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small">Payment Mode</label>
                                                        <select class="form-select form-select-sm new-payment-mode" 
                                                                name="new_stage_payment_mode" required>
                                                            <option value="" selected disabled>Select mode</option>
                                                            <option value="cash">Cash</option>
                                                            <option value="upi">UPI</option>
                                                            <option value="net_banking">Net Banking</option>
                                                            <option value="cheque">Cheque</option>
                                                            <option value="credit_card">Credit Card</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="stage-total d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                                        <span class="fw-medium">New Stage Total:</span>
                                        <span class="fw-bold text-primary" id="newStageTotal">₹0.00</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <label class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" id="newStageNotes" rows="2" placeholder="Any additional details about this stage or payment"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editProjectForm" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Update Project
                    </button>
                </div>
                
                <!-- Edit Success Alert -->
                <div class="alert alert-success alert-dismissible fade d-none" id="editSuccessAlert" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Project updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                
                <!-- Edit Error Alert -->
                <div class="alert alert-danger alert-dismissible fade d-none" id="editErrorAlert" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span id="editErrorMessage">An error occurred while updating.</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="deleteTransactionId">
                    <p>Are you sure you want to delete this project stage?</p>
                    <p class="fw-bold text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash me-1"></i> Delete Stage
                    </button>
                </div>
                
                <!-- Delete Success Alert -->
                <div class="alert alert-success alert-dismissible fade d-none" id="deleteSuccessAlert" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Project stage deleted successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                
                <!-- Delete Error Alert -->
                <div class="alert alert-danger alert-dismissible fade d-none" id="deleteErrorAlert" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span id="deleteErrorMessage">An error occurred while deleting.</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        </div>
    
    <!-- Project Stages Modal -->
    <div class="modal fade" id="projectStagesModal" tabindex="-1" aria-labelledby="projectStagesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="projectStagesModalLabel"><i class="bi bi-layers me-2"></i><span id="projectStagesTitle"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center" id="stagesSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading project stages...</p>
                    </div>
                    
                    <div class="table-responsive" id="stagesTableContainer" style="display: none;">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Stage</th>
                                    <th>Date</th>
                                    <th>Client Name</th>
                                    <th>Amount</th>
                                    <th>Payment Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="projectStagesTableBody">
                                <!-- Stages will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-danger d-none" id="stagesErrorMessage">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Error loading project stages.
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="d-flex justify-content-between w-100">
                        <div>
                            <span class="text-muted" id="stagesCount">0 stages</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="printAllStages">
                                <i class="bi bi-printer me-1"></i> Print All Stages
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleButton = document.getElementById('sidebarToggle');
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Filter functionality
        const filterProjectType = document.getElementById('filterProjectType');
        const filterStartDate = document.getElementById('filterStartDate');
        const filterEndDate = document.getElementById('filterEndDate');
        const filterClientName = document.getElementById('filterClientName');
        const filterManager = document.getElementById('filterManager');
        const resetFilterBtn = document.querySelector('.reset-btn');
        const tableRows = document.querySelectorAll('.data-table tbody tr');
        const activeFilterCount = document.getElementById('activeFilterCount');
        
        // Function to filter table data
        function filterTable() {
            console.log("Filter function triggered");
            
            const projectTypeValue = filterProjectType.value.toLowerCase();
            const startDate = filterStartDate.value ? new Date(filterStartDate.value) : null;
            const endDate = filterEndDate.value ? new Date(filterEndDate.value) : null;
            const clientNameValue = filterClientName.value;
            const selectedClientText = clientNameValue ? 
                filterClientName.options[filterClientName.selectedIndex].text : '';
            const managerValue = filterManager.value.toLowerCase();
            
            console.log("Filter values:", {
                projectType: projectTypeValue,
                startDate: filterStartDate.value,
                endDate: filterEndDate.value,
                clientValue: clientNameValue,
                clientText: selectedClientText,
                manager: managerValue
            });
            
            // Update client filter indicator
            const activeClientFilter = document.getElementById('activeClientFilter');
            const activeClientName = document.getElementById('activeClientName');
            
            if (clientNameValue) {
                activeClientFilter.classList.remove('d-none');
                activeClientName.textContent = selectedClientText;
            } else {
                activeClientFilter.classList.add('d-none');
            }
            
            // Update active filter count
            updateFilterCount();
            
            let hasVisibleRows = false;
            
            // Loop through all table rows
            tableRows.forEach(row => {
                // Skip the "no records" row if it exists
                // Skip rows that don't have standard structure (like "no records" rows)
                if (row.cells.length === 1 || row.cells.length < 4 || row.className === 'no-results-message') {
                    return;
                }
                
                // Get cell values for filtering
                const projectType = row.cells[2].textContent.trim().toLowerCase();
                const projectDate = row.cells[4].textContent.trim();
                const rowDate = convertDateFormat(projectDate);
                const paymentAmount = parseFloat(row.cells[5].textContent.replace(/[₹,]/g, '')) || 0;
                const clientName = row.cells[3].textContent.trim().toLowerCase();
                
                // Default visibility is true
                let isVisible = true;
                
                // Filter by project type
                if (projectTypeValue && !projectType.includes(projectTypeValue)) {
                    isVisible = false;
                }
                
                // Filter by date range
                if (startDate && endDate) {
                    const dateToCheck = new Date(rowDate);
                    if (dateToCheck < startDate || dateToCheck > endDate) {
                        isVisible = false;
                    }
                }
                
                // Filter by client name
                if (clientNameValue) {
                    // Get the client name from the table row (4th column - index 3)
                    const rowClientText = row.cells[3].textContent.trim();
                    
                    // Get the selected client name from the dropdown
                    const selectedClientText = filterClientName.options[filterClientName.selectedIndex].text;
                    
                    // Compare exact match (case insensitive)
                    const isMatch = rowClientText.toLowerCase() === selectedClientText.toLowerCase();
                    
                    console.log(`Row ${row.cells[0].textContent} comparison:`, {
                        rowClient: rowClientText,
                        selectedClient: selectedClientText,
                        match: isMatch
                    });
                    
                    // Only show matching rows
                    if (!isMatch) {
                        isVisible = false;
                    } else {
                        // Highlight matching rows
                        row.classList.add('highlight-row');
                    }
                } else {
                    // No client filter active, remove highlighting
                    row.classList.remove('highlight-row');
                }
                
                // Filter by manager
                if (managerValue && !clientName.includes(managerValue.toLowerCase())) {
                    isVisible = false;
                }
                
                // Set row visibility
                row.style.display = isVisible ? '' : 'none';
                
                // Track if we have any visible rows
                if (isVisible) {
                    hasVisibleRows = true;
                }
            });
            
            // Show "no results" message if all rows are filtered out
            showNoResultsMessage(hasVisibleRows);
        }
        
        // Helper function to convert date format from DD/MM/YYYY to YYYY-MM-DD
        function convertDateFormat(dateString) {
            if (!dateString) return '';
            
            // Check if the date is already in YYYY-MM-DD format
            if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                return dateString;
            }
            
            const parts = dateString.split('/');
            if (parts.length !== 3) return '';
            
            return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
        }
        
        // Function to show "no results" message
        function showNoResultsMessage(hasVisibleRows) {
            // Remove existing no results message if it exists
            const existingMessage = document.querySelector('.no-results-message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // If no visible rows, add a message
            if (!hasVisibleRows) {
                const tableBody = document.querySelector('.data-table tbody');
                const noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-message';
                noResultsRow.innerHTML = `
                    <td colspan="9" class="text-center py-4">
                        <div class="d-flex flex-column align-items-center">
                            <i class="bi bi-search text-muted" style="font-size: 2.5rem;"></i>
                            <h5 class="mt-3 mb-1">No matching records found</h5>
                            <p class="text-muted">Try adjusting your filters or reset filters to see all records</p>
                        </div>
                    </td>
                `;
                tableBody.appendChild(noResultsRow);
            }
        }
        
        // Add event listeners to filter controls
        filterProjectType.addEventListener('change', filterTable);
        filterStartDate.addEventListener('change', filterTable);
        filterEndDate.addEventListener('change', filterTable);
        filterClientName.addEventListener('change', filterTable);
        filterManager.addEventListener('change', filterTable);
        
        // Reset filters button
        resetFilterBtn.addEventListener('click', function() {
            // Reset all filter values
            filterProjectType.value = '';
            filterStartDate.value = '2025-06-09';
            filterEndDate.value = '2025-07-09';
            filterClientName.value = '';
            filterManager.value = '';
            
            // Reset table to show all rows
            tableRows.forEach(row => {
                row.style.display = '';
            });
            
            // Remove no results message if it exists
            const existingMessage = document.querySelector('.no-results-message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // Show toast notification
            showToast('Filters have been reset', 'info');
            
            // Add animation to indicate reset
            resetFilterBtn.classList.add('btn-animated');
            setTimeout(() => {
                resetFilterBtn.classList.remove('btn-animated');
            }, 500);
        });
        
        // Add input event listeners for more responsive filtering
        filterProjectType.addEventListener('input', filterTable);
        filterStartDate.addEventListener('input', filterTable);
        filterEndDate.addEventListener('input', filterTable);
        filterClientName.addEventListener('input', filterTable);
        filterClientName.addEventListener('change', filterTable); // Add change event for dropdown selection
        filterManager.addEventListener('input', filterTable);
        
        // Function to update active filter count badge
        function updateFilterCount() {
            let count = 0;
            
            if (filterProjectType.value) count++;
            if (filterClientName.value) count++;
            if (filterManager.value) count++;
            
            // For date range, only count if different from default values
            const defaultStartDate = '2025-06-09';
            const defaultEndDate = '2025-07-09';
            
            if (filterStartDate.value && filterStartDate.value !== defaultStartDate) count++;
            if (filterEndDate.value && filterEndDate.value !== defaultEndDate) count++;
            
            // Update badge
            activeFilterCount.textContent = count;
            
            if (count > 0) {
                activeFilterCount.classList.add('active');
            } else {
                activeFilterCount.classList.remove('active');
            }
            
            // Show notification if filtering by client
            if (filterClientName.value) {
                // Count visible rows to display in notification
                const visibleRows = Array.from(tableRows).filter(row => 
                    window.getComputedStyle(row).display !== 'none' && 
                    row.className !== 'no-results-message'
                ).length;
                
                if (visibleRows > 0) {
                    showToast(`Showing ${visibleRows} projects for client: ${filterClientName.options[filterClientName.selectedIndex].text}`, 'info');
                }
            }
            
            return count;
        }
        
        // Initialize filter count on page load
        updateFilterCount();
        
        // Apply filters on page load if any are set
        if (filterClientName.value || filterProjectType.value || filterManager.value) {
            console.log("Initial filter values detected, applying filters on load");
            filterTable();
        }
        
        // Add event listener for the clear client filter button
        document.getElementById('clearClientFilter').addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event bubbling
            filterClientName.value = '';
            filterTable();
            showToast('Client filter cleared', 'info');
        });
        
        // Project Stages Modal Functionality
        const projectStagesModal = document.getElementById('projectStagesModal');
        const viewStagesLinks = document.querySelectorAll('.view-stages');
        
        // Global variable to store project data
        let currentProjectData = null;
        
        // Store the project stages data
        const projectStagesData = <?php echo json_encode($projectGroups); ?>;
        
        // Add event listeners to "view stages" links
        viewStagesLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const projectName = this.getAttribute('data-project-name');
                showProjectStages(projectName);
            });
        });
        
        // Function to show project stages
        function showProjectStages(projectName) {
            // Set modal title
            document.getElementById('projectStagesTitle').textContent = projectName;
            
            // Show spinner, hide table and error message
            document.getElementById('stagesSpinner').style.display = 'block';
            document.getElementById('stagesTableContainer').style.display = 'none';
            document.getElementById('stagesErrorMessage').classList.add('d-none');
            
            // Get project data from the stored array
            if (projectStagesData[projectName]) {
                const stages = projectStagesData[projectName].stages;
                currentProjectData = stages;
                
                // Set stages count
                document.getElementById('stagesCount').textContent = `${stages.length} stages`;
                
                // Clear previous content
                const tableBody = document.getElementById('projectStagesTableBody');
                tableBody.innerHTML = '';
                
                // Add stages to table
                stages.forEach((stage, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <span class="badge bg-primary">Stage ${stage.stage_number}</span>
                        </td>
                        <td>${new Date(stage.stage_date).toLocaleDateString()}</td>
                        <td>${stage.client_name}</td>
                        <td>${formatCurrency(stage.total_paid_amount || 0)}</td>
                        <td>
                            <small class="text-muted">${stage.payment_details || 'No payment details'}</small>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary edit-btn-modal" data-transaction-id="${stage.transaction_id}"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-info view-btn-modal" data-transaction-id="${stage.transaction_id}" data-project-id="${stage.project_id || 0}"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-outline-danger delete-btn-modal" data-transaction-id="${stage.transaction_id}"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
                
                // Hide spinner, show table
                document.getElementById('stagesSpinner').style.display = 'none';
                document.getElementById('stagesTableContainer').style.display = 'block';
                
                // Add event listeners to the buttons in the modal
                addModalButtonListeners();
                
            } else {
                // Error case - should not happen if data is properly stored
                document.getElementById('stagesSpinner').style.display = 'none';
                document.getElementById('stagesErrorMessage').classList.remove('d-none');
                document.getElementById('stagesErrorMessage').textContent = 'Error: Project data not found.';
            }
        }
        
        // Add event listeners to buttons in the modal
        function addModalButtonListeners() {
            // View buttons in modal
            document.querySelectorAll('.view-btn-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    const transactionId = this.getAttribute('data-transaction-id');
                    const projectId = this.getAttribute('data-project-id');
                    
                    // Close the stages modal first
                    const stagesModalInstance = bootstrap.Modal.getInstance(projectStagesModal);
                    stagesModalInstance.hide();
                    
                    // Then show the view modal
                    setTimeout(() => {
                        viewProjectDetails(transactionId, projectId);
                    }, 500);
                });
            });
            
            // Edit buttons in modal
            document.querySelectorAll('.edit-btn-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    const transactionId = this.getAttribute('data-transaction-id');
                    
                    // Close the stages modal first
                    const stagesModalInstance = bootstrap.Modal.getInstance(projectStagesModal);
                    stagesModalInstance.hide();
                    
                    // Then fetch project details for editing
                    setTimeout(() => {
                        fetchProjectDetails(transactionId);
                    }, 500);
                });
            });
            
            // Delete buttons in modal
            document.querySelectorAll('.delete-btn-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    const transactionId = this.getAttribute('data-transaction-id');
                    
                    // Close the stages modal first
                    const stagesModalInstance = bootstrap.Modal.getInstance(projectStagesModal);
                    stagesModalInstance.hide();
                    
                    // Then show the delete confirmation modal
                    setTimeout(() => {
                        document.getElementById('deleteTransactionId').value = transactionId;
                        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                        deleteModal.show();
                    }, 500);
                });
            });
        }
        
        // Print all stages functionality
        document.getElementById('printAllStages').addEventListener('click', function() {
            if (currentProjectData) {
                printAllProjectStages(currentProjectData);
            }
        });
        
        // Function to print all stages of a project
        function printAllProjectStages(stages) {
            if (!stages || !stages.length) return;
            
            // Get project name from the first stage
            const projectName = stages[0].project_name;
            const clientName = stages[0].client_name;
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Calculate project totals
            let totalPaid = 0;
            stages.forEach(stage => {
                if (stage.total_paid_amount) {
                    totalPaid += parseFloat(stage.total_paid_amount);
                }
            });
            
            // Build HTML content for print
            const printContent = `
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>All Stages - ${projectName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .project-info { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
                        .project-info h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
                        .info-row { display: flex; margin-bottom: 10px; }
                        .info-label { font-weight: bold; width: 150px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .stage-heading { background-color: #f8f9fa; padding: 10px; margin: 15px 0 5px 0; border-radius: 5px; font-weight: bold; }
                        .summary { margin-top: 30px; border-top: 2px solid #333; padding-top: 15px; }
                        .text-right { text-align: right; }
                        .company-footer { margin-top: 50px; text-align: center; font-size: 0.9em; color: #666; }
                        @media print { 
                            body { margin: 0.5cm; } 
                            .no-print { display: none; }
                            button { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Project Stages Report</h1>
                        <p>Date: ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <div class="project-info">
                        <h2>Project Information</h2>
                        <div class="info-row">
                            <div class="info-label">Project Name:</div>
                            <div>${projectName}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Client Name:</div>
                            <div>${clientName}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Total Stages:</div>
                            <div>${stages.length}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Total Amount Paid:</div>
                            <div>${formatCurrency(totalPaid)}</div>
                        </div>
                    </div>
                    
                    <h2>All Project Stages</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Stage</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Payment Details</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${stages.map(stage => `
                                <tr>
                                    <td>Stage ${stage.stage_number}</td>
                                    <td>${new Date(stage.stage_date).toLocaleDateString()}</td>
                                    <td>${formatCurrency(stage.total_paid_amount || 0)}</td>
                                    <td>${stage.payment_details || 'No payment details'}</td>
                                    <td>${stage.stage_notes || '-'}</td>
                                </tr>
                            `).join('')}
                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                <td colspan="2">Total</td>
                                <td>${formatCurrency(totalPaid)}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="company-footer">
                        <p>This is a computer-generated document. No signature is required.</p>
                        <p>© ${new Date().getFullYear()} Your Company Name. All rights reserved.</p>
                    </div>
                    
                    <div class="no-print" style="text-align: center; margin-top: 30px;">
                        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Print Report
                        </button>
                    </div>
                </body>
                </html>
            `;
            
            // Write to the new window and print
            printWindow.document.open();
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Give it a moment to load before triggering print
            setTimeout(() => {
                printWindow.focus();
                // printWindow.print(); // Auto-print is optional
            }, 500);
        }
        
        // Function to show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('successToast');
            const toastMessage = document.getElementById('toastMessage');
            
            // Set message
            if (toastMessage) {
                toastMessage.textContent = message;
            }
            
            // Set toast type/color
            toast.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info');
            switch (type) {
                case 'error':
                    toast.classList.add('bg-danger');
                    break;
                case 'warning':
                    toast.classList.add('bg-warning');
                    break;
                case 'info':
                    toast.classList.add('bg-info');
                    break;
                default:
                    toast.classList.add('bg-success');
            }
            
            // Initialize and show toast
            const toastInstance = new bootstrap.Toast(toast);
            toastInstance.show();
        }
        
        // View Project Functionality
        const viewButtons = document.querySelectorAll('.view-btn');
        const viewProjectModal = document.getElementById('viewProjectModal');
        
        // Add event listeners to all view buttons
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const transactionId = this.getAttribute('data-transaction-id');
                const projectId = this.getAttribute('data-project-id');
                viewProjectDetails(transactionId, projectId);
            });
        });
        
        // Function to view project details
        function viewProjectDetails(transactionId, projectId) {
            // Show loading state
            const viewBtn = document.querySelector(`.view-btn[data-transaction-id="${transactionId}"]`);
            const originalBtnHtml = viewBtn.innerHTML;
            viewBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            viewBtn.disabled = true;
            
            // Reset modal content
            document.getElementById('viewProjectName').textContent = '';
            document.getElementById('viewClientName').textContent = '';
            document.getElementById('viewProjectType').textContent = '';
            document.getElementById('viewTotalProjectAmount').textContent = '';
            document.getElementById('viewRemainingAmount').textContent = '';
            document.getElementById('viewProjectAmountRow').classList.add('d-none');
            
            // Show loading indicator for stages
            document.getElementById('viewStagesAccordion').innerHTML = `
                <div id="loadingStages" class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading project stages...</p>
                </div>
            `;
            
            // Show modal
            const modal = new bootstrap.Modal(viewProjectModal);
            modal.show();
            
            // Fetch project details using AJAX with view_all=1 parameter
            fetch(`ajax_handlers/get_project_details.php?transaction_id=${transactionId}&view_all=1`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server responded with status ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Reset button state
                    viewBtn.innerHTML = originalBtnHtml;
                    viewBtn.disabled = false;
                    
                    if (data.success) {
                        // Populate project details
                        document.getElementById('viewProjectName').textContent = data.project.project_name || 'N/A';
                        document.getElementById('viewClientName').textContent = data.project.client_name || 'N/A';
                        document.getElementById('viewProjectType').textContent = (data.project.project_type || 'N/A').toUpperCase();
                        
                        // Show project amount information if available
                        if (data.project_total_amount) {
                            document.getElementById('viewProjectAmountRow').classList.remove('d-none');
                            document.getElementById('viewTotalProjectAmount').textContent = formatCurrency(data.project_total_amount);
                            document.getElementById('viewRemainingAmount').textContent = formatCurrency(data.project_remaining_amount);
                        } else {
                            document.getElementById('viewProjectAmountRow').classList.add('d-none');
                        }
                        
                        // Build stages accordion
                        let stagesHtml = '';
                        
                        // Check if there are stages to display
                        if (data.all_stages && data.all_stages.length > 0) {
                            data.all_stages.forEach((stage, index) => {
                                const stageId = `stage-${stage.transaction_id}`;
                                const isActive = index === 0 ? 'show' : ''; // First one is open
                                const stageTotalPaid = parseFloat(stage.stage_total_paid || 0);
                                
                                // Format the stage date
                                const stageDate = stage.stage_date ? new Date(stage.stage_date).toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                }) : 'N/A';
                                
                                stagesHtml += `
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading-${stageId}">
                                            <button class="accordion-button ${index === 0 ? '' : 'collapsed'}" type="button" 
                                                    data-bs-toggle="collapse" data-bs-target="#collapse-${stageId}" 
                                                    aria-expanded="${index === 0 ? 'true' : 'false'}" aria-controls="collapse-${stageId}">
                                                <div class="d-flex align-items-center justify-content-between w-100">
                                                    <div>
                                                        <span class="badge bg-primary me-2">Stage ${stage.stage_number}</span>
                                                        <span class="fw-medium">${stageDate}</span>
                                                    </div>
                                                    <span class="badge bg-success ms-2">${formatCurrency(stageTotalPaid)}</span>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="collapse-${stageId}" class="accordion-collapse collapse ${isActive}" 
                                            aria-labelledby="heading-${stageId}" data-bs-parent="#viewStagesAccordion">
                                            <div class="accordion-body">
                                                <h6 class="fw-bold">Payment Details</h6>
                                                ${stage.payments && stage.payments.length > 0 ? 
                                                    `<div class="table-responsive">
                                                        <table class="table table-sm table-bordered">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Date</th>
                                                                    <th>Amount</th>
                                                                    <th>Payment Mode</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                ${stage.payments.map(payment => `
                                                                    <tr>
                                                                        <td>${payment.payment_date ? new Date(payment.payment_date).toLocaleDateString() : 'N/A'}</td>
                                                                        <td>${formatCurrency(payment.payment_amount)}</td>
                                                                        <td><span class="badge bg-light text-dark">${formatPaymentMode(payment.payment_mode)}</span></td>
                                                                    </tr>
                                                                `).join('')}
                                                                <tr class="table-success">
                                                                    <td colspan="1"><strong>Total</strong></td>
                                                                    <td colspan="2"><strong>${formatCurrency(stageTotalPaid)}</strong></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>` : 
                                                    '<p class="text-muted">No payment records found for this stage.</p>'
                                                }
                                                
                                                ${stage.stage_notes ? 
                                                    `<div class="mt-3">
                                                        <h6 class="fw-bold">Notes</h6>
                                                        <p class="fst-italic text-muted">${stage.stage_notes}</p>
                                                    </div>` : ''
                                                }
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            // Add project summary at the end
                            stagesHtml += `
                                <div class="card mt-3">
                                    <div class="card-body bg-light">
                                        <h5 class="card-title">Project Summary</h5>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p class="mb-1 text-muted">Total Stages</p>
                                                <h4 class="mb-0">${data.all_stages.length}</h4>
                                            </div>
                                            <div class="col-md-4">
                                                <p class="mb-1 text-muted">Total Paid</p>
                                                <h4 class="mb-0">${formatCurrency(data.project_total_paid || 0)}</h4>
                                            </div>
                                            ${data.project_total_amount ? 
                                                `<div class="col-md-4">
                                                    <p class="mb-1 text-muted">Remaining</p>
                                                    <h4 class="mb-0">${formatCurrency(data.project_remaining_amount || 0)}</h4>
                                                </div>` : ''
                                            }
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Update stages container
                            document.getElementById('viewStagesAccordion').innerHTML = stagesHtml;
                        } else {
                            document.getElementById('viewStagesAccordion').innerHTML = `
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No stages found for this project.
                                </div>
                            `;
                        }
                        
                        // Set up print functionality
                        document.getElementById('printProjectDetails').addEventListener('click', function() {
                            printProjectDetails(data);
                        });
                    } else {
                        // Show error message
                        document.getElementById('viewStagesAccordion').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                ${data.message || 'Failed to load project details.'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    // Reset button state
                    viewBtn.innerHTML = originalBtnHtml;
                    viewBtn.disabled = false;
                    
                    // Show error message
                    document.getElementById('viewStagesAccordion').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading project details: ${error.message}
                        </div>
                    `;
                    console.error('Error fetching project details:', error);
                });
        }
        
        // Helper function to format payment mode
        function formatPaymentMode(mode) {
            if (!mode) return 'N/A';
            
            const modes = {
                'cash': 'Cash',
                'upi': 'UPI',
                'net_banking': 'Net Banking',
                'cheque': 'Cheque',
                'credit_card': 'Credit Card'
            };
            
            return modes[mode] || mode.charAt(0).toUpperCase() + mode.slice(1).replace('_', ' ');
        }
        
        // Function to print project details
        function printProjectDetails(data) {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Calculate project totals
            let totalPaid = 0;
            if (data.all_stages) {
                data.all_stages.forEach(stage => {
                    if (stage.stage_total_paid) {
                        totalPaid += parseFloat(stage.stage_total_paid);
                    }
                });
            }
            
            // Build HTML content for print
            const printContent = `
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Project Details - ${data.project.project_name}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .project-info { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
                        .project-info h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
                        .info-row { display: flex; margin-bottom: 10px; }
                        .info-label { font-weight: bold; width: 150px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .stage-heading { background-color: #f8f9fa; padding: 10px; margin: 15px 0 5px 0; border-radius: 5px; font-weight: bold; }
                        .summary { margin-top: 30px; border-top: 2px solid #333; padding-top: 15px; }
                        .text-right { text-align: right; }
                        .company-footer { margin-top: 50px; text-align: center; font-size: 0.9em; color: #666; }
                        @media print { 
                            body { margin: 0.5cm; } 
                            .no-print { display: none; }
                            button { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Project Payment Report</h1>
                        <p>Date: ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <div class="project-info">
                        <h2>Project Information</h2>
                        <div class="info-row">
                            <div class="info-label">Project Name:</div>
                            <div>${data.project.project_name || 'N/A'}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Client Name:</div>
                            <div>${data.project.client_name || 'N/A'}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Project Type:</div>
                            <div>${(data.project.project_type || 'N/A').toUpperCase()}</div>
                        </div>
                        ${data.project_total_amount ? 
                            `<div class="info-row">
                                <div class="info-label">Total Project Value:</div>
                                <div>${formatCurrency(data.project_total_amount)}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Amount Paid:</div>
                                <div>${formatCurrency(totalPaid)}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Remaining Amount:</div>
                                <div>${formatCurrency(Math.max(0, data.project_total_amount - totalPaid))}</div>
                            </div>` : ''
                        }
                    </div>
                    
                    <h2>Project Stages & Payments</h2>
                    ${data.all_stages && data.all_stages.length > 0 ? 
                        data.all_stages.map(stage => {
                            const stageTotalPaid = parseFloat(stage.stage_total_paid || 0);
                            const stageDate = stage.stage_date ? new Date(stage.stage_date).toLocaleDateString() : 'N/A';
                            
                            return `
                                <div class="stage-section">
                                    <div class="stage-heading">Stage ${stage.stage_number} - ${stageDate}</div>
                                    
                                    ${stage.payments && stage.payments.length > 0 ? 
                                        `<table>
                                            <thead>
                                                <tr>
                                                    <th>Payment Date</th>
                                                    <th>Payment Mode</th>
                                                    <th class="text-right">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${stage.payments.map(payment => `
                                                    <tr>
                                                        <td>${payment.payment_date ? new Date(payment.payment_date).toLocaleDateString() : 'N/A'}</td>
                                                        <td>${formatPaymentMode(payment.payment_mode)}</td>
                                                        <td class="text-right">${formatCurrency(payment.payment_amount)}</td>
                                                    </tr>
                                                `).join('')}
                                                <tr>
                                                    <td colspan="2"><strong>Stage Total</strong></td>
                                                    <td class="text-right"><strong>${formatCurrency(stageTotalPaid)}</strong></td>
                                                </tr>
                                            </tbody>
                                        </table>` : 
                                        '<p>No payment records found for this stage.</p>'
                                    }
                                    
                                    ${stage.stage_notes ? `<p><strong>Notes:</strong> ${stage.stage_notes}</p>` : ''}
                                </div>
                            `;
                        }).join('') : 
                        '<p>No stages found for this project.</p>'
                    }
                    
                    <div class="summary">
                        <h2>Project Summary</h2>
                        <div class="info-row">
                            <div class="info-label">Total Stages:</div>
                            <div>${data.all_stages ? data.all_stages.length : 0}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Total Paid Amount:</div>
                            <div>${formatCurrency(totalPaid)}</div>
                        </div>
                        ${data.project_total_amount ? 
                            `<div class="info-row">
                                <div class="info-label">Completion Percentage:</div>
                                <div>${Math.round((totalPaid / data.project_total_amount) * 100)}%</div>
                            </div>` : ''
                        }
                    </div>
                    
                    <div class="company-footer">
                        <p>This is a computer-generated document. No signature is required.</p>
                        <p>© ${new Date().getFullYear()} Your Company Name. All rights reserved.</p>
                    </div>
                    
                    <div class="no-print" style="text-align: center; margin-top: 30px;">
                        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Print Report
                        </button>
                    </div>
                </body>
                </html>
            `;
            
            // Write to the new window and print
            printWindow.document.open();
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Give it a moment to load before triggering print
            setTimeout(() => {
                printWindow.focus();
                // printWindow.print(); // Auto-print is optional
            }, 500);
        }
        
        // Edit Project Functionality
        const editButtons = document.querySelectorAll('.edit-btn');
        const editProjectModal = document.getElementById('editProjectModal');
        let editPaymentCount = 0;
        
        // Add event listeners to all edit buttons
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const transactionId = this.getAttribute('data-transaction-id');
                fetchProjectDetails(transactionId);
            });
        });
        
        // Function to fetch project details for editing
        function fetchProjectDetails(transactionId) {
            // Show loading state
            const editBtn = document.querySelector(`.edit-btn[data-transaction-id="${transactionId}"]`);
            const originalBtnHtml = editBtn.innerHTML;
            editBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            editBtn.disabled = true;
            
            // Fetch project details using AJAX
            fetch(`ajax_handlers/get_project_details.php?transaction_id=${transactionId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server responded with status ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        populateEditForm(data.project, data.payments);
                        
                        // Show the modal
                        const modal = new bootstrap.Modal(editProjectModal);
                        modal.show();
                    } else {
                        showToast(data.message || 'Failed to load project details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching project details:', error);
                    showToast('Error loading project details: ' + error.message, 'error');
                })
                .finally(() => {
                    // Reset button state
                    editBtn.innerHTML = originalBtnHtml;
                    editBtn.disabled = false;
                });
        }
        
        // Function to populate the edit form with project data
        function populateEditForm(project, payments) {
            // Clear existing payment entries
            const paymentEntriesContainer = document.getElementById('editPaymentEntries');
            paymentEntriesContainer.innerHTML = '';
            editPaymentCount = 0;
            
            // Reset new stage container
            document.getElementById('newStageContainer').classList.add('d-none');
            document.getElementById('newStageNumber').value = '';
            document.getElementById('newStageDate').value = '';
            document.getElementById('newStageNotes').value = '';
            
            // Set current date for new stage date
            document.getElementById('newStageDate').valueAsDate = new Date();
            
            // Set form fields
            document.getElementById('editTransactionId').value = project.transaction_id;
            document.getElementById('editProjectId').value = project.project_id;
            document.getElementById('editProjectName').value = project.project_name;
            document.getElementById('editProjectType').value = project.project_type;
            document.getElementById('editClientName').value = project.client_name;
            document.getElementById('editStageNumber').value = project.stage_number;
            document.getElementById('editStageDate').value = project.stage_date;
            document.getElementById('editStageNotes').value = project.stage_notes || '';
            
            // Set suggested next stage number
            document.getElementById('newStageNumber').value = parseInt(project.stage_number) + 1;
            
            // Handle total project amount if available
            if (project.total_project_amount) {
                document.getElementById('editRemainingAmountToggle').checked = true;
                document.getElementById('editRemainingAmountInfo').classList.remove('d-none');
                document.getElementById('editTotalProjectAmount').value = project.total_project_amount;
            } else {
                document.getElementById('editRemainingAmountToggle').checked = false;
                document.getElementById('editRemainingAmountInfo').classList.add('d-none');
                document.getElementById('editTotalProjectAmount').value = '';
            }
            
            // Add payment entries
            if (payments && payments.length > 0) {
                let totalAmount = 0;
                
                payments.forEach((payment, index) => {
                    addEditPaymentEntry(payment);
                    totalAmount += parseFloat(payment.payment_amount);
                });
                
                // Update stage total
                document.getElementById('editStageTotal').textContent = formatCurrency(totalAmount);
            } else {
                // Add at least one empty payment entry
                addEditPaymentEntry();
                document.getElementById('editStageTotal').textContent = '₹0.00';
            }
            
            // Set up event listener for remaining amount toggle
            document.getElementById('editRemainingAmountToggle').addEventListener('change', function() {
                const infoBox = document.getElementById('editRemainingAmountInfo');
                if (this.checked) {
                    infoBox.classList.remove('d-none');
                } else {
                    infoBox.classList.add('d-none');
                    document.getElementById('editTotalProjectAmount').value = '';
                }
            });
            
            // Set up event listener for add payment button
            document.getElementById('editAddPaymentBtn').addEventListener('click', function() {
                addEditPaymentEntry();
                updateEditStageTotal();
            });
            
            // Set up event listener for "Add New Stage" button
            document.getElementById('addNewStageBtn').addEventListener('click', function() {
                document.getElementById('newStageContainer').classList.remove('d-none');
                this.classList.add('d-none');
                
                // Set current date for new stage date field if not already set
                const newStageDateField = document.getElementById('newStageDate');
                if (!newStageDateField.value) {
                    newStageDateField.valueAsDate = new Date();
                }
                
                // Initialize new stage total
                updateNewStageTotal();
            });
            
            // Set up event listener for "Cancel New Stage" button
            document.getElementById('cancelNewStageBtn').addEventListener('click', function() {
                document.getElementById('newStageContainer').classList.add('d-none');
                document.getElementById('addNewStageBtn').classList.remove('d-none');
            });
            
            // Set up event listener for "Add Payment" button in new stage
            document.getElementById('newStageAddPaymentBtn').addEventListener('click', function() {
                addNewStagePaymentEntry();
                updateNewStageTotal();
            });
            
            // Add event listener to the initial payment amount in new stage
            const initialNewPaymentAmount = document.querySelector('.new-payment-amount');
            if (initialNewPaymentAmount) {
                initialNewPaymentAmount.addEventListener('input', updateNewStageTotal);
            }
        }
        
        // Function to add a payment entry to the edit form
        function addEditPaymentEntry(paymentData = null) {
            const paymentEntriesContainer = document.getElementById('editPaymentEntries');
            
            const paymentHtml = `
                <div class="payment-entry card mb-2">
                    <div class="card-body p-3 position-relative">
                        ${editPaymentCount > 0 ? `<button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-payment" data-index="${editPaymentCount}"></button>` : ''}
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Date</label>
                                <input type="date" class="form-control form-control-sm edit-payment-date" 
                                       name="payments[${editPaymentCount}][date]" 
                                       value="${paymentData ? paymentData.payment_date : ''}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Amount (₹)</label>
                                <input type="number" class="form-control form-control-sm edit-payment-amount" 
                                       name="payments[${editPaymentCount}][amount]" 
                                       placeholder="0.00" min="0" step="0.01" 
                                       value="${paymentData ? paymentData.payment_amount : ''}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Payment Mode</label>
                                <select class="form-select form-select-sm edit-payment-mode" 
                                        name="payments[${editPaymentCount}][payment_mode]" required>
                                    <option value="" ${!paymentData ? 'selected' : ''} disabled>Select mode</option>
                                    <option value="cash" ${paymentData && paymentData.payment_mode === 'cash' ? 'selected' : ''}>Cash</option>
                                    <option value="upi" ${paymentData && paymentData.payment_mode === 'upi' ? 'selected' : ''}>UPI</option>
                                    <option value="net_banking" ${paymentData && paymentData.payment_mode === 'net_banking' ? 'selected' : ''}>Net Banking</option>
                                    <option value="cheque" ${paymentData && paymentData.payment_mode === 'cheque' ? 'selected' : ''}>Cheque</option>
                                    <option value="credit_card" ${paymentData && paymentData.payment_mode === 'credit_card' ? 'selected' : ''}>Credit Card</option>
                                </select>
                            </div>
                            ${paymentData && paymentData.payment_id ? `<input type="hidden" name="payments[${editPaymentCount}][payment_id]" value="${paymentData.payment_id}">` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            paymentEntriesContainer.insertAdjacentHTML('beforeend', paymentHtml);
            
            // Add event listeners to the new payment entry
            const newPaymentEntry = paymentEntriesContainer.lastElementChild;
            
            // Add event listener to the remove button if it exists
            const removeBtn = newPaymentEntry.querySelector('.remove-payment');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    newPaymentEntry.remove();
                    updateEditStageTotal();
                });
            }
            
            // Add event listener to the amount input
            const amountInput = newPaymentEntry.querySelector('.edit-payment-amount');
            if (amountInput) {
                amountInput.addEventListener('input', updateEditStageTotal);
            }
            
            editPaymentCount++;
        }
        
        // Function to update the edit stage total
        function updateEditStageTotal() {
            const amountInputs = document.querySelectorAll('.edit-payment-amount');
            let total = 0;
            
            amountInputs.forEach(input => {
                const amount = parseFloat(input.value) || 0;
                total += amount;
            });
            
            document.getElementById('editStageTotal').textContent = formatCurrency(total);
        }
        
        // Function to add a payment entry to the new stage
        function addNewStagePaymentEntry() {
            const paymentEntriesContainer = document.getElementById('newStagePaymentEntries');
            const paymentCount = paymentEntriesContainer.querySelectorAll('.payment-entry').length;
            
            const paymentHtml = `
                <div class="payment-entry card mb-2">
                    <div class="card-body p-3 position-relative">
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-new-payment"></button>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Date</label>
                                <input type="date" class="form-control form-control-sm new-payment-date" 
                                       name="new_stage_payment_date_${paymentCount}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Amount (₹)</label>
                                <input type="number" class="form-control form-control-sm new-payment-amount" 
                                       name="new_stage_payment_amount_${paymentCount}" 
                                       placeholder="0.00" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Payment Mode</label>
                                <select class="form-select form-select-sm new-payment-mode" 
                                        name="new_stage_payment_mode_${paymentCount}" required>
                                    <option value="" selected disabled>Select mode</option>
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI</option>
                                    <option value="net_banking">Net Banking</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="credit_card">Credit Card</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            paymentEntriesContainer.insertAdjacentHTML('beforeend', paymentHtml);
            
            // Add event listeners to the new payment entry
            const newPaymentEntry = paymentEntriesContainer.lastElementChild;
            
            // Add event listener to the remove button
            const removeBtn = newPaymentEntry.querySelector('.remove-new-payment');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    newPaymentEntry.remove();
                    updateNewStageTotal();
                });
            }
            
            // Add event listener to the amount input
            const amountInput = newPaymentEntry.querySelector('.new-payment-amount');
            if (amountInput) {
                amountInput.addEventListener('input', updateNewStageTotal);
            }
            
            // Set current date for the new payment date field
            const dateInput = newPaymentEntry.querySelector('.new-payment-date');
            if (dateInput) {
                dateInput.valueAsDate = new Date();
            }
        }
        
        // Function to update the new stage total
        function updateNewStageTotal() {
            const amountInputs = document.querySelectorAll('.new-payment-amount');
            let total = 0;
            
            amountInputs.forEach(input => {
                const amount = parseFloat(input.value) || 0;
                total += amount;
            });
            
            document.getElementById('newStageTotal').textContent = formatCurrency(total);
        }
        
        // Function to format currency
        function formatCurrency(amount) {
            return '₹' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        // Handle edit form submission
        const editProjectForm = document.getElementById('editProjectForm');
        if (editProjectForm) {
            editProjectForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading state
                try {
                    // Find submit button by targeting the button with form attribute set to this form's id
                    const submitBtn = document.querySelector(`button[form="${this.id}"]`);
                    if (!submitBtn) {
                        console.error('Submit button not found for the form');
                        return;
                    }
                    
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Updating...';
                    submitBtn.disabled = true;
                    
                    // Store for later use in promises
                    this._submitBtn = submitBtn;
                    this._originalBtnText = originalBtnText;
                } catch (err) {
                    console.error('Error accessing submit button:', err);
                    return;
                }
                
                // Hide any existing alerts
                const editSuccessAlert = document.getElementById('editSuccessAlert');
                const editErrorAlert = document.getElementById('editErrorAlert');
                
                if (editSuccessAlert) editSuccessAlert.classList.add('d-none');
                if (editErrorAlert) editErrorAlert.classList.add('d-none');
                
                // Collect form data
                const formData = new FormData();
                
                // Add project details - safely access DOM elements
                function safeGetValue(id) {
                    const element = document.getElementById(id);
                    return element ? element.value : '';
                }
                
                function safeIsChecked(id) {
                    const element = document.getElementById(id);
                    return element ? element.checked : false;
                }
                
                formData.append('transaction_id', safeGetValue('editTransactionId'));
                formData.append('project_id', safeGetValue('editProjectId'));
                formData.append('project_name', safeGetValue('editProjectName'));
                formData.append('project_type', safeGetValue('editProjectType'));
                formData.append('client_name', safeGetValue('editClientName'));
                formData.append('stage_number', safeGetValue('editStageNumber'));
                formData.append('stage_date', safeGetValue('editStageDate'));
                formData.append('stage_notes', safeGetValue('editStageNotes'));
                
                // Add total project amount if toggle is checked
                if (safeIsChecked('editRemainingAmountToggle')) {
                    formData.append('total_project_amount', safeGetValue('editTotalProjectAmount'));
                }
                
                // Collect payment data for existing stage
                const payments = [];
                const paymentEntries = document.querySelectorAll('#editPaymentEntries .payment-entry');
                
                paymentEntries.forEach((entry, index) => {
                    const paymentId = entry.querySelector('input[name^="payments["][name$="][payment_id]"]')?.value || null;
                    const date = entry.querySelector('.edit-payment-date').value;
                    const amount = entry.querySelector('.edit-payment-amount').value;
                    const mode = entry.querySelector('.edit-payment-mode').value;
                    
                    if (date && amount && mode) {
                        payments.push({
                            payment_id: paymentId,
                            payment_date: date,
                            payment_amount: amount,
                            payment_mode: mode
                        });
                    }
                });
                
                formData.append('payments', JSON.stringify(payments));
                
                // Check if new stage is being added
                const newStageContainer = document.getElementById('newStageContainer');
                if (!newStageContainer.classList.contains('d-none')) {
                    // Get new stage data
                    const newStageData = {
                        stage_number: safeGetValue('newStageNumber'),
                        stage_date: safeGetValue('newStageDate'),
                        stage_notes: safeGetValue('newStageNotes'),
                        payments: []
                    };
                    
                    // Collect new stage payment data
                    const newPaymentEntries = document.querySelectorAll('#newStagePaymentEntries .payment-entry');
                    
                    newPaymentEntries.forEach((entry, index) => {
                        const date = entry.querySelector('.new-payment-date').value;
                        const amount = entry.querySelector('.new-payment-amount').value;
                        const mode = entry.querySelector('.new-payment-mode').value;
                        
                        if (date && amount && mode) {
                            newStageData.payments.push({
                                payment_date: date,
                                payment_amount: amount,
                                payment_mode: mode
                            });
                        }
                    });
                    
                    // Only add new stage data if there's at least one payment
                    if (newStageData.payments.length > 0) {
                        formData.append('new_stage', JSON.stringify(newStageData));
                    }
                }
                
                // Send AJAX request
                fetch('ajax_handlers/update_project_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server responded with status ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Reset button state
                    if (this._submitBtn) {
                        this._submitBtn.innerHTML = this._originalBtnText;
                        this._submitBtn.disabled = false;
                    }
                    
                    if (data.success) {
                        // Show success message
                        const editSuccessAlert = document.getElementById('editSuccessAlert');
                        if (editSuccessAlert) {
                            editSuccessAlert.classList.remove('d-none');
                            editSuccessAlert.classList.add('show');
                        }
                        
                        // Show appropriate success message
                        const successMessage = data.affected_stages && data.affected_stages > 0 ? 
                            `Project updated successfully! Client and project details updated for all ${data.affected_stages + 1} stages.` : 
                            'Project updated successfully!';
                        
                        // Show toast notification
                        showToast(successMessage);
                        
                        // Close modal and reload page after a delay
                        setTimeout(() => {
                            const modal = bootstrap.Modal.getInstance(editProjectModal);
                            if (modal) {
                                modal.hide();
                            }
                            
                            // Reload the page to show updated data
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        const errorMessage = document.getElementById('editErrorMessage');
                        if (errorMessage) {
                            errorMessage.textContent = data.message || 'An error occurred while updating.';
                        }
                        
                        const editErrorAlert = document.getElementById('editErrorAlert');
                        if (editErrorAlert) {
                            editErrorAlert.classList.remove('d-none');
                            editErrorAlert.classList.add('show');
                        }
                        
                        // Show toast notification
                        showToast(data.message || 'Failed to update project', 'error');
                    }
                })
                .catch(error => {
                    // Reset button state
                    if (this._submitBtn) {
                        this._submitBtn.innerHTML = this._originalBtnText;
                        this._submitBtn.disabled = false;
                    }
                    
                    // Show error message
                    const errorMessage = document.getElementById('editErrorMessage');
                    if (errorMessage) {
                        errorMessage.textContent = 'Error: ' + error.message;
                    }
                    
                    const editErrorAlert = document.getElementById('editErrorAlert');
                    if (editErrorAlert) {
                        editErrorAlert.classList.remove('d-none');
                        editErrorAlert.classList.add('show');
                    }
                    
                    // Show toast notification
                    showToast('Error updating project: ' + error.message, 'error');
                });
            });
        }
        
        // Add Project Button Click Event
        const addProjectBtn = document.querySelector('.btn-success:not(.attendance-btn)');
        if (addProjectBtn) {
            addProjectBtn.addEventListener('click', function() {
                try {
                    const modalElement = document.getElementById('addProjectModal');
                    if (!modalElement) {
                        console.error('Modal element not found');
                        return;
                    }
                    
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                } catch (err) {
                    console.error('Error showing modal:', err);
                }
            });
        } else {
            console.error('Add Project button not found');
        }
        
        // Add Stage Button Click Events
        const initialAddStageBtn = document.getElementById('initialAddStageBtn');
        const stagesContainer = document.getElementById('stagesContainer');
        let stageCount = 0;
        
        // Set up the initial add stage button
        if (initialAddStageBtn && stagesContainer) {
            initialAddStageBtn.addEventListener('click', addNewStage);
        }
        
        // Handle form submission
        const projectForm = document.getElementById('addProjectForm');
        if (projectForm && typeof projectForm.addEventListener === 'function') {
            projectForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading state
                let submitBtn = null;
                let originalBtnText = '';
                
                try {
                    submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        originalBtnText = submitBtn.innerHTML || '';
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Saving...';
                        submitBtn.disabled = true;
                    }
                } catch (err) {
                    console.error('Error accessing submit button:', err);
                }
                
                // Hide any existing alerts
                const saveSuccessAlert = document.getElementById('saveSuccessAlert');
                const saveErrorAlert = document.getElementById('saveErrorAlert');
                
                if (saveSuccessAlert) {
                    saveSuccessAlert.classList.remove('show');
                    saveSuccessAlert.classList.add('d-none');
                }
                
                if (saveErrorAlert) {
                    saveErrorAlert.classList.remove('show');
                    saveErrorAlert.classList.add('d-none');
                }
                
                // Collect form data
                let formData;
                try {
                    formData = new FormData(this);
                } catch (err) {
                    console.error('Error creating FormData:', err);
                    formData = new FormData();
                }
                
                // Add project details
                const projectNameEl = document.getElementById('projectName');
                const projectTypeEl = document.getElementById('projectType');
                const clientNameEl = document.getElementById('clientName');
                
                if (projectNameEl && projectTypeEl && clientNameEl) {
                    formData.append('project_name', projectNameEl.value || '');
                    formData.append('project_type', projectTypeEl.value || '');
                    formData.append('client_name', clientNameEl.value || '');
                } else {
                    console.error('Required form elements not found');
                    // Show error message
                    const errorAlert = document.getElementById('saveErrorAlert');
                    if (errorAlert) {
                        const errorMessageEl = document.getElementById('errorMessage');
                        if (errorMessageEl) {
                            errorMessageEl.textContent = 'Form elements not found. Please try again.';
                        }
                        errorAlert.classList.remove('d-none');
                        errorAlert.classList.add('show');
                    }
                    return; // Stop execution if elements are missing
                }
                
                // Collect stage data
                try {
                    const stageElements = document.querySelectorAll('.stage-item');
                    if (stageElements && stageElements.length > 0) {
                        // Convert stages to JSON and append as a single field
                        const stagesData = {};
                        
                        stageElements.forEach((stageEl, index) => {
                            const stageNum = index + 1;
                            const stageId = stageEl.id;
                            
                            // Get stage date
                            const stageDateEl = stageEl.querySelector('input[name^="stages["][name$="][date]"]');
                            const stageDate = stageDateEl ? stageDateEl.value : '';
                            
                            // Get stage notes
                            const stageNotesEl = stageEl.querySelector('textarea[name^="stages["][name$="][notes]"]');
                            const stageNotes = stageNotesEl ? stageNotesEl.value : '';
                            
                            // Get total amount if toggle is checked
                            const remainingToggle = stageEl.querySelector('.remaining-amount-toggle');
                            let totalAmount = '';
                            
                            if (remainingToggle && remainingToggle.checked) {
                                const totalAmountEl = stageEl.querySelector('input[name^="stages["][name$="][total_amount]"]');
                                totalAmount = totalAmountEl ? totalAmountEl.value : '';
                                console.log(`Stage ${stageNum} total amount: ${totalAmount}`);
                            }
                            
                            // Initialize stage data
                            stagesData[stageNum] = {
                                date: stageDate,
                                notes: stageNotes,
                                total_amount: totalAmount,
                                payments: []
                            };
                            
                            // Get all payment entries for this stage
                            const paymentEntries = stageEl.querySelectorAll('.payment-entry');
                            if (paymentEntries && paymentEntries.length > 0) {
                                paymentEntries.forEach((paymentEl, paymentIndex) => {
                                    const paymentDateEl = paymentEl.querySelector('input[name*="[date]"]');
                                    const paymentAmountEl = paymentEl.querySelector('input[name*="[amount]"]');
                                    const paymentModeEl = paymentEl.querySelector('select[name*="[payment_mode]"]');
                                    
                                    if (paymentDateEl && paymentAmountEl && paymentModeEl) {
                                        stagesData[stageNum].payments.push({
                                            date: paymentDateEl.value,
                                            amount: paymentAmountEl.value,
                                            payment_mode: paymentModeEl.value
                                        });
                                    }
                                });
                            }
                        });
                        
                        // Add stages data to form
                        formData.append('stages', JSON.stringify(stagesData));
                    } else {
                        // No stages found
                        const errorAlert = document.getElementById('saveErrorAlert');
                        if (errorAlert) {
                            const errorMessageEl = document.getElementById('errorMessage');
                            if (errorMessageEl) {
                                errorMessageEl.textContent = 'Please add at least one stage with payment details.';
                            }
                            errorAlert.classList.remove('d-none');
                            errorAlert.classList.add('show');
                        }
                        
                        // Reset button state
                        if (submitBtn) {
                            submitBtn.innerHTML = originalBtnText;
                            submitBtn.disabled = false;
                        }
                        
                        return; // Stop execution if no stages
                    }
                } catch (err) {
                    console.error('Error collecting stage data:', err);
                    
                    // Show error message
                    const errorAlert = document.getElementById('saveErrorAlert');
                    if (errorAlert) {
                        const errorMessageEl = document.getElementById('errorMessage');
                        if (errorMessageEl) {
                            errorMessageEl.textContent = 'Error collecting form data: ' + err.message;
                        }
                        errorAlert.classList.remove('d-none');
                        errorAlert.classList.add('show');
                    }
                    
                    // Reset button state
                    if (submitBtn) {
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    }
                    
                    return; // Stop execution on error
                }
                
                // Send AJAX request
                fetch('ajax_handlers/project_payment_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Check if response is ok before trying to parse JSON
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Server error response:', text);
                            throw new Error(`Server responded with status ${response.status}: ${response.statusText}`);
                        });
                    }
                    
                    // Try to parse JSON, but handle parsing errors gracefully
                    return response.text().then(text => {
                        try {
                            // Log the raw response for debugging
                            console.log('Raw server response:', text);
                            
                            // Check if the response is empty
                            if (!text || text.trim() === '') {
                                throw new Error('Empty response from server');
                            }
                            
                            // Try to parse as JSON
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Error parsing JSON response:', e);
                            console.error('Raw response:', text);
                            throw new Error('Invalid JSON response from server: ' + e.message);
                        }
                    });
                })
                .then(data => {
                    // Reset button state
                    if (submitBtn) {
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    }
                    
                    if (data.success) {
                        // Show success message
                        const successAlert = document.getElementById('saveSuccessAlert');
                        if (successAlert) {
                            successAlert.classList.remove('d-none');
                            successAlert.classList.add('show');
                        }
                        
                        // Count the number of stages added
                        const stageElements = document.querySelectorAll('.stage-item');
                        const stageCount = stageElements.length;
                        
                        // Show toast notification with stage count if multiple stages
                        if (stageCount > 1) {
                            showToast(`Project added successfully with ${stageCount} stages!`);
                        } else {
                            showToast(data.message || 'Project added successfully!');
                        }
                        
                        // Reset form after successful submission
                        setTimeout(() => {
                            if (projectForm) {
                                projectForm.reset();
                            }
                            
                            const modalElement = document.getElementById('addProjectModal');
                            if (modalElement) {
                                const modal = bootstrap.Modal.getInstance(modalElement);
                                if (modal) {
                                    modal.hide();
                                }
                            }
                            
                            // Reload the page to show updated data
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        const errorAlert = document.getElementById('saveErrorAlert');
                        const errorMessageEl = document.getElementById('errorMessage');
                        
                        if (errorMessageEl) {
                            errorMessageEl.textContent = data.message || 'An error occurred while saving.';
                        }
                        
                        if (errorAlert) {
                            errorAlert.classList.remove('d-none');
                            errorAlert.classList.add('show');
                        }
                        
                        // Show toast notification for error
                        showToast(data.message || 'Failed to save project data', 'error');
                    }
                })
                .catch(error => {
                    // Reset button state
                    if (submitBtn) {
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    }
                    
                    // Show error message
                    const errorAlert = document.getElementById('saveErrorAlert');
                    const errorMessageEl = document.getElementById('errorMessage');
                    
                    if (errorMessageEl) {
                        // Provide a more descriptive error message
                        let errorMsg = 'An error occurred while saving the project data.';
                        
                        if (error.message) {
                            if (error.message.includes('JSON')) {
                                errorMsg = 'Server returned an invalid response. Please try again or contact support.';
                            } else if (error.message.includes('status')) {
                                errorMsg = 'Server error: ' + error.message;
                            } else {
                                errorMsg = error.message;
                            }
                        }
                        
                        errorMessageEl.textContent = errorMsg;
                    }
                    
                    if (errorAlert) {
                        errorAlert.classList.remove('d-none');
                        errorAlert.classList.add('show');
                    }
                    
                    // Show toast notification for error
                    showToast(errorMessageEl ? errorMessageEl.textContent : 'An error occurred', 'error');
                    
                    console.error('Error:', error);
                });
            });
        }
        
        // Function to handle adding a stage
        function addNewStage() {
            // Remove the placeholder text if it exists
            const placeholder = stagesContainer.querySelector('p.text-muted');
            if (placeholder) {
                placeholder.remove();
            }
            
            // Hide the initial add button after first stage is added
            if (stageCount === 0) {
                initialAddStageBtn.style.display = 'none';
            }
            
            stageCount++;
            const stageId = `stage-${stageCount}`;
            
            const stageHTML = `
                <div id="${stageId}" class="stage-item border rounded p-3 mb-3 bg-white position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-stage" 
                            data-stage-id="${stageId}" aria-label="Remove stage"></button>
                    <h6 class="border-bottom pb-2 mb-3 d-flex align-items-center">
                        <span class="badge bg-primary me-2">${stageCount}</span>
                        Stage ${stageCount}
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="stages[${stageCount}][date]" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Stage Payments</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary add-payment-btn" data-stage="${stageCount}">
                                    <i class="bi bi-plus"></i> Add Payment
                                </button>
                            </div>
                            
                            <div class="payment-entries" id="paymentEntries-${stageCount}">
                                <!-- Initial payment entry -->
                                <div class="payment-entry card mb-2">
                                    <div class="card-body p-3">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label small">Date</label>
                                                <input type="date" class="form-control form-control-sm" 
                                                       name="stages[${stageCount}][payments][0][date]" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">Amount (₹)</label>
                                                <input type="number" class="form-control form-control-sm payment-amount" 
                                                       name="stages[${stageCount}][payments][0][amount]" 
                                                       placeholder="0.00" min="0" step="0.01" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">Payment Mode</label>
                                                <select class="form-select form-select-sm" 
                                                        name="stages[${stageCount}][payments][0][payment_mode]" required>
                                                    <option value="" selected disabled>Select mode</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="upi">UPI</option>
                                                    <option value="net_banking">Net Banking</option>
                                                    <option value="cheque">Cheque</option>
                                                    <option value="credit_card">Credit Card</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stage-total d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                                <span class="fw-medium">Stage Total:</span>
                                <span class="fw-bold text-primary" id="stageTotal-${stageCount}">₹0.00</span>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input remaining-amount-toggle" type="checkbox" 
                                       id="remainingAmountToggle-${stageCount}" 
                                       data-stage="${stageCount}">
                                <label class="form-check-label" for="remainingAmountToggle-${stageCount}">
                                    Track total project amount and remaining balance
                                </label>
                            </div>
                            
                            <div class="d-none" id="remainingAmountInfo-${stageCount}">
                                <label class="form-label">Total Project Amount</label>
                                <input type="number" class="form-control" 
                                       id="totalProjectAmount-${stageCount}" 
                                       name="stages[${stageCount}][total_amount]"
                                       placeholder="Enter total project amount" min="0" step="0.01">
                                <small class="text-muted">Enter the total project amount to calculate remaining amount</small>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="stages[${stageCount}][notes]" rows="2" placeholder="Any additional details about this stage or payment"></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            stagesContainer.insertAdjacentHTML('beforeend', stageHTML);
            
            // Add event listener for the "Add Payment" button
            const addPaymentBtn = document.querySelector(`#${stageId} .add-payment-btn`);
            if (addPaymentBtn) {
                addPaymentBtn.addEventListener('click', function() {
                    const stageNum = this.getAttribute('data-stage');
                    const paymentEntries = document.getElementById(`paymentEntries-${stageNum}`);
                    const existingEntries = paymentEntries.querySelectorAll('.payment-entry');
                    const paymentIndex = existingEntries.length;
                    
                    // Create new payment entry
                    const paymentHTML = `
                        <div class="payment-entry card mb-2">
                            <div class="card-body p-3 position-relative">
                                <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-payment" 
                                        data-stage="${stageNum}" data-index="${paymentIndex}"></button>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small">Date</label>
                                        <input type="date" class="form-control form-control-sm" 
                                               name="stages[${stageNum}][payments][${paymentIndex}][date]" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">Amount (₹)</label>
                                        <input type="number" class="form-control form-control-sm payment-amount" 
                                               name="stages[${stageNum}][payments][${paymentIndex}][amount]" 
                                               placeholder="0.00" min="0" step="0.01" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">Payment Mode</label>
                                        <select class="form-select form-select-sm" 
                                                name="stages[${stageNum}][payments][${paymentIndex}][payment_mode]" required>
                                            <option value="" selected disabled>Select mode</option>
                                            <option value="cash">Cash</option>
                                            <option value="upi">UPI</option>
                                            <option value="net_banking">Net Banking</option>
                                            <option value="cheque">Cheque</option>
                                            <option value="credit_card">Credit Card</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    paymentEntries.insertAdjacentHTML('beforeend', paymentHTML);
                    
                    // Add event listener to the remove button
                    const removeBtn = paymentEntries.querySelector(`.payment-entry:last-child .remove-payment`);
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function() {
                            const parentEntry = this.closest('.payment-entry');
                            if (parentEntry) {
                                parentEntry.remove();
                                updateStageTotal(stageNum);
                            }
                        });
                    }
                    
                    // Add event listener to the amount input
                    const amountInput = paymentEntries.querySelector(`.payment-entry:last-child .payment-amount`);
                    if (amountInput) {
                        amountInput.addEventListener('input', function() {
                            updateStageTotal(stageNum);
                        });
                    }
                    
                    updateStageTotal(stageNum);
                });
            }
            
            // Add event listener to the initial payment amount
            const initialAmountInput = document.querySelector(`#paymentEntries-${stageCount} .payment-amount`);
            if (initialAmountInput) {
                initialAmountInput.addEventListener('input', function() {
                    updateStageTotal(stageCount);
                });
            }
            
            // Function to update stage total
            function updateStageTotal(stageNum) {
                const totalElement = document.getElementById(`stageTotal-${stageNum}`);
                const amountInputs = document.querySelectorAll(`#paymentEntries-${stageNum} .payment-amount`);
                let total = 0;
                
                amountInputs.forEach(input => {
                    const amount = parseFloat(input.value) || 0;
                    total += amount;
                });
                
                totalElement.textContent = `₹${total.toFixed(2)}`;
            }
            
            // Update or add the floating Add Stage button
            let floatingBtn = document.getElementById('floatingAddStageBtn');
            
            if (!floatingBtn) {
                // First time adding a stage, create the floating button
                const floatingBtnHTML = `
                    <div class="text-center mt-3 mb-3" id="floatingAddStageBtn">
                        <button type="button" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Add Another Stage
                        </button>
                    </div>
                `;
                stagesContainer.insertAdjacentHTML('afterend', floatingBtnHTML);
                
                // Add click event to the floating button
                const floatingAddStageBtn = document.getElementById('floatingAddStageBtn').querySelector('button');
                if (floatingAddStageBtn) {
                    floatingAddStageBtn.addEventListener('click', addNewStage);
                }
            } else {
                // Move the floating button to be after the last stage
                const lastStage = stagesContainer.querySelector('.stage-item:last-child');
                if (lastStage) {
                    stagesContainer.appendChild(floatingBtn);
                }
            }
            
            // Add event listener for the remaining amount toggle
            const remainingToggle = document.querySelector(`#remainingAmountToggle-${stageCount}`);
            if (remainingToggle) {
                remainingToggle.addEventListener('change', function() {
                    const stageNum = this.getAttribute('data-stage');
                    const infoBox = document.getElementById(`remainingAmountInfo-${stageNum}`);
                    const totalAmountField = document.getElementById(`totalProjectAmount-${stageNum}`);
                    
                    if (this.checked) {
                        // Show the remaining amount input field
                        infoBox.classList.remove('d-none');
                        
                        // We're not automatically connecting remaining amount to payment amount
                        // Just keeping the total amount field available for reference
                    } else {
                        // Reset the field
                        totalAmountField.value = '';
                        
                        // Hide remaining amount field
                        infoBox.classList.add('d-none');
                    }
                });
            }
            
            // Add event listener to the remove button
            const removeBtn = document.querySelector(`#${stageId} .remove-stage`);
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    const stageId = this.getAttribute('data-stage-id');
                    const stageElement = document.getElementById(stageId);
                    if (stageElement) {
                        stageElement.remove();
                        
                        // If no stages left, add placeholder back
                        if (stagesContainer.children.length === 0) {
                            stagesContainer.innerHTML = '<p class="text-muted small fst-italic mb-0">No stages added yet. Click "Add Stage" to begin.</p>';
                            stageCount = 0;
                            
                            // Show the initial add button again
                            initialAddStageBtn.style.display = 'block';
                            
                            // Remove the floating button
                            const floatingBtn = document.getElementById('floatingAddStageBtn');
                            if (floatingBtn) {
                                floatingBtn.remove();
                            }
                        } else {
                            // Renumber remaining stages
                            renumberStages();
                        }
                    }
                });
            }
        }
        
        // Function to renumber all stages
        function renumberStages() {
            const stageItems = stagesContainer.querySelectorAll('.stage-item');
            stageItems.forEach((stage, index) => {
                const newNumber = index + 1;
                
                // Update the heading and badge
                const heading = stage.querySelector('h6');
                const badge = heading.querySelector('.badge');
                
                badge.textContent = newNumber;
                
                // Update text content of the heading (need to update the text node)
                const headingText = heading.childNodes[2]; // The text node after the badge
                headingText.nodeValue = ` Stage ${newNumber}`;
                
                // Update the name attributes to have sequential indices
                const inputs = stage.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    if (name) {
                        // Replace the existing index with the new one
                        const newName = name.replace(/stages\[\d+\]/, `stages[${newNumber}]`);
                        input.setAttribute('name', newName);
                    }
                });
                
                // Update the ID to maintain the relationship with the remove button
                const currentId = stage.id;
                const newId = `stage-${newNumber}`;
                stage.id = newId;
                
                // Update the data-stage-id attribute of the remove button
                const removeBtn = stage.querySelector('.remove-stage');
                removeBtn.setAttribute('data-stage-id', newId);
            });
            
            // Update the stageCount to match the current number of stages
            stageCount = stageItems.length;
        }
        
        // Check saved state
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            toggleButton.classList.add('collapsed');
        }

        // Toggle function
        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleButton.classList.toggle('collapsed');
            
            // Save state
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        // Click event
        toggleButton.addEventListener('click', toggleSidebar);

        // Enhanced hover effect
        toggleButton.addEventListener('mouseenter', function() {
            const isCollapsed = toggleButton.classList.contains('collapsed');
            const icon = toggleButton.querySelector('.bi');
            
            if (!isCollapsed) {
                icon.style.transform = 'translateX(-3px)';
            } else {
                icon.style.transform = 'translateX(3px) rotate(180deg)';
            }
        });

        toggleButton.addEventListener('mouseleave', function() {
            const isCollapsed = toggleButton.classList.contains('collapsed');
            const icon = toggleButton.querySelector('.bi');
            
            if (!isCollapsed) {
                icon.style.transform = 'none';
            } else {
                icon.style.transform = 'rotate(180deg)';
            }
        });

        // Handle window resize
        function handleResize() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                toggleButton.classList.add('collapsed');
            } else {
                // Restore saved state on desktop
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === null || savedState === 'false') {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    toggleButton.classList.remove('collapsed');
                }
            }
        }

        window.addEventListener('resize', handleResize);

        // Handle clicks outside sidebar on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                const isClickInside = sidebar.contains(event.target) || 
                                    toggleButton.contains(event.target);
                
                if (!isClickInside && !sidebar.classList.contains('collapsed')) {
                    toggleSidebar();
                }
            }
        });

        // Initial check for mobile devices
        handleResize();

        // Edit form submission handler
        // ... existing code ...
        
        // Delete Project Stage Functionality
        const deleteButtons = document.querySelectorAll('.delete-btn');
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        
        // Add event listeners to all delete buttons
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const transactionId = this.getAttribute('data-transaction-id');
                showDeleteConfirmation(transactionId);
            });
        });
        
        // Function to show delete confirmation modal
        function showDeleteConfirmation(transactionId) {
            // Set the transaction ID in the hidden input
            document.getElementById('deleteTransactionId').value = transactionId;
            
            // Show the modal
            const modal = new bootstrap.Modal(deleteConfirmModal);
            modal.show();
            
            // Hide any existing alerts
            const deleteSuccessAlert = document.getElementById('deleteSuccessAlert');
            const deleteErrorAlert = document.getElementById('deleteErrorAlert');
            
            if (deleteSuccessAlert) deleteSuccessAlert.classList.add('d-none');
            if (deleteErrorAlert) deleteErrorAlert.classList.add('d-none');
        }
        
        // Handle confirm delete button click
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function() {
                const transactionId = document.getElementById('deleteTransactionId').value;
                
                // Show loading state
                const originalBtnText = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Deleting...';
                this.disabled = true;
                
                // Create form data
                const formData = new FormData();
                formData.append('transaction_id', transactionId);
                
                // Send AJAX request
                fetch('ajax_handlers/delete_project_stage.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server responded with status ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Reset button state
                    this.innerHTML = originalBtnText;
                    this.disabled = false;
                    
                    if (data.success) {
                        // Show success message
                        const deleteSuccessAlert = document.getElementById('deleteSuccessAlert');
                        if (deleteSuccessAlert) {
                            deleteSuccessAlert.classList.remove('d-none');
                            deleteSuccessAlert.classList.add('show');
                        }
                        
                        // Show toast notification
                        showToast(data.message);
                        
                        // Close modal and reload page after a delay
                        setTimeout(() => {
                            const modal = bootstrap.Modal.getInstance(deleteConfirmModal);
                            if (modal) {
                                modal.hide();
                            }
                            
                            // Reload the page to show updated data
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        const errorMessage = document.getElementById('deleteErrorMessage');
                        if (errorMessage) {
                            errorMessage.textContent = data.message || 'An error occurred while deleting.';
                        }
                        
                        const deleteErrorAlert = document.getElementById('deleteErrorAlert');
                        if (deleteErrorAlert) {
                            deleteErrorAlert.classList.remove('d-none');
                            deleteErrorAlert.classList.add('show');
                        }
                        
                        // Show toast notification
                        showToast(data.message || 'Failed to delete project stage', 'error');
                    }
                })
                .catch(error => {
                    // Reset button state
                    this.innerHTML = originalBtnText;
                    this.disabled = false;
                    
                    // Show error message
                    const errorMessage = document.getElementById('deleteErrorMessage');
                    if (errorMessage) {
                        errorMessage.textContent = 'Error: ' + error.message;
                    }
                    
                    const deleteErrorAlert = document.getElementById('deleteErrorAlert');
                    if (deleteErrorAlert) {
                        deleteErrorAlert.classList.remove('d-none');
                        deleteErrorAlert.classList.add('show');
                    }
                    
                    // Show toast notification
                    showToast('Error deleting project stage: ' + error.message, 'error');
                });
            });
        }
    });
    </script>
</body>
</html> 