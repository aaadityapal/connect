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
$present_days = 0;
$absent_days = 0;
$half_days = 0;
$late_entries = 0;
$early_exits = 0;
$total_working_hours = 0;
$total_overtime_hours = 0;

// Array to store unique dates for counting
$unique_dates = [];

foreach ($attendance_records as $record) {
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
        
        // If shift info exists, calculate overtime
        if (!empty($record['start_time']) && !empty($record['end_time'])) {
            $shift_start = new DateTime($record['start_time']);
            $shift_end = new DateTime($record['end_time']);
            $shift_interval = $shift_start->diff($shift_end);
            
            $shift_hours = $shift_interval->h + ($shift_interval->days * 24);
            $shift_minutes = $shift_interval->i / 60;
            $shift_duration = $shift_hours + $shift_minutes;
            
            if ($working_hours > $shift_duration) {
                $total_overtime_hours += ($working_hours - $shift_duration);
            }
        }
    }
    
    // Check for late entries
    if ($record['auto_punch_out'] == 1) {
        $early_exits++;
    }
}

// Calculate total days in the month for showing attendance rate
if ($view_mode == 'monthly') {
    $total_days = date('t', strtotime($start_date));
    $absent_days = $total_days - $present_days;
} else {
    $period_start = new DateTime($start_date);
    $period_end = new DateTime($end_date);
    $interval = $period_start->diff($period_end);
    $total_days = $interval->days + 1;
    $absent_days = $total_days - $present_days;
}

// Format statistics
$attendance_rate = ($present_days / $total_days) * 100;
$attendance_rate = number_format($attendance_rate, 1);

// Format hours for display
$format_hours = function($hours) {
    $whole_hours = floor($hours);
    $minutes = round(($hours - $whole_hours) * 60);
    return sprintf("%02d:%02d", $whole_hours, $minutes);
};

$formatted_working_hours = $format_hours($total_working_hours);
$formatted_overtime_hours = $format_hours($total_overtime_hours);
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
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
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
                                     style="width: <?php echo min(100, ($present_days / $total_days) * 100); ?>%" 
                                     aria-valuenow="<?php echo $present_days; ?>" aria-valuemin="0" 
                                     aria-valuemax="<?php echo $total_days; ?>"></div>
                            </div>
                            <small><?php echo $present_days; ?> of <?php echo $total_days; ?> days</small>
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
                            <p>Overtime Hours</p>
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
                                        <th>Date</th>
                                        <th>Shift</th>
                                        <th>Punch In</th>
                                        <th>Punch Out</th>
                                        <th>Working Hours</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance_records)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No attendance records found for the selected period.</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                            <td><?php echo $record['shift_name'] ?? 'Regular'; ?></td>
                                            <td>
                                                <?php 
                                                    if (!empty($record['punch_in'])) {
                                                        echo date('h:i A', strtotime($record['punch_in']));
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
                                                    if (!empty($record['location'])) {
                                                        echo '<span data-toggle="tooltip" title="' . htmlspecialchars($record['location']) . '">';
                                                        echo '<i class="fas fa-map-marker-alt"></i> ';
                                                        
                                                        // Truncate location text
                                                        $location = $record['location'];
                                                        if (strlen($location) > 20) {
                                                            $location = substr($location, 0, 17) . '...';
                                                        }
                                                        
                                                        echo htmlspecialchars($location);
                                                        echo '</span>';
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
                                                    if (!empty($record['remarks'])) {
                                                        echo '<span data-toggle="tooltip" title="' . htmlspecialchars($record['remarks']) . '">';
                                                        
                                                        // Truncate remarks text
                                                        $remarks = $record['remarks'];
                                                        if (strlen($remarks) > 20) {
                                                            $remarks = substr($remarks, 0, 17) . '...';
                                                        }
                                                        
                                                        echo htmlspecialchars($remarks);
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
    
    <script>
        // Initialize charts when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initAttendanceChart();
            initDistributionChart();
        });
        
        // Function to initialize attendance history chart
        function initAttendanceChart() {
            // Prepare data for attendance history chart
            const labels = [];
            const workingHoursData = [];
            
            <?php 
            // Sort records by date
            $sorted_records = [];
            foreach ($attendance_records as $record) {
                $date = $record['date'];
                if (!isset($sorted_records[$date])) {
                    $sorted_records[$date] = [
                        'date' => $date,
                        'hours' => 0
                    ];
                }
                
                // Add hours if available
                if (!empty($record['total_hours'])) {
                    $time_parts = explode(':', $record['total_hours']);
                    $hours = intval($time_parts[0]) + (intval($time_parts[1]) / 60);
                    $sorted_records[$date]['hours'] += $hours;
                }
            }
            
            // Sort by date
            ksort($sorted_records);
            
            // Output JavaScript array
            foreach ($sorted_records as $date => $data) {
                $display_date = date('d M', strtotime($date));
                $hours = round($data['hours'], 2);
                echo "labels.push('$display_date');\n";
                echo "workingHoursData.push($hours);\n";
            }
            ?>
            
            // Create the chart
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Working Hours',
                        data: workingHoursData,
                        backgroundColor: 'rgba(66, 153, 225, 0.8)',
                        borderColor: 'rgba(49, 130, 206, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                        maxBarThickness: 40
                    }]
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
                        },
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
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#4a5568',
                                font: {
                                    size: 13,
                                    weight: 'bold'
                                },
                                padding: 20
                            }
                        },
                        title: {
                            display: true,
                            text: 'Daily Working Hours',
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
                            displayColors: false,
                            callbacks: {
                                title: function(tooltipItems) {
                                    return tooltipItems[0].label;
                                },
                                label: function(context) {
                                    return `Hours worked: ${context.raw.toFixed(2)} hrs`;
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
    </script>
</body>
</html> 