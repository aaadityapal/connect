<?php
require_once 'config/db_connect.php';

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Add these functions at the top of the file
function getDayNumber($dayName) {
    $days = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7
    ];
    return $days[strtolower($dayName)] ?? null;
}

// First, let's separate the working days calculation into a separate function
function calculateWorkingDays($conn, $month_start, $month_end) {
    $query = "SELECT 
        us.user_id,
        DAY(LAST_DAY(?)) - COUNT(DISTINCT CASE 
            WHEN DAYOFWEEK(dates.date) IN (
                SELECT CAST(
                    CASE LOWER(TRIM(weekly_offs))
                        WHEN 'monday' THEN 2
                        WHEN 'tuesday' THEN 3
                        WHEN 'wednesday' THEN 4
                        WHEN 'thursday' THEN 5
                        WHEN 'friday' THEN 6
                        WHEN 'saturday' THEN 7
                        WHEN 'sunday' THEN 1
                    END AS UNSIGNED
                ) FROM user_shifts us2
                WHERE us2.user_id = us.user_id
                AND us2.effective_from <= dates.date
                AND (us2.effective_to IS NULL OR us2.effective_to >= dates.date)
            ) OR dates.date IN (
                SELECT holiday_date 
                FROM office_holidays 
                WHERE holiday_date BETWEEN ? AND ?
            )
            THEN dates.date
        END) as working_days
    FROM (
        SELECT DATE_ADD(?, INTERVAL n-1 DAY) as date
        FROM (
            SELECT @row := @row + 1 as n
            FROM (SELECT 0 UNION ALL SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) t1,
                 (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) t2,
                 (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2) t3,
                 (SELECT @row:=0) r
        ) numbers
        WHERE n <= DAY(LAST_DAY(?))
    ) dates
    CROSS JOIN user_shifts us
    WHERE us.effective_from <= dates.date
    AND (us.effective_to IS NULL OR us.effective_to >= dates.date)
    GROUP BY us.user_id";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param('sssss', $month_start, $month_start, $month_end, $month_start, $month_start);
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return [];
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Result fetch failed: " . $stmt->error);
        return [];
    }
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Add at the beginning of the file
function getCachedData($key) {
    $cache_file = "cache/{$key}.json";
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < 3600)) { // 1 hour cache
        return json_decode(file_get_contents($cache_file), true);
    }
    return null;
}

function setCachedData($key, $data) {
    if (!is_dir('cache')) {
        mkdir('cache', 0777, true);
    }
    file_put_contents("cache/{$key}.json", json_encode($data));
}

// Add this before the cache key definition
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Then the cache key
$cache_key = "salary_overview_{$selected_month}_page_{$page}";
$users = getCachedData($cache_key);

if ($users === null) {
    // First, get the working days calculation
    $working_days_result = calculateWorkingDays($conn, $month_start, $month_end);

    // Create a temporary table to store working days
    $create_temp_table = "CREATE TEMPORARY TABLE temp_working_days (
        user_id int,
        working_days int
    )";
    $conn->query($create_temp_table);

    // Insert the working days data into temporary table
    $insert_temp = "INSERT INTO temp_working_days (user_id, working_days) VALUES (?, ?)";
    $stmt_insert = $conn->prepare($insert_temp);
    foreach ($working_days_result as $wd) {
        $stmt_insert->bind_param('ii', $wd['user_id'], $wd['working_days']);
        $stmt_insert->execute();
    }

    // Now modify the main query to use the temporary table
    $query = "SELECT 
        u.id, 
        u.username, 
        u.base_salary as monthly_salary,
        COALESCE(wd.working_days, 0) as total_working_days,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN DATE(a.date) END) as present_days,
        COALESCE(l.leave_count, 0) as leave_taken,
        COALESCE(sl.short_leave_count, 0) as short_leave,
        COALESCE(late.late_count, 0) as late,
        COALESCE(ot.total_hours, '0:00') as overtime_hours,
        COALESCE(s.travel_amount, 0) as travel_amount,
        COALESCE(s.misc_amount, 0) as misc_amount
    FROM users u
    LEFT JOIN temp_working_days wd ON u.id = wd.user_id
    LEFT JOIN attendance a ON u.id = a.user_id 
        AND DATE(a.date) BETWEEN ? AND ?
    LEFT JOIN (
        SELECT user_id, COUNT(*) as leave_count
        FROM leave_request
        WHERE status = 'approved' AND hr_approval = 'approved'
        AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?))
        GROUP BY user_id
    ) l ON u.id = l.user_id
    LEFT JOIN (
        SELECT user_id, COUNT(*) as short_leave_count
        FROM leave_request lr
        JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.status = 'approved' AND lr.hr_approval = 'approved'
        AND (lt.name LIKE '%short%' OR lt.name LIKE '%half%')
        AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?))
        GROUP BY user_id
    ) sl ON u.id = sl.user_id
    LEFT JOIN (
        SELECT att.user_id, COUNT(*) as late_count
        FROM attendance att
        JOIN user_shifts us ON us.user_id = att.user_id
        JOIN shifts s ON s.id = us.shift_id
        WHERE DATE(att.date) BETWEEN ? AND ?
        AND att.status = 'present'
        AND TIME(att.punch_in) > ADDTIME(TIME(s.start_time), '00:15:00')
        GROUP BY att.user_id
    ) late ON u.id = late.user_id
    LEFT JOIN (
        SELECT 
            user_id,
            SEC_TO_TIME(SUM(TIME_TO_SEC(overtime_hours))) as total_hours
        FROM attendance
        WHERE DATE(date) BETWEEN ? AND ?
        AND overtime_hours IS NOT NULL
        GROUP BY user_id
    ) ot ON u.id = ot.user_id
    LEFT JOIN salary_details s ON u.id = s.user_id 
        AND DATE_FORMAT(s.month_year, '%Y-%m') = ?
    WHERE u.deleted_at IS NULL AND u.status = 'active'
    GROUP BY u.id, u.username, u.base_salary";

    // Add pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 50; // Number of records per page
    $offset = ($page - 1) * $limit;
    $query .= " LIMIT ? OFFSET ?";

    // Execute the query with pagination
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param('sssssssssssssssii', 
        $month_start, $month_end,                  // attendance date range
        $month_start, $month_end,                  // leave request start date range
        $month_start, $month_end,                  // leave request end date range
        $month_start, $month_end,                  // short leave start date range
        $month_start, $month_end,                  // short leave end date range
        $month_start, $month_end,                  // late attendance date range
        $month_start, $month_end,                  // overtime date range
        $selected_month,                           // salary details month
        $limit, $offset                            // pagination
    );

    try {
        if (!$stmt->execute()) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception("Result fetch failed: " . $stmt->error);
        }
        
        $users = $result->fetch_all(MYSQLI_ASSOC);
        setCachedData($cache_key, $users);
    } catch (Exception $e) {
        error_log("Salary Overview Error: " . $e->getMessage());
        die("An error occurred while processing the salary data. Please try again later.");
    }

    // Drop the temporary table after use
    $conn->query("DROP TEMPORARY TABLE IF EXISTS temp_working_days");
}

// Function to update present days in salary_details
function updatePresentDays($conn, $userId, $monthYear, $presentDays) {
    $query = "INSERT INTO salary_details 
        (user_id, month_year, present_days) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE present_days = VALUES(present_days)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isi', $userId, $monthYear, $presentDays);
    return $stmt->execute();
}

// Update present days for each user
foreach ($users as $user) {
    updatePresentDays($conn, $user['id'], $month_start, $user['present_days']);
}

// Add this PHP function to fetch weekly offs
function getWeeklyOffs($conn, $userId, $date) {
    $query = "SELECT weekly_offs 
              FROM user_shifts 
              WHERE user_id = ? 
              AND effective_from <= ?
              AND (effective_to IS NULL OR effective_to >= ?)
              ORDER BY effective_from DESC 
              LIMIT 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $userId, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row ? explode(',', $row['weekly_offs']) : [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Overview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        /* Root Variables */
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #343a40;
            --light: #f8f9fa;
            --border: #e9ecef;
            --text: #212529;
            --text-muted: #6c757d;
            --shadow: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
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

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px);
        }

        .sidebar nav a {
            text-decoration: none;
        }

        .nav-link {
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: #4361ee;
            background-color: #F3F4FF;
        }

        .nav-link.active {
            background-color: #F3F4FF;
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #4361ee;
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout Link */
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

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        /* Toggle Button */
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

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        /* Your existing styles */
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --background-color: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #1e293b;
            --text-light: #64748b;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: var(--text-color);
            font-size: 24px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h1 i {
            color: var(--primary-color);
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: #f1f5f9;
            color: var(--text-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        td {
            color: var(--text-light);
        }

        .text-right {
            text-align: right;
        }

        /* Month Navigation Styles */
        .month-navigation {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 15px;
            margin-bottom: 25px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .date-picker {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-color);
            outline: none;
            min-width: 200px;
            cursor: pointer;
        }

        .date-picker:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        }

        .month-display {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
            min-width: 150px;
            text-align: center;
        }

        .nav-btn {
            padding: 8px 16px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-color);
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .export-btn {
            padding: 8px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s ease;
        }

        .export-btn:hover {
            background: var(--secondary-color);
        }

        /* Action Buttons */
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            margin: 0 3px;
        }

        .btn-edit {
            background: #f0f9ff;
            color: #0369a1;
        }

        .btn-edit:hover {
            background: #e0f2fe;
        }

        .btn-view {
            background: #f0fdf4;
            color: #166534;
        }

        .btn-view:hover {
            background: #dcfce7;
        }

        /* Amount Cells */
        .amount {
            font-family: 'Monaco', monospace;
            font-weight: 500;
        }

        /* Status Indicators */
        .status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-approved {
            background: #f0fdf4;
            color: #166534;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .container {
                padding: 15px;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .month-navigation {
                flex-wrap: wrap;
                justify-content: center;
            }

            .month-display {
                order: -1;
                width: 100%;
                margin-bottom: 10px;
            }
        }

        /* Enhanced Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn i {
            font-size: 14px;
        }

        /* Primary Button */
        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.1);
        }

        /* Info Button */
        .btn-info {
            background: #0ea5e9;
            color: white;
        }

        .btn-info:hover {
            background: #0284c7;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(14, 165, 233, 0.1);
        }

        /* Success Button */
        .btn-success {
            background: #22c55e;
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(34, 197, 94, 0.1);
        }

        /* Warning Button */
        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(245, 158, 11, 0.1);
        }

        /* Danger Button */
        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.1);
        }

        /* Outline Buttons */
        .btn-outline {
            background: transparent;
            border: 1px solid;
        }

        .btn-outline-primary {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .btn-outline-primary:hover {
            background: #3b82f6;
            color: white;
        }

        /* Button Sizes */
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        .btn-lg {
            padding: 10px 20px;
            font-size: 16px;
        }

        /* Button Groups */
        .btn-group {
            display: flex;
            gap: 8px;
        }

        /* Export Button Enhancement */
        .export-btn {
            background: linear-gradient(to right, #3b82f6, #2563eb);
            padding: 8px 20px;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
        }

        .export-btn:hover {
            background: linear-gradient(to right, #2563eb, #1d4ed8);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        /* Action Buttons in Table */
        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: flex-start;
            align-items: center;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .action-btn i {
            font-size: 14px;
        }

        .btn-edit {
            background: #f0f9ff;
            color: #0369a1;
        }

        .btn-edit:hover {
            background: #e0f2fe;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(3, 105, 161, 0.1);
        }

        .btn-view {
            background: #f0fdf4;
            color: #166534;
        }

        .btn-view:hover {
            background: #dcfce7;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(22, 101, 52, 0.1);
        }

        /* Disabled Button State */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .button-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
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
            <a href="salary_overview.php" class="nav-link active">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="bi bi-key-fill"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <h1>
            <i class="fas fa-money-bill-alt"></i> Salary Overview
        </h1>

        <div class="month-navigation">
            <input type="month" class="date-picker" value="<?php echo $selected_month; ?>">
            <div class="button-group">
                <a href="edit_attendance.php?month=<?php echo $selected_month; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Attendance
                </a>
                <a href="export_attendance.php?month=<?php echo $selected_month; ?>" class="btn btn-primary">
                    <i class="fas fa-file-export"></i>
                    Export Attendance
                </a>
                <a href="edit_leave.php?month=<?php echo $selected_month; ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-alt"></i>
                    Edit Leave
                </a>
                <a href="salary.php" class="btn btn-primary">
                    <i class="fas fa-info-circle"></i>
                    Salary Info
                </a>
                <button class="export-btn">
                    <i class="fas fa-download"></i>
                    Export
                </button>
            </div>
        </div>

        <div id="loading-overlay" style="display: none;">
            <div class="spinner"></div>
            <p>Loading salary data...</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="text-right">Monthly Salary</th>
                    <th class="text-right">Total Working Days</th>
                    <th class="text-right">Present Days</th>
                    <th class="text-right">Leave Taken</th>
                    <th class="text-right">Short Leave</th>
                    <th class="text-right">Late</th>
                    <th class="text-right">Travelling Expenses</th>
                    <th class="text-right">Salary Amount</th>
                    <th class="text-right">Overtime Amount</th>
                    <th class="text-right">Travel Amount</th>
                    <th class="text-right">Misc. Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $employee): ?>
                        <tr data-user-id="<?php echo $employee['id']; ?>">
                            <td><?php echo htmlspecialchars($employee['username']); ?></td>
                            <td class="text-right">₹<?php echo number_format($employee['monthly_salary'] ?? 0, 2); ?></td>
                            <td class="text-right working-days"><?php echo $employee['total_working_days'] ?? 0; ?></td>
                            <td class="text-right present-days"><?php echo $employee['present_days'] ?? 0; ?></td>
                            <td class="text-right"><?php echo $employee['leave_taken'] ?? 0; ?></td>
                            <td class="text-right"><?php echo $employee['short_leave'] ?? 0; ?></td>
                            <td class="text-right"><?php echo $employee['late'] ?? 0; ?></td>
                            <td class="text-right">₹<?php echo number_format($employee['travel_amount'] ?? 0, 2); ?></td>
                            <td class="text-right">₹<?php echo number_format($employee['salary_amount'] ?? 0, 2); ?></td>
                            <td class="text-right">₹<?php echo number_format($employee['overtime_amount'] ?? 0, 2); ?></td>
                            <td class="text-right">₹<?php echo number_format($employee['travel_amount'] ?? 0, 2); ?></td>
                            <td class="text-right">₹<?php echo number_format($employee['misc_amount'] ?? 0, 2); ?></td>
                            <td class="action-buttons">
                                <button class="action-btn btn-edit" onclick="editSalary(<?php echo $employee['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                    Edit
                                </button>
                                <button class="action-btn btn-view" onclick="viewSalary(<?php echo $employee['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                    View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13" style="text-align: center;">No salary data available for the selected month</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Sidebar Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar functionality
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Change icon direction
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('bi-chevron-left');
                    icon.classList.add('bi-chevron-right');
                } else {
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-left');
                }
            });
            
            // Handle responsive behavior
            function checkWidth() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                }
            }
            
            // Check on load
            checkWidth();
            
            // Check on resize
            window.addEventListener('resize', checkWidth);
            
            // Handle click outside on mobile
            document.addEventListener('click', function(e) {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile && !sidebar.contains(e.target) && !sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                }
            });

            // Existing salary overview functionality
            const datePicker = document.querySelector('.date-picker');
            const loadingOverlay = document.getElementById('loading-overlay');
            
            // Month picker change
            datePicker.addEventListener('change', function() {
                loadingOverlay.style.display = 'flex';
                window.location.href = 'salary_overview.php?month=' + this.value;
            });

            // Calculate working days considering user's weekly offs
            async function calculateWorkingDays(userId, year, month) {
                // Fetch weekly offs and holidays for the user
                const response = await fetch(`get_weekly_offs.php?user_id=${userId}&date=${year}-${month}-01`);
                const weeklyOffs = await response.json();
                
                // Fetch holidays for the month
                const holidaysResponse = await fetch(`get_holidays.php?year=${year}&month=${month}`);
                const holidays = await holidaysResponse.json();
                
                const startDate = new Date(year, month - 1, 1);
                const endDate = new Date(year, month, 0);
                let workingDays = 0;
                
                for (let date = new Date(startDate); date <= endDate; date.setDate(date.getDate() + 1)) {
                    const dateString = date.toISOString().split('T')[0];
                    // Check if current day is not in weekly offs and not a holiday
                    if (!weeklyOffs.includes(date.getDay().toString()) && !holidays.includes(dateString)) {
                        workingDays++;
                    }
                }
                return workingDays;
            }

            // Update working days for each employee
            async function updateWorkingDays() {
                const [year, month] = datePicker.value.split('-');
                const rows = document.querySelectorAll('tbody tr');
                
                for (const row of rows) {
                    const userId = row.dataset.userId;
                    if (userId) {
                        const workingDays = await calculateWorkingDays(userId, parseInt(year), parseInt(month));
                        const workingDaysCell = row.querySelector('.working-days');
                        if (workingDaysCell) {
                            workingDaysCell.textContent = workingDays;
                        }
                    }
                }
            }

            // Initialize working days on page load
            updateWorkingDays();
        });

        // Salary action functions
        function editSalary(employeeId) {
            window.location.href = `edit_salary.php?id=${employeeId}&month=${document.querySelector('.date-picker').value}`;
        }

        function viewSalary(employeeId) {
            window.location.href = `view_salary.php?id=${employeeId}&month=${document.querySelector('.date-picker').value}`;
        }
    </script>
</body>
</html>
