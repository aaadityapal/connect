<?php
session_start();
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        .settings-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .settings-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .settings-card .icon {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .settings-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .settings-card p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #666;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb i {
            font-size: 12px;
            color: #999;
        }

        .settings-header {
            margin-bottom: 30px;
        }

        .settings-header h2 {
            margin: 0;
            color: #333;
        }

        .settings-header p {
            margin: 5px 0 0 0;
            color: #666;
        }

        /* Animation for cards */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .settings-card {
            animation: fadeIn 0.5s ease forwards;
        }

        .settings-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .settings-card:nth-child(3) {
            animation-delay: 0.2s;
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin_dashboard.php">Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Settings</span>
        </div>

        <!-- Settings Header -->
        <div class="settings-header">
            <h2>Settings</h2>
            <p>Manage your system preferences and configurations</p>
        </div>

        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- Shift Management Card -->
            <div class="settings-card" onclick="window.location.href='admin_shifts.php'">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>Shift Management</h3>
                <p>Configure employee shifts, timings, and assignments</p>
            </div>

            <!-- Company Profile Card -->
            <div class="settings-card" onclick="window.location.href='company_profile.php'">
                <div class="icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3>Company Profile</h3>
                <p>Update company information and preferences</p>
            </div>

            <!-- System Settings Card -->
            <div class="settings-card" onclick="window.location.href='system_settings.php'">
                <div class="icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <h3>System Settings</h3>
                <p>Configure system-wide settings and parameters</p>
            </div>
        </div>
    </div>
</body>
</html>
