<!DOCTYPE html>
<html lang="en" style="height: 100%; overflow: auto;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Vendor Management - HR System</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --white: #ffffff;
            --gray: #95a5a6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html, body {
            height: 100%;
            min-height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            position: relative;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            margin-left: 250px; /* Add margin to accommodate the left panel */
            transition: margin-left 0.3s ease;
            width: calc(100% - 250px);
            margin-top: 0; /* Ensure no extra top margin */
            position: absolute;
            top: 0;
            right: 0;
        }
        
        /* When left panel is collapsed */
        .container.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
            max-width: 100%;
        }

        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .logo span {
            color: var(--secondary-color);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 0;
            padding-top: 0;
            width: 100%;
        }

        .sidebar {
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            height: fit-content;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .sidebar-menu i {
            font-size: 18px;
        }

        .main-content {
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-top: 0; /* Ensure no extra top margin */
            width: 100%;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .page-header div {
            display: flex;
            gap: 10px;
        }

        .page-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-success {
            background-color: var(--success-color);
            color: var(--white);
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: var(--white);
        }

        .btn-warning:hover {
            background-color: #d35400;
        }

        .table-container {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--secondary-color);
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .action-btns {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 5px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
            width: 500px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .search-filter {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 35px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .search-box i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .filter-dropdown {
            position: relative;
        }

        .filter-btn {
            padding: 10px 15px;
            background-color: var(--white);
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-options {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--white);
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
            min-width: 200px;
            z-index: 100;
            display: none;
        }

        .filter-options.show {
            display: block;
        }

        .filter-option {
            padding: 8px 0;
            cursor: pointer;
        }

        .filter-option:hover {
            color: var(--primary-color);
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
        }

        .stat-card i {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: var(--gray);
            font-size: 14px;
        }

        .card-primary {
            border-top: 3px solid var(--primary-color);
        }

        .card-success {
            border-top: 3px solid var(--success-color);
        }

        .card-warning {
            border-top: 3px solid var(--warning-color);
        }

        .card-danger {
            border-top: 3px solid var(--danger-color);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background-color: #f8f9fa;
        }

        .page-link.active {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }

        @media (max-width: 992px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .search-filter {
                flex-direction: column;
            }

            .search-box {
                max-width: 100%;
            }
            
            .mobile-header {
                display: flex;
            }
            
            .left-panel {
                transform: translateX(-100%);
                width: 80%;
                max-width: 300px;
                top: 60px; /* Below mobile header */
                height: calc(100vh - 60px);
                transition: transform 0.3s ease;
            }
            
            .left-panel.mobile-open {
                transform: translateX(0);
            }
            
            .container {
                margin-left: 0;
                width: 100%;
                padding-top: 70px; /* Space for mobile header */
            }
            
            .container.expanded {
                margin-left: 0;
                width: 100%;
            }
            
            body.panel-open {
                overflow: hidden;
            }
            
            .panel-overlay {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                width: 100%;
                height: calc(100vh - 60px);
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .panel-overlay.active {
                display: block;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .page-header div {
                width: 100%;
                justify-content: space-between;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .btn {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .table-container {
                margin: 0 -15px;
                width: calc(100% + 30px);
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px 10px;
            }
        }
        
        /* Extra small devices (phones, 375px and down) */
        @media (max-width: 375px) {
            .stats-cards {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 20px;
            }
            
            .stat-card {
                padding: 15px 10px;
            }
            
            .stat-card .value {
                font-size: 22px;
            }
            
            .stat-card i {
                font-size: 20px;
            }
            
            .btn {
                padding: 6px 8px;
                font-size: 12px;
            }
            
            .page-title {
                font-size: 18px;
            }
            
            .mobile-title {
                font-size: 16px;
            }
            
            /* Optimize table for very small screens */
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 6px 8px;
            }
            
            /* Hide less important columns on very small screens */
            table th:nth-child(4), 
            table td:nth-child(4),
            table th:nth-child(5), 
            table td:nth-child(5) {
                display: none;
            }
            
            /* Responsive table for mobile */
            .responsive-table-wrapper {
                display: block;
            }
            
            .desktop-table {
                display: none;
            }
            
            .mobile-vendor-card {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                padding: 12px;
                margin-bottom: 10px;
            }
            
            .mobile-vendor-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 8px;
                border-bottom: 1px solid #eee;
                padding-bottom: 8px;
            }
            
            .mobile-vendor-name {
                font-weight: 600;
                font-size: 14px;
            }
            
            .mobile-vendor-id {
                font-size: 12px;
                color: #777;
            }
            
            .mobile-vendor-info {
                font-size: 12px;
                margin-bottom: 10px;
            }
            
            .mobile-vendor-info p {
                margin: 5px 0;
                display: flex;
            }
            
            .mobile-vendor-info p span:first-child {
                font-weight: 500;
                width: 80px;
                color: #666;
            }
            
            .mobile-vendor-actions {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                border-top: 1px solid #eee;
                padding-top: 8px;
                margin-top: 5px;
            }
            
            /* Make action buttons smaller */
            .action-btn {
                padding: 4px 6px;
                font-size: 10px;
            }
            
            /* Adjust modal for small screens */
            .modal-content {
                width: 95%;
            }
            
            .modal-title {
                font-size: 16px;
            }
            
            .form-control {
                padding: 8px;
            }
        }
        
        /* Optimize for landscape orientation on mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .mobile-header {
                height: 50px;
            }
            
            .left-panel {
                top: 50px;
                height: calc(100vh - 50px);
            }
            
            .container {
                padding-top: 60px;
            }
            
            .panel-overlay {
                top: 50px;
                height: calc(100vh - 50px);
            }
            
            .stats-cards {
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }
            
            .stat-card {
                padding: 10px;
            }
            
            .stat-card .value {
                font-size: 20px;
                margin-bottom: 2px;
            }
            
            .stat-card i {
                font-size: 18px;
                margin-bottom: 5px;
            }
            
            .stat-card .label {
                font-size: 12px;
            }
        }
        
        /* Fix for iPhone notch */
        @supports (padding-top: env(safe-area-inset-top)) {
            .mobile-header {
                padding-top: env(safe-area-inset-top);
                height: calc(60px + env(safe-area-inset-top));
            }
            
            .left-panel {
                top: calc(60px + env(safe-area-inset-top));
                height: calc(100vh - 60px - env(safe-area-inset-top));
                padding-bottom: env(safe-area-inset-bottom);
            }
            
            .panel-overlay {
                top: calc(60px + env(safe-area-inset-top));
                height: calc(100vh - 60px - env(safe-area-inset-top));
            }
            
            .container {
                padding-top: calc(70px + env(safe-area-inset-top));
                padding-bottom: env(safe-area-inset-bottom);
            }
        }
        
        /* Fix for content visibility on larger screens */
        html, body {
            height: 100%;
            min-height: 100%;
        }
        
        .dashboard {
            min-height: calc(100vh - 40px);
            margin-top: 0;
            padding-top: 0;
        }
        
        /* Force content to top of page */
        body {
            display: block !important;
        }
        
        .left-panel {
            position: fixed !important;
            z-index: 1001 !important;
        }
        
        @media (min-width: 769px) {
            .container {
                padding-top: 0;
                margin-top: 0;
                top: 0 !important;
            }
            
            .main-content {
                margin-top: 0;
            }
            
            body::before {
                content: none !important;
            }
        }
        
        /* Left Panel Styling */
        .left-panel {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #1a237e;
            color: #ffffff;
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            z-index: 1000;
            transition: width 0.3s ease;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .left-panel::-webkit-scrollbar {
            display: none;
        }
        
        .left-panel.collapsed {
            width: 70px;
        }
        
        .brand-logo {
            background-color: #0d1757;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .brand-logo img {
            max-width: 140px;
            height: auto;
        }
        
        .toggle-btn {
            position: absolute;
            top: 15px;
            right: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #ffffff;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .menu-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: rgba(255, 255, 255, 0.8);
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        
        .menu-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-left: 3px solid #4fc3f7;
            color: #ffffff;
        }
        
        .menu-item i {
            font-size: 18px;
            min-width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .menu-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .section-start {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 5px;
            padding-top: 15px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            cursor: default;
        }
        
        .section-start:hover {
            background-color: transparent;
        }
        
        .logout-item {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #ff8a80;
        }
        
        /* When panel is collapsed */
        .left-panel.collapsed .menu-text,
        .left-panel.collapsed .brand-logo span {
            display: none;
        }
        
        .left-panel.collapsed .menu-item {
            padding: 15px 0;
            justify-content: center;
        }
        
        .left-panel.collapsed .menu-item i {
            margin-right: 0;
            font-size: 20px;
        }
        
        .left-panel.collapsed .section-start {
            height: 10px;
            padding: 0;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        
        .left-panel.collapsed .section-start i,
        .left-panel.collapsed .section-start .menu-text {
            display: none;
        }
        
        /* Mobile Responsive Styles */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 60px;
            background-color: #1a237e;
            color: white;
            z-index: 1001;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            align-items: center;
            padding: 0 15px;
        }
        
        .hamburger-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-title {
            font-size: 18px;
            font-weight: 600;
            margin-left: 15px;
        }
        
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
            }
            
            .left-panel {
                transform: translateX(-100%);
                width: 80%;
                max-width: 300px;
                top: 60px; /* Below mobile header */
                height: calc(100vh - 60px);
                transition: transform 0.3s ease;
            }
            
            .left-panel.mobile-open {
                transform: translateX(0);
            }
            
            .container {
                margin-left: 0;
                width: 100%;
                padding-top: 70px; /* Space for mobile header */
            }
            
            .container.expanded {
                margin-left: 0;
                width: 100%;
            }
            
            body.panel-open {
                overflow: hidden;
            }
            
            .panel-overlay {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                width: 100%;
                height: calc(100vh - 60px);
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .panel-overlay.active {
                display: block;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .page-header div {
                width: 100%;
                justify-content: space-between;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .btn {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .table-container {
                margin: 0 -15px;
                width: calc(100% + 30px);
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px 10px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body onload="window.scrollTo(0,0);">
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger-btn" id="hamburgerBtn">
            <i class="fas fa-bars"></i>
        </button>
        <div class="mobile-title">Vendor Management</div>
    </div>
    
    <!-- Panel Overlay (for mobile) -->
    <div class="panel-overlay" id="panelOverlay"></div>
    
    <?php include 'includes/manager_panel.php'; ?>
    
    <div class="container" style="position: absolute; top: 0; right: 0; margin-top: 0; padding-top: 0; max-width: 100%;">
        <div class="dashboard">

            <div class="main-content">
                <div class="page-header">
                    <h1 class="page-title">Vendor Management</h1>
                    <div>
                        <button class="btn btn-primary" id="addVendorBtn">
                            <i class="fas fa-plus"></i> Add Vendor
                        </button>
                        <button class="btn btn-success">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                        <button class="btn btn-warning">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <div class="stats-cards">
                    <div class="stat-card card-primary">
                        <i class="fas fa-users text-primary"></i>
                        <div class="value">124</div>
                        <div class="label">Total Vendors</div>
                    </div>
                    <div class="stat-card card-success">
                        <i class="fas fa-check-circle text-success"></i>
                        <div class="value">98</div>
                        <div class="label">Active Vendors</div>
                    </div>
                    <div class="stat-card card-warning">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <div class="value">18</div>
                        <div class="label">Pending Review</div>
                    </div>
                    <div class="stat-card card-danger">
                        <i class="fas fa-times-circle text-danger"></i>
                        <div class="value">8</div>
                        <div class="label">Inactive Vendors</div>
                    </div>
                </div>

                <div class="search-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search vendors...">
                    </div>
                    <div class="filter-dropdown">
                        <button class="filter-btn" id="filterBtn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <div class="filter-options" id="filterOptions">
                            <div class="filter-option" data-filter="all">All Vendors</div>
                            <div class="filter-option" data-filter="active">Active Only</div>
                            <div class="filter-option" data-filter="pending">Pending Review</div>
                            <div class="filter-option" data-filter="inactive">Inactive</div>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="desktop-table">
                        <table id="vendorsTable">
                            <thead>
                                <tr>
                                    <th>Vendor ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Services</th>
                                    <th>Contract Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Vendor data will be populated here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="responsive-table-wrapper" style="display: none;">
                        <div id="mobileVendorCards">
                            <!-- Mobile vendor cards will be populated here by JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="pagination">
                    <ul>
                        <li class="page-item"><a href="#" class="page-link">Previous</a></li>
                        <li class="page-item"><a href="#" class="page-link active">1</a></li>
                        <li class="page-item"><a href="#" class="page-link">2</a></li>
                        <li class="page-item"><a href="#" class="page-link">3</a></li>
                        <li class="page-item"><a href="#" class="page-link">Next</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Vendor Modal -->
    <div class="modal" id="vendorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Vendor</h3>
                <button class="close-btn" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="vendorForm">
                    <input type="hidden" id="vendorId">
                    <div class="form-group">
                        <label for="vendorName">Vendor Name</label>
                        <input type="text" id="vendorName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="contactPerson">Contact Person</label>
                        <input type="text" id="contactPerson" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="services">Services</label>
                        <select id="services" class="form-control" multiple>
                            <option value="IT Services">IT Services</option>
                            <option value="Consulting">Consulting</option>
                            <option value="Logistics">Logistics</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Facilities">Facilities</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="contractDate">Contract Date</label>
                        <input type="date" id="contractDate" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" id="cancelBtn">Cancel</button>
                <button class="btn btn-success" id="saveBtn">Save Vendor</button>
            </div>
        </div>
    </div>

    <script>
        // Sample vendor data
        const vendors = [
            {
                id: "VEN001",
                name: "Tech Solutions Inc.",
                contact: "Jane Smith",
                email: "jane@techsolutions.com",
                phone: "(555) 123-4567",
                services: ["IT Services", "Consulting"],
                contractDate: "2023-01-15",
                status: "Active"
            },
            {
                id: "VEN002",
                name: "Global Logistics",
                contact: "Mike Johnson",
                email: "mike@globallogistics.com",
                phone: "(555) 987-6543",
                services: ["Logistics"],
                contractDate: "2023-02-20",
                status: "Active"
            },
            {
                id: "VEN003",
                name: "Creative Marketing",
                contact: "Sarah Williams",
                email: "sarah@creativemarketing.com",
                phone: "(555) 456-7890",
                services: ["Marketing"],
                contractDate: "2023-03-10",
                status: "Pending"
            },
            {
                id: "VEN004",
                name: "Facility Pro",
                contact: "David Brown",
                email: "david@facilitypro.com",
                phone: "(555) 789-0123",
                services: ["Facilities"],
                contractDate: "2022-11-05",
                status: "Inactive"
            },
            {
                id: "VEN005",
                name: "Data Analytics Co.",
                contact: "Emily Chen",
                email: "emily@dataanalytics.com",
                phone: "(555) 234-5678",
                services: ["IT Services", "Consulting"],
                contractDate: "2023-04-18",
                status: "Active"
            },
            {
                id: "VEN006",
                name: "Security Systems Ltd.",
                contact: "Robert Taylor",
                email: "robert@securitysystems.com",
                phone: "(555) 345-6789",
                services: ["IT Services", "Facilities"],
                contractDate: "2023-01-30",
                status: "Pending"
            },
            {
                id: "VEN007",
                name: "Office Supplies Plus",
                contact: "Lisa Anderson",
                email: "lisa@officesupplies.com",
                phone: "(555) 678-9012",
                services: ["Facilities"],
                contractDate: "2022-12-12",
                status: "Active"
            },
            {
                id: "VEN008",
                name: "Cloud Computing Partners",
                contact: "James Wilson",
                email: "james@cloudpartners.com",
                phone: "(555) 901-2345",
                services: ["IT Services"],
                contractDate: "2023-05-22",
                status: "Active"
            }
        ];

        // DOM elements
        const vendorsTable = document.getElementById('vendorsTable').getElementsByTagName('tbody')[0];
        const addVendorBtn = document.getElementById('addVendorBtn');
        const vendorModal = document.getElementById('vendorModal');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const saveBtn = document.getElementById('saveBtn');
        const vendorForm = document.getElementById('vendorForm');
        const searchInput = document.getElementById('searchInput');
        const filterBtn = document.getElementById('filterBtn');
        const filterOptions = document.getElementById('filterOptions');
        const filterOptionItems = document.querySelectorAll('.filter-option');

        // Current filter
        let currentFilter = 'all';
        let isEditing = false;
        let currentVendorId = null;

        // Initialize the page
        function init() {
            renderVendors(vendors);
            setupEventListeners();
        }
        
        // Check screen size and toggle between desktop and mobile view
        function checkScreenSize() {
            const desktopTable = document.querySelector('.desktop-table');
            const mobileTable = document.querySelector('.responsive-table-wrapper');
            
            if (window.innerWidth <= 575) {
                desktopTable.style.display = 'none';
                mobileTable.style.display = 'block';
            } else {
                desktopTable.style.display = 'block';
                mobileTable.style.display = 'none';
            }
        }
        
        // Listen for window resize events
        window.addEventListener('resize', checkScreenSize);

        // Render vendors to the table
        function renderVendors(vendorsToRender) {
            vendorsTable.innerHTML = '';
            const mobileVendorCards = document.getElementById('mobileVendorCards');
            mobileVendorCards.innerHTML = '';
            
            vendorsToRender.forEach(vendor => {
                // Format services
                const services = vendor.services.join(', ');
                
                // Format contract date
                const contractDate = new Date(vendor.contractDate);
                const formattedDate = contractDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                
                // Status badge
                let statusBadge = '';
                let statusClass = '';
                if (vendor.status === 'Active') {
                    statusBadge = `<span class="badge badge-success">${vendor.status}</span>`;
                    statusClass = 'badge-success';
                } else if (vendor.status === 'Pending') {
                    statusBadge = `<span class="badge badge-warning">${vendor.status}</span>`;
                    statusClass = 'badge-warning';
                } else {
                    statusBadge = `<span class="badge badge-danger">${vendor.status}</span>`;
                    statusClass = 'badge-danger';
                }
                
                // Desktop table row
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${vendor.id}</td>
                    <td>${vendor.name}</td>
                    <td>${vendor.contact}<br><small>${vendor.email}</small></td>
                    <td>${services}</td>
                    <td>${formattedDate}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="action-btns">
                            <button class="action-btn btn-primary edit-btn" data-id="${vendor.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn btn-danger delete-btn" data-id="${vendor.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                
                vendorsTable.appendChild(row);
                
                // Mobile card
                const mobileCard = document.createElement('div');
                mobileCard.className = 'mobile-vendor-card';
                mobileCard.innerHTML = `
                    <div class="mobile-vendor-header">
                        <div class="mobile-vendor-name">${vendor.name}</div>
                        <div class="mobile-vendor-id">${vendor.id}</div>
                    </div>
                    <div class="mobile-vendor-info">
                        <p><span>Contact:</span> <span>${vendor.contact}</span></p>
                        <p><span>Email:</span> <span>${vendor.email}</span></p>
                        <p><span>Phone:</span> <span>${vendor.phone}</span></p>
                        <p><span>Status:</span> <span class="badge ${statusClass}">${vendor.status}</span></p>
                    </div>
                    <div class="mobile-vendor-actions">
                        <button class="btn btn-primary edit-btn" data-id="${vendor.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger delete-btn" data-id="${vendor.id}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `;
                
                mobileVendorCards.appendChild(mobileCard);
            });
            
            // Add event listeners to edit and delete buttons
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const vendorId = e.currentTarget.getAttribute('data-id');
                    editVendor(vendorId);
                });
            });
            
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const vendorId = e.currentTarget.getAttribute('data-id');
                    deleteVendor(vendorId);
                });
            });
            
            // Toggle between desktop and mobile view based on screen width
            checkScreenSize();
        }

        // Set up event listeners
        function setupEventListeners() {
            // Add vendor button
            addVendorBtn.addEventListener('click', () => {
                isEditing = false;
                currentVendorId = null;
                document.getElementById('modalTitle').textContent = 'Add New Vendor';
                vendorForm.reset();
                vendorModal.style.display = 'flex';
            });
            
            // Close modal buttons
            closeModal.addEventListener('click', () => {
                vendorModal.style.display = 'none';
            });
            
            cancelBtn.addEventListener('click', () => {
                vendorModal.style.display = 'none';
            });
            
            // Save vendor
            saveBtn.addEventListener('click', saveVendor);
            
            // Search functionality
            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.toLowerCase();
                filterVendors(searchTerm, currentFilter);
            });
            
            // Filter dropdown
            filterBtn.addEventListener('click', () => {
                filterOptions.classList.toggle('show');
            });
            
            // Filter options
            filterOptionItems.forEach(option => {
                option.addEventListener('click', () => {
                    currentFilter = option.getAttribute('data-filter');
                    filterBtn.innerHTML = `<i class="fas fa-filter"></i> ${option.textContent}`;
                    filterOptions.classList.remove('show');
                    filterVendors(searchInput.value.toLowerCase(), currentFilter);
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', (e) => {
                if (e.target === vendorModal) {
                    vendorModal.style.display = 'none';
                }
            });
        }

        // Filter vendors based on search term and status filter
        function filterVendors(searchTerm, filter) {
            let filteredVendors = vendors;
            
            // Apply status filter
            if (filter !== 'all') {
                filteredVendors = filteredVendors.filter(vendor => vendor.status === filter || 
                    (filter === 'pending' && vendor.status === 'Pending') ||
                    (filter === 'active' && vendor.status === 'Active') ||
                    (filter === 'inactive' && vendor.status === 'Inactive'));
            }
            
            // Apply search filter
            if (searchTerm) {
                filteredVendors = filteredVendors.filter(vendor => 
                    vendor.name.toLowerCase().includes(searchTerm) ||
                    vendor.contact.toLowerCase().includes(searchTerm) ||
                    vendor.email.toLowerCase().includes(searchTerm) ||
                    vendor.phone.toLowerCase().includes(searchTerm) ||
                    vendor.id.toLowerCase().includes(searchTerm) ||
                    vendor.services.some(service => service.toLowerCase().includes(searchTerm))
                );
            }
            
            renderVendors(filteredVendors);
        }

        // Edit vendor
        function editVendor(vendorId) {
            isEditing = true;
            currentVendorId = vendorId;
            document.getElementById('modalTitle').textContent = 'Edit Vendor';
            
            const vendor = vendors.find(v => v.id === vendorId);
            if (vendor) {
                document.getElementById('vendorId').value = vendor.id;
                document.getElementById('vendorName').value = vendor.name;
                document.getElementById('contactPerson').value = vendor.contact;
                document.getElementById('email').value = vendor.email;
                document.getElementById('phone').value = vendor.phone;
                
                // Set services (for a real app, you'd need more complex handling for multiple select)
                const servicesSelect = document.getElementById('services');
                Array.from(servicesSelect.options).forEach(option => {
                    option.selected = vendor.services.includes(option.value);
                });
                
                document.getElementById('contractDate').value = vendor.contractDate;
                document.getElementById('status').value = vendor.status;
                
                vendorModal.style.display = 'flex';
            }
        }

        // Delete vendor
        function deleteVendor(vendorId) {
            if (confirm('Are you sure you want to delete this vendor?')) {
                const index = vendors.findIndex(v => v.id === vendorId);
                if (index !== -1) {
                    vendors.splice(index, 1);
                    renderVendors(vendors);
                    alert('Vendor deleted successfully');
                }
            }
        }

        // Save vendor (add or update)
        function saveVendor() {
            // Get form values
            const vendorName = document.getElementById('vendorName').value;
            const contactPerson = document.getElementById('contactPerson').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            
            // Get selected services (simplified for this example)
            const servicesSelect = document.getElementById('services');
            const selectedServices = [];
            Array.from(servicesSelect.options).forEach(option => {
                if (option.selected) {
                    selectedServices.push(option.value);
                }
            });
            
            const contractDate = document.getElementById('contractDate').value;
            const status = document.getElementById('status').value;
            
            // Validate
            if (!vendorName || !contactPerson || !email || !phone || selectedServices.length === 0 || !contractDate) {
                alert('Please fill in all required fields');
                return;
            }
            
            if (isEditing) {
                // Update existing vendor
                const vendor = vendors.find(v => v.id === currentVendorId);
                if (vendor) {
                    vendor.name = vendorName;
                    vendor.contact = contactPerson;
                    vendor.email = email;
                    vendor.phone = phone;
                    vendor.services = selectedServices;
                    vendor.contractDate = contractDate;
                    vendor.status = status;
                    
                    alert('Vendor updated successfully');
                }
            } else {
                // Add new vendor
                const newId = `VEN${String(vendors.length + 1).padStart(3, '0')}`;
                const newVendor = {
                    id: newId,
                    name: vendorName,
                    contact: contactPerson,
                    email: email,
                    phone: phone,
                    services: selectedServices,
                    contractDate: contractDate,
                    status: status
                };
                
                vendors.push(newVendor);
                alert('Vendor added successfully');
            }
            
            // Refresh the table and close modal
            renderVendors(vendors);
            vendorModal.style.display = 'none';
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', init);
        
        // Handle left panel toggle
        document.addEventListener('DOMContentLoaded', function() {
            const leftPanelToggleBtn = document.getElementById('leftPanelToggleBtn');
            const leftPanel = document.getElementById('leftPanel');
            const container = document.querySelector('.container');
            const toggleIcon = document.getElementById('toggleIcon');
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const panelOverlay = document.getElementById('panelOverlay');
            
            // Check if toggle button exists (it's in the included manager_panel.php)
            if (leftPanelToggleBtn) {
                leftPanelToggleBtn.addEventListener('click', function() {
                    leftPanel.classList.toggle('collapsed');
                    container.classList.toggle('expanded');
                    toggleIcon.classList.toggle('fa-chevron-left');
                    toggleIcon.classList.toggle('fa-chevron-right');
                });
            }
            
            // Mobile hamburger menu
            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function() {
                    leftPanel.classList.toggle('mobile-open');
                    document.body.classList.toggle('panel-open');
                    panelOverlay.classList.toggle('active');
                    
                    // Change hamburger icon
                    const hamburgerIcon = hamburgerBtn.querySelector('i');
                    hamburgerIcon.classList.toggle('fa-bars');
                    hamburgerIcon.classList.toggle('fa-times');
                });
            }
            
            // Close panel when clicking overlay
            if (panelOverlay) {
                panelOverlay.addEventListener('click', function() {
                    leftPanel.classList.remove('mobile-open');
                    document.body.classList.remove('panel-open');
                    panelOverlay.classList.remove('active');
                    
                    // Reset hamburger icon
                    const hamburgerIcon = hamburgerBtn.querySelector('i');
                    hamburgerIcon.classList.add('fa-bars');
                    hamburgerIcon.classList.remove('fa-times');
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    // Reset mobile panel state when returning to desktop
                    leftPanel.classList.remove('mobile-open');
                    document.body.classList.remove('panel-open');
                    panelOverlay.classList.remove('active');
                    
                    // Reset hamburger icon
                    const hamburgerIcon = hamburgerBtn.querySelector('i');
                    hamburgerIcon.classList.add('fa-bars');
                    hamburgerIcon.classList.remove('fa-times');
                }
            });
            
            // Also listen for keyboard shortcut (Ctrl+B)
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'b') {
                    e.preventDefault();
                    leftPanel.classList.toggle('collapsed');
                    container.classList.toggle('expanded');
                    if (toggleIcon) {
                        toggleIcon.classList.toggle('fa-chevron-left');
                        toggleIcon.classList.toggle('fa-chevron-right');
                    }
                }
            });
        });
    </script>
</body>
</html>