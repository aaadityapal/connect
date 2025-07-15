<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Include config file
require_once 'config/db_connect.php';

// Now the user_id will be available for the attendance check
$user_id = $_SESSION['user_id'];

// Use the PDO connection directly from @config.php
try {
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }
} catch(Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch HR admin details from database
$stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE role = 'HR' AND status = 'active' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Set session variables from database results
$_SESSION['username'] = $admin['username'] ?? 'Admin';
$_SESSION['avatar'] = $admin['profile_image'] ?? 'https://ui-avatars.com/api/?name=Admin&background=4F46E5&color=fff';

// Get total users count
$total_users_query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
$total_users_result = $pdo->query($total_users_query);
$total_users_row = $total_users_result->fetch(PDO::FETCH_ASSOC);
$employee_stats['total_users'] = $total_users_row['total'];

// Get present users count for today
$present_users_query = "SELECT COUNT(DISTINCT user_id) as present 
                       FROM attendance 
                       WHERE DATE(date) = CURDATE() 
                       AND punch_in IS NOT NULL";
$present_users_result = $pdo->query($present_users_query);
$present_users_row = $present_users_result->fetch(PDO::FETCH_ASSOC);
$employee_stats['present_users'] = $present_users_row['present'];

// Get users on leave count for today
$users_on_leave_query = "SELECT COUNT(DISTINCT user_id) as on_leave 
                        FROM leaves 
                        WHERE status = 'approved'  -- Changed from hr_status to status
                        AND CURDATE() BETWEEN start_date AND end_date 
                        AND leave_type_id = 1";
$users_on_leave_result = $pdo->query($users_on_leave_query);
$users_on_leave_row = $users_on_leave_result->fetch(PDO::FETCH_ASSOC);
$employee_stats['users_on_leave'] = $users_on_leave_row['on_leave'];

// Get pending leaves count and details in one query
$pending_details_query = "SELECT 
    l.*,
    u.username,
    u.employee_id,
    lt.name as leave_type
    FROM leave_request l
    JOIN users u ON l.user_id = u.id
    JOIN leave_types lt ON l.leave_type = lt.id
    WHERE (
        l.status = 'pending' 
        OR l.status = 'pending_hr'
        OR (l.manager_approval IS NULL AND l.status != 'rejected')
        OR (l.hr_approval IS NULL AND l.status != 'rejected')
    )
    AND l.start_date >= CURDATE()
    ORDER BY l.created_at DESC";

try {
    $pending_details = $pdo->query($pending_details_query)->fetchAll(PDO::FETCH_ASSOC);
    $employee_stats['pending_leaves'] = count($pending_details);
} catch (PDOException $e) {
    error_log("Pending leaves query error: " . $e->getMessage());
    $pending_details = [];
    $employee_stats['pending_leaves'] = 0;
}

// Add debug output
error_log("Pending Leaves Count: " . print_r($employee_stats['pending_leaves'], true));
error_log("Pending Details: " . print_r($pending_details, true));

// Get users on short leave for today
$short_leave_query = "SELECT COUNT(DISTINCT user_id) as short_leave 
                     FROM leaves 
                     WHERE status = 'approved'  -- Changed from hr_status to status
                     AND leave_type_id = 2
                     AND DATE(start_date) = CURDATE()";
$short_leave_result = $pdo->query($short_leave_query);
$short_leave_row = $short_leave_result->fetch(PDO::FETCH_ASSOC);
$employee_stats['short_leave'] = $short_leave_row['short_leave'];

// Add this where you calculate $employee_stats
$short_leave_count_query = "
    SELECT COUNT(*) as short_leave_count 
    FROM leave_request 
    WHERE leave_type = 'Short Leave' 
    AND status = 'approved' 
    AND hr_approval = 'approved'
    AND DATE(start_date) = CURDATE()";
$short_leave_count = $pdo->query($short_leave_count_query)->fetch(PDO::FETCH_ASSOC);
$employee_stats['short_leave'] = $short_leave_count['short_leave_count'];

// Fetch announcements
$announcements_query = "
    SELECT 
        id,
        title,
        priority,
        created_at,
        display_until,
        status
    FROM announcements 
    WHERE status = 'active' 
    AND (display_until IS NULL OR display_until >= CURDATE())
    ORDER BY 
        CASE priority
            WHEN 'high' THEN 1
            WHEN 'normal' THEN 2
            WHEN 'low' THEN 3
        END,
        created_at DESC";

try {
    $announcements = $pdo->query($announcements_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Announcements query error: " . $e->getMessage());
    $announcements = [];
}

// Add this after your announcements query:
if (empty($announcements)) {
    error_log("No announcements found");
} else {
    error_log("Announcements data: " . print_r($announcements, true));
}

// Add this after your announcements query:
if ($pdo->errorInfo()[0] !== '00000') {
    error_log("Database error: " . print_r($pdo->errorInfo(), true));
}

// Fetch circulars
$circulars_query = "
    SELECT * FROM circulars 
    WHERE status = 'active' 
    AND (valid_until IS NULL OR valid_until >= CURDATE())
    ORDER BY created_at DESC";
$circulars = $pdo->query($circulars_query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch events
$events_query = "
    SELECT * FROM events 
    WHERE status = 'active' 
    AND event_date >= CURDATE()
    ORDER BY event_date ASC, start_time ASC
    LIMIT 5";
$events = $pdo->query($events_query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch holidays
$holidays_query = "
    SELECT 
        id,
        title,           /* Changed from holiday_name to title */
        holiday_date,    /* This matches your table */
        holiday_type,    /* This matches your table */
        description,     /* This matches your table */
        status,
        created_by,
        created_at
    FROM holidays 
    WHERE holiday_date >= CURDATE() 
    AND status = 'active'
    ORDER BY holiday_date ASC";

try {
    $stmt = $pdo->prepare($holidays_query);
    $stmt->execute();
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Holidays query error: " . $e->getMessage());
    $holidays = [];
}

// Replace the existing queries with these safer versions
try {
    // Get pipeline employees count
    $pipeline_count = 0;
    if($pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0) {
        $pipeline_query = "SELECT COUNT(*) as count FROM users WHERE status = 'pipeline'";
        $pipeline_result = $pdo->query($pipeline_query);
        $pipeline_count = $pipeline_result->fetch(PDO::FETCH_ASSOC)['count'];
    }

    // Get manager requests count
    $manager_requests_count = 0;
    if($pdo->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0) {
        $manager_requests_query = "SELECT COUNT(*) as count FROM requests WHERE type = 'manager' AND status = 'pending'";
        $manager_requests_result = $pdo->query($manager_requests_query);
        $manager_requests_count = $manager_requests_result->fetch(PDO::FETCH_ASSOC)['count'];
    }

    // Get employee requests count
    $employee_requests_count = 0;
    if($pdo->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0) {
        $employee_requests_query = "SELECT COUNT(*) as count FROM requests WHERE type = 'employee' AND status = 'pending'";
        $employee_requests_result = $pdo->query($employee_requests_query);
        $employee_requests_count = $employee_requests_result->fetch(PDO::FETCH_ASSOC)['count'];
    }

    // Get detailed requests
    $requests = [];
    if($pdo->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0) {
        $requests_query = "SELECT r.*, u.username, u.users_id 
                          FROM requests r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.status = 'pending' 
                          ORDER BY r.created_at DESC 
                          LIMIT 5";
        $requests = $pdo->query($requests_query)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // Log error and set default values
    error_log("Database Error: " . $e->getMessage());
    $pipeline_count = 0;
    $manager_requests_count = 0;
    $employee_requests_count = 0;
    $requests = [];
}

// Function to get salary data
function getSalaryData($month = null) {
    global $pdo;
    
    if (!$month) {
        $month = date('Y-m');
    }
    
    $query = "
        SELECT 
            u.username as employee_name,
            ss.basic_salary as monthly_salary,
            sr.working_days as total_working_days,
            sr.present_days,
            sr.leave_taken,
            sr.short_leave,
            sr.late_count,
            sr.overtime_hours,
            COALESCE((
                SELECT SUM(amount) 
                FROM travel_expenses 
                WHERE user_id = u.id 
                AND status = 'pending'
                AND DATE_FORMAT(expense_date, '%Y-%m') = :month
            ), 0) as travel_pending,
            COALESCE((
                SELECT SUM(approved_amount) 
                FROM travel_expenses 
                WHERE user_id = u.id 
                AND status = 'approved'
                AND DATE_FORMAT(expense_date, '%Y-%m') = :month
            ), 0) as travel_approved,
            sr.earned_salary as salary_amount,
            sr.overtime_amount,
            sr.travel_amount,
            sr.misc_amount,
            sr.id
        FROM users u
        LEFT JOIN salary_structures ss ON u.id = ss.user_id 
            AND :month BETWEEN DATE_FORMAT(ss.effective_from, '%Y-%m') 
            AND COALESCE(DATE_FORMAT(ss.effective_to, '%Y-%m'), :month)
        LEFT JOIN salary_records sr ON u.id = sr.user_id 
            AND DATE_FORMAT(sr.month, '%Y-%m') = :month
        WHERE u.status = 'active'
        ORDER BY u.username";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute(['month' => $month]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log the error and return an empty array
        error_log("Error fetching salary data: " . $e->getMessage());
        return [];
    }
}

// Get the selected month from URL parameter or use current month
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch salary data
$salary_data = getSalaryData($selected_month);

try {
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    $query = "SELECT * FROM resumes 
              WHERE DATE(created_at) = :date 
              ORDER BY created_at DESC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute(['date' => $date]);
    $resumes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching resumes: " . $e->getMessage());
    $resumes = [];
}

// Add this near the top of your file with other database queries
$user_id = $_SESSION['user_id']; // Assuming you store user_id in session
$profile_query = "SELECT profile_picture FROM users WHERE id = ?";
$stmt = $pdo->prepare($profile_query);
$stmt->execute([$user_id]);
$user_profile = $stmt->fetch();

// Set default avatar if no profile picture is found
// Set default avatar if no profile picture is found
$profile_picture = !empty($user['profile_picture']) 
    ? $user['profile_picture']  // Use the direct URL from database
    : 'assets/images/default-avatar.png';
// In your PHP code where you fetch salary data
$salary_query = "
    SELECT 
        sr.*,
        u.id as user_id,  -- Make sure user_id is included
        u.username as employee_name,
        ss.basic_salary as monthly_salary
    FROM salary_records sr
    JOIN users u ON sr.user_id = u.id
    LEFT JOIN salary_structures ss ON sr.user_id = ss.user_id
    WHERE sr.month = DATE_FORMAT(CURDATE(), '%Y-%m-01')  -- Changed to include day component
    ORDER BY u.username";

try {
    $salary_data = $pdo->query($salary_query)->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Salary query error: " . $e->getMessage());
    $salary_data = [];
}

// Update this section near the top of your file with other database queries
try {
    // Fetch user profile details including profile picture
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

// Remove these old profile picture related lines if they exist
// $user_id = $_SESSION['user_id']; 
// $profile_query = "SELECT profile_picture FROM users WHERE id = ?";
// $stmt = $pdo->prepare($profile_query);
// $stmt->execute([$user_id]);
// $user_profile = $stmt->fetch();

// Remove this old code
// $profile_picture = !empty($user['profile_picture']) 
//     ? $user['profile_picture']
//     : 'assets/images/default-avatar.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard | Modern HR Management</title>
    
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    
    <!-- Add these if they're not already present -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <!-- Add Chart.js plugins -->
    <script>
        // Create global Chart.js instance to ensure it's available
        window.ChartInstance = Chart;
    </script>
    
    <!-- Custom chart initialization script -->
    <script src="assets/js/labour-charts.js"></script>
    
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
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }

        /* Header Section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .user-welcome h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .user-welcome p {
            color: var(--gray);
            font-size: 0.875rem;
        }
        .employee-overview {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            margin: 20px 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            border: 2px solid #e2e8f0;
        }

        .employee-overview.top-section {
            border-radius: 20px 20px 0 0;
            border-bottom: none;
            margin-bottom: 0;
        }

        .employee-overview.bottom-section {
            margin-top: -20px;
            border-top: none;
            border-radius: 0 0 20px 20px;
        }

        .employee-overview.middle-section {
            margin-top: -20px;
            border-top: none;
            border-radius: 0 0 20px 20px;
            margin-bottom: 20px;
        }

        .employee-overview.last-section {
            border-radius: 20px;
            margin-top: 0;
            border-top: 2px solid #e2e8f0;
        }

        .employee-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .employee-stat-box {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }

        .short-leave-box {
            border-left: 4px solid #ffc107;
        }

        .stat-icon {
            margin-bottom: 15px;
        }

        .stat-numbers {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }

        .progress-bar {
            background: #f0f0f0;
            height: 6px;
            border-radius: 3px;
            margin: 10px 0;
        }

        .progress-fill {
            background: #ffc107;
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .tooltip-content {
            padding: 10px;
        }

        .tooltip-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .tooltip-item:last-child {
            border-bottom: none;
        }

        .employee-info, .leave-time, .leave-reason {
            margin: 5px 0;
        }

        .no-data {
            text-align: center;
            color: #666;
            padding: 20px;
        }

        .error-message {
            color: #dc3545;
            text-align: center;
            padding: 20px;
        }

        .stat-content h4 {
            font-size: 1.25rem;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
        }

        .dropdown-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .form-label {
            margin-right: 10px;
            font-weight: 500;
        }

        .form-select {
            width: 150px; /* Adjust width for a smaller dropdown */
            font-size: 0.875rem; /* Smaller font size */
        }

        /* Box-specific styles */
        .present-box .stat-icon {
            background: rgba(16, 185, 129, 0.1);
        }

        .present-box .stat-icon i {
            color: #10b981;
        }

        .present-box .progress-fill {
            background: #10b981;
        }

        .leave-box .stat-icon {
            background: rgba(245, 158, 11, 0.1);
        }

        .leave-box .stat-icon i {
            color: #f59e0b;
        }

        .leave-box .progress-fill {
            background: #f59e0b;
        }

        .pending-box .stat-icon {
            background: rgba(239, 68, 68, 0.1);
        }

        .pending-box .stat-icon i {
            color: #ef4444;
        }

        .pending-box .progress-fill {
            background: #ef4444;
        }

        .short-leave-box .stat-icon {
            background: rgba(99, 102, 241, 0.1);
        }

        .short-leave-box .stat-icon i {
            color: #6366f1;
        }

        .short-leave-box .progress-fill {
            background: #6366f1;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .employee-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .employee-stats-grid {
                grid-template-columns: 1fr;
            }
            .employee-overview {
                margin: 15px;
                padding: 20px;
            }
        }

        /* Updated tooltip styles */
        .employee-stat-box {
            position: relative;
            z-index: 1;
            transition: z-index 0s linear 0.3s;
        }

        .employee-stat-box:hover {
            z-index: 1000;
            transition-delay: 0s;
        }

        .stat-tooltip {
            position: absolute;
            top: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 280px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .employee-stat-box:hover .stat-tooltip {
            opacity: 1;
            visibility: visible;
        }

        .tooltip-header {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .tooltip-header h6 {
            margin: 0;
            font-weight: 600;
            color: #1e293b;
        }

        .tooltip-content {
            padding: 15px;
        }

        .tooltip-footer {
            padding: 8px 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 0.75rem;
            color: #64748b;
            text-align: center;
        }

        /* Site Status Items */
        .site-status-item {
            margin-bottom: 12px;
        }

        .site-status-item:last-child {
            margin-bottom: 0;
        }

        /* Manager List Styles */
        .site-group {
            margin-bottom: 12px;
        }

        .site-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
        }

        .manager-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
        }

        .manager-item i {
            font-size: 1rem;
        }

        .manager-item span {
            font-size: 0.875rem;
        }

        /* Engineer Stats Styles */
        .engineer-stats .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .engineer-stats .stat-label {
            font-size: 0.875rem;
            color: #64748b;
        }

        .engineer-stats .stat-value {
            text-align: right;
            width: 100px;
        }

        .engineer-stats .number {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
            display: block;
        }

        .active-projects {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2563eb;
            font-weight: 500;
        }

        /* Progress bar styles */
        .progress {
            background-color: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        /* Dropdown styles */
        .dropdown-container select {
            border-color: #e5e7eb;
            color: #1e293b;
            font-weight: 500;
        }

        .dropdown-container select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        }

        .tooltip-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .tooltip-item:last-child {
            border-bottom: none;
        }

        .employee-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .employee-name {
            font-weight: 500;
            color: #1e293b;
        }

        .leave-type {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            background: #e2e8f0;
            color: #64748b;
        }

        .leave-duration, .leave-time {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 5px;
        }

        .leave-reason {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 5px;
            font-style: italic;
        }

        .no-data {
            padding: 20px;
            text-align: center;
            color: #64748b;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-right: 5px;
        }

        .status-badge.studio {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }

        .status-badge.manager {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .status-badge.hr {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        /* Scrollbar Styles */
        .tooltip-content::-webkit-scrollbar {
            width: 6px;
        }

        .tooltip-content::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .tooltip-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .tooltip-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Add this after your existing employee stat boxes */
        .employee-stats-grid.additional-boxes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .announcement-box .stat-icon {
            background: rgba(79, 70, 229, 0.1);
        }

        .announcement-box .stat-icon i {
            color: #4F46E5;
        }

        .circular-box .stat-icon {
            background: rgba(245, 158, 11, 0.1);
        }

        .circular-box .stat-icon i {
            color: #F59E0B;
        }

        .event-box .stat-icon {
            background: rgba(16, 185, 129, 0.1);
        }

        .event-box .stat-icon i {
            color: #10B981;
        }

        .holiday-box .stat-icon {
            background: rgba(239, 68, 68, 0.1);
        }

        .holiday-box .stat-icon i {
            color: #EF4444;
        }

        .content-preview {
            margin: 10px 0;
            min-height: 40px;
        }

        .no-content {
            color: #6B7280;
            font-style: italic;
            font-size: 0.875rem;
        }

        .action-button {
            background: none;
            border: none;
            color: var(--primary);
            padding: 0;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: color 0.2s;
        }

        .action-button:hover {
            color: var(--primary-dark);
        }

        .action-button i {
            font-size: 1rem;
        }

        .user-management-box .stat-icon {
            background: rgba(99, 102, 241, 0.1);
        }

        .user-management-box .stat-icon i {
            color: #6366f1;
        }

        .pipeline-box .stat-icon {
            background: rgba(99, 102, 241, 0.1);
        }

        .pipeline-box .stat-icon i {
            color: #6366f1;
        }

        .manager-box .stat-icon {
            background: rgba(245, 158, 11, 0.1);
        }

        .manager-box .stat-icon i {
            color: #f59e0b;
        }

        .employee-requests-box .stat-icon {
            background: rgba(239, 68, 68, 0.1);
        }

        .employee-requests-box .stat-icon i {
            color: #ef4444;
        }

        .announcements-list {
            max-height: 150px;
            overflow-y: auto;
        }

        .announcement-item {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .announcement-title {
            font-weight: 500;
            color: #1e293b;
        }

        .priority-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .priority-badge.high {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .priority-badge.normal {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .priority-badge.low {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .announcement-expiry {
            font-size: 0.75rem;
            color: #64748b;
        }

        .announcement-message {
            font-size: 0.875rem;
            color: #4b5563;
            margin: 8px 0;
            white-space: pre-line;
        }

        .announcement-meta {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            color: #64748b;
        }

        .announcement-meta i {
            margin-right: 4px;
        }

        /* Scrollbar styles for announcements list */
        .announcements-list::-webkit-scrollbar {
            width: 4px;
        }

        .announcements-list::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .announcements-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        .announcements-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .circular-attachment {
            margin-top: 8px;
        }

        .circular-attachment a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .circular-attachment a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .event-type-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .event-type-badge.meeting {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .event-type-badge.training {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .event-type-badge.celebration {
            background: rgba(249, 115, 22, 0.1);
            color: #f97316;
        }

        .event-type-badge.holiday {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .event-type-badge.other {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .event-meta {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 8px;
        }

        .event-meta i {
            margin-right: 4px;
        }

        .event-location {
            margin-top: 4px;
        }

        .events-list {
            max-height: 150px;
            overflow-y: auto;
        }

        .event-item {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .event-item:last-child {
            border-bottom: none;
        }

        /* Add these styles to your existing CSS */
        .modal {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-dialog {
            max-width: 500px;
        }

        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
        }

        /* Holiday type badges */
        .holiday-type-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .holiday-type-badge.public {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .holiday-type-badge.company {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .holiday-type-badge.optional {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .holiday-date {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 4px;
        }

        .holiday-description {
            font-size: 0.875rem;
            color: #4b5563;
            margin-top: 8px;
            font-style: italic;
        }

        .holiday-meta {
            display: flex;
            gap: 16px;
            margin-top: 8px;
            font-size: 0.75rem;
            color: #64748b;
        }

        .holiday-meta i {
            margin-right: 4px;
        }

        .holidays-list {
            max-height: 150px;
            overflow-y: auto;
        }

        .holiday-item {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .holiday-item:last-child {
            border-bottom: none;
        }

        .holiday-title {
            font-weight: 500;
            color: #1e293b;
        }

        /* Add these styles to your existing CSS */
        .tooltip-action-link {
            display: block;
            padding: 12px;
            text-decoration: none;
            color: inherit;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .tooltip-action-link:hover {
            background-color: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            text-decoration: none;
        }

        .tooltip-action-link h6 {
            margin: 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tooltip-action-link p {
            margin: 4px 0 0 24px;
            font-size: 0.875rem;
            color: #64748b;
        }

        .tooltip-section {
            border-bottom: 1px solid #e2e8f0;
        }

        .tooltip-section:last-child {
            border-bottom: none;
        }

        /* Construction Stats Section */
        .employee-overview construction-section {
            border-radius: 20px;
            margin-top: 20px;
            border: 2px solid #e2e8f0;
        }

        .construction-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 20px;
        }

        .construction-stats .employee-stat-box {
            background: #ffffff;
            border-radius: 15px;
            padding: 15px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            display: flex;
            gap: 10px;
            transition: transform 0.3s ease;
            position: relative;
            z-index: 1;
            transition: z-index 0s linear 0.3s;
        }

        .construction-stats .employee-stat-box:hover {
            transform: translateY(-5px);
            z-index: 1000;
            transition-delay: 0s;
        }

        .construction-stats .stat-icon {
            background: rgba(26, 115, 232, 0.1);
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .construction-stats .stat-icon i {
            font-size: 1.5rem;
            color: #1a73e8;
        }

        .construction-stats .stat-content {
            flex: 1;
        }

        .construction-stats .stat-content h4 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .construction-stats .stat-numbers {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: baseline;
            gap: 5px;
        }

        .construction-stats .stat-numbers .divider {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .construction-stats .stat-numbers .total {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .construction-stats .progress-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .construction-stats .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .construction-stats .stat-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        .construction-stats .tooltip-content {
            position: relative;
            z-index: 1002;
            max-height: 300px;
            overflow-y: auto;
            padding: 0;
            background: white;
            border-radius: 12px;
        }

        .construction-stats .tooltip-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .construction-stats .tooltip-item:last-child {
            border-bottom: none;
        }

        .construction-stats .tooltip-header {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .construction-stats .tooltip-content {
            max-height: 300px;
            overflow-y: auto;
            padding: 0;
        }

        .construction-stats .tooltip-item .employee-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .construction-stats .tooltip-item .employee-name {
            font-weight: 500;
            color: #1e293b;
        }

        .construction-stats .tooltip-item .stat-label {
            font-size: 0.75rem;
            color: #64748b;
        }

        .construction-stats .tooltip-item .stat-numbers {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: baseline;
            gap: 5px;
        }

        .construction-stats .tooltip-item .stat-numbers .total {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .construction-stats .tooltip-item .stat-numbers .current {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .construction-stats .tooltip-item .stat-numbers .divider {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .construction-stats .tooltip-item .stat-tooltip {
            position: absolute;
            top: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 320px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1001;
        }

        .construction-stats .tooltip-item .stat-tooltip::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 0 8px 8px 8px;
            border-style: solid;
            border-color: transparent transparent white transparent;
            filter: drop-shadow(0 -2px 2px rgba(0, 0, 0, 0.1));
        }

        .construction-stats .tooltip-item:hover .stat-tooltip {
            opacity: 1;
            visibility: visible;
        }

        .site-overview-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .employee-stat-box {
            background: #ffffff;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .stat-content h4 {
            font-size: 1.25rem;
            margin-bottom: 5px;
        }

        .stat-numbers {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
        }

        .tooltip-footer {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 10px;
        }

        /* Tooltip styles */
        .employee-stat-box:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 10;
            white-space: nowrap;
            width: 200px; /* Adjust width as needed */
        }

        /* Supervisor Styles */
        .supervisor-list {
            font-size: 0.875rem;
        }

        .supervisor-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            color: #4b5563;
        }

        .supervisor-item i {
            font-size: 1rem;
        }

        .progress-info {
            margin-top: 8px;
        }

        /* Labour Stats Styles */
        .labour-stats .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 10px;
        }

        .distribution-item {
            font-size: 0.875rem;
        }

        .skill-distribution {
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
        }

        .skill-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            color: #4b5563;
            margin-bottom: 8px;
        }

        .skill-item i {
            font-size: 1rem;
            color: #6366f1;
        }

        /* Common Tooltip Styles */
        .site-group {
            margin-bottom: 15px;
        }

        .site-group:last-child {
            margin-bottom: 0;
        }

        .progress {
            background-color: #e5e7eb;
            border-radius: 2px;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        /* Task Overview Section Styles */
        .task-section {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
        }

        /* Update these styles */
        .task-stats-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;  /* Changed to 2 columns */
            gap: 20px;
            margin-top: 20px;
        }

        .task-stats-left {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .stat-boxes-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .task-stat-card {
            width: 100%;
            height: 150px;  /* Adjust height as needed */
        }

        .task-calendar {
            height: 100%;  /* Make calendar fill the full height */
            margin-top: 0;  /* Remove top margin */
        }

        .task-calendar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-title {
            display: flex;
            flex-direction: column;
        }

        .calendar-stats {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.875rem;
            color: #64748b;
        }

        .calendar-navigation {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .date-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-filter input[type="date"] {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 4px 8px;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: 500;
            color: #64748b;
            padding: 10px;
        }

        .calendar-day {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .calendar-day:hover {
            background-color: #f1f5f9;
        }

        .calendar-day.today {
            background-color: #6366f1;
            color: white;
        }

        .calendar-day.empty {
            visibility: hidden;
        }

        .calendar-navigation button {
            color: #64748b;
            padding: 5px;
        }

        .calendar-navigation button:hover {
            color: #6366f1;
        }

        .calendar-navigation h6 {
            margin: 0;
            color: #1e293b;
        }

        /* Enhanced Styles */
        .task-stat-card {
            padding: 30px;
            height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-radius: 16px;  /* Added this line */
        }

        .gradient-purple {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
            border-radius: 16px;  /* Added this line */
        }

        .task-stat-card h3 {
            font-size: 1.25rem;
            font-weight: 500;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.9);
        }

        .task-stat-card .stat-number {
            font-size: 2.5rem;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
        }

        .task-stat-card .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .task-stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
        }

        .task-stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .stat-header {
            margin-bottom: 20px;
        }

        .stat-header i {
            font-size: 1.4rem;
            color: #4f46e5;
        }

        .stat-header h4 {
            font-size: 1.1rem;
            font-weight: 500;
            color: #1f2937;
        }

        .stat-body .stat-number {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1f2937;
        }

        .stat-body .text-muted {
            color: #9ca3af;
            font-size: 1.2rem;
        }

        .stat-body .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .progress {
            height: 6px;
            background-color: #f3f4f6;
            border-radius: 10px;
            margin-top: 20px;
        }

        .progress-bar {
            border-radius: 10px;
            background-color: #4f46e5;
        }

        .progress-bar.bg-danger {
            background-color: #ef4444;
        }

        /* Calendar Styles */
        .task-calendar {
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .calendar-title h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .calendar-stats {
            margin-top: 8px;
        }

        .stat-item {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .stat-item i {
            font-size: 1rem;
        }

        .stat-item i.text-primary {
            color: #4f46e5 !important;
        }

        .stat-item i.text-success {
            color: #10b981 !important;
        }

        .calendar-navigation h6 {
            font-size: 1rem;
            font-weight: 500;
        }

        .calendar-day-header {
            font-size: 0.9rem;
            font-weight: 500;
            color: #6b7280;
        }

        .calendar-day {
            font-size: 0.9rem;
            color: #1f2937;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .calendar-day.today {
            background-color: #4f46e5;
            font-weight: 500;
        }

        /* Tooltip Styles */
        .priority-tooltip {
            padding: 8px 12px;
            font-size: 13px;
            background-color: #1f2937;
            border-radius: 8px;
        }

        .priority-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            color: #fff;
            white-space: nowrap;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot.high {
            background-color: #ef4444;
        }

        .dot.medium {
            background-color: #f59e0b;
        }

        .dot.low {
            background-color: #10b981;
        }

        /* Custom Bootstrap Tooltip Styles */
        .tooltip .tooltip-inner {
            background-color: #1f2937;
            padding: 10px 15px;
            border-radius: 8px;
            max-width: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .tooltip.bs-tooltip-top .tooltip-arrow::before {
            border-top-color: #1f2937;
        }

        /* Salary Overview Section Styles */
        .salary-section {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
        }

        .salary-section .table {
            font-size: 0.9rem;
        }

        .salary-section .table th {
            background-color: #f8fafc;
            font-weight: 500;
            text-align: center;
            vertical-align: middle;
        }

        .salary-section .table td {
            vertical-align: middle;
            text-align: center;
        }

        .salary-section .table tbody tr:hover {
            background-color: #f1f5f9;
        }

        .salary-section .actions {
            display: flex;
            gap: 10px;
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
        }

        /* Add these styles to your existing CSS */
        .profile-dropdown {
            min-width: 240px;
            padding: 0;
            margin-top: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            background: white;
        }

        .profile-dropdown .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: 12px 12px 0 0;
        }

        .profile-dropdown .dropdown-header h6 {
            margin: 0;
            color: #1e293b;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .profile-dropdown .dropdown-header small {
            color: #64748b;
            font-size: 0.825rem;
            margin-top: 2px;
            display: block;
        }

        .profile-dropdown .dropdown-item {
            padding: 0.875rem 1rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .profile-dropdown .dropdown-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .profile-dropdown .dropdown-item:hover {
            background-color: #f1f5f9;
            color: #4f46e5;
        }

        .profile-dropdown .dropdown-item.text-danger {
            color: #dc2626;
        }

        .profile-dropdown .dropdown-item.text-danger:hover {
            background-color: #fef2f2;
            color: #dc2626;
        }

        .profile-dropdown .dropdown-divider {
            margin: 0.5rem 0;
            border-color: #e5e7eb;
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

        /* Animation for dropdown */
        .dropdown-menu.show {
            animation: dropdownFade 0.2s ease-out;
        }

        @keyframes dropdownFade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .attendance-action {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .attendance-btn {
            padding: 8px 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .attendance-btn i {
            font-size: 1.1rem;
        }

        .attendance-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .punch-time {
            font-size: 0.875rem;
            color: #6B7280;
            white-space: nowrap;
        }

        /* Button States */
        .btn-success {
            background-color: #10B981;
            border-color: #10B981;
        }

        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
        }

        .btn-danger {
            background-color: #EF4444;
            border-color: #EF4444;
        }

        .btn-danger:hover {
            background-color: #DC2626;
            border-color: #DC2626;
        }

        .btn-secondary:disabled {
            background-color: #9CA3AF;
            border-color: #9CA3AF;
            cursor: not-allowed;
        }

        .leave-user-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.leave-user-name {
    font-weight: 500;
    color: #1e293b;
}

.leave-type {
    font-size: 0.875rem;
    color: #64748b;
    padding: 2px 8px;
    background: #f1f5f9;
    border-radius: 12px;
}

.leave-details {
    font-size: 0.875rem;
    color: #64748b;
}

.leave-dates {
    margin-bottom: 4px;
}

.leave-reason {
    color: #64748b;
}

.leave-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.leave-action-btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 4px;
}

.leave-accept-btn {
    background-color: #22c55e;
    color: white;
}

.leave-reject-btn {
    background-color: #ef4444;
    color: white;
}

.leave-action-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.leave-action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Updated styles for the pending leaves box */
.leave-user-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.user-details {
    display: flex;
    align-items: center;
    gap: 8px;
}

.employee-id {
    color: #64748b;
    font-size: 0.875rem;
}

.leave-type-badge {
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    color: #475569;
}

.leave-details {
    font-size: 0.875rem;
    color: #64748b;
    margin-bottom: 12px;
}

.leave-dates {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.leave-reason {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    line-height: 1.4;
}

.leave-actions {
    display: flex;
    gap: 8px;
}

.leave-action-btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.875rem;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
}

.leave-accept-btn {
    background-color: #22c55e;
    color: white;
}

.leave-reject-btn {
    background-color: #ef4444;
    color: white;
}

.leave-action-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.leave-action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.error-message {
    color: #ef4444;
    padding: 12px;
    text-align: center;
    font-size: 0.875rem;
}

/* Animation for removing items */
.tooltip-item {
    transition: all 0.3s ease;
}

.tooltip-item.removing {
    opacity: 0;
    transform: translateX(-20px);
}

/* Add this to your existing styles */
.manager-approval {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.875rem;
    color: #059669;
    background: #f0fdf4;
    padding: 4px 8px;
    border-radius: 4px;
    margin-top: 8px;
}

.manager-approval i {
    font-size: 1rem;
}

/* Add these new styles */
.approval-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.875rem;
    padding: 4px 8px;
    border-radius: 4px;
    margin-top: 8px;
    margin-bottom: 8px;
}

.approval-status.manager-approved {
    color: #059669;
    background: #f0fdf4;
}

.approval-status.pending-manager-approval {
    color: #d97706;
    background: #fffbeb;
}

.approval-status.manager-rejected {
    color: #dc2626;
    background: #fef2f2;
}

.approval-status.awaiting-manager-review {
    color: #6b7280;
    background: #f3f4f6;
}

.approval-status i {
    font-size: 1rem;
}

.action-reason {
    font-style: italic;
    opacity: 0.9;
}

/* Update existing tooltip-item style */
.tooltip-item {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.tooltip-item:last-child {
    border-bottom: none;
}

.tooltip-item:hover {
    background-color: #f8fafc;
}

.leave-action-btn {
    padding: 5px 15px;
    margin: 0 5px;
    border-radius: 4px;
    cursor: pointer;
}

.leave-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.leave-accept-btn {
    background-color: #4CAF50;
    color: white;
    border: none;
}

.leave-reject-btn {
    background-color: #f44336;
    color: white;
    border: none;
}

.leave-action-btn {
    padding: 6px 12px;
    margin: 0 4px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
}

.leave-accept-btn {
    background-color: #10B981;
    color: white;
}

.leave-reject-btn {
    background-color: #EF4444;
    color: white;
}

.leave-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.leave-actions {
    margin-top: 10px;
}

.approval-section {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.approval-label {
    font-weight: 500;
    color: #666;
}

.leave-action-btn {
    padding: 5px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.leave-accept-btn {
    background-color: #10B981;
    color: white;
}

.leave-reject-btn {
    background-color: #EF4444;
    color: white;
}

.leave-status {
    margin-top: 5px;
}

.status-text {
    font-size: 0.9rem;
    padding: 3px 8px;
    border-radius: 4px;
}

.status-text.awaiting {
    background-color: #FEF3C7;
    color: #92400E;
}

.status-text.rejected {
    background-color: #FEE2E2;
    color: #991B1B;
}

.delete-circular {
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.delete-circular:hover {
    opacity: 1;
}

.tooltip-item {
    position: relative;
}

.tooltip-item .btn-link {
    text-decoration: none;
}

    .delete-item {
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .delete-item:hover {
        opacity: 1;
    }

    .tooltip-item {
        position: relative;
    }

    .tooltip-item .btn-link {
        text-decoration: none;
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
            <a href="hr_dashboard.php" class="nav-link active">
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
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="hr_overtime_approval.php" class="nav-link">
                <i class="bi bi-clock"></i>
                Overtime Approval
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

    <!-- Add this button after the sidebar div -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="header">
            <div class="user-welcome">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
                <p><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="user-actions d-flex align-items-center gap-3">
                <!-- Add Punch In/Out Button -->
                <?php
                // Check if user has already punched in today
                $today = date('Y-m-d');
                $user_id = $_SESSION['user_id'];
                
                $attendance_query = "SELECT punch_in, punch_out FROM attendance 
                                   WHERE user_id = ? AND DATE(date) = ?";
                $stmt = $pdo->prepare($attendance_query);
                $stmt->execute([$user_id, $today]);
                $attendance = $stmt->fetch();

                $isPunchedIn = !empty($attendance['punch_in']);
                $isPunchedOut = !empty($attendance['punch_out']);
                ?>
                
                <div class="attendance-action">
                    <?php if (!$isPunchedIn): ?>
                        <button class="btn btn-success attendance-btn" onclick="punchIn()">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Punch In
                        </button>
                    <?php elseif (!$isPunchedOut): ?>
                        <button class="btn btn-danger attendance-btn" onclick="punchOut()">
                            <i class="bi bi-box-arrow-right"></i>
                            Punch Out
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary attendance-btn" disabled>
                            <i class="bi bi-check2-circle"></i>
                            Shift Complete
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($isPunchedIn && !$isPunchedOut): ?>
                        <div class="punch-time">
                            Punched in at: <?php echo date('h:i A', strtotime($attendance['punch_in'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <button class="btn btn-light">
                    <i class="bi bi-bell"></i>
                </button>
                <!-- Modified profile section -->
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
                    
                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown" aria-labelledby="profileDropdown">
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

      <!-- Employee Overview Section -->
        <div class="employee-overview top-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="bi bi-people-fill"></i>
                    Employees Overview
                </div>
            </div>

            <div class="employee-stats-grid">
                <!-- Present Users Box -->
                <div class="employee-stat-box present-box">
                    <div class="stat-icon">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Present Today</h4>
                        <div class="stat-numbers">
                            <span class="current"><?php echo $employee_stats['present_users']; ?></span>
                            <span class="divider">/</span>
                            <span class="total"><?php echo $employee_stats['total_users']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($employee_stats['present_users'] / max(1, $employee_stats['total_users'])) * 100; ?>%"></div>
                        </div>
                        <div class="stat-label">Total Employees</div>
                    </div>
                    
                    <!-- Present Users Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Present Employees</span>
                            <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                        </div>
                        <div class="tooltip-content">
                            <?php
                            $present_details_query = "
                                SELECT u.username, u.id, a.punch_in 
                                FROM attendance a 
                                JOIN users u ON a.user_id = u.id 
                                WHERE DATE(a.date) = CURDATE() 
                                AND a.punch_in IS NOT NULL 
                                ORDER BY a.punch_in DESC";
                            
                            $present_details = $pdo->query($present_details_query)->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($present_details)) {
                                foreach ($present_details as $employee) {
                                    echo "<div class='tooltip-item'>";
                                    echo "<div class='employee-info'>";
                                    echo "<span class='employee-name'><i class='bi bi-person-circle'></i> " . htmlspecialchars($employee['username']) . "</span>";
                                    echo "<span class='employee-time'>" . date('h:i A', strtotime($employee['punch_in'])) . "</span>";
                                    echo "</div>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='no-data'><i class='bi bi-info-circle'></i> No employees present yet</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Users on Leave Box -->
                <div class="employee-stat-box leave-box">
                    <div class="stat-icon">
                        <i class="bi bi-calendar2-x-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>On Leave</h4>
                        <div class="stat-numbers">
                            <?php
                            // Get count of users currently on approved leave
                            $on_leave_count_query = "
                                SELECT COUNT(DISTINCT lr.user_id) as users_on_leave 
                                FROM leave_request lr 
                                WHERE lr.status = 'approved' 
                                AND CURDATE() BETWEEN lr.start_date AND lr.end_date";
                            $on_leave_count = $pdo->query($on_leave_count_query)->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <span class="current"><?php echo $on_leave_count['users_on_leave']; ?></span>
                            <span class="divider">/</span>
                            <span class="total"><?php echo $employee_stats['total_users']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($on_leave_count['users_on_leave'] / max(1, $employee_stats['total_users'])) * 100; ?>%"></div>
                        </div>
                        <div class="stat-label">Full Day Leave</div>
                    </div>

                    <!-- Leave Users Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Employees on Leave</span>
                            <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                        </div>
                        <div class="tooltip-content">
                            <?php
                            $leave_details_query = "
                                SELECT u.username, u.id, lr.leave_type, lr.start_date, lr.end_date, lr.reason 
                                FROM leave_request lr 
                                JOIN users u ON lr.user_id = u.id 
                                WHERE lr.status = 'approved' 
                                AND CURDATE() BETWEEN lr.start_date AND lr.end_date 
                                ORDER BY lr.start_date ASC";

                            
                            $leave_details = $pdo->query($leave_details_query)->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($leave_details)) {
                                foreach ($leave_details as $leave) {
                                    $duration = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / (60 * 60 * 24) + 1;
                                    echo "<div class='tooltip-item'>";
                                    echo "<div class='employee-info'>";
                                    echo "<span class='employee-name'><i class='bi bi-person-circle'></i> " . htmlspecialchars($leave['username'] ?? '') . "</span>";
                                    echo "<span class='leave-type'>" . htmlspecialchars($leave['leave_type'] ?? '') . "</span>";
                                    echo "</div>";
                                    echo "<div class='leave-duration'>";
                                    echo "<i class='bi bi-calendar3'></i> ";
                                    echo date('d M', strtotime($leave['start_date']));
                                    if ($leave['start_date'] != $leave['end_date']) {
                                        echo " - " . date('d M', strtotime($leave['end_date']));
                                    }
                                    echo " <span class='days-count'>($duration days)</span>";
                                    echo "</div>";
                                    if (!empty($leave['reason'])) {
                                        echo "<div class='leave-reason'>";
                                        echo "<i class='bi bi-chat-left-text'></i> " . htmlspecialchars($leave['reason'] ?? '');
                                        echo "</div>";
                                    }
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='no-data'><i class='bi bi-info-circle'></i> No employees on leave today</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Pending Leaves Box -->
                <div class="employee-stat-box pending-box">
                    <div class="stat-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Pending Leaves</h4>
                        <div class="stat-numbers">
                            <span class="current" id="pendingLeavesCount"><?php echo $employee_stats['pending_leaves']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($employee_stats['pending_leaves'] / max(1, $employee_stats['total_users'])) * 100); ?>%"></div>
                        </div>
                        <div class="stat-label">Awaiting Approval</div>
                    </div>

                    <!-- Pending Leaves Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Pending Leave Requests</span>
                            <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                        </div>
                        <div class="tooltip-content" id="pendingLeavesContent">
                            <?php
                            // Fetch all pending leaves
                            $query = "SELECT 
                                        lr.*,
                                        u.username,
                                        u.employee_id
                                     FROM leave_request lr
                                     JOIN users u ON lr.user_id = u.id
                                     WHERE (
                                        lr.status = 'pending' 
                                        OR lr.status = 'pending_hr'
                                        OR (lr.manager_approval = 'approved' AND lr.hr_approval IS NULL)
                                        OR (lr.manager_approval IS NULL AND lr.status != 'rejected')
                                        OR (lr.hr_approval IS NULL AND lr.status != 'rejected')
                                     )
                                     ORDER BY lr.created_at DESC";
                            
                            try {
                                $stmt = $pdo->prepare($query);
                                $stmt->execute();
                                $pendingLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($pendingLeaves)): 
                                    foreach ($pendingLeaves as $leave): ?>
                                        <div class="tooltip-item" id="leave-item-<?php echo $leave['id']; ?>">
                                            <div class="leave-user-info">
                                                <div class="user-details">
                                                    <span class="leave-user-name">
                                                        <i class="bi bi-person-circle"></i>
                                                        <?php echo htmlspecialchars($leave['username'] ?? ''); ?>
                                                    </span>
                                                    <span class="employee-id">(<?php echo htmlspecialchars($leave['employee_id'] ?? ''); ?>)</span>
                                                </div>
                                                <span class="leave-type-badge">
                                                    <?php echo htmlspecialchars($leave['leave_type'] ?? ''); ?>
                                                </span>
                                            </div>
                                            <div class="leave-details">
                                                <div class="leave-dates">
                                                    <i class="bi bi-calendar3"></i>
                                                    <?php 
                                                    $start_date = date('M d, Y', strtotime($leave['start_date']));
                                                    $end_date = date('M d, Y', strtotime($leave['end_date']));
                                                    echo $start_date;
                                                    if ($leave['start_date'] !== $leave['end_date']) {
                                                        echo ' - ' . $end_date;
                                                    }
                                                    echo ' (' . $leave['duration'] . ' days)';
                                                    ?>
                                                </div>
                                                <?php if (!empty($leave['reason'])): ?>
                                                    <div class="leave-reason">
                                                        <i class="bi bi-chat-left-text"></i>
                                                        <?php echo htmlspecialchars($leave['reason'] ?? ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($leave['manager_approval']) && $leave['manager_approval'] == 'approved'): ?>
                                                    <div class="approval-status manager-approved">
                                                        <i class="bi bi-check-circle"></i>
                                                        Manager Approved
                                                        <?php if (!empty($leave['manager_approval_reason'])): ?>
                                                            <span class="action-reason">: <?php echo htmlspecialchars($leave['manager_approval_reason'] ?? ''); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif (!empty($leave['manager_approval']) && $leave['manager_approval'] == 'rejected'): ?>
                                                    <div class="approval-status manager-rejected">
                                                        <i class="bi bi-x-circle"></i>
                                                        Manager Rejected
                                                        <?php if (!empty($leave['manager_approval_reason'])): ?>
                                                            <span class="action-reason">: <?php echo htmlspecialchars($leave['manager_approval_reason'] ?? ''); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Simplified Action Buttons -->
                                                <div class="leave-actions">
                                                    <div class="approval-section">
                                                        <button type="button" 
                                                                class="leave-action-btn leave-accept-btn" 
                                                                onclick="handleLeaveAction(<?php echo (int)$leave['id']; ?>, 'approve', 'all', this)">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                        <button type="button" 
                                                                class="leave-action-btn leave-reject-btn" 
                                                                onclick="handleLeaveAction(<?php echo (int)$leave['id']; ?>, 'reject', 'all', this)">
                                                            <i class="bi bi-x-circle"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                else: ?>
                                    <div class="no-data">
                                        <i class="bi bi-check-circle"></i>
                                        No pending leave requests
                                    </div>
                                <?php endif;
                            } catch (PDOException $e) {
                                echo '<div class="error-message">Error fetching leave requests</div>';
                                error_log("Leave request query error: " . $e->getMessage());
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Short Leave Box -->
                <div class="employee-stat-box short-leave-box">
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-content">
                        <?php
                        // Get total active users
                        $total_users_query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
                        
                        // Get short leave count for today
                        $short_leave_count_query = "
                            SELECT COUNT(DISTINCT lr.user_id) as short_leave_count 
                            FROM leave_request lr
                            JOIN leave_types lt ON lr.leave_type = lt.name
                            WHERE lt.name = 'Short Leave'
                            AND lr.status = 'approved'
                            AND lr.hr_approval = 'approved'
                            AND DATE(lr.start_date) = CURDATE()
                            AND lr.manager_approval = 'approved'";
                        
                        try {
                            $total_users = $pdo->query($total_users_query)->fetch(PDO::FETCH_ASSOC);
                            $short_leave_count = $pdo->query($short_leave_count_query)->fetch(PDO::FETCH_ASSOC);
                            
                            $on_short_leave = $short_leave_count['short_leave_count'] ?? 0;
                            $total = $total_users['total'] ?? 0;
                        } catch (PDOException $e) {
                            error_log("Error fetching short leave counts: " . $e->getMessage());
                            $on_short_leave = 0;
                            $total = 0;
                        }
                        ?>
                        <h4>Short Leave</h4>
                        <div class="stat-numbers">
                            <span class="current"><?php echo $on_short_leave; ?></span>
                            <span class="divider">/</span>
                            <span class="total"><?php echo $total; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($total > 0) ? ($on_short_leave / $total * 100) : 0; ?>%"></div>
                        </div>
                        <div class="stat-label">Today's Short Leaves</div>
                    </div>

                    <!-- Short Leave Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Short Leave Details</span>
                            <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                        </div>
                        <div class="tooltip-content">
                            <?php
                            $short_leave_details_query = "
                                SELECT lr.*, u.username 
                                FROM leave_request lr
                                JOIN users u ON lr.user_id = u.id 
                                WHERE lr.leave_type = 'Short Leave'
                                AND lr.status = 'approved'
                                AND lr.hr_approval = 'approved'
                                AND DATE(lr.start_date) = CURDATE()
                                ORDER BY lr.time_from ASC";
                            
                            try {
                                $short_leave_details = $pdo->query($short_leave_details_query)->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($short_leave_details)) {
                                    foreach ($short_leave_details as $leave) {
                                        echo "<div class='tooltip-item'>";
                                        echo "<div class='employee-info'>";
                                        echo "<span class='employee-name'><i class='bi bi-person-circle'></i> " . htmlspecialchars($leave['username'] ?? '') . "</span>";
                                        echo "</div>";
                                        echo "<div class='leave-time'>";
                                        echo "<i class='bi bi-clock'></i> ";
                                        echo date('h:i A', strtotime($leave['time_from'])) . " - " . date('h:i A', strtotime($leave['time_to']));
                                        echo "</div>";
                                        if (!empty($leave['reason'])) {
                                            echo "<div class='leave-reason'>";
                                            echo "<i class='bi bi-chat-left-text'></i> " . htmlspecialchars($leave['reason'] ?? '');
                                            echo "</div>";
                                        }
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div class='no-data'><i class='bi bi-info-circle'></i> No short leaves today</div>";
                                }
                            } catch (PDOException $e) {
                                error_log("Error fetching short leave details: " . $e->getMessage());
                                echo "<div class='error-message'>Error loading short leave details</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Employee Overview Section (with middle 4 boxes) -->
        <div class="employee-overview middle-section">
            <div class="employee-stats-grid">
                <!-- Announcements Box -->
                <div class="employee-stat-box announcement-box">
                    <div class="stat-icon">
                        <i class="bi bi-megaphone-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Announcements</h4>
                        <div class="content-preview">
                            <?php if (empty($announcements)): ?>
                                <p class="no-content">No active announcements</p>
                            <?php else: ?>
                                <div class="announcements-list">
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="announcement-item">
                                            <div class="announcement-header">
                                                <span class="announcement-title">
                                                    <?php echo isset($announcement['title']) ? htmlspecialchars($announcement['title']) : 'Untitled'; ?>
                                                </span>
                                                <?php if (isset($announcement['priority']) && $announcement['priority'] === 'high'): ?>
                                                    <span class="priority-badge high">High Priority</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (isset($announcement['display_until']) && $announcement['display_until']): ?>
                                                <div class="announcement-expiry">
                                                    Valid until: <?php echo date('d M Y', strtotime($announcement['display_until'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="action-button" onclick="showAnnouncementModal()">
                            <i class="bi bi-plus-circle"></i> Add New
                        </button>
                    </div>

                    <!-- Announcements Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>All Announcements</span>
                            <span class="badge bg-primary"><?php echo count($announcements); ?> Active</span>
                        </div>
                        <div class="tooltip-content">
                            <?php if (empty($announcements)): ?>
                                <div class="no-data">No active announcements</div>
                            <?php else: ?>
                                <?php foreach ($announcements as $announcement): ?>
                                    <div class="tooltip-item" id="announcement-<?php echo $announcement['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="announcement-info">
                                                <span class="announcement-title">
                                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                                </span>
                                                <span class="priority-badge <?php echo $announcement['priority']; ?>">
                                                    <?php echo ucfirst($announcement['priority']); ?>
                                                </span>
                                            </div>
                                            <button class="btn btn-link text-danger p-0 delete-item" 
                                                    onclick="deleteItem('announcement', <?php echo $announcement['id']; ?>)"
                                                    title="Delete Announcement">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <?php if (isset($announcement['message']) && !empty($announcement['message'])): ?>
                                            <div class="announcement-message">
                                                <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="announcement-meta">
                                            <span class="announcement-date">
                                                <i class="bi bi-calendar3"></i>
                                                Posted: <?php echo isset($announcement['created_at']) ? date('d M Y', strtotime($announcement['created_at'])) : 'N/A'; ?>
                                            </span>
                                            <?php if (isset($announcement['display_until']) && $announcement['display_until']): ?>
                                                <span class="announcement-expiry">
                                                    <i class="bi bi-clock"></i>
                                                    Expires: <?php echo date('d M Y', strtotime($announcement['display_until'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Circulars Box -->
                <div class="employee-stat-box circular-box">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-text-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Circulars</h4>
                        <div class="content-preview">
                            <?php if (empty($circulars)): ?>
                                <p class="no-content">No active circulars</p>
                            <?php else: ?>
                                <div class="circulars-list">
                                    <?php foreach ($circulars as $circular): ?>
                                        <div class="circular-item">
                                            <div class="circular-header">
                                                <span class="circular-title">
                                                    <?php echo htmlspecialchars($circular['title']); ?>
                                                </span>
                                            </div>
                                            <?php if ($circular['valid_until']): ?>
                                                <div class="circular-expiry">
                                                    Valid until: <?php echo date('d M Y', strtotime($circular['valid_until'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="action-button" onclick="showCircularModal()">
                            <i class="bi bi-plus-circle"></i> Add New
                        </button>
                    </div>

                    <!-- Circulars Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>All Circulars</span>
                            <span class="badge bg-primary"><?php echo count($circulars); ?> Active</span>
                        </div>
                        <div class="tooltip-content">
                            <?php if (empty($circulars)): ?>
                                <div class="no-data">No active circulars</div>
                            <?php else: ?>
                                <?php foreach ($circulars as $circular): ?>
                                    <div class="tooltip-item" id="circular-<?php echo $circular['id']; ?>">
                                        <div class="circular-info">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="circular-title">
                                                    <?php echo htmlspecialchars($circular['title']); ?>
                                                </div>
                                                <button class="btn btn-link text-danger p-0 delete-circular" 
                                                        onclick="deleteCircular(<?php echo $circular['id']; ?>)"
                                                        title="Delete Circular">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <div class="circular-message">
                                                <?php echo nl2br(htmlspecialchars($circular['description'])); ?>
                                            </div>
                                            <?php if ($circular['attachment_path']): ?>
                                                <div class="circular-attachment">
                                                    <a href="<?php echo htmlspecialchars($circular['attachment_path']); ?>" 
                                                       target="_blank"
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-paperclip"></i> Download Attachment
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Events Box -->
                <div class="employee-stat-box event-box">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Events</h4>
                        <div class="content-preview">
                            <?php if (empty($events)): ?>
                                <p class="no-content">No upcoming events</p>
                            <?php else: ?>
                                <div class="events-list">
                                    <?php foreach ($events as $event): ?>
                                        <div class="event-item">
                                            <div class="event-header">
                                                <span class="event-title">
                                                    <?php echo htmlspecialchars($event['title']); ?>
                                                </span>
                                                <span class="event-type-badge <?php echo $event['event_type']; ?>">
                                                    <?php echo ucfirst($event['event_type']); ?>
                                                </span>
                                            </div>
                                            <div class="event-date">
                                                <i class="bi bi-calendar3"></i>
                                                <?php echo date('d M Y', strtotime($event['event_date'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="action-button" onclick="showEventModal()">
                            <i class="bi bi-plus-circle"></i> Add New
                        </button>
                    </div>

                    <!-- Events Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Upcoming Events</span>
                            <span class="badge bg-primary"><?php echo count($events); ?> Events</span>
                        </div>
                        <div class="tooltip-content">
                            <?php if (empty($events)): ?>
                                <div class="no-data">No upcoming events</div>
                            <?php else: ?>
                                <?php foreach ($events as $event): ?>
                                    <div class="tooltip-item" id="event-<?php echo $event['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="event-info">
                                                <span class="event-title">
                                                    <?php echo htmlspecialchars($event['title']); ?>
                                                </span>
                                            </div>
                                            <button class="btn btn-link text-danger p-0 delete-item" 
                                                    onclick="deleteItem('event', <?php echo $event['id']; ?>)"
                                                    title="Delete Event">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <div class="event-description">
                                            <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                        </div>
                                        <div class="event-meta">
                                            <div class="event-datetime">
                                                <i class="bi bi-calendar3"></i>
                                                <?php echo date('d M Y', strtotime($event['event_date'])); ?>
                                                <?php if ($event['start_time']): ?>
                                                    <i class="bi bi-clock ms-2"></i>
                                                    <?php 
                                                    echo date('h:i A', strtotime($event['start_time']));
                                                    if ($event['end_time']) {
                                                        echo ' - ' . date('h:i A', strtotime($event['end_time']));
                                                    }
                                                    ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($event['location']): ?>
                                                <div class="event-location">
                                                    <i class="bi bi-geo-alt"></i>
                                                    <?php echo htmlspecialchars($event['location']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Holidays Box -->
                <div class="employee-stat-box holiday-box">
                    <div class="stat-icon">
                        <i class="bi bi-calendar2-heart-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Upcoming Holiday</h4>
                        <div class="content-preview">
                            <?php if (empty($holidays)): ?>
                                <p class="no-content">No upcoming holidays</p>
                            <?php else: ?>
                                <div class="holidays-list">
                                    <?php foreach ($holidays as $holiday): ?>
                                        <div class="holiday-item">
                                            <div class="holiday-header">
                                                <span class="holiday-title">
                                                    <?php echo isset($holiday['title']) ? htmlspecialchars($holiday['title']) : 'Unnamed Holiday'; ?>
                                                </span>
                                                <?php if (isset($holiday['holiday_type'])): ?>
                                                    <span class="holiday-type-badge <?php echo strtolower($holiday['holiday_type']); ?>">
                                                        <?php echo htmlspecialchars($holiday['holiday_type']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (isset($holiday['holiday_date'])): ?>
                                                <div class="holiday-date">
                                                    <i class="bi bi-calendar3"></i>
                                                    <?php echo date('d M Y', strtotime($holiday['holiday_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (isset($holiday['description']) && !empty($holiday['description'])): ?>
                                                <div class="holiday-description">
                                                    <?php echo htmlspecialchars($holiday['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="action-button" onclick="showHolidayModal()">
                            <i class="bi bi-plus-circle"></i> Add New
                        </button>
                    </div>

                    <!-- Holidays Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Upcoming Holidays</span>
                            <span class="badge bg-primary"><?php echo count($holidays); ?> Holidays</span>
                        </div>
                        <div class="tooltip-content">
                            <?php if (empty($holidays)): ?>
                                <div class="no-data">No upcoming holidays</div>
                            <?php else: ?>
                                <div class="holidays-list">
                                    <?php foreach ($holidays as $holiday): ?>
                                        <div class="holiday-item" id="holiday-<?php echo $holiday['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="holiday-info">
                                                    <span class="holiday-title">
                                                        <?php echo htmlspecialchars($holiday['title']); ?>
                                                    </span>
                                                </div>
                                                <button class="btn btn-link text-danger p-0 delete-item" 
                                                        onclick="deleteItem('holiday', <?php echo $holiday['id']; ?>)"
                                                        title="Delete Holiday">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <?php if (isset($holiday['holiday_date'])): ?>
                                                <div class="holiday-date">
                                                    <i class="bi bi-calendar3"></i>
                                                    <?php echo date('d M Y', strtotime($holiday['holiday_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (isset($holiday['description']) && !empty($holiday['description'])): ?>
                                                <div class="holiday-description">
                                                    <?php echo htmlspecialchars($holiday['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Third Employee Overview Section (with last 4 boxes) -->
        <div class="employee-overview last-section">
            <div class="employee-stats-grid">
                <!-- Add/Delete User Box -->
                <div class="employee-stat-box user-management-box">
                    <div class="stat-icon">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Add / Delete User</h4>
                        <div class="content-preview">
                            <p>Manage employee accounts</p>
                        </div>
                        <div class="action-buttons">
                            <button class="action-button" onclick="showAddUserModal()">
                                <i class="bi bi-plus-circle"></i> Add User
                            </button>
                            <button class="action-button text-danger" onclick="showManageUsersModal()">
                                <i class="bi bi-person-x"></i> Manage Users
                            </button>
                        </div>
                    </div>

                    <!-- Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>User Management</span>
                        </div>
                        <div class="tooltip-content">
                            <div class="tooltip-section">
                                <a href="signup.php" class="tooltip-action-link">
                                    <h6><i class="bi bi-plus-circle"></i> Add New User</h6>
                                    <p>Create new employee accounts with roles and permissions</p>
                                </a>

                            </div>
                            <div class="tooltip-section">
                                <a href="edit_employee.php" class="tooltip-action-link">
                                    <h6><i class="bi bi-person-x"></i> Manage Existing Users</h6>
                                    <p>Edit, deactivate, or remove employee accounts</p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pipeline Employees Box -->
                <div class="employee-stat-box pipeline-box">
                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Employees in Pipeline</h4>
                        <div class="stat-numbers">
                            <?php echo $pipeline_count; ?> Selected
                        </div>
                        <button class="action-button" onclick="showPipelineModal()">
                            <i class="bi bi-eye"></i> View Details
                        </button>
                    </div>

                    <!-- Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Pipeline Employees</span>
                            <span class="badge bg-primary"><?php echo $pipeline_count; ?> Total</span>
                        </div>
                        <div class="tooltip-content">
                            <?php
                            $pipeline_details_query = "
                                SELECT 
                                    username, 
                                    position, 
                                    created_at  /* using created_at instead of selection_date */
                                FROM users 
                                WHERE status = 'pipeline' 
                                ORDER BY created_at DESC 
                                LIMIT 5";

                            try {
                                $pipeline_details = $pdo->query($pipeline_details_query)->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                error_log("Pipeline query error: " . $e->getMessage());
                                $pipeline_details = [];
                            }

                            if (!empty($pipeline_details)) {
                                foreach ($pipeline_details as $employee) {
                                    echo "<div class='tooltip-item'>";
                                    echo "<div class='employee-info'>";
                                    echo "<span class='employee-name'>" . htmlspecialchars($employee['username']) . "</span>";
                                    echo "<span class='position-badge'>" . htmlspecialchars($employee['position']) . "</span>";
                                    echo "</div>";
                                    echo "<div class='selection-date'>Selected: " . date('M d, Y', strtotime($employee['created_at'])) . "</div>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='no-data'>No employees in pipeline</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Manager Requests Box -->
                <div class="employee-stat-box manager-box">
                    <div class="stat-icon">
                        <i class="bi bi-briefcase-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Manager Requests</h4>
                        <div class="stat-numbers">
                            <?php echo $manager_requests_count; ?> Pending
                        </div>
                        <button class="action-button" onclick="showManagerRequestsModal()">
                            <i class="bi bi-list-check"></i> View Requests
                        </button>
                    </div>

                    <!-- Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Manager Requests</span>
                            <span class="badge bg-warning"><?php echo $manager_requests_count; ?> Pending</span>
                        </div>
                        <div class="tooltip-content">
                            <?php
                            $manager_requests = array_filter($requests, function($req) {
                                return $req['type'] === 'manager';
                            });

                            if (!empty($manager_requests)) {
                                foreach ($manager_requests as $request) {
                                    echo "<div class='tooltip-item'>";
                                    echo "<div class='request-info'>";
                                    echo "<span class='requester-name'>" . htmlspecialchars($request['username']) . "</span>";
                                    echo "<span class='request-type'>" . htmlspecialchars($request['request_type']) . "</span>";
                                    echo "</div>";
                                    echo "<div class='request-date'>Submitted: " . date('M d, Y', strtotime($request['created_at'])) . "</div>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='no-data'>No pending manager requests</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Employee Requests Box -->
                <div class="employee-stat-box employee-requests-box">
                    <div class="stat-icon">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Employee Requests</h4>
                        <div class="stat-numbers">
                            <?php echo $employee_requests_count; ?> Pending
                        </div>
                        <button class="action-button" onclick="showEmployeeRequestsModal()">
                            <i class="bi bi-list-check"></i> View Requests
                        </button>
                    </div>

                    <!-- Tooltip -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Employee Requests</span>
                            <span class="badge bg-warning"><?php echo $employee_requests_count; ?> Pending</span>
                        </div>
                        <div class="tooltip-content">
                            <?php
                            $employee_requests = array_filter($requests, function($req) {
                                return $req['type'] === 'employee';
                            });

                            if (!empty($employee_requests)) {
                                foreach ($employee_requests as $request) {
                                    echo "<div class='tooltip-item'>";
                                    echo "<div class='request-info'>";
                                    echo "<span class='requester-name'>" . htmlspecialchars($request['username']) . "</span>";
                                    echo "<span class='request-type'>" . htmlspecialchars($request['request_type']) . "</span>";
                                    echo "</div>";
                                    echo "<div class='request-date'>Submitted: " . date('M d, Y', strtotime($request['created_at'])) . "</div>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='no-data'>No pending employee requests</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Site Overview Section -->
        <div class="employee-overview last-section">
            <div class="section-header mb-4">
                <div class="section-title">
                    <i class="bi bi-buildings"></i>
                    Site Overview
                </div>
                <div class="dropdown-container">
                    <select class="form-select" id="siteFilter">
                        <option value="all">All Construction</option>
                        <option value="site1">Site 1</option>
                        <option value="site2">Site 2</option>
                        <option value="site3">Site 3</option>
                    </select>
                </div>
            </div>

            <div class="site-overview-grid">
                <div class="employee-stat-box" data-site-stat="total">
                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Total Labour Present Today</h4>
                        <?php
                        // Fetch total labour present today from database
                        $today = date('Y-m-d');
                        
                        // Query to count company labours present today
                        $company_labour_query = "SELECT COUNT(*) as company_labour_count 
                                               FROM sv_company_labours 
                                               WHERE (morning_attendance = 1 OR evening_attendance = 1)
                                               AND attendance_date = ?
                                               AND is_deleted = 0";
                        
                        // Query to count vendor labours present today
                        $vendor_labour_query = "SELECT COUNT(*) as vendor_labour_count 
                                              FROM sv_vendor_labours 
                                              WHERE (morning_attendance = 1 OR evening_attendance = 1)
                                              AND attendance_date = ?
                                              AND is_deleted = 0";
                        
                        try {
                            // Get company labour count
                            $stmt = $pdo->prepare($company_labour_query);
                            $stmt->execute([$today]);
                            $company_labour_result = $stmt->fetch(PDO::FETCH_ASSOC);
                            $company_labour_count = $company_labour_result['company_labour_count'] ?? 0;
                            
                            // Get vendor labour count
                            $stmt = $pdo->prepare($vendor_labour_query);
                            $stmt->execute([$today]);
                            $vendor_labour_result = $stmt->fetch(PDO::FETCH_ASSOC);
                            $vendor_labour_count = $vendor_labour_result['vendor_labour_count'] ?? 0;
                            
                            // Calculate total labour count
                            $total_labour_count = $company_labour_count + $vendor_labour_count;
                        } catch (PDOException $e) {
                            error_log("Error fetching labour count: " . $e->getMessage());
                            $total_labour_count = 0;
                        }
                        ?>
                        <div class="stat-numbers"><?php echo $total_labour_count; ?></div>
                        <div class="stat-label">Present today</div>
                        <button class="view-details-btn" data-site-type="total">View Details</button>
                    </div>
                </div>

                <div class="employee-stat-box" data-site-stat="manager">
                    <div class="stat-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Manager on Site</h4>
                        <div class="stat-numbers">2</div>
                        <div class="stat-label">Present today</div>
                        <button class="view-details-btn" data-site-type="manager">View Details</button>
                                    </div>
                                </div>

                <div class="employee-stat-box" data-site-stat="engineer">
                    <div class="stat-icon">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Engineer on Site</h4>
                        <div class="stat-numbers">4</div>
                        <div class="stat-label">Present today</div>
                        <button class="view-details-btn" data-site-type="engineer">View Details</button>
                                    </div>
                                </div>

                <div class="employee-stat-box" data-site-stat="supervisor">
                    <div class="stat-icon">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Supervisor on Site</h4>
                        <div class="stat-numbers">6</div>
                        <div class="stat-label">Present today</div>
                        <button class="view-details-btn" data-site-type="supervisor">View Details</button>
                                    </div>
                                </div>

                <div class="employee-stat-box" data-site-stat="labour">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Labour Attendance</h4>
                        
                        <div class="stat-label">Fetching Data...</div>
                        <button class="view-details-btn" data-site-type="labour">View Details</button>
                    </div>
                        </div>
                                    </div>
                                </div>

        <!-- Link to external CSS and JS files for Site Overview -->
        <link rel="stylesheet" href="assets/css/site-overview.css">
        <script src="assets/js/site-overview.js" defer></script>
        
        
            
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
    });

    function filterTasks(category) {
        window.location.href = '?category=' + encodeURIComponent(category);
    }

    function viewCircular(id) {
        // Create and show modal with circular details
        const modal = document.createElement('div');
        modal.className = 'circular-modal';
        
        // Fetch circular details using AJAX
        fetch(`get_circular.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>${data.title}</h5>
                            <button type="button" class="close-btn" onclick="this.closest('.circular-modal').remove()">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            ${data.description ? `
                                <div class="circular-description">${data.description}</div>
                            ` : ''}
                            ${data.attachment_path ? `
                                <div class="circular-attachment">
                                    <a href="${data.attachment_path}" target="_blank" class="attachment-btn">
                                        <i class="bi bi-paperclip"></i>
                                        View Attachment
                                    </a>
                                </div>
                            ` : ''}
                            <div class="circular-meta">
                                <div class="meta-item">
                                    <i class="bi bi-person"></i> Posted by ${data.creator_name}
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-calendar3"></i> ${new Date(data.created_at).toLocaleDateString()}
                                </div>
                                ${data.valid_until ? `
                                    <div class="meta-item validity">
                                        <i class="bi bi-clock"></i> Valid until ${new Date(data.valid_until).toLocaleDateString()}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        
        document.body.appendChild(modal);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Handle Announcement Form
        const announcementForm = document.getElementById('announcementForm');
        if (announcementForm) {
            announcementForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('add_announcement.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('announcementModal')).hide();
                        showToast('Success', 'Announcement added successfully');
                        location.reload(); // Reload to show new announcement
                    } else {
                        showToast('Error', data.message || 'Failed to add announcement');
                    }
                });
            });
        }

        // Handle Circular Form
        const circularForm = document.getElementById('circularForm');
        if (circularForm) {
            circularForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Adding...';

                const formData = new FormData(this);

                fetch('add_circular.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showToast('Success', 'Circular added successfully');
                        
                        // Close modal and refresh page
                        const modal = bootstrap.Modal.getInstance(document.getElementById('circularModal'));
                        modal.hide();
                        location.reload();
                    } else {
                        throw new Error(data.message || 'Failed to add circular');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error', error.message);
                })
                .finally(() => {
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Add Circular';
                });
            });
        }

        // Handle Event Form
        const eventForm = document.getElementById('eventForm');
        if (eventForm) {
            eventForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate times if both are provided
                const startTime = this.start_time.value;
                const endTime = this.end_time.value;
                if (startTime && endTime && startTime >= endTime) {
                    showToast('Error', 'End time must be after start time');
                    return;
                }

                const formData = new FormData(this);

                fetch('add_event.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('eventModal'));
                        modal.hide();
                        showToast('Success', 'Event added successfully');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showToast('Error', data.message || 'Failed to add event');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error', 'An error occurred while adding the event');
                });
            });

            // Add time validation on input
            const startTimeInput = eventForm.querySelector('#start_time');
            const endTimeInput = eventForm.querySelector('#end_time');
            
            endTimeInput.addEventListener('change', function() {
                if (startTimeInput.value && this.value && startTimeInput.value >= this.value) {
                    showToast('Error', 'End time must be after start time');
                    this.value = '';
                }
            });
        }

        // Handle Holiday Form
        const holidayForm = document.getElementById('holidayForm');
        if (holidayForm) {
            holidayForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('add_holiday.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('holidayModal')).hide();
                        showToast('Success', 'Holiday added successfully');
                        location.reload();
                    } else {
                        showToast('Error', data.message || 'Failed to add holiday');
                    }
                });
            });
        }

        // Toast notification function
        function showToast(title, message) {
            const toastHTML = `
                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.innerHTML = toastHTML;
            document.body.appendChild(toastContainer);
            
            const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
            toast.show();
            
            // Remove toast container after hiding
            toastContainer.querySelector('.toast').addEventListener('hidden.bs.toast', () => {
                toastContainer.remove();
            });
        }
    });

    function showAnnouncementModal() {
        const modal = new bootstrap.Modal(document.getElementById('announcementModal'));
        modal.show();
    }

    function showCircularModal() {
        const modal = new bootstrap.Modal(document.getElementById('circularModal'));
        modal.show();
    }

    document.getElementById('attachment').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload PDF, DOC, DOCX, XLS, XLSX, JPG, or PNG files only.');
                this.value = ''; // Clear the file input
            }
            
            // Check file size (e.g., 5MB limit)
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            if (file.size > maxSize) {
                alert('File size must be less than 5MB');
                this.value = ''; // Clear the file input
            }
        }
    });

    // Add this with your other JavaScript code
    function showEventModal() {
        const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
        eventModal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Event Form Handler
        const eventForm = document.getElementById('eventForm');
        if (eventForm) {
            eventForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate times if both are provided
                const startTime = this.start_time.value;
                const endTime = this.end_time.value;
                if (startTime && endTime && startTime >= endTime) {
                    showToast('Error', 'End time must be after start time');
                    return;
                }

                // Create FormData object
                const formData = new FormData(this);

                // Log form data for debugging
                console.log('Form data being sent:', Object.fromEntries(formData));

                fetch('add_event.php', {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON response:', text);
                        throw new Error('Invalid server response');
                    }
                })
                .then(data => {
                    console.log('Server response:', data);
                    if (data.success) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('eventModal'));
                        modal.hide();
                        showToast('Success', 'Event added successfully');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        let errorMessage = data.message;
                        if (data.debug_info) {
                            errorMessage += '\n\nDebug Info:\n' + 
                                JSON.stringify(data.debug_info, null, 2);
                        }
                        showToast('Error', errorMessage);
                        console.error('Error details:', data);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showToast('Error', 'An error occurred while adding the event: ' + error.message);
                });
            });
        }
    });

    // Toast function (you can replace this with a better-looking toast)
    function showToast(title, message) {
        // Create a Bootstrap toast if you have Bootstrap included
        if (typeof bootstrap !== 'undefined') {
            const toastHTML = `
                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            const toastContainer = document.getElementById('toast-container') || document.createElement('div');
            if (!document.getElementById('toast-container')) {
                toastContainer.id = 'toast-container';
                toastContainer.style.position = 'fixed';
                toastContainer.style.top = '20px';
                toastContainer.style.right = '20px';
                toastContainer.style.zIndex = '1050';
                document.body.appendChild(toastContainer);
            }
            
            toastContainer.innerHTML = toastHTML;
            const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
            toast.show();
        } else {
            // Fallback to alert if Bootstrap is not available
            alert(`${title}: ${message}`);
        }
    }

    // Add this to your existing JavaScript
    function showHolidayModal() {
        const modal = new bootstrap.Modal(document.getElementById('holidayModal'));
        modal.show();
    }

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover',
                placement: 'top'
            });
        });
    });

    function viewSalaryDetails(id) {
        if (id === 0) {
            alert('No salary details available');
            return;
        }
        // You can implement a modal or redirect to a detailed view
        // For now, we'll just show an alert
        alert('Viewing salary details for ID: ' + id);
    }

    function handleLeave(leaveId, action) {
        // Show confirmation dialog
        const confirmMessage = action === 'approve' ? 
            'Are you sure you want to approve this leave request?' : 
            'Are you sure you want to reject this leave request?';

        if (!confirm(confirmMessage)) {
            return;
        }

        // Prompt for reason (for both approve and reject)
        let actionReason = prompt(
            action === 'approve' ? 
            'Please provide comments for approval:' : 
            'Please provide reason for rejection:'
        );
        
        // Check if user cancelled the prompt
        if (actionReason === null) return;

        // Validate reason is not empty
        if (actionReason.trim() === '') {
            alert('Please provide a reason for ' + (action === 'approve' ? 'approval' : 'rejection'));
            return;
        }

        // Prepare the data
        const formData = new FormData();
        formData.append('leave_id', leaveId);
        formData.append('action', action);
        formData.append('reason', actionReason);

        // Send AJAX request
        fetch('handle_leave_request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                // Reload the page to update the leave requests
                location.reload();
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request');
        });
    }

    function deleteCircular(id) {
        if (!confirm('Are you sure you want to delete this circular?')) {
            return;
        }

        const formData = new FormData();
        formData.append('id', id);

        fetch('delete_circular.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the circular item from DOM
                document.getElementById(`circular-${id}`).remove();
                
                // Show success message
                showToast('Success', 'Circular deleted successfully');
                
                // If no more circulars, show no data message
                const tooltipContent = document.querySelector('.tooltip-content');
                if (!tooltipContent.querySelector('.tooltip-item')) {
                    tooltipContent.innerHTML = '<div class="no-data">No active circulars</div>';
                }
            } else {
                throw new Error(data.message || 'Failed to delete circular');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', error.message);
        });
    }

    // Add this if you haven't already defined showToast
    function showToast(title, message) {
        const toastHTML = `
            <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>
        `;
        
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.innerHTML = toastHTML;
        document.body.appendChild(toastContainer);
        
        const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
        toast.show();
        
        // Remove toast container after hiding
        toastContainer.querySelector('.toast').addEventListener('hidden.bs.toast', () => {
            toastContainer.remove();
        });
    }

    function deleteItem(type, id) {
        const itemTypes = {
            'announcement': 'announcement',
            'event': 'event',
            'holiday': 'holiday'
        };

        if (!confirm(`Are you sure you want to delete this ${type}?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('type', type);
        formData.append('id', id);

        fetch('delete_item.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the item from DOM
                const item = document.getElementById(`${type}-${id}`);
                if (item) {
                    item.remove();
                }

                // Show success message
                showToast('Success', data.message);

                // Check if no items left and show no data message
                const tooltipContent = item.closest('.tooltip-content');
                if (tooltipContent && !tooltipContent.querySelector('.tooltip-item')) {
                    tooltipContent.innerHTML = `<div class="no-data">No ${type === 'announcement' ? 'active' : 'upcoming'} ${type}s</div>`;
                }

                // Update the count in the main box if necessary
                updateItemCount(type);
            } else {
                throw new Error(data.message || `Failed to delete ${type}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', error.message);
        });
    }

    // Function to update item counts
    function updateItemCount(type) {
        const countElements = {
            'announcement': document.querySelector('.announcement-box .stat-numbers'),
            'event': document.querySelector('.event-box .stat-numbers'),
            'holiday': document.querySelector('.holiday-box .stat-numbers')
        };

        const countElement = countElements[type];
        if (countElement) {
            let currentCount = parseInt(countElement.textContent) || 0;
            if (currentCount > 0) {
                countElement.textContent = (currentCount - 1).toString();
            }
        }
    }

    // Add CSS for delete button
    const style = document.createElement('style');
    style.textContent = `
        .delete-item {
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .delete-item:hover {
            opacity: 1;
        }

        .tooltip-item {
            position: relative;
        }

        .tooltip-item .btn-link {
            text-decoration: none;
        }
    `;
    document.head.appendChild(style);
    </script>

    <!-- Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="announcementModalLabel">Add New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="announcementForm">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Message</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="display_until" class="form-label">Display Until (Optional)</label>
                            <input type="date" class="form-control" id="display_until" name="display_until">
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Circular Modal -->
    <div class="modal fade" id="circularModal" tabindex="-1" aria-labelledby="circularModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="circularModalLabel">Add New Circular</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="circularForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="attachment" class="form-label">Attachment (Optional)</label>
                            <input type="file" class="form-control" id="attachment" name="attachment_path">
                        </div>
                        <div class="mb-3">
                            <label for="valid_until" class="form-label">Valid Until (Optional)</label>
                            <input type="date" class="form-control" id="valid_until" name="valid_until">
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Circular</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Place this right before </body> tag -->
    <!-- Events Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="eventForm">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Event Date</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time">
                            </div>
                            <div class="col">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location">
                        </div>
                        <div class="mb-3">
                            <label for="event_type" class="form-label">Event Type</label>
                            <select class="form-select" id="event_type" name="event_type">
                                <option value="meeting">Meeting</option>
                                <option value="training">Training</option>
                                <option value="celebration">Celebration</option>
                                <option value="holiday">Holiday</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Event</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this for toast container -->
    <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 1050;"></div>

    <!-- Add this before the closing </body> tag -->
    <!-- Holiday Modal -->
    <div class="modal fade" id="holidayModal" tabindex="-1" aria-labelledby="holidayModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="holidayModalLabel">Add New Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="holidayForm">
                        <div class="mb-3">
                            <label for="holiday_title" class="form-label">Holiday Name</label>
                            <input type="text" class="form-control" id="holiday_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="holiday_date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="holiday_date" name="holiday_date" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="holiday_type" class="form-label">Holiday Type</label>
                            <select class="form-control" id="holiday_type" name="holiday_type" required>
                                <option value="public">Public Holiday</option>
                                <option value="company">Company Holiday</option>
                                <option value="optional">Optional Holiday</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="holiday_description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="holiday_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Holiday</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this JavaScript for calendar functionality -->
    <script>
        function generateCalendar() {
            const calendar = document.querySelector('.calendar-grid');
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            
            // Add day headers
            days.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'calendar-day-header';
                dayHeader.textContent = day;
                calendar.appendChild(dayHeader);
            });

            // Add calendar days
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            // Add empty cells for days before first day of month
            for (let i = 0; i < firstDay.getDay(); i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'calendar-day empty';
                calendar.appendChild(emptyDay);
            }

            // Add actual days
            for (let i = 1; i <= lastDay.getDate(); i++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                dayCell.textContent = i;
                
                if (i === today.getDate()) {
                    dayCell.classList.add('today');
                }
                
                calendar.appendChild(dayCell);
            }
        }

        document.addEventListener('DOMContentLoaded', generateCalendar);
    </script>
    
    <!-- Add these script tags before closing body tag -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <!-- Initialize Bootstrap dropdowns -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'))
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl)
            });
        });
    </script>

    <script>
    async function punchIn() {
        try {
            const response = await fetch('punch_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'punch_in',
                    location: await getCurrentLocation()
                })
            });

            const data = await response.json();
            
            if (data.success) {
                showToast('Success', 'Punched in successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error', data.message || 'Failed to punch in', 'error');
            }
        } catch (error) {
            showToast('Error', 'Failed to punch in', 'error');
            console.error('Error:', error);
        }
    }

    async function punchOut() {
        try {
            const response = await fetch('punch_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'punch_out',
                    location: await getCurrentLocation()
                })
            });

            const data = await response.json();
            
            if (data.success) {
                showToast('Success', 'Punched out successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error', data.message || 'Failed to punch out', 'error');
            }
        } catch (error) {
            showToast('Error', 'Failed to punch out', 'error');
            console.error('Error:', error);
        }
    }

    async function getCurrentLocation() {
        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject);
            });
            
            return {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            };
        } catch (error) {
            console.error('Error getting location:', error);
            return null;
        }
    }

    function showToast(title, message, type = 'info') {
        // Implement your toast notification here
        // You can use libraries like Toastify or create your own
        console.log(`${title}: ${message}`);
    }
    </script>

    <!-- Add this modal for editing salary -->
    <div class="modal fade" id="editSalaryModal" tabindex="-1" aria-labelledby="editSalaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSalaryModalLabel">Edit Salary Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editSalaryForm">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="basic_salary" class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" id="basic_salary" name="basic_salary" required>
                            </div>
                            <div class="col-md-6">
                                <label for="effective_from" class="form-label">Effective From</label>
                                <input type="date" class="form-control" id="effective_from" name="effective_from" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="overtime_hours" class="form-label">Overtime Hours</label>
                                <input type="number" class="form-control" id="overtime_hours" name="overtime_hours">
                            </div>
                            <div class="col-md-4">
                                <label for="travel_amount" class="form-label">Travel Amount</label>
                                <input type="number" class="form-control" id="travel_amount" name="travel_amount">
                            </div>
                            <div class="col-md-4">
                                <label for="misc_amount" class="form-label">Miscellaneous Amount</label>
                                <input type="number" class="form-control" id="misc_amount" name="misc_amount">
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this JavaScript code -->
    <script>
    function editSalaryDetails(userId) {
        console.log('Edit button clicked for user:', userId); // Add this debug line
        if (!userId) {
            showToast('Error', 'Invalid user ID');
            return;
        }

        // Make sure Bootstrap is available
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap is not loaded');
            return;
        }

        try {
            // Fetch current salary details
            fetch(`get_salary_details.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the form
                        document.getElementById('edit_user_id').value = userId;
                        document.getElementById('basic_salary').value = data.salary_structure.basic_salary || '';
                        document.getElementById('effective_from').value = data.salary_structure.effective_from || '';
                        document.getElementById('overtime_hours').value = data.salary_record.overtime_hours || '';
                        document.getElementById('travel_amount').value = data.salary_record.travel_amount || '';
                        document.getElementById('misc_amount').value = data.salary_record.misc_amount || '';

                        // Show the modal
                        const modal = new bootstrap.Modal(document.getElementById('editSalaryModal'));
                        modal.show();
                    } else {
                        console.error('API Error:', data.message);
                        showToast('Error', data.message || 'Failed to fetch salary details');
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    showToast('Error', 'Failed to fetch salary details');
                });
        } catch (error) {
            console.error('Function Error:', error);
            showToast('Error', 'Failed to fetch salary details');
        }
    }

    // Make sure the showToast function exists
    function showToast(title, message) {
        // If you don't have a toast implementation, you can use alert temporarily
        alert(`${title}: ${message}`);
    }
    </script>

    <script>
    // Add this JavaScript function to handle leave actions
    function handleLeaveAction(leaveId, action, approvalType, button) {
        // Prevent double submission
        if (button) {
            button.disabled = true;
        }

        // Get reason for action
        const actionReason = prompt(`Please provide reason for ${action}ing the leave request:`);
        if (!actionReason || actionReason.trim() === '') {
            alert('A reason is required to process the leave request.');
            if (button) button.disabled = false;
            return;
        }

        // Send request to new endpoint
        fetch('process_leave_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                leave_id: parseInt(leaveId),
                action: action,
                approval_type: approvalType || 'all', // 'all', 'manager', or 'hr'
                action_reason: actionReason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showToast('Success', data.message, 'success');
                
                // Remove the item from the list with animation
                const leaveItem = document.getElementById(`leave-item-${leaveId}`);
                if (leaveItem) {
                    leaveItem.classList.add('removing');
                    setTimeout(() => {
                        leaveItem.remove();
                        
                        // Update the counter
                        const counterElement = document.getElementById('pendingLeavesCount');
                        if (counterElement) {
                            const currentCount = parseInt(counterElement.textContent);
                            if (!isNaN(currentCount) && currentCount > 0) {
                                counterElement.textContent = currentCount - 1;
                            }
                        }
                        
                        // Check if no more items
                        const tooltipContent = document.getElementById('pendingLeavesContent');
                        if (tooltipContent && !tooltipContent.querySelector('.tooltip-item')) {
                            tooltipContent.innerHTML = '<div class="no-data"><i class="bi bi-check-circle"></i> No pending leave requests</div>';
                        }
                    }, 300);
                } else {
                    // If can't find the item, just reload the page
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            } else {
                throw new Error(data.message || 'Failed to process leave request');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', error.message, 'error');
        })
        .finally(() => {
            if (button) button.disabled = false;
        });
    }

    // Helper function to show notifications
    function showNotification(message, type = 'success') {
        // Implement your notification system here
        // This is just a basic alert, replace with your preferred notification system
        alert(message);
    }
    </script>
</body>
</html>