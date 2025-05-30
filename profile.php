<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch current user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user role for conditional display
$user_role = $_SESSION['role'] ?? 'employee';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --light-bg: #f8f9fa;
            --dark-text: #212529;
            --gray-text: #6c757d;
            --border-color: #dee2e6;
            --card-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
            color: var(--dark-text);
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 1.5rem;
            background: var(--light-bg);
            margin-left: 280px;
            transition: var(--transition);
        }

        .main-content.collapsed {
            margin-left: 70px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb i {
            margin: 0 10px;
            color: var(--gray-text);
        }

        /* Profile Tabs */
        .profile-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .profile-tab {
            padding: 0.75rem 1.25rem;
            cursor: pointer;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            color: var(--gray-text);
        }

        .profile-tab.active {
            background: var(--primary-color);
            color: white;
        }

        .profile-tab:hover:not(.active) {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }

        .profile-tab i {
            font-size: 1rem;
        }

        /* Profile sections */
        .profile-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-section.active {
            display: block;
        }

        .profile-card, 
        .notification-preferences,
        .activity-log, 
        .hr-documents {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .section-title {
            color: var(--dark-text);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--light-bg);
        }

        /* Form elements */
        .form-section {
            margin-bottom: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.25rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-text);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            border-radius: 6px;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
        }

        .btn-secondary {
            background-color: #e9ecef;
            color: var(--dark-text);
        }

        .btn-secondary:hover {
            background-color: #dee2e6;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }

        .btn-success {
            background-color: #20c997;
            color: white;
        }

        .btn-success:hover {
            background-color: #1ba87e;
        }

        .btn-danger {
            background-color: #e63946;
            color: white;
        }

        .btn-danger:hover {
            background-color: #d62828;
        }

        /* Activity Log styles */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: var(--transition);
        }

        .activity-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(67, 97, 238, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .activity-icon i {
            color: var(--primary-color);
        }

        .activity-details {
            flex: 1;
        }

        .activity-time {
            color: var(--gray-text);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Notification preferences */
        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .preference-item:last-child {
            border-bottom: none;
        }

        .preference-info h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .preference-info p {
            color: var(--gray-text);
            font-size: 0.875rem;
            margin: 0;
        }

        /* Toggle switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
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
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        /* Document styles */
        .document-type-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-bg);
            overflow-x: auto;
        }

        .doc-tab {
            padding: 0.5rem 1rem;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray-text);
            position: relative;
            white-space: nowrap;
        }

        .doc-tab.active {
            color: var(--primary-color);
        }

        .doc-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1rem;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .document-item {
            display: flex;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            background-color: white;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: var(--transition);
        }

        .document-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }

        .document-icon {
            font-size: 1.75rem;
            margin-right: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 8px;
            color: var(--primary-color);
        }

        .document-details {
            flex: 1;
        }

        .document-details h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .document-details p {
            margin: 0 0 0.5rem 0;
            color: var(--gray-text);
            font-size: 0.875rem;
        }

        .document-details small {
            color: var(--gray-text);
            font-size: 0.8125rem;
        }

        .document-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            margin-left: 0.5rem;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-accepted {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .document-type-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            background-color: #e9ecef;
            border-radius: 20px;
            margin-left: 0.5rem;
        }

        .no-documents, .error-message {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-text);
            background: rgba(0,0,0,0.02);
            border-radius: 8px;
            margin: 1rem 0;
        }

        .no-documents i, .error-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-color);
        }

        /* Filter section */
        .filter-section {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }

        .filter-section select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: #fff;
            font-size: 14px;
            color: var(--dark-text);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        .filter-section select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-tabs {
                flex-wrap: wrap;
                padding: 0.5rem;
                gap: 0.25rem;
            }
            
            .profile-tab {
                flex: 1 0 calc(50% - 0.25rem);
                padding: 0.6rem 0.75rem;
                font-size: 0.85rem;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem 0.75rem;
            }
            
            .main-content.collapsed {
                margin-left: 0;
            }
            
            .document-type-tabs {
                overflow-x: auto;
                padding-bottom: 0.5rem;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .breadcrumb {
                padding: 0.75rem;
                margin-bottom: 1rem;
                font-size: 0.9rem;
            }
            
            .section-title {
                font-size: 1.25rem;
                margin-bottom: 1rem;
            }
            
            .profile-card, 
            .notification-preferences,
            .activity-log, 
            .hr-documents {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .button-group {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn {
                width: 100%;
            }
            
            .document-item {
                padding: 1rem;
                flex-direction: column;
            }
            
            .document-icon {
                margin-right: 0;
                margin-bottom: 0.75rem;
            }
            
            .document-actions {
                margin-top: 1rem;
                justify-content: space-between;
                width: 100%;
            }
        }

        /* iPhone XR/XS specific adjustments */
        @media (max-width: 414px) {
            .profile-tab {
                font-size: 0.8rem;
                padding: 0.5rem 0.5rem;
            }
            
            .profile-tab i {
                font-size: 0.9rem;
                margin-right: 0.25rem;
            }
            
            .main-content {
                padding: 0.75rem 0.5rem;
            }
            
            .form-control {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
            
            .form-group label {
                font-size: 0.85rem;
                margin-bottom: 0.25rem;
            }
            
            .education-table th,
            .education-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .activity-item {
                padding: 0.75rem;
            }
            
            .activity-icon {
                width: 35px;
                height: 35px;
                margin-right: 0.75rem;
            }
            
            .preference-item {
                padding: 0.75rem 0;
            }
            
            .preference-info h3 {
                font-size: 0.9rem;
            }
            
            .preference-info p {
                font-size: 0.8rem;
            }
            
            .switch {
                width: 50px;
                height: 26px;
            }
            
            .slider:before {
                height: 18px;
                width: 18px;
            }
            
            input:checked + .slider:before {
                transform: translateX(24px);
            }
            
            .document-details h3 {
                font-size: 1rem;
            }
        }

        /* iPhone SE and other very small screens */
        @media (max-width: 375px) {
            .profile-tabs {
                padding: 0.4rem;
                gap: 0.2rem;
            }
            
            .profile-tab {
                padding: 0.4rem;
                font-size: 0.75rem;
            }
            
            .profile-tab i {
                margin-right: 0.2rem;
            }
            
            .section-title {
                font-size: 1.1rem;
            }
            
            .document-type-tabs {
                gap: 0.5rem;
            }
            
            .doc-tab {
                padding: 0.4rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .document-details h3 {
                font-size: 0.9rem;
            }
            
            .document-details p {
                font-size: 0.8rem;
            }
            
            .document-details small {
                font-size: 0.7rem;
            }
            
            .status-badge {
                padding: 0.15rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .document-type-badge {
                font-size: 0.65rem;
                padding: 0.15rem 0.4rem;
            }
            
            .btn-sm {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .activity-time {
                font-size: 0.75rem;
            }
        }

        /* Add this for better handling of the left panel on mobile */
        @media (max-width: 991px) {
            /* Prevent horizontal scrolling */
            html, body {
                max-width: 100%;
                overflow-x: hidden;
            }
            
            .main-content {
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
                margin-left: 0;
                padding: 15px;
                padding-top: 60px;
            }
            
            .main-content.collapsed {
                margin-left: 0;
            }
            
            .hamburger-menu {
                display: flex;
            }
            
            .left-panel {
                transform: translateX(-100%);
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                z-index: 999;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                transition: transform 0.3s ease;
            }
            
            .left-panel.show {
                transform: translateX(0);
            }
            
            /* Remove the existing mobile-menu-toggle styles */
            .mobile-menu-toggle {
                display: none;
            }
            
            /* Replace menu-overlay with panel-overlay */
            .menu-overlay {
                display: none;
            }
            
            .panel-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0,0,0,0.5);
                z-index: 998;
                display: none;
            }
            
            /* Adjust breadcrumb to not overlap with menu button */
            .breadcrumb {
                margin-left: 0;
                padding: 0.75rem;
                margin-bottom: 1rem;
            }
            
            /* Profile tabs responsive adjustments */
            .profile-tabs {
                flex-wrap: wrap;
                padding: 0.75rem;
                gap: 0.5rem;
            }
            
            .profile-tab {
                flex: 1 0 calc(50% - 0.5rem);
                padding: 0.6rem 0.75rem;
                font-size: 0.85rem;
            }
            
            /* Form and card adjustments */
            .profile-card, 
            .notification-preferences,
            .activity-log, 
            .hr-documents {
                padding: 1rem;
                margin-bottom: 1rem;
                border-radius: var(--radius-md);
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            /* Document sections */
            .document-type-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 0.5rem;
            }
        }

        /* Extra adjustments for very small screens */
        @media (max-width: 360px) {
            .main-content {
                padding-left: 5px;
                padding-right: 5px;
            }
            
            .profile-tab {
                font-size: 0.75rem;
                padding: 0.4rem 0.5rem;
            }
            
            .section-title {
                font-size: 1.1rem;
            }
            
            .form-control {
                padding: 0.6rem;
                font-size: 0.85rem;
            }
            
            .document-details h3 {
                font-size: 0.9rem;
            }
        }

        /* Fix for SweetAlert2 on mobile */
        @media (max-width: 576px) {
            .swal2-popup {
                width: 90% !important;
                max-width: 90vw !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
        }

        /* Fix for iPhone notch */
        @supports (padding-top: env(safe-area-inset-top)) {
            .main-content {
                padding-top: calc(60px + env(safe-area-inset-top));
                padding-left: calc(15px + env(safe-area-inset-left));
                padding-right: calc(15px + env(safe-area-inset-right));
                padding-bottom: calc(15px + env(safe-area-inset-bottom));
            }
            
            .hamburger-menu {
                top: calc(20px + env(safe-area-inset-top));
                left: calc(20px + env(safe-area-inset-left));
            }
        }

        /* Update the hamburger menu styles to make it bigger and more visible */
        .hamburger-menu {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1000;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px; /* Increased from 4px */
            padding: 12px 15px; /* Increased from 8px 12px */
            font-size: 1.5rem; /* Increased from 1.25rem */
            cursor: pointer;
            box-shadow: 0 3px 8px rgba(0,0,0,0.3); /* Enhanced shadow */
            width: 50px; /* Set fixed width */
            height: 50px; /* Set fixed height */
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .hamburger-menu:hover {
            background: var(--secondary-color);
            transform: scale(1.05);
        }

        .hamburger-menu:active {
            transform: scale(0.95);
        }

        .hamburger-menu i {
            font-size: 1.75rem; /* Make the icon itself larger */
        }

        @media (max-width: 991px) {
            .hamburger-menu {
                display: flex; /* Changed from block to flex for better centering */
            }
            
            /* Add a subtle pulse animation to draw attention */
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
            
            /* Apply the animation when the page loads */
            .hamburger-menu {
                animation: pulse 1s ease-in-out;
            }
        }
    </style>
</head>
<body>
    <button class="hamburger-menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <?php include 'left_panel.php'; ?>

        <div class="main-content" id="mainContent">
            <div class="breadcrumb">
                <a href="similar_dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Profile</span>
            </div>

            <div class="profile-tabs">
                <div class="profile-tab active" data-tab="personal">
                    <i class="fas fa-user"></i> Personal Info
                </div>
                <div class="profile-tab" data-tab="security">
                    <i class="fas fa-lock"></i> Security
                </div>
                <div class="profile-tab" data-tab="notifications">
                    <i class="fas fa-bell"></i> Notifications
                </div>
                <div class="profile-tab" data-tab="activity">
                    <i class="fas fa-history"></i> Activity Log
                </div>
                <div class="profile-tab" data-tab="hr-documents">
                    <i class="fas fa-file-alt"></i> HR Documents
                </div>
            </div>

            <div class="profile-section active" id="personal">
                <div class="profile-card">
                    <?php include 'includes/profile_form.php'; ?>
                </div>
            </div>

            <div class="profile-section" id="security">
                <div class="profile-card">
                    <h2 class="section-title">Security Settings</h2>
                    <form id="securityForm" onsubmit="return updateSecurity(event)">
                        <div class="form-section">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="profile-section" id="notifications">
                <div class="notification-preferences">
                    <h2 class="section-title">Notification Preferences</h2>
                    <div class="preference-item">
                        <div class="preference-info">
                            <h3>Email Notifications</h3>
                            <p>Receive updates via email</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="emailNotifications">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="preference-item">
                        <div class="preference-info">
                            <h3>Push Notifications</h3>
                            <p>Receive notifications in browser</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="pushNotifications">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="profile-section" id="activity">
                <div class="activity-log">
                    <h2 class="section-title">Recent Activity</h2>
                    <div id="activityContainer">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-text">Logged in successfully</div>
                                <div class="activity-time">Today at 9:30 AM</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-section" id="hr-documents">
                <div class="hr-documents">
                    <h2 class="section-title">HR Documents</h2>

                    <div class="document-type-tabs">
                        <button class="doc-tab active" data-doctype="policies">Policies & Staff Requirements Forms</button>
                        <button class="doc-tab" data-doctype="official">Official Documents</button>
                        <button class="doc-tab" data-doctype="personal">User Personal Documents</button>
                    </div>

                    <div class="documents-container" id="policyDocuments">
                    </div>

                    <div class="documents-container" id="officialDocuments" style="display: none;">
                    </div>

                    <div class="documents-container" id="personalDocuments" style="display: none;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.querySelectorAll('.profile-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });

        function handleAvatarUpload(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                    uploadAvatar(file);
                };
                
                reader.readAsDataURL(file);
            }
        }

        function uploadAvatar(file) {
            const formData = new FormData();
            formData.append('avatar', file);
            
            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Profile picture updated successfully'
                    });
                } else {
                    throw new Error(data.message || 'Failed to update profile picture');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            });
        }

        function handleSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Profile updated successfully'
                    });
                } else {
                    throw new Error(data.message || 'Failed to update profile');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            });
            
            return false;
        }

        function updateSecurity(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            if (formData.get('new_password') !== formData.get('confirm_password')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'New passwords do not match'
                });
                return false;
            }
            
            fetch('update_security.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Security settings updated successfully'
                    });
                    event.target.reset();
                } else {
                    throw new Error(data.message || 'Failed to update security settings');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            });
            
            return false;
        }

        document.querySelectorAll('.switch input').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const type = this.id;
                const enabled = this.checked;
                
                fetch('update_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        enabled: enabled
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to update notification preferences');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message
                    });
                    this.checked = !enabled;
                });
            });
        });

        function loadActivityLog() {
            fetch('get_activity_log.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('activityContainer');
                        container.innerHTML = data.activities.map(activity => `
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="${getActivityIcon(activity.type)}"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-text">${activity.description}</div>
                                    <div class="activity-time">${formatActivityTime(activity.timestamp)}</div>
                                </div>
                            </div>
                        `).join('');
                    }
                })
                .catch(error => console.error('Error loading activity log:', error));
        }

        function getActivityIcon(type) {
            const icons = {
                login: 'fas fa-sign-in-alt',
                profile_update: 'fas fa-user-edit',
                password_change: 'fas fa-key',
            };
            return icons[type] || 'fas fa-info-circle';
        }

        function formatActivityTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString();
        }

        document.querySelector('[data-tab="activity"]').addEventListener('click', loadActivityLog);

        document.querySelector('[data-tab="hr-documents"]').addEventListener('click', () => {
            loadPolicyDocuments();
        });

        function formatFileSize(bytes) {
            if (!bytes) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function initializeDocumentTabs() {
            const tabs = document.querySelectorAll('.doc-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    document.querySelectorAll('.documents-container').forEach(container => {
                        container.style.display = 'none';
                    });

                    switch(tab.dataset.doctype) {
                        case 'policies':
                            document.getElementById('policyDocuments').style.display = 'block';
                            loadPolicyDocuments();
                            break;
                        case 'official':
                            document.getElementById('officialDocuments').style.display = 'block';
                            loadOfficialDocuments();
                            break;
                        case 'personal':
                            document.getElementById('personalDocuments').style.display = 'block';
                            loadPersonalDocuments();
                            break;
                    }
                });
            });
        }

        function loadOfficialDocuments() {
            fetch('get_employee_official_documents.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Document data:', data);
                    const container = document.getElementById('officialDocuments');
                    
                    if (!data.documents || data.documents.length === 0) {
                        container.innerHTML = `
                            <div class="no-documents">
                                <i class="fas fa-folder-open"></i>
                                <p>No official documents available</p>
                            </div>`;
                        return;
                    }

                    container.innerHTML = data.documents.map(doc => {
                        console.log('Processing document:', {
                            id: doc.id,
                            status: doc.status,
                            assigned_user_id: doc.assigned_user_id,
                            current_user_id: doc.current_user_id
                        });

                        const showActions = doc.status === 'pending' && 
                                          parseInt(doc.assigned_user_id) === parseInt(doc.current_user_id);
                        
                        console.log('Show actions:', showActions);

                        return `
                            <div class="document-item">
                                <div class="document-icon">
                                    <i class="fas ${doc.icon_class || 'fa-file-alt'}"></i>
                                </div>
                                <div class="document-details">
                                    <h3>
                                        ${doc.document_name}
                                        <span class="status-badge status-${doc.status.toLowerCase()}">
                                            ${doc.status.charAt(0).toUpperCase() + doc.status.slice(1)}
                                        </span>
                                    </h3>
                                    <p>Last updated: ${doc.upload_date}</p>
                                    <p><small>
                                        Type: ${doc.document_type}
                                        • Size: ${doc.formatted_size}
                                        ${doc.uploaded_by_name ? `• Uploaded by: ${doc.uploaded_by_name}` : ''}
                                    </small></p>
                                    <div class="document-actions">
                                        <button class="btn btn-primary btn-sm" onclick="viewDocument(${doc.id}, 'official')" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="downloadDocument(${doc.id}, 'official')" title="Download">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        ${showActions ? `
                                            <button class="btn btn-success btn-sm" onclick="updateDocumentStatus(${doc.id}, 'accepted')" title="Accept">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="updateDocumentStatus(${doc.id}, 'rejected')" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                })
                .catch(error => {
                    console.error('Error loading official documents:', error);
                    document.getElementById('officialDocuments').innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading documents. Please try again later.</p>
                        </div>`;
                });
        }

        function updateDocumentStatus(docId, status) {
            Swal.fire({
                title: `Confirm ${status.charAt(0).toUpperCase() + status.slice(1)}`,
                text: `Are you sure you want to ${status} this document?`,
                icon: status === 'accepted' ? 'success' : 'warning',
                showCancelButton: true,
                confirmButtonColor: status === 'accepted' ? '#28a745' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${status} it!`
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('update_document_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            document_id: docId,
                            status: status,
                            type: 'official'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                'Updated!',
                                `Document has been ${status}.`,
                                'success'
                            );
                            loadOfficialDocuments();
                        } else {
                            throw new Error(data.message || 'Failed to update status');
                        }
                    })
                    .catch(error => {
                        Swal.fire(
                            'Error',
                            error.message,
                            'error'
                        );
                    });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            initializeDocumentTabs();
            
            if (document.querySelector('[data-tab="hr-documents"]').classList.contains('active')) {
                loadPolicyDocuments();
            }
        });

        function loadPersonalDocuments() {
            const container = document.getElementById('personalDocuments');
            
            container.innerHTML = `
                <div class="filter-section" style="margin-bottom: 20px;">
                    <div class="row">
                        <div class="col-md-4">
                            <select id="personalDocTypeFilter" class="form-control">
                                <option value="">All Document Types</option>
                                <option value="aadhar_card">Aadhar Card</option>
                                <option value="pan_card">PAN Card</option>
                                <option value="passport">Passport</option>
                                <option value="driving_license">Driving License</option>
                                <option value="voter_id">Voter ID</option>
                                <option value="sslc_certificate">SSLC Certificate</option>
                                <option value="hsc_certificate">HSC Certificate</option>
                                <option value="graduation_certificate">Graduation Certificate</option>
                                <option value="post_graduation">Post Graduation</option>
                                <option value="diploma_certificate">Diploma Certificate</option>
                                <option value="other_education">Other Education</option>
                                <option value="resume">Resume</option>
                                <option value="experience_certificate">Experience Certificate</option>
                                <option value="relieving_letter">Relieving Letter</option>
                                <option value="salary_slip">Salary Slip</option>
                                <option value="bank_passbook">Bank Passbook</option>
                                <option value="cancelled_cheque">Cancelled Cheque</option>
                                <option value="form_16">Form 16</option>
                                <option value="marriage_certificate">Marriage Certificate</option>
                                <option value="caste_certificate">Caste Certificate</option>
                                <option value="disability_certificate">Disability Certificate</option>
                                <option value="others">Others</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="personalDocumentsContent"></div>
            `;

            document.getElementById('personalDocTypeFilter').addEventListener('change', function() {
                loadFilteredDocuments(this.value);
            });

            loadFilteredDocuments('');
        }

        function loadFilteredDocuments(documentType) {
            const contentContainer = document.getElementById('personalDocumentsContent');
            
            fetch('get_personal_documents.php' + (documentType ? `?type=${documentType}` : ''))
                .then(response => response.json())
                .then(data => {
                    if (!data.documents || data.documents.length === 0) {
                        contentContainer.innerHTML = `
                            <div class="no-documents">
                                <i class="fas fa-folder-open"></i>
                                <p>No personal documents available</p>
                            </div>`;
                        return;
                    }

                    contentContainer.innerHTML = data.documents.map(doc => `
                        <div class="document-item">
                            <div class="document-icon">
                                <i class="fas ${doc.icon_class || 'fa-file-alt'}"></i>
                            </div>
                            <div class="document-details">
                                <h3>
                                    ${doc.document_name}
                                    <span class="document-type-badge">${doc.document_type}</span>
                                </h3>
                                <p>Last updated: ${doc.upload_date}</p>
                                <p><small>
                                    Size: ${doc.formatted_size}
                                    ${doc.document_number ? `• Document No: ${doc.document_number}` : ''}
                                    ${doc.issuing_authority ? `• Issued By: ${doc.issuing_authority}` : ''}
                                </small></p>
                                ${(doc.issue_date || doc.expiry_date) ? `
                                    <p><small>
                                        ${doc.issue_date ? `Issue Date: ${doc.issue_date}` : ''}
                                        ${doc.expiry_date ? ` • Expiry Date: ${doc.expiry_date}` : ''}
                                    </small></p>
                                ` : ''}
                                <div class="document-actions">
                                    <button class="btn btn-primary btn-sm" onclick="viewDocument(${doc.id}, 'personal')" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="downloadDocument(${doc.id}, 'personal')" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error loading personal documents:', error);
                    contentContainer.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading documents. Please try again later.</p>
                        </div>`;
                });
        }

        function loadPolicyDocuments() {
            console.log('Loading policy documents...');
            fetch('get_policy_documents.php')
                .then(response => {
                    console.log('Raw response:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Policy data:', data);
                    const container = document.getElementById('policyDocuments');
                    
                    if (!data.policies || data.policies.length === 0) {
                        container.innerHTML = `
                            <div class="no-documents">
                                <i class="fas fa-folder-open"></i>
                                <p>No policy documents available</p>
                            </div>`;
                        return;
                    }

                    container.innerHTML = data.policies.map(policy => `
                        <div class="document-item">
                            <div class="document-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="document-details">
                                <h3>
                                    ${policy.policy_name}
                                    <span class="status-badge status-${policy.status.toLowerCase()}">
                                        ${policy.status.charAt(0).toUpperCase() + policy.status.slice(1)}
                                    </span>
                                </h3>
                                <p>Type: ${formatPolicyType(policy.policy_type)}</p>
                                <p><small>
                                    Last updated: ${policy.updated_at || policy.created_at}
                                    • Size: ${formatFileSize(policy.file_size)}
                                </small></p>
                                <div class="document-actions">
                                    <button class="btn btn-primary btn-sm" onclick="viewDocument(${policy.id}, 'policy')" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="downloadDocument(${policy.id}, 'policy')" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    ${policy.status === 'pending' ? `
                                        <button class="btn btn-success btn-sm" onclick="acknowledgePolicyDocument(${policy.id})" title="Acknowledge">
                                            <i class="fas fa-check"></i> Acknowledge
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error loading policy documents:', error);
                    document.getElementById('policyDocuments').innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading documents. Please try again later.</p>
                        </div>`;
                });
        }

        function formatPolicyType(type) {
            return type.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }

        function acknowledgePolicyDocument(policyId) {
            Swal.fire({
                title: 'Acknowledge Policy',
                text: 'By acknowledging this policy, you confirm that you have read and understood its contents.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Acknowledge',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('acknowledge_policy.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            policy_id: policyId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Success', 'Policy acknowledged successfully', 'success');
                            loadPolicyDocuments();
                        } else {
                            throw new Error(data.message || 'Failed to acknowledge policy');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', error.message, 'error');
                    });
                }
            });
        }

        function viewDocument(docId, type) {
            console.log(`Attempting to view document: ID=${docId}, Type=${type}`);
            
            window.open(`direct_view_document.php?id=${docId}&type=${type}`, '_blank');
        }

        function downloadDocument(docId, type) {
            const link = document.createElement('a');
            link.href = `down_document.php?id=${docId}&type=${type}`;
            link.setAttribute('download', '');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function togglePanel() {
            const panel = document.getElementById('leftPanel');
            const mainContent = document.getElementById('mainContent');
            const icon = document.getElementById('toggleIcon');
            panel.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
            icon.classList.toggle('fa-chevron-left');
            icon.classList.toggle('fa-chevron-right');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const leftPanel = document.querySelector('.left-panel');
            const mainContent = document.getElementById('mainContent');
            const overlay = document.createElement('div');
            
            // Create overlay for mobile
            overlay.classList.add('panel-overlay');
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.right = '0';
            overlay.style.bottom = '0';
            overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
            overlay.style.zIndex = '998';
            overlay.style.display = 'none';
            document.body.appendChild(overlay);
            
            // Toggle menu function
            function toggleMenu() {
                leftPanel.classList.toggle('show');
                if (leftPanel.classList.contains('show')) {
                    overlay.style.display = 'block';
                } else {
                    overlay.style.display = 'none';
                }
            }
            
            // Event listeners
            hamburgerMenu.addEventListener('click', toggleMenu);
            overlay.addEventListener('click', toggleMenu);
            
            // Close menu when window is resized to larger size
            window.addEventListener('resize', function() {
                if (window.innerWidth > 991 && leftPanel.classList.contains('show')) {
                    leftPanel.classList.remove('show');
                    overlay.style.display = 'none';
                }
            });
            
            // Tab switching functionality
            document.querySelectorAll('.profile-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and sections
                    document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding section
                    tab.classList.add('active');
                    document.getElementById(tab.dataset.tab).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>