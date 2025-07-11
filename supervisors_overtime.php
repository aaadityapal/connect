<?php
// Include database connection
require_once 'config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current month and year
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Format month name for display
$monthName = date('F', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

// Get user ID (for supervisors, they might be viewing other users' data)
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];

// Fetch managers for the dropdown
try {
    $managerQuery = $pdo->prepare("
        SELECT u.id, u.username, u.role 
        FROM users u 
        WHERE u.role IN ('Senior Manager (Studio)', 'Senior Manager (Site)')
        ORDER BY u.role, u.username
    ");
    $managerQuery->execute();
    $managers = $managerQuery->fetchAll();
} catch (PDOException $e) {
    error_log("Manager Query Error: " . $e->getMessage());
    $managers = [];
}

// Prepare date range for the selected month
$startDate = "$currentYear-$currentMonth-01";
$endDate = date('Y-m-t', strtotime($startDate));

// Function to calculate overtime hours
function calculateOvertimeHours($punchOutTime, $shiftEndTime) {
    if (!$punchOutTime || !$shiftEndTime) {
        return 0;
    }
    
    // Convert times to minutes since midnight for easier calculation
    $punchOutMinutes = date('H', strtotime($punchOutTime)) * 60 + date('i', strtotime($punchOutTime));
    $shiftEndMinutes = date('H', strtotime($shiftEndTime)) * 60 + date('i', strtotime($shiftEndTime));
    
    // Calculate difference in minutes
    $diffMinutes = $punchOutMinutes - $shiftEndMinutes;
    
    // If difference is less than 90 minutes (1 hour 30 minutes), no overtime
    if ($diffMinutes < 90) {
        return 0;
    }
    
    // Calculate overtime hours
    $overtimeHours = $diffMinutes / 60;
    
    // Round down to nearest 30-minute interval (0.5 hour)
    // For example:
    // 1.46 hours (1 hour 28 minutes) becomes 1.5 hours
    // 2.24 hours (2 hours 14 minutes) becomes 2.0 hours
    // 2.51 hours (2 hours 31 minutes) becomes 2.5 hours
    
    // First, multiply by 2 to convert to half-hour units
    $halfHourUnits = floor($overtimeHours * 2);
    
    // Then divide by 2 to get back to hours
    return $halfHourUnits / 2;
}

// Fetch overtime data
try {
    // Get user's shift information
    $shiftQuery = $pdo->prepare("
        SELECT s.end_time
        FROM user_shifts us
        JOIN shifts s ON us.shift_id = s.id
        WHERE us.user_id = :userId
        AND (
            (us.effective_to IS NULL OR us.effective_to >= :startDate)
            AND us.effective_from <= :endDate
        )
        ORDER BY us.effective_from DESC
        LIMIT 1
    ");
    $shiftQuery->execute([':userId' => $userId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $shiftData = $shiftQuery->fetch();
    
    $defaultShiftEndTime = $shiftData ? $shiftData['end_time'] : '18:00:00'; // Default to 6:00 PM if no shift found
    
    // Fetch attendance records for the month
    $attendanceQuery = $pdo->prepare("
        SELECT 
            a.id,
            a.date,
            a.punch_in,
            a.punch_out,
            a.work_report,
            a.overtime_status,
            a.overtime_hours,
            s.end_time as shift_end_time
        FROM attendance a
        LEFT JOIN user_shifts us ON a.user_id = us.user_id
            AND (
                (us.effective_to IS NULL OR us.effective_to >= a.date)
                AND us.effective_from <= a.date
            )
        LEFT JOIN shifts s ON us.shift_id = s.id
        WHERE a.user_id = :userId 
        AND DATE(a.date) BETWEEN :startDate AND :endDate
        AND a.punch_out IS NOT NULL
        ORDER BY a.date DESC
    ");
    $attendanceQuery->execute([':userId' => $userId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $attendanceRecords = $attendanceQuery->fetchAll();
    
    // Process attendance records to calculate overtime
    $overtimeRecords = [];
    $totalOvertimeHours = 0;
    $pendingHours = 0;
    $approvedHours = 0;
    $notSubmittedHours = 0;
    
    foreach ($attendanceRecords as $record) {
        // Use the shift end time from the record or default to 6:00 PM
        $shiftEndTime = $record['shift_end_time'] ?? $defaultShiftEndTime;
        
        // Calculate overtime hours based on punch out time and shift end time
        $overtimeHours = calculateOvertimeHours($record['punch_out'], $shiftEndTime);
        
        // If there's overtime, add to the records
        if ($overtimeHours > 0) {
            // Update overtime status counts
            $totalOvertimeHours += $overtimeHours;
            
            if ($record['overtime_status'] === 'pending') {
                $pendingHours += $overtimeHours;
            } elseif ($record['overtime_status'] === 'approved') {
                $approvedHours += $overtimeHours;
            } elseif ($record['overtime_status'] === null) {
                $notSubmittedHours += $overtimeHours;
            }
            
            // Add to overtime records
            $overtimeRecords[] = [
                'id' => $record['id'],
                'date' => $record['date'],
                'overtime_hours' => $overtimeHours,
                'shift_end_time' => $shiftEndTime,
                'punch_out' => $record['punch_out'],
                'work_report' => $record['work_report'],
                'overtime_status' => $record['overtime_status']
            ];
            
            // Update the attendance record with calculated overtime hours if needed
            if ($record['overtime_hours'] === null || $record['overtime_hours'] != $overtimeHours) {
                $updateQuery = $pdo->prepare("
                    UPDATE attendance 
                    SET overtime_hours = :overtimeHours 
                    WHERE id = :id
                ");
                $updateQuery->execute([
                    ':overtimeHours' => $overtimeHours,
                    ':id' => $record['id']
                ]);
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("Overtime Query Error: " . $e->getMessage());
    $error = "An error occurred while fetching overtime data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Overtime Dashboard - <?php echo $monthName . ' ' . $currentYear; ?></title>
    <link rel="shortcut icon" href="images/logo.png" type="image/png">
    <link rel="icon" href="images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f4f8;
            color: #333;
        }
        
        .dashboard {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        h1 {
            font-size: 26px;
            margin-bottom: 24px;
            color: #1e40af;
            font-weight: 600;
        }
        
        h2 {
            font-size: 20px;
            margin: 24px 0 20px;
            color: #1e3a8a;
            font-weight: 600;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }
        
        .stat-box {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .stat-box:nth-child(1)::before {
            background-color: #3b82f6;
        }
        
        .stat-box:nth-child(2)::before {
            background-color: #f59e0b;
        }
        
        .stat-box:nth-child(3)::before {
            background-color: #10b981;
        }
        
        .stat-box:nth-child(4)::before {
            background-color: #8b5cf6;
        }
        
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-box h3 {
            font-size: 15px;
            margin-bottom: 12px;
            color: #64748b;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .stat-box:nth-child(1) .stat-value {
            color: #2563eb;
        }
        
        .stat-box:nth-child(2) .stat-value {
            color: #d97706;
        }
        
        .stat-box:nth-child(3) .stat-value {
            color: #059669;
        }
        
        .stat-box:nth-child(4) .stat-value {
            color: #7c3aed;
        }
        
        .stat-label {
            font-size: 13px;
            color: #94a3b8;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(to right, rgba(59, 130, 246, 0.3), rgba(139, 92, 246, 0.3));
            margin: 30px 0;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 15px;
            min-width: 500px; /* Ensures table doesn't get too compressed */
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        thead {
            background: linear-gradient(to right, #3b82f6, #6366f1);
        }
        
        th {
            text-align: left;
            padding: 16px 20px;
            font-size: 14px;
            color: white;
            border-bottom: none;
            font-weight: 600;
            white-space: nowrap;
            position: relative;
            transition: all 0.2s ease;
        }
        
        th:hover {
            background-color: rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }
        
        th:hover::after {
            content: attr(title);
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        td {
            padding: 16px 20px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px solid #edf2f7;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .no-records {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-size: 15px;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #b45309;
        }
        
        .status-submitted {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .status-approved {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .status-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .status-not-submitted {
            background-color: #f1f5f9;
            color: #475569;
        }
        
        /* Action buttons styles */
        .action-cell {
            white-space: nowrap;
            text-align: center;
            padding: 10px 15px;
        }
        
        th[title="Available actions"] {
            text-align: center;
        }
        
        .action-buttons-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            margin: 0 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: white;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.2);
        }
        
        .action-btn:active {
            transform: translateY(0);
        }
        
        .action-btn i {
            font-size: 14px;
        }
        
        .send-btn {
            background: #4461F2;
        }
        
        .send-btn:hover {
            background: #3b54d3;
        }
        
        .view-btn {
            background: #6366F1;
        }
        
        .view-btn:hover {
            background: #5a5cd6;
        }
        
        .month-selector {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            border: 1px solid #e0f2fe;
        }
        
        .month-selector select {
            padding: 10px 15px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background-color: white;
            font-size: 14px;
            color: #334155;
            min-width: 120px;
            cursor: pointer;
            transition: all 0.2s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 12px) center;
            padding-right: 35px;
        }
        
        .month-selector select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .month-selector button {
            padding: 10px 20px;
            background: linear-gradient(to right, #3b82f6, #6366f1);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(59, 130, 246, 0.3);
        }
        
        .month-selector button:hover {
            background: linear-gradient(to right, #2563eb, #4f46e5);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
        }
        
        .month-selector button:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        /* Ensure main content adjusts to sidebar */
        .main-content {
            transition: margin-left var(--transition-speed) ease;
            padding: 25px;
        }
        
        /* Hamburger Menu Button */
        .hamburger-btn {
            position: fixed;
            top: 15px;
            left: 15px;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: white;
            border: none;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1100;
            box-shadow: 0 2px 10px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }
        
        .hamburger-btn:hover {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .hamburger-btn:active {
            transform: translateY(0);
        }
        
        .hamburger-btn i {
            font-size: 20px;
        }
        
        /* Mobile overlay */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }
        
        .mobile-overlay.active {
            display: block;
        }
        
        /* Adjust left panel for mobile first approach */
        @media (max-width: 768px) {
            .left-panel {
                transform: translateX(-100%);
            }
            
            .left-panel.mobile-visible {
                transform: translateX(0);
            }
        }
        
        /* Toggle button styles */
        .toggle-btn {
            position: absolute;
            right: -18px;
            top: 25px;
            background: white;
            border: none;
            color: #3b82f6;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }
        
        .toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            color: #2563eb;
        }
        
        /* Mobile responsiveness for the dashboard with sidebar */
        @media (max-width: 992px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            h1 {
                font-size: 24px;
            }
            
            h2 {
                font-size: 18px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                padding-top: 70px; /* Space for hamburger button */
            }
            
            .dashboard {
                padding: 20px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .month-selector {
                flex-wrap: wrap;
                gap: 10px;
            }
        }
        
        /* Desktop view */
        @media (min-width: 769px) {
            .left-panel {
                transform: translateX(0);
            }
            
            .hamburger-btn {
                display: none;
            }
            
            .main-content {
                margin-left: var(--panel-width, 280px);
            }
            
            .main-content.collapsed {
                margin-left: var(--panel-collapsed-width, 70px);
            }
            
            .left-panel.collapsed {
                width: var(--panel-collapsed-width, 70px);
            }
            
            .left-panel.collapsed .menu-text {
                display: none;
            }
            
            .left-panel.collapsed .menu-item i {
                width: 100%;
                margin-right: 0;
                font-size: 1.1em;
            }
        }
        
        /* iPad and smaller tablets */
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .dashboard {
                padding: 15px;
                border-radius: 10px;
            }
            
            h1 {
                font-size: 20px;
                margin-bottom: 20px;
            }
            
            h2 {
                font-size: 17px;
                margin: 20px 0 15px;
            }
            
            .stat-box {
                padding: 15px;
            }
            
            .stat-value {
                font-size: 22px;
            }
            
            th, td {
                padding: 12px 15px;
                font-size: 13px;
            }
        }
        
        /* iPhone XR (414px) */
        @media (max-width: 414px) {
            .main-content {
                padding: 15px;
                padding-top: 65px; /* Space for hamburger button */
            }
            
            .dashboard {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .stat-box h3 {
                font-size: 14px;
            }
            
            .stat-value {
                font-size: 20px;
            }
            
            .stat-label {
                font-size: 12px;
            }
            
            th, td {
                padding: 10px 12px;
            }
            
            .hamburger-btn {
                width: 40px;
                height: 40px;
                top: 12px;
                left: 12px;
            }
        }
        
        /* iPhone SE (375px) */
        @media (max-width: 375px) {
            .main-content {
                padding: 12px;
                padding-top: 60px; /* Space for hamburger button */
            }
            
            .dashboard {
                padding: 12px;
            }
            
            h1 {
                font-size: 18px;
                margin-bottom: 15px;
            }
            
            h2 {
                font-size: 16px;
                margin: 15px 0 12px;
            }
            
            .stat-box {
                padding: 12px;
            }
            
            .stat-box h3 {
                font-size: 13px;
                margin-bottom: 10px;
            }
            
            .stat-value {
                font-size: 18px;
                margin-bottom: 4px;
            }
            
            .stat-label {
                font-size: 11px;
            }
            
            .no-records {
                padding: 25px;
                font-size: 14px;
            }
            
            th, td {
                padding: 10px;
                font-size: 12px;
            }
            
            .hamburger-btn {
                width: 36px;
                height: 36px;
                top: 10px;
                left: 10px;
            }
            
            .hamburger-btn i {
                font-size: 16px;
            }
        }
        
        /* Very small screens */
        @media (max-width: 320px) {
            .main-content {
                padding: 10px;
                padding-top: 55px; /* Space for hamburger button */
            }
            
            .dashboard {
                padding: 10px;
            }
            
            h1 {
                font-size: 16px;
            }
            
            .stat-value {
                font-size: 16px;
            }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: white;
            margin: 6% auto;
            width: 90%;
            max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            position: relative;
            animation: modalFadeIn 0.3s ease;
            overflow: hidden;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        
        .modal h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #1e40af;
            display: flex;
            align-items: center;
        }
        
        .modal h2 i {
            margin-right: 10px;
            color: #4461F2;
        }
        
        .close-modal {
            font-size: 18px;
            color: #64748b;
            cursor: pointer;
            transition: color 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #f1f5f9;
        }
        
        .close-modal:hover {
            color: #1e40af;
            background-color: #e2e8f0;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .detail-item {
            display: flex;
            margin-bottom: 24px;
            align-items: flex-start;
        }
        
        .detail-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4461F2, #6366F1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            font-size: 16px;
            box-shadow: 0 3px 8px rgba(99, 102, 241, 0.2);
        }
        
        .detail-content {
            flex: 1;
        }
        
        .detail-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #334155;
        }
        
        .detail-text {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-top: 5px;
            font-size: 15px;
            color: #334155;
            line-height: 1.6;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
            border: 1px solid #e2e8f0;
        }
        
        .modal-footer {
            padding: 15px 25px 25px;
            display: flex;
            justify-content: flex-end;
        }
        
        .modal-close-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #4461F2, #6366F1);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }
        
        .modal-close-btn i {
            margin-right: 8px;
        }
        
        .modal-close-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(99, 102, 241, 0.3);
        }
        
        /* Send Modal Styles */
        .close-send-modal {
            font-size: 18px;
            color: #64748b;
            cursor: pointer;
            transition: color 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #f1f5f9;
        }
        
        .close-send-modal:hover {
            color: #1e40af;
            background-color: #e2e8f0;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            color: #334155;
            background-color: #f8fafc;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4461F2;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            background-color: #fff;
        }
        
        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 15px) center;
            padding-right: 40px;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
            line-height: 1.6;
        }
        
        .checkbox-container {
            margin-top: 30px;
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: #f8fafc;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            position: relative;
            cursor: pointer;
            padding: 5px 0;
        }
        
        .checkbox-label input[type="checkbox"] {
            opacity: 0;
            position: absolute;
            cursor: pointer;
            height: 24px;
            width: 24px;
            z-index: 1;
        }
        
        .checkbox-custom {
            width: 24px;
            height: 24px;
            background-color: white;
            border: 2px solid #4461F2;
            border-radius: 6px;
            margin-right: 12px;
            position: relative;
            transition: all 0.2s ease;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .checkbox-label input[type="checkbox"]:checked ~ .checkbox-custom {
            background-color: #4461F2;
            border-color: #4461F2;
        }
        
        .checkbox-label input[type="checkbox"]:checked ~ .checkbox-custom:after {
            content: '';
            position: absolute;
            left: 7px;
            top: 3px;
            width: 6px;
            height: 12px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .checkbox-text {
            font-size: 15px;
            color: #334155;
            line-height: 1.5;
            font-weight: 500;
        }
        
        .error-message {
            color: #dc2626;
            font-size: 13px;
            margin-top: 6px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .modal-footer {
            padding: 15px 25px 25px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .modal-cancel-btn {
            padding: 12px 20px;
            background-color: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }
        
        .modal-cancel-btn i {
            margin-right: 8px;
        }
        
        .modal-cancel-btn:hover {
            background-color: #e2e8f0;
            color: #334155;
        }
        
        .modal-submit-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #4461F2, #6366F1);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }
        
        .modal-submit-btn i {
            margin-right: 8px;
        }
        
        .modal-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(99, 102, 241, 0.3);
        }
        
        .modal-submit-btn:active {
            transform: translateY(0);
        }
        
        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 3000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: opacity 0.3s ease;
            max-width: 350px;
        }
        
        .notification.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
        }
        
        .notification.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
    </style>
</head>
<body>
    <!-- Hamburger menu button -->
    <button class="hamburger-btn" id="hamburgerBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <?php include 'includes/supervisor_panel.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="dashboard">
            <h1>Overtime Dashboard - <?php echo $monthName . ' ' . $currentYear; ?></h1>
            
            <!-- Month Selector -->
            <div class="month-selector">
                <form action="" method="get" id="monthForm" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <select name="month" id="monthSelect">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <select name="year" id="yearSelect">
                        <?php 
                        $startYear = 2023;
                        $endYear = date('Y') + 1;
                        for ($y = $startYear; $y <= $endYear; $y++): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <button type="submit">View</button>
                </form>
            </div>
            
            <h2>Monthly Overview</h2>
            
            <div class="stats-container">
                <div class="stat-box">
                    <h3>Total Hours</h3>
                    <div class="stat-value" id="total-hours"><?php echo number_format($totalOvertimeHours, 1); ?></div>
                    <div class="stat-label">Hours Worked</div>
                </div>
                
                <div class="stat-box">
                    <h3>Pending Approval</h3>
                    <div class="stat-value" id="pending-approval"><?php echo number_format($pendingHours, 1); ?></div>
                    <div class="stat-label">Hours Awaiting</div>
                </div>
                
                <div class="stat-box">
                    <h3>Approved Hours</h3>
                    <div class="stat-value" id="approved-hours"><?php echo number_format($approvedHours, 1); ?></div>
                    <div class="stat-label">This Month</div>
                </div>
                
                <div class="stat-box">
                    <h3>Overtime Left For Approval</h3>
                    <div class="stat-value" id="not-submitted"><?php echo number_format($notSubmittedHours, 1); ?></div>
                    <div class="stat-label">Hours Needing submission</div>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <h2><?php echo $monthName . ' ' . $currentYear; ?> Overtime Records</h2>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th title="Date of overtime">Date</th>
                            <th title="Overtime hours worked">Hours</th>
                            <th title="Employee's shift end time">Shift End Time</th>
                            <th title="Actual punch out time">Punch Out Time</th>
                            <th title="Employee's work report">Work Report</th>
                            <th title="Current approval status">Status</th>
                            <th title="Available actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($error)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="no-records"><?php echo $error; ?></div>
                                </td>
                            </tr>
                        <?php elseif (empty($overtimeRecords)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="no-records">No overtime records found for this month.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($overtimeRecords as $record): ?>
                                <tr>
                                    <td><?php echo date('d M, Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo number_format($record['overtime_hours'], 1); ?></td>
                                    <td><?php echo date('h:i A', strtotime($record['shift_end_time'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($record['punch_out'])); ?></td>
                                    <td><?php echo $record['work_report'] ? htmlspecialchars(substr($record['work_report'], 0, 50)) . (strlen($record['work_report']) > 50 ? '...' : '') : 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        $status = $record['overtime_status'] ?? 'not-submitted';
                                        $statusClass = 'status-' . $status;
                                        $statusText = ucfirst($status);
                                        if ($status === 'not-submitted') {
                                            $statusText = 'Not Submitted';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td class="action-cell">
                                        <div class="action-buttons-container">
                                            <button class="action-btn send-btn" data-id="<?php echo $record['id']; ?>" title="Send">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                            <button class="action-btn view-btn" data-id="<?php echo $record['id']; ?>" title="View">
                                                <i class="fas fa-eye"></i>
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

    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <!-- Overtime Details Modal -->
    <div id="overtimeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-clock"></i> Overtime Details</h2>
                <span class="close-modal"><i class="fas fa-times"></i></span>
            </div>
            <div class="modal-body">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="detail-content">
                        <span class="detail-label">Date</span>
                        <span class="detail-value" id="modal-date"></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="detail-content">
                        <span class="detail-label">Overtime Hours</span>
                        <span class="detail-value" id="modal-hours"></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="detail-content">
                        <span class="detail-label">Work Report</span>
                        <div class="detail-text" id="modal-report"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-close-btn"><i class="fas fa-check"></i> Close</button>
            </div>
        </div>
    </div>
    
    <!-- Overtime Send Modal -->
    <div id="sendOvertimeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-paper-plane"></i> Submit Overtime Request</h2>
                <span class="close-send-modal"><i class="fas fa-times"></i></span>
            </div>
            <div class="modal-body">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="detail-content">
                        <span class="detail-label">Date</span>
                        <span class="detail-value" id="send-modal-date"></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="detail-content">
                        <span class="detail-label">Overtime Hours</span>
                        <span class="detail-value" id="send-modal-hours"></span>
                    </div>
                </div>
                
                <div class="form-group checkbox-container">
                    <label class="checkbox-label">
                        <input type="checkbox" id="approvalConfirmation" required>
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">I confirm that I have approval for this overtime from my manager</span>
                    </label>
                    <div class="error-message" id="checkbox-error">This confirmation is required</div>
                </div>
                
                <div class="form-group">
                    <label for="managerSelect">Select Manager</label>
                    <select id="managerSelect" class="form-control" required>
                        <option value="">-- Select Manager --</option>
                        <?php if (!empty($managers)): ?>
                            <?php foreach ($managers as $manager): ?>
                                <option value="<?php echo $manager['id']; ?>" <?php echo ($manager['role'] === 'Senior Manager (Site)') ? 'selected' : ''; ?>><?php echo htmlspecialchars($manager['username']); ?> - <?php echo htmlspecialchars($manager['role']); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="studio_manager">Senior Manager (Studio)</option>
                            <option value="site_manager" selected>Senior Manager (Site)</option>
                        <?php endif; ?>
                    </select>
                    <div class="error-message" id="manager-error">Please select a manager</div>
                </div>
                
                <div class="form-group">
                    <label for="overtimeDescription">Overtime Description</label>
                    <textarea id="overtimeDescription" class="form-control" rows="4" placeholder="Please describe what work was done during the overtime hours..." required></textarea>
                    <div class="error-message" id="description-error">Please provide a description</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-cancel-btn" id="cancelSendBtn"><i class="fas fa-times"></i> Cancel</button>
                <button class="modal-submit-btn" id="submitOvertimeBtn"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </div>
        </div>
    </div>

    <script>
        // Toggle panel functionality
        function togglePanel() {
            const leftPanel = document.getElementById('leftPanel');
            const mainContent = document.getElementById('mainContent');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const toggleIcon = document.getElementById('toggleIcon');
            
            // For desktop view
            if (window.innerWidth >= 769) {
                leftPanel.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                
                // Change toggle icon direction
                if (toggleIcon && toggleIcon.classList.contains('fa-chevron-left')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else if (toggleIcon) {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            } 
            // For mobile view
            else {
                leftPanel.classList.toggle('mobile-visible');
                mobileOverlay.classList.toggle('active');
                
                // Toggle hamburger icon
                if (hamburgerBtn.querySelector('i').classList.contains('fa-bars')) {
                    hamburgerBtn.querySelector('i').classList.remove('fa-bars');
                    hamburgerBtn.querySelector('i').classList.add('fa-times');
                } else {
                    hamburgerBtn.querySelector('i').classList.remove('fa-times');
                    hamburgerBtn.querySelector('i').classList.add('fa-bars');
                }
            }
        }
        
        // Add event listener to hamburger button
        document.getElementById('hamburgerBtn').addEventListener('click', togglePanel);
        
        // Close panel when clicking on overlay
        document.getElementById('mobileOverlay').addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                togglePanel();
            }
        });
        
        // Handle table responsiveness on small screens
        function checkTableOverflow() {
            const tableContainers = document.querySelectorAll('.table-responsive');
            tableContainers.forEach(container => {
                const table = container.querySelector('table');
                if (table && table.offsetWidth > container.offsetWidth) {
                    container.classList.add('has-overflow');
                } else {
                    container.classList.remove('has-overflow');
                }
            });
        }
        
        // Check on load and resize
        window.addEventListener('load', checkTableOverflow);
        window.addEventListener('resize', checkTableOverflow);
        
        document.addEventListener('DOMContentLoaded', function() {
            // Month selector change event
            document.getElementById('monthSelect').addEventListener('change', function() {
                document.getElementById('monthForm').submit();
            });
            
            document.getElementById('yearSelect').addEventListener('change', function() {
                document.getElementById('monthForm').submit();
            });
            
            // Action button handlers
            const sendButtons = document.querySelectorAll('.send-btn');
            const viewButtons = document.querySelectorAll('.view-btn');
            const detailsModal = document.getElementById('overtimeModal');
            const sendModal = document.getElementById('sendOvertimeModal');
            const closeModal = document.querySelector('.close-modal');
            const closeSendModal = document.querySelector('.close-send-modal');
            
            // Store overtime records data for quick access
            const overtimeData = <?php echo json_encode($overtimeRecords ?? []); ?>;
            
            // Current selected record for submissions
            let currentRecord = null;
            
            sendButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const recordId = parseInt(this.getAttribute('data-id'));
                    
                    // Find the overtime record with the matching ID
                    const record = overtimeData.find(record => record.id == recordId);
                    currentRecord = record;
                    
                    if (record) {
                        // Format the date
                        const date = new Date(record.date);
                        const formattedDate = date.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        
                        // Populate modal with record data
                        document.getElementById('send-modal-date').textContent = formattedDate;
                        document.getElementById('send-modal-hours').textContent = record.overtime_hours.toFixed(1) + ' hours';
                        
                        // Reset form values
                        document.getElementById('approvalConfirmation').checked = false;
                        // The dropdown will already have Senior Manager (Site) selected by default from HTML
                        document.getElementById('overtimeDescription').value = record.work_report || '';
                        
                        // Reset error messages
                        document.querySelectorAll('.error-message').forEach(el => el.classList.remove('show'));
                        
                        // Reset submit button to original state (ensure it's not in "Submitting..." state)
                        const submitBtn = document.getElementById('submitOvertimeBtn');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
                        
                        // Show the modal
                        sendModal.style.display = 'block';
                        document.body.style.overflow = 'hidden'; // Prevent scrolling
                    }
                });
            });
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const recordId = parseInt(this.getAttribute('data-id'));
                    
                    // Find the overtime record with the matching ID
                    const record = overtimeData.find(record => record.id == recordId);
                    
                    if (record) {
                        // Format the date
                        const date = new Date(record.date);
                        const formattedDate = date.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        
                        // Populate modal with record data
                        document.getElementById('modal-date').textContent = formattedDate;
                        document.getElementById('modal-hours').textContent = record.overtime_hours.toFixed(1) + ' hours';
                        document.getElementById('modal-report').textContent = record.work_report || 'No work report submitted';
                        
                        // Show the modal
                        detailsModal.style.display = 'block';
                        document.body.style.overflow = 'hidden'; // Prevent scrolling
                    }
                });
            });
            
            // Handle form submission
            document.getElementById('submitOvertimeBtn').addEventListener('click', function() {
                // Validate form
                let isValid = true;
                
                // Check approval confirmation
                const approvalCheckbox = document.getElementById('approvalConfirmation');
                if (!approvalCheckbox.checked) {
                    document.getElementById('checkbox-error').classList.add('show');
                    isValid = false;
                } else {
                    document.getElementById('checkbox-error').classList.remove('show');
                }
                
                // Check manager selection
                const managerSelect = document.getElementById('managerSelect');
                if (!managerSelect.value) {
                    document.getElementById('manager-error').classList.add('show');
                    isValid = false;
                } else {
                    document.getElementById('manager-error').classList.remove('show');
                }
                
                // Check description
                const description = document.getElementById('overtimeDescription');
                if (!description.value.trim()) {
                    document.getElementById('description-error').classList.add('show');
                    isValid = false;
                } else {
                    document.getElementById('description-error').classList.remove('show');
                }
                
                // If valid, submit the form
                if (isValid && currentRecord) {
                    // Disable the submit button and show loading state
                    const submitBtn = document.getElementById('submitOvertimeBtn');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                    
                    // Prepare data for submission
                    const formData = new FormData();
                    formData.append('action', 'submit_overtime');
                    formData.append('overtime_id', currentRecord.id);
                    formData.append('manager_id', managerSelect.value);
                    formData.append('message', description.value);
                    
                    // Send AJAX request
                    fetch('ajax_handlers/overtime_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            showNotification('Overtime request submitted successfully!', 'success');
                            
                            // Instead of manually updating the UI, reload the page content while preserving filters
                            const currentMonth = document.getElementById('monthSelect').value;
                            const currentYear = document.getElementById('yearSelect').value;
                            
                            // Create URL with current filters
                            const url = `supervisors_overtime.php?month=${currentMonth}&year=${currentYear}`;
                            
                            // Fetch the updated page content
                            fetch(url)
                                .then(response => response.text())
                                .then(html => {
                                    // Create a temporary element to parse the HTML
                                    const tempDiv = document.createElement('div');
                                    tempDiv.innerHTML = html;
                                    
                                    // Extract the main content
                                    const newContent = tempDiv.querySelector('.main-content').innerHTML;
                                    
                                    // Update just the main content area
                                    document.getElementById('mainContent').innerHTML = newContent;
                                    
                                    // Reattach event listeners to the new elements
                                    reattachEventListeners();
                                    
                                    // Close the modal
                                    sendModal.style.display = 'none';
                                    document.body.style.overflow = '';
                                })
                                .catch(error => {
                                    console.error('Error refreshing page content:', error);
                                    // Fallback to full page reload if the partial refresh fails
                                    window.location.href = url;
                                });
                        } else {
                            showNotification('Error: ' + (data.message || 'Failed to submit request'), 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
                        }
                    })
                    .catch(error => {
                        console.error('Error submitting overtime request:', error);
                        showNotification('Error submitting request. Please try again.', 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
                    });
                }
            });
            
            // Close details modal when clicking the close button or Close button in footer
            closeModal.addEventListener('click', function() {
                detailsModal.style.display = 'none';
                document.body.style.overflow = '';
            });
            
            // Close details modal with footer button
            document.querySelector('.modal-close-btn').addEventListener('click', function() {
                detailsModal.style.display = 'none';
                document.body.style.overflow = '';
            });
            
            // Close send modal handlers
            closeSendModal.addEventListener('click', function() {
                sendModal.style.display = 'none';
                document.body.style.overflow = '';
            });
            
            document.getElementById('cancelSendBtn').addEventListener('click', function() {
                sendModal.style.display = 'none';
                document.body.style.overflow = '';
            });
            
            // Close modals when clicking outside the modal content
            window.addEventListener('click', function(event) {
                if (event.target == detailsModal) {
                    detailsModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
                if (event.target == sendModal) {
                    sendModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
            
            // Close modals with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (detailsModal.style.display === 'block') {
                        detailsModal.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                    if (sendModal.style.display === 'block') {
                        sendModal.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                }
            });
            
            // Function to show notifications
            function showNotification(message, type) {
                // Create notification element if it doesn't exist
                let notification = document.getElementById('notification');
                if (!notification) {
                    notification = document.createElement('div');
                    notification.id = 'notification';
                    document.body.appendChild(notification);
                }
                
                // Set content and style based on type
                notification.textContent = message;
                notification.className = `notification ${type}`;
                
                // Show notification
                notification.style.display = 'block';
                
                // Auto hide after 4 seconds
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.style.display = 'none';
                        notification.style.opacity = '1';
                    }, 300);
                }, 4000);
            }

            // Function to reattach event listeners after content refresh
            function reattachEventListeners() {
                // Month selector change event
                document.getElementById('monthSelect').addEventListener('change', function() {
                    document.getElementById('monthForm').submit();
                });
                
                document.getElementById('yearSelect').addEventListener('change', function() {
                    document.getElementById('monthForm').submit();
                });
                
                // Action button handlers
                const sendButtons = document.querySelectorAll('.send-btn');
                const viewButtons = document.querySelectorAll('.view-btn');
                
                // Reset submit button state to original
                const submitBtn = document.getElementById('submitOvertimeBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
                }
                
                sendButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const recordId = parseInt(this.getAttribute('data-id'));
                        
                        // Find the overtime record with the matching ID
                        const record = overtimeData.find(record => record.id == recordId);
                        currentRecord = record;
                        
                        if (record) {
                            // Format the date
                            const date = new Date(record.date);
                            const formattedDate = date.toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                            
                            // Populate modal with record data
                            document.getElementById('send-modal-date').textContent = formattedDate;
                            document.getElementById('send-modal-hours').textContent = record.overtime_hours.toFixed(1) + ' hours';
                            
                            // Reset form values
                            document.getElementById('approvalConfirmation').checked = false;
                            // The dropdown will already have Senior Manager (Site) selected by default from HTML
                            document.getElementById('overtimeDescription').value = record.work_report || '';
                            
                            // Reset error messages
                            document.querySelectorAll('.error-message').forEach(el => el.classList.remove('show'));
                            
                            // Reset submit button to original state (ensure it's not in "Submitting..." state)
                            const submitBtn = document.getElementById('submitOvertimeBtn');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
                            
                            // Show the modal
                            sendModal.style.display = 'block';
                            document.body.style.overflow = 'hidden'; // Prevent scrolling
                        }
                    });
                });
                
                viewButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const recordId = parseInt(this.getAttribute('data-id'));
                        
                        // Find the overtime record with the matching ID
                        const record = overtimeData.find(record => record.id == recordId);
                        
                        if (record) {
                            // Format the date
                            const date = new Date(record.date);
                            const formattedDate = date.toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                            
                            // Populate modal with record data
                            document.getElementById('modal-date').textContent = formattedDate;
                            document.getElementById('modal-hours').textContent = record.overtime_hours.toFixed(1) + ' hours';
                            document.getElementById('modal-report').textContent = record.work_report || 'No work report submitted';
                            
                            // Show the modal
                            detailsModal.style.display = 'block';
                            document.body.style.overflow = 'hidden'; // Prevent scrolling
                        }
                    });
                });
                
                // Update the overtimeData array with the latest data
                updateOvertimeData();
            }
            
            // Function to update the overtimeData array after page refresh
            function updateOvertimeData() {
                // Extract overtime data from the table rows
                const tableRows = document.querySelectorAll('table tbody tr');
                const newOvertimeData = [];
                
                tableRows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length > 0) {
                        const sendButton = row.querySelector('.send-btn');
                        if (sendButton) {
                            const id = parseInt(sendButton.getAttribute('data-id'));
                            const date = cells[0].textContent;
                            const overtime_hours = parseFloat(cells[1].textContent);
                            const shift_end_time = cells[2].textContent;
                            const punch_out = cells[3].textContent;
                            const work_report = cells[4].textContent === 'N/A' ? '' : cells[4].textContent;
                            
                            // Get status from badge class
                            const statusBadge = cells[5].querySelector('.status-badge');
                            let overtime_status = 'not-submitted';
                            if (statusBadge) {
                                if (statusBadge.classList.contains('status-pending')) overtime_status = 'pending';
                                else if (statusBadge.classList.contains('status-submitted')) overtime_status = 'submitted';
                                else if (statusBadge.classList.contains('status-approved')) overtime_status = 'approved';
                                else if (statusBadge.classList.contains('status-rejected')) overtime_status = 'rejected';
                            }
                            
                            newOvertimeData.push({
                                id,
                                date,
                                overtime_hours,
                                shift_end_time,
                                punch_out,
                                work_report,
                                overtime_status
                            });
                        }
                    }
                });
                
                // Update the global overtimeData array
                if (newOvertimeData.length > 0) {
                    overtimeData.length = 0; // Clear the array
                    newOvertimeData.forEach(record => overtimeData.push(record));
                }
            }
        });
    </script>
</body>
</html>