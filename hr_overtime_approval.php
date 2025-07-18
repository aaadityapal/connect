<?php
// Start session to maintain user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not authorized
    header('Location: login.php');
    exit();
}

// Check if user has HR role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    // Redirect to dashboard if not HR role
    header('Location: permission_denied.php');
    exit();
}

// Database connection
require_once 'config/db_connect.php';

// Debug: Log session data for troubleshooting
error_log("Overtime Reports - Session Data: " . print_r($_SESSION, true));

// Fetch users for Studio view (excluding site roles, trainees, and marketing roles)
$studioQuery = "SELECT id, username, position, email, phone_number, designation, department, role, reporting_manager, profile_picture 
                FROM users 
                WHERE role NOT IN ('Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Sales', 'Studio Trainees', 'Trainees', 'Site Trainees', 'Graphic Designer', 'Social Media Marketing', 'Marketing') 
                AND deleted_at IS NULL 
                AND status = 'active'
                ORDER BY username";
$studioResult = mysqli_query($conn, $studioQuery);
$studioUsers = [];

if ($studioResult && mysqli_num_rows($studioResult) > 0) {
    while ($row = mysqli_fetch_assoc($studioResult)) {
        $studioUsers[] = $row;
    }
}

// Fetch users for Site view (site roles and marketing roles, excluding trainees)
$siteQuery = "SELECT id, username, position, email, phone_number, designation, department, role, reporting_manager, profile_picture 
              FROM users 
              WHERE role IN ('Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Sales', 'Graphic Designer', 'Social Media Marketing', 'Marketing') 
              AND role NOT IN ('Studio Trainees', 'Trainees', 'Site Trainees')
              AND deleted_at IS NULL 
              AND status = 'active'
              ORDER BY username";
$siteResult = mysqli_query($conn, $siteQuery);
$siteUsers = [];

if ($siteResult && mysqli_num_rows($siteResult) > 0) {
    while ($row = mysqli_fetch_assoc($siteResult)) {
        $siteUsers[] = $row;
    }
}

// Fetch user shift assignments
$shiftsQuery = "SELECT us.user_id, s.shift_name, s.start_time, s.end_time, us.effective_from, us.effective_to
                FROM user_shifts us
                JOIN shifts s ON us.shift_id = s.id
                WHERE (us.effective_to IS NULL OR us.effective_to >= CURDATE())
                ORDER BY us.effective_from DESC";
$shiftsResult = mysqli_query($conn, $shiftsQuery);
$userShifts = [];

if ($shiftsResult && mysqli_num_rows($shiftsResult) > 0) {
    while ($row = mysqli_fetch_assoc($shiftsResult)) {
        $userId = $row['user_id'];
        
        // Keep only the most recent shift assignment for each user
        if (!isset($userShifts[$userId])) {
            $userShifts[$userId] = $row;
        }
    }
}

// Fetch attendance data with overtime for all users (excluding trainees)
// We'll calculate the actual overtime based on shift end time later
$attendanceQuery = "SELECT a.*, 
                   u.username, u.profile_picture, u.department, u.role,
                   manager.username as manager_username,
                   a.work_report,
                   otn.message as overtime_message,
                   otn.manager_response,
                   otn.created_at as submitted_on
                   FROM attendance a
                   JOIN users u ON a.user_id = u.id
                   LEFT JOIN users manager ON a.overtime_approved_by = manager.id
                   LEFT JOIN overtime_notifications otn ON a.id = otn.overtime_id
                   WHERE a.punch_out IS NOT NULL
                   AND (a.overtime_status IS NULL OR a.overtime_status != 'rejected')
                   AND u.role NOT IN ('Studio Trainees', 'Trainees', 'Site Trainees')
                   GROUP BY a.id
                   ORDER BY a.date DESC";
$attendanceResult = mysqli_query($conn, $attendanceQuery);
$attendanceData = [];

if ($attendanceResult && mysqli_num_rows($attendanceResult) > 0) {
    while ($row = mysqli_fetch_assoc($attendanceResult)) {
        // Add shift information if available
        $userId = $row['user_id'];
        if (isset($userShifts[$userId])) {
            $row['shift_name'] = $userShifts[$userId]['shift_name'];
            $row['shift_start'] = $userShifts[$userId]['start_time'];
            $row['shift_end'] = $userShifts[$userId]['end_time'];
        } else {
            $row['shift_name'] = 'Default';
            $row['shift_start'] = '09:00:00';
            $row['shift_end'] = '18:00:00';
        }
        
        // Calculate actual overtime based on shift end time
        if (!empty($row['punch_out']) && !empty($row['shift_end'])) {
            // Convert times to minutes for easier calculation
            $shiftEndParts = explode(':', $row['shift_end']);
            $shiftEndMinutes = ($shiftEndParts[0] * 60) + $shiftEndParts[1];
            
            $punchOutParts = explode(':', $row['punch_out']);
            $punchOutMinutes = ($punchOutParts[0] * 60) + $punchOutParts[1];
            
            // Check if punch out is before shift end time
            if ($punchOutMinutes <= $shiftEndMinutes) {
                // If user punched out before or at shift end time, overtime is 0
                $row['calculated_overtime'] = 0;
                // Don't include in attendance data as there's no overtime
            } else {
                // Calculate overtime in minutes
                $overtimeMinutes = $punchOutMinutes - $shiftEndMinutes;
                
                // Convert back to hours with decimal
                $overtimeHours = $overtimeMinutes / 60;
                
                // Round DOWN to nearest 0.5 (half hour)
                // This ensures 2.9 hours becomes 2.5, not 3.0
                $overtimeHours = floor($overtimeHours * 2) / 2;
                
                // Only include if overtime is at least 1.5 hours
                if ($overtimeHours >= 1.5) {
                    $row['calculated_overtime'] = $overtimeHours;
                    
                    // Use the overtime status from the database if available, otherwise default to 'pending'
                    if (!isset($row['overtime_status']) || empty($row['overtime_status'])) {
                        $row['overtime_status'] = 'pending';
                    }
                    
                    // Debug: Log the status to help troubleshoot
                    error_log("Overtime status for user {$row['user_id']}: " . $row['overtime_status']);
                    
                    // Use manager username instead of ID if available
                    if (!empty($row['manager_username'])) {
                        $row['manager'] = $row['manager_username'];
                    }
                    
        $attendanceData[] = $row;
                }
            }
        }
    }
}

// Group attendance data by user type (studio vs site)
$studioAttendance = [];
$siteAttendance = [];

foreach ($attendanceData as $record) {
    $role = $record['role'];
    if (in_array($role, ['Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Sales'])) {
        $siteAttendance[] = $record;
    } else {
        $studioAttendance[] = $record;
    }
}

// Close the database connection
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Approval</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
            width: 100%;
            background-color: #f8f9fa;
            color: #333;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Sidebar styles */
        .dashboard {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #7C3AED;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --dark: #111827;
            --gray: #6B7280;
            --light: #F3F4F6;
            --sidebar-width: 280px;
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
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease, width 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
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
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
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

        /* Update nav container to allow for margin-top: auto on logout */
        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px); /* Adjust based on your logo height */
        }

        /* Main Content */
        .app-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            max-width: 100%;
            margin: 0 auto;
            width: 100%;
        }
        
        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        .app-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            max-width: 100%;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
            max-width: 1800px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .page-title i {
            color: #3498db;
        }

        .btn {
            padding: 0.7rem 1.4rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(52, 152, 219, 0.3);
        }

        .filter-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            max-width: 1800px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
            border: 2px solid #9b59b6;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.7rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .filter-title {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.7rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0.5rem;
        }
        
        .filter-title i {
            color: #9b59b6;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
            min-width: 180px;
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 500;
            color: #7f8c8d;
            font-size: 0.85rem;
        }
        
        .form-select, .form-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #2c3e50;
            transition: all 0.2s;
            height: 2.2rem;
        }
        
        .form-select:focus, .form-input:focus {
            border-color: #9b59b6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(155, 89, 182, 0.2);
        }
        
        .form-group.filter-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 0.6rem;
            margin-top: 0;
            align-items: flex-end;
        }
        
        .btn-filter {
            background-color: #9b59b6;
            color: white;
            box-shadow: 0 4px 6px rgba(155, 89, 182, 0.2);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-filter:hover {
            background-color: #8e44ad;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(155, 89, 182, 0.3);
        }
        
        .btn-reset {
            background-color: #ecf0f1;
            color: #2c3e50;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .overtime-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            max-width: 1800px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }

        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            transition: all 0.3s ease;
            border-top: 4px solid;
            width: 100%;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .card.pending {
            border-color: #f39c12;
        }

        .card.approved {
            border-color: #2ecc71;
        }

        .card.rejected {
            border-color: #e74c3c;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .card-title i {
            font-size: 1.1rem;
        }

        .card-status {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background-color: #f39c12;
            color: white;
        }

        .status-approved {
            background-color: #2ecc71;
            color: white;
        }

        .status-rejected {
            background-color: #e74c3c;
            color: white;
        }
        
        .status-submitted {
            background-color: #3498db;
            color: white;
        }

        .card-details {
            margin-bottom: 1.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .detail-label {
            color: #7f8c8d;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-value {
            font-weight: 600;
        }

        .card-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            border-radius: 6px;
        }

        .btn-success {
            background-color: #2ecc71;
            color: white;
            box-shadow: 0 2px 4px rgba(46, 204, 113, 0.2);
        }

        .btn-success:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(46, 204, 113, 0.3);
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
            box-shadow: 0 2px 4px rgba(231, 76, 60, 0.2);
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(231, 76, 60, 0.3);
        }

        .btn-neutral {
            background-color: #ecf0f1;
            color: #2c3e50;
        }

        .btn-neutral:hover {
            background-color: #bdc3c7;
            transform: translateY(-2px);
        }

        .stats-container {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            max-width: 1800px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }

        .stat-card {
            flex: 1;
            min-width: 250px;
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.pending {
            background-color: #f39c12;
        }

        .stat-icon.approved {
            background-color: #2ecc71;
        }

        .stat-icon.rejected {
            background-color: #e74c3c;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 0.2rem;
        }

        .stat-info p {
            color: #7f8c8d;
            font-weight: 500;
        }

        .section-title {
            font-size: 1.4rem;
            color: #2c3e50;
            margin: 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            max-width: 1800px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }
        
        .section-title.overview {
            font-size: 1.6rem;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.8rem;
            margin-bottom: 1.2rem;
        }

        .section-title i {
            color: #3498db;
        }
        
        .overview-container {
            border: 2px solid #3498db;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            background-color: #f8f9fa;
            max-width: 1800px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }
        
        .overview-container .section-title.overview {
            margin-top: 0;
            border-bottom-color: #e0e0e0;
        }
        
        /* Overtime Details Table Styles */
        .details-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
            max-width: 1800px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
            border: 2px solid #3498db;
        }
        
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0.8rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .details-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .details-title i {
            color: #3498db;
        }
        
        /* Toggle Switch Styles */
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .toggle-label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 140px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ecf0f1;
            transition: .4s;
            border-radius: 34px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 10px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 65px;
            left: 4px;
            bottom: 4px;
            background-color: #e1f0fa;
            transition: .4s;
            border-radius: 34px;
            z-index: 1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .toggle-text {
            color: #7f8c8d;
            font-weight: 600;
            font-size: 0.8rem;
            z-index: 2;
            transition: .4s;
        }
        
        .toggle-text.left {
            padding-right: 5px;
        }
        
        .toggle-text.right {
            padding-left: 5px;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(67px);
        }
        
        input:checked + .toggle-slider {
            background-color: #3498db;
        }
        
        input:checked + .toggle-slider .toggle-text.left {
            color: white;
        }
        
        input:not(:checked) + .toggle-slider .toggle-text.right {
            color: white;
        }
        
        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }
        
        .overtime-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        
        .overtime-table th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            text-align: left;
            padding: 0.8rem;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .overtime-table td {
            padding: 0.8rem;
            border-bottom: 1px solid #e0e0e0;
            color: #2c3e50;
            vertical-align: middle;
        }
        
        /* Profile picture styles removed as requested */
        
        .overtime-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .overtime-table .status {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .overtime-table .status.pending {
            background-color: #f39c12;
            color: white;
        }
        
        .overtime-table .status.approved {
            background-color: #2ecc71;
            color: white;
        }
        
        .overtime-table .status.rejected {
            background-color: #e74c3c;
            color: white;
        }
        
        .overtime-table .status.submitted {
            background-color: #3498db;
            color: white;
        }
        
        .table-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            transform: translateY(-2px);
        }
        
        .btn-icon.approve {
            background-color: #2ecc71;
        }
        
        .btn-icon.reject {
            background-color: #e74c3c;
        }
        
        .btn-icon.view {
            background-color: #3498db;
        }
        
        .btn-icon.submit {
            background-color: #9b59b6;
        }
        
        .btn-icon.payment {
            background-color: #8e44ad;
        }
        
        .btn-icon.locked {
            background-color: #95a5a6;
            cursor: not-allowed;
            position: relative;
        }
        
        /* Enhanced tooltip for locked buttons */
        .btn-icon.locked:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 10;
            pointer-events: none;
        }
        
        .file-link {
            color: #3498db;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 500;
        }
        
        .file-link:hover {
            color: #2980b9;
        }
        
        /* Work Report styles */
        .work-report-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            padding: 3px 6px;
            border-radius: 4px;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            font-size: 0.85rem;
        }
        
        .work-report-cell:hover, .overtime-message-cell:hover {
            background-color: #e1f0fa;
            border-color: #3498db;
        }
        
        .overtime-message-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            padding: 3px 6px;
            border-radius: 4px;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            font-size: 0.85rem;
        }
        
        .no-report {
            color: #95a5a6;
            font-style: italic;
        }
        
        .manager-response-text {
            background-color: #f0f9ff;
            border-left: 3px solid #3498db;
        }
        
        .work-report-section .reason-header {
            background-color: #e8f4fd;
        }
        
        .submission-date {
            font-size: 0.85rem;
            color: #555;
            white-space: nowrap;
            background-color: #f8f9fa;
            padding: 3px 6px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            display: inline-block;
        }
        
        .work-report-section .reason-header i {
            color: #3498db;
        }
        
        /* Work Report Modal Styles */
        .work-report-modal {
            max-width: 600px;
            width: 90%;
        }
        
        .work-report-header {
            background-color: #f8f9fa;
            margin: -20px -20px 20px -20px;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .work-report-content {
            padding: 0 0 10px 0;
        }
        
        .report-label {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
            font-size: 1.05rem;
        }
        
        .report-text {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .report-text p {
            margin: 0 0 10px 0;
            line-height: 1.5;
        }
        
        .report-text p:last-child {
            margin-bottom: 0;
        }
        
        .no-report-full {
            color: #95a5a6;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
        
        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .page-item {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: white;
            border: 1px solid #e0e0e0;
            color: #2c3e50;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .page-item:hover {
            background-color: #f8f9fa;
            border-color: #3498db;
        }
        
        .page-item.active {
            background-color: #3498db;
            border-color: #3498db;
            color: white;
        }

        /* Responsive styles */
        /* Extra large screens */
        @media (min-width: 1800px) {
            .overtime-grid {
                grid-template-columns: repeat(6, 1fr);
            }
            
            .page-title {
                font-size: 2.2rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .section-title.overview {
                font-size: 2rem;
            }
        }
        
        /* Large screens */
        @media (min-width: 1400px) and (max-width: 1799px) {
            .overtime-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
        
        /* Medium-large screens */
        @media (min-width: 1024px) and (max-width: 1399px) {
            .overtime-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .overtime-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .overtime-table th,
            .overtime-table td {
                padding: 0.6rem;
            }
        }

        @media (max-width: 768px) {
            .app-container {
                padding: 1rem;
            }
            
            .stats-container {
                flex-direction: column;
            }
            
            .stat-card {
                width: 100%;
            }
            
            .overtime-grid {
                grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-title {
                font-size: 1.6rem;
            }
            
            .overtime-table {
                font-size: 0.85rem;
            }
            
            .table-responsive {
                margin: 0 -1rem;
                padding: 0 1rem;
                width: calc(100% + 2rem);
            }
        }

        /* iPhone SE (375px) and smaller devices */
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 0;
            width: 500px;
            max-width: 90%;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s;
            overflow: hidden;
        }
        
        /* Position the payment modal higher */
        #paymentModal .modal-content {
            margin: 5% auto;
        }
        
        /* Position the overtime details modal higher */
        #overtimeDetailsModal .modal-content {
            margin: 5% auto;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #f8f9fa;
        }
        
        /* Overtime Details Modal Styles */
        .details-modal {
            max-width: 700px;
            width: 90%;
        }
        
        .details-header h2 {
            font-size: 1.5rem;
            color: #2c3e50;
        }
        
        .details-body {
            padding: 0;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .details-section {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .employee-section {
            background-color: #f8f9fa;
        }
        
        .employee-profile {
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .details-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .employee-info {
            margin-left: 20px;
            flex: 1;
        }
        
        .employee-name {
            font-size: 1.4rem;
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .employee-meta {
            display: flex;
            gap: 15px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .employee-department, .employee-position {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-badge {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .details-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            text-transform: capitalize;
        }
        
        .details-status.pending {
            background-color: #f39c12;
        }
        
        .details-status.submitted {
            background-color: #3498db;
        }
        
        .details-status.approved {
            background-color: #2ecc71;
        }
        
        .details-status.rejected {
            background-color: #e74c3c;
        }
        
        .status.paid {
            background-color: #2ecc71;
        }
        
        .status.unpaid {
            background-color: #f39c12;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .section-title i {
            color: #3498db;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .detail-item {
            display: flex;
            align-items: flex-start;
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.2s ease;
        }
        
        .detail-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .detail-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e1f0fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #3498db;
            font-size: 1.1rem;
        }
        
        .highlight-item {
            border-left: 4px solid #3498db;
            background-color: #e1f0fa;
        }
        
        .highlight-item .detail-icon {
            background-color: #3498db;
            color: white;
        }
        
        .detail-content {
            flex: 1;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .reason-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .reason-header, .note-header {
            background-color: #e1f0fa;
            padding: 12px 15px;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reason-header i, .note-header i {
            color: #3498db;
        }
        
        .reason-content, .note-content {
            padding: 15px;
            color: #2c3e50;
        }
        
        .calculation-note {
            background-color: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #f39c12;
        }
        
        .calculation-note .note-header {
            background-color: #fef5e7;
        }
        
        .calculation-note .note-header i {
            color: #f39c12;
        }
        
        .note-content p {
            margin: 5px 0;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .details-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-info {
            background-color: #f8f9fa;
            margin: -20px -20px 20px -20px;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            width: 110px;
            font-weight: 600;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-label i {
            color: #3498db;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .info-value {
            flex: 1;
            font-weight: 500;
        }
        
        .info-value.highlight {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.05rem;
            background-color: rgba(52, 152, 219, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .form-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
            transition: border-color 0.2s;
        }
        
        .form-textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        /* Responsive styles for details modal */
        @media (max-width: 768px) {
            .details-modal {
                width: 95%;
                margin: 5% auto;
            }
            
            .employee-profile {
                flex-direction: column;
                text-align: center;
                padding-bottom: 50px;
            }
            
            .employee-info {
                margin-left: 0;
                margin-top: 15px;
            }
            
            .employee-meta {
                justify-content: center;
            }
            
            .status-badge {
                position: absolute;
                right: auto;
                top: auto;
                bottom: 10px;
                left: 50%;
                transform: translateX(-50%);
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 375px) {
            .app-container {
                padding: 0.8rem;
            }
            
            .page-title {
                font-size: 1.4rem;
            }
            
            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            
            .card {
                padding: 1rem;
            }
            
            .card-title {
                font-size: 1rem;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.3rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            
            .stat-info h3 {
                font-size: 1.5rem;
            }
            
            .stat-info p {
                font-size: 0.9rem;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
            
            .card-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-sm {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
            
            .overtime-table th,
            .overtime-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            
            .btn-icon {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }
        }

        .btn-export {
            background-color: #27ae60;
            color: white;
            box-shadow: 0 4px 6px rgba(39, 174, 96, 0.2);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-export:hover {
            background-color: #219653;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(39, 174, 96, 0.3);
        }
        
        /* Screen Loader Styles */
        .screen-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .screen-loader.active {
            opacity: 1;
            visibility: visible;
        }
        
        .loader-spinner {
            width: 70px;
            height: 70px;
            border: 6px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 6px solid #3498db;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loader-text {
            color: white;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
        }
        
        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }
        
        .toast {
            background-color: white;
            color: #333;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            animation: slideIn 0.3s forwards, fadeOut 0.3s 4.7s forwards;
            min-width: 300px;
        }
        
        @keyframes slideIn {
            to { transform: translateX(0); }
        }
        
        @keyframes fadeOut {
            to { opacity: 0; transform: translateX(120%); }
        }
        
        .toast-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            margin-bottom: 3px;
            font-size: 16px;
        }
        
        .toast-message {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .toast.success {
            border-left: 4px solid #2ecc71;
        }
        
        .toast.success .toast-icon {
            color: #2ecc71;
        }
        
        .toast.error {
            border-left: 4px solid #e74c3c;
        }
        
        .toast.error .toast-icon {
            color: #e74c3c;
        }
        
        .toast.info {
            border-left: 4px solid #3498db;
        }
        
        .toast.info .toast-icon {
            color: #3498db;
        }
        
        .toast.warning {
            border-left: 4px solid #f39c12;
        }
        
        .toast.warning .toast-icon {
            color: #f39c12;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- No external libraries needed for HTML-based Excel export -->
</head>
<body>
    <!-- Screen Loader -->
    <div class="screen-loader" id="screenLoader">
        <div class="loader-spinner"></div>
        <div class="loader-text">Processing your request...</div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="dashboard">
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
            <a href="payouts.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Manager Payouts
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="construction_site_overview.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="hr_overtime_approval.php" class="nav-link active">
                <i class="bi bi-clock"></i>
                Overtime Approval
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
        
        <div class="main-content" id="mainContent">
            <div class="app-container">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-clock"></i> Overtime Approval
                    </h1>
                    <button class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Request
                    </button>
                </div>

                <div class="filter-container">
                    <div class="filter-header">
                        <h2 class="filter-title">
                            <i class="fas fa-filter"></i> Filter Options
                        </h2>
                    </div>
                    <div class="filter-form">
                        <div class="form-group">
                            <label class="form-label">User</label>
                            <select class="form-select" id="filter-user">
                                <option value="">All Users</option>
                                <!-- Will be populated dynamically -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="filter-status">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="submitted">Submitted</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Month</label>
                            <select class="form-select" id="filter-month">
                                <option value="">All Months</option>
                                <option value="January">January</option>
                                <option value="February">February</option>
                                <option value="March">March</option>
                                <option value="April">April</option>
                                <option value="May">May</option>
                                <option value="June">June</option>
                                <option value="July">July</option>
                                <option value="August">August</option>
                                <option value="September">September</option>
                                <option value="October">October</option>
                                <option value="November">November</option>
                                <option value="December">December</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Year</label>
                            <select class="form-select" id="filter-year">
                                <option value="">All Years</option>
                                <!-- Will be populated dynamically -->
                            </select>
                        </div>
                        <div class="form-group filter-buttons">
                            <button class="btn btn-filter" id="apply-filters">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <button class="btn btn-reset" id="reset-filters">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <button class="btn btn-export" id="export-excel" style="background-color: #27ae60; color: white;">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                        </div>
                    </div>
                    <div id="filter-message" class="filter-message" style="display: none; margin-top: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 6px; text-align: center; color: #777;">
                        <span id="filter-message-icon"><i class="fas fa-info-circle"></i></span> <span id="filter-message-text"></span>
                    </div>
                </div>

                <div class="overview-container">
                    <h2 class="section-title overview">
                        <i class="fas fa-tachometer-alt"></i> Quick Overview of Overtime <span style="font-size: 0.9rem; color: #777; font-weight: normal;">(1.5+ hours)</span>
                    </h2>
                <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon" style="background-color: #3498db;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3>39</h3>
                                <p>Total Overtime Hours</p>
                            </div>
                        </div>
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-info">
                            <h3>12</h3>
                                <p>Pending Overtime</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3>24</h3>
                                <p>Approved Overtime</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="details-container">
                <div class="details-header">
                    <h2 class="details-title">
                        <i class="fas fa-list-alt"></i> Overtime Details <span style="font-size: 0.8rem; color: #777; font-weight: normal;">(minimum 1.5 hours)</span>
            </h2>
                    <div class="toggle-container">
                        <span class="toggle-label">Studio</span>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="toggle-slider">
                                <span class="toggle-text left">Studio</span>
                                <span class="toggle-text right">Site</span>
                            </span>
                        </label>
                        <span class="toggle-label">Site</span>
                        </div>
                        </div>
                <div class="table-responsive">
                    <table class="overtime-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Date</th>
                                <th>Shift End Time</th>
                                <th>Punch Out Time</th>
                                <th>Overtime Hours</th>
                                <th>Submitted On</th>
                                <th>Work Report</th>
                                <th>Overtime Report</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Table content will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-check-circle" style="color: #2ecc71;"></i> Approve Overtime</h2>
                <span class="close" onclick="closeModal('approveModal')">&times;</span>
                            </div>
            <div class="modal-body">
                <div class="modal-info">
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Employee:</span>
                        <span class="info-value" id="approveUserName"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date:</span>
                        <span class="info-value" id="approveDate"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-clock"></i> Overtime:</span>
                        <span class="info-value highlight" id="approveHours"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="approveReason">Reason for Approval (optional):</label>
                    <textarea id="approveReason" class="form-textarea" rows="3" placeholder="Enter reason for approval..."></textarea>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <div style="margin-bottom: 10px; font-weight: 600; color: #e74c3c;">Mandatory Confirmations:</div>
                    <div style="margin-bottom: 10px;">
                        <input type="checkbox" id="approveCheckbox1" style="margin-right: 10px;">
                        <label for="approveCheckbox1">Have you given the Conceqt to your subordinated for overtime ?</label>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <input type="checkbox" id="approveCheckbox2" style="margin-right: 10px;">
                        <label for="approveCheckbox2">Is this time eligible for OT as per company policy ?</label>
                    </div>
                    <div id="approveCheckboxError" class="error-message" style="display: none; color: #e74c3c; margin-top: 5px;">
                        Please confirm both statements before approving.
                    </div>
                </div>
                <input type="hidden" id="approveUserId" value="">
                <input type="hidden" id="approveRowId" value="">
            </div>
            <div class="modal-footer">
                <button class="btn btn-neutral" onclick="closeModal('approveModal')"><i class="fas fa-times"></i> Cancel</button>
                <button class="btn btn-success" onclick="confirmApprove()"><i class="fas fa-check"></i> Approve</button>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-times-circle" style="color: #e74c3c;"></i> Reject Overtime</h2>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
                            </div>
            <div class="modal-body">
                <div class="modal-info">
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Employee:</span>
                        <span class="info-value" id="rejectUserName"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date:</span>
                        <span class="info-value" id="rejectDate"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-clock"></i> Overtime:</span>
                        <span class="info-value highlight" id="rejectHours"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="rejectReason">Reason for Rejection (required):</label>
                    <textarea id="rejectReason" class="form-textarea" rows="3" placeholder="Enter reason for rejection..." required></textarea>
                    <div id="rejectReasonError" class="error-message" style="display: none; color: #e74c3c; margin-top: 5px;">
                        Please provide a reason for rejection.
                    </div>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <div style="margin-bottom: 10px; font-weight: 600; color: #e74c3c;">Mandatory Confirmations:</div>
                    <div style="margin-bottom: 10px;">
                        <input type="checkbox" id="rejectCheckbox1" style="margin-right: 10px;">
                        <label for="rejectCheckbox1">Have you given the Conceqt to your subordinated for overtime ?</label>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <input type="checkbox" id="rejectCheckbox2" style="margin-right: 10px;">
                        <label for="rejectCheckbox2">Is this time eligible for OT as per company policy ?</label>
                    </div>
                    <div id="rejectCheckboxError" class="error-message" style="display: none; color: #e74c3c; margin-top: 5px;">
                        Please confirm both statements before rejecting.
                    </div>
                </div>
                <input type="hidden" id="rejectUserId" value="">
                <input type="hidden" id="rejectRowId" value="">
            </div>
            <div class="modal-footer">
                <button class="btn btn-neutral" onclick="closeModal('rejectModal')"><i class="fas fa-times"></i> Cancel</button>
                <button class="btn btn-danger" onclick="confirmReject()"><i class="fas fa-ban"></i> Reject</button>
            </div>
        </div>
    </div>
    
    <!-- Overtime Message Modal -->
    <div id="overtimeMessageModal" class="modal">
        <div class="modal-content work-report-modal">
            <div class="modal-header">
                <h2><i class="fas fa-comment-dots" style="color: #3498db;"></i> Overtime Report</h2>
                <span class="close" onclick="closeModal('overtimeMessageModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="work-report-header">
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Employee:</span>
                        <span class="info-value" id="overtimeMessageUserName"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date:</span>
                        <span class="info-value" id="overtimeMessageDate"></span>
                    </div>
                </div>
                <div class="work-report-content">
                    <div class="report-label">System Message:</div>
                    <div id="overtimeMessageContent" class="report-text"></div>
                    
                    <div id="managerResponseSection" style="margin-top: 20px; display: none;">
                        <div class="report-label">Manager Response:</div>
                        <div id="managerResponseContent" class="report-text manager-response-text"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeModal('overtimeMessageModal')">
                    <i class="fas fa-check"></i> Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Work Report Modal -->
    <div id="workReportModal" class="modal">
        <div class="modal-content work-report-modal">
            <div class="modal-header">
                <h2><i class="fas fa-file-alt" style="color: #3498db;"></i> Work Report</h2>
                <span class="close" onclick="closeModal('workReportModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="work-report-header">
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Employee:</span>
                        <span class="info-value" id="workReportUserName"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date:</span>
                        <span class="info-value" id="workReportDate"></span>
                    </div>
                </div>
                <div class="work-report-content">
                    <div class="report-label">Report Content:</div>
                    <div id="workReportContent" class="report-text"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeModal('workReportModal')">
                    <i class="fas fa-check"></i> Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-rupee-sign" style="color: #8e44ad;"></i> Overtime Payment</h2>
                <span class="close" onclick="closeModal('paymentModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-info" style="background-color: #f0e6f6;">
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Employee:</span>
                        <span class="info-value" id="paymentUserName"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date:</span>
                        <span class="info-value" id="paymentDate"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-clock"></i> Overtime:</span>
                        <span class="info-value" id="paymentHours"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-rupee-sign"></i> Amount:</span>
                        <span class="info-value highlight" id="paymentAmount">₹1,500.00</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-tag"></i> Status:</span>
                        <span class="info-value">
                            <span id="paymentStatus" class="status unpaid" style="background-color: #f39c12; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8rem;">Unpaid</span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-hashtag"></i> Overtime ID:</span>
                        <span class="info-value" id="paymentOvertimeIdDisplay">-</span>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <div style="font-weight: 600; margin-bottom: 15px; color: #2c3e50; font-size: 1.1rem;">
                        <i class="fas fa-clipboard-check" style="color: #8e44ad; margin-right: 8px;"></i> Payment Confirmation
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <div style="margin-bottom: 10px; font-weight: 600; color: #8e44ad;">Required Confirmations:</div>
                        <div style="margin-bottom: 10px; background-color: #f8f9fa; padding: 10px; border-radius: 6px;">
                            <input type="checkbox" id="paymentCheckbox1" style="margin-right: 10px;">
                            <label for="paymentCheckbox1">I confirm this overtime payment has been approved by management</label>
                        </div>
                        <div style="margin-bottom: 10px; background-color: #f8f9fa; padding: 10px; border-radius: 6px;">
                            <input type="checkbox" id="paymentCheckbox2" style="margin-right: 10px;">
                            <label for="paymentCheckbox2">I confirm the amount is calculated according to company policy</label>
                        </div>
                        <div style="margin-bottom: 10px; background-color: #f8f9fa; padding: 10px; border-radius: 6px;">
                            <input type="checkbox" id="paymentCheckbox3" style="margin-right: 10px;">
                            <label for="paymentCheckbox3">I confirm this payment will be included in the next payroll cycle</label>
                        </div>
                        <div id="paymentCheckboxError" class="error-message" style="display: none; color: #e74c3c; margin-top: 5px;">
                            Please confirm all statements before proceeding.
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label for="paymentNotes">Additional Notes (optional):</label>
                        <textarea id="paymentNotes" class="form-textarea" rows="3" placeholder="Enter any additional notes regarding this payment..."></textarea>
                    </div>
                </div>
                
                <input type="hidden" id="paymentUserId" value="">
                <input type="hidden" id="paymentRowId" value="">
                <input type="hidden" id="paymentOvertimeId" value="">
            </div>
            <div class="modal-footer">
                <button class="btn btn-neutral" onclick="closeModal('paymentModal')"><i class="fas fa-times"></i> Cancel</button>
                <button class="btn btn-success" style="background-color: #8e44ad;" onclick="confirmPayment(); console.log('Process Payment button clicked');" id="processPaymentBtn"><i class="fas fa-check"></i> Process Payment</button>
            </div>
        </div>
    </div>
    
    <!-- Overtime Details Modal -->
    <div id="overtimeDetailsModal" class="modal">
        <div class="modal-content details-modal">
            <div class="modal-header details-header">
                <h2><i class="fas fa-info-circle" style="color: #3498db;"></i> Overtime Details</h2>
                <span class="close" onclick="closeModal('overtimeDetailsModal')">&times;</span>
            </div>
            <div class="modal-body details-body">
                <!-- Employee Info Section -->
                <div class="details-section employee-section">
                    <div class="employee-profile">
                        <img id="detailsUserAvatar" src="assets/default-avatar.png" alt="Employee" class="details-avatar">
                        <div class="employee-info">
                            <h3 id="detailsUserName" class="employee-name">Employee Name</h3>
                            <div class="employee-meta">
                                <span class="employee-department">
                                    <i class="fas fa-building"></i> 
                                    <span id="detailsUserDepartment">Department</span>
                                </span>
                                <span class="employee-position">
                                    <i class="fas fa-id-badge"></i> 
                                    <span id="detailsUserPosition">Position</span>
                                </span>
                            </div>
                        </div>
                        <div class="status-badge">
                            <span id="detailsStatus" class="details-status pending">Pending</span>
                        </div>
                    </div>
                </div>
                
                <!-- Overtime Details Section -->
                <div class="details-section overtime-details-section">
                    <h3 class="section-title"><i class="fas fa-clock"></i> Overtime Information</h3>
                    
                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-calendar-day"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Date</div>
                                <div id="detailsDate" class="detail-value">March 15, 2025</div>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-user-clock"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Shift</div>
                                <div id="detailsShift" class="detail-value">Regular (9AM-6PM)</div>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-sign-out-alt"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Shift End Time</div>
                                <div id="detailsShiftEnd" class="detail-value">6:00 PM</div>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-door-open"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Punch Out Time</div>
                                <div id="detailsPunchOut" class="detail-value">8:30 PM</div>
                            </div>
                        </div>
                        
                        <div class="detail-item highlight-item">
                            <div class="detail-icon"><i class="fas fa-hourglass-half"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Overtime Hours</div>
                                <div id="detailsOvertimeHours" class="detail-value">2.5 hours</div>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-user-check"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Approved/Rejected By</div>
                                <div id="detailsManagerInfo" class="detail-value">N/A</div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="detailsWorkReportSection" class="reason-section work-report-section" style="display: none;">
                        <div class="reason-header">
                            <i class="fas fa-file-alt"></i> Work Report
                        </div>
                        <div id="detailsWorkReport" class="reason-content">
                            Work report will appear here.
                        </div>
                    </div>
                    
                    <div id="detailsOvertimeMessageSection" class="reason-section overtime-message-section" style="display: none;">
                        <div class="reason-header">
                            <i class="fas fa-comment-dots"></i> Overtime Report
                        </div>
                        <div id="detailsOvertimeMessage" class="reason-content">
                            Overtime message will appear here.
                        </div>
                        <div id="detailsManagerResponseDiv" style="margin-top: 10px; display: none;">
                            <div class="reason-header" style="background-color: #e8f4fd;">
                                <i class="fas fa-reply"></i> Manager Response
                            </div>
                            <div id="detailsManagerResponse" class="reason-content manager-response-text">
                                Manager response will appear here.
                            </div>
                        </div>
                    </div>
                    
                    <div id="detailsReasonSection" class="reason-section" style="display: none;">
                        <div class="reason-header">
                            <i class="fas fa-comment-alt"></i> Reason
                        </div>
                        <div id="detailsReason" class="reason-content">
                            Reason text will appear here.
                        </div>
                    </div>
                    
                    <div class="calculation-note">
                        <div class="note-header">
                            <i class="fas fa-calculator"></i> Calculation Method
                        </div>
                        <div class="note-content">
                            <p>Overtime is calculated based on time worked after shift end time.</p>
                            <p>Hours are rounded down to nearest half-hour (e.g., 2.7 hours becomes 2.5 hours).</p>
                            <p>Minimum qualifying overtime is 1.5 hours.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer details-footer">
                <button class="btn btn-primary" onclick="closeModal('overtimeDetailsModal')">
                    <i class="fas fa-check"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script>
        /**
         * Show screen loader with optional custom message
         */
        function showScreenLoader(message) {
            const loader = document.getElementById('screenLoader');
            const loaderText = loader.querySelector('.loader-text');
            
            if (message) {
                loaderText.textContent = message;
            } else {
                loaderText.textContent = 'Processing your request...';
            }
            
            loader.classList.add('active');
        }
        
        /**
         * Hide screen loader
         */
        function hideScreenLoader() {
            const loader = document.getElementById('screenLoader');
            loader.classList.remove('active');
        }
        
        /**
         * Show toast notification
         * @param {string} message - The message to display
         * @param {string} type - The type of toast: 'success', 'error', 'info', 'warning'
         * @param {string} title - Optional title for the toast
         */
        function showToast(message, type = 'info', title = '') {
            const toastContainer = document.getElementById('toastContainer');
            
            // Set default titles based on type if not provided
            if (!title) {
                switch(type) {
                    case 'success': title = 'Success'; break;
                    case 'error': title = 'Error'; break;
                    case 'warning': title = 'Warning'; break;
                    default: title = 'Information'; break;
                }
            }
            
            // Set icon based on type
            let icon = '';
            switch(type) {
                case 'success': icon = '<i class="fas fa-check-circle"></i>'; break;
                case 'error': icon = '<i class="fas fa-exclamation-circle"></i>'; break;
                case 'warning': icon = '<i class="fas fa-exclamation-triangle"></i>'; break;
                default: icon = '<i class="fas fa-info-circle"></i>'; break;
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">${icon}</div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `;
            
            // Add to container
            toastContainer.appendChild(toast);
            
            // Remove toast after animation completes
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggle-btn');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                });
            }
            
            // DOM elements
            const filterUserSelect = document.getElementById('filter-user');
            const filterStatusSelect = document.getElementById('filter-status');
            const filterMonthSelect = document.getElementById('filter-month');
            const filterYearSelect = document.getElementById('filter-year');
            const applyFiltersBtn = document.getElementById('apply-filters');
            const resetFiltersBtn = document.getElementById('reset-filters');
            const exportExcelBtn = document.getElementById('export-excel');
            const locationToggle = document.querySelector('.toggle-switch input');
            const filterMessageContainer = document.getElementById('filter-message');
            const filterMessageText = document.getElementById('filter-message-text');
            
            // Get user data from PHP
            const studioUsers = <?php echo json_encode($studioUsers); ?>;
            const siteUsers = <?php echo json_encode($siteUsers); ?>;
            
            // Get attendance data from PHP
            const studioAttendance = <?php echo json_encode($studioAttendance); ?>;
            const siteAttendance = <?php echo json_encode($siteAttendance); ?>;
            
            // Format data for display
            const studioData = formatAttendanceData(studioAttendance);
            const siteData = formatAttendanceData(siteAttendance);
            
            // Initialize the view
            initializeView();
            
            // Set up event listeners
            setupEventListeners();
            
            /**
             * Format overtime hours to display as whole or half hours
             */
            function formatOvertimeHours(hours) {
                if (!hours && hours !== 0) return 'N/A';
                
                // Handle zero overtime case
                if (hours === 0) return '0 hours';
                
                // Round DOWN to nearest 0.5 (consistent with PHP logic)
                // This ensures 2.9 hours becomes 2.5, not 3.0
                const roundedHours = Math.floor(hours * 2) / 2;
                
                // Format without decimal if it's a whole number
                if (Number.isInteger(roundedHours)) {
                    return `${roundedHours} hours`;
            } else {
                    return `${roundedHours.toFixed(1)} hours`;
                }
            }
            
            /**
             * Format attendance data for display
             */
            function formatAttendanceData(attendanceRecords) {
                return attendanceRecords.map(record => {
                    // Debug log for raw record
                    console.log('Raw attendance record:', record);
                
                    // Format date
                    const date = new Date(record.date);
                    const formattedDate = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    
                    // Format times using actual shift end time from database
                    const shiftEndTime = record.shift_end ? formatTime(record.shift_end) : 'N/A';
                    const punchOutTime = record.punch_out ? formatTime(record.punch_out) : 'N/A';
                    
                    // Ensure status is properly set
                    let status = 'pending';
                    if (record.overtime_status) {
                        status = record.overtime_status.toLowerCase();
                    }
                    
                    return {
                        id: record.user_id,
                        username: record.username,
                        position: record.role || 'Staff',
                        department: record.department || 'General',
                        date: formattedDate,
                        rawDate: record.date, // Keep the raw date for filtering
                        shift_name: record.shift_name || 'Default',
                        shift_start: record.shift_start || '09:00:00',
                        shift_end: record.shift_end || '18:00:00',
                        shiftEnd: shiftEndTime,
                        punchOut: punchOutTime,
                        hours: formatOvertimeHours(record.calculated_overtime || record.overtime_hours),
                        rawHours: record.calculated_overtime || record.overtime_hours || 0, // Raw hours for calculations
                        profile_picture: record.profile_picture,
                        status: status,
                        manager: record.manager || record.manager_username || 'N/A',
                        actioned_at: record.overtime_actioned_at,
                        work_report: record.work_report || 'No report submitted',
                        overtime_message: record.overtime_message || 'No message available',
                        manager_response: record.manager_response || '',
                        submitted_on: record.submitted_on || '',
                        overtime_id: record.overtime_id || record.id || record.user_id // Use overtime_id if available, otherwise fall back to other IDs
                    };
                });
            }
            
            /**
             * Format time strings
             */
            function formatTime(timeString, hoursToAdd = 0) {
                if (!timeString) return 'N/A';
                
                try {
                    // Parse the time string
                    const timeParts = timeString.split(':');
                    let hours = parseInt(timeParts[0], 10);
                    const minutes = parseInt(timeParts[1], 10);
                    
                    // Add hours if needed (for shift end time calculation)
                    hours += hoursToAdd;
                    
                    // Format as 12-hour time
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12; // Convert 0 to 12
                    
                    return `${hours}:${minutes < 10 ? '0' + minutes : minutes} ${ampm}`;
                } catch (e) {
                    console.error('Error formatting time:', e);
                    return 'N/A';
                }
            }
            
            /**
             * Initialize the view with current data
             */
            function initializeView() {
                // Set up the initial data view (studio by default)
                const currentData = getCurrentDataset();
                
                // Populate filter dropdowns
                populateFilterDropdowns(currentData);
                
                // Set default filter values to current month/year
                setDefaultFilterValues();
                
                // Load initial data
                applyFilters();
            }
            
            /**
             * Set up all event listeners
             */
            function setupEventListeners() {
                // Toggle between studio and site data
                if (locationToggle) {
                    locationToggle.addEventListener('change', function() {
                        // Update filters for the new dataset
                        const currentData = getCurrentDataset();
                        populateFilterDropdowns(currentData);
                        
                        // Apply filters with the new dataset
                        applyFilters();
                    });
                }
                
                // Apply filters button
                if (applyFiltersBtn) {
                    applyFiltersBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        applyFilters();
                    });
                }
                
                // Reset filters button
                if (resetFiltersBtn) {
                    resetFiltersBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        resetFilters();
                    });
                }
                
                // Add change event listeners to filter selects for real-time feedback
                [filterUserSelect, filterStatusSelect, filterMonthSelect, filterYearSelect].forEach(select => {
                    if (select) {
                        select.addEventListener('change', function() {
                            // Provide visual feedback that the filter has changed
                            this.style.borderColor = '#9b59b6';
                            setTimeout(() => {
                                this.style.borderColor = '';
                            }, 500);
                        });
                    }
                });
            }
            
            /**
             * Get the current dataset based on toggle state
             */
            function getCurrentDataset() {
                return locationToggle && locationToggle.checked ? siteData : studioData;
            }
            
            /**
             * Get all users based on toggle state
             */
            function getCurrentUsers() {
                return locationToggle && locationToggle.checked ? siteUsers : studioUsers;
            }
            
            /**
             * Populate filter dropdowns based on the current dataset
             */
            function populateFilterDropdowns(data) {
                // Populate user dropdown
                populateUserDropdown();
                
                // Populate year dropdown with available years from data
                populateYearDropdown(data);
            }
            
            /**
             * Populate the user filter dropdown
             */
            function populateUserDropdown() {
                if (!filterUserSelect) return;
                
                const currentUsers = getCurrentUsers();
                
                // Clear existing options
            filterUserSelect.innerHTML = '<option value="">All Users</option>';
            
                // Sort users alphabetically
                const sortedUsers = [...currentUsers].sort((a, b) => 
                    a.username.localeCompare(b.username)
                );
                
                // Add user options
                sortedUsers.forEach(user => {
                const option = document.createElement('option');
                option.value = user.username;
                option.textContent = user.username;
                filterUserSelect.appendChild(option);
            });
            }
            
            /**
             * Populate the year dropdown with available years
             */
            function populateYearDropdown(data) {
                if (!filterYearSelect) return;
                
                // Extract unique years from data
                const years = new Set();
                
                data.forEach(item => {
                    if (item.rawDate) {
                        try {
                            const date = new Date(item.rawDate);
                            if (!isNaN(date.getTime())) {
                                years.add(date.getFullYear().toString());
                            }
                        } catch (e) {
                            console.error('Error extracting year:', e);
                        }
                    }
                });
                
                // Get current year to set as default
                const currentYear = new Date().getFullYear().toString();
                
                // Always include current year even if no data
                years.add(currentYear);
                
                // Clear existing options
                filterYearSelect.innerHTML = '<option value="">All Years</option>';
                
                // Add year options (most recent first)
                [...years].sort((a, b) => b - a).forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    filterYearSelect.appendChild(option);
                });
            }
            
            /**
             * Set default filter values to current month/year
             */
            function setDefaultFilterValues() {
                // Get current date
                const now = new Date();
                const currentMonth = now.toLocaleString('en-US', { month: 'long' });
                const currentYear = now.getFullYear().toString();
                
                // Set default month
                if (filterMonthSelect) {
                    const monthOption = filterMonthSelect.querySelector(`option[value="${currentMonth}"]`);
                    if (monthOption) {
                        monthOption.selected = true;
                    }
                }
                
                // Set default year
                if (filterYearSelect) {
                    const yearOption = filterYearSelect.querySelector(`option[value="${currentYear}"]`);
                    if (yearOption) {
                        yearOption.selected = true;
                    }
                }
            }
            
            /**
             * Reset all filters to default values
             */
            function resetFilters() {
                // Reset dropdowns
                if (filterUserSelect) filterUserSelect.value = '';
                if (filterStatusSelect) filterStatusSelect.value = '';
                
                // Reset to current month/year
                setDefaultFilterValues();
                
                // Apply the reset filters
                applyFilters();
                
                // Show reset confirmation
                showFilterMessage('Filters have been reset to defaults');
                setTimeout(() => {
                    hideFilterMessage();
                }, 3000);
            }
            
            /**
             * Apply filters to the data and update the UI
             */
            function applyFilters() {
                // Get filter values
                const userFilter = filterUserSelect ? filterUserSelect.value : '';
                const statusFilter = filterStatusSelect ? filterStatusSelect.value.toLowerCase() : '';
                const monthFilter = filterMonthSelect ? filterMonthSelect.value : '';
                const yearFilter = filterYearSelect ? filterYearSelect.value : '';
                
                // Log filter values for debugging
                console.log('Applying filters:', { userFilter, statusFilter, monthFilter, yearFilter });
                console.log('Current dataset before filtering:', getCurrentDataset().slice(0, 3));
                
                // Get current dataset
                const currentData = getCurrentDataset();
                
                // Apply filters
                const filteredData = currentData.filter(item => {
                    // Ensure minimum overtime requirement (1.5 hours)
                    const overtimeHours = parseFloat(item.rawHours) || 0;
                    if (overtimeHours < 1.5) {
                        return false;
                    }
                    
                    // User filter
                    if (userFilter && item.username !== userFilter) {
                        return false;
                    }
                    
                    // Status filter
                    if (statusFilter && item.status.toLowerCase() !== statusFilter) {
                        return false;
                    }
                    
                    // Date filters
                    if (monthFilter || yearFilter) {
                    try {
                            // Parse the date (use rawDate if available)
                            const dateStr = item.rawDate || item.date;
                            const date = new Date(dateStr);
                            
                        if (isNaN(date.getTime())) {
                                console.error('Invalid date during filtering:', dateStr);
                                return false;
                        }
                        
                            // Month filter
                        if (monthFilter) {
                            const month = date.toLocaleString('en-US', { month: 'long' });
                            if (month !== monthFilter) {
                                return false;
                            }
                        }
                        
                            // Year filter
                        if (yearFilter) {
                            const year = date.getFullYear().toString();
                            if (year !== yearFilter) {
                                return false;
                            }
                        }
                    } catch (e) {
                            console.error('Error filtering by date:', e);
                        return false;
                        }
                    }
                    
                    return true;
                });
                
                // Update UI with filtered data
                updateUI(filteredData, { userFilter, statusFilter, monthFilter, yearFilter });
            }
            
            /**
             * Update the UI with the filtered data
             */
            function updateUI(filteredData, filters) {
                const tableBody = document.querySelector('.overtime-table tbody');
                if (!tableBody) return;
                
                // Clear the table
                tableBody.innerHTML = '';
                
                // Check if we have data
                if (filteredData.length === 0) {
                    // Show no data message
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">
                                <div style="font-size: 16px; color: #777;">
                                    <i class="fas fa-info-circle" style="margin-right: 10px;"></i>
                                    No qualifying overtime data found (minimum 1.5 hours required).
                                </div>
                            </td>
                        </tr>
                    `;
                    
                    // Show filter message
                    const { userFilter, statusFilter, monthFilter, yearFilter } = filters;
                    let filterDesc = [];
                    if (userFilter) filterDesc.push(`User: ${userFilter}`);
                    if (statusFilter) filterDesc.push(`Status: ${statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1)}`);
                    if (monthFilter) filterDesc.push(`Month: ${monthFilter}`);
                    if (yearFilter) filterDesc.push(`Year: ${yearFilter}`);
                    
                    if (filterDesc.length > 0) {
                        showFilterMessage(`No data found for ${filterDesc.join(', ')}`);
                    }
                    
                    // Update stats to zero
                    updateStats([]);
                    return;
                }
                
                // Hide any previous filter messages
                hideFilterMessage();
                
                // Populate table with filtered data
                filteredData.forEach(item => {
                    const row = document.createElement('tr');
                    
                    // Determine status display
                    let statusClass = 'pending';
                    let statusText = 'Pending';
                    
                    if (item.status) {
                        // Log the status for debugging
                        console.log(`Status for user ${item.id}: "${item.status}"`);
                        
                        const status = item.status.toLowerCase();
                        if (status === 'approved') {
                            statusClass = 'approved';
                            statusText = 'Approved';
                        } else if (status === 'rejected') {
                            statusClass = 'rejected';
                            statusText = 'Rejected';
                        } else if (status === 'submitted') {
                            statusClass = 'submitted';
                            statusText = 'Submitted';
                        }
                    }
                    
                    // Format approval info
                    let approvalInfo = 'N/A';
                    
                    // Only show manager info for approved or rejected statuses
                    if ((item.status === 'approved' || item.status === 'rejected') && item.manager) {
                        approvalInfo = item.manager;
                        if (item.actioned_at) {
                            const actionDate = new Date(item.actioned_at);
                            if (!isNaN(actionDate.getTime())) {
                                approvalInfo += ` on ${actionDate.toLocaleDateString()}`;
                            }
                        }
                    }
                    
                    // Check payment status
                    let paymentStatus = 'Unpaid';
                    let paymentStatusClass = 'unpaid';
                    
                    // Check payment status for this overtime entry if approved
                    if (item.status && item.status.toLowerCase() === 'approved') {
                        // Make AJAX call to check payment status
                        $.ajax({
                            url: 'api/process_overtime_payment.php',
                            method: 'GET',
                            data: {
                                check_status: true,
                                overtime_id: item.overtime_id || item.id,
                                employee_id: item.id
                            },
                            async: false, // Synchronous call to ensure we have the status before rendering
                            success: function(response) {
                                console.log('Payment status check response:', response);
                                if (response.success && response.data && response.data.status === 'paid') {
                                    paymentStatus = 'Paid';
                                    paymentStatusClass = 'paid';
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Error checking payment status:', error);
                            }
                        });
                    }
                    
                    // Format submission date if available
                    let submittedDate = 'N/A';
                    if (item.submitted_on) {
                        try {
                            const submitDate = new Date(item.submitted_on);
                            if (!isNaN(submitDate.getTime())) {
                                submittedDate = submitDate.toLocaleDateString() + ' ' + submitDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                            }
                        } catch (e) {
                            console.error('Error formatting submission date:', e);
                        }
                    }
                    
                    // Create row content
                    row.innerHTML = `
                        <td>${item.username}</td>
                        <td>${item.date}</td>
                        <td>${item.shiftEnd}</td>
                        <td>${item.punchOut}</td>
                        <td>${item.hours}</td>
                        <td><span class="submission-date">${submittedDate}</span></td>
                        <td>
                            <div class="work-report-cell" title="Click to view full report" onclick="viewWorkReport('${item.username}', '${item.date}', ${JSON.stringify(item.work_report).replace(/"/g, '&quot;')})">
                                ${item.work_report && item.work_report !== 'No report submitted' 
                                    ? item.work_report.length > 30 
                                        ? item.work_report.substring(0, 30) + '...' 
                                        : item.work_report
                                    : '<span class="no-report">No report</span>'}
                            </div>
                        </td>
                        <td>
                            <div class="overtime-message-cell" title="Click to view details" onclick="viewOvertimeMessage('${item.username}', '${item.date}', ${JSON.stringify(item.overtime_message).replace(/"/g, '&quot;')}, ${JSON.stringify(item.manager_response).replace(/"/g, '&quot;')})">
                                ${item.overtime_message && item.overtime_message !== 'No message available' 
                                    ? item.overtime_message.length > 30 
                                        ? item.overtime_message.substring(0, 30) + '...' 
                                        : item.overtime_message
                                    : '<span class="no-report">No message</span>'}
                            </div>
                        </td>
                        <td><span class="status ${statusClass}">${statusText}</span></td>
                        <td>${approvalInfo}</td>
                        <td><span class="status ${paymentStatusClass}">${paymentStatus}</span></td>
                        <td>
                                            <div class="table-actions">
                            ${(() => {
                                                                        // Only show active approve/reject buttons for "submitted" status
                                    if (statusText === 'Submitted') {
                                        return `
                                                                                        <button class="btn-icon approve" title="Approve" onclick="openApproveModal(${item.id}, '${item.username}', '${item.date}', '${item.hours}')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn-icon reject" title="Reject" onclick="openRejectModal(${item.id}, '${item.username}', '${item.date}', '${item.hours}')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        `;
                                    } 
                                    // Show locked button with tooltip for "pending" status
                                    else if (statusText === 'Pending') {
                                        return `
                                            <button class="btn-icon locked" title="Cannot approve: Status must be 'Submitted' first">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        `;
                                    }
                                    // Don't show buttons for other statuses
                                    else {
                                        return '';
                                    }
                                })()}
                                <button class="btn-icon view" title="View Details" onclick="viewDetails(${item.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                                ${item.status && item.status.toLowerCase() === 'approved' ? `
                                <button class="btn-icon payment" title="Payment" style="background-color: #8e44ad;" onclick="openPaymentModal(${item.id}, '${item.username}', '${item.date}', '${item.hours}', ${item.overtime_id || item.id})">
                                    <i class="fas fa-rupee-sign"></i>
                                </button>
                                ` : ''}
                            </div>
                        </td>
                    `;
                    
                    tableBody.appendChild(row);
                });
                
                // Update statistics
                updateStats(filteredData);
            }
            
            /**
             * Show a filter message with optional type (info, loading, success, error)
             */
            function showFilterMessage(message, type = 'info') {
                if (filterMessageContainer && filterMessageText) {
                    // Set the message text
                    filterMessageText.textContent = message;
                    
                    // Set the appropriate icon based on message type
                    const iconContainer = document.getElementById('filter-message-icon');
                    if (iconContainer) {
                        if (type === 'loading') {
                            iconContainer.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        } else if (type === 'success') {
                            iconContainer.innerHTML = '<i class="fas fa-check-circle" style="color: #2ecc71;"></i>';
                        } else if (type === 'error') {
                            iconContainer.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i>';
                        } else {
                            // Default info icon
                            iconContainer.innerHTML = '<i class="fas fa-info-circle"></i>';
                        }
                    }
                    
                    // Show the message container
                    filterMessageContainer.style.display = 'block';
                }
            }
            
            /**
             * Hide the filter message
             */
            function hideFilterMessage() {
                if (filterMessageContainer) {
                    filterMessageContainer.style.display = 'none';
                }
            }
            
            /**
             * Update statistics based on filtered data
             */
            function updateStats(data) {
                // Get stat elements
                const statElements = document.querySelectorAll('.stat-info h3');
                if (!statElements || statElements.length < 3) return;
                
                if (!data || data.length === 0) {
                    // Reset stats to zero
                    statElements[0].textContent = '0';
                    statElements[1].textContent = '0';
                    statElements[2].textContent = '0';
                    return;
                }
                
                // Calculate total overtime hours
                let totalHours = 0;
                let pendingCount = 0;
                let approvedCount = 0;
                let rejectedCount = 0;
                
                data.forEach(item => {
                    // Use raw hours if available
                    if (typeof item.rawHours === 'number') {
                        totalHours += item.rawHours;
                    } else {
                        // Extract hours from string as fallback
                        const hoursMatch = item.hours && item.hours.match(/^(\d+(\.\d+)?)/);
                    if (hoursMatch) {
                        const hours = parseFloat(hoursMatch[1]);
                        if (!isNaN(hours)) {
                            totalHours += hours;
                            }
                        }
                    }
                    
                    // Count by status
                    const status = item.status ? item.status.toLowerCase() : 'pending';
                    if (status === 'approved') {
                            approvedCount++;
                    } else if (status === 'rejected') {
                        rejectedCount++;
                    } else {
                        pendingCount++;
                    }
                });
                
                // Update the stats display
                statElements[0].textContent = totalHours.toFixed(1);
                statElements[1].textContent = pendingCount;
                statElements[2].textContent = approvedCount;
            }
            
            // Expose these functions to the global scope for action buttons
            // Modal functions
            window.openApproveModal = function(userId, userName, date, hours) {
                document.getElementById('approveUserId').value = userId;
                document.getElementById('approveUserName').textContent = userName;
                document.getElementById('approveDate').textContent = date;
                document.getElementById('approveHours').textContent = hours;
                document.getElementById('approveReason').value = '';
                
                // Reset checkboxes and error message
                document.getElementById('approveCheckbox1').checked = false;
                document.getElementById('approveCheckbox2').checked = false;
                document.getElementById('approveCheckboxError').style.display = 'none';
                
                // Store the row identifier for more precise updates
                document.getElementById('approveRowId').value = `${userId}_${date}`;
                
                document.getElementById('approveModal').style.display = 'block';
            };
            
            window.openRejectModal = function(userId, userName, date, hours) {
                document.getElementById('rejectUserId').value = userId;
                document.getElementById('rejectUserName').textContent = userName;
                document.getElementById('rejectDate').textContent = date;
                document.getElementById('rejectHours').textContent = hours;
                document.getElementById('rejectReason').value = '';
                document.getElementById('rejectReasonError').style.display = 'none';
                
                // Reset checkboxes and error message
                document.getElementById('rejectCheckbox1').checked = false;
                document.getElementById('rejectCheckbox2').checked = false;
                document.getElementById('rejectCheckboxError').style.display = 'none';
                
                // Store the row identifier for more precise updates
                document.getElementById('rejectRowId').value = `${userId}_${date}`;
                
                document.getElementById('rejectModal').style.display = 'block';
            };
            
            window.closeModal = function(modalId) {
                document.getElementById(modalId).style.display = 'none';
            };
            
            // Close modal when clicking outside of it
            window.onclick = function(event) {
                if (event.target.className === 'modal') {
                    event.target.style.display = 'none';
                }
            };
            
            window.confirmApprove = function() {
                const userId = document.getElementById('approveUserId').value;
                const reason = document.getElementById('approveReason').value;
                const rowId = document.getElementById('approveRowId').value;
                
                // Validate checkboxes
                const checkbox1 = document.getElementById('approveCheckbox1');
                const checkbox2 = document.getElementById('approveCheckbox2');
                const checkboxError = document.getElementById('approveCheckboxError');
                
                if (!checkbox1.checked || !checkbox2.checked) {
                    checkboxError.style.display = 'block';
                    return;
                }
                
                console.log(`Approving overtime for user ID: ${userId} with reason: ${reason}, row ID: ${rowId}`);
                
                // Get date and hours from the modal
                const date = document.getElementById('approveDate').textContent;
                const hours = document.getElementById('approveHours').textContent;
                
                // Close the modal first to prevent UI issues
                closeModal('approveModal');
                
                // Show loading message
                showFilterMessage('Processing approval...', 'loading');
                
                // Call the update function which will make the AJAX request
                updateUserStatus(userId, 'approved', reason, date, hours, rowId);
                
                // Note: We don't need to call applyFilters() here as it's called in the AJAX success callback
            };
            
            window.confirmReject = function() {
                const userId = document.getElementById('rejectUserId').value;
                const reason = document.getElementById('rejectReason').value;
                const rowId = document.getElementById('rejectRowId').value;
                
                // Validate reason is provided for rejection
                if (!reason.trim()) {
                    document.getElementById('rejectReasonError').style.display = 'block';
                    return;
                }
                
                // Validate checkboxes
                const checkbox1 = document.getElementById('rejectCheckbox1');
                const checkbox2 = document.getElementById('rejectCheckbox2');
                const checkboxError = document.getElementById('rejectCheckboxError');
                
                if (!checkbox1.checked || !checkbox2.checked) {
                    checkboxError.style.display = 'block';
                    return;
                }
                
                console.log(`Rejecting overtime for user ID: ${userId} with reason: ${reason}, row ID: ${rowId}`);
                
                // Get date and hours from the modal
                const date = document.getElementById('rejectDate').textContent;
                const hours = document.getElementById('rejectHours').textContent;
                
                // Close the modal first to prevent UI issues
                closeModal('rejectModal');
                
                // Show loading message
                showFilterMessage('Processing rejection...', 'loading');
                
                // Call the update function which will make the AJAX request
                updateUserStatus(userId, 'rejected', reason, date, hours, rowId);
                
                // Note: We don't need to call applyFilters() here as it's called in the AJAX success callback
            };
            
            window.viewOvertimeMessage = function(username, date, message, managerResponse) {
                console.log(`Viewing overtime message for ${username} on ${date}`);
                
                // Set the values in the modal
                document.getElementById('overtimeMessageUserName').textContent = username;
                document.getElementById('overtimeMessageDate').textContent = date;
                
                // Set the message content
                const messageContent = document.getElementById('overtimeMessageContent');
                if (message && message !== 'No message available') {
                    messageContent.textContent = message;
                } else {
                    messageContent.innerHTML = '<p class="no-report-full">No overtime message is available.</p>';
                }
                
                // Set the manager response if available
                const responseSection = document.getElementById('managerResponseSection');
                const responseContent = document.getElementById('managerResponseContent');
                
                if (managerResponse && managerResponse.trim() !== '') {
                    responseContent.textContent = managerResponse;
                    responseSection.style.display = 'block';
                } else {
                    responseSection.style.display = 'none';
                }
                
                // Show the modal
                document.getElementById('overtimeMessageModal').style.display = 'block';
            };
            
            window.viewWorkReport = function(username, date, workReport) {
                console.log(`Viewing work report for ${username} on ${date}`);
                
                // Set the values in the modal
                document.getElementById('workReportUserName').textContent = username;
                document.getElementById('workReportDate').textContent = date;
                
                // Set the work report content
                const reportContent = document.getElementById('workReportContent');
                if (workReport && workReport !== 'No report submitted') {
                    // Format the text with paragraphs
                    const formattedReport = workReport
                        .split('\n')
                        .map(line => line.trim())
                        .filter(line => line.length > 0)
                        .map(line => `<p>${line}</p>`)
                        .join('');
                    
                    reportContent.innerHTML = formattedReport;
                } else {
                    reportContent.innerHTML = '<p class="no-report-full">No work report was submitted for this overtime.</p>';
                }
                
                // Show the modal
                document.getElementById('workReportModal').style.display = 'block';
            };
            
            // Payment Modal Functions
            window.openPaymentModal = function(userId, userName, date, hours, overtimeId) {
                console.log(`Opening payment modal for user ID: ${userId}, overtime ID: ${overtimeId}`);
                
                // Set the values in the modal
                document.getElementById('paymentUserId').value = userId;
                document.getElementById('paymentUserName').textContent = userName;
                document.getElementById('paymentDate').textContent = date;
                document.getElementById('paymentHours').textContent = hours;
                document.getElementById('paymentOvertimeId').value = overtimeId || '';
                document.getElementById('paymentOvertimeIdDisplay').textContent = overtimeId || 'N/A';
                
                // Always initialize with "Unpaid" status first
                const statusElement = document.getElementById('paymentStatus');
                statusElement.textContent = "Unpaid";
                statusElement.className = "status unpaid";
                statusElement.style.backgroundColor = "#f39c12";
                
                // Enable the payment button by default
                document.getElementById('processPaymentBtn').disabled = false;
                document.getElementById('processPaymentBtn').style.opacity = "1";
                
                // If no overtime ID is provided, use the test record ID
                if (!overtimeId) {
                    overtimeId = 633; // Fallback to test record ID
                }
                
                // Clear any previous console logs
                console.clear();
                console.log('Checking payment status for employee ID:', userId, 'and overtime ID:', overtimeId);
                
                // Check if payment exists in database
                $.ajax({
                    url: 'api/process_overtime_payment.php',
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        check_status: true,
                        overtime_id: overtimeId,
                        employee_id: userId
                    },
                    success: function(response) {
                        console.log('Payment status check response:', response);
                        
                        // Debug the actual status value
                        console.log('Payment status value:', response?.data?.status);
                        
                        // Always default to unpaid unless explicitly paid
                        let isPaid = false;
                        
                        // Check if payment record exists and status is explicitly 'paid'
                        if (response && 
                            response.success && 
                            response.data && 
                            typeof response.data.status === 'string' && 
                            response.data.status.toLowerCase() === 'paid') {
                            isPaid = true;
                        }
                        
                        if (isPaid) {
                            // Update status to "Paid"
                            statusElement.textContent = "Paid";
                            statusElement.className = "status paid";
                            statusElement.style.backgroundColor = "#2ecc71";
                            
                            // Disable the payment button
                            document.getElementById('processPaymentBtn').disabled = true;
                            document.getElementById('processPaymentBtn').style.opacity = "0.5";
                        } else {
                            // Ensure status is "Unpaid"
                            statusElement.textContent = "Unpaid";
                            statusElement.className = "status unpaid";
                            statusElement.style.backgroundColor = "#f39c12";
                            
                            // Enable the payment button
                            document.getElementById('processPaymentBtn').disabled = false;
                            document.getElementById('processPaymentBtn').style.opacity = "1";
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Payment status check error:', error);
                    }
                });
                
                // Calculate dummy payment amount based on hours
                let amount = 0;
                try {
                    // Extract numeric hours value
                    const hoursValue = parseFloat(hours.match(/\d+(\.\d+)?/)[0]);
                    // Calculate dummy amount (₹500 per hour)
                    amount = hoursValue * 500;
                } catch (e) {
                    console.error('Error calculating amount:', e);
                    amount = 1500; // Default fallback
                }
                
                // Format the amount with commas and rupee symbol
                const formattedAmount = '₹' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                document.getElementById('paymentAmount').textContent = formattedAmount;
                
                // Reset checkboxes and error message
                document.getElementById('paymentCheckbox1').checked = false;
                document.getElementById('paymentCheckbox2').checked = false;
                document.getElementById('paymentCheckbox3').checked = false;
                document.getElementById('paymentCheckboxError').style.display = 'none';
                document.getElementById('paymentNotes').value = '';
                
                // Store the row identifier for more precise updates
                document.getElementById('paymentRowId').value = `${userId}_${date}`;
                
                // Show the modal
                document.getElementById('paymentModal').style.display = 'block';
            };
            
            // Create payment animation overlay
            function createPaymentAnimationOverlay() {
                // Create overlay container
                const overlay = document.createElement('div');
                overlay.id = 'paymentAnimationOverlay';
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
                overlay.style.display = 'flex';
                overlay.style.flexDirection = 'column';
                overlay.style.justifyContent = 'center';
                overlay.style.alignItems = 'center';
                overlay.style.zIndex = '10000';
                overlay.style.opacity = '0';
                overlay.style.transition = 'opacity 0.5s ease';
                
                // Create animation container
                const animContainer = document.createElement('div');
                animContainer.style.width = '200px';
                animContainer.style.height = '200px';
                animContainer.style.position = 'relative';
                animContainer.style.marginBottom = '20px';
                
                // Create spinner animation
                const spinner = document.createElement('div');
                spinner.className = 'payment-spinner';
                spinner.style.width = '100%';
                spinner.style.height = '100%';
                spinner.style.border = '8px solid rgba(255, 255, 255, 0.1)';
                spinner.style.borderRadius = '50%';
                spinner.style.borderTop = '8px solid #8e44ad';
                spinner.style.animation = 'paymentSpin 1s linear infinite';
                
                // Create rupee icon
                const rupeeIcon = document.createElement('div');
                rupeeIcon.innerHTML = '<i class="fas fa-rupee-sign"></i>';
                rupeeIcon.style.position = 'absolute';
                rupeeIcon.style.top = '50%';
                rupeeIcon.style.left = '50%';
                rupeeIcon.style.transform = 'translate(-50%, -50%)';
                rupeeIcon.style.fontSize = '60px';
                rupeeIcon.style.color = '#8e44ad';
                
                // Create status text
                const statusText = document.createElement('div');
                statusText.id = 'paymentStatusText';
                statusText.textContent = 'Processing Payment...';
                statusText.style.color = 'white';
                statusText.style.fontSize = '24px';
                statusText.style.fontWeight = 'bold';
                statusText.style.marginBottom = '20px';
                statusText.style.textAlign = 'center';
                
                // Create progress bar container
                const progressContainer = document.createElement('div');
                progressContainer.style.width = '300px';
                progressContainer.style.height = '10px';
                progressContainer.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
                progressContainer.style.borderRadius = '5px';
                progressContainer.style.overflow = 'hidden';
                
                // Create progress bar
                const progressBar = document.createElement('div');
                progressBar.id = 'paymentProgressBar';
                progressBar.style.height = '100%';
                progressBar.style.width = '0%';
                progressBar.style.backgroundColor = '#8e44ad';
                progressBar.style.transition = 'width 0.1s linear';
                
                // Add CSS animation
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes paymentSpin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    
                    @keyframes fadeIn {
                        0% { opacity: 0; }
                        100% { opacity: 1; }
                    }
                    
                                         @keyframes pulse {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.1); }
                        100% { transform: scale(1); }
                    }
                    
                    @keyframes flyPlane {
                        0% { 
                            transform: translate(-150%, 0) rotate(15deg); 
                            opacity: 0;
                        }
                        10% { 
                            transform: translate(-100%, -20px) rotate(5deg); 
                            opacity: 1;
                        }
                        40% { 
                            transform: translate(-20%, -40px) rotate(0deg); 
                        }
                        60% { 
                            transform: translate(20%, -20px) rotate(-5deg); 
                        }
                        90% { 
                            transform: translate(100%, 0) rotate(-15deg); 
                            opacity: 1;
                        }
                        100% { 
                            transform: translate(150%, 20px) rotate(-20deg); 
                            opacity: 0;
                        }
                    }
                    
                    @keyframes statusChange {
                        0% { transform: scale(1); opacity: 1; }
                        50% { transform: scale(1.5); opacity: 0; }
                        51% { transform: scale(0.5); opacity: 0; }
                        100% { transform: scale(1); opacity: 1; }
                    }
                    
                    .payment-success {
                        animation: pulse 0.5s ease-in-out;
                        color: #2ecc71 !important;
                    }
                    
                    .flying-plane {
                        position: absolute;
                        font-size: 40px;
                        color: #3498db;
                        animation: flyPlane 3s ease-in-out forwards;
                        z-index: 10001;
                        text-shadow: 0 0 10px rgba(255,255,255,0.7);
                    }
                    
                    .status-change {
                        animation: statusChange 1s ease-in-out forwards;
                    }
                `;
                
                // Assemble the overlay
                document.head.appendChild(style);
                animContainer.appendChild(spinner);
                animContainer.appendChild(rupeeIcon);
                progressContainer.appendChild(progressBar);
                overlay.appendChild(animContainer);
                overlay.appendChild(statusText);
                overlay.appendChild(progressContainer);
                document.body.appendChild(overlay);
                
                // Fade in the overlay
                setTimeout(() => {
                    overlay.style.opacity = '1';
                }, 10);
                
                return {
                    overlay,
                    statusText: document.getElementById('paymentStatusText'),
                    progressBar: document.getElementById('paymentProgressBar'),
                    rupeeIcon: rupeeIcon,
                    spinner: spinner,
                    updateProgress: function(percent) {
                        document.getElementById('paymentProgressBar').style.width = percent + '%';
                    },
                    updateStatus: function(text) {
                        document.getElementById('paymentStatusText').textContent = text;
                    },
                    showSuccess: function() {
                        spinner.style.borderTop = '8px solid #2ecc71';
                        rupeeIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                        rupeeIcon.style.color = '#2ecc71';
                        rupeeIcon.classList.add('payment-success');
                        this.updateStatus('Payment Successful!');
                        document.getElementById('paymentProgressBar').style.backgroundColor = '#2ecc71';
                        document.getElementById('paymentProgressBar').style.width = '100%';
                        
                        // Create status change elements
                        const statusContainer = document.createElement('div');
                        statusContainer.style.position = 'relative';
                        statusContainer.style.marginTop = '30px';
                        statusContainer.style.width = '300px';
                        statusContainer.style.height = '60px';
                        statusContainer.style.overflow = 'hidden';
                        
                        // Create status labels
                        const unpaidStatus = document.createElement('div');
                        unpaidStatus.textContent = 'UNPAID';
                        unpaidStatus.style.position = 'absolute';
                        unpaidStatus.style.top = '50%';
                        unpaidStatus.style.left = '50%';
                        unpaidStatus.style.transform = 'translate(-50%, -50%)';
                        unpaidStatus.style.color = '#f39c12';
                        unpaidStatus.style.fontWeight = 'bold';
                        unpaidStatus.style.fontSize = '24px';
                        unpaidStatus.style.backgroundColor = 'rgba(0,0,0,0.3)';
                        unpaidStatus.style.padding = '5px 15px';
                        unpaidStatus.style.borderRadius = '20px';
                        unpaidStatus.id = 'unpaidStatus';
                        
                        const paidStatus = document.createElement('div');
                        paidStatus.textContent = 'PAID';
                        paidStatus.style.position = 'absolute';
                        paidStatus.style.top = '50%';
                        paidStatus.style.left = '50%';
                        paidStatus.style.transform = 'translate(-50%, -50%)';
                        paidStatus.style.color = '#2ecc71';
                        paidStatus.style.fontWeight = 'bold';
                        paidStatus.style.fontSize = '24px';
                        paidStatus.style.backgroundColor = 'rgba(0,0,0,0.3)';
                        paidStatus.style.padding = '5px 15px';
                        paidStatus.style.borderRadius = '20px';
                        paidStatus.style.opacity = '0';
                        paidStatus.id = 'paidStatus';
                        
                        statusContainer.appendChild(unpaidStatus);
                        statusContainer.appendChild(paidStatus);
                        overlay.appendChild(statusContainer);
                        
                        // Add flying airplane animation
                        setTimeout(() => {
                            // Create airplane element
                            const airplane = document.createElement('div');
                            airplane.innerHTML = '<i class="fas fa-paper-plane"></i>';
                            airplane.className = 'flying-plane';
                            airplane.style.top = statusContainer.offsetTop + 'px';
                            
                            // Add a small paper trail behind the plane
                            const paperTrail = document.createElement('div');
                            paperTrail.style.position = 'absolute';
                            paperTrail.style.top = (statusContainer.offsetTop + 10) + 'px';
                            paperTrail.style.left = '50%';
                            paperTrail.style.width = '80%';
                            paperTrail.style.height = '3px';
                            paperTrail.style.backgroundColor = 'rgba(255,255,255,0.3)';
                            paperTrail.style.transform = 'translateX(-50%)';
                            paperTrail.style.zIndex = '10000';
                            
                            // Add elements to the overlay
                            overlay.appendChild(paperTrail);
                            overlay.appendChild(airplane);
                            
                            // Trigger status change animation when plane is in the middle
                            setTimeout(() => {
                                unpaidStatus.classList.add('status-change');
                                
                                // Switch visibility after animation midpoint
                                setTimeout(() => {
                                    unpaidStatus.style.opacity = '0';
                                    paidStatus.style.opacity = '1';
                                    paidStatus.classList.add('status-change');
                                    
                                    // Add a burst of particles around the status
                                    this.createParticles(statusContainer);
                                }, 500);
                            }, 1500);
                        }, 500);
                    },
                    
                    // Create particle burst effect
                    createParticles: function(container) {
                        const colors = ['#2ecc71', '#3498db', '#f39c12', '#9b59b6', '#1abc9c'];
                        const particleCount = 20;
                        
                        for (let i = 0; i < particleCount; i++) {
                            const particle = document.createElement('div');
                            const size = Math.random() * 8 + 4;
                            
                            particle.style.position = 'absolute';
                            particle.style.width = size + 'px';
                            particle.style.height = size + 'px';
                            particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                            particle.style.borderRadius = '50%';
                            particle.style.top = '50%';
                            particle.style.left = '50%';
                            
                            // Random position and animation
                            const angle = Math.random() * Math.PI * 2;
                            const distance = Math.random() * 100 + 50;
                            const duration = Math.random() * 1 + 1;
                            
                            particle.style.transform = 'translate(-50%, -50%)';
                            particle.style.zIndex = '10002';
                            
                            // Create unique animation for each particle
                            const keyframeName = 'particle-' + i;
                            const style = document.createElement('style');
                            style.textContent = `
                                @keyframes ${keyframeName} {
                                    0% { 
                                        transform: translate(-50%, -50%);
                                        opacity: 1;
                                    }
                                    100% { 
                                        transform: translate(
                                            calc(-50% + ${Math.cos(angle) * distance}px), 
                                            calc(-50% + ${Math.sin(angle) * distance}px)
                                        );
                                        opacity: 0;
                                    }
                                }
                            `;
                            document.head.appendChild(style);
                            
                            particle.style.animation = `${keyframeName} ${duration}s ease-out forwards`;
                            container.appendChild(particle);
                            
                            // Remove particles after animation
                            setTimeout(() => {
                                container.removeChild(particle);
                                document.head.removeChild(style);
                            }, duration * 1000);
                        }
                    },
                    showError: function(message) {
                        spinner.style.borderTop = '8px solid #e74c3c';
                        rupeeIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                        rupeeIcon.style.color = '#e74c3c';
                        this.updateStatus(message || 'Payment Failed');
                        document.getElementById('paymentProgressBar').style.backgroundColor = '#e74c3c';
                    },
                    remove: function() {
                        overlay.style.opacity = '0';
                        setTimeout(() => {
                            document.body.removeChild(overlay);
                        }, 500);
                    }
                };
            }
            
            window.confirmPayment = function() {
                // Get values from the modal
                let userId = document.getElementById('paymentUserId').value;
                const notes = document.getElementById('paymentNotes').value;
                const rowId = document.getElementById('paymentRowId').value;
                const hours = document.getElementById('paymentHours').textContent;
                const amountText = document.getElementById('paymentAmount').textContent;
                
                // Use the actual overtime ID from the data attribute
                let overtimeId = parseInt(document.getElementById('paymentOvertimeId').value || '0');
                
                // If no overtime ID is set, use the test record ID as fallback
                if (!overtimeId) {
                    overtimeId = 633; // Fallback to test record ID
                    console.log('Using fallback overtime ID:', overtimeId);
                }
                
                console.log('Using generated overtime ID:', overtimeId);
                
                // Parse amount (remove rupee symbol and commas)
                const amount = parseFloat(amountText.replace('₹', '').replace(/,/g, ''));
                
                // Parse hours (extract number from string like "2.5 hours")
                const hoursValue = parseFloat(hours.match(/\d+(\.\d+)?/)[0]);
                
                // Validate checkboxes
                const checkbox1 = document.getElementById('paymentCheckbox1');
                const checkbox2 = document.getElementById('paymentCheckbox2');
                const checkbox3 = document.getElementById('paymentCheckbox3');
                const checkboxError = document.getElementById('paymentCheckboxError');
                
                if (!checkbox1.checked || !checkbox2.checked || !checkbox3.checked) {
                    checkboxError.style.display = 'block';
                    return;
                }
                
                console.log(`Processing payment for user ID: ${userId} with notes: ${notes}, overtime ID: ${overtimeId}, amount: ${amount}, hours: ${hoursValue}`);
                
                // Close the payment modal
                closeModal('paymentModal');
                
                // Create and show the payment animation
                const animation = createPaymentAnimationOverlay();
                
                // Simulate progress updates
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 4; // Increment by 4% each time
                    if (progress > 90) {
                        clearInterval(progressInterval);
                    } else {
                        animation.updateProgress(progress);
                    }
                }, 100);
                
                // Update status messages during animation
                setTimeout(() => { animation.updateStatus('Verifying payment details...'); }, 1000);
                setTimeout(() => { animation.updateStatus('Processing transaction...'); }, 2000);
                setTimeout(() => { animation.updateStatus('Updating records...'); }, 3000);
                
                // Send AJAX request to process payment
                console.log('Sending payment request to API...');
                
                // Debug: Show what we're sending
                console.log('Payment request data:', {
                    overtime_id: overtimeId,
                    employee_id: userId,
                    amount: amount,
                    hours: hoursValue,
                    notes: notes,
                    status: 'paid'
                });
                
                // Log the actual values to make sure we're using the right ones
                console.log('ACTUAL VALUES - overtime_id:', overtimeId, 'employee_id:', userId);
                
                $.ajax({
                    url: 'api/process_overtime_payment.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        overtime_id: overtimeId,
                        employee_id: userId,
                        amount: amount,
                        hours: hoursValue,
                        notes: notes,
                        status: 'paid' // Default to paid when processed through the UI
                    },
                    success: function(response) {
                        console.log('Payment API response:', response);
                        
                        clearInterval(progressInterval);
                        
                        if (response && response.success) {
                            // Show success animation
                            animation.updateProgress(100);
                            animation.showSuccess();
                            
                            // Remove animation after animation completes (extended to 5 seconds)
                            setTimeout(() => {
                                animation.remove();
                                
                                // Update UI to reflect payment
                                updateUIAfterPayment(userId, overtimeId);
                            }, 5000);
                        } else {
                            // Show error animation
                            animation.showError('Payment Failed: ' + (response && response.message ? response.message : 'Unknown error'));
                            
                            // Remove animation after 5 seconds
                            setTimeout(() => {
                                animation.remove();
                                
                                                        // Show error message
                        showFilterMessage('Error: ' + (response && response.message ? response.message : 'Failed to process payment'), 'error');
                        
                        // Show error toast
                        showToast(response && response.message ? response.message : 'Unknown error occurred', 'error', 'Payment Processing Failed');
                            }, 3000);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Payment API error:', error);
                        console.error('XHR status:', status);
                        console.error('XHR response text:', xhr.responseText);
                        
                        clearInterval(progressInterval);
                        
                        // Try to parse the response text for more details
                        let errorDetails = error;
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) {
                                errorDetails = response.message;
                            }
                        } catch (e) {
                            console.error('Could not parse error response:', e);
                        }
                        
                                                // Show error in animation
                        animation.showError('Payment Failed: ' + errorDetails);
                        
                        // Remove animation after 5 seconds
                        setTimeout(() => {
                            animation.remove();
                            
                            // Show error message
                            showFilterMessage('Error: ' + errorDetails, 'error');
                            
                            // Show error toast
                            showToast(errorDetails, 'error', 'Payment Processing Failed');
                        }, 3000);
                    }
                });
            };
            
            // Helper function to update UI after payment
            function updateUIAfterPayment(userId, overtimeId) {
                console.log(`Updating UI after payment for user ${userId}, overtime ID ${overtimeId}`);
                
                // Update the payment status in the table
                updatePaymentStatusInTable(userId, overtimeId);
                
                // Update the payment status in the modal
                const statusElement = document.getElementById('paymentStatus');
                statusElement.textContent = "Paid";
                statusElement.className = "status paid";
                statusElement.style.backgroundColor = "#2ecc71";
                
                // Disable the payment button
                document.getElementById('processPaymentBtn').disabled = true;
                document.getElementById('processPaymentBtn').style.opacity = "0.5";
            }
            
            window.viewDetails = function(userId) {
                console.log(`Viewing details for user ID: ${userId}`);
                
                // Find the user in either studio or site data
                let userRecord = null;
                
                for (const record of [...studioData, ...siteData]) {
                    if (record.id == userId) {
                        userRecord = record;
                        break;
                    }
                }
                
                if (userRecord) {
                    // Get the details modal
                    const detailsModal = document.getElementById('overtimeDetailsModal');
                    
                    // Set user details in the modal
                    document.getElementById('detailsUserName').textContent = userRecord.username;
                    document.getElementById('detailsUserAvatar').src = userRecord.profile_picture || 'assets/default-avatar.png';
                    document.getElementById('detailsUserAvatar').alt = userRecord.username;
                    document.getElementById('detailsUserDepartment').textContent = userRecord.department || 'N/A';
                    document.getElementById('detailsUserPosition').textContent = userRecord.position || 'N/A';
                    document.getElementById('detailsDate').textContent = userRecord.date;
                    document.getElementById('detailsShift').textContent = userRecord.shift_name || 'Default';
                    document.getElementById('detailsShiftEnd').textContent = userRecord.shiftEnd;
                    document.getElementById('detailsPunchOut').textContent = userRecord.punchOut;
                    document.getElementById('detailsOvertimeHours').textContent = userRecord.hours;
                    
                    // Set status with appropriate class
                    const statusElement = document.getElementById('detailsStatus');
                    statusElement.textContent = userRecord.status.charAt(0).toUpperCase() + userRecord.status.slice(1);
                    statusElement.className = 'details-status ' + userRecord.status;
                    
                    // Set manager info
                    const managerInfoElement = document.getElementById('detailsManagerInfo');
                    if (userRecord.status === 'approved' || userRecord.status === 'rejected') {
                        if (userRecord.manager) {
                            let managerInfo = userRecord.manager;
                            if (userRecord.actioned_at) {
                                const date = new Date(userRecord.actioned_at);
                                managerInfo += ` on ${date.toLocaleDateString()}`;
                            }
                            managerInfoElement.textContent = managerInfo;
                        } else {
                            managerInfoElement.textContent = 'N/A';
                        }
                    } else {
                        managerInfoElement.textContent = 'N/A';
                    }
                    
                    // Show or hide reason section based on status
                    const reasonSection = document.getElementById('detailsReasonSection');
                    if (userRecord.reason) {
                        document.getElementById('detailsReason').textContent = userRecord.reason;
                        reasonSection.style.display = 'block';
                    } else {
                        reasonSection.style.display = 'none';
                    }
                    
                    // Show work report if available
                    const workReportSection = document.getElementById('detailsWorkReportSection');
                    if (userRecord.work_report && userRecord.work_report !== 'No report submitted') {
                        document.getElementById('detailsWorkReport').textContent = userRecord.work_report;
                        workReportSection.style.display = 'block';
                    } else {
                        workReportSection.style.display = 'none';
                    }
                    
                    // Show overtime message if available
                    const overtimeMessageSection = document.getElementById('detailsOvertimeMessageSection');
                    if (userRecord.overtime_message && userRecord.overtime_message !== 'No message available') {
                        document.getElementById('detailsOvertimeMessage').textContent = userRecord.overtime_message;
                        overtimeMessageSection.style.display = 'block';
                        
                        // Show manager response if available
                        const managerResponseDiv = document.getElementById('detailsManagerResponseDiv');
                        if (userRecord.manager_response && userRecord.manager_response.trim() !== '') {
                            document.getElementById('detailsManagerResponse').textContent = userRecord.manager_response;
                            managerResponseDiv.style.display = 'block';
                        } else {
                            managerResponseDiv.style.display = 'none';
                        }
                    } else {
                        overtimeMessageSection.style.display = 'none';
                    }
                    
                    // Show the modal
                    detailsModal.style.display = 'block';
                } else {
                    // Fallback if record not found
                    alert(`No details found for user ID: ${userId}`);
                }
            };
            
            /**
             * Update a user's status in both data arrays
             */
            function updateUserStatus(userId, newStatus, reason = '', date = '', hours = '', rowId = '') {
                // Get the current user from session
                const currentUser = '<?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : "System"; ?>';
                const now = new Date().toISOString();
                
                console.log(`Updating status for user ${userId}, status: ${newStatus}, rowId: ${rowId}`);
                
                // Get date and hours from modal if not provided
                if (!date && newStatus === 'approved') {
                    date = document.getElementById('approveDate').textContent;
                    hours = document.getElementById('approveHours').textContent;
                } else if (!date && newStatus === 'rejected') {
                    date = document.getElementById('rejectDate').textContent;
                    hours = document.getElementById('rejectHours').textContent;
                }
                
                // Send AJAX request to update database
                $.ajax({
                    url: 'api/update_overtime_status.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        user_id: userId,
                        status: newStatus,
                        reason: reason,
                        date: date,
                        hours: hours
                    },
                    beforeSend: function() {
                        console.log(`Sending AJAX request to update status to ${newStatus} for user ${userId}`);
                    },
                    success: function(response) {
                        console.log('AJAX response:', response);
                        
                        if (response && response.success) {
                            // Update UI with success message
                            showFilterMessage(response.message || `Overtime ${newStatus} successfully!`, 'success');
                            
                            // Use manager username from response if available
                            const managerUsername = response.data && response.data.manager_username ? 
                                response.data.manager_username : currentUser;
                            
                            // Update local data arrays for UI
                            updateLocalData(userId, newStatus, managerUsername, now, reason);
                            
                            // Immediately update the UI without reloading data
                            console.log('Instantly updating UI after status change');
                            updateUIWithoutReload(userId, newStatus, managerUsername, rowId);
                        } else {
                            // Show error message
                            showFilterMessage('Error: ' + (response && response.message ? response.message : 'Unknown error'), 'error');
                            
                            // Still update local data for demo purposes
                            updateLocalData(userId, newStatus, currentUser, now, reason);
                            applyFilters();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        // Show error message
                        showFilterMessage('Error: ' + error, 'error');
                        
                        // Use session username for consistency
                        const managerUsername = '<?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : "System"; ?>';
                        
                        // Still update local data for demo purposes
                        updateLocalData(userId, newStatus, managerUsername, now, reason);
                        
                        // Immediately update the UI without reloading data
                        console.log('Instantly updating UI after status change (error fallback)');
                        updateUIWithoutReload(userId, newStatus, managerUsername, rowId);
                    }
                });
            }
            
            // Helper function to update local data arrays
            function updateLocalData(userId, newStatus, currentUser, timestamp, reason) {
                console.log(`Updating local data for user ID: ${userId} to status: ${newStatus}`);
                
                let updated = false;
                
                // Update in studioData
                for (let i = 0; i < studioData.length; i++) {
                    if (studioData[i].id == userId) {
                        console.log(`Found user in studioData, updating status from ${studioData[i].status} to ${newStatus}`);
                        // Force the status update
                        studioData[i].status = newStatus;
                        studioData[i].overtime_status = newStatus; // Add this to ensure the status is updated
                        
                        // Only store manager info for approved or rejected statuses
                        if (newStatus === 'approved' || newStatus === 'rejected') {
                            studioData[i].manager = currentUser;
                            studioData[i].actioned_at = timestamp;
                            if (reason) studioData[i].reason = reason;
                        } else {
                            studioData[i].manager = null;
                            studioData[i].actioned_at = null;
                            studioData[i].reason = null;
                        }
                        updated = true;
                        break;
                    }
                }
                
                // Update in siteData
                for (let i = 0; i < siteData.length; i++) {
                    if (siteData[i].id == userId) {
                        console.log(`Found user in siteData, updating status from ${siteData[i].status} to ${newStatus}`);
                        // Force the status update
                        siteData[i].status = newStatus;
                        siteData[i].overtime_status = newStatus; // Add this to ensure the status is updated
                        
                        // Only store manager info for approved or rejected statuses
                        if (newStatus === 'approved' || newStatus === 'rejected') {
                            siteData[i].manager = currentUser;
                            siteData[i].actioned_at = timestamp;
                            if (reason) siteData[i].reason = reason;
                        } else {
                            siteData[i].manager = null;
                            siteData[i].actioned_at = null;
                            siteData[i].reason = null;
                        }
                        updated = true;
                        break;
                    }
                }
                
                if (!updated) {
                    console.warn(`Could not find user ID ${userId} in either dataset`);
                    
                    // Try to find by user ID without exact status match
                    // This is a fallback in case the data structure doesn't match exactly
                    let found = false;
                    
                    // Check studio data
                    for (let i = 0; i < studioData.length; i++) {
                        if (studioData[i].id == userId) {
                            console.log(`Found user in studioData by ID only, updating status to ${newStatus}`);
                            // Force the status update
                            studioData[i].status = newStatus;
                            studioData[i].overtime_status = newStatus; // Add this to ensure the status is updated
                            
                            // Only store manager info for approved or rejected statuses
                            if (newStatus === 'approved' || newStatus === 'rejected') {
                                studioData[i].manager = currentUser;
                                studioData[i].actioned_at = timestamp;
                                if (reason) studioData[i].reason = reason;
                            } else {
                                studioData[i].manager = null;
                                studioData[i].actioned_at = null;
                                studioData[i].reason = null;
                            }
                            found = true;
                            break;
                        }
                    }
                    
                    // Check site data if not found in studio data
                    if (!found) {
                        for (let i = 0; i < siteData.length; i++) {
                            if (siteData[i].id == userId) {
                                console.log(`Found user in siteData by ID only, updating status to ${newStatus}`);
                                // Force the status update
                                siteData[i].status = newStatus;
                                siteData[i].overtime_status = newStatus; // Add this to ensure the status is updated
                                
                                // Only store manager info for approved or rejected statuses
                                if (newStatus === 'approved' || newStatus === 'rejected') {
                                    siteData[i].manager = currentUser;
                                    siteData[i].actioned_at = timestamp;
                                    if (reason) siteData[i].reason = reason;
                                } else {
                                    siteData[i].manager = null;
                                    siteData[i].actioned_at = null;
                                    siteData[i].reason = null;
                                }
                                found = true;
                                break;
                            }
                        }
                    }
                    
                    if (!found) {
                        console.error(`Could not find user ID ${userId} in any dataset, even with relaxed matching`);
                    }
                }
                
                            // Debugging: Log the first few items in each dataset to verify updates
            console.log('Sample studioData after update:', studioData.slice(0, 3));
            console.log('Sample siteData after update:', siteData.slice(0, 3));
        }
        
        /**
         * Directly update the UI for a specific user without reloading data
         * This function finds the row in the table for the given user and updates its status display
         */
        function updateUIWithoutReload(userId, newStatus, managerName, rowId = '') {
            console.log(`Direct UI update for user ${userId} to status ${newStatus}, rowId: ${rowId}`);
            
            // Use the actual manager name from session if available
            const currentManagerName = '<?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : "System"; ?>';
            managerName = currentManagerName || managerName;
            
            // Determine column indices by analyzing the table header
            const headerCells = document.querySelectorAll('.overtime-table thead th');
            let statusColumnIndex = 8; // Default values if header analysis fails
            let managerColumnIndex = 9;
            let actionsColumnIndex = 10;
            
            // Find the correct column indices by header text
            for (let i = 0; i < headerCells.length; i++) {
                const headerText = headerCells[i].textContent.trim().toLowerCase();
                if (headerText === 'status') {
                    statusColumnIndex = i + 1; // +1 because nth-child is 1-based
                    console.log('Found status column at index:', statusColumnIndex);
                } else if (headerText === 'approved by') {
                    managerColumnIndex = i + 1;
                    console.log('Found manager column at index:', managerColumnIndex);
                } else if (headerText === 'actions') {
                    actionsColumnIndex = i + 1;
                    console.log('Found actions column at index:', actionsColumnIndex);
                }
            }
            
            // Find the table row for this user
            const tableRows = document.querySelectorAll('.overtime-table tbody tr');
            let rowUpdated = false;
            
            // Store the specific row that was approved/rejected
            let targetRow = null;
            let targetDate = null;
            
            // If we have a rowId, use it to find the exact row
            if (rowId) {
                console.log('Using rowId to find target row:', rowId);
                const [rowUserId, rowDate] = rowId.split('_');
                
                // Find the row with the matching date cell
                // Using for...of instead of forEach to allow proper breaking out of the loop
                for (const row of tableRows) {
                    const dateCellText = row.querySelector('td:nth-child(2)').textContent.trim();
                    const userCellText = row.querySelector('td:nth-child(1)').textContent.trim();
                    
                    // Check if this row matches both user and date
                    if (userCellText === document.getElementById('approveUserName')?.textContent || 
                        userCellText === document.getElementById('rejectUserName')?.textContent) {
                        
                        if (dateCellText === rowDate) {
                            targetRow = row;
                            targetDate = rowDate;
                            console.log('Found target row using rowId match:', rowId);
                            break; // Exit the loop
                        }
                    }
                }
            }
            
            // If we didn't find the row using rowId, fall back to button-based search
            if (!targetRow) {
                console.log('No match found with rowId, falling back to button search');
                
                // First, find the exact row that was approved/rejected
                // We need to be more precise to avoid updating all rows for the same user
                // Using for...of instead of forEach to allow proper breaking out of the loop
                for (const row of tableRows) {
                    // Get the action buttons in this row
                    const approveButton = row.querySelector('.btn-icon.approve');
                    const rejectButton = row.querySelector('.btn-icon.reject');
                    
                    // If there are approve/reject buttons with this user ID, this is our target row
                    if (approveButton) {
                        const onclickAttr = approveButton.getAttribute('onclick') || '';
                        if (onclickAttr.includes(`openApproveModal(${userId},`)) {
                            targetRow = row;
                            
                            // Extract the date from the onclick attribute if possible
                            const dateMatch = onclickAttr.match(/openApproveModal\(\d+,\s*'[^']*',\s*'([^']*)',/);
                            if (dateMatch && dateMatch[1]) {
                                targetDate = dateMatch[1];
                            }
                            
                            console.log('Found target row with approve button for user', userId, 'date:', targetDate);
                            break; // Exit the loop
                        }
                    }
                    
                    if (rejectButton) {
                        const onclickAttr = rejectButton.getAttribute('onclick') || '';
                        if (onclickAttr.includes(`openRejectModal(${userId},`)) {
                            targetRow = row;
                            
                            // Extract the date from the onclick attribute if possible
                            const dateMatch = onclickAttr.match(/openRejectModal\(\d+,\s*'[^']*',\s*'([^']*)',/);
                            if (dateMatch && dateMatch[1]) {
                                targetDate = dateMatch[1];
                            }
                            
                            console.log('Found target row with reject button for user', userId, 'date:', targetDate);
                            break; // Exit the loop
                        }
                    }
                }
            }
            
            // If we found a specific row, update only that one
            if (targetRow) {
                console.log('Updating specific row for user', userId);
                
                // Update the status cell
                const statusCell = targetRow.querySelector(`td:nth-child(${statusColumnIndex})`);
                if (statusCell) {
                    // Determine status display
                    let statusClass = 'pending';
                    let statusText = 'Pending';
                    
                    if (newStatus === 'approved') {
                        statusClass = 'approved';
                        statusText = 'Approved';
                    } else if (newStatus === 'rejected') {
                        statusClass = 'rejected';
                        statusText = 'Rejected';
                    } else if (newStatus === 'submitted') {
                        statusClass = 'submitted';
                        statusText = 'Submitted';
                    }
                    
                    statusCell.innerHTML = `<span class="status ${statusClass}">${statusText}</span>`;
                    console.log('Status cell updated to:', statusText);
                }
                
                // Update the manager cell
                const managerCell = targetRow.querySelector(`td:nth-child(${managerColumnIndex})`);
                if (managerCell) {
                    const now = new Date();
                    const dateStr = now.toLocaleDateString();
                    
                    // Only show manager name for approved or rejected statuses
                    if (newStatus === 'approved' || newStatus === 'rejected') {
                        managerCell.textContent = `${managerName} on ${dateStr}`;
                    } else {
                        managerCell.textContent = 'N/A';
                    }
                    console.log('Manager cell updated to:', managerCell.textContent);
                }
                
                // Remove action buttons
                const actionsCell = targetRow.querySelector(`td:nth-child(${actionsColumnIndex})`);
                if (actionsCell) {
                    console.log('Updating actions cell for status:', newStatus);
                    
                    // Clear all existing buttons first
                    actionsCell.innerHTML = '';
                    
                    // Keep only the view button
                    const viewButton = targetRow.querySelector('.btn-icon.view');
                    if (viewButton) {
                        actionsCell.appendChild(viewButton.cloneNode(true));
                    } else {
                        // Create a new view button if it doesn't exist
                        const newViewButton = document.createElement('button');
                        newViewButton.className = 'btn-icon view';
                        newViewButton.title = 'View Details';
                        newViewButton.innerHTML = '<i class="fas fa-eye"></i>';
                        newViewButton.onclick = function() { viewDetails(userId); };
                        actionsCell.appendChild(newViewButton);
                    }
                    
                    // Add payment button with rupee icon only for approved overtime
                    if (newStatus === 'approved') {
                        const paymentButton = document.createElement('button');
                        paymentButton.className = 'btn-icon payment';
                        paymentButton.title = 'Payment';
                        paymentButton.style.backgroundColor = '#8e44ad';
                        paymentButton.innerHTML = '<i class="fas fa-rupee-sign"></i>';
                        paymentButton.onclick = function() { openPaymentModal(userId, 'User ' + userId, 'Current Date', '2.5 hours', userId); };
                        actionsCell.appendChild(paymentButton);
                    }
                    
                    console.log('Actions cell updated:', actionsCell.innerHTML);
                }
                
                rowUpdated = true;
            } else {
                // Fallback to the old method if we couldn't find the specific row
                console.log('Could not find specific row, falling back to user ID search');
                
                // Using for...of instead of forEach to allow proper breaking out of the loop
                for (const row of tableRows) {
                    // Check if this is the row for our user
                    const actionButtons = row.querySelectorAll('.table-actions button');
                    let foundUser = false;
                    
                    // Check if any button has an onclick handler with this user ID
                    for (const button of actionButtons) {
                        const onclickAttr = button.getAttribute('onclick') || '';
                        if (onclickAttr.includes(`(${userId},`) || onclickAttr.includes(`(${userId})`) || onclickAttr.includes(`viewDetails(${userId})`)) {
                            foundUser = true;
                            break;
                        }
                    }
                    
                    if (foundUser) {
                        console.log('Found matching row in table, updating status display');
                        
                        // Update the status cell
                        const statusCell = row.querySelector(`td:nth-child(${statusColumnIndex})`);
                        if (statusCell) {
                            // Determine status display
                            let statusClass = 'pending';
                            let statusText = 'Pending';
                            
                            if (newStatus === 'approved') {
                                statusClass = 'approved';
                                statusText = 'Approved';
                            } else if (newStatus === 'rejected') {
                                statusClass = 'rejected';
                                statusText = 'Rejected';
                            } else if (newStatus === 'submitted') {
                                statusClass = 'submitted';
                                statusText = 'Submitted';
                            }
                            
                            statusCell.innerHTML = `<span class="status ${statusClass}">${statusText}</span>`;
                            console.log('Fallback: Status cell updated to:', statusText);
                        }
                        
                        // Update the manager cell
                        const managerCell = row.querySelector(`td:nth-child(${managerColumnIndex})`);
                        if (managerCell) {
                            const now = new Date();
                            const dateStr = now.toLocaleDateString();
                            
                            // Only show manager name for approved or rejected statuses
                            if (newStatus === 'approved' || newStatus === 'rejected') {
                                managerCell.textContent = `${managerName} on ${dateStr}`;
                            } else {
                                managerCell.textContent = 'N/A';
                            }
                            console.log('Fallback: Manager cell updated to:', managerCell.textContent);
                        }
                        
                        // Remove action buttons
                        const actionsCell = row.querySelector(`td:nth-child(${actionsColumnIndex})`);
                        if (actionsCell) {
                            console.log('Updating actions cell in fallback for status:', newStatus);
                            
                            // Clear all existing buttons first
                            actionsCell.innerHTML = '';
                            
                            // Keep only the view button
                            const viewButton = row.querySelector('.btn-icon.view');
                            if (viewButton) {
                                actionsCell.appendChild(viewButton.cloneNode(true));
                            } else {
                                // Create a new view button if it doesn't exist
                                const newViewButton = document.createElement('button');
                                newViewButton.className = 'btn-icon view';
                                newViewButton.title = 'View Details';
                                newViewButton.innerHTML = '<i class="fas fa-eye"></i>';
                                newViewButton.onclick = function() { viewDetails(userId); };
                                actionsCell.appendChild(newViewButton);
                            }
                            
                            // Add payment button with rupee icon only for approved overtime
                            if (newStatus === 'approved') {
                                const paymentButton = document.createElement('button');
                                paymentButton.className = 'btn-icon payment';
                                paymentButton.title = 'Payment';
                                paymentButton.style.backgroundColor = '#8e44ad';
                                paymentButton.innerHTML = '<i class="fas fa-rupee-sign"></i>';
                                paymentButton.onclick = function() { openPaymentModal(userId, 'User ' + userId, 'Current Date', '2.5 hours', userId); };
                                actionsCell.appendChild(paymentButton);
                            }
                            
                            console.log('Actions cell updated in fallback:', actionsCell.innerHTML);
                        }
                        
                        rowUpdated = true;
                        break; // Only update the first matching row
                    }
                }
            }
            
            if (!rowUpdated) {
                console.warn(`Could not find table row for user ${userId}, falling back to full refresh`);
                applyFilters();
            } else {
                console.log('Successfully updated UI for specific row');
                
                // Also update stats
                updateStatsAfterStatusChange(newStatus);
            }
        }
        
        /**
         * Update the stats display after a status change without full refresh
         */
        function updateStatsAfterStatusChange(newStatus) {
            // Get stat elements
            const statElements = document.querySelectorAll('.stat-info h3');
            if (!statElements || statElements.length < 3) return;
            
            // Get current values
            const totalHours = parseFloat(statElements[0].textContent) || 0;
            let pendingCount = parseInt(statElements[1].textContent) || 0;
            let approvedCount = parseInt(statElements[2].textContent) || 0;
            
            // Update counts based on status change
            if (newStatus === 'approved') {
                // One less pending, one more approved
                pendingCount = Math.max(0, pendingCount - 1);
                approvedCount += 1;
            } else if (newStatus === 'rejected') {
                // One less pending
                pendingCount = Math.max(0, pendingCount - 1);
            }
            
            // Update the stats display
            statElements[1].textContent = pendingCount;
            statElements[2].textContent = approvedCount;
            
            console.log('Stats updated: Pending =', pendingCount, 'Approved =', approvedCount);
        }
        
        /**
         * Update payment status in the table after processing a payment
         */
        function updatePaymentStatusInTable(userId, overtimeId) {
            console.log(`Updating payment status for user ${userId}, overtime ID ${overtimeId}`);
            
            // Find the row for this user/overtime
            const tableRows = document.querySelectorAll('.overtime-table tbody tr');
            let found = false;
            
            tableRows.forEach(row => {
                // Check if this is the row for our user
                const actionButtons = row.querySelectorAll('.table-actions button');
                let isTargetRow = false;
                
                // Check if any button has an onclick handler with this user ID
                for (const button of actionButtons) {
                    const onclickAttr = button.getAttribute('onclick') || '';
                    if (onclickAttr.includes(`(${userId},`) || onclickAttr.includes(`openPaymentModal(${userId}`)) {
                        isTargetRow = true;
                        break;
                    }
                }
                
                if (isTargetRow) {
                    // Get the payment status cell (10th column)
                    const paymentStatusCell = row.querySelector('td:nth-child(10)');
                    if (paymentStatusCell) {
                        // Update the payment status to "Paid"
                        paymentStatusCell.innerHTML = '<span class="status paid">Paid</span>';
                        found = true;
                    }
                }
            });
            
            if (!found) {
                console.warn(`Could not find row for user ${userId} to update payment status`);
            }
        }
        
        // Update payment status when payment is processed
        window.updatePaymentStatusAfterProcessing = function(userId, overtimeId) {
            updatePaymentStatusInTable(userId, overtimeId);
        };

        // Export to Excel functionality
        if (exportExcelBtn) {
            exportExcelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                exportToExcel();
            });
        }

        /**
         * Export the current filtered data to Excel
         */
        function exportToExcel() {
            // Show loading message
            showFilterMessage('Preparing Excel export...', 'loading');
            
            // Show toast notification
            showToast('Your Excel file is being generated. Please wait...', 'info', 'Excel Export');
            
            // Show screen loader
            showScreenLoader();
            
            // Get current filtered data
            const userFilter = filterUserSelect ? filterUserSelect.value : '';
            const statusFilter = filterStatusSelect ? filterStatusSelect.value.toLowerCase() : '';
            const monthFilter = filterMonthSelect ? filterMonthSelect.value : '';
            const yearFilter = filterYearSelect ? filterYearSelect.value : '';
            
            // Get current dataset
            const isStudioView = !locationToggle || !locationToggle.checked;
            const studioDataFiltered = filterDataset(studioData, userFilter, statusFilter, monthFilter, yearFilter);
            const siteDataFiltered = filterDataset(siteData, userFilter, statusFilter, monthFilter, yearFilter);
            
            // Check if we have data to export
            if (studioDataFiltered.length === 0 && siteDataFiltered.length === 0) {
                showFilterMessage('No data to export', 'error');
                setTimeout(() => {
                    hideFilterMessage();
                }, 3000);
                return;
            }
            
            // Generate HTML for Excel
            let html = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <!--[if gte mso 9]>
                <xml>
                    <x:ExcelWorkbook>
                        <x:ExcelWorksheets>
                            <x:ExcelWorksheet>
                                <x:Name>Overtime Details</x:Name>
                                <x:WorksheetOptions>
                                    <x:DisplayGridlines/>
                                    <x:AutoFilter/>
                                </x:WorksheetOptions>
                            </x:ExcelWorksheet>
                        </x:ExcelWorksheets>
                    </x:ExcelWorkbook>
                </xml>
                <![endif]-->
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 9pt;
                    }
                    table {
                        border-collapse: collapse;
                        width: 100%;
                        border: 2px solid #4361ee;
                    }
                    td, th {
                        vertical-align: middle;
                        padding: 6px;
                        border: 1px solid #bdc3c7;
                        text-align: left;
                        font-size: 9pt;
                    }
                    .title {
                        background-color: #3a0ca3;
                        color: white;
                        font-weight: bold;
                        font-size: 14pt;
                        text-align: center;
                        padding: 10px;
                        border: 2px solid #3a0ca3;
                    }
                    .section-title {
                        background-color: #4361ee;
                        color: white;
                        font-weight: bold;
                        font-size: 11pt;
                        text-align: center;
                        padding: 8px;
                    }
                    .subtitle {
                        background-color: #f8f9fa;
                        font-weight: bold;
                        padding: 8px;
                        border: 1px solid #bdc3c7;
                        font-size: 10pt;
                    }
                    .header {
                        background-color: #4cc9f0;
                        color: #000000;
                        font-weight: bold;
                        border: 1px solid #3a86ff;
                        text-align: center;
                        white-space: nowrap;
                        font-size: 9pt;
                    }
                    .color-coding {
                        background-color: #f8f9fa;
                        padding: 6px;
                        font-size: 9pt;
                    }
                    .status-paid {
                        background-color: #00FF00;
                        color: #000000;
                        font-weight: bold;
                    }
                    .status-unpaid {
                        background-color: #FFCCCC;
                        color: #000000;
                        font-weight: bold;
                    }
                    .status-pending {
                        color: #000000;
                        font-weight: bold;
                        background-color: #FFFF99; /* Yellow */
                    }
                    .status-submitted {
                        color: #000000;
                        font-weight: bold;
                        background-color: #99CCFF; /* Light blue */
                    }
                    .status-approved {
                        color: #06d6a0;
                        font-weight: bold;
                        background-color: #e8fff3;
                    }
                    .status-rejected {
                        color: #e63946;
                        font-weight: bold;
                        background-color: #ffeeee;
                    }
                    .status-cell-paid {
                        background-color: #008800;
                        color: #FFFFFF;
                        font-weight: bold;
                    }
                    .status-cell-unpaid {
                        background-color: #FF6666;
                        color: #FFFFFF;
                        font-weight: bold;
                    }
                    .date-cell {
                        white-space: nowrap;
                        font-weight: 500;
                    }
                    .name-cell {
                        font-weight: 500;
                    }
                </style>
            </head>
            <body>
                <table border="1" cellpadding="5" cellspacing="0" width="100%">
                    <tr>
                        <td colspan="11" class="title">Overtime Details Report</td>
                    </tr>
                    <tr>
                        <td colspan="11" class="subtitle">Generated on: ${new Date().toLocaleDateString()}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="color-coding">
                            <div style="display: flex; justify-content: center; flex-direction: column; text-align: center;">
                               <span><b>Payment Status:</b> <span class="status-paid">🟢 Paid</span> | <span class="status-unpaid"> 🔴Unpaid</span></span>
                                    <span style="margin-top: 3px;"><b>Approval Status:</b> <span class="status-pending"> Pending</span> | <span class="status-submitted">🔵 Submitted</span></span>     
                            </div>
                        </td>
                        <td colspan="6" class="summary-table">
                            <table border="1" cellpadding="2" cellspacing="0" width="100%" style="border: 1px solid #3a86ff; font-size: 9pt;">
                                <tr>
                                    <th style="background-color: #4cc9f0; text-align: center; font-size: 9pt; padding: 4px;">Location</th>
                                    <th style="background-color: #4cc9f0; text-align: center; font-size: 9pt; padding: 4px;">Total Overtime Hours</th>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; text-align: center; font-size: 9pt; padding: 3px;">Studio</td>
                                    <td style="text-align: center; font-size: 9pt; padding: 3px;" id="studio-hours"></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; text-align: center; font-size: 9pt; padding: 3px;">Site</td>
                                    <td style="text-align: center; font-size: 9pt; padding: 3px;" id="site-hours"></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; text-align: center; background-color: #e9ecef; font-size: 9pt; padding: 3px;">Total</td>
                                    <td style="text-align: center; background-color: #e9ecef; font-weight: bold; font-size: 9pt; padding: 3px;" id="total-hours"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>`;
            
            // Add Studio data if available
            if (studioDataFiltered.length > 0) {
                html += `
                    <tr>
                        <td colspan="11" class="section-title">STUDIO OVERTIME DETAILS</td>
                    </tr>
                    <tr>
                        <td class="header">Username</td>
                        <td class="header">Date</td>
                        <td class="header">Shift End Time</td>
                        <td class="header">Punch Out Time</td>
                        <td class="header">Overtime Hours</td>
                        <td class="header">Work Report</td>
                        <td class="header">Overtime Report</td>
                        <td class="header">Submitted On</td>
                        <td class="header">Status</td>
                        <td class="header">Approved By</td>
                        <td class="header">Payment Status</td>
                    </tr>`;
                
                // Add data rows for studio
                studioDataFiltered.forEach(item => {
                    const paymentStatus = getPaymentStatus(item);
                    let rowClass = '';
                    
                    // Set row class based on status and payment status
                    if (item.status.toLowerCase() === 'pending') {
                        rowClass = 'status-pending';
                    } else if (item.status.toLowerCase() === 'submitted') {
                        rowClass = 'status-submitted';
                    } else if (item.status.toLowerCase() === 'approved') {
                        rowClass = paymentStatus === 'Paid' ? 'status-paid' : 'status-unpaid';
                    }
                    
                    const statusCellClass = paymentStatus === 'Paid' ? 'status-cell-paid' : 'status-cell-unpaid';
                    
                    html += `
                    <tr class="${rowClass}">
                        <td class="name-cell">${item.username}</td>
                        <td class="date-cell">${item.date}</td>
                        <td>${item.shiftEnd}</td>
                        <td>${item.punchOut}</td>
                        <td>${item.hours}</td>
                        <td>${stripHtml(item.work_report || 'No report')}</td>
                        <td>${stripHtml(item.overtime_message || 'No message')}</td>
                        <td>${formatSubmissionDate(item.submitted_on)}</td>
                        <td>${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</td>
                        <td>${item.manager || 'N/A'}</td>
                        <td class="${statusCellClass}">${paymentStatus}</td>
                    </tr>`;
                });
            }
            
            // Add Site data if available
            if (siteDataFiltered.length > 0) {
                html += `
                    <tr>
                        <td colspan="11" class="section-title">SITE OVERTIME DETAILS</td>
                    </tr>
                    <tr>
                        <td class="header">Username</td>
                        <td class="header">Date</td>
                        <td class="header">Shift End Time</td>
                        <td class="header">Punch Out Time</td>
                        <td class="header">Overtime Hours</td>
                        <td class="header">Work Report</td>
                        <td class="header">Overtime Report</td>
                        <td class="header">Submitted On</td>
                        <td class="header">Status</td>
                        <td class="header">Approved By</td>
                        <td class="header">Payment Status</td>
                    </tr>`;
                
                // Add data rows for site
                siteDataFiltered.forEach(item => {
                    const paymentStatus = getPaymentStatus(item);
                    let rowClass = '';
                    
                    // Set row class based on status and payment status
                    if (item.status.toLowerCase() === 'pending') {
                        rowClass = 'status-pending';
                    } else if (item.status.toLowerCase() === 'submitted') {
                        rowClass = 'status-submitted';
                    } else if (item.status.toLowerCase() === 'approved') {
                        rowClass = paymentStatus === 'Paid' ? 'status-paid' : 'status-unpaid';
                    }
                    
                    const statusCellClass = paymentStatus === 'Paid' ? 'status-cell-paid' : 'status-cell-unpaid';
                    
                    html += `
                    <tr class="${rowClass}">
                        <td class="name-cell">${item.username}</td>
                        <td class="date-cell">${item.date}</td>
                        <td>${item.shiftEnd}</td>
                        <td>${item.punchOut}</td>
                        <td>${item.hours}</td>
                        <td>${stripHtml(item.work_report || 'No report')}</td>
                        <td>${stripHtml(item.overtime_message || 'No message')}</td>
                        <td>${formatSubmissionDate(item.submitted_on)}</td>
                        <td>${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</td>
                        <td>${item.manager || 'N/A'}</td>
                        <td class="${statusCellClass}">${paymentStatus}</td>
                    </tr>`;
                });
            }
            
            // Calculate total overtime hours
            let studioTotalHours = 0;
            let siteTotalHours = 0;
            
            // Calculate studio hours
            studioDataFiltered.forEach(item => {
                // Extract numeric value from hours string (e.g. "2.5 hours" -> 2.5)
                const hoursMatch = item.hours.match(/(\d+(\.\d+)?)/);
                if (hoursMatch && hoursMatch[1]) {
                    studioTotalHours += parseFloat(hoursMatch[1]);
                }
            });
            
            // Calculate site hours
            siteDataFiltered.forEach(item => {
                const hoursMatch = item.hours.match(/(\d+(\.\d+)?)/);
                if (hoursMatch && hoursMatch[1]) {
                    siteTotalHours += parseFloat(hoursMatch[1]);
                }
            });
            
            // Format the hours to 1 decimal place
            const formattedStudioHours = studioTotalHours.toFixed(1);
            const formattedSiteHours = siteTotalHours.toFixed(1);
            const formattedTotalHours = (studioTotalHours + siteTotalHours).toFixed(1);
            
            // Update the summary table in the HTML
            html = html.replace('id="studio-hours"', `id="studio-hours">${formattedStudioHours}`);
            html = html.replace('id="site-hours"', `id="site-hours">${formattedSiteHours}`);
            html = html.replace('id="total-hours"', `id="total-hours">${formattedTotalHours}`);
            
            // Close the HTML document
            html += '</table></body></html>';
            
            // Generate file name with date
            const now = new Date();
            const dateStr = now.toISOString().split('T')[0];
            const fileName = `overtime_report_${dateStr}.xls`;
            
            // Create a Blob and download the file
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            // Hide screen loader
            hideScreenLoader();
            
            // Show success message
            showFilterMessage('Excel export successful!', 'success');
            
            // Show success toast
            showToast('Your Excel file has been downloaded successfully!', 'success', 'Excel Export Complete');
            
            setTimeout(() => {
                hideFilterMessage();
            }, 3000);
        }
        
        /**
         * Format submission date for display
         */
        function formatSubmissionDate(dateString) {
            if (!dateString) return 'N/A';
            
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    return 'N/A';
                }
                
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            } catch (e) {
                console.error('Error formatting submission date:', e);
                return 'N/A';
            }
        }
        
        /**
         * Filter a dataset based on the provided filters
         */
        function filterDataset(data, userFilter, statusFilter, monthFilter, yearFilter) {
            return data.filter(item => {
                // Ensure minimum overtime requirement (1.5 hours)
                const overtimeHours = parseFloat(item.rawHours) || 0;
                if (overtimeHours < 1.5) {
                    return false;
                }
                
                // User filter
                if (userFilter && item.username !== userFilter) {
                    return false;
                }
                
                // Status filter
                if (statusFilter && item.status.toLowerCase() !== statusFilter) {
                    return false;
                }
                
                // Date filters
                if (monthFilter || yearFilter) {
                    try {
                        // Parse the date (use rawDate if available)
                        const dateStr = item.rawDate || item.date;
                        const date = new Date(dateStr);
                        
                        if (isNaN(date.getTime())) {
                            return false;
                        }
                        
                        // Month filter
                        if (monthFilter) {
                            const month = date.toLocaleString('en-US', { month: 'long' });
                            if (month !== monthFilter) {
                                return false;
                            }
                        }
                        
                        // Year filter
                        if (yearFilter) {
                            const year = date.getFullYear().toString();
                            if (year !== yearFilter) {
                                return false;
                            }
                        }
                    } catch (e) {
                        return false;
                    }
                }
                
                return true;
            });
        }
        
                    /**
             * Get payment status for an item
             */
            function getPaymentStatus(item) {
                // Check if the item is approved
                if (item.status && item.status.toLowerCase() === 'approved') {
                    // Check if the payment status is already set
                    if (item.paymentStatus) {
                        return item.paymentStatus;
                    }
                    
                    // Check for payment status in the UI
                    // Look for payment status in the DOM for this item
                    const tableRows = document.querySelectorAll('.overtime-table tbody tr');
                    for (const row of tableRows) {
                        // Find the row that matches this item
                        const usernameCell = row.querySelector('td:first-child');
                        const dateCell = row.querySelector('td:nth-child(2)');
                        
                        if (usernameCell && dateCell && 
                            usernameCell.textContent === item.username && 
                            dateCell.textContent === item.date) {
                            
                            // Check the payment status cell
                            const paymentStatusCell = row.querySelector('td:nth-child(10) .status');
                            if (paymentStatusCell && paymentStatusCell.textContent.trim() === 'Paid') {
                                return 'Paid';
                            }
                            break;
                        }
                    }
                    
                    // If we're here, we couldn't find a matching row or it's not paid
                    // Make an AJAX call to check payment status
                    try {
                        // Synchronous AJAX call to ensure we have the status before continuing
                        let paymentStatus = 'Unpaid';
                        $.ajax({
                            url: 'api/process_overtime_payment.php',
                            method: 'GET',
                            data: {
                                check_status: true,
                                overtime_id: item.overtime_id || item.id,
                                employee_id: item.id
                            },
                            async: false, // Synchronous call to ensure we have the status
                            success: function(response) {
                                if (response.success && response.data && response.data.status === 'paid') {
                                    paymentStatus = 'Paid';
                                    // Cache the result for future use
                                    item.paymentStatus = 'Paid';
                                }
                            }
                        });
                        return paymentStatus;
                    } catch (e) {
                        console.error('Error checking payment status:', e);
                        return 'Unpaid';
                    }
                }
                return 'N/A';
            }
        
        /**
         * Strip HTML tags from a string
         */
        function stripHtml(html) {
            if (!html) return '';
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || '';
        }
        });
    </script>
    
    <!-- Add sidebar toggle functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded - initializing sidebar toggle');
        
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleButton = document.getElementById('sidebarToggle');
        
        console.log('Elements found:', {
            sidebar: sidebar ? 'Found' : 'Not found',
            mainContent: mainContent ? 'Found' : 'Not found',
            toggleButton: toggleButton ? 'Found' : 'Not found'
        });
        
        // Check saved state
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        console.log('Saved sidebar state:', sidebarCollapsed ? 'Collapsed' : 'Expanded');
        
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            toggleButton.classList.add('collapsed');
            console.log('Applied collapsed state from localStorage');
        }

        // Toggle function
        function toggleSidebar() {
            console.log('Toggle button clicked');
            console.log('Before toggle - Sidebar collapsed:', sidebar.classList.contains('collapsed'));
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleButton.classList.toggle('collapsed');
            
            console.log('After toggle - Sidebar collapsed:', sidebar.classList.contains('collapsed'));
            
            // Save state
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            console.log('Saved new state to localStorage');
        }

        // Click event
        if (toggleButton) {
            console.log('Adding click event listener to toggle button');
            toggleButton.addEventListener('click', function(e) {
                console.log('Toggle button click detected');
                e.preventDefault();
                toggleSidebar();
            });
        } else {
            console.error('Toggle button not found - cannot add click listener');
        }

        // Enhanced hover effect
        toggleButton.addEventListener('mouseenter', function() {
            const isCollapsed = toggleButton.classList.contains('collapsed');
            const icon = toggleButton.querySelector('.bi');
            
            if (!isCollapsed) {
                icon.style.transform = 'translateX(-3px)';
            } else {
                icon.style.transform = 'translateX(3px) rotate(180deg)';
            }
        });

        toggleButton.addEventListener('mouseleave', function() {
            const isCollapsed = toggleButton.classList.contains('collapsed');
            const icon = toggleButton.querySelector('.bi');
            
            if (!isCollapsed) {
                icon.style.transform = 'none';
            } else {
                icon.style.transform = 'rotate(180deg)';
            }
        });

        // Handle window resize
        function handleResize() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                toggleButton.classList.add('collapsed');
            } else {
                // Restore saved state on desktop
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === null || savedState === 'false') {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    toggleButton.classList.remove('collapsed');
                }
            }
        }

        window.addEventListener('resize', handleResize);

        // Handle clicks outside sidebar on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                const isClickInside = sidebar.contains(event.target) || 
                                    toggleButton.contains(event.target);
                
                if (!isClickInside && !sidebar.classList.contains('collapsed')) {
                    toggleSidebar();
                }
            }
        });

        // Initial check for mobile devices
        handleResize();
    });
</body>
</html>