<?php
// Include necessary files
require_once 'config/db_connect.php';
require_once 'includes/activity_logger.php';
require_once 'includes/file_upload.php';
require_once 'includes/process_event.php';
require_once 'includes/flash_messages.php';

// Ensure upload directory exists
$uploadDir = __DIR__ . '/uploads/work_progress';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in - defining the function inline since functions.php is not available
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get user information including role
$userId = $_SESSION['user_id'];
$userQuery = "SELECT username, role FROM users WHERE id = ?";
$stmt = $pdo->prepare($userQuery);
$stmt->execute([$userId]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// If we couldn't fetch the user info, use session data as fallback
$userName = isset($userInfo['username']) ? $userInfo['username'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Supervisor');
$userRole = isset($userInfo['role']) ? $userInfo['role'] : (isset($_SESSION['role']) ? $_SESSION['role'] : 'Site Supervisor');

// Check if the user has Site Supervisor role
// Instead of querying the database, check the session role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Site Supervisor') {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Since database tables don't exist yet, use placeholder data
// Placeholder data for supervised sites
$supervisedSites = [
    ['id' => 1, 'site_name' => 'Residential Complex - Phase 1', 'location' => 'Mumbai', 'status' => 'In Progress'],
    ['id' => 2, 'site_name' => 'Commercial Tower', 'location' => 'Delhi', 'status' => 'Planning'],
    ['id' => 3, 'site_name' => 'Villa Project', 'location' => 'Bangalore', 'status' => 'In Progress'],
    ['id' => 4, 'site_name' => 'Office Building', 'location' => 'Hyderabad', 'status' => 'Completed']
];

// Placeholder data for recent updates
$recentUpdates = [
    ['id' => 101, 'site_id' => 1, 'site_name' => 'Residential Complex - Phase 1', 'update_date' => '2023-11-15', 'notes' => 'Foundation work completed. Ready for next phase.'],
    ['id' => 102, 'site_id' => 1, 'site_name' => 'Residential Complex - Phase 1', 'update_date' => '2023-11-10', 'notes' => 'Materials delivered on time. Construction progressing as scheduled.'],
    ['id' => 103, 'site_id' => 3, 'site_name' => 'Villa Project', 'update_date' => '2023-11-08', 'notes' => 'Electrical wiring completed for Block A. Plumbing work in progress.'],
    ['id' => 104, 'site_id' => 2, 'site_name' => 'Commercial Tower', 'update_date' => '2023-11-05', 'notes' => 'Initial planning meeting held with architects. Blueprint review scheduled.'],
    ['id' => 105, 'site_id' => 3, 'site_name' => 'Villa Project', 'update_date' => '2023-11-01', 'notes' => 'Ground breaking ceremony completed. Site preparation started.']
];

// Page title
$pageTitle = "Site Supervision Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Site Supervision Dashboard for Construction Management">
    <meta name="theme-color" content="#34495e">
    <title><?= $pageTitle ?> | ArchitectsHive</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/dashboard/dashboard_styles.css">
    <!-- Custom Calendar Plus Button Styles -->
    <link rel="stylesheet" href="assets/css/calendar-plus-button.css">
    <link rel="stylesheet" href="assets/css/calendar-modal-mobile.css">
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        :root {
            --primary-color: #34495e;
            --secondary-color: #e74c3c;
            --accent-color: #e74c3c;
            --light-color: #f5f5f5;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --topbar-height: 60px;
            --white-color: #ffffff;
            --black-color: #2c3e50;
            --red-color: #e74c3c;
            --sidebar-bg: #34495e;
            --sidebar-hover: #2c3e50;
        }
        
        /* Root level fixes for scrollbar issues */
        html {
            overflow-x: hidden;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        /* Hide horizontal scrollbar for Chrome, Safari and Opera */
        html::-webkit-scrollbar {
            display: none;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            overflow-x: hidden;
            width: 100vw; /* Use viewport width instead of percentage */
            min-height: 100vh;
            max-width: 100vw; /* Ensure no overflow */
        }
        
        /* Sidebar Styles */
        .wrapper {
            display: flex;
            width: 100vw;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }
        
        #sidebar {
            min-width: var(--sidebar-collapsed-width);
            max-width: var(--sidebar-collapsed-width);
            background: var(--sidebar-bg);
            color: var(--white-color);
            transition: all 0.3s;
            height: 100vh;
            position: fixed;
            z-index: 999;
            overflow-x: hidden;
            overflow-y: hidden; /* Changed from auto to hidden */
            top: 0;
            left: 0;
        }
        
        /* Add hover functionality to enable scrolling only when needed */
        #sidebar:hover {
            overflow-y: auto;
        }
        
        #sidebar.expanded {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
        }
        
        #sidebar:not(.expanded) .sidebar-header h3, 
        #sidebar:not(.expanded) .sidebar-item span,
        #sidebar:not(.expanded) .sidebar-footer span {
            display: none;
        }
        
        #sidebar .sidebar-header {
            padding: 15px 20px;
            background: rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
        }
        
        #sidebar .sidebar-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: #fff;
            margin-left: 10px;
        }
        
        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        #sidebar ul li {
            list-style-type: none;
        }
        
        #sidebar ul li .sidebar-item {
            padding: 15px 20px;
            display: block;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            position: relative;
        }
        
        #sidebar ul li .sidebar-item:hover {
            background: var(--sidebar-hover);
            border-left: 3px solid var(--red-color);
        }
        
        #sidebar ul li .sidebar-item.active {
            background: var(--sidebar-hover);
            border-left: 3px solid var(--red-color);
        }
        
        #sidebar ul li .sidebar-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 1.2rem;
        }
        
        /* Icon tooltip */
        #sidebar:not(.expanded) .sidebar-item::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        #sidebar:not(.expanded) .sidebar-item:hover::after {
            opacity: 1;
            visibility: visible;
            left: calc(100% + 10px);
        }
        
        #sidebar .sidebar-footer {
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        
        /* Content Styles */
        #content {
            width: 100%;
            transition: all 0.3s;
            margin-left: var(--sidebar-collapsed-width);
            min-height: 100vh;
            padding-right: 15px; /* Prevent content from touching right edge */
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }
        
        #content.expanded {
            margin-left: var(--sidebar-width);
        }
        
        .toggle-btn {
            background: var(--sidebar-bg);
            color: var(--white-color);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 10px;
            left: calc(var(--sidebar-collapsed-width) - 20px);
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .toggle-btn.expanded {
            left: calc(var(--sidebar-width) - 20px);
        }
        
        .toggle-btn:hover {
            background: var(--red-color);
        }
        
        /* Hamburger menu for mobile */
        .hamburger-btn {
            display: none; /* Hidden by default */
            background: var(--sidebar-bg);
            color: var(--white-color);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1001;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }
        
        .hamburger-btn:hover {
            background: var(--red-color);
        }
        
        /* Mobile responsive styles */
        @media (max-width: 768px) {
            /* Hide the default sidebar toggle */
            .toggle-btn {
                display: none;
            }
            
            /* Show hamburger menu button */
            .hamburger-btn {
                display: flex;
            }
            
            /* Hide sidebar by default on mobile */
            #sidebar {
                left: -100px;
                min-width: 0;
                max-width: 0;
                opacity: 0;
                visibility: hidden;
            }
            
            /* When .mobile-open class is added */
            #sidebar.mobile-open {
                left: 0;
                min-width: 270px;
                max-width: 270px;
                opacity: 1;
                visibility: visible;
                z-index: 1050;
                box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            }
            
            /* Make sure text is visible in mobile sidebar */
            #sidebar.mobile-open .sidebar-header h3, 
            #sidebar.mobile-open .sidebar-item span,
            #sidebar.mobile-open .sidebar-footer span {
                display: inline-block !important;
                opacity: 1;
                visibility: visible;
            }
            
            /* Enhance mobile sidebar styles for better readability */
            #sidebar.mobile-open {
                padding-top: 10px;
            }
            
            #sidebar.mobile-open .sidebar-header {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
            }
            
            #sidebar.mobile-open .sidebar-item {
                padding: 12px 15px;
                font-size: 0.95rem;
            }
            
            #sidebar.mobile-open .sidebar-item i {
                margin-right: 15px;
                width: 20px;
                text-align: center;
                font-size: 1.1rem;
            }
            
            /* Add active item highlighting for better UX */
            #sidebar.mobile-open .sidebar-item.active {
                background-color: var(--sidebar-hover);
                border-left: 3px solid var(--red-color);
                font-weight: 500;
            }
            
            /* Remove default margin on content */
            #content {
                margin-left: 0;
                padding-left: 15px;
            }
            
            /* Add overlay for mobile sidebar */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100vw;
                height: 100vh;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                opacity: 0;
                transition: opacity 0.3s ease;
                -webkit-tap-highlight-color: transparent;
            }
            
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
            }
        }
        
        .content-inner {
            padding: 20px;
            width: 100%;
            overflow-x: hidden; /* Prevent horizontal scrolling within content */
            max-width: 100%;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--black-color);
            border-bottom: 2px solid var(--red-color);
            padding-bottom: 0.5rem;
            margin-top: 1rem;
            white-space: normal; /* Allow wrapping */
            word-break: break-word; /* Break long words if needed */
        }
        
        .page-title i {
            color: var(--red-color);
            margin-right: 10px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            width: 100%;
            overflow: hidden; /* Ensure content doesn't spill out */
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            white-space: normal; /* Allow wrapping */
        }
        
        .card-title i {
            color: var(--red-color);
            margin-right: 8px;
        }
        
        .card-body {
            padding: 1.5rem;
            overflow: auto; /* Add scrolling if needed */
        }
        
        .list-group-item {
            border: 1px solid #eee;
            margin-bottom: 8px;
            border-radius: 5px !important;
            transition: background-color 0.3s ease;
            white-space: normal; /* Allow wrapping */
            word-break: break-word; /* Break long words if needed */
        }
        
        .list-group-item:hover {
            background-color: #f8f9fa;
        }
        
        .btn {
            border-radius: 5px;
            padding: 0.375rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap; /* Keep button text on one line */
        }
        
        .btn-primary {
            background-color: var(--red-color);
            border-color: var(--red-color);
        }
        
        .btn-primary:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }
        
        .btn-info {
            background-color: var(--info-color);
            border-color: var(--info-color);
            color: white;
        }
        
        .btn-info:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            color: white;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
            border-color: #e67e22;
        }
        
        .table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .table td, .table th {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            white-space: normal; /* Allow cell content to wrap */
            word-break: break-word; /* Break long words if needed */
        }
        
        .table tbody tr {
            transition: background-color 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            border-radius: 5px;
        }
        
        .badge-primary {
            background-color: var(--accent-color);
        }
        
        .badge-success {
            background-color: var(--success-color);
        }
        
        .badge-warning {
            background-color: var(--warning-color);
        }
        
        .badge-danger {
            background-color: var(--danger-color);
        }
        
        .alert {
            border-radius: 10px;
            padding: 1rem 1.5rem;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--info-color);
        }
        
        .alert-info i {
            color: var(--info-color);
            margin-right: 10px;
        }
        
        /* Status Indicators */
        .status-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-in-progress {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        .status-planning {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }
        
        .status-completed {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        /* Dashboard Stats */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            flex: 1 0 calc(25% - 15px);
            min-width: 200px;
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
        }
        
        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .stat-info p {
            font-size: 0.9rem;
            color: #777;
            margin: 0;
        }
        
        .bg-primary-light {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        .bg-success-light {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .bg-warning-light {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }
        
        .bg-danger-light {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        /* Task Items */
        .task-item {
            border-left: 4px solid var(--red-color);
            transition: transform 0.3s ease;
        }
        
        .task-item:hover {
            transform: translateX(5px);
        }
        
        .task-item h5 {
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .task-item p {
            color: #777;
            margin-bottom: 0;
        }
        
        /* Responsive */
        @media (max-width: 1199px) {
            .stat-card {
                flex: 1 0 calc(50% - 15px);
            }
        }
        
        @media (max-width: 991px) {
            .row {
                flex-direction: column;
            }
            
            .col-md-4, .col-md-8 {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .stats-container {
                gap: 10px;
            }
            
            .stat-card {
                flex: 1 0 calc(50% - 10px);
                min-width: 150px;
                padding: 15px;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            
            .stat-info h3 {
                font-size: 1.2rem;
            }
            
            .stat-info p {
                font-size: 0.8rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .card-title {
                font-size: 1rem;
            }
            
            .content-inner {
                padding: 15px;
            }
            
            .list-group-item {
                padding: 10px;
            }
            
            .list-group-item .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card {
                flex: 1 0 100%;
                margin-bottom: 10px;
            }
            
            .stats-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .table th, .table td {
                padding: 0.5rem;
            }
            
            .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            
            .add-update-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .view-update-btn, .edit-update-btn {
                padding: 0.2rem 0.4rem;
                font-size: 0.75rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .toggle-btn {
                width: 35px;
                height: 35px;
                top: 5px;
            }
        }
        
        @media (max-width: 400px) {
            .page-title {
                font-size: 1.3rem;
            }
            
            .content-inner {
                padding: 10px;
            }
            
            .card-header {
                padding: 0.75rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .badge {
                padding: 0.3rem 0.5rem;
                font-size: 0.75rem;
            }
        }
        
        .form-check-input:checked {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        /* Work Report Modal Styles */
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        /* Dashboard Section Styles */
        .dashboard-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .dashboard-section .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .dashboard-section .section-title i {
            color: var(--accent-color);
            margin-right: 8px;
        }
        
        /* Customize dashboard cards for this theme */
        .dashboard-cards .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
        }
        
        .dashboard-cards .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-cards .border-left-primary {
            border-left: 4px solid var(--info-color) !important;
        }
        
        .dashboard-cards .border-left-success {
            border-left: 4px solid var(--success-color) !important;
        }
        
        .dashboard-cards .border-left-info {
            border-left: 4px solid var(--info-color) !important;
        }
        
        .dashboard-cards .border-left-warning {
            border-left: 4px solid var(--warning-color) !important;
        }
        
        .dashboard-cards .border-left-danger {
            border-left: 4px solid var(--danger-color) !important;
        }
        
        .dashboard-cards .text-primary {
            color: var(--info-color) !important;
        }
        
        .dashboard-cards .text-success {
            color: var(--success-color) !important;
        }
        
        .dashboard-cards .text-info {
            color: var(--info-color) !important;
        }
        
        .dashboard-cards .text-warning {
            color: var(--warning-color) !important;
        }
        
        .dashboard-cards .text-danger {
            color: var(--danger-color) !important;
        }
        
        .dashboard-cards .bg-primary {
            background-color: var(--info-color) !important;
        }
        
        .dashboard-cards .bg-success {
            background-color: var(--success-color) !important;
        }
        
        .dashboard-cards .bg-info {
            background-color: var(--info-color) !important;
        }
        
        .dashboard-cards .bg-warning {
            background-color: var(--warning-color) !important;
        }
        
        .dashboard-cards .bg-danger {
            background-color: var(--danger-color) !important;
        }
        
        /* Fix for any potential horizontal overflow in tables or other content */
        .table-responsive {
            overflow-x: auto; /* Use auto instead of scroll to only show when needed */
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
            scrollbar-width: thin; /* Thinner scrollbar for Firefox */
        }
        
        /* Custom scrollbar styling for WebKit browsers */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }
        
        /* Calendar Styles */
        .calendar-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin-bottom: 0;
            table-layout: fixed;
        }
        
        .calendar-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            text-align: center;
            padding: 10px;
            border: 1px solid #ebedf2;
        }
        
        .calendar-day {
            height: 120px;
            vertical-align: top !important;
            padding: 8px !important;
            border: 1px solid #ebedf2;
            position: relative;
            transition: all 0.2s ease;
        }
        
        /* Add event plus button on hover */
        .calendar-day::after {
            content: '+';
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.2s ease;
            cursor: pointer;
            z-index: 5;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .calendar-day:hover::after {
            opacity: 1;
        }
        
        /* Hide add button for previous and next month days */
        .prev-month::after, 
        .next-month::after {
            display: none;
        }
        
        .calendar-day:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .prev-month, .next-month {
            color: #bbb;
            background-color: #f9f9f9;
        }
        
        .today {
            background-color: rgba(52, 152, 219, 0.1);
            font-weight: bold;
            box-shadow: inset 0 0 0 2px var(--primary-color);
        }
        
        .calendar-event {
            padding: 5px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 0.8rem;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            overflow: hidden;
            max-width: 100%;
        }
        
        .calendar-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .calendar-event small {
            display: block;
            font-size: 0.7rem;
            opacity: 0.8;
        }
        
        .calendar-nav {
            display: flex;
            align-items: center;
        }
        
        .current-month {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .calendar-view-options .btn-group {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .calendar-view-options .btn {
            border-radius: 0;
            border: 1px solid #ebedf2;
            padding: 0.375rem 0.75rem;
            font-size: 0.9rem;
        }
        
        .calendar-legend {
            padding-top: 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .legend-item {
            display: flex;
            align-items: center;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 5px;
        }
        
        /* Responsive Calendar Container */
        .calendar-container {
            position: relative;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
        }
        
        /* Improved Responsive Handling */
        @media (max-width: 1200px) {
            .calendar-day {
                height: 110px;
            }
        }
        
        @media (max-width: 992px) {
            .calendar-day {
                height: 100px;
                padding: 6px !important;
            }
            
            .calendar-event {
                padding: 4px;
                margin-top: 4px;
            }
            
            .calendar-table th {
                padding: 8px;
            }
        }
        
        @media (max-width: 768px) {
            .calendar-day {
                height: 80px;
                font-size: 0.8rem;
                padding: 4px !important;
            }
            
            .calendar-event {
                font-size: 0.65rem;
                padding: 2px 4px;
                margin-top: 3px;
            }
            
            .calendar-event small {
                font-size: 0.6rem;
            }
            
            .calendar-table th {
                padding: 5px;
                font-size: 0.75rem;
            }
            
            .d-flex.justify-content-between.align-items-center {
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .calendar-view-options {
                margin-top: 10px;
                width: 100%;
            }
            
            .calendar-view-options .btn-group {
                width: 100%;
                display: flex;
            }
            
            .calendar-view-options .btn {
                flex: 1;
                font-size: 0.8rem;
                padding: 0.3rem 0.5rem;
            }
            
            .current-month {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            /* Switch to list view on very small screens */
            .calendar-container {
                overflow-x: auto;
            }
            
            .calendar-table {
                min-width: 500px; /* Ensure minimum width for scrolling */
            }
            
            .calendar-day {
                height: 60px;
                font-size: 0.7rem;
                padding: 2px !important;
            }
            
            /* Limit events to prevent overflow */
            .calendar-day .calendar-event:nth-child(n+3) {
                display: none;
            }
            
            .calendar-day .calendar-event:nth-child(2):after {
                content: "...";
                display: block;
                font-size: 0.6rem;
            }
            
            .legend-item {
                font-size: 0.75rem;
            }
            
            .calendar-nav button {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
        }
        
        /* Event count badge for small screens */
        .event-count {
            position: absolute;
            bottom: 2px;
            right: 2px;
            background-color: var(--primary-color);
            color: white;
            font-size: 0.65rem;
            padding: 2px 4px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }
        
        /* Compact calendar for very small screens */
        @media (max-width: 375px) {
            .calendar-table {
                min-width: 300px;
            }
            
            .calendar-day {
                height: 40px;
                font-size: 0.6rem;
                padding: 1px !important;
            }
            
            /* Show only one event + indicator */
            .calendar-day .calendar-event:nth-child(n+2) {
                display: none;
            }
            
            .calendar-day .calendar-event:first-child {
                padding: 1px 2px;
                margin-top: 2px;
                font-size: 0.6rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .calendar-day .calendar-event:first-child small {
                display: none;
            }
            
            .calendar-table th {
                padding: 3px 2px;
                font-size: 0.65rem;
            }
            
            .section-title {
                font-size: 1rem;
            }
        }

        /* Add these styles to your existing CSS */
        .vendor-type-custom .input-group {
            display: flex;
        }

        .vendor-type-custom .back-to-select {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .vendor-type-custom .form-control {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .back-to-select {
            padding: 0.375rem 0.75rem;
        }

        .back-to-select:hover {
            background-color: #e9ecef;
        }

        /* Add these styles to your existing CSS */
        .labor-form {
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .labor-form:hover {
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .input-group-text {
            background-color: #e9ecef;
            border-right: none;
        }

        .input-group .form-control {
            border-left: none;
        }

        .input-group .form-control:focus {
            border-left: none;
            box-shadow: none;
        }

        .labor-title {
            color: #6c757d;
        }

        /* Add styles for attendance select */
        .attendance-select {
            font-weight: 500;
        }

        .attendance-select option[value="P"] {
            color: #198754;
            font-weight: 600;
        }

        .attendance-select option[value="A"] {
            color: #dc3545;
            font-weight: 600;
        }

        /* ... existing styles ... */
        
        .vendors-container {
            position: relative;
            padding-bottom: 50px; /* Space for the floating button */
        }
        
        .add-vendor-btn {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            transition: all 0.3s ease;
            z-index: 1;
            background-color: #fff;
            border: 2px dashed #007bff;
            color: #007bff;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .add-vendor-btn:hover {
            background-color: #007bff;
            color: #fff;
        }
        
        .add-vendor-btn i {
            font-size: 14px;
        }
        
        .labor-container {
            position: relative;
            padding-bottom: 50px;
        }
        
        .btn-add-labor {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            transition: all 0.3s ease;
            z-index: 1;
            background-color: #fff;
            border: 2px dashed #28a745;
            color: #28a745;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .btn-add-labor:hover {
            background-color: #28a745;
            color: #fff;
        }
        
        .btn-add-labor i {
            font-size: 14px;
        }
        
        /* Mobile responsive styles for Add Labor button */
        @media (max-width: 768px) {
            .btn-add-labor {
                width: 90%;
                max-width: 180px;
                padding: 6px 12px;
                font-size: 14px;
                border-radius: 30px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                border: 1px solid #28a745;
                background-color: #f8fff9;
                font-weight: 500;
                justify-content: center;
                margin-bottom: 15px;
            }
            
            .btn-add-labor:active {
                transform: translateX(-50%) scale(0.97);
                background-color: #e8f5e9;
            }
        }
        
        @media (max-width: 576px) {
            .btn-add-labor {
                max-width: 150px;
                padding: 5px 10px;
                font-size: 13px;
                gap: 5px;
            }
            
            .btn-add-labor span {
                font-size: 12px;
                white-space: nowrap;
            }
        }
        
        .labor-form {
            position: relative;
            z-index: 2;
        }

        /* Company Labour Button Styles */
        .company-labours-container {
            position: relative;
            padding-bottom: 60px;
        }

        .btn-add-company-labour {
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            transition: all 0.3s ease;
            z-index: 1;
            background-color: #fff;
            border: 2px dashed #28a745;
            color: #28a745;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.15);
            min-width: 200px;
            justify-content: center;
            text-align: center;
        }

        .btn-add-company-labour:hover {
            background-color: #28a745;
            color: #fff;
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.25);
            transform: translateX(-50%) translateY(-3px);
        }

        .btn-add-company-labour:active {
            transform: translateX(-50%) translateY(0);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15);
        }

        .btn-add-company-labour i {
            font-size: 16px;
        }

        .company-labour-form {
            position: relative;
            z-index: 2;
            background-color: #fff;
        }

        /* Travel Expenses Styles */
        .travel-expenses-container {
            position: relative;
            padding-bottom: 60px;
        }

        .btn-add-travel {
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            transition: all 0.3s ease;
            z-index: 1;
            background-color: #fff;
            border: 2px dashed #17a2b8;
            color: #17a2b8;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(23, 162, 184, 0.15);
            min-width: 200px;
            justify-content: center;
            text-align: center;
        }

        .btn-add-travel:hover {
            background-color: #17a2b8;
            color: #fff;
            box-shadow: 0 6px 15px rgba(23, 162, 184, 0.25);
            transform: translateX(-50%) translateY(-3px);
        }

        .btn-add-travel:active {
            transform: translateX(-50%) translateY(0);
            box-shadow: 0 2px 8px rgba(23, 162, 184, 0.15);
        }

        .btn-add-travel i {
            font-size: 16px;
        }

        .travel-expense-form {
            position: relative;
            z-index: 2;
            background-color: #fff;
        }

        .travel-expense-form .form-control:read-only {
            background-color: #f8f9fa;
        }

        /* Beverages Styles */
        .beverages-container {
            position: relative;
            padding-bottom: 60px;
        }

        .btn-add-beverage {
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            transition: all 0.3s ease;
            z-index: 1;
            background-color: #fff;
            border: 2px dashed #6f42c1;
            color: #6f42c1;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(111, 66, 193, 0.15);
            min-width: 200px;
            justify-content: center;
            text-align: center;
        }

        .btn-add-beverage:hover {
            background-color: #6f42c1;
            color: #fff;
            box-shadow: 0 6px 15px rgba(111, 66, 193, 0.25);
            transform: translateX(-50%) translateY(-3px);
        }

        .btn-add-beverage:active {
            transform: translateX(-50%) translateY(0);
            box-shadow: 0 2px 8px rgba(111, 66, 193, 0.15);
        }

        .btn-add-beverage i {
            font-size: 16px;
        }

        .beverage-form {
            position: relative;
            z-index: 2;
            background-color: #fff;
        }

        .beverage-form .form-control:read-only {
            background-color: #f8f9fa;
        }
        
        /* Vendor Material Section Styles */
        .vendor-material-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .vendor-material-section h6 {
            color: #495057;
            font-weight: 500;
        }
        
        .vendor-material-section .form-control[type="file"] {
            padding: 8px;
            font-size: 14px;
        }
        
        .vendor-material-section textarea {
            resize: vertical;
            min-height: 60px;
        }
        
        @media (max-width: 768px) {
            .vendor-material-section {
                padding: 12px;
            }
            
            .vendor-material-section .row {
                gap: 10px;
            }
            
            .vendor-material-section .col-md-6 {
                padding: 0 8px;
            }
            
            .vendor-material-section label {
                font-size: 14px;
                margin-bottom: 4px;
            }
        }

        /* Work Progress Styles */
        .work-progress-container {
            position: relative;
            padding-bottom: 60px;
        }

        .btn-add-work {
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            transition: all 0.3s ease;
            z-index: 1;
            background-color: #fff;
            border: 2px dashed #20c997;
            color: #20c997;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(32, 201, 151, 0.15);
            min-width: 200px;
            justify-content: center;
            text-align: center;
        }

        .btn-add-work:hover {
            background-color: #20c997;
            color: #fff;
            box-shadow: 0 6px 15px rgba(32, 201, 151, 0.25);
            transform: translateX(-50%) translateY(-3px);
        }

        .btn-add-work:active {
            transform: translateX(-50%) translateY(0);
            box-shadow: 0 2px 8px rgba(32, 201, 151, 0.15);
        }

        .btn-add-work i {
            font-size: 16px;
        }

        .work-progress-form {
            position: relative;
            z-index: 2;
            background-color: #fff;
        }

        /* File upload styling */
        .custom-file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-label {
            display: block;
            width: 100%;
            margin-bottom: 0;
            cursor: pointer;
        }

        .work-media-file {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 0.1px;
            height: 0.1px;
            overflow: hidden;
        }

        .file-custom {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-weight: 400;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            text-align: center;
        }

        .file-custom:hover {
            background-color: #f8f9fa;
        }

        .file-custom i {
            margin-right: 5px;
        }

        /* Media preview */
        .media-preview {
            max-width: 100%;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 5px;
            text-align: center;
        }

        .img-preview, .video-preview {
            max-height: 200px;
            max-width: 100%;
            margin: 0 auto;
            display: block;
        }

        /* Media container styles */
        .media-container {
            position: relative;
        }

        .media-upload-header h6 {
            font-weight: 600;
            color: #555;
        }

        /* Media button styles */
        .add-media-btn {
            margin-top: 10px;
        }

        /* Inventory Section Styles */
        .inventory-container {
            position: relative;
            padding-bottom: 60px;
        }

        .btn-add-inventory {
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            transition: all 0.3s ease;
            z-index: 1;
            background-color: #fff;
            border: 2px dashed #fd7e14;
            color: #fd7e14;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(253, 126, 20, 0.15);
            min-width: 200px;
            justify-content: center;
            text-align: center;
        }

        .btn-add-inventory:hover {
            background-color: #fd7e14;
            color: #fff;
            box-shadow: 0 6px 15px rgba(253, 126, 20, 0.25);
            transform: translateX(-50%) translateY(-3px);
        }
        
        .btn-add-inventory:active {
            transform: translateX(-50%) translateY(0);
            box-shadow: 0 2px 8px rgba(253, 126, 20, 0.15);
        }

        .btn-add-inventory i {
            font-size: 16px;
        }

        .inventory-form {
            position: relative;
            z-index: 2;
            background-color: #fff;
            transition: all 0.3s ease;
        }

        .inventory-form:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
        }

        .inventory-title {
            font-weight: 600;
        }

        .bill-preview {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }

        .bill-preview-img {
            max-height: 200px;
            max-width: 100%;
            object-fit: contain;
        }

        .add-inventory-media-btn {
            margin-top: 10px;
        }

        /* ... existing styles ... */

        /* Bill Picture Upload */
        .bill-upload-container,
        .media-upload-container {
            border: 1px solid #e9ecef;
            background-color: #f8f9fa;
            transition: all 0.2s ease;
        }

        .bill-upload-container:hover,
        .media-upload-container:hover {
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
        }

        .file-custom {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .file-custom:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
        }

        .media-item {
            transition: all 0.2s ease;
            border: 1px solid #e9ecef;
        }

        .media-item:hover {
            border-color: #ced4da;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
        }

        .bill-preview,
        .media-preview {
            background-color: #fff;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .add-inventory-media-btn {
            padding: 0.375rem 1rem;
            font-weight: 500;
        }

        /* Wages Summary Section Styles */
        .wages-summary-section {
            animation: fadeIn 0.5s ease-in-out;
        }

        .section-heading {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f8f9fa;
            margin-bottom: 1rem;
        }

        .section-heading i {
            color: #007bff;
            margin-right: 0.5rem;
        }

        .wage-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            height: 100%;
        }

        .wage-card-header {
            padding: 10px 15px;
            color: #fff;
        }

        .wage-card-header h6 {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .wage-card-body {
            padding: 0;
        }

        .wage-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 15px;
            border-bottom: 1px solid #f1f1f1;
        }

        .wage-item:last-child {
            border-bottom: none;
        }

        .wage-item.total {
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-weight: 600;
        }

        .wage-label {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }

        .wage-label i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        .wage-amount {
            font-size: 0.85rem;
            font-weight: 500;
        }

        .grand-total-label {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .grand-total-amount {
            font-size: 1.1rem;
            font-weight: 700;
        }

        /* Colors for amounts */
        .wage-amount.text-danger {
            color: #dc3545 !important;
        }

        .wage-amount.text-warning {
            color: #ffc107 !important;
        }

        .wage-amount.text-success {
            color: #28a745 !important;
        }

        /* Fix for background colors */
        .bg-primary {
            background-color: #007bff !important;
        }

        .bg-warning {
            background-color: #ffc107 !important;
        }

        .grand-total-container {
            display: inline-flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }

        .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f1f1f1;
            border: none;
            color: #666;
        }

        .btn-icon:hover {
            background-color: #e9ecef;
            color: #007bff;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Mobile-specific button styles */
        @media (max-width: 768px) {
            /* Regular add buttons outside the modal */
            .btn-add-company-labour,
            .btn-add-travel,
            .btn-add-beverage,
            .btn-add-work,
            .btn-add-inventory {
                padding: 10px 16px;
                width: 85%;
                max-width: 240px;
                min-height: 45px;
                font-size: 0.9rem;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
            }
            
            .btn-add-company-labour i,
            .btn-add-travel i,
            .btn-add-beverage i,
            .btn-add-work i,
            .btn-add-inventory i {
                font-size: 16px;
            }
            
            /* Modal buttons should be even more compact */
            #addEventModal .btn-add-company-labour,
            #addEventModal .btn-add-travel,
            #addEventModal .btn-add-beverage,
            #addEventModal .btn-add-work,
            #addEventModal .btn-add-inventory,
            /* Target vendor button specifically */
            #addEventModal #addVendorBtn,
            #addEventModal #addCompanyLabourBtn,
            #addEventModal #addTravelBtn,
            #addEventModal #addBeverageBtn,
            #addEventModal #addWorkBtn,
            #addEventModal #addInventoryBtn {
                padding: 8px 12px;
                font-size: 0.85rem;
                min-height: 38px;
                width: 80%;
                max-width: 220px;
                gap: 6px;
            }
            
            #addEventModal .btn-add-company-labour i,
            #addEventModal .btn-add-travel i,
            #addEventModal .btn-add-beverage i,
            #addEventModal .btn-add-work i,
            #addEventModal .btn-add-inventory i,
            #addEventModal #addVendorBtn i {
                font-size: 14px;
            }
            
            /* Increase container padding to give more space */
            .company-labours-container,
            .travel-expenses-container,
            .beverages-container,
            .work-progress-container,
            .inventory-container {
                padding-bottom: 60px;
            }
            
            /* Inside modal, reduce padding further */
            #addEventModal .company-labours-container,
            #addEventModal .travel-expenses-container,
            #addEventModal .beverages-container,
            #addEventModal .work-progress-container,
            #addEventModal .inventory-container {
                padding-bottom: 50px;
            }
        }
        
        /* Further optimize for very small screens */
        @media (max-width: 480px) {
            .btn-add-company-labour,
            .btn-add-travel,
            .btn-add-beverage,
            .btn-add-work,
            .btn-add-inventory {
                width: 90%;
                font-size: 0.85rem;
                padding: 8px 14px;
                min-height: 40px;
                border-width: 1px;
            }
            
            /* Inside modal, make buttons even smaller for mobile */
            #addEventModal .btn-add-company-labour,
            #addEventModal .btn-add-travel,
            #addEventModal .btn-add-beverage,
            #addEventModal .btn-add-work,
            #addEventModal .btn-add-inventory,
            #addEventModal #addVendorBtn,
            #addEventModal #addCompanyLabourBtn,
            #addEventModal #addTravelBtn,
            #addEventModal #addBeverageBtn,
            #addEventModal #addWorkBtn,
            #addEventModal #addInventoryBtn {
                padding: 6px 10px;
                font-size: 0.8rem;
                min-height: 34px;
                max-width: 200px;
                width: 90%;
                border-width: 1px;
            }
            
            #addEventModal .btn-add-company-labour i,
            #addEventModal .btn-add-travel i,
            #addEventModal .btn-add-beverage i,
            #addEventModal .btn-add-work i,
            #addEventModal .btn-add-inventory i,
            #addEventModal #addVendorBtn i {
                font-size: 12px;
            }
            
            /* Add a subtle animation to make buttons more noticeable */
            @keyframes pulse {
                0% { transform: translateX(-50%) scale(1); }
                50% { transform: translateX(-50%) scale(1.03); }
                100% { transform: translateX(-50%) scale(1); }
            }
            
            /* Reduce animation scale for smaller effect */
            .btn-add-company-labour,
            .btn-add-travel,
            .btn-add-beverage,
            .btn-add-work,
            .btn-add-inventory {
                animation: pulse 2s infinite ease-in-out;
            }
            
            /* Stop animation on hover */
            .btn-add-company-labour:hover,
            .btn-add-travel:hover,
            .btn-add-beverage:hover,
            .btn-add-work:hover,
            .btn-add-inventory:hover {
                animation: none;
                transform: translateX(-50%) translateY(-2px);
            }
        }
        
        /* Modal responsive styles for small screens */
        @media (max-width: 768px) {
            /* Make modal header more compact */
            #addEventModal .modal-header {
                padding: 0.75rem 1rem;
            }
            
            #addEventModal .modal-title {
                font-size: 1.1rem;
            }
            
            /* Make modal body more compact */
            #addEventModal .modal-body {
                padding: 1rem;
            }
            
            /* Make form labels smaller */
            #addEventModal label.form-label {
                font-size: 0.85rem;
                margin-bottom: 0.3rem;
            }
            
            /* Make form controls smaller */
            #addEventModal .form-control {
                font-size: 0.9rem;
                padding: 0.4rem 0.75rem;
                height: auto;
            }
            
            /* Make section headings smaller */
            #addEventModal h6 {
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
            }
            
            /* Make spacing between sections smaller */
            #addEventModal .mt-4 {
                margin-top: 1rem !important;
            }
            
            #addEventModal .mb-3 {
                margin-bottom: 0.75rem !important;
            }
            
            /* Adjust padding for form sections */
            #addEventModal .p-3 {
                padding: 0.75rem !important;
            }
            
            /* Make form row spacing smaller */
            #addEventModal .row > [class*="col-"] {
                padding-right: 8px;
                padding-left: 8px;
            }
            
            #addEventModal .form-select {
                font-size: 0.9rem;
                padding: 0.4rem 0.75rem;
                height: auto;
            }
            
            /* Adjust icon sizes */
            #addEventModal i.fas {
                font-size: 0.9rem;
            }
            
            /* Adjust modal footer buttons */
            #addEventModal .modal-footer {
                padding: 0.75rem 1rem;
            }
            
            #addEventModal .modal-footer .btn {
                padding: 0.4rem 0.75rem;
                font-size: 0.9rem;
            }
        }
        
        /* Even smaller screens */
        @media (max-width: 480px) {
            #addEventModal .modal-title {
                font-size: 1rem;
            }
            
            #addEventModal label.form-label {
                font-size: 0.8rem;
            }
            
            #addEventModal .form-control,
            #addEventModal .form-select {
                font-size: 0.85rem;
                padding: 0.35rem 0.6rem;
            }
            
            #addEventModal h6 {
                font-size: 0.85rem;
            }
            
            /* Stack all columns for better mobile view */
            #addEventModal .row > [class*="col-"] {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 0.5rem;
            }
            
            /* Adjust modal footer buttons further */
            #addEventModal .modal-footer .btn {
                padding: 0.35rem 0.6rem;
                font-size: 0.85rem;
            }
        }
    </style>
    <script src="includes/work_progress_upload.js"></script>
    <script src="includes/inventory_upload.js"></script>
    <script src="includes/js/calendar_events.js"></script>
</head>
<body>

<div class="wrapper">
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-hard-hat"></i>
            <h3>Site Supervision</h3>
        </div>

        <ul class="components">
            <li>
                <a href="#" class="sidebar-item active" data-tooltip="Dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-item" data-tooltip="Sites">
                    <i class="fas fa-building"></i>
                    <span>Sites</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-item" data-tooltip="Updates">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Updates</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-item" data-tooltip="Tasks">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-item" data-tooltip="Workers">
                    <i class="fas fa-users"></i>
                    <span>Workers</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-item" data-tooltip="Materials">
                    <i class="fas fa-truck"></i>
                    <span>Materials</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-item" data-tooltip="Schedule">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-item" data-tooltip="Reports">
                    <i class="fas fa-chart-line"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="logout.php" class="sidebar-item" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Hamburger Menu Button (for mobile) -->
    <button type="button" id="hamburgerBtn" class="hamburger-btn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay"></div>

    <!-- Page Content -->
    <div id="content">
        <!-- Toggle Button -->
        <button type="button" id="sidebarCollapse" class="toggle-btn">
            <i class="fas fa-chevron-left" id="sidebar-toggle-icon"></i>
        </button>

        <div class="content-inner">
            
            <!-- Greetings Section -->
            <div class="card mb-4">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <div>
                            <h3 class="fs-5 mb-1">
                                Welcome, <span class="fw-semibold"><?= htmlspecialchars($userName) ?></span> 
                                <span class="small text-muted ms-1">(<?= htmlspecialchars($userRole) ?>)</span>
                            </h3>
                            <div class="d-flex align-items-center mt-2">
                                <i class="fas fa-calendar-day text-muted me-1"></i>
                                <p class="small text-muted mb-0"><?= date('l, F j, Y') ?></p>
                            </div>
                            <div class="d-flex align-items-center mt-1">
                                <i class="fas fa-clock text-muted me-1"></i>
                                <p class="small text-muted mb-0" id="current-time"><?= date('h:i:s A') ?> (IST)</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-md-0">
                            <div class="text-end">
                                <?php
                                // Check if user has already punched in today
                                $today = date('Y-m-d');
                                $checkPunch = $pdo->prepare("SELECT id, punch_in, punch_out FROM attendance 
                                                            WHERE user_id = ? AND date = ? 
                                                            ORDER BY id DESC LIMIT 1");
                                $checkPunch->execute([$userId, $today]);
                                $punchRecord = $checkPunch->fetch(PDO::FETCH_ASSOC);
                                
                                $isPunchedIn = false;
                                $attendanceId = null;
                                
                                if ($punchRecord && $punchRecord['punch_in'] && !$punchRecord['punch_out']) {
                                    $isPunchedIn = true;
                                    $attendanceId = $punchRecord['id'];
                                    $punchInTime = new DateTime($punchRecord['punch_in']);
                                    $punchDuration = $punchInTime->diff(new DateTime());
                                    $hourMinFormat = $punchDuration->format('%h hr %i min');
                                }
                                ?>
                                
                                <div id="punch-status" class="small text-muted mb-2 text-end">
                                    <?php if ($isPunchedIn): ?>
                                        <span class="text-success">
                                            <i class="fas fa-clock"></i> Working for <?= $hourMinFormat ?>
                                        </span>
                                    <?php else: ?>
                                        <span>Not punched in today</span>
                                    <?php endif; ?>
                                </div>
                                
                                <button id="punch-button" class="btn <?= $isPunchedIn ? 'btn-danger' : 'btn-success' ?>" 
                                        data-user-id="<?= $userId ?>" 
                                        data-status="<?= $isPunchedIn ? 'out' : 'in' ?>"
                                        <?= $isPunchedIn ? 'data-attendance-id="'.$attendanceId.'"' : '' ?>>
                                    <i class="fas <?= $isPunchedIn ? 'fa-sign-out-alt' : 'fa-sign-in-alt' ?>"></i>
                                    Punch <?= $isPunchedIn ? 'Out' : 'In' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            
            
            <!-- Dashboard Cards Section -->
            <div class="dashboard-section mb-4">
                <h2 class="section-title mb-3">
                    <i class="fas fa-tachometer-alt"></i> Task Dashboard
                </h2>
                <div class="row dashboard-cards">
                    <!-- Today's Tasks Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Today's Tasks</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">3</div>
                                        
                                        <div class="mt-3 small">
                                            <div class="mb-1">
                                                <i class="fas fa-dot-circle mr-1 text-primary"></i>
                                                Residential Complex - Phase 1
                    </div>
                                            <div class="mb-1">
                                                <i class="fas fa-dot-circle mr-1 text-primary"></i>
                                                Villa Project
                    </div>
                                            <div class="mb-1">
                                                <i class="fas fa-dot-circle mr-1 text-primary"></i>
                                                Commercial Tower
                </div>
                    </div>
                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
                </div>
                    </div>
                            </div>
                            <a href="#" class="card-footer text-primary clearfix small z-1">
                                <span class="float-left">View Details</span>
                                <span class="float-right">
                                    <i class="fas fa-angle-right"></i>
                                </span>
                            </a>
                    </div>
                </div>
                
                    <!-- Task Summary Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Task Summary</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">12 Tasks</div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-6">
                                                <div class="small font-weight-bold">
                                                    Completed: 8
                    </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="small font-weight-bold">
                                                    Pending: 4
                    </div>
                </div>
            </div>
            
                                        <div class="progress progress-sm mt-2">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                style="width: 67%" 
                                                aria-valuenow="67" aria-valuemin="0" aria-valuemax="100">
                        </div>
                                        </div>
                                        <div class="small mt-1 text-center">
                                            67% Complete
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <a href="#" class="card-footer text-success clearfix small z-1">
                                <span class="float-left">View Details</span>
                                <span class="float-right">
                                    <i class="fas fa-angle-right"></i>
                                </span>
                            </a>
                        </div>
                    </div>

                    <!-- Upcoming Tasks Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Upcoming Tasks</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">5</div>
                                        
                                        <div class="mt-3 small">
                                            <div class="mb-1">
                                                <i class="far fa-calendar mr-1 text-info"></i>
                                                Nov 20: Commercial Tower
                                            </div>
                                            <div class="mb-1">
                                                <i class="far fa-calendar mr-1 text-info"></i>
                                                Nov 22: Villa Project
                                            </div>
                                            <div class="mb-1">
                                                <i class="far fa-calendar mr-1 text-info"></i>
                                                Nov 25: Office Building
                                            </div>
                                            
                                            <div class="text-muted">+ 2 more</div>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <a href="#" class="card-footer text-info clearfix small z-1">
                                <span class="float-left">View Details</span>
                                <span class="float-right">
                                    <i class="fas fa-angle-right"></i>
                                                </span>
                            </a>
                                            </div>
                    </div>

                    <!-- Task Efficiency Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Task Efficiency</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">78%</div>
                                        
                                        <div class="mt-3">
                                            <div class="small">
                                                <span class="text-success mr-2"><i class="fas fa-arrow-up"></i> 5%</span>
                                                <span class="small">since last month</span>
                                            </div>
                                            
                                            <div class="progress progress-sm mt-2">
                                                <div class="progress-bar bg-warning" role="progressbar" 
                                                    style="width: 78%" 
                                                    aria-valuenow="78" aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tachometer-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                            <a href="#" class="card-footer text-warning clearfix small z-1">
                                <span class="float-left">View Details</span>
                                <span class="float-right">
                                    <i class="fas fa-angle-right"></i>
                                </span>
                            </a>
                                </div>
                        </div>
                    </div>
                </div>
                
            <!-- Calendar Section -->
            <div class="dashboard-section mb-4">
                <h2 class="section-title mb-3">
                    <i class="fas fa-calendar"></i> Schedule Calendar
                </h2>
                
                <div class="card shadow">
                        <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="calendar-nav">
                                <button class="btn btn-outline-secondary" id="prevMonth">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <h3 class="current-month d-inline-block mx-3">November 2023</h3>
                                <button class="btn btn-outline-secondary" id="nextMonth">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <div class="calendar-view-options">
                                <div class="btn-group">
                                    <button class="btn btn-outline-primary active" data-view="month">Month</button>
                                    <button class="btn btn-outline-primary" data-view="week">Week</button>
                                    <button class="btn btn-outline-primary" data-view="day">Day</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="calendar-container">
                            <table class="calendar-table table table-bordered">
                                        <thead>
                                            <tr>
                                        <th>Sunday</th>
                                        <th>Monday</th>
                                        <th>Tuesday</th>
                                        <th>Wednesday</th>
                                        <th>Thursday</th>
                                        <th>Friday</th>
                                        <th>Saturday</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                    <!-- Calendar cells will be generated by JavaScript -->
                                </tbody>
                            </table>
                    </div>
                    
                        <div class="calendar-legend mt-3">
                            <div class="d-flex flex-wrap">
                                <div class="legend-item mr-3 mb-2">
                                    <span class="legend-color bg-primary"></span>
                                    <span>Client Visit</span>
                        </div>
                                <div class="legend-item mr-3 mb-2">
                                    <span class="legend-color bg-success"></span>
                                    <span>Inspection</span>
                                        </div>
                                <div class="legend-item mr-3 mb-2">
                                    <span class="legend-color bg-info"></span>
                                    <span>Meeting</span>
                                    </div>
                                <div class="legend-item mr-3 mb-2">
                                    <span class="legend-color bg-warning"></span>
                                    <span>Delivery</span>
                                        </div>
                                <div class="legend-item mr-3 mb-2">
                                    <span class="legend-color bg-danger"></span>
                                    <span>Deadline</span>
                                    </div>
                                        </div>
                                    </div>
                                        </div>
                                    </div>
                            </div>
                     
                     
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Update Modal -->
<div class="modal fade" id="addUpdateModal" tabindex="-1" aria-labelledby="addUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUpdateModalLabel">Add Site Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateForm" class="needs-validation" novalidate>
                    <input type="hidden" id="siteId" name="site_id">
                    <div class="mb-3">
                        <label for="siteName" class="form-label">Site Name</label>
                        <input type="text" class="form-control" id="siteName" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="updateDate" class="form-label">Update Date</label>
                        <input type="date" class="form-control" id="updateDate" name="update_date" required>
                        <div class="invalid-feedback">Please select a date.</div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" required></textarea>
                        <div class="invalid-feedback">Please enter update notes.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveUpdateBtn">Save Update</button>
            </div>
        </div>
    </div>
</div>

<!-- View Update Modal -->
<div class="modal fade" id="viewUpdateModal" tabindex="-1" aria-labelledby="viewUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewUpdateModalLabel">View Site Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 class="fw-bold">Site:</h6>
                    <p id="viewSiteName" class="mb-2"></p>
                </div>
                <div class="mb-3">
                    <h6 class="fw-bold">Date:</h6>
                    <p id="viewUpdateDate" class="mb-2"></p>
                </div>
                <div class="mb-3">
                    <h6 class="fw-bold">Notes:</h6>
                    <p id="viewNotes" class="mb-2"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="editFromViewBtn">Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Work Report Modal -->
<div class="modal fade" id="workReportModal" tabindex="-1" aria-labelledby="workReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="workReportModalLabel">Submit Work Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="workReportError" class="alert alert-danger d-none"></div>
                
                <div class="mb-4">
                    <h5 class="card-title">Attendance Summary</h5>
                    <div class="card bg-light p-3">
                        <div class="summary-item">
                            <span>User:</span>
                            <strong id="wr-username"><?= $userName ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Date:</span>
                            <strong id="wr-date"><?= date('l, F j, Y') ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Punch In:</span>
                            <strong id="wr-punch-in" class="text-success"></strong>
                        </div>
                        <div class="summary-item">
                            <span>Punch Out:</span>
                            <strong id="wr-punch-out" class="text-danger"></strong>
                        </div>
                        <div class="summary-item">
                            <span>Working Hours:</span>
                            <strong id="wr-hours"></strong>
                        </div>
                    </div>
                </div>
                
                <form id="workReportForm">
                    <input type="hidden" id="wr-attendance-id" name="attendance_id">
                    <input type="hidden" id="is-punching-out" name="is_punching_out" value="0">
                    
                    <div class="mb-3">
                        <label for="work_report" class="form-label">Work Report <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="work_report" name="work_report" rows="6" required placeholder="Please provide details about your work activities today..."></textarea>
                        <div class="form-text">Describe tasks completed, challenges faced, and progress made.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="overtime" name="overtime" value="1">
                        <label class="form-check-label" for="overtime">Claim overtime for additional hours worked beyond standard hours</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Additional Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Any additional notes or remarks..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitWorkReport">Submit Work Report</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventModalLabel"><i class="fas fa-calendar-plus"></i> Today Site Update </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addEventForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label for="siteName" class="form-label">
                                <i class="fas fa-building"></i> Site Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="siteName" name="site_name" required 
                                   placeholder="Enter site name">
                            <div class="invalid-feedback">Please enter a site name.</div>
                        </div>
                        
                        <div class="col-md-5 mb-3">
                            <label for="eventDate" class="form-label">
                                <i class="fas fa-calendar-alt"></i> Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="eventDate" name="event_date" required>
                            <div class="invalid-feedback">Please select a date.</div>
                        </div>
                    </div>

                    <!-- Vendors Section -->
                    <div class="vendors-section mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="fas fa-truck"></i> Vendors</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addVendorBtn">
                                <i class="fas fa-plus"></i> Add Vendor
                            </button>
                        </div>
                        
                        <div id="vendorsContainer" class="vendors-container">
                            <!-- Vendor forms will be added here -->
                        </div>
                    </div>

                    <!-- Company Labours Section -->
                    <div class="company-labours-section mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="fas fa-users"></i> Company Labours</h6>
                        </div>
                        
                        <div class="company-labours-container position-relative" data-labour-count="0">
                            <!-- Company Labour forms will be added here -->
                            <button type="button" class="btn-add-company-labour" id="addCompanyLabourBtn">
                                <i class="fas fa-plus"></i> Add Company Labour
                            </button>
                        </div>
                    </div>

                    <!-- Travel Expenses Section -->
                    <div class="travel-expenses-section mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="fas fa-route"></i> Travel Expenses</h6>
                        </div>
                        
                        <div class="travel-expenses-container position-relative" data-travel-count="0">
                            <!-- Travel expense forms will be added here -->
                            <button type="button" class="btn-add-travel" id="addTravelBtn">
                                <i class="fas fa-plus"></i> Add Travel Expense
                            </button>
                        </div>
                    </div>

                    <!-- Beverages Section -->
                    <div class="beverages-section mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="fas fa-coffee"></i> Beverages</h6>
                        </div>
                        
                        <div class="beverages-container position-relative" data-beverage-count="0">
                            <!-- Beverage forms will be added here -->
                            <button type="button" class="btn-add-beverage" id="addBeverageBtn">
                                <i class="fas fa-plus"></i> Add Beverage
                            </button>
                        </div>
                    </div>

                    <!-- Work Progress Section -->
                    <div class="work-progress-section mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="fas fa-tasks"></i> Work Progress</h6>
                        </div>
                        
                        <div class="work-progress-container position-relative" data-work-count="0">
                            <!-- Work progress forms will be added here -->
                            <button type="button" class="btn-add-work" id="addWorkBtn">
                                <i class="fas fa-plus"></i> Add Work Progress
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveEventBtn">
                    <i class="fas fa-save"></i> Save Event
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Vendor Form Template (hidden) -->
<template id="vendorFormTemplate">
    <div class="vendor-form border rounded p-3 mb-3" data-vendor-number="1">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">
                <i class="fas fa-user-tie"></i> 
                <span class="vendor-title">Vendor #1</span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-vendor">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label">
                    <i class="fas fa-tags"></i> Vendor Type
                </label>
                <!-- Dropdown Select -->
                <div class="vendor-type-select">
                    <select class="form-select" name="vendor_type[]" required>
                        <option value="">Select Type</option>
                        <option value="material_supplier">Material Supplier</option>
                        <option value="equipment_rental">Equipment Rental</option>
                        <option value="labor_contractor">Labor Contractor</option>
                        <option value="electrical_contractor">Electrical Contractor</option>
                        <option value="plumbing_contractor">Plumbing Contractor</option>
                        <option value="hvac_contractor">HVAC Contractor</option>
                        <option value="transport">Transport Service</option>
                        <option value="security">Security Service</option>
                        <option value="cleaning">Cleaning Service</option>
                        <option value="custom">+ Add Custom Type</option>
                    </select>
                </div>
                <!-- Custom Input (Initially Hidden) -->
                <div class="vendor-type-custom d-none">
                    <div class="input-group">
                        <button class="btn btn-outline-secondary back-to-select" type="button">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <input type="text" class="form-control custom-vendor-type" 
                               placeholder="Enter custom vendor type">
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">
                    <i class="fas fa-user"></i> Vendor Name
                </label>
                <input type="text" class="form-control" name="vendor_name[]" required placeholder="Enter name">
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">
                    <i class="fas fa-phone"></i> Contact Number
                </label>
                <input type="tel" class="form-control" name="vendor_contact[]" required placeholder="Enter number">
            </div>
        </div>

        <!-- Vendor Material Section -->
        <div class="vendor-material-section mt-4">
            <h6 class="mb-3">
                <i class="fas fa-boxes"></i> Vendor Material
            </h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-image"></i> Material Picture
                    </label>
                    <input type="file" class="form-control" name="vendor_material_picture[]" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-comment-alt"></i> Remark
                    </label>
                    <textarea class="form-control" name="vendor_material_remark[]" rows="2" placeholder="Enter remarks about the material"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-rupee-sign"></i> Amount
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" name="vendor_material_amount[]" min="0" step="0.01" placeholder="Enter amount">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-receipt"></i> Bill Picture
                    </label>
                    <input type="file" class="form-control" name="vendor_bill_picture[]" accept="image/*">
                </div>
            </div>
        </div>

        <!-- Labor Section -->
        <div class="labor-section mt-4">
            <h6 class="mb-3">
                <i class="fas fa-hard-hat"></i> Laborers
            </h6>
            <div class="labor-container position-relative" data-labor-count="0">
                <!-- Labor forms will be added here -->
                <button type="button" class="btn-add-labor">
                    <i class="fas fa-plus"></i><span>Add Labor</span>
                </button>
            </div>
        </div>
    </div>
</template>

<!-- Labor Form Template (hidden) -->
<template id="laborFormTemplate">
    <div class="labor-form border rounded p-3 mb-3" data-labor-number="1">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="fas fa-user-clock"></i>
                <span class="labor-title">Labor #1</span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-labor">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row g-3">
            <!-- Labor Basic Info -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-user"></i> Labor Name
                </label>
                <input type="text" class="form-control" name="labor_name[]" required placeholder="Enter name">
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-phone"></i> Contact Number
                </label>
                <input type="tel" class="form-control" name="labor_contact[]" required placeholder="Enter number">
            </div>
            <!-- Attendance Section -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-sun"></i> Morning Attendance
                </label>
                <select class="form-control attendance-select morning-attendance" name="morning_attendance[]" required onchange="calculateTotalWages(this)">
                    <option value="">Select Status</option>
                    <option value="P">Present (P)</option>
                    <option value="A">Absent (A)</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-moon"></i> Evening Attendance
                </label>
                <select class="form-control attendance-select evening-attendance" name="evening_attendance[]" required onchange="calculateTotalWages(this)">
                    <option value="">Select Status</option>
                    <option value="P">Present (P)</option>
                    <option value="A">Absent (A)</option>
                </select>
            </div>
            <!-- Wages Section -->
            <div class="col-12">
                <hr>
                <h6 class="mb-3"><i class="fas fa-money-bill"></i> Daily Wages </h6>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-money-bill"></i> Wages Per Day
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control wages-per-day" name="wages_per_day[]" 
                           required placeholder="Enter amount" min="0" step="0.01" onchange="calculateTotalWages(this)">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-money-bill-wave"></i> Total Day Wages
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control total-day-wages" name="total_day_wages[]" 
                           readonly placeholder="0.00">
                </div>
            </div>

            

            <!-- Overtime Section -->
            <div class="col-12">
                <hr>
                <h6 class="mb-3"><i class="fas fa-clock"></i> Overtime Details</h6>
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-hourglass-half"></i> OT Hours
                </label>
                <input type="number" class="form-control ot-hours" name="ot_hours[]" 
                       min="0" max="24" placeholder="Hours" onchange="calculateOTAmount(this)">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-stopwatch"></i> OT Minutes
                </label>
                <select class="form-control ot-minutes" name="ot_minutes[]" onchange="calculateOTAmount(this)">
                    <option value="0">00</option>
                    <option value="30">30</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-rupee-sign"></i> OT Rate/Hour
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control ot-rate" name="ot_rate[]" 
                           min="0" step="0.01" placeholder="Rate" onchange="calculateOTAmount(this)">
                </div>
            </div>
            <div class="col-md-12">
                <label class="form-label">
                    <i class="fas fa-calculator"></i> Total OT Amount
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control total-ot-amount" name="total_ot_amount[]" 
                           readonly placeholder="0.00">
                </div>
            </div>

            <!-- Travel Expenses Section -->
            <div class="col-12">
                <hr>
                <h6 class="mb-3"><i class="fas fa-route"></i> Travel Expenses</h6>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-bus"></i> Mode of Transport
                </label>
                <select class="form-control" name="labor_transport_mode[]">
                    <option value="">Select Mode (if applicable)</option>
                    <option value="car">Car</option>
                    <option value="bike">Bike</option>
                    <option value="bus">Bus</option>
                    <option value="train">Train</option>
                    <option value="auto">Auto</option>
                    <option value="taxi">Taxi</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-rupee-sign"></i> Travel Amount
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control travel-amount" name="labor_travel_amount[]" 
                           min="0" step="0.01" placeholder="Enter amount">
                </div>
            </div>

            

            <!-- Grand Total Section -->
            <div class="col-12 mt-3">
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-money-check-alt"></i> Grand Total</h6>
                    <div class="input-group" style="width: 200px;">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control grand-total" name="grand_total[]" 
                               readonly placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Company Labour Form Template (hidden) -->
<template id="companyLabourFormTemplate">
    <div class="company-labour-form border rounded p-3 mb-3" data-labour-number="1">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="fas fa-user-tie"></i>
                <span class="labour-title">Company Labour #1</span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-company-labour">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row g-3">
            <!-- Labour Basic Info -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-user"></i> Labour Name
                </label>
                <input type="text" class="form-control" name="company_labour_name[]" required placeholder="Enter name">
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-phone"></i> Contact Number
                </label>
                <input type="tel" class="form-control" name="company_labour_contact[]" required placeholder="Enter number">
            </div>
            <!-- Attendance Section -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-sun"></i> Morning Attendance
                </label>
                <select class="form-control attendance-select morning-attendance" name="company_labour_morning_attendance[]" required onchange="calculateCompanyLabourTotalWages(this)">
                    <option value="">Select Status</option>
                    <option value="P">Present (P)</option>
                    <option value="A">Absent (A)</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-moon"></i> Evening Attendance
                </label>
                <select class="form-control attendance-select evening-attendance" name="company_labour_evening_attendance[]" required onchange="calculateCompanyLabourTotalWages(this)">
                    <option value="">Select Status</option>
                    <option value="P">Present (P)</option>
                    <option value="A">Absent (A)</option>
                </select>
            </div>
            <!-- Wages Section -->
            <div class="col-12">
                <hr>
                <h6 class="mb-3"><i class="fas fa-money-bill"></i> Daily Wages</h6>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-money-bill"></i> Wages Per Day
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control wages-per-day" name="company_labour_wages_per_day[]" 
                           required placeholder="Enter amount" min="0" step="0.01" onchange="calculateCompanyLabourTotalWages(this)">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-money-bill-wave"></i> Total Day Wages
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control total-day-wages" name="company_labour_total_day_wages[]" 
                           readonly placeholder="0.00">
                </div>
            </div>
             <!-- Overtime Section -->
             <div class="col-12">
                <hr>
                <h6 class="mb-3"><i class="fas fa-clock"></i> Overtime Details</h6>
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-hourglass-half"></i> OT Hours
                </label>
                <input type="number" class="form-control ot-hours" name="company_labour_ot_hours[]" 
                       min="0" max="24" placeholder="Hours" onchange="calculateCompanyLabourOTAmount(this)">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-stopwatch"></i> OT Minutes
                </label>
                <select class="form-control ot-minutes" name="company_labour_ot_minutes[]" onchange="calculateCompanyLabourOTAmount(this)">
                    <option value="0">00</option>
                    <option value="30">30</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-rupee-sign"></i> OT Rate/Hour
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control ot-rate" name="company_labour_ot_rate[]" 
                           min="0" step="0.01" placeholder="Rate" onchange="calculateCompanyLabourOTAmount(this)">
                </div>
            </div>
            <div class="col-md-12">
                <label class="form-label">
                    <i class="fas fa-calculator"></i> Total OT Amount
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control total-ot-amount" name="company_labour_total_ot_amount[]" 
                           readonly placeholder="0.00">
                </div>
            </div>

            <!-- Travel Expenses Section -->
            <div class="col-12">
                <hr>
                <h6 class="mb-3"><i class="fas fa-route"></i> Travel Expenses</h6>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-bus"></i> Mode of Transport
                </label>
                <select class="form-control" name="company_labour_transport_mode[]">
                    <option value="">Select Mode (if applicable)</option>
                    <option value="car">Car</option>
                    <option value="bike">Bike</option>
                    <option value="bus">Bus</option>
                    <option value="train">Train</option>
                    <option value="auto">Auto</option>
                    <option value="taxi">Taxi</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-rupee-sign"></i> Travel Amount
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control travel-amount" name="company_labour_travel_amount[]" 
                           min="0" step="0.01" placeholder="Enter amount">
                </div>
            </div>

            

            

           

            <!-- Grand Total Section -->
            <div class="col-12 mt-3">
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-money-check-alt"></i> Grand Total</h6>
                    <div class="input-group" style="width: 200px;">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control grand-total" name="company_labour_grand_total[]" 
                               readonly placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Travel Expense Form Template (hidden) -->
<template id="travelExpenseFormTemplate">
    <div class="travel-expense-form border rounded p-3 mb-3" data-travel-number="1">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="fas fa-car"></i>
                <span class="travel-title">Travel #1</span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-travel">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row g-3">
            <!-- Travel Location Details -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-map-marker-alt"></i> From Location
                </label>
                <input type="text" class="form-control" name="travel_from[]" 
                       required placeholder="Enter starting point">
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-map-marker"></i> To Location
                </label>
                <input type="text" class="form-control" name="travel_to[]" 
                       required placeholder="Enter destination">
            </div>

            <!-- Transport Details -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-bus"></i> Mode of Transport
                </label>
                <select class="form-control" name="transport_mode[]" required>
                    <option value="">Select Mode</option>
                    <option value="car">Car</option>
                    <option value="bike">Bike</option>
                    <option value="bus">Bus</option>
                    <option value="train">Train</option>
                    <option value="auto">Auto</option>
                    <option value="taxi">Taxi</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-road"></i> Distance (KM)
                </label>
                <input type="number" class="form-control distance-input" name="distance_km[]" 
                       min="0" step="0.1" required placeholder="Enter distance">
            </div>

            <!-- Amount Details -->
            <div class="col-md-12">
                <label class="form-label">
                    <i class="fas fa-money-bill-wave"></i> Total Amount
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control total-amount" name="travel_amount[]" 
                           min="0" step="0.01" required placeholder="Enter total amount">
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Beverage Form Template (hidden) -->
<template id="beverageFormTemplate">
    <div class="beverage-form border rounded p-3 mb-3" data-beverage-number="1">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="fas fa-glass-cheers"></i>
                <span class="beverage-title">Beverage #1</span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-beverage">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row g-3">
            <!-- Beverage Type -->
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-wine-glass-alt"></i> Beverage Type
                </label>
                <select class="form-control beverage-type" name="beverage_type[]" required>
                    <option value="">Select Type</option>
                    <option value="tea">Tea</option>
                    <option value="coffee">Coffee</option>
                    <option value="water">Water</option>
                    <option value="soft_drink">Soft Drink</option>
                    <option value="juice">Juice</option>
                    <option value="energy_drink">Energy Drink</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <!-- Beverage Name -->
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-tag"></i> Beverage Name
                </label>
                <input type="text" class="form-control" name="beverage_name[]" 
                       required placeholder="Enter beverage name">
            </div>

            <!-- Amount Paid -->
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-rupee-sign"></i> Amount Paid
                </label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control" name="beverage_amount[]" 
                           min="0" step="0.01" required placeholder="Enter amount">
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Work Progress Form Template (hidden) -->
<template id="workProgressFormTemplate">
    <div class="work-progress-form border rounded p-3 mb-3" data-work-number="1">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="fas fa-hammer"></i>
                <span class="work-title">Work Progress #1</span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-work">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row g-3">
            <!-- Work Category Field -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-th-large"></i> Work Category
                </label>
                <select class="form-control work-category" name="work_category[]" required onchange="updateWorkTypeOptions(this)">
                    <option value="">Select Category</option>
                    <option value="civil_work">Civil Work</option>
                    <option value="interior_work">Interior Work</option>
                    <option value="facade_work">Facade Work</option>
                    <option value="finishing_work">Finishing Work</option>
                    <option value="electrical_work">Electrical Work</option>
                    <option value="plumbing_work">Plumbing Work</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <!-- Type of Work Field (Dynamic options based on category) -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-tools"></i> Type of Work
                </label>
                <select class="form-control work-type" name="work_type[]" required>
                    <option value="">Select Type</option>
                    <!-- Options will be dynamically updated based on category -->
                </select>
            </div>
            
            <!-- Work Done Status -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-clipboard-check"></i> Work Done
                </label>
                <select class="form-control work-done" name="work_done[]" required>
                    <option value="">Select Status</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                    <option value="partial">Partially Complete</option>
                </select>
            </div>
            
            <!-- Completion Percentage (only shown if partial) -->
            <div class="col-md-6 completion-percentage-container d-none">
                <label class="form-label">
                    <i class="fas fa-percentage"></i> Completion Percentage
                </label>
                <div class="input-group">
                    <input type="number" class="form-control completion-percentage" name="completion_percentage[]" 
                           min="1" max="99" placeholder="Enter percentage">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            
            <!-- Remarks Field -->
            <div class="col-12">
                <label class="form-label">
                    <i class="fas fa-comment-alt"></i> Remarks
                </label>
                <textarea class="form-control" name="work_remarks[]" rows="2" placeholder="Enter remarks or observations"></textarea>
            </div>
            
            <!-- Media Upload Section -->
            <div class="col-12 mt-2">
                <hr>
                <div class="media-upload-header d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="fas fa-images"></i> Photos & Videos</h6>
                </div>
                
                <div id="work-media-container-{WORK_INDEX}" class="media-container">
                    <!-- Initial media upload item -->
                    <div class="media-item p-2">
                        <div class="custom-file-upload">
                            <label class="file-label">
                                <input type="file" name="work_media_file[{WORK_INDEX}][0]" class="work-media-file" accept="image/*,video/*">
                                <span class="file-custom"><i class="fas fa-upload"></i> Choose Media File</span>
                            </label>
                        </div>
                        <div class="media-preview mt-2"></div>
                    </div>
                </div>
                
                <div class="text-center mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary add-media-btn" onclick="addWorkMediaField({WORK_INDEX})">
                        <i class="fas fa-plus-circle"></i> Add Another Media File
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Inventory Form Template (hidden) -->
<template id="inventoryFormTemplate">
    <div class="inventory-form border rounded p-3 mb-3" data-inventory-number="1">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="fas fa-boxes"></i>
                <span class="inventory-title">Inventory Item #1</span>
            </h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-inventory">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row g-3">
            <!-- Inventory Type Field -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-tasks"></i> Inventory Type
                </label>
                <select class="form-control inventory-type" name="inventory_type[]" required>
                    <option value="">Select Type</option>
                    <option value="received">Received Item</option>
                    <option value="available">Item Available on Site</option>
                    <option value="consumed">Item Consumed</option>
                </select>
            </div>
            
            <!-- Material Selection Field -->
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-toolbox"></i> Material
                </label>
                <select class="form-control material-select" name="material[]" required>
                    <option value="">Select Material</option>
                    <option value="cement">Cement</option>
                    <option value="sand">Sand</option>
                    <option value="gravel">Gravel</option>
                    <option value="bricks">Bricks</option>
                    <option value="steel">Steel Bars</option>
                    <option value="tiles">Tiles</option>
                    <option value="paint">Paint</option>
                    <option value="wood">Wood</option>
                    <option value="glass">Glass</option>
                    <option value="pipes">Pipes</option>
                    <option value="electrical">Electrical Materials</option>
                    <option value="fixtures">Fixtures</option>
                    <option value="tools">Tools</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <!-- Quantity and Units Fields -->
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-balance-scale"></i> Quantity
                </label>
                <input type="number" class="form-control" name="quantity[]" min="0" step="0.01" required placeholder="Enter quantity">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-ruler-combined"></i> Units
                </label>
                <select class="form-control" name="units[]" required>
                    <option value="">Select Units</option>
                    <option value="kg">Kilograms (kg)</option>
                    <option value="g">Grams (g)</option>
                    <option value="ton">Ton</option>
                    <option value="lb">Pounds (lb)</option>
                    <option value="pcs">Pieces (pcs)</option>
                    <option value="m">Meters (m)</option>
                    <option value="cm">Centimeters (cm)</option>
                    <option value="ft">Feet (ft)</option>
                    <option value="in">Inches (in)</option>
                    <option value="sq_m">Square Meters (sq.m)</option>
                    <option value="sq_ft">Square Feet (sq.ft)</option>
                    <option value="l">Liters (L)</option>
                    <option value="ml">Milliliters (ml)</option>
                    <option value="cu_m">Cubic Meters (cu.m)</option>
                    <option value="cu_ft">Cubic Feet (cu.ft)</option>
                    <option value="bag">Bags</option>
                    <option value="roll">Rolls</option>
                    <option value="bundle">Bundles</option>
                    <option value="box">Boxes</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-warehouse"></i> Remaining Quantity
                </label>
                <div class="input-group">
                    <input type="number" class="form-control" name="remaining_quantity[]" min="0" step="0.01" placeholder="Enter remaining quantity">
                    <span class="input-group-text remaining-unit">-</span>
                </div>
            </div>
            
            <!-- Remarks Field -->
            <div class="col-12">
                <label class="form-label">
                    <i class="fas fa-comment-alt"></i> Remarks
                </label>
                <textarea class="form-control" name="inventory_remarks[]" rows="2" placeholder="Enter remarks or additional information"></textarea>
            </div>
            
            <!-- Bill Picture Upload -->
            <div class="col-12">
                <label class="form-label">
                    <i class="fas fa-file-invoice"></i> Bill Picture
                </label>
                <div class="bill-upload-container border rounded p-3 bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="custom-file-upload">
                                <label class="file-label">
                                    <input type="file" class="bill-picture-file" name="bill_picture[]" accept="image/*">
                                    <span class="file-custom d-flex align-items-center">
                                        <i class="fas fa-file-invoice me-2"></i> Choose File
                                    </span>
                                </label>
                                <small class="text-muted d-block mt-1">No file chosen</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-upload me-1"></i> Upload Bill Picture
                            </button>
                        </div>
                    </div>
                    <div class="bill-preview mt-3 d-none">
                        <img src="#" alt="Bill Preview" class="bill-preview-img img-fluid rounded">
                    </div>
                </div>
            </div>
            
            <!-- Media Upload Section -->
            <div class="col-12 mt-3">
                <label class="form-label">
                    <i class="fas fa-images"></i> Photos & Videos
                </label>
                <div class="media-upload-container border rounded p-3 bg-light">
                    <div class="media-container mb-3">
                        <!-- Initial media upload field -->
                        <div class="media-item mb-3 p-2 border rounded bg-white">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <div class="custom-file-upload">
                                        <label class="file-label">
                                            <input type="file" class="inventory-media-file" name="inventory_media_file[]" accept="image/*,video/*">
                                            <span class="file-custom d-flex align-items-center">
                                                <i class="fas fa-photo-video me-2"></i> Choose Photo/Video
                                            </span>
                                        </label>
                                        <small class="text-muted d-block mt-1">No file chosen</small>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <input type="text" class="form-control" name="inventory_media_caption[]" placeholder="Caption (optional)">
                                </div>
                                <div class="col-md-1 text-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-media-btn">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Preview container -->
                            <div class="media-preview mt-2 d-none">
                                <img src="#" alt="Preview" class="img-preview img-fluid rounded">
                                <video src="#" class="video-preview img-fluid rounded" controls style="display:none;"></video>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="button" class="btn btn-outline-primary add-inventory-media-btn">
                            <i class="fas fa-plus me-1"></i> Add More Photos/Videos
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Wages Summary Section Template (hidden) -->
<template id="wagesSummaryTemplate">
    <div class="wages-summary-section mt-4 mb-3">
        <h5 class="section-heading mb-3">
            <i class="fas fa-money-check-alt"></i> Wages Summary
        </h5>
        <div class="row g-3">
            <!-- Labour Wages Column -->
            <div class="col-md-6">
                <div class="wage-card">
                    <div class="wage-card-header bg-primary">
                        <h6 class="mb-0"><i class="fas fa-users"></i> Labour Wages</h6>
                    </div>
                    <div class="wage-card-body">
                        <div class="wage-item">
                            <div class="wage-label">
                                <i class="fas fa-user-tie text-primary"></i> Vendor Labour Wages
                            </div>
                            <div class="wage-amount vendor-labour-wages">₹0.00</div>
                        </div>
                        <div class="wage-item">
                            <div class="wage-label">
                                <i class="fas fa-hard-hat text-success"></i> Company Labour Wages
                            </div>
                            <div class="wage-amount company-labour-wages">₹0.00</div>
                        </div>
                        <div class="wage-item total">
                            <div class="wage-label">Total Labour Wages</div>
                            <div class="wage-amount total-labour-wages">₹0.00</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Miscellaneous Expenses Column -->
            <div class="col-md-6">
                <div class="wage-card">
                    <div class="wage-card-header bg-warning">
                        <h6 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Miscellaneous Expenses</h6>
                    </div>
                    <div class="wage-card-body">
                        <div class="wage-item">
                            <div class="wage-label">
                                <i class="fas fa-route text-info"></i> Travel Expenses
                            </div>
                            <div class="wage-amount travel-expenses">₹0.00</div>
                        </div>
                        <div class="wage-item">
                            <div class="wage-label">
                                <i class="fas fa-coffee text-danger"></i> Beverages
                            </div>
                            <div class="wage-amount beverage-expenses">₹0.00</div>
                        </div>
                        <div class="wage-item">
                            <div class="wage-label">
                                <i class="fas fa-bus text-primary"></i> Vendor Labour Travel
                            </div>
                            <div class="wage-amount vendor-travel-expenses">₹0.00</div>
                        </div>
                        <div class="wage-item">
                            <div class="wage-label">
                                <i class="fas fa-bus text-success"></i> Company Labour Travel
                            </div>
                            <div class="wage-amount company-travel-expenses">₹0.00</div>
                        </div>
                        <div class="wage-item total">
                            <div class="wage-label">Total Misc. Expenses</div>
                            <div class="wage-amount total-misc-expenses">₹0.00</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grand Total Row -->
        <div class="row mt-3">
            <div class="col-12 text-end">
                <div class="grand-total-container">
                    <span class="grand-total-label me-2">Grand Total:</span>
                    <span class="grand-total-amount">₹0.00</span>
                    <button type="button" class="btn btn-sm btn-icon refresh-summary ms-2" title="Refresh Summary">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
// Add this to your existing JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Safe element access with console warnings for missing elements
    function safeGetElement(id, name) {
        const el = document.getElementById(id);
        if (!el) console.warn(`${name || id} element not found`);
        return el;
    }
    
    const addVendorBtn = safeGetElement('addVendorBtn', 'Add vendor button');
    const vendorsContainer = safeGetElement('vendorsContainer', 'Vendors container');
    const vendorTemplate = safeGetElement('vendorFormTemplate', 'Vendor template');
    const laborTemplate = safeGetElement('laborFormTemplate', 'Labor template');

    function updateAddVendorButtonPosition() {
        const addVendorBtn = safeGetElement('addVendorBtn');
        const vendorsContainer = safeGetElement('vendorsContainer');
        
        if (!addVendorBtn || !vendorsContainer) return;
        
        const lastVendor = vendorsContainer.querySelector('.vendor-form:last-of-type');
        
        if (lastVendor) {
            const lastVendorRect = lastVendor.getBoundingClientRect();
            const containerRect = vendorsContainer.getBoundingClientRect();
            const newTop = lastVendorRect.bottom - containerRect.top + 20;
            addVendorBtn.style.top = `${newTop}px`;
        } else {
            addVendorBtn.style.top = '20px';
        }
    }

    function initializeLaborSection(vendorForm) {
        const laborContainer = vendorForm.querySelector('.labor-container');
        const addLaborBtn = laborContainer.querySelector('.btn-add-labor');
        
        if (addLaborBtn && laborContainer) {
            addLaborBtn.addEventListener('click', function() {
                // Remove the button temporarily
                addLaborBtn.remove();
                
                const laborCount = parseInt(laborContainer.dataset.laborCount || '0') + 1;
                const laborForm = laborTemplate.content.cloneNode(true);
                
                // Update labor form
                const laborFormElement = laborForm.querySelector('.labor-form');
                laborFormElement.dataset.laborNumber = laborCount;
                laborFormElement.querySelector('.labor-title').textContent = `Labor #${laborCount}`;
                
                // Add remove functionality
                const removeLaborBtn = laborForm.querySelector('.remove-labor');
                if (removeLaborBtn) {
                    removeLaborBtn.addEventListener('click', function() {
                        const laborForm = this.closest('.labor-form');
                        laborForm.remove();
                        updateLaborNumbers(laborContainer);
                        updateAddLaborButtonPosition(laborContainer);
                    });
                }
                
                // Add attendance calculation
                const morningAttendance = laborForm.querySelector('.morning-attendance');
                const eveningAttendance = laborForm.querySelector('.evening-attendance');
                const wagesPerDay = laborForm.querySelector('.wages-per-day');
                const totalWages = laborForm.querySelector('.total-wages');
                
                function calculateWages() {
                    const morning = morningAttendance.value;
                    const evening = eveningAttendance.value;
                    const wages = parseFloat(wagesPerDay.value) || 0;
                    
                    let multiplier = 0;
                    if (morning === 'P' && evening === 'P') multiplier = 1;
                    else if (morning === 'P' || evening === 'P') multiplier = 0.5;
                    
                    totalWages.value = (wages * multiplier).toFixed(2);
                }
                
                [morningAttendance, eveningAttendance, wagesPerDay].forEach(el => {
                    el.addEventListener('change', calculateWages);
                });
                
                // Append the new labor form
                laborContainer.appendChild(laborForm);
                
                // Add the button back at the end
                laborContainer.appendChild(addLaborBtn);
                
                // Update labor count
                laborContainer.dataset.laborCount = laborCount;
                
                // Update button position
                updateAddLaborButtonPosition(laborContainer);
            });
            
            // Initial button position
            updateAddLaborButtonPosition(laborContainer);
        }
    }

    function updateLaborNumbers(container) {
        const laborForms = container.querySelectorAll('.labor-form');
        laborForms.forEach((form, index) => {
            const number = index + 1;
            form.dataset.laborNumber = number;
            form.querySelector('.labor-title').textContent = `Labor #${number}`;
        });
        container.dataset.laborCount = laborForms.length;
    }

    function updateAddLaborButtonPosition(laborContainer) {
        const addLaborBtn = laborContainer.querySelector('.btn-add-labor');
        const lastLabor = laborContainer.querySelector('.labor-form:last-of-type');
        
        if (lastLabor) {
            const lastLaborRect = lastLabor.getBoundingClientRect();
            const containerRect = laborContainer.getBoundingClientRect();
            const newTop = lastLaborRect.bottom - containerRect.top + 20;
            addLaborBtn.style.top = `${newTop}px`;
        } else {
            addLaborBtn.style.top = '20px';
        }
    }

    if (addVendorBtn && vendorsContainer && vendorTemplate) {
        addVendorBtn.addEventListener('click', function() {
            // Remove the button temporarily
            addVendorBtn.remove();
            
            const vendorForm = vendorTemplate.content.cloneNode(true);
            const vendorNumber = vendorsContainer.querySelectorAll('.vendor-form').length + 1;
            
            // Set vendor number
            const vendorFormElement = vendorForm.querySelector('.vendor-form');
            vendorFormElement.dataset.vendorNumber = vendorNumber;
            vendorFormElement.querySelector('.vendor-title').textContent = `Vendor #${vendorNumber}`;
            
            // Initialize labor section for the new vendor
            initializeLaborSection(vendorFormElement);
            
            // Append the new vendor form to the container
            vendorsContainer.appendChild(vendorForm);
            
            // Add the button back at the end
            vendorsContainer.appendChild(addVendorBtn);
            
            // Update button position
            updateAddVendorButtonPosition();
            
            // Add remove vendor functionality
            const removeBtn = vendorsContainer.querySelector(`[data-vendor-number="${vendorNumber}"] .remove-vendor`);
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    const vendorForm = this.closest('.vendor-form');
                    vendorForm.remove();
                    updateVendorNumbers();
                    updateAddVendorButtonPosition();
                });
            }
        });

        // Initial button position
        updateAddVendorButtonPosition();
        
        // Update button position on window resize
        window.addEventListener('resize', updateAddVendorButtonPosition);
    }
    
    function updateVendorNumbers() {
        const vendorForms = document.querySelectorAll('.vendor-form');
        vendorForms.forEach((form, index) => {
            const number = index + 1;
            form.dataset.vendorNumber = number;
            form.querySelector('.vendor-title').textContent = `Vendor #${number}`;
        });
    }
});

// Function to smoothly scroll to an element
function smoothScrollTo(element) {
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }
}

// Add event listener for the Add Vendor button
document.getElementById('addVendorBtn').addEventListener('click', function() {
    // Wait for the vendor to be added to the DOM
    setTimeout(() => {
        const vendorsContainer = document.getElementById('vendorsContainer');
        const lastVendor = vendorsContainer.lastElementChild;
        smoothScrollTo(lastVendor);
    }, 100);
});

// Function to add labor and scroll to it
function addLabor(laborContainer) {
    // Original labor addition logic
    const laborCount = parseInt(laborContainer.dataset.laborCount || '0') + 1;
    const laborForm = laborTemplate.content.cloneNode(true);
    
    // Update labor form
    const laborFormElement = laborForm.querySelector('.labor-form');
    laborFormElement.dataset.laborNumber = laborCount;
    laborFormElement.querySelector('.labor-title').textContent = `Labor #${laborCount}`;
    
    // Add remove functionality
    const removeLaborBtn = laborForm.querySelector('.remove-labor');
    if (removeLaborBtn) {
        removeLaborBtn.addEventListener('click', function() {
            const laborForm = this.closest('.labor-form');
            laborForm.remove();
            updateLaborNumbers(laborContainer);
            updateAddLaborButtonPosition(laborContainer);
        });
    }
    
    // Add the new labor form
    laborContainer.appendChild(laborForm);
    
    // Update labor count
    laborContainer.dataset.laborCount = laborCount;
    
    // Scroll to the newly added labor form
    const newLaborForm = laborContainer.lastElementChild;
    smoothScrollTo(newLaborForm);
    
    // Focus on the first input field of the new labor form
    const firstInput = newLaborForm.querySelector('input[type="text"]');
    if (firstInput) {
        firstInput.focus();
    }
}

// Update the existing click handler for the Add Labor button
document.addEventListener('click', function(e) {
    if (e.target.matches('.btn-add-labor')) {
        const laborContainer = e.target.closest('.labor-container');
        if (laborContainer) {
            addLabor(laborContainer);
        }
    }
});

// Company Labour Functions
function calculateCompanyLabourTotalWages(element) {
    const labourForm = element.closest('.company-labour-form');
    const morningAttendance = labourForm.querySelector('.morning-attendance').value;
    const eveningAttendance = labourForm.querySelector('.evening-attendance').value;
    const wagesPerDay = parseFloat(labourForm.querySelector('.wages-per-day').value) || 0;
    
    let multiplier = 0;
    if (morningAttendance === 'P' && eveningAttendance === 'P') multiplier = 1;
    else if (morningAttendance === 'P' || eveningAttendance === 'P') multiplier = 0.5;
    
    const totalDayWages = wagesPerDay * multiplier;
    labourForm.querySelector('.total-day-wages').value = totalDayWages.toFixed(2);
    
    updateCompanyLabourGrandTotal(labourForm);
}

function calculateCompanyLabourOTAmount(element) {
    const labourForm = element.closest('.company-labour-form');
    const hours = parseInt(labourForm.querySelector('.ot-hours').value) || 0;
    const minutes = parseInt(labourForm.querySelector('.ot-minutes').value) || 0;
    const rate = parseFloat(labourForm.querySelector('.ot-rate').value) || 0;
    
    const totalHours = hours + (minutes / 60);
    const otAmount = totalHours * rate;
    
    labourForm.querySelector('.total-ot-amount').value = otAmount.toFixed(2);
    
    updateCompanyLabourGrandTotal(labourForm);
}

function updateCompanyLabourGrandTotal(labourForm) {
    const totalDayWages = parseFloat(labourForm.querySelector('.total-day-wages').value) || 0;
    const totalOTAmount = parseFloat(labourForm.querySelector('.total-ot-amount').value) || 0;
    const travelAmount = parseFloat(labourForm.querySelector('.travel-amount').value) || 0;
    
    const grandTotal = totalDayWages + totalOTAmount + travelAmount;
    labourForm.querySelector('.grand-total').value = grandTotal.toFixed(2);
}

// Add Company Labour functionality
document.getElementById('addCompanyLabourBtn').addEventListener('click', function() {
    const container = document.getElementById('companyLaboursContainer');
    const template = document.getElementById('companyLabourFormTemplate');
    const labourCount = container.children.length + 1;
    
    const clone = template.content.cloneNode(true);
    const form = clone.querySelector('.company-labour-form');
    
    // Update form numbering
    form.dataset.labourNumber = labourCount;
    form.querySelector('.labour-title').textContent = `Company Labour #${labourCount}`;
    
    // Add remove functionality
    const removeBtn = form.querySelector('.remove-company-labour');
    removeBtn.addEventListener('click', function() {
        form.remove();
        updateCompanyLabourNumbers();
    });
    
    container.appendChild(form);
    
    // Scroll to new form
    smoothScrollTo(form);
    
    // Focus first input
    const firstInput = form.querySelector('input[type="text"]');
    if (firstInput) {
        firstInput.focus();
    }
});

function updateCompanyLabourNumbers() {
    const container = document.getElementById('companyLaboursContainer');
    const forms = container.querySelectorAll('.company-labour-form');
    forms.forEach((form, index) => {
        const number = index + 1;
        form.dataset.labourNumber = number;
        form.querySelector('.labour-title').textContent = `Company Labour #${number}`;
    });
}

// Function to update Add Company Labour button position
function updateAddCompanyLabourButtonPosition(container) {
    const addBtn = container.querySelector('.btn-add-company-labour');
    if (addBtn) {
        const forms = container.querySelectorAll('.company-labour-form');
        if (forms.length > 0) {
            const lastForm = forms[forms.length - 1];
            const lastFormBottom = lastForm.offsetTop + lastForm.offsetHeight;
            addBtn.style.top = `${lastFormBottom + 20}px`; // 20px spacing
            addBtn.style.bottom = 'auto';
        } else {
            addBtn.style.top = 'auto';
            addBtn.style.bottom = '0';
        }
    }
}

// Modified Add Company Labour functionality
document.getElementById('addCompanyLabourBtn').addEventListener('click', function() {
    const container = document.querySelector('.company-labours-container');
    const template = document.getElementById('companyLabourFormTemplate');
    
    // Remove the button temporarily
    this.remove();
    
    const labourCount = parseInt(container.dataset.labourCount || '0') + 1;
    const clone = template.content.cloneNode(true);
    const form = clone.querySelector('.company-labour-form');
    
    // Update form numbering
    form.dataset.labourNumber = labourCount;
    form.querySelector('.labour-title').textContent = `Company Labour #${labourCount}`;
    
    // Add remove functionality
    const removeBtn = form.querySelector('.remove-company-labour');
    removeBtn.addEventListener('click', function() {
        form.remove();
        updateCompanyLabourNumbers();
        updateAddCompanyLabourButtonPosition(container);
    });
    
    // Append the new form
    container.appendChild(form);
    
    // Add the button back
    container.appendChild(this);
    
    // Update labour count
    container.dataset.labourCount = labourCount;
    
    // Update button position
    updateAddCompanyLabourButtonPosition(container);
    
    // Scroll to new form
    smoothScrollTo(form);
    
    // Focus first input
    const firstInput = form.querySelector('input[type="text"]');
    if (firstInput) {
        firstInput.focus();
    }
});

// Initialize button position on page load
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.company-labours-container');
    if (container) {
        updateAddCompanyLabourButtonPosition(container);
    }
});

// Update company labour numbers and button position
function updateCompanyLabourNumbers() {
    const container = document.querySelector('.company-labours-container');
    const forms = container.querySelectorAll('.company-labour-form');
    forms.forEach((form, index) => {
        const number = index + 1;
        form.dataset.labourNumber = number;
        form.querySelector('.labour-title').textContent = `Company Labour #${number}`;
    });
    container.dataset.labourCount = forms.length;
    updateAddCompanyLabourButtonPosition(container);
}

// Function to calculate travel amount
function calculateTravelAmount(element) {
    const travelForm = element.closest('.travel-expense-form');
    const distance = parseFloat(travelForm.querySelector('.distance-input').value) || 0;
    const rate = parseFloat(travelForm.querySelector('.rate-input').value) || 0;
    
    const totalAmount = distance * rate;
    travelForm.querySelector('.total-amount').value = totalAmount.toFixed(2);
}

// Function to update Add Travel button position
function updateAddTravelButtonPosition(container) {
    const addBtn = container.querySelector('.btn-add-travel');
    if (addBtn) {
        const forms = container.querySelectorAll('.travel-expense-form');
        if (forms.length > 0) {
            const lastForm = forms[forms.length - 1];
            const lastFormBottom = lastForm.offsetTop + lastForm.offsetHeight;
            addBtn.style.top = `${lastFormBottom + 20}px`; // 20px spacing
            addBtn.style.bottom = 'auto';
        } else {
            addBtn.style.top = 'auto';
            addBtn.style.bottom = '0';
        }
    }
}

// Add Travel Expense functionality
document.getElementById('addTravelBtn').addEventListener('click', function() {
    const container = document.querySelector('.travel-expenses-container');
    const template = document.getElementById('travelExpenseFormTemplate');
    
    // Remove the button temporarily
    this.remove();
    
    const travelCount = parseInt(container.dataset.travelCount || '0') + 1;
    const clone = template.content.cloneNode(true);
    const form = clone.querySelector('.travel-expense-form');
    
    // Update form numbering
    form.dataset.travelNumber = travelCount;
    form.querySelector('.travel-title').textContent = `Travel #${travelCount}`;
    
    // Add remove functionality
    const removeBtn = form.querySelector('.remove-travel');
    removeBtn.addEventListener('click', function() {
        form.remove();
        updateTravelNumbers();
        updateAddTravelButtonPosition(container);
    });
    
    // Append the new form
    container.appendChild(form);
    
    // Add the button back
    container.appendChild(this);
    
    // Update travel count
    container.dataset.travelCount = travelCount;
    
    // Update button position
    updateAddTravelButtonPosition(container);
    
    // Scroll to new form
    smoothScrollTo(form);
    
    // Focus first input
    const firstInput = form.querySelector('input[type="text"]');
    if (firstInput) {
        firstInput.focus();
    }
});

// Initialize travel button position on page load
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.travel-expenses-container');
    if (container) {
        updateAddTravelButtonPosition(container);
    }
});

// Update travel numbers
function updateTravelNumbers() {
    const container = document.querySelector('.travel-expenses-container');
    const forms = container.querySelectorAll('.travel-expense-form');
    forms.forEach((form, index) => {
        const number = index + 1;
        form.dataset.travelNumber = number;
        form.querySelector('.travel-title').textContent = `Travel #${number}`;
    });
    container.dataset.travelCount = forms.length;
}

// Function to update Add Beverage button position
function updateAddBeverageButtonPosition(container) {
    const addBtn = container.querySelector('.btn-add-beverage');
    if (addBtn) {
        const forms = container.querySelectorAll('.beverage-form');
        if (forms.length > 0) {
            const lastForm = forms[forms.length - 1];
            const lastFormBottom = lastForm.offsetTop + lastForm.offsetHeight;
            addBtn.style.top = `${lastFormBottom + 20}px`; // 20px spacing
            addBtn.style.bottom = 'auto';
        } else {
            addBtn.style.top = 'auto';
            addBtn.style.bottom = '0';
        }
    }
}

// Add Beverage functionality
document.getElementById('addBeverageBtn').addEventListener('click', function() {
    const container = document.querySelector('.beverages-container');
    const template = document.getElementById('beverageFormTemplate');
    
    // Remove the button temporarily
    this.remove();
    
    const beverageCount = parseInt(container.dataset.beverageCount || '0') + 1;
    const clone = template.content.cloneNode(true);
    const form = clone.querySelector('.beverage-form');
    
    // Update form numbering
    form.dataset.beverageNumber = beverageCount;
    form.querySelector('.beverage-title').textContent = `Beverage #${beverageCount}`;
    
    // Add remove functionality
    const removeBtn = form.querySelector('.remove-beverage');
    removeBtn.addEventListener('click', function() {
        form.remove();
        updateBeverageNumbers();
        updateAddBeverageButtonPosition(container);
    });
    
    // Append the new form
    container.appendChild(form);
    
    // Add the button back
    container.appendChild(this);
    
    // Update beverage count
    container.dataset.beverageCount = beverageCount;
    
    // Update button position
    updateAddBeverageButtonPosition(container);
    
    // Scroll to new form
    smoothScrollTo(form);
    
    // Focus first input
    const firstInput = form.querySelector('select.beverage-type');
    if (firstInput) {
        firstInput.focus();
    }
});

// Initialize beverage button position on page load
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.beverages-container');
    if (container) {
        updateAddBeverageButtonPosition(container);
    }
});

// Update beverage numbers
function updateBeverageNumbers() {
    const container = document.querySelector('.beverages-container');
    const forms = container.querySelectorAll('.beverage-form');
    forms.forEach((form, index) => {
        const number = index + 1;
        form.dataset.beverageNumber = number;
        form.querySelector('.beverage-title').textContent = `Beverage #${number}`;
    });
    container.dataset.beverageCount = forms.length;
}

// Work type options based on category
const workTypeOptions = {
    'civil_work': [
        {value: 'excavation', label: 'Excavation'},
        {value: 'foundation', label: 'Foundation Work'},
        {value: 'rcc_work', label: 'RCC Work'},
        {value: 'brick_work', label: 'Brick Work'},
        {value: 'plastering', label: 'Plastering'},
        {value: 'waterproofing', label: 'Waterproofing'},
        {value: 'other', label: 'Other Civil Work'}
    ],
    'interior_work': [
        {value: 'flooring', label: 'Flooring'},
        {value: 'carpentry', label: 'Carpentry'},
        {value: 'false_ceiling', label: 'False Ceiling'},
        {value: 'painting', label: 'Painting'},
        {value: 'wall_finishes', label: 'Wall Finishes'},
        {value: 'furniture', label: 'Furniture Installation'},
        {value: 'other', label: 'Other Interior Work'}
    ],
    'facade_work': [
        {value: 'glazing', label: 'Glazing'},
        {value: 'cladding', label: 'Cladding'},
        {value: 'external_painting', label: 'External Painting'},
        {value: 'structural_glazing', label: 'Structural Glazing'},
        {value: 'other', label: 'Other Facade Work'}
    ],
    'finishing_work': [
        {value: 'painting', label: 'Painting'},
        {value: 'polishing', label: 'Polishing'},
        {value: 'flooring', label: 'Floor Finishing'},
        {value: 'tiling', label: 'Tiling'},
        {value: 'other', label: 'Other Finishing Work'}
    ],
    'electrical_work': [
        {value: 'wiring', label: 'Wiring'},
        {value: 'fixtures', label: 'Light Fixtures'},
        {value: 'switchboard', label: 'Switchboard Installation'},
        {value: 'conduit', label: 'Conduit Installation'},
        {value: 'other', label: 'Other Electrical Work'}
    ],
    'plumbing_work': [
        {value: 'piping', label: 'Piping'},
        {value: 'fixtures', label: 'Fixture Installation'},
        {value: 'drainage', label: 'Drainage System'},
        {value: 'water_supply', label: 'Water Supply System'},
        {value: 'other', label: 'Other Plumbing Work'}
    ],
    'other': [
        {value: 'custom', label: 'Custom Work Type'}
    ]
};

// Function to update work type options based on selected category
function updateWorkTypeOptions(selectElement) {
    const workForm = selectElement.closest('.work-progress-form');
    const categoryValue = selectElement.value;
    const typeSelect = workForm.querySelector('.work-type');
    
    // Clear existing options
    typeSelect.innerHTML = '<option value="">Select Type</option>';
    
    // If a category is selected, add the corresponding options
    if (categoryValue && workTypeOptions[categoryValue]) {
        workTypeOptions[categoryValue].forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option.value;
            optionElement.textContent = option.label;
            typeSelect.appendChild(optionElement);
        });
    }
}

// Function to toggle completion percentage based on work done status
function toggleCompletionPercentage(selectElement) {
    const workForm = selectElement.closest('.work-progress-form');
    const percentageContainer = workForm.querySelector('.completion-percentage-container');
    const percentageInput = workForm.querySelector('.completion-percentage');
    
    if (selectElement.value === 'partial') {
        percentageContainer.classList.remove('d-none');
        percentageInput.required = true;
    } else {
        percentageContainer.classList.add('d-none');
        percentageInput.required = false;
        percentageInput.value = '';
    }
}

// Function to handle file selection and preview
function handleFileSelect(fileInput) {
    const mediaItem = fileInput.closest('.media-item');
    const previewContainer = mediaItem.querySelector('.media-preview');
    const imgPreview = mediaItem.querySelector('.img-preview');
    const videoPreview = mediaItem.querySelector('.video-preview');
    
    if (fileInput.files && fileInput.files[0]) {
        const file = fileInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Show preview container
            previewContainer.classList.remove('d-none');
            
            // Check if file is image or video
            if (file.type.startsWith('image/')) {
                imgPreview.src = e.target.result;
                imgPreview.style.display = 'block';
                videoPreview.style.display = 'none';
            } else if (file.type.startsWith('video/')) {
                videoPreview.src = e.target.result;
                videoPreview.style.display = 'block';
                imgPreview.style.display = 'none';
            }
        };
        
        reader.readAsDataURL(file);
        
        // Update the filename display
        const fileLabel = mediaItem.querySelector('.file-custom');
        fileLabel.innerHTML = `<i class="fas fa-file"></i> ${file.name}`;
    }
}

// Function to add a new media item
function addMediaItem(mediaContainer) {
    const mediaItemTemplate = `
        <div class="media-item mb-2">
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="custom-file-upload">
                        <label class="file-label">
                            <input type="file" class="work-media-file" name="work_media_file[]" accept="image/*,video/*">
                            <span class="file-custom">
                                <i class="fas fa-upload"></i> Choose Photo/Video
                            </span>
                        </label>
                    </div>
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control" name="work_media_caption[]" placeholder="Caption (optional)">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-media-btn">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
            <div class="media-preview mt-2 d-none">
                <img src="#" alt="Preview" class="img-preview img-fluid rounded">
                <video src="#" class="video-preview img-fluid rounded" controls style="display:none;"></video>
            </div>
        </div>
    `;
    
    // Add new item to the container
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = mediaItemTemplate;
    const newItem = tempDiv.firstElementChild;
    mediaContainer.appendChild(newItem);
    
    // Add event listeners to the new item
    const fileInput = newItem.querySelector('.work-media-file');
    fileInput.addEventListener('change', function() {
        handleFileSelect(this);
    });
    
    const removeBtn = newItem.querySelector('.remove-media-btn');
    removeBtn.addEventListener('click', function() {
        newItem.remove();
    });
    
    // Make sure the media section is visible after adding a new item
    const workProgressForm = mediaContainer.closest('.work-progress-form');
    if (workProgressForm) {
        // Update the container height to accommodate the new item
        setTimeout(() => {
            // Reposition the work progress button
            const workProgressContainer = document.querySelector('.work-progress-container');
            updateAddWorkButtonPosition(workProgressContainer);
        }, 10);
    }
    
    return newItem;
}

// Function to update Add Work button position
function updateAddWorkButtonPosition(container) {
    const addBtn = container.querySelector('.btn-add-work');
    if (addBtn) {
        const forms = container.querySelectorAll('.work-progress-form');
        if (forms.length > 0) {
            const lastForm = forms[forms.length - 1];
            const lastFormBottom = lastForm.offsetTop + lastForm.offsetHeight;
            addBtn.style.top = `${lastFormBottom + 20}px`; // 20px spacing
            addBtn.style.bottom = 'auto';
        } else {
            addBtn.style.top = 'auto';
            addBtn.style.bottom = '0';
        }
    }
}

// Add Work Progress functionality
document.getElementById('addWorkBtn').addEventListener('click', function() {
    const container = document.querySelector('.work-progress-container');
    const template = document.getElementById('workProgressFormTemplate');
    
    // Remove the button temporarily
    this.remove();
    
    const workCount = parseInt(container.dataset.workCount || '0') + 1;
    const clone = template.content.cloneNode(true);
    const form = clone.querySelector('.work-progress-form');
    
    // Update form numbering
    form.dataset.workNumber = workCount;
    form.querySelector('.work-title').textContent = `Work Progress #${workCount}`;
    
    // Add remove functionality
    const removeBtn = form.querySelector('.remove-work');
    removeBtn.addEventListener('click', function() {
        form.remove();
        updateWorkNumbers();
        updateAddWorkButtonPosition(container);
    });
    
    // Add event listeners for the work done status
    const workDoneSelect = form.querySelector('.work-done');
    if (workDoneSelect) {
        workDoneSelect.addEventListener('change', function() {
            toggleCompletionPercentage(this);
        });
    }
    
    // Add event listeners for file inputs
    const fileInputs = form.querySelectorAll('.work-media-file');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            handleFileSelect(this);
        });
    });
    
    // Add event listener for adding more media
    const addMediaBtn = form.querySelector('.add-media-btn');
    const mediaContainer = form.querySelector('.media-container');
    addMediaBtn.addEventListener('click', function() {
        const newItem = addMediaItem(mediaContainer);
        // Scroll to the new media item
        smoothScrollTo(newItem);
    });
    
    // Add event listeners for removing media
    const removeMediaBtns = form.querySelectorAll('.remove-media-btn');
    removeMediaBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.media-item').remove();
        });
    });
    
    // Append the new form
    container.appendChild(form);
    
    // Add the button back
    container.appendChild(this);
    
    // Update work count
    container.dataset.workCount = workCount;
    
    // Update button position
    updateAddWorkButtonPosition(container);
    
    // Scroll to new form
    smoothScrollTo(form);
    
    // Focus first input
    const firstInput = form.querySelector('select.work-category');
    if (firstInput) {
        firstInput.focus();
    }
});

// Initialize work button position on page load
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.work-progress-container');
    if (container) {
        updateAddWorkButtonPosition(container);
    }
    
    // Global event delegation for work done status changes
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('work-done')) {
            toggleCompletionPercentage(e.target);
        }
    });
});

// Update work numbers
function updateWorkNumbers() {
    const container = document.querySelector('.work-progress-container');
    const forms = container.querySelectorAll('.work-progress-form');
    forms.forEach((form, index) => {
        const number = index + 1;
        form.dataset.workNumber = number;
        form.querySelector('.work-title').textContent = `Work Progress #${number}`;
    });
    container.dataset.workCount = forms.length;
}

// Add these functions for the Inventory Section

// Inventory Section Functions
document.addEventListener('DOMContentLoaded', function() {
    // Add Inventory Section to event modal
    const addEventModal = document.getElementById('addEventModal');
    if (addEventModal) {
        const addEventForm = addEventModal.querySelector('#addEventForm');
        
        // Add Inventory Section after Work Progress Section
        const lastSection = addEventForm.querySelector('.work-progress-section');
        if (lastSection) {
            // Create inventory section HTML
            const inventorySection = document.createElement('div');
            inventorySection.className = 'inventory-section mt-4';
            inventorySection.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="fas fa-boxes"></i> Inventory</h6>
                </div>
                
                <div class="inventory-container position-relative" data-inventory-count="0">
                    <!-- Inventory forms will be added here -->
                    <button type="button" class="btn-add-inventory" id="addInventoryBtn">
                        <i class="fas fa-plus"></i> Add Inventory Item
                    </button>
                </div>
            `;
            
            // Insert after the last section
            lastSection.parentNode.insertBefore(inventorySection, lastSection.nextSibling);
            
            // Add event listener for the Add Inventory button
            const addInventoryBtn = document.getElementById('addInventoryBtn');
            if (addInventoryBtn) {
                addInventoryBtn.addEventListener('click', function() {
                    addInventoryItem();
                });
            }
        }
    }
});

// Function to add an inventory item
function addInventoryItem() {
    const container = document.querySelector('.inventory-container');
    const template = document.getElementById('inventoryFormTemplate');
    
    if (!container || !template) {
        console.error('Inventory container or template not found');
        return;
    }
    
    // Remove the button temporarily
    const addInventoryBtn = document.getElementById('addInventoryBtn');
    if (addInventoryBtn) {
        addInventoryBtn.remove();
    }
    
    const inventoryCount = parseInt(container.dataset.inventoryCount || '0') + 1;
    const clone = template.content.cloneNode(true);
    const form = clone.querySelector('.inventory-form');
    
    // Update form numbering
    form.dataset.inventoryNumber = inventoryCount;
    form.querySelector('.inventory-title').textContent = `Inventory Item #${inventoryCount}`;
    
    // Add remove functionality
    const removeBtn = form.querySelector('.remove-inventory');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            form.remove();
            updateInventoryNumbers();
            updateAddInventoryButtonPosition();
        });
    }
    
    // Add bill picture preview functionality
    const billPictureInput = form.querySelector('.bill-picture-file');
    if (billPictureInput) {
        billPictureInput.addEventListener('change', function() {
            handleBillPictureSelect(this);
        });
    }
    
    // Add event listeners for inventory type changes
    const inventoryTypeSelect = form.querySelector('select.inventory-type');
    const remainingQuantityField = form.querySelector('.col-md-4:nth-child(3)'); // Select the third col-md-4 instead of using :has()
    
    if (inventoryTypeSelect && remainingQuantityField) {
        // Show/hide remaining quantity based on inventory type
        inventoryTypeSelect.addEventListener('change', function() {
            updateRemainingQuantityVisibility(this, remainingQuantityField);
        });
        
        // Initial visibility check
        updateRemainingQuantityVisibility(inventoryTypeSelect, remainingQuantityField);
    }
    
    // Add unit synchronization for remaining quantity
    const unitsSelect = form.querySelector('select[name="units[]"]');
    const remainingUnitSpan = form.querySelector('.remaining-unit');
    if (unitsSelect && remainingUnitSpan) {
        // Set initial value if a unit is already selected
        if (unitsSelect.value) {
            updateRemainingUnit(unitsSelect, remainingUnitSpan);
        }
        
        // Add change event listener
        unitsSelect.addEventListener('change', function() {
            updateRemainingUnit(this, remainingUnitSpan);
        });
    }
    
    // Add remaining quantity level indicator
    const remainingQuantityInput = form.querySelector('input[name="remaining_quantity[]"]');
    const quantityInput = form.querySelector('input[name="quantity[]"]');
    
    if (remainingQuantityInput) {
        remainingQuantityInput.addEventListener('input', function() {
            updateRemainingQuantityIndicator(this);
        });
    }
    
    if (quantityInput && remainingQuantityInput) {
        quantityInput.addEventListener('input', function() {
            updateRemainingQuantityIndicator(remainingQuantityInput);
        });
    }
    
    // Add event listeners for media files
    const mediaInputs = form.querySelectorAll('.inventory-media-file');
    mediaInputs.forEach(input => {
        input.addEventListener('change', function() {
            handleInventoryMediaSelect(this);
        });
    });
    
    // Add event listener for adding more media
    const addMediaBtn = form.querySelector('.add-inventory-media-btn');
    const mediaContainer = form.querySelector('.media-container');
    if (addMediaBtn && mediaContainer) {
        addMediaBtn.addEventListener('click', function() {
            const newItem = addInventoryMediaItem(mediaContainer);
            smoothScrollTo(newItem);
        });
    }
    
    // Add event listeners for removing media
    const removeMediaBtns = form.querySelectorAll('.remove-media-btn');
    removeMediaBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.media-item').remove();
        });
    });
    
    // Append the new form
    container.appendChild(form);
    
    // Add the button back
    container.appendChild(addInventoryBtn);
    
    // Update inventory count
    container.dataset.inventoryCount = inventoryCount;
    
    // Update button position
    updateAddInventoryButtonPosition();
    
    // Scroll to new form
    smoothScrollTo(form);
    
    // Focus first input
    const firstInput = form.querySelector('select.inventory-type');
    if (firstInput) {
        firstInput.focus();
    }
}

// Function to handle bill picture selection
function handleBillPictureSelect(input) {
    const inventoryForm = input.closest('.inventory-form');
    const previewContainer = inventoryForm.querySelector('.bill-preview');
    const previewImg = inventoryForm.querySelector('.bill-preview-img');
    const fileNameDisplay = input.closest('.custom-file-upload').querySelector('small');
    
    if (!previewContainer || !previewImg) return;
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewContainer.classList.remove('d-none');
        };
        
        reader.readAsDataURL(file);
        
        // Update the filename display
        const fileLabel = inventoryForm.querySelector('.bill-picture-file').nextElementSibling;
        if (fileLabel) {
            fileLabel.innerHTML = `<i class="fas fa-file"></i> ${file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name}`;
        }
        
        // Update the "No file chosen" text
        if (fileNameDisplay) {
            fileNameDisplay.textContent = file.name;
        }
    }
}

// Function to handle inventory media selection
function handleInventoryMediaSelect(input) {
    const mediaItem = input.closest('.media-item');
    const previewContainer = mediaItem.querySelector('.media-preview');
    const imgPreview = mediaItem.querySelector('.img-preview');
    const videoPreview = mediaItem.querySelector('.video-preview');
    const fileNameDisplay = input.closest('.custom-file-upload').querySelector('small');
    
    if (!previewContainer || !imgPreview || !videoPreview) return;
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewContainer.classList.remove('d-none');
            
            if (file.type.startsWith('image/')) {
                imgPreview.src = e.target.result;
                imgPreview.style.display = 'block';
                videoPreview.style.display = 'none';
            } else if (file.type.startsWith('video/')) {
                videoPreview.src = e.target.result;
                videoPreview.style.display = 'block';
                imgPreview.style.display = 'none';
            }
        };
        
        reader.readAsDataURL(file);
        
        // Update the filename display
        const fileLabel = mediaItem.querySelector('.file-custom');
        fileLabel.innerHTML = `<i class="fas fa-file"></i> ${file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name}`;
        
        // Update the "No file chosen" text
        if (fileNameDisplay) {
            fileNameDisplay.textContent = file.name;
        }
    }
}

// Function to add inventory media item
function addInventoryMediaItem(mediaContainer) {
    const mediaItemTemplate = `
        <div class="media-item mb-3 p-2 border rounded bg-white">
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="custom-file-upload">
                        <label class="file-label">
                            <input type="file" class="inventory-media-file" name="inventory_media_file[]" accept="image/*,video/*">
                            <span class="file-custom d-flex align-items-center">
                                <i class="fas fa-photo-video me-2"></i> Choose Photo/Video
                            </span>
                        </label>
                        <small class="text-muted d-block mt-1">No file chosen</small>
                    </div>
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control" name="inventory_media_caption[]" placeholder="Caption (optional)">
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-media-btn">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
            <div class="media-preview mt-2 d-none">
                <img src="#" alt="Preview" class="img-preview img-fluid rounded">
                <video src="#" class="video-preview img-fluid rounded" controls style="display:none;"></video>
            </div>
        </div>
    `;
    
    // Add new item to the container
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = mediaItemTemplate;
    const newItem = tempDiv.firstElementChild;
    mediaContainer.appendChild(newItem);
    
    // Add event listeners to the new item
    const fileInput = newItem.querySelector('.inventory-media-file');
    fileInput.addEventListener('change', function() {
        handleInventoryMediaSelect(this);
    });
    
    const removeBtn = newItem.querySelector('.remove-media-btn');
    removeBtn.addEventListener('click', function() {
        newItem.remove();
    });
    
    // Make sure the inventory section is visible after adding a new item
    const inventoryForm = mediaContainer.closest('.inventory-form');
    if (inventoryForm) {
        setTimeout(() => {
            // Reposition the inventory button
            updateAddInventoryButtonPosition();
        }, 10);
    }
    
    return newItem;
}

// Function to update inventory numbers
function updateInventoryNumbers() {
    const container = document.querySelector('.inventory-container');
    const forms = container.querySelectorAll('.inventory-form');
    forms.forEach((form, index) => {
        const number = index + 1;
        form.dataset.inventoryNumber = number;
        form.querySelector('.inventory-title').textContent = `Inventory Item #${number}`;
    });
    container.dataset.inventoryCount = forms.length;
}

// Function to update Add Inventory button position
function updateAddInventoryButtonPosition() {
    const container = document.querySelector('.inventory-container');
    const addBtn = container.querySelector('.btn-add-inventory');
    if (addBtn) {
        const forms = container.querySelectorAll('.inventory-form');
        if (forms.length > 0) {
            const lastForm = forms[forms.length - 1];
            const lastFormBottom = lastForm.offsetTop + lastForm.offsetHeight;
            addBtn.style.top = `${lastFormBottom + 20}px`; // 20px spacing
            addBtn.style.bottom = 'auto';
        } else {
            addBtn.style.top = 'auto';
            addBtn.style.bottom = '0';
        }
    }
}

// ... existing JavaScript code
</script>

<style>
/* Add these styles to your existing CSS */
.vendor-type-custom .input-group {
    display: flex;
}

.vendor-type-custom .back-to-select {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.vendor-type-custom .form-control {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.back-to-select {
    padding: 0.375rem 0.75rem;
}

.back-to-select:hover {
    background-color: #e9ecef;
}

/* Add these styles to your existing CSS */
.labor-form {
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

.labor-form:hover {
    background-color: #fff;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.input-group-text {
    background-color: #e9ecef;
    border-right: none;
}

.input-group .form-control {
    border-left: none;
}

.input-group .form-control:focus {
    border-left: none;
    box-shadow: none;
}

.labor-title {
    color: #6c757d;
}

/* Add styles for attendance select */
.attendance-select {
    font-weight: 500;
}

.attendance-select option[value="P"] {
    color: #198754;
    font-weight: 600;
}

.attendance-select option[value="A"] {
    color: #dc3545;
    font-weight: 600;
}

/* ... existing styles ... */
    
.vendors-container {
    position: relative;
    padding-bottom: 50px; /* Space for the floating button */
}
    
.add-vendor-btn {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    transition: all 0.3s ease;
    z-index: 1;
    background-color: #fff;
    border: 2px dashed #007bff;
    color: #007bff;
    padding: 8px 20px;
    border-radius: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}
    
.add-vendor-btn:hover {
    background-color: #007bff;
    color: #fff;
}
    
.add-vendor-btn i {
    font-size: 14px;
}
    
.labor-container {
    position: relative;
    padding-bottom: 50px;
}
    
.btn-add-labor {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    transition: all 0.3s ease;
    z-index: 1;
    background-color: #fff;
    border: 2px dashed #28a745;
    color: #28a745;
    padding: 8px 20px;
    border-radius: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}
    
.btn-add-labor:hover {
    background-color: #28a745;
    color: #fff;
}
    
.btn-add-labor i {
    font-size: 14px;
}
    
.labor-form {
    position: relative;
    z-index: 2;
}

/* Company Labour Button Styles */
.company-labours-container {
    position: relative;
    padding-bottom: 60px;
}

.btn-add-company-labour {
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
    transition: all 0.3s ease;
    z-index: 1;
    background-color: #fff;
    border: 2px dashed #28a745;
    color: #28a745;
    padding: 10px 24px;
    border-radius: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(40, 167, 69, 0.15);
    min-width: 200px;
    justify-content: center;
    text-align: center;
}

.btn-add-company-labour:hover {
    background-color: #28a745;
    color: #fff;
    box-shadow: 0 6px 15px rgba(40, 167, 69, 0.25);
    transform: translateX(-50%) translateY(-3px);
}

.btn-add-company-labour:active {
    transform: translateX(-50%) translateY(0);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15);
}

.btn-add-company-labour i {
    font-size: 16px;
}

.company-labour-form {
    position: relative;
    z-index: 2;
    background-color: #fff;
}

/* Travel Expenses Styles */
.travel-expenses-container {
    position: relative;
    padding-bottom: 60px;
}

.btn-add-travel {
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
    transition: all 0.3s ease;
    z-index: 1;
    background-color: #fff;
    border: 2px dashed #17a2b8;
    color: #17a2b8;
    padding: 10px 24px;
    border-radius: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(23, 162, 184, 0.15);
    min-width: 200px;
    justify-content: center;
    text-align: center;
}

.btn-add-travel:hover {
    background-color: #17a2b8;
    color: #fff;
    box-shadow: 0 6px 15px rgba(23, 162, 184, 0.25);
    transform: translateX(-50%) translateY(-3px);
}

.btn-add-travel:active {
    transform: translateX(-50%) translateY(0);
    box-shadow: 0 2px 8px rgba(23, 162, 184, 0.15);
}

.btn-add-travel i {
    font-size: 16px;
}

.travel-expense-form {
    position: relative;
    z-index: 2;
    background-color: #fff;
}

.travel-expense-form .form-control:read-only {
    background-color: #f8f9fa;
}

/* Beverages Styles */
.beverages-container {
    position: relative;
    padding-bottom: 60px;
}

.btn-add-beverage {
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
    transition: all 0.3s ease;
    z-index: 1;
    background-color: #fff;
    border: 2px dashed #6f42c1;
    color: #6f42c1;
    padding: 10px 24px;
    border-radius: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(111, 66, 193, 0.15);
    min-width: 200px;
    justify-content: center;
    text-align: center;
}

.btn-add-beverage:hover {
    background-color: #6f42c1;
    color: #fff;
    box-shadow: 0 6px 15px rgba(111, 66, 193, 0.25);
    transform: translateX(-50%) translateY(-3px);
}

.btn-add-beverage:active {
    transform: translateX(-50%) translateY(0);
    box-shadow: 0 2px 8px rgba(111, 66, 193, 0.15);
}

.btn-add-beverage i {
    font-size: 16px;
}

.beverage-form {
    position: relative;
    z-index: 2;
    background-color: #fff;
}

.beverage-form .form-control:read-only {
    background-color: #f8f9fa;
}

/* Work Progress Styles */
.work-progress-container {
    position: relative;
    padding-bottom: 60px;
}

.btn-add-work {
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
    transition: all 0.3s ease;
    z-index: 1;
    background-color: #fff;
    border: 2px dashed #20c997;
    color: #20c997;
    padding: 10px 24px;
    border-radius: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(32, 201, 151, 0.15);
    min-width: 200px;
    justify-content: center;
    text-align: center;
}

.btn-add-work:hover {
    background-color: #20c997;
    color: #fff;
    box-shadow: 0 6px 15px rgba(32, 201, 151, 0.25);
    transform: translateX(-50%) translateY(-3px);
}

.btn-add-work:active {
    transform: translateX(-50%) translateY(0);
    box-shadow: 0 2px 8px rgba(32, 201, 151, 0.15);
}

.btn-add-work i {
    font-size: 16px;
}

.work-progress-form {
    position: relative;
    z-index: 2;
    background-color: #fff;
}

/* File upload styling */
.custom-file-upload {
    position: relative;
    display: inline-block;
    width: 100%;
}

.file-label {
    display: block;
    width: 100%;
    margin-bottom: 0;
    cursor: pointer;
}

.work-media-file {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 0.1px;
    height: 0.1px;
    overflow: hidden;
}

.file-custom {
    display: block;
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    text-align: center;
}

.file-custom:hover {
    background-color: #f8f9fa;
}

.file-custom i {
    margin-right: 5px;
}

/* Media preview */
.media-preview {
    max-width: 100%;
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 5px;
    text-align: center;
}

.img-preview, .video-preview {
    max-height: 200px;
    max-width: 100%;
    margin: 0 auto;
    display: block;
}

/* Media container styles */
.media-container {
    position: relative;
}

.media-upload-header h6 {
    font-weight: 600;
    color: #555;
}

/* Media button styles */
.add-media-btn {
    margin-top: 10px;
}

/* Inventory Section Styles */
.inventory-container {
    position: relative;
    padding-bottom: 60px;
}

.btn-add-inventory {
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
    transition: all 0.3s ease;
    z-index: 1;
    background-color: #fff;
    border: 2px dashed #fd7e14;
    color: #fd7e14;
    padding: 10px 24px;
    border-radius: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(253, 126, 20, 0.15);
    min-width: 200px;
    justify-content: center;
    text-align: center;
}

.btn-add-inventory:hover {
    background-color: #fd7e14;
    color: #fff;
    box-shadow: 0 6px 15px rgba(253, 126, 20, 0.25);
    transform: translateX(-50%) translateY(-3px);
}

.btn-add-inventory:active {
    transform: translateX(-50%) translateY(0);
    box-shadow: 0 2px 8px rgba(253, 126, 20, 0.15);
}

.btn-add-inventory i {
    font-size: 16px;
}

.inventory-form {
    position: relative;
    z-index: 2;
    background-color: #fff;
    transition: all 0.3s ease;
}

.inventory-form:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
}

.inventory-title {
    font-weight: 600;
}

/* Bill Picture Upload */
.bill-upload-container,
.media-upload-container {
    border: 1px solid #e9ecef;
    background-color: #f8f9fa;
    transition: all 0.2s ease;
}

.bill-upload-container:hover,
.media-upload-container:hover {
    background-color: #fff;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
}

.file-custom {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.file-custom:hover {
    background-color: #f8f9fa;
    border-color: #adb5bd;
}

.media-item {
    transition: all 0.2s ease;
    border: 1px solid #e9ecef;
}

.media-item:hover {
    border-color: #ced4da;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
}

.bill-preview,
.media-preview {
    background-color: #fff;
    padding: 10px;
    border-radius: 5px;
    text-align: center;
    border: 1px solid #e9ecef;
}

.add-inventory-media-btn {
    padding: 0.375rem 1rem;
    font-weight: 500;
}
</style>

<!-- Bootstrap & jQuery JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="js/site_supervision.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Sidebar toggle functionality and responsive behavior
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarCollapse');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const toggleIcon = document.getElementById('sidebar-toggle-icon');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        
        // Function to handle desktop sidebar toggle
        function toggleSidebar() {
            sidebar.classList.toggle('expanded');
            content.classList.toggle('expanded');
            sidebarToggle.classList.toggle('expanded');
            
            // Toggle icon between left and right arrows
            if (sidebar.classList.contains('expanded')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        }
        
        // Function to handle mobile sidebar toggle
        function toggleMobileSidebar() {
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('active');
            
            // Toggle hamburger/close icon
            const hamburgerIcon = hamburgerBtn.querySelector('i');
            if (sidebar.classList.contains('mobile-open')) {
                hamburgerIcon.classList.remove('fa-bars');
                hamburgerIcon.classList.add('fa-times');
                document.body.style.overflow = 'hidden';
            } else {
                hamburgerIcon.classList.remove('fa-times');
                hamburgerIcon.classList.add('fa-bars');
                document.body.style.overflow = '';
            }
        }
        
        // Add event listeners
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', toggleMobileSidebar);
        }
        
        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                if (sidebar.classList.contains('mobile-open')) {
                    toggleMobileSidebar();
                }
            });
        }
        
        // Close sidebar when clicking menu items on mobile
        const sidebarItems = document.querySelectorAll('#sidebar .sidebar-item');
        sidebarItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768 && sidebar.classList.contains('mobile-open')) {
                    toggleMobileSidebar();
                }
            });
        });
        
        // Responsive sidebar behavior for smaller screens
        function handleResponsiveLayout() {
            // For tablet/desktop view
            if (window.innerWidth <= 991 && window.innerWidth > 768) {
                if (sidebar.classList.contains('expanded')) {
                    sidebar.classList.remove('expanded');
                    content.classList.remove('expanded');
                    sidebarToggle.classList.remove('expanded');
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            }
            
            // For mobile view
            if (window.innerWidth <= 768) {
                // Hide sidebar on mobile by default
                sidebar.classList.remove('expanded');
                content.classList.remove('expanded');
                if (sidebarToggle) {
                    sidebarToggle.classList.remove('expanded');
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
                
                // If window resized from larger to mobile, close any open mobile sidebar
                if (sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            } else {
                // If screen resized from mobile to larger, make sure everything is reset
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        // Initial check
        handleResponsiveLayout();
        
        // Check on window resize
        window.addEventListener('resize', handleResponsiveLayout);
        
        // Update time in real-time
        function updateTime() {
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                // Create a date object with IST timezone
                const options = {
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                    timeZone: 'Asia/Kolkata'
                };
                
                const now = new Date();
                const istTime = now.toLocaleTimeString('en-US', options);
                
                timeElement.textContent = `${istTime} (IST)`;
            }
        }
        
        // Update time immediately and then every second
        updateTime();
        setInterval(updateTime, 1000);
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover'
            });
        });
        
        // Handle modals for smaller screens
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('shown.bs.modal', function () {
                if (window.innerWidth <= 576) {
                    modal.querySelector('.modal-dialog').classList.add('modal-fullscreen');
                } else {
                    modal.querySelector('.modal-dialog').classList.remove('modal-fullscreen');
                }
            });
        });
        
        // Check modal size on window resize
        window.addEventListener('resize', function() {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const modalDialog = openModal.querySelector('.modal-dialog');
                if (window.innerWidth <= 576) {
                    modalDialog.classList.add('modal-fullscreen');
                } else {
                    modalDialog.classList.remove('modal-fullscreen');
                }
            }
        });
    });
</script>

<!-- Punch In/Out Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const punchButton = document.getElementById('punch-button');
        
        // Check current attendance status on page load
        function checkAttendanceStatus() {
            if (punchButton) {
                const userId = punchButton.dataset.userId;
                
                // Make an AJAX call to get the current attendance status
                fetch(`includes/attendance/check_status.php?user_id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const isPunchedIn = data.is_punched_in;
                            
                            if (isPunchedIn) {
                                // Update button to punch out state
                                punchButton.dataset.status = 'out';
                                punchButton.dataset.attendanceId = data.attendance_id;
                                punchButton.classList.remove('btn-success');
                                punchButton.classList.add('btn-danger');
                                punchButton.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out';
                                
                                // Update status text with working hours
                                document.getElementById('punch-status').innerHTML = 
                                    `<span class="text-success"><i class="fas fa-clock"></i> Working for ${data.hours_worked} hr ${data.minutes_worked} min</span>`;
                                
                                // Start timer
                                startWorkTimer(data.seconds_worked || 0);
                            } else {
                                // Update button to punch in state
                                punchButton.dataset.status = 'in';
                                if (punchButton.hasAttribute('data-attendance-id')) {
                                    punchButton.removeAttribute('data-attendance-id');
                                }
                                punchButton.classList.remove('btn-danger');
                                punchButton.classList.add('btn-success');
                                punchButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In';
                                
                                // Update status text
                                document.getElementById('punch-status').innerHTML = '<span>Not punched in today</span>';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error checking attendance status:', error);
                    });
            }
        }
        
        // Check attendance status when page loads
        checkAttendanceStatus();
        
        if (punchButton) {
            punchButton.addEventListener('click', function() {
                // Get geolocation before proceeding
                if (navigator.geolocation) {
                    punchButton.disabled = true;
                    punchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            // Position obtained successfully
                            processPunch(position);
                        },
                        function(error) {
                            // Error getting position
                            console.error('Geolocation error:', error);
                            alert('Unable to get your location. Please enable location services and try again.');
                            punchButton.disabled = false;
                            punchButton.innerHTML = punchButton.dataset.status === 'in' ? 
                                '<i class="fas fa-sign-in-alt"></i> Punch In' : 
                                '<i class="fas fa-sign-out-alt"></i> Punch Out';
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );
                } else {
                    alert('Geolocation is not supported by this browser. Unable to punch in/out.');
                }
            });
        }
        
        function processPunch(position) {
            const status = punchButton.dataset.status;
            const userId = punchButton.dataset.userId;
            const attendanceId = punchButton.hasAttribute('data-attendance-id') ? 
                                 punchButton.dataset.attendanceId : null;
            
            // For punch out, directly show work report modal instead of doing a network request
            if (status === 'out' && attendanceId) {
                punchButton.disabled = false;
                punchButton.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out';
                showWorkReportModal(attendanceId, true);
                return;
            }
            
            // Prepare data to send
            const data = new FormData();
            data.append('action', status === 'in' ? 'punch_in' : 'punch_out');
            data.append('user_id', userId);
            if (attendanceId) {
                data.append('attendance_id', attendanceId);
            }
            
            // Add location data
            data.append('latitude', position.coords.latitude);
            data.append('longitude', position.coords.longitude);
            data.append('accuracy', position.coords.accuracy);
            
            // Add device info
            data.append('device_info', navigator.userAgent);
            
            // Send to server
            fetch('includes/attendance/process_attendance.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button state without page reload
                    if (status === 'in') {
                        punchButton.dataset.status = 'out';
                        punchButton.classList.remove('btn-success');
                        punchButton.classList.add('btn-danger');
                        punchButton.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out';
                        punchButton.dataset.attendanceId = data.attendance_id;
                        
                        // Update status text
                        document.getElementById('punch-status').innerHTML = 
                            `<span class="text-success"><i class="fas fa-clock"></i> Working for ${data.hours_worked} hr ${data.minutes_worked} min</span>`;
                        
                        // Start timer
                        startWorkTimer(data.seconds_worked || 0);
                        
                        // Show success message in a nicer way
                        showNotification(data.message, 'success');
                    }
                } else {
                    // Show error in a nicer way
                    showNotification(data.message, 'error');
                }
                
                punchButton.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while processing your request. Please try again.', 'error');
                punchButton.disabled = false;
                punchButton.innerHTML = status === 'in' ? 
                    '<i class="fas fa-sign-in-alt"></i> Punch In' : 
                    '<i class="fas fa-sign-out-alt"></i> Punch Out';
            });
        }
        
        // Timer for work duration
        let workTimer;
        function startWorkTimer(initialSeconds = 0) {
            let seconds = parseInt(initialSeconds);
            
            // Clear existing timer if it exists
            if (workTimer) {
                clearInterval(workTimer);
            }
            
            // Update the UI with initial time
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            
            document.getElementById('punch-status').innerHTML = 
                `<span class="text-success"><i class="fas fa-clock"></i> Working for ${hours} hr ${minutes} min</span>`;
            
            // Start interval
            workTimer = setInterval(function() {
                seconds++;
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                
                document.getElementById('punch-status').innerHTML = 
                    `<span class="text-success"><i class="fas fa-clock"></i> Working for ${hours} hr ${minutes} min</span>`;
            }, 60000); // Update every minute
        }
        
        // If already punched in, start the timer
        if (punchButton && punchButton.dataset.status === 'out') {
            startWorkTimer(0);
        }
        
        // Function to show work report modal
        function showWorkReportModal(attendanceId, isPunchingOut = false) {
            // Get attendance details to display in the modal
            fetch(`includes/attendance/get_attendance.php?id=${attendanceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the modal with attendance details
                        document.getElementById('wr-attendance-id').value = attendanceId;
                        document.getElementById('wr-punch-in').textContent = data.attendance.punch_in;
                        
                        if (isPunchingOut) {
                            // For punch out flow, set a flag to indicate this is part of punch out
                            document.getElementById('is-punching-out').value = "1";
                            
                            // Set punch out time to current time (not saved to DB yet)
                            const now = new Date();
                            const options = {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true,
                                timeZone: 'Asia/Kolkata'
                            };
                            const istTime = now.toLocaleTimeString('en-US', options) + ' IST';
                            document.getElementById('wr-punch-out').textContent = istTime;
                            
                            // Working hours can't be calculated yet
                            document.getElementById('wr-hours').textContent = 'Will be calculated on submission';
                            
                            // Change modal title to indicate this is part of punch out
                            document.getElementById('workReportModalLabel').textContent = 'Submit Work Report to Punch Out';
                            
                            // Change the submit button text
                            document.getElementById('submitWorkReport').textContent = 'Submit & Punch Out';
                            
                            // Set up event handler for modal close to ensure punch button state remains correct
                            const workReportModal = document.getElementById('workReportModal');
                            
                            // Remove existing event listener if any
                            if (workReportModal._punchCancelHandler) {
                                workReportModal.removeEventListener('hidden.bs.modal', workReportModal._punchCancelHandler);
                            }
                            
                            // Add new event listener for modal close
                            workReportModal._punchCancelHandler = function() {
                                // If closing without submitting, make sure the button remains in punch out state
                                if (punchButton && punchButton.dataset.status === 'out') {
                                    punchButton.disabled = false;
                                    punchButton.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out';
                                }
                            };
                            
                            workReportModal.addEventListener('hidden.bs.modal', workReportModal._punchCancelHandler);
                        } else {
                            // Regular work report (viewing past report)
                            document.getElementById('is-punching-out').value = "0";
                            document.getElementById('wr-punch-out').textContent = data.attendance.punch_out;
                            document.getElementById('wr-hours').textContent = data.attendance.working_hours + ' hours';
                            document.getElementById('workReportModalLabel').textContent = 'Submit Work Report';
                            document.getElementById('submitWorkReport').textContent = 'Submit Work Report';
                            
                            // If report already exists, populate it
                            if (data.attendance.work_report) {
                                document.getElementById('work_report').value = data.attendance.work_report;
                                document.getElementById('overtime').checked = data.attendance.overtime == 1;
                                document.getElementById('remarks').value = data.attendance.remarks || '';
                            }
                        }
                        
                        // Clear any previous error messages
                        const errorElement = document.getElementById('workReportError');
                        if (errorElement) {
                            errorElement.textContent = '';
                            errorElement.classList.add('d-none');
                        }
                        
                        // Show the modal
                        const workReportModal = new bootstrap.Modal(document.getElementById('workReportModal'));
                        workReportModal.show();
                    } else {
                        showNotification('Error loading attendance details: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading attendance details', 'error');
                });
        }
        
        // Handle work report submission
        document.getElementById('submitWorkReport').addEventListener('click', function() {
            const workReportForm = document.getElementById('workReportForm');
            
            // Basic form validation
            const workReport = document.getElementById('work_report').value.trim();
            if (!workReport) {
                document.getElementById('workReportError').textContent = 'Please enter your work report';
                document.getElementById('workReportError').classList.remove('d-none');
                return;
            }
            
            // Collect form data
            const formData = new FormData();
            formData.append('attendance_id', document.getElementById('wr-attendance-id').value);
            formData.append('work_report', workReport);
            formData.append('overtime', document.getElementById('overtime').checked ? '1' : '0');
            formData.append('remarks', document.getElementById('remarks').value.trim());
            formData.append('is_punching_out', document.getElementById('is-punching-out').value);
            
            // Submit form data
            fetch('includes/attendance/submit_work_report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the modal
                    bootstrap.Modal.getInstance(document.getElementById('workReportModal')).hide();
                    
                    // Check if this was part of the punch out process
                    if (document.getElementById('is-punching-out').value === "1") {
                        // Reset the punch button to punch in state
                        if (punchButton) {
                            punchButton.dataset.status = 'in';
                            punchButton.classList.remove('btn-danger');
                            punchButton.classList.add('btn-success');
                            punchButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In';
                            
                            // Remove attendance ID attribute
                            if (punchButton.hasAttribute('data-attendance-id')) {
                                punchButton.removeAttribute('data-attendance-id');
                            }
                            
                            // Update status message
                            document.getElementById('punch-status').innerHTML = '<span>Not punched in</span>';
                            
                            // Clear the work timer if it's running
                            if (workTimer) {
                                clearInterval(workTimer);
                                workTimer = null;
                            }
                        }
                    }
                    
                    // Show success message
                    showNotification(data.message, 'success');
                } else {
                    // Show error message in the modal
                    document.getElementById('workReportError').textContent = data.message;
                    document.getElementById('workReportError').classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('workReportError').textContent = 'An error occurred while submitting the work report';
                document.getElementById('workReportError').classList.remove('d-none');
            });
        });
        
        // Add event handlers for the Work Report Modal cancel button
        document.querySelectorAll('#workReportModal .btn-close, #workReportModal .btn-secondary').forEach(button => {
            button.addEventListener('click', function() {
                // Make sure the punch button state is preserved as "Punch Out" if the user is punched in
                const isPunchingOut = document.getElementById('is-punching-out').value === "1";
                if (isPunchingOut && punchButton && punchButton.dataset.status === 'out') {
                    punchButton.disabled = false;
                    punchButton.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out';
                }
            });
        });
    });
</script>

<!-- Function to show notifications -->
<script>
    function showNotification(message, type) {
        // Create notification element if it doesn't exist
        let notification = document.getElementById('punch-notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'punch-notification';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.padding = '15px 20px';
            notification.style.borderRadius = '5px';
            notification.style.zIndex = '9999';
            notification.style.maxWidth = '300px';
            notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            notification.style.transition = 'all 0.3s ease';
            document.body.appendChild(notification);
        }
        
        // Set type-specific styles
        if (type === 'success') {
            notification.style.backgroundColor = '#34C759';
            notification.style.color = 'white';
        } else {
            notification.style.backgroundColor = '#FF3B30';
            notification.style.color = 'white';
        }
        
        // Set content
        notification.innerHTML = `
            <div style="display: flex; align-items: center;">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}" style="margin-right: 10px;"></i>
                <span>${message}</span>
            </div>
        `;
        
        // Show notification
        notification.style.opacity = '1';
        
        // Hide after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }
</script>

<!-- Prevent accidental page close when punched in -->
<script>
    // Check if user is punched in before leaving page
    window.addEventListener('beforeunload', function(e) {
        const punchButton = document.getElementById('punch-button');
        if (punchButton && punchButton.dataset.status === 'out') {
            // User is currently punched in
            const message = 'You are currently punched in. If you leave this page, your timer will stop but you will still be logged as working. Are you sure you want to leave?';
            e.returnValue = message;
            return message;
        }
    });
    
    // Periodically sync the timer with server to ensure accuracy
    setInterval(function() {
        const punchButton = document.getElementById('punch-button');
        if (punchButton && punchButton.dataset.status === 'out' && punchButton.dataset.attendanceId) {
            fetch(`includes/attendance/check_status.php?user_id=${punchButton.dataset.userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.is_punched_in) {
                        // Resync the timer
                        startWorkTimer(data.seconds_worked || 0);
                    }
                })
                .catch(error => {
                    console.error('Error syncing work timer:', error);
                });
        }
    }, 300000); // Sync every 5 minutes
</script>

<!-- Calendar JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Calendar variables
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');
        const currentMonthDisplay = document.querySelector('.current-month');
        const viewButtons = document.querySelectorAll('.calendar-view-options .btn');
        const calendarBody = document.querySelector('.calendar-table tbody');
        const calendarContainer = document.querySelector('.calendar-container');
        
        // Current date
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();
        const today = new Date();
        
        // Track view mode (month, week, day)
        let currentView = 'month';
        
        // Responsive variables
        let isMobile = window.innerWidth < 768;
        let isVerySmall = window.innerWidth < 576;
        let isUltraSmall = window.innerWidth < 375;
        
        // Month names
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        // Short month names for small screens
        const shortMonthNames = [
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ];
        
        // Day names
        const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        // Short day names for smaller screens
        const shortDayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        // Ultra short day names for very small screens
        const ultraShortDayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        
        // Sample events data (in a real app, this would come from the database)
        const events = [
            { date: '2023-11-15', title: 'Site Inspection', time: '9:00 AM', color: 'bg-success' },
            { date: '2023-11-17', title: 'Material Delivery', time: '2:00 PM', color: 'bg-warning' },
            { date: '2023-11-20', title: 'Team Meeting', time: '11:00 AM', color: 'bg-info' },
            { date: '2023-11-22', title: 'Client Visit', time: '10:30 AM', color: 'bg-primary' },
            { date: '2023-11-25', title: 'Deadline', time: 'All Day', color: 'bg-danger' },
            { date: '2023-11-28', title: 'Progress Review', time: '2:00 PM', color: 'bg-info' },
            { date: '2023-12-05', title: 'Project Launch', time: '10:00 AM', color: 'bg-primary' },
            { date: '2023-12-12', title: 'Budget Review', time: '3:00 PM', color: 'bg-warning' },
            { date: '2023-12-15', title: 'Quality Check', time: '1:00 PM', color: 'bg-success' },
            { date: '2023-10-28', title: 'Team Workshop', time: '9:30 AM', color: 'bg-info' },
            { date: '2023-10-30', title: 'Vendor Meeting', time: '11:45 AM', color: 'bg-warning' }
        ];
        
        // Check screen size and update UI accordingly
        function checkScreenSize() {
            const width = window.innerWidth;
            isMobile = width < 768;
            isVerySmall = width < 576;
            isUltraSmall = width < 375;
            
            // Update month display
            updateCalendarTitle();
            
            // Update day headers
            updateDayHeaders();
            
            // Regenerate calendar
            generateCalendar();
        }
        
        // Update day headers based on screen size
        function updateDayHeaders() {
            const headerRow = document.querySelector('.calendar-table thead tr');
            if (!headerRow) return;
            
            headerRow.innerHTML = '';
            
            const names = isUltraSmall ? ultraShortDayNames : (isVerySmall ? shortDayNames : dayNames);
            
            names.forEach(name => {
                const th = document.createElement('th');
                th.textContent = name;
                headerRow.appendChild(th);
            });
        }
        
        // Update the calendar title
        function updateCalendarTitle() {
            const monthName = isMobile ? shortMonthNames[currentMonth] : monthNames[currentMonth];
            currentMonthDisplay.textContent = `${monthName} ${currentYear}`;
        }
        
        // Get the events for a specific date
        function getEventsForDate(date) {
            const dateStr = date.toISOString().split('T')[0];
            return events.filter(event => event.date === dateStr);
        }
        
        // Generate calendar
        function generateCalendar() {
            // Clear the existing calendar
            calendarBody.innerHTML = '';
            
            // Get the first day of the month
            const firstDay = new Date(currentYear, currentMonth, 1);
            // Get the last day of the month
            const lastDay = new Date(currentYear, currentMonth + 1, 0);
            
            // Get the day of the week for the first day of the month (0-6)
            const firstDayIndex = firstDay.getDay();
            
            // Get the total number of days in the month
            const totalDays = lastDay.getDate();
            
            // Get the last day of the previous month
            const prevMonthLastDay = new Date(currentYear, currentMonth, 0).getDate();
            
            // Calculate the number of days to show from previous month
            const prevMonthDays = firstDayIndex;
            
            // Calculate the number of days to show from next month
            const nextMonthDays = (6 - lastDay.getDay()) % 7;
            
            // Total cells needed (prev month + current month + next month)
            const totalCells = prevMonthDays + totalDays + nextMonthDays;
            
            // Counter for all cells
            let cellCounter = 1;
            
            // Create rows (weeks)
            for (let i = 0; i < Math.ceil(totalCells / 7); i++) {
                const row = document.createElement('tr');
                
                // Create cells (days)
                for (let j = 0; j < 7; j++) {
                    const cell = document.createElement('td');
                    cell.className = 'calendar-day';
                    
                    // Determine if the cell belongs to the previous, current or next month
                    if (cellCounter <= prevMonthDays) {
                        // Previous month
                        const prevMonthDate = prevMonthLastDay - prevMonthDays + cellCounter;
                        cell.textContent = prevMonthDate;
                        cell.classList.add('prev-month');
                        
                        // Create date object for the previous month day
                        const date = new Date(currentYear, currentMonth - 1, prevMonthDate);
                        
                        // Check for events on this day
                        const dateEvents = getEventsForDate(date);
                        if (dateEvents.length > 0) {
                            // Add events to the cell
                            dateEvents.forEach(event => {
                                const eventEl = createEventElement(event);
                                cell.appendChild(eventEl);
                            });
                        }
                    } else if (cellCounter <= prevMonthDays + totalDays) {
                        // Current month
                        const date = cellCounter - prevMonthDays;
                        cell.textContent = date;
                        
                        // Check if this is today
                        const currentDate = new Date(currentYear, currentMonth, date);
                        if (
                            currentYear === today.getFullYear() && 
                            currentMonth === today.getMonth() && 
                            date === today.getDate()
                        ) {
                            cell.classList.add('today');
                        }
                        
                        // Check for events on this day
                        const dateEvents = getEventsForDate(currentDate);
                        if (dateEvents.length > 0) {
                            // Add events to the cell
                            dateEvents.forEach(event => {
                                const eventEl = createEventElement(event);
                                cell.appendChild(eventEl);
                            });
                            
                            // Add event count badge for small screens if there are multiple events
                            if (isVerySmall && dateEvents.length > 1) {
                                const badge = document.createElement('span');
                                badge.className = 'badge badge-pill badge-primary event-count';
                                badge.textContent = dateEvents.length;
                                cell.appendChild(badge);
                            }
                        }
                    } else {
                        // Next month
                        const nextMonthDate = cellCounter - (prevMonthDays + totalDays);
                        cell.textContent = nextMonthDate;
                        cell.classList.add('next-month');
                        
                        // Create date object for the next month day
                        const date = new Date(currentYear, currentMonth + 1, nextMonthDate);
                        
                        // Check for events on this day
                        const dateEvents = getEventsForDate(date);
                        if (dateEvents.length > 0) {
                            // Add events to the cell
                            dateEvents.forEach(event => {
                                const eventEl = createEventElement(event);
                                cell.appendChild(eventEl);
                            });
                        }
                    }
                    
                    row.appendChild(cell);
                    cellCounter++;
                }
                
                calendarBody.appendChild(row);
            }
            
            // Make calendar events clickable after regenerating the calendar
            attachEventListeners();
        }
        
        // Create an event element
        function createEventElement(event) {
            const eventEl = document.createElement('div');
            eventEl.className = `calendar-event ${event.color}`;
            
            // Simplified display for ultra small screens
            if (isUltraSmall) {
                eventEl.innerHTML = `<div>${event.title}</div>`;
            } else {
                eventEl.innerHTML = `
                    <small>${event.time}</small>
                    <div>${event.title}</div>
                `;
            }
            return eventEl;
        }
        
        // Attach event listeners to calendar events
        function attachEventListeners() {
            const calendarEvents = document.querySelectorAll('.calendar-event');
            calendarEvents.forEach(event => {
                event.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent the click from bubbling up
                    const eventTitle = this.querySelector('div').textContent;
                    const eventTime = this.querySelector('small') ? this.querySelector('small').textContent : 'N/A';
                    
                    // In a real implementation, we would show event details here
                    alert(`Event: ${eventTitle}\nTime: ${eventTime}\n\nEvent details will be implemented in the next phase.`);
                });
            });
            
            // Add click events to days for mobile
            if (isMobile) {
                const calendarDays = document.querySelectorAll('.calendar-day');
                calendarDays.forEach(day => {
                    day.addEventListener('click', function() {
                        const date = this.textContent.trim();
                        const events = this.querySelectorAll('.calendar-event');
                        
                        if (events.length > 0) {
                            // On mobile, clicking a day with events shows a summary
                            let message = `Events on ${date}/${currentMonth + 1}/${currentYear}:\n\n`;
                            
                            events.forEach(event => {
                                const title = event.querySelector('div').textContent;
                                const time = event.querySelector('small') ? event.querySelector('small').textContent : 'N/A';
                                message += `- ${title} (${time})\n`;
                            });
                            
                            alert(message);
                        }
                    });
                });
            }
            
            // Add event listeners to the '+' icon on each calendar day
            const calendarDays = document.querySelectorAll('.calendar-day:not(.prev-month):not(.next-month)');
            calendarDays.forEach(day => {
                // Create a pseudo-element for the '+' button manually
                day.addEventListener('click', function(e) {
                    // Check if the click was on the "+" icon area (bottom right)
                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    // If click is in the bottom right corner (where the '+' icon is)
                    if (x > rect.width - 25 && y > rect.height - 25) {
                        e.stopPropagation(); // Prevent other click handlers
                        
                        // Get the day number
                        const dayNum = parseInt(this.textContent.trim());
                        // Format the date
                        const dateStr = `${currentYear}-${(currentMonth + 1).toString().padStart(2, '0')}-${dayNum.toString().padStart(2, '0')}`;
                        
                        // Show add event dialog (for now just an alert)
                        showAddEventDialog(dateStr);
                    }
                });
            });
        }
        
        // Function to show the add event dialog
        function showAddEventDialog(dateStr) {
            // Get the modal elements
            const modal = new bootstrap.Modal(document.getElementById('addEventModal'));
            const eventDateInput = document.getElementById('eventDate');
            
            // Set the date in the input
            eventDateInput.value = dateStr;
            
            // Show the modal
            modal.show();
            
            // Handle save button click
            const saveBtn = document.getElementById('saveEventBtn');
            if (!saveBtn) {
                console.error('Save button not found');
                return;
            }
            
            saveBtn.onclick = function() {
                const form = document.getElementById('addEventForm');
                if (!form) {
                    console.error('Form element not found');
                    return;
                }
                
                // Check form validity
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return;
                }
                
                // Show loading state
                const originalBtnContent = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveBtn.disabled = true;
                
                // Get form data using FormData (handles file uploads)
                const formData = new FormData(form);
                
                // Send form data to server using AJAX
                fetch('process_site_event.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (error) {
                            console.error('Error parsing JSON:', error);
                            console.log('Raw response:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Show success message
                        alert(data.message);
                        
                        // Close the modal
                        modal.hide();
                        
                        // Reset form
                        form.reset();
                        form.classList.remove('was-validated');
                        
                        // Reload the page to show the updated data
                        // Alternatively, you could update the UI without a refresh
                        window.location.reload();
                    } else {
                        // Show error message
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error submitting form:', error);
                    alert('An error occurred while saving the event. Please try again.');
                })
                .finally(() => {
                    // Restore button state
                    saveBtn.innerHTML = originalBtnContent;
                    saveBtn.disabled = false;
                });
            };
        }
        
        // Initialize the calendar
        updateCalendarTitle();
        updateDayHeaders();
        generateCalendar();
        
        // Event listeners for previous and next month buttons
        prevMonthBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            updateCalendarTitle();
            generateCalendar();
        });
        
        nextMonthBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            updateCalendarTitle();
            generateCalendar();
        });
        
        // Switch between different views (month, week, day)
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                viewButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Get the selected view
                const view = this.getAttribute('data-view');
                currentView = view;
                
                // In a real implementation, we would switch the calendar view here
                console.log(`Switching to ${view} view`);
                
                // For now, just show an alert for demonstration
                if (view !== 'month') {
                    alert(`${view.charAt(0).toUpperCase() + view.slice(1)} view will be implemented in the next phase.`);
                    
                    // Go back to month view
                    viewButtons.forEach(btn => {
                        if (btn.getAttribute('data-view') === 'month') {
                            btn.classList.add('active');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                    currentView = 'month';
                }
            });
        });
        
        // Check screen size on load
        checkScreenSize();
        
        // Listen for window resize and update UI accordingly
        window.addEventListener('resize', function() {
            checkScreenSize();
        });
    });
</script>

<!-- Prevent accidental page close when punched in -->
<script>
</script>

<script>
// Function to calculate total wages based on attendance and wages per day
function calculateTotalWages(element) {
    const laborForm = element.closest('.labor-form');
    const morningAttendance = laborForm.querySelector('.morning-attendance').value;
    const eveningAttendance = laborForm.querySelector('.evening-attendance').value;
    const wagesPerDay = parseFloat(laborForm.querySelector('.wages-per-day').value) || 0;
    
    let multiplier = 0;
    if (morningAttendance === 'P' && eveningAttendance === 'P') multiplier = 1;
    else if (morningAttendance === 'P' || eveningAttendance === 'P') multiplier = 0.5;
    
    const totalDayWages = wagesPerDay * multiplier;
    laborForm.querySelector('.total-day-wages').value = totalDayWages.toFixed(2);
    
    updateGrandTotal(laborForm);
}

// Function to calculate overtime amount
function calculateOTAmount(element) {
    const laborForm = element.closest('.labor-form');
    const hours = parseInt(laborForm.querySelector('.ot-hours').value) || 0;
    const minutes = parseInt(laborForm.querySelector('.ot-minutes').value) || 0;
    const rate = parseFloat(laborForm.querySelector('.ot-rate').value) || 0;
    
    const totalHours = hours + (minutes / 60);
    const otAmount = totalHours * rate;
    
    laborForm.querySelector('.total-ot-amount').value = otAmount.toFixed(2);
    
    updateGrandTotal(laborForm);
}

// Function to update grand total
function updateGrandTotal(laborForm) {
    const totalDayWages = parseFloat(laborForm.querySelector('.total-day-wages').value) || 0;
    const totalOTAmount = parseFloat(laborForm.querySelector('.total-ot-amount').value) || 0;
    const travelAmount = parseFloat(laborForm.querySelector('.travel-amount').value) || 0;
    
    const grandTotal = totalDayWages + totalOTAmount + travelAmount;
    laborForm.querySelector('.grand-total').value = grandTotal.toFixed(2);
}
</script>

<script>
// ... existing script code ...

// Add event listeners for travel amount updates
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('change', function(e) {
        // For vendor labor travel amount
        if (e.target && e.target.classList.contains('travel-amount') && e.target.name.startsWith('labor_')) {
            const laborForm = e.target.closest('.labor-form');
            if (laborForm) {
                updateGrandTotal(laborForm);
            }
        }
        
        // For company labour travel amount
        if (e.target && e.target.classList.contains('travel-amount') && e.target.name.startsWith('company_')) {
            const labourForm = e.target.closest('.company-labour-form');
            if (labourForm) {
                updateCompanyLabourGrandTotal(labourForm);
            }
        }
    });
});
</script>
<script>
// Function to update remaining unit based on selected unit
function updateRemainingUnit(selectElement, unitSpan) {
    if (!selectElement || !unitSpan) return;
    
    const selectedUnit = selectElement.value;
    let unitDisplay = '-';
    
    if (selectedUnit) {
        // Get the selected option's text
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        if (selectedOption) {
            // Extract the unit abbreviation from the option text
            const optionText = selectedOption.text;
            const matches = optionText.match(/\(([^)]+)\)/);
            
            if (matches && matches[1]) {
                // Use the unit abbreviation
                unitDisplay = matches[1];
            } else {
                // Use the value if no abbreviation
                unitDisplay = selectedUnit;
            }
        }
    }
    
    unitSpan.textContent = unitDisplay;
}

// Function to show/hide remaining quantity based on inventory type
function updateRemainingQuantityVisibility(typeSelect, remainingField) {
    if (!typeSelect || !remainingField) return;
    
    const selectedType = typeSelect.value;
    const remainingInput = remainingField.querySelector('input[name="remaining_quantity[]"]');
    
    // Show remaining field for available and received items, hide for consumed items
    if (selectedType === 'consumed') {
        remainingField.style.display = 'none';
        
        // Set remaining quantity to 0 for consumed items
        if (remainingInput) {
            remainingInput.value = '0';
            // Trigger the input event to update any indicators
            remainingInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        
        // Add a note about consumed items
        let noteElement = remainingField.nextElementSibling;
        if (!noteElement || !noteElement.classList.contains('consumed-note')) {
            noteElement = document.createElement('div');
            noteElement.className = 'col-12 consumed-note mt-2';
            noteElement.innerHTML = `
                <div class="alert alert-info py-2 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <small>For consumed items, the remaining quantity is automatically set to zero.</small>
                </div>
            `;
            remainingField.parentNode.insertBefore(noteElement, remainingField.nextElementSibling);
        }
    } else {
        remainingField.style.display = '';
        
        // Remove the consumed note if it exists
        const noteElement = remainingField.nextElementSibling;
        if (noteElement && noteElement.classList.contains('consumed-note')) {
            noteElement.remove();
        }
    }
}

// Function to update the remaining quantity indicator
function updateRemainingQuantityIndicator(input) {
    if (!input) return;
    
    const quantity = parseFloat(input.closest('.row').querySelector('input[name="quantity[]"]').value) || 0;
    const remaining = parseFloat(input.value) || 0;
    const unitSpan = input.nextElementSibling;
    
    if (quantity > 0) {
        const percentage = (remaining / quantity) * 100;
        
        // Remove any existing classes
        unitSpan.classList.remove('bg-danger', 'bg-warning', 'bg-success', 'text-white');
        
        // Add appropriate class based on percentage
        if (percentage <= 20) {
            unitSpan.classList.add('bg-danger', 'text-white');
        } else if (percentage <= 50) {
            unitSpan.classList.add('bg-warning');
        } else {
            unitSpan.classList.add('bg-success', 'text-white');
        }
    }
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ... existing code ...
    
    // Add a Wages Summary section after the Inventory section
    const addEventForm = document.getElementById('addEventForm');
    if (addEventForm) {
        const inventorySection = addEventForm.querySelector('.inventory-section');
        if (inventorySection) {
            // Create a container for the wages summary
            const wagesSummaryContainer = document.createElement('div');
            wagesSummaryContainer.className = 'wages-summary-container';
            
            // Insert after the inventory section
            inventorySection.parentNode.insertBefore(wagesSummaryContainer, inventorySection.nextSibling);
            
            // Add the wages summary section
            addWagesSummarySection(wagesSummaryContainer);
            
            // Initial calculation
            calculateWagesSummary();
            
            // Set up event listeners for form changes
            setupSummaryEventListeners();
        }
    }
});

// Function to add the Wages Summary section
function addWagesSummarySection(container) {
    const template = document.getElementById('wagesSummaryTemplate');
    if (template && container) {
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
        
        // Add event listener for refresh button
        const refreshBtn = container.querySelector('.refresh-summary');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                calculateWagesSummary();
            });
        }
    }
}

// Function to set up event listeners for automatic summary updates
function setupSummaryEventListeners() {
    const form = document.getElementById('addEventForm');
    if (!form) return;
    
    // Listen for input events on all wage-related fields
    form.addEventListener('input', function(e) {
        const target = e.target;
        // Check if the changed input is related to wages or expenses
        if (
            target.classList.contains('wages-per-day') ||
            target.classList.contains('ot-rate') ||
            target.classList.contains('ot-hours') ||
            target.classList.contains('ot-minutes') ||
            target.classList.contains('travel-amount') ||
            target.name && target.name.includes('beverage_amount') ||
            target.name && target.name.includes('travel_amount')
        ) {
            // Delay calculation slightly to allow all related calculations to complete
            setTimeout(calculateWagesSummary, 100);
        }
    });
    
    // Listen for changes in attendance that affect wages
    form.addEventListener('change', function(e) {
        const target = e.target;
        if (
            target.classList.contains('morning-attendance') ||
            target.classList.contains('evening-attendance')
        ) {
            setTimeout(calculateWagesSummary, 100);
        }
    });
    
    // Listen for additions and removals of forms
    const observerConfig = { childList: true, subtree: true };
    const observer = new MutationObserver(function(mutations) {
        let shouldRecalculate = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Check if added or removed nodes might affect the calculation
                const nodeClasses = [
                    'labor-form', 
                    'company-labour-form', 
                    'travel-expense-form', 
                    'beverage-form'
                ];
                
                if (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0) {
                    for (let i = 0; i < mutation.addedNodes.length; i++) {
                        const node = mutation.addedNodes[i];
                        if (node.classList) {
                            for (const cls of nodeClasses) {
                                if (node.classList.contains(cls)) {
                                    shouldRecalculate = true;
                                    break;
                                }
                            }
                        }
                    }
                    
                    for (let i = 0; i < mutation.removedNodes.length; i++) {
                        const node = mutation.removedNodes[i];
                        if (node.classList) {
                            for (const cls of nodeClasses) {
                                if (node.classList.contains(cls)) {
                                    shouldRecalculate = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        });
        
        if (shouldRecalculate) {
            setTimeout(calculateWagesSummary, 100);
        }
    });
    
    observer.observe(form, observerConfig);
}

// Function to calculate and update the wages summary
function calculateWagesSummary() {
    // Vendor Labour Wages
    const vendorLabourWages = calculateVendorLabourWages();
    
    // Company Labour Wages
    const companyLabourWages = calculateCompanyLabourWages();
    
    // Travel Expenses
    const travelExpenses = calculateTravelExpenses();
    
    // Beverage Expenses
    const beverageExpenses = calculateBeverageExpenses();
    
    // Vendor Labour Travel
    const vendorLabourTravel = calculateVendorLabourTravel();
    
    // Company Labour Travel
    const companyLabourTravel = calculateCompanyLabourTravel();
    
    // Total Labour Wages
    const totalLabourWages = vendorLabourWages + companyLabourWages;
    
    // Total Miscellaneous Expenses
    const totalMiscExpenses = travelExpenses + beverageExpenses + vendorLabourTravel + companyLabourTravel;
    
    // Grand Total
    const grandTotal = totalLabourWages + totalMiscExpenses;
    
    // Update the UI
    updateWagesSummaryUI({
        vendorLabourWages,
        companyLabourWages,
        totalLabourWages,
        travelExpenses,
        beverageExpenses,
        vendorLabourTravel,
        companyLabourTravel,
        totalMiscExpenses,
        grandTotal
    });
}

// Function to calculate vendor labour wages
function calculateVendorLabourWages() {
    let total = 0;
    
    // Get all vendor labor forms
    const laborForms = document.querySelectorAll('.labor-form');
    laborForms.forEach(form => {
        // Get the grand total
        const grandTotal = form.querySelector('.grand-total');
        if (grandTotal && grandTotal.value) {
            total += parseFloat(grandTotal.value) || 0;
        } else {
            // If no grand total, calculate from parts
            const totalDayWages = parseFloat(form.querySelector('.total-day-wages')?.value) || 0;
            const totalOTAmount = parseFloat(form.querySelector('.total-ot-amount')?.value) || 0;
            
            total += totalDayWages + totalOTAmount;
        }
    });
    
    return total;
}

// Function to calculate company labour wages
function calculateCompanyLabourWages() {
    let total = 0;
    
    // Get all company labour forms
    const labourForms = document.querySelectorAll('.company-labour-form');
    labourForms.forEach(form => {
        // Get the grand total
        const grandTotal = form.querySelector('.grand-total');
        if (grandTotal && grandTotal.value) {
            total += parseFloat(grandTotal.value) || 0;
        } else {
            // If no grand total, calculate from parts
            const totalDayWages = parseFloat(form.querySelector('.total-day-wages')?.value) || 0;
            const totalOTAmount = parseFloat(form.querySelector('.total-ot-amount')?.value) || 0;
            
            total += totalDayWages + totalOTAmount;
        }
    });
    
    return total;
}

// Function to calculate travel expenses
function calculateTravelExpenses() {
    let total = 0;
    
    // Get all travel expense forms
    const travelForms = document.querySelectorAll('.travel-expense-form');
    travelForms.forEach(form => {
        const amount = parseFloat(form.querySelector('.total-amount')?.value) || 0;
        total += amount;
    });
    
    return total;
}

// Function to calculate beverage expenses
function calculateBeverageExpenses() {
    let total = 0;
    
    // Get all beverage forms
    const beverageForms = document.querySelectorAll('.beverage-form');
    beverageForms.forEach(form => {
        const amount = parseFloat(form.querySelector('input[name="beverage_amount[]"]')?.value) || 0;
        total += amount;
    });
    
    return total;
}

// Function to calculate vendor labour travel expenses
function calculateVendorLabourTravel() {
    let total = 0;
    
    // Get all vendor labor forms
    const laborForms = document.querySelectorAll('.labor-form');
    laborForms.forEach(form => {
        const travelAmount = parseFloat(form.querySelector('.travel-amount')?.value) || 0;
        total += travelAmount;
    });
    
    return total;
}

// Function to calculate company labour travel expenses
function calculateCompanyLabourTravel() {
    let total = 0;
    
    // Get all company labour forms
    const labourForms = document.querySelectorAll('.company-labour-form');
    labourForms.forEach(form => {
        const travelAmount = parseFloat(form.querySelector('.travel-amount')?.value) || 0;
        total += travelAmount;
    });
    
    return total;
}

// Function to update the UI with calculated values
function updateWagesSummaryUI(data) {
    const container = document.querySelector('.wages-summary-section');
    if (!container) return;
    
    // Format each value as Indian Rupees
    const formatCurrency = value => {
        return '₹' + value.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    };
    
    // Update labor wages
    container.querySelector('.vendor-labour-wages').textContent = formatCurrency(data.vendorLabourWages);
    container.querySelector('.company-labour-wages').textContent = formatCurrency(data.companyLabourWages);
    container.querySelector('.total-labour-wages').textContent = formatCurrency(data.totalLabourWages);
    
    // Update miscellaneous expenses
    container.querySelector('.travel-expenses').textContent = formatCurrency(data.travelExpenses);
    container.querySelector('.beverage-expenses').textContent = formatCurrency(data.beverageExpenses);
    container.querySelector('.vendor-travel-expenses').textContent = formatCurrency(data.vendorLabourTravel);
    container.querySelector('.company-travel-expenses').textContent = formatCurrency(data.companyLabourTravel);
    container.querySelector('.total-misc-expenses').textContent = formatCurrency(data.totalMiscExpenses);
    
    // Update grand total
    container.querySelector('.grand-total-amount').textContent = formatCurrency(data.grandTotal);
    
    // Add visual indicators for high expense categories
    const amountElements = container.querySelectorAll('.wage-amount:not(.total-labour-wages):not(.total-misc-expenses)');
    amountElements.forEach(element => {
        const amount = parseFloat(element.textContent.replace(/[₹,]/g, '')) || 0;
        
        // Remove any existing indicators
        element.classList.remove('text-danger', 'text-warning', 'text-success');
        
        // Add indicator based on amount
        if (amount > 10000) {
            element.classList.add('text-danger');
        } else if (amount > 5000) {
            element.classList.add('text-warning');
        } else if (amount > 0) {
            element.classList.add('text-success');
        }
    });
}
</script>
</script>

<!-- Ensure modal can be closed properly -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure all modal close buttons work properly
        const addEventModal = document.getElementById('addEventModal');
        if (addEventModal) {
            // Simple solution: just use jQuery to initialize the modal properly
            const closeButtons = addEventModal.querySelectorAll('[data-bs-dismiss="modal"]');
            
            // Add click event listeners to each button
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Simply hide the modal and backdrop using DOM manipulation
                    addEventModal.classList.remove('show');
                    addEventModal.style.display = 'none';
                    
                    // Remove backdrop
                    const modalBackdrop = document.querySelector('.modal-backdrop');
                    if (modalBackdrop) modalBackdrop.remove();
                    
                    // Reset body
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                });
            });
        }
    });
</script>
</body>
</html>

