<?php
session_start();
// Include database connection and shift functions
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

// Get current user ID from session (assuming it's stored there)
// Only default to a test user if no user is logged in
$user_id = $_SESSION['user_id'] ?? null;

// Initialize variables
$user_shift = null;
$shift_end_time = 'N/A';
$shift_name = 'N/A';
$work_reports = [];
$overtime_data = [];
$user_role = 'N/A';

// Get filter parameters
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n') - 1; // 0-11, default to current month
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y'); // default to current year

// Initialize statistics variables
$pending_requests = 0;
$approved_hours = 0;
$rejected_requests = 0;
$estimated_cost = 0;

if ($user_id) {
    // Fetch user's role
    $user_role = getUserRole($pdo, $user_id);
    
    // Fetch user's shift end time
    $user_shift = getUserShiftEndTime($pdo, $user_id);
    if ($user_shift) {
        $shift_end_time = convertTo12HourFormat($user_shift['end_time']);
        $shift_name = $user_shift['shift_name'];
    } else {
        $shift_end_time = 'No shift assigned';
        $shift_name = 'N/A';
    }
    
    // Fetch overtime data based on filters
    $overtime_data = getOvertimeData($pdo, $user_id, $filter_month, $filter_year, $user_shift['end_time'] ?? '18:00:00');
    
    // Calculate statistics
    foreach ($overtime_data as $record) {
        $status = strtolower($record['status']);
        
        // For approved records, use accepted overtime hours instead of calculated hours
        if ($status === 'approved' && !empty($record['accepted_overtime_hours'])) {
            $hours = floatval($record['accepted_overtime_hours']);
        } else {
            $hours = floatval($record['ot_hours']);
        }
        
        switch ($status) {
            case 'pending':
                $pending_requests++;
                break;
            case 'approved':
                $approved_hours += $hours;
                break;
            case 'rejected':
                $rejected_requests++;
                break;
        }
    }
    
    // Calculate estimated cost based on approved hours (assuming $15/hour rate)
    $hourly_rate = 15;
    $estimated_cost = $approved_hours * $hourly_rate;
} else {
    // If no user is logged in, show sample data
    $overtime_data = getSampleOvertimeData();
    
    // Sample statistics
    $pending_requests = 12;
    $approved_hours = 142.5;
    $rejected_requests = 3;
    $estimated_cost = 4275.00;
}

/**
 * Get user's role from the database
 */
function getUserRole($pdo, $user_id) {
    try {
        $query = "SELECT role FROM users WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['role'] : 'N/A';
    } catch (Exception $e) {
        error_log("Error fetching user role: " . $e->getMessage());
        return 'N/A';
    }
}

/**
 * Get overtime data for a user based on month/year filters
 */
function getOvertimeData($pdo, $user_id, $month, $year, $shift_end_time) {
    try {
        // Calculate the first and last day of the selected month
        $first_day = sprintf('%04d-%02d-01', $year, $month + 1);
        $last_day = date('Y-m-t', strtotime($first_day));
        
        // Query to fetch overtime data with the new rule:
        // Only show records where punch_out time is at least 1.5 hours after shift end time
        $query = "SELECT 
                    a.id as attendance_id,
                    a.date,
                    a.punch_out,
                    a.overtime_hours,
                    a.work_report,
                    a.overtime_status,
                    o.status as request_status,
                    o.overtime_description,
                    o.overtime_hours as accepted_overtime_hours
                  FROM attendance a
                  LEFT JOIN overtime_requests o ON a.id = o.attendance_id
                  WHERE a.user_id = ? 
                  AND a.date BETWEEN ? AND ?
                  AND a.overtime_status IS NOT NULL
                  AND TIME_TO_SEC(a.punch_out) >= TIME_TO_SEC(?) + 5400
                  ORDER BY a.date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $first_day, $last_day, $shift_end_time]);
        
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Calculate overtime hours dynamically based on shift end time and punch out time
            $calculated_ot_hours = calculateOvertimeHours($shift_end_time, $row['punch_out']);
            
            // Determine the correct status to display
            // Priority: 1. overtime_requests.status if exists, 2. attendance.overtime_status
            $status = 'Pending';
            if (!empty($row['request_status'])) {
                // Use status from overtime_requests table if available
                $status = ucfirst($row['request_status']);
            } else if (!empty($row['overtime_status'])) {
                // Fallback to attendance.overtime_status
                $status = ucfirst($row['overtime_status']);
            }
            
            // Check if there's a corresponding overtime request in the overtime_requests table
            $overtime_description = '';
            
            // Check if the record is expired (older than 15 days)
            $record_date = new DateTime($row['date']);
            $nov_2025 = new DateTime('2025-11-01');
            $current_date = new DateTime();
            $interval = $record_date->diff($current_date);
            $days_old = $interval->days;
            $is_expired = $days_old > 15;
            
            // If record is expired AND status is pending, set status to Expired
            // For submitted, approved, or rejected records, do not show expired status
            $status_lower = strtolower($status);
            if ($is_expired && $status_lower === 'pending') {
                $status = 'Expired';
                $overtime_description = 'Overtime request period has expired (older than 15 days).';
            } else if ($record_date >= $nov_2025) {
                // For records from November 2025 and later, if an overtime request exists, 
                // use its status and description
                if ($status_lower !== 'expired') { // Only check if not already expired
                    if (!empty($row['request_status'])) {
                        // Use the description from overtime_requests table
                        $overtime_description = $row['overtime_description'] ?? '';
                    } else if ($status_lower === 'pending') {
                        // For pending status with no submission, show appropriate message
                        $overtime_description = 'Overtime not yet submitted. Please submit your overtime request.';
                    }
                }
            } else {
                // For records before November 2025, fetch message from overtime_notifications table
                if ($status_lower !== 'expired') { // Only check if not already expired
                    $check_notification_query = "SELECT message FROM overtime_notifications WHERE overtime_id = ? LIMIT 1";
                    $check_notification_stmt = $pdo->prepare($check_notification_query);
                    $check_notification_stmt->execute([$row['attendance_id']]);
                    $notification_result = $check_notification_stmt->fetch();
                    
                    if ($notification_result) {
                        $overtime_description = $notification_result['message'];
                    } else if ($status_lower === 'pending') {
                        // For pending status with no notification, show appropriate message
                        $overtime_description = 'Overtime details not yet provided.';
                    }
                }
            }
            
            $data[] = [
                'attendance_id' => $row['attendance_id'],
                'date' => $row['date'],
                'punch_out_time' => convertTo12HourFormat($row['punch_out']) ?? 'N/A',
                'ot_hours' => $calculated_ot_hours,
                'work_report' => $row['work_report'] ?? '',
                'overtime_description' => $overtime_description,
                'status' => $status,
                'accepted_overtime_hours' => $row['accepted_overtime_hours'] ?? null
            ];
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error fetching overtime data: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate overtime hours based on shift end time and punch out time
 */
function calculateOvertimeHours($shiftEndTime, $punchOutTime) {
    if (!$shiftEndTime || !$punchOutTime) {
        return '0.0';
    }
    
    // Convert times to seconds
    $shiftEndSeconds = timeToSeconds($shiftEndTime);
    $punchOutSeconds = timeToSeconds($punchOutTime);
    
    // Calculate overtime in seconds
    $overtimeSeconds = 0;
    
    // Only calculate overtime if punch out time is after shift end time
    if ($punchOutSeconds > $shiftEndSeconds) {
        // Same day punch out - calculate overtime as difference
        $overtimeSeconds = $punchOutSeconds - $shiftEndSeconds;
    }
    // If punchOutSeconds <= shiftEndSeconds, overtimeSeconds remains 0
    
    // If no overtime, return 0
    if ($overtimeSeconds <= 0) {
        return '0.0';
    }
    
    // Convert seconds to minutes
    $overtimeMinutes = $overtimeSeconds / 60;
    
    // Apply rounding logic:
    // - If less than 90 minutes (1.5 hours), return 1.5 (minimum threshold)
    // - Otherwise, round down to nearest 30-minute increment
    $roundedHours = roundOvertimeHours($overtimeMinutes);
    
    return number_format($roundedHours, 1, '.', '');
}

/**
 * Round overtime hours according to the specified rules:
 * - Minimum 1.5 hours
 * - Round down to nearest 30-minute increment
 */
function roundOvertimeHours($minutes) {
    // If less than 1.5 hours (90 minutes), return 1.5 (minimum threshold)
    if ($minutes < 90) {
        return 1.5;
    }
    
    // For 1.5 hours and above:
    // Round down to the nearest 30-minute increment
    // First, subtract 1.5 hours (90 minutes) from the total
    $adjustedMinutes = $minutes - 90;
    
    // Then round down to nearest 30-minute increment
    $roundedAdjusted = floor($adjustedMinutes / 30) * 30;
    
    // Add back the 1.5 hours base
    $finalMinutes = 90 + $roundedAdjusted;
    
    // Convert back to hours
    $finalHours = $finalMinutes / 60;
    
    return $finalHours;
}

/**
 * Convert TIME format (HH:MM:SS) to seconds
 */
function timeToSeconds($time) {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

/**
 * Convert 24-hour format to 12-hour AM/PM format
 */
function convertTo12HourFormat($time) {
    if (!$time || $time === 'N/A' || $time === 'No shift assigned') {
        return $time;
    }
    
    // Parse the time
    $timeParts = explode(':', $time);
    if (count($timeParts) < 2) {
        return $time;
    }
    
    $hours = (int)$timeParts[0];
    $minutes = $timeParts[1];
    
    // Determine AM/PM
    $period = ($hours >= 12) ? 'PM' : 'AM';
    
    // Convert hours to 12-hour format
    if ($hours == 0) {
        $hours = 12;
    } else if ($hours > 12) {
        $hours = $hours - 12;
    }
    
    return sprintf('%d:%s %s', $hours, $minutes, $period);
}

/**
 * Get sample data for when no user is logged in
 */
function getSampleOvertimeData() {
    return [
        [
            'attendance_id' => 1,
            'date' => '2025-10-28',
            'punch_out_time' => convertTo12HourFormat('22:00'),
            'ot_hours' => '4.0',
            'work_report' => 'Completed backend API integration',
            'overtime_description' => 'Overtime details not yet provided.',
            'status' => 'Pending',
            'accepted_overtime_hours' => null
        ],
        [
            'attendance_id' => 2,
            'date' => '2025-10-27',
            'punch_out_time' => convertTo12HourFormat('20:00'),
            'ot_hours' => '2.5',
            'work_report' => 'Resolved customer support tickets',
            'overtime_description' => 'System deployment and testing',
            'status' => 'Approved',
            'accepted_overtime_hours' => '2.5'
        ],
        [
            'attendance_id' => 3,
            'date' => '2025-10-27',
            'punch_out_time' => convertTo12HourFormat('21:00'),
            'ot_hours' => '3.0',
            'work_report' => 'Database optimization tasks',
            'overtime_description' => 'Overtime details not yet provided.',
            'status' => 'Pending',
            'accepted_overtime_hours' => null
        ],
        [
            'attendance_id' => 4,
            'date' => '2025-10-26',
            'punch_out_time' => convertTo12HourFormat('18:30'),
            'ot_hours' => '1.5',
            'work_report' => 'Weekly report compilation',
            'overtime_description' => 'System deployment and testing',
            'status' => 'Rejected',
            'accepted_overtime_hours' => '1.0'
        ],
        [
            'attendance_id' => 5,
            'date' => '2025-09-15',
            'punch_out_time' => convertTo12HourFormat('19:00'),
            'ot_hours' => '2.0',
            'work_report' => 'Client meeting and presentation',
            'overtime_description' => 'Overtime request period has expired (older than 15 days).',
            'status' => 'Expired',
            'accepted_overtime_hours' => null
        ],
        [
            'attendance_id' => 6,
            'date' => '2025-10-30',
            'punch_out_time' => convertTo12HourFormat('20:30'),
            'ot_hours' => '3.5',
            'work_report' => 'Project deployment and testing',
            'overtime_description' => 'Completed server migration and database optimization tasks.',
            'status' => 'Submitted',
            'accepted_overtime_hours' => '3.0'
        ]
    ];
}

/**
 * Convert TIME format (HH:MM:SS) to decimal hours
 */
function formatTimeToHours($time) {
    if (!$time) return '0.0';
    list($hours, $minutes, $seconds) = explode(':', $time);
    return number_format($hours + ($minutes / 60) + ($seconds / 3600), 1);
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date('Y-m-d', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Approval Page</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Apply Inter font */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom modal transition */
        #confirmation-modal {
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }
        /* Custom spinner */
        .spinner {
            border: 2px solid rgba(0,0,0,0.1);
            border-left-color: #4f46e5;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Fix for content overflow */
        .msb-content {
            transition: margin-left 0.22s ease, width 0.22s ease;
            width: calc(100% - var(--msb-width-collapsed, 64px));
            overflow-x: hidden;
            margin-left: var(--msb-width-collapsed, 64px);
        }
        .msb-content.is-expanded {
            width: calc(100% - var(--msb-width, 240px));
            margin-left: var(--msb-width, 240px);
        }
        /* Ensure the table container doesn't cause overflow */
        .table-container {
            overflow-x: auto;
            width: 100%;
        }
        /* Prevent content shift on sidebar toggle */
        body {
            overflow-x: hidden;
        }
        /* Clickable report cells */
        .work-report-cell, .overtime-report-cell {
            cursor: pointer;
        }
        .work-report-cell:hover, .overtime-report-cell:hover {
            background-color: #f9fafb;
            text-decoration: underline;
        }
        /* Enhanced header gradient */
        .bg-gradient-to-r {
            background: linear-gradient(90deg, #2563eb 0%, #4f46e5 100%);
        }
        /* Card hover effects */
        .transition-all {
            transition: all 0.3s ease;
        }
        /* Button hover effects */
        .transform {
            transition: transform 0.15s ease;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/minimal_sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="msb-content is-expanded" id="mainContent">
        <!-- 1. Header -->
        <header class="bg-gradient-to-r from-blue-600 to-indigo-700 shadow-lg">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-white">
                            Overtime Management System
                        </h1>
                        <p class="mt-1 text-blue-100">
                            Track, manage, and approve employee overtime requests
                        </p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="flex items-center space-x-4">
                            <div class="bg-blue-500 rounded-lg px-4 py-2 text-white">
                                <div class="text-xs opacity-80">Current User</div>
                                <div class="font-medium"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></div>
                            </div>
                            <div class="bg-indigo-500 rounded-lg px-4 py-2 text-white">
                                <div class="text-xs opacity-80">Today</div>
                                <div class="font-medium"><?php echo date('M d, Y'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- 2. Filters -->
        <section class="mb-6 p-6 bg-white rounded-xl shadow-lg mt-8 border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter Overtime Records
                </h2>
                <div class="text-sm text-gray-500 mt-2 md:mt-0">
                    Select month and year to view overtime records
                </div>
            </div>
            <div class="flex flex-col md:flex-row md:items-center md:space-x-6 space-y-4 md:space-y-0">
                <!-- Month Filter -->
                <div class="flex-1">
                    <label for="month-select" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <svg class="w-4 h-4 mr-1 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Month
                    </label>
                    <select id="month-select" name="month" class="block w-full p-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <!-- Options will be populated by JS -->
                    </select>
                </div>
                <!-- Year Filter -->
                <div class="flex-1">
                    <label for="year-select" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <svg class="w-4 h-4 mr-1 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Year
                    </label>
                    <select id="year-select" name="year" class="block w-full p-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <!-- Options will be populated by JS -->
                    </select>
                </div>
                <!-- Apply Button -->
                <div class="md:pt-6">
                    <button id="apply-filter-btn" class="w-full md:w-auto bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-5 py-2.5 rounded-lg shadow font-medium transition duration-150 ease-in-out transform hover:-translate-y-0.5 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span id="filter-btn-text">Apply Filters</span>
                        <div id="filter-spinner" class="spinner hidden ml-2"></div>
                    </button>
                </div>
            </div>
        </section>

        <!-- 3. Three Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- Card 1: Pending Requests -->
            <div class="bg-white p-6 rounded-xl shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-1 border-l-4 border-l-indigo-500">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Pending Requests</p>
                        <p id="pending-count" class="text-3xl font-bold text-indigo-600"><?php echo $pending_requests; ?></p>
                        <p class="text-sm text-gray-500">awaiting your action</p>
                    </div>
                    <div class="p-3 bg-indigo-100 rounded-full">
                        <svg class="w-6 h-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500 flex items-center">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Requires immediate attention
                    </p>
                </div>
            </div>

            <!-- Card 2: Approved Hours -->
            <div class="bg-white p-6 rounded-xl shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-1 border-l-4 border-l-green-500">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Approved Hours (Month)</p>
                        <p id="approved-hours" class="text-3xl font-bold text-green-600"><?php echo number_format($approved_hours, 1); ?></p>
                        <p class="text-sm text-gray-500">for selected period</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <svg class="w-6 h-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500 flex items-center">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Successfully processed
                    </p>
                </div>
            </div>

            <!-- Card 3: Rejected Requests -->
            <div class="bg-white p-6 rounded-xl shadow-lg transition-all duration-300 hover:shadow-xl hover:-translate-y-1 border-l-4 border-l-red-500">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Rejected Requests (Month)</p>
                        <p id="rejected-requests" class="text-3xl font-bold text-red-600"><?php echo $rejected_requests; ?></p>
                        <p class="text-sm text-gray-500">for selected period</p>
                    </div>
                    <div class="p-3 bg-red-100 rounded-full">
                        <svg class="w-6 h-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500 flex items-center">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Review required
                    </p>
                </div>
            </div>
        </section>

        <!-- 4. Table -->
        <section class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Overtime Submissions
                    </h2>
                </div>
            </div>
            
            <!-- Responsive Table Wrapper -->
            <div class="table-container">
                <table id="overtime-table" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Punch Out Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calculated OT Hours</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <div class="flex items-center">
                                    <span>Accepted OT Hours</span>
                                    <button id="accepted-ot-info" class="ml-2 text-gray-500 hover:text-gray-700 focus:outline-none">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Work Report</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime Report</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($overtime_data)): ?>
                            <?php foreach ($overtime_data as $index => $row): ?>
                            <tr id="row-<?php echo $index + 1; ?>" data-attendance-id="<?php echo isset($row['attendance_id']) ? $row['attendance_id'] : ($index + 1); ?>" data-overtime-description="<?php echo htmlspecialchars($row['overtime_description'] ?? ''); ?>" class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo formatDate($row['date']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($shift_end_time); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['punch_out_time']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($row['ot_hours']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 bg-blue-100"><?php echo !empty($row['accepted_overtime_hours']) ? htmlspecialchars($row['accepted_overtime_hours']) : '-'; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate work-report-cell" data-full-text="<?php echo htmlspecialchars($row['work_report']); ?>" title="<?php echo htmlspecialchars($row['work_report']); ?>">
                                    <?php 
                                    // Truncate work report to first 4-5 words
                                    $work_report_display = !empty($row['work_report']) ? htmlspecialchars($row['work_report']) : 'No work report submitted';
                                    if (!empty($row['work_report'])) {
                                        $words = explode(' ', $row['work_report']);
                                        if (count($words) > 5) {
                                            $work_report_display = htmlspecialchars(implode(' ', array_slice($words, 0, 5))) . '...';
                                        } else {
                                            $work_report_display = htmlspecialchars($row['work_report']);
                                        }
                                    }
                                    echo $work_report_display;
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate overtime-report-cell" data-full-text="<?php echo htmlspecialchars($row['overtime_description']); ?>" title="<?php echo !empty($row['overtime_description']) ? htmlspecialchars($row['overtime_description']) : 'System deployment and testing'; ?>">
                                    <?php 
                                    // Truncate overtime description to first 4-5 words
                                    $overtime_desc_display = !empty($row['overtime_description']) ? htmlspecialchars($row['overtime_description']) : 'System deployment and testing';
                                    if (!empty($row['overtime_description'])) {
                                        $words = explode(' ', $row['overtime_description']);
                                        if (count($words) > 5) {
                                            $overtime_desc_display = htmlspecialchars(implode(' ', array_slice($words, 0, 5))) . '...';
                                        } else {
                                            $overtime_desc_display = htmlspecialchars($row['overtime_description']);
                                        }
                                    }
                                    echo $overtime_desc_display;
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $statusClass = '';
                                    switch (strtolower($row['status'])) {
                                        case 'approved':
                                            $statusClass = 'bg-green-100 text-green-800';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'bg-red-100 text-red-800';
                                            break;
                                        case 'submitted':
                                            $statusClass = 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'expired':
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            break;
                                        case 'pending':
                                        default:
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            break;
                                    }
                                    
                                    // Check if the record is expired or already submitted
                                    $is_expired = strtolower($row['status']) === 'expired';
                                    $is_submitted = strtolower($row['status']) === 'submitted';
                                    $is_send_disabled = $is_expired || $is_submitted;
                                    ?>
                                    <span class="status-badge px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2 action-buttons">
                                    <!-- Always show action icons regardless of status -->
                                    <button class="action-btn-view" data-action="view" data-row-id="<?php echo $index + 1; ?>" title="View Details">
                                        <svg class="w-5 h-5 text-blue-600 hover:text-blue-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <button class="action-btn-edit" data-action="edit" data-row-id="<?php echo $index + 1; ?>" title="Edit">
                                        <svg class="w-5 h-5 text-yellow-600 hover:text-yellow-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button class="action-btn-send <?php echo $is_send_disabled ? 'opacity-50 cursor-not-allowed' : ''; ?>" data-action="send" data-row-id="<?php echo $index + 1; ?>" title="<?php echo $is_expired ? 'Cannot send expired request' : ($is_submitted ? 'Request already submitted' : 'Send/Submit'); ?>" <?php echo $is_send_disabled ? 'disabled' : ''; ?>>
                                        <svg class="w-5 h-5 <?php echo $is_send_disabled ? 'text-gray-400' : 'text-green-600 hover:text-green-800'; ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                    </button>
                                    <button class="action-btn-resubmit" data-action="resubmit" data-row-id="<?php echo $index + 1; ?>" title="Resubmit">
                                        <svg class="w-5 h-5 text-purple-600 hover:text-purple-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No overtime data found for the selected period.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- 
      Confirmation Modal (Hidden by default)
      Used instead of alert() for a better user experience.
    -->
    <div id="confirmation-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full flex items-center justify-center p-4 hidden visibility-hidden opacity-0 z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md transform transition-all scale-95 opacity-0" id="modal-content">
            <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">Confirm Action</h3>
            <div class="mt-2">
                <p class="text-sm text-gray-500" id="modal-message">
                    Are you sure you want to [ACTION] this request for [EMPLOYEE]?
                </p>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button id="modal-cancel-btn" type="button" class="bg-white px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </button>
                <button id="modal-confirm-btn" type="button" class="text-white px-4 py-2 rounded-lg shadow-sm text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2">
                    <!-- Button color will be set by JS -->
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- 
      Text Details Modal (Hidden by default)
      Used to display full text when clicking on truncated work report or overtime report
    -->
    <div id="text-details-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full flex items-center justify-center p-4 hidden visibility-hidden opacity-0 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md transform transition-all scale-95 opacity-0" id="text-modal-content">
            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-medium leading-6 text-gray-900" id="text-modal-title">
                    Details
                </h3>
                <button id="text-modal-close" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="text-sm text-gray-900 whitespace-pre-wrap" id="text-modal-content-text">
                    -
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 rounded-b-lg">
                <div class="flex justify-end">
                    <button id="text-modal-close-btn" type="button" class="bg-white px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden input to store user's role for JavaScript access -->
    <input type="hidden" id="current-user-role" value="<?php echo htmlspecialchars($user_role); ?>">

    <!-- 
      Overtime Details Modal (Hidden by default)
      Used to display detailed information about an overtime request.
    -->
    <div id="overtime-details-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full flex items-center justify-center p-4 hidden visibility-hidden opacity-0 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md transform transition-all scale-95 opacity-0" id="details-modal-content">
            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center bg-blue-50">
                <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                    <svg class="h-5 w-5 text-blue-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Overtime Details
                </h3>
                <button id="details-modal-close" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-4">
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">Date</span>
                        <span class="text-sm text-gray-900" id="details-date">-</span>
                    </div>
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">End Time</span>
                        <span class="text-sm text-gray-900" id="details-shift-end">-</span>
                    </div>
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">Punch Out</span>
                        <span class="text-sm text-gray-900" id="details-punch-out">-</span>
                    </div>
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">OT Hours</span>
                        <span class="text-sm font-semibold text-gray-900" id="details-ot-hours">-</span>
                    </div>
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">Accepted OT Hours</span>
                        <span class="text-sm font-semibold text-gray-900" id="details-accepted-ot-hours">-</span>
                    </div>
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">Status</span>
                        <span class="text-sm" id="details-status">-</span>
                    </div>
                    <div class="border-b border-gray-100 pb-2">
                        <div class="flex items-start mb-1">
                            <svg class="h-4 w-4 text-blue-500 mr-2 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-500">Work Report</span>
                        </div>
                        <p class="text-sm text-gray-900 ml-6" id="details-work-report">-</p>
                    </div>
                    <div>
                        <div class="flex items-start mb-1">
                            <svg class="h-4 w-4 text-blue-500 mr-2 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-500">Overtime Report</span>
                        </div>
                        <p class="text-sm text-gray-900 ml-6" id="details-overtime-report">System deployment and testing</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 rounded-b-lg">
                <div class="flex justify-end">
                    <button id="details-modal-close-btn" type="button" class="bg-white px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 
      Accepted OT Hours Info Modal (Hidden by default)
      Used to display information about accepted OT hours.
    -->
    <div id="accepted-ot-info-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full flex items-center justify-center p-4 hidden visibility-hidden opacity-0 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md transform transition-all scale-95 opacity-0" id="accepted-ot-info-modal-content">
            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center bg-blue-50">
                <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                    <svg class="h-5 w-5 text-blue-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Accepted OT Hours
                </h3>
                <button id="accepted-ot-info-modal-close" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="text-sm text-gray-700">
                    <p class="mb-3">
                        You submitted overtime requests with calculated hours, but your manager may have accepted only a portion of those hours.
                    </p>
                    <p class="mb-3">
                        The "Accepted OT Hours" column shows the actual hours that have been approved by your manager. This may differ from the "Calculated OT Hours" based on company policy or other considerations.
                    </p>
                    <p>
                        If there's a discrepancy between calculated and accepted hours, please contact your manager for clarification.
                    </p>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 rounded-b-lg">
                <div class="flex justify-end">
                    <button id="accepted-ot-info-modal-close-btn" type="button" class="bg-white px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

                        <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 
      Send Overtime Modal (Hidden by default)
      Used to send overtime request to a manager.
    -->
    <div id="send-overtime-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full flex items-center justify-center p-4 hidden visibility-hidden opacity-0 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md transform transition-all scale-95 opacity-0" id="send-modal-content">
            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                    <svg class="h-5 w-5 text-indigo-600 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z" />
                        <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z" />
                    </svg>
                    Send Overtime Request
                </h3>
                <button id="send-modal-close" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-4">
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-gray-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">Date</span>
                        <span class="text-sm text-gray-900" id="send-date">-</span>
                    </div>
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-gray-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">OT Hours</span>
                        <span class="text-sm font-semibold text-gray-900" id="send-ot-hours">-</span>
                    </div>
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-gray-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">Status</span>
                        <span class="text-sm" id="send-status">-</span>
                    </div>
                    <div class="border-b border-gray-100 pb-2">
                        <div class="flex items-start mb-1">
                            <svg class="h-4 w-4 text-gray-500 mr-2 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-500">Work Report</span>
                        </div>
                        <p class="text-sm text-gray-900 ml-6" id="send-work-report">-</p>
                    </div>
                    
                    <div class="pt-2">
                        <label for="manager-select" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <svg class="h-4 w-4 text-gray-500 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Select Manager
                        </label>
                        <select id="manager-select" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Select a manager...</option>
                            <!-- Manager options will be populated dynamically -->
                        </select>
                    </div>
                    
                    <div class="pt-2">
                        <label for="overtime-description" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <svg class="h-4 w-4 text-gray-500 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Overtime Work Description
                        </label>
                        <textarea id="overtime-description" rows="3" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Describe what you did during overtime..."></textarea>
                        <div class="mt-1 text-sm text-gray-500 flex justify-between">
                            <span id="word-count">0 words</span>
                            <span id="min-words" class="text-red-500 hidden">Minimum 15 words required</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 rounded-b-lg">
                <div class="flex justify-end space-x-3">
                    <button id="send-modal-cancel-btn" type="button" class="bg-white px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                        <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </button>
                    <button id="send-modal-send-btn" type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                        </svg>
                        Send Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 
      Edit Overtime Modal (Hidden by default)
      Used to edit overtime request details.
    -->
    <div id="edit-overtime-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full flex items-center justify-center p-4 hidden visibility-hidden opacity-0 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md transform transition-all scale-95 opacity-0" id="edit-modal-content">
            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center bg-blue-50">
                <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                    <svg class="h-5 w-5 text-blue-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Overtime Request
                </h3>
                <button id="edit-modal-close" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <input type="hidden" id="edit-attendance-id">
                <div class="space-y-4">
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">Date</span>
                        <span class="text-sm text-gray-900" id="edit-date">-</span>
                    </div>
                    <div class="flex items-center border-b border-gray-100 pb-2">
                        <svg class="h-4 w-4 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium text-gray-500 w-24">OT Hours</span>
                        <span class="text-sm font-semibold text-gray-900" id="edit-ot-hours">-</span>
                    </div>
                    <div class="border-b border-gray-100 pb-2">
                        <div class="flex items-start mb-1">
                            <svg class="h-4 w-4 text-blue-500 mr-2 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-500">Work Report</span>
                        </div>
                        <textarea id="edit-work-report" rows="3" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Enter your work report..."></textarea>
                    </div>
                    
                    <div class="pt-2">
                        <label for="edit-overtime-description" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <svg class="h-4 w-4 text-blue-500 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Overtime Work Description
                        </label>
                        <textarea id="edit-overtime-description" rows="3" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Describe what you did during overtime..."></textarea>
                        <div class="mt-1 text-sm text-gray-500 flex justify-between">
                            <span id="edit-word-count">0 words</span>
                            <span id="edit-min-words" class="text-red-500 hidden">Minimum 15 words required</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 rounded-b-lg">
                <div class="flex justify-end space-x-3">
                    <button id="edit-modal-cancel-btn" type="button" class="bg-white px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center">
                        <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </button>
                    <button id="edit-modal-save-btn" type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // --- Element References ---
            const monthSelect = document.getElementById('month-select');
            const yearSelect = document.getElementById('year-select');
            const applyFilterBtn = document.getElementById('apply-filter-btn');
            const filterBtnText = document.getElementById('filter-btn-text');
            const filterSpinner = document.getElementById('filter-spinner');
            const tableBody = document.getElementById('overtime-table').querySelector('tbody');
            const mainContent = document.getElementById('mainContent');
            const pendingCountEl = document.getElementById('pending-count');
            
            // Modal Elements (Confirmation Modal)
            const modal = document.getElementById('confirmation-modal');
            const modalContent = document.getElementById('modal-content');
            const modalTitle = document.getElementById('modal-title');
            const modalMessage = document.getElementById('modal-message');
            const modalCancelBtn = document.getElementById('modal-cancel-btn');
            const modalConfirmBtn = document.getElementById('modal-confirm-btn');

            // Modal Elements (Overtime Details Modal)
            const detailsModal = document.getElementById('overtime-details-modal');
            const detailsModalContent = document.getElementById('details-modal-content');
            const detailsModalClose = document.getElementById('details-modal-close');
            const detailsModalCloseBtn = document.getElementById('details-modal-close-btn');

            // Modal Elements (Send Overtime Modal)
            const sendModal = document.getElementById('send-overtime-modal');
            const sendModalContent = document.getElementById('send-modal-content');
            const sendModalClose = document.getElementById('send-modal-close');
            const sendModalCancelBtn = document.getElementById('send-modal-cancel-btn');
            const sendModalSendBtn = document.getElementById('send-modal-send-btn');
            const managerSelect = document.getElementById('manager-select');
            const overtimeDescription = document.getElementById('overtime-description');
            const wordCountElement = document.getElementById('word-count');
            const minWordsElement = document.getElementById('min-words');

            // Modal Elements (Edit Overtime Modal)
            const editModal = document.getElementById('edit-overtime-modal');
            const editModalContent = document.getElementById('edit-modal-content');
            const editModalClose = document.getElementById('edit-modal-close');
            const editModalCancelBtn = document.getElementById('edit-modal-cancel-btn');
            const editModalSaveBtn = document.getElementById('edit-modal-save-btn');
            const editAttendanceId = document.getElementById('edit-attendance-id');
            const editDate = document.getElementById('edit-date');
            const editOtHours = document.getElementById('edit-ot-hours');
            const editWorkReport = document.getElementById('edit-work-report');
            const editOvertimeDescription = document.getElementById('edit-overtime-description');
            const editWordCountElement = document.getElementById('edit-word-count');
            const editMinWordsElement = document.getElementById('edit-min-words');

            // Modal Elements (Text Details Modal)
            const textModal = document.getElementById('text-details-modal');
            const textModalContent = document.getElementById('text-modal-content');
            const textModalClose = document.getElementById('text-modal-close');
            const textModalCloseBtn = document.getElementById('text-modal-close-btn');
            const textModalTitle = document.getElementById('text-modal-title');
            const textModalContentText = document.getElementById('text-modal-content-text');

            // Modal Elements (Accepted OT Hours Info Modal)
            const acceptedOtInfoModal = document.getElementById('accepted-ot-info-modal');
            const acceptedOtInfoModalContent = document.getElementById('accepted-ot-info-modal-content');
            const acceptedOtInfoModalClose = document.getElementById('accepted-ot-info-modal-close');
            const acceptedOtInfoModalCloseBtn = document.getElementById('accepted-ot-info-modal-close-btn');
            const acceptedOtInfoButton = document.getElementById('accepted-ot-info');

            let currentAction = null;
            let currentRowId = null;

            // --- Initialize sidebar to collapsed state ---
            function initializeSidebar() {
                const sidebar = document.getElementById('msbSidebar');
                if (sidebar) {
                    // Set sidebar to collapsed state by default
                    sidebar.classList.add('is-collapsed');
                    // Update main content to match
                    if (mainContent) {
                        mainContent.classList.add('is-expanded');
                    }
                }
            }

            // --- Handle Sidebar Resize Events ---
            function handleSidebarResize() {
                const sidebar = document.getElementById('msbSidebar');
                if (sidebar && mainContent) {
                    // Check if sidebar is collapsed
                    if (sidebar.classList.contains('is-collapsed')) {
                        mainContent.classList.add('is-expanded');
                    } else {
                        mainContent.classList.remove('is-expanded');
                    }
                }
            }

            // Listen for sidebar toggle events
            document.addEventListener('click', function(e) {
                if (e.target.closest('#msbToggle')) {
                    // Delay to allow sidebar animation to complete
                    setTimeout(handleSidebarResize, 250);
                }
            });

            // Also handle window resize events
            window.addEventListener('resize', handleSidebarResize);

            // Initialize sidebar state
            initializeSidebar();

            // --- 1. Populate Filters ---
            function populateFilters() {
                const now = new Date();
                const currentYear = now.getFullYear();
                const currentMonth = now.getMonth(); // 0-11
                
                // Get filter values from URL if present
                const urlParams = new URLSearchParams(window.location.search);
                const selectedMonth = urlParams.has('month') ? parseInt(urlParams.get('month')) : currentMonth;
                const selectedYear = urlParams.has('year') ? parseInt(urlParams.get('year')) : currentYear;
                
                const months = [
                    "January", "February", "March", "April", "May", "June", 
                    "July", "August", "September", "October", "November", "December"
                ];

                // Populate Months
                months.forEach((month, index) => {
                    const option = document.createElement('option');
                    option.value = index;
                    option.textContent = month;
                    if (index === selectedMonth) {
                        option.selected = true;
                    }
                    monthSelect.appendChild(option);
                });

                // Populate Years (current year + last 5 years)
                for (let i = 0; i < 5; i++) {
                    const year = currentYear - i;
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    if (year === selectedYear) {
                        option.selected = true;
                    }
                    yearSelect.appendChild(option);
                }
            }

            // --- 2. Handle Filter Logic ---
            function handleFilter() {
                const month = monthSelect.value;
                const year = yearSelect.value;
                
                // Show loading spinner
                filterBtnText.classList.add('hidden');
                filterSpinner.classList.remove('hidden');
                applyFilterBtn.disabled = true;
                
                // Fetch data via AJAX
                fetch(`fetch_overtime_data.php?month=${month}&year=${year}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update table with new data
                            updateTable(data.data, data.shift_end_time);
                            
                            // Update statistics cards
                            updateStatistics(data.statistics);
                            
                            // Update URL without page reload
                            const url = new URL(window.location);
                            url.searchParams.set('month', month);
                            url.searchParams.set('year', year);
                            window.history.replaceState({}, '', url);
                        } else {
                            console.error('Error fetching data:', data.error);
                            alert('Failed to fetch data. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to fetch data. Please try again.');
                    })
                    .finally(() => {
                        // Hide loading spinner
                        filterBtnText.classList.remove('hidden');
                        filterSpinner.classList.add('hidden');
                        applyFilterBtn.disabled = false;
                    });
            }
            
            // --- Update statistics cards ---
            function updateStatistics(statistics) {
                // Update Pending Requests card
                const pendingCountEl = document.getElementById('pending-count');
                if (pendingCountEl) {
                    pendingCountEl.textContent = statistics.pending_requests;
                }
                
                // Update Approved Hours card
                const approvedHoursEl = document.getElementById('approved-hours');
                if (approvedHoursEl) {
                    approvedHoursEl.textContent = statistics.approved_hours.toFixed(1);
                }
                
                // Update Rejected Requests card
                const rejectedRequestsEl = document.getElementById('rejected-requests');
                if (rejectedRequestsEl) {
                    rejectedRequestsEl.textContent = statistics.rejected_requests;
                }
                
                // Update Estimated Cost card
                const estimatedCostEl = document.getElementById('estimated-cost');
                if (estimatedCostEl) {
                    estimatedCostEl.textContent = '$' + statistics.estimated_cost.toFixed(2);
                }
            }

            // --- Update table with new data ---
            function updateTable(data, shiftEndTime) {
                // Clear existing table rows
                tableBody.innerHTML = '';
                
                if (data.length > 0) {
                    // Add new rows
                    data.forEach((row, index) => {
                        const tr = document.createElement('tr');
                        tr.id = `row-${index + 1}`;
                        tr.dataset.attendanceId = row.attendance_id || 0;
                        tr.dataset.overtimeDescription = row.overtime_description || '';
                        
                        // Determine status class
                        let statusClass = 'bg-yellow-100 text-yellow-800';
                        if (row.status.toLowerCase() === 'approved') {
                            statusClass = 'bg-green-100 text-green-800';
                        } else if (row.status.toLowerCase() === 'rejected') {
                            statusClass = 'bg-red-100 text-red-800';
                        } else if (row.status.toLowerCase() === 'submitted') {
                            statusClass = 'bg-blue-100 text-blue-800';
                        } else if (row.status.toLowerCase() === 'expired') {
                            statusClass = 'bg-gray-100 text-gray-800';
                        }
                        
                        // Determine if send button should be disabled
                        const isExpired = row.status.toLowerCase() === 'expired';
                        const isSubmitted = row.status.toLowerCase() === 'submitted';
                        const isSendDisabled = isExpired || isSubmitted;
                        const sendButtonClass = isSendDisabled ? 'action-btn-send opacity-50 cursor-not-allowed' : 'action-btn-send';
                        const sendButtonDisabled = isSendDisabled ? 'disabled' : '';
                        
                        // Always show action icons regardless of status
                        // Check if the record has a valid overtime request ID for enabling the edit button
                        const hasValidOvertimeRequest = row.attendance_id && row.attendance_id > 0;
                        const editButtonClass = hasValidOvertimeRequest 
                            ? 'action-btn-edit' 
                            : 'action-btn-edit opacity-50 cursor-not-allowed';
                        const editButtonDisabled = hasValidOvertimeRequest ? '' : 'disabled';
                        const editButtonTitle = hasValidOvertimeRequest 
                            ? 'Edit' 
                            : 'Cannot edit - No valid overtime request ID';
                        
                        const actionButtons = `
                            <button class="action-btn-view" data-action="view" data-row-id="${index + 1}" title="View Details">
                                <svg class="w-5 h-5 text-blue-600 hover:text-blue-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                            <button class="${editButtonClass}" data-action="edit" data-row-id="${index + 1}" title="${editButtonTitle}" ${editButtonDisabled}>
                                <svg class="w-5 h-5 ${hasValidOvertimeRequest ? 'text-yellow-600 hover:text-yellow-800' : 'text-gray-400'}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button class="${sendButtonClass}" data-action="send" data-row-id="${index + 1}" title="${isExpired ? 'Cannot send expired request' : (isSubmitted ? 'Request already submitted' : 'Send/Submit')}" ${sendButtonDisabled}>
                                <svg class="w-5 h-5 ${isSendDisabled ? 'text-gray-400' : 'text-green-600 hover:text-green-800'}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                            </button>
                            <button class="action-btn-resubmit" data-action="resubmit" data-row-id="${index + 1}" title="Resubmit">
                                <svg class="w-5 h-5 text-purple-600 hover:text-purple-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        `;
                        
                        tr.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${row.date}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${shiftEndTime}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${row.punch_out_time}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">${row.ot_hours}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 bg-blue-100">${row.accepted_overtime_hours || '-'}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate work-report-cell" data-full-text="${row.work_report || ''}" title="${row.work_report || 'No work report submitted'}">
                                ${truncateText(row.work_report || 'No work report submitted', 5)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate overtime-report-cell" data-full-text="${row.overtime_description || ''}" title="${row.overtime_description || 'System deployment and testing'}">
                                ${truncateText(row.overtime_description || 'System deployment and testing', 5)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                    ${row.status}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2 action-buttons">
                                ${actionButtons}
                            </td>
                        `;
                        
                        tableBody.appendChild(tr);
                    });
                } else {
                    // Show no data message
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                            No overtime data found for the selected period.
                        </td>
                    `;
                    tableBody.appendChild(tr);
                }
                
                // Update statistics after table update
                updateStatisticsFromTable(data);
            }
            
            // --- Update statistics from table data ---
            function updateStatisticsFromTable(data) {
                let pendingRequests = 0;
                let approvedHours = 0;
                let rejectedRequests = 0;
                
                data.forEach(row => {
                    const status = row.status.toLowerCase();
                    
                    // For approved records, use accepted overtime hours instead of calculated hours
                    let hours = 0;
                    if (status === 'approved' && row.accepted_overtime_hours) {
                        hours = parseFloat(row.accepted_overtime_hours) || 0;
                    } else {
                        hours = parseFloat(row.ot_hours) || 0;
                    }
                    
                    switch (status) {
                        case 'pending':
                            pendingRequests++;
                            break;
                        case 'approved':
                            approvedHours += hours;
                            break;
                        case 'rejected':
                            rejectedRequests++;
                            break;
                    }
                });
                
                // Calculate estimated cost based on approved hours (assuming $15/hour rate)
                const hourlyRate = 15;
                const estimatedCost = approvedHours * hourlyRate;
                
                // Update the statistics display
                const statistics = {
                    pending_requests: pendingRequests,
                    approved_hours: approvedHours,
                    rejected_requests: rejectedRequests,
                    estimated_cost: estimatedCost
                };
                
                updateStatistics(statistics);
            }

            // --- 3. Modal Logic ---
            function showModal(action, employeeName, rowId) {
                currentAction = action;
                currentRowId = rowId;

                const isApprove = action === 'approve';
                
                // Update modal content
                modalTitle.textContent = `Confirm ${isApprove ? 'Approval' : 'Rejection'}`;
                modalMessage.textContent = `Are you sure you want to ${action} this request for ${employeeName}?`;
                
                // Update confirm button style
                modalConfirmBtn.className = `text-white px-4 py-2 rounded-lg shadow-sm text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 ${isApprove ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500'}`;
                modalConfirmBtn.textContent = isApprove ? 'Approve' : 'Reject';
                
                // Show modal with transition
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.remove('visibility-hidden', 'opacity-0');
                    modalContent.classList.remove('scale-95', 'opacity-0');
                }, 20);
            }

            function hideModal() {
                modalContent.classList.add('scale-95', 'opacity-0');
                modal.classList.add('opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden', 'visibility-hidden');
                }, 300); // Match transition duration
            }

            function handleConfirmAction() {
                if (!currentAction || !currentRowId) return;

                const row = document.getElementById(`row-${currentRowId}`);
                if (!row) return;

                const statusBadge = row.querySelector('.status-badge');
                const actionButtons = row.querySelector('.action-buttons');

                if (currentAction === 'approve') {
                    // Update status badge
                    statusBadge.textContent = 'Approved';
                    statusBadge.className = 'status-badge px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800';
                    console.log(`Request ${currentRowId} approved.`);
                } else {
                    // Update status badge
                    statusBadge.textContent = 'Rejected';
                    statusBadge.className = 'status-badge px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800';
                    console.log(`Request ${currentRowId} rejected.`);
                }
                
                // Update pending card count
                if (pendingCountEl) {
                    let currentPending = parseInt(pendingCountEl.textContent);
                    if (currentPending > 0) {
                        pendingCountEl.textContent = currentPending - 1;
                    }
                }

                // Replace buttons with "Processed" text
                actionButtons.innerHTML = '<span class="text-sm font-medium text-gray-400">Processed</span>';
                
                hideModal();

                // Reset state
                currentAction = null;
                currentRowId = null;
            }

            // --- Send Overtime Modal Logic ---
            function showSendOvertimeModal(rowId) {
                // Set the current row ID for later use
                currentRowId = rowId;
                
                // Check if modal elements exist
                if (!sendModal || !sendModalContent) {
                    console.error('Send overtime modal elements not found');
                    return;
                }
                
                // Get the row data
                const row = document.getElementById(`row-${rowId}`);
                if (!row) {
                    console.error(`Row with ID row-${rowId} not found`);
                    return;
                }
                
                // Check if the record is expired or already submitted
                const statusElement = row.cells[7] ? row.cells[7].querySelector('.status-badge') : null; // Status is at index 7
                const status = statusElement ? statusElement.textContent.trim() : '-';
                if (status.toLowerCase() === 'expired') {
                    alert('This overtime request has expired (older than 15 days) and cannot be submitted.');
                    return;
                }
                
                if (status.toLowerCase() === 'submitted') {
                    alert('This overtime request has already been submitted and cannot be sent again.');
                    return;
                }
                
                // Extract data from the row
                const date = row.cells[0] ? row.cells[0].textContent : '-';
                const otHours = row.cells[3] ? row.cells[3].textContent : '-';
                const workReportCell = row.cells[5]; // Work Report cell is at index 5
                const workReport = workReportCell ? (workReportCell.getAttribute('data-full-text') || workReportCell.textContent) : '-';
                
                // Update modal content
                if (document.getElementById('send-date')) {
                    document.getElementById('send-date').textContent = date;
                }
                if (document.getElementById('send-ot-hours')) {
                    document.getElementById('send-ot-hours').textContent = otHours;
                }
                if (document.getElementById('send-status')) {
                    document.getElementById('send-status').textContent = status;
                }
                if (document.getElementById('send-work-report')) {
                    document.getElementById('send-work-report').textContent = workReport;
                }
                
                // Clear previous form data
                if (managerSelect) {
                    managerSelect.value = '';
                }
                if (overtimeDescription) {
                    overtimeDescription.value = '';
                }
                
                // Reset word count display
                if (wordCountElement) {
                    wordCountElement.textContent = '0 words';
                    wordCountElement.classList.remove('text-green-500');
                    wordCountElement.classList.add('text-red-500');
                }
                if (minWordsElement) {
                    minWordsElement.classList.remove('hidden');
                }
                
                // Populate manager dropdown and auto-select appropriate manager
                populateManagerDropdown();
                
                // Show modal with transition
                sendModal.classList.remove('hidden');
                setTimeout(() => {
                    sendModal.classList.remove('visibility-hidden', 'opacity-0');
                    sendModalContent.classList.remove('scale-95', 'opacity-0');
                }, 20);
            }

            function hideSendModal() {
                if (!sendModal || !sendModalContent) return;
                
                sendModalContent.classList.add('scale-95', 'opacity-0');
                sendModal.classList.add('opacity-0');
                setTimeout(() => {
                    sendModal.classList.add('hidden', 'visibility-hidden');
                }, 300); // Match transition duration
            }

            // --- Overtime Details Modal Logic ---
            function showOvertimeDetails(rowId) {
                // Check if modal elements exist
                if (!detailsModal || !detailsModalContent) {
                    console.error('Overtime details modal elements not found');
                    return;
                }
                
                // Get the row data
                const row = document.getElementById(`row-${rowId}`);
                if (!row) {
                    console.error(`Row with ID row-${rowId} not found`);
                    return;
                }
                
                // Extract data from the row
                const date = row.cells[0] ? row.cells[0].textContent : '-';
                const shiftEnd = row.cells[1] ? row.cells[1].textContent : '-';
                const punchOut = row.cells[2] ? row.cells[2].textContent : '-';
                const otHours = row.cells[3] ? row.cells[3].textContent : '-';
                const acceptedOtHours = row.cells[4] ? row.cells[4].textContent : '-';
                const workReportCell = row.cells[5]; // Work Report cell
                const workReport = workReportCell ? (workReportCell.getAttribute('data-full-text') || workReportCell.textContent) : '-';
                const statusElement = row.cells[7] ? row.cells[7].querySelector('.status-badge') : null; // Status is at index 7
                const status = statusElement ? statusElement.textContent.trim() : '-';
                const overtimeDescription = row.dataset.overtimeDescription || '';
                
                // Update modal content
                if (document.getElementById('details-date')) {
                    document.getElementById('details-date').textContent = date;
                }
                if (document.getElementById('details-shift-end')) {
                    document.getElementById('details-shift-end').textContent = shiftEnd;
                }
                if (document.getElementById('details-punch-out')) {
                    document.getElementById('details-punch-out').textContent = punchOut;
                }
                if (document.getElementById('details-ot-hours')) {
                    document.getElementById('details-ot-hours').textContent = otHours;
                }
                if (document.getElementById('details-accepted-ot-hours')) {
                    document.getElementById('details-accepted-ot-hours').textContent = acceptedOtHours;
                }
                if (document.getElementById('details-work-report')) {
                    document.getElementById('details-work-report').textContent = workReport;
                }
                if (document.getElementById('details-status')) {
                    document.getElementById('details-status').textContent = status;
                }
                
                // Update overtime description
                const overtimeReportElement = document.getElementById('details-overtime-report');
                if (overtimeReportElement) {
                    overtimeReportElement.textContent = overtimeDescription || 'System deployment and testing';
                }
                
                // Show modal with transition
                detailsModal.classList.remove('hidden');
                setTimeout(() => {
                    detailsModal.classList.remove('visibility-hidden', 'opacity-0');
                    detailsModalContent.classList.remove('scale-95', 'opacity-0');
                }, 20);
            }

            function hideDetailsModal() {
                if (!detailsModal || !detailsModalContent) return;
                
                detailsModalContent.classList.add('scale-95', 'opacity-0');
                detailsModal.classList.add('opacity-0');
                setTimeout(() => {
                    detailsModal.classList.add('hidden', 'visibility-hidden');
                }, 300); // Match transition duration
            }

            // --- Accepted OT Hours Info Modal Logic ---
            function showAcceptedOtInfoModal() {
                if (!acceptedOtInfoModal || !acceptedOtInfoModalContent) {
                    console.error('Accepted OT Info modal elements not found');
                    return;
                }
                
                // Show modal with transition
                acceptedOtInfoModal.classList.remove('hidden');
                setTimeout(() => {
                    acceptedOtInfoModal.classList.remove('visibility-hidden', 'opacity-0');
                    acceptedOtInfoModalContent.classList.remove('scale-95', 'opacity-0');
                }, 20);
            }

            function hideAcceptedOtInfoModal() {
                if (!acceptedOtInfoModal || !acceptedOtInfoModalContent) return;
                
                acceptedOtInfoModalContent.classList.add('scale-95', 'opacity-0');
                acceptedOtInfoModal.classList.add('opacity-0');
                setTimeout(() => {
                    acceptedOtInfoModal.classList.add('hidden', 'visibility-hidden');
                }, 300); // Match transition duration
            }

            // --- Text Details Modal Logic ---
            function showTextDetails(title, content) {
                // Check if modal elements exist
                if (!textModal || !textModalContent) {
                    console.error('Text details modal elements not found');
                    return;
                }
                
                // Update modal content
                if (textModalTitle) {
                    textModalTitle.textContent = title;
                }
                if (textModalContentText) {
                    textModalContentText.textContent = content;
                }
                
                // Show modal with transition
                textModal.classList.remove('hidden');
                setTimeout(() => {
                    textModal.classList.remove('visibility-hidden', 'opacity-0');
                    textModalContent.classList.remove('scale-95', 'opacity-0');
                }, 20);
            }

            function hideTextModal() {
                if (!textModal || !textModalContent) return;
                
                textModalContent.classList.add('scale-95', 'opacity-0');
                textModal.classList.add('opacity-0');
                setTimeout(() => {
                    textModal.classList.add('hidden', 'visibility-hidden');
                }, 300); // Match transition duration
            }

            // --- Edit Overtime Modal Logic ---
            function showEditOvertimeModal(rowId) {
                // Check if modal elements exist
                if (!editModal || !editModalContent) {
                    console.error('Edit overtime modal elements not found');
                    return;
                }
                
                // Get the row data
                const row = document.getElementById(`row-${rowId}`);
                if (!row) {
                    console.error(`Row with ID row-${rowId} not found`);
                    return;
                }
                
                // Extract data from the row
                const attendanceId = row.dataset.attendanceId || 0;
                const date = row.cells[0] ? row.cells[0].textContent : '-';
                const otHours = row.cells[3] ? row.cells[3].textContent : '-';
                const workReportCell = row.cells[5]; // Work Report cell is at index 5
                const workReport = workReportCell ? (workReportCell.getAttribute('data-full-text') || workReportCell.textContent) : '';
                const overtimeDescription = row.dataset.overtimeDescription || '';
                
                // Update modal content
                if (editAttendanceId) {
                    editAttendanceId.value = attendanceId;
                }
                if (editDate) {
                    editDate.textContent = date;
                }
                if (editOtHours) {
                    editOtHours.textContent = otHours;
                }
                if (editWorkReport) {
                    editWorkReport.value = workReport;
                }
                if (editOvertimeDescription) {
                    editOvertimeDescription.value = overtimeDescription;
                }
                
                // Update word count display
                updateEditWordCount();
                
                // Show modal with transition
                editModal.classList.remove('hidden');
                setTimeout(() => {
                    editModal.classList.remove('visibility-hidden', 'opacity-0');
                    editModalContent.classList.remove('scale-95', 'opacity-0');
                }, 20);
            }

            function hideEditModal() {
                if (!editModal || !editModalContent) return;
                
                editModalContent.classList.add('scale-95', 'opacity-0');
                editModal.classList.add('opacity-0');
                setTimeout(() => {
                    editModal.classList.add('hidden', 'visibility-hidden');
                }, 300); // Match transition duration
            }

            function updateEditWordCount() {
                if (!editOvertimeDescription || !editWordCountElement || !editMinWordsElement) return;
                
                const description = editOvertimeDescription.value.trim();
                const wordCount = description.split(/\s+/).filter(word => word.length > 0).length;
                
                // Update word count display
                editWordCountElement.textContent = `${wordCount} word${wordCount !== 1 ? 's' : ''}`;
                
                // Update styling based on word count
                if (wordCount >= 15) {
                    editWordCountElement.classList.remove('text-red-500');
                    editWordCountElement.classList.add('text-green-500');
                    editMinWordsElement.classList.add('hidden');
                } else {
                    editWordCountElement.classList.remove('text-green-500');
                    editWordCountElement.classList.add('text-red-500');
                    editMinWordsElement.classList.remove('hidden');
                }
            }

            function saveOvertimeChanges() {
                // Get form data
                const attendanceId = editAttendanceId ? editAttendanceId.value : '';
                const workReport = editWorkReport ? editWorkReport.value.trim() : '';
                const overtimeDescription = editOvertimeDescription ? editOvertimeDescription.value.trim() : '';
                
                // Validate that the overtime description has at least 15 words
                const wordCount = overtimeDescription.split(/\s+/).filter(word => word.length > 0).length;
                if (wordCount < 15) {
                    alert('Please provide a detailed overtime description of at least 15 words.');
                    return;
                }
                
                // Prepare data for submission
                const requestData = {
                    attendance_id: attendanceId,
                    work_report: workReport,
                    overtime_description: overtimeDescription
                };
                
                // Show loading state
                const originalText = editModalSaveBtn.innerHTML;
                editModalSaveBtn.innerHTML = '<div class="spinner mr-2"></div>Saving...';
                editModalSaveBtn.disabled = true;
                
                // Send request to server
                fetch('update_overtime_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Overtime request updated successfully!');
                        hideEditModal();
                        
                        // Update the row data in the table
                        const rowId = currentRowId;
                        const row = document.getElementById(`row-${rowId}`);
                        if (row) {
                            // Update work report cell
                            const workReportCell = row.querySelector('.work-report-cell');
                            if (workReportCell) {
                                workReportCell.textContent = workReport || 'No work report submitted';
                                workReportCell.setAttribute('data-full-text', workReport);
                                workReportCell.setAttribute('title', workReport || 'No work report submitted');
                            }
                            
                            // Update overtime description in row dataset
                            row.dataset.overtimeDescription = overtimeDescription;
                            
                            // Update overtime report cell
                            const overtimeReportCell = row.querySelector('.overtime-report-cell');
                            if (overtimeReportCell) {
                                overtimeReportCell.textContent = overtimeDescription || 'System deployment and testing';
                                overtimeReportCell.setAttribute('data-full-text', overtimeDescription);
                                overtimeReportCell.setAttribute('title', overtimeDescription || 'System deployment and testing');
                            }
                        }
                    } else {
                        alert('Error updating overtime request: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating overtime request. Please try again.');
                })
                .finally(() => {
                    // Restore button state
                    editModalSaveBtn.innerHTML = originalText;
                    editModalSaveBtn.disabled = false;
                });
            }

            // --- 4. Event Listeners ---
            
            // Filter button
            applyFilterBtn.addEventListener('click', handleFilter);
            
            // Table action buttons (using event delegation)
            tableBody.addEventListener('click', (e) => {
                const viewButton = e.target.closest('.action-btn-view');
                const editButton = e.target.closest('.action-btn-edit');
                const sendButton = e.target.closest('.action-btn-send');
                const resubmitButton = e.target.closest('.action-btn-resubmit');
                const workReportCell = e.target.closest('.work-report-cell');
                const overtimeReportCell = e.target.closest('.overtime-report-cell');
                
                if (viewButton) {
                    const { rowId } = viewButton.dataset;
                    if (rowId) {
                        showOvertimeDetails(rowId);
                    } else {
                        console.error('Row ID not found in view button data attributes');
                    }
                } else if (sendButton) {
                    const { rowId } = sendButton.dataset;
                    if (rowId) {
                        // Check if the record is expired or already submitted before showing the send modal
                        const row = document.getElementById(`row-${rowId}`);
                        if (row) {
                            const statusElement = row.cells[6] ? row.cells[6].querySelector('.status-badge') : null;
                            const status = statusElement ? statusElement.textContent.trim().toLowerCase() : '';
                            
                            // Only prevent submission for expired records (not for submitted, approved, or rejected)
                            if (status === 'expired') {
                                alert('This overtime request has expired (older than 15 days) and cannot be submitted.');
                                return;
                            }
                            
                            if (status === 'submitted') {
                                alert('This overtime request has already been submitted and cannot be sent again.');
                                return;
                            }
                        }
                        showSendOvertimeModal(rowId);
                    } else {
                        console.error('Row ID not found in send button data attributes');
                    }
                } else if (editButton) {
                    // Check if the button is disabled
                    if (editButton.hasAttribute('disabled')) {
                        // Button is disabled, show tooltip or alert
                        const title = editButton.getAttribute('title');
                        if (title && title.includes('Cannot edit')) {
                            alert(title);
                        }
                        return;
                    }
                    
                    const { rowId } = editButton.dataset;
                    if (rowId) {
                        currentRowId = rowId;
                        showEditOvertimeModal(rowId);
                    } else {
                        console.error('Row ID not found in edit button data attributes');
                    }
                } else if (resubmitButton) {
                    const { rowId } = resubmitButton.dataset;
                    if (rowId) {
                        console.log(`Resubmit action for row ${rowId}`);
                        // Add resubmit logic here
                    } else {
                        console.error('Row ID not found in resubmit button data attributes');
                    }
                } else if (workReportCell) {
                    const fullText = workReportCell.getAttribute('data-full-text');
                    if (fullText) {
                        showTextDetails('Work Report', fullText);
                    }
                } else if (overtimeReportCell) {
                    const fullText = overtimeReportCell.getAttribute('data-full-text');
                    if (fullText) {
                        showTextDetails('Overtime Report', fullText);
                    }
                }
            });

            // Modal buttons (Confirmation Modal)
            modalCancelBtn.addEventListener('click', hideModal);
            modalConfirmBtn.addEventListener('click', handleConfirmAction);

            // Modal buttons (Overtime Details Modal)
            if (detailsModalClose) {
                detailsModalClose.addEventListener('click', hideDetailsModal);
            }
            if (detailsModalCloseBtn) {
                detailsModalCloseBtn.addEventListener('click', hideDetailsModal);
            }
            
            // Modal buttons (Edit Overtime Modal)
            if (editModalClose) {
                editModalClose.addEventListener('click', hideEditModal);
            }
            if (editModalCancelBtn) {
                editModalCancelBtn.addEventListener('click', hideEditModal);
            }
            if (editModalSaveBtn) {
                editModalSaveBtn.addEventListener('click', saveOvertimeChanges);
            }
            
            // Update word count as user types in edit modal
            if (editOvertimeDescription && editWordCountElement && editMinWordsElement) {
                editOvertimeDescription.addEventListener('input', updateEditWordCount);
            }
            
            // Update word count as user types in send modal
            if (overtimeDescription && wordCountElement && minWordsElement) {
                overtimeDescription.addEventListener('input', function() {
                    const description = overtimeDescription.value.trim();
                    const wordCount = description.split(/\s+/).filter(word => word.length > 0).length;
                    
                    // Update word count display
                    wordCountElement.textContent = `${wordCount} word${wordCount !== 1 ? 's' : ''}`;
                    
                    // Update styling based on word count
                    if (wordCount >= 15) {
                        wordCountElement.classList.remove('text-red-500');
                        wordCountElement.classList.add('text-green-500');
                        minWordsElement.classList.add('hidden');
                    } else {
                        wordCountElement.classList.remove('text-green-500');
                        wordCountElement.classList.add('text-red-500');
                        minWordsElement.classList.remove('hidden');
                    }
                });
            }
            
            // Modal buttons (Send Overtime Modal)
            if (sendModalClose) {
                sendModalClose.addEventListener('click', hideSendModal);
            }
            if (sendModalCancelBtn) {
                sendModalCancelBtn.addEventListener('click', hideSendModal);
            }
            if (sendModalSendBtn) {
                sendModalSendBtn.addEventListener('click', function() {
                    // Validate that the overtime description has at least 15 words
                    const description = overtimeDescription ? overtimeDescription.value.trim() : '';
                    const wordCount = description.split(/\s+/).filter(word => word.length > 0).length;
                    
                    if (wordCount < 15) {
                        alert('Please provide a detailed overtime description of at least 15 words.');
                        return;
                    }
                    
                    // Get selected manager
                    const selectedManagerId = managerSelect ? managerSelect.value : '';
                    if (!selectedManagerId) {
                        alert('Please select a manager.');
                        return;
                    }
                    
                    // Get current row data
                    const currentRow = document.getElementById(`row-${currentRowId}`);
                    if (!currentRow) {
                        alert('Error: Unable to find row data.');
                        return;
                    }
                    
                    // Extract data from the row
                    const date = document.getElementById('send-date').textContent;
                    const otHours = document.getElementById('send-ot-hours').textContent;
                    const workReport = document.getElementById('send-work-report').textContent;
                    
                    // Get shift end time from the table
                    const shiftEndTime = currentRow.cells[1].textContent;
                    const punchOutTime = currentRow.cells[2].textContent;
                    
                    // Get attendance ID (we'll need to add this to the table rows)
                    const attendanceId = currentRow.dataset.attendanceId || 0;
                    
                    // Debug: Log the raw values
                    console.log('Raw values: attendanceId=', attendanceId, 'date=', date, 'otHours=', otHours, 'workReport=', workReport);
                    
                    // Prepare data for submission
                    const shiftEnd24 = convertTo24HourFormat(shiftEndTime);
                    const punchOut24 = convertTo24HourFormat(punchOutTime);
                    
                    // Debug: Log the data being sent
                    console.log('Shift End Time (12h):', shiftEndTime);
                    console.log('Shift End Time (24h):', shiftEnd24);
                    console.log('Punch Out Time (12h):', punchOutTime);
                    console.log('Punch Out Time (24h):', punchOut24);
                    
                    const requestData = {
                        attendance_id: attendanceId,
                        date: date,
                        shift_end_time: shiftEnd24,
                        punch_out_time: punchOut24,
                        overtime_hours: parseFloat(otHours),
                        work_report: workReport,
                        overtime_description: description,
                        manager_id: selectedManagerId
                    };
                    
                    // Debug: Log the final request data
                    console.log('Final request data:', requestData);
                    
                    // Submit overtime request
                    submitOvertimeRequest(requestData);
                });
            }

            // Modal buttons (Text Details Modal)
            if (textModalClose) {
                textModalClose.addEventListener('click', hideTextModal);
            }
            if (textModalCloseBtn) {
                textModalCloseBtn.addEventListener('click', hideTextModal);
            }
            
            // Modal buttons (Accepted OT Hours Info Modal)
            if (acceptedOtInfoButton) {
                acceptedOtInfoButton.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    showAcceptedOtInfoModal();
                });
            }
            if (acceptedOtInfoModalClose) {
                acceptedOtInfoModalClose.addEventListener('click', hideAcceptedOtInfoModal);
            }
            if (acceptedOtInfoModalCloseBtn) {
                acceptedOtInfoModalCloseBtn.addEventListener('click', hideAcceptedOtInfoModal);
            }
            
            // Close modals when clicking outside
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        hideModal();
                    }
                });
            }
            
            if (detailsModal) {
                detailsModal.addEventListener('click', (e) => {
                    if (e.target === detailsModal) {
                        hideDetailsModal();
                    }
                });
            }
            
            if (sendModal) {
                sendModal.addEventListener('click', (e) => {
                    if (e.target === sendModal) {
                        hideSendModal();
                    }
                });
            }

            if (editModal) {
                editModal.addEventListener('click', (e) => {
                    if (e.target === editModal) {
                        hideEditModal();
                    }
                });
            }

            if (textModal) {
                textModal.addEventListener('click', (e) => {
                    if (e.target === textModal) {
                        hideTextModal();
                    }
                });
            }
            
            if (acceptedOtInfoModal) {
                acceptedOtInfoModal.addEventListener('click', (e) => {
                    if (e.target === acceptedOtInfoModal) {
                        hideAcceptedOtInfoModal();
                    }
                });
            }

            // Close modals with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (modal && !modal.classList.contains('hidden')) {
                        hideModal();
                    }
                    if (detailsModal && !detailsModal.classList.contains('hidden')) {
                        hideDetailsModal();
                    }
                    if (sendModal && !sendModal.classList.contains('hidden')) {
                        hideSendModal();
                    }
                    if (editModal && !editModal.classList.contains('hidden')) {
                        hideEditModal();
                    }
                    if (textModal && !textModal.classList.contains('hidden')) {
                        hideTextModal();
                    }
                    if (acceptedOtInfoModal && !acceptedOtInfoModal.classList.contains('hidden')) {
                        hideAcceptedOtInfoModal();
                    }
                }
            });

            // --- Helper Functions ---
            /**
             * Truncate text to specified number of words and add ellipsis
             */
            function truncateText(text, maxWords) {
                if (!text || text === 'No work report submitted' || text === 'System deployment and testing') {
                    return text;
                }
                
                const words = text.split(' ');
                if (words.length <= maxWords) {
                    return text;
                }
                
                return words.slice(0, maxWords).join(' ') + '...';
            }
            
            /**
             * Submit overtime request to the server
             */
            function submitOvertimeRequest(requestData) {
                // Debug: Log the request data
                console.log('Submitting overtime request:', requestData);
                
                // Show loading state
                const originalText = sendModalSendBtn.innerHTML;
                sendModalSendBtn.innerHTML = '<div class="spinner mr-2"></div>Submitting...';
                sendModalSendBtn.disabled = true;
                
                // Send request to server
                fetch('submit_overtime_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Overtime request submitted successfully!');
                        hideSendModal();
                        
                        // Update the row status to submitted
                        const row = document.getElementById(`row-${currentRowId}`);
                        if (row) {
                            const statusBadge = row.querySelector('.status-badge');
                            if (statusBadge) {
                                statusBadge.textContent = 'Submitted';
                                statusBadge.className = 'status-badge px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800';
                            }
                            
                            // Update pending count
                            if (pendingCountEl) {
                                let currentPending = parseInt(pendingCountEl.textContent);
                                pendingCountEl.textContent = currentPending + 1;
                            }
                        }
                    } else {
                        alert('Error submitting overtime request: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error submitting overtime request. Please try again.');
                })
                .finally(() => {
                    // Restore button state
                    sendModalSendBtn.innerHTML = originalText;
                    sendModalSendBtn.disabled = false;
                });
            }
            
            /**
             * Convert 12-hour format to 24-hour format
             */
            function convertTo24HourFormat(time12h) {
                console.log('Converting time:', time12h);
                
                if (!time12h || time12h === 'N/A') {
                    console.log('Returning default time');
                    return '00:00:00';
                }
                
                // Remove extra spaces and trim
                time12h = time12h.replace(/\s+/g, ' ').trim();
                console.log('After trimming:', time12h);
                
                // Split time and period
                const parts = time12h.split(' ');
                console.log('Parts:', parts);
                
                if (parts.length !== 2) {
                    console.log('Invalid format, returning default');
                    return '00:00:00';
                }
                
                let [time, period] = parts;
                let [hours, minutes] = time.split(':');
                
                // Convert to integers
                hours = parseInt(hours);
                minutes = minutes ? parseInt(minutes) : 0;
                
                console.log('Parsed hours:', hours, 'minutes:', minutes, 'period:', period);
                
                // Convert to 24-hour format
                if (period.toUpperCase() === 'AM') {
                    if (hours === 12) {
                        hours = 0;
                    }
                } else if (period.toUpperCase() === 'PM') {
                    if (hours !== 12) {
                        hours += 12;
                    }
                }
                
                // Format as HH:MM:SS
                const result = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:00`;
                console.log('Converted time result:', result);
                return result;
            }
            
            /**
             * Populate the manager dropdown with users who have the roles "Senior Manager (Studio)" or "Senior Manager (Site)" and are active
             * Fetches data from the server and organizes with headings
             * Auto-selects the appropriate manager based on the user's role
             */
            function populateManagerDropdown() {
                // Clear existing options except the default one
                if (!managerSelect) return;
                managerSelect.innerHTML = '<option value="">Select a manager...</option>';
                
                // Get the current user's role
                const userRoleInput = document.getElementById('current-user-role');
                const currentUserRole = userRoleInput ? userRoleInput.value : 'N/A';
                
                // Determine which manager type to auto-select
                let autoSelectManagerType = 'studio'; // default
                const siteRoles = ['Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Sales', 'Social Media Marketing', 'Graphic Designer'];
                
                if (siteRoles.includes(currentUserRole)) {
                    autoSelectManagerType = 'site';
                }
                
                // Fetch managers from the server
                fetch('fetch_managers.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Clear the loading message
                            managerSelect.innerHTML = '<option value="">Select a manager...</option>';
                            
                            let autoSelectManagerId = null;
                            
                            // Add Studio Managers heading and options
                            if (data.studio_managers.length > 0) {
                                const studioHeading = document.createElement('optgroup');
                                studioHeading.label = 'Studio Managers';
                                data.studio_managers.forEach(manager => {
                                    const option = document.createElement('option');
                                    option.value = manager.id;
                                    option.textContent = `${manager.name} (${manager.role})`;
                                    studioHeading.appendChild(option);
                                    
                                    // If this is the type we should auto-select and we haven't selected one yet, select this one
                                    if (autoSelectManagerType === 'studio' && autoSelectManagerId === null) {
                                        autoSelectManagerId = manager.id;
                                    }
                                });
                                managerSelect.appendChild(studioHeading);
                            }
                            
                            // Add Site Managers heading and options
                            if (data.site_managers.length > 0) {
                                const siteHeading = document.createElement('optgroup');
                                siteHeading.label = 'Site Managers';
                                data.site_managers.forEach(manager => {
                                    const option = document.createElement('option');
                                    option.value = manager.id;
                                    option.textContent = `${manager.name} (${manager.role})`;
                                    siteHeading.appendChild(option);
                                    
                                    // If this is the type we should auto-select and we haven't selected one yet, select this one
                                    if (autoSelectManagerType === 'site' && autoSelectManagerId === null) {
                                        autoSelectManagerId = manager.id;
                                    }
                                });
                                managerSelect.appendChild(siteHeading);
                            }
                            
                            // Auto-select the appropriate manager
                            if (autoSelectManagerId !== null) {
                                managerSelect.value = autoSelectManagerId;
                            }
                        } else {
                            console.error('Error fetching managers:', data.error);
                            managerSelect.innerHTML = '<option value="">Error loading managers</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching managers:', error);
                        managerSelect.innerHTML = '<option value="">Error loading managers</option>';
                    });
            }

            // Call populateManagerDropdown when the page loads
            populateManagerDropdown();

            // --- Initial Setup ---
            populateFilters();
        });
    </script>
</body>
</html>