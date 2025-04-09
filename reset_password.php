<?php
session_start();
require_once 'config/db_connect.php';

// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Script started - " . date('Y-m-d H:i:s'));

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && $_SESSION['role'] !== 'Senior Manager (Studio)')) {
    error_log("Unauthorized access attempt - User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", Role: " . ($_SESSION['role'] ?? 'Not set'));
    header('Location: unauthorized.php');
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
        
        // Check if prepare was successful
        if ($stmt === false) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        // Bind parameters and execute
        if (!$stmt->bind_param("si", $hashed_password, $user_id)) {
            throw new Exception("Failed to bind parameters: " . $stmt->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        
        // Check if the update affected any rows
        if ($stmt->affected_rows === 0) {
            throw new Exception("No user found with the provided ID");
        }
        
        // Safely close the first statement
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        $stmt = null;
        
        // Check if activity_log table has the required structure
        $check_table_query = "SHOW COLUMNS FROM activity_log LIKE 'generated_password'";
        $check_result = $conn->query($check_table_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            // Table has generated_password column
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
            // Table doesn't have generated_password column - use alternative query
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
        // Clean up resources
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
    $query = "SELECT id, username, email, role, employee_id, unique_id 
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
    
    // Close the statement after getting the result
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
    // Clean up any remaining statement
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
    <title>Reset User Passwords</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow: hidden;
        }

        /* Dashboard Layout */
        .dashboard {
            display: flex;
            height: 100vh;
            overflow: hidden;
            max-height: 100vh;
            position: relative;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            border-right: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            height: 100vh;
            overflow: visible !important;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            flex-shrink: 0;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .toggle-btn {
            position: absolute;
            top: 10px;
            right: -15px;
            background-color: #ffffff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            border: 1px solid #e0e0e0;
        }

        .sidebar.collapsed .toggle-btn {
            display: flex !important;
            opacity: 1 !important;
            right: -15px !important;
        }

        .sidebar-header {
            padding: 20px 15px 10px;
            color: #888;
            font-size: 12px;
            flex-shrink: 0;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 20px 0 10px;
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            flex-shrink: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-menu li a:hover {
            background-color: #f5f5f5;
        }

        .sidebar-menu li.active a {
            background-color: #f9f9f9;
            color: #ff3e3e;
            border-left: 3px solid #ff3e3e;
        }

        .sidebar-menu li a i {
            margin-right: 10px;
            font-size: 18px;
            min-width: 25px;
            text-align: center;
        }

        .sidebar.collapsed .sidebar-menu li a {
            padding: 12px 0;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-menu li a i {
            margin-right: 0;
            font-size: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            background-color: #f5f8fa;
            width: calc(100% - 250px);
            transition: width 0.3s ease;
        }

        .sidebar.collapsed + .main-content {
            width: calc(100% - 70px);
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 10px 0;
            flex-shrink: 0;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            color: #ff3e3e !important;
            padding: 12px 15px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 20px;
            background: transparent;
            box-shadow: none;
        }

        .page-header {
            margin: 20px 0 30px 0;
            padding: 20px 0;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: var(--primary);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        }

        .table-responsive:hover {
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.15);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            background: white;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-reset {
            background: linear-gradient(135deg, #fca311, #f8961e);
            color: white;
            box-shadow: 0 4px 12px rgba(248, 150, 30, 0.2);
        }

        .btn-reset:hover {
            background: linear-gradient(135deg, #f8961e, #e76f51);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(248, 150, 30, 0.3);
        }

        .btn-history {
            background: linear-gradient(135deg, #4361ee, #3a4ee0);
            color: white;
            margin-left: 10px;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .btn-history:hover {
            background: linear-gradient(135deg, #3a4ee0, #2f44d9);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.3);
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

        .close-modal:hover {
            color: #000;
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

        .password-history-item:last-child {
            border-bottom: none;
        }

        .copy-password {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .copy-password:hover {
            background-color: var(--primary-light);
        }

        .timestamp {
            color: var(--text-muted);
            font-size: 0.9em;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .main-content {
                width: calc(100% - 70px);
            }

            .sidebar.expanded + .main-content {
                width: calc(100% - 250px);
            }
            
            .container {
                padding: 10px;
            }

            th, td {
                padding: 8px;
            }

            .btn {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" id="toggle-btn">
                <i class="fas fa-chevron-left"></i>
            </div>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">MAIN</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="real.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="leave.php">
                        <i class="fas fa-calendar-check"></i>
                        <span class="sidebar-text">Leaves</span>
                    </a>
                </li>
                <li>
                    <a href="employee.php">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-text">Employees</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-box"></i>
                        <span class="sidebar-text">Projects</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">ANALYTICS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i>
                        <span class="sidebar-text"> Employee Reports</span>
                    </a>
                </li>
                <li>
                    <a href="work_report.php">
                        <i class="fas fa-file-invoice"></i>
                        <span class="sidebar-text"> Work Reports</span>
                    </a>
                </li>
                <li>
                    <a href="attendance_report.php">
                        <i class="fas fa-clock"></i>
                        <span class="sidebar-text"> Attendance Reports</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">SETTINGS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span class="sidebar-text">Profile</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-bell"></i>
                        <span class="sidebar-text">Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="manager_settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
                <li class="active">
                    <a href="reset_password.php">
                        <i class="fas fa-lock"></i>
                        <span class="sidebar-text">Reset Password</span>
                    </a>
                </li>
            </ul>

            <!-- Logout -->
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="sidebar-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
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
                                        <form method="POST" style="display: inline;" 
                                            onsubmit="return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($user['username']); ?>?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="reset_password" class="btn btn-reset">
                                                <i class="fas fa-sync-alt"></i> Reset Password
                                            </button>
                                        </form>
                                        <button onclick="showPasswordHistory(<?php echo $user['id']; ?>)" class="btn btn-history">
                                            <i class="fas fa-history"></i> Password History
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
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
        // Sidebar Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggle-btn');
            
            if (!sidebar || !toggleBtn) {
                return;
            }
            
            // Toggle sidebar collapse/expand
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                
                // Change icon direction based on sidebar state
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
        });

        // Password history functionality
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