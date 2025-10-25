<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get current user ID
$current_user_id = $_SESSION['user_id'];

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $current_user_id;

// Check if user is admin or HR to allow viewing other users' reports
$is_admin = in_array($_SESSION['role'], ['admin', 'HR', 'Senior Manager (Studio)']);

// Base query for work reports
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

// Add user filter
if ($is_admin && $filter_user_id != 'all') {
    $query .= " AND a.user_id = ?";
    $params[] = $filter_user_id;
} else if (!$is_admin) {
    // Regular users can only see their own reports
    $query .= " AND a.user_id = ?";
    $params[] = $current_user_id;
}

$query .= " ORDER BY a.date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$work_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of dates to reports for easier lookup
$reports_by_date = [];
foreach ($work_reports as $report) {
    $reports_by_date[$report['date']] = $report;
}

// Fetch holidays in the date range
$holidays_query = "SELECT holiday_date, holiday_name FROM office_holidays WHERE holiday_date BETWEEN ? AND ?";
$holidays_stmt = $pdo->prepare($holidays_query);
$holidays_stmt->execute([$start_date, $end_date]);
$holidays = $holidays_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of dates to holidays for easier lookup
$holidays_by_date = [];
foreach ($holidays as $holiday) {
    $holidays_by_date[$holiday['holiday_date']] = $holiday['holiday_name'];
}

// Fetch leaves in the date range
$leaves_query = "
    SELECT 
        lr.id,
        lr.user_id,
        lr.leave_type,
        lr.start_date,
        lr.end_date,
        lr.reason,
        lr.status,
        lr.duration,
        lr.time_from,
        lr.time_to,
        u.username,
        u.role,
        u.unique_id,
        lt.name as leave_type_name
    FROM leave_request lr
    JOIN users u ON lr.user_id = u.id
    LEFT JOIN leave_types lt ON lr.leave_type = lt.id
    WHERE lr.status IN ('approved', 'pending')
    AND (
        (lr.start_date BETWEEN ? AND ?) OR
        (lr.end_date BETWEEN ? AND ?) OR
        (lr.start_date <= ? AND lr.end_date >= ?)
    )
";

$leaves_params = [$start_date, $end_date, $start_date, $end_date, $start_date, $end_date];

// Add user filter for leaves
if ($is_admin && $filter_user_id != 'all') {
    $leaves_query .= " AND lr.user_id = ?";
    $leaves_params[] = $filter_user_id;
} else if (!$is_admin) {
    // Regular users can only see their own leaves
    $leaves_query .= " AND lr.user_id = ?";
    $leaves_params[] = $current_user_id;
}

$leaves_stmt = $pdo->prepare($leaves_query);
$leaves_stmt->execute($leaves_params);
$leaves = $leaves_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of dates to leaves for easier lookup
$leaves_by_date = [];
foreach ($leaves as $leave) {
    // Calculate all dates covered by this leave
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $end->modify('+1 day'); // Include the end date
    
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval, $end);
    
    foreach ($daterange as $date) {
        $date_str = $date->format('Y-m-d');
        // Only include dates within our filter range
        if ($date_str >= $start_date && $date_str <= $end_date) {
            if (!isset($leaves_by_date[$date_str])) {
                $leaves_by_date[$date_str] = [];
            }
            $leaves_by_date[$date_str][] = $leave;
        }
    }
}

// Generate all dates in the range
$dates_in_range = [];
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    new DateTime($end_date . ' +1 day')
);

foreach ($period as $date) {
    $dates_in_range[] = $date->format('Y-m-d');
}

// Fetch all active users for admin dropdown
$users = [];
if ($is_admin) {
    $users_query = "SELECT id, username, unique_id FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY username ASC";
    $users_stmt = $pdo->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch user profile details including profile picture
try {
    $user_id = $_SESSION['user_id'] ?? null;
    $profile_query = "SELECT username, profile_picture FROM users WHERE id = ?";
    $stmt = $pdo->prepare($profile_query);
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set profile picture with proper fallback
    $profile_picture = !empty($user_data['profile_picture']) 
        ? $user_data['profile_picture'] 
        : 'assets/images/default-avatar.png';

} catch(PDOException $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    $profile_picture = 'assets/images/default-avatar.png';
}
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
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --dark: #0f172a;
            --light: #f8fafc;
            --border: #e2e8f0;
            --hover: #f1f5f9;
            --no-report: #fef2f2;
            --no-report-border: #fecaca;
            --holiday: #fff7ed;
            --holiday-border: #fed7aa;
            --holiday-text: #ea580c;
            --leave-approved: #f0f9ff;
            --leave-approved-border: #bae6fd;
            --leave-approved-text: #0369a1;
            --leave-pending: #fffbeb;
            --leave-pending-border: #fde68a;
            --leave-pending-text: #b45309;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: var(--dark);
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
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .sidebar::-webkit-scrollbar {
            display: none;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
        }

        .main-content.expanded {
            margin-left: 0;
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
            color: var(--secondary);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            text-decoration: none;
            display: block;
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
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        /* Hide scrollbar for nav element in Chrome, Safari and Opera */
        .sidebar nav::-webkit-scrollbar {
            display: none;
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

        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .container {
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 1rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--secondary);
        }

        .filter-group input, .filter-group select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.875rem;
            min-width: 150px;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            background: white;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-success {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
        }

        .btn-outline {
            background: white;
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--hover);
        }

        .report-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .report-item:last-child {
            border-bottom: none;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .report-date {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.875rem;
        }

        .report-content {
            background-color: var(--light);
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            font-size: 0.95rem;
        }

        .no-report-day {
            background-color: var(--no-report);
            border: 1px solid var(--no-report-border);
            border-radius: 6px;
            padding: 1.5rem;
            text-align: center;
            color: #991b1b;
        }

        .no-report-day .report-date {
            color: #991b1b;
        }

        .holiday-day {
            background-color: var(--holiday);
            border: 1px solid var(--holiday-border);
            border-radius: 6px;
            padding: 1.5rem;
            text-align: center;
            color: var(--holiday-text);
        }

        .holiday-day .report-date {
            color: var(--holiday-text);
        }

        .holiday-name {
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .no-reports {
            text-align: center;
            padding: 3rem;
            color: var(--secondary);
        }

        .no-reports i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-title {
            font-size: 0.875rem;
            color: var(--secondary);
            margin-bottom: 0.25rem;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        .leave-day {
            border-radius: 6px;
            padding: 1.5rem;
        }

        .leave-day.approved {
            background-color: var(--leave-approved);
            border: 1px solid var(--leave-approved-border);
            color: var(--leave-approved-text);
        }

        .leave-day.pending {
            background-color: var(--leave-pending);
            border: 1px solid var(--leave-pending-border);
            color: var(--leave-pending-text);
        }

        .leave-day .report-date {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .leave-content h6 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .leave-content p {
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .report-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .filter-actions {
                margin-left: 0;
                width: 100%;
            }
            
            .filter-actions .btn {
                flex: 1;
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
            <a href="user_work_report_dashboard.php" class="nav-link active">
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

    <!-- Add this button after the sidebar div -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="header">
            <div class="container d-flex justify-content-between align-items-center">
                <h1 class="page-title"><i class="fas fa-file-invoice me-2"></i> Work Reports</h1>
                <div class="user-actions d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                            alt="<?php echo htmlspecialchars($_SESSION['username']); ?>'s Profile" 
                            class="rounded-circle dropdown-toggle" 
                            width="40" 
                            height="40" 
                            role="button" 
                            id="profileDropdown" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false"
                            style="object-fit: cover;"
                            onerror="this.src='assets/images/default-avatar.png'">
                        
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li class="dropdown-header">
                                <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['role']); ?></small>
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
        </div>

        <div class="container">
            <div class="summary-card">
                <div>
                    <div class="summary-title">Total Reports</div>
                    <div class="summary-value"><?php echo count($work_reports); ?></div>
                </div>
                <div>
                    <div class="summary-title">Period</div>
                    <div class="summary-value"><?php echo date('M Y', strtotime($start_date)); ?></div>
                </div>
                <div>
                    <div class="summary-title">Total Days</div>
                    <div class="summary-value"><?php echo count($dates_in_range); ?></div>
                </div>
            </div>

            <div class="card">
                <form method="GET" class="filters">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                            value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                            value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>

                    <?php if ($is_admin): ?>
                    <div class="filter-group">
                        <label for="user_id">Employee</label>
                        <select id="user_id" name="user_id">
                            <option value="all" <?php echo ($filter_user_id == 'all') ? 'selected' : ''; ?>>All Employees</option>
                            <option value="<?php echo $current_user_id; ?>" <?php echo ($filter_user_id == $current_user_id) ? 'selected' : ''; ?>>
                                My Reports
                            </option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($filter_user_id == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['unique_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button type="button" id="export-excel" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($dates_in_range)): ?>
                        <div class="no-reports">
                            <i class="fas fa-file-alt"></i>
                            <h3 class="h5 mt-2">Invalid Date Range</h3>
                            <p class="mb-0">Please select a valid date range.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $report_count = 0;
                        foreach ($dates_in_range as $date): 
                            $report = isset($reports_by_date[$date]) ? $reports_by_date[$date] : null;
                            $holiday_name = isset($holidays_by_date[$date]) ? $holidays_by_date[$date] : null;
                            $date_leaves = isset($leaves_by_date[$date]) ? $leaves_by_date[$date] : [];
                            if ($report) $report_count++;
                        ?>
                            <?php if ($holiday_name): ?>
                                <div class="report-item">
                                    <div class="holiday-day">
                                        <div class="report-date">
                                            <i class="fas fa-gift me-1"></i>
                                            <?php echo date('d M Y', strtotime($date)); ?> 
                                            <span class="ms-2">(<?php echo date('l', strtotime($date)); ?>)</span>
                                        </div>
                                        <div class="holiday-name">
                                            <i class="fas fa-calendar-day me-1"></i>
                                            <?php echo htmlspecialchars($holiday_name); ?>
                                        </div>
                                        <p class="mb-0 mt-2">Office Holiday - No work report required</p>
                                    </div>
                                </div>
                            <?php elseif (!empty($date_leaves)): ?>
                                <?php foreach ($date_leaves as $leave): ?>
                                <div class="report-item">
                                    <div class="leave-day <?php echo $leave['status'] == 'approved' ? 'approved' : 'pending'; ?>">
                                        <div class="report-date">
                                            <i class="fas fa-door-open me-1"></i>
                                            <?php echo date('d M Y', strtotime($date)); ?> 
                                            <span class="ms-2">(<?php echo date('l', strtotime($date)); ?>)</span>
                                            <span class="float-end badge <?php echo $leave['status'] == 'approved' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </span>
                                        </div>
                                        <div class="leave-content">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($leave['leave_type_name'] ?? $leave['leave_type']); ?>
                                            </h6>
                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($leave['reason'])); ?></p>
                                            <small class="text-muted">
                                                <?php if ($leave['start_date'] == $leave['end_date']): ?>
                                                    Full day leave
                                                <?php else: ?>
                                                    <?php echo date('d M Y', strtotime($leave['start_date'])); ?> to 
                                                    <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <?php if ($is_admin): ?>
                                        <div class="mt-2 text-muted small">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($leave['username']); ?> 
                                            <span class="mx-2">•</span>
                                            <?php echo htmlspecialchars($leave['role']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php elseif ($report): ?>
                                <div class="report-item">
                                    <div class="report-header">
                                        <div class="report-date">
                                            <i class="far fa-calendar me-1"></i>
                                            <?php echo date('d M Y', strtotime($report['date'])); ?> 
                                            <span class="text-muted ms-2">(<?php echo date('l', strtotime($report['date'])); ?>)</span>
                                        </div>
                                        <div class="report-id">
                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($report['unique_id']); ?></span>
                                        </div>
                                    </div>
                                    <div class="report-content">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['work_report'])); ?></p>
                                    </div>
                                    <?php if ($is_admin): ?>
                                    <div class="mt-2 text-muted small">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($report['username']); ?> 
                                        <span class="mx-2">•</span>
                                        <?php echo htmlspecialchars($report['role']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="report-item">
                                    <div class="no-report-day">
                                        <div class="report-date">
                                            <i class="far fa-calendar me-1"></i>
                                            <?php echo date('d M Y', strtotime($date)); ?> 
                                            <span class="ms-2">(<?php echo date('l', strtotime($date)); ?>)</span>
                                        </div>
                                        <p class="mb-0 mt-2">No work report submitted for this day</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if ($report_count == 0 && empty($holidays)): ?>
                            <div class="no-reports">
                                <i class="fas fa-file-alt"></i>
                                <h3 class="h5 mt-2">No Work Reports Found</h3>
                                <p class="mb-0">
                                    <?php if ($is_admin && isset($_GET['user_id']) && $_GET['user_id'] != 'all'): ?>
                                        The selected employee hasn't submitted any work reports for the selected period.
                                    <?php elseif ($is_admin && isset($_GET['user_id']) && $_GET['user_id'] == 'all'): ?>
                                        No employees have submitted work reports for the selected period.
                                    <?php else: ?>
                                        You haven't submitted any work reports for the selected period.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleButton = document.getElementById('sidebarToggle');
            
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

            // Export to Excel functionality
            document.getElementById('export-excel').addEventListener('click', function() {
                const start = document.getElementById('start_date').value;
                const end = document.getElementById('end_date').value;
                const user = document.getElementById('user_id') ? document.getElementById('user_id').value : '';
                
                // Create URL with parameters
                const params = new URLSearchParams();
                if (start) params.set('start_date', start);
                if (end) params.set('end_date', end);
                if (user) params.set('user_id', user);
                
                // Redirect to export script
                window.location.href = 'export_user_work_reports.php?' + params.toString();
            });
            
            // Date validation
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            startDate.addEventListener('change', function() {
                if (endDate.value && this.value > endDate.value) {
                    endDate.value = this.value;
                }
            });
            
            endDate.addEventListener('change', function() {
                if (startDate.value && this.value < startDate.value) {
                    startDate.value = this.value;
                }
            });
        });
    </script>
</body>
</html>