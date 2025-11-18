<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Always use the logged-in user's ID - no option to view others
$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'monthly';

// Get user info for display
$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch user's weekly offs from user_shifts table
$weekly_offs = [];
$stmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts WHERE user_id = ? AND CURRENT_DATE BETWEEN effective_from AND IFNULL(effective_to, '9999-12-31') LIMIT 1");
$stmt->execute([$user_id]);
$shift_info = $stmt->fetch(PDO::FETCH_ASSOC);

if ($shift_info && !empty($shift_info['weekly_offs'])) {
    $weekly_offs = explode(',', $shift_info['weekly_offs']);
    // Trim spaces and convert to lowercase for consistency
    $weekly_offs = array_map(function($day) {
        return strtolower(trim($day));
    }, $weekly_offs);
}

// If no weekly offs found, default to Sunday
if (empty($weekly_offs)) {
    $weekly_offs = ['sunday'];
}

// Fetch attendance data based on view_mode
if ($view_mode == 'monthly') {
    $start_date = sprintf("%04d-%02d-01", $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $query = "SELECT a.*, 
                    s.shift_name, s.start_time, s.end_time,
                    TIMEDIFF(a.punch_out, a.punch_in) as total_hours,
                    TIMESTAMPDIFF(HOUR, a.punch_in, a.punch_out) as hours_diff
              FROM attendance a
              LEFT JOIN user_shifts us ON a.user_id = us.user_id AND a.date BETWEEN us.effective_from AND IFNULL(us.effective_to, '9999-12-31')
              LEFT JOIN shifts s ON us.shift_id = s.id
              WHERE a.user_id = ? AND a.date BETWEEN ? AND ?
              ORDER BY a.date DESC, a.punch_in DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $start_date, $end_date]);
    
} elseif ($view_mode == 'range') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    
    $query = "SELECT a.*, 
                    s.shift_name, s.start_time, s.end_time,
                    TIMEDIFF(a.punch_out, a.punch_in) as total_hours,
                    TIMESTAMPDIFF(HOUR, a.punch_in, a.punch_out) as hours_diff
              FROM attendance a
              LEFT JOIN user_shifts us ON a.user_id = us.user_id AND a.date BETWEEN us.effective_from AND IFNULL(us.effective_to, '9999-12-31')
              LEFT JOIN shifts s ON us.shift_id = s.id
              WHERE a.user_id = ? AND a.date BETWEEN ? AND ?
              ORDER BY a.date DESC, a.punch_in DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $start_date, $end_date]);
}

// Fetch all attendance records
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_days = 0;
$working_days = 0;
$present_days = 0;
$absent_days = 0;
$half_days = 0;
$late_entries = 0;
$early_exits = 0;
$total_working_hours = 0;
$total_overtime_hours = 0;

// Array to store unique dates for counting
$unique_dates = [];

// Process each attendance record to calculate overtime locally
foreach ($attendance_records as $key => $record) {
    // Count unique dates as present
    if (!in_array($record['date'], $unique_dates)) {
        $unique_dates[] = $record['date'];
        $present_days++;
        
        // Check status for special counts
        if ($record['status'] == 'half-day') {
            $half_days++;
        }
    }
    
    // Calculate working hours if punch_out exists
    if (!empty($record['punch_out'])) {
        $punch_in = new DateTime($record['punch_in']);
        $punch_out = new DateTime($record['punch_out']);
        $interval = $punch_in->diff($punch_out);
        
        $hours = $interval->h + ($interval->days * 24);
        $minutes = $interval->i / 60;
        
        $working_hours = $hours + $minutes;
        $total_working_hours += $working_hours;
        
        // Calculate overtime locally based on shift end time
        if (!empty($record['end_time'])) {
            // Create DateTime objects for the actual punch-out time and the expected shift end time on the same day
            $actual_end_time = clone $punch_out;
            $expected_end_time = new DateTime($record['date'] . ' ' . $record['end_time']);
            
            // Only count as overtime if they punched out after their shift end time
            if ($actual_end_time > $expected_end_time) {
                // Calculate how many minutes the user worked beyond their shift end time
                $overtime_interval = $expected_end_time->diff($actual_end_time);
                $overtime_minutes = ($overtime_interval->h * 60) + $overtime_interval->i;
                
                // Only count as overtime if they worked at least 1 hour and 30 minutes (90 minutes) beyond shift end
                if ($overtime_minutes >= 90) {
                    // NEW ROUNDING RULE:
                    // If overtime is between 1:30 and 2:00, count as 1:30
                    // If overtime is between 2:00 and 2:30, count as 2:00
                    // And so on...
                    
                    // First, convert to hours and remaining minutes
                    $overtime_hours = floor($overtime_minutes / 60);
                    $remaining_minutes = $overtime_minutes % 60;
                    
                    // Apply rounding rule
                    if ($remaining_minutes > 0 && $remaining_minutes < 30) {
                        // Round down to the nearest half hour
                        if ($overtime_hours == 1) {
                            // Special case for 1:01-1:29 -> count as 1:30
                            $overtime_hours = 1;
                            $remaining_minutes = 30;
                        } else {
                            // For hours >= 2, round down to the exact hour
                            $remaining_minutes = 0;
                        }
                    } elseif ($remaining_minutes >= 30) {
                        // For minutes >= 30, always count as half hour
                        $remaining_minutes = 30;
                    }
                    
                    // Recalculate total overtime in hours (for calculations)
                    $overtime_hours_decimal = $overtime_hours + ($remaining_minutes / 60);
                    $total_overtime_hours += $overtime_hours_decimal;
                    
                    // Format for display: "X hour(s) and Y minute(s)"
                    $overtime_display = "";
                    if ($overtime_hours > 0) {
                        $overtime_display .= $overtime_hours . " hour" . ($overtime_hours > 1 ? "s" : "");
                        if ($remaining_minutes > 0) {
                            $overtime_display .= " and ";
                        }
                    }
                    if ($remaining_minutes > 0) {
                        $overtime_display .= $remaining_minutes . " minute" . ($remaining_minutes > 1 ? "s" : "");
                    }
                    
                    // Store overtime hours in the attendance_records array for display in the table
                    $attendance_records[$key]['overtime_hours'] = $overtime_display;
                    $attendance_records[$key]['overtime_hours_decimal'] = $overtime_hours_decimal; // Store decimal value for charts
                } else {
                    // No overtime if less than 90 minutes beyond shift end
                    $attendance_records[$key]['overtime_hours'] = null;
                    $attendance_records[$key]['overtime_hours_decimal'] = 0;
                }
            } else {
                // No overtime if punched out before shift end
                $attendance_records[$key]['overtime_hours'] = null;
                $attendance_records[$key]['overtime_hours_decimal'] = 0;
            }
        } else {
            // No overtime if no shift end time available
            $attendance_records[$key]['overtime_hours'] = null;
            $attendance_records[$key]['overtime_hours_decimal'] = 0;
        }
    }
    
    // Check for late entries
    if ($record['auto_punch_out'] == 1) {
        $early_exits++;
    }
}

// Calculate total days and working days in the period
if ($view_mode == 'monthly') {
    $total_days = date('t', strtotime($start_date));
    
    // Calculate working days for the month (excluding weekly offs)
    $current_date = new DateTime($start_date);
    $last_day = new DateTime($end_date);
    
    while ($current_date <= $last_day) {
        $day_of_week = strtolower($current_date->format('l')); // Monday, Tuesday, etc.
        if (!in_array($day_of_week, $weekly_offs)) {
            $working_days++;
        }
        $current_date->modify('+1 day');
    }
} else {
    // For date range view
    $period_start = new DateTime($start_date);
    $period_end = new DateTime($end_date);
    $interval = $period_start->diff($period_end);
    $total_days = $interval->days + 1;
    
    // Calculate working days for the date range (excluding weekly offs)
    $current_date = clone $period_start;
    
    while ($current_date <= $period_end) {
        $day_of_week = strtolower($current_date->format('l')); // Monday, Tuesday, etc.
        if (!in_array($day_of_week, $weekly_offs)) {
            $working_days++;
        }
        $current_date->modify('+1 day');
    }
}

$absent_days = $working_days - $present_days;
if ($absent_days < 0) $absent_days = 0; // Safeguard for edge cases

// Format statistics
$attendance_rate = ($present_days / ($working_days > 0 ? $working_days : 1)) * 100;
$attendance_rate = number_format($attendance_rate, 1);

// Format hours for display
$format_hours = function($hours) {
    $whole_hours = floor($hours);
    $minutes = round(($hours - $whole_hours) * 60);
    return sprintf("%02d:%02d", $whole_hours, $minutes);
};

// Format for display with text
$format_hours_text = function($hours) {
    $whole_hours = floor($hours);
    $minutes = round(($hours - $whole_hours) * 60);
    
    // Apply the same rounding rule as individual records
    if ($minutes > 0 && $minutes < 30) {
        if ($whole_hours == 1) {
            // Special case for 1:01-1:29 -> count as 1:30
            $minutes = 30;
        } else {
            // For hours >= 2, round down to the exact hour
            $minutes = 0;
        }
    } elseif ($minutes >= 30) {
        // For minutes >= 30, always count as half hour
        $minutes = 30;
    }
    
    $result = "";
    if ($whole_hours > 0) {
        $result .= $whole_hours . " hour" . ($whole_hours > 1 ? "s" : "");
        if ($minutes > 0) {
            $result .= " and ";
        }
    }
    if ($minutes > 0) {
        $result .= $minutes . " minute" . ($minutes > 1 ? "s" : "");
    }
    return $result;
};

$formatted_working_hours = $format_hours($total_working_hours);
$formatted_overtime_hours = $format_hours_text($total_overtime_hours);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Visualizer</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    <link rel="stylesheet" href="css/attendance_visualizer.css">
    
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Hamburger menu for mobile */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: #1e3246;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 12px 14px;
            z-index: 1000;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: background-color 0.3s;
        }
        
        .mobile-menu-toggle:hover {
            background-color: #283d52;
        }
        
        .mobile-menu-toggle i {
            font-size: 1.5rem;
        }
        
        /* Enhanced responsive table styles */
        @media (max-width: 991px) {
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table th, .table td {
                white-space: nowrap;
                min-width: 100px;
            }
            
            .dashboard-card {
                padding: 15px 10px;
            }
            
            .overview-card {
                margin-bottom: 15px;
            }
        }
        
        /* Styles for small screens like iPhone SE and XR */
        @media (max-width: 767px) {
            .mobile-menu-toggle {
                display: block;
            }
            
            .table-responsive {
                margin: 0 -10px; /* Negative margin to allow full-width scrolling */
            }
            
            .table th, .table td {
                padding: 8px 10px;
                font-size: 0.85rem;
            }
            
            .table th {
                position: sticky;
                top: 0;
                background-color: #f8f9fa;
                z-index: 10;
            }
            
            .badge {
                font-size: 70%;
                padding: 0.25em 0.4em;
            }
            
            .chart-container {
                height: 250px !important;
            }
            
            .filter-controls-wrapper {
                flex-direction: column;
            }
            
            .filter-row {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .filter-group {
                margin-bottom: 10px;
            }
            
            .header-title h2 {
                font-size: 1.5rem;
            }
            
            .header-card {
                padding: 15px 10px;
            }
            
            .overview-card .overview-details h3 {
                font-size: 1.3rem;
            }
        }
        
        /* Specifically for iPhone SE (smallest screen) */
        @media (max-width: 375px) {
            .table th, .table td {
                padding: 6px 8px;
                font-size: 0.8rem;
                min-width: 80px;
            }
            
            .dashboard-card .card-title {
                font-size: 1.1rem;
            }
            
            .overview-card .icon-box {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            
            .overview-card .overview-details h3 {
                font-size: 1.2rem;
            }
            
            .overview-card .overview-details p {
                font-size: 0.9rem;
            }
            
            .overview-card .overview-details small {
                font-size: 0.7rem;
            }
            
            .chart-container {
                height: 200px !important;
            }
        }
        
        /* Scrollable indicator for tables on mobile */
        .table-responsive::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 5px;
            background: linear-gradient(to right, rgba(0,0,0,0), rgba(0,0,0,0.1));
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .table-responsive:not(.no-scroll)::after {
            opacity: 1;
        }
        
        /* Fixed-width columns for better appearance */
        .table .date-column {
            min-width: 100px;
        }
        
        .table .time-column {
            min-width: 90px;
        }
        
        .table .address-column {
            min-width: 150px;
        }
        
        .table .hours-column {
            min-width: 100px;
        }
        
        .table .status-column {
            min-width: 85px;
        }
        
        .table .report-column {
            min-width: 150px;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Include Left Panel based on role -->
    <?php 
    if ($_SESSION['role'] == 'Site Supervisor') {
        include 'includes/supervisor_panel.php';
    } elseif ($_SESSION['role'] == 'Admin') {
        include 'includes/admin_panel.php';
    } else {
        include 'includes/worker_panel.php';
    }
    ?>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card header-card">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                            <div class="header-title mb-3 mb-md-0">
                                <div class="d-flex align-items-center">
                                    <div class="header-icon">
                                        <i class="fas fa-user-clock"></i>
                                    </div>
                                    <div>
                                        <h2>Attendance Visualizer</h2>
                                        <p class="text-muted mb-0">Track and analyze your attendance records</p>
                                    </div>
                                </div>
                            </div>
                            <div class="filters-container">
                                <form id="attendanceFilterForm" class="form-inline" method="GET" action="attendance_visualizer.php">
                                    <div class="filter-controls-wrapper">
                                        <div class="filter-row">
                                            <div class="filter-group">
                                                <label for="viewMode" class="filter-label">View</label>
                                                <select name="view" id="viewMode" class="form-control" onchange="toggleDateFields()">
                                                    <option value="monthly" <?php echo ($view_mode == 'monthly') ? 'selected' : ''; ?>>Monthly View</option>
                                                    <option value="range" <?php echo ($view_mode == 'range') ? 'selected' : ''; ?>>Date Range</option>
                                                </select>
                                            </div>
                                            
                                            <!-- Monthly View Filters -->
                                            <div id="monthlyFilters" class="filter-row-group" <?php echo ($view_mode == 'range') ? 'style="display:none;"' : ''; ?>>
                                                <div class="filter-group">
                                                    <label for="monthSelect" class="filter-label">Month</label>
                                                    <select name="month" id="monthSelect" class="form-control">
                                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                                            <option value="<?php echo $m; ?>" <?php echo ($month == $m) ? 'selected' : ''; ?>>
                                                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="filter-group">
                                                    <label for="yearSelect" class="filter-label">Year</label>
                                                    <select name="year" id="yearSelect" class="form-control">
                                                        <?php 
                                                        $current_year = date('Y');
                                                        for ($y = $current_year - 2; $y <= $current_year; $y++): 
                                                        ?>
                                                            <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : ''; ?>>
                                                                <?php echo $y; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- Date Range Filters -->
                                            <div id="rangeFilters" class="filter-row-group" <?php echo ($view_mode == 'monthly') ? 'style="display:none;"' : ''; ?>>
                                                <div class="filter-group">
                                                    <label for="startDate" class="filter-label">From</label>
                                                    <input type="date" id="startDate" name="start_date" class="form-control" 
                                                        value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); ?>">
                                                </div>
                                                <div class="range-separator">to</div>
                                                <div class="filter-group">
                                                    <label for="endDate" class="filter-label">To</label>
                                                    <input type="date" id="endDate" name="end_date" class="form-control" 
                                                        value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); ?>">
                                                </div>
                                            </div>
                                            
                                            <!-- Hidden user_id field to always use current user -->
                                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                            
                                            <div class="filter-group submit-group">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-filter mr-1"></i> Apply Filters
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Overview Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="dashboard-card overview-card">
                        <div class="icon-box bg-primary">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="overview-details">
                            <h3><?php echo $present_days; ?></h3>
                            <p>Present Days</p>
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" 
                                     style="width: <?php echo min(100, ($present_days / $working_days) * 100); ?>%" 
                                     aria-valuenow="<?php echo $present_days; ?>" aria-valuemin="0" 
                                     aria-valuemax="<?php echo $working_days; ?>"></div>
                            </div>
                            <small><?php echo $present_days; ?> of <?php echo $working_days; ?> working days</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="dashboard-card overview-card">
                        <div class="icon-box bg-success">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="overview-details">
                            <h3><?php echo $formatted_working_hours; ?></h3>
                            <p>Total Hours</p>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 100%" 
                                     aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small>Avg: <?php echo ($present_days > 0) ? $format_hours($total_working_hours / $present_days) : '00:00'; ?> per day</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="dashboard-card overview-card">
                        <div class="icon-box bg-info">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="overview-details">
                            <h3><?php echo $formatted_overtime_hours; ?></h3>
                            <p>Overtime Hours <i class="fas fa-info-circle" data-toggle="tooltip" title="Counted when working ≥ 1hr 30min beyond shift end. Overtime between 1:30-2:00 is counted as 1:30."></i></p>
                            <div class="progress">
                                <div class="progress-bar bg-info" role="progressbar" 
                                     style="width: <?php echo ($total_working_hours > 0) ? min(100, ($total_overtime_hours / $total_working_hours) * 100) : 0; ?>%" 
                                     aria-valuenow="<?php echo $total_overtime_hours; ?>" aria-valuemin="0" 
                                     aria-valuemax="<?php echo $total_working_hours; ?>"></div>
                            </div>
                            <small><?php echo number_format(($total_working_hours > 0) ? ($total_overtime_hours / $total_working_hours) * 100 : 0, 1); ?>% of total hours</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="dashboard-card overview-card">
                        <div class="icon-box bg-warning">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="overview-details">
                            <h3><?php echo $attendance_rate; ?>%</h3>
                            <p>Attendance Rate</p>
                            <div class="progress">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?php echo $attendance_rate; ?>%" 
                                     aria-valuenow="<?php echo $attendance_rate; ?>" aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                            <small><?php echo $absent_days; ?> days absent, <?php echo $half_days; ?> half days</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="dashboard-card">
                        <h4 class="card-title">Attendance History</h4>
                        <div class="chart-container">
                            <canvas id="attendanceChart" height="300"></canvas>
                        </div>
                        <div class="chart-legend mt-3 d-flex justify-content-center">
                            <div class="legend-item mr-4">
                                <span class="legend-color" style="background-color: rgba(66, 153, 225, 0.8);"></span>
                                <span class="legend-label">Regular Hours</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color" style="background-color: rgba(72, 187, 120, 0.8);"></span>
                                <span class="legend-label">Overtime Hours</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <div class="dashboard-card">
                        <h4 class="card-title">Attendance Distribution</h4>
                        <div class="chart-container">
                            <canvas id="distributionChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Attendance Table -->
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h4 class="card-title">Detailed Attendance Records</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th class="date-column">Date</th>
                                        <th class="time-column">Punch In</th>
                                        <th class="address-column">Punch In Address</th>
                                        <th class="time-column">Punch Out</th>
                                        <th class="address-column">Punch Out Address</th>
                                        <th class="hours-column">Working Hours</th>
                                        <th class="status-column">Status</th>
                                        <th class="hours-column">Overtime Hours <i class="fas fa-info-circle" data-toggle="tooltip" title="Counted when working ≥ 1hr 30min beyond shift end. Overtime between 1:30-2:00 is counted as 1:30."></i></th>
                                        <th class="report-column">Work Report <button id="exportWorkReportsBtn" class="btn btn-sm btn-outline-success ml-2" title="Export Work Reports"><i class="fas fa-file-excel"></i></button></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance_records)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No attendance records found for the selected period.</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                            <td>
                                                <?php 
                                                    if (!empty($record['punch_in'])) {
                                                        echo date('h:i A', strtotime($record['punch_in']));
                                                        // Add folder icon for punch in photo if available
                                                        if (!empty($record['punch_in_photo'])) {
                                                            echo ' <a href="#" class="photo-folder-link" data-toggle="modal" data-target="#photoModal" 
                                                                data-photo="' . htmlspecialchars($record['punch_in_photo']) . '" 
                                                                data-photo-fallback="uploads/attendance/' . htmlspecialchars($record['punch_in_photo']) . '"
                                                                data-title="Punch In Photo" 
                                                                data-date="' . htmlspecialchars($record['date']) . '"
                                                                data-time="' . date('h:i A', strtotime($record['punch_in'])) . '"
                                                                data-address="' . htmlspecialchars($record['address']) . '"
                                                                data-type="in"><i class="fas fa-folder text-primary"></i></a>';
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (!empty($record['address'])) {
                                                        echo '<span data-toggle="tooltip" title="' . htmlspecialchars($record['address']) . '">';
                                                        
                                                        // Truncate address text
                                                        $address = $record['address'];
                                                        if (strlen($address) > 20) {
                                                            $address = substr($address, 0, 17) . '...';
                                                        }
                                                        
                                                        echo htmlspecialchars($address);
                                                        echo '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (!empty($record['punch_out'])) {
                                                        echo date('h:i A', strtotime($record['punch_out']));
                                                        if ($record['auto_punch_out'] == 1) {
                                                            echo ' <span class="badge badge-warning" title="System auto punch-out">Auto</span>';
                                                        }
                                                        // Add folder icon for punch out photo if available
                                                        if (!empty($record['punch_out_photo'])) {
                                                            echo ' <a href="#" class="photo-folder-link" data-toggle="modal" data-target="#photoModal" 
                                                                data-photo="' . htmlspecialchars($record['punch_out_photo']) . '" 
                                                                data-photo-fallback="uploads/attendance/' . htmlspecialchars($record['punch_out_photo']) . '"
                                                                data-title="Punch Out Photo" 
                                                                data-date="' . htmlspecialchars($record['date']) . '"
                                                                data-time="' . date('h:i A', strtotime($record['punch_out'])) . '"
                                                                data-address="' . htmlspecialchars($record['punch_out_address'] ?? $record['address'] ?? '') . '"
                                                                data-type="out"><i class="fas fa-folder text-primary"></i></a>';
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (!empty($record['punch_out_address'])) {
                                                        echo '<span data-toggle="tooltip" title="' . htmlspecialchars($record['punch_out_address']) . '">';
                                                        
                                                        // Truncate address text
                                                        $punch_out_address = $record['punch_out_address'];
                                                        if (strlen($punch_out_address) > 20) {
                                                            $punch_out_address = substr($punch_out_address, 0, 17) . '...';
                                                        }
                                                        
                                                        echo htmlspecialchars($punch_out_address);
                                                        echo '</span>';
                                                    } else if (!empty($record['address'])) {
                                                        // Fallback to the general address column
                                                        echo '<span data-toggle="tooltip" title="' . htmlspecialchars($record['address']) . '">';
                                                        
                                                        // Truncate address text
                                                        $address = $record['address'];
                                                        if (strlen($address) > 20) {
                                                            $address = substr($address, 0, 17) . '...';
                                                        }
                                                        
                                                        echo htmlspecialchars($address);
                                                        echo '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (!empty($record['total_hours'])) {
                                                        // Format HH:MM:SS to HH:MM
                                                        $time_parts = explode(':', $record['total_hours']);
                                                        echo $time_parts[0] . ':' . $time_parts[1];
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $status_class = '';
                                                    $status_text = $record['status'] ?? 'present';
                                                    
                                                    switch ($status_text) {
                                                        case 'present':
                                                            $status_class = 'badge-success';
                                                            break;
                                                        case 'absent':
                                                            $status_class = 'badge-danger';
                                                            break;
                                                        case 'half-day':
                                                            $status_class = 'badge-warning';
                                                            $status_text = 'Half Day';
                                                            break;
                                                        case 'leave':
                                                            $status_class = 'badge-info';
                                                            $status_text = 'On Leave';
                                                            break;
                                                        case 'holiday':
                                                            $status_class = 'badge-primary';
                                                            $status_text = 'Holiday';
                                                            break;
                                                        default:
                                                            $status_class = 'badge-secondary';
                                                    }
                                                    
                                                    echo '<span class="badge ' . $status_class . '">' . ucfirst($status_text) . '</span>';
                                                ?>
                                            </td>
                                            
                                            <td>
                                                <?php 
                                                    if (!empty($record['overtime_hours'])) {
                                                        echo htmlspecialchars($record['overtime_hours']);
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (!empty($record['work_report'])) {
                                                        $date = date('d M Y', strtotime($record['date']));
                                                        $dayNumber = date('w', strtotime($record['date']));
                                                        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                                        $day = $dayNames[$dayNumber];
                                                        
                                                        echo '<span data-toggle="tooltip" title="' . htmlspecialchars($record['work_report']) . '">';
                                                        
                                                        // Truncate work report text
                                                        $work_report = $record['work_report'];
                                                        if (strlen($work_report) > 20) {
                                                            $work_report = substr($work_report, 0, 17) . '...';
                                                        }
                                                        
                                                        echo '<a href="#" class="work-report-link" data-toggle="modal" data-target="#workReportModal" 
                                                            data-report="' . htmlspecialchars($record['work_report']) . '" 
                                                            data-date="' . $date . '"
                                                            data-day="' . $day . '">' . htmlspecialchars($work_report) . '</a>';
                                                        echo '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include JS files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/attendance_visualizer.js"></script>
    
    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" role="dialog" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">Attendance Photo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="photoView">
                        <img id="attendancePhoto" src="" alt="Attendance photo" class="img-fluid">
                        <div class="photo-info mt-3">
                            <p><strong>Date:</strong> <span id="photoDate"></span></p>
                            <p><strong>Time:</strong> <span id="photoTime"></span></p>
                            <p><strong>Type:</strong> <span id="photoType"></span></p>
                            <p><strong>Address:</strong> <span id="photoAddress"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Work Report Modal -->
    <div class="modal fade" id="workReportModal" tabindex="-1" role="dialog" aria-labelledby="workReportModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="workReportModalLabel">Work Report Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="work-report-date-info mb-3">
                        <p><i class="far fa-calendar-alt mr-2"></i><span id="workReportDate"></span> (<span id="workReportDay"></span>)</p>
                    </div>
                    <div class="work-report-content p-3 bg-light rounded">
                        <p id="workReportText" class="mb-0"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize charts when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initAttendanceChart();
            initDistributionChart();
            
            // Add style for chart legend
            const style = document.createElement('style');
            style.textContent = `
                .chart-legend {
                    font-size: 0.9rem;
                }
                .legend-item {
                    display: flex;
                    align-items: center;
                }
                .legend-color {
                    display: inline-block;
                    width: 16px;
                    height: 16px;
                    margin-right: 6px;
                    border-radius: 3px;
                }
                .legend-label {
                    color: #4a5568;
                }
            `;
            document.head.appendChild(style);
        });
        
        // Function to initialize attendance history chart
        function initAttendanceChart() {
            // Prepare data for attendance history chart
            const labels = [];
            const regularHoursData = [];
            const overtimeHoursData = [];
            
            <?php 
            // Sort records by date
            $sorted_records = [];
            foreach ($attendance_records as $record) {
                $date = $record['date'];
                if (!isset($sorted_records[$date])) {
                    $sorted_records[$date] = [
                        'date' => $date,
                        'regular_hours' => 0,
                        'overtime_hours' => 0
                    ];
                }
                
                // Add regular hours if available
                if (!empty($record['total_hours'])) {
                    $time_parts = explode(':', $record['total_hours']);
                    $hours = intval($time_parts[0]) + (intval($time_parts[1]) / 60);
                    
                    // Use our locally calculated overtime hours (decimal value for charts)
                    $overtime = 0;
                    if (!empty($record['overtime_hours_decimal'])) {
                        $overtime = floatval($record['overtime_hours_decimal']);
                    }
                    
                    // Regular hours are total hours minus overtime
                    $regular_hours = max(0, $hours - $overtime);
                    
                    $sorted_records[$date]['regular_hours'] += $regular_hours;
                    $sorted_records[$date]['overtime_hours'] += $overtime;
                }
            }
            
            // Sort by date
            ksort($sorted_records);
            
            // Output JavaScript array
            foreach ($sorted_records as $date => $data) {
                $display_date = date('d M', strtotime($date));
                $regular_hours = round($data['regular_hours'], 2);
                $overtime_hours = round($data['overtime_hours'], 2);
                echo "labels.push('$display_date');\n";
                echo "regularHoursData.push($regular_hours);\n";
                echo "overtimeHoursData.push($overtime_hours);\n";
            }
            ?>
            
            // Create the chart
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Regular Hours',
                            data: regularHoursData,
                            backgroundColor: 'rgba(66, 153, 225, 0.8)',
                            borderColor: 'rgba(49, 130, 206, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            maxBarThickness: 40
                        },
                        {
                            label: 'Overtime Hours',
                            data: overtimeHoursData,
                            backgroundColor: 'rgba(72, 187, 120, 0.8)',
                            borderColor: 'rgba(56, 161, 105, 1)',
                            borderWidth: 1,
                            borderRadius: {
                                topLeft: 4,
                                topRight: 4,
                                bottomLeft: 0,
                                bottomRight: 0
                            },
                            maxBarThickness: 40
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            right: 25,
                            bottom: 20,
                            left: 25
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    size: 12
                                },
                                color: '#718096',
                                padding: 10
                            },
                            title: {
                                display: true,
                                text: 'Date',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                color: '#4a5568',
                                padding: {top: 10, bottom: 0}
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(160, 174, 192, 0.15)',
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    size: 12
                                },
                                color: '#718096',
                                padding: 10
                            },
                            title: {
                                display: true,
                                text: 'Hours',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                color: '#4a5568',
                                padding: {top: 0, bottom: 10}
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Daily Working Hours with Overtime (≥ 1hr 30min beyond shift)',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            color: '#2d3748',
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(45, 55, 72, 0.9)',
                            titleFont: {
                                size: 13
                            },
                            bodyFont: {
                                size: 13
                            },
                            padding: 12,
                            cornerRadius: 6,
                            callbacks: {
                                title: function(tooltipItems) {
                                    return tooltipItems[0].label;
                                },
                                label: function(context) {
                                    const datasetLabel = context.dataset.label;
                                    const value = context.parsed.y;
                                    return `${datasetLabel}: ${value.toFixed(2)} hrs`;
                                },
                                footer: function(tooltipItems) {
                                    const regularHours = parseFloat(tooltipItems[0].parsed.y || 0);
                                    const overtimeHours = tooltipItems.length > 1 ? parseFloat(tooltipItems[1].parsed.y || 0) : 0;
                                    const total = regularHours + overtimeHours;
                                    return `Total: ${total.toFixed(2)} hrs`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Function to initialize attendance distribution chart
        function initDistributionChart() {
            // Attendance distribution data
            const present = <?php echo $present_days; ?>;
            const absent = <?php echo $absent_days; ?>;
            const halfDays = <?php echo $half_days; ?>;
            
            // Create the chart
            const ctx = document.getElementById('distributionChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent', 'Half Days'],
                    datasets: [{
                        data: [present, absent, halfDays],
                        backgroundColor: [
                            'rgba(56, 161, 105, 0.85)',  // green
                            'rgba(229, 62, 62, 0.85)',   // red
                            'rgba(237, 137, 54, 0.85)'   // orange
                        ],
                        borderColor: [
                            'rgba(56, 161, 105, 1)',
                            'rgba(229, 62, 62, 1)',
                            'rgba(237, 137, 54, 1)'
                        ],
                        borderWidth: 2,
                        hoverBorderWidth: 3,
                        hoverBorderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    layout: {
                        padding: {
                            top: 20,
                            right: 20,
                            bottom: 20,
                            left: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                color: '#4a5568',
                                font: {
                                    size: 13
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Attendance Distribution',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            color: '#2d3748',
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(45, 55, 72, 0.9)',
                            titleFont: {
                                size: 13
                            },
                            bodyFont: {
                                size: 13
                            },
                            padding: 12,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Add additional styles for folder icon
            const style = document.createElement('style');
            style.textContent = `
                .photo-folder-link {
                    margin-left: 5px;
                    text-decoration: none;
                }
                .photo-folder-link:hover {
                    opacity: 0.8;
                }
            `;
            document.head.appendChild(style);
            
            // Handle opening the modal with fallback image loading
            $('#photoModal').on('show.bs.modal', function (event) {
                const link = $(event.relatedTarget);
                const photoUrl = link.data('photo');
                const photoFallbackUrl = link.data('photo-fallback');
                const photoDate = link.data('date');
                const photoTime = link.data('time');
                const photoType = link.data('type');
                const photoAddress = link.data('address');
                
                // Set photo information
                $('#photoDate').text(photoDate);
                $('#photoTime').text(photoTime);
                $('#photoType').text(photoType === 'in' ? 'Punch In' : 'Punch Out');
                
                // Use the provided address or fallback to a default message
                $('#photoAddress').text(photoAddress && photoAddress !== 'null' ? photoAddress : 'Address not available');
                
                // Try to load the image with fallback paths
                const imgElement = $('#attendancePhoto')[0];
                
                // First try the primary path
                imgElement.src = photoUrl;
                
                // If primary path fails, try the fallback path
                imgElement.onerror = function() {
                    console.log('Primary image path failed, trying fallback path:', photoFallbackUrl);
                    imgElement.src = photoFallbackUrl;
                    
                    // If fallback also fails, show a placeholder
                    imgElement.onerror = function() {
                        console.log('Both image paths failed, showing placeholder');
                        imgElement.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIE5vdCBGb3VuZDwvdGV4dD48L3N2Zz4=';
                    };
                };
            });
        });
        
        // Function to export work reports to Excel
        function exportWorkReports() {
            // Get current month and year from filters
            const currentMonth = document.getElementById('monthSelect')?.value || new Date().getMonth() + 1;
            const currentYear = document.getElementById('yearSelect')?.value || new Date().getFullYear();
            
            // Create a loading spinner
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm ml-1';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');
            
            // Add spinner to button
            const exportBtn = document.getElementById('exportWorkReportsBtn');
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<i class="fas fa-file-excel"></i>' + spinner.outerHTML;
            
            // Collect data from the table
            const workReportData = [];
            
            // Get all table rows
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(row => {
                const columns = row.querySelectorAll('td');
                if (columns.length >= 9) { // Make sure row has enough columns
                    const dateText = columns[0].textContent.trim();
                    
                    // Only include rows with valid dates and work reports
                    if (dateText !== '-') {
                        const date = new Date(dateText);
                        const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        const day = dayNames[date.getDay()];
                        
                        // Get work report (remove tooltip attributes if present)
                        let workReport = columns[8].textContent.trim();
                        if (workReport === '-') workReport = '';
                        
                        workReportData.push({
                            date: dateText,
                            day: day,
                            workReport: workReport
                        });
                    }
                }
            });
            
            // Generate Excel file
            setTimeout(() => {
                try {
                    // Create a proper HTML table for Excel with bold headers
                    let excelContent = `
                    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                    <head>
                        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                        <!--[if gte mso 9]>
                        <xml>
                            <x:ExcelWorkbook>
                                <x:ExcelWorksheets>
                                    <x:ExcelWorksheet>
                                        <x:Name>Work Reports</x:Name>
                                        <x:WorksheetOptions>
                                            <x:DisplayGridlines/>
                                        </x:WorksheetOptions>
                                    </x:ExcelWorksheet>
                                </x:ExcelWorksheets>
                            </x:ExcelWorkbook>
                        </xml>
                        <![endif]-->
                        <style>
                            td, th { border: 0.5pt solid #c0c0c0; }
                            .header { font-weight: bold; background-color: #f0f0f0; }
                        </style>
                    </head>
                    <body>
                        <table>
                            <tr class="header">
                                <th><b>Date</b></th>
                                <th><b>Day</b></th>
                                <th><b>Work Report</b></th>
                            </tr>
                    `;
                    
                    // Add data rows
                    workReportData.forEach(item => {
                        excelContent += `
                            <tr>
                                <td>${item.date}</td>
                                <td>${item.day}</td>
                                <td>${item.workReport.replace(/\n/g, '<br>')}</td>
                            </tr>
                        `;
                    });
                    
                    // Close HTML table
                    excelContent += `
                        </table>
                    </body>
                    </html>
                    `;
                    
                    // Create a Blob with the HTML content
                    const blob = new Blob([excelContent], { type: 'application/vnd.ms-excel' });
                    const url = URL.createObjectURL(blob);
                    
                    // Create a download link
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `Work_Reports_${currentYear}_${currentMonth}.xls`;
                    
                    // Append link, trigger click and remove
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Revoke the URL to free up memory
                    setTimeout(() => {
                        URL.revokeObjectURL(url);
                    }, 1000);
                    
                    // Show success message
                    showToast('Success', 'Work reports exported successfully', 'success');
                } catch (error) {
                    console.error('Export error:', error);
                    showToast('Error', 'Failed to export work reports', 'danger');
                } finally {
                    // Restore button
                    exportBtn.innerHTML = originalContent;
                    exportBtn.disabled = false;
                }
            }, 800); // Short delay to show loading effect
        }
        
        // Function to show toast notifications
        function showToast(title, message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.style.position = 'fixed';
                toastContainer.style.top = '20px';
                toastContainer.style.right = '20px';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast bg-${type} text-white`;
            toast.style.minWidth = '250px';
            toast.style.margin = '0 0 10px 0';
            toast.style.padding = '15px';
            toast.style.borderRadius = '4px';
            toast.style.boxShadow = '0 0.25rem 0.75rem rgba(0, 0, 0, 0.1)';
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            
            // Toast content
            toast.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong>${title}</strong>
                    <button type="button" class="ml-2 mb-1 close" aria-label="Close" style="color: white; opacity: 0.8;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div>${message}</div>
            `;
            
            // Add to container
            toastContainer.appendChild(toast);
            
            // Show toast with delay for animation
            setTimeout(() => {
                toast.style.opacity = '1';
            }, 10);
            
            // Add close button functionality
            const closeButton = toast.querySelector('.close');
            closeButton.addEventListener('click', () => {
                toast.style.opacity = '0';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            });
            
            // Auto-close after 5 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 5000);
        }
        
        // Add event listener to the export button
        document.addEventListener('DOMContentLoaded', function() {
            const exportBtn = document.getElementById('exportWorkReportsBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', exportWorkReports);
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            // Add style for work report links
            const style = document.createElement('style');
            style.textContent = `
                .work-report-link {
                    cursor: pointer;
                    color: #007bff;
                    text-decoration: none;
                }
                .work-report-link:hover {
                    text-decoration: underline;
                    color: #0056b3;
                }
                .work-report-content {
                    white-space: pre-wrap;
                    font-size: 0.95rem;
                    line-height: 1.5;
                    max-height: 300px;
                    overflow-y: auto;
                }
                .work-report-date-info {
                    color: #6c757d;
                    font-size: 1rem;
                    border-bottom: 1px solid #dee2e6;
                    padding-bottom: 10px;
                }
            `;
            document.head.appendChild(style);
            
            // Handle opening the work report modal
            $('#workReportModal').on('show.bs.modal', function (event) {
                const link = $(event.relatedTarget);
                const workReport = link.data('report');
                const reportDate = link.data('date');
                const reportDay = link.data('day');
                
                // Populate modal content
                $('#workReportText').text(workReport);
                $('#workReportDate').text(reportDate);
                $('#workReportDay').text(reportDay);
            });
        });
    </script>
</body>
</html> 