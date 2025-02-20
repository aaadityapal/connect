<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Fetch employee data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch assigned tasks
$stmt = $pdo->prepare("
    SELECT tasks.*, users.username as assigned_by 
    FROM tasks 
    JOIN users ON tasks.created_by = users.id 
    WHERE tasks.assigned_to = ? 
    ORDER BY 
        CASE 
            WHEN tasks.status = 'pending' THEN 1
            WHEN tasks.status = 'in_progress' THEN 2
            WHEN tasks.status = 'completed' THEN 3
        END,
        CASE 
            WHEN tasks.priority = 'high' THEN 1
            WHEN tasks.priority = 'medium' THEN 2
            WHEN tasks.priority = 'low' THEN 3
        END,
        tasks.due_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll();

// Fetch sales data with error handling
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN service_type = 'architecture' THEN total_price ELSE 0 END) as architecture_sales,
            SUM(CASE WHEN service_type = 'interior' THEN total_price ELSE 0 END) as interior_sales,
            SUM(CASE WHEN service_type = 'construction' THEN total_price ELSE 0 END) as construction_sales,
            SUM(total_price) as total_sales
        FROM orders
        WHERE assigned_to = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $salesData = $stmt->fetch();

    $architectureSales = $salesData['architecture_sales'] ?? 0;
    $interiorSales = $salesData['interior_sales'] ?? 0;
    $constructionSales = $salesData['construction_sales'] ?? 0;
    $totalSales = $salesData['total_sales'] ?? 0;
} catch (PDOException $e) {
    // If table doesn't exist or other database error, set default values
    $architectureSales = 0;
    $interiorSales = 0;
    $constructionSales = 0;
    $totalSales = 0;
}

// Remove these hardcoded values
// $totalSales = 15750.25;
// $totalOrders = 142;
// $totalCustomers = 89;
// $averageOrder = 110.92;

// Add this function at the top of your PHP code
function formatIndianCurrency($number) {
    $formatted = number_format($number, 2);
    if ($number >= 10000000) { // Convert to Crores
        $formatted = number_format($number/10000000, 2) . ' Cr';
    } else if ($number >= 100000) { // Convert to Lakhs
        $formatted = number_format($number/100000, 2) . ' L';
    }
    return '₹' . $formatted;
}

// Fetch task documents
$stmt = $pdo->prepare("
    SELECT td.*, t.title as task_title, u.username as uploaded_by 
    FROM task_documents td
    JOIN tasks t ON td.task_id = t.id
    JOIN users u ON td.uploaded_by = u.id
    WHERE t.assigned_to = ?
    ORDER BY td.uploaded_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$taskDocuments = $stmt->fetchAll();

// Add this near the top of the PHP section
date_default_timezone_set('Asia/Kolkata'); // Set to your timezone
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

// Add this function to check if employee is already punched in
function isAlreadyPunchedIn($pdo, $userId, $date) {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? AND punch_out IS NULL");
    $stmt->execute([$userId, $date]);
    return $stmt->fetch();
}

$isPunchedIn = isAlreadyPunchedIn($pdo, $_SESSION['user_id'], $currentDate);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f5f6fa;
            --text-color: #2d3436;
            --sidebar-width: 250px;
        }

        body {
            background-color: #f0f2f5;
        }

        .container {
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            position: fixed;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .left-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .logo {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 30px;
            text-align: center;
            padding: 10px 0;
        }

        .nav-item {
            padding: 12px 15px;
            margin: 8px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text-color);
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-item:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            width: calc(100% - var(--sidebar-width));
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 24px;
            color: var(--primary-color);
            font-weight: bold;
        }

        /* Recent Activity Section */
        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #ff6b6b;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .logout-btn:hover {
            background-color: #ff5252;
            color: white;
        }

        .logout-btn i {
            font-size: 18px;
            color: black;
        }

        .logout-btn span {
            font-size: 14px;
            color: black;
        }

        .tasks-section {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .tasks-section h2 {
            margin-bottom: 20px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .task-filters {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background: #f0f2f5;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .tasks-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .task-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .priority-badge, .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .priority-badge.high {
            background: #ffe0e0;
            color: #d63031;
        }

        .priority-badge.medium {
            background: #fff3e0;
            color: #fd9644;
        }

        .priority-badge.low {
            background: #e0f2e9;
            color: #00b894;
        }

        .status-badge.pending {
            background: #e0e0e0;
            color: #636e72;
        }

        .status-badge.in_progress {
            background: #e0f2ff;
            color: #0984e3;
        }

        .status-badge.completed {
            background: #e0ffe4;
            color: #00b894;
        }

        .task-title {
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .task-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .task-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #666;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .task-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .status-update {
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            cursor: pointer;
        }

        .view-details-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            background: var(--primary-color);
            color: white;
            cursor: pointer;
            transition: background 0.3s;
        }

        .view-details-btn:hover {
            background: var(--secondary-color);
        }

        .no-tasks {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-tasks i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #ddd;
        }

        /* Add these styles to your existing CSS */
        .documents-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .upload-form {
            max-width: 600px;
            margin: 20px 0;
        }

        .file-upload {
            margin: 20px 0;
            position: relative;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload label {
            display: block;
            padding: 20px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload label:hover {
            background: #e9ecef;
            border-color: var(--primary-color);
        }

        .file-upload i {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .upload-btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .upload-btn:hover {
            background: var(--secondary-color);
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .document-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
            display: flex;
            align-items: start;
            gap: 15px;
        }

        .document-icon {
            font-size: 24px;
            color: var(--primary-color);
        }

        .document-info {
            flex-grow: 1;
        }

        .document-info h4 {
            margin-bottom: 5px;
        }

        .document-info p {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .document-info small {
            color: #999;
        }

        .document-actions {
            display: flex;
            gap: 10px;
        }

        .document-actions button,
        .document-actions a {
            padding: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            text-decoration: none;
        }

        .download-btn {
            background: #3498db;
        }

        .delete-btn {
            background: #e74c3c;
        }

        /* Add these styles in the <style> section */
        .sales-dashboard {
            margin-bottom: 20px;
        }

        .sales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .sales-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .sales-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sales-icon i {
            font-size: 24px;
            color: white;
        }

        .sales-info h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .sales-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .sales-trend {
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .sales-trend.positive {
            color: #00b894;
        }

        .sales-trend.negative {
            color: #ff6b6b;
        }

        .sales-trend i {
            font-size: 10px;
        }

        /* Updated Document Section Styles */
        .documents-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin: 20px 0;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .upload-form {
            background: #f8fafc;
            padding: 24px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 500;
        }

        .form-input,
        .task-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-input:focus,
        .task-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        }

        .file-upload {
            margin: 20px 0;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px;
            background: white;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload label:hover {
            border-color: var(--primary-color);
            background: #f8fafc;
        }

        .file-upload i {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 12px;
        }

        .file-upload small {
            margin-top: 8px;
            color: #94a3b8;
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .document-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: start;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .document-icon {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 20px;
        }

        .document-info {
            flex: 1;
        }

        .document-info h4 {
            margin: 0 0 4px 0;
            color: #1e293b;
        }

        .document-info p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0 0 8px 0;
        }

        .document-meta {
            display: flex;
            gap: 12px;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .document-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .document-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .download-btn {
            background: #e0f2fe;
            color: #0284c7;
        }

        .delete-btn {
            background: #fee2e2;
            color: #ef4444;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .no-documents {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .no-documents i {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .time-attendance {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .datetime {
            text-align: right;
        }

        .current-time {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .current-date {
            color: #666;
            font-size: 14px;
        }

        .punch-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        #punchInBtn {
            background-color: #4CAF50;
            color: white;
        }

        #punchInBtn:hover {
            background-color: #45a049;
        }

        .punch-btn.punch-out {
            background-color: #ff6b6b;
            color: white;
        }

        .punch-btn.punch-out:hover {
            background-color: #ff5252;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background: #4CAF50;
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast i {
            font-size: 20px;
        }

        /* Add these styles to your existing CSS */
        .left-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .logout-container {
            margin-top: auto;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ff6b6b;
            text-decoration: none;
            padding: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background-color: rgba(255, 107, 107, 0.1);
        }

        .logout-btn i {
            font-size: 18px;
        }

        .logout-btn span {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="left-panel">
                <div class="logo">
                    <i class="fas fa-building"></i> ArchitectsHive
                </div>
                
                <!-- Navigation Links -->
                <div class="nav-links">
                    <a href="#" class="nav-item">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="tasks.php" class="nav-item">
                        <i class="fas fa-tasks"></i>
                        <span>My Tasks</span>
                        <?php if (isset($pendingTasks) && $pendingTasks > 0): ?>
                            <span class="badge"><?php echo $pendingTasks; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="projects.php" class="nav-item">
                        <i class="fas fa-project-diagram"></i>
                        <span>Projects</span>
                    </a>
                    <a href="discussions.php" class="nav-item">
                        <i class="fas fa-comments"></i>
                        <span>Discussions</span>
                        <?php if (isset($unreadDiscussions) && $unreadDiscussions > 0): ?>
                            <span class="badge"><?php echo $unreadDiscussions; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-calendar"></i> Calendar
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-message"></i> Messages
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-file"></i> Documents
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-gear"></i> Settings
                    </a>
                </div>

                <!-- Logout Container -->
                <div class="logout-container">
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="profile-section">
                    <div class="profile-img">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                        <small><?php echo htmlspecialchars($user['unique_id']); ?></small>
                    </div>
                </div>
                
                <div class="time-attendance">
                    <div class="datetime">
                        <div class="current-time"></div>
                        <div class="current-date"></div>
                    </div>
                    <?php if (!$isPunchedIn): ?>
                        <button id="punchInBtn" class="punch-btn">
                            <i class="fas fa-sign-in-alt"></i> Punch In
                        </button>
                    <?php else: ?>
                        <button id="punchOutBtn" class="punch-btn punch-out">
                            <i class="fas fa-sign-out-alt"></i> Punch Out
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sales Dashboard Section -->
            <div class="sales-dashboard">
                <div class="sales-grid">
                    <div class="sales-card">
                        <div class="sales-icon">
                            <i class="fas fa-building"></i>  <!-- Changed icon -->
                        </div>
                        <div class="sales-info">
                            <h3>Architecture Consultancy</h3>  <!-- Changed title -->
                            <div class="sales-value"><?php echo formatIndianCurrency($architectureSales); ?></div>
                            <div class="sales-trend positive">
                                <i class="fas fa-arrow-up"></i> 12.5%
                            </div>
                        </div>
                    </div>

                    <div class="sales-card">
                        <div class="sales-icon">
                            <i class="fas fa-couch"></i>  <!-- Changed icon -->
                        </div>
                        <div class="sales-info">
                            <h3>Interior Consultancy</h3>  <!-- Changed title -->
                            <div class="sales-value"><?php echo formatIndianCurrency($interiorSales); ?></div>
                            <div class="sales-trend positive">
                                <i class="fas fa-arrow-up"></i> 8.2%
                            </div>
                        </div>
                    </div>

                    <div class="sales-card">
                        <div class="sales-icon">
                            <i class="fas fa-hard-hat"></i>  <!-- Changed icon -->
                        </div>
                        <div class="sales-info">
                            <h3>Construction</h3>  <!-- Changed title -->
                            <div class="sales-value"><?php echo formatIndianCurrency($constructionSales); ?></div>
                            <div class="sales-trend positive">
                                <i class="fas fa-arrow-up"></i> 5.3%
                            </div>
                        </div>
                    </div>

                    <div class="sales-card">
                        <div class="sales-icon">
                            <i class="fas fa-chart-line"></i>  <!-- Changed icon -->
                        </div>
                        <div class="sales-info">
                            <h3>Total Sales</h3>  <!-- Changed title -->
                            <div class="sales-value"><?php echo formatIndianCurrency($totalSales); ?></div>
                            <div class="sales-trend positive">
                                <i class="fas fa-arrow-up"></i> 2.1%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Tasks Completed</h3>
                    <div class="value">12</div>
                </div>
                <div class="stat-card">
                    <h3>Projects</h3>
                    <div class="value">4</div>
                </div>
                <div class="stat-card">
                    <h3>Hours Worked</h3>
                    <div class="value">164</div>
                </div>
                <div class="stat-card">
                    <h3>Achievements</h3>
                    <div class="value">8</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <h4>Completed Project Documentation</h4>
                        <small>2 hours ago</small>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-file"></i>
                    </div>
                    <div>
                        <h4>Submitted Weekly Report</h4>
                        <small>Yesterday</small>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h4>Team Meeting</h4>
                        <small>2 days ago</small>
                    </div>
                </div>
            </div>

            <!-- Tasks Section -->
            <div class="tasks-section">
                <h2><i class="fas fa-tasks"></i> My Tasks</h2>
                <div class="task-filters">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="pending">Pending</button>
                    <button class="filter-btn" data-filter="in_progress">In Progress</button>
                    <button class="filter-btn" data-filter="completed">Completed</button>
                </div>
                
                <div class="tasks-container">
                    <?php foreach($tasks as $task): ?>
                        <div class="task-card" data-status="<?php echo $task['status']; ?>">
                            <div class="task-header">
                                <span class="priority-badge <?php echo $task['priority']; ?>">
                                    <?php echo ucfirst($task['priority']); ?>
                                </span>
                                <span class="status-badge <?php echo $task['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                                </span>
                            </div>
                            
                            <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                            
                            <div class="task-description">
                                <?php echo htmlspecialchars($task['description']); ?>
                            </div>
                            
                            <div class="task-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    From: <?php echo htmlspecialchars($task['assigned_by']); ?>
                                </div>
                            </div>
                            
                            <div class="task-actions">
                                <select class="status-update" onchange="updateTaskStatus(<?php echo $task['id']; ?>, this.value)">
                                    <option value="pending" <?php echo $task['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                                <button class="view-details-btn" onclick="viewTaskDetails(<?php echo $task['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($tasks)): ?>
                        <div class="no-tasks">
                            <i class="fas fa-clipboard-check"></i>
                            <p>No tasks assigned yet!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="documents-section">
                <div class="section-header">
                    <div class="header-left">
                        <h2><i class="fas fa-file-upload"></i> Task Documents</h2>
                        <p class="subtitle">Upload and manage your task-related documents</p>
                    </div>
                </div>

                <form action="upload_task_document.php" method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="form-group">
                        <label>Select Task</label>
                        <select name="task_id" required class="task-select">
                            <option value="">Choose a task...</option>
                            <?php foreach($tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Document Title</label>
                        <input type="text" name="title" required class="form-input">
                    </div>
                    
                    <div class="file-upload">
                        <input type="file" name="document" id="document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <label for="document">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose a file</span>
                            <small>Supported formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</small>
                        </label>
                    </div>
                    
                    <button type="submit" class="upload-btn">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                </form>

                <div class="documents-list">
                    <h3>Recent Documents</h3>
                    <div class="documents-grid">
                        <?php if (empty($taskDocuments)): ?>
                            <div class="no-documents">
                                <i class="fas fa-file-alt"></i>
                                <p>No documents uploaded yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($taskDocuments as $doc): ?>
                                <div class="document-card">
                                    <?php
                                        $extension = pathinfo($doc['file_name'], PATHINFO_EXTENSION);
                                        $iconClass = 'fa-file-alt'; // default icon
                                        
                                        switch(strtolower($extension)) {
                                            case 'pdf':
                                                $iconClass = 'fa-file-pdf';
                                                break;
                                            case 'doc':
                                            case 'docx':
                                                $iconClass = 'fa-file-word';
                                                break;
                                            case 'xls':
                                            case 'xlsx':
                                                $iconClass = 'fa-file-excel';
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                                $iconClass = 'fa-file-image';
                                                break;
                                        }
                                    ?>
                                    <div class="document-icon">
                                        <i class="fas <?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="document-info">
                                        <h4><?php echo htmlspecialchars($doc['title']); ?></h4>
                                        <p>Task: <?php echo htmlspecialchars($doc['task_title']); ?></p>
                                        <div class="document-meta">
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($doc['uploaded_by']); ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></span>
                                            <span><i class="fas fa-file-alt"></i> <?php echo strtoupper($extension); ?></span>
                                        </div>
                                    </div>
                                    <div class="document-actions">
                                        <a href="download_document.php?id=<?php echo $doc['id']; ?>" class="action-btn download-btn" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if ($doc['uploaded_by'] == $_SESSION['user_id']): ?>
                                            <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" class="action-btn delete-btn" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this at the bottom of your body tag -->
    <div id="toast" class="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>

    <!-- Add this JavaScript before the closing body tag -->
    <script>
        // Task filtering
        const filterButtons = document.querySelectorAll('.filter-btn');
        const taskCards = document.querySelectorAll('.task-card');

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                button.classList.add('active');
                
                const filter = button.dataset.filter;
                
                taskCards.forEach(card => {
                    if (filter === 'all' || card.dataset.status === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Update task status
        function updateTaskStatus(taskId, newStatus) {
            fetch('update_task_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_id: taskId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the page or update the UI
                    location.reload();
                } else {
                    alert('Error updating task status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating task status');
            });
        }

        // View task details
        function viewTaskDetails(taskId) {
            // Implement task details view functionality
            // This could open a modal with more detailed information
            alert('View task details for task ' + taskId);
        }

        function deleteDocument(docId) {
            if (confirm('Are you sure you want to delete this document?')) {
                fetch('delete_document.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        document_id: docId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting document');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting document');
                });
            }
        }

        // Update time and date
        function updateDateTime() {
            const now = new Date();
            const timeElement = document.querySelector('.current-time');
            const dateElement = document.querySelector('.current-date');
            
            timeElement.textContent = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit',
                hour12: true 
            });
            
            dateElement.textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Update time every second
        setInterval(updateDateTime, 1000);
        updateDateTime(); // Initial call

        // Toast notification
        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            toastMessage.textContent = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Punch In functionality
        document.getElementById('punchInBtn')?.addEventListener('click', function() {
            fetch('punch_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'punch_in'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Successfully punched in! Have a great day!');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error recording attendance');
            });
        });

        // Punch Out functionality
        document.getElementById('punchOutBtn')?.addEventListener('click', function() {
            fetch('punch_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'punch_out'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Successfully punched out! See you tomorrow!');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error recording attendance');
            });
        });
    </script>
</body>
</html>
