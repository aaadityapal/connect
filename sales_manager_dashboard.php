<?php
// Start session
session_start();

// Function to check user role
function isSeniorSalesManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Senior Manager (Sales)';
}

// Check if user is logged in and has correct role
if (!isset($_SESSION['user_id']) || !isSeniorSalesManager()) {
    // Redirect to login page or unauthorized page
    header('Location: login.php');
    exit();
}

// Database connection (adjust credentials as per your configuration)
function getDbConnection() {
    $host = 'localhost';
    $dbname = 'crm';
    $user = 'root';
    $pass = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Function to get project counts by type
function getProjectCounts() {
    try {
        $pdo = getDbConnection();
        $sql = "SELECT 
                    project_type, 
                    COUNT(*) as count 
                FROM projects 
                WHERE deleted_at IS NULL 
                GROUP BY project_type";
        
        $stmt = $pdo->query($sql);
        $counts = [
            'architecture' => 0,
            'interior' => 0,
            'construction' => 0
        ];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[strtolower($row['project_type'])] = $row['count'];
        }
        
        return $counts;
    } catch(PDOException $e) {
        error_log("Error fetching project counts: " . $e->getMessage());
        return [
            'architecture' => 0,
            'interior' => 0,
            'construction' => 0
        ];
    }
}

// Get project counts
$projectCounts = getProjectCounts();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales Dashboard - Senior Manager</title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="modals/styles/project_form_styles_v1.css">
    <link rel="stylesheet" href="modals/styles/back_office_form_styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        .sidebar {
            height: 100vh;
            width: 220px;
            background: white;
            position: fixed;
            left: 0;
            top: 0;
            transition: width 0.3s ease;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            overflow: visible;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }

        .section-title {
            color: #8a8a8a;
            font-size: 12px;
            padding: 20px 25px 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .menu-item {
            padding: 12px 25px;
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 14px;
            transition: all 0.2s ease;
            white-space: nowrap;
            position: relative;
            z-index: 99;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            color: #dc3545;
            background: #fff5f5;
            border-left: 3px solid #dc3545;
        }

        .menu-item i {
            min-width: 20px;
            margin-right: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .menu-item:hover i {
            color: #dc3545;
            transform: translateX(2px);
        }

        .menu-item span {
            opacity: 1;
            transition: opacity 0.2s ease;
        }

        /* Sidebar Closed State */
        .sidebar.closed {
            width: 60px;
        }

        .sidebar.closed .section-title {
            padding: 20px 0 10px;
            font-size: 10px;
            text-align: center;
            opacity: 0.6;
        }

        .sidebar.closed .menu-item {
            padding: 12px 0;
            justify-content: center;
        }

        .sidebar.closed .menu-item i {
            margin-right: 0;
        }

        .sidebar.closed .menu-item span {
            opacity: 0;
            width: 0;
            display: none;
        }

        /* Toggle Button */
        .toggle-btn {
            position: absolute;
            right: -10px;
            top: 12px;
            background: #f5f5f5;
            color: #666;
            width: 20px;
            height: 20px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 101;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .toggle-btn i {
            font-size: 10px;
        }

        .sidebar.closed .toggle-btn {
            transform: rotate(180deg);
        }

        /* Main Content */
        .main-content {
            margin-left: 220px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            max-width: calc(100% - 220px);
        }

        .main-content.expanded {
            margin-left: 60px;
            max-width: calc(100% - 60px);
        }

        /* Update closed sidebar hover states */
        .sidebar.closed .menu-item:hover {
            background: #fff5f5;
            border-left: 3px solid #dc3545;
        }

        .sidebar.closed .menu-item:hover i {
            color: #dc3545;
            transform: scale(1.1);
        }

        /* Active menu item state */
        .menu-item.active {
            color: #dc3545;
            background: #fff5f5;
            border-left: 3px solid #dc3545;
        }

        .menu-item.active i {
            color: #dc3545;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
        }

        .logout-btn {
            padding: 15px 25px;
            color: #dc3545;  /* Red color for logout */
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 14px;
            transition: all 0.2s ease;
            white-space: nowrap;
            border-top: 1px solid #eee;
            margin-top: auto;
        }

        .logout-btn i {
            min-width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        .logout-btn:hover {
            background: #fff5f5;
            color: #dc3545;
        }

        .sidebar.closed .logout-btn {
            padding: 15px 0;
            justify-content: center;
        }

        .sidebar.closed .logout-btn i {
            margin-right: 0;
        }

        .sidebar.closed .logout-btn span {
            display: none;
        }

        /* Add these media query styles at the end of your existing styles */

        /* For tablets (portrait) and smaller laptops */
        @media screen and (max-width: 1024px) {
            .sidebar {
                width: 280px;
            }

            .main-content {
                margin-left: 280px;
            }

            .menu-item {
                padding: 14px 25px;
                font-size: 14px;
            }

            .menu-item i {
                font-size: 18px;
                margin-right: 15px;
            }

            .section-title {
                padding: 20px 25px 10px;
                font-size: 13px;
            }
        }

        /* For mobile devices and small tablets */
        @media screen and (max-width: 768px) {
            .sidebar {
                width: 300px;
                left: -300px;
            }

            .sidebar.closed {
                width: 300px;
                left: -300px;
            }

            .menu-item {
                padding: 16px 25px;
                font-size: 15px;
            }

            .menu-item i {
                font-size: 20px;
                margin-right: 15px;
            }

            .section-title {
                padding: 25px 25px 12px;
                font-size: 14px;
            }

            /* Larger mobile menu button */
            .mobile-menu-btn {
                padding: 12px;
                font-size: 18px;
            }

            /* Adjust logout button */
            .logout-btn {
                padding: 20px 25px;
                font-size: 15px;
            }

            .logout-btn i {
                font-size: 20px;
            }
        }

        /* For very small mobile devices */
        @media screen and (max-width: 375px) {
            .sidebar {
                width: 90%;
                left: -90%;
            }

            .sidebar.closed {
                width: 90%;
                left: -90%;
            }

            .menu-item {
                padding: 16px 20px;
            }

            .section-title {
                padding: 20px 20px 12px;
            }
        }

        .greeting-section {
            padding: 20px;
            margin-bottom: 20px;
        }

        .greeting-card {
            background: #fcfcfc;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
        }

        .content-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .left-content {
            flex: 1;
        }

        .greeting-line {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .greeting {
            font-size: 15px;
            color: #555;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .greeting i {
            font-size: 14px;
            color: #ff6b6b;  /* Softer red */
        }

        .greeting-line h1 {
            font-size: 15px;
            color: #333;
            margin: 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .greeting-line h1 i {
            font-size: 14px;
            color: #ff6b6b;  /* Softer red */
        }

        .info-lines {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .time-line, .date-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #777;
        }

        .time-line i, .date-line i {
            font-size: 13px;
            color: #ff6b6b;  /* Softer red */
        }

        /* Punch section styles */
        .punch-section {
            text-align: right;
        }

        .last-punch {
            font-size: 14px;
            color: #777;
            margin-bottom: 8px;
        }

        .punch-time {
            font-weight: 500;
            color: #333;
        }

        .punch-btn {
            background: #fff;
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .punch-btn:hover {
            background: #ff6b6b;
            color: #fff;
        }

        .punch-btn i {
            font-size: 14px;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 768px) {
            .content-wrapper {
                flex-direction: column;
                gap: 20px;
            }

            .punch-section {
                width: 100%;
                text-align: left;
            }

            .punch-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Add these new styles */
        .project-section {
            padding: 0 20px 20px;
        }

        .project-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .project-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            border: 1px solid #f0f0f0;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .architecture .card-icon {
            background: #fff5f5;
            color: #ff6b6b;
        }

        .interior .card-icon {
            background: #f3f0ff;
            color: #845ef7;
        }

        .construction .card-icon {
            background: #fff9db;
            color: #fab005;
        }

        .card-content {
            flex: 1;
        }

        .card-content h3 {
            font-size: 14px;
            color: #666;
            margin: 0 0 8px 0;
            font-weight: 500;
        }

        .project-count {
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .project-count .number {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        .project-count .label {
            font-size: 13px;
            color: #888;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 1024px) {
            .project-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media screen and (max-width: 768px) {
            .project-cards {
                grid-template-columns: 1fr;
            }

            .project-section {
                padding: 0 15px 15px;
            }
        }

        /* Update existing styles and add new ones */
        .overview-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin: 0 20px 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0f0f0;
            height: calc(100vh - 120px);
            min-height: 700px;
            width: 100%;
            overflow: hidden;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .header-left h2 {
            font-size: 18px;
            color: #2c3e50;
            margin: 0 0 4px 0;
            font-weight: 600;
        }

        .header-left p {
            font-size: 13px;
            color: #888;
            margin: 0;
        }

        .date-filter {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            border-radius: 8px;
        }

        .filter-item {
            position: relative;
        }

        .filter-item label {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
            display: block;
        }

        .date-input {
            position: relative;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px 12px;
            min-width: 160px;
            cursor: pointer;
        }

        .date-input:hover {
            border-color: #d0d0d0;
        }

        .date-display {
            border: none;
            background: none;
            font-size: 14px;
            color: #333;
            width: calc(100% - 24px);
            cursor: pointer;
            padding: 0;
        }

        .month-picker {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .date-input i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 14px;
            pointer-events: none;
        }

        .filter-separator {
            color: #666;
            font-size: 16px;
            margin: 0 4px;
            margin-top: 24px;
        }

        /* Responsive styles */
        @media screen and (max-width: 768px) {
            .date-filter {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
            }

            .date-input {
                min-width: 140px;
            }
        }

        @media screen and (max-width: 480px) {
            .date-filter {
                flex-direction: column;
                gap: 16px;
            }

            .filter-separator {
                margin: 0;
            }

            .date-input {
                width: 100%;
                min-width: unset;
            }
        }

        /* Add these new styles */
        .overview-content {
            height: calc(100% - 60px);
            display: grid;
            grid-template-columns: minmax(0, 1fr) 350px;
            gap: 24px;
            margin-top: 24px;
        }

        .main-content-area {
            min-width: 0;
            overflow: hidden;
        }

        .followups-section {
            background: white;
            border-radius: 8px;
            border: 1px solid #f0f0f0;
            height: 560px; /* Fixed height */
            display: flex;
            flex-direction: column;
        }

        .followups-header {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .followups-header h3 {
            font-size: 13px;
            color: #333;
            margin: 0;
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-dropdown {
            position: relative;
            display: flex;
            align-items: center;
        }

        .filter-select {
            padding: 4px 24px 4px 8px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 11px;
            color: #666;
            background: white;
            cursor: pointer;
            appearance: none;
            min-width: 90px;
        }

        .filter-dropdown i {
            position: absolute;
            right: 8px;
            color: #666;
            font-size: 8px;
            pointer-events: none;
        }

        .add-followup-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .add-followup-btn i {
            font-size: 9px;
        }

        .add-followup-btn:hover {
            background: #ff5252;
        }

        .followups-list {
            overflow-y: auto;
            padding: 16px;
            flex: 1;
        }

        .followup-item {
            padding: 12px;
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            margin-bottom: 12px;
            background: white;
        }

        .followup-item:last-child {
            margin-bottom: 0;
        }

        /* Scrollbar styling specifically for followups */
        .followups-list::-webkit-scrollbar {
            width: 6px;
        }

        .followups-list::-webkit-scrollbar-track {
            background: #f5f5f5;
            border-radius: 3px;
        }

        .followups-list::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 3px;
        }

        .followups-list::-webkit-scrollbar-thumb:hover {
            background: #ccc;
        }

        .followup-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #fff5f5;
            color: #ff6b6b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .followup-content {
            flex: 1;
        }

        .followup-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }

        .followup-title h4 {
            font-size: 14px;
            color: #333;
            margin: 0;
            font-weight: 500;
        }

        .time {
            font-size: 12px;
            color: #888;
        }

        .followup-content p {
            font-size: 13px;
            color: #666;
            margin: 0 0 8px 0;
        }

        .followup-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .client {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .client i {
            font-size: 11px;
        }

        .status {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.completed {
            background: #d4edda;
            color: #155724;
        }

        .status.scheduled {
            background: #cce5ff;
            color: #004085;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 1400px) {
            .overview-content {
                grid-template-columns: minmax(0, 1fr) 300px;
            }

            .followups-section {
                width: 300px;
            }
        }

        @media screen and (max-width: 1200px) {
            .overview-content {
                grid-template-columns: 1fr;
            }

            .followups-section {
                width: 100%;
            }
        }

        /* Add these new styles */
        .projects-list-section {
            background: white;
            border-radius: 8px;
            border: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            height: 400px;
            margin-top: -8px;
        }

        .projects-list-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-tabs {
            display: flex;
            gap: 16px;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 8px 16px;
            font-size: 14px;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn.active {
            background: #fff5f5;
            color: #ff6b6b;
        }

        .add-project-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .add-project-btn:hover {
            background: #ff5252;
        }

        .projects-table-wrapper {
            overflow-y: auto;
            flex-grow: 1;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .projects-table-wrapper::-webkit-scrollbar {
            display: none;
        }

        /* Keep the table header sticky */
        .projects-table thead {
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
            border-bottom: 1px solid #f0f0f0;
        }

        .projects-table {
            width: 100%;
            border-collapse: collapse;
        }

        .projects-table th {
            background: #fcfcfc;
            padding: 12px 20px;
            text-align: left;
            font-size: 13px;
            font-weight: 500;
            color: #666;
            border-bottom: 1px solid #f0f0f0;
        }

        .projects-table td {
            padding: 16px 20px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
        }

        .project-name {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .project-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .project-icon.architecture {
            background: #fff5f5;
            color: #ff6b6b;
        }

        .project-info h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 500;
        }

        .project-info span {
            font-size: 12px;
            color: #888;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .progress-bar {
            width: 120px;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            position: relative;
        }

        .progress {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            background: #ff6b6b;
            border-radius: 3px;
        }

        .progress-bar span {
            position: absolute;
            right: -30px;
            top: -5px;
            font-size: 12px;
            color: #666;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            background: white;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            border-color: #ff6b6b;
            color: #ff6b6b;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 1024px) {
            .projects-list-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .header-tabs {
                width: 100%;
                overflow-x: auto;
            }

            .projects-table-wrapper {
                margin-top: 16px;
            }
        }

        @media screen and (max-width: 768px) {
            .tab-btn {
                padding: 6px 12px;
                font-size: 13px;
            }

            .projects-table th,
            .projects-table td {
                padding: 12px 16px;
            }
        }

        .tab-navigation {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .tab-group {
            display: flex;
            gap: 0;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 8px 20px;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            transition: all 0.2s ease;
        }

        .tab-btn i {
            font-size: 14px;
        }

        .tab-btn:hover {
            color: #ff6b6b;
            background: #fff5f5;
        }

        .tab-btn.active {
            color: #ff6b6b;
            background: #fff5f5;
        }

        .add-project-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .add-project-btn:hover {
            background: #ff5252;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 768px) {
            .tab-navigation {
                flex-wrap: wrap;
                gap: 12px;
            }

            .tab-group {
                width: 100%;
                overflow-x: auto;
            }
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .location-filter {
            position: relative;
            display: flex;
            align-items: center;
        }

        .filter-select {
            padding: 8px 32px 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            color: #666;
            background: white;
            cursor: pointer;
            appearance: none;
            min-width: 140px;
        }

        .location-filter i {
            position: absolute;
            right: 12px;
            color: #666;
            font-size: 12px;
            pointer-events: none;
        }

        .filter-select:hover {
            border-color: #d0d0d0;
        }

        .filter-select:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .add-project-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .add-project-btn:hover {
            background: #ff5252;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 768px) {
            .list-header {
                flex-direction: column;
                gap: 12px;
            }

            .tab-group {
                width: 100%;
                overflow-x: auto;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
                gap: 12px;
            }

            .location-filter {
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .add-project-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Update the project count cards style */
        .project-count-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #f0f0f0;
        }

        .project-count-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .project-count-card.architecture .icon {
            background: #fff5f5;
            color: #ff6b6b;
        }

        .project-count-card.interior .icon {
            background: #f3f0ff;
            color: #845ef7;
        }

        .project-count-card.construction .icon {
            background: #fff9db;
            color: #fab005;
        }

        .project-count-info h3 {
            font-size: 24px;
            color: #333;
            margin: 0 0 4px 0;
        }

        .project-count-info span {
            font-size: 14px;
            color: #666;
        }

        .custom-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            max-width: 350px;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .custom-notification.show {
            transform: translateX(0);
        }

        .custom-notification.success {
            border-left: 4px solid #10b981;
        }

        .custom-notification.error {
            border-left: 4px solid #ef4444;
        }

        .custom-notification.warning {
            border-left: 4px solid #f59e0b;
        }

        .notification-icon {
            font-size: 20px;
        }

        .custom-notification.success .notification-icon {
            color: #10b981;
        }

        .custom-notification.error .notification-icon {
            color: #ef4444;
        }

        .custom-notification.warning .notification-icon {
            color: #f59e0b;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 13px;
            color: #6b7280;
        }

        .notification-close {
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .notification-close:hover {
            background: #f3f4f6;
            color: #4b5563;
        }

        .work-report-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #f8fafc;
            border-radius: 16px;
            padding: 32px;
            width: 90%;
            max-width: 560px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            z-index: 1001;
            animation: dialogFadeIn 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .work-report-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(6px);
            z-index: 1000;
            animation: overlayFadeIn 0.3s ease;
        }

        .work-report-title {
            font-size: 1.125rem;
            font-weight: 500;
            color: #0f172a;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
        }

        .work-report-title i {
            color: #475569;
            font-size: 1rem;
        }

        .work-report-textarea {
            width: 100%;
            min-height: 140px;
            padding: 16px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.9375rem;
            line-height: 1.6;
            color: #334155;
            resize: vertical;
            transition: all 0.2s ease;
        }

        .work-report-textarea::placeholder {
            color: #94a3b8;
        }

        .work-report-textarea:focus {
            outline: none;
            border-color: #64748b;
            box-shadow: 0 0 0 3px rgba(100, 116, 139, 0.1);
        }

        .work-report-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .work-report-btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .work-report-btn.submit {
            background: #0f172a;
            color: white;
            border: none;
        }

        .work-report-btn.submit:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }

        .work-report-btn.cancel {
            background: #ffffff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .work-report-btn.cancel:hover {
            background: #f8fafc;
            color: #334155;
        }

        .work-report-btn i {
            font-size: 0.875rem;
        }

        /* Keyboard shortcut hints */
        .keyboard-hint {
            display: inline-flex;
            align-items: center;
            padding: 3px 6px;
            background: #f1f5f9;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #64748b;
            margin-left: 8px;
            border: 1px solid #e2e8f0;
        }

        @keyframes dialogFadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -48%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        @keyframes overlayFadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @media (max-width: 640px) {
            .work-report-dialog {
                width: 95%;
                padding: 24px;
            }
            
            .work-report-actions {
                flex-direction: column-reverse;
            }
            
            .work-report-btn {
                width: 100%;
                justify-content: center;
                padding: 12px;
            }
            
            .keyboard-hint {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php if (isSeniorSalesManager()): ?>
        <div class="sidebar" id="sidebar">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="fas fa-chevron-left" id="toggle-icon"></i>
            </button>
            
            <div class="sidebar-content">
                <div class="section-title">MAIN</div>
                <a href="#" class="menu-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Sales</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-tags"></i>
                    <span>Orders</span>
                </a>

                <div class="section-title">ANALYTICS</div>
                <a href="#" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-bullseye"></i>
                    <span>Targets</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Statistics</span>
                </a>

                <div class="section-title">SETTINGS</div>
                <a href="#" class="menu-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>

            <!-- Add logout button at bottom -->
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <div class="main-content" id="main-content">
            <div class="greeting-section">
                <div class="greeting-card">
                    <div class="content-wrapper">
                        <div class="left-content">
                            <div class="greeting-line">
                                <span class="greeting">
                                    <i class="fas fa-sun"></i>
                                    Good Morning,
                                </span>
                                <h1><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
                            </div>
                            <div class="info-lines">
                                <div class="time-line">
                                    <i class="far fa-clock"></i>
                                    <span id="ist-time"></span>
                                </div>
                                <div class="date-line">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('l, d M Y'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="punch-section">
                            <div class="last-punch">Last punch: <span class="punch-time">Not punched in today</span></div>
                            <button class="punch-btn" id="punchBtn">
                                <i class="fas fa-fingerprint"></i>
                                <span>Punch In</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add this after the greeting-section div -->
            <div class="overview-container">
                <div class="section-header">
                    <div class="header-left">
                        <h2>Project Overview</h2>
                        <p>Track your project statistics</p>
                    </div>
                    <div class="header-right">
                        <div class="date-filter">
                            <div class="filter-item">
                                <label>From</label>
                                <div class="date-input">
                                    <input type="text" class="date-display" value="March, 2025" readonly>
                                    <input type="month" class="month-picker" value="2025-03">
                                    <i class="far fa-calendar"></i>
                                </div>
                            </div>
                            <div class="filter-separator">-</div>
                            <div class="filter-item">
                                <label>To</label>
                                <div class="date-input">
                                    <input type="text" class="date-display" value="March, 2025" readonly>
                                    <input type="month" class="month-picker" value="2025-03">
                                    <i class="far fa-calendar"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overview-content">
                    <!-- Left side with cards and project list -->
                    <div class="main-content-area">
                        <!-- Project Cards -->
                        <div class="project-cards">
                            <div class="project-card architecture">
                                <div class="card-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="card-content">
                                    <h3>Architecture</h3>
                                    <div class="project-count">
                                        <span class="number"><?php echo htmlspecialchars($projectCounts['architecture']); ?></span>
                                        <span class="label">Projects</span>
                                    </div>
                                </div>
                            </div>

                            <div class="project-card interior">
                                <div class="card-icon">
                                    <i class="fas fa-couch"></i>
                                </div>
                                <div class="card-content">
                                    <h3>Interior</h3>
                                    <div class="project-count">
                                        <span class="number"><?php echo htmlspecialchars($projectCounts['interior']); ?></span>
                                        <span class="label">Projects</span>
                                    </div>
                                </div>
                            </div>

                            <div class="project-card construction">
                                <div class="card-icon">
                                    <i class="fas fa-hard-hat"></i>
                                </div>
                                <div class="card-content">
                                    <h3>Construction</h3>
                                    <div class="project-count">
                                        <span class="number"><?php echo htmlspecialchars($projectCounts['construction']); ?></span>
                                        <span class="label">Projects</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Projects List Section -->
                        <div class="projects-list-section">
                            <div class="list-header">
                                <div class="tab-group">
                                    <button class="tab-btn active">
                                        <i class="fas fa-building"></i> Architecture
                                    </button>
                                    <button class="tab-btn">
                                        <i class="fas fa-couch"></i> Interior
                                    </button>
                                    <button class="tab-btn">
                                        <i class="fas fa-hard-hat"></i> Construction
                                    </button>
                                </div>
                                <div class="header-actions">
                                    <div class="location-filter">
                                        <select class="filter-select">
                                            <option value="">All Locations</option>
                                            <option value="mumbai">Mumbai</option>
                                            <option value="delhi">Delhi</option>
                                            <option value="bangalore">Bangalore</option>
                                            <option value="pune">Pune</option>
                                        </select>
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <button class="add-project-btn">
                                        <i class="fas fa-plus"></i> Add Project
                                    </button>
                                </div>
                            </div>

                            <div class="projects-table-wrapper">
                                <table class="projects-table">
                                    <thead>
                                        <tr>
                                            <th>Project Name</th>
                                            <th>Client</th>
                                            <th>Start Date</th>
                                            <th>Deadline</th>
                                            <th>Budget</th>
                                            <th>Status</th>
                                            <th>Progress</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="project-name">
                                                    <div class="project-icon">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                    <div>Modern Villa Design</div>
                                                </div>
                                            </td>
                                            <td>John Smith</td>
                                            <td>01 Mar 2024</td>
                                            <td>30 Jun 2024</td>
                                            <td>₹1.2 Cr</td>
                                            <td><span class="status-badge in-progress">In Progress</span></td>
                                            <td>
                                                <div class="progress-bar">
                                                    <div class="progress" style="width: 65%"></div>
                                                    <span>65%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn"><i class="fas fa-edit"></i></button>
                                                    <button class="action-btn"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="project-name">
                                                    <div class="project-icon">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                    <div>Eco-Friendly Office Tower</div>
                                                </div>
                                            </td>
                                            <td>Robert Brown</td>
                                            <td>05 Mar 2024</td>
                                            <td>15 Sep 2024</td>
                                            <td>₹4.8 Cr</td>
                                            <td><span class="status-badge in-progress">In Progress</span></td>
                                            <td>
                                                <div class="progress-bar">
                                                    <div class="progress" style="width: 35%"></div>
                                                    <span>35%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn"><i class="fas fa-edit"></i></button>
                                                    <button class="action-btn"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="project-name">
                                                    <div class="project-icon">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                    <div>Heritage Restoration</div>
                                                </div>
                                            </td>
                                            <td>Alice Cooper</td>
                                            <td>10 Mar 2024</td>
                                            <td>20 Jul 2024</td>
                                            <td>₹3.2 Cr</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>
                                                <div class="progress-bar">
                                                    <div class="progress" style="width: 15%"></div>
                                                    <span>15%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn"><i class="fas fa-edit"></i></button>
                                                    <button class="action-btn"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="project-name">
                                                    <div class="project-icon">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                    <div>Smart Home Complex</div>
                                                </div>
                                            </td>
                                            <td>Peter Wang</td>
                                            <td>12 Mar 2024</td>
                                            <td>25 Aug 2024</td>
                                            <td>₹5.5 Cr</td>
                                            <td><span class="status-badge completed">Completed</span></td>
                                            <td>
                                                <div class="progress-bar">
                                                    <div class="progress" style="width: 100%"></div>
                                                    <span>100%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn"><i class="fas fa-edit"></i></button>
                                                    <button class="action-btn"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="project-name">
                                                    <div class="project-icon">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                    <div>Shopping Mall Renovation</div>
                                                </div>
                                            </td>
                                            <td>Maria Garcia</td>
                                            <td>15 Mar 2024</td>
                                            <td>30 Jun 2024</td>
                                            <td>₹6.7 Cr</td>
                                            <td><span class="status-badge on-hold">On Hold</span></td>
                                            <td>
                                                <div class="progress-bar">
                                                    <div class="progress" style="width: 45%"></div>
                                                    <span>45%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn"><i class="fas fa-edit"></i></button>
                                                    <button class="action-btn"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right side with Follow Ups -->
                    <div class="followups-section">
                        <div class="followups-header">
                            <h3>Recent Follow Ups</h3>
                            <div class="header-actions">
                                <div class="filter-dropdown">
                                    <select class="filter-select">
                                        <option value="all">All Types</option>
                                        <option value="meeting">Meetings</option>
                                        <option value="call">Calls</option>
                                        <option value="email">Emails</option>
                                    </select>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <button class="add-followup-btn">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                        </div>
                        <div class="followups-list">
                            <div class="followup-item">
                                <div class="followup-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="followup-content">
                                    <div class="followup-title">
                                        <h4>Modern Villa Project</h4>
                                        <span class="time">2 hours ago</span>
                                    </div>
                                    <p>Client meeting scheduled for design review</p>
                                    <div class="followup-meta">
                                        <span class="client"><i class="fas fa-user"></i> John Smith</span>
                                        <span class="status pending">Pending</span>
                                    </div>
                                </div>
                            </div>

                            <div class="followup-item">
                                <div class="followup-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="followup-content">
                                    <div class="followup-title">
                                        <h4>Office Interior</h4>
                                        <span class="time">5 hours ago</span>
                                    </div>
                                    <p>Sent quotation for furniture selection</p>
                                    <div class="followup-meta">
                                        <span class="client"><i class="fas fa-user"></i> Sarah Johnson</span>
                                        <span class="status completed">Completed</span>
                                    </div>
                                </div>
                            </div>

                            <div class="followup-item">
                                <div class="followup-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="followup-content">
                                    <div class="followup-title">
                                        <h4>Residential Complex</h4>
                                        <span class="time">1 day ago</span>
                                    </div>
                                    <p>Follow up on construction timeline</p>
                                    <div class="followup-meta">
                                        <span class="client"><i class="fas fa-user"></i> Mike Anderson</span>
                                        <span class="status scheduled">Scheduled</span>
                                    </div>
                                </div>
                            </div>

                            <div class="followup-item">
                                <div class="followup-icon">
                                    <i class="fas fa-file-signature"></i>
                                </div>
                                <div class="followup-content">
                                    <div class="followup-title">
                                        <h4>Smart Home Complex</h4>
                                        <span class="time">3 hours ago</span>
                                    </div>
                                    <p>Contract signing and documentation review</p>
                                    <div class="followup-meta">
                                        <span class="client"><i class="fas fa-user"></i> Peter Wang</span>
                                        <span class="status completed">Completed</span>
                                    </div>
                                </div>
                            </div>

                            <div class="followup-item">
                                <div class="followup-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="followup-content">
                                    <div class="followup-title">
                                        <h4>Shopping Mall Renovation</h4>
                                        <span class="time">6 hours ago</span>
                                    </div>
                                    <p>Budget discussion and timeline review</p>
                                    <div class="followup-meta">
                                        <span class="client"><i class="fas fa-user"></i> Maria Garcia</span>
                                        <span class="status pending">Pending</span>
                                    </div>
                                </div>
                            </div>

                            <div class="followup-item">
                                <div class="followup-icon">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="followup-content">
                                    <div class="followup-title">
                                        <h4>Heritage Restoration</h4>
                                        <span class="time">8 hours ago</span>
                                    </div>
                                    <p>Site inspection and material approval</p>
                                    <div class="followup-meta">
                                        <span class="client"><i class="fas fa-user"></i> Alice Cooper</span>
                                        <span class="status scheduled">Scheduled</span>
                                    </div>
                                </div>
                            </div>

                            <div class="followup-item">
                                <div class="followup-icon">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <div class="followup-content">
                                    <div class="followup-title">
                                        <h4>Eco-Friendly Office Tower</h4>
                                        <span class="time">1 day ago</span>
                                    </div>
                                    <p>Progress report presentation</p>
                                    <div class="followup-meta">
                                        <span class="client"><i class="fas fa-user"></i> Robert Brown</span>
                                        <span class="status completed">Completed</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add mobile menu button and overlay -->
        <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="sidebar-overlay" onclick="toggleMobileSidebar()"></div>
    <?php endif; ?>

    <?php include 'modals/project_form.php'; ?>
    <script src="modals/scripts/project_form_handler_v1.js"></script>
    <script src="modals/scripts/back_office_form_handler.js"></script>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleIcon = document.getElementById('toggle-icon');
            
            sidebar.classList.toggle('closed');
            mainContent.classList.toggle('expanded');
            
            // Change arrow direction
            if (sidebar.classList.contains('closed')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        }

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            
            if (!sidebar.contains(event.target) && 
                !mobileBtn.contains(event.target) && 
                sidebar.classList.contains('mobile-open')) {
                toggleMobileSidebar();
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            }
        });

        function updateISTTime() {
            const istTimeElement = document.getElementById('ist-time');
            
            // Check if element exists before updating
            if (istTimeElement) {
                const options = {
                    timeZone: 'Asia/Kolkata',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                };
                
                const istTime = new Date().toLocaleTimeString('en-US', options);
                istTimeElement.textContent = istTime + ' IST';
            }
        }

        // Make sure DOM is loaded before starting the timer
        document.addEventListener('DOMContentLoaded', function() {
            // Update time immediately and then every second
            updateISTTime();
            setInterval(updateISTTime, 1000);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const monthPickers = document.querySelectorAll('.month-picker');
            const dateDisplays = document.querySelectorAll('.date-display');

            monthPickers.forEach((picker, index) => {
                picker.addEventListener('change', function() {
                    const date = new Date(this.value + '-01');
                    const monthYear = date.toLocaleString('default', { 
                        month: 'long', 
                        year: 'numeric' 
                    });
                    dateDisplays[index].value = monthYear;

                    // Validate date range
                    if (index === 0) { // From date
                        if (monthPickers[1].value < this.value) {
                            monthPickers[1].value = this.value;
                            dateDisplays[1].value = monthYear;
                        }
                    } else { // To date
                        if (monthPickers[0].value > this.value) {
                            monthPickers[0].value = this.value;
                            dateDisplays[0].value = monthYear;
                        }
                    }
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabButtons = document.querySelectorAll('.tab-btn');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all buttons
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    button.classList.add('active');
                    
                    // Here you would typically load the corresponding projects
                    // based on the selected category (architecture/interior/construction)
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Handle location filter change
            const locationFilter = document.querySelector('.filter-select');
            locationFilter.addEventListener('change', function() {
                // Add your filtering logic here
                console.log('Selected location:', this.value);
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const punchBtn = document.querySelector('.punch-btn');
            const punchTime = document.querySelector('.punch-time');
            
            // Check initial punch status when page loads
            checkPunchStatus();
            
            punchBtn.addEventListener('click', handlePunchAction);
        });

        // Function to handle punch actions
        async function handlePunchAction() {
            const punchBtn = document.querySelector('.punch-btn');
            
            if (!punchBtn) {
                showNotification('Punch button not found', 'error');
                return;
            }
            
            const action = punchBtn.textContent.includes('In') ? 'punch_in' : 'punch_out';
            let data = { action: action };
            
            try {
                if (action === 'punch_out') {
                    const workReport = await promptWorkReport();
                    if (!workReport) return;
                    data.work_report = workReport;
                }
                
                const response = await fetch('punch.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    // Update button state and show success notification
                    if (action === 'punch_in') {
                        punchBtn.innerHTML = '<i class="fas fa-fingerprint"></i><span>Punch Out</span>';
                        const punchTimeSpan = document.querySelector('.punch-time');
                        const lastPunchText = document.querySelector('.last-punch');
                        if (punchTimeSpan && lastPunchText) {
                            lastPunchText.textContent = 'Last punch in: ';
                            punchTimeSpan.textContent = new Date().toLocaleTimeString();
                        }
                        showNotification('Successfully punched in', 'success');
                    } else {
                        punchBtn.innerHTML = '<i class="fas fa-fingerprint"></i><span>Punch In</span>';
                        const punchTimeSpan = document.querySelector('.punch-time');
                        const lastPunchText = document.querySelector('.last-punch');
                        if (punchTimeSpan && lastPunchText) {
                            lastPunchText.textContent = 'Last punch: ';
                            punchTimeSpan.textContent = new Date().toLocaleTimeString();
                        }
                        showNotification('Successfully punched out', 'success');
                    }
                } else {
                    showNotification(result.message || 'Failed to process request', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                    showNotification('Network error: Please check your connection', 'error');
                } else {
                    showNotification(error.message || 'An unexpected error occurred', 'error');
                }
            }
        }

        // Function to check initial punch status
        async function checkPunchStatus() {
            try {
                const response = await fetch('punch.php?action=check_status', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                const punchBtn = document.querySelector('.punch-btn');
                const punchTimeSpan = document.querySelector('.punch-time');
                const lastPunchText = document.querySelector('.last-punch');
                
                if (punchBtn && punchTimeSpan && lastPunchText) {
                    if (result.is_punched_in) {
                        punchBtn.innerHTML = '<i class="fas fa-fingerprint"></i><span>Punch Out</span>';
                        lastPunchText.textContent = 'Last punch in: ';
                        punchTimeSpan.textContent = result.punch_time ? 
                            new Date(result.punch_time).toLocaleTimeString() : 
                            new Date().toLocaleTimeString();
                    } else {
                        punchBtn.innerHTML = '<i class="fas fa-fingerprint"></i><span>Punch In</span>';
                        lastPunchText.textContent = 'Last punch: ';
                        punchTimeSpan.textContent = 'Not punched in today';
                    }
                }
            } catch (error) {
                console.error('Error checking punch status:', error);
            }
        }

        // Function to prompt for work report (you can replace this with a modal)
        function promptWorkReport() {
            return new Promise((resolve) => {
                // Create overlay
                const overlay = document.createElement('div');
                overlay.className = 'work-report-overlay';
                
                // Create dialog
                const dialog = document.createElement('div');
                dialog.className = 'work-report-dialog';
                
                dialog.innerHTML = `
                    <div class="work-report-title">
                        <i class="fas fa-clipboard-list"></i>
                        Work Report
                        <span class="keyboard-hint">Ctrl + Enter to submit</span>
                    </div>
                    <textarea 
                        class="work-report-textarea" 
                        placeholder="Please describe your work activities for today..."
                        autofocus
                    ></textarea>
                    <div class="work-report-actions">
                        <button class="work-report-btn cancel">
                            <i class="fas fa-xmark"></i>
                            Cancel
                        </button>
                        <button class="work-report-btn submit">
                            <i class="fas fa-check"></i>
                            Submit Report
                        </button>
                    </div>
                `;
                
                // Add to DOM
                document.body.appendChild(overlay);
                document.body.appendChild(dialog);
                
                // Focus textarea
                const textarea = dialog.querySelector('textarea');
                textarea.focus();
                
                // Handle submit
                const submitBtn = dialog.querySelector('.submit');
                submitBtn.addEventListener('click', () => {
                    const report = textarea.value.trim();
                    if (report) {
                        cleanup();
                        resolve(report);
                    } else {
                        showNotification('Please enter your work report', 'warning');
                        textarea.focus();
                    }
                });
                
                // Handle cancel
                const cancelBtn = dialog.querySelector('.cancel');
                cancelBtn.addEventListener('click', () => {
                    cleanup();
                    resolve(null);
                });
                
                // Handle escape key
                document.addEventListener('keydown', function escapeHandler(e) {
                    if (e.key === 'Escape') {
                        cleanup();
                        resolve(null);
                        document.removeEventListener('keydown', escapeHandler);
                    }
                });
                
                // Handle enter key (with Ctrl/Cmd)
                textarea.addEventListener('keydown', function enterHandler(e) {
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        const report = textarea.value.trim();
                        if (report) {
                            cleanup();
                            resolve(report);
                        } else {
                            showNotification('Please enter your work report', 'warning');
                        }
                    }
                });
                
                // Cleanup function
                function cleanup() {
                    dialog.remove();
                    overlay.remove();
                }
            });
        }

        // Add this function to handle notifications
        function showNotification(message, type = 'success', duration = 3000) {
            // Remove existing notification if any
            const existingNotification = document.querySelector('.custom-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            // Create notification elements
            const notification = document.createElement('div');
            notification.className = `custom-notification ${type}`;

            let icon;
            let title;
            switch (type) {
                case 'success':
                    icon = 'check-circle';
                    title = 'Success';
                    break;
                case 'error':
                    icon = 'exclamation-circle';
                    title = 'Error';
                    break;
                case 'warning':
                    icon = 'exclamation-triangle';
                    title = 'Warning';
                    break;
            }

            notification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                </div>
                <div class="notification-close">
                    <i class="fas fa-times"></i>
                </div>
            `;

            // Add notification to DOM
            document.body.appendChild(notification);

            // Show notification with animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            // Setup close button
            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.addEventListener('click', () => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            });

            // Auto close after duration
            if (duration) {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }, duration);
            }
        }
    </script>
</body>
</html>
