<?php
session_start();

// Check if admin is logged in and has admin role, if not redirect to login page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config/db_connect.php';  // Make sure this path matches your database connection file

// Get today's date in Y-m-d format
$today = date('Y-m-d');

// Initialize variables
$total_users = 0;
$present_count = 0;
$absent_count = 0;
$leaves_count = 0;

// Query to count total users
$sql = "SELECT COUNT(*) as total_users FROM users";
$result = mysqli_query($conn, $sql);

if ($result) {
    $data = mysqli_fetch_assoc($result);
    $total_users = $data['total_users'];
}

// Query for today's attendance statistics
$attendance_sql = "
    SELECT 
        COUNT(DISTINCT user_id) as present_users
    FROM attendance 
    WHERE date = '$today' 
    AND punch_in IS NOT NULL";

$attendance_result = mysqli_query($conn, $attendance_sql);

if ($attendance_result) {
    $attendance_data = mysqli_fetch_assoc($attendance_result);
    $present_count = $attendance_data['present_users'];
}

// Query for users on leave today
$leaves_sql = "
    SELECT COUNT(DISTINCT user_id) as users_on_leave
    FROM leaves 
    WHERE '$today' BETWEEN start_date AND end_date 
    AND status = 'approved'";

$leaves_result = mysqli_query($conn, $leaves_sql);

if ($leaves_result) {
    $leaves_data = mysqli_fetch_assoc($leaves_result);
    $leaves_count = $leaves_data['users_on_leave'];
}

// Calculate absent users
$absent_count = $total_users - $present_count - $leaves_count;

// Ensure absent_count doesn't go below 0
$absent_count = max(0, $absent_count);

// Query for project statistics
$projects_sql = "
    SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN project_type = 'Architecture' THEN 1 ELSE 0 END) as architecture_count,
        SUM(CASE WHEN project_type = 'Construction' THEN 1 ELSE 0 END) as construction_count,
        SUM(CASE WHEN project_type = 'Interior' THEN 1 ELSE 0 END) as interior_count
    FROM projects";

$projects_result = mysqli_query($conn, $projects_sql);
$architecture_count = 0;
$construction_count = 0;
$interior_count = 0;
$total_projects = 0;

if ($projects_result) {
    $projects_data = mysqli_fetch_assoc($projects_result);
    $total_projects = $projects_data['total_projects'];
    $architecture_count = $projects_data['architecture_count'];
    $construction_count = $projects_data['construction_count'];
    $interior_count = $projects_data['interior_count'];
}

// After the projects query, add this new query for tasks
$tasks_sql = "
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN project_type = 'Architecture' THEN 1 ELSE 0 END) as architecture_tasks,
        SUM(CASE WHEN project_type = 'Construction' THEN 1 ELSE 0 END) as construction_tasks,
        SUM(CASE WHEN project_type = 'Interior' THEN 1 ELSE 0 END) as interior_tasks
    FROM tasks";

$tasks_result = mysqli_query($conn, $tasks_sql);
$architecture_tasks = 0;
$construction_tasks = 0;
$interior_tasks = 0;
$total_tasks = 0;

if ($tasks_result) {
    $tasks_data = mysqli_fetch_assoc($tasks_result);
    $total_tasks = $tasks_data['total_tasks'];
    $architecture_tasks = $tasks_data['architecture_tasks'];
    $construction_tasks = $tasks_data['construction_tasks'];
    $interior_tasks = $tasks_data['interior_tasks'];
}

// Add this query before mysqli_close($conn)
$pending_leaves_sql = "
    SELECT 
        COUNT(*) as total_pending,
        SUM(CASE WHEN manager_status = 'pending' THEN 1 ELSE 0 END) as manager_pending,
        SUM(CASE WHEN manager_status = 'approved' AND hr_status = 'pending' THEN 1 ELSE 0 END) as hr_pending
    FROM leaves 
    WHERE (manager_status = 'pending') 
    OR (manager_status = 'approved' AND hr_status = 'pending')";

$pending_leaves_result = mysqli_query($conn, $pending_leaves_sql);
$manager_pending = 0;
$hr_pending = 0;
$total_pending = 0;

if ($pending_leaves_result) {
    $pending_data = mysqli_fetch_assoc($pending_leaves_result);
    $total_pending = $pending_data['total_pending'];
    $manager_pending = $pending_data['manager_pending'];
    $hr_pending = $pending_data['hr_pending'];
}

// Query for projects in pipeline
$pipeline_sql = "
    SELECT 
        id,
        project_name,
        client_name,
        father_husband_name,
        mobile,
        email,
        location,
        project_type,
        total_cost,
        assigned_to,
        created_at,
        status,
        archived_date
    FROM projects 
    WHERE status IN ('ongoing', 'active')
    ORDER BY created_at DESC
    LIMIT 10";

$pipeline_result = mysqli_query($conn, $pipeline_sql);
$pipeline_projects = [];

if ($pipeline_result) {
    while ($row = mysqli_fetch_assoc($pipeline_result)) {
        $pipeline_projects[] = $row;
    }
}

// Close connection after all queries
mysqli_close($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
               :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --primary-color: #000000;
            --secondary-color: #ff3333;
            --text-color: #ffffff;
            --text-muted: #999999;
            --hover-color: #1a1a1a;
            --border-color: #333333;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--primary-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.2) transparent;
        }

        .sidebar-content::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background-color: rgba(255,255,255,0.2);
            border-radius: 3px;
        }

        .sidebar-content::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255,255,255,0.3);
        }

        .sidebar.collapsed .sidebar-content {
            padding: 20px 0;
        }

        .sidebar-title {
            font-size: 24px;
            font-weight: 600;
            padding: 15px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-link {
            color: var(--text-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin: 8px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar-link:hover {
            background: var(--hover-color);
            color: var(--text-color);
            transform: translateX(5px);
        }

        .sidebar-link.active {
            background: var(--secondary-color);
            color: var(--text-color);
            border-left: 4px solid #fff;
        }

        .sidebar-link i {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 18px;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar.collapsed .sidebar-link {
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-link i {
            margin-right: 0;
            font-size: 20px;
        }

        .toggle-btn {
            position: absolute;
            right: -15px;
            top: 20px;
            background: #fff;
            color: var(--primary-color);
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 3px 15px rgba(0,0,0,0.2);
        }

        /* Navigation group styling */
        .nav-group {
            margin-bottom: 20px;
        }

        .nav-group-title {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 20px 15px 10px;
            letter-spacing: 1px;
        }

        /* Profile section in sidebar */
        .sidebar-profile {
            display: flex;
            align-items: center;
            padding: 20px 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .profile-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
            border: 2px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-weight: 600;
            margin: 0;
            font-size: 16px;
        }

        .profile-role {
            font-size: 14px;
            color: rgba(255,255,255,0.7);
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s ease;
        }
        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .stat-card {
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }

        .sidebar.collapsed .sidebar-title,
        .sidebar.collapsed .nav-group-title,
        .sidebar.collapsed .profile-info,
        .sidebar.collapsed .sidebar-link span {
            display: none;
        }

        .sidebar.collapsed .sidebar-profile {
            justify-content: center;
            padding: 10px 0;
        }

        .sidebar.collapsed .profile-image {
            width: 35px;
            height: 35px;
            margin-right: 0;
        }

        .sidebar.collapsed .sidebar-link {
            padding: 12px 0;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-link i {
            margin-right: 0;
            font-size: 20px;
        }

        .sidebar.collapsed .nav-group {
            text-align: center;
        }

        /* Tooltip for collapsed sidebar */
        .sidebar.collapsed .sidebar-link {
            position: relative;
        }

        .sidebar.collapsed .sidebar-link:hover::after {
            content: attr(data-title);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            margin-left: 10px;
            z-index: 1000;
        }

        /* Update submenu styles */
        .sub-menu {
            max-height: 0;
            transition: max-height 0.3s ease-in-out;
            overflow: hidden;
            margin-left: 20px;
            border-left: 1px solid var(--border-color);
            padding-left: 10px;
        }

        .sub-menu.active {
            max-height: 500px; /* Adjust this value based on your content */
        }

        .submenu-arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 12px;
        }

        .has-submenu {
            justify-content: space-between;
        }

        .sidebar-link.has-submenu.active .submenu-arrow {
            transform: rotate(180deg);
        }

        /* Adjust collapsed state styles */
        .sidebar.collapsed .submenu-arrow {
            display: none;
        }

        .sidebar.collapsed .has-submenu {
            justify-content: center;
        }

        .sidebar.collapsed .sub-menu {
            margin-left: 0;
            border-left: none;
            padding-left: 0;
        }

        /* Add transition for smooth size changes */
        .sidebar, .sidebar-profile, .profile-image {
            transition: all 0.3s ease;
        }

        .greeting-section {
            background: linear-gradient(135deg, #ff3333 0%, #cc0000 100%);
            padding: 20px 30px;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin: 20px 20px 15px 20px;  /* Adjusted margins */
        }

        .greeting-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        /* Cloud Animation */
        .clouds {
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .cloud {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 100px;
            animation: float 15s infinite linear;
        }

        .cloud1 {
            width: 100px;
            height: 40px;
            top: 20%;
            right: -20px;
            animation-delay: 0s;
        }

        .cloud2 {
            width: 60px;
            height: 25px;
            top: 40%;
            right: 10%;
            animation-delay: -5s;
        }

        .cloud3 {
            width: 80px;
            height: 30px;
            top: 60%;
            right: 30%;
            animation-delay: -8s;
        }

        .cloud4 {
            width: 70px;
            height: 28px;
            top: 80%;
            right: 50%;
            animation-delay: -12s;
        }

        @keyframes float {
            0% {
                transform: translateX(100%);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateX(-400%);
                opacity: 0;
            }
        }

        /* Make sure greeting content stays above clouds */
        .greeting-content {
            position: relative;
            z-index: 2;
        }

        .greeting-wrapper {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }

        .icon-circle {
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .icon-circle i {
            font-size: 28px;
            color: #ffffff;
        }

        .greeting-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .greeting-info h2 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 500;
            margin: 0;
            line-height: 1.2;
        }

        .datetime-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
        }

        .date-wrapper, .time-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .time-divider {
            color: rgba(255, 255, 255, 0.7);
        }

        .datetime-info i {
            font-size: 14px;
            opacity: 0.9;
        }

        .stat-card {
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        /* Update card layout styles */
        .row.mt-4 {
            margin: 0;
            display: flex;
            justify-content: center;
            gap: 15px;  /* Reduced from 20px */
            padding: 10px;  /* Reduced from 20px */
        }

        .col-md-3 {
            flex: 0 0 auto;
            width: 280px;
        }

        .stat-card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .icon-box {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .icon-box i {
            font-size: 32px; /* Increased icon size */
        }

        /* Card-specific colors */
        /* Blue theme for Total Employees */
        .stat-card:nth-child(1) .icon-box i {
            color: #0052cc;
        }
        .stat-card:nth-child(1) {
            border-left: 4px solid #0052cc;
        }

        /* Green theme for Active Projects */
        .stat-card:nth-child(2) .icon-box i {
            color: #00875a;
        }
        .stat-card:nth-child(2) {
            border-left: 4px solid #00875a;
        }

        /* Cyan theme for Total Tasks */
        .stat-card:nth-child(3) .icon-box i {
            color: red;
        }
        .stat-card:nth-child(3) {
            border-left: 4px solid red;
        }

        /* Orange theme for Pending Leaves */
        .stat-card:nth-child(4) .icon-box i {
            color: #ff8b00;
        }
        .stat-card:nth-child(4) {
            border-left: 4px solid #ff8b00;
        }

        /* Hover effects */
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .stat-card:hover .icon-box i {
            transform: scale(1.1);
        }

        /* Text styles */
        .stat-info h6 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin: 0 0 8px 0;
            text-transform: uppercase;
        }

        .stat-info h2 {
            color: #333;
            font-size: 32px;
            font-weight: 600;
            margin: 0;
        }

        /* Quick View Section Styling */
        .quick-view-section {
            padding: 20px;  /* Reduced from 25px */
            background: #ffffff;
            border-radius: 15px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin: 15px 20px;  /* Reduced top/bottom margin */
            position: relative;
            z-index: 2;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .section-header h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h5 i {
            color: #ff3333;
            font-size: 1.1em;
        }

        .header-line {
            flex: 1;
            height: 2px;
            background: linear-gradient(
                to right,
                rgba(0, 0, 0, 0.1),
                rgba(0, 0, 0, 0.05) 50%,
                transparent
            );
            border-radius: 2px;
        }

        /* Update row spacing */
        .row.mt-4 {
            margin: 0;
            display: flex;
            justify-content: center;
            gap: 15px;  /* Reduced from 20px */
            padding: 10px;  /* Reduced from 20px */
        }

        /* Adjust card styling for better fit */
        .stat-card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin-bottom: 10px; /* Add some bottom spacing */
            position: relative;
            z-index: 2;
        }

        .stat-info small {
            display: block;
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }

        .text-muted {
            color: #6c757d !important;
        }

        /* Tooltip Styles */
        .stat-card {
            position: relative;
        }

        .stat-tooltip {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            width: 250px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .stat-card:hover .stat-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .tooltip-header {
            padding: 12px 15px;
            background: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tooltip-date {
            font-size: 0.85em;
            color: #666;
            font-weight: normal;
        }

        .tooltip-content {
            padding: 15px;
        }

        .tooltip-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .tooltip-item:last-child {
            border-bottom: none;
        }

        .tooltip-item i {
            width: 20px;
            text-align: center;
        }

        .tooltip-item .label {
            flex: 1;
            color: #666;
        }

        .tooltip-item .value {
            font-weight: 600;
            color: #333;
        }

        /* Color classes */
        .text-success {
            color: #28a745;
        }

        .text-danger {
            color: #dc3545;
        }

        .text-warning {
            color: #ffc107;
        }

        /* Add arrow to tooltip */
        .stat-tooltip::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 20px;
            width: 12px;
            height: 12px;
            background: white;
            transform: rotate(45deg);
            border-left: 1px solid rgba(0,0,0,0.1);
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        /* Additional color for project types */
        .text-primary {
            color: #0052cc;
        }

        /* Project-specific tooltip styles */
        .stat-card[data-tooltip="projects"] .tooltip-item i {
            font-size: 1.1em;
        }

        /* Add progress bars to show distribution */
        .tooltip-item .progress-bar {
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }

        .tooltip-item .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* Progress bar colors */
        .tooltip-item:nth-child(1) .progress-fill {
            background: #0052cc;
        }

        .tooltip-item:nth-child(2) .progress-fill {
            background: #ffc107;
        }

        .tooltip-item:nth-child(3) .progress-fill {
            background: #28a745;
        }

        /* Optional: Add hover effect for tooltip items */
        .tooltip-item:hover {
            background: rgba(0,0,0,0.02);
        }

        .tooltip-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid rgba(0,0,0,0.05);
            font-size: 0.85em;
        }

        .tooltip-footer small {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .tooltip-footer i {
            color: #666;
        }

        /* Sales Overview Section Styles */
        .sales-overview-section {
            padding: 15px;  /* Reduced from default */
            background: #ffffff;
            border-radius: 15px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin: 5px 10px;  /* Reduced top/bottom margin */
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;  /* Reduced from 25px */
            padding-bottom: 10px;  /* Reduced from 15px */
        }

        .header-left h5 {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3345;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Date Filter Styles */
        .date-filter {
            display: flex;
            align-items: center;
            gap: 10px;  /* Reduced padding */
            background: #f8f9fa;
            padding: 4px 10px;  /* Reduced padding */
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 6px;  /* Reduced from 8px */
        }

        .date-inputs {
            display: flex;
            align-items: center;
            gap: 10px;  /* Reduced from 15px */
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 6px;  /* Reduced from 8px */
        }

        .form-group label {
            color: #6e6b7b;
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.8rem;
            color: #2c3345;
            width: 130px;
        }

        .filter-btn {
            background: #4a6cf7;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            height: 28px;
        }

        /* Total Sales Card */
        .total-sales-card {
            background: lightblue;
            border-radius: 12px;
            padding: 15px 20px;  /* Reduced padding */
            margin-bottom: 10px;  /* Reduced from 25px */
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            width: auto;
            box-shadow: 0 8px 24px -4px rgba(74, 108, 247, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-size: 1.2rem;
        }

        .total-sales-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 45%, rgba(255,255,255,0.1) 48%, rgba(255,255,255,0.1) 52%, transparent 55%);
            animation: shine 3s infinite;
        }

        /* Sales Cards Grid */
        .sales-cards-grid {
            display: flex;
            justify-content: space-between;
            gap: 10px;  /* Reduced from 20px */
            margin-top: 10px;  /* Reduced from default */
        }

        /* Enhanced Sales Card */
        .sales-card {
            flex: 1;
            background: #ffffff;
            border-radius: 12px;
            padding: 15px;  /* Reduced from 20px */
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.21, 0.6, 0.35, 1);
            position: relative;
            overflow: hidden;
            min-width: 0;
        }

        .sales-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }

        /* Card Header Enhancements */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            position: relative;
        }

        .card-header h6 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2c3345;
            margin: 0;
        }

        /* Enhanced Icons */
        .icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .icon i {
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .sales-card:hover .icon {
            transform: scale(1.1);
        }

        /* Card Type-Specific Styles */
        .sales-card.architecture {
            background: linear-gradient(to bottom, #ffffff, #f8faff);
            border-top: 4px solid #4a6cf7;
        }

        .sales-card.interior {
            background: linear-gradient(to bottom, #ffffff, #f8fff9);
            border-top: 4px solid #28c76f;
        }

        .sales-card.construction {
            background: linear-gradient(to bottom, #ffffff, #fff9f5);
            border-top: 4px solid #ff9f43;
        }

        /* Amount Styling */
        .amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3345;
            margin: 15px 0;
            background: linear-gradient(45deg, #2c3345, #4a4f5d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeIn 0.5s ease-out;
        }

        /* Stats Section */
        .stats {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .comparison {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Enhanced Trend Indicators */
        .trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .trend.up {
            background: rgba(40, 199, 111, 0.12);
            color: #28c76f;
        }

        .trend.down {
            background: rgba(234, 84, 85, 0.12);
            color: #ea5455;
        }

        .trend i {
            font-size: 0.8rem;
        }

        .percentage {
            color: #6e6b7b;
            font-size: 0.85rem;
            font-weight: 500;
            opacity: 0.9;
        }

        /* Animations */
        @keyframes shine {
            0% {
                left: -100%;
            }
            20% {
                left: 100%;
            }
            100% {
                left: 100%;
            }
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

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Loading Animation */
        .loading-spinner {
            text-align: center;
            padding: 30px;
            color: #4a6cf7;
        }

        .loading-spinner i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* Add new container for top section */
        .sales-overview-container {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 10px;  /* Reduced from 15px */
            margin-bottom: 0;
            height: auto;
        }

        /* Main content wrapper */
        .sales-main-content {
            display: flex;
            flex-direction: column;
        }

        /* Side Box Styling */
        .sales-side-box {
            background: linear-gradient(145deg, #ffffff, #f8f9ff);
            border-radius: 12px;
            padding: 15px;  /* Reduced from 20px */
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            height: 53%; /* Adjust to match main content height */
            display: flex;
            flex-direction: column;
        }

        .side-box-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .side-box-header h6 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2c3345;
            margin: 0;
        }

        .side-box-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Prevent content overflow */
        }

        /* Adjust existing styles */
        .total-sales-card {
            margin-bottom: 15px;  /* Reduced from 20px */
        }

        .sales-cards-grid {
            gap: 15px;  /* Reduced from 20px */
            margin-top: 0; /* Remove top margin as it's handled by the container */
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .sales-overview-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .sales-side-box {
                height: auto;
            }
        }

        /* Distribution Stats Styling */
        .distribution-stats {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6e6b7b;
            font-weight: 500;
        }

        .stat-value {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3345;
        }

        .progress-bar {
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }

        /* Pipeline Projects Styling */
        .pipeline-projects {
            max-height: calc(100% - 50px); /* Subtract header height */
            overflow-y: auto;
            padding-right: 8px;
            flex-grow: 1;
            gap: 8px;  /* Reduced from default */
        }

        .pipeline-projects::-webkit-scrollbar {
            width: 4px;
        }

        .pipeline-projects::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }

        .pipeline-projects::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
        }

        .pipeline-projects::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .pipeline-item {
            background: #fff;
            border-radius: 8px;
            padding: 12px;  /* Reduced from 15px */
            margin-bottom: 8px;  /* Reduced from 12px */
            border-left: 4px solid;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }

        .pipeline-item:hover {
            transform: translateX(5px);
        }

        .pipeline-item.high {
            border-left-color: #dc3545;
        }

        .pipeline-item.medium {
            border-left-color: #ffc107;
        }

        .pipeline-item.low {
            border-left-color: #28a745;
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 0.85rem;
            color: #666;
        }

        .project-type i {
            margin-right: 5px;
        }

        .project-name {
            font-weight: 600;
            color: #2c3345;
            margin-bottom: 8px;
        }

        .project-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-badge, .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.planning {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-badge.in-progress {
            background: #fff3e0;
            color: #f57c00;
        }

        .priority-badge {
            font-size: 0.7rem;
        }

        .priority-badge.high {
            background: #ffeaea;
            color: #dc3545;
        }

        .priority-badge.medium {
            background: #fff8e1;
            color: #ffa000;
        }

        .priority-badge.low {
            background: #e8f5e9;
            color: #28a745;
        }

        /* Update and add these styles to your existing CSS */
        .pipeline-item {
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .project-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3345;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .project-id {
            font-size: 0.8rem;
            color: #666;
            font-weight: normal;
        }

        .project-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }

        .detail-row i {
            width: 16px;
            color: #888;
        }

        .assigned-to {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #666;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .assigned-to i {
            font-size: 0.9rem;
            color: #888;
        }

        /* Update the max-height for better scrolling */
        .pipeline-projects {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 8px;
        }

        /* Hover effect enhancement */
        .pipeline-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        /* Status badge updates */
        .status-badge {
            text-transform: capitalize;
        }

        .status-badge.planning {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-badge.in_progress {
            background: #fff3e0;
            color: #f57c00;
        }

        /* Update sales overview section spacing */
        .sales-overview-section {
            padding: 10px;  /* Reduced from 15px */
            margin: 10px;  /* Reduced from 20px */
        }

        /* Adjust container spacing */
        .sales-overview-container {
            gap: 8px;  /* Reduced from 10px */
            margin-bottom: 0;
        }

        /* Update sales cards grid spacing */
        .sales-cards-grid {
            gap: 8px;  /* Reduced from 10px */
            margin-top: 8px;  /* Reduced from 10px */
        }

        /* Adjust total sales card spacing */
        .total-sales-card {
            padding: 12px 15px;  /* Reduced from 15px 20px */
            margin-bottom: 8px;  /* Reduced from 10px */
        }

        .total-sales-card .amount {
            font-size: 1.5rem;  /* Reduced from default */
        }

        /* Update sales card padding */
        .sales-card {
            padding: 12px;  /* Reduced from 15px */
        }

        .sales-card .amount {
            font-size: 1.3rem;  /* Reduced from default */
            margin: 10px 0;  /* Reduced from 15px */
        }

        /* Adjust side box spacing */
        .sales-side-box {
            padding: 12px;  /* Reduced from 15px */
        }

        /* Update section header spacing */
        .section-header {
            margin-bottom: 12px;  /* Reduced from 15px */
            padding-bottom: 8px;  /* Reduced from 10px */
        }

        /* Adjust date filter spacing */
        .date-filter {
            padding: 3px 8px;  /* Reduced from 4px 10px */
        }

        .form-group {
            gap: 4px;  /* Reduced from 6px */
        }

        .form-control {
            padding: 3px 6px;  /* Reduced from 4px 8px */
            width: 120px;  /* Reduced from 130px */
        }

        .filter-btn {
            padding: 4px 10px;  /* Reduced from 6px 12px */
            height: 24px;  /* Reduced from 28px */
        }

        /* Update pipeline projects spacing */
        .pipeline-projects {
            gap: 6px;  /* Reduced from 8px */
        }

        .pipeline-item {
            padding: 10px;  /* Reduced from 12px */
            margin-bottom: 6px;  /* Reduced from 8px */
        }

        .section-header h5 {
            font-size: 1rem;  /* Reduced from 1.25rem */
        }

        .card-header h6 {
            font-size: 0.85rem;  /* Reduced from 0.95rem */
        }

        /* Adjust icon sizes */
        .icon {
            width: 36px;  /* Reduced from 42px */
            height: 36px;  /* Reduced from 42px */
        }

        .icon i {
            font-size: 1rem;  /* Reduced from 1.2rem */
        }

        .project-overview-container {
            margin-top: 15px;
        }

        .project-cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 10px;
        }

        .project-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .project-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .project-card .card-header h6 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2c3345;
            margin: 0;
        }

        .project-card .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.05);
        }

        .project-card .amount {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3345;
            margin: 10px 0;
        }

        .project-card .stats {
            margin-top: 15px;
        }

        .project-status-pills {
            display: flex;
            gap: 10px;
        }

        .status-pill {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-pill i {
            font-size: 0.6rem;
        }

        .status-pill.active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-pill.ongoing {
            background: #fff3e0;
            color: #ef6c00;
        }

        .percentage-bar {
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            margin: 8px 0;
            overflow: hidden;
        }

        .percentage-bar .fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }

        /* Card-specific styles */
        .project-card.total .icon i {
            color: #5c6bc0;
        }

        .project-card.architecture {
            border-left: 4px solid #2196f3;
        }

        .project-card.architecture .icon i {
            color: #2196f3;
        }

        .project-card.architecture .percentage-bar .fill {
            background: #2196f3;
        }

        .project-card.construction {
            border-left: 4px solid #ff9800;
        }

        .project-card.construction .icon i {
            color: #ff9800;
        }

        .project-card.construction .percentage-bar .fill {
            background: #ff9800;
        }

        .project-card.interior {
            border-left: 4px solid #4caf50;
        }

        .project-card.interior .icon i {
            color: #4caf50;
        }

        .project-card.interior .percentage-bar .fill {
            background: #4caf50;
        }

        .percentage {
            font-size: 0.8rem;
            color: #666;
        }

        @media (max-width: 1200px) {
            .project-cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .project-cards-grid {
                grid-template-columns: 1fr;
            }
        }

        .project-overview-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 15px;
        }

        .project-card.total {
            background: #ffffff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .project-card.total .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .project-card.total .header-info h6 {
            font-size: 1.1rem;
            color: #2c3345;
            margin-bottom: 10px;
        }

        .project-card.total .amount {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3345;
        }

        .stats-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .status-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .project-status-pills {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .status-pill {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .status-pill.active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-pill.ongoing {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-pill.completed {
            background: #e3f2fd;
            color: #1976d2;
        }

        .progress-section {
            margin-top: 10px;
        }

        .progress-item {
            width: 100%;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #666;
        }

        .progress-bar {
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, #4CAF50, #8BC34A);
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        .project-types-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 10px;
        }

        .project-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .project-card .header-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .project-card .header-info h6 {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3345;
            margin: 0;
        }

        .project-card .header-info .amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3345;
        }

        .percentage-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }

        .trend {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .trend.up {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .trend.down {
            background: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }

        @media (max-width: 1200px) {
            .project-types-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .project-types-grid {
                grid-template-columns: 1fr;
            }
            
            .project-status-pills {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
     <!-- Sidebar -->
     <div class="sidebar">
        <!-- Fixed Header Section -->
        <div class="sidebar-header">
            <button class="toggle-btn">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <h3 class="sidebar-title">Admin Panel</h3>
            
            <!-- Profile Section -->
            <div class="sidebar-profile">
                <img src="Hive Tag line 11 (1).png" alt="Profile" class="profile-image">
                <div class="profile-info">
                    <h6 class="profile-name">Arya Enterprises</h6>
                    <span class="profile-role">Administrator</span>
                </div>
            </div>
        </div>

        <!-- Scrollable Content Section -->
        <div class="sidebar-content">
            <nav>
                <!-- Main Navigation -->
                <div class="nav-group">
                    <div class="nav-group-title">Main</div>
                    <a href="#" class="sidebar-link has-submenu" data-title="Dashboard">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <!-- Sub Dashboards -->
                    <div class="sub-menu">
                        <a href="#" class="sidebar-link sub-link" data-title="HR Dashboard">
                            <i class="fas fa-users-cog"></i>
                            <span>HR Dashboard</span>
                        </a>
                        <a href="#" class="sidebar-link sub-link" data-title="Studio Manager">
                            <i class="fas fa-video"></i>
                            <span>Studio Manager</span>
                        </a>
                        <a href="#" class="sidebar-link sub-link" data-title="Site Manager">
                            <i class="fas fa-sitemap"></i>
                            <span>Site Manager</span>
                        </a>
                        <a href="#" class="sidebar-link sub-link" data-title="Marketing">
                            <i class="fas fa-bullhorn"></i>
                            <span>Marketing Manager</span>
                        </a>
                        <a href="#" class="sidebar-link sub-link" data-title="Social Media">
                            <i class="fas fa-hashtag"></i>
                            <span>Social Media Manager</span>
                        </a>
                        <a href="#" class="sidebar-link sub-link" data-title="IT Manager">
                            <i class="fas fa-laptop-code"></i>
                            <span>IT Manager</span>
                        </a>
                    </div>
                    
                    <!-- Communications Section -->
                    <a href="#" class="sidebar-link has-submenu" data-title="Communications">
                        <i class="fas fa-comments"></i>
                        <span>Communications</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <div class="sub-menu">
                        <a href="#" class="sidebar-link sub-link" data-title="Circulars">
                            <i class="fas fa-circle-notch"></i>
                            <span>Circulars</span>
                        </a>
                        <a href="#" class="sidebar-link sub-link" data-title="Announcements">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                        <a href="#" class="sidebar-link sub-link" data-title="Events">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                        </a>
                    </div>

                    <!-- Employee IDs & Passwords - Direct Link -->
                    <a href="#" class="sidebar-link" data-title="Employee IDs">
                        <i class="fas fa-id-card"></i>
                        <span>Employee IDs & Passwords</span>
                    </a>

                    <!-- Task Overview - Direct Link -->
                    <a href="#" class="sidebar-link" data-title="Task Overview">
                        <i class="fas fa-tasks"></i>
                        <span>Task Overview</span>
                    </a>

                    <!-- Attendance - Direct Link -->
                    <a href="#" class="sidebar-link" data-title="Attendance">
                        <i class="fas fa-user-clock"></i>
                        <span>Attendance</span>
                    </a>
                </div>

                <!-- Management -->
                <div class="nav-group">
                    <div class="nav-group-title">Management</div>
                    <a href="#" class="sidebar-link" data-title="Users">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="#" class="sidebar-link" data-title="Products">
                        <i class="fas fa-box"></i>
                        <span>Products</span>
                    </a>
                    <a href="#" class="sidebar-link" data-title="Orders">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                </div>

                <!-- System -->
                <div class="nav-group">
                    <div class="nav-group-title">System</div>
                    <a href="#" class="sidebar-link" data-title="Settings">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="logout.php" class="sidebar-link" data-title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>
    </div>

     <!-- Main Content -->
     <div class="main-content">
        <!-- Greeting Section -->
        <div class="greeting-section mb-4">
            <!-- Cloud Elements -->
            <div class="clouds">
                <div class="cloud cloud1"></div>
                <div class="cloud cloud2"></div>
                <div class="cloud cloud3"></div>
                <div class="cloud cloud4"></div>
            </div>
            
            <div class="greeting-content">
                <div class="greeting-wrapper">
                    <div class="icon-circle">
                        <i id="timeIcon" class="fas fa-sun"></i>
                    </div>
                    <div class="greeting-info">
                        <h2 id="greetingText">Good Afternoon</h2>
                        <div class="datetime-info">
                            <span class="date-wrapper">
                                <i class="far fa-calendar-alt"></i>
                                <span id="currentDate">Monday, November 18, 2024</span>
                            </span>
                            <span class="time-divider">|</span>
                            <span class="time-wrapper">
                                <i class="far fa-clock"></i>
                                <span id="currentTime">12:52:54 PM</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick View Section -->
        <div class="quick-view-section">
            <div class="section-header">
                <h5><i class="fas fa-bolt"></i> Quick View</h5>
                <div class="header-line"></div>
            </div>
            
            <div class="row mt-4">
                <!-- Total Employees Card -->
                <div class="col-md-3">
                    <div class="stat-card" data-tooltip="attendance">
                        <div class="stat-info">
                            <h6>Total Employees</h6>
                            <h2><?php echo htmlspecialchars($total_users); ?></h2>
                        </div>
                        <div class="icon-box">
                            <i class="fas fa-users"></i>
                        </div>
                        
                        <!-- Tooltip Content -->
                        <div class="stat-tooltip">
                            <div class="tooltip-header">
                                Today's Attendance
                                <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <?php
                                // Calculate percentages
                                $total = max(1, $total_users); // Prevent division by zero
                                $present_percent = round(($present_count / $total) * 100);
                                $absent_percent = round(($absent_count / $total) * 100);
                                $leaves_percent = round(($leaves_count / $total) * 100);
                                ?>
                                <div class="tooltip-item">
                                    <i class="fas fa-user-check text-success"></i>
                                    <span class="label">Present:</span>
                                    <span class="value"><?php echo $present_count; ?> (<?php echo $present_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $present_percent; ?>%; background: #28a745;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-user-times text-danger"></i>
                                    <span class="label">Absent:</span>
                                    <span class="value"><?php echo $absent_count; ?> (<?php echo $absent_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $absent_percent; ?>%; background: #dc3545;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-user-clock text-warning"></i>
                                    <span class="label">On Leave:</span>
                                    <span class="value"><?php echo $leaves_count; ?> (<?php echo $leaves_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $leaves_percent; ?>%; background: #ffc107;"></div>
                                    </div>
                                </div>
                                
                                <!-- Additional Info Section -->
                                <div class="tooltip-footer">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Total employees: <?php echo $total_users; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Projects Card -->
                <div class="col-md-3">
                    <div class="stat-card" data-tooltip="projects">
                        <div class="stat-info">
                            <h6>Active Projects</h6>
                            <h2><?php echo htmlspecialchars($total_projects); ?></h2>
                        </div>
                        <div class="icon-box">
                            <i class="fas fa-laptop-code"></i>
                        </div>
                        
                        <!-- Tooltip Content -->
                        <div class="stat-tooltip">
                            <div class="tooltip-header">
                                Project Distribution
                                <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <?php
                                // Calculate percentages
                                $total = max(1, $total_projects); // Prevent division by zero
                                $arch_percent = round(($architecture_count / $total) * 100);
                                $const_percent = round(($construction_count / $total) * 100);
                                $int_percent = round(($interior_count / $total) * 100);
                                ?>
                                <div class="tooltip-item">
                                    <i class="fas fa-building text-primary"></i>
                                    <span class="label">Architecture:</span>
                                    <span class="value"><?php echo $architecture_count; ?> (<?php echo $arch_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $arch_percent; ?>%"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-hard-hat text-warning"></i>
                                    <span class="label">Construction:</span>
                                    <span class="value"><?php echo $construction_count; ?> (<?php echo $const_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $const_percent; ?>%"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-couch text-success"></i>
                                    <span class="label">Interior:</span>
                                    <span class="value"><?php echo $interior_count; ?> (<?php echo $int_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $int_percent; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Tasks Card -->
                <div class="col-md-3">
                    <div class="stat-card" data-tooltip="tasks">
                        <div class="stat-info">
                            <h6>Total Tasks</h6>
                            <h2><?php echo htmlspecialchars($total_tasks); ?></h2>
                        </div>
                        <div class="icon-box">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        
                        <!-- Tooltip Content -->
                        <div class="stat-tooltip">
                            <div class="tooltip-header">
                                Task Distribution
                                <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <?php
                                // Calculate percentages
                                $total = max(1, $total_tasks); // Prevent division by zero
                                $arch_percent = round(($architecture_tasks / $total) * 100);
                                $const_percent = round(($construction_tasks / $total) * 100);
                                $int_percent = round(($interior_tasks / $total) * 100);
                                ?>
                                <div class="tooltip-item">
                                    <i class="fas fa-building text-primary"></i>
                                    <span class="label">Architecture:</span>
                                    <span class="value"><?php echo $architecture_tasks; ?> (<?php echo $arch_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $arch_percent; ?>%"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-hard-hat text-warning"></i>
                                    <span class="label">Construction:</span>
                                    <span class="value"><?php echo $construction_tasks; ?> (<?php echo $const_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $const_percent; ?>%"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-couch text-success"></i>
                                    <span class="label">Interior:</span>
                                    <span class="value"><?php echo $interior_tasks; ?> (<?php echo $int_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $int_percent; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Leaves Card -->
                <div class="col-md-3">
                    <div class="stat-card" data-tooltip="leaves">
                        <div class="stat-info">
                            <h6>Pending Leaves</h6>
                            <h2><?php echo htmlspecialchars($total_pending); ?></h2>
                        </div>
                        <div class="icon-box">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        
                        <!-- Tooltip Content -->
                        <div class="stat-tooltip">
                            <div class="tooltip-header">
                                Pending Leave Requests
                                <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <?php
                                // Calculate percentages
                                $total = max(1, $total_pending); // Prevent division by zero
                                $manager_percent = round(($manager_pending / $total) * 100);
                                $hr_percent = round(($hr_pending / $total) * 100);
                                ?>
                                <div class="tooltip-item">
                                    <i class="fas fa-user-tie text-primary"></i>
                                    <span class="label">Manager Approval Pending:</span>
                                    <span class="value"><?php echo $manager_pending; ?> (<?php echo $manager_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $manager_percent; ?>%; background: #0052cc;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-users-cog text-warning"></i>
                                    <span class="label">HR Approval Pending:</span>
                                    <span class="value"><?php echo $hr_pending; ?> (<?php echo $hr_percent; ?>%)</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $hr_percent; ?>%; background: #ffc107;"></div>
                                    </div>
                                </div>
                                
                                <!-- Additional Info Section -->
                                <div class="tooltip-footer">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Total pending requests: <?php echo $total_pending; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Overview Section -->
        <div class="sales-overview-section">
            <div class="section-header">
                <div class="header-left">
                    <h5><i class="fas fa-chart-line"></i> Sales Overview</h5>
                </div>
                <div class="date-filter">
                    <form id="salesFilterForm" class="filter-form">
                        <div class="date-inputs">
                            <div class="form-group">
                                <label>From:</label>
                                <input type="date" id="startDate" name="startDate" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>To:</label>
                                <input type="date" id="endDate" name="endDate" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </form>
                </div>
            </div>
            
            <div id="salesContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>

        <!-- Project Overview Section -->
        <div class="sales-overview-section">
            <div class="section-header">
                <div class="header-left">
                    <h5><i class="fas fa-project-diagram"></i> Project Overview</h5>
                </div>
            </div>
            
            <div class="project-overview-container">
                <!-- Total Projects Card (Full Width) -->
                <div class="project-card total">
                    <div class="card-header">
                        <div class="header-info">
                            <h6>Total Projects</h6>
                            <div class="amount"><?php echo $total_projects; ?></div>
                        </div>
                        <div class="icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                    </div>
                    <div class="stats-container">
                        <div class="status-section">
                            <div class="project-status-pills">
                                <span class="status-pill active">
                                    <i class="fas fa-circle"></i> Active Projects
                                    <span class="count">
                                        <?php echo array_sum(array_map(function($p) { 
                                            return $p['status'] === 'active' ? 1 : 0; 
                                        }, $pipeline_projects)); ?>
                                    </span>
                                </span>
                                <span class="status-pill ongoing">
                                    <i class="fas fa-circle"></i> Ongoing Projects
                                    <span class="count">
                                        <?php echo array_sum(array_map(function($p) { 
                                            return $p['status'] === 'ongoing' ? 1 : 0; 
                                        }, $pipeline_projects)); ?>
                                    </span>
                                </span>
                                <span class="status-pill completed">
                                    <i class="fas fa-circle"></i> Completed
                                    <span class="count">
                                        <?php echo array_sum(array_map(function($p) { 
                                            return $p['status'] === 'completed' ? 1 : 0; 
                                        }, $pipeline_projects)); ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div class="progress-section">
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Overall Progress</span>
                                    <span>68%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 68%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Type Cards Grid -->
                <div class="project-types-grid">
                    <!-- Architecture Projects Card -->
                    <div class="project-card architecture">
                        <div class="card-header">
                            <div class="header-info">
                                <h6>Architecture</h6>
                                <div class="amount"><?php echo $architecture_count; ?></div>
                            </div>
                            <div class="icon">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                        <div class="stats">
                            <div class="percentage-bar">
                                <div class="fill" style="width: <?php echo ($architecture_count / max(1, $total_projects)) * 100; ?>%"></div>
                            </div>
                            <div class="percentage-info">
                                <span class="percentage"><?php echo round(($architecture_count / max(1, $total_projects)) * 100); ?>% of total</span>
                                <span class="trend up">
                                    <i class="fas fa-arrow-up"></i>
                                    12%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Construction Projects Card -->
                    <div class="project-card construction">
                        <div class="card-header">
                            <div class="header-info">
                                <h6>Construction</h6>
                                <div class="amount"><?php echo $construction_count; ?></div>
                            </div>
                            <div class="icon">
                                <i class="fas fa-hard-hat"></i>
                            </div>
                        </div>
                        <div class="stats">
                            <div class="percentage-bar">
                                <div class="fill" style="width: <?php echo ($construction_count / max(1, $total_projects)) * 100; ?>%"></div>
                            </div>
                            <div class="percentage-info">
                                <span class="percentage"><?php echo round(($construction_count / max(1, $total_projects)) * 100); ?>% of total</span>
                                <span class="trend up">
                                    <i class="fas fa-arrow-up"></i>
                                    8%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Interior Projects Card -->
                    <div class="project-card interior">
                        <div class="card-header">
                            <div class="header-info">
                                <h6>Interior</h6>
                                <div class="amount"><?php echo $interior_count; ?></div>
                            </div>
                            <div class="icon">
                                <i class="fas fa-couch"></i>
                            </div>
                        </div>
                        <div class="stats">
                            <div class="percentage-bar">
                                <div class="fill" style="width: <?php echo ($interior_count / max(1, $total_projects)) * 100; ?>%"></div>
                            </div>
                            <div class="percentage-info">
                                <span class="percentage"><?php echo round(($interior_count / max(1, $total_projects)) * 100); ?>% of total</span>
                                <span class="trend down">
                                    <i class="fas fa-arrow-down"></i>
                                    5%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle functionality
        const toggleBtn = document.querySelector('.toggle-btn');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const toggleIcon = toggleBtn.querySelector('i');

        // Initialize sidebar state from localStorage
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent?.classList.add('expanded');
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        }

        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent?.classList.toggle('expanded');
            
            // Save sidebar state
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        });

        // Improved submenu toggle functionality
        const submenuTriggers = document.querySelectorAll('.has-submenu');
        
        submenuTriggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Close other open submenus
                if (!sidebar.classList.contains('collapsed')) {
                    submenuTriggers.forEach(otherTrigger => {
                        if (otherTrigger !== trigger) {
                            otherTrigger.classList.remove('active');
                            const otherSubmenu = otherTrigger.nextElementSibling;
                            if (otherSubmenu && otherSubmenu.classList.contains('sub-menu')) {
                                otherSubmenu.classList.remove('active');
                            }
                        }
                    });
                }
                
                // Toggle current submenu
                this.classList.toggle('active');
                const submenu = this.nextElementSibling;
                if (submenu && submenu.classList.contains('sub-menu')) {
                    submenu.classList.toggle('active');
                }
            });
        });

        // Close submenus when sidebar collapses
        toggleBtn.addEventListener('click', () => {
            if (sidebar.classList.contains('collapsed')) {
                document.querySelectorAll('.sub-menu').forEach(submenu => {
                    submenu.classList.remove('active');
                });
                submenuTriggers.forEach(trigger => {
                    trigger.classList.remove('active');
                });
            }
        });

        function updateDateTime() {
            const now = new Date();
            const hours = now.getHours();
            
            // Update greeting and icon based on time
            const greetingText = document.getElementById('greetingText');
            const timeIcon = document.getElementById('timeIcon');
            
            if (hours >= 5 && hours < 12) {
                greetingText.textContent = 'Good Morning';
                timeIcon.className = 'fas fa-sun fa-2x text-white';
            } else if (hours >= 12 && hours < 17) {
                greetingText.textContent = 'Good Afternoon';
                timeIcon.className = 'fas fa-sun fa-2x text-white';
            } else if (hours >= 17 && hours < 20) {
                greetingText.textContent = 'Good Evening';
                timeIcon.className = 'fas fa-cloud-sun fa-2x text-white';
            } else {
                greetingText.textContent = 'Good Night';
                timeIcon.className = 'fas fa-moon fa-2x text-white';
            }

            // Update date
            const options = { 
                weekday: 'long', 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric'
            };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);

            // Update time
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        // Update immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Initial load
        fetchSalesData();

        // Form submission
        document.getElementById('salesFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetchSalesData();
        });

        function fetchSalesData() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const salesContent = document.getElementById('salesContent');

            // Show loading state
            salesContent.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            `;

            fetch(`fetch_sales_data.php?startDate=${startDate}&endDate=${endDate}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    salesContent.innerHTML = `
                        <div class="sales-overview-container">
                            <div class="sales-main-content">
                                <!-- Total Sales Card -->
                                <div class="total-sales-card">
                                    <div class="total-sales-info">
                                        <div class="sales-amount">
                                            <h6>Total Sales</h6>
                                            <div class="amount">₹${data.total.sales}</div>
                                        </div>
                                        <div class="total-sales-trend">
                                            <span class="trend ${data.total.growth >= 0 ? 'up' : 'down'}">
                                                <i class="fas fa-arrow-${data.total.growth >= 0 ? 'up' : 'down'}"></i>
                                                ${Math.abs(data.total.growth)}%
                                            </span>
                                            <span class="period">vs last month</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sales Cards Grid -->
                                <div class="sales-cards-grid">
                                    <!-- Architecture Card -->
                                    <div class="sales-card architecture">
                                        <div class="card-header">
                                            <h6>Architecture Consultancy</h6>
                                            <div class="icon architecture">
                                                <i class="fas fa-building"></i>
                                            </div>
                                        </div>
                                        <div class="amount">₹${data.architecture.sales}</div>
                                        <div class="stats">
                                            <div class="comparison">
                                                <span class="trend ${data.architecture.growth >= 0 ? 'up' : 'down'}">
                                                    <i class="fas fa-arrow-${data.architecture.growth >= 0 ? 'up' : 'down'}"></i>
                                                    ${Math.abs(data.architecture.growth)}%
                                                </span>
                                                <span class="period">vs last month</span>
                                            </div>
                                            <div class="percentage">${data.architecture.percentage}% of total sales</div>
                                        </div>
                                    </div>

                                    <!-- Interior Card -->
                                    <div class="sales-card interior">
                                        <div class="card-header">
                                            <h6>Interior Consultancy</h6>
                                            <div class="icon interior">
                                                <i class="fas fa-couch"></i>
                                            </div>
                                        </div>
                                        <div class="amount">₹${data.interior.sales}</div>
                                        <div class="stats">
                                            <div class="comparison">
                                                <span class="trend ${data.interior.growth >= 0 ? 'up' : 'down'}">
                                                    <i class="fas fa-arrow-${data.interior.growth >= 0 ? 'up' : 'down'}"></i>
                                                    ${Math.abs(data.interior.growth)}%
                                                </span>
                                                <span class="period">vs last month</span>
                                            </div>
                                            <div class="percentage">${data.interior.percentage}% of total sales</div>
                                        </div>
                                    </div>

                                    <!-- Construction Card -->
                                    <div class="sales-card construction">
                                        <div class="card-header">
                                            <h6>Construction Consultancy</h6>
                                            <div class="icon construction">
                                                <i class="fas fa-hard-hat"></i>
                                            </div>
                                        </div>
                                        <div class="amount">₹${data.construction.sales}</div>
                                        <div class="stats">
                                            <div class="comparison">
                                                <span class="trend ${data.construction.growth >= 0 ? 'up' : 'down'}">
                                                    <i class="fas fa-arrow-${data.construction.growth >= 0 ? 'up' : 'down'}"></i>
                                                    ${Math.abs(data.construction.growth)}%
                                                </span>
                                                <span class="period">vs last month</span>
                                            </div>
                                            <div class="percentage">${data.construction.percentage}% of total sales</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- New Side Box -->
                            <div class="sales-side-box">
                                <div class="side-box-header">
                                    <i class="fas fa-project-diagram"></i>
                                    <h6>Projects in Pipeline</h6>
                                </div>
                                <div class="side-box-content">
                                    <div class="pipeline-projects">
                                        <?php foreach ($pipeline_projects as $project): ?>
                                            <?php
                                            // Determine priority class based on project type
                                            $priorityClass = '';
                                            switch ($project['project_type']) {
                                                case 'Architecture':
                                                    $priorityClass = 'high';
                                                    $icon = 'building';
                                                    break;
                                                case 'Interior':
                                                    $priorityClass = 'medium';
                                                    $icon = 'couch';
                                                    break;
                                                case 'Construction':
                                                    $priorityClass = 'low';
                                                    $icon = 'hard-hat';
                                                    break;
                                                default:
                                                    $priorityClass = 'medium';
                                                    $icon = 'project-diagram';
                                            }

                                            // Format the cost
                                            $formatted_cost = '₹' . number_format($project['total_cost'], 2);
                                            ?>
                                            <div class="pipeline-item <?php echo $priorityClass; ?>">
                                                <div class="project-header">
                                                    <span class="project-type">
                                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                                        <?php echo htmlspecialchars($project['project_type']); ?>
                                                    </span>
                                                    <span class="project-date">
                                                        <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                                                    </span>
                                                </div>
                                                <div class="project-name">
                                                    <?php echo htmlspecialchars($project['project_name']); ?>
                                                    <span class="project-id">#<?php echo htmlspecialchars($project['id']); ?></span>
                                                </div>
                                                <div class="project-details">
                                                    <div class="detail-row">
                                                        <i class="fas fa-user"></i>
                                                        <span><?php echo htmlspecialchars($project['client_name']); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <i class="fas fa-phone"></i>
                                                        <span><?php echo htmlspecialchars($project['mobile']); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <span><?php echo htmlspecialchars($project['location']); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <i class="fas fa-indian-rupee-sign"></i>
                                                        <span><?php echo $formatted_cost; ?></span>
                                                    </div>
                                                </div>
                                                <div class="project-status">
                                                    <span class="status-badge <?php echo $project['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                    </span>
                                                    <span class="assigned-to">
                                                        <i class="fas fa-user-tie"></i>
                                                        <?php echo htmlspecialchars($project['assigned_to']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error:', error);
                    salesContent.innerHTML = `
                        <div class="error-message" style="color: #dc3545; padding: 20px; text-align: center;">
                            <i class="fas fa-exclamation-circle"></i>
                            ${error.message || 'Error loading sales data. Please try again.'}
                        </div>
                    `;
                });
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Set default dates
            const today = new Date();
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
            
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];

            // Initial load
            fetchSalesData();

            // Form submission
            document.getElementById('salesFilterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                fetchSalesData();
            });
        });
    });
    </script>
</body>
</html>

