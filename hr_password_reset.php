<?php
session_start();
require_once 'config/db_connect.php';

// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Script started - " . date('Y-m-d H:i:s'));

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    $_SESSION['error'] = "Access denied. Only HR personnel can access this page.";
    header("Location: login.php");
    exit();
}

// Function to generate a secure random password
function generateRandomPassword($length = 12) {
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $special = '@#$%^&*()_+';
    
    $password = '';
    
    // Add at least one character from each set
    $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
    $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $special[rand(0, strlen($special) - 1)];
    
    // Complete the password to desired length
    $allChars = $lowercase . $uppercase . $numbers . $special;
    while(strlen($password) < $length) {
        $password .= $allChars[rand(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password to make it more random
    return str_shuffle($password);
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $stmt = null;
    $check_result = null;
    
    try {
        if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
            throw new Exception("User ID is required");
        }
        
        // Check database connection
        if (!$conn || $conn->connect_error) {
            throw new Exception("Database connection error");
        }
        
        $user_id = $_POST['user_id'];
        $new_password = generateRandomPassword();
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Start transaction
        $conn->begin_transaction();
        
        // Update password
        $update_query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        
        if ($stmt === false) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        if (!$stmt->bind_param("si", $hashed_password, $user_id)) {
            throw new Exception("Failed to bind parameters: " . $stmt->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("No user found with the provided ID");
        }
        
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        $stmt = null;
        
        // Check if activity_log table has the required structure
        $check_table_query = "SHOW COLUMNS FROM activity_log LIKE 'generated_password'";
        $check_result = $conn->query($check_table_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            $log_query = "INSERT INTO activity_log (user_id, activity_type, description, generated_password) 
                         VALUES (?, 'password_reset', ?, ?)";
            $stmt = $conn->prepare($log_query);
            
            if ($stmt === false) {
                throw new Exception("Database prepare error for logging: " . $conn->error);
            }
            
            $admin_name = $_SESSION['username'] ?? 'Administrator';
            $description = "Password reset by " . $admin_name . " (ID: " . $_SESSION['user_id'] . ")";
            
            if (!$stmt->bind_param("iss", $user_id, $description, $new_password)) {
                throw new Exception("Failed to bind parameters for logging: " . $stmt->error);
            }
        } else {
            $log_query = "INSERT INTO activity_log (user_id, activity_type, description) 
                         VALUES (?, 'password_reset', ?)";
            $stmt = $conn->prepare($log_query);
            
            if ($stmt === false) {
                throw new Exception("Database prepare error for logging: " . $conn->error);
            }
            
            $admin_name = $_SESSION['username'] ?? 'Administrator';
            $description = "Password reset by " . $admin_name . " (ID: " . $_SESSION['user_id'] . "). New password generated.";
            
            if (!$stmt->bind_param("is", $user_id, $description)) {
                throw new Exception("Failed to bind parameters for logging: " . $stmt->error);
            }
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to log password reset: " . $stmt->error);
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Password has been reset successfully. New password: " . $new_password;
        
    } catch (Exception $e) {
        if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
            try {
                $conn->rollback();
            } catch (Exception $rollback_error) {
                // Silent catch - we're already in an error state
            }
        }
        $_SESSION['error_message'] = "Failed to reset password: " . $e->getMessage();
    } finally {
        if ($stmt instanceof mysqli_stmt) {
            try {
                $stmt->close();
            } catch (Exception $e) {
                // Silent catch - cleanup operation
            }
        }
        if ($check_result instanceof mysqli_result) {
            try {
                $check_result->close();
            } catch (Exception $e) {
                // Silent catch - cleanup operation
            }
        }
        $stmt = null;
        $check_result = null;
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all users except admins and current user
try {
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection is not available");
    }

    $current_user_id = $_SESSION['user_id'];
    $query = "SELECT id, username, email, role, unique_id 
              FROM users 
              WHERE id != ? 
              AND role NOT IN ('admin') 
              ORDER BY role, username";

    $stmt = $conn->prepare($query);
    
    if ($stmt === false) {
        throw new Exception("Failed to prepare user fetch query");
    }
    
    if (!$stmt->bind_param("i", $current_user_id)) {
        throw new Exception("Failed to bind parameters for user fetch");
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to fetch users");
    }
    
    $result = $stmt->get_result();
    
    if ($stmt instanceof mysqli_stmt) {
        try {
            $stmt->close();
        } catch (Exception $e) {
            // Silent catch - cleanup operation
        }
    }
    $stmt = null;
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Failed to load users: " . $e->getMessage();
    $result = false;
} finally {
    if ($stmt instanceof mysqli_stmt) {
        try {
            $stmt->close();
        } catch (Exception $e) {
            // Silent catch - cleanup operation
        }
    }
    $stmt = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset User Passwords | HR Portal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4F46E5;
            --primary-dark: #4338CA;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --bg-light: #F3F4F6;
            --bg-white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --border-radius: 16px;
            --spacing-sm: 12px;
            --spacing-md: 18px;
            --spacing-lg: 24px;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Sidebar Styles */
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
            color: var(--primary-color);
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

        .nav-link {
            color: var(--text-dark);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: var(--primary-color);
            background-color: #F3F4FF;
        }

        .nav-link i {
            margin-right: 0.75rem;
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
            background: var(--primary-color);
            color: white;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            width: calc(100% - var(--sidebar-width));
            padding: 20px;
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .container {
            width: 100%;
            max-width: none;
            padding: 0;
            margin: 0;
        }

        .reset-container {
            max-width: 500px;
            margin: 2rem auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px;
            border-bottom: none;
        }

        .card-header h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .card-body {
            padding: 40px;
            background: white;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .form-control {
            height: 50px;
            padding: 10px 20px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-reset {
            height: 50px;
            font-weight: 600;
            font-size: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0, 0, 0, 0.1);
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                width: calc(100% - 70px);
                padding: 15px;
            }

            .container {
                padding: 0;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .reset-container {
                margin: 1rem;
            }

            .card-body {
                padding: 20px;
            }

            .table-responsive {
                margin-top: 15px;
                padding: 15px;
            }
        }

        .user-select {
            background-color: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            margin-bottom: 20px;
            font-size: 16px;
        }

        .user-select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .user-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }

        .user-info.show {
            display: block;
        }

        .badge {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 15px;
            margin-left: 10px;
        }

        .badge-role {
            background-color: #4F46E5;
            color: white;
        }

        .role-badge {
            padding: 6px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .role-badge.hr {
            background-color: var(--danger);
            color: white;
        }

        .role-badge.senior-manager-studio {
            background-color: var(--primary);
            color: white;
        }

        .role-badge.manager {
            background-color: var(--success);
            color: white;
        }

        .role-badge.employee {
            background-color: var(--info);
            color: white;
        }

        .user-id {
            font-family: monospace;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            border: 1px solid #e5e9ff;
        }

        .table-responsive {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.1);
            overflow-x: auto;
            margin-top: 20px;
            transition: all 0.3s ease;
            border: 1px solid #e5e9ff;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .page-header {
            margin: 0 0 20px 0;
            padding: 20px 0;
            border-bottom: 2px solid var(--primary-light);
            width: 100%;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            justify-content: center;
            min-width: 140px;
            height: 40px;
        }

        .btn i {
            font-size: 16px;
        }

        .btn-reset {
            background: linear-gradient(45deg, #FF6B6B, #FF8E53);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.2);
        }

        .btn-reset:hover {
            background: linear-gradient(45deg, #FF8E53, #FF6B6B);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
            color: white;
        }

        .btn-history {
            background: linear-gradient(45deg, #4361EE, #3F37C9);
            color: white;
            margin-left: 0;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.2);
        }

        .btn-history:hover {
            background: linear-gradient(45deg, #3F37C9, #4361EE);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 10% auto;
            padding: 25px;
            width: 80%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.2);
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .password-history {
            margin-top: 20px;
        }

        .password-history-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .timestamp {
            color: var(--text-muted);
            font-size: 0.9em;
            margin-top: 5px;
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
            <a href="shifts.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="manager_payouts.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Managers Payout
            </a>
            <a href="company_analytics_dashboard.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Company Stats
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-x-fill"></i>
                Leave Request
            </a>
            <a href="admin/manage_geofence_locations.php" class="nav-link">
                <i class="bi bi-geo-alt-fill"></i>
                Geofence Locations
            </a>
            <a href="travelling_allowanceh.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="hr_overtime_approval.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Overtime Approval
            </a>
            <a href="hr_password_reset.php" class="nav-link active">
                <i class="bi bi-key-fill"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <div class="mt-auto"></div>
            <a href="logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-left"></i>
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
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-key"></i> Reset User Passwords
                </h1>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($user = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="user-id"><?php echo htmlspecialchars($user['unique_id']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge <?php 
                                            echo strtolower(str_replace(' ', '-', $user['role'])); 
                                        ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;" 
                                                onsubmit="return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($user['username']); ?>?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="reset_password" class="btn btn-reset">
                                                    <i class="fas fa-key"></i> Reset Password
                                                </button>
                                            </form>
                                            <button onclick="showPasswordHistory(<?php echo $user['id']; ?>)" class="btn btn-history">
                                                <i class="fas fa-history"></i> Password History
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Password History Modal -->
    <div id="passwordHistoryModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closePasswordHistory()">&times;</span>
            <h2>Password Reset History</h2>
            <div id="passwordHistoryContent" class="password-history">
                <!-- Password history will be loaded here -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            // Handle sidebar toggle click
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Store the state in localStorage
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
            
            // Check if sidebar was collapsed previously
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            }
            
            // Add mobile detection for sidebar
            function checkMobile() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                } else if (!sidebarCollapsed) {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                }
            }
            
            // Initial check
            checkMobile();
            
            // Listen for window resize
            window.addEventListener('resize', checkMobile);
        });

        // User selection handling
        document.getElementById('user_select').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const userInfo = document.getElementById('userInfo');
            
            if (this.value) {
                document.getElementById('selectedUsername').textContent = selectedOption.dataset.username;
                document.getElementById('selectedEmail').textContent = selectedOption.dataset.email;
                document.getElementById('selectedRole').innerHTML = selectedOption.dataset.role + 
                    ' <span class="badge badge-role">' + selectedOption.dataset.role + '</span>';
                userInfo.classList.add('show');
            } else {
                userInfo.classList.remove('show');
            }
        });

        // Password toggle visibility
        function togglePassword() {
            const passwordInput = document.getElementById('new_password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });

        // Add password history functionality
        async function showPasswordHistory(userId) {
            try {
                const response = await fetch('get_password_history.php?user_id=' + userId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();

                const modal = document.getElementById('passwordHistoryModal');
                const content = document.getElementById('passwordHistoryContent');
                
                if (!modal || !content) {
                    return;
                }

                content.innerHTML = data.map(item => `
                    <div class="password-history-item">
                        <div>
                            <div>${item.description}</div>
                            <div class="timestamp">${item.timestamp}</div>
                        </div>
                        <button class="copy-password" onclick="copyPassword('${item.generated_password}')">
                            <i class="fas fa-copy"></i> Copy Password
                        </button>
                    </div>
                `).join('') || '<p>No password reset history found.</p>';
                
                modal.style.display = 'block';
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load password history'
                });
            }
        }

        function closePasswordHistory() {
            document.getElementById('passwordHistoryModal').style.display = 'none';
        }

        function copyPassword(password) {
            navigator.clipboard.writeText(password).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'Password has been copied to clipboard',
                    timer: 1500,
                    showConfirmButton: false
                });
            }).catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to copy password'
                });
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('passwordHistoryModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html> 
