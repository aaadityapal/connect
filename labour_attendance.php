<?php
// Enable error reporting for troubleshooting but don't display to users
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Create a log file for debugging
$logFile = 'logs/labour_attendance_errors.log';
if (!file_exists(dirname($logFile))) {
    @mkdir(dirname($logFile), 0777, true);
}

function logError($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    @error_log($logMessage, 3, $logFile);
}

try {
    // Include database connection
    require_once 'config/db_connect.php';

    // Session and authentication check
    session_start();

    // Restrict access to HR role only
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // User not logged in, redirect to login page
        header("Location: login.php?redirect=labour_attendance.php");
        exit();
    }

    // Check if user has HR role
    if ($_SESSION['role'] !== 'HR') {
        // User doesn't have HR role, redirect to unauthorized page
        header("Location: unauthorized.php");
        exit();
    }

    // Initialize filters
    $fromDateFilter = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d', strtotime('-7 days'));
    $toDateFilter = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $eventFilter = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    $vendorFilter = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
    $labourTypeFilter = isset($_GET['labour_type']) ? $_GET['labour_type'] : 'all';
    $siteTitle = '';

    // Check if required tables exist
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_calendar_events'";
    $stmt = $pdo->query($tableCheckQuery);
    $tableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tableCheck || $tableCheck['table_exists'] == 0) {
        throw new Exception("Required tables not found in database. Please run the setup script first.");
    }

    // Fetch unique construction sites for dropdown
    $eventsQuery = "SELECT MIN(event_id) as event_id, title 
                    FROM sv_calendar_events 
                    GROUP BY title 
                    ORDER BY title ASC";
    $events = $pdo->query($eventsQuery)->fetchAll();

    // Get the site title if an event is selected
    if ($eventFilter > 0) {
        $siteQuery = "SELECT title FROM sv_calendar_events WHERE event_id = :event_id LIMIT 1";
        $stmt = $pdo->prepare($siteQuery);
        $stmt->execute([':event_id' => $eventFilter]);
        $siteResult = $stmt->fetch();
        if ($siteResult) {
            $siteTitle = $siteResult['title'];
        }
    }

    // Check if company labours table exists
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_company_labours'";
    $stmt = $pdo->query($tableCheckQuery);
    $companyTableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize company labours array
    $companyLabours = [];

    // Only proceed if table exists
    if ($companyTableCheck && $companyTableCheck['table_exists'] > 0) {
        // Check if is_deleted column exists
        $columnCheckQuery = "SELECT COUNT(*) AS column_exists FROM information_schema.columns 
                            WHERE table_schema = DATABASE() 
                            AND table_name = 'sv_company_labours' 
                            AND column_name = 'is_deleted'";
        $stmt = $pdo->query($columnCheckQuery);
        $columnCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        $isDeletedExists = ($columnCheck && $columnCheck['column_exists'] > 0);
        
        // Fetch company labours
        $companyLaboursQuery = "
            SELECT 
                cl.*,
                ce.title as event_name
            FROM 
                sv_company_labours cl
            LEFT JOIN 
                sv_calendar_events ce ON cl.event_id = ce.event_id
            WHERE 1=1
        ";
        
        // Add is_deleted check only if the column exists
        if ($isDeletedExists) {
            $companyLaboursQuery .= " AND cl.is_deleted = 0";
        }

        // Apply filters for company labours
        $params = [];
        if ($eventFilter > 0 && !empty($siteTitle)) {
            $companyLaboursQuery .= " AND ce.title = :site_title";
            $params[':site_title'] = $siteTitle;
        } elseif ($eventFilter > 0) {
            $companyLaboursQuery .= " AND cl.event_id = :event_id";
            $params[':event_id'] = $eventFilter;
        }

        // Apply date range filters
        if ($fromDateFilter && $toDateFilter) {
            $companyLaboursQuery .= " AND cl.attendance_date BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $fromDateFilter;
            $params[':to_date'] = $toDateFilter;
        }

        if ($statusFilter !== 'all') {
            $companyLaboursQuery .= " AND (cl.morning_attendance = :status OR cl.evening_attendance = :status)";
            $params[':status'] = $statusFilter;
        }

        $companyLaboursQuery .= " ORDER BY cl.attendance_date DESC, cl.sequence_number, cl.labour_name";

        $stmt = $pdo->prepare($companyLaboursQuery);
        $stmt->execute($params);
        $companyLabours = $stmt->fetchAll();
    }

    // Check if vendor labours table exists
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_vendor_labours'";
    $stmt = $pdo->query($tableCheckQuery);
    $vendorTableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize vendor labours array
    $vendorLabours = [];

    // Only proceed if table exists
    if ($vendorTableCheck && $vendorTableCheck['table_exists'] > 0) {
        // Check if is_deleted column exists
        $columnCheckQuery = "SELECT COUNT(*) AS column_exists FROM information_schema.columns 
                            WHERE table_schema = DATABASE() 
                            AND table_name = 'sv_vendor_labours' 
                            AND column_name = 'is_deleted'";
        $stmt = $pdo->query($columnCheckQuery);
        $columnCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        $isDeletedExists = ($columnCheck && $columnCheck['column_exists'] > 0);
        
        // Fetch vendor labours
        $vendorLaboursQuery = "
            SELECT 
                vl.*,
                ev.vendor_name,
                ev.vendor_type,
                ce.title as event_name
            FROM 
                sv_vendor_labours vl
            LEFT JOIN 
                sv_event_vendors ev ON vl.vendor_id = ev.vendor_id
            LEFT JOIN 
                sv_calendar_events ce ON ev.event_id = ce.event_id
            WHERE 1=1
        ";
        
        // Add is_deleted check only if the column exists
        if ($isDeletedExists) {
            $vendorLaboursQuery .= " AND vl.is_deleted = 0";
        }

        // Apply filters for vendor labours
        $vendorParams = [];
        if ($vendorFilter > 0) {
            $vendorLaboursQuery .= " AND vl.vendor_id = :vendor_id";
            $vendorParams[':vendor_id'] = $vendorFilter;
        } elseif ($eventFilter > 0 && !empty($siteTitle)) {
            $vendorLaboursQuery .= " AND ce.title = :site_title";
            $vendorParams[':site_title'] = $siteTitle;
        }

        // Apply date range filters
        if ($fromDateFilter && $toDateFilter) {
            $vendorLaboursQuery .= " AND vl.attendance_date BETWEEN :from_date AND :to_date";
            $vendorParams[':from_date'] = $fromDateFilter;
            $vendorParams[':to_date'] = $toDateFilter;
        }

        if ($statusFilter !== 'all') {
            $vendorLaboursQuery .= " AND (vl.morning_attendance = :status OR vl.evening_attendance = :status)";
            $vendorParams[':status'] = $statusFilter;
        }

        $vendorLaboursQuery .= " ORDER BY vl.attendance_date DESC, ev.vendor_name, vl.sequence_number, vl.labour_name";

        $stmt = $pdo->prepare($vendorLaboursQuery);
        $stmt->execute($vendorParams);
        $vendorLabours = $stmt->fetchAll();
    }

    // Combine both types of labours based on labour type filter
    if ($labourTypeFilter === 'all') {
        $allLabours = array_merge($companyLabours, $vendorLabours);
    } elseif ($labourTypeFilter === 'company') {
        $allLabours = $companyLabours;
    } elseif ($labourTypeFilter === 'vendor') {
        $allLabours = $vendorLabours;
    } else {
        $allLabours = [];
    }

    // Count statistics
    $presentCount = 0;
    $companyLabourCount = count($companyLabours);
    $vendorLabourCount = count($vendorLabours);
    $totalCount = count($allLabours);

    foreach ($allLabours as $labour) {
        if (isset($labour['morning_attendance']) && isset($labour['evening_attendance']) &&
            ($labour['morning_attendance'] === 'present' || $labour['evening_attendance'] === 'present')) {
            $presentCount++;
        }
    }

    // Check if vendors table exists
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sv_event_vendors'";
    $stmt = $pdo->query($tableCheckQuery);
    $vendorsTableCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize vendors array
    $vendors = [];

    // Only proceed if table exists
    if ($vendorsTableCheck && $vendorsTableCheck['table_exists'] > 0) {
        // Fetch vendors for dropdown
        $vendorsQuery = "SELECT vendor_id, vendor_name, vendor_type FROM sv_event_vendors ORDER BY vendor_name";
        $vendors = $pdo->query($vendorsQuery)->fetchAll();
    }
} catch (Exception $e) {
    // Log the error
    logError("Error in labour_attendance.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Create empty arrays for the UI to not break
    $events = [];
    $companyLabours = [];
    $vendorLabours = [];
    $allLabours = [];
    $vendors = [];
    $presentCount = 0;
    $companyLabourCount = 0;
    $vendorLabourCount = 0;
    $totalCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labour Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #f72585;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #f94144;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            font-size: 16px;
        }

        .container {
            width: 95%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px;
        }

        header {
            background: linear-gradient(to right, #3a7bd5, #00d2ff);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 18px 0;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            position: sticky;
            top: 0;
            z-index: 100;
            color: white;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 25px;
        }
        
        .header-left, .header-right {
            display: flex;
            align-items: center;
        }

        .logo {
            font-size: 26px;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: 0.5px;
        }

        .logo i {
            font-size: 28px;
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.1));
        }

        .date-display {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.95);
            font-weight: 500;
            text-align: right;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .dashboard {
            display: block;
            width: 100%;
        }

        /* Horizontal Filter Section */
        .horizontal-filter-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .filter-section-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .filter-section-header h3 {
            color: var(--dark-color);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .horizontal-filter-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }

        .horizontal-filter-group {
            flex: 1;
            min-width: 180px;
        }

        .horizontal-filter-group h4 {
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .date-inputs {
            display: flex;
            gap: 15px;
        }

        .date-input-group {
            flex: 1;
        }

        .date-input-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            color: var(--gray-color);
        }

        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 10px;
        }

        /* Responsive styles for filter section */
        @media (max-width: 992px) {
            .filter-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .horizontal-filter-group {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .horizontal-filter-section {
                padding: 15px;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .filter-actions .btn {
                width: 100%;
            }
        }

        .sidebar {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .sidebar h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group {
            margin-bottom: 20px;
        }

        .filter-group h4 {
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-select, 
        input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
            background-color: white;
            transition: var(--transition);
        }

        .filter-select:focus, 
        input[type="date"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .main-content {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .content-header h2 {
            color: var(--dark-color);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--light-color);
            color: var(--dark-color);
        }

        .btn-secondary:hover {
            background-color: #e2e6ea;
            transform: translateY(-2px);
        }

        .btn-action {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 4px;
        }

        .btn-filter {
            width: 100%;
            margin-top: 10px;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .attendance-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid #eee;
            white-space: nowrap;
        }

        .attendance-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .attendance-table tr:hover {
            background-color: #f8f9fa;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
            max-height: 600px; /* Set a fixed height */
            overflow-y: auto; /* Add vertical scrolling */
        }

        /* Style the scrollbar for webkit browsers */
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            gap: 4px;
        }

        .status-present {
            background-color: rgba(76, 201, 240, 0.15);
            color: var(--success-color);
        }

        .status-absent {
            background-color: rgba(249, 65, 68, 0.15);
            color: var(--danger-color);
        }

        .status-late {
            background-color: rgba(248, 150, 30, 0.15);
            color: var(--warning-color);
        }

        .status-leave {
            background-color: rgba(67, 97, 238, 0.15);
            color: var(--primary-color);
        }

        /* Labour type styling - Enhanced for better differentiation */
        .company-labour {
            background-color: rgba(76, 201, 240, 0.15);
            color: #0ca2e2;
            border: 1px solid rgba(76, 201, 240, 0.3);
            font-weight: 600;
        }

        .vendor-labour {
            background-color: rgba(247, 37, 133, 0.15);
            color: #f72585;
            border: 1px solid rgba(247, 37, 133, 0.3);
            font-weight: 600;
        }
        
        /* Row styling for different labour types */
        tr.company-labour-row {
            background-color: rgba(76, 201, 240, 0.04);
        }

        tr.company-labour-row:hover {
            background-color: rgba(76, 201, 240, 0.1);
        }

        tr.vendor-labour-row {
            background-color: rgba(247, 37, 133, 0.04);
        }

        tr.vendor-labour-row:hover {
            background-color: rgba(247, 37, 133, 0.1);
        }
        
        /* Pagination styling */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background-color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .pagination-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-btn:hover:not(.active) {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }

        /* Summary cards styling */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }

        .summary-card h3 {
            font-size: 14px;
            color: var(--gray-color);
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .summary-card .value {
            font-size: 28px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .present-card::before {
            background-color: var(--success-color);
        }

        .present-card .value,
        .present-card .card-icon {
            color: var(--success-color);
        }

        .total-card::before {
            background-color: var(--primary-color);
        }

        .total-card .value,
        .total-card .card-icon {
            color: var(--primary-color);
        }

        .card-icon {
            font-size: 24px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        /* Summary card styling for labour types */
        .company-labour-card::before {
            background-color: #0ca2e2;
        }

        .company-labour-card .value,
        .company-labour-card .card-icon {
            color: #0ca2e2;
        }
        
        .vendor-labour-card::before {
            background-color: #f72585;
        }

        .vendor-labour-card .value,
        .vendor-labour-card .card-icon {
            color: #f72585;
        }

        /* Mobile Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 10px 15px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
            width: 100%;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Section Header Styling */
        .section-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            color: var(--dark-color);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Summary Section Styling */
        .summary-section {
            border: 1px solid rgba(67, 97, 238, 0.2);
            border-radius: var(--border-radius);
            padding: 25px;
            background-color: white;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .summary-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--success-color), var(--primary-color), var(--warning-color), var(--danger-color));
        }
        
        .summary-section .section-header {
            margin-top: 0;
            margin-bottom: 25px;
            border-bottom-color: rgba(67, 97, 238, 0.1);
        }
        
        .summary-section .summary-cards {
            margin-bottom: 0;
        }
        
        /* Media Queries for Responsiveness */
        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
                margin-bottom: 20px;
                display: none; /* Hide by default on mobile */
            }

            .sidebar.active {
                display: block;
            }

            .sidebar-toggle {
                display: flex;
            }
            
            .table-responsive {
                max-height: 500px; /* Reduce height on tablets */
            }
        }

        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .container {
                width: 100%;
                padding: 10px;
            }

            .header-content {
                padding: 0 15px;
            }
            
            .logo {
                font-size: 22px;
            }
            
            .logo i {
                font-size: 24px;
            }
            
            .date-display {
                font-size: 14px;
            }

            .main-content,
            .sidebar {
                padding: 15px;
            }
            
            .summary-section {
                padding: 20px;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 10px;
            }

            .btn {
                padding: 7px 14px;
            }
            
            .table-responsive {
                max-height: 450px; /* Further reduce height on mobile */
            }
            
            /* Optimize date range inputs */
            .filter-group input[type="date"] {
                font-size: 13px;
                padding: 8px;
            }

            /* Section Header Styling */
            .section-header h2 {
                font-size: 20px;
            }
        }

        @media (max-width: 640px) {
            /* New breakpoint for medium-small devices */
            .attendance-table th:nth-child(6),
            .attendance-table td:nth-child(6) {
                display: none; /* Hide vendor column on medium-small devices */
            }
        }

        @media (max-width: 480px) {
            .summary-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .summary-section {
                padding: 15px;
            }
            
            .summary-section::before {
                height: 3px;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .action-buttons {
                width: 100%;
                justify-content: space-between;
            }
            
            .header-content {
                padding: 0 15px;
            }

            .logo {
                font-size: 20px;
            }
            
            .logo i {
                font-size: 22px;
            }

            .date-display {
                font-size: 13px;
            }

            .attendance-table {
                font-size: 12px;
            }
            
            .attendance-table th,
            .attendance-table td {
                padding: 8px 5px;
            }

            .summary-card {
                padding: 15px;
            }

            .summary-card .value {
                font-size: 24px;
            }

            /* Adjust column visibility for very small screens */
            .hide-xs {
                display: none;
            }
            
            /* Optimize table for mobile */
            .table-responsive {
                max-height: 400px;
                margin-bottom: 15px;
            }
            
            /* Keep most important columns visible */
            .attendance-table th:nth-child(1), 
            .attendance-table td:nth-child(1) {
                min-width: 30px; /* ID column */
            }
            
            .attendance-table th:nth-child(2), 
            .attendance-table td:nth-child(2) {
                min-width: 80px; /* Name column */
            }
            
            .attendance-table th:nth-child(4), 
            .attendance-table td:nth-child(4) {
                min-width: 70px; /* Type column */
            }

            /* Make buttons more compact */
            .btn {
                padding: 6px 10px;
                font-size: 12px;
            }
            
            /* Optimize sidebar filters */
            .filter-group h4 {
                font-size: 13px;
            }
            
            .filter-select, 
            input[type="date"] {
                padding: 8px 10px;
                font-size: 13px;
            }

            /* Section Header Styling */
            .section-header h2 {
                font-size: 18px;
            }
        }

        /* iPhone SE specific adjustments */
        @media (max-width: 375px) {
            .btn-text {
                display: none;
            }
            
            .btn i {
                margin-right: 0;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 6px 3px;
                font-size: 11px;
            }
            
            /* Optimize table for very small screens */
            .table-responsive {
                max-height: 350px;
            }
            
            /* Reduce header font size */
            .content-header h2 {
                font-size: 18px;
            }
            
            /* Make status badges more compact */
            .status {
                padding: 3px 6px;
                font-size: 10px;
            }
            
            /* Optimize filters for very small screens */
            .filter-group {
                margin-bottom: 15px;
            }
            
            .sidebar h3 {
                font-size: 16px;
                margin-bottom: 15px;
            }
            
            /* Make cards more compact */
            .summary-card {
                padding: 12px 10px;
            }
            
            .card-icon {
                font-size: 20px;
                margin-bottom: 8px;
            }
            
            .summary-card .value {
                font-size: 20px;
            }
            
            .summary-card h3 {
                font-size: 12px;
                margin-bottom: 5px;
            }
        }
        
        /* Extra small devices */
        @media (max-width: 320px) {
            /* Further optimize for extremely small screens */
            .logo {
                font-size: 16px;
            }
            
            .logo i {
                font-size: 18px;
            }
            
            .date-display {
                font-size: 10px;
            }
            
            .attendance-table th:not(:nth-child(2)):not(:nth-child(8)):not(:nth-child(9)),
            .attendance-table td:not(:nth-child(2)):not(:nth-child(8)):not(:nth-child(9)) {
                padding: 6px 2px;
                font-size: 10px;
            }
            
            .btn-action {
                padding: 4px 8px;
                font-size: 10px;
            }
            
            .table-responsive {
                max-height: 300px;
            }
            
            /* Only show essential columns on extremely small screens */
            .hide-xxs {
                display: none;
            }
            
            /* Adjust pagination for very small screens */
            .pagination {
                gap: 3px;
            }
            
            .pagination-btn {
                padding: 5px 8px;
                font-size: 12px;
            }
        }

        @media (min-width: 1400px) {
            .container {
                max-width: 95%;
                width: 95%;
            }
            
            .dashboard {
                grid-template-columns: 300px 1fr;
            }
            
            .table-responsive {
                max-height: 700px;
            }
            
            body {
                font-size: 17px;
            }
            
            .attendance-table {
                font-size: 15px;
            }
            
            .attendance-table th, 
            .attendance-table td {
                padding: 14px 18px;
            }
            
            .pagination-btn {
                padding: 10px 15px;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 15px;
            }
        }

        @media (min-width: 1600px) {
            .container {
                max-width: 98%;
                width: 98%;
            }
            
            .dashboard {
                grid-template-columns: 320px 1fr;
                gap: 30px;
            }
            
            .table-responsive {
                max-height: 800px;
            }
            
            .summary-cards {
                gap: 30px;
            }
            
            .attendance-table th, 
            .attendance-table td {
                padding: 16px 20px;
            }
        }

        @media (min-width: 1920px) {
            .container {
                max-width: 1800px;
            }
            
            .dashboard {
                grid-template-columns: 350px 1fr;
                gap: 35px;
            }
            
            .summary-cards {
                gap: 35px;
            }
            
            .table-responsive {
                max-height: 900px;
            }
        }
        
        /* Labour Sidebar Panel Styles */
        .labour-sidebar-panel {
            width: 280px;
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 995; /* Below header but above content */
            padding: 2rem;
            padding-top: 100px; /* Space for header */
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            overflow-y: auto;
        }

        .labour-sidebar-panel.labour-collapsed {
            transform: translateX(-100%);
        }

        .labour-main-content {
            margin-left: 280px;
            transition: margin 0.3s ease;
        }

        .labour-main-content.labour-expanded {
            margin-left: 0;
        }

        .labour-toggle-sidebar {
            position: fixed;
            left: 264px;
            top: 120px; /* Position below header */
            z-index: 996; /* Above sidebar but below header */
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

        .labour-toggle-sidebar:hover {
            background: var(--primary-color);
            color: white;
        }

        .labour-toggle-sidebar i {
            transition: transform 0.3s ease;
        }

        .labour-toggle-sidebar.labour-collapsed {
            left: 1rem;
        }

        .labour-toggle-sidebar.labour-collapsed i {
            transform: rotate(180deg);
        }

        .labour-sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .labour-nav-link {
            color: var(--gray-color);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .labour-nav-link:hover, .labour-nav-link.labour-active-link {
            color: var(--primary-color);
            background: rgba(79, 70, 229, 0.1);
        }

        .labour-nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .labour-logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding-top: 1rem;
            color: #D22B2B;
        }

        .labour-logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .labour-sidebar-nav {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 100px);
        }

        /* Add new styles for fixed header */
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999; /* Above sidebar */
            width: 100%;
        }

        .header-content {
            transition: margin-left 0.3s ease;
            padding: 0 25px;
        }

        body {
            padding-top: 80px; /* Adjust based on your header height */
        }

        .labour-main-content {
            margin-top: 20px; /* Space after header */
            transition: margin-left 0.3s ease;
        }

        /* Adjust the sidebar and main content to account for fixed header */
        .labour-sidebar-panel {
            padding-top: 100px; /* Allow space for fixed header */
        }

        /* Reset body padding since we removed the header */
        body {
            padding-top: 0;
            margin-top: 0;
        }
        
        /* Adjust the main content area to start higher */
        .labour-main-content {
            margin-top: 0;
            padding-top: 15px;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }
        
        .labour-main-content.labour-expanded {
            margin-left: 0;
        }
        
        /* Move the summary section up */
        .summary-section {
            margin-top: 0;
        }
        
        /* Adjust sidebar to start from top since header is removed */
        .labour-sidebar-panel {
            padding-top: 2rem;
            top: 0;
            width: 280px;
            background: white;
            height: 100vh;
            position: fixed;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 995;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            overflow-y: auto;
            
            /* Hide scrollbar but keep functionality */
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .labour-sidebar-panel::-webkit-scrollbar {
            display: none;
        }
        
        .labour-sidebar-panel.labour-collapsed {
            transform: translateX(-100%);
        }
        
        /* Move toggle button to top since there's no header */
        .labour-toggle-sidebar {
            position: fixed;
            left: 264px;
            top: 20px;
            z-index: 996;
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
        
        .labour-toggle-sidebar.labour-collapsed {
            left: 1rem;
        }
        
        .labour-toggle-sidebar i {
            transition: transform 0.3s ease;
        }
        
        .labour-toggle-sidebar.labour-collapsed i {
            transform: rotate(180deg);
        }
        
        /* Add space between sidebar logo and first nav item */
        .labour-sidebar-logo {
            margin-bottom: 1.5rem;
        }
        
        /* Adjust summary cards to be more compact */
        .summary-cards {
            margin-top: 10px;
        }
        
        /* Adjust the horizontal filter section */
        .horizontal-filter-section {
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .labour-sidebar-panel {
                padding-top: 1.5rem;
                transform: translateX(-100%);
                z-index: 1050;
            }
            
            .labour-main-content {
                margin-left: 0;
                padding-top: 10px;
            }
            
            .labour-toggle-sidebar {
                top: 15px;
                left: 1rem;
                z-index: 1051;
            }
            
            .labour-sidebar-panel.labour-show {
                transform: translateX(0);
            }
        }
        
        @media (max-width: 480px) {
            .labour-toggle-sidebar {
                top: 15px;
            }
            
            .labour-sidebar-panel {
                padding-top: 1.5rem;
            }
            
            .summary-section {
                padding: 15px;
            }
        }

        /* Vendor tag styling */
        .vendor-tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background-color: rgba(247, 37, 133, 0.15);
            color: #f72585;
            border: 1px solid rgba(247, 37, 133, 0.3);
            gap: 5px;
        }
        
        .vendor-tag i {
            font-size: 10px;
        }

        /* Wages styling */
        .wage-amount {
            display: inline-block;
            font-weight: bold;
            color: #4361ee;
            background-color: rgba(67, 97, 238, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid rgba(67, 97, 238, 0.2);
        }

        /* Enhanced table responsiveness for mobile */
        @media (max-width: 768px) {
            .attendance-table th,
            .attendance-table td {
                padding: 10px 8px;
                font-size: 13px;
            }
            
            .vendor-tag {
                padding: 3px 8px;
                font-size: 11px;
            }
            
            .status {
                padding: 3px 6px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 640px) {
            /* Stack-based table for very small screens */
            .stack-table-container {
                display: none; /* Hide regular table on very small screens */
            }
            
            .mobile-card-container {
                display: block; /* Show mobile cards on small screens */
            }
            
            .mobile-labour-card {
                background-color: white;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                margin-bottom: 15px;
                padding: 15px;
                position: relative;
                overflow: hidden;
            }
            
            .mobile-labour-card.company-labour-row::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background-color: #0ca2e2;
            }
            
            .mobile-labour-card.vendor-labour-row::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background-color: #f72585;
            }
            
            .mobile-card-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            
            .mobile-card-name {
                font-weight: 600;
                font-size: 16px;
            }
            
            .mobile-card-row {
                display: flex;
                margin-bottom: 8px;
                align-items: center;
            }
            
            .mobile-card-label {
                width: 110px;
                font-size: 12px;
                color: var(--gray-color);
                font-weight: 500;
            }
            
            .mobile-card-value {
                flex: 1;
                font-size: 13px;
            }
            
            .mobile-card-actions {
                margin-top: 10px;
                display: flex;
                justify-content: flex-end;
            }
        }
        
        @media (min-width: 641px) {
            .stack-table-container {
                display: block; /* Show regular table on larger screens */
            }
            
            .mobile-card-container {
                display: none; /* Hide mobile cards on larger screens */
            }
        }
    </style>
</head>
<body>
    <!-- Labour Sidebar Panel -->
    <div class="labour-sidebar-panel" id="labourSidebarPanel">
        <div class="labour-sidebar-logo">
            <i class="fas fa-hexagon-fill"></i>
            HR Portal
        </div>
        
        <nav class="labour-sidebar-nav">
            <a href="hr_dashboard.php" class="labour-nav-link">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="employee.php" class="labour-nav-link">
                <i class="fas fa-users"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="labour-nav-link">
                <i class="fas fa-calendar-check"></i>
                Attendance
            </a>
            <a href="shifts.php" class="labour-nav-link">
                <i class="fas fa-clock"></i>
                Shifts
            </a>
            <a href="manager_payouts.php" class="labour-nav-link">
                <i class="fas fa-money-bill-wave"></i>
                Manager Payouts
            </a>
            <a href="company_analytics_dashboard.php" class="labour-nav-link">
                <i class="fas fa-chart-line"></i>
                Company Stats
            </a>
            <a href="salary_overview.php" class="labour-nav-link">
                <i class="fas fa-dollar-sign"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="labour-nav-link">
                <i class="fas fa-calendar-minus"></i>
                Leave Request
            </a>
            <a href="admin/manage_geofence_locations.php" class="labour-nav-link">
                <i class="fas fa-map-marker-alt"></i>
                Geofence Locations
            </a>
            <a href="hr_travel_expenses.php" class="labour-nav-link">
                <i class="fas fa-car"></i>
                Travel Expenses
            </a>
            <a href="hr_overtime_approval.php" class="labour-nav-link">
                <i class="fas fa-hourglass-half"></i>
                Overtime Approval
            </a>
            <a href="hr_password_reset.php" class="labour-nav-link">
                <i class="fas fa-key"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="labour-nav-link">
                <i class="fas fa-cog"></i>
                Settings
            </a>
            <a href="labour_attendance.php" class="labour-nav-link labour-active-link">
                <i class="fas fa-clipboard-list"></i>
                Labour Attendance
            </a>
            <a href="logout.php" class="labour-nav-link labour-logout-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Toggle Button for Sidebar -->
    <button class="labour-toggle-sidebar" id="labourSidebarToggle" title="Toggle Sidebar">
        <i class="fas fa-chevron-left"></i>
    </button>

    <div class="container labour-main-content" id="labourMainContent">
        <div class="summary-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-pie"></i> Attendance Summary</h2>
            </div>
            
        <div class="summary-cards">
            <div class="summary-card present-card">
                    <i class="fas fa-check-circle card-icon"></i>
                <h3>Present Today</h3>
                    <div class="value" id="presentCount"><?php echo $presentCount; ?></div>
            </div>
                <div class="summary-card company-labour-card">
                    <i class="fas fa-hard-hat card-icon"></i>
                    <h3>Company Labour</h3>
                    <div class="value" id="companyLabourCount"><?php echo $companyLabourCount; ?></div>
            </div>
                <div class="summary-card vendor-labour-card">
                    <i class="fas fa-user-tie card-icon"></i>
                    <h3>Vendor Labour</h3>
                    <div class="value" id="vendorLabourCount"><?php echo $vendorLabourCount; ?></div>
            </div>
            <div class="summary-card total-card">
                    <i class="fas fa-users card-icon"></i>
                <h3>Total Labour</h3>
                    <div class="value" id="totalCount"><?php echo $totalCount; ?></div>
                </div>
            </div>
        </div>

        <!-- Horizontal Filter Section -->
        <div class="horizontal-filter-section">
            <div class="filter-section-header">
                <h3><i class="fas fa-filter"></i> Filters</h3>
            </div>
            <form action="" method="GET" id="filterForm" class="horizontal-filter-form">
                <div class="filter-row">
                    <div class="horizontal-filter-group">
                        <h4><i class="fas fa-calendar-alt"></i> Date Range</h4>
                        <div class="date-inputs">
                            <div class="date-input-group">
                                <label for="fromDateFilter">From:</label>
                                <input type="date" id="fromDateFilter" name="from_date" class="filter-select" value="<?php echo $fromDateFilter; ?>">
                            </div>
                            <div class="date-input-group">
                                <label for="toDateFilter">To:</label>
                                <input type="date" id="toDateFilter" name="to_date" class="filter-select" value="<?php echo $toDateFilter; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="horizontal-filter-group">
                        <h4><i class="fas fa-tag"></i> Status</h4>
                        <select id="statusFilter" name="status" class="filter-select">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="present" <?php echo $statusFilter === 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="absent" <?php echo $statusFilter === 'absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="late" <?php echo $statusFilter === 'late' ? 'selected' : ''; ?>>Late</option>
                        </select>
                    </div>
                    
                    <div class="horizontal-filter-group">
                        <h4><i class="fas fa-building"></i> Construction Site</h4>
                        <select id="eventFilter" name="event_id" class="filter-select">
                            <option value="0">All Construction Sites</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['event_id']; ?>" <?php echo $eventFilter == $event['event_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="horizontal-filter-group">
                        <h4><i class="fas fa-user-tie"></i> Vendor</h4>
                        <select id="vendorFilter" name="vendor_id" class="filter-select">
                            <option value="0">All Vendors</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?php echo $vendor['vendor_id']; ?>" <?php echo $vendorFilter == $vendor['vendor_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vendor['vendor_name'] . ' (' . $vendor['vendor_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="horizontal-filter-group">
                        <h4><i class="fas fa-user-tag"></i> Labour Type</h4>
                        <select id="labourTypeFilter" name="labour_type" class="filter-select">
                            <option value="all" <?php echo $labourTypeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="company" <?php echo $labourTypeFilter === 'company' ? 'selected' : ''; ?>>Company Labour</option>
                            <option value="vendor" <?php echo $labourTypeFilter === 'vendor' ? 'selected' : ''; ?>>Vendor Labour</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" id="applyFilters" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="labour_attendance.php" class="btn btn-secondary" style="text-decoration: none;">
                        <i class="fas fa-undo"></i> Reset Filters
                    </a>
                </div>
            </form>
        </div>

        <div class="dashboard">
            <div class="main-content" style="width: 100%; margin-left: 0;">
                <div class="content-header">
                    <h2><i class="fas fa-clipboard-list"></i> Labour Attendance</h2>
                    <div class="action-buttons">
                        <a href="export_labour_attendance.php?from_date=<?php echo urlencode($fromDateFilter); ?>&to_date=<?php echo urlencode($toDateFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&event_id=<?php echo urlencode($eventFilter); ?>&vendor_id=<?php echo urlencode($vendorFilter); ?>&labour_type=<?php echo urlencode($labourTypeFilter); ?>" class="btn btn-secondary">
                            <i class="fas fa-download"></i> <span class="btn-text">Export</span>
                        </a>
                        <a href="add_labour_attendance.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> <span class="btn-text">Add Record</span>
                        </a>
                    </div>
                </div>

                <div class="stack-table-container">
                    <div class="table-responsive">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th class="hide-xs">Contact</th>
                                    <th>Type</th>
                                    <th class="hide-xs">Site</th>
                                    <th class="hide-xs">Vendor</th>
                                    <th class="hide-xxs">Date</th>
                                    <th>Morning</th>
                                    <th>Evening</th>
                                    <th>Wages</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceTableBody">
                                <?php if (empty($allLabours)): ?>
                                    <tr>
                                        <td colspan="11" style="text-align: center;">No labour records found for the selected filters.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allLabours as $index => $labour): ?>
                                        <?php 
                                            // Determine labour type more specifically
                                            $labourType = isset($labour['company_labour_id']) ? 'Company' : 'Vendor';
                                            $labourTypeDetailed = isset($labour['company_labour_id']) ? 'Company Labour' : 'Vendor Labour';
                                            $typeClass = isset($labour['company_labour_id']) ? 'company-labour' : 'vendor-labour';
                                            $rowClass = isset($labour['company_labour_id']) ? 'company-labour-row' : 'vendor-labour-row';
                                            
                                            // Calculate wage based on attendance status
                                            $wageAmount = 0;
                                            $dailyWage = isset($labour['daily_wage']) ? $labour['daily_wage'] : 
                                                        (isset($labour['wage_rate']) ? $labour['wage_rate'] : 0);
                                            
                                            if ($labour['morning_attendance'] === 'present' && $labour['evening_attendance'] === 'present') {
                                                $wageAmount = $dailyWage; // Full day
                                            } elseif ($labour['morning_attendance'] === 'present' || $labour['evening_attendance'] === 'present') {
                                                $wageAmount = $dailyWage / 2; // Half day
                                            } elseif ($labour['morning_attendance'] === 'late' && $labour['evening_attendance'] === 'present') {
                                                $wageAmount = $dailyWage * 0.75; // 3/4 day for late morning but present evening
                                            } elseif ($labour['morning_attendance'] === 'present' && $labour['evening_attendance'] === 'late') {
                                                $wageAmount = $dailyWage * 0.75; // 3/4 day for present morning but late evening
                                            } elseif ($labour['morning_attendance'] === 'late' || $labour['evening_attendance'] === 'late') {
                                                $wageAmount = $dailyWage * 0.5; // Half day for only late in one session
                                            }
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($labour['labour_name']); ?></td>
                                            <td class="hide-xs"><?php echo htmlspecialchars($labour['contact_number'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="status <?php echo $typeClass; ?>">
                                                    <?php if ($labourType === 'Company'): ?>
                                                        <i class="fas fa-hard-hat"></i> Company Labour
                                                    <?php else: ?>
                                                        <i class="fas fa-user-tie"></i> Vendor Labour
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td class="hide-xs">
                                                <?php echo htmlspecialchars($labour['event_name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="hide-xs">
                                                <?php if (isset($labour['vendor_name']) && !empty($labour['vendor_name'])): ?>
                                                    <span class="vendor-tag">
                                                        <i class="fas fa-building"></i> 
                                                        <?php echo htmlspecialchars($labour['vendor_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="hide-xxs"><?php echo htmlspecialchars($labour['attendance_date']); ?></td>
                                            <td>
                                                <?php 
                                                    $morningStatus = $labour['morning_attendance'] ?? 'N/A';
                                                    $morningClass = '';
                                                    $morningIcon = '';
                                                    
                                                    if ($morningStatus === 'present') {
                                                        $morningClass = 'status-present';
                                                        $morningIcon = '<i class="fas fa-check-circle"></i>';
                                                    } elseif ($morningStatus === 'absent') {
                                                        $morningClass = 'status-absent';
                                                        $morningIcon = '<i class="fas fa-times-circle"></i>';
                                                    } elseif ($morningStatus === 'late') {
                                                        $morningClass = 'status-late';
                                                        $morningIcon = '<i class="fas fa-clock"></i>';
                                                    }
                                                ?>
                                                <span class="status <?php echo $morningClass; ?>">
                                                    <?php echo $morningIcon; ?> <?php echo ucfirst($morningStatus); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $eveningStatus = $labour['evening_attendance'] ?? 'N/A';
                                                    $eveningClass = '';
                                                    $eveningIcon = '';
                                                    
                                                    if ($eveningStatus === 'present') {
                                                        $eveningClass = 'status-present';
                                                        $eveningIcon = '<i class="fas fa-check-circle"></i>';
                                                    } elseif ($eveningStatus === 'absent') {
                                                        $eveningClass = 'status-absent';
                                                        $eveningIcon = '<i class="fas fa-times-circle"></i>';
                                                    } elseif ($eveningStatus === 'late') {
                                                        $eveningClass = 'status-late';
                                                        $eveningIcon = '<i class="fas fa-clock"></i>';
                                                    }
                                                ?>
                                                <span class="status <?php echo $eveningClass; ?>">
                                                    <?php echo $eveningIcon; ?> <?php echo ucfirst($eveningStatus); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="wage-amount">
                                                    ₹<?php echo number_format($wageAmount, 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                    $editUrl = isset($labour['company_labour_id']) 
                                                        ? "edit_company_labour.php?id=" . $labour['company_labour_id'] 
                                                        : "edit_vendor_labour.php?id=" . $labour['labour_id'];
                                                ?>
                                                <a href="<?php echo $editUrl; ?>" class="btn btn-secondary btn-action">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Mobile-optimized card view -->
                <div class="mobile-card-container">
                    <?php if (empty($allLabours)): ?>
                        <div class="empty-state">
                            <p style="text-align: center; padding: 20px;">No labour records found for the selected filters.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allLabours as $index => $labour): ?>
                            <?php 
                                // Determine labour type more specifically
                                $labourType = isset($labour['company_labour_id']) ? 'Company' : 'Vendor';
                                $labourTypeDetailed = isset($labour['company_labour_id']) ? 'Company Labour' : 'Vendor Labour';
                                $typeClass = isset($labour['company_labour_id']) ? 'company-labour' : 'vendor-labour';
                                $rowClass = isset($labour['company_labour_id']) ? 'company-labour-row' : 'vendor-labour-row';
                                
                                // Calculate wage based on attendance status
                                $wageAmount = 0;
                                $dailyWage = isset($labour['daily_wage']) ? $labour['daily_wage'] : 
                                            (isset($labour['wage_rate']) ? $labour['wage_rate'] : 0);
                                
                                if ($labour['morning_attendance'] === 'present' && $labour['evening_attendance'] === 'present') {
                                    $wageAmount = $dailyWage; // Full day
                                } elseif ($labour['morning_attendance'] === 'present' || $labour['evening_attendance'] === 'present') {
                                    $wageAmount = $dailyWage / 2; // Half day
                                } elseif ($labour['morning_attendance'] === 'late' && $labour['evening_attendance'] === 'present') {
                                    $wageAmount = $dailyWage * 0.75; // 3/4 day for late morning but present evening
                                } elseif ($labour['morning_attendance'] === 'present' && $labour['evening_attendance'] === 'late') {
                                    $wageAmount = $dailyWage * 0.75; // 3/4 day for present morning but late evening
                                } elseif ($labour['morning_attendance'] === 'late' || $labour['evening_attendance'] === 'late') {
                                    $wageAmount = $dailyWage * 0.5; // Half day for only late in one session
                                }
                                
                                // Determine morning and evening status
                                $morningStatus = $labour['morning_attendance'] ?? 'N/A';
                                $morningClass = '';
                                $morningIcon = '';
                                
                                if ($morningStatus === 'present') {
                                    $morningClass = 'status-present';
                                    $morningIcon = '<i class="fas fa-check-circle"></i>';
                                } elseif ($morningStatus === 'absent') {
                                    $morningClass = 'status-absent';
                                    $morningIcon = '<i class="fas fa-times-circle"></i>';
                                } elseif ($morningStatus === 'late') {
                                    $morningClass = 'status-late';
                                    $morningIcon = '<i class="fas fa-clock"></i>';
                                }
                                
                                $eveningStatus = $labour['evening_attendance'] ?? 'N/A';
                                $eveningClass = '';
                                $eveningIcon = '';
                                
                                if ($eveningStatus === 'present') {
                                    $eveningClass = 'status-present';
                                    $eveningIcon = '<i class="fas fa-check-circle"></i>';
                                } elseif ($eveningStatus === 'absent') {
                                    $eveningClass = 'status-absent';
                                    $eveningIcon = '<i class="fas fa-times-circle"></i>';
                                } elseif ($eveningStatus === 'late') {
                                    $eveningClass = 'status-late';
                                    $eveningIcon = '<i class="fas fa-clock"></i>';
                                }
                                
                                $editUrl = isset($labour['company_labour_id']) 
                                    ? "edit_company_labour.php?id=" . $labour['company_labour_id'] 
                                    : "edit_vendor_labour.php?id=" . $labour['labour_id'];
                            ?>
                            <div class="mobile-labour-card <?php echo $rowClass; ?>">
                                <div class="mobile-card-header">
                                    <div class="mobile-card-name"><?php echo htmlspecialchars($labour['labour_name']); ?></div>
                                    <span class="status <?php echo $typeClass; ?>">
                                        <?php if ($labourType === 'Company'): ?>
                                            <i class="fas fa-hard-hat"></i> Company Labour
                                        <?php else: ?>
                                            <i class="fas fa-user-tie"></i> Vendor Labour
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($labour['contact_number'])): ?>
                                <div class="mobile-card-row">
                                    <div class="mobile-card-label">Contact:</div>
                                    <div class="mobile-card-value"><?php echo htmlspecialchars($labour['contact_number']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($labour['event_name'])): ?>
                                <div class="mobile-card-row">
                                    <div class="mobile-card-label">Site:</div>
                                    <div class="mobile-card-value"><?php echo htmlspecialchars($labour['event_name']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($labour['vendor_name']) && !empty($labour['vendor_name'])): ?>
                                <div class="mobile-card-row">
                                    <div class="mobile-card-label">Vendor:</div>
                                    <div class="mobile-card-value">
                                        <span class="vendor-tag">
                                            <i class="fas fa-building"></i> 
                                            <?php echo htmlspecialchars($labour['vendor_name']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mobile-card-row">
                                    <div class="mobile-card-label">Date:</div>
                                    <div class="mobile-card-value"><?php echo htmlspecialchars($labour['attendance_date']); ?></div>
                                </div>
                                
                                <div class="mobile-card-row">
                                    <div class="mobile-card-label">Morning:</div>
                                    <div class="mobile-card-value">
                                        <span class="status <?php echo $morningClass; ?>">
                                            <?php echo $morningIcon; ?> <?php echo ucfirst($morningStatus); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mobile-card-row">
                                    <div class="mobile-card-label">Evening:</div>
                                    <div class="mobile-card-value">
                                        <span class="status <?php echo $eveningClass; ?>">
                                            <?php echo $eveningIcon; ?> <?php echo ucfirst($eveningStatus); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mobile-card-row">
                                    <div class="mobile-card-label">Wages:</div>
                                    <div class="mobile-card-value">
                                        <span class="wage-amount" style="font-weight: bold; color: #4361ee;">
                                            ₹<?php echo number_format($wageAmount, 2); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mobile-card-actions">
                                    <a href="<?php echo $editUrl; ?>" class="btn btn-secondary btn-action">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination would go here if needed -->
            </div>
        </div>
    </div>

    <script>
        // Debugging: Log database query results and filters
        console.log('Labour Attendance Debug Information:');
        console.log('Date Filters:', {
            fromDate: '<?php echo $fromDateFilter; ?>',
            toDate: '<?php echo $toDateFilter; ?>'
        });
        console.log('Other Filters:', {
            status: '<?php echo $statusFilter; ?>',
            eventId: <?php echo $eventFilter; ?>,
            vendorId: <?php echo $vendorFilter; ?>,
            labourType: '<?php echo $labourTypeFilter; ?>'
        });
        console.log('Query Results:', {
            companyLabourCount: <?php echo $companyLabourCount; ?>,
            vendorLabourCount: <?php echo $vendorLabourCount; ?>,
            totalCount: <?php echo $totalCount; ?>
        });
        
        // Log SQL-related information if authorized
        <?php if ($_SESSION['role'] === 'HR'): ?>
        console.log('Database Tables Check:');
        <?php
            // Check if required tables exist and log results
            $tableCheckInfo = [];
            $requiredTables = ['sv_calendar_events', 'sv_company_labours', 'sv_vendor_labours', 'sv_event_vendors'];
            
            foreach ($requiredTables as $tableName) {
                $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
                $stmt = $pdo->prepare($tableCheckQuery);
                $stmt->execute([$tableName]);
                $result = $stmt->fetch();
                $tableExists = ($result && $result['table_exists'] > 0);
                
                echo "console.log('" . $tableName . " exists: " . ($tableExists ? 'Yes' : 'No') . "');\n";
                
                // If table exists, check record count
                if ($tableExists) {
                    $countQuery = "SELECT COUNT(*) AS record_count FROM " . $tableName;
                    $stmt = $pdo->prepare($countQuery);
                    $stmt->execute();
                    $countResult = $stmt->fetch();
                    $recordCount = $countResult ? $countResult['record_count'] : 0;
                    
                    echo "console.log('" . $tableName . " record count: " . $recordCount . "');\n";
                }
            }
        ?>
        <?php endif; ?>
        
        // Log any PHP errors
        <?php 
            $logFile = 'logs/labour_attendance_errors.log';
            if (file_exists($logFile)) {
                $logContents = file_get_contents($logFile);
                if ($logContents) {
                    echo "console.log('Recent error logs:', `" . addslashes($logContents) . "`);\n";
                }
            }
        ?>
        
        // Display alert for Add Record button functionality check
        document.addEventListener('DOMContentLoaded', function() {
            // Display a guide if there are no records
            if (<?php echo $totalCount; ?> === 0) {
                console.log('No records found. This could be because:');
                console.log('1. The database tables exist but have no data');
                console.log('2. The current filters are too restrictive');
                console.log('3. There might be a SQL query issue');
                console.log('Try clicking the "Add Record" button to add test data');
            }
            
            // Check if Add Record button exists and is clickable
            const addRecordButton = document.querySelector('a[href="add_labour_attendance.php"]');
            if (addRecordButton) {
                console.log('Add Record button found and is clickable');
                addRecordButton.addEventListener('click', function() {
                    console.log('Add Record button clicked');
                });
            } else {
                console.error('Add Record button not found or not properly configured');
            }
        });

        // Display current date
        function updateCurrentDate() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const today = new Date();
            document.getElementById('currentDate').textContent = today.toLocaleDateString('en-US', options);
        }

        // Labour Sidebar Toggle Functionality
        function toggleLabourSidebar() {
            const sidebar = document.getElementById('labourSidebarPanel');
            const mainContent = document.getElementById('labourMainContent');
            const toggleButton = document.getElementById('labourSidebarToggle');
            
            sidebar.classList.toggle('labour-collapsed');
            mainContent.classList.toggle('labour-expanded');
            toggleButton.classList.toggle('labour-collapsed');
            
            // Save state
            localStorage.setItem('labourSidebarCollapsed', sidebar.classList.contains('labour-collapsed'));
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Set event listeners for original sidebar if it exists
            const originalToggleButton = document.getElementById('sidebarToggle');
            if (originalToggleButton) {
                originalToggleButton.addEventListener('click', toggleSidebar);
            }
            
            // Labour Sidebar Functionality
            const labourSidebar = document.getElementById('labourSidebarPanel');
            const labourMainContent = document.getElementById('labourMainContent');
            const labourToggleButton = document.getElementById('labourSidebarToggle');
            
            if (labourSidebar && labourToggleButton) {
                // Check saved state
                const sidebarCollapsed = localStorage.getItem('labourSidebarCollapsed') === 'true';
                if (sidebarCollapsed) {
                    labourSidebar.classList.add('labour-collapsed');
                    labourMainContent.classList.add('labour-expanded');
                    labourToggleButton.classList.add('labour-collapsed');
                }
                
                // Add click event
                labourToggleButton.addEventListener('click', toggleLabourSidebar);
                
                // Enhanced hover effect
                labourToggleButton.addEventListener('mouseenter', function() {
                    const isCollapsed = labourToggleButton.classList.contains('labour-collapsed');
                    const icon = labourToggleButton.querySelector('.fas');
                    
                    if (!isCollapsed) {
                        icon.style.transform = 'translateX(-3px)';
                    } else {
                        icon.style.transform = 'translateX(3px) rotate(180deg)';
                    }
                });
                
                labourToggleButton.addEventListener('mouseleave', function() {
                    const isCollapsed = labourToggleButton.classList.contains('labour-collapsed');
                    const icon = labourToggleButton.querySelector('.fas');
                    
                    if (!isCollapsed) {
                        icon.style.transform = 'none';
                    } else {
                        icon.style.transform = 'rotate(180deg)';
                    }
                });
                
                // Handle window resize
                function handleLabourResize() {
                    if (window.innerWidth <= 768) {
                        labourSidebar.classList.add('labour-collapsed');
                        labourMainContent.classList.add('labour-expanded');
                        labourToggleButton.classList.add('labour-collapsed');
                    } else {
                        // Restore saved state on desktop
                        const savedState = localStorage.getItem('labourSidebarCollapsed');
                        if (savedState === null || savedState === 'false') {
                            labourSidebar.classList.remove('labour-collapsed');
                            labourMainContent.classList.remove('labour-expanded');
                            labourToggleButton.classList.remove('labour-collapsed');
                        }
                    }
                }
                
                window.addEventListener('resize', handleLabourResize);
                
                // Handle clicks outside sidebar on mobile
                document.addEventListener('click', function(event) {
                    if (window.innerWidth <= 768) {
                        const isClickInside = labourSidebar.contains(event.target) || 
                                          labourToggleButton.contains(event.target);
                        
                        if (!isClickInside && !labourSidebar.classList.contains('labour-collapsed')) {
                            toggleLabourSidebar();
                        }
                    }
                });
                
                // Initial check for mobile devices
                handleLabourResize();
            }
            
            // We're using horizontal filters now, so this function is simplified
            function handleResponsiveAdjustments() {
                // Just a placeholder for responsive adjustments if needed in the future
            }
            
            // Initial check
            handleResponsiveAdjustments();
            
            // Listen for window resize
            window.addEventListener('resize', handleResponsiveAdjustments);
        });
    </script>
</body>
</html>