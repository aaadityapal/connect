<?php
date_default_timezone_set('Asia/Kolkata');

session_start();
require_once 'config.php';
require_once 'functions.php';

// Add this function at the top of your file, after any required includes
function getRoleMetrics($role) {
    global $pdo;  // Assuming you have a PDO connection
    
    $metrics = [];
    $metrics['tasks'] = 0; // Set default value
    
    switch($role) {
        case 'HR':
            // Get HR specific metrics
            $stmt = $pdo->prepare("SELECT COUNT(*) as employee_count FROM users WHERE status = 'active'");
            $stmt->execute();
            $metrics['total_employees'] = $stmt->fetch()['employee_count'];
            
            // Get pending leaves count
            $stmt = $pdo->prepare("SELECT COUNT(*) as pending_leaves FROM leaves WHERE status = 'Pending'");
            $stmt->execute();
            $metrics['pending_leaves'] = $stmt->fetch()['pending_leaves'];
            break;
            
        case 'admin':
            // Get admin specific metrics
            $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users");
            $stmt->execute();
            $metrics['total_users'] = $stmt->fetch()['user_count'];
            break;
            
        case 'Senior Manager (Studio)':
            // Get studio manager metrics
            $stmt = $pdo->prepare("SELECT COUNT(*) as team_count FROM users WHERE role LIKE '%Design Team%' OR role LIKE '%Working Team%' OR role LIKE '%3D Designing Team%'");
            $stmt->execute();
            $metrics['team_members'] = $stmt->fetch()['team_count'];
            break;
            
        case 'Senior Manager (Site)':
            // Get site manager metrics
            $stmt = $pdo->prepare("SELECT COUNT(*) as site_count FROM users WHERE role IN ('Site Manager', 'Site Supervisor', 'Site Trainee')");
            $stmt->execute();
            $metrics['site_team'] = $stmt->fetch()['site_count'];
            break;
            
        // Add more cases based on your roles
            
        default:
            $metrics['default'] = 'No specific metrics available';
            break;
    }
    
    return $metrics;
}

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// Get user roles and details
$roles = explode(',', $_SESSION['role']);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Set default role if not already set
if (!isset($_SESSION['current_role'])) {
    $_SESSION['current_role'] = $roles[0];
}

// Validate current_role is in user's assigned roles
if (!in_array($_SESSION['current_role'], $roles)) {
    $_SESSION['current_role'] = $roles[0];
}

$currentRole = $_SESSION['current_role'];
$metrics = getRoleMetrics($currentRole);

// Add this after getting the metrics and before the HTML
$tasks_query = "SELECT t.*, c.name as category_name 
                FROM tasks t 
                JOIN task_categories c ON t.category_id = c.id 
                WHERE t.assigned_to = ? 
                ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($tasks_query);
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll();

// Add this after getting the metrics and before getting tasks
$can_assign_tasks = false;
// Define roles that can assign tasks
$task_assigning_roles = ['HR', 'admin', 'Senior Manager (Studio)', 'Senior Manager (Site)'];
if (in_array($currentRole, $task_assigning_roles)) {
    $can_assign_tasks = true;
}

// Get all users for task assignment dropdown
$users_query = "SELECT id, username, role FROM users WHERE status = 'active' AND id != ?";
$stmt = $pdo->prepare($users_query);
$stmt->execute([$_SESSION['user_id']]);
$available_users = $stmt->fetchAll();

// Get task categories
$categories_query = "SELECT * FROM task_categories";
$stmt = $pdo->prepare($categories_query);
$stmt->execute();
$task_categories = $stmt->fetchAll();

// Add this after your existing PDO queries, before the HTML
$leave_status_query = "SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $pdo->prepare($leave_status_query);
$stmt->execute([$_SESSION['user_id']]);
$recent_leaves = $stmt->fetchAll();

// First, get the user's shift information
$user_shift_query = "
    SELECT 
        s.id as shift_id,
        s.name as shift_name,
        s.start_time,
        s.end_time,
        us.effective_from,
        us.effective_to
    FROM user_shifts us
    JOIN shifts s ON us.shift_id = s.id
    WHERE us.user_id = ?
    AND CURRENT_DATE >= us.effective_from
    AND (us.effective_to IS NULL OR CURRENT_DATE <= us.effective_to)
    LIMIT 1";

try {
    $stmt = $pdo->prepare($user_shift_query);
    $stmt->execute([$_SESSION['user_id']]);
    $user_shift = $stmt->fetch();

    if ($user_shift) {
        // Now get late punch-ins based on the user's shift
        $late_punches_query = "
            SELECT 
                a.date,
                a.punch_in,
                ? as start_time,
                TIMESTAMPDIFF(MINUTE, 
                    TIMESTAMP(a.date, ?),
                    a.punch_in
                ) as minutes_late
            FROM attendance a
            WHERE a.user_id = ?
                AND a.punch_in > TIMESTAMP(a.date, ?)
                AND a.date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            ORDER BY a.date DESC
            LIMIT 5";

        $stmt = $pdo->prepare($late_punches_query);
        $stmt->execute([
            $user_shift['start_time'],
            $user_shift['start_time'],
            $_SESSION['user_id'],
            $user_shift['start_time']
        ]);
        $late_punches = $stmt->fetchAll();

        // Debug information
        if (empty($late_punches)) {
            error_log("No late punches found for user_id: " . $_SESSION['user_id'] . " with shift start time: " . $user_shift['start_time']);
        }
    } else {
        error_log("No active shift assignment found for user_id: " . $_SESSION['user_id']);
        $late_punches = [];
    }
} catch (PDOException $e) {
    error_log("Late punches query error: " . $e->getMessage());
    $late_punches = [];
}

// Get current leave balances
$current_month = date('n');
$current_year = date('Y');

// Function to ensure leave balance record exists for current month
function ensureLeaveBalance($pdo, $user_id, $month, $year) {
    // Check if balance exists for current month
    $check_query = "SELECT id FROM leave_balances 
                   WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = $pdo->prepare($check_query);
    $stmt->execute([$user_id, $month, $year]);
    
    if (!$stmt->fetch()) {
        // Get previous month's balance
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month == 0) {
            $prev_month = 12;
            $prev_year--;
        }
        
        // Get previous month's unused leaves
        $prev_balance_query = "
            SELECT 
                lb.casual_leaves - COALESCE(
                    (SELECT SUM(CASE 
                        WHEN days_count > 0 THEN days_count 
                        ELSE 1 
                    END)
                    FROM leaves 
                    WHERE user_id = ? 
                    AND leave_type = 'Casual'
                    AND MONTH(start_date) = ?
                    AND YEAR(start_date) = ?
                    AND status = 'Approved'), 0
                ) as carried_casual,
                lb.medical_leaves - COALESCE(
                    (SELECT SUM(CASE 
                        WHEN days_count > 0 THEN days_count 
                        ELSE 1 
                    END)
                    FROM leaves 
                    WHERE user_id = ? 
                    AND leave_type = 'Medical'
                    AND MONTH(start_date) = ?
                    AND YEAR(start_date) = ?
                    AND status = 'Approved'), 0
                ) as carried_medical
            FROM leave_balances lb
            WHERE lb.user_id = ? 
            AND lb.month = ? 
            AND lb.year = ?";
        
        $stmt = $pdo->prepare($prev_balance_query);
        $stmt->execute([
            $user_id, $prev_month, $prev_year,
            $user_id, $prev_month, $prev_year,
            $user_id, $prev_month, $prev_year
        ]);
        $prev_balance = $stmt->fetch();
        
        // Calculate new balances
        $carried_casual = ($prev_balance && $prev_balance['carried_casual'] > 0) ? $prev_balance['carried_casual'] : 0;
        $carried_medical = ($prev_balance && $prev_balance['carried_medical'] > 0) ? $prev_balance['carried_medical'] : 0;
        
        // Insert new balance record
        $insert_query = "INSERT INTO leave_balances 
                        (user_id, month, year, casual_leaves, medical_leaves)
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($insert_query);
        $stmt->execute([
            $user_id,
            $month,
            $year,
            $carried_casual + 2.0, // Monthly casual leave allowance + carried forward
            6.0  // Monthly medical leave allowance (no carry forward)
        ]);
    }
}

// Ensure current month's balance exists
ensureLeaveBalance($pdo, $_SESSION['user_id'], $current_month, $current_year);

// Get current leave balances and taken leaves
$leave_query = "
    SELECT 
        lb.*,
        COALESCE(
            (SELECT SUM(CASE 
                WHEN days_count > 0 THEN days_count 
                ELSE 1 
            END)
            FROM leaves 
            WHERE user_id = lb.user_id 
            AND leave_type = 'Casual'
            AND MONTH(start_date) = lb.month
            AND YEAR(start_date) = lb.year
            AND status = 'Approved'), 0
        ) as casual_taken,
        COALESCE(
            (SELECT SUM(CASE 
                WHEN days_count > 0 THEN days_count 
                ELSE 1 
            END)
            FROM leaves 
            WHERE user_id = lb.user_id 
            AND leave_type = 'Medical'
            AND MONTH(start_date) = lb.month
            AND YEAR(start_date) = lb.year
            AND status = 'Approved'), 0
        ) as medical_taken,
        COALESCE(
            (SELECT COUNT(*)
            FROM leaves 
            WHERE user_id = lb.user_id 
            AND leave_type = 'Short'
            AND MONTH(start_date) = lb.month
            AND YEAR(start_date) = lb.year
            AND status = 'Approved'), 0
        ) as short_taken
    FROM leave_balances lb
    WHERE lb.user_id = ? 
    AND lb.month = ? 
    AND lb.year = ?";

$stmt = $pdo->prepare($leave_query);
$stmt->execute([$_SESSION['user_id'], $current_month, $current_year]);
$leave_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate remaining leaves
$casual_remaining = $leave_data['casual_leaves'] - $leave_data['casual_taken'];
$medical_remaining = $leave_data['medical_leaves'] - $leave_data['medical_taken'];
$short_remaining = 2 - $leave_data['short_taken']; // 2 short leaves per month

// Update the HTML section
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo htmlspecialchars($user['username']); ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/css/datepicker.min.css">
    <script src="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/js/datepicker.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Add jQuery (required for some Bootstrap features) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Update Bootstrap JS to 5.3.2 -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top bg-white border-bottom">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <button id="sidebar-toggle" class="btn btn-link text-dark me-3">
                    <i class="fas fa-bars"></i>
                </button>
                <a class="navbar-brand" href="#">
                    <img src="Hive Tag line 11 (1).png" height="30" alt="Logo">
                </a>
            </div>
            
            <div class="d-flex align-items-center">
                <!-- Punch In/Out Button -->
                <div class="me-3">
                    <?php
                    // Get today's punch status
                    $today = date('Y-m-d');
                    $stmt = $pdo->prepare("SELECT punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
                    $stmt->execute([$_SESSION['user_id'], $today]);
                    $attendance = $stmt->fetch();
                    
                    if (!$attendance || !$attendance['punch_in']) {
                        // Not punched in yet
                        echo '<button id="punchButton" class="btn btn-success" onclick="handlePunch(\'in\')" data-action="in">
                                <i class="fas fa-sign-in-alt me-2"></i>Punch In
                              </button>';
                    } elseif (!$attendance['punch_out']) {
                        // Punched in but not out
                        echo '<button id="punchButton" class="btn btn-danger" onclick="handlePunch(\'out\')" data-action="out">
                                <i class="fas fa-sign-out-alt me-2"></i>Punch Out
                              </button>';
                    } else {
                        // Already punched out
                        echo '<button class="btn btn-secondary" disabled>
                                <i class="fas fa-check me-2"></i>Shift Complete
                              </button>';
                    }
                    ?>
                </div>

                <!-- Role Switcher -->
                <div class="dropdown me-3">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="roleDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-tag me-2"></i>
                        <?php echo htmlspecialchars($currentRole); ?>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="roleDropdown">
                        <?php foreach($roles as $role): ?>
                        <li><a class="dropdown-item" href="switch_role.php?role=<?php echo urlencode($role); ?>">
                            <?php echo htmlspecialchars($role); ?>
                        </a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- User Menu -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo $user['avatar_url'] ?? 'photo_2024-11-07_17-34-58.jpg'; ?>" 
                             class="rounded-circle me-2" width="30" height="30" alt="Avatar">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user me-2"></i>Profile
                        </a></li>
                        <li><a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Add this right after your header section -->
    <?php if (isset($_SESSION['leave_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-4" role="alert" style="z-index: 1050;">
            <i class="fas fa-check-circle me-2"></i>
            Leave application submitted successfully! Your request has been sent to <?php echo htmlspecialchars($_SESSION['manager_name']); ?> for approval.
            You will be notified once it's approved.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
        // Clear the session variables
        unset($_SESSION['leave_success']);
        unset($_SESSION['manager_name']);
        ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['leave_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show position-fixed top-0 end-0 m-4" role="alert" style="z-index: 1050;">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['leave_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['leave_error']); ?>
    <?php endif; ?>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar">
        <!-- Toggle Button -->
        <button id="sidebarToggle" class="sidebar-toggle">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div class="sidebar-content">
            <!-- Navigation Menu -->
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-link active" data-title="Dashboard">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="analytics.php" class="nav-link" data-title="Analytics">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
                
                <a href="projects.php" class="nav-link" data-title="Projects">
                    <i class="fas fa-project-diagram"></i>
                    <span>Projects</span>
                    <span class="badge">8</span>
                </a>
                
                <a href="tasks.php" class="nav-link" data-title="Tasks">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                    <span class="badge">12</span>
                </a>
                
                <a href="calendar.php" class="nav-link" data-title="Calendar">
                    <i class="fas fa-calendar"></i>
                    <span>Calendar</span>
                </a>
                
                <a href="messages.php" class="nav-link" data-title="Messages">
                    <i class="fas fa-comments"></i>
                    <span>Messages</span>
                    <span class="badge badge-danger">3</span>
                </a>
                
                <a href="apply_leaves.php" class="nav-link" data-title="Apply Leaves">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Apply Leaves</span>
                </a>

                    <!-- Add new Travelling Allowances option -->
    <a href="travelling_allowances.php" class="nav-link" data-title="Travelling Allowances">
        <i class="fas fa-route"></i>
        <span>Travelling Allowances</span>
    </a>
    
                <a href="settings.php" class="nav-link" data-title="Settings">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div id="main-content" class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section mb-4">
            <h1 class="h3">Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
            <p class="text-muted">Here's what's happening with your <?php echo htmlspecialchars($currentRole); ?> account today.</p>
        </div>

        <!-- Metrics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Total Tasks</h6>
                                <h2 class="card-title mb-0"><?php echo count($tasks); ?></h2>
                            </div>
                            <div class="icon-box bg-primary">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Add more metric cards here -->
        </div>

        <!-- Add a new section for Tasks List -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Assigned Tasks</h5>
                <?php if ($can_assign_tasks): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignTaskModal">
                    <i class="fas fa-plus me-2"></i>Assign New Task
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($tasks) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                                        <td><?php echo htmlspecialchars($task['category_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $task['priority'] === 'high' ? 'danger' : 
                                                    ($task['priority'] === 'medium' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst(htmlspecialchars($task['priority'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($task['due_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $task['status'] === 'completed' ? 'success' : 
                                                    ($task['status'] === 'in_progress' ? 'primary' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-task" data-task-id="<?php echo $task['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($task['status'] !== 'completed'): ?>
                                                <button class="btn btn-sm btn-success update-status" data-task-id="<?php echo $task['id']; ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">No tasks assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div id="activities">
                    <!-- Activities will be loaded here via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Add this right panel after your main content div -->
    <div id="rightPanel" class="right-panel">
        <!-- Calendar Section -->
        <div class="panel-section calendar-container">
            <h5 class="section-title">Attendance & Tasks Calendar</h5>
            <div id="calendar" class="mini-calendar"></div>
        </div>

        <!-- Attendance Log with adjusted spacing -->
        <div class="panel-section attendance-section">
            <h5 class="section-title">Attendance Log</h5>
            <div class="attendance-log">
                <?php
                // Fetch last 5 days attendance
                $stmt = $pdo->prepare("
                    SELECT date, punch_in, punch_out 
                    FROM attendance 
                    WHERE user_id = ? 
                    ORDER BY date DESC 
                    LIMIT 5
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $logs = $stmt->fetchAll();

                foreach ($logs as $log) {
                    $date = new DateTime($log['date']);
                    $today = new DateTime(date('Y-m-d'));
                    $diff = $today->diff($date)->days;
                    
                    $dateLabel = $diff === 0 ? 'Today' : 
                                ($diff === 1 ? 'Yesterday' : 
                                $date->format('M d, Y'));
                    
                    echo '<div class="log-item">
                            <div class="log-date">' . $dateLabel . '</div>
                            <div class="log-times">';
                    
                    if ($log['punch_in']) {
                        echo '<span class="in-time">
                                <i class="fas fa-sign-in-alt"></i> ' . 
                                date('h:i A', strtotime($log['punch_in'])) . ' IST' . 
                              '</span>';
                    }
                    
                    if ($log['punch_out']) {
                        echo '<span class="out-time">
                                <i class="fas fa-sign-out-alt"></i> ' . 
                                date('h:i A', strtotime($log['punch_out'])) . ' IST' . 
                              '</span>';
                    }
                    
                    echo '</div></div>';
                }
                ?>
            </div>
        </div>

        <!-- Leave Overview -->
        <div class="panel-section">
            <h5 class="section-title">Leave Status</h5>
            <div class="leave-status">
                <?php if (count($recent_leaves) > 0): ?>
                    <?php foreach ($recent_leaves as $leave): ?>
                        <div class="leave-status-item">
                            <div class="leave-type">
                                <?php echo htmlspecialchars($leave['leave_type']); ?>
                            </div>
                            <div class="leave-dates">
                                <?php 
                                echo date('M d', strtotime($leave['start_date']));
                                if ($leave['start_date'] !== $leave['end_date']) {
                                    echo ' - ' . date('M d', strtotime($leave['end_date']));
                                }
                                ?>
                            </div>
                            <div class="leave-status-badge <?php echo strtolower($leave['status']); ?>">
                                <?php echo ucfirst($leave['status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">No recent leave applications</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Late Punch-ins -->
        <div class="panel-section">
            <h5 class="section-title">
                Late Punch-ins 
                <?php if ($user_shift): ?>
                    <small class="text-muted">(Shift starts at <?php echo date('h:i A', strtotime($user_shift['start_time'])); ?>)</small>
                <?php endif; ?>
            </h5>
            <div class="late-punches">
                <?php if ($user_shift): ?>
                    <?php if (!empty($late_punches)): ?>
                        <?php foreach ($late_punches as $punch): ?>
                            <?php if ($punch['minutes_late'] > 0): ?>
                                <div class="late-punch-item">
                                    <div class="date"><?php echo date('M d, Y', strtotime($punch['date'])); ?></div>
                                    <div class="time"><?php echo date('h:i A', strtotime($punch['punch_in'])); ?></div>
                                    <div class="status late">
                                        <?php echo round($punch['minutes_late']); ?> mins late
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-check-circle mb-2"></i>
                            <p class="mb-0">No late punch-ins in the last 30 days</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-exclamation-circle mb-2"></i>
                        <p class="mb-0">No active shift assignment found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Leave Bank -->
        <div class="panel-section">
            <h5 class="section-title">Leave Bank</h5>
            <div class="leave-bank">
                <!-- Casual Leaves -->
                <div class="leave-type-balance">
                    <div class="leave-info">
                        <div class="leave-label">
                            <i class="fas fa-umbrella-beach text-primary"></i>
                            Casual Leaves
                        </div>
                        <div class="leave-numbers">
                            <span class="available"><?php echo number_format($casual_remaining, 1); ?></span>
                            <span class="total">/ <?php echo number_format($leave_data['casual_leaves'], 1); ?></span>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-primary" role="progressbar" 
                             style="width: <?php echo ($casual_remaining / $leave_data['casual_leaves']) * 100; ?>%">
                        </div>
                    </div>
                </div>

                <!-- Medical Leaves -->
                <div class="leave-type-balance">
                    <div class="leave-info">
                        <div class="leave-label">
                            <i class="fas fa-hospital text-danger"></i>
                            Medical Leaves
                        </div>
                        <div class="leave-numbers">
                            <span class="available"><?php echo number_format($medical_remaining, 1); ?></span>
                            <span class="total">/ <?php echo number_format($leave_data['medical_leaves'], 1); ?></span>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" role="progressbar" 
                             style="width: <?php echo ($medical_remaining / $leave_data['medical_leaves']) * 100; ?>%">
                        </div>
                    </div>
                </div>

                <!-- Short Leaves -->
                <div class="leave-type-balance">
                    <div class="leave-info">
                        <div class="leave-label">
                            <i class="fas fa-clock text-warning"></i>
                            Short Leaves
                        </div>
                        <div class="leave-numbers">
                            <span class="available"><?php echo $short_remaining; ?></span>
                            <span class="total">/ 2</span>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" role="progressbar" 
                             style="width: <?php echo ($short_remaining / 2) * 100; ?>%">
                        </div>
                    </div>
                </div>

                <div class="leave-note">
                    <i class="fas fa-info-circle"></i>
                    Unused casual leaves carry forward to next month
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/dashboard.js"></script>

    <!-- CSS -->
    <style>
        :root {
            --primary-color: #6366f1;      /* Indigo */
            --primary-hover: #4f46e5;      /* Darker Indigo */
            --secondary-color: #64748b;    /* Slate */
            --success-color: #22c55e;      /* Green */
            --danger-color: #ef4444;       /* Red */
            --warning-color: #f59e0b;      /* Amber */
            --info-color: #3b82f6;         /* Blue */
            
            /* Background Colors */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            
            /* Text Colors */
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            
            /* Sidebar Specific */
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --sidebar-bg: #ffffff;
            --sidebar-hover: #f1f5f9;
        }

        /* Core Sidebar Styles */
        .sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            height: calc(100vh - var(--header-height));
            width: 260px;
            background: var(--bg-primary);
            border-right: 1px solid rgba(0,0,0,0.1);
            transition: width 0.3s ease;
            z-index: 1000;
        }

        /* Sidebar Content */
        .sidebar-content {
            padding: 1.5rem 1rem;
            padding-top: 1.5rem;
            height: 100%;
            overflow-y: auto;
        }

        /* Navigation Links */
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s ease;
        }

        .nav-link i {
            font-size: 1.25rem;
            width: 1.75rem;
            margin-right: 0.75rem;
            color: var(--text-secondary);
            transition: color 0.2s ease;
        }

        .nav-link span {
            font-size: 0.95rem;
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
        }

        .nav-link:hover i {
            color: var(--primary-color);
        }

        .nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        .nav-link.active i {
            color: white;
        }

        /* Badge Styling */
        .badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
            margin-left: auto;
            background: var(--bg-secondary);
            color: var(--text-secondary);
        }

        .badge-danger {
            background: #fef2f2;
            color: var(--danger-color);
        }

        /* Toggle Button */
        .sidebar-toggle {
            position: absolute;
            top: 50%;
            right: -0.75rem;
            width: 28px;
            height: 28px;
            background: var(--primary-color);
            border: none;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transform: translateY(-50%);
        }

        .sidebar-toggle:hover {
            background: var(--primary-hover);
            transform: translateY(-50%) scale(1.1);
        }

        .sidebar-toggle i {
            font-size: 0.875rem;
            transition: transform 0.3s ease;
        }

        /* Collapsed State */
        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        .sidebar.collapsed .nav-link {
            padding: 0.75rem;
            justify-content: center;
        }

        .sidebar.collapsed .nav-link i {
            margin: 0;
            font-size: 1.4rem;
        }

        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .badge {
            display: none;
        }

        /* Tooltip for Collapsed State */
        .sidebar.collapsed .nav-link:hover::after {
            content: attr(data-title);
            position: absolute;
            left: calc(100% + 0.5rem);
            top: 50%;
            transform: translateY(-50%);
            background: var(--text-primary);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Main Content Adjustment */
        .main-content {
            margin-left: 260px;
            margin-top: var(--header-height);
            padding: 2rem;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--header-height));
        }

        .main-content.expanded {
            margin-left: 70px;
        }

        /* Scrollbar Styling */
        .sidebar-content::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 4px;
        }

        .sidebar-content::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        /* Welcome Section specific styles */
        .welcome-section {
            padding-top: 1rem;
            margin-bottom: 2rem;
        }

        /* Header/Navbar styles (if not already defined) */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            z-index: 1001;
            padding: 0 1.5rem;
        }

        /* Header/Navbar Height Variable */
        :root {
            --header-height: 60px;
        }

        /* Right Panel Styles */
        .right-panel {
            position: fixed;
            top: var(--header-height);
            right: 0;
            width: 300px;
            height: calc(100vh - var(--header-height));
            background: var(--bg-primary);
            border-left: 1px solid rgba(0,0,0,0.1);
            overflow-y: auto;
            padding: 1.5rem;
            z-index: 999;
        }

        /* Panel Sections */
        .panel-section {
            margin-bottom: 2rem;
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        /* Calendar Container */
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 15px 15px 15px 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .mini-calendar {
            width: 100%;
            background: #fff;
            border-radius: 8px;
            overflow: visible;
            margin-left: -8px;
        }

        /* Hide the extra title */
        .datepicker-title {
            display: none !important;
        }

        /* Enhance the header styling */
        .datepicker-header {
            background: transparent !important;
            padding: 10px !important;
            border-bottom: 1px solid #edf2f7;
        }

        /* Style the month/year display */
        .datepicker-header .datepicker-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 10px;
        }

        /* Navigation buttons */
        .datepicker .prev-btn,
        .datepicker .next-btn {
            color: var(--primary-color);
            background: transparent;
            border: none;
            padding: 5px 10px !important;
            border-radius: 4px;
        }

        .datepicker .prev-btn:hover,
        .datepicker .next-btn:hover {
            background-color: var(--bg-secondary);
        }

        /* Calendar grid background */
        .datepicker-main {
            background: transparent !important;
        }

        .datepicker-picker {
            background: transparent !important;
            border: none !important;
        }

        /* Update the calendar initialization script */
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const elem = document.getElementById('calendar');
            const datepicker = new Datepicker(elem, {
                autohide: true,
                format: 'yyyy-mm-dd',
                todayHighlight: true,
                updateOnBlur: false,
                weekStart: 1,
                calendarWeeks: false,
                daysOfWeekHighlighted: [0, 6],
                maxNumberOfDates: 1,
                title: '', // Remove the title
                container: '.mini-calendar'
            });
        });
        </script>

        /* Datepicker Customization */
        .datepicker {
            width: 100% !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }

        .datepicker-picker {
            width: calc(100% + 16px) !important;
            padding: 0 !important;
            border: 1px solid #edf2f7;
            border-radius: 8px;
        }

        /* Header Styling */
        .datepicker-header {
            background: #fff;
            padding: 10px !important;
            border-bottom: 1px solid #edf2f7;
        }

        .datepicker-title {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }

        /* Navigation Buttons */
        .datepicker .prev-btn,
        .datepicker .next-btn {
            color: #4a5568;
            padding: 5px !important;
            border-radius: 6px;
        }

        .datepicker .prev-btn:hover,
        .datepicker .next-btn:hover {
            background-color: #f7fafc;
        }

        /* Calendar Grid */
        .datepicker-main {
            padding: 10px !important;
        }

        .datepicker-view {
            width: 100% !important;
        }

        .datepicker-grid {
            width: 100% !important;
            padding: 0 4px;
        }

        /* Calendar Cells */
        .datepicker-cell {
            height: 32px !important;
            width: calc(100% / 7) !important;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4a5568;
            padding: 0 !important;
        }

        /* Today's Date */
        .datepicker-cell.today {
            background-color: #edf2f7 !important;
            color: var(--primary-color) !important;
            font-weight: 600;
        }

        /* Selected Date */
        .datepicker-cell.selected,
        .datepicker-cell.selected:hover {
            background-color: var(--primary-color) !important;
            color: white !important;
            font-weight: 600;
        }

        /* Hover State */
        .datepicker-cell:hover {
            background-color: #f7fafc !important;
        }

        /* Weekend Days */
        .datepicker-cell.sun,
        .datepicker-cell.sat {
            color: #e53e3e;
        }

        /* Days of Week Header */
        .days-of-week {
            width: 100% !important;
            padding: 0 4px;
        }

        .days-of-week span {
            width: calc(100% / 7) !important;
            height: 32px !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Disabled Dates */
        .datepicker-cell.disabled {
            color: #cbd5e0;
        }

        /* Month View Adjustments */
        .months .datepicker-cell,
        .years .datepicker-cell {
            height: 50px !important;
            font-size: 0.875rem;
        }

        /* Adjust calendar size in right panel */
        .right-panel .mini-calendar {
            min-height: auto;
            max-height: 300px;
        }

        /* Attendance Log */
        .log-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-date {
            font-weight: 500;
            color: var(--text-primary);
        }

        .log-times {
            display: flex;
            gap: 15px;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .log-times i {
            margin-right: 5px;
            color: var(--primary-color);
        }

        /* Leave Stats */
        .leave-stat-item {
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .stat-numbers {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .used {
            font-weight: 600;
            color: var(--primary-color);
        }

        .total {
            color: var(--text-secondary);
        }

        .progress {
            height: 6px;
            background: var(--bg-secondary);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            transition: width 0.3s ease;
        }

        /* Late Punch-ins */
        .late-punch-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            background: var(--bg-secondary);
            transition: transform 0.2s ease;
        }

        .late-punch-item:hover {
            transform: translateX(2px);
            background: #f8fafc;
        }

        .late-punch-item .date {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .late-punch-item .time {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .late-punch-item .status.late {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Empty state styling */
        .text-center.text-muted {
            font-size: 0.875rem;
        }

        .text-center.text-muted i {
            font-size: 1.5rem;
            color: var(--text-secondary);
            display: block;
        }

        /* Scrollbar for right panel */
        .right-panel::-webkit-scrollbar {
            width: 4px;
        }

        .right-panel::-webkit-scrollbar-track {
            background: transparent;
        }

        .right-panel::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 4px;
        }

        /* Adjust main content margin */
        .main-content {
            margin-right: 300px;
        }

        /* Calendar Container */
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 15px 15px 15px 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        /* Attendance Section */
        .attendance-section {
            margin-top: 30px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Attendance Log Styling */
        .attendance-log {
            margin-top: 15px;
        }

        .log-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-date {
            font-weight: 500;
            color: var(--text-primary);
        }

        .log-times {
            display: flex;
            gap: 15px;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .log-times i {
            margin-right: 5px;
            color: var(--primary-color);
        }

        /* Section Title Enhancement */
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        /* Add these styles to your CSS */
        .alert {
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
        }

        .alert-success {
            border-left-color: #198754;
        }

        .alert-danger {
            border-left-color: #dc3545;
        }

        .alert.fade {
            transition: opacity 0.15s linear;
        }

        .alert.fade.show {
            opacity: 1;
        }

        .alert i {
            font-size: 1.1em;
        }

        .alert .btn-close {
            padding: 0.75rem;
        }

        .header {
            background: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding: 1rem;
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1030;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: #2c3e50;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: #666;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.3s ease;
        }

        .sidebar-toggle:hover {
            color: #333;
        }

        /* Dropdown Styles */
        .dropdown-menu {
            padding: 0.5rem 0;
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
            border-radius: 0.5rem;
            min-width: 200px;
        }

        .dropdown-item {
            padding: 0.75rem 1.25rem;
            color: #2c3e50;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #4c6fff;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
        }

        .dropdown-divider {
            margin: 0.5rem 0;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        /* Button Styles */
        .btn-outline-primary, .btn-outline-secondary {
            border-width: 1.5px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline-primary:hover, .btn-outline-secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .header-content {
                padding: 0 0.5rem;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .btn-outline-primary, .btn-outline-secondary {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
        }

        /* Add these CSS fixes */
        .dropdown-menu {
            z-index: 1021; /* Make sure dropdowns appear above other elements */
        }

        .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }

        .dropdown-menu.show {
            display: block;
        }

        /* Leave Status Styles */
        .leave-status {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .leave-status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .leave-status-item:hover {
            transform: translateX(2px);
            background: #f8fafc;
        }

        .leave-type {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .leave-dates {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .leave-status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }

        .leave-status-badge.pending {
            background: #fff7ed;
            color: #c2410c;
        }

        .leave-status-badge.approved {
            background: #f0fdf4;
            color: #15803d;
        }

        .leave-status-badge.rejected {
            background: #fef2f2;
            color: #dc2626;
        }

        .leave-status-badge.cancelled {
            background: #f1f5f9;
            color: #64748b;
        }

        /* Leave Bank Styles */
        .leave-bank {
            padding: 0.5rem 0;
        }

        .leave-type-balance {
            margin-bottom: 1.25rem;
        }

        .leave-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .leave-label {
            font-size: 0.875rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .leave-label i {
            font-size: 1rem;
        }

        .leave-numbers {
            font-size: 0.875rem;
        }

        .leave-numbers .available {
            font-weight: 600;
            color: var(--text-primary);
        }

        .leave-numbers .total {
            color: var(--text-secondary);
        }

        .progress {
            height: 6px;
            background-color: #f1f5f9;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        .leave-note {
            font-size: 0.75rem;
            color: var(--text-secondary);
            padding-top: 0.5rem;
            border-top: 1px solid rgba(0,0,0,0.05);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .leave-note i {
            color: var(--primary-color);
        }

        /* Additional CSS for Short Leaves */
        .leave-type-balance {
            margin-bottom: 1.25rem;
            background: rgba(255, 255, 255, 0.5);
            padding: 0.75rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .leave-type-balance:hover {
            background: rgba(255, 255, 255, 0.8);
            transform: translateX(2px);
        }

        .leave-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .leave-label {
            font-size: 0.875rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .leave-label i {
            font-size: 1rem;
            width: 1.25rem;
            text-align: center;
        }

        .leave-numbers {
            font-size: 0.875rem;
        }

        .leave-numbers .available {
            font-weight: 600;
            color: var(--text-primary);
        }

        .leave-numbers .total {
            color: var(--text-secondary);
        }

        .progress {
            height: 6px;
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        .progress-bar.bg-warning {
            background-color: #f59e0b !important;
        }

        .leave-note {
            font-size: 0.75rem;
            color: var(--text-secondary);
            padding-top: 0.75rem;
            margin-top: 0.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .leave-note i {
            color: var(--primary-color);
        }

        /* Hover effects */
        .leave-type-balance:hover .progress-bar {
            opacity: 0.9;
        }
    </style>

    <!-- Before closing body tag, add JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mainContent = document.querySelector('.main-content');

            // Toggle sidebar
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                // Store state
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });

            // Load saved state
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }

            // Optional: Keyboard shortcut (Ctrl/Cmd + B)
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                    e.preventDefault();
                    sidebarToggle.click();
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const elem = document.getElementById('calendar');
            const datepicker = new Datepicker(elem, {
                autohide: true,
                format: 'yyyy-mm-dd',
                todayHighlight: true,
                updateOnBlur: false,
                weekStart: 1, // Monday
                calendarWeeks: false,
                daysOfWeekHighlighted: [0, 6], // Highlight weekends
                maxNumberOfDates: 1,
                title: new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
                container: '.mini-calendar'
            });

            // Update the title when month changes
            elem.addEventListener('changeMonth', function(e) {
                const date = e.detail.date;
                const title = date.toLocaleString('default', { month: 'long', year: 'numeric' });
                const titleElement = elem.querySelector('.datepicker-title');
                if (titleElement) {
                    titleElement.textContent = title;
                }
            });

            // Optional: Add event listener for date selection
            elem.addEventListener('changeDate', function(e) {
                console.log('Selected date:', e.detail.date);
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const punchButton = document.getElementById('punchButton');
            if (punchButton) {
                punchButton.addEventListener('click', function() {
                    const action = this.dataset.action;
                    
                    fetch('punch_attendance.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=' + action
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            const alert = `
                                <div class="alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-4" role="alert" style="z-index: 1050;">
                                    <i class="fas fa-check-circle me-2"></i>
                                    ${action === 'in' ? 'Punched in successfully!' : 'Punched out successfully!'}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            document.body.insertAdjacentHTML('beforeend', alert);
                            
                            // Reload the page after a short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            // Show error message
                            alert('Error: ' + (data.message || 'Something went wrong'));
                            // Reset button state
                            button.disabled = false;
                            button.innerHTML = originalContent;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while processing your request.');
                        // Reset button state
                        button.disabled = false;
                        button.innerHTML = originalContent;
                    });
                });
            }
        });

        // Task Assignment Form Handler
        const assignTaskForm = document.getElementById('assignTaskForm');
        if (assignTaskForm) {
            assignTaskForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('assign_task.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal and reload page
                        const modal = bootstrap.Modal.getInstance(document.getElementById('assignTaskModal'));
                        modal.hide();
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while assigning the task.');
                });
            });
        }
    </script>

    <!-- Add this modal at the bottom of the file, before the closing body tag -->
    <?php if ($can_assign_tasks): ?>
    <div class="modal fade" id="assignTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignTaskForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Task Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select class="form-select" name="assigned_to" required>
                                <option value="">Select User</option>
                                <?php foreach ($available_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']) . ' (' . htmlspecialchars($user['role']) . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($task_categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" class="form-control" name="due_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add this JavaScript at the bottom of your file, before </body> -->
    <script>
    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
    </script>

    <!-- Add this JavaScript after your existing scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(function(dropdown) {
            new bootstrap.Dropdown(dropdown, {
                boundary: 'window'
            });
        });
        
        // Optional: Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            var dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(function(dropdown) {
                if (!dropdown.contains(e.target)) {
                    var toggle = document.querySelector('[data-bs-toggle="dropdown"][aria-expanded="true"]');
                    if (toggle && !toggle.contains(e.target)) {
                        var bsDropdown = bootstrap.Dropdown.getInstance(toggle);
                        if (bsDropdown) {
                            bsDropdown.hide();
                        }
                    }
                }
            });
        });
    });
    </script>

    <script>
    // Function to update leave status
    function updateLeaveStatus() {
        fetch('get_leave_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const leaveStatusContainer = document.querySelector('.leave-status');
                    leaveStatusContainer.innerHTML = data.html;
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Update leave status every 30 seconds
    setInterval(updateLeaveStatus, 30000);

    // Also update when the page becomes visible again
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateLeaveStatus();
        }
    });
    </script>
</body>
</html>
