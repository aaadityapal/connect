<?php

// Start session and include database connection
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db_connect.php';

// Add role-based access control
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'Senior Manager (Studio)') {
        // Redirect unauthorized users to an appropriate page
        header("Location: unauthorized.php");
        exit();
    }
} catch (PDOException $e) {
    // Log the error and redirect to an error page
    error_log('Database error: ' . $e->getMessage());
    header("Location: error.php");
    exit();
}

?>


<!DOCTYPE html>
<html>
<head>
    <title>Sales Dashboard - Senior Manager</title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="modals/styles/project_form_styles_v1.css">
    <link rel="stylesheet" href="assets/css/notification-system.css">
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
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            position: relative;
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

        /* Add to your existing styles */
        .attendance-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .popup-content {
            background: white;
            padding: 24px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .popup-content i {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .popup-content i.success {
            color: #28a745;
        }

        .popup-content i.error {
            color: #dc3545;
        }

        .popup-content h3 {
            margin: 0 0 12px 0;
            color: #333;
        }

        .popup-content p {
            margin: 0 0 20px 0;
            color: #666;
        }

        .popup-close-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .popup-close-btn:hover {
            background: #ff5252;
        }

        .punch-btn.punched-in {
            background: #ff6b6b;
            color: white;
        }

        .punch-btn.punched-in:hover {
            background: #ff5252;
        }

        /* Add to your existing styles */
        .shift-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #777;
        }

        .shift-line i {
            font-size: 13px;
            color: #ff6b6b;
        }

        .rotating {
            animation: rotate 2s linear infinite;
        }

        @keyframes rotate {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .shift-info {
            margin-right: 8px;
        }

        .shift-timer {
            font-weight: 500;
            color: #333;
        }

        /* Add different colors for different remaining times */
        .shift-timer.ending-soon {
            color: #dc3545;
        }

        .shift-timer.mid-shift {
            color: #ffc107;
        }

        .shift-timer.shift-start {
            color: #28a745;
        }

        /* Add to your existing styles */
        .user-info {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #666;
        }

        .user-info span {
            position: relative;
            padding-right: 12px;
        }

        .user-info span:not(:last-child)::after {
            content: '•';
            position: absolute;
            right: 0;
            color: #ddd;
        }

        #user-name {
            font-weight: 500;
            color: #333;
            margin-left: 4px;
        }

        /* Enhanced Notification Icon Styling */
        .greeting-line .notification-wrapper {
            margin-left: 20px;
            position: relative;
            display: flex;
            align-items: center;
        }

        .greeting-line .notification-icon {
            background: #fff;
            color: #ff6b6b;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(255, 107, 107, 0.1);
            border: 2px solid #ffe5e5;
        }

        .greeting-line .notification-icon i {
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .greeting-line .notification-icon:hover {
            background: #fff5f5;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(255, 107, 107, 0.2);
        }

        .greeting-line .notification-icon:hover i {
            transform: scale(1.1);
        }

        .greeting-line .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(255, 107, 107, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(255, 107, 107, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 107, 107, 0);
            }
        }

        /* Adjust greeting line spacing */
        .greeting-line {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            position: relative;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 768px) {
            .greeting-line {
                justify-content: flex-start;
                gap: 10px;
            }
            
            .greeting-line .notification-wrapper {
                margin-left: 10px;
            }

            .greeting-line .notification-icon {
                width: 34px;
                height: 34px;
            }

            .greeting-line .notification-icon i {
                font-size: 14px;
            }

            .greeting-line .notification-badge {
                width: 18px;
                height: 18px;
                font-size: 10px;
            }
        }

        /* Add smooth animation for badge appearance */
        .notification-badge {
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform-origin: center;
        }

        .notification-badge[style*="display: none"] {
            transform: scale(0);
        }

        .notification-badge[style*="display: flex"] {
            transform: scale(1);
        }

        .employee-overview-section {
            padding: 0 20px 20px;
        }

        /* Add new header styles */
        .overview-header {
            background-color: white;
            border-radius: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            padding-top: 20px;
            padding-left: 20px;
            padding-right: 20px;

        }

        .overview-header h2 {
            font-size: 20px;
            color: #2c3e50;
            margin: 0 0 8px 0;
            font-weight: 600;
        }

        .overview-header p {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
        }

        .stats-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .stat-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        /* Enhanced stat card styles */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            display: flex;
            align-items: flex-start;
            gap: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 180px;
            position: relative;
            overflow: hidden;
            border: 1px solid #f0f0f0;
            cursor: pointer;
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, rgba(255,255,255,0.1), rgba(255,255,255,0.3));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }

        .stat-card.present {
            background: white;
        }

        .stat-card.present .stat-icon {
            background: #e3fcef;
            color: #00a854;
        }

        .stat-card.pending {
            background: white;
        }

        .stat-card.pending .stat-icon {
            background: #fff7e6;
            color: #fa8c16;
        }

        .stat-card.short-leave {
            background: linear-gradient(145deg, #ffffff, #f9f0ff);
        }

        .stat-card.short-leave .stat-icon {
            background: #f9f0ff;
            color: #722ed1;
        }

        .stat-card.on-leave {
            background: linear-gradient(145deg, #ffffff, #fff1f0);
        }

        .stat-card.on-leave .stat-icon {
            background: #fff1f0;
            color: #f5222d;
        }

        .stat-info {
            flex: 1;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 600;
            color: #2c3e50;
            margin: 16px 0;
            letter-spacing: -1px;
        }

        .stat-trend {
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            width: fit-content;
        }

        .stat-trend.positive {
            background: rgba(0, 168, 84, 0.1);
            color: #00a854;
        }

        .stat-trend.negative {
            background: rgba(245, 34, 45, 0.1);
            color: #f5222d;
        }

        .stat-trend.neutral {
            background: rgba(250, 140, 22, 0.1);
            color: #fa8c16;
        }

        /* Calendar Styles */
        .calendar-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            height: 480px; /* Reduced from default height */
            display: flex;
            flex-direction: column;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-header h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }

        .calendar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .calendar-nav {
            background: none;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            transition: all 0.2s ease;
        }

        .calendar-nav:hover {
            background: #f5f5f5;
            color: #333;
        }

        #currentMonth {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        .calendar-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px; /* Slightly reduced gap */
            margin-bottom: 16px;
            flex: 1;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: #666;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 8px; /* Slightly reduced padding */
        }

        .calendar-day:hover {
            background: #f5f5f5;
        }

        .calendar-day.today {
            background: #e6f7ff;
            color: #1890ff;
            font-weight: 500;
        }

        .calendar-day.has-event {
            position: relative;
        }

        .calendar-day.has-event::after {
            content: '';
            position: absolute;
            bottom: 4px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: currentColor;
        }

        .calendar-legend {
            display: flex;
            gap: 16px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #666;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot.approved {
            background: #52c41a;
        }

        .dot.pending {
            background: #faad14;
        }

        .dot.holiday {
            background: #1890ff;
        }

        /* Responsive Design */
        @media screen and (max-width: 1200px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }

            .calendar-container {
                max-width: none;
            }
        }

        @media screen and (max-width: 768px) {
            .stat-row {
                grid-template-columns: 1fr;
            }

            .employee-overview-section {
                padding: 0 15px 15px;
            }
        }
        /* Add this just before the closing body tag */
        <div id="workReportModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Daily Work Report</h3>
                    <span class="close-work-report">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="workReport">Please provide your work report for today:</label>
                        <textarea id="workReport" rows="6" placeholder="Enter your work report here..."></textarea>
                        <div id="workReportError" class="error-message"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="submitWorkReport" class="submit-btn">Submit & Punch Out</button>
                    <button id="cancelWorkReport" class="cancel-btn">Cancel</button>
                </div>
            </div>
        </div>

        /* Add these styles to your existing style tag */
        <style>
            .modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1000;
                animation: fadeIn 0.3s ease;
            }

            .modal-content {
                position: relative;
                background-color: #fff;
                margin: 10% auto;
                padding: 0;
                width: 90%;
                max-width: 500px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                animation: slideIn 0.3s ease;
            }

            .modal-header {
                padding: 20px;
                background: #f8f9fa;
                border-bottom: 1px solid #eee;
                border-radius: 8px 8px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .modal-header h3 {
                margin: 0;
                color: #333;
                font-size: 18px;
                font-weight: 600;
            }

            .close-work-report {
                font-size: 24px;
                color: #666;
                cursor: pointer;
                transition: color 0.2s;
            }

            .close-work-report:hover {
                color: #ff6b6b;
            }

            .modal-body {
                padding: 20px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #555;
                font-size: 14px;
            }

            .form-group textarea {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 14px;
                resize: vertical;
                transition: border-color 0.2s;
            }

            .form-group textarea:focus {
                outline: none;
                border-color: #ff6b6b;
            }

            .error-message {
                color: #dc3545;
                font-size: 12px;
                margin-top: 5px;
                display: none;
            }

            .modal-footer {
                padding: 15px 20px;
                background: #f8f9fa;
                border-top: 1px solid #eee;
                border-radius: 0 0 8px 8px;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }

            .submit-btn, .cancel-btn {
                padding: 8px 16px;
                border-radius: 4px;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.2s;
            }

            .submit-btn {
                background: #ff6b6b;
                color: white;
                border: none;
            }

            .submit-btn:hover {
                background: #ff5252;
            }

            .cancel-btn {
                background: #fff;
                color: #666;
                border: 1px solid #ddd;
            }

            .cancel-btn:hover {
                background: #f8f9fa;
                border-color: #ff6b6b;
                color: #ff6b6b;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes slideIn {
                from { transform: translateY(-20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            @media screen and (max-width: 576px) {
                .modal-content {
                    margin: 20% auto;
                    width: 95%;
                }
            }
        </style>
    </style>

    <!-- Add these tooltip styles -->
    <style>
        .tooltip-container {
            position: absolute; /* Change from fixed to absolute */
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid #eaeaea;
            max-width: 300px;
            z-index: 1000;
            display: none;
            animation: fadeIn 0.2s ease;
            margin-top: 10px; /* Add spacing between card and tooltip */
        }

        /* Add a pointer arrow to the tooltip */
        .tooltip-container::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 20px;
            width: 16px;
            height: 16px;
            background: white;
            transform: rotate(45deg);
            border-left: 1px solid #eaeaea;
            border-top: 1px solid #eaeaea;
        }

        .tooltip-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }

        .tooltip-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .tooltip-icon.present {
            background: #e3fcef;
            color: #00a854;
        }

        .tooltip-icon.pending {
            background: #fff7e6;
            color: #fa8c16;
        }

        .tooltip-icon.short-leave {
            background: #f9f0ff;
            color: #722ed1;
        }

        .tooltip-icon.on-leave {
            background: #fff1f0;
            color: #f5222d;
        }

        .tooltip-title {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin: 0;
        }

        .tooltip-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .tooltip-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .tooltip-item:last-child {
            border-bottom: none;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 12px;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 14px;
            color: #333;
            margin: 0 0 2px 0;
        }

        .user-position {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        /* Add this to your existing stat-card styles */
        .stat-card {
            cursor: pointer;
        }
    </style>
</head>
<body>
   
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
                                    <span id="greeting-text">Good Morning</span>,
                                </span>
                                <h1>
                                    <i class="fas fa-user"></i>
                                    <span id="user-name">Loading...</span>
                                </h1>
                                <!-- Updated notification wrapper -->
                                <div class="notification-wrapper">
                                    <div class="notification-icon">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <span class="notification-badge" style="display: none;">0</span>
                                </div>
                            </div>
                            <div class="user-info">
                                <span id="user-position"></span>
                                <span id="user-department"></span>
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
                                <div class="shift-line">
                                    <i class="fas fa-hourglass-half rotating"></i>
                                    <span class="shift-info">Shift: <span id="shift-name">Loading...</span></span>
                                    <span class="shift-timer" id="shift-countdown">--:--:--</span>
                                </div>
                            </div>
                        </div>
                        <div class="punch-section">
                            <div class="last-punch">
                                <span id="punch-status">Last punch in: </span>
                                <span id="punch-time" class="punch-time"></span>
                            </div>
                            <button id="punchButton" class="punch-btn" onclick="handlePunch()">
                                <i class="fas fa-fingerprint"></i>
                                <span id="punchButtonText">Punch In</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
                <!-- Employee Overview Section -->
    <div class="overview-container-wrapper">
        <div class="employee-overview-section">
            <div class="overview-header">
                <h2>Employee Overview</h2>
                <p>Monitor attendance and leave statistics across your organization</p>
            </div>
            <div class="overview-grid">
                <!-- Left side stats -->
                <div class="stats-container">
                    <div class="stat-row">
                        <!-- Present Employees Card -->
                        <div class="stat-card present">
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Employee Present</h3>
                                <div class="stat-number">147</div>
                                <div class="stat-trend positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>12% more than yesterday</span>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Leaves Card -->
                        <div class="stat-card pending">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Pending Leaves</h3>
                                <div class="stat-number">23</div>
                                <div class="stat-trend neutral">
                                    <i class="fas fa-minus"></i>
                                    <span>Requires attention</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stat-row">
                        <!-- Short Leave Card -->
                        <div class="stat-card short-leave">
                            <div class="stat-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Short Leave</h3>
                                <div class="stat-number">8</div>
                                <div class="stat-trend negative">
                                    <i class="fas fa-arrow-down"></i>
                                    <span>3 less than usual</span>
                                </div>
                            </div>
                        </div>

                        <!-- On Leave Card -->
                        <div class="stat-card on-leave">
                            <div class="stat-icon">
                                <i class="fas fa-user-minus"></i>
                            </div>
                            <div class="stat-info">
                                <h3>On Leave</h3>
                                <div class="stat-number">15</div>
                                <div class="stat-trend neutral">
                                    <i class="fas fa-minus"></i>
                                    <span>Average for month</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right side calendar -->
                <div class="calendar-container">
                    <div class="calendar-header">
                        <h3>Leave Calendar</h3>
                        <div class="calendar-actions">
                            <button class="calendar-nav" id="prevMonth">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span id="currentMonth">March 2024</span>
                            <button class="calendar-nav" id="nextMonth">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    <div class="calendar-body" id="calendarBody">
                        <!-- Calendar will be populated by JavaScript -->
                    </div>
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <span class="dot approved"></span>
                            <span>Approved Leave</span>
                        </div>
                        <div class="legend-item">
                            <span class="dot pending"></span>
                            <span>Pending Leave</span>
                        </div>
                        <div class="legend-item">
                            <span class="dot holiday"></span>
                            <span>Holiday</span>
                        </div>
                    </div>
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
                                        <span class="number">248</span>
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
                                        <span class="number">145</span>
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
                                        <span class="number">186</span>
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
   

    <?php include 'modals/project_form.php'; ?>
    <script src="modals/scripts/project_form_handler_v1.js"></script>
    <script src="assets/js/notification-handler.js"></script>

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
            const options = {
                timeZone: 'Asia/Kolkata',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            
            const istTime = new Date().toLocaleTimeString('en-US', options);
            document.getElementById('ist-time').textContent = istTime + ' IST';
        }

        // Update time immediately and then every second
        updateISTTime();
        setInterval(updateISTTime, 1000);

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

        function showPopup(success, title, message) {
            const popup = document.getElementById('attendancePopup');
            const icon = document.getElementById('popupIcon');
            const popupTitle = document.getElementById('popupTitle');
            const popupMessage = document.getElementById('popupMessage');

            icon.className = success ? 'fas fa-check-circle success' : 'fas fa-exclamation-circle error';
            popupTitle.textContent = title;
            // Replace \n with <br> for line breaks in the message
            popupMessage.innerHTML = message.replace(/\n/g, '<br>');
            popup.style.display = 'flex';
        }

        function closePopup() {
            document.getElementById('attendancePopup').style.display = 'none';
        }

        function updatePunchButton(isPunchedIn) {
            const button = document.getElementById('punchButton');
            const buttonText = document.getElementById('punchButtonText');
            
            if (isPunchedIn) {
                button.classList.add('punched-in');
                buttonText.textContent = 'Punch Out';
            } else {
                button.classList.remove('punched-in');
                buttonText.textContent = 'Punch In';
            }
        }

        function updatePunchStatus(punchInTime = null, workingHours = null) {
            const statusElement = document.getElementById('punch-status');
            const timeElement = document.getElementById('punch-time');
            
            if (punchInTime) {
                const formattedTime = new Date(punchInTime).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
                statusElement.textContent = 'Last punch in: ';
                timeElement.textContent = formattedTime;
                
                if (workingHours) {
                    statusElement.textContent = 'Working hours: ';
                    timeElement.textContent = workingHours;
                }
            } else {
                statusElement.textContent = 'Not punched in today';
                timeElement.textContent = '';
            }
        }

        async function checkAttendanceStatus() {
            try {
                const response = await fetch('punch_attendance.php?action=check_status');
                const data = await response.json();
                
                if (data.success) {
                    updatePunchButton(data.is_punched_in && !data.is_punched_out);
                    
                    // Update punch status display
                    const statusElement = document.getElementById('punch-status');
                    const timeElement = document.getElementById('punch-time');
                    
                    if (data.is_punched_in) {
                        if (data.is_punched_out) {
                            // Show working hours if punched out
                            statusElement.textContent = 'Working hours: ';
                            timeElement.textContent = data.working_hours;
                        } else {
                            // Show punch in time if still working
                            statusElement.textContent = 'Punched in at: ';
                            timeElement.textContent = new Date(data.punch_in_time)
                                .toLocaleTimeString('en-US', {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    hour12: true
                                });
                        }
                    } else {
                        statusElement.textContent = 'Not punched in';
                        timeElement.textContent = '';
                    }
                }
            } catch (error) {
                console.error('Error checking attendance status:', error);
            }
        }

        async function handlePunch() {
            const button = document.getElementById('punchButton');
            const isPunchOut = button.classList.contains('punched-in');
            
            if (isPunchOut) {
                // Show work report modal for punch out
                showWorkReportModal();
            } else {
                // Regular punch in
                await processPunch('punch_in');
            }
        }

        // Add these new functions
        function showWorkReportModal() {
            const modal = document.getElementById('workReportModal');
            modal.style.display = 'block';
            
            // Clear previous input
            document.getElementById('workReport').value = '';
            document.getElementById('workReportError').style.display = 'none';
        }

        function hideWorkReportModal() {
            const modal = document.getElementById('workReportModal');
            modal.style.display = 'none';
        }

        async function submitWorkReport() {
            const workReport = document.getElementById('workReport').value.trim();
            const errorElement = document.getElementById('workReportError');
            
            if (!workReport) {
                errorElement.textContent = 'Please enter your work report';
                errorElement.style.display = 'block';
                return;
            }
            
            // Process punch out with work report
            await processPunch('punch_out', workReport);
            hideWorkReportModal();
        }

        async function processPunch(action, workReport = null) {
            try {
                const response = await fetch('punch_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        action,
                        work_report: workReport 
                    }),
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (action === 'punch_in') {
                        showPopup(
                            true,
                            'Punched In Successfully',
                            'Your attendance has been recorded.'
                        );
                    } else {
                        // For punch out, show working hours
                        showPopup(
                            true,
                            'Punched Out Successfully',
                            `Total working hours: ${data.workingTime}\nWork report submitted successfully.`
                        );
                    }
                    
                    // Update button state and status display
                    await checkAttendanceStatus();
                } else {
                    showPopup(false, 'Error', data.message);
                }
            } catch (error) {
                showPopup(false, 'Error', 'Failed to process your request. Please try again.');
                console.error('Error:', error);
            }
        }

        // Add event listeners when the document loads
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking the close button or outside the modal
            document.querySelector('.close-work-report').addEventListener('click', hideWorkReportModal);
            document.getElementById('cancelWorkReport').addEventListener('click', hideWorkReportModal);
            document.getElementById('submitWorkReport').addEventListener('click', submitWorkReport);
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('workReportModal');
                if (event.target === modal) {
                    hideWorkReportModal();
                }
            });
        });

        // Check attendance status when page loads
        document.addEventListener('DOMContentLoaded', checkAttendanceStatus);

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        function updateTimerStyle(remainingSeconds) {
            const timerElement = document.getElementById('shift-countdown');
            
            if (remainingSeconds <= 1800) { // Last 30 minutes
                timerElement.className = 'shift-timer ending-soon';
            } else if (remainingSeconds <= 7200) { // Last 2 hours
                timerElement.className = 'shift-timer mid-shift';
            } else {
                timerElement.className = 'shift-timer shift-start';
            }
        }

        let countdownInterval;

        async function fetchShiftData() {
            try {
                const response = await fetch('get_shift_data.php');
                const data = await response.json();
                console.log('Shift data response:', data); // Debug log
                
                const shiftNameElement = document.getElementById('shift-name');
                const countdownElement = document.getElementById('shift-countdown');
                const hourglassIcon = document.querySelector('.shift-line i');
                
                if (data.success) {
                    shiftNameElement.textContent = data.shift_name;
                    hourglassIcon.classList.add('rotating');
                    
                    // Parse shift times
                    const now = new Date();
                    const endTime = new Date();
                    const [endHours, endMinutes] = data.end_time.split(':');
                    
                    endTime.setHours(parseInt(endHours), parseInt(endMinutes), 0);
                    
                    // If end time is earlier than current time, it's for next day
                    if (endTime < now) {
                        endTime.setDate(endTime.getDate() + 1);
                    }
                    
                    // Clear existing interval if any
                    if (countdownInterval) {
                        clearInterval(countdownInterval);
                    }
                    
                    // Start countdown
                    function updateCountdown() {
                        const now = new Date();
                        const diff = Math.max(0, Math.floor((endTime - now) / 1000));
                        
                        if (diff === 0) {
                            clearInterval(countdownInterval);
                            countdownElement.textContent = 'Shift Ended';
                            hourglassIcon.classList.remove('rotating');
                            return;
                        }
                        
                        const formattedTime = formatTime(diff);
                        countdownElement.textContent = formattedTime;
                        updateTimerStyle(diff);
                    }
                    
                    updateCountdown(); // Initial call
                    countdownInterval = setInterval(updateCountdown, 1000);
                    
                } else {
                    console.log('No shift assigned:', data.message); // Debug log
                    shiftNameElement.textContent = 'No shift assigned';
                    countdownElement.textContent = '--:--:--';
                    hourglassIcon.classList.remove('rotating');
                }
            } catch (error) {
                console.error('Error fetching shift data:', error);
                document.getElementById('shift-name').textContent = 'Error loading shift';
                document.getElementById('shift-countdown').textContent = '--:--:--';
                document.querySelector('.shift-line i').classList.remove('rotating');
            }
        }

        // Make sure to call this function when the page loads
        document.addEventListener('DOMContentLoaded', fetchShiftData);

        async function fetchUserData() {
            try {
                const response = await fetch('get_user_data.php');
                const data = await response.json();
                
                if (data.success) {
                    const { username, position, department } = data.user;
                    
                    // Update username
                    document.getElementById('user-name').textContent = username;
                    
                    // Update position and department if they exist
                    if (position) {
                        document.getElementById('user-position').textContent = position;
                    }
                    if (department) {
                        document.getElementById('user-department').textContent = department;
                    }
                    
                    // Update greeting based on time of day
                    const hour = new Date().getHours();
                    let greeting = 'Good Morning';
                    if (hour >= 12 && hour < 17) {
                        greeting = 'Good Afternoon';
                    } else if (hour >= 17) {
                        greeting = 'Good Evening';
                    }
                    document.getElementById('greeting-text').textContent = greeting;
                    
                } else {
                    document.getElementById('user-name').textContent = 'User';
                }
            } catch (error) {
                console.error('Error fetching user data:', error);
                document.getElementById('user-name').textContent = 'User';
            }
        }

        // Add this to your DOMContentLoaded event listener
        document.addEventListener('DOMContentLoaded', function() {
            fetchUserData();
            // ... your existing DOMContentLoaded code ...
        });

        // Add this to your existing script section
        document.addEventListener('DOMContentLoaded', function() {
            // Get all stat cards and tooltips
            const statCards = document.querySelectorAll('.stat-card');
            const tooltips = document.querySelectorAll('.tooltip-container');
            
            // Hide all tooltips initially
            function hideAllTooltips() {
                tooltips.forEach(tooltip => tooltip.style.display = 'none');
            }

            // Show tooltip for specific card
            function showTooltip(cardType) {
                hideAllTooltips();
                const tooltip = document.getElementById(`${cardType}Tooltip`);
                const card = document.querySelector(`.stat-card.${cardType}`);
                
                if (tooltip && card) {
                    // Get card's position
                    const cardRect = card.getBoundingClientRect();
                    
                    // Position tooltip below the card
                    tooltip.style.display = 'block';
                    tooltip.style.top = `${cardRect.bottom + window.scrollY + 10}px`;
                    tooltip.style.left = `${cardRect.left}px`;
                    
                    // Check if tooltip would go off-screen to the right
                    const tooltipRect = tooltip.getBoundingClientRect();
                    if (tooltipRect.right > window.innerWidth) {
                        tooltip.style.left = `${window.innerWidth - tooltipRect.width - 20}px`;
                    }
                    
                    // Fetch and populate the data
                    fetchTooltipData(cardType);
                }
            }

            // Add hover listeners to stat cards
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    if (this.classList.contains('present')) {
                        showTooltip('present');
                    } else if (this.classList.contains('pending')) {
                        showTooltip('pending');
                    } else if (this.classList.contains('short-leave')) {
                        showTooltip('shortLeave');
                    } else if (this.classList.contains('on-leave')) {
                        showTooltip('onLeave');
                    }

                    // Add mouseleave event
                    card.addEventListener('mouseleave', function(e) {
                        // Check if the mouse is not moving to the tooltip
                        const tooltip = document.querySelector('.tooltip-container:hover');
                        if (!tooltip) {
                            hideAllTooltips();
                        }
                    });
                });
            });

            // Keep tooltip visible when hovering over it
            tooltips.forEach(tooltip => {
                tooltip.addEventListener('mouseleave', function() {
                    hideAllTooltips();
                });
            });

            // Add scroll event listener to reposition tooltips
            window.addEventListener('scroll', function() {
                const visibleTooltip = document.querySelector('.tooltip-container[style*="display: block"]');
                if (visibleTooltip) {
                    const cardType = visibleTooltip.id.replace('Tooltip', '');
                    const card = document.querySelector(`.stat-card.${cardType.toLowerCase().replace(/([A-Z])/g, '-$1').toLowerCase()}`);
                    if (card) {
                        const cardRect = card.getBoundingClientRect();
                        visibleTooltip.style.top = `${cardRect.bottom + window.scrollY + 10}px`;
                        visibleTooltip.style.left = `${cardRect.left}px`;
                    }
                }
            });

            // Example function to fetch and populate tooltip data
            async function fetchTooltipData(type) {
                try {
                    // For testing, use static data
                    const mockData = [
                        { name: 'John Doe', position: 'Senior Developer' },
                        { name: 'Jane Smith', position: 'UI Designer' },
                        { name: 'Mike Johnson', position: 'Project Manager' }
                    ];
                    
                    const tooltipList = document.querySelector(`#${type}Tooltip .tooltip-list`);
                    // This would be your actual API endpoint
                    const response = await fetch(`get_${type}_employees.php`);
                    const data = await response.json();
                    
                    const tooltipList = document.querySelector(`#${type}Tooltip .tooltip-list`);
                    tooltipList.innerHTML = ''; // Clear existing items
                    
                    // Add new items
                    data.forEach(user => {
                        const initials = user.name.split(' ').map(n => n[0]).join('');
                        tooltipList.innerHTML += `
                            <div class="tooltip-item">
                                <div class="user-avatar">${initials}</div>
                                <div class="user-info">
                                    <h4 class="user-name">${user.name}</h4>
                                    <p class="user-position">${user.position}</p>
                                </div>
                            </div>
                        `;
                    });
                } catch (error) {
                    console.error('Error fetching tooltip data:', error);
                }
            }
        });
    </script>

    <!-- Add this near the end of body tag -->
    <div id="attendancePopup" class="attendance-popup">
        <div class="popup-content">
            <i id="popupIcon" class="fas"></i>
            <h3 id="popupTitle"></h3>
            <p id="popupMessage"></p>
            <button onclick="closePopup()" class="popup-close-btn">Close</button>
        </div>
    </div>

    <!-- Add this just before the closing body tag -->
    <div id="workReportModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Daily Work Report</h3>
                <span class="close-work-report">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="workReport">Please provide your work report for today:</label>
                    <textarea id="workReport" rows="6" placeholder="Enter your work report here..."></textarea>
                    <div id="workReportError" class="error-message"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="submitWorkReport" class="submit-btn">Submit & Punch Out</button>
                <button id="cancelWorkReport" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Add these styles to your existing style tag -->
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 10% auto;
            padding: 0;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease;
        }

        .modal-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }

        .close-work-report {
            font-size: 24px;
            color: #666;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close-work-report:hover {
            color: #ff6b6b;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            resize: vertical;
            transition: border-color 0.2s;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .modal-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            border-radius: 0 0 8px 8px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .submit-btn, .cancel-btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .submit-btn {
            background: #ff6b6b;
            color: white;
            border: none;
        }

        .submit-btn:hover {
            background: #ff5252;
        }

        .cancel-btn {
            background: #fff;
            color: #666;
            border: 1px solid #ddd;
        }

        .cancel-btn:hover {
            background: #f8f9fa;
            border-color: #ff6b6b;
            color: #ff6b6b;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media screen and (max-width: 576px) {
            .modal-content {
                margin: 20% auto;
                width: 95%;
            }
        }
    </style>

    <script>
                document.addEventListener('DOMContentLoaded', function() {
            let currentDate = new Date();
            updateCalendar(currentDate);

            document.getElementById('prevMonth').addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                updateCalendar(currentDate);
            });

            document.getElementById('nextMonth').addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                updateCalendar(currentDate);
            });
        });

        function updateCalendar(date) {
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            document.getElementById('currentMonth').textContent = 
                `${monthNames[date.getMonth()]} ${date.getFullYear()}`;

            const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
            const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
            
            const calendarBody = document.getElementById('calendarBody');
            calendarBody.innerHTML = '';

            // Add day headers
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            days.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'calendar-day header';
                dayHeader.textContent = day;
                calendarBody.appendChild(dayHeader);
            });

            // Add empty cells for days before the first day of month
            for (let i = 0; i < firstDay.getDay(); i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'calendar-day empty';
                calendarBody.appendChild(emptyDay);
            }

            // Add days of the month
            const today = new Date();
            for (let i = 1; i <= lastDay.getDate(); i++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                dayCell.textContent = i;

                if (date.getMonth() === today.getMonth() && 
                    date.getFullYear() === today.getFullYear() && 
                    i === today.getDate()) {
                    dayCell.classList.add('today');
                }

                calendarBody.appendChild(dayCell);
            }
        }
    </script>

    <!-- Tooltips -->
    <div id="presentTooltip" class="tooltip-container">
        <div class="tooltip-header">
            <div class="tooltip-icon present">
                <i class="fas fa-user-check"></i>
            </div>
            <h3 class="tooltip-title">Present Employees</h3>
        </div>
        <div class="tooltip-list">
            <!-- Sample items, these would be populated dynamically -->
            <div class="tooltip-item">
                <div class="user-avatar">JD</div>
                <div class="user-info">
                    <h4 class="user-name">John Doe</h4>
                    <p class="user-position">Senior Developer</p>
                </div>
            </div>
        </div>
    </div>

    <div id="pendingTooltip" class="tooltip-container">
        <div class="tooltip-header">
            <div class="tooltip-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <h3 class="tooltip-title">Pending Leaves</h3>
        </div>
        <div class="tooltip-list">
            <!-- Will be populated dynamically -->
        </div>
    </div>

    <div id="shortLeaveTooltip" class="tooltip-container">
        <div class="tooltip-header">
            <div class="tooltip-icon short-leave">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <h3 class="tooltip-title">Short Leaves</h3>
        </div>
        <div class="tooltip-list">
            <!-- Will be populated dynamically -->
        </div>
    </div>

    <div id="onLeaveTooltip" class="tooltip-container">
        <div class="tooltip-header">
            <div class="tooltip-icon on-leave">
                <i class="fas fa-user-minus"></i>
            </div>
            <h3 class="tooltip-title">On Leave</h3>
        </div>
        <div class="tooltip-list">
            <!-- Will be populated dynamically -->
        </div>
    </div>
</body>
</html>
