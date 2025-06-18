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
            --transition-normal: all 0.3s ease;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --gradient-primary: linear-gradient(145deg, #3b82f6, #2563eb);
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

        /* Enhance page header */
        h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-color);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.5px;
            position: relative;
            padding-bottom: 0.75rem;
        }

        h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: 3px;
        }

        /* Card styling */
        .card {
            border: 1px solid rgba(226, 232, 240, 0.6);
            box-shadow: var(--shadow-md);
            border-radius: 16px;
            margin-bottom: 1.75rem;
            transition: var(--transition-normal);
            overflow: hidden;
            position: relative;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-body {
            padding: 1.75rem;
        }

        /* Card headers */
        .card h5 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Status card */
        .card.mb-4 {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
        }

        .card.mb-4 .card-body {
            border-left: 4px solid var(--primary-color);
        }

        /* Status badges */
        .badge {
            padding: 0.5rem 0.85rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .badge-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .badge-warning {
            background-color: #fff7ed;
            color: #c2410c;
            border: 1px solid #fdba74;
        }

        .badge-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        /* Form controls */
        .form-control {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            transition: all 0.25s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(145deg, #2563eb, #1d4ed8);
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.25);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-success {
            background: linear-gradient(145deg, #10b981, #059669);
            border: none;
            color: white;
            box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2);
        }

        .btn-success:hover {
            background: linear-gradient(145deg, #059669, #047857);
            box-shadow: 0 6px 12px rgba(5, 150, 105, 0.25);
        }

        .btn-danger {
            background: linear-gradient(145deg, #ef4444, #dc2626);
            border: none;
            color: white;
            box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2);
        }

        .btn-danger:hover {
            background: linear-gradient(145deg, #dc2626, #b91c1c);
            box-shadow: 0 6px 12px rgba(220, 38, 38, 0.25);
        }

        /* Manager and HR Action cards */
        .card.mt-4 {
            margin-top: 2rem;
        }

        .card.mt-4:nth-of-type(1) .card-body {
            border-left: 4px solid #0369a1;
        }

        .card.mt-4:nth-of-type(2) .card-body {
            border-left: 4px solid #a855f7;
        }

        /* Add subtle animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.4s ease-out;
        }

        /* Enhanced styling for select dropdowns, especially Leave Type */
        select.form-control {
            height:50px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%234b5563' width='24px' height='24px'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        select.form-control:hover {
            border-color: #94a3b8;
            background-color: #f8fafc;
        }

        select.form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            background-color: white;
        }

        /* Specifically target the leave type dropdown */
        #leave_type {
            font-weight: 500;
            color: #334155;
            border: 1px solid #cbd5e1;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        #leave_type option {
            padding: 10px;
            font-weight: normal;
        }

        #leave_type option:first-child {
            font-weight: 600;
        }

        /* Add a subtle label enhancement */
        label[for="leave_type"] {
            color: #1e40af;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        label[for="leave_type"]::before {
            content: '\f073';
            font-family: 'Font Awesome 5 Free';
            color: var(--primary-color);
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