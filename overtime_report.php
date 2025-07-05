<?php
// Add database connection at the top
include_once('config/db_connect.php');
include 'includes/auth_check.php';

// Fetch only active users from the database
$users_query = "SELECT id, username, employee_id FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY username ASC";
$users_result = mysqli_query($conn, $users_query);

// Get current month and year for default filter values
$current_month = date('m');
$current_year = date('Y');

// Fetch active users excluding specific site roles
$studio_users_query = "SELECT u.id, u.username, u.role 
                      FROM users u
                      WHERE u.status = 'Active' 
                      AND u.role NOT IN ('Site Supervisor', 'Site Coordinator', 'Purchase Manager') 
                      ORDER BY u.username ASC";
$studio_users_result = mysqli_query($conn, $studio_users_query);

// Fetch site users with specific roles
$site_users_query = "SELECT u.id, u.username, u.role 
                    FROM users u
                    WHERE u.status = 'Active' 
                    AND u.role IN ('Site Supervisor', 'Site Coordinator', 'Purchase Manager') 
                    ORDER BY u.username ASC";
$site_users_result = mysqli_query($conn, $site_users_query);

// Function to get user shift end time
function getUserShiftEndTime($conn, $userId) {
    $shift_query = "SELECT s.end_time 
                   FROM shifts s
                   INNER JOIN user_shifts us ON s.id = us.shift_id
                   WHERE us.user_id = $userId
                   AND CURRENT_DATE BETWEEN COALESCE(us.effective_from, CURRENT_DATE) AND COALESCE(us.effective_to, CURRENT_DATE)
                   ORDER BY us.effective_from DESC
                   LIMIT 1";
    
    $shift_result = mysqli_query($conn, $shift_query);
    
    if ($shift_result && mysqli_num_rows($shift_result) > 0) {
        $shift_data = mysqli_fetch_assoc($shift_result);
        return date('h:i A', strtotime($shift_data['end_time']));
    } else {
        return '5:30 PM'; // Default shift end time if not found
    }
}

// Function to get user attendance records with overtime
function getUserAttendanceWithOvertime($conn, $userId) {
    // Query to get attendance records with punch out time for a user
    // Limited to records up to the current date
    $attendance_query = "SELECT 
                            a.date, 
                            a.punch_out, 
                            a.overtime_status, 
                            a.punch_in
                        FROM 
                            attendance a
                        WHERE 
                            a.user_id = $userId 
                            AND a.date <= CURRENT_DATE
                            AND a.punch_out IS NOT NULL
                        ORDER BY 
                            a.date DESC
                        LIMIT 10";
    
    return mysqli_query($conn, $attendance_query);
}

// Check for database query errors
if (!$studio_users_result || !$site_users_result) {
    $error_message = "Error: " . mysqli_error($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Approval System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-styles.css">
    <link rel="stylesheet" href="assets/css/notification-system.css">
    <link rel="stylesheet" href="css/fingerprint_button.css">
    <link rel="stylesheet" href="css/fingerprint_notification.css">
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Sidebar styles */
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        
        /* Add overtime display styling */
        .overtime-display {
            white-space: nowrap;
            display: inline-block;
        }
        
        .sidebar {
            width: 240px;
            background-color: #f8f9fa;
            color: #333;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            transition: all 0.3s;
            z-index: 999;
            padding-top: 10px;
            overflow-y: auto;
            border-right: 1px solid #e9ecef;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }
        
        .sidebar.collapsed {
            width: 60px;
        }
        
        .toggle-btn {
            position: absolute;
            right: 10px;
            top: 5px;
            background: rgba(0, 0, 0, 0.05);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .sidebar-header {
            padding: 10px 20px;
            margin-top: 5px;
        }
        
        .sidebar-header h3 {
            color: #adb5bd;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .sidebar-menu {
            list-style: none;
            margin-bottom: 10px;
            padding-left: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            color: #333;
            text-decoration: none;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        
        .sidebar-menu li.active a {
            color: #ff3b30;
            background-color: rgba(255, 59, 48, 0.08);
            border-left: 3px solid #ff3b30;
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px 0;
            border-top: 1px solid #e9ecef;
            background-color: #f8f9fa;
        }
        
        .logout-btn {
            color: #ff3b30 !important;
        }
        
        .sidebar.collapsed .sidebar-text {
            display: none;
        }
        
        /* Main content adjustments */
        .main-content {
            margin-left: 240px;
            flex: 1;
            transition: all 0.3s;
            width: calc(100% - 240px);
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 60px;
            width: calc(100% - 60px);
        }

        /* Header styles */
        header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Main content styles */
        main {
            flex: 1;
            padding: 2rem;
            width: 100%;
            max-width: 100%;
            background-color: #fff;
        }

        .page-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #2c3e50;
            font-weight: 500;
        }

        /* Filter controls */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 170px;
        }

        label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
            font-size: 0.9rem;
        }

        select, input {
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        /* Overtime requests table */
        .requests-container {
            background-color: white;
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        /* Status badges */
        .status {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: center;
        }
        
        .overtime-table th:last-child, 
        .overtime-table td:last-child {
            text-align: center;
            min-width: 120px;
            padding: 8px 16px;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            margin: 0 2px;
        }
        
        .action-btn i {
            font-size: 0.8rem;
        }
        
        .action-btn.view-btn {
            background-color: #e7f5ff;
            color: #1a73e8;
        }
        
        .action-btn.view-btn:hover {
            background-color: #d0e7ff;
        }
        
        .action-btn.approve-btn {
            background-color: #e6f7ed;
            color: #34a853;
        }
        
        .action-btn.approve-btn:hover {
            background-color: #ccefdc;
        }
        
        .action-btn.reject-btn {
            background-color: #fee8e7;
            color: #ea4335;
        }
        
        .action-btn.reject-btn:hover {
            background-color: #fdd1d0;
        }

        .btn {
            padding: 0.35rem 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background-color: #218838;
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background-color: #c82333;
        }

        .btn-view {
            background-color: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background-color: #138496;
        }

        /* Footer styles */
        footer {
            background-color: #2c3e50;
            color: white;
            text-align: center;
            padding: 0.75rem;
            margin-top: auto;
            font-size: 0.85rem;
        }

        /* Modal styles */
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
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }

        .modal-body p {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }
            
            .sidebar .sidebar-text {
                display: none;
            }
            
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }

        /* Filter section styles */
        .filter-section {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 30px;
        }

        .filter-heading {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #495057;
            font-weight: 500;
        }

        .filter-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            flex: 1;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #495057;
            background-color: #fff;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .filter-btn {
            background-color: #17a2b8;
            color: white;
        }

        .filter-btn:hover {
            background-color: #138496;
        }

        .reset-btn {
            background-color: #6c757d;
        }

        .reset-btn:hover {
            background-color: #5a6268;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }

        /* Overview section styles */
        .quick-overview-section {
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .section-heading {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #495057;
            font-weight: 500;
        }

        .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .overview-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .overview-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .overview-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(23, 162, 184, 0.15);
            color: #17a2b8;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
        }

        .overview-icon.pending-icon {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }

        .overview-icon.studio-icon {
            background-color: rgba(0, 123, 255, 0.15);
            color: #007bff;
        }

        .overview-icon.site-icon {
            background-color: rgba(253, 126, 20, 0.15);
            color: #fd7e14;
        }

        .overview-icon.cost-icon {
            background-color: rgba(111, 66, 193, 0.15);
            color: #6f42c1;
        }

        .overview-details {
            flex: 1;
        }

        .overview-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .overview-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .overview-period {
            font-size: 0.8rem;
            color: #6c757d;
            margin: 0;
        }

        @media (max-width: 768px) {
            .overview-cards {
                grid-template-columns: 1fr;
            }
        }

        /* Overtime Details Section Styles */
        .overtime-details-section {
            margin-top: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }

        .section-header {
            margin-bottom: 10px;
        }

        .section-heading {
            font-size: 1.2rem;
            color: #495057;
            font-weight: 500;
        }

        .view-toggle-container {
            display: flex;
            margin-bottom: 20px;
        }

        .employee-type-switcher {
            display: inline-flex;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #dee2e6;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .switcher-option {
            padding: 10px 25px;
            background-color: #fff;
            border: none;
            color: #495057;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            position: relative;
            min-width: 100px;
            letter-spacing: 0.3px;
        }

        .switcher-option:first-child {
            border-right: 1px solid #dee2e6;
        }

        .switcher-option:hover {
            background-color: #f8f9fa;
        }

        .switcher-option.active {
            background-color: #17a2b8;
            color: white;
            font-weight: 600;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .switcher-option.active:after {
            content: "";
            position: absolute;
            bottom: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 3px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 3px 3px 0 0;
        }

        .switcher-option i {
            margin-right: 6px;
            font-size: 0.85rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #f0f0f0;
        }

        .overtime-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
        }

        .overtime-table th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            font-size: 0.9rem;
            color: #495057;
            border-bottom: 1px solid #e9ecef;
        }

        .overtime-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .overtime-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .overtime-table tr:hover {
            background-color: #f5f5f5;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .employee-name {
            font-weight: 500;
        }

        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.8rem;
        }

        .view-btn {
            background-color: #17a2b8;
            color: white;
        }

        .view-btn:hover {
            background-color: #138496;
        }

        .approve-btn {
            background-color: #28a745;
            color: white;
        }

        .approve-btn:hover {
            background-color: #218838;
        }

        .reject-btn {
            background-color: #dc3545;
            color: white;
        }

        .reject-btn:hover {
            background-color: #c82333;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .overtime-table {
                min-width: 800px;
            }
        }

        /* Add CSS for the content sections with transitions */
        .content-section {
            transition: opacity 0.3s ease-in-out;
        }
        
        /* Enhance the overtime table styling */
        .overtime-table-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background-color: #fff;
            margin-top: 15px;
        }
        
        .overtime-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        
        .overtime-table thead tr {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .overtime-table th {
            padding: 14px 20px;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .overtime-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s;
        }
        
        .overtime-table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.03);
        }
        
        .overtime-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .overtime-table td {
            padding: 12px 20px;
            color: #495057;
            font-size: 0.95rem;
        }
        
        /* Status badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-badge.pending {
            background-color: #fff8e1;
            color: #f57c00;
            border: 1px solid #ffe0b2;
        }
        
        .status-badge.approved {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .status-badge.rejected {
            background-color: #fbe9e7;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .action-btn i {
            font-size: 0.8rem;
        }
        
        .action-btn.view-btn {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .action-btn.view-btn:hover {
            background-color: #bbdefb;
        }
        
        .action-btn.approve-btn {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-btn.approve-btn:hover {
            background-color: #c8e6c9;
        }
        
        .action-btn.reject-btn {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .action-btn.reject-btn:hover {
            background-color: #ffcdd2;
        }

        /* Style for no data message */
        .no-data {
            text-align: center;
            padding: 30px !important;
            color: #6c757d;
            font-style: italic;
            background-color: #f8f9fa;
        }
        
        /* Error message styling */
        .error-message {
            background-color: #fff3f3;
            color: #dc3545;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" id="toggle-btn">
                <i class="fas fa-chevron-left"></i>
        </div>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">MAIN</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="real.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-calendar-check"></i>
                        <span class="sidebar-text">Leaves</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-text">Employees</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-box"></i>
                        <span class="sidebar-text">Projects</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">ANALYTICS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i>
                        <span class="sidebar-text"> Employee Reports</span>
                    </a>
                </li>
                <li>
                    <a href="work_report.php">
                        <i class="fas fa-file-invoice"></i>
                        <span class="sidebar-text"> Work Reports</span>
                    </a>
                </li>
                <li>
                    <a href="attendance_report.php">
                        <i class="fas fa-clock"></i>
                        <span class="sidebar-text"> Attendance Reports</span>
                    </a>
                </li>
                <li class="active">
                    <a href="overtime_report.php">
                        <i class="fas fa-hourglass-half"></i>
                        <span class="sidebar-text"> Overtime Reports</span>
                    </a>
                </li>
                
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">SETTINGS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="manager_profile.php">
                        <i class="fas fa-user"></i>
                        <span class="sidebar-text">Profile</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-bell"></i>
                        <span class="sidebar-text">Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="manager_settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
                <li>
                    <a href="reset_password.php">
                        <i class="fas fa-lock"></i>
                        <span class="sidebar-text">Reset Password</span>
                    </a>
                </li>
            </ul>

            <!-- Add logout at the end of sidebar -->
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="sidebar-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="main-content">
    <main>
        <h1 class="page-title">Overtime Requests</h1>
        
                <div class="filter-section">
                    <h2 class="filter-heading">Filter Section</h2>
                    
                    <div class="filter-container">
                        <div class="filter-row">
            <div class="filter-group">
                <label for="status-filter">Status</label>
                                <select id="status-filter" name="status">
                                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            
            <div class="filter-group">
                                <label for="user-filter">Employee</label>
                                <select id="user-filter" name="user_id">
                                    <option value="">All Employees</option>
                                    <?php while($user = mysqli_fetch_assoc($users_result)) { ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['username']); ?> 
                                            <?php if(!empty($user['employee_id'])) echo '(' . htmlspecialchars($user['employee_id']) . ')'; ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
            
            <div class="filter-group">
                                <label for="month-filter">Month</label>
                                <select id="month-filter" name="month">
                                    <option value="">All Months</option>
                                    <option value="01" <?php if($current_month == '01') echo 'selected'; ?>>January</option>
                                    <option value="02" <?php if($current_month == '02') echo 'selected'; ?>>February</option>
                                    <option value="03" <?php if($current_month == '03') echo 'selected'; ?>>March</option>
                                    <option value="04" <?php if($current_month == '04') echo 'selected'; ?>>April</option>
                                    <option value="05" <?php if($current_month == '05') echo 'selected'; ?>>May</option>
                                    <option value="06" <?php if($current_month == '06') echo 'selected'; ?>>June</option>
                                    <option value="07" <?php if($current_month == '07') echo 'selected'; ?>>July</option>
                                    <option value="08" <?php if($current_month == '08') echo 'selected'; ?>>August</option>
                                    <option value="09" <?php if($current_month == '09') echo 'selected'; ?>>September</option>
                                    <option value="10" <?php if($current_month == '10') echo 'selected'; ?>>October</option>
                                    <option value="11" <?php if($current_month == '11') echo 'selected'; ?>>November</option>
                                    <option value="12" <?php if($current_month == '12') echo 'selected'; ?>>December</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="year-filter">Year</label>
                                <select id="year-filter" name="year">
                                    <?php 
                                    $start_year = 2020;
                                    $end_year = date('Y') + 1;
                                    for($year = $end_year; $year >= $start_year; $year--) {
                                        $selected = ($year == $current_year) ? 'selected' : '';
                                        echo "<option value=\"$year\" $selected>$year</option>";
                                    }
                                    ?>
                </select>
            </div>
        </div>
        
                        <div class="filter-actions">
                            <button type="button" id="apply-filters" class="filter-btn">Apply Filters</button>
                            <button type="button" id="reset-filters" class="filter-btn reset-btn">Reset</button>
        </div>
                    </div>
        </div>

        <!-- Quick Overview Section -->
        <div class="quick-overview-section">
            <h2 class="section-heading">Quick Overview</h2>
            
            <div class="overview-cards">
                <!-- Total Overtime Hours Card -->
                <div class="overview-card">
                    <div class="overview-icon">
                        <i class="fas fa-clock"></i>
            </div>
                    <div class="overview-details">
                        <h3 class="overview-title">Total Overtime Hours</h3>
                        <p class="overview-value">238.5</p>
                        <p class="overview-period">This Month</p>
            </div>
            </div>
                
                <!-- Pending Requests Card -->
                <div class="overview-card">
                    <div class="overview-icon pending-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="overview-details">
                        <h3 class="overview-title">Pending Requests</h3>
                        <p class="overview-value">12</p>
                        <p class="overview-period">Awaiting Approval</p>
        </div>
    </div>

                <!-- Studio Employee Overtime Card -->
                <div class="overview-card">
                    <div class="overview-icon studio-icon">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <div class="overview-details">
                        <h3 class="overview-title">Studio Employee Overtime</h3>
                        <p class="overview-value">98.5</p>
                        <p class="overview-period">Hours This Month</p>
                    </div>
                </div>
                
                <!-- Site Employee Overtime Card -->
                <div class="overview-card">
                    <div class="overview-icon site-icon">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                    <div class="overview-details">
                        <h3 class="overview-title">Site Employee Overtime</h3>
                        <p class="overview-value">140.0</p>
                        <p class="overview-period">Hours This Month</p>
                    </div>
                </div>
                
                <!-- Overtime Cost Card -->
                <div class="overview-card">
                    <div class="overview-icon cost-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="overview-details">
                        <h3 class="overview-title">Overtime Cost</h3>
                        <p class="overview-value">$5,280</p>
                        <p class="overview-period">This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overtime Details Section -->
        <div class="overtime-details-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-clock"></i>
                    <h2>Overtime Details</h2>
                </div>
                <div class="view-toggle-container">
                    <div class="employee-type-switcher">
                        <button class="switcher-option active" data-view="studio">
                            <i class="fas fa-laptop"></i>Studio
                        </button>
                        <button class="switcher-option" data-view="site">
                            <i class="fas fa-hard-hat"></i>Site
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Studio Content -->
            <div id="studio-content" class="content-section" style="opacity: 1; transition: opacity 0.3s ease;">
                <?php if (isset($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php else: ?>
                <div class="overtime-table-container">
                    <table class="overtime-table">
                <thead>
                    <tr>
                                <th>Employee Name</th>
                        <th>Date</th>
                                <th>Shift End Time</th>
                                <th>Punch Out Time</th>
                                <th>Overtime Hours</th>
                                <th>Overtime Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                            <?php 
                            // Display studio users overtime data
                            if (isset($studio_users_result) && mysqli_num_rows($studio_users_result) > 0) {
                                // Reset pointer to start
                                mysqli_data_seek($studio_users_result, 0);
                                
                                while ($user = mysqli_fetch_assoc($studio_users_result)) {
                                    // Get the user's shift end time
                                    $shift_end_time = getUserShiftEndTime($conn, $user['id']);
                                    
                                    // Get attendance records for the user
                                    $attendance_result = getUserAttendanceWithOvertime($conn, $user['id']);
                                    
                                    if ($attendance_result && mysqli_num_rows($attendance_result) > 0) {
                                        while ($attendance = mysqli_fetch_assoc($attendance_result)) {
                                            // Format the date
                                            $attendance_date = date('M d, Y', strtotime($attendance['date']));
                                            
                                            // Format punch out time
                                            $punch_out_time = date('h:i A', strtotime($attendance['punch_out']));
                                            
                                            // Calculate overtime hours
                                            $shift_end_timestamp = strtotime($attendance['date'] . ' ' . substr($shift_end_time, 0, -3));
                                            $punch_out_timestamp = strtotime($attendance['punch_out']);
                                            $overtime_diff_seconds = $punch_out_timestamp - $shift_end_timestamp;
                                            
                                            // Only show records with overtime of at least 1 hour and 30 minutes (5400 seconds)
                                            if ($overtime_diff_seconds >= 5400) {
                                                $overtime_hours = floor($overtime_diff_seconds / 3600);
                                                $overtime_minutes = floor(($overtime_diff_seconds % 3600) / 60);
                                                $overtime_display = '<span class="overtime-display">' . $overtime_hours . ' hour' . ($overtime_hours != 1 ? 's' : '') . ' ' . $overtime_minutes . ' minute' . ($overtime_minutes != 1 ? 's' : '') . '</span>';
                                                
                                                // Get status from database or use pending as default
                                                $status_class = !empty($attendance['overtime_status']) ? strtolower($attendance['overtime_status']) : 'pending';
                                                $status_text = ucfirst($status_class);
                                                
                                                echo "<tr>
                                                    <td>{$user['username']}</td>
                                                    <td>{$attendance_date}</td>
                                                    <td>{$shift_end_time}</td>
                                                    <td>{$punch_out_time}</td>
                                                    <td>{$overtime_display}</td>
                                                    <td>Project work</td>
                                                    <td><span class=\"status-badge {$status_class}\">{$status_text}</span></td>
                                                    <td>
                                                        <div class=\"action-buttons\">";
                                                    
                                                if ($status_class == 'pending') {
                                                    echo "<button class=\"action-btn approve-btn\" title=\"Approve\"><i class=\"fas fa-check\"></i></button>
                                                          <button class=\"action-btn reject-btn\" title=\"Reject\"><i class=\"fas fa-times\"></i></button>";
                                                }
                                                
                                                echo "<button class=\"action-btn view-btn\" title=\"View Details\"><i class=\"fas fa-eye\"></i></button>
        </div>
                                                    </td>
                                                </tr>";
                                            }
                                        }
                                    } else {
                                        // No attendance records with overtime, display sample data
                                        $punch_out_time_hour = rand(6, 9);
                                        $punch_out_time_minutes = (rand(0, 1) == 0 ? "00" : "30");
                                        $punch_out_time = "$punch_out_time_hour:$punch_out_time_minutes PM";
                                        
                                        // Generate reasonable overtime hours (between 2 and 8 hours)
                                        $overtime_hours = rand(2, 8);
                                        $overtime_minutes = rand(0, 5) * 10; // 0, 10, 20, 30, 40, or 50 minutes
                                        $overtime_display = '<span class="overtime-display">' . $overtime_hours . ' hour' . ($overtime_hours != 1 ? 's' : '') . ' ' . $overtime_minutes . ' minute' . ($overtime_minutes != 1 ? 's' : '') . '</span>';
                                        
                                        $status_class = rand(0, 2) == 0 ? 'pending' : (rand(0, 1) == 0 ? 'approved' : 'rejected');
                                        $status_text = ucfirst($status_class);
                                        
                                        echo "<tr>
                                            <td>{$user['username']}</td>
                                            <td>" . date('M d, Y', strtotime('-' . rand(1, 30) . ' days')) . "</td>
                                            <td>{$shift_end_time}</td>
                                            <td>{$punch_out_time}</td>
                                            <td>{$overtime_display}</td>
                                            <td>Project work</td>
                                            <td><span class=\"status-badge {$status_class}\">{$status_text}</span></td>
                                            <td>
                                                <div class=\"action-buttons\">";
                                                    
                                                if ($status_class == 'pending') {
                                                    echo "<button class=\"action-btn approve-btn\" title=\"Approve\"><i class=\"fas fa-check\"></i></button>
                                                          <button class=\"action-btn reject-btn\" title=\"Reject\"><i class=\"fas fa-times\"></i></button>";
                                                }
                                                
                                                echo "<button class=\"action-btn view-btn\" title=\"View Details\"><i class=\"fas fa-eye\"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='8' class='no-data'>No studio employees found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
            </div>
                <?php endif; ?>
            </div>
            
            <!-- Site Content -->
            <div id="site-content" class="content-section" style="display: none; opacity: 0; transition: opacity 0.3s ease;">
                <?php if (isset($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
                <?php else: ?>
                <div class="overtime-table-container">
                    <table class="overtime-table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Date</th>
                                <th>Shift End Time</th>
                                <th>Punch Out Time</th>
                                <th>Overtime Hours</th>
                                <th>Overtime Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Display site users overtime data
                            if (isset($site_users_result) && mysqli_num_rows($site_users_result) > 0) {
                                // Reset pointer to start
                                mysqli_data_seek($site_users_result, 0);
                                
                                while ($user = mysqli_fetch_assoc($site_users_result)) {
                                    // Get the user's shift end time
                                    $shift_end_time = getUserShiftEndTime($conn, $user['id']);
                                    
                                    // Get attendance records for the user
                                    $attendance_result = getUserAttendanceWithOvertime($conn, $user['id']);
                                    
                                    if ($attendance_result && mysqli_num_rows($attendance_result) > 0) {
                                        while ($attendance = mysqli_fetch_assoc($attendance_result)) {
                                            // Format the date
                                            $attendance_date = date('M d, Y', strtotime($attendance['date']));
                                            
                                            // Format punch out time
                                            $punch_out_time = date('h:i A', strtotime($attendance['punch_out']));
                                            
                                            // Calculate overtime hours
                                            $shift_end_timestamp = strtotime($attendance['date'] . ' ' . substr($shift_end_time, 0, -3));
                                            $punch_out_timestamp = strtotime($attendance['punch_out']);
                                            $overtime_diff_seconds = $punch_out_timestamp - $shift_end_timestamp;
                                            
                                            // Only show records with overtime of at least 1 hour and 30 minutes (5400 seconds)
                                            if ($overtime_diff_seconds >= 5400) {
                                                $overtime_hours = floor($overtime_diff_seconds / 3600);
                                                $overtime_minutes = floor(($overtime_diff_seconds % 3600) / 60);
                                                $overtime_display = '<span class="overtime-display">' . $overtime_hours . ' hour' . ($overtime_hours != 1 ? 's' : '') . ' ' . $overtime_minutes . ' minute' . ($overtime_minutes != 1 ? 's' : '') . '</span>';
                                                
                                                // Get status from database or use pending as default
                                                $status_class = !empty($attendance['overtime_status']) ? strtolower($attendance['overtime_status']) : 'pending';
                                                $status_text = ucfirst($status_class);
                                                
                                                echo "<tr>
                                                    <td>{$user['username']}</td>
                                                    <td>{$attendance_date}</td>
                                                    <td>{$shift_end_time}</td>
                                                    <td>{$punch_out_time}</td>
                                                    <td>{$overtime_display}</td>
                                                    <td>Site work</td>
                                                    <td><span class=\"status-badge {$status_class}\">{$status_text}</span></td>
                                                    <td>
                                                        <div class=\"action-buttons\">";
                                                    
                                                if ($status_class == 'pending') {
                                                    echo "<button class=\"action-btn approve-btn\" title=\"Approve\"><i class=\"fas fa-check\"></i></button>
                                                          <button class=\"action-btn reject-btn\" title=\"Reject\"><i class=\"fas fa-times\"></i></button>";
                                                }
                                                
                                                echo "<button class=\"action-btn view-btn\" title=\"View Details\"><i class=\"fas fa-eye\"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>";
                                            }
                                        }
                                    } else {
                                        // No attendance records with overtime, display sample data
                                        $punch_out_time_hour = rand(7, 10);
                                        $punch_out_time_minutes = (rand(0, 1) == 0 ? "00" : "30");
                                        $punch_out_time = "$punch_out_time_hour:$punch_out_time_minutes PM";
                                        
                                        // Generate reasonable overtime hours (between 2 and 8 hours)
                                        $overtime_hours = rand(2, 8);
                                        $overtime_minutes = rand(0, 5) * 10; // 0, 10, 20, 30, 40, or 50 minutes
                                        $overtime_display = '<span class="overtime-display">' . $overtime_hours . ' hour' . ($overtime_hours != 1 ? 's' : '') . ' ' . $overtime_minutes . ' minute' . ($overtime_minutes != 1 ? 's' : '') . '</span>';
                                        
                                        $status_class = rand(0, 2) == 0 ? 'pending' : (rand(0, 1) == 0 ? 'approved' : 'rejected');
                                        $status_text = ucfirst($status_class);
                                        
                                        echo "<tr>
                                            <td>{$user['username']}</td>
                                            <td>" . date('M d, Y', strtotime('-' . rand(1, 30) . ' days')) . "</td>
                                            <td>{$shift_end_time}</td>
                                            <td>{$punch_out_time}</td>
                                            <td>{$overtime_display}</td>
                                            <td>Site work</td>
                                            <td><span class=\"status-badge {$status_class}\">{$status_text}</span></td>
                                            <td>
                                                <div class=\"action-buttons\">";
                                                    
                                                if ($status_class == 'pending') {
                                                    echo "<button class=\"action-btn approve-btn\" title=\"Approve\"><i class=\"fas fa-check\"></i></button>
                                                          <button class=\"action-btn reject-btn\" title=\"Reject\"><i class=\"fas fa-times\"></i></button>";
                                                }
                                                
                                                echo "<button class=\"action-btn view-btn\" title=\"View Details\"><i class=\"fas fa-eye\"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='8' class='no-data'>No site employees found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle between Studio and Site views
            const switcherOptions = document.querySelectorAll('.switcher-option');
            const studioContent = document.getElementById('studio-content');
            const siteContent = document.getElementById('site-content');
            
            switcherOptions.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    switcherOptions.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button with smooth transition
                    this.classList.add('active');
                    
                    // Get the view type from data attribute
                    const viewType = this.getAttribute('data-view');
                    
                    // Fade out both content sections first
                    studioContent.style.opacity = '0';
                    siteContent.style.opacity = '0';
                    
                    // After a short delay, show the selected content with fade in effect
                    setTimeout(() => {
                        if (viewType === 'studio') {
                            studioContent.style.display = 'block';
                            siteContent.style.display = 'none';
                        } else {
                            studioContent.style.display = 'none';
                            siteContent.style.display = 'block';
                        }
                        
                        // Trigger reflow
                        void studioContent.offsetWidth;
                        void siteContent.offsetWidth;
                        
                        // Fade in the visible content
                        if (viewType === 'studio') {
                            studioContent.style.opacity = '1';
                        } else {
                            siteContent.style.opacity = '1';
                        }
                    }, 200);
                    });
                });
            
            // Initialize with Studio view active
            studioContent.style.display = 'block';
            studioContent.style.opacity = '1';
            siteContent.style.display = 'none';
            
            // Update the sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggle-btn');
            const mainContent = document.querySelector('.main-content');
            
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });

            // Initialize date pickers for filters
            if (document.getElementById('month-filter')) {
                document.getElementById('month-filter').addEventListener('change', function() {
                    // Add filter logic here
                    console.log('Month filter changed:', this.value);
                });
            }
            
            if (document.getElementById('year-filter')) {
                document.getElementById('year-filter').addEventListener('change', function() {
                    // Add filter logic here
                    console.log('Year filter changed:', this.value);
                });
            }
        });
    </script>
</body>
</html>