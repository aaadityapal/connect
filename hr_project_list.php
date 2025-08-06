<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Replace the SheetJS CDN with a more reliable one -->
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary-color: #3949ab;
            --secondary-color: #303f9f;
            --accent-color: #3d5afe;
            --success-color: #00acc1;
            --light-bg: #f0f2f5;
            --dark-text: #1e293b;
            --muted-text: #64748b;
            --border-color: #cbd5e1;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.08);
            --hover-shadow: 0 6px 12px rgba(0,0,0,0.15);
            --table-header-bg: #eef2ff;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --transition-speed: 0.3s;
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #7C3AED;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --dark: #111827;
            --gray: #6B7280;
            --light: #F3F4F6;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--dark-text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
        }
        
        /* Increase container width for larger screens */
        @media (min-width: 1400px) {
            .container {
                max-width: 1320px;
            }
        }
        
        @media (min-width: 1600px) {
            .container {
                max-width: 1540px;
            }
        }
        
        /* Sidebar styles */
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
            min-height: 100vh;
        }
        
        /* Modern Sidebar */
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
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .sidebar::-webkit-scrollbar {
            display: none;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
        }

        .main-content.expanded {
            margin-left: 0;
        }

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
            background: var(--primary);
            color: white;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }


        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link {
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }
        
        /* Update nav container to allow for margin-top: auto on logout */
        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px); /* Adjust based on your logo height */
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        /* Hide scrollbar for nav element in Chrome, Safari and Opera */
        .sidebar nav::-webkit-scrollbar {
            display: none;
        }
        
        /* Logout button styles */
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
        
        .sidebar-menu li a {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin: 0 10px;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .sidebar-menu li a:hover {
            color: #333;
            background: rgba(0,0,0,0.03);
            border-radius: 5px;
            margin: 0 10px;
        }
        
        .sidebar-menu li.active a {
            color: #4361ee;
            background: rgba(67, 97, 238, 0.1);
            box-shadow: none;
            border-radius: 5px;
            margin: 0 10px;
            font-weight: 600;
        }
        
        .sidebar-menu li a i {
            min-width: 30px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        /* Dashboard icon colors */
        .sidebar-menu li a i.fa-tachometer-alt {
            color: #FF6B6B;
        }
        
        .sidebar-menu li a i.fa-calendar-check {
            color: #4ECDC4;
        }
        
        .sidebar-menu li a i.fa-users {
            color: #FFD166;
        }
        
        .sidebar-menu li a i.fa-box {
            color: #6A8EFF;
        }
        
        .sidebar-menu li a i.fa-chart-line {
            color: #F72585;
        }
        
        .sidebar-menu li a i.fa-file-invoice {
            color: #4CC9F0;
        }
        
        .sidebar-menu li a i.fa-clock {
            color: #8AC926;
        }
        
        .sidebar-menu li a i.fa-hourglass-half {
            color: #A5B4FC;
        }
        
        .sidebar-menu li a i.fa-plane {
            color: #FB5607;
        }
        
        .sidebar-menu li a i.fa-user {
            color: #06D6A0;
        }
        
        .sidebar-menu li a i.fa-bell {
            color: #FFD166;
        }
        
        .sidebar-menu li a i.fa-cog {
            color: #A5B4FC;
        }
        
        .sidebar-menu li a i.fa-lock {
            color: #F72585;
        }
        
        .sidebar-menu li a i.fa-sign-out-alt {
            color: #FF6B6B;
        }
        
        .sidebar-menu li a .sidebar-text {
            margin-left: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-transform: none;
            color: inherit;
            opacity: 1;
        }
        
        .sidebar.collapsed .sidebar-menu li a .sidebar-text {
            opacity: 0;
            width: 0;
        }
        
        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(0,0,0,0.05);
            margin-top: auto;
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            color: #ff6b6b !important;
            padding: 10px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,107,107,0.1) !important;
        }
        
        .logout-btn i {
            margin-right: 10px;
        }
        
        #content {
            width: calc(100% - 280px);
            min-height: 100vh;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-left: 280px;
        }
        
        #content.expanded {
            width: calc(100% - 70px);
            margin-left: 70px;
        }
        
        .page-header {
            background-color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }
        
        .page-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0;
        }
        
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: var(--hover-shadow);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        .filter-section {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--muted-text);
        }
        
        .form-control, .form-select {
            border-radius: 0.375rem;
            border: 1px solid var(--border-color);
            padding: 0.5rem 0.75rem;
            font-size: 0.95rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        .btn {
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-outline-secondary {
            color: var(--muted-text);
            border-color: var(--border-color);
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: var(--dark-text);
        }
        
        .export-btn {
            margin-bottom: 1rem;
        }
        
        .table-container {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: var(--card-shadow);
            overflow-x: auto;
            width: 100%;
            max-width: 100%;
        }
        
        .data-table {
            margin-bottom: 0;
            width: 100%;
            table-layout: fixed;
        }
        
        /* Column width adjustments */
        .data-table th.col-id {
            width: 5%;
        }
        
        .data-table th.col-date {
            width: 10%;
        }
        
        .data-table th.col-title {
            width: 15%;
        }
        
        .data-table th.col-type {
            width: 15%;
        }
        
        .data-table th.col-location {
            width: 15%;
        }
        
        .data-table th.col-client {
            width: 12%;
        }
        
        .data-table th.col-assigned {
            width: 12%;
        }
        
        .data-table th.col-actions {
            width: 8%;
            min-width: 100px;
        }
        
        .data-table td .btn-group {
            display: flex;
            gap: 2px;
            justify-content: center;
        }
        
        .data-table td .action-btn {
            padding: 0.25rem 0.5rem;
            line-height: 1;
        }
        
        .data-table th {
            background-color: var(--table-header-bg);
            color: var(--muted-text);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.3px;
            padding: 0.5rem 0.25rem;
            border-top: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .data-table td {
            padding: 0.5rem 0.25rem;
            vertical-align: middle;
            color: var(--dark-text);
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .data-table tr:hover {
            background-color: rgba(67, 97, 238, 0.03);
        }
        
        /* Project Type Color Coding for Rows */
        .row-architecture {
            background-color: rgba(79, 70, 229, 0.05);
        }
        
        .row-architecture:hover {
            background-color: rgba(79, 70, 229, 0.1) !important;
        }
        
        .row-interior {
            background-color: rgba(139, 92, 246, 0.05);
        }
        
        .row-interior:hover {
            background-color: rgba(139, 92, 246, 0.1) !important;
        }
        
        .row-construction {
            background-color: rgba(8, 145, 178, 0.05);
        }
        
        .row-construction:hover {
            background-color: rgba(8, 145, 178, 0.1) !important;
        }
        
        .row-other {
            background-color: rgba(107, 114, 128, 0.02);
        }
        
        .row-other:hover {
            background-color: rgba(107, 114, 128, 0.07) !important;
        }
        
        /* Left border indicators for rows */
        .row-architecture td:first-child {
            border-left: 4px solid #4f46e5;
        }
        
        .row-interior td:first-child {
            border-left: 4px solid #8b5cf6;
        }
        
        .row-construction td:first-child {
            border-left: 4px solid #0891b2;
        }
        
        .row-other td:first-child {
            border-left: 4px solid #6b7280;
        }
        
        .status-active {
            color: #10b981;
            font-weight: 600;
            background-color: rgba(16, 185, 129, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-inactive {
            color: #ef4444;
            font-weight: 600;
            background-color: rgba(239, 68, 68, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            color: #f59e0b;
            font-weight: 600;
            background-color: rgba(245, 158, 11, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Project Type Color Coding */
        .project-type {
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .project-type-architecture {
            color: #4f46e5;
            background-color: rgba(79, 70, 229, 0.1);
            border-left: 3px solid #4f46e5;
        }
        
        .project-type-interior {
            color: #8b5cf6;
            background-color: rgba(139, 92, 246, 0.1);
            border-left: 3px solid #8b5cf6;
        }
        
        .project-type-construction {
            color: #0891b2;
            background-color: rgba(8, 145, 178, 0.1);
            border-left: 3px solid #0891b2;
        }
        
        .project-type-other {
            color: #6b7280;
            background-color: rgba(107, 114, 128, 0.1);
            border-left: 3px solid #6b7280;
        }
        
        .action-btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-info {
            background-color: rgba(67, 97, 238, 0.1);
            border-color: transparent;
            color: var(--primary-color);
        }
        
        .btn-info:hover {
            background-color: rgba(67, 97, 238, 0.2);
            color: var(--primary-color);
        }
        
        .btn-warning {
            background-color: rgba(245, 158, 11, 0.1);
            border-color: transparent;
            color: #f59e0b;
        }
        
        .btn-warning:hover {
            background-color: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: var(--muted-text);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #e2e8f0;
        }
        
        .empty-state h5 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            max-width: 400px;
            margin: 0 auto;
        }

        /* Additional styles for modal */
        .status-badge {
            padding: 0.35rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .status-badge-inactive {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .status-badge-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .modal-body .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: 0.5rem;
        }
        
        .modal-body .card-header {
            background-color: white;
            border-bottom: 1px solid #f0f0f0;
            padding: 1rem 1.25rem;
        }
        
        .modal-body .card-body {
            padding: 1.25rem;
        }
        
        .modal-body small.text-muted {
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .empty-value {
            color: #9ca3af;
            font-style: italic;
        }
        
        /* Substages styling */
        .substages-container .card {
            box-shadow: none;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .substages-container .card-header {
            background-color: rgba(0,0,0,0.02);
        }
        
        .toggle-substages {
            color: var(--primary-color);
            transition: all 0.2s;
        }
        
        .toggle-substages:hover {
            color: var(--secondary-color);
            transform: scale(1.1);
        }
        
        .substages-container {
            background-color: rgba(0,0,0,0.01);
            transition: all 0.3s ease;
        }
        
        /* Project Type Color Coding for Rows */
        /* Mobile responsiveness for sidebar */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -280px;
            }
            
            .sidebar.collapsed {
                margin-left: 0;
                width: 280px;
            }
            
            #content {
                width: 100%;
                margin-left: 0;
            }
            
            #content.expanded {
                width: 100%;
                margin-left: 0;
            }
            
            .sidebar.collapsed .sidebar-text,
            .sidebar.collapsed .sidebar-menu li a .sidebar-text {
                opacity: 1;
                width: auto;
            }
            
            /* Add overlay when sidebar is open on mobile */
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 998;
                display: none;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <?php
    // Include database connection
    require_once 'config/db_connect.php';
    
    // Initialize filter variables
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $projectType = isset($_GET['projectType']) ? $_GET['projectType'] : '';
    $categoryId = isset($_GET['categoryId']) ? $_GET['categoryId'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Build SQL query with filters
    $sql = "SELECT p.*, 
                   p.created_at,
                   p.contact_number,
                   u1.username as creator_name,
                   u2.username as assignee_name,
                   pc.name as category_name
            FROM projects p
            LEFT JOIN users u1 ON p.created_by = u1.id
            LEFT JOIN users u2 ON p.assigned_to = u2.id
            LEFT JOIN project_categories pc ON p.category_id = pc.id
            WHERE p.deleted_at IS NULL";
    
    $params = [];
    
    // Apply filters if set
    if (!empty($dateFrom)) {
        $sql .= " AND p.start_date >= :dateFrom";
        $params[':dateFrom'] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $sql .= " AND p.start_date <= :dateTo";
        $params[':dateTo'] = $dateTo;
    }
    
    if (!empty($status)) {
        $sql .= " AND p.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($projectType)) {
        $sql .= " AND p.project_type = :projectType";
        $params[':projectType'] = $projectType;
    }
    
    if (!empty($categoryId)) {
        $sql .= " AND p.category_id = :categoryId";
        $params[':categoryId'] = $categoryId;
    }
    
    if (!empty($search)) {
        $sql .= " AND (p.title LIKE :search OR p.description LIKE :search OR p.client_name LIKE :search OR p.project_location LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    // Get distinct project types and statuses for filter dropdowns
    $projectTypesQuery = "SELECT DISTINCT project_type FROM projects WHERE deleted_at IS NULL ORDER BY project_type";
    $statusesQuery = "SELECT DISTINCT status FROM projects WHERE deleted_at IS NULL ORDER BY status";
    
    // Get project categories for filter dropdown
    $categoriesQuery = "SELECT id, name, description FROM project_categories WHERE deleted_at IS NULL ORDER BY name";
    
    try {
        // Prepare and execute the main query
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $projects = $stmt->fetchAll();
        
        // Get project types for dropdown
        $projectTypesStmt = $pdo->query($projectTypesQuery);
        $projectTypes = $projectTypesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get statuses for dropdown
        $statusesStmt = $pdo->query($statusesQuery);
        $statuses = $statusesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get categories for dropdown
        $categoriesStmt = $pdo->query($categoriesQuery);
        $categories = $categoriesStmt->fetchAll();
        
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error fetching projects: ' . $e->getMessage() . '</div>';
        $projects = [];
        $projectTypes = [];
        $statuses = [];
        $categories = [];
    }
    
    // Count total projects
    $projectCount = count($projects);
    ?>

    <div class="wrapper">
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
                <a href="manager_payouts.php" class="nav-link">
                    <i class="bi bi-cash-coin"></i>
                    Manager Payouts
                </a>
                <a href="company_analytics_dashboard.php" class="nav-link">
                    <i class="bi bi-graph-up"></i>
                    Company Stats
                </a>
                <a href="salary_overview.php" class="nav-link">
                    <i class="bi bi-cash-coin"></i>
                    Salary
                </a>
                <a href="edit_leave.php" class="nav-link">
                    <i class="bi bi-calendar-check-fill"></i>
                    Leave Request
                </a>
                <a href="admin/manage_geofence_locations.php" class="nav-link">
                    <i class="bi bi-map"></i>
                    Geofence Locations
                </a>
                <a href="travelling_allowanceh.php" class="nav-link">
                    <i class="bi bi-car-front-fill"></i>
                    Travel Expenses
                </a>
                <a href="hr_overtime_approval.php" class="nav-link">
                    <i class="bi bi-clock"></i>
                    Overtime Approval
                </a>
                <a href="hr_project_list.php" class="nav-link active">
                    <i class="bi bi-diagram-3-fill"></i>
                    Projects
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

        <!-- Page Content -->
        <div id="content">
            <!-- Page Header -->
            <header class="page-header bg-dark text-white">
                <div class="container-fluid px-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h1 class="page-title"><i class="bi bi-kanban me-2"></i>Projects Dashboard</h1>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <a href="add_project.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> New Project
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="container-fluid px-4">
        <!-- Filter Section -->
                <div class="card filter-section">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>Filter Options</h5>
                        <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
                            <i class="bi bi-chevron-down"></i> Toggle Filters
                        </button>
                    </div>
                    <div class="collapse show" id="filterCollapse">
                        <div class="card-body">
                            <form id="filterForm" method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="dateFrom" class="form-label">Date From</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                            <input type="date" class="form-control" id="dateFrom" name="dateFrom" value="<?= htmlspecialchars($dateFrom) ?>">
                                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="dateTo" class="form-label">Date To</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                            <input type="date" class="form-control" id="dateTo" name="dateTo" value="<?= htmlspecialchars($dateTo) ?>">
                                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">All Statuses</option>
                                            <?php foreach ($statuses as $statusOption): ?>
                                                <option value="<?= htmlspecialchars($statusOption) ?>" <?= $status === $statusOption ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($statusOption) ?>
                                                </option>
                                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                                        <label for="projectType" class="form-label">Project Type</label>
                                        <select class="form-select" id="projectType" name="projectType">
                                            <option value="">All Types</option>
                                            <?php foreach ($projectTypes as $typeOption): ?>
                                                <option value="<?= htmlspecialchars($typeOption) ?>" <?= $projectType === $typeOption ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($typeOption) ?>
                                                </option>
                                            <?php endforeach; ?>
                        </select>
                    </div>
                                    <div class="col-md-3">
                                        <label for="categoryId" class="form-label">Category</label>
                                        <select class="form-select" id="categoryId" name="categoryId">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= htmlspecialchars($category['id']) ?>" <?= $categoryId == $category['id'] ? 'selected' : '' ?> title="<?= htmlspecialchars($category['description'] ?? '') ?>">
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by title, description, client or location..." value="<?= htmlspecialchars($search) ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-filter me-1"></i> Apply Filters
                                            </button>
                    </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="d-block text-muted mb-2">Actions</label>
                                        <div class="d-flex gap-2">
                                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-circle me-1"></i> Reset Filters
                                            </a>
                                            <button type="button" id="saveFilters" class="btn btn-outline-primary">
                                                <i class="bi bi-bookmark-plus me-1"></i> Save Filters
                                            </button>
                                        </div>
                    </div>
                </div>
            </form>
                        </div>
                    </div>
        </div>
        
                <!-- Results Summary and Export Button -->
                <div class="d-flex justify-content-between align-items-center mb-3 bg-dark text-white p-3 rounded">
                    <div>
                        <p class="mb-0">
                            <i class="bi bi-clipboard-data me-2"></i> 
                            Showing <strong><?= $projectCount ?></strong> project<?= $projectCount !== 1 ? 's' : '' ?>
                            <?= !empty($search) || !empty($status) || !empty($projectType) || !empty($categoryId) || !empty($dateFrom) || !empty($dateTo) ? '<span class="badge bg-primary ms-2">Filtered</span>' : '' ?>
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline-light export-btn" id="exportExcel">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
            </button>
        </div>
        
        <!-- Data Table -->
                <div class="table-container">
        <div class="table-responsive">
                        <table class="data-table table table-hover" id="dataTable">
                <thead>
                    <tr>
                                    <th class="col-id"><i class="bi bi-hash me-1"></i>ID</th>
                                    <th class="col-date"><i class="bi bi-calendar-event me-1"></i>Created On</th>
                                    <th class="col-title"><i class="bi bi-file-earmark-text me-1"></i>Title</th>
                                    <th class="col-type"><i class="bi bi-building me-1"></i>Project Type with Category</th>
                                    <th class="col-location"><i class="bi bi-geo-alt me-1"></i>Site Address</th>
                                    <th class="col-client"><i class="bi bi-telephone me-1"></i>Phone Number</th>
                                    <th class="col-assigned"><i class="bi bi-person-check me-1"></i>Assigned To</th>
                                    <th class="col-actions text-center"><i class="bi bi-gear me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                                <?php if (empty($projects)): ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <i class="bi bi-folder"></i>
                                                <h5>No projects found</h5>
                                                <p>Try adjusting your filters or create a new project to get started.</p>
                                            </div>
                                        </td>
                    </tr>
                                <?php else: ?>
                                    <?php foreach ($projects as $project): ?>
                                        <tr class="project-type-<?= strtolower($project['project_type']) ?>">
                                            <td><?= htmlspecialchars($project['id']) ?></td>
                                            <td><?= htmlspecialchars(date('M d, Y', strtotime($project['created_at']))) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($project['title']) ?></strong>
                                                <?php if (!empty($project['description'])): ?>
                                                    <div class="small text-muted text-truncate" style="max-width: 100%;" title="<?= htmlspecialchars($project['description']) ?>">
                                                        <?= htmlspecialchars(substr($project['description'], 0, 30)) ?><?= strlen($project['description']) > 30 ? '...' : '' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="project-type project-type-<?= strtolower($project['project_type']) ?>">
                                                    <?= htmlspecialchars($project['project_type']) ?>
                                                </span>
                                                <div class="small text-muted"><?= htmlspecialchars($project['category_name'] ?? 'N/A') ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($project['project_location'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($project['contact_number'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if (!empty($project['assignee_name'])): ?>
                                                    <?= htmlspecialchars($project['assignee_name']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="#" class="btn btn-sm btn-info action-btn view-project" 
                                                       title="View Details" 
                                                       data-bs-toggle="modal" 
                                                       data-bs-target="#projectDetailModal" 
                                                       data-id="<?= $project['id'] ?>"
                                                       data-title="<?= htmlspecialchars($project['title']) ?>"
                                                       data-description="<?= htmlspecialchars($project['description'] ?? '') ?>"
                                                       data-client="<?= htmlspecialchars($project['client_name'] ?? '') ?>"
                                                       data-location="<?= htmlspecialchars($project['project_location'] ?? '') ?>"
                                                       data-type="<?= htmlspecialchars($project['project_type']) ?>"
                                                       data-category="<?= htmlspecialchars($project['category_name'] ?? '') ?>"
                                                       data-start="<?= htmlspecialchars($project['start_date']) ?>"
                                                       data-end="<?= htmlspecialchars($project['end_date'] ?? '') ?>"
                                                       data-status="<?= htmlspecialchars($project['status']) ?>"
                                                       data-assignee="<?= htmlspecialchars($project['assignee_name'] ?? '') ?>"
                                                       data-creator="<?= htmlspecialchars($project['creator_name'] ?? '') ?>"
                                                       data-plot-area="<?= htmlspecialchars($project['plot_area'] ?? '') ?>"
                                                       data-contact="<?= htmlspecialchars($project['contact_number'] ?? '') ?>"
                                                       data-address="<?= htmlspecialchars($project['client_address'] ?? '') ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit_project.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-warning action-btn me-1" title="Edit Project">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger action-btn delete-project" 
                                                            title="Delete Project"
                                                            data-id="<?= $project['id'] ?>"
                                                            data-title="<?= htmlspecialchars($project['title']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Project Detail Modal -->
            <div class="modal fade" id="projectDetailModal" tabindex="-1" aria-labelledby="projectDetailModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title" id="projectDetailModalLabel">
                                <i class="bi bi-folder2-open me-2"></i>Project Details
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h4 id="modalTitle" class="mb-0"><i class="bi bi-file-earmark-text me-2"></i><span></span></h4>
                                        <span id="modalStatusBadge" class="status-badge"></span>
                                    </div>
                            <p id="modalDescription" class="text-muted"><i class="bi bi-quote me-2"></i><span></span></p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Project Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-building me-1"></i>Project Type</small>
                                        <span id="modalType" class="project-type"></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-tag me-1"></i>Category</small>
                                        <span id="modalCategory"></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-calendar3 me-1"></i>Timeline</small>
                                        <span id="modalTimeline"></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-aspect-ratio me-1"></i>Plot Area</small>
                                        <span id="modalPlotArea"></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i>Location</small>
                                        <span id="modalLocation"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-person me-2"></i>Client Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-person-badge me-1"></i>Client Name</small>
                                        <span id="modalClient"></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i>Contact Number</small>
                                        <span id="modalContactNumber"></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-house-door me-1"></i>Address</small>
                                        <span id="modalClientAddress"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Team Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-person-check me-1"></i>Assigned To</small>
                                        <span id="modalAssignee"></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><i class="bi bi-person-plus me-1"></i>Created By</small>
                                        <span id="modalCreator"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Stages Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Project Stages</h6>
                                    <span class="badge bg-primary" id="stagesCount">0 Stages</span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0" id="projectStagesTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 40px;"></th>
                                                    <th><i class="bi bi-hash me-1"></i>Stage</th>
                                                    <th><i class="bi bi-person me-1"></i>Assigned To</th>
                                                    <th><i class="bi bi-calendar-event me-1"></i>Start Date</th>
                                                    <th><i class="bi bi-calendar-check me-1"></i>End Date</th>
                                                    <th><i class="bi bi-circle-half me-1"></i>Status</th>
                                                    <th><i class="bi bi-clipboard-check me-1"></i>Assignment</th>
                    </tr>
                                            </thead>
                                            <tbody id="stagesTableBody">
                                                <tr>
                                                    <td colspan="7" class="text-center py-3 text-muted">
                                                        <i class="bi bi-hourglass me-2"></i>Loading project stages...
                                                    </td>
                    </tr>
                </tbody>
            </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="#" id="modalEditLink" class="btn btn-warning">
                        <i class="bi bi-pencil me-1"></i> Edit Project
                    </a>
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Helper function to create toast notification - moved to the top for global access
        function createToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type} show`;
            toast.style.position = 'fixed';
            toast.style.bottom = '20px';
            toast.style.right = '20px';
            toast.style.backgroundColor = type === 'success' ? '#10b981' : '#3949ab';
            toast.style.color = 'white';
            toast.style.padding = '12px 20px';
            toast.style.borderRadius = '4px';
            toast.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            toast.style.zIndex = '9999';
            toast.style.transition = 'all 0.5s ease';
            toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>${message}`;
            return toast;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
                    // Apply project type styling for both cells and rows
                    document.querySelectorAll('.project-type').forEach(element => {
                        const projectType = element.textContent.trim().toLowerCase();
                        
                        // Remove any existing project-type-* classes
                        element.classList.forEach(className => {
                            if (className.startsWith('project-type-') && className !== 'project-type') {
                                element.classList.remove(className);
                            }
                        });
                        
                        // Add appropriate class based on project type
                        if (projectType.includes('architect') || projectType === 'architecture') {
                            element.classList.add('project-type-architecture');
                        } else if (projectType.includes('interior') || projectType.includes('design')) {
                            element.classList.add('project-type-interior');
                        } else if (projectType.includes('construct') || projectType.includes('build')) {
                            element.classList.add('project-type-construction');
                        } else {
                            element.classList.add('project-type-other');
                        }
                    });
                    
                    // Apply row styling based on project type
                    document.querySelectorAll('tr[class^="project-type-"]').forEach(row => {
                        const rowClasses = Array.from(row.classList);
                        let projectTypeClass = rowClasses.find(cls => cls.startsWith('project-type-'));
                        
                        if (projectTypeClass) {
                            // Get the project type from the class
                            const projectType = projectTypeClass.replace('project-type-', '');
                            
                            // Remove the project-type-* class from the row
                            row.classList.remove(projectTypeClass);
                            
                            // Add the appropriate row-* class
                            if (projectType.includes('architect') || projectType === 'architecture') {
                                row.classList.add('row-architecture');
                            } else if (projectType.includes('interior') || projectType.includes('design')) {
                                row.classList.add('row-interior');
                            } else if (projectType.includes('construct') || projectType.includes('build')) {
                                row.classList.add('row-construction');
                            } else {
                                row.classList.add('row-other');
                            }
                        }
            });
            
            // Export to Excel button
            document.getElementById('exportExcel').addEventListener('click', function() {
                exportToExcel();
            });
            
                    // Project Detail Modal
                    document.querySelectorAll('.view-project').forEach(button => {
                        button.addEventListener('click', function(e) {
                            e.preventDefault();
                            const projectId = this.dataset.id;
                            const projectData = {
                                id: projectId,
                                title: this.dataset.title,
                                description: this.dataset.description,
                                client: this.dataset.client,
                                location: this.dataset.location,
                                type: this.dataset.type,
                                category: this.dataset.category,
                                startDate: this.dataset.start,
                                endDate: this.dataset.end,
                                status: this.dataset.status,
                                assignee: this.dataset.assignee,
                                creator: this.dataset.creator,
                                plotArea: this.dataset.plotArea,
                                contactNumber: this.dataset.contact,
                                clientAddress: this.dataset.address
                            };

                            // Set project title and description
                            document.getElementById('modalTitle').querySelector('span').textContent = projectData.title || 'Untitled Project';
                            document.getElementById('modalDescription').querySelector('span').textContent = projectData.description || 'No description available.';
                            
                            // Set project type with appropriate styling
                            const typeElement = document.getElementById('modalType');
                            typeElement.textContent = projectData.type || 'Not specified';
                            typeElement.className = 'project-type'; // Reset classes
                            
                            // Apply project type styling
                            if (projectData.type) {
                                const projectType = projectData.type.toLowerCase();
                                if (projectType.includes('architect') || projectType === 'architecture') {
                                    typeElement.classList.add('project-type-architecture');
                                } else if (projectType.includes('interior') || projectType.includes('design')) {
                                    typeElement.classList.add('project-type-interior');
                                } else if (projectType.includes('construct') || projectType.includes('build')) {
                                    typeElement.classList.add('project-type-construction');
                                } else {
                                    typeElement.classList.add('project-type-other');
                                }
                            }
                            
                            // Set status badge
                            const statusBadge = document.getElementById('modalStatusBadge');
                            statusBadge.textContent = projectData.status || 'Unknown';
                            statusBadge.className = 'status-badge'; // Reset classes
                            
                            if (projectData.status) {
                                const status = projectData.status.toLowerCase();
                                if (status === 'active') {
                                    statusBadge.classList.add('status-badge-active');
                                } else if (status === 'inactive') {
                                    statusBadge.classList.add('status-badge-inactive');
                                } else if (status === 'pending') {
                                    statusBadge.classList.add('status-badge-pending');
                                }
                            }
                            
                            // Format and set timeline
                            let timeline = '';
                            if (projectData.startDate) {
                                const startDate = new Date(projectData.startDate);
                                timeline = `Started: ${startDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}`;
                                
                                if (projectData.endDate) {
                                    const endDate = new Date(projectData.endDate);
                                    timeline += ` • Due: ${endDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}`;
                                }
                            } else {
                                timeline = 'Timeline not specified';
                            }
                            document.getElementById('modalTimeline').textContent = timeline;
                            
                            // Set other project information
                            setModalField('modalCategory', projectData.category);
                            setModalField('modalPlotArea', projectData.plotArea);
                            setModalField('modalLocation', projectData.location);
                            
                            // Set client information
                            setModalField('modalClient', projectData.client);
                            setModalField('modalContactNumber', projectData.contactNumber);
                            setModalField('modalClientAddress', projectData.clientAddress);
                            
                            // Set team information
                            setModalField('modalAssignee', projectData.assignee);
                            setModalField('modalCreator', projectData.creator);
                            
                            // Set edit link
                            document.getElementById('modalEditLink').href = `edit_project.php?id=${projectData.id}`;
                            
                            // Update modal title
                            document.getElementById('projectDetailModalLabel').textContent = `Project Details`;
                            
                            // Fetch project stages
                            fetchProjectStages(projectId);
                        });
                    });
                    
                    // Helper function to set modal field values with fallback for empty values
                    function setModalField(elementId, value) {
                        const element = document.getElementById(elementId);
                        if (value && value.trim() !== '') {
                            element.textContent = value;
                            element.classList.remove('empty-value');
                        } else {
                            element.textContent = 'Not specified';
                            element.classList.add('empty-value');
                        }
                    }
                    
                    // Function to fetch project stages
                    function fetchProjectStages(projectId) {
                        // Show loading state
                        document.getElementById('stagesTableBody').innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">
                                    <i class="bi bi-hourglass me-2"></i>Loading project stages...
                                </td>
                            </tr>
                        `;
                        
                        // Create AJAX request
                        const xhr = new XMLHttpRequest();
                        xhr.open('GET', `ajax_handlers/get_project_stages.php?project_id=${projectId}`, true);
                        
                        xhr.onload = function() {
                            if (this.status === 200) {
                                try {
                                    const response = JSON.parse(this.responseText);
                                    
                                    if (response.success) {
                                        const stages = response.data;
                                        updateStagesTable(stages);
                                    } else {
                                        showStagesError(response.message || 'Failed to load project stages');
                                    }
                                } catch (error) {
                                    showStagesError('Invalid response from server');
                                    console.error('Error parsing JSON:', error);
                                }
                            } else {
                                showStagesError('Server error');
                                console.error('Server returned status:', this.status);
                            }
                        };
                        
                        xhr.onerror = function() {
                            showStagesError('Network error');
                            console.error('Network error occurred');
                        };
                        
                        xhr.send();
                    }
                    
                    // Function to update the stages table
                    function updateStagesTable(stages) {
                        const tableBody = document.getElementById('stagesTableBody');
                        
                        // Update stages count
                        document.getElementById('stagesCount').textContent = `${stages.length} Stage${stages.length !== 1 ? 's' : ''}`;
                        
                        // Clear existing content
                        tableBody.innerHTML = '';
                        
                        if (stages.length === 0) {
                            tableBody.innerHTML = `
                                <tr>
                                    <td colspan="7" class="text-center py-3 text-muted">
                                        <i class="bi bi-exclamation-circle me-2"></i>No stages found for this project
                                    </td>
                                </tr>
                            `;
                            return;
                        }
                        
                        // Add each stage to the table
                        stages.forEach(stage => {
                            const row = document.createElement('tr');
                            
                            // Format dates
                            const startDate = stage.start_date ? formatDate(stage.start_date) : 'Not set';
                            const endDate = stage.end_date ? formatDate(stage.end_date) : 'Not set';
                            
                            // Create status badge
                            const statusClass = getStatusClass(stage.status);
                            const statusBadge = `<span class="status-${statusClass}">${stage.status}</span>`;
                            
                            // Create assignment status badge
                            const assignmentClass = getAssignmentStatusClass(stage.assignment_status);
                            const assignmentBadge = `<span class="badge ${assignmentClass}">${stage.assignment_status}</span>`;
                            
                            // Determine if we have substages and set the expand button accordingly
                            const hasSubstages = stage.sub_stages && stage.sub_stages.length > 0;
                            const expandButton = hasSubstages 
                                ? `<button class="btn btn-sm btn-link p-0 text-decoration-none toggle-substages" data-stage-id="${stage.id}">
                                    <i class="bi bi-plus-circle"></i>
                                   </button>`
                                : `<span class="text-muted"><i class="bi bi-dash"></i></span>`;
                            
                            // Populate row
                            row.innerHTML = `
                                <td>${expandButton}</td>
                                <td><strong>Stage ${stage.stage_number}</strong></td>
                                <td>${stage.assignee_name || '<span class="text-muted">Unassigned</span>'}</td>
                                <td>${startDate}</td>
                                <td>${endDate}</td>
                                <td>${statusBadge}</td>
                                <td>${assignmentBadge}</td>
                            `;
                            
                            tableBody.appendChild(row);
                            
                            // Add substages container row if there are substages
                            if (hasSubstages) {
                                const substagesRow = document.createElement('tr');
                                substagesRow.classList.add('substages-container');
                                substagesRow.classList.add('d-none'); // Hidden by default
                                substagesRow.dataset.stageId = stage.id;
                                
                                // Create substages table
                                let substagesTable = `
                                    <td colspan="7" class="p-0 border-0">
                                        <div class="ms-4 me-2 my-2">
                                            <div class="card">
                                                <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                                                    <h6 class="mb-0 small"><i class="bi bi-layers me-2"></i>Sub-stages for Stage ${stage.stage_number}</h6>
                                                    <span class="badge bg-secondary">${stage.sub_stages.length} Sub-stage${stage.sub_stages.length !== 1 ? 's' : ''}</span>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th><i class="bi bi-hash me-1"></i>Number</th>
                                                                <th><i class="bi bi-file-text me-1"></i>Title</th>
                                                                <th><i class="bi bi-person me-1"></i>Assigned To</th>
                                                                <th><i class="bi bi-calendar-event me-1"></i>Start Date</th>
                                                                <th><i class="bi bi-calendar-check me-1"></i>End Date</th>
                                                                <th><i class="bi bi-circle-half me-1"></i>Status</th>
                                                                <th><i class="bi bi-clipboard-check me-1"></i>Assignment</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                `;
                                
                                // Add each substage
                                stage.sub_stages.forEach(substage => {
                                    const subStartDate = substage.start_date ? formatDate(substage.start_date) : 'Not set';
                                    const subEndDate = substage.end_date ? formatDate(substage.end_date) : 'Not set';
                                    const subStatusClass = getStatusClass(substage.status);
                                    const subStatusBadge = `<span class="status-${subStatusClass}">${substage.status}</span>`;
                                    const subAssignmentClass = getAssignmentStatusClass(substage.assignment_status);
                                    const subAssignmentBadge = `<span class="badge ${subAssignmentClass}">${substage.assignment_status}</span>`;
                                    
                                    substagesTable += `
                                        <tr>
                                            <td>
                                                <button class="btn btn-sm btn-link p-0 text-decoration-none toggle-files me-2" data-substage-id="${substage.id}">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                                <span class="badge bg-light text-dark border">${substage.substage_number}</span>
                                            </td>
                                            <td><strong>${substage.title || `Sub-stage ${substage.substage_number}`}</strong>
                                                ${substage.drawing_number ? `<div class="small text-muted">Drawing: ${substage.drawing_number}</div>` : ''}
                                            </td>
                                            <td>${substage.assignee_name || '<span class="text-muted">Unassigned</span>'}</td>
                                            <td>${subStartDate}</td>
                                            <td>${subEndDate}</td>
                                            <td>${subStatusBadge}</td>
                                            <td>${subAssignmentBadge}</td>
                                        </tr>
                                        <tr class="files-container d-none" data-substage-id="${substage.id}">
                                            <td colspan="7" class="p-0 border-0">
                                                <div class="ms-5 me-2 my-2">
                                                    <div class="card">
                                                        <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                                                            <h6 class="mb-0 small"><i class="bi bi-files me-2"></i>Files for Sub-stage ${substage.substage_number}</h6>
                                                            <span class="badge bg-secondary files-count">Loading...</span>
                                                        </div>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-hover mb-0">
                                                                <thead>
                                                                    <tr>
                                                                        <th>File Name</th>
                                                                        <th>Type</th>
                                                                        <th>Uploaded By</th>
                                                                        <th>Uploaded At</th>
                                                                        <th>Status</th>
                                                                        <th>Last Modified</th>
                                                                        <th>Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="files-tbody">
                                                                    <tr>
                                                                        <td colspan="7" class="text-center py-3 text-muted">
                                                                            <i class="bi bi-hourglass me-2"></i>Loading files...
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                });
                                
                                // Close the substages table
                                substagesTable += `
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            `;
                                
                                substagesRow.innerHTML = substagesTable;
                                tableBody.appendChild(substagesRow);
                            }
                        });
                        
                        // Add event listeners to toggle buttons
                        document.querySelectorAll('.toggle-substages').forEach(button => {
                            button.addEventListener('click', function() {
                                const stageId = this.dataset.stageId;
                                const icon = this.querySelector('i');
                                const substagesRow = document.querySelector(`.substages-container[data-stage-id="${stageId}"]`);
                                
                                if (substagesRow.classList.contains('d-none')) {
                                    // Show substages
                                    substagesRow.classList.remove('d-none');
                                    icon.classList.remove('bi-plus-circle');
                                    icon.classList.add('bi-dash-circle');
                                } else {
                                    // Hide substages
                                    substagesRow.classList.add('d-none');
                                    icon.classList.remove('bi-dash-circle');
                                    icon.classList.add('bi-plus-circle');
                                }
                            });
                        });

                        // Add event listeners to files toggle buttons
                        document.querySelectorAll('.toggle-files').forEach(button => {
                            button.addEventListener('click', function() {
                                const substageId = this.dataset.substageId;
                                const icon = this.querySelector('i');
                                const filesRow = document.querySelector(`.files-container[data-substage-id="${substageId}"]`);
                                
                                if (filesRow.classList.contains('d-none')) {
                                    // Show files
                                    filesRow.classList.remove('d-none');
                                    icon.classList.remove('bi-plus-circle');
                                    icon.classList.add('bi-dash-circle');
                                    // Fetch files only when showing them
                                    fetchSubstageFiles(substageId);
                                } else {
                                    // Hide files
                                    filesRow.classList.add('d-none');
                                    icon.classList.remove('bi-dash-circle');
                                    icon.classList.add('bi-plus-circle');
                                }
                            });
                        });
                    }
                    
                    // Helper function to show error message in stages table
                    function showStagesError(message) {
                        const tableBody = document.getElementById('stagesTableBody');
                        document.getElementById('stagesCount').textContent = '0 Stages';
                        
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-3 text-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>${message}
                                </td>
                            </tr>
                        `;
                    }
                    
                    // Helper function to format date
                    function formatDate(dateString) {
                        const date = new Date(dateString);
                        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    }
                    
                    // Helper function to get status class
                    function getStatusClass(status) {
                        if (!status) return 'pending';
                        
                        status = status.toLowerCase();
                        if (status === 'active' || status === 'completed' || status === 'done') {
                            return 'active';
                        } else if (status === 'inactive' || status === 'cancelled') {
                            return 'inactive';
                        } else {
                            return 'pending';
                        }
                    }
                    
                    // Helper function to get assignment status class
                    function getAssignmentStatusClass(status) {
                        if (!status) return 'bg-secondary';
                        
                        status = status.toLowerCase();
                        if (status === 'assigned' || status === 'accepted') {
                            return 'bg-success';
                        } else if (status === 'rejected' || status === 'cancelled') {
                            return 'bg-danger';
                        } else if (status === 'in_progress' || status === 'in progress') {
                            return 'bg-primary';
                        } else if (status === 'completed' || status === 'done') {
                            return 'bg-info';
                        } else {
                            return 'bg-secondary';
                        }
                    }
                    
                    // Sidebar toggle functionality
                    document.addEventListener('DOMContentLoaded', function() {
                        const sidebar = document.querySelector('.sidebar');
                        const toggleBtn = document.getElementById('toggle-btn');
                        const content = document.getElementById('content');
                        const isMobile = window.innerWidth <= 768;
                        
                        // Create sidebar overlay for mobile
                        const sidebarOverlay = document.createElement('div');
                        sidebarOverlay.className = 'sidebar-overlay';
                        document.body.appendChild(sidebarOverlay);
                        
                        // Initialize sidebar state based on device
                        if (!isMobile) {
                            // On desktop, start with sidebar expanded
                            sidebar.classList.remove('collapsed');
                            content.classList.remove('expanded');
                        } else {
                            // On mobile, start with sidebar collapsed (hidden)
                            sidebar.classList.add('collapsed');
                            content.classList.add('expanded');
                        }
                        
                        // Toggle sidebar
                        toggleBtn.addEventListener('click', function() {
                            sidebar.classList.toggle('collapsed');
                            content.classList.toggle('expanded');
                            
                            // Rotate toggle button icon
                            const icon = toggleBtn.querySelector('i');
                            if (sidebar.classList.contains('collapsed')) {
                                icon.style.transform = 'rotate(180deg)';
                            } else {
                                icon.style.transform = 'rotate(0deg)';
                            }
                            
                            // Show/hide overlay on mobile
                            if (window.innerWidth <= 768) {
                                sidebarOverlay.classList.toggle('active');
                            }
                        });
                        
                        // Close sidebar when clicking overlay
                        sidebarOverlay.addEventListener('click', function() {
                            sidebar.classList.add('collapsed');
                            content.classList.add('expanded');
                            const icon = toggleBtn.querySelector('i');
                            icon.style.transform = 'rotate(180deg)';
                            sidebarOverlay.classList.remove('active');
                        });
                        
                        // Handle window resize
                        window.addEventListener('resize', function() {
                            const newIsMobile = window.innerWidth <= 768;
                            
                            // If transitioning between mobile and desktop
                            if (newIsMobile !== isMobile) {
                                if (newIsMobile) {
                                    // Switching to mobile
                                    sidebar.classList.add('collapsed');
                                    content.classList.add('expanded');
                                    sidebarOverlay.classList.remove('active');
                                    const icon = toggleBtn.querySelector('i');
                                    icon.style.transform = 'rotate(180deg)';
                                } else {
                                    // Switching to desktop
                                    sidebar.classList.remove('collapsed');
                                    content.classList.remove('expanded');
                                    sidebarOverlay.classList.remove('active');
                                    const icon = toggleBtn.querySelector('i');
                                    icon.style.transform = 'rotate(0deg)';
                                }
                            }
                        });
                    });
                    
                    // Save filters functionality
                    document.getElementById('saveFilters').addEventListener('click', function() {
                        // Get current filter values
                        const filters = {
                            dateFrom: document.getElementById('dateFrom').value,
                            dateTo: document.getElementById('dateTo').value,
                            status: document.getElementById('status').value,
                            projectType: document.getElementById('projectType').value,
                            categoryId: document.getElementById('categoryId').value,
                            search: document.getElementById('search').value
                        };
                        
                        // Save to localStorage
                        localStorage.setItem('projectFilters', JSON.stringify(filters));
                        
                        // Show confirmation
                        const toast = createToast('Filters saved successfully!', 'success');
                        document.body.appendChild(toast);
                        
                        // Auto-remove toast after 3 seconds
                        setTimeout(() => {
                            toast.classList.add('hide');
                            setTimeout(() => toast.remove(), 500);
                        }, 3000);
                    });
                    
                    // Load saved filters on page load
                    document.addEventListener('DOMContentLoaded', function() {
                        // Check if we have saved filters and if the form is not already submitted
                        const savedFilters = localStorage.getItem('projectFilters');
                        const urlParams = new URLSearchParams(window.location.search);
                        
                        // Only load saved filters if there are no query parameters (user hasn't submitted the form)
                        if (savedFilters && urlParams.toString() === '') {
                            try {
                                const filters = JSON.parse(savedFilters);
                                
                                // Apply saved filters to form
                                if (filters.dateFrom) document.getElementById('dateFrom').value = filters.dateFrom;
                                if (filters.dateTo) document.getElementById('dateTo').value = filters.dateTo;
                                if (filters.status) document.getElementById('status').value = filters.status;
                                if (filters.projectType) document.getElementById('projectType').value = filters.projectType;
                                if (filters.categoryId) document.getElementById('categoryId').value = filters.categoryId;
                                if (filters.search) document.getElementById('search').value = filters.search;
                                
                                // Submit the form to apply filters
                                document.getElementById('filterForm').submit();
                            } catch (e) {
                                console.error('Error loading saved filters:', e);
                            }
                        }
                    });
                });
            
            function exportToExcel() {
                // Check if XLSX is available
                if (typeof XLSX === 'undefined') {
                    alert('Excel export library not loaded. Please refresh the page and try again.');
                    return;
                }
                
                // Show loading indicator
                const exportBtn = document.getElementById('exportExcel');
                const originalText = exportBtn.innerHTML;
                exportBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Exporting...';
                exportBtn.disabled = true;
                
                try {
                    // Create a new workbook
                    const wb = XLSX.utils.book_new();
                    
                    // Get the table data for projects
                    const table = document.getElementById('dataTable');
                    
                    // Prepare data array for projects
                    const projectsData = [];
                    
                    // Add headers for projects (excluding the Actions column)
                    const headers = [
                        'S.No.',
                        'Title',
                        'Client',
                        'Project Type',
                        'Category',
                        'Start Date',
                        'End Date',
                        'Status',
                        'Location',
                        'Assigned To'
                    ];
                    projectsData.push(headers);
                    
                    // Add rows for projects
                    const rows = table.querySelectorAll('tbody tr');
                    let serialNumber = 1;
                    
                    // Array to hold project IDs and titles for fetching stages
                    const projects = [];
                    
                    rows.forEach(row => {
                        if (row.cells.length > 1 && !row.querySelector('.empty-state')) { // Skip "No projects found" row
                            const rowData = [serialNumber]; // Start with serial number instead of ID
                            const cells = row.querySelectorAll('td');
                            
                            // Store project ID and title for fetching stages later
                            const projectId = cells[0].textContent.trim();
                            const projectTitle = cells[1].querySelector('strong') ? 
                                cells[1].querySelector('strong').textContent.trim() : 
                                'Project ' + projectId;
                                
                            projects.push({
                                id: projectId,
                                title: projectTitle
                            });
                            
                            // Add project data (skip first column which is ID and last column which is Actions)
                            for (let i = 1; i < cells.length - 1; i++) {
                                // Get the text content without the description for the title cell
                                if (i === 1 && cells[i].querySelector('strong')) {
                                    rowData.push(cells[i].querySelector('strong').textContent.trim());
                                } else {
                                    // Clean up the text (remove extra spaces, etc.)
                                    let cellText = cells[i].textContent.trim().replace(/\s+/g, ' ');
                                    rowData.push(cellText);
                                }
                            }
                            projectsData.push(rowData);
                            serialNumber++;
                        }
                    });
                    
                    // Add projects worksheet
                    const projectsWS = XLSX.utils.aoa_to_sheet(projectsData);
                    
                    // Style the header row - using cell formatting options that work with the library
                    const range = XLSX.utils.decode_range(projectsWS['!ref']);
                    projectsWS['!cols'] = Array(range.e.c + 1).fill({ wch: 15 }); // Set column width
                    
                    // Add the worksheet to the workbook
                    XLSX.utils.book_append_sheet(wb, projectsWS, "Projects");
                    
                    // Fetch stages and substages for each project if we have projects
                    if (projects.length > 0) {
                        // Create a single worksheet for all stages and substages
                        const allStagesData = [];
                        
                        // Add a title for the stages worksheet
                        allStagesData.push(['Project Stages and Substages']);
                        allStagesData.push([]); // Empty row for spacing
                        
                        // Process each project sequentially to avoid overwhelming the server
                        const processProjects = async () => {
                            let errorCount = 0;
                            
                            for (let i = 0; i < projects.length; i++) {
                                const project = projects[i];
                                
                                try {
                                    // Fetch stages for this project without logging
                                    const stages = await fetchProjectStagesPromise(project.id);
                                    
                                    // Add project header
                                    allStagesData.push([`Project ${i + 1}: ${project.title} (ID: ${project.id})`]);
                                    allStagesData.push([]); // Empty row for spacing
                                    
                                    if (!stages || stages.length === 0) {
                                        allStagesData.push(['No stages found for this project']);
                                        allStagesData.push([]); // Empty row for spacing
                                        continue;
                                    }
                                    
                                    // Add stages header
                                    allStagesData.push([
                                        'S.No.',
                                        'Project Title',
                                        'Stage Number',
                                        'Assigned To',
                                        'Start Date',
                                        'End Date',
                                        'Status',
                                        'Assignment Status'
                                    ]);
                                    
                                    // Add rows for stages
                                    let stageSerialNumber = 1;
                                    stages.forEach(stage => {
                                        const startDate = stage.start_date ? formatDateForExcel(stage.start_date) : 'Not set';
                                        const endDate = stage.end_date ? formatDateForExcel(stage.end_date) : 'Not set';
                                        
                                        allStagesData.push([
                                            stageSerialNumber,
                                            project.title,
                                            'Stage ' + stage.stage_number,
                                            stage.assignee_name || 'Unassigned',
                                            startDate,
                                            endDate,
                                            stage.status || 'N/A',
                                            stage.assignment_status || 'N/A'
                                        ]);
                                        
                                        // Add substages if they exist
                                        if (stage.sub_stages && stage.sub_stages.length > 0) {
                                            // Add substage header
                                            allStagesData.push([]); // Empty row for spacing
                                            allStagesData.push([
                                                '',
                                                'Substages for Stage ' + stage.stage_number + ':'
                                            ]);
                                            allStagesData.push([
                                                '',
                                                'S.No.',
                                                'Project Title',
                                                'Number',
                                                'Title',
                                                'Drawing No.',
                                                'Assigned To',
                                                'Start Date',
                                                'End Date',
                                                'Status',
                                                'Assignment Status'
                                            ]);
                                            
                                            // Add rows for substages
                                            let substageSerialNumber = 1;
                                            stage.sub_stages.forEach(substage => {
                                                const subStartDate = substage.start_date ? formatDateForExcel(substage.start_date) : 'Not set';
                                                const subEndDate = substage.end_date ? formatDateForExcel(substage.end_date) : 'Not set';
                                                
                                                allStagesData.push([
                                                    '',
                                                    substageSerialNumber,
                                                    project.title,
                                                    substage.substage_number,
                                                    substage.title || 'Substage ' + substage.substage_number,
                                                    substage.drawing_number || 'N/A',
                                                    substage.assignee_name || 'Unassigned',
                                                    subStartDate,
                                                    subEndDate,
                                                    substage.status || 'N/A',
                                                    substage.assignment_status || 'N/A'
                                                ]);
                                                
                                                substageSerialNumber++;
                                            });
                                            
                                            allStagesData.push([]); // Empty row for spacing
                                        }
                                        
                                        stageSerialNumber++;
                                    });
                                    
                                    // Add spacing between projects
                                    allStagesData.push([]);
                                    allStagesData.push([]);
                                } catch (error) {
                                    // Error processing project, silently continue
                                    allStagesData.push([`Error fetching stages for project ${project.title} (ID: ${project.id})`]);
                                    allStagesData.push([]);
                                    errorCount++;
                                }
                            }
                            
                            // Create worksheet for all stages
                            const stagesWS = XLSX.utils.aoa_to_sheet(allStagesData);
                            
                            // Add the stages worksheet to the workbook
                            XLSX.utils.book_append_sheet(wb, stagesWS, "Stages & Substages");
                            
                            // Get current date for filename
                            const now = new Date();
                            const dateStr = now.toISOString().split('T')[0];
                            
                            // Export the workbook with projects and stages
                            XLSX.writeFile(wb, `projects_export_${dateStr}.xlsx`);
                            
                            // Show appropriate message
                            if (errorCount > 0) {
                                alert(`Some project stages (${errorCount} of ${projects.length}) could not be loaded. A partial export has been created.`);
                            } else {
                                // Show success message
                                const toast = createToast('Projects exported successfully with stages and substages!', 'success');
                                document.body.appendChild(toast);
                                setTimeout(() => {
                                    toast.classList.add('hide');
                                    setTimeout(() => toast.remove(), 500);
                                }, 3000);
                            }
                        };
                        
                        // Start processing projects
                        processProjects().catch(error => {
                            // Still export what we have even if there was an error
                            const now = new Date();
                            const dateStr = now.toISOString().split('T')[0];
                            XLSX.writeFile(wb, `projects_export_${dateStr}.xlsx`);
                            
                            alert('An error occurred while processing projects. A partial export has been created.');
                        }).finally(() => {
                            // Reset button
                            exportBtn.innerHTML = originalText;
                            exportBtn.disabled = false;
                        });
                    } else {
                        // No projects to export stages for, just export the projects worksheet
                        const now = new Date();
                        const dateStr = now.toISOString().split('T')[0];
                        XLSX.writeFile(wb, `projects_export_${dateStr}.xlsx`);
                        
                        // Show success message
                        const toast = createToast('Projects exported successfully!', 'success');
                        document.body.appendChild(toast);
                        setTimeout(() => {
                            toast.classList.add('hide');
                            setTimeout(() => toast.remove(), 500);
                        }, 3000);
                        
                        // Reset button
                        exportBtn.innerHTML = originalText;
                        exportBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Export error:', error);
                    alert('An error occurred during export. Please try again later.');
                    
                    // Reset button
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                }
            }
            
            // Helper function to fetch project stages as a Promise
            function fetchProjectStagesPromise(projectId) {
                return new Promise((resolve, reject) => {
                    // Create AJAX request
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', `ajax_handlers/get_project_stages.php?project_id=${projectId}`, true);
                    
                    // Set a timeout to prevent hanging requests
                    xhr.timeout = 10000; // 10 seconds timeout
                    
                    xhr.onload = function() {
                        if (this.status === 200) {
                            try {
                                const response = JSON.parse(this.responseText);
                                if (response.success) {
                                    resolve(response.data);
                                } else {
                                    // No success flag in response
                                    resolve([]);
                                }
                            } catch (error) {
                                // Error parsing JSON, silently continue
                                resolve([]);
                            }
                        } else {
                            // Server returned error status
                            resolve([]);
                        }
                    };
                    
                    xhr.ontimeout = function() {
                        // Request timed out
                        resolve([]);
                    };
                    
                    xhr.onerror = function() {
                        // Network error occurred
                        resolve([]);
                    };
                    
                    xhr.send();
                });
            }
            
                                // Function to fetch and display substage files
                    function fetchSubstageFiles(substageId) {
                        const filesContainer = document.querySelector(`.files-container[data-substage-id="${substageId}"]`);
                        const filesTbody = filesContainer.querySelector('.files-tbody');
                        const filesCount = filesContainer.querySelector('.files-count');
                        
                        // Create AJAX request
                        const xhr = new XMLHttpRequest();
                        xhr.open('GET', `ajax_handlers/get_substage_files.php?substage_id=${substageId}`, true);
                        
                        xhr.onload = function() {
                            if (this.status === 200) {
                                try {
                                    const response = JSON.parse(this.responseText);
                                    
                                    if (response.success) {
                                        const files = response.data;
                                        filesCount.textContent = `${files.length} File${files.length !== 1 ? 's' : ''}`;
                                        
                                        if (files.length === 0) {
                                            filesTbody.innerHTML = `
                                                <tr>
                                                    <td colspan="9" class="text-center py-3 text-muted">
                                                        <i class="bi bi-exclamation-circle me-2"></i>No files found
                                                    </td>
                                                </tr>
                                            `;
                                            return;
                                        }
                                        
                                        // Clear existing content
                                        filesTbody.innerHTML = '';
                                        
                                        // Add each file
                                        files.forEach(file => {
                                            const row = document.createElement('tr');
                                            row.innerHTML = `
                                                <td>
                                                    <i class="bi bi-file-earmark me-2"></i>
                                                    <strong>${file.file_name}</strong>
                                                </td>
                                                <td><span class="badge bg-light text-dark">${file.type}</span></td>
                                                <td>${file.uploaded_by_name || 'N/A'}</td>
                                                <td>${formatDate(file.uploaded_at)}</td>
                                                <td><span class="badge bg-${getStatusBadgeClass(file.status)}">${file.status}</span></td>
                                                <td>
                                                    ${file.last_modified_at ? `
                                                        <div>${formatDate(file.last_modified_at)}</div>
                                                        <small class="text-muted">by ${file.last_modified_by_name}</small>
                                                    ` : 'N/A'}
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="${file.file_path}" class="btn btn-info" title="Download" target="_blank">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            `;
                                            filesTbody.appendChild(row);
                                        });
                                    } else {
                                        showFilesError(filesTbody, filesCount, response.message || 'Failed to load files');
                                    }
                                } catch (error) {
                                    showFilesError(filesTbody, filesCount, 'Invalid response from server');
                                    console.error('Error parsing JSON:', error);
                                }
                            } else {
                                showFilesError(filesTbody, filesCount, 'Server error');
                                console.error('Server returned status:', this.status);
                            }
                        };
                        
                        xhr.onerror = function() {
                            showFilesError(filesTbody, filesCount, 'Network error');
                            console.error('Network error occurred');
                        };
                        
                        xhr.send();
                    }
                    
                    // Helper function to show files error
                    function showFilesError(tbody, countBadge, message) {
                        tbody.innerHTML = `
                            <tr>
                                                                                <td colspan="7" class="text-center py-3 text-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>${message}
                                </td>
                            </tr>
                        `;
                        countBadge.textContent = '0 Files';
                    }
                    
                    // Helper function to get status badge class
                    function getStatusBadgeClass(status) {
                        if (!status) return 'secondary';
                        
                        status = status.toLowerCase();
                        switch (status) {
                            case 'active':
                            case 'approved':
                                return 'success';
                            case 'pending':
                            case 'in review':
                                return 'warning';
                            case 'rejected':
                            case 'deleted':
                                return 'danger';
                            default:
                                return 'secondary';
                        }
                    }

                    // Helper function to format date
                    function formatDate(dateString) {
                        if (!dateString) return 'Not set';
                        const date = new Date(dateString);
                        return date.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }

                    // Helper function to format date for Excel
                    function formatDateForExcel(dateString) {
                        if (!dateString) return 'Not set';
                        const date = new Date(dateString);
                        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    }

                    // Add event listeners for project deletion
                    document.querySelectorAll('.delete-project').forEach(button => {
                        button.addEventListener('click', function() {
                            const projectId = this.dataset.id;
                            const projectTitle = this.dataset.title;
                            
                            if (confirm(`Are you sure you want to delete project "${projectTitle}"? This action cannot be undone.`)) {
                                const xhr = new XMLHttpRequest();
                                xhr.open('POST', 'ajax_handlers/delete_project.php', true);
                                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                
                                xhr.onload = function() {
                                    if (this.status === 200) {
                                        try {
                                            const response = JSON.parse(this.responseText);
                                            if (response.success) {
                                                // Show success message
                                                const toast = createToast('Project deleted successfully!', 'success');
                                                document.body.appendChild(toast);
                                                setTimeout(() => {
                                                    toast.classList.add('hide');
                                                    setTimeout(() => toast.remove(), 500);
                                                    // Reload the page to refresh the project list
                                                    window.location.reload();
                                                }, 2000);
                                            } else {
                                                alert('Error: ' + (response.message || 'Failed to delete project'));
                                            }
                                        } catch (error) {
                                            alert('Error processing server response');
                                            console.error('Error parsing JSON:', error);
                                        }
                                    } else {
                                        alert('Server error occurred');
                                        console.error('Server returned status:', this.status);
                                    }
                                };
                                
                                xhr.onerror = function() {
                                    alert('Network error occurred');
                                    console.error('Network error occurred');
                                };
                                
                                xhr.send('project_id=' + encodeURIComponent(projectId));
                            }
                        });
                    });
                    
                    // Toggle sidebar functionality
                    document.getElementById('sidebarToggle').addEventListener('click', function() {
                        const sidebar = document.getElementById('sidebar');
                        const mainContent = document.getElementById('content');
                        const toggleBtn = this;
                        
                        sidebar.classList.toggle('collapsed');
                        mainContent.classList.toggle('expanded');
                        toggleBtn.classList.toggle('collapsed');
                    });
    </script>
        </div>
    </div>
</body>
</html>