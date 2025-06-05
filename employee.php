<?php
session_start();
require_once 'config.php';

// Check authentication and role permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR', 'Senior Manager (Studio)'])) {
    header('Location: unauthorized.php');
    exit();
}

// Add role-based visibility restrictions
$isHR = $_SESSION['role'] === 'HR';
$isSeniorManager = $_SESSION['role'] === 'Senior Manager (Studio)';

// Modified query - both HR and Senior Manager can see all employees
$query = "
    SELECT * FROM users 
    WHERE deleted_at IS NULL 
    ORDER BY username ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$employees = $stmt->fetchAll();

// Fetch all senior managers
$managerQuery = "SELECT DISTINCT reporting_manager 
                 FROM users 
                 WHERE reporting_manager LIKE 'Sr. Manager%'
                 AND deleted_at IS NULL 
                 ORDER BY reporting_manager ASC";
$managerStmt = $pdo->prepare($managerQuery);
$managerStmt->execute();
$managers = $managerStmt->fetchAll(PDO::FETCH_ASSOC);

// Group managers by their position
$groupedManagers = [];
foreach ($managers as $manager) {
    $position = $manager['reporting_manager'];
    if (!isset($groupedManagers[$position])) {
        $groupedManagers[$position] = [];
    }
    $groupedManagers[$position][] = $manager;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* Root Variables */
        :root {
            --primary-color: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #7C3AED;
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

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
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
            font-family: 'Inter', sans-serif;
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
            color: var(--text-light);
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
            background-color: rgba(79, 70, 229, 0.1);
        }

        .nav-link.active {
            background-color: rgba(79, 70, 229, 0.1);
            font-weight: 500;
        }

        /* Update icon color on hover and active */
        .nav-link:hover i,
        .nav-link.active i {
            color: var(--primary-color);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout button styles */
        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            color: white !important;
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
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

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
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Layout */
        .container {
            max-width: 100%;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        /* Header Styles */
        .page-header {
            margin-bottom: var(--spacing-lg);
        }

        h2 {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Search Bar */
        .search-bar {
            margin: 1.5rem 0;
            background: white;
            border-radius: 12px;
            padding: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .search-input::placeholder {
            color: #9CA3AF;
        }

        /* Employee Card */
        .employee-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        /* Employee Header */
        .employee-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .profile-image {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            object-fit: cover;
            background: #F3F4FF;
        }

        .employee-details h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }

        .employee-details p {
            color: #6B7280;
            font-size: 0.875rem;
        }

        /* Detail Sections */
        .detail-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #4F46E5;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-grid {
            display: grid;
            gap: 1rem;
        }

        .info-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-group label {
            display: block;
            font-size: 0.75rem;
            color: #6B7280;
            margin-bottom: 0.25rem;
        }

        .info-value {
            background: #F9FAFB;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #1a1a1a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .edit-icon {
            flex-shrink: 0;
        }

        .info-value .edit-icon {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .info-value:hover .edit-icon {
            opacity: 1;
        }

        .edit-icon {
            color: #00b894;
            cursor: pointer;
            font-size: 14px;
            padding: 5px;
        }

        .edit-icon:hover {
            color: #009677;
        }

        @media (max-width: 768px) {
            .info-row {
                grid-template-columns: 1fr;
            }

            .info-value .edit-icon {
                opacity: 1;
            }
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            display: inline-block;
            margin-top: 5px;
        }

        .status-active {
            color: #059669;
            background: rgba(5, 150, 105, 0.1);
        }

        .status-inactive {
            color: #DC2626;
            background: rgba(220, 38, 38, 0.1);
        }

        /* Edit Icons */
        .edit-icon {
            color: var(--primary-color);
            cursor: pointer;
            padding: 5px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .edit-icon:hover {
            color: var(--primary-dark);
            transform: scale(1.1);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            position: relative;
            background: var(--bg-white);
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-close {
            position: absolute;
            right: 15px;
            top: 15px;
            cursor: pointer;
            font-size: 1.2em;
            color: var(--text-light);
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--text-dark);
        }

        /* Form Elements */
        .edit-form input,
        .edit-form select {
            width: 100%;
            padding: 10px 15px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .edit-form input:focus,
        .edit-form select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1);
        }

        .save-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s ease;
            width: 100%;
            margin-top: var(--spacing-md);
        }

        .save-btn:hover {
            background: var(--primary-dark);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .employee-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .employee-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .employee-card {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background-color: #fff;
            margin: 10px 0;
        }

        .form-select:focus {
            border-color: #00b894;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1);
        }

        optgroup {
            font-weight: 600;
            color: #00b894;
        }

        option {
            color: #333;
            padding: 8px;
        }

        label[for="positionSelect"] {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 14px;
        }

        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        .notification.success {
            border-left: 4px solid #00b894;
        }

        .notification.error {
            border-left: 4px solid #ff7675;
        }

        .notification strong {
            display: block;
            margin-bottom: 5px;
            color: #2d3436;
        }

        .notification p {
            margin: 0;
            color: #636e72;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Update loading state styles */
        .save-btn:disabled {
            background: #b2bec3;
            cursor: not-allowed;
        }

        #managerSelect {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background-color: #fff;
            margin: 10px 0;
        }

        #managerSelect:focus {
            border-color: #00b894;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1);
        }

        #managerSelect option {
            padding: 8px;
        }

        .manager-title {
            color: #666;
            font-size: 0.9em;
            margin-left: 5px;
        }

        #managerSelect optgroup {
            font-weight: 600;
            color: #00b894;
            padding: 8px 0;
        }

        #managerSelect option {
            padding: 8px;
            color: #333;
            font-weight: normal;
        }

        .manager-group-title {
            color: #00b894;
            font-weight: 600;
            margin-top: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        /* Debug styles */
        .employee-card:not([data-employee-id]) {
            border: 2px solid red !important;
        }

        .info-group:not([data-field]) {
            border: 2px solid orange !important;
        }

        /* Ensure value-display spans are block-level */
        .value-display {
            display: block;
            width: 100%;
        }

        /* Add transition for smooth updates */
        .info-value {
            transition: background-color 0.3s ease;
        }

        .info-value.updating {
            background-color: rgba(0, 184, 148, 0.1);
        }

        /* Updated Button Styles */
        .btn-view-more {
            width: 100%;
            margin-top: 1.5rem;
            padding: 0.75rem;
            background: #4F46E5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-view-more:hover {
            background: #4338CA;
        }

        /* Updated Modal Styles */
        .modal-content {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 30px;
            max-width: 550px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        /* Date Input Styles */
        input[type="date"] {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background-color: #fff;
            margin: 10px 0;
        }

        input[type="date"]:focus {
            border-color: #00b894;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1);
        }

        /* Calendar Icon Styling */
        input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            padding: 5px;
            filter: invert(60%) sepia(98%) saturate(480%) hue-rotate(121deg) brightness(87%) contrast(87%);
        }

        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 0.7;
        }

        .status-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: #4F46E5;
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #4F46E5;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .slider.round {
            border-radius: 34px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        .status-text {
            font-size: 14px;
            font-weight: 500;
        }

        .role-indicator {
            margin: 1rem 0;
        }

        .badge-hr {
            background-color: #4F46E5;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Hide edit controls for unauthorized users */
        .employee-card[data-self="true"] .edit-icon {
            display: none;
        }

        .access-note {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }

        /* Modify the badge-manager style to be more distinct */
        .badge-manager {
            background-color: #3b82f6;
            color: white;
        }

        .status-date {
            display: block;
            font-size: 0.75rem;
            color: #6B7280;
            margin-top: 0.25rem;
        }

        /* Custom date picker in status modal */
        #statusDateModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1100;
            backdrop-filter: blur(4px);
        }

        .status-date-modal-content {
            position: relative;
            background: white;
            width: 90%;
            max-width: 400px;
            margin: 100px auto;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .status-date-modal-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
        }

        .status-date-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .status-date-buttons {
            display: flex;
            justify-content: space-between;
        }

        .btn-cancel, .btn-confirm {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-confirm {
            background: #4F46E5;
            color: white;
            border: none;
        }

        .btn-confirm:hover {
            background: #4338CA;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
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
            <a href="employee.php" class="nav-link active">
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
        <div class="container">
            <h2>Employee Management</h2>
            <?php if ($isHR): ?>
            <div class="role-indicator">
                <span class="badge badge-hr">HR Access</span>
            </div>
            <?php elseif ($isSeniorManager): ?>
            <div class="role-indicator">
                <span class="badge badge-manager">Studio Manager Access</span>
                <p class="access-note">View-only access for most operations</p>
            </div>
            <?php endif; ?>

            <div class="search-bar">
                <input type="text" id="searchInput" class="search-input" 
                       placeholder="Search by name, employee ID, designation...">
            </div>

            <div class="employee-grid">
                <?php foreach ($employees as $employee): ?>
                    <div class="employee-card" data-employee-id="<?php echo $employee['id']; ?>">
                        <div class="employee-header">
                            <img src="<?php echo $employee['profile_picture'] ?? 'default-avatar.png'; ?>" 
                                 alt="Profile" class="profile-image">
                            <div class="employee-details">
                                <h3><?php echo htmlspecialchars($employee['username']); ?></h3>
                                <p><?php echo htmlspecialchars($employee['designation']); ?></p>
                                <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                <div class="status-container">
                                    <label class="switch">
                                        <input type="checkbox" 
                                               class="status-toggle" 
                                               data-employee-id="<?php echo $employee['id']; ?>"
                                               <?php echo (strtolower($employee['status']) === 'active' || $employee['status'] == 1) ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                    <span class="status-text status-<?php echo strtolower($employee['status']); ?>">
                                        <?php echo ucfirst(strtolower($employee['status'])); ?>
                                    </span>
                                    <?php if (!empty($employee['status_changed_date'])): ?>
                                    <small class="status-date">Changed: <?php echo date('d M Y', strtotime($employee['status_changed_date'])); ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="detail-section">
                            <div class="section-title">Basic Information</div>
                            <div class="detail-grid">
                                <span class="label">Employee ID:</span>
                                <span class="value"><?php echo htmlspecialchars($employee['unique_id']); ?></span>
                                <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                <i class="fas fa-pencil edit-icon" onclick="editField('unique_id', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['unique_id']); ?>')"></i>
                                <?php endif; ?>
                                
                                <span class="label">Email:</span>
                                <span class="value"><?php echo htmlspecialchars($employee['email']); ?></span>
                                <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                <i class="fas fa-pencil edit-icon" onclick="editField('email', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['email']); ?>')"></i>
                                <?php endif; ?>
                                
                                <span class="label">Phone:</span>
                                <span class="value"><?php echo htmlspecialchars($employee['phone']); ?></span>
                                <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                <i class="fas fa-pencil edit-icon" onclick="editField('phone', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['phone']); ?>')"></i>
                                <?php endif; ?>
                                
                                <span class="label">DOB:</span>
                                <span class="value"><?php echo $employee['dob'] ? date('d M Y', strtotime($employee['dob'])) : '-'; ?></span>
                                <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                <i class="fas fa-pencil edit-icon" onclick="editField('dob', '<?php echo $employee['id']; ?>', '<?php echo $employee['dob']; ?>')"></i>
                                <?php endif; ?>
                                
                                <span class="label">Gender:</span>
                                <span class="value"><?php echo htmlspecialchars($employee['gender']); ?></span>
                                <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                <i class="fas fa-pencil edit-icon" onclick="editField('gender', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['gender']); ?>')"></i>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="detail-section work-info">
                            <div class="section-title">
                                <i class="fas fa-briefcase"></i>
                                Work Information
                            </div>
                            <div class="info-grid">
                                <div class="info-row">
                                    <div class="info-group" data-field="position">
                                        <label>Position</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['position']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('position', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['position']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-group" data-field="designation">
                                        <label>Department</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['designation']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('designation', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['designation']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-group" data-field="joining_date">
                                        <label>Joining Date</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo $employee['joining_date'] ? date('d M Y', strtotime($employee['joining_date'])) : 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('joining_date', '<?php echo $employee['id']; ?>', '<?php echo $employee['joining_date']; ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-group" data-field="reporting_manager">
                                        <label>Reporting To</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['reporting_manager']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('reporting_manager', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['reporting_manager']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section contact-info">
                            <div class="section-title">
                                <i class="fas fa-address-card"></i>
                                Contact Information
                            </div>
                            <div class="info-grid">
                                <div class="info-row">
                                    <div class="info-group" data-field="address">
                                        <label>Address</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo !empty($employee['address']) ? htmlspecialchars($employee['address']) : ''; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('address', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['address'] ?? ''); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-group" data-field="city">
                                        <label>City</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['city']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('city', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['city']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-group" data-field="state">
                                        <label>State</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['state']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('state', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['state']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-group" data-field="country">
                                        <label>Country</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['country']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('country', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['country']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-group" data-field="postal_code">
                                        <label>Postal Code</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['postal_code']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('postal_code', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['postal_code']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-section emergency-contact">
                            <div class="section-title">
                                <i class="fas fa-phone-alt"></i>
                                Emergency Contact
                            </div>
                            <div class="info-grid">
                                <div class="info-row">
                                    <div class="info-group" data-field="emergency_contact_name">
                                        <label>Name</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['emergency_contact_name']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                            <i class="fas fa-pencil edit-icon" onclick="editField('emergency_contact_name', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['emergency_contact_name']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-group" data-field="emergency_contact_phone">
                                        <label>Phone</label>
                                        <div class="info-value">
                                            <span class="value-display"><?php echo htmlspecialchars($employee['emergency_contact_phone']) ?: 'Not Set'; ?></span>
                                            <?php if ($isHR || ($isSeniorManager && $employee['id'] !== $_SESSION['user_id'])): ?>
                                                <i class="fas fa-pencil edit-icon" onclick="editField('emergency_contact_phone', '<?php echo $employee['id']; ?>', '<?php echo htmlspecialchars($employee['emergency_contact_phone']); ?>')"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button onclick="viewFullProfile(<?php echo $employee['id']; ?>)" class="btn-view-more">
                            View Full Profile
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h3>Edit Information</h3>
            <form id="editForm" class="edit-form">
                <input type="hidden" id="employeeId" name="employeeId">
                <input type="hidden" id="fieldName" name="fieldName">
                <div id="fieldInput"></div>
                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="statusDateModal" class="modal">
        <div class="status-date-modal-content">
            <div class="status-date-modal-title">Set Status Change Date</div>
            <input type="date" id="customStatusDate" class="status-date-input" max="<?php echo date('Y-m-d'); ?>">
            <div class="status-date-buttons">
                <button class="btn-cancel" onclick="cancelStatusChange()">Cancel</button>
                <button class="btn-confirm" onclick="confirmStatusChange()">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.employee-card').forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // View full profile function
        function viewFullProfile(employeeId) {
            window.location.href = `employee-detail.php?id=${employeeId}`;
        }

        // Add role information to JavaScript
        const userRole = '<?php echo $_SESSION['role']; ?>';
        const userId = '<?php echo $_SESSION['user_id']; ?>';

        // Modify edit field function to restrict Senior Manager's edit capabilities
        function editField(fieldName, employeeId, currentValue) {
            if (userRole === 'Senior Manager (Studio)') {
                // Senior Manager can only edit their direct reports
                const employeeCard = document.querySelector(`.employee-card[data-employee-id="${employeeId}"]`);
                const reportingManager = employeeCard.querySelector('[data-field="reporting_manager"] .value-display').textContent;
                
                if (reportingManager !== '<?php echo $_SESSION['username']; ?>') {
                    showNotification('Access Denied', 'You can only edit information for your direct reports.', 'error');
                    return;
                }
            }

            const modal = document.getElementById('editModal');
            const fieldInput = document.getElementById('fieldInput');
            document.getElementById('employeeId').value = employeeId;
            document.getElementById('fieldName').value = fieldName;

            let inputHtml = '';
            
            if (fieldName === 'designation') {
                inputHtml = `
                    <label for="departmentSelect">Select Department</label>
                    <select name="value" id="departmentSelect" class="form-select" required>
                        <optgroup label="Architecture">
                            <option value="Architecture Design" ${currentValue === 'Architecture Design' ? 'selected' : ''}>Architecture Design</option>
                            <option value="Interior Design" ${currentValue === 'Interior Design' ? 'selected' : ''}>Interior Design</option>
                            <option value="Landscape Design" ${currentValue === 'Landscape Design' ? 'selected' : ''}>Landscape Design</option>
                            <option value="Urban Planning" ${currentValue === 'Urban Planning' ? 'selected' : ''}>Urban Planning</option>
                            <option value="BIM Department" ${currentValue === 'BIM Department' ? 'selected' : ''}>BIM Department</option>
                            <option value="3D Visualization" ${currentValue === '3D Visualization' ? 'selected' : ''}>3D Visualization</option>
                        </optgroup>
                        <optgroup label="Construction">
                            <option value="Project Management" ${currentValue === 'Project Management' ? 'selected' : ''}>Project Management</option>
                            <option value="Site Operations" ${currentValue === 'Site Operations' ? 'selected' : ''}>Site Operations</option>
                            <option value="Civil Engineering" ${currentValue === 'Civil Engineering' ? 'selected' : ''}>Civil Engineering</option>
                            <option value="Structural Engineering" ${currentValue === 'Structural Engineering' ? 'selected' : ''}>Structural Engineering</option>
                            <option value="MEP Engineering" ${currentValue === 'MEP Engineering' ? 'selected' : ''}>MEP Engineering</option>
                            <option value="Quality Control" ${currentValue === 'Quality Control' ? 'selected' : ''}>Quality Control</option>
                            <option value="Safety Management" ${currentValue === 'Safety Management' ? 'selected' : ''}>Safety Management</option>
                            <option value="Quantity Surveying" ${currentValue === 'Quantity Surveying' ? 'selected' : ''}>Quantity Surveying</option>
                        </optgroup>
                        <optgroup label="Support & Administration">
                            <option value="Human Resources" ${currentValue === 'Human Resources' ? 'selected' : ''}>Human Resources</option>
                            <option value="Finance" ${currentValue === 'Finance' ? 'selected' : ''}>Finance</option>
                            <option value="Administration" ${currentValue === 'Administration' ? 'selected' : ''}>Administration</option>
                            <option value="IT Support" ${currentValue === 'IT Support' ? 'selected' : ''}>IT Support</option>
                            <option value="Document Control" ${currentValue === 'Document Control' ? 'selected' : ''}>Document Control</option>
                            <option value="Procurement" ${currentValue === 'Procurement' ? 'selected' : ''}>Procurement</option>
                            <option value="Legal" ${currentValue === 'Legal' ? 'selected' : ''}>Legal</option>
                        </optgroup>
                    </select>
                `;
            } else if (fieldName === 'joining_date' || fieldName === 'dob') {
                // Shared handling for both joining date and DOB with date picker
                const labelText = fieldName === 'joining_date' ? 'Select Joining Date' : 'Select Date of Birth';
                inputHtml = `
                    <label for="${fieldName}">${labelText}</label>
                    <input 
                        type="date" 
                        id="${fieldName}" 
                        name="value" 
                        value="${currentValue ? currentValue.split(' ')[0] : ''}"
                        class="form-select"
                        ${fieldName === 'dob' ? 'max="' + new Date().toISOString().split('T')[0] + '"' : ''}
                        required
                    >
                `;
            } else if (fieldName === 'reporting_manager') {
                inputHtml = `
                    <label for="managerSelect">Select Reporting Manager</label>
                    <select name="value" id="managerSelect" class="form-select" required>
                        <option value="">Select Manager</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?php echo htmlspecialchars($manager['reporting_manager']); ?>"
                                <?php echo (trim($currentValue) === trim($manager['reporting_manager'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($manager['reporting_manager']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                `;
            } else if (fieldName === 'position') {
                inputHtml = `
                    <label for="positionSelect">Select Position</label>
                    <select name="value" id="positionSelect" class="form-select" required>
                        <optgroup label="Architecture">
                            <option value="Principal Architect" ${currentValue === 'Principal Architect' ? 'selected' : ''}>Principal Architect</option>
                            <option value="Senior Architect" ${currentValue === 'Senior Architect' ? 'selected' : ''}>Senior Architect</option>
                            <option value="Project Architect" ${currentValue === 'Project Architect' ? 'selected' : ''}>Project Architect</option>
                            <option value="Junior Architect" ${currentValue === 'Junior Architect' ? 'selected' : ''}>Junior Architect</option>
                            <option value="Architectural Designer" ${currentValue === 'Architectural Designer' ? 'selected' : ''}>Architectural Designer</option>
                            <option value="Interior Architect" ${currentValue === 'Interior Architect' ? 'selected' : ''}>Interior Architect</option>
                            <option value="Landscape Architect" ${currentValue === 'Landscape Architect' ? 'selected' : ''}>Landscape Architect</option>
                            <option value="BIM Manager" ${currentValue === 'BIM Manager' ? 'selected' : ''}>BIM Manager</option>
                            <option value="CAD Technician" ${currentValue === 'CAD Technician' ? 'selected' : ''}>CAD Technician</option>
                            <option value="3D Visualization Specialist" ${currentValue === '3D Visualization Specialist' ? 'selected' : ''}>3D Visualization Specialist</option>
                        </optgroup>
                        <optgroup label="Construction">
                            <option value="Construction Manager" ${currentValue === 'Construction Manager' ? 'selected' : ''}>Construction Manager</option>
                            <option value="Project Manager" ${currentValue === 'Project Manager' ? 'selected' : ''}>Project Manager</option>
                            <option value="Site Engineer" ${currentValue === 'Site Engineer' ? 'selected' : ''}>Site Engineer</option>
                            <option value="Civil Engineer" ${currentValue === 'Civil Engineer' ? 'selected' : ''}>Civil Engineer</option>
                            <option value="Structural Engineer" ${currentValue === 'Structural Engineer' ? 'selected' : ''}>Structural Engineer</option>
                            <option value="MEP Engineer" ${currentValue === 'MEP Engineer' ? 'selected' : ''}>MEP Engineer</option>
                            <option value="Safety Manager" ${currentValue === 'Safety Manager' ? 'selected' : ''}>Safety Manager</option>
                            <option value="Quality Control Manager" ${currentValue === 'Quality Control Manager' ? 'selected' : ''}>Quality Control Manager</option>
                            <option value="Quantity Surveyor" ${currentValue === 'Quantity Surveyor' ? 'selected' : ''}>Quantity Surveyor</option>
                            <option value="Construction Supervisor" ${currentValue === 'Construction Supervisor' ? 'selected' : ''}>Construction Supervisor</option>
                            <option value="Foreman" ${currentValue === 'Foreman' ? 'selected' : ''}>Foreman</option>
                        </optgroup>
                        <optgroup label="Support & Administration">
                            <option value="HR" ${currentValue === 'HR' ? 'selected' : ''}>HR</option>
                            <option value="Project Coordinator" ${currentValue === 'Project Coordinator' ? 'selected' : ''}>Project Coordinator</option>
                            <option value="Document Controller" ${currentValue === 'Document Controller' ? 'selected' : ''}>Document Controller</option>
                            <option value="Procurement Specialist" ${currentValue === 'Procurement Specialist' ? 'selected' : ''}>Procurement Specialist</option>
                            <option value="Contract Administrator" ${currentValue === 'Contract Administrator' ? 'selected' : ''}>Contract Administrator</option>
                            <option value="Office Manager" ${currentValue === 'Office Manager' ? 'selected' : ''}>Office Manager</option>
                        </optgroup>
                    </select>
                `;
            } else {
                inputHtml = `<input type="text" name="value" value="${currentValue}" required>`;
            }
            
            fieldInput.innerHTML = inputHtml;
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const employeeId = formData.get('employeeId');

            if (userRole === 'Senior Manager (Studio)') {
                const employeeCard = document.querySelector(`.employee-card[data-employee-id="${employeeId}"]`);
                const reportingManager = employeeCard.querySelector('[data-field="reporting_manager"] .value-display').textContent;
                
                if (reportingManager !== '<?php echo $_SESSION['username']; ?>') {
                    showNotification('Access Denied', 'You can only edit information for your direct reports.', 'error');
                    return;
                }
            }

            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = 'Updating...';
            submitButton.disabled = true;

            fetch('update_employee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Get the updated values
                    const fieldName = formData.get('fieldName');
                    const newValue = data.newValue || formData.get('value');

                    // Find the specific employee card and value element
                    const employeeCard = document.querySelector(`.employee-card[data-employee-id="${employeeId}"]`);
                    if (!employeeCard) {
                        throw new Error('Employee card not found');
                    }

                    // Find the value display element within the specific field
                    const valueDisplay = employeeCard.querySelector(`[data-field="${fieldName}"] .value-display`);
                    if (!valueDisplay) {
                        throw new Error('Value display element not found');
                    }

                    // Update the display value
                    valueDisplay.textContent = newValue || 'Not Set';
                    
                    // Close modal and show success message
                    closeModal();
                    showNotification('Success', 'Information updated successfully', 'success');
                } else {
                    throw new Error(data.message || 'Error updating information');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error', error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
            });
        });

        // Add notification function
        function showNotification(title, message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <strong>${title}</strong>
                <p>${message}</p>
            `;
            document.body.appendChild(notification);

            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Update the info-value divs to include data attributes
        document.querySelectorAll('.info-value').forEach(el => {
            const field = el.closest('.info-group').querySelector('label').textContent.toLowerCase();
            const employeeId = el.closest('.employee-card').dataset.employeeId;
            el.setAttribute('data-field', field);
            el.setAttribute('data-id', employeeId);
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Make sure all elements have proper data attributes
        document.addEventListener('DOMContentLoaded', function() {
            // Verify all necessary elements exist
            const employeeCards = document.querySelectorAll('.employee-card');
            employeeCards.forEach(card => {
                if (!card.dataset.employeeId) {
                    console.error('Employee card missing data-employee-id attribute:', card);
                }
                
                const fields = card.querySelectorAll('.info-group');
                fields.forEach(field => {
                    if (!field.dataset.field) {
                        console.error('Field missing data-field attribute:', field);
                    }
                });
            });
        });

        // Variables to store temporary status change information
        let tempStatusToggle = null;
        let tempEmployeeId = null;
        let tempNewStatus = null;

        // Add event listener for status toggles - replace existing code
        document.querySelectorAll('.status-toggle').forEach(toggle => {
            toggle.addEventListener('change', function(e) {
                // Prevent default toggle behavior
                e.preventDefault();
                
                if (userRole !== 'HR') {
                    this.checked = !this.checked; // Revert toggle
                    showNotification('Access Denied', 'Only HR can change employee status.', 'error');
                    return;
                }
                
                // Store temporarily
                tempStatusToggle = this;
                tempEmployeeId = this.dataset.employeeId;
                tempNewStatus = !this.checked ? 'inactive' : 'active'; // reversed because we prevented the toggle
                
                // Set default date to today
                document.getElementById('customStatusDate').value = new Date().toISOString().split('T')[0];
                
                // Show date selection modal
                document.getElementById('statusDateModal').style.display = 'block';
                
                // Don't toggle yet - we'll do it after date confirmation
                this.checked = !this.checked; // Revert toggle until confirmed
            });
        });
        
        // Function to cancel status change
        function cancelStatusChange() {
            document.getElementById('statusDateModal').style.display = 'none';
            tempStatusToggle = null;
            tempEmployeeId = null;
            tempNewStatus = null;
        }
        
        // Function to confirm status change with custom date
        function confirmStatusChange() {
            if (!tempStatusToggle || !tempEmployeeId || !tempNewStatus) {
                showNotification('Error', 'Invalid status change request', 'error');
                return;
            }
            
            const customDate = document.getElementById('customStatusDate').value;
            if (!customDate) {
                showNotification('Error', 'Please select a date', 'error');
                return;
            }
            
            // Hide modal
            document.getElementById('statusDateModal').style.display = 'none';
            
            // Apply the toggle visually
            tempStatusToggle.checked = tempNewStatus === 'active';
            
            const statusText = tempStatusToggle.parentElement.nextElementSibling;
            
            // Show loading state
            statusText.textContent = 'Updating...';
            
            // Create FormData
            const formData = new FormData();
            formData.append('employeeId', tempEmployeeId);
            formData.append('fieldName', 'status');
            formData.append('value', tempNewStatus);
            formData.append('updateStatusDate', 'true');
            formData.append('customStatusDate', customDate);
            
            // Send update request
            fetch('update_employee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Update status text and class
                    statusText.textContent = tempNewStatus.charAt(0).toUpperCase() + tempNewStatus.slice(1);
                    statusText.className = `status-text status-${tempNewStatus}`;
                    
                    // Update the status change date if returned
                    if (data.statusChangedDate) {
                        let statusDateElem = tempStatusToggle.parentElement.querySelector('.status-date');
                        if (!statusDateElem) {
                            statusDateElem = document.createElement('small');
                            statusDateElem.className = 'status-date';
                            tempStatusToggle.parentElement.appendChild(statusDateElem);
                        }
                        statusDateElem.textContent = 'Changed: ' + data.statusChangedDate;
                    }
                    
                    showNotification('Success', 'Status updated successfully', 'success');
                } else {
                    // Revert toggle if update failed
                    tempStatusToggle.checked = tempNewStatus !== 'active';
                    throw new Error(data.message || 'Error updating status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert toggle state
                tempStatusToggle.checked = tempNewStatus !== 'active';
                statusText.textContent = tempStatusToggle.checked ? 'Active' : 'Inactive';
                showNotification('Error', error.message, 'error');
            })
            .finally(() => {
                // Clear temp variables
                tempStatusToggle = null;
                tempEmployeeId = null;
                tempNewStatus = null;
            });
        }

        // Close status date modal when clicking outside
        window.onclick = function(event) {
            const statusModal = document.getElementById('statusDateModal');
            const editModal = document.getElementById('editModal');
            
            if (event.target == statusModal) {
                cancelStatusChange();
            }
            
            if (event.target == editModal) {
                closeModal();
            }
        }

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
                    
                    // Update the icon
                    const icon = sidebarToggle.querySelector('i');
                    icon.classList.remove('bi-chevron-left');
                    icon.classList.add('bi-chevron-right');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                    
                    // Update the icon
                    const icon = sidebarToggle.querySelector('i');
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-left');
                }
            }
            
            // Check on load
            checkWidth();
            
            // Check on resize
            window.addEventListener('resize', checkWidth);
            
            // Handle click outside on mobile
            document.addEventListener('click', function(e) {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && !sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                    
                    // Update the icon
                    const icon = sidebarToggle.querySelector('i');
                    icon.classList.remove('bi-chevron-left');
                    icon.classList.add('bi-chevron-right');
                }
            });
        });
    </script>
</body>
</html>
