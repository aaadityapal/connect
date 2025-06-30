<?php
// Database connection
require_once 'config/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get filter values from GET parameters or use current month/year
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$current_month = date('m');
$current_year = date('Y');

// Format month name for display
$month_name = date('F', mktime(0, 0, 0, $filter_month, 1, $filter_year));

// Function to calculate total overtime hours
function calculateTotalHours($result, $column) {
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $total += floatval($row[$column]);
    }
    return $total;
}

// Get user's shift information
$shift_query = "SELECT s.start_time, s.end_time, us.weekly_offs 
                FROM shifts s 
                JOIN user_shifts us ON s.id = us.shift_id 
                WHERE us.user_id = ? 
                AND (us.effective_to IS NULL OR us.effective_to >= CURDATE()) 
                AND us.effective_from <= CURDATE()
                ORDER BY us.effective_from DESC 
                LIMIT 1";

$shift_stmt = $conn->prepare($shift_query);
$shift_stmt->bind_param("i", $user_id);
$shift_stmt->execute();
$shift_result = $shift_stmt->get_result();
$shift_data = $shift_result->fetch_assoc();

// Default shift end time if no shift data found
$shift_end_time = $shift_data ? $shift_data['end_time'] : '18:00:00'; // 6:00 PM default

// Query for filtered month attendance with calculated overtime
$query = "SELECT a.*, 
          CASE 
              WHEN TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) >= 5400 THEN 
                  FLOOR(TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) / 1800) * 0.5
              ELSE 0 
          END as calculated_overtime
          FROM attendance a
          WHERE a.user_id = ? 
          AND MONTH(a.date) = ? 
          AND YEAR(a.date) = ? 
          AND a.punch_out IS NOT NULL
          ORDER BY a.date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssiis", $shift_end_time, $shift_end_time, $user_id, $filter_month, $filter_year);
$stmt->execute();
$result = $stmt->get_result();

// Count rows
$total_records = $result->num_rows;

// Calculate statistics
$total_overtime = 0;
$pending_overtime = 0;
$approved_overtime = 0;

// Clone the result for calculating totals
$result_clone = $result->fetch_all(MYSQLI_ASSOC);
$result->data_seek(0); // Reset pointer

foreach ($result_clone as $row) {
    // Use calculated_overtime instead of overtime_hours
    $overtime = floatval($row['calculated_overtime']);
    $total_overtime += $overtime;
    
    if (isset($row['overtime_status']) && $row['overtime_status'] == 'approved') {
        $approved_overtime += $overtime;
    } elseif (!isset($row['overtime_status']) || $row['overtime_status'] == 'pending' || $row['overtime_status'] == 'submitted') {
        $pending_overtime += $overtime;
    }
}

// Calculate estimated payout (example rate: $15/hour)
$hourly_rate = 15;
$estimated_payout = $approved_overtime * $hourly_rate;

// Get recent overtime entries (limit to 10)
$recent_query = "SELECT a.*, 
                CASE 
                    WHEN TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) >= 5400 THEN 
                        FLOOR(TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) / 1800) * 0.5
                    ELSE 0 
                END as calculated_overtime,
                (SELECT s.end_time FROM shifts s 
                    JOIN user_shifts us ON s.id = us.shift_id 
                    WHERE us.user_id = a.user_id 
                    AND (us.effective_to IS NULL OR us.effective_to >= a.date) 
                    AND us.effective_from <= a.date
                    ORDER BY us.effective_from DESC 
                    LIMIT 1) as shift_end_time,
                IFNULL(a.overtime_status, 'pending') as overtime_status
                FROM attendance a 
                WHERE a.user_id = ? 
                AND MONTH(a.date) = ? 
                AND YEAR(a.date) = ?
                AND a.punch_out IS NOT NULL
                AND TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) >= 5400
                ORDER BY a.date DESC
                LIMIT 10";

$stmt_recent = $conn->prepare($recent_query);
$stmt_recent->bind_param("ssiiss", $shift_end_time, $shift_end_time, $user_id, $filter_month, $filter_year, $shift_end_time);
$stmt_recent->execute();
$recent_result = $stmt_recent->get_result();

// Get weekday and weekend overtime breakdown
$weekday_query = "SELECT SUM(
                  CASE 
                      WHEN TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) >= 5400 THEN 
                          FLOOR(TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) / 1800) * 0.5
                      ELSE 0 
                  END) as weekday_hours
                  FROM attendance a 
                  WHERE a.user_id = ? 
                  AND MONTH(a.date) = ? 
                  AND YEAR(a.date) = ?
                  AND a.punch_out IS NOT NULL
                  AND DAYOFWEEK(a.date) BETWEEN 2 AND 6";

$stmt_weekday = $conn->prepare($weekday_query);
$stmt_weekday->bind_param("ssiis", $shift_end_time, $shift_end_time, $user_id, $filter_month, $filter_year);
$stmt_weekday->execute();
$weekday_result = $stmt_weekday->get_result();
$weekday_row = $weekday_result->fetch_assoc();
$weekday_hours = $weekday_row['weekday_hours'] ?: 0;

$weekend_query = "SELECT SUM(
                 CASE 
                     WHEN TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) >= 5400 THEN 
                         FLOOR(TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) / 1800) * 0.5
                     ELSE 0 
                 END) as weekend_hours
                 FROM attendance a
                 WHERE a.user_id = ? 
                 AND MONTH(a.date) = ? 
                 AND YEAR(a.date) = ?
                 AND a.punch_out IS NOT NULL
                 AND DAYOFWEEK(a.date) IN (1, 7)";

$stmt_weekend = $conn->prepare($weekend_query);
$stmt_weekend->bind_param("ssiis", $shift_end_time, $shift_end_time, $user_id, $filter_month, $filter_year);
$stmt_weekend->execute();
$weekend_result = $stmt_weekend->get_result();
$weekend_row = $weekend_result->fetch_assoc();
$weekend_hours = $weekend_row['weekend_hours'] ?: 0;

// Calculate percentages for the chart
$total_hours = $weekday_hours + $weekend_hours;
$weekday_percent = ($total_hours > 0) ? round(($weekday_hours / $total_hours) * 100) : 0;
$weekend_percent = ($total_hours > 0) ? round(($weekend_hours / $total_hours) * 100) : 0;

// Special rates overtime (only on weekly offs from user_shifts)
$special_query = "SELECT SUM(
                 CASE 
                     WHEN TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) >= 5400 THEN 
                         FLOOR(TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) / 1800) * 0.5
                     ELSE 0 
                 END) as special_hours
                 FROM attendance a
                 JOIN user_shifts us ON a.user_id = us.user_id
                 WHERE a.user_id = ? 
                 AND MONTH(a.date) = ? 
                 AND YEAR(a.date) = ?
                 AND a.punch_out IS NOT NULL
                 AND FIND_IN_SET(DAYOFWEEK(a.date)-1, us.weekly_offs) > 0
                 AND (us.effective_to IS NULL OR us.effective_to >= a.date)
                 AND us.effective_from <= a.date";

$stmt_special = $conn->prepare($special_query);
$stmt_special->bind_param("ssiis", $shift_end_time, $shift_end_time, $user_id, $filter_month, $filter_year);
$stmt_special->execute();
$special_result = $stmt_special->get_result();
$special_row = $special_result->fetch_assoc();
$special_hours = $special_row['special_hours'] ?: 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --sidebar-width: 250px;
            --light-bg: #f5f7fa;
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            height: 100vh;
            position: relative;
        }
        
        /* Side Panel Styles */
        .left-panel {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50, #34495e);
            color: #fff;
            height: 100vh;
            transition: all 0.3s ease;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        
        .left-panel.collapsed {
            width: 70px;
        }
        
        .brand-logo {
            padding: 20px 25px;
            margin-bottom: 20px;
        }
        
        .brand-logo img {
            max-width: 150px;
            height: auto;
        }
        
        .toggle-btn {
            position: absolute;
            right: -18px;
            top: 25px;
            background: #fff;
            border: none;
            color: #2c3e50;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }
        
        .toggle-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            background: #f8f9fa;
        }
        
        .toggle-btn i {
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .toggle-btn:hover i {
            color: #1a237e;
            transform: scale(1.2);
        }
        
        .menu-item {
            padding: 16px 25px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin: 5px 0;
            position: relative;
            overflow: hidden;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #3498db;
            padding-left: 30px;
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #3498db;
        }
        
        .menu-item::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.1);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
            z-index: 0;
        }
        
        .menu-item:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        .menu-item i {
            margin-right: 15px;
            width: 20px;
            font-size: 1.2em;
            text-align: center;
            position: relative;
            z-index: 1;
            color: #3498db;
            transition: transform 0.3s ease;
        }
        
        .menu-item:hover i {
            transform: scale(1.2);
            color: #3498db;
        }
        
        .menu-item::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.1);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
            z-index: 0;
        }
        
        .menu-item:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        .menu-item::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.1);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
            z-index: 0;
        }
        
        .menu-item:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        .menu-text {
            transition: all 0.3s ease;
            font-size: 0.95em;
            letter-spacing: 0.3px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .left-panel.collapsed .menu-text {
            display: none;
        }
        
        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 0, 0, 0.1);
        }
        
        .logout-item:hover {
            background: rgba(255, 0, 0, 0.2);
            border-left: 4px solid #ff4444 !important;
        }
        
        .logout-item i {
            color: #ff4444 !important;
        }
        
        /* Section headers in sidebar */
        .section-header {
            padding: 10px 25px;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            margin-top: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .left-panel.collapsed .section-header {
            display: none;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            height: 100vh;
            overflow-y: auto;
            background: #f8f9fa;
            padding-bottom: 30px;
            transition: margin-left 0.3s ease;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;     /* Firefox */
            width: calc(100% - var(--sidebar-width));
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .main-content::-webkit-scrollbar {
            display: none;
            width: 0;
        }

        /* Hide scrollbar for the entire body */
        body {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;     /* Firefox */
        }

        body::-webkit-scrollbar {
            display: none;
            width: 0;
        }
        
        .main-content.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
        
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        header h1 {
            color: var(--dark-color);
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        header p {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            font-weight: 500;
        }
        
        .btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .required-field::after {
            content: " *";
            color: var(--accent-color);
        }
        
        .notification {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: none;
        }
        
        .success {
            background-color: rgba(46, 204, 113, 0.2);
            border-left: 4px solid var(--success-color);
            color: var(--dark-color);
        }
        
        .error {
            background-color: rgba(231, 76, 60, 0.2);
            border-left: 4px solid var(--accent-color);
            color: var(--dark-color);
        }
        
        .info-icon {
            color: var(--primary-color);
            margin-right: 10px;
        }
        
        /* Content container styles */
        .content-container {
            padding: 20px;
            width: 100%;
            margin: 0;
        }
        
        .page-header {
            margin-bottom: 30px;
            padding: 0 20px;
        }
        
        .page-header h1 {
            font-size: 28px;
            color: var(--dark-color);
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
        }
        
        /* Filters section */
        .filters-section {
            display: flex;
            gap: 20px;
            align-items: center;
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin: 0 20px 25px 20px;
            flex-wrap: wrap;
            width: calc(100% - 40px);
        }
        
        .filter-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-container label {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .filter-select {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ddd;
            background-color: white;
            font-size: 14px;
            min-width: 150px;
        }
        
        .filter-btn {
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            transition: background-color 0.2s;
        }
        
        .filter-btn:hover {
            background-color: var(--secondary-color);
        }
        
        /* Section containers */
        .overview-section,
        .overtime-records-section {
            margin: 30px 20px;
        }
        
        .section-title {
            padding: 15px 20px;
            background-color: #f1f5f9;
            border-radius: 8px 8px 0 0;
            border: 1px solid #e0e4e8;
            border-bottom: none;
            margin: 0;
        }
        
        .section-title h2 {
            font-size: 20px;
            color: var(--dark-color);
            font-weight: 600;
            margin: 0;
        }
        
        /* Cards container and cards */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            grid-gap: 20px;
        }
        
        .overview-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .overview-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .overview-card::after {
            content: '';
            position: absolute;
            height: 100%;
            width: 5px;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            left: 0;
            top: 0;
            border-radius: 10px 0 0 10px;
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(52, 152, 219, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .card-icon i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .card-content {
            flex: 1;
        }
        
        .card-content h3 {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 5px;
            font-weight: normal;
        }
        
        .card-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            color: var(--secondary-color);
            line-height: 1.2;
        }
        
        .card-subtitle {
            font-size: 0.8rem;
            color: #95a5a6;
            margin-top: 5px;
        }
        
        /* Table styles */
        .table-container {
            background-color: white;
            border: 1px solid #e0e4e8;
            border-radius: 0 0 8px 8px;
            width: 100%;
            overflow-x: auto;
        }
        
        .overtime-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .overtime-table th {
            background-color: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--dark-color);
            border-bottom: 1px solid #e0e4e8;
            position: sticky;
            top: 0;
        }
        
        .overtime-table td {
            padding: 12px 15px;
            font-size: 14px;
            border-bottom: 1px solid #e0e4e8;
            color: #495057;
        }
        
        .overtime-table tr:last-child td {
            border-bottom: none;
        }
        
        .overtime-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Action buttons */
        .action-btn {
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            margin-right: 5px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-btn i {
            font-size: 12px;
        }
        
        .view-btn {
            background-color: #e3f2fd;
            color: #0277bd;
        }
        
        .view-btn:hover {
            background-color: #bbdefb;
        }
        
        .send-btn {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .send-btn:hover {
            background-color: #c8e6c9;
        }
        
        .send-btn.sent {
            background-color: #dff6df;
            color: #1b5e20;
            box-shadow: 0 2px 10px rgba(46, 204, 113, 0.2);
            position: relative;
            overflow: hidden;
            animation: sent-pulse 2s infinite;
        }
        
        @keyframes sent-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(46, 204, 113, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0);
            }
        }

        @media (max-width: 1200px) {
            .cards-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .left-panel {
                width: 70px;
            }
            
            .menu-text {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .content-container {
                padding: 10px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
                width: 100%;
            }
            
            .left-panel {
                transform: translateX(-100%);
                z-index: 1050;
            }
            
            .left-panel.show,
            .left-panel.mobile-show {
                transform: translateX(0);
            }
            
            .cards-container {
                grid-template-columns: 1fr;
                width: 100%;
                margin: 0;
                padding: 15px;
            }
            
            .overview-section,
            .overtime-records-section {
                margin: 20px 10px;
            }
            
            .section-title {
                margin: 0;
                padding: 10px 15px;
            }
            
            .table-container {
                border-radius: 0 0 5px 5px;
            }
            
            .overtime-table th,
            .overtime-table td {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .action-btn {
                padding: 5px 10px;
                font-size: 11px;
            }
            
            .filter-btn {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                justify-content: center;
            }
            
            .mobile-toggle {
                display: block !important;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1060;
                background: var(--primary-color);
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 5px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Side Panel -->
        <div class="left-panel" id="leftPanel">
            <div class="brand-logo">
                <img src="" alt="Logo">
            </div>
            <button class="toggle-btn" onclick="togglePanel()">
                <i class="fas fa-chevron-left" id="toggleIcon"></i>
            </button>
            
            <!-- Main Navigation -->
            <div class="menu-item" onclick="window.location.href='similar_dashboard.php'">
                <i class="fas fa-home"></i>
                <span class="menu-text">Dashboard</span>
            </div>
            
            <!-- Personal Section -->
            <div class="menu-item" onclick="window.location.href='profile.php'">
                <i class="fas fa-user-circle"></i>
                <span class="menu-text">My Profile</span>
            </div>
            <div class="menu-item" onclick="window.location.href='leave.php'">
                <i class="fas fa-calendar-alt"></i>
                <span class="menu-text">Apply Leave</span>
            </div>
            <div class="menu-item" onclick="window.location.href='std_travel_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Travel Expenses</span>
            </div>
            <div class="menu-item active" onclick="window.location.href='employee_overtime.php'">
                <i class="fas fa-clock"></i>
                <span class="menu-text">Overtime</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_expenses.php'">
                <i class="fas fa-receipt"></i>
                <span class="menu-text">Site Excel</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_updates.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Site Updates</span>
            </div>
            
            <!-- Work Section -->
            <div class="menu-item">
                <i class="fas fa-tasks"></i>
                <span class="menu-text">My Tasks</span>
            </div>
            <div class="menu-item" onclick="window.location.href='work_sheet.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Work Sheet & Attendance</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span class="menu-text">Performance</span>
            </div>
            
            <!-- Settings & Support -->
            <div class="menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-question-circle"></i>
                <span class="menu-text">Help & Support</span>
            </div>
            
            <!-- Logout at the bottom -->
            <div class="menu-item logout-item" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </div>
        </div>

        <!-- Mobile toggle button -->
        <div class="mobile-toggle" id="mobileToggle" style="display: none;">
            <i class="fas fa-bars"></i>
        </div>
        
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="content-container">
                <div class="page-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h1>Overtime Dashboard - <?php echo $month_name . ' ' . $filter_year; ?></h1>
                        <button onclick="showOvertimeRequestForm()" class="btn" style="display: flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #3498db, #2980b9); box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3); border-radius: 8px; transition: all 0.3s ease;">
                            <i class="fas fa-plus-circle"></i>
                            Request Overtime
                        </button>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <?php if ($_GET['success'] == 1): ?>
                        <div class="notification success" style="display: block; margin: 0 20px 20px 20px;">
                            <i class="fas fa-check-circle info-icon"></i>
                            <?php echo htmlspecialchars($_GET['message']); ?>
                        </div>
                    <?php else: ?>
                        <div class="notification error" style="display: block; margin: 0 20px 20px 20px;">
                            <i class="fas fa-exclamation-circle info-icon"></i>
                            <?php echo htmlspecialchars($_GET['message']); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <div style="text-align: right; margin: 0 20px 15px 20px;">
                    <a href="db/setup_overtime_tables.php" style="font-size: 14px; color: #7f8c8d; text-decoration: none;">
                        <i class="fas fa-database"></i> Setup Overtime Tables
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Month/Year Filter Section -->
                <div class="filters-section">
                    <form method="GET" action="employee_overtime.php" style="display: flex; width: 100%; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div class="filter-container">
                            <label for="month" style="margin-bottom: 0;">Month:</label>
                            <select id="month" name="month" class="filter-select">
                                <?php
                                for ($i = 1; $i <= 12; $i++) {
                                    $month_num = sprintf('%02d', $i);
                                    $month_name = date('F', mktime(0, 0, 0, $i, 1));
                                    $selected = ($filter_month == $month_num) ? 'selected' : '';
                                    echo "<option value=\"$month_num\" $selected>$month_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="filter-container">
                            <label for="year" style="margin-bottom: 0;">Year:</label>
                            <select id="year" name="year" class="filter-select">
                                <?php
                                $current_year = (int)date('Y');
                                for ($i = $current_year - 2; $i <= $current_year; $i++) {
                                    $selected = ($filter_year == $i) ? 'selected' : '';
                                    echo "<option value=\"$i\" $selected>$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter"></i>
                            Apply Filter
                        </button>
                        
                        <?php if($filter_month != date('m') || $filter_year != date('Y')): ?>
                        <a href="employee_overtime.php" class="filter-btn" style="background: #95a5a6; text-decoration: none;">
                            <i class="fas fa-sync-alt"></i>
                            Reset to Current Month
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Request Form Modal -->
                <div id="overtimeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; overflow-y: auto;">
                    <div style="position: relative; width: 90%; max-width: 600px; margin: 50px auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div style="position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 22px; color: #95a5a6;" onclick="closeOvertimeModal()">
                            <i class="fas fa-times"></i>
                        </div>
                        
                        <h2 style="color: #2c3e50; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 24px;">Request Overtime</h2>
                        
                        <form id="overtimeForm" action="submit_overtime.php" method="post">
                            <div class="form-group">
                                <label for="overtimeDate" class="required-field">Date</label>
                                <input type="date" id="overtimeDate" name="date" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="endTime" class="required-field">Punch Out Time</label>
                                <input type="time" id="endTime" name="end_time" required class="form-control">
                                <small style="color: #7f8c8d; display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> 
                                    Overtime eligibility requires working at least 1.5 hours after your shift end time.
                                    Overtime is rounded down to nearest 30-minute intervals (e.g., 1:46 → 1:30, 2:15 → 2:00).
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="workReport" class="required-field">Work Description</label>
                                <textarea id="workReport" name="work_report" placeholder="Describe the work done during overtime" required class="form-control"></textarea>
                            </div>
                            
                            <div style="margin-top: 30px; text-align: right;">
                                <button type="button" onclick="closeOvertimeModal()" style="background: #ecf0f1; border: none; color: #7f8c8d; padding: 12px 20px; margin-right: 10px; border-radius: 5px; cursor: pointer; font-weight: 500;">Cancel</button>
                                <button type="submit" class="btn" style="background: #3498db; padding: 12px 25px;">Submit Request</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Overview Section -->
                <div class="overview-section">
                    <div class="section-title" style="background: linear-gradient(135deg, #3498db, #2c3e50); color: white; border: none; display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; border-radius: 10px 10px 0 0;">
                        <h2 style="margin: 0; font-size: 22px; font-weight: 600; color: white; display: flex; align-items: center;">
                            <i class="fas fa-chart-line" style="margin-right: 15px; font-size: 24px;"></i>
                            Monthly Overview
                        </h2>
                        <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 14px;">
                            <i class="fas fa-sync-alt" style="margin-right: 5px;"></i>
                            <?php echo ($filter_month == date('m') && $filter_year == date('Y')) ? 'Current Month' : 'Historical Data'; ?>
                        </span>
                    </div>
                    
                    <div class="cards-container" style="background: white; padding: 25px; border-radius: 0 0 10px 10px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); gap: 25px;">
                        <!-- Card 1: Month Summary -->
                        <div class="overview-card" style="border-radius: 15px; position: relative; overflow: hidden; padding: 25px 20px; border: none; background: linear-gradient(145deg, #ffffff, #f5f7fa); box-shadow: 5px 5px 15px rgba(0,0,0,0.05), -5px -5px 15px rgba(255,255,255,0.6);">
                            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(52, 152, 219, 0.05); border-radius: 50%;"></div>
                            <div style="position: absolute; bottom: -30px; left: -10px; width: 80px; height: 80px; background: rgba(52, 152, 219, 0.05); border-radius: 50%;"></div>
                            
                            <div class="card-icon" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; width: 70px; height: 70px; margin-right: 20px; box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);">
                                <i class="fas fa-calendar-alt" style="font-size: 28px; color: white;"></i>
                            </div>
                            <div class="card-content">
                                <h3 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px; font-weight: 500;">Total Hours</h3>
                                <p class="card-value" style="font-size: 32px; font-weight: 700; margin: 0; background: linear-gradient(to right, #3498db, #2980b9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1;"><?php echo number_format($total_overtime, 1); ?></p>
                                <p class="card-subtitle" style="font-size: 14px; color: #95a5a6; margin-top: 8px; display: flex; align-items: center;">
                                    <i class="fas fa-arrow-up" style="color: #2ecc71; margin-right: 5px;"></i> 
                                    <span>Hours Worked</span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Card 2: Pending Approval -->
                        <div class="overview-card" style="border-radius: 15px; position: relative; overflow: hidden; padding: 25px 20px; border: none; background: linear-gradient(145deg, #ffffff, #f5f7fa); box-shadow: 5px 5px 15px rgba(0,0,0,0.05), -5px -5px 15px rgba(255,255,255,0.6);">
                            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(230, 126, 34, 0.05); border-radius: 50%;"></div>
                            <div style="position: absolute; bottom: -30px; left: -10px; width: 80px; height: 80px; background: rgba(230, 126, 34, 0.05); border-radius: 50%;"></div>
                            
                            <div class="card-icon" style="background: linear-gradient(135deg, #e67e22, #d35400); color: white; width: 70px; height: 70px; margin-right: 20px; box-shadow: 0 10px 20px rgba(230, 126, 34, 0.3);">
                                <i class="fas fa-hourglass-half" style="font-size: 28px; color: white;"></i>
                            </div>
                            <div class="card-content">
                                <h3 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px; font-weight: 500;">Pending Approval</h3>
                                <p class="card-value" style="font-size: 32px; font-weight: 700; margin: 0; background: linear-gradient(to right, #e67e22, #d35400); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1;"><?php echo number_format($pending_overtime, 1); ?></p>
                                <p class="card-subtitle" style="font-size: 14px; color: #95a5a6; margin-top: 8px; display: flex; align-items: center;">
                                    <i class="fas fa-clock" style="color: #e67e22; margin-right: 5px;"></i> 
                                    <span>Hours Awaiting</span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Card 3: Approved Hours -->
                        <div class="overview-card" style="border-radius: 15px; position: relative; overflow: hidden; padding: 25px 20px; border: none; background: linear-gradient(145deg, #ffffff, #f5f7fa); box-shadow: 5px 5px 15px rgba(0,0,0,0.05), -5px -5px 15px rgba(255,255,255,0.6);">
                            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(46, 204, 113, 0.05); border-radius: 50%;"></div>
                            <div style="position: absolute; bottom: -30px; left: -10px; width: 80px; height: 80px; background: rgba(46, 204, 113, 0.05); border-radius: 50%;"></div>
                            
                            <div class="card-icon" style="background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; width: 70px; height: 70px; margin-right: 20px; box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);">
                                <i class="fas fa-check-circle" style="font-size: 28px; color: white;"></i>
                            </div>
                            <div class="card-content">
                                <h3 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px; font-weight: 500;">Approved Hours</h3>
                                <p class="card-value" style="font-size: 32px; font-weight: 700; margin: 0; background: linear-gradient(to right, #2ecc71, #27ae60); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1;"><?php echo number_format($approved_overtime, 1); ?></p>
                                <p class="card-subtitle" style="font-size: 14px; color: #95a5a6; margin-top: 8px; display: flex; align-items: center;">
                                    <i class="fas fa-check" style="color: #2ecc71; margin-right: 5px;"></i> 
                                    <span>This Month</span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Card 4: Overtime Left For Sending Approval -->
                        <div class="overview-card" style="border-radius: 15px; position: relative; overflow: hidden; padding: 25px 20px; border: none; background: linear-gradient(145deg, #ffffff, #f5f7fa); box-shadow: 5px 5px 15px rgba(0,0,0,0.05), -5px -5px 15px rgba(255,255,255,0.6);">
                            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(155, 89, 182, 0.05); border-radius: 50%;"></div>
                            <div style="position: absolute; bottom: -30px; left: -10px; width: 80px; height: 80px; background: rgba(155, 89, 182, 0.05); border-radius: 50%;"></div>
                            
                            <div class="card-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; width: 70px; height: 70px; margin-right: 20px; box-shadow: 0 10px 20px rgba(155, 89, 182, 0.3);">
                                <i class="fas fa-paper-plane" style="font-size: 28px; color: white;"></i>
                            </div>
                            <div class="card-content">
                                <h3 style="font-size: 16px; color: #7f8c8d; margin-bottom: 8px; font-weight: 500;">Overtime Left For Approval</h3>
                                <?php
                                // Calculate overtime left for sending
                                $overtime_left_query = "SELECT SUM(
                                    CASE 
                                        WHEN TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) >= 5400 THEN 
                                            FLOOR(TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) / 1800) * 0.5
                                        ELSE 0 
                                    END) as unsent_hours
                                    FROM attendance a 
                                    LEFT JOIN overtime_notifications n ON a.id = n.overtime_id
                                    WHERE a.user_id = ? 
                                    AND MONTH(a.date) = ? 
                                    AND YEAR(a.date) = ?
                                    AND a.punch_out IS NOT NULL
                                    AND n.id IS NULL";
                                
                                try {
                                    $stmt_left = $conn->prepare($overtime_left_query);
                                    $stmt_left->bind_param("ssiis", $shift_end_time, $shift_end_time, $user_id, $filter_month, $filter_year);
                                    $stmt_left->execute();
                                    $left_result = $stmt_left->get_result();
                                    $left_row = $left_result->fetch_assoc();
                                    $overtime_left = $left_row['unsent_hours'] ?: 0;
                                } catch (Exception $e) {
                                    // If table doesn't exist yet
                                    $overtime_left = $total_overtime;
                                }
                                ?>
                                <p class="card-value" style="font-size: 32px; font-weight: 700; margin: 0; background: linear-gradient(to right, #9b59b6, #8e44ad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1;"><?php echo number_format($overtime_left, 1); ?></p>
                                <p class="card-subtitle" style="font-size: 14px; color: #95a5a6; margin-top: 8px; display: flex; align-items: center;">
                                    <i class="fas fa-clock" style="color: #9b59b6; margin-right: 5px;"></i> 
                                    <span>Hours Needing Submission</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Section -->
                <div class="overview-section">
                    <div class="section-title">
                        <h2><?php echo $month_name . ' ' . $filter_year; ?> Overtime Records</h2>
                    </div>
                    
                    <div style="background-color: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background-color: #f5f7fa; border-bottom: 1px solid #eee;">
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #2c3e50;">Date</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #2c3e50;">Hours</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #2c3e50;">Shift End Time</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #2c3e50;">Punch Out Time</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #2c3e50;">Work Report</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #2c3e50;">Status</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #2c3e50;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $status_colors = [
                                    'approved' => ['bg' => '#e8f5e9', 'text' => '#2e7d32'],
                                    'pending' => ['bg' => '#e1f5fe', 'text' => '#0288d1'],
                                    'rejected' => ['bg' => '#ffebee', 'text' => '#c62828']
                                ];
                                
                                while ($row = $recent_result->fetch_assoc()) {
                                    $date = date('M d, Y', strtotime($row['date']));
                                    $hours = number_format($row['calculated_overtime'], 1);
                                    $shift_end_time = date('h:i A', strtotime($row['shift_end_time'] ?? $shift_end_time));
                                    $punch_out_time = date('h:i A', strtotime($row['punch_out']));
                                    $work_report = htmlspecialchars(substr($row['work_report'], 0, 100)) . (strlen($row['work_report']) > 100 ? '...' : '');
                                    $status = strtolower($row['overtime_status']) ?: 'pending';
                                    $status_display = ucfirst($status);
                                    
                                    $bg_color = isset($status_colors[$status]) ? $status_colors[$status]['bg'] : '#e1f5fe';
                                    $text_color = isset($status_colors[$status]) ? $status_colors[$status]['text'] : '#0288d1';
                                    
                                    // Check if this overtime has been sent to a manager
                                    $notification_check = false;
                                    try {
                                        $check_query = "SELECT id FROM overtime_notifications WHERE overtime_id = ? LIMIT 1";
                                        $check_stmt = $conn->prepare($check_query);
                                        $check_stmt->bind_param("i", $row['id']);
                                        $check_stmt->execute();
                                        $check_result = $check_stmt->get_result();
                                        $notification_check = $check_result->num_rows > 0;
                                    } catch (Exception $e) {
                                        // Silent catch - table may not exist yet
                                    }
                                    
                                    // Set send button class and icon based on whether it has been sent
                                    $send_btn_class = $notification_check ? "send-btn sent" : "send-btn";
                                    $send_icon = $notification_check ? "fa-check" : "fa-paper-plane";
                                    $send_title = $notification_check ? "Already Sent" : "Send Report";
                                    $onclick = $notification_check ? "" : "onclick=\"openSendModal('" . $row['id'] . "', '" . $date . "', '" . $hours . "')\"";
                                    
                                    echo "
                                    <tr style=\"border-bottom: 1px solid #eee;\">
                                        <td style=\"padding: 15px; color: #7f8c8d;\">$date</td>
                                        <td style=\"padding: 15px; color: #2c3e50; font-weight: 600;\">$hours</td>
                                        <td style=\"padding: 15px; color: #7f8c8d;\">$shift_end_time</td>
                                        <td style=\"padding: 15px; color: #7f8c8d;\">$punch_out_time</td>
                                        <td style=\"padding: 15px; color: #7f8c8d;\">$work_report</td>
                                        <td style=\"padding: 15px;\"><span style=\"background-color: $bg_color; color: $text_color; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem;\">$status_display</span></td>
                                        <td style=\"padding: 15px; text-align: center;\">
                                            <button class=\"action-btn view-btn\" style=\"width: 36px; height: 36px; border-radius: 50%; padding: 0; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px;\" title=\"View Details\" onclick=\"openViewModal('" . $row['id'] . "', '" . $date . "', '" . $hours . "', '" . $shift_end_time . "', '" . $punch_out_time . "', '" . addslashes($work_report) . "', '" . $status_display . "')\">
                                                <i class=\"fas fa-eye\"></i>
                                            </button>
                                            <button class=\"action-btn $send_btn_class\" style=\"width: 36px; height: 36px; border-radius: 50%; padding: 0; display: inline-flex; align-items: center; justify-content: center;\" title=\"$send_title\" $onclick>
                                                <i class=\"fas $send_icon\"></i>
                                            </button>
                                        </td>
                                    </tr>";
                                }
                                
                                if ($recent_result->num_rows == 0) {
                                    echo "
                                    <tr>
                                        <td colspan=\"7\" style=\"padding: 30px; text-align: center; color: #7f8c8d;\">
                                            <i class=\"fas fa-info-circle\" style=\"font-size: 24px; margin-bottom: 10px; color: #95a5a6;\"></i>
                                            <p>No overtime records found for this month.</p>
                                        </td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.getElementById('mobileToggle');
            const leftPanel = document.getElementById('leftPanel');
            
            // Create overlay for mobile
            const overlay = document.createElement('div');
            overlay.classList.add('panel-overlay');
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.right = '0';
            overlay.style.bottom = '0';
            overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
            overlay.style.zIndex = '998';
            overlay.style.display = 'none';
            document.body.appendChild(overlay);
            
            // Show mobile toggle button on small screens
            if (window.innerWidth <= 768) {
                mobileToggle.style.display = 'flex';
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    mobileToggle.style.display = 'flex';
                } else {
                    mobileToggle.style.display = 'none';
                    leftPanel.classList.remove('mobile-show');
                    leftPanel.classList.remove('show');
                    overlay.style.display = 'none';
                }
            });
            
            // Toggle menu function
            function toggleMenu() {
                leftPanel.classList.toggle('show');
                if (leftPanel.classList.contains('show')) {
                    overlay.style.display = 'block';
                } else {
                    overlay.style.display = 'none';
                }
            }
            
            // Event listeners
            mobileToggle.addEventListener('click', toggleMenu);
            overlay.addEventListener('click', toggleMenu);
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && 
                    !leftPanel.contains(event.target) && 
                    !mobileToggle.contains(event.target) &&
                    (leftPanel.classList.contains('mobile-show') || leftPanel.classList.contains('show'))) {
                    leftPanel.classList.remove('mobile-show');
                    leftPanel.classList.remove('show');
                    overlay.style.display = 'none';
                }
            });
            
            // Toggle sidebar
            window.togglePanel = function() {
                const leftPanel = document.getElementById('leftPanel');
                const mainContent = document.getElementById('mainContent');
                const toggleIcon = document.getElementById('toggleIcon');
                
                leftPanel.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                // Rotate icon
                if (leftPanel.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
                
                // Save the state to localStorage
                const isCollapsed = leftPanel.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            };
            
            // Check localStorage on page load for sidebar state
            document.addEventListener('DOMContentLoaded', function() {
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'true') {
                    document.getElementById('leftPanel').classList.add('collapsed');
                    document.getElementById('mainContent').classList.add('expanded');
                    document.getElementById('toggleIcon').classList.remove('fa-chevron-left');
                    document.getElementById('toggleIcon').classList.add('fa-chevron-right');
                }
            });
        });
        
        // Overtime form functions
        function showOvertimeRequestForm() {
            document.getElementById('overtimeModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeOvertimeModal() {
            document.getElementById('overtimeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Send overtime report modal functionality
        function openSendModal(overtimeId, date, hours) {
            document.getElementById('sendOvertimeId').value = overtimeId;
            document.getElementById('sendOvertimeDate').textContent = date;
            document.getElementById('sendOvertimeHours').textContent = hours;
            document.getElementById('sendOvertimeModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeSendModal() {
            document.getElementById('sendOvertimeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // View overtime details modal functionality
        function openViewModal(overtimeId, date, hours, shiftEndTime, punchOutTime, workReport, status) {
            document.getElementById('viewOvertimeId').value = overtimeId;
            document.getElementById('viewOvertimeDate').textContent = date;
            document.getElementById('viewOvertimeHours').textContent = hours;
            document.getElementById('viewShiftEndTime').textContent = shiftEndTime;
            document.getElementById('viewPunchOutTime').textContent = punchOutTime;
            document.getElementById('viewWorkReport').textContent = workReport;
            document.getElementById('viewStatus').textContent = status;
            
            // Set status color
            let statusElem = document.getElementById('viewStatus');
            statusElem.className = '';
            if (status.toLowerCase() === 'approved') {
                statusElem.className = 'status-approved';
            } else if (status.toLowerCase() === 'rejected') {
                statusElem.className = 'status-rejected';
            } else if (status.toLowerCase() === 'submitted') {
                statusElem.className = 'status-submitted';
            } else {
                statusElem.className = 'status-pending';
            }
            
            document.getElementById('viewOvertimeModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeViewModal() {
            document.getElementById('viewOvertimeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    </script>
    
    <!-- Common modal styles -->
    <style>
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1050;
            overflow-y: auto;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        .modal-container {
            position: relative;
            width: 90%;
            max-width: 550px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            transform: translateY(20px);
            opacity: 0;
            animation: modalSlideIn 0.3s forwards;
            overflow: hidden;
        }
        
        @keyframes modalSlideIn {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            position: relative;
            padding: 25px 30px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-title {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: white;
        }
        
        .modal-subtitle {
            margin: 5px 0 0;
            font-size: 15px;
            opacity: 0.8;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            color: white;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.4);
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .modal-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .modal-btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .modal-btn-cancel {
            background: #ecf0f1;
            color: #7f8c8d;
        }
        
        .modal-btn-cancel:hover {
            background: #dde4e6;
        }
        
        .modal-btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(52, 152, 219, 0.4);
        }
        
        .modal-section {
            margin-bottom: 25px;
        }
        
        .modal-section:last-child {
            margin-bottom: 0;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .detail-label {
            font-weight: 500;
            color: #7f8c8d;
            flex: 1;
        }
        
        .detail-value {
            font-weight: 600;
            color: #2c3e50;
            flex: 1;
            text-align: right;
        }
        
        .work-report-section {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        
        .work-report-label {
            font-weight: 500;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .work-report-content {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
            color: #2c3e50;
            line-height: 1.6;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .status-indicator {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            min-width: 100px;
        }
        
        .status-approved {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-rejected {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .status-pending {
            background-color: #e1f5fe;
            color: #0288d1;
        }
        
        .status-submitted {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        /* Form styling */
        .select-container {
            position: relative;
            margin-bottom: 25px;
        }
        
        .select-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .select-container select {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: white;
            font-size: 16px;
            color: #2c3e50;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232c3e50' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .select-container select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        .textarea-container {
            margin-bottom: 25px;
        }
        
        .textarea-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .textarea-container textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            min-height: 120px;
            resize: vertical;
            font-size: 15px;
            line-height: 1.5;
            color: #2c3e50;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .textarea-container textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
    </style>
    
    <!-- Send Overtime Modal -->
    <div id="sendOvertimeModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Send Overtime Report</h2>
                <p class="modal-subtitle">Select a manager to send this overtime record</p>
                <button class="modal-close" onclick="closeSendModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="modal-section">
                    <div class="detail-item">
                        <span class="detail-label">Date:</span>
                        <span id="sendOvertimeDate" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Overtime Hours:</span>
                        <span id="sendOvertimeHours" class="detail-value"></span>
                    </div>
                </div>
                
                <form id="sendOvertimeForm" action="send_overtime.php" method="post">
                    <input type="hidden" id="sendOvertimeId" name="overtime_id">
                    
                    <div class="select-container">
                        <label for="managerSelect">Select Manager:</label>
                        <?php
                        // Get current user's role to determine which manager group should be selected by default
                        $user_role_query = "SELECT role FROM users WHERE id = ?";
                        $role_stmt = $conn->prepare($user_role_query);
                        $role_stmt->bind_param("i", $user_id);
                        $role_stmt->execute();
                        $role_result = $role_stmt->get_result();
                        $user_role_data = $role_result->fetch_assoc();
                        $user_role = $user_role_data['role'] ?? '';
                        
                        // Determine if site or studio managers should be first based on user role
                        $is_site_staff = in_array($user_role, ['Site Supervisor', 'Site Coordinator']);
                        
                        // Fetch both manager types
                        $studio_query = "SELECT id, username FROM users WHERE role = 'Senior Manager (Studio)' ORDER BY username";
                        $studio_result = $conn->query($studio_query);
                        $studio_managers = $studio_result->fetch_all(MYSQLI_ASSOC);
                        
                        $site_query = "SELECT id, username FROM users WHERE role = 'Senior Manager (Site)' ORDER BY username";
                        $site_result = $conn->query($site_query);
                        $site_managers = $site_result->fetch_all(MYSQLI_ASSOC);
                        ?>
                        
                        <select id="managerSelect" name="manager_id" class="form-control">
                            <?php if ($is_site_staff): ?>
                                <!-- Show Site Managers first for site staff -->
                                <optgroup label="Site Managers">
                                    <?php
                                    if (count($site_managers) > 0) {
                                        foreach ($site_managers as $manager) {
                                            echo "<option value=\"{$manager['id']}\" selected>{$manager['username']} - Senior Manager (Site)</option>";
                                        }
                                    } else {
                                        echo "<option disabled>No site managers found</option>";
                                    }
                                    ?>
                                </optgroup>
                                <optgroup label="Studio Managers">
                                    <?php
                                    if (count($studio_managers) > 0) {
                                        foreach ($studio_managers as $manager) {
                                            echo "<option value=\"{$manager['id']}\">{$manager['username']} - Senior Manager (Studio)</option>";
                                        }
                                    } else {
                                        echo "<option disabled>No studio managers found</option>";
                                    }
                                    ?>
                                </optgroup>
                            <?php else: ?>
                                <!-- Show Studio Managers first for studio staff -->
                                <optgroup label="Studio Managers">
                                    <?php
                                    if (count($studio_managers) > 0) {
                                        foreach ($studio_managers as $manager) {
                                            echo "<option value=\"{$manager['id']}\" selected>{$manager['username']} - Senior Manager (Studio)</option>";
                                        }
                                    } else {
                                        echo "<option disabled>No studio managers found</option>";
                                    }
                                    ?>
                                </optgroup>
                                <optgroup label="Site Managers">
                                    <?php
                                    if (count($site_managers) > 0) {
                                        foreach ($site_managers as $manager) {
                                            echo "<option value=\"{$manager['id']}\">{$manager['username']} - Senior Manager (Site)</option>";
                                        }
                                    } else {
                                        echo "<option disabled>No site managers found</option>";
                                    }
                                    ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="textarea-container">
                        <label for="messageText">Message (Optional):</label>
                        <textarea id="messageText" name="message" placeholder="Add a message to the manager..."></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeSendModal()" class="modal-btn modal-btn-cancel">Cancel</button>
                <button type="submit" form="sendOvertimeForm" class="modal-btn modal-btn-primary">
                    <i class="fas fa-paper-plane" style="margin-right: 8px;"></i>
                    Send Report
                </button>
            </div>
        </div>
    </div>
    
    <!-- View Overtime Details Modal -->
    <div id="viewOvertimeModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Overtime Details</h2>
                <p class="modal-subtitle">Complete information about your overtime</p>
                <button class="modal-close" onclick="closeViewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <input type="hidden" id="viewOvertimeId">
                
                <div class="modal-section">
                    <div class="detail-item">
                        <span class="detail-label">Date:</span>
                        <span id="viewOvertimeDate" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Shift End Time:</span>
                        <span id="viewShiftEndTime" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Punch Out Time:</span>
                        <span id="viewPunchOutTime" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Overtime Hours:</span>
                        <span id="viewOvertimeHours" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span id="viewStatus" class="status-indicator"></span>
                    </div>
                </div>
                
                <div class="work-report-section">
                    <div class="work-report-label">Work Report:</div>
                    <div id="viewWorkReport" class="work-report-content"></div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeViewModal()" class="modal-btn modal-btn-cancel">Close</button>
            </div>
        </div>
    </div>
</body>
</html>