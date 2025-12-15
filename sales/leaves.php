<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Database connection
require_once '../config/db_connect.php';

// Get user data from database
$user_id = $_SESSION['user_id'];
$username = 'User';
$user_role = 'Employee';
$profile_image = '';

try {
    $stmt = $pdo->prepare("SELECT username, role, profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        $username = isset($user['username']) ? $user['username'] : 'User';
        $user_role = isset($user['role']) ? $user['role'] : 'Employee';
        $profile_image = isset($user['profile_image']) ? $user['profile_image'] : '';
    }
} catch (Exception $e) {
    // Fallback to session data if query fails
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
    error_log("Error fetching user data: " . $e->getMessage());
}

// Fetch leave types from database
$leave_types = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, max_days, carry_forward, paid, color_code, status 
                           FROM leave_types 
                           WHERE status = 'active' 
                           AND name != 'Back office leave'
                           ORDER BY name ASC");
    $stmt->execute();
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching leave types: " . $e->getMessage());
}

// Fetch user's leave balance for each type
$leave_balances = [];
$total_available = 0;
$total_used = 0;

try {
    foreach ($leave_types as $type) {
        // Calculate used days for this leave type (approved + pending requests for current year)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(duration), 0) as used_days 
                               FROM leave_request 
                               WHERE user_id = ? 
                               AND leave_type = ? 
                               AND status IN ('approved', 'pending')
                               AND YEAR(start_date) = YEAR(CURDATE())");
        $stmt->execute([$user_id, $type['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $used_days = floatval($result['used_days']);

        $leave_balances[$type['name']] = [
            'used' => $used_days,
            'total' => $type['max_days'],
            'available' => $type['max_days'] - $used_days
        ];

        $total_available += ($type['max_days'] - $used_days);
        $total_used += $used_days;
    }
} catch (Exception $e) {
    error_log("Error calculating leave balance: " . $e->getMessage());
}

// Get statistics
$stats = [
    'total_balance' => $total_available,
    'casual_available' => isset($leave_balances['Casual Leave']) ? $leave_balances['Casual Leave']['available'] : 0,
    'casual_total' => isset($leave_balances['Casual Leave']) ? $leave_balances['Casual Leave']['total'] : 0,
    'sick_available' => isset($leave_balances['Sick Leave']) ? $leave_balances['Sick Leave']['available'] : 0,
    'sick_total' => isset($leave_balances['Sick Leave']) ? $leave_balances['Sick Leave']['total'] : 0,
    'pending_requests' => 0
];

// Get pending requests count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_request WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_requests'] = $result['count'];
} catch (Exception $e) {
    error_log("Error fetching pending requests: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management | ArchitectsHive</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <!-- Icons (Feather Icons) -->
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }
    </style>
    <!-- Pass user data to JavaScript -->
    <script>
        window.currentUsername = "<?php echo htmlspecialchars($username); ?>";
        window.userRole = "<?php echo htmlspecialchars($user_role); ?>";
        window.profileImageUrl = "<?php echo htmlspecialchars($profile_image); ?>";
    </script>
    <!-- Greeting Module -->
    <script src="greeting.js"></script>
</head>

<body>

    <!-- Sidebar Container -->
    <aside class="sidebar" id="sidebarContainer">
        <!-- Sidebar content will be loaded here -->
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i data-feather="menu"></i>
            </button>
            <h1 class="page-title">Leave Management</h1>

            <div class="header-actions">
                <div class="search-bar">
                    <i data-feather="search" class="search-icon"></i>
                    <input type="text" placeholder="Search leaves...">
                </div>

                <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                    <i data-feather="sun"></i>
                </button>

                <button class="btn-icon" id="notificationBtn">
                    <i data-feather="bell"></i>
                    <span class="notification-badge"></span>
                </button>

                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="dropdown-header">
                        <span>Notifications</span>
                        <button class="mark-read">Mark all as read</button>
                    </div>
                    <div class="dropdown-body">
                        <div class="notification-item unread">
                            <div class="notif-icon bg-green"><i data-feather="check-circle"
                                    style="width: 16px; height: 16px;"></i></div>
                            <div class="notif-content">
                                <p class="notif-text">Your leave request was <strong>approved</strong>.</p>
                                <span class="notif-time">2 hours ago</span>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div class="notif-icon bg-blue"><i data-feather="calendar"
                                    style="width: 16px; height: 16px;"></i></div>
                            <div class="notif-content">
                                <p class="notif-text">Upcoming leave on <strong>Dec 20, 2024</strong>.</p>
                                <span class="notif-time">Yesterday</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn-primary" id="applyLeaveBtn">
                    <i data-feather="plus"></i>
                    <span>Apply Leave</span>
                </button>
            </div>
        </header>

        <!-- Dashboard View -->
        <div class="dashboard-view">

            <!-- Greeting Section -->
            <div class="greeting-section">
                <div class="greeting-content">
                    <div class="greeting-left">
                        <h2 class="greeting-text"><span id="greeting-msg">Good Morning</span>, <span
                                id="username">User</span></h2>
                        <div class="datetime-display">
                            <div class="date-time-group">
                                <div class="date-item">
                                    <i data-feather="calendar"></i>
                                    <span id="date-info"></span>
                                </div>
                                <div class="time-item">
                                    <i data-feather="clock"></i>
                                    <span id="time-info"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Balance Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Leave Balance</span>
                        <i data-feather="calendar" class="stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_balance'], 1); ?></div>
                    <div class="stat-trend trend-up">
                        <i data-feather="info" width="14"></i>
                        <span>Days remaining</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Casual Leave</span>
                        <i data-feather="coffee" class="stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['casual_available'], 1); ?></div>
                    <div class="stat-trend trend-up">
                        <i data-feather="trending-up" width="14"></i>
                        <span>Out of <?php echo $stats['casual_total']; ?> days</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Sick Leave</span>
                        <i data-feather="heart" class="stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['sick_available'], 1); ?></div>
                    <div class="stat-trend trend-up">
                        <i data-feather="trending-up" width="14"></i>
                        <span>Out of <?php echo $stats['sick_total']; ?> days</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Pending Requests</span>
                        <i data-feather="clock" class="stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
                    <div class="stat-trend <?php echo $stats['pending_requests'] > 0 ? 'trend-down' : 'trend-up'; ?>">
                        <i data-feather="<?php echo $stats['pending_requests'] > 0 ? 'alert-circle' : 'check-circle'; ?>"
                            width="14"></i>
                        <span><?php echo $stats['pending_requests'] > 0 ? 'Awaiting approval' : 'All clear'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Leave History Section -->
            <div class="leads-section">
                <div class="section-header">
                    <div class="header-top">
                        <h2 class="section-title">Leave History</h2>
                        <div class="filter-actions">
                            <!-- Could add export/print here -->
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="filter-bar">
                        <div class="filter-group">
                            <select class="filter-select" id="statusFilter">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select class="filter-select" id="typeFilter">
                                <option value="">All Types</option>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>">
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select class="filter-select" id="monthFilter">
                                <option value="">All Months</option>
                                <?php 
                                $currentMonth = date('m');
                                $months = [
                                    '01' => 'January', '02' => 'February', '03' => 'March',
                                    '04' => 'April', '05' => 'May', '06' => 'June',
                                    '07' => 'July', '08' => 'August', '09' => 'September',
                                    '10' => 'October', '11' => 'November', '12' => 'December'
                                ];
                                foreach ($months as $value => $name):
                                ?>
                                <option value="<?php echo $value; ?>" <?php echo $value == $currentMonth ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select class="filter-select" id="yearFilter">
                                <option value="">All Years</option>
                                <?php
                                $currentYear = date('Y');
                                for ($year = $currentYear; $year >= $currentYear - 5; $year--):
                                    ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year == $currentYear ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Applied On</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="leaveRequestsTableBody">
                            <tr id="loadingRow">
                                <td colspan="8" style="text-align: center; padding: 3rem;">
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                                        <i data-feather="loader"
                                            style="width: 32px; height: 32px; color: var(--text-muted); animation: spin 1s linear infinite;"></i>
                                        <span style="color: var(--text-muted);">Loading leave requests...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Apply Leave Modal -->
    <div class="modal-overlay" id="applyLeaveModal">
        <div class="modal" style="max-width: 650px;">
            <div class="modal-header"
                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%); border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div
                        style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--accent-color) 0%, rgba(255, 255, 255, 0.8) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i data-feather="calendar" style="width: 20px; height: 20px; color: var(--accent-inverse);"></i>
                    </div>
                    <div>
                        <h3 class="modal-title" style="margin: 0; font-size: 1.3rem;">Apply for Leave</h3>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">Submit your leave request for
                            approval</p>
                    </div>
                </div>
                <button class="close-modal" id="closeModalBtn">&times;</button>
            </div>
            <div class="modal-body" style="padding: 2rem;">
                <form id="applyLeaveForm">
                    <!-- Leave Type Section -->
                    <div
                        style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <i data-feather="tag" style="width: 18px; height: 18px; color: var(--accent-color);"></i>
                            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-primary);">
                                Leave Information</h4>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="leaveType"
                                style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span>Leave Type</span>
                                <span style="color: var(--status-lost);">*</span>
                            </label>
                            <select id="leaveType" required
                                style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; cursor: pointer; transition: all 0.2s;">
                                <option value="">Select Leave Type</option>
                                <?php foreach ($leave_types as $type):
                                    // Calculate available days
                                    $total_days = $type['max_days'];
                                    $used_days = isset($leave_balances[$type['name']]) ? $leave_balances[$type['name']]['used'] : 0;
                                    $available_days = $total_days - $used_days;

                                    // Determine emoji based on leave type name
                                    $emoji = '📋';
                                    if (stripos($type['name'], 'casual') !== false)
                                        $emoji = '🏖️';
                                    elseif (stripos($type['name'], 'sick') !== false)
                                        $emoji = '🏥';
                                    elseif (stripos($type['name'], 'earned') !== false || stripos($type['name'], 'annual') !== false)
                                        $emoji = '✈️';
                                    elseif (stripos($type['name'], 'unpaid') !== false)
                                        $emoji = '💼';
                                    elseif (stripos($type['name'], 'maternity') !== false)
                                        $emoji = '👶';
                                    elseif (stripos($type['name'], 'paternity') !== false)
                                        $emoji = '👨‍👶';

                                    $display_text = $emoji . ' ' . htmlspecialchars($type['name']);
                                    if ($total_days > 0) {
                                        $display_text .= ' (' . $available_days . ' of ' . $total_days . ' days available)';
                                    }
                                    ?>
                                    <option value="<?php echo $type['id']; ?>"
                                        data-max-days="<?php echo $type['max_days']; ?>"
                                        data-available="<?php echo $available_days; ?>"
                                        data-color="<?php echo htmlspecialchars($type['color_code']); ?>"
                                        data-paid="<?php echo $type['paid'] ? 'yes' : 'no'; ?>">
                                        <?php echo $display_text; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Duration Section -->
                    <div
                        style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <i data-feather="calendar"
                                style="width: 18px; height: 18px; color: var(--accent-color);"></i>
                            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-primary);">
                                Duration</h4>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="fromDate"
                                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <i data-feather="arrow-right" style="width: 14px; height: 14px;"></i>
                                    <span>From Date</span>
                                    <span style="color: var(--status-lost);">*</span>
                                </label>
                                <input type="date" id="fromDate" required
                                    style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; transition: all 0.2s;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="toDate"
                                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <i data-feather="arrow-left" style="width: 14px; height: 14px;"></i>
                                    <span>To Date</span>
                                    <span style="color: var(--status-lost);">*</span>
                                </label>
                                <input type="date" id="toDate" required
                                    style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; transition: all 0.2s;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="totalDays"
                                style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i data-feather="hash" style="width: 14px; height: 14px;"></i>
                                <span>Total Days</span>
                            </label>
                            <div style="position: relative;">
                                <input type="text" id="totalDays" readonly placeholder="Auto-calculated"
                                    style="width: 100%; padding: 0.75rem 1rem; padding-left: 3rem; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; font-weight: 600; cursor: not-allowed;">
                                <div
                                    style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); width: 24px; height: 24px; background-color: var(--status-new); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                    <i data-feather="calendar" style="width: 14px; height: 14px; color: white;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reason Section -->
                    <div
                        style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <i data-feather="file-text"
                                style="width: 18px; height: 18px; color: var(--accent-color);"></i>
                            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-primary);">
                                Reason & Details</h4>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="leaveReason"
                                style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span>Reason for Leave</span>
                                <span style="color: var(--status-lost);">*</span>
                            </label>
                            <textarea id="leaveReason" rows="4"
                                placeholder="Please provide a detailed reason for your leave request..." required
                                style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; resize: vertical; transition: all 0.2s;"></textarea>
                            <small
                                style="color: var(--text-muted); font-size: 0.75rem; display: flex; align-items: center; gap: 0.25rem; margin-top: 0.5rem;">
                                <i data-feather="info" style="width: 12px; height: 12px;"></i>
                                <span>Provide a clear and detailed explanation for your leave request</span>
                            </small>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div
                        style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <i data-feather="phone" style="width: 18px; height: 18px; color: var(--accent-color);"></i>
                            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-primary);">
                                Contact Information</h4>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="contactDuringLeave"
                                style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i data-feather="smartphone" style="width: 14px; height: 14px;"></i>
                                <span>Contact Number During Leave</span>
                            </label>
                            <input type="tel" id="contactDuringLeave" placeholder="+91 XXXXX XXXXX"
                                style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; transition: all 0.2s;">
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="emergencyContact"
                                style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i data-feather="user" style="width: 14px; height: 14px;"></i>
                                <span>Emergency Contact Person</span>
                            </label>
                            <input type="text" id="emergencyContact" placeholder="Name and contact number"
                                style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: var(--font-main); font-size: 0.9rem; transition: all 0.2s;">
                        </div>
                    </div>

                    <!-- Attachment Section -->
                    <div
                        style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <i data-feather="paperclip"
                                style="width: 18px; height: 18px; color: var(--accent-color);"></i>
                            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-primary);">
                                Supporting Documents</h4>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="attachDocument" style="display: block; margin-bottom: 0.5rem;">
                                <span>Attach Document (if applicable)</span>
                            </label>
                            <div style="position: relative;">
                                <input type="file" id="attachDocument"
                                    style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-input); border: 1px dashed var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: var(--font-main); font-size: 0.85rem; cursor: pointer; transition: all 0.2s;">
                            </div>
                            <small
                                style="color: var(--text-muted); font-size: 0.75rem; display: flex; align-items: center; gap: 0.25rem; margin-top: 0.5rem;">
                                <i data-feather="info" style="width: 12px; height: 12px;"></i>
                                <span>Medical certificate for sick leave, invitation for events, etc.</span>
                            </small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"
                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.02) 0%, rgba(255, 255, 255, 0.01) 100%); border-top: 1px solid var(--border-color); padding: 1.5rem 2rem; gap: 1rem;">
                <button class="btn-secondary" id="cancelBtn"
                    style="flex: 1; padding: 0.85rem 1.5rem; font-size: 0.95rem;">
                    <i data-feather="x" style="width: 16px; height: 16px;"></i>
                    <span>Cancel</span>
                </button>
                <button class="btn-primary" id="submitLeaveBtn"
                    style="flex: 2; padding: 0.85rem 1.5rem; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(255, 255, 255, 0.15);">
                    <i data-feather="send" style="width: 16px; height: 16px;"></i>
                    <span>Submit Leave Request</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Leave Details Drawer -->
    <div class="drawer-overlay" id="leaveDrawer">
        <div class="drawer">
            <div class="drawer-header">
                <div>
                    <h3 class="drawer-title">Leave Details</h3>
                    <span class="drawer-subtitle">Casual Leave - 3 Days</span>
                </div>
                <button class="close-drawer" id="closeDrawerBtn"><i data-feather="x"></i></button>
            </div>
            <div class="drawer-body">
                <div class="detail-section">
                    <span class="detail-label">Leave Type</span>
                    <div class="detail-value">Casual Leave</div>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Duration</span>
                    <div class="detail-value">Dec 20, 2024 - Dec 22, 2024 (3 days)</div>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Status</span>
                    <span class="status-badge status-new">Pending Approval</span>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Applied On</span>
                    <div class="detail-value" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i data-feather="calendar" style="width: 16px;"></i>
                        <span>Dec 15, 2024 at 10:30 AM</span>
                    </div>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Reason</span>
                    <div class="detail-value">Family function - attending cousin's wedding ceremony in hometown.</div>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Contact During Leave</span>
                    <div class="detail-value">+91 98765 43210</div>
                </div>

                <div class="detail-section">
                    <span class="detail-label">Activity Timeline</span>
                    <div class="activity-timeline">
                        <div class="activity-item">
                            <span class="activity-date">Dec 15, 2024 - 10:30 AM</span>
                            <div class="activity-content">Leave request submitted.</div>
                        </div>
                        <div class="activity-item">
                            <span class="activity-date">Pending</span>
                            <div class="activity-content">Awaiting manager approval.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                <button class="btn-secondary" style="flex: 1;" id="cancelLeaveBtn">Cancel Request</button>
                <button class="btn-primary" style="flex: 1;" id="editLeaveBtn">Edit Request</button>
            </div>
        </div>
    </div>

    <script>
        // Initialize Feather Icons
        feather.replace();

        // Modal Logic
        const modal = document.getElementById('applyLeaveModal');
        const applyBtn = document.getElementById('applyLeaveBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const submitBtn = document.getElementById('submitLeaveBtn');

        function openModal() {
            modal.classList.add('active');
            // Set minimum date to 15 days ago
            const today = new Date();
            const fifteenDaysAgo = new Date(today);
            fifteenDaysAgo.setDate(today.getDate() - 15);
            const minDate = fifteenDaysAgo.toISOString().split('T')[0];
            document.getElementById('fromDate').setAttribute('min', minDate);
            document.getElementById('toDate').setAttribute('min', minDate);
        }

        function closeModal() {
            modal.classList.remove('active');
        }

        applyBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Calculate total days
        const fromDate = document.getElementById('fromDate');
        const toDate = document.getElementById('toDate');
        const totalDays = document.getElementById('totalDays');
        const leaveTypeSelect = document.getElementById('leaveType');

        function calculateDays() {
            if (fromDate.value && toDate.value) {
                const from = new Date(fromDate.value);
                const to = new Date(toDate.value);
                const diffTime = Math.abs(to - from);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

                if (diffDays > 0) {
                    totalDays.value = diffDays + (diffDays === 1 ? ' day' : ' days');

                    // Check if requested days exceed available balance
                    checkLeaveBalance(diffDays);
                } else {
                    totalDays.value = '';
                }
            }
        }

        function checkLeaveBalance(requestedDays) {
            const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const availableDays = parseInt(selectedOption.getAttribute('data-available'));
                const maxDays = parseInt(selectedOption.getAttribute('data-max-days'));

                // Remove any existing warning
                const existingWarning = document.getElementById('balanceWarning');
                if (existingWarning) {
                    existingWarning.remove();
                }

                if (maxDays > 0 && requestedDays > availableDays) {
                    // Create warning message
                    const warning = document.createElement('div');
                    warning.id = 'balanceWarning';
                    warning.style.cssText = 'margin-top: 0.75rem; padding: 0.75rem 1rem; background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;';
                    warning.innerHTML = `
                        <i data-feather="alert-triangle" style="width: 16px; height: 16px; color: var(--status-lost); flex-shrink: 0;"></i>
                        <span style="color: var(--status-lost); font-size: 0.85rem;">
                            <strong>Insufficient balance!</strong> You have only ${availableDays} day${availableDays !== 1 ? 's' : ''} available but requesting ${requestedDays} day${requestedDays !== 1 ? 's' : ''}.
                        </span>
                    `;

                    // Insert warning after total days field
                    totalDays.parentElement.parentElement.appendChild(warning);
                    feather.replace();
                } else if (maxDays > 0 && requestedDays > 0) {
                    // Show success message
                    const success = document.createElement('div');
                    success.id = 'balanceWarning';
                    success.style.cssText = 'margin-top: 0.75rem; padding: 0.75rem 1rem; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;';
                    success.innerHTML = `
                        <i data-feather="check-circle" style="width: 16px; height: 16px; color: var(--status-qualified); flex-shrink: 0;"></i>
                        <span style="color: var(--status-qualified); font-size: 0.85rem;">
                            Balance available: ${availableDays - requestedDays} day${(availableDays - requestedDays) !== 1 ? 's' : ''} remaining after this request.
                        </span>
                    `;

                    totalDays.parentElement.parentElement.appendChild(success);
                    feather.replace();
                }
            }
        }

        fromDate.addEventListener('change', () => {
            // Update toDate minimum to fromDate
            toDate.setAttribute('min', fromDate.value);
            calculateDays();
        });

        toDate.addEventListener('change', calculateDays);

        // Check balance when leave type changes
        leaveTypeSelect.addEventListener('change', () => {
            if (fromDate.value && toDate.value) {
                calculateDays();
            }
        });

        // Submit Leave via AJAX
        submitBtn.addEventListener('click', () => {
            const leaveType = document.getElementById('leaveType').value;
            const reason = document.getElementById('leaveReason').value;

            if (!leaveType || !fromDate.value || !toDate.value || !reason) {
                alert('Please fill in all required fields');
                return;
            }

            // Validate that start date is not older than 15 days from today
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const fifteenDaysAgo = new Date(today);
            fifteenDaysAgo.setDate(today.getDate() - 15);
            
            const selectedStartDate = new Date(fromDate.value);
            selectedStartDate.setHours(0, 0, 0, 0);
            
            if (selectedStartDate < fifteenDaysAgo) {
                alert('Leave start date cannot be more than 15 days in the past. Please select a date from ' + fifteenDaysAgo.toLocaleDateString() + ' onwards.');
                return;
            }

            // Check if there's a balance warning
            const balanceWarning = document.getElementById('balanceWarning');
            if (balanceWarning && balanceWarning.querySelector('[data-feather="alert-triangle"]')) {
                const confirmSubmit = confirm('You are requesting more days than your available balance. Do you still want to submit this request?');
                if (!confirmSubmit) {
                    return;
                }
            }

            // Disable submit button and show loading state
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-feather="loader" style="width: 16px; height: 16px; animation: spin 1s linear infinite;"></i><span>Submitting...</span>';
            feather.replace();

            // Check if this is an edit operation
            const editId = document.getElementById('applyLeaveForm').getAttribute('data-edit-id');
            const isEdit = editId && editId !== '';

            // Prepare form data
            const formData = new FormData();
            if (isEdit) {
                formData.append('leave_id', editId);
            }
            formData.append('leave_type', leaveType);
            formData.append('start_date', fromDate.value);
            formData.append('end_date', toDate.value);
            formData.append('reason', reason);
            formData.append('contact_during_leave', document.getElementById('contactDuringLeave').value);
            formData.append('emergency_contact', document.getElementById('emergencyContact').value);
            formData.append('duration_type', 'full_day');

            // Add file if selected (only for new requests)
            if (!isEdit) {
                const fileInput = document.getElementById('attachDocument');
                if (fileInput.files.length > 0) {
                    formData.append('attachment', fileInput.files[0]);
                }
            }

            // Determine API endpoint
            const apiEndpoint = isEdit ? 'api_update_leave.php' : 'api_submit_leave.php';

            // Submit via AJAX
            fetch(apiEndpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                feather.replace();

                if (data.success) {
                    alert(data.message || (isEdit ? 'Leave request updated successfully!' : 'Leave request submitted successfully!'));
                    closeModal();
                    document.getElementById('applyLeaveForm').reset();
                    document.getElementById('applyLeaveForm').removeAttribute('data-edit-id');
                    totalDays.value = '';

                    // Remove warning if exists
                    const warning = document.getElementById('balanceWarning');
                    if (warning) warning.remove();

                    // Reset submit button text
                    submitBtn.innerHTML = '<i data-feather="send" style="width: 16px; height: 16px;"></i><span>Submit Leave Request</span>';
                    feather.replace();

                    // Save current filter state before reload
                    const currentMonth = document.getElementById('monthFilter').value;
                    const currentYear = document.getElementById('yearFilter').value;
                    const currentStatus = document.getElementById('statusFilter').value;
                    const currentType = document.getElementById('typeFilter').value;
                    
                    localStorage.setItem('leave_filter_month', currentMonth);
                    localStorage.setItem('leave_filter_year', currentYear);
                    localStorage.setItem('leave_filter_status', currentStatus);
                    localStorage.setItem('leave_filter_type', currentType);

                    // Reload page to update stats
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert(data.message || 'Failed to submit leave request. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error submitting leave request:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                feather.replace();
                alert('An error occurred while submitting your request. Please try again.');
            });
        });

        // Drawer Logic
        const drawer = document.getElementById('leaveDrawer');
        const closeDrawerBtn = document.getElementById('closeDrawerBtn');
        const actionBtns = document.querySelectorAll('.action-btn');

        function openDrawer() {
            drawer.classList.add('active');
        }

        function closeDrawer() {
            drawer.classList.remove('active');
        }

        // Add click event to all action buttons
        actionBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                openDrawer();
            });
        });

        closeDrawerBtn.addEventListener('click', closeDrawer);

        // Close drawer on outside click
        drawer.addEventListener('click', (e) => {
            if (e.target === drawer) {
                closeDrawer();
            }
        });

        // Theme Toggle Logic
        const themeToggleBtn = document.getElementById('themeToggle');
        themeToggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('light-mode');

            // Update icon
            if (document.body.classList.contains('light-mode')) {
                themeToggleBtn.innerHTML = '<i data-feather="moon"></i>';
            } else {
                themeToggleBtn.innerHTML = '<i data-feather="sun"></i>';
            }
            feather.replace();
        });

        // Notification Logic
        const notifBtn = document.getElementById('notificationBtn');
        const notifDropdown = document.getElementById('notificationDropdown');

        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('active');
        });

        // Close notification on outside click
        document.addEventListener('click', (e) => {
            if (notifDropdown.classList.contains('active')) {
                if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                    notifDropdown.classList.remove('active');
                }
            }
        });

        // Load Leave Requests Function
        function loadLeaveRequests(filters = {}) {
            const tbody = document.getElementById('leaveRequestsTableBody');

            // Build query string
            const params = new URLSearchParams();
            if (filters.status) params.append('status', filters.status);
            if (filters.type) params.append('type', filters.type);
            if (filters.start_date) params.append('start_date', filters.start_date);
            if (filters.end_date) params.append('end_date', filters.end_date);

            fetch(`api_get_leave_requests.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        tbody.innerHTML = '';

                        if (data.data.length === 0) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 3rem;">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                                            <i data-feather="inbox" style="width: 48px; height: 48px; color: var(--text-muted);"></i>
                                            <span style="color: var(--text-muted); font-size: 1.1rem;">No leave requests found</span>
                                            <button class="btn-primary" onclick="document.getElementById('applyLeaveBtn').click()" style="margin-top: 0.5rem;">
                                                <i data-feather="plus" style="width: 16px; height: 16px;"></i>
                                                <span>Apply for Leave</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                            feather.replace();
                            return;
                        }

                        data.data.forEach(request => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>
                                    <div class="lead-info">
                                        <div class="lead-avatar" style="background-color: ${request.color_code}20; color: ${request.color_code || '#60a5fa'};">
                                            <i data-feather="calendar" style="width: 16px; height: 16px;"></i>
                                        </div>
                                        <div>
                                            <span class="lead-name">${request.leave_type_name || 'Unknown'}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>${request.start_date_formatted}</td>
                                <td>${request.end_date_formatted}</td>
                                <td>${request.duration_display}</td>
                                <td><span class="status-badge ${request.status_class}">${request.status_display}</span></td>
                                <td>${request.created_at_formatted}</td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${request.reason || '-'}</td>
                                <td>
                                    <button class="action-btn" title="View Details" onclick="viewLeaveDetails(${request.id})">
                                        <i data-feather="eye"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });

                        feather.replace();
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--status-lost);">
                                    <i data-feather="alert-circle" style="width: 24px; height: 24px;"></i>
                                    <span>Error loading leave requests</span>
                                </td>
                            </tr>
                        `;
                        feather.replace();
                    }
                })
                .catch(error => {
                    console.error('Error loading leave requests:', error);
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: var(--status-lost);">
                                <i data-feather="alert-circle" style="width: 24px; height: 24px;"></i>
                                <span>Failed to load leave requests</span>
                            </td>
                        </tr>
                    `;
                    feather.replace();
                });
        }

        // Restore filter state from localStorage if it exists
        const savedMonth = localStorage.getItem('leave_filter_month');
        const savedYear = localStorage.getItem('leave_filter_year');
        const savedStatus = localStorage.getItem('leave_filter_status');
        const savedType = localStorage.getItem('leave_filter_type');
        
        let filtersRestored = false;
        
        if (savedMonth !== null) {
            document.getElementById('monthFilter').value = savedMonth;
            localStorage.removeItem('leave_filter_month');
            filtersRestored = true;
        }
        if (savedYear !== null) {
            document.getElementById('yearFilter').value = savedYear;
            localStorage.removeItem('leave_filter_year');
            filtersRestored = true;
        }
        if (savedStatus !== null) {
            document.getElementById('statusFilter').value = savedStatus;
            localStorage.removeItem('leave_filter_status');
            filtersRestored = true;
        }
        if (savedType !== null) {
            document.getElementById('typeFilter').value = savedType;
            localStorage.removeItem('leave_filter_type');
            filtersRestored = true;
        }

        // Filter handlers
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
        document.getElementById('typeFilter').addEventListener('change', applyFilters);
        document.getElementById('monthFilter').addEventListener('change', applyFilters);
        document.getElementById('yearFilter').addEventListener('change', applyFilters);

        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;

            const filters = {};
            if (status) filters.status = status;
            if (type) filters.type = type;

            // Calculate start and end dates from month and year
            if (month && year) {
                // Both month and year selected
                const lastDay = new Date(year, month, 0).getDate();
                filters.start_date = `${year}-${month}-01`;
                filters.end_date = `${year}-${month}-${lastDay}`;
            } else if (year) {
                // Only year selected - show entire year
                filters.start_date = `${year}-01-01`;
                filters.end_date = `${year}-12-31`;
            } else if (month) {
                // Only month selected - use current year
                const currentYear = new Date().getFullYear();
                const lastDay = new Date(currentYear, month, 0).getDate();
                filters.start_date = `${currentYear}-${month}-01`;
                filters.end_date = `${currentYear}-${month}-${lastDay}`;
            }
            // If neither month nor year selected, API will default to current month

            loadLeaveRequests(filters);
        }

        // Load leave requests on page load with restored filters or default
        if (filtersRestored) {
            applyFilters(); // Apply the restored filters
        } else {
            loadLeaveRequests(); // Load with default (current month)
        }

        // View leave details function
        window.viewLeaveDetails = function(leaveId) {
            // Fetch leave details from API
            fetch(`api_get_leave_detail.php?id=${leaveId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const leave = data.data;
                        
                        // Update drawer title
                        document.querySelector('.drawer-title').textContent = 'Leave Details';
                        document.querySelector('.drawer-subtitle').textContent = `${leave.leave_type_name} - ${leave.duration_display}`;
                        
                        // Update drawer body
                        const drawerBody = document.querySelector('.drawer-body');
                        drawerBody.innerHTML = `
                            <div class="detail-section">
                                <span class="detail-label">LEAVE TYPE</span>
                                <div class="detail-value">${leave.leave_type_name}</div>
                            </div>

                            <div class="detail-section">
                                <span class="detail-label">DURATION</span>
                                <div class="detail-value">${leave.start_date_formatted} - ${leave.end_date_formatted} (${leave.duration_display})</div>
                            </div>

                            <div class="detail-section">
                                <span class="detail-label">STATUS</span>
                                <span class="status-badge ${leave.status_class}">${leave.status_display}</span>
                            </div>

                            <div class="detail-section">
                                <span class="detail-label">APPLIED ON</span>
                                <div class="detail-value" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i data-feather="calendar" style="width: 16px;"></i>
                                    <span>${leave.created_at_formatted}</span>
                                </div>
                            </div>

                            <div class="detail-section">
                                <span class="detail-label">REASON</span>
                                <div class="detail-value">${leave.reason || 'No reason provided'}</div>
                            </div>

                            ${leave.action_reason ? `
                            <div class="detail-section">
                                <span class="detail-label">ACTION COMMENTS</span>
                                <div class="detail-value">${leave.action_reason}</div>
                            </div>
                            ` : ''}

                            <div class="detail-section">
                                <span class="detail-label">ACTIVITY TIMELINE</span>
                                <div class="activity-timeline">
                                    ${leave.timeline.map(item => `
                                        <div class="activity-item">
                                            <span class="activity-date">${item.date}</span>
                                            <div class="activity-content">${item.action}</div>
                                            ${item.reason ? `<div class="activity-content" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">${item.reason}</div>` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                        
                        // Update footer buttons based on status
                        const drawerFooter = document.querySelector('.drawer-footer');
                        if (leave.status === 'pending') {
                            drawerFooter.innerHTML = `
                                <button class="btn-secondary" style="flex: 1;" onclick="cancelLeaveRequest(${leave.id})">Cancel Request</button>
                                <button class="btn-primary" style="flex: 1;" onclick="editLeaveRequest(${leave.id})">Edit Request</button>
                            `;
                        } else {
                            drawerFooter.innerHTML = `
                                <button class="btn-secondary" style="flex: 1;" onclick="closeDrawer()">Close</button>
                            `;
                        }
                        
                        feather.replace();
                        openDrawer();
                    } else {
                        alert(data.message || 'Failed to load leave details');
                    }
                })
                .catch(error => {
                    console.error('Error loading leave details:', error);
                    alert('Failed to load leave details. Please try again.');
                });
        };

        // Cancel leave request function
        window.cancelLeaveRequest = function(leaveId) {
            if (confirm('Are you sure you want to cancel this leave request? This action cannot be undone.')) {
                // Show loading state
                const drawerFooter = document.querySelector('.drawer-footer');
                const originalFooterHTML = drawerFooter.innerHTML;
                drawerFooter.innerHTML = '<div style="text-align: center; padding: 1rem; color: var(--text-muted);">Cancelling...</div>';
                
                // Prepare form data
                const formData = new FormData();
                formData.append('leave_id', leaveId);
                
                // Submit cancellation request
                fetch('api_cancel_leave.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Leave request cancelled successfully');
                        closeDrawer();
                        
                        // Save current filter state before reload
                        const currentMonth = document.getElementById('monthFilter').value;
                        const currentYear = document.getElementById('yearFilter').value;
                        const currentStatus = document.getElementById('statusFilter').value;
                        const currentType = document.getElementById('typeFilter').value;
                        
                        localStorage.setItem('leave_filter_month', currentMonth);
                        localStorage.setItem('leave_filter_year', currentYear);
                        localStorage.setItem('leave_filter_status', currentStatus);
                        localStorage.setItem('leave_filter_type', currentType);
                        
                        // Reload page to update stats
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert(data.message || 'Failed to cancel leave request');
                        drawerFooter.innerHTML = originalFooterHTML;
                    }
                })
                .catch(error => {
                    console.error('Error cancelling leave:', error);
                    alert('An error occurred while cancelling the request');
                    drawerFooter.innerHTML = originalFooterHTML;
                });
            }
        };

        // Edit leave request function
        window.editLeaveRequest = function(leaveId) {
            // Fetch leave details first
            fetch(`api_get_leave_detail.php?id=${leaveId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const leave = data.data;
                        
                        // Close drawer
                        closeDrawer();
                        
                        // Open modal
                        setTimeout(() => {
                            openModal();
                            
                            // Pre-fill form with existing data
                            document.getElementById('leaveType').value = leave.leave_type;
                            document.getElementById('fromDate').value = leave.start_date;
                            document.getElementById('toDate').value = leave.end_date;
                            document.getElementById('leaveReason').value = leave.reason;
                            
                            // Calculate and display total days
                            calculateDays();
                            
                            // Store leave ID for update
                            document.getElementById('applyLeaveForm').setAttribute('data-edit-id', leaveId);
                            
                            // Change submit button text
                            submitBtn.innerHTML = '<i data-feather="save" style="width: 16px; height: 16px;"></i><span>Update Leave Request</span>';
                            feather.replace();
                        }, 300);
                    } else {
                        alert(data.message || 'Failed to load leave details');
                    }
                })
                .catch(error => {
                    console.error('Error loading leave for edit:', error);
                    alert('Failed to load leave details for editing');
                });
        };


        // Load Sidebar
        fetch('sidebar.html')
            .then(response => response.text())
            .then(html => {
                document.getElementById('sidebarContainer').innerHTML = html;
                feather.replace(); // Re-init icons for sidebar

                // Update user profile data from window variables
                if (window.currentUsername) {
                    const usernameEl = document.getElementById('sidebarUsername');
                    const roleEl = document.getElementById('sidebarUserRole');
                    const initialsEl = document.getElementById('userInitials');
                    const profileImg = document.getElementById('profileImg');

                    if (usernameEl) {
                        usernameEl.textContent = window.currentUsername;
                    }

                    if (roleEl) {
                        roleEl.textContent = window.userRole || 'Employee';
                    }

                    if (initialsEl) {
                        // Generate initials
                        const names = window.currentUsername.split(' ');
                        const initials = names.map(n => n.charAt(0).toUpperCase()).join('');
                        initialsEl.textContent = initials || 'U';
                    }

                    // Load profile picture if available
                    if (profileImg && window.profileImageUrl && window.profileImageUrl.trim() !== '') {
                        profileImg.src = window.profileImageUrl;
                        profileImg.style.display = 'block';
                        if (initialsEl) {
                            initialsEl.style.display = 'none';
                        }
                    }
                }

                // Sidebar Toggle Logic
                const sidebar = document.getElementById('sidebarContainer');
                if (typeof feather !== 'undefined') feather.replace();

                // Highlight active link (Leaves)
                setTimeout(() => {
                    const links = sidebar.querySelectorAll('.nav-link');
                    links.forEach(link => {
                        const href = link.getAttribute('href');
                        if (href === 'leaves.php') {
                            link.classList.add('active');
                        }
                    });
                }, 100);

                const toggleBtn = document.getElementById('sidebarToggle');
                const mobileMenuBtn = document.getElementById('mobileMenuBtn');
                const sidebarOverlay = document.getElementById('sidebarOverlay');

                // Desktop Collapse
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        sidebar.classList.toggle('collapsed');
                    });
                }

                // Mobile Open
                if (mobileMenuBtn) {
                    mobileMenuBtn.addEventListener('click', () => {
                        sidebar.classList.add('mobile-open');
                        sidebarOverlay.classList.add('active');
                    });
                }

                // Mobile Close (Overlay Click)
                if (sidebarOverlay) {
                    sidebarOverlay.addEventListener('click', () => {
                        sidebar.classList.remove('mobile-open');
                        sidebarOverlay.classList.remove('active');
                    });
                }
            })
            .catch(err => console.error('Error loading sidebar:', err));

    </script>
</body>

</html>