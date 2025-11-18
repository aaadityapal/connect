<?php
session_start();
require_once 'config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Update the date range logic at the top of the file
$current_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Calculate start and end dates for the selected month
$start_date = date('Y-m-01', strtotime("$current_year-$current_month-01"));
$end_date = date('Y-m-t', strtotime("$current_year-$current_month-01"));

// Fetch leave data from leave_request and leave_types tables
$leave_query = "SELECT 
                lr.id, lr.start_date, lr.end_date, lr.duration, lr.status, 
                lt.name as leave_type_name, lt.color_code,
                DATEDIFF(lr.end_date, lr.start_date) + 1 as days_count
                FROM leave_request lr
                JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.user_id = ? 
                AND ((lr.start_date BETWEEN ? AND ?) 
                OR (lr.end_date BETWEEN ? AND ?)
                OR (? BETWEEN lr.start_date AND lr.end_date))
                AND lr.status = 'approved'
                ORDER BY lr.start_date";

$leave_stmt = $conn->prepare($leave_query);
$leave_stmt->bind_param("isssss", $user_id, $start_date, $end_date, $start_date, $end_date, $start_date);
$leave_stmt->execute();
$leave_result = $leave_stmt->get_result();

// Initialize leave data arrays
$user_leaves = [];
$leave_types_count = [];
$total_leave_days = 0;

// Process leave data
while ($leave_row = $leave_result->fetch_assoc()) {
    $user_leaves[] = $leave_row;
    
    // Count leave types
    $leave_type = $leave_row['leave_type_name'];
    $color_code = $leave_row['color_code'];
    
    if (!isset($leave_types_count[$leave_type])) {
        $leave_types_count[$leave_type] = [
            'count' => 0,
            'color' => $color_code
        ];
    }
    $leave_types_count[$leave_type]['count'] += $leave_row['days_count'];
    $total_leave_days += $leave_row['days_count'];
}

// Format leave types for display
$leave_types_formatted = '';
foreach ($leave_types_count as $type => $info) {
    $color_style = "";
    if (!empty($info['color'])) {
        $color_style = "background-color: #{$info['color']}; color: white; border-color: #{$info['color']};";
    }
    $leave_types_formatted .= '<span class="leave-type-badge" style="' . $color_style . '">' . $type . ': ' . $info['count'] . '</span> ';
}
$leave_types_formatted = rtrim($leave_types_formatted);

// If no leave types, provide a default message
if (empty($leave_types_formatted)) {
    $leave_types_formatted = '<span class="leave-type-badge">No leaves</span>';
}

// Fetch attendance records with work reports
$query = "SELECT 
    a.*,
    a.waved_off,
    us.shift_id,
    DATE_FORMAT(a.date, '%d-%m-%Y') as formatted_date,
    TIME_FORMAT(a.punch_in, '%h:%i %p') as formatted_punch_in,
    TIME_FORMAT(a.punch_out, '%h:%i %p') as formatted_punch_out,
    a.punch_in_photo,
    a.punch_out_photo,
    s.shift_name,
    s.start_time as raw_shift_start,
    s.end_time as raw_shift_end,
    a.punch_in as raw_punch_in,
    a.punch_out as raw_punch_out,
    TIME_TO_SEC(a.punch_in) as punch_in_seconds,
    TIME_TO_SEC(a.punch_out) as punch_out_seconds,
    TIME_TO_SEC(s.start_time) as shift_start_seconds,
    TIME_TO_SEC(s.end_time) as shift_end_seconds,
    TIME_FORMAT(s.start_time, '%h:%i %p') as shift_start,
    TIME_FORMAT(s.end_time, '%h:%i %p') as shift_end,
    CASE 
        WHEN a.punch_out IS NOT NULL 
            AND s.end_time IS NOT NULL 
            AND TIME_TO_SEC(
                CASE 
                    WHEN TIME_TO_SEC(a.punch_out) > TIME_TO_SEC(s.end_time)
                    THEN SEC_TO_TIME(
                        CASE
                            WHEN TIME_TO_SEC(a.punch_out) >= TIME_TO_SEC(s.end_time)
                            THEN TIME_TO_SEC(a.punch_out) - TIME_TO_SEC(s.end_time)
                            ELSE (TIME_TO_SEC(a.punch_out) + 86400) - TIME_TO_SEC(s.end_time)
                        END
                    )
                    ELSE '00:00:00'
                END
            ) >= 5400  -- 5400 seconds = 1 hour 30 minutes
        THEN 
            -- Round down to nearest 30-minute increment (0 or 30 minutes)
            SEC_TO_TIME(
                FLOOR(
                    CASE
                        WHEN TIME_TO_SEC(a.punch_out) >= TIME_TO_SEC(s.end_time)
                        THEN (TIME_TO_SEC(a.punch_out) - TIME_TO_SEC(s.end_time))
                        ELSE ((TIME_TO_SEC(a.punch_out) + 86400) - TIME_TO_SEC(s.end_time))
                    END / 1800
                ) * 1800
            )
        ELSE '00:00:00'
    END as calculated_overtime,
    CASE
        WHEN a.punch_in IS NOT NULL 
            AND s.start_time IS NOT NULL 
            AND TIME_TO_SEC(a.punch_in) > (TIME_TO_SEC(s.start_time) + 960) -- 900 seconds = 15 minutes
        THEN 1
        ELSE 0
    END as is_late,
    CASE
        WHEN a.punch_in IS NOT NULL AND a.punch_out IS NOT NULL
        THEN TIME_FORMAT(
            TIMEDIFF(a.punch_out, a.punch_in),
            '%H:%i'
        )
        ELSE NULL
    END as working_hours
FROM attendance a
LEFT JOIN user_shifts us ON a.user_id = us.user_id
    AND a.date >= us.effective_from 
    AND (us.effective_to IS NULL OR a.date <= us.effective_to)
LEFT JOIN shifts s ON us.shift_id = s.id
WHERE a.user_id = ? 
AND a.date BETWEEN ? AND ?
GROUP BY a.id
ORDER BY a.date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Calculate stats for the overview section
$total_present = 0;
$leave_types = [];

// Count the number of Short Leaves used in this month
$short_leaves_used = 0;
foreach ($user_leaves as $leave) {
    if ($leave['leave_type_name'] == 'Short Leave') {
        $short_leaves_used += $leave['days_count'];
    }
}

// Calculate how many Short Leaves are still available this month (max 2)
$short_leaves_available = max(0, 2 - $short_leaves_used);

// Debug string for late punches
$late_punch_debug = [];
$all_punches_debug = [];

// Reset result pointer
$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    // Add all records to the debug array for inspection
    $all_punches_debug[] = [
        'date' => $row['formatted_date'],
        'punch_in' => $row['formatted_punch_in'],
        'shift_start' => $row['shift_start'] ?? '-',
        'raw_punch_in' => $row['raw_punch_in'] ?? null,
        'raw_shift_start' => $row['raw_shift_start'] ?? null,
        'punch_in_seconds' => $row['punch_in_seconds'] ?? 0,
        'shift_start_seconds' => $row['shift_start_seconds'] ?? 0,
        'time_diff_seconds' => isset($row['punch_in_seconds']) && isset($row['shift_start_seconds']) ? 
            ($row['punch_in_seconds'] - $row['shift_start_seconds']) : 0,
        'is_late_flag' => $row['is_late'] == 1,
        'status' => $row['status'],
        'shift_id' => $row['shift_id']
    ];
}

// Calculate the final adjusted late punches count
// Now we use the waved_off column from the database instead of the adjusted_late_count
$late_punches = 0;
$waved_off_count = 0;
$total_late_punches = 0;

// Initialize variables to ensure they have values
if (!isset($short_leaves_used)) {
    $short_leaves_used = 0;
}

// Count late punches and waved off punches
if ($result) {
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] == 'present' && $row['is_late'] == 1) {
            $total_late_punches++;
            // If waved_off is 1, increment the waved_off_count
            if (isset($row['waved_off']) && $row['waved_off'] == 1) {
                $waved_off_count++;
            } else {
                // If not waved off, count it as a late punch
                $late_punches++;
            }
        }
    }
}

// Use the leave data from the leave request table
$total_leaves = $total_leave_days;

// Reset result for template rendering
if ($result) {
    $result->data_seek(0);
}

// Add adjusted late punches to debug info
$debug_info = [
    'month' => $current_month,
    'year' => $current_year,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'late_punches' => $late_punches,
    'total_late_punches' => $total_late_punches,
    'waved_off_count' => $waved_off_count,
    'late_punch_details' => $late_punch_debug,
    'all_punches' => $all_punches_debug,
    'short_leaves_used' => $short_leaves_used,
    'short_leaves_available' => max(0, 2 - $short_leaves_used),
    'total_leaves' => $total_leaves,
    'leave_types' => $leave_types_formatted,
    'query' => $query
];

// Debug output to see what values we have
// error_log("Late punches debug: late_punches=$late_punches, total_late_punches=$total_late_punches, waved_off_count=$waved_off_count");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Sheet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Add SheetJS library for Excel export -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    
    <style>
        /* Add dashboard container styles */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            background-color: #f9fafb;
        }

        .main-content.collapsed {
            margin-left: 70px;
        }

        /* Update container styles to work with new layout */
        .container {
            max-width: none;
            margin: 0;
            padding: 0;
            background: transparent;
            box-shadow: none;
        }

        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #7209b7;
            --background-color: #f7f9fc;
            --card-background: #ffffff;
            --border-color: #e9ecef;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

                                    
            background-color: var(--card-background);
            box-shadow: var(--shadow-md);
        }

        .header h1 {
            color: var(--secondary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .date-filter {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            background: var(--background-color);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        .month-year-picker {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .date-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            color: var(--text-primary);
            background-color: white;
            cursor: pointer;
            min-width: 120px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%234a5568' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 0.75rem) center;
            padding-right: 2rem;
            transition: all 0.2s ease;
        }

        .date-select:hover {
            border-color: var(--primary-color);
        }

        .date-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .filter-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .attendance-overview {
            background: var(--card-background);
            border-radius: var(--radius-lg);
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
        }

        .overview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .overview-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin: 0;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-card.leaves-card {
            grid-column: span 2;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
        }

        .stat-info {
            flex: 1;
        }

        .stat-info h3 {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin: 0 0 0.5rem 0;
        }

        .stat-info p {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .chart-wrapper {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            height: 300px;
            min-height: 300px;
            position: relative;
            display: block;
            box-shadow: var(--shadow-sm);
        }

        .worksheet-table-container {
            background: var(--card-background);
            border-radius: var(--radius-lg);
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .worksheet-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 0;
        }

        .worksheet-table th {
            background: var(--secondary-color);
            color: white;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 500;
            font-size: 0.875rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .worksheet-table th:first-child {
            border-top-left-radius: var(--radius-sm);
        }

        .worksheet-table th:last-child {
            border-top-right-radius: var(--radius-sm);
        }

        .worksheet-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .worksheet-table tr:last-child td {
            border-bottom: none;
        }

        .worksheet-table tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .status-present {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-present::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--success-color);
        }

        .status-absent {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .status-absent::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--danger-color);
        }

        .status-holiday {
            background-color: rgba(114, 9, 183, 0.1);
            color: var(--accent-color);
        }

        .status-holiday::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--accent-color);
        }

        .status-leave {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-leave::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--warning-color);
        }

        .status-halfday {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 50%, rgba(16, 185, 129, 0.1) 50%);
            color: #92400e;
        }

        .status-halfday::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--warning-color) 50%, var(--success-color) 50%);
        }

        .status-onleave {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-onleave::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--warning-color);
        }

        .status-weekend {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }

        .status-weekend::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--info-color);
        }

        .status-late {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 50%, rgba(16, 185, 129, 0.1) 50%);
            color: var(--danger-color);
        }

        .status-late::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--danger-color) 50%, var(--success-color) 50%);
        }

        .status-sickleave {
            background-color: rgba(236, 72, 153, 0.1);
            color: #be185d;
        }

        .status-sickleave::before {
            content: "";
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: #be185d;
        }

        .time-cell {
            white-space: nowrap;
            font-family: 'SF Mono', 'Courier New', monospace;
            font-size: 0.8125rem;
        }

        .overtime {
            color: var(--danger-color);
            font-weight: 600;
        }

        .leave-types-list {
            margin-top: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }

        .leave-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 500;
            background-color: var(--accent-color);
            color: white;
            border: none;
        }

        .leave-note {
            display: block;
            font-size: 0.6875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            font-style: italic;
        }

        .work-report-preview {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            color: var(--primary-color);
            transition: color 0.2s ease;
        }

        .work-report-preview:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 0;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .date-filter {
                width: 100%;
            }

            .month-year-picker {
                width: 100%;
            }

            .date-select {
                flex: 1;
            }

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .stat-card.leaves-card {
                grid-column: span 1;
            }

            .charts-container {
                grid-template-columns: 1fr;
            }

            .chart-wrapper {
                height: 250px;
                min-height: 250px;
            }

            .worksheet-table-container {
                padding: 1rem;
                margin: 0 -1rem;
                border-radius: 0;
            }

            .worksheet-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-color);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Sweet Alert customization */
        .work-report-popup .swal2-title {
            font-size: 1.25rem;
            color: var(--secondary-color);
        }

        .data-fallback-notice {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background-color: rgba(245, 158, 11, 0.1);
            border-left: 4px solid var(--warning-color);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #92400e;
            font-size: 0.875rem;
        }
        
        /* Add these new responsive styles */
        .hamburger-menu {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1000;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 1.25rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Enhanced responsive styles for all sections */
        @media (max-width: 991px) {
            /* Existing mobile styles */
            
            /* Prevent horizontal scrolling */
            html, body {
                max-width: 100%;
                overflow-x: hidden;
            }
            
            .main-content {
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
                padding-left: 10px;
                padding-right: 10px;
            }
            
            /* Make table container handle scrolling internally */
            .worksheet-table-container {
                width: 100%;
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-left: 0;
                margin-right: 0;
                padding: 0.5rem;
                border-radius: var(--radius-md);
            }
            
            .worksheet-table {
                width: 100%;
                min-width: 100%;
                table-layout: fixed;
            }
            
            /* Adjust column widths for better mobile display */
            .worksheet-table th,
            .worksheet-table td {
                padding: 0.5rem 0.4rem;
                font-size: 0.75rem;
            }
            
            .worksheet-table th:nth-child(1), 
            .worksheet-table td:nth-child(1) {
                width: 20%;
            }
            
            .worksheet-table th:nth-child(3), 
            .worksheet-table td:nth-child(3),
            .worksheet-table th:nth-child(4), 
            .worksheet-table td:nth-child(4),
            .worksheet-table th:nth-child(5), 
            .worksheet-table td:nth-child(5) {
                width: 15%;
            }
            
            .worksheet-table th:nth-child(8), 
            .worksheet-table td:nth-child(8) {
                width: 20%;
            }
            
            /* Hide more columns on mobile */
            .worksheet-table th:nth-child(2), 
            .worksheet-table td:nth-child(2),
            .worksheet-table th:nth-child(6), 
            .worksheet-table td:nth-child(6),
            .worksheet-table th:nth-child(7), 
            .worksheet-table td:nth-child(7) {
                display: none;
            }
            
            /* Make charts fit within container */
            .charts-container {
                width: 100%;
                overflow: hidden;
            }
            
            .chart-wrapper {
                width: 100%;
                overflow: hidden;
            }
            
            /* Fix container width */
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            /* Fix attendance overview width */
            .attendance-overview {
                width: 100%;
                max-width: 100%;
                margin-left: 0;
                margin-right: 0;
                padding: 0.75rem;
            }
            
            /* Fix header width */
            .header {
                width: 100%;
                max-width: 100%;
                margin-left: 0;
                margin-right: 0;
            }
            
            /* Adjust filter controls to fit width */
            .date-filter {
                width: 100%;
            }
            
            .month-year-picker {
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto auto;
                gap: 0.5rem;
                width: 100%;
            }
            
            .filter-btn {
                grid-column: span 2;
                width: 100%;
            }
        }

        /* Extra adjustments for very small screens */
        @media (max-width: 360px) {
            .main-content {
                padding-left: 5px;
                padding-right: 5px;
            }
            
            .worksheet-table th, 
            .worksheet-table td {
                padding: 0.4rem 0.3rem;
                font-size: 0.7rem;
            }
            
            /* Show only essential columns on very small screens */
            .worksheet-table th:nth-child(5), 
            .worksheet-table td:nth-child(5) {
                display: none;
            }
            
            .worksheet-table th:nth-child(1), 
            .worksheet-table td:nth-child(1) {
                width: 30%;
            }
            
            .worksheet-table th:nth-child(3), 
            .worksheet-table td:nth-child(3),
            .worksheet-table th:nth-child(4), 
            .worksheet-table td:nth-child(4) {
                width: 20%;
            }
            
            .worksheet-table th:nth-child(8), 
            .worksheet-table td:nth-child(8) {
                width: 30%;
            }
            
            /* Make status badges more compact */
            .status-badge {
                padding: 0.15rem 0.3rem;
                font-size: 0.6rem;
            }
            
            /* Adjust stat cards for very small screens */
            .stat-card {
                padding: 0.5rem;
                gap: 0.5rem;
            }
            
            .stat-icon {
                width: 2rem;
                height: 2rem;
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

        /* Photo viewer modal styles */
        .photo-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            overflow: auto;
        }
        
        .photo-modal-content {
            position: relative;
            margin: auto;
            padding: 0;
            width: 90%;
            max-width: 700px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .photo-close {
            position: absolute;
            top: -30px;
            right: 0;
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1060;
        }
        
        .punch-photo {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .photo-caption {
            text-align: center;
            color: white;
            padding: 10px 0;
            font-size: 1rem;
        }
        
        .photo-icon {
            margin-left: 5px;
            color: var(--primary-color);
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .photo-icon:hover {
            color: var(--secondary-color);
        }
        
        /* Responsive adjustments for photo viewer */
        @media (max-width: 576px) {
            .photo-modal-content {
                width: 95%;
            }
            
            .photo-caption {
                font-size: 0.875rem;
            }
        }

        /* Add these styles to your CSS section */
        .fa-file-excel {
            color: #1D6F42 !important; /* Excel green color */
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .fa-file-excel:hover {
            transform: scale(1.2);
        }

        /* Add tooltip style */
        th {
            position: relative;
        }

        [title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 100;
            pointer-events: none;
        }

        @media (max-width: 991px) {
            /* Make sure the Excel icon is visible on mobile */
            .worksheet-table th:nth-child(7), 
            .worksheet-table td:nth-child(7) {
                display: table-cell;
                width: 15%;
            }
        }
    
    
</style>
</head>
<body>
    <?php include 'components/minimal_sidebar.php'; ?>
    
    <div class="dashboard-container">

        <!-- Main Content -->
        <div class="main-content msb-content" id="mainContent">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-calendar-alt" style="margin-right: 0.75rem; color: var(--primary-color);"></i>Work Sheet History</h1>
                    <div class="date-filter">
                        <div class="month-year-picker">
                            <select id="monthSelect" class="date-select">
                                <?php
                                $months = [
                                    1 => 'January', 2 => 'February', 3 => 'March',
                                    4 => 'April', 5 => 'May', 6 => 'June',
                                    7 => 'July', 8 => 'August', 9 => 'September',
                                    10 => 'October', 11 => 'November', 12 => 'December'
                                ];
                                $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
                                foreach ($months as $num => $name) {
                                    $selected = $currentMonth === $num ? 'selected' : '';
                                    echo "<option value=\"$num\" $selected>$name</option>";
                                }
                                ?>
                            </select>

                            <select id="yearSelect" class="date-select">
                                <?php
                                $currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                                $startYear = 2020;
                                $endYear = (int)date('Y');
                                
                                for ($year = $endYear; $year >= $startYear; $year--) {
                                    $selected = $currentYear === $year ? 'selected' : '';
                                    echo "<option value=\"$year\" $selected>$year</option>";
                                }
                                ?>
                            </select>

                            <button class="filter-btn" onclick="filterMonthYear()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>

                <div class="attendance-overview">
                    <div class="overview-header">
                        <h2><i class="fas fa-chart-pie" style="margin-right: 0.5rem; color: var(--primary-color);"></i>Attendance Overview</h2>
                    </div>
                    
                    <div class="overview-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Present Days</h3>
                                <p id="presentDays">0</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #3a86ff, #0582ca);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Hours</h3>
                                <p id="totalHours">0</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #7209b7, #560bad);">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Overtime Hours (≥1:30)</h3>
                                <p id="overtimeHours">0</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Attendance Rate</h3>
                                <p id="attendanceRate">0%</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Late Punches (15+ min)</h3>
                                <p id="latePunches">0</p>
                                <small style="display: block; font-size: 0.6875rem; color: var(--text-secondary);">Excludes short leaves</small>
                                <small id="shortLeaveAdjustment" style="display: block; font-size: 0.6875rem; color: var(--text-secondary);"></small>
                            </div>
                        </div>
                        
                        <div class="stat-card leaves-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <i class="fas fa-sign-out-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Leaves Taken</h3>
                                <p id="leavesTaken">0</p>
                                <div id="leaveTypesList" class="leave-types-list"></div>
                                <small class="leave-note">Shows only approved leaves</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="charts-container">
                        <div class="chart-wrapper">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="hoursChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="worksheet-table-container">
                    <table class="worksheet-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Shift</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Working Hours</th>
                                <th>Overtime</th>
                                <th>
                                    Work Report
                                    <i class="fas fa-file-excel photo-icon" style="color: #1D6F42; margin-left: 5px;" 
                                       onclick="exportWorkReportsToExcel()" 
                                       title="Export work reports to Excel"></i>
                                </th>
                                
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['formatted_date']; ?></td>
                                    <td><?php echo $row['shift_name'] . ' (' . $row['shift_start'] . ' - ' . $row['shift_end'] . ')'; ?></td>
                                    <td class="time-cell">
                                        <?php echo $row['formatted_punch_in']; ?>
                                        <?php if (!empty($row['punch_in_photo'])): ?>
                                            <i class="fas fa-folder photo-icon" onclick="showPunchPhoto('<?php echo htmlspecialchars($row['punch_in_photo']); ?>', 'Punch In - <?php echo $row['formatted_date']; ?> (<?php echo $row['formatted_punch_in']; ?>')"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="time-cell">
                                        <?php echo $row['formatted_punch_out'] ?: '-'; ?>
                                        <?php if (!empty($row['punch_out_photo'])): ?>
                                            <i class="fas fa-folder photo-icon" onclick="showPunchPhoto('<?php echo htmlspecialchars($row['punch_out_photo']); ?>', 'Punch Out - <?php echo $row['formatted_date']; ?> (<?php echo $row['formatted_punch_out']; ?>')"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="time-cell"><?php echo $row['working_hours'] ?: '-'; ?></td>
                                    <td class="time-cell <?php echo $row['calculated_overtime'] !== '00:00:00' ? 'overtime' : ''; ?>">
                                        <?php echo $row['calculated_overtime'] !== '00:00:00' ? $row['calculated_overtime'] : '-'; ?>
                                    </td>
                                    <td class="work-report-cell">
                                        <?php if ($row['work_report']): ?>
                                            <div class="work-report-preview" onclick="showWorkReport('<?php echo htmlspecialchars($row['work_report'], ENT_QUOTES); ?>', '<?php echo $row['formatted_date']; ?>')">
                                                <?php echo substr($row['work_report'], 0, 50) . '...'; ?>
                                            </div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php 
                                        // Determine status badge styling and label
                                        $status = strtolower($row['status']);
                                        $status_class = 'status-' . $status;
                                        $status_label = ucfirst($row['status']);
                                        
                                        // Handle specific status types
                                        if ($status == 'leave' || $status == 'on leave') {
                                            $status_class = 'status-leave';
                                            // If there's a leave type available, use it instead of generic "Leave"
                                            if (!empty($row['leave_type'])) {
                                                $status_label = $row['leave_type'];
                                            } else {
                                                $status_label = 'Leave';
                                            }
                                        } elseif ($status == 'on leave') {
                                            $status_class = 'status-onleave';
                                            $status_label = 'On Leave';
                                        } elseif ($status == 'half day' || $status == 'halfday') {
                                            $status_class = 'status-halfday';
                                            $status_label = 'Half Day';
                                        } elseif ($status == 'weekend' || $status == 'weekly off') {
                                            $status_class = 'status-weekend';
                                            $status_label = 'Weekend';
                                        } elseif ($status == 'sick' || $status == 'sick leave') {
                                            $status_class = 'status-sickleave';
                                            $status_label = 'Sick Leave';
                                        } elseif ($status == 'present' && isset($row['is_late']) && $row['is_late'] == 1) {
                                            // Check if this late punch was waved off using the database column
                                            if (isset($row['waved_off']) && $row['waved_off'] == 1) {
                                                $status_class = 'status-present';
                                                $status_label = 'Present';
                                            } else {
                                                $status_class = 'status-late';
                                                $status_label = 'Late';
                                            }
                                        }
                                        
                                        // Add an icon based on status
                                        $status_icon = '';
                                        if ($status == 'present' || $status_label == 'Present') {
                                            $status_icon = '<i class="fas fa-check-circle" style="margin-right: 0.25rem;"></i>';
                                        } elseif ($status == 'absent') {
                                            $status_icon = '<i class="fas fa-times-circle" style="margin-right: 0.25rem;"></i>';
                                        } elseif ($status == 'leave' || $status == 'on leave' || $status == 'sick leave') {
                                            $status_icon = '<i class="fas fa-calendar-minus" style="margin-right: 0.25rem;"></i>';
                                        } elseif ($status == 'half day' || $status == 'halfday') {
                                            $status_icon = '<i class="fas fa-adjust" style="margin-right: 0.25rem;"></i>';
                                        } elseif ($status == 'weekend' || $status == 'weekly off') {
                                            $status_icon = '<i class="fas fa-calendar-day" style="margin-right: 0.25rem;"></i>';
                                        } elseif ($status == 'holiday') {
                                            $status_icon = '<i class="fas fa-gifts" style="margin-right: 0.25rem;"></i>';
                                        } elseif ($status_label == 'Late') {
                                            $status_icon = '<i class="fas fa-clock" style="margin-right: 0.25rem;"></i>';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_icon . $status_label; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this at the end of the body for the photo viewer modal -->
    <div id="photoModal" class="photo-modal">
        <div class="photo-modal-content">
            <span class="photo-close">&times;</span>
            <img id="punchPhoto" class="punch-photo" src="" alt="Punch Photo">
            <div id="photoCaption" class="photo-caption"></div>
        </div>
    </div>

    <script>
        let attendanceChart = null;
        let hoursChart = null;
        let currentMonth = <?php echo $current_month; ?>;
        let currentYear = <?php echo $current_year; ?>;
        let phpDebugInfo = <?php echo json_encode($debug_info); ?>;
        let latePunchesCount = <?php echo $late_punches; ?>;
        let totalLatePunches = <?php echo $total_late_punches; ?>;
        let wavedOffCount = <?php echo $waved_off_count; ?>;
        let totalLeavesCount = <?php echo $total_leaves; ?>;
        let shortLeavesUsed = <?php echo $short_leaves_used; ?>;
        let shortLeavesAvailable = <?php echo max(0, 2 - $short_leaves_used); ?>;
        let leaveTypesFormatted = `<?php echo $leave_types_formatted; ?>`;
        
        // Store all attendance data for local filtering
        let attendanceData = [];

        // Initialize everything when the DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            collectTableData();
            calculateAndDisplayStats();
        });
        
        // Ensure clean chart initialization
        window.onload = function() {
            // Give a little time for the DOM to fully render
            setTimeout(() => {
                initializeCharts();
            }, 200);
        };
        
        function collectTableData() {
            // Get all rows from the table
            const rows = document.querySelectorAll('.worksheet-table tbody tr');
            
            attendanceData = []; // Clear existing data
            
                rows.forEach((row) => {
                const dateCell = row.querySelector('td:nth-child(1)');
                const shiftCell = row.querySelector('td:nth-child(2)');
                const punchInCell = row.querySelector('td:nth-child(3)');
                const punchOutCell = row.querySelector('td:nth-child(4)');
                const hoursCell = row.querySelector('td:nth-child(5)');
                const overtimeCell = row.querySelector('td:nth-child(6)');
                const workReportCell = row.querySelector('td:nth-child(7)');
                const statusCell = row.querySelector('td:nth-child(8) .status-badge');
                
                if (dateCell && hoursCell && overtimeCell && statusCell) {
                    const date = dateCell.textContent.trim();
                    const hours = hoursCell.textContent.trim() !== '-' ? hoursCell.textContent.trim() : '00:00';
                    const overtime = overtimeCell.textContent.trim() !== '-' ? overtimeCell.textContent.trim() : '00:00';
                    const status = statusCell.textContent.trim().toLowerCase();
                    
                    // Get shift times from the shift cell text which is in format "Shift Name (HH:MM AM/PM - HH:MM AM/PM)"
                    const shiftMatch = shiftCell.textContent.match(/\((.*?)\s*-\s*(.*?)\)/);
                    const shiftStart = shiftMatch ? shiftMatch[1].trim() : '09:00 AM';
                    const shiftEnd = shiftMatch ? shiftMatch[2].trim() : '06:00 PM';
                    
                    // Parse the date in DD-MM-YYYY format
                    const dateParts = date.split('-');
                    const day = parseInt(dateParts[0], 10);
                    const month = parseInt(dateParts[1], 10);
                    const year = parseInt(dateParts[2], 10);
                    
                    // Create date object (month is 0-indexed in JavaScript Date)
                    const dateObj = new Date(year, month - 1, day);
                    
                    const entry = {
                        date: date,
                        dateObj: dateObj,
                        day: day,
                        month: month,
                        year: year,
                        hours: hours,
                        overtime: overtime,
                        status: status,
                        shiftStart: shiftStart,
                        shiftEnd: shiftEnd,
                        punchIn: punchInCell.textContent.trim(),
                        punchOut: punchOutCell.textContent.trim() !== '-' ? punchOutCell.textContent.trim() : ''
                    };
                    
                    attendanceData.push(entry);
                }
            });
        }
        
        function calculateStats(data) {
            // Check if we have any data at all
            if (data.length === 0) {
                return {
                    presentDays: 0,
                    totalHours: "00:00",
                    overtimeHours: "00:00",
                    attendanceRate: 0,
                    totalWorkMinutes: 0,
                    filteredOvertimeMinutes: 0,
                    regularMinutes: 0,
                    data: [],
                    isUsingFallback: false
                };
            }
            
            // Filter data for the current month/year
            const filteredData = data.filter(item => {
                return item.month === currentMonth && item.year === currentYear;
            });
            
            // If no data for this month, try to find closest month with data
            let dataToUse = filteredData;
            let isUsingFallback = false;
            
            if (filteredData.length === 0) {
                // Find months with data
                const availableMonths = {};
                data.forEach(item => {
                    availableMonths[`${item.month}-${item.year}`] = true;
                });
                
                const availableMonthsList = Object.keys(availableMonths);
                
                if (availableMonthsList.length > 0) {
                    // Just use all data if we can't find the selected month
                    dataToUse = data;
                    isUsingFallback = true;
                }
            }
            
            // Count days by status
            let presentDays = 0;
            let weekendDays = 0;
            let holidayDays = 0;
            let leaveDays = 0;
            let totalWorkMinutes = 0;
            let filteredOvertimeMinutes = 0;
            let workDatesMap = {};
            
            // First pass: collect all dates and their statuses
            dataToUse.forEach(item => {
                const dateKey = `${item.year}-${item.month}-${item.day}`;
                workDatesMap[dateKey] = item.status.toLowerCase();
                
                if (item.status.includes('present') || item.status.includes('late')) {
                    presentDays++;
                    
                    // Calculate total work minutes
                    const workMinutes = convertTimeToMinutes(item.hours);
                    totalWorkMinutes += workMinutes;
                    
                    // Get overtime minutes
                    const overtimeMinutes = convertOvertimeToMinutes(item.overtime);
                    
                    // Only count overtime that's 90 minutes (1:30) or more
                    if (overtimeMinutes >= 90) {
                        filteredOvertimeMinutes += overtimeMinutes;
                    }
                }
                else if (item.status.includes('weekend') || item.status.includes('weekly off')) {
                    weekendDays++;
                }
                else if (item.status.includes('holiday')) {
                    holidayDays++;
                }
                else if (item.status.includes('leave') || item.status.includes('sick') || item.status === 'on leave') {
                    leaveDays++;
                }
            });
            
            // Calculate attendance rate - exclude weekends, holidays from total expected days
            const businessDays = getBusinessDaysInMonth(currentYear, currentMonth);
            const expectedWorkDays = businessDays - holidayDays; // Exclude holidays from business days
            
            // Calculate attendance rate: present days divided by (expected work days minus leave days)
            const denominator = Math.max(1, expectedWorkDays - leaveDays); // Avoid division by zero
            const attendanceRate = Math.round((presentDays / denominator) * 100);
            
            // Format hours
            const totalHours = formatMinutesToTime(totalWorkMinutes);
            const overtimeHours = formatMinutesToTime(filteredOvertimeMinutes);
            
            return {
                presentDays: presentDays,
                totalHours: totalHours,
                overtimeHours: overtimeHours,
                attendanceRate: attendanceRate,
                totalWorkMinutes: totalWorkMinutes,
                filteredOvertimeMinutes: filteredOvertimeMinutes,
                regularMinutes: totalWorkMinutes - filteredOvertimeMinutes,
                data: dataToUse,
                isUsingFallback: isUsingFallback
            };
        }
        
        // Helper function to count business days in a month (Mon-Fri)
        function getBusinessDaysInMonth(year, month) {
            const daysInMonth = new Date(year, month, 0).getDate();
            let businessDays = 0;
            
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month - 1, day);
                const dayOfWeek = date.getDay();
                
                // 0 = Sunday, 6 = Saturday
                if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                    businessDays++;
                }
            }
            
            return businessDays;
        }
        
        function convertTimeToMinutes(timeString) {
            if (!timeString || timeString === '-') return 0;
            
            const parts = timeString.split(':');
            if (parts.length < 2) return 0;
            
            const hours = parseInt(parts[0], 10) || 0;
            const minutes = parseInt(parts[1], 10) || 0;
            
            return (hours * 60) + minutes;
        }
        
        // Add a specific function for overtime calculation respecting 30-min increments
        function convertOvertimeToMinutes(overtimeString) {
            if (!overtimeString || overtimeString === '-' || overtimeString === '00:00:00') return 0;
            
            const parts = overtimeString.split(':');
            if (parts.length < 2) return 0;
            
            const hours = parseInt(parts[0], 10) || 0;
            const minutes = parseInt(parts[1], 10) || 0;
            
            // Only count full hours and exact half hours
            return (hours * 60) + (minutes === 30 ? 30 : 0);
        }
        
        function formatMinutesToTime(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            
            // For overtime display, only show 00 or 30 for minutes
            if (mins > 0 && mins < 30) {
                return `${String(hours).padStart(2, '0')}:00`;
            } else if (mins >= 30) {
                return `${String(hours).padStart(2, '0')}:30`;
            }
            
            return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
        }

        function prepareChartData(filteredData) {
            // Sort data by date
            const sortedData = [...filteredData].sort((a, b) => a.dateObj - b.dateObj);
            
            // Prepare data for charts
            let dates = [];
            let workingHours = [];
            
            // Format dates for better display (DD/MM)
            sortedData.forEach(item => {
                // Convert DD-MM-YYYY to DD/MM format for display
                const formattedDate = item.day + '/' + item.month;
                dates.push(formattedDate);
                
                // Convert hours (HH:MM) to decimal hours for the chart
                const hourMinutes = item.hours.split(':');
                const hours = parseInt(hourMinutes[0], 10);
                const minutes = parseInt(hourMinutes[1], 10);
                const decimalHours = hours + (minutes / 60);
                
                workingHours.push(parseFloat(decimalHours.toFixed(2)));
            });
            
            return {
                dates: dates,
                workingHours: workingHours
            };
        }

        function calculateAndDisplayStats() {
            const stats = calculateStats(attendanceData);
            
            // Update dashboard stats
            document.getElementById('presentDays').textContent = stats.presentDays;
            document.getElementById('totalHours').textContent = stats.totalHours;
            document.getElementById('overtimeHours').textContent = stats.overtimeHours;
            document.getElementById('attendanceRate').textContent = `${stats.attendanceRate}%`;
            
            // Update late punches and leaves stats from server data
            document.getElementById('latePunches').textContent = latePunchesCount;
            document.getElementById('leavesTaken').textContent = totalLeavesCount;
            
            // Update short leave adjustment text
            const shortLeaveAdjustment = document.getElementById('shortLeaveAdjustment');
            if (shortLeaveAdjustment) {
                if (wavedOffCount > 0) {
                    shortLeaveAdjustment.textContent = `${totalLatePunches} total - ${wavedOffCount} waved off = ${latePunchesCount} counted`;
                } else if (shortLeavesAvailable > 0) {
                    shortLeaveAdjustment.textContent = `${shortLeavesAvailable} Short Leave${shortLeavesAvailable > 1 ? 's' : ''} available`;
                } else {
                    shortLeaveAdjustment.textContent = `All Short Leaves used (2/2)`;
                }
            }
            
            // Update leave types breakdown
            const leaveTypesList = document.getElementById('leaveTypesList');
            if (leaveTypesList) {
                // Directly set innerHTML as the server already provides HTML-formatted content
                leaveTypesList.innerHTML = leaveTypesFormatted || '<span class="leave-type-badge">None</span>';
            }
            
            // Update the card header to indicate the minimum overtime threshold
            document.querySelector('.stat-card:nth-child(3) .stat-info h3').textContent = 'Overtime Hours (≥1:30)';
            
            // Show notification if using fallback data (if property exists)
            if (stats.isUsingFallback) {
                // Add a warning banner to the overview header
                const overviewHeader = document.querySelector('.overview-header');
                if (overviewHeader && !document.querySelector('.data-fallback-notice')) {
                    const notice = document.createElement('div');
                    notice.className = 'data-fallback-notice';
                    notice.innerHTML = `
                        <div style="background-color: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 6px; font-size: 14px; margin-top: 10px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            No data available for ${currentMonth}/${currentYear}. Showing all available data.
                        </div>
                    `;
                    overviewHeader.appendChild(notice);
                }
            }
            
            // Calculate values for hours distribution chart
            // Convert time strings to decimal hours for the chart
            const regularHours = stats.regularMinutes / 60;
            const overtimeHours = stats.filteredOvertimeMinutes / 60;
            
            // Save data for charts to be initialized later
            window.chartData = {
                ...prepareChartData(stats.data),
                regularHours: regularHours,
                overtimeHours: overtimeHours
            };
        }

        function initializeCharts() {
            try {
                // Clear any existing charts first
                if (attendanceChart) {
                    attendanceChart.destroy();
                    attendanceChart = null;
                }
                
                if (hoursChart) {
                    hoursChart.destroy();
                    hoursChart = null;
                }
                
                // Get saved chart data
                const data = window.chartData || {
                    dates: ['No data'],
                    workingHours: [0],
                    regularHours: 0,
                    overtimeHours: 0
                };
                
                // Create attendance chart
                const ctx1 = document.getElementById('attendanceChart').getContext('2d');
                attendanceChart = new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: data.dates,
                        datasets: [{
                            label: 'Working Hours',
                            data: data.workingHours,
                            backgroundColor: '#3498db'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                
                // Create hours distribution chart
                const ctx2 = document.getElementById('hoursChart').getContext('2d');
                hoursChart = new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: ['Regular Hours', 'Overtime Hours (≥1:30)'],
                        datasets: [{
                            data: [data.regularHours, data.overtimeHours],
                            backgroundColor: ['#3498db', '#e74c3c']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            } catch (error) {
                // Silently catch errors but don't log them
            }
        }

        function filterMonthYear() {
            const month = parseInt(document.getElementById('monthSelect').value);
            const year = parseInt(document.getElementById('yearSelect').value);
            
            window.location.href = `work_sheet.php?month=${month}&year=${year}`;
        }

        function showWorkReport(report, date) {
            Swal.fire({
                title: `Work Report - ${date}`,
                html: `<div style="text-align: left; padding: 1.25rem; background-color: #f7f9fc; border-radius: 0.5rem; margin-top: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <pre style="white-space: pre-wrap; font-family: inherit; margin: 0; font-size: 0.875rem;">${report}</pre>
                </div>`,
                width: 600,
                confirmButtonText: 'Close',
                confirmButtonColor: '#4361ee',
                customClass: {
                    popup: 'work-report-popup'
                }
            });
        }

        // Sidebar functionality is now handled by minimal_sidebar.php

        function showPunchPhoto(photoUrl, caption) {
            // Get the modal
            const modal = document.getElementById("photoModal");
            const photoImg = document.getElementById("punchPhoto");
            const photoCaption = document.getElementById("photoCaption");
            const closeBtn = document.querySelector(".photo-close");
            
            // Set the image source and caption
            photoImg.src = photoUrl;
            photoCaption.textContent = caption;
            
            // Show the modal
            modal.style.display = "block";
            
            // Close the modal when clicking the close button
            closeBtn.onclick = function() {
                modal.style.display = "none";
            }
            
            // Close the modal when clicking outside of it
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        }

        function exportWorkReportsToExcel() {
            // Show loading indicator
            Swal.fire({
                title: 'Generating Excel File',
                html: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Collect data from the table
            const rows = document.querySelectorAll('.worksheet-table tbody tr');
            let workReportData = [];
            
            rows.forEach((row) => {
                const dateCell = row.querySelector('td:nth-child(1)');
                const workReportCell = row.querySelector('td:nth-child(7)');
                const statusCell = row.querySelector('td:nth-child(8) .status-badge');
                
                if (dateCell && workReportCell) {
                    const date = dateCell.textContent.trim();
                    
                    // Get day of week
                    const dateParts = date.split('-');
                    const day = parseInt(dateParts[0], 10);
                    const month = parseInt(dateParts[1], 10) - 1; // JS months are 0-indexed
                    const year = parseInt(dateParts[2], 10);
                    const dateObj = new Date(year, month, day);
                    const dayOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][dateObj.getDay()];
                    
                    // Get work report content
                    let workReport = '-';
                    if (workReportCell.querySelector('.work-report-preview')) {
                        // Extract the original work report from the onclick attribute
                        const onclickAttr = workReportCell.querySelector('.work-report-preview').getAttribute('onclick');
                        if (onclickAttr) {
                            const match = onclickAttr.match(/showWorkReport\('([^']+)',/);
                            if (match && match[1]) {
                                workReport = match[1].replace(/\\'/g, "'").replace(/\\"/g, '"');
                            } else {
                                workReport = workReportCell.querySelector('.work-report-preview').textContent.trim();
                            }
                        }
                    }
                    
                    // Get status
                    const status = statusCell ? statusCell.textContent.trim() : '-';
                    
                    workReportData.push({
                        date: date,
                        day: dayOfWeek,
                        status: status,
                        workReport: workReport
                    });
                }
            });
            
            // Create worksheet data with headers
            const wsData = [
                ['Date', 'Day', 'Status', 'Work Report'] // Headers
            ];
            
            // Add data rows
            workReportData.forEach(item => {
                wsData.push([item.date, item.day, item.status, item.workReport]);
            });
            
            // Create workbook and worksheet
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(wsData);
            
            // Set column widths
            const cols = [
                { wch: 12 },  // Date column width
                { wch: 10 },  // Day column width
                { wch: 15 },  // Status column width
                { wch: 80 }   // Work Report column width
            ];
            ws['!cols'] = cols;
            
            // Apply bold formatting to header row
            const headerRange = XLSX.utils.decode_range(ws['!ref']);
            for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                const cellAddress = XLSX.utils.encode_cell({ r: 0, c: C });
                if (!ws[cellAddress]) continue;
                
                // Create cell object if it doesn't exist
                if (!ws[cellAddress].s) ws[cellAddress].s = {};
                
                // Apply bold font
                ws[cellAddress].s = { 
                    font: { bold: true },
                    alignment: { horizontal: 'center' },
                    fill: { fgColor: { rgb: "EEEEEE" } } // Light gray background
                };
            }
            
            // Set the month name and year
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                'July', 'August', 'September', 'October', 'November', 'December'];
            const sheetName = `${monthNames[currentMonth-1]} ${currentYear}`;
            const fileName = `Work_Reports_${monthNames[currentMonth-1]}_${currentYear}.xlsx`;
            
            // Add worksheet to workbook
            XLSX.utils.book_append_sheet(wb, ws, sheetName);
            
            // Generate Excel file and trigger download
            setTimeout(() => {
                // Write workbook and download
                XLSX.writeFile(wb, fileName);
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Excel File Ready',
                    text: `Downloaded ${fileName}`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 500);
        }
    </script>
</body>
</html>