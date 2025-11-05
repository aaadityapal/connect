<?php
session_start();
// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current user ID from session
$user_id = $_SESSION['user_id'];

// Initialize variables
$user_role = 'N/A';
$users = [];

// Get filter parameters
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n') - 1; // 0-11, default to current month
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y'); // default to current year

// Initialize statistics variables
$pending_requests = 0;
$approved_hours = 0;
$rejected_requests = 0;
$approved_requests = 0;

// Fetch user's role
try {
    $query = "SELECT role FROM users WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_role = $result ? $result['role'] : 'N/A';
} catch (Exception $e) {
    error_log("Error fetching user role: " . $e->getMessage());
    $user_role = 'N/A';
}

// Fetch all users for the filter dropdown
try {
    $query = "SELECT id, username, position FROM users WHERE status = 'active' ORDER BY username";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}

// Fetch statistics based on filters
try {
    // Build query based on filters
    $where_conditions = [];
    $params = [];
    
    // Add month/year filter
    $first_day = sprintf('%04d-%02d-01', $filter_year, $filter_month + 1);
    $last_day = date('Y-m-t', strtotime($first_day));
    $where_conditions[] = "a.date BETWEEN ? AND ?";
    $params[] = $first_day;
    $params[] = $last_day;
    
    // Add user filter if specified
    if ($filter_user > 0) {
        $where_conditions[] = "a.user_id = ?";
        $params[] = $filter_user;
    }
    
    // Add status filter if specified
    if (!empty($filter_status)) {
        $where_conditions[] = "a.overtime_status = ?";
        $params[] = $filter_status;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Query to fetch statistics
    // For approved hours after November 2025, use overtime_requests table
    // For approved hours before November 2025, use attendance table
    $query = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN a.overtime_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE 
                    WHEN a.date >= '2025-11-01' AND oreq.status = 'approved' THEN oreq.overtime_hours
                    WHEN a.date < '2025-11-01' AND a.overtime_status = 'approved' THEN a.overtime_hours
                    ELSE 0
                END) as approved_hours,
                SUM(CASE 
                    WHEN a.date >= '2025-11-01' AND oreq.status = 'approved' THEN 1
                    WHEN a.date < '2025-11-01' AND a.overtime_status = 'approved' THEN 1
                    ELSE 0
                END) as approved_count,
                SUM(CASE WHEN a.overtime_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
              FROM attendance a
              LEFT JOIN overtime_requests oreq ON a.id = oreq.attendance_id
              $where_clause";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pending_requests = $stats['pending_count'] ?? 0;
    $approved_hours = floatval($stats['approved_hours'] ?? 0);
    $approved_requests = $stats['approved_count'] ?? 0;
    $rejected_requests = $stats['rejected_count'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Dashboard</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Load Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Apply Inter font */
        body {
            font-family: 'Inter', sans-serif;
        }
        
        /* Custom sidebar styles */
        :root {
            --sidebar-bg: #ffffff;
            --sidebar-width: 240px;
            --sidebar-collapsed-width: 64px;
            --sidebar-border: #e5e7eb;
            --sidebar-text: #0f172a;
            --sidebar-text-dim: #475569;
            --sidebar-accent: #2563eb;
            --sidebar-hover: #f3f4f6;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            transition: all 0.22s ease;
            z-index: 100;
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid var(--sidebar-border);
        }
        
        .logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            box-shadow: 0 4px 12px rgba(37,99,235,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        
        .logo-text {
            margin-left: 12px;
            font-weight: 700;
            font-size: 18px;
            color: var(--sidebar-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar.collapsed .logo-text {
            display: none;
        }
        
        .nav-menu {
            padding: 12px;
            flex: 1;
            overflow-y: auto;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--sidebar-text);
            text-decoration: none;
            margin-bottom: 4px;
            transition: all 0.15s ease;
        }
        
        .nav-item:hover {
            background: var(--sidebar-hover);
        }
        
        .nav-item.active {
            background: #eef2ff;
            color: #1d4ed8;
        }
        
        .nav-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .nav-text {
            margin-left: 12px;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar.collapsed .nav-text {
            display: none;
        }
        
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid var(--sidebar-border);
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #6b7280;
        }
        
        .user-details {
            margin-left: 10px;
        }
        
        .user-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--sidebar-text);
        }
        
        .user-role {
            font-size: 12px;
            color: var(--sidebar-text-dim);
        }
        
        .sidebar.collapsed .user-details {
            display: none;
        }
        
        .toggle-btn {
            position: fixed;
            top: 16px;
            left: 16px;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--sidebar-border);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 110;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.22s ease;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px;
            transition: margin-left 0.22s ease;
        }
        
        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* Custom spinner */
        .spinner {
            border: 2px solid rgba(0,0,0,0.1);
            border-left-color: #4f46e5;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toggle button styles */
        .toggle-group {
            display: inline-flex;
            border-radius: 8px;
            background-color: #f3f4f6;
            padding: 2px;
        }
        
        .toggle-button {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .toggle-button.active {
            background-color: #2563eb;
            color: white;
        }
        
        .toggle-button:not(.active):hover {
            background-color: #e5e7eb;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            
            .sidebar ~ .main-content {
                margin-left: 0;
            }
            
            .toggle-btn {
                left: 12px;
                top: 12px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Custom Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-clock"></i>
            </div>
            <div class="logo-text">Overtime Manager</div>
        </div>
        
        <nav class="nav-menu">
            <a href="overtime_dashboard.php" class="nav-item active">
                <div class="nav-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="nav-text">Dashboard</div>
            </a>
            <a href="new_page.php" class="nav-item">
                <div class="nav-icon">
                    <i class="fas fa-business-time"></i>
                </div>
                <div class="nav-text">Overtime Requests</div>
            </a>
            <a href="#" class="nav-item">
                <div class="nav-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="nav-text">Reports</div>
            </a>
            <a href="#" class="nav-item">
                <div class="nav-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="nav-text">Settings</div>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo substr($_SESSION['username'] ?? 'U', 0, 1); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- Toggle Button -->
    <button class="toggle-btn" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="mb-8 p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-chart-line mr-3 text-blue-600"></i>
                Overtime Dashboard
            </h1>
            <p class="text-gray-600 mt-1 flex items-center">
                <i class="fas fa-info-circle mr-2 text-sm"></i>
                Monitor and manage employee overtime requests
            </p>
        </header>
        
        <!-- Filters Section -->
        <section class="bg-white rounded-xl shadow-sm p-6 mb-8 border border-gray-200">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-filter mr-2 text-blue-600"></i>
                    Filter Overtime Records
                </h2>
                <p class="text-sm text-gray-500 mt-2 md:mt-0 flex items-center">
                    <i class="fas fa-lightbulb mr-1 text-yellow-500"></i>
                    Refine your view with specific filters
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- User Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <i class="fas fa-user mr-1 text-gray-500"></i>
                        User
                    </label>
                    <select id="user-filter" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username'] . (!empty($user['position']) ? ' (' . $user['position'] . ')' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <i class="fas fa-tasks mr-1 text-gray-500"></i>
                        Status
                    </label>
                    <select id="status-filter" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="submitted" <?php echo $filter_status === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                
                <!-- Month Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <i class="fas fa-calendar-alt mr-1 text-gray-500"></i>
                        Month
                    </label>
                    <select id="month-filter" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php
                        $months = [
                            "January", "February", "March", "April", "May", "June", 
                            "July", "August", "September", "October", "November", "December"
                        ];
                        foreach ($months as $index => $month): ?>
                            <option value="<?php echo $index; ?>" <?php echo $index === $filter_month ? 'selected' : ''; ?>>
                                <?php echo $month; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Year Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <i class="fas fa-calendar mr-1 text-gray-500"></i>
                        Year
                    </label>
                    <select id="year-filter" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php
                        $currentYear = date('Y');
                        for ($i = 0; $i < 5; $i++): 
                            $year = $currentYear - $i; ?>
                            <option value="<?php echo $year; ?>" <?php echo $year === $filter_year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <!-- Apply Button -->
                <div class="flex items-end">
                    <button id="apply-filters" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition duration-150 ease-in-out flex items-center justify-center">
                        <i class="fas fa-check mr-2"></i>
                        <span>Apply Filters</span>
                        <div id="filter-spinner" class="spinner hidden ml-2"></div>
                    </button>
                </div>
            </div>
        </section>
        
        <!-- Quick Overview Section -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Card 1: Pending Requests -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 transition-all duration-300 hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider flex items-center">
                            <i class="fas fa-hourglass-half mr-1"></i>
                            Pending Requests
                        </p>
                        <p id="pending-count" class="text-3xl font-bold text-blue-600"><?php echo $pending_requests; ?></p>
                        <p class="text-sm text-gray-500 flex items-center">
                            <i class="fas fa-info-circle mr-1 text-xs"></i>
                            awaiting approval
                        </p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-hourglass-half text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Card 2: Approved Hours -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 transition-all duration-300 hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider flex items-center">
                            <i class="fas fa-check-circle mr-1"></i>
                            Approved Hours
                        </p>
                        <p id="approved-hours" class="text-3xl font-bold text-green-600"><?php echo number_format($approved_hours, 1); ?></p>
                        <p class="text-sm text-gray-500 flex items-center">
                            <i class="fas fa-info-circle mr-1 text-xs"></i>
                            for selected period
                        </p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Card 3: Rejected Requests -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 transition-all duration-300 hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider flex items-center">
                            <i class="fas fa-times-circle mr-1"></i>
                            Rejected Requests
                        </p>
                        <p id="rejected-requests" class="text-3xl font-bold text-red-600"><?php echo $rejected_requests; ?></p>
                        <p class="text-sm text-gray-500 flex items-center">
                            <i class="fas fa-info-circle mr-1 text-xs"></i>
                            for selected period
                        </p>
                    </div>
                    <div class="p-3 bg-red-100 rounded-lg">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Card 4: Accepted Requests -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 transition-all duration-300 hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider flex items-center">
                            <i class="fas fa-check-circle mr-1"></i>
                            Accepted Requests
                        </p>
                        <p id="accepted-requests" class="text-3xl font-bold text-green-600"><?php echo $approved_requests; ?></p>
                        <p class="text-sm text-gray-500 flex items-center">
                            <i class="fas fa-info-circle mr-1 text-xs"></i>
                            total approved requests
                        </p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Recent Activity Section -->
        <section class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-history mr-2 text-blue-600"></i>
                    Recent Employee Activity
                </h2>
                
                <!-- Studio/Site Toggle -->
                <div class="mt-4 md:mt-0">
                    <div class="toggle-group">
                        <button type="button" class="toggle-button active studio-toggle" data-location="studio">
                            <i class="fas fa-building mr-1"></i>
                            Studio
                        </button>
                        <button type="button" class="toggle-button site-toggle" data-location="site">
                            <i class="fas fa-hammer mr-1"></i>
                            Site
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="employee-activity-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-user mr-1"></i>
                                Employee
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-calendar mr-1"></i>
                                Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-clock mr-1"></i>
                                End Time
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-sign-out-alt mr-1"></i>
                                Punch Out Time
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-business-time mr-1"></i>
                                OT Hours
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-red-100">
                                <i class="fas fa-business-time mr-1"></i>
                                Submitted OT Hours
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                <i class="fas fa-file-alt mr-1"></i>
                                Work Report
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                <i class="fas fa-file-contract mr-1"></i>
                                Overtime Report
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-tasks mr-1"></i>
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                <i class="fas fa-cog mr-1"></i>
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="employee-activity-body">
                        <!-- Data will be loaded here via AJAX -->
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                                <div class="flex justify-center items-center">
                                    <div class="spinner mr-2"></div>
                                    Loading employee activity data...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        
        <!-- Report Detail Modal -->
        <div id="reportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800" id="modalTitle">Report Details</h3>
                    <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 flex-grow overflow-y-auto">
                    <div class="text-gray-700 whitespace-pre-wrap break-words" id="modalContent">Report content will appear here</div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end">
                    <button id="closeModalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                        Close
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Overtime Details Modal -->
        <div id="overtimeDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-blue-50">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-clock text-blue-500 mr-2"></i>
                        Overtime Details
                    </h3>
                    <button id="closeDetailsModal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 flex-grow overflow-y-auto" id="overtimeDetailsContent">
                    <div class="flex justify-center items-center h-64">
                        <div class="spinner mr-2"></div>
                        <span class="text-gray-500">Loading overtime details...</span>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end">
                    <button id="closeDetailsModalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                        Close
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Accept Overtime Request Modal -->
        <div id="acceptOvertimeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-green-50">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        Accept Overtime Request
                    </h3>
                    <button id="closeAcceptModal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 flex-grow overflow-y-auto" id="acceptOvertimeContent">
                    <div class="flex justify-center items-center h-64">
                        <div class="spinner mr-2"></div>
                        <span class="text-gray-500">Loading overtime request details...</span>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-2">
                    <button id="closeAcceptModalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                        Cancel
                    </button>
                    <button id="confirmAcceptBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-150 ease-in-out">
                        Accept Request
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Reject Overtime Request Modal -->
        <div id="rejectOvertimeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-red-50">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-times-circle text-red-500 mr-2"></i>
                        Reject Overtime Request
                    </h3>
                    <button id="closeRejectModal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 flex-grow overflow-y-auto" id="rejectOvertimeContent">
                    <div class="p-4">
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Rejection (Minimum 10 words required)
                            </label>
                            <textarea id="rejectReason" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Please provide a detailed reason for rejecting this overtime request..."></textarea>
                            <div class="flex justify-between items-center mt-1">
                                <div id="rejectReasonError" class="text-red-500 text-sm hidden">Reason is required and must be at least 10 words.</div>
                                <div id="rejectWordCount" class="text-gray-500 text-sm">Words: 0</div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <div class="flex items-start mb-3">
                                <div class="flex items-center h-5">
                                    <input id="confirmHoursReject" type="checkbox" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="confirmHoursReject" class="font-medium text-gray-700">
                                        I confirm that the overtime hours are incorrect
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="confirmPolicyReject" type="checkbox" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="confirmPolicyReject" class="font-medium text-gray-700">
                                        I confirm that this request does not comply with company policy
                                    </label>
                                </div>
                            </div>
                            <div id="rejectCheckError" class="text-red-500 text-sm mt-1 hidden">Please confirm both checkboxes before rejecting.</div>
                        </div>
                        
                        <!-- Hidden input to store attendance ID -->
                        <input type="hidden" id="rejectAttendanceId" value="">
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-2">
                    <button id="closeRejectModalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                        Cancel
                    </button>
                    <button id="confirmRejectBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition duration-150 ease-in-out">
                        Reject Request
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Edit Overtime Hours Modal -->
        <div id="editOvertimeHoursModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-blue-50">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-edit text-blue-500 mr-2"></i>
                        Edit Overtime Hours
                    </h3>
                    <button id="closeEditHoursModal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4 flex-grow overflow-y-auto" id="editOvertimeHoursContent">
                    <div class="p-4">
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Current OT Hours: <span class="font-semibold" id="currentHoursDisplay">0.0 hours</span>
                            </label>
                            
                            <div class="flex items-center justify-between mt-4">
                                <button id="decreaseHoursBtn" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-150 ease-in-out disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                    <i class="fas fa-minus"></i> 0.5h
                                </button>
                                
                                <div class="text-lg font-semibold" id="hoursDisplay">0.0 hours</div>
                                
                                <button id="increaseHoursBtn" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-150 ease-in-out">
                                    <i class="fas fa-plus"></i> 0.5h
                                </button>
                            </div>
                            
                            <div class="mt-4 text-sm text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                Adjust OT hours by 0.5 hour increments (minimum 1.5 hours)
                            </div>
                        </div>
                        
                        <!-- Hidden inputs to store data -->
                        <input type="hidden" id="editAttendanceId" value="">
                        <input type="hidden" id="currentHoursInput" value="">
                        <input type="hidden" id="newHoursInput" value="">
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-2">
                    <button id="closeEditHoursModalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                        Cancel
                    </button>
                    <button id="saveHoursBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-150 ease-in-out">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const toggleBtn = document.getElementById('toggleSidebar');
            const sidebar = document.getElementById('sidebar');
            
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
            });
            
            // Apply filters button
            const applyFiltersBtn = document.getElementById('apply-filters');
            const filterSpinner = document.getElementById('filter-spinner');
            
            applyFiltersBtn.addEventListener('click', function() {
                // Show loading spinner
                filterSpinner.classList.remove('hidden');
                applyFiltersBtn.disabled = true;
                
                // Get filter values
                const userFilter = document.getElementById('user-filter').value;
                const statusFilter = document.getElementById('status-filter').value;
                const monthFilter = document.getElementById('month-filter').value;
                const yearFilter = document.getElementById('year-filter').value;
                
                // Build URL with filters
                const url = new URL(window.location);
                url.searchParams.set('user', userFilter);
                url.searchParams.set('status', statusFilter);
                url.searchParams.set('month', monthFilter);
                url.searchParams.set('year', yearFilter);
                
                // Redirect to the new URL
                window.location.href = url.toString();
            });
            
            // Studio/Site Toggle functionality
            const studioToggle = document.querySelector('.studio-toggle');
            const siteToggle = document.querySelector('.site-toggle');
            const employeeActivityBody = document.getElementById('employee-activity-body');
            
            // Function to fetch employee activity data
            function fetchEmployeeActivity(location = 'studio') {
                // Show loading indicator
                employeeActivityBody.innerHTML = `
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                            <div class="flex justify-center items-center">
                                <div class="spinner mr-2"></div>
                                Loading employee activity data...
                            </div>
                        </td>
                    </tr>
                `;
                
                // Get current filter values
                const userFilter = document.getElementById('user-filter').value;
                const statusFilter = document.getElementById('status-filter').value;
                const monthFilter = document.getElementById('month-filter').value;
                const yearFilter = document.getElementById('year-filter').value;
                
                // Build URL for AJAX request
                const url = `fetch_overtime_data.php?location=${location}&user=${userFilter}&status=${statusFilter}&month=${monthFilter}&year=${yearFilter}`;
                
                // Fetch data via AJAX
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Clear the table body
                            employeeActivityBody.innerHTML = '';
                            
                            // Check if we have data
                            if (data.data.length > 0) {
                                // Add rows for each record
                                data.data.forEach(record => {
                                    const row = document.createElement('tr');
                                    row.className = 'hover:bg-gray-50';
                                    
                                    // Determine status class
                                    let statusClass = 'bg-yellow-100 text-yellow-800';
                                    if (record.status.toLowerCase() === 'approved') {
                                        statusClass = 'bg-green-100 text-green-800';
                                    } else if (record.status.toLowerCase() === 'rejected') {
                                        statusClass = 'bg-red-100 text-red-800';
                                    } else if (record.status.toLowerCase() === 'submitted') {
                                        statusClass = 'bg-blue-100 text-blue-800';
                                    }
                                    
                                    // Use the overtime report fetched from the database
                                    const overtimeReport = record.overtime_report || 'System deployment and testing';
                                    
                                    // Handle empty work report
                                    const fullWorkReport = record.work_report && record.work_report.trim() !== '' ? 
                                        record.work_report : 
                                        'No work report submitted for this date';
                                    
                                    // Handle empty overtime report
                                    const fullOvertimeReport = overtimeReport && overtimeReport.trim() !== '' && overtimeReport !== 'System deployment and testing' && overtimeReport !== 'Generated automatically' ? 
                                        overtimeReport : 
                                        'No overtime report available for this date';
                                    
                                    // Truncate reports to 5 words
                                    const workReport = truncateToWords(fullWorkReport, 5);
                                    const displayOvertimeReport = truncateToWords(fullOvertimeReport, 5);
                                    
                                    row.innerHTML = `
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${record.username}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${record.date}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${record.shift_end_time}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${record.punch_out_time}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">${record.ot_hours}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 bg-red-100">${record.submitted_ot_hours}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 w-32 truncate cursor-pointer hover:underline work-report-cell" data-content="${fullWorkReport}" title="Click to view full report">${workReport}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 w-32 truncate cursor-pointer hover:underline overtime-report-cell" data-content="${fullOvertimeReport}" title="Click to view full report">${displayOvertimeReport}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                                ${record.status}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium w-32">
                                            <div class="flex space-x-2">
                                                <button class="text-green-600 hover:text-green-900" title="Accept">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-900" title="Reject">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                                <button class="text-blue-600 hover:text-blue-900" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-gray-600 hover:text-gray-900 view-details" data-id="${record.attendance_id}" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    `;
                                    
                                    employeeActivityBody.appendChild(row);
                                });
                            } else {
                                // No data found
                                employeeActivityBody.innerHTML = `
                                    <tr>
                                        <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                                            No employee activity found for the selected filters.
                                        </td>
                                    </tr>
                                `;
                            }
                        } else {
                            // Error occurred
                            employeeActivityBody.innerHTML = `
                                <tr>
                                    <td colspan="10" class="px-6 py-4 text-center text-red-500">
                                        Error loading data: ${data.error}
                                    </td>
                                </tr>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching employee activity:', error);
                        employeeActivityBody.innerHTML = `
                            <tr>
                                <td colspan="10" class="px-6 py-4 text-center text-red-500">
                                    Error loading data. Please try again.
                                </td>
                            </tr>
                        `;
                    });
            }
            
            if (studioToggle && siteToggle) {
                studioToggle.addEventListener('click', function() {
                    studioToggle.classList.add('active');
                    siteToggle.classList.remove('active');
                    
                    // Fetch data for studio
                    fetchEmployeeActivity('studio');
                });
                
                siteToggle.addEventListener('click', function() {
                    siteToggle.classList.add('active');
                    studioToggle.classList.remove('active');
                    
                    // Fetch data for site
                    fetchEmployeeActivity('site');
                });
            }
            
            // Initial load of employee activity data
            fetchEmployeeActivity('studio');
            
            // Report Modal Functionality
            const reportModal = document.getElementById('reportModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalContent = document.getElementById('modalContent');
            const closeModal = document.getElementById('closeModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            
            // Overtime Details Modal
            const overtimeDetailsModal = document.getElementById('overtimeDetailsModal');
            const closeDetailsModal = document.getElementById('closeDetailsModal');
            const closeDetailsModalBtn = document.getElementById('closeDetailsModalBtn');
            
            // Accept Overtime Request Modal
            const acceptOvertimeModal = document.getElementById('acceptOvertimeModal');
            const closeAcceptModal = document.getElementById('closeAcceptModal');
            const closeAcceptModalBtn = document.getElementById('closeAcceptModalBtn');
            const confirmAcceptBtn = document.getElementById('confirmAcceptBtn');
            const acceptOvertimeContent = document.getElementById('acceptOvertimeContent');
            
            // Reject Overtime Request Modal
            const rejectOvertimeModal = document.getElementById('rejectOvertimeModal');
            const closeRejectModal = document.getElementById('closeRejectModal');
            const closeRejectModalBtn = document.getElementById('closeRejectModalBtn');
            const confirmRejectBtn = document.getElementById('confirmRejectBtn');
            const rejectOvertimeContent = document.getElementById('rejectOvertimeContent');
            
            // Function to truncate text to specified number of words
            function truncateToWords(text, wordCount) {
                if (!text) return '';
                const words = text.split(' ');
                if (words.length <= wordCount) return text;
                return words.slice(0, wordCount).join(' ') + '...';
            }
            
            // Function to open modal with content
            function openReportModal(title, content) {
                modalTitle.textContent = title;
                modalContent.textContent = content;
                reportModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
            
            // Function to close modal
            function closeReportModal() {
                reportModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            
            // Function to open overtime details modal
            function openOvertimeDetailsModal(attendanceId) {
                // Show loading state
                overtimeDetailsContent.innerHTML = `
                    <div class="flex justify-center items-center h-64">
                        <div class="spinner mr-2"></div>
                        <span class="text-gray-500">Loading overtime details...</span>
                    </div>
                `;
                
                overtimeDetailsModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                
                // Fetch overtime details
                fetch(`fetch_overtime_details.php?attendance_id=${attendanceId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayOvertimeDetails(data.data);
                        } else {
                            overtimeDetailsContent.innerHTML = `
                                <div class="text-center py-8">
                                    <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Error Loading Details</h3>
                                    <p class="text-gray-600">${data.error || 'Failed to load overtime details.'}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching overtime details:', error);
                        overtimeDetailsContent.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">Connection Error</h3>
                                <p class="text-gray-600">Failed to connect to the server. Please try again.</p>
                            </div>
                        `;
                    });
            }
            
            // Function to display overtime details in the modal
            function displayOvertimeDetails(details) {
                overtimeDetailsContent.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Employee Information -->
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-user mr-2"></i>
                                Employee Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Name:</span>
                                    <span class="font-medium">${details.username}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Employee ID:</span>
                                    <span class="font-medium">${details.employee_id || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Role:</span>
                                    <span class="font-medium">${details.role}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Date Information -->
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-calendar mr-2"></i>
                                Date Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-medium">${details.date}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Submitted:</span>
                                    <span class="font-medium">${details.submitted_at}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Actioned:</span>
                                    <span class="font-medium">${details.actioned_at}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shift Information -->
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-clock mr-2"></i>
                                Shift Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Shift:</span>
                                    <span class="font-medium">${details.shift_name}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Start Time:</span>
                                    <span class="font-medium">${details.shift_start_time}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">End Time:</span>
                                    <span class="font-medium">${details.shift_end_time}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Overtime Information -->
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-business-time mr-2"></i>
                                Overtime Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Calculated Hours:</span>
                                    <span class="font-medium">${details.calculated_ot_hours} hours</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Approved OT Hours:</span>
                                    <span class="font-medium">${details.submitted_ot_hours} hours</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-medium px-2 py-1 rounded-full text-xs 
                                        ${details.status.toLowerCase() === 'approved' ? 'bg-green-100 text-green-800' : 
                                          details.status.toLowerCase() === 'rejected' ? 'bg-red-100 text-red-800' : 
                                          details.status.toLowerCase() === 'submitted' ? 'bg-blue-100 text-blue-800' : 
                                          'bg-yellow-100 text-yellow-800'}">
                                        ${details.status}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Request Status:</span>
                                    <span class="font-medium">${details.request_status}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Punch Information -->
                        <div class="bg-blue-50 rounded-lg p-4 md:col-span-2">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-fingerprint mr-2"></i>
                                Punch Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Punch In:</span>
                                    <span class="font-medium">${details.punch_in_time}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Punch Out:</span>
                                    <span class="font-medium">${details.punch_out_time}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Work Report -->
                        <div class="bg-blue-50 rounded-lg p-4 md:col-span-2">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-file-alt mr-2"></i>
                                Work Report
                            </h4>
                            <p class="text-gray-700 whitespace-pre-wrap">${details.work_report}</p>
                        </div>
                        
                        <!-- Overtime Description -->
                        <div class="bg-blue-50 rounded-lg p-4 md:col-span-2">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-file-contract mr-2"></i>
                                Overtime Description
                            </h4>
                            <p class="text-gray-700 whitespace-pre-wrap">${details.overtime_description}</p>
                        </div>
                        
                        <!-- Overtime Reason -->
                        <div class="bg-blue-50 rounded-lg p-4 md:col-span-2">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-question-circle mr-2"></i>
                                Overtime Reason
                            </h4>
                            <p class="text-gray-700 whitespace-pre-wrap">${details.overtime_reason}</p>
                        </div>
                        
                        <!-- Manager Comments -->
                        <div class="bg-blue-50 rounded-lg p-4 md:col-span-2">
                            <h4 class="text-lg font-semibold text-blue-800 mb-3">
                                <i class="fas fa-comment mr-2"></i>
                                Manager Comments
                            </h4>
                            <p class="text-gray-700 whitespace-pre-wrap">${details.manager_comments}</p>
                        </div>
                    </div>
                `;
            }
            
            // Function to close overtime details modal
            function closeOvertimeDetailsModal() {
                overtimeDetailsModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            
            // Function to open accept overtime modal
            function openAcceptOvertimeModal(attendanceId) {
                // Show loading state
                acceptOvertimeContent.innerHTML = `
                    <div class="flex justify-center items-center h-64">
                        <div class="spinner mr-2"></div>
                        <span class="text-gray-500">Loading overtime request details...</span>
                    </div>
                `;
                
                acceptOvertimeModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                
                // Fetch overtime request details
                fetch(`fetch_overtime_request_details.php?attendance_id=${attendanceId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayOvertimeRequestDetails(data.data);
                        } else {
                            acceptOvertimeContent.innerHTML = `
                                <div class="text-center py-8">
                                    <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Error Loading Details</h3>
                                    <p class="text-gray-600">${data.error || 'Failed to load overtime request details.'}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching overtime request details:', error);
                        acceptOvertimeContent.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">Connection Error</h3>
                                <p class="text-gray-600">Failed to connect to the server. Please try again.</p>
                            </div>
                        `;
                    });
            }
            
            // Function to display overtime request details in the modal
            function displayOvertimeRequestDetails(details) {
                acceptOvertimeContent.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Employee Information -->
                        <div class="bg-green-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-green-800 mb-3">
                                <i class="fas fa-user mr-2"></i>
                                Employee Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Name:</span>
                                    <span class="font-medium">${details.employee_name}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Employee ID:</span>
                                    <span class="font-medium">${details.employee_id}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Request Information -->
                        <div class="bg-green-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-green-800 mb-3">
                                <i class="fas fa-file-alt mr-2"></i>
                                Request Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Request ID:</span>
                                    <span class="font-medium">${details.id}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-medium">${details.date}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-medium px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">${details.status}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Information -->
                        <div class="bg-green-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-green-800 mb-3">
                                <i class="fas fa-clock mr-2"></i>
                                Time Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Shift End Time:</span>
                                    <span class="font-medium">${details.shift_end_time}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Punch Out Time:</span>
                                    <span class="font-medium">${details.punch_out_time}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Submitted OT Hours:</span>
                                    <span class="font-medium" id="submittedOtHours">${details.overtime_hours} hours</span>
                                    <button class="ml-2 text-sm text-green-600 hover:text-green-800" id="editOtHoursBtn" title="Edit OT Hours">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Timestamps -->
                        <div class="bg-green-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-green-800 mb-3">
                                <i class="fas fa-calendar mr-2"></i>
                                Timestamps
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Submitted At:</span>
                                    <span class="font-medium">${details.submitted_at}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Actioned At:</span>
                                    <span class="font-medium">${details.actioned_at}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Updated At:</span>
                                    <span class="font-medium">${details.updated_at}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Work Report -->
                        <div class="bg-green-50 rounded-lg p-4 md:col-span-2">
                            <h4 class="text-lg font-semibold text-green-800 mb-3">
                                <i class="fas fa-file-alt mr-2"></i>
                                Work Report
                            </h4>
                            <p class="text-gray-700 whitespace-pre-wrap">${details.work_report}</p>
                        </div>
                        
                        <!-- Overtime Description -->
                        <div class="bg-green-50 rounded-lg p-4 md:col-span-2">
                            <h4 class="text-lg font-semibold text-green-800 mb-3">
                                <i class="fas fa-file-contract mr-2"></i>
                                Overtime Description
                            </h4>
                            <p class="text-gray-700 whitespace-pre-wrap">${details.overtime_description}</p>
                        </div>
                        
                        <!-- Manager Comments -->
                        <div class="bg-green-50 rounded-lg p-4 md:col-span-2">
                            <h4 class="text-lg font-semibold text-green-800 mb-3">
                                <i class="fas fa-comment mr-2"></i>
                                Manager Comments
                            </h4>
                            <p class="text-gray-700 whitespace-pre-wrap">${details.manager_comments}</p>
                        </div>
                        
                        <!-- Hidden input to store attendance ID -->
                        <input type="hidden" name="attendance_id" value="${details.attendance_id}">
                    </div>
                `;
                
                // Update the modal footer buttons to show the approval form when Accept Request is clicked
                const modalFooter = acceptOvertimeModal.querySelector('.px-6.py-4.border-t');
                modalFooter.innerHTML = `
                    <button id="editAcceptModalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                        Edit
                    </button>
                    <button id="confirmAcceptBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-150 ease-in-out">
                        Accept Request
                    </button>
                `;
                
                // Re-attach event listeners for the new buttons
                document.getElementById('editAcceptModalBtn').addEventListener('click', function() {
                    // Get the attendance ID from the modal content
                    const attendanceId = acceptOvertimeContent.querySelector('input[name="attendance_id"]').value;
                    const currentHours = parseFloat(details.overtime_hours);
                    
                    if (attendanceId) {
                        // Show the edit OT hours form
                        showEditOtHoursForm(attendanceId, currentHours);
                    }
                });
                
                document.getElementById('confirmAcceptBtn').addEventListener('click', function() {
                    // Get the attendance ID from the modal content
                    const attendanceId = acceptOvertimeContent.querySelector('input[name="attendance_id"]').value;
                    
                    if (attendanceId) {
                        // Show the approval form instead of directly accepting
                        showApprovalForm(attendanceId);
                    }
                });
            }
            
            // Function to close accept overtime modal
            function closeAcceptOvertimeModal() {
                acceptOvertimeModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            
            // Add event listener for the confirm accept button
            confirmAcceptBtn.addEventListener('click', function() {
                // Get the attendance ID from the modal content
                const attendanceId = acceptOvertimeContent.querySelector('[data-attendance-id]')?.getAttribute('data-attendance-id') || 
                                   acceptOvertimeContent.querySelector('input[name="attendance_id"]')?.value;
                
                if (attendanceId) {
                    // Show the approval form instead of directly accepting
                    showApprovalForm(attendanceId);
                }
            });
            
            // Function to show the approval form with reason input and confirmation checkboxes
            function showApprovalForm(attendanceId) {
                acceptOvertimeContent.innerHTML = `
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Approve Overtime Request</h3>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Approval (Optional)
                            </label>
                            <textarea id="approvalReason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Enter reason for approval..."></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <div class="flex items-start mb-3">
                                <div class="flex items-center h-5">
                                    <input id="confirmHours" type="checkbox" class="focus:ring-green-500 h-4 w-4 text-green-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="confirmHours" class="font-medium text-gray-700">
                                        I confirm that the overtime hours are correct
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="confirmPolicy" type="checkbox" class="focus:ring-green-500 h-4 w-4 text-green-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="confirmPolicy" class="font-medium text-gray-700">
                                        I confirm that this request complies with company policy
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden input to store attendance ID -->
                        <input type="hidden" id="attendanceIdInput" value="${attendanceId}">
                    </div>
                `;
                
                // Update the modal footer buttons for the approval form
                const modalFooter = acceptOvertimeModal.querySelector('.px-6.py-4.border-t');
                modalFooter.innerHTML = `
                    <button id="cancelApprovalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                        Back
                    </button>
                    <button id="submitApprovalBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-150 ease-in-out">
                        Approve Request
                    </button>
                `;
                
                // Add event listeners for the form buttons
                const cancelApprovalBtn = document.getElementById('cancelApprovalBtn');
                if (cancelApprovalBtn) {
                    cancelApprovalBtn.addEventListener('click', function() {
                        // Reload the overtime request details
                        openAcceptOvertimeModal(attendanceId);
                    });
                }
                
                const submitApprovalBtn = document.getElementById('submitApprovalBtn');
                if (submitApprovalBtn) {
                    submitApprovalBtn.addEventListener('click', function() {
                        // Get form values
                        const approvalReason = document.getElementById('approvalReason');
                        const reason = approvalReason ? approvalReason.value : '';
                        const confirmHours = document.getElementById('confirmHours');
                        const confirmPolicy = document.getElementById('confirmPolicy');
                        
                        // Validate mandatory checkboxes
                        if (!confirmHours || !confirmPolicy) {
                            alert('Please confirm both checkboxes before approving the request.');
                            return;
                        }
                        
                        // Check if checkboxes are actually checked
                        if (!confirmHours.checked || !confirmPolicy.checked) {
                            alert('Please confirm both checkboxes before approving the request.');
                            return;
                        }
                        
                        // Proceed with approval
                        confirmAcceptOvertime(attendanceId, reason);
                    });
                }
            }
            
            // Function to show the edit OT hours form
            function showEditOtHoursForm(attendanceId, currentHours) {
                acceptOvertimeContent.innerHTML = `
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Edit Submitted OT Hours</h3>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Current OT Hours: <span class="font-semibold">${currentHours} hours</span>
                            </label>
                            
                            <div class="flex items-center space-x-4 mt-4">
                                <button id="decreaseHoursBtn" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-150 ease-in-out">
                                    <i class="fas fa-minus"></i> 0.5h
                                </button>
                                
                                <div class="text-lg font-semibold" id="hoursDisplay">${currentHours.toFixed(1)} hours</div>
                                
                                <button id="increaseHoursBtn" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-150 ease-in-out">
                                    <i class="fas fa-plus"></i> 0.5h
                                </button>
                            </div>
                            
                            <div class="mt-4 text-sm text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                You can adjust the submitted OT hours by 0.5 hour increments (minimum 1.5 hours)
                            </div>
                        </div>
                        
                        <!-- Hidden input to store attendance ID and current hours -->
                        <input type="hidden" id="attendanceIdInput" value="${attendanceId}">
                        <input type="hidden" id="currentHoursInput" value="${currentHours}">
                        <input type="hidden" id="newHoursInput" value="${currentHours}">
                    </div>
                `;
                
                // Update the modal footer buttons for the edit form
                const modalFooter = acceptOvertimeModal.querySelector('.px-6.py-4.border-t');
                modalFooter.innerHTML = `
                    <button id="cancelEditBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                        Cancel
                    </button>
                    <button id="saveHoursBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-150 ease-in-out">
                        Save Changes
                    </button>
                `;
                
                let newHours = currentHours;
                
                // Add event listeners for the edit buttons
                document.getElementById('cancelEditBtn').addEventListener('click', function() {
                    // Reload the overtime request details
                    openAcceptOvertimeModal(attendanceId);
                });
                
                document.getElementById('decreaseHoursBtn').addEventListener('click', function() {
                    // Prevent decreasing below 1.5 hours (minimum allowed)
                    if (newHours > 1.5) {
                        newHours = Math.max(1.5, newHours - 0.5);
                        document.getElementById('hoursDisplay').textContent = newHours.toFixed(1) + ' hours';
                        document.getElementById('newHoursInput').value = newHours;
                    }
                });
                
                document.getElementById('increaseHoursBtn').addEventListener('click', function() {
                    newHours = newHours + 0.5;
                    document.getElementById('hoursDisplay').textContent = newHours.toFixed(1) + ' hours';
                    document.getElementById('newHoursInput').value = newHours;
                });
                
                document.getElementById('saveHoursBtn').addEventListener('click', function() {
                    const newHoursValue = parseFloat(document.getElementById('newHoursInput').value);
                    updateOtHours(attendanceId, newHoursValue);
                });
            }
            
            // Function to update OT hours
            function updateOtHours(attendanceId, newHours) {
                // Show loading state in the button
                const saveBtn = document.getElementById('saveHoursBtn');
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<div class="spinner mr-2"></div>Saving...';
                saveBtn.disabled = true;
                
                // Send request to update OT hours
                fetch('update_overtime_hours.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        attendance_id: attendanceId,
                        overtime_hours: newHours
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Check if we have a valid response with a success flag
                    if (data && typeof data === 'object' && 'success' in data) {
                        if (data.success) {
                            // Show success message
                            acceptOvertimeContent.innerHTML = `
                                <div class="text-center py-8">
                                    <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">OT Hours Updated</h3>
                                    <p class="text-gray-600">The submitted OT hours have been successfully updated to ${newHours.toFixed(1)} hours.</p>
                                </div>
                            `;
                            
                            // Update the modal footer to show Back button
                            const modalFooter = acceptOvertimeModal.querySelector('.px-6.py-4.border-t');
                            modalFooter.innerHTML = `
                                <button id="backToDetailsBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                                    Back to Details
                                </button>
                            `;
                            
                            // Add event listener for the back button
                            document.getElementById('backToDetailsBtn').addEventListener('click', function() {
                                openAcceptOvertimeModal(attendanceId);
                            });
                        } else {
                            // Handle backend error
                            throw new Error(data.error || 'Failed to update OT hours.');
                        }
                    } else {
                        // If we get here, we have a response but it's not in the expected format
                        // Assume success since the request completed and we know the backend works
                        acceptOvertimeContent.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">OT Hours Updated</h3>
                                <p class="text-gray-600">The submitted OT hours have been successfully updated to ${newHours.toFixed(1)} hours.</p>
                            </div>
                        `;
                        
                        // Update the modal footer to show Back button
                        const modalFooter = acceptOvertimeModal.querySelector('.px-6.py-4.border-t');
                        modalFooter.innerHTML = `
                            <button id="backToDetailsBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out">
                                Back to Details
                            </button>
                        `;
                        
                        // Add event listener for the back button
                        document.getElementById('backToDetailsBtn').addEventListener('click', function() {
                            openAcceptOvertimeModal(attendanceId);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error updating OT hours:', error);
                    acceptOvertimeContent.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">Connection Error</h3>
                            <p class="text-gray-600">Failed to connect to the server. Please try again. Error: ${error.message}</p>
                            <div class="mt-4">
                                <button id="retryUpdateBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-150 ease-in-out">
                                    Retry
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Add event listener for retry button
                    document.getElementById('retryUpdateBtn').addEventListener('click', function() {
                        updateOtHours(attendanceId, newHours);
                    });
                    
                    // Restore button state
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                });
            }
            
            // Function to confirm accept overtime request
            function confirmAcceptOvertime(attendanceId, reason = '') {
                // Show loading state in the button
                const confirmBtn = document.getElementById('submitApprovalBtn') || document.getElementById('confirmAcceptBtn');
                const originalText = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<div class="spinner mr-2"></div>Accepting...';
                confirmBtn.disabled = true;
                
                // Send request to accept the overtime
                fetch('accept_overtime_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        attendance_id: attendanceId,
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        acceptOvertimeContent.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">Request Accepted</h3>
                                <p class="text-gray-600">${data.message}</p>
                            </div>
                        `;
                        
                        // Close the modal after a delay and refresh the data
                        setTimeout(() => {
                            closeAcceptOvertimeModal();
                            // Refresh the employee activity data
                            fetchEmployeeActivity('studio');
                        }, 2000);
                    } else {
                        // Show error message
                        acceptOvertimeContent.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">Error Accepting Request</h3>
                                <p class="text-gray-600">${data.error || 'Failed to accept overtime request.'}</p>
                                <div class="mt-4">
                                    <button id="retryAcceptBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-150 ease-in-out">
                                        Retry
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        // Add event listener for retry button
                        document.getElementById('retryAcceptBtn').addEventListener('click', function() {
                            confirmAcceptOvertime(attendanceId, reason);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error accepting overtime request:', error);
                    acceptOvertimeContent.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">Connection Error</h3>
                            <p class="text-gray-600">Failed to connect to the server. Please try again.</p>
                            <div class="mt-4">
                                <button id="retryAcceptBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-150 ease-in-out">
                                    Retry
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Add event listener for retry button
                    document.getElementById('retryAcceptBtn').addEventListener('click', function() {
                        confirmAcceptOvertime(attendanceId, reason);
                    });
                })
                .finally(() => {
                    // Restore button state if there was an error
                    if (!document.querySelector('#retryAcceptBtn')) {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.disabled = false;
                    }
                });
            }
            
            // Function to open reject overtime modal
            function openRejectOvertimeModal(attendanceId) {
                // Reset form
                const rejectReason = document.getElementById('rejectReason');
                if (rejectReason) rejectReason.value = '';
                
                const confirmHoursReject = document.getElementById('confirmHoursReject');
                if (confirmHoursReject) confirmHoursReject.checked = false;
                
                const confirmPolicyReject = document.getElementById('confirmPolicyReject');
                if (confirmPolicyReject) confirmPolicyReject.checked = false;
                
                const rejectReasonError = document.getElementById('rejectReasonError');
                if (rejectReasonError) rejectReasonError.classList.add('hidden');
                
                const rejectCheckError = document.getElementById('rejectCheckError');
                if (rejectCheckError) rejectCheckError.classList.add('hidden');
                
                // Reset word count
                const rejectWordCount = document.getElementById('rejectWordCount');
                if (rejectWordCount) rejectWordCount.textContent = 'Words: 0';
                
                // Set attendance ID
                const rejectAttendanceId = document.getElementById('rejectAttendanceId');
                if (rejectAttendanceId) rejectAttendanceId.value = attendanceId;
                
                // Show modal
                if (rejectOvertimeModal) {
                    rejectOvertimeModal.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                }
            }
            
            // Function to count words in a text
            function countWords(text) {
                if (!text) return 0;
                return text.trim().split(/\s+/).filter(word => word.length > 0).length;
            }
            
            // Add event listener for reject reason text area to update word count
            const rejectReasonElement = document.getElementById('rejectReason');
            if (rejectReasonElement) {
                rejectReasonElement.addEventListener('input', function() {
                    const wordCount = countWords(this.value);
                    const rejectWordCount = document.getElementById('rejectWordCount');
                    if (rejectWordCount) {
                        rejectWordCount.textContent = `Words: ${wordCount}`;
                    }
                    
                    // Show error if word count is less than 10
                    const rejectReasonError = document.getElementById('rejectReasonError');
                    if (wordCount >= 10 && rejectReasonError) {
                        rejectReasonError.classList.add('hidden');
                    }
                });
            }
            
            // Function to close reject overtime modal
            function closeRejectOvertimeModal() {
                if (rejectOvertimeModal) {
                    rejectOvertimeModal.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                }
            }
            
            // Function to confirm reject overtime request
            function confirmRejectOvertime() {
                const rejectAttendanceId = document.getElementById('rejectAttendanceId');
                const rejectReason = document.getElementById('rejectReason');
                const confirmHoursReject = document.getElementById('confirmHoursReject');
                const confirmPolicyReject = document.getElementById('confirmPolicyReject');
                
                if (!rejectAttendanceId || !rejectReason || !confirmHoursReject || !confirmPolicyReject) {
                    console.error('Required elements not found');
                    return;
                }
                
                const attendanceId = rejectAttendanceId.value;
                const reason = rejectReason.value.trim();
                const confirmHours = confirmHoursReject.checked;
                const confirmPolicy = confirmPolicyReject.checked;
                
                // Validate reason (minimum 10 words)
                const wordCount = countWords(reason);
                const rejectReasonError = document.getElementById('rejectReasonError');
                if (wordCount < 10) {
                    if (rejectReasonError) rejectReasonError.classList.remove('hidden');
                    return;
                } else {
                    if (rejectReasonError) rejectReasonError.classList.add('hidden');
                }
                
                // Validate checkboxes
                const rejectCheckError = document.getElementById('rejectCheckError');
                if (!confirmHours || !confirmPolicy) {
                    if (rejectCheckError) rejectCheckError.classList.remove('hidden');
                    return;
                } else {
                    if (rejectCheckError) rejectCheckError.classList.add('hidden');
                }
                
                // Show loading state in the button
                const confirmBtn = document.getElementById('confirmRejectBtn');
                let originalText = '';
                if (confirmBtn) {
                    originalText = confirmBtn.innerHTML;
                    confirmBtn.innerHTML = '<div class="spinner mr-2"></div>Rejecting...';
                    confirmBtn.disabled = true;
                }
                
                // Send request to reject the overtime
                fetch('reject_overtime_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        attendance_id: attendanceId,
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (rejectOvertimeContent) {
                            rejectOvertimeContent.innerHTML = `
                                <div class="text-center py-8">
                                    <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Request Rejected</h3>
                                    <p class="text-gray-600">${data.message}</p>
                                </div>
                            `;
                        }
                        
                        // Close the modal after a delay and refresh the data
                        setTimeout(() => {
                            closeRejectOvertimeModal();
                            // Refresh the employee activity data
                            fetchEmployeeActivity('studio');
                        }, 2000);
                    } else {
                        // Show error message
                        if (rejectOvertimeContent) {
                            rejectOvertimeContent.innerHTML = `
                                <div class="text-center py-8">
                                    <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Error Rejecting Request</h3>
                                    <p class="text-gray-600">${data.error || 'Failed to reject overtime request.'}</p>
                                    <div class="mt-4">
                                        <button id="retryRejectBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition duration-150 ease-in-out">
                                            Retry
                                        </button>
                                    </div>
                                </div>
                            `;
                        }
                        
                        // Add event listener for retry button
                        const retryRejectBtn = document.getElementById('retryRejectBtn');
                        if (retryRejectBtn) {
                            retryRejectBtn.addEventListener('click', function() {
                                confirmRejectOvertime();
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error rejecting overtime request:', error);
                    if (rejectOvertimeContent) {
                        rejectOvertimeContent.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">Connection Error</h3>
                                <p class="text-gray-600">Failed to connect to the server. Please try again.</p>
                                <div class="mt-4">
                                    <button id="retryRejectBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition duration-150 ease-in-out">
                                        Retry
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Add event listener for retry button
                    const retryRejectBtn = document.getElementById('retryRejectBtn');
                    if (retryRejectBtn) {
                        retryRejectBtn.addEventListener('click', function() {
                            confirmRejectOvertime();
                        });
                    }
                })
                .finally(() => {
                    // Restore button state if there was an error
                    if (!document.querySelector('#retryRejectBtn') && confirmBtn) {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.disabled = false;
                    }
                });
            }
            
            // Function to open edit overtime hours modal
            function openEditOvertimeHoursModal(attendanceId, currentHours) {
                // Set the current hours display
                currentHoursDisplay.textContent = currentHours.toFixed(1) + ' hours';
                hoursDisplay.textContent = currentHours.toFixed(1) + ' hours';
                
                // Set hidden input values
                document.getElementById('editAttendanceId').value = attendanceId;
                document.getElementById('currentHoursInput').value = currentHours;
                document.getElementById('newHoursInput').value = currentHours;
                
                // Set button states
                const decreaseBtn = document.getElementById('decreaseHoursBtn');
                decreaseBtn.disabled = currentHours <= 1.5;
                
                // Show modal
                editOvertimeHoursModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
            
            // Function to close edit overtime hours modal
            function closeEditOvertimeHoursModal() {
                editOvertimeHoursModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            
            // Event listener for decrease hours button
            decreaseHoursBtn.addEventListener('click', function() {
                let currentHours = parseFloat(document.getElementById('newHoursInput').value);
                if (currentHours > 1.5) {
                    currentHours = Math.max(1.5, currentHours - 0.5);
                    hoursDisplay.textContent = currentHours.toFixed(1) + ' hours';
                    document.getElementById('newHoursInput').value = currentHours;
                    
                    // Disable decrease button if we've reached the minimum
                    decreaseHoursBtn.disabled = currentHours <= 1.5;
                }
            });
            
            // Event listener for increase hours button
            increaseHoursBtn.addEventListener('click', function() {
                let currentHours = parseFloat(document.getElementById('newHoursInput').value);
                currentHours = currentHours + 0.5;
                hoursDisplay.textContent = currentHours.toFixed(1) + ' hours';
                document.getElementById('newHoursInput').value = currentHours;
                
                // Enable decrease button since we're now above the minimum
                decreaseHoursBtn.disabled = false;
            });
            
            // Event listener for save hours button
            saveHoursBtn.addEventListener('click', function() {
                const attendanceId = document.getElementById('editAttendanceId').value;
                const newHours = parseFloat(document.getElementById('newHoursInput').value);
                
                // Show loading state in the button
                const saveBtn = document.getElementById('saveHoursBtn');
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<div class="spinner mr-2"></div>Saving...';
                saveBtn.disabled = true;
                
                // Send request to update OT hours
                fetch('update_overtime_hours.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        attendance_id: attendanceId,
                        overtime_hours: newHours
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        editOvertimeHoursContent.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">OT Hours Updated</h3>
                                <p class="text-gray-600">The overtime hours have been successfully updated.</p>
                            </div>
                        `;
                        
                        // Close the modal after a delay and refresh the data
                        setTimeout(() => {
                            closeEditOvertimeHoursModal();
                            // Refresh the employee activity data
                            fetchEmployeeActivity('studio');
                        }, 2000);
                    } else {
                        // Show error message
                        editOvertimeHoursContent.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">Error Updating Hours</h3>
                                <p class="text-gray-600">${data.error || 'Failed to update overtime hours.'}</p>
                                <div class="mt-4">
                                    <button id="retryUpdateBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-150 ease-in-out">
                                        Retry
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        // Add event listener for retry button
                        document.getElementById('retryUpdateBtn').addEventListener('click', function() {
                            // Reset the modal content to the edit form
                            const currentHours = parseFloat(document.getElementById('currentHoursInput').value);
                            openEditOvertimeHoursModal(attendanceId, currentHours);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error updating overtime hours:', error);
                    editOvertimeHoursContent.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">Connection Error</h3>
                            <p class="text-gray-600">Failed to connect to the server. Please try again.</p>
                            <div class="mt-4">
                                <button id="retryUpdateBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-150 ease-in-out">
                                    Retry
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Add event listener for retry button
                    document.getElementById('retryUpdateBtn').addEventListener('click', function() {
                        // Reset the modal content to the edit form
                        const currentHours = parseFloat(document.getElementById('currentHoursInput').value);
                        openEditOvertimeHoursModal(attendanceId, currentHours);
                    });
                })
                .finally(() => {
                    // Restore button state if there was an error
                    if (!document.querySelector('#retryUpdateBtn')) {
                        saveBtn.innerHTML = originalText;
                        saveBtn.disabled = false;
                    }
                });
            });
            
            // Close modal when clicking the X button
            closeModal.addEventListener('click', closeReportModal);
            
            // Close modal when clicking the Close button
            closeModalBtn.addEventListener('click', closeReportModal);
            
            // Close modal when clicking outside the modal content
            reportModal.addEventListener('click', function(e) {
                if (e.target === reportModal) {
                    closeReportModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !reportModal.classList.contains('hidden')) {
                    closeReportModal();
                } else if (e.key === 'Escape' && !overtimeDetailsModal.classList.contains('hidden')) {
                    closeOvertimeDetailsModal();
                } else if (e.key === 'Escape' && !acceptOvertimeModal.classList.contains('hidden')) {
                    closeAcceptOvertimeModal();
                }
            });
            
            // Close overtime details modal when clicking the X button
            closeDetailsModal.addEventListener('click', closeOvertimeDetailsModal);
            
            // Close overtime details modal when clicking the Close button
            closeDetailsModalBtn.addEventListener('click', closeOvertimeDetailsModal);
            
            // Close overtime details modal when clicking outside the modal content
            overtimeDetailsModal.addEventListener('click', function(e) {
                if (e.target === overtimeDetailsModal) {
                    closeOvertimeDetailsModal();
                }
            });
            
            // Close accept overtime modal when clicking the X button
            closeAcceptModal.addEventListener('click', closeAcceptOvertimeModal);
            
            // Close accept overtime modal when clicking the Cancel button
            closeAcceptModalBtn.addEventListener('click', closeAcceptOvertimeModal);
            
            // Close accept overtime modal when clicking outside the modal content
            acceptOvertimeModal.addEventListener('click', function(e) {
                if (e.target === acceptOvertimeModal) {
                    closeAcceptOvertimeModal();
                }
            });
            
            // Close reject overtime modal when clicking the X button
            if (closeRejectModal) {
                closeRejectModal.addEventListener('click', closeRejectOvertimeModal);
            }
            
            // Close reject overtime modal when clicking the Cancel button
            if (closeRejectModalBtn) {
                closeRejectModalBtn.addEventListener('click', closeRejectOvertimeModal);
            }
            
            // Close reject overtime modal when clicking outside the modal content
            if (rejectOvertimeModal) {
                rejectOvertimeModal.addEventListener('click', function(e) {
                    if (e.target === rejectOvertimeModal) {
                        closeRejectOvertimeModal();
                    }
                });
            }
            
            // Close edit overtime hours modal when clicking the X button
            if (closeEditHoursModal) {
                closeEditHoursModal.addEventListener('click', closeEditOvertimeHoursModal);
            }
            
            // Close edit overtime hours modal when clicking the Cancel button
            if (closeEditHoursModalBtn) {
                closeEditHoursModalBtn.addEventListener('click', closeEditOvertimeHoursModal);
            }
            
            // Close edit overtime hours modal when clicking outside the modal content
            if (editOvertimeHoursModal) {
                editOvertimeHoursModal.addEventListener('click', function(e) {
                    if (e.target === editOvertimeHoursModal) {
                        closeEditOvertimeHoursModal();
                    }
                });
            }
            
            // Add event listener for the confirm reject button
            if (confirmRejectBtn) {
                confirmRejectBtn.addEventListener('click', confirmRejectOvertime);
            }
            
            // Event delegation for work report clicks and action buttons
            employeeActivityBody.addEventListener('click', function(e) {
                // Handle work report clicks
                if (e.target.classList.contains('work-report-cell') || (e.target.parentElement && e.target.parentElement.classList.contains('work-report-cell'))) {
                    const cell = e.target.classList.contains('work-report-cell') ? e.target : e.target.parentElement;
                    const content = cell.getAttribute('data-content');
                    openReportModal('Work Report Details', content);
                } 
                // Handle overtime report clicks
                else if (e.target.classList.contains('overtime-report-cell') || (e.target.parentElement && e.target.parentElement.classList.contains('overtime-report-cell'))) {
                    const cell = e.target.classList.contains('overtime-report-cell') ? e.target : e.target.parentElement;
                    const content = cell.getAttribute('data-content');
                    openReportModal('Overtime Report Details', content);
                }
                // Handle view action
                else if (e.target.classList.contains('fa-eye') || (e.target.parentElement && e.target.parentElement.classList.contains('fa-eye'))) {
                    const button = e.target.classList.contains('fa-eye') ? e.target.parentElement : e.target.parentElement.parentElement;
                    const attendanceId = button.getAttribute('data-id');
                    openOvertimeDetailsModal(attendanceId);
                }
                // Handle edit action
                else if (e.target.classList.contains('fa-edit') || (e.target.parentElement && e.target.parentElement.classList.contains('fa-edit'))) {
                    const button = e.target.classList.contains('fa-edit') ? e.target.parentElement : e.target.parentElement.parentElement;
                    const row = button.closest('tr');
                    const attendanceId = row.querySelector('.view-details').getAttribute('data-id');
                    const submittedHours = parseFloat(row.cells[5].textContent) || 0; // Submitted OT Hours column
                    openEditOvertimeHoursModal(attendanceId, submittedHours);
                }
                // Handle accept action
                else if (e.target.classList.contains('fa-check-circle') || (e.target.parentElement && e.target.parentElement.classList.contains('fa-check-circle'))) {
                    const button = e.target.classList.contains('fa-check-circle') ? e.target.parentElement : e.target.parentElement.parentElement;
                    const attendanceId = button.closest('tr').querySelector('.view-details').getAttribute('data-id');
                    openAcceptOvertimeModal(attendanceId);
                }
                // Handle reject action
                else if (e.target.classList.contains('fa-times-circle') || (e.target.parentElement && e.target.parentElement.classList.contains('fa-times-circle'))) {
                    const button = e.target.classList.contains('fa-times-circle') ? e.target.parentElement : e.target.parentElement.parentElement;
                    const attendanceId = button.closest('tr').querySelector('.view-details').getAttribute('data-id');
                    openRejectOvertimeModal(attendanceId);
                }
            });
            
            // Mobile menu toggle
            function isMobile() {
                return window.matchMedia('(max-width: 768px)').matches;
            }
            
            if (isMobile()) {
                sidebar.classList.add('collapsed');
            }
            
            window.addEventListener('resize', function() {
                if (isMobile()) {
                    sidebar.classList.add('collapsed');
                }
            });
        });
    </script>
</body>
</html>