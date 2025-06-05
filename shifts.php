<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && !isset($_SESSION['temp_admin_access']))) {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_shift'])) {
            $stmt = $pdo->prepare("INSERT INTO shifts (shift_name, start_time, end_time) VALUES (?, ?, ?)");
            $stmt->execute([
                $_POST['shift_name'],
                $_POST['start_time'],
                $_POST['end_time']
            ]);
            $_SESSION['success_message'] = "New shift created successfully!";
            header('Location: shifts.php');
            exit();
        }
        if (isset($_POST['assign_shift'])) {
            $pdo->beginTransaction();
            
            // First, get all current active assignments for this user
            $checkStmt = $pdo->prepare("SELECT id FROM user_shifts 
                                       WHERE user_id = ? 
                                       AND (effective_to IS NULL OR effective_to >= CURRENT_DATE())
                                       ORDER BY effective_from DESC");
            $checkStmt->execute([$_POST['user_id']]);
            $existingShifts = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

            // End all existing active assignments one day before new assignment
            if (count($existingShifts) > 0) {
                $dayBeforeNewAssignment = date('Y-m-d', strtotime($_POST['effective_from'] . ' -1 day'));
                
                $updateStmt = $pdo->prepare("UPDATE user_shifts 
                                          SET effective_to = ? 
                                          WHERE id IN (" . implode(',', array_fill(0, count($existingShifts), '?')) . ")");
                
                $params = array_merge([$dayBeforeNewAssignment], $existingShifts);
                $updateStmt->execute($params);
            }

            // Insert new shift assignment
            $insertStmt = $pdo->prepare("INSERT INTO user_shifts 
                                        (user_id, shift_id, weekly_offs, effective_from) 
                                        VALUES (?, ?, ?, ?)");
            $insertStmt->execute([
                $_POST['user_id'],
                $_POST['shift_id'],
                implode(',', $_POST['weekly_offs'] ?? []),
                $_POST['effective_from']
            ]);

            $pdo->commit();
            $_SESSION['success_message'] = "Shift assigned successfully!";
        }
        
        // Clean up duplicate shifts if requested
        if (isset($_POST['cleanup_duplicates'])) {
            $pdo->beginTransaction();
            
            // Get all users with active shifts
            $usersStmt = $pdo->query("SELECT DISTINCT user_id FROM user_shifts 
                                      WHERE effective_to IS NULL OR effective_to >= CURRENT_DATE()");
            $users = $usersStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $cleanedCount = 0;
            
            // For each user, keep only the most recent active shift
            foreach ($users as $userId) {
                // Get all active assignments for this user ordered by id desc (newest first)
                $shiftsStmt = $pdo->prepare("SELECT id FROM user_shifts 
                                           WHERE user_id = ? 
                                           AND (effective_to IS NULL OR effective_to >= CURRENT_DATE())
                                           ORDER BY id DESC");
                $shiftsStmt->execute([$userId]);
                $userShifts = $shiftsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // If more than one active shift, keep only the newest one
                if (count($userShifts) > 1) {
                    // Get the newest shift ID
                    $newestShift = $userShifts[0];
                    
                    // Remove the newest from the array
                    array_shift($userShifts);
                    
                    // End all other shifts as of yesterday
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $updateStmt = $pdo->prepare("UPDATE user_shifts 
                                               SET effective_to = ? 
                                               WHERE id IN (" . implode(',', array_fill(0, count($userShifts), '?')) . ")");
                    
                    $params = array_merge([$yesterday], $userShifts);
                    $updateStmt->execute($params);
                    
                    $cleanedCount += count($userShifts);
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "Cleanup complete! Ended $cleanedCount duplicate shift assignments.";
            header('Location: shifts.php');
            exit();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    header('Location: shifts.php');
    exit();
}

// Fetch all active users
$users_query = "SELECT id, username, unique_id, designation FROM users WHERE deleted_at IS NULL ORDER BY username";
$users = $pdo->query($users_query);
if (!$users) {
    echo "Error fetching users: " . print_r($pdo->errorInfo(), true);
}
$users = $users->fetchAll();

// Fetch all shifts
$shifts_query = "SELECT * FROM shifts ORDER BY shift_name";
$shifts = $pdo->query($shifts_query);
if (!$shifts) {
    echo "Error fetching shifts: " . print_r($pdo->errorInfo(), true);
}
$shifts = $shifts->fetchAll();

// Debug output
echo "<!-- Number of users: " . count($users) . " -->";
echo "<!-- Number of shifts: " . count($shifts) . " -->";

// Auto cleanup on page load - fix existing duplicates immediately
try {
    // Check if there are duplicates to clean up
    $duplicateCheckStmt = $pdo->query("SELECT user_id, COUNT(*) as count 
                                       FROM user_shifts 
                                       WHERE (effective_to IS NULL OR effective_to >= CURRENT_DATE())
                                       GROUP BY user_id 
                                       HAVING count > 1");
    $duplicateUsers = $duplicateCheckStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicateUsers) > 0) {
        $pdo->beginTransaction();
        $cleanedCount = 0;
        
        foreach ($duplicateUsers as $dupUser) {
            $userId = $dupUser['user_id'];
            
            // Get all active shifts for this user, ordered by newest first (highest ID)
            $userShiftsStmt = $pdo->prepare("SELECT id 
                                           FROM user_shifts 
                                           WHERE user_id = ? 
                                           AND (effective_to IS NULL OR effective_to >= CURRENT_DATE())
                                           ORDER BY id DESC");
            $userShiftsStmt->execute([$userId]);
            $userShifts = $userShiftsStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Keep the newest, end all others
            if (count($userShifts) > 1) {
                $newestId = array_shift($userShifts); // Remove and get the first (newest) ID
                
                if (!empty($userShifts)) {
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    
                    // Build placeholders for the SQL query
                    $placeholders = implode(',', array_fill(0, count($userShifts), '?'));
                    
                    // Update statement to end all old shifts
                    $endShiftsStmt = $pdo->prepare("UPDATE user_shifts 
                                                  SET effective_to = ? 
                                                  WHERE id IN ($placeholders)");
                    
                    $params = array_merge([$yesterday], $userShifts);
                    $endShiftsStmt->execute($params);
                    
                    $cleanedCount += count($userShifts);
                }
            }
        }
        
        $pdo->commit();
        // Only show message if we actually cleaned something
        if ($cleanedCount > 0) {
            $_SESSION['info_message'] = "Auto-cleaned $cleanedCount duplicate shift assignments.";
        }
    }
} catch (PDOException $e) {
    // Silent fail for auto-cleanup, don't disturb user
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// Fetch current shift assignments - using a simpler approach that works on older MySQL
// First get the list of user IDs with their most recent assignment ID
$latestAssignmentQuery = "SELECT user_id, MAX(id) as latest_id 
                          FROM user_shifts 
                          WHERE (effective_to IS NULL OR effective_to >= CURRENT_DATE()) 
                          GROUP BY user_id";

$latestAssignments = $pdo->query($latestAssignmentQuery)->fetchAll(PDO::FETCH_KEY_PAIR);

// If we have active assignments, fetch the details
if (!empty($latestAssignments)) {
    $ids = implode(',', $latestAssignments);
    $assignmentQuery = "SELECT us.*, u.username, u.unique_id, s.shift_name, s.start_time, s.end_time 
                      FROM user_shifts us 
                      JOIN users u ON us.user_id = u.id 
                      JOIN shifts s ON us.shift_id = s.id 
                      WHERE us.id IN ($ids) 
                      ORDER BY u.username";
    
    $current_assignments = $pdo->query($assignmentQuery)->fetchAll();
} else {
    $current_assignments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Shift Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        /* Root Variables */
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #343a40;
            --light: #f8f9fa;
            --border: #e9ecef;
            --text: #212529;
            --text-muted: #6c757d;
            --shadow: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Preserved Sidebar Styles */
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

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px);
        }

        .sidebar nav a {
            text-decoration: none;
        }

        .nav-link {
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: #4361ee;
            background-color: #F3F4FF;
        }

        .nav-link.active {
            background-color: #F3F4FF;
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #4361ee;
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout Link */
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

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        /* Toggle Button */
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

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        /* Your existing styles */
        .shift-form {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* Update other styles to match the modern look */
        .content-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            margin-top: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn {
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #4361ee;
            color: white;
        }

        .btn-primary:hover {
            background: #3a4ee0;
        }

        /* Update Table Styles */
        .shift-table-container {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .shift-table-container h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .shift-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1rem;
        }

        .shift-table th {
            background: #F8FAFC;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4B5563;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .shift-table td {
            padding: 1rem;
            font-size: 0.875rem;
            color: #1F2937;
            border-bottom: 1px solid #e5e7eb;
            background: white;
        }

        .shift-table tr:hover td {
            background: #F3F4FF;
        }

        .shift-table tr:last-child td {
            border-bottom: none;
        }

        /* Badge Styles */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            margin: 0.125rem;
        }

        .badge-primary {
            background: #EEF2FF;
            color: #4F46E5;
        }

        .badge-success {
            background: #ECFDF5;
            color: #059669;
        }

        /* Action Buttons */
        .btn-group {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #EEF2FF;
            color: #4F46E5;
        }

        .btn-edit:hover {
            background: #E0E7FF;
        }

        .btn-delete {
            background: #FEE2E2;
            color: #DC2626;
        }

        .btn-delete:hover {
            background: #FEE2E2;
        }

        /* Weekly Offs Display */
        .weekly-offs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .weekly-off-tag {
            background: #F3F4FF;
            color: #4F46E5;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Timing Display */
        .timing-display {
            color: #4B5563;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .timing-display i {
            color: #6B7280;
            font-size: 0.875rem;
        }

        /* Status Indicator */
        .status-active {
            width: 8px;
            height: 8px;
            background: #10B981;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        /* Form Styles Update */
        .shift-form {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .shift-form h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4B5563;
            margin-bottom: 0.5rem;
        }
        
        /* Cleanup Button Styles */
        .cleanup-container {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            text-align: right;
        }
        
        .btn-warning {
            background-color: #FFFBEB;
            color: #D97706;
            border: 1px solid #FDE68A;
            padding: 0.625rem 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-warning i {
            font-size: 0.875rem;
        }
        
        .btn-warning:hover {
            background-color: #FEF3C7;
        }
        
        .alert-info {
            background-color: #EFF6FF;
            color: #3B82F6;
            border: 1px solid #BFDBFE;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
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
            <a href="hr_dashboard.php" class="nav-link">
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
            <a href="shifts.php" class="nav-link active">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="bi bi-key-fill"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="content-header">
                <h2>User Shift Management</h2>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info_message'])): ?>
                <div class="alert alert-info">
                    <?php 
                    echo $_SESSION['info_message'];
                    unset($_SESSION['info_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="shift-form">
                <h3>Create New Shift</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="shift_name">Shift Name</label>
                        <input type="text" id="shift_name" name="shift_name" class="form-control" 
                               required placeholder="e.g., Morning Shift">
                    </div>

                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" class="form-control" required>
                    </div>

                    <button type="submit" name="create_shift" class="btn btn-primary">Create Shift</button>
                </form>
            </div>

            <!-- Available Shifts Table -->
            <div class="shift-table-container">
                <h3>
                    <i class="bi bi-clock"></i>
                    Available Shifts
                </h3>
                <table class="shift-table">
                    <thead>
                        <tr>
                            <th>Shift Name</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="status-active"></span>
                                        <?php echo htmlspecialchars($shift['shift_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="timing-display">
                                        <i class="bi bi-sunrise"></i>
                                        <?php echo date('h:i A', strtotime($shift['start_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="timing-display">
                                        <i class="bi bi-sunset"></i>
                                        <?php echo date('h:i A', strtotime($shift['end_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editShift(<?php echo $shift['id']; ?>)" 
                                                class="btn-icon btn-edit" title="Edit Shift">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button onclick="deleteShift(<?php echo $shift['id']; ?>)" 
                                                class="btn-icon btn-delete" title="Delete Shift">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="shift-form">
                <h3>Assign Shift to User</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="user_id">Select User</label>
                        <select id="user_id" name="user_id" class="form-control" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['unique_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="shift_id">Select Shift</label>
                        <select id="shift_id" name="shift_id" class="form-control" required>
                            <option value="">Select Shift</option>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo $shift['id']; ?>">
                                    <?php echo htmlspecialchars($shift['shift_name'] . ' (' . 
                                        date('h:i A', strtotime($shift['start_time'])) . ' - ' . 
                                        date('h:i A', strtotime($shift['end_time'])) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="effective_from">Effective From</label>
                        <input type="date" id="effective_from" name="effective_from" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Weekly Offs</label>
                        <div class="weekly-offs">
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day) {
                                echo "<label>
                                    <input type='checkbox' name='weekly_offs[]' value='$day'>
                                    $day
                                </label>";
                            }
                            ?>
                        </div>
                    </div>

                    <button type="submit" name="assign_shift" class="btn btn-primary">Assign Shift</button>
                </form>
            </div>

            <!-- Current Shift Assignments Table -->
            <div class="shift-table-container">
                <h3>
                    <i class="bi bi-people"></i>
                    Current Shift Assignments
                </h3>
                <?php if (isset($current_assignments) && count($current_assignments) > 0): ?>
                <table class="shift-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Employee ID</th>
                            <th>Shift</th>
                            <th>Timing</th>
                            <th>Weekly Offs</th>
                            <th>Effective From</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current_assignments as $assignment): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="status-active"></span>
                                        <?php echo htmlspecialchars($assignment['username']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($assignment['unique_id']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($assignment['shift_name']); ?></td>
                                <td>
                                    <div class="timing-display">
                                        <i class="bi bi-clock"></i>
                                        <?php 
                                        echo date('h:i A', strtotime($assignment['start_time'])) . ' - ' . 
                                             date('h:i A', strtotime($assignment['end_time']));
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="weekly-offs">
                                        <?php
                                        $weekly_offs = explode(',', $assignment['weekly_offs']);
                                        foreach ($weekly_offs as $day) {
                                            echo "<span class='weekly-off-tag'>$day</span>";
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-success">
                                        <?php echo date('d M Y', strtotime($assignment['effective_from'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editAssignment(<?php echo $assignment['id']; ?>)" 
                                                class="btn-icon btn-edit" title="Edit Assignment">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button onclick="endAssignment(<?php echo $assignment['id']; ?>)" 
                                                class="btn-icon btn-delete" title="End Assignment">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="alert alert-info">No active shift assignments found.</div>
                <?php endif; ?>
                
                <!-- Cleanup Button -->
                <div class="cleanup-container">
                    <form method="POST" onsubmit="return confirm('This will clean up all duplicate shift assignments. Continue?');">
                        <button type="submit" name="cleanup_duplicates" class="btn btn-warning">
                            <i class="bi bi-wrench"></i> Fix Duplicate Assignments
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Change icon direction
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('bi-chevron-left');
                    icon.classList.add('bi-chevron-right');
                } else {
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-left');
                }
            });
            
            // Handle responsive behavior
            function checkWidth() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                }
            }
            
            // Check on load
            checkWidth();
            
            // Check on resize
            window.addEventListener('resize', checkWidth);
            
            // Handle click outside on mobile
            document.addEventListener('click', function(e) {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile && !sidebar.contains(e.target) && !sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                }
            });
        });

        function editAssignment(id) {
            window.location.href = `edit_user_shift.php?id=${id}`;
        }

        function endAssignment(id) {
            if (confirm('Are you sure you want to end this shift assignment?')) {
                window.location.href = `end_user_shift.php?id=${id}`;
            }
        }

        function editShift(id) {
            window.location.href = `edit_shift.php?id=${id}`;
        }

        function deleteShift(id) {
            if (confirm('Are you sure you want to delete this shift? This may affect existing assignments.')) {
                window.location.href = `delete_shift.php?id=${id}`;
            }
        }
    </script>
</body>
</html> 