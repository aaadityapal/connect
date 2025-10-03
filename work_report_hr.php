<?php
session_start();
require_once 'config.php';

// Check authentication and role permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR', 'Senior Manager (Studio)'])) {
    header('Location: unauthorized.php');
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

// Base query
$query = "
    SELECT 
        a.date,
        a.work_report,
        u.username,
        u.role,
        u.unique_id
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.work_report IS NOT NULL 
    AND a.date BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

// Add user filter to query
if ($user_id) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_id;
}

$query .= " ORDER BY a.date DESC, u.username ASC";

// Fetch work reports
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$work_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all users for filter dropdown
$users_query = "SELECT id, username, unique_id FROM users WHERE deleted_at IS NULL ORDER BY username ASC";
$users_stmt = $pdo->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="work_report.css">
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

        body {
            font-family: 'Inter', sans-serif;
            background-color: #F9FAFB;
            color: var(--dark);
            margin: 0;
            padding: 0;
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
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .sidebar::-webkit-scrollbar {
            display: none;
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
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout button styles */
        .logout-link {
            margin-top: auto;
            color: black!important;
            background-color: #D22B2B;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            height: 100vh;
            overflow-y: auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .filter-group input, .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 0.25rem;
        }

        .apply-filters, .export-excel {
            align-self: flex-end;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .apply-filters {
            background-color: var(--primary);
            color: white;
        }

        .export-excel {
            background-color: #1D6F42;
            color: white;
        }

        /* Reports Summary */
        .reports-summary {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        /* Reports Grid */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .report-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .username {
            font-weight: 600;
            margin: 0;
        }

        .designation {
            font-size: 0.875rem;
            color: var(--gray);
            margin: 0;
        }

        .report-date {
            font-size: 0.875rem;
            color: var(--gray);
        }

        .report-content {
            margin-bottom: 1rem;
        }

        .report-content p {
            margin: 0;
            white-space: pre-wrap;
        }

        .report-footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 1rem;
        }

        .employee-id {
            font-size: 0.875rem;
            color: var(--gray);
        }

        .no-reports-container {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .no-reports-container i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
            <a href="manager_payouts.php" class="nav-link">
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
            <a href="admin/manage_geofence_locations.php" class="nav-link">
                <i class="bi bi-map"></i>
                Geofence Locations
            </a>
            <a href="travelling_allowanceh.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="hr_overtime_approval.php" class="nav-link">
                <i class="bi bi-clock"></i>
                Overtime Approval
            </a>
            <a href="hr_project_list.php" class="nav-link">
                <i class="bi bi-diagram-3-fill"></i>
                Projects
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="bi bi-key-fill"></i>
                Password Reset
            </a>
            <a href="hr_work_report.php" class="nav-link active">
                <i class="bi bi-file-earmark-text-fill"></i>
                Work Report
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="header">
                <h1><i class="fas fa-file-invoice"></i> Work Reports</h1>
            </div>

            <form class="filters" method="GET">
                <div class="filter-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" 
                           value="<?php echo $start_date; ?>">
                </div>

                <div class="filter-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" 
                           value="<?php echo $end_date; ?>">
                </div>

                <div class="filter-group">
                    <label for="user_id">Employee</label>
                    <select id="user_id" name="user_id">
                        <option value="">All Employees</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?> 
                                (<?php echo htmlspecialchars($user['unique_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="apply-filters">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
                <div class="filter-group">
                    <button type="button" id="export-excel" class="export-excel">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>
            </form>
            
            <div class="reports-summary">
                <p>
                    <i class="fas fa-calendar-check"></i> 
                    Showing reports from <strong><?php echo date('d M Y', strtotime($start_date)); ?></strong> 
                    to <strong><?php echo date('d M Y', strtotime($end_date)); ?></strong>
                    <?php if ($user_id): ?>
                        for selected employee
                    <?php else: ?>
                        for all employees
                    <?php endif; ?>
                </p>
            </div>

            <div class="reports-grid">
                <?php if (empty($work_reports)): ?>
                    <div class="no-reports-container">
                        <i class="fas fa-file-alt"></i>
                        <p>No work reports found for the selected criteria.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($work_reports as $report): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <div class="user-info">
                                    <span class="user-avatar"><?php echo strtoupper(substr($report['username'], 0, 1)); ?></span>
                                    <div>
                                        <h3 class="username"><?php echo htmlspecialchars($report['username']); ?></h3>
                                        <p class="designation"><?php echo htmlspecialchars($report['role']); ?></p>
                                    </div>
                                </div>
                                <span class="report-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('d M Y', strtotime($report['date'])); ?>
                                </span>
                            </div>
                            <div class="report-content">
                                <p><?php echo nl2br(htmlspecialchars($report['work_report'])); ?></p>
                            </div>
                            <div class="report-footer">
                                <span class="employee-id"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($report['unique_id']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="work_report.js"></script>
</body>
</html>