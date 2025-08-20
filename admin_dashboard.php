<?php
session_start();
include 'config/db_connect.php';

// Check if admin is logged in and has admin role, if not redirect to login page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get today's date
$today = date('Y-m-d');

// Initialize variables
$totalUsers = 0;
$presentUsers = 0;
$absentUsers = 0;
$onLeaveUsers = 0;
$activeProjects = 0;
$totalTasks = 0;
$pendingLeaves = 0;

// Initialize additional variables for detailed statistics
$total_projects = 0;
$architecture_count = 0;
$construction_count = 0;
$interior_count = 0;
$architecture_tasks = 0;
$construction_tasks = 0;
$interior_tasks = 0;
$total_pending = 0;
$manager_pending = 0;
$hr_pending = 0;
$present_count = 0;
$leaves_count = 0;

// Initialize user counts
$total_users = 0;
$present_count = 0;
$leaves_count = 0;
$absent_count = 0;

// Initialize attendance variables
$total_users = 0;
$present_count = 0;
$leaves_count = 0;
$absent_count = 0;

// Get current month's data
$current_month = date('m');
$current_year = date('Y');
$first_day_month = date('Y-m-01');
$last_day_month = date('Y-m-t');

// Initialize payment variables
$monthly_received = 0;
$monthly_total = 0;
$architecture_payments = 0;
$construction_payments = 0;
$interior_payments = 0;
$received_payments = 0;
$pending_payments = 0;
$overdue_payments = 0;

// Add this debug code at the top after database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Simplified query to test database connection
$test_query = "SELECT COUNT(*) as count FROM projects";
$test_result = $conn->query($test_query);
if ($test_result) {
    $count = $test_result->fetch_assoc()['count'];
    error_log("Total projects in database: " . $count);
} else {
    error_log("Test query failed: " . $conn->error);
}

// Simplified pipeline projects query
$pipeline_projects = [];
try {
    $pipeline_query = "
        SELECT 
            id,
            project_name,
            project_type,
            client_name,
            mobile,
            location,
            total_cost,
            status,
            created_at
        FROM projects 
        ORDER BY created_at DESC
        LIMIT 15";
    
    error_log("Executing query: " . $pipeline_query);
    
    $result = $conn->query($pipeline_query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        $pipeline_projects[] = $row;
    }
    
    error_log("Found " . count($pipeline_projects) . " projects");
    
} catch (Exception $e) {
    error_log("Error fetching pipeline projects: " . $e->getMessage());
}

// Debug output
echo "<!-- Debug: Number of projects found: " . count($pipeline_projects) . " -->";
if (empty($pipeline_projects)) {
    echo "<!-- Debug: No projects found -->";
} else {
    echo "<!-- Debug: First project: " . print_r($pipeline_projects[0], true) . " -->";
}

try {
    // Query to count total users
    $sql = "SELECT COUNT(*) as total_users FROM users";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $data = mysqli_fetch_assoc($result);
        $total_users = $data['total_users'];
        
        // Debug log
        error_log("Total Users Query: " . $sql);
        error_log("Total Users Result: " . $total_users);
    }

    // Query for today's attendance statistics
    $attendance_sql = "
        SELECT 
            COUNT(DISTINCT a.user_id) as present_count
        FROM attendance a
        INNER JOIN users u ON a.user_id = u.id
        WHERE DATE(a.date) = ? 
        AND a.punch_in IS NOT NULL 
        AND u.status = 'active'";
    
    $stmt = $conn->prepare($attendance_sql);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    
    if ($attendance_result && $row = $attendance_result->fetch_assoc()) {
        $present_count = $row['present_count'];
    }
    $stmt->close();

    // Query for users on leave today
    $leaves_sql = "
        SELECT COUNT(DISTINCT l.user_id) as leaves_count
        FROM leaves l
        INNER JOIN users u ON l.user_id = u.id
        WHERE ? BETWEEN l.start_date AND l.end_date
        AND l.status = 'approved'
        AND u.status = 'active'";
    
    $stmt = $conn->prepare($leaves_sql);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $leaves_result = $stmt->get_result();
    
    if ($leaves_result && $row = $leaves_result->fetch_assoc()) {
        $leaves_count = $row['leaves_count'];
    }
    $stmt->close();

    // Calculate absent users (total - present - on leave)
    $absent_count = $total_users - $present_count - $leaves_count;

    // Debug logs
    error_log("Attendance Statistics for $today:");
    error_log("Total Users: $total_users");
    error_log("Present Count: $present_count");
    error_log("Leaves Count: $leaves_count");
    error_log("Absent Count: $absent_count");

    // Get detailed attendance information for tooltip
    $detailed_attendance_sql = "
        SELECT 
            u.id,
            u.username,
            u.employee_id,
            a.punch_in,
            a.punch_out,
            a.overtime
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND DATE(a.date) = ?
        WHERE u.status = 'active'
        ORDER BY u.username";
    
    $stmt = $conn->prepare($detailed_attendance_sql);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $detailed_result = $stmt->get_result();
    
    $attendance_details = [];
    while ($row = $detailed_result->fetch_assoc()) {
        $attendance_details[] = $row;
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Error in attendance calculations: " . $e->getMessage());
    // Keep default values for error case
}

// Verify the date being used
error_log("Date being used: " . $today);

// Fetch project statistics
try {
    // Project type distribution
    $query = "SELECT 
        SUM(CASE WHEN project_type = 'architecture' THEN 1 ELSE 0 END) as architecture_count,
        SUM(CASE WHEN project_type = 'construction' THEN 1 ELSE 0 END) as construction_count,
        SUM(CASE WHEN project_type = 'interior' THEN 1 ELSE 0 END) as interior_count
    FROM projects 
    WHERE status = 'active'";
    
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $architecture_count = $row['architecture_count'];
        $construction_count = $row['construction_count'];
        $interior_count = $row['interior_count'];
        $total_projects = $architecture_count + $construction_count + $interior_count;
    }
} catch (Exception $e) {
    error_log("Error fetching project statistics: " . $e->getMessage());
}

// Fetch task statistics
$task_counts = [];
try {
    $db = new PDO("mysql:host=localhost;dbname=login_system", "root", "");
    
    // Get delayed and completed task counts
    $count_query = "SELECT 
        SUM(CASE WHEN due_date < CURRENT_DATE() AND status != 'Completed' THEN 1 ELSE 0 END) as delayed_count,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count
    FROM tasks";
    
    $task_counts = $db->query($count_query)->fetch(PDO::FETCH_ASSOC);
    
    // Set the variables
    $delayed_count = $task_counts['delayed_count'] ?? 0;
    $completed_count = $task_counts['completed_count'] ?? 0;
    
} catch(PDOException $e) {
    // Handle any database errors
    error_log("Error fetching task counts: " . $e->getMessage());
    $delayed_count = 0;
    $completed_count = 0;
}

// If for some reason the query fails, ensure we have a default value
if (!isset($total_tasks)) {
    $total_tasks = 0;
}

// Fetch leave statistics
try {
    // Pending leave requests
    $query = "SELECT 
        SUM(CASE WHEN approval_level = 'manager' THEN 1 ELSE 0 END) as manager_pending,
        SUM(CASE WHEN approval_level = 'hr' THEN 1 ELSE 0 END) as hr_pending
    FROM leaves 
    WHERE status = 'pending' 
    AND ('$today' BETWEEN start_date AND end_date)";
    
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $manager_pending = $row['manager_pending'];
        $hr_pending = $row['hr_pending'];
        $total_pending = $manager_pending + $hr_pending;
    }
} catch (Exception $e) {
    error_log("Error fetching leave statistics: " . $e->getMessage());
}

// You can also verify the current date being used
error_log("Current Date: " . $today);
error_log("CURRENT_DATE(): " . date('Y-m-d'));

// Fetch admin username
$adminUsername = '';
$userId = $_SESSION['user_id'];
try {
    $query = "SELECT username FROM users WHERE id = ? AND role = 'admin'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($adminUsername);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    // Handle error silently or log it
    error_log("Error fetching admin username: " . $e->getMessage());
}

// Add this PHP code for sales calculations
function fetchSalesData($conn, $from_date, $end_date) {
    $sales_data = [
        'total_sales' => 0,
        'architecture' => 0,
        'interior' => 0,
        'construction' => 0,
        'project_counts' => [
            'total' => 0,
            'architecture' => 0,
            'interior' => 0,
            'construction' => 0
        ]
    ];

    try {
        $query = "
            SELECT 
                project_type,
                COUNT(*) as project_count,
                SUM(total_cost) as total_value
            FROM projects 
            WHERE created_at BETWEEN ? AND ?
                AND status != 'cancelled'
                AND (archived_date IS NULL OR archived_date > ?)
            GROUP BY project_type";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $from_date, $end_date, $from_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $value = $row['total_value'] / 100000; // Convert to Lakhs
            $sales_data['total_sales'] += $value;
            $sales_data['project_counts']['total'] += $row['project_count'];
            
            switch ($row['project_type']) {
                case 'architecture':
                    $sales_data['architecture'] = $value;
                    $sales_data['project_counts']['architecture'] = $row['project_count'];
                    break;
                case 'interior':
                    $sales_data['interior'] = $value;
                    $sales_data['project_counts']['interior'] = $row['project_count'];
                    break;
                case 'construction':
                    $sales_data['construction'] = $value;
                    $sales_data['project_counts']['construction'] = $row['project_count'];
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching sales data: " . $e->getMessage());
    }
    
    return $sales_data;
}

// Default to current month if no dates selected
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Fetch sales data
$sales_data = fetchSalesData($conn, $from_date, $end_date);

// Add these PHP queries before the HTML section
try {
    // Get total projects count for each type
    $projects_query = "SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN project_type = 'architecture' THEN 1 ELSE 0 END) as architecture_total,
        SUM(CASE WHEN project_type = 'interior' THEN 1 ELSE 0 END) as interior_total,
        SUM(CASE WHEN project_type = 'construction' THEN 1 ELSE 0 END) as construction_total
        FROM projects 
        WHERE status != 'cancelled'";
    
    $result = $conn->query($projects_query);
    $total_all_projects = 0;
    $architecture_total = 0;
    $interior_total = 0;
    $construction_total = 0;
    
    if ($result && $row = $result->fetch_assoc()) {
        $total_all_projects = $row['total_projects'];
        $architecture_total = $row['architecture_total'];
        $interior_total = $row['interior_total'];
        $construction_total = $row['construction_total'];
    }

    // Get today's tasks with the correct column names
    $tasks_query = "SELECT t.*, 
            u.username as assigned_user_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE DATE(t.due_date) = CURRENT_DATE
        ORDER BY 
            CASE 
                WHEN t.priority = 'high' THEN 1
                WHEN t.priority = 'medium' THEN 2
                WHEN t.priority = 'low' THEN 3
                ELSE 4
            END,
            t.due_time ASC";
    
    $tasks_result = $conn->query($tasks_query);
    $today_tasks = [];
    
    if ($tasks_result) {
        while ($task = $tasks_result->fetch_assoc()) {
            $today_tasks[] = $task;
        }
    }

} catch (Exception $e) {
    error_log("Error fetching project overview data: " . $e->getMessage());
    $today_tasks = []; // Initialize to empty array if there's an error
}

// Task Overview Data
$task_stats = [];
try {
    $db = new PDO("mysql:host=localhost;dbname=crm", "root", "");
    
    // Get total tasks count
    $total_tasks_query = "SELECT COUNT(*) as total FROM tasks";
    $total_tasks = $db->query($total_tasks_query)->fetch(PDO::FETCH_ASSOC)['total'];

    // Get priority distribution
    $priority_query = "SELECT priority, COUNT(*) as count 
                      FROM tasks 
                      GROUP BY priority";
    $priority_stmt = $db->query($priority_query);
    $priority_stats = [
        'high' => 0,
        'medium' => 0,
        'low' => 0
    ];
    while ($row = $priority_stmt->fetch(PDO::FETCH_ASSOC)) {
        $priority_stats[strtolower($row['priority'])] = $row['count'];
    }

    // Get stages count (based on unique status values)
    $stages_query = "SELECT COUNT(DISTINCT status) as stages FROM tasks";
    $total_stages = $db->query($stages_query)->fetch(PDO::FETCH_ASSOC)['stages'];

    // Get completed stages (assuming 'completed' or 'done' status)
    $completed_stages_query = "SELECT COUNT(*) as completed 
                             FROM tasks 
                             WHERE status IN ('completed', 'done')";
    $completed_stages = $db->query($completed_stages_query)->fetch(PDO::FETCH_ASSOC)['completed'];

    // Get delayed tasks (where due_date is past and status is not completed)
    $delayed_tasks_query = "SELECT COUNT(*) as delayed 
                          FROM tasks 
                          WHERE due_date < CURDATE() 
                          AND status NOT IN ('completed', 'done')";
    $delayed_tasks = $db->query($delayed_tasks_query)->fetch(PDO::FETCH_ASSOC)['delayed'];

    // Get tasks for calendar
    $current_month_tasks = "SELECT 
        due_date,
        COUNT(*) as task_count,
        SUM(CASE WHEN status = 'deadline' THEN 1 ELSE 0 END) as deadline_count
    FROM tasks 
    WHERE MONTH(due_date) = MONTH(CURRENT_DATE())
    AND YEAR(due_date) = YEAR(CURRENT_DATE())
    GROUP BY due_date";

    $calendar_tasks = $db->query($current_month_tasks)->fetchAll(PDO::FETCH_ASSOC);

    $task_stats = [
        'total' => $total_tasks,
        'priority' => $priority_stats,
        'total_stages' => $total_stages,
        'completed_stages' => $completed_stages,
        'delayed' => $delayed_tasks,
        'calendar_tasks' => $calendar_tasks
    ];

} catch(PDOException $e) {
    error_log("Error fetching task statistics: " . $e->getMessage());
    $task_stats = [
        'total' => 0,
        'priority' => ['high' => 0, 'medium' => 0, 'low' => 0],
        'total_stages' => 0,
        'completed_stages' => 0,
        'delayed' => 0,
        'calendar_tasks' => []
    ];
}

// Add this PHP code near your other database queries
$employee_stats = [];

// Get total users count
$total_users_query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
$total_users_result = mysqli_query($conn, $total_users_query);
$total_users_row = mysqli_fetch_assoc($total_users_result);
$employee_stats['total_users'] = $total_users_row['total'];

// Get present users count for today
$present_users_query = "SELECT COUNT(DISTINCT user_id) as present 
                       FROM attendance 
                       WHERE DATE(date) = CURDATE() 
                       AND punch_in IS NOT NULL";  // Consider present if they've punched in
$present_users_result = mysqli_query($conn, $present_users_query);
$present_users_row = mysqli_fetch_assoc($present_users_result);
$employee_stats['present_users'] = $present_users_row['present'];

// Get users on leave count for today
$users_on_leave_query = "SELECT COUNT(DISTINCT user_id) as on_leave 
                        FROM leaves 
                        WHERE hr_status = 'approved' 
                        AND CURDATE() BETWEEN start_date AND end_date 
                        AND leave_type_id = 1"; // Assuming 1 is for full-day leaves
$users_on_leave_result = mysqli_query($conn, $users_on_leave_query);
$users_on_leave_row = mysqli_fetch_assoc($users_on_leave_result);
$employee_stats['users_on_leave'] = $users_on_leave_row['on_leave'];

// Initialize arrays before use
$pending_leaves = [];
$approved_leaves = [];
$rejected_leaves = [];

// For pending leaves
$pending_leaves_query = "
    SELECT 
        l.*,  // This already includes pending_leaves
        lt.name as leave_type,
        u.username,
        l.start_date,
        l.end_date,
        l.status,
        l.hr_status,
        l.manager_status,
        l.reason
    FROM leaves l
    JOIN leave_types lt ON l.leave_type_id = lt.id
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'pending'
    ORDER BY l.created_at DESC
";

// Execute queries with error handling
try {
    $pending_leaves_result = mysqli_query($conn, $pending_leaves_query);
    if (!$pending_leaves_result) {
        throw new Exception("Error in pending leaves query: " . mysqli_error($conn));
    }
    
    // Process results
    while ($row = mysqli_fetch_assoc($pending_leaves_result)) {
        $row['pending_leaves'] = $row['pending_leaves'] ?? 0; // Ensure pending_leaves has a default value
        $pending_leaves[] = $row;
    }
    
} catch (Exception $e) {
    // Log the error
    error_log($e->getMessage());
    // Keep empty array if there's an error
    $pending_leaves = [];
}

// For approved leaves
$approved_leaves_query = "
    SELECT 
        l.*,  // This already includes pending_leaves
        lt.name as leave_type,
        u.username,
        l.start_date,
        l.end_date,
        l.status,
        l.hr_status,
        l.manager_status,
        l.reason
    FROM leaves l
    JOIN leave_types lt ON l.leave_type_id = lt.id
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'approved'
    ORDER BY l.created_at DESC
";

// For rejected leaves
$rejected_leaves_query = "
    SELECT 
        l.*,  // This already includes pending_leaves
        lt.name as leave_type,
        u.username,
        l.start_date,
        l.end_date,
        l.status,
        l.hr_status,
        l.manager_status,
        l.reason
    FROM leaves l
    JOIN leave_types lt ON l.leave_type_id = lt.id
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'rejected'
    ORDER BY l.created_at DESC
";

// Execute queries with error handling
try {
    $approved_leaves_result = mysqli_query($conn, $approved_leaves_query);
    if (!$approved_leaves_result) {
        throw new Exception("Error in approved leaves query: " . mysqli_error($conn));
    }
    
    $rejected_leaves_result = mysqli_query($conn, $rejected_leaves_query);
    if (!$rejected_leaves_result) {
        throw new Exception("Error in rejected leaves query: " . mysqli_error($conn));
    }
    
    // Process results
    $approved_leaves = [];
    while ($row = mysqli_fetch_assoc($approved_leaves_result)) {
        $row['pending_leaves'] = $row['pending_leaves'] ?? 0;
        $approved_leaves[] = $row;
    }
    
    $rejected_leaves = [];
    while ($row = mysqli_fetch_assoc($rejected_leaves_result)) {
        $row['pending_leaves'] = $row['pending_leaves'] ?? 0;
        $rejected_leaves[] = $row;
    }
    
} catch (Exception $e) {
    // Log the error
    error_log($e->getMessage());
    // Set empty arrays if there's an error
    $approved_leaves = [];
    $rejected_leaves = [];
}

// Debug information
echo "<!-- Debug: Pending Leaves Count: " . count($pending_leaves) . " -->\n";
echo "<!-- Debug: Approved Leaves Count: " . count($approved_leaves) . " -->\n";
echo "<!-- Debug: Rejected Leaves Count: " . count($rejected_leaves) . " -->\n";

// Function to format leave data for display
function formatLeaveData($leave) {
    // First ensure all required keys exist with default values
    $defaultLeave = [
        'id' => '',
        'username' => '',
        'leave_type' => '',
        'start_date' => '',
        'end_date' => '',
        'status' => '',
        'hr_status' => '',
        'manager_status' => '',
        'reason' => '',
        'pending_leaves' => 0,
        'created_at' => ''
    ];

    // Merge the provided leave data with defaults
    $leave = array_merge($defaultLeave, $leave);

    return [
        'id' => $leave['id'],
        'username' => htmlspecialchars($leave['username']),
        'leave_type' => htmlspecialchars($leave['leave_type']),
        'start_date' => $leave['start_date'] ? date('Y-m-d', strtotime($leave['start_date'])) : '',
        'end_date' => $leave['end_date'] ? date('Y-m-d', strtotime($leave['end_date'])) : '',
        'status' => htmlspecialchars($leave['status']),
        'hr_status' => htmlspecialchars($leave['hr_status']),
        'manager_status' => htmlspecialchars($leave['manager_status']),
        'reason' => htmlspecialchars($leave['reason']),
        'pending_leaves' => intval($leave['pending_leaves']),
        'created_at' => $leave['created_at'] ? date('Y-m-d', strtotime($leave['created_at'])) : ''
    ];
}

// Format all leave data with proper initialization
$formatted_pending_leaves = !empty($pending_leaves) ? array_map('formatLeaveData', $pending_leaves) : [];
$formatted_approved_leaves = !empty($approved_leaves) ? array_map('formatLeaveData', $approved_leaves) : [];
$formatted_rejected_leaves = !empty($rejected_leaves) ? array_map('formatLeaveData', $rejected_leaves) : [];

// For leave balance
$leave_balance_query = "
    SELECT 
        lb.*,
        lt.name as leave_type,  // Changed from l.leave_type to lt.name
        u.username,
        lb.total_leaves,
        lb.used_leaves,
        (lb.total_leaves - lb.used_leaves) as remaining_leaves,
        COALESCE(l.pending_leaves, 0) as pending_leaves  // Add this line
    FROM leave_balance lb
    JOIN users u ON lb.user_id = u.id
    JOIN leave_types lt ON lb.leave_type_id = lt.id
    LEFT JOIN leaves l ON lb.user_id = l.user_id  // Add LEFT JOIN to get pending_leaves
    WHERE lb.user_id = ?
";

try {
    $stmt = $conn->prepare($leave_balance_query);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $leave_balance_result = $stmt->get_result();
    
    $leave_balances = [];
    while ($row = $leave_balance_result->fetch_assoc()) {
        $row['pending_leaves'] = $row['pending_leaves'] ?? 0; // Ensure pending_leaves has a default value
        $leave_balances[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching leave balance: " . $e->getMessage());
    $leave_balances = [];
}

// Get users on short leave for today
$short_leave_query = "SELECT COUNT(DISTINCT user_id) as short_leave 
                     FROM leaves 
                     WHERE hr_status = 'approved' 
                     AND leave_type_id = 2 /* Assuming 2 is for short leaves */
                     AND DATE(start_date) = CURDATE()";
$short_leave_result = mysqli_query($conn, $short_leave_query);
$short_leave_row = mysqli_fetch_assoc($short_leave_result);
$employee_stats['short_leave'] = $short_leave_row['short_leave'];

// For leave history
$leave_history_query = "
    SELECT 
        l.*,
        lt.name as leave_type,  // Changed from l.leave_type to lt.name
        u.username,
        l.start_date,
        l.end_date,
        l.status,
        l.hr_status,
        l.manager_status,
        l.reason,
        l.created_at,
        COALESCE(l.pending_leaves, 0) as pending_leaves  // Add this line
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    JOIN leave_types lt ON l.leave_type_id = lt.id
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
    LIMIT 10
";

try {
    $stmt = $conn->prepare($leave_history_query);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $leave_history_result = $stmt->get_result();
    
    $leave_history = [];
    while ($row = $leave_history_result->fetch_assoc()) {
        $row['pending_leaves'] = $row['pending_leaves'] ?? 0; // Ensure pending_leaves has a default value
        $leave_history[] = $row;
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching leave history: " . $e->getMessage());
    $leave_history = [];
}

try {
    $alter_query = "ALTER TABLE leaves ADD COLUMN pending_leaves INT DEFAULT 0";
    if (!mysqli_query($conn, $alter_query)) {
        throw new Exception("Error adding pending_leaves column: " . mysqli_error($conn));
    }
    echo "Column 'pending_leaves' added successfully";
} catch (Exception $e) {
    error_log($e->getMessage());
    echo "Error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f4f6f9;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            width: 250px;
            background: #1a1a1a;
            transition: all 0.3s ease;
            z-index: 100;
            animation: slideIn 0.5s ease forwards;
        }

        .sidebar.close {
            width: 78px;
        }

        .logo-container {
            height: auto;
            width: 100%;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #2d2d2d;
            background: linear-gradient(to bottom, #1a1a1a 0%, #242424 100%);
            transition: all 0.3s ease;
        }

        .logo-container:hover {
            background: linear-gradient(to bottom, #1a1a1a 0%, #2c2c2c 100%);
        }

        .logo-container img {
            height: 50px;
            transition: all 0.3s ease;
        }

        .logo-container:hover img {
            transform: scale(1.05);
        }

        .logo-container .logo-text {
            color: white;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
            text-align: center;
            padding-bottom: 5px;
        }

        .logo-container:hover .logo-text {
            color: #ff4444;
            transform: scale(1.05);
        }

        .sidebar.close .logo-container {
            padding: 15px 0;
        }

        .sidebar.close .logo-container img {
            height: 40px;
        }

        .sidebar.close .logo-text {
            opacity: 0;
            height: 0;
            padding: 0;
        }

        .nav-links {
            padding: 20px 0;
        }

        .nav-links li {
            list-style: none;
            position: relative;
            padding: 0;
            margin: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-links li a {
            text-decoration: none;
            color: #fff;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            transition: all 0.3s ease;
            border-radius: 8px;
            background: linear-gradient(to right, transparent 50%, #ff4444 50%);
            background-size: 200% 100%;
            background-position: 0 0;
            overflow: hidden;
            text-align: justify;
            word-wrap: break-word;
        }

        .nav-links li a:hover {
            color: #fff;
            background-position: -100% 0;
            box-shadow: 0 5px 15px rgba(255, 68, 68, 0.2);
        }

        .nav-links li a i {
            min-width: 50px;
            text-align: center;
            font-size: 18px;
            transition: all 0.3s ease;
            display: flex;
        }

        .nav-links li a:hover i {
            transform: translateX(5px);
        }

        .nav-links li a .link-text {
            white-space: normal;
            transition: all 0.3s ease;
            flex: 1;
        }

        .nav-links li a:hover .link-text {
            transform: translateX(5px);
        }

        .nav-links li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: #ff4444;
            border-radius: 15px;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .nav-links li:hover::before {
            opacity: 1;
        }

        .sidebar.close .link-text {
            opacity: 0;
            pointer-events: none;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .sidebar.close ~ .main-content {
            margin-left: 78px;
        }

        .toggle-btn {
            position: absolute;
            top: 20px;
            right: -12px;
            height: 24px;
            width: 24px;
            background: #ff4444;
            color: #fff;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            animation: pulseButton 2s infinite;
        }

        @keyframes pulseButton {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 68, 68, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 68, 68, 0);
            }
        }

        .toggle-btn:hover {
            background: #ff3333;
            transform: scale(1.1);
        }

        .toggle-btn i {
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .sidebar.close .toggle-btn i {
            transform: rotate(180deg);
        }

        /* Active menu item style */
        .nav-links li.active a {
            background: #ff4444;
            color: white;
        }

        .nav-links li.active::before {
            opacity: 1;
        }

        /* Sidebar animation */
        @keyframes slideIn {
            0% {
                transform: translateX(-100%);
                opacity: 0;
            }
            100% {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Add these new styles */
        .sub-menu {
            padding-left: 0px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .sub-menu.show {
            max-height: 500px; /* Adjust based on number of items */
            transition: max-height 0.3s ease-in;
        }

        .nav-links li.has-submenu > a::after {
            text-align: justify;
            content: '\f107'; /* FontAwesome down arrow */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .nav-links li.has-submenu.open > a::after {
            transform: rotate(180deg);
        }

        .sub-menu li {
            margin: 8px 0;
            opacity: 0;
            transform: translateX(-10px);
            transition: all 0.3s ease;
        }

        .sub-menu.show li {
            opacity: 1;
            transform: translateX(0);
        }

        /* Add delay for each submenu item */
        .sub-menu li:nth-child(1) { transition-delay: 0.1s; }
        .sub-menu li:nth-child(2) { transition-delay: 0.2s; }
        .sub-menu li:nth-child(3) { transition-delay: 0.3s; }
        .sub-menu li:nth-child(4) { transition-delay: 0.4s; }
        .sub-menu li:nth-child(5) { transition-delay: 0.5s; }
        .sub-menu li:nth-child(6) { transition-delay: 0.6s; }

        .sub-menu li a {
            font-size: 0.9em;
            padding: 8px 15px;
        }

        .sub-menu li a i {
            font-size: 14px;
        }

        .sidebar.close .nav-links li a i {
            font-size: 24px;
            min-width: 78px;
        }

        .greeting-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            margin: 20px 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .greeting-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .greeting-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
        }

        /* Time-based gradient backgrounds */
        .greeting-section.morning {
            background: linear-gradient(135deg, #FF9966, #FF5E62);
        }

        .greeting-section.afternoon {
            background: linear-gradient(135deg, #4CA1AF, #2C3E50);
        }

        .greeting-section.evening {
            background: linear-gradient(135deg, #2C3E50, #3498db);
        }

        .greeting-section.night {
            background: linear-gradient(135deg, #141E30, #243B55);
        }

        .greeting-section h1 {
            color: #ffffff;
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
        }

        .date-time {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date-time i {
            margin-right: 5px;
        }

        .weather-section {
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(5px);
        }

        .weather-icon {
            font-size: 1.8rem;
        }

        #weather-temp {
            font-size: 1.2rem;
            font-weight: 500;
        }

        #weather-description {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Seasonal effects */
        .greeting-section.snow::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('path/to/snow-overlay.png');
            opacity: 0.1;
            pointer-events: none;
        }

        .greeting-section.rain::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.2) 100%);
            pointer-events: none;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .greeting-section {
                flex-direction: column;
                text-align: center;
                gap: 20px;
                padding: 20px;
            }

            .greeting-content {
                flex-direction: column;
            }

            .weather-section {
                width: 100%;
                justify-content: center;
            }
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
            display: flex;
            flex-wrap: wrap;
            gap: 12px; /* Reduced gap */
            justify-content: center;
            padding: 0;
            margin: 0;
        }

        .col-md-3 {
            flex: 0 0 auto;
            width: calc(16% - 12px); /* Reduced width for 6 cards */
            min-width: 180px; /* Reduced minimum width */
        }

        .stat-card {
            width: 100%;
            background: #FFFFFF;
            border-radius: 12px;
            padding: 12px; /* Reduced padding */
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin: 0;
        }

        /* Adjust text sizes */
        .stat-info h6 {
            font-size: 0.8rem;
            margin-bottom: 4px;
        }

        .stat-info h2 {
            font-size: 1.3rem;
        }

        .icon-box {
            width: 32px;
            height: 32px;
        }

        .icon-box i {
            font-size: 1.1rem;
        }

        /* Card-specific colors */
        /* Total Employees - Blue theme */
        .stat-card:nth-child(1) {
            background: linear-gradient(135deg, #ffffff 0%, #e8f0fe 100%);
            border-left: 4px solid #1a73e8;
        }
        .stat-card:nth-child(1) .icon-box i {
            color: #1a73e8;
        }

        /* Active Projects - Purple theme */
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #ffffff 0%, #f3e8fd 100%);
            border-left: 4px solid #9334e6;
        }
        .stat-card:nth-child(2) .icon-box i {
            color: #9334e6;
        }

        /* Total Tasks - Red theme */
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #ffffff 0%, #fce8e8 100%);
            border-left: 4px solid #ea4335;
        }
        .stat-card:nth-child(3) .icon-box i {
            color: #ea4335;
        }

        /* Pending Leaves - Orange theme */
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #ffffff 0%, #fff0e8 100%);
            border-left: 4px solid #ff7043;
        }
        .stat-card:nth-child(4) .icon-box i {
            color: #ff7043;
        }

        /* Received Payments - Green theme */
        .stat-card:nth-child(5) {
            background: linear-gradient(135deg, #ffffff 0%, #e8f5e9 100%);
            border-left: 4px solid #34a853;
        }
        .stat-card:nth-child(5) .icon-box i {
            color: #34a853;
        }

        /* Total Payments - Teal theme */
        .stat-card:nth-child(6) {
            background: linear-gradient(135deg, #ffffff 0%, #e8f4f8 100%);
            border-left: 4px solid #00acc1;
        }
        .stat-card:nth-child(6) .icon-box i {
            color: #00acc1;
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

        /* Ensure tooltips appear above other elements */
        .quick-view-section {
            overflow: visible !important;
        }

        /* Adjust tooltip position for different cards */
        .stat-card:nth-child(5) .stat-tooltip,
        .stat-card:nth-child(6) .stat-tooltip {
            left: auto;
            right: 0;
        }

        .stat-card:nth-child(5) .stat-tooltip::before,
        .stat-card:nth-child(6) .stat-tooltip::before {
            left: auto;
            right: 20px;
        }

        /* Progress bar styles */
        .tooltip-item .progress-bar {
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
            width: 100%;
        }

        .tooltip-item .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* Tooltip footer */
        .tooltip-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(0,0,0,0.05);
            font-size: 0.85em;
            color: #666;
        }

        /* Make sure the container doesn't clip the tooltips */
        .row.mt-4 {
            overflow: visible;
        }

        .col-md-3 {
            overflow: visible;
        }

        /* Add hover effect for cards */
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Ensure tooltips stay visible when hovering */
        .stat-card:hover .stat-tooltip {
            pointer-events: auto;
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

        /* Payment card specific colors */
        /* Received Payments Card */
        .stat-card:nth-child(5) .icon-box i {
            color: #2ecc71;
        }
        .stat-card:nth-child(5) {
            border-left: 4px solid #2ecc71;
        }

        /* Total Payments Card */
        .stat-card:nth-child(6) .icon-box i {
            color: #ffc107;
        }
        .stat-card:nth-child(6) {
            border-left: 4px solid #ffc107;
        }

        /* Quick View Section Styling */
        .quick-view-section {
            padding: 25px;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 
                0 4px 20px rgba(0, 0, 0, 0.05),
                0 0 0 1px rgba(0, 0, 0, 0.05);
            margin: 20px;
            position: relative;
            z-index: 2;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
        }

        /* Add decorative corners */
        .quick-view-section::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            width: 40px;
            height: 40px;
            border-top: 3px solid #1a73e8;
            border-left: 3px solid #1a73e8;
            border-radius: 15px 0 0 0;
        }

        .quick-view-section::after {
            content: '';
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 40px;
            height: 40px;
            border-bottom: 3px solid #1a73e8;
            border-right: 3px solid #1a73e8;
            border-radius: 0 0 15px 0;
        }

        /* Enhanced Section Header */
        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(26, 115, 232, 0.1);
            position: relative;
        }

        .section-header h5 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1a73e8;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-left: 10px;
            position: relative;
        }

        .section-header h5 i {
            color: #1a73e8;
            font-size: 1.2em;
            background: rgba(26, 115, 232, 0.1);
            padding: 8px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .section-header h5::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 70%;
            background: #1a73e8;
            border-radius: 2px;
        }

        .header-line {
            flex: 1;
            height: 2px;
            background: linear-gradient(
                to right,
                rgba(26, 115, 232, 0.2),
                rgba(26, 115, 232, 0.05) 50%,
                transparent
            );
            border-radius: 2px;
        }

        /* Add subtle inner shadow */
        .quick-view-section {
            position: relative;
            overflow: hidden;
        }

        .quick-view-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(
                180deg,
                rgba(255, 255, 255, 0.8) 0%,
                rgba(255, 255, 255, 0) 100%
            );
            pointer-events: none;
        }

        /* Add subtle pattern overlay */
        .quick-view-section {
            position: relative;
        }

        .quick-view-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(45deg, rgba(26, 115, 232, 0.03) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(26, 115, 232, 0.03) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(26, 115, 232, 0.03) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(26, 115, 232, 0.03) 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            pointer-events: none;
            border-radius: 20px;
        }

        /* Add hover effect to the section */
        .quick-view-section {
            transition: all 0.3s ease;
        }

        .quick-view-section:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 6px 25px rgba(0, 0, 0, 0.07),
                0 0 0 1px rgba(0, 0, 0, 0.08);
        }

        /* Add animation for the icon */
        .section-header h5 i {
            animation: pulse 2s infinite;
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

        /* Add responsive padding */
        @media (max-width: 768px) {
            .quick-view-section {
                padding: 20px;
                margin: 15px;
            }
            
            .section-header h5 {
                font-size: 1.2rem;
            }
        }

        .sales-overview {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            margin: 20px 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        }

        .sales-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(26, 115, 232, 0.1);
        }

        .sales-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a73e8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .date-input {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .filter-btn {
            padding: 8px 15px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: #1557b0;
        }

        /* Grid Layout */
        .sales-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
            margin: 15px;
        }

        /* Total Sales Box */
        .total-sales-box {
            background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
            padding: 20px;
            border-radius: 12px;
            color: white;
            height: fit-content;
        }

        .total-sales-box h3 {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .total-sales-box .amount {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .total-sales-box .total-projects {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* Sales Breakdown Cards */
        .sales-breakdown {
            grid-column: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .sales-card {
            height: 200px;
            display: flex;
            flex-direction: column;
            background: white;
            padding: 10px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        /* Pipeline Card */
        .pipeline-card {
            grid-column: 2;
            grid-row: 1 / span 2;
            background: white;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            height: 100%;
            max-height: calc(100vh - 240px); /* Adjusted height */
        }

        /* Pipeline Header */
        .pipeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .pipeline-header h4 {
            font-size: 0.9rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .pipeline-stats {
            font-size: 0.8rem;
            color: #666;
        }

        /* Projects Container */
        .pipeline-projects-container {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 8px;
            margin-right: -8px;
        }

        /* Individual Project Card */
        .pipeline-project {
            padding: 12px;
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }

        .pipeline-project:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        /* Project Details */
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .project-header-left {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .project-type-tag, .project-status {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }

        .project-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: #333;
            margin: 6px 0;
        }

        .project-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 6px 0;
        }

        .detail-item {
            font-size: 0.75rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .detail-item i {
            font-size: 0.7rem;
            width: 12px;
            text-align: center;
            color: #1a73e8;
        }

        .project-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 0.75rem;
            color: #666;
        }

        /* Scrollbar Styling */
        .pipeline-projects-container::-webkit-scrollbar {
            width: 4px;
        }

        .pipeline-projects-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
        }

        .pipeline-projects-container::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 2px;
        }

        .pipeline-projects-container::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.15);
        }

        /* Status and Type Colors */
        .project-status.active { background: rgba(46, 213, 115, 0.1); color: #2ed573; }
        .project-status.ongoing { background: rgba(25, 118, 210, 0.1); color: #1976d2; }
        .project-status.hold { background: rgba(255, 152, 0, 0.1); color: #ff9800; }

        .project-type-tag.architecture { background: rgba(26, 115, 232, 0.1); color: #1a73e8; }
        .project-type-tag.interior { background: rgba(52, 168, 83, 0.1); color: #34a853; }
        .project-type-tag.construction { background: rgba(251, 188, 4, 0.1); color: #fbbc04; }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .sales-grid {
                grid-template-columns: 1fr;
            }
            
            .pipeline-card {
                grid-column: 1;
                grid-row: auto;
                max-height: 400px;
            }
        }

        @media (max-width: 768px) {
            .sales-breakdown {
                grid-template-columns: 1fr;
            }
            
            .pipeline-card {
                max-height: 350px;
            }
        }

        .sales-card h4 {
            color: #666;
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sales-card .amount {
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
        }

        .sales-card .progress-bar {
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            margin-top: 15px;
            overflow: hidden;
        }

        .sales-card .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* Card-specific colors */
        .architecture-card .progress-fill {
            background: #1a73e8;
        }

        .interior-card .progress-fill {
            background: #34a853;
        }

        .construction-card .progress-fill {
            background: #fbbc04;
        }

        .project-count {
            color: #666;
            font-size: 0.9rem;
            margin: 5px 0 10px 0;
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .project-header-left {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .project-type-tag {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .project-name {
            font-size: 0.95rem;
            font-weight: 500;
            color: #333;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .project-name i {
            color: #1a73e8;
            font-size: 0.9rem;
        }

        .project-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 8px 0;
        }

        .detail-item {
            font-size: 0.8rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .detail-item i {
            color: #1a73e8;
            width: 16px;
            text-align: center;
        }

        .detail-item a {
            color: inherit;
            text-decoration: none;
        }

        .detail-item a:hover {
            color: #1a73e8;
        }

        .project-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .project-value, .project-id {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Status colors */
        .project-status {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .project-status.active {
            background: rgba(46, 213, 115, 0.1);
            color: #2ed573;
        }

        .project-status.ongoing {
            background: rgba(25, 118, 210, 0.1);
            color: #1976d2;
        }

        .project-status.hold,
        .project-status.on-hold {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }

        /* Project type colors */
        .project-type-tag.architecture {
            background: rgba(26, 115, 232, 0.1);
            color: #1a73e8;
        }

        .project-type-tag.interior {
            background: rgba(52, 168, 83, 0.1);
            color: #34a853;
        }

        .project-type-tag.construction {
            background: rgba(251, 188, 4, 0.1);
            color: #fbbc04;
        }

        /* Add some CSS for the no-projects message */
        .no-projects {
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 10px;
        }

        .no-projects i {
            color: #1a73e8;
        }

        .project-overview {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            margin: 20px 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(26, 115, 232, 0.1);
        }

        .project-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a73e8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .project-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
            margin: 15px;
        }

        /* Total Projects Box */
        .total-projects-box {
            background: linear-gradient(135deg, #1a73e8 0%, #0052cc 100%);
            padding: 20px;
            border-radius: 12px;
            color: white;
            height: fit-content;
        }

        .total-projects-box h3 {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .total-projects-box .amount {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* Project Breakdown Cards */
        .project-breakdown {
            grid-column: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .project-card {
            height: 200px;
            display: flex;
            flex-direction: column;
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .project-card h4 {
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .project-card .amount {
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
        }

        .project-card .project-count {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        .project-card .progress-bar {
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            margin-top: 15px;
            overflow: hidden;
        }

        .project-card .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* Task Cards */
        .tasks-card {
            grid-column: 2;
            grid-row: 1 / span 2;
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            max-height: calc(100vh - 240px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(26, 115, 232, 0.1);
        }

        .tasks-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1a73e8;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tasks-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .tasks-container {
            overflow-y: auto;
            flex-grow: 1;
            padding-right: 8px;
        }

        .task-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .task-header-left {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .task-type-tag, .task-priority {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .task-time {
            font-size: 0.8rem;
            color: #666;
        }

        .task-project {
            font-size: 0.9rem;
            color: #333;
        }

        .task-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .assigned-to {
            font-size: 0.8rem;
            color: #666;
        }

        .task-status {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .task-status.pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .task-status.in-progress {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .task-status.completed {
            background: rgba(255, 255, 255, 0.1);
            color: #28a745;
        }

        .task-status.overdue {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .no-tasks {
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 10px;
        }

        .no-tasks i {
            color: #1a73e8;
        }

        .task-description {
            font-size: 0.85rem;
            color: #666;
            margin: 8px 0;
            line-height: 1.4;
        }

        .task-name {
            font-size: 0.95rem;
            font-weight: 500;
            color: #333;
            margin: 8px 0;
        }

        .task-time {
            font-size: 0.8rem;
            color: #666;
            font-weight: 500;
        }

        .task-type-tag {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .task-priority {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        /* Priority colors */
        .priority-high {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .priority-medium {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .priority-low {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        /* Status colors */
        .task-status.pending {
            color: #ffc107;
        }

        .task-status.in-progress {
            color: #17a2b8;
        }

        .task-status.completed {
            color: #28a745;
        }

        .task-status.overdue {
            color: #dc3545;
        }

        /* Task Overview Section Styles */
        .task-overview {
            background: #f8f9fd;
            border-radius: 20px;
            padding: 25px;
            margin: 20px 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(26, 115, 232, 0.1);
        }

        .task-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a73e8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Task Grid Layout */
        .task-grid {
            display: grid;
            grid-template-columns: 3fr 1fr; /* Adjusted ratio */
            gap: 25px;
            margin-top: 20px;
        }

        /* Task Stats Layout */
        .task-stats {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Total Tasks Box - Full Width */
        .total-tasks-box {
            background: linear-gradient(135deg, #4776E6 0%, #8E54E9 100%);
            padding: 30px;
            border-radius: 20px;
            color: white;
            box-shadow: 0 10px 20px rgba(71, 118, 230, 0.15);
            transition: transform 0.3s ease;
            width: 100%;
            margin-bottom: 10px;
        }

        /* Task Breakdown Layout */
        .task-breakdown {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* Three columns of equal width */
            gap: 20px;
            width: 100%;
        }

        /* Priority Task Cards */
        .task-card {
            background: white;
            padding: 20px; /* Reduced padding */
            border-radius: 15px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .task-card h4 {
            font-size: 0.9rem; /* Smaller font size */
            margin-bottom: 12px;
        }

        .task-card .amount {
            font-size: 1.6rem; /* Smaller font size */
            margin-bottom: 5px;
        }

        .task-card .task-count {
            font-size: 0.8rem; /* Smaller font size */
            margin-bottom: 12px;
        }

        /* Responsive Design */
        @media (max-width: 1400px) {
            .task-breakdown {
                grid-template-columns: repeat(3, 1fr); /* Maintain 3 columns */
                gap: 15px;
            }
            
            .task-card {
                padding: 15px;
            }
        }

        @media (max-width: 1200px) {
            .task-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .task-breakdown {
                grid-template-columns: 1fr; /* Stack cards on mobile */
            }
        }

        .task-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            transition: all 0.3s ease;
        }

        .task-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.06);
        }

        .task-card h4 {
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .task-card .amount {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .task-card .task-count {
            font-size: 0.95rem;
            color: #64748b;
            margin-bottom: 18px;
        }

        /* Priority-specific styles */
        .high-priority-card::before { background: #dc3545; }
        .medium-priority-card::before { background: #ffc107; }
        .low-priority-card::before { background: #28a745; }

        .high-priority-card h4 i { 
            color: #dc3545;
            font-size: 1.2rem;
        }

        .medium-priority-card h4 i { 
            color: #ffc107;
            font-size: 1.2rem;
        }

        .low-priority-card h4 i { 
            color: #28a745;
            font-size: 1.2rem;
        }

        .high-priority-card .progress-fill { 
            background: linear-gradient(to right, #ff416c, #dc3545);
        }

        .medium-priority-card .progress-fill { 
            background: linear-gradient(to right, #f7b733, #ffc107);
        }

        .low-priority-card .progress-fill { 
            background: linear-gradient(to right, #00b09b, #28a745);
        }

        /* Right Side Calendar Styles */
        .task-calendar-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        /* Calendar Header */
        .calendar-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .calendar-header i {
            color: #1a73e8;
            font-size: 1.2rem;
        }

        .calendar-stats {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #64748b;
            font-size: 0.85rem;
        }

        /* Month Navigation */
        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        #currentMonth {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .nav-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 12px;
            color: #1a73e8;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* Calendar Grid */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        /* Weekday Headers */
        .weekday {
            text-align: center;
            padding: 8px 0;
            font-weight: 600;
            color: #64748b;
            font-size: 0.85rem;
        }

        /* Calendar Days */
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #334155;
            border-radius: 8px;
            cursor: pointer;
            position: relative;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .calendar-day:hover {
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .calendar-day.today {
            background: #1a73e8;
            color: white;
            font-weight: 500;
        }

        .calendar-day.other-month {
            color: #94a3b8;
        }

        .calendar-day.has-tasks {
            background: #f0f7ff;
            border: 1px solid #1a73e8;
            font-weight: 500;
        }

        /* Task Tooltip */
        .day-task-tooltip {
            display: none;
            position: absolute;
            top: calc(100% + 5px);
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 12px;
            width: 180px;
            z-index: 1000;
            border: 1px solid #e2e8f0;
        }

        .calendar-day:hover .day-task-tooltip {
            display: block;
        }

        .tooltip-date {
            font-weight: 600;
            color: #1e293b;
            padding-bottom: 8px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }

        .tooltip-stat {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 0.85rem;
            padding: 4px 0;
        }

        .tooltip-stat i {
            color: #1a73e8;
            width: 16px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .calendar-grid {
                gap: 4px;
            }
            
            .calendar-day {
                font-size: 0.8rem;
            }
            
            .weekday {
                font-size: 0.8rem;
            }
        }

        .task-overview-box {
            background: linear-gradient(135deg, #4776E6 0%, #8E54E9 100%);
            padding: 30px;
            border-radius: 20px;
            color: white;
            position: relative;
            box-shadow: 0 10px 20px rgba(71, 118, 230, 0.15);
            transition: transform 0.3s ease;
        }

        .task-overview-box:hover {
            transform: translateY(-5px);
        }

        .task-overview-box h3 {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 15px;
        }

        .task-overview-box .amount {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .overview-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Status Metric Boxes */
        .task-status-breakdown {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .status-metric-box {
            background: white;
            padding: 20px;
            border-radius: 15px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }

        .status-metric-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.06);
        }

        .status-metric-box h4 {
            color: #2c3e50;
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .metric-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .metric-total {
            font-size: 1rem;
            color: #64748b;
            font-weight: normal;
        }

        .metric-subtitle {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 12px;
        }

        .metric-progress {
            height: 4px;
            background: #f1f5f9;
            border-radius: 2px;
            overflow: hidden;
        }

        /* Box-specific styles */
        .stages-box .progress-fill {
            background: linear-gradient(to right, #3498db, #2980b9);
        }

        .pending-box .progress-fill {
            background: linear-gradient(to right, #f1c40f, #f39c12);
        }

        .delayed-box .progress-fill {
            background: linear-gradient(to right, #e74c3c, #c0392b);
        }

        /* Priority Tooltip Styles */
        .task-priority-tooltip {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 300px;
            padding: 20px;
            z-index: 1000;
            margin-top: 10px;
        }

        .task-overview-box:hover .task-priority-tooltip {
            display: block;
        }

        .tooltip-header {
            color: #2c3e50;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tooltip-date {
            font-size: 0.8rem;
            color: #64748b;
        }

        .priority-item {
            padding: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .priority-item i {
            width: 20px;
        }

        .priority-item .label {
            flex: 1;
            color: #2c3e50;
            font-size: 0.85rem;
        }

        .priority-item .value {
            color: #2c3e50;
            font-weight: 600;
        }

        .priority-meter {
            height: 4px;
            background: #f1f5f9;
            border-radius: 2px;
            overflow: hidden;
            width: 100%;
            margin-top: 5px;
        }

        /* Priority-specific styles */
        .urgent-level i { color: #dc3545; }
        .urgent-level .meter-fill { background: linear-gradient(to right, #ff416c, #dc3545); }

        .moderate-level i { color: #ffc107; }
        .moderate-level .meter-fill { background: linear-gradient(to right, #f7b733, #ffc107); }

        .normal-level i { color: #28a745; }
        .normal-level .meter-fill { background: linear-gradient(to right, #00b09b, #28a745); }

        /* Total Tasks Box Styles */
        .task-overview-box {
            background: linear-gradient(135deg, #4776E6 0%, #8E54E9 100%);
            padding: 20px 25px; /* Reduced padding */
            border-radius: 20px;
            color: white;
            position: relative;
            box-shadow: 0 10px 20px rgba(71, 118, 230, 0.15);
            transition: transform 0.3s ease;
            margin-bottom: 15px; /* Reduced margin */
        }

        .task-overview-box:hover {
            transform: translateY(-3px);
        }

        .task-overview-box h3 {
            font-size: 1rem; /* Smaller font size */
            opacity: 0.9;
            margin-bottom: 8px; /* Reduced margin */
        }

        .task-overview-box .amount {
            font-size: 2.2rem; /* Smaller font size */
            font-weight: 700;
            margin-bottom: 5px; /* Reduced margin */
            line-height: 1; /* Tighter line height */
        }

        .overview-subtitle {
            font-size: 0.85rem; /* Smaller font size */
            opacity: 0.8;
        }

        /* Status Metric Boxes - Adjusted top margin */
        .task-status-breakdown {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 15px; /* Reduced margin */
        }

        /* Adjust tooltip position for smaller box */
        .task-priority-tooltip {
            top: calc(100% + 5px); /* Adjusted position */
            margin-top: 5px; /* Reduced margin */
        }

        /* Unique Calendar Task Tooltip Styles */
        .task-date-insight {
            display: none;
            position: absolute;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
            padding: 12px;
            width: 180px;
            z-index: 1000;
            top: calc(100% + 5px);
            left: 50%;
            transform: translateX(-50%);
            pointer-events: none;
        }

        .calendar-day:hover .task-date-insight {
            display: block;
        }

        .insight-header {
            font-size: 0.85rem;
            font-weight: 600;
            color: #2c3e50;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            margin-bottom: 8px;
        }

        .insight-detail {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 6px;
        }

        .insight-detail:last-child {
            margin-bottom: 0;
        }

        .insight-detail i {
            width: 14px;
            text-align: center;
        }

        .insight-value {
            font-weight: 600;
            color: #2c3e50;
        }

        /* Update calendar day to handle tooltip */
        .calendar-day {
            position: relative;
        }

        /* Calendar Container Styles */
        .task-calendar-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .calendar-header h4 {
            font-size: 1rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .calendar-stats {
            font-size: 0.85rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .month-navigation {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        #calendar-month {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .month-nav-btn {
            background: none;
            border: none;
            color: #1a73e8;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #2c3e50;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .calendar-day.header {
            font-weight: 600;
            color: #64748b;
            font-size: 0.8rem;
            cursor: default;
        }

        .calendar-day:not(.header):hover {
            background: rgba(26, 115, 232, 0.1);
        }

        .calendar-day.today {
            background: #1a73e8;
            color: white;
            font-weight: 600;
        }

        .calendar-day.has-tasks {
            border: 2px solid #1a73e8;
            font-weight: 600;
        }

        .calendar-day.other-month {
            color: #cbd5e1;
        }

        /* Employee Overview Styles */
        .employee-overview {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            margin: 20px 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        }

        .employee-overview .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .employee-overview .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a73e8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .employee-stat-box {
            background: #ffffff;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            display: flex;
            gap: 15px;
            transition: transform 0.3s ease;
        }

        .employee-stat-box:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            background: rgba(26, 115, 232, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: #1a73e8;
        }

        .stat-content {
            flex: 1;
        }

        .stat-content h4 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .stat-numbers {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: baseline;
            gap: 5px;
        }

        .stat-numbers .divider {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .stat-numbers .total {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .progress-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
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
        }

        /* Pending Leaves Tooltip Styles */
        .stat-tooltip {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 320px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            margin-top: 10px;
        }

        .employee-stat-box[data-tooltip]:hover .stat-tooltip {
            opacity: 1;
            visibility: visible;
        }

        .tooltip-header {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tooltip-date {
            font-size: 0.8rem;
            color: #64748b;
        }

        .tooltip-content {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
        }

        .pending-leave-item {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .pending-leave-item:last-child {
            border-bottom: none;
        }

        .leave-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .employee-name {
            font-weight: 500;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .employee-name i {
            color: #64748b;
            font-size: 0.9rem;
        }

        .leave-type {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 12px;
            background: #e2e8f0;
            color: #64748b;
        }

        .leave-dates {
            font-size: 0.9rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 8px;
        }

        .days-count {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .approval-status {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            background: #f1f5f9;
            color: #64748b;
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

        .no-pending-leaves {
            padding: 20px;
            text-align: center;
            color: #64748b;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .no-pending-leaves i {
            font-size: 1.5rem;
            color: #10b981;
        }

        /* Scrollbar Styles for Tooltip */
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

        /* Add these styles for the additional boxes */
        .additional-boxes .employee-stat-box {
            background: #ffffff;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            display: flex;
            gap: 15px;
        }

        .additional-boxes .stat-icon {
            background: rgba(26, 115, 232, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .additional-boxes .stat-icon i {
            font-size: 1.5rem;
            color: #1a73e8;
        }

        .additional-boxes .stat-content {
            flex: 1;
        }

        .additional-boxes .stat-content h4 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .additional-boxes .stat-numbers {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: baseline;
            gap: 5px;
        }

        .additional-boxes .stat-numbers .divider {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .additional-boxes .stat-numbers .total {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .additional-boxes .progress-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .additional-boxes .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .additional-boxes .stat-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Add these styles for the additional boxes */
        .additional-boxes .employee-stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.06);
        }

        /* Add these styles for the box headers */
        .additional-boxes .box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .additional-boxes .box-header h4 {
            font-size: 1rem;
            color: #1a73e8;
            margin: 0;
        }

        .additional-boxes .add-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 12px;
            color: #1a73e8;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .additional-boxes .add-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* Add these styles for the no-content message */
        .additional-boxes .no-content {
            color: #94a3b8;
            text-align: center;
            padding: 20px 0;
            font-size: 0.9rem;
        }

        /* Add these styles for the box content */
        .additional-boxes .box-content {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
        }

        /* Site Overview Styles */
        .site-overview {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            margin: 20px 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        }

        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .site-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a73e8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .site-selector {
            position: relative;
            min-width: 250px;
        }

        .site-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #1e293b;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            appearance: none;
        }

        .site-select:hover {
            border-color: #1a73e8;
        }

        .site-select:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        .site-selector::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }

        .site-stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .site-stat-box {
            background: #ffffff;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            transition: transform 0.3s ease;
        }

        .site-stat-box:hover {
            transform: translateY(-5px);
        }

        .site-stat-icon {
            background: rgba(26, 115, 232, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .site-stat-icon i {
            font-size: 1.5rem;
            color: #1a73e8;
        }

        .site-stat-content h4 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .site-stat-numbers {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .site-stat-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Box-specific styles */
        .active-site-box .site-stat-icon {
            background: rgba(16, 185, 129, 0.1);
        }

        .active-site-box .site-stat-icon i {
            color: #10b981;
        }

        .manager-box .site-stat-icon {
            background: rgba(99, 102, 241, 0.1);
        }

        .manager-box .site-stat-icon i {
            color: #6366f1;
        }

        .engineer-box .site-stat-icon {
            background: rgba(245, 158, 11, 0.1);
        }

        .engineer-box .site-stat-icon i {
            color: #f59e0b;
        }

        .supervisor-box .site-stat-icon {
            background: rgba(239, 68, 68, 0.1);
        }

        .supervisor-box .site-stat-icon i {
            color: #ef4444;
        }

        .labour-box .site-stat-icon {
            background: rgba(139, 92, 246, 0.1);
        }

        .labour-box .site-stat-icon i {
            color: #8b5cf6;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .site-stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .site-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .site-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .site-selector {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .site-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Add these tooltip styles to your existing CSS */
        .site-stat-box {
            position: relative; /* Add this */
        }

        .site-stat-tooltip {
            position: absolute;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%);
            width: 300px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            padding: 15px;
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .site-stat-box:hover .site-stat-tooltip {
            visibility: visible;
            opacity: 1;
            bottom: 110%;
        }

        .site-stat-tooltip::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid white;
        }

        .tooltip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #1e293b;
        }

        .tooltip-content {
            font-size: 0.9rem;
        }

        .tooltip-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color: #64748b;
        }

        .tooltip-item i {
            width: 20px;
            margin-right: 8px;
        }

        .tooltip-progress {
            margin-top: 4px;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .tooltip-progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .tooltip-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* Add this after your existing employee stat boxes */
        .employee-stats-grid.additional-boxes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        /* Add these styles for the additional boxes */
        .additional-boxes .employee-stat-box {
            background: #ffffff;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            display: flex;
            gap: 15px;
        }

        .additional-boxes .stat-icon {
            background: rgba(26, 115, 232, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .additional-boxes .stat-icon i {
            font-size: 1.5rem;
            color: #1a73e8;
        }

        .additional-boxes .stat-content {
            flex: 1;
        }

        .additional-boxes .stat-content h4 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .additional-boxes .stat-numbers {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: baseline;
            gap: 5px;
        }

        .additional-boxes .stat-numbers .divider {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .additional-boxes .stat-numbers .total {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .additional-boxes .progress-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .additional-boxes .progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .additional-boxes .stat-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Add these styles for the additional boxes */
        .additional-boxes .employee-stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.06);
        }

        /* Add these styles for the box headers */
        .additional-boxes .box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .additional-boxes .box-header h4 {
            font-size: 1rem;
            color: #1a73e8;
            margin: 0;
        }

        .additional-boxes .add-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 12px;
            color: #1a73e8;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .additional-boxes .add-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* Add these styles for the no-content message */
        .additional-boxes .no-content {
            color: #94a3b8;
            text-align: center;
            padding: 20px 0;
            font-size: 0.9rem;
        }

        /* Add these styles for the box content */
        .additional-boxes .box-content {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
        }

        /* Site Overview Styles */
        .site-overview {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            margin: 20px 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        }

        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .site-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a73e8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .site-selector {
            position: relative;
            min-width: 250px;
        }

        .site-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #1e293b;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            appearance: none;
        }

        .site-select:hover {
            border-color: #1a73e8;
        }

        .site-select:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        .site-selector::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }

        .site-stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .site-stat-box {
            background: #ffffff;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            transition: transform 0.3s ease;
        }

        .site-stat-box:hover {
            transform: translateY(-5px);
        }

        .site-stat-icon {
            background: rgba(26, 115, 232, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .site-stat-icon i {
            font-size: 1.5rem;
            color: #1a73e8;
        }

        .site-stat-content h4 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .site-stat-numbers {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .site-stat-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Box-specific styles */
        .active-site-box .site-stat-icon {
            background: rgba(16, 185, 129, 0.1);
        }

        .active-site-box .site-stat-icon i {
            color: #10b981;
        }

        .manager-box .site-stat-icon {
            background: rgba(99, 102, 241, 0.1);
        }

        .manager-box .site-stat-icon i {
            color: #6366f1;
        }

        .engineer-box .site-stat-icon {
            background: rgba(245, 158, 11, 0.1);
        }

        .engineer-box .site-stat-icon i {
            color: #f59e0b;
        }

        .supervisor-box .site-stat-icon {
            background: rgba(239, 68, 68, 0.1);
        }

        .supervisor-box .site-stat-icon i {
            color: #ef4444;
        }

        .labour-box .site-stat-icon {
            background: rgba(139, 92, 246, 0.1);
        }

        .labour-box .site-stat-icon i {
            color: #8b5cf6;
        }

        /* Add these styles for the announcement form modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 500px;
            z-index: 1001;
        }

        .modal-header {
    display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a73e8;
        }

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #64748b;
    cursor: pointer;
    padding: 5px;
}

.announcement-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group label {
    font-size: 0.9rem;
    color: #64748b;
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.95rem;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.submit-btn {
    background: #1a73e8;
    color: white;
    padding: 10px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.95rem;
    transition: background 0.3s ease;
}

.submit-btn:hover {
    background: #1557b0;
}

/* Present Users Tooltip Styles */
.present-users-tooltip {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.1);
    width: 300px;
    z-index: 1050; /* Increased z-index to ensure it appears above other elements */
    border: 1px solid rgba(0,0,0,0.1);
}

.employee-stat-box {
    position: relative;
    /* Add this to ensure proper stacking context */
    z-index: 1;
}

/* Add this new style */
.employee-stat-box:hover {
    z-index: 1051; /* Higher than the tooltip to ensure proper stacking */
}

.employee-stat-box:hover .present-users-tooltip {
    display: block;
}

.tooltip-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tooltip-header span {
    font-weight: 600;
    color: #333;
}

.tooltip-date {
    font-size: 0.85em;
    color: #666;
}

.tooltip-content {
    max-height: 250px;
    overflow-y: auto;
    padding: 10px;
}

.tooltip-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.tooltip-item:last-child {
    border-bottom: none;
}

.tooltip-item i {
    color: #1a73e8;
    font-size: 1.1em;
}

.tooltip-item .user-name {
    flex: 1;
    font-size: 0.9em;
    color: #333;
}

.tooltip-item .punch-time {
    font-size: 0.8em;
    color: #666;
    font-weight: 500;
}

.no-users {
    padding: 20px;
    text-align: center;
    color: #666;
    font-size: 0.9em;
}

/* Scrollbar styling */
.tooltip-content::-webkit-scrollbar {
    width: 4px;
}

.tooltip-content::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.05);
}

.tooltip-content::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.2);
    border-radius: 2px;
}

/* Add arrow to tooltip */
.present-users-tooltip::before {
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

/* Global tooltip z-index handling */
.stat-card,
.site-stat-box,
.employee-stat-box {
    position: relative;
    z-index: 1;
}

.stat-card:hover,
.site-stat-box:hover,
.employee-stat-box:hover {
    z-index: 1051;
}

.stat-tooltip,
.site-stat-tooltip,
.present-users-tooltip {
    z-index: 1050;
}

/* Leave Box Tooltip Styles */
.leave-box .stat-tooltip {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.1);
    width: 320px;
    z-index: 1050;
    border: 1px solid rgba(0,0,0,0.1);
    visibility: hidden;
    opacity: 0;
    transition: all 0.3s ease;
}

.leave-box:hover .stat-tooltip {
    visibility: visible;
    opacity: 1;
}

.leave-box .tooltip-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.leave-box .tooltip-item:last-child {
    border-bottom: none;
}

.leave-box .tooltip-item i {
    color: #1a73e8;
    font-size: 1.1em;
}

.leave-box .user-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.leave-box .user-name {
    font-size: 0.9em;
    color: #333;
    font-weight: 500;
}

.leave-box .leave-type {
    font-size: 0.8em;
    color: #666;
}

.leave-box .leave-duration {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.leave-box .dates {
    font-size: 0.8em;
    color: #1a73e8;
    font-weight: 500;
}

.leave-box .days {
    font-size: 0.75em;
    color: #666;
}

.leave-box .no-users {
    padding: 20px;
    text-align: center;
    color: #666;
    font-size: 0.9em;
}

/* Add arrow to tooltip */
.leave-box .stat-tooltip::before {
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

/* Remove arrow from present users tooltip */
.present-users-tooltip::before {
    display: none;
}

/* Leave Box Styles */
.leave-box {
    position: relative;
}

/* Leave Box Tooltip Styles */
.leave-tooltip {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.1);
    width: 320px;
    z-index: 1050;
    border: 1px solid rgba(0,0,0,0.1);
    visibility: hidden;
    opacity: 0;
    transition: all 0.3s ease;
}

.leave-box:hover .leave-tooltip {
    visibility: visible;
    opacity: 1;
}

/* Arrow for leave tooltip */
.leave-tooltip::before {
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

.leave-tooltip .tooltip-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.leave-tooltip .tooltip-content {
    max-height: 250px;
    overflow-y: auto;
    padding: 10px;
}

.leave-tooltip .tooltip-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.leave-tooltip .tooltip-item:last-child {
    border-bottom: none;
}

.leave-tooltip .tooltip-item i {
    color: #1a73e8;
    font-size: 1.1em;
}

.leave-tooltip .user-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.leave-tooltip .user-name {
    font-size: 0.9em;
    color: #333;
    font-weight: 500;
}

.leave-tooltip .leave-type {
    font-size: 0.8em;
    color: #666;
}

.leave-tooltip .leave-duration {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.leave-tooltip .dates {
    font-size: 0.8em;
    color: #1a73e8;
    font-weight: 500;
}

.leave-tooltip .days {
    font-size: 0.75em;
    color: #666;
}

.leave-tooltip .no-users {
    padding: 20px;
    text-align: center;
    color: #666;
    font-size: 0.9em;
}

/* Present Box Styles */
.present-box {
    position: relative;
}

/* Present Users Tooltip Styles */
.present-tooltip {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.1);
    width: 300px;
    z-index: 1050;
    border: 1px solid rgba(0,0,0,0.1);
    visibility: hidden;
    opacity: 0;
    transition: all 0.3s ease;
}

.present-box:hover .present-tooltip {
    visibility: visible;
    opacity: 1;
}

/* Arrow for present tooltip */
.present-tooltip::before {
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

.present-tooltip .tooltip-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.present-tooltip .tooltip-content {
    max-height: 250px;
    overflow-y: auto;
    padding: 10px;
}

.present-tooltip .tooltip-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.present-tooltip .tooltip-item:last-child {
    border-bottom: none;
}

.present-tooltip .tooltip-item i {
    color: #1a73e8;
    font-size: 1.1em;
}

.present-tooltip .user-name {
    flex: 1;
    font-size: 0.9em;
    color: #333;
    font-weight: 500;
}

.present-tooltip .punch-time {
    font-size: 0.8em;
    color: #1a73e8;
    font-weight: 500;
}

.present-tooltip .no-users {
    padding: 20px;
    text-align: center;
    color: #666;
    font-size: 0.9em;
}

/* Global tooltip z-index handling */
.employee-stat-box {
    position: relative;
    z-index: 1;
}

.employee-stat-box:hover {
    z-index: 1051;
}

.present-tooltip,
.leave-tooltip {
    z-index: 1050;
}

        </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo-container">
            <img src="Hive Tag line 11 (1).png" alt="Company Logo">
            <span class="logo-text">ArchitectsHive</span>
        </div>
        <button class="toggle-btn" title="Toggle Sidebar">
            <i class="fas fa-angle-left"></i>
        </button>
        <ul class="nav-links">
            <li class="has-submenu">
                <a href="#" class="submenu-trigger">
                    <i class="fas fa-home"></i>
                    <span class="link-text">Dashboard</span>
                </a>
                <ul class="sub-menu">
                    <li>
                        <a href="#">
                            <i class="fas fa-user-tie"></i>
                            <span class="link-text">HR Manager</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="fas fa-video"></i>
                            <span class="link-text">Studio Manager</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="fas fa-building"></i>
                            <span class="link-text">Site Manager</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="fas fa-bullhorn"></i>
                            <span class="link-text">Marketing Manager</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="fas fa-share-alt"></i>
                            <span class="link-text">Social Media Manager</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="fas fa-laptop-code"></i>
                            <span class="link-text">IT Manager</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="employee_passwords.php">
                    <i class="fas fa-id-badge"></i>
                    <span class="link-text">Employee ID and Passwords</span>
                </a>
            </li>
            <li>
                <a href="category_view.php">
                    <i class="fas fa-tasks"></i>
                    <span class="link-text">Task Overview</span>
                </a>
            </li>
            <li>
                <a href="admin_attendance.php">
                    <i class="fas fa-calendar-check"></i>
                    <span class="link-text">Attendance</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-users"></i>
                    <span class="link-text">Users</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-cog"></i>
                    <span class="link-text">Settings</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="link-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div id="greeting" class="greeting-section">
            <div class="greeting-content">
                <i id="greeting-icon" class="greeting-icon"></i>
                <div>
                    <h1 id="greeting-text">Hello, <?php echo htmlspecialchars($adminUsername); ?>!</h1>
                    <div id="date-time" class="date-time">
                        <i class="fas fa-calendar-alt"></i> <span id="current-date"></span>
                        <i class="fas fa-clock"></i> <span id="current-time"></span>
                    </div>
                </div>
            </div>
            <div id="weather" class="weather-section">
                <i id="weather-icon" class="weather-icon"></i>
                <span id="weather-description"></span>
                <span id="weather-temp"></span>
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
                            <h2><?php echo $total_users; ?></h2>
                        </div>
                        <div class="icon-box">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        
                        <!-- Tooltip Content -->
                        <div class="stat-tooltip">
                            <div class="tooltip-header">
                                Today's Attendance
                                <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <?php
                                // Calculate percentages safely
                                $total = max(1, $total_users); // Prevent division by zero
                                $present_percentage = ($present_count / $total) * 100;
                                $absent_percentage = ($absent_count / $total) * 100;
                                $leaves_percentage = ($leaves_count / $total) * 100;
                                ?>
                                <div class="tooltip-item">
                                    <i class="fas fa-user-check text-success"></i>
                                    <span class="label">Present:</span>
                                    <span class="value"><?php echo $present_count; ?> / <?php echo $total_users; ?></span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $present_percentage; ?>%; background: #28a745;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-user-times text-danger"></i>
                                    <span class="label">Absent:</span>
                                    <span class="value"><?php echo $absent_count; ?> / <?php echo $total_users; ?></span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $absent_percentage; ?>%; background: #dc3545;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-user-clock text-warning"></i>
                                    <span class="label">On Leave:</span>
                                    <span class="value"><?php echo $leaves_count; ?> / <?php echo $total_users; ?></span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $leaves_percentage; ?>%; background: #ffc107;"></div>
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
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        
                        <!-- Tooltip Content -->
                        <div class="stat-tooltip">
                            <div class="tooltip-header">
                                Project Distribution
                                <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <div class="tooltip-item">
                                    <i class="fas fa-building text-primary"></i>
                                    <span class="label">Architecture:</span>
                                    <span class="value"><?php echo $architecture_count; ?> out of <?php echo $total_projects; ?></span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($architecture_count / max(1, $total_projects)) * 100; ?>%"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-hard-hat text-warning"></i>
                                    <span class="label">Construction:</span>
                                    <span class="value"><?php echo $construction_count; ?> out of <?php echo $total_projects; ?></span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($construction_count / max(1, $total_projects)) * 100; ?>%"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-couch text-success"></i>
                                    <span class="label">Interior:</span>
                                    <span class="value"><?php echo $interior_count; ?> out of <?php echo $total_projects; ?></span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($interior_count / max(1, $total_projects)) * 100; ?>%"></div>
                                    </div>
                                </div>
                                
                                <!-- Additional Info Section -->
                                <div class="tooltip-footer">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Total active projects: <?php echo $total_projects; ?>
                                    </small>
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
                            <i class="fas fa-tasks"></i>
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
                            <i class="fas fa-hourglass-half"></i>
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

                <!-- Received Payments Card -->
                <div class="col-md-3">
                    <div class="stat-card" data-tooltip="received-payments">
                        <div class="stat-info">
                            <h6>Monthly Payments Received</h6>
                            <h2>₹<?php echo number_format($monthly_received, 1); ?>L</h2>
                        </div>
                        <div class="icon-box">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        
                        <div class="stat-tooltip">
                            <div class="tooltip-header">
                                Payment Breakdown
                                <span class="tooltip-date"><?php echo date('F Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <div class="tooltip-item">
                                    <i class="fas fa-building text-primary"></i>
                                    <span class="label">Architecture:</span>
                                    <span class="value">₹<?php echo number_format($architecture_payments, 1); ?>L</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($architecture_payments / max(1, $monthly_total)) * 100; ?>%; background: #0052cc;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-hard-hat text-warning"></i>
                                    <span class="label">Construction:</span>
                                    <span class="value">₹<?php echo number_format($construction_payments, 1); ?>L</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($construction_payments / max(1, $monthly_total)) * 100; ?>%; background: #ffc107;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-couch text-success"></i>
                                    <span class="label">Interior:</span>
                                    <span class="value">₹<?php echo number_format($interior_payments, 1); ?>L</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($interior_payments / max(1, $monthly_total)) * 100; ?>%; background: #28a745;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Payments Card -->
                <div class="col-md-3">
                    <div class="stat-card" data-tooltip="total-payments">
                        <div class="stat-info">
                            <h6>Monthly Payouts</h6>
                            <h2>₹<?php echo number_format($monthly_total, 1); ?>L</h2>
                        </div>
                        <div class="icon-box">
                            <i class="fas fa-coins"></i>
                        </div>
                        
                        <div class="stat-tooltip">
                            <div class="tooltip-header">
                                Payment Status
                                <span class="tooltip-date"><?php echo date('F Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <div class="tooltip-item">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span class="label">Received:</span>
                                    <span class="value">₹<?php echo number_format($received_payments, 1); ?>L</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($received_payments / max(1, $monthly_total)) * 100; ?>%; background: #28a745;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-clock text-warning"></i>
                                    <span class="label">Pending:</span>
                                    <span class="value">₹<?php echo number_format($pending_payments, 1); ?>L</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($pending_payments / max(1, $monthly_total)) *100; ?>%; background: #ffc107;"></div>
                                    </div>
                                </div>
                                <div class="tooltip-item">
                                    <i class="fas fa-exclamation-circle text-danger"></i>
                                    <span class="label">Overdue:</span>
                                    <span class="value">₹<?php echo number_format($overdue_payments, 1); ?>L</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($overdue_payments / max(1, $monthly_total)) *100; ?>%; background: #dc3545;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="tooltip-footer">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    Data for <?php echo date('F Y'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sales-overview">
            <div class="sales-header">
                <div class="sales-title">
                    <i class="fas fa-chart-line"></i>
                    Sales Overview
                </div>
                <form id="salesFilterForm" class="date-filters">
                    <input 
                        type="date" 
                        name="from_date" 
                        id="from_date"
                        class="date-input" 
                        value="<?php echo htmlspecialchars($from_date); ?>"
                        required
                    >
                    <span>to</span>
                    <input 
                        type="date" 
                        name="end_date" 
                        id="end_date"
                        class="date-input" 
                        value="<?php echo htmlspecialchars($end_date); ?>"
                        required
                    >
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </form>
            </div>

            <div class="sales-grid">
                <!-- Total Sales Box -->
                <div class="total-sales-box">
                    <h3>Total Sales</h3>
                    <div class="amount">₹<?php echo number_format($sales_data['total_sales'], 1); ?>L</div>
                    <div class="total-projects">
                        Total Projects: <?php echo $sales_data['project_counts']['total']; ?>
                    </div>
                </div>

                <!-- Pipeline Projects Card -->
                <div class="pipeline-card">
                    <div class="pipeline-header">
                        <h4>
                            <i class="fas fa-stream"></i>
                            Projects in Pipeline
                        </h4>
                        <div class="pipeline-stats">
                            <span class="stat-item">
                                <i class="fas fa-clipboard-list"></i>
                                <?php echo count($pipeline_projects); ?> Projects
                            </span>
                        </div>
                    </div>
                    
                    <div class="pipeline-projects-container">
                        <?php if (empty($pipeline_projects)): ?>
                            <div class="no-projects">
                                <i class="fas fa-info-circle"></i>
                                No projects found
                            </div>
                        <?php else: ?>
                            <?php foreach ($pipeline_projects as $project): ?>
                                <div class="pipeline-project">
                                    <div class="project-header">
                                        <div class="project-header-left">
                                            <span class="project-type-tag <?php echo htmlspecialchars($project['project_type']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($project['project_type'])); ?>
                                            </span>
                                            <span class="project-status <?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>">
                                                <?php echo ucfirst(htmlspecialchars($project['status'])); ?>
                                            </span>
                                        </div>
                                        <span class="project-date">
                                            <?php echo date('d M Y', strtotime($project['created_at'])); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="project-name">
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </div>
                                    
                                    <div class="project-details">
                                        <div class="detail-item">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($project['client_name']); ?>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($project['mobile']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="project-details">
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($project['location']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="project-footer">
                                        <div class="project-value">
                                            <i class="fas fa-indian-rupee-sign"></i>
                                            <?php echo number_format($project['total_cost'] / 100000, 1); ?>L
                                        </div>
                                        <div class="project-id">
                                            #<?php echo $project['id']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sales Breakdown Cards -->
                <div class="sales-breakdown">
                    <!-- Architecture Card -->
                    <div class="sales-card architecture-card">
                        <h4>
                            <i class="fas fa-building text-primary"></i>
                            Architecture Consultancy
                        </h4>
                        <div class="amount"><?php echo number_format($sales_data['architecture'], 1); ?>L</div>
                        <div class="project-count">
                            Projects: <?php echo $sales_data['project_counts']['architecture']; ?>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($sales_data['architecture'] / max(1, $sales_data['total_sales'])) * 100; ?>%"></div>
                        </div>
                    </div>

                    <!-- Interior Card -->
                    <div class="sales-card interior-card">
                        <h4>
                            <i class="fas fa-couch text-success"></i>
                            Interior Consultancy
                        </h4>
                        <div class="amount">₹<?php echo number_format($sales_data['interior'], 1); ?>L</div>
                        <div class="project-count">
                            Projects: <?php echo $sales_data['project_counts']['interior']; ?>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($sales_data['interior'] / max(1, $sales_data['total_sales'])) * 100; ?>%"></div>
                        </div>
                    </div>

                    <!-- Construction Card -->
                    <div class="sales-card construction-card">
                        <h4>
                            <i class="fas fa-hard-hat text-warning"></i>
                            Construction Consultancy
                        </h4>
                        <div class="amount">₹<?php echo number_format($sales_data['construction'], 1); ?>L</div>
                        <div class="project-count">
                            Projects: <?php echo $sales_data['project_counts']['construction']; ?>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($sales_data['construction'] / max(1, $sales_data['total_sales'])) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="project-overview">
            <div class="project-header">
                <div class="project-title">
                    <i class="fas fa-project-diagram"></i>
                    Project Overview
                </div>
                <form id="projectFilterForm" class="date-filters">
                    <input 
                        type="date" 
                        name="project_from_date" 
                        id="project_from_date"
                        class="date-input" 
                        value="<?php echo date('Y-m-01'); ?>"
                        required
                    >
                    <span>to</span>
                    <input 
                        type="date" 
                        name="project_end_date" 
                        id="project_end_date"
                        class="date-input" 
                        value="<?php echo date('Y-m-t'); ?>"
                        required
                    >
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </form>
            </div>

            <div class="project-grid">
                <!-- Total Projects Box -->
                <div class="total-projects-box">
                    <h3>Total Projects</h3>
                    <div class="amount"><?php echo $total_all_projects; ?></div>
                    <div class="total-distribution">
                        Active Projects Distribution
                    </div>
                </div>

                <!-- Project Breakdown Cards -->
                <div class="project-breakdown">
                    <!-- Architecture Card -->
                    <div class="project-card architecture-card">
                        <h4>
                            <i class="fas fa-building text-primary"></i>
                            Architecture Projects
                        </h4>
                        <div class="amount"><?php echo $architecture_total; ?></div>
                        <div class="project-count">
                            out of <?php echo $total_all_projects; ?> Total Projects
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($architecture_total / max(1, $total_all_projects)) * 100; ?>%"></div>
                        </div>
                    </div>

                    <!-- Interior Card -->
                    <div class="project-card interior-card">
                        <h4>
                            <i class="fas fa-couch text-success"></i>
                            Interior Projects
                        </h4>
                        <div class="amount"><?php echo $interior_total; ?></div>
                        <div class="project-count">
                            out of <?php echo $total_all_projects; ?> Total Projects
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($interior_total / max(1, $total_all_projects)) * 100; ?>%"></div>
                        </div>
                    </div>

                    <!-- Construction Card -->
                    <div class="project-card construction-card">
                        <h4>
                            <i class="fas fa-hard-hat text-warning"></i>
                            Construction Projects
                        </h4>
                        <div class="amount"><?php echo $construction_total; ?></div>
                        <div class="project-count">
                            out of <?php echo $total_all_projects; ?> Total Projects
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($construction_total / max(1, $total_all_projects)) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Today's Tasks Card -->
                <div class="tasks-card">
                    <div class="tasks-header">
                        <h4>
                            <i class="fas fa-tasks"></i>
                            Today's Tasks
                        </h4>
                        <div class="tasks-stats">
                            <span class="stat-item">
                                <i class="fas fa-clipboard-list"></i>
                                <?php echo count($today_tasks); ?> Tasks
                            </span>
                        </div>
                    </div>
                    
                    <div class="tasks-container">
                        <?php if (empty($today_tasks)): ?>
                            <div class="no-tasks">
                                <i class="fas fa-info-circle"></i>
                                No tasks scheduled for today
                            </div>
                        <?php else: ?>
                            <?php foreach ($today_tasks as $task): ?>
                                <div class="task-item">
                                    <div class="task-header">
                                        <div class="task-header-left">
                                            <span class="task-type-tag <?php echo htmlspecialchars($task['project_type']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($task['project_type'])); ?>
                                            </span>
                                            <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </div>
                                        <span class="task-time">
                                            <?php echo date('h:i A', strtotime($task['due_time'])); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="task-name">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </div>
                                    
                                    <?php if (!empty($task['description'])): ?>
                                    <div class="task-description">
                                        <?php echo htmlspecialchars(substr($task['description'], 0, 100)) . (strlen($task['description']) > 100 ? '...' : ''); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="task-footer">
                                        <div class="assigned-to">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($task['assigned_user_name'] ?? 'Unassigned'); ?>
                                        </div>
                                        <div class="task-status <?php echo strtolower($task['status']); ?>">
                                            <?php echo ucfirst($task['status']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="task-overview">
            <div class="task-header">
                <div class="task-title">
                    <i class="fas fa-tasks"></i>
                    Task Overview
                </div>
                <form id="taskFilterForm" class="date-filters">
                    <input type="date" name="task_from_date" id="task_from_date" class="date-input" value="<?php echo date('Y-m-01'); ?>" required>
                    <span>to</span>
                    <input type="date" name="task_end_date" id="task_end_date" class="date-input" value="<?php echo date('Y-m-t'); ?>" required>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </form>
            </div>

            <div class="task-grid">
                <!-- Left Side: Task Stats -->
                <div class="task-stats">
                    <!-- Total Tasks Box with Tooltip -->
                    <div class="task-overview-box" data-tooltip="priority-breakdown">
                        <h3>Total Tasks</h3>
                        <div class="amount"><?php echo $task_stats['total']; ?></div>
                        <div class="overview-subtitle">Active tasks this month</div>
                        
                        <!-- Custom Tooltip Content -->
                        <div class="task-priority-tooltip">
                            <div class="tooltip-header">
                                Priority Distribution
                                <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                            </div>
                            <div class="tooltip-content">
                                <div class="priority-item urgent-level">
                                    <i class="fas fa-arrow-up"></i>
                                    <span class="label">High Priority:</span>
                                    <span class="value"><?php echo $task_stats['priority']['high']; ?></span>
                                    <div class="priority-meter">
                                        <div class="meter-fill" style="width: <?php echo ($task_stats['priority']['high'] / max(1, $task_stats['total'])) * 100; ?>%"></div>
                                    </div>
                                </div>
                                <div class="priority-item moderate-level">
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="label">Medium Priority:</span>
                                    <span class="value"><?php echo $task_stats['priority']['medium']; ?></span>
                                    <div class="priority-meter">
                                        <div class="meter-fill" style="width: <?php echo ($task_stats['priority']['medium'] / max(1, $task_stats['total'])) * 100; ?>%"></div>
                                    </div>
                                </div>
                                <div class="priority-item normal-level">
                                    <i class="fas fa-arrow-down"></i>
                                    <span class="label">Low Priority:</span>
                                    <span class="value"><?php echo $task_stats['priority']['low']; ?></span>
                                    <div class="priority-meter">
                                        <div class="meter-fill" style="width: <?php echo ($task_stats['priority']['low'] / max(1, $task_stats['total'])) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Three Status Boxes -->
                    <div class="task-status-breakdown">
                        <!-- Number of Stages -->
                        <div class="status-metric-box stages-box">
                            <h4><i class="fas fa-layer-group"></i> Number of Stages</h4>
                            <div class="metric-value"><?php echo $task_stats['total_stages']; ?></div>
                            <div class="metric-subtitle">Total workflow stages</div>
                            <div class="metric-progress">
                                <div class="progress-fill" style="width: 100%"></div>
                            </div>
                        </div>

                        <!-- Stages Pending -->
                        <div class="status-metric-box pending-box">
                            <h4><i class="fas fa-hourglass-half"></i> Stages Pending</h4>
                            <div class="metric-value">
                                <?php echo ($task_stats['total_stages'] - $task_stats['completed_stages']); ?>
                                <span class="metric-total">/ <?php echo $task_stats['total_stages']; ?></span>
                            </div>
                            <div class="metric-subtitle">Stages awaiting completion</div>
                            <div class="metric-progress">
                                <div class="progress-fill" style="width: <?php echo (($task_stats['total_stages'] - $task_stats['completed_stages']) / max(1, $task_stats['total_stages'])) * 100; ?>%"></div>
                            </div>
                        </div>

                        <!-- Delayed Tasks -->
                        <div class="status-metric-box delayed-box">
                            <h4><i class="fas fa-clock-rotate-left"></i> Tasks Delayed</h4>
                            <div class="metric-value"><?php echo $task_stats['delayed']; ?></div>
                            <div class="metric-subtitle">Overdue tasks</div>
                            <div class="metric-progress">
                                <div class="progress-fill" style="width: <?php echo ($task_stats['delayed'] / max(1, $task_stats['total'])) * 100; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Calendar remains unchanged -->
                <div class="task-calendar-card">
                    <div class="calendar-header">
                        <h4>
                            <i class="fas fa-calendar-alt"></i> 
                            Task Calendar
                        </h4>
                        <div class="calendar-stats">
                            <span class="delayed-stat">
                                <i class="fas fa-clock text-warning"></i> 
                                <?php echo intval($delayed_count); ?> delayed
                            </span>
                            <span>•</span>
                            <span class="completed-stat">
                                <i class="fas fa-check text-success"></i> 
                                <?php echo intval($completed_count); ?> completed
                            </span>
                        </div>
                    </div>
                    <div class="calendar-container">
                        <div class="month-navigation">
                            <button id="prevMonth" class="nav-btn"><i class="fas fa-chevron-left"></i></button>
                            <h5 id="currentMonth">November 2024</h5>
                            <button id="nextMonth" class="nav-btn"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="calendar-grid">
                            <div class="weekday">Sun</div>
                            <div class="weekday">Mon</div>
                            <div class="weekday">Tue</div>
                            <div class="weekday">Wed</div>
                            <div class="weekday">Thu</div>
                            <div class="weekday">Fri</div>
                            <div class="weekday">Sat</div>
                            <!-- Calendar days will be inserted here by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Overview Section -->
        <div class="employee-overview">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    Employees Overview
                </div>
            </div>

            <div class="employee-stats-grid">
                <!-- Present Users Box -->
                <div class="employee-stat-box present-box">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
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
                    <div class="present-tooltip"> <!-- Changed class name to be more specific -->
                        <div class="tooltip-header">
                            <span>Present Employees</span>
                            <span class="tooltip-date"><?php echo date('d M Y'); ?></span>
                        </div>
                        <div class="tooltip-content">
                            <?php
                            $present_users_query = "
                                SELECT u.username, u.employee_id, a.punch_in 
                                FROM attendance a 
                                JOIN users u ON a.user_id = u.id 
                                WHERE DATE(a.date) = CURDATE() 
                                AND a.punch_in IS NOT NULL 
                                ORDER BY a.punch_in DESC";
                            
                            $present_users_result = mysqli_query($conn, $present_users_query);
                            
                            if (mysqli_num_rows($present_users_result) > 0) {
                                while ($user = mysqli_fetch_assoc($present_users_result)) {
                                    echo "<div class='tooltip-item'>";
                                    echo "<i class='fas fa-user-circle'></i>";
                                    echo "<span class='user-name'>" . htmlspecialchars($user['username']) . "</span>";
                                    echo "<span class='punch-time'>" . date('h:i A', strtotime($user['punch_in'])) . "</span>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='no-users'>No employees present yet</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Users on Leave Box -->
                <div class="employee-stat-box leave-box">
                    <div class="stat-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h4>On Leave</h4>
                        <div class="stat-numbers">
                            <span class="current"><?php echo $employee_stats['users_on_leave']; ?></span>
                            <span class="divider">/</span>
                            <span class="total"><?php echo $employee_stats['total_users']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($employee_stats['users_on_leave'] / max(1, $employee_stats['total_users'])) * 100; ?>%"></div>
                        </div>
                        <div class="stat-label">Full Day Leave</div>
                    </div>

                    <!-- Leave Users Tooltip -->
                    <div class="leave-tooltip"> <!-- Changed class name to be more specific -->
                        <div class="tooltip-header">
                            <span>Employees on Leave</span>
                            <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                        </div>
                        <div class="tooltip-content">
                            <?php
                            $leave_users_query = "
                                SELECT u.username, u.employee_id, l.leave_type, l.start_date, l.end_date 
                                FROM leaves l 
                                JOIN users u ON l.user_id = u.id 
                                WHERE l.status = 'approved' 
                                AND CURDATE() BETWEEN l.start_date AND l.end_date 
                                ORDER BY l.start_date ASC";
                            
                            $leave_users_result = mysqli_query($conn, $leave_users_query);
                            
                            if (mysqli_num_rows($leave_users_result) > 0) {
                                while ($user = mysqli_fetch_assoc($leave_users_result)) {
                                    $duration = (strtotime($user['end_date']) - strtotime($user['start_date'])) / (60 * 60 * 24) + 1;
                                    echo "<div class='tooltip-item'>";
                                    echo "<i class='fas fa-user-clock'></i>";
                                    echo "<div class='user-info'>";
                                    echo "<span class='user-name'>" . htmlspecialchars($user['username']) . "</span>";
                                    echo "<span class='leave-type'>" . htmlspecialchars($user['leave_type']) . "</span>";
                                    echo "</div>";
                                    echo "<div class='leave-duration'>";
                                    echo "<span class='dates'>" . date('d M', strtotime($user['start_date']));
                                    if ($user['start_date'] != $user['end_date']) {
                                        echo " - " . date('d M', strtotime($user['end_date']));
                                    }
                                    echo "</span>";
                                    echo "<span class='days'>(" . $duration . " day" . ($duration > 1 ? "s" : "") . ")</span>";
                                    echo "</div>";
                                    echo "</div>";
                                }
                            } else {
                            echo "<div class='no-users'>No employees on leave today</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Pending Leaves Box -->
                <div class="employee-stat-box pending-box" data-tooltip="pending-leaves">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Pending Leaves</h4>
                        <div class="stat-numbers">
                            <span class="current"><?php echo $employee_stats['pending_leaves']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($employee_stats['pending_leaves'] / max(1, $employee_stats['total_users'])) * 100); ?>%"></div>
                        </div>
                        <div class="stat-label">Awaiting Approval</div>
                    </div>
                    
                    <!-- Tooltip Content -->
                    <div class="stat-tooltip">
                        <div class="tooltip-header">
                            <span>Pending Leave Requests</span>
                            <span class="tooltip-date"><?php echo date('d M, Y'); ?></span>
                        </div>
                        <div class="tooltip-content">
                            <?php if (empty($pending_leaves_details)): ?>
                                <div class="no-pending-leaves">
                                    <i class="fas fa-check-circle"></i>
                                    No pending leave requests
                                </div>
                            <?php else: ?>
                                <?php foreach ($pending_leaves_details as $leave): ?>
                                    <div class="pending-leave-item">
                                        <div class="leave-header">
                                            <span class="employee-name">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($leave['employee_name']); ?>
                                            </span>
                                            <span class="leave-type <?php echo strtolower($leave['leave_type']); ?>">
                                                <?php echo htmlspecialchars($leave['leave_type']); ?>
                                            </span>
                                        </div>
                                        <div class="leave-dates">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php 
                                                $start = date('d M', strtotime($leave['start_date']));
                                                $end = date('d M', strtotime($leave['end_date']));
                                                echo $start === $end ? $start : "$start - $end"; 
                                            ?>
                                            <span class="days-count">(<?php echo $leave['days_count']; ?> days)</span>
                                        </div>
                                        <div class="approval-status">
                                            <?php if ($leave['studio_manager_status'] === 'pending'): ?>
                                                <span class="status-badge studio">Studio Manager Approval Pending</span>
                                            <?php endif; ?>
                                            <?php if ($leave['manager_status'] === 'pending'): ?>
                                                <span class="status-badge manager">Manager Approval Pending</span>
                                            <?php endif; ?>
                                            <?php if ($leave['hr_status'] === 'pending'): ?>
                                                <span class="status-badge hr">HR Approval Pending</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Short Leave Box -->
                <div class="employee-stat-box short-leave-box">
                    <div class="stat-icon">
                        <i class="fas fa-user-minus"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Short Leave</h4>
                        <div class="stat-numbers">
                            <span class="current"><?php echo $employee_stats['short_leave']; ?></span>
                            <span class="divider">/</span>
                            <span class="total"><?php echo $employee_stats['total_users']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($employee_stats['short_leave'] / max(1, $employee_stats['total_users'])) * 100; ?>%"></div>
                        </div>
                        <div class="stat-label">Today's Short Leaves</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add this after your existing employee stat boxes -->
        <div class="employee-stats-grid additional-boxes">
            <!-- Announcements Box -->
            <div class="employee-stat-box announcement-box">
                <div class="stat-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="stat-content">
                    <div class="box-header">
                        <h4>Announcements</h4>
                        <button class="add-btn" onclick="showAnnouncementModal()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="box-content">
                        <!-- You can add a loop here to show recent announcements -->
                        <div class="no-content">No recent announcements</div>
                    </div>
                </div>
            </div>

            <!-- Circulars Box -->
            <div class="employee-stat-box circular-box">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="box-header">
                        <h4>Circulars</h4>
                        <button class="add-btn" onclick="showCircularModal()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="box-content">
                        <div class="no-content">No recent circulars</div>
                    </div>
                </div>
            </div>

            <!-- Events Box -->
            <div class="employee-stat-box event-box">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <div class="box-header">
                        <h4>Events</h4>
                        <button class="add-btn" onclick="showEventModal()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="box-content">
                        <div class="no-content">No upcoming events</div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Leaves/Holidays Box -->
            <div class="employee-stat-box holiday-box">
                <div class="stat-icon">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <div class="stat-content">
                    <div class="box-header">
                        <h4>Upcoming Leaves/Holidays</h4>
                        <button class="add-btn" onclick="showLeaveModal()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="box-content">
                        <div class="no-content">No upcoming holidays</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add this after the employee overview section -->
        <div class="site-overview">
            <div class="site-header">
                <div class="site-title">
                    <i class="fas fa-hard-hat"></i>
                    Site Overview
                </div>
                <div class="site-selector">
                    <select class="site-select" id="constructionSiteSelect">
                        <option value="">Select Construction Site</option>
                        <option value="site1">Residential Complex - Phase 1</option>
                        <option value="site2">Commercial Plaza - Block A</option>
                        <option value="site3">Township Project - Zone 2</option>
                        <!-- Add more sites as needed -->
                    </select>
                </div>
            </div>

            <div class="site-stats-grid">
                <!-- Total Active Sites -->
                <div class="site-stat-box active-site-box">
                    <div class="site-stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="site-stat-content">
                        <h4>Total Site Active</h4>
                        <div class="site-stat-numbers">3</div>
                        <div class="site-stat-label">Current active sites</div>
                    </div>
                    
                    <!-- Active Sites Tooltip -->
                    <div class="site-stat-tooltip">
                        <div class="tooltip-header">
                            <span>Active Sites Overview</span>
                            <i class="fas fa-building text-primary"></i>
                        </div>
                        <div class="tooltip-content">
                            <div class="tooltip-item">
                                <i class="fas fa-hammer"></i>
                                <span>Under Construction: 2</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-drafting-compass"></i>
                                <span>Planning Phase: 1</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-clock"></i>
                                <span>Average Completion: 65%</span>
                            </div>
                            <div class="tooltip-progress">
                                <div class="tooltip-progress-fill" style="width: 65%; background: #10b981;"></div>
                            </div>
                        </div>
                        <div class="tooltip-footer">
                            <i class="fas fa-info-circle"></i>
                            Last updated: Today at 9:00 AM
                        </div>
                    </div>
                </div>

                <!-- Manager on Site -->
                <div class="site-stat-box manager-box">
                    <div class="site-stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="site-stat-content">
                        <h4>Manager on Site</h4>
                        <div class="site-stat-numbers">2</div>
                        <div class="site-stat-label">Present today</div>
                    </div>
                    
                    <!-- Managers Tooltip -->
                    <div class="site-stat-tooltip">
                        <div class="tooltip-header">
                            <span>Site Managers Details</span>
                            <i class="fas fa-user-tie text-indigo-600"></i>
                        </div>
                        <div class="tooltip-content">
                            <div class="tooltip-item">
                                <i class="fas fa-user-check"></i>
                                <span>Present: 2 out of 3</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Site A: John Smith</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Site B: Sarah Johnson</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-user-clock"></i>
                                <span>On Leave: Mike Wilson</span>
                            </div>
                        </div>
                        <div class="tooltip-footer">
                            <i class="fas fa-info-circle"></i>
                            Attendance updated at 8:30 AM
                        </div>
                    </div>
                </div>

                <!-- Engineer on Site -->
                <div class="site-stat-box engineer-box">
                    <div class="site-stat-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="site-stat-content">
                        <h4>Engineer on Site</h4>
                        <div class="site-stat-numbers">4</div>
                        <div class="site-stat-label">Present today</div>
                    </div>
                    
                    <!-- Engineers Tooltip -->
                    <div class="site-stat-tooltip">
                        <div class="tooltip-header">
                            <span>Site Engineers Status</span>
                            <i class="fas fa-user-cog text-yellow-600"></i>
                        </div>
                        <div class="tooltip-content">
                            <div class="tooltip-item">
                                <i class="fas fa-hard-hat"></i>
                                <span>Civil Engineers: 2</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-bolt"></i>
                                <span>Electrical Engineers: 1</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-temperature-high"></i>
                                <span>MEP Engineers: 1</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-tasks"></i>
                                <span>Active Projects: 3</span>
                            </div>
                        </div>
                        <div class="tooltip-footer">
                            <i class="fas fa-info-circle"></i>
                            All engineers reported on time
                        </div>
                    </div>
                </div>

                <!-- Supervisor on Site -->
                <div class="site-stat-box supervisor-box">
                    <div class="site-stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="site-stat-content">
                        <h4>Supervisor on Site</h4>
                        <div class="site-stat-numbers">6</div>
                        <div class="site-stat-label">Present today</div>
                    </div>
                    
                    <!-- Supervisors Tooltip -->
                    <div class="site-stat-tooltip">
                        <div class="tooltip-header">
                            <span>Site Supervisors Distribution</span>
                            <i class="fas fa-user-shield text-red-600"></i>
                        </div>
                        <div class="tooltip-content">
                            <div class="tooltip-item">
                                <i class="fas fa-building"></i>
                                <span>Site A: 2 Supervisors</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-building"></i>
                                <span>Site B: 3 Supervisors</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-building"></i>
                                <span>Site C: 1 Supervisor</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-clock"></i>
                                <span>Average Time on Site: 8.5 hrs</span>
                            </div>
                        </div>
                        <div class="tooltip-footer">
                            <i class="fas fa-info-circle"></i>
                            All zones under supervision
                        </div>
                    </div>
                </div>

                <!-- Labour Present -->
                <div class="site-stat-box labour-box">
                    <div class="site-stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="site-stat-content">
                        <h4>Labour Present</h4>
                        <div class="site-stat-numbers">45</div>
                        <div class="site-stat-label">Present today</div>
                    </div>
                    
                    <!-- Labour Tooltip -->
                    <div class="site-stat-tooltip">
                        <div class="tooltip-header">
                            <span>Labour Force Details</span>
                            <i class="fas fa-users text-purple-600"></i>
                        </div>
                        <div class="tooltip-content">
                            <div class="tooltip-item">
                                <i class="fas fa-hammer"></i>
                                <span>Skilled Workers: 25</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-hands-helping"></i>
                                <span>Semi-Skilled: 15</span>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-user"></i>
                                <span>Helpers: 5</span>
                            </div>
                            <div class="tooltip-progress">
                                <div class="tooltip-progress-fill" style="width: 90%; background: #8b5cf6;"></div>
                            </div>
                            <div class="tooltip-item">
                                <i class="fas fa-percentage"></i>
                                <span>Attendance Rate: 90%</span>
                            </div>
                        </div>
                        <div class="tooltip-footer">
                            <i class="fas fa-info-circle"></i>
                            Morning shift: 35 | Evening shift: 10
                        </div>
                    </div>
                </div>
            </div>
        </div>
<!-- Add this before closing body tag -->
<div class="modal-overlay" id="announcementModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New Announcement</h3>
            <button class="close-modal" onclick="closeAnnouncementModal()">&times;</button>
        </div>
        <form class="announcement-form" id="announcementForm">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="content">Content</label>
                <textarea id="content" name="content" required></textarea>
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority" required>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            <button type="submit" class="submit-btn">Add Announcement</button>
        </form>
    </div>
</div>

<!-- Add this modal structure at the end of the body section -->
<div class="modal-overlay" id="circularModal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Add Circular</h2>
            <button class="close-modal" onclick="closeCircularModal()">&times;</button>
        </div>
        <form class="announcement-form" id="circularForm" action="add_circular.php" method="POST">
            <div class="form-group">
                <label for="circularTitle">Title</label>
                <input type="text" id="circularTitle" name="circularTitle" required>
            </div>
            <div class="form-group">
                <label for="circularContent">Content</label>
                <textarea id="circularContent" name="circularContent" required></textarea>
            </div>
            <button type="submit" class="submit-btn">Add Circular</button>
        </form>
    </div>
</div>

<!-- Update the add-btn in the Circulars Box -->
<button class="add-btn" onclick="showCircularModal()">
    <i class="fas fa-plus"></i>
</button>

<!-- Add these JavaScript functions to handle modal display -->
<script>
function showCircularModal() {
    document.getElementById('circularModal').style.display = 'block';
}

function closeCircularModal() {
    document.getElementById('circularModal').style.display = 'none';
}
</script>

<!-- Add this modal structure for Events -->
<div class="modal-overlay" id="eventModal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Add Event</h2>
            <button class="close-modal" onclick="closeEventModal()">&times;</button>
        </div>
        <form class="announcement-form" id="eventForm" action="add_event.php" method="POST">
            <div class="form-group">
                <label for="eventTitle">Event Title</label>
                <input type="text" id="eventTitle" name="eventTitle" required>
            </div>
            <div class="form-group">
                <label for="eventDate">Event Date</label>
                <input type="date" id="eventDate" name="eventDate" required>
            </div>
            <div class="form-group">
                <label for="eventDescription">Description</label>
                <textarea id="eventDescription" name="eventDescription" required></textarea>
            </div>
            <button type="submit" class="submit-btn">Add Event</button>
        </form>
    </div>
</div>

<!-- Add this modal structure for Upcoming Leaves/Holidays -->
<div class="modal-overlay" id="leaveModal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Add Upcoming Leave/Holiday</h2>
            <button class="close-modal" onclick="closeLeaveModal()">&times;</button>
        </div>
        <form class="announcement-form" id="leaveForm" action="add_leave.php" method="POST">
            <div class="form-group">
                <label for="leaveTitle">Leave/Holiday Title</label>
                <input type="text" id="leaveTitle" name="leaveTitle" required>
            </div>
            <div class="form-group">
                <label for="leaveDate">Leave/Holiday Date</label>
                <input type="date" id="leaveDate" name="leaveDate" required>
            </div>
            <div class="form-group">
                <label for="leaveDescription">Description</label>
                <textarea id="leaveDescription" name="leaveDescription" required></textarea>
            </div>
            <button type="submit" class="submit-btn">Add Leave/Holiday</button>
        </form>
    </div>
</div>

<!-- Update the add-btn in the Events Box -->
<button class="add-btn" onclick="showEventModal()">
    <i class="fas fa-plus"></i>
</button>

<!-- Update the add-btn in the Upcoming Leaves/Holidays Box -->
<button class="add-btn" onclick="showLeaveModal()">
    <i class="fas fa-plus"></i>
</button>

<!-- Add these JavaScript functions to handle modal display -->
<script>
function showEventModal() {
    document.getElementById('eventModal').style.display = 'block';
}

function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
}

function showLeaveModal() {
    document.getElementById('leaveModal').style.display = 'block';
}

function closeLeaveModal() {
    document.getElementById('leaveModal').style.display = 'none';
}
</script>
        <script>
            // Wait for DOM to be fully loaded
            document.addEventListener('DOMContentLoaded', function() {
                // Sidebar toggle functionality
                const toggleBtn = document.querySelector('.toggle-btn');
                const sidebar = document.querySelector('.sidebar');

                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function() {
                        sidebar.classList.toggle('close');
                        localStorage.setItem('sidebarState', sidebar.classList.contains('close') ? 'closed' : 'open');
                    });
                }

                // Submenu toggle functionality
                const submenuTrigger = document.querySelector('.submenu-trigger');
                if (submenuTrigger) {
                    submenuTrigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        const parent = this.parentElement;
                        const submenu = parent.querySelector('.sub-menu');
                        
                        submenu.classList.toggle('show');
                        parent.classList.toggle('open');
                        
                        // Close other open submenus
                        const otherSubmenus = document.querySelectorAll('.sub-menu.show');
                        otherSubmenus.forEach(menu => {
                            if (menu !== submenu) {
                                menu.classList.remove('show');
                                menu.parentElement.classList.remove('open');
                            }
                        });
                    });
                }

                // Close submenu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.has-submenu')) {
                        const openSubmenus = document.querySelectorAll('.sub-menu.show');
                        openSubmenus.forEach(menu => {
                            menu.classList.remove('show');
                            menu.parentElement.classList.remove('open');
                        });
                    }
                });

                // Active link handling
                document.querySelectorAll('.nav-links li a').forEach(link => {
                    link.addEventListener('click', function(e) {
                        document.querySelectorAll('.nav-links li a').forEach(l => {
                            l.classList.remove('active');
                        });
                        this.classList.add('active');
                    });
                });

                // Greeting section functionality
                setupGreetingSection();

                // Sales filter functionality
                const salesFilterForm = document.getElementById('salesFilterForm');
                if (salesFilterForm) {
                    salesFilterForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const fromDate = document.getElementById('from_date').value;
                        const endDate = document.getElementById('end_date').value;
                        
                        if (!fromDate || !endDate) {
                            alert('Please select both start and end dates');
                            return;
                        }
                        
                        if (new Date(fromDate) > new Date(endDate)) {
                            alert('Start date cannot be later than end date');
                            return;
                        }
                        
                        updateSalesOverview(fromDate, endDate);
                        savePageState();
                    });
                }

                // Restore page state if needed
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('from_date') || urlParams.has('end_date')) {
                    restorePageState();
                }
                
                // Clear stored position after restoration
                localStorage.removeItem('scrollPosition');

                // Project filter functionality
                const projectFilterForm = document.getElementById('projectFilterForm');
                if (projectFilterForm) {
                    projectFilterForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const fromDate = document.getElementById('project_from_date').value;
                        const endDate = document.getElementById('project_end_date').value;
                        
                        if (!fromDate || !endDate) {
                            alert('Please select both start and end dates');
                            return;
                        }
                        
                        if (new Date(fromDate) > new Date(endDate)) {
                            alert('Start date cannot be later than end date');
                            return;
                        }
                        
                        updateProjectOverview(fromDate, endDate);
                    });
                }

                // Task filter functionality
                const taskFilterForm = document.getElementById('taskFilterForm');
                if (taskFilterForm) {
                    taskFilterForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const fromDate = document.getElementById('task_from_date').value;
                        const endDate = document.getElementById('task_end_date').value;
                        
                        if (!fromDate || !endDate) {
                            alert('Please select both start and end dates');
                            return;
                        }
                        
                        if (new Date(fromDate) > new Date(endDate)) {
                            alert('Start date cannot be later than end date');
                            return;
                        }
                        
                        updateTaskOverview(fromDate, endDate);
                    });
                }

                // Initialize calendar
                initializeCalendar();

                const siteSelect = document.getElementById('constructionSiteSelect');
                
                siteSelect.addEventListener('change', function() {
                    const selectedSite = this.value;
                    if (selectedSite) {
                        // Here you would typically make an AJAX call to fetch the site data
                        // For now, we'll just log the selection
                        console.log('Selected site:', selectedSite);
                        
                        // In the future, you would update the stats based on the response:
                        // updateSiteStats(response.data);
                    }
                });
            });

            // Helper Functions
            function setupGreetingSection() {
                const greetingSection = document.getElementById('greeting');
                const greetingText = document.getElementById('greeting-text');
                const greetingIcon = document.getElementById('greeting-icon');
                const currentDateElement = document.getElementById('current-date');
                const currentTimeElement = document.getElementById('current-time');

                if (!greetingSection || !greetingText || !greetingIcon || !currentDateElement || !currentTimeElement) {
                    return;
                }

                // Get current hour and month
                const currentHour = new Date().getHours();
                const currentMonth = new Date().getMonth();

                // Set greeting based on time of day
                let greetingMessage = '';
                let iconClass = '';
                let timeClass = '';

                if (currentHour >= 5 && currentHour < 12) {
                    greetingMessage = 'Good Morning';
                    iconClass = 'fas fa-sun';
                    timeClass = 'morning';
                } else if (currentHour >= 12 && currentHour < 17) {
                    greetingMessage = 'Good Afternoon';
                    iconClass = 'fas fa-sun';
                    timeClass = 'afternoon';
                } else if (currentHour >= 17 && currentHour < 21) {
                    greetingMessage = 'Good Evening';
                    iconClass = 'fas fa-cloud-sun';
                    timeClass = 'evening';
                } else {
                    greetingMessage = 'Good Night';
                    iconClass = 'fas fa-moon';
                    timeClass = 'night';
                }

                // Apply time-based classes and content
                greetingSection.classList.add(timeClass);
                greetingIcon.className = `greeting-icon ${iconClass}`;
                const username = greetingText.textContent.split(', ')[1] || '';
                greetingText.textContent = `${greetingMessage}, ${username}`;

                // Add seasonal class
                if (currentMonth >= 10 || currentMonth <= 1) {
                    greetingSection.classList.add('snow');
                } else if (currentMonth >= 6 && currentMonth <= 8) {
                    greetingSection.classList.add('rain');
                }

                // Update date and time
                function updateDateTime() {
                    const now = new Date();
                    const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    const timeOptions = { hour: '2-digit', minute: '2-digit' };
                    
                    currentDateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
                    currentTimeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
                }

                updateDateTime();
                setInterval(updateDateTime, 60000);
            }

            function updateSalesOverview(fromDate, endDate) {
                const formData = new FormData();
                formData.append('from_date', fromDate);
                formData.append('end_date', endDate);
                formData.append('action', 'update_sales');

                fetch('ajax_handlers/update_sales.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update sales data in the UI
                        updateSalesUI(data);
                        
                        // Update URL without page refresh
                        const url = new URL(window.location);
                        url.searchParams.set('from_date', fromDate);
                        url.searchParams.set('end_date', endDate);
                        window.history.pushState({}, '', url);
                    } else {
                        console.error('Error updating sales data:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }

            function updateSalesUI(data) {
                // Update total sales
                document.querySelector('.total-sales-box .amount').textContent = 
                    `₹${Number(data.total_sales).toFixed(1)}L`;
                document.querySelector('.total-sales-box .total-projects').textContent = 
                    `Total Projects: ${data.project_counts.total}`;

                // Update category cards
                updateCategoryCard('architecture', data);
                updateCategoryCard('interior', data);
                updateCategoryCard('construction', data);
            }

            function updateCategoryCard(category, data) {
                const card = document.querySelector(`.${category}-card`);
                if (card) {
                    card.querySelector('.amount').textContent = 
                        `₹${Number(data[category]).toFixed(1)}L`;
                    card.querySelector('.project-count').textContent = 
                        `Projects: ${data.project_counts[category]}`;
                    card.querySelector('.progress-fill').style.width = 
                        `${(data[category] / Math.max(1, data.total_sales)) * 100}%`;
                }
            }

            function savePageState() {
                const sidebar = document.querySelector('.sidebar');
                localStorage.setItem('sidebarState', sidebar.classList.contains('close') ? 'closed' : 'open');
                localStorage.setItem('scrollPosition', window.scrollY);
                
                // Save project filter dates
                const projectFromDate = document.getElementById('project_from_date')?.value;
                const projectEndDate = document.getElementById('project_end_date')?.value;
                if (projectFromDate && projectEndDate) {
                    localStorage.setItem('projectFromDate', projectFromDate);
                    localStorage.setItem('projectEndDate', projectEndDate);
                }
            }

            function restorePageState() {
                const savedSidebarState = localStorage.getItem('sidebarState');
                const sidebar = document.querySelector('.sidebar');
                if (savedSidebarState === 'closed') {
                    sidebar.classList.add('close');
                } else {
                    sidebar.classList.remove('close');
                }
                
                const scrollPosition = localStorage.getItem('scrollPosition');
                if (scrollPosition) {
                    window.scrollTo(0, parseInt(scrollPosition));
                }
                
                // Restore project filter dates
                const projectFromDate = localStorage.getItem('projectFromDate');
                const projectEndDate = localStorage.getItem('projectEndDate');
                if (projectFromDate && projectEndDate) {
                    document.getElementById('project_from_date').value = projectFromDate;
                    document.getElementById('project_end_date').value = projectEndDate;
                    updateProjectOverview(projectFromDate, projectEndDate);
                }
            }

            function updateProjectOverview(fromDate, endDate) {
                const formData = new FormData();
                formData.append('from_date', fromDate);
                formData.append('end_date', endDate);

                fetch('ajax_handlers/update_projects.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProjectsUI(data.data);
                        
                        // Update URL without page refresh
                        const url = new URL(window.location);
                        url.searchParams.set('project_from_date', fromDate);
                        url.searchParams.set('project_end_date', endDate);
                        window.history.pushState({}, '', url);
                    } else {
                        console.error('Error updating project data:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }

            function updateProjectsUI(data) {
                // Update total projects
                document.querySelector('.total-projects-box .amount').textContent = data.total_projects;

                // Update project type cards
                updateProjectTypeCard('architecture', data.architecture_total, data.total_projects);
                updateProjectTypeCard('interior', data.interior_total, data.total_projects);
                updateProjectTypeCard('construction', data.construction_total, data.total_projects);
            }

            function updateProjectTypeCard(type, count, total) {
                const card = document.querySelector(`.${type}-card`);
                if (card) {
                    card.querySelector('.amount').textContent = count;
                    card.querySelector('.project-count').textContent = 
                        `out of ${total} Total Projects`;
                    
                    // Update progress bar
                    const percentage = (count / Math.max(1, total)) * 100;
                    card.querySelector('.progress-fill').style.width = `${percentage}%`;
                }
            }

            function updateTaskOverview(fromDate, endDate) {
                const formData = new FormData();
                formData.append('from_date', fromDate);
                formData.append('end_date', endDate);
                formData.append('action', 'update_tasks');

                fetch('ajax_handlers/update_tasks.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update task overview data in the UI
                        updateTaskOverviewUI(data);
                        
                        // Update URL without page refresh
                        const url = new URL(window.location);
                        url.searchParams.set('task_from_date', fromDate);
                        url.searchParams.set('task_end_date', endDate);
                        window.history.pushState({}, '', url);
                    } else {
                        console.error('Error updating task overview data:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }

            function updateTaskOverviewUI(data) {
                // Update task overview data in the UI
                document.getElementById('total-tasks').textContent = data.total_tasks;
                document.getElementById('completed-tasks').textContent = data.completed_tasks;
                document.getElementById('pending-tasks').textContent = data.pending_tasks;
                document.getElementById('overdue-tasks').textContent = data.overdue_tasks;

                // Update progress bars
                document.getElementById('completed-progress').style.width = `${(data.completed_tasks / data.total_tasks) * 100}%`;
                document.getElementById('pending-progress').style.width = `${(data.pending_tasks / data.total_tasks) * 100}%`;
                document.getElementById('overdue-progress').style.width = `${(data.overdue_tasks / data.total_tasks) * 100}%`;
            }

            function initializeCalendar() {
                const calendarGrid = document.querySelector('.calendar-grid');
                const currentMonthElement = document.getElementById('currentMonth');
                const prevButton = document.getElementById('prevMonth');
                const nextButton = document.getElementById('nextMonth');
                
                let currentDate = new Date();
                
                function renderCalendar(date) {
                    // Clear existing calendar days (keeping weekday headers)
                    const days = calendarGrid.querySelectorAll('.calendar-day');
                    days.forEach(day => day.remove());
                    
                    // Update month display
                    currentMonthElement.textContent = date.toLocaleString('default', { 
                        month: 'long', 
                        year: 'numeric' 
                    });
                    
                    // Get first day of month and last day
                    const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
                    const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
                    
                    // Add previous month's days
                    for (let i = 0; i < firstDay.getDay(); i++) {
                        const prevMonthDay = new Date(date.getFullYear(), date.getMonth(), 0 - i);
                        addDayToCalendar(prevMonthDay, true);
                    }
                    
                    // Add current month's days
                    for (let i = 1; i <= lastDay.getDate(); i++) {
                        const currentDay = new Date(date.getFullYear(), date.getMonth(), i);
                        addDayToCalendar(currentDay, false);
                    }
                    
                    // Add next month's days to complete the grid
                    const remainingDays = 42 - (firstDay.getDay() + lastDay.getDate());
                    for (let i = 1; i <= remainingDays; i++) {
                        const nextMonthDay = new Date(date.getFullYear(), date.getMonth() + 1, i);
                        addDayToCalendar(nextMonthDay, true);
                    }
                }
                
                function addDayToCalendar(date, isOtherMonth) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day';
                    if (isOtherMonth) dayElement.classList.add('other-month');
                    
                    // Check if it's today
                    const today = new Date();
                    if (date.toDateString() === today.toDateString()) {
                        dayElement.classList.add('today');
                    }
                    
                    dayElement.textContent = date.getDate();
                    
                    // Add task tooltip
                    const tooltip = createTooltip(date);
                    dayElement.appendChild(tooltip);
                    
                    calendarGrid.appendChild(dayElement);
                }
                
                function createTooltip(date) {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'day-task-tooltip';
                    
                    const formattedDate = date.toLocaleDateString('default', { 
                        weekday: 'short', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                    
                    // Get task data for this date (replace with your actual data)
                    const taskCount = getTaskCount(date); // Implement this function
                    const deadlineCount = getDeadlineCount(date); // Implement this function
                    
                    tooltip.innerHTML = `
                        <div class="tooltip-date">${formattedDate}</div>
                        <div class="tooltip-stat">
                            <i class="fas fa-tasks"></i>
                            Total Tasks: ${taskCount}
                        </div>
                        <div class="tooltip-stat">
                            <i class="fas fa-flag-checkered"></i>
                            Stage Deadlines: ${deadlineCount}
                        </div>
                    `;
                    
                    return tooltip;
                }
                
                // Navigation event listeners
                prevButton.addEventListener('click', () => {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                    renderCalendar(currentDate);
                });
                
                nextButton.addEventListener('click', () => {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                    renderCalendar(currentDate);
                });
                
                // Initial render
                renderCalendar(currentDate);
            }

            // Helper functions to get task data (implement these based on your data structure)
            function getTaskCount(date) {
                // Replace with actual data fetch
                return Math.floor(Math.random() * 5);
            }

            function getDeadlineCount(date) {
                // Replace with actual data fetch
                return Math.floor(Math.random() * 3);
            }

            function updateSiteStats(data) {
                // This function will update the site statistics when connected to the database
                // Example implementation:
                /*
                document.querySelector('.active-site-box .site-stat-numbers').textContent = data.activeSites;
                document.querySelector('.manager-box .site-stat-numbers').textContent = data.managersPresent;
                document.querySelector('.engineer-box .site-stat-numbers').textContent = data.engineersPresent;
                document.querySelector('.supervisor-box .site-stat-numbers').textContent = data.supervisorsPresent;
                document.querySelector('.labour-box .site-stat-numbers').textContent = data.labourPresent;
                */
            }

          
// Add announcement related functions
function showAnnouncementModal() {
    document.getElementById('announcementModal').style.display = 'block';
}

function closeAnnouncementModal() {
    document.getElementById('announcementModal').style.display = 'none';
}

document.getElementById('announcementForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('add_announcement.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Announcement added successfully!');
            closeAnnouncementModal();
            // Refresh announcements list
            loadAnnouncements();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error adding announcement');
        console.error('Error:', error);
    }
});

// Function to load announcements
async function loadAnnouncements() {
    try {
        const response = await fetch('get_announcements.php');
        const announcements = await response.json();
        
        const container = document.querySelector('.announcement-box .box-content');
        
        if (announcements.length === 0) {
            container.innerHTML = '<div class="no-content">No recent announcements</div>';
            return;
        }
        
        container.innerHTML = announcements.map(announcement => `
            <div class="announcement-item">
                <div class="announcement-header">
                    <h5>${announcement.title}</h5>
                    <span class="priority-badge ${announcement.priority}">${announcement.priority}</span>
                </div>
                <p>${announcement.content}</p>
                <div class="announcement-footer">
                    <span class="date">${announcement.created_at}</span>
                </div>
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error loading announcements:', error);
    }
}

// Load announcements when page loads
document.addEventListener('DOMContentLoaded', loadAnnouncements);

        </script>
    </div>
    
    <!-- Include mandatory password change modal -->
    <?php include 'include_password_change.php'; ?>
</body>
</html>
    