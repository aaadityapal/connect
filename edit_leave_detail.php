<?php
session_start();
require_once 'config/db_connect.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add role check at the top of the file
$is_manager = isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
$is_hr = isset($_SESSION['role']) && $_SESSION['role'] === 'hr';

// Check if leave ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Leave ID is required";
    header('Location: edit_leave.php');
    exit();
}

$leave_id = $_GET['id'];

// Fetch the existing leave request
$query = "SELECT lr.*, lt.name as leave_type_name 
          FROM leave_request lr 
          JOIN leave_types lt ON lr.leave_type = lt.id 
          WHERE lr.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $leave_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Leave request not found";
    header('Location: edit_leave.php');
    exit();
}

$leave_data = $result->fetch_assoc();

// Fetch leave types for dropdown
$leave_types_query = "SELECT * FROM leave_types";
$leave_types_result = $conn->query($leave_types_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Leave Request</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        /* Root Variables */
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #059669;
            --danger-color: #dc2626;
            --background-color: #f1f5f9;
            --border-color: #e2e8f0;
            --text-color: #1e293b;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
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

        .sidebar nav a {
            text-decoration: none;
        }

        .nav-link {
            color: var(--text-color);
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
            min-height: 100vh;
            background-color: var(--background-color);
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .container {
            max-width: none;
            width: 100%;
            margin: 0;
            padding: 0;
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
                padding: 1rem;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .container {
                padding: 1rem;
            }
        }

        /* Your existing styles */
        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.75rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
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
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link active">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="#" class="nav-link">
                <i class="bi bi-file-earmark-text-fill"></i>
                Reports
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
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
        <div class="container">
            <h2>Leave Request Details</h2>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Display current status -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5>Current Status</h5>
                    <div class="mb-2">
                        <strong>Status:</strong> 
                        <span class="badge badge-<?php echo getLeaveStatusBadgeClass($leave_data['status']); ?>">
                            <?php echo ucfirst($leave_data['status']); ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <strong>Manager Approval:</strong> 
                        <span class="badge badge-<?php echo getLeaveStatusBadgeClass($leave_data['manager_approval']); ?>">
                            <?php echo $leave_data['manager_approval'] === null ? 'Pending' : ucfirst($leave_data['manager_approval']); ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <strong>HR Approval:</strong> 
                        <span class="badge badge-<?php echo getLeaveStatusBadgeClass($leave_data['hr_approval']); ?>">
                            <?php echo $leave_data['hr_approval'] === null ? 'Pending' : ucfirst($leave_data['hr_approval']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <form action="handle_leave_operations.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="leave_id" value="<?php echo $leave_id; ?>">
                <input type="hidden" name="user_id" value="<?php echo $leave_data['user_id']; ?>">

                <div class="form-group">
                    <label>Leave Type</label>
                    <select class="form-control" name="leave_type" id="leave_type">
                        <?php while ($type = $leave_types_result->fetch_assoc()): ?>
                            <option value="<?php echo $type['id']; ?>" 
                                    <?php echo ($type['id'] == $leave_data['leave_type']) ? 'selected' : ''; ?>
                                    data-is-short="<?php echo stripos($type['name'], 'short') !== false ? '1' : '0'; ?>">
                                <?php echo $type['name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo $leave_data['start_date']; ?>" required>
                </div>

                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo $leave_data['end_date']; ?>" required>
                </div>

                <div class="time-fields" style="display: <?php echo stripos($leave_data['leave_type_name'], 'short') !== false ? 'block' : 'none'; ?>">
                    <div class="form-group">
                        <label>Time From</label>
                        <input type="time" class="form-control" name="time_from" 
                               value="<?php echo $leave_data['time_from']; ?>">
                    </div>

                    <div class="form-group">
                        <label>Time To</label>
                        <input type="time" class="form-control" name="time_to" 
                               value="<?php echo $leave_data['time_to']; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Duration (days)</label>
                    <input type="number" step="0.5" class="form-control" name="duration" 
                           value="<?php echo $leave_data['duration']; ?>" required>
                </div>

                <div class="form-group">
                    <label>Reason</label>
                    <textarea class="form-control" name="reason" required><?php echo $leave_data['reason']; ?></textarea>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <!-- Regular update buttons -->
                        <button type="submit" class="btn btn-primary">Update Leave Request</button>
                        <a href="edit_leave.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>

                <!-- Manager Approval Section -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5>Manager Action</h5>
                        <form action="handle_leave_operations.php" method="POST">
                            <input type="hidden" name="action" value="manager_action">
                            <input type="hidden" name="leave_id" value="<?php echo $leave_id; ?>">
                            
                            <div class="form-group">
                                <label for="manager_action_reason">Manager Comments</label>
                                <textarea class="form-control" name="manager_action_reason" id="manager_action_reason" rows="3" required></textarea>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="manager_decision" value="approved" class="btn btn-success">
                                    <i class="fas fa-check"></i> Manager Approve
                                </button>
                                <button type="submit" name="manager_decision" value="rejected" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Manager Reject
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- HR Approval Section -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5>HR Action</h5>
                        <form action="handle_leave_operations.php" method="POST">
                            <input type="hidden" name="action" value="hr_action">
                            <input type="hidden" name="leave_id" value="<?php echo $leave_id; ?>">
                            
                            <div class="form-group">
                                <label for="hr_action_reason">HR Comments</label>
                                <textarea class="form-control" name="hr_action_reason" id="hr_action_reason" rows="3" required></textarea>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="hr_decision" value="approved" class="btn btn-success">
                                    <i class="fas fa-check"></i> HR Approve
                                </button>
                                <button type="submit" name="hr_decision" value="rejected" class="btn btn-danger">
                                    <i class="fas fa-times"></i> HR Reject
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        // Add sidebar functionality
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

        // Your existing scripts
        $(document).ready(function() {
            $('#leave_type').change(function() {
                const isShortLeave = $(this).find(':selected').data('is-short') === 1;
                $('.time-fields').toggle(isShortLeave);
            });
        });
    </script>

    <?php
    function getLeaveStatusBadgeClass($status) {
        switch (strtolower($status)) {
            case 'approved':
                return 'success';
            case 'rejected':
                return 'danger';
            case 'pending':
            case null:
                return 'warning';
            default:
                return 'secondary';
        }
    }
    ?>
</body>
</html> 