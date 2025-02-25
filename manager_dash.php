<?php

// Start session and include database connection
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db_connect.php';

// Fetch manager data including profile picture
$role = "Senior Manager (Studio)";
$manager_id = $_SESSION['user_id'];

$query = "SELECT username, profile_picture FROM users 
          WHERE id = ? AND role = ? 
          AND deleted_at IS NULL 
          AND status = 'active'";

try {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $manager_id, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $manager_data = $result->fetch_assoc();

    if (!$manager_data) {
        header("Location: login.php");
        exit();
    }

    // Set default profile picture if none exists
    if (empty($manager_data['profile_picture'])) {
        $manager_data['profile_picture'] = 'assets/images/default-avatar.png';
    }
} catch (Exception $e) {
    error_log("Error fetching manager data: " . $e->getMessage());
    $error_message = "An error occurred while fetching data.";
}

// Fetch today's attendance data
$today = date('Y-m-d');
$attendance_query = "SELECT a.*, u.username, u.profile_picture 
                    FROM attendance a 
                    JOIN users u ON a.user_id = u.id 
                    WHERE DATE(a.date) = ? 
                    AND a.punch_in IS NOT NULL 
                    AND a.punch_out IS NULL";

try {
    $stmt = $conn->prepare($attendance_query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    $present_users = $attendance_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching attendance data: " . $e->getMessage());
    $present_users = [];
}

// Fetch pending leave requests with simplified conditions
$pending_leaves_query = "SELECT lr.*, u.username, u.profile_picture 
                        FROM leave_request lr
                        JOIN users u ON lr.user_id = u.id 
                        WHERE lr.status = 'pending' 
                        ORDER BY lr.created_at DESC";

try {
    $pending_leaves_result = $conn->query($pending_leaves_query);
    $pending_leaves = $pending_leaves_result->fetch_all(MYSQLI_ASSOC);
    
    // Add debug logging
    error_log("Pending leaves query result: " . print_r($pending_leaves, true));
} catch (Exception $e) {
    error_log("Error fetching pending leaves: " . $e->getMessage());
    $pending_leaves = [];
}

// Debug output
echo "<!-- Debug: Number of pending leaves: " . count($pending_leaves) . " -->";
echo "<!-- Debug: Pending leaves data: " . print_r($pending_leaves, true) . " -->";

// Fetch users who are on leave today
$on_leave_query = "SELECT lr.*, u.username, u.profile_picture 
                   FROM leave_request lr
                   JOIN users u ON lr.user_id = u.id 
                   WHERE lr.status = 'approved' 
                   AND ? BETWEEN lr.start_date AND lr.end_date
                   AND lr.manager_approval = 1";

try {
    $stmt = $conn->prepare($on_leave_query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $on_leave_result = $stmt->get_result();
    $on_leave_users = $on_leave_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching on-leave users: " . $e->getMessage());
    $on_leave_users = [];
}

// Update the total count of employees on leave
$on_leave_count = count($on_leave_users);

// Fetch all active users for task assignment
$users_query = "SELECT id, username FROM users 
                WHERE deleted_at IS NULL 
                AND status = 'active' 
                ORDER BY username ASC";
try {
    $users_result = $conn->query($users_query);
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}

// Add this query to get total users count
$employee_stats_query = "SELECT COUNT(*) as total_users 
                        FROM users 
                        WHERE deleted_at IS NULL 
                        AND status = 'active'";
try {
    $stats_result = $conn->query($employee_stats_query);
    $employee_stats = $stats_result->fetch_assoc();
} catch (Exception $e) {
    error_log("Error fetching employee stats: " . $e->getMessage());
    $employee_stats = ['total_users' => 0];
}

// Fetch today's short leaves
$short_leaves_query = "SELECT lr.*, u.username, u.profile_picture, lt.name as leave_type_name 
                      FROM leave_request lr
                      JOIN users u ON lr.user_id = u.id 
                      JOIN leave_types lt ON lr.leave_type = lt.id
                      WHERE lr.status = 'approved' 
                      AND lr.start_date = CURRENT_DATE
                      AND lt.name = 'Short Leave'
                      AND lr.manager_approval = 1";

try {
    $stmt = $conn->prepare($short_leaves_query);
    $stmt->execute();
    $short_leaves_result = $stmt->get_result();
    $short_leaves = $short_leaves_result->fetch_all(MYSQLI_ASSOC);
    $short_leaves_count = count($short_leaves);
} catch (Exception $e) {
    error_log("Error fetching short leaves: " . $e->getMessage());
    $short_leaves = [];
    $short_leaves_count = 0;
}

function fetchCategories($conn, $parent_id = null) {
    $sql = "SELECT id, name, description 
            FROM project_categories 
            WHERE parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . "
            AND deleted_at IS NULL 
            ORDER BY name ASC";
            
    try {
        $stmt = $conn->prepare($sql);
        if ($parent_id !== null) {
            $stmt->bind_param('i', $parent_id);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        return [];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard</title>
    <link rel="stylesheet" href="dashboard/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <meta name="user-id" content="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
    <script>
        window.USER_ID = <?php echo json_encode($_SESSION['user_id']); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo-container">
                <img src="Hive Tag line 11 (1).png" alt="Company Logo">
            </div>
            <button class="toggle-btn">
                <i class="fas fa-chevron-left"></i>
            </button>
            <ul class="nav-links">
                <li><a href="manager_dash.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="#"><i class="fas fa-users"></i> <span>Team</span></a></li>
                <li><a href="#"><i class="fas fa-tasks"></i> <span>Create Project</span></a></li>
                <li><a href="leave.php"><i class="fas fa-calendar-plus"></i> <span>Apply Leave</span></a></li>
                <li><a href="#"><i class="fas fa-calendar-check"></i> <span>Leave Management</span></a></li>
                <li><a href="project_activity.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
                <li><a href="#"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <!-- Profile Dropdown (Moved outside greeting section) -->
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <img src="<?php echo htmlspecialchars($manager_data['profile_picture']); ?>" 
                             alt="Profile Avatar"
                             onerror="this.src='assets/images/default-avatar.png'">
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($manager_data['username']); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($role); ?></span>
                        </div>
                    </div>
                    <ul class="dropdown-menu">
                        <li><a href="profile.php"><i class="fas fa-user"></i>My Profile</a></li>
                        <li><a href="account-settings.php"><i class="fas fa-cog"></i>Account Settings</a></li>
                        <li><a href="change-password.php"><i class="fas fa-key"></i>Change Password</a></li>
                        <li><a href="#" id="darkModeToggle"><i class="fas fa-moon"></i>Dark Mode</a></li>
                        <div class="dropdown-divider"></div>
                        <li><a href="help.php"><i class="fas fa-question-circle"></i>Help & Support</a></li>
                        <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
                    </ul>
                </div>

                <div class="greeting-section">
                    <div class="greeting-content">
                        <div class="user-info">
                            <div class="avatar-container">
                                <img src="<?php echo htmlspecialchars($manager_data['profile_picture']); ?>" 
                                     alt="Profile Avatar"
                                     class="avatar-img"
                                     id="profileAvatar"
                                     onerror="this.src='assets/images/default-avatar.png'">
                            </div>
                            <div class="greeting-text">
                                <h1>Welcome, <span id="user-name"><?php echo htmlspecialchars($manager_data['username']); ?></span>!</h1>
                                <p id="greeting-time"></p>
                            </div>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <div class="notification-icon">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">0</span>
                        </div>
                        <button id="punchButton" class="punch-button">
                            <i class="fas fa-fingerprint"></i>
                            <span>Punch In</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Employee Overview Section -->
            <div class="employee-overview">
                <div class="section-header">
                    <i class="fas fa-users"></i>
                    <h2>Employees Overview</h2>
                </div>
                <div class="overview-cards">
                    <div class="overview-card present" data-tooltip-id="presentTooltip">
                        <div class="card-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="card-content">
                            <h3>Present Today</h3>
                            <div class="stat">
                                <span class="number"><?php echo count($present_users); ?></span>
                                <span class="divider">/</span>
                                <span class="total"><?php echo $employee_stats['total_users']; ?></span>
                            </div>
                            <p>Total Employees</p>
                        </div>
                        <!-- Add hidden tooltip content -->
                        <div id="presentTooltip" class="tooltip-content">
                            <div class="tooltip-header">
                                <h4>Present Employees</h4>
                                <span class="tooltip-time">As of <?php echo date('h:i A'); ?></span>
                            </div>
                            <div class="tooltip-body">
                                <?php foreach($present_users as $user): ?>
                                <div class="tooltip-user">
                                    <img src="<?php echo htmlspecialchars($user['profile_picture'] ?: 'assets/images/default-avatar.png'); ?>" 
                                         alt="User Avatar" 
                                         class="tooltip-avatar">
                                    <div class="tooltip-user-info">
                                        <span class="tooltip-username"><?php echo htmlspecialchars($user['username']); ?></span>
                                        <span class="tooltip-punch-time">In: <?php echo date('h:i A', strtotime($user['punch_in'])); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="overview-card pending" data-tooltip-id="pendingLeavesTooltip">
                        <div class="card-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="card-content">
                            <h3>Pending Leaves</h3>
                            <div class="stat">
                                <span class="number"><?php echo count($pending_leaves); ?></span>
                                <span class="total">Awaiting Approval</span>
                            </div>
                        </div>
                        
                        <!-- Pending Leaves Tooltip -->
                        <div id="pendingLeavesTooltip" class="tooltip-content">
                            <div class="tooltip-header">
                                <h4>Pending Leave Requests</h4>
                                <span class="tooltip-time">As of <?php echo date('h:i A'); ?></span>
                            </div>
                            <div class="tooltip-body">
                                <?php if (empty($pending_leaves)): ?>
                                    <p class="no-leaves">No pending leave requests</p>
                                <?php else: ?>
                                    <?php foreach($pending_leaves as $leave): ?>
                                        <div class="leave-request-item">
                                            <div class="leave-user-info">
                                                <img src="<?php echo htmlspecialchars($leave['profile_picture'] ?: 'assets/images/default-avatar.png'); ?>" 
                                                     alt="User Avatar" 
                                                     class="tooltip-avatar">
                                                <div class="leave-details">
                                                    <span class="tooltip-username"><?php echo htmlspecialchars($leave['username']); ?></span>
                                                    <div class="leave-days">
                                                        <span class="days-count"><?php echo $leave['duration']; ?></span>
                                                    </div>
                                                    <div class="leave-date">
                                                        <i class="far fa-calendar-alt"></i>
                                                        <span><?php 
                                                            echo date('d M', strtotime($leave['start_date']));
                                                            if ($leave['start_date'] !== $leave['end_date']) {
                                                                echo ' - ' . date('d M', strtotime($leave['end_date']));
                                                            }
                                                            echo ' (' . $leave['duration'] . ' days)';
                                                        ?></span>
                                                    </div>
                                                    <p class="leave-reason"><?php echo htmlspecialchars($leave['reason']); ?></p>
                                                    <div class="leave-actions">
                                                        <div class="action-buttons">
                                                            <button class="approve-btn" onclick="handleLeaveAction(<?php echo $leave['id']; ?>, 'approve', this)">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                            <button class="reject-btn" onclick="handleLeaveAction(<?php echo $leave['id']; ?>, 'reject', this)">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar Card -->
                    <div class="overview-card calendar">
                        <div class="card-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="card-content">
                            <h3>Calendar</h3>
                            <!-- Calendar container -->
                            <div id="mini-calendar">
                                <!-- Calendar will be populated via JavaScript -->
                            </div>
                        </div>
                    </div>

                    <div class="overview-card short" data-tooltip-id="shortLeavesTooltip">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <h3>Short Leave</h3>
                            <div class="stat">
                                <span class="number"><?php echo $short_leaves_count; ?></span>
                                <span class="divider">/</span>
                                <span class="total"><?php echo $employee_stats['total_users']; ?></span>
                            </div>
                            <p>Today's Short Leaves</p>
                        </div>

                        <!-- Short Leaves Tooltip -->
                        <div id="shortLeavesTooltip" class="tooltip-content">
                            <div class="tooltip-header">
                                <h4>Today's Short Leaves</h4>
                                <span class="tooltip-time">As of <?php echo date('h:i A'); ?></span>
                            </div>
                            <div class="tooltip-body">
                                <?php if (empty($short_leaves)): ?>
                                    <p class="no-leaves">No short leaves today</p>
                                <?php else: ?>
                                    <?php foreach($short_leaves as $leave): ?>
                                        <div class="tooltip-user">
                                            <img src="<?php echo htmlspecialchars($leave['profile_picture'] ?: 'assets/images/default-avatar.png'); ?>" 
                                                 alt="User Avatar" 
                                                 class="tooltip-avatar">
                                            <div class="tooltip-user-info">
                                                <span class="tooltip-username"><?php echo htmlspecialchars($leave['username']); ?></span>
                                                <div class="leave-date">
                                                    <i class="far fa-clock"></i>
                                                    <span><?php echo date('h:i A', strtotime($leave['time_from'])); ?> - <?php echo date('h:i A', strtotime($leave['time_to'])); ?></span>
                                                </div>
                                                <p class="leave-reason"><?php echo htmlspecialchars($leave['reason']); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="overview-card leave" data-tooltip-id="onLeaveTooltip">
                        <div class="card-icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <div class="card-content">
                            <h3>On Leave</h3>
                            <div class="stat">
                                <span class="number"><?php echo $on_leave_count; ?></span>
                                <span class="divider">/</span>
                                <span class="total"><?php echo $employee_stats['total_users']; ?></span>

                            </div>
                            <p>Full Day Leave</p>
                        </div>
                        
                        <!-- On Leave Tooltip -->
                        <div id="onLeaveTooltip" class="tooltip-content">
                            <div class="tooltip-header">
                                <h4>Employees On Leave</h4>
                                <span class="tooltip-time">As of <?php echo date('h:i A'); ?></span>
                            </div>
                            <div class="tooltip-body">
                                <?php if (empty($on_leave_users)): ?>
                                    <p class="no-leaves">No employees on leave today</p>
                                <?php else: ?>
                                    <?php foreach($on_leave_users as $user): ?>
                                        <div class="tooltip-user">
                                            <img src="<?php echo htmlspecialchars($user['profile_picture'] ?: 'assets/images/default-avatar.png'); ?>" 
                                                 alt="User Avatar" 
                                                 class="tooltip-avatar">
                                            <div class="tooltip-user-info">
                                                <span class="tooltip-username"><?php echo htmlspecialchars($user['username']); ?></span>
                                                <div class="leave-date">
                                                    <i class="far fa-calendar-alt"></i>
                                                    <span><?php 
                                                        echo date('d M', strtotime($user['start_date']));
                                                        if ($user['start_date'] !== $user['end_date']) {
                                                            echo ' - ' . date('d M', strtotime($user['end_date']));
                                                        }
                                                    ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- After Employee Overview section -->
            <div class="task-overview-container">
                <div class="task-overview-header">
                    <div class="task-header-left">
                        <h2>Task Overview</h2>
                        <button class="add-task-btn">
                            <i class="fas fa-plus"></i>
                            <span>Add Task</span>
                        </button>
                    </div>
                    <div class="task-view-controls">
                        <div class="view-toggle-container">
                            <div class="view-toggle">
                                <input type="radio" id="overviewView" name="view" value="overview" checked>
                                <label for="overviewView">Overview</label>
                                <input type="radio" id="calendarView" name="view" value="calendar">
                                <label for="calendarView">Calendar</label>
                                <span class="slider"></span>
                            </div>
                        </div>
                        <div class="task-date-range">
                            <div class="date-input-group">
                                <label>From</label>
                                <div class="date-input">
                                    <i class="far fa-calendar"></i>
                                    <input type="date" id="taskDateFrom" class="task-date-picker">
                                </div>
                            </div>
                            <div class="date-input-group">
                                <label>To</label>
                                <div class="date-input">
                                    <i class="far fa-calendar"></i>
                                    <input type="date" id="taskDateTo" class="task-date-picker">
                                </div>
                            </div>
                            <button class="task-filter-btn">
                                <i class="fas fa-filter"></i>
                                Filter
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="task-overview-cards">
                    <!-- Total Projects Card -->
                    <div class="task-card total">
                        <div class="task-card-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="task-card-content">
                            <h3>Total Projects</h3>
                            <div class="task-stat">
                                <?php
                                $sql = "SELECT COUNT(*) as total FROM projects WHERE deleted_at IS NULL";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_assoc($result);
                                ?>
                                <span class="task-number"><?php echo $row['total']; ?></span>
                                <span class="task-total">Projects</span>
                            </div>
                            <p>Active Projects</p>
                        </div>
                    </div>

                    <!-- Completed Stages & Substages Card -->
                    <div class="task-card completed">
                        <div class="task-card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="task-card-content">
                            <h3>Completed</h3>
                            <div class="task-stat">
                                <?php
                                $sql = "SELECT 
                                    (SELECT COUNT(*) FROM project_stages WHERE status = 'completed') +
                                    (SELECT COUNT(*) FROM project_substages WHERE status = 'completed') as completed_total,
                                    (SELECT COUNT(*) FROM project_stages) +
                                    (SELECT COUNT(*) FROM project_substages) as total";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_assoc($result);
                                ?>
                                <span class="task-number"><?php echo $row['completed_total']; ?></span>
                                <span class="task-total">/ <?php echo $row['total']; ?></span>
                            </div>
                            <p>Completed Stages & Substages</p>
                        </div>
                    </div>

                    <!-- In Progress Card -->
                    <div class="task-card pending">
                        <div class="task-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="task-card-content">
                            <h3>In Progress</h3>
                            <div class="task-stat">
                                <?php
                                $sql = "SELECT 
                                    (SELECT COUNT(*) FROM project_stages WHERE status = 'in_progress') +
                                    (SELECT COUNT(*) FROM project_substages WHERE status = 'in_progress') as progress_total,
                                    (SELECT COUNT(*) FROM project_stages) +
                                    (SELECT COUNT(*) FROM project_substages) as total";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_assoc($result);
                                ?>
                                <span class="task-number"><?php echo $row['progress_total']; ?></span>
                                <span class="task-total">/ <?php echo $row['total']; ?></span>
                            </div>
                            <p>Ongoing Stages & Substages</p>
                        </div>
                    </div>

                    <!-- Overdue Card -->
                    <div class="task-card overdue">
                        <div class="task-card-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="task-card-content">
                            <h3>Overdue</h3>
                            <div class="task-stat">
                                <?php
                                $sql = "SELECT 
                                    (SELECT COUNT(*) FROM project_stages WHERE end_date < CURDATE() AND status != 'completed') +
                                    (SELECT COUNT(*) FROM project_substages WHERE end_date < CURDATE() AND status != 'completed') as overdue_total";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_assoc($result);
                                ?>
                                <span class="task-number"><?php echo $row['overdue_total']; ?></span>
                                <span class="task-total">Tasks</span>
                            </div>
                            <p>Delayed Stages & Substages</p>
                        </div>
                    </div>

                    <!-- Pending Stages Card -->
                    <div class="task-card pending-stages">
                        <div class="task-card-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="task-card-content">
                            <h3>Pending Stages</h3>
                            <div class="task-stat">
                                <?php
                                $sql = "SELECT 
                                    COUNT(*) as pending_stages,
                                    (SELECT COUNT(*) FROM project_stages) as total_stages
                                    FROM project_stages 
                                    WHERE status = 'pending'";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_assoc($result);
                                ?>
                                <span class="task-number"><?php echo $row['pending_stages']; ?></span>
                                <span class="task-total">/ <?php echo $row['total_stages']; ?></span>
                            </div>
                            <p>Stages Awaiting</p>
                        </div>
                    </div>

                    <!-- Pending Substages Card -->
                    <div class="task-card pending-substages">
                        <div class="task-card-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="task-card-content">
                            <h3>Pending Substages</h3>
                            <div class="task-stat">
                                <?php
                                $sql = "SELECT 
                                    COUNT(*) as pending_substages,
                                    (SELECT COUNT(*) FROM project_substages) as total_substages
                                    FROM project_substages 
                                    WHERE status = 'pending'";
                                $result = mysqli_query($conn, $sql);
                                $row = mysqli_fetch_assoc($result);
                                ?>
                                <span class="task-number"><?php echo $row['pending_substages']; ?></span>
                                <span class="task-total">/ <?php echo $row['total_substages']; ?></span>
                            </div>
                            <p>Substages Awaiting</p>
                        </div>
                    </div>
                </div>

                <!-- Add this after the task-overview-cards div -->
                <div class="task-calendar-view">
                    <div class="task-calendar-header">
                        <button class="task-calendar-nav prev"><i class="fas fa-chevron-left"></i></button>
                        <h2 class="task-calendar-title"></h2>
                        <button class="task-calendar-nav next"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="task-calendar-weekdays">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>
                    <div id="taskCalendarDates" class="task-calendar-dates">
                        <div class="task-calendar-date expanded">
                            <div class="tasks-container">
                                <div class="calendar-task" data-task-id="123">
                                    <!-- Task content -->
                                </div>
                            </div>
                            <!-- or -->
                            <div class="task-preview-container">
                                <div class="calendar-task-preview" data-task-id="123">
                                    <!-- Task preview content -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <br>
                <div class="calendar-legend">
        <div class="legend-item">
            <div class="legend-color architecture"></div>
            <span>Architecture</span>
        </div>
        <div class="legend-item">
            <div class="legend-color interior"></div>
            <span>Interior</span>
        </div>
        <div class="legend-item">
            <div class="legend-color construction"></div>
            <span>Construction</span>
        </div>
    </div>
            </div>
        </main>
    </div>

  

    <!-- Add this dialog HTML at the end of the body tag -->
    <div id="leaveActionDialog" class="dialog-overlay">
        <div class="dialog-content">
            <div class="dialog-header">
                <h3>Leave Action Confirmation</h3>
                <button class="close-dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="dialog-body">
                <div class="leave-info">
                    <p>Employee: <span id="dialogEmployeeName"></span></p>
                    <p>Duration: <span id="dialogLeaveDuration"></span></p>
                </div>
                <div class="form-group">
                    <label for="actionReason">Reason for <span id="actionType"></span>:</label>
                    <textarea id="actionReason" rows="4" placeholder="Enter your reason here..."></textarea>
                </div>
            </div>
            <div class="dialog-footer">
                <button class="cancel-btn">Cancel</button>
                <button id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Add this dialog HTML at the end of the body tag, before the closing </body> tag -->
    <div id="addTaskDialog" class="task-dialog-overlay">
        <div class="task-dialog-content">
            <div class="task-dialog-header">
                <h3>Create New Project</h3>
                <button class="task-close-dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="task-dialog-body">
                <div class="task-form-group">
                    <label for="taskTitle">Project Name</label>
                    <input type="text" id="taskTitle" placeholder="Enter project name">
                </div>
                <div class="task-form-group">
                    <label for="taskDescription">Project Description</label>
                    <textarea id="taskDescription" rows="4" placeholder="Enter project description"></textarea>
                </div>
                <div class="task-form-group">
                    <label for="projectType">Project Type</label>
                    <select id="projectType" required>
                        <option value="">Select Project Type</option>
                        <option value="architecture">Architecture</option>
                        <option value="interior">Interior</option>
                        <option value="construction">Construction</option>
                    </select>
                </div>
                <div class="task-form-group">
                    <label for="taskCategory">Category</label>
                    <select id="taskCategory" class="category-select" required>
                        <option value="">Select Category</option>
                        <?php
                        $main_categories = fetchCategories($conn);
                        foreach ($main_categories as $main_category) {
                            echo "<optgroup label='" . htmlspecialchars($main_category['name']) . "'>";
                            
                            // Fetch subcategories
                            $subcategories = fetchCategories($conn, $main_category['id']);
                            foreach ($subcategories as $subcategory) {
                                printf(
                                    '<option value="%d" data-parent="%d" title="%s">%s</option>',
                                    $subcategory['id'],
                                    $main_category['id'],
                                    htmlspecialchars($subcategory['description']),
                                    htmlspecialchars($subcategory['name'])
                                );
                            }
                            
                            echo "</optgroup>";
                        }
                        ?>
                    </select>
                </div>
                <div class="task-form-row">
                    <div class="task-form-group">
                        <label for="taskStartDate">Start Date & Time</label>
                        <div class="task-datetime-input">
                            <input type="datetime-local" id="taskStartDate">
                        </div>
                    </div>
                    <div class="task-form-group">
                        <label for="taskDueDate">Due By</label>
                        <div class="task-datetime-input">
                            <input type="datetime-local" id="taskDueDate">
                        </div>
                    </div>
                </div>
                <div class="task-form-group">
                    <label for="taskAssignee">Assign To</label>
                    <select id="taskAssignee">
                        <option value="">Select Employee</option>
                        <!-- PHP will populate this -->
                    </select>
                </div>

                <!-- Stages Section -->
                <div class="stages-container">
                    <div id="stagesWrapper">
                        <!-- Template for a stage -->
                        <div class="stage-block" style="display: none;">
                            <div class="stage-header">
                                <h4>Stage</h4>
                                <button type="button" class="remove-stage-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="stage-form-group">
                                <label>Description</label>
                                <textarea class="stage-description" rows="3"></textarea>
                            </div>
                            <div class="stage-form-row">
                                <div class="stage-form-group">
                                    <label>Assignee</label>
                                    <select class="stage-assignee">
                                        <option value="">Select Employee</option>
                                    </select>
                                </div>
                            </div>
                            <div class="stage-form-row">
                                <div class="stage-form-group">
                                    <label>Start Date</label>
                                    <input type="datetime-local" class="stage-start-date">
                                </div>
                                <div class="stage-form-group">
                                    <label>Due Date</label>
                                    <input type="datetime-local" class="stage-due-date">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button id="addStageBtn" class="add-stage-btn">
                        <i class="fas fa-plus"></i>
                        <span>Add Stage</span>
                    </button>
                </div>
            </div>
            <div class="task-dialog-footer">
                <button class="task-cancel-btn">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button id="saveTaskBtn" class="task-save-btn">
                    <span>Create Project</span>
                    <i class="fas fa-check"></i>
                </button>
            </div>
        </div>
    </div>

    

    <script src="dashboard/js/script.js"></script>

    <!-- Add before closing body tag -->
    <div id="projectDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="project-title"></h3>
                <button class="close-modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="project-info">
                    <div class="info-row">
                        <span class="label"><i class="fas fa-user"></i> Project Manager:</span>
                        <span class="project-manager"></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-calendar"></i> Timeline:</span>
                        <span class="project-timeline"></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-tag"></i> Type:</span>
                        <span class="project-type"></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-info-circle"></i> Status:</span>
                        <span class="project-status"></span>
                    </div>
                </div>
                <div class="project-description"></div>
                <div class="project-stages">
                    <h4>Project Stages</h4>
                    <div class="stages-list"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this before closing body tag -->
    <div id="fileUploadDialog" class="file-upload-dialog-overlay">
        <div class="file-upload-dialog-content">
            <div class="file-upload-dialog-header">
                <h3>Upload File</h3>
                <button class="task-close-dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="file-upload-dialog-body">
                <div class="task-form-group">
                    <label for="fileName">File Name</label>
                    <input type="text" id="fileName" placeholder="Enter file name">
                </div>
                <div class="task-form-group">
                    <label for="fileUpload">Choose File</label>
                    <input type="file" id="fileUpload" class="task-form-control">
                </div>
            </div>
            <div class="file-upload-dialog-footer">
                <button class="task-cancel-btn">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button id="uploadFileBtn" class="task-save-btn">
                    <span>Upload</span>
                    <i class="fas fa-upload"></i>
                </button>
            </div>
        </div>
    </div>


</body>
</html>
