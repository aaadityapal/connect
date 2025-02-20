<?php
session_start();
require_once 'config.php'; // Make sure this path is correct and contains your database connection
require_once 'functions.php'; // Add this line at the top of your file with other includes

// Strict role checking
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Senior Manager (Studio)') {
    header('Location: login.php');
    exit();
}

// Get the manager's ID
$managerId = $_SESSION['user_id'];

// Fetch user's name from database
try {
    $nameQuery = "SELECT username FROM users WHERE id = ?";
    $stmt = $pdo->prepare($nameQuery);
    $stmt->execute([$managerId]);
    $userName = $stmt->fetchColumn();

    // If no username found, set a default
    if (!$userName) {
        $userName = 'Studio Manager';
    }
} catch (PDOException $e) {
    error_log("Error fetching user name: " . $e->getMessage());
    $userName = 'Studio Manager'; // Fallback name in case of error
}

// Enhanced database queries for real statistics
try {
    // Get team members count
    $teamQuery = "SELECT COUNT(*) FROM users WHERE reporting_manager = ?";
    $stmt = $pdo->prepare($teamQuery);
    $stmt->execute([$managerId]);
    $teamCount = $stmt->fetchColumn();

    // Fix the projects query and add default values
    $projectsQuery = "SELECT 
        (SELECT COUNT(*) FROM projects WHERE status = 'active' AND manager_id = ?) as active_projects,
        (SELECT COUNT(*) FROM projects WHERE status = 'completed' AND manager_id = ?) as completed_projects";
    $stmt = $pdo->prepare($projectsQuery);
    $stmt->execute([$managerId, $managerId]);
    $projectStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add this new query to get tasks by priority
    $priorityTasksQuery = "SELECT 
        priority,
        COUNT(*) as total_count,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
    FROM projects 
    WHERE manager_id = ?
    GROUP BY priority";
    $stmt = $pdo->prepare($priorityTasksQuery);
    $stmt->execute([$managerId]);
    $priorityTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set default values if query returns no results
    if (!$projectStats) {
        $projectStats = [
            'active_projects' => 0,
            'completed_projects' => 0
        ];
    }

    // Get tasks statistics
    $tasksQuery = "SELECT COUNT(*) FROM tasks WHERE assigned_by = ? AND status = 'pending'";
    $stmt = $pdo->prepare($tasksQuery);
    $stmt->execute([$managerId]);
    $pendingTasks = $stmt->fetchColumn();

    // Add this new query to get pending leaves count
    $leavesQuery = "SELECT COUNT(*) FROM leaves 
                    WHERE manager_id = ? 
                    AND status = 'pending'";
    $stmt = $pdo->prepare($leavesQuery);
    $stmt->execute([$managerId]);
    $pendingCount = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
    // Set default values in case of error
    $teamCount = 0;
    $projectStats = [
        'active_projects' => 0,
        'completed_projects' => 0
    ];
    $pendingTasks = 0;
    $pendingCount = 0;
    $priorityTasks = []; // Add this default value
}


// Add this function to get present employees
function getPresentEmployees($pdo) {
    $today = date('Y-m-d');
    $query = "SELECT users.username, users.unique_id, attendance.punch_in 
              FROM attendance 
              JOIN users ON attendance.user_id = users.id 
              WHERE DATE(attendance.date) = ? 
              AND attendance.punch_out IS NULL
              AND attendance.status = 'present'";  // Make sure status is 'present'
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$today]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching present employees: " . $e->getMessage());
        return [];
    }
}

// Add this before the HTML section
try {
    // Get employee statistics
    $employee_stats = [
        'total_users' => 0,
        'present_users' => 0,
        'users_on_leave' => 0,
        'pending_leaves' => 0,
        'short_leave' => 0
    ];

    // Get total users
    $total_users_query = "SELECT COUNT(*) FROM users WHERE status = 'active'";
    $employee_stats['total_users'] = $pdo->query($total_users_query)->fetchColumn();

    // Get present users
    $present_users_query = "SELECT COUNT(DISTINCT user_id) FROM attendance 
                           WHERE DATE(date) = CURDATE() 
                           AND punch_in IS NOT NULL 
                           AND punch_out IS NULL";
    $employee_stats['present_users'] = $pdo->query($present_users_query)->fetchColumn();

    // Get users on leave
    $users_on_leave_query = "SELECT COUNT(DISTINCT user_id) FROM leaves 
                            WHERE status = 'approved' 
                            AND CURDATE() BETWEEN start_date AND end_date";
    $employee_stats['users_on_leave'] = $pdo->query($users_on_leave_query)->fetchColumn();

    // Get pending leaves
    $pending_leaves_query = "SELECT COUNT(*) FROM leaves 
                            WHERE status = 'pending'";
    $employee_stats['pending_leaves'] = $pdo->query($pending_leaves_query)->fetchColumn();

    // Get short leaves for today
    $short_leave_query = "SELECT COUNT(*) FROM leaves 
                         WHERE status = 'approved' 
                         AND DATE(start_date) = CURDATE() 
                         AND leave_type = 'short'";
    $employee_stats['short_leave'] = $pdo->query($short_leave_query)->fetchColumn();

    // Get pending leaves details
    $pending_leaves_details_query = "
        SELECT 
            u.username as employee_name,
            l.leave_type,
            l.start_date,
            l.end_date,
            DATEDIFF(l.end_date, l.start_date) + 1 as days_count,
            l.status as hr_status,
            l.manager_status,
            l.studio_manager_status
        FROM leaves l
        JOIN users u ON l.user_id = u.id
        WHERE l.status = 'pending'
        ORDER BY l.start_date ASC";
    $pending_leaves_details = $pdo->query($pending_leaves_details_query)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching employee statistics: " . $e->getMessage());
    // Set default values in case of error
    $employee_stats = [
        'total_users' => 0,
        'present_users' => 0,
        'users_on_leave' => 0,
        'pending_leaves' => 0,
        'short_leave' => 0
    ];
    $pending_leaves_details = [];
}

function getLeaveStatistics($pdo) {
    try {
        $today = date('Y-m-d');
        
        // Get current leaves
        $current_leaves_query = "
            SELECT COUNT(*) as count 
            FROM leaves 
            WHERE status = 'approved' 
            AND start_date <= :today 
            AND end_date >= :today";
        
        // Get pending leaves
        $pending_leaves_query = "
            SELECT COUNT(*) as count 
            FROM leaves 
            WHERE status = 'pending'";
        
        // Get short leaves for today
        $short_leaves_query = "
            SELECT COUNT(*) as count 
            FROM leaves 
            WHERE status = 'approved' 
            AND leave_type = 'short' 
            AND start_date = :today";
        
        // Execute queries
        $stmt = $pdo->prepare($current_leaves_query);
        $stmt->execute(['today' => $today]);
        $current_leaves = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $pdo->prepare($pending_leaves_query);
        $stmt->execute();
        $pending_leaves = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $pdo->prepare($short_leaves_query);
        $stmt->execute(['today' => $today]);
        $short_leaves = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        return [
            'current_leaves' => (int)$current_leaves,
            'pending_leaves' => (int)$pending_leaves,
            'short_leaves' => (int)$short_leaves
        ];
    } catch (PDOException $e) {
        error_log("Error fetching leave statistics: " . $e->getMessage());
        return [
            'current_leaves' => 0,
            'pending_leaves' => 0,
            'short_leaves' => 0
        ];
    }
}

function getTotalEmployees($pdo) {
    try {
        $query = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
        $stmt = $pdo->query($query);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error fetching total employees: " . $e->getMessage());
        return 0;
    }
}

// Initialize variables with default values in case of errors
$leaveStats = [
    'current_leaves' => 0,
    'pending_leaves' => 0,
    'short_leaves' => 0
];
$totalEmployees = 0;

// Fetch the actual data
try {
    $leaveStats = getLeaveStatistics($pdo);
    $totalEmployees = getTotalEmployees($pdo);
} catch (Exception $e) {
    error_log("Error initializing dashboard data: " . $e->getMessage());
}


// Update the function to only fetch username
function getAllUsers($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, unique_id
            FROM users 
            WHERE role != 'admin' 
            ORDER BY username
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

// Get all users for the dropdown
$availableUsers = getAllUsers($pdo);

// Update the pending leaves card HTML to use this value
// Fetch stages pending data
$stagesQuery = "
    SELECT 
        ts.*, 
        t.title as task_title,
        u.username as assigned_user,
        u.unique_id as user_id
    FROM task_stages ts
    LEFT JOIN tasks t ON ts.task_id = t.id
    LEFT JOIN users u ON ts.assigned_to = u.id
    WHERE ts.status != 'completed'
    ORDER BY ts.due_date ASC";

$stmt = $pdo->prepare($stagesQuery);
$stmt->execute();
$pendingStages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total stages
$totalStagesQuery = "SELECT COUNT(*) FROM task_stages";
$stmt = $pdo->prepare($totalStagesQuery);
$stmt->execute();
$totalStages = $stmt->fetchColumn();

// Count pending stages
$pendingStagesCount = count($pendingStages);

$substagesQuery = "
    SELECT 
        ts.*, 
        t.title as task_title,
        s.stage_number,
        u.username as assigned_user,
        u.unique_id as user_id
    FROM task_substages ts
    LEFT JOIN task_stages s ON ts.stage_id = s.id
    LEFT JOIN tasks t ON s.task_id = t.id
    LEFT JOIN users u ON s.assigned_to = u.id
    WHERE ts.status != 'completed'
    ORDER BY ts.end_date ASC";

$stmt = $pdo->prepare($substagesQuery);
$stmt->execute();
$pendingSubstages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total substages
$totalSubstagesQuery = "SELECT COUNT(*) FROM task_substages";
$stmt = $pdo->prepare($totalSubstagesQuery);
$stmt->execute();
$totalSubstages = $stmt->fetchColumn();

// Count pending substages
$pendingSubstagesCount = count($pendingSubstages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio Manager Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@4/dark.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="assets/js/simple-chat.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- For error messages -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="./assets/css/studio_task_modal.css">
    <link rel="stylesheet" href="./assets/css/approval_board.css">
    <link rel="stylesheet" href="assets/css/task-calendar.css">
    <link rel="stylesheet" href="assets/css/notifications.css">

    <script src="../assets/js/notifications.js"></script>
    
    
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f5f6fa;
        }

        .wrapper {
            display: flex;
        }

        /* Updated/New Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-color);
            padding: 40px 15px 20px;
            transition: all 0.3s ease;
            position: fixed;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow: visible;
            z-index: 99;
            display: flex;
            flex-direction: column;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
            padding: 40px 10px 20px;
        }

        /* Updated Navigation Links */
        .nav-links {
            list-style: none; /* This removes the bullet points */
            padding: 0; /* Remove default padding */
            margin: 0; /* Reset margin */
            flex-grow: 1; /* This pushes the logout button to the bottom */
        }

        .nav-links li {
            list-style-type: none; /* Explicitly remove list styling */
            padding: 12px 8px;
            margin: 8px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .nav-links i {
            min-width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }

        .nav-text {
            margin-left: 10px;
            transition: opacity 0.3s ease;
        }

        /* Collapsed state styles */
        .sidebar.collapsed .nav-text {
            opacity: 0;
            width: 0;
            height: 0;
            margin: 0;
        }

        .sidebar.collapsed .nav-links li {
            padding: 12px 0;
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .sidebar.collapsed .nav-links a {
            justify-content: center;
        }

        .sidebar.collapsed .nav-links i {
            margin: 0;
        }

        /* Hover effects */
        .nav-links li:hover {
            background: var(--accent-color);
            transform: translateX(5px);
        }

        .sidebar.collapsed .nav-links li:hover {
            transform: scale(1.1);
        }

        /* Active state */
        .nav-links li.active {
            background: var(--accent-color);
        }

        /* Enhanced tooltip for collapsed state */
        .sidebar.collapsed .nav-links a::after {
            content: attr(data-title);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
            margin-left: 15px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        .sidebar.collapsed .nav-links a:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* Updated Toggle Button Styles */
        .toggle-btn {
            position: absolute;
            right: -12px;
            top: 20px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 100;
        }

        .toggle-btn:hover {
            background: #2980b9;
            transform: scale(1.1);
        }

        .toggle-btn i {
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .sidebar.collapsed .toggle-btn i {
            transform: rotate(180deg);
        }

        /* Main content adjustment */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s ease;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        /* Add these new styles */
        .logout-btn {
            background-color: #C41E3A;
            padding: 12px 8px;
            margin: 20px 0 10px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: none;
            width: 100%;
            cursor: pointer;
        }

        .logout-btn a {
            color: black; /* Red color for logout */
            text-decoration: none;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .logout-btn i {
            min-width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }

        .logout-btn .nav-text {
            margin-left: 10px;
        }

        .logout-btn:hover {
            background: #986868 /* Light red background on hover */
        }

        /* Collapsed state styles for logout */
        .sidebar.collapsed .logout-btn {
            padding: 12px 0;
            display: flex;
            justify-content: center;
        }

        .sidebar.collapsed .logout-btn .nav-text {
            opacity: 0;
            width: 0;
            height: 0;
            margin: 0;
        }

        /* Tooltip for logout when collapsed */
        .sidebar.collapsed .logout-btn a::after {
            content: "Logout";
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
            margin-left: 15px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        .sidebar.collapsed .logout-btn:hover a::after {
            opacity: 1;
            visibility: visible;
        }

        /* Updated Greeting Section Styles */
        .greeting-section {
            background: linear-gradient(135deg, #1a4f95 0%, #0ea5e9 100%);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 32px;
            color: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* Add subtle pattern overlay */
        .greeting-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M54.627 0l.83.828-1.415 1.415L51.8 0h2.827zM5.373 0l-.83.828L5.96 2.243 8.2 0H5.374zM48.97 0l3.657 3.657-1.414 1.414L46.143 0h2.828zM11.03 0L7.372 3.657 8.787 5.07 13.857 0H11.03zm32.284 0L49.8 6.485 48.384 7.9l-7.9-7.9h2.83zM16.686 0L10.2 6.485 11.616 7.9l7.9-7.9h-2.83zm5.657 0L19.514 8.485 20.93 9.9l8.485-8.485h-1.415zM32.343 0L13.857 10.03 15.272 11.444l10.03-10.03h-1.415zm-1.415 0L19.514 11.444l1.414 1.414L34.2 0h-3.242zm-5.656 0L12.686 12.615l1.415 1.415L27.372 0h-2.83zM38.03 0L26.585 11.444l1.414 1.414L41.243 0h-3.242zm3.242 0L29.8 11.444l1.414 1.414L43.457 0h-2.187zm5.657 0L35.457 11.444l1.414 1.414L49.114 0h-2.187zm5.657 0L41.114 11.444l1.414 1.414L54.627 0h-2.185zm5.657 0L46.77 11.444l1.415 1.414L60 0h-1.415zM54.627 5.373L43.184 16.817l1.414 1.414L60 5.373v-2.83zm0 5.657L48.84 16.817l1.415 1.414L60 11.03v-2.83zm0 5.657L54.497 16.817l1.414 1.414L60 16.687v-2.83zM54.627 22.343L60 16.97v-2.83L52.212 19.927l1.415 1.414zm0 5.657L60 22.627v-2.83l-7.785 7.785 1.414 1.414zm0 5.657L60 28.284v-2.83L52.212 31.24l1.415 1.414zm0 5.657L60 33.94v-2.83l-7.785 7.785 1.414 1.414zm0 5.657L60 39.6v-2.83l-7.785 7.785 1.414 1.414zm0 5.657L60 45.255v-2.83l-7.785 7.785 1.414 1.414zm0 5.657L60 50.912v-2.83l-7.785 7.785 1.414 1.414zM49.114 54.627L60 56.97v-2.83L46.7 56.042l1.414 1.414zm-5.657 0L60 62.627v-2.83L41.042 61.7l1.414 1.414zm-5.657 0L60 68.284v-2.83l-23.8-23.8 1.414 1.414zm-5.657 0L60 73.94v-2.83L35.385 67.357l1.414 1.414zm-5.657 0L60 79.6v-2.83L29.728 72.7l1.414 1.414z' fill='%23ffffff' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
        }

        .greeting-content {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .greeting-content h1 {
            font-size: 2rem;
            font-weight: 600;
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .datetime {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
        }

        .datetime i {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .greeting-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        /* Updated Button Styles */
        .studio-task-creation-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            backdrop-filter: blur(8px);
        }

        .studio-task-creation-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .punch-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            backdrop-filter: blur(8px);
        }

        .punch-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .punch-btn.punched {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Updated Notification Icon */
        .notification-icon {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(8px);
        }

        .notification-icon:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        /* Avatar Dropdown Styles - Updated */
        .avatar-container {
            position: relative;
            z-index: 9999; /* High z-index */
        }

        .avatar {
            cursor: pointer;
            position: relative;
            z-index: 9999;
            width: 40px;
            height: 40px;
            background: #4F46E5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
        }

        .avatar-dropdown {
            display: none;
            position: fixed; /* Change to fixed positioning */
            top: 70px; /* Adjust based on your header height */
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 220px;
            z-index: 10000;
        }

        .avatar-dropdown.show {
            display: block;
        }

        /* Ensure proper stacking context */
        body {
            position: relative;
            z-index: 1;
        }

        .wrapper {
            position: relative;
            z-index: 1;
        }

        .main-content {
            position: relative;
            z-index: 1;
        }

        .greeting-section {
            position: relative;
            z-index: 2;
        }

        .greeting-actions {
            position: relative;
            z-index: 9999;
        }

        /* Rest of the dropdown styles remain the same */
        .dropdown-header {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .user-name {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .user-role {
            display: block;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 4px 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .dropdown-item:hover {
            background-color: #f3f4f6;
        }

        .dropdown-item i {
            font-size: 1rem;
            width: 16px;
            color: #6b7280;
        }

        .dropdown-item.logout {
            color: #ef4444;
        }

        .dropdown-item.logout i {
            color: #ef4444;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid #1a4f95;
        }

        /* Add animation for time updates */
        @keyframes timeUpdate {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }

        #currentTime {
            animation: timeUpdate 2s infinite;
        }

        /* Custom animation for the alert */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translate3d(0, -20%, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }
        }

        .animated {
            animation-duration: 0.3s;
            animation-fill-mode: both;
        }

        .fadeInDown {
            animation-name: fadeInDown;
        }

        /* Custom styles for SweetAlert */
        .swal2-popup {
            border-radius: 15px !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .swal2-title {
            font-size: 1.5em !important;
        }

        .swal2-confirm {
            border-radius: 8px !important;
            padding: 12px 25px !important;
            font-weight: 500 !important;
        }

        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #2D2D2D;
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transform: translateX(120%);
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
        }

        .toast-notification.show {
            transform: translateX(0);
        }

        .toast-notification.error {
            border-left: 4px solid #DC3545;
        }

        .toast-notification.success {
            border-left: 4px solid #28A745;
        }

        .toast-icon {
            font-size: 1.2em;
        }

        .toast-notification.error .toast-icon {
            color: #DC3545;
        }

        .toast-notification.success .toast-icon {
            color: #28A745;
        }

        .toast-message {
            font-family: 'Segoe UI', sans-serif;
            font-size: 0.95em;
        }

        .task-overview-section {
            padding: 1.5rem;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }

        .task-overview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr; /* Adjusted for the wider recent tasks column */
            gap: 24px;
            margin-top: 20px;
        }

        .metrics-column {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .metrics-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: calc(50% - 12px); /* Adjust height to account for gap */
        }

        .metrics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .metrics-icon {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stages-pending .metrics-icon { color: #6366f1; }
        .substages-pending .metrics-icon { color: #8b5cf6; }
        .active-stages .metrics-icon { color: #10b981; }
        .upcoming-deadlines .metrics-icon { color: #f59e0b; }

        .metrics-content h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .metrics-numbers {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: baseline;
            gap: 0.25rem;
        }

        .metrics-numbers .divider {
            color: #9ca3af;
            font-weight: 400;
        }

        .metrics-numbers .total {
            color: #6b7280;
            font-size: 1rem;
        }

        .metrics-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        /* Recent Tasks Column Styles */
        .recent-tasks-column {
            grid-column: 3;
            grid-row: 1 / span 2; /* Spans both rows */
        }

        .recent-tasks-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .recent-tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .recent-tasks-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
        }

        .recent-tasks-header select {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .recent-tasks-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .task-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .task-item:hover {
            background: #f3f4f6;
        }

        .task-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .task-title {
            font-weight: 500;
            color: #111827;
        }

        .task-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .task-status-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .task-deadline {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .task-status {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .task-status.completed {
            background: #d1fae5;
            color: #059669;
        }

        .task-status.in-progress {
            background: #dbeafe;
            color: #2563eb;
        }

        .task-status.delayed {
            background: #fee2e2;
            color: #dc2626;
        }

        .task-status.pending {
            background: #fef3c7;
            color: #d97706;
        }

        .date-filter .input-group {
            width: auto;
        }

        .date-filter .input-group-text {
            background: #f8f9fa;
            border-right: none;
        }

        .date-filter input.form-control {
            border-left: none;
            padding-left: 0;
        }

        .filter-btn {
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .total-tasks-card {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .task-illustration {
            font-size: 5rem;
            opacity: 0.2;
            position: absolute;
            right: 2rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.08);
        }

        .stats-card.compact {
            padding: 1rem;
        }

        .stats-card.compact .stats-icon {
            width: 2.5rem;
            height: 2.5rem;
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }

        .stats-card.compact .stats-number {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .stats-card.compact .stats-label {
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .stats-card.compact .stats-trend {
            font-size: 0.75rem;
        }

        /* Make the first card taller */
        .col-md-4 .stats-card:not(.compact) {
            height: calc(100% - 1.5rem);
            margin-bottom: 1.5rem;
        }

        /* Adjust progress bar in compact cards */
        .stats-card.compact .progress {
            height: 4px;
            margin-top: 0.5rem;
        }

        /* Enhance hover effect */
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08);
        }

        .stats-card .icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stats-card .icon {
            background: rgba(78, 84, 200, 0.1);
            color: #4e54c8;
        }

        .stats-card .icon.warning {
            background: rgba(255, 159, 67, 0.1);
            color: #ff9f43;
        }

        .stats-card .icon.danger {
            background: rgba(234, 84, 85, 0.1);
            color: #ea5455;
        }

        .stats-number {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 0.5rem;
        }

        .stats-info p {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .progress {
            background-color: #f8f9fa;
        }

        .progress-bar {
            background-color: #4e54c8;
        }

        @media (max-width: 768px) {
            .date-filter {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .date-filter .input-group {
                width: 100%;
            }
            
            .filter-btn {
                width: 100%;
            }
        }

        /* Updated Task Overview Styles */
        .task-overview-section {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            display: flex;
            flex-direction: column;
        }

        .section-title h4 {
            margin-bottom: 0.5rem;
        }

        .section-title p {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .date-filter {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .date-filter .input-group {
            width: auto;
        }

        .date-filter .input-group-text {
            background: #f8f9fa;
            border-right: none;
        }

        .date-filter input.form-control {
            border-left: none;
            padding-left: 0;
        }

        .filter-btn {
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08);
        }

        .stats-card .icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stats-card .icon {
            background: rgba(78, 84, 200, 0.1);
            color: #4e54c8;
        }

        .stats-card .icon.warning {
            background: rgba(255, 159, 67, 0.1);
            color: #ff9f43;
        }

        .stats-card .icon.danger {
            background: rgba(234, 84, 85, 0.1);
            color: #ea5455;
        }

        .stats-number {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 0.5rem;
        }

        .stats-info p {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .progress {
            background-color: #f8f9fa;
        }

        .progress-bar {
            background-color: #4e54c8;
        }

        .stats-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6c757d;
        }

        .stats-trend.positive {
            color: #28A745;
        }

        .stats-trend.negative {
            color: #DC3545;
        }

        /* Updated Stats Card Styles */
        .stats-card.mini {
            padding: 0.75rem;
        }

        .stats-card.mini .stats-icon {
            width: 2rem;
            height: 2rem;
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .stats-card.mini .stats-number {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .stats-card.mini .stats-label {
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .stats-card.mini .stats-trend {
            font-size: 0.625rem;
        }

        /* Updated Stats Card Styles */
        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.08);
        }

        /* New mini card style for the smaller cards */
        .stats-card.mini {
            padding: 0.75rem;
            text-align: center;
        }

        .stats-card.mini .stats-icon {
            width: 2rem;
            height: 2rem;
            font-size: 0.875rem;
            margin: 0 auto 0.5rem;
        }

        .stats-card.mini .stats-number {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .stats-card.mini .stats-label {
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .stats-card.mini .stats-trend {
            font-size: 0.7rem;
        }

        /* Progress bar adjustments for mini cards */
        .stats-card.mini .progress {
            height: 3px;
            margin-top: 0.25rem;
        }

        /* Icon styles */
        .stats-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        /* Card color variations */
        .stats-card.primary .stats-icon {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        .stats-card.success .stats-icon {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .stats-card.warning .stats-icon {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .stats-card.danger .stats-icon {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        /* Hover effect */
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.05);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-card.mini {
                margin-bottom: 1rem;
            }
            
            .col-4 {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    

    
       
        /* Calendar Styles */
        .calendar-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.08);
        }

        .calendar-header {
            margin-bottom: 15px;
        }

        .calendar-header h4 {
            margin: 0;
            color: #4b5563;
            font-size: 1.1rem;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: 500;
            color: #6b7280;
            font-size: 0.8rem;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .calendar-dates {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            padding-top: 10px;
            gap: 8px;
            padding: 10px 0;
        }

        .calendar-date {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            color: #4b5563;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .calendar-date:hover:not(.empty) {
            background-color: #f3f4f6;
            border-color: #e5e7eb;
        }

        .calendar-date.empty {
            cursor: default;
        }

        .calendar-date.current {
            background-color: #6366f1;
            color: white;
            font-weight: 600;
        }

        .calendar-date.has-events {
            border-color: #6366f1;
            color: #6366f1;
            font-weight: 500;
        }

        /* Quick view card adjustments */
        .quick-view-card {
            padding: 24px; /* Increased padding */
            height: calc((100% - 30px) / 2); /* Adjust height to account for gap */
            min-height: 160px; /* Minimum height for cards */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Responsive adjustments */
        @media (max-width: 1400px) {
            .quick-view-cards {
                gap: 20px;
            }
            
            .overview-column {
                gap: 20px;
            }
        }

        @media (max-width: 1200px) {
            .quick-view-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .overview-column:last-child {
                grid-column: span 2;
                margin-top: 20px;
            }
            
            .calendar-card {
                height: 400px; /* Fixed height for smaller screens */
            }
        }

        @media (max-width: 768px) {
            .quick-view-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .overview-column {
                gap: 15px;
            }
            
            .overview-column:last-child {
                grid-column: span 1;
            }
            
            .quick-view-card {
                height: auto;
                min-height: 140px;
            }
        }

        /* Weekend dates style */
        .calendar-weekdays div:first-child,
        .calendar-weekdays div:last-child {
            color: #ef4444;
        }

        .calendar-date:nth-child(7n+1),
        .calendar-date:nth-child(7n) {
            color: #ef4444;
        }

        .calendar-date:nth-child(7n+1).current,
        .calendar-date:nth-child(7n).current {
            background-color: #ef4444;
            color: white;
        }

        /* Add these new card-specific styles */
        .quick-view-card.task-total {
            border-color: #6366f1;
            background: linear-gradient(145deg, #ffffff 0%, #eef2ff 100%);
        }

        .quick-view-card.stages-pending {
            border-color: #f97316;
            background: linear-gradient(145deg, #ffffff 0%, #fff7ed 100%);
        }

        .quick-view-card.stages-total {
            border-color: #22c55e;
            background: linear-gradient(145deg, #ffffff 0%, #f0fdf4 100%);
        }

        .quick-view-card.tasks-delayed {
            border-color: #ef4444;
            background: linear-gradient(145deg, #ffffff 0%, #fef2f2 100%);
        }

        .task-total .card-icon {
            background: #6366f1;
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .stages-pending .card-icon {
            background: #f97316;
            color: white;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
        }

        .stages-total .card-icon {
            background: #22c55e;
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
        }

        .tasks-delayed .card-icon {
            background: #ef4444;
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }


        /* Add this notification panel HTML after the notification icon */
        .notification-panel {
            display: none;
            position: absolute;
            top: 60px;
            right: 20px;
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            border: 1px solid #e5e7eb;
        }

        /* Add these styles */
        .notification-header {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 1rem;
            color: #374151;
        }

        .clear-all {
            background: none;
            border: none;
            color: #6366f1;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f9fafb;
        }

        .notification-item.unread {
            background-color: #eef2ff;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-content {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #6366f1;
        }

        .notification-text {
            flex: 1;
        }

        .notification-title {
            font-weight: 500;
            color: #374151;
            margin-bottom: 4px;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* Animation for new notifications */
        @keyframes notification-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .notification-badge.has-new {
            animation: notification-pulse 1s infinite;
        }

        /* Add these new styles for the pending leaves tooltip */
        .pending-leave-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
        }

        .pending-leave-item:last-child {
            border-bottom: none;
        }

        .leave-user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .leave-user-name {
            font-weight: 500;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .leave-dates {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .leave-actions {
            display: flex;
            gap: 8px;
        }

        .leave-action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            border: none;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .leave-accept-btn {
            background: #22c55e;
            color: white;
        }

        .leave-accept-btn:hover {
            background: #16a34a;
        }

        .leave-reject-btn {
            background: #ef4444;
            color: white;
        }

        .leave-reject-btn:hover {
            background: #dc2626;
        }


        /* Quick View Section Layout Fixes */
        .quick-view-section {
            margin-bottom: 30px;
        }

        .quick-view-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 20px;
        }

        .overview-column {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Quick View Card Styles */
        .quick-view-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
            position: relative;
            transition: all 0.3s ease;
            height: 100%;
            min-height: 160px;
        }

        .quick-view-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        /* Card Content Layout */
        .card-content {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 16px;
        }

        .card-content h3 {
            font-size: 1.1rem;
            color: #374151;
            margin: 0;
        }

        .card-numbers {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .card-numbers .current {
            font-size: 2rem;
            font-weight: 600;
            color: #111827;
        }

        .card-numbers .divider {
            color: #9CA3AF;
        }

        .card-numbers .total {
            color: #6B7280;
            font-size: 1.25rem;
        }

        .card-subtitle {
            color: #6B7280;
            font-size: 0.875rem;
            margin: 0;
        }

        /* Calendar Card Specific Styles */
        .calendar-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .calendar-header {
            margin-bottom: 20px;
        }

        .calendar-body {
            flex: 1;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .quick-view-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .overview-column:last-child {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .quick-view-cards {
                grid-template-columns: 1fr;
            }
            
            .overview-column:last-child {
                grid-column: span 1;
            }
            
            .quick-view-card {
                min-height: auto;
            }
        }

    

        /* Add these new status indicator styles */
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .status-indicator select {
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            font-size: 0.875rem;
            background-color: white;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 4px;
        }

        .status-not-started .status-dot {
            background-color: #9ca3af;
        }

        .status-in-progress .status-dot {
            background-color: #3b82f6;
        }

        .status-completed .status-dot {
            background-color: #10b981;
        }

        .status-delayed .status-dot {
            background-color: #ef4444;
        }

        .status-on-hold .status-dot {
            background-color: #f59e0b;
        }

        /* Employee Overview Section Styles */
        .employee-metrics-section {
            margin-bottom: 30px;
        }

        .employee-metrics-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 20px;
        }

        .metrics-column {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Metrics Card Base Styles */
        .metrics-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
            position: relative;
            transition: all 0.3s ease;
            height: 100%;
            min-height: 160px;
        }

        .metrics-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        /* Card Content Layout */
        .metrics-content {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .metrics-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 16px;
        }

        .metrics-content h3 {
            font-size: 1.1rem;
            color: #374151;
            margin: 0;
        }

        .metrics-numbers {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .metrics-numbers .current {
            font-size: 2rem;
            font-weight: 600;
            color: #111827;
        }

        .metrics-numbers .divider {
            color: #9CA3AF;
        }

        .metrics-numbers .total {
            color: #6B7280;
            font-size: 1.25rem;
        }

        .metrics-subtitle {
            color: #6B7280;
            font-size: 0.875rem;
            margin: 0;
        }

        /* Card Specific Styles */
        .metrics-card.attendance {
            border-color: #6366f1;
            background: linear-gradient(145deg, #ffffff 0%, #eef2ff 100%);
        }

        .metrics-card.leaves {
            border-color: #f97316;
            background: linear-gradient(145deg, #ffffff 0%, #fff7ed 100%);
        }

        .metrics-card.short-leaves {
            border-color: #22c55e;
            background: linear-gradient(145deg, #ffffff 0%, #f0fdf4 100%);
        }

        /* Icon Colors */
        .attendance .metrics-icon {
            background: #6366f1;
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .leaves .metrics-icon {
            background: #f97316;
            color: white;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
        }

        .short-leaves .metrics-icon {
            background: #22c55e;
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
        }

        /* Tooltip Styles */
        .employee-details-tooltip {
            position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10;
    max-height: 450px;
    overflow-y: auto;
    border: 1px solid rgba(99, 102, 241, 0.1);
    margin-top: 15px;
}

        .metrics-card:hover .employee-details-tooltip {
            display: block;
        }

        .tooltip-header {
            display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px;
    background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 2px solid rgba(99, 102, 241, 0.2);
    font-weight: 600;
    color: #1e293b;
    position: sticky;
    top: 0;
    z-index: 1;
}

        .employee-item {
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
        }

        .employee-item:last-child {
            border-bottom: none;
        }

        .employee-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .employee-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4b5563;
        }

        .employee-count {
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .employee-metrics-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .metrics-column:last-child {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .employee-metrics-cards {
                grid-template-columns: 1fr;
            }
            
            .metrics-column:last-child {
                grid-column: span 1;
            }
            
            .metrics-card {
                min-height: auto;
            }
        }

        /* Adjust tooltip position for cards that might get cut off */
        .metrics-card.leaves .employee-details-tooltip,
        .metrics-card.short-leaves .employee-details-tooltip {
            left: auto;
            right: 0; /* Align to right edge of card */
        }

        /* Ensure tooltips stay on top */
        .metrics-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            z-index: 1001; /* Ensure hovered card stays on top */
        }

        /* Task Overview Section Styles */
        .task-overview-section {
            padding: 1.5rem;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }

        .task-overview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr; /* Adjusted for the wider recent tasks column */
            gap: 24px;
            margin-top: 20px;
        }

        .metrics-column {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .metrics-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: calc(50% - 12px); /* Adjust height to account for gap */
        }

        .metrics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .metrics-icon {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stages-pending .metrics-icon { color: #6366f1; }
        .substages-pending .metrics-icon { color: #8b5cf6; }
        .active-stages .metrics-icon { color: #10b981; }
        .upcoming-deadlines .metrics-icon { color: #f59e0b; }

        .metrics-content h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .metrics-numbers {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: baseline;
            gap: 0.25rem;
        }

        .metrics-numbers .divider {
            color: #9ca3af;
            font-weight: 400;
        }

        .metrics-numbers .total {
            color: #6b7280;
            font-size: 1rem;
        }

        .metrics-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        /* Recent Tasks Column Styles */
        .recent-tasks-column {
            grid-column: 3;
            grid-row: 1 / span 2; /* Spans both rows */
        }

        .recent-tasks-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .recent-tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .recent-tasks-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
        }

        .recent-tasks-header select {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .recent-tasks-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .task-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .task-item:hover {
            background: #f3f4f6;
        }

        .task-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .task-title {
            font-weight: 500;
            color: #111827;
        }

        .task-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .task-status-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .task-deadline {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .task-status {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .task-status.completed {
            background: #d1fae5;
            color: #059669;
        }

        .task-status.in-progress {
            background: #dbeafe;
            color: #2563eb;
        }

        .task-status.delayed {
            background: #fee2e2;
            color: #dc2626;
        }

        .task-status.pending {
            background: #fef3c7;
            color: #d97706;
        }

        .project-numbers .divider {
            color: #9CA3AF;
        }

        .project-numbers .total {
            color: #6B7280;
            font-size: 1.25rem;
        }

        .project-subtitle {
            color: #6B7280;
            font-size: 0.875rem;
            margin: 0;
        }

        /* Card Specific Styles */
        .project-card.total-tasks {
            border-color: #6366f1;
            background: linear-gradient(145deg, #ffffff 0%, #eef2ff 100%);
        }

        .project-card.pending-stages {
            border-color: #f97316;
            background: linear-gradient(145deg, #ffffff 0%, #fff7ed 100%);
        }

        .project-card.total-stages {
            border-color: #22c55e;
            background: linear-gradient(145deg, #ffffff 0%, #f0fdf4 100%);
        }

        .project-card.delayed-tasks {
            border-color: #ef4444;
            background: linear-gradient(145deg, #ffffff 0%, #fef2f2 100%);
        }

        /* Icon Colors */
        .total-tasks .project-icon {
            background: #6366f1;
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .pending-stages .project-icon {
            background: #f97316;
            color: white;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
        }

        .total-stages .project-icon {
            background: #22c55e;
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
        }

        .delayed-tasks .project-icon {
            background: #ef4444;
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }

        /* Task Priority Tooltip Styles */
        .tasks-priority-tooltip {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 280px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            border: 1px solid #e5e7eb;
            padding: 12px;
            margin-top: 10px;
        }

        .project-card:hover .tasks-priority-tooltip {
            display: block;
        }

        .priority-tooltip-header {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 500;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tooltip-header i {
    color: #6366f1;
    font-size: 1.1rem;
}

        .priority-item {
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
        }

        .priority-item:last-child {
            border-bottom: none;
        }

        .priority-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .priority-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4b5563;
        }

        .priority-count {
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Calendar Card Specific Styles */
        .project-calendar-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .project-metrics-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .project-column:last-child {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .project-metrics-cards {
                grid-template-columns: 1fr;
            }
            
            .project-column:last-child {
                grid-column: span 1;
            }
            
            .project-card {
                min-height: auto;
            }
        }

        /* Adjust tooltip position for cards that might get cut off */
        .project-card.pending-stages .tasks-priority-tooltip,
        .project-card.delayed-tasks .tasks-priority-tooltip {
            left: auto;
            right: 0;
        }

        /* Ensure tooltips stay on top */
        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            z-index: 1001;
        }

        /* File upload container styles */
        .file-upload-container {
            margin: 10px 0;
            grid-column: 1 / -1;
        }

        .file-list {
            margin-top: 5px;
            font-size: 0.875rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .file-remove {
            color: #ef4444;
            cursor: pointer;
        }

        /* Timeline styles */
        .substage-timeline {
            display: flex;
            gap: 10px;
            margin: 8px 0;
        }

        .substage-timeline input[type="datetime-local"] {
            width: 200px;
        }

        /* Substage file upload styles */
        .substage-file-upload {
            margin: 8px 0;
        }

        /* Responsive adjustments */
        @media (max-width: 1280px) {
            .studio-task-modal {
                width: 95%;
            }
        }

        @media (max-width: 768px) {
            .studio-task-stage__controls {
                grid-template-columns: 1fr;
            }
            
            .studio-task-substage {
                grid-template-columns: 1fr;
            }
        }

        /* Add these styles for the sidebar task button */
        .sidebar-task-btn {
            margin: 20px 15px;
            padding: 10px;
            background: #10B981;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            width: calc(100% - 30px);
            transition: all 0.3s ease;
        }

        .sidebar-task-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .sidebar-task-btn i {
            font-size: 1rem;
        }

        .sidebar.collapsed .sidebar-task-btn span {
            display: none;
        }

        .sidebar.collapsed .sidebar-task-btn {
            width: 40px;
            margin: 20px auto;
            justify-content: center;
        }

        .leave-type {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            margin-left: 10px;
        }

        .leave-details {
            margin: 8px 0;
            font-size: 0.9em;
            color: #666;
        }

        .leave-reason {
            margin-top: 5px;
            font-style: italic;
        }

        .pending-leave-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .pending-leave-item:last-child {
            border-bottom: none;
        }

        .leave-action-btn {
            padding: 5px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            font-size: 0.9em;
        }

        .leave-accept-btn {
            background: #28a745;
            color: white;
        }

        .leave-reject-btn {
            background: #dc3545;
            color: white;
        }

        .leave-user-info {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .leave-dates i, .leave-reason i {
            margin-right: 5px;
            color: #666;
        }

        .employee-details-tooltip {
            max-height: 400px;
            overflow-y: auto;
        }

        .employee-metrics-section {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        /* Whiteboard Styles */
        .whiteboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .whiteboard-header {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .whiteboard-filters {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .whiteboard-filters select {
            padding: 6px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .whiteboard-body {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
        }

        .whiteboard-legend {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.875rem;
        }

        .legend-item::before {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        .weekly-off::before {
            background: #CBD5E1;
        }

        .on-leave::before {
            background: #FFA500; /* Orange color */
        }

        .short-leave::before {
            background: #22C55E; /* Green color */
        }

        .upcoming-leave::before {
            background: #FCD34D;
        }

        .employee-row {
            display: flex;
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            align-items: center;
        }

        .employee-info {
            width: 150px;
            font-size: 0.875rem;
        }

        .employee-status {
            flex: 1;
            display: flex;
            gap: 4px;
        }

        .status-cell {
            flex: 1;
            padding: 4px;
            text-align: center;
            border-radius: 3px;
            font-size: 0.75rem;
        }

        .status-weekly-off {
            background: #CBD5E1;
            color: #1F2937;
        }

        .status-on-leave {
            background: #FFA500 !important; /* Orange color */
            color: #1F2937;
        }

        .status-short-leave {
            background: #22C55E !important; /* Green color */
            color: #1F2937;
        }

        .status-upcoming-leave {
            background: #FCD34D;
        }

        /* Avatar Dropdown Styles - Updated */
        .avatar-container {
            position: relative;
            z-index: 9999; /* High z-index */
        }

        .avatar {
            cursor: pointer;
            position: relative;
            z-index: 9999;
            width: 40px;
            height: 40px;
            background: #4F46E5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
        }

        .avatar-dropdown {
            display: none;
            position: fixed; /* Change to fixed positioning */
            top: 70px; /* Adjust based on your header height */
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 220px;
            z-index: 10000;
        }

        .avatar-dropdown.show {
            display: block;
        }

        /* Ensure proper stacking context */
        body {
            position: relative;
            z-index: 1;
        }

        .wrapper {
            position: relative;
            z-index: 1;
        }

        .main-content {
            position: relative;
            z-index: 1;
        }

        .greeting-section {
            position: relative;
            z-index: 2;
        }

        .greeting-actions {
            position: relative;
            z-index: 9999;
        }

        /* Rest of the dropdown styles remain the same */
        .dropdown-header {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .user-name {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .user-role {
            display: block;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 4px 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .dropdown-item:hover {
            background-color: #f3f4f6;
        }

        .dropdown-item i {
            font-size: 1rem;
            width: 16px;
            color: #6b7280;
        }

        .dropdown-item.logout {
            color: #ef4444;
        }

        .dropdown-item.logout i {
            color: #ef4444;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Ensure the parent containers don't clip the dropdown */
        .greeting-actions {
            position: relative;
            z-index: 9999;
        }

        .greeting-section {
            position: relative;
            z-index: 9998;
        }

        .main-content {
            position: relative;
            z-index: 1; /* Lower z-index for main content */
        }
        /* Chat Widget Styles */
.chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}

.chat-container {
    position: fixed;
    bottom: 80px;
    right: 20px;
    width: 350px;
    height: 500px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.15);
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e5e7eb;
}

.chat-container.active {
    display: flex;
}

.chat-header {
    padding: 16px;
    background: #4F46E5;
    color: white;
}

.chat-tabs {
    display: flex;
    gap: 16px;
    margin-bottom: 8px;
}

.chat-tab {
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.chat-tab.active {
    background: rgba(255,255,255,0.1);
}

.chat-actions {
    display: flex;
    justify-content: flex-end;
}

.create-group-btn {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: #f9fafb;
}

.message-box {
    padding: 12px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 8px;
    align-items: center;
    background: white;
}

.file-attach-btn {
    cursor: pointer;
    color: #6b7280;
    padding: 4px;
}

.message-input {
    flex: 1;
    border: 1px solid #e5e7eb;
    padding: 8px 12px;
    border-radius: 20px;
    outline: none;
}

.send-button {
    background: none;
    border: none;
    color: #4F46E5;
    cursor: pointer;
    padding: 4px;
}

.chat-button {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #4F46E5;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    transition: transform 0.2s;
}

.chat-button:hover {
    transform: scale(1.1);
}

.unread-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

/* Chat List Styles */
.user-list, .group-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.user-item, .group-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: background-color 0.2s;
}

.user-item:hover, .group-item:hover {
    background: #f3f4f6;
}

.user-content, .group-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.user-avatar, .group-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.unread-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    background: #ef4444;
    border-radius: 50%;
    border: 2px solid white;
}

.user-info, .group-info {
    flex: 1;
}

.user-name, .group-name {
    font-weight: 500;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 4px;
}

.user-status, .group-role {
    font-size: 0.875rem;
    color: #6b7280;
}

.unread-count {
    font-size: 0.75rem;
    color: #ef4444;
}

/* Message Styles */
.messages-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.message {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-width: 80%;
}

.message.received {
    align-self: flex-start;
}

.message.sent {
    align-self: flex-end;
}

.message-content {
    padding: 8px 12px;
    border-radius: 12px;
    background: white;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.message.sent .message-content {
    background: #4F46E5;
    color: white;
}

.message-time {
    font-size: 0.75rem;
    color: #6b7280;
    align-self: flex-end;
}

/* File Attachment Styles */
.file-attachment {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: rgba(0,0,0,0.05);
    border-radius: 8px;
    margin-top: 4px;
}

.file-icon {
    color: #6b7280;
}

.file-name {
    font-size: 0.875rem;
    color: #374151;
}

/* Group Actions */
.group-actions {
    display: flex;
    gap: 4px;
}

.group-action-btn {
    background: none;
    border: none;
    color: #6b7280;
    padding: 4px;
    cursor: pointer;
    border-radius: 4px;
}

.group-action-btn:hover {
    background: #f3f4f6;
}

.group-action-btn.edit:hover {
    color: #4F46E5;
}

.group-action-btn.delete:hover {
    color: #ef4444;
}

/* ... existing code ... */

/* Task Overview Section Enhancements */
.task-overview-section {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid rgba(99, 102, 241, 0.1);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

/* Metrics Card Enhancements */
.metrics-card {
    background: linear-gradient(145deg, #ffffff 0%, #ffffff 100%);
    border: none;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

/* Card-specific gradients and colors */
.metrics-card.stages-pending {
    background: linear-gradient(145deg, #ffffff 0%, #eff6ff 100%);
    border-left: 4px solid #3b82f6;
}

.metrics-card.substages-pending {
    background: linear-gradient(145deg, #ffffff 0%, #faf5ff 100%);
    border-left: 4px solid #8b5cf6;
}

.metrics-card.active-stages {
    background: linear-gradient(145deg, #ffffff 0%, #f0fdf4 100%);
    border-left: 4px solid #22c55e;
}

.metrics-card.upcoming-deadlines {
    background: linear-gradient(145deg, #ffffff 0%, #fff7ed 100%);
    border-left: 4px solid #f97316;
}

/* Enhanced Recent Tasks Card */
.recent-tasks-card {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid rgba(99, 102, 241, 0.1);
}

/* Task Item Enhancements */
.task-item {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.task-item:hover {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-color: rgba(99, 102, 241, 0.2);
}

/* Enhanced Status Badges */
.task-status {
    font-weight: 600;
    padding: 0.35rem 1rem;
}

.task-status.completed {
    background: linear-gradient(145deg, #dcfce7 0%, #bbf7d0 100%);
    color: #15803d;
}

.task-status.in-progress {
    background: linear-gradient(145deg, #dbeafe 0%, #bfdbfe 100%);
    color: #1d4ed8;
}

.task-status.delayed {
    background: linear-gradient(145deg, #fee2e2 0%, #fecaca 100%);
    color: #b91c1c;
}

.task-status.pending {
    background: linear-gradient(145deg, #fef3c7 0%, #fde68a 100%);
    color: #b45309;
}

/* Task Meta Information */
.task-meta {
    color: #64748b;
}

.task-deadline {
    color: #64748b;
    font-weight: 500;
}

/* Hover effects for interactive elements */
.recent-tasks-header select:hover {
    border-color: #6366f1;
}

.metrics-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

/* ... existing code ... */

/* View Toggle Styles */
.view-toggle-container {
    display: flex;
    justify-content: center;
    margin-bottom: 1.5rem;
    padding: 0.5rem;
}

.view-toggle {
    background: #f1f5f9;
    padding: 4px;
    border-radius: 12px;
    display: inline-flex;
    position: relative;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid rgba(99, 102, 241, 0.1);
}

.view-toggle:hover {
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.15);
}

.view-toggle-option {
    padding: 10px 28px;
    cursor: pointer;
    position: relative;
    z-index: 1;
    font-weight: 600;
    color: #64748b;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    border-radius: 8px;
}

.view-toggle-option i {
    font-size: 1.1rem;
    transition: transform 0.3s ease;
}

.view-toggle-option:hover i {
    transform: scale(1.1);
}

.view-toggle-option.active {
    color: #4f46e5; /* Indigo color for active state */
}

.view-toggle-slider {
    position: absolute;
    top: 4px;
    left: 4px;
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    border-radius: 8px;
    height: calc(100% - 8px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.25);
    width: calc(50% - 4px);
}

/* Slider positions */
.view-toggle[data-view="stats"] .view-toggle-slider {
    transform: translateX(0);
}

.view-toggle[data-view="board"] .view-toggle-slider {
    transform: translateX(calc(100% + 4px));
}

/* Active state styles */
.view-toggle-option.active {
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.view-toggle-option.active i {
    transform: scale(1.1);
    color: white;
}

/* Inactive option hover effect */
.view-toggle-option:not(.active):hover {
    color: #4f46e5;
}

/* Add subtle animation for option transitions */
@keyframes optionPop {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.view-toggle-option.active {
    animation: optionPop 0.3s ease;
}

/* Add glow effect on hover */
.view-toggle:hover .view-toggle-slider {
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4),
                0 0 0 1px rgba(99, 102, 241, 0.1);
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .view-toggle-option {
        padding: 8px 20px;
        font-size: 0.9rem;
    }
}

/* Board View Styles */
.board-view {
    display: none; /* Hidden by default */
    width: 100%;
    margin-top: 1rem;
}

.board-view.active {
    display: block; /* Show when active */
}

/* Task table container styles */
.task-table-container {
    width: 100%;
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    margin: 20px 0;
}

/* Remove any grid-related properties */
.task-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

/* Update the stages container to use flex instead of grid */
.stages-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 8px;
}

/* Stage block styles */
.stage-block {
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px;
    border: 1px solid #e2e8f0;
}

/* Remove any column-specific styles */
.board-column,
.board-column-header {
    display: none; /* Hide these elements as they're not needed */
}

.board-task {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
}

.board-task:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.board-task-title {
    font-weight: 500;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.board-task-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
    color: #64748b;
}

.board-task-assignee {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.board-task-deadline {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Stats View */
.stats-view {
    display: block;
}

.stats-view.hidden {
    display: none;
}

/* Update Recent Tasks card styles */
.recent-tasks-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
}

.recent-tasks-header {
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-tasks-list {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    /* Adjust max-height to match other cards */
    max-height: 300px; /* Reduced from 600px */
}

/* Make task items more compact */
.task-item {
    background: white;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
}

.task-header {
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
}

/* Make stages more compact */
.stages-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.stage-block {
    background: #f8fafc;
    border-radius: 6px;
    padding: 8px;
    border-left: 3px solid #6366f1;
}

/* Reduce spacing in stage header */
.stage-header {
    margin-bottom: 6px;
}

/* Make substages more compact */
.substages-list {
    margin-top: 6px;
    padding-left: 8px;
    border-left: 2px solid #e2e8f0;
}

.substage-item {
    padding: 6px;
    margin-bottom: 3px;
}

/* Adjust font sizes for better fit */
.task-title {
    font-size: 0.95rem;
}

.stage-number {
    font-size: 0.9rem;
}

.stage-meta, .substage-meta {
    font-size: 0.8rem;
}

/* Add custom scrollbar for better appearance */
.recent-tasks-list::-webkit-scrollbar {
    width: 4px;
}

.recent-tasks-list::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.recent-tasks-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.recent-tasks-list::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Add these styles to your CSS */
.board-filters {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.month-filter {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    color: #444;
    background-color: white;
    cursor: pointer;
    min-width: 200px;
}

.month-filter:focus {
    outline: none;
    border-color: #4a90e2;
    box-shadow: 0 0 0 2px rgba(74,144,226,0.2);
}

.loading-state,
.error-state,
.empty-state {
    text-align: center;
    padding: 20px;
    color: #666;
}

.error-state {
    color: #dc3545;
}

.empty-state {
    color: #6c757d;
}

// Add this PHP code before the metrics cards section
<?php
// Fetch stages pending data
$stagesQuery = "
    SELECT 
        ts.*, 
        t.title as task_title,
        u.username as assigned_user,
        u.unique_id as user_id
    FROM task_stages ts
    LEFT JOIN tasks t ON ts.task_id = t.id
    LEFT JOIN users u ON ts.assigned_to = u.id
    WHERE ts.status != 'completed'
    ORDER BY ts.due_date ASC";

$stmt = $pdo->prepare($stagesQuery);
$stmt->execute();
$pendingStages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total stages
$totalStagesQuery = "SELECT COUNT(*) FROM task_stages";
$stmt = $pdo->prepare($totalStagesQuery);
$stmt->execute();
$totalStages = $stmt->fetchColumn();

// Count pending stages
$pendingStagesCount = count($pendingStages);
?>

<!-- Update the Stages Pending metrics card with the fetched data -->
<div class="metrics-card stages-pending">
    <div class="employee-details-tooltip">
        <div class="tooltip-header">
            <i class="fas fa-layer-group"></i>
            Pending Stages Details
        </div>
        <?php if (!empty($pendingStages)): ?>
            <?php foreach ($pendingStages as $stage): ?>
                <div class="stage-item">
                    <div class="stage-info">
                        <span class="stage-label">
                            <i class="fas fa-tasks"></i>
                            <?php echo htmlspecialchars($stage['task_title']); ?>
                            <small>(Stage <?php echo htmlspecialchars($stage['stage_number']); ?>)</small>
                        </span>
                        <div class="stage-details">
                            <span class="assigned-to">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($stage['assigned_user']); ?> 
                                (<?php echo htmlspecialchars($stage['user_id']); ?>)
                            </span>
                            <span class="stage-deadline <?php echo strtotime($stage['due_date']) < time() ? 'overdue' : ''; ?>">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M d, Y', strtotime($stage['due_date'])); ?>
                            </span>
                            <span class="stage-priority <?php echo strtolower($stage['priority']); ?>">
                                <?php echo ucfirst($stage['priority']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-stages">No pending stages</div>
        <?php endif; ?>
    </div>
    <div class="metrics-icon">
        <i class="fas fa-layer-group"></i>
    </div>
    <div class="metrics-content">
        <h3>Stages Pending</h3>
        <div class="metrics-numbers">
            <span class="current"><?php echo $pendingStagesCount; ?></span>
            <span class="divider">/</span>
            <span class="total"><?php echo $totalStages; ?></span>
        </div>
        <p class="metrics-subtitle">Total Stages</p>
    </div>
</div>

/* Add these CSS styles for the new elements */
<style>
.stage-item {
    padding: 16px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    transition: all 0.2s ease;
    background: white;
}

/* Add gap after every second stage */
.stage-item:nth-child(2n) {
    border-bottom: 2px dashed rgba(99, 102, 241, 0.2);
    margin-bottom: 12px;
    padding-bottom: 20px;
}

/* Add a subtle background for better visual grouping */
.stage-item:nth-child(4n+1),
.stage-item:nth-child(4n+2) {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
}

/* Remove border from last item */
.stage-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

/* Add a subtle separator line */
.stage-item:nth-child(2n)::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -6px;
    height: 6px;
    background: linear-gradient(90deg, 
        rgba(99, 102, 241, 0.1) 0%, 
        rgba(99, 102, 241, 0.05) 50%,
        rgba(99, 102, 241, 0) 100%
    );
}

.stage-item:hover {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    transform: translateX(4px);
}


.stage-item:last-child {
    border-bottom: none;
}

.stage-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.stage-label {
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.05rem;
}

.stage-label i{
    color: #6366f1;
}

.stage-label small {
    color: #64748b;
    font-weight: 500;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
}

.stage-details {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 0.9rem;
    color: #64748b;
    padding: 4px 0;
}

.assigned-to {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 500;
}

.assigned-to i{
    color: #6366f1;
}

.stage-deadline {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 500;
}

.stage-deadline.overdue {
    color: #ef4444;
    background: #fee2e2;
}

.stage-deadline.overdue i {
    color: #ef4444;
}

.stage-priority {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stage-priority.high {
    background: linear-gradient(145deg, #fee2e2 0%, #fecaca 100%);
    color: #b91c1c;
}

.stage-priority.medium {
    background: linear-gradient(145deg, #fef3c7 0%, #fde68a 100%);
    color: #b45309;
}

.stage-priority.low {
    background: linear-gradient(145deg, #dcfce7 0%, #bbf7d0 100%);
    color: #15803d;
}

.no-stages {
    padding: 24px;
    text-align: center;
    color: #64748b;
    font-weight: 500;
    background: #f8fafc;
    border-radius: 8px;
    margin: 16px;
}

.tooltip-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #f8fafc;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    font-weight: 500;
    color: #1e293b;
}

.employee-details-tooltip {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 10;
    max-height: 400px;
    overflow-y: auto;
}

.metrics-card:hover .employee-details-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateY(10px);
}
/* Scrollbar styling */
.employee-details-tooltip::-webkit-scrollbar {
    width: 8px;
}

.employee-details-tooltip::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 8px;
}

.employee-details-tooltip::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 8px;
}

.employee-details-tooltip::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Enhanced hover effect */
.metrics-card:hover .employee-details-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

/* Animation for stage items */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.stage-item {
    animation: slideIn 0.3s ease forwards;
    animation-delay: calc(var(--item-index) * 0.05s);
}

/* Substage Item Styles */
.substage-item {
    padding: 16px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    transition: all 0.2s ease;
    background: white;
}

/* Add gap after every second substage */
.substage-item:nth-child(2n) {
    border-bottom: 2px dashed rgba(99, 102, 241, 0.2);
    margin-bottom: 12px;
    padding-bottom: 20px;
}

/* Subtle background for better visual grouping */
.substage-item:nth-child(4n+1),
.substage-item:nth-child(4n+2) {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
}

/* Remove border from last item */
.substage-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

/* Hover effect */
.substage-item:hover {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
}

/* Substage Info Layout */
.substage-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Substage Label Styles */
.substage-label {
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.05rem;
}

.substage-label i {
    color: #6366f1;
}

.substage-label small {
    color: #64748b;
    font-weight: 500;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
}

/* Substage Details Layout */
.substage-details {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.9rem;
    color: #64748b;
    padding: 4px 0;
}

/* Assigned User Style */
.substage-assigned-to {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 500;
}

.substage-assigned-to i {
    color: #6366f1;
}

/* Deadline Style */
.substage-deadline {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 500;
}

.substage-deadline.overdue {
    color: #ef4444;
    background: #fee2e2;
}

.substage-deadline.overdue i {
    color: #ef4444;
}

/* Priority Badges */
.substage-priority {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.substage-priority.high {
    background: linear-gradient(145deg, #fee2e2 0%, #fecaca 100%);
    color: #b91c1c;
}

.substage-priority.medium {
    background: linear-gradient(145deg, #fef3c7 0%, #fde68a 100%);
    color: #b45309;
}

.substage-priority.low {
    background: linear-gradient(145deg, #dcfce7 0%, #bbf7d0 100%);
    color: #15803d;
}

/* Description Style */
.substage-description {
    color: #64748b;
    font-size: 0.9rem;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
    margin-top: 4px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    line-height: 1.5;
}

.substage-description i {
    color: #6366f1;
    margin-top: 2px;
}

/* Empty State Style */
.no-substages {
    padding: 24px;
    text-align: center;
    color: #64748b;
    font-weight: 500;
    background: #f8fafc;
    border-radius: 8px;
    margin: 16px;
}

/* Tooltip Header Style */
.substages-pending .tooltip-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px;
    background: #f8fafc;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    font-weight: 600;
    color: #1e293b;
}

.substages-pending .tooltip-header i {
    color: #6366f1;
}

/* Scrollbar Styling */
.employee-details-tooltip::-webkit-scrollbar {
    width: 8px;
}

.employee-details-tooltip::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 8px;
}

.employee-details-tooltip::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 8px;
}

.employee-details-tooltip::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Animation for substage items */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.substage-item {
    animation: slideIn 0.3s ease forwards;
    animation-delay: calc(var(--item-index) * 0.05s);
}
/* Table Container Styles */
.task-table-container {
    overflow-x: auto;
    margin: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 1px;
    width: 100%;
}

/* Main Table Styles */
.task-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    font-size: 0.95rem;
}

/* Header Styles */
.task-table thead {
    background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
}

.task-table th {
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    position: sticky;
    top: 0;
    background: inherit;
    z-index: 10;
}

.task-table th:first-child {
    border-top-left-radius: 12px;
}

.task-table th:last-child {
    border-top-right-radius: 12px;
}

/* Cell Styles */
.task-table td {
    padding: 14px 16px;
    color: #334155;
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

/* Row Styles */
.task-table tbody tr {
    transition: all 0.2s ease;
}

.task-table tbody tr:hover {
    background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

/* Serial Number Column */
.task-table td:first-child {
    font-weight: 600;
    color: #6366f1;
    width: 80px;
}

/* Description Column */
.task-table td:nth-child(2) {
    max-width: 300px;
    line-height: 1.5;
}

/* Deadline Column Styles */
.deadline-cell {
    width: 100%; /* Changed from min-width/max-width to full width */
    padding: 0 16px;
}

.stage-deadline {
    margin: 12px 0;
    padding: 16px 20px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 3px solid #6366f1;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    width: 100%;
}

.stage-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.stage-title {
    font-weight: 600;
    color: #1e293b;
    font-size: 1.1em;
}

.stage-date {
    color: #64748b;
    font-size: 0.9em;
}

/* Substage List Styles */
.substage-list {
    margin: 8px 0 0 0;
    padding: 0;
    list-style: none;
    width: 100%;
}

.substage-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    margin: 4px 0;
    border-bottom: 1px dashed #e2e8f0;
}

.substage-list li:last-child {
    border-bottom: none;
}

.substage-name {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #475569;
}

.substage-name::before {
    content: '';
    width: 6px;
    height: 6px;
    background: #6366f1;
    border-radius: 50%;
    flex-shrink: 0;
}

.substage-date {
    color: #94a3b8;
    font-size: 0.9em;
    white-space: nowrap;
}

/* Column Width Adjustments */
.task-table td:first-child {
    width: 80px;
}

.task-table td:nth-child(2) {
    width: 200px;
}

.task-table td:nth-child(3) {
    width: auto; /* This will take up remaining space */
}

.task-table td:nth-child(4),
.task-table td:nth-child(5) {
    width: 150px;
    white-space: nowrap;
}

.task-table td:last-child {
    width: 120px;
}

.stage-deadline strong {
    color: #1e293b;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stage-deadline strong::after {
    content: attr(data-date);
    color: #64748b;
    font-weight: 500;
    font-size: 0.9em;
}

/* Substage List Styles */
.substage-list {
    margin: 4px 0 0 24px;
    padding-left: 16px;
    border-left: 2px solid #e2e8f0;
    list-style-type: none;
}

.substage-list li {
    margin: 6px 0;
    color: #64748b;
    font-size: 0.9em;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.substage-list li::before {
    content: '';
    width: 6px;
    height: 6px;
    background: #6366f1;
    border-radius: 50%;
    flex-shrink: 0;
}

.substage-list li .substage-date {
    color: #94a3b8;
    font-size: 0.9em;
    white-space: nowrap;
}

/* Status Badge Styles */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.pending {
    background: #fef2f2;
    color: #dc2626;
}

.status-badge.pending::before {
    background: #dc2626;
}

.status-badge.in_progress {
    background: #eff6ff;
    color: #2563eb;
}

.status-badge.in_progress::before {
    background: #2563eb;
}

.status-badge.completed {
    background: #f0fdf4;
    color: #16a34a;
}

.status-badge.completed::before {
    background: #16a34a;
}

/* Assignment Columns */
.task-table td:nth-child(4),
.task-table td:nth-child(5) {
    white-space: nowrap;
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .task-table-container {
        margin: 10px;
        border-radius: 8px;
    }

    .task-table td,
    .task-table th {
        padding: 12px;
    }

    .deadline-cell {
        min-width: 200px;
    }
}

/* Empty State Styles */
.task-table tbody:empty::after {
    content: 'No tasks available';
    display: block;
    text-align: center;
    padding: 40px;
    color: #94a3b8;
    font-style: italic;
}

/* Scrollbar Styling */
.task-table-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.task-table-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.task-table-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.task-table-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Animation for new rows */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.task-row {
    animation: fadeIn 0.3s ease forwards;
}

/* Hover effects for interactive elements */
.stage-deadline:hover {
    border-left-width: 5px;
    transform: translateX(2px);
}

.substage-list li:hover {
    color: #1e293b;
    transform: translateX(4px);
}

/* Print-friendly styles */
@media print {
    .task-table-container {
        box-shadow: none;
        margin: 0;
    }

    .task-table th {
        background: white !important;
        color: black;
    }

    .status-badge {
        border: 1px solid currentColor;
        background: none !important;
    }
}

.deadline-section {
    margin: 12px 0;
}

.deadline-section h4 {
    color: #1e293b;
    font-size: 0.9em;
    margin-bottom: 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid #e2e8f0;
}

.deadline-item {
    background: #f8fafc;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 8px;
    border-left: 3px solid #6366f1;
}

.deadline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.deadline-title {
    font-weight: 500;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 6px;
}

.deadline-priority {
    font-size: 0.8em;
    padding: 2px 8px;
    border-radius: 12px;
}

.deadline-priority.high {
    background: #fef2f2;
    color: #dc2626;
}

.deadline-priority.medium {
    background: #fff7ed;
    color: #ea580c;
}

.deadline-priority.low {
    background: #f0fdf4;
    color: #16a34a;
}

.deadline-description {
    color: #475569;
    font-size: 0.9em;
    margin: 4px 0;
}

.deadline-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85em;
    color: #64748b;
    margin-top: 8px;
}

.deadline-assignee, .deadline-time {
    display: flex;
    align-items: center;
    gap: 4px;
}

.no-deadlines {
    text-align: center;
    padding: 20px;
    color: #94a3b8;
    font-style: italic;
}

.view-toggle-container {
    margin: 20px 0;
    display: flex;
    justify-content: center;
}

.view-toggle {
    background: #f1f5f9;
    padding: 4px;
    border-radius: 30px;
    display: inline-flex;
    position: relative;
    gap: 4px;
}

.view-toggle-option {
    padding: 8px 16px;
    border-radius: 25px;
    cursor: pointer;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    transition: color 0.3s ease;
}

.view-toggle-option i {
    font-size: 16px;
}

.view-toggle-option span {
    font-size: 14px;
    font-weight: 500;
}

.view-toggle-option.active {
    color: #fff;
}

.view-toggle-slider {
    position: absolute;
    top: 4px;
    left: 4px;
    background: #6366f1;
    border-radius: 25px;
    transition: transform 0.3s ease;
    height: calc(100% - 8px);
    z-index: 0;
}

/* Add these classes to control slider position */
.view-toggle[data-view="stats"] .view-toggle-slider {
    width: calc(33.33% - 4px);
    transform: translateX(0);
}

.view-toggle[data-view="board"] .view-toggle-slider {
    width: calc(33.33% - 4px);
    transform: translateX(100%);
}

.view-toggle[data-view="calendar"] .view-toggle-slider {
    width: calc(33.33% - 4px);
    transform: translateX(200%);
}

<style>
.view-toggle-container {
    margin: 20px 0;
    display: flex;
    justify-content: center;
}

.view-toggle {
    background: rgba(99, 102, 241, 0.1); /* Light purple background */
    padding: 4px;
    border-radius: 50px;
    display: inline-flex;
    position: relative;
    gap: 4px;
    box-shadow: 0 2px 10px rgba(99, 102, 241, 0.1);
}

.view-toggle-option {
    padding: 8px 20px;
    border-radius: 50px;
    cursor: pointer;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6366f1; /* Purple text for inactive state */
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 500;
}

.view-toggle-option i {
    font-size: 16px;
}

.view-toggle-option.active {
    color: #ffffff; /* White text for active state */
    background: #6366f1; /* Purple background for active state */
}

/* Remove the slider since we're using background color on active state */
.view-toggle-slider {
    display: none;
}

/* Hover effect */
.view-toggle-option:hover:not(.active) {
    background: rgba(99, 102, 241, 0.1);
}
</style>
<style>
/* Add these styles to your existing CSS */
.studio-task-form__group select#studioProjectType {
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #ddd;
    width: 100%;
    font-size: 14px;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 16px;
    cursor: pointer;
}

/* Project Type Option Colors */
.project-type-architecture {
    background-color: #4CAF50 !important;
    color: white !important;
}

.project-type-interior {
    background-color: #2196F3 !important;
    color: white !important;
}

.project-type-construction {
    background-color: #FF9800 !important;
    color: white !important;
}

/* Selected Option Styling */
.studio-task-form__group select#studioProjectType option:checked {
    font-weight: bold;
}

/* Hover effect for options */
.studio-task-form__group select#studioProjectType option:hover {
    background-color: #f5f5f5;
}
</style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="fas fa-chevron-left"></i>
            </button>

            <!-- Add this button after the toggle button -->
            <button class="sidebar-task-btn" id="sidebarTaskCreationTrigger">
                <i class="fas fa-plus"></i>
                <span>Add Task</span>
            </button>

            <!-- Navigation Links -->
            <ul class="nav-links">
                <li class="active">
                    <a href="#" data-title="Dashboard">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="#" data-title="Team">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Team</span>
                    </a>
                </li>
                <li>
                    <a href="taskboard.php" data-title="Tasks">
                        <i class="fas fa-tasks"></i>
                        <span class="nav-text">Create Project</span>
                    </a>
                </li>
                <!-- Add Apply Leave option -->
                <li>
                    <a href="leave.php" data-title="Apply Leave">
                        <i class="fas fa-calendar-plus"></i>
                        <span class="nav-text">Apply Leave</span>
                    </a>
                </li>
                <li>
                    <a href="#" data-title="Leave Management">
                        <i class="fas fa-calendar"></i>
                        <span class="nav-text">Leave Management</span>
                    </a>
                </li>
                <li>
                    <a href="#" data-title="Reports">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li>
                    <a href="#" data-title="Settings">
                        <i class="fas fa-cog"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>
            </ul>

            <!-- Add Logout Button -->
            <button class="logout-btn">
                <a href="logout.php" data-title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </button>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <div class="greeting-section">
                <div class="greeting-content">
                    <h1><span id="timeGreeting">Good Morning</span>, <?php echo htmlspecialchars($userName); ?>!</h1>
                    <p class="datetime">
                        <i class="far fa-clock"></i>
                        <span id="currentTime">00:00:00</span> | 
                        <span id="currentDate">Date</span>
                    </p>
                </div>
                
                <div class="greeting-actions">
                    <!-- Add Task Button with unique ID -->
                    <button class="studio-task-creation-btn" id="studioTaskCreationTrigger">
                        <i class="fas fa-plus"></i>
                        <span>Add Task</span>
                    </button>

                    <!-- Punch In/Out Button -->
                    <button class="punch-btn" id="punchButton">
                        <i class="fas fa-fingerprint"></i>
                        <span>Punch In</span>
                    </button>

                    <!-- Notification Icon -->
                    <div class="notification-icon" id="notificationIcon">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationCount">2</span>
                    </div>

                    <!-- Avatar with Dropdown Menu -->
                    <div class="avatar-container">
                        <div class="avatar" id="avatarButton">
                            <?php echo strtoupper(substr($userName, 0, 1)); ?>
                        </div>
                        <div class="avatar-dropdown" id="avatarDropdown">
                            <div class="dropdown-header">
                                <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                                <span class="user-role">Studio Manager</span>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                Profile
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Overview Section -->
            <div class="employee-metrics-section">
                <h2 class="section-title">
                    <i class="fas fa-users"></i>
                    Employees Overview
                </h2>
                <div class="employee-metrics-cards">
                    <!-- Left Column -->
                    <div class="metrics-column">
                        <!-- Present Today Card -->
                        <div class="metrics-card attendance">
                            <div class="employee-details-tooltip">
                                <div class="tooltip-header">
                                    <i class="fas fa-user-check"></i>
                                    Present Employees
                                </div>
                                <?php
                                $presentEmployees = getPresentEmployees($pdo);
                                if (!empty($presentEmployees)) {
                                    foreach ($presentEmployees as $employee) {
                                        echo '<div class="employee-item">';
                                        echo '<div class="employee-info">';
                                        echo '<span class="employee-label">';
                                        echo '<i class="fas fa-id-badge"></i> ';
                                        echo htmlspecialchars($employee['username']) . ' ';
                                        echo '<small>(' . htmlspecialchars($employee['unique_id']) . ')</small>';
                                        echo '</span>';
                                        echo '<span class="employee-count">' . date('h:i A', strtotime($employee['punch_in'])) . '</span>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="employee-item"><div class="employee-info">No employees present yet.</div></div>';
                                }
                                ?>
                            </div>
                            <div class="metrics-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="metrics-content">
                                <h3>Present Today</h3>
                                <div class="metrics-numbers">
                                    <span class="current"><?php echo count($presentEmployees); ?></span>
                                    <span class="divider">/</span>
                                    <span class="total"><?php echo $totalEmployees; ?></span>
                                </div>
                                <p class="metrics-subtitle">Total Employees</p>
                            </div>
                        </div>

                        <!-- Pending Leaves Card -->
                        <div class="metrics-card pending">
                            <div class="employee-details-tooltip">
                                <div class="tooltip-header">
                                    <i class="fas fa-hourglass-half"></i>
                                    Pending Leave Requests
                                </div>
                                <?php
                                // Direct SQL query to fetch pending leaves
                                $query = "SELECT lr.*, u.username 
                                         FROM leave_request lr
                                         JOIN users u ON lr.user_id = u.id
                                         WHERE lr.status = 'pending'
                                         ORDER BY lr.created_at DESC";
                                
                                $stmt = $pdo->prepare($query);
                                $stmt->execute();
                                $pendingLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($pendingLeaves)) {
                                    foreach ($pendingLeaves as $leave) {
                                        echo '<div class="pending-leave-item">';
                                        echo '<div class="leave-user-info">';
                                        echo '<span class="leave-user-name">';
                                        echo '<i class="fas fa-user"></i> ';
                                        echo htmlspecialchars($leave['username']);
                                        echo '</span>';
                                        echo '<span class="leave-type">' . htmlspecialchars($leave['leave_type']) . '</span>';
                                        echo '</div>';
                                        echo '<div class="leave-details">';
                                        echo '<div class="leave-dates">';
                                        echo '<i class="fas fa-calendar"></i> ';
                                        echo date('M d, Y', strtotime($leave['start_date']));
                                        if ($leave['start_date'] !== $leave['end_date']) {
                                            echo ' - ' . date('M d, Y', strtotime($leave['end_date']));
                                        }
                                        echo ' (' . htmlspecialchars($leave['duration']) . ' days)';
                                        echo '</div>';
                                        echo '<div class="leave-reason">';
                                        echo '<i class="fas fa-comment"></i> ';
                                        echo htmlspecialchars($leave['reason']);
                                        echo '</div>';
                                        echo '</div>';
                                        echo '<div class="leave-actions">';
                                        echo '<button class="leave-action-btn leave-accept-btn" onclick="handleLeave(' . $leave['id'] . ', \'approve\', this)">Approve</button>';
                                        echo '<button class="leave-action-btn leave-reject-btn" onclick="handleLeave(' . $leave['id'] . ', \'reject\', this)">Reject</button>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="employee-item"><div class="employee-info">No pending leave requests</div></div>';
                                }
                                ?>
                            </div>
                            <div class="metrics-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="metrics-content">
                                <h3>Pending Leaves</h3>
                                <div class="metrics-numbers">
                                    <span class="current"><?php echo count($pendingLeaves); ?></span>
                                </div>
                                <p class="metrics-subtitle">Awaiting Approval</p>
                            </div>
                        </div>
                    </div>

                    <!-- Middle Column -->
                    <div class="metrics-column">
                        <!-- On Leave Card -->
                        <div class="metrics-card leaves">
                            <div class="employee-details-tooltip">
                                <div class="tooltip-header">
                                    <i class="fas fa-calendar-times"></i>
                                    Employees on Leave
                                </div>
                                <?php
                                // Fetch current leaves from leave_request table
                                $currentLeavesQuery = "
                                    SELECT lr.*, u.username, u.unique_id 
                                    FROM leave_request lr
                                    JOIN users u ON lr.user_id = u.id
                                    WHERE lr.status = 'approved'
                                    AND lr.start_date <= CURRENT_DATE 
                                    AND lr.end_date >= CURRENT_DATE
                                    ORDER BY lr.start_date ASC";
                                
                                $stmt = $pdo->prepare($currentLeavesQuery);
                                $stmt->execute();
                                $currentLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($currentLeaves)) {
                                    foreach ($currentLeaves as $leave) {
                                        echo '<div class="employee-item">';
                                        echo '<div class="employee-info">';
                                        echo '<span class="employee-label">';
                                        echo '<i class="fas fa-user"></i> ';
                                        echo htmlspecialchars($leave['username']) . 
                                             ' <small>(' . htmlspecialchars($leave['unique_id']) . ')</small>';
                                        echo '</span>';
                                        echo '<span class="employee-count">';
                                        // Format dates
                                        $startDate = date('M d', strtotime($leave['start_date']));
                                        $endDate = date('M d', strtotime($leave['end_date']));
                                        echo $startDate === $endDate ? $startDate : "$startDate - $endDate";
                                        echo '</span>';
                                        echo '</div>';
                                        
                                        // Add leave details
                                        echo '<div class="leave-details">';
                                        echo '<div class="leave-type-badge">' . 
                                             htmlspecialchars(ucfirst($leave['leave_type'])) . 
                                             ' (' . htmlspecialchars($leave['duration']) . ' days)</div>';
                                        if (!empty($leave['reason'])) {
                                            echo '<div class="leave-reason">';
                                            echo '<i class="fas fa-comment-alt"></i> ';
                                            echo htmlspecialchars($leave['reason']);
                                            echo '</div>';
                                        }
                                        // Add approval details
                                        echo '<div class="approval-info">';
                                        if ($leave['manager_approval'] === 'approved') {
                                            echo '<span class="approval-badge manager">Manager Approved</span>';
                                        }
                                        if ($leave['hr_approval'] === 'approved') {
                                            echo '<span class="approval-badge hr">HR Approved</span>';
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                    }

                                    // Add CSS for new elements
                                    echo '<style>
                                        .leave-type-badge {
                                            display: inline-block;
                                            background: #e5e7eb;
                                            padding: 2px 8px;
                                            border-radius: 12px;
                                            font-size: 0.8em;
                                            margin: 4px 0;
                                        }
                                        .approval-info {
                                            margin-top: 4px;
                                            font-size: 0.8em;
                                        }
                                        .approval-badge {
                                            display: inline-block;
                                            padding: 2px 6px;
                                            border-radius: 4px;
                                            margin-right: 4px;
                                            font-size: 0.85em;
                                        }
                                        .approval-badge.manager {
                                            background: #10B981;
                                            color: white;
                                        }
                                        .approval-badge.hr {
                                            background: #6366F1;
                                            color: white;
                                        }
                                        .leave-reason {
                                            color: #6B7280;
                                            font-size: 0.9em;
                                            margin-top: 4px;
                                        }
                                        .leave-reason i {
                                            color: #9CA3AF;
                                        }
                                    </style>';
                                } else {
                                    echo '<div class="employee-item"><div class="employee-info">No employees currently on leave</div></div>';
                                }

                                // Count total current leaves for the metrics
                                $totalCurrentLeaves = count($currentLeaves);
                                ?>
                            </div>
                            <div class="metrics-icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <div class="metrics-content">
                                <h3>On Leave</h3>
                                <div class="metrics-numbers">
                                    <span class="current"><?php echo $totalCurrentLeaves; ?></span>
                                    <span class="divider">/</span>
                                    <span class="total"><?php echo $totalEmployees; ?></span>
                                </div>
                                <p class="metrics-subtitle">Full Day Leave</p>
                            </div>
                        </div>

                        <!-- Short Leave Card -->
                        <div class="metrics-card short-leaves">
                            <div class="employee-details-tooltip">
                                <div class="tooltip-header">
                                    <i class="far fa-clock"></i>
                                    Today's Short Leaves
                                </div>
                                <?php
                                // Fetch today's short leaves
                                $shortLeavesQuery = "
                                    SELECT lr.*, u.username, u.unique_id, lt.name as leave_type_name, lt.color_code
                                    FROM leave_request lr
                                    JOIN users u ON lr.user_id = u.id
                                    JOIN leave_types lt ON lr.leave_type = lt.name
                                    WHERE lr.status = 'approved'
                                    AND lr.duration <= 1
                                    AND DATE(lr.start_date) = CURRENT_DATE
                                    AND lt.name LIKE '%short%'
                                    ORDER BY lr.start_date ASC";
                                
                                $stmt = $pdo->prepare($shortLeavesQuery);
                                $stmt->execute();
                                $shortLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($shortLeaves)) {
                                    foreach ($shortLeaves as $leave) {
                                        echo '<div class="employee-item">';
                                        echo '<div class="employee-info">';
                                        echo '<span class="employee-label">';
                                        echo '<i class="fas fa-user"></i> ';
                                        echo htmlspecialchars($leave['username']) . 
                                             ' <small>(' . htmlspecialchars($leave['unique_id']) . ')</small>';
                                        echo '</span>';
                                        echo '<span class="employee-time">';
                                        // Format time
                                        $startTime = date('h:i A', strtotime($leave['start_date']));
                                        $endTime = date('h:i A', strtotime($leave['end_date']));
                                        echo "$startTime - $endTime";
                                        echo '</span>';
                                        echo '</div>';
                                        
                                        // Add leave details
                                        echo '<div class="leave-details">';
                                        echo '<div class="leave-type-badge" style="background-color: ' . 
                                             htmlspecialchars($leave['color_code']) . '20; color: ' . 
                                             htmlspecialchars($leave['color_code']) . ';">' . 
                                             htmlspecialchars($leave['leave_type_name']) . '</div>';
                                        
                                        if (!empty($leave['reason'])) {
                                            echo '<div class="leave-reason">';
                                            echo '<i class="fas fa-comment-alt"></i> ';
                                            echo htmlspecialchars($leave['reason']);
                                            echo '</div>';
                                        }
                                        
                                        // Add approval details
                                        echo '<div class="approval-info">';
                                        if ($leave['manager_approval'] === 'approved') {
                                            echo '<span class="approval-badge manager">Manager Approved</span>';
                                        }
                                        if ($leave['hr_approval'] === 'approved') {
                                            echo '<span class="approval-badge hr">HR Approved</span>';
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                    }

                                    // Add CSS for new elements
                                    echo '<style>
                                        .employee-time {
                                            font-size: 0.85em;
                                            color: #6B7280;
                                            font-weight: 500;
                                        }
                                        .leave-type-badge {
                                            display: inline-block;
                                            padding: 2px 8px;
                                            border-radius: 12px;
                                            font-size: 0.8em;
                                            margin: 4px 0;
                                            font-weight: 500;
                                        }
                                        .approval-info {
                                            margin-top: 4px;
                                            font-size: 0.8em;
                                        }
                                        .approval-badge {
                                            display: inline-block;
                                            padding: 2px 6px;
                                            border-radius: 4px;
                                            margin-right: 4px;
                                            font-size: 0.85em;
                                        }
                                        .approval-badge.manager {
                                            background: #10B981;
                                            color: white;
                                        }
                                        .approval-badge.hr {
                                            background: #6366F1;
                                            color: white;
                                        }
                                        .leave-reason {
                                            color: #6B7280;
                                            font-size: 0.9em;
                                            margin-top: 4px;
                                        }
                                        .leave-reason i {
                                            color: #9CA3AF;
                                        }
                                    </style>';
                                } else {
                                    echo '<div class="employee-item"><div class="employee-info">No short leaves for today</div></div>';
                                }

                                // Count total short leaves for today
                                $totalShortLeaves = count($shortLeaves);
                                ?>
                            </div>
                            <div class="metrics-icon">
                                <i class="far fa-clock"></i>
                            </div>
                            <div class="metrics-content">
                                <h3>Short Leave</h3>
                                <div class="metrics-numbers">
                                    <span class="current"><?php echo $totalShortLeaves; ?></span>
                                    <span class="divider">/</span>
                                    <span class="total"><?php echo $totalEmployees; ?></span>
                                </div>
                                <p class="metrics-subtitle">Today's Short Leaves</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Weekly Off & Leave Board -->
                    <div class="metrics-column">
                        <div class="whiteboard-card">
                            <div class="whiteboard-header">
                                <h4>Team Availability Board</h4>
                                <div class="whiteboard-filters">
                                    <select id="filterDepartment">
                                        <option value="">All Departments</option>
                                        <!-- Populate departments dynamically -->
                                    </select>
                                    <select id="filterWeek">
                                        <option value="current">Current Week</option>
                                        <option value="next">Next Week</option>
                                        <option value="previous">Previous Week</option>
                                    </select>
                                </div>
                            </div>
                            <div class="whiteboard-body">
                                <div class="whiteboard-legend">
                                    <span class="legend-item weekly-off">Weekly Off</span>
                                    <span class="legend-item on-leave">On Leave</span>
                                    <span class="legend-item short-leave">Short Leave</span>
                                    <span class="legend-item upcoming-leave">Upcoming Leave</span>
                                </div>
                                <div class="whiteboard-content" id="teamAvailabilityBoard">
                                    <!-- Content will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Overview Section -->
            <div class="task-overview-section">
                <h2 class="section-title">
                    <i class="fas fa-tasks"></i>
                    Task Overview
                </h2>

                <div class="view-toggle-container">
                    <div class="view-toggle">
                        <div class="view-toggle-option active" data-view="stats">
                            <i class="fas fa-chart-bar"></i>
                            <span>Stats</span>
                        </div>
                        <div class="view-toggle-option" data-view="board">
                            <i class="fas fa-columns"></i>
                            <span>Board</span>
                        </div>
                        <div class="view-toggle-option" data-view="calendar">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Calendar</span>
                        </div>
                        <div class="view-toggle-slider"></div>
                    </div>
                </div>

                <!-- Add the calendar view container -->
                <div class="calendar-view" style="display: none;">
                    <div id="taskCalendarContainer"></div>
                </div>

                <div class="stats-view">
                    <div class="task-overview-grid">
                        <!-- Left Column -->
                        <div class="metrics-column">
                            <!-- Stages Pending -->
                            <!-- Update the Stages Pending metrics card with the fetched data -->
<div class="metrics-card stages-pending">
    <div class="employee-details-tooltip">
        <div class="tooltip-header">
            <i class="fas fa-layer-group"></i>
            Pending Stages Details
        </div>
        <?php if (!empty($pendingStages)): ?>
            <?php foreach ($pendingStages as $stage): ?>
                <div class="stage-item">
                    <div class="stage-info">
                        <span class="stage-label">
                            <i class="fas fa-tasks"></i>
                            <?php echo htmlspecialchars($stage['task_title']); ?>
                            <small>(Stage <?php echo htmlspecialchars($stage['stage_number']); ?>)</small>
                        </span>
                        <div class="stage-details">
                            <span class="assigned-to">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($stage['assigned_user']); ?> 
                                (<?php echo htmlspecialchars($stage['user_id']); ?>)
                            </span>
                            <span class="stage-deadline <?php echo strtotime($stage['due_date']) < time() ? 'overdue' : ''; ?>">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M d, Y', strtotime($stage['due_date'])); ?>
                            </span>
                            <span class="stage-priority <?php echo strtolower($stage['priority']); ?>">
                                <?php echo ucfirst($stage['priority']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-stages">No pending stages</div>
        <?php endif; ?>
    </div>
    <div class="metrics-icon">
        <i class="fas fa-layer-group"></i>
    </div>
    <div class="metrics-content">
        <h3>Stages Pending</h3>
        <div class="metrics-numbers">
            <span class="current"><?php echo $pendingStagesCount; ?></span>
            <span class="divider">/</span>
            <span class="total"><?php echo $totalStages; ?></span>
        </div>
        <p class="metrics-subtitle">Total Stages</p>
    </div>
</div>

                            <!-- Active Stages -->
                            <div class="metrics-card active-stages">
                                <div class="employee-details-tooltip">
                                    <div class="tooltip-header">
                                        <i class="fas fa-play-circle"></i>
                                        Active Stages Details
                                    </div>
                                    <?php
                                    // First, let's check if there are any in_progress stages
                                    $debugQuery = "
                                        SELECT COUNT(*) as debug_count 
                                        FROM task_status_history 
                                        WHERE entity_type = 'stage' 
                                        AND new_status = 'in_progress'";
                                    $debugStmt = $pdo->prepare($debugQuery);
                                    $debugStmt->execute();
                                    $debugResult = $debugStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    // Main query to fetch active stages
                                    $activeStagesQuery = "
                                        SELECT 
                                            tsh.*, 
                                            ts.stage_number,
                                            ts.due_date,
                                            ts.priority,
                                            t.description as task_description,
                                            u1.username as assigned_user,
                                            u2.username as changed_by_user
                                        FROM task_status_history tsh
                                        JOIN task_stages ts ON tsh.entity_id = ts.id
                                        JOIN tasks t ON ts.task_id = t.id
                                        JOIN users u1 ON ts.assigned_to = u1.id
                                        JOIN users u2 ON tsh.changed_by = u2.id
                                        WHERE tsh.entity_type = 'stage' 
                                        AND tsh.new_status = 'in_progress'
                                        AND NOT EXISTS (
                                            SELECT 1 
                                            FROM task_status_history tsh2 
                                            WHERE tsh2.entity_id = tsh.entity_id 
                                            AND tsh2.entity_type = 'stage'
                                            AND tsh2.id > tsh.id
                                        )
                                        ORDER BY tsh.changed_at DESC";
                                    
                                    try {
                                        $stmt = $pdo->prepare($activeStagesQuery);
                                        $stmt->execute();
                                        $activeStages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        $activeStagesCount = count($activeStages);
                                        
                                        // Debug information
                                        echo "<!-- Debug Info: Total in_progress records: " . $debugResult['debug_count'] . " -->";
                                        echo "<!-- Debug Info: Active stages found: " . $activeStagesCount . " -->";
                                        
                                    } catch (PDOException $e) {
                                        echo "<!-- Debug Info: Query error: " . $e->getMessage() . " -->";
                                        $activeStages = [];
                                        $activeStagesCount = 0;
                                    }

                                    if (!empty($activeStages)): ?>
                                        <?php foreach ($activeStages as $stage): ?>
                                            <div class="stage-item">
                                                <div class="stage-info">
                                                    <span class="stage-label">
                                                        <i class="fas fa-tasks"></i>
                                                        <?php echo htmlspecialchars($stage['task_description']); ?>
                                                        <small>(Stage <?php echo htmlspecialchars($stage['stage_number']); ?>)</small>
                                                    </span>
                                                    <div class="stage-details">
                                                        <span class="assigned-to">
                                                            <i class="fas fa-user"></i>
                                                            <?php echo htmlspecialchars($stage['assigned_user']); ?>
                                                        </span>
                                                        <span class="stage-deadline <?php echo strtotime($stage['due_date']) < time() ? 'overdue' : ''; ?>">
                                                            <i class="fas fa-calendar"></i>
                                                            <?php echo date('M d, Y', strtotime($stage['due_date'])); ?>
                                                        </span>
                                                        <span class="stage-priority <?php echo strtolower($stage['priority']); ?>">
                                                            <?php echo ucfirst($stage['priority']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="stage-meta">
                                                        <span class="status-changed">
                                                            <i class="fas fa-history"></i>
                                                            Status changed by <?php echo htmlspecialchars($stage['changed_by_user']); ?>
                                                            on <?php echo date('M d, Y H:i', strtotime($stage['changed_at'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-stages">No active stages</div>
                                    <?php endif; ?>
                                </div>
                                <div class="metrics-icon">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                                <div class="metrics-content">
                                    <h3>Active Stages</h3>
                                    <div class="metrics-numbers">
                                        <span class="current"><?php echo $activeStagesCount; ?></span>
                                    </div>
                                    <p class="metrics-subtitle">Currently in Progress</p>
                                </div>
                            </div>
                        </div>

                        <!-- Middle Column -->
                        <div class="metrics-column">
                            <!-- Substages Pending -->
                            <div class="metrics-card substages-pending">
                                <div class="employee-details-tooltip">
                                    <div class="tooltip-header">
                                        <i class="fas fa-tasks"></i>
                                        Pending Substages Details
                                    </div>
                                    <?php
                                    // Fetch only substages data with specified columns
                                    $substagesQuery = "
                                        SELECT 
                                            id,
                                            stage_id,
                                            description,
                                            status,
                                            created_at,
                                            updated_at,
                                            priority,
                                            start_date,
                                            end_date
                                        FROM task_substages 
                                        WHERE status != 'completed'
                                        ORDER BY end_date ASC";

                                    $stmt = $pdo->prepare($substagesQuery);
                                    $stmt->execute();
                                    $pendingSubstages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Count total substages
                                    $totalSubstagesQuery = "SELECT COUNT(*) FROM task_substages";
                                    $stmt = $pdo->prepare($totalSubstagesQuery);
                                    $stmt->execute();
                                    $totalSubstages = $stmt->fetchColumn();

                                    if (!empty($pendingSubstages)): ?>
                                        <?php foreach ($pendingSubstages as $substage): ?>
                                            <div class="substage-item">
                                                <div class="substage-info">
                                                    <div class="substage-details">
                                                        <span class="substage-id">
                                                            <i class="fas fa-hashtag"></i>
                                                            ID: <?php echo htmlspecialchars($substage['id']); ?>
                                                        </span>
                                                        <span class="substage-stage">
                                                            <i class="fas fa-layer-group"></i>
                                                            Stage ID: <?php echo htmlspecialchars($substage['stage_id']); ?>
                                                        </span>
                                                        <span class="substage-priority <?php echo strtolower($substage['priority']); ?>">
                                                            <?php echo ucfirst($substage['priority']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="substage-description">
                                                        <i class="fas fa-info-circle"></i>
                                                        <?php echo htmlspecialchars($substage['description']); ?>
                                                    </div>
                                                    <div class="substage-timeline">
                                                        <span class="substage-date">
                                                            <i class="fas fa-calendar-plus"></i>
                                                            Start: <?php echo date('M d, Y', strtotime($substage['start_date'])); ?>
                                                        </span>
                                                        <span class="substage-date <?php echo strtotime($substage['end_date']) < time() ? 'overdue' : ''; ?>">
                                                            <i class="fas fa-calendar-check"></i>
                                                            End: <?php echo date('M d, Y', strtotime($substage['end_date'])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="substage-meta">
                                                        <span class="substage-status">
                                                            <i class="fas fa-info-circle"></i>
                                                            Status: <?php echo ucfirst($substage['status']); ?>
                                                        </span>
                                                        <span class="substage-created">
                                                            <i class="fas fa-clock"></i>
                                                            Created: <?php echo date('M d, Y', strtotime($substage['created_at'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-substages">No pending substages</div>
                                    <?php endif; ?>
                                </div>
                                <div class="metrics-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div class="metrics-content">
                                    <h3>Substages Pending</h3>
                                    <div class="metrics-numbers">
                                        <span class="current"><?php echo count($pendingSubstages); ?></span>
                                        <span class="divider">/</span>
                                        <span class="total"><?php echo $totalSubstages; ?></span>
                                    </div>
                                    <p class="metrics-subtitle">Total Substages</p>
                                </div>
                            </div>

                            <!-- Upcoming Deadlines -->
                            <div class="metrics-card upcoming-deadlines">
                                <div class="employee-details-tooltip">
                                    <div class="tooltip-header">
                                        <i class="fas fa-clock"></i>
                                        Upcoming Deadlines (Next 48 Hours)
                                    </div>
                                    <?php
                                    // Calculate the date range
                                    $now = new DateTime();
                                    $future = new DateTime('+48 hours');
                                    
                                    // Query for upcoming stage deadlines
                                    $stageQuery = "
                                        SELECT 
                                            ts.id,
                                            ts.stage_number,
                                            t.description as task_description,
                                            ts.due_date,
                                            ts.priority,
                                            ts.status,
                                            u.username as assigned_to
                                        FROM task_stages ts
                                        JOIN tasks t ON ts.task_id = t.id
                                        JOIN users u ON ts.assigned_to = u.id
                                        WHERE ts.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 48 HOUR)
                                        AND ts.status != 'completed'
                                    ";
                                    
                                    // Query for upcoming substage deadlines
                                    $substageQuery = "
                                        SELECT 
                                            tsub.id,
                                            tsub.description,
                                            tsub.end_date as due_date,
                                            tsub.priority,
                                            tsub.status,
                                            ts.stage_number,
                                            u.username as assigned_to
                                        FROM task_substages tsub
                                        JOIN task_stages ts ON tsub.stage_id = ts.id
                                        JOIN users u ON ts.assigned_to = u.id
                                        WHERE tsub.end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 48 HOUR)
                                        AND tsub.status != 'completed'
                                    ";

                                    $stmt = $pdo->prepare($stageQuery);
                                    $stmt->execute();
                                    $upcomingStages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    $stmt = $pdo->prepare($substageQuery);
                                    $stmt->execute();
                                    $upcomingSubstages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    $totalUpcoming = count($upcomingStages) + count($upcomingSubstages);

                                    if ($totalUpcoming > 0): ?>
                                        <!-- Stages Section -->
                                        <?php if (!empty($upcomingStages)): ?>
                                            <div class="deadline-section">
                                                <h4>Upcoming Stages</h4>
                                                <?php foreach ($upcomingStages as $stage): ?>
                                                    <div class="deadline-item">
                                                        <div class="deadline-header">
                                                            <span class="deadline-title">
                                                                <i class="fas fa-layer-group"></i>
                                                                Stage <?php echo htmlspecialchars($stage['stage_number']); ?>
                                                            </span>
                                                            <span class="deadline-priority <?php echo strtolower($stage['priority']); ?>">
                                                                <?php echo ucfirst($stage['priority']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="deadline-details">
                                                            <p class="deadline-description">
                                                                <?php echo htmlspecialchars($stage['task_description']); ?>
                                                            </p>
                                                            <div class="deadline-meta">
                                                                <span class="deadline-assignee">
                                                                    <i class="fas fa-user"></i>
                                                                    <?php echo htmlspecialchars($stage['assigned_to']); ?>
                                                                </span>
                                                                <span class="deadline-time">
                                                                    <i class="fas fa-clock"></i>
                                                                    <?php 
                                                                    $deadline = new DateTime($stage['due_date']);
                                                                    $interval = $now->diff($deadline);
                                                                    echo $interval->format('%h hours %i mins');
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Substages Section -->
                                        <?php if (!empty($upcomingSubstages)): ?>
                                            <div class="deadline-section">
                                                <h4>Upcoming Substages</h4>
                                                <?php foreach ($upcomingSubstages as $substage): ?>
                                                    <div class="deadline-item">
                                                        <div class="deadline-header">
                                                            <span class="deadline-title">
                                                                <i class="fas fa-tasks"></i>
                                                                Stage <?php echo htmlspecialchars($substage['stage_number']); ?> Substage
                                                            </span>
                                                            <span class="deadline-priority <?php echo strtolower($substage['priority']); ?>">
                                                                <?php echo ucfirst($substage['priority']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="deadline-details">
                                                            <p class="deadline-description">
                                                                <?php echo htmlspecialchars($substage['description']); ?>
                                                            </p>
                                                            <div class="deadline-meta">
                                                                <span class="deadline-assignee">
                                                                    <i class="fas fa-user"></i>
                                                                    <?php echo htmlspecialchars($substage['assigned_to']); ?>
                                                                </span>
                                                                <span class="deadline-time">
                                                                    <i class="fas fa-clock"></i>
                                                                    <?php 
                                                                    $deadline = new DateTime($substage['due_date']);
                                                                    $interval = $now->diff($deadline);
                                                                    echo $interval->format('%h hours %i mins');
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="no-deadlines">No upcoming deadlines in the next 48 hours</div>
                                    <?php endif; ?>
                                </div>
                                <div class="metrics-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="metrics-content">
                                    <h3>Upcoming Deadlines</h3>
                                    <div class="metrics-numbers">
                                        <span class="current"><?php echo $totalUpcoming; ?></span>
                                    </div>
                                    <p class="metrics-subtitle">Next 48 Hours</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Recent Tasks -->
                        <div class="recent-tasks-column">
                            <div class="recent-tasks-card">
                                <div class="recent-tasks-header">
                                    <h3>Recent Tasks</h3>
                                    <select id="taskFilter">
                                        <option value="all">All Tasks</option>
                                        <option value="pending">Pending</option>
                                        <option value="completed">Completed</option>
                                        <option value="not started">Not Started</option>
                                    </select>
                                </div>
                                <div class="recent-tasks-list">
                                    <?php
                                    // Fetch tasks with their stages and substages
                                    $taskQuery = "
                                        SELECT 
                                            t.id as task_id,
                                            t.title as task_title,
                                            ts.id as stage_id,
                                            ts.stage_number,
                                            ts.status as stage_status,
                                            ts.due_date,
                                            ts.priority as stage_priority,
                                            ts.start_date as stage_start_date,
                                            u.username as assigned_to,
                                            tss.id as substage_id,
                                            tss.description as substage_description,
                                            tss.status as substage_status,
                                            tss.priority as substage_priority,
                                            tss.start_date as substage_start_date,
                                            tss.end_date as substage_end_date
                                        FROM tasks t
                                        LEFT JOIN task_stages ts ON t.id = ts.task_id
                                        LEFT JOIN task_substages tss ON ts.id = tss.stage_id
                                        LEFT JOIN users u ON ts.assigned_to = u.id
                                        ORDER BY t.created_at DESC, ts.stage_number ASC
                                        LIMIT 10";
                                    
                                    $stmt = $pdo->prepare($taskQuery);
                                    $stmt->execute();
                                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Group results by task
                                    $tasks = [];
                                    foreach ($results as $row) {
                                        $taskId = $row['task_id'];
                                        if (!isset($tasks[$taskId])) {
                                            $tasks[$taskId] = [
                                                'title' => $row['task_title'],
                                                'stages' => []
                                            ];
                                        }
                                        
                                        $stageId = $row['stage_id'];
                                        if ($stageId && !isset($tasks[$taskId]['stages'][$stageId])) {
                                            $tasks[$taskId]['stages'][$stageId] = [
                                                'stage_number' => $row['stage_number'],
                                                'status' => $row['stage_status'],
                                                'due_date' => $row['due_date'],
                                                'priority' => $row['stage_priority'],
                                                'start_date' => $row['stage_start_date'],
                                                'assigned_to' => $row['assigned_to'],
                                                'substages' => []
                                            ];
                                        }
                                        
                                        if ($row['substage_id']) {
                                            $tasks[$taskId]['stages'][$stageId]['substages'][] = [
                                                'description' => $row['substage_description'],
                                                'status' => $row['substage_status'],
                                                'priority' => $row['substage_priority'],
                                                'start_date' => $row['substage_start_date'],
                                                'end_date' => $row['substage_end_date']
                                            ];
                                        }
                                    }

                                    foreach ($tasks as $taskId => $task): ?>
                                        <div class="task-item">
                                            <div class="task-header">
                                                <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                            </div>
                                            <div class="stages-container">
                                                <?php foreach ($task['stages'] as $stage): ?>
                                                    <div class="stage-block">
                                                        <div class="stage-header">
                                                            <span class="stage-number">Stage <?php echo htmlspecialchars($stage['stage_number']); ?></span>
                                                            <span class="stage-status <?php echo strtolower($stage['status']); ?>">
                                                                <?php echo ucfirst($stage['status']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="stage-details">
                                                            <div class="stage-meta">
                                                                <span class="stage-assignee">
                                                                    <i class="fas fa-user"></i>
                                                                    <?php echo htmlspecialchars($stage['assigned_to']); ?>
                                                                </span>
                                                                <span class="stage-priority <?php echo strtolower($stage['priority']); ?>">
                                                                    <?php echo ucfirst($stage['priority']); ?>
                                                                </span>
                                                                <span class="stage-date">
                                                                    <i class="fas fa-calendar"></i>
                                                                    <?php echo date('M d', strtotime($stage['due_date'])); ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <?php if (!empty($stage['substages'])): ?>
                                                                <div class="substages-list">
                                                                    <?php foreach ($stage['substages'] as $substage): ?>
                                                                        <div class="substage-item">
                                                                            <div class="substage-content">
                                                                                <?php echo htmlspecialchars($substage['description']); ?>
                                                                            </div>
                                                                            <div class="substage-meta">
                                                                                <span class="substage-status <?php echo strtolower($substage['status']); ?>">
                                                                                    <?php echo ucfirst($substage['status']); ?>
                                                                                </span>
                                                                                <span class="substage-priority <?php echo strtolower($substage['priority']); ?>">
                                                                                    <?php echo ucfirst($substage['priority']); ?>
                                                                                </span>
                                                                                <span class="substage-timeline">
                                                                                    <?php 
                                                                                    echo date('M d', strtotime($substage['start_date']));
                                                                                    if ($substage['end_date']) {
                                                                                        echo ' - ' . date('M d', strtotime($substage['end_date']));
                                                                                    }
                                                                                    ?>
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="board-view">
                    <!-- Add month filter -->
                    <div class="board-filters">
                        <select id="monthFilter" class="month-filter">
                            <?php
                            // Get current month and year
                            $currentMonth = date('n');
                            $currentYear = date('Y');
                            
                            // Generate last 12 months options
                            for ($i = 0; $i < 12; $i++) {
                                $timestamp = mktime(0, 0, 0, $currentMonth - $i, 1, $currentYear);
                                $value = date('Y-m', $timestamp);
                                $label = date('F Y', $timestamp);
                                $selected = ($i === 0) ? 'selected' : '';
                                echo "<option value='{$value}' {$selected}>{$label}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="task-table-container">
                        <table class="task-table">
                            <thead>
                                <tr>
                                    <th width='8%'>S. No.</th>
                                    <th width='25%'>Description</th>
                                    <th width='67%'>Timeline & Stages</th>
                                </tr>
                            </thead>
                            <tbody id="taskTableBody">
                                <?php
                                // Modify the query to include date filtering
                                $taskQuery = "
                                    SELECT 
                                        t.id AS task_id,
                                        t.description AS task_description,
                                        t.created_by,
                                        ts.id AS stage_id,
                                        ts.stage_number,
                                        ts.due_date AS stage_deadline,
                                        ts.assigned_to AS stage_assigned_to,
                                        tsub.id AS substage_id,
                                        tsub.description AS substage_description,
                                        tsub.end_date AS substage_deadline,
                                        u1.username AS created_by_name,
                                        u2.username AS assigned_to_name,
                                        ts.status AS stage_status
                                    FROM tasks t
                                    LEFT JOIN task_stages ts ON t.id = ts.task_id
                                    LEFT JOIN task_substages tsub ON ts.id = tsub.stage_id
                                    LEFT JOIN users u1 ON t.created_by = u1.id
                                    LEFT JOIN users u2 ON ts.assigned_to = u2.id
                                    WHERE DATE_FORMAT(ts.due_date, '%Y-%m') = :yearMonth
                                    ORDER BY t.id, ts.stage_number, tsub.id
                                ";
                                
                                $currentYearMonth = date('Y-m');
                                $stmt = $pdo->prepare($taskQuery);
                                $stmt->execute(['yearMonth' => $currentYearMonth]);
                                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                $currentTaskId = null;
                                $currentStageId = null;
                                $serialNumber = 1;

                                foreach ($results as $row) {
                                    if ($currentTaskId !== $row['task_id']) {
                                        if ($currentTaskId !== null) {
                                            echo "</div></td></tr>"; // Close previous row if exists
                                        }
                                        echo "<tr class='task-row'>";
                                        echo "<td class='task-number'>{$serialNumber}</td>";
                                        echo "<td class='task-description'>" . htmlspecialchars($row['task_description']) . "</td>";
                                        echo "<td class='timeline-cell'><div class='stages-container'>";
                                        $currentTaskId = $row['task_id'];
                                        $serialNumber++;
                                    }

                                    if ($currentStageId !== $row['stage_id'] && $row['stage_id']) {
                                        echo "<div class='stage-block'>";
                                        // Stage Header
                                        echo "<div class='stage-header'>";
                                        echo "<div class='stage-title-row'>";
                                        echo "<h4>Stage {$row['stage_number']}</h4>";
                                        echo "<span class='status-pill {$row['stage_status']}'>" . ucfirst($row['stage_status']) . "</span>";
                                        echo "</div>";
                                        
                                        // Stage Details
                                        echo "<div class='stage-details'>";
                                        echo "<div class='stage-detail-item'>";
                                        echo "<i class='fas fa-calendar-alt'></i>";
                                        echo "<span>" . date('M d, Y', strtotime($row['stage_deadline'])) . "</span>";
                                        echo "</div>";
                                        echo "<div class='stage-detail-item'>";
                                        echo "<i class='fas fa-user'></i>";
                                        echo "<span>Assigned to: " . htmlspecialchars($row['assigned_to_name']) . "</span>";
                                        echo "</div>";
                                        echo "<div class='stage-detail-item'>";
                                        echo "<i class='fas fa-user-shield'></i>";
                                        echo "<span>By: " . htmlspecialchars($row['created_by_name']) . "</span>";
                                        echo "</div>";
                                        echo "</div>";
                                        echo "</div>";

                                        // Substages Section
                                        if ($row['substage_id']) {
                                            echo "<div class='substages-section'>";
                                            do {
                                                if ($row['substage_id']) {
                                                    echo "<div class='substage-item'>";
                                                    echo "<div class='substage-dot'></div>";
                                                    echo "<div class='substage-content'>";
                                                    echo "<span class='substage-name'>" . htmlspecialchars($row['substage_description']) . "</span>";
                                                    echo "<span class='substage-date'>" . date('M d, Y', strtotime($row['substage_deadline'])) . "</span>";
                                                    echo "</div>";
                                                    echo "</div>";
                                                }
                                                $row = next($results);
                                            } while ($row && $row['stage_id'] === $currentStageId);
                                            echo "</div>";
                                            prev($results);
                                        }
                                        echo "</div>";
                                        $currentStageId = $row['stage_id'];
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>



    <!-- Add this notification panel HTML after the notification icon -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="clear-all">Clear All</button>
        </div>
        <div class="notification-tabs">
            <button class="notification-tab-btn active" data-tab="all">All</button>
            <button class="notification-tab-btn" data-tab="attendance">Attendance</button>
            <button class="notification-tab-btn" data-tab="leave">Leaves</button>
            <button class="notification-tab-btn" data-tab="task">Tasks</button>
        </div>
        <div class="notification-list" id="notificationList">
            <!-- Notifications will be dynamically inserted here -->
        </div>
    </div>

    <!-- Task Creation Modal -->
    <div class="studio-task-modal" id="studioTaskCreationModal">
        <div class="studio-task-modal__content">
            <div class="studio-task-modal__header">
                <h2>Create Task</h2>
                <button class="studio-task-modal__close" id="studioTaskModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form class="studio-task-form" id="studioTaskCreationForm" enctype="multipart/form-data">
                <!-- Main Details Section -->
                <div class="studio-task-form__main-details">
                    <div class="studio-task-form__group">
                        <label for="studioTaskTitle">Task Title</label>
                        <div class="input-wrapper">
                            <input type="text" id="studioTaskTitle" name="taskTitle" placeholder="Enter task title" required>
                        </div>
                    </div>
                    
                    <!-- Add Project Type Field Here -->
                    <div class="studio-task-form__group">
                        <label for="studioProjectType">Project Type</label>
                        <div class="input-wrapper">
                            <select id="studioProjectType" name="projectType" required>
                                <option value="">Select Project Type</option>
                                <option value="architecture" data-color="#4CAF50">Architecture</option>
                                <option value="interior" data-color="#2196F3">Interior</option>
                                <option value="construction" data-color="#FF9800">Construction</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="studio-task-form__group">
                        <label for="studioTaskDescription">Description</label>
                        <div class="input-wrapper">
                            <textarea id="studioTaskDescription" name="taskDescription" 
                                    placeholder="Enter task description" rows="4"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Stages Section -->
                <div class="studio-task-stages" id="studioTaskStagesContainer">
                    <!-- Stage 1 -->
                    <div class="studio-task-stage">
                        <div class="studio-task-stage__header">
                            <h3>Stage 1</h3>
                            
                            <div class="studio-task-stage__controls">
                                <div class="studio-task-form__group">
                                    <label for="studioStage1AssignTo">Assign To</label>
                                    <div class="input-wrapper">
                                        <select id="studioStage1AssignTo" name="stage1_assign_to" required>
                                            <option value="">Select Employee</option>
                                            <?php foreach ($availableUsers as $user): ?>
                                                <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                                    <?php echo htmlspecialchars($user['full_name'] ?? $user['username']) . 
                                                          ' (' . htmlspecialchars($user['unique_id']) . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="studio-task-form__group">
                                    <label for="studioStage1Priority">Priority</label>
                                    <div class="input-wrapper priority-select">
                                        <select id="studioStage1Priority" name="stage1_priority" required>
                                            <option value="">Select Priority</option>
                                            <option value="high">High</option>
                                            <option value="medium">Medium</option>
                                            <option value="low">Low</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="studio-task-form__group">
                                    <label for="studioStage1StartDate">Start Date</label>
                                    <div class="input-wrapper">
                                        <input type="datetime-local" id="studioStage1StartDate" 
                                               name="stage1_start_date" required>
                                    </div>
                                </div>
                                
                                <div class="studio-task-form__group">
                                    <label for="studioStage1DueDate">End Date</label>
                                    <div class="input-wrapper">
                                        <input type="datetime-local" id="studioStage1DueDate" 
                                               name="stage1_due_date" required>
                                    </div>
                                </div>
                                
                                <div class="studio-task-form__group">
                                    <label for="studioStage1Files">Stage Files</label>
                                    <div class="input-wrapper">
                                        <div class="file-upload">
                                            <label for="studioStage1Files" class="file-upload-btn">
                                                <i class="fas fa-paperclip"></i> Choose Files
                                            </label>
                                            <input type="file" id="studioStage1Files" name="stage1_files[]" 
                                                   multiple class="stage-file-input" data-stage="1"
                                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="file-list" id="studioStage1FileList"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Substages Section -->
                        <div class="studio-task-substages" id="studioStage1Substages">
                            <div class="studio-task-substage">
                                <div class="substage-content">
                                    <input type="text" name="stage1_substages[]" placeholder="Enter substage description">
                                    <div class="substage-controls">
                                        <select name="stage1_substage_priority[]" required>
                                            <option value="">Priority</option>
                                            <option value="high">High</option>
                                            <option value="medium">Medium</option>
                                            <option value="low">Low</option>
                                        </select>
                                        
                                        <div class="substage-timeline">
                                            <input type="datetime-local" name="stage1_substage_start[]" required>
                                            <input type="datetime-local" name="stage1_substage_end[]" required>
                                        </div>
                                        
                                        <div class="file-upload">
                                            <label class="file-upload-btn">
                                                <i class="fas fa-paperclip"></i> Add Files
                                                <input type="file" name="stage1_substage_files_1[]" multiple 
                                                       class="substage-file-input" data-stage="1" data-substage="1"
                                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                            </label>
                                            <div class="file-list" id="studioStage1Substage1FileList"></div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="studio-task-substage__remove">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="studio-task-substage__add" data-stage="1">
                            <i class="fas fa-plus"></i> Add Substage
                        </button>
                    </div>

                    <button type="button" class="studio-task-stage__add" id="studioAddStageBtn">
                        <i class="fas fa-plus"></i> Add Another Stage
                    </button>
                </div>

                <div class="studio-task-form__actions">
                    <button type="submit" class="studio-task-form__submit">Create Task</button>
                    <button type="button" class="studio-task-form__cancel" id="studioTaskFormCancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Add this before closing </body> tag -->
<div class="chat-widget">
    <div class="chat-container" id="chatContainer">
        <div class="chat-header">
            <div class="chat-tabs">
                <div class="chat-tab active" data-tab="chats">Chats</div>
                <div class="chat-tab" data-tab="groups">Groups</div>
            </div>
            <div class="chat-actions">
                <button class="create-group-btn" onclick="window.chat.createNewGroup()">
                    <i class="fas fa-users"></i>
                    New Group
                </button>
            </div>
        </div>
        <div class="chat-body" id="chatBody">
            <!-- Chat content will be loaded here -->
        </div>
        <div class="message-box" id="messageBox" style="display: none;">
            <label for="fileInput" class="file-attach-btn">
                <i class="fas fa-paperclip"></i>
            </label>
            <input type="file" id="fileInput" style="display: none;" accept="image/*,.pdf,.doc,.docx">
            <input type="text" id="messageInput" placeholder="Type a message..." class="message-input">
            <button class="send-button">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    <div class="chat-button" onclick="toggleChat()">
        <i class="fas fa-comments"></i>
        <span class="unread-badge" style="display: none;">0</span>
    </div>
</div>
<script>
// Add current user ID for the chat
window.currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;

// Toggle chat function
function toggleChat() {
    const chatContainer = document.getElementById('chatContainer');
    chatContainer.classList.toggle('active');
    
    // Update unread count when opening chat
    if (chatContainer.classList.contains('active') && window.chat) {
        window.chat.updateUnreadCount();
    }
}

// Initialize SimpleChat when document is ready
document.addEventListener('DOMContentLoaded', function() {
    window.chat = new SimpleChat();
});
</script>

    <script>
        // Sidebar Toggle Function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

// With this safer version
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if(confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        });
    }
});

        // Time-based greeting and clock function
        function updateGreeting() {
            const now = new Date();
            const hour = now.getHours();
            const greeting = document.getElementById('timeGreeting');
            const timeDisplay = document.getElementById('currentTime');
            const dateDisplay = document.getElementById('currentDate');

            // Update greeting based on time
            if (hour >= 5 && hour < 12) {
                greeting.textContent = 'Good Morning';
            } else if (hour >= 12 && hour < 17) {
                greeting.textContent = 'Good Afternoon';
            } else if (hour >= 17 && hour < 21) {
                greeting.textContent = 'Good Evening';
            } else {
                greeting.textContent = 'Good Night';
            }

            // Update time
            timeDisplay.textContent = now.toLocaleTimeString();
            
            // Update date - Format: Monday, January 1, 2024
            dateDisplay.textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }

        // Update greeting and time every second
        updateGreeting();
        setInterval(updateGreeting, 1000);

        // Add this console log to verify the button is found
        document.addEventListener('DOMContentLoaded', function() {
            const punchButton = document.getElementById('punchButton');
            if (!punchButton) {
                console.error('Punch button not found!');
                return;
            }

            console.log('Adding click listener to punch button');
            punchButton.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent any default form submission
                console.log('Punch button clicked'); // Debug log
                
                const punchText = this.querySelector('span');
                const action = punchText.textContent.trim() === 'Punch In' ? 'punch_in' : 'punch_out';
                
                console.log('Action:', action); // Debug log
                
                this.disabled = true;
                
                fetch('punch_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ action: action })
                })
                .then(response => {
                    console.log('Response received:', response); // Debug log
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data); // Debug log
                    
                    if (data.success) {
                        if (action === 'punch_in') {
                            this.classList.add('punched');
                            punchText.textContent = 'Punch Out';
                            showToast('success', 'Punched in successfully');
                            
                            // Start the working time counter
                            const punchInTime = new Date();
                            updateWorkingTime(punchInTime);
                        } else {
                            this.classList.remove('punched');
                            punchText.textContent = 'Punch In';
                            showToast('success', `Punched out successfully. ${data.working_hours}`);
                            
                            // Stop the working time counter
                            if (window.workingTimeInterval) {
                                clearInterval(window.workingTimeInterval);
                            }
                        }
                    } else {
                        showToast('error', data.message || 'Something went wrong');
                    }
                })
                .catch(error => {
                    console.error('Error:', error); // Debug log
                    showToast('error', 'An error occurred');
                })
                .finally(() => {
                    this.disabled = false;
                });
            });

            // Check initial punch status when page loads
            checkPunchStatus();
        });

        // Function to check punch status
        function checkPunchStatus() {
            fetch('punch_attendance.php?action=check_status')
                .then(response => response.json())
                .then(data => {
                    const punchButton = document.getElementById('punchButton');
                    const punchText = punchButton.querySelector('span');
                    
                    if (data.is_punched_in && !data.is_punched_out) {
                        punchButton.classList.add('punched');
                        punchText.textContent = 'Punch Out';
                        
                        // Display working time if available
                        if (data.punch_in_time) {
                            updateWorkingTime(data.punch_in_time);
                        }
                    } else {
                        punchButton.classList.remove('punched');
                        punchText.textContent = 'Punch In';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Update the updateWorkingTime function to fix the syntax error
        function updateWorkingTime(punchInTime) {
            // Clear any existing interval
            if (window.workingTimeInterval) {
                clearInterval(window.workingTimeInterval);
            }

            // Create new interval
            window.workingTimeInterval = setInterval(() => {
                const start = new Date(punchInTime);
                const now = new Date();
                const diff = now - start;
                
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                const timeWorked = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                
                // Update the display
                const workingTimeDisplay = document.getElementById('workingTimeDisplay');
                if (workingTimeDisplay) {
                    workingTimeDisplay.textContent = `Time Worked: ${timeWorked}`;
                }
            }, 1000);
        }

        // Add this function to show toast messages if it's not already defined
        function showToast(type, message) {
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    ${type === 'error' ? '<i class="fas fa-exclamation-circle"></i>' : '<i class="fas fa-check-circle"></i>'}
                </div>
                <div class="toast-message">${message}</div>
            `;
            document.body.appendChild(toast);

            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 100);

            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }



        // Add this function to format time in 12-hour format
        function formatTime(date) {
            return date.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                hour12: true,
                timeZone: 'Asia/Kolkata'
            });
        }

        // Update the current time display
        function updateTime() {
            const timeDisplay = document.getElementById('currentTime');
            const now = new Date();
            timeDisplay.textContent = formatTime(now);
        }

        // Update time every second
        setInterval(updateTime, 1000);

        document.addEventListener('DOMContentLoaded', function() {
            // Initial time update
            updateTime();
            
            // Rest of your existing DOMContentLoaded code...
        });

        function updateTaskStats() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            fetch('get_task_stats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ startDate, endDate })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('totalTasks').textContent = data.total_tasks;
                document.getElementById('totalStages').textContent = data.total_stages;
                document.getElementById('pendingStages').innerHTML = 
                    `${data.pending_stages} <span class="text-muted">/ ${data.total_stages}</span>`;
                document.getElementById('delayedTasks').textContent = data.delayed_tasks;
                
                // Update progress bars
                updateProgressBars(data);
            })
            .catch(error => console.error('Error:', error));
        }

        function updateProgressBars(data) {
            // Update progress bars logic here
            const pendingPercentage = (data.pending_stages / data.total_stages) * 100;
            const delayedPercentage = (data.delayed_tasks / data.total_tasks) * 100;
            
            document.querySelector('.stats-card:nth-child(2) .progress-bar')
                .style.width = `${pendingPercentage}%`;
            document.querySelector('.stats-card:nth-child(3) .progress-bar')
                .style.width = `${delayedPercentage}%`;
        }

        // Add this to your existing JavaScript
        let currentDate = new Date();

        function updateCalendar() {
            const monthYear = document.getElementById('currentMonthYear');
            const calendarDates = document.getElementById('calendarDates');
            
            // Fetch tasks for the current month
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1;
            
            fetch(`get_month_tasks.php?year=${year}&month=${month}`)
                .then(response => response.json())
                .then(taskData => {
                    // Format the month and year
                    monthYear.textContent = currentDate.toLocaleString('default', { 
                        month: 'long', 
                        year: 'numeric' 
                    });
                    
                    // Get first day of the month
                    const firstDay = new Date(year, month - 1, 1);
                    const lastDay = new Date(year, month, 0);
                    
                    // Clear existing dates
                    calendarDates.innerHTML = '';
                    
                    // Add empty cells for days before the first of the month
                    for(let i = 0; i < firstDay.getDay(); i++) {
                        calendarDates.innerHTML += '<div class="calendar-date empty"></div>';
                    }
                    
                    // Add the days of the month
                    for(let day = 1; day <= lastDay.getDate(); day++) {
                        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                        const tasks = taskData[dateStr] || [];
                        const isToday = new Date(year, month - 1, day).toDateString() === new Date().toDateString();
                        
                        let tooltipContent = '';
                        if(tasks.length > 0) {
                            tooltipContent = createTooltipContent(tasks);
                        } else {
                            tooltipContent = '<div class="tooltip-no-tasks">No tasks for today</div>';
                        }
                        
                        const hasTasksClass = tasks.length > 0 ? 'has-tasks' : '';
                        const todayClass = isToday ? 'current' : '';
                        
                        calendarDates.innerHTML += `
                            <div class="calendar-date ${hasTasksClass} ${todayClass}" 
                                 data-tooltip-content="${escapeHtml(tooltipContent)}">
                                ${day}
                                ${tasks.length > 0 ? '<span class="task-indicator"></span>' : ''}
                            </div>
                        `;
                    }
                });
        }

        function createTooltipContent(tasks) {
            return `
                <div class="tooltip-tasks">
                    ${tasks.map(task => `
                        <div class="tooltip-task">
                            <span class="task-name">${task.project_name}</span>
                            <span class="task-type">${task.project_type}</span>
                            <span class="task-status ${task.status}">${task.status}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            updateCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            updateCalendar();
        }

        // Initialize calendar when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateCalendar();
        });

        // Add this JavaScript for the overview calendar
        document.addEventListener('DOMContentLoaded', function() {
            updateOverviewCalendar();
        });

        let overviewCurrentDate = new Date();

        function updateOverviewCalendar() {
            const monthYear = document.getElementById('monthYear');
            if (monthYear) {
                monthYear.textContent = currentDate.toLocaleString('default', { 
                    month: 'long', 
                    year: 'numeric' 
                });
            }
            // ... rest of the function
        }

        function previousMonth() {
            overviewCurrentDate.setMonth(overviewCurrentDate.getMonth() - 1);
            updateOverviewCalendar();
        }

        function nextMonth() {
            overviewCurrentDate.setMonth(overviewCurrentDate.getMonth() + 1);
            updateOverviewCalendar();
        }

        // Add this JavaScript for the task calendar
        let taskCurrentDate = new Date();

        function updateTaskCalendar() {
            const taskMonthYear = document.getElementById('taskMonthYear');
            if (taskMonthYear) {
                taskMonthYear.textContent = taskCurrentDate.toLocaleString('default', { 
                    month: 'long', 
                    year: 'numeric' 
                });
            }
            // ... rest of the function
        }

        function previousTaskMonth() {
            taskCurrentDate.setMonth(taskCurrentDate.getMonth() - 1);
            updateTaskCalendar();
        }

        function nextTaskMonth() {
            taskCurrentDate.setMonth(taskCurrentDate.getMonth() + 1);
            updateTaskCalendar();
        }

        // Initialize task calendar when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateTaskCalendar();
        });

        // Toggle notification panel
        document.getElementById('notificationIcon').addEventListener('click', function() {
            const panel = document.getElementById('notificationPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            
            // Mark all as read when opening panel
            if (panel.style.display === 'block') {
                markAllNotificationsAsRead();
            }
        });

        // Close panel when clicking outside
        document.addEventListener('click', function(event) {
            const panel = document.getElementById('notificationPanel');
            const icon = document.getElementById('notificationIcon');
            
            if (!panel.contains(event.target) && !icon.contains(event.target)) {
                panel.style.display = 'none';
            }
        });

        // Clear all notifications
        document.querySelector('.clear-all').addEventListener('click', function() {
            clearAllNotifications();
        });

        // Function to add a new notification
        function addNotification(data) {
            const list = document.getElementById('notificationList');
            const count = document.getElementById('notificationCount');
            
            // Create notification item
            const item = document.createElement('div');
            item.className = 'notification-item unread';
            item.innerHTML = `
                <div class="notification-content">
                    <div class="notification-icon">
                        <i class="fas ${data.type === 'leave' ? 'fa-calendar-alt' : 'fa-bell'}"></i>
                    </div>
                    <div class="notification-text">
                        <div class="notification-title">${data.message}</div>
                        <div class="notification-time">${formatTimeAgo(data.timestamp)}</div>
                    </div>
                </div>
            `;
            
            // Add to list
            list.insertBefore(item, list.firstChild);
            
            // Update count
            count.textContent = parseInt(count.textContent) + 1;
            count.classList.add('has-new');
            
            // Update pending leaves count and tooltip
            if (data.type === 'leave') {
                updatePendingLeavesCount(data.leaveCount);
                updatePendingLeavesTooltip(data.leaveDetails);
            }
        }

        // Function to mark all notifications as read
        function markAllNotificationsAsRead() {
            const unreadItems = document.querySelectorAll('.notification-item.unread');
            unreadItems.forEach(item => item.classList.remove('unread'));
            
            document.getElementById('notificationCount').classList.remove('has-new');
        }

        // Function to clear all notifications
        function clearAllNotifications() {
            document.getElementById('notificationList').innerHTML = '';
            document.getElementById('notificationCount').textContent = '0';
            document.getElementById('notificationCount').classList.remove('has-new');
        }

        // Function to format time ago
        function formatTimeAgo(timestamp) {
            const now = new Date();
            const date = new Date(timestamp);
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
            return date.toLocaleDateString();
        }

        // Function to update pending leaves count
        function updatePendingLeavesCount(count) {
            const pendingLeavesNumber = document.querySelector('.quick-view-card.pending .card-numbers .current');
            pendingLeavesNumber.textContent = count;
        }

        // Function to update pending leaves tooltip
        function updatePendingLeavesTooltip(leaveDetails) {
            const tooltip = document.querySelector('.quick-view-card.pending .tasks-priority-details-tooltip');
            const tooltipContent = leaveDetails.map(leave => `
                <div class="priority-item">
                    <div class="priority-info">
                        <span class="priority-label">
                            <i class="fas fa-user"></i>
                            ${leave.employee_name}
                            <small>(${leave.leave_type})</small>
                        </span>
                        <span class="priority-count">${formatDate(leave.start_date)}</span>
                    </div>
                </div>
            `).join('');
            
            tooltip.innerHTML = `
                <div class="priority-tooltip-header">
                    <i class="fas fa-hourglass-half"></i>
                    Pending Leave Requests
                </div>
                ${tooltipContent}
            `;
        }

        // Function to format date
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        }

        // WebSocket connection for real-time notifications
        let ws;
        function connectWebSocket() {
            try {
                ws = new WebSocket('ws://your-websocket-server/');
                
                ws.onopen = function() {
                    console.log('WebSocket connected successfully');
                };
                
                ws.onerror = function(error) {
                    console.error('WebSocket error:', error);
                    // Attempt to reconnect after 5 seconds
                    setTimeout(connectWebSocket, 5000);
                };
                
                ws.onclose = function() {
                    console.log('WebSocket connection closed');
                    // Attempt to reconnect after 5 seconds
                    setTimeout(connectWebSocket, 5000);
                };
                
                ws.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        // Handle your WebSocket messages here
                        console.log('Received message:', data);
                    } catch (e) {
                        console.error('Error parsing WebSocket message:', e);
                    }
                };
            } catch (e) {
                console.error('Error creating WebSocket connection:', e);
                // Attempt to reconnect after 5 seconds
                setTimeout(connectWebSocket, 5000);
            }
        }

        // Initialize WebSocket connection when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            connectWebSocket();
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Get modal elements
            const modal = document.getElementById('studioTaskCreationModal');
            const openBtn = document.getElementById('studioTaskCreationTrigger');
            const sidebarOpenBtn = document.getElementById('sidebarTaskCreationTrigger'); // Add this line
            const closeBtn = document.getElementById('studioTaskModalClose');
            const cancelBtn = document.getElementById('studioTaskFormCancel');
            const form = document.getElementById('studioTaskCreationForm');
            const addStageBtn = document.getElementById('studioAddStageBtn');
            let stageCount = 1;

            // Add Project Type Handling
            const projectTypeSelect = document.getElementById('studioProjectType');
            
            // Function to update the select styling based on selected project type
            function updateProjectTypeStyle() {
                const selectedOption = projectTypeSelect.options[projectTypeSelect.selectedIndex];
                const color = selectedOption.getAttribute('data-color');
                
                // Remove existing color classes
                projectTypeSelect.classList.remove('project-type-architecture', 'project-type-interior', 'project-type-construction');
                
                if (selectedOption.value) {
                    projectTypeSelect.classList.add(`project-type-${selectedOption.value}`);
                    projectTypeSelect.style.backgroundColor = color;
                    projectTypeSelect.style.color = 'white';
                } else {
                    projectTypeSelect.style.backgroundColor = '';
                    projectTypeSelect.style.color = '';
                }
            }
            
            // Add change event listener for project type
            if (projectTypeSelect) {
                projectTypeSelect.addEventListener('change', updateProjectTypeStyle);
            }

            // Open modal function
            const openModal = () => {
                modal.style.display = 'block';
            };

            // Add click handlers for both buttons
            openBtn.addEventListener('click', openModal);
            sidebarOpenBtn.addEventListener('click', openModal); // Add this line

            // Close modal functions
            const closeModal = () => {
                modal.style.display = 'none';
                // Reset form when closing
                form.reset();
                // Reset stages to initial state
                resetStages();
            };

            closeBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);

            // Close on outside click
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });

            // Function to reset stages
            function resetStages() {
                const stagesContainer = document.getElementById('studioTaskStagesContainer');
                const stages = stagesContainer.querySelectorAll('.studio-task-stage');
                
                // Remove all stages except the first one
                stages.forEach((stage, index) => {
                    if (index > 0) {
                        stage.remove();
                    }
                });
                
                // Reset first stage to initial state
                if (stages[0]) {
                    const firstStageSubstages = stages[0].querySelector('.studio-task-substages');
                    firstStageSubstages.innerHTML = `
                        <div class="studio-task-substage">
                            <input type="text" name="stage1_substages[]" placeholder="Enter substage">
                            <button type="button" class="studio-task-substage__remove">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    `;
                }
                
                stageCount = 1;
                addStageBtn.style.display = 'block';
            }

            // Add new stage
            addStageBtn.addEventListener('click', () => {
                stageCount++;
                const newStage = createNewStage(stageCount);
                // Insert before the add stage button
                addStageBtn.insertAdjacentElement('beforebegin', newStage);
            });

            // Add substage event delegation
            document.addEventListener('click', function(e) {
                if (e.target.matches('.studio-task-substage__add') || e.target.closest('.studio-task-substage__add')) {
                    const button = e.target.matches('.studio-task-substage__add') ? e.target : e.target.closest('.studio-task-substage__add');
                    const stageNum = button.dataset.stage;
                    addSubstage(stageNum);
                }
                
                if (e.target.matches('.studio-task-substage__remove') || e.target.closest('.studio-task-substage__remove')) {
                    const button = e.target.matches('.studio-task-substage__remove') ? e.target : e.target.closest('.studio-task-substage__remove');
                    const substageDiv = button.closest('.studio-task-substage');
                    const substagesContainer = substageDiv.parentElement;
                    
                    if (substagesContainer.children.length > 1) {
                        substageDiv.remove();
                    }
                }
                
                if (e.target.matches('.studio-task-stage__remove') || e.target.closest('.studio-task-stage__remove')) {
                    const button = e.target.matches('.studio-task-stage__remove') ? e.target : e.target.closest('.studio-task-stage__remove');
                    const stageDiv = button.closest('.studio-task-stage');
                    stageDiv.remove();
                    stageCount--;
                    updateStageNumbers();
                    addStageBtn.style.display = 'block';
                }
            });

            // Create new stage element
            function createNewStage(stageNum) {
                const stageDiv = document.createElement('div');
                stageDiv.className = 'studio-task-stage';
                
                stageDiv.innerHTML = `
                    <div class="studio-task-stage__header">
                        <div class="studio-task-stage__title">
                            <h3>Stage ${stageNum}</h3>
                            <button type="button" class="studio-task-stage__remove" title="Remove Stage">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="studio-task-stage__controls">
                            <label for="studioStage${stageNum}AssignTo">Assign To:</label>
                            <select id="studioStage${stageNum}AssignTo" name="stage${stageNum}_assign_to" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($availableUsers as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <?php echo htmlspecialchars($user['full_name'] ?? $user['username']) . 
                                              ' (' . htmlspecialchars($user['unique_id']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label for="studioStage${stageNum}Priority">Priority:</label>
                            <select id="studioStage${stageNum}Priority" name="stage${stageNum}_priority" required>
                                <option value="">Select Priority</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                            
                            <label for="studioStage${stageNum}StartDate">Start Date:</label>
                            <input type="datetime-local" id="studioStage${stageNum}StartDate" 
                                   name="stage${stageNum}_start_date" required>
                            
                            <label for="studioStage${stageNum}DueDate">End Date:</label>
                            <input type="datetime-local" id="studioStage${stageNum}DueDate" 
                                   name="stage${stageNum}_due_date" required>
                            
                            <label for="studioStage${stageNum}Files">Stage Files:</label>
                            <div class="file-upload-container">
                                <input type="file" id="studioStage${stageNum}Files" 
                                       name="stage${stageNum}_files[]" multiple
                                       class="stage-file-input" data-stage="${stageNum}"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                <div class="file-list" id="studioStage${stageNum}FileList"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="studio-task-substages" id="studioStage${stageNum}Substages">
                        <div class="studio-task-substage">
                            <input type="text" name="stage${stageNum}_substages[]" placeholder="Enter substage">
                            <select name="stage${stageNum}_substage_priority[]" required>
                                <option value="">Priority</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                            
                            <div class="substage-timeline">
                                <input type="datetime-local" name="stage${stageNum}_substage_start[]" 
                                       placeholder="Start Time" required>
                                <input type="datetime-local" name="stage${stageNum}_substage_end[]" 
                                       placeholder="End Time" required>
                            </div>
                            
                            <div class="substage-file-upload">
                                <input type="file" name="stage${stageNum}_substage_files_1[]" multiple 
                                       class="substage-file-input" data-stage="${stageNum}" data-substage="1"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                <div class="file-list" id="studioStage${stageNum}Substage1FileList"></div>
                            </div>
                            
                            <button type="button" class="studio-task-substage__remove">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="button" class="studio-task-substage__add" data-stage="${stageNum}">
                        Add Substage
                    </button>
                `;

                return stageDiv;
            }

            // Function to add substage
            function addSubstage(stageNum) {
                const substagesContainer = document.getElementById(`studioStage${stageNum}Substages`);
                const substageCount = substagesContainer.children.length + 1;
                
                const newSubstage = document.createElement('div');
                newSubstage.className = 'studio-task-substage';
                newSubstage.innerHTML = `
                    <input type="text" name="stage${stageNum}_substages[]" placeholder="Enter substage">
                    <select name="stage${stageNum}_substage_priority[]" required>
                        <option value="">Priority</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                    
                    <div class="substage-timeline">
                        <input type="datetime-local" name="stage${stageNum}_substage_start[]" 
                               placeholder="Start Time" required>
                        <input type="datetime-local" name="stage${stageNum}_substage_end[]" 
                               placeholder="End Time" required>
                    </div>
                    
                    <div class="substage-file-upload">
                        <input type="file" name="stage${stageNum}_substage_files_${substageCount}[]" 
                               multiple class="substage-file-input" 
                               data-stage="${stageNum}" data-substage="${substageCount}"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <div class="file-list" id="studioStage${stageNum}Substage${substageCount}FileList"></div>
                    </div>
                    
                    <button type="button" class="studio-task-substage__remove">
                        <i class="fas fa-minus"></i>
                    </button>
                `;
                
                substagesContainer.appendChild(newSubstage);
            }

            // Function to update stage numbers
            function updateStageNumbers() {
                const stages = document.querySelectorAll('.studio-task-stage');
                stages.forEach((stage, index) => {
                    const stageNum = index + 1;
                    
                    // Update stage title
                    stage.querySelector('h3').textContent = `Stage ${stageNum}`;
                    
                    // Update form elements
                    const assignTo = stage.querySelector('select');
                    const startDate = stage.querySelector('input[name="stage${stageNum}_start_date"]');
                    const dueDate = stage.querySelector('input[name="stage${stageNum}_due_date"]');
                    const substages = stage.querySelectorAll('.studio-task-substage input');
                    const addSubstageBtn = stage.querySelector('.studio-task-substage__add');
                    
                    assignTo.id = `studioStage${stageNum}AssignTo`;
                    assignTo.name = `stage${stageNum}_assign_to`;
                    
                    startDate.id = `studioStage${stageNum}StartDate`;
                    startDate.name = `stage${stageNum}_start_date`;
                    
                    dueDate.id = `studioStage${stageNum}DueDate`;
                    dueDate.name = `stage${stageNum}_due_date`;
                    
                    substages.forEach(input => {
                        input.name = `stage${stageNum}_substages[]`;
                    });
                    
                    addSubstageBtn.dataset.stage = stageNum;
                });
            }

            // Update the form submission handler
            document.getElementById('studioTaskCreationForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                try {
                    const formData = new FormData();
                    
                    // Add basic task data including project type
                    formData.append('taskTitle', document.getElementById('studioTaskTitle').value.trim());
                    formData.append('taskDescription', document.getElementById('studioTaskDescription').value.trim());
                    formData.append('projectType', document.getElementById('studioProjectType').value);
                    
                    // Validate project type
                    if (!formData.get('projectType')) {
                        throw new Error('Please select a project type');
                    }

                    // Process stages
                    const stages = document.querySelectorAll('.studio-task-stage');
                    stages.forEach((stage, stageIndex) => {
                        const stageNum = stageIndex + 1;
                        
                        // Add stage basic info
                        formData.append(`stages[${stageIndex}][assign_to]`, stage.querySelector(`select[name="stage${stageNum}_assign_to"]`).value);
                        formData.append(`stages[${stageIndex}][priority]`, stage.querySelector(`select[name="stage${stageNum}_priority"]`).value);
                        formData.append(`stages[${stageIndex}][start_date]`, stage.querySelector(`input[name="stage${stageNum}_start_date"]`).value);
                        formData.append(`stages[${stageIndex}][due_date]`, stage.querySelector(`input[name="stage${stageNum}_due_date"]`).value);
                        
                        // Add stage files
                        const stageFileInput = stage.querySelector(`input[type="file"][name="stage${stageNum}_files[]"]`);
                        if (stageFileInput && stageFileInput.files.length > 0) {
                            Array.from(stageFileInput.files).forEach((file, fileIndex) => {
                                formData.append(`stageFiles[${stageIndex}][]`, file);
                            });
                        }
                        
                        // Process substages
                        const substages = stage.querySelectorAll('.studio-task-substage');
                        substages.forEach((substage, substageIndex) => {
                            formData.append(`stages[${stageIndex}][substages][${substageIndex}][title]`, 
                                substage.querySelector(`input[name="stage${stageNum}_substages[]"]`).value);
                            formData.append(`stages[${stageIndex}][substages][${substageIndex}][priority]`, 
                                substage.querySelector(`select[name="stage${stageNum}_substage_priority[]"]`).value);
                            formData.append(`stages[${stageIndex}][substages][${substageIndex}][start_date]`, 
                                substage.querySelector(`input[name="stage${stageNum}_substage_start[]"]`).value);
                            formData.append(`stages[${stageIndex}][substages][${substageIndex}][end_date]`, 
                                substage.querySelector(`input[name="stage${stageNum}_substage_end[]"]`).value);
                            
                            // Add substage files
                            const substageFileInput = substage.querySelector(`input[type="file"][name="stage${stageNum}_substage_files_${substageIndex + 1}[]"]`);
                            if (substageFileInput && substageFileInput.files.length > 0) {
                                Array.from(substageFileInput.files).forEach((file, fileIndex) => {
                                    formData.append(`substageFiles[${stageIndex}][${substageIndex}][]`, file);
                                });
                            }
                        });
                    });

                    // Debug: Log the FormData
                    console.log('Form Data:');
                    for (let pair of formData.entries()) {
                        console.log(pair[0], pair[1]);
                    }

                    // Send form data to server
                    const response = await fetch('create_task.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('success', 'Task created successfully');
                        const modal = document.getElementById('studioTaskCreationModal');
                        if (modal) {
                            modal.style.display = 'none';
                        }
                        this.reset();
                        // Reset project type styling after form submission
                        if (projectTypeSelect) {
                            projectTypeSelect.style.backgroundColor = '';
                            projectTypeSelect.style.color = '';
                        }
                    } else {
                        throw new Error(result.message || 'Failed to create task');
                    }
                    
                } catch (error) {
                    showToast('error', error.message);
                    console.error('Error:', error);
                }
            });

            // Add this function to validate the form before submission
            function validateTaskForm(form) {
                const title = form.querySelector('#studioTaskTitle').value.trim();
                if (!title) {
                    throw new Error('Task title is required');
                }

                const stages = form.querySelectorAll('.studio-task-stage');
                if (stages.length === 0) {
                    throw new Error('At least one stage is required');
                }

                stages.forEach((stage, index) => {
                    const assignTo = stage.querySelector(`select[name^="stage${index + 1}_assign_to"]`).value;
                    const startDate = stage.querySelector(`input[name^="stage${index + 1}_start_date"]`).value;
                    const dueDate = stage.querySelector(`input[name^="stage${index + 1}_due_date"]`).value;
                    
                    if (!assignTo) {
                        throw new Error(`Please assign Stage ${index + 1} to a user`);
                    }
                    if (!startDate) {
                        throw new Error(`Please set a start date for Stage ${index + 1}`);
                    }
                    if (!dueDate) {
                        throw new Error(`Please set a due date for Stage ${index + 1}`);
                    }

                    const substages = stage.querySelectorAll(`input[name^="stage${index + 1}_substages"]`);
                    let hasValidSubstage = false;
                    substages.forEach(input => {
                        if (input.value.trim()) {
                            hasValidSubstage = true;
                        }
                    });

                    if (!hasValidSubstage) {
                        throw new Error(`Please add at least one substage for Stage ${index + 1}`);
                    }
                });
            }
        });

        // Add this function to your existing JavaScript
        function handleLeave(leaveId, action, buttonElement) {
            // Show confirmation dialog with reason input
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Leave Request`,
                input: 'textarea',
                inputLabel: 'Reason',
                inputPlaceholder: `Enter reason for ${action}ing the leave request`,
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please enter a reason';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send AJAX request to update leave status
                    fetch('handle_leave_request.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            leave_id: leaveId,
                            action: action,
                            reason: result.value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove the leave request item from UI
                            const leaveItem = buttonElement.closest('.pending-leave-item');
                            leaveItem.remove();
                            
                            // Update the pending leaves count
                            const countElement = document.querySelector('.metrics-card.pending .metrics-numbers .current');
                            const currentCount = parseInt(countElement.textContent);
                            countElement.textContent = currentCount - 1;
                            
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: data.message
                            });
                        } else {
                            throw new Error(data.message || 'Failed to process leave request');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message
                        });
                    });
                }
            });
        }

        // Handle file uploads
        document.addEventListener('change', function(e) {
            if (e.target.matches('.stage-file-input, .substage-file-input')) {
                const files = e.target.files;
                const fileList = e.target.nextElementSibling;
                
                fileList.innerHTML = '';
                Array.from(files).forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.innerHTML = `
                        <span>${file.name}</span>
                        <i class="fas fa-times file-remove"></i>
                    `;
                    fileList.appendChild(fileItem);
                });
            }
        });

        // Handle file removal
        document.addEventListener('click', function(e) {
            if (e.target.matches('.file-remove')) {
                const fileItem = e.target.closest('.file-item');
                const fileInput = fileItem.closest('.file-upload-container, .substage-file-upload')
                                        .querySelector('input[type="file"]');
                
                // Clear the file input
                fileInput.value = '';
                fileItem.remove();
            }
        });

        // Initialize the whiteboard
        document.addEventListener('DOMContentLoaded', function() {
            updateTeamAvailability();
            
            // Add event listeners for filters
            document.getElementById('filterDepartment').addEventListener('change', updateTeamAvailability);
            document.getElementById('filterWeek').addEventListener('change', updateTeamAvailability);
        });

        function updateTeamAvailability() {
            const department = document.getElementById('filterDepartment').value;
            const weekFilter = document.getElementById('filterWeek').value;
            
            fetch('get_team_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    department: department,
                    weekFilter: weekFilter
                })
            })
            .then(response => response.json())
            .then(data => {
                renderTeamAvailability(data);
            })
            .catch(error => console.error('Error:', error));
        }

        function renderTeamAvailability(data) {
            const board = document.getElementById('teamAvailabilityBoard');
            board.innerHTML = '';
            
            data.forEach(employee => {
                const row = document.createElement('div');
                row.className = 'employee-row';
                
                // Employee info
                const info = document.createElement('div');
                info.className = 'employee-info';
                info.innerHTML = `
                    <strong>${employee.name}</strong>
                   
                `;
                
                // Status cells for each day
                const status = document.createElement('div');
                status.className = 'employee-status';
                
                employee.weekStatus.forEach(day => {
                    const cell = document.createElement('div');
                    cell.className = `status-cell status-${day.status}`;
                    cell.title = day.tooltip;
                    cell.textContent = day.label;
                    status.appendChild(cell);
                });
                
                row.appendChild(info);
                row.appendChild(status);
                board.appendChild(row);
            });
        }

        // Avatar Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const avatarButton = document.getElementById('avatarButton');
            const avatarDropdown = document.getElementById('avatarDropdown');

            if (!avatarButton || !avatarDropdown) {
                console.error('Avatar elements not found!');
                return;
            }

            // Toggle dropdown on avatar click
            avatarButton.addEventListener('click', function(e) {
                e.stopPropagation();
                avatarDropdown.classList.toggle('show');
                console.log('Avatar clicked, dropdown toggled'); // Debug log
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!avatarButton.contains(e.target) && !avatarDropdown.contains(e.target)) {
                    avatarDropdown.classList.remove('show');
                }
            });

            // Confirm before logout
            const logoutLink = avatarDropdown.querySelector('.logout');
            if (logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = this.href;
                    }
                });
            }
        });

        // Add this to your existing JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const viewToggle = document.querySelector('.view-toggle');
            const viewOptions = document.querySelectorAll('.view-toggle-option');
            const statsView = document.querySelector('.stats-view');
            const boardView = document.querySelector('.board-view');

            viewOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const view = this.dataset.view;
                    
                    // Update toggle state
                    viewToggle.dataset.view = view;
                    viewOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');

                    // Show/hide appropriate view
                    if (view === 'stats') {
                        statsView.classList.remove('hidden');
                        boardView.classList.remove('active');
                    } else {
                        statsView.classList.add('hidden');
                        boardView.classList.add('active');
                        loadBoardTasks(); // Function to load tasks into board view
                    }
                });
            });

            // Function to load tasks into board view
            function loadBoardTasks() {
                // Make an AJAX call to get tasks
                fetch('get_tasks.php')
                    .then(response => response.json())
                    .then(tasks => {
                        // Clear existing tasks
                        document.querySelectorAll('.board-column').forEach(column => {
                            const tasksContainer = column.querySelector('.board-tasks');
                            if (tasksContainer) {
                                tasksContainer.innerHTML = '';
                            }
                        });

                        // Update task counts
                        const counts = {
                            todo: 0,
                            'in-progress': 0,
                            review: 0,
                            completed: 0
                        };

                        // Populate tasks
                        tasks.forEach(task => {
                            const taskElement = createTaskElement(task);
                            const column = document.querySelector(`.${task.status}-column`);
                            if (column) {
                                column.appendChild(taskElement);
                                counts[task.status]++;
                            }
                        });

                        // Update counts
                        Object.keys(counts).forEach(status => {
                            const countElement = document.querySelector(`.${status}-column .task-count`);
                            if (countElement) {
                                countElement.textContent = counts[status];
                            }
                        });
                    })
                    .catch(error => console.error('Error loading tasks:', error));
            }

            function createTaskElement(task) {
                const taskElement = document.createElement('div');
                taskElement.className = 'board-task';
                taskElement.innerHTML = `
                    <div class="board-task-title">${task.title}</div>
                    <div class="board-task-meta">
                        <div class="board-task-assignee">
                            <i class="fas fa-user"></i>
                            ${task.assignee}
                        </div>
                        <div class="board-task-deadline">
                            <i class="fas fa-calendar"></i>
                            ${task.deadline}
                        </div>
                    </div>
                `;
                return taskElement;
            }
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const viewToggle = document.querySelector('.view-toggle');
    const statsView = document.querySelector('.stats-view');
    const boardView = document.querySelector('.board-view');
    const calendarView = document.querySelector('.calendar-view');
    let calendar = null;

    viewToggle.addEventListener('click', function(e) {
        const option = e.target.closest('.view-toggle-option');
        if (!option) return;

        // Update active state
        viewToggle.querySelectorAll('.view-toggle-option').forEach(opt => {
            opt.classList.remove('active');
        });
        option.classList.add('active');

        // Update view
        const view = option.getAttribute('data-view');
        statsView.style.display = view === 'stats' ? 'block' : 'none';
        boardView.style.display = view === 'board' ? 'block' : 'none';
        calendarView.style.display = view === 'calendar' ? 'block' : 'none';

        // Initialize calendar if needed
        if (view === 'calendar' && !calendar) {
            calendar = new TaskCalendar('taskCalendarContainer');
        }
    });
});
</script>
<script>
document.getElementById('monthFilter').addEventListener('change', function() {
    const selectedMonth = this.value;
    
    // Show loading state
    document.getElementById('taskTableBody').innerHTML = '<tr><td colspan="3" class="loading-state">Loading...</td></tr>';
    
    // Fetch tasks for selected month
    fetch('get_tasks_by_month.php', {  // Updated path to include 'hr/'
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ yearMonth: selectedMonth })
    })
    .then(response => response.json())
    .then(data => {
        // Debug log
        console.log('Received data:', data);
        updateTaskTable(data);
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('taskTableBody').innerHTML = 
            '<tr><td colspan="3" class="error-state">Error loading tasks. Please try again.</td></tr>';
    });
});

function updateTaskTable(tasks) {
    const tbody = document.getElementById('taskTableBody');
    if (!Array.isArray(tasks) || tasks.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="empty-state">No tasks found for selected month</td></tr>';
        return;
    }
    
    let html = '';
    let currentTaskId = null;
    let currentStageId = null;
    let serialNumber = 1;
    
    tasks.forEach((row, index) => {
        if (currentTaskId !== row.task_id) {
            if (currentTaskId !== null) {
                html += '</div></td></tr>';
            }
            html += `
                <tr class='task-row'>
                    <td class='task-number'>${serialNumber}</td>
                    <td class='task-description'>${escapeHtml(row.task_description)}</td>
                    <td class='timeline-cell'><div class='stages-container'>
            `;
            currentTaskId = row.task_id;
            serialNumber++;
        }

        if (currentStageId !== row.stage_id && row.stage_id) {
            html += `
                <div class='stage-block'>
                    <div class='stage-header'>
                        <div class='stage-title-row'>
                            <h4>Stage ${row.stage_number}</h4>
                            <span class='status-pill ${row.stage_status}'>${capitalize(row.stage_status)}</span>
                        </div>
                        <div class='stage-details'>
                            <div class='stage-detail-item'>
                                <i class='fas fa-calendar-alt'></i>
                                <span>${formatDate(row.stage_deadline)}</span>
                            </div>
                            <div class='stage-detail-item'>
                                <i class='fas fa-user'></i>
                                <span>Assigned to: ${escapeHtml(row.assigned_to_name)}</span>
                            </div>
                            <div class='stage-detail-item'>
                                <i class='fas fa-user-shield'></i>
                                <span>By: ${escapeHtml(row.created_by_name)}</span>
                            </div>
                        </div>
                    </div>
            `;

            // Start substages section if there are substages
            if (row.substage_id) {
                html += '<div class="substages-section">';
                while (index < tasks.length && tasks[index].stage_id === row.stage_id) {
                    if (tasks[index].substage_id) {
                        html += `
                            <div class='substage-item'>
                                <div class='substage-dot'></div>
                                <div class='substage-content'>
                                    <span class='substage-name'>${escapeHtml(tasks[index].substage_description)}</span>
                                    <span class='substage-date'>${formatDate(tasks[index].substage_deadline)}</span>
                                </div>
                            </div>
                        `;
                    }
                    index++;
                }
                html += '</div>';
                index--; // Adjust index since forEach will increment it
            }
            
            html += '</div>'; // Close stage-block
            currentStageId = row.stage_id;
        }
    });

    // Close any open containers
    if (currentTaskId !== null) {
        html += '</div></td></tr>';
    }
    
    tbody.innerHTML = html;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>
    <!-- Add these before closing body tag -->
<script src="assets/js/task-calendar.js"></script>

<script>
document.getElementById('statsTimeFilter').addEventListener('change', function() {
    const filter = this.value;
    window.location.href = window.location.pathname + '?time_filter=' + filter;
});
</script>
</body>
</html>