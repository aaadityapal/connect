<?php
// Attendance and Salary Report with Detailed Deductions
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has appropriate role (HR, Manager, Admin)
$allowed_roles = ['HR', 'Admin', 'Manager'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../unauthorized.php?message=You don't have permission to access this page");
    exit;
}

// Include database connection
require_once '../config/db_connect.php';

// Get current month/year or selected values
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_role = isset($_GET['role']) ? $_GET['role'] : '';

// Get month display name
$month_display = date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year));
$selected_month = sprintf('%04d-%02d', $current_year, $current_month); // Format as YYYY-MM
$month_start = date('Y-m-01', strtotime($selected_month));
$month_end = date('Y-m-t', strtotime($selected_month));

// Fetch distinct roles from the database for the filter
$roles_query = "SELECT DISTINCT role FROM users WHERE status = 'active' AND deleted_at IS NULL AND role IS NOT NULL ORDER BY role";
$roles_stmt = $pdo->prepare($roles_query);
$roles_stmt->execute();
$available_roles = $roles_stmt->fetchAll(PDO::FETCH_COLUMN);

// Build query to fetch employee data with attendance and salary information
$query = "SELECT 
            u.id,
            u.employee_id,
            u.username,
            u.role,
            u.base_salary,
            u.status
          FROM users u 
          WHERE u.status = 'active' AND u.deleted_at IS NULL";

// Add role filter if selected
if (!empty($selected_role)) {
    $query .= " AND u.role = :role";
}

$query .= " ORDER BY u.username";

try {
    $stmt = $pdo->prepare($query);
    if (!empty($selected_role)) {
        $stmt->bindParam(':role', $selected_role);
    }
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each employee to calculate attendance and salary details
    foreach ($employees as &$employee) {
        // Calculate working days for the month
        $working_days_query = "SELECT COUNT(*) as working_days 
                              FROM attendance 
                              WHERE user_id = :user_id 
                              AND DATE(date) BETWEEN :start_date AND :end_date 
                              AND status IN ('present', 'leave')";
        $working_stmt = $pdo->prepare($working_days_query);
        $working_stmt->bindParam(':user_id', $employee['id']);
        $working_stmt->bindParam(':start_date', $month_start);
        $working_stmt->bindParam(':end_date', $month_end);
        $working_stmt->execute();
        $working_result = $working_stmt->fetch(PDO::FETCH_ASSOC);
        $employee['working_days'] = $working_result['working_days'] ?? 0;
        
        // Calculate present days
        $present_days_query = "SELECT COUNT(*) as present_days 
                              FROM attendance 
                              WHERE user_id = :user_id 
                              AND DATE(date) BETWEEN :start_date AND :end_date 
                              AND status = 'present'";
        $present_stmt = $pdo->prepare($present_days_query);
        $present_stmt->bindParam(':user_id', $employee['id']);
        $present_stmt->bindParam(':start_date', $month_start);
        $present_stmt->bindParam(':end_date', $month_end);
        $present_stmt->execute();
        $present_result = $present_stmt->fetch(PDO::FETCH_ASSOC);
        $employee['present_days'] = $present_result['present_days'] ?? 0;
        
        // Calculate leave days
        $leave_days_query = "SELECT COUNT(*) as leave_days 
                            FROM attendance 
                            WHERE user_id = :user_id 
                            AND DATE(date) BETWEEN :start_date AND :end_date 
                            AND status = 'leave'";
        $leave_stmt = $pdo->prepare($leave_days_query);
        $leave_stmt->bindParam(':user_id', $employee['id']);
        $leave_stmt->bindParam(':start_date', $month_start);
        $leave_stmt->bindParam(':end_date', $month_end);
        $leave_stmt->execute();
        $leave_result = $leave_stmt->fetch(PDO::FETCH_ASSOC);
        $employee['leave_days'] = $leave_result['leave_days'] ?? 0;
        
        // Calculate late days (punch in after shift start time + grace period)
        // This is a simplified version - in a real system, you would check against shift times
        $late_days_query = "SELECT COUNT(*) as late_days 
                           FROM attendance 
                           WHERE user_id = :user_id 
                           AND DATE(date) BETWEEN :start_date AND :end_date 
                           AND status = 'present'
                           AND TIME(punch_in) > '09:15:00'"; // Simplified - after 9:15 AM
        $late_stmt = $pdo->prepare($late_days_query);
        $late_stmt->bindParam(':user_id', $employee['id']);
        $late_stmt->bindParam(':start_date', $month_start);
        $late_stmt->bindParam(':end_date', $month_end);
        $late_stmt->execute();
        $late_result = $late_stmt->fetch(PDO::FETCH_ASSOC);
        $employee['late_days'] = $late_result['late_days'] ?? 0;
        
        // Calculate 1+ hour late days (punch in after shift start time + 1 hour)
        $one_hour_late_query = "SELECT COUNT(*) as one_hour_late 
                               FROM attendance 
                               WHERE user_id = :user_id 
                               AND DATE(date) BETWEEN :start_date AND :end_date 
                               AND status = 'present'
                               AND TIME(punch_in) > '10:00:00'"; // Simplified - after 10:00 AM
        $one_hour_stmt = $pdo->prepare($one_hour_late_query);
        $one_hour_stmt->bindParam(':user_id', $employee['id']);
        $one_hour_stmt->bindParam(':start_date', $month_start);
        $one_hour_stmt->bindParam(':end_date', $month_end);
        $one_hour_stmt->execute();
        $one_hour_result = $one_hour_stmt->fetch(PDO::FETCH_ASSOC);
        $employee['one_hour_late'] = $one_hour_result['one_hour_late'] ?? 0;
        
        // Calculate salary days (present days for now)
        $employee['salary_days'] = $employee['present_days'];
        
        // Calculate deductions (simplified)
        $daily_salary = $employee['base_salary'] > 0 && $employee['working_days'] > 0 ? 
                        $employee['base_salary'] / $employee['working_days'] : 0;
        
        $employee['leave_deduction'] = $employee['leave_days'] * ($daily_salary * 0.5); // 50% for leave
        $employee['late_deduction'] = $employee['late_days'] * ($daily_salary * 0.1); // 10% for late
        $employee['one_hour_late_deduction'] = $employee['one_hour_late'] * ($daily_salary * 0.25); // 25% for 1+ hour late
        $employee['penalty'] = 0; // Simplified - no penalty
        $employee['short_leave'] = 0; // Simplified - no short leave
        $employee['fourth_punch_missing'] = 0; // Simplified - no missing punch
        
        // Calculate net salary
        $total_deductions = $employee['leave_deduction'] + $employee['late_deduction'] + 
                           $employee['one_hour_late_deduction'] + $employee['penalty'];
        $employee['net_salary'] = $employee['base_salary'] - $total_deductions;
        $employee['excess_day_salary'] = 0; // Simplified - no excess days
    }
} catch (PDOException $e) {
    error_log("Error fetching employee data: " . $e->getMessage());
    $employees = [];
}

// Calculate previous and next months for navigation
$prev_month = $current_month == 1 ? 12 : $current_month - 1;
$prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
$next_month = $current_month == 12 ? 1 : $current_month + 1;
$next_year = $current_month == 12 ? $current_year + 1 : $current_year;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance & Salary Report - HR System</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/salary-main.css">
    <link rel="stylesheet" href="css/salary-components.css">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Additional styles for this specific page */
        .filter-cards-container {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            flex: 1;
            min-width: 250px;
            transition: all 0.3s ease;
        }
        
        .filter-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        
        .filter-card h3 {
            margin-top: 0;
            color: #2d3748;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-card h3 i {
            color: #4299e1;
        }
        
        .filter-card .form-group {
            margin-bottom: 15px;
        }
        
        .filter-card label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }
        
        .filter-card select, .filter-card input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .filter-card select:focus, .filter-card input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .filter-card .btn-apply {
            width: 100%;
            padding: 12px;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .filter-card .btn-apply:hover {
            background: #3182ce;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        
        .nav-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #4a5568;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .nav-btn:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }
        
        .table-wrapper {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .salary-details-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .salary-details-table th {
            background: #f7fafc;
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
            border-bottom: 2px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .salary-details-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #4a5568;
        }
        
        .salary-details-table tr:hover td {
            background: #f7fafc;
        }
        
        .salary-details-table .employee-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #4299e1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .employee-info h4 {
            margin: 0 0 4px 0;
            font-size: 15px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .employee-info .employee-id {
            margin: 0;
            font-size: 13px;
            color: #718096;
        }
        
        .amount-positive {
            color: #38a169;
            font-weight: 600;
        }
        
        .amount-negative {
            color: #e53e3e;
            font-weight: 600;
        }
        
        .amount-neutral {
            color: #4a5568;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-processed {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-pending {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .status-review {
            background: #e9d8fd;
            color: #553c9a;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-view {
            background: #ebf8ff;
            color: #3182ce;
        }
        
        .btn-edit {
            background: #f0fff4;
            color: #38a169;
        }
        
        .btn-process {
            background: #fffbeb;
            color: #d69e2e;
        }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .filter-cards-container {
                flex-direction: column;
            }
            
            .navigation-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .salary-details-table {
                min-width: 1200px;
            }
        }
    </style>
</head>
<body>
    <!-- Left Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-users-cog"></i>
                <span class="sidebar-title">HR System</span>
            </div>
            <button class="sidebar-toggle-btn" id="sidebarToggle">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-money-bill-wave"></i>
                        <span class="nav-text">Employee Salary</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="attendance_salary_report.php" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span class="nav-text">Attendance & Salary</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Employees</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin User'); ?></span>
                    <span class="user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Floating Toggle Button (shows when sidebar is collapsed) -->
    <button class="floating-toggle-btn" id="floatingToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Content Wrapper -->
    <div class="main-wrapper" id="mainWrapper">
        <!-- Top Navigation -->
        <div class="top-nav">
            <button class="sidebar-toggle-btn mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="breadcrumb">
                <span class="breadcrumb-item">HR System</span>
                <i class="fas fa-chevron-right"></i>
                <span class="breadcrumb-item">Attendance & Salary</span>
            </div>
        </div>
        
        <!-- Header -->
        <header class="salary-header">
            <div class="header-container">
                <div class="header-left">
                    <div class="page-title">
                        <h1>Attendance & Salary Report</h1>
                        <p>Detailed attendance and salary information for <?php echo $month_display; ?></p>
                    </div>
                </div>
                <div class="header-right">
                    <button class="btn btn-primary" onclick="exportReport()">
                        <i class="fas fa-download"></i>
                        Export Report
                    </button>
                </div>
            </div>
        </header>
        
        <!-- Filter Cards -->
        <div class="filter-cards-container">
            <div class="filter-card">
                <h3><i class="fas fa-calendar-alt"></i> Month Filter</h3>
                <div class="form-group">
                    <label for="monthSelect">Select Month</label>
                    <select id="monthSelect" onchange="changeFilters()">
                        <?php
                        $months = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        foreach ($months as $num => $name) {
                            $selected = ($num == $current_month) ? 'selected' : '';
                            echo "<option value='$num' $selected>$name</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="filter-card">
                <h3><i class="fas fa-calendar-alt"></i> Year Filter</h3>
                <div class="form-group">
                    <label for="yearSelect">Select Year</label>
                    <select id="yearSelect" onchange="changeFilters()">
                        <?php
                        $current_year_val = date('Y');
                        for ($year = $current_year_val - 2; $year <= $current_year_val + 1; $year++) {
                            $selected = ($year == $current_year) ? 'selected' : '';
                            echo "<option value='$year' $selected>$year</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="filter-card">
                <h3><i class="fas fa-filter"></i> Additional Filters</h3>
                <div class="form-group">
                    <label for="roleFilter">Employee Role</label>
                    <select id="roleFilter" onchange="changeFilters()">
                        <option value="">All Roles</option>
                        <?php foreach ($available_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>" <?php echo ($selected_role == $role) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn-apply" onclick="applyFilters()">
                    <i class="fas fa-sync-alt"></i> Apply Filters
                </button>
            </div>
        </div>
        
        <!-- Navigation Buttons -->
        <div class="navigation-buttons">
            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="nav-btn">
                <i class="fas fa-chevron-left"></i>
                Previous Month
            </a>
            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="nav-btn">
                Next Month
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        
        <!-- Main Table -->
        <div class="table-wrapper">
            <table class="salary-details-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Base Salary</th>
                        <th>Working Days</th>
                        <th>Present Days</th>
                        <th>Leave Taken</th>
                        <th>Leave Deduction</th>
                        <th>Short Leave</th>
                        <th>Late Days</th>
                        <th>Late Deduction</th>
                        <th>1+ Hour Late</th>
                        <th>1+ Hour Late Deduction</th>
                        <th>4th Punch Out Missing</th>
                        <th>Salary Days Calculated</th>
                        <th>Penalty</th>
                        <th>Net Salary</th>
                        <th>Excess Day Salary</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                            <td>
                                <div class="employee-cell">
                                    <div class="employee-avatar">
                                        <?php 
                                        $initials = '';
                                        $name_parts = explode(' ', $employee['username']);
                                        foreach ($name_parts as $part) {
                                            $initials .= strtoupper(substr($part, 0, 1));
                                        }
                                        echo substr($initials, 0, 2);
                                        ?>
                                    </div>
                                    <div class="employee-info">
                                        <h4><?php echo htmlspecialchars($employee['username']); ?></h4>
                                        <p class="employee-id"><?php echo htmlspecialchars($employee['employee_id']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($employee['role']); ?></td>
                            <td class="amount-positive">₹<?php echo number_format($employee['base_salary'], 0); ?></td>
                            <td><?php echo $employee['working_days']; ?></td>
                            <td><?php echo $employee['present_days']; ?></td>
                            <td><?php echo $employee['leave_days']; ?></td>
                            <td class="amount-negative">₹<?php echo number_format($employee['leave_deduction'], 0); ?></td>
                            <td><?php echo $employee['short_leave']; ?></td>
                            <td><?php echo $employee['late_days']; ?></td>
                            <td class="amount-negative">₹<?php echo number_format($employee['late_deduction'], 0); ?></td>
                            <td><?php echo $employee['one_hour_late']; ?></td>
                            <td class="amount-negative">₹<?php echo number_format($employee['one_hour_late_deduction'], 0); ?></td>
                            <td><?php echo $employee['fourth_punch_missing']; ?></td>
                            <td><?php echo $employee['salary_days']; ?></td>
                            <td class="amount-negative">₹<?php echo number_format($employee['penalty'], 0); ?></td>
                            <td class="amount-positive">₹<?php echo number_format($employee['net_salary'], 0); ?></td>
                            <td class="amount-positive">₹<?php echo number_format($employee['excess_day_salary'], 0); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn btn-view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="19" style="text-align: center; padding: 20px;">
                                No employee data found for the selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="js/sidebar.js"></script>
    
    <script>
        // Function to change filters
        function changeFilters() {
            // This would normally trigger an AJAX call to update the table
            console.log('Filters changed');
        }
        
        // Function to apply filters
        function applyFilters() {
            const month = document.getElementById('monthSelect').value;
            const year = document.getElementById('yearSelect').value;
            const role = document.getElementById('roleFilter').value;
            
            // Redirect with new parameters
            window.location.href = `?month=${month}&year=${year}&role=${role}`;
        }
        
        // Function to export report
        function exportReport() {
            alert('Export functionality would be implemented here');
        }
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
        });
    </script>
</body>
</html>