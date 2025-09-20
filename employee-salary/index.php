<?php
// Employee Salary Management System
// Currently displaying dummy data for demonstration
// TODO: Implement backend API endpoints for real data integration

// Start session
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

// Get current month or selected month
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = date('Y-m-01', strtotime($selected_month));
$month_end = date('Y-m-t', strtotime($selected_month));

// Get month display name
$month_display = date('F Y', strtotime($selected_month));

// Get total employees count
$total_employees_query = "SELECT COUNT(*) as total FROM users WHERE status = 'active' AND deleted_at IS NULL";
$total_stmt = $pdo->prepare($total_employees_query);
$total_stmt->execute();
$total_employees = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total salary budget
$salary_budget_query = "SELECT SUM(base_salary) as total_budget FROM users WHERE status = 'active' AND deleted_at IS NULL";
$budget_stmt = $pdo->prepare($salary_budget_query);
$budget_stmt->execute();
$total_budget = $budget_stmt->fetch(PDO::FETCH_ASSOC)['total_budget'] ?? 0;

// Get processed payroll count for selected month
$processed_query = "SELECT COUNT(DISTINCT user_id) as processed FROM salary_details WHERE month_year = ?";
$processed_stmt = $pdo->prepare($processed_query);
$processed_stmt->execute([$selected_month]);
$processed_count = $processed_stmt->fetch(PDO::FETCH_ASSOC)['processed'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Salary Management - HR System</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/salary-main.css">
    <link rel="stylesheet" href="css/salary-components.css">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                <li class="nav-item active">
                    <a href="#" class="nav-link">
                        <i class="fas fa-money-bill-wave"></i>
                        <span class="nav-text">Employee Salary</span>
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
                    <span class="user-name">Admin User</span>
                    <span class="user-role">Administrator</span>
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
                <span class="breadcrumb-item current">Employee Salary</span>
            </div>
        </div>
        
        <!-- Demo Data Notice -->
        <div class="demo-notice" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; padding: 0.75rem 2rem; text-align: center; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
            Demo Mode: Currently displaying sample data. Backend API integration pending for live data.
        </div>

        <!-- Header -->
        <header class="salary-header">
            <div class="header-container">
                <div class="header-left">
                    <div class="page-title">
                        <h1>Employee Salary Management</h1>
                        <p>Manage and track employee compensation for <?php echo $month_display; ?></p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="month-selector">
                        <label for="monthSelect">Select Month:</label>
                        <input type="month" id="monthSelect" value="<?php echo $selected_month; ?>" 
                               onchange="changeMonth(this.value)">
                    </div>
                    <button class="btn btn-primary" onclick="openBulkProcessModal()">
                        <i class="fas fa-cogs"></i>
                        Bulk Process
                    </button>
                </div>
            </div>
        </header>

    <!-- Statistics Dashboard -->
    <section class="stats-section">
        <div class="stats-container">
            <div class="stat-card total-employees">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_employees); ?></h3>
                    <p>Total Employees</p>
                </div>
            </div>
            
            <div class="stat-card budget-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>₹<?php echo number_format($total_budget, 0); ?></h3>
                    <p>Monthly Budget</p>
                </div>
            </div>
            
            <div class="stat-card processed-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $processed_count; ?></h3>
                    <p>Processed Payrolls</p>
                </div>
            </div>
            
            <div class="stat-card pending-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo ($total_employees - $processed_count); ?></h3>
                    <p>Pending Reviews</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Filter and Action Bar -->
    <section class="action-bar">
        <div class="action-container">
            <div class="filter-section">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="employeeSearch" placeholder="Search employees...">
                </div>
                
                <select id="roleFilter" class="filter-select">
                    <option value="">All Roles</option>
                    <option value="Software Engineer">Software Engineer</option>
                    <option value="Sales Manager">Sales Manager</option>
                    <option value="Marketing Specialist">Marketing Specialist</option>
                    <option value="HR Manager">HR Manager</option>
                    <option value="Finance Analyst">Finance Analyst</option>
                    <option value="Operations Manager">Operations Manager</option>
                    <option value="Team Lead">Team Lead</option>
                    <option value="Senior Developer">Senior Developer</option>
                </select>
                
                <select id="statusFilter" class="filter-select">
                    <option value="">All Status</option>
                    <option value="processed">Processed</option>
                    <option value="pending">Pending</option>
                    <option value="review">Under Review</option>
                </select>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-outline" onclick="exportSalaryData()">
                    <i class="fas fa-download"></i>
                    Export
                </button>
                <button class="btn btn-success" onclick="openPayrollWizard()">
                    <i class="fas fa-magic"></i>
                    Payroll Wizard
                </button>
            </div>
        </div>
    </section>

    <!-- Main Salary Table -->
    <section class="salary-table-section">
        <div class="table-container">
            <div class="table-header">
                <h2>Employee Salary Details - <?php echo $month_display; ?></h2>
                <div class="table-actions">
                    <button class="btn btn-sm btn-outline" onclick="refreshTable()">
                        <i class="fas fa-sync-alt"></i>
                        Refresh
                    </button>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table class="salary-table" id="salaryTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Base Salary</th>
                            <th>Working Days</th>
                            <th>Present Days</th>
                            <th>Late Days</th>
                            <th>Leave Days</th>
                            <th>Overtime Hours</th>
                            <th>Overtime Amount</th>
                            <th>Travel Allowance</th>
                            <th>Bonus/Incentives</th>
                            <th>Total Deductions</th>
                            <th>Tax Deduction</th>
                            <th>PF Contribution</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salaryTableBody">
                        <!-- Employee data will be loaded here via AJAX -->
                        <tr>
                            <td colspan="18" class="loading-row">
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Loading employee salary data...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Table Pagination -->
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing <span id="showingStart">1</span> to <span id="showingEnd">10</span> 
                    of <span id="totalRecords">0</span> employees
                </div>
                <div class="pagination-controls">
                    <button class="btn btn-sm" onclick="previousPage()" id="prevBtn">
                        <i class="fas fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="page-numbers" id="pageNumbers">
                        <!-- Page numbers will be generated here -->
                    </span>
                    <button class="btn btn-sm" onclick="nextPage()" id="nextBtn">
                        Next
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        </section>
    </div>

    <!-- Include Modals -->
    <?php include 'components/salary-detail-modal.php'; ?>
    <?php include 'components/bulk-process-modal.php'; ?>
    <?php include 'components/payroll-wizard-modal.php'; ?>

    <!-- JavaScript Files -->
    <script src="js/salary-main.js"></script>
    <script src="js/salary-table.js"></script>
    <script src="js/salary-modals.js"></script>
    <script src="js/sidebar.js"></script>
    
    <script>
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadSalaryData();
            setupEventListeners();
        });
        
        // Function to change month
        function changeMonth(month) {
            // Update the month selector value if it exists
            const monthSelect = document.getElementById('monthSelect');
            if (monthSelect) {
                monthSelect.value = month;
            }
            
            // Reload salary data for the selected month
            loadSalaryData();
            
            // Update page title and header to reflect selected month
            const monthDisplay = new Date(month + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            const pageTitle = document.querySelector('.page-title p');
            if (pageTitle) {
                pageTitle.textContent = `Manage and track employee compensation for ${monthDisplay}`;
            }
        }
    </script>
</body>
</html>