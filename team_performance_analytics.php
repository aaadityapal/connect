<?php
session_start();
require_once 'config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');
$conn->query("SET time_zone = '+05:30'");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user details
$current_user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user_data = $result->fetch_assoc();

// Get selected user ID from URL parameter or default to current user
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $current_user_id;

// Get selected user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $selected_user_id);
$stmt->execute();
$result = $stmt->get_result();
$selected_user_data = $result->fetch_assoc();

// Get all users for dropdown
$users_query = "SELECT id, username, profile_picture FROM users WHERE deleted_at IS NULL ORDER BY username ASC";
$users_result = $conn->query($users_query);
$all_users = [];
while ($user = $users_result->fetch_assoc()) {
    $all_users[] = $user;
}

// Get current date for calculations
$currentDate = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Performance Analytics - HR System</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --purple-color: #8b5cf6;
            --pink-color: #ec4899;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .performance-container {
            padding: 2rem;
            width: 100%;
            min-height: 100vh;
            margin: 0;
            background: var(--bg-light);
            padding-left: 280px; /* Account for sidebar */
            transition: padding-left 220ms ease;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1200;
            background: var(--bg-white);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem;
            cursor: pointer;
            box-shadow: var(--shadow);
            color: var(--text-dark);
        }

        .mobile-menu-toggle:hover {
            background: var(--bg-light);
        }

        .page-header {
            margin-bottom: 2rem;
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            color: white;
            text-align: left;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.25rem;
            font-weight: 300;
            color: white;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.5px;
        }

        .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }

        .page-subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 1rem;
            font-weight: 400;
            max-width: 500px;
            margin: 0;
            line-height: 1.5;
        }

        .user-selector {
            position: relative;
            z-index: 2;
            min-width: 250px;
        }

        .user-selector label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .user-dropdown {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 0.95rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .user-dropdown:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.2);
        }

        .user-dropdown option {
            background: var(--text-dark);
            color: white;
            padding: 0.5rem;
        }

        .selected-user-info {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: var(--bg-white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .metric-card.success::before { background: linear-gradient(90deg, var(--success-color), #22c55e); }
        .metric-card.warning::before { background: linear-gradient(90deg, var(--warning-color), #f97316); }
        .metric-card.danger::before { background: linear-gradient(90deg, var(--danger-color), #dc2626); }
        .metric-card.info::before { background: linear-gradient(90deg, var(--info-color), #0ea5e9); }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .metric-icon.primary { background: var(--primary-color); }
        .metric-icon.success { background: var(--success-color); }
        .metric-icon.warning { background: var(--warning-color); }
        .metric-icon.danger { background: var(--danger-color); }
        .metric-icon.info { background: var(--info-color); }

        .metric-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .metric-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .metric-progress {
            width: 100%;
            height: 8px;
            background: var(--bg-light);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .metric-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
            border-radius: 4px;
            transition: width 0.8s ease;
        }

        .metric-progress-fill.success { background: linear-gradient(90deg, var(--success-color), #22c55e); }

        .metric-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--bg-white);
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            height: fit-content;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }

        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Enhanced Stage and Substage Tracking Styles */
        .completion-tracking-section {
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--bg-white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .completion-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .completion-stat-card {
            background: var(--bg-white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .completion-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.stages { background: var(--primary-color); }
        .stat-icon.substages { background: var(--info-color); }
        .stat-icon.late { background: var(--danger-color); }
        .stat-icon.overall { background: var(--success-color); }

        .stat-content {
            flex: 1;
        }

        .stat-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-details {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Project Performance Breakdown */
        .project-performance-section {
            background: var(--bg-white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .project-performance-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .project-performance-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .projects-breakdown {
            display: grid;
            gap: 1.5rem;
        }

        .project-breakdown-card {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .project-breakdown-card:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow);
        }

        .project-breakdown-card.high-efficiency { border-left-color: var(--success-color); }
        .project-breakdown-card.medium-efficiency { border-left-color: var(--warning-color); }
        .project-breakdown-card.low-efficiency { border-left-color: var(--danger-color); }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .project-title-section {
            flex: 1;
        }

        .project-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .project-details-preview {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .project-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toggle-details-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .toggle-details-btn:hover {
            background: #2563eb;
            transform: scale(1.1);
        }

        .toggle-details-btn.active {
            background: var(--success-color);
        }

        .project-efficiency {
            font-size: 1.5rem;
            font-weight: 700;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            color: white;
        }

        .project-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .project-stat {
            display: flex;
            flex-direction: column;
            text-align: center;
        }

        .project-stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }

        .project-stat-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Project Details (Expandable Section) */
        .project-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .loading-stages {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            gap: 12px;
            color: var(--text-muted);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Stages and Substages Styles */
        .stages-container {
            display: grid;
            gap: 1rem;
        }

        .stage-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stage-header {
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stage-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .stage-meta {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .stage-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .stage-dates {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .progress-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: conic-gradient(var(--success-color) 0deg, var(--border-color) 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-circle::before {
            content: '';
            position: absolute;
            width: 36px;
            height: 36px;
            background: white;
            border-radius: 50%;
        }

        .progress-text {
            position: relative;
            z-index: 1;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Substages */
        .substages-list {
            padding: 0;
        }

        .substage-card {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s ease;
        }

        .substage-card:last-child {
            border-bottom: none;
        }

        .substage-card:hover {
            background: var(--bg-light);
        }

        .substage-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .substage-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .substage-meta {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .substage-identifier {
            font-size: 0.75rem;
            color: var(--primary-color);
            background: rgba(59, 130, 246, 0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-weight: 500;
        }

        .drawing-number {
            font-size: 0.75rem;
            color: var(--info-color);
            background: rgba(6, 182, 212, 0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-weight: 500;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        /* Status Colors */
        .status-completed, .status-badge.status-completed {
            background: var(--success-color);
            color: white;
        }

        .status-in-progress, .status-badge.status-in-progress {
            background: var(--primary-color);
            color: white;
        }

        .status-pending, .status-badge.status-pending {
            background: var(--warning-color);
            color: white;
        }

        .status-not-started, .status-badge.status-not-started {
            background: var(--text-muted);
            color: white;
        }

        .status-on-hold, .status-badge.status-on-hold {
            background: var(--danger-color);
            color: white;
        }

        .substage-details {
            display: grid;
            gap: 0.5rem;
        }

        .substage-dates {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .date-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .date-item i {
            font-size: 0.75rem;
        }

        .substage-assignment {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .substage-assignment i {
            font-size: 0.75rem;
            color: var(--primary-color);
        }

        .project-efficiency.high { background: var(--success-color); }
        .project-efficiency.medium { background: var(--warning-color); }
        .project-efficiency.low { background: var(--danger-color); }

        /* Empty State Styling */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state.error {
            color: var(--danger-color);
        }

        .empty-state.error i {
            color: var(--danger-color);
        }
        .project-efficiency.medium { background: var(--warning-color); }
        .project-efficiency.low { background: var(--danger-color); }

        .project-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .project-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--bg-white);
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .project-stat-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .project-stat-value {
            color: var(--text-dark);
            font-weight: 600;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .performance-container {
                padding: 1rem;
                padding-left: 1rem;
                padding-top: 4rem;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
                margin: 0 0.5rem 2rem 0.5rem;
                padding: 1.25rem 1.5rem;
                text-align: left;
            }
            
            .page-title {
                font-size: 1.75rem;
                gap: 10px;
            }

            .page-subtitle {
                font-size: 0.9rem;
                max-width: 90%;
            }

            .user-selector {
                width: 100%;
                min-width: auto;
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .completion-stats-grid {
                grid-template-columns: 1fr;
            }

            .project-stats {
                grid-template-columns: 1fr;
            }

            .project-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        /* When sidebar is collapsed on desktop */
        .msb-sidebar.is-collapsed ~ .performance-container {
            padding-left: 80px;
        }
    </style>
</head>

<body>
    <?php include 'components/minimal_sidebar.php'; ?>

    <div class="performance-container">
        <!-- Toggle Button for Mobile -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-users-cog"></i>
                    Team Performance Analytics
                </h1>
                <p class="page-subtitle">Comprehensive overview of team member work performance and productivity metrics</p>
            </div>
            
            <div class="user-selector">
                <label for="userSelect">Select Team Member:</label>
                <select id="userSelect" class="user-dropdown" onchange="changeUser()">
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo ($user['id'] == $selected_user_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="selected-user-info">
                    Viewing: <?php echo htmlspecialchars($selected_user_data['username']); ?>
                </div>
            </div>
        </div>

        <!-- Key Metrics Grid -->
        <div class="metrics-grid">
            <!-- Overall Efficiency -->
            <div class="metric-card success">
                <div class="metric-header">
                    <div>
                        <div class="metric-title">Overall Efficiency</div>
                        <?php
                        // Calculate overall efficiency for selected user
                        $efficiencyQuery = "SELECT 
                            COUNT(*) as total_completed,
                            SUM(CASE WHEN pss.updated_at <= pss.end_date THEN 1 ELSE 0 END) as on_time_completed
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id
                        JOIN projects p ON p.id = ps.project_id
                        WHERE pss.assigned_to = ? AND pss.status = 'completed'
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
                        
                        $stmt = $conn->prepare($efficiencyQuery);
                        $stmt->bind_param("i", $selected_user_id);
                        $stmt->execute();
                        $efficiencyData = $stmt->get_result()->fetch_assoc();
                        
                        $totalCompleted = $efficiencyData['total_completed'];
                        $onTimeCompleted = $efficiencyData['on_time_completed'];
                        $efficiencyPercentage = $totalCompleted > 0 ? round(($onTimeCompleted / $totalCompleted) * 100) : 0;
                        ?>
                        <div class="metric-value"><?php echo $efficiencyPercentage; ?>%</div>
                        <div class="metric-description">On-time completion rate</div>
                    </div>
                    <div class="metric-icon success">
                        <i class="fas fa-bullseye"></i>
                    </div>
                </div>
                <div class="metric-progress">
                    <div class="metric-progress-fill success" style="width: <?php echo $efficiencyPercentage; ?>%"></div>
                </div>
                <div class="metric-details">
                    <span><?php echo $onTimeCompleted; ?> on-time</span>
                    <span><?php echo $totalCompleted; ?> total</span>
                </div>
            </div>

            <!-- Active Tasks -->
            <div class="metric-card primary">
                <div class="metric-header">
                    <div>
                        <div class="metric-title">Active Tasks</div>
                        <?php
                        // Count active tasks for selected user
                        $activeQuery = "SELECT COUNT(*) as active_count 
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id
                        JOIN projects p ON p.id = ps.project_id
                        WHERE pss.assigned_to = ? AND pss.status IN ('pending', 'in_progress', 'in_review')
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
                        
                        $stmt = $conn->prepare($activeQuery);
                        $stmt->bind_param("i", $selected_user_id);
                        $stmt->execute();
                        $activeData = $stmt->get_result()->fetch_assoc();
                        $activeTasks = $activeData['active_count'];
                        ?>
                        <div class="metric-value"><?php echo $activeTasks; ?></div>
                        <div class="metric-description">Currently in progress</div>
                    </div>
                    <div class="metric-icon primary">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </div>

            <!-- Monthly Completions -->
            <div class="metric-card info">
                <div class="metric-header">
                    <div>
                        <div class="metric-title">This Month</div>
                        <?php
                        // Count monthly completions for selected user
                        $monthlyQuery = "SELECT COUNT(*) as monthly_count 
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id
                        JOIN projects p ON p.id = ps.project_id
                        WHERE pss.assigned_to = ? AND pss.status = 'completed'
                        AND DATE_FORMAT(pss.updated_at, '%Y-%m') = ?
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
                        
                        $stmt = $conn->prepare($monthlyQuery);
                        $stmt->bind_param("is", $selected_user_id, $currentMonth);
                        $stmt->execute();
                        $monthlyData = $stmt->get_result()->fetch_assoc();
                        $monthlyCompletions = $monthlyData['monthly_count'];
                        ?>
                        <div class="metric-value"><?php echo $monthlyCompletions; ?></div>
                        <div class="metric-description">Tasks completed</div>
                    </div>
                    <div class="metric-icon info">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <!-- Upcoming Deadlines -->
            <div class="metric-card warning">
                <div class="metric-header">
                    <div>
                        <div class="metric-title">Upcoming Deadlines</div>
                        <?php
                        // Count upcoming deadlines for selected user (next 7 days)
                        $upcomingQuery = "SELECT COUNT(*) as upcoming_count 
                        FROM project_substages pss
                        JOIN project_stages ps ON ps.id = pss.stage_id
                        JOIN projects p ON p.id = ps.project_id
                        WHERE pss.assigned_to = ? AND pss.status NOT IN ('completed', 'cancelled')
                        AND pss.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
                        
                        $stmt = $conn->prepare($upcomingQuery);
                        $stmt->bind_param("i", $selected_user_id);
                        $stmt->execute();
                        $upcomingData = $stmt->get_result()->fetch_assoc();
                        $upcomingDeadlines = $upcomingData['upcoming_count'];
                        ?>
                        <div class="metric-value"><?php echo $upcomingDeadlines; ?></div>
                        <div class="metric-description">Next 7 days</div>
                    </div>
                    <div class="metric-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Charts -->
        <div class="charts-section">
            <!-- Performance Trend Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Performance Trend</h3>
                </div>
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>

            <!-- Task Distribution Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Task Status Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Stage and Substage Completion Tracking -->
        <div class="completion-tracking-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-tasks"></i>
                    Detailed Completion Tracking
                </h2>
            </div>
            
            <!-- Completion Statistics Cards -->
            <div class="completion-stats-grid" id="completionStatsGrid">
                <div class="completion-stat-card">
                    <div class="stat-icon overall">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-title">Overall Tasks</div>
                        <div class="stat-value" id="totalTasks">-</div>
                        <div class="stat-details">
                            <span id="completedTasks">-</span> completed
                        </div>
                    </div>
                </div>
                
                <div class="completion-stat-card">
                    <div class="stat-icon stages">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-title">Stages</div>
                        <div class="stat-value" id="totalStages">-</div>
                        <div class="stat-details">
                            <span id="completedStages">-</span> completed • 
                            <span id="onTimeStages">-</span> on-time
                        </div>
                    </div>
                </div>
                
                <div class="completion-stat-card">
                    <div class="stat-icon substages">
                        <i class="fas fa-list-ul"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-title">Substages</div>
                        <div class="stat-value" id="totalSubstages">-</div>
                        <div class="stat-details">
                            <span id="completedSubstages">-</span> completed • 
                            <span id="onTimeSubstages">-</span> on-time
                        </div>
                    </div>
                </div>
                
                <div class="completion-stat-card">
                    <div class="stat-icon late">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-title">Late Tasks</div>
                        <div class="stat-value" id="lateTasks">-</div>
                        <div class="stat-details">
                            <span id="overdueTasks">-</span> overdue
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Performance Breakdown -->
        <div class="project-performance-section">
            <div class="project-performance-header">
                <h3 class="project-performance-title">
                    <i class="fas fa-project-diagram"></i>
                    Project Performance Breakdown
                </h3>
            </div>
            <div id="projectPerformanceBreakdown" class="projects-breakdown">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variable to store selected user ID
        let selectedUserId = <?php echo $selected_user_id; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    const sidebar = document.getElementById('msbSidebar');
                    const backdrop = document.getElementById('msbBackdrop');
                    
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('is-open');
                        backdrop.classList.toggle('is-visible');
                        document.body.style.overflow = sidebar.classList.contains('is-open') ? 'hidden' : '';
                    }
                });
            }

            // Handle sidebar collapse for performance container
            const sidebar = document.getElementById('msbSidebar');
            const performanceContainer = document.querySelector('.performance-container');
            
            if (sidebar && performanceContainer) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            const isCollapsed = sidebar.classList.contains('is-collapsed');
                            if (window.innerWidth > 768) {
                                performanceContainer.style.paddingLeft = isCollapsed ? '80px' : '280px';
                            }
                        }
                    });
                });
                
                observer.observe(sidebar, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }

            // Load charts for selected user
            loadUserPerformanceData(selectedUserId);
        });

        // Function to change user and reload data
        function changeUser() {
            const selectElement = document.getElementById('userSelect');
            const newUserId = selectElement.value;
            
            // Update URL and reload page with new user
            window.location.href = `team_performance_analytics.php?user_id=${newUserId}`;
        }

        // Function to load performance data for specific user
        function loadUserPerformanceData(userId) {
            // Get performance data for charts
            fetch(`get_team_performance_data.php?user_id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Performance Trend Chart
                    const performanceCtx = document.getElementById('performanceChart');
                    if (performanceCtx) {
                        new Chart(performanceCtx.getContext('2d'), {
                            type: 'line',
                            data: {
                                labels: data.months,
                                datasets: [{
                                    label: 'Efficiency %',
                                    data: data.efficiency,
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                }, {
                                    label: 'Completion Rate %',
                                    data: data.completion,
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 100
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'top'
                                    }
                                }
                            }
                        });
                    }

                    // Task Distribution Chart
                    const distributionCtx = document.getElementById('distributionChart');
                    if (distributionCtx) {
                        new Chart(distributionCtx.getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                labels: ['Completed', 'In Progress', 'Pending', 'Not Started'],
                                datasets: [{
                                    data: data.distribution,
                                    backgroundColor: [
                                        '#10b981',
                                        '#3b82f6',
                                        '#f59e0b',
                                        '#6b7280'
                                    ],
                                    borderWidth: 0,
                                    cutout: '50%'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            padding: 15,
                                            usePointStyle: true,
                                            font: {
                                                size: 12
                                            }
                                        }
                                    }
                                },
                                layout: {
                                    padding: {
                                        top: 10,
                                        bottom: 10
                                    }
                                }
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading performance data:', error);
                    showChartError('performanceChart', 'Failed to load performance data');
                    showChartError('distributionChart', 'Failed to load distribution data');
                });
            
            // Load detailed completion statistics
            loadCompletionStatistics(userId);
            
            // Load project performance breakdown
            loadProjectPerformanceBreakdown(userId);
        }
        
        // Function to load detailed completion statistics
        function loadCompletionStatistics(userId) {
            fetch(`get_completion_statistics.php?user_id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Update overall tasks
                    document.getElementById('totalTasks').textContent = data.total_tasks || 0;
                    document.getElementById('completedTasks').textContent = data.completed_tasks || 0;
                    
                    // Update stages
                    document.getElementById('totalStages').textContent = data.total_stages || 0;
                    document.getElementById('completedStages').textContent = data.completed_stages || 0;
                    document.getElementById('onTimeStages').textContent = data.on_time_stages || 0;
                    
                    // Update substages
                    document.getElementById('totalSubstages').textContent = data.total_substages || 0;
                    document.getElementById('completedSubstages').textContent = data.completed_substages || 0;
                    document.getElementById('onTimeSubstages').textContent = data.on_time_substages || 0;
                    
                    // Update late tasks
                    document.getElementById('lateTasks').textContent = data.late_tasks || 0;
                    document.getElementById('overdueTasks').textContent = data.overdue_tasks || 0;
                })
                .catch(error => {
                    console.error('Error loading completion statistics:', error);
                    // Set default values on error
                    document.getElementById('totalTasks').textContent = '0';
                    document.getElementById('completedTasks').textContent = '0';
                    document.getElementById('totalStages').textContent = '0';
                    document.getElementById('completedStages').textContent = '0';
                    document.getElementById('onTimeStages').textContent = '0';
                    document.getElementById('totalSubstages').textContent = '0';
                    document.getElementById('completedSubstages').textContent = '0';
                    document.getElementById('onTimeSubstages').textContent = '0';
                    document.getElementById('lateTasks').textContent = '0';
                    document.getElementById('overdueTasks').textContent = '0';
                });
        }
        
        // Function to load project performance breakdown
        function loadProjectPerformanceBreakdown(userId) {
            fetch(`get_project_breakdown.php?user_id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const container = document.getElementById('projectPerformanceBreakdown');
                    
                    if (data.projects && data.projects.length > 0) {
                        container.innerHTML = data.projects.map(project => {
                            const efficiencyClass = project.efficiency >= 80 ? 'high' : 
                                                   project.efficiency >= 60 ? 'medium' : 'low';
                            const cardClass = project.efficiency >= 80 ? 'high-efficiency' : 
                                            project.efficiency >= 60 ? 'medium-efficiency' : 'low-efficiency';
                            
                            return `
                                <div class="project-breakdown-card ${cardClass}">
                                    <div class="project-header">
                                        <div class="project-title-section">
                                            <h4 class="project-title">${project.name || 'Unnamed Project'}</h4>
                                            <div class="project-details-preview">
                                                ${project.total_stages || 0} stages • ${project.total_substages || 0} substages
                                            </div>
                                        </div>
                                        <div class="project-actions">
                                            <div class="project-efficiency ${efficiencyClass}">${project.efficiency || 0}%</div>
                                            <button class="toggle-details-btn" onclick="toggleProjectDetails(${project.id})" data-project-id="${project.id}">
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="project-stats">
                                        <div class="project-stat">
                                            <span class="project-stat-label">Total Tasks:</span>
                                            <span class="project-stat-value">${project.total_tasks || 0}</span>
                                        </div>
                                        <div class="project-stat">
                                            <span class="project-stat-label">Completed:</span>
                                            <span class="project-stat-value">${project.completed_tasks || 0}</span>
                                        </div>
                                        <div class="project-stat">
                                            <span class="project-stat-label">On-Time:</span>
                                            <span class="project-stat-value">${project.on_time_tasks || 0}</span>
                                        </div>
                                        <div class="project-stat">
                                            <span class="project-stat-label">Progress:</span>
                                            <span class="project-stat-value">${project.total_tasks > 0 ? Math.round((project.completed_tasks / project.total_tasks) * 100) : 0}%</span>
                                        </div>
                                    </div>
                                    <div class="project-details" id="project-details-${project.id}" style="display: none;">
                                        <div class="loading-stages" id="loading-stages-${project.id}">
                                            <div class="spinner"></div>
                                            <span>Loading stages and substages...</span>
                                        </div>
                                        <div class="stages-container" id="stages-container-${project.id}" style="display: none;"></div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    } else {
                        container.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-project-diagram"></i>
                                <p>No project data available for this user</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading project breakdown:', error);
                    document.getElementById('projectPerformanceBreakdown').innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Error loading project data. Please try again.</p>
                        </div>
                    `;
                });
        }

        function showChartError(chartId, message) {
            const chartContainer = document.getElementById(chartId).parentElement;
            chartContainer.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>${message}</p>
                </div>
            `;
        }
        
        // Function to toggle project details (stages and substages)
        function toggleProjectDetails(projectId) {
            const detailsContainer = document.getElementById(`project-details-${projectId}`);
            const toggleButton = document.querySelector(`[data-project-id="${projectId}"]`);
            const loadingContainer = document.getElementById(`loading-stages-${projectId}`);
            const stagesContainer = document.getElementById(`stages-container-${projectId}`);
            
            if (!detailsContainer) {
                console.error(`Project details container not found for project ID: ${projectId}`);
                return;
            }
            
            if (!toggleButton) {
                console.error(`Toggle button not found for project ID: ${projectId}`);
                return;
            }
            
            if (detailsContainer.style.display === 'none' || detailsContainer.style.display === '') {
                // Show details
                detailsContainer.style.display = 'block';
                toggleButton.innerHTML = '<i class="fas fa-chevron-up"></i>';
                toggleButton.classList.add('active');
                
                // Load stages if not already loaded
                if (stagesContainer && !stagesContainer.hasAttribute('data-loaded')) {
                    loadProjectStages(projectId);
                }
            } else {
                // Hide details
                detailsContainer.style.display = 'none';
                toggleButton.innerHTML = '<i class="fas fa-chevron-down"></i>';
                toggleButton.classList.remove('active');
            }
        }
        
        // Function to load project stages and substages
        function loadProjectStages(projectId) {
            const loadingContainer = document.getElementById(`loading-stages-${projectId}`);
            const stagesContainer = document.getElementById(`stages-container-${projectId}`);
            const userId = document.getElementById('userSelect').value;
            
            if (!loadingContainer || !stagesContainer) return;
            
            loadingContainer.style.display = 'flex';
            stagesContainer.style.display = 'none';
            
            fetch(`get_project_stages.php?project_id=${projectId}&user_id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    loadingContainer.style.display = 'none';
                    
                    if (data.stages && data.stages.length > 0) {
                        stagesContainer.innerHTML = data.stages.map(stage => {
                            const stageProgressPercent = stage.total_substages > 0 
                                ? Math.round((stage.completed_substages / stage.total_substages) * 100) 
                                : 0;
                            
                            return `
                                <div class="stage-card">
                                    <div class="stage-header">
                                        <div class="stage-info">
                                            <h5 class="stage-title">Stage ${stage.stage_number}</h5>
                                            <div class="stage-meta">
                                                <span class="stage-status status-${stage.status}">${stage.status}</span>
                                                <span class="stage-dates">${formatDate(stage.start_date)} - ${formatDate(stage.end_date)}</span>
                                            </div>
                                        </div>
                                        <div class="stage-progress">
                                            <div class="progress-circle">
                                                <span class="progress-text">${stageProgressPercent}%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="substages-list">
                                        ${stage.substages.map(substage => `
                                            <div class="substage-card">
                                                <div class="substage-header">
                                                    <div class="substage-info">
                                                        <h6 class="substage-title">${substage.title || 'Substage ' + substage.substage_number}</h6>
                                                        <div class="substage-meta">
                                                            <span class="substage-identifier">#${substage.substage_identifier || substage.substage_number}</span>
                                                            ${substage.drawing_number ? `<span class="drawing-number">Drawing: ${substage.drawing_number}</span>` : ''}
                                                        </div>
                                                    </div>
                                                    <div class="substage-status">
                                                        <span class="status-badge status-${substage.status}">${substage.status}</span>
                                                    </div>
                                                </div>
                                                <div class="substage-details">
                                                    <div class="substage-dates">
                                                        <span class="date-item">
                                                            <i class="fas fa-calendar-start"></i>
                                                            Start: ${formatDate(substage.start_date)}
                                                        </span>
                                                        <span class="date-item">
                                                            <i class="fas fa-calendar-check"></i>
                                                            End: ${formatDate(substage.end_date)}
                                                        </span>
                                                    </div>
                                                    ${substage.assigned_username ? `
                                                        <div class="substage-assignment">
                                                            <i class="fas fa-user"></i>
                                                            Assigned to: ${substage.assigned_username}
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        stagesContainer.style.display = 'block';
                        stagesContainer.setAttribute('data-loaded', 'true');
                    } else {
                        stagesContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-tasks"></i>
                                <p>No stages or substages found for this project</p>
                            </div>
                        `;
                        stagesContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading project stages:', error);
                    loadingContainer.style.display = 'none';
                    stagesContainer.innerHTML = `
                        <div class="empty-state error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Error loading stages and substages. Please try again.</p>
                        </div>
                    `;
                    stagesContainer.style.display = 'block';
                });
        }
        
        // Helper function to format dates
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'Invalid Date';
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    </script>
</body>
</html>