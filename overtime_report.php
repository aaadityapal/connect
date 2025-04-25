<?php
session_start();
// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Senior Manager (Studio)') {
    // Redirect to login page if not authorized
    header('Location: login.php');
    exit();
}

// Database connection
require_once 'config/db_connect.php';

// Initialize filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$user_filter = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Get all users for the filter dropdown
$users_query = "SELECT id, username, designation FROM users WHERE status = 'active' ORDER BY username ASC";
$users_result = mysqli_query($conn, $users_query);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);

// Build the SQL query with filters
$query = "SELECT a.id, a.user_id, a.date, a.punch_in, a.punch_out, a.working_hours, 
        a.overtime_hours, a.overtime, a.status, a.remarks, u.username, u.designation 
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.overtime_hours > 0 ";

// Add date range filter
$query .= " AND a.date BETWEEN '$start_date' AND '$end_date'";

// Add user filter if selected
if (!empty($user_filter)) {
    $query .= " AND a.user_id = '$user_filter'";
}

// Add sorting
$query .= " ORDER BY $sort_by $sort_order";

// Execute query
$result = mysqli_query($conn, $query);

// Calculate total overtime hours
$total_query = "SELECT SUM(overtime_hours) as total_overtime 
                FROM attendance 
                WHERE overtime_hours > 0 
                AND date BETWEEN '$start_date' AND '$end_date'";
                
if (!empty($user_filter)) {
    $total_query .= " AND user_id = '$user_filter'";
}

$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_overtime = $total_row['total_overtime'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Reports</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-styles.css">
    <link rel="stylesheet" href="assets/css/reports.css">
    <style>
        /* Enhanced Professional Styling */
        :root {
            --primary-color: #4361ee;
            --primary-light: #eef2ff;
            --secondary-color: #10B981;
            --secondary-light: #ecfdf5;
            --warning-color: #F59E0B;
            --warning-light: #fffbeb;
            --danger-color: #EF4444;
            --danger-light: #fef2f2;
            --dark-color: #1F2937;
            --gray-color: #6B7280;
            --light-gray: #F3F4F6;
            --white-color: #FFFFFF;
            --body-bg: #f9fafb;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --header-height: 70px;
        }

        body {
            background-color: var(--body-bg);
            font-family: 'Inter', 'Segoe UI', Roboto, Arial, sans-serif;
            color: var(--dark-color);
            line-height: 1.5;
        }

        /* Dashboard Layout Refinements */
        .main-content {
            background-color: var(--body-bg);
            transition: all 0.3s ease;
        }

        .content-header {
            background: linear-gradient(to right, var(--primary-color), #6366F1);
            padding: 24px 30px;
            box-shadow: 0 4px 12px rgba(63, 81, 181, 0.15);
            margin-bottom: 30px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            color: white;
        }

        .content-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMjAwIDEwMGwxMDAtNTBWMEwwIDEwMGgyMDB6IiBmaWxsPSJyZ2JhKDI1NSwgMjU1LCAyNTUsIDAuMSkiLz48L3N2Zz4=');
            background-repeat: no-repeat;
            background-position: right;
            opacity: 0.3;
            pointer-events: none;
        }

        .content-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 16px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .content-header h1 i {
            font-size: 2rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 50%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .page-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-top: 6px;
            font-weight: normal;
            display: block;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 1;
        }

        .user-info .notification-icon {
            position: relative;
            background: rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .user-info .notification-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .user-info .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.5);
            transition: all 0.2s ease;
        }

        .user-avatar:hover {
            border-color: white;
            transform: scale(1.05);
        }

        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            transition: all 0.2s ease;
        }

        .user-dropdown:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .user-name {
            font-weight: 500;
            color: white;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-wrapper {
            padding: 0 24px 24px;
        }

        /* Summary Card Styling */
        .summary-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #4F46E5, #3B82F6);
            color: var(--white-color);
            padding: 24px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-title {
            font-size: 1.1rem;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        
        .summary-period {
            font-size: 0.85rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .summary-card i {
            font-size: 3rem;
            opacity: 0.8;
        }
        
        @media (max-width: 768px) {
            .summary-cards-container {
                grid-template-columns: 1fr;
            }
            
            .summary-card {
                padding: 20px;
            }
            
            .summary-value {
                font-size: 1.8rem;
            }
        }

        /* Filter Container */
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            background: var(--white-color);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            flex: 1;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--gray-color);
        }

        .filter-group input, 
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border 0.2s ease;
            background-color: var(--white-color);
        }

        .filter-group input:focus, 
        .filter-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white-color);
        }

        .btn-primary:hover {
            background-color: #3a56d4;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .export-btn {
            background-color: var(--secondary-color);
            color: var(--white-color);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .export-btn:hover {
            background-color: #0ea271;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        /* Table Styling */
        .table-responsive {
            background: var(--white-color);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .table thead {
            background-color: var(--light-gray);
        }

        .table th {
            padding: 16px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table th a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--gray-color);
        }

        .sort-icon {
            margin-left: 6px;
            font-size: 12px;
            color: var(--primary-color);
        }

        .table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .table tbody tr:last-child {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background-color: var(--primary-light);
        }

        .table td {
            padding: 16px 20px;
            font-size: 0.95rem;
            vertical-align: middle;
        }

        .overtime-highlight {
            color: #e74c3c;
            font-weight: 600;
        }

        .overtime-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            min-width: 90px;
        }

        .status-approved {
            background-color: var(--secondary-light);
            color: var(--secondary-color);
        }

        .status-pending {
            background-color: var(--warning-light);
            color: var(--warning-color);
        }

        .status-rejected {
            background-color: var(--danger-light);
            color: var(--danger-color);
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 6px;
        }

        .view-btn {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        .view-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .approve-btn {
            background-color: var(--secondary-light);
            color: var(--secondary-color);
        }

        .approve-btn:hover {
            background-color: var(--secondary-color);
            color: white;
        }

        .reject-btn {
            background-color: var(--danger-light);
            color: var(--danger-color);
        }

        .reject-btn:hover {
            background-color: var(--danger-color);
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 30px 20px;
            color: var(--gray-color);
            font-style: italic;
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--white-color);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: var(--dark-color);
        }

        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-color);
            transition: color 0.2s ease;
        }

        .close:hover {
            color: var(--danger-color);
        }

        .modal-body {
            padding: 24px;
        }

        /* Detail Grid */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 12px;
            align-items: start;
        }

        .detail-label {
            font-weight: 500;
            color: var(--gray-color);
        }

        .detail-value {
            color: var(--dark-color);
            word-break: break-word;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
            font-family: inherit;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-cancel {
            padding: 10px 20px;
            background-color: var(--light-gray);
            color: var(--gray-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background-color: #e5e7eb;
        }

        .btn-submit {
            padding: 10px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-submit:hover {
            background-color: #3a56d4;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .filter-group {
                min-width: 180px;
            }
        }

        @media (max-width: 992px) {
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .export-btn {
                width: 100%;
                justify-content: center;
            }
            
            .table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
                padding: 20px;
            }
            
            .user-info {
                align-self: flex-end;
            }
            
            .summary-card {
                padding: 20px;
            }
            
            .summary-value {
                font-size: 1.8rem;
            }
            
            .modal-content {
                width: 95%;
            }
            
            .detail-row {
                grid-template-columns: 1fr;
                gap: 4px;
            }
            
            .detail-label {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" id="toggle-btn">
                <i class="fas fa-chevron-left"></i>
            </div>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">MAIN</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="real.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-calendar-check"></i>
                        <span class="sidebar-text">Leaves</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-text">Employees</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-box"></i>
                        <span class="sidebar-text">Projects</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">ANALYTICS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i>
                        <span class="sidebar-text"> Employee Reports</span>
                    </a>
                </li>
                <li>
                    <a href="work_report.php">
                        <i class="fas fa-file-invoice"></i>
                        <span class="sidebar-text"> Work Reports</span>
                    </a>
                </li>
                <li>
                    <a href="attendance_report.php">
                        <i class="fas fa-clock"></i>
                        <span class="sidebar-text"> Attendance Reports</span>
                    </a>
                </li>
                <li class="active">
                    <a href="overtime_report.php">
                        <i class="fas fa-hourglass-half"></i>
                        <span class="sidebar-text"> Overtime Reports</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">SETTINGS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="manager_profile.php">
                        <i class="fas fa-user"></i>
                        <span class="sidebar-text">Profile</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-bell"></i>
                        <span class="sidebar-text">Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="manager_settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
                <li>
                    <a href="reset_password.php">
                        <i class="fas fa-lock"></i>
                        <span class="sidebar-text">Reset Password</span>
                    </a>
                </li>
            </ul>

            <!-- Add logout at the end of sidebar -->
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="sidebar-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <div class="header-title">
                    <h1>
                        <i class="fas fa-hourglass-half"></i>
                        Overtime Reports
                        <span class="page-subtitle">Track and manage employee overtime records</span>
                    </h1>
                </div>
                <div class="header-actions">
                    <div class="user-info">
                        <a href="#" class="notification-icon" title="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="badge">0</span>
                        </a>
                        <img src="<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'assets/default-avatar.png'; ?>" alt="User" class="user-avatar" title="<?php echo htmlspecialchars($_SESSION['username']); ?>">
                        <div class="user-dropdown">
                            <span class="user-name"><?php echo $_SESSION['username']; ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content-wrapper">
                <!-- Summary Cards -->
                <div class="summary-cards-container">
                    <div class="summary-card">
                        <div>
                            <div class="summary-title">Total Overtime Hours</div>
                            <div class="summary-value"><?php echo number_format($total_overtime, 2); ?> hours</div>
                            <div class="summary-period">
                                <i class="fas fa-calendar-alt"></i> 
                                <?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M, Y', strtotime($end_date)); ?>
                            </div>
                        </div>
                        <i class="fas fa-clock fa-3x"></i>
                    </div>
                    
                    <?php
                    // Calculate additional statistics if there are records
                    if (mysqli_num_rows($result) > 0) {
                        mysqli_data_seek($result, 0); // Reset result pointer
                        $employee_count = 0;
                        $total_days = 0;
                        $unique_employees = [];
                        
                        while ($row = mysqli_fetch_assoc($result)) {
                            if (!in_array($row['user_id'], $unique_employees)) {
                                $unique_employees[] = $row['user_id'];
                                $employee_count++;
                            }
                            $total_days++;
                        }
                        
                        mysqli_data_seek($result, 0); // Reset result pointer again
                    ?>
                    <div class="summary-card" style="background: linear-gradient(135deg, #2563EB, #7C3AED);">
                        <div>
                            <div class="summary-title">Employees with Overtime</div>
                            <div class="summary-value"><?php echo $employee_count; ?></div>
                            <div class="summary-period">From total workforce</div>
                        </div>
                        <i class="fas fa-users fa-3x"></i>
                    </div>
                    
                    <div class="summary-card" style="background: linear-gradient(135deg, #059669, #10B981);">
                        <div>
                            <div class="summary-title">Total Overtime Records</div>
                            <div class="summary-value"><?php echo $total_days; ?></div>
                            <div class="summary-period">Individual day records</div>
                        </div>
                        <i class="fas fa-calendar-check fa-3x"></i>
                    </div>
                    <?php } ?>
                </div>

                <!-- Filters -->
                <form action="" method="GET" class="filter-container">
                    <div class="filter-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="user_id">Employee:</label>
                        <select id="user_id" name="user_id">
                            <option value="">All Employees</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($user_filter == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']) . ' (' . htmlspecialchars($user['designation']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    
                    <button type="button" id="exportBtn" class="export-btn">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </form>

                <!-- Results Table -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&user_id=<?php echo $user_filter; ?>&sort_by=username&sort_order=<?php echo ($sort_by == 'username' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>">
                                        Employee
                                        <?php if ($sort_by == 'username'): ?>
                                            <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&user_id=<?php echo $user_filter; ?>&sort_by=designation&sort_order=<?php echo ($sort_by == 'designation' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>">
                                        Designation
                                        <?php if ($sort_by == 'designation'): ?>
                                            <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&user_id=<?php echo $user_filter; ?>&sort_by=date&sort_order=<?php echo ($sort_by == 'date' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>">
                                        Date
                                        <?php if ($sort_by == 'date'): ?>
                                            <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>
                                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&user_id=<?php echo $user_filter; ?>&sort_by=working_hours&sort_order=<?php echo ($sort_by == 'working_hours' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>">
                                        Working Hours
                                        <?php if ($sort_by == 'working_hours'): ?>
                                            <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&user_id=<?php echo $user_filter; ?>&sort_by=overtime_hours&sort_order=<?php echo ($sort_by == 'overtime_hours' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>">
                                        Overtime Hours
                                        <?php if ($sort_by == 'overtime_hours'): ?>
                                            <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['designation']); ?></td>
                                        <td><?php echo date('d M, Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo ($row['punch_in']) ? date('h:i A', strtotime($row['punch_in'])) : 'N/A'; ?></td>
                                        <td><?php echo ($row['punch_out']) ? date('h:i A', strtotime($row['punch_out'])) : 'N/A'; ?></td>
                                        <td><?php echo is_numeric($row['working_hours']) ? number_format((float)$row['working_hours'], 2) : $row['working_hours']; ?> hrs</td>
                                        <td class="overtime-highlight"><?php 
                                            if (is_numeric($row['overtime_hours'])) {
                                                echo number_format((float)$row['overtime_hours'], 2);
                                            } else if (strpos($row['overtime_hours'], ':') !== false) {
                                                // Handle time format like HH:MM:SS
                                                $parts = explode(':', $row['overtime_hours']);
                                                $hours = (int)$parts[0];
                                                $minutes = isset($parts[1]) ? (int)$parts[1] / 60 : 0;
                                                $seconds = isset($parts[2]) ? (int)$parts[2] / 3600 : 0;
                                                echo number_format($hours + $minutes + $seconds, 2);
                                            } else {
                                                echo $row['overtime_hours'];
                                            }
                                        ?> hrs</td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($row['overtime']) {
                                                case 'approved':
                                                    $status_class = 'status-approved';
                                                    break;
                                                case 'pending':
                                                    $status_class = 'status-pending';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'status-rejected';
                                                    break;
                                                default:
                                                    $status_class = 'status-pending';
                                            }
                                            ?>
                                            <span class="overtime-status <?php echo $status_class; ?>">
                                                <?php echo ucfirst($row['overtime'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="action-btn view-btn" data-id="<?php echo $row['id']; ?>" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($row['overtime'] == 'pending'): ?>
                                                <button class="action-btn approve-btn" data-id="<?php echo $row['id']; ?>" title="Approve Overtime">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="action-btn reject-btn" data-id="<?php echo $row['id']; ?>" title="Reject Overtime">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="no-data">No overtime records found for the selected period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Overtime Details</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="overtimeDetails">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <div class="modal" id="actionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="actionTitle">Approve Overtime</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="overtimeActionForm">
                    <input type="hidden" id="overtime_id" name="overtime_id">
                    <input type="hidden" id="action_type" name="action_type">
                    
                    <div class="form-group">
                        <label for="remarks">Remarks:</label>
                        <textarea id="remarks" name="remarks" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn-cancel" id="cancelAction">Cancel</button>
                        <button type="submit" class="btn-submit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            const toggleBtn = document.getElementById('toggle-btn');
            const sidebar = document.getElementById('sidebar');
            
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                document.querySelector('.main-content').classList.toggle('expanded');
                toggleBtn.classList.toggle('rotated');
            });
            
            // Detail view buttons
            const viewButtons = document.querySelectorAll('.view-btn');
            const detailModal = document.getElementById('detailModal');
            const detailClose = detailModal.querySelector('.close');
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    
                    // Fetch overtime details
                    fetch(`get_overtime_details.php?id=${id}`)
                        .then(response => response.json())
                        .then(data => {
                            const detailsDiv = document.getElementById('overtimeDetails');
                            // Format the details HTML based on the data
                            let html = `
                                <div class="detail-grid">
                                    <div class="detail-row">
                                        <div class="detail-label">Employee:</div>
                                        <div class="detail-value">${data.username}</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Date:</div>
                                        <div class="detail-value">${data.date}</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Punch In:</div>
                                        <div class="detail-value">${data.punch_in}</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Punch Out:</div>
                                        <div class="detail-value">${data.punch_out}</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Working Hours:</div>
                                        <div class="detail-value">${data.working_hours} hours</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Overtime Hours:</div>
                                        <div class="detail-value overtime-highlight">${data.overtime_hours} hours</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Status:</div>
                                        <div class="detail-value">
                                            <span class="overtime-status status-${data.overtime}">${data.overtime || 'Pending'}</span>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Remarks:</div>
                                        <div class="detail-value">${data.remarks || 'No remarks'}</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Work Report:</div>
                                        <div class="detail-value">${data.work_report || 'No work report'}</div>
                                    </div>
                                </div>
                            `;
                            detailsDiv.innerHTML = html;
                            detailModal.style.display = 'block';
                        })
                        .catch(error => {
                            console.error('Error fetching overtime details:', error);
                        });
                });
            });
            
            detailClose.addEventListener('click', function() {
                detailModal.style.display = 'none';
            });
            
            // Action buttons (approve/reject)
            const actionModal = document.getElementById('actionModal');
            const actionClose = actionModal.querySelector('.close');
            const cancelAction = document.getElementById('cancelAction');
            const actionForm = document.getElementById('overtimeActionForm');
            const actionTitle = document.getElementById('actionTitle');
            
            // Approve buttons
            const approveButtons = document.querySelectorAll('.approve-btn');
            approveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    document.getElementById('overtime_id').value = id;
                    document.getElementById('action_type').value = 'approve';
                    actionTitle.textContent = 'Approve Overtime';
                    actionModal.style.display = 'block';
                });
            });
            
            // Reject buttons
            const rejectButtons = document.querySelectorAll('.reject-btn');
            rejectButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    document.getElementById('overtime_id').value = id;
                    document.getElementById('action_type').value = 'reject';
                    actionTitle.textContent = 'Reject Overtime';
                    actionModal.style.display = 'block';
                });
            });
            
            // Close action modal
            actionClose.addEventListener('click', function() {
                actionModal.style.display = 'none';
            });
            
            cancelAction.addEventListener('click', function() {
                actionModal.style.display = 'none';
            });
            
            // Handle action form submission
            actionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('process_overtime_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Action completed successfully');
                        actionModal.style.display = 'none';
                        // Reload the page to show updated data
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error processing action:', error);
                    alert('An error occurred. Please try again.');
                });
            });
            
            // Export button
            document.getElementById('exportBtn').addEventListener('click', function() {
                // Create export URL with current filters
                const exportUrl = `export_overtime.php?start_date=${document.getElementById('start_date').value}&end_date=${document.getElementById('end_date').value}&user_id=${document.getElementById('user_id').value}`;
                
                // Open in new tab
                window.open(exportUrl, '_blank');
            });
            
            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === detailModal) {
                    detailModal.style.display = 'none';
                }
                if (event.target === actionModal) {
                    actionModal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html> 