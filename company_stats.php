<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "crm";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to get income data grouped by date and project_type
function getIncomeData($conn, $month, $year) {
    $sql = "SELECT 
                DATE(project_date) as date,
                SUM(CASE WHEN project_type = 'architecture' THEN amount ELSE 0 END) as architecture,
                SUM(CASE WHEN project_type = 'interior' THEN amount ELSE 0 END) as interior,
                SUM(CASE WHEN project_type = 'construction' THEN amount ELSE 0 END) as construction
            FROM project_payouts
            WHERE MONTH(project_date) = ? AND YEAR(project_date) = ?
            GROUP BY DATE(project_date)
            ORDER BY date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Get available years and months for filters
function getAvailableYearsMonths($conn) {
    $sql = "SELECT 
                DISTINCT YEAR(project_date) as year, 
                MONTH(project_date) as month 
            FROM project_payouts 
            ORDER BY year DESC, month DESC";
    
    $result = $conn->query($sql);
    
    $years = array();
    $months = array();
    
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['year'], $years)) {
            $years[] = $row['year'];
        }
        if (!in_array($row['month'], $months)) {
            $months[] = $row['month'];
        }
    }
    
    return array('years' => $years, 'months' => $months);
}

// Get available years and months
$availableFilters = getAvailableYearsMonths($conn);
$years = $availableFilters['years'];
$months = $availableFilters['months'];

// Default to current month and year if available, otherwise use the first available
$currentMonth = date('n');
$currentYear = date('Y');

$defaultMonth = in_array($currentMonth, $months) ? $currentMonth : (count($months) > 0 ? $months[0] : 1);
$defaultYear = in_array($currentYear, $years) ? $currentYear : (count($years) > 0 ? $years[0] : date('Y'));

// Get initial income data
$incomeData = getIncomeData($conn, $defaultMonth, $defaultYear);

// Convert to JSON for JavaScript
$incomeDataJson = json_encode($incomeData);
$yearsJson = json_encode($years);
$monthsJson = json_encode($months);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Stats Dashboard</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4bb543;
            --warning-color: #fca311;
            --danger-color: #ef233c;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Left Sidebar Styles */
        .sidebar#sidebar {
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

        .sidebar#sidebar.collapsed {
            transform: translateX(-100%);
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
            background: var(--primary-color);
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

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link {
            color: var(--dark-color);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
            background: rgba(67, 97, 238, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding-top: 1rem;
            color: #dc3545!important;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Main content styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0;
            background: transparent;
            border-bottom: none;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        @media (max-width: 768px) {
            .sidebar#sidebar {
                transform: translateX(-100%);
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar#sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .change {
            font-size: 14px;
            display: flex;
            align-items: center;
        }

        .change.positive {
            color: var(--success-color);
        }

        .change.negative {
            color: var(--danger-color);
        }

        .change.neutral {
            color: var(--warning-color);
        }

        /* Charts Container */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .chart-card h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--dark-color);
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        /* Table Styles */
        .employees-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: var(--light-color);
            font-weight: 600;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-on-leave {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .department {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background-color: #e2e3e5;
            color: #383d41;
            font-size: 12px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination button {
            padding: 8px 16px;
            margin: 0 5px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
        }

        .pagination button.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination button:hover:not(.active) {
            background-color: #f1f1f1;
        }
        
        /* Avatar styles for manager list */
        .avatar-sm {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.1);
            border-radius: 50%;
            overflow: hidden;
        }

        /* Enhanced table styles */
        .table {
            margin-bottom: 0;
        }
        
        .table th, .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }
        
        .table thead th {
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
        }
        
        .table tfoot {
            border-top: 2px solid #e9ecef;
        }
        
        .table tfoot td {
            font-weight: 600;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.04);
        }
        
        /* Currency formatting */
        .text-end {
            text-align: right;
        }
        
        /* Zero value styling */
        .zero-value {
            color: #adb5bd;
        }
        
        /* Manager card styling */
        
        .manager-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
        }
        
        .manager-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
        }
        
        .manager-card .card-header {
            padding: 0.75rem 1rem;
        }
        
        .manager-card .card-body {
            padding: 1.25rem;
        }
        
        .manager-card .card-footer {
            padding: 1rem;
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .studio-header {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
        }
        
        .site-header {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
        }
        
        .manager-info p {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .manager-info i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
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
            <a href="payouts.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Manager Payouts
            </a>
            <a href="company_stats.php" class="nav-link active">
                <i class="bi bi-bar-chart-fill"></i>
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
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
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

    <!-- Add toggle sidebar button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content Section -->
    <div class="main-content" id="mainContent">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1>Company Statistics</h1>
                <p class="text-muted">Saturday, June 14, 2025</p>
            </div>
            <div>
                <a href="#" class="btn text-primary">
                    <i class="bi bi-download me-1"></i>
                    Export Report
                </a>
            </div>
        </div>

        <!-- Company Income Table Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">Company Income</h2>
                <div class="d-flex gap-2">
                    <div class="input-group" style="width: 120px;">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-calendar3-month"></i>
                        </span>
                        <select class="form-select border-start-0" id="monthFilter">
                            <option value="6">June</option>
                            <option value="5">May</option>
                            <option value="4">April</option>
                            <option value="3">March</option>
                        </select>
                    </div>
                    <div class="input-group" style="width: 120px;">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-calendar3-year"></i>
                        </span>
                        <select class="form-select border-start-0" id="yearFilter">
                            <option value="2025">2025</option>
                            <option value="2024">2024</option>
                            <option value="2023">2023</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" id="applyFilters">
                        <i class="bi bi-funnel-fill me-1"></i>Apply
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 border-bottom">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th class="text-end">Architecture</th>
                                <th class="text-end">Interior</th>
                                <th class="text-end">Construction</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="incomeTableBody">
                            <!-- Table data will be populated by JavaScript -->
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td>Total</td>
                                <td class="text-end" id="totalArchitecture">₹0.00</td>
                                <td class="text-end" id="totalInterior">₹0.00</td>
                                <td class="text-end" id="totalConstruction">₹0.00</td>
                                <td class="text-end text-success" id="grandTotal">₹0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Section Payout Part -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">Section Payout Part</h2>
                <div class="d-flex gap-2">
                    <div class="input-group" style="width: 120px;">
                        <select class="form-select" id="managerRoleFilter">
                            <option value="all">All Roles</option>
                            <option value="Senior Manager (Studio)">Studio</option>
                            <option value="Senior Manager (Site)">Site</option>
                        </select>
                    </div>
                    <div class="input-group" style="width: 120px;">
                        <select class="form-select" id="managerMonthFilter">
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    <div class="input-group" style="width: 100px;">
                        <select class="form-select" id="managerYearFilter">
                            <option value="2023">2023</option>
                            <option value="2024">2024</option>
                            <option value="2025">2025</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" id="applyManagerFilters">
                        <i class="bi bi-funnel"></i> Apply
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="managersCardContainer">
                    <!-- Manager cards will be populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Manager Payout Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">Manager Payout</h2>
                <div class="d-flex gap-2">
                    <div class="input-group" style="width: 180px;">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-briefcase"></i>
                        </span>
                        <select class="form-select border-start-0" id="departmentFilter">
                            <option value="all">All Departments</option>
                            <option value="architecture">Architecture</option>
                            <option value="interior">Interior</option>
                            <option value="construction">Construction</option>
                        </select>
                    </div>
                    <div class="input-group" style="width: 120px;">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-calendar3-month"></i>
                        </span>
                        <select class="form-select border-start-0" id="monthFilterManagers">
                            <option value="6">June</option>
                            <option value="5">May</option>
                            <option value="4">April</option>
                            <option value="3">March</option>
                        </select>
                    </div>
                    <div class="input-group" style="width: 120px;">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-calendar3-year"></i>
                        </span>
                        <select class="form-select border-start-0" id="yearFilterManagers">
                            <option value="2025">2025</option>
                            <option value="2024">2024</option>
                            <option value="2023">2023</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" id="applyManagerFilters">
                        <i class="bi bi-funnel-fill me-1"></i>Apply
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="managerCardsContainer">
                    <!-- Manager cards will be dynamically generated by JavaScript -->
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value">$1,245,678</div>
                <div class="change positive">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                        <polyline points="17 6 23 6 23 12"></polyline>
                    </svg>
                    12.5% from last month
                </div>
            </div>

            <div class="stat-card">
                <h3>Total Employees</h3>
                <div class="value">247</div>
                <div class="change positive">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                        <polyline points="17 6 23 6 23 12"></polyline>
                    </svg>
                    3 new hires
                </div>
            </div>

            <div class="stat-card">
                <h3>Active Projects</h3>
                <div class="value">18</div>
                <div class="change neutral">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                    </svg>
                    No change
                </div>
            </div>

            <div class="stat-card">
                <h3>Customer Satisfaction</h3>
                <div class="value">92%</div>
                <div class="change negative">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline>
                        <polyline points="17 18 23 18 23 12"></polyline>
                    </svg>
                    2% from last quarter
                </div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <h2>Revenue by Quarter</h2>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2>Department Distribution</h2>
                <div class="chart-container">
                    <canvas id="departmentChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">Recent Employees</h2>
                <button class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-person-plus me-1"></i>
                    View All
                </button>
            </div>
            <div class="card-body p-0">
                <div class="employees-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Hire Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="employees-table-body">
                            <!-- Employees will be inserted here by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="pagination mt-3">
                    <button id="prev-page">Previous</button>
                    <button class="active">1</button>
                    <button>2</button>
                    <button>3</button>
                    <button id="next-page">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">Process Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <input type="hidden" id="paymentManagerId">
                        <input type="hidden" id="paymentManagerName">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="projectName" class="form-label">Project Name</label>
                                <select class="form-select" id="projectName" required>
                                    <option value="">Select Project</option>
                                    <!-- Project options will be populated by JavaScript -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="projectType" class="form-label">Project Type</label>
                                <input type="text" class="form-control" id="projectType" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="clientName" class="form-label">Client Name</label>
                                <input type="text" class="form-control" id="clientName" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="paymentDate" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" id="paymentDate" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="projectStage" class="form-label">Project Stage</label>
                                <select class="form-select" id="projectStage" required>
                                    <option value="">Select Stage</option>
                                    <option value="1">Stage 1</option>
                                    <option value="2">Stage 2</option>
                                    <option value="3">Stage 3</option>
                                    <option value="4">Stage 4</option>
                                    <option value="5">Stage 5</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="paymentMode" class="form-label">Payment Mode</label>
                                <select class="form-select" id="paymentMode" required>
                                    <option value="">Select Mode</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="check">Check</option>
                                    <option value="upi">UPI</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="paymentAmount" class="form-label">Amount (₹)</label>
                            <input type="number" class="form-control" id="paymentAmount" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="paymentNotes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="paymentNotes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitPayment">Process Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        console.log("Script loaded");
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM fully loaded");
            // Toggle sidebar functionality
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                sidebarToggle.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });
            
            // Get data from PHP
            const incomeData = <?php echo $incomeDataJson; ?>;
            const availableYears = <?php echo $yearsJson; ?>;
            const availableMonths = <?php echo $monthsJson; ?>;
            
            // Populate year filter
            const yearFilter = document.getElementById('yearFilter');
            yearFilter.innerHTML = '';
            availableYears.forEach(year => {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                yearFilter.appendChild(option);
            });
            
            // Populate month filter
            const monthFilter = document.getElementById('monthFilter');
            monthFilter.innerHTML = '';
            
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            availableMonths.forEach(month => {
                const option = document.createElement('option');
                option.value = month;
                option.textContent = monthNames[month - 1];
                monthFilter.appendChild(option);
            });
            
            // Set default values
            yearFilter.value = <?php echo $defaultYear; ?>;
            monthFilter.value = <?php echo $defaultMonth; ?>;
            
            // Function to filter data by month and year
            function filterData(data, month, year) {
                return data.filter(item => {
                    const date = new Date(item.date);
                    return date.getMonth() + 1 === parseInt(month) && date.getFullYear() === parseInt(year);
                });
            }
            
            // Function to update the income table
            function updateIncomeTable(data) {
                const tableBody = document.getElementById('incomeTableBody');
                tableBody.innerHTML = '';
                
                if (data.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                No income data available for the selected filters
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                let totalArchitecture = 0;
                let totalInterior = 0;
                let totalConstruction = 0;
                
                data.forEach(item => {
                    const date = new Date(item.date);
                    const formattedDate = date.toLocaleDateString('en-IN', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                    
                    // Convert string values to numbers
                    const architectureAmount = parseFloat(item.architecture) || 0;
                    const interiorAmount = parseFloat(item.interior) || 0;
                    const constructionAmount = parseFloat(item.construction) || 0;
                    
                    totalArchitecture += architectureAmount;
                    totalInterior += interiorAmount;
                    totalConstruction += constructionAmount;
                    
                    const total = architectureAmount + interiorAmount + constructionAmount;
                    
                    tableBody.innerHTML += `
                        <tr>
                            <td>${formattedDate}</td>
                            <td class="text-end ${architectureAmount === 0 ? 'zero-value' : ''}">₹${architectureAmount.toLocaleString('en-IN')}</td>
                            <td class="text-end ${interiorAmount === 0 ? 'zero-value' : ''}">₹${interiorAmount.toLocaleString('en-IN')}</td>
                            <td class="text-end ${constructionAmount === 0 ? 'zero-value' : ''}">₹${constructionAmount.toLocaleString('en-IN')}</td>
                            <td class="text-end fw-bold">₹${total.toLocaleString('en-IN')}</td>
                        </tr>
                    `;
                });
                
                // Update the footer totals
                document.getElementById('totalArchitecture').textContent = `₹${totalArchitecture.toLocaleString('en-IN')}`;
                document.getElementById('totalInterior').textContent = `₹${totalInterior.toLocaleString('en-IN')}`;
                document.getElementById('totalConstruction').textContent = `₹${totalConstruction.toLocaleString('en-IN')}`;
                document.getElementById('grandTotal').textContent = `₹${(totalArchitecture + totalInterior + totalConstruction).toLocaleString('en-IN')}`;
            }
            
            // Function to fetch income data from server
            function fetchIncomeData(month, year) {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', `get_income_data.php?month=${month}&year=${year}`, true);
                    
                    xhr.onload = function() {
                        if (this.status === 200) {
                            try {
                                const data = JSON.parse(this.responseText);
                                resolve(data);
                            } catch (e) {
                                reject(e);
                            }
                        } else {
                            reject(new Error(`Server returned status ${this.status}`));
                        }
                    };
                    
                    xhr.onerror = function() {
                        reject(new Error('Network error'));
                    };
                    
                    xhr.send();
                });
            }
            
            // Initialize income table with default filters
            const initialMonthIncome = "6"; // June
            const initialYearIncome = "2025"; // 2025
            document.getElementById('monthFilter').value = initialMonthIncome;
            document.getElementById('yearFilter').value = initialYearIncome;
            
            // Fetch initial data
            fetchIncomeData(initialMonthIncome, initialYearIncome)
                .then(data => {
                    updateIncomeTable(data);
                })
                .catch(error => {
                    console.error('Error fetching income data:', error);
                    document.getElementById('incomeTableBody').innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-4 text-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error loading data. Please try again later.
                            </td>
                        </tr>
                    `;
                });
            
            // Apply filters button click handler
            document.getElementById('applyFilters').addEventListener('click', function() {
                const selectedMonth = document.getElementById('monthFilter').value;
                const selectedYear = document.getElementById('yearFilter').value;
                
                // Show loading indicator
                document.getElementById('incomeTableBody').innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="ms-2">Loading data...</span>
                        </td>
                    </tr>
                `;
                
                // Fetch data with selected filters
                fetchIncomeData(selectedMonth, selectedYear)
                    .then(data => {
                        updateIncomeTable(data);
                    })
                    .catch(error => {
                        console.error('Error fetching income data:', error);
                        document.getElementById('incomeTableBody').innerHTML = `
                            <tr>
                                <td colspan="5" class="text-center py-4 text-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Error loading data. Please try again later.
                                </td>
                            </tr>
                        `;
                    });
            });
            
            // Load managers data
            loadManagers();
            
            // Set up manager filter event listeners
            document.getElementById('managerRoleFilter').addEventListener('change', function() {
                // Don't immediately load on role change, wait for Apply button
                // This allows users to change multiple filters before applying
            });
            
            // Set up month and year defaults
            const currentDate = new Date();
            document.getElementById('managerMonthFilter').value = currentDate.getMonth() + 1;
            document.getElementById('managerYearFilter').value = currentDate.getFullYear();
            
            // Apply filters button click handler
            document.getElementById('applyManagerFilters').addEventListener('click', function() {
                const selectedRole = document.getElementById('managerRoleFilter').value;
                const selectedMonth = document.getElementById('managerMonthFilter').value;
                const selectedYear = document.getElementById('managerYearFilter').value;
                
                loadManagers(selectedRole, selectedMonth, selectedYear);
            });
            
            // Set up payment modal
            setupPaymentModal();
        });
        
        // Function to load managers data
        function loadManagers(role = 'all', month = null, year = null) {
            // Show loading indicator
            document.getElementById('managersCardContainer').innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Loading managers data...</span>
                </div>
            `;
            
            // Build query parameters
            const params = new URLSearchParams();
            if (role !== 'all') {
                params.append('role', role);
            }
            if (month !== null) {
                params.append('month', month);
            }
            if (year !== null) {
                params.append('year', year);
            }
            
            const queryString = params.toString() ? `?${params.toString()}` : '';
            
            fetch(`get_managers.php${queryString}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Managers data:', data);
                    // Check if the response contains an error
                    if (data.error) {
                        throw new Error(data.message || 'Error loading managers data');
                    }
                    updateManagersTable(data);
                })
                .catch(error => {
                    console.error('Error fetching managers data:', error);
                    document.getElementById('managersCardContainer').innerHTML = `
                        <div class="col-12 text-center py-4 text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading managers data: ${error.message}
                        </div>
                    `;
                });
        }
        
        // Function to update the managers display
        function updateManagersTable(data) {
            const cardContainer = document.getElementById('managersCardContainer');
            cardContainer.innerHTML = '';
            
            // Check if data is valid array
            if (!Array.isArray(data) || data.length === 0) {
                cardContainer.innerHTML = `
                    <div class="col-12 text-center py-4 text-muted">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        No managers found with the selected filter
                    </div>
                `;
                return;
            }
            
            // Get filter period info from first manager (all have same filter)
            const filteredMonth = data[0]?.filtered_month;
            const filteredYear = data[0]?.filtered_year;
            
            // Show filter period if specified
            if (filteredMonth && filteredYear) {
                const monthNames = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                
                cardContainer.innerHTML = `
                    <div class="col-12 mb-3">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Showing payouts for <strong>${monthNames[filteredMonth - 1]} ${filteredYear}</strong>
                        </div>
                    </div>
                `;
            }
            
            data.forEach(manager => {
                const statusClass = manager.status === 'active' ? 'text-success' : 'text-danger';
                const statusIcon = manager.status === 'active' ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
                const roleClass = manager.role.includes('Studio') ? 'studio-header' : 'site-header';
                const isStudioManager = manager.role.includes('Studio');
                
                const card = document.createElement('div');
                card.className = 'col-md-6 col-lg-4 mb-4';
                card.innerHTML = `
                    <div class="card manager-card h-100 shadow-sm">
                        <div class="card-header ${roleClass} text-white">
                            <h5 class="card-title mb-0">${manager.role}</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar-sm me-3 rounded-circle">
                                    ${manager.profile_picture !== 'default.png' ? 
                                        `<img src="uploads/profile/${manager.profile_picture}" alt="${manager.name}" class="rounded-circle" width="48" height="48">` : 
                                        `<span>${manager.name.charAt(0).toUpperCase()}</span>`
                                    }
                                </div>
                                <div>
                                    <h5 class="mb-0">${manager.name}</h5>
                                    <small class="${statusClass}">
                                        <i class="bi ${statusIcon} me-1"></i>${manager.status}
                                    </small>
                                </div>
                            </div>
                            
                            <div class="total-payout mb-3 p-2 bg-light rounded text-center">
                                <h6 class="mb-1">Total Payable</h6>
                                <h4 class="mb-0 text-primary">₹${manager.total_payable}</h4>
                                <div class="small text-muted mt-1">
                                    <span>Remaining Amount: <strong class="text-danger">₹${manager.remaining_amount || 0}</strong></span>
                                </div>
                                <div class="small text-muted">
                                    <span>Amount Paid: <strong class="text-success">₹${manager.amount_paid || 0}</strong></span>
                                </div>
                            </div>
                            
                            <div class="manager-info">
                                <div class="payout-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-building me-2"></i>Architecture (5%)</span>
                                        <span class="fw-bold text-primary">₹${manager.architecture_payout}</span>
                                    </div>
                                    <div class="progress mt-1 mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-primary" style="width: 100%"></div>
                                    </div>
                                </div>
                                
                                <div class="payout-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-house me-2"></i>Interior (5%)</span>
                                        <span class="fw-bold text-primary">₹${manager.interior_payout}</span>
                                    </div>
                                    <div class="progress mt-1 mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-info" style="width: 100%"></div>
                                    </div>
                                </div>
                                
                                ${!isStudioManager ? `
                                <div class="payout-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-bricks me-2"></i>Construction (3%)</span>
                                        <span class="fw-bold text-primary">₹${manager.construction_payout}</span>
                                    </div>
                                    <div class="progress mt-1 mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: 60%"></div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <div class="payout-item mt-3 pt-2 border-top">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold"><i class="bi bi-calculator me-2"></i>Total Commission</span>
                                        <span class="fw-bold text-primary">₹${manager.total_commission}</span>
                                    </div>
                                    <div class="commission-breakdown mt-2 ps-3 small">
                                        <div class="d-flex justify-content-between">
                                            <span>Architecture Commission</span>
                                            <span class="text-primary">₹${manager.architecture_payout}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Interior Commission</span>
                                            <span class="text-primary">₹${manager.interior_payout}</span>
                                        </div>
                                        ${!isStudioManager ? `
                                        <div class="d-flex justify-content-between">
                                            <span>Construction Commission</span>
                                            <span class="text-primary">₹${manager.construction_payout}</span>
                                        </div>
                                        ` : ''}
                                        <div class="d-flex justify-content-between mt-2">
                                            <span>Fixed Remuneration</span>
                                            <span class="text-success">₹${manager.fixed_remuneration}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2 fw-bold">
                                            <span>Total Payable</span>
                                            <span class="text-primary">₹${manager.total_payable}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <a href="manager_payout.php?id=${manager.id}" class="btn btn-primary">
                                    <i class="bi bi-wallet me-1"></i>View Payout Details
                                </a>
                                <button class="btn btn-success pay-amount-btn" data-manager-id="${manager.id}" data-manager-name="${manager.name}">
                                    <i class="bi bi-cash me-1"></i>Pay Amount
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                cardContainer.appendChild(card);
            });
            
            // Initialize payment buttons after cards are rendered
            setupPaymentModal();
        }
        
        // Function to set up payment modal
        function setupPaymentModal() {
            console.log("Setting up payment modal");
            // Get all pay amount buttons
            const payButtons = document.querySelectorAll('.pay-amount-btn');
            console.log(`Found ${payButtons.length} payment buttons`);
            
            // Initialize the modal
            const paymentModalElement = document.getElementById('paymentModal');
            const paymentModal = new bootstrap.Modal(paymentModalElement);
            const paymentForm = document.getElementById('paymentForm');
            const paymentManagerId = document.getElementById('paymentManagerId');
            const paymentManagerName = document.getElementById('paymentManagerName');
            const projectNameSelect = document.getElementById('projectName');
            const projectTypeInput = document.getElementById('projectType');
            const clientNameInput = document.getElementById('clientName');
            const paymentDateInput = document.getElementById('paymentDate');
            
            // Set default payment date to today
            const today = new Date().toISOString().split('T')[0];
            paymentDateInput.value = today;
            
            // Add click event to all pay buttons
            payButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log("Pay button clicked");
                    const managerId = this.getAttribute('data-manager-id');
                    const managerName = this.getAttribute('data-manager-name');
                    
                    console.log(`Opening payment modal for manager: ${managerName} (ID: ${managerId})`);
                    
                    // Set manager ID and name in the form
                    paymentManagerId.value = managerId;
                    paymentManagerName.value = managerName;
                    
                    // Update modal title
                    document.getElementById('paymentModalLabel').textContent = `Process Payment for ${managerName}`;
                    
                    // Fetch projects for dropdown
                    fetchProjects();
                    
                    // Show modal
                    paymentModal.show();
                });
            });
            
            // Function to fetch projects for dropdown
            function fetchProjects() {
                fetch('fetch_project_data_for_modal.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log("Projects loaded:", data);
                        // Clear existing options
                        projectNameSelect.innerHTML = '<option value="">Select Project</option>';
                        
                        // Add project options
                        data.forEach(project => {
                            const option = document.createElement('option');
                            option.value = project.id;
                            option.textContent = project.project_name;
                            option.dataset.type = project.project_type;
                            option.dataset.client = project.client_name;
                            projectNameSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching projects:', error);
                        projectNameSelect.innerHTML = '<option value="">Error loading projects</option>';
                    });
            }
            
            // Handle project selection change
            projectNameSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    projectTypeInput.value = selectedOption.dataset.type;
                    clientNameInput.value = selectedOption.dataset.client;
                } else {
                    projectTypeInput.value = '';
                    clientNameInput.value = '';
                }
            });
            
            // Handle form submission
            document.getElementById('submitPayment').addEventListener('click', function() {
                // Check if form is valid
                if (!paymentForm.checkValidity()) {
                    paymentForm.reportValidity();
                    return;
                }
                
                // Collect form data
                const formData = new FormData();
                formData.append('manager_id', paymentManagerId.value);
                formData.append('project_id', projectNameSelect.value);
                formData.append('payment_date', paymentDateInput.value);
                formData.append('project_stage', document.getElementById('projectStage').value);
                formData.append('payment_mode', document.getElementById('paymentMode').value);
                formData.append('amount', document.getElementById('paymentAmount').value);
                formData.append('notes', document.getElementById('paymentNotes').value);
                
                // Send payment data to server
                fetch('process_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message
                        alert('Payment processed successfully!');
                        
                        // Close modal
                        paymentModal.hide();
                        
                        // Reload manager data to reflect changes
                        const selectedRole = document.getElementById('managerRoleFilter').value;
                        const selectedMonth = document.getElementById('managerMonthFilter').value;
                        const selectedYear = document.getElementById('managerYearFilter').value;
                        loadManagers(selectedRole, selectedMonth, selectedYear);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error processing payment:', error);
                    alert('Error processing payment. Please try again.');
                });
            });
        }
    </script>
</body>
</html>